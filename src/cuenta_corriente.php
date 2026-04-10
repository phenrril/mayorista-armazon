<?php
session_start();
include "../conexion.php";
require_once "includes/mayorista_helpers.php";

if (!($conexion instanceof mysqli)) {
    exit('No se pudo conectar a la base de datos.');
}
/** @var mysqli $conexion */

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('cuenta_corriente', 'clientes'));
$esAdmin = mayorista_es_admin($id_user);
$hasClienteOptica = mayorista_column_exists($conexion, 'cliente', 'optica');
$hasClienteLocalidad = mayorista_column_exists($conexion, 'cliente', 'localidad');
$hasClienteProvincia = mayorista_column_exists($conexion, 'cliente', 'provincia');
$hasClienteDni = mayorista_column_exists($conexion, 'cliente', 'dni');
$hasClienteCuit = mayorista_column_exists($conexion, 'cliente', 'cuit');
$hasFinanzas = mayorista_schema_finanzas_operativas_listo($conexion);

$schemaReady = mayorista_table_exists($conexion, 'cuenta_corriente') && mayorista_table_exists($conexion, 'movimientos_cc');
$schemaMovimientosCc = $schemaReady && mayorista_schema_movimientos_cc_metodos_listo($conexion);
$metodosPago = mayorista_obtener_metodos_pago($conexion);
$alert = '';
$selectedClientId = isset($_GET['cliente']) ? (int) $_GET['cliente'] : 0;

function cc_metodo_es_cheque($idMetodo)
{
    return (int) $idMetodo === 5;
}

function cc_actualizar_movimiento_base($conexion, $idMovimiento, $monto, $idMetodo, $origenTipo = null, $origenId = null)
{
    $idMovimiento = (int) $idMovimiento;
    $monto = round((float) $monto, 2);
    $idMetodo = (int) $idMetodo;

    $updates = array("monto = $monto");
    if (mayorista_column_exists($conexion, 'movimientos_cc', 'id_metodo')) {
        $updates[] = 'id_metodo = ' . ($idMetodo > 0 ? $idMetodo : 'NULL');
    }
    if ($origenTipo !== null && mayorista_column_exists($conexion, 'movimientos_cc', 'origen_tipo')) {
        $origenTipo = trim((string) $origenTipo);
        $updates[] = "origen_tipo = " . ($origenTipo !== '' ? "'" . mysqli_real_escape_string($conexion, $origenTipo) . "'" : 'NULL');
    }
    if ($origenId !== null && mayorista_column_exists($conexion, 'movimientos_cc', 'origen_id')) {
        $origenId = (int) $origenId;
        $updates[] = 'origen_id = ' . ($origenId > 0 ? $origenId : 'NULL');
    }

    if (empty($updates)) {
        return false;
    }

    return mysqli_query(
        $conexion,
        "UPDATE movimientos_cc
         SET " . implode(",\n             ", $updates) . "
         WHERE id = $idMovimiento"
    ) !== false;
}

function cc_eliminar_registros_venta_cobro($conexion, $idVenta)
{
    $idVenta = (int) $idVenta;
    if ($idVenta <= 0) {
        return;
    }

    if (mayorista_table_exists($conexion, 'ingresos')) {
        mysqli_query($conexion, "DELETE FROM ingresos WHERE id_venta = '$idVenta'");
    }

    if (mayorista_table_exists($conexion, 'compromisos_financieros')) {
        $query = mysqli_query(
            $conexion,
            "SELECT id, estado
             FROM compromisos_financieros
             WHERE id_venta = $idVenta
             AND tipo = 'cheque_recibido'"
        );
        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $estado = (string) ($row['estado'] ?? '');
                if ($estado !== 'pendiente_confirmacion') {
                    throw new RuntimeException('La venta tiene un cheque ya confirmado y no puede reconfigurarse desde cuenta corriente.');
                }
                mysqli_query($conexion, "DELETE FROM compromisos_financieros WHERE id = " . (int) $row['id']);
            }
        }
    }
}

