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

## npm run build is broken here; use vue-tsc to verify the frontend
`npm run build` fails on this machine for two pre-existing environmental reasons, neither caused by application code:

1. `node_modules/.bin/` is empty, so the `vp` shim (vite-plus) does not exist: "'vp' is not recognized as an internal or external command".
2. Running it directly (`node node_modules/vite-plus/bin/vp build`) then fails with `Cannot find native binding` / `Cannot find module '@rolldown/binding-wasm32-wasi'` — the npm optional-dependency bug (npm/cli#4828).

The documented fix is deleting `node_modules` and `package-lock.json` and reinstalling, which is a dependency operation needing the user's approval. Until that is done, verify the frontend with `npx vue-tsc --noEmit -p tsconfig.json` instead.

Note that vue-tsc reports many PRE-EXISTING `Property 'form' does not exist on type ...` errors from Wayfinder's route-function typing, across auth pages, storefront components and account pages. Those are not yours — only check that your own files add no new errors.

## Commit multi-line messages with git commit -F, never -m
PowerShell strips double quotes when passing an argument to a native executable, so `git commit -m` with a message containing a `"` gets split at that point and git fails with a bogus `error: pathspec '...' did not match any file(s)`. A PowerShell here-string (`@'...'@`) does not help — the stripping happens at the native-command boundary.

Write the message to a file and use `git commit -F <file>` instead. This is the same underlying trap already documented for `tinker --execute`, and it bites any native command given a quoted multi-line argument.
