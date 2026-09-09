---
paths:
  - 'app/Http/Controllers/Admin/PermissionController.php, routes/admin/access.php'
---

# Admin Admin

## Permissions are code, so the screen is read-only
`PermissionController` is invokable and GET-only on purpose. Permissions come from `PermissionSeeder::PERMISSIONS` and are enforced by `can:` middleware on routes, so a create form would write a row nothing checks and a delete would silently open every route guarding on it. Roles are where the store makes decisions; this screen is the reference a role editor works against.

It sits behind `roles.manage`, not `staff.manage`. Knowing the exact shape of the authorisation model is a step towards working around it.

Group and action labels are derived from the `<resource>.<action>` name, never a lookup table — the same rule `AdminPermissionGroupData` follows. A table drifts from the seeder the first time someone adds a permission and forgets the second file.
