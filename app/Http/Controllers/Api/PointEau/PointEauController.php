<?php

namespace App\Http\Controllers\Api\PointEau;

use App\Http\Controllers\Controller;
use App\Models\PointEau;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PointEauController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/point-eaux.getAllData",
     * summary="Liste des points d’eau",
     * tags={"Points d’eau"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function index()
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = PointEau::with('abonne')->latest()
            ->searh(trim($q))
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
     * path="/api/point-eaux.store",
     * summary="Créer un point d’eau",
     * tags={"Points d’eau"},
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(
     *       required={"abonne_id"},
     *       @OA\Property(property="abonne_id", type="integer", example=1),
     *       @OA\Property(property="localisation", type="string", example="-1.6789,29.2345"),
     *       @OA\Property(property="numero_compteur", type="string", example="COMP-001"),
     *       @OA\Property(property="status", type="string", example="Actif")
     *    )
     * ),
     * @OA\Response(response=201, description="Point d’eau créé avec succès"),
     * @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request)
    {
        $rules = [
            'abonne_id'       => ['required', 'integer', 'exists:abonnes,id'],
            'localisation'    => ['nullable', 'string', 'max:255'],
            'numero_compteur' => ['nullable', 'string', 'max:255', 'unique:point_eaus,numero_compteur'],
            'status'          => ['nullable', 'string', 'in:Actif,Inactif'],
        ];

        $messages = [
            'numero_compteur.unique' => 'Ce numéro de compteur existe déjà.',
            'abonne_id.exists'       => 'L’abonné spécifié n’existe pas.'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $pointEau = PointEau::create([
                'abonne_id'       => $request->input('abonne_id'),
                'localisation'    => $request->input('localisation'),
                'numero_compteur' => $request->input('numero_compteur'),
                'status'          => $request->input('status', 'Actif'),
            ]);

            DB::commit();

            return response()->json([
                'message' => "Point d’eau ajouté avec succès",
                'success' => true,
                'status'  => 201,
                'data'    => $pointEau
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création du point d’eau.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     * path="/api/point-eaux.update/{id}",
     * summary="Mettre à jour un point d’eau",
     * tags={"Points d’eau"},
     * @OA\Parameter(name="id", in="path", required=true, description="ID du point d’eau"),
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(
     *       @OA\Property(property="localisation", type="string", example="-1.6789,29.2345"),
     *       @OA\Property(property="numero_compteur", type="string", example="COMP-002"),
     *       @OA\Property(property="status", type="string", example="Inactif")
     *    )
     * ),
     * @OA\Response(response=200, description="Point d’eau mis à jour avec succès"),
     * @OA\Response(response=404, description="Point d’eau non trouvé")
     * )
     */
    public function update(Request $request, $id)
    {
        $pointEau = PointEau::find($id);
        if (!$pointEau) {
            return response()->json([
                'message' => 'Point d’eau non trouvé'
            ], 404);
        }

        $rules = [
            'localisation'    => ['nullable', 'string', 'max:255'],
            'numero_compteur' => ['nullable', 'string', 'max:255', 'unique:point_eaus,numero_compteur,' . $id],
            'status'          => ['nullable', 'string', 'in:Actif,Inactif'],
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

            $pointEau->update($request->all());

            DB::commit();

            return response()->json([
                'message' => "Point d’eau mis à jour avec succès",
                'success' => true,
                'status'  => 200,
                'data'    => $pointEau
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du point d’eau.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     * path="/api/point-eaux.delete/{id}",
     * summary="Supprimer un point d’eau",
     * tags={"Points d’eau"},
     * @OA\Parameter(name="id", in="path", required=true, description="ID du point d’eau"),
     * @OA\Response(response=200, description="Point d’eau supprimé avec succès"),
     * @OA\Response(response=404, description="Point d’eau non trouvé")
     * )
     */
    public function destroy($id)
    {
        $pointEau = PointEau::find($id);
        if (!$pointEau) {
            return response()->json([
                'message' => 'Point d’eau non trouvé'
            ], 404);
        }

        try {
            DB::beginTransaction();
            $pointEau->delete();
            DB::commit();

            return response()->json([
                'message' => "Point d’eau supprimé avec succès",
                'success' => true,
                'status'  => 200
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la suppression du point d’eau.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
