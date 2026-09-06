<?php

namespace App\Http\Requests\Admin;

use App\Data\AdminRoleOptionData;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Editing a colleague's account.
 *
 * Two refusals before the rules in {@see StaffFormRequest} even run.
 *
 * You cannot edit your own account here. Everything an admin panel would let
 * you change about yourself — name, email, password, 2FA — is in Settings; the
 * only thing this form adds is your own roles, and being able to edit those is
 * exactly how someone widens their own access or removes the last Super Admin
 * by accident. Your own row is read-only, and it takes a second person to
 * change what you may do.
 *
 * And you cannot edit a colleague whose roles you could not have granted. The
 * inverse of escalation is sabotage: without this, a Support account holding
 * `staff.manage` could strip the Admin above it.
 */
class StaffUpdateRequest extends StaffFormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->targetUser();

        if (! $actor instanceof User || $target === null || $target->is($actor)) {
            return false;
        }

        return AdminRoleOptionData::actorMayManage($actor, array_values(array_map(
            static fn (Role $role): string => $role->name,
            $target->roles->all(),
        )));
    }
}
