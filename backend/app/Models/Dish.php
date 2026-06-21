<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dish extends Model
{
    protected $fillable = [
        'name', 'serving_size', 'calories', 'protein',
        'carbs', 'fat', 'fiber', 'sugar', 'sodium', 'ai_generated', 'diet_type',
    ];

    protected function casts(): array
    {
        return ['ai_generated' => 'boolean'];
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'menu_dishes');
    }

    public function aiJob()
    {
        return $this->hasOne(AiJob::class);
    }
}
