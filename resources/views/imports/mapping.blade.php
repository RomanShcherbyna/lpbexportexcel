<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Review mapping</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 24px; }
        .box { max-width: 1200px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; font-size: 13px; vertical-align: top; }
        th { background: #f5f5f5; text-align: left; }
        .muted { color: #666; font-size: 13px; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; background: #eee; }
        .pill.high { background: #e7ffe7; }
        .pill.mid { background: #fff3d6; }
        .pill.low { background: #ffe8e8; }
        select { width: 100%; padding: 6px; }
        .btn { margin-top: 14px; padding: 10px 14px; font-weight: 700; }
        .notice { padding: 10px 12px; border: 1px solid #ddd; background: #fafafa; margin: 12px 0; }
        .notice.bad { border-color: #ffb3b3; background: #ffe8e8; }
        .reason { font-size: 12px; color: #444; margin-top: 4px; }
        code { font-size: 12px; }
    </style>
</head>
<body>
<div class="box">
    <h2>Review mapping</h2>
    <p class="muted">File: <code>{{ $input['original_filename'] }}</code> · Supplier: <code>{{ $input['supplier_name'] }}</code></p>

    @if (!empty($ai_unavailable))
        <div class="notice bad">
            <strong>AI mapping unavailable.</strong>
            <div class="muted">{{ $ai_unavailable }}</div>
            <div class="muted">You can continue with saved/manual mapping.</div>
        </div>
    @else
        <div class="notice">
            <strong>AI suggestions are only suggestions.</strong>
            <div class="muted">Nothing will be applied until you confirm mapping below.</div>
        </div>
    @endif

    @php
        $aiBySource = [];
        foreach (($ai_suggestions ?? []) as $s) {
            $aiBySource[$s['source_column']] = $s;
        }
    @endphp

    <form method="post" action="{{ route('imports.products.normalize') }}">
        @csrf

        <input type="hidden" name="supplier" value="{{ $input['supplier_code'] }}">
        <input type="hidden" name="stored_path" value="{{ $input['stored_path'] }}">
        <input type="hidden" name="original_filename" value="{{ $input['original_filename'] }}">
        <input type="hidden" name="export_xlsx" value="{{ $input['export_xlsx'] ? 1 : 0 }}">
        <input type="hidden" name="export_csv" value="{{ $input['export_csv'] ? 1 : 0 }}">

        <h3>Suggested mapping</h3>
        <table>
            <thead>
            <tr>
                <th style="width: 18%;">Source column</th>
                <th style="width: 22%;">Saved mapping</th>
                <th style="width: 22%;">AI suggestion</th>
                <th style="width: 8%;">Confidence</th>
                <th style="width: 30%;">Final selected</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($supplier_headers as $src)
                @php
                    $saved = $saved_mapping[$src] ?? null;
                    $ai = $aiBySource[$src]['target_column'] ?? null;
                    $conf = $aiBySource[$src]['confidence'] ?? null;
                    $reason = $aiBySource[$src]['reason'] ?? null;

                    $badge = null;
                    if (is_numeric($conf)) {
                        $c = (float)$conf;
                        if ($c >= 0.85) $badge = 'high';
                        elseif ($c >= 0.55) $badge = 'mid';
                        else $badge = 'low';
                    }

                    $defaultFinal = old("mapping.$src");
                    if ($defaultFinal === null) {
                        $defaultFinal = $saved ?? $ai ?? '__unmapped__';
                    }
                @endphp
                <tr>
                    <td><code>{{ $src }}</code></td>
                    <td>{{ $saved ?? '' }}</td>
                    <td>
                        {{ $ai ?? '' }}
                        @if (!empty($reason))
                            <div class="reason">{{ $reason }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($badge)
                            <span class="pill {{ $badge }}">{{ number_format((float)$conf, 2) }}</span>
                        @else
                            <span class="pill">n/a</span>
                        @endif
                    </td>
                    <td>
                        <select name="mapping[{{ $src }}]">
                            <option value="__unmapped__" @selected($defaultFinal === '__unmapped__')>Leave unmapped</option>
                            @foreach ($template_columns as $col)
                                <option value="{{ $col }}" @selected($defaultFinal === $col)>{{ $col }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <button class="btn" type="submit">Confirm mapping & continue</button>
        <a class="muted" href="/imports/products" style="margin-left: 12px;">Back</a>
    </form>

    @if (!empty($ai_raw_example))
        <details style="margin-top: 14px;">
            <summary class="muted">AI raw JSON (debug)</summary>
            <pre style="white-space: pre-wrap; border: 1px solid #ddd; padding: 10px; background: #fafafa;">{{ $ai_raw_example }}</pre>
        </details>
    @endif
</div>
</body>
</html>

