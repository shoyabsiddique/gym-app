<?php

namespace Tests\Unit;

use App\Services\FitnessService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FitnessServiceTest extends TestCase
{
    #[Test]
    public function calculates_bmr_for_male(): void
    {
        // 10*80 + 6.25*175 - 5*30 + 5 = 800 + 1093.75 - 150 + 5 = 1748.75
        $bmr = FitnessService::calculateBMR('male', 80, 175, 30);
        $this->assertEqualsWithDelta(1748.75, $bmr, 0.01);
    }

    #[Test]
    public function calculates_bmr_for_female(): void
    {
        // 10*65 + 6.25*162 - 5*25 - 161 = 650 + 1012.5 - 125 - 161 = 1376.5
        $bmr = FitnessService::calculateBMR('female', 65, 162, 25);
        $this->assertEqualsWithDelta(1376.5, $bmr, 0.01);
    }

    #[Test]
    public function calculates_tdee_for_all_activity_levels(): void
    {
        $bmr = 1748.75;
        $this->assertEqualsWithDelta(1748.75 * 1.2,    FitnessService::calculateTDEE($bmr, 'sedentary'),   0.01);
        $this->assertEqualsWithDelta(1748.75 * 1.375,  FitnessService::calculateTDEE($bmr, 'light'),       0.01);
        $this->assertEqualsWithDelta(1748.75 * 1.55,   FitnessService::calculateTDEE($bmr, 'moderate'),    0.01);
        $this->assertEqualsWithDelta(1748.75 * 1.725,  FitnessService::calculateTDEE($bmr, 'active'),      0.01);
        $this->assertEqualsWithDelta(1748.75 * 1.9,    FitnessService::calculateTDEE($bmr, 'very_active'), 0.01);
    }

    #[Test]
    public function fat_loss_target_reduces_calories_by_500(): void
    {
        $tdee    = 2500.0;
        $targets = FitnessService::calculateTargets($tdee, 'fat_loss', 75);
        $this->assertEquals(2000, $targets['target_calories']);
        $this->assertEquals(round(75 * 2.2), $targets['target_protein']);
    }

    #[Test]
    public function maintenance_target_equals_tdee(): void
    {
        $tdee    = 2500.0;
        $targets = FitnessService::calculateTargets($tdee, 'maintenance', 75);
        $this->assertEquals(2500, $targets['target_calories']);
        $this->assertEquals(round(75 * 1.8), $targets['target_protein']);
    }

    #[Test]
    public function muscle_gain_target_adds_300_calories(): void
    {
        $tdee    = 2500.0;
        $targets = FitnessService::calculateTargets($tdee, 'muscle_gain', 75);
        $this->assertEquals(2800, $targets['target_calories']);
        $this->assertEquals(round(75 * 2.0), $targets['target_protein']);
    }

    #[Test]
    public function carbs_and_fat_are_non_negative(): void
    {
        foreach (['fat_loss', 'maintenance', 'muscle_gain'] as $goal) {
            $targets = FitnessService::calculateTargets(2000.0, $goal, 70);
            $this->assertGreaterThanOrEqual(0, $targets['target_carbs']);
            $this->assertGreaterThanOrEqual(0, $targets['target_fat']);
        }
    }

    #[Test]
    public function recalculate_returns_all_fields(): void
    {
        $result = FitnessService::recalculate([
            'gender'         => 'male',
            'weight_kg'      => 75,
            'height_cm'      => 175,
            'age'            => 28,
            'activity_level' => 'moderate',
            'goal'           => 'muscle_gain',
        ]);

        $this->assertArrayHasKey('bmr',              $result);
        $this->assertArrayHasKey('tdee',             $result);
        $this->assertArrayHasKey('target_calories',  $result);
        $this->assertArrayHasKey('target_protein',   $result);
        $this->assertArrayHasKey('target_carbs',     $result);
        $this->assertArrayHasKey('target_fat',       $result);
    }
}
