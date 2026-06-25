<?php

return [
    'status' => [
        'pending' => 'Pending',
        'customer_reviewing' => 'Customer Reviewing',
        'in_route' => 'In Route',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        // Legacy status kept for old records until migrated.
        'paid_completed' => 'Delivered',
        // Legacy values written before status workflow was unified.
        'processing' => 'Processing',
        'delivering' => 'In Route',
        'completed' => 'Delivered',
    ],
    'payment_status' => [
        'unpaid' => 'Unpaid',
        'pending' => 'Pending',
        'partial' => 'Unpaid',
        'paid' => 'Paid',
        'payment_due' => 'Due',
    ],
    'fulfillment_types' => [
        'delivery' => 'Delivery',
        'pickup' => 'Pickup / Courier',
    ],
    'order_type' => [
        'registered' => 'Registered Customer',
        'walk_in' => 'Walk-in',
        'public' => 'General Link Order',
    ],
    'file' => [
        'invoice' => 'Invoice',
        'invoice2' => 'Invoice W/O Price',
        'delivery-order' => 'Delivery Order',
    ],
    'payment_methods' => [
        'cash' => 'Cash',
        'qr' => 'QR',
        'bank-transfer' => 'Bank Transfer',
        'e-wallet' => 'E-Wallet',
        'payment-gateway' => 'Payment Gateway',
        'credit-term' => 'Credit Term',
        'customer-credit' => 'Customer Credit',
        'cod' => 'COD',
    ],
];
