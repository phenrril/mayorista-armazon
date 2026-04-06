<?php
session_start();
include "../conexion.php";
require_once "includes/mayorista_helpers.php";

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('nueva_venta'));

$condicionesIva = array(
    'Consumidor Final',
    'IVA Responsable Inscripto',
    'Responsable Monotributo',
    'IVA Sujeto Exento',
    'IVA Responsable no Inscripto',
    'IVA no Responsable',
    'Sujeto no Categorizado',
    'Proveedor del Exterior',
    'Cliente del Exterior',
    'IVA Liberado - Ley N° 19.640',
);
$hasClienteCuit = mayorista_column_exists($conexion, 'cliente', 'cuit');
$hasClienteCondicionIva = mayorista_column_exists($conexion, 'cliente', 'condicion_iva');
$hasClienteTipoDocumento = mayorista_column_exists($conexion, 'cliente', 'tipo_documento');
$hasClienteOptica = mayorista_column_exists($conexion, 'cliente', 'optica');
$hasClienteLocalidad = mayorista_column_exists($conexion, 'cliente', 'localidad');
$hasClienteCodigoPostal = mayorista_column_exists($conexion, 'cliente', 'codigo_postal');
$hasClienteProvincia = mayorista_column_exists($conexion, 'cliente', 'provincia');
$hasModoDespacho = mayorista_column_exists($conexion, 'ventas', 'modo_despacho');
$modosDespacho = mayorista_modos_despacho();
$ventaToken = mayorista_generar_token_venta();
$schemaReady = mayorista_column_exists($conexion, 'producto', 'precio_mayorista')
    && mayorista_column_exists($conexion, 'ventas', 'tipo_venta')
    && mayorista_table_exists($conexion, 'cuenta_corriente')
    && mayorista_table_exists($conexion, 'movimientos_cc');

