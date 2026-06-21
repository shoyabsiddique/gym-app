<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    protected $fillable = [
        'user_id', 'recommendation_date',
        'total_calories', 'total_protein', 'total_carbs', 'total_fat',
    ];

    protected function casts(): array
    {
        return ['recommendation_date' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(RecommendationItem::class);
    }
}
