import React, { useState } from 'react';
import { Input } from "@/components/ui/input";

const DEFAULT_PATHS = {
  'Vue/React': 'resources/js/_dashboard/pages/',
  Model: 'app/Models/',
  Controller: 'app/Http/Controllers/',
  Service: 'app/Services/',
  Migration: 'database/migrations/',
  Seeder: 'database/seeders/',
  Resource: 'app/Http/Resources/',
  Lang: 'resources/lang/',
  View: 'resources/views/',
  Types: 'resources/js/_dashboard/types/',
};

const BACKEND_FILES = [
  { key: 'model', label: 'Model' },
  { key: 'migration', label: 'Migration' },
  { key: 'controller', label: 'Controller' },
  { key: 'request', label: 'Request' },
  { key: 'resource', label: 'Resource' },
  { key: 'seeder', label: 'Seeder' },
  { key: 'factory', label: 'Factory' },
  { key: 'policy', label: 'Policy' },
  { key: 'routes', label: 'Routes' },
  { key: 'lang', label: 'Lang' },
];

const VUE_FILES = [
  { key: 'list_page', label: 'List Page (Users.vue)' },
  { key: 'table_component', label: 'Table Component (table.vue)' },
  { key: 'form_component', label: 'Form Component (form.vue)' },
  { key: 'single_page', label: 'Single Page (optional)' },
  { key: 'types', label: 'TypeScript Types' },
  { key: 'routes', label: 'Vue Router Routes' },
];

const DEFAULT_BACKEND_FILES = ['model', 'migration', 'controller', 'request', 'resource'];
const DEFAULT_VUE_FILES = ['list_page', 'table_component', 'form_component', 'types', 'routes'];

interface SettingsTabProps {
  outputBasePath: string;
  setOutputBasePath: (path: string) => void;
  backendFiles: string[];
  setBackendFiles: (files: string[]) => void;
  vueFiles: string[];
  setVueFiles: (files: string[]) => void;
  generateUI: boolean;
  setGenerateUI: (value: boolean) => void;
}

export default function SettingsTab({
  outputBasePath,
  setOutputBasePath,
  backendFiles,
  setBackendFiles,
  vueFiles,
  setVueFiles,
  generateUI,
  setGenerateUI,
}: SettingsTabProps) {
  const [paths, setPaths] = useState(DEFAULT_PATHS);

  const handlePathChange = (key: string, value: string) => {
    setPaths(prev => ({ ...prev, [key]: value }));
  };

  return (
    <div className="space-y-6">
      {/* Backend Files Section */}
      <div>
        <label className="block text-sm font-semibold mb-2">Generated Backend Files</label>
        <div className="flex flex-wrap gap-4">
          {BACKEND_FILES.map(f => (
            <label key={f.key} className="flex items-center gap-1 text-xs cursor-pointer">
              <input
                type="checkbox"
                checked={backendFiles.includes(f.key)}
                onChange={e => setBackendFiles(files => e.target.checked ? [...files, f.key] : files.filter(k => k !== f.key))}
              />
              <span>{f.label}</span>
            </label>
          ))}
        </div>
      </div>

      {/* UI Files Option */}
      <div className="flex items-center gap-2 mt-4">
        <input
          type="checkbox"
          checked={generateUI}
          onChange={e => setGenerateUI(e.target.checked)}
          id="generate-ui"
        />
        <label htmlFor="generate-ui" className="text-sm font-semibold">
          Generate UI Files (Vue)
        </label>
      </div>

      {/* Vue Files Options - shown only when generateUI is true */}
      {generateUI && (
        <div className="bg-slate-50 dark:bg-slate-800/20 p-4 rounded border border-slate-200 dark:border-slate-700">
          <label className="block text-sm font-semibold mb-2">Vue Files to Generate</label>
          <div className="flex flex-wrap gap-4">
            {VUE_FILES.map(f => (
              <label key={f.key} className="flex items-center gap-1 text-xs cursor-pointer">
                <input
                  type="checkbox"
                  checked={vueFiles.includes(f.key)}
                  onChange={e => setVueFiles(files => e.target.checked ? [...files, f.key] : files.filter(k => k !== f.key))}
                />
                <span>{f.label}</span>
              </label>
            ))}
          </div>
        </div>
      )}

      <div>
        <label className="block text-sm font-semibold mb-1">
          Output Base Path (اختياري)
          <span className="text-xs text-slate-500 ml-2 font-normal">
            (افتراضي: مسار الحزمة الحالي)
          </span>
        </label>
        <Input
          className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-400"
          placeholder="e.g. G:\\code\\laravel\\L12\\lang-app"
          value={outputBasePath}
          onChange={e => setOutputBasePath(e.target.value)}
        />
        <p className="text-xs text-slate-500 mt-1">
          اتركه فارغاً لتوليد الملفات في المشروع الحالي، أو أدخل مسار مشروع آخر
        </p>
      </div>
      <div>
        <label className="block text-sm font-semibold mb-2">File Output Paths</label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {Object.entries(paths).map(([type, path]) => (
            <div key={type}>
              <span className="block text-xs text-slate-500 mb-1">{type}</span>
              <Input
                className="bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-400"
                value={path}
                onChange={e => handlePathChange(type, e.target.value)}
              />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
