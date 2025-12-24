<?php

namespace MicSoleLaravelGen\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncTemplatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mic-sole:sync-templates
                            {--file= : Sync specific file only}
                            {--force : Overwrite existing templates without confirmation}
                            {--dry-run : Show what would be synced without actually syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync modifications from generated files back to templates';

    protected $basePath;
    protected $stubPath;

    /**
     * Mapping of generated files to their template stubs
     */
    protected $fileMapping = [
        // Core files
        'resources/ts/_dashboard/utilities/langHelper.ts' => 'utilities/langHelper.stub',
        'resources/ts/_dashboard/utilities/primevue-locale.ts' => 'utilities/primevue-locale.stub',
        'resources/ts/vue-shim.d.ts' => 'vue-shim.d.ts.stub',
        'resources/ts/_dashboard/services/apiHandler.ts' => 'services/apiHandler.stub',

        // Layouts
        'resources/ts/_dashboard/layouts/PageHeader.vue' => 'layouts/PageHeader.stub',
        'resources/ts/_dashboard/layouts/main-layout.vue' => 'layouts/main-layout.stub',
        'resources/ts/_dashboard/layouts/app-sidebar.vue' => 'layouts/app-sidebar.stub',
        'resources/ts/_dashboard/layouts/app-header.vue' => 'layouts/app-header.stub',

        // Components
        'resources/ts/_dashboard/components/LogoutButton.vue' => 'components/LogoutButton.stub',
        'resources/ts/_dashboard/components/Dashboard_Statistic.vue' => 'components/Dashboard_Statistic.stub',
        'resources/ts/shared/components/AppButton.vue' => 'components/AppButton.stub',
        'resources/ts/shared/components/Can.vue' => 'components/Can.stub',
        'resources/ts/shared/components/AppChart.vue' => 'components/AppChart.stub',
        'resources/ts/shared/components/index.ts' => 'components/index.stub',
        'resources/ts/shared/components/exports/ExportAsPdf.vue' => 'components/exports/ExportAsPdf.stub',
        'resources/ts/shared/components/exports/ExportAsExcel.vue' => 'components/exports/ExportAsExcel.stub',
        'resources/ts/shared/components/exports/ExportAsImage.vue' => 'components/exports/ExportAsImage.stub',

        // Stores
        'resources/ts/shared/stores/auth_store.ts' => 'stores/auth_store.stub',
        'resources/ts/shared/stores/ui_store.ts' => 'stores/ui_store.stub',

        // Types
        'resources/ts/shared/types/responses.ts' => 'types/responses.stub',

        // Pages
        'resources/ts/_dashboard/pages/404.vue' => 'pages/404.stub',
        'resources/ts/_dashboard/pages/Dashboard.vue' => 'pages/Dashboard.stub',
        'resources/ts/_dashboard/pages/Authorization.vue' => 'pages/Authorization.stub',
        'resources/ts/_dashboard/pages/Settings.vue' => 'pages/Settings.stub',
        'resources/ts/_dashboard/pages/Profile.vue' => 'pages/Profile.stub',
        'resources/ts/_dashboard/pages/Login.vue' => 'pages/Login.stub',
        'resources/ts/_dashboard/pages/ForgotPassword.vue' => 'pages/ForgotPassword.stub',
        'resources/ts/_dashboard/pages/ResetPassword.vue' => 'pages/ResetPassword.stub',
        'resources/ts/_dashboard/pages/User.vue' => 'pages/User.stub',
        'resources/ts/_dashboard/pages/UserCreate.vue' => 'pages/UserCreate.stub',

        // Authorization components
        'resources/ts/_dashboard/pages/_authorization/users/table.vue' => 'pages/_authorization/user-table.stub',
        'resources/ts/_dashboard/pages/_authorization/roles/table.vue' => 'pages/_authorization/role-table.stub',
        'resources/ts/_dashboard/pages/_authorization/permissions/table.vue' => 'pages/_authorization/permission-table.stub',
        'resources/ts/_dashboard/pages/_authorization/users/form.vue' => 'pages/_authorization/user-form.stub',
        'resources/ts/_dashboard/pages/_authorization/users/form-show.vue' => 'pages/_authorization/user-form-show.stub',
        'resources/ts/_dashboard/pages/_authorization/users/password-change-form.vue' => 'pages/_authorization/user-password-change-form.stub',
        'resources/ts/_dashboard/pages/_authorization/users/filter.vue' => 'pages/_authorization/user-filter.stub',
        'resources/ts/_dashboard/pages/_authorization/roles/form.vue' => 'pages/_authorization/role-form.stub',
        'resources/ts/_dashboard/pages/_authorization/roles/form-show.vue' => 'pages/_authorization/role-form-show.stub',
        'resources/ts/_dashboard/pages/_authorization/role-form.vue' => 'pages/_authorization/role-form.stub',
        'resources/ts/_dashboard/pages/_authorization/permission-form.vue' => 'pages/_authorization/permission-form.stub',

        // Settings
        'resources/ts/_dashboard/pages/_settings/ApplicationTab.vue' => 'pages/_settings/ApplicationTab.stub',
        'resources/ts/shared/components/FontConfig.vue' => 'components/FontConfig.stub',
        'resources/ts/shared/components/ColorConfig.vue' => 'components/ColorConfig.stub',
        'resources/ts/shared/composables/useFontConfig.ts' => 'composables/useFontConfig.stub',
        'resources/ts/shared/composables/useColorConfig.ts' => 'composables/useColorConfig.stub',

        // App
        'resources/ts/_dashboard/App.vue' => 'App.vue.stub',
        'resources/ts/dashboard.ts' => 'dashboard.ts.stub',

        // Router
        'resources/ts/_dashboard/router/index.ts' => 'router/index.stub',
        'resources/ts/_dashboard/router/routes.ts' => 'router/routes.stub',
        'resources/ts/_dashboard/router/raws/dashboard.ts' => 'router/raws/dashboard.stub',
        'resources/ts/_dashboard/router/raws/auth.ts' => 'router/raws/auth.stub',
        'resources/ts/_dashboard/router/raws/authorization.ts' => 'router/raws/authorization.stub',
        'resources/ts/_dashboard/router/raws/settings.ts' => 'router/raws/settings.stub',

        // Views
        'resources/views/dashboard.blade.php' => 'dashboard.blade.stub',
        'resources/views/auth/login.blade.php' => 'views/auth/login.blade.stub',
        'resources/views/auth/forgot-password.blade.php' => 'views/auth/forgot-password.blade.stub',
        'resources/views/auth/reset-password.blade.php' => 'views/auth/reset-password.blade.stub',
        'resources/views/maintenance.blade.php' => 'views/maintenance.blade.stub',
        'resources/views/components/layouts/auth.blade.php' => 'views/components/layouts/auth.blade.stub',
        'resources/views/components/auth/form-input.blade.php' => 'views/components/auth/form-input.blade.stub',
        'resources/views/components/auth/form-checkbox.blade.php' => 'views/components/auth/form-checkbox.blade.stub',
        'resources/views/components/auth/alert.blade.php' => 'views/components/auth/alert.blade.stub',

        // CSS
        'resources/css/dashboard.css' => 'dashboard.css.stub',
        'resources/css/app.css' => 'app.css.stub',

        // Development Config Files
        'vite.config.js' => 'vite.config.stub',
        'tsconfig.json' => 'tsconfig.stub',
        'package.json' => 'package.json.stub',

        // CRUD Generator Files (Package Development UI)
        'resources/js/crud-generator/GeneratorTab.tsx' => 'crud-generator/GeneratorTab.stub',
        'resources/js/crud-generator/CrudGeneratorApp.tsx' => 'crud-generator/CrudGeneratorApp.stub',
        'resources/js/crud-generator/SettingsTab.tsx' => 'crud-generator/SettingsTab.stub',
        'resources/js/crud-generator/HistoryTab.tsx' => 'crud-generator/HistoryTab.stub',
        'resources/js/crud-generator/OtherFeaturesTab.tsx' => 'crud-generator/OtherFeaturesTab.stub',
        'resources/js/crud-generator/PreviewGenerator.tsx' => 'crud-generator/PreviewGenerator.stub',
        'resources/js/crud-generator/SelectMultiple.tsx' => 'crud-generator/SelectMultiple.stub',
        'resources/js/crud-generator/Font.tsx' => 'crud-generator/Font.stub',
        'resources/js/crud-generator/types.ts' => 'crud-generator/types.stub',

        // Lang (Arabic)
        'resources/lang/ar/common.php' => 'lang/common.stub',
        'resources/lang/ar/auth.php' => 'lang/auth.stub',
        'resources/lang/ar/passwords.php' => 'lang/passwords.stub',
        'resources/lang/ar/user.php' => 'lang/user.stub',
        'resources/lang/ar/role.php' => 'lang/role.stub',
        'resources/lang/ar/permission.php' => 'lang/permission.stub',
        'resources/lang/ar/setting.php' => 'lang/setting.stub',
        'resources/lang/ar/profile.php' => 'lang/profile.stub',

        // Helpers
        'app/Helpers/Upload.php' => 'helpers/Upload.stub',

        // Traits
        'app/Traits/MicResponseTrait.php' => 'traits/MicResponseTrait.stub',

        // Exceptions
        'app/Exceptions/MICApiResponse.php' => 'exceptions/MICApiResponse.stub',

        // Middleware
        'app/Http/Middleware/AuthGates.php' => 'middleware/AuthGates.stub',
        'app/Http/Middleware/CheckApplicationStatus.php' => 'middleware/CheckApplicationStatus.stub',

        // Services
        'app/Services/UserService.php' => 'services/UserService.stub',
        'app/Services/RoleService.php' => 'services/RoleService.stub',
        'app/Services/PermissionService.php' => 'services/PermissionService.stub',

        // Controllers
        'app/Http/Controllers/UserController.php' => 'controllers/UserController.stub',
        'app/Http/Controllers/DashboardController.php' => 'controllers/DashboardController.stub',
        'app/Http/Controllers/RoleController.php' => 'controllers/RoleController.stub',
        'app/Http/Controllers/PermissionController.php' => 'controllers/PermissionController.stub',
        'app/Http/Controllers/Auth/AuthController.php' => 'controller/AuthController.stub',
        'app/Http/Controllers/Auth/AuthenticatedSessionController.php' => 'controller/AuthenticatedSessionController.stub',
        'app/Http/Controllers/Auth/PasswordResetLinkController.php' => 'controller/PasswordResetLinkController.stub',
        'app/Http/Controllers/Auth/NewPasswordController.php' => 'controller/NewPasswordController.stub',

        // Requests
        'app/Http/Requests/UserRequest.php' => 'requests/UserRequest.stub',
        'app/Http/Requests/RoleRequest.php' => 'requests/RoleRequest.stub',
        'app/Http/Requests/PermissionRequest.php' => 'requests/PermissionRequest.stub',
        'app/Http/Requests/ChangePasswordRequest.php' => 'requests/ChangePasswordRequest.stub',
        'app/Http/Requests/Auth/LoginRequest.php' => 'requests/Auth/LoginRequest.stub',
        'app/Http/Requests/Auth/RegisterRequest.php' => 'requests/Auth/RegisterRequest.stub',
        'app/Http/Requests/Auth/PasswordResetRequest.php' => 'requests/Auth/PasswordResetRequest.stub',

        // Resources
        'app/Http/Resources/UserResource.php' => 'resources/UserResource.stub',
        'app/Http/Resources/RoleResource.php' => 'resources/RoleResource.stub',
        'app/Http/Resources/PermissionResource.php' => 'resources/PermissionResource.stub',

        // Mail
        'app/Mail/UserCredentialsMail.php' => 'mail/UserCredentialsMail.stub',

        // Email Views
        'resources/views/emails/user-credentials.blade.php' => 'views/emails/user-credentials.blade.stub',

        // Routes
        'routes/mic-sole-route.php' => 'routes/mic-sole-route.stub',

        // Seeders
        'database/seeders/ApplicationSeeder.php' => 'ApplicationSeeder.stub',
        'database/seeders/UserSeeder.php' => 'UserSeeder.stub',
        'database/seeders/RoleSeeder.php' => 'RoleSeeder.stub',
        'database/seeders/PermissionSeeder.php' => 'PermissionSeeder.stub',
        'database/seeders/DatabaseSeeder.php' => 'DatabaseSeeder.stub',

        // Policies
        'app/Policies/UserPolicy.php' => 'policies/UserPolicy.stub',
        'app/Policies/RolePolicy.php' => 'policies/RolePolicy.stub',
        'app/Policies/PermissionPolicy.php' => 'policies/PermissionPolicy.stub',

        // Routes
        'routes/auth.php' => 'routes/auth.stub',
        'routes/web.php' => 'routes/web.stub',
        'routes/api.php' => 'routes/api.stub',
        'routes/api/dashboard/api-users.php' => 'routes.stub',
        'routes/api/dashboard/api-roles.php' => 'routes.stub',
        'routes/api/dashboard/api-permissions.php' => 'routes.stub',

        // Models
        'app/Models/User.php' => 'models/UserModel.stub',
        'app/Models/Role.php' => 'models/RoleModel.stub',
        'app/Models/Permission.php' => 'models/PermissionModel.stub',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->basePath = base_path();
        $this->stubPath = \MicSoleLaravelGen\Providers\MicSoleLaravelGenServiceProvider::getTemplatesPath() . '/';

        $this->info('🔄 Syncing templates from generated files...');
        $this->newLine();

        $fileToSync = $this->option('file');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

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
     * Sync all files
     */
    protected function syncAllFiles(bool $force, bool $dryRun)
    {
        $synced = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($this->fileMapping as $generatedFile => $stubFile) {
            $result = $this->syncFile($generatedFile, $force, $dryRun, false);

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
    protected function syncFile(string $generatedFile, bool $force, bool $dryRun, bool $showOutput = true)
    {
        // Normalize path separators (convert backslash to forward slash for cross-platform compatibility)
        $generatedFile = str_replace('\\', '/', $generatedFile);

        // Check if file exists in mapping
        if (!isset($this->fileMapping[$generatedFile])) {
            if ($showOutput) {
                $this->error("❌ File not found in mapping: {$generatedFile}");
            }
            return 'error';
        }

        $stubFile = $this->fileMapping[$generatedFile];
        $generatedPath = $this->basePath . '/' . $generatedFile;
        $stubPath = $this->stubPath . $stubFile;

        // Check if generated file exists
        if (!File::exists($generatedPath)) {
            if ($showOutput) {
                $this->warn("⚠️  Generated file not found: {$generatedFile}");
            }
            return 'skipped';
        }

        // Check if stub directory exists, create if not
        $stubDir = dirname($stubPath);
        if (!File::exists($stubDir)) {
            if (!$dryRun) {
                File::makeDirectory($stubDir, 0755, true);
            }
            if ($showOutput) {
                $this->line("📁 Created directory: " . dirname($stubFile));
            }
        }

        // Check if stub already exists
        if (File::exists($stubPath) && !$force && !$dryRun) {
            if ($showOutput) {
                if (!$this->confirm("Template already exists: {$stubFile}. Overwrite?", false)) {
                    return 'skipped';
                }
            } else {
                return 'skipped';
            }
        }

        if ($dryRun) {
            if ($showOutput) {
                $this->line("📋 Would sync: {$generatedFile} → {$stubFile}");
            }
            return 'synced';
        }

        // Read generated file content
        $content = File::get($generatedPath);

        // Process content (remove project-specific values if needed)
        $content = $this->processContent($content, $generatedFile);

        // Write to stub
        File::put($stubPath, $content);

        if ($showOutput) {
            $this->info("✅ Synced: {$generatedFile} → {$stubFile}");
        }

        return 'synced';
    }

    /**
     * Process content to make it more generic for templates
     */
    protected function processContent(string $content, string $filePath): string
    {
        // For now, just return the content as-is
        // You can add logic here to replace project-specific values with placeholders

        // Example: Replace specific email addresses with placeholders
        // $content = preg_replace('/admin@example\.com/', '{{admin_email}}', $content);

        // Example: Replace specific URLs with placeholders
        // $content = preg_replace('/https?:\/\/[^\s\'"]+/', '{{api_url}}', $content);

        return $content;
    }
}


