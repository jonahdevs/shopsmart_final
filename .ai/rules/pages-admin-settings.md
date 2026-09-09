---
paths:
  - 'resources/js/components/admin/settings/**, resources/js/pages/admin/settings/**'
---

# Pages Admin Settings

## Settings screens compose SettingsScreen, not the chrome directly
`SettingsScreen.vue` owns the page header, the tab strip and the sub-nav. Never render `AdminSettingsTabs` or `AdminSettingsSubnav` in a page.

A screen that saves a settings group uses `SettingsForm.vue`, which wraps `SettingsScreen` in the `<Form>` so the Save button sits in the header with `processing` in scope.

A screen that only performs actions — Cache, Backup — uses `SettingsScreen` directly and puts its own buttons in the `#actions` slot. Each action is its own small `<Form>` with a hidden input, so the button is a real submit rather than a click handler assembling a request.

`layouts/settings/Layout.vue` composes it too, which is how the account pages get the same chrome as the General tab.
