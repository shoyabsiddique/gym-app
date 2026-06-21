import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'react-hot-toast';
import { useAuthStore } from '../../store/authStore';
import api from '../../lib/api';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import type { Recommendation, MealType, MealLog, SwapAlternative, Dish } from '../../types';

const MEAL_LABELS: Record<MealType, { icon: string; label: string; time: string }> = {
  breakfast: { icon: '🌅', label: 'Breakfast', time: '8:00 - 10:00 AM' },
  lunch:     { icon: '☀️', label: 'Lunch',     time: '12:00 - 2:00 PM' },
  snacks:    { icon: '🍎', label: 'Snacks',    time: '4:00 - 5:00 PM'  },
  dinner:    { icon: '🌙', label: 'Dinner',    time: '7:00 - 9:00 PM'  },
};

const MACRO_COLORS = {
  Calories: { bar: 'bg-blue-500',    text: 'text-blue-400'    },
  Protein:  { bar: 'bg-emerald-500', text: 'text-emerald-400' },
  Carbs:    { bar: 'bg-orange-500',  text: 'text-orange-400'  },
  Fat:      { bar: 'bg-red-500',     text: 'text-red-400'     },
};

const MEAL_ORDER: MealType[] = ['breakfast', 'lunch', 'snacks', 'dinner'];

interface RecResponse extends Recommendation {
  is_future?: boolean;
}

