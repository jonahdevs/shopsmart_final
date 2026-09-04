<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Loads the marques carried by the store from `database/data/brands.json`.
 *
 * Brand logos are not part of the demo data set; the reference app stored a
 * `logo` path string on the row, whereas this schema serves brand imagery from
 * the medialibrary `logo` collection, which stays empty until a real asset is
 * uploaded through the admin.
 */
class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/brands.json');

        if (! File::exists($path)) {
            $this->command->error("brands.json not found at {$path}.");

            return;
        }

        /** @var list<array{slug: string, name: string, description?: string|null, website_url?: string|null, is_active?: bool}>|null $rows */
        $rows = json_decode(File::get($path), true);

        if (! is_array($rows)) {
            $this->command->error('Could not parse brands.json: '.json_last_error_msg());

            return;
        }

        $sortOrder = 0;

        foreach ($rows as $row) {
            Brand::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'website_url' => $row['website_url'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'sort_order' => ++$sortOrder,
                    'meta_title' => $row['name'],
                    'meta_description' => $row['description'] ?? null,
                ],
            );
        }

        $this->command->info("Seeded {$sortOrder} brands from brands.json.");
    }
}
