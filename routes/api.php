<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FuaController;
use App\Http\Controllers\FuasSentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::get('/fuas', [FuaController::class, 'index']);
Route::get('/fuas-enviados', [FuasSentController::class, 'index']);
Route::get('/fuas/rpt-pdf', [FuaController::class, 'reporte_pdf']);
Route::get('/fuas/rpt-excel', [FuaController::class, 'reporte_excel']);
