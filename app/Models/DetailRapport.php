<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailRapport extends Model
{
    protected $fillable = ['rapport_id', 'motif', 'amount'];

    public function depense()
    {
        return $this->belongsTo(Rapport::class);
    }
}
