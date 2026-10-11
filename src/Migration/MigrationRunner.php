<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Migration;

use WPL\ProductBundles\Migration\Migrations\Migration_1_0_0;

/**
 * Compares the stored schema version against the current code version and
 * runs any pending migrations in order.
 *
 * All migrations are idempotent; re-running is safe.
 */
final class MigrationRunner {

    public const CURRENT_VERSION = 1;

    private const OPTION_KEY = 'wpl_product_bundles_schema_version';

    /** @var MigrationInterface[] */
    private array $migrations;

    /**
     * @param MigrationInterface[]|null $migrations Pass null to use the default production set.
     *                                               Pass an explicit array (including empty) for testing.
     */
    public function __construct(?array $migrations = null) {
        $this->migrations = $migrations ?? [
            new Migration_1_0_0(),
        ];
    }

    /**
     * Runs all migrations whose version is greater than the stored version.
     * Updates the stored version after each successful migration.
     */
    public function run(): void {
        $storedVersion = (int) get_option(self::OPTION_KEY, 0);

        if ($storedVersion >= self::CURRENT_VERSION) {
            return;
        }

        foreach ($this->migrations as $migration) {
            if ($migration->version() > $storedVersion) {
                $migration->run();
                update_option(self::OPTION_KEY, $migration->version(), false);
                $storedVersion = $migration->version();
            }
        }
    }

    public function getCurrentVersion(): int {
        return self::CURRENT_VERSION;
    }

    public function getStoredVersion(): int {
        return (int) get_option(self::OPTION_KEY, 0);
    }
}
