<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>BaseLinker тест</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 24px; }
        .wrap { max-width: 1400px; }
        .grid { display: grid; grid-template-columns: 380px 1fr; gap: 18px; }
        .box { border: 1px solid #ddd; background: #fff; border-radius: 10px; padding: 12px; }
        h2, h3 { margin: 8px 0 10px; }
        .muted { color: #666; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #eee; padding: 6px 8px; font-size: 13px; vertical-align: top; }
        th { background: #fafafa; text-align: left; }
        a { color: #0b57d0; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #f2f2f2; font-size: 12px; }
        .scroll { max-height: 74vh; overflow: auto; }
        .filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        input[type="text"] { padding: 6px 8px; width: 260px; }
        .btn { padding: 7px 10px; border: 1px solid #ddd; background: #fafafa; border-radius: 8px; cursor: pointer; }
        .btn:hover { background: #f3f3f3; }
        .cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .card { border: 1px solid #eee; border-radius: 12px; overflow: hidden; background: #fff; }
        .cardTop { display: grid; grid-template-columns: 110px 1fr; gap: 10px; padding: 10px; }
        .thumb { width: 110px; height: 110px; background: #f4f4f4; border-radius: 10px; overflow: hidden; display:flex; align-items:center; justify-content:center; }
        .thumb img { width: 110px; height: 110px; object-fit: cover; display:block; }
        .title { font-weight: 700; line-height: 1.2; margin-bottom: 6px; }
        .meta { display:flex; flex-wrap: wrap; gap: 6px; }
        .meta .pill { background:#f6f6f6; }
        .cardBottom { padding: 10px; border-top: 1px solid #f0f0f0; display:flex; justify-content: space-between; gap:10px; align-items:center; }
        .linkBtn { padding: 6px 10px; border: 1px solid #ddd; border-radius: 10px; background:#fafafa; }
        .linkBtn:hover { background:#f2f2f2; }
        .pager { margin-top: 10px; }
    </style>
</head>
<body>
<div class="wrap">
    <h2>BaseLinker тестовая страница</h2>
    <p class="muted">
        Страница читает данные <strong>из нашей БД</strong>. Синхронизацию запускай командой
        <code>php artisan baselinker:sync</code>.
    </p>

    <div class="grid">
        <div class="box">
            <h3>Категории</h3>
            <p class="muted">Нажми категорию, чтобы увидеть товары.</p>
            <div class="scroll">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Parent</th>
                        <th>Name</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($categories as $c)
                        <tr>
                            <td>
                                <a href="{{ url('/test') }}?inventory_id={{ $c->inventory_id }}&category_id={{ $c->id }}">
                                    {{ $c->id }}
                                </a>
                            </td>
                            <td>{{ $c->parent_id ?? '—' }}</td>
                            <td>{{ $c->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Категории пустые. Запусти sync.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="box">
            <div class="filters">
                <div class="pill">inventory_id: {{ $inventory_id ?? '—' }}</div>
                <div class="pill">category_id: {{ $category_id ?? '—' }}</div>
                @php
                    $baseQuery = request()->query();
                    $baseQuery['inventory_id'] = $inventory_id;
                    $baseQuery['category_id'] = $category_id;
                @endphp
                <a class="btn" href="{{ url('/test') }}">Сбросить фильтры</a>
                <a class="btn" href="{{ url('/test') }}?{{ http_build_query(array_merge($baseQuery, ['view' => 'cards'])) }}">Карточки</a>
                <a class="btn" href="{{ url('/test') }}?{{ http_build_query(array_merge($baseQuery, ['view' => 'table'])) }}">Таблица</a>
            </div>

            <h3 style="margin-top: 12px;">Товары</h3>
            <div class="scroll">
                @if(($view_mode ?? 'cards') === 'table')
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>SKU</th>
                            <th>EAN</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Category</th>
                            <th>Image</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($products as $p)
                            <tr>
                                <td>
                                    <a href="{{ url('/test/products/' . $p->inventory_id . '/' . $p->id) }}">{{ $p->id }}</a>
                                </td>
                                <td><code>{{ $p->sku ?? '' }}</code></td>
                                <td><code>{{ $p->ean ?? '' }}</code></td>
                                <td>{{ $p->name }}</td>
                                <td>{{ $p->price ?? '' }}</td>
                                <td>{{ $p->stock ?? '' }}</td>
                                <td>{{ $p->category_id ?? '' }}</td>
                                <td>
                                    @if(!empty($p->image))
                                        <a href="{{ $p->image }}" target="_blank" rel="noreferrer">open</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="muted">Товары пустые. Запусти sync.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                @else
                    <div class="cards">
                        @forelse($products as $p)
                            <div class="card">
                                <div class="cardTop">
                                    <div class="thumb">
                                        @if(!empty($p->image))
                                            <img src="{{ $p->image }}" alt="">
                                        @else
                                            <span class="muted">no photo</span>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="title">{{ $p->name }}</div>
                                        <div class="meta">
                                            <span class="pill">ID: {{ $p->id }}</span>
                                            @if(!empty($p->sku)) <span class="pill">SKU: {{ $p->sku }}</span> @endif
                                            @if(!empty($p->ean)) <span class="pill">EAN: {{ $p->ean }}</span> @endif
                                            @if($p->price !== null) <span class="pill">Цена: {{ $p->price }}</span> @endif
                                            @if($p->stock !== null) <span class="pill">Остаток: {{ $p->stock }}</span> @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="cardBottom">
                                    <span class="muted">Категория: {{ $p->category_id ?? '—' }}</span>
                                    <a class="linkBtn" href="{{ url('/test/products/' . $p->inventory_id . '/' . $p->id) }}">Открыть карточку</a>
                                </div>
                            </div>
                        @empty
                            <div class="muted">Товары пустые. Запусти sync.</div>
                        @endforelse
                    </div>
                @endif
            </div>
            <div class="pager">
                {{ $products->links() }}
            </div>
        </div>
    </div>
</div>
</body>
</html>

