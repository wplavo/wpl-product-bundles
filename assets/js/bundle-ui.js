/**
 * Bundle UI — selection tracking, client-side validation, and live price display.
 *
 * Initialized from window.wplBundleConfig (set via wp_localize_script).
 * Never trusts its own validation as a security gate — server-side
 * SelectionValidator runs again on form submission.
 *
 * Live price estimation is available for per_component pricing mode only
 * (per_bundle uses the WC product price; tiered requires server-side tier lookup).
 */
/* global wplBundleConfig */
( function () {
	'use strict';

	/**
	 * @typedef {Object} ComponentConfig
	 * @property {string}   id
	 * @property {number}   productId
	 * @property {string}   productName
	 * @property {string}   unitPrice         Raw numeric string e.g. "12.50"
	 * @property {string}   componentDiscount Percentage e.g. "10.00"
	 * @property {boolean}  isInStock
	 * @property {number|null} variationId
	 * @property {number}   quantityMin
	 * @property {number}   quantityMax   0 = unlimited
	 * @property {number}   quantityDefault
	 * @property {boolean}  optional
	 * @property {string}   priceMode
	 * @property {number[]} allowedProducts
	 * @property {number[]} allowedVariations
	 * @property {Array}    allowedProductsData
	 */

	/**
	 * @typedef {Object} Selection
	 * @property {number}      productId
	 * @property {number|null} variationId
	 * @property {number}      qty
	 * @property {boolean}     included
	 */

	class BundleUI {

		/**
		 * @param {HTMLFormElement} form
		 * @param {Object} config  From window.wplBundleConfig
		 */
		constructor( form, config ) {
			this.form    = form;
			this.config  = config;
			/** @type {Object.<string, Selection>} */
			this.selections = {};
			this.errors     = {};
			this.totalQty   = 0;

			this._initSelections();
			this._bindEvents();
			this._validate();
			this._updateUI();
		}

		// ── Initialization ────────────────────────────────────────────────

		_initSelections() {
			this.config.components.forEach( ( /** @type {ComponentConfig} */ component ) => {
				this.selections[ component.id ] = {
					productId:   component.productId,
					variationId: component.variationId,
					qty:         component.quantityDefault,
					included:    ! component.optional,
				};
			} );
		}

		_bindEvents() {
			this.form.addEventListener( 'change', this._handleChange.bind( this ) );
			this.form.addEventListener( 'input',  this._handleChange.bind( this ) );
			this.form.addEventListener( 'submit', this._handleSubmit.bind( this ) );
		}

		// ── Event handlers ────────────────────────────────────────────────

		_handleChange( event ) {
			const target      = /** @type {HTMLInputElement|HTMLSelectElement} */ ( event.target );
			const componentId = target.dataset.componentId;
			if ( ! componentId || ! ( componentId in this.selections ) ) {
				return;
			}

			const type = target.dataset.type;

			if ( type === 'optional-toggle' ) {
				this.selections[ componentId ].included =
					/** @type {HTMLInputElement} */ ( target ).checked;
			} else if ( type === 'quantity' ) {
				const raw = parseInt( /** @type {HTMLInputElement} */ ( target ).value, 10 );
				this.selections[ componentId ].qty = isNaN( raw ) ? 0 : raw;
			} else if ( type === 'product-select' ) {
				const val = /** @type {HTMLSelectElement} */ ( target ).value;
				this.selections[ componentId ].productId   = val ? parseInt( val, 10 ) : 0;
				this.selections[ componentId ].variationId = null;
				this.selections[ componentId ].included    = val !== '';
			}

			this._validate();
			this._updateUI();
		}

		_handleSubmit( event ) {
			this._validate();
			if ( Object.keys( this.errors ).length > 0 ) {
				event.preventDefault();
				this._updateUI();
				this._focusFirstError();
			}
		}

		// ── Validation ────────────────────────────────────────────────────

		_validate() {
			/** @type {Object.<string, string[]>} */
			const errors = {};
			let totalQty  = 0;

			this.config.components.forEach( ( /** @type {ComponentConfig} */ component ) => {
				const sel = this.selections[ component.id ];

				if ( ! sel || ! sel.included ) {
					if ( ! component.optional ) {
						errors[ component.id ] = errors[ component.id ] || [];
						errors[ component.id ].push( 'required' );
					}
					return;
				}

				// M&M: product must be selected.
				if ( this.config.mode === 'mix_and_match' && ! sel.productId ) {
					errors[ component.id ] = errors[ component.id ] || [];
					errors[ component.id ].push( 'no_product_selected' );
					return;
				}

				// Quantity minimum.
				if ( sel.qty < component.quantityMin ) {
					errors[ component.id ] = errors[ component.id ] || [];
					errors[ component.id ].push( 'qty_too_low' );
				}

				// Quantity maximum (0 = unlimited).
				if ( component.quantityMax > 0 && sel.qty > component.quantityMax ) {
					errors[ component.id ] = errors[ component.id ] || [];
					errors[ component.id ].push( 'qty_too_high' );
				}

				// Zero quantity when included is an error.
				if ( sel.qty <= 0 ) {
					errors[ component.id ] = errors[ component.id ] || [];
					if ( ! errors[ component.id ].includes( 'qty_too_low' ) ) {
						errors[ component.id ].push( 'qty_too_low' );
					}
				}

				totalQty += sel.qty;
			} );

			// Bundle-level total for M&M.
			if ( this.config.mode === 'mix_and_match' ) {
				if ( totalQty < this.config.minQty ) {
					errors[ '_bundle' ] = [ 'total_qty_below_min' ];
				}
				if ( this.config.maxQty > 0 && totalQty > this.config.maxQty ) {
					errors[ '_bundle' ] = errors[ '_bundle' ] || [];
					errors[ '_bundle' ].push( 'total_qty_above_max' );
				}
			}

			this.errors   = errors;
			this.totalQty = totalQty;
			return Object.keys( errors ).length === 0;
		}

		// ── UI updates ────────────────────────────────────────────────────

		_updateUI() {
			this._updateComponentErrors();
			this._updateGlobalError();
			this._updateProgress();
			this._updateSubmitButton();
			this._updatePriceTotal();
		}

		_updateComponentErrors() {
			this.config.components.forEach( ( /** @type {ComponentConfig} */ component ) => {
				const codes   = this.errors[ component.id ] || [];
				const errorEl = this.form.querySelector( '[data-error-for="' + component.id + '"]' );
				if ( ! errorEl ) {
					return;
				}
				if ( codes.length > 0 ) {
					errorEl.textContent    = codes.map( ( c ) => this._message( c, component ) ).join( ' ' );
					errorEl.style.display  = 'block';
				} else {
					errorEl.textContent    = '';
					errorEl.style.display  = 'none';
				}
			} );
		}

		_updateGlobalError() {
			const globalEl = this.form.querySelector( '.wpl-bundle-error--global' );
			if ( ! globalEl ) {
				return;
			}
			const bundleErrors = this.errors[ '_bundle' ] || [];
			if ( bundleErrors.length > 0 ) {
				globalEl.textContent   = bundleErrors.map( ( c ) => this._message( c, null ) ).join( ' ' );
				globalEl.style.display = 'block';
			} else {
				globalEl.textContent   = '';
				globalEl.style.display = 'none';
			}
		}

		_updateProgress() {
			if ( this.config.mode !== 'mix_and_match' ) {
				return;
			}

			const max       = this.config.maxQty > 0 ? this.config.maxQty : this.config.minQty;
			const labelEl   = this.form.querySelector( '.wpl-bundle-progress-label' );
			const barEl     = this.form.querySelector( '.wpl-bundle-progress-bar' );
			const fillEl    = this.form.querySelector( '.wpl-bundle-progress-bar-fill' );

			if ( labelEl ) {
				labelEl.textContent = this.totalQty + ' / ' + max + ' ' + ( this.config.i18n.items || 'items' );
			}

			if ( barEl ) {
				barEl.setAttribute( 'aria-valuenow', String( this.totalQty ) );
			}

			if ( fillEl ) {
				const pct = max > 0 ? Math.min( 100, ( this.totalQty / max ) * 100 ) : 0;
				/** @type {HTMLElement} */ ( fillEl ).style.width = pct + '%';
			}
		}

		_updateSubmitButton() {
			const btn = this.form.querySelector( '.wpl-bundle-submit' );
			if ( ! btn ) {
				return;
			}
			const isValid = Object.keys( this.errors ).length === 0;
			/** @type {HTMLButtonElement} */ ( btn ).disabled = ! isValid;
			btn.classList.toggle( 'disabled', ! isValid );
		}

		/**
		 * Calculates and displays the live estimated price for per_component pricing mode.
		 * For per_bundle and tiered modes, hides the estimate (server-side price is authoritative).
		 */
		_updatePriceTotal() {
			const totalEl   = this.form.querySelector( '.wpl-bundle-price-total' );
			const savingsEl = this.form.querySelector( '.wpl-bundle-price-savings' );
			if ( ! totalEl ) {
				return;
			}

			if ( this.config.priceMode !== 'per_component' ) {
				totalEl.style.display = 'none';
				if ( savingsEl ) {
					savingsEl.style.display = 'none';
				}
				return;
			}

			const bundleDiscountPct = parseFloat( this.config.discount ) || 0;
			const bundleFactor      = 1.0 - bundleDiscountPct / 100.0;
			let   totalPrice        = 0;
			let   undiscountedTotal = 0;

			this.config.components.forEach( ( /** @type {ComponentConfig} */ component ) => {
				const sel = this.selections[ component.id ];
				if ( ! sel || ! sel.included || sel.qty <= 0 ) {
					return;
				}

				const unitPrice = parseFloat( component.unitPrice ) || 0;
				if ( unitPrice <= 0 ) {
					return;
				}

				const compDiscountPct = parseFloat( component.componentDiscount ) || 0;
				const compFactor      = 1.0 - compDiscountPct / 100.0;
				const effectiveUnit   = unitPrice * compFactor * bundleFactor;

				totalPrice        += effectiveUnit * sel.qty;
				undiscountedTotal += unitPrice * sel.qty;
			} );

			const i18n     = this.config.i18n || {};
			const label    = i18n.estTotal || 'Estimated total:';
			const currency = this.config.currency || {};

			totalEl.style.display = 'block';
			totalEl.innerHTML =
				'<span class="wpl-est-label">' + this._escHtml( label ) + '</span> ' +
				'<span class="wpl-est-price">' + this._formatPrice( totalPrice, currency ) + '</span>';

			const savings = undiscountedTotal - totalPrice;
			if ( savingsEl ) {
				if ( savings > 0.001 ) {
					const savingsLabel = i18n.savings || 'You save:';
					savingsEl.style.display = 'block';
					savingsEl.innerHTML =
						'<span class="wpl-savings-label">' + this._escHtml( savingsLabel ) + '</span> ' +
						'<span class="wpl-savings-amount">' + this._formatPrice( savings, currency ) + '</span>';
				} else {
					savingsEl.style.display = 'none';
				}
			}
		}

		// ── Helpers ───────────────────────────────────────────────────────

		_focusFirstError() {
			const firstVisible = this.form.querySelector( '[data-error-for]:not([style*="display: none"])' );
			if ( firstVisible ) {
				firstVisible.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			}
		}

		/**
		 * @param {string}              code
		 * @param {ComponentConfig|null} component
		 * @returns {string}
		 */
		_message( code, component ) {
			const i18n = this.config.i18n || {};
			switch ( code ) {
				case 'required':
					return i18n.required || 'Required.';
				case 'no_product_selected':
					return i18n.selectProduct || 'Please select a product.';
				case 'qty_too_low':
					if ( component && i18n.qtyTooLow ) {
						return i18n.qtyTooLow.replace( '%d', String( component.quantityMin ) );
					}
					return component
						? 'Minimum quantity: ' + component.quantityMin + '.'
						: 'Quantity too low.';
				case 'qty_too_high':
					if ( component && i18n.qtyTooHigh ) {
						return i18n.qtyTooHigh.replace( '%d', String( component.quantityMax ) );
					}
					return component
						? 'Maximum quantity: ' + component.quantityMax + '.'
						: 'Quantity too high.';
				case 'total_qty_below_min':
					if ( i18n.totalBelowMin ) {
						return i18n.totalBelowMin.replace( '%d', String( this.config.minQty ) );
					}
					return 'Please select at least ' + this.config.minQty + ' item(s) in total.';
				case 'total_qty_above_max':
					if ( i18n.totalAboveMax ) {
						return i18n.totalAboveMax.replace( '%d', String( this.config.maxQty ) );
					}
					return 'You may select at most ' + this.config.maxQty + ' item(s) in total.';
				default:
					return code;
			}
		}

		/**
		 * Formats a numeric price using the store currency settings.
		 *
		 * @param {number} amount
		 * @param {{symbol: string, position: string, decimals: number, decSep: string, thouSep: string}} currency
		 * @returns {string}
		 */
		_formatPrice( amount, currency ) {
			const decimals  = currency.decimals ?? 2;
			const decSep    = currency.decSep   ?? '.';
			const thouSep   = currency.thouSep  ?? ',';
			const symbol    = currency.symbol   ?? '';
			const position  = currency.position ?? 'left';

			const fixed    = amount.toFixed( decimals );
			const parts    = fixed.split( '.' );
			const intPart  = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, thouSep );
			const decPart  = parts[ 1 ] ?? '';
			const formatted = intPart + ( decPart !== '' ? decSep + decPart : '' );

			switch ( position ) {
				case 'right':      return formatted + symbol;
				case 'left_space': return symbol + ' ' + formatted;
				case 'right_space': return formatted + ' ' + symbol;
				default:           return symbol + formatted;
			}
		}

		/**
		 * Minimal HTML escaping for safe innerHTML assignment.
		 *
		 * @param {string} str
		 * @returns {string}
		 */
		_escHtml( str ) {
			return str
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' );
		}
	}

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	function init() {
		const form = document.querySelector( '.wpl-bundle-form' );
		if ( ! form ) {
			return;
		}
		if ( typeof window.wplBundleConfig === 'undefined' ) {
			return;
		}
		new BundleUI(
			/** @type {HTMLFormElement} */ ( form ),
			window.wplBundleConfig
		);
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
