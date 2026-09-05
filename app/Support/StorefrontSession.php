<?php

namespace App\Support;

use App\Data\CartData;
use App\Data\CartItemData;
use App\Data\ShopperStateData;
use App\Enums\ProductVisibility;
use App\Enums\SavedProductList;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SavedProduct;
use App\Models\User;
use App\Settings\InventorySettings;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * The one entry point to a shopper's cart, wishlist and compare tray.
 *
 * The session always holds the live copy — for guests, who have nowhere else to
 * put it, and for signed-in customers, who get the same arrays mirrored into
 * `carts` / `cart_items` / `saved_products` on every mutation. That is what
 * makes the header counts free: they are an array_sum over the session, on a
 * path that runs on every single request, rather than a query per response.
 *
 * Callers never branch on whether someone is signed in. Persistence is a
 * private detail of this class, and login is the one moment the two copies have
 * to be reconciled — see {@see mergeIntoUser()}.
 *
 * Session shapes:
 * - cart: `['12|45' => ['product_id' => 12, 'variant_id' => 45, 'quantity' => 2, 'unit_price_cents' => 150000]]`
 * - wishlist / compare: `[12, 7, 33]`, product ids in the order they were saved
 */
class StorefrontSession
{
    /** How many products the compare tray holds before it starts dropping the oldest. */
    public const COMPARE_LIMIT = 4;

    /** Absolute ceiling on a single cart line, whatever the stock rules allow. */
    private const MAX_LINE_QUANTITY = 999;

    private const CART_KEY = 'storefront.cart';

    private const WISHLIST_KEY = 'storefront.wishlist';

    private const COMPARE_KEY = 'storefront.compare';

    /**
     * Separates the product id from the variant id in a cart line key. Ids are
     * numeric, so the pipe can never occur inside one half.
     */
    private const VARIANT_SEPARATOR = '|';

    // ==================================================
    // SHARED STATE
    // ==================================================

    /**
     * Everything the header, the product tiles and the compare tray need, read
     * entirely from the session. No queries — this is shared on every response.
     */
    public function shopperState(): ShopperStateData
    {
        $wishlist = $this->savedIds(SavedProductList::Wishlist);
        $compare = $this->savedIds(SavedProductList::Compare);

        return new ShopperStateData(
            cartCount: $this->cartCount(),
            wishlistCount: count($wishlist),
            compareCount: count($compare),
            wishlistProductIds: $wishlist,
            compareProductIds: $compare,
            compareLimit: self::COMPARE_LIMIT,
        );
    }

    /** Total units in the cart, not distinct lines. */
    public function cartCount(): int
    {
        return array_sum(array_map(
            fn (array $line): int => $line['quantity'],
            $this->rawCart(),
        ));
    }

    public function wishlistCount(): int
    {
        return count($this->savedIds(SavedProductList::Wishlist));
    }

    public function compareCount(): int
    {
        return count($this->savedIds(SavedProductList::Compare));
    }

    /** Build the session key identifying one cart line. */
    public function lineKey(int $productId, ?int $variantId = null): string
    {
        return $variantId === null
            ? (string) $productId
            : $productId.self::VARIANT_SEPARATOR.$variantId;
    }

    // ==================================================
    // CART
    // ==================================================

