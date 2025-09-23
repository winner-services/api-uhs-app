<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Facturation",
 *     type="object",
 *     title="Facturation",
 *     description="Modèle de facturation",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="abonne_id", type="integer", example=2),
 *     @OA\Property(property="addedBy", type="integer", example=1),
 *     @OA\Property(property="mois", type="string", example="09-2025"),
 *     @OA\Property(property="montant", type="number", format="float", example=150.75),
 *     @OA\Property(property="dette", type="number", format="float", example=50.00),
 *     @OA\Property(property="status", type="string", example="Non payé"),
 *     @OA\Property(property="date_emission", type="string", format="date", example="2025-09-01"),
 *     @OA\Property(property="date_paiement", type="string", format="date", nullable=true, example="2025-09-10"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-09-21T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-09-21T12:00:00Z")
 * )
 */
class Facturation extends Model
{
    protected $fillable = [
        'abonne_id', 'mois', 'montant', 'dette',
        'status', 'date_emission', 'date_paiement','addedBy'
    ];

    public function abonne()
    {
        return $this->belongsTo(Abonne::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
