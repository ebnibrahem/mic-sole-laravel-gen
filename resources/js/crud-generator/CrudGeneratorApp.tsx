import React, { useState, useRef } from 'react';
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Sun, Moon } from 'lucide-react';
import GeneratorTab from "./GeneratorTab";
import SettingsTab from './SettingsTab';
import OtherFeaturesTab from './OtherFeaturesTab';
import HistoryTab from './HistoryTab';
import Font from './Font';
import type { CrudField, CrudRelationship, GeneratorOptions } from './types';

const TABS = [
  { label: 'Generator' },
  { label: 'Settings' },
  { label: 'Other Features' },
  { label: 'History' },
];

 const FONT_LIST = [
   "MonoLisa",
   "MonoLisa Light",
   "Melon",
   "ginto",
   "Cascadia Code PL",
   "Fira Code iScript",
   "Dank Mono",
   "DM mono",
   "JetBrains Mono light",
   "Cascadia Code Light",
   "Dejavu Sans Mono",
   "Monolisa light",
   "monospace",
 ];

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

const UI_FILES = [
  'vuePage',
  'vueTable',
  'vueForm',
  'reactPage',
  'reactTable',
  'reactForm',
];

const DEFAULT_BACKEND_FILES = ['model', 'migration', 'controller', 'request', 'resource'];

