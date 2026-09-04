<?php

namespace App\Support;

use App\Models\Category;

/**
 * The catalog tree, read once and walked in memory.
 *
 * Every consumer needs the same two things — the subtree below a category, and
 * the ancestor trail above it — and each was previously computing them from its
 * own copy of the edge list. Loading `(id, parent_id)` once and answering both
 * from it keeps a category page to a single tree query however deep the tree
 * is, and keeps the cycle guard in one place.
 */
class CategoryTree
{
    /** How deep a walk may go before we assume the tree is cyclic. */
    private const MAX_DEPTH = 10;

    /**
     * @param  array<int, int>  $parentByChild  category id => parent id
     * @param  array<int, list<int>>  $childrenByParent  parent id => child ids
     */
    private function __construct(
        private array $parentByChild,
        private array $childrenByParent,
    ) {}

    /**
     * A tree with no edges, for a caller that has established there is nothing
     * to walk and should not pay for the query.
     */
    public static function empty(): self
    {
        return new self([], []);
    }

    /**
     * Load the whole edge list. One query; categories without a parent are
     * omitted because a missing key already means "no parent".
     */
    public static function load(): self
    {
        /** @var array<int, int> $parentByChild */
        $parentByChild = Category::query()
            ->whereNotNull('parent_id')
            ->pluck('parent_id', 'id')
            ->map(fn (mixed $parentId): int => (int) $parentId)
            ->all();

        $childrenByParent = [];

        foreach ($parentByChild as $id => $parentId) {
            $childrenByParent[$parentId][] = (int) $id;
        }

        return new self($parentByChild, $childrenByParent);
    }

    /**
     * A category id plus every descendant, breadth-first. Ids already seen are
     * skipped, so a cycle terminates instead of looping.
     *
     * @return list<int>
     */
    public function subtreeIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $seen = [$categoryId => true];
        $queue = [$categoryId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($this->childrenByParent[$current] ?? [] as $childId) {
                if (isset($seen[$childId])) {
                    continue;
                }

                $seen[$childId] = true;
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * This category's ancestors, nearest parent first. Bounded by MAX_DEPTH so
     * a cyclic tree cannot spin here.
     *
     * @return list<int>
     */
    public function ancestorIds(int $categoryId): array
    {
        $ancestorIds = [];
        $current = $this->parentByChild[$categoryId] ?? null;
        $depth = 0;

        while ($current !== null && $depth < self::MAX_DEPTH) {
            $ancestorIds[] = $current;
            $current = $this->parentByChild[$current] ?? null;
            $depth++;
        }

        return $ancestorIds;
    }

    /**
     * Roll per-category totals up through each subtree.
     *
     * @param  array<int, int>  $counts  category id => count for that category alone
     */
    public function subtreeCount(int $categoryId, array $counts): int
    {
        $total = 0;

        foreach ($this->subtreeIds($categoryId) as $id) {
            $total += $counts[$id] ?? 0;
        }

        return $total;
    }
}
