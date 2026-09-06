<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminRoleOptionData;
use App\Data\AdminStaffRowData;
use App\Data\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffIndexRequest;
use App\Http\Requests\Admin\StaffStoreRequest;
use App\Http\Requests\Admin\StaffUpdateRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The people who run the store.
 *
 * "Staff" is not a column: a staff member is a user holding at least one role
 * ({@see User::isStaff()}), so this section is really an editor for one
 * relationship. That has a consequence the UI has to be honest about — taking
 * away somebody's last role does not disable their account, it turns them back
 * into a customer, with their orders and addresses intact. It is a distinct
 * button with its own confirmation rather than a side effect of saving an empty
 * form.
 *
 * No password is ever set from here. {@see invite()} sends Fortify's own reset
 * link and the colleague chooses their own credential, so the store never holds
 * a password two people know.
 *
 * Every write ends by clearing the permission cache. Spatie caches the whole
 * role/permission map, and a stale cache after a demotion is not a display
 * bug — it is the demoted account still being allowed in.
 */
class StaffController extends Controller
{
    use BuildsLikeQueries;

    /** Rows per page in the staff table. */
    private const PER_PAGE = 25;

    public function index(StaffIndexRequest $request): Response
    {
        $viewer = $this->staffMember($request);
        $sort = $request->validated('sort') ?? 'name';
        $direction = $request->validated('direction') ?? 'asc';

        $staff = User::query()
            ->whereHas('roles')
            ->with('roles:id,name')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $assignable = AdminRoleOptionData::assignableFor($viewer);

        return Inertia::render('admin/staff/Index', [
            'staff' => array_values(array_map(
                fn (User $user): AdminStaffRowData => AdminStaffRowData::fromModel($user, $viewer, $assignable),
                $staff->items(),
            )),
            'pagination' => PaginationData::fromPaginator($staff),
            'filters' => [
                'search' => $request->validated('search'),
                'role' => $request->validated('role'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'roleOptions' => AdminRoleOptionData::forActor($viewer),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/staff/Create', [
            'roleOptions' => AdminRoleOptionData::forActor($this->staffMember($request)),
        ]);
    }

    /**
     * Invite a colleague.
     *
     * The account is created with a random password nobody is told and nobody
     * keeps: it exists only because the column is not nullable, and the reset
     * link that goes out immediately is the only way in. That is why there is no
     * password field on the form — the colleague's first credential should be
     * one only they have ever seen.
     */
    public function store(StaffStoreRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => (string) $request->validated('name'),
            'email' => (string) $request->validated('email'),
            'password' => Str::password(64),
        ]);

        $user->syncRoles($this->submittedRoles($request));

        $this->forgetPermissionCache();

        $status = $this->sendInvitation($user);

        Inertia::flash('toast', [
            'type' => $status === Password::RESET_LINK_SENT ? 'success' : 'warning',
            'message' => $status === Password::RESET_LINK_SENT
                ? __(':name has been invited. They will set their own password from the email.', ['name' => $user->name])
                : __(':name was added, but the invitation email could not be sent. Send it again from their row.', ['name' => $user->name]),
        ]);

        return to_route('admin.staff.index');
    }

    public function edit(Request $request, User $user): Response
    {
        $viewer = $this->staffMember($request);

        $this->guardTarget($viewer, $user);

        $user->load('roles:id,name');

        return Inertia::render('admin/staff/Edit', [
            'member' => AdminStaffRowData::fromModel($user, $viewer, AdminRoleOptionData::assignableFor($viewer)),
            'roleOptions' => AdminRoleOptionData::forActor($viewer),
        ]);
    }

    public function update(StaffUpdateRequest $request, User $user): RedirectResponse
    {
        $this->guardTarget($this->staffMember($request), $user);

        $user->update([
            'name' => (string) $request->validated('name'),
            'email' => (string) $request->validated('email'),
        ]);

        $user->syncRoles($this->submittedRoles($request));

        $this->forgetPermissionCache();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Staff account updated.')]);

        return to_route('admin.staff.index');
    }