export default function CrudGeneratorApp() {
  const [dark, setDark] = useState(() => {
    const stored = localStorage.getItem('crudgen_dark');
    return stored ? stored === 'true' : false;
  });
  const [activeTab, setActiveTab] = useState(0);
  const [font, setFont] = useState(() => {
    const stored = localStorage.getItem('crudgen_font');
    return stored || FONT_LIST[0];
  });
  const [fields, setFields] = useState<CrudField[]>(JSON.parse(localStorage.getItem('crudgen_fields') || '[]'));
  const [relationships, setRelationships] = useState<CrudRelationship[]>(JSON.parse(localStorage.getItem('crudgen_relationships') || '[]'));
  const [options, setOptions] = useState<GeneratorOptions>({ permissions: true, translations: true });
  const [model, setModel] = useState('Post');
  const [frontend, setFrontend] = useState<'vue' | 'react' | 'both'>(() => {
    const stored = localStorage.getItem('crudgen_frontend');
    return stored === 'react' || stored === 'both' ? stored : 'vue';
  });
  const [backendFiles, setBackendFiles] = useState<string[]>(DEFAULT_BACKEND_FILES);
  const [responseMsg, setResponseMsg] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [writtenFiles, setWrittenFiles] = useState<{ [key: string]: string } | null>(null);
  const [generateUI, setGenerateUI] = useState(false);
  const [outputBasePath, setOutputBasePath] = useState<string>(''); // المسار الخارجي (افتراضي: فارغ = مسار الحزمة)
  const [vueFiles, setVueFiles] = useState<string[]>([]);

  const codeFontStyle = {
    fontFamily: `${font}, ${FONT_LIST.filter(f => f !== font).join(', ')}`
  };

  // Export state as JSON
  const handleExport = () => {
    const data = JSON.stringify({ fields, relationships, options, model }, null, 2);
    const blob = new Blob([data], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'crud-generator-state.json';
    a.click();
    URL.revokeObjectURL(url);
  };

  // Import state from JSON
  const handleImport = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (event) => {
      try {
        const data = JSON.parse(event.target?.result as string);
        if (Array.isArray(data.fields) && typeof data.model === 'string') {
          setFields(data.fields);
          setRelationships(data.relationships || []);
          setOptions(data.options || { permissions: true, translations: true });
          setModel(data.model);
        }
      } catch {}
    };
    reader.readAsText(file);
  };

  // Persist fields in localStorage
  React.useEffect(() => {
    localStorage.setItem('crudgen_fields', JSON.stringify(fields));
  }, [fields]);

  // Persist relationships in localStorage
  React.useEffect(() => {
    localStorage.setItem('crudgen_relationships', JSON.stringify(relationships));
  }, [relationships]);

  React.useEffect(() => {
    if (dark) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    localStorage.setItem('crudgen_dark', String(dark));
  }, [dark]);

  React.useEffect(() => {
    localStorage.setItem('crudgen_font', font);
  }, [font]);

  React.useEffect(() => {
    localStorage.setItem('crudgen_frontend', frontend);
  }, [frontend]);

  // Parse 422 validation error message
  const parse422Message = (errorData: any): string => {
    const errors = errorData.message;

    if (!errors) {
      return 'Validation error occurred.';
    }

    if (typeof errors === 'string') {
      return errors;
    }

    if (typeof errors === 'object') {
      const messages: string[] = [];
      Object.keys(errors).forEach((key) => {
        if (Array.isArray(errors[key]) && errors[key].length > 0) {
          messages.push(errors[key][0]);
        } else if (typeof errors[key] === 'string') {
          messages.push(errors[key]);
        }
      });
      return messages.join(', ') || 'Validation error occurred.';
    }

    return 'Validation error occurred.';
  };

  // Send data to backend
  const handleGenerate = async () => {
    // Validation before sending
    if (!model || model.trim() === '') {
      setResponseMsg('Please enter a model name.');
      return;
    }

    if (!fields || fields.length === 0) {
      setResponseMsg('Please add at least one field.');
      return;
    }

    if (!backendFiles || backendFiles.length === 0) {
      setResponseMsg('Please select at least one backend file to generate.');
      return;
    }

    setLoading(true);
    setResponseMsg(null);
    setWrittenFiles(null);
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      if (!token) {
        setResponseMsg('CSRF token not found. Please refresh the page.');
        setLoading(false);
        return;
      }

      // Filter out empty relationships (those without name or relatedModel)
      const validRelationships = (relationships || []).filter((rel: any) => {
        return rel && rel.name && rel.name.trim() !== '' && rel.relatedModel && rel.relatedModel.trim() !== '';
      });

      const requestData = {
        model: model.trim(),
        fields,
        relationships: validRelationships,
        backendFiles: backendFiles,
        vueFiles: generateUI ? vueFiles : [],
        options: options || {},
        outputBasePath: outputBasePath || undefined,
      };

      console.log('Sending generation request:', requestData);

      const res = await fetch('/api/generator/generate', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
        },
        body: JSON.stringify(requestData),
      });

      console.log('Response status:', res.status, res.statusText);

      const response = await res.json();
      console.log('Response data:', response);

      // Handle response (MicResponseTrait format)
      const { status, message, data, code } = response || {};

      // Handle success
      if (status === 'success') {
        setResponseMsg(message || 'Generation successful!');
        if (data?.written) setWrittenFiles(data.written);
        setLoading(false);
        return;
      }

      // Handle error (status === 'error')
      if (status === 'error') {
        let errorMessage: string;

        // Handle 422 validation errors
        if (code === 422 || code === '422') {
          errorMessage = parse422Message({ message });
        } else {
          // Other errors (non-422)
          errorMessage = typeof message === 'string' ? message : 'Error occurred.';
        }

        setResponseMsg(errorMessage);
        setLoading(false);
        return;
      }

      // Fallback for non-standard responses
      if (!res.ok) {
        setResponseMsg(`HTTP ${res.status}: ${res.statusText}`);
        setLoading(false);
        return;
      }

      // Default success
      setResponseMsg('Generation successful!');
      if (data?.written) setWrittenFiles(data.written);
    } catch (e: any) {
      console.error('Generation error:', e);
      setResponseMsg(`Network error: ${e.message || 'Failed to connect to server'}`);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      className={`min-h-screen bg-gradient-to-br from-slate-100 via-slate-200 to-slate-300 dark:from-slate-900 dark:via-slate-800 dark:to-slate-700 text-slate-900 dark:text-slate-100 transition-colors duration-300 flex flex-col items-center py-8 px-2`}
      style={codeFontStyle}
    >
      <Card className="w-full max-w-4xl shadow-2xl border border-slate-300 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 p-8">
        <div className="flex flex-col gap-4 mb-8">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <span className="text-2xl font-bold tracking-tight text-primary-400">⚡️ CRUD Generator</span>
              <span className="text-xs bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300 rounded px-2 py-0.5 ml-2">Modern UI</span>
            </div>
            <div className="flex items-center gap-4">
              <button onClick={handleExport} className="text-xs px-2 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600">Export</button>
              <label className="text-xs px-2 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 cursor-pointer">
                Import
                <input type="file" accept="application/json" onChange={handleImport} className="hidden" />
              </label>
              <Font font={font} setFont={setFont} fontList={FONT_LIST} dark={dark} setDark={setDark} />
            </div>
          </div>

         </div>
        <div className="flex gap-2 mb-6 border-b border-slate-300 dark:border-slate-700">
          {TABS.map((tab, idx) => (
            <button
              key={tab.label}
              className={`px-4 py-2 text-sm font-semibold rounded-t ${
                activeTab === idx
                  ? "bg-slate-200 dark:bg-slate-800 text-primary-700 dark:text-primary-300"
                  : "bg-slate-100 dark:bg-slate-900 text-slate-500"
              }`}
              onClick={() => setActiveTab(idx)}
            >
              {tab.label}
            </button>
          ))}
        </div>
        <div>
          {activeTab === 0 && (
            <>
              <GeneratorTab
                fields={fields}
                setFields={setFields}
                relationships={relationships}
                setRelationships={setRelationships}
                options={options}
                setOptions={setOptions}
                model={model}
                setModel={setModel}
                frontend={frontend}
                setFrontend={setFrontend}
              />
              <div className="mt-6 flex flex-col gap-4">
                <div className="flex items-center gap-4">
                  <Button onClick={handleGenerate} disabled={loading} className="px-8 py-2 text-base font-bold bg-primary">
                    {loading ? 'Generating...' : 'Generate'}
                  </Button>
                  {responseMsg && (
                    <div className={`text-sm ${typeof responseMsg === 'string' && responseMsg.toLowerCase().includes('success') ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'} max-w-2xl`}>
                      <pre className="whitespace-pre-wrap font-sans">{typeof responseMsg === 'string' ? responseMsg : String(responseMsg)}</pre>
                    </div>
                  )}
                </div>
                {writtenFiles && (
                  <div className="mt-2 p-3 rounded bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700">
                    <div className="font-semibold mb-2 text-sm text-slate-700 dark:text-slate-200">Generated Files:</div>
                    <ul className="text-xs space-y-1">
                      {Object.entries(writtenFiles).map(([type, path]) => (
                        <li key={type} className="flex items-center gap-2">
                          <span className="font-mono font-bold text-slate-900 dark:text-slate-100">{type}</span>
                          <span className="text-slate-500">→</span>
                          <span className="break-all">{path}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            </>
          )}
          {activeTab === 1 && (
            <SettingsTab
              outputBasePath={outputBasePath}
              setOutputBasePath={setOutputBasePath}
              backendFiles={backendFiles}
              setBackendFiles={setBackendFiles}
              vueFiles={vueFiles}
              setVueFiles={setVueFiles}
              generateUI={generateUI}
              setGenerateUI={setGenerateUI}
            />
          )}
          {activeTab === 2 && <OtherFeaturesTab />}
          {activeTab === 3 && <HistoryTab />}
        </div>
      </Card>
    </div>
  );
}
