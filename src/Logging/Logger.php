<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Logging;

/**
 * Wraps WooCommerce's logger (`wc_get_logger`) with a fixed source identifier.
 *
 * Falls back to PHP's error_log if WooCommerce is not available yet
 * (e.g. during activation). This should not happen in normal operation.
 */
final class Logger implements LoggerInterface {

    private const SOURCE = 'wpl-product-bundles';

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void {
        $this->log('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void {
        $this->log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void {
        if (function_exists('wc_get_logger')) {
            $wcContext = ['source' => self::SOURCE] + $context;
            wc_get_logger()->$level($message, $wcContext);
            return;
        }

        // Fallback — should only occur if called before WooCommerce loads.
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log(sprintf('[%s][%s] %s', self::SOURCE, strtoupper($level), $message));
    }
}
