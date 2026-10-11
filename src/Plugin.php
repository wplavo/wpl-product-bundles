<?php

declare(strict_types=1);

namespace WPL\ProductBundles;

use WPL\ProductBundles\Admin\AdminServiceProvider;
use WPL\ProductBundles\Admin\BundleProductSaver;
use WPL\ProductBundles\Admin\BundleProductTab;
use WPL\ProductBundles\Admin\ProductSearchController;
use WPL\ProductBundles\Api\RestBundleController;
use WPL\ProductBundles\Api\RestServiceProvider;
use WPL\ProductBundles\Cart\AddToCartHandler;
use WPL\ProductBundles\Cart\CartPriceManager;
use WPL\ProductBundles\Cart\CartSelectionBuilder;
use WPL\ProductBundles\Cart\CartServiceProvider;
use WPL\ProductBundles\Cart\CartSyncManager;
use WPL\ProductBundles\Compatibility\HPOSCompatibility;
use WPL\ProductBundles\Config\BundleConfigRepository;
use WPL\ProductBundles\Config\WcProductResolver;
use WPL\ProductBundles\Frontend\BundleRenderer;
use WPL\ProductBundles\Frontend\FrontendServiceProvider;
use WPL\ProductBundles\Migration\MigrationRunner;
use WPL\ProductBundles\Order\OrderIntegration;
use WPL\ProductBundles\Order\OrderServiceProvider;
use WPL\ProductBundles\Pricing\BundleProductPriceService;
use WPL\ProductBundles\Pricing\PricingService;
use WPL\ProductBundles\Pricing\TierResolver;
use WPL\ProductBundles\Pricing\WcProductPriceResolver;
use WPL\ProductBundles\ProductType\ProductTypeRegistrar;
use WPL\ProductBundles\Selection\SelectionValidator;
use WPL\ProductBundles\Selection\WcStockChecker;
use WPL\ProductBundles\Validation\BundleValidator;

/**
 * Plugin bootstrap — owns the DI container and wires up all service providers.
 *
 * Access via Plugin::instance(); never instantiate directly.
 */
final class Plugin {

    private static ?Plugin $instance = null;
    private bool $initialized       = false;
    private Container $container;
    private string $file;

    private const VERSION = '1.0.0';

    private function __construct(string $file) {
        $this->file      = $file;
        $this->container = new Container();
    }

    /**
     * Returns the singleton instance, creating it on first call.
     *
     * @param string $file Absolute path to the plugin file (required on first call).
     */
    public static function instance(string $file = ''): static {
        if (null === static::$instance) {
            static::$instance = new static($file);
        }
        return static::$instance;
    }

    /**
     * Wire up services and hooks. Safe to call only once per request.
     */
    public function init(): void {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        $this->registerServices();
        $this->registerHooks();
        $this->runMigrations();
    }

