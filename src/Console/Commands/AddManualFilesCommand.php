<?php

namespace MicSoleLaravelGen\Console\Commands;

use Illuminate\Console\Command;
use MicSoleLaravelGen\Services\FileTrackerService;
use Illuminate\Support\Facades\File;

class AddManualFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mic-sole:add-manual-files
                            {--force : Force add even if files already tracked}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add manually created Blade auth files to tracking system';

    /**
     * Execute the console command.
     */
    public function handle(FileTrackerService $tracker)
    {
        $this->info('📝 Adding manually created files to tracking system...');
        $this->newLine();

        $files = [];

        // AuthenticatedSessionController
        $controllerPath = base_path('app/Http/Controllers/Auth/AuthenticatedSessionController.php');
        if (File::exists($controllerPath)) {
            $files[] = [
                'type' => 'controller',
                'path' => $controllerPath,
                'relative_path' => str_replace(base_path() . '/', '', $controllerPath),
                'original_content' => null, // New file
            ];
            $this->info("✅ Found: AuthenticatedSessionController.php");
        }

        // Blade Views
        $bladeFiles = [
            'resources/views/auth/login.blade.php' => 'view',
            'resources/views/layouts/auth.blade.php' => 'view',
            'resources/views/components/layouts/auth.blade.php' => 'component',
            'resources/views/components/auth/form-input.blade.php' => 'component',
            'resources/views/components/auth/form-checkbox.blade.php' => 'component',
            'resources/views/components/auth/alert.blade.php' => 'component',
        ];

        // Vue Layout Components
        $vueLayoutFiles = [
            'resources/ts/_dashboard/layouts/main-layout.vue' => 'vue',
            'resources/ts/_dashboard/layouts/app-sidebar.vue' => 'vue',
            'resources/ts/_dashboard/layouts/app-header.vue' => 'vue',
            'resources/ts/_dashboard/components/LogoutButton.vue' => 'vue',
        ];

        foreach ($bladeFiles as $relativePath => $type) {
            $fullPath = base_path($relativePath);
            if (File::exists($fullPath)) {
                $files[] = [
                    'type' => $type,
                    'path' => $fullPath,
                    'relative_path' => $relativePath,
                    'original_content' => null, // New file
                ];
                $this->info("✅ Found: {$relativePath}");
            }
        }

        // Add Vue Layout files
        foreach ($vueLayoutFiles as $relativePath => $type) {
            $fullPath = base_path($relativePath);
            if (File::exists($fullPath)) {
                $files[] = [
                    'type' => $type,
                    'path' => $fullPath,
                    'relative_path' => $relativePath,
                    'original_content' => null, // New file
                ];
                $this->info("✅ Found: {$relativePath}");
            }
        }

        // routes/auth.php
        $authRoutesPath = base_path('routes/auth.php');
        if (File::exists($authRoutesPath)) {
            $files[] = [
                'type' => 'routes',
                'path' => $authRoutesPath,
                'relative_path' => str_replace(base_path() . '/', '', $authRoutesPath),
                'original_content' => null, // New file
            ];
            $this->info("✅ Found: routes/auth.php");
        }

        // Check web.php modification
        $webRoutesPath = base_path('routes/web.php');
        if (File::exists($webRoutesPath)) {
            $content = File::get($webRoutesPath);
            // Check if auth routes are included
            if (str_contains($content, "require __DIR__.'/auth.php'")) {
                // This is a modification, but we don't have original content
                // We'll track it as a new file modification
                $files[] = [
                    'type' => 'routes',
                    'path' => $webRoutesPath,
                    'relative_path' => str_replace(base_path() . '/', '', $webRoutesPath),
                    'original_content' => null, // We don't have original, but it's modified
                ];
                $this->info("✅ Found: routes/web.php (modified)");
            }
        }

        if (empty($files)) {
            $this->warn('⚠️  No manual files found to add.');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info("📦 Found " . count($files) . " file(s) to add to tracking system.");

        // Check if files are already tracked
        if (!$this->option('force')) {
            $history = $tracker->getHistory();
            $alreadyTracked = false;
            foreach ($history as $gen) {
                if ($gen['model'] === 'Auth' && $gen['command'] === 'init-dashboard') {
                    $genFiles = $tracker->getGenerationFiles($gen['id']);
                    foreach ($genFiles as $genFile) {
                        foreach ($files as $file) {
                            if (($genFile['path'] ?? $genFile['relative_path'] ?? '') === ($file['path'] ?? $file['relative_path'] ?? '')) {
                                $alreadyTracked = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            if ($alreadyTracked) {
                $this->warn('⚠️  Some files are already tracked. Use --force to add them anyway.');
                if (!$this->confirm('Do you want to continue?', false)) {
                    return Command::SUCCESS;
                }
            }
        }

        // Track the files
        $tracker->track([
            'model' => 'Auth',
            'command' => 'add-manual-files',
            'files' => $files,
            'metadata' => [
                'manual_files' => true,
                'blade_auth_setup' => true,
                'created_files' => count($files),
                'modified_files' => 0,
            ],
        ]);

        $this->newLine();
        $this->info('✅ Successfully added ' . count($files) . ' file(s) to tracking system!');
        $this->info('📋 You can now use `php artisan mic-sole:status` to view tracked files.');
        $this->info('🔄 You can use `php artisan mic-sole:rollback` to rollback these files.');

        return Command::SUCCESS;
    }
}

