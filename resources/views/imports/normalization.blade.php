<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Normalization suggestions</title>
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
        .notice { padding: 10px 12px; border: 1px solid #ddd; background: #fafafa; margin: 12px 0; }
        .notice.bad { border-color: #ffb3b3; background: #ffe8e8; }
        .btn { margin-top: 14px; padding: 10px 14px; font-weight: 700; }
        code { font-size: 12px; }
    </style>
</head>
<body>
<div class="box">
    <h2>AI normalization suggestions</h2>
    <p class="muted">Supplier: <code>{{ $input['supplier_name'] }}</code> · File: <code>{{ $input['original_filename'] }}</code></p>

    @if (!empty($saved_rules_applied))
        <div class="notice">
            <strong>Rule applied from saved normalization rules.</strong>
            <div class="muted">Existing supplier rules were automatically applied for preview/export.</div>
        </div>
    @endif

    @if (!empty($ai_unavailable))
        <div class="notice bad">
            <strong>AI normalization unavailable.</strong>
            <div class="muted">{{ $ai_unavailable }}</div>
            <div class="muted">You may continue without normalization.</div>
        </div>
    @endif

    <h3>Suggested rules</h3>
    <form method="post" action="{{ route('imports.products.finalize') }}">
        @csrf

        <input type="hidden" name="supplier" value="{{ $input['supplier_code'] }}">
        <input type="hidden" name="stored_path" value="{{ $input['stored_path'] }}">
        <input type="hidden" name="original_filename" value="{{ $input['original_filename'] }}">
        <input type="hidden" name="export_xlsx" value="{{ $input['export_xlsx'] ? 1 : 0 }}">
        <input type="hidden" name="export_csv" value="{{ $input['export_csv'] ? 1 : 0 }}">
        @foreach ($input['mapping'] as $k => $v)
            <input type="hidden" name="mapping[{{ $k }}]" value="{{ $v }}">
        @endforeach

        <table>
            <thead>
            <tr>
                <th style="width: 16%;">Target column</th>
                <th style="width: 34%;">Source values (grouped)</th>
                <th style="width: 18%;">Canonical value (editable)</th>
                <th style="width: 10%;">Confidence</th>
                <th style="width: 16%;">Apply</th>
                <th style="width: 22%;">Reason</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rules as $idx => $r)
                @php
                    $conf = $r['confidence'];
                    $badge = null;
                    if (is_numeric($conf)) {
                        $c = (float)$conf;
                        if ($c >= 0.85) $badge = 'high';
                        elseif ($c >= 0.55) $badge = 'mid';
                        else $badge = 'low';
                    }
                @endphp
                <tr>
                    <td>{{ $r['target_column'] }}</td>
                    <td><code>{{ implode(', ', $r['source_values']) }}</code></td>
                    <td>
                        <input type="text" name="rules[{{ $idx }}][canonical_value]" value="{{ old("rules.$idx.canonical_value", $r['canonical_value']) }}" style="width: 100%; padding: 6px;">
                    </td>
                    <td>
                        @if ($badge)
                            <span class="pill {{ $badge }}">{{ number_format((float)$conf, 2) }}</span>
                        @else
                            <span class="pill">n/a</span>
                        @endif
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" name="rules[{{ $idx }}][apply]" value="1" @checked(old("rules.$idx.apply", true))>
                    </td>
                    <td class="muted">{{ $r['reason'] }}</td>
                </tr>

                <input type="hidden" name="rules[{{ $idx }}][target_column]" value="{{ $r['target_column'] }}">
                @foreach ($r['source_values'] as $sv)
                    <input type="hidden" name="rules[{{ $idx }}][source_values][]" value="{{ $sv }}">
                @endforeach
            @empty
                <tr><td colspan="6" class="muted">No suggestions.</td></tr>
            @endforelse
            </tbody>
        </table>

        <button class="btn" type="submit">Continue to export</button>
        <a class="muted" href="/imports/products" style="margin-left: 12px;">Back</a>
    </form>
</div>
</body>
</html>

