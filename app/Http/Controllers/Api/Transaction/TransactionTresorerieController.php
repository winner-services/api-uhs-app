<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Http\Controllers\Controller;
use App\Models\HistoriqueTransactions;
use App\Models\TrasactionTresorerie;
use App\Models\Tresorerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TransactionTresorerieController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/transaction-tresoreries.getAllData",
     * summary="Liste des Trasactions",
     * tags={"TransactionTrésoreries"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function getTransactionData()
    {
        // $caisse = Tresorerie::where('designation', 'Caisse')->first();
        $caisse = Tresorerie::first();
        if (is_null($caisse)) {
            return response()->json([
                'message' => "Compte introuvable",
                'success' => false,
                'status' => 404
            ]);
        }
        $idCompte = request("account_id", null);

        if ($idCompte === null || $idCompte === 'null') {
            $idCompte = $caisse->id;
        }

        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = TrasactionTresorerie::join('users', 'trasaction_tresoreries.addedBy', '=', 'users.id')
            ->join('tresoreries', 'trasaction_tresoreries.account_id', '=', 'tresoreries.id')
            ->select('trasaction_tresoreries.*', 'users.name as addedBy', 'tresoreries.designation as account_name')
            ->where('trasaction_tresoreries.status', true)
            ->where('account_id', $idCompte)
            ->searh(trim($q))
            ->orderBy($sort_field, $sort_direction)
            ->paginate($page);
        $result = [
            'message' => "OK",
            'success' => true,
            'data' => $data,
            'status' => 200,
        ];
        return response()->json($result);
    }

    /**
     * @OA\Post(
     *     path="/api/transaction-tresoreries.store",
     *     summary="Créer une transaction de trésorerie",
     *     tags={"TransactionTrésoreries"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"transaction_date","account_id","amount","transaction_type"},
     *             @OA\Property(property="motif", type="string", example="Achat fournitures"),
     *             @OA\Property(property="transaction_date", type="string", format="date", example="2025-09-27"),
     *             @OA\Property(property="account_id", type="integer", example=1),
     *             @OA\Property(property="amount", type="number", format="decimal", example=500.00),
     *             @OA\Property(property="transaction_type", type="string", example="depot"),
     *             @OA\Property(property="beneficiaire", type="string", example="winner")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Transaction créée avec succès"),
     *     @OA\Response(response=400, description="Erreur de validation"),
     *     @OA\Response(response=409, description="Transaction déjà existante")
     * )
     */
    public function store(Request $request)
    {
        $rules = [
            'motif'            => ['nullable', 'string'],
            'transaction_date' => ['required', 'date'],
            'account_id'       => ['required', 'exists:tresoreries,id'],
            'amount'           => ['required', 'numeric'],
            'transaction_type' => ['required', 'string'],
            'beneficiaire' => 'nullable'
        ];

        $messages = [
            'transaction_date.required' => 'La date de la transaction est obligatoire.',
            'transaction_date.date'     => 'La date de la transaction doit être valide.',
            'account_id.required'       => 'Le compte associé est obligatoire.',
            'account_id.exists'         => 'Le compte sélectionné n’existe pas.',
            'amount.required'           => 'Le montant de la transaction est obligatoire.',
            'amount.numeric'            => 'Le montant doit être un nombre.',
            'transaction_type.required' => 'Le type de transaction est obligatoire.',
            'transaction_type.string'   => 'Le type de transaction doit être une chaîne de caractères.'
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
            $user = Auth::user();
            $lastTransaction = TrasactionTresorerie::where('account_id', $request->input('account_id'))
                ->latest('id')
                ->first();
            $solde = $lastTransaction ? $lastTransaction->solde : 0;

            if ($request->input('transaction_type') === 'DEPENSE') {
                if ($solde < $request->input('amount')) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'solde insuffisant.',
                        'success' => false,
                        'status' => 400
                    ]);
                }
            }
            $transaction = [
                'transaction_date' => $request->input('transaction_date'),
                'account_id'       => $request->input('account_id'),
                'amount'           => $request->input('amount'),
                'transaction_type' => $request->input('transaction_type'),
                'solde'            => ($request->input('transaction_type') === 'RECETTE')
                    ? $solde + $request->input('amount')
                    : ($request->input('transaction_type') === 'DEPENSE' ? $solde - $request->input('amount') : $solde),
                'reference'        => fake()->unique()->numerify('TRANS-#####'),
                'addedBy'          => $user->id,
                'beneficiaire' => $request->beneficiaire
            ];
            $transaction['motif'] = $request->filled('motif') ? $request->motif : '-';

            TrasactionTresorerie::create($transaction);

            DB::commit();

            return response()->json([
                'message' => "Transaction ajoutée avec succès",
                'success' => true,
                'status'  => 201,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création de la transaction.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/transaction-tresoreries.update/{id}",
     *     summary="Mettre à jour une transaction de trésorerie",
     *     tags={"TransactionTrésoreries"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la transaction",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="motif", type="string", example="Achat fournitures"),
     *             @OA\Property(property="transaction_date", type="string", format="date", example="2025-09-27"),
     *             @OA\Property(property="account_id", type="integer", example=1),
     *             @OA\Property(property="amount", type="number", format="decimal", example=500.00),
     *             @OA\Property(property="transaction_type", type="string", example="DEPENSE"),
     *             @OA\Property(property="beneficiaire", type="string", example="winner")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Transaction mise à jour avec succès"),
     *     @OA\Response(response=400, description="Erreur de validation"),
     *     @OA\Response(response=404, description="Transaction non trouvée")
     * )
     */

    public function update(Request $request, int $id)
    {
        $transaction = TrasactionTresorerie::findOrFail($id);

        $rules = [
            'motif'            => ['nullable', 'string'],
            'transaction_date' => ['sometimes', 'required', 'date'],
            'account_id'       => ['sometimes', 'required', 'exists:tresoreries,id'],
            'amount'           => ['sometimes', 'required', 'numeric'],
            'transaction_type' => ['sometimes', 'required', 'string'],
            'beneficiaire' => 'nullable'
        ];

        $messages = [
            'transaction_date.required' => 'La date de la transaction est obligatoire.',
            'transaction_date.date'     => 'La date de la transaction doit être valide.',
            'account_id.required'       => 'Le compte associé est obligatoire.',
            'account_id.exists'         => 'Le compte sélectionné n’existe pas.',
            'amount.required'           => 'Le montant de la transaction est obligatoire.',
            'amount.numeric'            => 'Le montant doit être un nombre.',
            'transaction_type.required' => 'Le type de transaction est obligatoire.',
            'transaction_type.string'   => 'Le type de transaction doit être une chaîne de caractères.'
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
            $user = Auth::user();

            // Mise à jour du solde si account_id ou amount ou type_transaction change
            $solde = $transaction->solde;
            if ($request->filled('amount') || $request->filled('transaction_type') || $request->filled('account_id')) {
                $lastTransaction = TrasactionTresorerie::where('account_id', $request->input('account_id', $transaction->account_id))
                    ->where('id', '<>', $transaction->id)
                    ->latest('id')
                    ->first();
                $solde = $lastTransaction ? $lastTransaction->solde : 0;

                $transaction_type = $request->input('transaction_type', $transaction->transaction_type);
                $amount = $request->input('amount', $transaction->amount);

                $solde = ($transaction_type === 'RECETTE') ? $solde + $amount
                    : ($transaction_type === 'DEPENSE' ? $solde - $amount : $solde);
            }

            $transaction->update([
                'motif'            => $request->filled('motif') ? $request->motif : $transaction->motif,
                'transaction_date' => $request->input('transaction_date', $transaction->transaction_date),
                'account_id'       => $request->input('account_id', $transaction->account_id),
                'amount'           => $request->input('amount', $transaction->amount),
                'transaction_type' => $request->input('transaction_type', $transaction->transaction_type),
                'solde'            => $solde,
                'beneficiaire' => $request->beneficiaire
            ]);

            DB::commit();

            return response()->json([
                'message' => "Transaction mise à jour avec succès",
                'success' => true,
                'status'  => 200,
                'data'    => $transaction
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la transaction.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/transaction-tresoreries.delete/{id}",
     *     summary="Supprimer une transaction de trésorerie",
     *     tags={"TransactionTrésoreries"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la transaction",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Transaction supprimée avec succès"),
     *     @OA\Response(response=404, description="Transaction non trouvée")
     * )
     */
    public function destroy(int $id)
    {
        $transaction = TrasactionTresorerie::findOrFail($id);

        try {
            DB::beginTransaction();
            $transaction->delete();
            DB::commit();

            return response()->json([
                'message' => 'Transaction supprimée avec succès',
                'success' => true,
                'status'  => 204
            ], 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la suppression de la transaction.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/transfer-fonds.store",
     *     summary="Transférer des fonds entre deux comptes de la trésorerie",
     *     tags={"Transfert de fonds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"account_from_id","account_to_id","montant","type_transaction"},
     *             @OA\Property(property="account_from_id", type="integer", example=1),
     *             @OA\Property(property="account_to_id", type="integer", example=2),
     *             @OA\Property(property="montant", type="number", format="float", example=150.50),
     *             @OA\Property(property="type_transaction", type="string", example="Virement"),
     *             @OA\Property(property="date_transaction", type="string", format="date-time", example="2025-11-14T08:00:00Z"),
     *             @OA\Property(property="description", type="string", example="Transfert entre caisse et banque"),
     *             @OA\Property(property="beneficiaire", type="string", example="Winner")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Transfert effectué avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Requête invalide"),
     *     @OA\Response(response=500, description="Erreur serveur")
     * )
     */

    public function transferFunds(Request $request)
    {
        // Validation
        $rules = [
            'account_from_id' => ['required', 'integer', 'exists:tresoreries,id'],
            'account_to_id'   => ['required', 'integer', 'exists:tresoreries,id', 'different:account_from_id'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'date_transaction' => ['nullable', 'date'],
            'description'     => ['nullable', 'string']
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Données invalides.',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        $user = Auth::user();

        try {

            // Récupérer les dernières transactions (pour obtenir le solde courant)
            $from = TrasactionTresorerie::where('account_id', $request->account_from_id)
                ->latest('id')
                ->first();

            $to = TrasactionTresorerie::where('account_id', $request->account_to_id)
                ->latest('id')
                ->first();

            $solde_from = $from ? $from->solde : 0;
            $solde_to   = $to ? $to->solde : 0;

            // Vérification du solde suffisant
            if ($solde_from < $request->amount) {
                // On peut retourner 422 pour une erreur de logique métier, ou garder 500 si tu préfères.
                DB::rollBack();
                return response()->json([
                    'message' => 'Solde insuffisant.',
                    'success' => false,
                    'status' => 422
                ], 422);
            }

            // Historique global du transfert
            $transaction = HistoriqueTransactions::create([
                'account_from_id' => $request->account_from_id,
                'account_to_id'   => $request->account_to_id,
                'montant'         => $request->amount,
                'type_transaction' => 'Transerf de fonds',
                'description'     => $request->description ?? null,
                'created_by'      => $user->id,
                'date_transaction' => $request->date_transaction ?? now()
            ]);

            // Référence unique (production-safe)
            $reference = 'TRANS-' . Str::upper(Str::random(5));

            // Entrée pour le compte débité (DEPENSE)
            $debitEntry = TrasactionTresorerie::create([
                'motif'            => 'Transerf de fonds',
                'transaction_type' => 'DEPENSE',
                'amount'           => $request->amount,
                'account_id'       => $request->account_from_id,
                'transaction_date' => $request->date_transaction ?? now(),
                'addedBy'          => $user->id,
                'reference'        => $reference . '-D',
                'solde'            => $solde_from - $request->amount,
                'beneficiaire' => $request->beneficiaire
            ]);

            // Entrée pour le compte crédité (RECETTE)
            $creditEntry = TrasactionTresorerie::create([
                'motif'            => 'Transerf de fonds',
                'transaction_type' => 'RECETTE',
                'amount'           => $request->amount,
                'account_id'       => $request->account_to_id,
                'transaction_date' => $request->date_transaction ?? now(),
                'addedBy'          => $user->id,
                'reference'        => $reference . '-C',
                'solde'            => $solde_to + $request->amount,
                'beneficiaire' => $request->beneficiaire
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transfert effectué avec succès.',
                'success' => true,
                'status'    => 201
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            $payload = ['message' => 'Erreur lors du transfert.', 'success' => false];
            if (config('app.debug')) {
                $payload['error'] = $e->getMessage();
                $payload['trace'] = $e->getTraceAsString();
            }
            return response()->json($payload, 500);
        }
    }
}
