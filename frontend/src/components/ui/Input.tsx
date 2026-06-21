import { type InputHTMLAttributes, forwardRef } from 'react';

interface Props extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
}

export const Input = forwardRef<HTMLInputElement, Props>(
  ({ label, error, className = '', ...props }, ref) => (
    <div className="w-full">
      {label && (
        <label className="block text-sm font-medium text-gray-300 mb-1">{label}</label>
      )}
      <input
        ref={ref}
        className={`w-full bg-gray-700 border border-gray-600 text-gray-100 rounded-lg px-3 py-2 text-sm
          focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent
          placeholder-gray-500 disabled:opacity-50 ${error ? 'border-red-500' : ''} ${className}`}
        {...props}
      />
      {error && <p className="mt-1 text-xs text-red-400">{error}</p>}
    </div>
  )
);
Input.displayName = 'Input';
