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
