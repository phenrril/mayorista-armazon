<?php
session_start();
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";

header('Content-Type: application/json; charset=utf-8');

// Verificar sesión
if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

if (!($conexion instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo conectar a la base de datos']);
    exit();
}

$id_user = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $id_user, array('ventas'))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tenes permisos para consultar facturas']);
    exit();
}

// Verificar que se haya enviado el ID de venta
if (!isset($_GET['id_venta']) || empty($_GET['id_venta'])) {
    echo json_encode(['success' => false, 'message' => 'ID de venta no proporcionado']);
    exit();
}

$id_venta = intval($_GET['id_venta']);
$ventaQuery = mysqli_query($conexion, "SELECT id FROM ventas WHERE id = $id_venta LIMIT 1");
if (!$ventaQuery || mysqli_num_rows($ventaQuery) === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'La venta indicada no existe']);
    exit();
}

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

