import { useEffect, useState } from 'react';
import { toast } from 'react-hot-toast';
import api from '../../lib/api';
import { Card } from '../../components/ui/Card';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Badge } from '../../components/ui/Badge';
import type { Dish } from '../../types';

export function AdminDishes() {
  const [dishes, setDishes] = useState<Dish[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [editing, setEditing] = useState<Dish | null>(null);

  const load = (q = '') =>
    api.get('/dishes', { params: { search: q } })
      .then(r => setDishes(r.data.data ?? r.data))
      .finally(() => setLoading(false));

  useEffect(() => { load(); }, []);
  useEffect(() => {
    const t = setTimeout(() => load(search), 400);
    return () => clearTimeout(t);
  }, [search]);

  const handleSave = async () => {
    if (!editing) return;
    try {
      const res = await api.put(`/dishes/${editing.id}`, editing);
      setDishes(d => d.map(x => x.id === editing.id ? res.data : x));
      setEditing(null);
      toast.success('Dish updated');
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Update failed');
    }
  };

  const aiStatusBadge = (dish: Dish) => {
    const status = dish.ai_job?.status;
    if (status === 'done')       return <Badge variant="success">AI Done</Badge>;
    if (status === 'processing') return <Badge variant="info">Processing</Badge>;
    if (status === 'failed')     return <Badge variant="danger">Failed</Badge>;
    if (status === 'pending')    return <Badge variant="warning">Pending</Badge>;
    if (dish.ai_generated)       return <Badge variant="success">AI</Badge>;
    return <Badge variant="neutral">Manual</Badge>;
  };

  return (
    <div className="max-w-5xl mx-auto px-4 py-6">
      <h1 className="text-xl font-bold text-white mb-6">Dishes</h1>

      <div className="mb-4">
        <Input placeholder="Search dishes..." value={search} onChange={e => setSearch(e.target.value)} />
      </div>

      {editing && (
        <Card className="mb-4 border-emerald-600">
          <h2 className="text-sm font-semibold text-gray-200 mb-4">Edit: {editing.name}</h2>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            {(['calories', 'protein', 'carbs', 'fat', 'fiber', 'sugar', 'sodium'] as const).map(field => (
              <div key={field}>
                <label className="block text-xs text-gray-400 mb-1 capitalize">{field}</label>
                <input type="number" step="0.1" min="0"
                  value={(editing as any)[field]}
                  onChange={e => setEditing(prev => prev ? { ...prev, [field]: +e.target.value } : null)}
                  className="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>
            ))}
            <div className="col-span-2">
              <label className="block text-xs text-gray-400 mb-1">Serving Size</label>
              <input type="text"
                value={editing.serving_size}
                onChange={e => setEditing(prev => prev ? { ...prev, serving_size: e.target.value } : null)}
                className="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
          </div>
          <div className="flex gap-3 mt-4">
            <Button onClick={handleSave}>Save</Button>
            <Button variant="secondary" onClick={() => setEditing(null)}>Cancel</Button>
          </div>
        </Card>
      )}

      {loading ? (
        <div className="text-center py-12 text-gray-400">Loading...</div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-700">
                {['Name', 'Calories', 'Protein', 'Carbs', 'Fat', 'Status', ''].map(h => (
                  <th key={h} className="text-left py-2 px-3 text-xs font-semibold text-gray-400 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-800">
              {dishes.map(d => (
                <tr key={d.id} className="hover:bg-gray-800/50 transition-colors">
                  <td className="py-2.5 px-3 text-gray-200 font-medium">{d.name}</td>
                  <td className="py-2.5 px-3 text-gray-400">{d.calories}</td>
                  <td className="py-2.5 px-3 text-emerald-400">{d.protein}g</td>
                  <td className="py-2.5 px-3 text-orange-400">{d.carbs}g</td>
                  <td className="py-2.5 px-3 text-red-400">{d.fat}g</td>
                  <td className="py-2.5 px-3">{aiStatusBadge(d)}</td>
                  <td className="py-2.5 px-3">
                    <button onClick={() => setEditing(d)} className="text-xs text-blue-400 hover:underline">Edit</button>
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
