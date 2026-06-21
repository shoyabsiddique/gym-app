export type Role = 'admin' | 'employee';
export type Gender = 'male' | 'female';
export type ActivityLevel = 'sedentary' | 'light' | 'moderate' | 'active' | 'very_active';
export type Goal = 'fat_loss' | 'maintenance' | 'muscle_gain';
export type MealType = 'breakfast' | 'lunch' | 'snacks' | 'dinner';
export type AiJobStatus = 'pending' | 'processing' | 'done' | 'failed';
export type DietType = 'veg' | 'non_veg' | 'eggetarian';

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
  age?: number;
  gender?: Gender;
  weight_kg?: number;
  height_cm?: number;
  activity_level?: ActivityLevel;
  goal?: Goal;
  target_weight?: number;
  bmr?: number;
  tdee?: number;
  target_calories?: number;
  target_protein?: number;
  target_carbs?: number;
  target_fat?: number;
  diet_type?: DietType;
  allergies?: string[];
}

export interface Dish {
  id: number;
  name: string;
  serving_size: string;
  calories: number;
  protein: number;
  carbs: number;
  fat: number;
  fiber: number;
  sugar: number;
  sodium: number;
  ai_generated: boolean;
  diet_type?: DietType;
  ai_job?: AiJob;
}

export interface Menu {
  id: number;
  menu_date: string;
  meal_type: MealType;
  counter: string;
  dishes: Dish[];
}

export interface AiJob {
  id: number;
  dish_id: number;
  status: AiJobStatus;
  error?: string;
  dish?: Dish;
}

export interface RecommendationItem {
  dish: Dish;
  counter: string;
  servings: number;
}

export interface Recommendation {
  id: number;
  date: string;
  targets: MacroTarget;
  totals: MacroActual;
  meals: Record<MealType, RecommendationItem[]>;
}

export interface MacroTarget {
  calories: number;
  protein: number;
  carbs: number;
  fat: number;
}

export interface MacroActual {
  calories: number;
  protein: number;
  carbs: number;
  fat: number;
}

export interface WeightEntry {
  id: number;
  weight_kg: number;
  recorded_at: string;
}

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
}

export interface MealLog {
  id: number;
  user_id: number;
  dish_id: number;
  meal_type: MealType;
  log_date: string;
  servings: number;
  dish: Dish;
}

export interface SwapAlternative {
  dish: Dish;
  counter: string;
  score: number;
  cal_diff: number;
  prot_diff: number;
}

export interface InsightsData {
  streak: number;
  daily: DailyLog[];
  avg_macros: MacroActual;
  targets: MacroTarget;
  weight_trend: WeightTrend | null;
  tips: string[];
  logged_days: number;
}

export interface DailyLog {
  date: string;
  logged: boolean;
  totals: MacroActual;
  dish_count: number;
}

export interface WeightTrend {
  direction: 'up' | 'down' | 'stable';
  change_kg: number;
  latest_kg: number;
  entries: number;
}
