<?php

namespace MicSoleLaravelGen\Console\Commands;

use Illuminate\Console\Command;
use MicSoleLaravelGen\Services\DashboardSetupService;
use MicSoleLaravelGen\Services\CrudGeneratorService;
use MicSoleLaravelGen\Services\FileTrackerService;
use Illuminate\Support\Facades\File;

class InitDashboardCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mic-sole:init-dashboard
                            {--with-example : Create User, Role, Permission management system as example}
                            {--fresh : Regenerate examples after reset (requires --with-example)}
                            {--force : Force overwrite existing files}
                            {--skip-validation : Skip TypeScript validation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize complete dashboard structure with optional Post example';

    /**
     * Get stub file path
     *
     * @param string $stubPath Relative path from Templates directory
     * @return string
     */
    protected function getStubPath(string $stubPath): string
    {
        return \MicSoleLaravelGen\Providers\MicSoleLaravelGenServiceProvider::getStubPath($stubPath);
    }

    /**
     * Execute the console command.
     */
    public function handle(
        DashboardSetupService $dashboardService,
        CrudGeneratorService $crudService,
        FileTrackerService $tracker
    ) {
        $this->info('🚀 Initializing dashboard structure...');
        $this->newLine();

        $fresh = $this->option('fresh');
        $withExample = $this->option('with-example');

        // Validate --fresh requires --with-example
        if ($fresh && !$withExample) {
            $this->error('❌ Error: --fresh option requires --with-example');
            return Command::FAILURE;
        }

        // If --fresh is used, reset ALL generated models first (BEFORE clearing history)
        if ($fresh && $withExample) {
            $this->info('🔄 Fresh mode: Resetting ALL generated models and restoring files to original templates...');
            $this->resetAllModels($tracker);
            $this->newLine();
        }

        // Clear .mic file if --force is used (AFTER resetting models)
        if ($this->option('force')) {
            $this->info('🗑️  Clearing tracking file (--force mode)...');
            $tracker->clear();
            $this->info('✅ Tracking file cleared!');
            $this->newLine();
        }

        // Step 1: Setup dashboard structure
        // When --fresh is used, force update dashboard files (responses.ts, routes.ts, etc.)
        $forceDashboard = $this->option('force') || $fresh;
        $this->info('📁 Step 1: Setting up dashboard structure...');
        try {
            $dashboardFiles = $dashboardService->setup($forceDashboard);

            // Track dashboard files
            if (!empty($dashboardFiles)) {
                $tracker->track([
                    'model' => 'Dashboard',
                    'command' => 'init-dashboard',
                    'files' => $dashboardFiles,
                    'metadata' => [
                        'dashboard_setup' => true,
                        'created_files' => count(array_filter($dashboardFiles, fn($f) => !isset($f['original_content']) || $f['original_content'] === null)),
                        'modified_files' => count(array_filter($dashboardFiles, fn($f) => isset($f['original_content']) && $f['original_content'] !== null)),
                    ],
                ]);
            }

            $this->info('✅ Dashboard structure setup completed!');
        } catch (\Exception $e) {
            $this->error('❌ Error setting up dashboard: ' . $e->getMessage());
            return Command::FAILURE;
        }
        $this->newLine();

        // Step 2: Create User Management System (Authorization) if requested
        $generationIds = [];

        if ($withExample) {
            $this->info('👥 Step 2: Creating User Management System (Authorization)...');
            try {
                // Generate User, Role, Permission models
                $models = [
                    ['name' => 'User', 'fields' => []],
                    ['name' => 'Role', 'fields' => []],
                    ['name' => 'Permission', 'fields' => []],
                ];

                foreach ($models as $modelData) {
                    $result = $crudService->generate([
                        'model' => $modelData['name'],
                        'fields' => $modelData['fields'],
                        'relationships' => [],
                        'backendFiles' => ['model', 'controller', 'service', 'request', 'resource', 'migration', 'seeder', 'factory', 'policy', 'routes', 'lang'],
                        'vueFiles' => [],
                        'force' => $this->option('force'),
                    ]);

                    if ($result['status'] === 'success' && isset($result['generation_id'])) {
                        $generationIds[] = $result['generation_id'];
                    }
                }

                $this->info('✅ User Management System created successfully!');
            } catch (\Exception $e) {
                $this->error('❌ Error creating User Management System: ' . $e->getMessage());
                return Command::FAILURE;
            }
            $this->newLine();
        }

        // Step 2.5: Create Authentication pages (Login, Register) and AuthController
        if ($withExample) {
            $this->info('🔐 Step 2.5: Creating Authentication pages and controller...');
            try {
                $this->createAuthPages();
                $authFiles = $this->createAuthController();
                $bladeAuthFiles = $this->createBladeAuthPages();
                $routesFiles = $this->createAuthRoutes();
                $bootstrapModification = $this->updateBootstrapApp();
                $this->createDatabaseSeeder();

                // Track auth files
                if (!empty($authFiles['created']) || !empty($authFiles['modified']) || !empty($bladeAuthFiles) || !empty($routesFiles) || $bootstrapModification) {
                    $allAuthFiles = array_merge(
                        $authFiles['created'] ?? [],
                        $authFiles['modified'] ?? [],
                        $bladeAuthFiles ?? [],
                        $routesFiles ?? []
                    );
                    if ($bootstrapModification) {
                        $allAuthFiles[] = $bootstrapModification;
                    }

                    // Track auth files as a special generation
                    $tracker->track([
                        'model' => 'Auth',
                        'command' => 'init-dashboard',
                        'files' => $allAuthFiles,
                        'metadata' => [
                            'auth_setup' => true,
                            'created_files' => count($authFiles['created'] ?? []) + count($bladeAuthFiles ?? []),
                            'modified_files' => count($authFiles['modified'] ?? []) + ($bootstrapModification ? 1 : 0),
                        ],
                    ]);
                }

                $this->info('✅ Authentication pages and controller created successfully!');
            } catch (\Exception $e) {
                $this->warn('⚠️  Warning: Could not create authentication pages: ' . $e->getMessage());
            }
            $this->newLine();
        }

        // Step 3: Create notifications table migration
        $this->info('📬 Step 3: Creating notifications table migration...');
        try {
            // Check if notifications migration already exists
            $migrationsPath = database_path('migrations');
            $existingMigration = collect(File::glob($migrationsPath . '/*_create_notifications_table.php'))->first();

            if ($existingMigration) {
                $this->info('✅ Notifications table migration already exists!');
            } else {
                // Try to call the command using Artisan facade with proper context
                $exitCode = \Illuminate\Support\Facades\Artisan::call('notifications:table', [], $this->getOutput());
                if ($exitCode === 0) {
                    $this->info('✅ Notifications table migration created successfully!');
                } else {
                    $this->warn('⚠️  Warning: Could not create notifications table migration.');
                    $this->warn('   You can create it manually by running: php artisan notifications:table');
                }
            }
        } catch (\Exception $e) {
            $this->warn('⚠️  Warning: Could not create notifications table migration: ' . $e->getMessage());
            $this->warn('   You can create it manually by running: php artisan notifications:table');
        }
        $this->newLine();

        $this->info('✅ Dashboard initialization completed successfully!');
        return Command::SUCCESS;
    }

    /**
     * Create AuthController
     * Returns array of created/modified files for tracking
     */
    protected function createAuthController(): array
    {
        $trackedFiles = [];
        $modifiedFiles = [];

        // Create MicResponseTrait if it doesn't exist
        $traitFile = $this->createMicResponseTrait();
        if ($traitFile) {
            $trackedFiles[] = $traitFile;
        }

        // Create Auth requests first
        $requestFiles = $this->createAuthRequests();
        $trackedFiles = array_merge($trackedFiles, $requestFiles);

        // Create AuthGates middleware
        $middlewareFile = $this->createAuthGates();
        if ($middlewareFile) {
            $trackedFiles[] = $middlewareFile;
        }

        // Create MICApiResponse exception handler
        $exceptionFile = $this->createMICApiResponse();
        if ($exceptionFile) {
            $trackedFiles[] = $exceptionFile;
        }

        // Update Controller base class (track modification)
        $controllerModification = $this->updateControllerBase();
        if ($controllerModification) {
            $modifiedFiles[] = $controllerModification;
        }

        // Create AuthController
        $controllerDir = base_path('app/Http/Controllers/Auth');
        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0755, true);
        }

        $controllerPath = $controllerDir . '/AuthController.php';
        $stubPath = $this->getStubPath('controller/AuthController.stub');

        if (file_exists($stubPath) && !file_exists($controllerPath)) {
            $controllerContent = file_get_contents($stubPath);
            file_put_contents($controllerPath, $controllerContent);
            $trackedFiles[] = [
                'type' => 'controller',
                'path' => $controllerPath,
                'relative_path' => str_replace(base_path() . '/', '', $controllerPath),
            ];
        } elseif (!file_exists($controllerPath)) {
            // Fallback to inline code if stub doesn't exist
            $controllerContent = file_get_contents($stubPath);
            file_put_contents($controllerPath, $controllerContent);
            $trackedFiles[] = [
                'type' => 'controller',
                'path' => $controllerPath,
                'relative_path' => str_replace(base_path() . '/', '', $controllerPath),
            ];
        }

        return [
            'created' => $trackedFiles,
            'modified' => $modifiedFiles,
        ];
    }

    /**
     * Create MicResponseTrait
     * Returns file info if created, null otherwise
     */
    protected function createMicResponseTrait(): ?array
    {
        $traitsDir = base_path('app/Traits');
        if (!is_dir($traitsDir)) {
            mkdir($traitsDir, 0755, true);
        }

        $traitPath = $traitsDir . '/MicResponseTrait.php';
        $traitStub = $this->getStubPath('traits/MicResponseTrait.stub');

        if (file_exists($traitStub) && !file_exists($traitPath)) {
            copy($traitStub, $traitPath);
            return [
                'type' => 'trait',
                'path' => $traitPath,
                'relative_path' => str_replace(base_path() . '/', '', $traitPath),
            ];
        }

        return null;
    }

    /**
     * Create Auth Requests (LoginRequest, RegisterRequest)
     * Returns array of created files for tracking
     */
    protected function createAuthRequests(): array
    {
        $requestsDir = base_path('app/Http/Requests/Auth');
        if (!is_dir($requestsDir)) {
            mkdir($requestsDir, 0755, true);
        }

        $stubPath = $this->getStubPath('requests/Auth/');
        $trackedFiles = [];

        // Create LoginRequest
        $loginRequestPath = $requestsDir . '/LoginRequest.php';
        $loginRequestStub = $stubPath . '/LoginRequest.stub';
        if (file_exists($loginRequestStub) && !file_exists($loginRequestPath)) {
            copy($loginRequestStub, $loginRequestPath);
            $trackedFiles[] = [
                'type' => 'request',
                'path' => $loginRequestPath,
                'relative_path' => str_replace(base_path() . '/', '', $loginRequestPath),
            ];
        }

        // Create RegisterRequest
        $registerRequestPath = $requestsDir . '/RegisterRequest.php';
        $registerRequestStub = $stubPath . '/RegisterRequest.stub';
        if (file_exists($registerRequestStub) && !file_exists($registerRequestPath)) {
            copy($registerRequestStub, $registerRequestPath);
            $trackedFiles[] = [
                'type' => 'request',
                'path' => $registerRequestPath,
                'relative_path' => str_replace(base_path() . '/', '', $registerRequestPath),
            ];
        }

        // Create PasswordResetRequest
        $passwordResetRequestPath = $requestsDir . '/PasswordResetRequest.php';
        $passwordResetRequestStub = $stubPath . '/PasswordResetRequest.stub';
        if (file_exists($passwordResetRequestStub) && !file_exists($passwordResetRequestPath)) {
            copy($passwordResetRequestStub, $passwordResetRequestPath);
            $trackedFiles[] = [
                'type' => 'request',
                'path' => $passwordResetRequestPath,
                'relative_path' => str_replace(base_path() . '/', '', $passwordResetRequestPath),
            ];
        }

        return $trackedFiles;
    }

    /**
     * Create AuthGates Middleware
     * Returns file info if created, null otherwise
     */
    protected function createAuthGates(): ?array
    {
        $middlewareDir = base_path('app/Http/Middleware');
        if (!is_dir($middlewareDir)) {
            mkdir($middlewareDir, 0755, true);
        }

        $authGatesPath = $middlewareDir . '/AuthGates.php';
        $authGatesStub = $this->getStubPath('middleware/AuthGates.stub');

        if (file_exists($authGatesStub) && !file_exists($authGatesPath)) {
            copy($authGatesStub, $authGatesPath);
            return [
                'type' => 'middleware',
                'path' => $authGatesPath,
                'relative_path' => str_replace(base_path() . '/', '', $authGatesPath),
            ];
        }

        return null;
    }

    /**
     * Create MICApiResponse Exception Handler
     * Returns file info if created, null otherwise
     */
    protected function createMICApiResponse(): ?array
    {
        $exceptionsDir = base_path('app/Exceptions');
        if (!is_dir($exceptionsDir)) {
            mkdir($exceptionsDir, 0755, true);
        }

        $micApiResponsePath = $exceptionsDir . '/MICApiResponse.php';
        $micApiResponseStub = $this->getStubPath('exceptions/MICApiResponse.stub');

        if (file_exists($micApiResponseStub) && !file_exists($micApiResponsePath)) {
            copy($micApiResponseStub, $micApiResponsePath);
            return [
                'type' => 'exception',
                'path' => $micApiResponsePath,
                'relative_path' => str_replace(base_path() . '/', '', $micApiResponsePath),
            ];
        }

        return null;
    }

    /**
     * Update Controller Base Class to include MicResponseTrait
     * Returns modification info if updated, null otherwise
     */
    protected function updateControllerBase(): ?array
    {
        $controllerPath = base_path('app/Http/Controllers/Controller.php');
        $controllerStub = $this->getStubPath('Controller.stub');

        if (file_exists($controllerStub)) {
            $stubContent = file_get_contents($controllerStub);
            // Only update if Controller doesn't have MicResponseTrait
            if (file_exists($controllerPath)) {
                $currentContent = file_get_contents($controllerPath);
                if (!str_contains($currentContent, 'MicResponseTrait')) {
                    // Backup original content to storage before modification
                    $this->backupFile($controllerPath, $currentContent);

                    file_put_contents($controllerPath, $stubContent);
                    return [
                        'type' => 'controller_base',
                        'path' => $controllerPath,
                        'relative_path' => str_replace(base_path() . '/', '', $controllerPath),
                        'original_content' => $currentContent, // For rollback
                    ];
                }
            } else {
                file_put_contents($controllerPath, $stubContent);
                return [
                    'type' => 'controller_base',
                    'path' => $controllerPath,
                    'relative_path' => str_replace(base_path() . '/', '', $controllerPath),
                    'original_content' => null, // New file, no original content
                ];
            }
        }

        return null;
    }

    /**
     * Create DatabaseSeeder if it doesn't exist
     */
    protected function createDatabaseSeeder(): void
    {
        $seederPath = base_path('database/seeders/DatabaseSeeder.php');
        $seederStub = $this->getStubPath('DatabaseSeeder.stub');

        if (file_exists($seederStub) && !file_exists($seederPath)) {
            copy($seederStub, $seederPath);
        }
    }

    /**
     * Update bootstrap/app.php to include AuthGates and MICApiResponse
     * Returns modification info if updated, null otherwise
     */
    protected function updateBootstrapApp(): ?array
    {
        $bootstrapAppPath = base_path('bootstrap/app.php');
        if (!file_exists($bootstrapAppPath)) {
            return null;
        }

        $content = file_get_contents($bootstrapAppPath);
        $originalContent = $content; // Backup for rollback

        // Backup original content to storage before modification
        $this->backupFile($bootstrapAppPath, $originalContent);

        $updated = false;

        // Check if AuthGates is already added
        if (!str_contains($content, 'AuthGates::class')) {
            // Add AuthGates to middleware groups
            if (str_contains($content, '->withMiddleware(function (Middleware $middleware): void {')) {
                // Check if statefulApi exists
                if (!str_contains($content, 'statefulApi()')) {
                    $middlewareCode = <<<'PHP'
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->appendToGroup(
            'api',
            [
                \App\Http\Middleware\AuthGates::class,
            ]
        );

        $middleware->appendToGroup(
            'web',
            [
                \App\Http\Middleware\AuthGates::class,
            ]
        );
PHP;
                    // Replace empty middleware function
                    $content = preg_replace(
                        '/->withMiddleware\(function \(Middleware \$middleware\): void \{[\s\n]*\/\/[\s\n]*\}\)/s',
                        '->withMiddleware(function (Middleware $middleware): void {' . "\n" . $middlewareCode . "\n    })",
                        $content
                    );
                } else {
                    // Add AuthGates after existing middleware code
                    $middlewareCode = <<<'PHP'

        $middleware->appendToGroup(
            'api',
            [
                \App\Http\Middleware\AuthGates::class,
            ]
        );

        $middleware->appendToGroup(
            'web',
            [
                \App\Http\Middleware\AuthGates::class,
            ]
        );
PHP;
                    // Insert before closing brace of middleware function
                    $content = preg_replace(
                        '/(->withMiddleware\(function \(Middleware \$middleware\): void \{[\s\S]*?)(\}\))/s',
                        '$1' . $middlewareCode . "\n    $2",
                        $content
                    );
                }
                $updated = true;
            }
        }

        // Check if MICApiResponse is already added
        if (!str_contains($content, 'MICApiResponse::exceptions')) {
            // Add MICApiResponse to exceptions handler
            if (str_contains($content, '->withExceptions(function (Exceptions $exceptions): void {')) {
                $exceptionsCode = <<<'PHP'
        if (request()->ajax() || request()->wantsJson() || request()->is('api/*')) {
            \App\Exceptions\MICApiResponse::exceptions($exceptions);
        }
PHP;
                // Replace empty exceptions function
                $content = preg_replace(
                    '/->withExceptions\(function \(Exceptions \$exceptions\): void \{[\s\n]*\/\/[\s\n]*\}\)/s',
                    '->withExceptions(function (Exceptions $exceptions): void {' . "\n" . $exceptionsCode . "\n    })",
                    $content
                );
                $updated = true;
            }
        }

        if ($updated) {
            file_put_contents($bootstrapAppPath, $content);
            return [
                'type' => 'bootstrap',
                'path' => $bootstrapAppPath,
                'relative_path' => str_replace(base_path() . '/', '', $bootstrapAppPath),
                'original_content' => $originalContent, // For rollback
            ];
        }

        return null;
    }

    /**
     * Create Blade Auth pages (login.blade.php, layouts, components)
     * Returns array of created files for tracking
     */
    protected function createBladeAuthPages(): array
    {
        $createdFiles = [];

        // Create AuthenticatedSessionController
        $controllerDir = base_path('app/Http/Controllers/Auth');
        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0755, true);
        }

        $controllerPath = $controllerDir . '/AuthenticatedSessionController.php';
        $controllerStub = $this->getStubPath('controller/AuthenticatedSessionController.stub');

        if (file_exists($controllerStub) && !file_exists($controllerPath)) {
            copy($controllerStub, $controllerPath);
            $createdFiles[] = [
                'type' => 'controller',
                'path' => $controllerPath,
                'relative_path' => str_replace(base_path() . '/', '', $controllerPath),
            ];
        }

        // Create PasswordResetLinkController
        $passwordResetLinkPath = $controllerDir . '/PasswordResetLinkController.php';
        $passwordResetLinkStub = $this->getStubPath('controller/PasswordResetLinkController.stub');
        if (file_exists($passwordResetLinkStub) && !file_exists($passwordResetLinkPath)) {
            copy($passwordResetLinkStub, $passwordResetLinkPath);
            $createdFiles[] = [
                'type' => 'controller',
                'path' => $passwordResetLinkPath,
                'relative_path' => str_replace(base_path() . '/', '', $passwordResetLinkPath),
            ];
        }

        // Create NewPasswordController
        $newPasswordPath = $controllerDir . '/NewPasswordController.php';
        $newPasswordStub = $this->getStubPath('controller/NewPasswordController.stub');
        if (file_exists($newPasswordStub) && !file_exists($newPasswordPath)) {
            copy($newPasswordStub, $newPasswordPath);
            $createdFiles[] = [
                'type' => 'controller',
                'path' => $newPasswordPath,
                'relative_path' => str_replace(base_path() . '/', '', $newPasswordPath),
            ];
        }

        // Create auth views directory
        $authViewsDir = base_path('resources/views/auth');
        if (!is_dir($authViewsDir)) {
            mkdir($authViewsDir, 0755, true);
        }

        // Create login.blade.php
        $loginViewPath = $authViewsDir . '/login.blade.php';
        $loginStub = $this->getStubPath('views/auth/login.blade.stub');
        if (file_exists($loginStub) && !file_exists($loginViewPath)) {
            copy($loginStub, $loginViewPath);
            $createdFiles[] = [
                'type' => 'view',
                'path' => $loginViewPath,
                'relative_path' => str_replace(base_path() . '/', '', $loginViewPath),
            ];
        }

        // Create layouts directory
        $layoutsDir = base_path('resources/views/layouts');
        if (!is_dir($layoutsDir)) {
            mkdir($layoutsDir, 0755, true);
        }

        // Create layouts/auth.blade.php
        $layoutPath = $layoutsDir . '/auth.blade.php';
        $layoutStub = $this->getStubPath('views/layouts/auth.blade.stub');
        if (file_exists($layoutStub) && !file_exists($layoutPath)) {
            copy($layoutStub, $layoutPath);
            $createdFiles[] = [
                'type' => 'view',
                'path' => $layoutPath,
                'relative_path' => str_replace(base_path() . '/', '', $layoutPath),
            ];
        }

        // Create components directory structure
        $componentsLayoutsDir = base_path('resources/views/components/layouts');
        if (!is_dir($componentsLayoutsDir)) {
            mkdir($componentsLayoutsDir, 0755, true);
        }

        // Create components/layouts/auth.blade.php
        $componentLayoutPath = $componentsLayoutsDir . '/auth.blade.php';
        $componentLayoutStub = $this->getStubPath('views/components/layouts/auth.blade.stub');
        if (file_exists($componentLayoutStub) && !file_exists($componentLayoutPath)) {
            copy($componentLayoutStub, $componentLayoutPath);
            $createdFiles[] = [
                'type' => 'component',
                'path' => $componentLayoutPath,
                'relative_path' => str_replace(base_path() . '/', '', $componentLayoutPath),
            ];
        }

        // Create components/auth directory
        $componentsAuthDir = base_path('resources/views/components/auth');
        if (!is_dir($componentsAuthDir)) {
            mkdir($componentsAuthDir, 0755, true);
        }

        // Create form-input.blade.php
        $formInputPath = $componentsAuthDir . '/form-input.blade.php';
        $formInputStub = $this->getStubPath('views/components/auth/form-input.blade.stub');
        if (file_exists($formInputStub) && !file_exists($formInputPath)) {
            copy($formInputStub, $formInputPath);
            $createdFiles[] = [
                'type' => 'component',
                'path' => $formInputPath,
                'relative_path' => str_replace(base_path() . '/', '', $formInputPath),
            ];
        }

        // Create form-checkbox.blade.php
        $formCheckboxPath = $componentsAuthDir . '/form-checkbox.blade.php';
        $formCheckboxStub = $this->getStubPath('views/components/auth/form-checkbox.blade.stub');
        if (file_exists($formCheckboxStub) && !file_exists($formCheckboxPath)) {
            copy($formCheckboxStub, $formCheckboxPath);
            $createdFiles[] = [
                'type' => 'component',
                'path' => $formCheckboxPath,
                'relative_path' => str_replace(base_path() . '/', '', $formCheckboxPath),
            ];
        }

        // Create alert.blade.php
        $alertPath = $componentsAuthDir . '/alert.blade.php';
        $alertStub = $this->getStubPath('views/components/auth/alert.blade.stub');
        if (file_exists($alertStub) && !file_exists($alertPath)) {
            copy($alertStub, $alertPath);
            $createdFiles[] = [
                'type' => 'component',
                'path' => $alertPath,
                'relative_path' => str_replace(base_path() . '/', '', $alertPath),
            ];
        }

        return $createdFiles;
    }

    /**
     * Create Auth routes
     * Returns array of created/modified files for tracking
     */
    protected function createAuthRoutes(): array
    {
        $trackedFiles = [];

        // Create routes/auth.php
        $authRoutesPath = base_path('routes/auth.php');
        $authRoutesStub = $this->getStubPath('routes/auth.stub');

        // Always update routes/auth.php from stub if it exists (to ensure it has correct content)
        if (file_exists($authRoutesStub)) {
            // Check if file doesn't exist or is empty/small (less than 100 bytes)
            $shouldUpdate = !file_exists($authRoutesPath) || 
                           (file_exists($authRoutesPath) && filesize($authRoutesPath) < 100);
            
            if ($shouldUpdate) {
                copy($authRoutesStub, $authRoutesPath);
                $trackedFiles[] = [
                    'type' => 'routes',
                    'path' => $authRoutesPath,
                    'relative_path' => str_replace(base_path() . '/', '', $authRoutesPath),
                ];
            }
        }

        // Update web.php to include auth routes
        $webRoutesPath = base_path('routes/web.php');
        if (file_exists($webRoutesPath)) {
            $content = file_get_contents($webRoutesPath);
            $originalContent = $content; // Backup for rollback

            // Ensure welcome route exists at root
            if (!str_contains($content, "Route::get('/', function () {")) {
                // Add welcome route after opening PHP tag
                $welcomeRoute = "Route::get('/', function () {\n    return view('welcome');\n});\n\n";
                $content = preg_replace(
                    '/(<\?php\n\nuse Illuminate\\\\Support\\\\Facades\\\\Route;)/',
                    "$1\n" . $welcomeRoute,
                    $content
                );
            }

            // Check if auth routes are already included
            if (!str_contains($content, "require __DIR__.'/auth.php'")) {
                // Add auth routes require after welcome route
                $authRequire = "// Auth Routes\nrequire __DIR__.'/auth.php';\n\n";

                // Insert after welcome route
                if (str_contains($content, "Route::get('/', function () {")) {
                    $content = preg_replace(
                        '/(Route::get\(\'\/\', function \(\) \{[^}]+\}\);)/s',
                        "$1\n\n" . $authRequire,
                        $content
                    );
                } else {
                    // Insert after opening PHP tag if welcome route doesn't exist
                    $content = preg_replace(
                        '/(<\?php\n\nuse Illuminate\\\\Support\\\\Facades\\\\Route;)/',
                        "$1\n\n" . $authRequire,
                        $content
                    );
                }
            }

            // Remove old /dashboard route if it exists
            $content = preg_replace(
                '/Route::get\(\'\/dashboard\', function \(\) \{[^}]+\}\)->middleware\(\'auth\'\)->name\(\'dashboard\'\);[\s\n]*/s',
                '',
                $content
            );

            // Add catch-all route for Vue Router if it doesn't exist
            if (!str_contains($content, "Route::get('/{any}'")) {
                $catchAllRoute = "// Catch-all route for Vue Router (SPA) - must be after all other routes\n// This allows Vue Router to handle all client-side routing\n// Excludes /login and /logout to allow public access and logout functionality\n// No Laravel auth middleware needed - Vue Router handles authentication\nRoute::get('/{any}', function () {\n    return view('dashboard');\n})->where('any', '^(?!login|logout|api|storage|images|css|js|fonts).*');\n";

                // Insert at the end of the file
                $content = rtrim($content) . "\n\n" . $catchAllRoute;
            } else {
                // Update existing catch-all route to include logout in exclusions
                $content = preg_replace(
                    '/Route::get\(\'\/\{any\}\', function \(\) \{[^}]+\}\)->where\(\'any\', \'\^\(\\?![^)]+\)\.\*\'\);[\s\n]*/s',
                    "Route::get('/{any}', function () {\n    return view('dashboard');\n})->where('any', '^(?!login|logout|api|storage|images|css|js|fonts).*');\n",
                    $content
                );
            }

            // Backup original content to storage before modification
            $this->backupFile($webRoutesPath, $originalContent);

            file_put_contents($webRoutesPath, $content);

            $trackedFiles[] = [
                'type' => 'routes',
                'path' => $webRoutesPath,
                'relative_path' => str_replace(base_path() . '/', '', $webRoutesPath),
                'original_content' => $originalContent, // For rollback
            ];
        }

        // Add auth API routes
        $apiRoutesPath = base_path('routes/api.php');
        if (file_exists($apiRoutesPath)) {
            $content = file_get_contents($apiRoutesPath);
            $originalContent = $content; // Backup for rollback

            if (!str_contains($content, "Route::post('/login'") && !str_contains($content, "Route::post('auth/login'")) {
                // Add use statement
                if (!str_contains($content, 'use App\\Http\\Controllers\\Auth\\AuthController')) {
                    if (str_contains($content, 'use Illuminate\\Support\\Facades\\Route;')) {
                        $content = preg_replace(
                            '/(use Illuminate\\\\Support\\\\Facades\\\\Route;)/',
                            "$1\nuse App\\Http\\Controllers\\Auth\\AuthController;",
                            $content
                        );
                    } else {
                        $content = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\nuse App\\Http\\Controllers\\Auth\\AuthController;\n" . ltrim($content, "<?php\n");
                    }
                }

                $authApiRoutes = <<<'PHP'

// Authentication API routes (public - no auth required)
Route::group(['prefix' => 'auth'], function () {
    Route::post('/login', [AuthController::class, 'login']);
    // Register is handled through admin panel (users management) - no public registration
});

// Authentication API routes (protected - requires auth)
Route::group(['prefix' => 'auth', 'middleware' => 'auth:sanctum'], function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});
PHP;

                // Check if v1 group exists
                if (str_contains($content, "Route::group(['prefix' => 'v1'") || str_contains($content, '// API Routes (Protected with Sanctum Authentication)')) {
                    // Add routes before the v1 group
                    $content = preg_replace(
                        '/(\/\/ API Routes \(Protected with Sanctum Authentication\))/s',
                        $authApiRoutes . "\n\n$1",
                        $content
                    );
                } else {
                    // If no v1 group exists, just append the auth routes
                    $content = rtrim($content) . "\n" . $authApiRoutes . "\n";
                }

                // Backup original content to storage before modification
                $this->backupFile($apiRoutesPath, $originalContent);

                file_put_contents($apiRoutesPath, $content);

                $trackedFiles[] = [
                    'type' => 'routes',
                    'path' => $apiRoutesPath,
                    'relative_path' => str_replace(base_path() . '/', '', $apiRoutesPath),
                    'original_content' => $originalContent, // For rollback
                ];
            }
        }

        return $trackedFiles;
    }

    /**
     * Create Auth pages (Login, Register Vue components)
     */
    protected function createAuthPages(): void
    {
        // This method would create Login.vue and Register.vue pages
        // Implementation depends on your Vue template structure
        // For now, we'll skip this as it's handled by DashboardSetupService
    }

    /**
     * Reset ALL generated models and restore files to original templates
     * This deletes all generated models (not just examples) and clears history
     */
    protected function resetAllModels(FileTrackerService $tracker): void
    {
        $generations = $tracker->getHistory();
        $filesToDelete = [];
        $filesToRevert = [];

        $this->line("   📊 Found " . count($generations) . " generations in history");

        // Process all generations (not just example models)
        $exampleModels = ['User', 'Role', 'Permission'];

        foreach ($generations as $gen) {
            $model = $gen['model'] ?? null;

            // Skip Dashboard setup generations
            if ($model === 'Dashboard') {
                $this->line("   ⏭️  Skipping Dashboard generation");
                continue;
            }

            $this->line("   📦 Processing model: " . ($model ?? 'Unknown'));

            $files = isset($gen['files_file']) ? $tracker->getGenerationFiles($gen['id']) : ($gen['files'] ?? []);

            $this->line("   📁 Found " . count($files) . " files for {$model}");

            foreach ($files as $file) {
                $filePath = $file['path'] ?? null;
                if (!$filePath) {
                    continue;
                }

                // Normalize path for Windows
                $filePath = str_replace('\\', '/', $filePath);
                if (!str_starts_with($filePath, base_path())) {
                    $filePath = base_path() . '/' . ltrim($filePath, '/');
                }

                // Check if file was created or modified
                // If original_content exists and is not null, file was modified
                // If original_content is null or doesn't exist, file was created
                if (isset($file['original_content']) && $file['original_content'] !== null) {
                    // File was modified - mark for revert
                    // But only if it's a Dashboard setup file (routes.ts, api.php, web.php, responses.ts)
                    $isDashboardFile = str_contains($filePath, 'resources/ts/shared/types/responses.ts') ||
                                      str_contains($filePath, 'resources/ts/_dashboard/router/routes.ts') ||
                                      str_contains($filePath, 'routes/web.php') ||
                                      str_contains($filePath, 'routes/api.php');

                    if ($isDashboardFile) {
                        $filesToRevert[] = [
                            'path' => $filePath,
                            'original_content' => $file['original_content'],
                            'model' => $model,
                        ];
                        $this->line("   🔄 Marked for revert: " . basename($filePath));
                    }
                } else {
                    // File was created (new file) - mark for deletion
                    // Exclude Dashboard setup files, migrations, and Example Models (they will be regenerated)
                    $isDashboardFile = str_contains($filePath, 'resources/ts/shared/types/responses.ts') ||
                                      str_contains($filePath, 'resources/ts/_dashboard/router/routes.ts') ||
                                      str_contains($filePath, 'routes/web.php') ||
                                      str_contains($filePath, 'routes/api.php');

                    $isMigration = str_contains($filePath, 'database/migrations/');

                    // Check if this is an Example Model file (will be regenerated)
                    $isExampleModelFile = $model && in_array($model, $exampleModels);

                    // Delete ALL created files (including Example Models - they will be regenerated)
                    // Except Dashboard files and migrations
                    if (!$isDashboardFile && !$isMigration && file_exists($filePath)) {
                        $filesToDelete[] = $filePath;
                        $label = $isExampleModelFile ? " (Example Model - will regenerate)" : "";
                        $this->line("   🗑️  Marked for deletion{$label}: " . str_replace(base_path() . '/', '', $filePath));
                    }
                }
            }
        }

        // Delete created files
        $deletedCount = 0;
        foreach ($filesToDelete as $filePath) {
            if (file_exists($filePath)) {
                try {
                    unlink($filePath);
                    $deletedCount++;
                    $this->line("   🗑️  Deleted: " . str_replace(base_path() . '/', '', $filePath));
                } catch (\Exception $e) {
                    $this->warn("⚠️  Could not delete: {$filePath} - " . $e->getMessage());
                }
            }
        }

        // Revert modified files (routes.ts, api.php, web.php, responses.ts, etc.)
        $revertedCount = 0;
        foreach ($filesToRevert as $file) {
            $filePath = $file['path'];
            if (file_exists($filePath) && $file['original_content'] !== null) {
                try {
                    file_put_contents($filePath, $file['original_content']);
                    $revertedCount++;
                    $this->line("   🔄 Reverted: " . str_replace(base_path() . '/', '', $filePath));
                } catch (\Exception $e) {
                    $this->warn("⚠️  Could not revert: {$filePath} - " . $e->getMessage());
                }
            }
        }

        // Don't clear history here - it will be cleared by --force option after resetAllModels
        // This allows us to access the history during resetAllModels

        $this->info("✅ All models reset completed! ({$deletedCount} files deleted, {$revertedCount} files reverted)");
    }

    /**
     * Backup file content to storage before modification
     */
    protected function backupFile(string $filePath, string $content): void
    {
        $backupDir = storage_path('app/mic-sole-laravel-gen/backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $relativePath = str_replace(base_path() . '/', '', $filePath);
        $backupPath = $backupDir . '/' . str_replace(['/', '\\'], '_', $relativePath) . '_' . time() . '.bak';

        file_put_contents($backupPath, $content);
    }
}
