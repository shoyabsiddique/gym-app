interface Props {
  label: string;
  current: number;
  target: number;
  unit?: string;
  color: 'blue' | 'green' | 'orange' | 'red';
}

const colorMap = {
  blue:   { bar: 'bg-blue-500',   text: 'text-blue-400' },
  green:  { bar: 'bg-green-500',  text: 'text-green-400' },
  orange: { bar: 'bg-orange-500', text: 'text-orange-400' },
  red:    { bar: 'bg-red-500',    text: 'text-red-400' },
};

export function MacroProgressBar({ label, current, target, unit = 'g', color }: Props) {
  const pct = target > 0 ? Math.min((current / target) * 100, 110) : 0;
  const barColor =
    pct > 105 ? 'bg-red-500' :
    pct > 90  ? colorMap[color].bar :
    'bg-gray-500';

  return (
    <div className="mb-3">
      <div className="flex justify-between text-sm mb-1">
        <span className="font-medium text-gray-300">{label}</span>
        <span className={colorMap[color].text}>
          {Math.round(current)} / {target}{unit === 'kcal' ? ' kcal' : `${unit}`}
        </span>
      </div>
      <div className="h-2 bg-gray-700 rounded-full overflow-hidden">
        <div
          className={`h-full rounded-full transition-all duration-500 ${barColor}`}
          style={{ width: `${Math.min(pct, 100)}%` }}
        />
      </div>
    </div>
  );
}
