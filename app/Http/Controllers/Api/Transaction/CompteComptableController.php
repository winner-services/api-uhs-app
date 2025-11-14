<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Http\Controllers\Controller;
use App\Models\CompteComptable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompteComptableController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/compte-comptables.getOptionsData",
     *      operationId="getAllSpentOptions",
     *      tags={"CompteComptable"},
     *      summary="Get list of Categories",
     *      description="Returns list of Categories",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     * *          @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *       ),
     *     )
     */
    public function getAllSpentOptions()
    {
        $data = CompteComptable::latest()->get();
        $result = [
            'message' => "OK",
            'success' => true,
            'data' => $data,
            'status' => 200
        ];
        return response()->json($result);
    }

    /**
     * @OA\Get(
     *      path="/api/compte-comptables.getAllData",
     *      operationId="getAllSpentCategoryData",
     *      tags={"CompteComptable"},
     *      summary="Get list of Categories",
     *      description="Returns list of Categories",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     * *          @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *       ),
     *     )
     */
    public function getAllSpentCategoryData()
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = CompteComptable::latest()
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
     *     path="/api/compte-comptables.store",
     *     tags={"CompteComptable"},
     *     summary="Créer une catégorie de dépense",
     *     description="Crée une nouvelle catégorie de compte comptable (spent category).",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"designation","transaction_type"},
     *             @OA\Property(property="designation", type="string", example="Frais de bureau"),
     *             @OA\Property(property="description", type="string", example="Papeterie et consommables"),
     *             @OA\Property(property="transaction_type", type="string", example="debit")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Catégorie ajoutée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="success", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error")
     * )
     */

    public function createSpentCategory(Request $request)
    {
        // règles de validation
        $rules = [
            'designation' => 'required|string|max:255|unique:compte_comptables,designation',
            'description' => 'nullable|string',
            'transaction_type' => 'required|string' // si vous avez des valeurs définies, remplacez par: in:debit,credit,...
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors' => $validator->errors(),
                'success' => false,
            ], 422);
        }

        $data = [
            'designation' => trim($request->input('designation')),
            'transaction_type' => $request->input('transaction_type'),
            'description' => $request->filled('description') ? $request->input('description') : '-',
        ];

        try {
            DB::beginTransaction();

            CompteComptable::create($data);

            DB::commit();

            return response()->json([
                'message' => 'ajoutée avec succès.',
                'success' => true,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // En production, n'exposez pas $e->getMessage()
            $errorPayload = ['message' => 'Une erreur est survenue lors de l\'enregistrement.'];

            if (config('app.debug')) {
                $errorPayload['error'] = $e->getMessage();
            }

            return response()->json(array_merge($errorPayload, ['success' => false]), 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/compte-comptables.update/{id}",
     *     tags={"CompteComptable"},
     *     summary="Mettre à jour une catégorie de dépense",
     *     description="Met à jour une catégorie existante identifiée par son id.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la catégorie"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"designation","transaction_type"},
     *             @OA\Property(property="designation", type="string", example="Frais de bureau modifié"),
     *             @OA\Property(property="description", type="string", example="Nouvelle description"),
     *             @OA\Property(property="transaction_type", type="string", example="debit")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Catégorie mise à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Catégorie introuvable"),
     *     @OA\Response(response=422, description="Validation failed"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function updateSpentCategory(Request $request, $id)
    {
        // Vérifier que la catégorie existe
        $categorie = CompteComptable::find($id);
        if (! $categorie) {
            return response()->json([
                'message' => 'Catégorie introuvable.',
                'success' => false
            ], 404);
        }

        // règles de validation
        $rules = [
            // unique:table,column,except,idColumn (ici idColumn = id)
            'designation' => "required|string|max:255|unique:compte_comptables,designation,{$id}",
            'description' => 'nullable|string',
            'transaction_type' => 'required|string' // ou 'required|in:debit,credit' si vous avez des valeurs fixes
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors' => $validator->errors(),
                'success' => false
            ], 422);
        }

        $data = [
            'designation' => trim($request->input('designation')),
            'transaction_type' => $request->input('transaction_type'),
            'description' => $request->filled('description') ? $request->input('description') : '-',
        ];

        try {
            DB::beginTransaction();

            $categorie->update($data);

            DB::commit();

            return response()->json([
                'message' => 'mise à jour avec succès.',
                'success' => true,
                'data' => $categorie->fresh()
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            $payload = ['message' => 'Une erreur est survenue lors de la mise à jour.', 'success' => false];
            if (config('app.debug')) {
                $payload['error'] = $e->getMessage();
            }

            return response()->json($payload, 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/compte-comptables.delete/{id}",
     *     tags={"CompteComptable"},
     *     summary="Supprimer une catégorie de dépense",
     *     description="Supprime une catégorie comptable existante par son ID.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la catégorie",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Catégorie supprimée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie supprimée avec succès."),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Catégorie introuvable",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie introuvable."),
     *             @OA\Property(property="success", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function deleteSpentCategory($id)
    {
        $categorie = CompteComptable::find($id);

        if (! $categorie) {
            return response()->json([
                'message' => 'Catégorie introuvable.',
                'success' => false
            ], 404);
        }

        try {
            DB::beginTransaction();

            $categorie->delete();

            DB::commit();

            return response()->json([
                'message' => 'Catégorie supprimée avec succès.',
                'success' => true
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            $payload = [
                'message' => 'Une erreur est survenue lors de la suppression.',
                'success' => false
            ];

            if (config('app.debug')) {
                $payload['error'] = $e->getMessage();
            }

            return response()->json($payload, 500);
        }
    }
}
