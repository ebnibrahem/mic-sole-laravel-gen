<?php

namespace MicSoleLaravelGen\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DashboardSetupService
{
    protected $basePath;
    protected $stubPath;

    public function __construct()
    {
        $this->basePath = base_path();
        $this->stubPath = \MicSoleLaravelGen\Providers\MicSoleLaravelGenServiceProvider::getTemplatesPath() . '/';
    }

    /**
     * Setup dashboard structure
     * Returns array of created and modified files for tracking
     */
    public function setup(bool $force = false): array
    {
        // 1. Create directory structure
        $this->createDirectories();

        // 2. Copy/create core files
        $createdFiles = $this->createCoreFiles($force);

        // 3. Update vite.config.js
        $viteConfig = $this->updateViteConfig($force);

        // 4. Update tsconfig.json
        $tsConfig = $this->updateTsConfig($force);

        // 5. Update package.json (if needed)
        $packageJson = $this->updatePackageJson($force);

        // 6. Update composer.json (add Sanctum if needed)
        $composerJson = $this->updateComposerJson($force);

        // Combine all files
        $allFiles = array_merge(
            $createdFiles,
            array_filter([$viteConfig, $tsConfig, $packageJson, $composerJson])
        );

        return $allFiles;
    }

    /**
     * Create directory structure
     */
    protected function createDirectories(): void
    {
        $directories = [
            'resources/ts/_dashboard/utilities',
            'resources/ts/_dashboard/services',
            'resources/ts/_dashboard/types',
            'resources/ts/_dashboard/layouts',
            'resources/ts/_dashboard/components',
            'resources/ts/_dashboard/pages',
            'resources/ts/_dashboard/router',
            'resources/ts/_dashboard/router/raws',
            'resources/ts/shared',
            'resources/ts/shared/components',
            'resources/ts/shared/components/exports',
            'resources/ts/shared/stores',
        ];

        foreach ($directories as $dir) {
            $fullPath = $this->basePath . '/' . $dir;
            if (!File::exists($fullPath)) {
                File::makeDirectory($fullPath, 0755, true);
            }
        }
    }

