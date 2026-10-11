# WPL Product Bundles — Hooks & Filters

All hooks are prefixed `wpl_bundle_` and are namespaced to avoid conflicts.

> **Stability policy**: A hook is stable once its regression tests exist in the test suite. Do not rely on hooks marked **(planned)** in production code — their signatures may still change.

---

## Filters

---

### `wpl_bundle_config`

Fired after a `BundleConfig` is loaded from product meta. Lets you override the configuration before it is used for rendering, validation, pricing, or REST API responses.

**Signature:**

```php
apply_filters( 'wpl_bundle_config', BundleConfig $config, int $productId )
```

**Parameters:**

| Parameter   | Type           | Description                               |
|-------------|----------------|-------------------------------------------|
| `$config`   | `BundleConfig` | The configuration loaded from meta.       |
| `$productId`| `int`          | The WooCommerce product ID.               |

**Returns:** `BundleConfig` — return a modified `BundleConfig` instance. Any other type is ignored and the original is used.

**Execution context:** Every code path that calls `BundleConfigRepository::get()`, including storefront rendering, cart validation, price injection, and REST reads.

**Example — force all components optional on a sale:**

```php
add_filter( 'wpl_bundle_config', function ( BundleConfig $config, int $productId ): BundleConfig {
    $product = wc_get_product( $productId );
    if ( ! $product || ! $product->is_on_sale() ) {
        return $config;
    }

    $items = array_map(
        fn( ComponentConfig $c ) => ComponentConfig::fromArray(
            array_merge( $c->toArray(), ['optional' => true] )
        ),
        $config->items
    );

    return BundleConfig::fromArray( array_merge( $config->toArray(), ['items' => $items] ) );
}, 10, 2 );
```

---

### `wpl_bundle_validation_result`

Fired at the end of `SelectionValidator::validate()`. Lets you add extra validation rules or override the computed result.

**Signature:**

```php
apply_filters(
    'wpl_bundle_validation_result',
    ValidationResult $result,
    BundleConfig     $config,
    BundleSelection  $selection
)
```

**Parameters:**

| Parameter    | Type               | Description                          |
|--------------|--------------------|--------------------------------------|
| `$result`    | `ValidationResult` | The computed validation result.      |
| `$config`    | `BundleConfig`     | Bundle configuration.                |
| `$selection` | `BundleSelection`  | The customer's selection.            |

**Returns:** `ValidationResult` — return a modified instance. Any other type is ignored.

**Execution context:** `woocommerce_add_to_cart_validation` filter, before WooCommerce adds the parent bundle to the cart.

**Example — block a specific product from being selected in any bundle:**

```php
add_filter(
    'wpl_bundle_validation_result',
    function ( ValidationResult $result, BundleConfig $config, BundleSelection $selection ): ValidationResult {
        foreach ( $selection->getSelections() as $sel ) {
            if ( $sel->productId === RESTRICTED_PRODUCT_ID ) {
                $result->addError( 'restricted_product', 'This product cannot be added to a bundle.' );
            }
        }
        return $result;
    },
    10,
    3
);
```

---

### `wpl_bundle_price_result`

Fired at the end of `PricingService::calculateFromSelection()`. Lets you modify the computed price before it is stored on the cart item, used in REST estimate responses, or displayed to the customer.

**Signature:**

```php
apply_filters(
    'wpl_bundle_price_result',
    PriceResult      $result,
    BundleConfig     $config,
    BundleSelection  $selection,
    int              $bundleQty
)
```

**Parameters:**

| Parameter    | Type              | Description                                          |
|--------------|-------------------|------------------------------------------------------|
| `$result`    | `PriceResult`     | The computed price result.                           |
| `$config`    | `BundleConfig`    | Bundle configuration.                                |
| `$selection` | `BundleSelection` | The customer's selection.                            |
| `$bundleQty` | `int`             | Number of bundle units (relevant for tiered pricing).|

**Returns:** `PriceResult` — return a modified instance. Any other type is ignored.

**Execution context:** Every `calculateFromSelection()` call: add-to-cart, cart recalculation (`woocommerce_before_calculate_totals`), and the REST `/estimate` endpoint.

**Security note:** This filter runs on the server-authoritative price. Do not feed any client-submitted value here — clients cannot submit prices via WPL.

**Example — apply a loyalty discount for a specific user:**

