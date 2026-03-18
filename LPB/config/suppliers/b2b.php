<?php

return [
    'supplier_code' => 'b2b',
    'supplier_name' => 'B2B',

    /**
     * null => first sheet
     * string => sheet name
     * int => sheet index (0-based)
     */
    'sheet' => null,

    /**
     * Explicit mapping: supplier source column header => BaseLinker template column header.
     * Strict rules:
     * - no guessing
     * - if source column missing => target stays empty
     * - if target column not in template => configuration error
     */
    'column_mapping' => [
        // Example placeholders (update to exact header names from supplier file):
        'brand' => 'Brend (manufacturer)',
        'description' => 'Product name (EN)',
        'sku' => 'Sku',
        'matrix' => 'композитным SKU',
        'ean' => 'EAN / Barcode',
        'collection' => 'Collection',
        'color_code' => 'Color Code',
        'color' => 'Color Description EN',
        'size' => 'Age Size',
        'quantity' => 'Qty',
        'unit_price' => 'Wholesale Price',
        'pvp' => 'Recommended Retail Price',
        'item_category' => 'Category',
        'item_group' => 'Sub-category',
        'genere' => 'Gender',
    ],
];

