<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Data\AdminRoleOptionData;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

/**
 * What the invite and edit forms share: the roles rule that stops escalation.
 *
 * The single most dangerous thing `staff.manage` could be is a way to obtain
 * permissions you do not have — invite an account, give it Super Admin, sign in
 * as it, or simply hand a friendly colleague the role and ask. So a role may
 * only be granted when every permission it carries is one the actor already
 * holds. A Super Admin holds everything and is therefore unconstrained; an
 * Admin cannot create a Super Admin; a custom role with `staff.manage` and
 * nothing else can only ever produce accounts as powerless as itself.
 *
 * At least one role is required, because a user with no role is not a staff
 * member at all ({@see User::isStaff()}) — saving an empty list would silently
 * turn a colleague into a customer. Doing that is a deliberate act with its own
 * button and its own confirmation.
 */
abstract class StaffFormRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->targetId()),
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($this->assignableRoleNames())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => __('Choose at least one role. A user with no role is a customer, not a staff member.'),
            'roles.min' => __('Choose at least one role. A user with no role is a customer, not a staff member.'),
            'roles.*.in' => __('You can only assign a role whose permissions you already hold yourself.'),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('roles') || $validator->errors()->has('roles.*')) {
                    return;
                }

                $target = $this->targetUser();

                if ($target === null || ! $this->wouldStrandTheLastSuperAdmin($target)) {
                    return;
                }

                $validator->errors()->add('roles', __(
                    'This is the last :role. Give the role to somebody else before taking it from them.',
                    ['role' => PermissionSeeder::SUPER_ADMIN],
                ));
            },
        ];
    }

    /**
     * The roles this actor may hand out.
     *
     * @return list<string>
     */
    protected function assignableRoleNames(): array
    {
        $actor = $this->user();

        return $actor instanceof User ? AdminRoleOptionData::assignableFor($actor) : [];
    }

    /**
     * The staff member being edited, or null when one is being invited.
     */
    protected function targetUser(): ?User
    {
        $target = $this->route('user');

        return $target instanceof User ? $target : null;
    }

    /**
     * The id the email uniqueness rule must ignore.
     */
    protected function targetId(): ?int
    {
        return $this->targetUser()?->getKey();
    }

    /**
     * Whether saving this form would leave the store with no Super Admin.
     *
     * The one role that can restore every other is not allowed to disappear:
     * without it nobody can grant `roles.manage` again and the store is locked
     * out of its own permission system for good.
     */
    private function wouldStrandTheLastSuperAdmin(User $target): bool
    {
        if (! $target->hasRole(PermissionSeeder::SUPER_ADMIN)) {
            return false;
        }

        /** @var list<string> $submitted */
        $submitted = array_values(array_filter(
            (array) $this->input('roles', []),
            'is_string',
        ));

        if (in_array(PermissionSeeder::SUPER_ADMIN, $submitted, true)) {
            return false;
        }

        return $this->isTheLastSuperAdmin($target);
    }

    private function isTheLastSuperAdmin(User $target): bool
    {
        $role = Role::query()
            ->where('name', PermissionSeeder::SUPER_ADMIN)
            ->where('guard_name', PermissionSeeder::GUARD)
            ->first();

        if ($role === null) {
            return false;
        }

        return $role->users()->whereKeyNot($target->getKey())->doesntExist();
    }
}
