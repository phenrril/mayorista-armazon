<?php
session_start();
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";

header('Content-Type: application/json; charset=utf-8');

function migracion_json($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function migracion_ejecutar($conexion, $sql, &$log)
{
    $ok = mysqli_query($conexion, $sql);
    if (!$ok) {
        throw new Exception(mysqli_error($conexion));
    }

    $log[] = $sql;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    migracion_json(array('success' => false, 'message' => 'Metodo no permitido.'), 405);
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    migracion_json(array('success' => false, 'message' => 'No autorizado.'), 403);
}

if (!($conexion instanceof mysqli)) {
    migracion_json(array('success' => false, 'message' => 'No se pudo establecer la conexion a la base de datos.'), 500);
}

$idUser = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $idUser, array('configuracion'))) {
    migracion_json(array('success' => false, 'message' => 'No tenes permisos para ejecutar esta migracion.'), 403);
}

$token = $_POST['csrf_token'] ?? '';
if (!mayorista_validar_token_migracion_remito($token)) {
    migracion_json(array('success' => false, 'message' => 'Token de seguridad invalido o vencido.'), 403);
}

if (mayorista_schema_remito_productos_listo($conexion)) {
    mayorista_marcar_migracion_remito_productos_ejecutada($conexion);
    mayorista_invalidar_token_migracion_remito();
    migracion_json(array(
        'success' => true,
        'message' => 'La migracion ya estaba aplicada.',
        'already_applied' => true,
    ));
}

$log = array();

try {
    if (!mayorista_column_exists($conexion, 'cliente', 'optica')) {
        migracion_ejecutar($conexion, "ALTER TABLE cliente ADD COLUMN optica VARCHAR(150) NULL AFTER nombre", $log);
    }
    if (!mayorista_column_exists($conexion, 'cliente', 'localidad')) {
        migracion_ejecutar($conexion, "ALTER TABLE cliente ADD COLUMN localidad VARCHAR(120) NULL AFTER direccion", $log);
    }
    if (!mayorista_column_exists($conexion, 'cliente', 'codigo_postal')) {
        migracion_ejecutar($conexion, "ALTER TABLE cliente ADD COLUMN codigo_postal VARCHAR(20) NULL AFTER localidad", $log);
    }
    if (!mayorista_column_exists($conexion, 'cliente', 'provincia')) {
        migracion_ejecutar($conexion, "ALTER TABLE cliente ADD COLUMN provincia VARCHAR(120) NULL AFTER codigo_postal", $log);
    }
    if (!mayorista_column_exists($conexion, 'cliente', 'cuit')) {
        migracion_ejecutar($conexion, "ALTER TABLE cliente ADD COLUMN cuit VARCHAR(13) NULL AFTER dni", $log);
    }
    if (!mayorista_column_exists($conexion, 'cliente', 'condicion_iva')) {
        migracion_ejecutar($conexion, "ALTER TABLE cliente ADD COLUMN condicion_iva VARCHAR(50) NOT NULL DEFAULT 'Consumidor Final' AFTER cuit", $log);
    }
    if (!mayorista_column_exists($conexion, 'cliente', 'tipo_documento')) {
        migracion_ejecutar($conexion, "ALTER TABLE cliente ADD COLUMN tipo_documento INT NOT NULL DEFAULT 96 AFTER condicion_iva", $log);
    }

    if (!mayorista_column_exists($conexion, 'producto', 'modelo')) {
        migracion_ejecutar($conexion, "ALTER TABLE producto ADD COLUMN modelo VARCHAR(120) NULL AFTER marca", $log);
    }
    if (!mayorista_column_exists($conexion, 'producto', 'color')) {
        migracion_ejecutar($conexion, "ALTER TABLE producto ADD COLUMN color VARCHAR(120) NULL AFTER modelo", $log);
    }

    $tipoMaterialType = mayorista_obtener_tipo_columna($conexion, 'producto', 'tipo_material');
    if (!mayorista_column_exists($conexion, 'producto', 'tipo_material')) {
        migracion_ejecutar(
            $conexion,
            "ALTER TABLE producto ADD COLUMN tipo_material ENUM('Acetato', 'Tr90', 'Metal', 'Inyeccion') NULL AFTER color",
            $log
        );
    } elseif (strpos($tipoMaterialType, 'acetato') === false || strpos($tipoMaterialType, 'inyeccion') === false) {
        migracion_ejecutar(
            $conexion,
            "ALTER TABLE producto MODIFY COLUMN tipo_material ENUM('Acetato', 'Tr90', 'Metal', 'Inyeccion') NULL",
            $log
        );
    }

    if (!mayorista_column_exists($conexion, 'producto', 'tipo')) {
        migracion_ejecutar(
            $conexion,
            "ALTER TABLE producto ADD COLUMN tipo ENUM('receta', 'sol', 'clip-on') NOT NULL DEFAULT 'receta' AFTER tipo_material",
            $log
        );
    } else {
        $tipoColumna = mayorista_obtener_tipo_columna($conexion, 'producto', 'tipo');
        if (strpos($tipoColumna, 'receta') === false || strpos($tipoColumna, 'clip-on') === false || strpos($tipoColumna, 'sol') === false) {
            migracion_ejecutar(
                $conexion,
                "ALTER TABLE producto MODIFY COLUMN tipo ENUM('armazon', 'accesorio', 'receta', 'sol', 'clip-on') NOT NULL DEFAULT 'receta'",
                $log
            );
            migracion_ejecutar(
                $conexion,
                "UPDATE producto SET tipo = 'receta' WHERE tipo IN ('armazon', 'accesorio') OR tipo IS NULL OR tipo = ''",
                $log
            );
            migracion_ejecutar(
                $conexion,
                "ALTER TABLE producto MODIFY COLUMN tipo ENUM('receta', 'sol', 'clip-on') NOT NULL DEFAULT 'receta'",
                $log
            );
        }
    }

    $modoDespachoType = mayorista_obtener_tipo_columna($conexion, 'ventas', 'modo_despacho');
    if (!mayorista_column_exists($conexion, 'ventas', 'modo_despacho')) {
        migracion_ejecutar(
            $conexion,
            "ALTER TABLE ventas ADD COLUMN modo_despacho ENUM('Andreani', 'Via cargo', 'Credifin', 'Correo argentino', 'Oca', 'Buspack', 'Send box', 'A convenir') NOT NULL DEFAULT 'A convenir' AFTER id_metodo",
            $log
        );
    } elseif (strpos($modoDespachoType, 'andreani') === false || strpos($modoDespachoType, 'a convenir') === false) {
        migracion_ejecutar(
            $conexion,
            "ALTER TABLE ventas MODIFY COLUMN modo_despacho ENUM('Andreani', 'Via cargo', 'Credifin', 'Correo argentino', 'Oca', 'Buspack', 'Send box', 'A convenir') NOT NULL DEFAULT 'A convenir'",
            $log
        );
    }

    if (!mayorista_schema_remito_productos_listo($conexion)) {
        throw new Exception('La migracion termino pero el esquema aun no quedo completo.');
    }

    mayorista_marcar_migracion_remito_productos_ejecutada($conexion);
    mayorista_invalidar_token_migracion_remito();

    migracion_json(array(
        'success' => true,
        'message' => 'Migracion aplicada correctamente.',
        'log' => $log,
    ));
} catch (Exception $e) {
    migracion_json(array(
        'success' => false,
        'message' => 'No se pudo completar la migracion: ' . $e->getMessage(),
        'log' => $log,
    ), 500);
}
