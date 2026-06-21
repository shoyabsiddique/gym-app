import { useEffect, useState } from 'react';
import { toast } from 'react-hot-toast';
import api from '../../lib/api';
import { Card } from '../../components/ui/Card';
import { Button } from '../../components/ui/Button';
import { Badge } from '../../components/ui/Badge';
import type { AiJob, AiJobStatus } from '../../types';

const statusVariant: Record<AiJobStatus, 'success' | 'info' | 'warning' | 'danger' | 'neutral'> = {
  done:       'success',
  processing: 'info',
  pending:    'warning',
  failed:     'danger',
};

export function AdminAiJobs() {
  const [jobs, setJobs] = useState<AiJob[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('');

  const load = (status = '') =>
    api.get('/ai-jobs', { params: status ? { status } : {} })
      .then(r => setJobs(r.data.data ?? r.data))
      .finally(() => setLoading(false));

  useEffect(() => { load(filter); }, [filter]);

  const retry = async (job: AiJob) => {
    try {
      const r = await api.post(`/ai-jobs/${job.id}/retry`);
      toast.success('Job queued for retry');
      setJobs(prev => prev.map(j => j.id === job.id ? r.data.job : j));
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Retry failed');
    }
  };

  const stats = jobs.reduce((acc, j) => {
    acc[j.status] = (acc[j.status] || 0) + 1;
    return acc;
  }, {} as Record<string, number>);

  return (
    <div className="max-w-5xl mx-auto px-4 py-6">
      <h1 className="text-xl font-bold text-white mb-6">AI Nutrition Jobs</h1>

      {/* Stats */}
      <div className="grid grid-cols-4 gap-3 mb-6">
        {[
          { key: 'done',       label: 'Completed', color: 'text-emerald-400' },
          { key: 'processing', label: 'Processing', color: 'text-blue-400' },
          { key: 'pending',    label: 'Pending',    color: 'text-yellow-400' },
          { key: 'failed',     label: 'Failed',     color: 'text-red-400' },
        ].map(({ key, label, color }) => (
          <Card key={key} className="text-center">
            <div className={`text-2xl font-bold ${color}`}>{stats[key] || 0}</div>
            <div className="text-xs text-gray-400 mt-1">{label}</div>
          </Card>
        ))}
      </div>

      {/* Filter */}
      <div className="flex gap-2 mb-4">
        {['', 'pending', 'processing', 'done', 'failed'].map(s => (
          <button key={s} onClick={() => setFilter(s)}
            className={`px-3 py-1.5 rounded-lg text-sm transition-colors ${
              filter === s ? 'bg-emerald-700 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
            }`}>
            {s || 'All'}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="text-center py-12 text-gray-400">Loading...</div>
      ) : jobs.length === 0 ? (
        <Card className="text-center py-10">
          <p className="text-gray-500">No jobs found.</p>
        </Card>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-700">
                {['Dish', 'Status', 'Error', 'Created', 'Action'].map(h => (
                  <th key={h} className="text-left py-2 px-3 text-xs font-semibold text-gray-400 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-800">
              {jobs.map(job => (
                <tr key={job.id} className="hover:bg-gray-800/50">
                  <td className="py-2.5 px-3 text-gray-200">{job.dish?.name || `Dish #${job.dish_id}`}</td>
                  <td className="py-2.5 px-3">
                    <Badge variant={statusVariant[job.status]}>{job.status}</Badge>
                  </td>
                  <td className="py-2.5 px-3 text-xs text-red-400 max-w-xs truncate">{job.error || '—'}</td>
                  <td className="py-2.5 px-3 text-gray-500 text-xs">
                    {new Date((job as any).created_at).toLocaleDateString()}
                  </td>
                  <td className="py-2.5 px-3">
                    {job.status !== 'done' && (
                      <Button size="sm" variant="secondary" onClick={() => retry(job)}>Retry</Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
