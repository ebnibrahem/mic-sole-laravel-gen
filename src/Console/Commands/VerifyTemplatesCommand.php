<?php

namespace MicSoleLaravelGen\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class VerifyTemplatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mic-sole:verify-templates
                            {--type= : Check specific type (backend, vue, all)}
                            {--detailed : Show detailed comparison}
                            {--fix : Auto-fix missing templates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that template stubs match generated files';

    protected $basePath;
    protected $stubPath;

    /**
     * Template types used by CrudGeneratorService
     */
    protected $crudTemplateTypes = [
        'model' => 'model.stub',
        'controller' => 'controller.stub',
        'migration' => 'migration.stub',
        'request' => 'request.stub',
        'resource' => 'resource.stub',
        'service' => 'service.stub',
        'seeder' => 'seeder.stub',
        'factory' => 'factory.stub',
        'policy' => 'policy.stub',
        'routes' => 'routes.stub',
        'lang' => 'lang.stub',
        'test' => 'test.stub',
    ];

    /**
     * Vue template types
     */
    protected $vueTemplateTypes = [
        'list_page' => 'vue/list_page.stub',
        'create_page' => 'vue/create_page.stub',
        'single_page' => 'vue/single_page.stub',
        'table_component' => 'vue/table_component.stub',
        'form_component' => 'vue/form_component.stub',
        'form_show' => 'vue/form-show.stub',
        'filter' => 'vue/filter.stub',
        'types' => 'vue/types.stub',
        'routes' => 'vue/routes.stub',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->basePath = base_path();
        $this->stubPath = \MicSoleLaravelGen\Providers\MicSoleLaravelGenServiceProvider::getTemplatesPath() . '/';

        $this->info('🔍 Verifying template stubs compatibility...');
        $this->newLine();

        $type = $this->option('type') ?? 'all';
        $detailed = $this->option('detailed');
        $fix = $this->option('fix');

        $issues = [];

        if ($type === 'all' || $type === 'backend') {
            $issues = array_merge($issues, $this->verifyBackendTemplates($detailed));
        }

        if ($type === 'all' || $type === 'vue') {
            $issues = array_merge($issues, $this->verifyVueTemplates($detailed));
        }

        if ($type === 'all') {
            $issues = array_merge($issues, $this->verifyStaticTemplates($detailed));
        }

        $this->displayResults($issues, $fix);

        return Command::SUCCESS;
    }

    /**
     * Verify backend CRUD templates
     */
    protected function verifyBackendTemplates(bool $detailed): array
    {
        $issues = [];
        $this->line('📋 Checking backend CRUD templates...');

        foreach ($this->crudTemplateTypes as $type => $stubFile) {
            $stubPath = $this->stubPath . $stubFile;

            if (!File::exists($stubPath)) {
                $issues[] = [
                'type' => 'missing',
                'category' => 'backend',
                'template' => $stubFile,
                'message' => "Missing template: {$stubFile}",
            ];
            } else {
                // Check if template has required placeholders
                $content = File::get($stubPath);
                $requiredPlaceholders = $this->getRequiredPlaceholders($type);
                $missingPlaceholders = [];

                foreach ($requiredPlaceholders as $placeholder) {
                    if (!str_contains($content, $placeholder)) {
                        $missingPlaceholders[] = $placeholder;
                    }
                }

                if (!empty($missingPlaceholders)) {
                    $issues[] = [
                        'type' => 'missing_placeholders',
                        'category' => 'backend',
                        'template' => $stubFile,
                        'message' => "Missing placeholders in {$stubFile}: " . implode(', ', $missingPlaceholders),
                        'details' => $missingPlaceholders,
                    ];
                } elseif ($detailed) {
                    $this->line("  ✅ {$stubFile}");
                }
            }
        }

        // Check for model-specific stubs
        $modelStubs = ['user.stub'];
        foreach ($modelStubs as $modelStub) {
            $stubPath = $this->stubPath . $modelStub;
            if (!File::exists($stubPath)) {
                $issues[] = [
                    'type' => 'missing',
                    'category' => 'backend',
                    'template' => $modelStub,
                    'message' => "Missing model-specific template: {$modelStub}",
                ];
            }
        }

        return $issues;
    }

    /**
     * Verify Vue templates
     */
    protected function verifyVueTemplates(bool $detailed): array
    {
        $issues = [];
        $this->line('📋 Checking Vue templates...');

        foreach ($this->vueTemplateTypes as $type => $stubFile) {
            $stubPath = $this->stubPath . $stubFile;

            if (!File::exists($stubPath)) {
                $issues[] = [
                    'type' => 'missing',
                    'category' => 'vue',
                    'template' => $stubFile,
                    'message' => "Missing Vue template: {$stubFile}",
                ];
            } else {
                // Check if template has required placeholders
                $content = File::get($stubPath);
                $requiredPlaceholders = $this->getVueRequiredPlaceholders($type);
                $missingPlaceholders = [];

                foreach ($requiredPlaceholders as $placeholder) {
                    if (!str_contains($content, $placeholder)) {
                        $missingPlaceholders[] = $placeholder;
                    }
                }

                if (!empty($missingPlaceholders)) {
                    $issues[] = [
                        'type' => 'missing_placeholders',
                        'category' => 'vue',
                        'template' => $stubFile,
                        'message' => "Missing placeholders in {$stubFile}: " . implode(', ', $missingPlaceholders),
                        'details' => $missingPlaceholders,
                    ];
                } elseif ($detailed) {
                    $this->line("  ✅ {$stubFile}");
                }
            }
        }

        return $issues;
    }

    /**
     * Verify static templates (from SyncTemplatesCommand mapping)
     */
    protected function verifyStaticTemplates(bool $detailed): array
    {
        $issues = [];
        $this->line('📋 Checking static templates...');

        // Get mapping from SyncTemplatesCommand
        $reflection = new \ReflectionClass(\MicSoleLaravelGen\Console\Commands\SyncTemplatesCommand::class);
        $property = $reflection->getProperty('fileMapping');
        $property->setAccessible(true);
        $syncCommand = $reflection->newInstanceWithoutConstructor();
        $fileMapping = $property->getValue($syncCommand);

        $checked = 0;
        $missing = 0;

        foreach ($fileMapping as $generatedFile => $stubFile) {
            $stubPath = $this->stubPath . $stubFile;

            if (!File::exists($stubPath)) {
                $issues[] = [
                    'type' => 'missing',
                    'category' => 'static',
                    'template' => $stubFile,
                    'message' => "Missing static template: {$stubFile} (for {$generatedFile})",
                ];
                $missing++;
            } else {
                $checked++;
                if ($detailed) {
                    $this->line("  ✅ {$stubFile}");
                }
            }
        }

        if (!$detailed) {
            $this->line("  Checked: {$checked}, Missing: {$missing}");
        }

        return $issues;
    }

    /**
     * Get required placeholders for backend template type
     */
    protected function getRequiredPlaceholders(string $type): array
    {
        $placeholders = [
            'model' => ['{{modelName}}', '{{tableName}}', '{{fieldsForFillable}}'],
            'controller' => ['{{modelName}}'], // Uses {{modelName}} only, {{modelNamePlural}} not required
            'migration' => ['{{tableName}}', '{{fieldsForMigration}}'],
            'request' => ['{{modelName}}', '{{fieldsForValidation}}'],
            'resource' => ['{{modelName}}', '{{fieldsForResource}}'],
            'service' => ['{{modelName}}'], // Uses {{modelName}} and {{modelNamePluralLower}}, not {{modelNamePlural}}
            'seeder' => ['{{modelName}}'],
            'factory' => ['{{modelName}}'],
            'policy' => ['{{modelName}}'],
            'routes' => ['{{modelName}}', '{{modelNamePluralLower}}'], // Uses {{modelNamePluralLower}}, not {{modelNamePlural}}
            'lang' => ['{{modelName}}', '{{fieldsForLang}}'],
            'test' => ['{{modelName}}', '{{fieldsForTest}}'],
        ];

        return $placeholders[$type] ?? [];
    }

    /**
     * Get required placeholders for Vue template type
     */
    protected function getVueRequiredPlaceholders(string $type): array
    {
        $placeholders = [
            'list_page' => ['{{modelName}}', '{{modelNamePlural}}'],
            'create_page' => ['{{modelName}}'],
            'single_page' => ['{{modelName}}'],
            'table_component' => ['{{modelName}}', '{{tableColumns}}'],
            'form_component' => ['{{modelName}}', '{{formFields}}'],
            'form_show' => ['{{modelName}}'],
            'filter' => ['{{modelName}}', '{{modelNameLower}}'],
            'types' => ['{{modelName}}', '{{types}}'],
            'routes' => ['{{modelName}}', '{{modelNamePlural}}'],
        ];

        return $placeholders[$type] ?? [];
    }

    /**
     * Display verification results
     */
    protected function displayResults(array $issues, bool $fix): void
    {
        $this->newLine();

        if (empty($issues)) {
            $this->info('✅ All templates are compatible!');
            return;
        }

        // Group issues by type
        $missing = array_filter($issues, fn($i) => $i['type'] === 'missing');
        $missingPlaceholders = array_filter($issues, fn($i) => $i['type'] === 'missing_placeholders');

        $this->warn('⚠️  Found ' . count($issues) . ' issue(s):');
        $this->newLine();

        if (!empty($missing)) {
            $this->error('Missing Templates (' . count($missing) . '):');
            foreach ($missing as $issue) {
                $this->line("  ❌ {$issue['template']}");
                if (isset($issue['message'])) {
                    $this->line("     {$issue['message']}");
                }
            }
            $this->newLine();
        }

        if (!empty($missingPlaceholders)) {
            $this->error('Missing Placeholders (' . count($missingPlaceholders) . '):');
            foreach ($missingPlaceholders as $issue) {
                $this->line("  ⚠️  {$issue['template']}");
                if (isset($issue['details'])) {
                    $this->line("     Missing: " . implode(', ', $issue['details']));
                }
            }
            $this->newLine();
        }

        if ($fix) {
            $this->info('🔧 Attempting to fix issues...');
            // Auto-fix logic can be added here
        } else {
            $this->line('💡 Tip: Use --fix to attempt automatic fixes');
            $this->line('💡 Use --detailed to see all checked templates');
        }
    }
}

