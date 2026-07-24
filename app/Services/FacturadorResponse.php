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
        // true cuando SUNAT devuelve código 98: ticket aún en proceso (no es rechazo)
        public bool    $pending       = false,
    ) {}

    /**
     * Respuesta de POST /api/invoices/send y POST /api/notes/send.
     *
     * Cuando enviarSunat=false el facturador devuelve solo los campos raíz
     * (hash, xml, qr_data, total_letras) sin sunatResponse — en ese caso
     * ok=true indica que el XML fue generado correctamente aunque no se
     * envió a SUNAT todavía.
     */
    public static function fromInvoice(array $json): self
    {
        $sunatRaw  = $json['sunatResponse'] ?? null;

        // sunatResponse ausente → el facturador solo generó el XML (enviarSunat=false)
        if ($sunatRaw === null) {
            $generadoOk = isset($json['xml']) || isset($json['hash']);
            $errMsg     = $generadoOk ? null : ($json['message'] ?? 'El facturador no devolvió XML.');

            return new self(
                ok:           $generadoOk,
                hash:         $json['hash'] ?? null,
                xmlBase64:    $json['xml'] ?? null,
                totalLetras:  $json['total_letras'] ?? null,
                qrData:       $json['qr_data'] ?? null,
                sunatSuccess: false,
                errorMessage: $errMsg,
            );
        }

        // sunatResponse presente → se intentó enviar a SUNAT
        $sunatResp = is_array($sunatRaw) ? $sunatRaw : [];
        $cdr       = $sunatResp['cdrResponse'] ?? [];
        $sunatOk   = (bool) ($sunatResp['success'] ?? false);
        $error     = $sunatResp['error'] ?? null;

        $errorCode    = isset($error['code']) ? (string) $error['code'] : null;
        $errorMessage = $error['message']
            ?? (! $sunatOk && empty($sunatResp) ? ($json['message'] ?? null) : null);

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
            errorCode:        $errorCode,
            errorMessage:     $errorMessage,
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

        // Fallback: algunos facturadores devuelven el mensaje de error en la raíz
        $errorMessage = $error['message']
            ?? (! $ok ? ($json['message'] ?? null) : null);

        return new self(
            ok:           $ok,
            hash:         $json['hash'] ?? null,
            xmlBase64:    $json['xml'] ?? null,
            sunatSuccess: $ok,
            ticket:       $json['ticket'] ?? null,
            errorCode:    isset($error['code']) && $error['code'] !== null ? (string) $error['code'] : null,
            errorMessage: $errorMessage,
        );
    }

    /**
     * Respuesta de POST /api/summaries/status y POST /api/voids/status
     * Mismo formato que sunatResponse: devuelve CDR con resultado definitivo
     */
    public static function fromStatus(array $json): self
    {
        $ok    = (bool) ($json['success'] ?? false);
        $cdr   = is_array($json['cdrResponse'] ?? null) ? $json['cdrResponse'] : [];
        $error = $json['error'] ?? null;

        $errorCode = isset($error['code']) && $error['code'] !== null ? (string) $error['code'] : null;

        $errorMessage = ($error['message'] ?? null)
            ?? (! $ok ? ($json['message'] ?? null) : null);

        // Errores de infraestructura de SUNAT (no son rechazos reales del RC):
        //  - Código 98: ticket aún en proceso
        //  - "Cloud Agent Error" / "taking longer than": timeout del gateway de SUNAT
        //  - "backside connection": proxy de SUNAT no alcanza su backend
        //  - "pending: true" enviado explícitamente por el facturador
        $msg = strtolower((string) ($errorMessage ?? ''));
        $isSunatInfra = str_contains($msg, 'cloud agent error')
            || str_contains($msg, 'taking longer than')
            || str_contains($msg, 'backside connection')
            || str_contains($msg, 'failed to establish');
        $pending = ! $ok && (
            ($json['pending'] ?? false)
            || $errorCode === '98'
            || $isSunatInfra
        );

        return new self(
            ok:               $ok,
            sunatSuccess:     $ok,
            sunatCode:        isset($cdr['code']) && $cdr['code'] !== null ? (int) $cdr['code'] : null,
            sunatDescription: $cdr['description'] ?? null,
            sunatNotes:       $cdr['notes'] ?? [],
            cdrZip:           $json['cdrZip'] ?? null,
            errorCode:        $errorCode,
            errorMessage:     $errorMessage,
            pending:          $pending,
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
