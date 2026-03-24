{{-- Shared UI for import + settings (inline, no build step) --}}
:root {
    --lpb-bg: #f1f5f9;
    --lpb-bg-accent: #e2e8f0;
    --lpb-surface: #ffffff;
    --lpb-surface-muted: #f8fafc;
    --lpb-text: #0f172a;
    --lpb-muted: #64748b;
    --lpb-border: #e2e8f0;
    /* Акцент — нейтральный slate (без фиолетового) */
    --lpb-accent: #1e293b;
    --lpb-accent-hover: #0f172a;
    --lpb-accent-soft: rgba(15, 23, 42, 0.08);
    --lpb-success: #059669;
    --lpb-success-soft: #d1fae5;
    --lpb-warning: #d97706;
    --lpb-warning-soft: #fef3c7;
    --lpb-danger: #dc2626;
    --lpb-danger-soft: #fee2e2;
    --lpb-radius: 16px;
    --lpb-radius-sm: 12px;
    --lpb-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 10px 28px rgba(15, 23, 42, 0.07);
    --lpb-font: "DM Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    --lpb-mono: "JetBrains Mono", ui-monospace, monospace;
    /* Поля формы — как на макете: светлая панель */
    --lpb-field-bg: #f1f5f9;
    --lpb-field-bg-hover: #e8eef4;
    --lpb-field-border: #e2e8f0;
    --lpb-field-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
}

*, *::before, *::after { box-sizing: border-box; }

html { font-size: 18px; -webkit-font-smoothing: antialiased; }

body.lpb-ui {
    margin: 0;
    min-height: 100vh;
    font-family: var(--lpb-font);
    color: var(--lpb-text);
    background: var(--lpb-bg);
    background-image:
        radial-gradient(ellipse 110% 70% at 100% 0%, #e2e8f0 0%, transparent 52%),
        radial-gradient(ellipse 90% 55% at 0% 100%, #e2e8f0 0%, transparent 48%);
    line-height: 1.55;
}

.lpb-shell {
    max-width: 1180px;
    margin: 0 auto;
    padding: 1.5rem 1.15rem 3.25rem;
}

.lpb-topbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1rem;
    margin-bottom: 1.5rem;
}

.lpb-brand {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    text-decoration: none;
    color: inherit;
}

/* Логотип: крупнее, картинка почти на весь квадрат (минимальный внутренний отступ) */
.lpb-brand-mark {
    width: 4rem;
    height: 4rem;
    border-radius: 14px;
    flex-shrink: 0;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lpb-brand-mark--placeholder {
    background: linear-gradient(135deg, #475569 0%, #64748b 100%);
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.18);
}

.lpb-brand-mark--image {
    background: #fff;
    border: 1px solid var(--lpb-border);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
    padding: 0;
}

.lpb-brand-mark--image img {
    width: 100%;
    height: 100%;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    object-position: center;
    display: block;
}

.lpb-brand-text {
    font-weight: 700;
    font-size: 1.2rem;
    letter-spacing: -0.02em;
}

.lpb-brand-sub {
    font-size: 0.875rem;
    color: var(--lpb-muted);
    font-weight: 500;
}

.lpb-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    align-items: center;
}

.lpb-nav a {
    display: inline-flex;
    align-items: center;
    padding: 0.55rem 1.05rem;
    border-radius: 999px;
    font-size: 1rem;
    font-weight: 600;
    color: var(--lpb-muted);
    text-decoration: none;
    transition: color 0.15s, background 0.15s;
}

.lpb-nav a:hover {
    color: var(--lpb-text);
    background: var(--lpb-accent-soft);
}

.lpb-nav a.is-active {
    color: var(--lpb-text);
    background: var(--lpb-accent-soft);
}

.lpb-card {
    background: var(--lpb-surface);
    border-radius: var(--lpb-radius);
    box-shadow: var(--lpb-shadow);
    border: 1px solid rgba(226, 232, 240, 0.9);
    padding: 1.75rem 1.75rem 2rem;
}

@media (max-width: 640px) {
    .lpb-card { padding: 1.25rem 1.15rem 1.5rem; }
}

.lpb-page-title {
    margin: 0 0 0.35rem;
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    line-height: 1.25;
}

.lpb-lead {
    margin: 0 0 1.25rem;
    color: var(--lpb-muted);
    font-size: 0.95rem;
    max-width: 52ch;
}

.lpb-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.25rem;
    margin-bottom: 1.25rem;
    font-size: 0.875rem;
    color: var(--lpb-muted);
}

