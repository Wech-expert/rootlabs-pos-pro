<?php


/**
 * RootLabs POS uses custom operational tables for POS data.
 * These database calls are intentional and isolated in repository/service layers.
 *
 * rootlabs-pos-pro-w2a-db-intentional
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 */

namespace MXPOSPro\Sales;

defined('ABSPATH') || exit;

class SaleHistoryRepository
{
    private string $salesTable;
    private string $logsTable;
    private string $refundsTable;

    private const ALLOWED_STATUSES = ['pending', 'completed', 'cancelled', 'refunded'];
    private const ALLOWED_DISPLAY_STATUSES = ['pending', 'completed', 'partially_refunded', 'cancelled', 'refunded'];

    public function __construct()
    {
        global $wpdb;

        $this->salesTable   = $wpdb->prefix . 'mx_pos_sales';
        $this->logsTable    = $wpdb->prefix . 'mx_pos_sale_logs';
        $this->refundsTable = $wpdb->prefix . 'mx_pos_refunds';
    }

    public function query_paginated(int $userId, array $filters, int $page, int $perPage): array
    {
        global $wpdb;

        $perPage  = max(1, min(100, $perPage));
        $offset   = ($page - 1) * $perPage;
        $where    = '1=1';
        $whereArgs = [];

        if ($this->should_restrict_to_own_sales($userId)) {
            $where       .= ' AND s.cashier_id = %d';
            $whereArgs[] = $userId;
        }

        if (! empty($filters['date_from'])) {
            $where       .= ' AND s.created_at >= %s';
            $whereArgs[] = $filters['date_from'] . ' 00:00:00';
        }

        if (! empty($filters['date_to'])) {
            $where       .= ' AND s.created_at <= %s';
            $whereArgs[] = $filters['date_to'] . ' 23:59:59';
        }

        if (! empty($filters['cashier_id']) && is_numeric($filters['cashier_id'])) {
            $where       .= ' AND s.cashier_id = %d';
            $whereArgs[] = (int) $filters['cashier_id'];
        }

        if (! empty($filters['status']) && in_array($filters['status'], self::ALLOWED_DISPLAY_STATUSES, true)) {
            if ($filters['status'] === 'partially_refunded') {
                $where .= " AND s.status = 'completed' AND s.refunded_total > 0 AND s.refunded_total < s.total";
            } else {
                $where       .= ' AND s.status = %s';
                $whereArgs[] = $filters['status'];
            }
        }

        if (! empty($filters['search']) && is_numeric($filters['search'])) {
            $searchVal   = (int) $filters['search'];
            $where       .= ' AND (s.id = %d OR s.wc_order_id = %d)';
            $whereArgs[] = $searchVal;
            $whereArgs[] = $searchVal;
        }

        if (! empty($filters['session_id']) && is_numeric($filters['session_id'])) {
            $where       .= ' AND s.session_id = %d';
            $whereArgs[] = (int) $filters['session_id'];
        }

        $orderBy = 's.created_at DESC, s.id DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal table identifier and fixed WHERE fragments; dynamic values are prepared below.
        $sql = "SELECT s.* FROM {$this->salesTable} s WHERE {$where} ORDER BY {$orderBy} LIMIT %d OFFSET %d";
        $whereArgs[] = $perPage;
        $whereArgs[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query template uses internal table identifier; placeholders are prepared here.
        $prepared = $wpdb->prepare($sql, $whereArgs);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared immediately above.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        if (! is_array($rows)) {
            return ['items' => [], 'total' => 0];
        }

        $total = $this->count_total($userId, $filters);

        $items = array_map([$this, 'map_listing_row'], $rows);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function count_total(int $userId, array $filters): int
    {
        global $wpdb;

        $where    = '1=1';
        $whereArgs = [];

        if ($this->should_restrict_to_own_sales($userId)) {
            $where       .= ' AND cashier_id = %d';
            $whereArgs[] = $userId;
        }

        if (! empty($filters['date_from'])) {
            $where       .= ' AND created_at >= %s';
            $whereArgs[] = $filters['date_from'] . ' 00:00:00';
        }

        if (! empty($filters['date_to'])) {
            $where       .= ' AND created_at <= %s';
            $whereArgs[] = $filters['date_to'] . ' 23:59:59';
        }

        if (! empty($filters['cashier_id']) && is_numeric($filters['cashier_id'])) {
            $where       .= ' AND cashier_id = %d';
            $whereArgs[] = (int) $filters['cashier_id'];
        }

        if (! empty($filters['status']) && in_array($filters['status'], self::ALLOWED_DISPLAY_STATUSES, true)) {
            if ($filters['status'] === 'partially_refunded') {
                $where .= " AND status = 'completed' AND refunded_total > 0 AND refunded_total < total";
            } else {
                $where       .= ' AND status = %s';
                $whereArgs[] = $filters['status'];
            }
        }

        if (! empty($filters['search']) && is_numeric($filters['search'])) {
            $searchVal   = (int) $filters['search'];
            $where       .= ' AND (id = %d OR wc_order_id = %d)';
            $whereArgs[] = $searchVal;
            $whereArgs[] = $searchVal;
        }

        if (! empty($filters['session_id']) && is_numeric($filters['session_id'])) {
            $where       .= ' AND session_id = %d';
            $whereArgs[] = (int) $filters['session_id'];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal table identifier and fixed WHERE fragments; dynamic values are prepared below when present.
        $sql = "SELECT COUNT(*) FROM {$this->salesTable} WHERE {$where}";
        $prepared = $whereArgs !== []
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query template uses internal table identifier; placeholders are prepared here.
            ? $wpdb->prepare($sql, $whereArgs)
            : $sql;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query has no user input or was prepared above.
        return (int) $wpdb->get_var($prepared);
    }

    public function get_detail(int $saleId, int $userId): ?array
    {
        global $wpdb;

        if ($saleId <= 0) {
            return null;
        }

        $saleRepo = new SaleRepository();
        $sale     = $saleRepo->get_by_id($saleId);

        if ($sale === null) {
            return null;
        }

        if ($this->should_restrict_to_own_sales($userId)) {
            if ((int) $sale['cashier_id'] !== $userId) {
                return null;
            }
        }

        $order = wc_get_order((int) $sale['wc_order_id']);

        $payment = $this->decode_payment($sale['payment_summary'] ?? null);

        $refundRepo = new RefundRepository();
        $refunds    = $refundRepo->get_by_sale_id($saleId);

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_type, message, created_by, created_at
                 FROM {$this->logsTable}
                 WHERE sale_id = %d
                 ORDER BY created_at ASC, id ASC",
                $saleId
            ),
            ARRAY_A
        );

        if (! is_array($logs)) {
            $logs = [];
        }

        $items = [];
        if ($order instanceof \WC_Order) {
            foreach ($order->get_items('line_item') as $item) {
                if (! $item instanceof \WC_Order_Item_Product) {
                    continue;
                }

                $items[] = [
                    'name'       => $item->get_name(),
                    'sku'        => '',
                    'quantity'   => (int) $item->get_quantity(),
                    'unit_price' => $this->formatDecimal((float) $item->get_total() / max(1, (int) $item->get_quantity())),
                    'line_total' => $this->formatDecimal((float) $item->get_total()),
                ];
            }
        }

        $mappedRefunds = array_map(function (array $r) {
            return [
                'id'            => (int) $r['id'],
                'refund_type'   => $r['refund_type'],
                'refund_amount' => $this->formatDecimal((float) $r['refund_amount']),
                'refund_method' => $r['refund_method'],
                'reason'        => $r['reason'],
                'created_at'    => $r['created_at'],
            ];
        }, $refunds);

        $mappedLogs = array_map(function (array $l) {
            return [
                'event_type' => $l['event_type'],
                'message'    => $l['message'],
                'created_by' => isset($l['created_by']) ? (int) $l['created_by'] : null,
                'created_at' => $l['created_at'],
            ];
        }, $logs);

        $total          = (float) $sale['total'];
        $refundedTotal  = (float) ($sale['refunded_total'] ?? '0.0000');
        $netTotal       = $total - $refundedTotal;

        if ($netTotal < 0) {
            $netTotal = 0;
        }

        return [
            'sale' => [
                'id'              => (int) $sale['id'],
                'wc_order_id'     => (int) $sale['wc_order_id'],
                'order_number'    => $order instanceof \WC_Order ? (string) $order->get_order_number() : '',
                'status'          => $sale['status'],
                'display_status'  => $this->resolve_display_status($sale),
                'cashier_id'      => (int) $sale['cashier_id'],
                'cashier_name'    => $this->resolve_display_name((int) $sale['cashier_id']),
                'total'           => $this->formatDecimal($total),
                'refunded_total'  => $this->formatDecimal($refundedTotal),
                'net_total'       => $this->formatDecimal($netTotal),
                'created_at'      => $sale['created_at'],
            ],
            'order' => $order instanceof \WC_Order ? [
                'id'             => $order->get_id(),
                'number'         => (string) $order->get_order_number(),
                'status'         => $order->get_status(),
                'subtotal'       => $this->formatDecimal((float) $order->get_subtotal()),
                'discount_total' => $this->formatDecimal($this->resolve_discount_total($order)),
                'total'          => $this->formatDecimal((float) $order->get_total()),
                'date_created'   => $order->get_date_created()
                    ? $order->get_date_created()->format('Y-m-d\TH:i:s')
                    : null,
            ] : null,
            'items'   => $items,
            'payment' => $payment,
            'refunds' => $mappedRefunds,
            'logs'    => $mappedLogs,
            'actions' => [
                'can_reprint_ticket' => $order instanceof \WC_Order,
            ],
        ];
    }

    public function get_distinct_cashiers(): array
    {
        global $wpdb;

        $rows = $wpdb->get_col(
            "SELECT DISTINCT cashier_id FROM {$this->salesTable} ORDER BY cashier_id ASC"
        );

        if (! is_array($rows)) {
            return [];
        }

        $cashiers = [];

        foreach ($rows as $id) {
            $userId = (int) $id;

            if ($userId <= 0) {
                continue;
            }

            $user = get_userdata($userId);

            if (! $user instanceof \WP_User) {
                continue;
            }

            $cashiers[] = [
                'id'   => $userId,
                'name' => $user->display_name,
            ];
        }

        return $cashiers;
    }

    private function map_listing_row(array $row): array
    {
        $total         = (float) $row['total'];
        $refundedTotal = (float) ($row['refunded_total'] ?? '0.0000');
        $netTotal      = $total - $refundedTotal;

        if ($netTotal < 0) {
            $netTotal = 0;
        }

        $paymentSummary = $this->decode_payment_summary($row['payment_summary'] ?? null);
        $paymentMethod  = $paymentSummary['payment']['method'] ?? null;
        $methodLabel    = $paymentMethod === 'cash' ? 'Efectivo' : ($paymentMethod === 'card' ? 'Tarjeta' : null);

        return [
            'id'                 => (int) $row['id'],
            'wc_order_id'        => (int) $row['wc_order_id'],
            'status'             => $row['status'],
            'display_status'     => $this->resolve_display_status($row),
            'cashier_id'         => (int) $row['cashier_id'],
            'cashier_name'       => $this->resolve_display_name((int) $row['cashier_id']),
            'payment_method'     => $paymentMethod,
            'payment_method_label' => $methodLabel,
            'total'              => $this->formatDecimal($total),
            'refunded_total'     => $this->formatDecimal($refundedTotal),
            'net_total'          => $this->formatDecimal($netTotal),
            'created_at'         => $row['created_at'],
        ];
    }

    private function resolve_display_status(array $sale): string
    {
        $status        = $sale['status'] ?? '';
        $refundedTotal = (float) ($sale['refunded_total'] ?? '0.0000');
        $total         = (float) ($sale['total'] ?? '0.0000');

        if ($status === 'completed' && $refundedTotal > 0 && $refundedTotal < $total) {
            return 'partially_refunded';
        }

        return $status;
    }

    private function decode_payment_summary(mixed $paymentSummary): array
    {
        if (! is_string($paymentSummary) || trim($paymentSummary) === '') {
            return [];
        }

        $decoded = json_decode($paymentSummary, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function decode_payment(mixed $paymentSummary): ?array
    {
        $summary = $this->decode_payment_summary($paymentSummary);
        $payment = $summary['payment'] ?? null;

        if (! is_array($payment)) {
            return null;
        }

        $method           = $payment['method'] ?? '';
        $methodLabel      = $method === 'cash' ? 'Efectivo' : ($method === 'card' ? 'Tarjeta' : $method);
        $amountReceived   = $payment['amount_received'] ?? '0';
        $change           = $payment['change'] ?? '0';
        $cardReference    = $payment['card_reference'] ?? null;

        $result = [
            'method'       => $method,
            'method_label' => $methodLabel,
        ];

        if ($method === 'cash') {
            $result['amount_received'] = $amountReceived;
            $result['change']          = $change;
        }

        if ($method === 'card' && is_string($cardReference) && $cardReference !== '') {
            $result['card_reference'] = $cardReference;
        }

        return $result;
    }

    private function resolve_display_name(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $user = get_userdata($userId);

        if (! $user instanceof \WP_User) {
            return '';
        }

        return $user->display_name;
    }

    private function resolve_discount_total(\WC_Order $order): float
    {
        $discountTotal = 0.0;

        foreach ($order->get_fees() as $fee) {
            if ($fee->get_meta('_mx_pos_is_pos_discount', true) !== 'yes') {
                continue;
            }

            $discountTotal += abs((float) $fee->get_total());
        }

        return $discountTotal;
    }

    public function lookup_by_query(int $userId, string $query): array
    {
        global $wpdb;

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $where    = '';
        $whereArgs = [];

        if (is_numeric($query)) {
            $searchVal = (int) $query;
            $where      = ' AND (s.id = %d OR s.wc_order_id = %d)';
            $whereArgs[] = $searchVal;
            $whereArgs[] = $searchVal;
        } else {
            return [];
        }

        if ($this->should_restrict_to_own_sales($userId)) {
            $where       .= ' AND s.cashier_id = %d';
            $whereArgs[] = $userId;
        }

        $limit = 10;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal table identifier and fixed WHERE fragments; dynamic values are prepared below.
        $sql = "SELECT s.id, s.wc_order_id, s.status, s.total, s.refunded_total, s.created_at
                FROM {$this->salesTable} s
                WHERE 1=1 {$where}
                ORDER BY s.created_at DESC, s.id DESC
                LIMIT %d";

        $whereArgs[] = $limit;

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared in the nested call.
        $rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query template uses internal table identifier; placeholders are prepared here.
            $wpdb->prepare($sql, $whereArgs),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_map(function (array $row) {
            $saleTotal      = (float) $row['total'];
            $refundedTotal  = (float) ($row['refunded_total'] ?? '0');

            return [
                'id'            => (int) $row['id'],
                'order_id'      => (int) $row['wc_order_id'],
                'order_number'  => (string) $row['wc_order_id'],
                'status'        => $row['status'],
                'total'         => $this->formatDecimal($saleTotal),
                'refunded_total' => $this->formatDecimal($refundedTotal),
                'created_at'    => $row['created_at'],
                'can_refund'    => in_array($row['status'], ['completed', 'processing'], true)
                    && $saleTotal - $refundedTotal > 0.00001,
            ];
        }, $rows);
    }

    private function should_restrict_to_own_sales(int $userId): bool
    {
        if (current_user_can('manage_options')) {
            return false;
        }

        if (current_user_can('mx_pos_refund')) {
            return false;
        }

        return true;
    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
