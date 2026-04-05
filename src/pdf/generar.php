<?php
session_start();
if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado');
}

require_once '../../conexion.php';
require_once '../includes/mayorista_helpers.php';
require_once 'fpdf/fpdf.php';
require_once 'pdf_footer_helper.php';

$idVenta = isset($_GET['v']) ? (int) $_GET['v'] : 0;
$idCliente = isset($_GET['cl']) ? (int) $_GET['cl'] : 0;

if ($idVenta <= 0 || $idCliente <= 0) {
    exit('Parametros invalidos');
}

$config = mysqli_query($conexion, "SELECT * FROM configuracion LIMIT 1");
$empresa = $config ? mysqli_fetch_assoc($config) : array();

$camposVenta = array('v.*', 'c.nombre AS cliente_nombre', 'c.telefono', 'c.direccion', 'm.descripcion AS metodo_pago');
if (mayorista_column_exists($conexion, 'cliente', 'optica')) {
    $camposVenta[] = 'c.optica';
}
if (mayorista_column_exists($conexion, 'cliente', 'localidad')) {
    $camposVenta[] = 'c.localidad';
}
if (mayorista_column_exists($conexion, 'cliente', 'codigo_postal')) {
    $camposVenta[] = 'c.codigo_postal';
}
if (mayorista_column_exists($conexion, 'cliente', 'provincia')) {
    $camposVenta[] = 'c.provincia';
}
if (mayorista_column_exists($conexion, 'cliente', 'cuit')) {
    $camposVenta[] = 'c.cuit';
}
if (mayorista_column_exists($conexion, 'ventas', 'tipo_venta')) {
    $camposVenta[] = 'v.tipo_venta';
}
if (mayorista_column_exists($conexion, 'ventas', 'modo_despacho')) {
    $camposVenta[] = 'v.modo_despacho';
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
if (mayorista_column_exists($conexion, 'producto', 'marca')) {
    $camposDetalle[] = 'p.marca';
}
if (mayorista_column_exists($conexion, 'producto', 'modelo')) {
    $camposDetalle[] = 'p.modelo';
}
if (mayorista_column_exists($conexion, 'producto', 'color')) {
    $camposDetalle[] = 'p.color';
}
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

function remito_texto($value, $fallback = '')
{
    $value = trim((string) $value);
    return $value !== '' ? $value : $fallback;
}

function remito_marca($row)
{
    return trim((string) ($row['marca'] ?? ''));
}

function remito_modelo($row)
{
    $modelo = trim((string) ($row['modelo'] ?? ''));
    if ($modelo !== '') {
        return $modelo;
    }

    return trim((string) ($row['codigo'] ?? ''));
}

function remito_color($row)
{
    return trim((string) ($row['color'] ?? ''));
}

function remito_campo_box($pdf, $label, $value, $x, $y, $width, $height = 7.2)
{
    $pdf->SetFont('Arial', 'B', 7.7);
    $pdf->SetTextColor(103, 112, 122);
    $pdf->SetXY($x, $y);
    $pdf->Cell($width, 3.6, utf8_decode($label), 0, 1, 'L');
    $pdf->SetFillColor(238, 241, 245);
    $pdf->SetDrawColor(238, 241, 245);
    $pdf->Rect($x, $y + 5.3, $width, $height, 'F');
    $pdf->SetTextColor(42, 49, 57);
    $pdf->SetFont('Arial', 'B', 10.2);
    $pdf->SetXY($x + 2, $y + 6.6);
    $pdf->Cell($width - 4, $height - 1.8, utf8_decode((string) $value), 0, 1, 'L');
}

function remito_dibujar_header($pdf, $brandLogoPath, $ventaData)
{
    $left = 12;
    $rightColX = 104;
    $top = 14;

    if ($brandLogoPath && file_exists($brandLogoPath)) {
        $pdf->Image($brandLogoPath, 16, 12, 58, 0, 'PNG');
    }

    $pdf->SetFont('Arial', 'B', 22);
    $pdf->SetTextColor(55, 63, 73);
    $pdf->SetXY($rightColX, $top + 1);
    $pdf->Cell(92, 10, 'NOTA DE PEDIDO', 0, 1, 'R');

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(55, 63, 73);
    $pdf->SetXY($rightColX, $top + 13);
    $pdf->Cell(92, 6, date('d/m/Y', strtotime($ventaData['fecha'] ?? 'now')), 0, 1, 'R');

    $fieldWidth = 76;
    $fieldGap = 8;
    $leftY = 45;
    $rowGap = 14;
    remito_campo_box($pdf, 'OPTICA:', remito_texto($ventaData['optica'] ?? ''), $left, $leftY, $fieldWidth);
    remito_campo_box($pdf, 'DIRECCION:', remito_texto($ventaData['direccion'] ?? ''), $left + $fieldWidth + $fieldGap, $leftY, $fieldWidth);
    remito_campo_box($pdf, 'NOMBRE:', remito_texto($ventaData['cliente_nombre'] ?? ''), $left, $leftY + $rowGap, $fieldWidth);
    remito_campo_box($pdf, 'LOCALIDAD:', remito_texto($ventaData['localidad'] ?? ''), $left + $fieldWidth + $fieldGap, $leftY + $rowGap, $fieldWidth);
    remito_campo_box($pdf, 'CUIT:', remito_texto($ventaData['cuit'] ?? '', remito_texto($ventaData['dni'] ?? '')), $left, $leftY + ($rowGap * 2), $fieldWidth);
    remito_campo_box($pdf, 'CODIGO POSTAL:', remito_texto($ventaData['codigo_postal'] ?? ''), $left + $fieldWidth + $fieldGap, $leftY + ($rowGap * 2), $fieldWidth);
    remito_campo_box($pdf, 'TELEFONO:', remito_texto($ventaData['telefono'] ?? ''), $left, $leftY + ($rowGap * 3), $fieldWidth);
    remito_campo_box($pdf, 'PROVINCIA:', remito_texto($ventaData['provincia'] ?? ''), $left + $fieldWidth + $fieldGap, $leftY + ($rowGap * 3), $fieldWidth);
    $modoDespachoY = $leftY + ($rowGap * 4);
    remito_campo_box($pdf, 'MODO DESPACHO:', remito_texto($ventaData['modo_despacho'] ?? '', 'A convenir'), $left, $modoDespachoY, 160);

    return $modoDespachoY + 18;
}

function remito_dibujar_tabla_header($pdf, $y, $widths)
{
    $pdf->SetFillColor(45, 71, 82);
    $pdf->SetDrawColor(211, 218, 224);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 7.8);
    $pdf->SetXY(12, $y);
    $pdf->Cell($widths[0], 8, 'CANT.', 1, 0, 'C', true);
    $pdf->Cell($widths[1], 8, 'MARCA', 1, 0, 'C', true);
    $pdf->Cell($widths[2], 8, 'MODELO', 1, 0, 'C', true);
    $pdf->Cell($widths[3], 8, 'COLOR', 1, 0, 'C', true);
    $pdf->Cell($widths[4], 8, utf8_decode('DESCRIPCION'), 1, 0, 'C', true);
    $pdf->Cell($widths[5], 8, utf8_decode('PRECIO UNIT.'), 1, 0, 'C', true);
    $pdf->Cell($widths[6], 8, 'IMPORTE', 1, 1, 'C', true);
}

