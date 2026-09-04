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
