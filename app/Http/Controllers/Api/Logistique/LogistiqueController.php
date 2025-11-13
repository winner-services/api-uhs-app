<?php

namespace App\Http\Controllers\Api\Logistique;

use App\Http\Controllers\Controller;
use App\Models\Logistique;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LogistiqueController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/produits.getAllData",
     * summary="Liste des produits",
     * tags={"Produits"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function getallProduitData()
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = Produit::searh(trim($q))
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
     * @OA\Get(
     * path="/api/produits.getOptionsData",
     * summary="Liste des produits",
     * tags={"Produits"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function getallProduit()
    {
        $result = [
            'message' => "OK",
            'success' => true,
            'data' => Produit::latest()->get(),
            'status' => 200
        ];
        return response()->json($result);
    }

    /**
     * @OA\Post(
     *     path="/api/produits.store",
     *     summary="Créer",
     *     tags={"Produits"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"designation","quantite","prix_unit_achat","prix_unit_vente"},
     *             @OA\Property(property="designation", type="text", format="string", example=1000.00),
     *             @OA\Property(property="quantite", type="number", format="float", example=500.00),
     *             @OA\Property(property="prix_unit_achat", type="integer", nullable=true, example=3),
     *             @OA\Property(property="prix_unit_vente", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Paiement créé avec succès"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=500, description="Erreur interne du serveur")
     * )
     */

    public function storeProduit(Request $request)
    {
        $rules = [
            'designation' => ['required', 'string', 'max:255'],
            'quantite' => ['required', 'integer', 'min:0'],
            'prix_unit_achat' => ['nullable', 'numeric', 'min:0'],
            'prix_unit_vente' => ['required', 'numeric', 'min:0'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Données invalides.',
                'errors' => $validator->errors()
            ], 422);
        }
        $produit = Produit::where('designation', $request->designation)->first();

        if ($produit) {
            return response()->json([
                'message' => 'Ce produit existe',
                'status' => 422
            ]);
        }
        try {
            DB::beginTransaction();

            $user = Auth::user();

            $produit = Produit::create([
                'designation' => $request->input('designation'),
                'quantite' => $request->input('quantite'),
                'prix_unit_achat' => $request->input('prix_unit_achat'),
                'prix_unit_vente' => $request->input('prix_unit_vente'),
                'addedBy' => $user ? $user->id : null,
            ]);
            $productJournal = [
                'date_transaction' => date('Y-m-d'),
                'previous_quantity' => 0,
                'new_quantity' => $request->input('quantite'),
                'motif' => 'Initialisation du stock',
                'type_transaction' => 'Entrée',
                'product_id' => $produit->id,
                'reference'            => fake()->unique()->numerify('ENT-#####'),
                'addedBy' => $user ? $user->id : null
            ];

            Logistique::create($productJournal);

            DB::commit();

            return response()->json([
                'message' => 'Produit créé avec succès.',
                'success' => true,
                'status' => 201,
                'data' => $produit
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création du produit.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/produits.update/{id}",
     *     summary="Modifier un produit existant",
     *     tags={"Produits"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du produit à modifier",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"designation","quantite","prix_unit_achat","prix_unit_vente"},
     *             @OA\Property(property="designation", type="string", example="Savon liquide"),
     *             @OA\Property(property="quantite", type="number", format="float", example=250.00),
     *             @OA\Property(property="prix_unit_achat", type="number", format="float", example=1.50),
     *             @OA\Property(property="prix_unit_vente", type="number", format="float", example=2.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Produit mis à jour avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Produit non trouvé"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur"
     *     )
     * )
     */

    public function updateProduit(Request $request, $id)
    {
        $rules = [
            'designation' => ['required', 'string', 'max:255'],
            'quantite' => ['required', 'integer', 'min:0'],
            'prix_unit_achat' => ['nullable', 'numeric', 'min:0'],
            'prix_unit_vente' => ['required', 'numeric', 'min:0'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Données invalides.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 🔹 Vérifier que le produit existe
            $produit = Produit::find($id);
            if (!$produit) {
                return response()->json([
                    'message' => 'Produit introuvable.',
                    'status' => 404
                ], 404);
            }

            // 🔹 Vérifier unicité de la désignation (sauf si c’est la même)
            $existing = Produit::where('designation', $request->designation)
                ->where('id', '!=', $id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Un autre produit avec cette désignation existe déjà.',
                    'status' => 422
                ], 422);
            }

            $user = Auth::user();

            // 🔹 Stock précédent pour journal
            $previousQuantity = $produit->quantite;
            $newQuantity = $request->input('quantite');
            $motif = $newQuantity > $previousQuantity
                ? 'Augmentation du stock'
                : ($newQuantity < $previousQuantity ? 'Réduction du stock' : 'Mise à jour sans changement de stock');

            $typeTransaction = $newQuantity > $previousQuantity ? 'Entrée' : 'Sortie';

            // 🔹 Mise à jour du produit
            $produit->update([
                'designation' => $request->input('designation'),
                'quantite' => $newQuantity,
                'prix_unit_achat' => $request->input('prix_unit_achat'),
                'prix_unit_vente' => $request->input('prix_unit_vente'),
                'updatedBy' => $user ? $user->id : null,
            ]);

            // 🔹 Enregistrer dans le journal logistique
            if ($previousQuantity != $newQuantity) {
                Logistique::create([
                    'date_transaction' => date('Y-m-d'),
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $newQuantity,
                    'motif' => $motif,
                    'type_transaction' => $typeTransaction,
                    'product_id' => $produit->id,
                    'addedBy' => $user ? $user->id : null
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Produit mis à jour avec succès.',
                'success' => true,
                'status' => 200,
                'data' => $produit
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la mise à jour du produit.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *   path="/api/produits.delete/{id}",
     *   summary="Supprimer un produit",
     *   tags={"Produits"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Produit supprimé",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Produit supprimé avec succès."),
     *       @OA\Property(property="success", type="boolean", example=true)
     *     )
     *   ),
     *   @OA\Response(response=404, description="Produit non trouvé"),
     *   @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function destroyProduit($id)
    {
        $produit = Produit::findOrFail($id);

        try {
            DB::beginTransaction();

            $produit->delete();

            DB::commit();

            return response()->json([
                'message' => 'Produit supprimé avec succès.',
                'success' => true
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la suppression du produit.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
