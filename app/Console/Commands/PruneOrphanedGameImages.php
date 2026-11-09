<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PruneOrphanedGameImages extends Command
{
    protected $signature = 'images:prune-orphans
                            {--dry-run : Only report what would be deleted, even with --force}
                            {--force : Delete the files. Without it, the command is a dry run}
                            {--min-age-days=2 : Keep files newer than this, so a running import is not affected}';

    protected $description = 'Delete downloaded SteamGridDB images that no record references';

    private const GAME_IMAGE_COLUMNS = ['cover_image_id', 'hero_image_id'];

    // None of these are written with covers/ paths today. They are checked so a
    // manually entered path cannot lose its file.
    private const PATH_COLUMNS = [
        'game_lists' => 'og_image_path',
        'news' => 'image_path',
        'news_articles' => 'featured_image_url',
        'pc_ports' => 'cover_path',
        'user_game_collections' => 'cover_image_path',
    ];

    private const GENERATED_FILENAME = '/^[^_]+_\d+\.[a-z0-9]+$/i';

    private const DRY_RUN_SAMPLE_SIZE = 20;

    public function handle(): int
    {
        $minAgeDays = (int) $this->option('min-age-days');
        if ($minAgeDays < 1) {
            $this->error('--min-age-days must be at least 1.');

            return self::FAILURE;
        }

        $directory = storage_path('app/public/covers');
        if (! File::isDirectory($directory)) {
            $this->info('Nothing to do: the covers directory does not exist.');

            return self::SUCCESS;
        }

        $referenced = $this->referencedFilenames();
        $cutoff = now()->subDays($minAgeDays)->getTimestamp();
        $scanned = 0;
        $orphans = [];
        $orphanBytes = 0;

        // Production holds tens of thousands of files: File::files() keeps a
        // Finder object for each one and runs out of memory.
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $scanned++;

            if (preg_match(self::GENERATED_FILENAME, $file->getFilename()) === 1
                && $file->getMTime() < $cutoff
                && ! isset($referenced[$file->getFilename()])) {
                $orphans[] = $file->getFilename();
                $orphanBytes += $file->getSize();
            }
        }

        if ($scanned > 0 && $referenced === []) {
            $this->error('No image references found in the database. Refusing to continue.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Scanned %d files. Orphaned: %d (%s GB).',
            $scanned,
            count($orphans),
            number_format($orphanBytes / 1_000_000_000, 2),
        ));

        if ($this->option('dry-run') || ! $this->option('force')) {
            foreach (array_slice($orphans, 0, self::DRY_RUN_SAMPLE_SIZE) as $orphan) {
                $this->line($orphan);
            }

            if (count($orphans) > self::DRY_RUN_SAMPLE_SIZE) {
                $this->line(sprintf('... and %d more.', count($orphans) - self::DRY_RUN_SAMPLE_SIZE));
            }

            $this->info('Dry run: nothing was deleted. Use --force without --dry-run to delete these files.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($orphans as $orphan) {
            if (! File::delete($directory.'/'.$orphan)) {
                $failed++;
            }
        }

        $this->info(sprintf('Deleted %d files.', count($orphans) - $failed));

        if ($failed > 0) {
            $this->error("Failed to delete {$failed} files.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function referencedFilenames(): array
    {
        $referenced = [];

        foreach (self::GAME_IMAGE_COLUMNS as $column) {
            foreach (DB::table('games')->where($column, 'like', '%.%')->pluck($column) as $filename) {
                $referenced[$filename] = true;
            }
        }

        foreach (self::PATH_COLUMNS as $table => $column) {
            foreach (DB::table($table)->where($column, 'like', '%covers/%')->pluck($column) as $path) {
                $referenced[basename(parse_url($path, PHP_URL_PATH) ?: $path)] = true;
            }
        }

        return $referenced;
    }
}
