<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeDishNutrition;
use App\Models\AiJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = AiJob::with('dish')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($jobs);
    }

    public function retry(AiJob $aiJob): JsonResponse
    {
        if ($aiJob->status === 'done') {
            return response()->json(['message' => 'Job already completed'], 400);
        }

        $aiJob->update(['status' => 'pending', 'error' => null]);
        AnalyzeDishNutrition::dispatch($aiJob->dish_id, $aiJob->dish->name);

        return response()->json(['message' => 'Job queued for retry', 'job' => $aiJob->fresh()]);
    }
}
