@extends('layouts.import')

@section('title', $supplier_name)

@section('content')
    <div class="lpb-settings-head">
        <h1 class="lpb-page-title" style="margin:0;">{{ $supplier_name }}</h1>
        <a class="lpb-link" style="margin-left:auto;" href="{{ route('settings.brands.instruction.csv', ['supplier' => $supplier_code]) }}">Скачать инструкцию CSV</a>
    </div>

    @if (session('status'))
        <div class="lpb-notice lpb-notice--success lpb-page-flash">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="lpb-errors lpb-page-flash">
            <ul>
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('settings.brands.update', ['supplier' => $supplier_code]) }}">
        @csrf

        <section class="lpb-settings-section lpb-settings-section--accordion" aria-labelledby="settings-sku-title">
            <button
                type="button"
                class="lpb-settings-section__trigger"
                aria-expanded="false"
                aria-controls="settings-sku-panel"
            >
                <span class="lpb-settings-section__trigger-main">
                    <span class="lpb-settings-section__title" id="settings-sku-title">Генерация SKU</span>
                    <span class="lpb-settings-section__lead">Для Liewood: как подставлять SKU, если в файле уже есть значение.</span>
                </span>
                <span class="lpb-settings-section__chev-mark" aria-hidden="true">▼</span>
            </button>
            <div class="lpb-settings-section__panel" id="settings-sku-panel" role="region" aria-labelledby="settings-sku-title">
                <div class="lpb-settings-section__panel-inner">
                    <label class="lpb-label" for="sku_mode">Режим</label>
                    <select class="lpb-select" id="sku_mode" name="sku_mode">
                        <option value="file_then_formula" @selected($sku_mode === 'file_then_formula')>Из файла, если есть — иначе формула</option>
                        <option value="file_only" @selected($sku_mode === 'file_only')>Только из файла (не генерировать)</option>
                        <option value="always_formula" @selected($sku_mode === 'always_formula')>Всегда по формуле</option>
                    </select>
                </div>
            </div>
        </section>

        @php
            $lpbBucketLabels = [
                'footwear' => 'Обувь',
                'hat' => 'Шапки / кепки',
                'socks' => 'Носки',
                'generic' => 'Остальное (generic)',
            ];
        @endphp

        <section class="lpb-settings-section lpb-settings-section--accordion" aria-labelledby="settings-type-title">
            <button
                type="button"
                class="lpb-settings-section__trigger"
                aria-expanded="false"
                aria-controls="settings-type-panel"
            >
                <span class="lpb-settings-section__trigger-main">
                    <span class="lpb-settings-section__title" id="settings-type-title">Type → категория (маршрут размеров)</span>
                    <span class="lpb-settings-section__lead">Сохранённые привязки для Liewood. Нажмите строку типа ниже, чтобы открыть выбор категории.</span>
                </span>
                <span class="lpb-settings-section__chev-mark" aria-hidden="true">▼</span>
            </button>
            <div class="lpb-settings-section__panel" id="settings-type-panel" role="region" aria-labelledby="settings-type-title">
                <div class="lpb-settings-section__panel-inner">

            @if ($classifications === [])
                <div class="lpb-empty-state">
                    <p class="lpb-empty-state__title">Пока нет записей</p>
                    <p class="lpb-field-hint">Появятся после импорта, когда встретятся новые значения Type.</p>
                </div>
            @else
                <div class="lpb-type-acc-toolbar">
                    <button type="button" id="lpb-type-acc-expand-all" class="lpb-type-acc-toolbar__btn">Развернуть всё</button>
                    <button type="button" id="lpb-type-acc-collapse-all" class="lpb-type-acc-toolbar__btn">Свернуть всё</button>
                </div>
                <div class="lpb-type-acc" id="lpb-type-accordion" role="list">
                    @foreach ($classifications as $row)
                        @php
                            $b = $row->route_bucket;
                            $pillText = $lpbBucketLabels[$b] ?? $b;
                        @endphp
                        <div class="lpb-type-acc__item" role="listitem">
                            <button
                                type="button"
                                class="lpb-type-acc__head"
                                id="lpb-acc-head-{{ $row->id }}"
                                aria-expanded="false"
                                aria-controls="lpb-acc-panel-{{ $row->id }}"
                            >
                                <span class="lpb-type-acc__type"><code class="lpb-code">{{ $row->type_raw }}</code></span>
                                <span class="lpb-type-acc__pill lpb-type-acc__pill--{{ $b }}" data-bucket-pill>{{ $pillText }}</span>
                                <span class="lpb-type-acc__chev" aria-hidden="true">▼</span>
                            </button>
                            <div
                                class="lpb-type-acc__panel"
                                id="lpb-acc-panel-{{ $row->id }}"
                                role="region"
                                aria-labelledby="lpb-acc-head-{{ $row->id }}"
                            >
                                <div class="lpb-type-acc__panel-inner">
                                    <div class="lpb-type-acc__panel-body">
                                        <label class="lpb-label" for="bucket-select-{{ $row->id }}">Категория</label>
                                        <select class="lpb-select lpb-type-acc__select" id="bucket-select-{{ $row->id }}" name="bucket[{{ $row->id }}]">
                                            <option value="footwear" @selected($row->route_bucket === 'footwear')>Обувь</option>
                                            <option value="hat" @selected($row->route_bucket === 'hat')>Шапки / кепки</option>
                                            <option value="socks" @selected($row->route_bucket === 'socks')>Носки</option>
                                            <option value="generic" @selected($row->route_bucket === 'generic')>Остальное (generic)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

                </div>
            </div>
        </section>

        <div class="lpb-settings-actions">
            <button class="lpb-btn lpb-btn--settings-save" type="submit">Сохранить</button>
        </div>
    </form>

    <div class="lpb-back-row">
        <a class="lpb-link" href="{{ route('settings.brands.index') }}">Все бренды</a>
        <span class="lpb-muted">·</span>
        <a class="lpb-link" href="{{ route('imports.products') }}">Импорт</a>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            document.querySelectorAll('.lpb-settings-section--accordion').forEach(function (section) {
                var trigger = section.querySelector('.lpb-settings-section__trigger');
                var panel = section.querySelector('.lpb-settings-section__panel');
                if (!trigger || !panel) return;

                trigger.addEventListener('click', function () {
                    var expanded = trigger.getAttribute('aria-expanded') === 'true';
                    var next = !expanded;
                    trigger.setAttribute('aria-expanded', next ? 'true' : 'false');
                    panel.classList.toggle('is-open', next);
                });
            });

            var root = document.getElementById('lpb-type-accordion');
            if (!root) {
                return;
            }

            var labels = {
                footwear: 'Обувь',
                hat: 'Шапки / кепки',
                socks: 'Носки',
                generic: 'Остальное (generic)'
            };

            function setOpen(head, panel, open) {
                head.setAttribute('aria-expanded', open ? 'true' : 'false');
                panel.classList.toggle('is-open', open);
            }

            root.querySelectorAll('.lpb-type-acc__head').forEach(function (head) {
                var panelId = head.getAttribute('aria-controls');
                var panel = panelId ? document.getElementById(panelId) : null;
                if (!panel) return;

                head.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var expanded = head.getAttribute('aria-expanded') === 'true';
                    setOpen(head, panel, !expanded);
                });
            });

            root.querySelectorAll('.lpb-type-acc__select').forEach(function (sel) {
                sel.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
                sel.addEventListener('change', function () {
                    var item = sel.closest('.lpb-type-acc__item');
                    if (!item) return;
                    var pill = item.querySelector('[data-bucket-pill]');
                    if (!pill) return;
                    var v = sel.value;
                    pill.textContent = labels[v] || v;
                    pill.className = 'lpb-type-acc__pill lpb-type-acc__pill--' + v;
                });
            });

            var btnExp = document.getElementById('lpb-type-acc-expand-all');
            var btnCol = document.getElementById('lpb-type-acc-collapse-all');
            if (btnExp) {
                btnExp.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    root.querySelectorAll('.lpb-type-acc__head').forEach(function (head) {
                        var panel = document.getElementById(head.getAttribute('aria-controls'));
                        if (panel) setOpen(head, panel, true);
                    });
                });
            }
            if (btnCol) {
                btnCol.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    root.querySelectorAll('.lpb-type-acc__head').forEach(function (head) {
                        var panel = document.getElementById(head.getAttribute('aria-controls'));
                        if (panel) setOpen(head, panel, false);
                    });
                });
            }
        })();
    </script>
@endpush