.lpb-meta code {
    font-family: var(--lpb-mono);
    font-size: 0.8rem;
    background: var(--lpb-surface-muted);
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    color: var(--lpb-text);
}

h3.lpb-h3 {
    margin: 1.65rem 0 0.85rem;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--lpb-text);
    letter-spacing: -0.02em;
}

.lpb-table-wrap {
    overflow: auto;
    border-radius: var(--lpb-radius-sm);
    border: 1px solid var(--lpb-border);
    margin: 0.75rem 0 1rem;
}

.lpb-table-wrap--flush {
    margin: 0;
}

.lpb-table-wrap.scroll-y {
    max-height: min(420px, 55vh);
}

table.lpb-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.94rem;
}

table.lpb-table th,
table.lpb-table td {
    padding: 0.75rem 0.95rem;
    text-align: left;
    vertical-align: top;
    border-bottom: 1px solid var(--lpb-border);
}

table.lpb-table th {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    font-weight: 600;
    color: #475569;
    white-space: nowrap;
}

table.lpb-table tbody tr:last-child td {
    border-bottom: none;
}

table.lpb-table tbody tr:hover td {
    background: #fafbfc;
}

.lpb-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 600;
    background: #f1f5f9;
    color: #475569;
}

.lpb-pill.high { background: var(--lpb-success-soft); color: #047857; }
.lpb-pill.mid { background: var(--lpb-warning-soft); color: #b45309; }
.lpb-pill.low { background: var(--lpb-danger-soft); color: #b91c1c; }
.lpb-pill.ok { background: var(--lpb-success-soft); color: #047857; }
.lpb-pill.warn { background: var(--lpb-warning-soft); color: #b45309; }
.lpb-pill.bad { background: var(--lpb-danger-soft); color: #b91c1c; }

.lpb-notice {
    padding: 0.85rem 1rem;
    border-radius: var(--lpb-radius-sm);
    border: 1px solid var(--lpb-border);
    background: var(--lpb-surface-muted);
    margin: 0.75rem 0 1rem;
    font-size: 0.9rem;
}

.lpb-notice.bad {
    border-color: #fecaca;
    background: #fff7f7;
    color: #7f1d1d;
}

.lpb-notice strong {
    display: block;
    margin-bottom: 0.25rem;
    color: var(--lpb-text);
}

.lpb-notice .lpb-muted-inline {
    color: var(--lpb-muted);
    font-size: 0.85rem;
}

.lpb-label {
    display: block;
    font-weight: 600;
    font-size: 0.92rem;
    letter-spacing: -0.01em;
    color: #475569;
    margin: 1.35rem 0 0.5rem;
}

.lpb-card form > .lpb-label:first-of-type,
.lpb-card form > .lpb-errors + .lpb-label {
    margin-top: 0;
}

.lpb-input,
.lpb-select,
.lpb-textarea {
    width: 100%;
    max-width: 100%;
    padding: 0.7rem 1rem;
    min-height: 2.85rem;
    border-radius: 12px;
    border: 1px solid var(--lpb-field-border);
    font-family: inherit;
    font-size: 1rem;
    line-height: 1.4;
    color: var(--lpb-text);
    background: var(--lpb-field-bg);
    box-shadow: var(--lpb-field-shadow);
    transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
}

.lpb-input:hover,
.lpb-select:hover,
.lpb-textarea:hover {
    background: var(--lpb-field-bg-hover);
    border-color: #cbd5e1;
}

.lpb-select {
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    padding-right: 2.75rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23475569' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.85rem center;
    background-size: 1.1rem 1.1rem;
    background-color: var(--lpb-field-bg);
}

.lpb-select:hover {
    background-color: var(--lpb-field-bg-hover);
}

.lpb-select:focus {
    background-color: #fff;
}

input[type="file"].lpb-input {
    padding: 0.5rem 0.65rem;
    cursor: pointer;
    border-style: dashed;
    background: var(--lpb-surface-muted);
}

.lpb-input:focus,
.lpb-select:focus,
.lpb-textarea:focus {
    outline: none;
    border-color: #94a3b8;
    background: #fff;
    box-shadow: var(--lpb-field-shadow), 0 0 0 3px rgba(148, 163, 184, 0.35);
}

/* Подсказка под полем */
.lpb-field-hint {
    margin: 0.45rem 0 0;
    font-size: 0.86rem;
    line-height: 1.45;
    color: #64748b;
}

.lpb-field-hint .lpb-code {
    font-size: 0.82rem;
    background: #e2e8f0;
    color: #334155;
}

textarea.lpb-textarea {
    min-height: 11rem;
    font-family: var(--lpb-mono);
    font-size: 0.88rem;
    line-height: 1.5;
    resize: vertical;
}

.lpb-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    margin-top: 1.15rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 12px;
    font-family: inherit;
    font-size: 1.05rem;
    font-weight: 700;
    cursor: pointer;
    background: linear-gradient(180deg, #334155 0%, #1e293b 100%);
    color: #fff;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.22);
    transition: transform 0.12s, box-shadow 0.12s;
}

.lpb-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.28);
}

.lpb-btn:active {
    transform: translateY(0);
}

.lpb-btn-ghost {
    display: inline-flex;
    align-items: center;
    margin-top: 1.1rem;
    margin-left: 0.75rem;
    padding: 0.75rem 0.55rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--lpb-muted);
    text-decoration: none;
    border-radius: 8px;
}

.lpb-btn-ghost:hover {
    color: var(--lpb-text);
}

.lpb-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #334155;
    font-weight: 700;
    font-size: 0.92rem;
    text-decoration: none;
    padding: 0.42rem 0.75rem;
    border-radius: 10px;
    border: 1px solid #dbe3ed;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    transition:
        transform 0.16s ease,
        box-shadow 0.22s ease,
        border-color 0.2s ease,
        color 0.2s ease,
        background 0.22s ease;
}

.lpb-link:hover {
    color: #0f172a;
    border-color: #cbd5e1;
    background: linear-gradient(180deg, #ffffff 0%, #eef2f7 100%);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.1);
    transform: translateY(-1px);
}

.lpb-link:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

.lpb-muted {
    color: var(--lpb-muted);
    font-size: 0.95rem;
}

.lpb-reason {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.35rem;
}

.lpb-errors {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    border-radius: var(--lpb-radius-sm);
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    color: #9f1239;
    font-size: 0.875rem;
}

.lpb-errors ul { margin: 0.35rem 0 0; padding-left: 1.2rem; }

.lpb-type-row {
    margin: 0.75rem 0;
    padding: 0.75rem 0.85rem;
    background: var(--lpb-surface-muted);
    border-radius: var(--lpb-radius-sm);
    border: 1px solid var(--lpb-border);
}

.lpb-type-row code {
    font-family: var(--lpb-mono);
    font-size: 0.8rem;
    background: #fff;
    padding: 0.15rem 0.4rem;
    border-radius: 6px;
}

.lpb-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

@media (max-width: 900px) {
    .lpb-grid-2 { grid-template-columns: 1fr; }
}

.lpb-downloads {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.lpb-downloads a {
    display: inline-flex;
    padding: 0.6rem 1.15rem;
    border-radius: 11px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    background: var(--lpb-surface-muted);
    color: var(--lpb-text);
    border: 1px solid var(--lpb-border);
}

.lpb-downloads a:hover {
    background: #e2e8f0;
}

.lpb-section {
    margin: 1.5rem 0;
}

details.lpb-details summary {
    cursor: pointer;
    color: var(--lpb-muted);
    font-size: 0.8rem;
}

code.lpb-code {
    font-family: var(--lpb-mono);
    font-size: 0.88rem;
    background: var(--lpb-surface-muted);
    padding: 0.12rem 0.4rem;
    border-radius: 4px;
}

.lpb-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 0.5rem;
}

.lpb-checks label {
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin: 0;
}

.lpb-radio-stack {
    padding: 0.85rem 1rem;
    border-radius: 12px;
    background: var(--lpb-field-bg);
    border: 1px solid var(--lpb-field-border);
    box-shadow: var(--lpb-field-shadow);
}

.lpb-radio-stack label {
    font-weight: 500;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin: 0.5rem 0;
    color: #334155;
}

.lpb-radio-stack label:first-child { margin-top: 0; }
.lpb-radio-stack label:last-child { margin-bottom: 0; }

.lpb-status-ok { color: var(--lpb-success); font-weight: 700; }

/* Зона загрузки файла (drag & drop) */
.lpb-dropzone {
    position: relative;
    min-height: 220px;
    border: 2px dashed #cbd5e1;
    border-radius: 14px;
    background: var(--lpb-field-bg);
    box-shadow: var(--lpb-field-shadow);
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s, transform 0.15s;
}

@media (min-width: 640px) {
    .lpb-dropzone { min-height: 260px; }
}

.lpb-dropzone:hover {
    border-color: #94a3b8;
    background: var(--lpb-field-bg-hover);
}

.lpb-dropzone.is-dragover {
    border-color: #64748b;
    border-style: solid;
    background: #e2e8f0;
    box-shadow: inset 0 0 0 3px rgba(15, 23, 42, 0.06);
    transform: scale(1.005);
}

.lpb-dropzone:focus-within {
    outline: none;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.12);
}

.lpb-dropzone__inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 2rem 1.5rem;
    min-height: inherit;
    pointer-events: none;
}

.lpb-dropzone__icon {
    color: #64748b;
    margin-bottom: 1rem;
    opacity: 0.9;
}

.lpb-dropzone.is-dragover .lpb-dropzone__icon {
    color: #334155;
    transform: translateY(-2px);
    transition: transform 0.2s;
}

.lpb-dropzone__title {
    margin: 0 0 0.5rem;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--lpb-text);
    letter-spacing: -0.02em;
}

