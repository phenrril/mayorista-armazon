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
    array('label' => 'Nueva venta', 'icon' => 'fa-store', 'href' => 'ventas.php', 'class' => 'accent-indigo'),
    array('label' => 'Ventas', 'icon' => 'fa-receipt', 'href' => 'lista_ventas.php', 'class' => 'accent-blue'),
    array('label' => 'Clientes', 'icon' => 'fa-users', 'href' => 'clientes.php', 'class' => 'accent-cyan'),
    array('label' => 'Cuenta corriente', 'icon' => 'fa-file-invoice-dollar', 'href' => 'cuenta_corriente.php', 'class' => 'accent-emerald'),
    array('label' => 'Productos', 'icon' => 'fa-glasses', 'href' => 'productos.php', 'class' => 'accent-teal'),
    array('label' => 'Estadísticas', 'icon' => 'fa-chart-line', 'href' => 'estadisticas.php', 'class' => 'accent-amber'),
    array('label' => 'Reportes', 'icon' => 'fa-chart-bar', 'href' => 'reporte.php', 'class' => 'accent-orange'),
    array('label' => 'API', 'icon' => 'fa-key', 'href' => 'api_config.php', 'class' => 'accent-violet'),
);

include_once "includes/header.php";
?>

<style>
.dashboard-container {
    max-width: 1500px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #fff;
    border-radius: 18px;
    padding: 28px 32px;
    margin-bottom: 24px;
    box-shadow: 0 15px 35px rgba(99, 102, 241, 0.22);
}

.page-header h2 {
    margin: 0;
    font-weight: 700;
}

.page-header p {
    margin: 8px 0 0;
    opacity: 0.9;
}

.kpi-card,
.panel-card,
.quick-link {
    border: none;
    border-radius: 18px;
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
    background: #fff;
}

.kpi-card {
    height: 100%;
}

.kpi-card .card-body {
    padding: 22px;
}

.kpi-card strong {
    display: block;
    font-size: 1.9rem;
    color: #0f172a;
}

.panel-card .card-header {
    border: 0;
    border-radius: 18px 18px 0 0 !important;
    padding: 18px 24px;
    font-weight: 600;
    color: #fff;
}

.alertas-header {
    background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
}

.accesos-header {
    background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
}

.alert-list {
    display: grid;
    gap: 14px;
}

.alert-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 16px 18px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #f8fafc;
}

.alert-item strong {
    display: block;
    color: #0f172a;
}

.alert-item span {
    color: #64748b;
    font-size: 0.95rem;
}

.alert-badge {
    min-width: 96px;
    text-align: center;
    padding: 10px 14px;
    border-radius: 12px;
    font-weight: 700;
    background: #e2e8f0;
    color: #0f172a;
}

.alert-badge.is-danger {
    background: #fee2e2;
    color: #b91c1c;
}

.alert-badge.is-warning {
    background: #fef3c7;
    color: #b45309;
}

.alert-badge.is-info {
    background: #dbeafe;
    color: #1d4ed8;
}

.quick-links-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}

.quick-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px;
    color: #0f172a;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid #e5e7eb;
}

.quick-link:hover {
    text-decoration: none;
    color: #0f172a;
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
}

.quick-link-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.1rem;
}

.accent-indigo .quick-link-icon { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); }
.accent-blue .quick-link-icon { background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); }
.accent-cyan .quick-link-icon { background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); }
.accent-emerald .quick-link-icon { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
.accent-teal .quick-link-icon { background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); }
.accent-amber .quick-link-icon { background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); }
.accent-orange .quick-link-icon { background: linear-gradient(135deg, #ea580c 0%, #f97316 100%); }
.accent-violet .quick-link-icon { background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%); }

.quick-link small {
    display: block;
    color: #64748b;
}
</style>

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
                        <?php foreach ($accesosRapidos as $acceso) { ?>
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