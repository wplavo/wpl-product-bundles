<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Admin;

use WPL\ProductBundles\Config\BundleConfig;
use WPL\ProductBundles\Config\BundleConfigRepositoryInterface;
use WPL\ProductBundles\Config\ComponentConfig;

/**
 * Registers and renders the Bundle configuration tab in the WooCommerce product editor.
 *
 * Tab target: #wpl_bundle_data (must match the `target` key in registerTab()).
 * The tab is shown only when the product type is 'bundle' (class show_if_bundle).
 *
 * HTML structure follows WC conventions: each panel is a div.panel.woocommerce_options_panel,
 * fields are p.form-field, and grouped by div.options_group.
 */
final class BundleProductTab {

    public function __construct(
        private readonly BundleConfigRepositoryInterface $repository,
    ) {}

    // ── Hook handlers ────────────────────────────────────────────────────────

    /**
     * Registers the Bundle tab.
     *
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public function registerTab(array $tabs): array {
        $tabs['bundle'] = [
            'label'    => __('Bundle', 'wpl-product-bundles'),
            'target'   => 'wpl_bundle_data',
            'class'    => ['show_if_bundle'],
            'priority' => 15,
        ];
        return $tabs;
    }

    /**
     * Renders the panel HTML inside woocommerce_product_data_panels.
     */
    public function renderPanel(): void {
        $productId = (int) get_the_ID();
        $config    = $this->repository->get($productId);
        ?>
        <div id="wpl_bundle_data" class="panel woocommerce_options_panel wpl-bundle-panel">
            <?php wp_nonce_field('wpl_save_bundle_' . $productId, '_wpl_bundle_nonce'); ?>

            <?php $this->renderBundleSettings($config); ?>
            <?php $this->renderPriceTiers($config); ?>
            <?php $this->renderComponents($productId, $config); ?>
            <?php $this->renderComponentTemplate(); ?>
            <?php $this->renderTierTemplate(); ?>
        </div>
        <?php
    }

    /**
     * Enqueues admin assets on the product edit screen.
     */
    public function enqueueAssets(string $hookSuffix): void {
        if (!in_array($hookSuffix, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'product') {
            return;
        }

        wp_enqueue_script(
            'wpl-admin-bundle',
            WPL_PRODUCT_BUNDLES_URL . 'assets/js/admin-bundle.js',
            ['jquery', 'select2'],
            WPL_PRODUCT_BUNDLES_VERSION,
            true
        );

        wp_enqueue_style(
            'wpl-admin-bundle',
            WPL_PRODUCT_BUNDLES_URL . 'assets/css/admin-bundle.css',
            ['woocommerce_admin_styles'],
            WPL_PRODUCT_BUNDLES_VERSION
        );

        wp_localize_script('wpl-admin-bundle', 'wplAdminBundle', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'searchNonce' => wp_create_nonce('wpl_product_search'),
            'i18n'        => [
                'searchProducts'  => __('Search for a product…', 'wpl-product-bundles'),
                'selectVariation' => __('— Select variation —', 'wpl-product-bundles'),
                'noVariation'     => __('(no specific variation)', 'wpl-product-bundles'),
                'addComponent'    => __('Add Component', 'wpl-product-bundles'),
                'removeComponent' => __('Remove', 'wpl-product-bundles'),
                'confirmRemove'   => __('Remove this component?', 'wpl-product-bundles'),
                'moveUp'          => __('Move up', 'wpl-product-bundles'),
                'moveDown'        => __('Move down', 'wpl-product-bundles'),
                'addTier'         => __('Add Tier', 'wpl-product-bundles'),
                'removeTier'      => __('Remove tier', 'wpl-product-bundles'),
                'newComponent'    => __('New Component', 'wpl-product-bundles'),
                'loadingVar'      => __('Loading variations…', 'wpl-product-bundles'),
            ],
        ]);
    }

    // ── Panel sections ───────────────────────────────────────────────────────

