<?php

namespace MicSoleLaravelGen\Services;

use Illuminate\Support\Facades\File;
use MicSoleLaravelGen\Services\CrudGeneratorService;

class FileTrackerService
{
    protected $trackFile;
    protected $storageDir;
    protected $backupDir;

    public function __construct()
    {
        // Use storage/app/mic-sole-laravel-gen/ instead of .mic in root
        $this->storageDir = storage_path('app/mic-sole-laravel-gen');
        $this->backupDir = $this->storageDir . '/backups';

        // Create directories if they don't exist
        if (!\File::exists($this->storageDir)) {
            \File::makeDirectory($this->storageDir, 0755, true);
        }
        if (!\File::exists($this->backupDir)) {
            \File::makeDirectory($this->backupDir, 0755, true);
        }

        // Main tracking file (index)
        $this->trackFile = $this->storageDir . '/index.json';
    }

    /**
     * Get all tracked generations
     */
    public function getHistory(): array
    {
        if (!File::exists($this->trackFile)) {
            return [];
        }

        $content = File::get($this->trackFile);
        $data = json_decode($content, true);

        return $data['history'] ?? [];
    }

    /**
     * Get current generation
     */
    public function getCurrent(): ?array
    {
        $history = $this->getHistory();
        return !empty($history) ? end($history) : null;
    }

    /**
     * Track a new generation
     * Files are stored in separate files to avoid large JSON files
     * @return string The generation ID
     */
    public function track(array $data): string
    {
        $history = $this->getHistory();

        $generationId = uniqid('gen_', true);
        $generation = [
            'id' => $generationId,
            'timestamp' => now()->toIso8601String(),
            'model' => $data['model'] ?? null,
            'command' => $data['command'] ?? 'generate',
            'files_count' => count($data['files'] ?? []),
            'files_file' => $this->getGenerationFilesPath($generationId), // Reference to separate file
            'metadata' => $data['metadata'] ?? [],
        ];

        // Save files in separate file
        $this->saveGenerationFiles($generationId, $data['files'] ?? []);

        $history[] = $generation;

        $this->saveHistory($history);

        return $generationId;
    }

    /**
     * Get path for generation files file
     */
    protected function getGenerationFilesPath(string $generationId): string
    {
        return $this->storageDir . '/generations/' . $generationId . '.json';
    }

