<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the admin panel's roles and permissions.
 *
 * Idempotent: every role and permission is resolved with firstOrCreate and the
 * permission sets are synced, so re-running only reconciles the matrix.
 */
class PermissionSeeder extends Seeder
{
    /** The guard every role and permission is registered against. */
    public const GUARD = 'web';

    /** Role granted every permission. */
    public const SUPER_ADMIN = 'Super Admin';

    /**
     * Roles that cannot be edited or deleted through the UI.
     *
     * @var list<string>
     */
    public const PROTECTED_ROLES = [self::SUPER_ADMIN, 'Admin'];

    /**
     * Full set of admin-panel permissions grouped by resource segment.
     *
     * Naming: <resource>.<action>
     *   .view   — read-only access (list + show)
     *   .manage — full CRUD
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        // Products
        'products.view',
        'products.manage',

        // Catalog (categories, brands, attributes)
        'catalog.manage',

        // Orders
        'orders.view',
        'orders.manage',

        // Payments
        'payments.view',
        'payments.manage',

        // Customers
        'customers.view',
        'customers.manage',

        // Reviews
        'reviews.manage',

        // Marketing (subscribers, campaigns, coupons…)
        'marketing.manage',

        // System
        'settings.manage',

        // Staff
        'staff.manage',

        // Roles & permissions — Super Admin only
        'roles.manage',

        // Activity log
        'activity.view',
    ];

    /**
     * Permissions granted to the "Admin" role.
     * Admins can do everything except manage roles and permissions.
     *
     * @var list<string>
     */
    public const ADMIN_PERMISSIONS = [
        'products.view',
        'products.manage',
        'catalog.manage',
        'orders.view',
        'orders.manage',
        'payments.view',
        'payments.manage',
        'customers.view',
        'customers.manage',
        'reviews.manage',
        'marketing.manage',
        'settings.manage',
        'staff.manage',
        'activity.view',
    ];

    /**
     * Permissions granted to the "Manager" role.
     * Managers run day-to-day trading — catalog, orders, payments, customers
     * and marketing — but cannot touch system config, staff accounts, roles or
     * the activity log.
     *
     * @var list<string>
     */
    public const MANAGER_PERMISSIONS = [
        'products.view',
        'products.manage',
        'catalog.manage',
        'orders.view',
        'orders.manage',
        'payments.view',
        'payments.manage',
        'customers.view',
        'customers.manage',
        'reviews.manage',
        'marketing.manage',
    ];

    /**
     * Permissions granted to the "Support" role.
     * Support can action orders and moderate reviews but cannot change catalog
     * structure, customer records, payments, staff accounts or system config.
     *
     * @var list<string>
     */
    public const SUPPORT_PERMISSIONS = [
        'products.view',
        'orders.view',
        'orders.manage',
        'payments.view',
        'customers.view',
        'reviews.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        $this->role(self::SUPER_ADMIN)->syncPermissions(self::PERMISSIONS);
        $this->role('Admin')->syncPermissions(self::ADMIN_PERMISSIONS);
        $this->role('Manager')->syncPermissions(self::MANAGER_PERMISSIONS);
        $this->role('Support')->syncPermissions(self::SUPPORT_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
    }
}