```php
add_filter(
    'wpl_bundle_price_result',
    function ( PriceResult $result, BundleConfig $config, BundleSelection $selection, int $bundleQty ): PriceResult {
        if ( ! is_user_logged_in() || ! wpl_user_has_loyalty_discount( get_current_user_id() ) ) {
            return $result;
        }

        $discounted = (string) round( (float) $result->total * 0.95, 2 );

        return new PriceResult(
            mode:           $result->mode,
            subtotal:       $result->subtotal,
            bundleDiscount: $result->bundleDiscount,
            total:          $discounted,
            components:     $result->components,
        );
    },
    10,
    4
);
```

---

### `wpl_bundle_component_visible`

Fired inside `CartServiceProvider::isCartItemVisible()` for bundle child items. Controls whether component line items appear in the cart table, mini-cart, and checkout order summary.

**Signature:**

```php
apply_filters(
    'wpl_bundle_component_visible',
    bool   $visible,
    array  $cartItem,
    string $cartItemKey
)
```

**Parameters:**

| Parameter      | Type     | Description                                               |
|----------------|----------|-----------------------------------------------------------|
| `$visible`     | `bool`   | Current visibility (`false` by default for child items).  |
| `$cartItem`    | `array`  | The WooCommerce cart item array.                          |
| `$cartItemKey` | `string` | The cart item key.                                        |

**Returns:** `bool` — `true` to show the component row, `false` to hide it.

**Execution context:** `woocommerce_cart_item_visible`, `woocommerce_widget_cart_item_visible`, and `woocommerce_checkout_cart_item_visible`.

**Example — show components in the checkout summary:**

```php
add_filter(
    'wpl_bundle_component_visible',
    function ( bool $visible, array $cartItem, string $cartItemKey ): bool {
        // Show during checkout only.
        return is_checkout();
    },
    10,
    3
);
```

---

## Actions

---

### `wpl_bundle_after_add_to_cart`

Fired after all child component items have been added to the WooCommerce cart for a bundle.

**Signature:**

```php
do_action(
    'wpl_bundle_after_add_to_cart',
    string          $cartItemKey,
    int             $productId,
    int             $qty,
    BundleConfig    $config,
    BundleSelection $selection
)
```

**Parameters:**

| Parameter      | Type              | Description                                       |
|----------------|-------------------|---------------------------------------------------|
| `$cartItemKey` | `string`          | The cart item key of the parent bundle item.      |
| `$productId`   | `int`             | The bundle product ID.                            |
| `$qty`         | `int`             | Number of bundle units added.                     |
| `$config`      | `BundleConfig`    | Bundle configuration at time of add-to-cart.      |
| `$selection`   | `BundleSelection` | The validated customer selection.                 |

**Execution context:** `woocommerce_add_to_cart` (priority 20), after child items are in the cart.

**Example — track bundle additions in a custom analytics system:**

```php
add_action(
    'wpl_bundle_after_add_to_cart',
    function (
        string $cartItemKey,
        int $productId,
        int $qty,
        BundleConfig $config,
        BundleSelection $selection
    ): void {
        my_analytics_track( 'bundle_add_to_cart', [
            'product_id'      => $productId,
            'quantity'        => $qty,
            'component_count' => count( $selection->getSelections() ),
        ] );
    },
    10,
    5
);
```

---

## Class Reference

Classes referenced above live in the `WPL\ProductBundles` namespace:

| Class                | Namespace                              | Description                             |
|----------------------|----------------------------------------|-----------------------------------------|
| `BundleConfig`       | `WPL\ProductBundles\Config`            | Immutable bundle configuration.         |
| `ComponentConfig`    | `WPL\ProductBundles\Config`            | Immutable component slot configuration. |
| `BundleSelection`    | `WPL\ProductBundles\Selection`         | Customer's full bundle selection.       |
| `ComponentSelection` | `WPL\ProductBundles\Selection`         | Customer's choice for one slot.         |
| `ValidationResult`   | `WPL\ProductBundles\Validation`        | Accumulated validation errors.          |
| `PriceResult`        | `WPL\ProductBundles\Pricing`           | Computed price for a selection.         |
| `ComponentPrice`     | `WPL\ProductBundles\Pricing`           | Per-component price breakdown.          |

Use `BundleConfig::fromArray()` and `ComponentConfig::fromArray()` to construct modified instances from `toArray()` output; all fields have safe defaults and unknown keys are ignored.
