import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { useAuthStore } from './store/authStore';
import { Navbar } from './components/layout/Navbar';
import { Login } from './pages/auth/Login';
import { Register } from './pages/auth/Register';
import { Wizard } from './pages/wizard/Wizard';
import { Dashboard } from './pages/dashboard/Dashboard';
import { WeekView } from './pages/week/WeekView';
import { Progress } from './pages/progress/Progress';
import { Profile } from './pages/profile/Profile';
import { Insights } from './pages/insights/Insights';
import { AdminMenus } from './pages/admin/Menus';
import { AdminDishes } from './pages/admin/Dishes';
import { AdminAiJobs } from './pages/admin/AiJobs';

function RequireAuth({ children }: { children: React.ReactNode }) {
  const { user } = useAuthStore();
  return user ? <>{children}</> : <Navigate to="/login" replace />;
}

function RequireAdmin({ children }: { children: React.ReactNode }) {
  const { user } = useAuthStore();
  if (!user) return <Navigate to="/login" replace />;
  if (user.role !== 'admin') return <Navigate to="/dashboard" replace />;
  return <>{children}</>;
}

function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-gray-950 text-gray-100">
      <Navbar />
      <main>{children}</main>
    </div>
  );
}

export default function App() {
  const { user } = useAuthStore();

  return (
    <BrowserRouter>
      <Toaster
        position="top-right"
        toastOptions={{
          style: { background: '#1F2937', color: '#F9FAFB', border: '1px solid #374151' },
          success: { iconTheme: { primary: '#10B981', secondary: '#F9FAFB' } },
          error:   { iconTheme: { primary: '#EF4444', secondary: '#F9FAFB' } },
        }}
      />

      <Routes>
        {/* Public */}
        <Route path="/login"    element={user ? <Navigate to="/dashboard" /> : <Login />} />
        <Route path="/register" element={user ? <Navigate to="/dashboard" /> : <Register />} />
        <Route path="/"         element={<Navigate to={user ? '/dashboard' : '/login'} />} />

        {/* Wizard (auth required, no navbar) */}
        <Route path="/wizard" element={<RequireAuth><Wizard /></RequireAuth>} />

        {/* Employee */}
        <Route path="/dashboard" element={<RequireAuth><AppLayout><Dashboard /></AppLayout></RequireAuth>} />
        <Route path="/week"      element={<RequireAuth><AppLayout><WeekView /></AppLayout></RequireAuth>} />
        <Route path="/progress"  element={<RequireAuth><AppLayout><Progress /></AppLayout></RequireAuth>} />
        <Route path="/insights"  element={<RequireAuth><AppLayout><Insights /></AppLayout></RequireAuth>} />
        <Route path="/profile"   element={<RequireAuth><AppLayout><Profile /></AppLayout></RequireAuth>} />

        {/* Admin */}
        <Route path="/admin/menus"   element={<RequireAdmin><AppLayout><AdminMenus /></AppLayout></RequireAdmin>} />
        <Route path="/admin/dishes"  element={<RequireAdmin><AppLayout><AdminDishes /></AppLayout></RequireAdmin>} />
        <Route path="/admin/ai-jobs" element={<RequireAdmin><AppLayout><AdminAiJobs /></AppLayout></RequireAdmin>} />

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/" />} />
      </Routes>
    </BrowserRouter>
  );
}
