<?php
/**
 * Generador de PDF de Factura Electrónica
 * Formato oficial según normativa ARCA/AFIP
 */

session_start();
if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header('HTTP/1.1 403 Forbidden');
    die('Acceso denegado. Debe iniciar sesión para ver este documento.');
}

require_once '../../conexion.php';
require_once '../includes/mayorista_helpers.php';
require_once 'fpdf/fpdf.php';
require_once 'pdf_footer_helper.php';
require_once '../classes/FacturacionElectronica.php';

if (!($conexion instanceof mysqli)) {
    header('HTTP/1.1 500 Internal Server Error');
    die('No se pudo conectar a la base de datos.');
}

$id_user = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $id_user, array('ventas'))) {
    header('HTTP/1.1 403 Forbidden');
    die('No tenés permisos para acceder a la factura electrónica.');
}

// Verificar parámetros
if (!isset($_GET['v']) || empty($_GET['v'])) {
    die('ID de venta no especificado');
}

$id_venta = intval($_GET['v']);
$query_venta = mysqli_query($conexion, "SELECT * FROM ventas WHERE id = $id_venta LIMIT 1");
$venta = $query_venta ? mysqli_fetch_assoc($query_venta) : null;
if (!$venta) {
    header('HTTP/1.1 404 Not Found');
    die('La venta indicada no existe');
}

