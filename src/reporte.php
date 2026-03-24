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
$hasMontoCc = mayorista_column_exists($conexion, 'ventas', 'monto_cc');
$hasCcTables = mayorista_table_exists($conexion, 'cuenta_corriente') && mayorista_table_exists($conexion, 'movimientos_cc');
$hasEgresos = mayorista_table_exists($conexion, 'egresos');

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$tipo = $_GET['tipo'] ?? 'todas';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $desde = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $hasta = date('Y-m-d');
}

if ($desde > $hasta) {
    $tmp = $desde;
    $desde = $hasta;
    $hasta = $tmp;
}

$whereVentas = "DATE(v.fecha) BETWEEN '$desde' AND '$hasta'";
if ($hasTipoVenta && in_array($tipo, array('mayorista', 'minorista'), true)) {
    $whereVentas .= " AND v.tipo_venta = '" . mysqli_real_escape_string($conexion, $tipo) . "'";
}

$whereVentasPlain = str_replace('v.', '', $whereVentas);

$ventasResumen = mysqli_fetch_assoc(mysqli_query(
    $conexion,
    "SELECT COUNT(*) AS operaciones, IFNULL(SUM(v.total),0) AS total, IFNULL(SUM(v.abona),0) AS cobrado
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

include_once "includes/header.php";
?>
<style>
.reportes-container {
    max-width: 1500px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    color: #fff;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 24px;
}

.kpi-card {
    border: none;
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
    margin-bottom: 24px;
    height: 100%;
}

.kpi-card strong {
    display: block;
    font-size: 1.9rem;
}
</style>

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

    <div class="row">
        <div class="col-md-3"><div class="card kpi-card"><div class="card-body"><span>Ventas</span><strong><?php echo $ventasResumen['operaciones']; ?></strong><?php echo mayorista_formatear_moneda($ventasResumen['total']); ?></div></div></div>
        <div class="col-md-3"><div class="card kpi-card"><div class="card-body"><span>Cobrado</span><strong><?php echo mayorista_formatear_moneda($ventasResumen['cobrado']); ?></strong>Ingresos registrados</div></div></div>
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
</div>

<?php include_once "includes/footer.php"; ?>
