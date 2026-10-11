# WPL Product Bundles — REST API

Namespace: `wpl/v1`  
Base URL: `{site_url}/wp-json/wpl/v1`

---

## Authentication

WPL REST endpoints use WordPress's standard REST authentication layer.

- **Read operations** on published bundles require no authentication.
- **Read operations** on draft or private bundles require the `edit_products` capability.
- **Write operations** require the `edit_products` capability.
- Pass credentials via the standard `Authorization` header (Application Passwords), cookie authentication with `X-WP-Nonce`, or any registered `WP_REST_Authentication` handler.

---

## Endpoints

### GET /wpl/v1/bundles

List all bundle products.

**Requires:** `edit_products` capability.

**Query parameters:**

| Parameter  | Type    | Default | Description                              |
|------------|---------|---------|------------------------------------------|
| `per_page` | integer | 20      | Items per page (1–100).                  |
| `page`     | integer | 1       | Page number.                             |
| `search`   | string  | —       | Optional text search on product name.    |

**Response headers:**

- `X-WP-Total` — total number of matching bundle products
- `X-WP-TotalPages` — total pages at the current `per_page` value

**Example response (200):**

```json
[
  {
    "product_id": 42,
    "name": "Starter Kit",
    "status": "publish",
    "mode": "fixed",
    "price_mode": "per_component",
    "component_count": 3,
    "link": "https://example.com/wp-json/wpl/v1/bundles/42"
  }
]
```

---

### GET /wpl/v1/bundles/{product_id}

Return the bundle configuration for a single product.

**Requires:** No authentication for published products. `edit_products` for draft or private.

**Query parameters:**

| Parameter | Type   | Default | Description                                          |
|-----------|--------|---------|------------------------------------------------------|
| `context` | string | `view`  | `view` (public-safe) or `edit` (includes internals). |

**View context** omits merchant-internal pricing fields (`discount`, `override_price`, `stock_mode`).  
**Edit context** returns the complete configuration.

**Example response — view context (200):**

```json
{
  "product_id": 42,
  "mode": "fixed",
  "price_mode": "per_component",
  "min_qty": 1,
  "max_qty": 0,
  "components": [
    {
      "id": "3f2a8b1c-...",
      "sort_order": 0,
      "product_id": 10,
      "variation_id": null,
      "slot_title": "Main Item",
      "quantity_min": 1,
      "quantity_max": 1,
      "quantity_default": 1,
      "optional": false,
      "price_mode": "component_default",
      "allowed_products": [],
      "allowed_categories": [],
      "allowed_variations": []
    }
  ],
  "price_tiers": []
}
```

**Edit context** additionally includes per-bundle `discount` and per-component `discount`, `override_price`, and `stock_mode`.

**Error responses:**

| Code | Error code           | Meaning                                         |
|------|----------------------|-------------------------------------------------|
| 404  | `wpl_not_found`      | No WooCommerce product found for this ID.       |
| 403  | `wpl_forbidden`      | Product not published and caller lacks access.  |
| 404  | `wpl_not_configured` | Product exists but has no bundle configuration. |

---

### PUT /wpl/v1/bundles/{product_id}

Replace the bundle configuration on an existing bundle product.

**Requires:** `edit_products` capability and `edit_product` capability for the specific product.

**Request body (JSON):**

```json
{
  "mode": "fixed",
  "price_mode": "per_component",
  "discount": "10.00",
  "min_qty": 1,
  "max_qty": 0,
  "components": [
    {
      "id": "3f2a8b1c-...",
      "sort_order": 0,
      "product_id": 10,
      "variation_id": null,
      "slot_title": "Main Item",
      "quantity_min": 1,
      "quantity_max": 1,
      "quantity_default": 1,
      "optional": false,
      "price_mode": "component_default",
      "discount": "0.00",
      "override_price": null,
      "allowed_products": [],
      "allowed_categories": [],
      "allowed_variations": [],
      "stock_mode": "inherit"
    }
  ],
  "price_tiers": []
}
```

