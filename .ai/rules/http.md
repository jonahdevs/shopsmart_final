---
paths:
  - 'app/Http/Middleware/TrackVisitor.php, app/Models/Visitor.php, app/Jobs/RecordVisit.php, app/Support/Http/**'
---

# Http

## Visitor counting is gated on analytics consent, once per session
TrackVisitor appends to the `web` group and writes ONE `visitors` row per browsing session — never per page view. The `visitor.counted` session flag says a visit is already counted; the `visitor` cookie is the browser's pseudonymous id and its absence is what `is_new` means (no existence query).

The gate is App\Support\Consent with ConsentCategory::Analytics — the same object AnalyticsTags asks. Without granted analytics there is no row AND no cookie: the cookie is itself the tracking. Never add a second switch; unticking Analytics on the privacy screen turns this off for everyone, and the screen declares it beside the Google/Meta tags.

This is why the storefront query budgets did not move: tests send no consent cookie, so nothing dispatches. Consent reads PrivacyConfig, which the document head already caches, so a consenting request costs no extra query either. The consent check is last in the `counts()` chain so /admin and /up never reach it.

Country comes only from an edge header on a request from a trusted proxy — otherwise null. Do not trust the header unconditionally; a visitor could type their own country into the store's analytics.