.lpb-dropzone__sub {
    margin: 0;
    font-size: 0.95rem;
    color: var(--lpb-muted);
    line-height: 1.45;
    max-width: 28rem;
}

.lpb-dropzone__hint {
    margin: 0.75rem 0 0;
    font-size: 0.82rem;
    color: #94a3b8;
}

.lpb-dropzone__file {
    margin: 1rem 0 0;
    padding: 0.5rem 0.85rem;
    border-radius: 8px;
    background: #fff;
    border: 1px solid var(--lpb-border);
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--lpb-text);
    max-width: 100%;
    word-break: break-word;
    pointer-events: auto;
}

.lpb-file-input-native {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Список брендов (настройки) */
.lpb-brand-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
    margin: 1.25rem 0 0;
    max-width: 100%;
}

@media (min-width: 900px) {
    .lpb-brand-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.15rem;
    }
}

.lpb-brand-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1.1rem 1.15rem;
    border-radius: 14px;
    border: 1px solid var(--lpb-field-border);
    background: var(--lpb-field-bg);
    box-shadow: var(--lpb-field-shadow);
    text-decoration: none;
    color: inherit;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}

.lpb-brand-card:hover {
    transform: translateY(-2px);
    border-color: #cbd5e1;
    background: #fff;
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.08);
}

