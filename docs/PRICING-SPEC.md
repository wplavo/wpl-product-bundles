# WPL Product Bundles — Pricing Specification

**Status:** Phase 1 proposal. Not yet implemented.  
**Last updated:** 2026-10-09

---

## 1. Pricing Modes

Three mutually exclusive modes are configured per bundle product:

| Mode | Description |
|---|---|
| `per_bundle` | Admin sets a single fixed price on the bundle WC product. Component prices are not shown to the customer. |
| `per_component` | Bundle price = sum of resolved component prices after per-component discounts. Admin sets a bundle-level discount as an optional second pass. |
| `tiered` | Bundle has a tiered price table based on total bundle quantity in the cart. Not related to component prices. |

---

## 2. Per-Bundle Pricing

### 2.1 Calculation

```
bundle_price = WC_Product_Bundle::get_price()
             = regular_price  (or sale_price if sale is active)
```

This is the standard WooCommerce price field. No component prices are summed. The plugin does not override this calculation—it relies entirely on WooCommerce's own price storage and retrieval.

### 2.2 Display

- Single price shown on product page and in cart
- Component list shown below the price but without individual prices
- No per-component line items in cart totals (components are hidden)

### 2.3 Sale price

Standard WooCommerce sale price dates and amounts apply. No plugin-specific logic.

---

## 3. Per-Component Pricing

### 3.1 Calculation algorithm

For each component `c` in the resolved bundle configuration:

```
component_base_price(c) =
    if c.price_mode == 'free':      0.00
    if c.price_mode == 'override':  c.override_price
    if c.price_mode == 'component_default':
        wc_get_product(c.product_id or c.variation_id)->get_price()
        // get_price() returns sale_price if a WC sale is active, otherwise regular_price
```

```
component_discounted_price(c) =
    component_base_price(c) * (1 - c.discount / 100)
```

```
component_line_price(c) =
    component_discounted_price(c) * c.resolved_quantity
```

```
bundle_subtotal =
    sum(component_line_price(c) for c in components)
```

```
bundle_price_after_bundle_discount =
    bundle_subtotal * (1 - bundle_discount / 100)
```

```
final_bundle_price = bundle_price_after_bundle_discount
```

In the cart, the **parent bundle item** carries `price = 0`; each **child component item** carries its `component_discounted_price`. WooCommerce sums all cart items, so the total is correct.

### 3.2 Rounding

- Component prices are rounded to `wc_get_price_decimals()` decimal places (store setting) using PHP's `round()` with `PHP_ROUND_HALF_UP`.
- Rounding is applied **after** discount multiplication, not before.
- This is done once per component, not per unit within the component line.
- The bundle-level discount is applied to the already-rounded component sum, then rounded again.

```php
$price = round( $base * (1 - $discount / 100), wc_get_price_decimals() );
```

### 3.3 Currency

All calculations are in the store's configured base currency. Multi-currency (e.g. WPML WooCommerce Multilingual) is out of scope for Phase 1.

### 3.4 Tax handling

