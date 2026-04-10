<?php
session_start();
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";

header('Content-Type: application/json; charset=utf-8');

function migracion_vencimientos_venta_json($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function migracion_vencimientos_venta_ejecutar($conexion, $sql, &$log)
{
    $ok = mysqli_query($conexion, $sql);
    if (!$ok) {
        throw new Exception(mysqli_error($conexion));
    }

    $log[] = $sql;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    migracion_vencimientos_venta_json(array('success' => false, 'message' => 'Metodo no permitido.'), 405);
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    migracion_vencimientos_venta_json(array('success' => false, 'message' => 'No autorizado.'), 403);
}

if (!($conexion instanceof mysqli)) {
    migracion_vencimientos_venta_json(array('success' => false, 'message' => 'No se pudo establecer la conexion a la base de datos.'), 500);
}

$idUser = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $idUser, array('configuracion'))) {
    migracion_vencimientos_venta_json(array('success' => false, 'message' => 'No tenes permisos para ejecutar esta migracion.'), 403);
}

$token = $_POST['csrf_token'] ?? '';
if (!mayorista_validar_token_migracion_vencimientos_venta($token)) {
    migracion_vencimientos_venta_json(array('success' => false, 'message' => 'Token de seguridad invalido o vencido.'), 403);
}

if (mayorista_schema_vencimientos_venta_listo($conexion)) {
    mayorista_invalidar_token_migracion_vencimientos_venta();
    migracion_vencimientos_venta_json(array(
        'success' => true,
        'message' => 'La migracion de vencimientos internos ya estaba aplicada.',
        'already_applied' => true,
    ));
}

$log = array();

try {
    migracion_vencimientos_venta_ejecutar(
        $conexion,
        "CREATE TABLE IF NOT EXISTS venta_vencimientos (
            id INT NOT NULL AUTO_INCREMENT,
            id_venta INT NOT NULL,
            fecha_vencimiento DATE NOT NULL,
            monto DECIMAL(10,2) NULL DEFAULT NULL,
            nota_interna TEXT NULL,
            estado ENUM('pendiente', 'cumplido', 'cancelado') NOT NULL DEFAULT 'pendiente',
            fecha_ultimo_recordatorio DATE NULL DEFAULT NULL,
            id_usuario INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_venta_vencimientos_venta (id_venta),
            KEY idx_venta_vencimientos_estado_fecha (estado, fecha_vencimiento),
            KEY idx_venta_vencimientos_recordatorio (fecha_ultimo_recordatorio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        $log
    );

    $alterStatements = array();
    if (!mayorista_column_exists($conexion, 'venta_vencimientos', 'monto')) {
        $alterStatements[] = "ALTER TABLE venta_vencimientos ADD COLUMN monto DECIMAL(10,2) NULL DEFAULT NULL AFTER fecha_vencimiento";
    }
    if (!mayorista_column_exists($conexion, 'venta_vencimientos', 'nota_interna')) {
        $alterStatements[] = "ALTER TABLE venta_vencimientos ADD COLUMN nota_interna TEXT NULL AFTER monto";
    }
    if (!mayorista_column_exists($conexion, 'venta_vencimientos', 'estado')) {
        $alterStatements[] = "ALTER TABLE venta_vencimientos ADD COLUMN estado ENUM('pendiente', 'cumplido', 'cancelado') NOT NULL DEFAULT 'pendiente' AFTER nota_interna";
    }
    if (!mayorista_column_exists($conexion, 'venta_vencimientos', 'fecha_ultimo_recordatorio')) {
        $alterStatements[] = "ALTER TABLE venta_vencimientos ADD COLUMN fecha_ultimo_recordatorio DATE NULL DEFAULT NULL AFTER estado";
    }
    if (!mayorista_column_exists($conexion, 'venta_vencimientos', 'id_usuario')) {
        $alterStatements[] = "ALTER TABLE venta_vencimientos ADD COLUMN id_usuario INT NOT NULL DEFAULT $idUser AFTER fecha_ultimo_recordatorio";
    }
    if (!mayorista_column_exists($conexion, 'venta_vencimientos', 'created_at')) {
        $alterStatements[] = "ALTER TABLE venta_vencimientos ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER id_usuario";
    }
    if (!mayorista_column_exists($conexion, 'venta_vencimientos', 'updated_at')) {
        $alterStatements[] = "ALTER TABLE venta_vencimientos ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at";
    }

    foreach ($alterStatements as $sql) {
        migracion_vencimientos_venta_ejecutar($conexion, $sql, $log);
    }

    @mysqli_query(
        $conexion,
        "ALTER TABLE venta_vencimientos
         ADD CONSTRAINT fk_venta_vencimientos_venta
         FOREIGN KEY (id_venta) REFERENCES ventas(id) ON DELETE CASCADE"
    );
    $log[] = "Se verifico la clave foranea de venta_vencimientos -> ventas";

    if (!mayorista_schema_vencimientos_venta_listo($conexion)) {
        throw new Exception('La migracion termino pero la tabla de vencimientos internos no quedo completa.');
    }

    mayorista_invalidar_token_migracion_vencimientos_venta();

    migracion_vencimientos_venta_json(array(
        'success' => true,
        'message' => 'Migracion de vencimientos internos aplicada correctamente.',
        'log' => $log,
    ));
} catch (Exception $e) {
    migracion_vencimientos_venta_json(array(
        'success' => false,
        'message' => 'No se pudo completar la migracion de vencimientos internos: ' . $e->getMessage(),
        'log' => $log,
    ), 500);
}