function cc_guardar_registro_venta_cobro($conexion, array $venta, $abona, $idMetodo, $idUsuario)
{
    $idVenta = (int) ($venta['id'] ?? 0);
    $idCliente = (int) ($venta['id_cliente'] ?? 0);
    $abona = round((float) $abona, 2);
    $idMetodo = (int) $idMetodo;
    $idUsuario = (int) $idUsuario;
    $fechaVenta = !empty($venta['fecha']) ? date('Y-m-d H:i:s', strtotime((string) $venta['fecha'])) : date('Y-m-d H:i:s');
    $fechaVentaIso = date('Y-m-d', strtotime($fechaVenta));

    cc_eliminar_registros_venta_cobro($conexion, $idVenta);
    if ($abona <= 0) {
        return;
    }

    if (cc_metodo_es_cheque($idMetodo)) {
        mayorista_registrar_compromiso_financiero($conexion, array(
            'tipo' => 'cheque_recibido',
            'id_cliente' => $idCliente,
            'id_venta' => $idVenta,
            'id_metodo' => 5,
            'monto_total' => $abona,
            'saldo_pendiente' => $abona,
            'estado' => 'pendiente_confirmacion',
            'fecha_compromiso' => $fechaVentaIso,
            'fecha_vencimiento' => date('Y-m-d', strtotime($fechaVentaIso . ' +30 days')),
            'fecha_deposito' => date('Y-m-d', strtotime($fechaVentaIso . ' +30 days')),
            'descripcion' => 'Cheque recibido venta #' . $idVenta,
            'observaciones' => 'Actualizado desde cuenta corriente',
            'id_usuario' => $idUsuario,
        ));
        return;
    }

    $columnas = array('ingresos', 'fecha', 'id_venta', 'id_cliente', 'id_metodo');
    $valores = array(
        $abona,
        "'" . mysqli_real_escape_string($conexion, $fechaVenta) . "'",
        "'" . $idVenta . "'",
        $idCliente,
        $idMetodo,
    );
    if (mayorista_column_exists($conexion, 'ingresos', 'descripcion')) {
        $columnas[] = 'descripcion';
        $valores[] = "'Ingreso inicial venta #$idVenta'";
    }

    $ok = mysqli_query(
        $conexion,
        "INSERT INTO ingresos(" . implode(', ', $columnas) . ")
         VALUES (" . implode(', ', $valores) . ")"
    );
    if (!$ok) {
        throw new RuntimeException('No se pudo actualizar el ingreso asociado a la venta.');
    }
}

function cc_guardar_movimiento_pago_asociado($conexion, array $movimiento, $monto, $idMetodo, $idUsuario)
{
    $monto = round((float) $monto, 2);
    $idMetodo = (int) $idMetodo;
    $idUsuario = (int) $idUsuario;
    $idCliente = (int) ($movimiento['id_cliente'] ?? 0);
    $descripcion = mayorista_limpiar_descripcion($movimiento['descripcion'] ?? 'Pago manual', 255);
    $fechaMovimiento = !empty($movimiento['fecha']) ? date('Y-m-d H:i:s', strtotime((string) $movimiento['fecha'])) : date('Y-m-d H:i:s');
    $fechaMovimientoIso = date('Y-m-d', strtotime($fechaMovimiento));
    $origenTipo = trim((string) ($movimiento['origen_tipo'] ?? ''));
    $origenId = (int) ($movimiento['origen_id'] ?? 0);

    if ($origenTipo === 'ingreso' && $origenId > 0) {
        mysqli_query($conexion, "DELETE FROM ingresos WHERE id = $origenId");
    }
    if ($origenTipo === 'compromiso_financiero' && $origenId > 0) {
        $compromisoQuery = mysqli_query(
            $conexion,
            "SELECT estado
             FROM compromisos_financieros
             WHERE id = $origenId
             LIMIT 1"
        );
        $compromiso = $compromisoQuery ? mysqli_fetch_assoc($compromisoQuery) : null;
        if ($compromiso && ($compromiso['estado'] ?? '') !== 'pendiente_confirmacion') {
            throw new RuntimeException('El cheque asociado ya fue procesado y no puede editarse desde cuenta corriente.');
        }
        mysqli_query($conexion, "DELETE FROM compromisos_financieros WHERE id = $origenId");
    }

    if (cc_metodo_es_cheque($idMetodo)) {
        $nuevoOrigenId = mayorista_registrar_compromiso_financiero($conexion, array(
            'tipo' => 'cheque_recibido',
            'id_cliente' => $idCliente,
            'id_metodo' => 5,
            'monto_total' => $monto,
            'saldo_pendiente' => $monto,
            'estado' => 'pendiente_confirmacion',
            'fecha_compromiso' => $fechaMovimientoIso,
            'fecha_vencimiento' => date('Y-m-d', strtotime($fechaMovimientoIso . ' +30 days')),
            'fecha_deposito' => date('Y-m-d', strtotime($fechaMovimientoIso . ' +30 days')),
            'descripcion' => 'Cheque recibido CC - ' . $descripcion,
            'observaciones' => 'Actualizado desde cuenta corriente',
            'id_usuario' => $idUsuario,
        ));

        return array(
            'origen_tipo' => 'compromiso_financiero',
            'origen_id' => $nuevoOrigenId,
            'sincronizado' => true,
        );
    }

    $columnas = array('ingresos', 'fecha', 'id_venta', 'id_cliente', 'id_metodo');
    $valores = array(
        $monto,
        "'" . mysqli_real_escape_string($conexion, $fechaMovimiento) . "'",
        "'CC-MOV-" . (int) ($movimiento['id'] ?? 0) . "'",
        $idCliente,
        $idMetodo,
    );
    if (mayorista_column_exists($conexion, 'ingresos', 'descripcion')) {
        $columnas[] = 'descripcion';
        $valores[] = "'" . mysqli_real_escape_string($conexion, $descripcion) . "'";
    }

    $ok = mysqli_query(
        $conexion,
        "INSERT INTO ingresos(" . implode(', ', $columnas) . ")
         VALUES (" . implode(', ', $valores) . ")"
    );
    if (!$ok) {
        throw new RuntimeException('No se pudo actualizar el ingreso asociado al pago.');
    }

    return array(
        'origen_tipo' => 'ingreso',
        'origen_id' => (int) mysqli_insert_id($conexion),
        'sincronizado' => true,
    );
}

