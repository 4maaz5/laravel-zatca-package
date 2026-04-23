<?php

declare(strict_types=1);

return [
    'seller_name_required' => 'اسم البائع مطلوب.',
    'seller_vat_required' => 'الرقم الضريبي للبائع مطلوب.',
    'seller_vat_invalid' => 'يجب أن يكون الرقم الضريبي للبائع مكونًا من 15 رقمًا.',
    'items_required' => 'يجب إضافة عنصر واحد على الأقل في الفاتورة.',
    'item_invalid' => 'بند الفاتورة رقم :line غير صالح.',
    'item_name_required' => 'اسم بند الفاتورة رقم :line مطلوب.',
    'item_quantity_invalid' => 'يجب أن تكون كمية بند الفاتورة رقم :line أكبر من صفر.',
    'item_unit_price_invalid' => 'يجب ألا يكون سعر الوحدة في بند الفاتورة رقم :line سالبًا.',
    'item_tax_percent_invalid' => 'يجب ألا تكون نسبة الضريبة في بند الفاتورة رقم :line سالبة.',
    'subtotal_mismatch' => 'إجمالي الفاتورة قبل الضريبة غير مطابق. المتوقع :expected، والموجود :actual.',
    'tax_amount_mismatch' => 'قيمة الضريبة غير مطابقة. المتوقع :expected، والموجود :actual.',
    'total_amount_mismatch' => 'إجمالي الفاتورة غير مطابق. المتوقع :expected، والموجود :actual.',
];
