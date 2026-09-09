---
paths:
  - 'app/Settings/**, app/Http/Controllers/Admin/Settings/**, resources/js/pages/admin/settings/**'
---

# Admin Settings

## General is the staff member's own account, not the store
The settings taxonomy in `resources/js/components/admin/settings/settingsNav.ts` follows both reference builds: General holds Profile, Security and Appearance (the shared `settings/` pages), and everything below it is the store and carries `settings.manage`.

`layouts/settings/Layout.vue` therefore renders the same tabs and sub-nav as the admin settings screens. It used to hold a second aside listing the same three links; do not reintroduce one.

Group permissions are filtered by `useSettingsNav()`, so a staff member without `settings.manage` sees General alone and gets no tab strip. Hiding a tab is a courtesy — the `can:` middleware on each route is the boundary.

Maintenance mode is settings-driven, not `artisan down`: `EnsureStoreIsOpen` wraps `routes/shop.php` only, and lets staff and `api/*` through so the panel stays usable and the Paystack webhook still lands.
