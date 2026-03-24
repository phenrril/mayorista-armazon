<?php
namespace App;

/**
 * Autenticación directa con AFIP WSAA (Web Service de Autenticación y Autorización)
 * Implementación propia sin dependencia de SDKs externos.
 */
class AfipWsaa {

    const URL_TEST = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL';
    const URL_PROD = 'https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL';

    private $cert_path;
    private $key_path;
    private $production;
    private $ta_folder;

    public function __construct($cert_path, $key_path, $production = false, $ta_folder = '/tmp/') {
        $this->cert_path  = $cert_path;
        $this->key_path   = $key_path;
        $this->production = $production;
        $this->ta_folder  = rtrim($ta_folder, '/') . '/';

        if (!is_dir($this->ta_folder)) {
            mkdir($this->ta_folder, 0755, true);
        }
    }

    /**
     * Opciones SSL permisivas para compatibilidad con servidores AFIP
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
     * Obtener token y sign para un servicio AFIP.
     * Usa el Ticket de Acceso (TA) cacheado si todavía es válido.
     */
    public function getTicket($service = 'wsfe') {
        $env     = $this->production ? 'prod' : 'test';
        $ta_file = $this->ta_folder . "TA_{$service}_{$env}.xml";

        if (file_exists($ta_file)) {
            try {
                $ta_xml     = file_get_contents($ta_file);
                $ta         = new \SimpleXMLElement($ta_xml);
                $expiration = strtotime((string) $ta->header->expirationTime);
                if ($expiration > (time() + 120)) {
                    return [
                        'token' => (string) $ta->credentials->token,
                        'sign'  => (string) $ta->credentials->sign,
                    ];
                }
            } catch (\Exception $e) {
                // TA cacheado inválido, se regenera
            }
        }

        $tra    = $this->buildTRA($service);
        $cms    = $this->signTRA($tra);
        $ta_xml = $this->callWSAA($cms);

        file_put_contents($ta_file, $ta_xml);

        $ta = new \SimpleXMLElement($ta_xml);
        return [
            'token' => (string) $ta->credentials->token,
            'sign'  => (string) $ta->credentials->sign,
        ];
    }

    /**
     * Construir el XML del Ticket de Requerimiento de Acceso (TRA)
     */
    private function buildTRA($service) {
        $uniqueId       = time();
        $generationTime = date('c', time() - 60);
        $expirationTime = date('c', time() + 3600);

        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<loginTicketRequest version="1.0">' .
                '<header>' .
                    "<uniqueId>{$uniqueId}</uniqueId>" .
                    "<generationTime>{$generationTime}</generationTime>" .
                    "<expirationTime>{$expirationTime}</expirationTime>" .
                '</header>' .
                "<service>{$service}</service>" .
            '</loginTicketRequest>';
    }

    /**
     * Firmar el TRA con el certificado y clave privada usando OpenSSL (PKCS7/CMS)
     */
    private function signTRA($tra) {
        $tra_tmp = tempnam(sys_get_temp_dir(), 'tra_');
        $cms_tmp = tempnam(sys_get_temp_dir(), 'cms_');

        file_put_contents($tra_tmp, $tra);

        $cert = file_get_contents($this->cert_path);
        $key  = file_get_contents($this->key_path);

        if (!$cert) throw new \Exception("No se pudo leer el certificado: {$this->cert_path}");
        if (!$key)  throw new \Exception("No se pudo leer la clave privada: {$this->key_path}");

        $ok = openssl_pkcs7_sign(
            $tra_tmp,
            $cms_tmp,
            $cert,
            [$key, ''],
            [],
            PKCS7_NOSIGS | PKCS7_BINARY | PKCS7_NOATTR
        );

        @unlink($tra_tmp);

        if (!$ok) {
            @unlink($cms_tmp);
            $errs = [];
            while ($e = openssl_error_string()) $errs[] = $e;
            throw new \Exception("Error al firmar TRA con OpenSSL: " . implode('; ', $errs));
        }

        $smime = file_get_contents($cms_tmp);
        @unlink($cms_tmp);

        // Extraer el cuerpo base64 del SMIME (sin las cabeceras MIME)
        if (preg_match('/\r\n\r\n(.+)$/s', $smime, $m) ||
            preg_match('/\n\n(.+)$/s', $smime, $m)) {
            return str_replace(["\n", "\r", " "], '', $m[1]);
        }

        throw new \Exception("No se pudo extraer el CMS firmado del output SMIME");
    }

    /**
     * Descargar el WSDL con caché local
     */
    private function getWsdlPath() {
        $url       = $this->production ? self::URL_PROD : self::URL_TEST;
        $cache_key = md5($url);
        $cached    = sys_get_temp_dir() . "/wsaa_{$cache_key}.wsdl";

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
                "No se pudo descargar el WSDL de WSAA desde {$url}. " .
                "Detalle: " . ($err['message'] ?? 'desconocido')
            );
        }

        file_put_contents($cached, $content);
        return $cached;
    }

    /**
     * Llamar al webservice WSAA de AFIP para obtener el Ticket de Acceso (TA)
     */
    private function callWSAA($cms) {
        $url       = $this->production ? self::URL_PROD : self::URL_TEST;
        $endpoint  = str_replace('?WSDL', '', $url);
        $wsdl_path = $this->getWsdlPath();

        $context = stream_context_create([
            'ssl' => $this->getSslOptions(),
        ]);

        try {
            $client = new \SoapClient($wsdl_path, [
                'soap_version'   => SOAP_1_2,
                'location'       => $endpoint,
                'trace'          => true,
                'exceptions'     => true,
                'stream_context' => $context,
                'cache_wsdl'     => WSDL_CACHE_NONE,
            ]);

            $result = $client->loginCms(['in0' => $cms]);
            return $result->loginCmsReturn;

        } catch (\SoapFault $e) {
            throw new \Exception("Error WSAA SOAP: " . $e->getMessage());
        }
    }
}
