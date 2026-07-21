<?php

return [
    'portals' => [
        'admin' => [
            'label' => '管理端',
            'permissions' => [
                'dashboard' => [
                    'label' => '仪表盘',
                    'description' => '查看管理端仪表盘和个人资料。',
                ],
                'customers' => [
                    'label' => '客户',
                    'description' => '管理、邀请和编辑客户账号。',
                ],
                'orders' => [
                    'label' => '订单',
                    'description' => '管理订单、配送时段、付款和 PDF 文件。',
                ],
                'products' => [
                    'label' => '库存 / 商品',
                    'description' => '库存余额、入库/出库和变动记录。',
                ],
                'reports' => [
                    'label' => '报表',
                    'description' => '每日销售和送货单报表。',
                ],
                'drivers' => [
                    'label' => '司机',
                    'description' => '管理司机和司机分配。',
                ],
                'settings' => [
                    'label' => '系统设置',
                    'description' => '区域、计量单位和订单字段设置。',
                ],
            ],
        ],
        'customer' => [
            'label' => '客户端',
            'permissions' => [
                'products' => [
                    'label' => '订购菜单',
                    'description' => '浏览商品并下单。',
                ],
                'cart' => [
                    'label' => '购物车',
                    'description' => '查看和管理购物车。',
                ],
                'checkout' => [
                    'label' => '结账',
                    'description' => '从结账页提交订单。',
                ],
                'orders' => [
                    'label' => '我的订单',
                    'description' => '查看订单历史和订单摘要。',
                ],
                'order_payments' => [
                    'label' => '订单付款',
                    'description' => '在订单上提交付款凭证。',
                ],
                'bulk_payments' => [
                    'label' => '批量付款',
                    'description' => '账期客户可提交批量付款。',
                ],
                'invoices' => [
                    'label' => '发票',
                    'description' => '在符合条件时下载订单发票。',
                ],
                'profile' => [
                    'label' => '我的资料',
                    'description' => '查看资料并修改密码。',
                ],
                'policies' => [
                    'label' => '条款与政策',
                    'description' => '查看条款、政策和联系信息。',
                ],
            ],
        ],
        'driver' => [
            'label' => '司机端',
            'permissions' => [
                'delivery_orders' => [
                    'label' => '配送订单',
                    'description' => '查看已分配的配送订单列表。',
                ],
                'order_detail' => [
                    'label' => '订单详情',
                    'description' => '打开已分配订单的详情页。',
                ],
                'customer_info' => [
                    'label' => '客户信息',
                    'description' => '查看已分配订单上的客户姓名、联系方式和送货地址。',
                ],
                'update_status' => [
                    'label' => '更新配送状态',
                    'description' => '将订单标记为配送中或已送达。',
                ],
                'record_payment' => [
                    'label' => '记录付款',
                    'description' => '记录货到付款或现场付款。',
                ],
                'payment_proof' => [
                    'label' => '付款凭证',
                    'description' => '查看已上传的付款凭证文件。',
                ],
            ],
        ],
    ],
];
