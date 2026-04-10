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
$alert = '';
$selectedClientId = isset($_GET['cliente']) ? (int) $_GET['cliente'] : 0;

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

                $saldo = mayorista_registrar_movimiento_cc(
                    $conexion,
                    $selectedClientId,
                    'pago',
                    $monto,
                    $descripcion,
                    $id_user,
                    null
                );

                $referencia = 'CC-PAGO-' . $selectedClientId . '-' . time();
                if ($metodo === 5) {
                    mayorista_registrar_compromiso_financiero($conexion, array(
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
                } else {
                    mysqli_query(
                        $conexion,
                        "INSERT INTO ingresos(ingresos, fecha, id_venta, id_cliente, id_metodo, descripcion)
                         VALUES ($monto, NOW(), '$referencia', $selectedClientId, $metodo, '" . mysqli_real_escape_string($conexion, $descripcion) . "')"
                    );
                }

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

if ($selectedClientId > 0) {
    $clienteQuery = mysqli_query($conexion, "SELECT * FROM cliente WHERE idcliente = $selectedClientId LIMIT 1");
    $clienteActual = $clienteQuery ? mysqli_fetch_assoc($clienteQuery) : null;
    if ($clienteActual && $schemaReady) {
        $cuentaActual = mayorista_obtener_cuenta_corriente($conexion, $selectedClientId);
        $movimientos = mysqli_query(
            $conexion,
            "SELECT m.*, u.nombre AS usuario_nombre
             FROM movimientos_cc m
             LEFT JOIN usuario u ON m.id_usuario = u.idusuario
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
                                    <option value="1">Efectivo</option>
                                    <option value="2">Credito</option>
                                    <option value="3">Debito</option>
                                    <option value="4">Transferencia</option>
                                    <option value="5">Cheque</option>
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
                                <th>Monto</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($movimientos && mysqli_num_rows($movimientos) > 0) {
                                while ($mov = mysqli_fetch_assoc($movimientos)) { ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha'])); ?></td>
                                        <td><?php echo ucfirst($mov['tipo']); ?></td>
                                        <td><?php echo htmlspecialchars($mov['descripcion']); ?></td>
                                        <td><?php echo mayorista_formatear_moneda($mov['monto']); ?></td>
                                        <td><?php echo htmlspecialchars($mov['usuario_nombre'] ?: '-'); ?></td>
                                    </tr>
                            <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Todavia no hay movimientos para este cliente.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

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
});
</script>

<?php include_once "includes/footer.php"; ?>
