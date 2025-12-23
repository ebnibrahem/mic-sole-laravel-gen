import React, { useState, useEffect } from 'react';
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Clock, Trash2, RotateCcw, FileText, Calendar, Package, AlertCircle } from 'lucide-react';

interface Generation {
  id: string;
  timestamp: string;
  model: string | null;
  command: string;
  files_count: number;
  files?: Array<{
    type: string;
    path: string;
    relative_path?: string;
  }>;
  metadata?: {
    backend_files?: string[];
    vue_files?: string[];
    fields_count?: number;
  };
}

interface Stats {
  total_generations: number;
  total_files: number;
  models: Record<string, number>;
  last_generation: Generation | null;
}

export default function HistoryTab() {
  const [history, setHistory] = useState<Generation[]>([]);
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [selectedGen, setSelectedGen] = useState<Generation | null>(null);
  const [rollbackLevel, setRollbackLevel] = useState(1);
  const [rollingBack, setRollingBack] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const fetchHistory = async () => {
    setLoading(true);
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const res = await fetch('/generator/history?limit=50', {
        headers: {
          'X-CSRF-TOKEN': token || '',
        },
      });
      const data = await res.json();
      if (res.ok && data.status === 'success') {
        setHistory(data.history || []);
      }
    } catch (e) {
      console.error('Failed to fetch history:', e);
    } finally {
      setLoading(false);
    }
  };

  const fetchStats = async () => {
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const res = await fetch('/generator/stats', {
        headers: {
          'X-CSRF-TOKEN': token || '',
        },
      });
      const data = await res.json();
      if (res.ok && data.status === 'success') {
        setStats(data.stats);
      }
    } catch (e) {
      console.error('Failed to fetch stats:', e);
    }
  };

  const fetchGenerationDetails = async (id: string) => {
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const res = await fetch(`/generator/generation/${id}`, {
        headers: {
          'X-CSRF-TOKEN': token || '',
        },
      });
      const data = await res.json();
      if (res.ok && data.status === 'success') {
        setSelectedGen(data.generation);
      }
    } catch (e) {
      console.error('Failed to fetch generation details:', e);
    }
  };

  const handleRollback = async (id?: string, level: number = 1) => {
    if (!confirm(`Are you sure you want to rollback ${level} level(s)? This will delete generated files and restore updated files to their original state.`)) {
      return;
    }

    setRollingBack(true);
    setMessage(null);
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const res = await fetch('/generator/rollback', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token || '',
        },
        body: JSON.stringify({
          id: id || undefined,
          level,
        }),
      });
      const data = await res.json();
      if (res.ok && data.status === 'success') {
        setMessage({ type: 'success', text: data.message || 'Rollback completed successfully!' });
        await fetchHistory();
        await fetchStats();
        setSelectedGen(null);
      } else {
        setMessage({ type: 'error', text: data.message || 'Rollback failed' });
      }
    } catch (e) {
      setMessage({ type: 'error', text: 'Network error occurred' });
    } finally {
      setRollingBack(false);
    }
  };

  const handleClearHistory = async () => {
    if (!confirm('Are you sure you want to clear all generation history? This action cannot be undone.')) {
      return;
    }

    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const res = await fetch('/generator/history', {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': token || '',
        },
      });
      const data = await res.json();
      if (res.ok && data.status === 'success') {
        setMessage({ type: 'success', text: 'History cleared successfully!' });
        await fetchHistory();
        await fetchStats();
        setSelectedGen(null);
      } else {
        setMessage({ type: 'error', text: data.message || 'Failed to clear history' });
      }
    } catch (e) {
      setMessage({ type: 'error', text: 'Network error occurred' });
    }
  };

  useEffect(() => {
    fetchHistory();
    fetchStats();
  }, []);

  const formatDate = (timestamp: string) => {
    const date = new Date(timestamp);
    return date.toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="text-slate-500">Loading history...</div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Statistics Card */}
      {stats && (
        <Card className="p-4 bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-900 border-slate-200 dark:border-slate-700">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                <Package className="w-5 h-5 text-blue-600 dark:text-blue-300" />
              </div>
              <div>
                <div className="text-xs text-slate-500 dark:text-slate-400">Total Generations</div>
                <div className="text-lg font-bold text-slate-900 dark:text-slate-100">{stats.total_generations}</div>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <div className="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                <FileText className="w-5 h-5 text-green-600 dark:text-green-300" />
              </div>
              <div>
                <div className="text-xs text-slate-500 dark:text-slate-400">Total Files</div>
                <div className="text-lg font-bold text-slate-900 dark:text-slate-100">{stats.total_files}</div>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <div className="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                <Calendar className="w-5 h-5 text-purple-600 dark:text-purple-300" />
              </div>
              <div>
                <div className="text-xs text-slate-500 dark:text-slate-400">Models</div>
                <div className="text-lg font-bold text-slate-900 dark:text-slate-100">{Object.keys(stats.models || {}).length}</div>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <div className="p-2 bg-orange-100 dark:bg-orange-900 rounded-lg">
                <Clock className="w-5 h-5 text-orange-600 dark:text-orange-300" />
              </div>
              <div>
                <div className="text-xs text-slate-500 dark:text-slate-400">Last Generation</div>
                <div className="text-sm font-semibold text-slate-900 dark:text-slate-100">
                  {stats.last_generation ? formatDate(stats.last_generation.timestamp) : 'N/A'}
                </div>
              </div>
            </div>
          </div>
        </Card>
      )}

      {/* Message */}
      {message && (
        <div className={`p-3 rounded-lg flex items-center gap-2 ${
          message.type === 'success'
            ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800'
            : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800'
        }`}>
          {message.type === 'success' ? (
            <span className="text-green-600 dark:text-green-400">✓</span>
          ) : (
            <AlertCircle className="w-4 h-4" />
          )}
          <span className="text-sm">{message.text}</span>
        </div>
      )}

      {/* Actions */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Button
            onClick={() => fetchHistory()}
            variant="outline"
            size="sm"
            className="text-xs"
          >
            Refresh
          </Button>
          <Button
            onClick={handleClearHistory}
            variant="outline"
            size="sm"
            className="text-xs text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
          >
            <Trash2 className="w-3 h-3 mr-1" />
            Clear History
          </Button>
        </div>
        <div className="flex items-center gap-2">
          <label className="text-xs text-slate-600 dark:text-slate-400">Rollback Level:</label>
          <input
            type="number"
            min="1"
            value={rollbackLevel}
            onChange={(e) => setRollbackLevel(parseInt(e.target.value) || 1)}
            className="w-16 px-2 py-1 text-xs border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-800"
          />
          <Button
            onClick={() => handleRollback(undefined, rollbackLevel)}
            disabled={rollingBack || history.length === 0}
            size="sm"
            className="text-xs bg-orange-600 hover:bg-orange-700"
          >
            <RotateCcw className={`w-3 h-3 mr-1 ${rollingBack ? 'animate-spin' : ''}`} />
            Rollback {rollbackLevel}
          </Button>
        </div>
      </div>

      {/* History List */}
      {history.length === 0 ? (
        <Card className="p-8 text-center">
          <Clock className="w-12 h-12 mx-auto mb-4 text-slate-400" />
          <div className="text-slate-500 dark:text-slate-400">No generation history found</div>
        </Card>
      ) : (
        <div className="space-y-2">
          {history.map((gen, index) => (
            <Card
              key={gen.id}
              className={`p-4 cursor-pointer transition-all hover:shadow-md border ${
                selectedGen?.id === gen.id
                  ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                  : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'
              }`}
              onClick={() => {
                if (selectedGen?.id === gen.id) {
                  setSelectedGen(null);
                } else {
                  fetchGenerationDetails(gen.id);
                }
              }}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-4 flex-1">
                  <div className="flex items-center justify-center w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300">
                    {history.length - index}
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="font-semibold text-slate-900 dark:text-slate-100">
                        {gen.model || 'N/A'}
                      </span>
                      <span className="text-xs px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400">
                        {gen.command}
                      </span>
                    </div>
                    <div className="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                      <span className="flex items-center gap-1">
                        <Clock className="w-3 h-3" />
                        {formatDate(gen.timestamp)}
                      </span>
                      <span className="flex items-center gap-1">
                        <FileText className="w-3 h-3" />
                        {gen.files_count} file(s)
                      </span>
                      <span className="text-xs font-mono text-slate-400 dark:text-slate-500">
                        {gen.id.substring(0, 12)}...
                      </span>
                    </div>
                  </div>
                </div>
                <Button
                  onClick={(e) => {
                    e.stopPropagation();
                    handleRollback(gen.id, 1);
                  }}
                  disabled={rollingBack}
                  size="sm"
                  variant="outline"
                  className="text-xs text-orange-600 hover:text-orange-700 dark:text-orange-400 dark:hover:text-orange-300"
                >
                  <RotateCcw className="w-3 h-3 mr-1" />
                  Rollback
                </Button>
              </div>

              {/* Expanded Details */}
              {selectedGen?.id === gen.id && (
                <div className="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                  <div className="space-y-3">
                    {gen.metadata && (
                      <div>
                        <div className="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Metadata:</div>
                        <div className="text-xs text-slate-600 dark:text-slate-400 space-y-1">
                          {gen.metadata.backend_files && (
                            <div>Backend Files: {gen.metadata.backend_files.join(', ')}</div>
                          )}
                          {gen.metadata.vue_files && (
                            <div>Vue Files: {gen.metadata.vue_files.join(', ')}</div>
                          )}
                          {gen.metadata.fields_count !== undefined && (
                            <div>Fields Count: {gen.metadata.fields_count}</div>
                          )}
                        </div>
                      </div>
                    )}
                    {gen.files && gen.files.length > 0 && (
                      <div>
                        <div className="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Generated Files:</div>
                        <div className="max-h-48 overflow-y-auto space-y-1">
                          {gen.files.map((file, idx) => (
                            <div key={idx} className="text-xs font-mono text-slate-600 dark:text-slate-400 p-2 bg-slate-50 dark:bg-slate-800 rounded">
                              <span className="font-semibold text-slate-700 dark:text-slate-300">{file.type}:</span> {file.path}
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              )}
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
