import React, { useState, useRef, useEffect } from 'react';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { Checkbox } from "@/components/ui/checkbox";
import { Select, SelectItem, SelectTrigger, SelectContent, SelectValue } from "@/components/ui/select";
import { Plus, Trash2, ChevronDown, ChevronRight, MoreVertical, AlertCircle, Asterisk, Loader2 } from 'lucide-react';
import SelectMultiple from './SelectMultiple';
import type { CrudField, CrudRelationship, GeneratorOptions } from './types';
import PreviewGenerator from './PreviewGenerator';

interface GeneratorTabProps {
  fields: CrudField[];
  setFields: React.Dispatch<React.SetStateAction<CrudField[]>>;
  relationships: CrudRelationship[];
  setRelationships: React.Dispatch<React.SetStateAction<CrudRelationship[]>>;
  options: GeneratorOptions;
  setOptions: React.Dispatch<React.SetStateAction<GeneratorOptions>>;
  model: string;
  setModel: React.Dispatch<React.SetStateAction<string>>;
  frontend: 'vue' | 'react' | 'both';
  setFrontend: React.Dispatch<React.SetStateAction<'vue' | 'react' | 'both'>>;
}

const FIELD_TYPES = [
  { value: 'string', label: 'String' },
  { value: 'integer', label: 'Integer' },
  { value: 'boolean', label: 'Boolean' },
  { value: 'date', label: 'Date' },
  { value: 'text', label: 'Text' },
  { value: 'richtext', label: 'Rich Text Editor' },
  { value: 'float', label: 'Float' },
  { value: 'enum', label: 'Enum' },
  { value: 'relationship', label: 'Relationship (علاقة)' },
];

const RELATIONSHIP_TYPES = [
  { value: 'belongsTo', label: 'Belongs To (Many to One)' },
  { value: 'hasOne', label: 'Has One (One to One)' },
  { value: 'hasMany', label: 'Has Many (One to Many)' },
  { value: 'belongsToMany', label: 'Belongs To Many (Many to Many)' },
  { value: 'hasManyThrough', label: 'Has Many Through' },
];

const SHOW_IN_OPTIONS = ['add', 'edit', 'show', 'table'];

const DEFAULT_FIELDS_JSON = `[
  { "name": "name", "type": "string", "required": true, "unique": false, "default": "", "min": "", "max": "", "enumValues": "", "showIn": ["add", "edit", "show", "table"] },
  { "name": "user_id", "type": "integer", "required": true, "unique": false, "default": "", "min": "", "max": "", "enumValues": "", "showIn": ["add", "edit", "show", "table"] },
  { "name": "content", "type": "text", "required": true, "unique": false, "default": "", "min": "", "max": "", "enumValues": "", "showIn": ["add", "edit", "show"] },
  { "name": "slug", "type": "string", "required": true, "unique": true, "default": "", "min": "", "max": "", "enumValues": "", "showIn": ["add", "edit", "show", "table"] },
  { "name": "image", "type": "string", "required": false, "unique": false, "default": "", "min": "", "max": "", "enumValues": "", "showIn": ["add", "edit", "show"] },
  { "name": "status", "type": "string", "required": true, "unique": false, "default": "draft", "min": "", "max": "", "enumValues": "draft,published,archived", "showIn": ["add", "edit", "show", "table"] }
]`;

function isNumericType(type: string) {
  return type === 'integer' || type === 'float';
}

