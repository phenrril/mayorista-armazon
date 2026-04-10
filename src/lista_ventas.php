<?php
session_start();
include "../conexion.php";
require_once "includes/mayorista_helpers.php";

if (!($conexion instanceof mysqli)) {
    exit('No se pudo conectar a la base de datos.');
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('ventas'));

$hasClienteOptica = mayorista_column_exists($conexion, 'cliente', 'optica');
$campos = array('v.id', 'v.id_cliente', 'v.total', 'v.abona', 'v.resto', 'v.fecha', 'c.nombre');
if ($hasClienteOptica) {
    $campos[] = 'c.optica';
} else {
    $campos[] = "'' AS optica";
}
if (mayorista_column_exists($conexion, 'ventas', 'tipo_venta')) {
    $campos[] = 'v.tipo_venta';
}
if (mayorista_column_exists($conexion, 'ventas', 'monto_cc')) {
    $campos[] = 'v.monto_cc';
}
if (mayorista_column_exists($conexion, 'ventas', 'saldo_cc_cliente')) {
    $campos[] = 'v.saldo_cc_cliente';
}
if (mayorista_table_exists($conexion, 'cuenta_corriente')) {
    $campos[] = 'cc.saldo_actual AS saldo_cc_actual';
}
if (mayorista_column_exists($conexion, 'ventas', 'precio_modificado')) {
    $campos[] = 'v.precio_modificado';
}

$query = mysqli_query(
    $conexion,
    "SELECT " . implode(', ', $campos) . "
     FROM ventas v
     LEFT JOIN cliente c ON v.id_cliente = c.idcliente
     LEFT JOIN cuenta_corriente cc ON v.id_cliente = cc.id_cliente
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
<style>
.ventas-tabla-shell .table-responsive {
    overflow-x: visible;
    padding: 0.3rem 0.6rem 0.45rem;
    border-radius: 18px;
}

.ventas-tabla-shell #tbl {
    width: 100% !important;
    margin: 0 auto;
    font-size: 0.92rem;
}

.ventas-tabla-shell .dataTables_wrapper {
    width: 100%;
    margin: 0 auto;
    font-size: 0.92rem;
}

.ventas-tabla-shell #tbl thead th,
.ventas-tabla-shell #tbl tbody td {
    padding: 0.55rem 0.4rem;
}

.ventas-tabla-shell .dataTables_wrapper .dataTables_length,
.ventas-tabla-shell .dataTables_wrapper .dataTables_filter,
.ventas-tabla-shell .dataTables_wrapper .dataTables_info,
.ventas-tabla-shell .dataTables_wrapper .dataTables_paginate {
    font-size: 0.88rem;
}

.ventas-tabla-shell .dataTables_wrapper .dataTables_filter input,
.ventas-tabla-shell .dataTables_wrapper .dataTables_length select {
    font-size: 0.88rem;
    padding-top: 0.2rem;
    padding-bottom: 0.2rem;
}

.ventas-tabla-shell .venta-fecha {
    min-width: 118px;
    line-height: 1.1;
}

.ventas-tabla-shell .venta-fecha-dia {
    display: block;
    font-weight: 600;
}

