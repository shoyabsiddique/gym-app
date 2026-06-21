<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationItem extends Model
{
    protected $fillable = ['recommendation_id', 'meal_type', 'dish_id', 'counter', 'servings'];

    public function dish()
    {
        return $this->belongsTo(Dish::class);
    }

    public function recommendation()
    {
        return $this->belongsTo(Recommendation::class);
    }
}
