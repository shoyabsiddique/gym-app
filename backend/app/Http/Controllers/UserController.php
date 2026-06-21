<?php

namespace App\Http\Controllers;

use App\Services\FitnessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    public function profile(): JsonResponse
    {
        return response()->json(JWTAuth::user());
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = JWTAuth::user();

        $data = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'age'            => 'sometimes|integer|min:10|max:120',
            'gender'         => 'sometimes|in:male,female',
            'weight_kg'      => 'sometimes|numeric|min:20|max:500',
            'height_cm'      => 'sometimes|numeric|min:50|max:300',
            'activity_level' => 'sometimes|in:sedentary,light,moderate,active,very_active',
            'goal'           => 'sometimes|in:fat_loss,maintenance,muscle_gain',
            'target_weight'  => 'sometimes|nullable|numeric|min:20|max:500',
            'diet_type'      => 'sometimes|in:veg,non_veg,eggetarian',
            'allergies'      => 'sometimes|nullable|array',
            'allergies.*'    => 'string|max:100',
        ]);

        $user->fill($data);

        if ($user->isDirty(['weight_kg', 'height_cm', 'age', 'gender', 'activity_level', 'goal'])) {
            $required = ['gender', 'weight_kg', 'height_cm', 'age', 'activity_level', 'goal'];
            $profile  = array_merge($user->toArray(), $data);
            $allSet   = collect($required)->every(fn($k) => !empty($profile[$k]));

            if ($allSet) {
                $calc = FitnessService::recalculate($profile);
                $user->fill($calc);
            }
        }

        $user->save();

        return response()->json($user);
    }
}
