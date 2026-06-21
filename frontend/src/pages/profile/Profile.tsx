import { useForm } from 'react-hook-form';
import { toast } from 'react-hot-toast';
import { useAuthStore } from '../../store/authStore';
import api from '../../lib/api';
import { Input } from '../../components/ui/Input';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';

interface Form {
  name: string;
  age: number;
  gender: 'male' | 'female';
  weight_kg: number;
  height_cm: number;
  activity_level: 'sedentary' | 'light' | 'moderate' | 'active' | 'very_active';
  goal: 'fat_loss' | 'maintenance' | 'muscle_gain';
  target_weight?: number;
  diet_type: 'veg' | 'non_veg' | 'eggetarian';
  allergies_text: string;
}

export function Profile() {
  const { user, setUser } = useAuthStore();

  const { register, handleSubmit, formState: { isSubmitting } } = useForm<Form>({
    defaultValues: {
      name:           user?.name || '',
      age:            user?.age,
      gender:         user?.gender,
      weight_kg:      user?.weight_kg,
      height_cm:      user?.height_cm,
      activity_level: user?.activity_level,
      goal:           user?.goal,
      target_weight:  user?.target_weight,
      diet_type:      user?.diet_type || 'non_veg',
      allergies_text: user?.allergies?.join(', ') || '',
    },
  });

  const onSubmit = async (data: Form) => {
    try {
      const allergies = data.allergies_text
        .split(',')
        .map(a => a.trim())
        .filter(a => a.length > 0);

      const { allergies_text, ...rest } = data;
      const res = await api.put('/users/profile', {
        ...rest,
        allergies: allergies.length > 0 ? allergies : null,
      });
      setUser(res.data);
      toast.success('Profile updated! Targets recalculated.');
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Update failed');
    }
  };

  return (
    <div className="max-w-xl mx-auto px-4 py-6">
      <h1 className="text-xl font-bold text-white mb-6">Your Profile</h1>

      {user?.target_calories && (
        <Card className="mb-6">
          <h2 className="text-sm font-semibold text-gray-300 mb-3 uppercase tracking-wide">Current Targets</h2>
          <div className="grid grid-cols-4 gap-3 text-center">
            {[
              { label: 'Calories', val: `${user.target_calories} kcal` },
              { label: 'Protein',  val: `${user.target_protein}g` },
              { label: 'Carbs',    val: `${user.target_carbs}g` },
              { label: 'Fat',      val: `${user.target_fat}g` },
            ].map(({ label, val }) => (
              <div key={label} className="bg-gray-700/50 rounded-lg p-2">
                <div className="text-xs text-gray-500">{label}</div>
                <div className="text-sm font-semibold text-emerald-300">{val}</div>
              </div>
            ))}
          </div>
          <p className="text-xs text-gray-500 mt-2">BMR: {user.bmr} kcal  TDEE: {user.tdee} kcal</p>
        </Card>
      )}

      <Card>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <Input label="Name" {...register('name')} />
          <div className="grid grid-cols-2 gap-4">
            <Input label="Age (years)" type="number" {...register('age', { valueAsNumber: true })} />
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-1">Gender</label>
              <select {...register('gender')}
                className="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="">Select</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <Input label="Weight (kg)" type="number" step="0.1" {...register('weight_kg', { valueAsNumber: true })} />
            <Input label="Height (cm)" type="number" {...register('height_cm', { valueAsNumber: true })} />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-300 mb-1">Activity Level</label>
            <select {...register('activity_level')}
              className="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
              <option value="sedentary">Sedentary (desk job, no exercise)</option>
              <option value="light">Light (exercise 1-3x/week)</option>
              <option value="moderate">Moderate (exercise 3-5x/week)</option>
              <option value="active">Active (hard exercise 6-7x/week)</option>
              <option value="very_active">Very Active (physical job + training)</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-300 mb-1">Goal</label>
            <select {...register('goal')}
              className="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
              <option value="fat_loss">🔥 Fat Loss</option>
              <option value="maintenance">⚖️ Maintenance</option>
              <option value="muscle_gain">💪 Muscle Gain</option>
            </select>
          </div>
          <Input label="Target Weight (kg, optional)" type="number" step="0.1"
            {...register('target_weight', { valueAsNumber: true })} />

          <div className="border-t border-gray-700 pt-4 mt-4">
            <h3 className="text-sm font-semibold text-gray-300 mb-3 uppercase tracking-wide">Dietary Preferences</h3>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-1">Diet Type</label>
              <select {...register('diet_type')}
                className="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="non_veg">🍗 Non-Veg (everything)</option>
                <option value="eggetarian">🥚 Eggetarian (veg + eggs)</option>
                <option value="veg">🥬 Vegetarian (no meat/eggs)</option>
              </select>
            </div>
            <div className="mt-3">
              <Input label="Allergies (comma-separated)" placeholder="e.g. peanut, gluten, dairy"
                {...register('allergies_text')} />
              <p className="text-xs text-gray-500 mt-1">Dishes with these keywords will be excluded from recommendations.</p>
            </div>
          </div>

          <Button type="submit" loading={isSubmitting} className="w-full">
            Save & Recalculate Targets
          </Button>
        </form>
      </Card>
    </div>
  );
}
