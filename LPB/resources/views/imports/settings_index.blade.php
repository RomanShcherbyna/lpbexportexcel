@extends('layouts.import')

@section('title', 'Бренды')

@section('content')
    <h1 class="lpb-page-title">Настройки брендов</h1>

    <div class="lpb-brand-grid">
        @foreach ($suppliers as $s)
            @php
                $n = trim($s['name']);
                $initials = mb_strtoupper(mb_substr($n, 0, 2));
                if (mb_strlen($initials) < 2) {
                    $initials = mb_strtoupper(mb_substr($s['code'], 0, 2));
                }
            @endphp
            <a class="lpb-brand-card" href="{{ route('settings.brands.show', ['supplier' => $s['code']]) }}">
                <span class="lpb-brand-card__icon" aria-hidden="true">{{ $initials }}</span>
                <span class="lpb-brand-card__body">
                    <span class="lpb-brand-card__name">{{ $s['name'] }}</span>
                    <span class="lpb-brand-card__code">{{ $s['code'] }}</span>
                </span>
                <span class="lpb-brand-card__arrow" aria-hidden="true">→</span>
            </a>
        @endforeach
    </div>

    <div class="lpb-back-row">
        <a class="lpb-btn-ghost" href="{{ route('imports.products') }}" style="margin-top:0;">← К импорту</a>
    </div>
@endsection
