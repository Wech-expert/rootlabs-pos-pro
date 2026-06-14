<?php

namespace MXPOSPro\Admin;

defined('ABSPATH') || exit;

use MXPOSPro\Entities\BranchRepository;
use MXPOSPro\Entities\EmployeeRepository;
use MXPOSPro\Entities\RegisterRepository;

class AuditPage
{
    private const PER_PAGE = 50;

    public static function render(): void
    {
        if (! current_user_can('manage_options') && ! current_user_can('mx_pos_view_audit')) {
            wp_die(
                esc_html__('You do not have permission to access this page.', 'mx-pos-pro')
            );
        }

        global $wpdb;

        $table = $wpdb->prefix . 'mx_pos_audit_logs';

        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        if ($found !== $table) {
            echo '<div class="notice notice-warning inline"><p>';
            esc_html_e('La tabla de auditoria no existe. Reactive el plugin para crearla.', 'mx-pos-pro');
            echo '</p></div>';
            return;
        }

        $filters = self::parse_filters();

        $branch_repo   = new BranchRepository();
        $register_repo = new RegisterRepository();
        $employee_repo = new EmployeeRepository();

        $branches  = $branch_repo->get_all_active();
        $registers = $register_repo->get_all_with_branch();
        $employees = $employee_repo->get_all_active();

        $action_options = $wpdb->get_col(
            "SELECT DISTINCT `action` FROM `{$table}` WHERE `action` != '' ORDER BY `action` ASC"
        );

        $where  = '1=1';
        $params = [];

        if ($filters['date_from'] !== '') {
            $where  .= ' AND created_at >= %s';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if ($filters['date_to'] !== '') {
            $where  .= ' AND created_at <= %s';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if ($filters['action'] !== '') {
            $where  .= ' AND action = %s';
            $params[] = $filters['action'];
        }

        if ($filters['pos_employee_id'] > 0) {
            $where  .= ' AND pos_employee_id = %d';
            $params[] = $filters['pos_employee_id'];
        }

        if ($filters['pos_register_id'] > 0) {
            $where  .= ' AND pos_register_id = %d';
            $params[] = $filters['pos_register_id'];
        }

        if ($filters['branch_id'] > 0) {
            $where  .= ' AND branch_id = %d';
            $params[] = $filters['branch_id'];
        }

        if ($filters['cash_session_id'] > 0) {
            $where  .= ' AND context_data LIKE %s';
            $params[] = '%"cash_session_id":' . $filters['cash_session_id'] . '%';
        }

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE {$where}",
                $params
            )
        );

        $page   = max(1, $filters['paged']);
        $offset = ($page - 1) * self::PER_PAGE;

        $params[] = self::PER_PAGE;
        $params[] = $offset;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
                $params
            ),
            ARRAY_A
        );

        $base_url = add_query_arg(
            ['page' => 'mx-pos-pro', 'tab' => 'auditoria'],
            admin_url('admin.php')
        );

        $user_cache     = [];
        $branch_cache   = [];
        $register_cache = [];
        $employee_cache = [];

        $resolve_user = function (int $user_id) use (&$user_cache): string {
            if ($user_id <= 0) {
                return '—';
            }

            if (isset($user_cache[$user_id])) {
                return $user_cache[$user_id];
            }

            $user = get_userdata($user_id);
            $name = $user instanceof \WP_User
                ? ($user->display_name !== '' ? $user->display_name : "User {$user_id}")
                : "Deleted ({$user_id})";

            $user_cache[$user_id] = $name;

            return $name;
        };

        $resolve_branch = function (?int $branch_id) use (&$branch_cache, $branch_repo): string {
            if ($branch_id === null || $branch_id <= 0) {
                return '—';
            }

            if (isset($branch_cache[$branch_id])) {
                return $branch_cache[$branch_id];
            }

            $branch = $branch_repo->get_by_id($branch_id);
            $name   = $branch !== null ? $branch['name'] : "Branch {$branch_id}";

            $branch_cache[$branch_id] = $name;

            return $name;
        };

        $resolve_register = function (?int $register_id) use (&$register_cache, $register_repo): string {
            if ($register_id === null || $register_id <= 0) {
                return '—';
            }

            if (isset($register_cache[$register_id])) {
                return $register_cache[$register_id];
            }

            $reg  = $register_repo->get_by_id($register_id);
            $name = $reg !== null ? $reg['name'] : "Register {$register_id}";

            $register_cache[$register_id] = $name;

            return $name;
        };

        $resolve_employee = function (?int $employee_id) use (&$employee_cache, $employee_repo): string {
            if ($employee_id === null || $employee_id <= 0) {
                return '—';
            }

            if (isset($employee_cache[$employee_id])) {
                return $employee_cache[$employee_id];
            }

            $emp  = $employee_repo->get_by_id($employee_id);
            $name = $emp !== null ? $emp['display_name'] : "Employee {$employee_id}";

            $employee_cache[$employee_id] = $name;

            return $name;
        };

        $resolve_actor = function (array $row) use ($resolve_user, $resolve_employee): string {
            $context    = self::decode_context($row['context_data'] ?? null);
            $actor_type = $context['actor_type'] ?? 'wp_admin';
            $actor_id   = (int) ($row['actor_id'] ?? 0);
            $emp_id     = isset($row['pos_employee_id']) ? (int) $row['pos_employee_id'] : 0;

            if ($actor_type === 'pos_employee' && $emp_id > 0) {
                return $resolve_employee($emp_id);
            }

            if ($actor_id > 0) {
                return $resolve_user($actor_id);
            }

            if ($actor_type === 'system') {
                return __('Sistema', 'mx-pos-pro');
            }

            return '—';
        };

        $resolve_entity = function (array $row): string {
            $entity_type = $row['entity_type'] ?? '';
            $entity_id   = $row['entity_id'] ?? null;

            if ($entity_type === '' && $entity_id === null) {
                return '—';
            }

            $label = $entity_type !== '' ? $entity_type : 'entity';

            if ($entity_id !== null) {
                $label .= ' #' . (int) $entity_id;
            }

            return $label;
        };

        ?>
        <h2><?php esc_html_e('Auditoria', 'mx-pos-pro'); ?></h2>
        <p class="description">
            <?php esc_html_e('Registro de eventos del POS. Incluye acciones de empleados, administradores y sistema.', 'mx-pos-pro'); ?>
        </p>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="mx-pos-pro" />
            <input type="hidden" name="tab" value="auditoria" />

            <div class="tablenav top">
                <div class="alignleft actions" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    <label for="mx-audit-date-from">
                        <?php esc_html_e('Desde:', 'mx-pos-pro'); ?>
                    </label>
                    <input type="date"
                           id="mx-audit-date-from"
                           name="audit_date_from"
                           value="<?php echo esc_attr($filters['date_from']); ?>" />

                    <label for="mx-audit-date-to">
                        <?php esc_html_e('Hasta:', 'mx-pos-pro'); ?>
                    </label>
                    <input type="date"
                           id="mx-audit-date-to"
                           name="audit_date_to"
                           value="<?php echo esc_attr($filters['date_to']); ?>" />

                    <label for="mx-audit-action">
                        <?php esc_html_e('Accion:', 'mx-pos-pro'); ?>
                    </label>
                    <select id="mx-audit-action" name="audit_action">
                        <option value=""><?php esc_html_e('Todas', 'mx-pos-pro'); ?></option>
                        <?php foreach ($action_options as $opt): ?>
                            <option value="<?php echo esc_attr($opt); ?>"
                                <?php selected($filters['action'], $opt); ?>>
                                <?php echo esc_html($opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="mx-audit-employee">
                        <?php esc_html_e('Empleado:', 'mx-pos-pro'); ?>
                    </label>
                    <select id="mx-audit-employee" name="audit_employee">
                        <option value=""><?php esc_html_e('Todos', 'mx-pos-pro'); ?></option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo esc_attr((string) $emp['id']); ?>"
                                <?php selected($filters['pos_employee_id'], (int) $emp['id']); ?>>
                                <?php echo esc_html($emp['display_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="mx-audit-register">
                        <?php esc_html_e('Caja:', 'mx-pos-pro'); ?>
                    </label>
                    <select id="mx-audit-register" name="audit_register">
                        <option value=""><?php esc_html_e('Todas', 'mx-pos-pro'); ?></option>
                        <?php foreach ($registers as $reg): ?>
                            <option value="<?php echo esc_attr((string) $reg['id']); ?>"
                                <?php selected($filters['pos_register_id'], (int) $reg['id']); ?>>
                                <?php echo esc_html($reg['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="mx-audit-branch">
                        <?php esc_html_e('Sucursal:', 'mx-pos-pro'); ?>
                    </label>
                    <select id="mx-audit-branch" name="audit_branch">
                        <option value=""><?php esc_html_e('Todas', 'mx-pos-pro'); ?></option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo esc_attr((string) $b['id']); ?>"
                                <?php selected($filters['branch_id'], (int) $b['id']); ?>>
                                <?php echo esc_html($b['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="mx-audit-session">
                        <?php esc_html_e('Sesion ID:', 'mx-pos-pro'); ?>
                    </label>
                    <input type="number"
                           id="mx-audit-session"
                           name="audit_session"
                           value="<?php echo $filters['cash_session_id'] > 0 ? esc_attr((string) $filters['cash_session_id']) : ''; ?>"
                           min="1"
                           step="1"
                           style="width: 100px;" />

                    <?php submit_button(__('Filtrar', 'mx-pos-pro'), 'secondary', 'filter_action', false); ?>

                    <?php if (self::has_active_filters($filters)): ?>
                        <a href="<?php echo esc_url($base_url); ?>" class="button" style="margin-left:4px;">
                            <?php esc_html_e('Limpiar', 'mx-pos-pro'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="tablenav-pages">
                    <?php
                    $total_pages = (int) ceil($total / self::PER_PAGE);
                    if ($total_pages > 1) {
                        $page_links = paginate_links([
                            'base'      => add_query_arg('audit_paged', '%#%', $base_url),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $total_pages,
                            'current'   => $page,
                        ]);

                        if ($page_links) {
                            echo '<span class="pagination-links">' . $page_links . '</span>';
                        }
                    }

                    echo '<span class="displaying-num" style="margin-left:8px;">';
                    echo esc_html(
                        sprintf(
                            __('%s eventos', 'mx-pos-pro'),
                            number_format_i18n($total)
                        )
                    );
                    echo '</span>';
                    ?>
                </div>
            </div>
        </form>

        <?php if (empty($rows)): ?>
            <p><?php esc_html_e('No hay eventos de auditoria registrados.', 'mx-pos-pro'); ?></p>
            <?php return; ?>
        <?php endif; ?>

        <table class="widefat striped" style="max-width: 100%;">
            <thead>
                <tr>
                    <th style="width: 155px;"><?php esc_html_e('Fecha / Hora', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Accion', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Entidad', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Actor', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Sucursal', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Caja', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Empleado', 'mx-pos-pro'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Metadata', 'mx-pos-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row):
                    $context       = self::decode_context($row['context_data'] ?? null);
                    $actor_name    = $resolve_actor($row);
                    $entity_label  = $resolve_entity($row);
                    $branch_name   = $resolve_branch(isset($row['branch_id']) ? (int) $row['branch_id'] : null);
                    $register_name = $resolve_register(isset($row['pos_register_id']) ? (int) $row['pos_register_id'] : null);
                    $employee_name = $resolve_employee(isset($row['pos_employee_id']) ? (int) $row['pos_employee_id'] : null);
                    $has_meta      = ! empty($context) || ($row['ip_address'] ?? '') !== '';
                    $row_id        = 'mx-audit-row-' . (int) $row['id'];
                    $message       = $context['message'] ?? '';
                ?>
                    <tr>
                        <td><?php echo esc_html($row['created_at'] ?? '—'); ?></td>
                        <td>
                            <strong><?php echo esc_html($row['action'] ?? '—'); ?></strong>
                            <?php if ($message !== ''): ?>
                                <br><small style="color:#666;"><?php echo esc_html($message); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($entity_label); ?></td>
                        <td><?php echo esc_html($actor_name); ?></td>
                        <td><?php echo esc_html($branch_name); ?></td>
                        <td><?php echo esc_html($register_name); ?></td>
                        <td><?php echo esc_html($employee_name); ?></td>
                        <td>
                            <?php if ($has_meta): ?>
                                <button type="button"
                                        class="button button-small"
                                        onclick="var el=document.getElementById('<?php echo esc_js($row_id); ?>');el.style.display=el.style.display==='none'?'table-row':'none';">
                                    <?php esc_html_e('Ver', 'mx-pos-pro'); ?>
                                </button>
                            <?php else: ?>
                                <span style="color:#a7aaad;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($has_meta): ?>
                    <tr id="<?php echo esc_attr($row_id); ?>" style="display:none;">
                        <td colspan="8" style="padding:12px;background:#f9f9f9;">
                            <?php if ($message !== ''): ?>
                                <p><strong><?php esc_html_e('Mensaje:', 'mx-pos-pro'); ?></strong>
                                    <?php echo esc_html($message); ?></p>
                            <?php endif; ?>
                            <?php if (($row['ip_address'] ?? '') !== ''): ?>
                                <p style="margin:4px 0;color:#666;">
                                    <strong>IP:</strong> <?php echo esc_html($row['ip_address']); ?>
                                    <?php if (($row['user_agent'] ?? '') !== ''): ?>
                                        <br><strong>User-Agent:</strong> <?php echo esc_html(mb_substr($row['user_agent'], 0, 200)); ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if (! empty($context)): ?>
                                <details style="margin-top: 6px;">
                                    <summary style="cursor:pointer;font-weight:600;">
                                        <?php esc_html_e('Ver metadata JSON', 'mx-pos-pro'); ?>
                                    </summary>
                                    <pre style="background:#f0f0f1;padding:12px;overflow:auto;max-height:300px;font-size:12px;margin-top:6px;"><?php
                                        $json_flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
                                        echo esc_html(wp_json_encode($context, $json_flags) ?: '{}');
                                    ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th><?php esc_html_e('Fecha / Hora', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Accion', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Entidad', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Actor', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Sucursal', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Caja', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Empleado', 'mx-pos-pro'); ?></th>
                    <th><?php esc_html_e('Metadata', 'mx-pos-pro'); ?></th>
                </tr>
            </tfoot>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                if ($total_pages > 1) {
                    $bottom_links = paginate_links([
                        'base'      => add_query_arg('audit_paged', '%#%', $base_url),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $total_pages,
                        'current'   => $page,
                    ]);

                    if ($bottom_links) {
                        echo '<span class="pagination-links">' . $bottom_links . '</span>';
                    }
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * @return array{date_from: string, date_to: string, action: string, pos_employee_id: int, pos_register_id: int, branch_id: int, cash_session_id: int, paged: int}
     */
    private static function parse_filters(): array
    {
        return [
            'date_from'        => isset($_GET['audit_date_from']) ? sanitize_text_field(wp_unslash($_GET['audit_date_from'])) : '',
            'date_to'          => isset($_GET['audit_date_to']) ? sanitize_text_field(wp_unslash($_GET['audit_date_to'])) : '',
            'action'           => isset($_GET['audit_action']) ? sanitize_text_field(wp_unslash($_GET['audit_action'])) : '',
            'pos_employee_id'  => isset($_GET['audit_employee']) ? (int) $_GET['audit_employee'] : 0,
            'pos_register_id'  => isset($_GET['audit_register']) ? (int) $_GET['audit_register'] : 0,
            'branch_id'        => isset($_GET['audit_branch']) ? (int) $_GET['audit_branch'] : 0,
            'cash_session_id'  => isset($_GET['audit_session']) ? (int) $_GET['audit_session'] : 0,
            'paged'            => isset($_GET['audit_paged']) ? (int) $_GET['audit_paged'] : 1,
        ];
    }

    private static function has_active_filters(array $filters): bool
    {
        return $filters['date_from'] !== ''
            || $filters['date_to'] !== ''
            || $filters['action'] !== ''
            || $filters['pos_employee_id'] > 0
            || $filters['pos_register_id'] > 0
            || $filters['branch_id'] > 0
            || $filters['cash_session_id'] > 0;
    }

    private static function decode_context(mixed $context_data): array
    {
        if (! is_string($context_data) || trim($context_data) === '') {
            return [];
        }

        $decoded = json_decode($context_data, true);

        return is_array($decoded) ? $decoded : [];
    }
}
