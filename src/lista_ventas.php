<?php
session_start();
include "../conexion.php";
require_once "includes/mayorista_helpers.php";

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('ventas'));

$campos = array('v.id', 'v.id_cliente', 'v.total', 'v.abona', 'v.resto', 'v.fecha', 'c.nombre');
if (mayorista_column_exists($conexion, 'ventas', 'tipo_venta')) {
    $campos[] = 'v.tipo_venta';
}
if (mayorista_column_exists($conexion, 'ventas', 'monto_cc')) {
    $campos[] = 'v.monto_cc';
}
if (mayorista_column_exists($conexion, 'ventas', 'saldo_cc_cliente')) {
    $campos[] = 'v.saldo_cc_cliente';
}
if (mayorista_column_exists($conexion, 'ventas', 'precio_modificado')) {
    $campos[] = 'v.precio_modificado';
}

$query = mysqli_query(
    $conexion,
    "SELECT " . implode(', ', $campos) . "
     FROM ventas v
     LEFT JOIN cliente c ON v.id_cliente = c.idcliente
     ORDER BY v.id DESC"
);

$totalVentas = 0;
$totalFacturado = 0;
$totalCc = 0;
if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $totalVentas++;
        $totalFacturado += (float) $row['total'];
        $totalCc += isset($row['monto_cc']) ? (float) $row['monto_cc'] : (float) $row['resto'];
    }
    mysqli_data_seek($query, 0);
}

include_once "includes/header.php";
?>
<div class="ventas-container">
    <div class="page-header">
        <h2><i class="fas fa-receipt mr-2"></i> Ventas</h2>
        <p class="mb-0">Seguimiento de operaciones minoristas y mayoristas con impacto en cuenta corriente.</p>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="kpi">
                <span>Ventas registradas</span>
                <strong><?php echo $totalVentas; ?></strong>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="kpi">
                <span>Total facturado</span>
                <strong><?php echo mayorista_formatear_moneda($totalFacturado); ?></strong>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="kpi">
                <span>En cuenta corriente</span>
                <strong><?php echo mayorista_formatear_moneda($totalCc); ?></strong>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover custom-dt-init" id="tbl">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Total</th>
                            <th>Abonado</th>
                            <th>A CC</th>
                            <th>Saldo CC cliente</th>
                            <th>Precio mod.</th>
                            <th>Fecha</th>
                            <th>Editar</th>
                            <th>Factura</th>
                            <th>Recibo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($query) {
                            while ($row = mysqli_fetch_assoc($query)) {
                                $tipo = ucfirst($row['tipo_venta'] ?? 'minorista');
                                $montoCc = isset($row['monto_cc']) ? (float) $row['monto_cc'] : (float) $row['resto'];
                                $saldoCc = isset($row['saldo_cc_cliente']) ? (float) $row['saldo_cc_cliente'] : 0;
                                $precioModificado = !empty($row['precio_modificado']) ? 'Si' : 'No';

                                $facturaQuery = mysqli_query(
                                    $conexion,
                                    "SELECT id
                                     FROM facturas_electronicas
                                     WHERE id_venta = " . (int) $row['id'] . "
                                     AND estado = 'aprobado'
                                     LIMIT 1"
                                );
                                $tieneFactura = $facturaQuery && mysqli_num_rows($facturaQuery) > 0;
                        ?>
                            <tr>
                                <td data-order="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['nombre'] ?: 'Consumidor final'); ?></td>
                                <td><?php echo $tipo; ?></td>
                                <td><?php echo mayorista_formatear_moneda($row['total']); ?></td>
                                <td><?php echo mayorista_formatear_moneda($row['abona']); ?></td>
                                <td><?php echo mayorista_formatear_moneda($montoCc); ?></td>
                                <td><?php echo mayorista_formatear_moneda($saldoCc); ?></td>
                                <td><?php echo $precioModificado; ?></td>
                                <td data-order="<?php echo (int) strtotime($row['fecha']); ?>"><?php echo date('d/m/Y H:i', strtotime($row['fecha'])); ?></td>
                                <td>
                                    <?php if (!$tieneFactura) { ?>
                                        <a class="btn btn-sm btn-outline-primary" href="editar_venta.php?id=<?php echo (int) $row['id']; ?>">
                                            Editar
                                        </a>
                                    <?php } else { ?>
                                        <span class="text-muted small">Bloqueado</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($tieneFactura) { ?>
                                        <a class="btn btn-sm btn-outline-success" href="pdf/generar_factura_electronica.php?v=<?php echo (int) $row['id']; ?>" target="_blank">
                                            PDF
                                        </a>
                                    <?php } else { ?>
                                        <button class="btn btn-sm btn-primary" onclick="generarFacturaElectronica(<?php echo (int) $row['id']; ?>)">
                                            Facturar
                                        </button>
                                    <?php } ?>
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-danger" href="pdf/generar.php?cl=<?php echo (int) $row['id_cliente']; ?>&v=<?php echo (int) $row['id']; ?>" target="_blank">
                                        Ver PDF
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

<script src="../assets/js/facturacion.js"></script>

<script>
window.addEventListener('load', function () {
    var $ = window.jQuery;
    if (!$ || !$.fn.DataTable) {
        return;
    }
    var $table = $('#tbl');
    if ($table.length && !$.fn.DataTable.isDataTable($table)) {
        $table.DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
            columnDefs: [
                { targets: [9, 10, 11], orderable: false, searchable: false }
            ],
            language: {
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_ ventas',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ ventas',
                infoEmpty: 'Sin ventas para mostrar',
                infoFiltered: '(filtrado de _MAX_ ventas)',
                zeroRecords: 'No hay coincidencias',
                emptyTable: 'No hay ventas registradas',
                paginate: {
                    first: 'Primera',
                    last: 'Última',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        });
    }
});
</script>

<?php include_once "includes/footer.php"; ?>
