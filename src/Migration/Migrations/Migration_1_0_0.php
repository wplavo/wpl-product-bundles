<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Migration\Migrations;

use WPL\ProductBundles\Migration\MigrationInterface;

/**
 * Version 1 — initial schema.
 *
 * No data transformations are needed; the options that store the schema version
 * are managed by MigrationRunner. This migration serves as the baseline marker
 * and the template for future migrations.
 */
final class Migration_1_0_0 implements MigrationInterface {

    public function version(): int {
        return 1;
    }

    public function run(): void {
        // No data to migrate in the initial version.
        // Future migrations will use wc_get_products() / order object APIs
        // to transform existing bundle product meta between schema versions.
    }
}
