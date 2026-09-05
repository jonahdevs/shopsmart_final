<?php

namespace App\Http\Controllers\Shop;

use App\Data\CompareData;
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
 * The compare tray: a small, capped, ordered set of products laid out
 * side by side.
 *
 * The cap is enforced in {@see StorefrontSession}, which drops the oldest entry
 * rather than refusing the new one — a shopper adding a fifth product means to
 * compare it, and a modal explaining why nothing happened would be worse.
 */
class CompareController extends Controller
{
    public function index(StorefrontSession $storefront): Response
    {
        return Inertia::render('shop/Compare', [
            'compare' => CompareData::fromProducts(
                $storefront->compareProducts(),
                StorefrontSession::COMPARE_LIMIT,
            ),
        ]);
    }

    public function store(SaveProductRequest $request, StorefrontSession $storefront): RedirectResponse
    {
        /** @var Product $product */
        $product = $request->product();

        $added = $storefront->save(SavedProductList::Compare, $product);

        Inertia::flash('toast', $added
            ? ['type' => 'success', 'message' => __(':name added to compare.', ['name' => $product->name])]
            : ['type' => 'info', 'message' => __(':name is already being compared.', ['name' => $product->name])]);

        return back();
    }

    public function destroy(RemoveSavedProductRequest $request, StorefrontSession $storefront): RedirectResponse
    {
        $storefront->removeSaved(SavedProductList::Compare, $request->productId());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Removed from compare.')]);

        return back();
    }

    public function clear(StorefrontSession $storefront): RedirectResponse
    {
        $storefront->clearSaved(SavedProductList::Compare);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Compare list cleared.')]);

        return to_route('compare.index');
    }
}