    /**
     * The rendered cart.
     *
     * Products and variants are loaded in two queries however many lines there
     * are. A line whose product has since been deleted, unpublished or hidden
     * is dropped here and pruned from the session, so a cart cannot rot into
     * something the shopper can see but never remove.
     */
    public function cart(): CartData
    {
        $lines = $this->rawCart();

        if ($lines === []) {
            return CartData::blank();
        }

        /** @var EloquentCollection<int, Product> $products */
        $products = Product::query()
            ->with(['brand:id,name,slug', 'media'])
            ->whereIn('id', array_map(fn (array $line): int => $line['product_id'], $lines))
            ->get()
            ->keyBy('id');

        $variantIds = array_values(array_filter(array_map(
            fn (array $line): ?int => $line['variant_id'],
            $lines,
        )));

        /** @var EloquentCollection<int, ProductVariant> $variants */
        $variants = $variantIds === []
            ? new EloquentCollection
            : ProductVariant::query()
                ->with(['media', 'attributeValues'])
                ->whereIn('id', $variantIds)
                ->get()
                ->keyBy('id');

        $items = [];
        $kept = [];

        foreach ($lines as $key => $line) {
            $product = $products->get($line['product_id']);

            if ($product === null || ! $this->isVisibleToShopper($product)) {
                continue;
            }

            $variant = $line['variant_id'] === null ? null : $variants->get($line['variant_id']);

            if ($line['variant_id'] !== null && ($variant === null || ! $variant->is_active)) {
                continue;
            }

            // The variant's price falls back to its parent product's; setting
            // the relation here keeps that from becoming a lazy load per line.
            $variant?->setRelation('product', $product);

            $items[] = CartItemData::fromLine(
                key: $key,
                product: $product,
                variant: $variant,
                quantity: $line['quantity'],
                unitPriceCents: $line['unit_price_cents'],
                currentUnitPriceCents: $this->unitPriceCents($product, $variant),
                maxQuantity: $this->availableQuantity($product, $variant),
            );

            $kept[$key] = $line;
        }

        if (count($kept) !== count($lines)) {
            $this->putCart($kept);
        }

        return CartData::fromItems($items);
    }

    /**
     * Add to the cart, or top up a line that is already there.
     *
     * Returns the quantity the line actually ended up at, which is not always
     * what was asked for: stock and the product's minimum order quantity are
     * applied here rather than trusted from the request.
     */
    public function addToCart(Product $product, ?ProductVariant $variant = null, int $quantity = 1): int
    {
        $key = $this->lineKey($product->getKey(), $variant?->getKey());
        $cart = $this->rawCart();
        $existing = $cart[$key]['quantity'] ?? 0;

        $resolved = $this->clampQuantity($product, $variant, $existing + max(1, $quantity));

        $cart[$key] = [
            'product_id' => $product->getKey(),
            'variant_id' => $variant?->getKey(),
            'quantity' => $resolved,
            // The captured price belongs to the line, not to the top-up: adding
            // one more of something already in the cart must not silently
            // re-price the units already there.
            'unit_price_cents' => $cart[$key]['unit_price_cents'] ?? $this->unitPriceCents($product, $variant),
        ];

        $this->putCart($cart);

        return $resolved;
    }

    /**
     * Set a line to an exact quantity. Zero (or less) removes it, which is what
     * a stepper stepped down past one means.
     */
    public function setCartQuantity(Product $product, ?ProductVariant $variant, int $quantity): int
    {
        if ($quantity <= 0) {
            $this->removeFromCart($product->getKey(), $variant?->getKey());

            return 0;
        }

        $key = $this->lineKey($product->getKey(), $variant?->getKey());
        $cart = $this->rawCart();

        if (! isset($cart[$key])) {
            return $this->addToCart($product, $variant, $quantity);
        }

        $resolved = $this->clampQuantity($product, $variant, $quantity);
        $cart[$key]['quantity'] = $resolved;

        $this->putCart($cart);

        return $resolved;
    }

    /**
     * Drop a line. Deliberately takes ids rather than models: a product that has
     * been deleted or hidden since it was added still has to be removable.
     */
    public function removeFromCart(int $productId, ?int $variantId = null): void
    {
        $cart = $this->rawCart();
        unset($cart[$this->lineKey($productId, $variantId)]);

        $this->putCart($cart);
    }

    public function clearCart(): void
    {
        $this->putCart([]);
    }

    /**
     * Whether this product (in this configuration) can go in a cart at all.
     *
     * A variable product cannot: its price and stock live on a variant, so the
     * shopper has to choose one first. Neither can a price-on-application
     * product, which is a quote request rather than a purchase.
     */
    public function isPurchasable(Product $product, ?ProductVariant $variant = null): bool
    {
        if (! $this->isVisibleToShopper($product)) {
            return false;
        }

        if ($variant !== null) {
            if ($variant->product_id !== $product->getKey() || ! $variant->is_active) {
                return false;
            }

            return $this->isSellableInStock($variant->isInStock(), $variant->allow_backorder);
        }

        if ($product->hasVariants() || $product->effectivePriceCents() === null) {
            return false;
        }

        return $this->isSellableInStock($product->isInStock(), $product->allow_backorder);
    }

