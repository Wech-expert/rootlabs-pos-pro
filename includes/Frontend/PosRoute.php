<?php



/**
 * Frontend POS route processes login and cash-count forms.
 * Password values are intentionally not passed through text sanitizers because doing so
 * could alter valid password characters before authentication.
 *
 * Denomination arrays are normalized by the POS cash-count flow before persistence.
 *
 * rootlabs-pos-pro-w3-posroute-form-fields
 *
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 */

/**
 * Request superglobals are checked/sanitized before operational use.
 *
 * rootlabs-pos-pro-w2a-request-superglobals
 *
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
 */

namespace MXPOSPro\Frontend;

defined('ABSPATH') || exit;

use MXPOSPro\Auth\POSAuthService;
use MXPOSPro\Cash\CashSessionRepository;
use MXPOSPro\Cash\CashSessionService;
use MXPOSPro\Cash\CashMovementRepository;
use MXPOSPro\Entities\BranchRepository;
use MXPOSPro\Entities\EmployeeRepository;
use MXPOSPro\Entities\RegisterRepository;

class PosRoute
{
    public const QUERY_VAR = 'mx_pos_route';
    public const ROUTE_VALUE = 'pos';

    private const DENOMINATION_VALUES = [
        'bill-1000' => 100000,
        'bill-500'  => 50000,
        'bill-200'  => 20000,
        'bill-100'  => 10000,
        'bill-50'   => 5000,
        'bill-20'   => 2000,
        'coin-20'   => 2000,
        'coin-10'   => 1000,
        'coin-5'    => 500,
        'coin-2'    => 200,
        'coin-1'    => 100,
        'coin-050'  => 50,
    ];

    public function register(): void
    {
        add_action('init', [self::class, 'register_rewrite_rules']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('template_redirect', [$this, 'render']);
    }

    public static function register_rewrite_rules(): void
    {
        add_rewrite_rule(
            '^pos/?$',
            'index.php?' . self::QUERY_VAR . '=' . self::ROUTE_VALUE,
            'top'
        );
    }

    public function register_query_vars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public function render(): void
    {
        if (get_query_var(self::QUERY_VAR) !== self::ROUTE_VALUE) {
            return;
        }

        $authService = new POSAuthService(new EmployeeRepository());

        $logout_url = wp_nonce_url(
            home_url('/pos?mx_pos_action=logout'),
            'mx_pos_logout',
            'mx_pos_logout_nonce'
        );

        // ── Logout ──────────────────────────────────

        if (
            isset($_GET['mx_pos_action'])
            && $_GET['mx_pos_action'] === 'logout'
        ) {
            $nonce = isset($_GET['mx_pos_logout_nonce'])
                ? sanitize_text_field(wp_unslash($_GET['mx_pos_logout_nonce']))
                : '';

            if ($nonce !== '' && wp_verify_nonce($nonce, 'mx_pos_logout')) {
                $authService->logout();
            }

            wp_safe_redirect(home_url('/pos'));
            exit;
        }

        // ── Login POST ─────────────────────────────

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['mx_pos_login'])
        ) {
            $nonce = isset($_POST['mx_pos_login_nonce'])
                ? sanitize_text_field(wp_unslash($_POST['mx_pos_login_nonce']))
                : '';

            if ($nonce === '' || ! wp_verify_nonce($nonce, 'mx_pos_login')) {
                $login_error  = true;
                $username_esc = '';
                require MX_POS_PRO_DIR . 'templates/frontend/pos-login.php';
                exit;
            }

            $username = isset($_POST['mx_pos_username'])
                ? sanitize_user(wp_unslash($_POST['mx_pos_username']), true)
                : '';

            $password = isset($_POST['mx_pos_password'])
                ? wp_unslash($_POST['mx_pos_password'])
                : '';

            $result = $authService->login($username, $password);

            if (is_wp_error($result)) {
                $username_esc = esc_attr($username);

                if ($result->get_error_code() === 'mx_pos_employee_locked') {
                    $locked_minutes = 5;
                    $error_msg      = $result->get_error_message();

                    if (preg_match('/(\d+)/', $error_msg, $matches)) {
                        $locked_minutes = max(1, (int) $matches[1]);
                    }

                    require MX_POS_PRO_DIR . 'templates/frontend/pos-login.php';
                    exit;
                }

                $login_error = true;
                require MX_POS_PRO_DIR . 'templates/frontend/pos-login.php';
                exit;
            }

            wp_safe_redirect(home_url('/pos'));
            exit;
        }

