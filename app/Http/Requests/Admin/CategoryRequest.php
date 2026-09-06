<?php

namespace App\Http\Requests\Admin;

use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Support\CategoryTree;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Everything the category editor may send, validated once for both create and
 * edit.
 *
 * The rule worth reading is the cycle guard. `parent_id` is the only field that
 * can make the taxonomy unwalkable: filing a category under one of its own
 * descendants produces a loop, and every consumer of the tree —
 * {@see CategoryTree::subtreeIds()}, the breadcrumb trail, the facet roll-up —
 * would then be walking a ring. Those all terminate defensively, but a tree
 * that needs defending is already wrong, so the loop is refused at the door.
 */
abstract class CategoryRequest extends FormRequest
{
    /** The category being edited, or null when one is being created. */
    abstract protected function category(): ?Category;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categoryId = $this->category()?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($categoryId)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Inline SVG rather than an upload: these are single-colour glyphs
            // on nav tiles, and a file per glyph would be a request per tile.
            'icon_svg' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::enum(CategoryStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->assertParentIsNotInOwnSubtree($validator);
        });
    }

    /**
     * The category's own columns, ready for a mass assignment.
     *
     * @return array<string, mixed>
     */
    public function categoryAttributes(): array
    {
        $slug = $this->nullableString('slug');

        return [
            'name' => (string) $this->validated('name'),
            'slug' => $slug ?? $this->uniqueSlugFromName(),
            'parent_id' => $this->nullableInt('parent_id'),
            'description' => $this->nullableString('description'),
            'icon_svg' => $this->nullableString('icon_svg'),
            'status' => (string) $this->validated('status'),
            'sort_order' => $this->nullableInt('sort_order') ?? 0,
            'meta_title' => $this->nullableString('meta_title'),
            'meta_description' => $this->nullableString('meta_description'),
        ];
    }

    /**
     * A category may not be its own parent, nor sit under any of its
     * descendants.
     *
     * Checked against the whole edge list rather than one hop, because the loop
     * a staff member can actually create is rarely direct: A→B→C already
     * exists, and filing A under C is the mistake. One query loads the tree.
     */
    private function assertParentIsNotInOwnSubtree(Validator $validator): void
    {
        $category = $this->category();
        $parentId = $this->nullableInt('parent_id');

        if ($category === null || $parentId === null) {
            return;
        }

        if (in_array($parentId, CategoryTree::load()->subtreeIds($category->getKey()), true)) {
            $validator->errors()->add(
                'parent_id',
                __('A category cannot sit under itself or one of its own subcategories.'),
            );
        }
    }

    private function uniqueSlugFromName(): string
    {
        $base = Str::slug((string) $this->validated('name'));
        $base = $base === '' ? 'category' : $base;
        $slug = $base;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->whereKeyNot($this->category()?->getKey() ?? 0)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private function nullableInt(string $key): ?int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : null;
    }
}
