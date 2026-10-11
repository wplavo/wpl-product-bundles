<?php
/**
 * Individual bundle component partial.
 *
 * @var array<string, mixed>                          $component_data  Rich data built by BundleRenderer.
 * @var int                                           $index           0-based slot index.
 * @var string                                        $bundle_mode     'fixed' | 'mix_and_match'.
 */

defined( 'ABSPATH' ) || exit;

/** @var \WPL\ProductBundles\Config\ComponentConfig $config */
$config     = $component_data['config'];
$name       = $component_data['name'];
$image      = $component_data['image'];
$price_html = $component_data['price_html'];
$in_stock   = $component_data['is_in_stock'];

/** @var array<int, array{id:int,name:string,price_html:string,is_in_stock:bool}> $allowed_products */
$allowed_products = $component_data['allowed_products_data'];

$is_mix_match = ( $bundle_mode === 'mix_and_match' );
$component_id = esc_attr( $config->id );
$row_id       = 'wpl-component-' . $component_id;
?>
<li
	id="<?php echo $row_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr applied above ?>"
	class="wpl-bundle-component<?php echo $config->optional ? ' wpl-bundle-component--optional' : ''; ?>"
	data-component-id="<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
>
	<?php if ( $image ) : ?>
	<div class="wpl-bundle-component__image" aria-hidden="true">
		<?php echo wp_kses_post( $image ); ?>
	</div>
	<?php endif; ?>

	<div class="wpl-bundle-component__body">
		<div class="wpl-bundle-component__header">
			<span class="wpl-bundle-component__name"><?php echo esc_html( $name ); ?></span>

			<?php if ( $config->optional ) : ?>
			<span class="wpl-bundle-component__badge wpl-bundle-component__badge--optional">
				<?php esc_html_e( 'Optional', 'wpl-product-bundles' ); ?>
			</span>
			<?php else : ?>
			<span class="wpl-bundle-component__badge wpl-bundle-component__badge--required">
				<?php esc_html_e( 'Required', 'wpl-product-bundles' ); ?>
			</span>
			<?php endif; ?>
		</div>

		<?php if ( ! $in_stock ) : ?>
		<p class="wpl-bundle-component__stock wpl-bundle-component__stock--unavailable">
			<?php esc_html_e( 'Out of stock', 'wpl-product-bundles' ); ?>
		</p>
		<?php endif; ?>

		<?php if ( $is_mix_match && ! empty( $allowed_products ) ) : ?>
		<div class="wpl-bundle-component__product-select">
			<label
				class="screen-reader-text"
				for="wpl-product-<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
			>
				<?php
				printf(
					/* translators: %s: slot name */
					esc_html__( 'Select product for %s', 'wpl-product-bundles' ),
					esc_html( $name )
				);
				?>
			</label>
			<select
				id="wpl-product-<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
				name="wpl_component[<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>][product_id]"
				data-component-id="<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
				data-type="product-select"
				<?php echo $config->optional ? '' : 'required'; ?>
				aria-required="<?php echo $config->optional ? 'false' : 'true'; ?>"
			>
				<option value=""><?php esc_html_e( '— Select a product —', 'wpl-product-bundles' ); ?></option>
				<?php foreach ( $allowed_products as $ap ) : ?>
				<option value="<?php echo esc_attr( (string) $ap['id'] ); ?>"
					<?php echo ! $ap['is_in_stock'] ? 'disabled' : ''; ?>>
					<?php echo esc_html( $ap['name'] ); ?>
					<?php if ( ! $ap['is_in_stock'] ) : ?>
					<?php esc_html_e( '(out of stock)', 'wpl-product-bundles' ); ?>
					<?php endif; ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php elseif ( ! $is_mix_match ) : ?>
		<input
			type="hidden"
			name="wpl_component[<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>][product_id]"
			value="<?php echo esc_attr( (string) $config->productId ); ?>"
		>
		<?php if ( $config->variationId !== null ) : ?>
		<input
			type="hidden"
			name="wpl_component[<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>][variation_id]"
			value="<?php echo esc_attr( (string) $config->variationId ); ?>"
		>
		<?php endif; ?>
		<?php endif; ?>

		<?php if ( $config->quantityMin !== $config->quantityMax || $config->optional ) : ?>
		<div class="wpl-bundle-component__qty">
			<?php if ( $config->optional ) : ?>
			<label class="wpl-bundle-component__optional-label">
				<input
					type="checkbox"
					class="wpl-bundle-component--optional-toggle"
					data-component-id="<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
					data-type="optional-toggle"
					<?php checked( ! $config->optional ); // default checked only if required ?>
					aria-label="<?php
					printf(
						/* translators: %s: component name */
						esc_attr__( 'Include %s', 'wpl-product-bundles' ),
						esc_attr( $name )
					);
					?>"
				>
				<?php esc_html_e( 'Include', 'wpl-product-bundles' ); ?>
			</label>
			<?php endif; ?>

			<label
				class="screen-reader-text"
				for="wpl-qty-<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
			>
				<?php
				printf(
					/* translators: %s: component name */
					esc_html__( 'Quantity for %s', 'wpl-product-bundles' ),
					esc_html( $name )
				);
				?>
			</label>
			<input
				type="number"
				id="wpl-qty-<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
				name="wpl_component[<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>][quantity]"
				class="wpl-bundle-component__qty-input"
				data-component-id="<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
				data-type="quantity"
				value="<?php echo esc_attr( (string) $config->quantityDefault ); ?>"
				min="<?php echo esc_attr( (string) $config->quantityMin ); ?>"
				<?php if ( $config->quantityMax > 0 ) : ?>
				max="<?php echo esc_attr( (string) $config->quantityMax ); ?>"
				<?php endif; ?>
				step="1"
				aria-label="<?php
				printf(
					/* translators: %s: component name */
					esc_attr__( 'Quantity for %s', 'wpl-product-bundles' ),
					esc_attr( $name )
				);
				?>"
			>
		</div>
		<?php else : ?>
		<input
			type="hidden"
			name="wpl_component[<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>][quantity]"
			value="<?php echo esc_attr( (string) $config->quantityDefault ); ?>"
		>
		<p class="wpl-bundle-component__qty-fixed">
			<?php
			printf(
				/* translators: %d: quantity */
				esc_html__( 'Qty: %d', 'wpl-product-bundles' ),
				$config->quantityDefault
			);
			?>
		</p>
		<?php endif; ?>

		<?php if ( $price_html ) : ?>
		<div class="wpl-bundle-component__price">
			<?php echo wp_kses_post( $price_html ); ?>
		</div>
		<?php endif; ?>

		<div
			class="wpl-bundle-component__error"
			data-error-for="<?php echo $component_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
			role="alert"
			style="display:none"
		></div>
	</div>
</li>
