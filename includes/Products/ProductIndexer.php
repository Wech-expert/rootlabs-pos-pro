<?php

namespace MXPOSPro\Products;

defined('ABSPATH') || exit;

class ProductIndexResult
{
    public int $total_seen = 0;
    public int $total_indexed = 0;
    public int $simple_count = 0;
    public int $variable_count = 0;
    public int $variation_count = 0;
    public int $skipped_count = 0;
    public float $duration = 0.0;

    /** @var string[] */
    public array $errors = [];
}

class ProductIndexer
{
    private ProductIndexRepository $repository;

    private const BATCH_SIZE = 100;

    private const MAX_ERRORS_REPORTED = 20;

    public function __construct(ProductIndexRepository $repository)
    {
        $this->repository = $repository;
    }

    public function rebuild(): ProductIndexResult
    {
        if (! class_exists('WooCommerce')) {
            throw new \RuntimeException(
                __('WooCommerce is required to rebuild the product index.', 'mx-pos-pro')
            );
        }

        $result = new ProductIndexResult();
        $start = microtime(true);
        $generation = time();

        wp_suspend_cache_invalidation(true);
        wp_defer_term_counting(true);

        $page = 1;

        do {
            $query = wc_get_products([
                'status'   => 'publish',
                'limit'    => self::BATCH_SIZE,
                'page'     => $page,
                'orderby'  => 'ID',
                'order'    => 'ASC',
                'return'   => 'ids',
                'paginate' => true,
            ]);

            $product_ids = is_object($query) && isset($query->products)
                ? (array) $query->products
                : (array) $query;

            if (empty($product_ids)) {
                break;
            }

            foreach ($product_ids as $product_id) {
                $product = wc_get_product((int) $product_id);

                if (! $product instanceof \WC_Product) {
                    continue;
                }

                $result->total_seen++;

                try {
                    $this->index_product_rows($product, $result, $generation);
                } catch (\Throwable $e) {
                    $result->skipped_count++;

                    if (count($result->errors) < self::MAX_ERRORS_REPORTED) {
                        $result->errors[] = sprintf(
                            'Product %d: %s',
                            $product->get_id(),
                            $e->getMessage()
                        );
                    }
                }
            }

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            $page++;
        } while (count($product_ids) === self::BATCH_SIZE);

        $this->repository->delete_stale_generation($generation);

        wp_suspend_cache_invalidation(false);
        wp_defer_term_counting(false);

        if (count($result->errors) > self::MAX_ERRORS_REPORTED) {
            $overflow = count($result->errors) - self::MAX_ERRORS_REPORTED;
            $result->errors = array_slice($result->errors, 0, self::MAX_ERRORS_REPORTED);
            $result->errors[] = sprintf(
                '... and %d more error(s)',
                $overflow
            );
        }

        $result->duration = round(microtime(true) - $start, 2);

        return $result;
    }

    public function index_product_by_id(int $product_id): void
    {
        if ($product_id <= 0) {
            return;
        }

        $product = wc_get_product($product_id);

        if (! $product instanceof \WC_Product) {
            $this->repository->delete_product($product_id);

            return;
        }

        if ($this->is_child_product($product)) {
            $parent_id = (int) $product->get_parent_id();

            if ($parent_id > 0) {
                $this->index_product_by_id($parent_id);
                return;
            }

            $this->repository->delete_variation($product_id);
            return;
        }

        $this->repository->delete_product($product_id);

        if ($product->get_status() !== 'publish') {
            return;
        }

        $result = new ProductIndexResult();
        $this->index_product_rows($product, $result, time());
    }

    private function index_product_rows(\WC_Product $product, ProductIndexResult $result, int $generation): void
    {
        $children = $this->get_child_ids($product);
        $child_rows = [];

        foreach ($children as $child_id) {
            $child = wc_get_product($child_id);

            if (! $child instanceof \WC_Product) {
                continue;
            }

            $result->total_seen++;

            try {
                $child_rows[] = $this->build_row($child, $product, $generation);
            } catch (\Throwable $e) {
                $result->skipped_count++;

                if (count($result->errors) < self::MAX_ERRORS_REPORTED) {
                    $result->errors[] = sprintf(
                        'Child product %d (parent %d): %s',
                        $child_id,
                        $product->get_id(),
                        $e->getMessage()
                    );
                }
            }
        }

        if (! $product->is_purchasable() && count($child_rows) === 0) {
            $result->skipped_count++;
            return;
        }

        $parent_row = $this->build_row($product, null, $generation, $child_rows);
        $this->repository->upsert($parent_row);
        $result->total_indexed++;

        if (count($child_rows) > 0) {
            $result->variable_count++;

            foreach ($child_rows as $row) {
                $this->repository->upsert($row);
                $result->total_indexed++;
                $result->variation_count++;
            }

            return;
        }

        $result->simple_count++;
    }

