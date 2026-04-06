<?php
session_start();
include "../conexion.php";
require_once "includes/mayorista_helpers.php";

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('estadisticas'));

$hasTipoVenta = mayorista_column_exists($conexion, 'ventas', 'tipo_venta');
$hasMontoCc = mayorista_column_exists($conexion, 'ventas', 'monto_cc');

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

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

$whereVentas = "DATE(fecha) BETWEEN '$desde' AND '$hasta'";
$ventasTotales = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n, IFNULL(SUM(total),0) AS t FROM ventas WHERE $whereVentas"));
$ventasMayoristas = mysqli_fetch_assoc(mysqli_query(
    $conexion,
    $hasTipoVenta
        ? "SELECT COUNT(*) AS n, IFNULL(SUM(total),0) AS t FROM ventas WHERE $whereVentas AND tipo_venta = 'mayorista'"
        : "SELECT 0 AS n, 0 AS t"
));
$ventasMinoristas = mysqli_fetch_assoc(mysqli_query(
    $conexion,
    $hasTipoVenta
        ? "SELECT COUNT(*) AS n, IFNULL(SUM(total),0) AS t FROM ventas WHERE $whereVentas AND tipo_venta = 'minorista'"
        : "SELECT COUNT(*) AS n, IFNULL(SUM(total),0) AS t FROM ventas WHERE $whereVentas"
));
$ccPendiente = mysqli_fetch_assoc(mysqli_query(
    $conexion,
    $hasMontoCc
        ? "SELECT IFNULL(SUM(monto_cc),0) AS total FROM ventas WHERE $whereVentas"
        : "SELECT IFNULL(SUM(resto),0) AS total FROM ventas WHERE $whereVentas"
));

$serieLabels = array();
$serieMayorista = array();
$serieMinorista = array();
$serieQuery = mysqli_query(
    $conexion,
    $hasTipoVenta
        ? "SELECT DATE(fecha) AS fecha,
                  IFNULL(SUM(CASE WHEN tipo_venta = 'mayorista' THEN total ELSE 0 END),0) AS mayorista,
                  IFNULL(SUM(CASE WHEN tipo_venta = 'minorista' THEN total ELSE 0 END),0) AS minorista
           FROM ventas
           WHERE $whereVentas
           GROUP BY DATE(fecha)
           ORDER BY DATE(fecha) ASC"
        : "SELECT DATE(fecha) AS fecha,
                  0 AS mayorista,
                  IFNULL(SUM(total),0) AS minorista
           FROM ventas
           WHERE $whereVentas
           GROUP BY DATE(fecha)
           ORDER BY DATE(fecha) ASC"
);

while ($row = mysqli_fetch_assoc($serieQuery)) {
    $serieLabels[] = date('d/m', strtotime($row['fecha']));
    $serieMayorista[] = (float) $row['mayorista'];
    $serieMinorista[] = (float) $row['minorista'];
}

$productosCantidad = mysqli_query(
    $conexion,
    "SELECT p.descripcion, SUM(d.cantidad) AS cantidad
     FROM detalle_venta d
     INNER JOIN ventas v ON d.id_venta = v.id
     INNER JOIN producto p ON d.id_producto = p.codproducto
     WHERE $whereVentas
     GROUP BY d.id_producto, p.descripcion
     ORDER BY cantidad DESC
     LIMIT 8"
);

$productosMonto = mysqli_query(
    $conexion,
    "SELECT p.descripcion, SUM(d.cantidad * d.precio) AS monto
     FROM detalle_venta d
     INNER JOIN ventas v ON d.id_venta = v.id
     INNER JOIN producto p ON d.id_producto = p.codproducto
     WHERE $whereVentas
     GROUP BY d.id_producto, p.descripcion
     ORDER BY monto DESC
     LIMIT 8"
);

