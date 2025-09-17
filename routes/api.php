<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Permission\PermissionController;
use App\Http\Controllers\Api\Role\RoleController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

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

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
