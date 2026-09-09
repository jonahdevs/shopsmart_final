---
paths:
  - 'resources/js/pages/admin/settings/**'
---

# Settings

## Settings navigation is two levels, defined once
`resources/js/components/admin/settings/settingsNav.ts` is the single source of truth for the settings screens and the tabs they sit in. The six tabs and their icons — General, Website, App, System, Financial, Other — are the wryterscript build's `SettingsRegistry::tabs()` and `SettingsNavBuilder::icon()` verbatim. Do not invent a different taxonomy; file a new screen into one of these.

System and Other are declared with no screens yet and are filtered out of every surface by `visibleSettingsGroups`, the same way `SettingsNavBuilder` skips an empty tab. Mail, queues, backups and cache belong there when they arrive.

Navigation is two levels, both borrowed from the wryterscript settings layout. `AdminSettingsTabs.vue` is the underline tab strip of groups across the top; `AdminSettingsSubnav.vue` is the card of that group's screens down the side. `SettingsForm.vue` renders both, between the page header and the fields, because above the header the tabs would separate the title from the screen it names.

The rail's Settings disclosure lists the same three groups, each opening onto its first screen. Adding a screen means editing `settingsNav.ts` only — all three surfaces read from it, and `findSettingsGroup()` is the shared lookup.

A sidebar row that stands for several screens carries `matches` (see `AdminNavItem`), which `AdminNav.isActive` checks before `href`. Without it the group row is dark on every screen but its first.
