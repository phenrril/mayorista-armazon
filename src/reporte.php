<?php
session_start();
include "../conexion.php";
require_once "includes/mayorista_helpers.php";

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('reportes', 'reporte'));

$hasTipoVenta = mayorista_column_exists($conexion, 'ventas', 'tipo_venta');
$hasCcTables = mayorista_table_exists($conexion, 'cuenta_corriente') && mayorista_table_exists($conexion, 'movimientos_cc');
$hasEgresos = mayorista_table_exists($conexion, 'egresos');
$hasIngresosDescripcion = mayorista_column_exists($conexion, 'ingresos', 'descripcion');
$hasEgresosDescripcion = $hasEgresos && mayorista_column_exists($conexion, 'egresos', 'descripcion');
$hasFinanzas = mayorista_schema_finanzas_operativas_listo($conexion);
$tesoreriaManualDisponible = $hasIngresosDescripcion && $hasEgresosDescripcion;
$alert = '';

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$tipo = $_GET['tipo'] ?? 'todas';

if (!mayorista_fecha_iso_valida($desde)) {
    $desde = date('Y-m-01');
}

if (!mayorista_fecha_iso_valida($hasta)) {
    $hasta = date('Y-m-d');
}

if ($desde > $hasta) {
    $tmp = $desde;
    $desde = $hasta;
    $hasta = $tmp;
}

