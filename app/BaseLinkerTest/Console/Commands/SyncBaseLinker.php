<?php

namespace App\BaseLinkerTest\Console\Commands;

use App\BaseLinkerTest\Models\BlCategory;
use App\BaseLinkerTest\Models\BlProduct;
use App\BaseLinkerTest\Services\BaseLinkerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class SyncBaseLinker extends Command
{
    protected $signature = 'baselinker:sync
        {--inventory= : Inventory ID (optional; if omitted sync all)}
        {--force : Ignore sync cooldown}
        {--details-batch=100 : Batch size for getInventoryProductsData}
        {--cooldown=60 : Minimum seconds between sync runs}';

    protected $description = 'Sync categories/products from BaseLinker inventories into local DB (test module).';

    public function handle(BaseLinkerService $bl): int
    {
        $lock = Cache::lock('bl:sync:lock', 300);
        if (!$lock->get()) {
            $this->warn('Sync already running.');
            return self::SUCCESS;
        }

        try {
            // #region agent log
            @file_put_contents(
                base_path('.cursor/debug-9a7511.log'),
                json_encode([
                    'sessionId' => '9a7511',
                    'runId' => 'bl-pre',
                    'hypothesisId' => 'BL-H4',
                    'location' => 'app/BaseLinkerTest/Console/Commands/SyncBaseLinker.php:handle',
                    'message' => 'Starting baselinker:sync',
                    'data' => [
                        'inventory_option' => $this->option('inventory'),
                        'force' => (bool)$this->option('force'),
                        'details_batch' => (int)$this->option('details-batch'),
                        'cooldown' => (int)$this->option('cooldown'),
                    ],
                    'timestamp' => (int) (microtime(true) * 1000),
                ]) . PHP_EOL,
                FILE_APPEND
            );
            // #endregion

            $cooldown = (int)$this->option('cooldown');
            if (!$this->option('force')) {
                $last = Cache::get('bl:sync:last_ts');
                if (is_int($last) && (time() - $last) < $cooldown) {
                    $this->info("Skipped (cooldown {$cooldown}s). Use --force to run anyway.");
                    return self::SUCCESS;
                }
            }

            $inventoryFilter = $this->option('inventory');
            $inventoryIdFilter = is_numeric($inventoryFilter) ? (int)$inventoryFilter : null;

            $inventories = $bl->getInventories();
            $items = $inventories['inventories'] ?? [];
            if (!is_array($items)) {
                $this->error('Unexpected inventories response.');
                return self::FAILURE;
            }

            $inventoryIds = [];
            foreach ($items as $inv) {
                $id = $inv['inventory_id'] ?? null;
                if (!is_numeric($id)) {
                    continue;
                }
                $id = (int)$id;
                if ($inventoryIdFilter !== null && $inventoryIdFilter !== $id) {
                    continue;
                }
                $inventoryIds[] = $id;
            }

            if ($inventoryIds === []) {
                $this->warn('No inventories found (or inventory filter did not match).');
                return self::SUCCESS;
            }

            foreach ($inventoryIds as $inventoryId) {
                $this->info("Sync inventory {$inventoryId}");
                $this->syncCategories($bl, $inventoryId);
                $this->syncProducts($bl, $inventoryId);
                $this->syncProductDetails($bl, $inventoryId);
            }

            Cache::put('bl:sync:last_ts', time(), 3600);
            $this->info('Done.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            // #region agent log
            @file_put_contents(
                base_path('.cursor/debug-9a7511.log'),
                json_encode([
                    'sessionId' => '9a7511',
                    'runId' => 'bl-pre',
                    'hypothesisId' => 'BL-H5',
                    'location' => 'app/BaseLinkerTest/Console/Commands/SyncBaseLinker.php:handle',
                    'message' => 'baselinker:sync failed',
                    'data' => [
                        'exception' => get_class($e),
                        'message_short' => mb_substr((string)$e->getMessage(), 0, 500),
                    ],
                    'timestamp' => (int) (microtime(true) * 1000),
                ]) . PHP_EOL,
                FILE_APPEND
            );
            // #endregion

            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function syncCategories(BaseLinkerService $bl, int $inventoryId): void
    {
        $resp = $bl->getCategories($inventoryId);
        $cats = $resp['categories'] ?? [];
        if (!is_array($cats)) {
            $this->warn('No categories in response.');
            return;
        }

        $rows = [];
        foreach ($cats as $c) {
            $id = $c['category_id'] ?? null;
            if (!is_numeric($id)) {
                continue;
            }
            $rows[] = [
                'id' => (int)$id,
                'inventory_id' => $inventoryId,
                'name' => (string)($c['name'] ?? ''),
                'parent_id' => isset($c['parent_id']) && is_numeric($c['parent_id']) ? (int)$c['parent_id'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            // Composite PK: (inventory_id, id)
            BlCategory::query()->upsert($rows, ['inventory_id', 'id'], ['name', 'parent_id', 'updated_at']);
        }

        $this->info('Categories: ' . count($rows));
    }

    private function syncProducts(BaseLinkerService $bl, int $inventoryId): void
    {
        $page = 1;
        $total = 0;

        while (true) {
            $resp = $bl->getProductsList($inventoryId, $page, true);
            $products = $resp['products'] ?? [];
            if (!is_array($products) || $products === []) {
                break;
            }

            $rows = [];
            foreach ($products as $pid => $p) {
                $id = is_numeric($pid) ? (int)$pid : (is_numeric($p['id'] ?? null) ? (int)$p['id'] : null);
                if ($id === null) {
                    continue;
                }

                $prices = is_array($p['prices'] ?? null) ? $p['prices'] : null;
                $stock = is_array($p['stock'] ?? null) ? $p['stock'] : null;

                $priceFlat = null;
                if (is_array($prices) && $prices !== []) {
                    $first = array_values($prices)[0] ?? null;
                    $priceFlat = is_numeric($first) ? (float)$first : null;
                }

                $stockFlat = null;
                if (is_array($stock) && $stock !== []) {
                    $sum = 0;
                    foreach ($stock as $v) {
                        if (is_numeric($v)) {
                            $sum += (int)$v;
                        }
                    }
                    $stockFlat = $sum;
                }

                $rows[] = [
                    'id' => $id,
                    'inventory_id' => $inventoryId,
                    'parent_id' => is_numeric($p['parent_id'] ?? null) ? (int)$p['parent_id'] : 0,
                    'ean' => isset($p['ean']) ? (string)$p['ean'] : null,
                    'sku' => isset($p['sku']) ? (string)$p['sku'] : null,
                    'name' => (string)($p['name'] ?? ''),
                    // SQLite bindings don't accept PHP arrays; store as JSON string.
                    'prices_json' => $prices !== null ? json_encode($prices, JSON_UNESCAPED_UNICODE) : null,
                    'stock_json' => $stock !== null ? json_encode($stock, JSON_UNESCAPED_UNICODE) : null,
                    'price' => $priceFlat,
                    'stock' => $stockFlat,
                    'category_id' => null,
                    'image' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                BlProduct::query()->upsert(
                    $rows,
                    ['inventory_id', 'id'],
                    ['parent_id', 'name', 'sku', 'ean', 'prices_json', 'stock_json', 'price', 'stock', 'updated_at']
                );
            }

            $total += count($rows);
            $this->info("Products page {$page}: " . count($rows));
            $page++;
        }

        $this->info("Products total: {$total}");
    }

    private function syncProductDetails(BaseLinkerService $bl, int $inventoryId): void
    {
        $batchSize = max(1, min(1000, (int)$this->option('details-batch')));

        $ids = BlProduct::query()
            ->where('inventory_id', $inventoryId)
            ->pluck('id')
            ->all();

        if (!is_array($ids) || $ids === []) {
            $this->info('No products to fetch details for.');
            return;
        }

        $chunks = array_chunk($ids, $batchSize);
        $this->info('Details batches: ' . count($chunks));

        foreach ($chunks as $i => $chunk) {
            $resp = $bl->getProductsData($inventoryId, $chunk);
            $products = $resp['products'] ?? null;
            if (!is_array($products)) {
                $this->warn("No products data in batch {$i}");
                continue;
            }

            foreach ($products as $pid => $p) {
                $id = is_numeric($pid) ? (int)$pid : (is_numeric($p['product_id'] ?? null) ? (int)$p['product_id'] : null);
                if ($id === null) {
                    continue;
                }

                $categoryId = isset($p['category_id']) && is_numeric($p['category_id']) ? (int)$p['category_id'] : null;
                $image = null;
                if (isset($p['images']) && is_array($p['images'])) {
                    $image = (string)(array_values($p['images'])[0] ?? '');
                    $image = trim($image) !== '' ? $image : null;
                } elseif (isset($p['image'])) {
                    $image = trim((string)$p['image']) !== '' ? (string)$p['image'] : null;
                }

                BlProduct::query()
                    ->where('inventory_id', $inventoryId)
                    ->where('id', $id)
                    ->update([
                        'category_id' => $categoryId,
                        'image' => $image,
                        'updated_at' => now(),
                    ]);
            }

            $this->info('Details batch ' . ($i + 1) . '/' . count($chunks));
        }
    }
}

