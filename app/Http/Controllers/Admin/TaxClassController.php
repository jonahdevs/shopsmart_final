<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminTaxClassFormData;
use App\Data\AdminTaxClassRowData;
use App\Data\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaxClassIndexRequest;
use App\Http\Requests\Admin\TaxClassStoreRequest;
use App\Http\Requests\Admin\TaxClassUpdateRequest;
use App\Models\TaxClass;
use App\Settings\TaxSettings;
use App\Support\CheckoutPricer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * VAT bands.
 *
 * Structure rather than merchandise — the same argument that puts brands,
 * categories and attributes behind `catalog.manage` puts tax classes there.
 * The band a product sits in is a fact about the catalog, not a price.
 *
 * A tax class is the one piece of catalog structure whose deletion cannot be
 * made safe by nulling the reference, so this controller refuses two deletes
 * outright:
 *
 * - A band products point at. `products.tax_class_id` is `nullOnDelete`, so
 *   the database would happily orphan them — and an orphaned product silently
 *   falls back to the store default, which is a different rate. Nobody would
 *   see that happen; they would see the VAT on an order change months later.
 * - The band {@see TaxSettings::$default_tax_class_id} names. That column is a
 *   settings value rather than a foreign key, so nothing at all protects it:
 *   deleting the row leaves a dangling id, {@see CheckoutPricer}
 *   finds nothing, and the whole store quietly charges 0% VAT.
 *
 * Both refusals are `back()->withErrors()` rather than a toast, matching
 * AttributeController — the message has to stay on screen next to the button
 * that was refused, because it is an instruction ("empty this band first"),
 * not a notification.
 */
class TaxClassController extends Controller
{
    use BuildsLikeQueries;

    /** Rows per page in the tax classes table. */
    private const PER_PAGE = 25;

    public function __construct(private TaxSettings $taxSettings) {}

    public function index(TaxClassIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'name';
        $direction = $request->validated('direction') ?? 'asc';
        $defaultId = $this->taxSettings->default_tax_class_id;

        $taxClasses = TaxClass::query()
            // An aggregate, not a loaded relation: this is the number that
            // decides whether the delete is allowed, so it is needed on every
            // row and counting in SQL keeps the page flat as the catalog grows.
            ->withCount('products')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy(is_string($sort) ? $sort : 'name', $direction === 'desc' ? 'desc' : 'asc')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/tax-classes/Index', [
            'taxClasses' => array_values(array_map(
                fn (TaxClass $taxClass): AdminTaxClassRowData => AdminTaxClassRowData::fromModel($taxClass, $defaultId),
                $taxClasses->items(),
            )),
            'pagination' => PaginationData::fromPaginator($taxClasses),
            'filters' => [
                'search' => $request->validated('search'),
                'active' => $request->validated('active'),
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/tax-classes/Form', [
            'taxClass' => AdminTaxClassFormData::blank(),
        ]);
    }

    public function edit(TaxClass $taxClass): Response
    {
        return Inertia::render('admin/tax-classes/Form', [
            'taxClass' => AdminTaxClassFormData::fromModel(
                $taxClass->loadCount('products'),
                $this->taxSettings->default_tax_class_id,
            ),
        ]);
    }

    public function store(TaxClassStoreRequest $request): RedirectResponse
    {
        TaxClass::query()->create($request->taxClassAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax class created.')]);

        return to_route('admin.tax-classes.index');
    }

    public function update(TaxClassUpdateRequest $request, TaxClass $taxClass): RedirectResponse
    {
        $taxClass->update($request->taxClassAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax class saved.')]);

        return to_route('admin.tax-classes.index');
    }

    public function destroy(TaxClass $taxClass): RedirectResponse
    {
        if ($taxClass->products()->exists()) {
            return back()->withErrors([
                'taxClass' => __('Products are still in this tax band. Move them to another band before deleting it.'),
            ]);
        }

        if ($this->taxSettings->default_tax_class_id === $taxClass->getKey()) {
            return back()->withErrors([
                'taxClass' => __('This is the store\'s default tax band. Choose a different default in shipping and tax settings first.'),
            ]);
        }

        $taxClass->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax class deleted.')]);

        return to_route('admin.tax-classes.index');
    }

    /**
     * @param  Builder<TaxClass>  $query
     */
    private function applyFilters(Builder $query, TaxClassIndexRequest $request): void
    {
        $search = $request->validated('search');

        if (is_string($search) && trim($search) !== '') {
            $pattern = $this->containsPattern(trim($search));

            $query->where(function (Builder $match) use ($pattern): void {
                $match
                    ->whereRaw($this->likeExpression('name'), [$pattern])
                    ->orWhereRaw($this->likeExpression('slug'), [$pattern]);
            });
        }

        $active = $request->validated('active');

        if ($active !== null) {
            $query->where('is_active', $active === '1');
        }
    }
}
