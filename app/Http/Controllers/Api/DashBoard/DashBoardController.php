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
        return response()->json([
            'success' => true,
            'status' => 200,
            'total_factures' => $total_factures
        ]);
    }
}