    /**
     * Create core files
     * Returns array of created files for tracking
     */
    protected function createCoreFiles(bool $force): array
    {
        $createdFiles = [];
        $files = [
            'app/Helpers/Upload.php' => 'helpers/Upload.stub',
            'resources/ts/_dashboard/utilities/langHelper.ts' => 'utilities/langHelper.stub',
            'resources/ts/_dashboard/utilities/primevue-locale.ts' => 'utilities/primevue-locale.stub',
            'resources/ts/vue-shim.d.ts' => 'vue-shim.d.ts.stub',
            'resources/ts/_dashboard/services/apiHandler.ts' => 'services/apiHandler.stub',
            'resources/ts/_dashboard/layouts/PageHeader.vue' => 'layouts/PageHeader.stub',
            'resources/ts/_dashboard/layouts/main-layout.vue' => 'layouts/main-layout.stub',
            'resources/ts/_dashboard/layouts/app-sidebar.vue' => 'layouts/app-sidebar.stub',
            'resources/ts/_dashboard/layouts/app-header.vue' => 'layouts/app-header.stub',
            'resources/ts/_dashboard/components/LogoutButton.vue' => 'components/LogoutButton.stub',
            'resources/ts/_dashboard/pages/404.vue' => 'pages/404.stub',
            'resources/ts/_dashboard/pages/Dashboard.vue' => 'pages/Dashboard.stub',
            'resources/ts/_dashboard/pages/Authorization.vue' => 'pages/Authorization.stub',
            'resources/ts/_dashboard/pages/Settings.vue' => 'pages/Settings.stub',
            'resources/ts/_dashboard/pages/Profile.vue' => 'pages/Profile.stub',
            'resources/ts/_dashboard/pages/_authorization/user-table.vue' => 'pages/_authorization/user-table.stub',
            'resources/ts/_dashboard/pages/_authorization/role-table.vue' => 'pages/_authorization/role-table.stub',
            'resources/ts/_dashboard/pages/_authorization/permission-table.vue' => 'pages/_authorization/permission-table.stub',
            'resources/ts/_dashboard/pages/_authorization/role-form.vue' => 'pages/_authorization/role-form.stub',
            'resources/ts/_dashboard/pages/_authorization/permission-form.vue' => 'pages/_authorization/permission-form.stub',
            'resources/ts/_dashboard/App.vue' => 'App.vue.stub',
            'resources/ts/shared/types/responses.ts' => 'types/responses.stub',
            'resources/ts/shared/components/AppButton.vue' => 'components/AppButton.stub',
            'resources/ts/shared/components/Can.vue' => 'components/Can.stub',
            'resources/ts/shared/components/index.ts' => 'components/index.stub',
            'resources/ts/shared/components/exports/ExportAsPdf.vue' => 'components/exports/ExportAsPdf.stub',
            'resources/ts/shared/components/exports/ExportAsExcel.vue' => 'components/exports/ExportAsExcel.stub',
            'resources/ts/shared/components/exports/ExportAsImage.vue' => 'components/exports/ExportAsImage.stub',
            'resources/ts/shared/stores/auth_store.ts' => 'stores/auth_store.stub',
            'resources/ts/shared/stores/ui_store.ts' => 'stores/ui_store.stub',
            'resources/ts/dashboard.ts' => 'dashboard.ts.stub',
            'resources/css/dashboard.css' => 'dashboard.css.stub',
            'resources/views/dashboard.blade.php' => 'dashboard.blade.stub',
            'resources/lang/ar/common.php' => 'lang/common.stub',
            'resources/lang/ar/auth.php' => 'lang/auth.stub',
            'resources/lang/ar/passwords.php' => 'lang/passwords.stub',
        ];

        foreach ($files as $target => $stub) {
            $targetPath = $this->basePath . '/' . $target;
            $stubPath = $this->stubPath . $stub;

            // Skip if file exists and not forcing
            if (File::exists($targetPath) && !$force) {
                continue;
            }

            // Create directory if it doesn't exist
            $targetDir = dirname($targetPath);
            if (!File::exists($targetDir)) {
                File::makeDirectory($targetDir, 0755, true);
            }

            if (File::exists($stubPath)) {
                File::copy($stubPath, $targetPath);
                $createdFiles[] = [
                    'type' => $this->getFileType($target),
                    'path' => $targetPath,
                    'relative_path' => $target,
                ];
            } else {
                // Create from existing files if stub doesn't exist
                $this->createFileFromExisting($target, $stub);
                if (File::exists($targetPath)) {
                    $createdFiles[] = [
                        'type' => $this->getFileType($target),
                        'path' => $targetPath,
                        'relative_path' => $target,
                    ];
                }
            }
        }

        // Create router structure
        $routerIndexPath = $this->basePath . '/resources/ts/_dashboard/router/index.ts';
        $routerRoutesPath = $this->basePath . '/resources/ts/_dashboard/router/routes.ts';
        $routerRawsPath = $this->basePath . '/resources/ts/_dashboard/router/raws';

        // Create raws directory
        if (!File::exists($routerRawsPath)) {
            File::makeDirectory($routerRawsPath, 0755, true);
        }

        // Create router/index.ts if it doesn't exist or force
        if (!File::exists($routerIndexPath) || $force) {
            $stubPath = $this->stubPath . 'router/index.stub';
            if (File::exists($stubPath)) {
                File::copy($stubPath, $routerIndexPath);
                $createdFiles[] = [
                    'type' => 'router',
                    'path' => $routerIndexPath,
                    'relative_path' => 'resources/ts/_dashboard/router/index.ts',
                ];
            }
        }

        // Create router/routes.ts if it doesn't exist or force
        if (!File::exists($routerRoutesPath) || $force) {
            $stubPath = $this->stubPath . 'router/routes.stub';
            if (File::exists($stubPath)) {
                File::copy($stubPath, $routerRoutesPath);
                $createdFiles[] = [
                    'type' => 'router',
                    'path' => $routerRoutesPath,
                    'relative_path' => 'resources/ts/_dashboard/router/routes.ts',
                ];
            }
        }

        // Create router/raws/dashboard.ts if it doesn't exist or force
        $dashboardRoutesPath = $routerRawsPath . '/dashboard.ts';
        if (!File::exists($dashboardRoutesPath) || $force) {
            $stubPath = $this->stubPath . 'router/raws/dashboard.stub';
            if (File::exists($stubPath)) {
                File::copy($stubPath, $dashboardRoutesPath);
                $createdFiles[] = [
                    'type' => 'router',
                    'path' => $dashboardRoutesPath,
                    'relative_path' => 'resources/ts/_dashboard/router/raws/dashboard.ts',
                ];
            }
        }

        // Create router/raws/auth.ts if it doesn't exist or force
        $authRoutesPath = $routerRawsPath . '/auth.ts';
        if (!File::exists($authRoutesPath) || $force) {
            $stubPath = $this->stubPath . 'router/raws/auth.stub';
            if (File::exists($stubPath)) {
                File::copy($stubPath, $authRoutesPath);
                $createdFiles[] = [
                    'type' => 'router',
                    'path' => $authRoutesPath,
                    'relative_path' => 'resources/ts/_dashboard/router/raws/auth.ts',
                ];
            }
        }

        // Create router/raws/authorization.ts if it doesn't exist or force
        $authorizationRoutesPath = $routerRawsPath . '/authorization.ts';
        if (!File::exists($authorizationRoutesPath) || $force) {
            $stubPath = $this->stubPath . 'router/raws/authorization.stub';
            if (File::exists($stubPath)) {
                File::copy($stubPath, $authorizationRoutesPath);
                $createdFiles[] = [
                    'type' => 'router',
                    'path' => $authorizationRoutesPath,
                    'relative_path' => 'resources/ts/_dashboard/router/raws/authorization.ts',
                ];
            }
        }

        // Create router/raws/settings.ts if it doesn't exist or force
        $settingsRoutesPath = $routerRawsPath . '/settings.ts';
        if (!File::exists($settingsRoutesPath) || $force) {
            $stubPath = $this->stubPath . 'router/raws/settings.stub';
            if (File::exists($stubPath)) {
                File::copy($stubPath, $settingsRoutesPath);
                $createdFiles[] = [
                    'type' => 'router',
                    'path' => $settingsRoutesPath,
                    'relative_path' => 'resources/ts/_dashboard/router/raws/settings.ts',
                ];
            }
        }

        return $createdFiles;
    }

