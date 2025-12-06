<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'brand', 'last4', 'exp_month', 'exp_year', 'token', 'is_default',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