    /**
     * The most of this the shopper may take. Null means no ceiling: stock is
     * untracked, or backorders are allowed and the shelf is not the limit.
     */
    public function availableQuantity(Product $product, ?ProductVariant $variant = null): ?int
    {
        if ($variant !== null) {
            return $variant->allow_backorder ? null : $variant->stock_quantity;
        }

        return $product->allow_backorder ? null : $product->stock_quantity;
    }

    // ==================================================
    // WISHLIST AND COMPARE
    // ==================================================

    public function isSaved(SavedProductList $list, int $productId): bool
    {
        return in_array($productId, $this->savedIds($list), true);
    }

    /**
     * Save a product to a list. Already-saved is a no-op, so the button is
     * idempotent; the compare tray drops its oldest entry once it is full.
     *
     * @return bool Whether the product was newly added.
     */
    public function save(SavedProductList $list, Product $product): bool
    {
        $ids = $this->savedIds($list);
        $productId = $product->getKey();

        if (in_array($productId, $ids, true)) {
            return false;
        }

        $ids[] = $productId;

        $this->putSavedIds($list, $ids);

        return true;
    }

    public function removeSaved(SavedProductList $list, int $productId): void
    {
        $this->putSavedIds($list, array_values(array_filter(
            $this->savedIds($list),
            fn (int $id): bool => $id !== $productId,
        )));
    }

    public function clearSaved(SavedProductList $list): void
    {
        $this->putSavedIds($list, []);
    }

    /**
     * The wishlist, in saved order, with everything a product tile renders.
     *
     * @return EloquentCollection<int, Product>
     */
    public function wishlistProducts(): EloquentCollection
    {
        return $this->savedProducts(
            SavedProductList::Wishlist,
            ['brand:id,name,slug', 'media'],
            withReviewStats: true,
        );
    }

    /**
     * The compare tray, in saved order, with the specification attributes the
     * compare table lays out side by side.
     *
     * @return EloquentCollection<int, Product>
     */
    public function compareProducts(): EloquentCollection
    {
        return $this->savedProducts(
            SavedProductList::Compare,
            [
                'brand:id,name,slug',
                'media',
                'productAttributes' => fn ($query) => $query->visible(),
                'productAttributes.attribute',
            ],
            withReviewStats: true,
        );
    }

    // ==================================================
    // LOGIN
    // ==================================================

    /**
     * Reconcile the session the shopper arrived with against whatever they left
     * behind last time, then rehydrate the session from the merged result so the
     * live copy reflects both.
     *
     * Overlapping cart lines keep the *larger* quantity rather than the sum,
     * which is what makes this idempotent: after the first login the session and
     * the database hold the same numbers, so logging in again — or logging in on
     * a second tab — cannot inflate a line.
     */
    public function mergeIntoUser(User $user): void
    {
        $this->mergeCartIntoUser($user);
        $this->mergeSavedListIntoUser($user, SavedProductList::Wishlist);
        $this->mergeSavedListIntoUser($user, SavedProductList::Compare);
    }

    // ==================================================
    // SESSION READS AND WRITES
    // ==================================================

    /**
     * @return array<string, array{product_id: int, variant_id: int|null, quantity: int, unit_price_cents: int}>
     */
    private function rawCart(): array
    {
        /** @var array<string, array{product_id: int, variant_id: int|null, quantity: int, unit_price_cents: int}> $cart */
        $cart = Session::get(self::CART_KEY, []);

        return $cart;
    }

    /**
     * @param  array<string, array{product_id: int, variant_id: int|null, quantity: int, unit_price_cents: int}>  $cart
     */
    private function putCart(array $cart): void
    {
        Session::put(self::CART_KEY, $cart);

        $this->persistCart();
    }

    /** @return list<int> */
    private function savedIds(SavedProductList $list): array
    {
        /** @var list<int> $ids */
        $ids = Session::get($this->savedKey($list), []);

        return $ids;
    }

