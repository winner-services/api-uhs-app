<?php

namespace App\Http\Controllers\Api\Rapport;

use App\Http\Controllers\Controller;
use App\Models\Rapport;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RapportController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/depenses.store",
     *     summary="Créer une dépense avec ses détails",
     *     description="Crée une nouvelle dépense (main) et plusieurs détails associés dans une seule transaction.",
     *     operationId="storeDepense",
     *     tags={"Depenses"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="main", type="object",
     *                 @OA\Property(property="date", type="string", example="2025-10-03"),
     *                 @OA\Property(property="description", type="string", example="lorem ipsum"),
     *                 @OA\Property(property="status", type="string", example="Cloturer"),
     *                 @OA\Property(property="ticket_id", type="integer", example=2)
     *             ),
     *             @OA\Property(property="details", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="motif", type="string", example="test"),
     *                     @OA\Property(property="amount", type="number", example=30.50)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Dépense créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Dépense créée avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=500, description="Erreur serveur interne")
     * )
     */
    public function storeDepense(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'main.date' => 'required|date',
            'main.description' => 'nullable|string',
            'main.status' => 'required|string',
            'main.ticket_id' => 'required|integer|exists:tickets,id',
            'details' => 'required|array|min:1',
            'details.*.motif' => 'required|string',
            'details.*.amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $depense = DB::transaction(function () use ($request) {
                $depense = Rapport::create($request->input('main'));

                foreach ($request->input('details') as $detail) {
                    $depense->details()->create($detail);
                }

                return $depense->load('details');
            });

            return response()->json([
                'message' => 'Dépense créée avec succès',
                'data' => $depense,
                'success' => true,
                'status' => 201
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de la dépense',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/depenses.update/{id}",
     *     summary="Mettre à jour une dépense et ses détails",
     *     description="Met à jour les informations principales et tous les détails associés d'une dépense existante.",
     *     operationId="updateDepense",
     *     tags={"Depenses"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la dépense à mettre à jour",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="main", type="object",
     *                 @OA\Property(property="date", type="string", example="2025-10-04"),
     *                 @OA\Property(property="description", type="string", example="Nouvelle dépense modifiée"),
     *                 @OA\Property(property="status", type="string", example="Cloturer"),
     *                 @OA\Property(property="ticket_id", type="integer", example=2)
     *             ),
     *             @OA\Property(property="details", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="motif", type="string", example="Transport"),
     *                     @OA\Property(property="amount", type="number", example=100.00)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Dépense mise à jour avec succès"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=500, description="Erreur serveur interne")
     * )
     */
    public function updateDepense(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'main.date' => 'required|date',
            'main.description' => 'nullable|string',
            'main.status' => 'required|string',
            'main.ticket_id' => 'required|integer|exists:tickets,id',
            'details' => 'required|array|min:1',
            'details.*.motif' => 'required|string',
            'details.*.amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $depense = DB::transaction(function () use ($request, $id) {
                $depense = Rapport::findOrFail($id);
                $depense->update($request->input('main'));

                $depense->details()->delete();

                foreach ($request->input('details') as $detail) {
                    $depense->details()->create($detail);
                }

                return $depense->load('details');
            });

            return response()->json([
                'message' => 'Dépense mise à jour avec succès',
                'data' => $depense,
                'success' => true,
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la dépense',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/depenses.delete/{id}",
     *     summary="Supprimer une dépense",
     *     description="Supprime une dépense et tous ses détails associés.",
     *     operationId="deleteDepense",
     *     tags={"Depenses"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la dépense à supprimer",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Dépense supprimée avec succès"),
     *     @OA\Response(response=404, description="Dépense non trouvée"),
     *     @OA\Response(response=500, description="Erreur serveur interne")
     * )
     */
    public function deleteDepense($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $depense = Rapport::findOrFail($id);
                $depense->delete();
            });

            return response()->json([
                'message' => 'Dépense supprimée avec succès',
                'success' => true,
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la dépense',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
