<?php

/**
 * Экспорт Liewood в формат retail CSV (как ручной final + 10 фото в конце).
 */
return [
    'enabled' => env('LIEWOOD_RETAIL_EXPORT', true),

    /** Курс EUR → PLN для расчёта PLN-колонок */
    'eur_to_pln_rate' => (float) env('LIEWOOD_EUR_TO_PLN_RATE', 4.25),

    'constants' => [
        'tagi_produktu' => 'LIEWOOD',
        'producent' => 'LIEWOOD',
    ],

    /**
     * Уникальные ключи строки (порядок экспорта).
     * Дублирующиеся подписи в CSV задаются в csv_header_row.
     *
     * @var array<int, string>
     */
    'template_column_keys' => [
        'Product name (EN)',
        'Nazwa produktu (EN)',
        'Nazwa kategorii dop pole',
        'Tagi produktu',
        'Ilość',
        'SKU',
        'EAN',
        'Brend',
        'PRODUCENT',
        'Order No.',
        'Sku Brand',
        'Color describtion',
        'item SubCategory',
        'Gender',
        'Season/Collection',
        'Color Code',
        'Size Xs-Xl',
        'KG size',
        'Age size',
        'height size',
        'socks size',
        'hatz size',
        'Shoe size',
        'Wholesale Price EUR',
        'Recommended Retail Price EUR',
        'Recommended Retail Price EUR 2',
        'Wholesale Price PLN',
        'Wholesale Price PLN 2',
        'Style no',
        'Supplier product ID',
        'Recommend Retail Price PLN',
        'Recommend Retail Price PLN 2',
        'Photo 1',
        'Photo 2',
        'Photo 3',
        'Photo 4',
        'Photo 5',
        'Photo 6',
        'Photo 7',
        'Photo 8',
        'Photo 9',
        'Photo 10',
    ],

    /**
     * Первая строка CSV (может содержать одинаковые подписи, как в Excel).
     * Длина = template_column_keys.
     *
     * @var array<int, string>
     */
    'csv_header_row' => [
        'Product name (EN)',
        'Nazwa produktu (EN)',
        'Nazwa kategorii dop pole',
        'Tagi produktu',
        'Ilość',
        'SKU',
        'EAN',
        'Brend',
        'PRODUCENT',
        'Order No.',
        'Sku Brand',
        'Color describtion',
        'item SubCategory',
        'Gender',
        'Season/Collection',
        'Color Code',
        'Size Xs-Xl',
        'KG size',
        'Age size',
        'height size',
        'socks size',
        'hatz size',
        'Shoe size',
        'Wholesale Price EUR',
        'Recommended Retail Price EUR',
        'Recommended Retail Price EUR',
        'Wholesale Price PLN',
        'Wholesale Price PLN',
        'Style no',
        'Supplier product ID',
        'Recommend Retail Price PLN',
        'Recommend Retail Price PLN',
        'Photo 1',
        'Photo 2',
        'Photo 3',
        'Photo 4',
        'Photo 5',
        'Photo 6',
        'Photo 7',
        'Photo 8',
        'Photo 9',
        'Photo 10',
    ],

    /**
     * Колонки под URL фото (GCS/Drive) — в конце файла.
     *
     * @var array<int, string>
     */
    'photo_slots' => [
        'Photo 1',
        'Photo 2',
        'Photo 3',
        'Photo 4',
        'Photo 5',
        'Photo 6',
        'Photo 7',
        'Photo 8',
        'Photo 9',
        'Photo 10',
    ],

    /** Type (Liewood) → считаем обувью: числовой размер в Shoe size */
    'footwear_types' => [
        'SANDALS',
        'SLIPPERS',
        'BOOTS',
        'SHOES',
        'FOOTWEAR',
    ],

    /** Type → шапки: числовой размер 45–62 в hatz size */
    'hat_types' => [
        'HATS/CAP',
        'HATS',
        'CAP',
    ],

    /**
     * Маршруты вывода размера в retail CSV (порядок как в шаблоне output-26).
     * Поле Size из файла поставщика попадает ровно в одну из этих колонок.
     *
     * @var array<int, string>
     */
    'size_route_columns' => [
        'Size Xs-Xl',
        'KG size',
        'Age size',
        'height size',
        'socks size',
        'hatz size',
        'Shoe size',
    ],

    'validation' => [
        'sku' => 'SKU',
        'name' => 'Product name (EN)',
        'price' => 'Wholesale Price EUR',
    ],
];
