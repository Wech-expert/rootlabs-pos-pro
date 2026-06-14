<?php

namespace MXPOSPro\Admin;

defined('ABSPATH') || exit;

use MXPOSPro\Audit\AuditLogger;
use MXPOSPro\Cash\CashSessionRepository;
use MXPOSPro\Cash\CashMovementRepository;
use MXPOSPro\Cash\CashSessionService;
use MXPOSPro\Entities\BranchRepository;
use MXPOSPro\Entities\EmployeeRepository;
use MXPOSPro\Entities\RegisterRepository;
use MXPOSPro\Cash\CashCutRepository;
use MXPOSPro\Payments\PaymentMethodRepository;
use MXPOSPro\Products\ProductIndexRepository;

class AdminPage
{
    private const MENU_SLUG = 'mx-pos-pro';
    private const OLD_SETTINGS_SLUG = 'mx-pos-pro-settings';
    private const NONCE_ACTION = 'mx_pos_save_settings';
    private const NONCE_NAME = '_mx_pos_settings_nonce';

    private const BRANCH_NONCE_ACTION = 'mx_pos_manage_branch';
    private const BRANCH_NONCE_NAME = '_mx_pos_branch_nonce';

    private const REGISTER_NONCE_ACTION = 'mx_pos_manage_register';
    private const REGISTER_NONCE_NAME = '_mx_pos_register_nonce';

    private const EMPLOYEE_NONCE_ACTION = 'mx_pos_manage_employee';
    private const EMPLOYEE_NONCE_NAME = '_mx_pos_employee_nonce';

    private const PAYMENT_METHOD_NONCE_ACTION = 'mx_pos_manage_payment_method';
    private const PAYMENT_METHOD_NONCE_NAME = '_mx_pos_payment_method_nonce';

    private const VOID_SESSION_NONCE_ACTION = 'mx_pos_void_session';
    private const VOID_SESSION_NONCE_NAME = '_mx_pos_void_session_nonce';

    private const REMOTE_CLOSE_SESSION_NONCE_ACTION = 'mx_pos_remote_close_session';
    private const REMOTE_CLOSE_SESSION_NONCE_NAME = '_mx_pos_remote_close_session_nonce';

    private const PROTECTED_PAYMENT_METHOD_SLUGS = ['cash', 'card', 'mixed'];
    private const PAYMENT_METHOD_TYPES = ['cash', 'card', 'mixed', 'woocommerce', 'other'];

    private const MAX_ACTIVE_REGISTERS = 5;

