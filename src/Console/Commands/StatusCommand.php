<?php

namespace MicSoleLaravelGen\Console\Commands;

use Illuminate\Console\Command;
use MicSoleLaravelGen\Services\FileTrackerService;
use Illuminate\Support\Facades\File;

class StatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mic-sole:status
                            {--model= : Filter by model name}
                            {--type= : Filter by file type}
                            {--exists : Show only existing files}
                            {--missing : Show only missing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show status of generated files from .mic file';

    /**
     * Execute the console command.
     */
    public function handle(FileTrackerService $tracker)
    {
        $history = $tracker->getHistory();

        if (empty($history)) {
            $this->info('📋 No generated files found in .mic file.');
            return Command::SUCCESS;
        }

        $modelFilter = $this->option('model');
        $typeFilter = $this->option('type');
        $showExists = $this->option('exists');
        $showMissing = $this->option('missing');

        $this->info('📊 Generated Files Status');
        $this->newLine();

        // Collect all files from all generations
        $allFiles = [];
        foreach ($history as $gen) {
            if ($modelFilter && $gen['model'] !== $modelFilter) {
                continue;
            }

            // Get files from separate file if available
            $files = isset($gen['files_file']) ? $tracker->getGenerationFiles($gen['id']) : ($gen['files'] ?? []);
            foreach ($files as $file) {
                if ($typeFilter && $file['type'] !== $typeFilter) {
                    continue;
                }

                $filePath = $file['path'] ?? $file['relative_path'] ?? null;
                if (!$filePath) {
                    continue;
                }

                // Normalize path
                $normalizedPath = str_replace(base_path() . '/', '', $filePath);
                $normalizedPath = str_replace(base_path() . '\\', '', $normalizedPath);

                $exists = File::exists($filePath);

                // Apply filters
                if ($showExists && !$exists) {
                    continue;
                }
                if ($showMissing && $exists) {
                    continue;
                }

                $allFiles[] = [
                    'model' => $gen['model'] ?? 'N/A',
                    'type' => $file['type'] ?? 'unknown',
                    'path' => $normalizedPath,
                    'full_path' => $filePath,
                    'exists' => $exists,
                    'generation_id' => $gen['id'] ?? 'N/A',
                    'timestamp' => $gen['timestamp'] ?? 'N/A',
                ];
            }
        }

        if (empty($allFiles)) {
            $this->warn('⚠️  No files match the specified filters.');
            return Command::SUCCESS;
        }

        // Group by model
        $groupedByModel = [];
        foreach ($allFiles as $file) {
            $model = $file['model'];
            if (!isset($groupedByModel[$model])) {
                $groupedByModel[$model] = [];
            }
            $groupedByModel[$model][] = $file;
        }

        // Display summary
        $totalFiles = count($allFiles);
        $existingFiles = count(array_filter($allFiles, fn($f) => $f['exists']));
        $missingFiles = $totalFiles - $existingFiles;

        $this->line("Total Files: {$totalFiles}");
        $this->line("✅ Existing: {$existingFiles}");
        if ($missingFiles > 0) {
            $this->line("❌ Missing: {$missingFiles}");
        }
        $this->newLine();

        // Display files grouped by model
        foreach ($groupedByModel as $model => $files) {
            $this->info("📦 Model: {$model}");
            $this->line("   Files: " . count($files));

            // Group by type
            $byType = [];
            foreach ($files as $file) {
                $type = $file['type'];
                if (!isset($byType[$type])) {
                    $byType[$type] = [];
                }
                $byType[$type][] = $file;
            }

            foreach ($byType as $type => $typeFiles) {
                $this->line("   └─ {$type}: " . count($typeFiles));

                // Show file details if not too many
                if (count($typeFiles) <= 5) {
                    foreach ($typeFiles as $file) {
                        $status = $file['exists'] ? '✅' : '❌';
                        $this->line("      {$status} {$file['path']}");
                    }
                } else {
                    $existing = count(array_filter($typeFiles, fn($f) => $f['exists']));
                    $missing = count($typeFiles) - $existing;
                    $this->line("      ✅ {$existing} existing, ❌ {$missing} missing");
                }
            }
            $this->newLine();
        }

        // Show detailed list if requested
        if ($this->option('verbose')) {
            $this->info('📋 Detailed File List:');
            $this->newLine();

            $headers = ['Status', 'Type', 'Model', 'Path'];
            $rows = [];

            foreach ($allFiles as $file) {
                $rows[] = [
                    $file['exists'] ? '✅' : '❌',
                    $file['type'],
                    $file['model'],
                    $file['path'],
                ];
            }

            $this->table($headers, $rows);
        }

        return Command::SUCCESS;
    }
}

