<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

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

@if(! empty($jsonLd))
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif

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



@php
    /*
       Only `app.js` is unconditional. A view that draws music pushes its own
       entry point onto the `page-bundles` stack — one path per push — and it is
       merged in here so a page still makes a single @vite() call: two calls
       would emit the Vite dev client twice while `npm run dev` is running.

       On a wire:navigate the new page's <script src> lands in the head and
       Livewire waits for it to finish loading before it initialises Alpine on
       the swapped-in body, so a bundle that registers Alpine.data() is always
       registered before the component that needs it is processed.
    */
    $pageBundles = collect(preg_split('/\R/', $__env->yieldPushContent('page-bundles')))
        ->map(trim(...))
        ->filter()
        ->unique()
        ->values()
        ->all();
@endphp

@vite(array_merge(['resources/js/app.js'], $pageBundles))
@fluxAppearance