        // ── Register selection POST ────────────────

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['mx_pos_select_register'])
        ) {
            $employee = $authService->validate();

            if ($employee === null) {
                wp_safe_redirect(home_url('/pos'));
                exit;
            }

            $nonce = isset($_POST['mx_pos_select_register_nonce'])
                ? sanitize_text_field(wp_unslash($_POST['mx_pos_select_register_nonce']))
                : '';

            if ($nonce === '' || ! wp_verify_nonce($nonce, 'mx_pos_select_register')) {
                $open_cash_error = __('Solicitud inválida. Intente de nuevo.', 'mx-pos-pro');
                $registers       = (new RegisterRepository())->get_active_registers_for_selection();

                require MX_POS_PRO_DIR . 'templates/frontend/pos-open-cash.php';
                exit;
            }

            $pos_register_id = isset($_POST['pos_register_id'])
                ? (int) $_POST['pos_register_id']
                : 0;

            $registerRepo = new RegisterRepository();
            $register     = $registerRepo->get_by_id($pos_register_id);

            if ($register === null || ! (int) $register['is_active']) {
                $open_cash_error      = __('La caja seleccionada no está disponible.', 'mx-pos-pro');
                $registers            = $registerRepo->get_active_registers_for_selection();
                $selected_register_id = '';

                require MX_POS_PRO_DIR . 'templates/frontend/pos-open-cash.php';
                exit;
            }

            $branch_id = (int) $register['branch_id'];

            $branchRepo = new BranchRepository();
            $branch     = $branchRepo->get_by_id($branch_id);

            if ($branch === null || ! (int) $branch['is_active']) {
                $open_cash_error      = __('La sucursal de esta caja no está activa.', 'mx-pos-pro');
                $registers            = $registerRepo->get_active_registers_for_selection();
                $selected_register_id = (string) $pos_register_id;

                require MX_POS_PRO_DIR . 'templates/frontend/pos-open-cash.php';
                exit;
            }

            $sessionRepo  = new CashSessionRepository();
            $registerOpen = $sessionRepo->find_open_by_register($pos_register_id);

            if ($registerOpen !== null) {
                $open_cash_error      = __('La caja seleccionada ya tiene una sesión abierta.', 'mx-pos-pro');
                $registers            = $registerRepo->get_active_registers_for_selection();
                $selected_register_id = (string) $pos_register_id;

                require MX_POS_PRO_DIR . 'templates/frontend/pos-open-cash.php';
                exit;
            }

            $employeeOpen = $sessionRepo->find_open_by_pos_employee((int) $employee['id']);

            if ($employeeOpen !== null) {
                $open_cash_error      = __('Ya tienes una sesión de caja abierta.', 'mx-pos-pro');
                $registers            = $registerRepo->get_active_registers_for_selection();
                $selected_register_id = (string) $pos_register_id;

                require MX_POS_PRO_DIR . 'templates/frontend/pos-open-cash.php';
                exit;
            }



            $authService->set_selected_register($pos_register_id, $branch_id);

            wp_safe_redirect(home_url('/pos'));
            exit;
        }

        // ── Cash count POST ───────────────────────

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['mx_pos_count_cash'])
        ) {
            $employee = $authService->validate();

            if ($employee === null) {
                wp_safe_redirect(home_url('/pos'));
                exit;
            }

            $nonce = isset($_POST['mx_pos_count_cash_nonce'])
                ? sanitize_text_field(wp_unslash($_POST['mx_pos_count_cash_nonce']))
                : '';

            if ($nonce === '' || ! wp_verify_nonce($nonce, 'mx_pos_count_cash')) {
                $count_cash_error = __('Solicitud inválida. Intente de nuevo.', 'mx-pos-pro');
                $selected_register = $this->build_selected_register_context(
                    $authService,
                    new RegisterRepository(),
                    new BranchRepository()
                );

                $denom_values = isset($_POST['denominations']) && is_array($_POST['denominations'])
                    ? $this->sanitize_denomination_input($_POST['denominations'])
                    : [];

                require MX_POS_PRO_DIR . 'templates/frontend/pos-count-cash.php';
                exit;
            }

            $selectedReg = $authService->get_selected_register();

            if ($selectedReg === null) {
                wp_safe_redirect(home_url('/pos'));
                exit;
            }

            $pos_register_id = $selectedReg['pos_register_id'];
            $branch_id       = $selectedReg['branch_id'];

            $registerRepo = new RegisterRepository();
            $register     = $registerRepo->get_by_id($pos_register_id);

            if ($register === null || ! (int) $register['is_active']) {
                $count_cash_error   = __('La caja seleccionada ya no está disponible.', 'mx-pos-pro');
                $selected_register  = $this->build_selected_register_context(
                    $authService, $registerRepo, new BranchRepository()
                );
                $denom_values = isset($_POST['denominations']) && is_array($_POST['denominations'])
                    ? $this->sanitize_denomination_input($_POST['denominations'])
                    : [];

                require MX_POS_PRO_DIR . 'templates/frontend/pos-count-cash.php';
                exit;
            }

            $branchRepo = new BranchRepository();
            $branch     = $branchRepo->get_by_id($branch_id);

            if ($branch === null || ! (int) $branch['is_active']) {
                $count_cash_error   = __('La sucursal ya no está activa.', 'mx-pos-pro');
                $selected_register  = $this->build_selected_register_context(
                    $authService, $registerRepo, $branchRepo
                );
                $denom_values = isset($_POST['denominations']) && is_array($_POST['denominations'])
                    ? $this->sanitize_denomination_input($_POST['denominations'])
                    : [];

                require MX_POS_PRO_DIR . 'templates/frontend/pos-count-cash.php';
                exit;
            }

            $sessionRepo  = new CashSessionRepository();
            $registerOpen = $sessionRepo->find_open_by_register($pos_register_id);

            if ($registerOpen !== null) {
                $count_cash_error   = __('La caja seleccionada ya tiene una sesión abierta.', 'mx-pos-pro');
                $selected_register  = $this->build_selected_register_context(
                    $authService, $registerRepo, $branchRepo
                );
                $denom_values = isset($_POST['denominations']) && is_array($_POST['denominations'])
                    ? $this->sanitize_denomination_input($_POST['denominations'])
                    : [];

                require MX_POS_PRO_DIR . 'templates/frontend/pos-count-cash.php';
                exit;
            }

            $employeeOpen = $sessionRepo->find_open_by_pos_employee((int) $employee['id']);

            if ($employeeOpen !== null) {
                $count_cash_error   = __('Ya tienes una sesión de caja abierta.', 'mx-pos-pro');
                $selected_register  = $this->build_selected_register_context(
                    $authService, $registerRepo, $branchRepo
                );
                $denom_values = isset($_POST['denominations']) && is_array($_POST['denominations'])
                    ? $this->sanitize_denomination_input($_POST['denominations'])
                    : [];

                require MX_POS_PRO_DIR . 'templates/frontend/pos-count-cash.php';
                exit;
            }



            $denominations_input = isset($_POST['denominations']) && is_array($_POST['denominations'])
                ? $_POST['denominations']
                : [];

            list($valid, $denom_total_cents, $denom_quantities) = $this->validate_denominations($denominations_input);

            if (! $valid || $denom_total_cents <= 0) {
                $count_cash_error   = __('El fondo inicial debe ser mayor a cero.', 'mx-pos-pro');
                $selected_register  = $this->build_selected_register_context(
                    $authService, $registerRepo, $branchRepo
                );
                $denom_values = $this->sanitize_denomination_input($denominations_input);

                require MX_POS_PRO_DIR . 'templates/frontend/pos-count-cash.php';
                exit;
            }

            $opening_amount     = $this->centsToDecimal($denom_total_cents);
            $denominations_json = wp_json_encode($denom_quantities);

            $sessionService = new CashSessionService(
                new CashSessionRepository(),
                new CashMovementRepository()
            );

            $result = $sessionService->open_session_for_pos_employee(
                (int) $employee['id'],
                $pos_register_id,
                $branch_id,
                $opening_amount,
                $denominations_json
            );

            if (is_wp_error($result)) {
                $count_cash_error   = $result->get_error_message();
                $selected_register  = $this->build_selected_register_context(
                    $authService, $registerRepo, $branchRepo
                );
                $denom_values = $this->sanitize_denomination_input($denominations_input);

                require MX_POS_PRO_DIR . 'templates/frontend/pos-count-cash.php';
                exit;
            }

            $authService->clear_selected_register();

            wp_safe_redirect(home_url('/pos'));
            exit;
        }

        // ── Session check (GET) ────────────────────

        $employee = $authService->validate();

        if ($employee === null) {
            require MX_POS_PRO_DIR . 'templates/frontend/pos-login.php';
            exit;
        }
