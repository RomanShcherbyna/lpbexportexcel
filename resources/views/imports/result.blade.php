<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Conversion result</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 24px; }
        .box { max-width: 1200px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; font-size: 13px; vertical-align: top; }
        th { background: #f5f5f5; text-align: left; }
        .muted { color: #666; font-size: 13px; }
        .ok { color: #0a7a0a; font-weight: 700; }
        .bad { color: #a30000; font-weight: 700; }
        .warn { color: #8a5b00; font-weight: 700; }
        .downloads a { margin-right: 12px; }
        .section { margin: 18px 0; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; background: #eee; }
        .pill.bad { background: #ffe8e8; }
        .pill.warn { background: #fff3d6; }
        .pill.ok { background: #e7ffe7; }
        .scroll { overflow: auto; max-height: 420px; border: 1px solid #ddd; }
    </style>
</head>
<body>
<div class="box">
    <h2>Conversion result</h2>
    <p class="muted">Job: <code>{{ $preview['job_id'] }}</code></p>

    <div class="section downloads">
        @if (!empty($preview['exports']['xlsx']))
            <a href="{{ route('imports.products.download.xlsx', ['job' => $preview['job_id']]) }}">Download XLSX</a>
        @endif
        @if (!empty($preview['exports']['csv']))
            <a href="{{ route('imports.products.download.csv', ['job' => $preview['job_id']]) }}">Download CSV</a>
        @endif
        <a href="/imports/products">Back</a>
    </div>

    @if (!empty($preview['normalization']['saved_rules_applied']))
        <div class="section">
            <p class="muted"><strong>Rule applied from saved normalization rules.</strong> Export/preview includes saved normalization.</p>
        </div>
    @endif

    <div class="section">
        <h3>Summary</h3>
        <table>
            <tr><th>File</th><td>{{ $preview['input']['original_filename'] }}</td></tr>
            <tr><th>Supplier</th><td>{{ $preview['input']['supplier_name'] }} ({{ $preview['input']['supplier_code'] }})</td></tr>
            <tr><th>Rows read</th><td>{{ $preview['counts']['rows_read'] }}</td></tr>
            <tr><th>Rows exported</th><td>{{ $preview['counts']['rows_exported'] }}</td></tr>
            <tr><th>Warnings</th><td><span class="pill warn">{{ $preview['counts']['warning_count'] }}</span></td></tr>
            <tr><th>Errors</th><td><span class="pill bad">{{ $preview['counts']['error_count'] }}</span></td></tr>
        </table>
    </div>

    <div class="section">
        <h3>Deterministic validation</h3>
        <p class="muted">
            Critical fields (identifier, name, price, duplicates). These never block export — they only show here as warnings,
            except for completely empty rows which are skipped.
        </p>
        @php $dc = $preview['deterministic']['counts'] ?? []; @endphp
        <table>
            <tr>
                <th>Duplicate SKU</th>
                <td><span class="pill warn">{{ $dc['duplicate_sku_count'] ?? 0 }}</span></td>
            </tr>
            <tr>
                <th>Missing identifier (SKU / Supplier Product ID)</th>
                <td><span class="pill bad">{{ $dc['missing_sku_count'] ?? 0 }}</span></td>
            </tr>
            <tr>
                <th>Missing name (Product name EN)</th>
                <td><span class="pill bad">{{ $dc['missing_name_count'] ?? 0 }}</span></td>
            </tr>
            <tr>
                <th>Missing price (Wholesale Price EUR)</th>
                <td><span class="pill bad">{{ $dc['missing_price_count'] ?? 0 }}</span></td>
            </tr>
            <tr>
                <th>Completely empty rows (not exported)</th>
                <td><span class="pill warn">{{ $dc['empty_row_count'] ?? 0 }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>AI warnings (diagnostic only)</h3>
        @if (!empty($preview['ai']['unavailable']))
            <p class="muted"><strong>AI data checker unavailable.</strong> {{ $preview['ai']['unavailable'] }} Deterministic validation still completed.</p>
        @endif

        @php
            $aiWarnings = $preview['ai']['warnings'] ?? [];
        @endphp

        @if (empty($aiWarnings))
            <p class="muted">No AI warnings.</p>
        @else
            <div class="muted" style="margin-bottom: 8px;">
                Filter:
                <select id="aiSeverityFilter" style="padding: 4px;">
                    <option value="all">All</option>
                    <option value="critical">critical</option>
                    <option value="warning">warning</option>
                    <option value="info">info</option>
                </select>
            </div>

            <table id="aiWarningsTable">
                <thead>
                <tr>
                    <th>Severity</th>
                    <th>Type</th>
                    <th>Message</th>
                    <th>Confidence</th>
                    <th>Rows</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($aiWarnings as $w)
                    @php
                        $sev = $w['severity'] ?? 'warning';
                        $conf = $w['confidence'] ?? null;
                        $rows = $w['affected_rows'] ?? [];
                        $pillClass = $sev === 'critical' ? 'bad' : ($sev === 'warning' ? 'warn' : 'ok');
                    @endphp
                    <tr data-severity="{{ $sev }}">
                        <td><span class="pill {{ $pillClass }}">{{ $sev }}</span></td>
                        <td><code>{{ $w['type'] ?? '' }}</code></td>
                        <td>
                            <div><strong>{{ $w['message'] ?? '' }}</strong></div>
                            <details>
                                <summary class="muted">Reason</summary>
                                <div class="muted" style="margin-top:6px;">{{ $w['reason'] ?? '' }}</div>
                            </details>
                        </td>
                        <td>{{ is_numeric($conf) ? number_format((float)$conf, 2) : 'n/a' }}</td>
                        <td>
                            @if (is_array($rows) && count($rows) > 0)
                                {{ implode(', ', array_slice($rows, 0, 30)) }}@if(count($rows) > 30)…@endif
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <script>
                (function () {
                    const sel = document.getElementById('aiSeverityFilter');
                    const table = document.getElementById('aiWarningsTable');
                    if (!sel || !table) return;
                    sel.addEventListener('change', () => {
                        const v = sel.value;
                        table.querySelectorAll('tbody tr').forEach(tr => {
                            const s = tr.getAttribute('data-severity');
                            tr.style.display = (v === 'all' || v === s) ? '' : 'none';
                        });
                    });
                })();
            </script>
        @endif
    </div>

    <div class="grid section">
        <div>
            <h3>Mapping preview</h3>
            <table>
                <thead>
                <tr>
                    <th>Supplier column</th>
                    <th>Template column</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach (($preview['mapping']['pairs'] ?? []) as $pair)
                    <tr>
                        <td>{{ $pair['source'] }}</td>
                        <td>{{ $pair['target'] }}</td>
                        <td class="ok">mapped</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @if (!empty($preview['mapping']['missing_source_columns']))
                <p class="muted">Missing source columns in supplier file (targets will stay empty):</p>
                <ul>
                    @foreach ($preview['mapping']['missing_source_columns'] as $c)
                        <li><code>{{ $c }}</code></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <h3>Template columns</h3>
            <div class="scroll">
                <table>
                    <thead>
                    <tr><th>#</th><th>Column</th></tr>
                    </thead>
                    <tbody>
                    @foreach (($preview['template']['columns'] ?? []) as $i => $c)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $c }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Output preview (first 30 rows)</h3>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    @foreach (($preview['template']['columns'] ?? []) as $c)
                        <th>{{ $c }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach (($preview['preview_rows'] ?? []) as $row)
                    <tr>
                        @foreach (($preview['template']['columns'] ?? []) as $c)
                            <td>{{ $row[$c] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid section">
        <div>
            <h3>Errors</h3>
            <table>
                <thead>
                <tr><th>Row</th><th>SKU</th><th>Message</th></tr>
                </thead>
                <tbody>
                @forelse (($preview['errors'] ?? []) as $e)
                    <tr>
                        <td>{{ $e['row_number'] }}</td>
                        <td>{{ $e['sku'] }}</td>
                        <td>{{ $e['message'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="ok">No errors</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div>
            <h3>Warnings</h3>
            <table>
                <thead>
                <tr><th>Row</th><th>SKU</th><th>Message</th></tr>
                </thead>
                <tbody>
                @forelse (($preview['warnings'] ?? []) as $w)
                    <tr>
                        <td>{{ $w['row_number'] }}</td>
                        <td>{{ $w['sku'] }}</td>
                        <td>{{ $w['message'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="ok">No warnings</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>

