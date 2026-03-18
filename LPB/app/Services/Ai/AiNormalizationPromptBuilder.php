<?php

namespace App\Services\Ai;

final class AiNormalizationPromptBuilder
{
    /**
     * Strict prompt: suggest normalization rules ONLY for allowed target columns.
     *
     * @param array<string,mixed> $payload
     * @param array<int,string> $allowedTargetColumns
     */
    public function build(array $payload, array $allowedTargetColumns): array
    {
        $allowed = json_encode(array_values($allowedTargetColumns), JSON_UNESCAPED_UNICODE);

        $system = <<<SYS
You are a strict normalization-rule suggester.

Rules:
- You MUST NOT change data yourself. You only propose normalization rules.
- You MUST NOT target any column outside this allowlist: {$allowed}
- Do NOT propose rules for SKU/EAN/prices/categories/brands/descriptions unless explicitly allowed.
- Do NOT propose overly broad rules. Each rule must list specific source values to change.
- You MUST NOT invent values. Canonical_value MUST be one of the existing values in the dataset for that column (present in distinct_values).
- Canonical value MUST be non-empty.
- If unsure, do not suggest a rule.
- Output MUST be valid JSON only. No markdown, no extra text.

Return JSON with schema:
{
  "rules": [
    {
      "target_column": "string",
      "source_values": ["string", ...],
      "canonical_value": "string",
      "confidence": 0.0-1.0|null,
      "reason": "string"
    }
  ]
}
SYS;

        $user = "INPUT_PAYLOAD_JSON:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return ['system' => $system, 'user' => $user];
    }
}

