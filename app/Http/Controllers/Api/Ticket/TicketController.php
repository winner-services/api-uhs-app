<?php

namespace App\Http\Controllers\Api\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non authentifié',
                'success' => false,
                'status' => 401
            ], 401);
        }

        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');

        $query = Ticket::join('point_eaus', 'tickets.point_id', '=', 'point_eaus.id')
            ->join('users as u1', 'tickets.addedBy', '=', 'u1.id')
            ->join('users as u2', 'tickets.technicien_id', '=', 'u2.id')
            ->select(
                'tickets.*',
                'point_eaus.matricule as point_eau',
                'point_eaus.numero_compteur',
                'point_eaus.lat',
                'point_eaus.long',
                'u1.name as addedBy',
                'u2.name as technicien'
            );
            
        // --- Filtrage selon le rôle ---
        if ($user->hasRole('technicien')) {
            $query->where('tickets.technicien_id', '=', $user->id);
        } elseif ($user->hasRole('admin')) {
            dd($user->id);
            // Admin → voit tous les tickets, pas de filtre
        } else {
            // Autres rôles → ne voit aucun ticket
            $query->whereRaw('0 = 1');
        }

        // --- Recherche ---
        if (!empty(trim($q))) {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('point_eaus.matricule', 'like', "%$q%")
                    ->orWhere('tickets.titre', 'like', "%$q%")
                    ->orWhere('u2.name', 'like', "%$q%");
            });
        }

        $data = $query->orderBy($sort_field, $sort_direction)
            ->paginate($page);

        return response()->json([
            'message' => "OK",
            'success' => true,
            'data' => $data,
            'status' => 200,
        ]);
    }

    // public function index()
    // {
    //     $page = request("paginate", 10);
    //     $q = request("q", "");
    //     $sort_direction = request('sort_direction', 'desc');
    //     $sort_field = request('sort_field', 'id');
    //     $data = Ticket::join('point_eaus', 'tickets.point_id', '=', 'point_eaus.id')
    //         ->join('users as u1', 'tickets.addedBy', '=', 'u1.id')
    //         ->join('users as u2', 'tickets.technicien_id', '=', 'u2.id')
    //         ->select(
    //             'tickets.*',
    //             'point_eaus.matricule as point_eau',
    //             'point_eaus.numero_compteur',
    //             'point_eaus.lat',
    //             'point_eaus.long',
    //             'u1.name as addedBy',
    //             'u2.name as technicien'
    //         )
    //         ->latest()
    //         // ->searh(trim($q))
    //         ->orderBy($sort_field, $sort_direction)
    //         ->paginate($page);
    //     $result = [
    //         'message' => "OK",
    //         'success' => true,
    //         'data' => $data,
    //         'status' => 200
    //     ];
    //     return response()->json($result);
    // }

    /**
     * @OA\Get(
     *     path="/api/tickets.getOptionsData",
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
    public function getTicketOptionsData()
    {
        $data = Ticket::with('point', 'user')
            ->latest()->get();
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
     *     path="/api/tickets.store",
     *     summary="Créer un ticket",
     *     description="Ajout d’un nouveau ticket de maintenance",
     *     tags={"Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"point_id","description","status","priorite","technicien_id","date_ouverture"},
     *             @OA\Property(property="point_id", type="integer", example=1),
     *             @OA\Property(property="description", type="string", example="Fuite détectée dans le compteur."),
     *             @OA\Property(property="status", type="string", example="Ouvert"),
     *             @OA\Property(property="priorite", type="string", example="Haute"),
     *             @OA\Property(property="technicien_id", type="integer", example=1),
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
            'description'   => ['nullable', 'string'],
            'status'        => ['nullable', 'string'],
            'priorite'      => ['nullable', 'string'],
            'technicien_id'    => ['required'],
            'date_ouverture' => ['nullable', 'date'],
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
            $user = Auth::user();
            $ticket = Ticket::create([
                'point_id' => $request->point_id,
                'description' => $request->description,
                'statut' => $request->status,
                'priorite' => $request->priorite,
                'technicien_id' => $request->technicien_id,
                'date_ouverture' => $request->date_ouverture,
                'date_cloture' => $request->date_cloture,
                'addedBy' => $user->id,
                'reference' => fake()->unique()->numerify('PAN-#####')
            ]);

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
     *             @OA\Property(property="status", type="string", example="Clôturé"),
     *             @OA\Property(property="priorite", type="string", example="Moyenne"),
     *             @OA\Property(property="technicien_id", type="integer", example=1),
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
        $user = Auth::user();
        $rules = [
            'description'   => ['sometimes', 'string'],
            'status'        => ['sometimes', 'string'],
            'priorite'      => ['sometimes', 'string'],
            'technicien_id'    => ['sometimes'],
            'date_ouverture' => ['sometimes', 'date'],
            'date_cloture'  => ['nullable', 'date'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $ticket->update([
            'point_id' => $request->point_id,
            'description' => $request->description,
            'statut' => $request->status,
            'priorite' => $request->priorite,
            'technicien' => $request->technicien_id,
            'date_ouverture' => $request->date_ouverture,
            'date_cloture' => $request->date_cloture,
            'addedBy' => $user->id ?? $ticket->addedBy
        ]);

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
