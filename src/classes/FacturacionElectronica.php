<?php
namespace App;

/**
 * Clase para manejar la Facturación Electrónica con ARCA (ex AFIP)
 * Utiliza el webservice WSFE (Web Service de Factura Electrónica)
 */
class FacturacionElectronica {
    
    protected $conexion;
    protected $config;
    protected $afip;
    
    // Códigos de tipos de comprobante
    const FACTURA_A = 1;
    const FACTURA_B = 6;
    const FACTURA_C = 11;
    const NOTA_CREDITO_A = 3;
    const NOTA_CREDITO_B = 8;
    const NOTA_CREDITO_C = 13;
    
    // Códigos de IVA
    const IVA_21 = 5;  // 21%
    const IVA_105 = 4; // 10.5%
    const IVA_27 = 6;  // 27%
    const IVA_0 = 3;   // 0%
    const IVA_EXENTO = 2;
    
    // Tipos de documento
    const DOC_CUIT = 80;
    const DOC_CUIL = 86;
    const DOC_DNI = 96;
    const DOC_CONSUMIDOR_FINAL = 99;
    
    // Condiciones IVA
    const COND_IVA_RESPONSABLE_INSCRIPTO = 1;
    const COND_IVA_EXENTO = 4;
    const COND_IVA_CONSUMIDOR_FINAL = 5;
    const COND_IVA_MONOTRIBUTO = 6;
    
    /**
     * Constructor
     */
    public function __construct($conexion) {
        $this->conexion = $conexion;
        $this->cargarConfiguracion();
    }
    
    /**
     * Cargar configuración desde la base de datos
     */
    private function cargarConfiguracion() {
        $query = mysqli_query($this->conexion, "SELECT * FROM facturacion_config LIMIT 1");
        if ($query && mysqli_num_rows($query) > 0) {
            $this->config = mysqli_fetch_assoc($query);
        } else {
            throw new \Exception("No se encontró configuración de facturación electrónica");
        }
    }
    
    /**
     * Inicializar SDK de AFIP
     * Nota: Este método depende del SDK que elijas usar
     */
    protected function inicializarSDK() {
        // Ejemplo usando AfipSDK (deberás ajustar según el SDK que uses)
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $wsfe_config = [
            'cuit' => $this->config['cuit'],
            'cert' => $this->config['cert_path'],
            'key' => $this->config['key_path'],
            'production' => (bool) $this->config['produccion']
        ];
        
        // Aquí inicializarías el SDK específico que uses
        // Ejemplo: $this->afip = new \AfipSDK\Afip($wsfe_config);
        
        return true;
    }
    
    /**
     * Determinar tipo de comprobante según condición IVA del cliente
     */
    public function determinarTipoComprobante($cliente_condicion_iva) {
        $emisor_cond = $this->config['iva_condition'];
        
        // Si el emisor es Responsable Inscripto
        if (stripos($emisor_cond, 'Responsable Inscripto') !== false) {
            if (stripos($cliente_condicion_iva, 'Responsable Inscripto') !== false) {
                return self::FACTURA_A; // Factura A
            } elseif (stripos($cliente_condicion_iva, 'Monotributo') !== false || 
                      stripos($cliente_condicion_iva, 'Exento') !== false) {
                return self::FACTURA_A; // Factura A
            } else {
                return self::FACTURA_B; // Factura B (Consumidor Final)
            }
        }
        
        // Si el emisor es Monotributo
        if (stripos($emisor_cond, 'Monotributo') !== false) {
            return self::FACTURA_C; // Siempre Factura C
        }
        
        return self::FACTURA_C; // Por defecto
    }
    
