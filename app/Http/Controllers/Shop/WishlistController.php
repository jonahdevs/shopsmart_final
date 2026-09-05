<?php

namespace App\Http\Controllers\Shop;

use App\Data\ProductCardData;
use App\Enums\SavedProductList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\RemoveSavedProductRequest;
use App\Http\Requests\Shop\SaveProductRequest;
use App\Models\Product;
use App\Support\StorefrontSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The wishlist: an open-ended, ordered set of products the shopper saved.
 *
 * `store` adds rather than toggles, and adding something already saved is a
 * no-op. The heart button knows which state it is in from the shopper's saved
 * ids in the shared props, so it can pick the right verb without a round trip
 * and without two requests racing a toggle into the wrong state.
 */
class WishlistController extends Controller
{
    public function index(StorefrontSession $storefront): Response
    {
        return Inertia::render('shop/Wishlist', [
            'products' => array_values($storefront->wishlistProducts()
                ->map(fn (Product $product): ProductCardData => ProductCardData::fromModel($product))
                ->all()),
        ]);
    }

    public function store(SaveProductRequest $request, StorefrontSession $storefront): RedirectResponse
    {
        /** @var Product $product */
        $product = $request->product();

        $added = $storefront->save(SavedProductList::Wishlist, $product);

        Inertia::flash('toast', $added
            ? ['type' => 'success', 'message' => __(':name saved to your wishlist.', ['name' => $product->name])]
            : ['type' => 'info', 'message' => __(':name is already in your wishlist.', ['name' => $product->name])]);

        return back();
    }

    public function destroy(RemoveSavedProductRequest $request, StorefrontSession $storefront): RedirectResponse
    {
        $storefront->removeSaved(SavedProductList::Wishlist, $request->productId());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Removed from your wishlist.')]);

        return back();
    }

    public function clear(StorefrontSession $storefront): RedirectResponse
    {
        $storefront->clearSaved(SavedProductList::Wishlist);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your wishlist is now empty.')]);

        return to_route('wishlist.index');
    }
}
