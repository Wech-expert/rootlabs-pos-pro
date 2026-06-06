<?php

namespace MXPOSPro\Notifications;

defined('ABSPATH') || exit;

class NotificationFormatter
{
    public static function session_opened(array $context): string
    {
        $displayName = $context['cashier_name'] ?? self::resolve_display_name($context['cashier_id'] ?? 0);
        $openingAmount = self::format_currency($context['opening_amount'] ?? '0.0000');
        $sessionId     = (int) ($context['session_id'] ?? 0);

        $registerName = '';
        if (isset($context['pos_register_id']) && $context['pos_register_id'] > 0) {
            $registerRepo = new \MXPOSPro\Entities\RegisterRepository();
            $register = $registerRepo->get_by_id((int) $context['pos_register_id']);
            if ($register) {
                $registerName = $register['name'];
            }
        }
        
        $denomMapping = [
            'bill-1000' => '$1000',
            'bill-500'  => '$500',
            'bill-200'  => '$200',
            'bill-100'  => '$100',
            'bill-50'   => '$50',
            'bill-20'   => '$20',
            'coin-20'   => '$20',
            'coin-10'   => '$10',
            'coin-5'    => '$5',
            'coin-2'    => '$2',
            'coin-1'    => '$1',
            'coin-050'  => '$0.50',
        ];

        $denominationsText = '';
        if (!empty($context['denominations_json'])) {
            $denominations = json_decode($context['denominations_json'], true);
            if (is_array($denominations) && !empty($denominations)) {
                $denomsList = [];
                foreach ($denominations as $key => $qty) {
                    if ((int)$qty > 0) {
                        $label = $denomMapping[$key] ?? $key;
                        $denomsList[] = str_pad($label, 6) . ' : ' . $qty . ' pz';
                    }
                }
                if (!empty($denomsList)) {
                    $denominationsText = "\n\nDESGLOSE DE EFECTIVO\n" . implode("\n", $denomsList);
                }
            }
        }

        $dateStr = wp_date('d/m/Y, h:i:s a', current_time('timestamp'));
        
        $cajaLine = $registerName !== '' ? "\nCaja: " . $registerName : '';

        return sprintf(
            "<pre><code>APERTURA DE CAJA - %s%s\n%s%s\n\nRESUMEN\nTotal Contado: %s\nSesión: #%d</code></pre>",
            $displayName,
            $cajaLine,
            $dateStr,
            $denominationsText,
            $openingAmount,
            $sessionId
        );
    }

    public static function session_closed(array $context): string
    {
        $displayName = $context['cashier_name'] ?? self::resolve_display_name($context['cashier_id'] ?? 0);
        $expected    = self::format_currency($context['expected_amount'] ?? '0.0000');
        $counted     = self::format_currency($context['counted_amount'] ?? '0.0000');
        $difference  = self::format_currency($context['difference'] ?? '0.0000');
        
        $opening     = self::format_currency($context['opening_amount'] ?? '0.0000');
        $salesCash   = self::format_currency($context['sales_cash'] ?? '0.0000');
        $salesCard   = self::format_currency($context['sales_card'] ?? '0.0000');
        
        $netSales = (float)($context['sales_cash'] ?? '0') + (float)($context['sales_card'] ?? '0');
        $netSalesFormatted = self::format_currency((string)$netSales);

        $diffFloat = (float)($context['difference'] ?? '0');
        if ($diffFloat > 0) {
            $resultado = "Sobrante {$difference}";
        } elseif ($diffFloat < 0) {
            $resultado = "Faltante {$difference}";
        } else {
            $resultado = "Todo perfecto $0.00";
        }

        $denomMapping = [
            'bill-1000' => '$1000',
            'bill-500'  => '$500',
            'bill-200'  => '$200',
            'bill-100'  => '$100',
            'bill-50'   => '$50',
            'bill-20'   => '$20',
            'coin-20'   => '$20',
            'coin-10'   => '$10',
            'coin-5'    => '$5',
            'coin-2'    => '$2',
            'coin-1'    => '$1',
            'coin-050'  => '$0.50',
        ];

        $denominationsText = '';
        if (!empty($context['denominations_json'])) {
            $denominations = json_decode($context['denominations_json'], true);
            if (is_array($denominations) && !empty($denominations)) {
                $denomsList = [];
                foreach ($denominations as $key => $qty) {
                    if ((int)$qty > 0) {
                        $label = $denomMapping[$key] ?? $key;
                        $denomsList[] = str_pad($label, 6) . ' : ' . $qty . ' pz';
                    }
                }
                if (!empty($denomsList)) {
                    $denominationsText = "\n\nDESGLOSE DE EFECTIVO\n" . implode("\n", $denomsList);
                }
            }
        }

        $dateStr = wp_date('d/m/Y, h:i:s a', current_time('timestamp'));

        return sprintf(
            "<pre><code>ARQUEO DE CAJA - %s\n%s%s\n\nRESUMEN\nTotal Contado: %s\nVenta del Día: %s\nResultado: %s\n\nDetalles del Cierre:\nFondo Inicial: %s\nVentas Efectivo: %s\nVentas Tarjeta: %s\nTotal Esperado: %s</code></pre>",
            $displayName,
            $dateStr,
            $denominationsText,
            $counted,
            $netSalesFormatted,
            $resultado,
            $opening,
            $salesCash,
            $salesCard,
            $expected
        );
    }

