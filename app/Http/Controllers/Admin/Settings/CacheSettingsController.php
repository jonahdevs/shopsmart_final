<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The caches a staff member is allowed to throw away.
 *
 * Deliberately a short, closed list. Every entry either rebuilds itself on the
 * next request or falls back to reading the source files, so the worst outcome
 * is one slow page — clearing a cache here can never leave the application
 * unable to boot.
 */
class CacheSettingsController extends Controller
{
    /**
     * The commands, keyed by what the screen calls them.
     *
     * `optimize:clear` is not among them: it also clears the compiled events
     * and the package manifest, which is a deploy concern rather than something
     * to hand a staff member a button for.
     *
     * @var array<string, array{label: string, commands: array<int, string>}>
     */
    private const CACHES = [
        'application' => ['label' => 'Application', 'commands' => ['cache:clear']],
        'config' => ['label' => 'Configuration', 'commands' => ['config:clear']],
        'route' => ['label' => 'Routes', 'commands' => ['route:clear']],
        'view' => ['label' => 'Views', 'commands' => ['view:clear']],
        'all' => [
            'label' => 'Everything above',
            'commands' => ['cache:clear', 'config:clear', 'route:clear', 'view:clear'],
        ],
    ];

    public function edit(): Response
    {
        return Inertia::render('admin/settings/Cache');
    }

    public function update(Request $request): RedirectResponse
    {
        $key = (string) $request->validate([
            'cache' => ['required', 'string', Rule::in(array_keys(self::CACHES))],
        ])['cache'];

        foreach (self::CACHES[$key]['commands'] as $command) {
            Artisan::call($command);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':cache cache cleared.', ['cache' => self::CACHES[$key]['label']]),
        ]);

        /*
          A full page visit rather than an Inertia partial: clearing the view
          cache deletes the compiled Blade root this response would otherwise be
          rendered through, and the redirect is what gets it recompiled.
        */
        return to_route('admin.settings.cache');
    }
}
