<?php

namespace MicSoleLaravelGen\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use MicSoleLaravelGen\Http\Requests\CrudGeneratorRequest;
use MicSoleLaravelGen\Services\CrudGeneratorService;
use MicSoleLaravelGen\Services\FileTrackerService;

class CrudGeneratorController extends Controller
{
    protected $service;
    protected $tracker;

    public function __construct(CrudGeneratorService $service, FileTrackerService $tracker)
    {
        $this->service = $service;
        $this->tracker = $tracker;
    }

    public function generate(CrudGeneratorRequest $request)
    {
        $data = $request->validated();
        $result = $this->service->generate($data);
        return response()->json($result);
    }

    /**
     * Get generation history
     */
    public function getHistory(Request $request)
    {
        $limit = (int) ($request->get('limit', 50));
        $history = $this->tracker->list($limit);

        // Load files for each generation
        foreach ($history as &$gen) {
            if (isset($gen['files_file'])) {
                $gen['files'] = $this->tracker->getGenerationFiles($gen['id']);
            }
        }

        return response()->json([
            'status' => 'success',
            'history' => $history,
        ]);
    }

    /**
     * Get generation statistics
     */
    public function getStats()
    {
        $stats = $this->tracker->getStats();
        return response()->json([
            'status' => 'success',
            'stats' => $stats,
        ]);
    }

    /**
     * Get specific generation details
     */
    public function getGeneration(Request $request, $id)
    {
        $gen = $this->tracker->getGeneration($id);

        if (!$gen) {
            return response()->json([
                'status' => 'error',
                'message' => 'Generation not found',
            ], 404);
        }

        // Load files
        if (isset($gen['files_file'])) {
            $gen['files'] = $this->tracker->getGenerationFiles($gen['id']);
        }

        return response()->json([
            'status' => 'success',
            'generation' => $gen,
        ]);
    }

    /**
     * Rollback to a specific generation
     */
    public function rollback(Request $request)
    {
        $request->validate([
            'id' => 'nullable|string',
            'level' => 'nullable|integer|min:1',
        ]);

        $id = $request->get('id');
        $level = (int) ($request->get('level', 1));

        $result = $this->tracker->rollback($id ?? -1, $level);

        if ($result['status'] === 'error') {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Preview rollback without executing
     */
    public function previewRollback(Request $request)
    {
        $request->validate([
            'id' => 'nullable|string',
            'level' => 'nullable|integer|min:1',
        ]);

        $id = $request->get('id');
        $level = (int) ($request->get('level', 1));

        $result = $this->tracker->previewRollback($id ?? -1, $level);

        if ($result['status'] === 'error') {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Clear all history
     */
    public function clearHistory(Request $request)
    {
        $this->tracker->clear();
        return response()->json([
            'status' => 'success',
            'message' => 'History cleared successfully',
        ]);
    }

    /**
     * Get list of available models
     */
    public function getModels()
    {
        $modelsPath = app_path('Models');
        $models = [];

        if (is_dir($modelsPath)) {
            $files = glob($modelsPath . '/*.php');
            foreach ($files as $file) {
                $filename = basename($file, '.php');
                // Skip abstract classes and interfaces
                if ($filename === 'Model') {
                    continue;
                }
                $models[] = $filename;
            }
        }

        // Sort models alphabetically
        sort($models);

        return response()->json([
            'status' => 'success',
            'models' => $models,
        ]);
    }
}
