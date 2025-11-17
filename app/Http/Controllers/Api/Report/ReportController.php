<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Facturation;
use App\Models\PointEau;
use App\Models\PointEauAbonne;
use App\Models\Produit;
use App\Models\Rapport;
use App\Models\Ticket;
use App\Models\TrasactionTresorerie;
use App\Models\Versement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/rapport.borne",
     * summary="Liste des points d’eau",
     * tags={"Rapports"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function rapportBorne()
    {
        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));

        $about = About::first();

        // 🔧 Vérifier si le logo existe et générer base64
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

        $data = PointEau::where('status', 'Actif')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'success',
            'success' => true,
            'status' => 200,
            'data' => $data,
            'company_info' => $about,
        ]);
    }

    /**
     * @OA\Get(
     * path="/api/rapport.point-eau-abonne",
     * summary="Liste des points d’eau abonnes",
     * tags={"Rapports"},
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         description="Date de début au format YYYY-MM-DD (inclus). Par défaut : début du mois courant.",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         description="Date de fin au format YYYY-MM-DD (inclus). Par défaut : date du jour.",
     *         @OA\Schema(type="string", format="date", example="2025-10-25")
     *     ),
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function rapportPointEauAbonne()
    {
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

        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));
        $data = PointEauAbonne::join('abonnes', 'point_eau_abonnes.abonne_id', '=', 'abonnes.id')
            ->join('users', 'point_eau_abonnes.addedBy', '=', 'users.id')
            ->join('point_eaus', 'point_eau_abonnes.point_eau_id', '=', 'point_eaus.id')
            ->select('point_eau_abonnes.*', 'point_eaus.numero_compteur', 'point_eaus.matricule', 'abonnes.nom as abonne', 'users.name as addedBy')
            ->whereBetween('date_operation', [$date_start, $date_end])->get();

        return response()->json([
            'message' => 'success',
            'success' => true,
            'status' => 200,
            'data' => $data,
            'company_info' => $about
        ]);
    }

    /**
     * @OA\Get(
     * path="/api/rapport.facturations",
     * summary="Liste des facturations",
     * tags={"Rapports"},
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         description="Date de début au format YYYY-MM-DD (inclus). Par défaut : début du mois courant.",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         description="Date de fin au format YYYY-MM-DD (inclus). Par défaut : date du jour.",
     *         @OA\Schema(type="string", format="date", example="2025-10-25")
     *     ),
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */

    public function rapportFacturations()
    {
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


        // $date_start = request('date_start', date('Y-m-01'));
        // $date_end = request('date_end', date('Y-m-d'));

        $date_start = request('date_start');
        $date_end   = request('date_end');

        $status     = request('status');

        $query = Facturation::with('pointEauAbonne.abonne', 'user')
            ->orderByRaw("
            CASE 
                WHEN status = 'impayé'  THEN 1
                WHEN status = 'acompte' THEN 2
                WHEN status = 'insoldée' THEN 3
                WHEN status = 'payé'    THEN 4
                ELSE 5
            END
        ")
            ->orderBy('created_at', 'desc');
        if (!empty($date_start) && !empty($date_end)) {
            $query->whereBetween('date_emission', [$date_start, $date_end]);
        }

        // Si un status est envoyé, on filtre
        if (!empty($status)) {
            $query->where('status', $status);
        }

        $data = $query->get();

        $result = [
            'message' => "OK",
            'success' => true,
            'status'  => 200,
            'data'    => $data,
            'company_info' => $about
        ];

        return response()->json($result);
    }

    /**
     * @OA\Get(
     * path="/api/rapport.versements",
     * summary="Liste des versements",
     * tags={"Rapports"},
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         description="Date de début au format YYYY-MM-DD (inclus). Par défaut : début du mois courant.",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         description="Date de fin au format YYYY-MM-DD (inclus). Par défaut : date du jour.",
     *         @OA\Schema(type="string", format="date", example="2025-10-25")
     *     ),
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */

    public function versements()
    {
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

        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));
        $data = Versement::join('tresoreries', 'versements.account_id', '=', 'tresoreries.id')
            ->join('users as u1', 'versements.addedBy', '=', 'u1.id')
            ->join('users as u2', 'versements.agent_id', '=', 'u2.id')
            ->select('versements.*', 'u2.name as agent', 'u1.name as addedBy', 'tresoreries.designation as tresorerie')
            ->latest()
            ->whereBetween('transaction_date', [$date_start, $date_end])->get();
        $result = [
            'message' => "OK",
            'success' => true,
            'status'  => 200,
            'data'    => $data,
            'company_info' => $about
        ];

        return response()->json($result);
    }

    /**
     * @OA\Get(
     * path="/api/rapport.tickets",
     * summary="Liste des tickets",
     * tags={"Rapports"},
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         description="Date de début au format YYYY-MM-DD (inclus). Par défaut : début du mois courant.",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         description="Date de fin au format YYYY-MM-DD (inclus). Par défaut : date du jour.",
     *         @OA\Schema(type="string", format="date", example="2025-10-25")
     *     ),
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function rapportTickets()
    {
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

        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));
        $data = Ticket::join('point_eaus', 'tickets.point_id', '=', 'point_eaus.id')
            ->join('users as u1', 'tickets.addedBy', '=', 'u1.id')
            ->join('users as u2', 'tickets.technicien_id', '=', 'u2.id')
            ->select(
                'tickets.*',
                'tickets.statut as status',
                'point_eaus.matricule as point_eau',
                'point_eaus.numero_compteur',
                'point_eaus.lat',
                'point_eaus.long',
                'u1.name as addedBy',
                'u2.name as technicien'
            )->whereBetween('date_ouverture', [$date_start, $date_end])->get();
        $result = [
            'message' => "OK",
            'success' => true,
            'status'  => 200,
            'data'    => $data,
            'company_info' => $about
        ];

        return response()->json($result);
    }

    /**
     * @OA\Get(
     * path="/api/rapport.trasactionsReport",
     * summary="Liste des trasactionsReport",
     * tags={"Rapports"},
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         description="Date de début au format YYYY-MM-DD (inclus). Par défaut : début du mois courant.",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         description="Date de fin au format YYYY-MM-DD (inclus). Par défaut : date du jour.",
     *         @OA\Schema(type="string", format="date", example="2025-10-25")
     *     ),
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function trasactionsReport()
    {
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

        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));

        $data = TrasactionTresorerie::join('users', 'trasaction_tresoreries.addedBy', '=', 'users.id')
            ->join('tresoreries', 'trasaction_tresoreries.account_id', '=', 'tresoreries.id')
            ->select('trasaction_tresoreries.*', 'users.name as addedBy', 'tresoreries.designation as account_name')
            ->where('trasaction_tresoreries.status', true)
            ->whereBetween('transaction_date', [$date_start, $date_end])->get();

        $result = [
            'message' => "OK",
            'success' => true,
            'status'  => 200,
            'data'    => $data,
            'company_info' => $about
        ];

        return response()->json($result);
    }

    /**
     * @OA\Get(
     * path="/api/rapport.depenseReport",
     * summary="Liste des depenseReport",
     * tags={"Rapports"},
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         description="Date de début au format YYYY-MM-DD (inclus). Par défaut : début du mois courant.",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         description="Date de fin au format YYYY-MM-DD (inclus). Par défaut : date du jour.",
     *         @OA\Schema(type="string", format="date", example="2025-10-25")
     *     ),
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function depenseReport()
    {
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
        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));

        $data = Rapport::with(['details', 'ticket', 'user', 'ticket.point'])
            ->whereBetween('date', [$date_start, $date_end])->get();
        $result = [
            'message' => "OK",
            'success' => true,
            'status'  => 200,
            'data'    => $data,
            'company_info' => $about
        ];

        return response()->json($result);
    }

    /**
     * @OA\Post(
     * path="/api/rapport.fiche-stock",
     * summary="Liste des trasactionsReport",
     * tags={"Rapports"},
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         description="Date de début au format YYYY-MM-DD",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         description="Date de fin au format YYYY-MM-DD",
     *         @OA\Schema(type="string", format="date", example="2025-10-25")
     *     ),
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    // public function stockReportData(Request $request)
    // {
    //     // 🔹 Validation minimale
    //     $request->validate([
    //         'date_start' => ['required', 'date'],
    //         'date_end'   => ['required', 'date'],
    //         'q'          => ['nullable', 'string'],
    //     ]);

    //     $date_start = $request->input('date_start');
    //     $date_end   = $request->input('date_end');
    //     $searchTerm = '%' . $request->query('q', '') . '%';

    //     $about = About::first();

    //     if ($about && $about->logo) {
    //         $path = storage_path('app/public/' . $about->logo);

    //         if (file_exists($path)) {
    //             $mime = mime_content_type($path);
    //             $data = base64_encode(file_get_contents($path));
    //             $about->logo = "data:$mime;base64,$data";
    //         } else {
    //             // Si fichier manquant, on peut utiliser une image par défaut
    //             $about->logo = asset('images/default-logo.png');
    //         }
    //     }

    //     // --- Sous-requêtes ---
    //     $stockBefore = DB::table('logistiques')
    //         ->select(
    //             'product_id',
    //             DB::raw('COUNT(*) as tx_count'),
    //             DB::raw("
    //             SUM(
    //                 CASE
    //                     WHEN type_transaction IN ('Entree','initial') THEN new_quantity
    //                     WHEN type_transaction = 'Sortie' THEN -new_quantity
    //                     ELSE 0
    //                 END
    //             ) AS stock_before_start
    //         ")
    //         )
    //         ->where('date_transaction', '<', $date_start)
    //         ->groupBy('product_id');

    //     $lastInit = DB::table('logistiques')
    //         ->select('product_id', DB::raw('MAX(date_transaction) as max_date'))
    //         ->where('type_transaction', 'initial')
    //         ->groupBy('product_id');

    //     $fallbackInit = DB::table('logistiques as lg')
    //         ->select('lg.product_id', 'lg.new_quantity as fallback_quantity')
    //         ->joinSub($lastInit, 'latest_init', function ($join) {
    //             $join->on('lg.product_id', '=', 'latest_init.product_id')
    //                 ->on('lg.date_transaction', '=', 'latest_init.max_date');
    //         })
    //         ->where('lg.type_transaction', 'initial');

    //     $achatsSummary = DB::table('entrees')
    //         ->select('product_id', DB::raw('SUM(quantite) as total_entry'))
    //         ->whereDate('date_transaction', '>=', $date_start)
    //         ->whereDate('date_transaction', '<=', $date_end)
    //         ->groupBy('product_id');
    //     $ventesSummary = DB::table('logistiques')
    //         ->select('product_id', DB::raw('SUM(quantite) as total_exit'))
    //         ->where('type_transaction', 'Sortie')
    //         ->whereDate('date_transaction', '>=', $date_start)
    //         ->whereDate('date_transaction', '<=', $date_end)
    //         ->groupBy('product_id');


    //     // --- Requête principale ---
    //     $rows = DB::table('produits as p')
    //         ->select(
    //             'p.id as product_id',
    //             'p.designation as product_name',
    //             DB::raw("
    //             COALESCE(
    //                 CASE WHEN sb.tx_count > 0 THEN sb.stock_before_start ELSE NULL END,
    //                 fi.fallback_quantity,
    //                 p.quantite
    //             ) AS previous_quantity
    //         "),
    //             DB::raw("COALESCE(a.total_entry, 0) AS total_entry"),
    //             DB::raw("COALESCE(v.total_exit, 0) AS total_exit"),
    //             DB::raw("
    //             (
    //                 COALESCE(
    //                     CASE WHEN sb.tx_count > 0 THEN sb.stock_before_start ELSE NULL END,
    //                     fi.fallback_quantity,
    //                     p.quantite
    //                 )
    //                 + COALESCE(a.total_entry, 0)
    //                 - COALESCE(v.total_exit, 0)
    //             ) AS stock_remaining
    //         ")
    //         )
    //         ->leftJoinSub($stockBefore, 'sb', fn($j) => $j->on('sb.product_id', '=', 'p.id'))
    //         ->leftJoinSub($fallbackInit, 'fi', fn($j) => $j->on('fi.product_id', '=', 'p.id'))
    //         ->leftJoinSub($achatsSummary, 'a', fn($j) => $j->on('a.product_id', '=', 'p.id'))
    //         ->leftJoinSub($ventesSummary, 'v', fn($j) => $j->on('v.product_id', '=', 'p.id'))
    //         ->where('p.designation', 'like', $searchTerm)
    //         ->whereRaw("
    //         (
    //             COALESCE(
    //                 CASE WHEN sb.tx_count > 0 THEN sb.stock_before_start ELSE NULL END,
    //                 fi.fallback_quantity,
    //                 p.quantite
    //             ) <> 0
    //             OR COALESCE(a.total_entry, 0) <> 0
    //             OR COALESCE(v.total_exit, 0) <> 0
    //         )
    //     ")
    //         ->orderBy('p.designation', 'asc')
    //         ->get();

    //     // 🔹 Construction format JSON final
    //     $data = $rows->map(function ($row) use ($date_end) {

    //         $summary = [
    //             'previous_quantity' => (float) $row->previous_quantity,
    //             'entry'             => (float) $row->total_entry,
    //             'exit'              => (float) $row->total_exit,
    //             'stock_remaining'   => (float) $row->stock_remaining,
    //             'date_transaction'  => $date_end,
    //         ];

    //         return [
    //             'product_id'   => $row->product_id,
    //             'product_name' => $row->product_name,
    //             'transactions' => [$summary],
    //         ];
    //     })->toArray();

    //     return response()->json([
    //         'success' => true,
    //         'data'    => $data,
    //         'company_info' => $about,
    //         'message' => 'Fiche de stock générée avec succès',
    //     ]);
    // }

    public function stockReportData(Request $request)
    {
        $date_start = $request->date_start;
        $date_end   = $request->date_end;
        $searchTerm = '%' . ($request->q ?? '') . '%';

        $query = "
        WITH transactions_before AS (
            SELECT
                product_id,
                COUNT(*) AS tx_count,
                SUM(
                    CASE
                        WHEN type_transaction IN ('Entrée', 'initial') THEN new_quantity
                        WHEN type_transaction = 'Sortie' THEN -new_quantity
                        ELSE 0
                    END
                ) AS stock_before_start
            FROM logistiques
            WHERE date_transaction < ?
            AND deleted = false
            GROUP BY product_id
        ),
        fallback_initial AS (
            SELECT pl.product_id, pl.new_quantity AS fallback_quantity
            FROM logistiques pl
            INNER JOIN (
                SELECT product_id, MAX(date_transaction) AS max_date
                FROM logistiques
                WHERE type_transaction = 'initial' AND deleted = false
                GROUP BY product_id
            ) latest_init ON latest_init.product_id = pl.product_id AND latest_init.max_date = pl.date_transaction
            WHERE pl.type_transaction = 'initial' AND pl.deleted = false
        ),
        achats_summary AS (
            SELECT 
                ad.product_id,
                SUM(ad.quantite) AS total_entry
            FROM entrees ad
            WHERE ad.deleted = false
            AND ad.date_transaction BETWEEN ? AND ?
            GROUP BY ad.product_id
        ),
        ventes_summary AS (
            SELECT 
                vd.product_id,
                SUM(vd.quantite) AS total_exit
            FROM sorties vd
            WHERE vd.deleted = false
            AND vd.date_transaction BETWEEN ? AND ?
            GROUP BY vd.product_id
        )
        SELECT
            p.id AS product_id,
            p.designation AS product_name,
            COALESCE(
                CASE 
                    WHEN tb.tx_count > 0 THEN tb.stock_before_start
                    ELSE NULL
                END,
                fi.fallback_quantity,
                p.quantity
            ) AS previous_quantity,

            COALESCE(a.total_entry, 0) AS entry,
            COALESCE(v.total_exit, 0) AS exit,

            COALESCE(
                CASE 
                    WHEN tb.tx_count > 0 THEN tb.stock_before_start
                    ELSE NULL
                END,
                fi.fallback_quantity,
                p.quantity
            ) + COALESCE(a.total_entry, 0) - COALESCE(v.total_exit, 0) AS stock_remaining

        FROM produits p
        LEFT JOIN transactions_before tb ON tb.product_id = p.id
        LEFT JOIN fallback_initial fi ON fi.product_id = p.id
        LEFT JOIN achats_summary a ON a.product_id = p.id
        LEFT JOIN ventes_summary v ON v.product_id = p.id

        WHERE (
            COALESCE(
                CASE WHEN tb.tx_count > 0 THEN tb.stock_before_start ELSE NULL END,
                fi.fallback_quantity,
                p.quantite
            ) <> 0
            OR COALESCE(a.total_entry, 0) <> 0
            OR COALESCE(v.total_exit, 0) <> 0
        )
        AND p.designation LIKE ?
        ORDER BY p.designation ASC;
    ";

        $rows = DB::select($query, [
            $date_start,
            $date_start,
            $date_end,
            $date_start,
            $date_end,
            $searchTerm
        ]);

        // Mapper comme ton code Node
        $result = collect($rows)->map(fn($row) => [
            'product_id' => $row->product_id,
            'product_name' => $row->product_name,
            'transactions' => [[
                'type' => "summary",
                'reference' => "Résumé global",
                'previous_quantity' => $row->previous_quantity,
                'entry' => $row->entry,
                'exit' => $row->exit,
                'stock_remaining' => $row->stock_remaining,
                'date_transaction' => $date_start
            ]]
        ]);

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }




    /**
     * @OA\Get(
     * path="/api/rapport.getProductAllData",
     * summary="Liste des produits",
     * tags={"Rapports"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function getRapportProduitData()
    {
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

        $data = Produit::latest()->get();
        $result = [
            'message' => "OK",
            'success' => true,
            'data' => $data,
            'company_info' => $about,
            'status' => 200
        ];
        return response()->json($result);
    }
}
