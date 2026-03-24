<?php
session_start();
include "../conexion.php";
require_once "includes/mayorista_helpers.php";

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('cuenta_corriente', 'clientes'));
$esAdmin = mayorista_es_admin($id_user);

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

        if ($monto <= 0) {
            $alert = '<div class="alert alert-warning">El monto del pago debe ser mayor a cero.</div>';
        } else {
            mysqli_begin_transaction($conexion);
            try {
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
                mysqli_query(
                    $conexion,
                    "INSERT INTO ingresos(ingresos, fecha, id_venta, id_cliente, id_metodo)
                     VALUES ($monto, NOW(), '$referencia', $selectedClientId, $metodo)"
                );

                mysqli_commit($conexion);
                $alert = '<div class="alert alert-success">Pago registrado. Saldo actual: ' . mayorista_formatear_moneda($saldo) . '.</div>';
            } catch (Exception $e) {
                mysqli_rollback($conexion);
                $alert = '<div class="alert alert-danger">No se pudo registrar el pago.</div>';
            }
        }
    }
}

$clientes = mysqli_query(
    $conexion,
    $schemaReady
        ? "SELECT c.idcliente, c.nombre, c.telefono, c.direccion,
                  cc.id AS cc_id, cc.limite_credito, cc.saldo_actual,
                  MAX(m.fecha) AS ultima_actividad
           FROM cliente c
           LEFT JOIN cuenta_corriente cc ON c.idcliente = cc.id_cliente
           LEFT JOIN movimientos_cc m ON cc.id = m.id_cuenta_corriente
           WHERE c.estado = 1
           GROUP BY c.idcliente, c.nombre, c.telefono, c.direccion, cc.id, cc.limite_credito, cc.saldo_actual
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
                            placeholder="Nombre, telefono o direccion"
                        >
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover custom-dt-init" id="tbl">
                            <thead class="thead-dark">
                                <tr>
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
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
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
                                <select name="metodo_pago" class="form-control">
                                    <option value="1">Efectivo</option>
                                    <option value="2">Credito</option>
                                    <option value="3">Debito</option>
                                    <option value="4">Transferencia</option>
                                </select>
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
$(function () {
    const $input = $('#buscar_cliente_cc');
    const $table = $('#tbl');

    $input.on('input', function () {
        const value = $(this).val();
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
});
</script>

<?php include_once "includes/footer.php"; ?>
