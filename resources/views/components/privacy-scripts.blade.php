{{--
    Consent configuration for the storefront banner. Always present; it carries
    no tag ids, only which categories are asked about and what this visitor has
    already answered. resources/js/lib/consent.ts reads it.
--}}
<script type="application/json" id="consent-config">@json($config)</script>

@if (isset($tags['ga4']) || isset($tags['gtm']))
    {{--
        Consent Mode v2 defaults are pushed before anything Google loads, so a
        container reached on analytics consent alone still has advertising
        storage denied inside it.
    --}}
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('consent', 'default', @json($googleConsent));
    </script>
@endif

@isset($tags['ga4'])
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($tags['ga4']) }}"></script>
    <script>
        gtag('js', new Date());
        gtag('config', @json($tags['ga4']));
    </script>
@endisset

@isset($tags['gtm'])
    <script>
        (function (w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(i) + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', @json($tags['gtm']));
    </script>
@endisset

@isset($tags['metaPixel'])
    <script>
        !(function (f, b, e, v, n, t, s) {
            if (f.fbq) return;
            n = f.fbq = function () {
                n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
            };
            if (!f._fbq) f._fbq = n;
            n.push = n;
            n.loaded = true;
            n.version = '2.0';
            n.queue = [];
            t = b.createElement(e);
            t.async = true;
            t.src = v;
            s = b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t, s);
        })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', @json($tags['metaPixel']));
        fbq('track', 'PageView');
    </script>
@endisset
