<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The six slides the home-page hero opens with.
 *
 * The reference app kept this list as a PHP array inside the Blade view; these
 * are ordinary rows an admin can rewrite, reorder or schedule. Seeding is
 * idempotent on the headline, so re-running never duplicates a slide, and
 * artwork already attached is left alone.
 *
 * Artwork is borrowed from the category imagery already on the public disk —
 * there is no dedicated banner set in this project yet.
 */
class HeroSlideSeeder extends Seeder
{
    /**
     * Storefront routes are not registered yet, so the calls to action point at
     * the paths the catalog will live on rather than at named routes.
     *
     * @var list<array{headline: string, subheadline: string, cta_label: string, cta_slug: string|null, alignment: string, text_theme: string, desktop: string, mobile: string}>
     */
    private const SLIDES = [
        [
            'headline' => 'Shop beauty, fashion, electronics and groceries',
            'subheadline' => 'Twelve departments in one order, delivered countrywide.',
            'cta_label' => 'Browse all products',
            'cta_slug' => null,
            'alignment' => 'right',
            'text_theme' => 'dark',
            'desktop' => 'categories/home.webp',
            'mobile' => 'categories/groceries.webp',
        ],
        [
            'headline' => 'Skincare, haircare and makeup restocked weekly',
            'subheadline' => 'Cleansers, serums and colour from the brands you already use.',
            'cta_label' => 'Shop beauty',
            'cta_slug' => 'beauty-personal-care',
            'alignment' => 'right',
            'text_theme' => 'dark',
            'desktop' => 'categories/makeup.webp',
            'mobile' => 'categories/skin-care.webp',
        ],
        [
            'headline' => 'Dresses, bags and shoes for the season',
            'subheadline' => "New arrivals in women's fashion, in sizes that stay in stock.",
            'cta_label' => "Shop women's fashion",
            'cta_slug' => 'womens-fashion',
            'alignment' => 'right',
            'text_theme' => 'dark',
            'desktop' => 'categories/womens-dresses.webp',
            'mobile' => 'categories/womens-bags.webp',
        ],
        [
            'headline' => 'Phones, laptops and speakers in stock',
            'subheadline' => 'Electronics dispatched the same day on orders placed before 2pm.',
            'cta_label' => 'Shop electronics',
            'cta_slug' => 'electronics',
            'alignment' => 'left',
            'text_theme' => 'light',
            'desktop' => 'categories/electronics.webp',
            'mobile' => 'categories/laptops.webp',
        ],
        [
            'headline' => 'Furnish the room you keep putting off',
            'subheadline' => 'Sofas, beds, storage and decor for every room in the house.',
            'cta_label' => 'Shop home and living',
            'cta_slug' => 'home-living',
            'alignment' => 'right',
            'text_theme' => 'dark',
            'desktop' => 'categories/furniture.webp',
            'mobile' => 'categories/home-decoration.webp',
        ],
        [
            'headline' => 'Selected kitchen and dining reduced this month',
            'subheadline' => 'Cookware, glassware and storage at lower prices while stock lasts.',
            'cta_label' => 'Shop the sale',
            'cta_slug' => 'kitchen-dining',
            'alignment' => 'left',
            'text_theme' => 'light',
            'desktop' => 'categories/kitchen-accessories.webp',
            // No separate portrait shot exists for this one; the mobile
            // conversion crops the same source to its own aspect.
            'mobile' => 'categories/kitchen-accessories.webp',
        ],
    ];

    private int $missingImages = 0;

    public function run(): void
    {
        foreach (self::SLIDES as $index => $slide) {
            $record = HeroSlide::updateOrCreate(
                ['headline' => $slide['headline']],
                [
                    'subheadline' => $slide['subheadline'],
                    'cta_label' => $slide['cta_label'],
                    'cta_url' => $this->ctaUrl($slide['cta_slug']),
                    'alignment' => $slide['alignment'],
                    'text_theme' => $slide['text_theme'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'starts_at' => null,
                    'ends_at' => null,
                ],
            );

            $this->attachArtwork($record, 'desktop', $slide['desktop']);
            $this->attachArtwork($record, 'mobile', $slide['mobile']);
        }

        $count = count(self::SLIDES);

        $this->command->info("Seeded {$count} hero slides.");

        if ($this->missingImages > 0) {
            $this->command->warn("{$this->missingImages} hero image(s) were not found on the public disk and were skipped.");
        }
    }

    /**
     * Resolve a CTA through the router rather than storing a hand-written path,
     * so a slide can never outlive the URL structure it was written against.
     * A null slug sends the shopper to the full catalogue.
     */
    private function ctaUrl(?string $categorySlug): string
    {
        return $categorySlug === null
            ? route('catalog', absolute: false)
            : route('category.show', $categorySlug, absolute: false);
    }

    /**
     * Attach one slide image, skipping a file that is not on the disk rather
     * than failing the whole seed. Re-running leaves a populated collection
     * alone so the artwork is not replaced on every seed.
     */
    private function attachArtwork(HeroSlide $slide, string $collection, string $relativePath): void
    {
        if ($slide->getMedia($collection)->isNotEmpty()) {
            return;
        }

        if (! Storage::disk('public')->exists($relativePath)) {
            $this->missingImages++;

            return;
        }

        $media = $slide->addMedia(Storage::disk('public')->path($relativePath))
            ->preservingOriginal()
            ->toMediaCollection($collection);

        // Seeders run with model events muted, so medialibrary's HasUuid
        // `creating` hook never fires and the nullable column stays null.
        $media->uuid ??= (string) Str::uuid();
        $media->save();
    }
}