    /**
     * Get file type from path
     */
    protected function getFileType(string $path): string
    {
        if (str_contains($path, '.vue')) return 'vue';
        if (str_contains($path, '.ts')) return 'typescript';
        if (str_contains($path, '.css')) return 'css';
        if (str_contains($path, '.blade.php')) return 'blade';
        if (str_contains($path, '.php')) return 'php';
        return 'file';
    }

    /**
     * Create file from existing source
     */
    protected function createFileFromExisting(string $target, string $stubName): void
    {
        $targetPath = $this->basePath . '/' . $target;

        // Map stub names to existing files
        $fileMap = [
            'utilities/langHelper.stub' => 'resources/ts/_dashboard/utilities/langHelper.ts',
            'services/apiHandler.stub' => 'resources/ts/_dashboard/services/apiHandler.ts',
            'types/index.stub' => 'resources/ts/_dashboard/types/index.ts',
            'layouts/PageHeader.stub' => 'resources/ts/_dashboard/layouts/PageHeader.vue',
            'components/AppButton.stub' => 'resources/ts/shared/components/AppButton.vue',
            'components/Can.stub' => 'resources/ts/shared/components/Can.vue',
            'stores/auth_store.stub' => 'resources/ts/shared/stores/auth_store.ts',
            'stores/ui_store.stub' => 'resources/ts/shared/stores/ui_store.ts',
        ];

        if (isset($fileMap[$stubName]) && File::exists($this->basePath . '/' . $fileMap[$stubName])) {
            File::copy($this->basePath . '/' . $fileMap[$stubName], $targetPath);
        }
    }

