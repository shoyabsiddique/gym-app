import type { MealType, RecommendationItem } from '../types';
import { Card } from './ui/Card';

const mealIcons: Record<MealType, string> = {
  breakfast: '🌅',
  lunch:     '☀️',
  snacks:    '🍎',
  dinner:    '🌙',
};

interface Props {
  mealType: MealType;
  items: RecommendationItem[];
}

export function MealCard({ mealType, items }: Props) {
  const totalCal = items.reduce((sum, i) => sum + i.dish.calories * i.servings, 0);

  return (
    <Card>
      <div className="flex items-center justify-between mb-3">
        <h3 className="font-semibold text-gray-100 capitalize">
          {mealIcons[mealType]} {mealType}
        </h3>
        <span className="text-xs text-gray-400">{Math.round(totalCal)} kcal</span>
      </div>
      <ul className="space-y-2">
        {items.map((item, i) => (
          <li key={i} className="flex items-center justify-between text-sm">
            <span className="text-gray-300">{item.dish.name}</span>
            <div className="text-right text-gray-500">
              <span>{item.servings}× serving</span>
              <span className="ml-2 text-emerald-400">{Math.round(item.dish.protein * item.servings)}g pro</span>
            </div>
          </li>
        ))}
      </ul>
    </Card>
  );
}
