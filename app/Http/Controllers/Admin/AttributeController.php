<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminAttributeFormData;
use App\Data\AdminAttributeRowData;
use App\Data\PaginationData;
use App\Enums\AttributeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttributeIndexRequest;
use App\Http\Requests\Admin\AttributeRequest;
use App\Http\Requests\Admin\AttributeStoreRequest;
use App\Http\Requests\Admin\AttributeUpdateRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The axes a product varies on — Size, Colour — and the values along each.
 *
 * Values are edited on the attribute's own form rather than through routes of
 * their own: a value has no meaning apart from its attribute, and splitting
 * them would let "Colour" and its swatches be saved in two steps that fail
 * separately. One form, one transaction.
 *
 * Deleting is guarded rather than cascaded. Every foreign key pointing at an
 * attribute cascades — `product_attributes`, `attribute_values`, and through
 * those the `attribute_value_product_variant` rows that *define* each variant —
 * so removing an attribute a product uses would quietly unpick every variant
 * built on it. An attribute in use is refused, and so is a value that still
 * defines a variant.
 */
class AttributeController extends Controller
{
    use BuildsLikeQueries;

    /** Rows per page in the attributes table. */
    private const PER_PAGE = 25;

    public function index(AttributeIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'sort_order';
        $direction = $request->validated('direction') ?? 'asc';

        $attributes = Attribute::query()
            ->withCount(['values', 'productAttributes'])
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy(is_string($sort) ? $sort : 'sort_order', $direction === 'desc' ? 'desc' : 'asc')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/attributes/Index', [
            'attributes' => array_values(array_map(
                fn (Attribute $attribute): AdminAttributeRowData => AdminAttributeRowData::fromModel($attribute),
                $attributes->items(),
            )),
            'pagination' => PaginationData::fromPaginator($attributes),
            'filters' => [
                'search' => $request->validated('search'),
                'type' => $request->validated('type'),
                'active' => $request->validated('active'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'typeOptions' => AttributeType::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/attributes/Form', [
            'attribute' => AdminAttributeFormData::blank(),
            'typeOptions' => AttributeType::options(),
        ]);
    }

    public function edit(Attribute $attribute): Response
    {
        return Inertia::render('admin/attributes/Form', [
            'attribute' => AdminAttributeFormData::fromModel($this->loadForEditing($attribute)),
            'typeOptions' => AttributeType::options(),
        ]);
    }

    public function store(AttributeStoreRequest $request): RedirectResponse
    {
        $attribute = DB::transaction(function () use ($request): Attribute {
            $attribute = Attribute::query()->create($request->attributeAttributes());

            $this->syncValues($attribute, $request);

            return $attribute;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attribute created.')]);

        return to_route('admin.attributes.edit', $attribute);
    }

    public function update(AttributeUpdateRequest $request, Attribute $attribute): RedirectResponse
    {
        $removed = $this->valuesInUseThatWouldBeRemoved($attribute, $request);

        if ($removed !== []) {
            return back()->withErrors([
                'values' => __('These values still define product variants and cannot be removed: :values', [
                    'values' => implode(', ', $removed),
                ]),
            ]);
        }

        DB::transaction(function () use ($request, $attribute): void {
            $attribute->update($request->attributeAttributes());

            $this->syncValues($attribute, $request);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attribute saved.')]);

        return back();
    }

    public function destroy(Attribute $attribute): RedirectResponse
    {
        if ($attribute->productAttributes()->exists()) {
            return back()->withErrors([
                'attribute' => __('This attribute is still used by products. Remove it from them before deleting it.'),
            ]);
        }

        $attribute->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attribute deleted.')]);

        return to_route('admin.attributes.index');
    }

    /**
     * @param  Builder<Attribute>  $query
     */
    private function applyFilters(Builder $query, AttributeIndexRequest $request): void
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

        $query->when(
            $request->validated('type'),
            fn (Builder $q, string $type) => $q->where('type', $type),
        );

        $active = $request->validated('active');

        if ($active !== null) {
            $query->where('is_active', $active === '1');
        }
    }

    private function loadForEditing(Attribute $attribute): Attribute
    {
        $attribute->load(['values' => fn ($query) => $query->withCount('variants')]);

        return $attribute;
    }

    /**
     * The labels of values the payload drops that still define a variant.
     *
     * Checked before anything is written, so the refusal is a validation error
     * on a form that still holds what the staff member typed rather than a
     * half-applied save.
     *
     * @return list<string>
     */
    private function valuesInUseThatWouldBeRemoved(Attribute $attribute, AttributeRequest $request): array
    {
        $keptIds = [];

        foreach ($request->valueRows() as $row) {
            if ($row['id'] !== null) {
                $keptIds[] = $row['id'];
            }
        }

        return array_values($attribute->values()
            ->when($keptIds !== [], fn (Builder $query) => $query->whereKeyNot($keptIds))
            ->has('variants')
            ->pluck('label')
            ->map(strval(...))
            ->all());
    }

    /**
     * Values are matched by id so editing one keeps its row — and every variant
     * that points at it. A row the staff member removed is deleted outright:
     * unlike a variant, a value holds nothing a shopper ever captured, and
     * {@see self::valuesInUseThatWouldBeRemoved()} has already refused the ones
     * that still define a variant.
     */
    private function syncValues(Attribute $attribute, AttributeRequest $request): void
    {
        $keptIds = [];

        foreach ($request->valueRows() as $row) {
            $valueId = $row['id'];
            unset($row['id']);

            $value = $valueId === null
                ? null
                : $attribute->values()->whereKey($valueId)->first();

            if ($value instanceof AttributeValue) {
                $value->update($row);
            } else {
                $value = $attribute->values()->create($row);
            }

            $keptIds[] = $value->getKey();
        }

        $attribute->values()
            ->when($keptIds !== [], fn (Builder $query) => $query->whereKeyNot($keptIds))
            ->delete();
    }
}