// Obtener datos de la factura electrónica
$query_factura = mysqli_query($conexion, 
    "SELECT f.*, t.descripcion as tipo_comprobante_desc, t.codigo as tipo_codigo
     FROM facturas_electronicas f
     LEFT JOIN tipos_comprobante t ON f.tipo_comprobante = t.id
     WHERE f.id_venta = $id_venta
     ORDER BY f.created_at DESC
     LIMIT 1");

if (!$query_factura || mysqli_num_rows($query_factura) == 0) {
    die('No se encontró factura electrónica para esta venta');
}

$factura = mysqli_fetch_assoc($query_factura);

// Verificar que esté aprobada
if ($factura['estado'] !== 'aprobado') {
    die('La factura no está aprobada. Estado: ' . $factura['estado']);
}

// Obtener datos del cliente
$id_cliente = $venta['id_cliente'];
$query_cliente = mysqli_query($conexion, "SELECT * FROM cliente WHERE idcliente = $id_cliente");
$cliente = mysqli_fetch_assoc($query_cliente);

// Obtener detalle de productos
$query_detalle = mysqli_query($conexion, 
    "SELECT d.*, p.descripcion, p.codigo, p.marca, p.modelo, p.color, p.tipo
     FROM detalle_venta d 
     INNER JOIN producto p ON d.id_producto = p.codproducto 
     WHERE d.id_venta = $id_venta
     ORDER BY d.id ASC");

// Obtener configuración del negocio
$query_config_neg = mysqli_query($conexion, "SELECT * FROM configuracion LIMIT 1");
$config_negocio = mysqli_fetch_assoc($query_config_neg);

// Obtener configuración de facturación
$query_config_fact = mysqli_query($conexion, "SELECT * FROM facturacion_config LIMIT 1");
$config_facturacion = mysqli_fetch_assoc($query_config_fact);

// Crear PDF
$footerAssets = mayorista_pdf_footer_assets();

$pdf = new MayoristaBrandedPdf('P', 'mm', 'A4');
$pdf->setBrandFooter(
    $footerAssets['brand_logos'],
    $footerAssets['whatsapp_icon'],
    $footerAssets['whatsapp_text'],
    $footerAssets['instagram_icon'],
    $footerAssets['instagram_text'],
    array(
        'Este documento es una representacion impresa de la Factura Electronica',
        'La validez de la factura puede verificarse en www.afip.gob.ar/fe/qr/',
        '*** DOCUMENTO NO VALIDO COMO FACTURA ***',
    )
);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, $pdf->getFooterHeight() + 6);
$pdf->AddPage();

$brandLogoPath = realpath(__DIR__ . '/../../assets/logo-pdf-clean-white.png');

// Formatear números
$numero_comprobante = sprintf("%04d-%08d", $factura['punto_venta'], $factura['numero_comprobante']);
$fecha_emision = date('d/m/Y', strtotime($factura['fecha_emision']));
$vencimiento_cae = date('d/m/Y', strtotime($factura['vencimiento_cae']));

// Determinar letra del comprobante
$letra_comprobante = '';
switch ($factura['tipo_comprobante']) {
    case 1: $letra_comprobante = 'A'; break;
    case 6: $letra_comprobante = 'B'; break;
    case 11: $letra_comprobante = 'C'; break;
    default: $letra_comprobante = 'X';
}

// =====================================================
// ENCABEZADO - Datos del Emisor y Letra
// =====================================================

// Logo premium
if ($brandLogoPath && file_exists($brandLogoPath)) {
    $pdf->Image($brandLogoPath, 15, 13, 52, 0, 'PNG');
} elseif (file_exists("../../assets/img/logo.png")) {
    $pdf->Image("../../assets/img/logo.png", 15, 15, 30, 30, 'PNG');
}

// Datos del emisor (izquierda)
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetXY(15, 50);
$pdf->Cell(70, 5, utf8_decode($config_facturacion['razon_social'] ?? $config_negocio['nombre']), 0, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->SetX(15);
$pdf->Cell(70, 4, utf8_decode($config_negocio['direccion'] ?? ''), 0, 1, 'L');
$pdf->SetX(15);
$pdf->Cell(70, 4, 'Tel: ' . ($config_negocio['telefono'] ?? ''), 0, 1, 'L');
$pdf->SetX(15);
$pdf->Cell(70, 4, utf8_decode($config_negocio['email'] ?? ''), 0, 1, 'L');

// Letra grande en el centro (recuadro)
$pdf->SetLineWidth(1.5);
$pdf->Rect(93, 15, 24, 24);
$pdf->SetFont('Arial', 'B', 45);
$pdf->SetXY(93, 18);
$pdf->Cell(24, 20, $letra_comprobante, 0, 0, 'C');

// Código del comprobante debajo de la letra
$pdf->SetFont('Arial', '', 7);
$pdf->SetXY(93, 35);
$pdf->Cell(24, 4, 'Cod. ' . str_pad($factura['tipo_comprobante'], 2, '0', STR_PAD_LEFT), 0, 0, 'C');

// Datos fiscales del emisor (derecha)
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetXY(125, 15);
$pdf->Cell(70, 5, utf8_decode('FACTURA'), 0, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->SetXY(125, 20);
$pdf->Cell(70, 4, 'Punto de Venta: ' . str_pad($factura['punto_venta'], 4, '0', STR_PAD_LEFT), 0, 1, 'L');
$pdf->SetX(125);
$pdf->Cell(70, 4, utf8_decode('Comp. Nro: ') . $numero_comprobante, 0, 1, 'L');
$pdf->SetX(125);
$pdf->Cell(70, 4, 'Fecha: ' . $fecha_emision, 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetX(125);
$pdf->Cell(70, 4, 'CUIT: ' . $config_facturacion['cuit'], 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->SetX(125);
$pdf->Cell(70, 4, utf8_decode($config_facturacion['iva_condition'] ?? 'IVA Resp. Inscripto'), 0, 1, 'L');
$pdf->SetX(125);
$pdf->Cell(70, 4, 'Inicio Act: ' . date('d/m/Y', strtotime($config_facturacion['inicio_actividades'] ?? '2000-01-01')), 0, 1, 'L');

// Línea separadora
$pdf->SetLineWidth(0.5);
$pdf->Line(10, 75, 200, 75);

// =====================================================
// DATOS DEL CLIENTE
// =====================================================

$pdf->SetY(78);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(190, 6, utf8_decode('DATOS DEL CLIENTE'), 0, 1, 'L', true);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(50, 5, utf8_decode('Apellido y Nombre / Razón Social:'), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(140, 5, utf8_decode($cliente['nombre'] ?? 'CONSUMIDOR FINAL'), 0, 1, 'L');

// CUIT/DNI
$pdf->SetFont('Arial', '', 9);
$doc_label = 'DNI';
$doc_numero = $cliente['dni'] ?? '';

if (!empty($cliente['cuit'])) {
    $doc_label = 'CUIT';
    $doc_numero = $cliente['cuit'];
}

if (empty($doc_numero)) {
    $doc_numero = 'S/D';
}

$pdf->Cell(50, 5, $doc_label . ':', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(60, 5, $doc_numero, 0, 0, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(30, 5, utf8_decode('Condición IVA:'), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(50, 5, utf8_decode($cliente['condicion_iva'] ?? 'Consumidor Final'), 0, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(50, 5, utf8_decode('Dirección:'), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(140, 5, utf8_decode($cliente['direccion'] ?? ''), 0, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(50, 5, utf8_decode('Teléfono:'), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(140, 5, $cliente['telefono'] ?? '', 0, 1, 'L');

// Línea separadora
$pdf->SetLineWidth(0.5);
$pdf->Line(10, $pdf->GetY() + 2, 200, $pdf->GetY() + 2);

// =====================================================
// DETALLE DE PRODUCTOS
// =====================================================

$pdf->SetY($pdf->GetY() + 5);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220);

// Encabezados de tabla
if ($letra_comprobante == 'A' || $letra_comprobante == 'B') {
    // Factura con IVA discriminado
    $pdf->Cell(15, 6, 'Cant.', 1, 0, 'C', true);
    $pdf->Cell(75, 6, utf8_decode('Descripción'), 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'P. Unit.', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'Neto', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'IVA 21%', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'Subtotal', 1, 1, 'C', true);
} else {
    // Factura C (sin discriminar IVA)
    $pdf->Cell(15, 6, 'Cant.', 1, 0, 'C', true);
    $pdf->Cell(100, 6, utf8_decode('Descripción'), 1, 0, 'C', true);
    $pdf->Cell(35, 6, 'Precio Unit.', 1, 0, 'C', true);
    $pdf->Cell(40, 6, 'Subtotal', 1, 1, 'C', true);
}

// Items
$pdf->SetFont('Arial', '', 8);
$subtotal_general = 0;
$iva_general = 0;

while ($item = mysqli_fetch_assoc($query_detalle)) {
    $cantidad = floatval($item['cantidad']);
    $precio = floatval($item['precio']);
    $subtotal_item = $cantidad * $precio;
    
    if ($letra_comprobante == 'A' || $letra_comprobante == 'B') {
        // Calcular neto e IVA
        $neto_item = $subtotal_item / 1.21;
        $iva_item = $subtotal_item - $neto_item;
        
        $pdf->Cell(15, 5, number_format($cantidad, 0), 1, 0, 'C');
        $pdf->Cell(75, 5, utf8_decode(substr(mayorista_nombre_producto($item), 0, 50)), 1, 0, 'L');
        $pdf->Cell(25, 5, '$' . number_format($precio, 2), 1, 0, 'R');
        $pdf->Cell(25, 5, '$' . number_format($neto_item, 2), 1, 0, 'R');
        $pdf->Cell(25, 5, '$' . number_format($iva_item, 2), 1, 0, 'R');
        $pdf->Cell(25, 5, '$' . number_format($subtotal_item, 2), 1, 1, 'R');
        
        $iva_general += $iva_item;
    } else {
        // Factura C
        $pdf->Cell(15, 5, number_format($cantidad, 0), 1, 0, 'C');
        $pdf->Cell(100, 5, utf8_decode(substr(mayorista_nombre_producto($item), 0, 65)), 1, 0, 'L');
        $pdf->Cell(35, 5, '$' . number_format($precio, 2), 1, 0, 'R');
        $pdf->Cell(40, 5, '$' . number_format($subtotal_item, 2), 1, 1, 'R');
    }
    
    $subtotal_general += $subtotal_item;
}

// =====================================================
// TOTALES
// =====================================================

$y_actual = $pdf->GetY() + 3;
$pdf->SetY($y_actual);

$pdf->SetFont('Arial', 'B', 10);

if ($letra_comprobante == 'A' || $letra_comprobante == 'B') {
    // Discriminar IVA
    $neto_gravado = floatval($factura['neto_gravado']);
    $iva_total = floatval($factura['iva_total']);
    $total = floatval($factura['total']);
    
    $pdf->Cell(140, 6, '', 0, 0, 'R');
    $pdf->Cell(25, 6, 'Neto Gravado:', 0, 0, 'R');
    $pdf->Cell(25, 6, '$' . number_format($neto_gravado, 2), 1, 1, 'R');
    
    $pdf->Cell(140, 6, '', 0, 0, 'R');
    $pdf->Cell(25, 6, 'IVA 21%:', 0, 0, 'R');
    $pdf->Cell(25, 6, '$' . number_format($iva_total, 2), 1, 1, 'R');
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(140, 8, '', 0, 0, 'R');
    $pdf->Cell(25, 8, 'TOTAL:', 0, 0, 'R');
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(25, 8, '$' . number_format($total, 2), 1, 1, 'R', true);
} else {
    // Factura C - No discriminar IVA
    $total = floatval($factura['total']);
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(140, 5, '', 0, 0, 'R');
    $pdf->Cell(50, 5, '(IVA incluido en el precio)', 0, 1, 'R');
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(140, 8, '', 0, 0, 'R');
    $pdf->Cell(25, 8, 'TOTAL:', 0, 0, 'R');
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(25, 8, '$' . number_format($total, 2), 1, 1, 'R', true);
}

// =====================================================
// INFORMACIÓN DEL CAE
// =====================================================

$pdf->SetY($pdf->GetY() + 10);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(255, 255, 200);
$pdf->Cell(190, 7, 'COMPROBANTE AUTORIZADO', 0, 1, 'C', true);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 6, 'CAE (Cod. Autorizacion Electronico):', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(95, 6, $factura['cae'], 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 6, utf8_decode('Fecha de Vencimiento del CAE:'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 6, $vencimiento_cae, 0, 1, 'L');

// =====================================================
// CÓDIGO QR
// =====================================================

// Generar URL del código QR según ARCA
$qr_data = [
    'ver' => 1,
    'fecha' => date('Y-m-d', strtotime($factura['fecha_emision'])),
    'cuit' => (int) $config_facturacion['cuit'],
    'ptoVta' => (int) $factura['punto_venta'],
    'tipoCmp' => (int) $factura['tipo_comprobante'],
    'nroCmp' => (int) $factura['numero_comprobante'],
    'importe' => (float) $factura['total'],
    'moneda' => 'PES',
    'ctz' => 1,
    'tipoDocRec' => 99, // Ajustar según cliente
    'nroDocRec' => 0,
    'tipoCodAut' => 'E',
    'codAut' => (int) $factura['cae']
];

$qr_json = json_encode($qr_data);
$qr_base64 = base64_encode($qr_json);
$qr_url_afip = "https://www.afip.gob.ar/fe/qr/?p=" . urlencode($qr_base64);

// Generar imagen QR usando servicio externo
$qr_image_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_url_afip);

// Intentar mostrar código QR
$y_qr = $pdf->GetY() + 5;

try {
    // Descargar temporalmente la imagen QR
    $qr_temp_file = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
    $qr_image_content = @file_get_contents($qr_image_url);
    
    if ($qr_image_content !== false) {
        file_put_contents($qr_temp_file, $qr_image_content);
        $pdf->Image($qr_temp_file, 15, $y_qr, 35, 35, 'PNG');
        @unlink($qr_temp_file); // Limpiar archivo temporal
        
        // Texto explicativo del QR
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetXY(52, $y_qr);
        $pdf->MultiCell(140, 3, utf8_decode('Escaneá este código QR con tu celular para verificar la validez de esta factura en el sitio oficial de ARCA/AFIP'), 0, 'L');
    }
} catch (Exception $e) {
    // Si falla la descarga del QR, mostrar el enlace
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(15, $y_qr);
    $pdf->MultiCell(180, 3, utf8_decode('Verificá esta factura en: ' . $qr_url_afip), 0, 'L');
}

// =====================================================
// OUTPUT
// =====================================================

$nombre_archivo = "Factura_" . $letra_comprobante . "_" . $numero_comprobante . ".pdf";
$pdf->Output($nombre_archivo, "I");
?>

