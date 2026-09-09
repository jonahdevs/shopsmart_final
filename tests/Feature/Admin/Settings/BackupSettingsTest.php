<?php

use App\Jobs\RunStoreBackup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * The Backup screen.
 *
 * The listing is read off the disk rather than a table, so the assertions here
 * are about files. The one that matters most is the path check: download and
 * delete both take a path from the request, and anything the listing does not
 * contain has to be a 404 rather than a file handed over or removed.
 */
beforeEach(function () {
    $this->withoutVite();

    $this->seed(PermissionSeeder::class);

    Storage::fake('local');
});

function backupManager(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole('Admin'));
}

function backupFolder(): string
{
    return (string) config('backup.backup.name', config('app.name'));
}

function storeArchive(string $name = '2026-09-09-10-00-00.zip'): string
{
    $path = backupFolder().'/'.$name;

    Storage::disk('local')->put($path, 'zip-bytes');

    return $path;
}

it('lists the archives on the disk, newest first', function () {
    storeArchive('2026-09-01-09-00-00.zip');
    storeArchive('2026-09-08-09-00-00.zip');

    // Written after, so it is the newer of the two whatever the names say.
    touch(Storage::disk('local')->path(backupFolder().'/2026-09-08-09-00-00.zip'), time() + 60);

    $this->actingAs(backupManager())
        ->get(route('admin.settings.backup'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/settings/Backup')
            ->has('backups', 2)
            ->where('backups.0.name', '2026-09-08-09-00-00.zip')
            ->where('disk', 'local'),
        );
});

it('shows an empty listing rather than failing when nothing has been backed up', function () {
    $this->actingAs(backupManager())
        ->get(route('admin.settings.backup'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('backups', 0));
});

it('refuses a staff member without settings.manage', function () {
    $manager = tap(User::factory()->create(), fn (User $user) => $user->assignRole('Manager'));

    $this->actingAs($manager)
        ->get(route('admin.settings.backup'))
        ->assertForbidden();
});

it('queues a full backup rather than running it in the request', function () {
    Queue::fake();

    $this->actingAs(backupManager())
        ->post(route('admin.settings.backup.store'))
        ->assertRedirect(route('admin.settings.backup'));

    Queue::assertPushed(RunStoreBackup::class, fn (RunStoreBackup $job): bool => $job->databaseOnly === false);
});

it('queues a database-only backup when asked for one', function () {
    Queue::fake();

    $this->actingAs(backupManager())
        ->post(route('admin.settings.backup.store'), ['database_only' => '1']);

    Queue::assertPushed(RunStoreBackup::class, fn (RunStoreBackup $job): bool => $job->databaseOnly === true);
});

it('hands over an archive it listed', function () {
    $path = storeArchive();

    $this->actingAs(backupManager())
        ->get(route('admin.settings.backup.download', ['path' => $path]))
        ->assertOk()
        ->assertDownload('2026-09-09-10-00-00.zip');
});

it('refuses to download a path it never listed', function () {
    storeArchive();

    $this->actingAs(backupManager())
        ->get(route('admin.settings.backup.download', ['path' => '../.env']))
        ->assertNotFound();
});

it('deletes an archive it listed', function () {
    $path = storeArchive();

    $this->actingAs(backupManager())
        ->delete(route('admin.settings.backup.destroy'), ['path' => $path])
        ->assertRedirect(route('admin.settings.backup'));

    Storage::disk('local')->assertMissing($path);
});

it('refuses to delete a path it never listed', function () {
    Storage::disk('local')->put('secrets.txt', 'nope');

    $this->actingAs(backupManager())
        ->delete(route('admin.settings.backup.destroy'), ['path' => 'secrets.txt'])
        ->assertNotFound();

    Storage::disk('local')->assertExists('secrets.txt');
});
