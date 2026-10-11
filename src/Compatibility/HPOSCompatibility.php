<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Compatibility;

/**
 * Declares WooCommerce HPOS and block-checkout compatibility.
 *
 * This class exists solely so the compatibility declarations are testable and
 * not buried in an anonymous closure. The declarations themselves happen via
 * the before_woocommerce_init action registered in the main plugin file.
 */
final class HPOSCompatibility {

    private string $pluginFile;

    public function __construct(string $pluginFile) {
        $this->pluginFile = $pluginFile;
    }

    /**
     * Declares HPOS and block-checkout compatibility with WooCommerce.
     * Must be called from a before_woocommerce_init action handler.
     */
    public function declare(): void {
        if (!class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            $this->pluginFile,
            true
        );

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            $this->pluginFile,
            true
        );
    }
}
