<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\SeedsDemoHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Everyone who can sign in: staff, the two named shoppers, and the customer
 * list behind them.
 *
 * Runs early — {@see ReviewSeeder} attributes reviews to the named shopper and
 * {@see OrderSeeder} sells to the whole list, so both need these rows to exist
 * first.
 *
 * Registrations are spread across the trading window rather than all created
 * "now", because "new customers" on the admin dashboard is a period-on-period
 * comparison. Forty-seven accounts sharing one timestamp produce a flat line
 * and a null delta.
 */
class UserSeeder extends Seeder
{
    use SeedsDemoHistory;

    /** Generated shoppers behind the named ones. */
    private const CUSTOMERS = 45;

    /**
     * The one password every seeded account shares.
     *
     * Fine here and nowhere else: this seeder only ever runs against a demo
     * database. {@see DatabaseSeeder} is the only caller, and a deployment that
     * runs it is already publishing a store full of invented orders.
     */
    private const DEMO_PASSWORD = 'password';

    /**
     * The furnished shopper account — orders, addresses, reviews to write.
     *
     * Public because {@see ReviewSeeder} runs between this seeder and
     * {@see OrderSeeder} and has to attribute reviews to the same person.
     * Three copies of the same literal is how the demo shopper ends up with no
     * reviews and nobody notices.
     */
    public const DEMO_SHOPPER_EMAIL = 'customer@shopsmart.com';

    /** The untouched one, kept out of the order pool so empty states are reachable. */
    public const NEW_SHOPPER_EMAIL = 'peter.kimani@gmail.com';

    /**
     * Signed-in accounts anyone can pick up and use.
     *
     * A store you cannot log into is a store you cannot look at. Four staff
     * accounts — one per seeded role, so the permission-filtered sidebar can be
     * demonstrated rather than described — and two shoppers, because the
     * account area has two completely different faces and only one of them is
     * reachable from a furnished account.
     *
     * @var list<array{role: string|null, email: string, name: string, note: string}>
     */
    private const DEMO_ACCOUNTS = [
        ['role' => 'Super Admin', 'email' => 'jonah@shopsmart.com', 'name' => 'Jonah Wakahiu', 'note' => 'every permission, including roles'],
        ['role' => 'Admin', 'email' => 'admin@shopsmart.com', 'name' => 'ShopSmart Admin', 'note' => 'everything except roles'],
        ['role' => 'Manager', 'email' => 'james.mwangi@shopsmart.com', 'name' => 'James Mwangi', 'note' => 'catalog and orders, no settings'],
        ['role' => 'Support', 'email' => 'grace.njeri@shopsmart.com', 'name' => 'Grace Njeri', 'note' => 'orders and customers only'],
        ['role' => null, 'email' => self::DEMO_SHOPPER_EMAIL, 'name' => 'Anita Wanjiru', 'note' => 'orders, addresses, reviews to write'],
        ['role' => null, 'email' => self::NEW_SHOPPER_EMAIL, 'name' => 'Peter Kimani', 'note' => 'nothing yet — the empty states'],
    ];

    /**
     * The pool the generated shoppers are drawn from.
     *
     * The factory falls back to Faker's default locale, which fills a Kenyan
     * store's customer list with American names — the one place in a demo where
     * the data being invented becomes obvious at a glance. Kept here rather than
     * in UserFactory because the factory serves the test suite, where a name is
     * noise and changing it would churn fixtures for no gain.
     *
     * @var list<string>
     */
    private const FIRST_NAMES = [
        'Achieng', 'Amina', 'Anita', 'Brian', 'Caroline', 'Cynthia', 'Daniel',
        'David', 'Dennis', 'Elizabeth', 'Esther', 'Faith', 'Fatuma', 'Felix',
        'Gideon', 'Grace', 'Hassan', 'Irene', 'James', 'Janet', 'Joseph',
        'Joyce', 'Kevin', 'Linda', 'Lucy', 'Mercy', 'Michael', 'Nancy',
        'Nicholas', 'Otieno', 'Patrick', 'Peter', 'Purity', 'Rose', 'Samuel',
        'Sarah', 'Stephen', 'Susan', 'Teresia', 'Victor', 'Wanjiku', 'Zipporah',
    ];

    /** @var list<string> */
    private const SURNAMES = [
        'Achieng', 'Barasa', 'Chebet', 'Gitau', 'Hassan', 'Kamau', 'Kariuki',
        'Kiplagat', 'Kimani', 'Kipchoge', 'Koech', 'Maina', 'Mbugua', 'Mutiso',
        'Muthoni', 'Mwangi', 'Njeri', 'Njoroge', 'Nyambura', 'Ochieng', 'Odhiambo',
        'Okello', 'Omondi', 'Onyango', 'Otieno', 'Ouma', 'Wafula', 'Wairimu',
        'Wanjala', 'Wanjiru', 'Waweru',
    ];

    public function run(): void
    {
        /*
          Unguarded because back-dating is the point. `created_at` and
          `updated_at` appear in no `#[Fillable]` list — correctly, nothing in
          the application should mass-assign them — so `create()` would silently
          drop them and stack every registration onto today.
        */
        Model::unguarded(function (): void {
            $this->createStaff();
            $this->createDemoShopper();
            $this->createUntouchedShopper();
            $this->createCustomers();
        });

        $this->announceAccounts();
    }

