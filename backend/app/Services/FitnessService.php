<?php

namespace App\Services;

class FitnessService
{
    private const ACTIVITY_MULTIPLIERS = [
        'sedentary'   => 1.2,
        'light'       => 1.375,
        'moderate'    => 1.55,
        'active'      => 1.725,
        'very_active' => 1.9,
    ];

    public static function calculateBMR(string $gender, float $weight, float $height, int $age): float
    {
        $base = (10 * $weight) + (6.25 * $height) - (5 * $age);
        return $gender === 'male' ? $base + 5 : $base - 161;
    }

    public static function calculateTDEE(float $bmr, string $activityLevel): float
    {
        $multiplier = self::ACTIVITY_MULTIPLIERS[$activityLevel] ?? 1.2;
        return round($bmr * $multiplier, 2);
    }

    public static function calculateTargets(float $tdee, string $goal, float $weightKg): array
    {
        switch ($goal) {
            case 'fat_loss':
                $calories = $tdee - 500;
                $protein  = $weightKg * 2.2;
                break;
            case 'muscle_gain':
                $calories = $tdee + 300;
                $protein  = $weightKg * 2.0;
                break;
            default: // maintenance
                $calories = $tdee;
                $protein  = $weightKg * 1.8;
                break;
        }

        $fat   = ($calories * 0.25) / 9;
        $carbs = ($calories - ($protein * 4) - ($fat * 9)) / 4;

        return [
            'target_calories' => round($calories),
            'target_protein'  => round($protein),
            'target_carbs'    => round(max(0, $carbs)),
            'target_fat'      => round($fat),
        ];
    }

    public static function recalculate(array $user): array
    {
        $bmr  = self::calculateBMR($user['gender'], $user['weight_kg'], $user['height_cm'], $user['age']);
        $tdee = self::calculateTDEE($bmr, $user['activity_level']);
        $targets = self::calculateTargets($tdee, $user['goal'], $user['weight_kg']);

        return array_merge(['bmr' => round($bmr), 'tdee' => round($tdee)], $targets);
    }
}
