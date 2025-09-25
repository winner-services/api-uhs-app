<?php

namespace App\Http\Controllers\Api\Intervention;

use App\Http\Controllers\Controller;
use App\Models\RapportIntervention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RapportInterventionController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/rapport-interventions.getAllData",
     *      operationId="getAllRapportData",
     *      tags={"RapportInterventions"},
     *      summary="Récupère tous les rapports d'intervention",
     *      description="Retourne la liste des rapports d'intervention",
     *      @OA\Response(
     *          response=200,
     *          description="Succès",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/RapportIntervention"))
     *      )
     * )
     */
    public function getAllRapportData()
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = RapportIntervention::with(['categorie', 'user'])
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

    /**
     * @OA\Post(
     *      path="/api/rapport-interventions.store",
     *      operationId="storeRapport",
     *      tags={"RapportInterventions"},
     *      summary="Créer un rapport d'intervention",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/RapportIntervention")
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Créé avec succès"
     *      )
     * )
     */
    public function storeRapport(Request $request)
    {
        $rules = [
            'ticket_id'     => ['required', 'integer', 'exists:tickets,id'],
            'description'   => ['required', 'string'],
            'cout_reparation' => ['nullable', 'numeric', 'min:0'],
            'date_rapport'  => ['required', 'date'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            $user = Auth::user();

            $rapport = RapportIntervention::create([
                'ticket_id' => $request->ticket_id,
                'description' => $request->description,
                'cout_reparation' => $request->cout_reparation,
                'date_rapport' => $request->date_rapport,
                'addedBy' => $user->id                
            ]);

            DB::commit();

            return response()->json([
                'message' => "Rapport d'intervention ajouté avec succès",
                'success' => true,
                'data'    => $rapport
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => "Erreur lors de la création du rapport",
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Put(
     *      path="/api/rapport-interventions.update/{id}",
     *      operationId="updateRapport",
     *      tags={"RapportInterventions"},
     *      summary="Met à jour un rapport d'intervention",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/RapportIntervention")
     *      ),
     *      @OA\Response(response=200, description="Mis à jour avec succès"),
     *      @OA\Response(response=404, description="Non trouvé")
     * )
     */
    public function updateRapport(Request $request, $id)
    {
        $rapport = RapportIntervention::find($id);

        if (!$rapport) {
            return response()->json([
                'message' => "Rapport d'intervention non trouvé",
                'success' => false
            ], 404);
        }

        $rules = [
            'ticket_id'     => ['sometimes', 'integer', 'exists:tickets,id'],
            'description'   => ['sometimes', 'string'],
            'cout_reparation' => ['nullable', 'numeric', 'min:0'],
            'date_rapport'  => ['sometimes', 'date'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            $user = Auth::user();


            $rapport->update([
                'ticket_id' => $request->ticket_id,
                'description' => $request->description,
                'cout_reparation' => $request->cout_reparation,
                'date_rapport' => $request->date_rapport,
                'addedBy' => $user->id ?? $rapport->addedBy
            ]);

            DB::commit();

            return response()->json([
                'message' => "Rapport d'intervention mis à jour avec succès",
                'success' => true,
                'data'    => $rapport
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => "Erreur lors de la mise à jour du rapport",
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *      path="/api/rapport-interventions.delete/{id}",
     *      operationId="destroyRapport",
     *      tags={"RapportInterventions"},
     *      summary="Supprime un rapport d'intervention",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(response=204, description="Supprimé avec succès"),
     *      @OA\Response(response=404, description="Non trouvé")
     * )
     */
    public function destroyRapport($id)
    {
        $rapport = RapportIntervention::findOrFail($id);
        $rapport->delete();

        return response()->json(null, 204);
    }
}
