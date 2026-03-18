<?php

namespace App\Services\Supplier;

use App\DataTransferObjects\SupplierProfileData;

final class SupplierProfileLoader
{
    public function __construct(
        private readonly SupplierProfileRepository $repository,
    ) {
    }

    public function load(string $supplierCode): SupplierProfileData
    {
        return $this->repository->load($supplierCode);
    }
}

