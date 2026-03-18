<?php

return [
    'supplier_code' => 'liewood',
    'supplier_name' => 'LIEWOOD',

    /**
     * null => first sheet
     * string => sheet name
     * int => sheet index (0-based)
     */
    'sheet' => null,

    /**
     * Mapping: Liewood source header => BaseLinker template header.
     * Based on "Описание" row for LIEWOOD in the mapping CSV.
     */
    'column_mapping' => [
        // Liewood headers            // Target template headers (as in master template)
        'Brand'                     => 'Brend (manufacturer)',
        'Style Name'                => 'Product name (EN)',
        'Order No.'                 => 'Order No.',
        'Style No'                  => 'Supplier Product ID',
        'Barcode'                   => 'EAN / Barcode',
        'Qty (CU)'                  => 'Qty',
        'Type'                      => 'Category',
        'Season'                    => 'Season/Collection',
        'Gender'                    => 'Gender',
        'Color Code'                => 'Color Code',
        'Color Description'         => 'Color Description EN',
        'Size'                      => 'Age Size',
        'Wholesale Price'           => 'Wholesale Price',
        'Recommended Retail Price'  => 'Recommended Retail Price',
    ],
];

