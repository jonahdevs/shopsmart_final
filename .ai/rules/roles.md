---
paths:
  - 'app/Http/Controllers/Admin/RoleController.php, app/Http/Controllers/Admin/StaffController.php, resources/js/pages/admin/roles/**'
---

# Roles

## Staff and roles share one screen
`/admin/roles` is the whole Access screen: role cards on top, the staff table below. `RoleController@index` owns both queries; `StaffController` keeps only the writes and redirects to `admin.roles.index`. `/admin/staff` is a `Route::redirect` kept for old links.

The guard is `can:staff.manage`, not `roles.manage`. An Admin holds the first and not the second, so they must still reach the staff table. The controller sends `canManageRoles` and the page hides the role cards when it is false — the role create/edit/store/update/destroy routes still refuse them on their own.

Do not re-split these into two index screens. A role is a definition and a staff member is an instance of it; separated, the question every reader arrives with — who would this affect? — needed two pages.
