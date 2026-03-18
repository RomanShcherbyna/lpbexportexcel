<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Товар {{ $product->id }}</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 24px; }
        .wrap { max-width: 1100px; }
        .box { border: 1px solid #ddd; background: #fff; border-radius: 12px; padding: 12px; }
        .muted { color: #666; font-size: 13px; }
        .top { display: grid; grid-template-columns: 160px 1fr; gap: 14px; align-items: start; }
        .thumb { width: 160px; height: 160px; background:#f4f4f4; border-radius: 12px; overflow:hidden; display:flex; align-items:center; justify-content:center; }
        .thumb img { width:160px; height:160px; object-fit:cover; display:block; }
        .title { font-size: 18px; font-weight: 800; margin-bottom: 8px; }
        .meta { display:flex; flex-wrap:wrap; gap:8px; margin-bottom: 10px; }
        .pill { display:inline-block; padding: 2px 8px; border-radius: 999px; background:#f2f2f2; font-size: 12px; }
        a { color:#0b57d0; text-decoration:none; }
        a:hover { text-decoration:underline; }
        .tabs { display:flex; flex-wrap:wrap; gap:8px; margin-top: 12px; }
        .tab { padding: 7px 10px; border: 1px solid #ddd; border-radius: 10px; background:#fafafa; }
        .tab.active { background:#eaf2ff; border-color:#b7cffc; }
        table { width:100%; border-collapse: collapse; margin-top: 10px; }
        th,td { border:1px solid #eee; padding:6px 8px; font-size: 13px; vertical-align: top; }
        th { background:#fafafa; text-align:left; width: 240px; }
        pre { white-space: pre-wrap; background:#fafafa; border:1px solid #eee; padding:10px; border-radius: 10px; font-size: 12px; }
        .back { display:inline-block; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="wrap">
    <a class="back" href="{{ url('/test') }}?inventory_id={{ $product->inventory_id }}@if(!empty($product->category_id))&category_id={{ $product->category_id }}@endif">← Назад</a>

    <div class="box">
        <div class="top">
            <div class="thumb">
                @if(!empty($product->image))
                    <img src="{{ $product->image }}" alt="">
                @else
                    <span class="muted">no photo</span>
                @endif
            </div>
            <div>
                <div class="title">{{ $product->name }}</div>
                <div class="meta">
                    <span class="pill">inventory_id: {{ $product->inventory_id }}</span>
                    <span class="pill">id: {{ $product->id }}</span>
                    @if(!empty($product->sku)) <span class="pill">SKU: {{ $product->sku }}</span> @endif
                    @if(!empty($product->ean)) <span class="pill">EAN: {{ $product->ean }}</span> @endif
                    @if($product->price !== null) <span class="pill">Цена: {{ $product->price }}</span> @endif
                    @if($product->stock !== null) <span class="pill">Остаток: {{ $product->stock }}</span> @endif
                    @if(!empty($product->category_id)) <span class="pill">Категория: {{ $product->category_id }}</span> @endif
                </div>
                @if($category)
                    <div class="muted">Категория (имя): <strong>{{ $category->name }}</strong></div>
                @endif
            </div>
        </div>

        <div class="tabs">
            @php
                $base = url('/test/products/' . $product->inventory_id . '/' . $product->id);
            @endphp
            <a class="tab @if($tab==='info') active @endif" href="{{ $base }}?tab=info">Инфо</a>
            <a class="tab @if($tab==='media') active @endif" href="{{ $base }}?tab=media">Медиа</a>
            <a class="tab @if($tab==='stock') active @endif" href="{{ $base }}?tab=stock">Склад</a>
            <a class="tab @if($tab==='prices') active @endif" href="{{ $base }}?tab=prices">Цены</a>
            <a class="tab @if($tab==='raw') active @endif" href="{{ $base }}?tab=raw">RAW</a>
        </div>

        @if($tab === 'info')
            <table>
                <tr><th>ID</th><td>{{ $product->id }}</td></tr>
                <tr><th>Parent ID</th><td>{{ $product->parent_id }}</td></tr>
                <tr><th>Название</th><td>{{ $product->name }}</td></tr>
                <tr><th>SKU</th><td><code>{{ $product->sku ?? '' }}</code></td></tr>
                <tr><th>EAN</th><td><code>{{ $product->ean ?? '' }}</code></td></tr>
                <tr><th>Категория</th><td>{{ $product->category_id ?? '' }}</td></tr>
            </table>
        @elseif($tab === 'media')
            @if(!empty($product->image))
                <p class="muted">Основное изображение:</p>
                <div class="thumb" style="width: 320px; height: 320px;">
                    <img src="{{ $product->image }}" alt="" style="width:320px;height:320px;">
                </div>
                <p class="muted" style="margin-top:10px;">URL: <a href="{{ $product->image }}" target="_blank" rel="noreferrer">{{ $product->image }}</a></p>
            @else
                <p class="muted">Изображение отсутствует в данных.</p>
            @endif
        @elseif($tab === 'stock')
            <p class="muted">Склады (как вернул BaseLinker):</p>
            <pre>{{ json_encode($product->stock_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @elseif($tab === 'prices')
            <p class="muted">Цены (по price group):</p>
            <pre>{{ json_encode($product->prices_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @else
            <p class="muted">Сырые поля из нашей БД:</p>
            <pre>{{ json_encode($product->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </div>
</div>
</body>
</html>

