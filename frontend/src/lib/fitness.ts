import type { ActivityLevel, Gender, Goal } from '../types';

const ACTIVITY_MULTIPLIERS: Record<ActivityLevel, number> = {
  sedentary:   1.2,
  light:       1.375,
  moderate:    1.55,
  active:      1.725,
  very_active: 1.9,
};

export function calculateBMR(gender: Gender, weight: number, height: number, age: number): number {
  const base = 10 * weight + 6.25 * height - 5 * age;
  return gender === 'male' ? base + 5 : base - 161;
}

export function calculateTDEE(bmr: number, activityLevel: ActivityLevel): number {
  return Math.round(bmr * ACTIVITY_MULTIPLIERS[activityLevel]);
}

export function calculateTargets(tdee: number, goal: Goal, weightKg: number) {
  let calories: number;
  let protein: number;

  switch (goal) {
    case 'fat_loss':
      calories = tdee - 500;
      protein  = weightKg * 2.2;
      break;
    case 'muscle_gain':
      calories = tdee + 300;
      protein  = weightKg * 2.0;
      break;
    default:
      calories = tdee;
      protein  = weightKg * 1.8;
  }

  const fat   = (calories * 0.25) / 9;
  const carbs = Math.max(0, (calories - protein * 4 - fat * 9) / 4);

  return {
    target_calories: Math.round(calories),
    target_protein:  Math.round(protein),
    target_carbs:    Math.round(carbs),
    target_fat:      Math.round(fat),
  };
}
