<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Api;

/**
 * Registers the WPL bundle REST API routes on rest_api_init.
 */
final class RestServiceProvider {

    public function __construct(
        private readonly RestBundleController $controller,
    ) {}

    public function register(): void {
        add_action('rest_api_init', [$this->controller, 'register_routes']);
    }
}
