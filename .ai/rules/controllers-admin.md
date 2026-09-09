---
paths:
  - app/Http/Controllers/Admin/ProductController.php
  - 'app/Http/Controllers/Admin/**'
  - app/Http/Controllers/Admin/DashboardController.php
---

# Controllers Admin

## "low_stock" is a pseudo stock_status filter, not a column value
The products index accepts `?stock_status=low_stock` (ProductIndexRequest::LOW_STOCK). It is NOT a StockStatus case — `products.stock_status` has three legal values — but a comparison against InventorySettings::low_stock_threshold, applied in ProductController::applyFilters().

So the index sends `stockStatusFilterOptions()` (four options) while the editor keeps `StockStatus::options()` (three). Do not merge them; a product cannot be saved as "low stock".

The threshold is the store-wide setting only, deliberately matching DashboardController::lowStockCount(). The per-product `low_stock_threshold` override is not read by either. Two admin screens disagreeing about what "low stock" means is a bug staff never reconcile — if the override is ever honoured, honour it in both.

The index tile row is one conditional-sum aggregate over `products` (ProductController::stats()), plus the one-off inventory settings group read. ProductAdminTest caps the whole page at 22 queries; a fifth tile must not become a fifth query.

## Date filtering goes through DateRangePreset + DateRange, never raw from/to
Every admin screen that filters by date takes three query params: `range` (a DateRangePreset key), plus `from`/`to` only when `range=custom`. Compose `App\Http\Requests\Concerns\FiltersByDateRange` into the form request for the rules and `$request->dateRange()`; never hand-roll `'from' => ['nullable','date']`.

The client sends a preset KEY, never the dates it resolves to, so a bookmarked "last 30 days" is still rolling next month. `App\Support\DateRange` is the only place that knows what a preset means; its `start()`/`end()` return copies because Carbon is mutable.

`DateRange::MAX_SPAN_DAYS` (366) is a security bound, not a UI nicety: the window comes from a public query string and every dashboard panel aggregates over it unbounded. An open-ended `from` is measured against today for that check.

Send the resolved window to the client as `AdminDateRangeData` beside the raw `filters` — the URL holds what was asked for, the Data holds what it means today. Index pages set `range`/`from`/`to` in ONE `useIndexTable` form assignment so the deep watcher makes one visit.

## Country shares divide by the placed sessions, not by every session
`visitorCountries()` drops rows with a null country instead of bucketing them as a blank. A session with no country is not a place — it is a session whose country is unknown — and on the map the two would be indistinguishable.

That makes the denominator the PLACED sessions, not `sessionCount`. The platform breakdown beside it divides by every session in the window, so the two panels' shares deliberately answer to different totals: the map states its own. See AdminDashboardCountrySliceData.

The breakdown is uncapped, unlike platforms: a map has as many colours as regions, so there is no six-slot ramp to run out of and no honest "Other" — it is not a place and cannot be shaded.

Country itself is only ever set when the request came through a trusted proxy that resolved it (App\Support\Http\VisitorCountry), so on a developer's machine every real row is null and the panel is empty-stated. VisitorSeeder is what gives the map something to draw locally.
