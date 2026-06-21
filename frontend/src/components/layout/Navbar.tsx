import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuthStore } from '../../store/authStore';
import api from '../../lib/api';

const employeeLinks = [
  { to: '/dashboard', label: 'Today' },
  { to: '/week', label: 'Week' },
  { to: '/insights', label: 'Insights' },
  { to: '/progress', label: 'Progress' },
  { to: '/profile', label: 'Profile' },
];

const adminLinks = [
  { to: '/admin/menus', label: 'Menus' },
  { to: '/admin/dishes', label: 'Dishes' },
  { to: '/admin/ai-jobs', label: 'AI Jobs' },
];

export function Navbar() {
  const { user, refreshToken, logout } = useAuthStore();
  const navigate = useNavigate();
  const location = useLocation();

  const handleLogout = async () => {
    try { await api.post('/auth/logout', { refresh_token: refreshToken }); } catch {}
    logout();
    navigate('/login');
  };

  const links = user?.role === 'admin' ? adminLinks : employeeLinks;

  return (
    <nav className="bg-gray-900 border-b border-gray-800 sticky top-0 z-50">
      <div className="max-w-6xl mx-auto px-4 flex items-center justify-between h-14">
        <Link to="/" className="text-emerald-400 font-bold text-lg tracking-tight">
          🏋️ GymMeals
        </Link>

        <div className="hidden md:flex items-center gap-1">
          {links.map(({ to, label }) => (
            <Link
              key={to}
              to={to}
              className={`px-3 py-1.5 rounded-lg text-sm transition-colors ${
                location.pathname.startsWith(to)
                  ? 'bg-gray-700 text-white'
                  : 'text-gray-400 hover:text-white hover:bg-gray-800'
              }`}
            >
              {label}
            </Link>
          ))}
        </div>

        <div className="flex items-center gap-3">
          <span className="text-sm text-gray-400 hidden sm:block">{user?.name}</span>
          <button
            onClick={handleLogout}
            className="text-sm text-gray-400 hover:text-red-400 transition-colors"
          >
            Sign out
          </button>
        </div>
      </div>

      {/* Mobile nav */}
      <div className="md:hidden border-t border-gray-800 flex overflow-x-auto">
        {links.map(({ to, label }) => (
          <Link
            key={to}
            to={to}
            className={`flex-shrink-0 px-4 py-2 text-sm transition-colors ${
              location.pathname.startsWith(to)
                ? 'text-emerald-400 border-b-2 border-emerald-400'
                : 'text-gray-400'
            }`}
          >
            {label}
          </Link>
        ))}
      </div>
    </nav>
  );
}
