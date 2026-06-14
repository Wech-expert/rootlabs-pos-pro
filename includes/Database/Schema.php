<?php

namespace MXPOSPro\Database;

defined('ABSPATH') || exit;

final class Schema
{
    public static function get_tables(): array
    {
        global $wpdb;

        $prefix  = $wpdb->prefix;
        $collate = $wpdb->get_charset_collate();

        return [
            'mx_pos_product_index' => "
                CREATE TABLE {$prefix}mx_pos_product_index (
                    id               BIGINT UNSIGNED AUTO_INCREMENT,
                    object_id        BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    product_id       BIGINT UNSIGNED NOT NULL,
                    variation_id     BIGINT UNSIGNED DEFAULT NULL,
                    parent_id        BIGINT UNSIGNED DEFAULT NULL,
                    catalog_group_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    sku              VARCHAR(100)    NOT NULL DEFAULT '',
                    sku_normalized   VARCHAR(100)    NOT NULL DEFAULT '',
                    name             VARCHAR(255)    NOT NULL DEFAULT '',
                    name_normalized  VARCHAR(255)    NOT NULL DEFAULT '',
                    parent_name      VARCHAR(255)    NOT NULL DEFAULT '',
                    variation_label  VARCHAR(255)    NOT NULL DEFAULT '',
                    type             VARCHAR(50)     NOT NULL DEFAULT 'simple',
                    status           VARCHAR(50)     NOT NULL DEFAULT 'publish',
                    is_purchasable   TINYINT(1)      NOT NULL DEFAULT 0,
                    stock_quantity   INT             DEFAULT NULL,
                    stock_status     VARCHAR(50)     NOT NULL DEFAULT 'instock',
                    regular_price    DECIMAL(19,4)   DEFAULT NULL,
                    sale_price       DECIMAL(19,4)   DEFAULT NULL,
                    display_price    DECIMAL(19,4)   DEFAULT NULL,
                    min_price        DECIMAL(19,4)   DEFAULT NULL,
                    max_price        DECIMAL(19,4)   DEFAULT NULL,
                    image_url        TEXT            DEFAULT NULL,
                    image_alt        VARCHAR(255)    NOT NULL DEFAULT '',
                    image_version    VARCHAR(50)     NOT NULL DEFAULT '',
                    searchable_text  LONGTEXT        DEFAULT NULL,
                    index_generation BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    indexed_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY   product_variation (product_id, variation_id),
                    KEY          object_id (object_id),
                    KEY          sku (sku),
                    KEY          sku_normalized (sku_normalized),
                    KEY          name_normalized (name_normalized),
                    KEY          catalog_group_id (catalog_group_id),
                    KEY          status_stock_group (status, stock_status, catalog_group_id),
                    KEY          type_status (type, status),
                    KEY          index_generation (index_generation)
                ) {$collate};
            ",

            'mx_pos_sessions' => "
                CREATE TABLE {$prefix}mx_pos_sessions (
                    id                 BIGINT UNSIGNED AUTO_INCREMENT,
                    cashier_id         BIGINT UNSIGNED NOT NULL,
                    register_id        VARCHAR(100)    NOT NULL DEFAULT '',
                    pos_register_id    BIGINT UNSIGNED DEFAULT NULL,
                    branch_id          BIGINT UNSIGNED DEFAULT NULL,
                    pos_employee_id    BIGINT UNSIGNED DEFAULT NULL,
                    status             VARCHAR(20)     NOT NULL DEFAULT 'open',
                    opening_amount     DECIMAL(19,4)   NOT NULL DEFAULT 0,
                    closing_expected   DECIMAL(19,4)   DEFAULT NULL,
                    closing_counted    DECIMAL(19,4)   DEFAULT NULL,
                    difference         DECIMAL(19,4)   DEFAULT NULL,
                    closed_by          BIGINT UNSIGNED DEFAULT NULL,
                    close_note         VARCHAR(500)    DEFAULT NULL,
                    denominations_json LONGTEXT        DEFAULT NULL,
                    opened_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    closed_at          DATETIME        DEFAULT NULL,
                    voided_at          DATETIME        DEFAULT NULL,
                    voided_by          BIGINT UNSIGNED DEFAULT NULL,
                    void_reason        VARCHAR(500)    DEFAULT NULL,
                    PRIMARY KEY  (id),
                    KEY          status (status),
                    KEY          cashier_id (cashier_id),
                    KEY          pos_register_id (pos_register_id),
                    KEY          branch_id (branch_id),
                    KEY          pos_employee_id (pos_employee_id),
                    KEY          opened_at (opened_at)
                ) {$collate};
            ",

            'mx_pos_sales' => "
                CREATE TABLE {$prefix}mx_pos_sales (
                    id               BIGINT UNSIGNED AUTO_INCREMENT,
                    wc_order_id      BIGINT UNSIGNED NOT NULL,
                    session_id       BIGINT UNSIGNED NOT NULL,
                    branch_id        BIGINT UNSIGNED DEFAULT NULL,
                    pos_register_id  BIGINT UNSIGNED DEFAULT NULL,
                    pos_employee_id  BIGINT UNSIGNED DEFAULT NULL,
                    cashier_id       BIGINT UNSIGNED NOT NULL,
                    total            DECIMAL(19,4)   NOT NULL DEFAULT 0,
                    refunded_total   DECIMAL(19,4)   NOT NULL DEFAULT 0.0000,
                    payment_summary  LONGTEXT        DEFAULT NULL,
                    status           VARCHAR(20)     NOT NULL DEFAULT 'completed',
                    client_request_id VARCHAR(100)   DEFAULT NULL,
                    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY   wc_order_id (wc_order_id),
                    KEY          session_id (session_id),
                    KEY          branch_id (branch_id),
                    KEY          pos_register_id (pos_register_id),
                    KEY          pos_employee_id (pos_employee_id),
                    KEY          cashier_id (cashier_id),
                    KEY          status (status),
                    UNIQUE KEY   client_request_id (client_request_id),
                    KEY          created_at (created_at)
                ) {$collate};
            ",

            'mx_pos_sale_logs' => "
                CREATE TABLE {$prefix}mx_pos_sale_logs (
                    id               BIGINT UNSIGNED AUTO_INCREMENT,
                    sale_id          BIGINT UNSIGNED NOT NULL,
                    event_type       VARCHAR(100)    NOT NULL DEFAULT '',
                    message          LONGTEXT        DEFAULT NULL,
                    created_by       BIGINT UNSIGNED DEFAULT NULL,
                    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY          sale_id (sale_id),
                    KEY          event_type (event_type),
                    KEY          created_at (created_at)
                ) {$collate};
            ",

            'mx_pos_refunds' => "
                CREATE TABLE {$prefix}mx_pos_refunds (
                    id                 BIGINT UNSIGNED AUTO_INCREMENT,
                    sale_id            BIGINT UNSIGNED NOT NULL,
                    wc_refund_id       BIGINT UNSIGNED NOT NULL,
                    session_id         BIGINT UNSIGNED NOT NULL,
                    cashier_id         BIGINT UNSIGNED NOT NULL,
                    refund_type        VARCHAR(20)    NOT NULL DEFAULT 'partial',
                    refund_amount      DECIMAL(19,4)   NOT NULL DEFAULT 0,
                    refund_method      VARCHAR(20)    DEFAULT NULL,
                    items_data         LONGTEXT       DEFAULT NULL,
                    reason             TEXT           DEFAULT NULL,
                    client_request_id  VARCHAR(100)   DEFAULT NULL,
                    created_at         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY   client_request_id (client_request_id),
                    KEY          sale_id (sale_id),
                    KEY          session_id (session_id),
                    KEY          cashier_id (cashier_id),
                    KEY          wc_refund_id (wc_refund_id)
                ) {$collate};
            ",

            'mx_pos_cash_movements' => "
                CREATE TABLE {$prefix}mx_pos_cash_movements (
                    id               BIGINT UNSIGNED AUTO_INCREMENT,
                    session_id       BIGINT UNSIGNED NOT NULL,
                    branch_id        BIGINT UNSIGNED DEFAULT NULL,
                    pos_register_id  BIGINT UNSIGNED DEFAULT NULL,
                    pos_employee_id  BIGINT UNSIGNED DEFAULT NULL,
                    movement_type    VARCHAR(20)     NOT NULL DEFAULT 'cash_in',
                    amount           DECIMAL(19,4)   NOT NULL DEFAULT 0,
                    reason           VARCHAR(255)    DEFAULT NULL,
                    created_by       BIGINT UNSIGNED NOT NULL,
                    client_request_id VARCHAR(100)   DEFAULT NULL,
                    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY          session_id (session_id),
                    KEY          branch_id (branch_id),
                    KEY          pos_register_id (pos_register_id),
                    KEY          pos_employee_id (pos_employee_id),
                    KEY          movement_type (movement_type),
                    KEY          created_at (created_at),
                    UNIQUE KEY   client_request_id (client_request_id)
                ) {$collate};
            ",

            'mx_pos_parked_carts' => "
                CREATE TABLE {$prefix}mx_pos_parked_carts (
                    id               BIGINT UNSIGNED AUTO_INCREMENT,
                    session_id       BIGINT UNSIGNED DEFAULT NULL,
                    cashier_id       BIGINT UNSIGNED NOT NULL,
                    customer_id      BIGINT UNSIGNED DEFAULT NULL,
                    cart_hash        VARCHAR(64)     NOT NULL DEFAULT '',
                    cart_data        LONGTEXT        NOT NULL,
                    note             TEXT            DEFAULT NULL,
                    status           VARCHAR(20)     NOT NULL DEFAULT 'parked',
                    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY   cart_hash (cart_hash),
                    KEY          session_id (session_id),
                    KEY          cashier_id (cashier_id),
                    KEY          status (status)
                ) {$collate};
            ",

            // UNIQUE(session_id, is_final) prevents duplicate Z cuts per session.
            // If Sprint 19 ever persists multiple X cuts, this index must be
            // redesigned (e.g. UNIQUE(session_id, cut_type, is_final)).

            'mx_pos_cash_cuts' => "
                CREATE TABLE {$prefix}mx_pos_cash_cuts (
                    id              BIGINT UNSIGNED AUTO_INCREMENT,
                    session_id      BIGINT UNSIGNED NOT NULL,
                    branch_id       BIGINT UNSIGNED DEFAULT NULL,
                    pos_register_id BIGINT UNSIGNED DEFAULT NULL,
                    pos_employee_id BIGINT UNSIGNED DEFAULT NULL,
                    cut_type        VARCHAR(5)      NOT NULL,
                    sequence        INT UNSIGNED    NOT NULL DEFAULT 1,
                    summary_json    LONGTEXT        NOT NULL,
                    generated_by    BIGINT UNSIGNED NOT NULL,
                    generated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    is_final        TINYINT(1)      NOT NULL DEFAULT 0,
                    PRIMARY KEY  (id),
                    KEY          session_id (session_id),
                    KEY          branch_id (branch_id),
                    KEY          pos_register_id (pos_register_id),
                    KEY          generated_at (generated_at),
                    UNIQUE KEY   unique_final_z (session_id, is_final)
                ) {$collate};
            ",

            'mx_pos_audit_logs' => "
                CREATE TABLE {$prefix}mx_pos_audit_logs (
                    id               BIGINT UNSIGNED AUTO_INCREMENT,
                    actor_id         BIGINT UNSIGNED DEFAULT NULL,
                    branch_id        BIGINT UNSIGNED DEFAULT NULL,
                    pos_register_id  BIGINT UNSIGNED DEFAULT NULL,
                    pos_employee_id  BIGINT UNSIGNED DEFAULT NULL,
                    action           VARCHAR(100)    NOT NULL DEFAULT '',
                    entity_type      VARCHAR(100)    NOT NULL DEFAULT '',
                    entity_id        BIGINT UNSIGNED DEFAULT NULL,
                    ip_address       VARCHAR(45)     DEFAULT NULL,
                    user_agent       TEXT            DEFAULT NULL,
                    context_data     LONGTEXT        DEFAULT NULL,
                    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY          actor_id (actor_id),
                    KEY          branch_id (branch_id),
                    KEY          pos_register_id (pos_register_id),
                    KEY          pos_employee_id (pos_employee_id),
                    KEY          action (action),
                    KEY          entity (entity_type, entity_id),
                    KEY          created_at (created_at)
                ) {$collate};
            ",

            'mx_pos_branches' => "
                CREATE TABLE {$prefix}mx_pos_branches (
                    id               BIGINT UNSIGNED AUTO_INCREMENT,
                    name             VARCHAR(150)    NOT NULL DEFAULT '',
                    slug             VARCHAR(50)     NOT NULL DEFAULT '',
                    address          TEXT            DEFAULT NULL,
                    phone            VARCHAR(30)     DEFAULT NULL,
                    is_active        TINYINT(1)      NOT NULL DEFAULT 1,
                    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY   slug (slug),
                    KEY          is_active (is_active)
                ) {$collate};
            ",

            'mx_pos_registers' => "
                CREATE TABLE {$prefix}mx_pos_registers (
                    id               BIGINT UNSIGNED AUTO_INCREMENT,
                    branch_id        BIGINT UNSIGNED NOT NULL,
                    name             VARCHAR(100)    NOT NULL DEFAULT '',
                    slug             VARCHAR(50)     NOT NULL DEFAULT '',
                    is_active        TINYINT(1)      NOT NULL DEFAULT 1,
                    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY   slug (slug),
                    KEY          branch_id (branch_id),
                    KEY          is_active (is_active)
                ) {$collate};
            ",

            'mx_pos_employees' => "
                CREATE TABLE {$prefix}mx_pos_employees (
                    id               BIGINT UNSIGNED AUTO_INCREMENT,
                    branch_id        BIGINT UNSIGNED DEFAULT NULL,
                    wp_user_id       BIGINT UNSIGNED DEFAULT NULL,
                    username         VARCHAR(100)    NOT NULL DEFAULT '',
                    password_hash    VARCHAR(255)    DEFAULT NULL,
                    display_name     VARCHAR(150)    NOT NULL DEFAULT '',
                    role             VARCHAR(30)     NOT NULL DEFAULT 'cashier',
                    is_active        TINYINT(1)      NOT NULL DEFAULT 1,
                    deleted_at       DATETIME        DEFAULT NULL,
                    failed_attempts  INT UNSIGNED    NOT NULL DEFAULT 0,
                    locked_until     DATETIME        DEFAULT NULL,
                    last_login_at    DATETIME        DEFAULT NULL,
                    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY   wp_user_id (wp_user_id),
                    UNIQUE KEY   username (username),
                    KEY          branch_id (branch_id),
                    KEY          role (role),
                    KEY          is_active (is_active)
                ) {$collate};
            ",

            'mx_pos_payment_methods' => "
                CREATE TABLE {$prefix}mx_pos_payment_methods (
                    id                   BIGINT UNSIGNED AUTO_INCREMENT,
                    name                 VARCHAR(100)    NOT NULL DEFAULT '',
                    slug                 VARCHAR(50)     NOT NULL DEFAULT '',
                    payment_type         VARCHAR(20)     NOT NULL DEFAULT 'other',
                    affects_cash_register TINYINT(1)     NOT NULL DEFAULT 0,
                    allow_reference      TINYINT(1)      NOT NULL DEFAULT 0,
                    card_fee_enabled     TINYINT(1)      NOT NULL DEFAULT 0,
                    card_fee_type        VARCHAR(20)     DEFAULT NULL,
                    card_fee_value       DECIMAL(10,4)   DEFAULT NULL,
                    wc_gateway_id        VARCHAR(100)    DEFAULT NULL,
                    is_active            TINYINT(1)      NOT NULL DEFAULT 1,
                    sort_order           INT UNSIGNED    NOT NULL DEFAULT 0,
                    created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY   slug (slug),
                    KEY          is_active (is_active),
                    KEY          sort_order (sort_order)
                ) {$collate};
            ",

            'mx_pos_order_payments' => "
                CREATE TABLE {$prefix}mx_pos_order_payments (
                    id                BIGINT UNSIGNED AUTO_INCREMENT,
                    sale_id           BIGINT UNSIGNED NOT NULL,
                    payment_method_id BIGINT UNSIGNED NOT NULL,
                    amount            DECIMAL(19,4)   NOT NULL DEFAULT 0,
                    tendered_amount   DECIMAL(19,4)   DEFAULT NULL,
                    change_amount     DECIMAL(19,4)   DEFAULT NULL,
                    currency          VARCHAR(10)     NOT NULL DEFAULT 'MXN',
                    status            VARCHAR(20)     NOT NULL DEFAULT 'completed',
                    card_reference    VARCHAR(100)    DEFAULT NULL,
                    transaction_id    VARCHAR(255)    DEFAULT NULL,
                    client_request_id VARCHAR(100)    DEFAULT NULL,
                    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY          sale_id (sale_id),
                    KEY          payment_method_id (payment_method_id),
                    KEY          status (status),
                    UNIQUE KEY   client_request_id (client_request_id),
                    KEY          created_at (created_at)
                ) {$collate};
            ",
        ];
    }
}
