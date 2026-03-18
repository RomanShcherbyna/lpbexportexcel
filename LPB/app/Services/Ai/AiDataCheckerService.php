<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

final class AiDataCheckerService
{
    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,content:?string,error:?string}
     */
    public function check(array $payload): array
    {
        $apiKey = (string) env('OPENAI_API_KEY', '');
        $model = (string) env('OPENAI_MODEL', 'gpt-4o-mini');

        if ($apiKey === '') {
            return ['ok' => false, 'content' => null, 'error' => 'OPENAI_API_KEY is not set.'];
        }

        $system = <<<SYS
You are a strict data-checker for an e-commerce import.

Rules:
- You MUST NOT change or propose changing any data.
- You MUST NOT change mapping. Mapping is already confirmed.
- You MUST NOT invent new columns or values.
- You only warn about suspicious patterns and risks.
- If unsure, state it as suspicion with low confidence.
- Output MUST be valid JSON only. No markdown, no extra text.

Return JSON with schema:
{
  "warnings": [
    {
      "type": "string",
      "severity": "critical|warning|info",
      "confidence": 0.0-1.0|null,
      "message": "string",
      "reason": "string",
      "affected_rows": [int, ...]
    }
  ]
}
SYS;

        $user = "INPUT_PAYLOAD_JSON:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            $resp = Http::withToken($apiKey)
                ->timeout(30)
                ->acceptJson()
                ->asJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
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

