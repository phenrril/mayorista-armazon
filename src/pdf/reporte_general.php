<?php
session_start();
if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado');
}

require_once '../../conexion.php';
require_once '../includes/mayorista_helpers.php';
require_once 'fpdf/fpdf.php';

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('reportes', 'reporte'));

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$tipo = $_GET['tipo'] ?? 'todas';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $desde = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $hasta = date('Y-m-d');
}

if ($desde > $hasta) {
    $tmp = $desde;
    $desde = $hasta;
    $hasta = $tmp;
}

$hasTipoVenta = mayorista_column_exists($conexion, 'ventas', 'tipo_venta');
$hasCcTables = mayorista_table_exists($conexion, 'cuenta_corriente') && mayorista_table_exists($conexion, 'movimientos_cc');
$hasEgresos = mayorista_table_exists($conexion, 'egresos');

$whereVentas = "DATE(v.fecha) BETWEEN '$desde' AND '$hasta'";
if ($hasTipoVenta && in_array($tipo, array('mayorista', 'minorista'), true)) {
    $whereVentas .= " AND v.tipo_venta = '" . mysqli_real_escape_string($conexion, $tipo) . "'";
}

$ventasResumen = mysqli_fetch_assoc(mysqli_query(
    $conexion,
    "SELECT COUNT(*) AS operaciones, IFNULL(SUM(v.total),0) AS total, IFNULL(SUM(v.abona),0) AS cobrado
     FROM ventas v
     WHERE $whereVentas"
));

$ccResumen = $hasCcTables
    ? mysqli_fetch_assoc(mysqli_query(
        $conexion,
        "SELECT
            IFNULL(SUM(CASE WHEN m.tipo = 'cargo' THEN m.monto ELSE 0 END),0) AS cargos,
            IFNULL(SUM(CASE WHEN m.tipo = 'pago' THEN m.monto ELSE 0 END),0) AS pagos
         FROM movimientos_cc m
         WHERE DATE(m.fecha) BETWEEN '$desde' AND '$hasta'"
    ))
    : array('cargos' => 0, 'pagos' => 0);

$ingresos = mysqli_fetch_assoc(mysqli_query(
    $conexion,
    "SELECT IFNULL(SUM(ingresos),0) AS total
     FROM ingresos
     WHERE DATE(fecha) BETWEEN '$desde' AND '$hasta'"
));

$egresos = $hasEgresos
    ? mysqli_fetch_assoc(mysqli_query(
        $conexion,
        "SELECT IFNULL(SUM(egresos),0) AS total
         FROM egresos
         WHERE DATE(fecha) BETWEEN '$desde' AND '$hasta'"
    ))
    : array('total' => 0);

$productosReporte = mysqli_query(
    $conexion,
    "SELECT p.codigo, p.descripcion, p.marca, p.modelo, p.color, p.tipo, SUM(d.cantidad) AS vendidos, SUM(d.cantidad * d.precio) AS monto
     FROM detalle_venta d
     INNER JOIN ventas v ON d.id_venta = v.id
     INNER JOIN producto p ON d.id_producto = p.codproducto
     WHERE $whereVentas
     GROUP BY d.id_producto, p.codigo, p.descripcion, p.marca, p.modelo, p.color, p.tipo
     ORDER BY vendidos DESC
     LIMIT 8"
);

$clientesTop = mysqli_query(
    $conexion,
    "SELECT c.nombre, COUNT(v.id) AS operaciones, SUM(v.total) AS volumen
     FROM ventas v
     INNER JOIN cliente c ON v.id_cliente = c.idcliente
     WHERE $whereVentas
     GROUP BY v.id_cliente, c.nombre
     ORDER BY volumen DESC
     LIMIT 8"
);

$clientesMora = $hasCcTables
    ? mysqli_query(
        $conexion,
        "SELECT c.nombre, cc.saldo_actual, cc.limite_credito
         FROM cuenta_corriente cc
         INNER JOIN cliente c ON cc.id_cliente = c.idcliente
         WHERE cc.saldo_actual > 0
         ORDER BY cc.saldo_actual DESC
         LIMIT 8"
    )
    : false;

function report_money($amount)
{
    return '$ ' . number_format((float) $amount, 2, ',', '.');
}

