<?php
require "../conexion.php";
require_once "includes/mayorista_helpers.php";
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Metodo no permitido.'));
    exit();
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => 'Sesion no valida.'));
    exit();
}

if (!($conexion instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'No se pudo establecer conexion a la base de datos.'));
    exit();
}

$idUser = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $idUser, array('reportes', 'reporte', 'tesoreria'))) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => 'No tenes permisos para registrar movimientos manuales.'));
    exit();
}

try {
    mayorista_registrar_movimiento_tesoreria(
        $conexion,
        $_POST['tipo'] ?? '',
        $_POST['valor'] ?? '',
        $_POST['descripcion'] ?? '',
        $_POST['fecha'] ?? date('Y-m-d'),
        0,
        (int) ($_POST['id_metodo'] ?? 1)
    );

    echo json_encode(array(
        'success' => true,
        'message' => (($_POST['tipo'] ?? '') === 'egreso' ? 'Egreso' : 'Ingreso') . ' agregado correctamente.'
    ));
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(array(
        'success' => false,
        'message' => $e->getMessage()
    ));
}