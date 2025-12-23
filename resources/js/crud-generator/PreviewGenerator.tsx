import React from 'react';
import type { CrudField } from './types';

interface PreviewGeneratorProps {
  fields: CrudField[];
}

export default function PreviewGenerator({ fields }: PreviewGeneratorProps) {
  return (
    <div className="mt-6 p-4 rounded bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700">
      <h3 className="font-semibold mb-2 text-sm text-slate-700 dark:text-slate-200">Preview (Human Preferences)</h3>
      <ul className="space-y-2 text-xs">
        {fields.map((field, idx) => (
          <li key={idx} className="">
            <span className="font-mono font-bold text-slate-900 dark:text-slate-100">{field.name}</span>
            {field.required && <span className="ml-2 text-red-500">(required)</span>}
            <span className="ml-2 text-slate-500">type: {field.type}</span>
            <span className="ml-2">show in: {field.showIn.join(', ')}</span>
            {field.unique && <span className="ml-2 text-blue-500">unique</span>}
            {field.default && <span className="ml-2 text-green-600">default: {field.default}</span>}
            {field.enumValues && <span className="ml-2 text-purple-500">enum: {field.enumValues}</span>}
          </li>
        ))}
      </ul>
    </div>
  );
}
