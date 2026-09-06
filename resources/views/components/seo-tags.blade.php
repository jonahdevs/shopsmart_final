{{--
    The head a crawler actually reads.

    Rendered from the server rather than through Inertia's <Head>, because SSR
    is off: a title set client-side never reaches the initial HTML, so a bot
    that does not run JavaScript would see nothing at all. Pages still use
    <Head> for the browser tab; this is what search engines get.

    `seo` is shared on every page response by HandleInertiaRequests, so a page
    that sets nothing of its own still emits the store's own title, description
    and robots directive.
--}}
{{--
    `page` is passed in explicitly: a Blade component has isolated scope, so the
    root view's own $page is not visible in here and the whole head silently
    rendered empty until it was.
--}}
@props(['page'])

@php
    /** @var array<string, mixed>|null $seo */
    $seo = $page['props']['documentHead'] ?? null;
@endphp

@if ($seo)
    <meta name="robots" content="{{ $seo['robots'] }}">

    @if (! empty($seo['description']))
        <meta name="description" content="{{ $seo['description'] }}">
    @endif

    @if (! empty($seo['canonicalUrl']))
        <link rel="canonical" href="{{ $seo['canonicalUrl'] }}">
    @endif

    {{-- Open Graph, from the same three values, so the social card and the
         search result cannot describe the page differently. --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:url" content="{{ $seo['canonicalUrl'] }}">
    @if (! empty($seo['description']))
        <meta property="og:description" content="{{ $seo['description'] }}">
    @endif

    @foreach ($seo['jsonLd'] ?? [] as $block)
        {{-- JSON_UNESCAPED_SLASHES keeps the schema.org URLs readable;
             JSON_HEX_TAG is what stops a product name containing "</script>"
             from closing this element and turning content into markup. --}}
        <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endforeach
@endif
