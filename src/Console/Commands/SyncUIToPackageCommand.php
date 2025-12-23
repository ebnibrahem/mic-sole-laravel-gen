<?php

namespace MicSoleLaravelGen\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncUIToPackageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mic-sole:sync-ui-to-package
                            {--file= : Sync specific file only}
                            {--force : Overwrite existing files without confirmation}
                            {--watch : Watch for changes and auto-sync}
                            {--dry-run : Show what would be synced without actually syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync UI changes from project to package (for development)';

    protected $projectPath;
    protected $packagePath;

    /**
     * UI files to sync from project to package
     */
    protected $uiFiles = [
        'resources/js/crud-generator',
        'resources/js/mic-sole.tsx',
        'resources/css/mic-sole.css',
        'resources/views/generator.blade.php',
        'resources/js/components/ui', // UI components
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->projectPath = base_path();
        $this->packagePath = base_path('mic-sole-laravel-gen');

        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $watch = $this->option('watch');
        $fileToSync = $this->option('file');

        if ($watch) {
            return $this->watchForChanges($force);
        }

        $this->info('🔄 Syncing UI files from project to package...');
        $this->line("   From: {$this->projectPath}");
        $this->line("   To:   {$this->packagePath}");
        $this->newLine();

        if (!File::exists($this->packagePath)) {
            $this->error("❌ Package path not found: {$this->packagePath}");
            return Command::FAILURE;
        }

        if ($fileToSync) {
            // Sync single file
            $this->syncFile($fileToSync, $force, $dryRun);
        } else {
            // Sync all files
            $this->syncAllFiles($force, $dryRun);
        }

        return Command::SUCCESS;
    }

    /**
     * Watch for changes and auto-sync
     */
    protected function watchForChanges(bool $force)
    {
        $this->info('👀 Watching for UI file changes...');
        $this->line("   Press Ctrl+C to stop");
        $this->newLine();

        $lastSync = [];

        while (true) {
            $changed = false;

            foreach ($this->uiFiles as $file) {
                $sourcePath = $this->projectPath . '/' . $file;

                if (!File::exists($sourcePath)) {
                    continue;
                }

                $hash = $this->getFileHash($sourcePath);

                if (!isset($lastSync[$file]) || $lastSync[$file] !== $hash) {
                    $this->syncPath($file, $force, false, false);
                    $lastSync[$file] = $hash;
                    $changed = true;
                }
            }

            if ($changed) {
                $this->line("✅ Synced at " . now()->format('H:i:s'));
            }

            sleep(2); // Check every 2 seconds
        }
    }

    /**
     * Get file or directory hash
     */
    protected function getFileHash(string $path): string
    {
        if (File::isFile($path)) {
            return md5_file($path);
        }

        // For directories, hash all files
        $files = File::allFiles($path);
        $hashes = [];
        foreach ($files as $file) {
            $hashes[] = md5_file($file->getPathname());
        }
        return md5(implode('', $hashes));
    }

    /**
     * Sync all files
     */
    protected function syncAllFiles(bool $force, bool $dryRun)
    {
        $synced = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($this->uiFiles as $file) {
            $result = $this->syncPath($file, $force, $dryRun);

            if ($result === 'synced') {
                $synced++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } elseif ($result === 'error') {
                $errors++;
            }
        }

        $this->newLine();
        $this->info("✅ Sync completed!");
        $this->line("   Synced: {$synced}");
        $this->line("   Skipped: {$skipped}");
        if ($errors > 0) {
            $this->error("   Errors: {$errors}");
        }
    }

    /**
     * Sync a single file
     */
    protected function syncFile(string $filePath, bool $force, bool $dryRun)
    {
        $sourcePath = $this->projectPath . '/' . $filePath;
        $targetPath = $this->packagePath . '/' . $filePath;

        if (!File::exists($sourcePath)) {
            $this->error("❌ Project file not found: {$filePath}");
            return Command::FAILURE;
        }

        return $this->syncPath($filePath, $force, $dryRun, true);
    }

    /**
     * Sync a single path
     */
    protected function syncPath(string $path, bool $force, bool $dryRun, bool $showOutput = true): string
    {
        $sourcePath = $this->projectPath . '/' . $path;
        $targetPath = $this->packagePath . '/' . $path;

        if (!File::exists($sourcePath)) {
            if ($showOutput) {
                $this->warn("⚠️  Project file not found: {$path}");
            }
            return 'skipped';
        }

        // Check if target exists
        if (File::exists($targetPath) && !$force && !$dryRun) {
            if ($showOutput) {
                if (!$this->confirm("Package file exists: {$path}. Overwrite?", true)) {
                    return 'skipped';
                }
            } else {
                return 'skipped';
            }
        }

        if ($dryRun) {
            if ($showOutput) {
                $this->line("📋 Would sync: {$path}");
            }
            return 'synced';
        }

        try {
            // Create target directory if needed
            $targetDir = dirname($targetPath);
            if (!File::exists($targetDir)) {
                File::makeDirectory($targetDir, 0755, true);
            }

            // Copy file or directory
            if (File::isFile($sourcePath)) {
                File::copy($sourcePath, $targetPath);
            } else {
                File::copyDirectory($sourcePath, $targetPath);
            }

            if ($showOutput) {
                $this->info("✅ Synced: {$path}");
            }
            return 'synced';
        } catch (\Exception $e) {
            if ($showOutput) {
                $this->error("❌ Error syncing {$path}: " . $e->getMessage());
            }
            return 'error';
        }
    }
}

