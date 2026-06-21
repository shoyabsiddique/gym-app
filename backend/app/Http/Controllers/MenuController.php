<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\Menu;
use App\Models\MenuDish;
use App\Services\GeminiService;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TCPDF;

class MenuController extends Controller
{
    public function __construct(
        private MenuService $menuService,
        private GeminiService $geminiService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Menu::with('dishes');

        if ($request->has('date')) {
            $query->whereDate('menu_date', $request->date);
        }
        if ($request->has('week_start')) {
            $end = Carbon::parse($request->week_start)->addDays(6)->toDateString();
            $query->whereBetween('menu_date', [$request->week_start, $end]);
        }

        return response()->json($query->orderBy('menu_date')->orderBy('meal_type')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'menu_date'  => 'required|date',
            'meal_type'  => 'required|in:breakfast,lunch,snacks,dinner',
            'dish_ids'   => 'required|array|min:1',
            'dish_ids.*' => 'exists:dishes,id',
        ]);

        $menu = Menu::firstOrCreate([
            'menu_date' => $data['menu_date'],
            'meal_type' => $data['meal_type'],
        ]);

        $menu->dishes()->syncWithoutDetaching($data['dish_ids']);
        $menu->load('dishes');

        return response()->json($menu, 201);
    }

    public function update(Request $request, Menu $menu): JsonResponse
    {
        $data = $request->validate([
            'menu_date'  => 'sometimes|date',
            'meal_type'  => 'sometimes|in:breakfast,lunch,snacks,dinner',
            'dish_ids'   => 'sometimes|array',
            'dish_ids.*' => 'exists:dishes,id',
        ]);

        $menu->update(array_filter($data, fn($k) => $k !== 'dish_ids', ARRAY_FILTER_USE_KEY));

        if (isset($data['dish_ids'])) {
            $menu->dishes()->sync($data['dish_ids']);
        }

        return response()->json($menu->load('dishes'));
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $menu->delete();
        return response()->json(['message' => 'Menu deleted']);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['menu' => 'required|array']);
        $result = $this->menuService->importFromJson($request->menu);
        return response()->json(['message' => 'Import successful', 'summary' => $result], 201);
    }

    public function uploadImages(Request $request): JsonResponse
    {
        set_time_limit(300);

        Log::info('[MenuUpload] ========== UPLOAD STARTED ==========');

        $request->validate([
            'images'      => 'required|array|min:1|max:20',
            'images.*'    => 'file|mimes:jpg,jpeg,png,webp,gif|max:10240',
            'monday_date' => 'required|date',
        ]);

        $monday = Carbon::parse($request->monday_date)
            ->startOfWeek(Carbon::MONDAY)
            ->toDateString();

        Log::info('[MenuUpload] Validated request', [
            'image_count' => count($request->file('images')),
            'monday_date' => $monday,
        ]);

        // --- Step 1: Stitch images into a single PDF ---
        Log::info('[MenuUpload] Step 1: Stitching images into PDF...');
        $pdfStart = microtime(true);

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);

        foreach ($request->file('images') as $i => $file) {
            $pdf->AddPage();
            $imgPath = $file->getRealPath();
            $ext     = strtolower($file->getClientOriginalExtension());
            $type    = in_array($ext, ['jpg', 'jpeg']) ? 'JPEG' : strtoupper($ext);
            $pdf->Image($imgPath, 5, 5, 287, 200, $type, '', '', true, 150, '', false, false, 0);

            Log::info("[MenuUpload] Added image " . ($i + 1) . " to PDF", [
                'name'    => $file->getClientOriginalName(),
                'size_kb' => round($file->getSize() / 1024),
                'type'    => $type,
            ]);
        }

        $pdfContent = $pdf->Output('', 'S');
        $base64Pdf  = base64_encode($pdfContent);

        Log::info('[MenuUpload] PDF created', [
            'elapsed_sec' => round(microtime(true) - $pdfStart, 2),
            'pages'       => count($request->file('images')),
            'pdf_kb'      => round(strlen($pdfContent) / 1024),
            'base64_kb'   => round(strlen($base64Pdf) / 1024),
        ]);

        // --- Step 2: Gemini parses PDF → day × meal × counter × [dish names] ---
        Log::info('[MenuUpload] Step 2: Sending PDF to Gemini...');
        $parseStart = microtime(true);

        try {
            $parsed = $this->geminiService->parseMenuPdf($base64Pdf, $monday);
        } catch (\RuntimeException $e) {
            Log::error('[MenuUpload] Gemini parseMenuPdf FAILED', [
                'error'       => $e->getMessage(),
                'elapsed_sec' => round(microtime(true) - $parseStart, 2),
            ]);
            return response()->json(['message' => 'Failed to parse menu: ' . $e->getMessage()], 422);
        }

        Log::info('[MenuUpload] Gemini parseMenuPdf completed', [
            'elapsed_sec' => round(microtime(true) - $parseStart, 2),
            'dates_found' => array_keys($parsed),
            'raw_parsed'  => $parsed,
        ]);

        // Normalise: date → meal_type → counter → [dishes]
        $validMealTypes = ['breakfast', 'lunch', 'snacks', 'dinner'];
        $normalised     = [];
        $allDishNames   = [];

        foreach ($parsed as $date => $meals) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                Log::warning("[MenuUpload] Skipping invalid date key", ['key' => $date]);
                continue;
            }
            $normalised[$date] = [];
            foreach ($meals as $mealType => $counters) {
                $mt = strtolower($mealType);
                if (!in_array($mt, $validMealTypes)) {
                    Log::warning("[MenuUpload] Skipping invalid meal type", ['meal_type' => $mealType]);
                    continue;
                }

                if (isset($counters[0]) && is_string($counters[0])) {
                    Log::info("[MenuUpload] Flat format detected for {$date}/{$mt}, wrapping in General");
                    $counters = ['General' => $counters];
                }

                $normalised[$date][$mt] = [];
                foreach ($counters as $counter => $dishes) {
                    $counterName = trim((string) $counter) ?: 'General';
                    $clean = array_values(array_filter(
                        array_map('trim', (array) $dishes),
                        fn($d) => strlen($d) > 1
                    ));
                    if (empty($clean)) continue;
                    $normalised[$date][$mt][$counterName] = $clean;
                    $allDishNames = array_merge($allDishNames, $clean);
                    Log::info("[MenuUpload] {$date} / {$mt} / {$counterName}: " . count($clean) . " dishes", [
                        'dishes' => $clean,
                    ]);
                }
                if (empty($normalised[$date][$mt])) unset($normalised[$date][$mt]);
            }
            if (empty($normalised[$date])) unset($normalised[$date]);
        }

        $allDishNames = array_unique($allDishNames);

        Log::info('[MenuUpload] Normalisation complete', [
            'normalised_dates' => array_keys($normalised),
            'total_unique_dishes' => count($allDishNames),
            'dish_names' => array_values($allDishNames),
        ]);

        if (empty($normalised)) {
            Log::error('[MenuUpload] No dishes extracted after normalisation');
            return response()->json(['message' => 'No dishes could be extracted from the images'], 422);
        }

        // --- Step 3: Identify new dishes that need nutrition ---
        $existingDishes = Dish::whereIn('name', $allDishNames)->get()->keyBy('name');
        $newDishNames   = array_values(array_diff($allDishNames, $existingDishes->keys()->toArray()));

        Log::info('[MenuUpload] Dish lookup complete', [
            'total_unique'   => count($allDishNames),
            'already_exist'  => count($existingDishes),
            'need_nutrition' => count($newDishNames),
            'existing_names' => $existingDishes->keys()->toArray(),
            'new_names'      => $newDishNames,
        ]);

        // --- Step 4: Batch get nutrition for new dishes ---
        $nutritionMap = [];
        if (!empty($newDishNames)) {
            $chunks = array_chunk($newDishNames, 30);
            Log::info('[MenuUpload] Fetching nutrition in batches', [
                'total_new' => count($newDishNames),
                'chunks'    => count($chunks),
            ]);

            foreach ($chunks as $ci => $chunk) {
                Log::info("[MenuUpload] Nutrition batch " . ($ci + 1) . "/" . count($chunks), [
                    'dishes' => $chunk,
                ]);
                $batchStart = microtime(true);
                try {
                    $batch = $this->geminiService->analyzeMultipleDishes($chunk);
                    $nutritionMap = array_merge($nutritionMap, $batch);
                    Log::info("[MenuUpload] Nutrition batch " . ($ci + 1) . " done", [
                        'elapsed_sec' => round(microtime(true) - $batchStart, 2),
                        'returned'    => count($batch),
                    ]);
                } catch (\RuntimeException $e) {
                    Log::error("[MenuUpload] Nutrition batch " . ($ci + 1) . " FAILED", [
                        'elapsed_sec' => round(microtime(true) - $batchStart, 2),
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        } else {
            Log::info('[MenuUpload] No new dishes — skipping nutrition fetch');
        }

        Log::info('[MenuUpload] Nutrition fetching complete', [
            'total_fetched' => count($nutritionMap),
            'fetched_names' => array_keys($nutritionMap),
        ]);

        // --- Step 5: Save everything to DB (date → meal_type → counter → dishes) ---
        $stats = ['menus' => 0, 'new_dishes' => 0, 'existing_dishes' => 0, 'nutrition_fetched' => count($nutritionMap)];

        Log::info('[MenuUpload] Starting DB transaction...');
        $dbStart = microtime(true);

        DB::transaction(function () use ($normalised, $existingDishes, $nutritionMap, &$stats) {
            foreach ($normalised as $date => $meals) {
                foreach ($meals as $mealType => $counters) {
                    foreach ($counters as $counterName => $dishNames) {
                        $menu = Menu::firstOrCreate([
                            'menu_date' => $date,
                            'meal_type' => $mealType,
                            'counter'   => $counterName,
                        ]);
                        $stats['menus']++;

                        foreach ($dishNames as $dishName) {
                            $trimmed = trim($dishName);

                            if ($existingDishes->has($trimmed)) {
                                $dish = $existingDishes[$trimmed];
                                $stats['existing_dishes']++;
                            } else {
                                $nutrition = $nutritionMap[$trimmed] ?? null;
                                $dish = Dish::firstOrCreate(
                                    ['name' => $trimmed],
                                    [
                                        'serving_size'  => $nutrition['serving_size'] ?? '1 serving',
                                        'calories'      => $nutrition['calories']     ?? 0,
                                        'protein'       => $nutrition['protein']      ?? 0,
                                        'carbs'         => $nutrition['carbs']        ?? 0,
                                        'fat'           => $nutrition['fat']          ?? 0,
                                        'fiber'         => $nutrition['fiber']        ?? 0,
                                        'sugar'         => $nutrition['sugar']        ?? 0,
                                        'sodium'        => $nutrition['sodium']       ?? 0,
                                        'diet_type'     => $nutrition['diet_type']    ?? 'veg',
                                        'ai_generated'  => $nutrition !== null,
                                    ]
                                );

                                if ($dish->wasRecentlyCreated) {
                                    $stats['new_dishes']++;
                                    $existingDishes[$trimmed] = $dish;
                                }
                            }

                            MenuDish::firstOrCreate([
                                'menu_id' => $menu->id,
                                'dish_id' => $dish->id,
                            ]);
                        }
                    }
                }
            }
        });

        Log::info('[MenuUpload] DB transaction complete', [
            'elapsed_sec' => round(microtime(true) - $dbStart, 2),
            'stats'       => $stats,
        ]);

        $totalDishes = 0;
        foreach ($normalised as $meals) {
            foreach ($meals as $counters) {
                foreach ($counters as $dishes) $totalDishes += count($dishes);
            }
        }

        $totalElapsed = round(microtime(true) - $parseStart, 2);
        Log::info('[MenuUpload] ========== UPLOAD COMPLETE ==========', [
            'total_elapsed_sec' => $totalElapsed,
            'stats'             => $stats,
            'days'              => count($normalised),
            'total_dish_slots'  => $totalDishes,
            'unique_dishes'     => count($allDishNames),
        ]);

        return response()->json([
            'message' => 'Menu imported successfully',
            'summary' => $stats,
            'parsed'  => $normalised,
            'stats'   => [
                'days'             => count($normalised),
                'total_dish_slots' => $totalDishes,
                'unique_dishes'    => count($allDishNames),
            ],
        ], 201);
    }
}
