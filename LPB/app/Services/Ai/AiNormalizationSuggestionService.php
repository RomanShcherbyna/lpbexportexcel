<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

final class AiNormalizationSuggestionService
{
    public function __construct(
        private readonly AiNormalizationPromptBuilder $promptBuilder,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $allowedTargetColumns
     * @return array{ok:bool,content:?string,error:?string}
     */
    public function suggest(array $payload, array $allowedTargetColumns): array
    {
        $apiKey = (string) env('OPENAI_API_KEY', '');
        $model = (string) env('OPENAI_MODEL', 'gpt-4o-mini');

        if ($apiKey === '') {
            return ['ok' => false, 'content' => null, 'error' => 'OPENAI_API_KEY is not set.'];
        }

        $prompt = $this->promptBuilder->build($payload, $allowedTargetColumns);

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

