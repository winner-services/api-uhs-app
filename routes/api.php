<?php

use App\Http\Controllers\Api\Abonnement\AbonnementCategoryController;
use App\Http\Controllers\Api\Abonnement\AbonnementController;
use App\Http\Controllers\Api\About\AboutController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\DashBoard\DashBoardController;
use App\Http\Controllers\Api\Facturation\FacturationController;
use App\Http\Controllers\Api\Intervention\RapportInterventionController;
use App\Http\Controllers\Api\Payement\AutrePayementController;
use App\Http\Controllers\Api\Payement\PayementController;
use App\Http\Controllers\Api\Permission\PermissionController;
use App\Http\Controllers\Api\PointEau\PointEauAbonne\PointEauAbonneController;
use App\Http\Controllers\Api\PointEau\PointEauController;
use App\Http\Controllers\Api\Rapport\RapportController;
use App\Http\Controllers\Api\Role\RoleController;
use App\Http\Controllers\Api\Ticket\TicketController;
use App\Http\Controllers\Api\Transaction\TransactionTresorerieController;
use App\Http\Controllers\Api\Tresorerie\TresorerieController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/check-auth', function (Request $request) {
    return response()->json(['authenticated' => true]);
});


Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {

    Route::controller(UserController::class)->group(function () {
        Route::get('/users.getData', 'index');
        Route::get('/user.Options', 'getAllUsersOptions');
        Route::post('/user.store', 'store');
        Route::put('/user.update/{id}', 'update');
        Route::delete('/user.delete/{id}', 'destroy');
        Route::put('/user.activate/{id}', 'activateUser');
        Route::put('/user.disable/{id}', 'disableUser');
    });

    Route::controller(RoleController::class)->group(function () {
        Route::post('/role.store', 'storeRole');
        Route::put('/role.update/{id}', 'updateRole');
        Route::get('/role.Options', 'getRole');
        Route::get('/permissionDataByRole/{id}', 'getPermissionDataByRole');
    });
    Route::get('/permission.index', [PermissionController::class, 'getPemissionData']);

    Route::controller(AboutController::class)->group(function () {
        Route::get('/about.index', 'getData');
        Route::post('/about.store', 'store');
        Route::post('/about.update/{id}', 'update');
    });

    Route::controller(AbonnementCategoryController::class)->group(function () {
        Route::get('/category_abonne.getOptionsData', 'getallData');
        Route::get('/category_abonne.getAllData', 'index');
        Route::post('/category_abonne.store', 'store');
        Route::put('/category_abonne.update/{id}', 'update');
        Route::delete('/category_abonne.delete/{id}', 'destroy');
    });

    Route::controller(AbonnementController::class)->group(function () {
        Route::get('/abonnes.getAllData', 'index');
        Route::get('/abonnes.getOptionsData', 'getaAbonnellData');
        Route::post('/abonnes.store', 'store');
        Route::put('/abonnes.update/{id}', 'update');
        Route::delete('/abonnes.delete/{id}', 'destroy');
    });

    Route::controller(FacturationController::class)->group(function () {
        Route::get('/facturations.getAllData', 'index');
        Route::post('/facturations.store', 'genererFacturesMensuelles');
        Route::delete('/facturations.delete/{id}', 'destroy');
    });

    Route::controller(PointEauController::class)->group(function () {
        Route::get('/point-eaux.getAllData', 'indexPoint');
        Route::get('/point-eaux.getOptionsData', 'getOptionsPointData');
        Route::post('/point-eaux.store', 'store');
        Route::put('/point-eaux.update/{id}', 'update');
        Route::delete('/point-eaux.delete/{id}', 'destroy');
    });

    Route::controller(PointEauAbonneController::class)->group(function () {
        Route::get('/point-eau-abonne.getAllData', 'indexPointAbonne');
        Route::post('/point-eau-abonnes.store', 'store');
        Route::put('/point-eau-abonnes/{id}', 'update');
        Route::delete('/point-eau-abonnes/{id}', 'destroy');
    });
    Route::controller(TicketController::class)->group(function () {
        Route::get('/tickets.getAllData', 'index');
        Route::get('/tickets.getOptionsData', 'getTicketOptionsData');
        Route::post('/tickets.store', 'store');
        Route::put('/tickets.update/{id}', 'update');
        Route::delete('/tickets.delete/{id}', 'destroy');
    });
    Route::controller(RapportInterventionController::class)->group(function () {
        Route::get('/rapport-interventions.getAllData', 'getAllRapportData');
        Route::post('/rapport-interventions.store', 'storeRapport');
        Route::put('/rapport-interventions.update/{id}', 'updateRapport');
        Route::delete('/rapport-interventions.delete/{id}', 'destroyRapport');
    });

    Route::controller(TresorerieController::class)->group(function () {
        Route::get('/tresoreries.getAllData', 'indexTresorerie');
        Route::get('/Tresoreries.getOptionsData', 'getOptionsTresorerie');
        Route::post('/tresoreries.store', 'store');
        Route::put('/tresoreries.update/{id}', 'update');
        Route::delete('/tresoreries.delete/{id}', 'destroy');
    });

    Route::controller(TransactionTresorerieController::class)->group(function () {
        Route::get('/transaction-tresoreries.getAllData', 'getTransactionData');
        Route::post('/transaction-tresoreries.store', 'store');
        Route::get('/transaction-tresoreries.update/{id}', 'update');
        Route::get('/transaction-tresoreries.delete/{id}', 'destroy');
    });

    Route::controller(PayementController::class)->group(function () {
        Route::get('/payements.getAllData', 'getPayement');
        Route::get('/payementsWeb.getAllData', 'getPayementWeb');
        Route::post('/payements.store', 'store');
    });
    Route::controller(RapportController::class)->group(function () {
        Route::post('/depenses.store', 'storeDepense');
        Route::put('/depenses.update/{id}', 'updateDepense');
        Route::delete('/depenses.delete/{id}', 'deleteDepense');
        Route::get('/depenses.index', 'indexDepense');
    });

    Route::controller(DashBoardController::class)->group(function () {
        Route::get('/dashboard.mobile', 'indexMobile');
    });

    Route::controller(AutrePayementController::class)->group(function () {
        Route::get('/versements.getAllData', 'getVersement');
    });
});
