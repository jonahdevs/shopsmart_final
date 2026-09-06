<?php

namespace App\Data;

use App\Models\Category;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One entry in a category picker.
 *
 * `depth` is carried so a flat `<select>` can indent its options and read as
 * the tree it describes — an option list is the one place a category's position
 * cannot be inferred from anything else on screen.
 *
 * The parent picker on the category editor is fed the same list with the
 * category's own subtree already removed, which is what keeps a cycle from
 * being offerable in the first place. The server refuses one regardless.
 */
#[TypeScript]
class AdminCategoryOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public int $depth,
    ) {}

    /**
     * Flatten an already-loaded set of categories depth-first, parents before
     * their children.
     *
     * Takes a collection rather than running its own query so a caller that
     * needs the tree twice on one page pays for it once, and so the ordering
     * within a level is whatever the caller sorted by — `sort_order` then name,
     * everywhere this is used.
     *
     * A category whose parent is missing from the set (filtered out, or the row
     * is gone) is treated as a root rather than dropped: an option list that
     * silently omits a category would make it unpickable with no explanation.
     * `$skipSubtreeOf` is the parent picker's exclusion — a category may not be
     * offered itself or its own descendants as a parent.
     *
     * @param  Collection<int, Category>  $categories
     * @return list<self>
     */
    public static function tree(Collection $categories, ?int $skipSubtreeOf = null): array
    {
        $childrenByParent = [];
        $ids = [];

        foreach ($categories as $category) {
            $ids[(int) $category->getKey()] = true;
        }

        foreach ($categories as $category) {
            $parentId = $category->parent_id;
            $key = $parentId !== null && isset($ids[$parentId]) ? $parentId : 0;
            $childrenByParent[$key][] = $category;
        }

        $options = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$options, $childrenByParent, $skipSubtreeOf): void {
            foreach ($childrenByParent[$parentId] ?? [] as $category) {
                $id = (int) $category->getKey();

                if ($id === $skipSubtreeOf) {
                    continue;
                }

                $options[] = new self(id: $id, name: $category->name, depth: $depth);

                $walk($id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $options;
    }
}
