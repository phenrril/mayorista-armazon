<?php
session_start();
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";

header('Content-Type: application/json; charset=utf-8');

function migracion_movimientos_cc_json($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function migracion_movimientos_cc_ejecutar($conexion, $sql, &$log)
{
    $ok = mysqli_query($conexion, $sql);
    if (!$ok) {
        throw new Exception(mysqli_error($conexion));
    }

    $log[] = $sql;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    migracion_movimientos_cc_json(array('success' => false, 'message' => 'Metodo no permitido.'), 405);
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    migracion_movimientos_cc_json(array('success' => false, 'message' => 'No autorizado.'), 403);
}

if (!($conexion instanceof mysqli)) {
    migracion_movimientos_cc_json(array('success' => false, 'message' => 'No se pudo establecer la conexion a la base de datos.'), 500);
}

$idUser = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $idUser, array('configuracion'))) {
    migracion_movimientos_cc_json(array('success' => false, 'message' => 'No tenes permisos para ejecutar esta migracion.'), 403);
}

$token = $_POST['csrf_token'] ?? '';
if (!mayorista_validar_token_migracion_movimientos_cc($token)) {
    migracion_movimientos_cc_json(array('success' => false, 'message' => 'Token de seguridad invalido o vencido.'), 403);
}

if (!mayorista_table_exists($conexion, 'movimientos_cc')) {
    migracion_movimientos_cc_json(array('success' => false, 'message' => 'La tabla movimientos_cc no existe en esta base.'), 400);
}

if (mayorista_schema_movimientos_cc_metodos_listo($conexion)) {
    mayorista_marcar_migracion_movimientos_cc_metodos_ejecutada($conexion);
    mayorista_invalidar_token_migracion_movimientos_cc();
    migracion_movimientos_cc_json(array(
        'success' => true,
        'message' => 'La migracion ya estaba aplicada.',
        'already_applied' => true,
    ));
}

$log = array();

try {
    if (!mayorista_column_exists($conexion, 'movimientos_cc', 'id_metodo')) {
        migracion_movimientos_cc_ejecutar(
            $conexion,
            "ALTER TABLE movimientos_cc ADD COLUMN id_metodo INT NULL DEFAULT NULL AFTER descripcion",
            $log
        );
    }

    if (!mayorista_column_exists($conexion, 'movimientos_cc', 'origen_tipo')) {
        migracion_movimientos_cc_ejecutar(
            $conexion,
            "ALTER TABLE movimientos_cc ADD COLUMN origen_tipo VARCHAR(50) NULL DEFAULT NULL AFTER id_metodo",
            $log
        );
    }

    if (!mayorista_column_exists($conexion, 'movimientos_cc', 'origen_id')) {
        migracion_movimientos_cc_ejecutar(
            $conexion,
            "ALTER TABLE movimientos_cc ADD COLUMN origen_id INT NULL DEFAULT NULL AFTER origen_tipo",
            $log
        );
    }

    migracion_movimientos_cc_ejecutar(
        $conexion,
        "UPDATE movimientos_cc
         SET origen_tipo = 'venta',
             origen_id = id_venta
         WHERE id_venta IS NOT NULL
         AND id_venta > 0
         AND (origen_tipo IS NULL OR origen_tipo = '' OR origen_id IS NULL OR origen_id = 0)",
        $log
    );

    if (mayorista_table_exists($conexion, 'ventas') && mayorista_column_exists($conexion, 'ventas', 'id_metodo')) {
        migracion_movimientos_cc_ejecutar(
            $conexion,
            "UPDATE movimientos_cc m
             INNER JOIN ventas v ON v.id = m.id_venta
             SET m.id_metodo = v.id_metodo
             WHERE m.id_venta IS NOT NULL
             AND m.id_venta > 0
             AND (m.id_metodo IS NULL OR m.id_metodo = 0)",
            $log
        );
    }

    if (!mayorista_schema_movimientos_cc_metodos_listo($conexion)) {
        throw new Exception('La migracion termino pero el esquema aun no quedo completo.');
    }

    mayorista_marcar_migracion_movimientos_cc_metodos_ejecutada($conexion);
    mayorista_invalidar_token_migracion_movimientos_cc();

    migracion_movimientos_cc_json(array(
        'success' => true,
        'message' => 'Migracion aplicada correctamente.',
        'log' => $log,
    ));
} catch (Exception $e) {
    migracion_movimientos_cc_json(array(
        'success' => false,
        'message' => 'No se pudo completar la migracion: ' . $e->getMessage(),
        'log' => $log,
    ), 500);
}
