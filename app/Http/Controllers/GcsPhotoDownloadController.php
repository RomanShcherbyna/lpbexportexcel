<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Отдаёт объект из публичного GCS с Content-Disposition: attachment (скачивание, не inline preview).
 * Используется только если LIEWOOD_GCS_USE_DOWNLOAD_PROXY=true.
 */
final class GcsPhotoDownloadController extends Controller
{
    public function download(string $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $payload = Cache::get('gcs_photo_dl:'.$id);
        if (! is_array($payload)) {
            abort(404);
        }

        $bucket = trim((string) ($payload['bucket'] ?? ''));
        $object = (string) ($payload['object'] ?? '');
        if ($bucket === '' || $object === '') {
            abort(404);
        }

        $expectedBucket = trim((string) config('liewood_drive.gcs_bucket', ''));
        if ($expectedBucket !== '' && $bucket !== $expectedBucket) {
            abort(404);
        }

        $prefix = ltrim(trim((string) config('liewood_drive.gcs_prefix', '')), '/');
        $objNorm = ltrim($object, '/');
        if ($prefix !== '' && ! str_starts_with($objNorm, $prefix)) {
            abort(404);
        }

        $publicBaseUrl = rtrim((string) config('liewood_drive.gcs_public_base_url', 'https://storage.googleapis.com'), '/');
        $segments = array_map('rawurlencode', explode('/', $objNorm));
        $srcUrl = $publicBaseUrl.'/'.rawurlencode($bucket).'/'.implode('/', $segments);

        $filename = basename($object);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = 'download.bin';
        }

        try {
            $head = Http::timeout(30)->head($srcUrl);
            if (! $head->successful()) {
                abort(404);
            }
        } catch (\Throwable) {
            abort(502);
        }

        return response()->streamDownload(function () use ($srcUrl): void {
            $in = @fopen($srcUrl, 'rb');
            if ($in === false) {
                return;
            }
            fpassthru($in);
            fclose($in);
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