export function Dashboard() {
  const { user } = useAuthStore();
  const navigate = useNavigate();
  const [rec, setRec] = useState<RecResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [mealLogs, setMealLogs] = useState<MealLog[]>([]);
  const [loggingDish, setLoggingDish] = useState<number | null>(null);

  // Swap modal state
  const [swapModal, setSwapModal] = useState<{
    dish: Dish;
    mealType: MealType;
    alternatives: SwapAlternative[];
  } | null>(null);
  const [swapLoading, setSwapLoading] = useState(false);
  const [swapping, setSwapping] = useState<number | null>(null);

  const planDate = rec?.date || new Date().toISOString().split('T')[0];

  useEffect(() => {
    if (!user?.target_calories) { navigate('/wizard'); return; }
    api.get('/recommendations/today')
      .then(r => setRec(r.data))
      .catch(() => setRec(null))
      .finally(() => setLoading(false));
  }, [user, navigate]);

  // Load meal logs for the plan date
  useEffect(() => {
    if (!planDate) return;
    api.get('/meal-logs', { params: { date: planDate } })
      .then(r => setMealLogs(r.data))
      .catch(() => {});
  }, [planDate]);

  const generate = async (regenerate = false) => {
    setGenerating(true);
    try {
      const r = await api.post('/recommendations/generate', { regenerate });
      setRec(r.data);
      toast.success(regenerate ? 'Plan regenerated!' : 'Meal plan ready!');
    } catch (e: any) {
      toast.error(e.response?.data?.message || "No cafeteria menu uploaded yet");
    } finally {
      setGenerating(false);
    }
  };

  const toggleLog = async (dish: Dish, mealType: MealType, servings: number) => {
    const existing = mealLogs.find(l => l.dish_id === dish.id && l.meal_type === mealType);
    setLoggingDish(dish.id);
    try {
      if (existing) {
        await api.delete(`/meal-logs/${existing.id}`);
        setMealLogs(prev => prev.filter(l => l.id !== existing.id));
        toast.success('Removed from log');
      } else {
        const r = await api.post('/meal-logs', {
          dish_id: dish.id,
          meal_type: mealType,
          log_date: planDate,
          servings,
        });
        setMealLogs(prev => [...prev, r.data]);
        toast.success('Logged!');
      }
    } catch (e: any) {
      toast.error('Failed to update log');
    } finally {
      setLoggingDish(null);
    }
  };

  const openSwap = async (dish: Dish, mealType: MealType) => {
    setSwapLoading(true);
    setSwapModal({ dish, mealType, alternatives: [] });
    try {
      const r = await api.get('/recommendations/swap', {
        params: { dish_id: dish.id, meal_type: mealType, date: planDate },
      });
      setSwapModal({ dish, mealType, alternatives: r.data.alternatives });
    } catch {
      toast.error('Failed to load alternatives');
      setSwapModal(null);
    } finally {
      setSwapLoading(false);
    }
  };

  const confirmSwap = async (alt: SwapAlternative) => {
    if (!rec || !swapModal) return;
    setSwapping(alt.dish.id);
    try {
      const r = await api.post('/recommendations/swap', {
        recommendation_id: rec.id,
        old_dish_id: swapModal.dish.id,
        new_dish_id: alt.dish.id,
        meal_type: swapModal.mealType,
      });
      setRec(r.data);
      setSwapModal(null);
      toast.success(`Swapped to ${alt.dish.name}`);
    } catch {
      toast.error('Swap failed');
    } finally {
      setSwapping(null);
    }
  };

  if (!user?.target_calories) return null;

  const hasMeals = rec && Object.values(rec.meals).some(m => m?.length);

  const planDateObj = new Date(planDate + 'T00:00:00');
  const planDateFmt = planDateObj.toLocaleDateString('en-US', {
    weekday: 'long', month: 'long', day: 'numeric',
  });
  const isFuture = rec?.is_future ?? false;

  const todayFmt = new Date().toLocaleDateString('en-US', {
    weekday: 'long', month: 'long', day: 'numeric',
  });

  // Calculate logged totals
  const loggedTotals = mealLogs.reduce(
    (a, l) => ({
      calories: a.calories + l.dish.calories * l.servings,
      protein:  a.protein  + l.dish.protein  * l.servings,
      carbs:    a.carbs    + l.dish.carbs    * l.servings,
      fat:      a.fat      + l.dish.fat      * l.servings,
    }),
    { calories: 0, protein: 0, carbs: 0, fat: 0 }
  );

  const hasLogs = mealLogs.length > 0;

  return (
    <div className="max-w-3xl mx-auto px-4 py-6 space-y-5">

      {/* Greeting */}
      <div>
        <h1 className="text-xl font-bold text-white">
          Hi {user.name?.split(' ')[0]} 👋
        </h1>
        {isFuture ? (
          <div className="mt-1">
            <p className="text-gray-400 text-sm">{todayFmt}</p>
            <div className="flex items-center gap-2 mt-1">
              <span className="inline-block w-2 h-2 rounded-full bg-amber-400 animate-pulse" />
              <p className="text-amber-300 text-sm font-medium">
                No cafeteria menu today - showing {planDateFmt}'s plan
              </p>
            </div>
          </div>
        ) : (
          <p className="text-gray-400 text-sm mt-0.5">{todayFmt} - Here's what to eat today</p>
        )}
      </div>

      {/* Macro progress */}
      {user.target_calories && (
        <Card>
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-xs font-semibold text-gray-400 uppercase tracking-wide">
              {isFuture ? `${planDateObj.toLocaleDateString('en-US', { weekday: 'long' })}'s Targets` : 'Daily Targets'}
            </h2>
            {hasLogs && (
              <span className="text-xs text-emerald-400 font-medium">
                Logged: {Math.round(loggedTotals.calories)} kcal
              </span>
            )}
          </div>
          <div className="space-y-3">
            {[
              { label: 'Calories', recommended: rec?.totals.calories ?? 0, logged: loggedTotals.calories, target: user.target_calories, unit: 'kcal' },
              { label: 'Protein',  recommended: rec?.totals.protein  ?? 0, logged: loggedTotals.protein,  target: user.target_protein!, unit: 'g'    },
              { label: 'Carbs',    recommended: rec?.totals.carbs    ?? 0, logged: loggedTotals.carbs,    target: user.target_carbs!,   unit: 'g'    },
              { label: 'Fat',      recommended: rec?.totals.fat      ?? 0, logged: loggedTotals.fat,      target: user.target_fat!,     unit: 'g'    },
            ].map(({ label, recommended, logged, target, unit }) => {
              const current = hasLogs ? logged : recommended;
              const pct = Math.min(100, (current / target) * 100);
              const { bar, text } = MACRO_COLORS[label as keyof typeof MACRO_COLORS];
              return (
                <div key={label}>
                  <div className="flex justify-between text-xs mb-1">
                    <span className="text-gray-400">{label}</span>
                    <span className={`font-medium ${text}`}>
                      {Math.round(current)}{unit} <span className="text-gray-500">/ {target}{unit}</span>
                    </span>
                  </div>
                  <div className="h-2 bg-gray-700 rounded-full overflow-hidden">
                    <div className={`h-full rounded-full transition-all duration-500 ${bar}`} style={{ width: `${pct}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
          {hasLogs && (
            <p className="text-xs text-gray-500 mt-2">Progress bars show your logged intake.</p>
          )}
        </Card>
      )}

      {/* Meal plan */}
      {loading ? (
        <div className="text-center py-12 text-gray-400">Loading your plan...</div>
      ) : !hasMeals ? (
        <Card className="text-center py-10">
          <div className="text-5xl mb-4">🍽️</div>
          <p className="text-gray-300 font-medium mb-1">No meal plan available</p>
          <p className="text-gray-500 text-sm mb-5">
            We'll pick the best dishes from the cafeteria menu to match your {user.goal?.replace('_', ' ')} goal.
          </p>
          <Button onClick={() => generate()} loading={generating} size="lg">
            Generate Meal Plan
          </Button>
        </Card>
      ) : (
        <div className="space-y-4">
          {MEAL_ORDER.map(mealType => {
            const items = rec?.meals[mealType];
            if (!items?.length) return null;
            const { icon, label, time } = MEAL_LABELS[mealType];

            const totals = items.reduce((a, item) => {
              const s = Number(item.servings);
              return {
                cal:     a.cal     + (item.dish.calories * s),
                protein: a.protein + (item.dish.protein  * s),
                carbs:   a.carbs   + (item.dish.carbs    * s),
                fat:     a.fat     + (item.dish.fat      * s),
              };
            }, { cal: 0, protein: 0, carbs: 0, fat: 0 });

            // Group items by counter
            const byCounter: Record<string, typeof items> = {};
            for (const item of items) {
              const c = item.counter || 'General';
              if (!byCounter[c]) byCounter[c] = [];
              byCounter[c].push(item);
            }

            // Count logged items in this meal
            const mealLogIds = new Set(
              mealLogs.filter(l => l.meal_type === mealType).map(l => l.dish_id)
            );

            return (
              <Card key={mealType}>
                {/* Meal header */}
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center gap-2">
                    <span className="text-xl">{icon}</span>
                    <div>
                      <h3 className="text-sm font-semibold text-gray-100">{label}</h3>
                      <p className="text-xs text-gray-500">{time}</p>
                    </div>
                  </div>
                  <div className="text-right">
                    <span className="text-sm font-bold text-white">{Math.round(totals.cal)}</span>
                    <span className="text-xs text-gray-500 ml-1">kcal</span>
                  </div>
                </div>

                {/* Dishes grouped by counter */}
                <div className="space-y-3 mb-3">
                  {Object.entries(byCounter).map(([counter, counterItems]) => (
                    <div key={counter}>
                      <div className="flex items-center gap-2 mb-1.5">
                        <span className="text-xs font-bold text-amber-300">{counter}</span>
                        <div className="flex-1 h-px bg-gray-700/50" />
                      </div>
                      <ul className="space-y-1.5">
                        {counterItems.map(item => {
                          const isLogged = mealLogIds.has(item.dish.id);
                          return (
                            <li key={item.dish.id}
                              className={`flex items-center justify-between rounded-lg px-3 py-2 transition-colors ${
                                isLogged ? 'bg-emerald-900/30 border border-emerald-800/50' : 'bg-gray-800/50'
                              }`}>
                              <div className="flex items-center gap-2 min-w-0 flex-1">
                                <span className="text-emerald-400 text-sm font-medium shrink-0">
                                  {Number(item.servings) === 1 ? '1x' : `${Number(item.servings).toFixed(1)}x`}
                                </span>
                                <span className="text-sm text-gray-200 truncate">{item.dish.name}</span>
                              </div>
                              <div className="flex items-center gap-2 shrink-0 ml-2">
                                <span className="text-xs text-gray-400">
                                  {Math.round(item.dish.calories * Number(item.servings))} kcal
                                </span>
                                <button
                                  onClick={() => openSwap(item.dish, mealType)}
                                  className="text-xs text-gray-500 hover:text-amber-400 transition-colors"
                                  title="Swap dish"
                                >
                                  🔄
                                </button>
                                <button
                                  onClick={() => toggleLog(item.dish, mealType, Number(item.servings))}
                                  disabled={loggingDish === item.dish.id}
                                  className={`text-xs px-2 py-0.5 rounded-full transition-colors ${
                                    isLogged
                                      ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                      : 'bg-gray-700 text-gray-400 hover:bg-gray-600 hover:text-white'
                                  }`}
                                >
                                  {isLogged ? '✓ Ate' : 'Log'}
                                </button>
                              </div>
                            </li>
                          );
                        })}
                      </ul>
                    </div>
                  ))}
                </div>

                {/* Per-meal macro chips */}
                <div className="flex gap-2 flex-wrap">
                  {[
                    { label: 'P', val: totals.protein, color: 'text-emerald-400 bg-emerald-900/30' },
                    { label: 'C', val: totals.carbs,   color: 'text-orange-400 bg-orange-900/30'  },
                    { label: 'F', val: totals.fat,     color: 'text-red-400 bg-red-900/30'        },
                  ].map(({ label, val, color }) => (
                    <span key={label} className={`text-xs font-medium px-2 py-0.5 rounded-full ${color}`}>
                      {label} {Math.round(val)}g
                    </span>
                  ))}
                </div>
              </Card>
            );
          })}

          <div className="text-center pt-1">
            <button onClick={() => generate(true)} disabled={generating}
              className="text-xs text-gray-500 hover:text-gray-300 underline transition-colors">
              {generating ? 'Regenerating...' : 'Regenerate plan'}
            </button>
          </div>
        </div>
      )}

      {/* Swap Modal */}
      {swapModal && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-end sm:items-center justify-center p-4"
          onClick={() => setSwapModal(null)}>
          <div className="bg-gray-900 rounded-2xl w-full max-w-md max-h-[80vh] overflow-y-auto border border-gray-700"
            onClick={e => e.stopPropagation()}>
            <div className="p-4 border-b border-gray-700">
              <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold text-white">Swap Dish</h3>
                <button onClick={() => setSwapModal(null)} className="text-gray-400 hover:text-white text-lg">&times;</button>
              </div>
              <p className="text-xs text-gray-400 mt-1">
                Current: <span className="text-gray-200">{swapModal.dish.name}</span>
                <span className="text-gray-500 ml-1">({swapModal.dish.calories} kcal, {swapModal.dish.protein}g protein)</span>
              </p>
            </div>
            <div className="p-4">
              {swapLoading ? (
                <p className="text-center text-gray-400 py-8">Finding alternatives...</p>
              ) : swapModal.alternatives.length === 0 ? (
                <p className="text-center text-gray-500 py-8">No alternatives available for this meal.</p>
              ) : (
                <ul className="space-y-2">
                  {swapModal.alternatives.map(alt => (
                    <li key={alt.dish.id}>
                      <button
                        onClick={() => confirmSwap(alt)}
                        disabled={swapping === alt.dish.id}
                        className="w-full text-left bg-gray-800/50 hover:bg-gray-700/60 rounded-lg px-3 py-2.5 transition-colors border border-transparent hover:border-emerald-600/40 disabled:opacity-50"
                      >
                        <div className="flex items-center justify-between">
                          <div className="min-w-0 flex-1">
                            <p className="text-sm text-gray-200 truncate">{alt.dish.name}</p>
                            <div className="flex gap-2 mt-1">
                              <span className="text-xs text-gray-500">{alt.counter}</span>
                              <span className="text-xs text-gray-400">{alt.dish.calories} kcal</span>
                              <span className="text-xs text-emerald-400">{alt.dish.protein}g P</span>
                            </div>
                          </div>
                          <div className="text-right shrink-0 ml-3">
                            <div className={`text-xs font-medium ${alt.cal_diff > 0 ? 'text-red-400' : alt.cal_diff < 0 ? 'text-emerald-400' : 'text-gray-400'}`}>
                              {alt.cal_diff > 0 ? '+' : ''}{alt.cal_diff} kcal
                            </div>
                            <div className={`text-xs ${alt.prot_diff > 0 ? 'text-emerald-400' : alt.prot_diff < 0 ? 'text-red-400' : 'text-gray-400'}`}>
                              {alt.prot_diff > 0 ? '+' : ''}{alt.prot_diff}g P
                            </div>
                          </div>
                        </div>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
