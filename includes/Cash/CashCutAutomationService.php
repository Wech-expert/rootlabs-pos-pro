<?php

namespace MXPOSPro\Cash;

defined('ABSPATH') || exit;

use MXPOSPro\Sales\RefundRepository;
use MXPOSPro\Sales\SaleRepository;
use MXPOSPro\Sales\TicketService;

class CashCutAutomationService
{
    public function register(): void
    {
        add_action('mx_pos_cash_session_closed', [$this, 'generate_for_pos_close'], 20, 1);
        add_action('mx_pos_cash_session_closed_remote', [$this, 'generate_for_remote_close'], 20, 1);
    }

    public function generate_for_pos_close(array $context): void
    {
        $this->generate(
            (int) ($context['session_id'] ?? 0),
            (int) ($context['cashier_id'] ?? 0)
        );
    }

    public function generate_for_remote_close(array $context): void
    {
        $this->generate(
            (int) ($context['session_id'] ?? 0),
            (int) ($context['admin_user_id'] ?? 0)
        );
    }

    private function generate(int $sessionId, int $userId): void
    {
        if ($sessionId <= 0) {
            return;
        }

        $movementRepo = new CashMovementRepository();
        $service      = new CashCutService(
            new CashCutRepository(),
            new CashSessionService(new CashSessionRepository(), $movementRepo),
            $movementRepo,
            new SaleRepository(),
            new RefundRepository(),
            new TicketService()
        );

        $result = $service->generate_z($sessionId, $userId);

        if (is_wp_error($result)) {
        }
    }
}
