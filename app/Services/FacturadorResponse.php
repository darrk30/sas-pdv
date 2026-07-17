<?php

namespace App\Services;

readonly class FacturadorResponse
{
    public function __construct(
        public bool    $ok,
        public ?string $hash          = null,
        public ?string $xmlBase64     = null,
        public ?string $ticket        = null,
        public ?string $totalLetras   = null,
        public ?string $qrData        = null,
        public bool    $sunatSuccess  = false,
        public ?int    $sunatCode     = null,
        public ?string $sunatDescription = null,
        public array   $sunatNotes    = [],
        public ?string $cdrZip        = null,
        public ?string $errorCode     = null,
        public ?string $errorMessage  = null,
        public ?string $httpError     = null,
    ) {}

    /**
     * Respuesta de POST /api/invoices/send y POST /api/notes/send
     * La clave de éxito está dentro de sunatResponse.success
     */
    public static function fromInvoice(array $json): self
    {
        $sunatResp = $json['sunatResponse'] ?? [];
        $cdr       = $sunatResp['cdrResponse'] ?? [];
        $sunatOk   = (bool) ($sunatResp['success'] ?? false);
        $error     = $sunatResp['error'] ?? null;

        return new self(
            ok:               $sunatOk,
            hash:             $json['hash'] ?? null,
            xmlBase64:        $json['xml'] ?? null,
            totalLetras:      $json['total_letras'] ?? null,
            qrData:           $json['qr_data'] ?? null,
            sunatSuccess:     $sunatOk,
            sunatCode:        isset($cdr['code']) ? (int) $cdr['code'] : null,
            sunatDescription: $cdr['description'] ?? null,
            sunatNotes:       $cdr['notes'] ?? [],
            cdrZip:           $sunatResp['cdrZip'] ?? null,
            errorCode:        isset($error['code']) ? (string) $error['code'] : null,
            errorMessage:     $error['message'] ?? null,
        );
    }

    /**
     * Respuesta de POST /api/summaries/send y POST /api/voids/send
     * La clave de éxito está en el nivel raíz; devuelve ticket para polling
     */
    public static function fromSummaryEnvio(array $json): self
    {
        $ok    = (bool) ($json['success'] ?? false);
        $error = $json['error'] ?? null;

        return new self(
            ok:           $ok,
            hash:         $json['hash'] ?? null,
            xmlBase64:    $json['xml'] ?? null,
            sunatSuccess: $ok,
            ticket:       $json['ticket'] ?? null,
            errorCode:    isset($error['code']) ? (string) $error['code'] : null,
            errorMessage: $error['message'] ?? null,
        );
    }

    /**
     * Respuesta de POST /api/summaries/status y POST /api/voids/status
     * Mismo formato que sunatResponse: devuelve CDR con resultado definitivo
     */
    public static function fromStatus(array $json): self
    {
        $ok    = (bool) ($json['success'] ?? false);
        $cdr   = $json['cdrResponse'] ?? [];
        $error = $json['error'] ?? null;

        return new self(
            ok:               $ok,
            sunatSuccess:     $ok,
            sunatCode:        isset($cdr['code']) ? (int) $cdr['code'] : null,
            sunatDescription: $cdr['description'] ?? null,
            sunatNotes:       $cdr['notes'] ?? [],
            cdrZip:           $json['cdrZip'] ?? null,
            errorCode:        isset($error['code']) ? (string) $error['code'] : null,
            errorMessage:     $error['message'] ?? null,
        );
    }

    /** Error de transporte HTTP o configuración incorrecta */
    public static function fromError(string $message): self
    {
        return new self(ok: false, httpError: $message);
    }

    /** Mensaje de error legible para mostrar al usuario */
    public function mensajeError(): string
    {
        if ($this->httpError) {
            return $this->httpError;
        }
        if ($this->errorMessage) {
            return "[{$this->errorCode}] {$this->errorMessage}";
        }
        if ($this->sunatDescription) {
            return "[{$this->sunatCode}] {$this->sunatDescription}";
        }
        return 'Error desconocido al comunicarse con SUNAT.';
    }
}
