<?php

namespace Database\Seeders;

use App\Models\Dish;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@company.com')],
            [
                'name'     => 'Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin@123')),
                'role'     => 'admin',
            ]
        );

        // Sample employee
        User::firstOrCreate(
            ['email' => 'employee@company.com'],
            [
                'name'           => 'Raj Kumar',
                'password'       => Hash::make('Employee@123'),
                'role'           => 'employee',
                'age'            => 28,
                'gender'         => 'male',
                'weight_kg'      => 75,
                'height_cm'      => 175,
                'activity_level' => 'moderate',
                'goal'           => 'muscle_gain',
                'bmr'            => 1806.25,
                'tdee'           => 2799.69,
                'target_calories'=> 3100,
                'target_protein' => 150,
                'target_carbs'   => 355,
                'target_fat'     => 86,
            ]
        );

        // 20 Indian cafeteria dishes with realistic nutrition
        $dishes = [
            ['name' => 'Poha',                'serving_size' => '1 plate (200g)',  'calories' => 250, 'protein' => 6,  'carbs' => 45, 'fat' => 6,  'fiber' => 3, 'sugar' => 3, 'sodium' => 320],
            ['name' => 'Omelette',            'serving_size' => '2 eggs',          'calories' => 180, 'protein' => 14, 'carbs' => 2,  'fat' => 12, 'fiber' => 0, 'sugar' => 1, 'sodium' => 380],
            ['name' => 'Boiled Eggs',         'serving_size' => '2 eggs',          'calories' => 155, 'protein' => 13, 'carbs' => 1,  'fat' => 11, 'fiber' => 0, 'sugar' => 1, 'sodium' => 124],
            ['name' => 'Idli',                'serving_size' => '3 pieces',        'calories' => 195, 'protein' => 6,  'carbs' => 38, 'fat' => 2,  'fiber' => 2, 'sugar' => 1, 'sodium' => 390],
            ['name' => 'Upma',                'serving_size' => '1 bowl (200g)',   'calories' => 230, 'protein' => 5,  'carbs' => 40, 'fat' => 7,  'fiber' => 3, 'sugar' => 2, 'sodium' => 350],
            ['name' => 'Rice',                'serving_size' => '1 cup cooked',    'calories' => 206, 'protein' => 4,  'carbs' => 45, 'fat' => 0,  'fiber' => 1, 'sugar' => 0, 'sodium' => 1],
            ['name' => 'Dal Tadka',           'serving_size' => '1 bowl (200ml)',  'calories' => 165, 'protein' => 9,  'carbs' => 26, 'fat' => 4,  'fiber' => 5, 'sugar' => 3, 'sodium' => 440],
            ['name' => 'Paneer Butter Masala','serving_size' => '1 bowl (200g)',   'calories' => 320, 'protein' => 16, 'carbs' => 18, 'fat' => 22, 'fiber' => 3, 'sugar' => 8, 'sodium' => 520],
            ['name' => 'Roti',                'serving_size' => '2 rotis',         'calories' => 210, 'protein' => 6,  'carbs' => 40, 'fat' => 4,  'fiber' => 3, 'sugar' => 1, 'sodium' => 230],
            ['name' => 'Rajma',               'serving_size' => '1 bowl (200ml)',  'calories' => 200, 'protein' => 12, 'carbs' => 35, 'fat' => 3,  'fiber' => 8, 'sugar' => 4, 'sodium' => 410],
            ['name' => 'Chicken Curry',       'serving_size' => '1 bowl (200g)',   'calories' => 290, 'protein' => 28, 'carbs' => 10, 'fat' => 16, 'fiber' => 2, 'sugar' => 4, 'sodium' => 580],
            ['name' => 'Dal Makhani',         'serving_size' => '1 bowl (200ml)',  'calories' => 240, 'protein' => 10, 'carbs' => 28, 'fat' => 11, 'fiber' => 6, 'sugar' => 4, 'sodium' => 490],
            ['name' => 'Sprouts Chaat',       'serving_size' => '1 bowl (150g)',   'calories' => 130, 'protein' => 8,  'carbs' => 22, 'fat' => 2,  'fiber' => 6, 'sugar' => 4, 'sodium' => 220],
            ['name' => 'Banana',              'serving_size' => '1 medium',        'calories' => 105, 'protein' => 1,  'carbs' => 27, 'fat' => 0,  'fiber' => 3, 'sugar' => 14,'sodium' => 1],
            ['name' => 'Milk',                'serving_size' => '1 glass (250ml)', 'calories' => 150, 'protein' => 8,  'carbs' => 12, 'fat' => 8,  'fiber' => 0, 'sugar' => 12,'sodium' => 105],
            ['name' => 'Tea',                 'serving_size' => '1 cup',           'calories' => 45,  'protein' => 1,  'carbs' => 6,  'fat' => 2,  'fiber' => 0, 'sugar' => 5, 'sodium' => 15],
            ['name' => 'Salad',               'serving_size' => '1 bowl (150g)',   'calories' => 55,  'protein' => 2,  'carbs' => 10, 'fat' => 1,  'fiber' => 4, 'sugar' => 5, 'sodium' => 80],
            ['name' => 'Curd Rice',           'serving_size' => '1 plate (250g)',  'calories' => 280, 'protein' => 8,  'carbs' => 48, 'fat' => 7,  'fiber' => 1, 'sugar' => 6, 'sodium' => 310],
            ['name' => 'Sambar',              'serving_size' => '1 bowl (200ml)',  'calories' => 110, 'protein' => 5,  'carbs' => 18, 'fat' => 3,  'fiber' => 4, 'sugar' => 5, 'sodium' => 460],
            ['name' => 'Aloo Sabzi',          'serving_size' => '1 bowl (150g)',   'calories' => 180, 'protein' => 3,  'carbs' => 32, 'fat' => 6,  'fiber' => 3, 'sugar' => 3, 'sodium' => 350],
        ];

        $dishModels = [];
        foreach ($dishes as $d) {
            $dishModels[$d['name']] = Dish::firstOrCreate(
                ['name' => $d['name']],
                array_merge($d, ['ai_generated' => true])
            );
        }

        // 2-week sample menu
        $today    = now()->startOfWeek(\Carbon\Carbon::MONDAY);
        $menuData = [
            'breakfast' => ['Poha', 'Omelette', 'Boiled Eggs', 'Milk', 'Banana', 'Tea'],
            'lunch'     => ['Rice', 'Dal Tadka', 'Paneer Butter Masala', 'Roti', 'Rajma', 'Sambar', 'Salad'],
            'snacks'    => ['Sprouts Chaat', 'Tea', 'Banana'],
            'dinner'    => ['Chicken Curry', 'Dal Makhani', 'Rice', 'Roti', 'Aloo Sabzi', 'Salad'],
        ];

        for ($week = 0; $week < 2; $week++) {
            for ($day = 0; $day < 5; $day++) { // Mon–Fri
                $date = $today->copy()->addWeeks($week)->addDays($day)->toDateString();
                foreach ($menuData as $mealType => $dishNames) {
                    $menu = Menu::firstOrCreate([
                        'menu_date' => $date,
                        'meal_type' => $mealType,
                    ]);
                    foreach ($dishNames as $name) {
                        if (isset($dishModels[$name])) {
                            $menu->dishes()->syncWithoutDetaching([$dishModels[$name]->id]);
                        }
                    }
                }
            }
        }

        $this->command->info('Seeded: admin user, 20 dishes, 2-week menu');
    }
}
