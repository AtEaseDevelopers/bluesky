<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin portal modules and allowed roles
    |--------------------------------------------------------------------------
    |
    | Superadmin — full access (all modules).
    | Admin — Order, Product (inventory), Customer, Report, Driver only.
    | Settings (system configuration, admin users, areas, UOM) — superadmin only.
    |
    */

    'roles' => [
        'superadmin' => 'Superadmin',
        'admin' => 'Admin',
    ],

    'modules' => [
        'dashboard' => ['superadmin', 'admin'],
        'customers' => ['superadmin', 'admin'],
        'orders' => ['superadmin', 'admin'],
        'products' => ['superadmin', 'admin'],
        'reports' => ['superadmin', 'admin'],
        'drivers' => ['superadmin', 'admin'],
        'settings' => ['superadmin'],
    ],

    'route_modules' => [
        'dashboard' => 'dashboard',
        'profile' => 'dashboard',
        'customers' => 'customers',
        'customer' => 'customers',
        'pos' => 'customers',
        'delete-customer-visibility-product' => 'customers',
        'get-products-for-category' => 'customers',
        'orders' => 'orders',
        'order' => 'orders',
        'delivery-slots' => 'orders',
        'delivery-blackouts' => 'orders',
        'fetch-delivery-slots' => 'orders',
        'change-order-status' => 'orders',
        'change-order-delivery' => 'orders',
        'assign-order-driver' => 'orders',
        'order-products-list' => 'orders',
        'update-order-products-weight' => 'orders',
        'download_do_zip' => 'orders',
        'inventory' => 'products',
        'fetch-stock-balances' => 'products',
        'fetch-stock-movements' => 'products',
        'products' => 'products',
        'products-import' => 'products',
        'product' => 'products',
        'product-daily-price' => 'products',
        'product-daily-prices' => 'products',
        'daily-sales-report' => 'reports',
        'export-daily-sales-report' => 'reports',
        'do-report' => 'reports',
        'drivers' => 'drivers',
        'fetch-drivers' => 'drivers',
        'vehicles' => 'drivers',
        'fetch-vehicles' => 'drivers',
        'areas' => 'settings',
        'fetch-areas' => 'settings',
        'uom' => 'settings',
        'fetch-uom' => 'settings',
        'product-categories' => 'settings',
        'fetch-product-categories' => 'settings',
        'customer-categories' => 'settings',
        'fetch-customer-categories' => 'settings',
        'settings' => 'settings',
    ],

    /*
    | POST routes that only load data for list pages (view permission).
    */
    'view_post_routes' => [
        'fetch-*',
        'order/get-customer-info',
        'order-products-list',
    ],

    /*
    | POST routes that create new records (create permission).
    */
    'create_post_routes' => [
        'customer/add',
        'customer/invite',
        'order/add',
        'product/add',
        'products-import',
        'pos/checkout',
        'pos/*',
    ],

    /*
    | Explicit module/capability overrides for routes that do not follow defaults.
    */
    'capability_overrides' => [
        'profile' => ['module' => 'dashboard', 'capability' => 'view'],
        'customers/export' => ['module' => 'customers', 'capability' => 'view'],
        'orders/export' => ['module' => 'orders', 'capability' => 'view'],
        'products/export' => ['module' => 'products', 'capability' => 'view'],
        'order/summary/*' => ['module' => 'orders', 'capability' => 'view'],
        'orders/*/invoice' => ['module' => 'orders', 'capability' => 'view'],
        'orders/*/invoice2' => ['module' => 'orders', 'capability' => 'view'],
        'orders/*/delivery-order' => ['module' => 'orders', 'capability' => 'view'],
        'orders/*/payment-proof/*' => ['module' => 'orders', 'capability' => 'view'],
        'order/batch-download-files' => ['module' => 'orders', 'capability' => 'view'],
        'download_do_zip' => ['module' => 'orders', 'capability' => 'view'],
        'export-daily-sales-report' => ['module' => 'reports', 'capability' => 'view'],
        'customer/invite/success/*' => ['module' => 'customers', 'capability' => 'create'],
        'customer/generate-new-login-link/*' => ['module' => 'customers', 'capability' => 'edit'],
        'customer/generate-registration-link/*' => ['module' => 'customers', 'capability' => 'edit'],
        'settings/delivery-order' => ['module' => 'settings', 'capability' => 'view'],
    ],

    /*
    | Routes that only superadmin may access, regardless of role permissions.
    */
    'superadmin_only_segments' => [
        'admins',
        'fetch-admins',
        'update-admin-status',
        'roles',
        'role-permissions',
    ],

];
