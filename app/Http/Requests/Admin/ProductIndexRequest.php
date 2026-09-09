<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Enums\StockStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The products table's filter bar, validated.
 *
 * Every filter is optional and every one is checked against a closed set — the
 * sort column especially, which is interpolated into an `orderBy` and would
 * otherwise be the one place on this page a query string reaches SQL.
 */
class ProductIndexRequest extends FormRequest
{
    /**
     * Columns the table may be sorted by. Anything else is rejected before it
     * can reach `orderBy`. `price` is not a column sort — the controller turns
     * it into the COALESCE(sale_price, price) expression the storefront sorts
     * by, so a discounted product sorts where the shopper sees it.
     *
     * @var list<string>
     */
    public const SORTABLE = ['name', 'sku', 'price', 'stock_quantity', 'sort_order', 'created_at', 'updated_at'];

    /**
     * Which side of the soft-delete line the table shows.
     *
     * @var list<string>
     */
    public const TRASHED = ['with', 'only'];

    /**
     * The one `stock_status` filter value that is not a column value.
     *
     * "Low stock" is a comparison against the store's threshold, not a state a
     * product can be saved in — `products.stock_status` has exactly three legal
     * values and the editor's picker must keep offering only those. It rides on
     * this filter anyway because a staff member reading the Stock column is
     * asking one question, and splitting it across two controls would make them
     * ask it twice.
     */
    public const LOW_STOCK = 'low_stock';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'visibility' => ['nullable', Rule::enum(ProductVisibility::class)],
            'stock_status' => ['nullable', Rule::in([
                ...array_column(StockStatus::cases(), 'value'),
                self::LOW_STOCK,
            ])],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'brand' => ['nullable', 'integer', 'exists:brands,id'],
            'trashed' => ['nullable', Rule::in(self::TRASHED)],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
