<?php
session_start();
if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado');
}

require_once '../../conexion.php';
require_once '../includes/mayorista_helpers.php';
require_once 'fpdf/fpdf.php';

$idVenta = isset($_GET['v']) ? (int) $_GET['v'] : 0;
$idCliente = isset($_GET['cl']) ? (int) $_GET['cl'] : 0;

if ($idVenta <= 0 || $idCliente <= 0) {
    exit('Parametros invalidos');
}

$config = mysqli_query($conexion, "SELECT * FROM configuracion LIMIT 1");
$empresa = $config ? mysqli_fetch_assoc($config) : array();

$camposVenta = array('v.*', 'c.nombre AS cliente_nombre', 'c.telefono', 'c.direccion', 'm.descripcion AS metodo_pago');
if (mayorista_column_exists($conexion, 'ventas', 'tipo_venta')) {
    $camposVenta[] = 'v.tipo_venta';
}
if (mayorista_column_exists($conexion, 'ventas', 'monto_cc')) {
    $camposVenta[] = 'v.monto_cc';
}
if (mayorista_column_exists($conexion, 'ventas', 'saldo_cc_cliente')) {
    $camposVenta[] = 'v.saldo_cc_cliente';
}

$venta = mysqli_query(
    $conexion,
    "SELECT " . implode(', ', $camposVenta) . "
     FROM ventas v
     INNER JOIN cliente c ON v.id_cliente = c.idcliente
     LEFT JOIN metodos m ON v.id_metodo = m.id
     WHERE v.id = $idVenta AND c.idcliente = $idCliente
     LIMIT 1"
);

$ventaData = $venta ? mysqli_fetch_assoc($venta) : null;
if (!$ventaData) {
    exit('Venta no encontrada');
}

$camposDetalle = array('d.cantidad', 'd.precio', 'd.precio_original', 'p.codigo', 'p.descripcion');
if (mayorista_column_exists($conexion, 'detalle_venta', 'precio_personalizado')) {
    $camposDetalle[] = 'd.precio_personalizado';
}
if (mayorista_column_exists($conexion, 'detalle_venta', 'tipo_precio')) {
    $camposDetalle[] = 'd.tipo_precio';
}

$detalle = mysqli_query(
    $conexion,
    "SELECT " . implode(', ', $camposDetalle) . "
     FROM detalle_venta d
     INNER JOIN producto p ON d.id_producto = p.codproducto
     WHERE d.id_venta = $idVenta
     ORDER BY d.id ASC"
);

function money_pdf($amount)
{
    return '$ ' . number_format((float) $amount, 2, ',', '.');
}

function printLabel($pdf, $label, $value, $x, $y, $width = 84)
{
    $pdf->SetXY($x, $y);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(28, 6, utf8_decode($label), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell($width, 6, utf8_decode((string) $value), 0, 1, 'L');
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(12, 12, 12);
$pdf->AddPage();

$pdf->SetFillColor(30, 41, 59);
$pdf->Rect(12, 12, 186, 26, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetXY(16, 18);
$pdf->Cell(120, 8, utf8_decode($empresa['nombre'] ?? 'Sistema Mayorista'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->SetX(16);
$pdf->Cell(120, 6, utf8_decode('Comprobante de venta'), 0, 1);
$pdf->SetTextColor(0, 0, 0);

printLabel($pdf, 'Fecha', date('d/m/Y H:i', strtotime($ventaData['fecha'])), 14, 44);
printLabel($pdf, 'Venta', '#' . $ventaData['id'], 110, 44, 40);
printLabel($pdf, 'Tipo', ucfirst($ventaData['tipo_venta'] ?? 'minorista'), 14, 51);
printLabel($pdf, 'Metodo', $ventaData['metodo_pago'] ?: 'Sin definir', 110, 51, 40);

$pdf->Ln(12);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(186, 8, utf8_decode('Cliente'), 0, 1);
$pdf->SetFont('Arial', '', 9);
$pdf->SetFillColor(248, 250, 252);
$pdf->Cell(70, 8, utf8_decode($ventaData['cliente_nombre']), 1, 0, 'L', true);
$pdf->Cell(46, 8, utf8_decode($ventaData['telefono'] ?: '-'), 1, 0, 'L', true);
$pdf->Cell(70, 8, utf8_decode($ventaData['direccion'] ?: '-'), 1, 1, 'L', true);

$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(186, 8, utf8_decode('Detalle del pedido'), 0, 1);
$pdf->SetFillColor(226, 232, 240);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(24, 8, 'Codigo', 1, 0, 'C', true);
$pdf->Cell(70, 8, utf8_decode('Descripcion'), 1, 0, 'L', true);
$pdf->Cell(18, 8, 'Cant.', 1, 0, 'C', true);
$pdf->Cell(28, 8, 'Base', 1, 0, 'R', true);
$pdf->Cell(22, 8, 'Venta', 1, 0, 'R', true);
$pdf->Cell(24, 8, 'Subtotal', 1, 1, 'R', true);

$pdf->SetFont('Arial', '', 9);
$total = 0;
while ($row = mysqli_fetch_assoc($detalle)) {
    $subtotal = (float) $row['cantidad'] * (float) $row['precio'];
    $total += $subtotal;

    $pdf->Cell(24, 8, utf8_decode($row['codigo']), 1, 0, 'C');
    $pdf->Cell(70, 8, utf8_decode(substr($row['descripcion'], 0, 40)), 1, 0, 'L');
    $pdf->Cell(18, 8, $row['cantidad'], 1, 0, 'C');
    $pdf->Cell(28, 8, money_pdf($row['precio_original']), 1, 0, 'R');
    $pdf->Cell(22, 8, money_pdf($row['precio']), 1, 0, 'R');
    $pdf->Cell(24, 8, money_pdf($subtotal), 1, 1, 'R');
}

$abona = (float) $ventaData['abona'];
$montoCc = isset($ventaData['monto_cc']) ? (float) $ventaData['monto_cc'] : (float) $ventaData['resto'];
$saldoCc = isset($ventaData['saldo_cc_cliente']) ? (float) $ventaData['saldo_cc_cliente'] : 0;

$pdf->Ln(8);
$pdf->SetX(118);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(38, 8, 'Total', 0, 0, 'R');
$pdf->Cell(30, 8, money_pdf($total), 0, 1, 'R');
$pdf->SetX(118);
$pdf->Cell(38, 8, 'Abona ahora', 0, 0, 'R');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(30, 8, money_pdf($abona), 0, 1, 'R');
$pdf->SetX(118);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(38, 8, 'Carga a CC', 0, 0, 'R');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(30, 8, money_pdf($montoCc), 0, 1, 'R');

if ($montoCc > 0) {
    $pdf->SetX(108);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(48, 8, 'Saldo CC cliente', 0, 0, 'R');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 8, money_pdf($saldoCc), 0, 1, 'R');
}

$pdf->SetY(-28);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(0, 5, utf8_decode('Documento generado por el sistema mayorista de armazones.'), 0, 1, 'C');
$pdf->Cell(0, 5, utf8_decode('Conserve este comprobante para el seguimiento de pagos y cuenta corriente.'), 0, 1, 'C');

$pdf->Output('I', 'venta-' . $idVenta . '.pdf');
