<?php
session_start();

require_once "../conexion.php";
require_once "classes/FacturacionElectronica.php";

header('Content-Type: application/json');

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

$id_venta = 0;
if (isset($_POST['id_venta'])) {
    $id_venta = intval($_POST['id_venta']);
} elseif (isset($_GET['id_venta'])) {
    $id_venta = intval($_GET['id_venta']);
}

if ($id_venta <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de venta no proporcionado']);
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
    $facturacion = new \App\FacturacionElectronica($conexion);
    $preview = $facturacion->obtenerDatosPreviosFacturacion($id_venta, $override_data);

    echo json_encode([
        'success' => true,
        'data' => [
            'id_venta' => $id_venta,
            'total' => (float) ($preview['venta']['total'] ?? 0),
            'fecha_emision' => [
                'db' => $preview['fecha_emision'],
                'display' => $preview['fecha_emision_display'],
            ],
            'cliente' => $preview['cliente_factura'],
            'tipo_comprobante' => [
                'id' => $preview['tipo_comprobante'],
                'descripcion' => $preview['tipo_comprobante_desc'],
                'predeterminado_id' => $preview['tipo_comprobante_predeterminado'],
                'predeterminado_desc' => $preview['tipo_comprobante_predeterminado_desc'],
            ],
            'tipos_disponibles' => $preview['tipos_disponibles'],
        ],
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
