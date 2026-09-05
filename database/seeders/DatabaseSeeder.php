<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order is dependency order: tax classes and the taxonomy first, then the
     * catalog that points at them, then the merchandising and social proof
     * layered on top of the catalog.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            TaxClassSeeder::class,
            BrandSeeder::class,
            CategorySeeder::class,
            AttributeSeeder::class,
            ProductSeeder::class,
            HeroSlideSeeder::class,
            TagSeeder::class,
            ReviewSeeder::class,
            CouponSeeder::class,
        ]);

        $this->announceImageConversions();
    }

    /**
     * Media conversions are queued rather than generated inline, which keeps
     * `migrate:fresh --seed` down to seconds instead of many minutes. Nothing
     * renders the derived images until a worker drains that queue, so say so.
     */
    private function announceImageConversions(): void
    {
        $this->command->newLine();
        $this->command->warn('Product and category images are attached, but their conversions (thumb, card, zoom, webp, lqip) are queued.');
        $this->command->line('  Run <options=bold>php artisan queue:work</> (or <options=bold>composer dev</>, which starts a listener) to build them in the background,');
        $this->command->line('  or <options=bold>php artisan media-library:regenerate</> to build them inline now.');
    }
}
