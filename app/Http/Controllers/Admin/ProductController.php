<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminCategoryOptionData;
use App\Data\AdminProductFormData;
use App\Data\AdminProductRowData;
use App\Data\PaginationData;
use App\Enums\ProductLinkType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductIndexRequest;
use App\Http\Requests\Admin\ProductMediaRequest;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The catalog's merchandise, as staff maintain it.
 *
 * Reading is separated from writing: `products.view` gets the table,
 * `products.manage` is required to create, edit or remove anything — a Support
 * role holds the first and not the second, so it can look a product up for a
 * customer without being able to reprice it.
 *
 * Money crosses this controller in major units and is stored in cents.
 * {@see ProductRequest} owns that conversion; nothing here multiplies or
 * divides by 100.
 *
 * A product is never destroyed, only soft-deleted. Order history does not
 * depend on that — `order_items` snapshot the name, SKU and price that were
 * actually sold, precisely so the catalog is free to change — but a product
 * pulled by mistake is a product staff need back, and `restore` is how.
 */
class ProductController extends Controller
{
    use BuildsLikeQueries;

    /** Rows per page in the products table. */
    private const PER_PAGE = 25;

    public function index(ProductIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'created_at';
        $direction = $request->validated('direction') ?? 'desc';
        $trashed = $request->validated('trashed');

        $products = Product::query()
            ->with(['brand:id,name', 'primaryCategory:id,name'])
            // An aggregate rather than a loaded relation: this page shows 25
            // products, and hydrating every variant of each to print "6
            // variants" would be the one query here that grows with the
            // catalog.
            ->withCount('variants')
            ->when($trashed === 'with', fn (Builder $query) => $query->withTrashed())
            ->when($trashed === 'only', fn (Builder $query) => $query->onlyTrashed())
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->tap(fn (Builder $query) => $this->applySort($query, is_string($sort) ? $sort : 'created_at', $direction === 'asc' ? 'asc' : 'desc'))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/products/Index', [
            'products' => array_values(array_map(
                fn (Product $product): AdminProductRowData => AdminProductRowData::fromModel($product),
                $products->items(),
            )),
            'pagination' => PaginationData::fromPaginator($products),
            'filters' => [
                'search' => $request->validated('search'),
                'status' => $request->validated('status'),
                'visibility' => $request->validated('visibility'),
                'stock_status' => $request->validated('stock_status'),
                'category' => $request->validated('category'),
                'brand' => $request->validated('brand'),
                'trashed' => $trashed,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusOptions' => ProductStatus::options(),
            'visibilityOptions' => ProductVisibility::options(),
            'stockStatusOptions' => StockStatus::options(),
            'categoryOptions' => $this->categoryOptions(),
            'brandOptions' => $this->brandOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/Form', [
            'product' => AdminProductFormData::blank(),
            ...$this->formOptions(null),
        ]);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('admin/products/Form', [
            'product' => AdminProductFormData::fromModel($this->loadForEditing($product)),
            ...$this->formOptions($product),
        ]);
    }

    public function store(ProductStoreRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request): Product {
            $product = Product::query()->create($request->productAttributes());

            $this->syncRelations($product, $request);

            return $product;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        // Straight to the editor rather than back to the table: images and
        // variants can only be attached to a product that exists, so the
        // create form is never the last step of creating a product.
        return to_route('admin.products.edit', $product);
    }

    public function update(ProductUpdateRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $product->update($request->productAttributes());

            $this->syncRelations($product, $request);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product saved.')]);

        return back();
    }

    /**
     * Soft-delete. The product leaves every storefront listing immediately —
     * the scopes read the default query, which excludes trashed rows — while
     * the row itself stays for `restore`, and the orders that sold it are
     * untouched either way.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product moved to the bin.')]);

        return to_route('admin.products.index');
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product restored.')]);

        return back();
    }

    public function storeMedia(ProductMediaRequest $request, Product $product): RedirectResponse
    {
        foreach ($request->file('images', []) as $image) {
            $product->addMedia($image)->toMediaCollection('images');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Images added.')]);

        return back();
    }

    /**
     * Removing one image.
     *
     * The media row is bound by id, so it has to be checked against the product
     * in the URL — otherwise `/products/a/media/{id of b's image}` would delete
     * somebody else's file.
     */
    public function destroyMedia(Product $product, Media $media): RedirectResponse
    {
        abort_unless(
            $media->model_type === Product::class && (int) $media->model_id === $product->getKey(),
            404,
        );

        $media->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image removed.')]);

        return back();
    }

    /**
     * Narrow the table by the filter bar.
     *
     * @param  Builder<Product>  $query
     */
    private function applyFilters(Builder $query, ProductIndexRequest $request): void
    {
        $search = $request->validated('search');

        if (is_string($search) && trim($search) !== '') {
            $pattern = $this->containsPattern(trim($search));

            $query->where(function (Builder $match) use ($pattern): void {
                $match
                    ->whereRaw($this->likeExpression('name'), [$pattern])
                    ->orWhereRaw($this->likeExpression('sku'), [$pattern])
                    ->orWhereRaw($this->likeExpression('model_number'), [$pattern]);
            });
        }

        $query
            ->when($request->validated('status'), fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($request->validated('visibility'), fn (Builder $q, string $visibility) => $q->where('visibility', $visibility))
            ->when($request->validated('stock_status'), fn (Builder $q, string $stock) => $q->where('stock_status', $stock))
            ->when($request->validated('brand'), fn (Builder $q, mixed $brandId) => $q->where('brand_id', (int) $brandId));

        $categoryId = $request->validated('category');

        if (is_numeric($categoryId)) {
            $categoryId = (int) $categoryId;

            // Both membership routes, never one alone: the seeder keeps the
            // primary column and the pivot in step, but an import may set only
            // one, and a filter that reads half of them hides products that are
            // filed here.
            $query->where(function (Builder $filed) use ($categoryId): void {
                $filed
                    ->where('primary_category_id', $categoryId)
                    ->orWhereHas('categories', fn (Builder $pivot) => $pivot->whereKey($categoryId));
            });
        }
    }

    /**
     * @param  Builder<Product>  $query
     * @param  'asc'|'desc'  $direction
     */
    private function applySort(Builder $query, string $sort, string $direction): void
    {
        if ($sort === 'price') {
            // The effective price, so a discounted product sorts where the
            // shopper sees it, with price-on-application rows always last.
            // Both branches are spelled out so the expression stays a literal
            // string rather than one assembled from a query parameter.
            $direction === 'asc'
                ? $query->orderByRaw('COALESCE(sale_price, price) IS NULL, COALESCE(sale_price, price) ASC')
                : $query->orderByRaw('COALESCE(sale_price, price) IS NULL, COALESCE(sale_price, price) DESC');
        } else {
            $query->orderBy($sort, $direction);
        }

        // A stable tiebreak, so page 2 never repeats a row from page 1 when the
        // sort column holds duplicates — which `sort_order` and `status` both do
        // across most of the catalog.
        $query->orderByDesc('id');
    }

    /**
     * Everything the editor's pickers offer.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?Product $product): array
    {
        return [
            'statusOptions' => ProductStatus::options(),
            'visibilityOptions' => ProductVisibility::options(),
            'typeOptions' => ProductType::options(),
            'stockStatusOptions' => StockStatus::options(),
            'linkTypeOptions' => ProductLinkType::options(),
            'categoryOptions' => $this->categoryOptions(),
            'brandOptions' => $this->brandOptions(),
            'taxClassOptions' => array_values(TaxClass::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (TaxClass $class): array => ['value' => (int) $class->getKey(), 'label' => $class->name])
                ->all()),
            'attributeGroups' => $this->attributeGroups(),
            'linkableProducts' => $this->linkableProducts($product),
        ];
    }

    /**
     * The category tree flattened depth-first, so a plain `<select>` can indent
     * its options and still read as the tree it describes.
     *
     * @return list<AdminCategoryOptionData>
     */
    private function categoryOptions(): array
    {
        return AdminCategoryOptionData::tree(
            Category::query()->orderBy('sort_order')->orderBy('name')->get(),
        );
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function brandOptions(): array
    {
        return array_values(Brand::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $brand): array => ['value' => (int) $brand->getKey(), 'label' => $brand->name])
            ->all());
    }

    /**
     * Attribute values grouped by their attribute, for the variant editor's
     * option picker. Only active attributes: an inactive one is not an axis the
     * store is selling on any more.
     *
     * @return list<array{label: string, options: list<array{value: int, label: string}>}>
     */
    private function attributeGroups(): array
    {
        return array_values(Attribute::query()
            ->active()
            ->with('values')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Attribute $attribute): array => [
                'label' => $attribute->name,
                'options' => array_values($attribute->values
                    ->map(fn (AttributeValue $value): array => ['value' => (int) $value->getKey(), 'label' => $value->label])
                    ->all()),
            ])
            ->all());
    }

    /**
     * The products a link row may point at. The product being edited is left
     * out — a tile linking to the page it is already on is not a
     * recommendation, and the request refuses one anyway.
     *
     * @return list<array{value: int, label: string}>
     */
    private function linkableProducts(?Product $product): array
    {
        return array_values(Product::query()
            ->when($product !== null, fn (Builder $query) => $query->whereKeyNot($product?->getKey()))
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $linkable): array => [
                'value' => (int) $linkable->getKey(),
                'label' => $linkable->sku === null ? $linkable->name : $linkable->name.' ('.$linkable->sku.')',
            ])
            ->all());
    }

    /** Everything {@see AdminProductFormData::fromModel()} reads, in one pass. */
    private function loadForEditing(Product $product): Product
    {
        return $product->load([
            'categories:id',
            'variants.attributeValues:id',
            'links.linkedProduct:id,name',
            'tags',
            'media',
        ]);
    }

    /**
     * Everything that hangs off a product and is edited alongside it.
     *
     * Runs inside the caller's transaction: a product saved with half its
     * variants is a product whose pricing is wrong, which is worse than a save
     * that failed outright.
     */
    private function syncRelations(Product $product, ProductRequest $request): void
    {
        $product->categories()->sync($request->categoryIds());
        $product->syncTags($request->tagNames());

        $this->syncVariants($product, $request);
        $this->syncLinks($product, $request);
    }

    /**
     * Variants are matched by id, so editing one keeps its row — and its place
     * in any cart line that captured it. A row the staff member removed is
     * soft-deleted rather than erased: `product_variants` keeps its SKU under
     * a unique index, and a hard delete would let the SKU be reissued to
     * something else while old carts still name it.
     */
    private function syncVariants(Product $product, ProductRequest $request): void
    {
        $keptIds = [];

        foreach ($request->variantRows() as $row) {
            $attributeValueIds = $row['attribute_value_ids'];
            $variantId = $row['id'];

            unset($row['attribute_value_ids'], $row['id']);

            $variant = $variantId === null
                ? null
                : $product->variants()->whereKey($variantId)->first();

            if ($variant instanceof ProductVariant) {
                $variant->update($row);
            } else {
                $variant = $product->variants()->create($row);
            }

            $variant->attributeValues()->sync($attributeValueIds);

            $keptIds[] = $variant->getKey();
        }

        $product->variants()
            ->when($keptIds !== [], fn (Builder $query) => $query->whereKeyNot($keptIds))
            ->get()
            ->each(fn (ProductVariant $variant) => $variant->delete());

        // A product cannot default to a variant that is no longer on it.
        if ($product->default_variant_id !== null && ! in_array($product->default_variant_id, $keptIds, true)) {
            $product->update(['default_variant_id' => null]);
        }
    }

    /**
     * Links are replaced wholesale rather than matched by id: a link row holds
     * no state of its own worth preserving — it is a pointer, a type and an
     * order — and `product_links` carries a unique index on (product, linked,
     * type) that a diff would have to work around for no gain.
     */
    private function syncLinks(Product $product, ProductRequest $request): void
    {
        $product->links()->delete();

        foreach ($request->linkRows() as $row) {
            $product->links()->create($row);
        }
    }
}
