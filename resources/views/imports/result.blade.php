@extends('layouts.import')

@section('title', 'Результат')

@section('content')
    <h1 class="lpb-page-title">Готово</h1>
    <p class="lpb-lead">Задача <code class="lpb-code">{{ $preview['job_id'] }}</code></p>

    <div class="lpb-section lpb-downloads">
        @if (!empty($preview['exports']['xlsx']))
            <a href="{{ route('imports.products.download.xlsx', ['job' => $preview['job_id']]) }}">Скачать XLSX</a>
        @endif
        @if (!empty($preview['exports']['csv']))
            <a href="{{ route('imports.products.download.csv', ['job' => $preview['job_id']]) }}">Скачать CSV</a>
        @endif
        <a href="{{ route('imports.products') }}" style="background:transparent;border-color:transparent;color:var(--lpb-muted);">Новый импорт</a>
    </div>

    @if (!empty($preview['normalization']['saved_rules_applied']))
        <div class="lpb-notice">
            <strong>Сохранённые правила нормализации</strong>
            <span class="lpb-muted-inline">Учтены в превью и экспорте.</span>
        </div>
    @endif

    <div class="lpb-section">
        <h3 class="lpb-h3">Сводка</h3>
        <div class="lpb-table-wrap">
            <table class="lpb-table">
                <tbody>
                <tr><th>Файл</th><td>{{ $preview['input']['original_filename'] }}</td></tr>
                <tr><th>Поставщик</th><td>{{ $preview['input']['supplier_name'] }} ({{ $preview['input']['supplier_code'] }})</td></tr>
                <tr><th>Строк прочитано</th><td>{{ $preview['counts']['rows_read'] }}</td></tr>
                <tr><th>Строк экспорт</th><td>{{ $preview['counts']['rows_exported'] }}</td></tr>
                <tr><th>Предупреждения</th><td><span class="lpb-pill warn">{{ $preview['counts']['warning_count'] }}</span></td></tr>
                <tr><th>Ошибки</th><td><span class="lpb-pill bad">{{ $preview['counts']['error_count'] }}</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="lpb-section">
        <h3 class="lpb-h3">Детерминированная проверка</h3>
        <p class="lpb-muted">Критичные поля (идентификатор, имя, цена, дубликаты). Не блокируют экспорт — только отображаются здесь.</p>
        @php $dc = $preview['deterministic']['counts'] ?? []; @endphp
        <div class="lpb-table-wrap">
            <table class="lpb-table">
                <tbody>
                <tr>
                    <th>Дубликат SKU</th>
                    <td><span class="lpb-pill warn">{{ $dc['duplicate_sku_count'] ?? 0 }}</span></td>
                </tr>
                <tr>
                    <th>Нет идентификатора (SKU / Supplier Product ID)</th>
                    <td><span class="lpb-pill bad">{{ $dc['missing_sku_count'] ?? 0 }}</span></td>
                </tr>
                <tr>
                    <th>Нет названия (Product name EN)</th>
                    <td><span class="lpb-pill bad">{{ $dc['missing_name_count'] ?? 0 }}</span></td>
                </tr>
                <tr>
                    <th>Нет цены (Wholesale Price EUR)</th>
                    <td><span class="lpb-pill bad">{{ $dc['missing_price_count'] ?? 0 }}</span></td>
                </tr>
                <tr>
                    <th>Пустые строки (не экспорт)</th>
                    <td><span class="lpb-pill warn">{{ $dc['empty_row_count'] ?? 0 }}</span></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="lpb-section">
        <h3 class="lpb-h3">AI (диагностика)</h3>
        @if (!empty($preview['ai']['unavailable']))
            <p class="lpb-muted"><strong>Недоступен.</strong> {{ $preview['ai']['unavailable'] }}</p>
        @endif

        @php
            $aiWarnings = $preview['ai']['warnings'] ?? [];
        @endphp

        @if (empty($aiWarnings))
            <p class="lpb-muted">Нет предупреждений AI.</p>
        @else
            <div class="lpb-muted" style="margin-bottom:0.5rem;">
                Фильтр:
                <select class="lpb-select" id="aiSeverityFilter" style="display:inline-block; width:auto; min-width:140px; margin-left:0.35rem;">
                    <option value="all">Все</option>
                    <option value="critical">critical</option>
                    <option value="warning">warning</option>
                    <option value="info">info</option>
                </select>
            </div>

            <div class="lpb-table-wrap">
                <table class="lpb-table" id="aiWarningsTable">
                    <thead>
                    <tr>
                        <th>Важность</th>
                        <th>Тип</th>
                        <th>Сообщение</th>
                        <th>Уверенность</th>
                        <th>Строки</th>
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
                            <td><span class="lpb-pill {{ $pillClass }}">{{ $sev }}</span></td>
                            <td><code class="lpb-code">{{ $w['type'] ?? '' }}</code></td>
                            <td>
                                <div><strong>{{ $w['message'] ?? '' }}</strong></div>
                                <details class="lpb-details">
                                    <summary>Причина</summary>
                                    <div class="lpb-muted" style="margin-top:0.35rem;">{{ $w['reason'] ?? '' }}</div>
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
            </div>

            @push('scripts')
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
            @endpush
        @endif
    </div>

    <div class="lpb-grid-2 lpb-section">
        <div>
            <h3 class="lpb-h3">Маппинг</h3>
            <div class="lpb-table-wrap">
                <table class="lpb-table">
                    <thead>
                    <tr>
                        <th>Колонка файла</th>
                        <th>Шаблон</th>
                        <th>Статус</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach (($preview['mapping']['pairs'] ?? []) as $pair)
                        <tr>
                            <td>{{ $pair['source'] }}</td>
                            <td>{{ $pair['target'] }}</td>
                            <td class="lpb-status-ok">ok</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if (!empty($preview['mapping']['missing_source_columns']))
                <p class="lpb-muted">Нет в файле (цели останутся пустыми):</p>
                <ul>
                    @foreach ($preview['mapping']['missing_source_columns'] as $c)
                        <li><code class="lpb-code">{{ $c }}</code></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <h3 class="lpb-h3">Колонки шаблона</h3>
            <div class="lpb-table-wrap scroll-y">
                <table class="lpb-table">
                    <thead>
                    <tr><th>#</th><th>Колонка</th></tr>
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

    <div class="lpb-section">
        <h3 class="lpb-h3">Превью вывода (первые 30 строк)</h3>
        <div class="lpb-table-wrap scroll-y">
            <table class="lpb-table">
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

    <div class="lpb-grid-2 lpb-section">
        <div>
            <h3 class="lpb-h3">Ошибки</h3>
            <div class="lpb-table-wrap">
                <table class="lpb-table">
                    <thead>
                    <tr><th>Строка</th><th>SKU</th><th>Сообщение</th></tr>
                    </thead>
                    <tbody>
                    @forelse (($preview['errors'] ?? []) as $e)
                        <tr>
                            <td>{{ $e['row_number'] }}</td>
                            <td>{{ $e['sku'] }}</td>
                            <td>{{ $e['message'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="lpb-status-ok">Нет</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            <h3 class="lpb-h3">Предупреждения</h3>
            <div class="lpb-table-wrap">
                <table class="lpb-table">
                    <thead>
                    <tr><th>Строка</th><th>SKU</th><th>Сообщение</th></tr>
                    </thead>
                    <tbody>
                    @forelse (($preview['warnings'] ?? []) as $w)
                        <tr>
                            <td>{{ $w['row_number'] }}</td>
                            <td>{{ $w['sku'] }}</td>
                            <td>{{ $w['message'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="lpb-status-ok">Нет</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
