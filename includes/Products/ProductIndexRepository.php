<?php

namespace MXPOSPro\Products;

defined('ABSPATH') || exit;

class ProductIndexRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'mx_pos_product_index';
    }

    public function table_name(): string
    {
        return $this->table;
    }

    public function truncate(): void
    {
        global $wpdb;

        $wpdb->query("TRUNCATE TABLE {$this->table}");
    }

    public function delete_product(int $product_id): void
    {
        global $wpdb;

        if ($product_id <= 0) {
            return;
        }

        $wpdb->delete(
            $this->table,
            ['product_id' => $product_id],
            ['%d']
        );
    }

    public function delete_variation(int $variation_id): void
    {
        global $wpdb;

        if ($variation_id <= 0) {
            return;
        }

        $wpdb->delete(
            $this->table,
            ['variation_id' => $variation_id],
            ['%d']
        );
    }

    public function delete_stale_generation(int $generation): void
    {
        global $wpdb;

        if ($generation <= 0) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table}
                 WHERE index_generation != %d",
                $generation
            )
        );
    }

    /**
     * Idempotent upsert keyed by the WooCommerce object id.
     *
     * The legacy unique key contains nullable variation_id, so we avoid relying
     * on ON DUPLICATE KEY for parent product rows.
     */
    public function upsert(array $row): void
    {
        global $wpdb;

        $prepared = $this->prepare_row($row);
        $object_id = (int) ($prepared['object_id'] ?? 0);

        if ($object_id <= 0) {
            return;
        }

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table}
                 WHERE object_id = %d
                 LIMIT 1",
                $object_id
            )
        );

        if (! $existing) {
            if ($prepared['variation_id'] === null) {
                $existing = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$this->table}
                         WHERE product_id = %d
                           AND variation_id IS NULL
                         LIMIT 1",
                        $prepared['product_id']
                    )
                );
            } else {
                $existing = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$this->table}
                         WHERE product_id = %d
                           AND variation_id = %d
                         LIMIT 1",
                        $prepared['product_id'],
                        $prepared['variation_id']
                    )
                );
            }
        }

        if ($existing) {
            $wpdb->update(
                $this->table,
                $prepared,
                ['id' => (int) $existing],
                $this->formats(),
                ['%d']
            );

            return;
        }

        $wpdb->insert(
            $this->table,
            $prepared,
            $this->formats()
        );
    }

    public function search(string $query, int $limit = 20): array
    {
        return $this->search_rows($query, $limit);
    }

    /**
     * Return rows for the initial POS catalog, grouped later by product_id.
     */
    public function list_catalog_rows(int $limit = 24): array
    {
        global $wpdb;

        $limit = max(1, min($limit, 50));

        $group_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT catalog_group_id
                 FROM {$this->table}
                 WHERE status = %s
                   AND stock_status != %s
                 GROUP BY catalog_group_id
                 ORDER BY MAX(indexed_at) DESC, catalog_group_id DESC
                 LIMIT %d",
                'publish',
                'outofstock',
                $limit
            )
        );

        return $this->get_rows_for_group_ids($group_ids);
    }

    /**
     * Return rows for products matching a query, grouped later by product_id.
     */
    public function search_catalog_rows(string $query, int $limit = 24): array
    {
        global $wpdb;

        $query = trim($query);

        if ($query === '') {
            return $this->list_catalog_rows($limit);
        }

        $limit = max(1, min($limit, 50));
        $normalized = self::normalize_search_value($query);
        $prefix = $wpdb->esc_like($normalized) . '%';
        $contains = '%' . $wpdb->esc_like($normalized) . '%';

        $group_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT catalog_group_id
                 FROM {$this->table}
                 WHERE status = %s
                   AND stock_status != %s
                   AND (
                     sku_normalized = %s
                     OR sku_normalized LIKE %s
                     OR name_normalized LIKE %s
                     OR searchable_text LIKE %s
                   )
                 GROUP BY catalog_group_id
                 ORDER BY
                     MIN(CASE
                         WHEN sku_normalized = %s THEN 0
                         WHEN sku_normalized LIKE %s THEN 1
                         WHEN name_normalized LIKE %s THEN 2
                         ELSE 3
                     END),
                     MAX(indexed_at) DESC,
                     catalog_group_id DESC
                 LIMIT %d",
                'publish',
                'outofstock',
                $normalized,
                $prefix,
                $prefix,
                $contains,
                $normalized,
                $prefix,
                $prefix,
                $limit
            )
        );

        return $this->get_rows_for_group_ids($group_ids);
    }

    public function search_rows(string $query, int $limit = 20): array
    {
        global $wpdb;

        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $limit = max(1, min($limit, 50));
        $normalized = self::normalize_search_value($query);
        $prefix = $wpdb->esc_like($normalized) . '%';
        $contains = '%' . $wpdb->esc_like($normalized) . '%';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$this->table}
                 WHERE status = %s
                   AND stock_status != %s
                   AND is_purchasable = 1
                   AND (
                     sku_normalized = %s
                     OR sku_normalized LIKE %s
                     OR name_normalized LIKE %s
                     OR searchable_text LIKE %s
                   )
                 ORDER BY
                     CASE
                         WHEN sku_normalized = %s THEN 0
                         WHEN sku_normalized LIKE %s THEN 1
                         WHEN name_normalized LIKE %s THEN 2
                         ELSE 3
                     END,
                     indexed_at DESC,
                     object_id DESC
                 LIMIT %d",
                'publish',
                'outofstock',
                $normalized,
                $prefix,
                $prefix,
                $contains,
                $normalized,
                $prefix,
                $prefix,
                $limit
            ),
            ARRAY_A
        );
    }

    private function get_rows_for_group_ids(array $group_ids): array
    {
        global $wpdb;

        $group_ids = array_values(array_filter(array_map('absint', $group_ids)));

        if (count($group_ids) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($group_ids), '%d'));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$this->table}
                 WHERE catalog_group_id IN ({$placeholders})
                   AND status = %s
                   AND stock_status != %s
                 ORDER BY FIELD(catalog_group_id, {$placeholders}),
                   CASE WHEN variation_id IS NULL THEN 0 ELSE 1 END,
                   name ASC",
                ...array_merge($group_ids, ['publish', 'outofstock'], $group_ids)
            ),
            ARRAY_A
        );
    }

    public function count(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }

    public function get_last_indexed_at(): ?string
    {
        global $wpdb;

        $result = $wpdb->get_var("SELECT MAX(indexed_at) FROM {$this->table}");

        return $result ?: null;
    }

    /**
     * @return array<string, int> type => count
     */
    public function get_type_counts(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT type, COUNT(*) AS cnt FROM {$this->table} GROUP BY type",
            ARRAY_A
        );

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['type']] = (int) $row['cnt'];
        }

        return $counts;
    }

    public static function normalize_search_value(string $value): string
    {
        $value = trim(wp_strip_all_tags($value));
        $value = remove_accents($value);
        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value)
            : strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    /**
     * Normalise and prepare a row for insert/update.
     */
    private function prepare_row(array $row): array
    {
        $product_id = (int) ($row['product_id'] ?? 0);
        $variation_id = isset($row['variation_id']) && $row['variation_id'] !== null
            ? (int) $row['variation_id']
            : null;
        $object_id = (int) ($row['object_id'] ?? ($variation_id ?: $product_id));
        $parent_id = isset($row['parent_id']) && $row['parent_id'] !== null
            ? (int) $row['parent_id']
            : null;
        $catalog_group_id = (int) ($row['catalog_group_id'] ?? ($parent_id ?: $product_id));
        $sku = (string) ($row['sku'] ?? '');
        $name = (string) ($row['name'] ?? '');
        $searchable_text = (string) ($row['searchable_text'] ?? implode(' ', array_filter([$sku, $name])));

        return [
            'object_id'        => $object_id,
            'product_id'       => $product_id,
            'variation_id'     => $variation_id,
            'parent_id'        => $parent_id,
            'catalog_group_id' => $catalog_group_id > 0 ? $catalog_group_id : $product_id,
            'sku'              => $sku,
            'sku_normalized'   => self::normalize_search_value((string) ($row['sku_normalized'] ?? $sku)),
            'name'             => $name,
            'name_normalized'  => self::normalize_search_value((string) ($row['name_normalized'] ?? $name)),
            'parent_name'      => (string) ($row['parent_name'] ?? ''),
            'variation_label'  => (string) ($row['variation_label'] ?? ''),
            'type'             => (string) ($row['type'] ?? 'simple'),
            'status'           => (string) ($row['status'] ?? 'publish'),
            'is_purchasable'   => ! empty($row['is_purchasable']) ? 1 : 0,
            'stock_quantity'   => isset($row['stock_quantity']) && $row['stock_quantity'] !== ''
                ? (int) $row['stock_quantity']
                : null,
            'stock_status'     => (string) ($row['stock_status'] ?? 'instock'),
            'regular_price'    => $this->price_to_float($row['regular_price'] ?? null),
            'sale_price'       => $this->price_to_float($row['sale_price'] ?? null),
            'display_price'    => $this->price_to_float($row['display_price'] ?? null),
            'min_price'        => $this->price_to_float($row['min_price'] ?? null),
            'max_price'        => $this->price_to_float($row['max_price'] ?? null),
            'image_url'        => isset($row['image_url']) && $row['image_url'] !== null
                ? (string) $row['image_url']
                : null,
            'image_alt'        => (string) ($row['image_alt'] ?? ''),
            'image_version'    => (string) ($row['image_version'] ?? ''),
            'searchable_text'  => self::normalize_search_value($searchable_text),
            'index_generation' => (int) ($row['index_generation'] ?? 0),
            'indexed_at'       => $row['indexed_at'] ?? current_time('mysql'),
        ];
    }

    /**
     * Formats for $wpdb->insert / $wpdb->update.
     */
    private function formats(): array
    {
        return [
            '%d',   // object_id
            '%d',   // product_id
            '%d',   // variation_id
            '%d',   // parent_id
            '%d',   // catalog_group_id
            '%s',   // sku
            '%s',   // sku_normalized
            '%s',   // name
            '%s',   // name_normalized
            '%s',   // parent_name
            '%s',   // variation_label
            '%s',   // type
            '%s',   // status
            '%d',   // is_purchasable
            '%d',   // stock_quantity
            '%s',   // stock_status
            '%f',   // regular_price
            '%f',   // sale_price
            '%f',   // display_price
            '%f',   // min_price
            '%f',   // max_price
            '%s',   // image_url
            '%s',   // image_alt
            '%s',   // image_version
            '%s',   // searchable_text
            '%d',   // index_generation
            '%s',   // indexed_at
        ];
    }

    private function price_to_float(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
