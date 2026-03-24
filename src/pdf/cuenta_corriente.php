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
mayorista_requiere_permiso($conexion, $id_user, array('cuenta_corriente', 'clientes'));

$idCliente = isset($_GET['cliente']) ? (int) $_GET['cliente'] : 0;
if ($idCliente <= 0) {
    exit('Cliente invalido');
}

$clienteQuery = mysqli_query($conexion, "SELECT * FROM cliente WHERE idcliente = $idCliente LIMIT 1");
$cliente = $clienteQuery ? mysqli_fetch_assoc($clienteQuery) : null;
if (!$cliente) {
    exit('Cliente no encontrado');
}

$cuenta = mayorista_obtener_cuenta_corriente($conexion, $idCliente);
$movimientos = mysqli_query(
    $conexion,
    "SELECT m.*, u.nombre AS usuario_nombre
     FROM movimientos_cc m
     LEFT JOIN usuario u ON m.id_usuario = u.idusuario
     WHERE m.id_cuenta_corriente = " . (int) $cuenta['id'] . "
     ORDER BY m.fecha DESC"
);

function cc_money($amount)
{
    return '$ ' . number_format((float) $amount, 2, ',', '.');
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(12, 12, 12);
$pdf->AddPage();

$pdf->SetFillColor(15, 23, 42);
$pdf->Rect(12, 12, 186, 24, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetXY(16, 18);
$pdf->Cell(0, 8, utf8_decode('Estado de cuenta corriente'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->SetX(16);
$pdf->Cell(0, 6, utf8_decode('Cliente: ' . $cliente['nombre']), 0, 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 8, 'Saldo actual', 1, 0, 'C');
$pdf->Cell(60, 8, 'Limite de credito', 1, 0, 'C');
$pdf->Cell(66, 8, 'Fecha emision', 1, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 8, cc_money($cuenta['saldo_actual']), 1, 0, 'C');
$pdf->Cell(60, 8, cc_money($cuenta['limite_credito']), 1, 0, 'C');
$pdf->Cell(66, 8, date('d/m/Y H:i'), 1, 1, 'C');

$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(226, 232, 240);
$pdf->Cell(34, 8, 'Fecha', 1, 0, 'C', true);
$pdf->Cell(22, 8, 'Tipo', 1, 0, 'C', true);
$pdf->Cell(80, 8, utf8_decode('Descripcion'), 1, 0, 'L', true);
$pdf->Cell(25, 8, 'Monto', 1, 0, 'R', true);
$pdf->Cell(25, 8, 'Usuario', 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 8);
if ($movimientos && mysqli_num_rows($movimientos) > 0) {
    while ($mov = mysqli_fetch_assoc($movimientos)) {
        $pdf->Cell(34, 8, date('d/m/Y H:i', strtotime($mov['fecha'])), 1, 0, 'C');
        $pdf->Cell(22, 8, ucfirst($mov['tipo']), 1, 0, 'C');
        $pdf->Cell(80, 8, utf8_decode(substr($mov['descripcion'], 0, 55)), 1, 0, 'L');
        $pdf->Cell(25, 8, cc_money($mov['monto']), 1, 0, 'R');
        $pdf->Cell(25, 8, utf8_decode(substr($mov['usuario_nombre'] ?: '-', 0, 18)), 1, 1, 'L');
    }
} else {
    $pdf->Cell(186, 10, utf8_decode('No hay movimientos registrados para este cliente.'), 1, 1, 'C');
}

$pdf->Output('I', 'cuenta-corriente-' . $idCliente . '.pdf');
