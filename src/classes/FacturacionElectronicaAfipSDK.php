<?php
namespace App;

require_once __DIR__ . '/AfipWsaa.php';
require_once __DIR__ . '/AfipWsfe.php';

/**
 * Implementación de facturación electrónica usando comunicación directa con AFIP
 * (WSAA + WSFE) sin dependencia de SDKs externos de terceros.
 */
class FacturacionElectronicaAfipSDK extends FacturacionElectronica {

    /** @var AfipWsaa */
    private $wsaa = null;

    /** @var AfipWsfe */
    private $wsfe = null;

    /**
     * Inicializar conexión directa con AFIP
     */
    protected function inicializarSDK() {
        $cert_path = $this->config['cert_path'];
        $key_path  = $this->config['key_path'];

        if (!file_exists($cert_path)) {
            throw new \Exception("No se encontró el certificado: {$cert_path}");
        }
        if (!file_exists($key_path)) {
            throw new \Exception("No se encontró la clave privada: {$key_path}");
        }

        // Detectar si storage/afip_ta existe fuera de public_html (cPanel)
        // o dentro del proyecto (Docker/desarrollo).
        // Desde src/classes/:
        //   3 niveles arriba → home del usuario en cPanel (/home/user/)
        //   2 niveles arriba → raíz del proyecto en Docker (/var/www/html/)
        $storage_cpanel = realpath(__DIR__ . '/../../../') . '/storage/afip_ta/';
        $storage_local  = realpath(__DIR__ . '/../../') . '/storage/afip_ta/';

        if (is_dir(dirname($storage_cpanel))) {
            // Existe el directorio padre (home del usuario en cPanel)
            $ta_folder = $storage_cpanel;
        } else {
            $ta_folder = $storage_local;
        }

        if (!is_dir($ta_folder)) {
            mkdir($ta_folder, 0755, true);
        }

        $this->wsaa = new AfipWsaa(
            $cert_path,
            $key_path,
            (bool) $this->config['produccion'],
            $ta_folder
        );

        $this->wsfe = new AfipWsfe(
            $this->config['cuit'],
            $this->wsaa,
            (bool) $this->config['produccion']
        );
    }