if ($hasFinanzas) {
    mysqli_query(
        $conexion,
        "UPDATE compromisos_financieros
         SET estado = 'vencido', updated_at = NOW()
         WHERE estado IN ('pendiente', 'parcial')
         AND saldo_pendiente > 0
         AND fecha_vencimiento < CURDATE()"
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';

    try {
        if ($accion === 'guardar_movimiento_manual') {
            if (!$tesoreriaManualDisponible) {
                throw new RuntimeException('Primero ejecutá la migración financiera para habilitar descripción libre en tesorería.');
            }
            mayorista_registrar_movimiento_tesoreria(
                $conexion,
                $_POST['tipo_movimiento'] ?? '',
                $_POST['monto_movimiento'] ?? '',
                $_POST['descripcion_movimiento'] ?? '',
                $_POST['fecha_movimiento'] ?? date('Y-m-d')
            );
            $alert = '<div class="alert alert-success">Movimiento manual registrado correctamente.</div>';
        } elseif ($accion === 'guardar_compromiso' && $hasFinanzas) {
            mysqli_begin_transaction($conexion);

            $tipoCompromiso = $_POST['tipo_compromiso'] ?? '';
            $fechaCompromiso = $_POST['fecha_compromiso'] ?? date('Y-m-d');
            $fechaVencimiento = $_POST['fecha_vencimiento'] ?? $fechaCompromiso;
            $fechaDeposito = trim((string) ($_POST['fecha_deposito'] ?? ''));
            $estado = in_array($tipoCompromiso, array('cheque_recibido', 'cheque_emitido'), true) ? 'pendiente_confirmacion' : 'pendiente';
            $idProveedor = 0;

            if (in_array($tipoCompromiso, array('deuda_proveedor', 'cheque_emitido'), true)) {
                $idProveedor = mayorista_obtener_o_crear_proveedor($conexion, $_POST['proveedor_nombre'] ?? '');
            }
            if ($fechaVencimiento < date('Y-m-d') && $estado === 'pendiente') {
                $estado = 'vencido';
            }

            mayorista_registrar_compromiso_financiero($conexion, array(
                'tipo' => $tipoCompromiso,
                'id_cliente' => (int) ($_POST['id_cliente'] ?? 0),
                'id_proveedor' => $idProveedor,
                'id_metodo' => (int) ($_POST['id_metodo'] ?? 1),
                'monto_total' => $_POST['monto_compromiso'] ?? '',
                'saldo_pendiente' => $_POST['monto_compromiso'] ?? '',
                'estado' => $estado,
                'fecha_compromiso' => $fechaCompromiso,
                'fecha_vencimiento' => $fechaVencimiento,
                'fecha_deposito' => $fechaDeposito !== '' ? $fechaDeposito : null,
                'descripcion' => $_POST['descripcion_compromiso'] ?? '',
                'observaciones' => $_POST['observaciones_compromiso'] ?? '',
                'id_usuario' => $id_user,
            ));

            mysqli_commit($conexion);
            $alert = '<div class="alert alert-success">Compromiso financiero registrado correctamente.</div>';
        } elseif ($accion === 'registrar_pago_compromiso' && $hasFinanzas) {
            mysqli_begin_transaction($conexion);
            $idCompromiso = (int) ($_POST['id_compromiso'] ?? 0);
            $queryCompromiso = mysqli_query(
                $conexion,
                "SELECT *
                 FROM compromisos_financieros
                 WHERE id = $idCompromiso
                 LIMIT 1"
            );
            $compromiso = $queryCompromiso ? mysqli_fetch_assoc($queryCompromiso) : null;
            if (!$compromiso) {
                throw new InvalidArgumentException('No se encontró el compromiso indicado.');
            }

            $nuevoSaldo = mayorista_registrar_pago_compromiso(
                $conexion,
                $idCompromiso,
                $_POST['monto_pago_compromiso'] ?? '',
                $_POST['fecha_pago_compromiso'] ?? date('Y-m-d'),
                $_POST['descripcion_pago_compromiso'] ?? 'Pago de compromiso',
                $id_user,
                (int) ($_POST['id_metodo_pago_compromiso'] ?? 1)
            );

            if (in_array($compromiso['tipo'], array('deuda_proveedor', 'compromiso_pago'), true)) {
                mayorista_registrar_movimiento_tesoreria(
                    $conexion,
                    'egreso',
                    $_POST['monto_pago_compromiso'] ?? '',
                    $_POST['descripcion_pago_compromiso'] ?? ('Pago de ' . ($compromiso['descripcion'] ?? 'compromiso')),
                    $_POST['fecha_pago_compromiso'] ?? date('Y-m-d'),
                    (int) ($compromiso['id_cliente'] ?? 0),
                    (int) ($_POST['id_metodo_pago_compromiso'] ?? 1)
                );
            }

            mysqli_commit($conexion);
            $alert = '<div class="alert alert-success">Pago registrado. Saldo restante: ' . mayorista_formatear_moneda($nuevoSaldo) . '.</div>';
        } elseif ($accion === 'confirmar_cheque' && $hasFinanzas) {
            mysqli_begin_transaction($conexion);
            mayorista_confirmar_cheque_recibido($conexion, (int) ($_POST['id_compromiso'] ?? 0), $_POST['fecha_confirmacion'] ?? date('Y-m-d'));
            mysqli_commit($conexion);
            $alert = '<div class="alert alert-success">Cheque confirmado e impactado como ingreso.</div>';
        } elseif ($accion === 'confirmar_cheque_emitido' && $hasFinanzas) {
            mysqli_begin_transaction($conexion);
            mayorista_confirmar_cheque_emitido($conexion, (int) ($_POST['id_compromiso'] ?? 0), $_POST['fecha_confirmacion'] ?? date('Y-m-d'));
            mysqli_commit($conexion);
            $alert = '<div class="alert alert-success">Cheque emitido confirmado e impactado como egreso.</div>';
        } elseif ($accion === 'posponer_recordatorio' && $hasFinanzas) {
            mayorista_diferir_recordatorio_compromiso($conexion, (int) ($_POST['id_compromiso'] ?? 0));
            $alert = '<div class="alert alert-info">El recordatorio se volverá a mostrar mañana.</div>';
        }
    } catch (Exception $e) {
        if (mysqli_errno($conexion) || mysqli_error($conexion)) {
            @mysqli_rollback($conexion);
        }
        $alert = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

$whereVentas = "DATE(v.fecha) BETWEEN '$desde' AND '$hasta'";
if ($hasTipoVenta && in_array($tipo, array('mayorista', 'minorista'), true)) {
    $whereVentas .= " AND v.tipo_venta = '" . mysqli_real_escape_string($conexion, $tipo) . "'";
}

$ventasResumen = mysqli_fetch_assoc(mysqli_query(
    $conexion,
    "SELECT COUNT(*) AS operaciones, IFNULL(SUM(v.total),0) AS total
     FROM ventas v
     WHERE $whereVentas"
));

$ccResumen = $hasCcTables
    ? mysqli_fetch_assoc(mysqli_query(
        $conexion,
        "SELECT
            IFNULL(SUM(CASE WHEN m.tipo = 'cargo' THEN m.monto ELSE 0 END),0) AS cargos,
            IFNULL(SUM(CASE WHEN m.tipo = 'pago' THEN m.monto ELSE 0 END),0) AS pagos
         FROM movimientos_cc m
         WHERE DATE(m.fecha) BETWEEN '$desde' AND '$hasta'"
    ))
    : array('cargos' => 0, 'pagos' => 0);

$clientesMora = $hasCcTables
    ? mysqli_query(
        $conexion,
        "SELECT c.nombre, cc.saldo_actual, cc.limite_credito
         FROM cuenta_corriente cc
         INNER JOIN cliente c ON cc.id_cliente = c.idcliente
         WHERE cc.saldo_actual > 0
         ORDER BY cc.saldo_actual DESC
         LIMIT 10"
    )
    : false;

$productosReporte = mysqli_query(
    $conexion,
    "SELECT p.descripcion, p.existencia, SUM(d.cantidad) AS vendidos, SUM(d.cantidad * d.precio) AS monto
     FROM detalle_venta d
     INNER JOIN ventas v ON d.id_venta = v.id
     INNER JOIN producto p ON d.id_producto = p.codproducto
     WHERE $whereVentas
     GROUP BY d.id_producto, p.descripcion, p.existencia
     ORDER BY vendidos DESC
     LIMIT 10"
);

$clientesTop = mysqli_query(
    $conexion,
    "SELECT c.nombre, COUNT(v.id) AS operaciones, SUM(v.total) AS volumen
     FROM ventas v
     INNER JOIN cliente c ON v.id_cliente = c.idcliente
     WHERE $whereVentas
     GROUP BY v.id_cliente, c.nombre
     ORDER BY volumen DESC
     LIMIT 10"
);

$ingresos = mysqli_fetch_assoc(mysqli_query(
    $conexion,
    "SELECT IFNULL(SUM(ingresos),0) AS total
     FROM ingresos
     WHERE DATE(fecha) BETWEEN '$desde' AND '$hasta'"
));

$egresos = $hasEgresos
    ? mysqli_fetch_assoc(mysqli_query(
        $conexion,
        "SELECT IFNULL(SUM(egresos),0) AS total
         FROM egresos
         WHERE DATE(fecha) BETWEEN '$desde' AND '$hasta'"
    ))
    : array('total' => 0);

$clientesActivos = mysqli_query(
    $conexion,
    "SELECT idcliente, nombre
     FROM cliente
     WHERE estado = 1
     ORDER BY nombre ASC"
);

$descripcionIngresoSql = $hasIngresosDescripcion ? 'descripcion' : "''";
$descripcionEgresoSql = $hasEgresosDescripcion ? 'descripcion' : "''";

$movimientosTesoreria = $hasEgresos
    ? mysqli_query(
        $conexion,
        "(SELECT 'Ingreso' AS tipo, ingresos AS monto, $descripcionIngresoSql AS descripcion, fecha
          FROM ingresos
          WHERE DATE(fecha) BETWEEN '$desde' AND '$hasta')
         UNION ALL
         (SELECT 'Egreso' AS tipo, ABS(egresos) AS monto, $descripcionEgresoSql AS descripcion, fecha
          FROM egresos
          WHERE DATE(fecha) BETWEEN '$desde' AND '$hasta')
         ORDER BY fecha DESC
         LIMIT 20"
    )
    : false;

$alertasFinancieras = $hasFinanzas ? mayorista_obtener_alertas_financieras($conexion, 12) : array();
$compromisosFinancieros = $hasFinanzas
    ? mysqli_query(
        $conexion,
        "SELECT cf.*, c.nombre AS cliente_nombre, p.nombre AS proveedor_nombre,
                IFNULL((SELECT SUM(cp.monto) FROM compromisos_financieros_pagos cp WHERE cp.id_compromiso = cf.id), 0) AS total_pagado
         FROM compromisos_financieros cf
         LEFT JOIN cliente c ON cf.id_cliente = c.idcliente
         LEFT JOIN proveedores p ON cf.id_proveedor = p.id
         ORDER BY
             CASE WHEN cf.estado IN ('pendiente_confirmacion', 'vencido') THEN 0 ELSE 1 END,
             COALESCE(cf.fecha_deposito, cf.fecha_vencimiento) ASC,
             cf.id DESC
         LIMIT 30"
    )
    : false;

include_once "includes/header.php";
?>
<div class="reportes-container">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h2><i class="fas fa-chart-bar mr-2"></i> Reportes</h2>
            <p class="mb-0">KPIs de ventas, cuenta corriente, productos y tesoreria en una sola vista.</p>
        </div>
        <a class="btn btn-light mt-3 mt-md-0" target="_blank" href="pdf/reporte_general.php?desde=<?php echo urlencode($desde); ?>&hasta=<?php echo urlencode($hasta); ?>&tipo=<?php echo urlencode($tipo); ?>">
            <i class="fas fa-file-pdf mr-1"></i> Exportar PDF
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form class="form-row">
                <div class="form-group col-md-3">
                    <label>Desde</label>
                    <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($desde); ?>">
                </div>
                <div class="form-group col-md-3">
                    <label>Hasta</label>
                    <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($hasta); ?>">
                </div>
                <div class="form-group col-md-3">
                    <label>Tipo de venta</label>
                    <select name="tipo" class="form-control">
                        <option value="todas" <?php echo $tipo === 'todas' ? 'selected' : ''; ?>>Todas</option>
                        <option value="mayorista" <?php echo $tipo === 'mayorista' ? 'selected' : ''; ?>>Mayorista</option>
                        <option value="minorista" <?php echo $tipo === 'minorista' ? 'selected' : ''; ?>>Minorista</option>
                    </select>
                </div>
                <div class="form-group col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary btn-block" type="submit">Aplicar filtros</button>
                </div>
            </form>
        </div>
    </div>

    <?php echo $alert; ?>

    <div class="row">
        <div class="col-md-3"><div class="card kpi-card"><div class="card-body"><span>Ventas</span><strong><?php echo $ventasResumen['operaciones']; ?></strong><?php echo mayorista_formatear_moneda($ventasResumen['total']); ?></div></div></div>
        <div class="col-md-3"><div class="card kpi-card"><div class="card-body"><span>Cobrado</span><strong><?php echo mayorista_formatear_moneda($ingresos['total']); ?></strong>Ingresos realmente registrados</div></div></div>
        <div class="col-md-3"><div class="card kpi-card"><div class="card-body"><span>Cargos CC</span><strong><?php echo mayorista_formatear_moneda($ccResumen['cargos']); ?></strong>Deuda generada</div></div></div>
        <div class="col-md-3"><div class="card kpi-card"><div class="card-body"><span>Pagos CC</span><strong><?php echo mayorista_formatear_moneda($ccResumen['pagos']); ?></strong>Cobranzas del periodo</div></div></div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card kpi-card">
                <div class="card-header bg-success text-white">Productos</div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead><tr><th>Producto</th><th>Stock</th><th>Vendidos</th><th>Monto</th></tr></thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($productosReporte)) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                                    <td><?php echo (int) $row['existencia']; ?></td>
                                    <td><?php echo (float) $row['vendidos']; ?></td>
                                    <td><?php echo mayorista_formatear_moneda($row['monto']); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card kpi-card">
                <div class="card-header bg-info text-white">Clientes top</div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead><tr><th>Cliente</th><th>Operaciones</th><th>Volumen</th></tr></thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($clientesTop)) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                                    <td><?php echo (int) $row['operaciones']; ?></td>
                                    <td><?php echo mayorista_formatear_moneda($row['volumen']); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card kpi-card">
                <div class="card-header bg-warning text-white">Clientes en mora</div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead><tr><th>Cliente</th><th>Saldo</th><th>Limite</th></tr></thead>
                        <tbody>
                            <?php if ($clientesMora && mysqli_num_rows($clientesMora) > 0) {
                                while ($row = mysqli_fetch_assoc($clientesMora)) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                                        <td><?php echo mayorista_formatear_moneda($row['saldo_actual']); ?></td>
                                        <td><?php echo mayorista_formatear_moneda($row['limite_credito']); ?></td>
                                    </tr>
                            <?php }
                            } else { ?>
                                <tr><td colspan="3" class="text-center text-muted">Sin deuda pendiente registrada.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card kpi-card">
                <div class="card-header bg-dark text-white">Tesoreria</div>
                <div class="card-body">
                    <div class="mb-3">
                        <span>Ingresos del periodo</span>
                        <strong><?php echo mayorista_formatear_moneda($ingresos['total']); ?></strong>
                    </div>
                    <div class="mb-3">
                        <span>Egresos del periodo</span>
                        <strong><?php echo mayorista_formatear_moneda($egresos['total']); ?></strong>
                    </div>
                    <div>
                        <span>Resultado operativo</span>
                        <strong><?php echo mayorista_formatear_moneda((float) $ingresos['total'] - (float) $egresos['total']); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-primary text-white">Tesorería manual</div>
                <div class="card-body">
                    <?php if (!$tesoreriaManualDisponible) { ?>
                    <div class="alert alert-warning">
                        Para usar ingresos y egresos con descripción libre, ejecutá primero la migración financiera desde configuración.
                    </div>
                    <?php } ?>
                    <form method="post">
                        <input type="hidden" name="action" value="guardar_movimiento_manual">
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Tipo</label>
                                <select name="tipo_movimiento" class="form-control" required>
                                    <option value="ingreso">Ingreso</option>
                                    <option value="egreso">Egreso</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Fecha</label>
                                <input type="date" name="fecha_movimiento" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Monto</label>
                                <input type="number" step="0.01" min="0.01" name="monto_movimiento" class="form-control" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Descripción</label>
                                <input type="text" name="descripcion_movimiento" class="form-control" maxlength="255" placeholder="Ayudamemoria" required>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit" <?php echo !$tesoreriaManualDisponible ? 'disabled' : ''; ?>>Guardar movimiento</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <?php if ($hasFinanzas) { ?>
            <div class="card">
                <div class="card-header bg-secondary text-white">Compromisos y cheques</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="guardar_compromiso">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Tipo</label>
                                <select name="tipo_compromiso" class="form-control" required>
                                    <option value="cheque_recibido">Cheque recibido</option>
                                    <option value="cheque_emitido">Cheque emitido</option>
                                    <option value="deuda_proveedor">Deuda con proveedor</option>
                                    <option value="compromiso_pago">Compromiso de pago</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Cliente</label>
                                <select name="id_cliente" class="form-control">
                                    <option value="0">Sin cliente</option>
                                    <?php if ($clientesActivos) {
                                        while ($clienteOption = mysqli_fetch_assoc($clientesActivos)) { ?>
                                            <option value="<?php echo (int) $clienteOption['idcliente']; ?>">
                                                <?php echo htmlspecialchars($clienteOption['nombre']); ?>
                                            </option>
                                    <?php }
                                        mysqli_data_seek($clientesActivos, 0);
                                    } ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Proveedor</label>
                                <input type="text" name="proveedor_nombre" class="form-control" maxlength="150" placeholder="Solo para deudas o cheques emitidos">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Monto</label>
                                <input type="number" step="0.01" min="0.01" name="monto_compromiso" class="form-control" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Fecha base</label>
                                <input type="date" name="fecha_compromiso" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Vencimiento</label>
                                <input type="date" name="fecha_vencimiento" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Depósito cheque</label>
                                <input type="date" name="fecha_deposito" class="form-control">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Método</label>
                                <select name="id_metodo" class="form-control">
                                    <option value="1">Efectivo</option>
                                    <option value="2">Crédito</option>
                                    <option value="3">Débito</option>
                                    <option value="4">Transferencia</option>
                                    <option value="5">Cheque</option>
                                </select>
                            </div>
                            <div class="form-group col-md-8">
                                <label>Descripción</label>
                                <input type="text" name="descripcion_compromiso" class="form-control" maxlength="255" placeholder="Ej: cheque 60 días Banco Nación" required>
                            </div>
                            <div class="form-group col-md-12">
                                <label>Observaciones</label>
                                <input type="text" name="observaciones_compromiso" class="form-control" maxlength="500">
                            </div>
                        </div>
                        <button class="btn btn-secondary" type="submit">Guardar compromiso</button>
                    </form>
                </div>
            </div>
            <?php } else { ?>
            <div class="alert alert-warning mb-0">
                Ejecutá la migración financiera desde configuración para habilitar cheques, compromisos y deudas con proveedores.
            </div>
            <?php } ?>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-dark text-white">Movimientos manuales del período</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Descripción</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($movimientosTesoreria && mysqli_num_rows($movimientosTesoreria) > 0) {
                                    while ($mov = mysqli_fetch_assoc($movimientosTesoreria)) { ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($mov['fecha'])); ?></td>
                                            <td><?php echo htmlspecialchars($mov['tipo']); ?></td>
                                            <td><?php echo htmlspecialchars($mov['descripcion'] ?? '-'); ?></td>
                                            <td><?php echo mayorista_formatear_moneda($mov['monto']); ?></td>
                                        </tr>
                                <?php }
                                } else { ?>
                                    <tr><td colspan="4" class="text-center text-muted">Sin movimientos manuales en el período.</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card" id="vencimientos-financieros">
                <div class="card-header bg-danger text-white">Recordatorios pendientes</div>
                <div class="card-body">
                    <?php if ($hasFinanzas && !empty($alertasFinancieras)) { ?>
                        <?php foreach ($alertasFinancieras as $recordatorio) { ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between flex-wrap">
                                    <strong><?php echo htmlspecialchars($recordatorio['descripcion']); ?></strong>
                                    <span class="badge badge-<?php echo $recordatorio['estado'] === 'pendiente_confirmacion' ? 'warning' : 'danger'; ?>">
                                        <?php echo htmlspecialchars($recordatorio['estado']); ?>
                                    </span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <?php if (!empty($recordatorio['cliente_nombre'])) { ?>Cliente: <?php echo htmlspecialchars($recordatorio['cliente_nombre']); ?> | <?php } ?>
                                    <?php if (!empty($recordatorio['proveedor_nombre'])) { ?>Proveedor: <?php echo htmlspecialchars($recordatorio['proveedor_nombre']); ?> | <?php } ?>
                                    Vence: <?php echo !empty($recordatorio['fecha_vencimiento']) ? date('d/m/Y', strtotime($recordatorio['fecha_vencimiento'])) : '-'; ?>
                                    <?php if (!empty($recordatorio['fecha_deposito'])) { ?> | Depósito: <?php echo date('d/m/Y', strtotime($recordatorio['fecha_deposito'])); ?><?php } ?>
                                </div>
                                <div class="mb-2">Saldo pendiente: <strong><?php echo mayorista_formatear_moneda($recordatorio['saldo_pendiente']); ?></strong></div>
                                <div class="d-flex flex-wrap">
                                    <?php if ($recordatorio['tipo'] === 'cheque_recibido' && $recordatorio['estado'] === 'pendiente_confirmacion') { ?>
                                    <form method="post" class="mr-2 mb-2">
                                        <input type="hidden" name="action" value="confirmar_cheque">
                                        <input type="hidden" name="id_compromiso" value="<?php echo (int) $recordatorio['id']; ?>">
                                        <input type="hidden" name="fecha_confirmacion" value="<?php echo date('Y-m-d'); ?>">
                                        <button class="btn btn-sm btn-success" type="submit">Confirmar depósito</button>
                                    </form>
                                    <?php } elseif ($recordatorio['tipo'] === 'cheque_emitido' && $recordatorio['estado'] === 'pendiente_confirmacion') { ?>
                                    <form method="post" class="mr-2 mb-2">
                                        <input type="hidden" name="action" value="confirmar_cheque_emitido">
                                        <input type="hidden" name="id_compromiso" value="<?php echo (int) $recordatorio['id']; ?>">
                                        <input type="hidden" name="fecha_confirmacion" value="<?php echo date('Y-m-d'); ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">Confirmar débito</button>
                                    </form>
                                    <?php } ?>
                                    <form method="post" class="mb-2">
                                        <input type="hidden" name="action" value="posponer_recordatorio">
                                        <input type="hidden" name="id_compromiso" value="<?php echo (int) $recordatorio['id']; ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Recordar mañana</button>
                                    </form>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <p class="text-muted mb-0">No hay recordatorios financieros pendientes para hoy.</p>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($hasFinanzas) { ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">Compromisos financieros activos</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Referencia</th>
                                    <th>Fechas</th>
                                    <th>Total</th>
                                    <th>Saldo</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($compromisosFinancieros && mysqli_num_rows($compromisosFinancieros) > 0) {
                                    while ($comp = mysqli_fetch_assoc($compromisosFinancieros)) { ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($comp['tipo']))); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($comp['descripcion']); ?>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($comp['cliente_nombre'] ?: ($comp['proveedor_nombre'] ?: '-')); ?>
                                                </div>
                                            </td>
                                            <td>
                                                Base: <?php echo date('d/m/Y', strtotime($comp['fecha_compromiso'])); ?><br>
                                                Vence: <?php echo date('d/m/Y', strtotime($comp['fecha_vencimiento'])); ?>
                                                <?php if (!empty($comp['fecha_deposito'])) { ?><br>Depósito: <?php echo date('d/m/Y', strtotime($comp['fecha_deposito'])); ?><?php } ?>
                                            </td>
                                            <td><?php echo mayorista_formatear_moneda($comp['monto_total']); ?></td>
                                            <td><?php echo mayorista_formatear_moneda($comp['saldo_pendiente']); ?></td>
                                            <td><?php echo htmlspecialchars($comp['estado']); ?></td>
                                            <td>
                                                <?php if ($comp['tipo'] === 'cheque_recibido' && $comp['estado'] === 'pendiente_confirmacion') { ?>
                                                    <form method="post" class="mb-2">
                                                        <input type="hidden" name="action" value="confirmar_cheque">
                                                        <input type="hidden" name="id_compromiso" value="<?php echo (int) $comp['id']; ?>">
                                                        <input type="hidden" name="fecha_confirmacion" value="<?php echo date('Y-m-d'); ?>">
                                                        <button class="btn btn-sm btn-success" type="submit">Confirmar</button>
                                                    </form>
                                                <?php } elseif ($comp['tipo'] === 'cheque_emitido' && $comp['estado'] === 'pendiente_confirmacion') { ?>
                                                    <form method="post" class="mb-2">
                                                        <input type="hidden" name="action" value="confirmar_cheque_emitido">
                                                        <input type="hidden" name="id_compromiso" value="<?php echo (int) $comp['id']; ?>">
                                                        <input type="hidden" name="fecha_confirmacion" value="<?php echo date('Y-m-d'); ?>">
                                                        <button class="btn btn-sm btn-danger" type="submit">Confirmar débito</button>
                                                    </form>
                                                <?php } elseif ((float) $comp['saldo_pendiente'] > 0.009 && in_array($comp['tipo'], array('deuda_proveedor', 'compromiso_pago'), true)) { ?>
                                                    <form method="post" class="form-inline">
                                                        <input type="hidden" name="action" value="registrar_pago_compromiso">
                                                        <input type="hidden" name="id_compromiso" value="<?php echo (int) $comp['id']; ?>">
                                                        <input type="hidden" name="fecha_pago_compromiso" value="<?php echo date('Y-m-d'); ?>">
                                                        <input type="hidden" name="id_metodo_pago_compromiso" value="<?php echo (int) ($comp['id_metodo'] ?: 1); ?>">
                                                        <input type="text" name="descripcion_pago_compromiso" class="form-control form-control-sm mr-2 mb-2" placeholder="Pago parcial" value="Pago compromiso #<?php echo (int) $comp['id']; ?>">
                                                        <input type="number" step="0.01" min="0.01" max="<?php echo (float) $comp['saldo_pendiente']; ?>" name="monto_pago_compromiso" class="form-control form-control-sm mr-2 mb-2" placeholder="Monto" required>
                                                        <button class="btn btn-sm btn-primary mb-2" type="submit">Registrar pago</button>
                                                    </form>
                                                <?php } else { ?>
                                                    <span class="text-muted">Sin acción</span>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                <?php }
                                } else { ?>
                                    <tr><td colspan="7" class="text-center text-muted">No hay compromisos financieros cargados.</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<?php include_once "includes/footer.php"; ?>
