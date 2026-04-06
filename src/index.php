<?php
session_start();
include "../conexion.php";
require_once "includes/mayorista_helpers.php";

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('nueva_venta', 'ventas'));

$hasFacturas = mayorista_table_exists($conexion, 'facturas_electronicas');
$hasCcTables = mayorista_table_exists($conexion, 'cuenta_corriente') && mayorista_table_exists($conexion, 'movimientos_cc');

$ventasHoy = mysqli_fetch_assoc(mysqli_query(
    $conexion,
    "SELECT COUNT(*) AS operaciones, IFNULL(SUM(total),0) AS total
     FROM ventas
     WHERE DATE(fecha) = CURDATE()"
));

$ccPendiente = $hasCcTables
    ? mysqli_fetch_assoc(mysqli_query(
        $conexion,
        "SELECT COUNT(*) AS clientes, IFNULL(SUM(saldo_actual),0) AS deuda
         FROM cuenta_corriente
         WHERE saldo_actual > 0"
    ))
    : array('clientes' => 0, 'deuda' => 0);

$clientesExcedidos = $hasCcTables
    ? mysqli_fetch_assoc(mysqli_query(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM cuenta_corriente
         WHERE limite_credito > 0
         AND saldo_actual > limite_credito"
    ))
    : array('total' => 0);

$sinStock = mysqli_fetch_assoc(mysqli_query(
    $conexion,
    "SELECT COUNT(*) AS total
     FROM producto
     WHERE existencia <= 0"
));

$stockBajo = mysqli_fetch_assoc(mysqli_query(
    $conexion,
    "SELECT COUNT(*) AS total
     FROM producto
     WHERE existencia > 0
     AND existencia <= 5"
));

$facturasPendientes = $hasFacturas
    ? mysqli_fetch_assoc(mysqli_query(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM ventas v
         LEFT JOIN facturas_electronicas f
           ON f.id_venta = v.id
          AND f.estado = 'aprobado'
         WHERE f.id IS NULL"
    ))
    : array('total' => 0);

$accesosRapidos = array(
    array('label' => 'Nueva venta', 'icon' => 'fa-store', 'href' => 'ventas.php', 'class' => 'accent-indigo', 'perm' => array('nueva_venta', 'ventas')),
    array('label' => 'Ventas', 'icon' => 'fa-receipt', 'href' => 'lista_ventas.php', 'class' => 'accent-blue', 'perm' => array('ventas')),
    array('label' => 'Clientes', 'icon' => 'fa-users', 'href' => 'clientes.php', 'class' => 'accent-cyan', 'perm' => array('clientes')),
    array('label' => 'Cuenta corriente', 'icon' => 'fa-file-invoice-dollar', 'href' => 'cuenta_corriente.php', 'class' => 'accent-emerald', 'perm' => array('cuenta_corriente', 'clientes')),
    array('label' => 'Productos', 'icon' => 'fa-glasses', 'href' => 'productos.php', 'class' => 'accent-teal', 'perm' => array('productos')),
    array('label' => 'Estadísticas', 'icon' => 'fa-chart-line', 'href' => 'estadisticas.php', 'class' => 'accent-amber', 'perm' => array('estadisticas')),
    array('label' => 'Reportes', 'icon' => 'fa-chart-bar', 'href' => 'reporte.php', 'class' => 'accent-orange', 'perm' => array('reportes', 'reporte')),
    array('label' => 'API', 'icon' => 'fa-key', 'href' => 'api_config.php', 'class' => 'accent-violet', 'perm' => array('api_config')),
);

include_once "includes/header.php";
?>
<div class="dashboard-container">
    <div class="page-header">
        <h2><i class="fas fa-bolt mr-2"></i> Panel Operativo</h2>
        <p>Estado general del negocio y accesos clave para trabajar más rápido.</p>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card kpi-card">
                <div class="card-body">
                    <span>Ventas de hoy</span>
                    <strong><?php echo (int) ($ventasHoy['operaciones'] ?? 0); ?></strong>
                    <?php echo mayorista_formatear_moneda($ventasHoy['total'] ?? 0); ?>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card kpi-card">
                <div class="card-body">
                    <span>Clientes con deuda</span>
                    <strong><?php echo (int) ($ccPendiente['clientes'] ?? 0); ?></strong>
                    <?php echo mayorista_formatear_moneda($ccPendiente['deuda'] ?? 0); ?>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card kpi-card">
                <div class="card-body">
                    <span>Facturas pendientes</span>
                    <strong><?php echo (int) ($facturasPendientes['total'] ?? 0); ?></strong>
                    <?php echo $hasFacturas ? 'Ventas sin factura aprobada' : 'Módulo de facturación no detectado'; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card panel-card">
                <div class="card-header alertas-header">
                    <i class="fas fa-bell mr-2"></i> Alertas operativas
                </div>
                <div class="card-body">
                    <div class="alert-list">
                        <div class="alert-item">
                            <div>
                                <strong>Clientes con deuda en cuenta corriente</strong>
                                <span>Seguimiento de cobranzas pendientes.</span>
                            </div>
                            <div class="alert-badge is-info"><?php echo (int) ($ccPendiente['clientes'] ?? 0); ?></div>
                        </div>
                        <div class="alert-item">
                            <div>
                                <strong>Clientes excedidos del límite</strong>
                                <span>Revisá cuentas que superan su crédito configurado.</span>
                            </div>
                            <div class="alert-badge <?php echo !empty($clientesExcedidos['total']) ? 'is-danger' : ''; ?>">
                                <?php echo (int) ($clientesExcedidos['total'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="alert-item">
                            <div>
                                <strong>Productos sin stock</strong>
                                <span>Items agotados para reponer o desactivar.</span>
                            </div>
                            <div class="alert-badge <?php echo !empty($sinStock['total']) ? 'is-danger' : ''; ?>">
                                <?php echo (int) ($sinStock['total'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="alert-item">
                            <div>
                                <strong>Productos con stock bajo</strong>
                                <span>Menos de 6 unidades disponibles.</span>
                            </div>
                            <div class="alert-badge <?php echo !empty($stockBajo['total']) ? 'is-warning' : ''; ?>">
                                <?php echo (int) ($stockBajo['total'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="alert-item">
                            <div>
                                <strong>Ventas pendientes de facturar</strong>
                                <span>Control de comprobantes fiscales.</span>
                            </div>
                            <div class="alert-badge <?php echo !empty($facturasPendientes['total']) ? 'is-warning' : ''; ?>">
                                <?php echo (int) ($facturasPendientes['total'] ?? 0); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card panel-card">
                <div class="card-header accesos-header">
                    <i class="fas fa-compass mr-2"></i> Accesos rápidos
                </div>
                <div class="card-body">
                    <div class="quick-links-grid">
                        <?php foreach ($accesosRapidos as $acceso) {
                            if (!mayorista_nav_link_visible($conexion, $id_user, $acceso['perm'])) {
                                continue;
                            }
                            ?>
                            <a class="quick-link <?php echo $acceso['class']; ?>" href="<?php echo $acceso['href']; ?>">
                                <span class="quick-link-icon"><i class="fas <?php echo $acceso['icon']; ?>"></i></span>
                                <span>
                                    <strong><?php echo htmlspecialchars($acceso['label']); ?></strong>
                                    <small>Abrir módulo</small>
                                </span>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once "includes/footer.php"; ?>