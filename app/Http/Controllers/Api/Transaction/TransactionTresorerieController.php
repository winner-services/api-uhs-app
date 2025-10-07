<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Http\Controllers\Controller;
use App\Models\TrasactionTresorerie;
use App\Models\Tresorerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
        $caisse = Tresorerie::where('designation', 'Caisse')->first();
        if (is_null($caisse)) {
            return response()->json([
                'message' => "Compte 'CAISSE' introuvable",
                'success' => false,
                'status' => 404
            ]);
        }
        $idCompte = request("account_id", null);

        if ($idCompte === null || $idCompte === 'null') {
            $idCompte = $caisse->id;
            dd($idCompte);
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
     *             @OA\Property(property="transaction_type", type="string", example="depot")
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
            'transaction_type' => ['required', 'string']
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
     *             @OA\Property(property="transaction_type", type="string", example="DEPENSE")
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
}
