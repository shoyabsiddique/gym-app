<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiJob extends Model
{
    protected $fillable = ['dish_id', 'status', 'error'];

    public function dish()
    {
        return $this->belongsTo(Dish::class);
    }
}
