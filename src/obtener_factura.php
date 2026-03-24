<?php
session_start();
require_once "../conexion.php";

// Verificar sesión
if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

// Verificar que se haya enviado el ID de venta
if (!isset($_GET['id_venta']) || empty($_GET['id_venta'])) {
    echo json_encode(['success' => false, 'message' => 'ID de venta no proporcionado']);
    exit();
}

$id_venta = intval($_GET['id_venta']);

// Obtener datos de la factura
$query = mysqli_query($conexion,
    "SELECT f.*, t.descripcion as tipo_comprobante_desc, t.codigo
     FROM facturas_electronicas f
     LEFT JOIN tipos_comprobante t ON f.tipo_comprobante = t.id
     WHERE f.id_venta = $id_venta
     ORDER BY f.created_at DESC
     LIMIT 1");

if ($query && mysqli_num_rows($query) > 0) {
    $factura = mysqli_fetch_assoc($query);
    
    // Formatear fechas
    $factura['fecha_emision'] = date('d/m/Y', strtotime($factura['fecha_emision']));
    if ($factura['vencimiento_cae']) {
        $factura['vencimiento_cae'] = date('d/m/Y', strtotime($factura['vencimiento_cae']));
    }
    
    echo json_encode([
        'success' => true,
        'factura' => $factura
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No se encontró factura para esta venta'
    ]);
}

