---
paths:
  - '**'
---

# General

## Run PHP/artisan through PowerShell, and never inline double quotes in `tinker --execute`
On this Windows machine `php` and `composer` are only on PATH for PowerShell, not for the Bash tool (`php: command not found`). Prefix every command with `Set-Location <project root>;` — the working directory does not always persist.

PowerShell strips double quotes when passing an argument to a native executable, so the documented `php artisan tinker --execute 'DB::table("jobs")->count();'` fails with `Undefined constant "jobs"` or `Unexpected end of input`. Write the snippet to a file (no `<?php` tag) and pipe it in instead:
`Get-Content script.php -Raw | php artisan tinker`

Herd serves the site at https://shopsmart_final.test — never run `php artisan serve`.

## Browser automation cannot drive shopsmart_final.test
The Herd hostname `shopsmart_final.test` contains an underscore, which is not a legal hostname character. Chrome itself tolerates it, but the Claude-in-Chrome extension's URL parser rejects it: every `mcp__claude-in-chrome__*` call returns "Can't interact with browser-internal or unparseable URLs", including screenshot and read_page.

So there is no way to click through the storefront from here. Verify server-side with `curl.exe -sk -o file -w "status=%{http_code}\n" https://shopsmart_final.test/...` (PowerShell 5.1 has no `-SkipHttpErrorCheck`, so `Invoke-WebRequest` cannot read a non-2xx response), and rely on Pest for behaviour. `herd link shopsmart-final` would add an underscore-free alias if a real browser pass is ever needed — ask before running it, it changes the developer's environment.

Note also that `php artisan tinker --execute '...'` DOES work with single quotes; it is only double quotes inside that PowerShell strips. Piping a file into `php artisan tinker` fails on this machine with a T_VARIABLE parse error.

## Always run `npm run build` — a broken install hides real errors as fake ones
`npm run build` and `npx vue-tsc --noEmit -p tsconfig.json` both work and both must pass. Fixed 2026-09-06 by deleting `node_modules` and `package-lock.json` and reinstalling; the tree went from 131 top-level packages to 215.

Two symptoms mean the install has rotted again, and both are npm's optional-dependency bug (npm/cli#4828), never application code:

1. `node_modules/.bin/` is empty, so the `vp` shim (vite-plus) is missing: "'vp' is not recognized as an internal or external command".
2. Running the binary directly then fails with `Cannot find native binding` / `Cannot find module '@rolldown/binding-wasm32-wasi'`.

The fix is the full reinstall above. It is a dependency operation, so ask first — but note it only regenerated the lockfile; `package.json` was untouched and nothing but two patch versions moved.

**Do not dismiss `Property 'form' does not exist on type ...` errors as pre-existing Wayfinder noise.** They were, briefly, believed to be exactly that. They were entirely an artefact of the broken install and vanished on reinstall. `vue-tsc` is now clean, so any such error is real.

The build is also the only check that catches a wrong Wayfinder import: action functions are named after the CONTROLLER METHOD (`updateStatus`, `updateNote`), not after the route name (`status`, `note`). Importing the route name compiles under PHPStan and passes every Pest test, then fails the build with `MISSING_EXPORT`.

## Commit multi-line messages with git commit -F, never -m
PowerShell strips double quotes when passing an argument to a native executable, so `git commit -m` with a message containing a `"` gets split at that point and git fails with a bogus `error: pathspec '...' did not match any file(s)`. A PowerShell here-string (`@'...'@`) does not help — the stripping happens at the native-command boundary.

Write the message to a file and use `git commit -F <file>` instead. This is the same underlying trap already documented for `tinker --execute`, and it bites any native command given a quoted multi-line argument.
