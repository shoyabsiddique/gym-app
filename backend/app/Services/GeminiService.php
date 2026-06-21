<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model  = config('services.gemini.model', 'gemini-2.5-flash');

        Log::info('[Gemini] Service initialized', [
            'model'       => $this->model,
            'api_key_set' => !empty($this->apiKey),
        ]);
    }

    public function parseMenuPdf(string $base64Pdf, string $mondayDate): array
    {
        Log::info('[Gemini] parseMenuPdf called', [
            'pdf_base64_kb' => round(strlen($base64Pdf) / 1024),
            'monday_date'   => $mondayDate,
        ]);

        $days = [];
        for ($i = 0; $i < 5; $i++) {
            $date = date('Y-m-d', strtotime($mondayDate . " +{$i} days"));
            $day  = date('l', strtotime($date));
            $days[] = "{$day} = {$date}";
        }
        $dayMap = implode(', ', $days);

        $prompt = <<<PROMPT
This PDF contains photos of a cafeteria/office weekly menu from multiple food counters/stations. Extract EVERY dish listed, organized by day, meal type, AND counter name.

The week dates are: {$dayMap}

Rules:
- Each page or section belongs to a specific counter/station (e.g. "Coriander", "TATVA", "Bowl'd", "Angithi", "Kettls", "Zest", "Art of Wok"). Look for the counter name in headers, logos, or branding on each page.
- Keep dishes grouped under their counter — do NOT merge dishes from different counters.
- Map meal periods: "Breakfast"/"Morning" → breakfast, "Lunch"/"Afternoon" → lunch, "Snacks"/"Evening"/"Tea Time" → snacks, "Dinner"/"Night" → dinner.
- If a page only shows one meal type (e.g. "Lunch Grain Bowl"), classify all dishes under that meal type.
- If a menu says "consistent all week" or "same every day" or "Monday to Friday" or "8AM to 4AM", repeat it for EVERY weekday (Monday through Friday).
- Include every item: grains, proteins, vegetables, sauces, sides, drinks, desserts, beverages, chaas, juices, smoothies, milkshakes, muesli, oats, fruits, tea, coffee.
- Use dish names exactly as shown, preserving capitalization.
- Skip decorative text, logos, prices, and calorie counts.
- If you see "Or" between items, include BOTH items.
- If a counter name is not clearly visible, use "General" as the counter name.

Return ONLY a valid JSON object (no markdown, no explanation):
{
  "YYYY-MM-DD": {
    "breakfast": {
      "Counter Name": ["dish1", "dish2"],
      "Another Counter": ["dish3"]
    },
    "lunch": {
      "Counter Name": ["dish4", "dish5"],
      "Another Counter": ["dish6", "dish7"]
    }
  }
}

Only include meal types that actually appear. Only include days that have dishes.
PROMPT;

        Log::info('[Gemini] Sending PDF to API', [
            'model'    => $this->model,
            'endpoint' => "{$this->baseUrl}/{$this->model}:generateContent",
        ]);

        $startTime = microtime(true);

        $response = Http::timeout(120)->post(
            "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [[
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => 'application/pdf',
                                'data'      => $base64Pdf,
                            ],
                        ],
                        ['text' => $prompt],
                    ],
                ]],
                'generationConfig' => [
                    'temperature'     => 0.1,
                    'maxOutputTokens' => 8192,
                ],
            ]
        );

        $elapsed = round(microtime(true) - $startTime, 2);

        Log::info('[Gemini] PDF parse response', [
            'elapsed_sec' => $elapsed,
            'status'      => $response->status(),
            'successful'  => $response->successful(),
        ]);

        if (!$response->successful()) {
            $body = $response->body();
            Log::error('[Gemini] PDF parse FAILED', [
                'status' => $response->status(),
                'body'   => substr($body, 0, 1000),
            ]);
            throw new \RuntimeException("Gemini API error: " . $body);
        }

        $text = $response->json('candidates.0.content.parts.0.text', '');
        $usage = $response->json('usageMetadata', []);

        Log::info('[Gemini] PDF parse raw response', [
            'response_length'   => strlen($text),
            'prompt_tokens'     => $usage['promptTokenCount'] ?? 'N/A',
            'completion_tokens' => $usage['candidatesTokenCount'] ?? 'N/A',
            'total_tokens'      => $usage['totalTokenCount'] ?? 'N/A',
            'response_preview'  => substr($text, 0, 500),
        ]);

        $text = trim(preg_replace('/```json\s*|\s*```/s', '', $text));

        $data = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('[Gemini] PDF parse JSON decode FAILED', [
                'error'   => json_last_error_msg(),
                'snippet' => substr($text, 0, 500),
            ]);
            throw new \RuntimeException("Could not parse menu from PDF. Gemini returned: " . substr($text, 0, 500));
        }

        $dishCount = 0;
        foreach ($data as $meals) {
            foreach ($meals as $counters) {
                if (is_array($counters)) {
                    foreach ($counters as $dishes) {
                        if (is_array($dishes)) $dishCount += count($dishes);
                    }
                }
            }
        }

        Log::info('[Gemini] PDF parsed successfully', [
            'dates'        => array_keys($data),
            'dishes_found' => $dishCount,
        ]);

        return $data;
    }

    public function analyzeMultipleDishes(array $dishNames): array
    {
        if (empty($dishNames)) return [];

        Log::info('[Gemini] analyzeMultipleDishes called', [
            'dish_count' => count($dishNames),
            'dishes'     => $dishNames,
        ]);

        $list = implode("\n", array_map(
            fn($i, $n) => ($i + 1) . ". {$n}",
            array_keys($dishNames),
            $dishNames
        ));

        $prompt = <<<PROMPT
Estimate realistic nutrition values for standard cafeteria servings of the following dishes:

{$list}

Return ONLY a valid JSON array (no markdown, no explanation) with one object per dish, in the SAME order:
[
  {
    "dish_name": "exact name from above",
    "diet_type": "veg" or "non_veg" or "eggetarian",
    "serving_size": "description (e.g., 1 cup, 2 pieces, 1 glass)",
    "calories": <number>,
    "protein": <number in grams>,
    "carbs": <number in grams>,
    "fat": <number in grams>,
    "fiber": <number in grams>,
    "sugar": <number in grams>,
    "sodium": <number in milligrams>
  }
]

diet_type rules:
- "non_veg": contains meat, fish, seafood, chicken, mutton, pork, prawn, keema, tikka with meat, kebab with meat
- "eggetarian": contains egg, anda, omelette, bhurji (egg-based) but no meat/fish
- "veg": everything else (paneer, dal, vegetables, fruits, drinks, bread, rice, etc.)

All numeric values must be non-negative numbers. Be realistic for typical Indian/office cafeteria portions.
PROMPT;

        $startTime = microtime(true);

        $response = Http::timeout(90)->post(
            "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.1,
                    'maxOutputTokens' => 8192,
                ],
            ]
        );

        $elapsed = round(microtime(true) - $startTime, 2);

        Log::info('[Gemini] Nutrition batch response', [
            'elapsed_sec' => $elapsed,
            'status'      => $response->status(),
        ]);

        if (!$response->successful()) {
            $body = $response->body();
            Log::error('[Gemini] Nutrition batch FAILED', ['status' => $response->status(), 'body' => substr($body, 0, 1000)]);
            throw new \RuntimeException("Gemini API error: " . $body);
        }

        $text = $response->json('candidates.0.content.parts.0.text', '');
        Log::info('[Gemini] Nutrition batch raw', [
            'response_length' => strlen($text),
            'preview'         => substr($text, 0, 500),
        ]);

        $text = trim(preg_replace('/```json\s*|\s*```/s', '', $text));

        $items = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($items)) {
            Log::error('[Gemini] Nutrition JSON parse FAILED', ['error' => json_last_error_msg()]);
            throw new \RuntimeException("Invalid nutrition JSON from Gemini");
        }

        $result = [];
        foreach ($items as $item) {
            $name = $item['dish_name'] ?? '';
            if (!$name) continue;
            try {
                $validated = $this->validate($item);
                $validated['serving_size'] = $item['serving_size'] ?? '1 serving';
                $validated['dish_name']    = $name;
                $dietType = $item['diet_type'] ?? 'veg';
                $validated['diet_type'] = in_array($dietType, ['veg', 'non_veg', 'eggetarian']) ? $dietType : 'veg';
                $result[$name] = $validated;
            } catch (\RuntimeException $e) {
                Log::warning("[Gemini] Skipping nutrition for '{$name}'", ['error' => $e->getMessage()]);
            }
        }

        Log::info('[Gemini] analyzeMultipleDishes complete', [
            'requested' => count($dishNames),
            'returned'  => count($result),
            'missing'   => array_values(array_diff($dishNames, array_keys($result))),
        ]);

        return $result;
    }

    public function analyzeDishNutrition(string $dishName): array
    {
        Log::info("[Gemini] analyzeDishNutrition called", ['dish' => $dishName]);

        $prompt = <<<PROMPT
Estimate realistic nutrition values for a standard cafeteria serving of the following dish: "{$dishName}".

Return ONLY a valid JSON object (no markdown, no explanation) with these exact fields:
{
  "dish_name": "{$dishName}",
  "diet_type": "veg" or "non_veg" or "eggetarian",
  "serving_size": "description of one serving (e.g., 1 cup, 2 pieces)",
  "calories": <number>,
  "protein": <number in grams>,
  "carbs": <number in grams>,
  "fat": <number in grams>,
  "fiber": <number in grams>,
  "sugar": <number in grams>,
  "sodium": <number in milligrams>
}

diet_type: "non_veg" if contains meat/fish/chicken/seafood, "eggetarian" if contains egg but no meat, "veg" for everything else.
All numeric values must be non-negative numbers. Be realistic for typical cafeteria portions.
PROMPT;

        $startTime = microtime(true);

        $response = Http::timeout(30)->post(
            "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.1,
                    'maxOutputTokens' => 512,
                ],
            ]
        );

        $elapsed = round(microtime(true) - $startTime, 2);

        if (!$response->successful()) {
            Log::error("[Gemini] Single dish FAILED", ['dish' => $dishName, 'status' => $response->status()]);
            throw new \RuntimeException("Gemini API error: " . $response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text', '');
        Log::info("[Gemini] Single dish response", ['dish' => $dishName, 'elapsed_sec' => $elapsed, 'preview' => substr($text, 0, 300)]);

        $text = trim(preg_replace('/```json\s*|\s*```/s', '', $text));

        $data = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("[Gemini] Single dish JSON FAILED", ['dish' => $dishName, 'error' => json_last_error_msg()]);
            throw new \RuntimeException("Invalid JSON from Gemini: {$text}");
        }

        return $this->validate($data);
    }

    private function validate(array $data): array
    {
        $required = ['calories', 'protein', 'carbs', 'fat', 'fiber', 'sugar', 'sodium'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || !is_numeric($data[$field]) || $data[$field] < 0) {
                throw new \RuntimeException("Invalid nutrition data for field: {$field}");
            }
        }
        return $data;
    }
}
