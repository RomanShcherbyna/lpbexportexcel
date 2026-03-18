<?php

namespace App\DataTransferObjects;

final class SupplierProfileData
{
    /**
     * @param array<string,string> $mapping
     * @param array<string,array<string,string>> $normalizationRules
     * @param array<int,string> $rowFilters
     * @param array<string,mixed> $defaultSettings
     */
    public function __construct(
        public readonly string $supplierCode,
        public readonly int $version,
        public readonly string $name,
        public readonly array $mapping,
        public readonly array $normalizationRules,
        public readonly array $rowFilters,
        public readonly array $defaultSettings,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'supplier_code' => $this->supplierCode,
            'version' => $this->version,
            'name' => $this->name,
            'mapping' => $this->mapping,
            'normalization_rules' => $this->normalizationRules,
            'row_filters' => $this->rowFilters,
            'default_settings' => $this->defaultSettings,
        ];
    }
}

