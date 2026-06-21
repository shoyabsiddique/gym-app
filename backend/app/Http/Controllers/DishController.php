<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeDishNutrition;
use App\Models\AiJob;
use App\Models\Dish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DishController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $dishes = Dish::with('aiJob')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate(30);

        return response()->json($dishes);
    }

    public function show(Dish $dish): JsonResponse
    {
        return response()->json($dish->load('aiJob'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'required|string|unique:dishes,name',
            'serving_size' => 'sometimes|string',
            'calories'     => 'sometimes|numeric|min:0',
            'protein'      => 'sometimes|numeric|min:0',
            'carbs'        => 'sometimes|numeric|min:0',
            'fat'          => 'sometimes|numeric|min:0',
            'fiber'        => 'sometimes|numeric|min:0',
            'sugar'        => 'sometimes|numeric|min:0',
            'sodium'       => 'sometimes|numeric|min:0',
        ]);

        $dish = Dish::create($data);

        if (!isset($data['calories']) || $data['calories'] == 0) {
            AiJob::create(['dish_id' => $dish->id, 'status' => 'pending']);
            AnalyzeDishNutrition::dispatch($dish->id, $dish->name);
        }

        return response()->json($dish->load('aiJob'), 201);
    }

    public function update(Request $request, Dish $dish): JsonResponse
    {
        $data = $request->validate([
            'name'         => "sometimes|string|unique:dishes,name,{$dish->id}",
            'serving_size' => 'sometimes|string',
            'calories'     => 'sometimes|numeric|min:0',
            'protein'      => 'sometimes|numeric|min:0',
            'carbs'        => 'sometimes|numeric|min:0',
            'fat'          => 'sometimes|numeric|min:0',
            'fiber'        => 'sometimes|numeric|min:0',
            'sugar'        => 'sometimes|numeric|min:0',
            'sodium'       => 'sometimes|numeric|min:0',
        ]);

        $dish->update($data);

        return response()->json($dish->load('aiJob'));
    }

    public function destroy(Dish $dish): JsonResponse
    {
        $dish->delete();
        return response()->json(['message' => 'Dish deleted']);
    }
}
