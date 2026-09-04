<?php

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * Generates TypeScript definitions for the Data objects and enums that cross the
 * Inertia boundary, so Vue pages get the same types the server sends.
 *
 * Output lands in `resources/js/types/generated.d.ts`, which is picked up by the
 * `@/types` barrel. Regenerate with `php artisan typescript:transform`.
 */
class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(app_path('Data'), app_path('Enums'))
            ->outputDirectory(resource_path('js/types'))
            // No formatter: this project formats JS/TS with oxfmt (via vite-plus),
            // and Prettier is not installed.
            ->writer(new GlobalNamespaceWriter('generated.d.ts'));
    }
}
