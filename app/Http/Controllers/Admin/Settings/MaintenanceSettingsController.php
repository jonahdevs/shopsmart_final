<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateMaintenanceSettingsRequest;
use App\Settings\MaintenanceSettings;
use App\Support\StorefrontCache;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Whether the shop floor is open to shoppers.
 */
class MaintenanceSettingsController extends Controller
{
    public function __construct(private MaintenanceSettings $maintenance) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/Maintenance', [
            'maintenance' => [
                'maintenance_mode' => $this->maintenance->maintenance_mode,
                'maintenance_message' => $this->maintenance->maintenance_message,
            ],
        ]);
    }

    public function update(UpdateMaintenanceSettingsRequest $request): RedirectResponse
    {
        $this->maintenance->fill($request->maintenanceValues())->save();

        // EnsureStoreIsOpen reads through the cache on every storefront
        // request; nothing observes a settings save.
        StorefrontCache::forgetMaintenance();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Maintenance settings saved.'),
        ]);

        return to_route('admin.settings.maintenance');
    }
}
