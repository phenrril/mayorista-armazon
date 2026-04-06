<?php
session_start();
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";

header('Content-Type: application/json; charset=utf-8');

function reset_cc_json($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reset_cc_json(array('success' => false, 'message' => 'Metodo no permitido.'), 405);
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    reset_cc_json(array('success' => false, 'message' => 'No autorizado.'), 403);
}

if (!($conexion instanceof mysqli)) {
    reset_cc_json(array('success' => false, 'message' => 'No se pudo establecer la conexion a la base de datos.'), 500);
}

$idUser = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $idUser, array('configuracion'))) {
    reset_cc_json(array('success' => false, 'message' => 'No tenes permisos para ejecutar esta accion.'), 403);
}

$token = $_POST['csrf_token'] ?? '';
if (!mayorista_validar_token_reset_cc_masivo($token)) {
    reset_cc_json(array('success' => false, 'message' => 'Token de seguridad invalido o vencido.'), 403);
}

if (!mayorista_table_exists($conexion, 'cuenta_corriente') || !mayorista_table_exists($conexion, 'movimientos_cc')) {
    reset_cc_json(array('success' => false, 'message' => 'Las tablas de cuenta corriente no estan disponibles en esta base.'), 400);
}

if (mayorista_reset_cc_masivo_fue_ejecutado($conexion)) {
    mayorista_invalidar_token_reset_cc_masivo();
    reset_cc_json(array(
        'success' => true,
        'message' => 'Esta accion ya habia sido ejecutada anteriormente.',
        'already_applied' => true,
    ));
}

$movimientosBorrados = 0;
$cuentasActualizadas = 0;

try {
    if (!mysqli_begin_transaction($conexion)) {
        throw new RuntimeException('No se pudo iniciar la transaccion.');
    }

    if (!mysqli_query($conexion, 'DELETE FROM movimientos_cc')) {
        throw new RuntimeException(mysqli_error($conexion) ?: 'No se pudieron eliminar los movimientos de cuenta corriente.');
    }
    $movimientosBorrados = mysqli_affected_rows($conexion);

    if (!mysqli_query($conexion, 'UPDATE cuenta_corriente SET saldo_actual = 0')) {
        throw new RuntimeException(mysqli_error($conexion) ?: 'No se pudieron actualizar los saldos.');
    }
    $cuentasActualizadas = mysqli_affected_rows($conexion);

    if (!mayorista_marcar_reset_cc_masivo_ejecutado($conexion)) {
        throw new RuntimeException('No se pudo registrar el cierre de un solo uso.');
    }

    if (!mysqli_commit($conexion)) {
        throw new RuntimeException('No se pudo confirmar la transaccion.');
    }

    mayorista_invalidar_token_reset_cc_masivo();

    reset_cc_json(array(
        'success' => true,
        'message' => 'Cuentas corrientes en cero: se eliminaron ' . (int) $movimientosBorrados . ' movimiento(s) y se actualizaron ' . (int) $cuentasActualizadas . ' cuenta(s).',
        'movimientos_borrados' => (int) $movimientosBorrados,
        'cuentas_actualizadas' => (int) $cuentasActualizadas,
    ));
} catch (Throwable $e) {
    mysqli_rollback($conexion);
    reset_cc_json(array(
        'success' => false,
        'message' => $e->getMessage(),
    ), 500);
}
