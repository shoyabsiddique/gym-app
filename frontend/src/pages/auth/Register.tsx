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
  name:                  z.string().min(2, 'Name required'),
  email:                 z.string().email('Invalid email'),
  password:              z.string().min(8, 'Minimum 8 characters'),
  password_confirmation: z.string(),
}).refine((d) => d.password === d.password_confirmation, {
  message: 'Passwords do not match',
  path: ['password_confirmation'],
});
type Form = z.infer<typeof schema>;

export function Register() {
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<Form>({ resolver: zodResolver(schema) });
  const { setAuth } = useAuthStore();
  const navigate = useNavigate();

  const onSubmit = async (data: Form) => {
    try {
      const res = await api.post('/auth/register', data);
      setAuth(res.data.user, res.data.access_token, res.data.refresh_token || '');
      navigate('/wizard');
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Registration failed');
    }
  };

  return (
    <div className="min-h-screen bg-gray-950 flex items-center justify-center px-4">
      <div className="w-full max-w-sm">
        <div className="text-center mb-8">
          <div className="text-5xl mb-3">🏋️</div>
          <h1 className="text-2xl font-bold text-white">GymMeals</h1>
          <p className="text-gray-400 mt-1">Create your account</p>
        </div>

        <div className="bg-gray-800 border border-gray-700 rounded-2xl p-6">
          <h2 className="text-lg font-semibold text-white mb-5">Create Account</h2>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <Input label="Full Name" placeholder="Raj Kumar" {...register('name')} error={errors.name?.message} />
            <Input label="Email" type="email" placeholder="you@company.com" {...register('email')} error={errors.email?.message} />
            <Input label="Password" type="password" placeholder="Min 8 characters" {...register('password')} error={errors.password?.message} />
            <Input label="Confirm Password" type="password" placeholder="Repeat password" {...register('password_confirmation')} error={errors.password_confirmation?.message} />
            <Button type="submit" loading={isSubmitting} className="w-full" size="lg">
              Create Account
            </Button>
          </form>
          <p className="mt-4 text-center text-sm text-gray-400">
            Already have an account?{' '}
            <Link to="/login" className="text-emerald-400 hover:underline">Sign in</Link>
          </p>
        </div>
      </div>
    </div>
  );
}