    /** @param  list<int>  $ids */
    private function putSavedIds(SavedProductList $list, array $ids): void
    {
        if ($list === SavedProductList::Compare && count($ids) > self::COMPARE_LIMIT) {
            $ids = array_slice($ids, -self::COMPARE_LIMIT);
        }

        Session::put($this->savedKey($list), $ids);

        $this->persistSavedList($list);
    }

    private function savedKey(SavedProductList $list): string
    {
        return match ($list) {
            SavedProductList::Wishlist => self::WISHLIST_KEY,
            SavedProductList::Compare => self::COMPARE_KEY,
        };
    }

    // ==================================================
    // PRICING, STOCK AND VISIBILITY
    // ==================================================

    /**
     * What one unit costs right now, in cents. Zero rather than null: a line
     * only exists for something {@see isPurchasable()} let through, which
     * already excludes price-on-application products.
     */
    private function unitPriceCents(Product $product, ?ProductVariant $variant): int
    {
        return $variant?->effectivePriceCents()
            ?? $product->effectivePriceCents()
            ?? 0;
    }

    /**
     * Raise to the product's minimum order quantity, then cap at whatever is
     * actually available. A minimum above the remaining stock loses to the
     * stock — we cannot sell what is not there.
     */
    private function clampQuantity(Product $product, ?ProductVariant $variant, int $quantity): int
    {
        $minimum = max(1, $product->min_order_quantity ?? 1);
        $available = $this->availableQuantity($product, $variant);

        $ceiling = min(
            self::MAX_LINE_QUANTITY,
            $available ?? self::MAX_LINE_QUANTITY,
        );

        return max(1, min(max($quantity, $minimum), $ceiling));
    }

    /**
     * An out-of-stock product is still sellable when it can be backordered —
     * unless the store hides out-of-stock products altogether, in which case it
     * is not on the storefront to be bought.
     */
    private function isSellableInStock(bool $inStock, bool $allowsBackorder): bool
    {
        if ($inStock) {
            return true;
        }

        return $allowsBackorder
            && app(InventorySettings::class)->out_of_stock_behavior !== 'hide';
    }

    /** Live, and not deliberately kept off the storefront. */
    private function isVisibleToShopper(Product $product): bool
    {
        return $product->isPublished() && $product->visibility !== ProductVisibility::Hidden;
    }

    /**
     * Hydrate a saved list in session order, dropping — and pruning — anything
     * that is no longer on the storefront.
     *
     * @param  array<int|string, mixed>  $with
     * @return EloquentCollection<int, Product>
     */
    private function savedProducts(SavedProductList $list, array $with, bool $withReviewStats): EloquentCollection
    {
        $ids = $this->savedIds($list);

        if ($ids === []) {
            /** @var EloquentCollection<int, Product> */
            return new EloquentCollection;
        }

        $query = Product::query()->with($with)->whereIn('id', $ids);

        if ($withReviewStats) {
            $query->withReviewStats();
        }

        $products = $query->get()->keyBy('id');

        $ordered = [];
        $kept = [];

        foreach ($ids as $id) {
            /** @var Product|null $product */
            $product = $products->get($id);

            if ($product === null || ! $this->isVisibleToShopper($product)) {
                continue;
            }

            $ordered[] = $product;
            $kept[] = $id;
        }

        if (count($kept) !== count($ids)) {
            $this->putSavedIds($list, $kept);
        }

        /** @var EloquentCollection<int, Product> */
        return new EloquentCollection($ordered);
    }

    // ==================================================
    // PERSISTENCE
    // ==================================================

    /**
     * Mirror the session cart into the signed-in customer's row. A no-op for
     * guests, who have no identity to hang a durable cart on.
     *
     * The session is authoritative, so this is a rewrite rather than a merge:
     * anything the session no longer has is deleted. Merging only happens at
     * login, where the two copies genuinely diverged.
     */
    private function persistCart(?User $user = null): void
    {
        $user = $this->resolveUser($user);

        if ($user === null) {
            return;
        }

        $lines = $this->rawCart();
        $existing = Cart::query()->where('user_id', $user->getKey())->first();

        // Never create a cart row just to record that it is empty.
        if ($lines === [] && $existing === null) {
            return;
        }

        $cart = $existing ?? Cart::create(['user_id' => $user->getKey()]);

        DB::transaction(function () use ($cart, $lines): void {
            $keptIds = [];

            foreach ($lines as $line) {
                $item = $cart->items()->updateOrCreate(
                    [
                        'product_id' => $line['product_id'],
                        'product_variant_id' => $line['variant_id'],
                    ],
                    [
                        'quantity' => $line['quantity'],
                        'unit_price_cents' => $line['unit_price_cents'],
                    ],
                );

                $keptIds[] = $item->getKey();
            }

            $cart->items()->whereNotIn('id', $keptIds)->delete();
        });

        $cart->markActive();
    }

