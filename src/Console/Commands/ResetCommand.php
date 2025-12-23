<?php

namespace MicSoleLaravelGen\Console\Commands;

use Illuminate\Console\Command;
use MicSoleLaravelGen\Services\FileTrackerService;
use Illuminate\Support\Facades\File;

class ResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mic-sole:reset
                            {--with-example : Keep User, Role, Permission files (--with-example models)}
                            {--fresh : Regenerate examples after reset}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all generated files to initial state, optionally keeping --with-example files';

    /**
     * Models to keep if --with-example is used
     */
    protected $exampleModels = ['User', 'Role', 'Permission'];

    /**
     * Execute the console command.
     */
    public function handle(FileTrackerService $tracker)
    {
        $withExample = $this->option('with-example');
        $fresh = $this->option('fresh');
        $force = $this->option('force');

        $this->info('🔄 Resetting generated files...');
        if ($withExample) {
            $this->line('   Keeping --with-example files: ' . implode(', ', $this->exampleModels));
        }
        $this->newLine();

        // Get all generations
        $generations = $tracker->getHistory();

        if (empty($generations)) {
            $this->info('ℹ️  No generations found. Nothing to reset.');
            return Command::SUCCESS;
        }

        // Collect all files to delete
        $filesToDelete = [];
        $filesToKeep = [];
        $filesToRevert = [];
        $errors = [];

        foreach ($generations as $gen) {
            $model = $gen['model'] ?? null;

            // Skip example models if --with-example is used
            if ($withExample && $model && in_array($model, $this->exampleModels)) {
                $this->line("   ⏭️  Skipping {$model} (--with-example)");

                // Get files for this generation to mark as kept
                $files = isset($gen['files_file']) ? $tracker->getGenerationFiles($gen['id']) : ($gen['files'] ?? []);
                foreach ($files as $file) {
                    $filePath = $file['path'] ?? null;
                    if ($filePath) {
                        $filesToKeep[] = [
                            'path' => $filePath,
                            'model' => $model,
                            'type' => $file['type'] ?? 'unknown',
                        ];
                    }
                }
                continue;
            }

            // Get files for this generation
            $files = isset($gen['files_file']) ? $tracker->getGenerationFiles($gen['id']) : ($gen['files'] ?? []);

            foreach ($files as $file) {
                $filePath = $file['path'] ?? null;
                if (!$filePath) {
                    continue;
                }

                // Check if this is a modified file (has original_content)
                if (isset($file['original_content'])) {
                    if ($file['original_content'] === null) {
                        // File was created - will be deleted
                        $filesToDelete[] = [
                            'path' => $filePath,
                            'type' => $file['type'] ?? 'unknown',
                            'model' => $model ?? 'N/A',
                            'generation' => $gen['id'],
                        ];
                    } else {
                        // File was modified - will be reverted
                        $filesToRevert[] = [
                            'path' => $filePath,
                            'type' => $file['type'] ?? 'unknown',
                            'model' => $model ?? 'N/A',
                            'generation' => $gen['id'],
                            'original_content' => $file['original_content'],
                        ];
                    }
                } else {
                    // Regular file (created) - will be deleted
                    $filesToDelete[] = [
                        'path' => $filePath,
                        'type' => $file['type'] ?? 'unknown',
                        'model' => $model ?? 'N/A',
                        'generation' => $gen['id'],
                    ];
                }
            }
        }

        // Show summary
        $this->line("📊 Summary:");
        $this->line("   Generations to process: " . count($generations));
        $this->line("   Files to delete: " . count($filesToDelete));
        $this->line("   Files to revert: " . count($filesToRevert));
        if ($withExample) {
            $this->line("   Files to keep (--with-example): " . count($filesToKeep));
        }
        $this->newLine();

        if (empty($filesToDelete) && empty($filesToRevert)) {
            $this->info('ℹ️  No files to reset.');
            return Command::SUCCESS;
        }

        // Confirm
        if (!$force && !$this->confirm('Are you sure you want to reset all generated files?', false)) {
            $this->info('Reset cancelled.');
            return Command::SUCCESS;
        }

        // Delete files
        $deleted = 0;
        $notDeleted = [];

        foreach ($filesToDelete as $file) {
            $filePath = $file['path'];

            if (!File::exists($filePath)) {
                continue; // Already deleted
            }

            try {
                File::delete($filePath);
                $deleted++;
            } catch (\Exception $e) {
                $notDeleted[] = [
                    'path' => $filePath,
                    'error' => $e->getMessage(),
                    'model' => $file['model'],
                    'type' => $file['type'],
                ];
                $errors[] = [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Revert files
        $reverted = 0;
        foreach ($filesToRevert as $file) {
            $filePath = $file['path'];

            if (!File::exists($filePath)) {
                continue;
            }

            try {
                File::put($filePath, $file['original_content']);
                $reverted++;
            } catch (\Exception $e) {
                $notDeleted[] = [
                    'path' => $filePath,
                    'error' => $e->getMessage(),
                    'model' => $file['model'],
                    'type' => $file['type'],
                ];
                $errors[] = [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Clean up empty directories
        $this->cleanupEmptyDirectories($filesToDelete);

        // Clear history (except example models if --with-example)
        if ($withExample) {
            // Remove only non-example generations from history
            $this->removeNonExampleGenerations($tracker);
        } else {
            // Clear all history
            $tracker->clear();
        }

        // Show results
        $this->newLine();
        $this->info('✅ Reset completed!');
        $this->newLine();

        $this->line("📁 Files deleted: {$deleted}");
        $this->line("🔄 Files reverted: {$reverted}");

        if ($withExample) {
            $this->line("⏭️  Files kept (--with-example): " . count($filesToKeep));
        }

        // Show count of files that could not be deleted/reverted
        $notDeletedCount = count($notDeleted);
        if ($notDeletedCount > 0) {
            $this->newLine();
            $this->warn("⚠️  Files that could not be deleted/reverted: {$notDeletedCount}");
            $this->newLine();

            // Group by model
            $byModel = [];
            foreach ($notDeleted as $file) {
                $model = $file['model'];
                if (!isset($byModel[$model])) {
                    $byModel[$model] = [];
                }
                $byModel[$model][] = $file;
            }

            foreach ($byModel as $model => $files) {
                $this->line("   📦 Model: {$model}");
                foreach ($files as $file) {
                    $relativePath = str_replace(base_path() . '/', '', $file['path']);
                    $relativePath = str_replace(base_path() . '\\', '', $relativePath);
                    $this->line("      ❌ [{$file['type']}] {$relativePath}");
                    $this->line("         Error: {$file['error']}");
                }
                $this->newLine();
            }
        }

        // If --fresh, regenerate example models
        if ($fresh && $withExample) {
            $this->newLine();
            $this->info('🔄 Regenerating --with-example files...');
            $this->call('mic-sole:init-dashboard', [
                '--with-example' => true,
                '--force' => true,
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Clean up empty directories
     */
    protected function cleanupEmptyDirectories(array $filesToDelete)
    {
        $directories = [];

        foreach ($filesToDelete as $file) {
            $dir = dirname($file['path']);
            if (!isset($directories[$dir])) {
                $directories[$dir] = true;
            }
        }

        foreach (array_keys($directories) as $dir) {
            if (File::isDirectory($dir) && count(File::allFiles($dir)) === 0) {
                try {
                    File::deleteDirectory($dir);
                } catch (\Exception $e) {
                    // Ignore errors
                }
            }
        }
    }

    /**
     * Remove non-example generations from history
     */
    protected function removeNonExampleGenerations(FileTrackerService $tracker)
    {
        $history = $tracker->getHistory();
        $newHistory = [];

        foreach ($history as $gen) {
            $model = $gen['model'] ?? null;
            if ($model && in_array($model, $this->exampleModels)) {
                $newHistory[] = $gen;
            }
        }

        // Save only example generations
        if (!empty($newHistory)) {
            $this->saveFilteredHistory($tracker, $newHistory);
        } else {
            $tracker->clear();
        }
    }

    /**
     * Save filtered history (only example models)
     * Uses reflection to access protected saveHistory method
     */
    protected function saveFilteredHistory(FileTrackerService $tracker, array $history)
    {
        // Use reflection to call protected saveHistory method
        $reflection = new \ReflectionClass($tracker);
        $method = $reflection->getMethod('saveHistory');
        $method->setAccessible(true);
        $method->invoke($tracker, $history);
    }
}

