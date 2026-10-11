<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Migration;

/**
 * Contract for a single schema migration.
 *
 * Each implementation must be idempotent: running it more than once must not
 * produce a different result than running it exactly once.
 */
interface MigrationInterface {

    /**
     * The schema version this migration brings the data up to.
     * Must be a positive integer; migrations run in ascending version order.
     */
    public function version(): int;

    /**
     * Execute the migration.
     */
    public function run(): void;
}
