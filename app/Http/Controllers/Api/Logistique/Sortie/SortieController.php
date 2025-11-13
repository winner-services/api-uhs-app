<?php

namespace App\Http\Controllers\Api\Logistique\Sortie;

use App\Http\Controllers\Controller;
use App\Models\Logistique;
use App\Models\Produit;
use App\Models\Sortie;
use App\Models\TrasactionTresorerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SortieController extends Controller
{

    /**
     * @OA\Get(
     * path="/api/sorties.getAllData",
     * summary="Liste",
     * tags={"Sorties"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function getallSortie()
    {
        $page = request("paginate", 10);
        // $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = Sortie::join('produits', 'sorties.product_id', '=', 'produits.id')
            ->join('users', 'sorties.addedBy', '=', 'users.id')
            ->select('sorties.*', 'users.name as addedBy', 'produits.designation as produit')
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
     *     path="/api/sortie.store",
     *     summary="Créer une entrée de stock",
     *     description="Crée une entrée dans la table `entrees`. La création s'effectue à l'intérieur d'une transaction DB et vérifie que `quantite` > 0.",
     *     tags={"Sorties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantite","product_id"},
     *             @OA\Property(property="quantite", type="integer", example=10, description="Quantité entrée (doit être > 0)"),
     *             @OA\Property(property="prix_unit_vente", type="number", format="float", nullable=true, example=2.50, description="Prix unitaire d'achat (optionnel)"),
     *             @OA\Property(property="product_id", type="integer", example=1, description="ID du produit (doit exister dans la table produits)"),
     *             @OA\Property(property="addedBy", type="integer", nullable=true, example=5, description="ID de l'utilisateur ayant ajouté (optionnel — généralement récupéré depuis le token)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Entrée créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Entrée ajoutée avec succès."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="quantite", type="integer", example=10),
     *                 @OA\Property(property="prix_unit_achat", type="number", format="float", example=2.50),
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="addedBy", type="integer", example=5),
     *                 @OA\Property(property="account_id", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", example="2025-11-13T11:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Requête incorrecte (ex: quantite <= 0)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="La quantité doit être supérieure à 0.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur de validation"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur lors de l'insertion : ...")
     *         )
     *     )
     * )
     */

    public function storeSortie(Request $request)
    {
        // ✅ Validation des données entrantes
        $validator = Validator::make($request->all(), [
            'quantite'        => 'required|integer|min:1',
            'prix_unit_vente' => 'nullable|numeric|min:0',
            'product_id'      => 'required|exists:produits,id',
            'account_id' => 'required'
        ], [
            'quantite.required' => 'La quantité est obligatoire.',
            'quantite.integer'  => 'La quantité doit être un nombre entier.',
            'quantite.min'      => 'La quantité doit être supérieure à 0.',
            'prix_unit_vente.numeric' => 'Le prix unitaire de vente doit être un nombre.',
            'product_id.exists' => 'Le produit sélectionné est invalide.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'status' => 422,
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Vérification supplémentaire (sécurité côté logique)
        if ($request->quantite <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'La quantité doit être supérieure à 0.',
                'status' => 400
            ], 400);
        }

        $produit = Produit::findOrFail($request->product_id);

        if ($request->quantite > $produit->quantite) {
            return response()->json([
                'success' => false,
                'message' => 'La quantité ne doit pas être supérieure au stock.',
                'status' => 400
            ], 400);
        }

        // Tout est validé -> on effectue la transaction
        try {
            DB::beginTransaction();

            $userId = Auth::id(); // peut être null si non authentifié

            $lastTransaction = TrasactionTresorerie::where('account_id', $request->account_id)
                ->latest('id')
                ->first();
            $solde = $lastTransaction ? $lastTransaction->solde : 0;

            // 1️⃣ Vérifier si montant payé <= 0
            if ($request->prix_unit_vente <= 0) {
                return response()->json([
                    'message' => 'Le montant payé doit être supérieur à 0.',
                    'status'  => 422,
                ], 422);
            }

            // Création de la sortie (nommé $sortie ici, pas $entree)
            $sortie = Sortie::create([
                'quantite'        => $request->quantite,
                'prix_unit_vente' => $request->prix_unit_vente,
                'product_id'      => $request->product_id,
                // Utilise uniqid pour éviter d'avoir à importer des helpers supplémentaires
                'reference'       => fake()->unique()->numerify('SORT-#####'),
                'account_id' => $request->account_id,
                'addedBy'         => $userId,
            ]);

            // Enregistrement dans la logistique : quantité précédente + nouvelle quantité prévue
            $previousQuantity = $produit->quantite;
            $newQuantity = $previousQuantity - $request->quantite;

            Logistique::create([
                'date_transaction'   => now()->toDateString(),
                'previous_quantity'  => $previousQuantity,
                'new_quantity'       => $newQuantity,
                'motif'              => 'Vente des produits',
                'type_transaction'   => 'Sortie',
                'product_id'         => $request->product_id,
                'reference'          => fake()->unique()->numerify('SORT-#####'),
                'addedBy'            => $userId,
            ]);

            $produit->decrement('quantite', $request->quantite);

            $produit->refresh();


            // Enregistrement dans la trésorerie
            TrasactionTresorerie::create([
                'motif'            => 'Paiement de la Vente Produits',
                'transaction_type' => 'RECETTE',
                'amount'           => $request->prix_unit_vente,
                'account_id'       => $request->account_id,
                'transaction_date' => now()->toDateString(),
                'addedBy'          => $userId,
                'reference'        => fake()->unique()->numerify('TRANS-#####'),
                'solde'            => $solde + $request->prix_unit_vente
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sortie ajoutée avec succès.',
                'status'  => 201,
                'data'    => $sortie,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // log l'erreur si souhaité : \Log::error($e);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'insertion : ' . $e->getMessage(),
                'status'  => 500
            ], 500);
        }
    }

    // public function storeSortie(Request $request)
    // {
    //     // ✅ Validation des données entrantes
    //     $validator = Validator::make($request->all(), [
    //         'quantite'        => 'required|integer|min:1',
    //         'prix_unit_vente' => 'nullable|numeric|min:0',
    //         'product_id'      => 'required|exists:produits,id',
    //     ], [
    //         'quantite.required' => 'La quantité est obligatoire.',
    //         'quantite.integer'  => 'La quantité doit être un nombre entier.',
    //         'quantite.min'      => 'La quantité doit être supérieure à 0.',
    //         'prix_unit_achat.numeric' => 'Le prix unitaire d\'achat doit être un nombre.',
    //         'product_id.exists' => 'Le produit sélectionné est invalide.',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur de validation',
    //             'status' => 422,
    //             'errors'  => $validator->errors(),
    //         ], 422);
    //     }

    //     // ✅ Vérification supplémentaire (sécurité côté logique)
    //     if ($request->quantite <= 0) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'La quantité doit être supérieure à 0.',
    //             'status' => 400
    //         ], 400);
    //     }
    //     $produit1 = Produit::findOrFail($request->product_id);
    //     if ($request->quantite >  $produit1->quantite) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'La quantité ne doit pas être supérieure au stock.',
    //             'status' => 400
    //         ], 400);
    //     }

    //     try {

    //         DB::beginTransaction();
    //         $user = Auth::user();
    //         $produit = Produit::findOrFail($request->product_id);

    //         $entree = Sortie::create([
    //             'quantite'        => $request->quantite,
    //             'prix_unit_vente' => $request->prix_unit_vente,
    //             'product_id'      => $request->product_id,
    //             'reference'            => fake()->unique()->numerify('SORT-#####'),
    //             'addedBy'         => $user->id
    //         ]);
    //         Logistique::create([
    //             'date_transaction' => date('Y-m-d'),
    //             'previous_quantity' => $produit->quantite,
    //             'new_quantity' => $produit->quantite - $request->quantite,
    //             'motif' => 'Vente des produits',
    //             'type_transaction' => 'Sortie',
    //             'product_id' => $request->product_id,
    //             'reference'            => fake()->unique()->numerify('SORT-#####'),
    //             'addedBy' => $user ? $user->id : null
    //         ]);
    //         $produit->increment('quantite', $request->quantite);
    //         $produit->quantite -= $request->quantite;
    //         $produit->save();

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Sortie ajoutée avec succès.',
    //             'status' => 201,
    //             'data'    => $entree,
    //         ], 201);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur lors de l\'insertion : ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }
}
