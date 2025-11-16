<?php

namespace App\Http\Controllers\Api\Logistique\Entree;

use App\Http\Controllers\Controller;
use App\Models\Entree;
use App\Models\Logistique;
use App\Models\Produit;
use App\Models\TrasactionTresorerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EntreeController extends Controller
{

    /**
     * @OA\Get(
     * path="/api/entrees.getAllData",
     * summary="Liste",
     * tags={"Entrees"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function getallEntree()
    {
        $page = request("paginate", 10);
        // $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = Entree::join('produits', 'entrees.product_id', '=', 'produits.id')
            ->join('users', 'entrees.addedBy', '=', 'users.id')
            ->select('entrees.*', 'users.name as addedBy', 'produits.designation as produit')
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
     *     path="/api/entrees.store",
     *     summary="Créer une entrée de stock",
     *     description="Crée une entrée dans la table `entrees`. La création s'effectue à l'intérieur d'une transaction DB et vérifie que `quantite` > 0.",
     *     tags={"Entrees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantite","product_id"},
     *             @OA\Property(property="quantite", type="integer", example=10, description="Quantité entrée (doit être > 0)"),
     *             @OA\Property(property="prix_unit_achat", type="number", format="float", nullable=true, example=2.50, description="Prix unitaire d'achat (optionnel)"),
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
     *                 @OA\Property(property="account_id", type="integer", example=1)
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

    public function storeEntree(Request $request)
    {
        // Validation des données entrantes
        $validator = Validator::make($request->all(), [
            'quantite'         => 'required|integer|min:1',
            'prix_unit_achat'  => 'nullable|numeric|min:0',
            'product_id'       => 'required|exists:produits,id',
            'account_id' => 'nullable'
        ], [
            'quantite.required' => 'La quantité est obligatoire.',
            'quantite.integer'  => 'La quantité doit être un nombre entier.',
            'quantite.min'      => 'La quantité doit être supérieure à 0.',
            'prix_unit_achat.numeric' => 'Le prix unitaire d\'achat doit être un nombre.',
            'product_id.exists' => 'Le produit sélectionné est invalide.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
                'status'  => 422,
            ], 422);
        }

        // Vérification supplémentaire côté logique
        if ($request->quantite <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'La quantité doit être supérieure à 0.',
                'status'  => 400,
            ], 400);
        }

        // Récupération du produit avant la transaction (fail fast)
        $produit = Produit::findOrFail($request->product_id);

        try {
            DB::beginTransaction();

            $userId = Auth::id(); // peut être null si non authentifié

            $lastTransaction = TrasactionTresorerie::where('account_id', $request->account_id)
                ->latest('id')
                ->first();
            $solde = $lastTransaction ? $lastTransaction->solde : 0;

            if ($solde < $request->prix_unit_achat) {
                return response()->json([
                    'message' => 'Fonds insuffisants',
                    'status'  => 422,
                ], 422);
            }

            // Création de l'entrée
            $entree = Entree::create([
                'quantite'         => $request->quantite,
                'prix_unit_achat'  => $request->prix_unit_achat,
                'product_id'       => $request->product_id,
                'reference'        => fake()->unique()->numerify('ENTR-#####'),
                'account_id' => $request->account_id,
                'date_transaction' => date('Y-m-d'),
                'addedBy'          => $userId,
            ]);

            // Enregistrement de la logistique (quantité précédente / nouvelle quantité)
            $previousQuantity = $produit->quantite;
            $newQuantity = $previousQuantity + $request->quantite;

            Logistique::create([
                'date_transaction'  => now()->toDateString(),
                'previous_quantity' => $previousQuantity,
                'new_quantity'      => $newQuantity,
                'motif'             => 'Achat des produits',
                'type_transaction'  => 'Entrée',
                'product_id'        => $request->product_id,
                'reference'         => fake()->unique()->numerify('ENTR-#####'),
                'addedBy'           => $userId,
                'quantite' => $request->quantite
            ]);

            // Mise à jour atomique du stock : incrémenter la quantité en base
            $produit->increment('quantite', $request->quantite);
            $produit->refresh();

            // $prix_achat = $request->prix_unit_achat * $request->quantite;
            // Enregistrement dans la trésorerie
            TrasactionTresorerie::create([
                'motif'            => 'Paiement Approvisionnement',
                'transaction_type' => 'DEPENSE',
                'amount'           => $request->prix_unit_achat,
                'account_id'       => $request->account_id,
                'transaction_date' => now()->toDateString(),
                'addedBy'          => $userId,
                'reference'        => fake()->unique()->numerify('TRANS-#####'),
                'solde'            => $solde - $request->prix_unit_achat
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Entrée ajoutée avec succès.',
                'status'  => 201,
                'data'    => $entree,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Log de l'erreur si nécessaire : \Log::error($e);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'insertion : ' . $e->getMessage(),
                'status'  => 500,
            ], 500);
        }
    }
}