function cc_editar_movimiento($conexion, array $movimiento, $nuevoMonto, $idMetodo, $idUsuario)
{
    $idMovimiento = (int) ($movimiento['id'] ?? 0);
    $idCuenta = (int) ($movimiento['id_cuenta_corriente'] ?? 0);
    $idCliente = (int) ($movimiento['id_cliente'] ?? 0);
    $tipo = (string) ($movimiento['tipo'] ?? '');
    $montoAnterior = round((float) ($movimiento['monto'] ?? 0), 2);
    $nuevoMonto = round((float) $nuevoMonto, 2);
    $idMetodo = (int) $idMetodo;

    if ($idMovimiento <= 0 || $idCuenta <= 0 || $idCliente <= 0) {
        throw new InvalidArgumentException('No se encontró el movimiento seleccionado.');
    }
    if ($nuevoMonto <= 0) {
        throw new InvalidArgumentException('El monto debe ser mayor a cero.');
    }

    $cuenta = mayorista_obtener_cuenta_corriente($conexion, $idCliente);
    $saldoActual = round((float) ($cuenta['saldo_actual'] ?? 0), 2);
    $saldoProyectado = $saldoActual;
    if ($tipo === 'cargo') {
        $saldoProyectado = $saldoActual - $montoAnterior + $nuevoMonto;
        $limite = round((float) ($cuenta['limite_credito'] ?? 0), 2);
        if ($limite > 0 && $saldoProyectado > ($limite + 0.009)) {
            throw new RuntimeException(
                'El nuevo monto supera el límite de crédito. Saldo proyectado: '
                . mayorista_formatear_moneda($saldoProyectado)
                . ' | Límite: '
                . mayorista_formatear_moneda($limite)
            );
        }
    }

    $origenTipo = trim((string) ($movimiento['origen_tipo'] ?? ''));
    $origenId = (int) ($movimiento['origen_id'] ?? 0);
    if ($tipo === 'cargo' && $origenId <= 0 && (int) ($movimiento['id_venta'] ?? 0) > 0) {
        $origenTipo = 'venta';
        $origenId = (int) $movimiento['id_venta'];
    }

    $sincronizado = true;
    $mensajeExtra = '';

    if ($tipo === 'cargo' && $origenTipo === 'venta' && $origenId > 0) {
        $ventaQuery = mysqli_query($conexion, "SELECT * FROM ventas WHERE id = $origenId LIMIT 1");
        $venta = $ventaQuery ? mysqli_fetch_assoc($ventaQuery) : null;
        if (!$venta) {
            throw new RuntimeException('No se encontró la venta asociada al movimiento.');
        }
        if (mayorista_venta_tiene_factura_aprobada($conexion, $origenId)) {
            throw new RuntimeException('La venta asociada ya tiene factura aprobada y no puede editarse desde cuenta corriente.');
        }

        $totalVenta = round((float) ($venta['total'] ?? 0), 2);
        if ($nuevoMonto > ($totalVenta + 0.009)) {
            throw new RuntimeException('El monto en cuenta corriente no puede superar el total de la venta.');
        }

        $nuevoAbona = round($totalVenta - $nuevoMonto, 2);
        cc_guardar_registro_venta_cobro($conexion, $venta, $nuevoAbona, $idMetodo, $idUsuario);
        if (!cc_actualizar_movimiento_base($conexion, $idMovimiento, $nuevoMonto, $idMetodo, 'venta', $origenId)) {
            throw new RuntimeException('No se pudo actualizar el movimiento de cuenta corriente.');
        }

        $nuevoSaldo = mayorista_actualizar_saldo_cc($conexion, $idCuenta);
        $updatesVenta = array(
            "abona = $nuevoAbona",
            "resto = $nuevoMonto",
        );
        if (mayorista_column_exists($conexion, 'ventas', 'monto_cc')) {
            $updatesVenta[] = "monto_cc = $nuevoMonto";
        }
        if (mayorista_column_exists($conexion, 'ventas', 'saldo_cc_cliente')) {
            $updatesVenta[] = "saldo_cc_cliente = $nuevoSaldo";
        }
        if (mayorista_column_exists($conexion, 'ventas', 'id_metodo')) {
            $updatesVenta[] = "id_metodo = $idMetodo";
        }
        mysqli_query(
            $conexion,
            "UPDATE ventas
             SET " . implode(",\n                 ", $updatesVenta) . "
             WHERE id = $origenId"
        );
        if (mayorista_table_exists($conexion, 'postpagos')) {
            mysqli_query(
                $conexion,
                "UPDATE postpagos
                 SET abona = $nuevoAbona,
                     resto = $nuevoMonto
                 WHERE id_venta = $origenId"
            );
        }

        return array('saldo' => $nuevoSaldo, 'sincronizado' => true, 'mensaje_extra' => '');
    }

    if ($tipo === 'pago') {
        $resultadoPago = cc_guardar_movimiento_pago_asociado($conexion, $movimiento, $nuevoMonto, $idMetodo, $idUsuario);
        $sincronizado = !empty($resultadoPago['sincronizado']);
        if (!cc_actualizar_movimiento_base(
            $conexion,
            $idMovimiento,
            $nuevoMonto,
            $idMetodo,
            $resultadoPago['origen_tipo'] ?? $origenTipo,
            $resultadoPago['origen_id'] ?? $origenId
        )) {
            throw new RuntimeException('No se pudo actualizar el movimiento de cuenta corriente.');
        }
        $nuevoSaldo = mayorista_actualizar_saldo_cc($conexion, $idCuenta);
        if (!$sincronizado) {
            $mensajeExtra = ' Se actualizó el saldo, pero no se encontró una referencia vinculada fuera de cuenta corriente.';
        }

        return array('saldo' => $nuevoSaldo, 'sincronizado' => $sincronizado, 'mensaje_extra' => $mensajeExtra);
    }

    if (!cc_actualizar_movimiento_base($conexion, $idMovimiento, $nuevoMonto, $idMetodo, $origenTipo, $origenId)) {
        throw new RuntimeException('No se pudo actualizar el movimiento de cuenta corriente.');
    }

    return array(
        'saldo' => mayorista_actualizar_saldo_cc($conexion, $idCuenta),
        'sincronizado' => false,
        'mensaje_extra' => ' Se actualizó únicamente la cuenta corriente porque el movimiento no tiene una referencia externa asociada.',
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaReady) {
    $selectedClientId = (int) ($_POST['id_cliente'] ?? $selectedClientId);

    if (!empty($_POST['action']) && $_POST['action'] === 'guardar_limite') {
        if (!$esAdmin) {
            $alert = '<div class="alert alert-danger">Solo el administrador puede modificar el limite de credito.</div>';
        } else {
            $limite = round((float) ($_POST['limite_credito'] ?? 0), 2);
            $cuenta = mayorista_asegurar_cuenta_corriente($conexion, $selectedClientId);
            if ($cuenta) {
                $update = mysqli_query(
                    $conexion,
                    "UPDATE cuenta_corriente
                     SET limite_credito = $limite
                     WHERE id = " . (int) $cuenta['id']
                );
                $alert = $update
                    ? '<div class="alert alert-success">Limite de credito actualizado.</div>'
                    : '<div class="alert alert-danger">No se pudo actualizar el limite.</div>';
            }
        }
    }

    if (!empty($_POST['action']) && $_POST['action'] === 'registrar_pago') {
        $monto = round((float) ($_POST['monto'] ?? 0), 2);
        $descripcion = trim($_POST['descripcion'] ?? 'Pago manual');
        $metodo = (int) ($_POST['metodo_pago'] ?? 1);
        $chequePlazoDias = (int) ($_POST['cheque_plazo_dias'] ?? 30);
        $chequeFechaBase = trim((string) ($_POST['cheque_fecha_base'] ?? date('Y-m-d')));
        $chequeFechaDeposito = trim((string) ($_POST['cheque_fecha_deposito'] ?? ''));

        if ($monto <= 0) {
            $alert = '<div class="alert alert-warning">El monto del pago debe ser mayor a cero.</div>';
        } elseif (!$schemaMovimientosCc) {
            $alert = '<div class="alert alert-warning">Primero ejecutá la migración de movimientos de cuenta corriente desde configuración para guardar el modo de pago.</div>';
        } elseif ($metodo === 5 && !$hasFinanzas) {
            $alert = '<div class="alert alert-warning">Primero ejecutá la migración financiera desde configuración para usar cheques con recordatorios.</div>';
        } else {
            mysqli_begin_transaction($conexion);
            try {
                if ($metodo === 5) {
                    if (!in_array($chequePlazoDias, array(30, 60, 90, 120), true)) {
                        $chequePlazoDias = 30;
                    }
                    if (!mayorista_fecha_iso_valida($chequeFechaBase)) {
                        throw new InvalidArgumentException('La fecha base del cheque no es válida.');
                    }
                    if (!mayorista_fecha_iso_valida($chequeFechaDeposito)) {
                        throw new InvalidArgumentException('La fecha esperada de depósito del cheque no es válida.');
                    }
                }

                $origenTipo = cc_metodo_es_cheque($metodo) ? 'compromiso_financiero' : 'ingreso';
                $origenId = 0;
                if (!cc_metodo_es_cheque($metodo)) {
                    $referencia = 'CC-MOV-' . $selectedClientId . '-' . time();
                    $columnasIngreso = array('ingresos', 'fecha', 'id_venta', 'id_cliente', 'id_metodo');
                    $valoresIngreso = array(
                        $monto,
                        'NOW()',
                        "'" . mysqli_real_escape_string($conexion, $referencia) . "'",
                        $selectedClientId,
                        $metodo,
                    );
                    if (mayorista_column_exists($conexion, 'ingresos', 'descripcion')) {
                        $columnasIngreso[] = 'descripcion';
                        $valoresIngreso[] = "'" . mysqli_real_escape_string($conexion, $descripcion) . "'";
                    }
                    $insertIngreso = mysqli_query(
                        $conexion,
                        "INSERT INTO ingresos(" . implode(', ', $columnasIngreso) . ")
                         VALUES (" . implode(', ', $valoresIngreso) . ")"
                    );
                    if (!$insertIngreso) {
                        throw new RuntimeException('No se pudo registrar el ingreso asociado al pago.');
                    }
                    $origenId = (int) mysqli_insert_id($conexion);
                } else {
                    $origenId = (int) mayorista_registrar_compromiso_financiero($conexion, array(
                        'tipo' => 'cheque_recibido',
                        'id_cliente' => $selectedClientId,
                        'id_metodo' => 5,
                        'monto_total' => $monto,
                        'saldo_pendiente' => $monto,
                        'estado' => 'pendiente_confirmacion',
                        'fecha_compromiso' => $chequeFechaBase,
                        'fecha_vencimiento' => $chequeFechaDeposito,
                        'fecha_deposito' => $chequeFechaDeposito,
                        'descripcion' => 'Cheque recibido CC - ' . $descripcion . ' (' . $chequePlazoDias . ' dias)',
                        'observaciones' => 'Pago manual de cuenta corriente',
                        'id_usuario' => $id_user,
                    ));
                }

                $saldo = mayorista_registrar_movimiento_cc(
                    $conexion,
                    $selectedClientId,
                    'pago',
                    $monto,
                    $descripcion,
                    $id_user,
                    null,
                    null,
                    $metodo,
                    $origenTipo,
                    $origenId
                );

                mysqli_commit($conexion);
                $alert = $metodo === 5
                    ? '<div class="alert alert-success">Pago registrado con cheque. Saldo actual: ' . mayorista_formatear_moneda($saldo) . '. Se agregó el recordatorio para la fecha de depósito.</div>'
                    : '<div class="alert alert-success">Pago registrado. Saldo actual: ' . mayorista_formatear_moneda($saldo) . '.</div>';
            } catch (Exception $e) {
                mysqli_rollback($conexion);
                $alert = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    if (!empty($_POST['action']) && $_POST['action'] === 'editar_movimiento') {
        $idMovimiento = (int) ($_POST['id_movimiento'] ?? 0);
        $monto = round((float) ($_POST['monto'] ?? 0), 2);
        $metodo = (int) ($_POST['metodo_pago'] ?? 0);

        if (!$schemaMovimientosCc) {
            $alert = '<div class="alert alert-warning">Primero ejecutá la migración de movimientos de cuenta corriente desde configuración.</div>';
        } elseif ($idMovimiento <= 0) {
            $alert = '<div class="alert alert-danger">No se encontró el movimiento a editar.</div>';
        } elseif ($monto <= 0) {
            $alert = '<div class="alert alert-warning">El monto debe ser mayor a cero.</div>';
        } elseif ($metodo <= 0) {
            $alert = '<div class="alert alert-warning">Seleccioná un modo de pago válido.</div>';
        } elseif (cc_metodo_es_cheque($metodo) && !$hasFinanzas) {
            $alert = '<div class="alert alert-warning">Primero ejecutá la migración financiera desde configuración para usar cheques con recordatorios.</div>';
        } else {
            $movimientoQuery = mysqli_query(
                $conexion,
                "SELECT m.*, cc.id_cliente
                 FROM movimientos_cc m
                 INNER JOIN cuenta_corriente cc ON cc.id = m.id_cuenta_corriente
                 WHERE m.id = $idMovimiento
                 AND cc.id_cliente = $selectedClientId
                 LIMIT 1"
            );
            $movimiento = $movimientoQuery ? mysqli_fetch_assoc($movimientoQuery) : null;
            if (!$movimiento) {
                $alert = '<div class="alert alert-danger">El movimiento seleccionado no pertenece a este cliente.</div>';
            } else {
                mysqli_begin_transaction($conexion);
                try {
                    $resultadoEdicion = cc_editar_movimiento($conexion, $movimiento, $monto, $metodo, $id_user);
                    mysqli_commit($conexion);
                    $alert = '<div class="alert alert-success">Movimiento actualizado. Saldo actual: '
                        . mayorista_formatear_moneda($resultadoEdicion['saldo'] ?? 0)
                        . '.'
                        . htmlspecialchars((string) ($resultadoEdicion['mensaje_extra'] ?? ''))
                        . '</div>';
                } catch (Exception $e) {
                    mysqli_rollback($conexion);
                    $alert = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
}

$clientes = mysqli_query(
    $conexion,
    $schemaReady
        ? "SELECT c.idcliente, c.nombre, c.telefono, c.direccion,"
          . ($hasClienteOptica ? " c.optica," : " '' AS optica,")
          . ($hasClienteLocalidad ? " c.localidad," : " '' AS localidad,")
          . ($hasClienteProvincia ? " c.provincia," : " '' AS provincia,")
          . ($hasClienteDni ? " c.dni," : " '' AS dni,")
          . ($hasClienteCuit ? " c.cuit," : " '' AS cuit,")
          . "
                  cc.id AS cc_id, cc.limite_credito, cc.saldo_actual,
                  MAX(m.fecha) AS ultima_actividad
           FROM cliente c
           LEFT JOIN cuenta_corriente cc ON c.idcliente = cc.id_cliente
           LEFT JOIN movimientos_cc m ON cc.id = m.id_cuenta_corriente
           WHERE c.estado = 1
           GROUP BY c.idcliente, c.nombre, c.telefono, c.direccion,"
           . ($hasClienteOptica ? " c.optica," : '')
           . ($hasClienteLocalidad ? " c.localidad," : '')
           . ($hasClienteProvincia ? " c.provincia," : '')
           . ($hasClienteDni ? " c.dni," : '')
           . ($hasClienteCuit ? " c.cuit," : '')
           . " cc.id, cc.limite_credito, cc.saldo_actual
           ORDER BY c.nombre ASC"
        : "SELECT idcliente, nombre, telefono, direccion FROM cliente WHERE estado = 1 ORDER BY nombre ASC"
);

$clienteActual = null;
$cuentaActual = array('saldo_actual' => 0, 'limite_credito' => 0, 'id' => null);
$movimientos = false;
$columnaMetodoLabel = mayorista_column_exists($conexion, 'metodos', 'descripcion')
    ? 'descripcion'
    : (mayorista_column_exists($conexion, 'metodos', 'metodo') ? 'metodo' : '');
$joinMetodosMovimientos = ($schemaMovimientosCc && $columnaMetodoLabel !== '' && mayorista_table_exists($conexion, 'metodos'))
    ? " LEFT JOIN metodos mt ON m.id_metodo = mt.id "
    : '';
$selectMetodoMovimiento = $schemaMovimientosCc
    ? ($joinMetodosMovimientos !== ''
        ? ", mt.$columnaMetodoLabel AS metodo_nombre"
        : ", NULL AS metodo_nombre")
    : ", NULL AS metodo_nombre, NULL AS id_metodo, NULL AS origen_tipo, NULL AS origen_id";

if ($selectedClientId > 0) {
    $clienteQuery = mysqli_query($conexion, "SELECT * FROM cliente WHERE idcliente = $selectedClientId LIMIT 1");
    $clienteActual = $clienteQuery ? mysqli_fetch_assoc($clienteQuery) : null;
    if ($clienteActual && $schemaReady) {
        $cuentaActual = mayorista_obtener_cuenta_corriente($conexion, $selectedClientId);
        $movimientos = mysqli_query(
            $conexion,
            "SELECT m.*, u.nombre AS usuario_nombre $selectMetodoMovimiento
             FROM movimientos_cc m
             LEFT JOIN usuario u ON m.id_usuario = u.idusuario
             $joinMetodosMovimientos
             WHERE m.id_cuenta_corriente = " . (int) $cuentaActual['id'] . "
             ORDER BY m.fecha DESC"
        );
    }
}

include_once "includes/header.php";
?>
<div class="cc-container">
    <div class="page-header">
        <h2><i class="fas fa-file-invoice-dollar mr-2"></i> Cuenta corriente</h2>
        <p class="mb-0">Gestiona saldo, limite de credito y pagos manuales por cliente.</p>
    </div>

    <?php if (!$schemaReady) { ?>
        <div class="alert alert-warning">
            Falta aplicar `sql/2026_mayorista_armazones.sql` para habilitar este modulo.
        </div>
    <?php } ?>
    <?php if ($schemaReady && !$schemaMovimientosCc) { ?>
        <div class="alert alert-warning">
            Para guardar y editar el modo de pago por movimiento, ejecutá la migración de cuenta corriente desde `configuracion_sistema.php`.
        </div>
    <?php } ?>

    <?php echo $alert; ?>

    <div class="row">
        <div class="col-lg-7">
            <div class="card card-soft">
                <div class="card-header bg-primary text-white">Clientes</div>
                <div class="card-body">
                    <div class="search-box">
                        <label for="buscar_cliente_cc" class="mb-2 d-block">
                            <i class="fas fa-search mr-2"></i>Buscar cliente
                        </label>
                        <input
                            type="text"
                            id="buscar_cliente_cc"
                            class="form-control"
                            placeholder="Nombre, óptica, teléfono, dirección, localidad, DNI o CUIT"
                        >
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover custom-dt-init" id="tbl">
                            <thead class="thead-dark">
                                <tr>
                                    <th class="d-none">Busqueda</th>
                                    <th>Cliente</th>
                                    <th>Telefono</th>
                                    <th>Saldo actual</th>
                                    <th>Limite</th>
                                    <th>Ultima actividad</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($clientes) {
                                    while ($row = mysqli_fetch_assoc($clientes)) {
                                        $saldo = $schemaReady ? (float) ($row['saldo_actual'] ?? 0) : 0;
                                        $limite = $schemaReady ? (float) ($row['limite_credito'] ?? 0) : 0;
                                        $clienteBusquedaRaw = trim(implode(' ', array_filter(array(
                                            $row['nombre'] ?? '',
                                            $row['optica'] ?? '',
                                            $row['telefono'] ?? '',
                                            $row['direccion'] ?? '',
                                            $row['localidad'] ?? '',
                                            $row['provincia'] ?? '',
                                            $row['dni'] ?? '',
                                            $row['cuit'] ?? '',
                                        ))));
                                        $clienteBusqueda = $clienteBusquedaRaw;
                                        $clienteBusquedaAscii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clienteBusquedaRaw);
                                        if ($clienteBusquedaAscii !== false && trim($clienteBusquedaAscii) !== '') {
                                            $clienteBusqueda .= ' ' . $clienteBusquedaAscii;
                                        }
                                ?>
                                    <tr>
                                        <td class="d-none"><?php echo htmlspecialchars($clienteBusqueda); ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($row['nombre']); ?></div>
                                            <?php if (!empty($row['optica'])) { ?>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($row['optica']); ?></small>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['telefono'] ?? '-'); ?></td>
                                        <td><?php echo mayorista_formatear_moneda($saldo); ?></td>
                                        <td><?php echo mayorista_formatear_moneda($limite); ?></td>
                                        <td><?php echo !empty($row['ultima_actividad']) ? date('d/m/Y H:i', strtotime($row['ultima_actividad'])) : '-'; ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary" href="cuenta_corriente.php?cliente=<?php echo (int) $row['idcliente']; ?>">
                                                Ver
                                            </a>
                                        </td>
                                    </tr>
                                <?php }
                                } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card card-soft">
                <div class="card-header bg-success text-white">Ficha del cliente</div>
                <div class="card-body">
                    <?php if (!$clienteActual) { ?>
                        <p class="text-muted mb-0">Selecciona un cliente para ver su cuenta corriente.</p>
                    <?php } else { ?>
                        <h4><?php echo htmlspecialchars($clienteActual['nombre']); ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($clienteActual['telefono'] ?: '-'); ?> | <?php echo htmlspecialchars($clienteActual['direccion'] ?: '-'); ?></p>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <div class="metric-box">
                                    <span>Saldo actual</span>
                                    <strong><?php echo mayorista_formatear_moneda($cuentaActual['saldo_actual']); ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="metric-box">
                                    <span>Limite</span>
                                    <strong><?php echo mayorista_formatear_moneda($cuentaActual['limite_credito']); ?></strong>
                                </div>
                            </div>
                        </div>

                        <?php if ($esAdmin) { ?>
                            <form method="post" class="mb-4">
                                <input type="hidden" name="action" value="guardar_limite">
                                <input type="hidden" name="id_cliente" value="<?php echo (int) $clienteActual['idcliente']; ?>">
                                <div class="form-group">
                                    <label>Limite de credito</label>
                                    <input type="number" name="limite_credito" step="0.01" min="0" class="form-control" value="<?php echo (float) $cuentaActual['limite_credito']; ?>">
                                    <small class="form-text text-muted">Si queda en 0, la cuenta corriente se considera sin limite configurado.</small>
                                </div>
                                <button class="btn btn-primary" type="submit">Guardar limite</button>
                            </form>
                        <?php } else { ?>
                            <div class="alert alert-secondary mb-4">
                                Solo el administrador puede editar el limite de credito. El valor actual se muestra como referencia.
                            </div>
                        <?php } ?>

                        <form method="post">
                            <input type="hidden" name="action" value="registrar_pago">
                            <input type="hidden" name="id_cliente" value="<?php echo (int) $clienteActual['idcliente']; ?>">
                            <div class="form-group">
                                <label>Monto del pago</label>
                                <input type="number" name="monto" step="0.01" min="0.01" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Metodo de pago</label>
                                <select name="metodo_pago" id="metodo_pago_cc" class="form-control">
                                    <?php foreach ($metodosPago as $idMetodo => $labelMetodo) { ?>
                                        <option value="<?php echo (int) $idMetodo; ?>"><?php echo htmlspecialchars($labelMetodo); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div id="cc_cheque_fields" class="border rounded p-3 mb-3" style="display:none;">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Plazo del cheque</label>
                                        <select name="cheque_plazo_dias" id="cc_cheque_plazo_dias" class="form-control">
                                            <option value="30">30 días</option>
                                            <option value="60">60 días</option>
                                            <option value="90">90 días</option>
                                            <option value="120">120 días</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Fecha base</label>
                                        <input type="date" name="cheque_fecha_base" id="cc_cheque_fecha_base" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Fecha esperada de depósito</label>
                                        <input type="date" name="cheque_fecha_deposito" id="cc_cheque_fecha_deposito" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                                    </div>
                                </div>
                                <small class="text-muted">El saldo de la cuenta se actualiza ahora y el ingreso se confirmará desde recordatorios cuando el cheque se deposite.</small>
                            </div>
                            <div class="form-group">
                                <label>Descripcion</label>
                                <input type="text" name="descripcion" class="form-control" placeholder="Pago manual de cuenta corriente">
                            </div>
                            <div class="d-flex justify-content-between">
                                <button class="btn btn-success" type="submit">Registrar pago</button>
                                <?php if ($schemaReady) { ?>
                                    <a class="btn btn-outline-danger" target="_blank" href="pdf/cuenta_corriente.php?cliente=<?php echo (int) $clienteActual['idcliente']; ?>">
                                        Exportar PDF
                                    </a>
                                <?php } ?>
                            </div>
                        </form>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($clienteActual && $schemaReady) { ?>
        <div class="card card-soft">
            <div class="card-header bg-dark text-white">Movimientos</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Descripcion</th>
                                <th>Modo de pago</th>
                                <th>Monto</th>
                                <th>Usuario</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($movimientos && mysqli_num_rows($movimientos) > 0) {
                                while ($mov = mysqli_fetch_assoc($movimientos)) { ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha'])); ?></td>
                                        <td><?php echo ucfirst($mov['tipo']); ?></td>
                                        <td><?php echo htmlspecialchars($mov['descripcion']); ?></td>
                                        <td><?php echo htmlspecialchars($mov['metodo_nombre'] ?: mayorista_metodo_pago_etiqueta($conexion, (int) ($mov['id_metodo'] ?? 0)) ?: '-'); ?></td>
                                        <td><?php echo mayorista_formatear_moneda($mov['monto']); ?></td>
                                        <td><?php echo htmlspecialchars($mov['usuario_nombre'] ?: '-'); ?></td>
                                        <td class="text-right">
                                            <?php if ($schemaMovimientosCc) { ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary js-editar-movimiento"
                                                    data-id="<?php echo (int) $mov['id']; ?>"
                                                    data-monto="<?php echo htmlspecialchars(number_format((float) $mov['monto'], 2, '.', '')); ?>"
                                                    data-metodo="<?php echo (int) ($mov['id_metodo'] ?? 0); ?>"
                                                    data-descripcion="<?php echo htmlspecialchars($mov['descripcion']); ?>"
                                                    data-tipo="<?php echo htmlspecialchars($mov['tipo']); ?>"
                                                >
                                                    Editar
                                                </button>
                                            <?php } else { ?>
                                                <span class="text-muted">-</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                            <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Todavia no hay movimientos para este cliente.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<?php if ($clienteActual && $schemaReady && $schemaMovimientosCc) { ?>
    <div class="modal fade" id="modalEditarMovimientoCc" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar movimiento</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar_movimiento">
                        <input type="hidden" name="id_cliente" value="<?php echo (int) $clienteActual['idcliente']; ?>">
                        <input type="hidden" name="id_movimiento" id="editar_movimiento_id">
                        <div class="form-group">
                            <label>Movimiento</label>
                            <input type="text" id="editar_movimiento_resumen" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Monto</label>
                            <input type="number" name="monto" id="editar_movimiento_monto" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group mb-0">
                            <label>Modo de pago</label>
                            <select name="metodo_pago" id="editar_movimiento_metodo" class="form-control" required>
                                <?php foreach ($metodosPago as $idMetodo => $labelMetodo) { ?>
                                    <option value="<?php echo (int) $idMetodo; ?>"><?php echo htmlspecialchars($labelMetodo); ?></option>
                                <?php } ?>
                            </select>
                            <small class="form-text text-muted">Si cambiás un cargo de venta, también se ajustará la venta asociada y su registro financiero.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php } ?>

<script>
window.addEventListener('load', function () {
    const $ = window.jQuery;
    if (!$) {
        return;
    }

    const $input = $('#buscar_cliente_cc');
    const $table = $('#tbl');

    if ($.fn.DataTable && $table.length && !$.fn.DataTable.isDataTable($table)) {
        $table.DataTable({
            pageLength: 10,
            dom: 'tip',
            columnDefs: [
                { targets: 0, visible: false, searchable: true, orderable: false },
                { targets: -1, orderable: false }
            ],
            order: [[1, 'asc']]
        });
    }

    $input.on('input', function () {
        const value = ($(this).val() || '').toString();
        if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
            $table.DataTable().search(value).draw();
            return;
        }

        const term = value.toLowerCase();
        $table.find('tbody tr').each(function () {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(term) !== -1);
        });
    });

    function actualizarCamposChequeCc() {
        const esCheque = ($('#metodo_pago_cc').val() || '') === '5';
        const $campos = $('#cc_cheque_fields');
        $campos.toggle(esCheque);
        if (!esCheque) {
            return;
        }

        const fechaBase = $('#cc_cheque_fecha_base').val() || new Date().toISOString().slice(0, 10);
        const plazo = parseInt($('#cc_cheque_plazo_dias').val(), 10) || 30;
        const fecha = new Date(fechaBase + 'T00:00:00');
        fecha.setDate(fecha.getDate() + plazo);
        const yyyy = fecha.getFullYear();
        const mm = String(fecha.getMonth() + 1).padStart(2, '0');
        const dd = String(fecha.getDate()).padStart(2, '0');
        $('#cc_cheque_fecha_deposito').val(yyyy + '-' + mm + '-' + dd);
    }

    $('#metodo_pago_cc').on('change', actualizarCamposChequeCc);
    $('#cc_cheque_plazo_dias, #cc_cheque_fecha_base').on('change', actualizarCamposChequeCc);
    actualizarCamposChequeCc();

    $(document).on('click', '.js-editar-movimiento', function () {
        const $button = $(this);
        $('#editar_movimiento_id').val($button.data('id') || '');
        $('#editar_movimiento_monto').val($button.data('monto') || '');
        $('#editar_movimiento_metodo').val(String($button.data('metodo') || '1'));
        $('#editar_movimiento_resumen').val(
            (($button.data('tipo') || '').toString() + ' - ' + ($button.data('descripcion') || '').toString()).trim()
        );
        $('#modalEditarMovimientoCc').modal('show');
    });
});
</script>

<?php include_once "includes/footer.php"; ?>
