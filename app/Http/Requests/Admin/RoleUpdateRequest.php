<?php

namespace App\Http\Requests\Admin;

use Database\Seeders\PermissionSeeder;

/**
 * Editing a role's name and its permissions.
 *
 * {@see PermissionSeeder::PROTECTED_ROLES} are refused here, on the server.
 * Super Admin is the role that can grant every other, and Admin is what the
 * store runs on; a form that could empty either of them is one misclick from an
 * admin panel nobody can get into. They are defined by the seeder and changed by
 * editing the seeder — a deliberate, reviewable act — not through a screen.
 */
class RoleUpdateRequest extends RoleFormRequest
{
    public function authorize(): bool
    {
        $role = $this->targetRole();

        return $role !== null && ! in_array($role->name, PermissionSeeder::PROTECTED_ROLES, true);
    }
}
