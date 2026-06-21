<?php

namespace App\Jobs;

use App\Models\AiJob;
use App\Models\Dish;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeDishNutrition implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $dishId,
        public readonly string $dishName,
    ) {}

    public function handle(GeminiService $gemini): void
    {
        $doneJob = AiJob::where('dish_id', $this->dishId)->where('status', 'done')->first();
        if ($doneJob) {
            Log::info("Skipping AI analysis for dish {$this->dishId} — already done.");
            return;
        }

        AiJob::where('dish_id', $this->dishId)->update(['status' => 'processing']);

        try {
            $nutrition = $gemini->analyzeDishNutrition($this->dishName);

            Dish::where('id', $this->dishId)->update([
                'serving_size' => $nutrition['serving_size'] ?? '1 serving',
                'calories'     => $nutrition['calories'],
                'protein'      => $nutrition['protein'],
                'carbs'        => $nutrition['carbs'],
                'fat'          => $nutrition['fat'],
                'fiber'        => $nutrition['fiber'],
                'sugar'        => $nutrition['sugar'],
                'sodium'       => $nutrition['sodium'],
                'ai_generated' => true,
            ]);

            AiJob::where('dish_id', $this->dishId)->update(['status' => 'done', 'error' => null]);

            Log::info("AI nutrition analysis complete for dish {$this->dishId} ({$this->dishName})");
        } catch (\Throwable $e) {
            AiJob::where('dish_id', $this->dishId)->update([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
            Log::error("AI analysis failed for dish {$this->dishId}: " . $e->getMessage());
            throw $e;
        }
    }
}
