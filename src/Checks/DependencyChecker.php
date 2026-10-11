<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Checks;

/**
 * Verifies that required dependencies (WooCommerce version, etc.) are present.
 *
 * Errors are collected and can be displayed as admin notices without throwing.
 */
final class DependencyChecker {

    private const MIN_WC_VERSION = '8.0.0';
    private const MIN_WP_VERSION = '6.4';

    /** @var string[] */
    private array $errors = [];

    /**
     * Runs all dependency checks. Returns true when all requirements are met.
     */
    public function check(): bool {
        $this->checkWordPress();
        $this->checkWooCommerce();
        return empty($this->errors);
    }

    /**
     * Outputs all collected error messages as WP admin notices.
     * Intended to be hooked to admin_notices.
     */
    public function displayNotices(): void {
        foreach ($this->errors as $error) {
            printf(
                '<div class="notice notice-error"><p><strong>WPL Product Bundles:</strong> %s</p></div>',
                esc_html($error)
            );
        }
    }

    /** @return string[] */
    public function getErrors(): array {
        return $this->errors;
    }

    private function checkWordPress(): void {
        global $wp_version;
        if (version_compare((string) $wp_version, self::MIN_WP_VERSION, '<')) {
            $this->errors[] = sprintf(
                /* translators: %s: required WordPress version */
                __('WordPress %s or higher is required.', 'wpl-product-bundles'),
                self::MIN_WP_VERSION
            );
        }
    }

    private function checkWooCommerce(): void {
        if (!class_exists('WooCommerce')) {
            $this->errors[] = __(
                'WooCommerce must be installed and active.',
                'wpl-product-bundles'
            );
            return;
        }

        if (version_compare(WC_VERSION, self::MIN_WC_VERSION, '<')) {
            $this->errors[] = sprintf(
                /* translators: %s: required WooCommerce version */
                __('WooCommerce %s or higher is required.', 'wpl-product-bundles'),
                self::MIN_WC_VERSION
            );
        }
    }
}
