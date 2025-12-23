<?php

namespace MicSoleLaravelGen\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrudGeneratorRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules() {
        return [
            'model' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Check if model file already exists
                    $modelPath = app_path('Models/' . $value . '.php');
                    if (file_exists($modelPath)) {
                        $fail("The model \"{$value}\" already exists. Please choose a different name or delete the existing model first.");
                    }
                },
            ],
            'fields' => 'required|array',
            'relationships' => 'sometimes|array',
            'relationships.*.name' => 'required|string',
            'relationships.*.type' => 'required|in:belongsTo,hasOne,hasMany,belongsToMany,hasManyThrough',
            'relationships.*.relatedModel' => 'required|string',
            'relationships.*.foreignKey' => 'sometimes|nullable|string',
            'relationships.*.localKey' => 'sometimes|nullable|string',
            'relationships.*.pivotTable' => 'sometimes|nullable|string',
            'relationships.*.foreignPivotKey' => 'sometimes|nullable|string',
            'relationships.*.relatedPivotKey' => 'sometimes|nullable|string',
            'relationships.*.withTimestamps' => 'sometimes|boolean',
            'relationships.*.withPivot' => 'sometimes|array',
            'backendFiles' => 'required|array',
            'vueFiles' => 'sometimes|array', // ملفات Vue (اختياري)
            'options' => 'sometimes|array',
            'outputBasePath' => 'sometimes|string', // المسار الخارجي (اختياري)
        ];
    }
}
