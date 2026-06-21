<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeightHistory extends Model
{
    protected $table = 'weight_history';

    protected $fillable = ['user_id', 'weight_kg', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
