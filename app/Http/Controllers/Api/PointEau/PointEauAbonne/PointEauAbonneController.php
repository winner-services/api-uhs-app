<?php

namespace App\Http\Controllers\Api\PointEau\PointEauAbonne;

use App\Http\Controllers\Controller;
use App\Models\PointEauAbonne;
use Illuminate\Http\Request;

class PointEauAbonneController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/point-eau-abonne.getAllData",
     * summary="Liste des points d’eau",
     * tags={"Points d’eau Abonnee"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function indexPointAbonne()
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = PointEauAbonne::join('abonnes','point_eau_abonnes.abonne_id','=','abonnes.id')
        ->join('users','point_eau_abonnes.addedBy','=','users.id')
        ->select('point_eau_abonnes.*','abonnes.nom as abonne','users.name as addedBy')
        ->latest()
            // ->searh(trim($q))
            ->orderBy($sort_field, $sort_direction)
            ->paginate($page);
        $result = [
            'message' => "OK",
            'success' => true,
            'data' => $data,
            'status' => 200
        ];
        return response()->json($result);
    }
}
