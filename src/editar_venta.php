<?php
session_start();
include "../conexion.php";
require_once "includes/mayorista_helpers.php";

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('ventas', 'nueva_venta'));

$idVenta = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id_venta'] ?? 0);
if ($idVenta <= 0) {
    header("Location: lista_ventas.php");
    exit();
}

$ventaQuery = mysqli_query(
    $conexion,
    "SELECT v.*, c.nombre AS cliente_nombre
     FROM ventas v
     LEFT JOIN cliente c ON v.id_cliente = c.idcliente
     WHERE v.id = $idVenta
     LIMIT 1"
);
$venta = $ventaQuery ? mysqli_fetch_assoc($ventaQuery) : null;
if (!$venta) {
    header("Location: lista_ventas.php");
    exit();
}

if (mayorista_venta_tiene_factura_aprobada($conexion, $idVenta)) {
    header("Location: lista_ventas.php");
    exit();
}

$tipoVenta = mayorista_tipo_venta_valido($venta['tipo_venta'] ?? 'minorista');
$hasMayorista = mayorista_column_exists($conexion, 'producto', 'precio_mayorista');
$alert = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productos = $_POST['id_producto'] ?? array();
    $cantidades = $_POST['cantidad'] ?? array();
    $precios = $_POST['precio'] ?? array();
    $abona = round((float) ($venta['abona'] ?? 0), 2);
    $metodoPago = (int) ($venta['id_metodo'] ?? 1);
    $idCliente = (int) ($venta['id_cliente'] ?? 0);

    $nuevosItems = array();
    foreach ($productos as $index => $idProductoRaw) {
        $idProducto = (int) $idProductoRaw;
        $cantidad = max(0, (int) ($cantidades[$index] ?? 0));
        $precio = round((float) ($precios[$index] ?? 0), 2);
        if ($idProducto <= 0 || $cantidad <= 0 || $precio < 0) {
            continue;
        }

        $nuevosItems[] = array(
            'id_producto' => $idProducto,
            'cantidad' => $cantidad,
            'precio' => $precio,
        );
    }

    if (empty($nuevosItems)) {
        $alert = '<div class="alert alert-danger">La venta debe tener al menos un producto.</div>';
    } else {
        $detalleActualQuery = mysqli_query($conexion, "SELECT * FROM detalle_venta WHERE id_venta = $idVenta");
        $detalleActual = array();
        $cantidadesAnteriores = array();
        if ($detalleActualQuery) {
            while ($row = mysqli_fetch_assoc($detalleActualQuery)) {
                $detalleActual[] = $row;
                $idProductoDetalle = (int) $row['id_producto'];
                $cantidadesAnteriores[$idProductoDetalle] = ($cantidadesAnteriores[$idProductoDetalle] ?? 0) + (int) $row['cantidad'];
            }
        }

        $cantidadesNuevas = array();
        $idsProductos = array();
        foreach ($nuevosItems as $item) {
            $cantidadesNuevas[$item['id_producto']] = ($cantidadesNuevas[$item['id_producto']] ?? 0) + $item['cantidad'];
            $idsProductos[$item['id_producto']] = true;
        }
        foreach (array_keys($cantidadesAnteriores) as $idProductoAnterior) {
            $idsProductos[$idProductoAnterior] = true;
        }

        $productosData = array();
        if (!empty($idsProductos)) {
            $idsSql = implode(',', array_map('intval', array_keys($idsProductos)));
            $queryProductos = mysqli_query($conexion, "SELECT * FROM producto WHERE codproducto IN ($idsSql)");
            while ($row = mysqli_fetch_assoc($queryProductos)) {
                $productosData[(int) $row['codproducto']] = $row;
            }
        }

        $totalNuevo = 0;
        $precioModificado = 0;
        $stockFinal = array();
        $errorStock = '';

        foreach ($idsProductos as $idProducto => $_unused) {
            if (!isset($productosData[$idProducto])) {
                $errorStock = 'Uno de los productos seleccionados ya no existe.';
                break;
            }

            $producto = $productosData[$idProducto];
            $stockActual = (int) $producto['existencia'];
            $stockDisponible = $stockActual + (int) ($cantidadesAnteriores[$idProducto] ?? 0);
            $cantidadNueva = (int) ($cantidadesNuevas[$idProducto] ?? 0);
            if ($cantidadNueva > $stockDisponible) {
                $errorStock = 'Stock insuficiente para ' . $producto['descripcion'] . '. Disponible para edición: ' . $stockDisponible . '.';
                break;
            }
            $stockFinal[$idProducto] = $stockDisponible - $cantidadNueva;
        }

        foreach ($nuevosItems as $item) {
            $producto = $productosData[$item['id_producto']] ?? null;
            if (!$producto) {
                continue;
            }
            $precioBase = round((float) mayorista_precio_producto($producto, $tipoVenta), 2);
            if (abs($item['precio'] - $precioBase) > 0.009) {
                $precioModificado = 1;
            }
            $totalNuevo += $item['cantidad'] * $item['precio'];
        }

        $totalNuevo = round($totalNuevo, 2);
        if ($errorStock === '' && $totalNuevo < $abona) {
            $errorStock = 'El nuevo total no puede ser menor al importe ya abonado.';
        }

        if ($errorStock !== '') {
            $alert = '<div class="alert alert-danger">' . htmlspecialchars($errorStock) . '</div>';
        } else {
            $montoCcNuevo = round($totalNuevo - $abona, 2);
            mysqli_begin_transaction($conexion);

            try {
                mysqli_query($conexion, "DELETE FROM detalle_venta WHERE id_venta = $idVenta");
                foreach ($nuevosItems as $item) {
                    $producto = $productosData[$item['id_producto']];
                    $precioBase = round((float) mayorista_precio_producto($producto, $tipoVenta), 2);
                    $precioPersonalizado = abs($item['precio'] - $precioBase) > 0.009 ? $item['precio'] : null;
                    $camposDetalle = array('id_producto', 'id_venta', 'cantidad', 'precio', 'precio_original', 'abona', 'resto', 'obrasocial');
                    $valoresDetalle = array($item['id_producto'], $idVenta, $item['cantidad'], $item['precio'], $precioBase, $abona, $montoCcNuevo, 0);
                    if (mayorista_column_exists($conexion, 'detalle_venta', 'idcristal')) {
                        $camposDetalle[] = 'idcristal';
                        $valoresDetalle[] = 0;
                    }
                    if (mayorista_column_exists($conexion, 'detalle_venta', 'precio_personalizado')) {
                        $camposDetalle[] = 'precio_personalizado';
                        $valoresDetalle[] = $precioPersonalizado === null ? 'NULL' : $precioPersonalizado;
                    }
                    if (mayorista_column_exists($conexion, 'detalle_venta', 'tipo_precio')) {
                        $camposDetalle[] = 'tipo_precio';
                        $valoresDetalle[] = "'" . $tipoVenta . "'";
                    }

                    $valoresSql = array();
                    foreach ($valoresDetalle as $valorDetalle) {
                        $valoresSql[] = $valorDetalle === 'NULL' ? 'NULL' : $valorDetalle;
                    }
                    $insertDetalle = mysqli_query(
                        $conexion,
                        "INSERT INTO detalle_venta(" . implode(', ', $camposDetalle) . ")
                         VALUES (" . implode(', ', $valoresSql) . ")"
                    );
                    if (!$insertDetalle) {
                        throw new Exception('No se pudo actualizar el detalle de la venta.');
                    }
                }

                foreach ($stockFinal as $idProductoStock => $nuevoStock) {
                    mysqli_query(
                        $conexion,
                        "UPDATE producto
                         SET existencia = $nuevoStock,
                             estado = IF($nuevoStock <= 0, 0, estado)
                         WHERE codproducto = " . (int) $idProductoStock
                    );
                }

                mysqli_query($conexion, "DELETE FROM movimientos_cc WHERE id_venta = $idVenta AND tipo = 'cargo'");
                $saldoCcCliente = 0;
                if ($montoCcNuevo > 0) {
                    $validacionCc = mayorista_validar_nuevo_cargo_cc($conexion, $idCliente, $montoCcNuevo);
                    if (!$validacionCc['permitido']) {
                        throw new Exception('La edición supera el límite de crédito del cliente.');
                    }
                    $saldoCcCliente = mayorista_registrar_movimiento_cc(
                        $conexion,
                        $idCliente,
                        'cargo',
                        $montoCcNuevo,
                        'Venta editada #' . $idVenta,
                        $id_user,
                        $idVenta
                    );
                } else {
                    $cuenta = mayorista_obtener_cuenta_corriente($conexion, $idCliente);
                    $saldoCcCliente = (float) ($cuenta['saldo_actual'] ?? 0);
                }

                mysqli_query(
                    $conexion,
                    "UPDATE ventas
                     SET total = $totalNuevo,
                         resto = $montoCcNuevo,
                         monto_cc = $montoCcNuevo,
                         saldo_cc_cliente = $saldoCcCliente,
                         precio_modificado = $precioModificado
                     WHERE id = $idVenta"
                );

                if (mayorista_table_exists($conexion, 'postpagos')) {
                    mysqli_query(
                        $conexion,
                        "UPDATE postpagos
                         SET abona = $abona,
                             resto = $montoCcNuevo,
                             precio = $totalNuevo,
                             precio_original = $totalNuevo
                         WHERE id_venta = $idVenta"
                    );
                }

                mysqli_commit($conexion);
                $alert = '<div class="alert alert-success">Venta actualizada correctamente.</div>';

                $ventaQuery = mysqli_query(
                    $conexion,
                    "SELECT v.*, c.nombre AS cliente_nombre
                     FROM ventas v
                     LEFT JOIN cliente c ON v.id_cliente = c.idcliente
                     WHERE v.id = $idVenta
                     LIMIT 1"
                );
                $venta = $ventaQuery ? mysqli_fetch_assoc($ventaQuery) : $venta;
            } catch (Exception $e) {
                mysqli_rollback($conexion);
                $alert = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}

$detalleVenta = mysqli_query(
    $conexion,
    "SELECT dv.*, p.codigo, p.descripcion
     FROM detalle_venta dv
     INNER JOIN producto p ON dv.id_producto = p.codproducto
     WHERE dv.id_venta = $idVenta
     ORDER BY dv.id ASC"
);
include_once "includes/header.php";
?>
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span>Editar remito / venta #<?php echo (int) $venta['id']; ?></span>
                <a href="lista_ventas.php" class="btn btn-sm btn-light">Volver</a>
            </div>
            <div class="card-body">
                <?php echo $alert; ?>
                <div class="row mb-4">
                    <div class="col-md-3"><strong>Cliente:</strong><br><?php echo htmlspecialchars($venta['cliente_nombre'] ?: 'Consumidor final'); ?></div>
                    <div class="col-md-3"><strong>Tipo:</strong><br><?php echo htmlspecialchars(ucfirst($tipoVenta)); ?></div>
                    <div class="col-md-3"><strong>Abonado:</strong><br><?php echo mayorista_formatear_moneda($venta['abona']); ?></div>
                    <div class="col-md-3"><strong>Total actual:</strong><br><?php echo mayorista_formatear_moneda($venta['total']); ?></div>
                </div>

                <form method="post" id="formEditarVenta">
                    <input type="hidden" name="id_venta" value="<?php echo (int) $venta['id']; ?>">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="tablaDetalleEditable">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">Producto</th>
                                    <th style="width: 15%;">Cantidad</th>
                                    <th style="width: 20%;">Precio</th>
                                    <th style="width: 15%;">Subtotal</th>
                                    <th style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($detalleVenta) {
                                    while ($row = mysqli_fetch_assoc($detalleVenta)) { ?>
                                        <tr>
                                            <td>
                                                <input type="hidden" name="id_producto[]" class="producto-id-hidden" value="<?php echo (int) $row['id_producto']; ?>">
                                                <input
                                                    type="text"
                                                    class="form-control producto-buscador"
                                                    value="<?php echo htmlspecialchars($row['codigo'] . ' - ' . $row['descripcion']); ?>"
                                                    placeholder="Escribí código o descripción"
                                                    autocomplete="off"
                                                    required
                                                >
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <div class="input-group-prepend">
                                                        <button type="button" class="btn btn-outline-secondary ajustar-cantidad" data-delta="-1" aria-label="Restar una unidad">-</button>
                                                    </div>
                                                    <input type="number" min="1" name="cantidad[]" class="form-control cantidad-input text-center" value="<?php echo (int) $row['cantidad']; ?>" required>
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-outline-secondary ajustar-cantidad" data-delta="1" aria-label="Sumar una unidad">+</button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><input type="number" step="0.01" min="0" name="precio[]" class="form-control precio-input" value="<?php echo (float) $row['precio']; ?>" required></td>
                                            <td class="subtotal-cell"><?php echo mayorista_formatear_moneda((float) $row['precio'] * (int) $row['cantidad']); ?></td>
                                            <td><button type="button" class="btn btn-sm btn-danger remover-fila">&times;</button></td>
                                        </tr>
                                <?php }
                                } ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-right">Nuevo total</th>
                                    <th id="nuevoTotalTabla"><?php echo mayorista_formatear_moneda($venta['total']); ?></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <button type="button" class="btn btn-outline-primary mb-3" id="btnAgregarFila">Agregar producto</button>
                    <div>
                        <button class="btn btn-success" type="submit">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<template id="filaProductoTemplate">
    <tr>
        <td>
            <input type="hidden" name="id_producto[]" class="producto-id-hidden" value="">
            <input
                type="text"
                class="form-control producto-buscador"
                value=""
                placeholder="Escribí 2 o 3 letras para buscar"
                autocomplete="off"
                required
            >
        </td>
        <td>
            <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                    <button type="button" class="btn btn-outline-secondary ajustar-cantidad" data-delta="-1" aria-label="Restar una unidad">-</button>
                </div>
                <input type="number" min="1" name="cantidad[]" class="form-control cantidad-input text-center" value="1" required>
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-secondary ajustar-cantidad" data-delta="1" aria-label="Sumar una unidad">+</button>
                </div>
            </div>
        </td>
        <td><input type="number" step="0.01" min="0" name="precio[]" class="form-control precio-input" value="0" required></td>
        <td class="subtotal-cell">$0,00</td>
        <td><button type="button" class="btn btn-sm btn-danger remover-fila">&times;</button></td>
    </tr>
</template>

<script>
function formatearMoneda(monto) {
    return '$' + (parseFloat(monto) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function recalcularTotalesEdicion() {
    let total = 0;
    document.querySelectorAll('#tablaDetalleEditable tbody tr').forEach(function(row) {
        const cantidadInput = row.querySelector('.cantidad-input');
        const precioInput = row.querySelector('.precio-input');
        const subtotalCell = row.querySelector('.subtotal-cell');
        const cantidad = parseFloat(cantidadInput ? cantidadInput.value : 0) || 0;
        const precio = parseFloat(precioInput ? precioInput.value : 0) || 0;
        const subtotal = cantidad * precio;
        total += subtotal;
        if (subtotalCell) {
            subtotalCell.textContent = formatearMoneda(subtotal);
        }
    });
    const totalCell = document.getElementById('nuevoTotalTabla');
    if (totalCell) {
        totalCell.textContent = formatearMoneda(total);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const btnAgregarFila = document.getElementById('btnAgregarFila');
    const tablaBody = document.querySelector('#tablaDetalleEditable tbody');
    const tipoVentaActual = <?php echo json_encode($tipoVenta); ?>;

    function inicializarBuscadorProducto(input) {
        if (!input || typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.autocomplete !== 'function') {
            return;
        }

        const $input = window.jQuery(input);
        if ($input.data('ui-autocomplete')) {
            return;
        }

        $input.autocomplete({
            minLength: 2,
            source: function(request, response) {
                window.jQuery.getJSON('ajax.php', {
                    pro: request.term,
                    tipo_venta: tipoVentaActual
                }, function(items) {
                    const resultados = Array.isArray(items) ? items : [];
                    const termino = String(request.term || '').trim();

                    if (termino.length >= 2 && resultados.length === 0) {
                        response([{
                            id: 0,
                            label: 'No hay coincidencias',
                            value: termino,
                            noMatch: true
                        }]);
                        return;
                    }

                    response(resultados);
                });
            },
            appendTo: '#layoutSidenav_content',
            focus: function(event, ui) {
                if (ui.item && ui.item.noMatch) {
                    event.preventDefault();
                    return false;
                }
            },
            select: function(event, ui) {
                if (ui.item && ui.item.noMatch) {
                    event.preventDefault();
                    return false;
                }

                const fila = input.closest('tr');
                const hidden = fila ? fila.querySelector('.producto-id-hidden') : null;
                const precioInput = fila ? fila.querySelector('.precio-input') : null;

                input.value = ui.item.label;
                if (hidden) {
                    hidden.value = ui.item.id;
                }
                if (precioInput) {
                    precioInput.value = ui.item.precio;
                }
                recalcularTotalesEdicion();
                return false;
            }
        });

        const instance = $input.autocomplete('instance');
        if (instance) {
            instance._renderItem = function(ul, item) {
                if (item.noMatch) {
                    return window.jQuery('<li>')
                        .addClass('autocomplete-empty-state')
                        .append('<div class="ui-menu-item-wrapper">No hay coincidencias</div>')
                        .appendTo(ul);
                }

                return window.jQuery('<li>')
                    .append(
                        window.jQuery('<div class="ui-menu-item-wrapper">').append(
                            window.jQuery('<div class="autocomplete-client-name">').text(item.label),
                            window.jQuery('<small class="autocomplete-client-meta">').text(
                                [
                                    item.marca ? 'Marca: ' + item.marca : '',
                                    item.modelo ? 'Modelo: ' + item.modelo : '',
                                    item.tipo_material ? 'Material: ' + item.tipo_material : '',
                                    'Stock: ' + (item.existencia || 0)
                                ].filter(Boolean).join(' | ')
                            )
                        )
                    )
                    .appendTo(ul);
            };
        }
    }

    if (btnAgregarFila && tablaBody) {
        btnAgregarFila.addEventListener('click', function() {
            const template = document.getElementById('filaProductoTemplate');
            if (!template || !template.content) {
                return;
            }

            const clone = document.importNode(template.content, true);
            tablaBody.appendChild(clone);
            const nuevaFila = tablaBody.lastElementChild;
            if (nuevaFila) {
                inicializarBuscadorProducto(nuevaFila.querySelector('.producto-buscador'));
            }
            recalcularTotalesEdicion();
        });
    }

    document.addEventListener('click', function(event) {
        const botonCantidad = event.target.closest('.ajustar-cantidad');
        if (botonCantidad) {
            const fila = botonCantidad.closest('tr');
            const cantidadInput = fila ? fila.querySelector('.cantidad-input') : null;
            const delta = parseInt(botonCantidad.dataset.delta || '0', 10);
            if (!fila || !cantidadInput || !delta) {
                return;
            }

            const cantidadActual = parseInt(cantidadInput.value || '0', 10) || 0;
            if (delta < 0 && cantidadActual <= 1) {
                fila.remove();
                recalcularTotalesEdicion();
                return;
            }

            cantidadInput.value = Math.max(1, cantidadActual + delta);
            recalcularTotalesEdicion();
            return;
        }

        const botonRemover = event.target.closest('.remover-fila');
        if (botonRemover) {
            const fila = botonRemover.closest('tr');
            if (fila) {
                fila.remove();
                recalcularTotalesEdicion();
            }
            return;
        }
    });

    document.addEventListener('change', function(event) {
        if (event.target.matches('.cantidad-input, .precio-input')) {
            recalcularTotalesEdicion();
        }
    });

    document.addEventListener('input', function(event) {
        if (event.target.matches('.producto-buscador')) {
            const fila = event.target.closest('tr');
            const hidden = fila ? fila.querySelector('.producto-id-hidden') : null;
            if (hidden) {
                hidden.value = '';
            }
            return;
        }

        if (event.target.matches('.cantidad-input, .precio-input')) {
            recalcularTotalesEdicion();
        }
    });

    const formEditarVenta = document.getElementById('formEditarVenta');
    if (formEditarVenta) {
        formEditarVenta.addEventListener('submit', function(event) {
            let filaInvalida = false;
            document.querySelectorAll('#tablaDetalleEditable tbody tr').forEach(function(row) {
                const buscador = row.querySelector('.producto-buscador');
                const hidden = row.querySelector('.producto-id-hidden');
                const texto = buscador ? String(buscador.value || '').trim() : '';
                const idProducto = hidden ? parseInt(hidden.value || '0', 10) : 0;

                if (texto !== '' && idProducto <= 0) {
                    filaInvalida = true;
                }
            });

            if (filaInvalida) {
                event.preventDefault();
                alert('Seleccioná un producto válido desde la búsqueda en todas las filas antes de guardar.');
            }
        });
    }

    document.querySelectorAll('.producto-buscador').forEach(inicializarBuscadorProducto);
    recalcularTotalesEdicion();
});
</script>

<?php include_once "includes/footer.php"; ?>
