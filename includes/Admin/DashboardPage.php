<?php

namespace MXPOSPro\Admin;

defined('ABSPATH') || exit;

use MXPOSPro\Reports\DashboardDataService;

class DashboardPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options') && ! current_user_can('mx_pos_view_dashboard')) {
            wp_die(
                esc_html__('You do not have permission to access this page.', 'mx-pos-pro')
            );
        }

        $dateFrom  = isset($_GET['dashboard_date_from']) ? sanitize_text_field(wp_unslash($_GET['dashboard_date_from'])) : date('Y-m-d');
        $dateTo    = isset($_GET['dashboard_date_to']) ? sanitize_text_field(wp_unslash($_GET['dashboard_date_to'])) : date('Y-m-d');
        $branchId  = isset($_GET['branch_id']) ? absint($_GET['branch_id']) : null;
        $registerId = isset($_GET['register_id']) ? absint($_GET['register_id']) : null;
        $employeeId = isset($_GET['employee_id']) ? absint($_GET['employee_id']) : null;
        $movementsPage = isset($_GET['movements_page']) ? max(1, absint($_GET['movements_page'])) : 1;
        $refundsPage   = isset($_GET['refunds_page']) ? max(1, absint($_GET['refunds_page'])) : 1;

        $service = new DashboardDataService();
        $filterOpts = $service->get_filter_options();
        $kpi        = $service->get_kpi_data($dateFrom, $dateTo, $branchId, $registerId, $employeeId);
        $byEmployee = $service->get_sales_by_employee($dateFrom, $dateTo, $branchId, $registerId, $employeeId);
        $byMethod   = $service->get_sales_by_payment_method($dateFrom, $dateTo, $branchId, $registerId, $employeeId);
        $discounts  = $service->get_discounts_coupons_summary($dateFrom, $dateTo, $branchId, $registerId, $employeeId);
        $movements  = $service->get_cash_movements_paginated($dateFrom, $dateTo, $branchId, $registerId, $employeeId, $movementsPage, 20);
        $closures   = $service->get_closures($dateFrom, $dateTo, $branchId, $registerId, $employeeId);
        $refunds    = $service->get_refunds_paginated($dateFrom, $dateTo, $branchId, $registerId, $employeeId, $refundsPage, 20);

        $tabParam = '&tab=dashboard';
        $baseUrl  = admin_url('admin.php?page=mx-pos-pro' . $tabParam);

        ?>
        <h2><?php esc_html_e('Dashboard operativo', 'mx-pos-pro'); ?></h2>
        <p class="description">
            <?php esc_html_e('Resumen de operación del POS por fecha, sucursal, caja y empleado.', 'mx-pos-pro'); ?>
        </p>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="mx-pos-pro" />
            <input type="hidden" name="tab" value="dashboard" />
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                <label>
                    <?php esc_html_e('Desde', 'mx-pos-pro'); ?><br/>
                    <input type="date" name="dashboard_date_from" value="<?php echo esc_attr($dateFrom); ?>" />
                </label>
                <label>
                    <?php esc_html_e('Hasta', 'mx-pos-pro'); ?><br/>
                    <input type="date" name="dashboard_date_to" value="<?php echo esc_attr($dateTo); ?>" />
                </label>
                <label>
                    <?php esc_html_e('Sucursal', 'mx-pos-pro'); ?><br/>
                    <select name="branch_id">
                        <option value=""><?php esc_html_e('Todas', 'mx-pos-pro'); ?></option>
                        <?php foreach ($filterOpts['branches'] as $b): ?>
                            <option value="<?php echo esc_attr((string) $b['id']); ?>" <?php selected($branchId, (int) $b['id']); ?>>
                                <?php echo esc_html($b['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <?php esc_html_e('Caja', 'mx-pos-pro'); ?><br/>
                    <select name="register_id">
                        <option value=""><?php esc_html_e('Todas', 'mx-pos-pro'); ?></option>
                        <?php foreach ($filterOpts['registers'] as $r): ?>
                            <option value="<?php echo esc_attr((string) $r['id']); ?>" <?php selected($registerId, (int) $r['id']); ?>>
                                <?php echo esc_html($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <?php esc_html_e('Empleado', 'mx-pos-pro'); ?><br/>
                    <select name="employee_id">
                        <option value=""><?php esc_html_e('Todos', 'mx-pos-pro'); ?></option>
                        <?php foreach ($filterOpts['employees'] as $emp): ?>
                            <option value="<?php echo esc_attr((string) $emp['id']); ?>" <?php selected($employeeId, (int) $emp['id']); ?>>
                                <?php echo esc_html($emp['display_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="align-self:flex-end;">
                    <br/>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Filtrar', 'mx-pos-pro'); ?></button>
                </label>
                <label style="align-self:flex-end;">
                    <br/>
                    <a href="<?php echo esc_url(add_query_arg(['dashboard_date_from' => date('Y-m-d'), 'dashboard_date_to' => date('Y-m-d'), 'branch_id' => null, 'register_id' => null, 'employee_id' => null], $baseUrl)); ?>" class="button">
                        <?php esc_html_e('Hoy', 'mx-pos-pro'); ?>
                    </a>
                </label>
            </div>
        </form>

        <?php self::render_kpi_cards($kpi); ?>

        <?php self::render_sales_by_employee($byEmployee); ?>

        <?php self::render_sales_by_payment_method($byMethod); ?>

        <?php self::render_discounts_coupons($discounts); ?>

        <?php self::render_cash_movements($movements, $baseUrl, $dateFrom, $dateTo, $branchId, $registerId, $employeeId); ?>

        <?php self::render_closures($closures); ?>

        <?php self::render_refunds($refunds, $baseUrl, $dateFrom, $dateTo, $branchId, $registerId, $employeeId); ?>
        <?php
    }

    private static function money(string $amount): string
    {
        return '$' . number_format((float) $amount, 2);
    }

    private static function maybeNull(string $value): string
    {
        $f = (float) $value;
        return $f != 0 ? self::money($value) : '$0.00';
    }

    private static function render_kpi_cards(array $kpi): void
    {
        $cards = [
            ['Ventas brutas', $kpi['gross_sales'], '#0073aa'],
            ['Tickets', (string) $kpi['ticket_count'], '#2271b1'],
            ['Ticket promedio', $kpi['avg_ticket'], '#005a87'],
            ['Devoluciones', $kpi['refund_total'], '#d63638'],
            ['Efectivo esperado', $kpi['expected_cash'], '#00a32a'],
            ['Cajas abiertas', (string) $kpi['open_sessions'], '#dba617'],
            ['Cajas cerradas', (string) $kpi['closed_sessions'], '#2271b1'],
            ['Cierres con diferencia', (string) $kpi['difference_closures'], '#d63638'],
            ['Cierres remotos', (string) $kpi['remote_closures'], '#9068be'],
        ];

        ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
            <?php foreach ($cards as $card): ?>
                <div style="flex:1;min-width:150px;max-width:200px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;text-align:center;border-left:4px solid <?php echo esc_attr($card[2]); ?>;">
                    <div style="font-size:12px;color:#646970;text-transform:uppercase;margin-bottom:4px;"><?php echo esc_html($card[0]); ?></div>
                    <div style="font-size:22px;font-weight:600;color:#1d2327;">
                        <?php echo in_array($card[0], ['Tickets', 'Cajas abiertas', 'Cajas cerradas', 'Cierres con diferencia', 'Cierres remotos'], true)
                            ? esc_html($card[1])
                            : esc_html(self::money($card[1])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private static function render_sales_by_employee(array $data): void
    {
        ?>
        <div class="mx-pos-section" style="margin-bottom:24px;">
            <h3><?php esc_html_e('Ventas por empleado', 'mx-pos-pro'); ?></h3>
            <?php if (empty($data)): ?>
                <p><?php esc_html_e('Sin datos para el período seleccionado.', 'mx-pos-pro'); ?></p>
            <?php else: ?>
                <table class="widefat striped" style="max-width:700px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Empleado', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Ventas', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Total', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Ticket promedio', 'mx-pos-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row):
                            $total   = (float) $row['total_sales'];
                            $count   = (int) $row['sale_count'];
                            $avgTkt  = $count > 0 ? $total / $count : 0;
                        ?>
                            <tr>
                                <td><?php echo esc_html($row['employee_name']); ?></td>
                                <td><?php echo esc_html((string) $count); ?></td>
                                <td><?php echo esc_html(self::money((string) $total)); ?></td>
                                <td><?php echo esc_html(self::money(number_format($avgTkt, 2, '.', ''))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_sales_by_payment_method(array $data): void
    {
        ?>
        <div class="mx-pos-section" style="margin-bottom:24px;">
            <h3><?php esc_html_e('Ventas por método de pago', 'mx-pos-pro'); ?></h3>
            <?php if (empty($data)): ?>
                <p><?php esc_html_e('Sin datos para el período seleccionado.', 'mx-pos-pro'); ?></p>
            <?php else: ?>
                <table class="widefat striped" style="max-width:700px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Método', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Pagos', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Total', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('%', 'mx-pos-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row['method_name']); ?></td>
                                <td><?php echo esc_html((string) $row['sale_count']); ?></td>
                                <td><?php echo esc_html(self::money($row['total_amount'])); ?></td>
                                <td><?php echo esc_html($row['percentage']); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_discounts_coupons(array $data): void
    {
        ?>
        <div class="mx-pos-section" style="margin-bottom:24px;">
            <h3><?php esc_html_e('Descuentos y cupones', 'mx-pos-pro'); ?></h3>
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
                <div style="flex:1;min-width:140px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px;text-align:center;">
                    <div style="font-size:11px;color:#646970;text-transform:uppercase;"><?php esc_html_e('Descuento total', 'mx-pos-pro'); ?></div>
                    <div style="font-size:18px;font-weight:600;"><?php echo esc_html(self::money($data['discount_total'])); ?></div>
                </div>
                <div style="flex:1;min-width:140px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px;text-align:center;">
                    <div style="font-size:11px;color:#646970;text-transform:uppercase;"><?php esc_html_e('Ventas con descuento', 'mx-pos-pro'); ?></div>
                    <div style="font-size:18px;font-weight:600;"><?php echo esc_html((string) $data['discount_count']); ?></div>
                </div>
                <div style="flex:1;min-width:140px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px;text-align:center;">
                    <div style="font-size:11px;color:#646970;text-transform:uppercase;"><?php esc_html_e('Cupón total', 'mx-pos-pro'); ?></div>
                    <div style="font-size:18px;font-weight:600;"><?php echo esc_html(self::money($data['coupon_total'])); ?></div>
                </div>
                <div style="flex:1;min-width:140px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px;text-align:center;">
                    <div style="font-size:11px;color:#646970;text-transform:uppercase;"><?php esc_html_e('Ventas con cupón', 'mx-pos-pro'); ?></div>
                    <div style="font-size:18px;font-weight:600;"><?php echo esc_html((string) $data['coupon_count']); ?></div>
                </div>
            </div>
            <?php if (! empty($data['coupons_by_code'])): ?>
                <table class="widefat striped" style="max-width:600px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Cupón', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Usos', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Monto descontado', 'mx-pos-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['coupons_by_code'] as $c): ?>
                            <tr>
                                <td><code><?php echo esc_html($c['code']); ?></code></td>
                                <td><?php echo esc_html((string) $c['count']); ?></td>
                                <td><?php echo esc_html(self::money(number_format($c['total'], 2, '.', ''))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_cash_movements(array $data, string $baseUrl, string $dateFrom, string $dateTo, ?int $branchId, ?int $registerId, ?int $employeeId): void
    {
        ?>
        <div class="mx-pos-section" style="margin-bottom:24px;">
            <h3><?php esc_html_e('Entradas y salidas manuales', 'mx-pos-pro'); ?></h3>
            <?php if (empty($data['items'])): ?>
                <p><?php esc_html_e('Sin datos para el período seleccionado.', 'mx-pos-pro'); ?></p>
            <?php else: ?>
                <table class="widefat striped" style="max-width:960px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Fecha', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Tipo', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Monto', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Motivo', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Empleado', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Caja', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Sucursal', 'mx-pos-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['items'] as $item):
                            $typeLabel = $item['movement_type'] === 'cash_in' ? 'Entrada' : 'Salida';
                        ?>
                            <tr>
                                <td><?php echo esc_html($item['created_at']); ?></td>
                                <td><?php echo esc_html($typeLabel); ?></td>
                                <td><?php echo esc_html(self::money($item['amount'])); ?></td>
                                <td><?php echo esc_html($item['reason'] ?? '—'); ?></td>
                                <td><?php echo esc_html($item['employee_name']); ?></td>
                                <td><?php echo esc_html($item['register_name']); ?></td>
                                <td><?php echo esc_html($item['branch_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php self::render_pagination($data, $baseUrl, 'movements_page', $dateFrom, $dateTo, $branchId, $registerId, $employeeId); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_closures(array $data): void
    {
        ?>
        <div class="mx-pos-section" style="margin-bottom:24px;">
            <h3><?php esc_html_e('Cierres del período', 'mx-pos-pro'); ?></h3>
            <?php if (empty($data)): ?>
                <p><?php esc_html_e('Sin datos para el período seleccionado.', 'mx-pos-pro'); ?></p>
            <?php else: ?>
                <table class="widefat striped" style="max-width:1100px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Sesión', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Sucursal', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Caja', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Empleado', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Apertura', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Cierre', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Esperado', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Contado', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Diferencia', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Motivo', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Tipo', 'mx-pos-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td>#<?php echo esc_html((string) $row['session_id']); ?></td>
                                <td><?php echo esc_html($row['branch_name']); ?></td>
                                <td><?php echo esc_html($row['register_name']); ?></td>
                                <td><?php echo esc_html($row['employee_name']); ?></td>
                                <td><?php echo esc_html(self::maybeNull($row['opening_amount'])); ?></td>
                                <td><?php echo esc_html($row['closed_at'] ?? '—'); ?></td>
                                <td><?php echo esc_html($row['closing_expected'] !== null ? self::money($row['closing_expected']) : '—'); ?></td>
                                <td><?php echo esc_html($row['closing_counted'] !== null ? self::money($row['closing_counted']) : '—'); ?></td>
                                <td><?php echo esc_html($row['difference'] !== null ? self::money($row['difference']) : '—'); ?></td>
                                <td><?php echo esc_html($row['close_note'] ?? '—'); ?></td>
                                <td>
                                    <span style="<?php echo $row['is_remote'] ? 'color:#9068be;font-weight:600;' : 'color:#2271b1;'; ?>">
                                        <?php echo esc_html($row['type_label']); ?>
                                    </span>
                                    <?php if ($row['is_remote'] && $row['admin_name']): ?>
                                        <br/><small><?php echo esc_html($row['admin_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_refunds(array $data, string $baseUrl, string $dateFrom, string $dateTo, ?int $branchId, ?int $registerId, ?int $employeeId): void
    {
        ?>
        <div class="mx-pos-section" style="margin-bottom:24px;">
            <h3><?php esc_html_e('Devoluciones / Cancelaciones', 'mx-pos-pro'); ?></h3>
            <?php if (empty($data['items'])): ?>
                <p><?php esc_html_e('Sin datos para el período seleccionado.', 'mx-pos-pro'); ?></p>
            <?php else: ?>
                <table class="widefat striped" style="max-width:960px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Fecha', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Ticket', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Tipo', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Empleado', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Monto', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Método', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Motivo', 'mx-pos-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['items'] as $item):
                            $typeLabel = in_array($item['refund_type'], ['total', 'full'], true) ? 'Total' : 'Parcial';
                        ?>
                            <tr>
                                <td><?php echo esc_html($item['created_at']); ?></td>
                                <td>#<?php echo esc_html((string) $item['sale_id']); ?></td>
                                <td><?php echo esc_html($typeLabel); ?></td>
                                <td><?php echo esc_html($item['cashier_name']); ?></td>
                                <td><?php echo esc_html(self::money($item['refund_amount'])); ?></td>
                                <td><?php echo esc_html(ucfirst($item['refund_method'] ?? '—')); ?></td>
                                <td><?php echo esc_html($item['reason'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php self::render_pagination($data, $baseUrl, 'refunds_page', $dateFrom, $dateTo, $branchId, $registerId, $employeeId); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_pagination(array $data, string $baseUrl, string $pageParam, string $dateFrom, string $dateTo, ?int $branchId, ?int $registerId, ?int $employeeId): void
    {
        $totalPages = max(1, (int) ceil($data['total'] / $data['per_page']));
        if ($totalPages <= 1) {
            return;
        }

        $currentPage = $data['page'];
        $queryBase = add_query_arg([
            'dashboard_date_from' => $dateFrom,
            'dashboard_date_to'   => $dateTo,
            'branch_id'           => $branchId,
            'register_id'         => $registerId,
            'employee_id'         => $employeeId,
        ], $baseUrl);

        ?>
        <div style="margin-top:12px;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p === $currentPage): ?>
                    <strong style="margin:0 4px;"><?php echo esc_html((string) $p); ?></strong>
                <?php else: ?>
                    <a href="<?php echo esc_url(add_query_arg($pageParam, $p, $queryBase)); ?>" style="margin:0 4px;">
                        <?php echo esc_html((string) $p); ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php
    }
}
