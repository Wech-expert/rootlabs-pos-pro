<?php

namespace MXPOSPro\Sales;

defined('ABSPATH') || exit;

use MXPOSPro\Entities\BranchRepository;
use MXPOSPro\Entities\RegisterRepository;
use MXPOSPro\Entities\EmployeeRepository;
use WP_Error;

class TicketService
{
    private SaleRepository $saleRepo;
    private ?array $ticketSettingsCache = null;
    private ?BranchRepository $branchRepo = null;
    private ?RegisterRepository $registerRepo = null;
    private ?EmployeeRepository $employeeRepo = null;

    public function __construct()
    {
        $this->saleRepo = new SaleRepository();
    }

    private function branch_repo(): BranchRepository
    {
        if ($this->branchRepo === null) {
            $this->branchRepo = new BranchRepository();
        }

        return $this->branchRepo;
    }

    private function register_repo(): RegisterRepository
    {
        if ($this->registerRepo === null) {
            $this->registerRepo = new RegisterRepository();
        }

        return $this->registerRepo;
    }

    private function employee_repo(): EmployeeRepository
    {
        if ($this->employeeRepo === null) {
            $this->employeeRepo = new EmployeeRepository();
        }

        return $this->employeeRepo;
    }

    private function get_ticket_settings(): array
    {
        if ($this->ticketSettingsCache !== null) {
            return $this->ticketSettingsCache;
        }

        $show_logo = get_option('mx_pos_ticket_show_logo', 'no') === 'yes';
        $attachment_id = (int) get_option('mx_pos_ticket_logo_attachment_id', 0);
        $logo_url = '';
        if ($show_logo && $attachment_id > 0) {
            $mime = get_post_mime_type($attachment_id);
            if ($mime && str_starts_with($mime, 'image/')) {
                $url = wp_get_attachment_image_url($attachment_id, 'medium');
                if ($url) {
                    $logo_url = $url;
                }
            }
        }

        $paper_width = (string) get_option('mx_pos_ticket_paper_width', '80mm');
        if (! in_array($paper_width, ['80mm', '58mm'], true)) {
            $paper_width = '80mm';
        }

        $this->ticketSettingsCache = [
            'business_name'       => (string) get_option('mx_pos_ticket_business_name', ''),
            'footer_text'         => (string) get_option('mx_pos_ticket_footer_text', ''),
            'paper_width'         => $paper_width,
            'show_logo'           => $show_logo,
            'logo_url'            => $logo_url,
            'apply_logo_to_sales' => get_option('mx_pos_ticket_apply_logo_to_sales', 'yes') === 'yes',
            'apply_logo_to_cuts'  => get_option('mx_pos_ticket_apply_logo_to_cuts', 'no') === 'yes',
            'show_store_info'     => get_option('mx_pos_ticket_show_store_info', 'yes') === 'yes',
            'show_cashier'        => get_option('mx_pos_ticket_show_cashier', 'yes') === 'yes',
            'show_payment_method' => get_option('mx_pos_ticket_show_payment_method', 'yes') === 'yes',
        ];

        return $this->ticketSettingsCache;
    }

    private function has_visible_logo(array $ticket_settings, string $context = 'sale'): bool
    {
        $apply_logo = $context === 'cut'
            ? ! empty($ticket_settings['apply_logo_to_cuts'])
            : ! empty($ticket_settings['apply_logo_to_sales']);

        return ! empty($ticket_settings['show_logo'])
            && $apply_logo
            && ! empty($ticket_settings['logo_url']);
    }

    private function get_store_line_html(array $ticket_settings, string $store_name, string $context = 'sale'): string
    {
        if (empty($ticket_settings['show_store_info'])) {
            return '';
        }

        if ($this->has_visible_logo($ticket_settings, $context)) {
            return '';
        }

        return '<div class="store">' . esc_html(mb_strtoupper($store_name)) . '</div>';
    }

    private function get_rootlabs_credit_html(): string
    {
        return '<div class="rootlabs-credit">Desarrollado por rootlabs.mx</div><div class="ticket-bottom-spacer">&nbsp;<br>&nbsp;</div>';
    }

