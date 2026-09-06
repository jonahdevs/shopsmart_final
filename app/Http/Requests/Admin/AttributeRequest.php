<?php

namespace App\Http\Requests\Admin;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * An attribute and its values, validated as one payload.
 *
 * Values are part of this request rather than requests of their own because a
 * value has no meaning apart from its attribute — "Large" is only a size — and
 * splitting them would let the pair be saved in two steps that fail separately.
 *
 * `attribute_values` carries a composite unique index on (attribute_id, slug),
 * so slugs are checked against the other rows in the payload here, not just
 * against the database: two new values slugging the same would otherwise pass
 * validation and fail on insert.
 */
abstract class AttributeRequest extends FormRequest
{
    /** The attribute being edited, or null when one is being created. */
    abstract protected function attribute(): ?Attribute;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $attributeId = $this->attribute()?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('attributes', 'slug')->ignore($attributeId)],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],

            'values' => ['nullable', 'array', 'max:200'],
            'values.*.id' => ['nullable', 'integer'],
            'values.*.value' => ['required', 'string', 'max:255'],
            'values.*.label' => ['required', 'string', 'max:255'],
            'values.*.slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            // Only meaningful when the attribute renders as a colour swatch,
            // but validated whichever type is chosen — a hex left behind after
            // a type change is harmless, a malformed one is not.
            'values.*.color_code' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'values.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'values.*.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'values.*.color_code.regex' => __('A colour must be a six-digit hex code, for example #1B4D3E.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $seen = [];

            foreach ($this->valueRows() as $index => $row) {
                $slug = $row['slug'];

                if (isset($seen[$slug])) {
                    $validator->errors()->add(
                        "values.$index.slug",
                        __('Two values of one attribute cannot share a slug.'),
                    );

                    continue;
                }

                $seen[$slug] = true;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function attributeAttributes(): array
    {
        $slug = $this->nullableString('slug');

        return [
            'name' => (string) $this->validated('name'),
            'slug' => $slug ?? $this->uniqueSlugFromName(),
            'type' => (string) $this->validated('type'),
            'is_active' => (bool) $this->validated('is_active'),
            'sort_order' => $this->nullableInt('sort_order') ?? 0,
        ];
    }

    /**
     * The value rows, each with a slug resolved — from what the staff member
     * typed, or from the label when they left it blank.
     *
     * @return list<array{id: int|null, value: string, label: string, slug: string, color_code: string|null, sort_order: int, is_active: bool}>
     */
    public function valueRows(): array
    {
        $rows = [];
        $index = 0;

        foreach ((array) $this->input('values', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = is_scalar($row['label'] ?? null) ? (string) $row['label'] : '';
            $slug = is_string($row['slug'] ?? null) && trim($row['slug']) !== ''
                ? Str::slug(trim($row['slug']))
                : Str::slug($label);

            $rows[] = [
                'id' => is_numeric($row['id'] ?? null) ? (int) $row['id'] : null,
                'value' => is_scalar($row['value'] ?? null) ? (string) $row['value'] : '',
                'label' => $label,
                'slug' => $slug === '' ? 'value-'.($index + 1) : $slug,
                'color_code' => is_string($row['color_code'] ?? null) && trim($row['color_code']) !== ''
                    ? trim($row['color_code'])
                    : null,
                'sort_order' => is_numeric($row['sort_order'] ?? null) ? (int) $row['sort_order'] : $index,
                'is_active' => (bool) ($row['is_active'] ?? false),
            ];

            $index++;
        }

        return $rows;
    }

    private function uniqueSlugFromName(): string
    {
        $base = Str::slug((string) $this->validated('name'));
        $base = $base === '' ? 'attribute' : $base;
        $slug = $base;
        $suffix = 2;

        while (Attribute::query()
            ->where('slug', $slug)
            ->whereKeyNot($this->attribute()?->getKey() ?? 0)
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
