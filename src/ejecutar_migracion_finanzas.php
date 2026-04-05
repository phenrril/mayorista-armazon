<?php
session_start();
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";

header('Content-Type: application/json; charset=utf-8');

function migracion_finanzas_json($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function migracion_finanzas_ejecutar($conexion, $sql, &$log)
{
    $ok = mysqli_query($conexion, $sql);
    if (!$ok) {
        throw new Exception(mysqli_error($conexion));
    }

    $log[] = $sql;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    migracion_finanzas_json(array('success' => false, 'message' => 'Metodo no permitido.'), 405);
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    migracion_finanzas_json(array('success' => false, 'message' => 'No autorizado.'), 403);
}

if (!($conexion instanceof mysqli)) {
    migracion_finanzas_json(array('success' => false, 'message' => 'No se pudo establecer la conexion a la base de datos.'), 500);
}

$idUser = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $idUser, array('configuracion'))) {
    migracion_finanzas_json(array('success' => false, 'message' => 'No tenes permisos para ejecutar esta migracion.'), 403);
}

$token = $_POST['csrf_token'] ?? '';
if (!mayorista_validar_token_migracion_finanzas($token)) {
    migracion_finanzas_json(array('success' => false, 'message' => 'Token de seguridad invalido o vencido.'), 403);
}

if (mayorista_schema_finanzas_operativas_listo($conexion)) {
    mayorista_marcar_migracion_finanzas_operativas_ejecutada($conexion);
    mayorista_invalidar_token_migracion_finanzas();
    migracion_finanzas_json(array(
        'success' => true,
        'message' => 'La migracion financiera ya estaba aplicada.',
        'already_applied' => true,
    ));
}

$log = array();

