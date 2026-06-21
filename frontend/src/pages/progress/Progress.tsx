import { useEffect, useState } from 'react';
import { toast } from 'react-hot-toast';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import api from '../../lib/api';
import { useAuthStore } from '../../store/authStore';
import { Card } from '../../components/ui/Card';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import type { WeightEntry } from '../../types';

export function Progress() {
  const { user } = useAuthStore();
  const [history, setHistory] = useState<WeightEntry[]>([]);
  const [newWeight, setNewWeight] = useState('');
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);

  const load = () =>
    api.get('/weight/history').then(r => setHistory(r.data)).finally(() => setLoading(false));

  useEffect(() => { load(); }, []);

  const handleAdd = async () => {
    if (!newWeight) return;
    setSaving(true);
    try {
      await api.post('/weight', { weight_kg: +newWeight });
      setNewWeight('');
      toast.success('Weight logged!');
      load();
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Failed to save');
    } finally {
      setSaving(false);
    }
  };

  const chartData = [...history]
    .reverse()
    .map(e => ({
      date:   new Date(e.recorded_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
      weight: +e.weight_kg,
    }));

  return (
    <div className="max-w-3xl mx-auto px-4 py-6">
      <h1 className="text-xl font-bold text-white mb-6">Progress Tracking</h1>

      {/* Log weight */}
      <Card className="mb-6">
        <h2 className="text-sm font-semibold text-gray-300 mb-3 uppercase tracking-wide">Log Today's Weight</h2>
        <div className="flex gap-3">
          <Input
            placeholder="75.5"
            type="number"
            step="0.1"
            value={newWeight}
            onChange={e => setNewWeight(e.target.value)}
            className="max-w-xs"
          />
          <Button onClick={handleAdd} loading={saving}>Log</Button>
        </div>
        {user?.target_weight && (
          <p className="mt-2 text-xs text-gray-400">
            Current: {user.weight_kg}kg → Target: {user.target_weight}kg
            {user.weight_kg && user.target_weight && (
              <span className="ml-2 text-emerald-400">
                ({(+user.weight_kg - +user.target_weight).toFixed(1)}kg to go)
              </span>
            )}
          </p>
        )}
      </Card>

      {/* Weight chart */}
      {chartData.length > 1 ? (
        <Card className="mb-6">
          <h2 className="text-sm font-semibold text-gray-300 mb-4 uppercase tracking-wide">Weight History</h2>
          <ResponsiveContainer width="100%" height={240}>
            <LineChart data={chartData} margin={{ top: 5, right: 5, bottom: 5, left: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
              <XAxis dataKey="date" tick={{ fill: '#9CA3AF', fontSize: 11 }} />
              <YAxis tick={{ fill: '#9CA3AF', fontSize: 11 }} domain={['auto', 'auto']} />
              <Tooltip
                contentStyle={{ backgroundColor: '#1F2937', border: '1px solid #374151', borderRadius: 8 }}
                labelStyle={{ color: '#D1D5DB' }}
                itemStyle={{ color: '#10B981' }}
              />
              <Line type="monotone" dataKey="weight" stroke="#10B981" strokeWidth={2} dot={{ fill: '#10B981', r: 3 }} />
            </LineChart>
          </ResponsiveContainer>
        </Card>
      ) : !loading && chartData.length <= 1 ? (
        <Card className="text-center py-8 mb-6">
          <p className="text-gray-500 text-sm">Log at least 2 entries to see your weight trend.</p>
        </Card>
      ) : null}

      {/* History list */}
      <Card>
        <h2 className="text-sm font-semibold text-gray-300 mb-3 uppercase tracking-wide">Recent Logs</h2>
        {loading ? (
          <p className="text-gray-500 text-sm">Loading...</p>
        ) : history.length === 0 ? (
          <p className="text-gray-500 text-sm">No entries yet.</p>
        ) : (
          <ul className="divide-y divide-gray-700">
            {history.slice(0, 20).map(entry => (
              <li key={entry.id} className="py-2.5 flex justify-between text-sm">
                <span className="text-gray-400">
                  {new Date(entry.recorded_at).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}
                </span>
                <span className="font-semibold text-gray-200">{entry.weight_kg} kg</span>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}
