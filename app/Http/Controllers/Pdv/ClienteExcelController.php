<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Services\ClienteExcelTemplateService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClienteExcelController extends Controller
{
    public function __construct(private ClienteExcelTemplateService $plantillas) {}

    public function descargar(): StreamedResponse
    {
        return $this->plantillas->descargar();
    }
}
