<?php
// Output buffering: captura cualquier salida inesperada (warnings, notices, etc.)
ob_start();

session_start();

// Capturar errores fatales y devolver siempre JSON con HTTP 200
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean(); // limpiar cualquier salida acumulada
        http_response_code(200);
        header('Content-Type: application/json');

        $mensaje = $error['message'];
        if (strpos($mensaje, 'Class') !== false && strpos($mensaje, 'not found') !== false) {
            $mensaje = 'El SDK de facturación no está instalado correctamente. Ejecutá dentro del contenedor: php composer.phar require afipsdk/afip.php';
        } elseif (strpos($mensaje, 'autoload') !== false) {
            $mensaje = 'Las dependencias PHP no están instaladas. Ejecutá dentro del contenedor: php composer.phar install';
        }

        echo json_encode([
            'success' => false,
            'message' => $mensaje,
            'debug_file' => basename($error['file']) . ':' . $error['line']
        ]);
    }
});

require_once "../conexion.php";
require_once "classes/FacturacionElectronica.php";
require_once "classes/FacturacionElectronicaAfipSDK.php";
require_once "includes/mayorista_helpers.php";

if (!($conexion instanceof \mysqli)) {
    echo json_encode(['success' => false, 'message' => 'No se pudo conectar a la base de datos']);
    exit();
}

// Verificar sesión
if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

// Verificar que se haya enviado el ID de venta
if (!isset($_POST['id_venta']) || empty($_POST['id_venta'])) {
    echo json_encode(['success' => false, 'message' => 'ID de venta no proporcionado']);
    exit();
}

$id_venta = intval($_POST['id_venta']);
if (mayorista_venta_tiene_factura_aprobada($conexion, $id_venta)) {
    echo json_encode(['success' => false, 'message' => 'La venta ya tiene una factura aprobada y no puede volver a facturarse.']);
    exit();
}
$override_data = [
    'nombre_cliente' => $_POST['nombre_cliente'] ?? '',
    'dni' => $_POST['dni'] ?? '',
    'cuit' => $_POST['cuit'] ?? '',
    'tipo_factura' => $_POST['tipo_factura'] ?? '',
    'tipo_documento' => $_POST['tipo_documento'] ?? '',
    'fecha_emision' => $_POST['fecha_emision'] ?? '',
];

try {
    // Leer configuración para decidir si usar simulación o SDK real
    $config_res = mysqli_query($conexion, "SELECT produccion FROM facturacion_config LIMIT 1");
    $config_row = ['produccion' => 0];
    if ($config_res instanceof \mysqli_result && mysqli_num_rows($config_res) > 0) {
        $config_row = mysqli_fetch_assoc($config_res);
    }
    $modo_produccion = !empty($config_row['produccion']);

    // Crear instancia de facturación electrónica
    // - Modo Testing (produccion = 0): usa clase base con simulación (no impacta en ARCA)
    // - Modo Producción (produccion = 1): usa integración real con ARCA mediante SDK
    if ($modo_produccion) {
        $facturacion = new \App\FacturacionElectronicaAfipSDK($conexion);
    } else {
        $facturacion = new \App\FacturacionElectronica($conexion);
    }
    
    // Generar factura
    $resultado = $facturacion->generarFactura($id_venta, $override_data);
    
    // Formatear respuesta
    $tipo_comprobante_texto = '';
    switch ($resultado['tipo_comprobante']) {
        case \App\FacturacionElectronica::FACTURA_A:
            $tipo_comprobante_texto = 'Factura A';
            break;
        case \App\FacturacionElectronica::FACTURA_B:
            $tipo_comprobante_texto = 'Factura B';
            break;
        case \App\FacturacionElectronica::FACTURA_C:
            $tipo_comprobante_texto = 'Factura C';
            break;
        default:
            $tipo_comprobante_texto = 'Comprobante';
    }
    
    $comprobante_completo = sprintf(
        "%s N° %04d-%08d",
        $tipo_comprobante_texto,
        $resultado['punto_venta'],
        $resultado['numero_comprobante']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Factura generada exitosamente',
        'data' => [
            'cae' => $resultado['cae'],
            'vencimiento_cae' => date('d/m/Y', strtotime($resultado['vencimiento_cae'])),
            'comprobante' => $comprobante_completo,
            'tipo_comprobante' => $tipo_comprobante_texto,
            'numero' => sprintf("%04d-%08d", $resultado['punto_venta'], $resultado['numero_comprobante'])
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error al generar factura electrónica: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al generar factura: ' . $e->getMessage()
    ]);
}

