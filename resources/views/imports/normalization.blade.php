@extends('layouts.import')

@section('title', 'Normalization')

@section('content')
    <h1 class="lpb-page-title">Нормализация</h1>
    <div class="lpb-meta">
        <span>{{ $input['supplier_name'] }}</span>
        <span><code>{{ $input['original_filename'] }}</code></span>
    </div>

    @if (!empty($saved_rules_applied))
        <div class="lpb-notice">
            <strong>Применены сохранённые правила</strong>
            <span class="lpb-muted-inline">Для превью и экспорта уже подмешаны правила нормализации из настроек поставщика.</span>
        </div>
    @endif

    @if (!empty($ai_unavailable))
        <div class="lpb-notice bad">
            <strong>AI нормализация недоступна</strong>
            <span class="lpb-muted-inline">{{ $ai_unavailable }}</span>
            <span class="lpb-muted-inline">Можно продолжить без правил.</span>
        </div>
    @endif

    <h3 class="lpb-h3">Предлагаемые правила</h3>
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
        <input type="hidden" name="liewood_photo_csv_links" value="{{ $input['liewood_photo_csv_links'] }}">
        <input type="hidden" name="liewood_gcs_prefix" value="{{ $input['liewood_gcs_prefix'] }}">

        <div class="lpb-table-wrap">
            <table class="lpb-table">
                <thead>
                <tr>
                    <th style="width:16%;">Колонка</th>
                    <th style="width:34%;">Исходные значения</th>
                    <th style="width:18%;">Канон</th>
                    <th style="width:10%;">Уверенность</th>
                    <th style="width:8%;">Применить</th>
                    <th style="width:22%;">Причина</th>
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
                        <td><code class="lpb-code">{{ implode(', ', $r['source_values']) }}</code></td>
                        <td>
                            <input class="lpb-input" type="text" name="rules[{{ $idx }}][canonical_value]" value="{{ old("rules.$idx.canonical_value", $r['canonical_value']) }}">
                        </td>
                        <td>
                            @if ($badge)
                                <span class="lpb-pill {{ $badge }}">{{ number_format((float)$conf, 2) }}</span>
                            @else
                                <span class="lpb-pill">n/a</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <input type="checkbox" name="rules[{{ $idx }}][apply]" value="1" @checked(old("rules.$idx.apply", true))>
                        </td>
                        <td class="lpb-muted">{{ $r['reason'] }}</td>
                    </tr>

                    <input type="hidden" name="rules[{{ $idx }}][target_column]" value="{{ $r['target_column'] }}">
                    @foreach ($r['source_values'] as $sv)
                        <input type="hidden" name="rules[{{ $idx }}][source_values][]" value="{{ $sv }}">
                    @endforeach
                @empty
                    <tr><td colspan="6" class="lpb-muted">Нет предложений.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <button class="lpb-btn" type="submit">Экспорт</button>
        <a class="lpb-btn-ghost" href="{{ route('imports.products') }}">Назад</a>
    </form>
@endsection
