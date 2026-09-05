<?php

namespace Database\Seeders;

use App\Enums\ProductLinkType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Enums\StockStatus;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductLink;
use App\Models\ProductVariant;
use App\Models\TaxClass;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The catalog itself, built from `database/data/products.json`.
 *
 * Prices in that file are major units (KES 1298.7); every monetary column here
 * is integer cents, so amounts go through {@see Money::toMinor()}.
 *
 * The source data describes simple products only. To exercise the whole schema
 * the seeder promotes roughly one product in seven to a variable product with
 * its own colour or size variants, discounts one in eight, and derives a
 * plausible web of curated product links from category and price neighbours.
 * All of that is index-driven rather than random, so a re-run reproduces
 * exactly the same catalog.
 */
class ProductSeeder extends Seeder
{
    /** Every nth product in the source file becomes a variable product. */
    private const VARIABLE_EVERY_NTH = 7;

    /** Every nth product in the source file gets a sale price. */
    private const SALE_EVERY_NTH = 8;

    /** Fraction knocked off the list price of a discounted product. */
    private const SALE_DISCOUNT = 0.15;

    /** Units remaining before the admin flags a product as low on stock. */
    private const LOW_STOCK_THRESHOLD = 5;

    /** Minimum products a category needs before it is worth cross-linking. */
    private const MIN_CATEGORY_SIZE_FOR_LINKS = 3;

    /** @var array<string, int> brand name => brand id */
    private array $brandIdByName = [];

    /** @var array<string, int> lowercased category name => category id */
    private array $categoryIdByName = [];

    /** @var array<string, array{id: int, values: list<array{id: int, slug: string}>}> attribute slug => axis */
    private array $variationAxes = [];

    /** @var array<int, list<array{id: int, price: int}>> category id => its products, in creation order */
    private array $catalogByCategory = [];

    /** @var list<array<string, mixed>> pending product_links rows, written in one batch */
    private array $linkRows = [];

    private ?int $defaultTaxClassId = null;

    private int $missingImages = 0;

    private int $variableProducts = 0;

    private int $variants = 0;

    public function __construct(private readonly Money $money) {}

    public function run(): void
    {
        $path = database_path('data/products.json');

        if (! File::exists($path)) {
            $this->command->error("products.json not found at {$path}.");

            return;
        }

        /** @var list<array<string, mixed>>|null $rows */
        $rows = json_decode(File::get($path), true);

        if (! is_array($rows)) {
            $this->command->error('Could not parse products.json: '.json_last_error_msg());

            return;
        }

        $this->primeLookups();

        foreach ($rows as $index => $row) {
            $this->seedProduct($row, $index);
        }

        $links = $this->linkRelatedProducts();

        $this->command->info(sprintf(
            'Seeded %d products (%d variable with %d variants) and %d curated product links.',
            count($rows),
            $this->variableProducts,
            $this->variants,
            $links,
        ));

        if ($this->missingImages > 0) {
            $this->command->warn("{$this->missingImages} product image(s) referenced by the JSON were not found on the public disk and were skipped.");
        }
    }