    /**
     * Mirror one saved list into `saved_products`, positions and all. Same
     * rewrite semantics as the cart.
     */
    private function persistSavedList(SavedProductList $list, ?User $user = null): void
    {
        $user = $this->resolveUser($user);

        if ($user === null) {
            return;
        }

        $ids = $this->savedIds($list);

        DB::transaction(function () use ($user, $list, $ids): void {
            $stale = SavedProduct::query()
                ->where('user_id', $user->getKey())
                ->where('list', $list);

            if ($ids !== []) {
                $stale->whereNotIn('product_id', $ids);
            }

            $stale->delete();

            foreach ($ids as $position => $productId) {
                SavedProduct::updateOrCreate(
                    ['user_id' => $user->getKey(), 'list' => $list, 'product_id' => $productId],
                    ['position' => $position],
                );
            }
        });
    }

    /**
     * The Login event fires from the guard before it sets the resolved user, so
     * every persistence path takes the user explicitly and only falls back to
     * the guard for ordinary requests.
     */
    private function resolveUser(?User $user): ?User
    {
        if ($user instanceof User) {
            return $user;
        }

        $current = Auth::user();

        return $current instanceof User ? $current : null;
    }

    // ==================================================
    // LOGIN MERGE
    // ==================================================

    private function mergeCartIntoUser(User $user): void
    {
        $lines = $this->rawCart();
        $existing = Cart::query()->with('items')->where('user_id', $user->getKey())->first();

        // A staff member who has never shopped logs in with an empty session
        // and no saved cart: give them no row at all.
        if ($lines === [] && $existing === null) {
            return;
        }

        $cart = $existing ?? Cart::create(['user_id' => $user->getKey()]);

        foreach ($lines as $line) {
            $item = $cart->items()->firstOrNew([
                'product_id' => $line['product_id'],
                'product_variant_id' => $line['variant_id'],
            ]);

            if (! $item->exists) {
                // A line only the guest session had keeps the price it was
                // opened at; an existing line keeps the older commitment.
                $item->unit_price_cents = $line['unit_price_cents'];
            }

            $item->quantity = max((int) $item->quantity, $line['quantity']);
            $item->save();
        }

        $cart->markActive();

        $this->hydrateCartFrom($cart);
    }

    /**
     * Replace the session cart with the merged persisted one, so the live copy
     * covers what the shopper had on every device.
     */
    private function hydrateCartFrom(Cart $cart): void
    {
        $lines = [];

        foreach ($cart->items()->get() as $item) {
            $lines[$this->lineKey($item->product_id, $item->product_variant_id)] = [
                'product_id' => $item->product_id,
                'variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
            ];
        }

        Session::put(self::CART_KEY, $lines);
    }

    /**
     * Saved lists merge as a union — saved order first, then anything new from
     * this session — which is idempotent for the same reason the cart is.
     */
    private function mergeSavedListIntoUser(User $user, SavedProductList $list): void
    {
        $sessionIds = $this->savedIds($list);

        /** @var list<int> $storedIds */
        $storedIds = SavedProduct::query()
            ->forList($user->getKey(), $list)
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($sessionIds === [] && $storedIds === []) {
            return;
        }

        $merged = array_values(array_unique([...$storedIds, ...$sessionIds]));

        if ($list === SavedProductList::Compare && count($merged) > self::COMPARE_LIMIT) {
            $merged = array_slice($merged, -self::COMPARE_LIMIT);
        }

        Session::put($this->savedKey($list), $merged);

        $this->persistSavedList($list, $user);
    }
}