    /**
     * Update vite.config.js
     * Returns file info if modified, null otherwise
     */
    protected function updateViteConfig(bool $force): ?array
    {
        $viteConfigPath = $this->basePath . '/vite.config.js';

        if (!File::exists($viteConfigPath)) {
            // Create new vite.config.js
            $stubPath = $this->stubPath . 'vite.config.stub';
            if (File::exists($stubPath)) {
                $stub = File::get($stubPath);
                File::put($viteConfigPath, $stub);
                return [
                    'type' => 'config',
                    'path' => $viteConfigPath,
                    'relative_path' => 'vite.config.js',
                    'original_content' => null, // New file
                ];
            }
            return null;
        }

        $content = File::get($viteConfigPath);
        $originalContent = $content; // Save original before any modifications

        // If force, replace with stub
        if ($force) {
            $stubPath = $this->stubPath . 'vite.config.stub';
            if (File::exists($stubPath)) {
                $stub = File::get($stubPath);
                File::put($viteConfigPath, $stub);
                return [
                    'type' => 'config',
                    'path' => $viteConfigPath,
                    'relative_path' => 'vite.config.js',
                    'original_content' => $originalContent,
                ];
            }
        }

        // Check if resolve import exists, if not add it
        if (!Str::contains($content, "import { resolve }")) {
            // Add resolve import if path import exists
            if (Str::contains($content, "import path from")) {
                $content = Str::replace("import path from", "import { resolve } from", $content);
            } elseif (!Str::contains($content, "from 'path'") && !Str::contains($content, 'from "path"')) {
                // Add import if it doesn't exist
                $content = Str::replace("import { defineConfig } from 'vite';", "import { defineConfig } from 'vite';\nimport { resolve } from 'path';", $content);
            }
        }

        // Remove duplicate aliases first
        if (preg_match('/alias:\s*\{([^}]*)\}/s', $content, $matches)) {
            $aliasBlock = $matches[1];
            $lines = explode("\n", $aliasBlock);
            $seen = [];
            $cleanedLines = [];

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (empty($trimmed) || $trimmed === ',') {
                    continue;
                }

                // Extract alias name (handles both 'alias' and "alias")
                if (preg_match("/['\"]?([^'\":\s]+)['\"]?\s*:/", $trimmed, $aliasMatch)) {
                    $aliasName = $aliasMatch[1];
                    if (!isset($seen[$aliasName])) {
                        $seen[$aliasName] = true;
                        $cleanedLines[] = $trimmed;
                    }
                } else {
                    $cleanedLines[] = $trimmed;
                }
            }

            $cleanedBlock = implode("\n            ", $cleanedLines);
            $content = preg_replace('/alias:\s*\{[^}]*\}/s', "alias: {\n            " . $cleanedBlock . "\n        }", $content);
        }

        // Add aliases
        $aliases = [
            '@dashboard' => "resolve(__dirname, 'resources/ts/_dashboard/')",
            '@shared' => "resolve(__dirname, 'resources/ts/shared/')",
            '@types' => "resolve(__dirname, 'resources/ts/shared/types/')",
            '@libs' => "resolve(__dirname, 'resources/ts/shared/libs/')",
            '@components' => "resolve(__dirname, 'resources/ts/shared/components/')",
            '@js' => "resolve(__dirname, 'resources/js/')",
            '@' => "resolve(__dirname, 'resources/js/')",
        ];

        if (Str::contains($content, 'resolve:') && Str::contains($content, 'alias:')) {
            // Update existing alias block - check for duplicates
            foreach ($aliases as $alias => $path) {
                // Check if alias exists (with any quote style or without quotes)
                $aliasExists = preg_match("/['\"]?{$alias}['\"]?\s*:/", $content);
                if (!$aliasExists) {
                    // Add alias if not present
                    if (preg_match('/alias:\s*\{([^}]*)\}/s', $content, $matches)) {
                        $aliasContent = trim($matches[1]);
                        $newAliasContent = $aliasContent;
                        if (!empty($aliasContent) && !Str::endsWith($aliasContent, ',')) {
                            $newAliasContent .= ',';
                        }
                        $newAliasContent .= "\n            '{$alias}': {$path},";
                        $content = preg_replace('/alias:\s*\{[^}]*\}/s', "alias: {\n            " . $newAliasContent . "\n        }", $content);
                    }
                }
            }
        } else {
            // Add resolve block if it doesn't exist
            $aliasStrings = [];
            foreach ($aliases as $alias => $path) {
                $aliasStrings[] = "\"{$alias}\": {$path}";
            }
            $resolveBlock = "    resolve: {\n        alias: {\n            " . implode(",\n            ", $aliasStrings) . ",\n        },\n    },";

            // Insert before closing brace of export default
            if (Str::contains($content, 'plugins:') && !Str::contains($content, 'resolve:')) {
                // Insert after plugins array
                $content = preg_replace('/(plugins:\s*\[[^\]]+\],)/s', '$1' . "\n" . $resolveBlock, $content);
            } else {
                // Insert before closing });
                $content = Str::replaceLast('});', $resolveBlock . "\n});", $content);
            }
        }

