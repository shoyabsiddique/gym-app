import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'react-hot-toast';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Card } from '../../components/ui/Card';
import api from '../../lib/api';
import { useAuthStore } from '../../store/authStore';
import { calculateBMR, calculateTDEE, calculateTargets } from '../../lib/fitness';
import type { ActivityLevel, DietType, Gender, Goal } from '../../types';

const ACTIVITY_LABELS: Record<ActivityLevel, string> = {
  sedentary:   'Sedentary (desk job, no exercise)',
  light:       'Light (exercise 1-3x/week)',
  moderate:    'Moderate (exercise 3-5x/week)',
  active:      'Active (hard exercise 6-7x/week)',
  very_active: 'Very Active (physical job + training)',
};

const GOAL_LABELS: Record<Goal, { label: string; desc: string; icon: string }> = {
  fat_loss:     { label: 'Fat Loss',     desc: 'Calorie deficit to shed body fat',   icon: '🔥' },
  maintenance:  { label: 'Maintenance',  desc: 'Eat at maintenance, stay stable',    icon: '⚖️' },
  muscle_gain:  { label: 'Muscle Gain',  desc: 'Calorie surplus to build muscle',   icon: '💪' },
};

const DIET_LABELS: Record<DietType, { label: string; desc: string; icon: string }> = {
  veg:        { label: 'Vegetarian',  desc: 'No meat, fish, or eggs',           icon: '🥬' },
  eggetarian: { label: 'Eggetarian',  desc: 'Vegetarian + eggs',               icon: '🥚' },
  non_veg:    { label: 'Non-Veg',     desc: 'Everything including meat & fish', icon: '🍗' },
};

const TOTAL_STEPS = 5;

