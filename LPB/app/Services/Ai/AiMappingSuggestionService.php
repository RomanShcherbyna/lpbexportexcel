<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

final class AiMappingSuggestionService
{
    public function __construct(
        private readonly AiPromptBuilder $promptBuilder,
    ) {
    }

    /**
     * @param array<int,string> $supplierHeaders
     * @param array<int,array<string,string>> $sampleRows
     * @param array<int,string> $templateColumns
     * @return array{ok:bool,content:?string,error:?string}
     */
    public function suggest(array $supplierHeaders, array $sampleRows, array $templateColumns): array
    {
        $apiKey = (string) env('OPENAI_API_KEY', '');
        $model = (string) env('OPENAI_MODEL', 'gpt-4o-mini');

        if ($apiKey === '') {
            return ['ok' => false, 'content' => null, 'error' => 'OPENAI_API_KEY is not set.'];
        }

        $payload = [
            'supplier_headers' => array_values($supplierHeaders),
            'sample_rows' => array_values($sampleRows),
            'template_columns' => array_values($templateColumns),
        ];

        $prompt = $this->promptBuilder->build($payload);

        try {
            $resp = Http::withToken($apiKey)
                ->timeout(25)
                ->acceptJson()
                ->asJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $prompt['system']],
                        ['role' => 'user', 'content' => $prompt['user']],
                    ],
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'content' => null, 'error' => 'OpenAI request failed: ' . $e->getMessage()];
        }

        if (!$resp->successful()) {
            return ['ok' => false, 'content' => null, 'error' => 'OpenAI HTTP error: ' . $resp->status()];
        }

        $json = $resp->json();
        $content = $json['choices'][0]['message']['content'] ?? null;
        $content = is_string($content) ? $content : null;

        if ($content === null || trim($content) === '') {
            return ['ok' => false, 'content' => null, 'error' => 'OpenAI returned empty content.'];
        }

        return ['ok' => true, 'content' => $content, 'error' => null];
    }
}

