<?php

namespace App\Services\Import;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\Permission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Подставляет URL картинок Google Drive в строки экспорта Liewood по имени файла:
 * ...LW#####_####_..._N.png → ключ Style No + Color Code.
 */
final class LiewoodGoogleDriveImageFiller
{
    private const STYLE_COLOR_PATTERN = '/(LW\d{5})_(\d{4})/i';

    private const SEQ_PATTERN = '/_(\d+)\.(png|jpe?g|webp|gif)$/i';

    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  array<int, string>|null  $imageSlotsOverride  Имена колонок под URL (например Photo 1…10)
     * @param  bool|null  $gcsUseDownloadProxy  null = взять из config('liewood_drive.gcs_use_download_proxy')
     * @param  string|null  $gcsPrefixOverride  null = взять из config('liewood_drive.gcs_prefix')
     * @return array<int, array<string, string>>
     */
    public function fillExportRows(
        array $rows,
        ?array $imageSlotsOverride = null,
        ?bool $gcsUseDownloadProxy = null,
        ?string $gcsPrefixOverride = null,
    ): array
    {
        $enabled = (bool) config('liewood_drive.enabled', false);

        if (! $enabled) {
            return $rows;
        }

        $maxExec = (int) config('liewood_drive.max_execution_seconds', 300);
        if ($maxExec > 0) {
            @set_time_limit($maxExec);
        } else {
            @set_time_limit(0);
        }

        $source = strtolower(trim((string) config('liewood_drive.source', 'gcs')));
        $useGcsDownloadProxy = $gcsUseDownloadProxy ?? (bool) config('liewood_drive.gcs_use_download_proxy', false);

        try {
            if ($source === 'gcs') {
                $bucket = trim((string) config('liewood_drive.gcs_bucket', ''));
                $prefixRaw = $gcsPrefixOverride ?? (string) config('liewood_drive.gcs_prefix', '');
                $prefix = ltrim(trim($prefixRaw), '/');
                if ($prefix !== '' && ! str_ends_with($prefix, '/')) {
                    $prefix .= '/';
                }
                $publicBaseUrl = trim((string) config('liewood_drive.gcs_public_base_url', 'https://storage.googleapis.com'));

                if ($bucket === '') {
                    Log::info('Liewood images (GCS): skipped (missing gcs_bucket).');

                    return $rows;
                }

                $map = $this->getStyleColorToUrlsMapFromGcs($bucket, $prefix, $publicBaseUrl, $useGcsDownloadProxy);
            } else {
                $creds = (string) config('liewood_drive.credentials_path', '');
                $token = (string) config('liewood_drive.token_path', '');
                $folderId = trim((string) config('liewood_drive.folder_id', ''));

                if ($folderId === '' || ! is_file($creds) || ! is_file($token)) {
                    Log::info('Liewood Drive images: skipped (missing credentials, token, or folder_id).');

                    return $rows;
                }

                $map = $this->getStyleColorToUrlsMapFromDrive($folderId);
            }
        } catch (Throwable $e) {
            Log::warning('Liewood images: failed to build map.', [
                'message' => $e->getMessage(),
            ]);

            return $rows;
        }

        if ($map === []) {
            return $rows;
        }

        $slots = $imageSlotsOverride ?? (array) config('liewood_drive.image_slots', []);

        foreach ($rows as $i => $row) {
            $style = $this->resolveStyleNo($row);
            $color = $this->normalizeColorCode($row);
            if ($style === '' || $color === '') {
                continue;
            }

            $key = $style.'|'.$color;
            if (! isset($map[$key])) {
                continue;
            }

            $urls = $map[$key];
            $n = min(count($urls), count($slots));
            for ($j = 0; $j < $n; $j++) {
                $col = $slots[$j];
                if (array_key_exists($col, $row)) {
                    $row[$col] = $urls[$j];
                }
            }
            $rows[$i] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, list<string>> key "LW12345|2696" => urls ordered
     */
    private function getStyleColorToUrlsMapFromDrive(string $folderId): array
    {
        $ttl = (int) config('liewood_drive.cache_ttl_seconds', 1800);
        $cacheKey = 'liewood_drive_urls_v2_'.$folderId;

        return Cache::remember($cacheKey, max(60, $ttl), function () use ($folderId) {
            $client = $this->createAuthorizedClient();
            $drive = new Drive($client);

            $files = $this->listAllFilesRecursive($drive, $folderId);
            $grouped = [];

            foreach ($files as $file) {
                $name = $file['name'] ?? '';
                $id = $file['id'] ?? '';
                if ($id === '' || $name === '') {
                    continue;
                }

                $parsed = $this->parseFilename($name);
                if ($parsed === null) {
                    continue;
                }

                [$style, $color, $seq] = $parsed;

                if ((bool) config('liewood_drive.apply_public_permission_per_file', false)) {
                    try {
                        $perm = new Permission([
                            'type' => 'anyone',
                            'role' => 'reader',
                        ]);
                        $drive->permissions->create($id, $perm, ['fields' => 'id']);
                    } catch (Throwable) {
                        // уже открыт или нет прав — продолжаем
                    }
                }

                // BaseLinker часто не корректно обрабатывает редиректы из export=view.
                // export=download обычно отдает "прямой" контент картинки.
                $url = 'https://drive.google.com/uc?export=download&id='.rawurlencode($id);
                $key = $style.'|'.$color;
                if (! isset($grouped[$key])) {
                    $grouped[$key] = [];
                }
                $grouped[$key][] = ['seq' => $seq, 'url' => $url];
            }

            $out = [];
            foreach ($grouped as $key => $items) {
                usort($items, fn (array $a, array $b): int => [$a['seq'], $a['url']] <=> [$b['seq'], $b['url']]);
                $out[$key] = array_values(array_map(fn (array $x) => $x['url'], $items));
            }

            return $out;
        });
    }

    /**
     * @return array<string, list<string>> key "LW12345|2696" => urls ordered
     */
    private function getStyleColorToUrlsMapFromGcs(string $bucket, string $prefix, string $publicBaseUrl, bool $useDownloadProxy): array
    {
        $ttl = (int) config('liewood_drive.cache_ttl_seconds', 1800);
        $proxyFlag = $useDownloadProxy ? '1' : '0';
        $cacheKey = 'liewood_gcs_urls_v3_'.md5($bucket.'|'.$prefix.'|'.$publicBaseUrl.'|'.$proxyFlag);

        return Cache::remember($cacheKey, max(60, $ttl), function () use ($bucket, $prefix, $publicBaseUrl, $useDownloadProxy) {
            $objects = $this->listAllGcsObjects($bucket, $prefix);
            $grouped = [];

            foreach ($objects as $objectName) {
                $parsed = $this->parseFilename(basename($objectName));
                if ($parsed === null) {
                    continue;
                }

                [$style, $color, $seq] = $parsed;
                $url = $this->buildExportImageUrl($publicBaseUrl, $bucket, $objectName, $useDownloadProxy);
                $key = $style.'|'.$color;
                if (! isset($grouped[$key])) {
                    $grouped[$key] = [];
                }
                $grouped[$key][] = ['seq' => $seq, 'url' => $url];
            }

            $out = [];
            foreach ($grouped as $key => $items) {
                usort($items, fn (array $a, array $b): int => [$a['seq'], $a['url']] <=> [$b['seq'], $b['url']]);
                $out[$key] = array_values(array_map(fn (array $x) => $x['url'], $items));
            }

            return $out;
        });
    }

    private function createAuthorizedClient(): Client
    {
        $credsPath = (string) config('liewood_drive.credentials_path');
        $tokenPath = (string) config('liewood_drive.token_path');

        $client = new Client;
        $client->setAuthConfig($credsPath);
        $client->addScope(Drive::DRIVE);
        $client->setAccessType('offline');

        $accessToken = json_decode((string) file_get_contents($tokenPath), true);
        if (! is_array($accessToken)) {
            throw new \RuntimeException('Invalid token.json');
        }
        // Python google-auth-oauthlib сохраняет access token в ключе "token";
        // PHP Google\Client требует "access_token" (иначе InvalidArgumentException).
        if (isset($accessToken['token']) && ! isset($accessToken['access_token'])) {
            $accessToken['access_token'] = $accessToken['token'];
        }
        $client->setAccessToken($accessToken);

        if ($client->isAccessTokenExpired()) {
            $refresh = $client->getRefreshToken();
            if ($refresh) {
                $client->fetchAccessTokenWithRefreshToken($refresh);
                $new = $client->getAccessToken();
                if (is_array($new)) {
                    if (! isset($new['refresh_token']) && isset($accessToken['refresh_token'])) {
                        $new['refresh_token'] = $accessToken['refresh_token'];
                    }
                    file_put_contents($tokenPath, json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            }
        }

        return $client;
    }

    /**
     * @return list<string> Full object names in bucket
     */
    private function listAllGcsObjects(string $bucket, string $prefix): array
    {
        $out = [];
        $pageToken = null;
        $bucketEnc = rawurlencode($bucket);

        do {
            $query = [
                'prefix' => ltrim($prefix, '/'),
                'maxResults' => 1000,
            ];
            if ($pageToken !== null && $pageToken !== '') {
                $query['pageToken'] = $pageToken;
            }

            $url = 'https://storage.googleapis.com/storage/v1/b/'.$bucketEnc.'/o?'.http_build_query($query);
            $json = @file_get_contents($url);
            if (! is_string($json) || $json === '') {
                break;
            }

            $data = json_decode($json, true);
            if (! is_array($data)) {
                break;
            }

            $items = $data['items'] ?? [];
            if (is_array($items)) {
                foreach ($items as $item) {
                    $name = (string) ($item['name'] ?? '');
                    if ($name !== '') {
                        $out[] = $name;
                    }
                }
            }

            $pageToken = (string) ($data['nextPageToken'] ?? '');
        } while ($pageToken !== '');

        return $out;
    }

    private function buildGcsPublicUrl(string $publicBaseUrl, string $bucket, string $objectName): string
    {
        $base = rtrim($publicBaseUrl, '/');
        $segments = array_map('rawurlencode', explode('/', ltrim($objectName, '/')));
        $encodedPath = implode('/', $segments);

        return $base.'/'.rawurlencode($bucket).'/'.$encodedPath;
    }

    /**
     * Прямая ссылка на GCS:
     * - preview: обычный public URL
     * - download: public URL с параметром response-content-disposition=attachment
     */
    private function buildExportImageUrl(string $publicBaseUrl, string $bucket, string $objectName, bool $useDownloadProxy): string
    {
        if (! $useDownloadProxy) {
            return $this->buildGcsPublicUrl($publicBaseUrl, $bucket, $objectName);
        }

        // BaseLinker скачивает по URL. Добавляем параметр attachment для браузеров,
        // но поведение зависит от реализации GCS для public URL.
        $url = $this->buildGcsPublicUrl($publicBaseUrl, $bucket, $objectName);

        $filename = basename($objectName);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = 'download.bin';
        }

        $disposition = 'attachment; filename="'.$filename.'"';
        $query = http_build_query([
            'response-content-disposition' => $disposition,
        ]);

        return $url.'?'.$query;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function listAllFilesRecursive(Drive $drive, string $rootFolderId): array
    {
        $out = [];
        $queue = [$rootFolderId];

        while ($queue !== []) {
            $fid = array_shift($queue);
            $pageToken = null;
            do {
                $params = [
                    'q' => "'".$fid."' in parents and trashed = false",
                    'spaces' => 'drive',
                    'fields' => 'nextPageToken, files(id, name, mimeType)',
                    'pageSize' => 1000,
                ];
                if ($pageToken !== null) {
                    $params['pageToken'] = $pageToken;
                }
                $resp = $drive->files->listFiles($params);
                foreach ($resp->getFiles() as $f) {
                    $mime = (string) $f->getMimeType();
                    if ($mime === 'application/vnd.google-apps.folder') {
                        $queue[] = (string) $f->getId();
                    } else {
                        $out[] = [
                            'id' => (string) $f->getId(),
                            'name' => (string) $f->getName(),
                        ];
                    }
                }
                $pageToken = $resp->getNextPageToken();
            } while ($pageToken !== null);
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: string, 2: int}|null style, color, sequence
     */
    private function parseFilename(string $name): ?array
    {
        if (! preg_match(self::STYLE_COLOR_PATTERN, $name, $m)) {
            return null;
        }
        $style = strtoupper($m[1]);
        $color = $m[2];
        $seq = 0;
        if (preg_match(self::SEQ_PATTERN, $name, $sm)) {
            $seq = (int) $sm[1];
        }

        return [$style, $color, $seq];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveStyleNo(array $row): string
    {
        foreach (['Supplier Product ID', 'Style No', 'Style no'] as $k) {
            $v = strtoupper(trim((string) ($row[$k] ?? '')));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /**
     * @param  array<string, string>  $row
     */
    private function normalizeColorCode(array $row): string
    {
        $raw = trim((string) ($row['Color Code'] ?? ''));
        if ($raw === '') {
            return '';
        }
        if (ctype_digit($raw)) {
            return str_pad($raw, 4, '0', STR_PAD_LEFT);
        }

        return $raw;
    }
}
