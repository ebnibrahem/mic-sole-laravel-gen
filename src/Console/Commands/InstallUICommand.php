<?php

namespace MicSoleLaravelGen\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallUICommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mic-sole:install-ui
                            {--force : Overwrite existing files without confirmation}
                            {--dry-run : Show what would be installed without actually installing}
                            {--update-package-json : Automatically update package.json with missing dependencies}
                            {--skip-routes : Skip adding routes to web.php}
                            {--skip-vite : Skip updating vite.config.js}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install CRUD generator UI files from package to project';

    protected $packagePath;
    protected $projectPath;

    /**
     * UI files to install from package to project
     */
    protected $uiFiles = [
        'resources/js/crud-generator',
        'resources/js/mic-sole.tsx',
        'resources/css/mic-sole.css',
        'resources/views/generator.blade.php',
        'resources/js/components/ui', // UI components
    ];

    /**
     * Required production dependencies
     */
    protected $requiredDependencies = [
        'react' => '^19.1.0',
        'react-dom' => '^19.1.0',
        'lucide-react' => '^0.525.0',
        '@radix-ui/react-checkbox' => '^1.3.2',
        '@radix-ui/react-popover' => '^1.1.14',
        '@radix-ui/react-select' => '^2.2.5',
        '@radix-ui/react-slot' => '^1.2.3',
        '@radix-ui/react-switch' => '^1.2.5',
        'class-variance-authority' => '^0.7.1',
        'clsx' => '^2.1.1',
        'tailwind-merge' => '^3.3.1',
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

    /**
     * Required development dependencies
     */
    protected $requiredDevDependencies = [
        '@vitejs/plugin-react' => '^4.7.0',
        'typescript' => '^5.9.3',
        'vite' => '^6.3.5',
        'laravel-vite-plugin' => '^1.3.0',
        'tailwindcss' => '^4.1.11',
        '@tailwindcss/vite' => '^4.1.11',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->packagePath = \MicSoleLaravelGen\Providers\MicSoleLaravelGenServiceProvider::getPackagePath();
        $this->projectPath = base_path();

        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info('📦 Installing CRUD Generator UI files...');
        $this->newLine();

        if (!File::exists($this->packagePath)) {
            $this->error("❌ Package path not found: {$this->packagePath}");
            return Command::FAILURE;
        }

        // 1. Copy UI files
        $this->info('📁 Copying UI files...');
        $this->copyUIFiles($force, $dryRun);

        // 2. Add routes
        if (!$dryRun && !$this->option('skip-routes')) {
            $this->newLine();
            $this->info('🛣️  Adding routes...');
            $this->addRoutes($force);
        }

        // 3. Update vite.config.js
        if (!$dryRun && !$this->option('skip-vite')) {
            $this->newLine();
            $this->info('⚙️  Updating vite.config.js...');
            $this->updateViteConfig($force);
        }

        // 4. Check and update package.json dependencies
        if (!$dryRun) {
            $this->newLine();
            $this->info('📋 Checking package.json dependencies...');
            $this->checkPackageJson($this->option('update-package-json'));
        }

        // 5. Show next steps
        $this->newLine();
        $this->showNextSteps();

        return Command::SUCCESS;
    }

    /**
     * Copy UI files from package to project
     */
    protected function copyUIFiles(bool $force, bool $dryRun)
    {
        $copied = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($this->uiFiles as $file) {
            $sourcePath = $this->packagePath . '/' . $file;
            $targetPath = $this->projectPath . '/' . $file;

            if (!File::exists($sourcePath)) {
                $this->warn("⚠️  Package file not found: {$file}");
                $skipped++;
                continue;
            }

            // Check if target exists
            if (File::exists($targetPath) && !$force && !$dryRun) {
                if (!$this->confirm("File exists: {$file}. Overwrite?", false)) {
                    $skipped++;
                    continue;
                }
            }

            if ($dryRun) {
                $this->line("📋 Would copy: {$file}");
                $copied++;
                continue;
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

                $this->info("✅ Copied: {$file}");
                $copied++;
            } catch (\Exception $e) {
                $this->error("❌ Error copying {$file}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->line("   Copied: {$copied}, Skipped: {$skipped}, Errors: {$errors}");
    }

    /**
     * Add routes from package to project web.php
     */
    protected function addRoutes(bool $force)
    {
        // Copy template file to routes directory
        $templateFile = $this->packagePath . '/src/Templates/routes/mic-sole-route.stub';
        $routesFile = $this->projectPath . '/routes/mic-sole-route.php';
        $projectRoutesFile = $this->projectPath . '/routes/web.php';

        if (!File::exists($templateFile)) {
            $this->warn("⚠️  Routes template file not found: {$templateFile}");
            return;
        }

        if (!File::exists($projectRoutesFile)) {
            $this->error("❌ Project routes file not found: {$projectRoutesFile}");
            return;
        }

        // Copy template to routes directory (convert .stub to .php)
        $templateContent = File::get($templateFile);
        // Add PHP opening tag if not present
        if (strpos($templateContent, '<?php') === false) {
            $templateContent = "<?php\n\n" . $templateContent;
        }
        File::put($routesFile, $templateContent);
        $this->info("✅ Copied routes file: routes/mic-sole-route.php");

        $projectRoutes = File::get($projectRoutesFile);

        // Check if require statement is already uncommented
        $requireUncommented = preg_match('/require\s+__DIR__\.[\'"]\/mic-sole-route\.php[\'"];?/', $projectRoutes) &&
                              !preg_match('/\/\/\s*require\s+__DIR__\.[\'"]\/mic-sole-route\.php[\'"];?/', $projectRoutes);

        if ($requireUncommented && !$force) {
            $this->line("   Routes already uncommented in web.php");
            return;
        }

        // Uncomment require statement
        $projectRoutes = preg_replace(
            '/\/\/\s*CRUD Generator Routes\s*\n\s*\/\/\s*require\s+__DIR__\.[\'"]\/mic-sole-route\.php[\'"];?/',
            "// CRUD Generator Routes\nrequire __DIR__.'/mic-sole-route.php';",
            $projectRoutes
        );

        // Also handle if comment is on same line
        $projectRoutes = preg_replace(
            '/\/\/\s*require\s+__DIR__\.[\'"]\/mic-sole-route\.php[\'"];?/',
            "require __DIR__.'/mic-sole-route.php';",
            $projectRoutes
        );

        File::put($projectRoutesFile, $projectRoutes);
        $this->info("✅ Routes require statement added to web.php");
    }

    /**
     * Extract routes from package routes file
     */
    protected function extractRoutes(string $content): string
    {
        // Extract use statement if exists
        $useStatement = '';
        if (preg_match('/use\s+MicSoleLaravelGen\\\Http\\\Controllers\\\CrudGeneratorController;/', $content)) {
            $useStatement = "use MicSoleLaravelGen\Http\Controllers\CrudGeneratorController;";
        }

        // Extract generator routes - read line by line to ensure complete routes
        $routes = [];
        $lines = explode("\n", $content);
        $currentRoute = '';
        $inRoute = false;
        $braceCount = 0;
        $bracketCount = 0;

        foreach ($lines as $line) {
            // Check if line starts a generator route
            if (preg_match('/Route::(post|get)\([\'"]\/generator[\'"]/', $line)) {
                $inRoute = true;
                $currentRoute = $line;
                // Count brackets and braces
                $bracketCount += substr_count($line, '[') - substr_count($line, ']');
                $braceCount += substr_count($line, '{') - substr_count($line, '}');
            } elseif ($inRoute) {
                $currentRoute .= "\n" . $line;
                $bracketCount += substr_count($line, '[') - substr_count($line, ']');
                $braceCount += substr_count($line, '{') - substr_count($line, '}');

                // Check if route is complete (ends with ; and brackets/braces are balanced)
                if (strpos($line, ';') !== false && $bracketCount === 0 && $braceCount === 0) {
                    $routes[] = trim($currentRoute);
                    $currentRoute = '';
                    $inRoute = false;
                    $braceCount = 0;
                    $bracketCount = 0;
                }
            }
        }

        // Fallback: if line-by-line didn't work, use regex
        if (empty($routes)) {
            // Match Route::post with array parameter (single line)
            if (preg_match('/Route::post\([\'"]\/generator[\'"],\s*\[[^\]]+\]\);/', $content, $postMatch)) {
                $routes[] = trim($postMatch[0]);
            }

            // Match Route::get with function (multiline)
            if (preg_match('/Route::get\([\'"]\/generator[\'"],\s*function\s*\(\)\s*\{[^}]*\}\);/s', $content, $getMatch)) {
                $routes[] = trim($getMatch[0]);
            }
        }

        // Ensure we have both routes - if missing, add from template
        $hasPost = false;
        $hasGet = false;
        foreach ($routes as $route) {
            if (strpos($route, 'Route::post') !== false) {
                $hasPost = true;
            }
            if (strpos($route, 'Route::get') !== false && strpos($route, '/generator') !== false) {
                $hasGet = true;
            }
        }

        // If missing routes, add them from default template
        if (!$hasPost) {
            array_unshift($routes, "Route::post('/generator', [CrudGeneratorController::class, 'generate']);");
        }
        if (!$hasGet) {
            $routes[] = "Route::get('/generator', function () {\n    return view('generator');\n});";
        }

        $routesString = implode("\n", $routes);

        // Add comment and use statement
        $result = "// CRUD Generator Routes\n";
        if ($useStatement) {
            $result .= $useStatement . "\n";
        }
        $result .= $routesString;

        return $result;
    }

    /**
     * Update vite.config.js to include UI files
     */
    protected function updateViteConfig(bool $force)
    {
        $viteConfigPath = $this->projectPath . '/vite.config.js';

        if (!File::exists($viteConfigPath)) {
            $this->warn("⚠️  vite.config.js not found");
            return;
        }

        $content = File::get($viteConfigPath);

        // Check if already uncommented (only skip if force is not used)
        if (!$force) {
            $alreadyUncommented = strpos($content, "'resources/js/mic-sole.tsx'") !== false &&
                                  strpos($content, "// 'resources/js/mic-sole.tsx'") === false;
            $hasReactUncommented = strpos($content, 'react({') !== false &&
                                   strpos($content, '// react({') === false;
            $hasVueIncludeUncommented = strpos($content, 'include: /\.vue$/') !== false &&
                                        strpos($content, '// include: /\.vue$/') === false;

            // If already uncommented, skip unless force
            if ($alreadyUncommented && $hasReactUncommented && $hasVueIncludeUncommented) {
                $this->line("   vite.config.js already contains UI files and proper configuration");
                return;
            }
        }

        // Check if react plugin is imported
        $hasReactPlugin = strpos($content, '@vitejs/plugin-react') !== false;

        if (!$hasReactPlugin) {
            // Add react plugin import
            $content = preg_replace(
                "/(import.*from ['\"]@vitejs\/plugin-vue['\"];)/",
                '$1' . "\nimport react from '@vitejs/plugin-react';",
                $content
            );
        }

        // Uncomment react plugin block (multiline)
        $content = preg_replace(
            '/\/\/\s*react\(\{\s*\n\s*\/\/\s*include:\s*\/\\.\(jsx\|tsx\)\$,?\s*\n\s*\/\/\s*exclude:\s*\/node_modules\|\\\.vue\$,?\s*\n\s*\/\/\s*\}\),?/s',
            "react({\n            include: /\.(jsx|tsx)$/,\n            exclude: /node_modules|\.vue$/,\n        }),",
            $content
        );

        // Also handle if react is commented but not the full block
        // Uncomment react plugin line by line (even if react({ is not commented)
        // Uncomment include line (handle various indentation patterns)
        // Pattern 1: react({ followed by commented include on next line
        $content = preg_replace(
            '/react\(\{\s*\n\s*\/\/\s*include:\s*\/\\.\(jsx\|tsx\)\$,?\s*\n/',
            "react({\n            include: /\.(jsx|tsx)$/,\n",
            $content
        );
        // Pattern 2: react({ with commented include on same line or different spacing
        $content = preg_replace(
            '/react\(\{\s*\n\s*\/\/\s*include:\s*\/\\.\(jsx\|tsx\)\$,/',
            "react({\n            include: /\.(jsx|tsx)$,",
            $content
        );
        // Pattern 3: Handle weird spacing like "//     include:" with tabs/spaces (multiline)
        $content = preg_replace(
            '/react\(\{\s*\n\s*\/\/\s+include:\s*\/\\.\(jsx\|tsx\)\$,?\s*\n/',
            "react({\n            include: /\.(jsx|tsx)$/,\n",
            $content
        );
        // Pattern 4: Handle case where include is commented with weird indentation (like "//     include:")
        $content = preg_replace(
            '/react\(\{\s*\n\s*\/\/\s+include:\s*\/\\.\(jsx\|tsx\)\$,?\s*\n/',
            "react({\n            include: /\.(jsx|tsx)$/,\n",
            $content
        );
        // Pattern 5: Direct replacement of commented include anywhere after react({
        $content = preg_replace(
            '/(react\(\{[\s\S]*?)\/\/\s*include:\s*\/\\.\(jsx\|tsx\)\$,?/',
            '$1            include: /\.(jsx|tsx)$/,',
            $content
        );
        // Uncomment exclude line (handle various patterns)
        // Pattern 1: exclude on separate line after include
        $content = preg_replace(
            '/\n\s*\/\/\s*exclude:\s*\/node_modules\|\\\.vue\$,?\s*\n/',
            "\n            exclude: /node_modules|\.vue$/,\n",
            $content
        );
        // Pattern 2: exclude with weird spacing
        $content = preg_replace(
            '/\n\s*\/\/\s+exclude:\s*\/node_modules\|\\\.vue\$,?\s*\n/',
            "\n            exclude: /node_modules|\.vue$/,\n",
            $content
        );
        // Pattern 3: exclude on same line
        $content = preg_replace(
            '/\s*\/\/\s*exclude:\s*\/node_modules\|\\\.vue\$,/',
            "            exclude: /node_modules|\.vue$,",
            $content
        );

        // Uncomment vue include (handle various patterns)
        $content = preg_replace(
            '/vue\(\{\s*\n\s*\/\/\s*include:\s*\/\\.vue\$,?\s*\n/',
            "vue({\n            include: /\.vue$,\n",
            $content
        );
        // Also handle if include is on same line
        $content = preg_replace(
            '/vue\(\{\s*\n\s*\/\/\s*include:\s*\/\\.vue\$,/',
            "vue({\n            include: /\.vue$,",
            $content
        );
        // Direct replacement of commented include anywhere after vue({
        $content = preg_replace(
            '/(vue\(\{[\s\S]*?)\/\/\s*include:\s*\/\\.vue\$,?/',
            '$1            include: /\.vue$,',
            $content
        );

        // Uncomment Components include (handle various patterns)
        $content = preg_replace(
            '/Components\(\{\s*\n\s*\/\/\s*include:\s*\/\\.vue\$,?\s*\n/',
            "Components({\n            include: /\.vue$,\n",
            $content
        );
        // Also handle if include is on same line
        $content = preg_replace(
            '/Components\(\{\s*\n\s*\/\/\s*include:\s*\/\\.vue\$,/',
            "Components({\n            include: /\.vue$,",
            $content
        );
        // Direct replacement of commented include anywhere after Components({
        $content = preg_replace(
            '/(Components\(\{[\s\S]*?)\/\/\s*include:\s*\/\\.vue\$,?/',
            '$1            include: /\.vue$,',
            $content
        );

        // Uncomment mic-sole.tsx and mic-sole.css in input array
        $content = preg_replace(
            "/\/\/\s*['\"]resources\/js\/mic-sole\.tsx['\"],?\s*\n?/",
            "'resources/js/mic-sole.tsx',\n                ",
            $content
        );
        $content = preg_replace(
            "/\/\/\s*['\"]resources\/css\/mic-sole\.css['\"],?\s*\n?/",
            "'resources/css/mic-sole.css',\n                ",
            $content
        );

        // Also handle if they're on the same line with comment
        $content = preg_replace(
            "/\/\/\s*['\"]resources\/js\/mic-sole\.tsx['\"],/",
            "'resources/js/mic-sole.tsx',",
            $content
        );
        $content = preg_replace(
            "/\/\/\s*['\"]resources\/css\/mic-sole\.css['\"],/",
            "'resources/css/mic-sole.css',",
            $content
        );

        File::put($viteConfigPath, $content);
        $this->info("✅ Updated vite.config.js");
    }

    /**
     * Check package.json for required dependencies
     */
    protected function checkPackageJson(bool $updatePackageJson = false)
    {
        $packageJsonPath = $this->projectPath . '/package.json';

        if (!File::exists($packageJsonPath)) {
            $this->warn("⚠️  package.json not found");
            return;
        }

        $packageJson = json_decode(File::get($packageJsonPath), true);

        if ($packageJson === null) {
            $this->error("❌ Invalid package.json file");
            return;
        }

        $missingDeps = [];
        $missingDevDeps = [];

        // Check production dependencies
        foreach ($this->requiredDependencies as $dep => $version) {
            $exists = false;
            if (isset($packageJson['dependencies'][$dep]) || isset($packageJson['devDependencies'][$dep])) {
                $exists = true;
            }
            if (!$exists) {
                $missingDeps[$dep] = $version;
            }
        }

        // Check dev dependencies
        foreach ($this->requiredDevDependencies as $dep => $version) {
            $exists = false;
            if (isset($packageJson['devDependencies'][$dep])) {
                $exists = true;
            }
            if (!$exists) {
                $missingDevDeps[$dep] = $version;
            }
        }

        if (empty($missingDeps) && empty($missingDevDeps)) {
            $this->info("✅ All required dependencies are present");
        } else {
            if (!empty($missingDeps)) {
                $this->warn("⚠️  Missing production dependencies:");
                foreach ($missingDeps as $dep => $version) {
                    $this->line("   - {$dep}: {$version}");
                }
            }

            if (!empty($missingDevDeps)) {
                $this->warn("⚠️  Missing development dependencies:");
                foreach ($missingDevDeps as $dep => $version) {
                    $this->line("   - {$dep}: {$version}");
                }
            }

            if ($updatePackageJson) {
                // Update package.json automatically
                $this->newLine();
                $this->info("📝 Updating package.json...");

                // Ensure dependencies and devDependencies arrays exist
                if (!isset($packageJson['dependencies'])) {
                    $packageJson['dependencies'] = [];
                }
                if (!isset($packageJson['devDependencies'])) {
                    $packageJson['devDependencies'] = [];
                }

                // Add missing production dependencies
                foreach ($missingDeps as $dep => $version) {
                    $packageJson['dependencies'][$dep] = $version;
                    $this->line("   ✅ Added: {$dep}@{$version}");
                }

                // Add missing dev dependencies
                foreach ($missingDevDeps as $dep => $version) {
                    $packageJson['devDependencies'][$dep] = $version;
                    $this->line("   ✅ Added: {$dep}@{$version} (dev)");
                }

                // Save updated package.json
                $updatedJson = json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                File::put($packageJsonPath, $updatedJson . "\n");

                $this->info("✅ package.json updated successfully!");
                $this->newLine();
                $this->line("💡 Run the following command to install dependencies:");
                $this->line("   npm install");
            } else {
                $this->newLine();
                $this->line("💡 Run the following command to install missing dependencies:");
                $this->newLine();

                $installCmd = "npm install";
                if (!empty($missingDeps)) {
                    foreach ($missingDeps as $dep => $version) {
                        $installCmd .= " {$dep}@{$version}";
                    }
                }

                if (!empty($missingDevDeps)) {
                    $installCmd .= " --save-dev";
                    foreach ($missingDevDeps as $dep => $version) {
                        $installCmd .= " {$dep}@{$version}";
                    }
                }

                $this->line("   " . $installCmd);
                $this->newLine();
                $this->line("   Or use --update-package-json to automatically update package.json");
            }
        }
    }

    /**
     * Show next steps to the developer
     */
    protected function showNextSteps()
    {
        $this->info('🎉 Installation completed!');
        $this->newLine();
        $this->line('📝 Next steps:');
        $this->newLine();
        $this->line('   1. Install dependencies:');
        $this->line('      npm install');
        $this->newLine();
        $this->line('   2. Build assets:');
        $this->line('      npm run dev');
        $this->newLine();
        $this->line('   3. Open the CRUD Generator UI:');
        $this->line('      ' . url('/generator'));
        $this->newLine();
    }
}

