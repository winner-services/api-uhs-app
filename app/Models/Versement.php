<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Versement extends Model
{
    protected $fillable = ['transaction_date', 'amount', 'paid_amount', 'taux', 'agent_id','addedBy'];
}
