<?php

namespace App\Http\Controllers;

use App\Models\WeightHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class WeightController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'weight_kg'   => 'required|numeric|min:20|max:500',
            'recorded_at' => 'sometimes|date',
        ]);

        $user   = JWTAuth::user();
        $entry  = WeightHistory::create([
            'user_id'     => $user->id,
            'weight_kg'   => $data['weight_kg'],
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        // Update user's current weight
        $user->update(['weight_kg' => $data['weight_kg']]);

        return response()->json($entry, 201);
    }

    public function history(Request $request): JsonResponse
    {
        $user    = JWTAuth::user();
        $history = WeightHistory::where('user_id', $user->id)
            ->orderBy('recorded_at', 'desc')
            ->limit(90)
            ->get();

        return response()->json($history);
    }
}