        // Add dashboard.ts to input array if not present
        if (!Str::contains($content, 'dashboard.ts')) {
            $content = preg_replace(
                "/(input:\s*\[[^\]]*'resources\/js\/mic-sole\.tsx',)/s",
                "$1\n                'resources/ts/dashboard.ts',",
                $content
            );
        }

        // Add dashboard.css to input array if not present
        if (!Str::contains($content, 'dashboard.css')) {
            // Try to add after app.css
            if (preg_match("/(input:\s*\[[^\]]*'resources\/css\/app\.css',)/s", $content)) {
                $content = preg_replace(
                    "/(input:\s*\[[^\]]*'resources\/css\/app\.css',)/s",
                    "$1\n                'resources/css/dashboard.css',",
                    $content
                );
            } else {
                // If app.css not found, add after first input entry
                $content = preg_replace(
                    "/(input:\s*\[[^\]]*'resources\/[^']+',)/s",
                    "$1\n                'resources/css/dashboard.css',",
                    $content,
                    1
                );
            }
        }

        // Check if content was modified
        if ($content !== $originalContent) {
            File::put($viteConfigPath, $content);
            return [
                'type' => 'config',
                'path' => $viteConfigPath,
                'relative_path' => 'vite.config.js',
                'original_content' => $originalContent,
            ];
        }

