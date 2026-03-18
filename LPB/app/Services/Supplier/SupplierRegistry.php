<?php

namespace App\Services\Supplier;

use App\DataTransferObjects\SupplierProfileData;

final class SupplierRegistry
{
    /**
     * @return array<int, array{code:string,name:string,config_path:string}>
     */
    public function listSuppliers(): array
    {
        $items = [];

        foreach (glob(config_path('suppliers/*.php')) ?: [] as $path) {
            $cfg = require $path;
            if (!is_array($cfg)) {
                continue;
            }

            $code = (string)($cfg['supplier_code'] ?? '');
            $name = (string)($cfg['supplier_name'] ?? $code);
            if ($code === '') {
                continue;
            }

            $items[] = [
                'code' => $code,
                'name' => $name,
                'config_path' => $path,
            ];
        }

        usort($items, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $items;
    }

    public function exists(string $supplierCode): bool
    {
        return file_exists(config_path('suppliers/' . $supplierCode . '.php'));
    }

    /**
     * Convenience helper for profile-aware listing.
     *
     * @return array<int,array{code:string,name:string,has_profile:bool,profile_version:int|null}>
     */
    public function listWithProfiles(SupplierProfileRepository $profiles): array
    {
        $base = $this->listSuppliers();
        $out = [];
        foreach ($base as $s) {
            $profile = $profiles->load($s['code']);
            $has = $profile instanceof SupplierProfileData && $profile->mapping !== [];
            $out[] = [
                'code' => $s['code'],
                'name' => $s['name'],
                'has_profile' => $has,
                'profile_version' => $has ? $profile->version : null,
            ];
        }
        return $out;
    }
}

