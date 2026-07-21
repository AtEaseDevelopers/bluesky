<?php

return [

    'portals' => [
        'admin' => [
            'label' => 'Admin Portal',
            'description' => 'Back-office staff using the admin portal.',
            'permissions' => [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'description' => 'Admin dashboard and profile.',
                    'capabilities' => [
                        'view' => [
                            'label' => 'View',
                            'description' => 'Open the dashboard and profile pages.',
                        ],
                    ],
                    'default' => ['view' => true],
                ],
                'customers' => [
                    'label' => 'Customers',
                    'description' => 'Customer accounts, invitations, and POS walk-in orders.',
                    'capabilities' => [
                        'view' => [
                            'label' => 'View',
                            'description' => 'List and open customer records.',
                        ],
                        'create' => [
                            'label' => 'Create',
                            'description' => 'Add customers, send invites, and use POS for new orders.',
                        ],
                        'edit' => [
                            'label' => 'Edit',
                            'description' => 'Update customer details, credit, and login links.',
                        ],
                    ],
                    'default' => ['view' => true, 'create' => true, 'edit' => true],
                ],
                'orders' => [
                    'label' => 'Orders',
                    'description' => 'Orders, delivery slots, payments, and PDF documents.',
                    'capabilities' => [
                        'view' => [
                            'label' => 'View',
                            'description' => 'List orders, view summaries, invoices, and delivery orders.',
                        ],
                        'create' => [
                            'label' => 'Create',
                            'description' => 'Add new orders from the admin portal.',
                        ],
                        'edit' => [
                            'label' => 'Edit',
                            'description' => 'Adjust orders, change status, record payments, and manage delivery slots.',
                        ],
                    ],
                    'default' => ['view' => true, 'create' => true, 'edit' => true],
                ],
                'products' => [
                    'label' => 'Inventory / Products',
                    'description' => 'Products, stock balance, stock movements, and daily prices.',
                    'capabilities' => [
                        'view' => [
                            'label' => 'View',
                            'description' => 'View products, stock balances, and movement logs.',
                        ],
                        'create' => [
                            'label' => 'Create',
                            'description' => 'Add products and import product data.',
                        ],
                        'edit' => [
                            'label' => 'Edit',
                            'description' => 'Edit products, stock in/out, prices, and categories.',
                        ],
                    ],
                    'default' => ['view' => true, 'create' => true, 'edit' => true],
                ],
                'reports' => [
                    'label' => 'Reports',
                    'description' => 'Daily sales and delivery order reports.',
                    'capabilities' => [
                        'view' => [
                            'label' => 'View',
                            'description' => 'Open and export reports.',
                        ],
                    ],
                    'default' => ['view' => true],
                ],
                'drivers' => [
                    'label' => 'Drivers / Vehicles',
                    'description' => 'Drivers, vehicles, and driver registry.',
                    'capabilities' => [
                        'view' => [
                            'label' => 'View',
                            'description' => 'List drivers and vehicles.',
                        ],
                        'create' => [
                            'label' => 'Create',
                            'description' => 'Add drivers and vehicles.',
                        ],
                        'edit' => [
                            'label' => 'Edit',
                            'description' => 'Update driver and vehicle records.',
                        ],
                    ],
                    'default' => ['view' => true, 'create' => true, 'edit' => true],
                ],
                'settings' => [
                    'label' => 'System Settings',
                    'description' => 'Areas, UOM, categories, and document settings.',
                    'capabilities' => [
                        'view' => [
                            'label' => 'View',
                            'description' => 'Open settings pages.',
                        ],
                        'edit' => [
                            'label' => 'Edit',
                            'description' => 'Change areas, UOM, categories, and delivery order settings.',
                        ],
                    ],
                    'default' => ['view' => false, 'edit' => false],
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
                'vehicle' => [
                    'label' => 'Vehicle Selection',
                    'description' => 'Choose which registered vehicle the driver is operating.',
                    'default' => false,
                ],
                'assigned_customers' => [
                    'label' => 'Assigned Customers',
                    'description' => 'View assigned customer list with invoice payment status and due dates.',
                    'default' => false,
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
                    'description' => 'Record COD payments on delivery.',
                    'default' => true,
                ],
                'payment_proof' => [
                    'label' => 'Payment Proof',
                    'description' => 'View uploaded payment proof files.',
                    'default' => true,
                ],
                'adjust_order' => [
                    'label' => 'Adjust Order',
                    'description' => 'Update actual qty/weight during delivery when totals change.',
                    'default' => true,
                ],
                'make_payment' => [
                    'label' => 'Make Payment',
                    'description' => 'Show payment gateway button for customer to pay (online).',
                    'default' => true,
                ],
            ],
        ],
    ],

    'member_routes' => [
        'member.products' => 'products',
        'member.products.show' => 'products',
        'member.add-to-cart' => 'products',
        'member.add-to-cart-product-info' => 'products',
        'member.cart' => 'cart',
        'member.checkout' => 'checkout',
        'member.orders' => 'orders',
        'member.orders.summary' => 'orders',
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
        'driver.vehicle.edit' => 'vehicle',
        'driver.vehicle.update' => 'vehicle',
        'driver.customers.index' => 'assigned_customers',
        'driver.customers.show' => 'assigned_customers',
        'driver.customers.record-payment' => 'record_payment',
        'driver.orders.index' => 'delivery_orders',
        'driver.orders.show' => 'order_detail',
        'driver.orders.update-status' => 'update_status',
        'driver.orders.adjust' => 'adjust_order',
        'driver.orders.record-payment' => 'record_payment',
        'driver.orders.payment-proof' => 'payment_proof',
    ],

];
