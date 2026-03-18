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
        // Liewood source headers => OUR TEMPLATE headers (must match template_columns word-for-word)
        // As specified in the master mapping template for LIEWOOD.
        'Brand'                     => 'Brend  ',
        'Style Name'                => 'Product name (EN)',
        'Order No.'                 => 'Order No.',
        'Style No'                  => 'Supplier Product ID',
        'Barcode'                   => 'EAN / Barcode',
        'Qty (CU)'                  => 'Qty (Quantity per Unit)',
        'Type'                      => 'item_category',
        'Season'                    => 'Season/Collection',
        'Gender'                    => 'Gender',
        'Color Code'                => 'Color Code',
        'Color Description'         => 'Color Description EN',
        'Size'                      => 'Shoe size',
        'Wholesale Price'           => 'Wholesale Price EUR',
        'Recommended Retail Price'  => 'Recommended Retail Price EUR',
    ],
];

