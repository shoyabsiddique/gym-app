import { useEffect, useState } from 'react';
import api from '../../lib/api';
import { Card } from '../../components/ui/Card';
import type { Recommendation } from '../../types';

interface DayData {
  date: string;
  recommendation: Recommendation | null;
}

const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export function WeekView() {
  const [week, setWeek] = useState<DayData[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/recommendations/week')
      .then(r => setWeek(r.data))
      .finally(() => setLoading(false));
  }, []);

  const today = new Date().toISOString().slice(0, 10);

  return (
    <div className="max-w-4xl mx-auto px-4 py-6">
      <h1 className="text-xl font-bold text-white mb-6">Weekly Meal Plan</h1>

      {loading ? (
        <div className="text-center py-12 text-gray-400">Loading...</div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7">
          {week.map((day, i) => {
            const isToday = day.date === today;
            const rec = day.recommendation;
            return (
              <Card key={day.date} className={`min-h-[140px] ${isToday ? 'border-emerald-500 ring-1 ring-emerald-500/50' : ''}`}>
                <div className="flex items-center justify-between mb-2">
                  <span className={`text-sm font-semibold ${isToday ? 'text-emerald-400' : 'text-gray-300'}`}>
                    {DAY_NAMES[i]}
                  </span>
                  {isToday && <span className="text-xs text-emerald-400 bg-emerald-900/40 px-1.5 py-0.5 rounded">Today</span>}
                </div>
                <p className="text-xs text-gray-500 mb-3">{new Date(day.date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</p>

                {rec ? (
                  <div className="space-y-1.5">
                    <div className="text-xs text-gray-400">{Math.round(rec.totals.calories)} kcal</div>
                    <div className="grid grid-cols-3 gap-1 text-xs">
                      <div className="text-center bg-gray-700/50 rounded p-1">
                        <div className="text-emerald-400 font-medium">{Math.round(rec.totals.protein)}g</div>
                        <div className="text-gray-500">pro</div>
                      </div>
                      <div className="text-center bg-gray-700/50 rounded p-1">
                        <div className="text-orange-400 font-medium">{Math.round(rec.totals.carbs)}g</div>
                        <div className="text-gray-500">carb</div>
                      </div>
                      <div className="text-center bg-gray-700/50 rounded p-1">
                        <div className="text-red-400 font-medium">{Math.round(rec.totals.fat)}g</div>
                        <div className="text-gray-500">fat</div>
                      </div>
                    </div>
                  </div>
                ) : (
                  <p className="text-xs text-gray-600 italic">No plan</p>
                )}
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}
