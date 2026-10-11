<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Selection;

use WPL\ProductBundles\Config\BundleConfig;
use WPL\ProductBundles\Validation\ValidationResult;

/**
 * Contract for validating a bundle selection against a config.
 * Allows add-to-cart handlers to be unit-tested with mock validators.
 */
interface SelectionValidatorInterface {

    public function validate(BundleConfig $config, BundleSelection $selection): ValidationResult;
}