    public static function difference_detected(array $context): string
    {
        $sessionId  = (int) ($context['session_id'] ?? 0);
        $difference = self::format_currency($context['difference'] ?? '0.0000');
        $closeNote  = self::truncate_note($context['close_note'] ?? '');

        $noteLine = $closeNote !== '' ? "\nNota: {$closeNote}" : '';

        return sprintf(
            "%s Diferencia detectada\nSesión: #%d\nDiferencia: %s%s",
            html_entity_decode('&#x26A0;&#xFE0F;', ENT_QUOTES, 'UTF-8'),
            $sessionId,
            $difference,
            $noteLine
        );
    }

    public static function sale_cancelled(array $context): string
    {
        $displayName = self::resolve_display_name($context['cashier_id'] ?? 0);
        $orderNumber = $context['order_number'] ?? '';
        $reason      = $context['reason'] ?? '';

        $reasonLine = $reason !== '' ? "\nMotivo: {$reason}" : '';

        return sprintf(
            "%s Venta cancelada\nOrden: #%s\nCajero: %s%s",
            html_entity_decode('&#x274C;', ENT_QUOTES, 'UTF-8'),
            $orderNumber,
            $displayName,
            $reasonLine
        );
    }

    public static function sale_refunded(array $context): string
    {
        $displayName  = self::resolve_display_name($context['cashier_id'] ?? 0);
        $orderNumber  = $context['order_number'] ?? '';
        $amount       = self::format_currency($context['refund_amount'] ?? '0.0000');
        $method       = ($context['refund_method'] ?? '') === 'cash'
            ? 'Efectivo'
            : 'Tarjeta';

        return sprintf(
            "%s Devolución registrada\nOrden: #%s\nMonto: %s\nMétodo: %s\nCajero: %s",
            html_entity_decode('&#x21A9;&#xFE0F;', ENT_QUOTES, 'UTF-8'),
            $orderNumber,
            $amount,
            $method,
            $displayName
        );
    }

    public static function cut_z_generated(array $context): string
    {
        $sessionId      = (int) ($context['session_id'] ?? 0);
        $summary        = $context['summary'] ?? [];
        $netAfterRefunds = self::format_currency($summary['net_after_refunds'] ?? '0.0000');
        $difference     = '';

        if (isset($summary['closing']['difference'])) {
            $difference = "\nDiferencia: " . self::format_currency($summary['closing']['difference']);
        }

        return sprintf(
            "%s Cierre de caja generado\nSesión: #%d\nVentas netas: %s%s",
            html_entity_decode('&#x1F4C4;', ENT_QUOTES, 'UTF-8'),
            $sessionId,
            $netAfterRefunds,
            $difference
        );
    }

    private static function resolve_display_name(mixed $userId): string
    {
        $id = (int) $userId;

        if ($id <= 0) {
            return '';
        }

        $user = get_userdata($id);

        if (! $user instanceof \WP_User) {
            return '';
        }

        return $user->display_name;
    }

    private static function format_currency(string $amount): string
    {
        $float = (float) $amount;
        $formatted = number_format(abs($float), 2, '.', ',');
        $sign = $float < 0 ? '-' : ($float > 0 ? '+' : '');

        return '$' . $sign . $formatted;
    }

    private static function truncate_note(string $note, int $maxLen = 100): string
    {
        $trimmed = trim($note);

        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) <= $maxLen) {
            return $trimmed;
        }

        return mb_substr($trimmed, 0, $maxLen - 3) . '...';
    }
}
