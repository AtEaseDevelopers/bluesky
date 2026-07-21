<?php

return [
    'portals' => [
        'admin' => [
            'label' => 'Admin Portal',
            'permissions' => [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'description' => 'View the admin dashboard and profile.',
                ],
                'customers' => [
                    'label' => 'Customers',
                    'description' => 'Manage, invite, and edit customer accounts.',
                ],
                'orders' => [
                    'label' => 'Orders',
                    'description' => 'Manage orders, delivery slots, payments, and PDFs.',
                ],
                'products' => [
                    'label' => 'Inventory / Products',
                    'description' => 'Stock balance, stock in/out, and movement logs.',
                ],
                'reports' => [
                    'label' => 'Reports',
                    'description' => 'Daily sales and delivery order reports.',
                ],
                'drivers' => [
                    'label' => 'Drivers',
                    'description' => 'Manage drivers and driver assignments.',
                ],
                'settings' => [
                    'label' => 'System Settings',
                    'description' => 'Areas, UOM, and order field settings.',
                ],
            ],
        ],
        'customer' => [
            'label' => 'Customer Portal',
            'permissions' => [
                'products' => [
                    'label' => 'Order Menu',
                    'description' => 'Browse products and place orders.',
                ],
                'cart' => [
                    'label' => 'Cart',
                    'description' => 'View and manage the shopping cart.',
                ],
                'checkout' => [
                    'label' => 'Checkout',
                    'description' => 'Submit orders from checkout.',
                ],
                'orders' => [
                    'label' => 'My Orders',
                    'description' => 'View order history and order summary.',
                ],
                'order_payments' => [
                    'label' => 'Order Payments',
                    'description' => 'Submit payment proof on orders.',
                ],
                'bulk_payments' => [
                    'label' => 'Bulk Payment',
                    'description' => 'Credit customers can submit bulk payments.',
                ],
                'invoices' => [
                    'label' => 'Invoices',
                    'description' => 'Download order invoices when eligible.',
                ],
                'profile' => [
                    'label' => 'My Profile',
                    'description' => 'View profile and change password.',
                ],
                'policies' => [
                    'label' => 'Terms & Policies',
                    'description' => 'View terms, policies, and contact information.',
                ],
            ],
        ],
        'driver' => [
            'label' => 'Driver Portal',
            'permissions' => [
                'delivery_orders' => [
                    'label' => 'Delivery Orders',
                    'description' => 'View assigned delivery order list.',
                ],
                'order_detail' => [
                    'label' => 'Order Detail',
                    'description' => 'Open assigned order detail pages.',
                ],
                'customer_info' => [
                    'label' => 'Customer Information',
                    'description' => 'View customer name, contact, and delivery address on assigned orders.',
                ],
                'update_status' => [
                    'label' => 'Update Delivery Status',
                    'description' => 'Mark orders in route or delivered.',
                ],
                'record_payment' => [
                    'label' => 'Record Payment',
                    'description' => 'Record COD or on-site payments.',
                ],
                'payment_proof' => [
                    'label' => 'Payment Proof',
                    'description' => 'View uploaded payment proof files.',
                ],
            ],
        ],
    ],
];