    /**
     * @param array<int, array<string, mixed>> $child_rows
     */
    public function build_row(
        \WC_Product $product,
        ?\WC_Product $parent = null,
        ?int $generation = null,
        array $child_rows = []
    ): array {
        $object_id = $product->get_id();
        $product_id = $parent instanceof \WC_Product
            ? $parent->get_id()
            : $product->get_id();
        $variation_id = $parent instanceof \WC_Product ? $product->get_id() : null;
        $parent_id = $parent instanceof \WC_Product ? $parent->get_id() : null;
        $catalog_group_id = $parent_id ?: $product_id;
        $type = $parent instanceof \WC_Product
            ? 'variation'
            : (count($child_rows) > 0 ? 'variable' : $product->get_type());
        $variation_label = $parent instanceof \WC_Product
            ? $this->variation_label($product)
            : '';
        $image = $this->image_data($product, $parent);
        $prices = $this->price_data($product, $child_rows);
        $name = (string) $product->get_name();
        $parent_name = $parent instanceof \WC_Product ? (string) $parent->get_name() : '';

        $searchable_text = implode(' ', array_filter([
            $name,
            $parent_name,
            (string) $product->get_sku(),
            $variation_label,
            $type,
            (string) $product->get_status(),
        ]));

        return [
            'object_id'        => $object_id,
            'product_id'       => $product_id,
            'variation_id'     => $variation_id,
            'parent_id'        => $parent_id,
            'catalog_group_id' => $catalog_group_id,
            'sku'              => (string) $product->get_sku(),
            'sku_normalized'   => ProductIndexRepository::normalize_search_value((string) $product->get_sku()),
            'name'             => $name,
            'name_normalized'  => ProductIndexRepository::normalize_search_value(trim($parent_name . ' ' . $name . ' ' . $variation_label)),
            'parent_name'      => $parent_name,
            'variation_label'  => $variation_label,
            'type'             => $type,
            'status'           => (string) $product->get_status(),
            'is_purchasable'   => $product->is_purchasable() ? 1 : 0,
            'stock_quantity'   => $product->get_stock_quantity(),
            'stock_status'     => (string) $product->get_stock_status(),
            'regular_price'    => $product->get_regular_price('edit'),
            'sale_price'       => $product->get_sale_price('edit'),
            'display_price'    => $prices['display'],
            'min_price'        => $prices['min'],
            'max_price'        => $prices['max'],
            'image_url'        => $image['image_url'],
            'image_alt'        => $image['image_alt'],
            'image_version'    => $image['image_version'],
            'searchable_text'  => $searchable_text,
            'index_generation' => $generation ?? time(),
            'indexed_at'       => current_time('mysql'),
        ];
    }

    /**
     * @return int[]
     */
    private function get_child_ids(\WC_Product $product): array
    {
        if ($this->is_child_product($product) || ! method_exists($product, 'get_children')) {
            return [];
        }

        $children = $product->get_children();

        if (! is_array($children)) {
            return [];
        }

        return array_values(array_filter(array_map('absint', $children)));
    }

    private function is_child_product(\WC_Product $product): bool
    {
        return method_exists($product, 'get_parent_id') && (int) $product->get_parent_id() > 0;
    }

    private function variation_label(\WC_Product $product): string
    {
        if (! method_exists($product, 'get_attributes')) {
            return '';
        }

        $attributes = $product->get_attributes();

        if (! is_array($attributes) || count($attributes) === 0) {
            return '';
        }

        $pairs = [];

        foreach ($attributes as $key => $value) {
            if ($value === '') {
                continue;
            }

            $label = wc_attribute_label((string) $key, $product);
            $pairs[] = $label . ': ' . $value;
        }

        return implode(', ', $pairs);
    }

    /**
     * @param array<int, array<string, mixed>> $child_rows
     * @return array{display:mixed,min:mixed,max:mixed}
     */
    private function price_data(\WC_Product $product, array $child_rows): array
    {
        if (count($child_rows) > 0) {
            $prices = [];

            foreach ($child_rows as $row) {
                $price = $row['display_price'] ?? null;

                if ($price !== null && $price !== '' && (float) $price > 0) {
                    $prices[] = (float) $price;
                }
            }

            if (count($prices) > 0) {
                return [
                    'display' => min($prices),
                    'min'     => min($prices),
                    'max'     => max($prices),
                ];
            }
        }

        $sale = $product->get_sale_price('edit');
        $regular = $product->get_regular_price('edit');
        $display = $sale !== '' && $sale !== null ? $sale : $regular;

        return [
            'display' => $display,
            'min'     => null,
            'max'     => null,
        ];
    }

    /**
     * @return array{image_url:?string,image_alt:string,image_version:string}
     */
    private function image_data(\WC_Product $product, ?\WC_Product $parent = null): array
    {
        $image_id = (int) $product->get_image_id();

        if ($image_id <= 0 && $parent instanceof \WC_Product) {
            $image_id = (int) $parent->get_image_id();
        }

        if ($image_id <= 0) {
            return [
                'image_url'     => null,
                'image_alt'     => '',
                'image_version' => '',
            ];
        }

        $url = wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail');

        if (! $url) {
            return [
                'image_url'     => null,
                'image_alt'     => '',
                'image_version' => '',
            ];
        }

        $fallback_name = $parent instanceof \WC_Product
            ? (string) $parent->get_name()
            : (string) $product->get_name();
        $alt = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));
        $version = $this->image_version($image_id);

        return [
            'image_url'     => esc_url_raw($version !== '' ? add_query_arg('mx_pos_img_ver', $version, $url) : $url),
            'image_alt'     => $alt !== '' ? $alt : $fallback_name,
            'image_version' => $version,
        ];
    }

    private function image_version(int $image_id): string
    {
        $versions = [];
        $modified = (int) get_post_modified_time('U', true, $image_id);

        if ($modified > 0) {
            $versions[] = $modified;
        }

        $file = get_attached_file($image_id);

        if (is_string($file) && $file !== '' && file_exists($file)) {
            $versions[] = (int) filemtime($file);
        }

        if (count($versions) === 0) {
            return '';
        }

        return (string) max($versions);
    }
}