- The plugin **never** calculates tax directly.
- Tax rates, inclusive/exclusive display, and tax class inheritance come from WooCommerce.
- Component products retain their own tax class.
- In `per_component` mode, child cart items carry the component product's tax class. WooCommerce applies tax per line item using `wc_get_price_including_tax()` / `wc_get_price_excluding_tax()` as appropriate for the store's display settings.
- The parent bundle cart item has `price = 0` and tax class `inherit` (uses the store's default).
- When `tax_display_shop` is `incl`, display prices must be retrieved via `wc_get_price_including_tax($product, ['price' => $calculated_price])`, not by adding the percentage manually.

### 3.5 Display in cart

In `per_component` mode:
- Parent bundle cart item shows the **sum** of component prices (computed in `CartDisplayHandler`)
- Child component items are **hidden** from the cart table but their line totals contribute to the cart total
- Bundle item "price" column shows the formatted bundle subtotal
- Individual component prices shown below the bundle name in a collapsed list

---

## 4. Tiered Pricing

### 4.1 Structure

```json
[
  { "min_qty": 1,  "max_qty": 5,    "price": "29.99" },
  { "min_qty": 6,  "max_qty": 11,   "price": "24.99" },
  { "min_qty": 12, "max_qty": null, "price": "19.99" }
]
```

### 4.2 Resolution

Given `$cart_bundle_qty` (the quantity of the bundle item in the cart):

```
for each tier in order:
    if cart_bundle_qty >= tier.min_qty AND (tier.max_qty is null OR cart_bundle_qty <= tier.max_qty):
        bundle_unit_price = tier.price
        break
if no tier matched:
    bundle_unit_price = first_tier.price  // fallback; validation should prevent gaps
```

### 4.3 Recalculation trigger

Tiered price is recalculated on every `woocommerce_before_calculate_totals` action. Changing the quantity in the cart re-evaluates the tier.

### 4.4 Display on product page

When `price_mode = tiered`, the product page renders a pricing table showing all tiers. The standard WooCommerce `$product->get_price_html()` override returns the lowest-tier price with a "from" prefix.

---

## 5. Discounts

### 5.1 Component-level discount (per_component mode only)

Stored on each component as `discount` (numeric string, 0–100). Applied before bundle-level discount.

### 5.2 Bundle-level discount (per_component mode only)

Stored on the bundle as `_wpl_bundle_discount` (numeric string, 0–100). Applied to the sum of discounted component prices.

### 5.3 WooCommerce coupon stacking

Coupons applied by the customer interact with bundle pricing as follows:

| Coupon type | Behavior |
|---|---|
| Fixed cart discount | Applied to cart total after bundle prices are set. Works normally. |
| Percentage cart discount | Applied to cart total. Works normally. |
| Fixed product discount | If applied to the **bundle product**, coupon reduces the parent bundle item's price. Children are unaffected. |
| Percentage product discount | Same: applies to the bundle product (parent item). |
| Coupon targeted at a **component product** | The component child cart item is a real WC cart item; `woocommerce_coupon_is_valid_for_product` will evaluate it. **Risk:** a coupon for a standalone product that is also used as a bundle component may apply inside the bundle unexpectedly. Mitigation: provide an admin setting `Exclude bundle components from product coupons` (default: off in Phase 1; documented as a known edge case). |

### 5.4 Sale prices on components (per_component mode)

`get_price()` on a `WC_Product` already returns the sale price when a WC scheduled sale is active. The plugin uses `get_price()` intentionally. If the admin wants to disable sale prices for bundle components, they should use the `override_price` field or set `price_mode = override` on the component.

---

## 6. Price Calculation Timing

| Event | Action |
|---|---|
| Product page load | `PricingService` calculates display price for the configured default component quantities |
| Add to cart | `AddToCartHandler` re-calculates with submitted quantities (server-side) |
| `woocommerce_before_calculate_totals` | `CartIntegration` re-injects component prices onto child items; any client-submitted prices are discarded |
| Checkout order creation | Prices are read from cart items (already server-validated); `OrderHandler` copies them to order items |
| Admin order edit | Prices are read from order item meta; no recalculation unless admin explicitly triggers it |

**Client-submitted price data is never trusted.** The plugin always recalculates from product meta on the server.

---

## 7. Edge Cases

| Scenario | Handling |
|---|---|
| Component product has no price set | `get_price()` returns `''`; treated as `0.00` |
| Component product is on sale but the sale ended while in cart | `woocommerce_before_calculate_totals` recalculates on every cart page load; price updates with a notice |
| Bundle discount + coupon → price below zero | Clamp to 0.00; log a notice in WC log |
| Tax-inclusive store with rounding | Always use WC's `wc_get_price_including_tax()` for display; never add tax percentage manually |
| Guest checkout price mismatch | Session is server-side in WC; same recalculation path |
| Zero-price bundle (all components free + bundle discount 100%) | Allowed; purchasable; order is created with `0.00` total for bundle line items |
| Tiered pricing + M&M variable quantity | Tier is evaluated against the total bundle item quantity (the quantity of the parent cart item), not the sum of component units |
