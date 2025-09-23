<?php

namespace App\Http\Controllers\Api\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tickets.getAllData",
     *     summary="Liste des tickets",
     *     description="Récupérer tous les tickets liés aux points d’eau",
     *     tags={"Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des tickets",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Ticket"))
     *         )
     *     )
     * )
     */
    public function index()
    {
        $tickets = Ticket::with('point')->get();

        return response()->json([
            'success' => true,
            'data'    => $tickets
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/tickets.store",
     *     summary="Créer un ticket",
     *     description="Ajout d’un nouveau ticket de maintenance",
     *     tags={"Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"point_id","description","statut","priorite","technicien","date_ouverture","addedBy"},
     *             @OA\Property(property="point_id", type="integer", example=1),
     *             @OA\Property(property="addedBy", type="integer", example=1),
     *             @OA\Property(property="description", type="string", example="Fuite détectée dans le compteur."),
     *             @OA\Property(property="statut", type="string", example="Ouvert"),
     *             @OA\Property(property="priorite", type="string", example="Haute"),
     *             @OA\Property(property="technicien", type="string", example="Jean Mukendi"),
     *             @OA\Property(property="date_ouverture", type="string", format="date", example="2025-09-21"),
     *             @OA\Property(property="date_cloture", type="string", format="date", example="2025-09-25")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Ticket créé avec succès"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function store(Request $request)
    {
        $rules = [
            'point_id'      => ['required', 'integer', 'exists:point_eaus,id'],
            'description'   => ['required', 'string'],
            'statut'        => ['required', 'string'],
            'priorite'      => ['required', 'string'],
            'technicien'    => ['required', 'string'],
            'date_ouverture'=> ['required', 'date'],
            'date_cloture'  => ['nullable', 'date'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $ticket = Ticket::create($request->all());

            DB::commit();

            return response()->json([
                'message' => "Ticket créé avec succès",
                'success' => true,
                'data'    => $ticket
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => "Erreur lors de la création",
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/tickets.update/{id}",
     *     summary="Modifier un ticket",
     *     description="Mise à jour d’un ticket existant",
     *     tags={"Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID du ticket", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="description", type="string", example="Réparation effectuée."),
     *             @OA\Property(property="statut", type="string", example="Clôturé"),
     *             @OA\Property(property="priorite", type="string", example="Moyenne"),
     *             @OA\Property(property="technicien", type="string", example="Pierre Kaseba"),
     *             @OA\Property(property="date_ouverture", type="string", format="date", example="2025-09-21"),
     *             @OA\Property(property="date_cloture", type="string", format="date", example="2025-09-23")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Ticket mis à jour avec succès"),
     *     @OA\Response(response=404, description="Ticket non trouvé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function update(Request $request, $id)
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json(['message' => "Ticket non trouvé"], 404);
        }

        $rules = [
            'description'   => ['sometimes', 'string'],
            'statut'        => ['sometimes', 'string'],
            'priorite'      => ['sometimes', 'string'],
            'technicien'    => ['sometimes', 'string'],
            'date_ouverture'=> ['sometimes', 'date'],
            'date_cloture'  => ['nullable', 'date'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $ticket->update($request->all());

        return response()->json([
            'message' => "Ticket mis à jour avec succès",
            'success' => true,
            'data'    => $ticket
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/tickets.delete/{id}",
     *     summary="Supprimer un ticket",
     *     description="Suppression d’un ticket de maintenance",
     *     tags={"Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID du ticket", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Ticket supprimé avec succès"),
     *     @OA\Response(response=404, description="Ticket non trouvé")
     * )
     */
    public function destroy($id)
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json(['message' => "Ticket non trouvé"], 404);
        }

        $ticket->delete();

        return response()->json([
            'message' => "Ticket supprimé avec succès",
            'success' => true
        ], 200);
    }
}
