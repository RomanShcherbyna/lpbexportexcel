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
     * При LIEWOOD_RETAIL_EXPORT=true — колонки как в retail final CSV (liewood_retail.php).
     * При false — старый master-шаблон (product_import.template_columns).
     */
    'column_mapping' => filter_var(env('LIEWOOD_RETAIL_EXPORT', true), FILTER_VALIDATE_BOOLEAN)
        ? [
            'Style Name' => 'Product name (EN)',
            'Order No.' => 'Order No.',
            'Style No' => 'Supplier product ID',
            'Barcode' => 'EAN',
            'Qty (CU)' => 'Ilość',
            'Brand' => 'Brend',
            'Color Code' => 'Color Code',
            'Color Description' => 'Color describtion',
            'Gender' => 'Gender',
            'Type' => 'item SubCategory',
            'Delivery window' => 'Season/Collection',
            'Wholesale Price' => 'Wholesale Price EUR',
            'Recommended Retail Price' => 'Recommended Retail Price EUR',
        ]
        : [
            'Brand' => 'Brend  ',
            'Style Name' => 'Product name (EN)',
            'Order No.' => 'Order No.',
            'Style No' => 'Supplier Product ID',
            'Barcode' => 'EAN / Barcode',
            'Qty (CU)' => 'Qty (Quantity per Unit)',
            'Type' => 'item_category',
            'Season' => 'Season/Collection',
            'Gender' => 'Gender',
            'Color Code' => 'Color Code',
            'Color Description' => 'Color Description EN',
            'Size' => 'Shoe size',
            'Wholesale Price' => 'Wholesale Price EUR',
            'Recommended Retail Price' => 'Recommended Retail Price EUR',
        ],
];