    /**
     * Save generation files to separate file
     * Also calculate and store file hash for change detection
     */
    protected function saveGenerationFiles(string $generationId, array $files): void
    {
        $generationsDir = $this->storageDir . '/generations';
        if (!\File::exists($generationsDir)) {
            \File::makeDirectory($generationsDir, 0755, true);
        }

        // Calculate hash for each file to detect manual modifications
        foreach ($files as &$file) {
            $filePath = $file['path'] ?? null;
            if ($filePath && File::exists($filePath)) {
                // Store original hash for change detection
                $file['original_hash'] = md5_file($filePath);
                // Store original content hash if it's a modified file
                if (isset($file['original_content']) && $file['original_content'] !== null) {
                    $file['original_content_hash'] = md5($file['original_content']);
                }
            }
        }

        $filesPath = $this->getGenerationFilesPath($generationId);
        \File::put($filesPath, json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * حفظ تعديلات ملف في ملف مؤقت خاص بالجيل
     * يتم استدعاؤها عند تعديل api.php أو routes.ts
     */
    public function saveFileModification(string $relativePath, string $originalContent, array $modificationData, ?string $generationId = null): void
    {
        // إذا لم يتم تمرير generationId، الحصول على آخر جيل
        if (!$generationId) {
            $current = $this->getCurrent();
            if (!$current) {
                return; // لا يوجد جيل حالي
            }
            $generationId = $current['id'];
        }
        $modificationsFile = $this->storageDir . '/modifications/' . $generationId . '.json';

        // إنشاء مجلد modifications إذا لم يكن موجوداً
        $modificationsDir = $this->storageDir . '/modifications';
        if (!\File::exists($modificationsDir)) {
            \File::makeDirectory($modificationsDir, 0755, true);
        }

        // قراءة التعديلات الحالية
        $modifications = [];
        if (\File::exists($modificationsFile)) {
            $modifications = json_decode(\File::get($modificationsFile), true) ?: [];
        }

        // إضافة التعديل الجديد
        if (!isset($modifications[$relativePath])) {
            $modifications[$relativePath] = [
                'original_content' => $originalContent, // المحتوى الأصلي قبل أي تعديل
                'modifications' => [], // قائمة التعديلات المضافة
            ];
        }

        // إضافة التعديل الجديد إلى القائمة
        $modifications[$relativePath]['modifications'][] = $modificationData;

        // حفظ التعديلات
        \File::put($modificationsFile, json_encode($modifications, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * الحصول على تعديلات ملف معين من جيل محدد
     */
    public function getFileModifications(string $generationId, string $relativePath): ?array
    {
        $modificationsFile = $this->storageDir . '/modifications/' . $generationId . '.json';

        if (!\File::exists($modificationsFile)) {
            return null;
        }

        $modifications = json_decode(\File::get($modificationsFile), true);
        return $modifications[$relativePath] ?? null;
    }

    /**
     * تتبع تعديل ملف موجود (مثل routes.ts)
     * يتم استدعاؤها قبل تعديل الملف لحفظ المحتوى الأصلي
     * @deprecated استخدام saveFileModification بدلاً منها
     */
    public function trackModifiedFile(string $filePath, string $originalContent): void
    {
        // الحصول على آخر جيل
        $current = $this->getCurrent();
        if (!$current) {
            return; // لا يوجد جيل حالي
        }

        // الحصول على ملفات الجيل الحالي
        $files = $this->getGenerationFiles($current['id']);

        // البحث عن الملف في القائمة
        $fileFound = false;
        foreach ($files as &$file) {
            if (($file['path'] ?? '') === $filePath) {
                // الملف موجود - تحديث original_content
                $file['original_content'] = $originalContent;
                $fileFound = true;
                break;
            }
        }

        if (!$fileFound) {
            // الملف غير موجود - إضافته كملف معدل
            $relativePath = str_replace(base_path() . '/', '', $filePath);
            $relativePath = str_replace(base_path() . '\\', '', $relativePath);

            $files[] = [
                'type' => 'modified',
                'path' => $filePath,
                'relative_path' => $relativePath,
                'original_content' => $originalContent, // حفظ المحتوى الأصلي
            ];
        }

        // حفظ الملفات المحدثة
        $this->saveGenerationFiles($current['id'], $files);
    }

    /**
     * Get generation files from separate file
     */
    public function getGenerationFiles(string $generationId): array
    {
        $filesPath = $this->getGenerationFilesPath($generationId);
        if (!\File::exists($filesPath)) {
            return [];
        }

        $content = \File::get($filesPath);
        return json_decode($content, true) ?? [];
    }

    /**
     * Get generation by ID or index
     */
    public function getGeneration($idOrIndex): ?array
    {
        $history = $this->getHistory();

        if (is_numeric($idOrIndex)) {
            // Index-based (negative for reverse: -1 = last, -2 = second last, etc.)
            $index = $idOrIndex < 0 ? count($history) + $idOrIndex : $idOrIndex;
            return $history[$index] ?? null;
        }

        // ID-based
        foreach ($history as $gen) {
            if ($gen['id'] === $idOrIndex) {
                return $gen;
            }
        }

        return null;
    }

    /**
     * Rollback to a specific generation
     */
    public function rollback($idOrIndex, int $levels = 1): array
    {
        $history = $this->getHistory();

        if (empty($history)) {
            return ['status' => 'error', 'message' => 'No generation history found'];
        }

        // Get target generation
        $targetGen = $this->getGeneration($idOrIndex);

        if (!$targetGen) {
            return ['status' => 'error', 'message' => 'Generation not found'];
        }

        // Calculate rollback index
        $targetIndex = array_search($targetGen, $history);
        $rollbackIndex = $targetIndex - $levels;

        if ($rollbackIndex < 0) {
            $rollbackIndex = 0;
        }

        // Get files to delete/restore (from target generation to rollback point)
        $filesToDelete = [];
        $filesToRevert = []; // Files that were modified and need to be reverted

        for ($i = $targetIndex; $i >= $rollbackIndex; $i--) {
            if (isset($history[$i])) {
                $gen = $history[$i];
                // Get files from separate file if available
                $files = isset($gen['files_file']) ? $this->getGenerationFiles($gen['id']) : ($gen['files'] ?? []);
                foreach ($files as $file) {
                    $filePath = $file['path'] ?? null;
                    if (!$filePath) {
                        continue;
                    }

                    // Check if this is a modified file (has original_content)
                    if (isset($file['original_content'])) {
                        // This is a modified file - check if it was created or modified
                        if ($file['original_content'] === null) {
                            // File was created (original_content is null) - delete it
                            if (File::exists($filePath)) {
                                $filesToDelete[] = $filePath;
                            }
                        } else {
                            // File was modified (has original_content) - restore original content directly
                            // لا نهتم بالتعديلات اليدوية - نستعيد الملف كما كان عند أول رصد
                            if (File::exists($filePath)) {
                                $filesToRevert[] = [
                                    'path' => $filePath,
                                    'original_content' => $file['original_content'],
                                    'generation' => $gen['id'],
                                ];
                            }
                        }
                    } else {
                        // Regular file (created, no original_content) - delete directly
                        // لا نهتم بالتعديلات اليدوية - نحذف الملف المولد مباشرة
                        if (File::exists($filePath)) {
                            $filesToDelete[] = $filePath;
                        }
                    }
                }
            }
        }

        // Delete files
        $deleted = [];
        $errors = [];
        $routeFilesToClean = [];
        $reverted = [];

        // First, revert modified files
        foreach ($filesToRevert as $fileInfo) {
            try {
                $filePath = $fileInfo['path'];
                $originalContent = $fileInfo['original_content'];

                if ($originalContent === null) {
                    // File was created - delete it
                    if (File::exists($filePath)) {
                        File::delete($filePath);
                        $deleted[] = $filePath;
                    }
                } else {
                    // File was modified - restore original content
                    File::put($filePath, $originalContent);
                    $reverted[] = $filePath;
                }
            } catch (\Exception $e) {
                $errors[] = ['file' => $fileInfo['path'], 'error' => $e->getMessage()];
            }
        }

        // Then, delete regular files
        foreach ($filesToDelete as $filePath) {
            try {
                // تحديد ملفات routes لتنظيف api.php
                if (str_contains($filePath, '/routes/api/dashboard/api-') || str_contains($filePath, '\\routes\\api\\dashboard\\api-')) {
                    $routeFilesToClean[] = $filePath;
                }

                if (File::exists($filePath)) {
                    File::delete($filePath);
                    $deleted[] = $filePath;
                }
            } catch (\Exception $e) {
                $errors[] = ['file' => $filePath, 'error' => $e->getMessage()];
            }
        }

        // تنظيف routes/api.php من ملفات routes المحذوفة
        foreach ($routeFilesToClean as $routeFile) {
            // تحديد basePath من مسار الملف
            $basePath = null;
            if (str_contains($routeFile, '/routes/api/dashboard/')) {
                $basePath = substr($routeFile, 0, strpos($routeFile, '/routes/api/dashboard/'));
            } elseif (str_contains($routeFile, '\\routes\\api\\dashboard\\')) {
                $basePath = substr($routeFile, 0, strpos($routeFile, '\\routes\\api\\dashboard\\'));
            }

            if ($basePath) {
                CrudGeneratorService::removeRouteFileFromApi($routeFile, $basePath);
            }
        }

        // استعادة ملفات api.php و routes.ts من التعديلات المحفوظة في الملفات المؤقتة
        for ($i = $targetIndex; $i >= $rollbackIndex; $i--) {
            if (isset($history[$i])) {
                $gen = $history[$i];
                $genId = $gen['id'];

                // استعادة api.php
                $apiModifications = $this->getFileModifications($genId, 'routes/api.php');
                if ($apiModifications) {
                    $apiPath = base_path() . '/routes/api.php';
                    if (File::exists($apiPath)) {
                        $currentContent = File::get($apiPath);

                        // إزالة التعديلات الخاصة بهذا الجيل
                        foreach ($apiModifications['modifications'] as $mod) {
                            if ($mod['type'] === 'api_route') {
                                $routeInclude = $mod['added_line'];

                                // إزالة السطر المضاف
                                $currentContent = str_replace($routeInclude, '', $currentContent);
                                $currentContent = preg_replace('/\n\s*\n\s*\n/', "\n\n", $currentContent); // تنظيف الأسطر الفارغة
                            }
                        }

                        // إذا كانت هذه آخر تعديلات (أول جيل)، استعد المحتوى الأصلي
                        if ($i === $rollbackIndex) {
                            File::put($apiPath, $apiModifications['original_content']);
                            $reverted[] = $apiPath;
                        } else {
                            File::put($apiPath, $currentContent);
                        }
                    }
                }

                // استعادة routes.ts
                $routesModifications = $this->getFileModifications($genId, 'resources/ts/_dashboard/router/routes.ts');
                if ($routesModifications) {
                    $routesPath = base_path() . '/resources/ts/_dashboard/router/routes.ts';
                    if (File::exists($routesPath)) {
                        $currentContent = File::get($routesPath);

                        // إزالة التعديلات الخاصة بهذا الجيل
                        foreach ($routesModifications['modifications'] as $mod) {
                            if ($mod['type'] === 'vue_route') {
                                $modelPluralLower = $mod['model_plural_lower'];

                                // إزالة import
                                $importPattern = '/import\s+' . preg_quote($modelPluralLower, '/') . '\s+from\s+[\'"]\.\/raws\/[^\'"]+[\'"];\s*\n?/';
                                $currentContent = preg_replace($importPattern, '', $currentContent);

                                // إزالة spread
                                $spreadPattern = '/\s*\.\.\.' . preg_quote($modelPluralLower, '/') . ',\s*\n?/';
                                $currentContent = preg_replace($spreadPattern, '', $currentContent);
                            }
                        }

                        // تنظيف الأسطر الفارغة
                        $currentContent = preg_replace('/\n\s*\n\s*\n/', "\n\n", $currentContent);

                        // إذا كانت هذه آخر تعديلات (أول جيل)، استعد المحتوى الأصلي
                        if ($i === $rollbackIndex) {
                            File::put($routesPath, $routesModifications['original_content']);
                            $reverted[] = $routesPath;
                        } else {
                            File::put($routesPath, $currentContent);
                        }
                    }
                }
            }
        }

        // Delete generation files for rolled back generations
        for ($i = $targetIndex; $i > $rollbackIndex; $i--) {
            if (isset($history[$i])) {
                $gen = $history[$i];
                $genId = $gen['id'];

                if (isset($gen['files_file'])) {
                    $filesPath = $this->getGenerationFilesPath($genId);
                    if (\File::exists($filesPath)) {
                        \File::delete($filesPath);
                    }
                }

                // حذف ملف التعديلات المؤقت للجيل
                $modificationsFile = $this->storageDir . '/modifications/' . $genId . '.json';
                if (\File::exists($modificationsFile)) {
                    \File::delete($modificationsFile);
                }
            }
        }

        // Remove generations from history
        $newHistory = array_slice($history, 0, $rollbackIndex + 1);
        $this->saveHistory($newHistory);

        return [
            'status' => 'success',
            'message' => "Rolled back {$levels} level(s)",
            'deleted' => $deleted,
            'reverted' => $reverted, // Files that were restored to original state
            'errors' => $errors,
        ];
    }

    /**
     * Preview what will be affected by rollback (without executing)
     */
    public function previewRollback($idOrIndex, int $levels = 1): array
    {
        $history = $this->getHistory();

        if (empty($history)) {
            return ['status' => 'error', 'message' => 'No generation history found'];
        }

        // Get target generation
        $targetGen = $this->getGeneration($idOrIndex);

        if (!$targetGen) {
            return ['status' => 'error', 'message' => 'Generation not found'];
        }

        // Calculate rollback index
        $targetIndex = array_search($targetGen, $history);
        $rollbackIndex = $targetIndex - $levels;

        if ($rollbackIndex < 0) {
            $rollbackIndex = 0;
        }

        // Get files that will be affected
        $filesToDelete = [];
        $filesToRevert = [];
        $generationsToRemove = [];

        for ($i = $targetIndex; $i >= $rollbackIndex; $i--) {
            if (isset($history[$i])) {
                $gen = $history[$i];
                $generationsToRemove[] = [
                    'id' => $gen['id'],
                    'model' => $gen['model'] ?? 'N/A',
                    'timestamp' => $gen['timestamp'] ?? '',
                ];

                // Get files from separate file if available
                $files = isset($gen['files_file']) ? $this->getGenerationFiles($gen['id']) : ($gen['files'] ?? []);

                foreach ($files as $file) {
                    $filePath = $file['path'] ?? null;
                    if (!$filePath) {
                        continue;
                    }

                    // Normalize path for display
                    $relativePath = str_replace(base_path() . '/', '', $filePath);
                    $relativePath = str_replace(base_path() . '\\', '', $relativePath);

                    // Check if this is a modified file (has original_content)
                    if (isset($file['original_content'])) {
                        if ($file['original_content'] === null) {
                            // File was created - will be deleted
                            $filesToDelete[] = [
                                'path' => $filePath,
                                'relative_path' => $relativePath,
                                'type' => $file['type'] ?? 'unknown',
                                'generation' => $gen['id'],
                                'model' => $gen['model'] ?? 'N/A',
                            ];
                        } else {
                            // File was modified - will be reverted
                            $filesToRevert[] = [
                                'path' => $filePath,
                                'relative_path' => $relativePath,
                                'type' => $file['type'] ?? 'unknown',
                                'generation' => $gen['id'],
                                'model' => $gen['model'] ?? 'N/A',
                            ];
                        }
                    } else {
                        // Regular file (created) - will be deleted
                        $filesToDelete[] = [
                            'path' => $filePath,
                            'relative_path' => $relativePath,
                            'type' => $file['type'] ?? 'unknown',
                            'generation' => $gen['id'],
                            'model' => $gen['model'] ?? 'N/A',
                        ];
                    }
                }
            }
        }

        // إضافة ملفات api.php و routes.ts إلى قائمة الملفات التي سيتم تعديلها
        for ($i = $targetIndex; $i >= $rollbackIndex; $i--) {
            if (isset($history[$i])) {
                $gen = $history[$i];
                $genId = $gen['id'];

                // التحقق من تعديلات api.php
                $apiModifications = $this->getFileModifications($genId, 'routes/api.php');
                if ($apiModifications) {
                    $apiPath = base_path() . '/routes/api.php';
                    $relativePath = 'routes/api.php';

                    // التحقق من عدم وجود الملف في القائمة بالفعل
                    $exists = false;
                    foreach ($filesToRevert as $file) {
                        if ($file['relative_path'] === $relativePath) {
                            $exists = true;
                            break;
                        }
                    }

                    if (!$exists) {
                        $filesToRevert[] = [
                            'path' => $apiPath,
                            'relative_path' => $relativePath,
                            'type' => 'modified',
                            'generation' => $genId,
                            'model' => $gen['model'] ?? 'N/A',
                            'modifications_count' => count($apiModifications['modifications']),
                        ];
                    }
                }

                // التحقق من تعديلات routes.ts
                $routesModifications = $this->getFileModifications($genId, 'resources/ts/_dashboard/router/routes.ts');
                if ($routesModifications) {
                    $routesPath = base_path() . '/resources/ts/_dashboard/router/routes.ts';
                    $relativePath = 'resources/ts/_dashboard/router/routes.ts';

                    // التحقق من عدم وجود الملف في القائمة بالفعل
                    $exists = false;
                    foreach ($filesToRevert as $file) {
                        if ($file['relative_path'] === $relativePath) {
                            $exists = true;
                            break;
                        }
                    }

                    if (!$exists) {
                        $filesToRevert[] = [
                            'path' => $routesPath,
                            'relative_path' => $relativePath,
                            'type' => 'modified',
                            'generation' => $genId,
                            'model' => $gen['model'] ?? 'N/A',
                            'modifications_count' => count($routesModifications['modifications']),
                        ];
                    }
                }
            }
        }

        // Get new current generation after rollback
        $newCurrent = null;
        if ($rollbackIndex >= 0 && isset($history[$rollbackIndex])) {
            $newCurrent = $history[$rollbackIndex];
        }

        return [
            'status' => 'success',
            'summary' => [
                'generations_to_remove' => count($generationsToRemove),
                'files_to_delete' => count($filesToDelete),
                'files_to_revert' => count($filesToRevert),
            ],
            'generations_to_remove' => $generationsToRemove,
            'files_to_delete' => $filesToDelete,
            'files_to_revert' => $filesToRevert,
            'new_current_generation' => $newCurrent ? [
                'id' => $newCurrent['id'],
                'model' => $newCurrent['model'] ?? 'N/A',
            ] : null,
        ];
    }

    /**
     * List all generations
     */
    public function list(int $limit = 10): array
    {
        $history = $this->getHistory();
        return array_slice(array_reverse($history), 0, $limit);
    }

    /**
     * Save history
     */
    protected function saveHistory(array $history): void
    {
        $data = [
            'version' => '1.0',
            'updated_at' => now()->toIso8601String(),
            'history' => $history,
        ];

        File::put($this->trackFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Clear all history and generation files
     */
    public function clear(): void
    {
        // Delete index file
        if (File::exists($this->trackFile)) {
            File::delete($this->trackFile);
        }

        // Delete all generation files
        $generationsDir = $this->storageDir . '/generations';
        if (File::exists($generationsDir)) {
            $files = File::files($generationsDir);
            foreach ($files as $file) {
                File::delete($file);
            }
        }

        // Delete backup files
        if (File::exists($this->backupDir)) {
            $backupFiles = File::files($this->backupDir);
            foreach ($backupFiles as $file) {
                File::delete($file);
            }
        }
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        $history = $this->getHistory();

        $totalFiles = 0;
        $models = [];

        foreach ($history as $gen) {
            $totalFiles += $gen['files_count'] ?? 0;
            if (isset($gen['model'])) {
                $models[$gen['model']] = ($models[$gen['model']] ?? 0) + 1;
            }
        }

        return [
            'total_generations' => count($history),
            'total_files' => $totalFiles,
            'models' => $models,
            'last_generation' => !empty($history) ? end($history) : null,
        ];
    }
}

