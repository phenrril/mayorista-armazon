<?php 
session_start();
include "../conexion.php";

// Validar sesión
if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = intval($_SESSION['idUser']);
$permiso = "reporte_costo";
$permiso_escaped = mysqli_real_escape_string($conexion, $permiso);

$sql = mysqli_query($conexion, "SELECT p.*, d.* FROM permisos p INNER JOIN detalle_permisos d ON p.id = d.id_permiso WHERE d.id_usuario = $id_user AND p.nombre = '$permiso_escaped'");
$existe = mysqli_fetch_all($sql);
if (empty($existe) && $id_user != 1) {
    header("Location: permisos.php");
    exit();
}

include_once "includes/header.php";

// Verificar si existe la columna costo
$check_column = mysqli_query($conexion, "SHOW COLUMNS FROM producto LIKE 'costo'");
$column_exists = mysqli_num_rows($check_column) > 0;

// Consultar productos con costo = 0
$query_sin_costo = mysqli_query($conexion, "SELECT * FROM producto WHERE estado = 1 ORDER BY codproducto DESC");
$total_sin_costo = 0;
$productos_sin_costo = array();
while ($row = mysqli_fetch_assoc($query_sin_costo)) {
    if (!$column_exists || !isset($row['costo']) || $row['costo'] == 0) {
        $productos_sin_costo[] = $row;
        $total_sin_costo += floatval($row['precio']) * intval($row['existencia']);
    }
}

// Consultar productos con costo = 1
$productos_con_costo = array();
$total_con_costo = 0;
if ($column_exists) {
    $query_con_costo = mysqli_query($conexion, "SELECT * FROM producto WHERE estado = 1 AND costo = 1 ORDER BY codproducto DESC");
    while ($row = mysqli_fetch_assoc($query_con_costo)) {
        $productos_con_costo[] = $row;
        $total_con_costo += floatval($row['precio']) * intval($row['existencia']);
    }
}
?>

<div class="reporte-container">
    <div class="page-header-modern">
        <h2><i class="fas fa-tag mr-2"></i> Reporte de Costo de Productos</h2>
        <p class="mb-0 mt-2">Distribución de productos por tipo de costo</p>
    </div>

    <!-- Resumen de Totales -->
    <div class="total-summary">
        <h3><i class="fas fa-chart-pie mr-2"></i> Resumen de Costos</h3>
        <div class="row">
            <div class="col-md-6">
                <div class="summary-item">
                    <strong><i class="fas fa-circle summary-dot-light"></i> productos:</strong><br>
                    <span class="summary-value"><?php echo count($productos_sin_costo); ?> productos</span><br>
                    <small>Total de valor: $<?php echo number_format($total_sin_costo, 2); ?></small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="summary-item">
                    <strong><i class="fas fa-circle summary-dot-dark"></i> productos:</strong><br>
                    <span class="summary-value"><?php echo count($productos_con_costo); ?> productos</span><br>
                    <small>Total de valor: $<?php echo number_format($total_con_costo, 2); ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Columna Izquierda: Productos SIN Costo (costo = 0) -->
        <div class="col-lg-6">
            <div class="card card-modern">
                <div class="section-header">
                    <i class="fas fa-circle summary-dot-light"></i> productos
                    <span class="badge badge-light float-right"><?php echo count($productos_sin_costo); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Marca</th>
                                <th>Precio</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (count($productos_sin_costo) > 0) {
                                foreach ($productos_sin_costo as $data) {
                                    // Stock bajo
                                    if ($data['existencia'] <= 10 && $data['existencia'] > 0) {
                                        $stock_class = 'text-warning';
                                        $stock_icon = '<i class="fas fa-exclamation-triangle mr-1"></i>';
                                    } elseif ($data['existencia'] <= 0) {
                                        $stock_class = 'text-danger';
                                        $stock_icon = '<i class="fas fa-times-circle mr-1"></i>';
                                    } else {
                                        $stock_class = 'text-success';
                                        $stock_icon = '<i class="fas fa-check-circle mr-1"></i>';
                                    }
                            ?>
                                <tr>
                                    <td><?php echo $data['codproducto']; ?></td>
                                    <td><?php echo $data['codigo']; ?></td>
                                    <td><?php echo $data['descripcion']; ?></td>
                                    <td><?php echo $data['marca']; ?></td>
                                    <td>$<?php echo number_format($data['precio'], 2); ?></td>
                                    <td><span class="<?php echo $stock_class; ?>"><?php echo $stock_icon; ?><?php echo $data['existencia']; ?></span></td>
                                </tr>
                            <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-box-open fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted">No hay productos</p>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Productos CON Costo (costo = 1) -->
        <div class="col-lg-6">
            <div class="card card-modern">
                <div class="section-header-danger">
                    <i class="fas fa-circle summary-dot-dark"></i> productos
                    <span class="badge badge-light float-right"><?php echo count($productos_con_costo); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Marca</th>
                                <th>Precio</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($column_exists && count($productos_con_costo) > 0) {
                                foreach ($productos_con_costo as $data) {
                                    // Stock bajo
                                    if ($data['existencia'] <= 10 && $data['existencia'] > 0) {
                                        $stock_class = 'text-warning';
                                        $stock_icon = '<i class="fas fa-exclamation-triangle mr-1"></i>';
                                    } elseif ($data['existencia'] <= 0) {
                                        $stock_class = 'text-danger';
                                        $stock_icon = '<i class="fas fa-times-circle mr-1"></i>';
                                    } else {
                                        $stock_class = 'text-success';
                                        $stock_icon = '<i class="fas fa-check-circle mr-1"></i>';
                                    }
                            ?>
                                <tr>
                                    <td><?php echo $data['codproducto']; ?></td>
                                    <td><?php echo $data['codigo']; ?></td>
                                    <td><?php echo $data['descripcion']; ?></td>
                                    <td><?php echo $data['marca']; ?></td>
                                    <td>$<?php echo number_format($data['precio'], 2); ?></td>
                                    <td><span class="<?php echo $stock_class; ?>"><?php echo $stock_icon; ?><?php echo $data['existencia']; ?></span></td>
                                </tr>
                            <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-tag fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted">No hay productos marcados como costo</p>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once "includes/footer.php"; ?>

