<?php

namespace App\Http\Controllers;

use App\Services\Panel\DatosPanel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CosechaController extends Controller
{
    public function __construct(private DatosPanel $datos) {}

    public function indice(Request $request): View
    {
        $pais = $request->query('pais');
        $pais = is_string($pais) ? strtoupper($pais) : null;

        return view('panel.cosecha', $this->datos->cosecha($pais));
    }
}
