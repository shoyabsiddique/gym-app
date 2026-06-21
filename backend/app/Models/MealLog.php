<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealLog extends Model
{
    protected $fillable = ['user_id', 'dish_id', 'meal_type', 'log_date', 'servings'];

    protected function casts(): array
    {
        return ['log_date' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dish()
    {
        return $this->belongsTo(Dish::class);
    }
}
