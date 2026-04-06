<?php
session_start();
require "../conexion.php";
require_once "includes/mayorista_helpers.php";

function anular_alerta($icon, $title, $timer = 2000)
{
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'status' => $icon === 'success' ? 'success' : 'error',
            'icon' => $icon,
            'title' => $title,
            'timer' => $timer,
        ));
        exit();
    }

    echo "<script>Swal.fire({
        position: 'center',
        toast: false,
        icon: '$icon',
        title: '" . addslashes($title) . "',
        showConfirmButton: false,
        timer: $timer
    })</script>;";
    exit();
}

if (!($conexion instanceof mysqli)) {
    anular_alerta('error', 'No se pudo conectar a la base de datos', 3000);
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    http_response_code(403);
    anular_alerta('error', 'No autorizado', 3000);
}

$id_user = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $id_user, array('ventas'))) {
    http_response_code(403);
    anular_alerta('error', 'No tenes permisos para anular ventas', 3000);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    anular_alerta('error', 'Metodo no permitido', 3000);
}

$id_venta = isset($_POST['idanular']) ? (int) $_POST['idanular'] : 0;
if ($id_venta <= 0) {
    anular_alerta('error', 'Error al eliminar venta, verifique ID', 3000);
}

if (mayorista_venta_tiene_factura_aprobada($conexion, $id_venta)) {
    anular_alerta('error', 'La venta tiene una factura aprobada y no puede anularse', 3000);
}

mysqli_begin_transaction($conexion);

try {
    $ventaQuery = mysqli_query(
        $conexion,
        "SELECT id_cliente
         FROM ventas
         WHERE id = $id_venta
         LIMIT 1"
    );
    $venta = $ventaQuery ? mysqli_fetch_assoc($ventaQuery) : null;
    if (!$venta) {
        throw new RuntimeException('Venta inexistente');
    }
    $id_cliente = (int) $venta['id_cliente'];

    $consultaDetalle = mysqli_query(
        $conexion,
        "SELECT id_producto, cantidad
         FROM detalle_venta
         WHERE id_venta = $id_venta"
    );
    if (!$consultaDetalle || mysqli_num_rows($consultaDetalle) === 0) {
        throw new RuntimeException('Venta inexistente');
    }

    while ($row = mysqli_fetch_assoc($consultaDetalle)) {
        $id_producto = (int) $row['id_producto'];
        $cantidad = (int) $row['cantidad'];

        $stockActual = mysqli_query(
            $conexion,
            "SELECT existencia
             FROM producto
             WHERE codproducto = $id_producto
             LIMIT 1"
        );
        $stockNuevo = $stockActual ? mysqli_fetch_assoc($stockActual) : null;
        if (!$stockNuevo) {
            throw new RuntimeException('No se pudo recuperar el producto asociado a la venta');
        }

        $stockTotal = (int) $stockNuevo['existencia'] + $cantidad;
        $stock = mysqli_query(
            $conexion,
            "UPDATE producto
             SET existencia = $stockTotal, estado = IF($stockTotal > 0, 1, estado)
             WHERE codproducto = $id_producto"
        );
        if (!$stock) {
            throw new RuntimeException('No se pudo restaurar el stock');
        }
    }

    if (!mysqli_query($conexion, "DELETE FROM detalle_venta WHERE id_venta = $id_venta")) {
        throw new RuntimeException('No se pudo eliminar el detalle de venta');
    }

    if (mayorista_table_exists($conexion, 'postpagos')) {
        if (!mysqli_query($conexion, "DELETE FROM postpagos WHERE id_venta = $id_venta")) {
            throw new RuntimeException('No se pudo eliminar el postpago asociado');
        }
    }

    if (mayorista_table_exists($conexion, 'movimientos_cc')) {
        if (!mysqli_query($conexion, "DELETE FROM movimientos_cc WHERE id_venta = $id_venta")) {
            throw new RuntimeException('No se pudo revertir la cuenta corriente asociada');
        }
        $cuenta = mayorista_obtener_cuenta_corriente($conexion, $id_cliente);
        if (!empty($cuenta['id'])) {
            mayorista_actualizar_saldo_cc($conexion, (int) $cuenta['id']);
        }
    }

    if (mayorista_table_exists($conexion, 'ingresos')) {
        if (!mysqli_query($conexion, "DELETE FROM ingresos WHERE id_venta = $id_venta")) {
            throw new RuntimeException('No se pudieron eliminar los ingresos asociados');
        }
    }

    if (mayorista_table_exists($conexion, 'facturas_electronicas')) {
        if (!mysqli_query($conexion, "DELETE FROM facturas_electronicas WHERE id_venta = $id_venta")) {
            throw new RuntimeException('No se pudieron limpiar los registros de facturacion');
        }
    }

    if (!mysqli_query($conexion, "DELETE FROM ventas WHERE id = $id_venta")) {
        throw new RuntimeException('No se pudo eliminar la venta');
    }

    mysqli_commit($conexion);
    anular_alerta('success', 'Venta eliminada');
} catch (Throwable $e) {
    mysqli_rollback($conexion);
    anular_alerta('error', $e->getMessage(), 3000);
}