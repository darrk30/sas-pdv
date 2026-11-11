<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Services\AjusteExcelTemplateService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AjusteExcelController extends Controller
{
    public function __construct(private AjusteExcelTemplateService $plantillas) {}

    public function descargar(): StreamedResponse
    {
        return $this->plantillas->descargar();
    }
}
