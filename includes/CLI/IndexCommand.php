<?php

namespace MXPOSPro\CLI;

defined('ABSPATH') || exit;

use WP_CLI;
use MXPOSPro\Products\ProductIndexRepository;
use MXPOSPro\Products\ProductIndexer;
use MXPOSPro\Products\ProductSearch;

class IndexCommand
{
    private ProductIndexRepository $repository;

    public function __construct()
    {
        $this->repository = new ProductIndexRepository();
    }

    /**
     * Rebuild the product search index from WooCommerce data.
     *
     * ## EXAMPLES
     *
     *     wp mx-pos index rebuild
     *
     * @when after_wp_load
     */
    public function rebuild(array $args, array $assoc_args): void
    {
        if (! class_exists('WooCommerce')) {
            WP_CLI::error(
                __('WooCommerce must be active to rebuild the product index.', 'mx-pos-pro')
            );
        }

        WP_CLI::line(__('Rebuilding product index...', 'mx-pos-pro'));

        $indexer  = new ProductIndexer($this->repository);

        try {
            $result = $indexer->rebuild();
        } catch (\RuntimeException $e) {
            WP_CLI::error($e->getMessage());
        }

        $this->report_result($result);
    }

    /**
     * Show current product index statistics.
     *
     * ## EXAMPLES
     *
     *     wp mx-pos index stats
     *
     * @when after_wp_load
     */
    public function stats(array $args, array $assoc_args): void
    {
        $total      = $this->repository->count();
        $last_index = $this->repository->get_last_indexed_at();
        $type_counts = $this->repository->get_type_counts();

        WP_CLI::line('');
        WP_CLI::line('Product Index Stats');
        WP_CLI::line('====================');
        WP_CLI::line("Total indexed:  {$total}");
        WP_CLI::line("Last indexed:   " . ($last_index ?: 'Never'));

        if (empty($type_counts)) {
            WP_CLI::line('Type breakdown: (empty)');

            return;
        }

        WP_CLI::line('');
        WP_CLI::line('Type breakdown:');

        $types = ['simple', 'variable', 'variation'];

        foreach ($types as $type) {
            $count = $type_counts[$type] ?? 0;
            WP_CLI::line(sprintf('  %-12s %d', ucfirst($type) . ':', $count));
        }

        foreach ($type_counts as $type => $count) {
            if (! in_array($type, $types, true)) {
                WP_CLI::line(sprintf('  %-12s %d', ucfirst($type) . ':', $count));
            }
        }

        WP_CLI::line('');
    }

    /**
     * Benchmark product index search queries.
     *
     * ## OPTIONS
     *
     * [--queries=<queries>]
     * : Pipe-separated query list. Example: --queries="abc|cafe|750"
     *
     * [--limit=<n>]
     * : Result limit per query.
     * ---
     * default: 24
     * ---
     *
     * [--format=<format>]
     * : Output format: table or json.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mx-pos index benchmark --queries="abc|cafe|750"
     *
     * @when after_wp_load
     */
    public function benchmark(array $args, array $assoc_args): void
    {
        $queries_arg = isset($assoc_args['queries']) ? (string) $assoc_args['queries'] : '';
        $queries = array_values(array_filter(array_map('trim', explode('|', $queries_arg))));

        if (count($queries) === 0) {
            $queries = ['abc', 'cafe', '750'];
        }

        $limit = max(1, min(50, absint($assoc_args['limit'] ?? 24)));
        $format = isset($assoc_args['format']) && $assoc_args['format'] === 'json'
            ? 'json'
            : 'table';
        $rows = [];

        foreach ($queries as $query) {
            $start = microtime(true);
            $result_rows = $this->repository->search_catalog_rows($query, $limit);
            $duration_ms = round((microtime(true) - $start) * 1000, 2);
            $group_ids = [];

            foreach ($result_rows as $row) {
                $group_ids[(int) ($row['catalog_group_id'] ?? $row['product_id'] ?? 0)] = true;
            }

            $rows[] = [
                'Query'       => $query,
                'Rows'        => count($result_rows),
                'Groups'      => count($group_ids),
                'Duration ms' => $duration_ms,
            ];
        }

        if ($format === 'json') {
            WP_CLI::line(wp_json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }

        WP_CLI\Utils\format_items('table', $rows, ['Query', 'Rows', 'Groups', 'Duration ms']);
    }

    private function report_result(\MXPOSPro\Products\ProductIndexResult $result): void
    {
        WP_CLI::line('');
        WP_CLI::line('Index complete.');

        WP_CLI\Utils\format_items('table', [
            [
                'Metric' => 'Total seen',
                'Value'  => $result->total_seen,
            ],
            [
                'Metric' => 'Total indexed',
                'Value'  => $result->total_indexed,
            ],
            [
                'Metric' => 'Simple',
                'Value'  => $result->simple_count,
            ],
            [
                'Metric' => 'Variable',
                'Value'  => $result->variable_count,
            ],
            [
                'Metric' => 'Variations',
                'Value'  => $result->variation_count,
            ],
            [
                'Metric' => 'Skipped',
                'Value'  => $result->skipped_count,
            ],
        ], ['Metric', 'Value']);

        if (! empty($result->errors)) {
            WP_CLI::line('');
            WP_CLI::line('Errors:');

            foreach ($result->errors as $error) {
                WP_CLI::warning($error);
            }
        }

        if (isset($result->duration)) {
            WP_CLI::line('');
            WP_CLI::line(sprintf('Duration: %.1fs', $result->duration));
        }

        WP_CLI::success(__('Product index rebuilt successfully.', 'mx-pos-pro'));
    }
}
