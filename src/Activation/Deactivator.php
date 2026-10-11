<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Activation;

/**
 * Runs on plugin deactivation.
 *
 * Flushes rewrite rules so the bundle product-type endpoint is removed.
 * Does not delete any plugin data — see uninstall.php for the data-retention policy.
 */
final class Deactivator {

    /**
     * Entry point for register_deactivation_hook().
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
