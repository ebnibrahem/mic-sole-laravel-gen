<?php

namespace MicSoleLaravelGen\Providers;

use Illuminate\Support\ServiceProvider;
use MicSoleLaravelGen\Exceptions\MICApiResponse;
use MicSoleLaravelGen\Console\Commands\SetupDashboardCommand;
use MicSoleLaravelGen\Console\Commands\InitDashboardCommand;
use MicSoleLaravelGen\Console\Commands\RollbackCommand;
use MicSoleLaravelGen\Console\Commands\ResetCommand;
use MicSoleLaravelGen\Console\Commands\StatusCommand;
use MicSoleLaravelGen\Console\Commands\AddManualFilesCommand;
use MicSoleLaravelGen\Console\Commands\SyncTemplatesCommand;
use MicSoleLaravelGen\Console\Commands\SyncUIToPackageCommand;
use MicSoleLaravelGen\Console\Commands\InstallUICommand;
use MicSoleLaravelGen\Console\Commands\VerifyTemplatesCommand;

class MicSoleLaravelGenServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SetupDashboardCommand::class,
                InitDashboardCommand::class,
                RollbackCommand::class,
                ResetCommand::class,
                StatusCommand::class,
                AddManualFilesCommand::class,
                SyncTemplatesCommand::class,
                SyncUIToPackageCommand::class,
                InstallUICommand::class,
                VerifyTemplatesCommand::class,
            ]);
        }

        // Register routes (disabled - routes should be in main app)
        // $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Register API routes if api.php exists (disabled - routes should be in main app)
        // if (file_exists(__DIR__ . '/../../routes/api.php')) {
        //     $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        // }

        // Register exception handler if needed
        // This can be called from bootstrap/app.php
    }

    /**
     * Register exception handlers
     */
    public static function registerExceptionHandlers($exceptions): void
    {
        MICApiResponse::exceptions($exceptions);
    }
}

