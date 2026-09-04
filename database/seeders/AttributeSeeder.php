<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The variant and specification axes the catalog offers.
 *
 * Colour and Size are the two axes {@see ProductSeeder} generates variants
 * from; Material and Capacity exist as display-only specification data.
 */
class AttributeSeeder extends Seeder
{
    /** Slug of the colour axis, used by ProductSeeder to build swatch variants. */
    public const COLOUR_SLUG = 'colour';

    /** Slug of the size axis, used by ProductSeeder to build size variants. */
    public const SIZE_SLUG = 'size';

    public function run(): void
    {
        /** @var list<array{name: string, slug: string, type: AttributeType, values: list<array{value: string, label: string, color_code?: string}>}> $attributes */
        $attributes = [
            [
                'name' => 'Colour',
                'slug' => self::COLOUR_SLUG,
                'type' => AttributeType::Color,
                'values' => [
                    ['value' => 'black', 'label' => 'Black', 'color_code' => '#111827'],
                    ['value' => 'white', 'label' => 'White', 'color_code' => '#F9FAFB'],
                    ['value' => 'silver', 'label' => 'Silver', 'color_code' => '#C0C4CC'],
                    ['value' => 'red', 'label' => 'Red', 'color_code' => '#EF4444'],
                    ['value' => 'blue', 'label' => 'Blue', 'color_code' => '#3B82F6'],
                    ['value' => 'green', 'label' => 'Green', 'color_code' => '#22C55E'],
                ],
            ],
            [
                'name' => 'Size',
                'slug' => self::SIZE_SLUG,
                'type' => AttributeType::Button,
                'values' => [
                    ['value' => 'xs', 'label' => 'Extra Small'],
                    ['value' => 's', 'label' => 'Small'],
                    ['value' => 'm', 'label' => 'Medium'],
                    ['value' => 'l', 'label' => 'Large'],
                    ['value' => 'xl', 'label' => 'Extra Large'],
                ],
            ],
            [
                'name' => 'Material',
                'slug' => 'material',
                'type' => AttributeType::Select,
                'values' => [
                    ['value' => 'stainless-steel', 'label' => 'Stainless Steel'],
                    ['value' => 'aluminium', 'label' => 'Aluminium'],
                    ['value' => 'cotton', 'label' => 'Cotton'],
                    ['value' => 'leather', 'label' => 'Leather'],
                    ['value' => 'plastic', 'label' => 'Plastic'],
                ],
            ],
            [
                'name' => 'Capacity',
                'slug' => 'capacity',
                'type' => AttributeType::Select,
                'values' => [
                    ['value' => '250ml', 'label' => '250 ml'],
                    ['value' => '500ml', 'label' => '500 ml'],
                    ['value' => '1l', 'label' => '1 L'],
                    ['value' => '2l', 'label' => '2 L'],
                ],
            ],
        ];

        $valueCount = 0;

        foreach ($attributes as $index => $definition) {
            $attribute = Attribute::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );

            foreach ($definition['values'] as $position => $value) {
                AttributeValue::updateOrCreate(
                    ['attribute_id' => $attribute->id, 'slug' => Str::slug($value['value'])],
                    [
                        'value' => $value['value'],
                        'label' => $value['label'],
                        'color_code' => $value['color_code'] ?? null,
                        'is_active' => true,
                        'sort_order' => $position + 1,
                    ],
                );

                $valueCount++;
            }
        }

        $this->command->info('Seeded '.count($attributes)." attributes with {$valueCount} values.");
    }
}