.ventas-tabla-shell .venta-fecha-hora {
    display: inline-block;
    margin-top: 0.2rem;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    background: rgba(13, 110, 253, 0.12);
    color: #0b5ed7;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}
</style>
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
            <div class="ventas-tabla-shell">
                <div class="table-responsive">
                    <table class="table table-striped table-hover custom-dt-init" id="tbl">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Óptica</th>
                                <th>Tipo</th>
                                <th>Total</th>
                                <th>Abonado</th>
                                <th>A CC</th>
                                <th>Saldo CC cliente</th>
                                <th>Precio mod.</th>
                                <th>Fecha</th>
                                <th>Editar</th>
                                <th>Eliminar</th>
                                <th>Factura</th>
                                <th>Recibo</th>
                                <th>Nombre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($query) {
                                while ($row = mysqli_fetch_assoc($query)) {
                                    $tipo = ucfirst($row['tipo_venta'] ?? 'minorista');
                                    $montoCc = isset($row['monto_cc']) ? (float) $row['monto_cc'] : (float) $row['resto'];
                                    $saldoCc = isset($row['saldo_cc_actual'])
                                        ? (float) $row['saldo_cc_actual']
                                        : (isset($row['saldo_cc_cliente']) ? (float) $row['saldo_cc_cliente'] : 0);
                                    $precioModificado = !empty($row['precio_modificado']) ? 'Si' : 'No';

                                    $tieneFactura = mayorista_venta_tiene_factura_aprobada($conexion, (int) $row['id']);
                                    $opticaVisible = trim((string) ($row['optica'] ?? '')) !== ''
                                        ? $row['optica']
                                        : ($row['nombre'] ?: 'Consumidor final');
                                    $timestampFecha = !empty($row['fecha']) ? strtotime($row['fecha']) : false;
                                    $fechaVentaTexto = $timestampFecha ? date('d/m/Y', $timestampFecha) : '-';
                                    $horaVentaTexto = $timestampFecha ? date('H:i', $timestampFecha) : '--:--';
                            ?>
                                <tr>
                                    <td data-order="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($opticaVisible); ?></td>
                                    <td><?php echo $tipo; ?></td>
                                    <td><?php echo mayorista_formatear_moneda($row['total']); ?></td>
                                    <td><?php echo mayorista_formatear_moneda($row['abona']); ?></td>
                                    <td><?php echo mayorista_formatear_moneda($montoCc); ?></td>
                                    <td><?php echo mayorista_formatear_moneda($saldoCc); ?></td>
                                    <td><?php echo $precioModificado; ?></td>
                                    <td data-order="<?php echo $timestampFecha ? (int) $timestampFecha : 0; ?>">
                                        <div class="venta-fecha">
                                            <span class="venta-fecha-dia"><?php echo $fechaVentaTexto; ?></span>
                                            <span class="venta-fecha-hora"><?php echo $horaVentaTexto; ?></span>
                                        </div>
                                    </td>
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
                                        <?php if (!$tieneFactura) { ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger js-anular-venta"
                                                data-id="<?php echo (int) $row['id']; ?>">
                                                Eliminar
                                            </button>
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
                                    <td><?php echo htmlspecialchars($row['nombre'] ?: 'Consumidor final'); ?></td>
                                </tr>
                            <?php }
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/facturacion.js"></script>

<script>
window.addEventListener('load', function () {
    var $ = window.jQuery;
    if (!$) {
        return;
    }
    var $table = $('#tbl');
    if ($.fn.DataTable && $table.length && !$.fn.DataTable.isDataTable($table)) {
        $table.DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
            columnDefs: [
                { targets: [9, 10, 11, 12], orderable: false, searchable: false },
                { targets: [13], visible: false }
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

    $(document).on('click', '.js-anular-venta', function () {
        var idVenta = parseInt($(this).data('id'), 10);
        if (!idVenta || !window.Swal) {
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Eliminar venta',
            text: 'Se restaurará el stock y se revertirán los movimientos asociados. Esta acción solo está disponible para ventas no facturadas.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: 'anular.php',
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                data: {
                    idanular: idVenta
                },
                success: function (response) {
                    var icon = response && response.icon ? response.icon : 'success';
                    var title = response && response.title ? response.title : 'Venta eliminada';
                    var timer = response && response.timer ? response.timer : 2000;
                    Swal.fire({
                        icon: icon,
                        title: title,
                        showConfirmButton: false,
                        timer: timer
                    }).then(function () {
                        if (response && response.status === 'success') {
                            window.location.reload();
                        }
                    });
                },
                error: function (xhr) {
                    var response = xhr.responseJSON || {};
                    Swal.fire({
                        icon: response.icon || 'error',
                        title: response.title || 'No se pudo eliminar la venta',
                        showConfirmButton: false,
                        timer: response.timer || 3000
                    });
                }
            });
        });
    });
});
</script>

<?php include_once "includes/footer.php"; ?>
