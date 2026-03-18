<?php

namespace App\BaseLinkerTest\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class BaseLinkerService
{
    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl = 'https://api.baselinker.com/connector.php',
    ) {
        if (trim($this->token) === '') {
            throw new RuntimeException('Missing BaseLinker token. Set BASELINKER_TOKEN in .env');
        }
    }

    public static function fromConfig(): self
    {
        /** @var string $token */
        $token = (string) config('services.baselinker.token', '');
        return new self($token);
    }

    public function getInventories(): array
    {
        return Cache::remember('bl:inventories', now()->addMinutes(10), function () {
            return $this->call('getInventories', []);
        });
    }

    public function getCategories(int $inventoryId): array
    {
        return Cache::remember("bl:{$inventoryId}:categories", now()->addMinutes(10), function () use ($inventoryId) {
            return $this->call('getInventoryCategories', [
                'inventory_id' => $inventoryId,
            ]);
        });
    }

    public function getProductsList(int $inventoryId, int $page = 1, bool $includeVariants = true): array
    {
        return Cache::remember("bl:{$inventoryId}:products:list:{$page}:" . ($includeVariants ? 'v1' : 'v0'), now()->addMinutes(10), function () use ($inventoryId, $page, $includeVariants) {
            return $this->call('getInventoryProductsList', [
                'inventory_id' => $inventoryId,
                'page' => $page,
                'include_variants' => $includeVariants,
                'filter_sort' => 'id ASC',
            ]);
        });
    }

    /**
     * @param array<int,int|string> $ids
     */
    public function getProductsData(int $inventoryId, array $ids): array
    {
        $ids = array_values(array_filter(array_map(fn ($v) => is_numeric($v) ? (int)$v : null, $ids), fn ($v) => is_int($v) && $v > 0));
        $key = 'bl:' . $inventoryId . ':products:data:' . md5(json_encode($ids));

        return Cache::remember($key, now()->addMinutes(10), function () use ($inventoryId, $ids) {
            return $this->call('getInventoryProductsData', [
                'inventory_id' => $inventoryId,
                'products' => $ids,
            ]);
        });
    }

    private function http(): PendingRequest
    {
        return Http::asForm()
            ->timeout(60)
            ->connectTimeout(15)
            ->withHeaders([
                'X-BLToken' => $this->token,
            ]);
    }

    private function call(string $method, array $parameters): array
    {
        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-9a7511.log'),
            json_encode([
                'sessionId' => '9a7511',
                'runId' => 'bl-pre',
                'hypothesisId' => 'BL-H1',
                'location' => 'app/BaseLinkerTest/Services/BaseLinkerService.php:call',
                'message' => 'Calling BaseLinker API',
                'data' => [
                    'method' => $method,
                    'has_inventory_id' => array_key_exists('inventory_id', $parameters),
                    'inventory_id' => isset($parameters['inventory_id']) && is_numeric($parameters['inventory_id']) ? (int)$parameters['inventory_id'] : null,
                    'page' => isset($parameters['page']) && is_numeric($parameters['page']) ? (int)$parameters['page'] : null,
                    'products_count' => isset($parameters['products']) && is_array($parameters['products']) ? count($parameters['products']) : null,
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion

        $resp = $this->http()->post($this->baseUrl, [
            'method' => $method,
            'parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
        ]);

        $resp->throw();

        $data = $resp->json();
        if (!is_array($data)) {
            throw new RuntimeException("BaseLinker response is not JSON array for method {$method}");
        }
        if (($data['status'] ?? null) === 'ERROR') {
            $msg = (string)($data['error_message'] ?? $data['error'] ?? 'Unknown error');
            $code = (string)($data['error_code'] ?? '');

            // #region agent log
            @file_put_contents(
                base_path('.cursor/debug-9a7511.log'),
                json_encode([
                    'sessionId' => '9a7511',
                    'runId' => 'bl-pre',
                    'hypothesisId' => 'BL-H2',
                    'location' => 'app/BaseLinkerTest/Services/BaseLinkerService.php:call',
                    'message' => 'BaseLinker API returned ERROR',
                    'data' => [
                        'method' => $method,
                        'error_code' => $code,
                        'error_message' => $msg,
                    ],
                    'timestamp' => (int) (microtime(true) * 1000),
                ]) . PHP_EOL,
                FILE_APPEND
            );
            // #endregion

            throw new RuntimeException("BaseLinker ERROR {$code}: {$msg}");
        }

        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-9a7511.log'),
            json_encode([
                'sessionId' => '9a7511',
                'runId' => 'bl-pre',
                'hypothesisId' => 'BL-H3',
                'location' => 'app/BaseLinkerTest/Services/BaseLinkerService.php:call',
                'message' => 'BaseLinker API SUCCESS summary',
                'data' => [
                    'method' => $method,
                    'top_level_keys' => array_slice(array_keys($data), 0, 20),
                    'counts' => [
                        'inventories' => isset($data['inventories']) && is_array($data['inventories']) ? count($data['inventories']) : null,
                        'categories' => isset($data['categories']) && is_array($data['categories']) ? count($data['categories']) : null,
                        'products_map' => isset($data['products']) && is_array($data['products']) ? count($data['products']) : null,
                    ],
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion

        return $data;
    }
}