// ── Check cash session / register context ──

        $sessionRepo = new CashSessionRepository();
        $session     = $sessionRepo->find_open_by_pos_employee((int) $employee['id']);

        if ($session !== null) {
            require MX_POS_PRO_DIR . 'templates/frontend/pos-shell.php';
            exit;
        }

        $selectedReg = $authService->get_selected_register();

        if ($selectedReg !== null) {
            $registerRepo = new RegisterRepository();
            $register     = $registerRepo->get_by_id($selectedReg['pos_register_id']);

            if ($register !== null && (int) $register['is_active']) {
                $branchRepo = new BranchRepository();
                $branch     = $branchRepo->get_by_id($selectedReg['branch_id']);

                $selected_register = [
                    'register_name' => $register['name'],
                    'branch_name'   => $branch !== null ? $branch['name'] : '',
                ];

                require MX_POS_PRO_DIR . 'templates/frontend/pos-count-cash.php';
                exit;
            }

            $authService->clear_selected_register();
        }

        $registers = (new RegisterRepository())->get_active_registers_for_selection();

        require MX_POS_PRO_DIR . 'templates/frontend/pos-open-cash.php';
        exit;
    }

    // ── Private helpers ────────────────────────────────

    private function build_selected_register_context(
        POSAuthService $authService,
        RegisterRepository $registerRepo,
        BranchRepository $branchRepo
    ): array {
        $sel = $authService->get_selected_register();

        if ($sel === null) {
            return [
                'register_name' => '',
                'branch_name'   => '',
            ];
        }

        $register = $registerRepo->get_by_id($sel['pos_register_id']);
        $branch   = $branchRepo->get_by_id($sel['branch_id']);

        return [
            'register_name' => $register !== null ? $register['name'] : '',
            'branch_name'   => $branch !== null ? $branch['name'] : '',
        ];
    }

    private function sanitize_denomination_input(array $input): array
    {
        $sanitized = [];

        foreach (array_keys(self::DENOMINATION_VALUES) as $key) {
            if (isset($input[$key])) {
                $qty = (int) $input[$key];
                if ($qty > 0) {
                    $sanitized[$key] = (string) $qty;
                }
            }
        }

        return $sanitized;
    }

    private function validate_denominations(array $input): array
    {
        $quantities = [];
        $total_cents = 0;

        foreach (self::DENOMINATION_VALUES as $key => $cent_value) {
            $qty = isset($input[$key]) ? (int) $input[$key] : 0;

            if ($qty < 0) {
                return [false, 0, []];
            }

            $quantities[$key] = $qty;
            $total_cents     += $qty * $cent_value;
        }

        return [true, $total_cents, $quantities];
    }

    private function centsToDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs  = abs($cents);
        $pesos    = intdiv($abs, 100);
        $fraccion = $abs % 100;

        return sprintf('%s%d.%02d0000', $sign, $pesos, $fraccion);
    }
}