    /**
     * Generar factura electrónica para una venta
     */
    public function generarFactura($id_venta) {
        // 1. Obtener datos de la venta
        $query_venta = mysqli_query($this->conexion, 
            "SELECT v.*, c.nombre, c.dni, c.cuit, c.telefono, c.direccion, c.condicion_iva, c.tipo_documento
             FROM ventas v 
             LEFT JOIN cliente c ON v.id_cliente = c.idcliente 
             WHERE v.id = " . intval($id_venta));
        
        if (!$query_venta || mysqli_num_rows($query_venta) == 0) {
            throw new \Exception("Venta no encontrada");
        }
        
        $venta = mysqli_fetch_assoc($query_venta);
        
        // 2. Obtener detalle de la venta
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
            throw new \Exception("No se encontraron items en la venta");
        }
        
        // 3. Determinar tipo de comprobante
        $tipo_comprobante = $this->determinarTipoComprobante($venta['condicion_iva'] ?? 'Consumidor Final');
        
        // 4. Obtener próximo número de comprobante
        $punto_venta = $this->config['punto_venta'];
        $proximo_numero = $this->obtenerProximoNumero($tipo_comprobante, $punto_venta);
        
        // 5. Preparar datos del comprobante
        $fecha_emision = date('Ymd'); // Formato: YYYYMMDD
        
        // Calcular totales según tipo de factura
        $total = floatval($venta['total']);
        $neto_gravado = 0;
        $iva_total = 0;
        
        if ($tipo_comprobante == self::FACTURA_A || $tipo_comprobante == self::FACTURA_B) {
            // Para Factura A y B, el total incluye IVA
            // Asumiendo IVA 21% (puedes ajustar según tus productos)
            $neto_gravado = round($total / 1.21, 2);
            $iva_total = round($total - $neto_gravado, 2);
        } else {
            // Para Factura C, no se discrimina IVA
            $neto_gravado = $total;
            $iva_total = 0;
        }
        
        // 6. Determinar tipo y número de documento del cliente
        $tipo_doc = self::DOC_DNI;
        $nro_doc = preg_replace('/[^0-9]/', '', $venta['dni'] ?? '0');
        
        if (!empty($venta['cuit']) && $tipo_comprobante == self::FACTURA_A) {
            $tipo_doc = self::DOC_CUIT;
            $nro_doc = preg_replace('/[^0-9]/', '', $venta['cuit']);
        }
        
        if (empty($nro_doc) || $nro_doc == '0') {
            $tipo_doc = self::DOC_CONSUMIDOR_FINAL;
            $nro_doc = 0;
        }
        
        // 7. Crear array de datos para enviar a AFIP
        $comprobante_data = [
            'CantReg' => 1, // Cantidad de comprobantes a registrar
            'PtoVta' => $punto_venta,
            'CbteTipo' => $tipo_comprobante,
            'Concepto' => 1, // 1=Productos, 2=Servicios, 3=Productos y Servicios
            'DocTipo' => $tipo_doc,
            'DocNro' => intval($nro_doc),
            'CbteDesde' => $proximo_numero,
            'CbteHasta' => $proximo_numero,
            'CbteFch' => $fecha_emision,
            'ImpTotal' => $total,
            'ImpTotConc' => 0, // Importe neto no gravado
            'ImpNeto' => $neto_gravado,
            'ImpOpEx' => 0, // Importe exento
            'ImpIVA' => $iva_total,
            'ImpTrib' => 0, // Otros tributos
            'MonId' => 'PES', // Moneda: Pesos
            'MonCotiz' => 1, // Cotización
        ];
        
        // 8. Agregar alícuotas de IVA (solo para Factura A y B)
        if ($tipo_comprobante == self::FACTURA_A || $tipo_comprobante == self::FACTURA_B) {
            $comprobante_data['Iva'] = [
                [
                    'Id' => self::IVA_21,
                    'BaseImp' => $neto_gravado,
                    'Importe' => $iva_total
                ]
            ];
        }
        
        // 9. Llamar al webservice de AFIP
        try {
            $this->inicializarSDK();
            
            // Aquí llamarías al método del SDK para crear el comprobante
            // Ejemplo (ajustar según SDK):
            // $resultado = $this->afip->ElectronicBilling->CreateVoucher($comprobante_data);
            
            // SIMULACIÓN DE RESPUESTA (debes reemplazar con la llamada real)
            $resultado = $this->simularRespuestaAFIP($comprobante_data);
            
            // 10. Guardar en base de datos
            $this->guardarFactura([
                'id_venta' => $id_venta,
                'tipo_comprobante' => $tipo_comprobante,
                'punto_venta' => $punto_venta,
                'numero_comprobante' => $proximo_numero,
                'fecha_emision' => date('Y-m-d'),
                'cae' => $resultado['CAE'],
                'vencimiento_cae' => $resultado['CAEFchVto'],
                'total' => $total,
                'iva_total' => $iva_total,
                'neto_gravado' => $neto_gravado,
                'estado' => 'aprobado',
                'xml_request' => json_encode($comprobante_data),
                'xml_response' => json_encode($resultado),
                'observaciones' => $resultado['Observaciones'] ?? ''
            ]);
            
            return [
                'success' => true,
                'cae' => $resultado['CAE'],
                'vencimiento_cae' => $resultado['CAEFchVto'],
                'tipo_comprobante' => $tipo_comprobante,
                'numero_comprobante' => $proximo_numero,
                'punto_venta' => $punto_venta
            ];
            
        } catch (\Exception $e) {
            // Guardar error en base de datos
            $this->guardarFactura([
                'id_venta' => $id_venta,
                'tipo_comprobante' => $tipo_comprobante,
                'punto_venta' => $punto_venta,
                'numero_comprobante' => $proximo_numero,
                'fecha_emision' => date('Y-m-d'),
                'total' => $total,
                'iva_total' => $iva_total,
                'neto_gravado' => $neto_gravado,
                'estado' => 'error',
                'xml_request' => json_encode($comprobante_data),
                'observaciones' => $e->getMessage()
            ]);
            
            throw new \Exception("Error al generar factura: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener próximo número de comprobante
     */
    protected function obtenerProximoNumero($tipo_comprobante, $punto_venta) {
        // Primero intentar obtener desde la base de datos local
        $query = mysqli_query($this->conexion,
            "SELECT MAX(numero_comprobante) as ultimo 
             FROM facturas_electronicas 
             WHERE tipo_comprobante = $tipo_comprobante 
             AND punto_venta = $punto_venta");
        
        if ($query && mysqli_num_rows($query) > 0) {
            $row = mysqli_fetch_assoc($query);
            if ($row['ultimo']) {
                return intval($row['ultimo']) + 1;
            }
        }
        
        // Si no hay registros locales, consultar a AFIP
        // $ultimo = $this->afip->ElectronicBilling->GetLastVoucher($punto_venta, $tipo_comprobante);
        // return $ultimo + 1;
        
        return 1; // Primer comprobante
    }
    
    /**
     * Guardar factura en base de datos
     */
    protected function guardarFactura($datos) {
        $campos = [];
        $valores = [];
        
        foreach ($datos as $campo => $valor) {
            $campos[] = $campo;
            if (is_null($valor)) {
                $valores[] = "NULL";
            } elseif (is_numeric($valor)) {
                $valores[] = $valor;
            } else {
                $valores[] = "'" . mysqli_real_escape_string($this->conexion, $valor) . "'";
            }
        }
        
        $sql = "INSERT INTO facturas_electronicas (" . implode(", ", $campos) . ") 
                VALUES (" . implode(", ", $valores) . ")";
        
        $query = mysqli_query($this->conexion, $sql);
        
        if (!$query) {
            throw new \Exception("Error al guardar factura: " . mysqli_error($this->conexion));
        }
        
        return mysqli_insert_id($this->conexion);
    }
    
    /**
     * Simulación de respuesta de AFIP (SOLO PARA DESARROLLO)
     * Debes reemplazar esto con la llamada real al webservice
     */
    private function simularRespuestaAFIP($data) {
        // Simular CAE (Código de Autorización Electrónica)
        $cae = str_pad(rand(10000000000000, 99999999999999), 14, '0', STR_PAD_LEFT);
        
        // Vencimiento del CAE (10 días desde hoy)
        $vencimiento = date('Ymd', strtotime('+10 days'));
        
        return [
            'CAE' => $cae,
            'CAEFchVto' => $vencimiento,
            'Resultado' => 'A', // A=Aprobado, R=Rechazado
            'Observaciones' => ''
        ];
    }
    
    /**
     * Obtener datos de factura por ID de venta
     */
    public function obtenerFacturaPorVenta($id_venta) {
        $query = mysqli_query($this->conexion,
            "SELECT f.*, t.descripcion as tipo_comprobante_desc 
             FROM facturas_electronicas f
             LEFT JOIN tipos_comprobante t ON f.tipo_comprobante = t.id
             WHERE f.id_venta = " . intval($id_venta) . "
             ORDER BY f.created_at DESC
             LIMIT 1");
        
        if ($query && mysqli_num_rows($query) > 0) {
            return mysqli_fetch_assoc($query);
        }
        
        return null;
    }
    
    /**
     * Generar PDF de factura electrónica
     */
    public function generarPDFFactura($id_factura) {
        // Aquí implementarías la generación del PDF con los datos de la factura
        // incluyendo el código QR con los datos del CAE
        // Lo veremos en el siguiente paso
    }
}

