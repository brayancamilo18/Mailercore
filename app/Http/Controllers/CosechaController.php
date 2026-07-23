<?php

namespace App\Http\Controllers;

use App\Services\Panel\DatosPanel;
use Illuminate\View\View;

class CosechaController extends Controller
{
    public function __construct(private DatosPanel $datos) {}

    public function indice(): View
    {
        return view('panel.cosecha', $this->datos->cosecha());
    }
}
