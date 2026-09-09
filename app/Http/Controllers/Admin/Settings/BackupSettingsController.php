<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\RunStoreBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The archives the store has taken of itself.
 *
 * Reads the disk the backup package writes to rather than keeping a table of
 * its own: the files are the record, and a table would only be a second one to
 * disagree with them after a manual delete.
 */
class BackupSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/settings/Backup', [
            'backups' => $this->backups(),
            'disk' => $this->diskName(),
        ]);
    }

    /**
     * Ask for a new archive.
     *
     * Queued, so the request answers now and the file appears in the list when
     * the zip is finished. On a `sync` queue this still runs inline, which is
     * what makes it testable.
     */
    public function store(Request $request): RedirectResponse
    {
        $databaseOnly = $request->boolean('database_only');

        RunStoreBackup::dispatch($databaseOnly);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $databaseOnly
                ? __('Database backup started. It will appear below when it is done.')
                : __('Backup started. It will appear below when it is done.'),
        ]);

        return to_route('admin.settings.backup');
    }

    /**
     * Hand one archive over.
     *
     * The path is validated against the listing rather than trusted, so a
     * crafted `..` cannot reach outside the backup folder.
     */
    public function download(Request $request): StreamedResponse
    {
        $path = $this->assertKnownPath((string) $request->query('path', ''));

        return Storage::disk($this->diskName())->download($path);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $path = $this->assertKnownPath((string) $request->input('path', ''));

        Storage::disk($this->diskName())->delete($path);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Backup deleted.'),
        ]);

        return to_route('admin.settings.backup');
    }

    /**
     * Every archive on the disk, newest first.
     *
     * @return array<int, array{path: string, name: string, size: int, created_at: string}>
     */
    private function backups(): array
    {
        $disk = Storage::disk($this->diskName());

        $files = collect($disk->files($this->folder()))
            ->filter(fn (string $path): bool => str_ends_with($path, '.zip'))
            ->map(fn (string $path): array => [
                'path' => $path,
                'name' => basename($path),
                'size' => $disk->size($path),
                'created_at' => now()->setTimestamp($disk->lastModified($path))->toIso8601String(),
            ])
            ->sortByDesc('created_at')
            ->values();

        return $files->all();
    }

    /**
     * Refuse any path the listing does not contain.
     *
     * `basename()` on the way in would be enough for a flat folder, but the
     * listing is the authority on what exists and checking against it survives
     * the folder gaining structure later.
     */
    private function assertKnownPath(string $path): string
    {
        abort_unless(
            collect($this->backups())->contains(fn (array $backup): bool => $backup['path'] === $path),
            404,
        );

        return $path;
    }

    private function diskName(): string
    {
        /** @var array<int, string> $disks */
        $disks = config('backup.backup.destination.disks', ['local']);

        return $disks[0] ?? 'local';
    }

    private function folder(): string
    {
        return (string) config('backup.backup.name', config('app.name'));
    }
}
