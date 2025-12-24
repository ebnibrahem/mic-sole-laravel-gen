<?php

namespace MicSoleLaravelGen\Services;

use Illuminate\Support\Str;
use MicSoleLaravelGen\Services\FileTrackerService;

class CrudGeneratorService
{
    protected $stubPath;
    protected $tracker;
    protected $outputBasePath; // المسار الأساسي للتوليد (افتراضي: base_path())
    protected $outputPaths = [
        'model'      => 'app/Models/',
        'controller' => 'app/Http/Controllers/',
        'migration'  => 'database/migrations/',
        'request'    => 'app/Http/Requests/',
        'resource'   => 'app/Http/Resources/',
        'service'    => 'app/Services/',
        'seeder'     => 'database/seeders/',
        'factory'    => 'database/factories/',
        'policy'     => 'app/Policies/',
        'routes'     => 'routes/api/dashboard/',
        'lang'       => 'resources/lang/ar/',
        'test'       => 'tests/Feature/',
        'test_crud_api' => 'tests/Feature/',

        // Vue files paths
        'vue_list_page'   => 'resources/ts/_dashboard/pages/',
        'vue_page'        => 'resources/ts/_dashboard/pages/',
        'vue_table_component' => 'resources/ts/_dashboard/pages/',
        'vue_table'       => 'resources/ts/_dashboard/pages/',
        'vue_form_component'  => 'resources/ts/_dashboard/pages/',
        'vue_form'        => 'resources/ts/_dashboard/pages/',
        'vue_single_page' => 'resources/ts/_dashboard/pages/',
        'vue_form_show'   => 'resources/ts/_dashboard/pages/',
        'vue_filter'      => 'resources/ts/_dashboard/pages/',
        'vue_create_page' => 'resources/ts/_dashboard/pages/',
        'vue_types'       => 'resources/ts/shared/types/',
            'vue_routes'      => 'resources/ts/_dashboard/router/raws/',
    ];

    public function __construct()
    {
        $this->stubPath = \MicSoleLaravelGen\Providers\MicSoleLaravelGenServiceProvider::getTemplatesPath() . '/';
        $this->tracker = new FileTrackerService();
    }

    public function generate(array $data)
    {
        $model = $data['model'];
        $fields = $data['fields'];
        $relationships = $data['relationships'] ?? [];
        $backendFiles = $data['backendFiles'] ?? [];
        $vueFiles = $data['vueFiles'] ?? [];
        $options = $data['options'] ?? [];
        $force = $data['force'] ?? false;

        // تحديد المسار الأساسي (الافتراضي: مسار الحزمة)
        $this->outputBasePath = $data['outputBasePath'] ?? base_path();

        $generated = [];
        $written = [];

        // Track generated files first (to create generation ID)
        $trackedFiles = [];
        $basePath = $this->outputBasePath ?? base_path();

        // Generate backend files
        $apiRoutesPath = null; // حفظ مسار API routes بشكل منفصل
        foreach ($backendFiles as $type) {
            $result = $this->generateFile($type, $model, $fields, $relationships, $force);
            $generated[$type] = $result['code'];
            $written[$type] = $result['path'];

            if ($result['path']) {
                // حفظ مسار API routes إذا كان نوع routes
                if ($type === 'routes' && str_contains($result['path'], '/api/dashboard/') && str_ends_with($result['path'], '.php')) {
                    $apiRoutesPath = $result['path'];
                }

                $trackedFiles[] = [
                    'type' => $type,
                    'path' => $result['path'],
                    'relative_path' => str_replace($basePath . '/', '', $result['path']),
                ];
            }
        }

        // إضافة صلاحيات النموذج المولد إلى PermissionSeeder تلقائياً (فقط إذا كان في نفس المشروع)
        if ($this->outputBasePath === base_path()) {
            $this->addPermissionsToSeeder($model);
        }

        // Generate Vue files if requested
        $vueRoutesPath = null; // حفظ مسار Vue routes بشكل منفصل
        if (!empty($vueFiles)) {
            foreach ($vueFiles as $type) {
                $result = $this->generateVueFile($type, $model, $fields, $options, $force);
                $generated[$type] = $result['code'];
                $written[$type] = $result['path'];

                if ($result['path']) {
                    // حفظ مسار Vue routes إذا كان نوع routes
                    if ($type === 'routes' && str_contains($result['path'], '/router/raws/') && str_ends_with($result['path'], '.ts')) {
                        $vueRoutesPath = $result['path'];
                    }

                    $trackedFiles[] = [
                        'type' => $type,
                        'path' => $result['path'],
                        'relative_path' => str_replace($basePath . '/', '', $result['path']),
                    ];
                }
            }
        }

        // Track generation (creates generation ID and returns it)
        $generationId = $this->tracker->track([
            'model' => $model,
            'command' => 'generate',
            'files' => $trackedFiles,
            'metadata' => [
                'backend_files' => $backendFiles,
                'vue_files' => $vueFiles,
                'fields_count' => count($fields),
                'relationships_count' => count($relationships),
                'options' => $options,
                'with_example' => $data['with_example'] ?? false,
            ],
        ]);

        // إضافة ملف API routes إلى api.php تلقائياً (بعد إنشاء الجيل)
        if ($apiRoutesPath && $this->outputBasePath === base_path() && $generationId) {
            $this->addRouteFileToApi($apiRoutesPath, $generationId);
        }

        // إضافة ملف Vue routes إلى routes.ts تلقائياً (بعد إنشاء الجيل)
        if ($vueRoutesPath && $this->outputBasePath === base_path() && $generationId) {
            $this->addVueRouteToRoutes($vueRoutesPath, $model, $generationId);
        }

        return [
            'status' => 'success',
            'message' => 'Files generated and written successfully',
            'files' => $generated,
            'written' => $written,
            'generation_id' => $this->tracker->getCurrent()['id'] ?? null,
        ];
    }

    protected function generateFile($type, $model, $fields, $relationships = [], $force = false)
    {
        // Add relationships automatically for Role, Permission, and User models
        if ($type === 'model') {
            if ($model === 'Role') {
                $relationships = array_merge($relationships, [
                    [
                        'name' => 'permissions',
                        'type' => 'belongsToMany',
                        'relatedModel' => 'Permission',
                        'pivotTable' => 'permission_role',
                    ],
                    [
                        'name' => 'users',
                        'type' => 'belongsToMany',
                        'relatedModel' => 'User',
                        'pivotTable' => 'role_user',
                    ],
                ]);
            } elseif ($model === 'Permission') {
                $relationships = array_merge($relationships, [
                    [
                        'name' => 'roles',
                        'type' => 'belongsToMany',
                        'relatedModel' => 'Role',
                        'pivotTable' => 'permission_role',
                    ],
                ]);
            } elseif ($model === 'User') {
                $relationships = array_merge($relationships, [
                    [
                        'name' => 'roles',
                        'type' => 'belongsToMany',
                        'relatedModel' => 'Role',
                        'pivotTable' => 'role_user',
                    ],
                    [
                        'name' => 'permissions',
                        'type' => 'belongsToMany',
                        'relatedModel' => 'Permission',
                        'pivotTable' => 'permission_user',
                    ],
                ]);
            }
        }

        // Check for model-specific request stub FIRST (e.g., requests/UserRequest.stub, requests/RoleRequest.stub, requests/PermissionRequest.stub)
        // This must be checked before the default request.stub to prioritize specific templates
        if ($type === 'request') {
            $modelSpecificRequestStub = $this->stubPath . 'requests/' . $model . 'Request.stub';
            if (file_exists($modelSpecificRequestStub)) {
                $stubFile = $modelSpecificRequestStub;
            } else {
                // Fallback to default request.stub
                $stubFile = $this->stubPath . $type . '.stub';
            }
        } else {
            $stubFile = $this->stubPath . $type . '.stub';
        }

        // Check for model-specific stub in models/ directory (e.g., models/RoleModel.stub)
        // Exclude application.stub as it's a translation file, not a model stub
        if ($type === 'model') {
            $modelSpecificStub = $this->stubPath . 'models/' . $model . 'Model.stub';
            if (file_exists($modelSpecificStub) && strtolower($model) !== 'application') {
                $stubFile = $modelSpecificStub;
            } else {
                // Fallback to lowercase model name in root (e.g., role.stub)
                $fallbackStub = $this->stubPath . strtolower($model) . '.stub';
                if (file_exists($fallbackStub) && strtolower($model) !== 'application') {
                    $stubFile = $fallbackStub;
                }
            }
        }

        // Check for model-specific resource stub (e.g., resources/UserResource.stub for User model)
        if ($type === 'resource') {
            $modelSpecificResourceStub = $this->stubPath . 'resources/' . $model . 'Resource.stub';
            if (file_exists($modelSpecificResourceStub)) {
                $stubFile = $modelSpecificResourceStub;
            }
        }

        // Check for model-specific lang stub (e.g., lang/user.stub for User model)
        if ($type === 'lang') {
            $modelSpecificLangStub = $this->stubPath . 'lang/' . strtolower($model) . '.stub';
            if (file_exists($modelSpecificLangStub)) {
                $stubFile = $modelSpecificLangStub;
            }
        }

        // Check for model-specific migration stub (e.g., migrations/RoleMigration.stub for Role model)
        if ($type === 'migration') {
            $modelSpecificMigrationStub = $this->stubPath . 'migrations/' . $model . 'Migration.stub';
            if (file_exists($modelSpecificMigrationStub)) {
                $stubFile = $modelSpecificMigrationStub;
            }
        }

        // Check for model-specific seeder stub (e.g., RoleSeeder.stub, PermissionSeeder.stub, UserSeeder.stub)
        if ($type === 'seeder') {
            $modelSpecificSeederStub = $this->stubPath . $model . 'Seeder.stub';
            if (file_exists($modelSpecificSeederStub)) {
                $stubFile = $modelSpecificSeederStub;
            }
        }

        // Check for model-specific service stub (e.g., services/UserService.stub, services/RoleService.stub, services/PermissionService.stub)
        if ($type === 'service') {
            $modelSpecificServiceStub = $this->stubPath . 'services/' . $model . 'Service.stub';
            if (file_exists($modelSpecificServiceStub)) {
                $stubFile = $modelSpecificServiceStub;
            }
        }

        // Check for test_crud_api stub
        if ($type === 'test_crud_api') {
            $stubFile = $this->stubPath . 'test_crud_api.stub';
        }

        if (file_exists($stubFile)) {
            $stub = file_get_contents($stubFile);

            // Check if this is a static template (in models/, resources/, services/, migrations/, etc.)
            // Static templates don't need placeholder replacement - copy directly
            // Normalize path separators for cross-platform compatibility
            $normalizedStubFile = str_replace('\\', '/', $stubFile);
            $isStaticTemplate = str_contains($normalizedStubFile, '/models/') ||
                               str_contains($normalizedStubFile, '/resources/') ||
                               str_contains($normalizedStubFile, '/services/') ||
                               str_contains($normalizedStubFile, '/controllers/') ||
                               str_contains($normalizedStubFile, '/requests/') ||
                               str_contains($normalizedStubFile, '/policies/') ||
                               str_contains($normalizedStubFile, '/migrations/') ||
                               (str_contains($normalizedStubFile, 'Seeder.stub') &&
                                (str_contains($normalizedStubFile, 'RoleSeeder') ||
                                 str_contains($normalizedStubFile, 'PermissionSeeder') ||
                                 str_contains($normalizedStubFile, 'UserSeeder')));

            if ($isStaticTemplate) {
                // Static template - copy directly without processing
                $code = $stub;
            } else {
                // Dynamic template - process placeholders
                $code = $this->replacePlaceholders($stub, $model, $fields, $relationships, $type);
            }

            // For User model, add authentication support (only for dynamic templates)
            if ($type === 'model' && $model === 'User' && !$isStaticTemplate) {
                $code = $this->addAuthenticationSupport($code);
            }

            $filename = $this->getFilename($type, $model);
            $basePath = $this->outputBasePath ?? base_path();
            $outputDir = $basePath . '/' . ($this->outputPaths[$type] ?? 'generated/');
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0777, true);
            }