    /**
     * Generar factura electrónica real contra AFIP
     */
    public function generarFactura($id_venta, array $overrideData = []) {
        $datos_facturacion = $this->obtenerDatosPreviosFacturacion($id_venta, $overrideData);
        $venta = $datos_facturacion['venta'];
        $tipo_comprobante = $datos_facturacion['tipo_comprobante'];
        $cliente_factura = $datos_facturacion['cliente_factura'];
        $fecha_emision_db = $datos_facturacion['fecha_emision'];
        $fecha_emision_afip = $datos_facturacion['fecha_emision_afip'];

        // Obtener detalle
        $query_detalle = mysqli_query($this->conexion,
            "SELECT dv.*, p.descripcion, p.codigo
             FROM detalle_venta dv
             INNER JOIN producto p ON dv.id_producto = p.codproducto
             WHERE dv.id_venta = " . intval($id_venta));

        $items = [];
        while ($row = mysqli_fetch_assoc($query_detalle)) {
            $items[] = $row;
        }

        if (empty($items)) {
            throw new \Exception("No se encontraron ítems en la venta");
        }

        $punto_venta      = (int) $this->config['punto_venta'];
        $total            = round((float) $venta['total'], 2);

        // Calcular importes
        if ($tipo_comprobante == self::FACTURA_A || $tipo_comprobante == self::FACTURA_B) {
            $neto_gravado = round($total / 1.21, 2);
            $iva_total    = round($total - $neto_gravado, 2);
        } else {
            $neto_gravado = $total;
            $iva_total    = 0;
        }

        // Tipo y número de documento del cliente
        $tipo_doc = $cliente_factura['tipo_documento'];
        $nro_doc  = $cliente_factura['numero_documento'];

        // Inicializar conexión AFIP
        $this->inicializarSDK();

        $proximo_numero = null;

        try {
            // Obtener último número de comprobante directamente desde AFIP
            $ultimo_numero  = $this->wsfe->getLastVoucher($punto_venta, $tipo_comprobante);
            $proximo_numero = $ultimo_numero + 1;

            $fecha_emision = $fecha_emision_afip;

            $data = [
                'CantReg'    => 1,
                'PtoVta'     => $punto_venta,
                'CbteTipo'   => $tipo_comprobante,
                'Concepto'   => 1,
                'DocTipo'    => $tipo_doc,
                'DocNro'     => (int) $nro_doc,
                'CbteDesde'  => $proximo_numero,
                'CbteHasta'  => $proximo_numero,
                'CbteFch'    => (int) $fecha_emision,
                'ImpTotal'   => $total,
                'ImpTotConc' => 0,
                'ImpNeto'    => $neto_gravado,
                'ImpOpEx'    => 0,
                'ImpIVA'     => $iva_total,
                'ImpTrib'    => 0,
                'MonId'      => 'PES',
                'MonCotiz'   => 1,
                'cliente_factura' => $cliente_factura,
                'fecha_emision' => $fecha_emision_db,
            ];

            if ($tipo_comprobante == self::FACTURA_A || $tipo_comprobante == self::FACTURA_B) {
                $data['Iva'] = [[
                    'Id'      => self::IVA_21,
                    'BaseImp' => $neto_gravado,
                    'Importe' => $iva_total,
                ]];
            }

            error_log("WSFE - Enviando comprobante: " . json_encode($data));

            $resultado = $this->wsfe->createVoucher($data);

            error_log("WSFE - Respuesta: " . json_encode($resultado));

            if (empty($resultado['CAE'])) {
                throw new \Exception("AFIP no devolvió CAE en la respuesta");
            }

            // Guardar en base de datos
            $factura_id = $this->guardarFactura([
                'id_venta'           => $id_venta,
                'tipo_comprobante'   => $tipo_comprobante,
                'punto_venta'        => $punto_venta,
                'numero_comprobante' => $proximo_numero,
                'fecha_emision'      => $fecha_emision_db,
                'cae'                => $resultado['CAE'],
                'vencimiento_cae'    => $resultado['CAEFchVto'],
                'total'              => $total,
                'iva_total'          => $iva_total,
                'neto_gravado'       => $neto_gravado,
                'estado'             => 'aprobado',
                'xml_request'        => json_encode($data),
                'xml_response'       => json_encode($resultado),
                'observaciones'      => '',
            ]);

            return [
                'success'            => true,
                'factura_id'         => $factura_id,
                'cae'                => $resultado['CAE'],
                'vencimiento_cae'    => $resultado['CAEFchVto'],
                'tipo_comprobante'   => $tipo_comprobante,
                'numero_comprobante' => $proximo_numero,
                'punto_venta'        => $punto_venta,
            ];

        } catch (\Exception $e) {
            $original_error = $e->getMessage();
            error_log("Error al generar factura electrónica: " . $original_error);

            // Solo guardar registro de error si ya teníamos número de comprobante válido
            if ($proximo_numero !== null && $proximo_numero > 0) {
                try {
                    $this->guardarFactura([
                        'id_venta'           => $id_venta,
                        'tipo_comprobante'   => $tipo_comprobante,
                        'punto_venta'        => $punto_venta,
                        'numero_comprobante' => $proximo_numero,
                        'fecha_emision'      => $fecha_emision_db,
                        'total'              => $total,
                        'iva_total'          => $iva_total,
                        'neto_gravado'       => $neto_gravado,
                        'estado'             => 'error',
                        'xml_request'        => isset($data) ? json_encode($data) : '',
                        'observaciones'      => $original_error,
                    ]);
                } catch (\Exception $dbError) {
                    error_log("Error al guardar registro de error en DB: " . $dbError->getMessage());
                }
            }

            throw new \Exception("Error al generar factura: " . $original_error);
        }
    }

    /**
     * Consultar estado de un comprobante en AFIP
     */
    public function consultarComprobante($tipo_comprobante, $punto_venta, $numero_comprobante) {
        $this->inicializarSDK();

        try {
            $client = $this->wsfe;
            // Método informativo — no siempre necesario
            return ['success' => true, 'message' => 'Consulta no implementada directamente'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
