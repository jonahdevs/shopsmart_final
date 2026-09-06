<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\Permission\Models\Role;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One colleague in the staff table.
 *
 * Built field by field from the model rather than by serialising it: `User`
 * hides its password hash, 2FA secret, recovery codes and remember token, and
 * the safest way to keep it that way is for this object to name every field it
 * sends. Nothing about credentials belongs in a page prop, and there is no line
 * here that could accidentally start carrying one.
 *
 * `manageable` says whether the *viewer* may act on this row — see
 * {@see AdminRoleOptionData}. `isSelf` is separate because your own account is
 * never editable from here: it is what stops the last Super Admin demoting
 * themselves by accident, and what stops anyone quietly widening their own
 * access. Your own name and email live in Settings.
 */
#[TypeScript]
class AdminStaffRowData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public array $roles,
        /** Null until the colleague has verified their address. */
        public ?string $emailVerifiedAt,
        /** True while the invitation is still outstanding. */
        public bool $invitationPending,
        public string $createdAt,
        /** True when this row is the signed-in staff member's own account. */
        public bool $isSelf,
        /** True when the viewer holds every permission this account's roles carry. */
        public bool $manageable,
    ) {}

    /**
     * The assignable list is passed in rather than resolved here: it is the
     * same for every row on the page, and working it out per row would put a
     * roles-and-permissions query behind each of the 25.
     *
     * @param  list<string>  $assignableRoles  Roles the viewer may grant or revoke.
     */
    public static function fromModel(User $user, User $viewer, array $assignableRoles): self
    {
        $roles = array_values(array_map(
            static fn (Role $role): string => $role->name,
            $user->roles->all(),
        ));

        return new self(
            id: $user->getKey(),
            name: $user->name,
            email: $user->email,
            roles: $roles,
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            invitationPending: $user->email_verified_at === null,
            createdAt: $user->created_at?->toIso8601String() ?? '',
            isSelf: $user->is($viewer),
            manageable: ! $user->is($viewer) && self::mayManage($roles, $assignableRoles),
        );
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $assignableRoles
     */
    private static function mayManage(array $roles, array $assignableRoles): bool
    {
        foreach ($roles as $role) {
            if (! in_array($role, $assignableRoles, true)) {
                return false;
            }
        }

        return true;
    }
}
