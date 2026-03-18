# Laravel BaseLinker Converter (Phases 1–5)

Strict converter: **supplier Excel → BaseLinker template → XLSX + CSV**.

Critical rules:
- System never invents data (missing stays empty).
- System never creates new columns (exports strictly follow template columns + order).
- System never fills "by meaning" without explicit mapping.

## Requirements

- PHP 8.2+
- Composer
- Laravel
- `maatwebsite/excel`

## Setup (local)

From the project root:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link
```

Install Excel package:

```bash
composer require maatwebsite/excel
```

### Required files and directories

Place your master template at:

- `storage/app/templates/baselinker_master.xlsx`

Ensure these storage directories exist (Laravel normally creates `storage/app` for you):

- `storage/app/templates`
- `storage/app/imports`
- `storage/app/import_mappings`
- `storage/app/normalization_rules`
- `storage/app/supplier_profiles`

Start server:

```bash
php artisan serve
```

Open:

- `http://127.0.0.1:8000/imports/products`

## Extended design (SKU, AI, currencies, categories)

For подробное описание того, **как должны работать**:

- генерация SKU,
- ИИ‑подсказки (категории, PL‑названия, цвета),
- пересчёт валют,
- UI для батч‑генерации и ручного ревью,

см. файл:

- `LPB/PRODUCT_PIPELINE_DESIGN.md`

## Usage (end-to-end flow)

1. Upload supplier Excel file (e.g. `B2B - SO25-03660.xlsx`)
2. Select supplier (Phase 1: **B2B**)
3. Click **Next: Review mapping**
4. On the mapping page, review:
   - supplier columns
   - saved mapping (if any)
   - AI mapping suggestions (if `OPENAI_API_KEY` is set)
   - final mapping dropdowns
5. Confirm mapping & continue to normalization suggestions.
6. Optionally accept normalization rules (Gender/Season/Color/Age Size, etc.).
7. On the result page review:
   - summary
   - mapping preview
   - deterministic validation errors/warnings
   - AI data-checker warnings (if AI enabled)
   - output preview
8. Download:
   - `output.xlsx`
   - `output.csv` (UTF-8, delimiter `;`)

## Environment variables

In `.env` you should define:

- `OPENAI_API_KEY` – optional, for AI mapping/checker/normalizer (leave empty to disable AI)
- `OPENAI_MODEL` – optional, e.g. `gpt-4o-mini` (default is used if missing)

If AI is disabled or unavailable, the app still works; AI sections will show a clear “unavailable” message.

## Where to edit supplier mapping

- `config/suppliers/b2b.php`

To add a new supplier, create a new file in `config/suppliers/` with the same structure:

- `supplier_code`
- `supplier_name`
- `sheet` (null or 0-based index)
- `column_mapping` (**explicit** source header => template header)

## Storage of results and rules

Each conversion creates a job directory:

- `storage/app/imports/{job_id}/`
  - `preview.json`
  - `output.xlsx` (if selected)
  - `output.csv` (if selected)

Mappings confirmed via the UI are stored per supplier:

- `storage/app/import_mappings/{supplier_code}.json`

Normalization rules confirmed via the UI are stored per supplier:

- `storage/app/normalization_rules/{supplier_code}.json`

Supplier profiles (combined mapping + normalization + filters + defaults) are stored per supplier:

- `storage/app/supplier_profiles/{supplier_code}.json`


