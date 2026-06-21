import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Link, useNavigate } from 'react-router-dom';
import { toast } from 'react-hot-toast';
import api from '../../lib/api';
import { useAuthStore } from '../../store/authStore';
import { Input } from '../../components/ui/Input';
import { Button } from '../../components/ui/Button';

const schema = z.object({
  email:    z.string().email('Invalid email'),
  password: z.string().min(1, 'Password required'),
});
type Form = z.infer<typeof schema>;

export function Login() {
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<Form>({ resolver: zodResolver(schema) });
  const { setAuth } = useAuthStore();
  const navigate = useNavigate();

  const onSubmit = async (data: Form) => {
    try {
      const res = await api.post('/auth/login', data);
      setAuth(res.data.user, res.data.access_token, res.data.refresh_token);
      navigate(res.data.user.role === 'admin' ? '/admin/menus' : '/dashboard');
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Login failed');
    }
  };

  return (
    <div className="min-h-screen bg-gray-950 flex items-center justify-center px-4">
      <div className="w-full max-w-sm">
        <div className="text-center mb-8">
          <div className="text-5xl mb-3">🏋️</div>
          <h1 className="text-2xl font-bold text-white">GymMeals</h1>
          <p className="text-gray-400 mt-1">Office Nutrition Planner</p>
        </div>

        <div className="bg-gray-800 border border-gray-700 rounded-2xl p-6">
          <h2 className="text-lg font-semibold text-white mb-5">Sign In</h2>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <Input label="Email" type="email" placeholder="you@company.com" {...register('email')} error={errors.email?.message} />
            <Input label="Password" type="password" placeholder="••••••••" {...register('password')} error={errors.password?.message} />
            <Button type="submit" loading={isSubmitting} className="w-full" size="lg">
              Sign In
            </Button>
          </form>
          <p className="mt-4 text-center text-sm text-gray-400">
            No account?{' '}
            <Link to="/register" className="text-emerald-400 hover:underline">Register</Link>
          </p>
        </div>
      </div>
    </div>
  );
}
