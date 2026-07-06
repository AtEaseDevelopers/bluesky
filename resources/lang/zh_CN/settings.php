<?php

return [
    'order_fields' => '订单字段设置',
    'order_fields_title' => '可选订单字段配置',
    'order_fields_help' => '配置客户下单时显示的重量预设按钮和海鲜处理方式选项。',

    'weight_presets' => '重量预设',
    'weight_presets_help' => '以逗号或换行分隔的公斤数值（例如 1, 1.5, 2）。',
    'weight_presets_placeholder' => '1, 1.5, 2, 2.5, 3',

    'situation_label' => '处理方式选项标签',
    'situation_label_help' => '商品选项中与此标签匹配的内容将显示为快捷选择按钮。',

    'situation_options' => '默认处理方式选项',
    'situation_options_help' => '供管理员配置商品时参考的列表（如 live、kill、clean 等）。',
    'situation_options_placeholder' => 'live, kill, clean',

    'save_settings' => '保存设置',
    'updated_success' => '订单字段设置已更新。',

    'do_settings' => '送货单设置',
    'do_settings_help' => '控制送货单 PDF 是否显示单价、行小计和订单总额。',
    'do_show_prices' => '在送货单上显示单价和小计',
    'do_show_prices_help' => '启用后，送货单将显示单价、行小计、配送费、调整金额和总额。总重量始终显示。',
    'do_settings_updated' => '送货单设置已更新。',
    'weight_presets_required' => '请至少输入一个重量预设值。',
    'situation_options_required' => '请至少输入一个处理方式选项。',
];
