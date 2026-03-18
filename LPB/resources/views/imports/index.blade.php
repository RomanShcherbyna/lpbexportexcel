<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Product Import</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 24px; }
        .box { max-width: 900px; }
        label { display: block; font-weight: 600; margin: 14px 0 6px; }
        input[type="file"], select { width: 100%; padding: 8px; }
        .row { display: flex; gap: 12px; align-items: center; }
        .row > * { flex: 1; }
        .errors { background: #ffe8e8; padding: 12px; border: 1px solid #ffb3b3; margin: 12px 0; }
        .hint { color: #555; font-size: 13px; }
        .btn { margin-top: 18px; padding: 10px 14px; font-weight: 700; }
        .checks { display: flex; gap: 18px; margin-top: 8px; }
        .checks label { font-weight: 500; margin: 0; }
    </style>
</head>
<body>
<div class="box">
    <h2>Product converter (Phase 1)</h2>
    <p class="hint">Strict converter: supplier Excel → BaseLinker template → XLSX/CSV. No guessing. Empty stays empty.</p>

    @if ($errors->any())
        <div class="errors">
            <div><strong>Errors</strong></div>
            <ul>
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('imports.products.mapping') }}" enctype="multipart/form-data">
        @csrf

        <label for="file">Supplier Excel file</label>
        <input id="file" name="file" type="file" accept=".xlsx,.xls,.csv" required>

        <label for="supplier">Supplier</label>
        <select id="supplier" name="supplier" required>
            <option value="" disabled selected>Select supplier</option>
            @foreach ($suppliers as $s)
                <option value="{{ $s['code'] }}" @selected(old('supplier') === $s['code'])>{{ $s['name'] }}</option>
            @endforeach
        </select>

        <label>Export</label>
        <div class="checks">
            <label><input type="checkbox" name="export_xlsx" value="1" @checked(old('export_xlsx', true))> XLSX</label>
            <label><input type="checkbox" name="export_csv" value="1" @checked(old('export_csv', true))> CSV (UTF-8, ;)</label>
        </div>

        <button class="btn" type="submit">Next: Review mapping</button>
    </form>
</div>
</body>
</html>

