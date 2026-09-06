<?php

namespace App\Http\Middleware;

use App\Enums\CategorySection;
use App\Models\CategoryPlacement;
use App\Settings\SocialSettings;
use App\Support\StorefrontCache;
use App\Support\StorefrontSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Spatie\Permission\Models\Permission;

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
                // A closure for the same reason `socialLinks` below is one:
                // `share()` runs on every request through this middleware,
                // including plain JSON ones like search-suggest, and
                // User::isStaff() is a `roles` existence query. Inertia only
                // resolves prop closures when it is building a page response,
                // so the keystroke endpoints never pay for it.
                'isStaff' => fn (): bool => (bool) $request->user()?->isStaff(),
                // Also a closure, and for the extra reason that it is two more
                // queries than `isStaff` is: the admin sidebar renders itself
                // from this list, so a Manager never sees a link to a page
                // `can:` would refuse them.
                'permissions' => fn (): array => $this->permissions($request),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'storefront' => [
                'navCategories' => $this->navCategories(),
                // A closure, not a value: `share()` runs on every request that
                // passes through this middleware, including plain JSON ones
                // like search-suggest, but Inertia only resolves prop closures
                // when it is actually building a page response. Resolved eagerly
                // this put a settings read on an endpoint that fires on every
                // keystroke.
                'socialLinks' => fn (): array => $this->socialLinks(),
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
     * Every permission the signed-in staff member holds, however granted.
     *
     * The admin sidebar filters itself against this, so the navigation and the
     * `can:` middleware on the routes are driven by one source rather than two
     * that can drift. Customers hold no roles and therefore no permissions —
     * they get an empty list without a permissions query being run at all.
     *
     * @return list<string>
     */
    private function permissions(Request $request): array
    {
        $user = $request->user();

        if ($user === null || ! $user->isStaff()) {
            return [];
        }

        return array_values(array_map(
            static fn (Permission $permission): string => $permission->name,
            $user->getAllPermissions()->all(),
        ));
    }

    /**
     * The store's social profiles, for the footer.
     *
     * Only the ones actually filled in are sent, so the footer renders what
     * exists rather than a fixed row with dead icons in it. `icon` is a key the
     * footer maps to its own mark — lucide dropped brand icons in v1, so the
     * glyphs are inline SVGs there.
     *
     * Cached like the nav: settings caching is off by default, and this is
     * shared on every response.
     *
     * @return array<int, array{icon: string, label: string, url: string}>
     */
    private function socialLinks(): array
    {
        return Cache::remember(StorefrontCache::SOCIAL_LINKS, now()->addHour(), function (): array {
            $social = app(SocialSettings::class);

            $profiles = [
                ['icon' => 'facebook', 'label' => 'Facebook', 'url' => $social->facebook_url],
                ['icon' => 'instagram', 'label' => 'Instagram', 'url' => $social->instagram_url],
                ['icon' => 'x', 'label' => 'X', 'url' => $social->x_url],
                ['icon' => 'youtube', 'label' => 'YouTube', 'url' => $social->youtube_url],
                ['icon' => 'linkedin', 'label' => 'LinkedIn', 'url' => $social->linkedin_url],
            ];

            return array_values(array_filter(
                $profiles,
                fn (array $profile): bool => trim($profile['url']) !== '',
            ));
        });
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
