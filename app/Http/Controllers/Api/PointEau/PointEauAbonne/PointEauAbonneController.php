<?php

namespace App\Http\Controllers\Api\PointEau\PointEauAbonne;

use App\Http\Controllers\Controller;
use App\Models\PointEauAbonne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
        $data = PointEauAbonne::join('abonnes', 'point_eau_abonnes.abonne_id', '=', 'abonnes.id')
            ->join('users', 'point_eau_abonnes.addedBy', '=', 'users.id')
            ->select('point_eau_abonnes.*', 'abonnes.nom as abonne', 'users.name as addedBy')
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
     * path="/api/point-eau-abonnes.store",
     * summary="Lier un abonné à un point d’eau",
     * tags={"Point d’eau abonnés"},
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(
     *       required={"abonne_id","point_eau_id"},
     *       @OA\Property(property="abonne_id", type="integer", example=1),
     *       @OA\Property(property="point_eau_id", type="integer", example=1)
     *    )
     * ),
     * @OA\Response(response=201, description="Lien créé avec succès"),
     * @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request)
    {
        $rules = [
            'abonne_id'    => ['required', 'exists:abonnes,id'],
            'point_eau_id' => ['required', 'exists:point_eaus,id']
        ];

        $messages = [
            'abonne_id.required'    => 'L’abonné est obligatoire.',
            'abonne_id.exists'      => 'Cet abonné n’existe pas.',
            'point_eau_id.required' => 'Le point d’eau est obligatoire.',
            'point_eau_id.exists'   => 'Ce point d’eau n’existe pas.',
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

            // Vérifier si le lien existe déjà
            $exists = PointEauAbonne::where('abonne_id', $request->abonne_id)
                ->where('point_eau_id', $request->point_eau_id)
                ->first();

            if ($exists) {
                return response()->json([
                    'message' => 'Cet abonné est déjà lié à ce point d’eau.',
                    'success' => false,
                    'status'  => 409
                ], 409);
            }

            $pointEauAbonne = PointEauAbonne::create([
                'abonne_id'    => $request->abonne_id,
                'point_eau_id' => $request->point_eau_id,
                'addedBy'      => 1, // si tu veux mettre l’utilisateur connecté
            ]);

            DB::commit();

            return response()->json([
                'message' => "Abonné lié au point d’eau avec succès",
                'success' => true,
                'status'  => 201,
                'data'    => $pointEauAbonne
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création du lien abonné - point d’eau.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     * path="/api/point-eau-abonnes/{id}",
     * summary="Mettre à jour un lien abonné - point d’eau",
     * tags={"Point d’eau abonnés"},
     * @OA\Parameter(
     *    name="id",
     *    in="path",
     *    required=true,
     *    description="ID du lien abonné - point d’eau",
     *    @OA\Schema(type="integer", example=1)
     * ),
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(
     *       required={"abonne_id","point_eau_id"},
     *       @OA\Property(property="abonne_id", type="integer", example=2),
     *       @OA\Property(property="point_eau_id", type="integer", example=4)
     *    )
     * ),
     * @OA\Response(response=200, description="Lien mis à jour avec succès"),
     * @OA\Response(response=404, description="Lien introuvable"),
     * @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function update(Request $request, $id)
    {
        $rules = [
            'abonne_id'    => ['required', 'exists:abonnes,id'],
            'point_eau_id' => ['required', 'exists:point_eaus,id']
        ];

        $messages = [
            'abonne_id.required'    => 'L’abonné est obligatoire.',
            'abonne_id.exists'      => 'Cet abonné n’existe pas.',
            'point_eau_id.required' => 'Le point d’eau est obligatoire.',
            'point_eau_id.exists'   => 'Ce point d’eau n’existe pas.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $pointEauAbonne = PointEauAbonne::find($id);

            if (!$pointEauAbonne) {
                return response()->json([
                    'message' => 'Lien abonné - point d’eau introuvable.',
                    'success' => false,
                    'status'  => 404
                ], 404);
            }

            DB::beginTransaction();

            // Vérifier si le nouveau couple abonne_id + point_eau_id existe déjà ailleurs
            $exists = PointEauAbonne::where('abonne_id', $request->abonne_id)
                ->where('point_eau_id', $request->point_eau_id)
                ->where('id', '!=', $id)
                ->first();

            if ($exists) {
                return response()->json([
                    'message' => 'Cet abonné est déjà lié à ce point d’eau.',
                    'success' => false,
                    'status'  => 409
                ], 409);
            }

            $pointEauAbonne->update([
                'abonne_id'    => $request->abonne_id,
                'point_eau_id' => $request->point_eau_id,
                'addedBy'      => $pointEauAbonne->addedBy, // conserve l’ancien si pas d’utilisateur connecté
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Lien abonné - point d’eau mis à jour avec succès',
                'success' => true,
                'status'  => 200,
                'data'    => $pointEauAbonne
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du lien abonné - point d’eau.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     * path="/api/point-eau-abonnes/{id}",
     * summary="Supprimer le lien entre un abonné et un point d’eau",
     * tags={"Point d’eau abonnés"},
     * @OA\Parameter(
     *    name="id",
     *    in="path",
     *    required=true,
     *    description="ID du lien abonné - point d’eau",
     *    @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(response=200, description="Lien supprimé avec succès"),
     * @OA\Response(response=404, description="Lien introuvable")
     * )
     */
    public function destroy($id)
    {
        try {
            $pointEauAbonne = PointEauAbonne::find($id);

            if (!$pointEauAbonne) {
                return response()->json([
                    'message' => 'Lien abonné - point d’eau introuvable.',
                    'success' => false,
                    'status'  => 404
                ], 404);
            }

            $pointEauAbonne->delete();

            return response()->json([
                'message' => 'Lien abonné - point d’eau supprimé avec succès',
                'success' => true,
                'status'  => 200
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression du lien abonné - point d’eau.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
