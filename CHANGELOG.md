# Changelog

All notable changes to WPL Product Bundles are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versions follow [Semantic Versioning](https://semver.org/).

---

## [1.0.0] — 2026-10-11

### Added

**Core product type**
- `bundle` product type registration, fully compatible with WooCommerce product taxonomy.
- HPOS compatibility declaration; all order operations use the `WC_Order` API exclusively.
- WooCommerce Blocks compatibility declaration (product type registration only).

**Bundle configuration**
- Admin product editor tab: mode (`fixed` / `mix_and_match`), pricing mode, discount, quantity constraints, component slots, price tiers.
- Per-component settings: slot title, product/variation assignment, quantity min/max/default, optional flag, component pricing mode (`component_default` / `custom_price` / `free`), component-level discount, allowed products/variations (M&M), stock mode.
- Idempotent schema migrations via `MigrationRunner`.

**Three pricing modes**
- `per_bundle` — uses the WooCommerce product price field directly.
- `per_component` — sums component prices with component- and bundle-level discounts; rounding per PRICING-SPEC §3.2.
- `tiered` — fixed unit price from a quantity-indexed tier table.

**Cart and checkout**
- Add-to-cart handler with server-side nonce verification and `SelectionValidator` enforcement.
- `CartPriceManager` recalculates all component prices on every `woocommerce_before_calculate_totals` hook — browser-submitted prices are never trusted.
- `CartSyncManager` keeps child-item quantities in sync with the parent bundle quantity and removes orphaned children.
- Child cart items hidden from display; bundle parent shows consolidated line.

**Order integration**
- Bundle meta written to `WC_Order_Item` on order placement.
- Parent stock double-adjustment guard: child component items are excluded from WooCommerce's default stock deduction.

**REST API** (`wpl/v1` namespace)
- `GET /wpl/v1/bundles` — paginated bundle list (requires `edit_products`).
- `GET /wpl/v1/bundles/{id}` — bundle config; view context omits merchant-sensitive fields.
- `PUT /wpl/v1/bundles/{id}` — update bundle config (requires `edit_products`).
- `POST /wpl/v1/bundles/{id}/estimate` — server-side price estimate (public).

**Developer extension hooks**
- `wpl_bundle_config` — filter loaded bundle configuration.
- `wpl_bundle_validation_result` — filter selection validation result.
- `wpl_bundle_price_result` — filter computed price result.
- `wpl_bundle_after_add_to_cart` — action after all child items are added.
- `wpl_bundle_component_visible` — filter component item visibility in cart.

**Frontend**
- Bundle component form with live price estimation (per_component mode), quantity controls, optional component toggles, M&M product selector with stock indicators, M&M progress bar, accessible labels and ARIA attributes.
- WooCommerce template override pattern — themes can override via `{theme}/woocommerce/`.
- Assets loaded only on bundle product pages.

**Documentation**
- `docs/ARCHITECTURE.md` — system design and component relationships.
- `docs/DATA-MODEL.md` — stored data structures and schema versions.
- `docs/PRICING-SPEC.md` — pricing rules and rounding specification.
- `docs/REST-API.md` — full API reference with request/response schemas.
- `docs/HOOKS-FILTERS.md` — hook reference with argument types and examples.
- `docs/SECURITY-AUDIT.md` — full security audit report.
- `docs/COMPATIBILITY.md` — WordPress/WooCommerce/PHP compatibility matrix.
- `docs/PERFORMANCE.md` — query analysis and performance guidance.
- `docs/INSTALLATION.md` — installation, upgrade, troubleshooting, and uninstall guide.

**Quality assurance**
- 228 PHPUnit tests, 388 assertions — 100% pass rate.
- PHPStan level 8 — 0 errors.
- PHPCS 73/73 files clean (WordPress Coding Standards + PSR-12 in src/).

### Known limitations in 1.0.0

- WooCommerce Cart/Checkout Blocks add-to-cart validation is not supported (classic single-product page is fully supported). See `docs/COMPATIBILITY.md`.
- New WooCommerce product block editor is not supported; use classic editor for bundle configuration.
- Coupon interaction with component products that are also sold standalone may cause unintended double discounts. See `docs/SECURITY-AUDIT.md` UR-2.
- No plugin-level rate limiting on the `/estimate` REST endpoint. See `docs/SECURITY-AUDIT.md` UR-3.