    /**
     * One login per seeded role, so the permission-filtered sidebar can
     * actually be demonstrated rather than described.
     */
    private function createStaff(): void
    {
        foreach (self::DEMO_ACCOUNTS as $account) {
            if ($account['role'] === null) {
                continue;
            }

            $this->demoAccount($account['email'], $account['name'], now())
                ->syncRoles([$account['role']]);
        }
    }

    /**
     * The shopper whose account is worth opening: saved addresses now, and
     * orders once {@see OrderSeeder} runs.
     *
     * Registered at the far end of the window so every back-dated order in that
     * run can legally belong to them — an order may not predate its customer.
     */
    private function createDemoShopper(): User
    {
        $account = $this->accountFor(self::DEMO_SHOPPER_EMAIL);

        $shopper = $this->demoAccount(
            $account['email'],
            $account['name'],
            now()->subDays(self::WINDOW_DAYS),
        );

        Address::factory()->isDefault()->for($shopper)->create([
            'label' => 'Home',
            'city' => 'Nairobi',
            'county' => 'Nairobi',
        ]);
        Address::factory()->for($shopper)->create(['label' => 'Office']);

        return $shopper;
    }

    /**
     * A shopper who has done nothing at all.
     *
     * Half the account area is empty states — no orders, no addresses, nothing
     * to review — and on a store seeded with four months of trading there is
     * otherwise no way to see any of them without emptying the database.
     * {@see OrderSeeder} deliberately keeps this account out of its pool.
     */
    private function createUntouchedShopper(): void
    {
        $account = $this->accountFor(self::NEW_SHOPPER_EMAIL);

        $this->demoAccount($account['email'], $account['name'], now()->subHours(2));
    }

    /**
     * Idempotent so re-running the seeder does not fail on the unique email and
     * does not silently leave a stale password behind either.
     */
    private function demoAccount(string $email, string $name, CarbonImmutable|Carbon $registeredAt): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'password' => Hash::make(self::DEMO_PASSWORD),
            'email_verified_at' => $registeredAt,
            'created_at' => $registeredAt,
            'updated_at' => $registeredAt,
        ])->save();

        return $user;
    }

    /**
     * @return array{role: string|null, email: string, name: string, note: string}
     */
    private function accountFor(string $email): array
    {
        foreach (self::DEMO_ACCOUNTS as $account) {
            if ($account['email'] === $email) {
                return $account;
            }
        }

        throw new InvalidArgumentException("No demo account is declared for {$email}.");
    }

    /**
     * The customer list behind the named accounts.
     *
     * @return Collection<int, User>
     */
    private function createCustomers(): Collection
    {
        $taken = [];

        return Collection::times(self::CUSTOMERS, function () use (&$taken): User {
            $registeredAt = $this->momentInWindow();
            [$name, $email] = $this->kenyanIdentity($taken);

            $customer = User::factory()->create([
                'name' => $name,
                'email' => $email,
                'created_at' => $registeredAt,
                'updated_at' => $registeredAt,
                'email_verified_at' => $registeredAt,
            ]);

            // Most shoppers save one address; a few save a second.
            Address::factory()->isDefault()->for($customer)->create();

            if (fake()->boolean(25)) {
                Address::factory()->for($customer)->create();
            }

            return $customer;
        });
    }

    /**
     * A Kenyan name and a matching consumer email address.
     *
     * The address is built from the name rather than randomised, because a
     * customer list where the name and the email have nothing to do with each
     * other reads as generated the moment anyone looks at two rows together.
     *
     * @param  list<string>  $taken  Addresses already handed out, appended to in place.
     * @return array{0: string, 1: string}
     */
    private function kenyanIdentity(array &$taken): array
    {
        $first = fake()->randomElement(self::FIRST_NAMES);
        $surname = fake()->randomElement(self::SURNAMES);
        $domain = fake()->randomElement(['gmail.com', 'yahoo.com', 'outlook.com']);

        $base = Str::lower("{$first}.{$surname}");
        $email = "{$base}@{$domain}";

        // A pool this size collides often enough to matter; the suffix keeps the
        // address readable where a random string would not.
        $suffix = 2;

        while (in_array($email, $taken, true)) {
            $email = "{$base}{$suffix}@{$domain}";
            $suffix++;
        }

        $taken[] = $email;

        return ["{$first} {$surname}", $email];
    }

    /**
     * Print the logins, because credentials nobody can find are credentials
     * nobody uses — and the alternative is a README that goes stale.
     */
    private function announceAccounts(): void
    {
        $this->command->newLine();
        $this->command->line('  <options=bold>Sign in with any of these — the password is always</> <fg=yellow;options=bold>'.self::DEMO_PASSWORD.'</>');
        $this->command->newLine();

        $this->command->table(
            ['Email', 'Name', 'Role', 'What it shows'],
            array_map(
                static fn (array $account): array => [
                    $account['email'],
                    $account['name'],
                    $account['role'] ?? 'Customer',
                    $account['note'],
                ],
                self::DEMO_ACCOUNTS,
            ),
        );
    }
}
