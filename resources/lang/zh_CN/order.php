<?php

return [
    'status' => [
        'pending' => '待处理',
        'packing' => '打包中',
        'handed_to_customer' => '已送达',
        'in_route' => '配送中',
        'delivered' => '已送达',
        'completed' => '已完成',
        'cancelled' => '已取消',
        'paid_completed' => '已送达',
        'processing' => '处理中',
        'delivering' => '配送中',
    ],
    'payment_status' => [
        'unpaid' => '未付款',
        'pending' => '待确认',
        'partial' => '未付清',
        'paid' => '已付款',
        'payment_due' => '到期应付',
    ],
    'fulfillment_types' => [
        'delivery' => '配送',
        'pickup' => '自提',
        'courier' => '快递',
    ],
    'order_type' => [
        'registered' => '注册客户',
        'walk_in' => '散客',
        'public' => '公开链接订单',
        'pos' => 'POS',
    ],
    'file' => [
        'invoice' => '发票',
        'invoice2' => '无价格发票',
        'delivery-order' => '送货单',
    ],
    'payment_methods' => [
        'cash' => '现金',
        'qr' => '二维码',
        'bank-transfer' => '银行转账',
        'e-wallet' => '电子钱包',
        'payment-gateway' => '支付网关',
        'credit-term' => '账期',
        'customer-credit' => '客户信用',
        'cod' => '货到付款',
        'in-store' => '店内付款',
        'term' => '货到付款 / 账期',
    ],
];