    /**
     * Resolve every foreign key the loop needs up front, so seeding a product
     * costs no lookup queries.
     */
    private function primeLookups(): void
    {
        /** @var array<string, int> $brands */
        $brands = Brand::query()->pluck('id', 'name')->all();
        $this->brandIdByName = $brands;

        $this->categoryIdByName = Category::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Category $category): array => [Str::lower($category->name) => $category->id])
            ->all();

        $this->defaultTaxClassId = TaxClass::query()
            ->where('slug', TaxClassSeeder::DEFAULT_SLUG)
            ->value('id');

        $axes = Attribute::query()
            ->with('values')
            ->whereIn('slug', [AttributeSeeder::COLOUR_SLUG, AttributeSeeder::SIZE_SLUG])
            ->get();

        foreach ($axes as $axis) {
            /** @var list<array{id: int, slug: string}> $values */
            $values = [];

            foreach ($axis->values as $value) {
                $values[] = ['id' => $value->id, 'slug' => $value->slug];
            }

            $this->variationAxes[$axis->slug] = ['id' => $axis->id, 'values' => $values];
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function seedProduct(array $row, int $index): void
    {
        /** @var string $name */
        $name = $row['name'];
        /** @var string $sku */
        $sku = $row['sku'];

        $isVariable = $index % self::VARIABLE_EVERY_NTH === 0 && $this->variationAxes !== [];
        $priceCents = $this->toCents($row['price'] ?? null);
        $quantity = is_numeric($row['quantity'] ?? null) ? (int) $row['quantity'] : null;
        $status = ProductStatus::tryFrom(is_string($row['status'] ?? null) ? $row['status'] : '') ?? ProductStatus::Draft;

        $product = Product::updateOrCreate(
            ['slug' => Str::slug($name.' '.$sku)],
            [
                'name' => $name,
                // A variable product carries no SKU of its own: the variants do.
                'sku' => $isVariable ? null : $sku,
                'brand_id' => $this->brandIdByName[trim((string) ($row['brand'] ?? ''))] ?? null,
                'primary_category_id' => $this->categoryIdByName[Str::lower(trim((string) ($row['category'] ?? '')))] ?? null,
                'model_number' => $row['model_number'] ?? null,
                'type' => $isVariable ? ProductType::Variable : ProductType::Simple,
                'status' => $status,
                'published_at' => $status === ProductStatus::Published ? now() : null,
                'short_description' => $row['short_description'] ?? Str::limit((string) ($row['description'] ?? ''), 140),
                'description' => $row['description'] ?? null,
                'price' => $priceCents,
                'sale_price' => $this->salePriceCents($priceCents, $index),
                'is_taxable' => true,
                'tax_class_id' => $this->defaultTaxClassId,
                'is_virtual' => false,
                'requires_shipping' => true,
                // A variable product stocks through its variants.
                'stock_status' => $this->stockStatus($quantity),
                'stock_quantity' => $isVariable ? null : $quantity,
                'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
                'visibility' => ProductVisibility::Visible,
                'meta_title' => $name,
                'meta_description' => Str::limit((string) ($row['description'] ?? $name), 155),
                'sort_order' => $index + 1,
            ],
        );

        $this->attachImages($product, $row);

        if ($isVariable) {
            $this->buildVariants($product, $sku, $index, $quantity);
        }

        if ($product->primary_category_id !== null) {
            $product->categories()->syncWithoutDetaching([
                $product->primary_category_id => ['sort_order' => $index + 1],
            ]);

            $this->catalogByCategory[$product->primary_category_id][] = [
                'id' => $product->id,
                'price' => $product->price ?? 0,
            ];
        }
    }

    /**
     * Attach the cover image and gallery to the `images` collection, in the
     * order the JSON lists them. Media order is written explicitly because the
     * seeder runs with model events muted, which would otherwise leave every
     * `order_column` null and the gallery unordered.
     *
     * @param  array<string, mixed>  $row
     */
    private function attachImages(Product $product, array $row): void
    {
        if ($product->getMedia('images')->isNotEmpty()) {
            return;
        }

        /** @var list<string> $paths */
        $paths = array_values(array_filter(
            array_merge(
                [is_string($row['image'] ?? null) ? $row['image'] : null],
                array_values((array) ($row['gallery'] ?? [])),
            ),
            fn ($path): bool => is_string($path) && $path !== '',
        ));

        $order = 0;

        foreach ($paths as $path) {
            if (! Storage::disk('public')->exists($path)) {
                $this->missingImages++;

                continue;
            }

            $media = $product->addMedia(Storage::disk('public')->path($path))
                ->preservingOriginal()
                ->withCustomProperties(['is_cover' => $order === 0])
                ->toMediaCollection('images');

            // `uuid` is assigned by medialibrary's HasUuid `creating` hook,
            // which the muted model events swallow just like `order_column`.
            // The column is nullable, so a missing uuid fails silently and
            // only surfaces later as a media row nothing can address by uuid.
            $media->uuid ??= (string) Str::uuid();
            $media->order_column = ++$order;
            $media->save();
        }
    }

    /**
     * Turn a product into a variable one: a colour or size axis, a variant per
     * value with its own SKU, price and stock, and a default variant.
     */
    private function buildVariants(Product $product, string $baseSku, int $index, ?int $quantity): void
    {
        $axisSlug = intdiv($index, self::VARIABLE_EVERY_NTH) % 2 === 0
            ? AttributeSeeder::COLOUR_SLUG
            : AttributeSeeder::SIZE_SLUG;

        $axis = $this->variationAxes[$axisSlug] ?? null;

        if ($axis === null || $axis['values'] === []) {
            return;
        }

        $values = array_slice($axis['values'], 0, 2 + ($index % 3));
        $basePriceCents = $product->price ?? 0;
        $firstVariantId = null;

        foreach ($values as $position => $value) {
            $variant = ProductVariant::updateOrCreate(
                ['sku' => $baseSku.'-'.Str::upper($value['slug'])],
                [
                    'product_id' => $product->id,
                    // Each step up the axis costs 8% more than the one before.
                    'price' => (int) round($basePriceCents * (1 + (0.08 * $position))),
                    'stock_status' => StockStatus::InStock,
                    'stock_quantity' => max(1, (int) round(($quantity ?? 20) / count($values)) + $position),
                    'is_active' => true,
                    'sort_order' => $position + 1,
                ],
            );

            $variant->attributeValues()->syncWithoutDetaching([$value['id']]);

            $firstVariantId ??= $variant->id;
            $this->variants++;
        }

        ProductAttribute::updateOrCreate(
            ['product_id' => $product->id, 'attribute_id' => $axis['id']],
            [
                'values' => array_column($values, 'slug'),
                'is_variation_attribute' => true,
                'is_visible' => true,
                'sort_order' => 1,
            ],
        );

        $product->forceFill(['default_variant_id' => $firstVariantId])->save();

        $this->variableProducts++;
    }

    /**
     * Wire the "complete your purchase" and "customers also buy" rails.
     *
     * Links are drawn between products that share a primary category, picked by
     * position and price rather than at random so the result is stable across
     * re-runs: neighbours become cross-sells, the next dearer products become
     * upsells, and the cheapest members of the category become accessories.
     */
    private function linkRelatedProducts(): int
    {
        $created = 0;

        foreach ($this->catalogByCategory as $siblings) {
            $count = count($siblings);

            if ($count < self::MIN_CATEGORY_SIZE_FOR_LINKS) {
                continue;
            }

            $byPrice = $siblings;
            usort($byPrice, fn (array $a, array $b): int => $a['price'] <=> $b['price']);

            foreach ($siblings as $position => $product) {
                $created += $this->link(
                    $product['id'],
                    [$siblings[($position + 1) % $count]['id'], $siblings[($position + 2) % $count]['id']],
                    ProductLinkType::CrossSell,
                );

                $dearer = array_values(array_filter(
                    $byPrice,
                    fn (array $sibling): bool => $sibling['price'] > $product['price'],
                ));

                $created += $this->link(
                    $product['id'],
                    array_column(array_slice($dearer, 0, 2), 'id'),
                    ProductLinkType::Upsell,
                );

                if ($position % 3 === 0) {
                    $created += $this->link(
                        $product['id'],
                        array_column(array_slice($byPrice, 0, 2), 'id'),
                        ProductLinkType::Accessory,
                        // The first accessory is the one the product really
                        // needs, so it is pre-checked with a sensible quantity.
                        isRequired: true,
                        defaultQuantity: 1 + ($position % 4),
                    );
                }

                if ($position % 5 === 0) {
                    $created += $this->link(
                        $product['id'],
                        [$siblings[($position + 3) % $count]['id']],
                        ProductLinkType::SparePart,
                    );
                }
            }
        }

        $this->flushLinks();

        return $created;
    }

    /**
     * Queue link rows for the batch upsert. A row per link would cost two
     * queries each across the whole catalog, so they are collected and written
     * in chunks against the (product, linked product, type) unique index.
     *
     * @param  list<int>  $linkedIds
     */
    private function link(
        int $productId,
        array $linkedIds,
        ProductLinkType $type,
        bool $isRequired = false,
        int $defaultQuantity = 1,
    ): int {
        $created = 0;
        $position = 0;

        foreach (array_unique($linkedIds) as $linkedId) {
            if ($linkedId === $productId) {
                continue;
            }

            $this->linkRows[] = [
                'product_id' => $productId,
                'linked_product_id' => $linkedId,
                'type' => $type->value,
                // Only the first link of a batch is the one the product needs.
                'is_required' => $isRequired && $position === 0,
                'default_quantity' => $position === 0 ? $defaultQuantity : 1,
                'sort_order' => ++$position,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $created++;
        }

        return $created;
    }

    /** Write the collected link rows, updating any that already exist. */
    private function flushLinks(): void
    {
        foreach (array_chunk($this->linkRows, 500) as $chunk) {
            ProductLink::upsert(
                $chunk,
                ['product_id', 'linked_product_id', 'type'],
                ['is_required', 'default_quantity', 'sort_order', 'updated_at'],
            );
        }

        $this->linkRows = [];
    }

    /** A tracked product with nothing left on the shelf is out of stock. */
    private function stockStatus(?int $quantity): StockStatus
    {
        return $quantity !== null && $quantity <= 0
            ? StockStatus::OutOfStock
            : StockStatus::InStock;
    }

    /** Discount every nth product so the storefront's sale rails have data. */
    private function salePriceCents(?int $priceCents, int $index): ?int
    {
        if ($priceCents === null || $index % self::SALE_EVERY_NTH !== 3) {
            return null;
        }

        return (int) round($priceCents * (1 - self::SALE_DISCOUNT));
    }

    /** Source prices are major units; every column here is integer cents. */
    private function toCents(mixed $major): ?int
    {
        if (! is_numeric($major)) {
            return null;
        }

        return $this->money->toMinor((float) $major);
    }
}
