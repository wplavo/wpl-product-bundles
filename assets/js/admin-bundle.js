/**
 * WPL Product Bundles — Admin Bundle Configuration UI
 *
 * Handles the bundle product data tab in the WooCommerce product editor:
 *   – Show/hide fields based on mode and price_mode selections.
 *   – Add, remove, and reorder component rows.
 *   – AJAX product search via select2 (excludes bundle products).
 *   – Load variation options when a variable product is selected.
 *   – Add / remove price tier rows.
 *
 * Requires: jQuery, select2 (both provided by WooCommerce admin).
 * Config injected via wp_localize_script as window.wplAdminBundle.
 */
/* global wplAdminBundle, jQuery */
( function ( $ ) {
	'use strict';

	const cfg  = window.wplAdminBundle || {};
	const i18n = cfg.i18n || {};

	// ── Unique index counters ─────────────────────────────────────────────────

	let tierIndex = 0;

	function nextTierIndex() {
		// Start above any existing indices to avoid collisions.
		return 'new_' + ( ++tierIndex );
	}

	// ── UUID generation (RFC 4122 v4) ─────────────────────────────────────────

	function generateUUID() {
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function ( c ) {
			const r = ( Math.random() * 16 ) | 0;
			const v = c === 'x' ? r : ( r & 0x3 ) | 0x8;
			return v.toString( 16 );
		} );
	}

	// ── Visibility management ─────────────────────────────────────────────────

	function updatePanelVisibility() {
		const mode      = $( '#wpl_bundle_mode' ).val();
		const priceMode = $( '#wpl_bundle_price_mode' ).val();

		// Mix & Match only fields.
		const isMM = mode === 'mix_and_match';
		$( '.wpl-field-mm-qty' ).toggle( isMM );
		$( '.wpl-component-row .wpl-field-mm-allowed' ).toggle( isMM );

		// Tiered pricing table.
		$( '.wpl-field-tiered' ).toggle( priceMode === 'tiered' );

		// Bundle-level discount field.
		const showDiscount = priceMode === 'per_bundle' || priceMode === 'per_component';
		$( '.wpl-field-bundle-discount' ).toggle( showDiscount );
	}

	// ── Product search (select2) ──────────────────────────────────────────────

	function initSingleProductSearch( $select ) {
		if ( ! $select.length || $select.hasClass( 'select2-hidden-accessible' ) ) {
			return;
		}
		$select.select2( {
			minimumInputLength: 2,
			placeholder:        i18n.searchProducts || 'Search for a product…',
			allowClear:         true,
			width:              '300px',
			ajax: {
				url:      cfg.ajaxUrl,
				dataType: 'json',
				delay:    300,
				data: function ( params ) {
					return {
						action: 'wpl_product_search',
						q:      params.term,
						nonce:  cfg.searchNonce,
					};
				},
				processResults: function ( data ) {
					return { results: data };
				},
			},
		} );
	}

	function initMultiProductSearch( $select ) {
		if ( ! $select.length || $select.hasClass( 'select2-hidden-accessible' ) ) {
			return;
		}
		$select.select2( {
			minimumInputLength: 2,
			placeholder:        i18n.searchProducts || 'Search for a product…',
			multiple:           true,
			width:              '300px',
			ajax: {
				url:      cfg.ajaxUrl,
				dataType: 'json',
				delay:    300,
				data: function ( params ) {
					return {
						action: 'wpl_product_search',
						q:      params.term,
						nonce:  cfg.searchNonce,
					};
				},
				processResults: function ( data ) {
					return { results: data };
				},
			},
		} );
	}

	// Initialise all product-search selects in an element.
	function initSearchInRow( $row ) {
		initSingleProductSearch( $row.find( '.wpl-product-search--single' ) );
		initMultiProductSearch( $row.find( '.wpl-product-search--multi' ) );
	}

	// ── Variation loading ─────────────────────────────────────────────────────

	function loadVariations( $row, productId, productType ) {
		const $variationRow    = $row.find( '.wpl-variation-row' );
		const $variationSelect = $row.find( '.wpl-variation-select' );

		if ( productType !== 'variable' ) {
			$variationRow.hide();
			$variationSelect.val( '' );
			return;
		}

		$variationRow.show();
		$variationSelect.find( 'option:not(:first)' ).remove();
		$variationSelect.prop( 'disabled', true );

		$.ajax( {
			url:      cfg.ajaxUrl,
			dataType: 'json',
			data: {
				action:     'wpl_get_variations',
				product_id: productId,
				nonce:      cfg.searchNonce,
			},
			success: function ( variations ) {
				if ( $.isArray( variations ) ) {
					variations.forEach( function ( v ) {
						$variationSelect.append(
							$( '<option>' ).val( v.id ).text( v.text )
						);
					} );
				}
				$variationSelect.prop( 'disabled', false );
			},
			error: function () {
				$variationSelect.prop( 'disabled', false );
			},
		} );
	}

	// ── Component rows ────────────────────────────────────────────────────────

	function addComponentRow() {
		const template = document.getElementById( 'wpl-component-template' );
		if ( ! template ) {
			return;
		}

		const uuid      = generateUUID();
		const sortOrder = $( '#wpl-components-list .wpl-component-row' ).length;
		const html      = template.innerHTML
			.replace( /__COMP_ID__/g, uuid )
			.replace( /__SORT_ORDER__/g, String( sortOrder ) );

		const $row = $( html );
		$( '#wpl-components-list' ).append( $row );

		initSearchInRow( $row );
		updatePanelVisibility();
	}

	function removeComponentRow( $row ) {
		if ( ! window.confirm( i18n.confirmRemove || 'Remove this component?' ) ) {
			return;
		}
		$row.remove();
		updateSortOrders();
	}

	function moveRowUp( $row ) {
		const $prev = $row.prev( '.wpl-component-row' );
		if ( $prev.length ) {
			$row.insertBefore( $prev );
			updateSortOrders();
		}
	}

	function moveRowDown( $row ) {
		const $next = $row.next( '.wpl-component-row' );
		if ( $next.length ) {
			$row.insertAfter( $next );
			updateSortOrders();
		}
	}

	function updateSortOrders() {
		$( '#wpl-components-list .wpl-component-row' ).each( function ( index ) {
			$( this ).find( '.wpl-comp-sort-order' ).val( String( index ) );
		} );
	}

	// ── Price tier rows ───────────────────────────────────────────────────────

	function addTierRow() {
		const template = document.getElementById( 'wpl-tier-template' );
		if ( ! template ) {
			return;
		}
		const idx  = nextTierIndex();
		const html = template.innerHTML.replace( /__TIER_INDEX__/g, String( idx ) );
		$( '#wpl-tiers-body' ).append( $( html ) );
	}

	function removeTierRow( $row ) {
		$row.remove();
	}

	// ── Event delegation ──────────────────────────────────────────────────────

	function bindEvents() {
		// Mode / price-mode changes.
		$( '#wpl_bundle_mode, #wpl_bundle_price_mode' ).on( 'change', updatePanelVisibility );

		// Add component.
		$( document ).on( 'click', '.wpl-add-component', function () {
			addComponentRow();
		} );

		// Remove component.
		$( document ).on( 'click', '.wpl-comp-remove', function () {
			removeComponentRow( $( this ).closest( '.wpl-component-row' ) );
		} );

		// Move component up.
		$( document ).on( 'click', '.wpl-comp-move-up', function () {
			moveRowUp( $( this ).closest( '.wpl-component-row' ) );
		} );

		// Move component down.
		$( document ).on( 'click', '.wpl-comp-move-down', function () {
			moveRowDown( $( this ).closest( '.wpl-component-row' ) );
		} );

		// Add price tier.
		$( document ).on( 'click', '.wpl-add-tier', function () {
			addTierRow();
		} );

		// Remove price tier.
		$( document ).on( 'click', '.wpl-remove-tier', function () {
			removeTierRow( $( this ).closest( '.wpl-tier-row' ) );
		} );

		// Update component header when slot title changes.
		$( document ).on( 'input', '.wpl-component-row input[name*="[slot_title]"]', function () {
			const $row = $( this ).closest( '.wpl-component-row' );
			const val  = $( this ).val();
			if ( val ) {
				$row.find( '.wpl-comp-header-name' ).text( val );
			}
		} );

		// Product selected via select2 → update header name and load variations.
		$( document ).on( 'select2:select', '.wpl-product-search--single', function ( e ) {
			const $row        = $( this ).closest( '.wpl-component-row' );
			const productId   = e.params.data.id;
			const productType = e.params.data.type || 'simple';
			const name        = e.params.data.text || '';

			// Update header only if there's no slot title already.
			const slotTitle = $row.find( 'input[name*="[slot_title]"]' ).val();
			if ( ! slotTitle ) {
				$row.find( '.wpl-comp-header-name' ).text( name );
			}

			loadVariations( $row, productId, productType );
		} );

		// Product cleared.
		$( document ).on( 'select2:unselect', '.wpl-product-search--single', function () {
			const $row = $( this ).closest( '.wpl-component-row' );
			$row.find( '.wpl-variation-row' ).hide();
			$row.find( '.wpl-variation-select' ).val( '' );
		} );

		// Keyboard accessibility: Enter on buttons should not submit the form.
		$( document ).on( 'keydown', '.wpl-comp-move-up, .wpl-comp-move-down, .wpl-comp-remove, .wpl-add-component, .wpl-add-tier, .wpl-remove-tier', function ( e ) {
			if ( e.key === 'Enter' || e.keyCode === 13 ) {
				e.preventDefault();
				$( this ).trigger( 'click' );
			}
		} );
	}

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	$( function () {
		if ( ! $( '#wpl_bundle_data' ).length ) {
			return;
		}

		// Initialise select2 on all existing component rows.
		$( '#wpl-components-list .wpl-component-row' ).each( function () {
			initSearchInRow( $( this ) );
		} );

		// Set initial sort order values.
		updateSortOrders();

		// Set initial visibility.
		updatePanelVisibility();

		bindEvents();
	} );

} )( jQuery );
