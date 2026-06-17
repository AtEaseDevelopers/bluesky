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
        'delete-customer-visibility-product' => 'customers',
        'get-products-for-category' => 'customers',
        'orders' => 'orders',
        'order' => 'orders',
        'delivery-slots' => 'orders',
        'fetch-delivery-slots' => 'orders',
        'change-order-status' => 'orders',
        'change-order-lorry' => 'orders',
        'assign-order-driver' => 'orders',
        'order-products-list' => 'orders',
        'update-order-products-weight' => 'orders',
        'download_do_zip' => 'orders',
        'inventory' => 'products',
        'fetch-stock-balances' => 'products',
        'fetch-stock-movements' => 'products',
        'daily-sales-report' => 'reports',
        'export-daily-sales-report' => 'reports',
        'do-report' => 'reports',
        'lorry' => 'drivers',
        'get-lorry' => 'drivers',
        'areas' => 'settings',
        'fetch-areas' => 'settings',
        'uom' => 'settings',
        'fetch-uom' => 'settings',
        'settings' => 'settings',
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
