<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rapport extends Model
{
    protected $fillable = ['date', 'description', 'total_price', 'status', 'ticket_id', 'addedBy'];

    public function details()
    {
        return $this->hasMany(DetailRapport::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