try {
    migracion_finanzas_ejecutar(
        $conexion,
        "CREATE TABLE IF NOT EXISTS proveedores (
            id INT NOT NULL AUTO_INCREMENT,
            nombre VARCHAR(150) NOT NULL,
            telefono VARCHAR(60) NULL DEFAULT NULL,
            email VARCHAR(150) NULL DEFAULT NULL,
            observaciones VARCHAR(255) NULL DEFAULT NULL,
            estado TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_proveedor_nombre (nombre),
            KEY idx_proveedor_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        $log
    );

    migracion_finanzas_ejecutar(
        $conexion,
        "CREATE TABLE IF NOT EXISTS compromisos_financieros (
            id INT NOT NULL AUTO_INCREMENT,
            tipo ENUM('cheque_recibido', 'cheque_emitido', 'deuda_proveedor', 'compromiso_pago') NOT NULL,
            id_cliente INT NULL DEFAULT NULL,
            id_proveedor INT NULL DEFAULT NULL,
            id_venta INT NULL DEFAULT NULL,
            id_metodo INT NULL DEFAULT NULL,
            monto_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            saldo_pendiente DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            estado ENUM('pendiente', 'parcial', 'cumplido', 'vencido', 'pendiente_confirmacion') NOT NULL DEFAULT 'pendiente',
            fecha_compromiso DATE NOT NULL,
            fecha_vencimiento DATE NOT NULL,
            fecha_deposito DATE NULL DEFAULT NULL,
            fecha_ultimo_recordatorio DATE NULL DEFAULT NULL,
            descripcion VARCHAR(255) NOT NULL,
            observaciones TEXT NULL,
            id_usuario INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_compromiso_tipo_estado (tipo, estado),
            KEY idx_compromiso_vencimiento (fecha_vencimiento),
            KEY idx_compromiso_deposito (fecha_deposito),
            KEY idx_compromiso_cliente (id_cliente),
            KEY idx_compromiso_proveedor (id_proveedor),
            KEY idx_compromiso_venta (id_venta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        $log
    );

    migracion_finanzas_ejecutar(
        $conexion,
        "CREATE TABLE IF NOT EXISTS compromisos_financieros_pagos (
            id INT NOT NULL AUTO_INCREMENT,
            id_compromiso INT NOT NULL,
            monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            fecha_pago DATE NOT NULL,
            descripcion VARCHAR(255) NOT NULL,
            id_metodo INT NULL DEFAULT NULL,
            id_usuario INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_compromiso_pago_compromiso (id_compromiso),
            KEY idx_compromiso_pago_fecha (fecha_pago)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        $log
    );

    if (!mayorista_tiene_permiso($conexion, $idUser, array('tesoreria'))) {
        mysqli_query(
            $conexion,
            "INSERT INTO permisos (nombre)
             SELECT 'tesoreria'
             WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE nombre = 'tesoreria')"
        );
    }
    $log[] = "INSERT permiso tesoreria si no existia";

    if (mayorista_table_exists($conexion, 'metodos')) {
        $columnaMetodo = mayorista_column_exists($conexion, 'metodos', 'descripcion') ? 'descripcion' : (mayorista_column_exists($conexion, 'metodos', 'metodo') ? 'metodo' : '');
        if ($columnaMetodo !== '') {
            $metodoCheque = mysqli_query($conexion, "SELECT id FROM metodos WHERE id = 5 OR $columnaMetodo = 'Cheque' LIMIT 1");
            if (!$metodoCheque || mysqli_num_rows($metodoCheque) === 0) {
                migracion_finanzas_ejecutar(
                    $conexion,
                    "INSERT INTO metodos (id, $columnaMetodo) VALUES (5, 'Cheque')",
                    $log
                );
            }
        } else {
            $log[] = "La tabla metodos existe pero no tiene columna compatible para registrar 'Cheque'";
        }
    }

    if (mayorista_table_exists($conexion, 'ingresos') && !mayorista_column_exists($conexion, 'ingresos', 'descripcion')) {
        migracion_finanzas_ejecutar(
            $conexion,
            "ALTER TABLE ingresos ADD COLUMN descripcion VARCHAR(255) NULL DEFAULT NULL AFTER ingresos",
            $log
        );
    }

    if (mayorista_table_exists($conexion, 'egresos') && !mayorista_column_exists($conexion, 'egresos', 'descripcion')) {
        migracion_finanzas_ejecutar(
            $conexion,
            "ALTER TABLE egresos ADD COLUMN descripcion VARCHAR(255) NULL DEFAULT NULL AFTER egresos",
            $log
        );
    }

    $constraints = array(
        "ALTER TABLE compromisos_financieros ADD CONSTRAINT fk_compromiso_proveedor FOREIGN KEY (id_proveedor) REFERENCES proveedores(id)",
        "ALTER TABLE compromisos_financieros ADD CONSTRAINT fk_compromiso_venta FOREIGN KEY (id_venta) REFERENCES ventas(id) ON DELETE SET NULL",
        "ALTER TABLE compromisos_financieros_pagos ADD CONSTRAINT fk_compromiso_pago_compromiso FOREIGN KEY (id_compromiso) REFERENCES compromisos_financieros(id) ON DELETE CASCADE"
    );
    foreach ($constraints as $sqlConstraint) {
        @mysqli_query($conexion, $sqlConstraint);
    }
    $log[] = "Se verificaron claves foraneas de la migracion financiera";

    if (!mayorista_schema_finanzas_operativas_listo($conexion)) {
        throw new Exception('La migracion termino pero la estructura financiera aun no quedo completa.');
    }

    mayorista_marcar_migracion_finanzas_operativas_ejecutada($conexion);
    mayorista_invalidar_token_migracion_finanzas();

    migracion_finanzas_json(array(
        'success' => true,
        'message' => 'Migracion financiera aplicada correctamente.',
        'log' => $log,
    ));
} catch (Exception $e) {
    migracion_finanzas_json(array(
        'success' => false,
        'message' => 'No se pudo completar la migracion financiera: ' . $e->getMessage(),
        'log' => $log,
    ), 500);
}