function remito_dibujar_fila($pdf, $y, $widths, $rowHeight, $fill, $item = null)
{
    $pdf->SetFont('Arial', '', 8.7);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->SetDrawColor(223, 229, 234);
    $pdf->SetFillColor($fill ? 242 : 255, $fill ? 245 : 255, $fill ? 248 : 255);
    $pdf->SetXY(12, $y);

    $cantidad = $item ? (string) $item['cantidad'] : '';
    $marca = $item ? remito_marca($item) : '';
    $modelo = $item ? remito_modelo($item) : '';
    $color = $item ? remito_color($item) : '';
    $descripcion = $item ? trim((string) ($item['descripcion'] ?? '')) : '';
    $precio = $item ? money_pdf($item['precio']) : '';
    $importe = $item ? money_pdf(((float) $item['cantidad']) * ((float) $item['precio'])) : '';

    $pdf->Cell($widths[0], $rowHeight, utf8_decode($cantidad), 1, 0, 'C', true);
    $pdf->Cell($widths[1], $rowHeight, utf8_decode(substr($marca, 0, 20)), 1, 0, 'L', true);
    $pdf->Cell($widths[2], $rowHeight, utf8_decode(substr($modelo, 0, 28)), 1, 0, 'L', true);
    $pdf->Cell($widths[3], $rowHeight, utf8_decode(substr($color, 0, 14)), 1, 0, 'L', true);
    $pdf->Cell($widths[4], $rowHeight, utf8_decode(substr($descripcion, 0, 22)), 1, 0, 'L', true);
    $pdf->Cell($widths[5], $rowHeight, utf8_decode($precio), 1, 0, 'R', true);
    $pdf->Cell($widths[6], $rowHeight, utf8_decode($importe), 1, 1, 'R', true);
}

$footerAssets = mayorista_pdf_footer_assets();