**Field constraints:**

| Field            | Allowed values                                    |
|------------------|---------------------------------------------------|
| `mode`           | `fixed`, `mix_and_match` *(required)*             |
| `price_mode`     | `per_bundle`, `per_component`, `tiered`           |
| Component `price_mode` | `component_default`, `custom_price`, `free` |
| Component `stock_mode` | `inherit`, `shared`, `independent`          |

**Returns:** The saved configuration in `edit` context (same schema as GET with `?context=edit`).

**Error responses:**

| Code | Error code             | Meaning                                |
|------|------------------------|----------------------------------------|
| 403  | `wpl_forbidden`        | Caller cannot edit this product.       |
| 400  | `wpl_invalid_product`  | Product is not of type `bundle`.       |
| 400  | `wpl_invalid_mode`     | `mode` field is missing or invalid.    |

---

### POST /wpl/v1/bundles/{product_id}/estimate

Compute a price estimate for a given component selection.

**Requires:** No authentication.

**Security:** Component prices are never read from the request body. `PricingService` recalculates all prices from authoritative product meta. This endpoint is equivalent to the live price calculation performed on the storefront.

**Request body (JSON):**

```json
{
  "bundle_quantity": 1,
  "components": [
    {
      "component_id": "3f2a8b1c-...",
      "product_id": 10,
      "variation_id": null,
      "quantity": 1
    }
  ]
}
```

**Example response (200):**

```json
{
  "product_id": 42,
  "bundle_quantity": 1,
  "price_mode": "per_component",
  "subtotal": "29.98",
  "bundle_discount": "10.00",
  "total": "26.98",
  "components": [
    {
      "component_id": "3f2a8b1c-...",
      "unit_price": "14.99",
      "quantity": 1,
      "line_total": "14.99"
    }
  ]
}
```

For `per_bundle` and `tiered` modes, `subtotal` and `total` reflect the tier/fixed price and `components` is empty.

**Error responses:**

| Code | Error code              | Meaning                                              |
|------|-------------------------|------------------------------------------------------|
| 404  | `wpl_not_configured`    | Product not found or no bundle configuration.        |
| 400  | `wpl_invalid_component` | A `component_id` value does not match any slot.      |

---

## Integration Example

### JavaScript (Fetch API)

```js
async function getBundleConfig(productId) {
  const res = await fetch(`/wp-json/wpl/v1/bundles/${productId}`);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

async function estimatePrice(productId, selection) {
  const res = await fetch(`/wp-json/wpl/v1/bundles/${productId}/estimate`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ bundle_quantity: 1, components: selection }),
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}
```

### PHP (authenticated write)

```php
$response = wp_remote_request(
    rest_url( 'wpl/v1/bundles/42' ),
    [
        'method'  => 'PUT',
        'headers' => [
            'Content-Type' => 'application/json',
            'X-WP-Nonce'   => wp_create_nonce( 'wp_rest' ),
        ],
        'body' => wp_json_encode( [
            'mode'       => 'fixed',
            'price_mode' => 'per_component',
            'discount'   => '0.00',
            'min_qty'    => 1,
            'max_qty'    => 0,
            'components' => [
                [
                    'product_id'     => 10,
                    'slot_title'     => 'Main Item',
                    'quantity_min'   => 1,
                    'quantity_max'   => 1,
                    'quantity_default' => 1,
                    'price_mode'     => 'component_default',
                    'discount'       => '0.00',
                ],
            ],
        ] ),
    ]
);
```

---

## Backwards Compatibility

This API is versioned at `wpl/v1`. Any breaking change to an existing endpoint's response schema will be released under `wpl/v2`.

A field is considered part of the stable contract only after regression tests for it exist in the test suite. Fields marked **internal** in this document may change without a version bump.