$clientesTop = mysqli_query(
    $conexion,
    "SELECT c.nombre, COUNT(v.id) AS operaciones, SUM(v.total) AS volumen
     FROM ventas v
     INNER JOIN cliente c ON v.id_cliente = c.idcliente
     WHERE $whereVentas
     GROUP BY v.id_cliente, c.nombre
     ORDER BY volumen DESC
     LIMIT 8"
);

include_once "includes/header.php";
?>
<div class="estadisticas-container">
    <div class="hero">
        <h2><i class="fas fa-chart-line mr-2"></i> Dashboard comercial</h2>
        <p class="mb-0">Separación entre canal mayorista y minorista, productos top y clientes con mayor volumen.</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form class="form-row">
                <div class="form-group col-md-4">
                    <label>Desde</label>
                    <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($desde); ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Hasta</label>
                    <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($hasta); ?>">
                </div>
                <div class="form-group col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary btn-block" type="submit">Aplicar filtros</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card kpi-card"><div class="card-body"><span>Ventas totales</span><strong><?php echo $ventasTotales['n']; ?></strong><?php echo mayorista_formatear_moneda($ventasTotales['t']); ?></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card kpi-card"><div class="card-body"><span>Mayorista</span><strong><?php echo $ventasMayoristas['n']; ?></strong><?php echo mayorista_formatear_moneda($ventasMayoristas['t']); ?></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card kpi-card"><div class="card-body"><span>Minorista</span><strong><?php echo $ventasMinoristas['n']; ?></strong><?php echo mayorista_formatear_moneda($ventasMinoristas['t']); ?></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card kpi-card"><div class="card-body"><span>Cuenta corriente pendiente</span><strong><?php echo mayorista_formatear_moneda($ccPendiente['total']); ?></strong>Saldo acumulado</div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card kpi-card">
                <div class="card-header bg-dark text-white">Ventas por canal del periodo</div>
                <div class="card-body">
                    <canvas id="ventasCanalChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card kpi-card">
                <div class="card-header bg-primary text-white">Distribucion del periodo</div>
                <div class="card-body">
                    <canvas id="ventasPieChart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card kpi-card">
                <div class="card-header bg-success text-white">Productos mas vendidos</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Producto</th><th>Cantidad</th></tr></thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($productosCantidad)) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                                        <td><?php echo (float) $row['cantidad']; ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card kpi-card">
                <div class="card-header bg-info text-white">Productos por monto</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Producto</th><th>Monto</th></tr></thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($productosMonto)) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                                        <td><?php echo mayorista_formatear_moneda($row['monto']); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card kpi-card">
                <div class="card-header bg-warning text-white">Clientes con mayor volumen</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Cliente</th><th>Oper.</th><th>Volumen</th></tr></thead>
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
    </div>
</div>

<script>
const serieLabels = <?php echo json_encode($serieLabels); ?>;
const serieMayorista = <?php echo json_encode($serieMayorista); ?>;
const serieMinorista = <?php echo json_encode($serieMinorista); ?>;
const totalMayorista = <?php echo json_encode((float) $ventasMayoristas['t']); ?>;
const totalMinorista = <?php echo json_encode((float) $ventasMinoristas['t']); ?>;

new Chart(document.getElementById('ventasCanalChart'), {
    type: 'bar',
    data: {
        labels: serieLabels,
        datasets: [
            {
                label: 'Mayorista',
                data: serieMayorista,
                backgroundColor: '#2563eb'
            },
            {
                label: 'Minorista',
                data: serieMinorista,
                backgroundColor: '#10b981'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            yAxes: [{
                ticks: {
                    beginAtZero: true
                }
            }]
        }
    }
});

new Chart(document.getElementById('ventasPieChart'), {
    type: 'pie',
    data: {
        labels: ['Mayorista', 'Minorista'],
        datasets: [{
            data: [totalMayorista, totalMinorista],
            backgroundColor: ['#2563eb', '#10b981']
        }]
    },
    options: {
        responsive: true
    }
});
</script>

<?php include_once "includes/footer.php"; ?>
