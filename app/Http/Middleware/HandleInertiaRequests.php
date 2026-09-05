<?php

namespace App\Http\Middleware;

use App\Enums\CategorySection;
use App\Models\CategoryPlacement;
use App\Support\StorefrontCache;
use App\Support\StorefrontSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private StorefrontSession $storefront) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'storefront' => [
                'navCategories' => $this->navCategories(),
                // The header's cart / wishlist / compare state. Read straight
                // out of the session for guests and signed-in customers alike —
                // StorefrontSession keeps the session as the live copy
                // precisely so this costs nothing on a path that runs on every
                // single request.
                'shopper' => $this->storefront->shopperState(),
            ],
        ];
    }

    /**
     * The categories in the header stripe and footer. Curated through
     * CategoryPlacement rather than "all top-level categories", so merchandising
     * decides the navigation.
     *
     * Cached because it is shared on every response and changes rarely.
     * CategoryPlacementObserver and CategoryObserver clear the key whenever a
     * placement or a category's name, slug or status changes, so the hour is a
     * backstop rather than the mechanism.
     *
     * @return array<int, array{name: string, slug: string}>
     */
    private function navCategories(): array
    {
        return Cache::remember(StorefrontCache::NAV_CATEGORIES, now()->addHour(), function (): array {
            // A placement cannot outlive its category — the foreign key cascades
            // on delete — so the relation is always present here.
            return CategoryPlacement::query()
                ->active()
                ->forLocation(CategorySection::Navbar)
                ->with('category:id,name,slug')
                ->get()
                ->map(fn (CategoryPlacement $placement): array => [
                    'name' => $placement->category->name,
                    'slug' => $placement->category->slug,
                ])
                ->values()
                ->all();
        });
    }
}
