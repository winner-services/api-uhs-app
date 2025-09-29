<?php

namespace App\Http\Controllers\Api\Payement;

use App\Http\Controllers\Controller;
use App\Models\Facturation;
use App\Models\Payement;
use App\Models\TrasactionTresorerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PayementController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/payements.getAllData",
     *     summary="Liste des Payements",
     *     description="Récupérer toutes les Payements avec leurs abonnés",
     *     tags={"Payements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des Payements",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Facturation"))
     *         )
     *     )
     * )
     */

    public function getPayement()
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = Payement::join('tresoreries', 'payements.account_id', '=', 'tresoreries.id')
            ->join('facturations', 'payements.facture_id', '=', 'facturations.id')
            ->join('users', 'payements.addedBy', '=', 'users.id')
            ->join('abonnes', 'facturations.abonne_id', '=', 'abonnes.id')
            ->select('payements.*', 'abonnes.nom as abonne', 'users.name as addedBy', 'tresoreries.dedignation as tresorerie')
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
     *     path="/api/payements.store",
     *     summary="Créer un paiement",
     *     tags={"Payements"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"loan_amount","paid_amount","transaction_date","account_id","facture_id"},
     *             @OA\Property(property="loan_amount", type="number", format="decimal", example=1000.00),
     *             @OA\Property(property="paid_amount", type="number", format="decimal", example=500.00),
     *             @OA\Property(property="transaction_date", type="string", format="date", example="2025-09-27"),
     *             @OA\Property(property="account_id", type="integer", example=1),
     *             @OA\Property(property="facture_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Paiement créé avec succès"),
     *     @OA\Response(response=400, description="Erreur de validation"),
     *     @OA\Response(response=500, description="Erreur serveur lors de la création")
     * )
     */
    public function store(Request $request)
{
    $rules = [
        'loan_amount'      => ['required', 'numeric'],
        'paid_amount'      => ['required', 'numeric'],
        'transaction_date' => ['required', 'date'],
        'account_id'       => ['required', 'exists:tresoreries,id'],
        'facture_id'       => ['required', 'exists:facturations,id'],
    ];

    $messages = [
        'loan_amount.required'      => 'Le montant du prêt est obligatoire.',
        'loan_amount.numeric'       => 'Le montant du prêt doit être un nombre.',
        'paid_amount.required'      => 'Le montant payé est obligatoire.',
        'paid_amount.numeric'       => 'Le montant payé doit être un nombre.',
        'transaction_date.required' => 'La date de la transaction est obligatoire.',
        'transaction_date.date'     => 'La date de la transaction doit être valide.',
        'account_id.required'       => 'Le compte associé est obligatoire.',
        'account_id.exists'         => 'Le compte sélectionné n’existe pas.',
        'facture_id.required'       => 'La facture associée est obligatoire.',
        'facture_id.exists'         => 'La facture sélectionnée n’existe pas.',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Les données envoyées ne sont pas valides.',
            'errors'  => $validator->errors(),
        ], 422);
    }

    DB::beginTransaction();
    try {
        $user    = Auth::user();
        $facture = Facturation::findOrFail($request->facture_id);

        $montantPaye  = $request->paid_amount;
        $lastTransaction = TrasactionTresorerie::where('account_id', $request->account_id)
            ->latest('id')
            ->first();

        $solde = $lastTransaction ? $lastTransaction->solde : 0;

        // Vérification cohérence montant payé
        if ($montantPaye <= 0) {
            return response()->json([
                'message' => 'Le montant payé doit être supérieur à 0.',
                'status'  => 422,
            ], 422);
        }

        if ($montantPaye > $request->loan_amount) {
            return response()->json([
                'message' => 'Le montant payé ne doit pas être supérieur au montant du prêt.',
                'status'  => 422,
            ], 422);
        }

        // Mise à jour de la dette
        $facture->dette -= $montantPaye;
        if ($facture->dette <= 0) {
            $facture->dette  = 0;
            $facture->status = 'payé';
        }
        $facture->save();

        // Création du paiement
        $payement = Payement::create([
            'loan_amount'      => max(0, $request->loan_amount - $montantPaye),
            'paid_amount'      => $montantPaye,
            'transaction_date' => $request->transaction_date,
            'account_id'       => $request->account_id,
            'facture_id'       => $request->facture_id,
            'addedBy'          => $user->id,
            'reference'        => fake()->unique()->numerify('PAY-#####'),
        ]);

        // Enregistrement dans la trésorerie
        TrasactionTresorerie::create([
            'motif'            => 'Paiement de la facture',
            'transaction_type' => 'RECETTE',
            'amount'           => $montantPaye,
            'account_id'       => $request->account_id,
            'transaction_date' => $request->transaction_date,
            'addedBy'          => $user->id,
            'reference'        => fake()->unique()->numerify('TRANS-#####'),
            'solde'            => $solde + $montantPaye,
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Paiement ajouté avec succès.',
            'success' => true,
            'status'  => 201,
            'data'    => $payement,
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Erreur lors de la création du paiement.',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

}
