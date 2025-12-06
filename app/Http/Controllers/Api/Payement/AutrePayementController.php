<?php

namespace App\Http\Controllers\Api\Payement;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Bornier;
use App\Models\TrasactionTresorerie;
use App\Models\Versement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AutrePayementController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/versements.getAllData",
     *     summary="Liste des versements",
     *     description="Récupérer toutes les Payements avec leurs abonnés",
     *     tags={"Versements"},
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
    public function getVersement()
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = Versement::join('tresoreries', 'versements.account_id', '=', 'tresoreries.id')
            ->join('users as u1', 'versements.addedBy', '=', 'u1.id')
            ->join('borniers as u2', 'versements.agent_id', '=', 'u2.id')
            ->select('versements.*', 'u2.nom as agent', 'u1.name as addedBy', 'tresoreries.designation as tresorerie')
            ->latest()
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
     *     path="/api/versements.store",
     *     tags={"Versements"},
     *     summary="Créer un nouveau versement",
     *     description="Crée un nouveau versement avec les informations fournies. Le taux est de 30% par défaut.",
     *     operationId="storeVersement",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"transaction_date", "amount", "paid_amount"},
     *             @OA\Property(property="transaction_date", type="string", format="date", example="2025-10-09"),
     *             @OA\Property(property="amount", type="number", format="float", example=1000.00),
     *             @OA\Property(property="paid_amount", type="number", format="float", example=700.00),
     *             @OA\Property(property="taux", type="number", format="float", example=30.00),
     *             @OA\Property(property="account_id", type="integer", example=1),
     *             @OA\Property(property="agent_id", type="integer", example=3),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Versement créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Versement créé avec succès."),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="status", type="integer", example=201),
     *             @OA\Property(property="data", ref="#/components/schemas/Versement")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation"
     *     )
     * )
     */

    public function storeVersement(Request $request)
    {
        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'amount'           => ['required', 'numeric', 'min:0'],
            'paid_amount'      => ['required', 'numeric', 'min:0'],
            'taux'             => ['nullable', 'numeric', 'min:0', 'max:100'],
            'account_id'       => ['required', 'exists:tresoreries,id'],
            'agent_id'         => ['nullable', 'exists:borniers,id']
        ]);

        return DB::transaction(function () use ($validated) {
            $about = About::first();

            if ($about && $about->logo) {
                $path = storage_path('app/public/' . $about->logo);

                if (file_exists($path)) {
                    $mime = mime_content_type($path);
                    $data = base64_encode(file_get_contents($path));
                    $about->logo = "data:$mime;base64,$data";
                } else {
                    // Si fichier manquant, on peut utiliser une image par défaut
                    $about->logo = asset('images/default-logo.png');
                }
            }
            $totalAmount = round($validated['amount'] - $validated['paid_amount'], 2);

            $lastTransaction = TrasactionTresorerie::where('account_id', $validated['account_id'])
                ->latest('id')
                ->first();
            $solde = $lastTransaction ? $lastTransaction->solde : 0;

            $bornier = Bornier::find($validated['agent_id']);

            $versement = Versement::create([
                'transaction_date' => $validated['transaction_date'],
                'amount'           => $validated['amount'],
                'paid_amount'      => $validated['paid_amount'],
                'taux'             => $validated['taux'] ?? 30.00,
                'reference'        => fake()->unique()->numerify('VERS-#####'),
                'account_id'       => $validated['account_id'],
                'agent_id'         => $validated['agent_id'] ?? null,
                'addedBy'          => Auth::user()->id
            ]);

            TrasactionTresorerie::create([
                'motif'            => 'Paiement de la facture du Bornier',
                'transaction_type' => 'RECETTE',
                'amount'           => $totalAmount,
                'account_id'       => $validated['account_id'],
                'transaction_date' => $validated['transaction_date'],
                'addedBy'          => Auth::user()->id,
                'reference'        => fake()->unique()->numerify('TRANS-#####') . '-' . $bornier->nom,
                'solde'            => $solde + $totalAmount
            ]);

            $data = Versement::join('borniers', '', '=', 'borniers.id')
                ->join('tresoreries', 'versements.account_id', '=', 'tresoreries.id')
                ->join('users as u1', 'versements.addedBy', '=', 'u1.id')
                ->select('versements.*', 'borniers.nom as bornier_nom', 'borniers.adresse as bornier_adresse', 'borniers.phone as bornier_phone', 'u1.name as addedBy')
                ->where('versements.id', $versement->id)
                ->first();

            return response()->json([
                'message' => 'Versement créé avec succès.',
                'status'  => 201,
                'success' => true,
                'data'    => $data,
                'company_info' => $about
            ], 201);
        });
    }

    /**
     * @OA\Put(
     *     path="/api/versements.update/{id}",
     *     tags={"Versements"},
     *     summary="Mettre à jour un versement existant",
     *     description="Met à jour un versement existant avec les informations fournies.",
     *     operationId="updateVersement",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du versement à mettre à jour",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"transaction_date", "amount", "paid_amount"},
     *             @OA\Property(property="transaction_date", type="string", format="date", example="2025-10-09"),
     *             @OA\Property(property="amount", type="number", format="float", example=1000.00),
     *             @OA\Property(property="paid_amount", type="number", format="float", example=700.00),
     *             @OA\Property(property="taux", type="number", format="float", example=30.00),
     *             @OA\Property(property="account_id", type="integer", example=1),
     *             @OA\Property(property="agent_id", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Versement mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Versement mis à jour avec succès."),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="data", ref="#/components/schemas/Versement")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Versement non trouvé"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation"
     *     )
     * )
     */
    public function updateVersement(Request $request, $id)
    {
        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'amount'           => ['required', 'numeric', 'min:0'],
            'paid_amount'      => ['required', 'numeric', 'min:0'],
            'taux'             => ['nullable', 'numeric', 'min:0', 'max:100'],
            'account_id'       => ['required', 'exists:tresoreries,id'],
            'agent_id'         => ['nullable', 'exists:borniers,id']
        ]);

        $versement = Versement::find($id);

        if (!$versement) {
            return response()->json([
                'message' => 'Versement non trouvé.',
                'status'  => 404,
                'success' => false
            ], 404);
        }

        return DB::transaction(function () use ($versement, $validated) {
            // Calcul du nouveau total
            $totalAmount = round($validated['amount'] - $validated['paid_amount'], 2);

            // Récupération du dernier solde du compte
            $lastTransaction = TrasactionTresorerie::where('account_id', $validated['account_id'])
                ->latest('id')
                ->first();
            $solde = $lastTransaction ? $lastTransaction->solde : 0;

            // Mise à jour du versement
            $versement->update([
                'transaction_date' => $validated['transaction_date'],
                'amount'           => $validated['amount'],
                'paid_amount'      => $validated['paid_amount'],
                'taux'             => $validated['taux'] ?? 30.00,
                'account_id'       => $validated['account_id'] ?? $versement->account_id,
                'agent_id'         => $validated['agent_id'] ?? $versement->agent_id,
                'updatedBy'        => Auth::user()->id
            ]);

            // Mise à jour de la transaction correspondante (si elle existe)
            $transaction = TrasactionTresorerie::where('reference', 'like', '%TRANS-%')
                ->where('account_id', $versement->account_id)
                ->where('transaction_type', 'RECETTE')
                ->whereDate('transaction_date', $versement->transaction_date)
                ->first();

            if ($transaction) {
                $transaction->update([
                    'amount'           => $totalAmount,
                    'transaction_date' => $validated['transaction_date'],
                    'solde'            => $solde + $totalAmount,
                    'updatedBy'        => Auth::user()->id
                ]);
            } else {
                // Si aucune transaction liée n'existe, on en crée une nouvelle
                TrasactionTresorerie::create([
                    'motif'            => 'Mise à jour du versement du Bornier',
                    'transaction_type' => 'RECETTE',
                    'amount'           => $totalAmount,
                    'account_id'       => $validated['account_id'],
                    'transaction_date' => $validated['transaction_date'],
                    'addedBy'          => Auth::user()->id,
                    'reference'        => fake()->unique()->numerify('TRANS-#####'),
                    'solde'            => $solde + $totalAmount
                ]);
            }

            return response()->json([
                'message' => 'Versement mis à jour avec succès.',
                'status'  => 200,
                'success' => true,
                'data'    => $versement
            ], 200);
        });
    }
}
