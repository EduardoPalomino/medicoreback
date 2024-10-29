<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

class FuasSentController extends Controller
{
    public function index(Request $request) {
        $fechaIni = $request->fechaIni;
        $fechaFin = $request->fechaFin;
        $datos = DB::select('exec [REPORTES].[SP_ENVIOS_PLATAFORMA] ?, ?', array($fechaIni, $fechaFin));
        return $datos;
    }
}
