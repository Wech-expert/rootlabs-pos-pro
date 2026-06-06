<?php

namespace MXPOSPro\Products;

defined('ABSPATH') || exit;

class ProductIndexHooks
{
    private ProductIndexRepository $repository;

    public function __construct(ProductIndexRepository $repository)
    {
        $this->repository = $repository;
    }

    public function register(): void
    {
        add_action('before_delete_post', [$this, 'delete_post']);
        add_action('wp_trash_post', [$this, 'delete_post']);
        add_action('untrashed_post', [$this, 'reindex_post']);
        add_action('woocommerce_update_product', [$this, 'reindex_post']);
        add_action('woocommerce_update_product_variation', [$this, 'reindex_variation']);
        add_action('woocommerce_product_set_stock', [$this, 'reindex_product_object']);
        add_action('woocommerce_variation_set_stock', [$this, 'reindex_product_object']);
        add_action('woocommerce_product_set_stock_status', [$this, 'reindex_product_object'], 10, 3);
        add_action('woocommerce_variation_set_stock_status', [$this, 'reindex_product_object'], 10, 3);
        add_action('added_post_meta', [$this, 'maybe_reindex_image_meta'], 10, 4);
        add_action('updated_post_meta', [$this, 'maybe_reindex_image_meta'], 10, 4);
        add_action('deleted_post_meta', [$this, 'maybe_reindex_image_meta'], 10, 4);
    }

    public function delete_post(int $post_id): void
    {
        $post_type = get_post_type($post_id);

        if ($post_type === 'product') {
            $this->repository->delete_product($post_id);
            return;
        }

        if ($post_type === 'product_variation') {
            $this->repository->delete_variation($post_id);
        }
    }

    public function reindex_post(int $product_id): void
    {
        if (get_post_type($product_id) !== 'product') {
            return;
        }

        $this->indexer()->index_product_by_id($product_id);
    }

    public function reindex_variation(int $variation_id): void
    {
        if (get_post_type($variation_id) !== 'product_variation') {
            return;
        }

        $variation = wc_get_product($variation_id);

        if (! $variation instanceof \WC_Product) {
            $this->repository->delete_variation($variation_id);
            return;
        }

        $parent_id = (int) $variation->get_parent_id();

        if ($parent_id > 0) {
            $this->indexer()->index_product_by_id($parent_id);
            return;
        }

        $this->repository->delete_variation($variation_id);
    }

    public function reindex_product_object(mixed $product_or_id): void
    {
        $product = $product_or_id instanceof \WC_Product
            ? $product_or_id
            : wc_get_product((int) $product_or_id);

        if (! $product instanceof \WC_Product) {
            return;
        }

        $product_id = $product->get_id();

        if ($product_id <= 0) {
            return;
        }

        if (method_exists($product, 'get_parent_id') && (int) $product->get_parent_id() > 0) {
            $this->reindex_variation($product_id);
            return;
        }

        $this->reindex_post($product_id);
    }

    public function maybe_reindex_image_meta($meta_id, int $object_id, string $meta_key, $meta_value = null): void
    {
        if ($meta_key !== '_thumbnail_id') {
            return;
        }

        $post_type = get_post_type($object_id);

        if ($post_type === 'product') {
            $this->clear_product_cache($object_id);
            $this->reindex_post($object_id);
            return;
        }

        if ($post_type === 'product_variation') {
            $this->clear_product_cache($object_id);
            $this->reindex_variation($object_id);
        }
    }

    private function clear_product_cache(int $product_id): void
    {
        clean_post_cache($product_id);

        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
    }

    private function indexer(): ProductIndexer
    {
        return new ProductIndexer($this->repository);
    }
}