        return null;
    }

    /**
     * Update tsconfig.json
     * Returns file info if modified, null otherwise
     */
    protected function updateTsConfig(bool $force): ?array
    {
        $tsConfigPath = $this->basePath . '/tsconfig.json';
        $originalContent = File::exists($tsConfigPath) ? File::get($tsConfigPath) : null;

        if (!File::exists($tsConfigPath)) {
            // Create new tsconfig.json
            $stub = File::get($this->stubPath . 'tsconfig.stub');
            File::put($tsConfigPath, $stub);
            return [
                'type' => 'config',
                'path' => $tsConfigPath,
                'relative_path' => 'tsconfig.json',
                'original_content' => null, // New file
            ];
        }

        $content = File::get($tsConfigPath);
        $originalContent = $content;

        // Update paths using regex (avoid JSON parsing issues with comments)
        $paths = [
            '@shared/*' => ['./resources/ts/shared/*'],
            '@types/*' => ['./resources/ts/shared/types/*'],
            '@libs/*' => ['./resources/ts/shared/libs/*'],
            '@components/*' => ['./resources/ts/shared/components/*'],
        ];

        foreach ($paths as $pathKey => $pathValue) {
            $pathValueStr = json_encode($pathValue, JSON_UNESCAPED_SLASHES);

            // Check if path already exists
            if (preg_match('/"@shared\/\*"/', $content) && str_contains($content, 'resources/ts/shared/')) {
                // Update existing path
                $content = preg_replace(
                    '/"@shared\/\*"\s*:\s*\[[^\]]+\]/',
                    "\"@shared/*\": {$pathValueStr}",
                    $content
                );
            } elseif (!str_contains($content, "\"{$pathKey}\"")) {
                // Add new path if not exists
                if (preg_match('/"paths"\s*:\s*\{/', $content)) {
                    // Add before closing brace of paths
                    $content = preg_replace(
                        '/("paths"\s*:\s*\{[^}]*)(\})/',
                        "$1            \"{$pathKey}\": {$pathValueStr},\n$2",
                        $content
                    );
                }
            }
        }

        // Ensure @shared paths are correct
        $content = str_replace('./resources/ts/_shared/', './resources/ts/shared/', $content);

        // Check if content was modified
        if ($content !== $originalContent) {
            File::put($tsConfigPath, $content);
            return [
                'type' => 'config',
                'path' => $tsConfigPath,
                'relative_path' => 'tsconfig.json',
                'original_content' => $originalContent,
            ];
        }

        return null;
    }

    /**
     * Update package.json
     * Returns file info if modified, null otherwise
     */
    protected function updatePackageJson(bool $force): ?array
    {
        $packageJsonPath = $this->basePath . '/package.json';

        if (!File::exists($packageJsonPath)) {
            return null;
        }

        $content = File::get($packageJsonPath);
        $originalContent = $content;
        $package = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Add vue and primevue if not present
        $requiredDeps = [
            'vue' => '^3.4.0',
            'primevue' => '^4.0.0',
            'vue-router' => '^4.0.0',
            'axios' => '^1.8.0',
            'jspdf' => '^2.5.1',
            'jspdf-autotable' => '^3.8.3',
            'xlsx' => '^0.18.5',
            // TipTap Editor dependencies
            '@tiptap/vue-3' => '^3.12.0',
            '@tiptap/starter-kit' => '^3.12.0',
            '@tiptap/extension-text-align' => '^3.12.0',
            '@tiptap/extension-underline' => '^3.12.0',
            '@tiptap/extension-link' => '^3.12.0',
            '@tiptap/extension-image' => '^3.12.0',
            '@tiptap/extension-color' => '^3.12.0',
            '@tiptap/extension-text-style' => '^3.12.0',
            '@tiptap/extension-highlight' => '^3.12.0',
        ];

        $needsUpdate = false;
        if (!isset($package['dependencies'])) {
            $package['dependencies'] = [];
        }

        foreach ($requiredDeps as $dep => $version) {
            if (!isset($package['dependencies'][$dep])) {
                $package['dependencies'][$dep] = $version;
                $needsUpdate = true;
            }
        }

        if ($needsUpdate) {
            $newContent = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            File::put($packageJsonPath, $newContent);
            return [
                'type' => 'config',
                'path' => $packageJsonPath,
                'relative_path' => 'package.json',
                'original_content' => $originalContent,
            ];
        }

        return null;
    }

    /**
     * Update composer.json to add required packages
     * Returns file info if modified, null otherwise
     */
    protected function updateComposerJson(bool $force): ?array
    {
        $composerJsonPath = $this->basePath . '/composer.json';

        if (!File::exists($composerJsonPath)) {
            return null;
        }

        $content = File::get($composerJsonPath);
        $originalContent = $content;
        $composer = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Add laravel/sanctum if not present (required for API authentication)
        $requiredPackages = [
            'laravel/sanctum' => '^4.0',
        ];

        // Add Pest for testing if not present
        $requiredDevPackages = [
            'pestphp/pest' => '*',
        ];

        $needsUpdate = false;
        if (!isset($composer['require'])) {
            $composer['require'] = [];
        }

        foreach ($requiredPackages as $package => $version) {
            if (!isset($composer['require'][$package])) {
                $composer['require'][$package] = $version;
                $needsUpdate = true;
            }
        }

        // Check and add dev dependencies
        if (!isset($composer['require-dev'])) {
            $composer['require-dev'] = [];
        }

        foreach ($requiredDevPackages as $package => $version) {
            if (!isset($composer['require-dev'][$package])) {
                $composer['require-dev'][$package] = $version;
                $needsUpdate = true;
            }
        }

        // Add Pest plugin to allow-plugins if config exists
        if (isset($composer['config']['allow-plugins'])) {
            if (!isset($composer['config']['allow-plugins']['pestphp/pest-plugin'])) {
                $composer['config']['allow-plugins']['pestphp/pest-plugin'] = true;
                $needsUpdate = true;
            }
        } elseif (isset($composer['config'])) {
            $composer['config']['allow-plugins'] = [
                'pestphp/pest-plugin' => true,
            ];
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            $newContent = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            File::put($composerJsonPath, $newContent);
            return [
                'type' => 'config',
                'path' => $composerJsonPath,
                'relative_path' => 'composer.json',
                'original_content' => $originalContent,
            ];
        }

        return null;
    }
}


