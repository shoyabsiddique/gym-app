type Variant = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

const styles: Record<Variant, string> = {
  success: 'bg-emerald-900/50 text-emerald-300 border-emerald-700',
  warning: 'bg-yellow-900/50 text-yellow-300 border-yellow-700',
  danger:  'bg-red-900/50 text-red-300 border-red-700',
  info:    'bg-blue-900/50 text-blue-300 border-blue-700',
  neutral: 'bg-gray-700/50 text-gray-300 border-gray-600',
};

interface Props {
  variant?: Variant;
  children: React.ReactNode;
}

export function Badge({ variant = 'neutral', children }: Props) {
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${styles[variant]}`}>
      {children}
    </span>
  );
}