include_once "includes/header.php";
?>
<div class="ventas-container">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h2><i class="fas fa-store mr-2"></i> Nueva venta mayorista</h2>
            <p>Selecciona el tipo de venta, arma el pedido y controla el impacto en cuenta corriente.</p>
        </div>
        <div class="badge-chip mt-3 mt-md-0">
            <i class="fas fa-user-tie"></i>
            <?php echo htmlspecialchars($_SESSION['nombre']); ?>
        </div>
    </div>

    <?php if (!$schemaReady) { ?>
        <div class="alert alert-warning">
            Falta aplicar la migración `sql/2026_mayorista_armazones.sql`. La pantalla funciona en modo limitado hasta que el esquema quede actualizado.
        </div>
    <?php } ?>
    <?php if (!mayorista_schema_remito_productos_listo($conexion)) { ?>
        <div class="alert alert-warning">
            Ejecutá la migración nueva desde configuración para habilitar clientes completos, modo de despacho y el remito actualizado.
        </div>
    <?php } ?>

    <div class="card card-modern">
        <div class="card-header card-header-modern d-flex justify-content-between align-items-center flex-wrap">
            <span><i class="fas fa-user mr-2"></i> Cliente y condiciones</span>
            <button type="button" class="btn btn-sm btn-success-modern btn-modern mt-2 mt-md-0" data-toggle="modal" data-target="#nuevo_cliente_venta">
                <i class="fas fa-plus mr-1"></i> Nuevo cliente
            </button>
        </div>
        <div class="card-body card-body-modern">
            <div class="row">
                <div class="col-lg-4">
                    <input type="hidden" id="idcliente" value="">
                    <input type="hidden" id="venta_token" value="<?php echo htmlspecialchars($ventaToken); ?>">
                    <div class="form-group">
                        <label>Cliente</label>
                        <input type="text" id="nom_cliente" class="form-control form-control-modern" placeholder="Buscar por nombre, DNI o teléfono">
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="form-group">
                        <label>Tipo de venta</label>
                        <select id="tipo_venta" class="form-control custom-select-modern">
                            <option value="minorista">Minorista</option>
                            <option value="mayorista">Mayorista</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" id="tel_cliente" class="form-control form-control-modern" disabled>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" id="dir_cliente" class="form-control form-control-modern" disabled>
                    </div>
                </div>
            </div>

            <?php if ($hasModoDespacho) { ?>
            <div class="row">
                <div class="col-lg-4">
                    <div class="form-group mb-0">
                        <label>Modo de despacho</label>
                        <select id="modo_despacho" class="form-control custom-select-modern">
                            <?php foreach ($modosDespacho as $modoDespacho) { ?>
                                <option value="<?php echo htmlspecialchars($modoDespacho); ?>" <?php echo $modoDespacho === 'A convenir' ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($modoDespacho); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>
            <?php } ?>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="cc-metric">
                        <h6>Saldo actual CC</h6>
                        <div class="cc-value" id="cc_saldo_actual">$0,00</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="cc-metric">
                        <h6>Límite de crédito</h6>
                        <div class="cc-value" id="cc_limite">$0,00</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="cc-metric">
                        <h6>Saldo luego de la venta</h6>
                        <div class="cc-value" id="cc_saldo_resultante">$0,00</div>
                    </div>
                </div>
            </div>
            <small class="text-muted">Si el cliente no abona todo, el saldo pendiente se carga automáticamente a su cuenta corriente. Si la cuenta tiene limite configurado, la venta no se podra confirmar al superarlo.</small>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="product-search-box mb-4">
                <h5 class="mb-3"><i class="fas fa-search mr-2"></i> Buscar producto</h5>
                <input id="producto" class="form-control form-control-modern" type="text" placeholder="Código o descripción del producto">
                <small class="text-muted d-block mt-2">El precio sugerido cambia según el tipo de venta seleccionado.</small>
            </div>

            <div class="card card-modern">
                <div class="card-header card-header-modern">
                    <i class="fas fa-wallet mr-2"></i> Cobro
                </div>
                <div class="card-body card-body-modern">
                    <div class="form-group">
                        <label>Método de pago del importe abonado</label>
                        <div class="d-flex flex-wrap premium-gap">
                            <label class="badge-chip"><input type="radio" name="pago" value="1" checked> Efectivo</label>
                            <label class="badge-chip"><input type="radio" name="pago" value="2"> Crédito</label>
                            <label class="badge-chip"><input type="radio" name="pago" value="3"> Débito</label>
                            <label class="badge-chip"><input type="radio" name="pago" value="4"> Transferencia</label>
                            <label class="badge-chip"><input type="radio" name="pago" value="5"> Cheque</label>
                        </div>
                    </div>
                    <div id="cheque_fields" class="border rounded p-3 mb-3" style="display:none;">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Plazo del cheque</label>
                                <select id="cheque_plazo_dias" class="form-control">
                                    <option value="30">30 días</option>
                                    <option value="60">60 días</option>
                                    <option value="90">90 días</option>
                                    <option value="120">120 días</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Fecha base</label>
                                <input type="date" id="cheque_fecha_base" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Fecha esperada de depósito</label>
                                <input type="date" id="cheque_fecha_deposito" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                            </div>
                        </div>
                        <small class="text-muted">El importe abonado con cheque no se contará como ingreso hasta que se confirme su depósito.</small>
                    </div>
                    <div class="form-group">
                        <label>Abona ahora</label>
                        <input type="number" class="form-control form-control-modern" id="abona" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Se carga a CC</label>
                        <input type="number" class="form-control form-control-modern" id="monto_cc" step="0.01" min="0" value="0">
                        <small class="form-text text-muted">Podés definir manualmente cuánto va a cuenta corriente. El precio unitario de cada item se puede ajustar libremente hasta generar la venta.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label>Observación interna</label>
                        <textarea id="observacion_venta" class="form-control form-control-modern" rows="3" placeholder="Detalle opcional de la operación"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card card-modern">
                <div class="card-header card-header-modern">
                    <i class="fas fa-shopping-cart mr-2"></i> Pedido actual
                </div>
                <div class="card-body card-body-modern">
                    <div class="table-responsive">
                        <table class="table table-modern" id="tblDetalle">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio unit.</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="detalle_venta">
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="fas fa-box-open fa-2x mb-2"></i>
                                            <div>No hay productos cargados.</div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-right">Total</th>
                                    <th id="total_tabla">$0,00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="total-display">
                <p>Total del pedido</p>
                <div class="total-amount" id="total-amount">$0,00</div>
            </div>

            <div class="d-flex flex-wrap justify-content-end acciones-venta premium-gap">
                <button class="btn btn-outline-modern btn-modern" id="btn_recalcular" type="button">
                    <i class="fas fa-sync-alt mr-1"></i> Recalcular
                </button>
                <button class="btn btn-success-modern btn-modern" id="btn_generar" type="button">
                    <i class="fas fa-save mr-1"></i> Generar venta
                </button>
            </div>
        </div>
    </div>
</div>

<div id="nuevo_cliente_venta" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header modal-header-accent">
                <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i> Nuevo cliente</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="form_nuevo_cliente">
                    <?php if ($hasClienteOptica) { ?>
                    <div class="form-group">
                        <label>Óptica</label>
                        <input type="text" class="form-control" id="optica_cliente" required>
                    </div>
                    <?php } ?>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" class="form-control" id="nombre_cliente" required>
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" class="form-control" id="telefono_cliente" required>
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" class="form-control" id="direccion_cliente" required>
                    </div>
                    <?php if ($hasClienteLocalidad) { ?>
                    <div class="form-group">
                        <label>Localidad</label>
                        <input type="text" class="form-control" id="localidad_cliente" required>
                    </div>
                    <?php } ?>
                    <?php if ($hasClienteProvincia) { ?>
                    <div class="form-group">
                        <label>Provincia</label>
                        <input type="text" class="form-control" id="provincia_cliente" required>
                    </div>
                    <?php } ?>
                    <?php if ($hasClienteCodigoPostal) { ?>
                    <div class="form-group">
                        <label>Código postal</label>
                        <input type="text" class="form-control" id="codigo_postal_cliente" required>
                    </div>
                    <?php } ?>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Tipo documento</label>
                            <select class="form-control" id="tipo_documento_cliente">
                                <option value="96">DNI</option>
                                <option value="80">CUIT</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>DNI</label>
                            <input type="text" class="form-control" id="dni_cliente">
                        </div>
                        <div class="form-group col-md-4">
                            <label>CUIT</label>
                            <input type="text" class="form-control" id="cuit_cliente">
                        </div>
                    </div>
                    <?php if ($hasClienteCondicionIva) { ?>
                    <div class="form-group">
                        <label>Condición IVA</label>
                        <select class="form-control" id="condicion_iva_cliente">
                            <?php foreach ($condicionesIva as $condicionIva) { ?>
                                <option value="<?php echo htmlspecialchars($condicionIva); ?>" <?php echo $condicionIva === 'Consumidor Final' ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($condicionIva); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php } ?>
                    <div class="text-right">
                        <button type="button" class="btn btn-success-modern btn-modern" id="btn_guardar_cliente" onclick="guardarNuevoCliente()">
                            Guardar cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once "includes/footer.php"; ?>
