<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NvidiaService
{
    private string $apiKey;
    private string $visionModel;
    private string $textModel;
    private string $baseUrl = 'https://integrate.api.nvidia.com/v1/chat/completions';
    private int $imagesPerBatch = 4;

    public function __construct()
    {
        $this->apiKey      = config('services.nvidia.key');
        $this->visionModel = config('services.nvidia.vision_model', 'meta/llama-3.2-90b-vision-instruct');
        $this->textModel   = config('services.nvidia.text_model', 'meta/llama-3.1-70b-instruct');

        Log::info('[NVIDIA] Service initialized', [
            'vision_model' => $this->visionModel,
            'text_model'   => $this->textModel,
            'api_key_set'  => !empty($this->apiKey),
        ]);
    }

    /**
     * Parse menu images → day × meal_type × counter × [dishes].
     * Batches images (4 per API call) to stay within 32K token limit, then merges.
     */
    public function parseMenuImages(array $images, string $mondayDate): array
    {
        Log::info('[NVIDIA] parseMenuImages called', [
            'image_count' => count($images),
            'monday_date' => $mondayDate,
        ]);

        $days = [];
        for ($i = 0; $i < 5; $i++) {
            $date = date('Y-m-d', strtotime($mondayDate . " +{$i} days"));
            $day  = date('l', strtotime($date));
            $days[] = "{$day} = {$date}";
        }
        $dayMap = implode(', ', $days);

        // Step 1: Compress all images first
        $compressed = [];
        foreach ($images as $idx => $img) {
            $origKb = round(strlen(base64_decode($img['base64'])) / 1024);
            $comp   = $this->compressImage($img['base64'], $img['mime']);
            $compKb = round(strlen(base64_decode($comp)) / 1024);
            Log::info("[NVIDIA] Image " . ($idx + 1) . "/" . count($images) . " compressed", [
                'original_kb'   => $origKb,
                'compressed_kb' => $compKb,
            ]);
            $compressed[] = $comp;
        }

        // Step 2: Batch images and send each batch as one API call
        $batches = array_chunk($compressed, $this->imagesPerBatch);
        $merged  = [];

        Log::info('[NVIDIA] Sending ' . count($batches) . ' batch(es) of images to vision API', [
            'images_per_batch' => $this->imagesPerBatch,
            'total_images'     => count($images),
        ]);

        foreach ($batches as $batchIdx => $batch) {
            $batchNum = ($batchIdx + 1) . '/' . count($batches);
            $imgRange = ($batchIdx * $this->imagesPerBatch + 1) . '-' . ($batchIdx * $this->imagesPerBatch + count($batch));

            Log::info("[NVIDIA] === Batch {$batchNum} (images {$imgRange}) ===");

            $prompt = <<<PROMPT
These are photos of a cafeteria/office weekly menu from multiple food counters/stations. Extract EVERY dish listed, organized by day, meal type, AND counter name.

The week dates are: {$dayMap}

Rules:
- Each image or section belongs to a specific counter/station (e.g. "Coriander", "TATVA", "Bowl'd", "Angithi", "Kettls", "Zest", "Art of Wok"). Look for the counter name in headers, logos, or branding.
- Keep dishes grouped under their counter — do NOT merge dishes from different counters.
- Map meal periods: "Breakfast"/"Morning" → breakfast, "Lunch"/"Afternoon" → lunch, "Snacks"/"Evening"/"Tea Time" → snacks, "Dinner"/"Night" → dinner.
- If an image only shows one meal type (e.g. "Lunch Grain Bowl"), classify all dishes under that meal type.
- If a menu says "consistent all week" or "same every day" or "Monday to Friday" or "8AM to 4AM", repeat it for EVERY weekday (Monday through Friday).
- Include every item: grains, proteins, vegetables, sauces, sides, drinks, desserts, beverages, chaas, juices, smoothies, milkshakes, muesli, oats, fruits, tea, coffee.
- Use dish names exactly as shown, preserving capitalization.
- Skip decorative text, logos, prices, and calorie counts.
- If you see "Or" between items, include BOTH items.
- If a counter name is not clearly visible, use "General" as the counter name.

Return ONLY a valid JSON object (no markdown, no explanation, no text before or after):
{
  "YYYY-MM-DD": {
    "breakfast": {
      "Counter Name": ["dish1", "dish2"],
      "Another Counter": ["dish3"]
    },
    "lunch": {
      "Counter Name": ["dish4", "dish5"]
    }
  }
}

Only include meal types that actually appear. Only include days that have dishes.
PROMPT;

            // Build content array: all images in batch + prompt
            $content = [];
            foreach ($batch as $b64) {
                $content[] = [
                    'type'      => 'image_url',
                    'image_url' => ['url' => 'data:image/jpeg;base64,' . $b64],
                ];
            }
            $content[] = ['type' => 'text', 'text' => $prompt];

            $startTime = microtime(true);

            try {
                $response = $this->callVisionApi($content, 8192, 0.1, 180);
                $elapsed  = round(microtime(true) - $startTime, 2);
                $text     = $this->extractText($response);
                $usage    = $response['usage'] ?? [];

                Log::info("[NVIDIA] Batch {$batchNum}: Response received", [
                    'elapsed_sec'       => $elapsed,
                    'response_length'   => strlen($text),
                    'prompt_tokens'     => $usage['prompt_tokens'] ?? 'N/A',
                    'completion_tokens' => $usage['completion_tokens'] ?? 'N/A',
                    'total_tokens'      => $usage['total_tokens'] ?? 'N/A',
                    'response_preview'  => substr($text, 0, 500),
                ]);

                $data = $this->parseJson($text);

                if (is_array($data)) {
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
                    Log::info("[NVIDIA] Batch {$batchNum}: Parsed OK", [
                        'dates'        => array_keys($data),
                        'dishes_found' => $dishCount,
                    ]);
                    $merged = $this->deepMergeMenus($merged, $data);
                } else {
                    Log::warning("[NVIDIA] Batch {$batchNum}: JSON parse FAILED", [
                        'raw_text' => substr($text, 0, 1000),
                    ]);
                }
            } catch (\RuntimeException $e) {
                $elapsed = round(microtime(true) - $startTime, 2);
                Log::error("[NVIDIA] Batch {$batchNum}: API call FAILED", [
                    'elapsed_sec' => $elapsed,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        Log::info('[NVIDIA] parseMenuImages complete', [
            'batches_sent'  => count($batches),
            'merged_dates'  => array_keys($merged),
            'merged_empty'  => empty($merged),
        ]);

        if (empty($merged)) {
            throw new \RuntimeException("Could not parse any menu data from the uploaded images.");
        }

        return $merged;
    }

    /**
     * Get nutrition for multiple dishes in one call.
     */
    public function analyzeMultipleDishes(array $dishNames): array
    {
        Log::info('[NVIDIA] analyzeMultipleDishes called', [
            'dish_count' => count($dishNames),
            'dishes'     => $dishNames,
        ]);

        if (empty($dishNames)) return [];

        $list = implode("\n", array_map(
            fn($i, $n) => ($i + 1) . ". {$n}",
            array_keys($dishNames),
            $dishNames
        ));

        $prompt = <<<PROMPT
Estimate realistic nutrition values for standard cafeteria servings of the following dishes:

{$list}

Return ONLY a valid JSON array (no markdown, no explanation, no text before or after) with one object per dish, in the SAME order:
[
  {
    "dish_name": "exact name from above",
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

All numeric values must be non-negative numbers. Be realistic for typical Indian/office cafeteria portions.
PROMPT;

        Log::info('[NVIDIA] Sending nutrition batch to text API', [
            'model'         => $this->textModel,
            'prompt_length' => strlen($prompt),
        ]);

        $startTime = microtime(true);
        $response  = $this->callTextApi($prompt, 8192, 0.1, 90);
        $elapsed   = round(microtime(true) - $startTime, 2);
        $text      = $this->extractText($response);
        $usage     = $response['usage'] ?? [];

        Log::info('[NVIDIA] Nutrition batch response received', [
            'elapsed_sec'       => $elapsed,
            'response_length'   => strlen($text),
            'prompt_tokens'     => $usage['prompt_tokens'] ?? 'N/A',
            'completion_tokens' => $usage['completion_tokens'] ?? 'N/A',
            'response_preview'  => substr($text, 0, 500),
        ]);

        $items = $this->parseJson($text);

        if (!is_array($items)) {
            Log::error('[NVIDIA] Nutrition JSON parse FAILED', ['raw_text' => substr($text, 0, 1000)]);
            throw new \RuntimeException("Invalid nutrition JSON from NVIDIA NIM");
        }

        Log::info('[NVIDIA] Nutrition JSON parsed', ['item_count' => count($items)]);

        $result = [];
        foreach ($items as $item) {
            $name = $item['dish_name'] ?? '';
            if (!$name) continue;
            try {
                $validated = $this->validate($item);
                $validated['serving_size'] = $item['serving_size'] ?? '1 serving';
                $validated['dish_name']    = $name;
                $result[$name] = $validated;
            } catch (\RuntimeException $e) {
                Log::warning("[NVIDIA] Skipping nutrition for '{$name}'", [
                    'error'    => $e->getMessage(),
                    'raw_item' => $item,
                ]);
            }
        }

        Log::info('[NVIDIA] analyzeMultipleDishes complete', [
            'requested' => count($dishNames),
            'returned'  => count($result),
            'missing'   => array_values(array_diff($dishNames, array_keys($result))),
        ]);

        return $result;
    }

    /**
     * Get nutrition for a single dish.
     */
    public function analyzeDishNutrition(string $dishName): array
    {
        Log::info("[NVIDIA] analyzeDishNutrition called", ['dish' => $dishName]);

        $prompt = <<<PROMPT
Estimate realistic nutrition values for a standard cafeteria serving of the following dish: "{$dishName}".

Return ONLY a valid JSON object (no markdown, no explanation, no text before or after) with these exact fields:
{
  "dish_name": "{$dishName}",
  "serving_size": "description of one serving (e.g., 1 cup, 2 pieces)",
  "calories": <number>,
  "protein": <number in grams>,
  "carbs": <number in grams>,
  "fat": <number in grams>,
  "fiber": <number in grams>,
  "sugar": <number in grams>,
  "sodium": <number in milligrams>
}

All numeric values must be non-negative numbers. Be realistic for typical cafeteria portions.
PROMPT;

        $startTime = microtime(true);
        $response  = $this->callTextApi($prompt, 512, 0.1, 30);
        $elapsed   = round(microtime(true) - $startTime, 2);
        $text      = $this->extractText($response);

        Log::info("[NVIDIA] Single dish response", [
            'dish'        => $dishName,
            'elapsed_sec' => $elapsed,
            'response'    => substr($text, 0, 500),
        ]);

        $data = $this->parseJson($text);

        if (!is_array($data)) {
            Log::error("[NVIDIA] Single dish JSON parse FAILED", ['dish' => $dishName, 'raw' => substr($text, 0, 500)]);
            throw new \RuntimeException("Invalid JSON from NVIDIA NIM: {$text}");
        }

        return $this->validate($data);
    }

    private function compressImage(string $base64, string $mime): string
    {
        $raw = base64_decode($base64);
        $img = @imagecreatefromstring($raw);

        if (!$img) {
            Log::warning('[NVIDIA] compressImage: GD failed, returning original');
            return $base64;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $maxDim = 768;

        if ($w > $maxDim || $h > $maxDim) {
            if ($w > $h) {
                $newW = $maxDim;
                $newH = (int) round($h * ($maxDim / $w));
            } else {
                $newH = $maxDim;
                $newW = (int) round($w * ($maxDim / $h));
            }
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }

        ob_start();
        imagejpeg($img, null, 70);
        $jpegData = ob_get_clean();
        imagedestroy($img);

        return base64_encode($jpegData);
    }

    private function deepMergeMenus(array $base, array $incoming): array
    {
        foreach ($incoming as $date => $meals) {
            if (!isset($base[$date])) {
                $base[$date] = $meals;
                continue;
            }
            foreach ($meals as $mealType => $counters) {
                if (!isset($base[$date][$mealType])) {
                    $base[$date][$mealType] = $counters;
                    continue;
                }
                foreach ($counters as $counter => $dishes) {
                    if (!isset($base[$date][$mealType][$counter])) {
                        $base[$date][$mealType][$counter] = $dishes;
                    } else {
                        $existing = $base[$date][$mealType][$counter];
                        $base[$date][$mealType][$counter] = array_values(
                            array_unique(array_merge($existing, $dishes))
                        );
                    }
                }
            }
        }
        return $base;
    }

    private function callVisionApi(array $content, int $maxTokens, float $temperature, int $timeout): array
    {
        $payload = [
            'model'       => $this->visionModel,
            'messages'    => [['role' => 'user', 'content' => $content]],
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
            'stream'      => false,
        ];

        $payloadSize = round(strlen(json_encode($payload)) / 1024);
        Log::info('[NVIDIA] callVisionApi: Sending', [
            'model' => $this->visionModel, 'payload_kb' => $payloadSize, 'timeout' => $timeout,
        ]);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->post($this->baseUrl, $payload);

        Log::info('[NVIDIA] callVisionApi: Status ' . $response->status());

        if (!$response->successful()) {
            $body = $response->body();
            Log::error('[NVIDIA] callVisionApi: FAILED', ['status' => $response->status(), 'body' => substr($body, 0, 1000)]);
            throw new \RuntimeException("NVIDIA NIM API error ({$response->status()}): " . substr($body, 0, 300));
        }

        return $response->json();
    }

    private function callTextApi(string $prompt, int $maxTokens, float $temperature, int $timeout): array
    {
        Log::info('[NVIDIA] callTextApi: Sending', [
            'model' => $this->textModel, 'prompt_len' => strlen($prompt), 'timeout' => $timeout,
        ]);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->post($this->baseUrl, [
                'model'       => $this->textModel,
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'max_tokens'  => $maxTokens,
                'temperature' => $temperature,
                'stream'      => false,
            ]);

        Log::info('[NVIDIA] callTextApi: Status ' . $response->status());

        if (!$response->successful()) {
            $body = $response->body();
            Log::error('[NVIDIA] callTextApi: FAILED', ['status' => $response->status(), 'body' => substr($body, 0, 1000)]);
            throw new \RuntimeException("NVIDIA NIM API error ({$response->status()}): " . substr($body, 0, 300));
        }

        return $response->json();
    }

    private function extractText(array $response): string
    {
        return $response['choices'][0]['message']['content'] ?? '';
    }

    private function parseJson(string $text): mixed
    {
        $text = trim(preg_replace('/```json\s*|\s*```/s', '', $text));
        if (($start = strpos($text, '{')) !== false || ($start = strpos($text, '[')) !== false) {
            $bracket = $text[$start];
            $close   = $bracket === '{' ? '}' : ']';
            $end     = strrpos($text, $close);
            if ($end !== false) {
                $text = substr($text, $start, $end - $start + 1);
            }
        }

        $data = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('[NVIDIA] parseJson failed', ['error' => json_last_error_msg(), 'snippet' => substr($text, 0, 300)]);
            return null;
        }
        return $data;
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
