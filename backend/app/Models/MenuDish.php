<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuDish extends Model
{
    protected $table = 'menu_dishes';

    protected $fillable = ['menu_id', 'dish_id'];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function dish()
    {
        return $this->belongsTo(Dish::class);
    }
}
