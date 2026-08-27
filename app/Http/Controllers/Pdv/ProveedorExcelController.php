<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Services\ProveedorExcelTemplateService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProveedorExcelController extends Controller
{
    public function __construct(private ProveedorExcelTemplateService $plantillas) {}

    public function descargar(): StreamedResponse
    {
        return $this->plantillas->descargar();
    }
}