export default function GeneratorTab({ fields, setFields, relationships, setRelationships, options, setOptions, model, setModel, frontend, setFrontend }: GeneratorTabProps) {
  const [expanded, setExpanded] = useState<number | null>(null);
  const [jsonInput, setJsonInput] = useState(DEFAULT_FIELDS_JSON);
  const [jsonError, setJsonError] = useState('');
  const [generatedFiles, setGeneratedFiles] = useState([]); // [{name, content}]
  const [activeFile, setActiveFile] = useState(0);
  const codeRef = useRef<HTMLPreElement>(null);
  const [jsonOpen, setJsonOpen] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<{ [idx: number]: string }>({});
  const [previewEnabled, setPreviewEnabled] = useState(true);
  const [previewOpen, setPreviewOpen] = useState(true);
  const [availableModels, setAvailableModels] = useState<string[]>([]);
  const [loadingModels, setLoadingModels] = useState(false);
  const [relationshipModalOpen, setRelationshipModalOpen] = useState(false);
  const [currentFieldIndex, setCurrentFieldIndex] = useState<number | null>(null);
  const [tempRelationship, setTempRelationship] = useState<CrudRelationship>({
    name: '',
    type: 'belongsTo',
    relatedModel: '',
    foreignKey: '',
    localKey: 'id'
  });

  // Helper function to extract model name from field name (e.g., user_id -> User)
  const extractModelNameFromField = (fieldName: string): string => {
    if (!fieldName) return '';
    // Remove _id suffix if present
    let modelName = fieldName.replace(/_id$/, '');
    // Convert snake_case to PascalCase
    modelName = modelName.split('_').map(word =>
      word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
    ).join('');
    return modelName;
  };

  // Open relationship modal for a field
  const openRelationshipModal = (fieldIdx: number) => {
    const field = fields[fieldIdx];
    const fieldName = field.name || '';
    const modelName = extractModelNameFromField(fieldName);

    // Check if relationship already exists for this field
    const existingRel = relationships.find(
      rel => rel.foreignKey === fieldName || rel.name === fieldName.replace(/_id$/, '')
    );

    if (existingRel) {
      setTempRelationship(existingRel);
    } else {
      setTempRelationship({
        name: fieldName.replace(/_id$/, ''),
        type: 'belongsTo',
        relatedModel: modelName,
        foreignKey: fieldName,
        localKey: 'id'
      });
    }

    setCurrentFieldIndex(fieldIdx);
    setRelationshipModalOpen(true);
  };

  // Save relationship from modal
  const saveRelationship = () => {
    if (currentFieldIndex === null) return;

    const field = fields[currentFieldIndex];
    const fieldName = field.name || '';

    // Check if relationship already exists
    const existingRelIndex = relationships.findIndex(
      rel => rel.foreignKey === fieldName || rel.name === fieldName.replace(/_id$/, '')
    );

    const updatedRelationship = {
      ...tempRelationship,
      foreignKey: fieldName, // Always use current field name
    };

    if (existingRelIndex !== -1) {
      // Update existing relationship
      setRelationships(relationships.map((rel, idx) =>
        idx === existingRelIndex ? updatedRelationship : rel
      ));
    } else {
      // Add new relationship
      setRelationships([...relationships, updatedRelationship]);
    }

    setRelationshipModalOpen(false);
    setCurrentFieldIndex(null);
  };

  const handleFieldChange = (idx: number, key: keyof CrudField, value: any) => {
    const updatedFields = fields.map((f, i) => i === idx ? { ...f, [key]: value } : f);
    setFields(updatedFields);

    const field = updatedFields[idx];

    // If type changed to "relationship", open modal
    if (key === 'type' && value === 'relationship') {
      openRelationshipModal(idx);
    }
  };

  const addField = () => setFields([
    ...fields,
    { name: '', type: 'string', required: true, unique: false, default: '', min: '', max: '', enumValues: '', showIn: [...SHOW_IN_OPTIONS] }
  ]);
  const removeField = (idx: number) => setFields(fields => fields.filter((_, i) => i !== idx));

  // Get relationship for a field
  const getFieldRelationship = (fieldName: string): CrudRelationship | null => {
    return relationships.find(
      rel => rel.foreignKey === fieldName || rel.name === fieldName.replace(/_id$/, '')
    ) || null;
  };

  // Remove relationship for a field
  const removeFieldRelationship = (fieldName: string) => {
    setRelationships(relationships.filter(
      rel => rel.foreignKey !== fieldName && rel.name !== fieldName.replace(/_id$/, '')
    ));
  };

  // Generate relationship code preview
  const generateRelationshipCode = (rel: CrudRelationship): string => {
    if (!rel.name || !rel.relatedModel || !rel.type) {
      return '// Please fill in all required fields';
    }

    const currentModel = model || 'Model';
    const name = rel.name;
    const type = rel.type;
    const relatedModel = rel.relatedModel;
    const relatedModelLower = relatedModel.charAt(0).toLowerCase() + relatedModel.slice(1);
    const currentModelLower = currentModel.charAt(0).toLowerCase() + currentModel.slice(1);

    let code = '';
    let importCode = `use App\\Models\\${relatedModel};`;

    switch (type) {
      case 'belongsTo':
        const foreignKey = rel.foreignKey || `${relatedModelLower}_id`;
        const ownerKey = rel.localKey || 'id';
        code = `    /**\n     * Get the ${relatedModelLower} that owns this ${currentModelLower}.\n     */\n    public function ${name}()\n    {\n        return $this->belongsTo(${relatedModel}::class, '${foreignKey}', '${ownerKey}');\n    }`;
        break;

      case 'hasOne':
        const fk1 = rel.foreignKey || `${currentModelLower}_id`;
        const lk1 = rel.localKey || 'id';
        code = `    /**\n     * Get the ${relatedModelLower} associated with this ${currentModelLower}.\n     */\n    public function ${name}()\n    {\n        return $this->hasOne(${relatedModel}::class, '${fk1}', '${lk1}');\n    }`;
        break;

      case 'hasMany':
        const fk2 = rel.foreignKey || `${currentModelLower}_id`;
        const lk2 = rel.localKey || 'id';
        code = `    /**\n     * Get the ${relatedModelLower} collection for this ${currentModelLower}.\n     */\n    public function ${name}()\n    {\n        return $this->hasMany(${relatedModel}::class, '${fk2}', '${lk2}');\n    }`;
        break;

      case 'belongsToMany':
        const pivotTable = rel.pivotTable || `${currentModelLower}s_${relatedModel.toLowerCase()}s`;
        const foreignPivotKey = rel.foreignPivotKey || `${currentModelLower}_id`;
        const relatedPivotKey = rel.relatedPivotKey || `${relatedModelLower}_id`;
        const withTimestamps = rel.withTimestamps ? 'true' : 'false';
        code = `    /**\n     * Get the ${relatedModelLower} collection for this ${currentModelLower} (many-to-many).\n     */\n    public function ${name}()\n    {\n        return $this->belongsToMany(${relatedModel}::class, '${pivotTable}', '${foreignPivotKey}', '${relatedPivotKey}'${withTimestamps === 'true' ? ')\n            ->withTimestamps(true)' : ''};`;
        break;

      case 'hasManyThrough':
        const firstKey = rel.foreignKey || `${currentModelLower}_id`;
        const secondKey = rel.relatedPivotKey || `${relatedModelLower}_id`;
        const throughModel = rel.pivotTable || 'IntermediateModel';
        importCode += `\nuse App\\Models\\${throughModel};`;
        code = `    /**\n     * Get the ${relatedModelLower} collection through ${throughModel}.\n     */\n    public function ${name}()\n    {\n        return $this->hasManyThrough(${relatedModel}::class, ${throughModel}::class, '${firstKey}', '${secondKey}');\n    }`;
        break;

      default:
        return '// Unknown relationship type';
    }

    return `${importCode}\n\n${code}`;
  };

  const handleOptionChange = (key: keyof GeneratorOptions, value: boolean) => {
    setOptions(opts => ({ ...opts, [key]: value }));
  };

  const validateFields = () => {
    const errors: { [idx: number]: string } = {};
    fields.forEach((field, idx) => {
      if (field.required && (!field.name.trim() || !field.type.trim())) {
        errors[idx] = 'Name and type are required';
      }
    });
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleCopy = () => {
    if (generatedFiles[activeFile]) {
      navigator.clipboard.writeText(generatedFiles[activeFile].content);
    }
  };

  const handleDownload = () => {
    if (generatedFiles[activeFile]) {
      const blob = new Blob([generatedFiles[activeFile].content], { type: 'text/plain' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = generatedFiles[activeFile].name;
      a.click();
      URL.revokeObjectURL(url);
    }
  };

  const handleImportJson = () => {
    setJsonError('');
    try {
      const parsed = JSON.parse(jsonInput);
      if (!Array.isArray(parsed)) throw new Error('JSON must be an array');
      // Basic schema check
      const valid = parsed.every((f: any) => typeof f.name === 'string' && typeof f.type === 'string');
      if (!valid) throw new Error('Each field must have at least name and type');
      setFields(parsed.map((f: any) => ({
        ...f,
        showIn: Array.isArray(f.showIn) ? f.showIn : [...SHOW_IN_OPTIONS],
      })));
    } catch (e: any) {
      setJsonError(e.message || 'Invalid JSON');
    }
  };

  const handleResetFields = () => {
    setJsonError('');
    // Reset fields to empty array
    setFields([]);
    // Reset JSON input to default
    setJsonInput(DEFAULT_FIELDS_JSON);
    // Close JSON section
    setJsonOpen(false);
  };

  // Track if models have been fetched
  const modelsFetchedRef = useRef(false);

  // Disable body scroll when modal is open
  useEffect(() => {
    if (relationshipModalOpen) {
      // Save current overflow value
      const originalOverflow = document.body.style.overflow;
      // Disable body scroll
      document.body.style.overflow = 'hidden';

      // Cleanup: restore original overflow when modal closes
      return () => {
        document.body.style.overflow = originalOverflow;
      };
    }
  }, [relationshipModalOpen]);

  // Load available models from API when modal opens
  React.useEffect(() => {
    if (relationshipModalOpen && !modelsFetchedRef.current) {
      modelsFetchedRef.current = true;
      const fetchModels = async () => {
        setLoadingModels(true);
        try {
          const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
          const res = await fetch('/generator/models', {
            method: 'GET',
            headers: {
              'X-CSRF-TOKEN': token || '',
            },
          });
          const data = await res.json();
          if (res.ok && data.status === 'success') {
            setAvailableModels(data.models || []);
          }
        } catch (e) {
          console.error('Failed to load models:', e);
        } finally {
          setLoadingModels(false);
        }
      };

      fetchModels();
    }
  }, [relationshipModalOpen]);

  return (
    <>
      <div className="mb-6">
        <label className="block text-sm font-semibold mb-1">
          Model/Entity Name
        </label>
        <Input
          className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-400"
          placeholder="e.g. User, Product, Invoice..."
          value={model}
          onChange={(e) => setModel(e.target.value)}
        />
      </div>
      {/* Collapsible Paste Fields as JSON */}
      <div className="mb-4">
        <button
          type="button"
          className="flex items-center gap-2 text-sm font-semibold mb-1 text-primary-700 dark:text-primary-300 hover:underline focus:outline-none"
          onClick={() => setJsonOpen((o) => !o)}
        >
          {jsonOpen ? (
            <ChevronDown className="w-4 h-4" />
          ) : (
            <ChevronRight className="w-4 h-4" />
          )}
          Paste Fields as JSON
        </button>
        {jsonOpen && (
          <>
            <textarea
              className="w-full min-h-[80px] rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 p-2 text-xs font-mono mt-2"
              placeholder='[\n  { "name": "title", "type": "string", "required": true, "unique": false, "default": "", "min": "", "max": "", "enumValues": "", "showIn": ["add", "edit", "show", "table"] }\n]'
              value={jsonInput}
              onChange={(e) => setJsonInput(e.target.value)}
            />
            <div className="flex items-center gap-2 mt-2">
              <Button size="sm" variant="outline" onClick={handleImportJson}>
                Import Fields from JSON
              </Button>
              <Button size="sm" variant="outline" onClick={handleResetFields}>
                Reset Fields from JSON
              </Button>
              {jsonError && (
                <span className="text-red-500 text-xs">{jsonError}</span>
              )}
            </div>
          </>
        )}
      </div>
      <section className="mb-6">
        <div className="flex items-center justify-between mb-2">
          <label className="block text-sm font-semibold">Fields</label>
          <div className="flex items-center gap-4">
            <Button
              variant="secondary"
              size="sm"
              onClick={addField}
              className="gap-1"
            >
              <Plus className="w-4 h-4" /> Add Field
            </Button>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm border-separate border-spacing-y-1">
            <thead>
              <tr className="text-slate-400">
                <th className="text-left px-2">Name</th>
                <th className="text-left px-2">Type</th>
                <th className="text-center px-2">Required</th>
                <th className="text-center px-2">Show In</th>
                <th className="px-2 text-center">Remove</th>
              </tr>
            </thead>
            <tbody>
              {fields.map((field, idx) => (
                <React.Fragment key={idx}>
                  <tr className="bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 rounded">
                    <td className="px-2 py-1 flex items-center gap-1">
                      <Input
                        className={`bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 ${
                          fieldErrors[idx] ? "border-red-500" : ""
                        }`}
                        placeholder="field_name"
                        value={field.name}
                        onChange={(e) =>
                          handleFieldChange(idx, "name", e.target.value)
                        }
                      />
                      {field.required && (
                        <Asterisk className="w-3 h-3 text-red-500" />
                      )}
                      {fieldErrors[idx] && (
                        <div className="flex items-center gap-1 text-xs text-red-500 mt-1">
                          <AlertCircle className="w-3 h-3" /> {fieldErrors[idx]}
                        </div>
                      )}
                    </td>
                    <td className="px-2 py-1">
                      <div className="flex items-center gap-2">
                        <Select
                          value={field.type}
                          onValueChange={(val) =>
                            handleFieldChange(idx, "type", val)
                          }
                        >
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder="Type" />
                          </SelectTrigger>
                          <SelectContent className="bg-white text-slate-900 dark:bg-slate-800 dark:text-slate-100">
                            {FIELD_TYPES.map((t) => (
                              <SelectItem key={t.value} value={t.value} className="hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer focus:bg-slate-100 dark:focus:bg-slate-700">
                                {t.label}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        {field.type === 'relationship' && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => openRelationshipModal(idx)}
                            className="text-xs"
                          >
                            {getFieldRelationship(field.name) ? 'Edit' : 'Setup'}
                          </Button>
                        )}
                      </div>
                    </td>
                    <td className="px-2 py-1 text-center">
                      <Checkbox
                        checked={field.required}
                        onCheckedChange={(val) =>
                          handleFieldChange(idx, "required", val)
                        }
                      />
                    </td>
                    <td className="px-2 py-1 text-center">
                      <SelectMultiple
                        options={SHOW_IN_OPTIONS}
                        value={field.showIn}
                        onChange={(val) =>
                          handleFieldChange(idx, "showIn", val)
                        }
                      />
                    </td>
                    <td className="px-2 py-1 text-center">
                      <Button variant="ghost" size="icon" onClick={() => removeField(idx)} aria-label="Remove field">
                        <Trash2 className="w-4 h-4 text-red-400" />
                      </Button>
                    </td>
                  </tr>
                  {field.type === 'relationship' && getFieldRelationship(field.name) && (
                    <tr className="bg-blue-50 dark:bg-blue-900/20">
                      <td colSpan={5} className="px-2 py-2 text-xs">
                        <div className="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                          <span className="font-semibold">Relationship:</span>
                          <span>{getFieldRelationship(field.name)?.name}</span>
                          <span className="text-slate-500">→</span>
                          <span>{RELATIONSHIP_TYPES.find(t => t.value === getFieldRelationship(field.name)?.type)?.label}</span>
                          <span className="text-slate-500">→</span>
                          <span>{getFieldRelationship(field.name)?.relatedModel}</span>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                              const rel = getFieldRelationship(field.name);
                              if (rel) {
                                removeFieldRelationship(field.name);
                              }
                            }}
                            className="ml-auto text-red-500 hover:text-red-700"
                          >
                            <Trash2 className="w-3 h-3" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  )}
                  {expanded === idx && (
                    <tr className="bg-slate-50 dark:bg-slate-800/60">
                      <td
                        colSpan={5}
                        className="p-3 border-t border-slate-200 dark:border-slate-700"
                      >
                        <div className="flex flex-wrap gap-4 items-center">
                          <div className="flex items-center gap-2">
                            <span className="text-xs text-slate-500">
                              Unique
                            </span>
                            <Switch
                              checked={field.unique}
                              onCheckedChange={(val) =>
                                handleFieldChange(idx, "unique", val)
                              }
                              className="data-[state=unchecked]:bg-slate-300 dark:data-[state=unchecked]:bg-slate-700"
                            />
                          </div>
                          <div className="flex items-center gap-2">
                            <span className="text-xs text-slate-500">
                              Default
                            </span>
                            <Input
                              className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-400"
                              placeholder="default"
                              value={field.default}
                              onChange={(e) =>
                                handleFieldChange(
                                  idx,
                                  "default",
                                  e.target.value
                                )
                              }
                              style={{ width: 100 }}
                            />
                          </div>
                          {isNumericType(field.type) && (
                            <>
                              <div className="flex items-center gap-2">
                                <span className="text-xs text-slate-500">
                                  Min
                                </span>
                                <Input
                                  type="number"
                                  className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-400"
                                  placeholder="min"
                                  value={field.min}
                                  onChange={(e) =>
                                    handleFieldChange(
                                      idx,
                                      "min",
                                      e.target.value
                                    )
                                  }
                                  style={{ width: 70 }}
                                />
                              </div>
                              <div className="flex items-center gap-2">
                                <span className="text-xs text-slate-500">
                                  Max
                                </span>
                                <Input
                                  type="number"
                                  className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-400"
                                  placeholder="max"
                                  value={field.max}
                                  onChange={(e) =>
                                    handleFieldChange(
                                      idx,
                                      "max",
                                      e.target.value
                                    )
                                  }
                                  style={{ width: 70 }}
                                />
                              </div>
                            </>
                          )}
                          {field.type === "enum" && (
                            <div className="flex items-center gap-2">
                              <span className="text-xs text-slate-500">
                                Enum Values
                              </span>
                              <Input
                                className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-400"
                                placeholder="val1, val2, ..."
                                value={field.enumValues}
                                onChange={(e) =>
                                  handleFieldChange(
                                    idx,
                                    "enumValues",
                                    e.target.value
                                  )
                                }
                                style={{ width: 180 }}
                              />
                            </div>
                          )}
                          <Button
                            variant="destructive"
                            size="sm"
                            onClick={() => removeField(idx)}
                          >
                            <Trash2 className="w-4 h-4 mr-1" /> Delete
                          </Button>
                        </div>
                      </td>
                    </tr>
                  )}
                </React.Fragment>
              ))}
            </tbody>
          </table>
        </div>
      </section>


      <section className="mb-4">
        <button
          type="button"
          className="flex items-center gap-2 text-sm font-semibold mb-1 text-primary-700 dark:text-primary-300 hover:underline focus:outline-none"
          onClick={() => setPreviewOpen((o) => !o)}
        >
          {previewOpen ? (
            <ChevronDown className="w-4 h-4" />
          ) : (
            <ChevronRight className="w-4 h-4" />
          )}
          Preview
        </button>
        {previewOpen && <PreviewGenerator fields={fields} />}
      </section>
      {/* Frontend UI */}
      <section className="mb-4 flex items-center gap-4">
        <span className="text-sm font-semibold">Frontend UI:</span>
        <label className="flex items-center gap-1">
          <input
            type="radio"
            checked={frontend === "vue"}
            onChange={() => setFrontend("vue")}
          />
          <span>Vue</span>
        </label>
        <label className="flex items-center gap-1">
          <input
            type="radio"
            checked={frontend === "react"}
            onChange={() => setFrontend("react")}
          />
          <span>React</span>
        </label>
        {/* <label className="flex items-center gap-1">
          <input type="radio" checked={frontend === 'both'} onChange={() => setFrontend('both')} />
          <span>Both</span>
        </label> */}
      </section>

      {previewEnabled && <PreviewGenerator fields={fields} />}
      <div className="mb-6 flex gap-8 flex-wrap">
        <div className="flex items-center gap-2">
          <Switch
            checked={options.permissions}
            onCheckedChange={(val) => handleOptionChange("permissions", val)}
            className="data-[state=unchecked]:bg-slate-300 dark:data-[state=unchecked]:bg-slate-700"
          />
          <span className="text-sm">Generate Permissions</span>
        </div>
        <div className="flex items-center gap-2">
          <Switch
            checked={options.translations}
            onCheckedChange={(val) => handleOptionChange("translations", val)}
            className="data-[state=unchecked]:bg-slate-300 dark:data-[state=unchecked]:bg-slate-700"
          />
          <span className="text-sm">Generate Translations</span>
        </div>
      </div>

      {/* Code Preview Panel */}
      {generatedFiles.length > 0 && (
        <div className="mt-10">
          <div className="flex gap-2 border-b border-slate-300 dark:border-slate-700 mb-2">
            {generatedFiles.map((file, idx) => (
              <button
                key={file.name}
                className={`px-3 py-1 rounded-t font-mono text-xs ${
                  activeFile === idx
                    ? "bg-slate-200 dark:bg-slate-800 text-primary-700 dark:text-primary-300"
                    : "bg-slate-100 dark:bg-slate-900 text-slate-500"
                }`}
                onClick={() => setActiveFile(idx)}
              >
                {file.name}
              </button>
            ))}
          </div>
          <div
            className="relative bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-b p-4 overflow-auto"
            style={{ minHeight: 200 }}
          >
            <pre
              ref={codeRef}
              className="whitespace-pre-wrap text-xs select-text"
            >
              {generatedFiles[activeFile].content}
            </pre>
            <div className="absolute top-2 right-2 flex gap-2">
              <Button size="sm" variant="outline" onClick={handleCopy}>
                Copy
              </Button>
              <Button size="sm" variant="outline" onClick={handleDownload}>
                Download
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Relationship Modal */}
      {relationshipModalOpen && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={() => setRelationshipModalOpen(false)}>
          <div className="bg-white dark:bg-slate-900 rounded-lg shadow-xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
            <h2 className="text-xl font-semibold mb-4 text-slate-900 dark:text-slate-100">Add Relationship</h2>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Name</label>
                <Input
                  className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100"
                  value={tempRelationship.name}
                  onChange={(e) => setTempRelationship({ ...tempRelationship, name: e.target.value })}
                  placeholder="e.g. user, category"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Type</label>
                  <Select
                    value={tempRelationship.type}
                    onValueChange={(val: any) => setTempRelationship({ ...tempRelationship, type: val })}
                  >
                    <SelectTrigger className="w-full bg-white dark:bg-slate-900">
                      <SelectValue placeholder="Select type" />
                    </SelectTrigger>
                    <SelectContent className="bg-white text-slate-900 dark:bg-slate-800 dark:text-slate-100">
                      {RELATIONSHIP_TYPES.map((t) => (
                        <SelectItem key={t.value} value={t.value} className="hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer focus:bg-slate-100 dark:focus:bg-slate-700">
                          {t.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div>
                  <label className="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Related Model</label>
                  <Select
                    value={tempRelationship.relatedModel && availableModels.includes(tempRelationship.relatedModel) ? tempRelationship.relatedModel : ""}
                    onValueChange={(val) => setTempRelationship({ ...tempRelationship, relatedModel: val })}
                    disabled={loadingModels}
                  >
                    <SelectTrigger className="w-full bg-white dark:bg-slate-900">
                      {loadingModels ? (
                        <div className="flex items-center gap-2">
                          <Loader2 className="w-4 h-4 animate-spin" />
                          <span className="text-slate-500">Loading models...</span>
                        </div>
                      ) : (
                        <SelectValue placeholder="Select model..." />
                      )}
                    </SelectTrigger>
                    <SelectContent className="bg-white text-slate-900 dark:bg-slate-800 dark:text-slate-100 max-h-[300px]">
                      {loadingModels ? (
                        <div className="px-2 py-1.5 text-sm text-slate-500 dark:text-slate-400 flex items-center gap-2">
                          <Loader2 className="w-4 h-4 animate-spin" />
                          <span>Loading models...</span>
                        </div>
                      ) : availableModels.length > 0 ? (
                        availableModels.map((modelName) => (
                          <SelectItem key={modelName} value={modelName} className="hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer focus:bg-slate-100 dark:focus:bg-slate-700">
                            {modelName}
                          </SelectItem>
                        ))
                      ) : (
                        <div className="px-2 py-1.5 text-sm text-slate-500 dark:text-slate-400">
                          No models found
                        </div>
                      )}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              {(tempRelationship.type === 'belongsTo' || tempRelationship.type === 'hasOne' || tempRelationship.type === 'hasMany') && (
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Foreign Key</label>
                    <Input
                      className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100"
                      value={tempRelationship.foreignKey || ''}
                      onChange={(e) => setTempRelationship({ ...tempRelationship, foreignKey: e.target.value })}
                      placeholder={tempRelationship.type === 'belongsTo' ? 'e.g. user_id' : 'optional'}
                      disabled={tempRelationship.type === 'hasOne' || tempRelationship.type === 'hasMany'}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Local Key</label>
                    <Input
                      className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100"
                      value={tempRelationship.localKey || 'id'}
                      onChange={(e) => setTempRelationship({ ...tempRelationship, localKey: e.target.value })}
                      placeholder="id"
                    />
                  </div>
                </div>
              )}

              {tempRelationship.type === 'belongsToMany' && (
                <>
                  <div>
                    <label className="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Pivot Table</label>
                    <Input
                      className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100"
                      value={tempRelationship.pivotTable || ''}
                      onChange={(e) => setTempRelationship({ ...tempRelationship, pivotTable: e.target.value })}
                      placeholder="e.g. category_product"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Foreign Pivot Key</label>
                    <Input
                      className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100"
                      value={tempRelationship.foreignPivotKey || ''}
                      onChange={(e) => setTempRelationship({ ...tempRelationship, foreignPivotKey: e.target.value })}
                      placeholder="e.g. category_id"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Related Pivot Key</label>
                    <Input
                      className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100"
                      value={tempRelationship.relatedPivotKey || ''}
                      onChange={(e) => setTempRelationship({ ...tempRelationship, relatedPivotKey: e.target.value })}
                      placeholder="e.g. product_id"
                    />
                  </div>
                  <div className="flex items-center gap-2">
                    <label className="text-sm font-medium text-slate-700 dark:text-slate-300">With Timestamps</label>
                    <Switch
                      checked={tempRelationship.withTimestamps || false}
                      onCheckedChange={(val) => setTempRelationship({ ...tempRelationship, withTimestamps: val })}
                      className="data-[state=unchecked]:bg-slate-300 dark:data-[state=unchecked]:bg-slate-700"
                    />
                  </div>
                </>
              )}

              {/* Preview Code Section */}
              <div className="mt-6 border-t border-slate-200 dark:border-slate-700 pt-4">
                <div className="flex items-center justify-between mb-3">
                  <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300">Preview Code (معاينة الكود)</label>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      const code = generateRelationshipCode(tempRelationship);
                      navigator.clipboard.writeText(code);
                    }}
                    className="text-xs"
                  >
                    Copy
                  </Button>
                </div>
                <div className="bg-slate-900 dark:bg-slate-950 rounded-lg p-4 overflow-x-auto border border-slate-700">
                  <pre className="text-xs text-slate-100 font-mono whitespace-pre-wrap leading-relaxed">
                    {generateRelationshipCode(tempRelationship)}
                  </pre>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-2 mt-6">
              <Button
                variant="outline"
                onClick={() => {
                  setRelationshipModalOpen(false);
                  setCurrentFieldIndex(null);
                }}
              >
                Cancel
              </Button>
              <Button onClick={saveRelationship}>
                Save
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
