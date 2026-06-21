<?php

namespace App\Http\Controllers;

use App\Models\MealLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class MealLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = JWTAuth::user();
        $date = $request->input('date', now()->toDateString());
        $logs = MealLog::where('user_id', $user->id)
            ->whereDate('log_date', $date)
            ->with('dish')
            ->orderBy('meal_type')
            ->get();
        return response()->json($logs);
    }

    public function store(Request $request): JsonResponse
    {
        $user = JWTAuth::user();
        $data = $request->validate([
            'dish_id'   => 'required|exists:dishes,id',
            'meal_type' => 'required|in:breakfast,lunch,snacks,dinner',
            'log_date'  => 'required|date',
            'servings'  => 'required|numeric|min:0.5|max:5',
        ]);
        $log = MealLog::updateOrCreate(
            ['user_id' => $user->id, 'dish_id' => $data['dish_id'], 'meal_type' => $data['meal_type'], 'log_date' => $data['log_date']],
            ['servings' => $data['servings']]
        );
        return response()->json($log->load('dish'), 201);
    }

    public function destroy(MealLog $mealLog): JsonResponse
    {
        $user = JWTAuth::user();
        if ($mealLog->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $mealLog->delete();
        return response()->json(['message' => 'Log removed']);
    }
}
