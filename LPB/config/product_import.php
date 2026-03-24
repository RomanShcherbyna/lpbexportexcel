<?php

return [
    /**
     * Master BaseLinker template (XLSX).
     * Source of truth for columns + order.
     */
    'template_path' => storage_path('app/templates/baselinker_master.xlsx'),

    /**
     * Storage base directory for conversion results.
     */
    'jobs_dir' => storage_path('app/imports'),

    /**
     * Confirmed mapping overrides saved per supplier.
     */
    'mapping_overrides_dir' => storage_path('app/import_mappings'),

    /**
     * Confirmed normalization rules per supplier.
     */
    'normalization_rules_dir' => storage_path('app/normalization_rules'),

    /**
     * Supplier profiles (JSON per supplier).
     */
    'supplier_profiles_dir' => storage_path('app/supplier_profiles'),

    /**
     * AI Normalizer allowlist (safe columns only).
     * AI must never propose rules for forbidden columns (SKU/EAN/prices/qty/brand/categories).
     */
    'normalization_safe_columns' => [
        'Gender',
        'Season',
        'Color Description EN',
        'Color Description PL',
        'Age Size',
    ],

    /**
     * Export filenames (within each job dir).
     */
    'export' => [
        'xlsx_name' => 'output.xlsx',
        'csv_name' => 'output.csv',
        'preview_json' => 'preview.json',
        'errors_json' => 'errors.json',
        'instruction_csv_name' => 'instruction.csv',
        'instruction_passport_json' => 'instruction_passport.json',
    ],

    /**
     * Validation rules bound to template column headers.
     * Keep strict: only explicit column names, no guessing.
     */
    'validation' => [
        'required_columns' => [
            'sku' => 'Sku',
            'name' => 'Product name (EN)',
            'price' => 'Wholesale Price',
        ],
        'empty_row_threshold' => 0, // 0 => row is "empty" if all output columns empty
    ],
];

