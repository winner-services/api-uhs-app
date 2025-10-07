<?php

namespace App\Http\Controllers\Api\DashBoard;

use App\Http\Controllers\Controller;
use App\Models\Facturation;
use Illuminate\Http\Request;

class DashBoardController extends Controller
{
    public function indexMobile()
    {
        $total_factures = Facturation::count();
        $total_factures_paye = Facturation::where('')->get();
        $total_factures_acompte = Facturation::where('')->get();
        return response()->json([
            'success' => true,
            'status' => 200,
            'total_factures' => $total_factures
        ]);
    }
}