.lpb-brand-card:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.45);
}

.lpb-brand-card__icon {
    flex-shrink: 0;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 10px;
    background: linear-gradient(145deg, #e2e8f0 0%, #f1f5f9 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.78rem;
    color: #475569;
    letter-spacing: 0.02em;
}

.lpb-brand-card__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.lpb-brand-card__name {
    font-weight: 700;
    font-size: 1.02rem;
    color: var(--lpb-text);
    letter-spacing: -0.02em;
    line-height: 1.25;
}

.lpb-brand-card__code {
    font-family: var(--lpb-mono);
    font-size: 0.78rem;
    color: #64748b;
}

.lpb-brand-card__arrow {
    flex-shrink: 0;
    color: #94a3b8;
    font-size: 1.15rem;
    line-height: 1;
    transition: transform 0.15s ease, color 0.15s ease;
}

.lpb-brand-card:hover .lpb-brand-card__arrow {
    color: #475569;
    transform: translateX(3px);
}

.lpb-back-row {
    margin-top: 1.75rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--lpb-border);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
}

/* Страница настроек бренда */
.lpb-settings-head {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.65rem 1rem;
    margin-bottom: 1.5rem;
}

.lpb-settings-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.7rem;
    border-radius: 10px;
    font-family: var(--lpb-mono);
    font-size: 0.82rem;
    font-weight: 500;
    color: #475569;
    background: var(--lpb-field-bg);
    border: 1px solid var(--lpb-field-border);
}

.lpb-settings-section {
    margin-bottom: 1.15rem;
    padding: 1.35rem 1.4rem 1.4rem;
    border-radius: 14px;
    border: 1px solid var(--lpb-field-border);
    background: #fff;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.05);
}

/* Секции настроек бренда — сворачиваемый блок */
.lpb-settings-section--accordion {
    padding: 0;
    overflow: hidden;
    transition:
        box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1),
        border-color 0.3s ease;
}

.lpb-settings-section--accordion:has(.lpb-settings-section__trigger[aria-expanded="true"]) {
    border-color: #cbd5e1;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.07);
}