    private const TABS = [
        'dashboard'      => 'Dashboard',
        'estado'         => 'Estado',
        'sucursales'     => 'Sucursales',
        'cajas'          => 'Cajas',
        'empleados'      => 'Empleados',
        'sesiones'       => 'Sesiones',
        'metodos_pago'   => 'Métodos de pago',
        'reportes'       => 'Reportes',
        'auditoria'      => 'Auditoría',
        'configuracion'  => 'Configuración',
    ];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu'], 99);
        add_action('admin_post_mx_pos_save_settings', [$this, 'handle_save']);
        add_action('admin_post_mx_pos_test_telegram', [$this, 'handle_test_telegram']);
        add_action('admin_post_mx_pos_manage_branch', [$this, 'handle_branch_action']);
        add_action('admin_post_mx_pos_manage_register', [$this, 'handle_register_action']);
        add_action('admin_post_mx_pos_manage_employee', [$this, 'handle_employee_action']);
        add_action('admin_post_mx_pos_manage_payment_method', [$this, 'handle_payment_method_action']);
        add_action('admin_post_mx_pos_void_session', [$this, 'handle_void_session']);
        add_action('admin_post_mx_pos_remote_close_session', [$this, 'handle_remote_close_session']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'handle_legacy_redirect']);
    }

    public function add_menu(): void
    {
        add_menu_page(
            esc_html__('Rootlabs Pos Pro', 'mx-pos-pro'),
            esc_html__('Rootlabs Pos Pro', 'mx-pos-pro'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render'],
            'dashicons-store',
            56
        );
    }

    public function handle_legacy_redirect(): void
    {
        global $pagenow;

        if ($pagenow !== 'admin.php') {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if ($page === self::OLD_SETTINGS_SLUG) {
            wp_safe_redirect(add_query_arg(
                ['page' => self::MENU_SLUG, 'tab' => 'configuracion'],
                admin_url('admin.php')
            ));
            exit;
        }
    }

    public function enqueue_assets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'toplevel_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_media();

        $css_path = MX_POS_PRO_DIR . 'assets/admin/settings.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'mx-pos-settings',
                MX_POS_PRO_ASSETS . 'admin/settings.css',
                [],
                (string) filemtime($css_path)
            );
        }

        $js_path = MX_POS_PRO_DIR . 'assets/admin/settings.js';
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'mx-pos-settings',
                MX_POS_PRO_ASSETS . 'admin/settings.js',
                ['jquery'],
                (string) filemtime($js_path),
                true
            );
        }
    }

    public function render(): void
    {
        if (! current_user_can('manage_options') && ! current_user_can('mx_pos_view_dashboard')) {
            wp_die(
                esc_html__('You do not have permission to access this page.', 'mx-pos-pro')
            );
        }

        $current_tab = $this->get_current_tab();

        $this->render_admin_notices();

        $visible_tabs = $this->get_visible_tabs();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Rootlabs Pos Pro', 'mx-pos-pro'); ?></h1>

            <nav class="nav-tab-wrapper">
                <?php foreach ($visible_tabs as $tab_id => $tab_label): ?>
                    <a href="<?php echo esc_url(add_query_arg('tab', $tab_id, admin_url('admin.php?page=' . self::MENU_SLUG))); ?>"
                       class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="tab-content" style="margin-top: 16px;">
                <?php $this->render_tab($current_tab); ?>
            </div>
        </div>
        <?php
    }

    private function get_current_tab(): string
    {
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'dashboard';

        $visible = $this->get_visible_tabs();

        if (! array_key_exists($tab, $visible)) {
            return 'dashboard';
        }

        return $tab;
    }

    private function get_visible_tabs(): array
    {
        $isAdmin = current_user_can('manage_options');
        $tabs    = [];

        if ($isAdmin || current_user_can('mx_pos_view_dashboard')) {
            $tabs['dashboard'] = 'Dashboard';
        }

        if ($isAdmin) {
            $tabs['estado']         = 'Estado';
            $tabs['sucursales']     = 'Sucursales';
            $tabs['cajas']          = 'Cajas';
            $tabs['empleados']      = 'Empleados';
            $tabs['sesiones']       = 'Sesiones';
            $tabs['metodos_pago']   = 'Métodos de pago';
            $tabs['reportes']       = 'Reportes';
            $tabs['configuracion']  = 'Configuración';
        }

        if ($isAdmin || current_user_can('mx_pos_view_audit')) {
            $tabs['auditoria'] = 'Auditoría';
        }

        return $tabs;
    }

    private function render_tab(string $tab): void
    {
        switch ($tab) {
            case 'dashboard':
                require_once MX_POS_PRO_INCLUDES . 'Reports/DashboardDataService.php';
                require_once MX_POS_PRO_INCLUDES . 'Admin/DashboardPage.php';
                \MXPOSPro\Admin\DashboardPage::render();
                break;
            case 'estado':
                $this->render_estado();
                break;
            case 'sucursales':
                $this->render_sucursales();
                break;
            case 'cajas':
                $this->render_cajas();
                break;
            case 'empleados':
                $this->render_empleados();
                break;
            case 'sesiones':
                $this->render_sesiones();
                break;
            case 'metodos_pago':
                $this->render_metodos_pago();
                break;
            case 'reportes':
                $this->render_reportes();
                break;
            case 'auditoria':
                \MXPOSPro\Admin\AuditPage::render();
                break;
            case 'configuracion':
                $this->render_configuracion();
                break;
        }
    }

    // ─── Estado ───────────────────────────────────────────────────────────────

    private function render_estado(): void
    {
        global $wpdb;

        $db_version = get_option('mx_pos_pro_db_version', '');
        $expected_db = defined('MX_POS_PRO_DB_VERSION') ? MX_POS_PRO_DB_VERSION : '—';

        $tables_exist = $this->check_all_tables_exist();

        $branch_repo    = new BranchRepository();
        $register_repo  = new RegisterRepository();
        $employee_repo  = new EmployeeRepository();
        $payment_repo   = new PaymentMethodRepository();
        $session_repo   = new CashSessionRepository();
        $product_repo   = new ProductIndexRepository();

        $main_branch    = $branch_repo->get_default();
        $main_register  = $register_repo->get_default();
        $seed_methods   = $payment_repo->get_all_active();
        $seed_slugs     = array_column($seed_methods, 'slug');
        $has_cash_seed  = in_array('cash', $seed_slugs, true);
        $has_card_seed  = in_array('card', $seed_slugs, true);
        $has_mixed_seed = in_array('mixed', $seed_slugs, true);

        $branches_total   = count($branch_repo->get_all_active());
        $registers_total  = $register_repo->count_active();
        $employees_total  = $employee_repo->count_active();
        $cashiers_count   = $employee_repo->count_by_role('cashier');
        $managers_count   = $employee_repo->count_by_role('manager');
        $methods_total    = count($seed_methods);
        $sessions_open    = $session_repo->count_by_status('open');
        $sessions_closed  = $session_repo->count_by_status('closed');
        $products_indexed = $product_repo->count();
        $last_indexed     = $product_repo->get_last_indexed_at();

        $sales_total    = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}mx_pos_sales`");
        $refunds_total  = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}mx_pos_refunds`");
        $cuts_total     = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}mx_pos_cash_cuts`");
        $parked_carts   = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}mx_pos_parked_carts` WHERE status = 'parked'"
        );

        $warnings = [];
        if (! $tables_exist) {
            $warnings[] = __('Faltan tablas en la base de datos. Ejecute la reactivación del plugin.', 'mx-pos-pro');
        }
        if ($db_version !== $expected_db) {
            $warnings[] = sprintf(
                __('La versión de base de datos (%s) no coincide con la esperada (%s).', 'mx-pos-pro'),
                $db_version ?: '—',
                $expected_db
            );
        }
        if (! $main_branch) {
            $warnings[] = __('Falta la Sucursal Principal. Ejecute la reactivación del plugin.', 'mx-pos-pro');
        }
        if (! $main_register) {
            $warnings[] = __('Falta la Caja Principal. Ejecute la reactivación del plugin.', 'mx-pos-pro');
        }
        if (! $has_cash_seed || ! $has_card_seed || ! $has_mixed_seed) {
            $warnings[] = __('Faltan métodos de pago base (cash/card/mixed). Ejecute la reactivación del plugin.', 'mx-pos-pro');
        }

        ?>
        <div class="mx-pos-section">
            <h2><?php esc_html_e('Estado del plugin', 'mx-pos-pro'); ?></h2>

            <?php if (! empty($warnings)): ?>
                <div class="notice notice-warning inline">
                    <ul style="margin: 4px 0; padding-left: 20px;">
                        <?php foreach ($warnings as $warning): ?>
                            <li><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <table class="widefat striped" style="max-width: 720px;">
                <tbody>
                    <tr>
                        <th scope="row" style="width: 240px;"><?php esc_html_e('Versión del plugin', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html(defined('MX_POS_PRO_VERSION') ? MX_POS_PRO_VERSION : '—'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Versión de base de datos', 'mx-pos-pro'); ?></th>
                        <td>
                            <?php echo esc_html($db_version ?: '—'); ?>
                            <?php if ($db_version !== $expected_db): ?>
                                <span class="dashicons dashicons-warning" style="color:#dba617;vertical-align:middle;" title="<?php esc_attr_e('Desactualizada', 'mx-pos-pro'); ?>"></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-yes-alt" style="color:#00a32a;vertical-align:middle;" title="<?php esc_attr_e('Actualizada', 'mx-pos-pro'); ?>"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Tablas de base de datos', 'mx-pos-pro'); ?></th>
                        <td>
                            <?php if ($tables_exist): ?>
                                <span style="color:#00a32a;"><?php esc_html_e('Todas las tablas presentes', 'mx-pos-pro'); ?></span>
                            <?php else: ?>
                                <span style="color:#d63638;"><?php esc_html_e('Faltan tablas', 'mx-pos-pro'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Sucursal Principal', 'mx-pos-pro'); ?></th>
                        <td>
                            <?php if ($main_branch): ?>
                                <span style="color:#00a32a;"><?php echo esc_html($main_branch['name']); ?></span>
                            <?php else: ?>
                                <span style="color:#d63638;"><?php esc_html_e('No encontrada', 'mx-pos-pro'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Caja Principal', 'mx-pos-pro'); ?></th>
                        <td>
                            <?php if ($main_register): ?>
                                <span style="color:#00a32a;"><?php echo esc_html($main_register['name']); ?></span>
                            <?php else: ?>
                                <span style="color:#d63638;"><?php esc_html_e('No encontrada', 'mx-pos-pro'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Métodos de pago base', 'mx-pos-pro'); ?></th>
                        <td>
                            <?php
                            $statuses = [];
                            $statuses[] = $has_cash_seed
                                ? '<span style="color:#00a32a;">' . esc_html__('Efectivo', 'mx-pos-pro') . '</span>'
                                : '<span style="color:#d63638;">' . esc_html__('Efectivo (falta)', 'mx-pos-pro') . '</span>';
                            $statuses[] = $has_card_seed
                                ? '<span style="color:#00a32a;">' . esc_html__('Tarjeta', 'mx-pos-pro') . '</span>'
                                : '<span style="color:#d63638;">' . esc_html__('Tarjeta (falta)', 'mx-pos-pro') . '</span>';
                            $statuses[] = $has_mixed_seed
                                ? '<span style="color:#00a32a;">' . esc_html__('Mixto', 'mx-pos-pro') . '</span>'
                                : '<span style="color:#d63638;">' . esc_html__('Mixto (falta)', 'mx-pos-pro') . '</span>';
                            echo implode(' &nbsp;|&nbsp; ', $statuses);
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mx-pos-section">
            <h2><?php esc_html_e('Conteos', 'mx-pos-pro'); ?></h2>

            <table class="widefat striped" style="max-width: 720px;">
                <tbody>
                    <tr>
                        <th scope="row" style="width: 240px;"><?php esc_html_e('Sucursales activas', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $branches_total); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Cajas activas', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html($registers_total . ' / ' . self::MAX_ACTIVE_REGISTERS); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Empleados POS activos', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html($employees_total . ' (' . $cashiers_count . ' cajeros, ' . $managers_count . ' gerentes)'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Métodos de pago activos', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $methods_total); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Sesiones abiertas', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $sessions_open); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Sesiones cerradas', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $sessions_closed); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Ventas totales', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $sales_total); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Devoluciones', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $refunds_total); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Cierres / Pre-cortes', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $cuts_total); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Carritos aparcados', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $parked_carts); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Productos indexados', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $products_indexed); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Última indexación', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html($last_indexed ?: __('Sin datos', 'mx-pos-pro')); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ─── Sucursales ───────────────────────────────────────────────────────────

    private function render_sucursales(): void
    {
        $repo         = new BranchRepository();
        $branches     = $repo->get_all();
        $default_slug = 'main';
        $edit_branch  = null;

        if (isset($_GET['edit_branch'])) {
            $edit_id = (int) $_GET['edit_branch'];
            $edit_branch = $repo->get_by_id($edit_id);
        }

        ?>
        <h2><?php esc_html_e('Sucursales', 'mx-pos-pro'); ?></h2>
        <p class="description">
            <?php esc_html_e('Gestión de sucursales. Las sucursales no se eliminan; solo se desactivan para conservar el historial.', 'mx-pos-pro'); ?>
        </p>

        <?php if (isset($_GET['branch_created'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Sucursal creada.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['branch_updated'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Sucursal actualizada.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['branch_toggled'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Sucursal activada/desactivada.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['branch_error'])): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['branch_error']))); ?></p>
            </div>
        <?php endif; ?>

        <p>
            <?php if ($edit_branch): ?>
                <a href="<?php echo esc_url(add_query_arg('tab', 'sucursales', admin_url('admin.php?page=' . self::MENU_SLUG))); ?>"
                   class="button">
                    <?php esc_html_e('Cancelar edición', 'mx-pos-pro'); ?>
                </a>
            <?php else: ?>
                <button type="button" id="mx-pos-toggle-branch-form" class="button button-primary">
                    <?php esc_html_e('Agregar sucursal', 'mx-pos-pro'); ?>
                </button>
            <?php endif; ?>
        </p>

        <div id="mx-pos-branch-form"
             style="display:<?php echo $edit_branch || isset($_GET['show_branch_form']) ? 'block' : 'none'; ?>;">
            <div style="background:#fff; border:1px solid #c3c4c7; padding:16px 24px; margin-bottom:16px; max-width:720px;">
                <h3>
                    <?php echo $edit_branch
                        ? esc_html__('Editar sucursal', 'mx-pos-pro')
                        : esc_html__('Nueva sucursal', 'mx-pos-pro'); ?>
                </h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mx_pos_manage_branch" />
                    <input type="hidden" name="do" value="<?php echo $edit_branch ? 'branch_update' : 'branch_create'; ?>" />
                    <?php if ($edit_branch): ?>
                        <input type="hidden" name="branch_id" value="<?php echo esc_attr((string) $edit_branch['id']); ?>" />
                    <?php endif; ?>
                    <?php wp_nonce_field(self::BRANCH_NONCE_ACTION, self::BRANCH_NONCE_NAME); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_branch_name"><?php esc_html_e('Nombre', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="mx_pos_branch_name" name="branch_name"
                                       class="regular-text" maxlength="150" required
                                       value="<?php echo esc_attr($edit_branch['name'] ?? ''); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_branch_slug"><?php esc_html_e('Código', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                            </th>
                            <td>
                                <?php if ($edit_branch && $edit_branch['slug'] === 'main'): ?>
                                    <input type="text" id="mx_pos_branch_slug" class="regular-text" disabled
                                           value="<?php echo esc_attr($edit_branch['slug']); ?>" />
                                    <p class="description"><?php esc_html_e('El código de la Sucursal Principal no puede modificarse.', 'mx-pos-pro'); ?></p>
                                <?php else: ?>
                                    <input type="text" id="mx_pos_branch_slug" name="branch_slug"
                                           class="regular-text" maxlength="50" required
                                           pattern="[a-z0-9_-]+"
                                           value="<?php echo esc_attr($edit_branch['slug'] ?? ''); ?>" />
                                    <p class="description"><?php esc_html_e('Solo minúsculas, números, guiones y guiones bajos.', 'mx-pos-pro'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_branch_address"><?php esc_html_e('Dirección', 'mx-pos-pro'); ?></label>
                            </th>
                            <td>
                                <textarea id="mx_pos_branch_address" name="branch_address"
                                          class="regular-text" rows="2"><?php echo esc_textarea($edit_branch['address'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_branch_phone"><?php esc_html_e('Teléfono', 'mx-pos-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="mx_pos_branch_phone" name="branch_phone"
                                       class="regular-text" maxlength="30"
                                       value="<?php echo esc_attr($edit_branch['phone'] ?? ''); ?>" />
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php echo $edit_branch
                                ? esc_html__('Guardar cambios', 'mx-pos-pro')
                                : esc_html__('Crear sucursal', 'mx-pos-pro'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <?php if (empty($branches)): ?>
            <div class="notice notice-warning inline">
                <p><?php esc_html_e('No hay sucursales registradas. Ejecute la reactivación del plugin para crear la sucursal principal.', 'mx-pos-pro'); ?></p>
            </div>
            <?php return; ?>
        <?php endif; ?>

        <table class="widefat striped" style="max-width: 960px;">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th><?php esc_html_e('Nombre', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Código', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Dirección', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Teléfono', 'mx-pos-pro'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Estado', 'mx-pos-pro'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Cajas activas', 'mx-pos-pro'); ?></th>
                    <th style="width: 160px;"><?php esc_html_e('Acciones', 'mx-pos-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $branch):
                    $is_main        = $branch['slug'] === $default_slug;
                    $active_regs    = $repo->count_active_registers((int) $branch['id']);
                    $is_active      = (int) $branch['is_active'] === 1;
                    $toggle_url     = wp_nonce_url(
                        add_query_arg([
                            'action'     => 'mx_pos_manage_branch',
                            'do'         => 'branch_toggle',
                            'branch_id'  => (int) $branch['id'],
                            'active'     => $is_active ? 0 : 1,
                        ], admin_url('admin-post.php')),
                        self::BRANCH_NONCE_ACTION,
                        self::BRANCH_NONCE_NAME
                    );
                    $edit_url       = add_query_arg([
                        'page'        => self::MENU_SLUG,
                        'tab'         => 'sucursales',
                        'edit_branch' => (int) $branch['id'],
                    ], admin_url('admin.php'));
                ?>
                    <tr>
                        <td><?php echo esc_html((string) $branch['id']); ?></td>
                        <td>
                            <strong><?php echo esc_html($branch['name']); ?></strong>
                            <?php if ($is_main): ?>
                                <span class="dashicons dashicons-yes-alt" style="color:#00a32a;vertical-align:middle;" title="<?php esc_attr_e('Sucursal Principal', 'mx-pos-pro'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo esc_html($branch['slug']); ?></code></td>
                        <td><?php echo esc_html($branch['address'] ?: '—'); ?></td>
                        <td><?php echo esc_html($branch['phone'] ?: '—'); ?></td>
                        <td>
                            <?php if ($is_active): ?>
                                <span style="color:#00a32a;"><?php esc_html_e('Activa', 'mx-pos-pro'); ?></span>
                            <?php else: ?>
                                <span style="color:#d63638;"><?php esc_html_e('Inactiva', 'mx-pos-pro'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) $active_regs); ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                <?php esc_html_e('Editar', 'mx-pos-pro'); ?>
                            </a>
                            <?php if ($is_main): ?>
                                <span style="color:#a7aaad;font-size:12px;">
                                    <?php esc_html_e('Principal', 'mx-pos-pro'); ?>
                                </span>
                            <?php else: ?>
                                <a href="<?php echo esc_url($toggle_url); ?>" class="button button-small">
                                    <?php echo $is_active
                                        ? esc_html__('Desactivar', 'mx-pos-pro')
                                        : esc_html__('Activar', 'mx-pos-pro'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        (function () {
            var btn = document.getElementById('mx-pos-toggle-branch-form');
            var form = document.getElementById('mx-pos-branch-form');
            if (btn && form) {
                btn.addEventListener('click', function () {
                    if (form.style.display === 'none' || form.style.display === '') {
                        form.style.display = 'block';
                        btn.textContent = '<?php echo esc_js(__('Cancelar', 'mx-pos-pro')); ?>';
                    } else {
                        form.style.display = 'none';
                        btn.textContent = '<?php echo esc_js(__('Agregar sucursal', 'mx-pos-pro')); ?>';
                    }
                });
            }
        })();
        </script>
        <?php
    }

    // ─── Cajas ─────────────────────────────────────────────────────────────────

    private function render_cajas(): void
    {
        $repo          = new RegisterRepository();
        $branch_repo   = new BranchRepository();
        $registers     = $repo->get_all_with_branch();
        $branches      = $branch_repo->get_all_active();
        $default_slug  = 'main';
        $active_count  = $repo->count_active();
        $edit_register = null;

        if (isset($_GET['edit_register'])) {
            $edit_id = (int) $_GET['edit_register'];
            $edit_register = $repo->get_by_id($edit_id);
        }

        ?>
        <h2><?php esc_html_e('Cajas', 'mx-pos-pro'); ?></h2>
        <p class="description">
            <?php esc_html_e('Gestión de cajas físicas. Las cajas no se eliminan; solo se desactivan para conservar el historial.', 'mx-pos-pro'); ?>
        </p>
        <p>
            <?php esc_html_e('Cajas activas:', 'mx-pos-pro'); ?>
            <strong><?php echo esc_html($active_count . ' / ' . self::MAX_ACTIVE_REGISTERS); ?></strong>
        </p>

        <?php if (isset($_GET['register_created'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Caja creada.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['register_updated'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Caja actualizada.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['register_toggled'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Caja activada/desactivada.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['register_error'])): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['register_error']))); ?></p>
            </div>
        <?php endif; ?>

        <p>
            <?php if ($edit_register): ?>
                <a href="<?php echo esc_url(add_query_arg('tab', 'cajas', admin_url('admin.php?page=' . self::MENU_SLUG))); ?>"
                   class="button">
                    <?php esc_html_e('Cancelar edición', 'mx-pos-pro'); ?>
                </a>
            <?php elseif ($active_count >= self::MAX_ACTIVE_REGISTERS): ?>
                <button type="button" class="button button-primary" disabled
                        title="<?php esc_attr_e('Límite de cajas activas alcanzado.', 'mx-pos-pro'); ?>">
                    <?php esc_html_e('Agregar caja', 'mx-pos-pro'); ?>
                </button>
            <?php else: ?>
                <button type="button" id="mx-pos-toggle-register-form" class="button button-primary">
                    <?php esc_html_e('Agregar caja', 'mx-pos-pro'); ?>
                </button>
            <?php endif; ?>
        </p>

        <div id="mx-pos-register-form"
             style="display:<?php echo $edit_register || isset($_GET['show_register_form']) ? 'block' : 'none'; ?>;">
            <div style="background:#fff; border:1px solid #c3c4c7; padding:16px 24px; margin-bottom:16px; max-width:720px;">
                <h3>
                    <?php echo $edit_register
                        ? esc_html__('Editar caja', 'mx-pos-pro')
                        : esc_html__('Nueva caja', 'mx-pos-pro'); ?>
                </h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mx_pos_manage_register" />
                    <input type="hidden" name="do" value="<?php echo $edit_register ? 'register_update' : 'register_create'; ?>" />
                    <?php if ($edit_register): ?>
                        <input type="hidden" name="register_id" value="<?php echo esc_attr((string) $edit_register['id']); ?>" />
                    <?php endif; ?>
                    <?php wp_nonce_field(self::REGISTER_NONCE_ACTION, self::REGISTER_NONCE_NAME); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_register_name"><?php esc_html_e('Nombre', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="mx_pos_register_name" name="register_name"
                                       class="regular-text" maxlength="100" required
                                       value="<?php echo esc_attr($edit_register['name'] ?? ''); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_register_slug"><?php esc_html_e('Código', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                            </th>
                            <td>
                                <?php if ($edit_register && $edit_register['slug'] === 'main'): ?>
                                    <input type="text" id="mx_pos_register_slug" class="regular-text" disabled
                                           value="<?php echo esc_attr($edit_register['slug']); ?>" />
                                    <p class="description"><?php esc_html_e('El código de la Caja Principal no puede modificarse.', 'mx-pos-pro'); ?></p>
                                <?php else: ?>
                                    <input type="text" id="mx_pos_register_slug" name="register_slug"
                                           class="regular-text" maxlength="50" required
                                           pattern="[a-z0-9_-]+"
                                           value="<?php echo esc_attr($edit_register['slug'] ?? ''); ?>" />
                                    <p class="description"><?php esc_html_e('Solo minúsculas, números, guiones y guiones bajos.', 'mx-pos-pro'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_register_branch"><?php esc_html_e('Sucursal', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                            </th>
                            <td>
                                <select id="mx_pos_register_branch" name="register_branch_id" required>
                                    <option value=""><?php esc_html_e('— Seleccionar —', 'mx-pos-pro'); ?></option>
                                    <?php foreach ($branches as $b):
                                        $selected = ($edit_register && (int) $edit_register['branch_id'] === (int) $b['id']) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo esc_attr((string) $b['id']); ?>" <?php echo $selected; ?>>
                                            <?php echo esc_html($b['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Solo se muestran sucursales activas.', 'mx-pos-pro'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php echo $edit_register
                                ? esc_html__('Guardar cambios', 'mx-pos-pro')
                                : esc_html__('Crear caja', 'mx-pos-pro'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <?php if (empty($registers)): ?>
            <div class="notice notice-warning inline">
                <p><?php esc_html_e('No hay cajas registradas. Ejecute la reactivación del plugin para crear la caja principal.', 'mx-pos-pro'); ?></p>
            </div>
            <?php return; ?>
        <?php endif; ?>

        <table class="widefat striped" style="max-width: 960px;">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th><?php esc_html_e('Nombre', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Código', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Sucursal', 'mx-pos-pro'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Estado', 'mx-pos-pro'); ?></th>
                    <th style="width: 160px;"><?php esc_html_e('Acciones', 'mx-pos-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registers as $register):
                    $is_main     = $register['slug'] === $default_slug;
                    $is_active   = (int) $register['is_active'] === 1;
                    $toggle_url  = wp_nonce_url(
                        add_query_arg([
                            'action'      => 'mx_pos_manage_register',
                            'do'          => 'register_toggle',
                            'register_id' => (int) $register['id'],
                            'active'      => $is_active ? 0 : 1,
                        ], admin_url('admin-post.php')),
                        self::REGISTER_NONCE_ACTION,
                        self::REGISTER_NONCE_NAME
                    );
                    $edit_url    = add_query_arg([
                        'page'         => self::MENU_SLUG,
                        'tab'          => 'cajas',
                        'edit_register' => (int) $register['id'],
                    ], admin_url('admin.php'));
                ?>
                    <tr>
                        <td><?php echo esc_html((string) $register['id']); ?></td>
                        <td>
                            <strong><?php echo esc_html($register['name']); ?></strong>
                            <?php if ($is_main): ?>
                                <span class="dashicons dashicons-yes-alt" style="color:#00a32a;vertical-align:middle;" title="<?php esc_attr_e('Caja Principal', 'mx-pos-pro'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo esc_html($register['slug']); ?></code></td>
                        <td><?php echo esc_html($register['branch_name'] ?? '—'); ?></td>
                        <td>
                            <?php if ($is_active): ?>
                                <span style="color:#00a32a;"><?php esc_html_e('Activa', 'mx-pos-pro'); ?></span>
                            <?php else: ?>
                                <span style="color:#d63638;"><?php esc_html_e('Inactiva', 'mx-pos-pro'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                <?php esc_html_e('Editar', 'mx-pos-pro'); ?>
                            </a>
                            <?php if ($is_main): ?>
                                <span style="color:#a7aaad;font-size:12px;">
                                    <?php esc_html_e('Principal', 'mx-pos-pro'); ?>
                                </span>
                            <?php else: ?>
                                <a href="<?php echo esc_url($toggle_url); ?>" class="button button-small">
                                    <?php echo $is_active
                                        ? esc_html__('Desactivar', 'mx-pos-pro')
                                        : esc_html__('Activar', 'mx-pos-pro'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        (function () {
            var btn = document.getElementById('mx-pos-toggle-register-form');
            var form = document.getElementById('mx-pos-register-form');
            if (btn && form) {
                btn.addEventListener('click', function () {
                    if (form.style.display === 'none' || form.style.display === '') {
                        form.style.display = 'block';
                        btn.textContent = '<?php echo esc_js(__('Cancelar', 'mx-pos-pro')); ?>';
                    } else {
                        form.style.display = 'none';
                        btn.textContent = '<?php echo esc_js(__('Agregar caja', 'mx-pos-pro')); ?>';
                    }
                });
            }
        })();
        </script>
        <?php
    }

    // ─── Empleados ─────────────────────────────────────────────────────────────

    private function render_empleados(): void
    {
        $repo         = new EmployeeRepository();
        $branch_repo  = new BranchRepository();
        $show_deleted = isset($_GET['show_deleted']);
        $edit_employee = null;

        if (isset($_GET['edit_employee'])) {
            $edit_id = (int) $_GET['edit_employee'];
            $edit_employee = $repo->get_by_id($edit_id);
            if ($edit_employee !== null) {
                $show_deleted = true;
            }
        }

        $employees = $repo->get_all();

        $branches   = $branch_repo->get_all_active();
        $total_active = $repo->count_active();
        $cashiers     = $repo->count_by_role('cashier');
        $managers     = $repo->count_by_role('manager');

        $admin_post_url = esc_url(admin_url('admin-post.php'));

        $base_url = add_query_arg(
            ['page' => self::MENU_SLUG, 'tab' => 'empleados'],
            admin_url('admin.php')
        );

        ?>
        <h2><?php esc_html_e('Empleados POS', 'mx-pos-pro'); ?></h2>
        <p class="description">
            <?php esc_html_e('Gestión de empleados internos del POS. Los empleados no se eliminan físicamente; se desactivan o se dan de baja lógicamente para conservar el historial.', 'mx-pos-pro'); ?>
        </p>
        <p>
            <strong><?php esc_html_e('Activos:', 'mx-pos-pro'); ?></strong>
            <?php echo esc_html((string) $total_active); ?>
            &nbsp;|&nbsp;
            <?php esc_html_e('Cajeros:', 'mx-pos-pro'); ?> <?php echo esc_html((string) $cashiers); ?>
            &nbsp;|&nbsp;
            <?php esc_html_e('Gerentes:', 'mx-pos-pro'); ?> <?php echo esc_html((string) $managers); ?>
        </p>

        <?php if (isset($_GET['employee_created'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Empleado creado.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['employee_updated'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Empleado actualizado.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['employee_toggled'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Estado del empleado actualizado.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['employee_deleted'])): ?>
            <div class="notice notice-warning is-dismissible"><p><?php esc_html_e('Empleado dado de baja lógicamente.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['employee_restored'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Empleado restaurado.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['employee_password_reset'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Contraseña restablecida.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['employee_unlocked'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Empleado desbloqueado.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['employee_error'])): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['employee_error']))); ?></p>
            </div>
        <?php endif; ?>

        <p>
            <?php if ($edit_employee): ?>
                <a href="<?php echo esc_url($base_url); ?>" class="button">
                    <?php esc_html_e('Cancelar edición', 'mx-pos-pro'); ?>
                </a>
            <?php else: ?>
                <button type="button" id="mx-pos-toggle-employee-form" class="button button-primary">
                    <?php esc_html_e('Agregar empleado', 'mx-pos-pro'); ?>
                </button>
            <?php endif; ?>
            &nbsp;
            <a href="<?php echo esc_url($show_deleted ? $base_url : add_query_arg('show_deleted', '1', $base_url)); ?>"
               class="button">
                <?php echo $show_deleted
                    ? esc_html__('Ocultar borrados', 'mx-pos-pro')
                    : esc_html__('Mostrar borrados', 'mx-pos-pro'); ?>
            </a>
        </p>

        <div id="mx-pos-employee-form"
             style="display:<?php echo $edit_employee || isset($_GET['show_employee_form']) ? 'block' : 'none'; ?>;">
            <div style="background:#fff; border:1px solid #c3c4c7; padding:16px 24px; margin-bottom:16px; max-width:720px;">
                <h3>
                    <?php echo $edit_employee
                        ? esc_html__('Editar empleado', 'mx-pos-pro')
                        : esc_html__('Nuevo empleado', 'mx-pos-pro'); ?>
                </h3>
                <form method="post" action="<?php echo esc_url($admin_post_url); ?>">
                    <input type="hidden" name="action" value="mx_pos_manage_employee" />
                    <input type="hidden" name="do" value="<?php echo $edit_employee ? 'employee_update' : 'employee_create'; ?>" />
                    <?php if ($edit_employee): ?>
                        <input type="hidden" name="employee_id" value="<?php echo esc_attr((string) $edit_employee['id']); ?>" />
                    <?php endif; ?>
                    <?php wp_nonce_field(self::EMPLOYEE_NONCE_ACTION, self::EMPLOYEE_NONCE_NAME); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_employee_display_name"><?php esc_html_e('Nombre visible', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="mx_pos_employee_display_name" name="employee_display_name"
                                       class="regular-text" maxlength="150" required
                                       value="<?php echo esc_attr($edit_employee['display_name'] ?? ''); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_employee_username"><?php esc_html_e('Usuario', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                            </th>
                            <td>
                                <?php if ($edit_employee): ?>
                                    <input type="text" id="mx_pos_employee_username" class="regular-text" disabled
                                           value="<?php echo esc_attr($edit_employee['username']); ?>" />
                                    <p class="description"><?php esc_html_e('El usuario no puede modificarse.', 'mx-pos-pro'); ?></p>
                                <?php else: ?>
                                    <input type="text" id="mx_pos_employee_username" name="employee_username"
                                           class="regular-text" maxlength="100" required
                                           pattern="[a-zA-Z0-9_\-]+"
                                           value="" />
                                    <p class="description"><?php esc_html_e('Letras, números, guiones y guiones bajos.', 'mx-pos-pro'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_employee_password"><?php esc_html_e('Contraseña', 'mx-pos-pro'); ?> <?php echo ! $edit_employee ? '<span style="color:#d63638;">*</span>' : ''; ?></label>
                            </th>
                            <td>
                                <input type="password" id="mx_pos_employee_password" name="employee_password"
                                       class="regular-text" minlength="8" autocomplete="new-password"
                                       <?php echo ! $edit_employee ? 'required' : ''; ?>
                                       value="" />
                                <p class="description">
                                    <?php esc_html_e('Mínimo 8 caracteres.', 'mx-pos-pro'); ?>
                                    <?php if ($edit_employee): ?>
                                        <?php esc_html_e('Dejar vacío para no modificar.', 'mx-pos-pro'); ?>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_employee_password_confirm"><?php esc_html_e('Confirmar contraseña', 'mx-pos-pro'); ?> <?php echo ! $edit_employee ? '<span style="color:#d63638;">*</span>' : ''; ?></label>
                            </th>
                            <td>
                                <input type="password" id="mx_pos_employee_password_confirm" name="employee_password_confirm"
                                       class="regular-text" minlength="8" autocomplete="new-password"
                                       <?php echo ! $edit_employee ? 'required' : ''; ?>
                                       value="" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_employee_role"><?php esc_html_e('Rol', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                            </th>
                            <td>
                                <select id="mx_pos_employee_role" name="employee_role" required>
                                    <option value="cashier" <?php echo ($edit_employee['role'] ?? '') === 'cashier' ? 'selected' : ''; ?>>
                                        <?php esc_html_e('Cajero', 'mx-pos-pro'); ?>
                                    </option>
                                    <option value="manager" <?php echo ($edit_employee['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>
                                        <?php esc_html_e('Gerente', 'mx-pos-pro'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_employee_branch"><?php esc_html_e('Sucursal', 'mx-pos-pro'); ?></label>
                            </th>
                            <td>
                                <select id="mx_pos_employee_branch" name="employee_branch_id">
                                    <option value=""><?php esc_html_e('— Sin sucursal —', 'mx-pos-pro'); ?></option>
                                    <?php foreach ($branches as $b):
                                        $selected = ($edit_employee && (int) $edit_employee['branch_id'] === (int) $b['id']) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo esc_attr((string) $b['id']); ?>" <?php echo $selected; ?>>
                                            <?php echo esc_html($b['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Solo se muestran sucursales activas.', 'mx-pos-pro'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php echo $edit_employee
                                ? esc_html__('Guardar cambios', 'mx-pos-pro')
                                : esc_html__('Crear empleado', 'mx-pos-pro'); ?>
                        </button>
                    </p>
                </form>

                <?php if ($edit_employee): ?>
                    <hr style="margin:16px 0;border-color:#dcdcde;">
                    <h3><?php esc_html_e('Restablecer contraseña', 'mx-pos-pro'); ?></h3>
                    <p class="description">
                        <?php esc_html_e('Asignar una nueva contraseña. También se desbloqueará la cuenta si estaba bloqueada.', 'mx-pos-pro'); ?>
                    </p>
                    <form method="post" action="<?php echo esc_url($admin_post_url); ?>">
                        <input type="hidden" name="action" value="mx_pos_manage_employee" />
                        <input type="hidden" name="do" value="employee_reset_password" />
                        <input type="hidden" name="employee_id" value="<?php echo esc_attr((string) $edit_employee['id']); ?>" />
                        <?php wp_nonce_field(self::EMPLOYEE_NONCE_ACTION, self::EMPLOYEE_NONCE_NAME); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="mx_pos_employee_reset_password"><?php esc_html_e('Nueva contraseña', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                                </th>
                                <td>
                                    <input type="password" id="mx_pos_employee_reset_password" name="employee_password"
                                           class="regular-text" minlength="8" autocomplete="new-password" required value="" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="mx_pos_employee_reset_password_confirm"><?php esc_html_e('Confirmar', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                                </th>
                                <td>
                                    <input type="password" id="mx_pos_employee_reset_password_confirm" name="employee_password_confirm"
                                           class="regular-text" minlength="8" autocomplete="new-password" required value="" />
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button">
                                <?php esc_html_e('Restablecer contraseña', 'mx-pos-pro'); ?>
                            </button>
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($employees)): ?>
            <div class="notice notice-info inline">
                <p><?php esc_html_e('No hay empleados POS registrados aún.', 'mx-pos-pro'); ?></p>
            </div>
            <?php endif; ?>

        <table class="widefat striped" style="max-width: 960px;">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th><?php esc_html_e('Nombre', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Usuario', 'mx-pos-pro'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Rol', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Sucursal', 'mx-pos-pro'); ?></th>
                    <th style="width: 130px;"><?php esc_html_e('Último login', 'mx-pos-pro'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Estado', 'mx-pos-pro'); ?></th>
                    <th style="width: 240px;"><?php esc_html_e('Acciones', 'mx-pos-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp):
                    $emp_id         = (int) $emp['id'];
                    $is_deleted     = $emp['deleted_at'] !== null;
                    $is_active      = (int) $emp['is_active'] === 1;
                    $is_locked      = $emp['locked_until'] !== null;
                    $visible        = $show_deleted || ! $is_deleted;
                    if (! $visible) {
                        continue;
                    }

                    $status_label = $is_deleted
                        ? __('Borrado', 'mx-pos-pro')
                        : ($is_active ? __('Activo', 'mx-pos-pro') : __('Inactivo', 'mx-pos-pro'));
                    $status_color = $is_deleted ? '#d63638' : ($is_active ? '#00a32a' : '#dba617');

                    $edit_url = add_query_arg([
                        'page'           => self::MENU_SLUG,
                        'tab'            => 'empleados',
                        'edit_employee'  => $emp_id,
                    ], admin_url('admin.php'));

                    $toggle_url = $is_deleted
                        ? ''
                        : wp_nonce_url(
                            add_query_arg([
                                'action'      => 'mx_pos_manage_employee',
                                'do'          => 'employee_toggle',
                                'employee_id' => $emp_id,
                                'active'      => $is_active ? 0 : 1,
                            ], admin_url('admin-post.php')),
                            self::EMPLOYEE_NONCE_ACTION,
                            self::EMPLOYEE_NONCE_NAME
                        );

                    $delete_url = ($is_deleted || ! $is_active)
                        ? ''
                        : wp_nonce_url(
                            add_query_arg([
                                'action'      => 'mx_pos_manage_employee',
                                'do'          => 'employee_soft_delete',
                                'employee_id' => $emp_id,
                            ], admin_url('admin-post.php')),
                            self::EMPLOYEE_NONCE_ACTION,
                            self::EMPLOYEE_NONCE_NAME
                        );

                    $restore_url = ! $is_deleted
                        ? ''
                        : wp_nonce_url(
                            add_query_arg([
                                'action'      => 'mx_pos_manage_employee',
                                'do'          => 'employee_restore',
                                'employee_id' => $emp_id,
                            ], admin_url('admin-post.php')),
                            self::EMPLOYEE_NONCE_ACTION,
                            self::EMPLOYEE_NONCE_NAME
                        );

                    $unlock_url = ! $is_locked || $is_deleted
                        ? ''
                        : wp_nonce_url(
                            add_query_arg([
                                'action'      => 'mx_pos_manage_employee',
                                'do'          => 'employee_unlock',
                                'employee_id' => $emp_id,
                            ], admin_url('admin-post.php')),
                            self::EMPLOYEE_NONCE_ACTION,
                            self::EMPLOYEE_NONCE_NAME
                        );
                ?>
                    <tr>
                        <td><?php echo esc_html((string) $emp_id); ?></td>
                        <td><strong><?php echo esc_html($emp['display_name']); ?></strong></td>
                        <td><code><?php echo esc_html($emp['username']); ?></code></td>
                        <td>
                            <?php if ($emp['role'] === 'manager'): ?>
                                <span style="color:#005a87;font-weight:600;"><?php esc_html_e('Gerente', 'mx-pos-pro'); ?></span>
                            <?php else: ?>
                                <?php esc_html_e('Cajero', 'mx-pos-pro'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($emp['branch_name'] ?? '—'); ?></td>
                        <td>
                            <?php echo esc_html($emp['last_login_at'] ?: '—'); ?>
                            <?php if ($is_locked): ?>
                                <br><small style="color:#d63638;"><?php esc_html_e('Bloqueado', 'mx-pos-pro'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color:<?php echo esc_attr($status_color); ?>;font-weight:600;">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (! $is_deleted): ?>
                                <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                    <?php esc_html_e('Editar', 'mx-pos-pro'); ?>
                                </a>
                                <?php if ($toggle_url !== ''): ?>
                                    <a href="<?php echo esc_url($toggle_url); ?>" class="button button-small">
                                        <?php echo $is_active
                                            ? esc_html__('Desactivar', 'mx-pos-pro')
                                            : esc_html__('Activar', 'mx-pos-pro'); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($delete_url !== ''): ?>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="button button-small"
                                       onclick="return confirm('<?php echo esc_js(__('¿Dar de baja este empleado?', 'mx-pos-pro')); ?>')">
                                        <?php esc_html_e('Borrar', 'mx-pos-pro'); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($unlock_url !== ''): ?>
                                    <a href="<?php echo esc_url($unlock_url); ?>" class="button button-small">
                                        <?php esc_html_e('Desbloquear', 'mx-pos-pro'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($restore_url !== ''): ?>
                                    <a href="<?php echo esc_url($restore_url); ?>" class="button button-small">
                                        <?php esc_html_e('Restaurar', 'mx-pos-pro'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        (function () {
            var btn = document.getElementById('mx-pos-toggle-employee-form');
            var form = document.getElementById('mx-pos-employee-form');
            if (btn && form) {
                btn.addEventListener('click', function () {
                    if (form.style.display === 'none' || form.style.display === '') {
                        form.style.display = 'block';
                        btn.textContent = '<?php echo esc_js(__('Cancelar', 'mx-pos-pro')); ?>';
                    } else {
                        form.style.display = 'none';
                        btn.textContent = '<?php echo esc_js(__('Agregar empleado', 'mx-pos-pro')); ?>';
                    }
                });
            }
        })();
        </script>
        <?php
    }

    // ─── Sesiones ──────────────────────────────────────────────────────────────

    private function render_sesiones(): void
    {
        $repo     = new CashSessionRepository();
        $sessions = $repo->get_recent(20);

        $branchRepo   = new BranchRepository();
        $registerRepo = new RegisterRepository();
        $employeeRepo = new EmployeeRepository();

        ?>
        <h2><?php esc_html_e('Sesiones de caja', 'mx-pos-pro'); ?></h2>
        <p class="description">
            <?php esc_html_e('Últimas 20 sesiones de caja. Las sesiones abiertas pueden anularse desde aquí si es necesario.', 'mx-pos-pro'); ?>
        </p>

        <?php if (empty($sessions)): ?>
            <div class="notice notice-info inline">
                <p><?php esc_html_e('No hay sesiones registradas aún.', 'mx-pos-pro'); ?></p>
            </div>
            <?php return; ?>
        <?php endif; ?>

        <table class="widefat striped" style="max-width: 960px;">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="width: 100px;"><?php esc_html_e('Sucursal', 'mx-pos-pro'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Caja', 'mx-pos-pro'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Empleado', 'mx-pos-pro'); ?></th>
                    <th style="width: 90px;"><?php esc_html_e('Fondo inicial', 'mx-pos-pro'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Estado', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Apertura', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Cierre / Anulación', 'mx-pos-pro'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Acciones', 'mx-pos-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session):
                    $branch_name   = '—';
                    $register_name = '—';
                    $employee_name = '—';

                    if (! empty($session['branch_id'])) {
                        $branch = $branchRepo->get_by_id((int) $session['branch_id']);
                        $branch_name = $branch !== null ? $branch['name'] : '—';
                    }

                    if (! empty($session['pos_register_id'])) {
                        $register = $registerRepo->get_by_id((int) $session['pos_register_id']);
                        $register_name = $register !== null ? $register['name'] : '—';
                    }

                    if (! empty($session['pos_employee_id'])) {
                        $employee = $employeeRepo->get_by_id((int) $session['pos_employee_id']);
                        $employee_name = $employee !== null ? $employee['display_name'] : '—';
                    } elseif (! empty($session['cashier_id'])) {
                        $employee_name = 'WP User #' . $session['cashier_id'];
                    }

                    $status_label = '';
                    $status_color = '';

                    if ($session['status'] === 'open') {
                        $status_label = __('Abierta', 'mx-pos-pro');
                        $status_color = '#dba617';
                    } elseif ($session['status'] === 'voided') {
                        $status_label = __('Anulada', 'mx-pos-pro');
                        $status_color = '#d63638';
                    } else {
                        $status_label = __('Cerrada', 'mx-pos-pro');
                        $status_color = '#00a32a';
                    }

                    $close_info = '—';

                    if ($session['status'] === 'closed' && ! empty($session['closed_at'])) {
                        $close_info = $session['closed_at'];
                    } elseif ($session['status'] === 'voided') {
                        $void_info_parts = [];
                        if (! empty($session['voided_at'])) {
                            $void_info_parts[] = $session['voided_at'];
                        }
                        if (! empty($session['void_reason'])) {
                            $reason_display = mb_strlen($session['void_reason']) > 40
                                ? mb_substr($session['void_reason'], 0, 40) . '...'
                                : $session['void_reason'];
                            $void_info_parts[] = esc_html($reason_display);
                        }
                        $close_info = implode(' — ', $void_info_parts);
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) $session['id']); ?></td>
                        <td><?php echo esc_html($branch_name); ?></td>
                        <td><?php echo esc_html($register_name); ?></td>
                        <td><?php echo esc_html($employee_name); ?></td>
                        <td><?php echo esc_html(number_format((float) $session['opening_amount'], 2)); ?></td>
                        <td><span style="color:<?php echo esc_attr($status_color); ?>;font-weight:600;"><?php echo esc_html($status_label); ?></span></td>
                        <td><?php echo esc_html($session['opened_at']); ?></td>
                        <td><?php echo esc_html($close_info); ?></td>
                        <td>
                            <?php if ($session['status'] === 'open'): ?>
                                <div style="display:flex;flex-direction:column;gap:6px;">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:4px;align-items:center;">
                                    <?php wp_nonce_field(self::VOID_SESSION_NONCE_ACTION, self::VOID_SESSION_NONCE_NAME); ?>
                                    <input type="hidden" name="action" value="mx_pos_void_session" />
                                    <input type="hidden" name="session_id" value="<?php echo esc_attr((string) $session['id']); ?>" />
                                    <input
                                        type="text"
                                        name="void_reason"
                                        required
                                        placeholder="<?php echo esc_attr__('Motivo', 'mx-pos-pro'); ?>"
                                        style="width:100px;padding:3px 6px;font-size:12px;"
                                        maxlength="500"
                                    />
                                    <button type="submit" class="button button-small" style="color:#d63638;border-color:#d63638;">
                                        <?php esc_html_e('Anular', 'mx-pos-pro'); ?>
                                    </button>
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:4px;align-items:center;">
                                    <?php wp_nonce_field(self::REMOTE_CLOSE_SESSION_NONCE_ACTION, self::REMOTE_CLOSE_SESSION_NONCE_NAME); ?>
                                    <input type="hidden" name="action" value="mx_pos_remote_close_session" />
                                    <input type="hidden" name="session_id" value="<?php echo esc_attr((string) $session['id']); ?>" />
                                    <input
                                        type="text"
                                        name="remote_reason"
                                        required
                                        placeholder="<?php echo esc_attr__('Motivo del cierre remoto', 'mx-pos-pro'); ?>"
                                        style="width:130px;padding:3px 6px;font-size:12px;"
                                        maxlength="500"
                                    />
                                    <button type="submit" class="button button-small" style="color:#2271b1;border-color:#2271b1;">
                                        <?php esc_html_e('Cerrar remotamente', 'mx-pos-pro'); ?>
                                    </button>
                                </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // ─── Métodos de pago ──────────────────────────────────────────────────────

    private function render_metodos_pago(): void
    {
        $repo        = new PaymentMethodRepository();
        $methods     = $repo->get_all();
        $gateways    = $repo->get_woocommerce_gateways();
        $edit_method = null;

        if (isset($_GET['edit_payment_method'])) {
            $edit_id = (int) $_GET['edit_payment_method'];
            $edit_method = $repo->get_by_id($edit_id);
        }

        ?>
        <h2><?php esc_html_e('Métodos de pago', 'mx-pos-pro'); ?></h2>
        <p class="description">
            <?php esc_html_e('Catálogo propio de métodos de pago del POS. Los métodos inactivos no se muestran en el POS.', 'mx-pos-pro'); ?>
        </p>

        <?php if (isset($_GET['payment_method_created'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Método de pago creado.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['payment_method_updated'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Método de pago actualizado.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['payment_method_toggled'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Estado del método de pago actualizado.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['payment_gateway_imported'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Gateway WooCommerce importado como método POS.', 'mx-pos-pro'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['payment_method_error'])): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['payment_method_error']))); ?></p>
            </div>
        <?php endif; ?>

        <p>
            <?php if ($edit_method): ?>
                <a href="<?php echo esc_url(add_query_arg('tab', 'metodos_pago', admin_url('admin.php?page=' . self::MENU_SLUG))); ?>"
                   class="button">
                    <?php esc_html_e('Cancelar edición', 'mx-pos-pro'); ?>
                </a>
            <?php else: ?>
                <button type="button" id="mx-pos-toggle-payment-method-form" class="button button-primary">
                    <?php esc_html_e('Agregar método', 'mx-pos-pro'); ?>
                </button>
            <?php endif; ?>
        </p>

        <?php
        $edit_slug = $edit_method['slug'] ?? '';
        $is_protected_edit = $edit_method && in_array($edit_slug, self::PROTECTED_PAYMENT_METHOD_SLUGS, true);
        $form_payment_type = $edit_method['payment_type'] ?? 'other';
        $form_affects_cash = (int) ($edit_method['affects_cash_register'] ?? 0) === 1;

        if ($is_protected_edit) {
            if ($edit_slug === 'cash') {
                $form_payment_type = 'cash';
                $form_affects_cash = true;
            } elseif ($edit_slug === 'card') {
                $form_payment_type = 'card';
                $form_affects_cash = false;
            } elseif ($edit_slug === 'mixed') {
                $form_payment_type = 'mixed';
                $form_affects_cash = true;
            }
        }
        ?>

        <div id="mx-pos-payment-method-form"
             style="display:<?php echo $edit_method || isset($_GET['show_payment_method_form']) ? 'block' : 'none'; ?>;">
            <div style="background:#fff; border:1px solid #c3c4c7; padding:16px 24px; margin-bottom:16px; max-width:820px;">
                <h3>
                    <?php echo $edit_method
                        ? esc_html__('Editar método de pago', 'mx-pos-pro')
                        : esc_html__('Agregar método de pago', 'mx-pos-pro'); ?>
                </h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mx_pos_manage_payment_method" />
                    <input type="hidden" name="do" value="<?php echo $edit_method ? 'payment_method_update' : 'payment_method_create'; ?>" />
                    <?php if ($edit_method): ?>
                        <input type="hidden" name="payment_method_id" value="<?php echo esc_attr((string) $edit_method['id']); ?>" />
                    <?php endif; ?>
                    <?php wp_nonce_field(self::PAYMENT_METHOD_NONCE_ACTION, self::PAYMENT_METHOD_NONCE_NAME); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_payment_method_name"><?php esc_html_e('Nombre', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="mx_pos_payment_method_name"
                                       name="payment_method_name"
                                       class="regular-text"
                                       maxlength="100"
                                       required
                                       value="<?php echo esc_attr($edit_method['name'] ?? ''); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_payment_method_slug"><?php esc_html_e('Slug', 'mx-pos-pro'); ?> <span style="color:#d63638;">*</span></label>
                            </th>
                            <td>
                                <?php if ($edit_method): ?>
                                    <input type="text"
                                           id="mx_pos_payment_method_slug"
                                           class="regular-text"
                                           disabled
                                           value="<?php echo esc_attr($edit_method['slug']); ?>" />
                                    <p class="description"><?php esc_html_e('El slug no se modifica en este sprint para conservar compatibilidad histórica.', 'mx-pos-pro'); ?></p>
                                <?php else: ?>
                                    <input type="text"
                                           id="mx_pos_payment_method_slug"
                                           name="payment_method_slug"
                                           class="regular-text"
                                           maxlength="50"
                                           pattern="[a-z0-9_-]+"
                                           required />
                                    <p class="description"><?php esc_html_e('Único. Solo minúsculas, números, guiones y guiones bajos.', 'mx-pos-pro'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_payment_method_type"><?php esc_html_e('Tipo', 'mx-pos-pro'); ?></label>
                            </th>
                            <td>
                                <select id="mx_pos_payment_method_type"
                                        name="payment_method_type"
                                        <?php disabled($is_protected_edit); ?>>
                                    <?php foreach (self::PAYMENT_METHOD_TYPES as $type): ?>
                                        <option value="<?php echo esc_attr($type); ?>" <?php selected($form_payment_type, $type); ?>>
                                            <?php echo esc_html($type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($is_protected_edit): ?>
                                    <p class="description"><?php esc_html_e('El tipo de los métodos base no puede cambiarse.', 'mx-pos-pro'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Afecta caja', 'mx-pos-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="payment_method_affects_cash_register"
                                           value="1"
                                           <?php checked($form_affects_cash); ?>
                                           <?php disabled($is_protected_edit); ?> />
                                    <?php esc_html_e('Cuenta como movimiento de caja.', 'mx-pos-pro'); ?>
                                </label>
                                <?php if ($is_protected_edit): ?>
                                    <p class="description"><?php esc_html_e('Regla fija para métodos base: cash y mixed afectan caja; card no afecta caja.', 'mx-pos-pro'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Permite referencia', 'mx-pos-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="payment_method_allow_reference"
                                           value="1"
                                           <?php checked((int) ($edit_method['allow_reference'] ?? 0) === 1); ?> />
                                    <?php esc_html_e('Permite capturar referencia o folio opcional.', 'mx-pos-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Comisión de tarjeta', 'mx-pos-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="payment_method_card_fee_enabled"
                                           value="1"
                                           <?php checked((int) ($edit_method['card_fee_enabled'] ?? 0) === 1); ?> />
                                    <?php esc_html_e('Aplicar comisión', 'mx-pos-pro'); ?>
                                </label>
                                <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                    <select name="payment_method_card_fee_type">
                                        <option value="percentage" <?php selected($edit_method['card_fee_type'] ?? 'percentage', 'percentage'); ?>>
                                            <?php esc_html_e('Porcentaje', 'mx-pos-pro'); ?>
                                        </option>
                                        <option value="fixed" <?php selected($edit_method['card_fee_type'] ?? '', 'fixed'); ?>>
                                            <?php esc_html_e('Fija', 'mx-pos-pro'); ?>
                                        </option>
                                    </select>
                                    <input type="number"
                                           name="payment_method_card_fee_value"
                                           min="0"
                                           step="0.0001"
                                           value="<?php echo esc_attr((string) ($edit_method['card_fee_value'] ?? '0')); ?>"
                                           style="width:120px;" />
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="mx_pos_payment_method_sort_order"><?php esc_html_e('Orden', 'mx-pos-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                       id="mx_pos_payment_method_sort_order"
                                       name="payment_method_sort_order"
                                       min="0"
                                       step="1"
                                       value="<?php echo esc_attr((string) ($edit_method['sort_order'] ?? 0)); ?>"
                                       style="width:100px;" />
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php echo $edit_method
                                ? esc_html__('Guardar cambios', 'mx-pos-pro')
                                : esc_html__('Crear método', 'mx-pos-pro'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <?php if (empty($methods)): ?>
            <div class="notice notice-warning inline">
                <p><?php esc_html_e('No hay métodos de pago registrados. Ejecute la reactivación del plugin para crearlos.', 'mx-pos-pro'); ?></p>
            </div>
            <?php return; ?>
        <?php endif; ?>

        <table class="widefat striped" style="max-width: 1180px;">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th><?php esc_html_e('Nombre', 'mx-pos-pro'); ?></th>
                    <th style="width:140px;"><?php esc_html_e('Slug', 'mx-pos-pro'); ?></th>
                    <th style="width:110px;"><?php esc_html_e('Tipo', 'mx-pos-pro'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Afecta caja', 'mx-pos-pro'); ?></th>
                    <th style="width: 130px;"><?php esc_html_e('Permite referencia', 'mx-pos-pro'); ?></th>
                    <th style="width: 140px;"><?php esc_html_e('Comisión tarjeta', 'mx-pos-pro'); ?></th>
                    <th style="width: 90px;"><?php esc_html_e('Estado', 'mx-pos-pro'); ?></th>
                    <th style="width: 70px;"><?php esc_html_e('Orden', 'mx-pos-pro'); ?></th>
                    <th style="width: 180px;"><?php esc_html_e('Acciones', 'mx-pos-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($methods as $method):
                    $is_protected = in_array($method['slug'], self::PROTECTED_PAYMENT_METHOD_SLUGS, true);
                    $is_active = (int) $method['is_active'] === 1;
                    $edit_url = add_query_arg([
                        'page'                => self::MENU_SLUG,
                        'tab'                 => 'metodos_pago',
                        'edit_payment_method' => (int) $method['id'],
                    ], admin_url('admin.php'));
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) $method['id']); ?></td>
                        <td><strong><?php echo esc_html($method['name']); ?></strong></td>
                        <td style="white-space:nowrap;"><code><?php echo esc_html($method['slug']); ?></code></td>
                        <td><?php echo esc_html($method['payment_type'] ?? 'other'); ?></td>
                        <td>
                            <?php if ((int) $method['affects_cash_register'] === 1): ?>
                                <?php esc_html_e('Sí', 'mx-pos-pro'); ?>
                            <?php else: ?>
                                <?php esc_html_e('No', 'mx-pos-pro'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int) ($method['allow_reference'] ?? 0) === 1 ? esc_html__('Sí', 'mx-pos-pro') : esc_html__('No', 'mx-pos-pro'); ?></td>
                        <td>
                            <?php if ((int) ($method['card_fee_enabled'] ?? 0) === 1): ?>
                                <?php
                                $fee_type = ($method['card_fee_type'] ?? '') === 'fixed'
                                    ? __('Fija', 'mx-pos-pro')
                                    : __('Porcentaje', 'mx-pos-pro');
                                echo esc_html($fee_type . ': ' . number_format((float) ($method['card_fee_value'] ?? 0), 4));
                                ?>
                            <?php else: ?>
                                <?php esc_html_e('Sin comisión', 'mx-pos-pro'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_active): ?>
                                <?php esc_html_e('Activo', 'mx-pos-pro'); ?>
                            <?php else: ?>
                                <?php esc_html_e('Inactivo', 'mx-pos-pro'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) $method['sort_order']); ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                <?php esc_html_e('Editar', 'mx-pos-pro'); ?>
                            </a>
                            <?php if ($is_protected): ?>
                                <span style="color:#646970;font-size:12px;"><?php esc_html_e('Protegido', 'mx-pos-pro'); ?></span>
                            <?php else: ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="mx_pos_manage_payment_method" />
                                    <input type="hidden" name="do" value="payment_method_toggle" />
                                    <input type="hidden" name="payment_method_id" value="<?php echo esc_attr((string) $method['id']); ?>" />
                                    <input type="hidden" name="payment_method_active" value="<?php echo $is_active ? '0' : '1'; ?>" />
                                    <?php wp_nonce_field(self::PAYMENT_METHOD_NONCE_ACTION, self::PAYMENT_METHOD_NONCE_NAME); ?>
                                    <button type="submit" class="button button-small">
                                        <?php echo $is_active
                                            ? esc_html__('Desactivar', 'mx-pos-pro')
                                            : esc_html__('Activar', 'mx-pos-pro'); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3 style="margin-top:24px;"><?php esc_html_e('Gateways WooCommerce disponibles', 'mx-pos-pro'); ?></h3>
        <p class="description">
            <?php esc_html_e('Listado informativo de gateways WooCommerce. Importarlos solo crea un método POS asociado; no ejecuta cobros online.', 'mx-pos-pro'); ?>
        </p>
        <?php if (empty($gateways)): ?>
            <p><?php esc_html_e('No hay gateways WooCommerce disponibles.', 'mx-pos-pro'); ?></p>
        <?php else: ?>
            <?php
            $imported_gateway_ids = array_filter(array_map(static function ($method) {
                return $method['wc_gateway_id'] ?? null;
            }, $methods));
            $method_slugs = array_column($methods, 'slug');
            ?>
            <table class="widefat striped" style="max-width: 960px;">
                <thead>
                    <tr>
                        <th style="width:160px;"><?php esc_html_e('ID gateway', 'mx-pos-pro'); ?></th>
                        <th><?php esc_html_e('Título', 'mx-pos-pro'); ?></th>
                        <th style="width:120px;"><?php esc_html_e('Estado WC', 'mx-pos-pro'); ?></th>
                        <th style="width:170px;"><?php esc_html_e('Acción', 'mx-pos-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gateways as $gateway):
                        $gateway_id = (string) ($gateway['id'] ?? '');
                        $gateway_slug = substr('wc_' . sanitize_key($gateway_id), 0, 50);
                        $already_imported = in_array($gateway_id, $imported_gateway_ids, true) || in_array($gateway_slug, $method_slugs, true);
                        ?>
                        <tr>
                            <td style="white-space:nowrap;"><code><?php echo esc_html($gateway_id); ?></code></td>
                            <td>
                                <strong><?php echo esc_html($gateway['title'] ?: ($gateway['method_title'] ?? $gateway_id)); ?></strong>
                                <?php if (! empty($gateway['method_title'])): ?>
                                    <br /><span class="description"><?php echo esc_html($gateway['method_title']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo ! empty($gateway['enabled']) ? esc_html__('Activo', 'mx-pos-pro') : esc_html__('Inactivo', 'mx-pos-pro'); ?></td>
                            <td>
                                <?php if ($already_imported): ?>
                                    <span style="color:#646970;"><?php esc_html_e('Importado', 'mx-pos-pro'); ?></span>
                                <?php else: ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="mx_pos_manage_payment_method" />
                                        <input type="hidden" name="do" value="payment_gateway_import" />
                                        <input type="hidden" name="gateway_id" value="<?php echo esc_attr($gateway_id); ?>" />
                                        <?php wp_nonce_field(self::PAYMENT_METHOD_NONCE_ACTION, self::PAYMENT_METHOD_NONCE_NAME); ?>
                                        <button type="submit" class="button button-small">
                                            <?php esc_html_e('Importar como método POS', 'mx-pos-pro'); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <script>
        (function () {
            var btn = document.getElementById('mx-pos-toggle-payment-method-form');
            var form = document.getElementById('mx-pos-payment-method-form');
            if (btn && form) {
                btn.addEventListener('click', function () {
                    if (form.style.display === 'none' || form.style.display === '') {
                        form.style.display = 'block';
                        btn.textContent = '<?php echo esc_js(__('Cancelar', 'mx-pos-pro')); ?>';
                    } else {
                        form.style.display = 'none';
                        btn.textContent = '<?php echo esc_js(__('Agregar método', 'mx-pos-pro')); ?>';
                    }
                });
            }
        })();
        </script>
        <?php
    }

    // ─── Reportes ─────────────────────────────────────────────────────────────

    private function render_reportes(): void
    {
        global $wpdb;

        $sales_total    = (float) $wpdb->get_var("SELECT COALESCE(SUM(total), 0) FROM `{$wpdb->prefix}mx_pos_sales`");
        $sales_count    = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}mx_pos_sales`");
        $refunds_total  = (float) $wpdb->get_var("SELECT COALESCE(SUM(refund_amount), 0) FROM `{$wpdb->prefix}mx_pos_refunds`");
        $refunds_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}mx_pos_refunds`");
        $cuts_count     = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}mx_pos_cash_cuts`");
        $zcuts_count    = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}mx_pos_cash_cuts` WHERE cut_type = 'Z'"
        );

        $cutRepo    = new CashCutRepository();
        $cutFilters = [];

        $filterType = isset($_GET['cut_type']) && in_array($_GET['cut_type'], ['X', 'Z'], true)
            ? sanitize_text_field(wp_unslash($_GET['cut_type']))
            : null;
        if ($filterType !== null) {
            $cutFilters['cut_type'] = $filterType;
        }

        $filterDateFrom = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : null;
        if ($filterDateFrom !== null && $filterDateFrom !== '') {
            $cutFilters['date_from'] = $filterDateFrom;
        }

        $filterDateTo = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : null;
        if ($filterDateTo !== null && $filterDateTo !== '') {
            $cutFilters['date_to'] = $filterDateTo;
        }

        $page    = isset($_GET['cut_page']) ? max(1, (int) $_GET['cut_page']) : 1;
        $perPage = 20;
        $cutList = $cutRepo->list_all($cutFilters, $page, $perPage);

        $open_sessions = $wpdb->get_results(
            "SELECT * FROM `{$wpdb->prefix}mx_pos_sessions` WHERE status = 'open' ORDER BY opened_at DESC",
            ARRAY_A
        );

        $restUrl   = rest_url('mx-pos/v1/cuts/');
        $restNonce = wp_create_nonce('wp_rest');
        $baseUrl   = admin_url('admin.php?page=mx-pos-pro&tab=reportes');

        ?>
        <h2><?php esc_html_e('Reportes', 'mx-pos-pro'); ?></h2>

        <div class="mx-pos-section">
            <h3><?php esc_html_e('Resumen de ventas', 'mx-pos-pro'); ?></h3>
            <table class="widefat striped" style="max-width: 640px;">
                <tbody>
                    <tr>
                        <th scope="row" style="width: 240px;"><?php esc_html_e('Total de ventas', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $sales_count); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Ingresos totales', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html(number_format((float) $sales_total, 2)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Total de devoluciones', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $refunds_count); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Monto devuelto', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html(number_format((float) $refunds_total, 2)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Total de cierres', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $cuts_count); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Cierres', 'mx-pos-pro'); ?></th>
                        <td><?php echo esc_html((string) $zcuts_count); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mx-pos-section">
            <h3><?php esc_html_e('Cierres anteriores', 'mx-pos-pro'); ?></h3>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-bottom: 16px;">
                <input type="hidden" name="page" value="mx-pos-pro" />
                <input type="hidden" name="tab" value="reportes" />
                <label style="margin-right: 8px;">
                    <?php esc_html_e('Tipo:', 'mx-pos-pro'); ?>
                    <select name="cut_type">
                        <option value=""><?php esc_html_e('Todos', 'mx-pos-pro'); ?></option>
                        <option value="X" <?php selected($filterType, 'X'); ?>><?php esc_html_e('Pre-corte', 'mx-pos-pro'); ?></option>
                        <option value="Z" <?php selected($filterType, 'Z'); ?>><?php esc_html_e('Cierre', 'mx-pos-pro'); ?></option>
                    </select>
                </label>
                <label style="margin-right: 8px;">
                    <?php esc_html_e('Desde:', 'mx-pos-pro'); ?>
                    <input type="date" name="date_from" value="<?php echo esc_attr($filterDateFrom ?? ''); ?>" />
                </label>
                <label style="margin-right: 8px;">
                    <?php esc_html_e('Hasta:', 'mx-pos-pro'); ?>
                    <input type="date" name="date_to" value="<?php echo esc_attr($filterDateTo ?? ''); ?>" />
                </label>
                <button type="submit" class="button"><?php esc_html_e('Filtrar', 'mx-pos-pro'); ?></button>
            </form>

            <?php if (empty($cutList['cuts'])): ?>
                <p><?php esc_html_e('No se encontraron cierres.', 'mx-pos-pro'); ?></p>
            <?php else: ?>
                <table class="widefat striped" style="max-width: 960px;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?php esc_html_e('Tipo', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Sesión', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Generado por', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Fecha', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Definitivo', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Acciones', 'mx-pos-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cutList['cuts'] as $cut): ?>
                            <tr>
                                <td><?php echo esc_html((string) $cut['id']); ?></td>
                                <td>
                                    <?php echo $cut['cut_type'] === 'Z'
                                        ? '<strong>' . esc_html__('Cierre', 'mx-pos-pro') . '</strong>'
                                        : esc_html__('Pre-corte', 'mx-pos-pro'); ?>
                                </td>
                                <td>#<?php echo esc_html((string) $cut['session_id']); ?></td>
                                <td><?php echo esc_html($cut['generated_by']); ?></td>
                                <td><?php echo esc_html($cut['generated_at']); ?></td>
                                <td><?php echo $cut['is_final'] ? esc_html__('Sí', 'mx-pos-pro') : esc_html__('No', 'mx-pos-pro'); ?></td>
                                <td>
                                    <button type="button"
                                            class="button button-small mx-pos-reprint-cut"
                                            data-cut-id="<?php echo esc_attr((string) $cut['id']); ?>">
                                        <?php esc_html_e('Reimprimir', 'mx-pos-pro'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                $totalPages = max(1, (int) ceil($cutList['total'] / $perPage));
                if ($totalPages > 1):
                    $queryBase = $baseUrl;
                    if ($filterType) $queryBase = add_query_arg('cut_type', $filterType, $queryBase);
                    if ($filterDateFrom) $queryBase = add_query_arg('date_from', $filterDateFrom, $queryBase);
                    if ($filterDateTo) $queryBase = add_query_arg('date_to', $filterDateTo, $queryBase);
                ?>
                    <div style="margin-top: 12px;">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php if ($p === $page): ?>
                                <strong style="margin: 0 4px;"><?php echo esc_html((string) $p); ?></strong>
                            <?php else: ?>
                                <a href="<?php echo esc_url(add_query_arg('cut_page', $p, $queryBase)); ?>"
                                   style="margin: 0 4px;"><?php echo esc_html((string) $p); ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <script>
        (function() {
            var restUrl = <?php echo wp_json_encode($restUrl); ?>;
            var restNonce = <?php echo wp_json_encode($restNonce); ?>;

            function reprintCut(cutId) {
                var url = restUrl + cutId + '/ticket';
                var win = window.open('', '_blank', 'width=420,height=760');
                if (!win) {
                    alert('<?php echo esc_js(__('No se pudo abrir la ventana de impresión. Permite ventanas emergentes.', 'mx-pos-pro')); ?>');
                    return;
                }

                fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': restNonce,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Error al obtener el ticket');
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (!data || typeof data.html !== 'string') {
                        throw new Error('Respuesta inválida');
                    }
                    if (data.html.indexOf('data-ticket-width="58mm"') !== -1) {
                        try { win.resizeTo(340, 680); } catch (e) {}
                    }
                    win.document.write(data.html);
                    win.document.close();
                    win.focus();
                    var fontReady = win.document.fonts && win.document.fonts.ready
                        ? win.document.fonts.ready.catch(function() {})
                        : Promise.resolve();
                    var imageReady = Array.prototype.slice.call(win.document.images).map(function(image) {
                        if (image.complete) {
                            return Promise.resolve();
                        }
                        return new Promise(function(resolve) {
                            image.addEventListener('load', resolve, { once: true });
                            image.addEventListener('error', resolve, { once: true });
                        });
                    });
                    Promise.race([
                        Promise.all([fontReady].concat(imageReady)),
                        new Promise(function(resolve) { setTimeout(resolve, 900); })
                    ]).then(function() { win.print(); });
                    win.addEventListener('afterprint', function() { win.close(); });
                })
                .catch(function(err) {
                    alert('Error: ' + (err.message || 'No se pudo generar el ticket'));
                    win.close();
                });
            }

            document.querySelectorAll('.mx-pos-reprint-cut').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var cutId = parseInt(this.getAttribute('data-cut-id'), 10);
                    if (cutId > 0) {
                        reprintCut(cutId);
                    }
                });
            });
        })();
        </script>

        <?php if (! empty($open_sessions)): ?>
            <div class="mx-pos-section">
                <h3>
                    <?php esc_html_e('Sesiones abiertas', 'mx-pos-pro'); ?>
                    <span style="color:#dba617;">(<?php echo esc_html((string) count($open_sessions)); ?>)</span>
                </h3>
                <table class="widefat striped" style="max-width: 640px;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?php esc_html_e('Cajero', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Monto inicial', 'mx-pos-pro'); ?></th>
                            <th><?php esc_html_e('Abierta desde', 'mx-pos-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($open_sessions as $session): ?>
                            <tr>
                                <td><?php echo esc_html((string) $session['id']); ?></td>
                                <td><?php echo esc_html((string) $session['cashier_id']); ?></td>
                                <td><?php echo esc_html(number_format((float) $session['opening_amount'], 2)); ?></td>
                                <td><?php echo esc_html($session['opened_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php
    }

    // ─── Configuración ────────────────────────────────────────────────────────

    private function render_configuracion(): void
    {
        $telegram_enabled = get_option('mx_pos_telegram_enabled', 'no') === 'yes';
        $telegram_token = (string) get_option('mx_pos_telegram_bot_token', '');
        $telegram_group_id = (string) get_option('mx_pos_telegram_group_id', '');
        if ($telegram_group_id === '') {
            $telegram_group_id = (string) get_option('mx_pos_telegram_chat_id', '');
        }

        $business_name = (string) get_option('mx_pos_ticket_business_name', '');
        $footer_text = (string) get_option('mx_pos_ticket_footer_text', '');
        $ticket_paper_width = (string) get_option('mx_pos_ticket_paper_width', '80mm');
        if (! in_array($ticket_paper_width, ['80mm', '58mm'], true)) {
            $ticket_paper_width = '80mm';
        }
        $show_logo = get_option('mx_pos_ticket_show_logo', 'no') === 'yes';
        $logo_attachment_id = (int) get_option('mx_pos_ticket_logo_attachment_id', 0);
        $apply_logo_to_sales = get_option('mx_pos_ticket_apply_logo_to_sales', 'yes') === 'yes';
        $apply_logo_to_cuts = get_option('mx_pos_ticket_apply_logo_to_cuts', 'no') === 'yes';
        $show_store_info = get_option('mx_pos_ticket_show_store_info', 'yes') === 'yes';
        $show_cashier = get_option('mx_pos_ticket_show_cashier', 'yes') === 'yes';
        $show_payment_method = get_option('mx_pos_ticket_show_payment_method', 'yes') === 'yes';
        $beep_enabled = get_option('mx_pos_beep_enabled', 'yes') === 'yes';

        $masked_token = $this->mask_token($telegram_token);
        $logo_preview_html = $this->get_logo_preview_html($logo_attachment_id);

        $admin_post_url = esc_url(admin_url('admin-post.php'));

        ?>
        <?php if (isset($_GET['saved'])): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Settings saved successfully.', 'mx-pos-pro'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['telegram_test'])): ?>
            <?php if ($_GET['telegram_test'] === 'ok'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Telegram test message sent successfully.', 'mx-pos-pro'); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <?php esc_html_e('Telegram test failed.', 'mx-pos-pro'); ?>
                        <?php if (! empty($_GET['telegram_error'])): ?>
                            <?php echo esc_html(sanitize_text_field(wp_unslash($_GET['telegram_error']))); ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url($admin_post_url); ?>" class="mx-pos-settings-form">
            <input type="hidden" name="action" value="mx_pos_save_settings" />
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>

            <div class="mx-pos-section">
                <h2><?php esc_html_e('Telegram', 'mx-pos-pro'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Configure Telegram notifications for POS events (session open/close, cancellations, refunds, Z-cuts).', 'mx-pos-pro'); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="mx_pos_telegram_enabled">
                                <?php esc_html_e('Enable Telegram', 'mx-pos-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox"
                                   id="mx_pos_telegram_enabled"
                                   name="mx_pos_telegram_enabled"
                                   value="yes"
                                   <?php checked($telegram_enabled); ?> />
                            <label for="mx_pos_telegram_enabled">
                                <?php esc_html_e('Send notifications via Telegram', 'mx-pos-pro'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mx_pos_telegram_bot_token">
                                <?php esc_html_e('Bot Token', 'mx-pos-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="password"
                                   id="mx_pos_telegram_bot_token"
                                   name="mx_pos_telegram_bot_token"
                                   class="regular-text"
                                   placeholder="<?php echo $masked_token !== '' ? esc_attr(sprintf(__('Current: %s', 'mx-pos-pro'), $masked_token)) : ''; ?>"
                                   value=""
                                   autocomplete="off" />
                            <?php if ($masked_token !== ''): ?>
                                <p class="description">
                                    <?php echo esc_html(sprintf(__('Current token: %s', 'mx-pos-pro'), $masked_token)); ?>
                                </p>
                                <p class="description">
                                    <label>
                                        <input type="checkbox" name="mx_pos_clear_telegram_token" value="1" />
                                        <?php esc_html_e('Remove current token', 'mx-pos-pro'); ?>
                                    </label>
                                </p>
                            <?php endif; ?>
                            <p class="description">
                                <?php esc_html_e('Leave empty to keep the current token. Enter a new token to replace it.', 'mx-pos-pro'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mx_pos_telegram_group_id">
                                <?php esc_html_e('Telegram Group / Chat ID', 'mx-pos-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="mx_pos_telegram_group_id"
                                   name="mx_pos_telegram_group_id"
                                   class="regular-text"
                                   value="<?php echo esc_attr($telegram_group_id); ?>" />
                            <p class="description">
                                <?php esc_html_e('The Telegram group, supergroup, or direct chat ID where notifications will be sent. Group IDs are usually negative, for example -1001234567890.', 'mx-pos-pro'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit" style="margin-bottom: 0;">
                    <button type="submit"
                            class="button button-secondary"
                            formaction="<?php echo esc_url(add_query_arg('action', 'mx_pos_test_telegram', $admin_post_url)); ?>">
                        <?php esc_html_e('Test Telegram', 'mx-pos-pro'); ?>
                    </button>
                </p>

                <p class="description mx-pos-privacy-note">
                    <?php esc_html_e('The token is stored in WordPress options, is not exposed in the POS, and is only shown masked on this screen.', 'mx-pos-pro'); ?>
                </p>
            </div>

            <div class="mx-pos-section">
                <h2><?php esc_html_e('Ticket térmico', 'mx-pos-pro'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Customize the content and appearance of printed tickets.', 'mx-pos-pro'); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="mx_pos_ticket_paper_width">
                                <?php esc_html_e('Ancho de papel', 'mx-pos-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="mx_pos_ticket_paper_width" name="mx_pos_ticket_paper_width">
                                <option value="80mm" <?php selected($ticket_paper_width, '80mm'); ?>>
                                    <?php esc_html_e('80mm recomendado', 'mx-pos-pro'); ?>
                                </option>
                                <option value="58mm" <?php selected($ticket_paper_width, '58mm'); ?>>
                                    <?php esc_html_e('58mm compacto', 'mx-pos-pro'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('80mm usa texto más grande y mejor espaciado. 58mm conserva compatibilidad con impresoras pequeñas.', 'mx-pos-pro'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mx_pos_ticket_business_name">
                                <?php esc_html_e('Business Name', 'mx-pos-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="mx_pos_ticket_business_name"
                                   name="mx_pos_ticket_business_name"
                                   class="regular-text"
                                   maxlength="100"
                                   value="<?php echo esc_attr($business_name); ?>"
                                   placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" />
                            <p class="description">
                                <?php esc_html_e('Shown at the top of the ticket. Leave empty to use the site name.', 'mx-pos-pro'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mx_pos_ticket_footer_text">
                                <?php esc_html_e('Footer Text', 'mx-pos-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="mx_pos_ticket_footer_text"
                                      name="mx_pos_ticket_footer_text"
                                      class="regular-text"
                                      maxlength="200"
                                      rows="2"><?php echo esc_textarea($footer_text); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Plain text only. Shown at the bottom of the ticket. Leave empty for the default message.', 'mx-pos-pro'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 16px 0; border-color: #dcdcde;">

                <h3><?php esc_html_e('Logo', 'mx-pos-pro'); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Show Logo', 'mx-pos-pro'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="mx_pos_ticket_show_logo"
                                       name="mx_pos_ticket_show_logo"
                                       value="yes"
                                       <?php checked($show_logo); ?> />
                                <?php esc_html_e('Display logo on tickets', 'mx-pos-pro'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Select Logo', 'mx-pos-pro'); ?>
                        </th>
                        <td>
                            <input type="hidden"
                                   id="mx_pos_ticket_logo_attachment_id"
                                   name="mx_pos_ticket_logo_attachment_id"
                                   value="<?php echo esc_attr((string) $logo_attachment_id); ?>" />

                            <div id="mx-pos-logo-preview" class="mx-pos-logo-preview">
                                <?php echo $logo_preview_html; ?>
                            </div>

                            <p style="margin-top: 8px;">
                                <button type="button"
                                        id="mx-pos-select-logo"
                                        class="button">
                                    <?php esc_html_e('Select Logo', 'mx-pos-pro'); ?>
                                </button>
                                <button type="button"
                                        id="mx-pos-remove-logo"
                                        class="button"
                                        <?php echo $logo_attachment_id > 0 ? '' : 'style="display:none;"'; ?>>
                                    <?php esc_html_e('Remove Logo', 'mx-pos-pro'); ?>
                                </button>
                            </p>
                            <p class="description" style="margin-top: 8px;">
                                <?php esc_html_e('For best thermal printing results, use monochrome or high-contrast logos.', 'mx-pos-pro'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Apply Logo To', 'mx-pos-pro'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label style="display: block; margin-bottom: 4px;">
                                    <input type="checkbox"
                                           name="mx_pos_ticket_apply_logo_to_sales"
                                           value="yes"
                                           <?php checked($apply_logo_to_sales); ?> />
                                    <?php esc_html_e('Sales tickets', 'mx-pos-pro'); ?>
                                </label>
                                <label style="display: block;">
                                    <input type="checkbox"
                                           name="mx_pos_ticket_apply_logo_to_cuts"
                                           value="yes"
                                           <?php checked($apply_logo_to_cuts); ?> />
                                    <?php esc_html_e('X/Z cut tickets', 'mx-pos-pro'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 16px 0; border-color: #dcdcde;">

                <h3><?php esc_html_e('Visibility', 'mx-pos-pro'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Choose which information appears on printed tickets.', 'mx-pos-pro'); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Show on Ticket', 'mx-pos-pro'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label style="display: block; margin-bottom: 4px;">
                                    <input type="checkbox"
                                           name="mx_pos_ticket_show_store_info"
                                           value="yes"
                                           <?php checked($show_store_info); ?> />
                                    <?php esc_html_e('Store name', 'mx-pos-pro'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 4px;">
                                    <input type="checkbox"
                                           name="mx_pos_ticket_show_cashier"
                                           value="yes"
                                           <?php checked($show_cashier); ?> />
                                    <?php esc_html_e('Cashier name', 'mx-pos-pro'); ?>
                                </label>
                                <label style="display: block;">
                                    <input type="checkbox"
                                           name="mx_pos_ticket_show_payment_method"
                                           value="yes"
                                           <?php checked($show_payment_method); ?> />
                                    <?php esc_html_e('Payment method', 'mx-pos-pro'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="mx-pos-section">
                <h2><?php esc_html_e('Feedback sensorial', 'mx-pos-pro'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Ajusta la confirmación sonora al agregar productos al carrito en el POS.', 'mx-pos-pro'); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="mx_pos_beep_enabled">
                                <?php esc_html_e('Beep al agregar producto', 'mx-pos-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox"
                                   id="mx_pos_beep_enabled"
                                   name="mx_pos_beep_enabled"
                                   value="yes"
                                   <?php checked($beep_enabled); ?> />
                            <label for="mx_pos_beep_enabled">
                                <?php esc_html_e('Emitir beep al agregar productos al carrito', 'mx-pos-pro'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Save Settings', 'mx-pos-pro'); ?>
                </button>
            </p>
        </form>
        <?php
    }

    // ─── Save Handler ─────────────────────────────────────────────────────────

    public function handle_save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mx-pos-pro'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        $enabled = isset($_POST['mx_pos_telegram_enabled']) && $_POST['mx_pos_telegram_enabled'] === 'yes'
            ? 'yes'
            : 'no';
        update_option('mx_pos_telegram_enabled', $enabled);

        $clear_token = isset($_POST['mx_pos_clear_telegram_token']);
        $new_token = isset($_POST['mx_pos_telegram_bot_token'])
            ? sanitize_text_field(wp_unslash($_POST['mx_pos_telegram_bot_token']))
            : '';

        if ($clear_token) {
            update_option('mx_pos_telegram_bot_token', '');
        } elseif ($new_token !== '') {
            update_option('mx_pos_telegram_bot_token', $new_token);
        }

        $telegram_destination_id = isset($_POST['mx_pos_telegram_group_id'])
            ? sanitize_text_field(wp_unslash($_POST['mx_pos_telegram_group_id']))
            : '';

        if ($telegram_destination_id === '' && isset($_POST['mx_pos_telegram_chat_id'])) {
            $telegram_destination_id = sanitize_text_field(wp_unslash($_POST['mx_pos_telegram_chat_id']));
        }

        update_option('mx_pos_telegram_group_id', $telegram_destination_id);
        update_option('mx_pos_telegram_chat_id', $telegram_destination_id);

        $business_name = isset($_POST['mx_pos_ticket_business_name'])
            ? sanitize_text_field(wp_unslash($_POST['mx_pos_ticket_business_name']))
            : '';
        update_option('mx_pos_ticket_business_name', $business_name);

        $footer_text = isset($_POST['mx_pos_ticket_footer_text'])
            ? sanitize_textarea_field(wp_unslash($_POST['mx_pos_ticket_footer_text']))
            : '';
        update_option('mx_pos_ticket_footer_text', $footer_text);

        $ticket_paper_width = isset($_POST['mx_pos_ticket_paper_width'])
            ? sanitize_text_field(wp_unslash($_POST['mx_pos_ticket_paper_width']))
            : '80mm';
        if (! in_array($ticket_paper_width, ['80mm', '58mm'], true)) {
            $ticket_paper_width = '80mm';
        }
        update_option('mx_pos_ticket_paper_width', $ticket_paper_width);

        $show_logo = isset($_POST['mx_pos_ticket_show_logo']) && $_POST['mx_pos_ticket_show_logo'] === 'yes'
            ? 'yes'
            : 'no';
        update_option('mx_pos_ticket_show_logo', $show_logo);

        $logo_attachment_id = isset($_POST['mx_pos_ticket_logo_attachment_id'])
            ? absint($_POST['mx_pos_ticket_logo_attachment_id'])
            : 0;
        $logo_attachment_id = $this->validate_logo_attachment($logo_attachment_id);
        update_option('mx_pos_ticket_logo_attachment_id', $logo_attachment_id);

        $apply_logo_to_sales = isset($_POST['mx_pos_ticket_apply_logo_to_sales']) && $_POST['mx_pos_ticket_apply_logo_to_sales'] === 'yes'
            ? 'yes'
            : 'no';
        update_option('mx_pos_ticket_apply_logo_to_sales', $apply_logo_to_sales);

        $apply_logo_to_cuts = isset($_POST['mx_pos_ticket_apply_logo_to_cuts']) && $_POST['mx_pos_ticket_apply_logo_to_cuts'] === 'yes'
            ? 'yes'
            : 'no';
        update_option('mx_pos_ticket_apply_logo_to_cuts', $apply_logo_to_cuts);

        $show_store_info = isset($_POST['mx_pos_ticket_show_store_info']) && $_POST['mx_pos_ticket_show_store_info'] === 'yes'
            ? 'yes'
            : 'no';
        update_option('mx_pos_ticket_show_store_info', $show_store_info);

        $show_cashier = isset($_POST['mx_pos_ticket_show_cashier']) && $_POST['mx_pos_ticket_show_cashier'] === 'yes'
            ? 'yes'
            : 'no';
        update_option('mx_pos_ticket_show_cashier', $show_cashier);

        $show_payment_method = isset($_POST['mx_pos_ticket_show_payment_method']) && $_POST['mx_pos_ticket_show_payment_method'] === 'yes'
            ? 'yes'
            : 'no';
        update_option('mx_pos_ticket_show_payment_method', $show_payment_method);

        $beep_enabled = isset($_POST['mx_pos_beep_enabled']) && $_POST['mx_pos_beep_enabled'] === 'yes'
            ? 'yes'
            : 'no';
        update_option('mx_pos_beep_enabled', $beep_enabled);

        $redirect = add_query_arg(
            ['page' => self::MENU_SLUG, 'tab' => 'configuracion', 'saved' => '1'],
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_test_telegram(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mx-pos-pro'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        $enabled = get_option('mx_pos_telegram_enabled', 'no') === 'yes';
        $token = (string) get_option('mx_pos_telegram_bot_token', '');
        $telegram_destination_id = (string) get_option('mx_pos_telegram_group_id', '');
        if ($telegram_destination_id === '') {
            $telegram_destination_id = (string) get_option('mx_pos_telegram_chat_id', '');
        }

        if (! $enabled) {
            $redirect = add_query_arg([
                'page' => self::MENU_SLUG,
                'tab' => 'configuracion',
                'telegram_test' => 'fail',
                'telegram_error' => urlencode(__('Telegram is disabled.', 'mx-pos-pro')),
            ], admin_url('admin.php'));
            wp_safe_redirect($redirect);
            exit;
        }

        if ($token === '' || $telegram_destination_id === '') {
            $redirect = add_query_arg([
                'page' => self::MENU_SLUG,
                'tab' => 'configuracion',
                'telegram_test' => 'fail',
                'telegram_error' => urlencode(__('Bot token and Telegram group/chat ID are required.', 'mx-pos-pro')),
            ], admin_url('admin.php'));
            wp_safe_redirect($redirect);
            exit;
        }

        $result = \MXPOSPro\Notifications\TelegramNotificationService::test_connection($token, $telegram_destination_id);

        if ($result === true) {
            $redirect = add_query_arg([
                'page' => self::MENU_SLUG,
                'tab' => 'configuracion',
                'telegram_test' => 'ok',
            ], admin_url('admin.php'));
        } else {
            $error_msg = is_string($result) ? $result : __('Unknown error.', 'mx-pos-pro');
            $redirect = add_query_arg([
                'page' => self::MENU_SLUG,
                'tab' => 'configuracion',
                'telegram_test' => 'fail',
                'telegram_error' => urlencode($error_msg),
            ], admin_url('admin.php'));
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_payment_method_action(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mx-pos-pro'));
        }

        check_admin_referer(self::PAYMENT_METHOD_NONCE_ACTION, self::PAYMENT_METHOD_NONCE_NAME);

        $do = isset($_POST['do']) ? sanitize_text_field(wp_unslash($_POST['do'])) : '';

        $repo   = new PaymentMethodRepository();
        $params = ['page' => self::MENU_SLUG, 'tab' => 'metodos_pago'];
        $error  = null;

        switch ($do) {
            case 'payment_method_create':
                $data = $this->get_payment_method_form_data(false);

                if (is_wp_error($data)) {
                    $error = $data->get_error_message();
                    break;
                }

                $result = $repo->create($data);

                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                    $params['show_payment_method_form'] = '1';
                    break;
                }

                $params['payment_method_created'] = '1';
                AuditLogger::log('payment_method_created', [
                    'entity_type' => 'payment_method',
                    'entity_id'   => (int) $result['id'],
                    'severity'    => 'info',
                    'message'     => __('Método de pago creado.', 'mx-pos-pro'),
                    'metadata'    => [
                        'name' => $result['name'],
                        'slug' => $result['slug'],
                    ],
                ]);
                break;

            case 'payment_method_update':
                $method_id = isset($_POST['payment_method_id']) ? absint($_POST['payment_method_id']) : 0;
                $method = $repo->get_by_id($method_id);

                if ($method_id <= 0 || $method === null) {
                    $error = __('Método de pago no encontrado.', 'mx-pos-pro');
                    break;
                }

                $data = $this->get_payment_method_form_data(true);

                if (is_wp_error($data)) {
                    $error = $data->get_error_message();
                    $params['edit_payment_method'] = $method_id;
                    break;
                }

                $this->apply_protected_payment_method_rules($method, $data);

                $result = $repo->update($method_id, $data);

                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                    $params['edit_payment_method'] = $method_id;
                    break;
                }

                $params['payment_method_updated'] = '1';
                AuditLogger::log('payment_method_updated', [
                    'entity_type' => 'payment_method',
                    'entity_id'   => $method_id,
                    'severity'    => 'info',
                    'message'     => __('Método de pago actualizado.', 'mx-pos-pro'),
                    'metadata'    => [
                        'name' => $result['name'],
                        'slug' => $result['slug'],
                    ],
                ]);
                break;

            case 'payment_method_toggle':
                $method_id = isset($_POST['payment_method_id']) ? absint($_POST['payment_method_id']) : 0;
                $active = isset($_POST['payment_method_active']) && (string) wp_unslash($_POST['payment_method_active']) === '1';

                if ($method_id <= 0) {
                    $error = __('Método de pago no encontrado.', 'mx-pos-pro');
                    break;
                }

                $result = $repo->set_active($method_id, $active);

                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                    break;
                }

                $params['payment_method_toggled'] = '1';
                AuditLogger::log($active ? 'payment_method_activated' : 'payment_method_deactivated', [
                    'entity_type' => 'payment_method',
                    'entity_id'   => $method_id,
                    'severity'    => 'info',
                    'message'     => $active
                        ? __('Método de pago activado.', 'mx-pos-pro')
                        : __('Método de pago desactivado.', 'mx-pos-pro'),
                    'metadata'    => [
                        'name' => $result['name'],
                        'slug' => $result['slug'],
                    ],
                ]);
                break;

            case 'payment_gateway_import':
                $gateway_id = isset($_POST['gateway_id']) ? sanitize_text_field(wp_unslash($_POST['gateway_id'])) : '';
                $gateway = $this->find_woocommerce_gateway($repo, $gateway_id);

                if ($gateway === null) {
                    $error = __('Gateway WooCommerce no encontrado.', 'mx-pos-pro');
                    break;
                }

                $methods = $repo->get_all();
                $max_sort_order = 0;

                foreach ($methods as $method) {
                    $max_sort_order = max($max_sort_order, (int) ($method['sort_order'] ?? 0));
                }

                $title = (string) ($gateway['title'] ?: ($gateway['method_title'] ?? $gateway_id));
                $result = $repo->create([
                    'name'                  => $title,
                    'slug'                  => substr('wc_' . sanitize_key($gateway_id), 0, 50),
                    'payment_type'          => 'woocommerce',
                    'affects_cash_register' => false,
                    'allow_reference'       => true,
                    'card_fee_enabled'      => false,
                    'card_fee_type'         => null,
                    'card_fee_value'        => null,
                    'wc_gateway_id'         => $gateway_id,
                    'sort_order'            => $max_sort_order + 10,
                ]);

                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                    break;
                }

                $params['payment_gateway_imported'] = '1';
                AuditLogger::log('payment_gateway_imported', [
                    'entity_type' => 'payment_method',
                    'entity_id'   => (int) $result['id'],
                    'severity'    => 'info',
                    'message'     => __('Gateway WooCommerce importado como método POS.', 'mx-pos-pro'),
                    'metadata'    => [
                        'gateway_id' => $gateway_id,
                        'slug'       => $result['slug'],
                    ],
                ]);
                break;

            default:
                $error = __('Acción no reconocida.', 'mx-pos-pro');
        }

        if ($error !== null) {
            $params['payment_method_error'] = $error;
        }

        wp_safe_redirect(add_query_arg($params, admin_url('admin.php')));
        exit;
    }

    private function get_payment_method_form_data(bool $is_update): array|\WP_Error
    {
        $name = isset($_POST['payment_method_name'])
            ? sanitize_text_field(wp_unslash($_POST['payment_method_name']))
            : '';

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            return new \WP_Error(
                'mx_pos_invalid_payment_method_name',
                __('El nombre debe tener entre 2 y 100 caracteres.', 'mx-pos-pro')
            );
        }

        $type = isset($_POST['payment_method_type'])
            ? sanitize_text_field(wp_unslash($_POST['payment_method_type']))
            : 'other';

        if (! in_array($type, self::PAYMENT_METHOD_TYPES, true)) {
            return new \WP_Error(
                'mx_pos_invalid_payment_method_type',
                __('Tipo de método de pago inválido.', 'mx-pos-pro')
            );
        }

        $card_fee_enabled = isset($_POST['payment_method_card_fee_enabled'])
            && (string) wp_unslash($_POST['payment_method_card_fee_enabled']) === '1';
        $card_fee_type = null;
        $card_fee_value = null;

        if ($card_fee_enabled) {
            $card_fee_type = isset($_POST['payment_method_card_fee_type'])
                ? sanitize_text_field(wp_unslash($_POST['payment_method_card_fee_type']))
                : '';

            if (! in_array($card_fee_type, ['percentage', 'fixed'], true)) {
                return new \WP_Error(
                    'mx_pos_invalid_card_fee_type',
                    __('Tipo de comisión inválido.', 'mx-pos-pro')
                );
            }

            $raw_fee_value = isset($_POST['payment_method_card_fee_value'])
                ? sanitize_text_field(wp_unslash($_POST['payment_method_card_fee_value']))
                : '0';

            if (! is_numeric($raw_fee_value) || (float) $raw_fee_value < 0) {
                return new \WP_Error(
                    'mx_pos_invalid_card_fee_value',
                    __('El valor de comisión debe ser mayor o igual a cero.', 'mx-pos-pro')
                );
            }

            $card_fee_value = (float) $raw_fee_value;
        }

        $data = [
            'name'                  => $name,
            'payment_type'          => $type,
            'affects_cash_register' => isset($_POST['payment_method_affects_cash_register'])
                && (string) wp_unslash($_POST['payment_method_affects_cash_register']) === '1',
            'allow_reference'       => isset($_POST['payment_method_allow_reference'])
                && (string) wp_unslash($_POST['payment_method_allow_reference']) === '1',
            'card_fee_enabled'      => $card_fee_enabled,
            'card_fee_type'         => $card_fee_type,
            'card_fee_value'        => $card_fee_value,
            'sort_order'            => isset($_POST['payment_method_sort_order'])
                ? absint($_POST['payment_method_sort_order'])
                : 0,
        ];

        if (! $is_update) {
            $slug = isset($_POST['payment_method_slug'])
                ? sanitize_text_field(wp_unslash($_POST['payment_method_slug']))
                : '';

            if ($slug === '' || ! preg_match('/^[a-z0-9_-]+$/', $slug) || mb_strlen($slug) > 50) {
                return new \WP_Error(
                    'mx_pos_invalid_payment_method_slug',
                    __('El slug debe ser único y usar solo minúsculas, números, guiones o guiones bajos.', 'mx-pos-pro')
                );
            }

            $data['slug'] = $slug;
        }

        return $data;
    }

    private function apply_protected_payment_method_rules(array $method, array &$data): void
    {
        $slug = (string) ($method['slug'] ?? '');

        if (! in_array($slug, self::PROTECTED_PAYMENT_METHOD_SLUGS, true)) {
            return;
        }

        if ($slug === 'cash') {
            $data['payment_type'] = 'cash';
            $data['affects_cash_register'] = true;
        } elseif ($slug === 'card') {
            $data['payment_type'] = 'card';
            $data['affects_cash_register'] = false;
        } elseif ($slug === 'mixed') {
            $data['payment_type'] = 'mixed';
            $data['affects_cash_register'] = true;
        }
    }

    private function find_woocommerce_gateway(PaymentMethodRepository $repo, string $gateway_id): ?array
    {
        if ($gateway_id === '') {
            return null;
        }

        foreach ($repo->get_woocommerce_gateways() as $gateway) {
            if ((string) ($gateway['id'] ?? '') === $gateway_id) {
                return $gateway;
            }
        }

        return null;
    }

    // ─── Branch Handler ────────────────────────────────────────────────────────

    public function handle_branch_action(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mx-pos-pro'));
        }

        check_admin_referer(self::BRANCH_NONCE_ACTION, self::BRANCH_NONCE_NAME);

        $do = isset($_POST['do']) ? sanitize_text_field(wp_unslash($_POST['do'])) : '';

        $repo   = new BranchRepository();
        $params = ['page' => self::MENU_SLUG, 'tab' => 'sucursales'];
        $error  = null;

        switch ($do) {
            case 'branch_create':
                $name = isset($_POST['branch_name']) ? sanitize_text_field(wp_unslash($_POST['branch_name'])) : '';
                $slug = isset($_POST['branch_slug']) ? sanitize_text_field(wp_unslash($_POST['branch_slug'])) : '';

                if ($name === '') {
                    $error = __('El nombre es obligatorio.', 'mx-pos-pro');
                    break;
                }

                if ($slug === '') {
                    $error = __('El código es obligatorio.', 'mx-pos-pro');
                    break;
                }

                $id = $repo->create([
                    'name'      => $name,
                    'slug'      => $slug,
                    'address'   => isset($_POST['branch_address'])
                        ? sanitize_textarea_field(wp_unslash($_POST['branch_address']))
                        : null,
                    'phone'     => isset($_POST['branch_phone'])
                        ? sanitize_text_field(wp_unslash($_POST['branch_phone']))
                        : null,
                    'is_active' => 1,
                ]);

                if ($id > 0) {
                    $params['branch_created'] = '1';
                    AuditLogger::log('branch_created', [
                        'entity_type'  => 'branch',
                        'entity_id'    => $id,
                        'branch_id'    => $id,
                        'severity'     => 'info',
                        'message'      => __('Sucursal creada.', 'mx-pos-pro'),
                        'metadata'     => [
                            'branch_name' => $name,
                            'branch_slug' => sanitize_title($slug),
                        ],
                    ]);
                } else {
                    $error = $repo->get_by_slug(sanitize_title($slug)) !== null
                        ? __('Ya existe una sucursal con ese código.', 'mx-pos-pro')
                        : __('No se pudo crear la sucursal.', 'mx-pos-pro');
                }
                break;

            case 'branch_update':
                $branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : 0;
                $name      = isset($_POST['branch_name']) ? sanitize_text_field(wp_unslash($_POST['branch_name'])) : '';

                if ($branch_id <= 0 || $name === '') {
                    $error = __('Datos inválidos.', 'mx-pos-pro');
                    break;
                }

                $update_data = ['name' => $name];

                if (isset($_POST['branch_slug'])) {
                    $update_data['slug'] = sanitize_text_field(wp_unslash($_POST['branch_slug']));
                }

                $update_data['address'] = isset($_POST['branch_address'])
                    ? sanitize_textarea_field(wp_unslash($_POST['branch_address']))
                    : null;

                $update_data['phone'] = isset($_POST['branch_phone'])
                    ? sanitize_text_field(wp_unslash($_POST['branch_phone']))
                    : null;

                if ($repo->update($branch_id, $update_data)) {
                    $params['branch_updated'] = '1';
                    AuditLogger::log('branch_updated', [
                        'entity_type'  => 'branch',
                        'entity_id'    => $branch_id,
                        'branch_id'    => $branch_id,
                        'severity'     => 'info',
                        'message'      => __('Sucursal actualizada.', 'mx-pos-pro'),
                        'metadata'     => [
                            'branch_name'    => $name,
                        ],
                    ]);
                } else {
                    $branch = $repo->get_by_id($branch_id);
                    if ($branch && $branch['slug'] === 'main' && isset($update_data['slug']) && $update_data['slug'] !== 'main') {
                        $error = __('No se puede cambiar el código de la Sucursal Principal.', 'mx-pos-pro');
                    } else {
                        $error = __('Ya existe una sucursal con ese código.', 'mx-pos-pro');
                    }
                }
                break;

            case 'branch_toggle':
                $branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
                $active    = isset($_GET['active']) ? (int) $_GET['active'] : 0;

                if ($branch_id <= 0) {
                    $error = __('Sucursal no encontrada.', 'mx-pos-pro');
                    break;
                }

                $branch = $repo->get_by_id($branch_id);
                if ($branch === null) {
                    $error = __('Sucursal no encontrada.', 'mx-pos-pro');
                    break;
                }

                if ($branch['slug'] === 'main' && $active === 0) {
                    $error = __('La Sucursal Principal no puede desactivarse.', 'mx-pos-pro');
                    break;
                }

                if ($active === 0 && $repo->count_active_registers($branch_id) > 0) {
                    $error = __('No se puede desactivar la sucursal porque tiene cajas activas.', 'mx-pos-pro');
                    break;
                }

                if ($repo->set_active($branch_id, $active === 1)) {
                    $params['branch_toggled'] = '1';
                    AuditLogger::log(
                        $active === 1 ? 'branch_activated' : 'branch_deactivated',
                        [
                            'entity_type'  => 'branch',
                            'entity_id'    => $branch_id,
                            'branch_id'    => $branch_id,
                            'severity'     => 'info',
                            'message'      => $active === 1
                                ? __('Sucursal activada.', 'mx-pos-pro')
                                : __('Sucursal desactivada.', 'mx-pos-pro'),
                            'metadata'     => [
                                'branch_name' => $branch['name'],
                            ],
                        ]
                    );
                } else {
                    $error = __('No se pudo cambiar el estado de la sucursal.', 'mx-pos-pro');
                }
                break;

            default:
                $error = __('Acción no reconocida.', 'mx-pos-pro');
        }

        if ($error !== null) {
            $params['branch_error'] = urlencode($error);
        }

        wp_safe_redirect(add_query_arg($params, admin_url('admin.php')));
        exit;
    }

    // ─── Register Handler ──────────────────────────────────────────────────────

    public function handle_register_action(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mx-pos-pro'));
        }

        check_admin_referer(self::REGISTER_NONCE_ACTION, self::REGISTER_NONCE_NAME);

        $do = isset($_POST['do']) ? sanitize_text_field(wp_unslash($_POST['do'])) : '';

        $repo        = new RegisterRepository();
        $branch_repo = new BranchRepository();
        $params      = ['page' => self::MENU_SLUG, 'tab' => 'cajas'];
        $error       = null;

        switch ($do) {
            case 'register_create':
                $name      = isset($_POST['register_name']) ? sanitize_text_field(wp_unslash($_POST['register_name'])) : '';
                $slug      = isset($_POST['register_slug']) ? sanitize_text_field(wp_unslash($_POST['register_slug'])) : '';
                $branch_id = isset($_POST['register_branch_id']) ? (int) $_POST['register_branch_id'] : 0;

                if ($name === '') {
                    $error = __('El nombre es obligatorio.', 'mx-pos-pro');
                    break;
                }

                if ($slug === '') {
                    $error = __('El código es obligatorio.', 'mx-pos-pro');
                    break;
                }

                if ($branch_id <= 0) {
                    $error = __('Selecciona una sucursal activa.', 'mx-pos-pro');
                    break;
                }

                $branch = $branch_repo->get_by_id($branch_id);
                if ($branch === null || (int) $branch['is_active'] !== 1) {
                    $error = __('Selecciona una sucursal activa.', 'mx-pos-pro');
                    break;
                }

                if ($repo->count_active() >= self::MAX_ACTIVE_REGISTERS) {
                    $error = sprintf(
                        __('No se pueden tener más de %d cajas activas.', 'mx-pos-pro'),
                        self::MAX_ACTIVE_REGISTERS
                    );
                    break;
                }

                $id = $repo->create([
                    'name'      => $name,
                    'slug'      => $slug,
                    'branch_id' => $branch_id,
                    'is_active' => 1,
                ]);

                if ($id > 0) {
                    $params['register_created'] = '1';
                    AuditLogger::log('register_created', [
                        'entity_type'      => 'register',
                        'entity_id'        => $id,
                        'branch_id'        => $branch_id,
                        'pos_register_id'  => $id,
                        'severity'         => 'info',
                        'message'          => __('Caja creada.', 'mx-pos-pro'),
                        'metadata'         => [
                            'register_name' => $name,
                            'register_slug' => sanitize_title($slug),
                            'branch_id'     => $branch_id,
                        ],
                    ]);
                } else {
                    $error = $repo->get_by_slug(sanitize_title($slug)) !== null
                        ? __('Ya existe una caja con ese código.', 'mx-pos-pro')
                        : __('No se pudo crear la caja.', 'mx-pos-pro');
                }
                break;

            case 'register_update':
                $register_id = isset($_POST['register_id']) ? (int) $_POST['register_id'] : 0;
                $name        = isset($_POST['register_name']) ? sanitize_text_field(wp_unslash($_POST['register_name'])) : '';

                if ($register_id <= 0 || $name === '') {
                    $error = __('Datos inválidos.', 'mx-pos-pro');
                    break;
                }

                $update_data = ['name' => $name];

                if (isset($_POST['register_slug'])) {
                    $update_data['slug'] = sanitize_text_field(wp_unslash($_POST['register_slug']));
                }

                if (isset($_POST['register_branch_id'])) {
                    $branch_id = (int) $_POST['register_branch_id'];
                    if ($branch_id <= 0) {
                        $error = __('Selecciona una sucursal activa.', 'mx-pos-pro');
                        break;
                    }
                    $branch = $branch_repo->get_by_id($branch_id);
                    if ($branch === null || (int) $branch['is_active'] !== 1) {
                        $error = __('Selecciona una sucursal activa.', 'mx-pos-pro');
                        break;
                    }
                    $update_data['branch_id'] = $branch_id;
                }

                if ($error !== null) {
                    break;
                }

                if ($repo->update($register_id, $update_data)) {
                    $params['register_updated'] = '1';
                    $updated_register = $repo->get_by_id($register_id);
                    AuditLogger::log('register_updated', [
                        'entity_type'      => 'register',
                        'entity_id'        => $register_id,
                        'branch_id'        => $updated_register['branch_id'] ?? null,
                        'pos_register_id'  => $register_id,
                        'severity'         => 'info',
                        'message'          => __('Caja actualizada.', 'mx-pos-pro'),
                        'metadata'         => [
                            'register_name' => $name,
                        ],
                    ]);
                } else {
                    $register = $repo->get_by_id($register_id);
                    if ($register && $register['slug'] === 'main' && isset($update_data['slug']) && $update_data['slug'] !== 'main') {
                        $error = __('No se puede cambiar el código de la Caja Principal.', 'mx-pos-pro');
                    } else {
                        $error = __('Ya existe una caja con ese código.', 'mx-pos-pro');
                    }
                }
                break;

            case 'register_toggle':
                $register_id = isset($_GET['register_id']) ? (int) $_GET['register_id'] : 0;
                $active      = isset($_GET['active']) ? (int) $_GET['active'] : 0;

                if ($register_id <= 0) {
                    $error = __('Caja no encontrada.', 'mx-pos-pro');
                    break;
                }

                $register = $repo->get_by_id($register_id);
                if ($register === null) {
                    $error = __('Caja no encontrada.', 'mx-pos-pro');
                    break;
                }

                if ($register['slug'] === 'main' && $active === 0) {
                    $error = __('La Caja Principal no puede desactivarse.', 'mx-pos-pro');
                    break;
                }

                if ($active === 0 && $repo->count_open_sessions($register_id) > 0) {
                    $error = __('No se puede desactivar la caja porque tiene sesiones abiertas.', 'mx-pos-pro');
                    break;
                }

                if ($active === 1 && $repo->count_active() >= self::MAX_ACTIVE_REGISTERS) {
                    $error = sprintf(
                        __('No se pueden tener más de %d cajas activas.', 'mx-pos-pro'),
                        self::MAX_ACTIVE_REGISTERS
                    );
                    break;
                }

                if ($repo->set_active($register_id, $active === 1)) {
                    $params['register_toggled'] = '1';
                    AuditLogger::log(
                        $active === 1 ? 'register_activated' : 'register_deactivated',
                        [
                            'entity_type'      => 'register',
                            'entity_id'        => $register_id,
                            'branch_id'        => (int) $register['branch_id'],
                            'pos_register_id'  => $register_id,
                            'severity'         => 'info',
                            'message'          => $active === 1
                                ? __('Caja activada.', 'mx-pos-pro')
                                : __('Caja desactivada.', 'mx-pos-pro'),
                            'metadata'         => [
                                'register_name' => $register['name'],
                            ],
                        ]
                    );
                } else {
                    $error = __('No se pudo cambiar el estado de la caja.', 'mx-pos-pro');
                }
                break;

            default:
                $error = __('Acción no reconocida.', 'mx-pos-pro');
        }

        if ($error !== null) {
            $params['register_error'] = urlencode($error);
        }

        wp_safe_redirect(add_query_arg($params, admin_url('admin.php')));
        exit;
    }

    // ─── Employee Handler ──────────────────────────────────────────────────────

    public function handle_employee_action(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mx-pos-pro'));
        }

        check_admin_referer(self::EMPLOYEE_NONCE_ACTION, self::EMPLOYEE_NONCE_NAME);

        $do = isset($_POST['do'])
            ? sanitize_text_field(wp_unslash($_POST['do']))
            : (isset($_GET['do']) ? sanitize_text_field(wp_unslash($_GET['do'])) : '');

        $repo        = new EmployeeRepository();
        $branch_repo = new BranchRepository();
        $params      = ['page' => self::MENU_SLUG, 'tab' => 'empleados'];
        $error       = null;

        switch ($do) {
            case 'employee_create':
                $display_name = isset($_POST['employee_display_name'])
                    ? sanitize_text_field(wp_unslash($_POST['employee_display_name']))
                    : '';
                $username = isset($_POST['employee_username'])
                    ? sanitize_text_field(wp_unslash($_POST['employee_username']))
                    : '';
                $password = isset($_POST['employee_password'])
                    ? wp_unslash($_POST['employee_password'])
                    : '';
                $password_confirm = isset($_POST['employee_password_confirm'])
                    ? wp_unslash($_POST['employee_password_confirm'])
                    : '';
                $role      = isset($_POST['employee_role']) && in_array($_POST['employee_role'], ['cashier', 'manager'], true)
                    ? $_POST['employee_role']
                    : '';
                $branch_id = isset($_POST['employee_branch_id']) ? (int) $_POST['employee_branch_id'] : 0;

                if ($display_name === '') {
                    $error = __('El nombre visible es obligatorio.', 'mx-pos-pro');
                    break;
                }

                if ($username === '') {
                    $error = __('El usuario es obligatorio.', 'mx-pos-pro');
                    break;
                }

                if ($repo->username_exists($username)) {
                    $error = __('Ya existe un empleado con ese usuario.', 'mx-pos-pro');
                    break;
                }

                if ($password === '' || strlen($password) < 8) {
                    $error = __('La contraseña debe tener al menos 8 caracteres.', 'mx-pos-pro');
                    break;
                }

                if ($password !== $password_confirm) {
                    $error = __('Las contraseñas no coinciden.', 'mx-pos-pro');
                    break;
                }

                if ($role === '') {
                    $error = __('Selecciona un rol válido.', 'mx-pos-pro');
                    break;
                }

                if ($branch_id > 0) {
                    $branch = $branch_repo->get_by_id($branch_id);
                    if ($branch === null || (int) $branch['is_active'] !== 1) {
                        $error = __('La sucursal seleccionada no está activa.', 'mx-pos-pro');
                        break;
                    }
                }

                $id = $repo->create([
                    'display_name' => $display_name,
                    'username'     => $username,
                    'password'     => $password,
                    'role'         => $role,
                    'branch_id'    => $branch_id > 0 ? $branch_id : null,
                ]);

                if ($id > 0) {
                    $params['employee_created'] = '1';
                    $this->log_employee_audit('employee_created', $id);
                } else {
                    $error = __('No se pudo crear el empleado.', 'mx-pos-pro');
                }
                break;

            case 'employee_update':
                $employee_id  = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
                $display_name = isset($_POST['employee_display_name'])
                    ? sanitize_text_field(wp_unslash($_POST['employee_display_name']))
                    : '';
                $role = isset($_POST['employee_role']) && in_array($_POST['employee_role'], ['cashier', 'manager'], true)
                    ? $_POST['employee_role']
                    : '';
                $branch_id = isset($_POST['employee_branch_id']) ? (int) $_POST['employee_branch_id'] : 0;

                if ($employee_id <= 0 || $display_name === '') {
                    $error = __('Datos inválidos.', 'mx-pos-pro');
                    break;
                }

                if ($role === '') {
                    $error = __('Selecciona un rol válido.', 'mx-pos-pro');
                    break;
                }

                $update_data = [
                    'display_name' => $display_name,
                    'role'         => $role,
                    'branch_id'    => $branch_id > 0 ? $branch_id : null,
                ];

                if ($branch_id > 0) {
                    $branch = $branch_repo->get_by_id($branch_id);
                    if ($branch === null || (int) $branch['is_active'] !== 1) {
                        $error = __('La sucursal seleccionada no está activa.', 'mx-pos-pro');
                        break;
                    }
                }

                if ($repo->update($employee_id, $update_data)) {
                    $params['employee_updated'] = '1';
                    $this->log_employee_audit('employee_updated', $employee_id);
                } else {
                    $error = __('No se pudo actualizar el empleado.', 'mx-pos-pro');
                }
                break;

            case 'employee_reset_password':
                $employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
                $password    = isset($_POST['employee_password']) ? wp_unslash($_POST['employee_password']) : '';
                $password_confirm = isset($_POST['employee_password_confirm'])
                    ? wp_unslash($_POST['employee_password_confirm'])
                    : '';

                if ($employee_id <= 0) {
                    $error = __('Empleado no encontrado.', 'mx-pos-pro');
                    break;
                }

                if ($password === '' || strlen($password) < 8) {
                    $error = __('La contraseña debe tener al menos 8 caracteres.', 'mx-pos-pro');
                    break;
                }

                if ($password !== $password_confirm) {
                    $error = __('Las contraseñas no coinciden.', 'mx-pos-pro');
                    break;
                }

                if ($repo->update_password($employee_id, $password)) {
                    $params['employee_password_reset'] = '1';
                    $this->log_employee_audit('employee_password_reset', $employee_id);
                } else {
                    $error = __('No se pudo restablecer la contraseña.', 'mx-pos-pro');
                }
                break;

            case 'employee_toggle':
                $employee_id = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
                $active      = isset($_GET['active']) ? (int) $_GET['active'] : 0;

                if ($employee_id <= 0) {
                    $error = __('Empleado no encontrado.', 'mx-pos-pro');
                    break;
                }

                if ($repo->set_active($employee_id, $active === 1)) {
                    $params['employee_toggled'] = '1';
                    $this->log_employee_audit(
                        $active === 1 ? 'employee_reactivated' : 'employee_deactivated',
                        $employee_id
                    );
                } else {
                    $error = __('No se pudo cambiar el estado del empleado.', 'mx-pos-pro');
                }
                break;

            case 'employee_soft_delete':
                $employee_id = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;

                if ($employee_id <= 0) {
                    $error = __('Empleado no encontrado.', 'mx-pos-pro');
                    break;
                }

                if ($repo->soft_delete($employee_id)) {
                    $params['employee_deleted'] = '1';
                    $this->log_employee_audit('employee_soft_deleted', $employee_id);
                } else {
                    $error = __('No se pudo dar de baja al empleado.', 'mx-pos-pro');
                }
                break;

            case 'employee_restore':
                $employee_id = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;

                if ($employee_id <= 0) {
                    $error = __('Empleado no encontrado.', 'mx-pos-pro');
                    break;
                }

                if ($repo->restore($employee_id)) {
                    $params['employee_restored'] = '1';
                    $this->log_employee_audit('employee_restored', $employee_id);
                } else {
                    $error = __('No se pudo restaurar el empleado.', 'mx-pos-pro');
                }
                break;

            case 'employee_unlock':
                $employee_id = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;

                if ($employee_id <= 0) {
                    $error = __('Empleado no encontrado.', 'mx-pos-pro');
                    break;
                }

                if ($repo->unlock($employee_id)) {
                    $params['employee_unlocked'] = '1';
                    $this->log_employee_audit('employee_unlocked', $employee_id);
                } else {
                    $error = __('No se pudo desbloquear al empleado.', 'mx-pos-pro');
                }
                break;

            default:
                $error = __('Acción no reconocida.', 'mx-pos-pro');
        }

        if ($error !== null) {
            $params['employee_error'] = urlencode($error);
        }

        wp_safe_redirect(add_query_arg($params, admin_url('admin.php')));
        exit;
    }

    private function log_employee_audit(string $action, int $employee_id, array $context = []): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mx_pos_audit_logs';

        $context_data = wp_json_encode(array_merge($context, [
            'action' => $action,
        ]));
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        $result = $wpdb->insert(
            $table,
            [
                'actor_id'      => get_current_user_id() > 0 ? get_current_user_id() : null,
                'entity_type'   => 'employee',
                'entity_id'     => $employee_id,
                'pos_employee_id' => $employee_id,
                'action'        => $action,
                'ip_address'    => $ipAddress,
                'user_agent'    => $userAgent,
                'context_data'  => $context_data,
                'created_at'    => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public function handle_void_session(): void
    {
        if (! current_user_can('manage_options') && ! current_user_can('mx_pos_void_session')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mx-pos-pro'));
        }

        check_admin_referer(self::VOID_SESSION_NONCE_ACTION, self::VOID_SESSION_NONCE_NAME);

        $session_id  = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
        $void_reason = isset($_POST['void_reason']) ? sanitize_text_field(wp_unslash($_POST['void_reason'])) : '';

        $params = ['page' => self::MENU_SLUG, 'tab' => 'sesiones'];
        $error  = null;

        if ($session_id <= 0) {
            $error = __('ID de sesión no válido.', 'mx-pos-pro');
        }

        if ($error === null) {
            $void_reason_trimmed = trim($void_reason);

            if ($void_reason_trimmed === '') {
                $error = __('El motivo de anulación es obligatorio.', 'mx-pos-pro');
            }
        }

        if ($error === null) {
            $sessionService = new CashSessionService(
                new CashSessionRepository(),
                new CashMovementRepository()
            );

            $result = $sessionService->void_session(
                $session_id,
                get_current_user_id(),
                $void_reason
            );

            if (is_wp_error($result)) {
                $params['void_error'] = urlencode($result->get_error_code());
            } else {
                $params['session_voided'] = '1';
            }
        }

        if ($error !== null) {
            $params['void_error'] = urlencode($error);
        }

        wp_safe_redirect(add_query_arg($params, admin_url('admin.php')));
        exit;
    }

    public function handle_remote_close_session(): void
    {
        if (! current_user_can('manage_options') && ! current_user_can('mx_pos_remote_close')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mx-pos-pro'));
        }

        check_admin_referer(self::REMOTE_CLOSE_SESSION_NONCE_ACTION, self::REMOTE_CLOSE_SESSION_NONCE_NAME);

        $session_id    = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
        $remote_reason = isset($_POST['remote_reason']) ? sanitize_text_field(wp_unslash($_POST['remote_reason'])) : '';

        $params = ['page' => self::MENU_SLUG, 'tab' => 'sesiones'];
        $error  = null;

        if ($session_id <= 0) {
            $error = __('ID de sesión no válido.', 'mx-pos-pro');
        }

        if ($error === null) {
            $remote_reason_trimmed = trim($remote_reason);

            if ($remote_reason_trimmed === '') {
                $error = __('El motivo del cierre remoto es obligatorio.', 'mx-pos-pro');
            }
        }

        if ($error === null) {
            $sessionService = new CashSessionService(
                new CashSessionRepository(),
                new CashMovementRepository()
            );

            $result = $sessionService->close_session_remote(
                $session_id,
                get_current_user_id(),
                $remote_reason
            );

            if (is_wp_error($result)) {
                $params['remote_close_error'] = urlencode($result->get_error_code());
            } else {
                $params['session_remote_closed'] = '1';
            }
        }

        if ($error !== null) {
            $params['remote_close_error'] = urlencode($error);
        }

        wp_safe_redirect(add_query_arg($params, admin_url('admin.php')));
        exit;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function render_admin_notices(): void
    {
        if (! isset($_GET['tab']) || sanitize_text_field(wp_unslash($_GET['tab'])) !== 'sesiones') {
            return;
        }

        if (isset($_GET['session_voided'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('La sesión fue anulada correctamente.', 'mx-pos-pro'); ?></p>
            </div>
            <?php
        }

        if (isset($_GET['void_error'])) {
            $error_code = sanitize_text_field(wp_unslash($_GET['void_error']));
            $message   = $error_code;

            if ($error_code === 'mx_pos_session_not_open') {
                $message = __('Solo se pueden anular sesiones abiertas.', 'mx-pos-pro');
            } elseif ($error_code === 'mx_pos_session_not_found') {
                $message = __('La sesión no existe.', 'mx-pos-pro');
            } elseif ($error_code === 'mx_pos_session_void_failed') {
                $message = __('No se pudo anular la sesión. Puede que ya haya sido cerrada o anulada.', 'mx-pos-pro');
            } elseif ($error_code === 'mx_pos_void_reason_required') {
                $message = __('El motivo de anulación es obligatorio.', 'mx-pos-pro');
            } elseif ($error_code === 'mx_pos_void_reason_too_long') {
                $message = __('El motivo de anulación no debe exceder 500 caracteres.', 'mx-pos-pro');
            } elseif ($error_code === 'mx_pos_invalid_session') {
                $message = __('ID de sesión no válido.', 'mx-pos-pro');
            }

            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
            <?php
        }

        if (isset($_GET['session_remote_closed'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('La sesión fue cerrada remotamente.', 'mx-pos-pro'); ?></p>
            </div>
            <?php
        }

        if (isset($_GET['remote_close_error'])) {
            $error_code = sanitize_text_field(wp_unslash($_GET['remote_close_error']));
            $message   = $error_code;

            if ($error_code === 'mx_pos_session_not_open') {
                $message = __('Solo se pueden cerrar remotamente sesiones abiertas.', 'mx-pos-pro');
            } elseif ($error_code === 'mx_pos_session_not_found') {
                $message = __('La sesión no existe.', 'mx-pos-pro');
            } elseif ($error_code === 'mx_pos_session_already_closed') {
                $message = __('La sesión ya fue cerrada.', 'mx-pos-pro');
            } elseif ($error_code === 'mx_pos_remote_close_reason_required') {
                $message = __('El motivo del cierre remoto es obligatorio.', 'mx-pos-pro');
            } elseif ($error_code === 'mx_pos_remote_close_reason_too_long') {
                $message = __('El motivo del cierre remoto no debe exceder 500 caracteres.', 'mx-pos-pro');
            } elseif ($error_code === 'mx_pos_invalid_session') {
                $message = __('ID de sesión no válido.', 'mx-pos-pro');
            }

            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
            <?php
        }
    }

    private function mask_token(string $token): string
    {
        if ($token === '') {
            return '';
        }

        $parts = explode(':', $token, 2);
        if (count($parts) === 2) {
            $suffix = substr($parts[1], -4);
            return '****:' . $suffix;
        }

        return '****';
    }

    private function get_logo_preview_html(int $attachment_id): string
    {
        if ($attachment_id <= 0) {
            return '<p class="description">' . esc_html__('No logo selected.', 'mx-pos-pro') . '</p>';
        }

        $mime = get_post_mime_type($attachment_id);
        if (! $mime || ! str_starts_with($mime, 'image/')) {
            return '<p class="description">' . esc_html__('Selected attachment is not a valid image.', 'mx-pos-pro') . '</p>';
        }

        $url = wp_get_attachment_image_url($attachment_id, 'medium');
        if (! $url) {
            return '<p class="description">' . esc_html__('Logo image not found.', 'mx-pos-pro') . '</p>';
        }

        $filename = basename(get_attached_file($attachment_id) ?: '');

        return sprintf(
            '<img src="%s" alt="%s" style="max-width:150px;height:auto;display:block;" />' .
            '<p class="description" style="margin-top:4px;">%s</p>',
            esc_url($url),
            esc_attr__('Logo preview', 'mx-pos-pro'),
            esc_html($filename)
        );
    }

    private function validate_logo_attachment(int $attachment_id): int
    {
        if ($attachment_id <= 0) {
            return 0;
        }

        $mime = get_post_mime_type($attachment_id);
        if (! $mime || ! str_starts_with($mime, 'image/')) {
            return 0;
        }

        return $attachment_id;
    }

    private function check_all_tables_exist(): bool
    {
        global $wpdb;

        $expected_tables = [
            'mx_pos_product_index',
            'mx_pos_sessions',
            'mx_pos_sales',
            'mx_pos_sale_logs',
            'mx_pos_refunds',
            'mx_pos_cash_movements',
            'mx_pos_parked_carts',
            'mx_pos_cash_cuts',
            'mx_pos_audit_logs',
            'mx_pos_branches',
            'mx_pos_registers',
            'mx_pos_employees',
            'mx_pos_payment_methods',
            'mx_pos_order_payments',
        ];

        foreach ($expected_tables as $table) {
            $full = $wpdb->prefix . $table;
            $found = $wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $full)
            );

            if ($found !== $full) {
                return false;
            }
        }

        return true;
    }
}
