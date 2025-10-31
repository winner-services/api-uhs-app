<?php

namespace App\Http\Controllers\Api\Payement;

use App\Http\Controllers\Controller;
use App\Models\PayementPane;
use App\Models\PointEau;
use App\Models\PointEauAbonne;
use App\Models\Ticket;
use App\Models\TrasactionTresorerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MaintenanceController extends Controller
{

    /**
     * @OA\Post(
     *     path="/api/payement-maintenance",
     *     summary="Créer un nouveau paiement",
     *     tags={"Payement Maintenance"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"loan_amount","paid_amount","transaction_date","account_id"},
     *             @OA\Property(property="loan_amount", type="number", format="float", example=1000.00),
     *             @OA\Property(property="paid_amount", type="number", format="float", example=500.00),
     *             @OA\Property(property="transaction_date", type="string", format="date", example="2025-09-27"),
     *             @OA\Property(property="ticket_id", type="integer", nullable=true, example=3),
     *             @OA\Property(property="account_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Paiement créé avec succès"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=500, description="Erreur interne du serveur")
     * )
     */
    public function storeMaintenance(Request $request)
    {
        $rules = [
            'transaction_date' => 'required|date',
            'loan_amount'      => 'required|numeric|min:0',
            'paid_amount'      => 'required|numeric|min:0',
            'ticket_id'        => 'nullable|exists:tickets,id',
            'account_id'       => 'nullable|exists:tresoreries,id',
        ];

        $messages = [
            'transaction_date.required' => 'La date de la transaction est obligatoire.',
            'transaction_date.date'     => 'La date de la transaction doit être valide.',
            'loan_amount.required'      => 'Le montant du prêt est obligatoire.',
            'loan_amount.numeric'       => 'Le montant du prêt doit être un nombre.',
            'loan_amount.min'           => 'Le montant du prêt doit être au moins 0.',
            'paid_amount.required'      => 'Le montant payé est obligatoire.',
            'paid_amount.numeric'       => 'Le montant payé doit être un nombre.',
            'paid_amount.min'           => 'Le montant payé doit être au moins 0.',
            'ticket_id.exists'          => 'Le ticket sélectionné n’existe pas.',
            'account_id.exists'         => 'Le compte sélectionné n’existe pas.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors(),
            ], 422);
        }
        DB::beginTransaction();
        try {
            $user    = Auth::user();

            $montantPaye  = $request->paid_amount;
            $loanAmount   = $request->loan_amount;

            $ticket = Ticket::find($request->ticket_id);
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket introuvable.'
                ], 404);
            }
            $borne = PointEau::find($ticket->point_id);
            if (!$borne) {
                return response()->json([
                    'success' => false,
                    'message' => 'Borne (PointEau) liée au ticket introuvable.'
                ], 404);
            }
            $abonne = PointEauAbonne::firstWhere('point_eau_id', $borne->id);
            if (!$abonne) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun abonné trouvé pour cette borne.'
                ], 404);
            }
            // $ticket = Ticket::find($request->ticket_id);
            // $borne = PointEau::find($ticket->point_id);
            // $abonne = PointEauAbonne::where('point_eau_id', $borne->id)->first();


            return PayementPane::create([
                'transaction_date' => $request->transaction_date,
                'reference'        => fake()->unique()->numerify('PAY-#####'),
                'loan_amount'      => $loanAmount,
                'paid_amount'      => $montantPaye,
                'account_id'       => $request->account_id,
                'abonne_id'        => $abonne->abonne_id,
                'addedBy'          => $user->id
            ]);

            $lastTransaction = TrasactionTresorerie::where('account_id', $request->account_id)
                ->latest('id')
                ->first();
            $solde = $lastTransaction ? $lastTransaction->solde : 0;

            TrasactionTresorerie::create([
                'motif'            => 'Paiement facture maintenance',
                'transaction_type' => 'RECETTE',
                'amount'           => $montantPaye,
                'account_id'       => $request->account_id,
                'transaction_date' => $request->transaction_date,
                'addedBy'          => $user->id,
                'reference'        => fake()->unique()->numerify('TRANS-#####'),
                'solde'            => $solde + $montantPaye,
                'facturation_id'   => $request->facture_id
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'data'    => $payement
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l’enregistrement du paiement.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