    private function build_ticket_style(string $template = 'sale'): string
    {
        $settings = $this->get_ticket_settings();
        $is80mm = ($settings['paper_width'] ?? '80mm') === '80mm';

        $pageWidth = $is80mm ? '80mm' : '58mm';
        $pageMargin = $is80mm ? '3mm' : '2mm';
        $bodyWidth = $is80mm ? '74mm' : '54mm';
        $bodyFontSize = $is80mm ? '13px' : '12px';
        $storeFontSize = $is80mm ? '16px' : '14px';
        $titleFontSize = $is80mm ? '17px' : '15px';
        $metaFontSize = $is80mm ? '12px' : '11px';
        $smallFontSize = $is80mm ? '11px' : '10px';
        $totalFontSize = $is80mm ? '18px' : '15px';
        $logoWidth = $is80mm ? '42mm' : '34mm';
        $amountWidth = $is80mm ? '27mm' : '22mm';

        $extra = '';
        if ($template === 'gift') {
            $extra = '
  .gift-title { text-align: center; font-size: ' . $titleFontSize . '; font-weight: 700; letter-spacing: 0; margin: 5px 0 6px; }
  .gift-policy { text-align: center; font-size: ' . $smallFontSize . '; margin-top: 8px; }
';
        } elseif ($template === 'cut') {
            $extra = '
  .cut-type { text-align: center; font-size: ' . $titleFontSize . '; font-weight: 700; letter-spacing: 0; margin: 4px 0 1px; }
  .cut-subtitle { text-align: center; font-size: ' . $smallFontSize . '; margin-bottom: 4px; }
  .section-title { display: block; font-size: ' . $smallFontSize . '; font-weight: 700; margin: 7px 0 3px; text-transform: uppercase; }
';
        }

        return '
  @page { size: ' . $pageWidth . ' auto; margin: ' . $pageMargin . '; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html,
  body {
    background: #fff;
    color: #000;
  }
  body {
    width: ' . $bodyWidth . ';
    margin: 0 auto;
    padding: 0;
    font-family: Arial, Helvetica, sans-serif;
    font-size: ' . $bodyFontSize . ';
    line-height: 1.35;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .store { text-align: center; font-weight: 700; font-size: ' . $storeFontSize . '; line-height: 1.2; margin-bottom: 3px; text-transform: uppercase; }
  .store-address { text-align: center; font-size: ' . $smallFontSize . '; line-height: 1.3; margin-bottom: 5px; }
  .logo { text-align: center; margin-bottom: 5px; }
  .logo img { max-width: ' . $logoWidth . '; max-height: 22mm; width: auto; height: auto; display: block; margin: 0 auto; }
  .meta { margin: 7px 0; font-size: ' . $metaFontSize . '; }
  .meta span { display: block; margin-bottom: 1px; }
  .sep { border: none; border-top: 1px dashed #000; margin: 7px 0; height: 0; }
  .items { width: 100%; }
  .items-title { font-size: ' . $smallFontSize . '; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 3px; margin-bottom: 4px; }
  .item { padding: 5px 0; border-bottom: 1px solid #d0d0d0; break-inside: avoid; }
  .item:last-child { border-bottom: none; }
  .item-name { font-weight: 700; word-break: break-word; }
  .item-sku,
  .item-variation { display: block; font-size: ' . $smallFontSize . '; color: #333; font-weight: 400; margin-top: 1px; }
  .item-detail,
  .payment-row,
  .totals tr { display: flex; justify-content: space-between; align-items: baseline; gap: 4mm; width: 100%; }
  .item-detail { margin-top: 3px; font-size: ' . $smallFontSize . '; }
  .item-total,
  .amount { min-width: ' . $amountWidth . '; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .totals { width: 100%; border-collapse: collapse; }
  .total-line { margin-bottom: 2px; }
  .total-line .label { text-align: left; }
  .total-final { font-weight: 700; border-top: 1px solid #000; margin-top: 5px; padding-top: 5px; font-size: ' . $totalFontSize . '; line-height: 1.2; }
  .payment { margin-top: 2px; }
  .payment-title { display: block; font-size: ' . $smallFontSize . '; font-weight: 700; margin-bottom: 3px; text-transform: uppercase; }
  .payment span { display: block; }
  .payment-row { margin-bottom: 2px; }
  .payment-ref { font-size: ' . $smallFontSize . '; color: #333; margin: -1px 0 3px; }
  .pos-id { text-align: center; font-size: ' . $smallFontSize . '; color: #4c4c4c; margin-top: 7px; }
  .footer { text-align: center; font-size: ' . $smallFontSize . '; margin-top: 8px; line-height: 1.3; }
  .rootlabs-credit { text-align: center; font-size: ' . $smallFontSize . '; margin-top: 7px; line-height: 1.25; font-weight: 700; }
  .ticket-bottom-spacer { height: 12mm; line-height: 6mm; font-size: 1px; }
' . $extra . '
  @media print {
    body { width: ' . $bodyWidth . '; }
    .sep { border-top: 1px dashed #000; }
  }';
    }

    private function get_branch_name(?int $branchId): string
    {
        if ($branchId === null || $branchId <= 0) {
            return '';
        }

        $branch = $this->branch_repo()->get_by_id($branchId);

        return is_array($branch) && ! empty($branch['name']) ? (string) $branch['name'] : '';
    }

    private function get_store_address_for_sale(?int $branchId): string
    {
        if ($branchId !== null && $branchId > 0) {
            $branch = $this->branch_repo()->get_by_id($branchId);

            if (is_array($branch) && ! empty($branch['address'])) {
                $address = trim((string) $branch['address']);

                if ($address !== '') {
                    return $address;
                }
            }
        }

        $addr1 = (string) get_option('woocommerce_store_address', '');
        $addr2 = (string) get_option('woocommerce_store_address_2', '');
        $city  = (string) get_option('woocommerce_store_city', '');
        $postcode = (string) get_option('woocommerce_store_postcode', '');

        $lines = [];
        if ($addr1 !== '') {
            $lines[] = $addr1;
        }
        if ($addr2 !== '') {
            $lines[] = $addr2;
        }

        $city_line_parts = [];
        if ($city !== '') {
            $city_line_parts[] = $city;
        }
        if ($postcode !== '') {
            $city_line_parts[] = $postcode;
        }
        if (count($city_line_parts) > 0) {
            $lines[] = implode(', ', $city_line_parts);
        }

        return implode("\n", $lines);
    }

    private function get_register_name(?int $registerId): string
    {
        if ($registerId === null || $registerId <= 0) {
            return '';
        }

        $register = $this->register_repo()->get_by_id($registerId);

        return is_array($register) && ! empty($register['name']) ? (string) $register['name'] : '';
    }

    private function get_pos_employee_name(?int $employeeId): string
    {
        if ($employeeId === null || $employeeId <= 0) {
            return '';
        }

        $employee = $this->employee_repo()->get_by_id($employeeId);

        if (! is_array($employee)) {
            return '';
        }

        $name = trim((string) ($employee['display_name'] ?? ''));

        if ($name === '') {
            $name = trim((string) ($employee['name'] ?? ''));
        }

        if ($name === '') {
            $name = trim((string) ($employee['username'] ?? ''));
        }

        return $name;
    }

    // ─── Sale ticket ────────────────────────────────────────────────

    public function generate_ticket_html(int $sale_id): string|WP_Error
    {
        $sale = $this->saleRepo->get_by_id($sale_id);

        if ($sale === null) {
            return new WP_Error(
                'mx_pos_ticket_sale_not_found',
                __('Sale not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        $wc_order_id = (int) $sale['wc_order_id'];
        $order = wc_get_order($wc_order_id);

        if (! $order instanceof \WC_Order) {
            return new WP_Error(
                'mx_pos_ticket_order_not_found',
                __('Order not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        $ticket_settings = $this->get_ticket_settings();

        $store_name     = $this->get_store_name();
        $store_address  = $this->get_store_address_for_sale(isset($sale['branch_id']) ? (int) $sale['branch_id'] : null);
        $order_number   = $order->get_order_number();
        $created_at     = $this->format_date($sale['created_at'] ?? current_time('mysql'));
        $cashier_name   = $this->get_cashier_name((int) ($sale['cashier_id'] ?? 0));
        $customer_name  = $this->get_customer_name($order);
        $branch_name    = $this->get_branch_name(isset($sale['branch_id']) ? (int) $sale['branch_id'] : null);
        $register_name  = $this->get_register_name(isset($sale['pos_register_id']) ? (int) $sale['pos_register_id'] : null);
        $pos_employee   = $this->get_pos_employee_name(isset($sale['pos_employee_id']) ? (int) $sale['pos_employee_id'] : null);
        $session_id     = (int) ($sale['session_id'] ?? 0);
        $session_cashier = $this->get_session_pos_employee_name($session_id);
        $order_cashier  = $this->get_order_cashier_name($order);
        $sale_cashier_as_employee = $this->get_pos_employee_name(isset($sale['cashier_id']) ? (int) $sale['cashier_id'] : null);
        $pos_sale_id    = (int) ($sale['id'] ?? 0);
        $display_cashier = $pos_employee !== ''
            ? $pos_employee
            : ($session_cashier !== ''
                ? $session_cashier
                : ($order_cashier !== ''
                    ? $order_cashier
                    : ($sale_cashier_as_employee !== '' ? $sale_cashier_as_employee : $cashier_name)));

        $items_html = $this->build_items_rows($order, true);

        $totals = $this->build_totals($order);
        $subtotal = $totals['subtotal'];
        $discount_total = $totals['discount_total'];
        $total = $totals['total'];

        $payment_html = '';
        if ($ticket_settings['show_payment_method']) {
            $payment_html = $this->build_payment_section($sale);
        }

        $customer_line = '';
        if ($customer_name !== '') {
            $customer_line = '<span>' . esc_html__('Cliente:', 'mx-pos-pro') . ' ' . esc_html($customer_name) . '</span>';
        }

        $cashier_line = '';
        if ($ticket_settings['show_cashier'] && $display_cashier !== '') {
            $cashier_line = '<span>' . esc_html__('Cajero:', 'mx-pos-pro') . ' ' . esc_html($display_cashier) . '</span>';
        }

        $discount_section = '';
        if ((float) $discount_total > 0) {
            $discount_section = '
        <tr class="total-line">
          <td class="label">' . esc_html__('Descuento:', 'mx-pos-pro') . '</td>
          <td class="amount">-$' . esc_html($discount_total) . '</td>
        </tr>';
        }

        $coupon_section = '';
        $couponTotal = $totals['coupon_total'] ?? '0.00';
        if ((float) $couponTotal > 0) {
            $codes = implode(', ', $totals['coupon_codes'] ?? []);
            $coupon_section = '
        <tr class="total-line">
          <td class="label">' . esc_html__('Cupón', 'mx-pos-pro') . ' (' . esc_html($codes) . '):</td>
          <td class="amount">-$' . esc_html($couponTotal) . '</td>
        </tr>';
        }

        $tax_section = '';
        $taxTotal = $totals['tax_total'] ?? '0.00';
        if ((float) $taxTotal > 0) {
            $tax_section = '
        <tr class="total-line">
          <td class="label">' . esc_html__('Impuestos:', 'mx-pos-pro') . '</td>
          <td class="amount">$' . esc_html($taxTotal) . '</td>
        </tr>';
        }

        $logo_html = '';
        if ($this->has_visible_logo($ticket_settings, 'sale')) {
            $logo_html = '<div class="logo"><img src="' . esc_url($ticket_settings['logo_url']) . '" alt="' . esc_attr($store_name) . '" /></div>';
        }

        $store_line = $this->get_store_line_html($ticket_settings, $store_name, 'sale');

        $store_address_html = '';
        if ($store_address !== '') {
            $store_address_html = '<div class="store-address">' . nl2br(esc_html($store_address)) . '</div>';
        }

        $footer_text = $ticket_settings['footer_text'] !== ''
            ? $ticket_settings['footer_text']
            : __('Gracias por su compra', 'mx-pos-pro');

        $meta_items = [];
        $meta_items[] = '<span>' . esc_html__('Folio:', 'mx-pos-pro') . ' #' . esc_html($order_number) . '</span>';
        $meta_items[] = '<span>' . esc_html($created_at) . '</span>';
        if ($cashier_line !== '') {
            $meta_items[] = $cashier_line;
        }
        if ($branch_name !== '') {
            $meta_items[] = '<span>' . esc_html__('Sucursal:', 'mx-pos-pro') . ' ' . esc_html($branch_name) . '</span>';
        }
        if ($register_name !== '') {
            $meta_items[] = '<span>' . esc_html__('Caja:', 'mx-pos-pro') . ' ' . esc_html($register_name) . '</span>';
        }
        if ($session_id > 0) {
            $meta_items[] = '<span>' . esc_html__('Sesión:', 'mx-pos-pro') . ' #' . esc_html((string) $session_id) . '</span>';
        }
        if ($customer_line !== '') {
            $meta_items[] = $customer_line;
        }
        $meta_html = implode("\n    ", $meta_items);

        $pos_id_line = '';
        if ($pos_sale_id > 0) {
            $pos_id_line = '<div class="pos-id">POS #' . esc_html((string) $pos_sale_id) . '</div>';
        }

        $payment_section_html = '';
        if ($payment_html !== '') {
            $payment_section_html = '
  <hr class="sep">
  ' . $payment_html . '
  <hr class="sep">';
        }

        $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . sprintf(
            /* translators: %s: order number */
            esc_html__('Ticket %s', 'mx-pos-pro'),
            esc_html($order_number)
        ) . '</title>
<style>
  ' . $this->build_ticket_style('sale') . '
</style>
</head>
<body data-ticket-width="' . esc_attr($ticket_settings['paper_width']) . '">
  ' . $logo_html . '
  ' . $store_line . '
  ' . $store_address_html . '
  <div class="meta">
    ' . $meta_html . '
  </div>
  <hr class="sep">
  <div class="items">
    <div class="items-title">' . esc_html__('Productos', 'mx-pos-pro') . '</div>
    ' . $items_html . '
  </div>
  <hr class="sep">
  <table class="totals">
    <tr class="total-line">
      <td class="label">' . esc_html__('Subtotal:', 'mx-pos-pro') . '</td>
      <td class="amount">$' . esc_html($subtotal) . '</td>
    </tr>
    ' . $discount_section . '
    ' . $coupon_section . '
    ' . $tax_section . '
    <tr class="total-line total-final">
      <td class="label">' . esc_html__('TOTAL:', 'mx-pos-pro') . '</td>
      <td class="amount">$' . esc_html($total) . '</td>
    </tr>
  </table>
  ' . $payment_section_html . '
  ' . $pos_id_line . '
  <div class="footer">' . esc_html($footer_text) . '</div>
  ' . $this->get_rootlabs_credit_html() . '
</body>
</html>';

        return $html;
    }

    // ─── Gift ticket ────────────────────────────────────────────────

    public function generate_gift_ticket_html(int $sale_id): string|WP_Error
    {
        $sale = $this->saleRepo->get_by_id($sale_id);

        if ($sale === null) {
            return new WP_Error(
                'mx_pos_ticket_sale_not_found',
                __('Sale not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        $wc_order_id = (int) $sale['wc_order_id'];
        $order = wc_get_order($wc_order_id);

        if (! $order instanceof \WC_Order) {
            return new WP_Error(
                'mx_pos_ticket_order_not_found',
                __('Order not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        $ticket_settings = $this->get_ticket_settings();

        $store_name    = $this->get_store_name();
        $store_address = $this->get_store_address_for_sale(isset($sale['branch_id']) ? (int) $sale['branch_id'] : null);
        $order_number  = $order->get_order_number();
        $created_at    = $this->format_date($sale['created_at'] ?? current_time('mysql'));
        $branch_name   = $this->get_branch_name(isset($sale['branch_id']) ? (int) $sale['branch_id'] : null);
        $register_name = $this->get_register_name(isset($sale['pos_register_id']) ? (int) $sale['pos_register_id'] : null);

        $items_html = $this->build_gift_items_rows($order);

        $logo_html = '';
        if ($this->has_visible_logo($ticket_settings, 'sale')) {
            $logo_html = '<div class="logo"><img src="' . esc_url($ticket_settings['logo_url']) . '" alt="' . esc_attr($store_name) . '" /></div>';
        }

        $store_line = $this->get_store_line_html($ticket_settings, $store_name, 'sale');

        $store_address_html = '';
        if ($store_address !== '') {
            $store_address_html = '<div class="store-address">' . nl2br(esc_html($store_address)) . '</div>';
        }

        $meta_items = [];
        $meta_items[] = '<span>' . esc_html__('Folio:', 'mx-pos-pro') . ' #' . esc_html($order_number) . '</span>';
        $meta_items[] = '<span>' . esc_html($created_at) . '</span>';
        if ($branch_name !== '') {
            $meta_items[] = '<span>' . esc_html__('Sucursal:', 'mx-pos-pro') . ' ' . esc_html($branch_name) . '</span>';
        }
        if ($register_name !== '') {
            $meta_items[] = '<span>' . esc_html__('Caja:', 'mx-pos-pro') . ' ' . esc_html($register_name) . '</span>';
        }
        $meta_html = implode("\n    ", $meta_items);

        $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . sprintf(
            /* translators: %s: order number */
            esc_html__('Ticket Regalo %s', 'mx-pos-pro'),
            esc_html($order_number)
        ) . '</title>
<style>
  ' . $this->build_ticket_style('gift') . '
</style>
</head>
<body data-ticket-width="' . esc_attr($ticket_settings['paper_width']) . '">
  ' . $logo_html . '
  ' . $store_line . '
  ' . $store_address_html . '
  <div class="gift-title">' . esc_html__('TICKET DE REGALO', 'mx-pos-pro') . '</div>
  <div class="meta">
    ' . $meta_html . '
  </div>
  <hr class="sep">
  <div class="items">
    <div class="items-title">' . esc_html__('Productos', 'mx-pos-pro') . '</div>
    ' . $items_html . '
  </div>
  <hr class="sep">
  <div class="gift-policy">' . esc_html__('Cambios sujetos a politicas de la tienda.', 'mx-pos-pro') . '</div>
  ' . $this->get_rootlabs_credit_html() . '
</body>
</html>';

        return $html;
    }

    // ─── Cut ticket ─────────────────────────────────────────────────

    public function generate_cut_ticket_html(array $summary, string $cutType): string
    {
        $ticket_settings = $this->get_ticket_settings();

        $store_name     = $this->get_store_name();
        $isZ            = $cutType === 'Z';
        $cutLabel       = $isZ ? 'CIERRE DE CAJA' : 'PRE-CORTE';
        $cutSubLabel    = $isZ ? '' : '<div class="cut-subtitle">No cierra caja</div>';
        $sessionId      = (int) ($summary['session']['id'] ?? 0);
        $openedAt       = $this->format_date($summary['session']['opened_at'] ?? '');
        $cutAt          = $this->format_date($summary['generated_at'] ?? '');
        $cashierNameRaw = trim((string) ($summary['session']['cashier_name'] ?? ''));
        $cashierName    = esc_html($cashierNameRaw !== '' ? $cashierNameRaw : __('Sin operador registrado', 'mx-pos-pro'));
        $cutId          = isset($summary['cut_id']) ? (int) $summary['cut_id'] : null;
        $sequence       = isset($summary['sequence']) ? (int) $summary['sequence'] : null;
        $openingAmount  = esc_html($summary['opening']['amount'] ?? '0.0000');
        $cashIn         = esc_html($summary['cash_flow']['cash_in_total'] ?? '0.0000');
        $cashOut        = esc_html($summary['cash_flow']['cash_out_total'] ?? '0.0000');
        $manualCashIn   = esc_html($summary['cash_flow']['manual_cash_in_total'] ?? '0.0000');
        $manualCashOut  = esc_html($summary['cash_flow']['manual_cash_out_total'] ?? '0.0000');
        $salesCashIn    = esc_html($summary['cash_flow']['sales_cash_in_total'] ?? ($summary['sales']['cash_collected_total'] ?? '0.0000'));
        $salesChangeOut = esc_html($summary['cash_flow']['sales_change_out_total'] ?? ($summary['sales']['cash_change_total'] ?? '0.0000'));
        $expectedCash   = esc_html($summary['expected_cash'] ?? '0.0000');

        $collectedSales = esc_html($summary['sales']['collected_total'] ?? '0.0000');
        $cardSales      = esc_html($summary['sales']['card_collected_total'] ?? '0.0000');
        $refundsTotal   = esc_html($summary['refunds']['total'] ?? '0.0000');
        $netAfter       = esc_html($summary['net_after_refunds'] ?? '0.0000');
        $countOrders    = (int) ($summary['sales']['count_orders'] ?? 0);
        $countRefunds   = (int) ($summary['refunds']['count_refunds'] ?? 0);
        $countCanc      = (int) ($summary['refunds']['count_cancellations'] ?? 0);
        $discountsTotal = esc_html($summary['discounts']['total'] ?? '0.0000');

        $couponTotal    = esc_html($summary['coupons']['total'] ?? '0.0000');
        $couponCount    = (int) ($summary['coupons']['count'] ?? 0);
        $cardFeesTotal  = esc_html($summary['card_fees']['total'] ?? '0.0000');
        $cardFeesCount  = (int) ($summary['card_fees']['count'] ?? 0);

        // ── Closing section (only Z) ──
        $closingSection = '';
        if ($isZ && isset($summary['closing'])) {
            $closing = $summary['closing'];
            $countedAmt  = esc_html($closing['counted_amount'] ?? '-');
            $diffAmt     = esc_html($closing['difference'] ?? '-');
            $closeNote   = esc_html($closing['close_note'] ?? '');

            $closingSection = '
   <hr class="sep">
   <span class="section-title">CIERRE</span>
   <table class="totals">
     <tr>
       <td class="label">Efectivo contado:</td>
       <td class="amount">$' . $countedAmt . '</td>
     </tr>
     <tr>
       <td class="label">Diferencia:</td>
       <td class="amount">$' . $diffAmt . '</td>
     </tr>
   </table>';

            if ($closeNote !== '') {
                $closingSection .= '
   <span>Nota: ' . $closeNote . '</span>';
            }
        }

        // ── Discounts ──
        $discountSection = '';
        if ((float) $discountsTotal > 0) {
            $discountSection = '
   <table class="totals">
     <tr>
       <td class="label">Descuentos:</td>
       <td class="amount">-$' . $discountsTotal . '</td>
     </tr>
   </table>';
        }

        // ── Coupons ──
        $couponSection = '';
        if ((float) $couponTotal > 0) {
            $couponSection = '
   <hr class="sep">
   <span class="section-title">CUPONES</span>
   <table class="totals">
     <tr>
       <td class="label">Total cupones (' . esc_html((string) $couponCount) . '):</td>
       <td class="amount">-$' . $couponTotal . '</td>
     </tr>
   </table>';
        }

        // ── Card fees ──
        $cardFeeSection = '';
        if ((float) $cardFeesTotal > 0) {
            $cardFeeSection = '
   <hr class="sep">
   <span class="section-title">COMISIONES TDC</span>
   <table class="totals">
     <tr>
       <td class="label">Comisiones (' . esc_html((string) $cardFeesCount) . '):</td>
       <td class="amount">-$' . $cardFeesTotal . '</td>
     </tr>
   </table>';
        }

        // ── Dynamic payment methods ──
        $byMethod = $summary['by_method'] ?? [];
        $byMethodRows = '';
        if (is_array($byMethod) && count($byMethod) > 0) {
            foreach ($byMethod as $methodSlug => $methodData) {
                if (! is_array($methodData)) {
                    continue;
                }
                $methodName  = esc_html($methodData['name'] ?? $methodSlug);
                $methodAmt   = esc_html($methodData['total'] ?? '0.0000');
                $methodCount = (int) ($methodData['count'] ?? 0);
                $byMethodRows .= '
     <tr>
       <td class="label">' . $methodName . ' (' . esc_html((string) $methodCount) . '):</td>
       <td class="amount">$' . $methodAmt . '</td>
     </tr>';
            }
        }

        // ── Mixed breakdown ──
        $mixedSection = '';
        $mixedBreakdown = $summary['mixed_breakdown'] ?? null;
        if (is_array($mixedBreakdown) && count($mixedBreakdown) > 0) {
            $mixedRows = '';
            foreach ($mixedBreakdown as $mixSlug => $mixData) {
                if (! is_array($mixData)) {
                    continue;
                }
                $mixTotal = esc_html($mixData['total'] ?? '0.0000');
                $mixCount = (int) ($mixData['count'] ?? 0);
                $mixedRows .= '
     <tr>
       <td class="label">' . esc_html(ucfirst($mixSlug)) . ' (' . esc_html((string) $mixCount) . '):</td>
       <td class="amount">$' . $mixTotal . '</td>
     </tr>';
            }
            if ($mixedRows !== '') {
                $mixedSection = '
   <hr class="sep">
   <span class="section-title">DESGLOSE MIXTO</span>
   <table class="totals">
     ' . $mixedRows . '
   </table>';
            }
        }

        // ── Cut meta (ID, sequence) ──
        $cutMetaItems = [];
        if ($cutId !== null) {
            $cutMetaItems[] = '<span>Comprobante #' . esc_html((string) $cutId) . '</span>';
        }
        if ($sequence !== null) {
            $cutMetaItems[] = '<span>Secuencia: ' . esc_html((string) $sequence) . '</span>';
        }
        $cutMetaHtml = count($cutMetaItems) > 0
            ? "\n   " . implode("\n   ", $cutMetaItems)
            : '';

        $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . esc_html($cutLabel) . '</title>
<style>
  ' . $this->build_ticket_style('cut') . '
</style>
</head>
<body data-ticket-width="' . esc_attr($ticket_settings['paper_width']) . '">
  ' . $this->get_cut_logo_html($ticket_settings, $store_name) . '
  ' . $this->get_store_line_html($ticket_settings, $store_name, 'cut') . '
  <div class="cut-type">' . esc_html($cutLabel) . '</div>
  ' . $cutSubLabel . '
  ' . $cutMetaHtml . '
  <div class="meta">
    <span>Sesión: #' . esc_html((string) $sessionId) . '</span>
    <span>Apertura: ' . esc_html($openedAt) . '</span>
    <span>Generado: ' . esc_html($cutAt) . '</span>
    <span>Cajero: ' . $cashierName . '</span>
  </div>
  <hr class="sep">
  <span class="section-title">EFECTIVO</span>
  <table class="totals">
    <tr>
      <td class="label">Apertura:</td>
      <td class="amount">$' . $openingAmount . '</td>
    </tr>
    <tr>
      <td class="label">Ventas efectivo:</td>
      <td class="amount">$' . $salesCashIn . '</td>
    </tr>
    <tr>
      <td class="label">Cambio ventas:</td>
      <td class="amount">-$' . $salesChangeOut . '</td>
    </tr>
    <tr>
      <td class="label">Ingresos manuales:</td>
      <td class="amount">$' . $manualCashIn . '</td>
    </tr>
    <tr>
      <td class="label">Salidas manuales:</td>
      <td class="amount">-$' . $manualCashOut . '</td>
    </tr>
    <tr>
      <td class="label">Entradas totales:</td>
      <td class="amount">$' . $cashIn . '</td>
    </tr>
    <tr>
      <td class="label">Salidas totales:</td>
      <td class="amount">-$' . $cashOut . '</td>
    </tr>
    <tr class="total-final">
      <td class="label">Esperado:</td>
      <td class="amount">$' . $expectedCash . '</td>
    </tr>
  </table>
  ' . $closingSection . '
  ' . $couponSection . '
  ' . $cardFeeSection . '
  <hr class="sep">
  <span class="section-title">VENTAS</span>
  <table class="totals">
    <tr>
      <td class="label">Cobradas:</td>
      <td class="amount">$' . $collectedSales . '</td>
    </tr>
    <tr>
      <td class="label">Tarjeta:</td>
      <td class="amount">$' . $cardSales . '</td>
    </tr>
    <tr>
      <td class="label">Devoluciones:</td>
      <td class="amount">-$' . $refundsTotal . '</td>
    </tr>
  </table>
  ' . $discountSection . '
  <table class="totals">
    <tr class="total-final">
      <td class="label">NETO:</td>
      <td class="amount">$' . $netAfter . '</td>
    </tr>
  </table>
  <span>Tickets: ' . esc_html((string) $countOrders) . ' | Dev: ' . esc_html((string) $countRefunds) . ' | Canc: ' . esc_html((string) $countCanc) . '</span>
  ' . $mixedSection . '
  <hr class="sep">
  <span class="section-title">PAGOS</span>
  <table class="totals">
    ' . $byMethodRows . '
  </table>
  <hr class="sep">
  <div class="footer">' . esc_html($this->get_cut_footer($ticket_settings, $cutLabel)) . '</div>
  ' . $this->get_rootlabs_credit_html() . '
</body>
</html>';

        return $html;
    }

    // ─── Shared helpers ─────────────────────────────────────────────

    private function get_store_name(): string
    {
        $settings = $this->get_ticket_settings();

        if ($settings['business_name'] !== '') {
            return $settings['business_name'];
        }

        $name = get_bloginfo('name');

        return $name !== '' ? $name : __('Tienda', 'mx-pos-pro');
    }

    private function get_session_pos_employee_name(int $sessionId): string
    {
        if ($sessionId <= 0) {
            return '';
        }

        global $wpdb;

        $sessionsTable = $wpdb->prefix . 'mx_pos_sessions';
        $employeesTable = $wpdb->prefix . 'mx_pos_employees';

        $name = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT e.display_name
                 FROM `{$sessionsTable}` s
                 LEFT JOIN `{$employeesTable}` e ON e.id = s.pos_employee_id
                 WHERE s.id = %d
                 LIMIT 1",
                $sessionId
            )
        );

        return is_string($name) ? trim($name) : '';
    }

    private function get_order_cashier_name(\WC_Order $order): string
    {
        foreach (['_mx_pos_employee_name', '_mx_pos_cashier_name', '_yith_pos_cashier'] as $key) {
            $value = $order->get_meta($key, true);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
    private function get_cashier_name(int $cashier_id): string
    {
        if ($cashier_id <= 0) {
            return '';
        }

        $user = get_userdata($cashier_id);

        if (! $user instanceof \WP_User) {
            return '';
        }

        return $user->display_name;
    }

    private function get_customer_name(\WC_Order $order): string
    {
        $first = $order->get_billing_first_name();
        $last  = $order->get_billing_last_name();

        if ($first !== '' && $last !== '') {
            return $first . ' ' . $last;
        }

        if ($first !== '') {
            return $first;
        }

        $customer_name = $order->get_meta('_mx_pos_customer_name', true);

        if (is_string($customer_name) && $customer_name !== '') {
            return $customer_name;
        }

        return '';
    }

    private function build_items_rows(\WC_Order $order, bool $includePrice = true): string
    {
        $rows = '';
        $items = $order->get_items('line_item');

        foreach ($items as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $name = $item->get_name();
            $qty = (int) $item->get_quantity();
            $line_total = (float) $item->get_total();
            $line_subtotal = (float) $item->get_subtotal();
            $unit_price = $qty > 0 ? $line_subtotal / $qty : $line_total;
            $product = $item->get_product();
            $sku = $product instanceof \WC_Product ? $product->get_sku() : '';
            $variations = $this->build_variation_labels($item);

            $nameCell = esc_html($name);
            if ($sku !== '') {
                $nameCell .= '<span class="item-sku">' . esc_html__('SKU:', 'mx-pos-pro') . ' ' . esc_html($sku) . '</span>';
            }
            if ($variations !== '') {
                $nameCell .= '<span class="item-variation">' . $variations . '</span>';
            }

            if ($includePrice) {
                $rows .= '
    <div class="item">
      <div class="item-name">' . $nameCell . '</div>
      <div class="item-detail">
        <span>' . esc_html((string) $qty) . ' x $' . esc_html(number_format($unit_price, 2, '.', ',')) . '</span>
        <span class="item-total">$' . esc_html(number_format($line_total, 2, '.', ',')) . '</span>
      </div>
    </div>';
            } else {
                $rows .= '
    <div class="item">
      <div class="item-name">' . $nameCell . '</div>
      <div class="item-detail">
        <span>' . esc_html__('Cantidad:', 'mx-pos-pro') . ' ' . esc_html((string) $qty) . '</span>
      </div>
    </div>';
            }
        }

        return $rows;
    }

    private function build_gift_items_rows(\WC_Order $order): string
    {
        return $this->build_items_rows($order, false);
    }

    private function build_variation_labels(\WC_Order_Item_Product $item): string
    {
        $formatted = $item->get_formatted_meta_data('_');

        if (empty($formatted)) {
            return '';
        }

        $labels = [];
        foreach ($formatted as $meta) {
            $key = $meta->key ?? '';
            $value = $meta->display_value ?? $meta->value ?? '';

            if ($key === '' || $value === '') {
                continue;
            }

            $labels[] = esc_html($key . ': ' . $value);
        }

        return implode(', ', $labels);
    }

    private function build_totals(\WC_Order $order): array
    {
        $subtotal = (float) $order->get_subtotal();

        $global_discount_total = 0.0;
        foreach ($order->get_fees() as $fee) {
            if ($fee->get_meta('_mx_pos_is_pos_discount', true) !== 'yes') {
                continue;
            }

            $global_discount_total += abs((float) $fee->get_total());
        }

        $line_discount_total = $this->resolve_line_discount_total($order);
        $discount_total = $global_discount_total + $line_discount_total;

        $couponTotal  = 0.0;
        $couponCodes  = [];
        foreach ($order->get_coupons() as $couponItem) {
            $couponTotal += (float) $couponItem->get_discount();
            $couponCodes[] = $couponItem->get_code();
        }

        $tax_total = (float) $order->get_total_tax();
        $total = (float) $order->get_total();

        return [
            'subtotal'       => number_format($subtotal, 2, '.', ','),
            'discount_total' => number_format($discount_total, 2, '.', ','),
            'line_discount_total' => number_format($line_discount_total, 2, '.', ','),
            'global_discount_total' => number_format($global_discount_total, 2, '.', ','),
            'coupon_total'   => number_format($couponTotal, 2, '.', ','),
            'coupon_codes'   => $couponCodes,
            'tax_total'      => number_format($tax_total, 2, '.', ','),
            'total'          => number_format($total, 2, '.', ','),
        ];
    }


    private function resolve_line_discount_total(\WC_Order $order): float
    {
        $orderMetaTotal = $order->get_meta('_mx_pos_line_discount_total', true);

        if (is_numeric($orderMetaTotal) && (float) $orderMetaTotal > 0) {
            return (float) $orderMetaTotal;
        }

        $lineDiscountTotal = 0.0;

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $lineDiscount = $item->get_meta('_mx_pos_line_discount_amount', true);

            if (is_numeric($lineDiscount) && (float) $lineDiscount > 0) {
                $lineDiscountTotal += (float) $lineDiscount;
            }
        }

        return $lineDiscountTotal;
    }
    private function build_payment_section(array $sale): string
    {
        $payment_summary = $sale['payment_summary'] ?? null;

        if (! is_string($payment_summary) || trim($payment_summary) === '') {
            return '<div class="payment"><span class="payment-title">' . esc_html__('Pago', 'mx-pos-pro') . '</span><div class="payment-row"><span>' . esc_html__('Pendiente', 'mx-pos-pro') . '</span></div></div>';
        }

        $decoded = json_decode($payment_summary, true);

        if (! is_array($decoded)) {
            return '<div class="payment"><span class="payment-title">' . esc_html__('Pago', 'mx-pos-pro') . '</span><div class="payment-row"><span>' . esc_html__('Pendiente', 'mx-pos-pro') . '</span></div></div>';
        }

        $payment = $decoded['payment'] ?? null;

        if (! is_array($payment)) {
            return '<div class="payment"><span class="payment-title">' . esc_html__('Pago', 'mx-pos-pro') . '</span><div class="payment-row"><span>' . esc_html__('Pendiente', 'mx-pos-pro') . '</span></div></div>';
        }

        $html = '<div class="payment"><span class="payment-title">' . esc_html__('Pago', 'mx-pos-pro') . '</span>';

        $hasLines = ! empty($payment['payment_lines']) && is_array($payment['payment_lines']);

        if ($hasLines) {
            foreach ($payment['payment_lines'] as $line) {
                $lineMethod = $line['method_name'] ?? $line['method'] ?? '';
                $lineAmount = isset($line['amount']) ? (float) $line['amount'] : 0;
                $lineRef    = $line['reference'] ?? null;

                $html .= '<div class="payment-row"><span>' . esc_html($lineMethod) . '</span><span class="amount">$' . esc_html(number_format($lineAmount, 2, '.', ',')) . '</span></div>';

                if (is_string($lineRef) && $lineRef !== '') {
                    $html .= '<span class="payment-ref">' . esc_html__('Ref:', 'mx-pos-pro') . ' ' . esc_html($lineRef) . '</span>';
                }
            }

            $cardFeeTotal = isset($payment['card_fee_total']) ? (float) $payment['card_fee_total'] : 0;
            if ($cardFeeTotal > 0) {
                $html .= '<div class="payment-row"><span>' . esc_html__('Comisión TDC', 'mx-pos-pro') . '</span><span class="amount">$' . esc_html(number_format($cardFeeTotal, 2, '.', ',')) . '</span></div>';
            }

            $change = isset($payment['change']) ? (float) $payment['change'] : 0;
            if ($change > 0) {
                $html .= '<div class="payment-row"><span>' . esc_html__('Cambio', 'mx-pos-pro') . '</span><span class="amount">$' . esc_html(number_format($change, 2, '.', ',')) . '</span></div>';
            }
        } else {
            $method_labels = [
                'cash' => __('Efectivo', 'mx-pos-pro'),
                'card' => __('Tarjeta', 'mx-pos-pro'),
            ];

            $method = $payment['method'] ?? '';
            $method_label = isset($method_labels[$method]) ? $method_labels[$method] : $method;

            $html .= '<div class="payment-row"><span>' . esc_html($method_label) . '</span></div>';

            if ($method === 'cash') {
                $amount_received = $payment['amount_received'] ?? '0';
                $change = $payment['change'] ?? '0';

                $html .= '<div class="payment-row"><span>' . esc_html__('Recibido', 'mx-pos-pro') . '</span><span class="amount">$' . esc_html($amount_received) . '</span></div>';
                $html .= '<div class="payment-row"><span>' . esc_html__('Cambio', 'mx-pos-pro') . '</span><span class="amount">$' . esc_html($change) . '</span></div>';
            }

            if ($method === 'card') {
                $card_reference = $payment['card_reference'] ?? null;

                if (is_string($card_reference) && $card_reference !== '') {
                    $html .= '<span class="payment-ref">' . esc_html__('Ref:', 'mx-pos-pro') . ' ' . esc_html($card_reference) . '</span>';
                }
            }
        }

        $html .= '</div>';

        return $html;
    }

    private function get_cut_logo_html(array $ticket_settings, string $store_name): string
    {
        if (! $this->has_visible_logo($ticket_settings, 'cut')) {
            return '';
        }

        return '<div class="logo"><img src="' . esc_url($ticket_settings['logo_url']) . '" alt="' . esc_attr($store_name) . '" /></div>';
    }

    private function get_cut_footer(array $ticket_settings, string $cutLabel): string
    {
        if ($ticket_settings['footer_text'] !== '') {
            return $ticket_settings['footer_text'];
        }

        return 'MX POS Pro — ' . $cutLabel;
    }

    private function format_date(string $date_str): string
    {
        $timestamp = strtotime($date_str);

        if ($timestamp === false) {
            return $date_str;
        }

        return wp_date('d/m/Y H:i', $timestamp);
    }
}
