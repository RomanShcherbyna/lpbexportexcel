@extends('layouts.import')

@section('title', 'Mapping')

@section('content')
    <h1 class="lpb-page-title">Сопоставление колонок</h1>
    <div class="lpb-meta">
        <span>Файл <code>{{ $input['original_filename'] }}</code></span>
        <span>Поставщик <code>{{ $input['supplier_name'] }}</code></span>
    </div>

    @if (!empty($ai_unavailable))
        <div class="lpb-notice bad">
            <strong>AI mapping недоступен</strong>
            <span class="lpb-muted-inline">{{ $ai_unavailable }}</span>
            <span class="lpb-muted-inline">Можно продолжить с сохранённым / ручным маппингом.</span>
        </div>
    @else
        <div class="lpb-notice">
            <strong>Подсказки AI</strong>
            <span class="lpb-muted-inline">Только рекомендации — ничего не применится, пока не подтвердите таблицу ниже.</span>
        </div>
    @endif

    <p class="lpb-muted" style="margin-bottom:1rem;">
        <a class="lpb-link" href="{{ route('settings.brands.index') }}">Настройки брендов</a>
        — классификация Type (Liewood), режим SKU
    </p>

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
        <input type="hidden" name="liewood_photo_csv_links" value="{{ $input['liewood_photo_csv_links'] }}">
        <input type="hidden" name="liewood_gcs_prefix" value="{{ $input['liewood_gcs_prefix'] }}">

        @if (($input['supplier_code'] ?? '') === 'liewood' && ! empty($unknown_liewood_types ?? []))
            <div class="lpb-notice bad">
                <strong>Новые значения Type в файле</strong>
                <span class="lpb-muted-inline">Укажите категорию один раз — сохранится для следующих импортов.</span>
                @foreach ($unknown_liewood_types as $raw)
                    @php $h = md5($raw); @endphp
                    <input type="hidden" name="unknown_type_hash[{{ $h }}]" value="{{ $raw }}">
                    <div class="lpb-type-row">
                        <div style="margin-bottom:0.35rem;"><code class="lpb-code">{{ $raw }}</code></div>
                        <select class="lpb-select" name="type_resolution[{{ $h }}]" required style="max-width:100%;">
                            @php $sel = old('type_resolution.'.$h); @endphp
                            <option value="" @selected($sel === null || $sel === '') disabled>— выберите категорию —</option>
                            <option value="footwear" @selected($sel === 'footwear')>Обувь (число → Shoe size)</option>
                            <option value="hat" @selected($sel === 'hat')>Шапка / кепка (45–62 → hatz size)</option>
                            <option value="socks" @selected($sel === 'socks')>Носки</option>
                            <option value="generic" @selected($sel === 'generic')>Остальное (XS–XL / возраст и т.д.)</option>
                        </select>
                    </div>
                @endforeach
            </div>
        @endif

        <h3 class="lpb-h3">Таблица соответствий</h3>
        <div class="lpb-table-wrap">
            <table class="lpb-table">
                <thead>
                <tr>
                    <th style="width:18%;">Колонка в файле</th>
                    <th style="width:22%;">Из конфига</th>
                    <th style="width:22%;">Подсказка AI</th>
                    <th style="width:8%;">Уверенность</th>
                    <th style="width:30%;">Итоговый выбор</th>
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
                        <td><code class="lpb-code">{{ $src }}</code></td>
                        <td>{{ $saved ?? '—' }}</td>
                        <td>
                            {{ $ai ?? '—' }}
                            @if (!empty($reason))
                                <div class="lpb-reason">{{ $reason }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($badge)
                                <span class="lpb-pill {{ $badge }}">{{ number_format((float)$conf, 2) }}</span>
                            @else
                                <span class="lpb-pill">n/a</span>
                            @endif
                        </td>
                        <td>
                            <select class="lpb-select" name="mapping[{{ $src }}]">
                                <option value="__unmapped__" @selected($defaultFinal === '__unmapped__')>Не маппить</option>
                                @foreach ($template_columns as $col)
                                    <option value="{{ $col }}" @selected($defaultFinal === $col)>{{ $col }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <button class="lpb-btn" type="submit">Подтвердить и продолжить</button>
        <a class="lpb-btn-ghost" href="{{ route('imports.products') }}">Назад</a>
    </form>

    @if (!empty($ai_raw_example))
        <details class="lpb-details" style="margin-top:1.25rem;">
            <summary>Отладка: сырой ответ AI (JSON)</summary>
            <pre style="white-space:pre-wrap; margin:0.75rem 0 0; padding:0.85rem; border-radius:10px; border:1px solid var(--lpb-border); background:var(--lpb-surface-muted); font-size:0.72rem; font-family:var(--lpb-mono); overflow:auto;">{{ $ai_raw_example }}</pre>
        </details>
    @endif
@endsection