    private function registerServices(): void {
        $this->container->singleton(
            MigrationRunner::class,
            fn(Container $c) => new MigrationRunner()
        );

        $this->container->singleton(
            Logging\Logger::class,
            fn(Container $c) => new Logging\Logger()
        );

        $this->container->singleton(
            WcProductResolver::class,
            fn(Container $c) => new WcProductResolver()
        );

        $this->container->singleton(
            BundleConfigRepository::class,
            fn(Container $c) => new BundleConfigRepository()
        );

        $this->container->singleton(
            BundleValidator::class,
            fn(Container $c) => new BundleValidator($c->make(WcProductResolver::class))
        );

        $this->container->singleton(
            WcProductPriceResolver::class,
            fn(Container $c) => new WcProductPriceResolver()
        );

        $this->container->singleton(
            TierResolver::class,
            fn(Container $c) => new TierResolver()
        );

        $this->container->singleton(
            PricingService::class,
            fn(Container $c) => new PricingService(
                $c->make(WcProductPriceResolver::class),
                $c->make(TierResolver::class)
            )
        );

        $this->container->singleton(
            WcStockChecker::class,
            fn(Container $c) => new WcStockChecker()
        );

        $this->container->singleton(
            SelectionValidator::class,
            fn(Container $c) => new SelectionValidator(
                $c->make(WcProductResolver::class),
                $c->make(WcStockChecker::class)
            )
        );

        $this->container->singleton(
            BundleRenderer::class,
            fn(Container $c) => new BundleRenderer($c->make(BundleConfigRepository::class))
        );

        $this->container->singleton(
            FrontendServiceProvider::class,
            fn(Container $c) => new FrontendServiceProvider($c->make(BundleRenderer::class))
        );

        $this->container->singleton(
            CartSelectionBuilder::class,
            fn(Container $c) => new CartSelectionBuilder()
        );

        $this->container->singleton(
            AddToCartHandler::class,
            fn(Container $c) => new AddToCartHandler(
                $c->make(BundleConfigRepository::class),
                $c->make(SelectionValidator::class),
                $c->make(WcProductResolver::class),
                $c->make(CartSelectionBuilder::class)
            )
        );

        $this->container->singleton(
            CartPriceManager::class,
            fn(Container $c) => new CartPriceManager(
                $c->make(BundleConfigRepository::class),
                $c->make(PricingService::class),
                $c->make(CartSelectionBuilder::class)
            )
        );

        $this->container->singleton(
            CartSyncManager::class,
            fn(Container $c) => new CartSyncManager()
        );

        $this->container->singleton(
            CartServiceProvider::class,
            fn(Container $c) => new CartServiceProvider(
                $c->make(AddToCartHandler::class),
                $c->make(CartPriceManager::class),
                $c->make(CartSyncManager::class)
            )
        );

        $this->container->singleton(
            OrderIntegration::class,
            fn(Container $c) => new OrderIntegration()
        );

        $this->container->singleton(
            OrderServiceProvider::class,
            fn(Container $c) => new OrderServiceProvider($c->make(OrderIntegration::class))
        );

        $this->container->singleton(
            RestBundleController::class,
            fn(Container $c) => new RestBundleController(
                $c->make(BundleConfigRepository::class),
                $c->make(PricingService::class)
            )
        );

        $this->container->singleton(
            RestServiceProvider::class,
            fn(Container $c) => new RestServiceProvider($c->make(RestBundleController::class))
        );

        if (is_admin()) {
            $this->container->singleton(
                ProductSearchController::class,
                fn(Container $c) => new ProductSearchController()
            );

            $this->container->singleton(
                BundleProductTab::class,
                fn(Container $c) => new BundleProductTab($c->make(BundleConfigRepository::class))
            );

            $this->container->singleton(
                BundleProductSaver::class,
                fn(Container $c) => new BundleProductSaver($c->make(BundleConfigRepository::class))
            );

            $this->container->singleton(
                AdminServiceProvider::class,
                fn(Container $c) => new AdminServiceProvider(
                    $c->make(Logging\Logger::class),
                    $this->file,
                    $c->make(BundleProductTab::class),
                    $c->make(BundleProductSaver::class),
                    $c->make(ProductSearchController::class)
                )
            );
        }
    }

    private function registerHooks(): void {
        add_action('init', [$this, 'loadTextDomain']);

        // Boot the static price-service registry used by BundleProduct::get_price().
        /** @var PricingService $ps */
        $ps = $this->container->make(PricingService::class);
        /** @var BundleConfigRepository $repo */
        $repo = $this->container->make(BundleConfigRepository::class);
        BundleProductPriceService::boot($ps, $repo);

        // Register the bundle product type with WooCommerce.
        (new ProductTypeRegistrar())->register();

        // Cart and order hooks must run in all contexts (including admin order editing).
        $this->container->make(CartServiceProvider::class)->register();
        $this->container->make(OrderServiceProvider::class)->register();
        $this->container->make(RestServiceProvider::class)->register();

        // Front-end: form rendering and asset enqueueing.
        if (!is_admin()) {
            $this->container->make(FrontendServiceProvider::class)->register();
        }

        if (is_admin()) {
            $this->container->make(AdminServiceProvider::class)->register();
        }
    }

    private function runMigrations(): void {
        $this->container->make(MigrationRunner::class)->run();
    }

    /** @internal For testing only. */
    public static function resetInstance(): void {
        static::$instance = null;
    }

    public function getVersion(): string {
        return self::VERSION;
    }

    public function getContainer(): Container {
        return $this->container;
    }

    public function loadTextDomain(): void {
        load_plugin_textdomain(
            'wpl-product-bundles',
            false,
            dirname(plugin_basename($this->file)) . '/languages'
        );
    }
}
