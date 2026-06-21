<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = ['menu_date', 'meal_type', 'counter'];

    protected function casts(): array
    {
        return ['menu_date' => 'date'];
    }

    public function dishes()
    {
        return $this->belongsToMany(Dish::class, 'menu_dishes');
    }
}
