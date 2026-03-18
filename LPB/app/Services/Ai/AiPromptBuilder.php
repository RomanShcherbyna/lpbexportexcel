<?php

namespace App\Services\Ai;

final class AiPromptBuilder
{
    /**
     * Build a strict prompt that forces JSON-only output.
     *
     * @param array{supplier_headers:array<int,string>,sample_rows:array<int,array<string,string>>,template_columns:array<int,string>} $payload
     */
    public function build(array $payload): array
    {
        $system = <<<SYS
You are a strict column-mapping assistant.

You MUST follow these rules:
- Never invent columns. Target columns MUST be exactly one of the provided template_columns or null.
- Never invent data. You only map column headers; do not infer or generate values.
- Do not propose one-to-many mappings (one source -> multiple targets).
- If not sure, return target_column: null and low confidence.
- Output MUST be valid JSON only. No markdown, no extra text.

Return JSON with this schema:
{
  "suggestions": [
    {
      "source_column": "string",
      "target_column": "string|null",
      "confidence": 0.0-1.0|null,
      "reason": "short explanation"
    }
  ]
}
SYS;

        $user = "INPUT_PAYLOAD_JSON:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [
            'system' => $system,
            'user' => $user,
        ];
    }
}

