interface Props {
  children: React.ReactNode;
  className?: string;
}

export function Card({ children, className = '' }: Props) {
  return (
    <div className={`bg-gray-800 border border-gray-700 rounded-xl p-5 shadow-lg ${className}`}>
      {children}
    </div>
  );
}
