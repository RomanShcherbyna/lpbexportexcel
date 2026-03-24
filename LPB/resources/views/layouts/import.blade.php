<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="color-scheme" content="light"/>
    <title>@yield('title', 'Import') — LPB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
    <style>@include('imports.partials.styles')</style>
    @stack('head')
</head>
<body class="lpb-ui">
@php
    $lpbLogoPath = (string) config('lpb_ui.logo_path', 'images/logo.png');
    $lpbLogoFull = public_path($lpbLogoPath);
    $lpbHasLogo = is_file($lpbLogoFull);
@endphp
<div class="lpb-shell">
    <header class="lpb-topbar">
        <a href="{{ route('imports.products') }}" class="lpb-brand">
            <span class="lpb-brand-mark {{ $lpbHasLogo ? 'lpb-brand-mark--image' : 'lpb-brand-mark--placeholder' }}" aria-hidden="true">
                @if ($lpbHasLogo)
                    <img src="{{ asset($lpbLogoPath) }}?v={{ filemtime($lpbLogoFull) }}" alt="" width="72" height="72" loading="eager" decoding="async">
                @endif
            </span>
            <span>
                <span class="lpb-brand-text">LPB Converter</span>
            </span>
        </a>
        <nav class="lpb-nav" aria-label="Main">
            <a href="{{ route('imports.products') }}" class="{{ request()->is('imports/*') ? 'is-active' : '' }}">Import</a>
            <a href="{{ route('settings.brands.index') }}" class="{{ request()->routeIs('settings.brands.*') ? 'is-active' : '' }}">Brands</a>
        </nav>
    </header>

    <main class="lpb-card">
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