.lpb-settings-section__trigger {
    width: 100%;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.85rem 1.15rem;
    padding: 1.35rem 1.4rem 1.15rem;
    margin: 0;
    border: none;
    background: transparent;
    cursor: pointer;
    text-align: left;
    font: inherit;
    color: inherit;
    transition: background 0.28s ease;
}

.lpb-settings-section__trigger:hover {
    background: var(--lpb-surface-muted);
}

.lpb-settings-section__trigger:focus-visible {
    outline: 2px solid var(--lpb-accent);
    outline-offset: -2px;
}

.lpb-settings-section__trigger-main {
    flex: 1;
    min-width: 0;
}

.lpb-settings-section--accordion .lpb-settings-section__title {
    margin: 0 0 0.15rem;
    display: block;
    font-size: 1.08rem;
    font-weight: 700;
    color: var(--lpb-text);
    letter-spacing: -0.02em;
}

.lpb-settings-section--accordion .lpb-settings-section__lead {
    margin: 0;
    font-size: 0.88rem;
    line-height: 1.45;
    color: #64748b;
}

.lpb-settings-section__chev-mark {
    flex-shrink: 0;
    width: 1.65rem;
    height: 1.65rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--lpb-field-bg);
    color: #64748b;
    font-size: 0.68rem;
    line-height: 1;
    margin-top: 0.15rem;
    transition:
        transform 0.42s cubic-bezier(0.34, 1.3, 0.64, 1),
        background 0.25s ease,
        color 0.25s ease;
}

.lpb-settings-section__trigger[aria-expanded="true"] .lpb-settings-section__chev-mark {
    transform: rotate(180deg);
    background: var(--lpb-accent-soft);
    color: var(--lpb-accent);
}

.lpb-settings-section__panel {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.44s cubic-bezier(0.4, 0, 0.2, 1);
}

.lpb-settings-section__panel.is-open {
    grid-template-rows: 1fr;
}

.lpb-settings-section__panel-inner {
    min-height: 0;
    overflow: hidden;
    padding: 0 1.4rem 1.4rem;
    opacity: 0;
    transform: translateY(-8px);
    transition:
        opacity 0.34s ease 0.06s,
        transform 0.4s cubic-bezier(0.34, 1.12, 0.64, 1) 0.05s;
}

.lpb-settings-section__panel.is-open .lpb-settings-section__panel-inner {
    opacity: 1;
    transform: translateY(0);
}

.lpb-settings-section__panel-inner .lpb-label:first-of-type {
    margin-top: 0;
}

.lpb-settings-section:last-of-type {
    margin-bottom: 0;
}

.lpb-settings-section__title {
    margin: 0 0 0.15rem;
    font-size: 1.08rem;
    font-weight: 700;
    color: var(--lpb-text);
    letter-spacing: -0.02em;
}

.lpb-settings-section__lead {
    margin: 0 0 1rem;
    font-size: 0.88rem;
    line-height: 1.45;
    color: #64748b;
}

.lpb-settings-section .lpb-label:first-of-type {
    margin-top: 0;
}

.lpb-settings-section .lpb-field-hint {
    margin-top: 0.5rem;
}

.lpb-settings-actions {
    margin-top: 1.5rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem 1.25rem;
}

.lpb-btn--settings-save {
    margin-top: 0;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border: 1px solid #0b1221;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.28);
    transition:
        transform 0.16s ease,
        box-shadow 0.24s ease,
        filter 0.2s ease;
}

.lpb-btn--settings-save:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.32);
    filter: brightness(1.04);
}

.lpb-btn--settings-save:active {
    transform: translateY(0);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.24);
}

.lpb-empty-state {
    padding: 1.75rem 1.25rem;
    text-align: center;
    border-radius: 12px;
    border: 2px dashed #cbd5e1;
    background: var(--lpb-field-bg);
}

.lpb-empty-state__title {
    margin: 0 0 0.35rem;
    font-weight: 600;
    font-size: 0.95rem;
    color: #64748b;
}

.lpb-empty-state .lpb-field-hint {
    margin: 0;
}

.lpb-notice--success {
    border-color: #bbf7d0 !important;
    background: #f0fdf4 !important;
    color: #166534 !important;
}

.lpb-page-flash {
    margin-bottom: 1.25rem;
}

/* Type → категория: аккордеон */
.lpb-type-acc-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem 0.75rem;
    margin: -0.35rem 0 0.85rem;
}

