@extends('layouts.import')

@section('title', 'Import')

@section('content')
    <h1 class="lpb-page-title">Загрузка файла</h1>

    @if ($errors->any())
        <div class="lpb-errors">
            <strong>Исправьте ошибки</strong>
            <ul>
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $photoLinksDefault = old('liewood_photo_csv_links');
        if ($photoLinksDefault !== 'preview' && $photoLinksDefault !== 'download') {
            $photoLinksDefault = filter_var(config('liewood_drive.gcs_use_download_proxy', false), FILTER_VALIDATE_BOOLEAN) ? 'download' : 'preview';
        }
        $gcsPrefixDefault = (string) ($gcs_prefix_default ?? '');
        $gcsPrefixOptions = is_array($gcs_prefix_options ?? null) ? $gcs_prefix_options : [];
        if ($gcsPrefixDefault !== '' && ! in_array($gcsPrefixDefault, $gcsPrefixOptions, true)) {
            array_unshift($gcsPrefixOptions, $gcsPrefixDefault);
        }
    @endphp

    <form method="post" action="{{ route('imports.products.mapping') }}" enctype="multipart/form-data" id="lpb-import-form">
        @csrf

        <label class="lpb-label" for="lpb-file-input">Файл Excel поставщика</label>
        <div
            class="lpb-dropzone"
            id="lpb-dropzone"
            role="button"
            tabindex="0"
            aria-label="Зона загрузки: перетащите файл или нажмите для выбора"
        >
            <input
                class="lpb-file-input-native"
                id="lpb-file-input"
                name="file"
                type="file"
                accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                required
            >
            <div class="lpb-dropzone__inner">
                <svg class="lpb-dropzone__icon" width="56" height="56" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                </svg>
                <p class="lpb-dropzone__title">Перетащите файл сюда</p>
                <p class="lpb-dropzone__sub">или нажмите в этой области, чтобы выбрать на устройстве</p>
                <p class="lpb-dropzone__hint">Форматы: .xlsx, .xls, .csv</p>
                <div class="lpb-dropzone__file" id="lpb-file-name" hidden></div>
            </div>
        </div>

        <label class="lpb-label" for="supplier">Поставщик</label>
        <select class="lpb-select" id="supplier" name="supplier" required>
            <option value="" disabled selected>Выберите поставщика</option>
            @foreach ($suppliers as $s)
                <option value="{{ $s['code'] }}" @selected(old('supplier') === $s['code'])>{{ $s['name'] }}</option>
            @endforeach
        </select>

        <label class="lpb-label" for="liewood_gcs_prefix">Папка с фото в GCS</label>
        <select class="lpb-select" id="liewood_gcs_prefix" name="liewood_gcs_prefix" required>
            @foreach ($gcsPrefixOptions as $prefix)
                <option value="{{ $prefix }}" @selected($gcsPrefixDefault === $prefix)>
                    {{ $prefix === ($gcs_no_photo_value ?? '__NO_PHOTO__') ? 'без фото' : $prefix }}
                </option>
            @endforeach
        </select>

        <label class="lpb-label">Ссылки на фото в CSV (Liewood, GCS)</label>
        <div class="lpb-radio-stack">
            <label>
                <input type="radio" name="liewood_photo_csv_links" value="preview" @checked($photoLinksDefault === 'preview') required>
                <span><strong>Превью</strong> — прямая ссылка на Storage (картинка в браузере).</span>
            </label>
            <label>
                <input type="radio" name="liewood_photo_csv_links" value="download" @checked($photoLinksDefault === 'download')>
                <span><strong>Скачивание</strong> — ссылка с принудительным скачиванием.</span>
            </label>
        </div>

        <input type="hidden" name="export_xlsx" value="0">
        <input type="hidden" name="export_csv" value="1">

        <button class="lpb-btn" type="submit">Далее: сопоставление колонок</button>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const zone = document.getElementById('lpb-dropzone');
    const input = document.getElementById('lpb-file-input');
    const nameEl = document.getElementById('lpb-file-name');
    if (!zone || !input || !nameEl) return;

    const allowedExt = ['.xlsx', '.xls', '.csv'];
    function isAllowedFile(file) {
        const n = (file && file.name) ? file.name.toLowerCase() : '';
        return allowedExt.some(function (ext) { return n.endsWith(ext); });
    }

    function showName(text) {
        nameEl.textContent = text;
        nameEl.hidden = false;
    }

    function clearName() {
        nameEl.textContent = '';
        nameEl.hidden = true;
    }

    function setFile(file) {
        if (!file || !isAllowedFile(file)) {
            alert('Нужен файл Excel или CSV: .xlsx, .xls или .csv');
            return;
        }
        try {
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            showName(file.name);
        } catch (e) {
            alert('Не удалось принять файл. Выберите файл через кнопку системы.');
        }
    }

    input.addEventListener('change', function () {
        if (input.files && input.files.length) {
            const f = input.files[0];
            if (!isAllowedFile(f)) {
                input.value = '';
                clearName();
                alert('Нужен файл Excel или CSV: .xlsx, .xls или .csv');
                return;
            }
            showName(f.name);
        } else {
            clearName();
        }
    });

    zone.addEventListener('click', function (e) {
        if (e.target === input) return;
        input.click();
    });

    zone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            input.click();
        }
    });

    zone.addEventListener('dragenter', function (e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.add('is-dragover');
    });
    zone.addEventListener('dragleave', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var to = e.relatedTarget;
        if (to && zone.contains(to)) return;
        zone.classList.remove('is-dragover');
    });
    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.add('is-dragover');
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
    });
    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.remove('is-dragover');
        var files = e.dataTransfer && e.dataTransfer.files;
        if (!files || !files.length) return;
        setFile(files[0]);
    });
})();
</script>
@endpush
