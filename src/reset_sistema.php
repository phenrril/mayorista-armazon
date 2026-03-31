<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";

function responder_reset_sistema($success, $message, $extra = array(), $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(
            array(
                'success' => (bool) $success,
                'message' => $message,
            ),
            $extra
        ),
        JSON_UNESCAPED_UNICODE
    );
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_reset_sistema(false, 'Método no permitido.', array(), 405);
}

if (!isset($_SESSION['idUser']) || !mayorista_es_admin($_SESSION['idUser'])) {
    responder_reset_sistema(false, 'Solo el administrador principal puede ejecutar esta acción.', array(), 403);
}

if (!$conexion || !is_object($conexion) || mysqli_connect_errno()) {
    responder_reset_sistema(false, 'No se pudo establecer conexión con la base de datos.', array(), 500);
}

$token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$confirmacion = isset($_POST['confirmacion']) ? trim((string) $_POST['confirmacion']) : '';

if (!mayorista_validar_token_reset_sistema($token)) {
    responder_reset_sistema(false, 'Token de seguridad inválido. Recargá la página e intentá de nuevo.', array(), 403);
}

if (!mayorista_asegurar_tabla_flags($conexion)) {
    responder_reset_sistema(false, 'No se pudo preparar el bloqueo de un solo uso.', array(), 500);
}

if (mayorista_reset_sistema_fue_ejecutado($conexion)) {
    mayorista_invalidar_token_reset_sistema();
    responder_reset_sistema(false, 'El reset ya fue ejecutado y quedó bloqueado permanentemente.', array('blocked' => true), 409);
}

if ($confirmacion !== 'ELIMINAR TODO') {
    responder_reset_sistema(false, 'La frase de confirmación no coincide exactamente.', array(), 422);
}

$tablasALimpiar = array(
    'facturas_electronicas',
    'movimientos_cc',
    'cuenta_corriente',
    'detalle_venta',
    'postpagos',
    'ingresos',
    'ventas',
    'detalle_temp',
    'descuento',
    'egresos',
    'historia_clinica',
    'graduaciones_temp',
    'graduaciones',
    'saldos',
    'cliente',
    'producto',
    'detalle_permisos',
    'usuario',
);

$foreignKeysDesactivadas = false;

try {
    if (!mysqli_begin_transaction($conexion)) {
        throw new RuntimeException('No se pudo iniciar la transacción.');
    }

    if (!mysqli_query($conexion, "SET FOREIGN_KEY_CHECKS = 0")) {
        throw new RuntimeException('No se pudieron desactivar las restricciones de claves foráneas.');
    }
    $foreignKeysDesactivadas = true;

    foreach ($tablasALimpiar as $tabla) {
        if (!mayorista_table_exists($conexion, $tabla)) {
            continue;
        }

        if (!mysqli_query($conexion, "DELETE FROM `$tabla`")) {
            throw new RuntimeException('No se pudo limpiar la tabla ' . $tabla . '.');
        }

        @mysqli_query($conexion, "ALTER TABLE `$tabla` AUTO_INCREMENT = 1");
    }

    $claveAdmin = md5('Matute00!');
    $sqlAdmin = "INSERT INTO usuario (idusuario, nombre, correo, usuario, clave, estado)
        VALUES (1, 'admin', 'admin@local', 'admin', '$claveAdmin', 1)";

    if (!mysqli_query($conexion, $sqlAdmin)) {
        throw new RuntimeException('No se pudo recrear el usuario administrador.');
    }

    if (mayorista_table_exists($conexion, 'permisos')) {
        $sqlPermisos = "INSERT INTO detalle_permisos (id_permiso, id_usuario)
            SELECT id, 1 FROM permisos";

        if (!mysqli_query($conexion, $sqlPermisos)) {
            throw new RuntimeException('No se pudieron reasignar los permisos al administrador.');
        }
    }

    if (!mayorista_marcar_reset_sistema_ejecutado($conexion)) {
        throw new RuntimeException('No se pudo bloquear permanentemente el reset.');
    }

    if (!mysqli_query($conexion, "SET FOREIGN_KEY_CHECKS = 1")) {
        throw new RuntimeException('No se pudieron reactivar las restricciones de claves foráneas.');
    }
    $foreignKeysDesactivadas = false;

    if (!mysqli_commit($conexion)) {
        throw new RuntimeException('No se pudo confirmar la transacción.');
    }
} catch (Throwable $e) {
    error_log('Reset del sistema falló: ' . $e->getMessage());

    if ($foreignKeysDesactivadas) {
        mysqli_query($conexion, "SET FOREIGN_KEY_CHECKS = 1");
    }

    mysqli_rollback($conexion);
    responder_reset_sistema(false, 'Ocurrió un error durante el reset. No se aplicaron cambios.', array(), 500);
}

mayorista_invalidar_token_reset_sistema();
$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

responder_reset_sistema(
    true,
    'Se eliminaron los datos operativos, se recreó el usuario admin y el reset quedó bloqueado permanentemente.',
    array('redirect' => '../')
);