.lpb-type-acc-toolbar button {
    font: inherit;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 0.35rem 0.75rem;
    border-radius: 10px;
    border: 1px solid var(--lpb-field-border);
    background: var(--lpb-surface-muted);
    color: #475569;
    cursor: pointer;
    transition:
        background 0.22s ease,
        border-color 0.22s ease,
        color 0.22s ease,
        transform 0.18s ease;
}

.lpb-type-acc-toolbar button:hover {
    background: var(--lpb-field-bg-hover);
    border-color: #cbd5e1;
    color: var(--lpb-text);
}

.lpb-type-acc-toolbar button:active {
    transform: scale(0.98);
}

.lpb-type-acc {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.lpb-type-acc__item {
    border-radius: 14px;
    border: 1px solid var(--lpb-field-border);
    background: var(--lpb-surface);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
    transition:
        box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1),
        border-color 0.3s ease;
}

.lpb-type-acc__item:has(.lpb-type-acc__head[aria-expanded="true"]) {
    border-color: #cbd5e1;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08);
}

.lpb-type-acc__head {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.65rem 0.85rem;
    padding: 0.85rem 1rem;
    margin: 0;
    border: none;
    background: transparent;
    cursor: pointer;
    text-align: left;
    font: inherit;
    color: inherit;
    transition: background 0.25s ease;
}

.lpb-type-acc__head:hover {
    background: var(--lpb-surface-muted);
}

.lpb-type-acc__head:focus-visible {
    outline: 2px solid var(--lpb-accent);
    outline-offset: 2px;
}

.lpb-type-acc__type {
    flex: 1;
    min-width: 0;
}

.lpb-type-acc__type .lpb-code {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: bottom;
}

.lpb-type-acc__pill {
    flex-shrink: 0;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.28rem 0.65rem;
    border-radius: 999px;
    border: 1px solid var(--lpb-field-border);
    background: var(--lpb-field-bg);
    color: #475569;
    transition:
        background 0.3s ease,
        border-color 0.3s ease,
        color 0.3s ease;
}

.lpb-type-acc__pill--footwear {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.lpb-type-acc__pill--hat {
    background: #faf5ff;
    border-color: #e9d5ff;
    color: #6b21a8;
}

.lpb-type-acc__pill--socks {
    background: #fff7ed;
    border-color: #fed7aa;
    color: #c2410c;
}

.lpb-type-acc__pill--generic {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #64748b;
}

.lpb-type-acc__chev {
    flex-shrink: 0;
    width: 1.5rem;
    height: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: var(--lpb-field-bg);
    color: #64748b;
    font-size: 0.65rem;
    line-height: 1;
    transition:
        transform 0.4s cubic-bezier(0.34, 1.3, 0.64, 1),
        background 0.25s ease,
        color 0.25s ease;
}

.lpb-type-acc__head[aria-expanded="true"] .lpb-type-acc__chev {
    transform: rotate(180deg);
    background: var(--lpb-accent-soft);
    color: var(--lpb-accent);
}

.lpb-type-acc__panel {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.42s cubic-bezier(0.4, 0, 0.2, 1);
}

.lpb-type-acc__panel.is-open {
    grid-template-rows: 1fr;
}

.lpb-type-acc__panel-inner {
    min-height: 0;
    overflow: hidden;
    padding: 0 1rem;
    opacity: 0;
    transform: translateY(-6px);
    transition:
        opacity 0.32s ease 0.05s,
        transform 0.38s cubic-bezier(0.34, 1.15, 0.64, 1) 0.04s;
}

.lpb-type-acc__panel.is-open .lpb-type-acc__panel-inner {
    opacity: 1;
    transform: translateY(0);
}

.lpb-type-acc__panel-body {
    padding-bottom: 1rem;
    padding-top: 0.15rem;
}

.lpb-type-acc__panel-body .lpb-select {
    width: 100%;
}

@media (prefers-reduced-motion: reduce) {
    .lpb-type-acc__panel,
    .lpb-type-acc__panel-inner,
    .lpb-type-acc__chev,
    .lpb-type-acc__item,
    .lpb-type-acc-toolbar button,
    .lpb-settings-section__panel,
    .lpb-settings-section__panel-inner,
    .lpb-settings-section__chev-mark,
    .lpb-settings-section--accordion {
        transition: none;
    }

    .lpb-type-acc__panel-inner,
    .lpb-settings-section__panel-inner {
        transform: none;
    }
}
