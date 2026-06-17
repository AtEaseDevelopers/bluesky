<?php

return [

    'portals' => [
        'admin' => [
            'label' => 'Admin Portal',
            'description' => 'Back-office staff using the admin portal.',
            'permissions' => [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'description' => 'View the admin dashboard and profile.',
                    'default' => true,
                ],
                'customers' => [
                    'label' => 'Customers',
                    'description' => 'Manage, invite, and edit customer accounts.',
                    'default' => true,
                ],
                'orders' => [
                    'label' => 'Orders',
                    'description' => 'Manage orders, delivery slots, payments, and PDFs.',
                    'default' => true,
                ],
                'products' => [
                    'label' => 'Inventory / Products',
                    'description' => 'Stock balance, stock in/out, and movement logs.',
                    'default' => true,
                ],
                'reports' => [
                    'label' => 'Reports',
                    'description' => 'Daily sales and delivery order reports.',
                    'default' => true,
                ],
                'drivers' => [
                    'label' => 'Drivers / Lorry',
                    'description' => 'Manage drivers and lorry assignments.',
                    'default' => true,
                ],
                'settings' => [
                    'label' => 'System Settings',
                    'description' => 'Areas, UOM, and order field settings.',
                    'default' => false,
                ],
            ],
        ],

        'customer' => [
            'label' => 'Customer Portal',
            'description' => 'Registered customers using the customer portal.',
            'permissions' => [
                'products' => [
                    'label' => 'Order Menu',
                    'description' => 'Browse products and place orders.',
                    'default' => true,
                ],
                'cart' => [
                    'label' => 'Cart',
                    'description' => 'View and manage the shopping cart.',
                    'default' => true,
                ],
                'checkout' => [
                    'label' => 'Checkout',
                    'description' => 'Submit orders from checkout.',
                    'default' => true,
                ],
                'orders' => [
                    'label' => 'My Orders',
                    'description' => 'View order history and order summary.',
                    'default' => true,
                ],
                'order_review' => [
                    'label' => 'Order Review',
                    'description' => 'Approve or reject orders pending customer review.',
                    'default' => true,
                ],
                'order_payments' => [
                    'label' => 'Order Payments',
                    'description' => 'Submit payment proof on orders.',
                    'default' => true,
                ],
                'bulk_payments' => [
                    'label' => 'Bulk Payment',
                    'description' => 'Credit customers can submit bulk payments.',
                    'default' => true,
                ],
                'invoices' => [
                    'label' => 'Invoices',
                    'description' => 'Download order invoices when eligible.',
                    'default' => true,
                ],
                'profile' => [
                    'label' => 'My Profile',
                    'description' => 'View profile and change password.',
                    'default' => true,
                ],
                'policies' => [
                    'label' => 'Terms & Policies',
                    'description' => 'View terms, policies, and contact information.',
                    'default' => true,
                ],
            ],
        ],

        'driver' => [
            'label' => 'Driver Portal',
            'description' => 'Delivery drivers using the driver portal.',
            'permissions' => [
                'delivery_orders' => [
                    'label' => 'Delivery Orders',
                    'description' => 'View assigned delivery order list.',
                    'default' => true,
                ],
                'order_detail' => [
                    'label' => 'Order Detail',
                    'description' => 'Open assigned order detail pages.',
                    'default' => true,
                ],
                'customer_info' => [
                    'label' => 'Customer Information',
                    'description' => 'View customer name, contact, and delivery address on assigned orders.',
                    'default' => true,
                ],
                'update_status' => [
                    'label' => 'Update Delivery Status',
                    'description' => 'Mark orders in route or delivered.',
                    'default' => true,
                ],
                'record_payment' => [
                    'label' => 'Record Payment',
                    'description' => 'Record COD or on-site payments.',
                    'default' => true,
                ],
                'payment_proof' => [
                    'label' => 'Payment Proof',
                    'description' => 'View uploaded payment proof files.',
                    'default' => true,
                ],
            ],
        ],
    ],

    'member_routes' => [
        'member.products' => 'products',
        'member.products.show' => 'products',
        'member.add-to-cart' => 'products',
        'member.cart' => 'cart',
        'member.checkout' => 'checkout',
        'member.orders' => 'orders',
        'member.orders.summary' => 'orders',
        'member.orders.review' => 'order_review',
        'member.orders.review.approve' => 'order_review',
        'member.orders.review.reject' => 'order_review',
        'member.orders.payments.store' => 'order_payments',
        'member.orders.payment-proof' => 'order_payments',
        'member.bulk-payments' => 'bulk_payments',
        'member.bulk-payments.store' => 'bulk_payments',
        'member.profile' => 'profile',
        'member.update.password' => 'profile',
        'member.policies.show' => 'policies',
    ],

    'member_paths' => [
        'update-cart-item' => 'cart',
        'remove-cart-item' => 'cart',
        'add-to-cart-product-info' => 'products',
        'checkout' => 'checkout',
        'order/buy-again' => 'orders',
        'orders/export' => 'orders',
    ],

    'member_file_permissions' => [
        'invoice' => 'invoices',
        'delivery-order' => 'orders',
    ],

    'driver_routes' => [
        'driver.orders.index' => 'delivery_orders',
        'driver.orders.show' => 'order_detail',
        'driver.orders.update-status' => 'update_status',
        'driver.orders.record-payment' => 'record_payment',
        'driver.orders.payment-proof' => 'payment_proof',
    ],

];