    /**
     * Send the invitation again.
     *
     * Reset links expire, and an invitation that has gone stale is the most
     * ordinary reason a new colleague cannot get in. Nothing about the account
     * changes here.
     */
    public function invite(Request $request, User $user): RedirectResponse
    {
        $this->guardTarget($this->staffMember($request), $user);

        $status = $this->sendInvitation($user);

        Inertia::flash('toast', [
            'type' => $status === Password::RESET_LINK_SENT ? 'success' : 'warning',
            'message' => $status === Password::RESET_LINK_SENT
                ? __('Invitation sent to :email.', ['email' => $user->email])
                : __('That invitation could not be sent. Try again shortly.'),
        ]);

        return back();
    }

    /**
     * Revoke staff access.
     *
     * The account is not deleted. It has orders, addresses and reviews hanging
     * off it, and destroying a user to remove their admin access would take a
     * customer's history with it. Every role comes off instead, which is exactly
     * what makes somebody a customer again — and it can be undone by granting a
     * role back.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $viewer = $this->staffMember($request);

        $this->guardTarget($viewer, $user);

        if ($this->isTheLastSuperAdmin($user)) {
            return back()->withErrors([
                'roles' => __(
                    'This is the last :role. Give the role to somebody else before revoking this account.',
                    ['role' => PermissionSeeder::SUPER_ADMIN],
                ),
            ]);
        }

        $user->syncRoles([]);

        $this->forgetPermissionCache();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name no longer has staff access and is a customer again.', ['name' => $user->name]),
        ]);

        return to_route('admin.staff.index');
    }

    /**
     * The three refusals every write on somebody else's account shares.
     *
     * A customer is a 404 rather than a 403 — there is no staff account at that
     * id, and saying "yes, but not for you" about a shopper's row tells the
     * asker something they did not have.
     */
    private function guardTarget(User $viewer, User $target): void
    {
        abort_unless($target->isStaff(), 404);

        // Your own account is managed in Settings. Editing your own roles is
        // how the last Super Admin demotes themselves and how anyone else
        // quietly widens their own access.
        abort_if($target->is($viewer), 403);

        abort_unless(
            AdminRoleOptionData::actorMayManage($viewer, array_values(array_map(
                static fn (Role $role): string => $role->name,
                $target->roles->all(),
            ))),
            403,
        );
    }

    /**
     * @return list<string>
     */
    private function submittedRoles(StaffStoreRequest|StaffUpdateRequest $request): array
    {
        /** @var list<string> $roles */
        $roles = array_values(array_filter((array) $request->validated('roles', []), 'is_string'));

        return $roles;
    }

    /**
     * Fortify's own password reset link, used as the invitation.
     */
    private function sendInvitation(User $user): string
    {
        return Password::broker()->sendResetLink(['email' => $user->email]);
    }

    private function isTheLastSuperAdmin(User $user): bool
    {
        if (! $user->hasRole(PermissionSeeder::SUPER_ADMIN)) {
            return false;
        }

        $role = Role::query()
            ->where('name', PermissionSeeder::SUPER_ADMIN)
            ->where('guard_name', PermissionSeeder::GUARD)
            ->first();

        return $role !== null && $role->users()->whereKeyNot($user->getKey())->doesntExist();
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyFilters(Builder $query, StaffIndexRequest $request): void
    {
        $search = $request->validated('search');

        if (is_string($search) && trim($search) !== '') {
            $pattern = $this->containsPattern(trim($search));

            $query->where(function (Builder $match) use ($pattern): void {
                $match
                    ->whereRaw($this->likeExpression('name'), [$pattern])
                    ->orWhereRaw($this->likeExpression('email'), [$pattern]);
            });
        }

        $query->when(
            $request->validated('role'),
            fn (Builder $q, string $role) => $q->whereHas('roles', fn (Builder $roles) => $roles->where('name', $role)),
        );
    }

    /**
     * The signed-in staff member. The route group guarantees one, so this
     * narrows the type rather than making a decision.
     */
    private function staffMember(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
