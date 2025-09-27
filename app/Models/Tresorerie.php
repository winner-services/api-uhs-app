<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tresorerie extends Model
{
    protected $fillable = [
        'designation',
        'reference',
        'type',
        'addedBy'
    ];

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('tresoreries.designation', 'like', $term)
            ->orWhere('reference', 'like', $term)
            ->orWhere('type', 'like', $term);
        });
    }
}