export function Wizard() {
  const { setUser } = useAuthStore();
  const navigate = useNavigate();
  const [step, setStep] = useState(1);
  const [saving, setSaving] = useState(false);

  const [form, setForm] = useState({
    age: '', gender: '' as Gender | '', weight_kg: '', height_cm: '',
    target_weight: '', activity_level: '' as ActivityLevel | '', goal: '' as Goal | '',
    diet_type: 'non_veg' as DietType, allergies: '' ,
  });

  const set = (field: string, value: string) =>
    setForm(prev => ({ ...prev, [field]: value }));

  const preview = (() => {
    const { gender, weight_kg, height_cm, age, activity_level, goal } = form;
    if (!gender || !weight_kg || !height_cm || !age || !activity_level || !goal) return null;
    const bmr  = calculateBMR(gender as Gender, +weight_kg, +height_cm, +age);
    const tdee = calculateTDEE(bmr, activity_level as ActivityLevel);
    return { bmr: Math.round(bmr), tdee, ...calculateTargets(tdee, goal as Goal, +weight_kg) };
  })();

  const handleSubmit = async () => {
    if (!preview) return;
    setSaving(true);
    try {
      const allergies = form.allergies
        .split(',')
        .map(a => a.trim())
        .filter(a => a.length > 0);

      const res = await api.put('/users/profile', {
        age:            +form.age,
        gender:         form.gender,
        weight_kg:      +form.weight_kg,
        height_cm:      +form.height_cm,
        target_weight:  form.target_weight ? +form.target_weight : undefined,
        activity_level: form.activity_level,
        goal:           form.goal,
        diet_type:      form.diet_type,
        allergies:      allergies.length > 0 ? allergies : null,
      });
      setUser(res.data);
      toast.success('Profile saved! Generating your first meal plan...');
      navigate('/dashboard');
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Failed to save profile');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-950 flex items-center justify-center px-4">
      <div className="w-full max-w-lg">
        <div className="text-center mb-6">
          <h1 className="text-2xl font-bold text-white">Set Up Your Fitness Profile</h1>
          <p className="text-gray-400 mt-1">Step {step} of {TOTAL_STEPS}</p>
          <div className="flex gap-1 mt-3 justify-center">
            {Array.from({ length: TOTAL_STEPS }, (_, i) => i + 1).map(n => (
              <div key={n} className={`h-1.5 w-12 rounded-full transition-colors ${n <= step ? 'bg-emerald-500' : 'bg-gray-700'}`} />
            ))}
          </div>
        </div>

        <Card>
          {step === 1 && (
            <div className="space-y-4">
              <h2 className="text-lg font-semibold text-white mb-4">Tell us about yourself</h2>
              <Input label="Age (years)" type="number" min="10" max="100" placeholder="28"
                value={form.age} onChange={e => set('age', e.target.value)} />
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">Gender</label>
                <div className="grid grid-cols-2 gap-3">
                  {(['male', 'female'] as Gender[]).map(g => (
                    <button key={g} onClick={() => set('gender', g)}
                      className={`p-3 rounded-lg border text-sm font-medium transition-colors capitalize ${
                        form.gender === g
                          ? 'border-emerald-500 bg-emerald-900/30 text-emerald-300'
                          : 'border-gray-600 text-gray-400 hover:border-gray-500'
                      }`}>
                      {g === 'male' ? '👨' : '👩'} {g}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          )}

          {step === 2 && (
            <div className="space-y-4">
              <h2 className="text-lg font-semibold text-white mb-4">Your body measurements</h2>
              <Input label="Current Weight (kg)" type="number" step="0.1" min="30" max="300" placeholder="75"
                value={form.weight_kg} onChange={e => set('weight_kg', e.target.value)} />
              <Input label="Height (cm)" type="number" min="100" max="250" placeholder="175"
                value={form.height_cm} onChange={e => set('height_cm', e.target.value)} />
              <Input label="Target Weight (kg, optional)" type="number" step="0.1" min="30" max="300" placeholder="70"
                value={form.target_weight} onChange={e => set('target_weight', e.target.value)} />
            </div>
          )}

          {step === 3 && (
            <div className="space-y-3">
              <h2 className="text-lg font-semibold text-white mb-4">How active are you?</h2>
              {(Object.keys(ACTIVITY_LABELS) as ActivityLevel[]).map(level => (
                <button key={level} onClick={() => set('activity_level', level)}
                  className={`w-full text-left p-3 rounded-lg border text-sm transition-colors ${
                    form.activity_level === level
                      ? 'border-emerald-500 bg-emerald-900/30 text-emerald-300'
                      : 'border-gray-600 text-gray-300 hover:border-gray-500'
                  }`}>
                  {ACTIVITY_LABELS[level]}
                </button>
              ))}
            </div>
          )}

          {step === 4 && (
            <div className="space-y-4">
              <h2 className="text-lg font-semibold text-white mb-4">What's your fitness goal?</h2>
              <div className="grid gap-3">
                {(Object.keys(GOAL_LABELS) as Goal[]).map(goal => {
                  const { label, desc, icon } = GOAL_LABELS[goal];
                  return (
                    <button key={goal} onClick={() => set('goal', goal)}
                      className={`text-left p-4 rounded-lg border transition-colors ${
                        form.goal === goal
                          ? 'border-emerald-500 bg-emerald-900/30'
                          : 'border-gray-600 hover:border-gray-500'
                      }`}>
                      <div className="font-medium text-white">{icon} {label}</div>
                      <div className="text-sm text-gray-400 mt-0.5">{desc}</div>
                    </button>
                  );
                })}
              </div>

              {preview && (
                <div className="mt-4 p-4 bg-gray-900 rounded-lg border border-gray-600">
                  <h3 className="text-sm font-medium text-gray-300 mb-3">Your Calculated Targets</h3>
                  <div className="grid grid-cols-2 gap-3 text-sm">
                    {[
                      { label: 'BMR',      val: `${preview.bmr} kcal` },
                      { label: 'TDEE',     val: `${preview.tdee} kcal` },
                      { label: 'Calories', val: `${preview.target_calories} kcal`, em: true },
                      { label: 'Protein',  val: `${preview.target_protein}g`,      em: true },
                      { label: 'Carbs',    val: `${preview.target_carbs}g`,        em: true },
                      { label: 'Fat',      val: `${preview.target_fat}g`,          em: true },
                    ].map(({ label, val, em }) => (
                      <div key={label} className={`p-2 rounded ${em ? 'bg-emerald-900/20' : ''}`}>
                        <div className="text-gray-400 text-xs">{label}</div>
                        <div className={`font-semibold ${em ? 'text-emerald-300' : 'text-gray-200'}`}>{val}</div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}

          {step === 5 && (
            <div className="space-y-4">
              <h2 className="text-lg font-semibold text-white mb-4">Dietary Preferences</h2>

              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">Diet Type</label>
                <div className="grid gap-3">
                  {(Object.keys(DIET_LABELS) as DietType[]).map(dt => {
                    const { label, desc, icon } = DIET_LABELS[dt];
                    return (
                      <button key={dt} onClick={() => set('diet_type', dt)}
                        className={`text-left p-3 rounded-lg border transition-colors ${
                          form.diet_type === dt
                            ? 'border-emerald-500 bg-emerald-900/30'
                            : 'border-gray-600 hover:border-gray-500'
                        }`}>
                        <div className="font-medium text-white text-sm">{icon} {label}</div>
                        <div className="text-xs text-gray-400 mt-0.5">{desc}</div>
                      </button>
                    );
                  })}
                </div>
              </div>

              <Input
                label="Allergies (comma-separated, optional)"
                placeholder="e.g. peanut, gluten, dairy"
                value={form.allergies}
                onChange={e => set('allergies', e.target.value)}
              />
              <p className="text-xs text-gray-500">
                Dishes containing these keywords will be excluded from your recommendations.
              </p>
            </div>
          )}

          <div className="flex gap-3 mt-6">
            {step > 1 && (
              <Button variant="secondary" onClick={() => setStep(s => s - 1)} className="flex-1">
                Back
              </Button>
            )}
            {step < TOTAL_STEPS ? (
              <Button onClick={() => setStep(s => s + 1)} className="flex-1"
                disabled={
                  (step === 1 && (!form.age || !form.gender)) ||
                  (step === 2 && (!form.weight_kg || !form.height_cm)) ||
                  (step === 3 && !form.activity_level) ||
                  (step === 4 && !form.goal)
                }>
                Next
              </Button>
            ) : (
              <Button onClick={handleSubmit} loading={saving} className="flex-1"
                disabled={!form.goal || !preview}>
                Save & Continue
              </Button>
            )}
          </div>
        </Card>
      </div>
    </div>
  );
}