function report_require_space($pdf, $height = 24)
{
    if ($pdf->GetY() + $height > 270) {
        $pdf->AddPage();
    }
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(12, 12, 12);
$pdf->AddPage();

$pdf->SetFillColor(15, 23, 42);
$pdf->Rect(12, 12, 186, 24, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 15);
$pdf->SetXY(16, 18);
$pdf->Cell(0, 7, utf8_decode('Reporte general'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->SetX(16);
$pdf->Cell(0, 6, utf8_decode("Periodo: $desde a $hasta | Tipo: $tipo"), 0, 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(46, 8, 'Ventas', 1, 0, 'C');
$pdf->Cell(46, 8, 'Cobrado', 1, 0, 'C');
$pdf->Cell(46, 8, 'Cargos CC', 1, 0, 'C');
$pdf->Cell(48, 8, 'Pagos CC', 1, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(46, 8, report_money($ventasResumen['total']), 1, 0, 'C');
$pdf->Cell(46, 8, report_money($ventasResumen['cobrado']), 1, 0, 'C');
$pdf->Cell(46, 8, report_money($ccResumen['cargos']), 1, 0, 'C');
$pdf->Cell(48, 8, report_money($ccResumen['pagos']), 1, 1, 'C');

$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(93, 8, 'Ingresos', 1, 0, 'C');
$pdf->Cell(93, 8, 'Egresos', 1, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(93, 8, report_money($ingresos['total']), 1, 0, 'C');
$pdf->Cell(93, 8, report_money($egresos['total']), 1, 1, 'C');
$pdf->Cell(186, 8, utf8_decode('Resultado operativo: ') . report_money((float) $ingresos['total'] - (float) $egresos['total']), 1, 1, 'C');

$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, utf8_decode('Productos'), 0, 1);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(120, 8, utf8_decode('Producto'), 1, 0, 'L');
$pdf->Cell(28, 8, 'Vendidos', 1, 0, 'C');
$pdf->Cell(38, 8, 'Monto', 1, 1, 'R');
$pdf->SetFont('Arial', '', 9);
while ($row = mysqli_fetch_assoc($productosReporte)) {
    report_require_space($pdf, 10);
    $pdf->Cell(120, 8, utf8_decode(substr(mayorista_nombre_producto($row), 0, 60)), 1, 0, 'L');
    $pdf->Cell(28, 8, (float) $row['vendidos'], 1, 0, 'C');
    $pdf->Cell(38, 8, report_money($row['monto']), 1, 1, 'R');
}

report_require_space($pdf, 26);
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, utf8_decode('Clientes top'), 0, 1);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(96, 8, 'Cliente', 1, 0, 'L');
$pdf->Cell(30, 8, 'Oper.', 1, 0, 'C');
$pdf->Cell(60, 8, 'Volumen', 1, 1, 'R');
$pdf->SetFont('Arial', '', 9);
if ($clientesTop && mysqli_num_rows($clientesTop) > 0) {
    while ($row = mysqli_fetch_assoc($clientesTop)) {
        report_require_space($pdf, 10);
        $pdf->Cell(96, 8, utf8_decode(substr($row['nombre'], 0, 48)), 1, 0, 'L');
        $pdf->Cell(30, 8, (int) $row['operaciones'], 1, 0, 'C');
        $pdf->Cell(60, 8, report_money($row['volumen']), 1, 1, 'R');
    }
} else {
    $pdf->Cell(186, 8, 'Sin datos para el periodo seleccionado', 1, 1, 'C');
}

report_require_space($pdf, 26);
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, utf8_decode('Clientes en mora'), 0, 1);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(86, 8, 'Cliente', 1, 0, 'L');
$pdf->Cell(50, 8, 'Saldo', 1, 0, 'R');
$pdf->Cell(50, 8, utf8_decode('Límite'), 1, 1, 'R');
$pdf->SetFont('Arial', '', 9);
if ($clientesMora && mysqli_num_rows($clientesMora) > 0) {
    while ($row = mysqli_fetch_assoc($clientesMora)) {
        report_require_space($pdf, 10);
        $pdf->Cell(86, 8, utf8_decode(substr($row['nombre'], 0, 42)), 1, 0, 'L');
        $pdf->Cell(50, 8, report_money($row['saldo_actual']), 1, 0, 'R');
        $pdf->Cell(50, 8, report_money($row['limite_credito']), 1, 1, 'R');
    }
} else {
    $pdf->Cell(186, 8, 'Sin deuda pendiente registrada', 1, 1, 'C');
}

$pdf->Output('I', 'reporte-general.pdf');