    private function renderBundleSettings(?BundleConfig $config): void {
        $mode         = $config?->mode ?? 'fixed';
        $priceMode    = $config?->priceMode ?? 'per_bundle';
        $discount     = $config?->discount ?? '0.00';
        $minQty       = $config?->minQty ?? 1;
        $maxQty       = $config?->maxQty ?? 0;
        ?>
        <div class="options_group">
            <p class="form-field">
                <label for="wpl_bundle_mode"><?php esc_html_e('Bundle Mode', 'wpl-product-bundles'); ?></label>
                <select id="wpl_bundle_mode" name="wpl_bundle[mode]" class="select short">
                    <option value="fixed"<?php selected($mode, 'fixed'); ?>>
                        <?php esc_html_e('Fixed — components are pre-configured', 'wpl-product-bundles'); ?>
                    </option>
                    <option value="mix_and_match"<?php selected($mode, 'mix_and_match'); ?>>
                        <?php esc_html_e('Mix & Match — customer selects products', 'wpl-product-bundles'); ?>
                    </option>
                </select>
                <?php echo wc_help_tip(esc_html__('Fixed bundles have pre-set components. Mix & Match lets customers choose from allowed products.', 'wpl-product-bundles')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip returns safe HTML ?>
            </p>

            <p class="form-field">
                <label for="wpl_bundle_price_mode"><?php esc_html_e('Pricing Mode', 'wpl-product-bundles'); ?></label>
                <select id="wpl_bundle_price_mode" name="wpl_bundle[price_mode]" class="select short">
                    <option value="per_bundle"<?php selected($priceMode, 'per_bundle'); ?>>
                        <?php esc_html_e('Per bundle — fixed bundle price', 'wpl-product-bundles'); ?>
                    </option>
                    <option value="per_component"<?php selected($priceMode, 'per_component'); ?>>
                        <?php esc_html_e('Per component — sum of component prices', 'wpl-product-bundles'); ?>
                    </option>
                    <option value="tiered"<?php selected($priceMode, 'tiered'); ?>>
                        <?php esc_html_e('Tiered — price by quantity range', 'wpl-product-bundles'); ?>
                    </option>
                </select>
                <?php echo wc_help_tip(esc_html__('Per bundle uses the WooCommerce product price. Per component sums component prices. Tiered applies a different price per quantity range.', 'wpl-product-bundles')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <p class="form-field wpl-field-bundle-discount" style="display:none">
                <label for="wpl_bundle_discount"><?php esc_html_e('Bundle Discount (%)', 'wpl-product-bundles'); ?></label>
                <input type="number" id="wpl_bundle_discount"
                    name="wpl_bundle[discount]"
                    value="<?php echo esc_attr($discount); ?>"
                    step="0.01" min="0" max="100"
                    class="short"
                    placeholder="0.00">
                <?php echo wc_help_tip(esc_html__('Percentage discount applied on top of individual component prices. Enter 0 for no discount.', 'wpl-product-bundles')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <p class="form-field wpl-field-mm-qty" style="display:none">
                <label for="wpl_bundle_min_qty"><?php esc_html_e('Minimum Items', 'wpl-product-bundles'); ?></label>
                <input type="number" id="wpl_bundle_min_qty"
                    name="wpl_bundle[min_qty]"
                    value="<?php echo esc_attr((string) $minQty); ?>"
                    step="1" min="1"
                    class="short">
                <?php echo wc_help_tip(esc_html__('Minimum total quantity a customer must select (Mix & Match only).', 'wpl-product-bundles')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>

            <p class="form-field wpl-field-mm-qty" style="display:none">
                <label for="wpl_bundle_max_qty"><?php esc_html_e('Maximum Items', 'wpl-product-bundles'); ?></label>
                <input type="number" id="wpl_bundle_max_qty"
                    name="wpl_bundle[max_qty]"
                    value="<?php echo esc_attr((string) $maxQty); ?>"
                    step="1" min="0"
                    class="short"
                    placeholder="<?php esc_attr_e('0 = unlimited', 'wpl-product-bundles'); ?>">
                <?php echo wc_help_tip(esc_html__('Maximum total quantity. Enter 0 for unlimited (Mix & Match only).', 'wpl-product-bundles')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>
        </div>
        <?php
    }

    private function renderPriceTiers(?BundleConfig $config): void {
        $tiers = $config?->priceTiers ?? [];
        ?>
        <div class="options_group wpl-field-tiered" style="display:none">
            <p><strong><?php esc_html_e('Price Tiers', 'wpl-product-bundles'); ?></strong>
                <?php echo wc_help_tip(esc_html__('Define price breaks by quantity. Rows are evaluated from top to bottom; the first matching tier is used.', 'wpl-product-bundles')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>
            <table class="wpl-price-tiers-table widefat" style="max-width:520px">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Min Qty', 'wpl-product-bundles'); ?></th>
                        <th><?php esc_html_e('Max Qty', 'wpl-product-bundles'); ?></th>
                        <th><?php esc_html_e('Price', 'wpl-product-bundles'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="wpl-tiers-body">
                    <?php foreach ($tiers as $i => $tier) : ?>
                    <tr class="wpl-tier-row" data-tier-index="<?php echo esc_attr((string) $i); ?>">
                        <td>
                            <input type="number" class="short"
                                name="wpl_bundle[price_tiers][<?php echo esc_attr((string) $i); ?>][min_qty]"
                                value="<?php echo esc_attr((string) ($tier['min_qty'] ?? '')); ?>"
                                min="1" step="1" required>
                        </td>
                        <td>
                            <input type="number" class="short"
                                name="wpl_bundle[price_tiers][<?php echo esc_attr((string) $i); ?>][max_qty]"
                                value="<?php echo esc_attr((string) ($tier['max_qty'] ?? '')); ?>"
                                min="0" step="1"
                                placeholder="<?php esc_attr_e('∞', 'wpl-product-bundles'); ?>">
                        </td>
                        <td>
                            <input type="text" class="short"
                                name="wpl_bundle[price_tiers][<?php echo esc_attr((string) $i); ?>][price]"
                                value="<?php echo esc_attr((string) ($tier['price'] ?? '')); ?>"
                                placeholder="0.00">
                        </td>
                        <td>
                            <button type="button" class="button wpl-remove-tier"
                                aria-label="<?php esc_attr_e('Remove tier', 'wpl-product-bundles'); ?>">✕</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="button wpl-add-tier" style="margin-top:.5em">
                <?php esc_html_e('+ Add Tier', 'wpl-product-bundles'); ?>
            </button>
        </div>
        <?php
    }

    private function renderComponents(int $productId, ?BundleConfig $config): void {
        $components = $config?->getComponents() ?? [];
        ?>
        <div class="options_group">
            <p><strong><?php esc_html_e('Bundle Components', 'wpl-product-bundles'); ?></strong></p>
            <div id="wpl-components-list">
                <?php foreach ($components as $index => $component) : ?>
                    <?php $this->renderComponentRow($index, $component); ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button wpl-add-component" style="margin:.75em 0">
                <?php esc_html_e('+ Add Component', 'wpl-product-bundles'); ?>
            </button>
        </div>
        <?php
    }

    private function renderComponentRow(int $sortOrder, ComponentConfig $component): void {
        $compId      = esc_attr($component->id);
        $productName = '';
        if ($component->productId > 0) {
            $p           = wc_get_product($component->productId);
            $productName = $p instanceof \WC_Product ? $p->get_name() : '';
        }
        $variationLabel = '';
        if ($component->variationId !== null) {
            $v = wc_get_product($component->variationId);
            if ($v instanceof \WC_Product_Variation) {
                $variationLabel = implode(' / ', array_filter(
                    $v->get_variation_attributes()
                ));
            }
        }
        $headerName = $component->slotTitle !== '' ? $component->slotTitle : $productName;
        if ($headerName === '') {
            $headerName = __('Component', 'wpl-product-bundles');
        }
        ?>
        <div class="wpl-component-row" data-comp-id="<?php echo $compId; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
            <input type="hidden" name="wpl_bundle[components][<?php echo $compId; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>][id]" value="<?php echo $compId; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
            <input type="hidden" name="wpl_bundle[components][<?php echo $compId; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>][sort_order]" class="wpl-comp-sort-order" value="<?php echo esc_attr((string) $sortOrder); ?>">

            <div class="wpl-component-row__header">
                <button type="button" class="wpl-comp-move-up button-link" aria-label="<?php esc_attr_e('Move up', 'wpl-product-bundles'); ?>">▲</button>
                <button type="button" class="wpl-comp-move-down button-link" aria-label="<?php esc_attr_e('Move down', 'wpl-product-bundles'); ?>">▼</button>
                <span class="wpl-comp-header-name"><?php echo esc_html($headerName); ?></span>
                <button type="button" class="wpl-comp-remove button-link" aria-label="<?php esc_attr_e('Remove component', 'wpl-product-bundles'); ?>">✕</button>
            </div>

            <div class="wpl-component-row__fields">
                <?php $this->renderComponentFields($compId, $component, $productName, $variationLabel); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renders all inner fields for one component row.
     * Called from both renderComponentRow() (for existing components) and renderComponentTemplate() (placeholder).
     *
     * @param string            $compId         Already esc_attr-escaped UUID.
     * @param ComponentConfig|null $component   null when rendering the JS template.
     * @param string            $productName    Pre-fetched product name ('' for template).
     * @param string            $variationLabel Pre-fetched variation label ('' for template).
     */
    private function renderComponentFields(
        string $compId,
        ?ComponentConfig $component,
        string $productName,
        string $variationLabel
    ): void {
        $slotTitle       = $component?->slotTitle ?? '';
        $productId       = $component?->productId ?? 0;
        $variationId     = $component?->variationId ?? null;
        $qtyMin          = $component?->quantityMin ?? 1;
        $qtyMax          = $component?->quantityMax ?? 1;
        $qtyDefault      = $component?->quantityDefault ?? 1;
        $optional        = $component?->optional ?? false;
        $compPriceMode   = $component?->priceMode ?? 'component_default';
        $discount        = $component?->discount ?? '0.00';
        $overridePrice   = $component?->overridePrice ?? '';
        $stockMode       = $component?->stockMode ?? 'inherit';
        $allowedProducts = $component?->allowedProducts ?? [];

        $nameBase = 'wpl_bundle[components][' . $compId . ']'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $compId is already esc_attr-escaped
        ?>
        <table class="widefat wpl-component-fields">
            <tbody>
                <tr>
                    <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Product', 'wpl-product-bundles'); ?></span></td>
                    <td>
                        <select
                            name="<?php echo esc_attr($nameBase); ?>[product_id]"
                            class="wpl-product-search--single wpl-product-search"
                            data-comp-id="<?php echo $compId; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
                            style="min-width:300px">
                            <?php if ($productId > 0 && $productName !== '') : ?>
                            <option value="<?php echo esc_attr((string) $productId); ?>" selected>
                                <?php echo esc_html($productName . ' (#' . $productId . ')'); ?>
                            </option>
                            <?php endif; ?>
                        </select>
                    </td>
                </tr>

                <tr class="wpl-variation-row" style="<?php echo $variationId === null ? 'display:none' : ''; ?>">
                    <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Variation', 'wpl-product-bundles'); ?></span></td>
                    <td>
                        <select
                            name="<?php echo esc_attr($nameBase); ?>[variation_id]"
                            class="wpl-variation-select">
                            <option value=""><?php esc_html_e('— Any variation —', 'wpl-product-bundles'); ?></option>
                            <?php if ($variationId !== null && $variationLabel !== '') : ?>
                            <option value="<?php echo esc_attr((string) $variationId); ?>" selected>
                                <?php echo esc_html($variationLabel); ?>
                            </option>
                            <?php endif; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Slot Title', 'wpl-product-bundles'); ?></span></td>
                    <td>
                        <input type="text"
                            name="<?php echo esc_attr($nameBase); ?>[slot_title]"
                            value="<?php echo esc_attr($slotTitle); ?>"
                            placeholder="<?php esc_attr_e('Leave blank to use product name', 'wpl-product-bundles'); ?>"
                            class="regular-text">
                    </td>
                </tr>

                <tr>
                    <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Qty (min / max / default)', 'wpl-product-bundles'); ?></span></td>
                    <td class="wpl-qty-row">
                        <input type="number" name="<?php echo esc_attr($nameBase); ?>[quantity_min]"
                            value="<?php echo esc_attr((string) $qtyMin); ?>"
                            min="0" step="1" class="tiny-text"
                            aria-label="<?php esc_attr_e('Minimum quantity', 'wpl-product-bundles'); ?>">
                        <span class="wpl-qty-sep">/</span>
                        <input type="number" name="<?php echo esc_attr($nameBase); ?>[quantity_max]"
                            value="<?php echo esc_attr((string) $qtyMax); ?>"
                            min="0" step="1" class="tiny-text"
                            aria-label="<?php esc_attr_e('Maximum quantity', 'wpl-product-bundles'); ?>">
                        <span class="wpl-qty-sep">/</span>
                        <input type="number" name="<?php echo esc_attr($nameBase); ?>[quantity_default]"
                            value="<?php echo esc_attr((string) $qtyDefault); ?>"
                            min="0" step="1" class="tiny-text"
                            aria-label="<?php esc_attr_e('Default quantity', 'wpl-product-bundles'); ?>">
                    </td>
                </tr>

                <tr>
                    <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Optional', 'wpl-product-bundles'); ?></span></td>
                    <td>
                        <input type="checkbox"
                            name="<?php echo esc_attr($nameBase); ?>[optional]"
                            value="1"
                            <?php checked($optional); ?>>
                        <span class="description"><?php esc_html_e('Customer may exclude this component', 'wpl-product-bundles'); ?></span>
                    </td>
                </tr>

                <tr>
                    <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Component Price Mode', 'wpl-product-bundles'); ?></span></td>
                    <td>
                        <select name="<?php echo esc_attr($nameBase); ?>[price_mode]" class="select">
                            <option value="component_default"<?php selected($compPriceMode, 'component_default'); ?>>
                                <?php esc_html_e('Use product price', 'wpl-product-bundles'); ?>
                            </option>
                            <option value="custom_price"<?php selected($compPriceMode, 'custom_price'); ?>>
                                <?php esc_html_e('Override price', 'wpl-product-bundles'); ?>
                            </option>
                            <option value="free"<?php selected($compPriceMode, 'free'); ?>>
                                <?php esc_html_e('Free (included in bundle)', 'wpl-product-bundles'); ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Component Discount (%)', 'wpl-product-bundles'); ?></span></td>
                    <td>
                        <input type="number"
                            name="<?php echo esc_attr($nameBase); ?>[discount]"
                            value="<?php echo esc_attr($discount); ?>"
                            step="0.01" min="0" max="100"
                            class="short">
                    </td>
                </tr>

                <tr>
                    <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Override Price', 'wpl-product-bundles'); ?></span></td>
                    <td>
                        <input type="text"
                            name="<?php echo esc_attr($nameBase); ?>[override_price]"
                            value="<?php echo esc_attr($overridePrice); ?>"
                            class="short"
                            placeholder="<?php esc_attr_e('Leave blank to use product price', 'wpl-product-bundles'); ?>">
                    </td>
                </tr>

                <tr>
                    <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Stock Mode', 'wpl-product-bundles'); ?></span></td>
                    <td>
                        <select name="<?php echo esc_attr($nameBase); ?>[stock_mode]" class="select">
                            <option value="inherit"<?php selected($stockMode, 'inherit'); ?>>
                                <?php esc_html_e('Inherit from product', 'wpl-product-bundles'); ?>
                            </option>
                            <option value="shared"<?php selected($stockMode, 'shared'); ?>>
                                <?php esc_html_e('Shared — deduct component stock', 'wpl-product-bundles'); ?>
                            </option>
                            <option value="independent"<?php selected($stockMode, 'independent'); ?>>
                                <?php esc_html_e('Independent — no stock deduction', 'wpl-product-bundles'); ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <tr class="wpl-field-mm-allowed" style="display:none">
                    <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Allowed Products (M&amp;M)', 'wpl-product-bundles'); ?></span></td>
                    <td>
                        <select multiple
                            name="<?php echo esc_attr($nameBase); ?>[allowed_products][]"
                            class="wpl-product-search--multi wpl-product-search"
                            style="min-width:300px">
                            <?php foreach ($allowedProducts as $apId) : ?>
                                <?php
                                $ap = wc_get_product($apId);
                                if (!$ap instanceof \WC_Product) {
                                    continue;
                                }
                                ?>
                                <option value="<?php echo esc_attr((string) $apId); ?>" selected>
                                    <?php echo esc_html($ap->get_name() . ' (#' . $apId . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="description"><?php esc_html_e('Products the customer may select for this slot. Leave empty to allow any.', 'wpl-product-bundles'); ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Outputs a <template> element used by JS to clone new component rows.
     * Placeholders: __COMP_ID__ (UUID) and __SORT_ORDER__ (integer).
     */
    private function renderComponentTemplate(): void {
        ?>
        <template id="wpl-component-template">
            <div class="wpl-component-row" data-comp-id="__COMP_ID__">
                <input type="hidden" name="wpl_bundle[components][__COMP_ID__][id]" value="__COMP_ID__">
                <input type="hidden" name="wpl_bundle[components][__COMP_ID__][sort_order]" class="wpl-comp-sort-order" value="__SORT_ORDER__">

                <div class="wpl-component-row__header">
                    <button type="button" class="wpl-comp-move-up button-link" aria-label="<?php esc_attr_e('Move up', 'wpl-product-bundles'); ?>">▲</button>
                    <button type="button" class="wpl-comp-move-down button-link" aria-label="<?php esc_attr_e('Move down', 'wpl-product-bundles'); ?>">▼</button>
                    <span class="wpl-comp-header-name"><?php esc_html_e('New Component', 'wpl-product-bundles'); ?></span>
                    <button type="button" class="wpl-comp-remove button-link" aria-label="<?php esc_attr_e('Remove component', 'wpl-product-bundles'); ?>">✕</button>
                </div>

                <div class="wpl-component-row__fields">
                    <table class="widefat wpl-component-fields">
                        <tbody>
                            <tr>
                                <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Product', 'wpl-product-bundles'); ?></span></td>
                                <td>
                                    <select name="wpl_bundle[components][__COMP_ID__][product_id]"
                                        class="wpl-product-search--single wpl-product-search"
                                        data-comp-id="__COMP_ID__"
                                        style="min-width:300px"></select>
                                </td>
                            </tr>
                            <tr class="wpl-variation-row" style="display:none">
                                <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Variation', 'wpl-product-bundles'); ?></span></td>
                                <td>
                                    <select name="wpl_bundle[components][__COMP_ID__][variation_id]" class="wpl-variation-select">
                                        <option value=""><?php esc_html_e('— Any variation —', 'wpl-product-bundles'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Slot Title', 'wpl-product-bundles'); ?></span></td>
                                <td>
                                    <input type="text" name="wpl_bundle[components][__COMP_ID__][slot_title]"
                                        value=""
                                        placeholder="<?php esc_attr_e('Leave blank to use product name', 'wpl-product-bundles'); ?>"
                                        class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Qty (min / max / default)', 'wpl-product-bundles'); ?></span></td>
                                <td class="wpl-qty-row">
                                    <input type="number" name="wpl_bundle[components][__COMP_ID__][quantity_min]"
                                        value="1" min="0" step="1" class="tiny-text"
                                        aria-label="<?php esc_attr_e('Minimum quantity', 'wpl-product-bundles'); ?>">
                                    <span class="wpl-qty-sep">/</span>
                                    <input type="number" name="wpl_bundle[components][__COMP_ID__][quantity_max]"
                                        value="1" min="0" step="1" class="tiny-text"
                                        aria-label="<?php esc_attr_e('Maximum quantity', 'wpl-product-bundles'); ?>">
                                    <span class="wpl-qty-sep">/</span>
                                    <input type="number" name="wpl_bundle[components][__COMP_ID__][quantity_default]"
                                        value="1" min="0" step="1" class="tiny-text"
                                        aria-label="<?php esc_attr_e('Default quantity', 'wpl-product-bundles'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Optional', 'wpl-product-bundles'); ?></span></td>
                                <td>
                                    <input type="checkbox" name="wpl_bundle[components][__COMP_ID__][optional]" value="1">
                                    <span class="description"><?php esc_html_e('Customer may exclude this component', 'wpl-product-bundles'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Component Price Mode', 'wpl-product-bundles'); ?></span></td>
                                <td>
                                    <select name="wpl_bundle[components][__COMP_ID__][price_mode]" class="select">
                                        <option value="component_default"><?php esc_html_e('Use product price', 'wpl-product-bundles'); ?></option>
                                        <option value="custom_price"><?php esc_html_e('Override price', 'wpl-product-bundles'); ?></option>
                                        <option value="free"><?php esc_html_e('Free (included in bundle)', 'wpl-product-bundles'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Component Discount (%)', 'wpl-product-bundles'); ?></span></td>
                                <td>
                                    <input type="number" name="wpl_bundle[components][__COMP_ID__][discount]"
                                        value="0.00" step="0.01" min="0" max="100" class="short">
                                </td>
                            </tr>
                            <tr>
                                <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Override Price', 'wpl-product-bundles'); ?></span></td>
                                <td>
                                    <input type="text" name="wpl_bundle[components][__COMP_ID__][override_price]"
                                        value="" class="short"
                                        placeholder="<?php esc_attr_e('Leave blank to use product price', 'wpl-product-bundles'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Stock Mode', 'wpl-product-bundles'); ?></span></td>
                                <td>
                                    <select name="wpl_bundle[components][__COMP_ID__][stock_mode]" class="select">
                                        <option value="inherit"><?php esc_html_e('Inherit from product', 'wpl-product-bundles'); ?></option>
                                        <option value="shared"><?php esc_html_e('Shared — deduct component stock', 'wpl-product-bundles'); ?></option>
                                        <option value="independent"><?php esc_html_e('Independent — no stock deduction', 'wpl-product-bundles'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr class="wpl-field-mm-allowed" style="display:none">
                                <td class="wpl-field-label"><span class="wpl-comp-label"><?php esc_html_e('Allowed Products (M&amp;M)', 'wpl-product-bundles'); ?></span></td>
                                <td>
                                    <select multiple
                                        name="wpl_bundle[components][__COMP_ID__][allowed_products][]"
                                        class="wpl-product-search--multi wpl-product-search"
                                        style="min-width:300px"></select>
                                    <span class="description"><?php esc_html_e('Products the customer may select for this slot.', 'wpl-product-bundles'); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
        <?php
    }

    /**
     * Outputs a <template> element for new price tier rows.
     * Placeholder: __TIER_INDEX__ (integer).
     */
    private function renderTierTemplate(): void {
        ?>
        <template id="wpl-tier-template">
            <tr class="wpl-tier-row">
                <td>
                    <input type="number" class="short"
                        name="wpl_bundle[price_tiers][__TIER_INDEX__][min_qty]"
                        value="" min="1" step="1" required>
                </td>
                <td>
                    <input type="number" class="short"
                        name="wpl_bundle[price_tiers][__TIER_INDEX__][max_qty]"
                        value="" min="0" step="1"
                        placeholder="<?php esc_attr_e('∞', 'wpl-product-bundles'); ?>">
                </td>
                <td>
                    <input type="text" class="short"
                        name="wpl_bundle[price_tiers][__TIER_INDEX__][price]"
                        value="" placeholder="0.00">
                </td>
                <td>
                    <button type="button" class="button wpl-remove-tier"
                        aria-label="<?php esc_attr_e('Remove tier', 'wpl-product-bundles'); ?>">✕</button>
                </td>
            </tr>
        </template>
        <?php
    }
}
