import { useEffect, useState } from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import api from '../../lib/api';
import { useAuthStore } from '../../store/authStore';
import { Card } from '../../components/ui/Card';
import type { InsightsData } from '../../types';

export function Insights() {
  const { user } = useAuthStore();
  const [data, setData] = useState<InsightsData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/insights/weekly')
      .then(r => setData(r.data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-6">
        <p className="text-center text-gray-400 py-12">Loading insights...</p>
      </div>
    );
  }

  if (!data) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-6">
        <p className="text-center text-gray-500 py-12">Could not load insights.</p>
      </div>
    );
  }

  const chartData = data.daily.map(d => ({
    day: new Date(d.date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short' }),
    Calories: d.logged ? d.totals.calories : 0,
    target: data.targets.calories,
  }));

  return (
    <div className="max-w-3xl mx-auto px-4 py-6 space-y-5">
      <h1 className="text-xl font-bold text-white">Weekly Insights</h1>

      {/* Streak & Stats Row */}
      <div className="grid grid-cols-3 gap-3">
        <Card className="text-center py-4">
          <div className="text-3xl mb-1">🔥</div>
          <div className="text-2xl font-bold text-white">{data.streak}</div>
          <div className="text-xs text-gray-400">Day Streak</div>
        </Card>
        <Card className="text-center py-4">
          <div className="text-3xl mb-1">📋</div>
          <div className="text-2xl font-bold text-white">{data.logged_days}</div>
          <div className="text-xs text-gray-400">Days Logged</div>
        </Card>
        <Card className="text-center py-4">
          <div className="text-3xl mb-1">
            {data.weight_trend?.direction === 'down' ? '📉' : data.weight_trend?.direction === 'up' ? '📈' : '⚖️'}
          </div>
          <div className="text-2xl font-bold text-white">
            {data.weight_trend ? `${data.weight_trend.change_kg > 0 ? '+' : ''}${data.weight_trend.change_kg}` : '--'}
          </div>
          <div className="text-xs text-gray-400">
            {data.weight_trend ? 'kg trend' : 'No weight data'}
          </div>
        </Card>
      </div>

      {/* Calorie Chart */}
      <Card>
        <h2 className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">
          Daily Calories (Last 7 Days)
        </h2>
        {data.logged_days > 0 ? (
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={chartData} margin={{ top: 5, right: 5, bottom: 5, left: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
              <XAxis dataKey="day" tick={{ fill: '#9CA3AF', fontSize: 11 }} />
              <YAxis tick={{ fill: '#9CA3AF', fontSize: 11 }} />
              <Tooltip
                contentStyle={{ backgroundColor: '#1F2937', border: '1px solid #374151', borderRadius: 8 }}
                labelStyle={{ color: '#D1D5DB' }}
              />
              <Bar dataKey="Calories" fill="#10B981" radius={[4, 4, 0, 0]} />
              <Bar dataKey="target" fill="#374151" radius={[4, 4, 0, 0]} name="Target" />
            </BarChart>
          </ResponsiveContainer>
        ) : (
          <p className="text-center text-gray-500 py-8 text-sm">
            Start logging meals on the Dashboard to see your calorie chart here.
          </p>
        )}
      </Card>

      {/* Avg vs Target */}
      {data.logged_days >= 1 && (
        <Card>
          <h2 className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">
            Average vs Target ({data.logged_days} day{data.logged_days > 1 ? 's' : ''})
          </h2>
          <div className="space-y-3">
            {[
              { label: 'Calories', avg: data.avg_macros.calories, target: data.targets.calories, unit: 'kcal', color: 'bg-blue-500' },
              { label: 'Protein',  avg: data.avg_macros.protein,  target: data.targets.protein,  unit: 'g',    color: 'bg-emerald-500' },
              { label: 'Carbs',    avg: data.avg_macros.carbs,    target: data.targets.carbs,    unit: 'g',    color: 'bg-orange-500' },
              { label: 'Fat',      avg: data.avg_macros.fat,      target: data.targets.fat,      unit: 'g',    color: 'bg-red-500' },
            ].map(({ label, avg, target, unit, color }) => {
              const pct = target > 0 ? Math.min(120, (avg / target) * 100) : 0;
              const diff = avg - target;
              return (
                <div key={label}>
                  <div className="flex justify-between text-xs mb-1">
                    <span className="text-gray-400">{label}</span>
                    <span className="text-gray-300">
                      {Math.round(avg)}{unit}
                      <span className="text-gray-500"> / {target}{unit}</span>
                      <span className={`ml-1 ${diff > 0 ? 'text-red-400' : diff < 0 ? 'text-emerald-400' : 'text-gray-400'}`}>
                        ({diff > 0 ? '+' : ''}{Math.round(diff)})
                      </span>
                    </span>
                  </div>
                  <div className="h-2 bg-gray-700 rounded-full overflow-hidden">
                    <div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${Math.min(100, pct)}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
        </Card>
      )}

      {/* Weight Trend */}
      {data.weight_trend && (
        <Card>
          <h2 className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Weight Trend</h2>
          <div className="flex items-center gap-4">
            <div className="text-4xl">
              {data.weight_trend.direction === 'down' ? '📉' : data.weight_trend.direction === 'up' ? '📈' : '⚖️'}
            </div>
            <div>
              <p className="text-lg font-bold text-white">{data.weight_trend.latest_kg} kg</p>
              <p className="text-sm text-gray-400">
                {data.weight_trend.direction === 'down' && (
                  <span className="text-emerald-400">Down {Math.abs(data.weight_trend.change_kg)} kg</span>
                )}
                {data.weight_trend.direction === 'up' && (
                  <span className="text-red-400">Up {data.weight_trend.change_kg} kg</span>
                )}
                {data.weight_trend.direction === 'stable' && (
                  <span className="text-gray-300">Stable</span>
                )}
                <span className="text-gray-500 ml-1">
                  over {data.weight_trend.entries} entries
                </span>
              </p>
              {user?.target_weight && (
                <p className="text-xs text-gray-500 mt-0.5">
                  Target: {user.target_weight} kg ({(data.weight_trend.latest_kg - user.target_weight).toFixed(1)} kg to go)
                </p>
              )}
            </div>
          </div>
        </Card>
      )}

      {/* Tips */}
      {data.tips.length > 0 && (
        <Card>
          <h2 className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Tips</h2>
          <ul className="space-y-2">
            {data.tips.map((tip, i) => (
              <li key={i} className="flex gap-2 text-sm">
                <span className="text-amber-400 shrink-0">💡</span>
                <span className="text-gray-300">{tip}</span>
              </li>
            ))}
          </ul>
        </Card>
      )}

      {/* Activity Grid */}
      <Card>
        <h2 className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">This Week</h2>
        <div className="grid grid-cols-7 gap-2">
          {data.daily.map(d => {
            const day = new Date(d.date + 'T00:00:00');
            const isToday = d.date === new Date().toISOString().split('T')[0];
            return (
              <div key={d.date} className="text-center">
                <div className="text-xs text-gray-500 mb-1">
                  {day.toLocaleDateString('en-US', { weekday: 'short' })}
                </div>
                <div className={`w-8 h-8 mx-auto rounded-lg flex items-center justify-center text-xs font-medium ${
                  d.logged
                    ? 'bg-emerald-600 text-white'
                    : isToday
                      ? 'bg-gray-700 text-gray-300 ring-1 ring-emerald-500'
                      : 'bg-gray-800 text-gray-500'
                }`}>
                  {d.logged ? d.dish_count : '-'}
                </div>
              </div>
            );
          })}
        </div>
        <p className="text-xs text-gray-500 mt-2 text-center">
          Green = logged meals. Numbers = dishes logged that day.
        </p>
      </Card>
    </div>
  );
}
