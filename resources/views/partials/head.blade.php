<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php
    $pageTitle = ($title ?? null) ? config('app.name') . ' – ' . $title : config('app.name');
    $pageDescription = $description ?? null;
    $canonicalUrl = $canonical ?? request()->url();
@endphp

<title>{{ $pageTitle }}</title>
<meta name="robots" content="{{ $noindex ?? false ? 'noindex, nofollow' : 'index, follow' }}" />
@if ($pageDescription)
<meta name="description" content="{{ $pageDescription }}" />
@endif
<link rel="canonical" href="{{ $canonicalUrl }}" />

{{-- Open Graph --}}
<meta property="og:site_name" content="{{ config('app.name') }}" />
<meta property="og:locale" content="hu_HU" />
<meta property="og:type" content="{{ $ogType ?? 'website' }}" />
<meta property="og:title" content="{{ $pageTitle }}" />
@if ($pageDescription)
<meta property="og:description" content="{{ $pageDescription }}" />
@endif
<meta property="og:url" content="{{ $canonicalUrl }}" />
<meta property="og:image" content="{{ $ogImage ?? asset('apple-touch-icon.png') }}" />

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary" />
<meta name="twitter:title" content="{{ $pageTitle }}" />
@if ($pageDescription)
<meta name="twitter:description" content="{{ $pageDescription }}" />
@endif

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<!--link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" /-->

    <link href="https://fonts.bunny.net/css?family=inter:400,400i,500,500i,600,600i,700&display=swap" rel="stylesheet" />


@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
