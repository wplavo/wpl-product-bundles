<?php
/**
 * Bundle product add-to-cart template.
 *
 * This template can be overridden by copying it to:
 *   {theme}/woocommerce/single-product/add-to-cart/bundle.php
 *
 * @var \WC_Product                          $product
 * @var \WPL\ProductBundles\Config\BundleConfig $bundle_config
 * @var array<int, array<string, mixed>>     $bundle_components
 */

defined( 'ABSPATH' ) || exit;

if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
	echo wp_kses_post( $product->add_to_cart_description() );
	return;
}

$is_mix_and_match = ( $bundle_config->mode === 'mix_and_match' );
$bundle_id        = esc_attr( (string) $product->get_id() );
?>

<form
	class="cart wpl-bundle-form"
	action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
	method="post"
	enctype="multipart/form-data"
	data-bundle-mode="<?php echo esc_attr( $bundle_config->mode ); ?>"
>
	<?php wp_nonce_field( 'wpl_add_bundle_to_cart', '_wpl_bundle_nonce' ); ?>
	<input type="hidden" name="add-to-cart" value="<?php echo $bundle_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_attr above ?>">

	<?php if ( $is_mix_and_match ) : ?>
	<div class="wpl-bundle-progress" aria-live="polite">
		<span class="wpl-bundle-progress-label">
			<?php
			printf(
				/* translators: 1: current selected count, 2: max allowed */
				esc_html__( '%1$d / %2$d %3$s', 'wpl-product-bundles' ),
				0,
				$bundle_config->maxQty > 0 ? $bundle_config->maxQty : $bundle_config->minQty,
				esc_html__( 'items selected', 'wpl-product-bundles' )
			);
			?>
		</span>
		<div class="wpl-bundle-progress-bar" role="progressbar" aria-valuemin="0"
			aria-valuemax="<?php echo esc_attr( (string) ( $bundle_config->maxQty > 0 ? $bundle_config->maxQty : $bundle_config->minQty ) ); ?>"
			aria-valuenow="0">
			<div class="wpl-bundle-progress-bar-fill" style="width:0%"></div>
		</div>
	</div>
	<?php endif; ?>

	<ul class="wpl-bundle-components" role="list">
		<?php foreach ( $bundle_components as $index => $component_data ) : ?>
			<?php
			wc_get_template(
				'partials/bundle-component.php',
				[
					'component_data' => $component_data,
					'index'          => $index,
					'bundle_mode'    => $bundle_config->mode,
				],
				'',
				WPL_PRODUCT_BUNDLES_DIR . 'templates/'
			);
			?>
		<?php endforeach; ?>
	</ul>

	<div class="wpl-bundle-error wpl-bundle-error--global" role="alert" style="display:none"></div>

	<div class="wpl-bundle-price-total" aria-live="polite" style="display:none"></div>
	<div class="wpl-bundle-price-savings" aria-live="polite" style="display:none"></div>

	<div class="wpl-bundle-actions">
		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<button type="submit"
			name="add-to-cart"
			value="<?php echo $bundle_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_attr above ?>"
			class="single_add_to_cart_button button alt wpl-bundle-submit"
			disabled
		>
			<?php echo esc_html( $product->single_add_to_cart_text() ); ?>
		</button>

		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
	</div>
</form>
