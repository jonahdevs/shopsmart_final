<?php

namespace Database\Seeders;

use App\Enums\CategorySection;
use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\CategoryPlacement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds the catalog tree from `database/data/categories.json`, pinning the
 * flagged nodes into the navbar, home page and footer.
 *
 * The reference app stored `image`, `banner` and `icon` path strings on the
 * row. Here the square tile is attached to the medialibrary `image` collection
 * instead. The JSON's `icon` entries are raster `.webp` files and this schema's
 * `icon_svg` column expects inline SVG markup, so those are deliberately
 * dropped rather than mangled into the wrong column.
 */
class CategorySeeder extends Seeder
{
    private int $created = 0;

    private int $missingImages = 0;

    /**
     * Placement order is tracked per location, so the navbar, home page and
     * footer each number their own entries from one.
     *
     * @var array<string, int>
     */
    private array $placementOrders = [];

    public function run(): void
    {
        $path = database_path('data/categories.json');

        if (! File::exists($path)) {
            $this->command->error("categories.json not found at {$path}.");

            return;
        }

        /** @var list<array<string, mixed>>|null $tree */
        $tree = json_decode(File::get($path), true);

        if (! is_array($tree)) {
            $this->command->error('Could not parse categories.json: '.json_last_error_msg());

            return;
        }

        foreach ($tree as $node) {
            $this->seedNode($node, null);
        }

        $this->command->info("Seeded {$this->created} categories from categories.json.");

        if ($this->missingImages > 0) {
            $this->command->warn("{$this->missingImages} category image(s) referenced by the JSON were not found on the public disk and were skipped.");
        }
    }

    /**
     * Create or refresh one node, then recurse into its children.
     *
     * @param  array<string, mixed>  $node
     */
    private function seedNode(array $node, ?int $parentId): void
    {
        /** @var string $name */
        $name = $node['name'];
        $slug = Str::slug(is_string($node['slug'] ?? null) ? $node['slug'] : $name);

        $category = Category::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'parent_id' => $parentId,
                'description' => $node['description'] ?? null,
                'status' => CategoryStatus::Active,
                'sort_order' => $node['sort_order'] ?? 0,
                'meta_title' => $name,
                'meta_description' => $node['description'] ?? null,
            ],
        );

        $this->created++;

        $this->attachImage($category, is_string($node['image'] ?? null) ? $node['image'] : null);

        /** @var list<string> $placements */
        $placements = $node['placements'] ?? [];

        foreach ($placements as $location) {
            $this->pin($category, $location);
        }

        /** @var list<array<string, mixed>> $children */
        $children = $node['children'] ?? [];

        foreach ($children as $child) {
            $this->seedNode($child, $category->id);
        }
    }

    /**
     * Attach the square tile from the public disk. Re-running the seeder leaves
     * an already-populated collection alone so images are not duplicated.
     */
    private function attachImage(Category $category, ?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        if ($category->getMedia('image')->isNotEmpty()) {
            return;
        }

        if (! Storage::disk('public')->exists($relativePath)) {
            $this->missingImages++;

            return;
        }

        $media = $category->addMedia(Storage::disk('public')->path($relativePath))
            ->preservingOriginal()
            ->toMediaCollection('image');

        // Seeders run with model events muted, so medialibrary's HasUuid
        // `creating` hook never fires and the nullable column stays null.
        $media->uuid ??= (string) Str::uuid();
        $media->save();
    }

    /** Pin the category into one storefront location, numbering within it. */
    private function pin(Category $category, string $location): void
    {
        $section = CategorySection::tryFrom($location);

        if ($section === null) {
            $this->command->warn("Unknown category placement \"{$location}\" on \"{$category->name}\"; skipped.");

            return;
        }

        $this->placementOrders[$section->value] = ($this->placementOrders[$section->value] ?? 0) + 1;

        CategoryPlacement::updateOrCreate(
            ['category_id' => $category->id, 'location' => $section],
            ['sort_order' => $this->placementOrders[$section->value], 'status' => CategoryStatus::Active],
        );
    }
}
