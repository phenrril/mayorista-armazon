<?php
namespace App;

/**
 * Facturación electrónica directa con AFIP WSFE (Web Service de Factura Electrónica)
 * Implementación propia sin dependencia de SDKs externos.
 */
class AfipWsfe {

    const URL_TEST = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL';
    const URL_PROD = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL';

    private $cuit;
    private $wsaa;
    private $production;
    private $client = null;

    public function __construct($cuit, AfipWsaa $wsaa, $production = false) {
        $this->cuit       = (int) $cuit;
        $this->wsaa       = $wsaa;
        $this->production = $production;
    }

    /**
     * Opciones SSL permisivas para compatibilidad con servidores AFIP
     * (usan algoritmos que OpenSSL 3 bloquea por defecto con SECLEVEL 2)
     */
    private function getSslOptions() {
        return [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
            'ciphers'           => 'DEFAULT:@SECLEVEL=0',
        ];
    }

    /**
     * Descargar el WSDL con caché local (evita que libxml lo intente cargar
     * directamente, lo cual falla en PHP 8 con entidades externas deshabilitadas).
     */
    private function getWsdlPath() {
        $url       = $this->production ? self::URL_PROD : self::URL_TEST;
        $cache_key = md5($url);
        $cached    = sys_get_temp_dir() . "/wsfe_{$cache_key}.wsdl";

        if (file_exists($cached) && (time() - filemtime($cached)) < 86400) {
            return $cached;
        }

        $context = stream_context_create([
            'ssl'  => $this->getSslOptions(),
            'http' => ['timeout' => 30],
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            $err = error_get_last();
            throw new \Exception(
                "No se pudo descargar el WSDL de AFIP desde {$url}. " .
                "Verificá que el servidor tenga acceso a internet. " .
                "Detalle: " . ($err['message'] ?? 'desconocido')
            );
        }

        file_put_contents($cached, $content);
        return $cached;
    }

    /**
     * Obtener cliente SOAP (con lazy init y WSDL local cacheado)
     */
    private function getClient() {
        if ($this->client !== null) return $this->client;

        $wsdl_path = $this->getWsdlPath();
        $url       = $this->production ? self::URL_PROD : self::URL_TEST;
        $endpoint  = str_replace('?WSDL', '', $url);

        $context = stream_context_create([
            'ssl' => $this->getSslOptions(),
        ]);

        try {
            $this->client = new \SoapClient($wsdl_path, [
                'soap_version'   => SOAP_1_2,
                'location'       => $endpoint,
                'trace'          => true,
                'exceptions'     => true,
                'stream_context' => $context,
                'cache_wsdl'     => WSDL_CACHE_NONE,
            ]);
        } catch (\SoapFault $e) {
            throw new \Exception("No se pudo inicializar el cliente SOAP WSFE: " . $e->getMessage());
        }

        return $this->client;
    }

    /**
     * Armar el array de autenticación para cada llamada SOAP
     */
    private function getAuth() {
        $ticket = $this->wsaa->getTicket('wsfe');
        return [
            'Token' => $ticket['token'],
            'Sign'  => $ticket['sign'],
            'Cuit'  => $this->cuit,
        ];
    }

    /**
     * Obtener el último número de comprobante autorizado en AFIP
     */
    public function getLastVoucher($punto_venta, $tipo_cbte) {
        $client = $this->getClient();

        try {
            $result = $client->FECompUltimoAutorizado([
                'Auth'     => $this->getAuth(),
                'PtoVta'   => (int) $punto_venta,
                'CbteTipo' => (int) $tipo_cbte,
            ]);
        } catch (\SoapFault $e) {
            throw new \Exception("Error WSFE FECompUltimoAutorizado: " . $e->getMessage());
        }

        $resp = $result->FECompUltimoAutorizadoResult;
        $this->checkErrors($resp);

        return (int) $resp->CbteNro;
    }

    /**
     * Solicitar CAE para un comprobante (FECAESolicitar)
     */
    public function createVoucher(array $data) {
        $client = $this->getClient();

        $det = [
            'Concepto'   => (int)   $data['Concepto'],
            'DocTipo'    => (int)   $data['DocTipo'],
            'DocNro'     => (int)   $data['DocNro'],
            'CbteDesde'  => (int)   $data['CbteDesde'],
            'CbteHasta'  => (int)   $data['CbteHasta'],
            'CbteFch'    => (int)   $data['CbteFch'],
            'ImpTotal'   => (float) $data['ImpTotal'],
            'ImpTotConc' => (float) ($data['ImpTotConc'] ?? 0),
            'ImpNeto'    => (float) $data['ImpNeto'],
            'ImpOpEx'    => (float) ($data['ImpOpEx'] ?? 0),
            'ImpIVA'     => (float) $data['ImpIVA'],
            'ImpTrib'    => (float) ($data['ImpTrib'] ?? 0),
            'MonId'      => $data['MonId'] ?? 'PES',
            'MonCotiz'   => (float) ($data['MonCotiz'] ?? 1),
        ];

        if (!empty($data['Iva'])) {
            $iva_items = [];
            foreach ($data['Iva'] as $iva) {
                $iva_items[] = [
                    'Id'      => (int)   $iva['Id'],
                    'BaseImp' => (float) $iva['BaseImp'],
                    'Importe' => (float) $iva['Importe'],
                ];
            }
            $det['Iva'] = [
                'AlicIva' => count($iva_items) === 1 ? $iva_items[0] : $iva_items,
            ];
        }

        $request = [
            'Auth'     => $this->getAuth(),
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg'  => (int) $data['CantReg'],
                    'PtoVta'   => (int) $data['PtoVta'],
                    'CbteTipo' => (int) $data['CbteTipo'],
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => $det,
                ],
            ],
        ];

        try {
            $result = $client->FECAESolicitar($request);
        } catch (\SoapFault $e) {
            throw new \Exception("Error WSFE FECAESolicitar: " . $e->getMessage());
        }

        $resp = $result->FECAESolicitarResult;
        $this->checkErrors($resp);

        $detResp = $resp->FeDetResp->FECAEDetResponse;

        if ($detResp->Resultado !== 'A') {
            $obs_msg = '';
            if (isset($detResp->Observaciones->Obs)) {
                $obs = $detResp->Observaciones->Obs;
                if (!is_array($obs)) $obs = [$obs];
                foreach ($obs as $o) {
                    $obs_msg .= "(Código {$o->Code}) {$o->Msg}; ";
                }
            }
            throw new \Exception("Comprobante rechazado por AFIP: " . trim($obs_msg, '; '));
        }

        return [
            'CAE'       => $detResp->CAE,
            'CAEFchVto' => date('Y-m-d', strtotime($detResp->CAEFchVto)),
            'Resultado' => $detResp->Resultado,
        ];
    }

    /**
     * Verificar errores en la respuesta WSFE
     */
    private function checkErrors($resp) {
        if (!isset($resp->Errors) || empty($resp->Errors)) return;

        $err = $resp->Errors->Err ?? null;
        if (empty($err)) return;

        if (!is_array($err)) $err = [$err];

        $msgs = [];
        foreach ($err as $e) {
            $msgs[] = "(Código {$e->Code}) {$e->Msg}";
        }

        throw new \Exception("Error AFIP: " . implode('; ', $msgs));
    }
}
