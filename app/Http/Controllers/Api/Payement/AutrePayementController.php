<?php

namespace App\Http\Controllers\Api\Payement;

use App\Http\Controllers\Controller;
use App\Models\Versement;
use Illuminate\Http\Request;

class AutrePayementController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/versements.getAllData",
     *     summary="Liste des versements",
     *     description="Récupérer toutes les Payements avec leurs abonnés",
     *     tags={"Versements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des Payements",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Facturation"))
     *         )
     *     )
     * )
     */
    public function getVersement()
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = Versement::join('tresoreries', 'versements.account_id', '=', 'tresoreries.id')
            ->join('users as u1', 'versements.addedBy', '=', 'u1.id')
            ->join('users as u2', 'versements.agent_id', '=', 'u2.id')
            ->select('versements.*', 'u2.name as agent', 'u1.name as addedBy', 'tresoreries.designation as tresorerie')
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
