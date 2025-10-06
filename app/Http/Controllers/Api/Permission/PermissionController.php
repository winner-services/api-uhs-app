<?php

namespace App\Http\Controllers\Api\Permission;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/permission.index",
     *      operationId="getPemissionData",
     *      tags={"Permission"},
     *      summary="Get list",
     *      description="Returns list of",
     *      @OA\Response(
     *          response=200,
     *          description="Successful",
     * *          @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *       ),
     *     )
     */
    public function getPemissionData()
    {
        $data = Permission::all();
        return response()->json([
            'message' => 'succes',
            'status' => 200,
            'data' => $data
        ]);
    }
}
