<?php

namespace Modules\Corsec\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\LibraryItem;

/**
 * Fix for QAX Pentest Report 2026-08-19, finding 4.1
 * "Unauthenticated attachment download" (High Risk).
 *
 * Moves every corsec_attachments / corsec_library_items row still pointing
 * at the 'public' disk over to the 'private' disk, copying the underlying
 * file on disk and only deleting the public copy once the private copy is
 * confirmed to exist. Safe to re-run: rows already on 'private' are skipped.
 *
 * Usage:
 *   php artisan corsec:migrate-attachments-to-private            # dry run
 *   php artisan corsec:migrate-attachments-to-private --apply     # do it for real
 *   php artisan corsec:migrate-attachments-to-private --apply --keep-old
 *       (keeps the old public copy on disk instead of deleting it — useful
 *        if you want to double check before reclaiming disk space)
 */
class MigrateAttachmentsToPrivateDiskCommand extends Command
{
    protected $signature = 'corsec:migrate-attachments-to-private
                            {--apply : Actually perform the migration. Without this flag, only a dry-run report is printed.}
                            {--keep-old : Do not delete the old file from the public disk after a successful copy.}';

    protected $description = 'Move corsec attachments/library files from the public disk to the private disk and update their DB records (fix for QAX pentest finding 4.1).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $keepOld = (bool) $this->option('keep-old');

        if (!$apply) {
            $this->warn('Dry-run mode. No files or DB rows will be changed. Pass --apply to execute.');
        }

        $attachmentStats = $this->migrateAttachments($apply, $keepOld);
        $libraryStats = $this->migrateLibraryItems($apply, $keepOld);

        $this->newLine();
        $this->info(sprintf(
            'Attachments: %d migrated, %d skipped (already private), %d failed.',
            $attachmentStats['migrated'],
            $attachmentStats['skipped'],
            $attachmentStats['failed']
        ));
        $this->info(sprintf(
            'Library items: %d migrated, %d skipped (already private), %d failed.',
            $libraryStats['migrated'],
            $libraryStats['skipped'],
            $libraryStats['failed']
        ));

        if (!$apply) {
            $this->newLine();
            $this->comment('Re-run with --apply once you are happy with the numbers above.');
        }

        $totalFailed = $attachmentStats['failed'] + $libraryStats['failed'];

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migrateAttachments(bool $apply, bool $keepOld): array
    {
        $stats = ['migrated' => 0, 'skipped' => 0, 'failed' => 0];

        Attachment::withTrashed()
            ->where('disk', 'public')
            ->orderBy('id')
            ->chunkById(100, function ($attachments) use ($apply, $keepOld, &$stats) {
                foreach ($attachments as $attachment) {
                    $this->migrateOne(
                        label: "Attachment #{$attachment->id} ({$attachment->path})",
                        oldDisk: 'public',
                        newDisk: 'private',
                        path: (string) $attachment->path,
                        apply: $apply,
                        keepOld: $keepOld,
                        onSuccess: function () use ($attachment) {
                            $attachment->forceFill(['disk' => 'private'])->save();
                        },
                        stats: $stats,
                    );
                }
            });

        return $stats;
    }

    private function migrateLibraryItems(bool $apply, bool $keepOld): array
    {
        $stats = ['migrated' => 0, 'skipped' => 0, 'failed' => 0];

        LibraryItem::withTrashed()
            ->where('file_disk', 'public')
            ->orderBy('id')
            ->chunkById(100, function ($items) use ($apply, $keepOld, &$stats) {
                foreach ($items as $item) {
                    $this->migrateOne(
                        label: "LibraryItem #{$item->id} ({$item->file_path})",
                        oldDisk: 'public',
                        newDisk: 'private',
                        path: (string) $item->file_path,
                        apply: $apply,
                        keepOld: $keepOld,
                        onSuccess: function () use ($item) {
                            $item->forceFill(['file_disk' => 'private'])->save();
                        },
                        stats: $stats,
                    );
                }
            });

        return $stats;
    }

    /**
     * @param  callable  $onSuccess  Called after the file copy succeeds, to update the owning DB row.
     */
    private function migrateOne(
        string $label,
        string $oldDisk,
        string $newDisk,
        string $path,
        bool $apply,
        bool $keepOld,
        callable $onSuccess,
        array &$stats,
    ): void {
        if ($path === '') {
            $this->line("<fg=yellow>SKIP</> {$label} — empty path.");
            $stats['skipped']++;

            return;
        }

        $old = Storage::disk($oldDisk);
        $new = Storage::disk($newDisk);

        if (!$old->exists($path)) {
            // Nothing on the old disk (maybe already moved by hand, or the
            // row is stale). If it's already on the new disk we just fix
            // the DB flag; otherwise we flag it as failed for manual review.
            if ($new->exists($path)) {
                $this->line("<fg=green>OK</>   {$label} — file already present on '{$newDisk}', fixing DB flag only.");
                if ($apply) {
                    $onSuccess();
                }
                $stats['migrated']++;

                return;
            }

            $this->line("<fg=red>FAIL</> {$label} — file not found on either disk.");
            $stats['failed']++;

            return;
        }

        if (!$apply) {
            $this->line("<fg=cyan>WOULD MOVE</> {$label}");
            $stats['migrated']++;

            return;
        }

        try {
            $stream = $old->readStream($path);

            if ($stream === false || $stream === null) {
                throw new \RuntimeException('Could not open read stream on source disk.');
            }

            $new->put($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if (!$new->exists($path)) {
                throw new \RuntimeException('Copy verification failed: file missing on destination disk.');
            }

            $onSuccess();

            if (!$keepOld) {
                $old->delete($path);
            }

            $this->line("<fg=green>OK</>   {$label} — moved to '{$newDisk}'.");
            $stats['migrated']++;
        } catch (\Throwable $e) {
            $this->line("<fg=red>FAIL</> {$label} — {$e->getMessage()}");
            $stats['failed']++;
        }
    }
}