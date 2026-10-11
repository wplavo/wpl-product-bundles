<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Logging;

/**
 * Minimal structured logging interface.
 *
 * Mirrors PSR-3 method signatures but avoids a hard PSR-3 dependency.
 */
interface LoggerInterface {

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void;
}
