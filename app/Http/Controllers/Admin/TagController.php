<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminTagFormData;
use App\Data\AdminTagRowData;
use App\Data\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TagIndexRequest;
use App\Http\Requests\Admin\TagStoreRequest;
use App\Http\Requests\Admin\TagUpdateRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Tags\Tag;

/**
 * Merchandising labels.
 *
 * A tag is catalog *structure* in the same sense a brand is — a bucket products
 * are filed into rather than a piece of merchandise — so it sits under the one
 * `catalog.manage` permission alongside categories, brands, attributes and tax
 * classes rather than growing a permission of its own.
 *
 * Tags are not decoration here: the home page's "Featured" rail and the "New
 * Arrival" badge are both a `whereHas('tags', …)` on the tag's name, and the
 * catalog's `?tag=` filter is the same query. So deleting one silently empties
 * a storefront rail, which is why the table prints the product count next to
 * every row and the editor repeats it before the delete button.
 *
 * The model is the package's, unsubclassed. Its `name` and `slug` are
 * translatable JSON columns, so both the count aggregate and the ordering go
 * through explicit SQL rather than an Eloquent relation the model does not
 * have — {@see self::countSubquery()} and {@see self::sortColumn()}.
 */
class TagController extends Controller
{
    use BuildsLikeQueries;

    /** Rows per page in the tags table. */
    private const PER_PAGE = 25;

    public function index(TagIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'name';
        $direction = $request->validated('direction') === 'desc' ? 'desc' : 'asc';

        $tags = Tag::query()
            ->select('tags.*')
            ->selectSub($this->countSubquery(), 'products_count')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy($this->sortColumn(is_string($sort) ? $sort : 'name'), $direction)
            // A tiebreaker, so two tags sharing a product count do not swap
            // places between pages of the same listing.
            ->orderBy('tags.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/tags/Index', [
            'tags' => array_values(array_map(
                fn (Tag $tag): AdminTagRowData => AdminTagRowData::fromModel($tag),
                $tags->items(),
            )),
            'pagination' => PaginationData::fromPaginator($tags),
            'filters' => [
                'search' => $request->validated('search'),
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/tags/Form', [
            'tag' => AdminTagFormData::blank(),
            'productCount' => 0,
        ]);
    }

    public function edit(Tag $tag): Response
    {
        return Inertia::render('admin/tags/Form', [
            'tag' => AdminTagFormData::fromModel($tag),
            // What a delete would cost, printed beside the button that does it.
            'productCount' => $this->productCount($tag),
        ]);
    }

    public function store(TagStoreRequest $request): RedirectResponse
    {
        // No slug is written. The package generates one from the name in a
        // `saving` hook, which fires here — unlike in a seeder, where model
        // events are muted and TagSeeder has to supply it by hand.
        Tag::create(['name' => $request->tagName()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag created.')]);

        return to_route('admin.tags.index');
    }

    public function update(TagUpdateRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update(['name' => $request->tagName()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag saved.')]);

        return to_route('admin.tags.index');
    }

    /**
     * Delete a tag, and with it every product's membership of it.
     *
     * Not refused when products carry it, unlike a category with children or a
     * tax class in use. Nothing is orphaned: `taggables.tag_id` is
     * `cascadeOnDelete`, the products themselves are untouched, and a tag with
     * no members is exactly as useless as no tag at all. The count is stated on
     * the editor instead, so the cost is read before the click rather than
     * discovered after it.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $count = $this->productCount($tag);
        $tag->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count === 0
                ? __('Tag deleted.')
                : __('Tag deleted. :count products no longer carry it.', ['count' => $count]),
        ]);

        return to_route('admin.tags.index');
    }

    /**
     * The search box, over the tag's name and its generated slug.
     *
     * Not the package's own `containing()` scope: that interpolates the term
     * straight into a LIKE, so a typed `%` there matches every tag — the same
     * fault BrandAdminTest pins down for brands. The escaping rule this
     * application uses is {@see BuildsLikeQueries}, and it has to hold on a
     * translatable column too.
     *
     * `likeExpression()` cannot be used to build the fragment, because its
     * column must be a literal string and a JSON path carries the runtime
     * locale. The grammar wraps that path for whichever driver is connected —
     * `json_unquote(json_extract(…))` on MySQL, `json_extract(…)` on SQLite —
     * and only the escape character is borrowed from the trait, so there is
     * still exactly one definition of it.
     *
     * @param  Builder<Tag>  $query
     */
    private function applyFilters(Builder $query, TagIndexRequest $request): void
    {
        $search = $request->validated('search');

        if (! is_string($search) || trim($search) === '') {
            return;
        }

        $pattern = $this->containsPattern(trim($search));
        $grammar = $query->getQuery()->getGrammar();
        $locale = Tag::getLocale();
        $suffix = " LIKE ? ESCAPE '".self::LIKE_ESCAPE."'";

        $name = $grammar->wrap('name->'.$locale).$suffix;
        $slug = $grammar->wrap('slug->'.$locale).$suffix;

        $query->where(function (Builder $match) use ($name, $slug, $pattern): void {
            $match
                ->whereRaw($name, [$pattern])
                ->orWhereRaw($slug, [$pattern]);
        });
    }

    /**
     * The correlated count of products carrying each tag.
     *
     * Scoped to products rather than counting every taggable: `taggables` is a
     * morph table and nothing stops a second model taking `HasTags` later, at
     * which point an unscoped count would quietly start reporting rows this
     * screen cannot show.
     */
    private function countSubquery(): QueryBuilder
    {
        return DB::table('taggables')
            ->selectRaw('count(*)')
            ->whereColumn('taggables.tag_id', 'tags.id')
            ->where('taggables.taggable_type', (new Product)->getMorphClass());
    }

    private function productCount(Tag $tag): int
    {
        return (int) DB::table('taggables')
            ->where('tag_id', $tag->getKey())
            ->where('taggable_type', (new Product)->getMorphClass())
            ->count();
    }

    /**
     * The whitelisted sort key, as a column an `orderBy` can take.
     *
     * `name` is a translation inside a JSON document, so ordering on the bare
     * column would sort by the serialised map — `{"en":"Clearance"}` — and put
     * every tag in the same place. `products_count` is the subquery alias.
     */
    private function sortColumn(string $sort): string
    {
        return match ($sort) {
            'products_count' => 'products_count',
            'created_at' => 'tags.created_at',
            default => 'name->'.Tag::getLocale(),
        };
    }
}
