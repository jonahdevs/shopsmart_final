<?php

namespace App\Http\Controllers\Shop;

use App\Data\CartData;
use App\Data\CartItemData;
use App\Data\ProductCardData;
use App\Enums\ProductLinkType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\AddToCartRequest;
use App\Http\Requests\Shop\RemoveCartItemRequest;
use App\Http\Requests\Shop\UpdateCartItemRequest;
use App\Models\Product;
use App\Models\ProductLink;
use App\Support\StorefrontSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The cart page and the four mutations behind it.
 *
 * Every mutation goes through {@see StorefrontSession}, which is what keeps a
 * guest's cart and a signed-in customer's identical from here: this controller
 * never asks who is shopping.
 */
class CartController extends Controller
{
    /** Products offered in the cross-sell rail below the cart. */
    private const RAIL_SIZE = 8;

    /**
     * Show the cart.
     *
     * The cross-sell rail is deferred: it is a second page of queries that
     * nothing above the fold depends on, and the cart itself should paint as
     * soon as it is read.
     */
    public function index(StorefrontSession $storefront): Response
    {
        $cart = $storefront->cart();

        return Inertia::render('shop/Cart', [
            'cart' => $cart,
            'crossSells' => Inertia::defer(fn (): array => $this->crossSells($cart)),
        ]);
    }

    /**
     * Add a product, or top up a line already in the cart.
     *
     * The stock rules can hand back fewer than were asked for, so the toast says
     * what actually happened rather than assuming the request was honoured.
     */
    public function store(AddToCartRequest $request, StorefrontSession $storefront): RedirectResponse
    {
        /** @var Product $product */
        $product = $request->product();

        $before = $storefront->cartCount();
        $requested = $request->quantity();

        $storefront->addToCart($product, $request->variant(), $requested);

        $added = $storefront->cartCount() - $before;

        Inertia::flash('toast', $added < $requested
            ? ['type' => 'warning', 'message' => __('Only :count more of :name are available.', ['count' => $added, 'name' => $product->name])]
            : ['type' => 'success', 'message' => __(':name added to your cart.', ['name' => $product->name])]);

        return back();
    }

    /** Set a line to an exact quantity; zero removes it. */
    public function update(UpdateCartItemRequest $request, StorefrontSession $storefront): RedirectResponse
    {
        /** @var Product $product */
        $product = $request->product();

        $quantity = $request->quantity();
        $resolved = $storefront->setCartQuantity($product, $request->variant(), $quantity);

        Inertia::flash('toast', match (true) {
            $resolved === 0 => ['type' => 'success', 'message' => __('Item removed from your cart.')],
            $resolved < $quantity => ['type' => 'warning', 'message' => __('Only :count of :name are available.', ['count' => $resolved, 'name' => $product->name])],
            default => ['type' => 'success', 'message' => __('Cart updated.')],
        });

        return back();
    }

    public function destroy(RemoveCartItemRequest $request, StorefrontSession $storefront): RedirectResponse
    {
        $storefront->removeFromCart($request->productId(), $request->variantId());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item removed from your cart.')]);

        return back();
    }

    public function clear(StorefrontSession $storefront): RedirectResponse
    {
        $storefront->clearCart();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your cart is now empty.')]);

        return to_route('cart.index');
    }

    /**
     * Products curated as cross-sells or accessories of what is already in the
     * cart, minus what is in there already.
     *
     * Two queries whatever the cart holds: one over the link table for the ids,
     * one to hydrate them through the same live/visible/in-stock gate the
     * catalog listings use.
     *
     * @return list<ProductCardData>
     */
    private function crossSells(CartData $cart): array
    {
        $inCart = array_map(fn (CartItemData $item): int => $item->productId, $cart->items);

        if ($inCart === []) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = ProductLink::query()
            ->whereIn('product_id', $inCart)
            ->whereIn('type', [ProductLinkType::CrossSell, ProductLinkType::Accessory])
            ->orderBy('sort_order')
            ->pluck('linked_product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->reject(fn (int $id): bool => in_array($id, $inCart, true))
            ->take(self::RAIL_SIZE)
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return array_values(Product::query()
            ->with(['brand:id,name,slug', 'media'])
            ->withReviewStats()
            ->published()
            ->visibleInCatalog()
            ->honorStockVisibility()
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (Product $product): ProductCardData => ProductCardData::fromModel($product))
            ->all());
    }
}
