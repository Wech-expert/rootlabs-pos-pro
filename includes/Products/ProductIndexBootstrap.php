<?php

declare(strict_types=1);

namespace MXPOSPro\Products;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProductIndexBootstrap
{
    private const LOCK_TRANSIENT = 'mx_pos_product_index_rebuild_lock';
    private const LAST_REBUILD_OPTION = 'mx_pos_product_index_last_auto_rebuild';

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'maybe_rebuild_if_needed'], 20);
        add_filter('rest_pre_dispatch', [self::class, 'maybe_rebuild_for_product_routes'], 5, 3);
    }

    /**
     * Rebuilds the product index before POS product REST endpoints are dispatched.
     *
     * @param mixed $result Existing REST pre-dispatch result.
     * @param mixed $server REST server instance.
     * @param mixed $request REST request instance.
     *
     * @return mixed
     */
    public static function maybe_rebuild_for_product_routes($result, $server, $request)
    {
        unset($server);

        if (! is_object($request) || ! method_exists($request, 'get_route')) {
            return $result;
        }

        $route = (string) $request->get_route();

        if (
            '/mx-pos/v1/products/search' === $route
            || '/mx-pos/v1/products/catalog' === $route
        ) {
            self::maybe_rebuild_if_needed();
        }

        return $result;
    }

    public static function maybe_rebuild_if_needed(): void
    {
        if (! self::can_rebuild()) {
            return;
        }

        if (get_transient(self::LOCK_TRANSIENT)) {
            return;
        }

        $repository = new ProductIndexRepository();

        if ($repository->count() > 0) {
            return;
        }

        if (! self::has_published_products()) {
            return;
        }

        set_transient(self::LOCK_TRANSIENT, '1', 5 * MINUTE_IN_SECONDS);

        try {
            $indexer = new ProductIndexer($repository);
            $indexer->rebuild();

            update_option(self::LAST_REBUILD_OPTION, current_time('mysql'), false);
        } catch (\Throwable $exception) {
            unset($exception);
        } finally {
            delete_transient(self::LOCK_TRANSIENT);
        }
    }

    private static function can_rebuild(): bool
    {
        if (! function_exists('wc_get_products')) {
            return false;
        }

        if (! class_exists(ProductIndexRepository::class) || ! class_exists(ProductIndexer::class)) {
            return false;
        }

        return true;
    }

    private static function has_published_products(): bool
    {
        if (! function_exists('wc_get_products')) {
            return false;
        }

        $products = wc_get_products([
            'status' => 'publish',
            'limit'  => 1,
            'return' => 'ids',
        ]);

        return ! empty($products);
    }
}
