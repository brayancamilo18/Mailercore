<?php

namespace App\Http\Controllers;

use App\Services\Panel\DatosPanel;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PanelController extends Controller
{
    public function __construct(private DatosPanel $datos) {}

    public function resumen(): View
    {
        return view('panel.resumen', [
            'embudo' => $this->datos->embudo(),
            'rampa' => $this->datos->rampaHoy(),
            'sectores' => $this->datos->tablaSectores(),
            'respuestas' => $this->datos->ultimasRespuestas(10),
        ]);
    }

    public function estadoJson(): JsonResponse
    {
        return response()->json($this->datos->estadoJson());
    }
}
