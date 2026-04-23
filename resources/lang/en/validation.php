<?php

declare(strict_types=1);

return [
    'seller_name_required' => 'Seller name is required.',
    'seller_vat_required' => 'Seller VAT number is required.',
    'seller_vat_invalid' => 'Seller VAT number must be a 15-digit numeric value.',
    'items_required' => 'At least one invoice item is required.',
    'item_invalid' => 'Invoice item :line is invalid.',
    'item_name_required' => 'Invoice item :line name is required.',
    'item_quantity_invalid' => 'Invoice item :line quantity must be greater than zero.',
    'item_unit_price_invalid' => 'Invoice item :line unit price must not be negative.',
    'item_tax_percent_invalid' => 'Invoice item :line tax percent must not be negative.',
    'subtotal_mismatch' => 'Invoice subtotal mismatch. Expected :expected, got :actual.',
    'tax_amount_mismatch' => 'Invoice tax amount mismatch. Expected :expected, got :actual.',
    'total_amount_mismatch' => 'Invoice total amount mismatch. Expected :expected, got :actual.',
];