            // For migration files, check for existing migrations with the same table name and delete them
            if ($type === 'migration') {
                $tableName = strtolower(Str::plural(Str::snake($model)));
                $migrationPattern = '*_create_' . $tableName . '_table.php';
                $existingMigrations = glob($outputDir . $migrationPattern);

                if (!empty($existingMigrations)) {
                    foreach ($existingMigrations as $existingMigration) {
                        if (file_exists($existingMigration)) {
                            unlink($existingMigration);
                        }
                    }
                }
            }

            $outputPath = $outputDir . $filename;

            // Skip if file exists and not forcing
            if (file_exists($outputPath) && !$force) {
                return ['code' => null, 'path' => $outputPath];
            }

            file_put_contents($outputPath, $code);

            return ['code' => $code, 'path' => $outputPath];
        }

        return ['code' => "// Stub for $type not found.", 'path' => null];
    }

    /**
     * Add authentication support to User model
     */
    protected function addAuthenticationSupport(string $code): string
    {
        // Check if already has Authenticatable
        if (str_contains($code, 'Authenticatable') || str_contains($code, 'Notifiable')) {
            // Ensure HasApiTokens is imported and used
            if (!str_contains($code, 'HasApiTokens')) {
                // Add HasApiTokens import
                if (!str_contains($code, 'use Laravel\\Sanctum\\HasApiTokens;')) {
                    $code = preg_replace(
                        '/(use Illuminate\\Notifications\\Notifiable;)/',
                        "$1\nuse Laravel\\Sanctum\\HasApiTokens;",
                        $code
                    );
                }

                // Add HasApiTokens to traits
                if (str_contains($code, 'use HasFactory, Notifiable;')) {
                    $code = str_replace(
                        'use HasFactory, Notifiable;',
                        'use HasFactory, Notifiable, HasApiTokens;',
                        $code
                    );
                } elseif (str_contains($code, 'use HasFactory, Notifiable')) {
                    $code = preg_replace(
                        '/(use HasFactory, Notifiable)/',
                        "$1, HasApiTokens",
                        $code
                    );
                }
            }

            // Ensure fillable has authentication fields
            if (preg_match('/protected \$fillable = \[([^\]]*)\];/s', $code, $matches)) {
                $fillableContent = $matches[1];
                $requiredFields = ['name', 'email', 'password', 'is_active'];
                $existingFields = [];

                // Extract existing fields
                if (preg_match_all("/['\"]([^'\"]+)['\"]/", $fillableContent, $fieldMatches)) {
                    $existingFields = $fieldMatches[1];
                }

                // Add missing fields
                $allFields = array_unique(array_merge($existingFields, $requiredFields));
                $newFillable = "        " . implode(",\n        ", array_map(fn($f) => "'{$f}'", $allFields)) . ",\n    ";

                $code = preg_replace(
                    '/protected \$fillable = \[[^\]]*\];/s',
                    "protected \$fillable = [\n{$newFillable}];",
                    $code
                );
            }
            return $code;
        }

        // Add imports
        $imports = "use Illuminate\Foundation\Auth\User as Authenticatable;\nuse Illuminate\Notifications\Notifiable;\nuse Laravel\Sanctum\HasApiTokens;";

        // Add imports after namespace
        $code = preg_replace(
            '/(namespace App\\Models;)/',
            "$1\n\n" . $imports,
            $code
        );

        // Change extends Model to extends Authenticatable
        $code = preg_replace(
            '/extends Model/',
            'extends Authenticatable',
            $code
        );

        // Add Notifiable and HasApiTokens traits
        $code = preg_replace(
            '/(use HasFactory;)/',
            "$1, Notifiable, HasApiTokens",
            $code
        );

        // Add authentication fields to fillable
        if (preg_match('/protected \$fillable = \[([^\]]*)\];/s', $code, $matches)) {
            $fillableContent = $matches[1];
            $requiredFields = ['name', 'email', 'password', 'is_active'];
            $existingFields = [];

            // Extract existing fields
            if (preg_match_all("/['\"]([^'\"]+)['\"]/", $fillableContent, $fieldMatches)) {
                $existingFields = $fieldMatches[1];
            }

            // Add missing fields
            $allFields = array_unique(array_merge($existingFields, $requiredFields));
            $newFillable = "        " . implode(",\n        ", array_map(fn($f) => "'{$f}'", $allFields)) . ",\n    ";

            $code = preg_replace(
                '/protected \$fillable = \[[^\]]*\];/s',
                "protected \$fillable = [\n{$newFillable}];",
                $code
            );
        }

        // Add hidden attributes
        if (!str_contains($code, 'protected $hidden')) {
            $hiddenAttributes = <<<'PHP'

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
PHP;
            // Add before relationships comment
            $code = preg_replace(
                '/(\s+\/\*\*\s*\n\s+\* Relationships)/',
                $hiddenAttributes . "\n\n$1",
                $code
            );
        }

        // Add casts method for password hashing
        if (!str_contains($code, 'protected function casts()')) {
            $castsMethod = <<<'PHP'

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
PHP;
            // Add before relationships comment
            $code = preg_replace(
                '/(\s+\/\*\*\s*\n\s+\* Relationships)/',
                $castsMethod . "\n\n$1",
                $code
            );
        }

        return $code;
    }

    protected function generateCommonFile()
    {
        $stubFile = $this->stubPath . 'common.stub';
        if (file_exists($stubFile)) {
            $stub = file_get_contents($stubFile);
            // No placeholders needed for common file
            $code = $stub;

            $filename = 'common.php';
            $basePath = $this->outputBasePath ?? base_path();
            $outputDir = $basePath . '/' . ($this->outputPaths['common'] ?? 'resources/lang/en/');
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0777, true);
            }
            $outputPath = $outputDir . $filename;
            file_put_contents($outputPath, $code);

            return ['code' => $code, 'path' => $outputPath];
        }

        return ['code' => "// Common stub not found.", 'path' => null];
    }

    protected function generateVueFile($type, $model, $fields, $options, $force = false)
    {
        $stubFile = $this->stubPath . 'vue/' . $type . '.stub';
        if (file_exists($stubFile)) {
            $stub = file_get_contents($stubFile);
            $code = $this->replaceVuePlaceholders($stub, $model, $fields, $options);

            $filename = $this->getVueFilename($type, $model);

            // Determine output directory based on type
            $outputKey = 'vue_' . $type;
            if (!isset($this->outputPaths[$outputKey])) {
                // Fallback for legacy types
                if ($type === 'page') $outputKey = 'vue_list_page';
                elseif ($type === 'table') $outputKey = 'vue_table_component';
                elseif ($type === 'form') $outputKey = 'vue_form_component';
            }

            $basePath = $this->outputBasePath ?? base_path();
            $baseDir = $basePath . '/' . ($this->outputPaths[$outputKey] ?? 'resources/js/generated/');

            // For components, create subdirectory with model name
            if (in_array($type, ['table_component', 'form_component', 'form_show', 'filter', 'table', 'form'])) {
                $modelDir = Str::kebab(Str::plural($model));
                $outputDir = $baseDir . '_' . $modelDir . '/';
            } elseif ($type === 'types') {
                // Types go directly to shared/types/ directory
                $outputDir = $baseDir;
            } else {
                $outputDir = $baseDir;
            }

            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0777, true);
            }
            $outputPath = $outputDir . $filename;

            // For types, add import for BaseModel from responses.ts
            if ($type === 'types') {
                $code = "import type { BaseModel } from './responses';\n\n" . $code;
                // Also add export to responses.ts if it exists
                $responsesPath = $baseDir . 'responses.ts';
                if (file_exists($responsesPath)) {
                    $responsesContent = file_get_contents($responsesPath);
                    $modelLower = strtolower($model);
                    $exportLine = "export type { {$model} } from './{$modelLower}';\n";
                    // Add export if not already present
                    if (!str_contains($responsesContent, "export type { {$model} }") &&
                        !str_contains($responsesContent, "export interface {$model}")) {
                        // Find the comment "// Export model types" and add after it
                        if (str_contains($responsesContent, "// Export model types")) {
                            $responsesContent = preg_replace(
                                '/(\/\/ Export model types.*?\n)/s',
                                "$1" . $exportLine,
                                $responsesContent
                            );
                        } else {
                            // Add at the end before last closing brace
                            $responsesContent = preg_replace(
                                '/(\n})$/',
                                "\n" . $exportLine . "$1",
                                $responsesContent
                            );
                        }
                        file_put_contents($responsesPath, $responsesContent);
                    }
                }
            }

            // For form components, add TipTapEditor import if richtext fields exist
            if (in_array($type, ['form_component', 'form']) && $this->hasRichtextField($fields)) {
                if (!str_contains($code, "import TipTapEditor from '@shared/components/TipTapEditor.vue'")) {
                    // Add import after other imports
                    $code = preg_replace(
                        '/(import type \{ .*? \} from .*?;\n)/',
                        "$1import TipTapEditor from '@shared/components/TipTapEditor.vue';\n",
                        $code,
                        1
                    );
                }
            }

            // Skip if file exists and not forcing
            if (file_exists($outputPath) && !$force) {
                return ['code' => null, 'path' => $outputPath];
            }

            file_put_contents($outputPath, $code);

            return ['code' => $code, 'path' => $outputPath];
        }

        return ['code' => "// Vue stub for $type not found.", 'path' => null];
    }

    protected function replacePlaceholders($stub, $model, $fields, $relationships = [], $type = null)
    {
        $stub = str_replace('{{modelName}}', $model, $stub);
        $stub = str_replace('{{modelNamePlural}}', Str::plural($model), $stub);
        $stub = str_replace('{{modelNameSnake}}', Str::snake($model), $stub);
        $stub = str_replace('{{modelNameCamel}}', Str::camel($model), $stub);
        $stub = str_replace('{{modelNameKebab}}', Str::kebab($model), $stub);
        $stub = str_replace('{{modelNameLower}}', strtolower($model), $stub);
        $stub = str_replace('{{modelNamePluralLower}}', strtolower(Str::plural($model)), $stub);
        $stub = str_replace('{{tableName}}', strtolower(Str::plural(Str::snake($model))), $stub);
        $stub = str_replace('{{fields}}', $this->fieldsToString($fields), $stub);
        // Note: fieldsForValidation and fieldsForMigration will be replaced later with relationships
        $stub = str_replace('{{fieldsForResource}}', $this->fieldsToResource($fields, $relationships), $stub);
        $stub = str_replace('{{fieldsForFillable}}', $this->fieldsToFillable($fields), $stub);
        $stub = str_replace('{{fieldsForCasts}}', $this->fieldsToCasts($fields), $stub);
        $stub = str_replace('{{fieldsForLang}}', $this->fieldsToLang($fields), $stub);
        $stub = str_replace('{{fieldsForSearch}}', $this->fieldsToSearchable($fields), $stub);

        // Test-specific placeholders
        $stub = str_replace('{{fieldsForTest}}', $this->fieldsToTestData($fields), $stub);
        $stub = str_replace('{{fieldsForTestAssert}}', $this->fieldsToTestAssert($fields), $stub);
        $stub = str_replace('{{fieldsForTestDatabase}}', $this->fieldsToTestDatabase($fields), $stub);
        $stub = str_replace('{{fieldsForTableAssert}}', $this->fieldsToTableAssert($fields), $stub);
        $stub = str_replace('{{fieldsForTestUpdate}}', $this->fieldsToTestUpdate($fields), $stub);
        $stub = str_replace('{{fieldsForTestUpdateDatabase}}', $this->fieldsToTestUpdateDatabase($fields), $stub);
        $stub = str_replace('{{testWithRelationships}}', $this->generateTestWithRelationships($relationships, $model, $fields), $stub);

        // Handle relationships - add imports and methods
        $relationshipData = $this->relationshipsToModelMethods($relationships, $model);
        $stub = str_replace('{{relationships}}', $relationshipData['methods'], $stub);
        $stub = str_replace('{{relationshipImports}}', $relationshipData['imports'], $stub);

        // Add relationship imports for test file (only for belongsTo relationships)
        if ($type === 'test_crud_api') {
            $testRelationshipImports = $this->generateRelationshipImportsForTest($relationships);
            $stub = str_replace('{{relationshipImports}}', $testRelationshipImports, $stub);
        }
        $stub = str_replace('{{relationshipForeignKeys}}', $this->relationshipsToFillable($relationships), $stub);

        // Add relationship foreign keys to migration
        if ($type === 'migration') {
            $stub = str_replace('{{fieldsForMigration}}', $this->fieldsToMigration($fields, $relationships), $stub);
        } else {
            $stub = str_replace('{{fieldsForMigration}}', $this->fieldsToMigration($fields), $stub);
        }

        // Add relationship foreign keys to validation
        $validationRules = $this->fieldsToValidationRules($fields, $model, $relationships);
        $stub = str_replace('{{fieldsForValidation}}', $validationRules, $stub);

        // Replace placeholders in validation rules (for unique rules with tableName and modelNameLower)
        $stub = str_replace('{{tableName}}', strtolower(Str::plural(Str::snake($model))), $stub);
        $stub = str_replace('{{modelNameLower}}', strtolower($model), $stub);

        return $stub;
    }

    /**
     * Add User seeder content with test credentials
     */
    protected function addUserSeederContent($code)
    {
        // Replace the foreach loop with test users
        $userSeederContent = <<<'PHP'
        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@app.com'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'email' => 'admin@app.com',
                'password' => Hash::make('password'),
                'phone' => null,
                'clan' => 'user',
                'bio' => null,
                'address' => null,
                'image' => null,
                'is_active' => true,
            ]
        );

        // Create test user
        User::firstOrCreate(
            ['email' => 'test@app.com'],
            [
                'name' => 'Test User',
                'username' => 'testuser',
                'email' => 'test@app.com',
                'password' => Hash::make('password'),
                'phone' => null,
                'clan' => 'customer',
                'bio' => null,
                'address' => null,
                'image' => null,
                'is_active' => true,
            ]
        );
