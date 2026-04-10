<?php
session_start();
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";

header('Content-Type: application/json; charset=utf-8');

function migracion_descuentos_json($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function migracion_descuentos_ejecutar($conexion, $sql, &$log)
{
    $ok = mysqli_query($conexion, $sql);
    if (!$ok) {
        throw new Exception(mysqli_error($conexion));
    }

    $log[] = $sql;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    migracion_descuentos_json(array('success' => false, 'message' => 'Metodo no permitido.'), 405);
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    migracion_descuentos_json(array('success' => false, 'message' => 'No autorizado.'), 403);
}

if (!($conexion instanceof mysqli)) {
    migracion_descuentos_json(array('success' => false, 'message' => 'No se pudo establecer la conexion a la base de datos.'), 500);
}

$idUser = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $idUser, array('configuracion'))) {
    migracion_descuentos_json(array('success' => false, 'message' => 'No tenes permisos para ejecutar esta migracion.'), 403);
}

$token = $_POST['csrf_token'] ?? '';
if (!mayorista_validar_token_migracion_descuentos_venta($token)) {
    migracion_descuentos_json(array('success' => false, 'message' => 'Token de seguridad invalido o vencido.'), 403);
}

if (mayorista_schema_descuentos_venta_listo($conexion)) {
    mayorista_marcar_migracion_descuentos_venta_ejecutada($conexion);
    mayorista_invalidar_token_migracion_descuentos_venta();
    migracion_descuentos_json(array(
        'success' => true,
        'message' => 'La migracion ya estaba aplicada.',
        'already_applied' => true,
    ));
}

$log = array();

try {
    if (!mayorista_column_exists($conexion, 'ventas', 'descuento_porcentaje')) {
        migracion_descuentos_ejecutar(
            $conexion,
            "ALTER TABLE ventas ADD COLUMN descuento_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER total",
            $log
        );
    }

    if (!mayorista_schema_descuentos_venta_listo($conexion)) {
        throw new Exception('La migracion termino pero el esquema aun no quedo completo.');
    }

    mayorista_marcar_migracion_descuentos_venta_ejecutada($conexion);
    mayorista_invalidar_token_migracion_descuentos_venta();

    migracion_descuentos_json(array(
        'success' => true,
        'message' => 'Migracion aplicada correctamente.',
        'log' => $log,
    ));
} catch (Exception $e) {
    migracion_descuentos_json(array(
        'success' => false,
        'message' => 'No se pudo completar la migracion: ' . $e->getMessage(),
        'log' => $log,
    ), 500);
}