$pdf = new MayoristaBrandedPdf('P', 'mm', 'A4');
$pdf->setBrandFooter(
    $footerAssets['brand_logos'],
    $footerAssets['whatsapp_icon'],
    $footerAssets['whatsapp_text'],
    $footerAssets['instagram_icon'],
    $footerAssets['instagram_text']
);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, $pdf->getFooterHeight() + 6);
$pdf->AddPage();

$brandLogoPath = realpath(__DIR__ . '/../../assets/logo-pdf-clean-white.png');
$items = array();
$totalVenta = 0;
while ($row = mysqli_fetch_assoc($detalle)) {
    $items[] = $row;
    $totalVenta += ((float) $row['cantidad']) * ((float) $row['precio']);
}

$totalVenta = round($totalVenta, 2);
$totalCobrado = round((float) ($ventaData['abona'] ?? 0), 2);
$totalCc = round(isset($ventaData['monto_cc']) ? (float) $ventaData['monto_cc'] : (float) ($ventaData['resto'] ?? 0), 2);
$columnas = array(12, 28, 38, 20, 36, 26, 26);
$rowHeight = 6.6;
$minRowsLastPage = 10;
$maxRowsLastPage = 18;
$maxRowsRegularPage = 24;
$chunks = array();
$totalItems = count($items);

if ($totalItems <= $maxRowsLastPage) {
    $chunks[] = $items;
} else {
    $itemsForRegularPages = array_slice($items, 0, $totalItems - $maxRowsLastPage);
    if (!empty($itemsForRegularPages)) {
        $chunks = array_chunk($itemsForRegularPages, $maxRowsRegularPage);
    }
    $chunks[] = array_slice($items, $totalItems - $maxRowsLastPage);
}

if (empty($chunks)) {
    $chunks[] = array();
}

foreach ($chunks as $pageIndex => $pageItems) {
    if ($pageIndex > 0) {
        $pdf->AddPage();
    }

    $tableHeaderY = remito_dibujar_header($pdf, $brandLogoPath, $ventaData);
    remito_dibujar_tabla_header($pdf, $tableHeaderY, $columnas);
    $y = $tableHeaderY + 8;

    foreach ($pageItems as $itemIndex => $item) {
        remito_dibujar_fila($pdf, $y, $columnas, $rowHeight, ($itemIndex % 2) === 1, $item);
        $y += $rowHeight;
    }

    $isLastPage = $pageIndex === (count($chunks) - 1);
    if ($isLastPage) {
        $rowsToRender = max($minRowsLastPage, count($pageItems));
        $faltantes = $rowsToRender - count($pageItems);
        for ($i = 0; $i < $faltantes; $i++) {
            remito_dibujar_fila($pdf, $y, $columnas, $rowHeight, ((count($pageItems) + $i) % 2) === 1, null);
            $y += $rowHeight;
        }

        $summaryY = $y + 6;
        $pdf->SetTextColor(55, 63, 73);
        $pdf->SetFont('Arial', '', 10.5);
        $pdf->SetXY(116, $summaryY);
        $pdf->Cell(42, 6, 'Total venta', 0, 0, 'R');
        $pdf->SetFont('Arial', 'B', 10.5);
        $pdf->Cell(40, 6, money_pdf($totalVenta), 0, 1, 'R');

        $pdf->SetFont('Arial', '', 10.5);
        $pdf->SetXY(116, $summaryY + 7);
        $pdf->Cell(42, 6, 'Total cobrado', 0, 0, 'R');
        $pdf->SetFont('Arial', 'B', 10.5);
        $pdf->Cell(40, 6, money_pdf($totalCobrado), 0, 1, 'R');

        $pdf->SetFont('Arial', '', 10.5);
        $pdf->SetXY(108, $summaryY + 14);
        $pdf->Cell(50, 6, 'Total a cobrar (CC)', 0, 0, 'R');
        $pdf->SetFont('Arial', 'B', 10.5);
        $pdf->Cell(40, 6, money_pdf($totalCc), 0, 1, 'R');

        $footerTopY = $pdf->GetPageHeight() - $pdf->getFooterHeight();
        $aviso = utf8_decode('Este documento no es valido como factura.');
        $avisoWidth = $pdf->GetStringWidth($aviso);
        $pdf->SetFont('Arial', 'I', 8.5);
        $pdf->SetTextColor(150, 156, 167);
        $pdf->Text(($pdf->GetPageWidth() - $avisoWidth) / 2, $footerTopY - 10, $aviso);
    }
}

$pdf->Output('I', 'venta-' . $idVenta . '.pdf');
