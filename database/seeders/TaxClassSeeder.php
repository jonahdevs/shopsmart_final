<?php

namespace Database\Seeders;

use App\Models\TaxClass;
use App\Settings\TaxSettings;
use Illuminate\Database\Seeder;

/**
 * The Kenyan VAT bands a product can be assigned to.
 *
 * Products with no class of their own fall back to the store default in
 * {@see TaxSettings::$default_tax_class_id}, which this seeder points at the
 * standard-rated band so tax actually resolves on a fresh install.
 */
class TaxClassSeeder extends Seeder
{
    /** The slug of the band used as the store-wide fallback. */
    public const DEFAULT_SLUG = 'standard-rated';

    public function run(): void
    {
        /** @var list<array{name: string, slug: string, rate: float, description: string}> $classes */
        $classes = [
            [
                'name' => 'Standard rated',
                'slug' => self::DEFAULT_SLUG,
                'rate' => 16.0,
                'description' => 'Standard Kenyan VAT at 16%. The default for most goods.',
            ],
            [
                'name' => 'Zero rated',
                'slug' => 'zero-rated',
                'rate' => 0.0,
                'description' => 'Zero-rated supplies such as exports and certain foodstuffs. VAT is charged at 0% and input tax is still recoverable.',
            ],
            [
                'name' => 'Exempt',
                'slug' => 'exempt',
                'rate' => 0.0,
                'description' => 'VAT-exempt supplies. No VAT is charged and input tax is not recoverable.',
            ],
        ];

        foreach ($classes as $class) {
            TaxClass::updateOrCreate(
                ['slug' => $class['slug']],
                [
                    'name' => $class['name'],
                    'rate' => $class['rate'],
                    'description' => $class['description'],
                    'is_active' => true,
                ],
            );
        }

        $standard = TaxClass::query()->where('slug', self::DEFAULT_SLUG)->first();

        $settings = app(TaxSettings::class);
        $settings->default_tax_class_id = $standard?->id;
        $settings->save();

        $this->command->info('Seeded '.count($classes).' tax classes; store default set to "Standard rated".');
    }
}