PHP;

        // Replace the foreach loop section
        $code = preg_replace(
            '/foreach \(range\(1, 5\) as \$i\) \{[^}]*\}/s',
            $userSeederContent,
            $code
        );

        // Add Hash import if not present
        if (strpos($code, 'use Illuminate\Support\Facades\Hash;') === false) {
            $code = str_replace(
                'use Illuminate\Database\Seeder;',
                "use Illuminate\Database\Seeder;\nuse Illuminate\Support\Facades\Hash;",
                $code
            );
        }

        return $code;
    }

    /**
     * Add User migration fields (name, email, password, is_active, and additional fields)
     */
    protected function addUserMigrationFields($code)
    {
        $userFields = <<<'PHP'

            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('clan')->default('user');
            $table->text('bio')->nullable();
            $table->text('address')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
PHP;

        // Replace empty fieldsForMigration with user fields
        $code = str_replace(
            '{{fieldsForMigration}}',
            $userFields,
            $code
        );

        // If fieldsForMigration is already replaced but empty, add after id
        if (strpos($code, '$table->id();') !== false && strpos($code, '$table->string(\'name\');') === false) {
            $code = str_replace(
                '$table->id();',
                '$table->id();' . $userFields,
                $code
            );
        }

        return $code;
    }

    /**
     * Add Role migration fields (name and title)
     */
    protected function addRoleMigrationFields($code)
    {
        $roleFields = <<<'PHP'

            $table->string('name')->unique();
            $table->string('title');
PHP;

        // Replace empty fieldsForMigration with role fields
        $code = str_replace(
            '{{fieldsForMigration}}',
            $roleFields,
            $code
        );

        // If fieldsForMigration is already replaced but empty, add after id
        if (strpos($code, '$table->id();') !== false && strpos($code, '$table->string(\'title\');') === false) {
            $code = str_replace(
                '$table->id();',
                '$table->id();' . $roleFields,
                $code
            );
        }

        return $code;
    }

    /**
     * Add Permission migration fields (name, title, is_active)
     */
    protected function addPermissionMigrationFields($code)
    {
        $permissionFields = <<<'PHP'

            $table->string('name')->unique();
            $table->string('title')->nullable();
            $table->boolean('is_active')->default(true);
PHP;

        // Replace empty fieldsForMigration with permission fields
        $code = str_replace(
            '{{fieldsForMigration}}',
            $permissionFields,
            $code
        );

        // If fieldsForMigration is already replaced but empty, add after id
        if (strpos($code, '$table->id();') !== false && strpos($code, '$table->string(\'name\');') === false) {
            $code = str_replace(
                '$table->id();',
                '$table->id();' . $permissionFields,
                $code
            );
        }

        return $code;
    }

    /**
     * Add Role seeder content with default roles
     */
    protected function addRoleSeederContent($code)
    {
        $roleSeederContent = <<<'PHP'
        // Create System Admin role
        Role::firstOrCreate(
            ['title' => 'مدير النظام'],
            [
                'title' => 'مدير النظام',
            ]
        );

        // Create Admin role
        Role::firstOrCreate(
            ['title' => 'مدير'],
            [
                'title' => 'مدير',
            ]
        );

        // Create User role
        Role::firstOrCreate(
            ['title' => 'مستخدم'],
            [
                'title' => 'مستخدم',
            ]
        );
PHP;

        // Replace the foreach loop section
        $code = preg_replace(
            '/foreach \(range\(1, 5\) as \$i\) \{[^}]*\}/s',
            $roleSeederContent,
            $code
        );

        return $code;
    }

    /**
     * Add Permission seeder content with default permissions
     */
    protected function addPermissionSeederContent($code)
    {
        $permissionSeederContent = <<<'PHP'
        // User permissions
        Permission::firstOrCreate(
            ['name' => 'user_access'],
            ['title' => 'الوصول للمستخدمين', 'is_active' => true]
        );
        Permission::firstOrCreate(
            ['name' => 'user_create'],
            ['title' => 'إنشاء مستخدم', 'is_active' => true]
        );
        Permission::firstOrCreate(
            ['name' => 'user_edit'],
            ['title' => 'تعديل مستخدم', 'is_active' => true]
        );
        Permission::firstOrCreate(
            ['name' => 'user_delete'],
            ['title' => 'حذف مستخدم', 'is_active' => true]
        );

        // Role permissions
        Permission::firstOrCreate(
            ['name' => 'role_access'],
            ['title' => 'الوصول للأدوار', 'is_active' => true]
        );
        Permission::firstOrCreate(
            ['name' => 'role_create'],
            ['title' => 'إنشاء دور', 'is_active' => true]
        );
        Permission::firstOrCreate(
            ['name' => 'role_edit'],
            ['title' => 'تعديل دور', 'is_active' => true]
        );
        Permission::firstOrCreate(
            ['name' => 'role_delete'],
            ['title' => 'حذف دور', 'is_active' => true]
        );

        // Permission permissions
        Permission::firstOrCreate(
            ['name' => 'permission_access'],
            ['title' => 'الوصول للصلاحيات', 'is_active' => true]
        );
        Permission::firstOrCreate(
            ['name' => 'permission_create'],
            ['title' => 'إنشاء صلاحية', 'is_active' => true]
        );
        Permission::firstOrCreate(
            ['name' => 'permission_edit'],
            ['title' => 'تعديل صلاحية', 'is_active' => true]
        );
        Permission::firstOrCreate(
            ['name' => 'permission_delete'],
            ['title' => 'حذف صلاحية', 'is_active' => true]
        );
PHP;

        // Replace the foreach loop section
        $code = preg_replace(
            '/foreach \(range\(1, 5\) as \$i\) \{[^}]*\}/s',
            $permissionSeederContent,
            $code
        );

        return $code;
    }

    protected function replaceVuePlaceholders($stub, $model, $fields, $options)
    {
        $stub = str_replace('{{modelName}}', $model, $stub);
        $stub = str_replace('{{modelNamePlural}}', Str::plural($model), $stub);
        $stub = str_replace('{{modelNameCamel}}', Str::camel($model), $stub);
        $stub = str_replace('{{modelNameKebab}}', Str::kebab($model), $stub);
        $stub = str_replace('{{modelNameLower}}', strtolower($model), $stub);
        $stub = str_replace('{{modelNamePluralLower}}', strtolower(Str::plural($model)), $stub);
        $stub = str_replace('{{modelNamePluralKebab}}', Str::kebab(Str::plural($model)), $stub);
        $stub = str_replace('{{fields}}', $this->fieldsToVueProps($fields), $stub);
        $stub = str_replace('{{tableColumns}}', $this->fieldsToTableColumns($fields), $stub);
        $stub = str_replace('{{formFields}}', $this->fieldsToFormFields($fields), $stub);
        $stub = str_replace('{{types}}', $this->fieldsToTypeScript($model, $fields), $stub);
        $stub = str_replace('{{formInitialValues}}', $this->fieldsToVueFormInitialValues($fields), $stub);
        $stub = str_replace('{{exportHeaders}}', $this->fieldsToExportHeaders($fields), $stub);
        $stub = str_replace('{{fieldsDisplay}}', $this->fieldsToDisplay($model, $fields), $stub);

        // Add TipTapEditor import if richtext fields exist
        $tipTapEditorImport = $this->hasRichtextField($fields)
            ? "import TipTapEditor from '@shared/components/TipTapEditor.vue';"
            : '';
        $stub = str_replace('{{tipTapEditorImport}}', $tipTapEditorImport, $stub);

        return $stub;
    }

    protected function getFilename($type, $model)
    {
        switch ($type) {
            case 'model':      return $model . '.php';
            case 'controller': return $model . 'Controller.php';
            case 'migration':  return date('Y_m_d_His') . '_create_' . strtolower(Str::plural(Str::snake($model))) . '_table.php';
            case 'request':    return $model . 'Request.php';
            case 'resource':   return $model . 'Resource.php';
            case 'service':    return $model . 'Service.php';
            case 'seeder':     return $model . 'Seeder.php';
            case 'factory':    return $model . 'Factory.php';
            case 'policy':     return $model . 'Policy.php';
            case 'routes':     return 'api-' . strtolower(Str::plural(Str::snake($model))) . '.php';
            case 'lang':       return strtolower($model) . '.php';
            case 'test':       return $model . 'Test.php';
            case 'test_crud_api': return $model . 'Test.php';
            default:           return $model . '_' . $type . '.php';
        }
    }

    protected function getVueFilename($type, $model)
    {
        switch ($type) {
            case 'list_page':
            case 'page':
                return Str::plural($model) . '.vue';
            case 'table_component':
            case 'table':
                return 'table.vue';
            case 'form_component':
            case 'form':
                return 'form.vue';
            case 'single_page':
                return $model . '.vue';
            case 'create_page':
                return $model . 'Create.vue';
            case 'form_show':
                return 'form-show.vue';
            case 'filter':
                return 'filter.vue';
            case 'types':
                return strtolower($model) . '.ts';
            case 'routes':
                return strtolower($model) . '.ts';
            default:
                return $model . '_' . $type . '.vue';
        }
    }

    // Helper methods for Vue generation
    protected function fieldsToVueProps($fields)
    {
        $props = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $type = $this->getVueType($field['type']);
            $props[] = "    {$name}: {$type}";
        }
        return implode(",\n", $props);
    }

    protected function fieldsToTableColumns($fields)
    {
        $columns = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $name));
            $sortable = isset($field['sortable']) && $field['sortable'] ? ' sortable' : '';
            $columns[] = "            <Column field=\"{$name}\" header=\"{$label}\"{$sortable}>";
            $columns[] = "                <template #header>";
            $columns[] = "                    <div class=\"flex items-center gap-2 cursor-pointer\" @click=\"handleSort('{$name}')\">";
            $columns[] = "                        <span>{$label}</span>";
            $columns[] = "                        <div class=\"flex flex-col gap-0.5\">";
            $columns[] = "                            <i";
            $columns[] = "                                v-if=\"currentSortBy === '{$name}' && currentSortOrder === 'asc'\"";
            $columns[] = "                                class=\"pi pi-arrow-up text-xs text-primary\"";
            $columns[] = "                                title=\"ترتيب تصاعدي\"";
            $columns[] = "                            ></i>";
            $columns[] = "                            <i";
            $columns[] = "                                v-else-if=\"currentSortBy === '{$name}' && currentSortOrder === 'desc'\"";
            $columns[] = "                                class=\"pi pi-arrow-down text-xs text-primary\"";
            $columns[] = "                                title=\"ترتيب تنازلي\"";
            $columns[] = "                            ></i>";
            $columns[] = "                            <i";
            $columns[] = "                                v-else";
            $columns[] = "                                class=\"pi pi-sort-alt text-xs text-gray-400 hover:text-primary transition-colors\"";
            $columns[] = "                                title=\"ترتيب\"";
            $columns[] = "                            ></i>";
            $columns[] = "                        </div>";
            $columns[] = "                    </div>";
            $columns[] = "                </template>";
            $columns[] = "                <template #body=\"{ data }\">";
            // Use v-memo to optimize rendering - only re-render when the field value changes
            $columns[] = "                    <span v-memo=\"[data.{$name}]\">{{ data.{$name} }}</span>";
            $columns[] = "                </template>";
            $columns[] = "            </Column>";
        }
        return implode("\n", $columns);
    }

    protected function fieldsToFormFields($fields)
    {
        $inputs = [];
        foreach ($fields as $field) {
            $input = $this->getVueInputForField($field);
            $inputs[] = "                " . $input;
        }
        return implode("\n", $inputs);
    }

    protected function fieldsToTypeScript($model, $fields)
    {
        $types = [];
        $types[] = "    id: number;";
        foreach ($fields as $field) {
            $types[] = "    {$field['name']}: {$this->getTsType($field['type'])};";
        }
        $types[] = "    created_at?: string;";
        $types[] = "    updated_at?: string;";
        return "export interface " . $model . " extends BaseModel {\n" . implode("\n", $types) . "\n}";
    }

    protected function getVueType($type)
    {
        // Map PHP types to TypeScript types
        $map = [
            'string' => 'string',
            'text' => 'string',
            'integer' => 'number',
            'float' => 'number',
            'boolean' => 'boolean',
            'date' => 'Date',
            'datetime' => 'Date',
            'json' => 'any'
        ];
        return $map[$type] ?? 'any';
    }

    protected function getVueInputForField($field)
    {
        $name = $field['name'];
        $label = "c('{{modelNameLower}}.fields.{$name}', '{{modelNameLower}}')";
        $required = isset($field['required']) && $field['required'] ? ' required' : '';

        // Generate placeholder using array format: c([common.enter, modelNameLower.fields.name])
        // Don't pass 'common' as dir parameter - let each key resolve to its own dir
        $placeholder = ":placeholder=\"c(['common.enter', '{{modelNameLower}}.fields.{$name}'])\"";

        switch ($field['type']) {
            case 'boolean':
                return "<Checkbox v-model=\"form.{$name}\" :label=\"{$label}\"{$required} />";
            case 'text':
                return "<Textarea v-model=\"form.{$name}\" :label=\"{$label}\"{$required} {$placeholder} rows=\"5\" />";
            case 'richtext':
                return "<div class=\"md:col-span-2\">\n                    <TipTapEditor v-model=\"form.{$name}\" :label=\"{$label}\"{$required} height=\"320px\" rtl />\n                </div>";
            case 'email':
                return "<InputText v-model=\"form.{$name}\" type=\"email\" :label=\"{$label}\"{$required} {$placeholder} />";
            case 'password':
                return "<Password v-model=\"form.{$name}\" :label=\"{$label}\"{$required} {$placeholder} toggleMask />";
            case 'number':
            case 'integer':
            case 'float':
            case 'decimal':
                return "<InputNumber v-model=\"form.{$name}\" :label=\"{$label}\"{$required} {$placeholder} />";
            case 'date':
                return "<Calendar v-model=\"form.{$name}\" :label=\"{$label}\"{$required} dateFormat=\"yy-mm-dd\" />";
            case 'datetime':
                return "<Calendar v-model=\"form.{$name}\" :label=\"{$label}\"{$required} dateFormat=\"yy-mm-dd\" showTime />";
            default:
                return "<InputText v-model=\"form.{$name}\" :label=\"{$label}\"{$required} {$placeholder} />";
        }
    }

    /**
     * Convert fields array to string representation for model stub
     */
    protected function fieldsToString($fields)
    {
        $lines = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $type = $this->getPhpType($field['type']);
            $nullable = isset($field['nullable']) && $field['nullable'] ? '?' : '';
            $lines[] = "    protected \${$name}; // {$type}";
        }
        return implode("\n", $lines);
    }

    /**
     * Convert relationships to fillable foreign keys
     */
    protected function relationshipsToFillable($relationships)
    {
        $foreignKeys = [];
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $foreignKey = $rel['foreignKey'] ?? strtolower($rel['relatedModel']) . '_id';
                $foreignKeys[] = "'{$foreignKey}'";
            }
        }
        if (empty($foreignKeys)) {
            return '';
        }
        return ",\n        " . implode(",\n        ", $foreignKeys);
    }

    /**
     * Convert fields array to Laravel validation rules
     */
    protected function fieldsToValidationRules($fields, $model = null, $relationships = [])
    {
        $rules = [];
        // Use placeholder that will be replaced later in replacePlaceholders
        $tableName = '{{tableName}}';
        $modelNameLower = '{{modelNameLower}}';

        foreach ($fields as $field) {
            $name = $field['name'];
            $rule = [];

            if (isset($field['required']) && $field['required']) {
                $rule[] = 'required';
            } else {
                $rule[] = 'nullable';
            }

            // Add type-specific rules
            switch ($field['type']) {
                case 'string':
                    $rule[] = 'string';
                    if (isset($field['max'])) {
                        $rule[] = 'max:' . $field['max'];
                    }
                    break;
                case 'text':
                    $rule[] = 'string';
                    break;
                case 'integer':
                    $rule[] = 'integer';
                    break;
                case 'float':
                case 'decimal':
                    $rule[] = 'numeric';
                    break;
                case 'boolean':
                    $rule[] = 'boolean';
                    break;
                case 'date':
                    $rule[] = 'date';
                    break;
                case 'datetime':
                    $rule[] = 'date';
                    break;
                case 'email':
                    $rule[] = 'email';
                    break;
                case 'url':
                    $rule[] = 'url';
                    break;
                case 'json':
                    $rule[] = 'json';
                    break;
            }

            if (isset($field['unique']) && $field['unique']) {
                // Use Rule::unique with ignore for update support
                // Use placeholder that will be replaced later in replacePlaceholders
                $fieldTableName = isset($field['table']) ? $field['table'] : '{{tableName}}';
                $rule[] = "Rule::unique('{$fieldTableName}', '{$name}')->ignore(\${{modelNameLower}}Id)";
            }

            $rules[] = "            '{$name}' => ['" . implode("', '", $rule) . "'],";
        }

        // Add relationship foreign keys to validation rules
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $foreignKey = $rel['foreignKey'] ?? strtolower($rel['relatedModel']) . '_id';
                $relatedTable = strtolower(Str::plural(Str::snake($rel['relatedModel'])));
                $rules[] = "            '{$foreignKey}' => ['nullable', 'integer', Rule::exists('{$relatedTable}', 'id')],";
            }
        }

        return implode("\n", $rules);
    }

    /**
     * Convert fields array to migration schema
     */
    protected function fieldsToMigration($fields, $relationships = [])
    {
        $lines = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $nullable = (isset($field['nullable']) && $field['nullable']) || (!isset($field['required']) || !$field['required']) ? '->nullable()' : '';
            $default = isset($field['default']) ? "->default('{$field['default']}')" : '';

            switch ($field['type']) {
                case 'string':
                    $length = isset($field['max']) ? $field['max'] : 255;
                    $lines[] = "            \$table->string('{$name}', {$length}){$nullable}{$default};";
                    break;
                case 'text':
                case 'richtext':
                    $lines[] = "            \$table->text('{$name}'){$nullable}{$default};";
                    break;
                case 'integer':
                    $lines[] = "            \$table->integer('{$name}'){$nullable}{$default};";
                    break;
                case 'bigInteger':
                    $lines[] = "            \$table->bigInteger('{$name}'){$nullable}{$default};";
                    break;
                case 'float':
                case 'decimal':
                    $precision = isset($field['precision']) ? $field['precision'] : 8;
                    $scale = isset($field['scale']) ? $field['scale'] : 2;
                    $lines[] = "            \$table->decimal('{$name}', {$precision}, {$scale}){$nullable}{$default};";
                    break;
                case 'boolean':
                    $lines[] = "            \$table->boolean('{$name}')->default(" . (isset($field['default']) ? ($field['default'] ? 'true' : 'false') : 'false') . ");";
                    break;
                case 'date':
                    $lines[] = "            \$table->date('{$name}'){$nullable}{$default};";
                    break;
                case 'datetime':
                    $lines[] = "            \$table->datetime('{$name}'){$nullable}{$default};";
                    break;
                case 'timestamp':
                    $lines[] = "            \$table->timestamp('{$name}'){$nullable}{$default};";
                    break;
                case 'json':
                    $lines[] = "            \$table->json('{$name}'){$nullable}{$default};";
                    break;
                default:
                    $lines[] = "            \$table->string('{$name}'){$nullable}{$default};";
            }
        }

        // Add relationship foreign keys to migration
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $foreignKey = $rel['foreignKey'] ?? strtolower($rel['relatedModel']) . '_id';
                $relatedTable = strtolower(Str::plural(Str::snake($rel['relatedModel'])));
                $lines[] = "            \$table->foreignId('{$foreignKey}')->nullable()->constrained('{$relatedTable}')->onDelete('set null');";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Convert fields array to resource array
     */
    protected function fieldsToResource($fields, $relationships = [])
    {
        $lines = [];
        // Always include id first
        $lines[] = "            'id' => \$this->id,";

        foreach ($fields as $field) {
            $name = $field['name'];
            $lines[] = "            '{$name}' => \$this->{$name},";
        }

        // Add relationship foreign keys and relationships
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $foreignKey = $rel['foreignKey'] ?? strtolower($rel['relatedModel']) . '_id';
                $name = $rel['name'];
                $lines[] = "            '{$foreignKey}' => \$this->{$foreignKey},";
                $lines[] = "            '{$name}' => \$this->whenLoaded('{$name}'),";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get PHP type from field type
     */
    protected function getPhpType($type)
    {
        $map = [
            'string' => 'string',
            'text' => 'string',
            'integer' => 'int',
            'bigInteger' => 'int',
            'float' => 'float',
            'decimal' => 'float',
            'boolean' => 'bool',
            'date' => '\Carbon\Carbon',
            'datetime' => '\Carbon\Carbon',
            'timestamp' => '\Carbon\Carbon',
            'json' => 'array',
        ];
        return $map[$type] ?? 'mixed';
    }

    /**
     * Convert fields to Vue form initial values
     */
    protected function fieldsToVueFormInitialValues($fields)
    {
        $values = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $default = isset($field['default']) ? $field['default'] : $this->getDefaultValueForType($field['type']);
            if ($default !== null) {
                $values[] = "        {$name}: " . (is_string($default) ? "'{$default}'" : ($default === true ? 'true' : ($default === false ? 'false' : $default)));
            } else {
                $values[] = "        {$name}: null";
            }
        }
        return implode(",\n", $values);
    }

    /**
     * Get default value for field type
     */
    protected function getDefaultValueForType($type)
    {
        switch ($type) {
            case 'boolean':
                return false;
            case 'integer':
            case 'bigInteger':
            case 'float':
            case 'decimal':
                return 0;
            case 'string':
            case 'text':
            case 'richtext':
            case 'email':
            case 'url':
                return '';
            default:
                return null;
        }
    }

    /**
     * Convert fields array to export headers for Excel
     */
    protected function fieldsToExportHeaders($fields)
    {
        $headers = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $name));
            $type = $field['type'] ?? 'text';

            // Map field types to export types
            $exportType = 'text';
            if (in_array($type, ['integer', 'bigInteger', 'float', 'decimal'])) {
                $exportType = 'number';
            } elseif (in_array($type, ['date', 'datetime', 'timestamp'])) {
                $exportType = 'date';
            } elseif ($type === 'boolean') {
                $exportType = 'boolean';
            }

            $headers[] = "                                    { key: '{$name}', label: '{$label}', type: '{$exportType}' },";
        }
        return implode("\n", $headers);
    }

    /**
     * Convert fields array to language file content
     */
    protected function fieldsToLang($fields)
    {
        $lines = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $name));
            $placeholder = isset($field['placeholder']) ? $field['placeholder'] : "أدخل {$label}";

            // Add field label
            $lines[] = "            '{$name}' => '{$label}',";

            // Add placeholder if it's an input field
            if (!in_array($field['type'], ['boolean', 'date', 'datetime', 'timestamp'])) {
                $lines[] = "            '{$name}_placeholder' => '{$placeholder}',";
            }

            // Add specific translations for certain field types
            if ($field['type'] === 'boolean') {
                $lines[] = "            '{$name}_true' => 'نعم',";
                $lines[] = "            '{$name}_false' => 'لا',";
            }

            if ($field['type'] === 'email') {
                $lines[] = "            '{$name}_invalid' => 'البريد الإلكتروني غير صحيح',";
            }

            if ($field['type'] === 'password') {
                $lines[] = "            '{$name}_confirmation' => 'تأكيد {$label}',";
                $lines[] = "            '{$name}_confirmation_placeholder' => 'أدخل تأكيد {$label}',";
            }
        }

        // Add common fields
        $lines[] = "            'id' => 'رقم التعريف',";
        $lines[] = "            'is_active' => 'الحالة',";
        $lines[] = "            'created_at' => 'تاريخ الإنشاء',";
        $lines[] = "            'updated_at' => 'تاريخ التحديث',";

        return implode("\n", $lines);
    }

    /**
     * Get TypeScript type from field type
     */
    protected function getTsType($type)
    {
        $map = [
            'string' => 'string',
            'text' => 'string',
            'richtext' => 'string',
            'integer' => 'number',
            'bigInteger' => 'number',
            'float' => 'number',
            'decimal' => 'number',
            'boolean' => 'boolean',
            'date' => 'string',
            'datetime' => 'string',
            'timestamp' => 'string',
            'json' => 'any',
            'email' => 'string',
            'url' => 'string',
        ];
        return $map[$type] ?? 'any';
    }

    /**
     * Convert fields array to fillable array
     */
    protected function fieldsToFillable($fields)
    {
        $fillable = [];
        foreach ($fields as $field) {
            $fillable[] = "'{$field['name']}'";
        }
        return implode(",\n        ", $fillable);
    }

    /**
     * Convert fields array to casts array
     */
    protected function fieldsToCasts($fields)
    {
        $casts = [];
        foreach ($fields as $field) {
            switch ($field['type']) {
                case 'boolean':
                    $casts[] = "'{$field['name']}' => 'boolean'";
                    break;
                case 'integer':
                case 'bigInteger':
                    $casts[] = "'{$field['name']}' => 'integer'";
                    break;
                case 'float':
                case 'decimal':
                    $casts[] = "'{$field['name']}' => 'decimal:" . (isset($field['scale']) ? $field['scale'] : 2) . "'";
                    break;
                case 'date':
                    $casts[] = "'{$field['name']}' => 'date'";
                    break;
                case 'datetime':
                case 'timestamp':
                    $casts[] = "'{$field['name']}' => 'datetime'";
                    break;
                case 'json':
                    $casts[] = "'{$field['name']}' => 'array'";
                    break;
            }
        }
        return implode(",\n            ", $casts);
    }

    /**
     * Convert fields array to searchable fields for service
     */
    protected function fieldsToSearchable($fields)
    {
        $searchableFields = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $type = $field['type'] ?? 'string';

            // Only include string, text, email fields for search (not boolean, date, etc.)
            if (in_array($type, ['string', 'text', 'email', 'richtext'])) {
                $searchableFields[] = "                \$q->orWhere('{$name}', 'like', \"%{\$search}%\");";
            }
        }

        // If no searchable fields found, add a default search on 'name' field
        if (empty($searchableFields)) {
            $searchableFields[] = "                \$q->orWhere('name', 'like', \"%{\$search}%\");";
        }

        return implode("\n", $searchableFields);
    }

    /**
     * Convert fields array to test data array
     */
    protected function fieldsToTestData($fields)
    {
        $data = [];
        foreach ($fields as $field) {
            if (isset($field['required']) && $field['required'] && $field['type'] !== 'boolean') {
                $value = $this->getTestValue($field);
                $data[] = "        '{$field['name']}' => {$value},";
            }
        }
        return implode("\n", $data);
    }

    /**
     * Convert fields array to test assert structure
     */
    protected function fieldsToTestAssert($fields)
    {
        $asserts = [];
        foreach ($fields as $field) {
            $asserts[] = "                '{$field['name']}',";
        }
        return !empty($asserts) ? implode("\n", $asserts) : '';
    }

    /**
     * Convert fields array to test database assertion
     */
    protected function fieldsToTestDatabase($fields)
    {
        $data = [];
        foreach ($fields as $field) {
            if (isset($field['required']) && $field['required'] && $field['type'] !== 'boolean') {
                $value = $this->getTestValue($field);
                $data[] = "        '{$field['name']}' => {$value},";
            }
        }
        return !empty($data) ? implode("\n", $data) : '';
    }

    /**
     * Convert fields array to table assert structure
     */
    protected function fieldsToTableAssert($fields)
    {
        $asserts = [];
        foreach ($fields as $field) {
            if (isset($field['showIn']) && in_array('table', $field['showIn'])) {
                $asserts[] = "                        '{$field['name']}',";
            }
        }
        return !empty($asserts) ? implode("\n", $asserts) : '';
    }

    /**
     * Convert fields array to test update data
     */
    protected function fieldsToTestUpdate($fields)
    {
        $data = [];
        foreach ($fields as $field) {
            if (isset($field['required']) && $field['required'] && $field['type'] !== 'boolean') {
                $value = $this->getTestUpdateValue($field);
                $data[] = "        '{$field['name']}' => {$value},";
            }
        }
        return !empty($data) ? implode("\n", $data) : '';
    }

    /**
     * Convert fields array to test update database assertion
     */
    protected function fieldsToTestUpdateDatabase($fields)
    {
        $data = [];
        foreach ($fields as $field) {
            if (isset($field['required']) && $field['required'] && $field['type'] !== 'boolean') {
                $value = $this->getTestUpdateValue($field);
                $data[] = "        '{$field['name']}' => {$value},";
            }
        }
        return !empty($data) ? implode("\n", $data) : '';
    }

    /**
     * Generate test with relationships
     */
    protected function generateTestWithRelationships($relationships, $model, $fields)
    {
        if (empty($relationships)) {
            return '';
        }

        $hasBelongsTo = false;
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $hasBelongsTo = true;
                break;
            }
        }

        if (!$hasBelongsTo) {
            return '';
        }

        $modelLower = strtolower($model);
        $modelPluralLower = strtolower(Str::plural($model));

        $code = "\ntest('can create {$modelLower} with relationships via API', function () {\n";

        // Create related models
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $relatedModel = $rel['relatedModel'];
                $relatedModelLower = strtolower($relatedModel);
                $code .= "    // Create related {$relatedModel}\n";
                $code .= "    \${$relatedModelLower} = {$relatedModel}::factory()->create();\n";
            }
        }

        $code .= "\n    \$data = [\n";

        // Add fields
        foreach ($fields as $field) {
            if (isset($field['required']) && $field['required'] && $field['type'] !== 'boolean') {
                $value = $this->getTestValue($field);
                $code .= "        '{$field['name']}' => {$value},\n";
            }
        }

        // Add relationship foreign keys
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $relatedModel = $rel['relatedModel'];
                $relatedModelLower = strtolower($relatedModel);
                $foreignKey = $rel['foreignKey'] ?? strtolower($relatedModel) . '_id';
                $code .= "        '{$foreignKey}' => \${$relatedModelLower}->id,\n";
            }
        }

        $code .= "    ];\n\n";
        $code .= "    \$response = \$this->postJson(\"{\$this->apiBaseUrl}/{$modelPluralLower}\", \$data);\n\n";
        $code .= "    \$response->assertStatus(200);\n\n";
        $code .= "    \${$modelLower} = {$model}::latest()->first();\n";

        // Assert relationships
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $relatedModel = $rel['relatedModel'];
                $relatedModelLower = strtolower($relatedModel);
                $name = $rel['name'];
                $code .= "    expect(\${$modelLower}->{$name})->not->toBeNull();\n";
                $code .= "    expect(\${$modelLower}->{$name}->id)->toBe(\${$relatedModelLower}->id);\n";
            }
        }

        $code .= "});\n";

        return $code;
    }

    /**
     * Generate relationship imports for test file
     */
    protected function generateRelationshipImportsForTest($relationships)
    {
        if (empty($relationships)) {
            return '';
        }

        $imports = [];
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $relatedModel = $rel['relatedModel'];
                if (!in_array($relatedModel, $imports)) {
                    $imports[] = $relatedModel;
                }
            }
        }

        if (empty($imports)) {
            return '';
        }

        $importsCode = '';
        foreach ($imports as $import) {
            $importsCode .= "use App\\Models\\{$import};\n";
        }

        return $importsCode;
    }

    /**
     * Get test value for a field
     */
    protected function getTestValue($field)
    {
        switch ($field['type']) {
            case 'string':
                return "'Test {$field['name']}'";
            case 'text':
                return "'Test content'";
            case 'integer':
            case 'bigInteger':
                return '1';
            case 'float':
            case 'decimal':
                return '1.5';
            case 'boolean':
                return 'true';
            case 'date':
                return "'2024-01-01'";
            case 'datetime':
            case 'timestamp':
                return "'2024-01-01 12:00:00'";
            default:
                return "'Test value'";
        }
    }

    /**
     * Get test update value for a field
     */
    protected function getTestUpdateValue($field)
    {
        switch ($field['type']) {
            case 'string':
                return "'Updated {$field['name']}'";
            case 'text':
            case 'richtext':
                return "'Updated content'";
            case 'integer':
            case 'bigInteger':
                return '2';
            case 'float':
            case 'decimal':
                return '2.5';
            case 'boolean':
                return 'false';
            case 'date':
                return "'2024-02-01'";
            case 'datetime':
            case 'timestamp':
                return "'2024-02-01 12:00:00'";
            default:
                return "'Updated value'";
        }
    }

    /**
     * Convert relationships array to model relationship methods
     */
    protected function relationshipsToModelMethods($relationships, $model)
    {
        if (empty($relationships)) {
            return [
                'imports' => '',
                'methods' => '    // No relationships defined'
            ];
        }

        $methods = [];
        $imports = [];

        foreach ($relationships as $rel) {
            $name = $rel['name'];
            $type = $rel['type'];
            $relatedModel = $rel['relatedModel'];
            $relatedModelLower = strtolower($relatedModel);

            // Add import for related model
            $imports[] = "use App\\Models\\{$relatedModel};";

            switch ($type) {
                case 'belongsTo':
                    $foreignKey = $rel['foreignKey'] ?? strtolower($relatedModel) . '_id';
                    $ownerKey = $rel['localKey'] ?? 'id';
                    $methods[] = "    /**\n     * Get the {$relatedModelLower} that owns this {$model}.\n     */\n    public function {$name}()\n    {\n        return \$this->belongsTo({$relatedModel}::class, '{$foreignKey}', '{$ownerKey}');\n    }";
                    break;

                case 'hasOne':
                    $foreignKey = $rel['foreignKey'] ?? strtolower($model) . '_id';
                    $localKey = $rel['localKey'] ?? 'id';
                    $methods[] = "    /**\n     * Get the {$relatedModelLower} associated with this {$model}.\n     */\n    public function {$name}()\n    {\n        return \$this->hasOne({$relatedModel}::class, '{$foreignKey}', '{$localKey}');\n    }";
                    break;

                case 'hasMany':
                    $foreignKey = $rel['foreignKey'] ?? strtolower($model) . '_id';
                    $localKey = $rel['localKey'] ?? 'id';
                    $methods[] = "    /**\n     * Get the {$relatedModelLower} collection for this {$model}.\n     */\n    public function {$name}()\n    {\n        return \$this->hasMany({$relatedModel}::class, '{$foreignKey}', '{$localKey}');\n    }";
                    break;

                case 'belongsToMany':
                    $pivotTable = $rel['pivotTable'] ?? strtolower(Str::plural(Str::snake($model))) . '_' . strtolower(Str::plural(Str::snake($relatedModel)));
                    $foreignPivotKey = $rel['foreignPivotKey'] ?? strtolower($model) . '_id';
                    $relatedPivotKey = $rel['relatedPivotKey'] ?? strtolower($relatedModel) . '_id';
                    $withTimestamps = isset($rel['withTimestamps']) && $rel['withTimestamps'] ? 'true' : 'false';
                    $withPivot = isset($rel['withPivot']) && !empty($rel['withPivot']) ? ", ['" . implode("', '", $rel['withPivot']) . "']" : '';

                    $method = "    /**\n     * Get the {$relatedModelLower} collection for this {$model} (many-to-many).\n     */\n    public function {$name}()\n    {\n        return \$this->belongsToMany({$relatedModel}::class, '{$pivotTable}', '{$foreignPivotKey}', '{$relatedPivotKey}'";
                    if ($withTimestamps === 'true' || !empty($withPivot)) {
                        $method .= ")\n            ->withTimestamps(" . ($withTimestamps === 'true' ? 'true' : 'false') . ")";
                        if (!empty($withPivot)) {
                            $method .= "\n            ->withPivot" . $withPivot;
                        }
                        $method .= ";";
                    } else {
                        $method .= ");";
                    }
                    $method .= "\n    }";
                    $methods[] = $method;
                    break;

                case 'hasManyThrough':
                    $firstKey = $rel['foreignKey'] ?? strtolower($model) . '_id';
                    $secondKey = $rel['relatedPivotKey'] ?? strtolower($relatedModel) . '_id';
                    $throughModel = $rel['pivotTable'] ?? 'IntermediateModel'; // This should be a model name
                    $imports[] = "use App\\Models\\{$throughModel};";
                    $methods[] = "    /**\n     * Get the {$relatedModelLower} collection through {$throughModel}.\n     */\n    public function {$name}()\n    {\n        return \$this->hasManyThrough({$relatedModel}::class, {$throughModel}::class, '{$firstKey}', '{$secondKey}');\n    }";
                    break;
            }
        }

        $uniqueImports = array_unique($imports);
        $importsString = !empty($uniqueImports) ? implode("\n", $uniqueImports) : '';
        $methodsString = implode("\n\n", $methods);

        return [
            'imports' => $importsString,
            'methods' => $methodsString
        ];
    }

    /**
     * إضافة ملف routes إلى api.php تلقائياً
     * مع حفظ التعديلات في ملف مؤقت للجيل
     */
    protected function addRouteFileToApi(string $routeFile, ?string $generationId = null): void
    {
        $basePath = $this->outputBasePath ?? base_path();
        $apiRoutesPath = $basePath . '/routes/api.php';
        if (!file_exists($apiRoutesPath)) {
            return;
        }

        $content = file_get_contents($apiRoutesPath);
        $routeFileName = basename($routeFile);
        $routeInclude = "Route::group([], __DIR__ . '/api/dashboard/{$routeFileName}');";

        // التحقق من عدم وجود الملف مسبقاً
        if (str_contains($content, $routeFileName)) {
            return; // الملف موجود بالفعل
        }

        // حفظ المحتوى الأصلي قبل التعديل (للاستعادة عند التراجع)
        $originalContent = $content;

        // حساب المسار النسبي لملف routes
        $relativeRoutePath = str_replace($basePath . '/', '', $routeFile);
        $routeInclude = "Route::group([], __DIR__ . '/api/dashboard/{$routeFileName}');";

        // البحث عن مجموعة v1 وإضافة الملف داخلها
        // البحث عن المكان المناسب داخل المجموعة الداخلية
        $pattern = '/(Route::group\s*\(\s*\[\s*[\'"]prefix[\'"]\s*=>\s*[\'"]v1[\'"]\s*,\s*[\'"]middleware[\'"]\s*=>\s*[\'"]auth:sanctum[\'"]\s*\]\s*,\s*function\s*\(\)\s*\{[^}]*Route::group\s*\([^}]*\{)([^}]*\/\/ Add more dashboard routes here)/s';

        if (preg_match($pattern, $content, $matches)) {
            $replacement = $matches[1] . "\n                " . $routeInclude . "\n" . $matches[2];
            $content = preg_replace($pattern, $replacement, $content);
            file_put_contents($apiRoutesPath, $content);
        } else {
            // إذا لم تجد النمط، حاول إضافة قبل التعليق
            $pattern2 = '/(\/\/ Add more dashboard routes here)/';
            if (preg_match($pattern2, $content)) {
                $content = preg_replace($pattern2, $routeInclude . "\n                $1", $content);
                file_put_contents($apiRoutesPath, $content);
            }
        }

        // حفظ التعديلات في ملف مؤقت للجيل
        if ($generationId) {
            $this->tracker->saveFileModification('routes/api.php', $originalContent, [
                'type' => 'api_route',
                'route_file' => $routeFileName,
                'added_line' => $routeInclude,
            ], $generationId);
        }
    }

    /**
     * إزالة ملف routes من api.php تلقائياً
     */
    public static function removeRouteFileFromApi(string $routeFile, ?string $basePath = null): bool
    {
        $basePath = $basePath ?? base_path();
        $apiRoutesPath = $basePath . '/routes/api.php';

        if (!file_exists($apiRoutesPath)) {
            return false;
        }

        $content = file_get_contents($apiRoutesPath);
        $routeFileName = basename($routeFile);

        // البحث عن السطر الذي يحتوي على routeFileName وإزالته
        $pattern = '/\s*Route::group\s*\(\s*\[\s*\]\s*,\s*__DIR__\s*\.\s*[\'"]\/api\/dashboard\/' . preg_quote($routeFileName, '/') . '[\'"]\s*\)\s*;\s*\n?/';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, '', $content);
            file_put_contents($apiRoutesPath, $content);
            return true;
        }

        return false;
    }

    /**
     * إضافة Vue routes إلى routes.ts تلقائياً
     * مع تتبع التعديلات للتراجع
     */
    protected function addVueRouteToRoutes(string $routeFile, string $model, ?string $generationId = null): void
    {
        $basePath = $this->outputBasePath ?? base_path();
        $routesTsPath = $basePath . '/resources/ts/_dashboard/router/routes.ts';

        if (!file_exists($routesTsPath)) {
            return;
        }

        $content = file_get_contents($routesTsPath);
        $modelPluralLower = strtolower(Str::plural($model));
        $routeFileName = basename($routeFile, '.ts');

        // التحقق من عدم وجود الملف مسبقاً
        if (str_contains($content, "import {$modelPluralLower}") || str_contains($content, "import {$routeFileName}")) {
            return; // الملف موجود بالفعل
        }

        // حفظ المحتوى الأصلي قبل التعديل (للاستعادة عند التراجع)
        $originalContent = $content;

        // إضافة import
        $importLine = "import {$modelPluralLower} from \"./raws/{$routeFileName}\";";

        // البحث عن آخر import قبل التعليق
        $importPattern = '/(import\s+\w+\s+from\s+[\'"][^\'"]+[\'"];)/';
        if (preg_match_all($importPattern, $content, $matches)) {
            $lastImport = end($matches[0]);
            $content = str_replace($lastImport, $lastImport . "\n" . $importLine, $content);
        } else {
            // إذا لم تجد imports، أضف قبل التعليق
            $content = str_replace('// Import model routes here', $importLine . "\n// Import model routes here", $content);
        }

        // إضافة spread في routes array
        $spreadLine = "    ...{$modelPluralLower},";

        // البحث عن "// Add model routes here" وإضافة قبلها
        if (str_contains($content, '// Add model routes here')) {
            $content = str_replace('// Add model routes here', $spreadLine . "\n    // Add model routes here", $content);
        } else {
            // إذا لم تجد التعليق، أضف قبل NotFound route
            $content = preg_replace(
                '/(\s+)(\{[\s\S]*?path:\s*[\'"]\/:pathMatch)/',
                '$1' . $spreadLine . "\n$1$2",
                $content,
                1
            );
        }

        file_put_contents($routesTsPath, $content);

        // حفظ التعديلات في ملف مؤقت للجيل
        if ($generationId) {
            $this->tracker->saveFileModification('resources/ts/_dashboard/router/routes.ts', $originalContent, [
                'type' => 'vue_route',
                'model' => $model,
                'model_plural_lower' => $modelPluralLower,
                'route_file' => $routeFileName,
                'added_import' => $importLine,
                'added_spread' => $spreadLine,
            ], $generationId);
        }
    }

    /**
     * إزالة Vue routes من routes.ts تلقائياً
     * يتم استدعاؤها من FileTrackerService عند التراجع
     */
    public static function removeVueRouteFromRoutes(string $routeFile, ?string $basePath = null): bool
    {
        $basePath = $basePath ?? base_path();
        $routesTsPath = $basePath . '/resources/ts/_dashboard/router/routes.ts';

        if (!file_exists($routesTsPath)) {
            return false;
        }

        $content = file_get_contents($routesTsPath);
        $routeFileName = basename($routeFile, '.ts');
        $modelPluralLower = $routeFileName; // عادة يكون نفس الاسم

        // إزالة import
        $importPattern = '/import\s+' . preg_quote($modelPluralLower, '/') . '\s+from\s+[\'"]\.\/raws\/' . preg_quote($routeFileName, '/') . '[\'"];\s*\n?/';
        $content = preg_replace($importPattern, '', $content);

        // إزالة spread
        $spreadPattern = '/\s*\.\.\.' . preg_quote($modelPluralLower, '/') . ',\s*\n?/';
        $content = preg_replace($spreadPattern, '', $content);

        file_put_contents($routesTsPath, $content);
        return true;
    }

    /**
     * إضافة صلاحيات النموذج المولد إلى PermissionSeeder تلقائياً
     */
    protected function addPermissionsToSeeder(string $model): void
    {
        $basePath = $this->outputBasePath ?? base_path();
        $seederPath = $basePath . '/database/seeders/PermissionSeeder.php';

        if (!file_exists($seederPath)) {
            return;
        }

        $modelLower = strtolower($model);
        $modelPlural = Str::plural($model);
        $modelPluralLower = strtolower($modelPlural);

        // إنشاء نصوص الصلاحيات
        $permissions = [
            ['name' => "{$modelLower}_access", 'title' => "الوصول ل{$modelPlural}"],
            ['name' => "{$modelLower}_create", 'title' => "إضافة {$modelLower}"],
            ['name' => "{$modelLower}_view", 'title' => "عرض {$modelPlural}"],
            ['name' => "{$modelLower}_edit", 'title' => "تعديل {$modelPlural}"],
            ['name' => "{$modelLower}_delete", 'title' => "حذف {$modelPlural}"],
        ];

        $content = file_get_contents($seederPath);

        // التحقق من وجود الصلاحيات مسبقاً
        $permissionExists = false;
        foreach ($permissions as $permission) {
            if (str_contains($content, "'name' => '{$permission['name']}'")) {
                $permissionExists = true;
                break;
            }
        }

        if ($permissionExists) {
            return; // الصلاحيات موجودة بالفعل
        }

        // إنشاء نص الصلاحيات للإضافة
        $permissionsText = "\n            // {$model} permissions\n";
        foreach ($permissions as $permission) {
            $permissionsText .= "            ['name' => '{$permission['name']}', 'title' => '{$permission['title']}'],\n";
        }

        // البحث عن المكان المناسب للإضافة (قبل Profile & Settings)
        $pattern = '/(\/\/ Profile & Settings)/';
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $permissionsText . "            $1", $content);
            file_put_contents($seederPath, $content);
        } else {
            // إذا لم تجد، أضف قبل نهاية المصفوفة
            $pattern2 = '/(\s+);\s*foreach\s*\(\$permissions\s+as\s+\$permission\)/';
            if (preg_match($pattern2, $content)) {
                $content = preg_replace($pattern2, $permissionsText . "        $1", $content);
                file_put_contents($seederPath, $content);
            }
        }
    }

    /**
     * Check if fields array contains any richtext field
     */
    protected function hasRichtextField($fields): bool
    {
        foreach ($fields as $field) {
            if (isset($field['type']) && $field['type'] === 'richtext') {
                return true;
            }
        }
        return false;
    }

    /**
     * Convert fields array to display format for single page view
     */
    protected function fieldsToDisplay($model, $fields)
    {
        $modelLower = strtolower($model);
        $display = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $type = $field['type'] ?? 'string';
            $isFullWidth = in_array($type, ['text', 'richtext']);

            $colSpan = $isFullWidth ? ' md:col-span-2' : '';

            if ($type === 'boolean') {
                $display[] = "                                <div class=\"space-y-1{$colSpan}\">";
                $display[] = "                                    <div class=\"text-sm text-gray-500\">{{ c('{$modelLower}.fields.{$name}', '{$modelLower}') }}</div>";
                $display[] = "                                    <Tag :value=\"{$modelLower}?.{$name} ? c('{$modelLower}.fields.{$name}_true', '{$modelLower}') : c('{$modelLower}.fields.{$name}_false', '{$modelLower}')\" :severity=\"{$modelLower}?.{$name} ? 'success' : 'secondary'\" />";
                $display[] = "                                </div>";
            } elseif ($type === 'richtext') {
                $display[] = "                                <div class=\"space-y-1{$colSpan}\">";
                $display[] = "                                    <div class=\"text-sm text-gray-500 mb-2\">{{ c('{$modelLower}.fields.{$name}', '{$modelLower}') }}</div>";
                $display[] = "                                    <div class=\"text-base text-gray-900 prose prose-sm max-w-none\" v-html=\"{$modelLower}?.{$name} || '-'\"></div>";
                $display[] = "                                </div>";
            } elseif ($type === 'text') {
                $display[] = "                                <div class=\"space-y-1{$colSpan}\">";
                $display[] = "                                    <div class=\"text-sm text-gray-500\">{{ c('{$modelLower}.fields.{$name}', '{$modelLower}') }}</div>";
                $display[] = "                                    <div class=\"text-base text-gray-900\">{{ {$modelLower}?.{$name} || '-' }}</div>";
                $display[] = "                                </div>";
            } else {
                $display[] = "                                <div class=\"space-y-1{$colSpan}\">";
                $display[] = "                                    <div class=\"text-sm text-gray-500\">{{ c('{$modelLower}.fields.{$name}', '{$modelLower}') }}</div>";
                $display[] = "                                    <div class=\"text-base font-medium text-gray-900\">{{ {$modelLower}?.{$name} || '-' }}</div>";
                $display[] = "                                </div>";
            }
        }

        return implode("\n", $display);
    }
}
