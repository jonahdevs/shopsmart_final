---
paths:
  - 'database/seeders/**'
---

# Seeders

## Seeders run with model events muted — set package-generated columns explicitly
DatabaseSeeder uses `WithoutModelEvents`, and that mutes events for every seeder it calls. Anything a package generates from a `creating`/`saving` hook will silently be left null:

- spatie/laravel-tags generates `tags.slug` in a `saving` hook. `Tag::findOrCreate()` therefore fails with "Field 'slug' doesn't have a default value". Create tags with an explicit translated slug (and `order_column`) — see TagSeeder::tag().
- medialibrary sets `media.order_column` in the Media `creating` observer. Assign it yourself after `toMediaCollection()` when gallery order matters — see ProductSeeder::attachImages().

Media conversions are unaffected: Filesystem::add() calls FileManipulator::createDerivedFiles() directly, not via an observer.
