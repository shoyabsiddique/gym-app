<?php

namespace Tests\Unit;

use App\Services\OptimizerService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OptimizerServiceTest extends TestCase
{
    private OptimizerService $optimizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->optimizer = new OptimizerService();
    }

    private function sampleDishes(): array
    {
        return [
            'breakfast' => [
                ['id' => 1, 'calories' => 250, 'protein' => 6,  'carbs' => 45, 'fat' => 6],
                ['id' => 2, 'calories' => 180, 'protein' => 14, 'carbs' => 2,  'fat' => 12],
                ['id' => 3, 'calories' => 150, 'protein' => 8,  'carbs' => 12, 'fat' => 8],
            ],
            'lunch' => [
                ['id' => 4, 'calories' => 206, 'protein' => 4,  'carbs' => 45, 'fat' => 0],
                ['id' => 5, 'calories' => 165, 'protein' => 9,  'carbs' => 26, 'fat' => 4],
                ['id' => 6, 'calories' => 210, 'protein' => 6,  'carbs' => 40, 'fat' => 4],
            ],
            'snacks' => [
                ['id' => 7, 'calories' => 130, 'protein' => 8,  'carbs' => 22, 'fat' => 2],
            ],
            'dinner' => [
                ['id' => 8,  'calories' => 290, 'protein' => 28, 'carbs' => 10, 'fat' => 16],
                ['id' => 9,  'calories' => 240, 'protein' => 10, 'carbs' => 28, 'fat' => 11],
                ['id' => 10, 'calories' => 55,  'protein' => 2,  'carbs' => 10, 'fat' => 1],
            ],
        ];
    }

    #[Test]
    public function returns_items_for_each_meal_type(): void
    {
        $result     = $this->optimizer->optimize($this->sampleDishes(), [
            'calories' => 2800, 'protein' => 150, 'carbs' => 320, 'fat' => 80,
        ]);
        $mealTypes  = array_unique(array_column($result['items'], 'meal_type'));
        sort($mealTypes);
        $this->assertEquals(['breakfast', 'dinner', 'lunch', 'snacks'], $mealTypes);
    }

    #[Test]
    public function servings_are_within_bounds(): void
    {
        $result = $this->optimizer->optimize($this->sampleDishes(), [
            'calories' => 2800, 'protein' => 150, 'carbs' => 320, 'fat' => 80,
        ]);

        foreach ($result['items'] as $item) {
            $this->assertGreaterThanOrEqual(0.5, $item['servings']);
            $this->assertLessThanOrEqual(3.0,   $item['servings']);
        }
    }

    #[Test]
    public function totals_are_returned(): void
    {
        $result = $this->optimizer->optimize($this->sampleDishes(), [
            'calories' => 2800, 'protein' => 150, 'carbs' => 320, 'fat' => 80,
        ]);

        $this->assertArrayHasKey('totals',    $result);
        $this->assertArrayHasKey('calories',  $result['totals']);
        $this->assertArrayHasKey('protein',   $result['totals']);
        $this->assertArrayHasKey('carbs',     $result['totals']);
        $this->assertArrayHasKey('fat',       $result['totals']);
    }

    #[Test]
    public function empty_dishes_returns_empty_items(): void
    {
        $result = $this->optimizer->optimize([], [
            'calories' => 2800, 'protein' => 150, 'carbs' => 320, 'fat' => 80,
        ]);
        $this->assertEmpty($result['items']);
    }

    #[Test]
    public function single_meal_type_works(): void
    {
        $result = $this->optimizer->optimize(
            ['lunch' => $this->sampleDishes()['lunch']],
            ['calories' => 600, 'protein' => 40, 'carbs' => 80, 'fat' => 15]
        );

        $this->assertNotEmpty($result['items']);
        foreach ($result['items'] as $item) {
            $this->assertEquals('lunch', $item['meal_type']);
        }
    }
}
