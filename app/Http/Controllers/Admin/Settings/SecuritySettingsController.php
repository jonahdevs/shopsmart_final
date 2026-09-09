<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSecuritySettingsRequest;
use App\Settings\SecuritySettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How hard it is to get in, and how hard it is to guess your way in.
 */
class SecuritySettingsController extends Controller
{
    public function __construct(private SecuritySettings $security) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/Security', [
            'security' => [
                'require_two_factor' => $this->security->require_two_factor,
                'login_attempts_per_minute' => $this->security->login_attempts_per_minute,
            ],
            /*
              Shown as a warning next to the toggle. Turning the rule on while
              this is false locks the person doing it out of the panel on their
              next request, which is a thing to be told before saving, not after.
            */
            'viewerHasTwoFactor' => request()->user()?->two_factor_confirmed_at !== null,
        ]);
    }

    public function update(UpdateSecuritySettingsRequest $request): RedirectResponse
    {
        $this->security->fill($request->securityValues())->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Security settings saved.'),
        ]);

        return to_route('admin.settings.security');
    }
}
