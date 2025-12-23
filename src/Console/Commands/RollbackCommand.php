<?php

namespace MicSoleLaravelGen\Console\Commands;

use Illuminate\Console\Command;
use MicSoleLaravelGen\Services\FileTrackerService;

class RollbackCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mic-sole:rollback
                            {--level=1 : Number of levels to rollback}
                            {--id= : Specific generation ID to rollback to}
                            {--list : List all generations}
                            {--stats : Show statistics}
                            {--clear : Clear all history}
                            {--preview : Preview what will be deleted/reverted without executing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback generated files by one or more levels';

    /**
     * Execute the console command.
     */
    public function handle(FileTrackerService $tracker)
    {
        if ($this->option('list')) {
            return $this->listGenerations($tracker);
        }

        if ($this->option('stats')) {
            return $this->showStats($tracker);
        }

        if ($this->option('clear')) {
            return $this->clearHistory($tracker);
        }

        if ($this->option('preview')) {
            return $this->previewRollback($tracker);
        }

        $level = (int) $this->option('level');
        $id = $this->option('id');

        if ($level < 1) {
            $this->error('Level must be at least 1');
            return Command::FAILURE;
        }

        $this->info("🔄 Rolling back {$level} level(s)...");
        $this->newLine();

        // Show current state
        $current = $tracker->getCurrent();
        if ($current) {
            $this->line("Current generation: {$current['id']}");
            $this->line("Model: {$current['model']}");
            $this->line("Files: " . ($current['files_count'] ?? 0));
            $this->newLine();
        }

        // Confirm
        if (!$this->confirm('Are you sure you want to rollback? This will delete generated files and restore updated files to their original state.', true)) {
            $this->info('Rollback cancelled.');
            return Command::SUCCESS;
        }

        // Perform rollback
        $result = $tracker->rollback($id ?? -1, $level);

        if ($result['status'] === 'error') {
            $this->error('❌ ' . $result['message']);
            return Command::FAILURE;
        }

        $this->info('✅ Rollback completed successfully!');
        $this->newLine();

        if (!empty($result['deleted'])) {
            $this->line('📁 Deleted files:');
            foreach ($result['deleted'] as $file) {
                $this->line("   - {$file}");
            }
            $this->newLine();
        }

        if (!empty($result['reverted'])) {
            $this->line('🔄 Restored files to original state:');
            foreach ($result['reverted'] as $file) {
                $this->line("   - {$file}");
            }
            $this->newLine();
        }

        if (!empty($result['errors'])) {
            $this->warn('⚠️  Errors:');
            foreach ($result['errors'] as $error) {
                $this->line("   - {$error['file']}: {$error['error']}");
            }
            $this->newLine();
        }

        // Show new current state
        $newCurrent = $tracker->getCurrent();
        if ($newCurrent) {
            $this->line("New current generation: {$newCurrent['id']}");
            $this->line("Model: {$newCurrent['model']}");
        } else {
            $this->line("No generations remaining.");
        }

        return Command::SUCCESS;
    }

    /**
     * List all generations
     */
    protected function listGenerations(FileTrackerService $tracker): int
    {
        $generations = $tracker->list(20);

        if (empty($generations)) {
            $this->info('No generations found.');
            return Command::SUCCESS;
        }

        $this->info('📋 Generation History:');
        $this->newLine();

        $headers = ['#', 'ID', 'Model', 'Timestamp', 'Files'];
        $rows = [];

        foreach ($generations as $index => $gen) {
            $rows[] = [
                $index + 1,
                substr($gen['id'], 0, 12) . '...',
                $gen['model'] ?? 'N/A',
                date('Y-m-d H:i:s', strtotime($gen['timestamp'])),
                count($gen['files'] ?? []),
            ];
        }

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }

    /**
     * Show statistics
     */
    protected function showStats(FileTrackerService $tracker): int
    {
        $stats = $tracker->getStats();

        $this->info('📊 Generation Statistics:');
        $this->newLine();

        $this->line("Total Generations: {$stats['total_generations']}");
        $this->line("Total Files Generated: {$stats['total_files']}");
        $this->newLine();

        if (!empty($stats['models'])) {
            $this->line('Models Generated:');
            foreach ($stats['models'] as $model => $count) {
                $this->line("   - {$model}: {$count} time(s)");
            }
            $this->newLine();
        }

        if ($stats['last_generation']) {
            $last = $stats['last_generation'];
            $this->line('Last Generation:');
            $this->line("   ID: {$last['id']}");
            $this->line("   Model: {$last['model']}");
            $this->line("   Files: " . ($last['files_count'] ?? 0));
            $this->line("   Timestamp: " . date('Y-m-d H:i:s', strtotime($last['timestamp'])));
        }

        return Command::SUCCESS;
    }

    /**
     * Clear history
     */
    protected function clearHistory(FileTrackerService $tracker): int
    {
        if (!$this->confirm('Are you sure you want to clear all generation history?', false)) {
            $this->info('Clear cancelled.');
            return Command::SUCCESS;
        }

        $tracker->clear();
        $this->info('✅ History cleared successfully!');

        return Command::SUCCESS;
    }

    /**
     * Preview what will be affected by rollback
     */
    protected function previewRollback(FileTrackerService $tracker): int
    {
        $level = (int) $this->option('level');
        $id = $this->option('id');

        if ($level < 1) {
            $this->error('Level must be at least 1');
            return Command::FAILURE;
        }

        $history = $tracker->getHistory();

        if (empty($history)) {
            $this->info('📋 No generation history found.');
            return Command::SUCCESS;
        }

        // Get target generation
        $targetGen = $tracker->getGeneration($id ?? -1);

        if (!$targetGen) {
            $this->error('❌ Generation not found');
            return Command::FAILURE;
        }

        // Calculate rollback index
        $targetIndex = array_search($targetGen, $history);
        $rollbackIndex = $targetIndex - $level;

        if ($rollbackIndex < 0) {
            $rollbackIndex = 0;
        }

        $this->info("🔍 Preview: What will be affected by rolling back {$level} level(s)");
        $this->newLine();

        // Show current state
        $this->line("Current generation: {$targetGen['id']}");
        $this->line("Model: {$targetGen['model']}");
        $this->line("Files: " . ($targetGen['files_count'] ?? 0));
        $this->newLine();

        // Get files that will be affected
        $filesToDelete = [];
        $filesToRevert = [];
        $generationsToRemove = [];

        for ($i = $targetIndex; $i >= $rollbackIndex; $i--) {
            if (isset($history[$i])) {
                $gen = $history[$i];
                $generationsToRemove[] = $gen;

                // Get files from separate file if available
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
                                'generation' => $gen['id'],
                                'model' => $gen['model'] ?? 'N/A',
                            ];
                        } else {
                            // File was modified - will be reverted
                            $filesToRevert[] = [
                                'path' => $filePath,
                                'type' => $file['type'] ?? 'unknown',
                                'generation' => $gen['id'],
                                'model' => $gen['model'] ?? 'N/A',
                            ];
                        }
                    } else {
                        // Regular file (created) - will be deleted
                        $filesToDelete[] = [
                            'path' => $filePath,
                            'type' => $file['type'] ?? 'unknown',
                            'generation' => $gen['id'],
                            'model' => $gen['model'] ?? 'N/A',
                        ];
                    }
                }
            }
        }

        // Display preview
        $this->line("📊 Summary:");
        $this->line("   Generations to remove: " . count($generationsToRemove));
        $this->line("   Files to delete: " . count($filesToDelete));
        $this->line("   Files to revert: " . count($filesToRevert));
        $this->newLine();

        if (!empty($generationsToRemove)) {
            $this->line("🔄 Generations that will be removed:");
            foreach ($generationsToRemove as $gen) {
                $this->line("   - {$gen['model']} ({$gen['id']}) - " . date('Y-m-d H:i:s', strtotime($gen['timestamp'])));
            }
            $this->newLine();
        }

        if (!empty($filesToDelete)) {
            $this->line("🗑️  Files that will be DELETED:");
            $this->newLine();

            // Group by model
            $byModel = [];
            foreach ($filesToDelete as $file) {
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
                }
                $this->newLine();
            }
        }

        if (!empty($filesToRevert)) {
            $this->line("🔄 Files that will be REVERTED to original state:");
            $this->newLine();

            // Group by model
            $byModel = [];
            foreach ($filesToRevert as $file) {
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
                    $this->line("      🔄 [{$file['type']}] {$relativePath}");
                }
                $this->newLine();
            }
        }

        if (empty($filesToDelete) && empty($filesToRevert)) {
            $this->info("ℹ️  No files will be affected.");
        }

        // Show what will be the new current generation
        if ($rollbackIndex >= 0 && isset($history[$rollbackIndex])) {
            $newCurrent = $history[$rollbackIndex];
            $this->newLine();
            $this->line("After rollback, current generation will be:");
            $this->line("   ID: {$newCurrent['id']}");
            $this->line("   Model: {$newCurrent['model']}");
        } else {
            $this->newLine();
            $this->line("After rollback, no generations will remain.");
        }

        return Command::SUCCESS;
    }
}

