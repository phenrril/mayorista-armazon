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

$schemaReady = mayorista_column_exists($conexion, 'producto', 'precio_mayorista')
    && mayorista_column_exists($conexion, 'ventas', 'tipo_venta')
    && mayorista_table_exists($conexion, 'cuenta_corriente')
    && mayorista_table_exists($conexion, 'movimientos_cc');

include_once "includes/header.php";
?>
<style>
.ventas-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #243b55 0%, #141e30 100%);
    color: #fff;
    border-radius: 18px;
    padding: 28px 32px;
    margin-bottom: 24px;
    box-shadow: 0 15px 35px rgba(20, 30, 48, 0.2);
}

.page-header h2 {
    margin: 0;
    font-weight: 700;
}

.page-header p {
    margin: 8px 0 0;
    opacity: 0.85;
}

.card-modern {
    border: none;
    border-radius: 18px;
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
    overflow: hidden;
    margin-bottom: 24px;
}

.card-header-modern {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #fff;
    padding: 18px 24px;
    font-weight: 600;
    border: 0;
}

.card-body-modern {
    padding: 24px;
}

.form-control-modern,
.custom-select-modern {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 11px 14px;
}

select.custom-select-modern {
    height: 48px;
    padding-right: 40px;
    line-height: 1.2;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: linear-gradient(45deg, transparent 50%, #64748b 50%), linear-gradient(135deg, #64748b 50%, transparent 50%);
    background-position: calc(100% - 18px) calc(50% - 3px), calc(100% - 12px) calc(50% - 3px);
    background-size: 6px 6px, 6px 6px;
    background-repeat: no-repeat;
}

.form-control-modern:focus,
.custom-select-modern:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.15);
}

.badge-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    font-weight: 600;
}

.cc-metric {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px;
    height: 100%;
}

.cc-metric h6 {
    font-size: 0.85rem;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 8px;
}

.cc-value {
    font-size: 1.55rem;
    font-weight: 700;
    color: #0f172a;
}

.product-search-box {
    background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
    border-radius: 18px;
    padding: 24px;
    border: 1px solid #fed7aa;
}

.table-modern thead th {
    background: #1e293b;
    color: #fff;
    border: 0;
    vertical-align: middle;
}

.table-modern td {
    vertical-align: middle;
}

.precio-editable-input {
    width: 115px;
    border: 1px dashed #94a3b8;
    border-radius: 8px;
    padding: 6px 8px;
    text-align: right;
    background: #fffef3;
}

.total-display {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    color: #fff;
    border-radius: 18px;
    padding: 28px;
    text-align: center;
    box-shadow: 0 12px 35px rgba(16, 185, 129, 0.25);
}

.total-display p {
    margin: 0;
    opacity: 0.88;
}

.total-display .total-amount {
    font-size: 2.5rem;
    font-weight: 700;
}

.acciones-venta {
    margin-top: 18px;
}

.btn-modern {
    border: 0;
    border-radius: 12px;
    padding: 12px 22px;
    font-weight: 600;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #fff;
}

.btn-success-modern {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    color: #fff;
}

.btn-outline-modern {
    background: #fff;
    border: 1px solid #cbd5e1;
    color: #334155;
}

.empty-state {
    text-align: center;
    color: #64748b;
    padding: 36px 0;
}
</style>

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

    <div class="card card-modern">
        <div class="card-header card-header-modern d-flex justify-content-between align-items-center">
            <span><i class="fas fa-user mr-2"></i> Cliente y condiciones</span>
            <button type="button" class="btn btn-sm btn-success-modern btn-modern" data-toggle="modal" data-target="#nuevo_cliente_venta">
                <i class="fas fa-plus mr-1"></i> Nuevo cliente
            </button>
        </div>
        <div class="card-body card-body-modern">
            <div class="row">
                <div class="col-lg-4">
                    <input type="hidden" id="idcliente" value="">
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
                        <div class="d-flex flex-wrap" style="gap:12px;">
                            <label class="badge-chip"><input type="radio" name="pago" value="1" checked> Efectivo</label>
                            <label class="badge-chip"><input type="radio" name="pago" value="2"> Crédito</label>
                            <label class="badge-chip"><input type="radio" name="pago" value="3"> Débito</label>
                            <label class="badge-chip"><input type="radio" name="pago" value="4"> Transferencia</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Abona ahora</label>
                        <input type="number" class="form-control form-control-modern" id="abona" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Se carga a CC</label>
                        <input type="number" class="form-control form-control-modern" id="monto_cc" step="0.01" min="0" value="0">
                        <small class="form-text text-muted">Podés definir manualmente cuánto va a cuenta corriente. El precio unitario de cada item se puede ajustar una sola vez por pedido.</small>
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

            <div class="d-flex flex-wrap justify-content-end acciones-venta" style="gap:12px;">
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
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color:#fff;">
                <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i> Nuevo cliente</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="form_nuevo_cliente">
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
                    <div class="form-group">
                        <label>DNI / CUIT</label>
                        <input type="text" class="form-control" id="dni_cliente">
                    </div>
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
