<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Versement",
 *     type="object",
 *     title="Versement",
 *     description="Schéma du modèle Versement",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="transaction_date", type="string", format="date", example="2025-10-09"),
 *     @OA\Property(property="amount", type="number", format="float", example=1000.00),
 *     @OA\Property(property="paid_amount", type="number", format="float", example=700.00),
 *     @OA\Property(property="taux", type="number", format="float", example=30.00),
 *     @OA\Property(property="reference", type="string", example="VF-20251009-0001"),
 *     @OA\Property(property="account_id", type="integer", example=1),
 *     @OA\Property(property="agent_id", type="integer", example=2),
 *     @OA\Property(property="addedBy", type="integer", example=5),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-09T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-09T11:00:00Z")
 * )
 */

class Versement extends Model
{
    protected $fillable = ['account_id', 'reference', 'transaction_date', 'amount', 'paid_amount', 'taux', 'agent_id', 'addedBy'];

    public function bornier()
    {
        return $this->belongsTo(Bornier::class, 'agent_id');
    }
    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('versements.reference', 'like', $term)
                ->orWhere('versements.transaction_date', 'like', $term)
                ->orWhereHas('bornier', function ($q2) use ($term) {
                    $q2->where('borniers.nom', 'like', $term);
                });
        });
    }
}
