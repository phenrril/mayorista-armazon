<?php
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";
session_start();

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'No autorizado'));
    exit();
}

if (!($conexion instanceof mysqli)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'No se pudo conectar a la base de datos'));
    exit();
}
/** @var mysqli $conexion */

$id_user = (int) $_SESSION['idUser'];

function ajax_json($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function ajax_get_producto_query($conexion)
{
    $fields = array('codproducto', 'codigo', 'descripcion', 'precio', 'existencia', 'estado', 'marca');
    if (mayorista_column_exists($conexion, 'producto', 'precio_mayorista')) {
        $fields[] = 'precio_mayorista';
    }
    if (mayorista_column_exists($conexion, 'producto', 'tipo')) {
        $fields[] = 'tipo';
    }
    if (mayorista_column_exists($conexion, 'producto', 'costo')) {
        $fields[] = 'costo';
    }
    if (mayorista_column_exists($conexion, 'producto', 'modelo')) {
        $fields[] = 'modelo';
    }
    if (mayorista_column_exists($conexion, 'producto', 'tipo_material')) {
        $fields[] = 'tipo_material';
    }

    return implode(', ', $fields);
}

function ajax_get_cliente_query($conexion)
{
    $fields = array('idcliente', 'nombre', 'telefono', 'direccion', 'dni', 'estado');
    if (mayorista_column_exists($conexion, 'cliente', 'optica')) {
        $fields[] = 'optica';
    }
    if (mayorista_column_exists($conexion, 'cliente', 'localidad')) {
        $fields[] = 'localidad';
    }
    if (mayorista_column_exists($conexion, 'cliente', 'codigo_postal')) {
        $fields[] = 'codigo_postal';
    }
    if (mayorista_column_exists($conexion, 'cliente', 'provincia')) {
        $fields[] = 'provincia';
    }
    if (mayorista_column_exists($conexion, 'cliente', 'cuit')) {
        $fields[] = 'cuit';
    }
    if (mayorista_column_exists($conexion, 'cliente', 'condicion_iva')) {
        $fields[] = 'condicion_iva';
    }
    if (mayorista_column_exists($conexion, 'cliente', 'tipo_documento')) {
        $fields[] = 'tipo_documento';
    }

    return implode(', ', $fields);
}

function ajax_limpiar_temporales($conexion, $id_user)
{
    mysqli_query($conexion, "DELETE FROM detalle_temp WHERE id_usuario = $id_user");
    if (mayorista_table_exists($conexion, 'descuento')) {
        mysqli_query($conexion, "DELETE FROM descuento WHERE id_usuario = $id_user");
    }
    unset($_SESSION['detalle_temp_precios_editados']);
}

function ajax_precio_detalle_editado($idDetalle)
{
    $idDetalle = (int) $idDetalle;
    if ($idDetalle <= 0) {
        return false;
    }

    if (empty($_SESSION['detalle_temp_precios_editados']) || !is_array($_SESSION['detalle_temp_precios_editados'])) {
        return false;
    }

    return !empty($_SESSION['detalle_temp_precios_editados'][$idDetalle]);
}

function ajax_marcar_precio_detalle_editado($idDetalle)
{
    $idDetalle = (int) $idDetalle;
    if ($idDetalle <= 0) {
        return;
    }

    if (empty($_SESSION['detalle_temp_precios_editados']) || !is_array($_SESSION['detalle_temp_precios_editados'])) {
        $_SESSION['detalle_temp_precios_editados'] = array();
    }

    $_SESSION['detalle_temp_precios_editados'][$idDetalle] = true;
}

function ajax_limpiar_precio_detalle_editado($idDetalle)
{
    $idDetalle = (int) $idDetalle;
    if ($idDetalle <= 0 || empty($_SESSION['detalle_temp_precios_editados']) || !is_array($_SESSION['detalle_temp_precios_editados'])) {
        return;
    }

    unset($_SESSION['detalle_temp_precios_editados'][$idDetalle]);
}

if (isset($_GET['q'])) {
    $datos = array();
    $busqueda = mysqli_real_escape_string($conexion, trim($_GET['q']));
    $clienteQuery = ajax_get_cliente_query($conexion);
    $condicionesBusqueda = array(
        "nombre LIKE '%$busqueda%'",
        "dni LIKE '%$busqueda%'",
        "telefono LIKE '%$busqueda%'",
    );
    if (mayorista_column_exists($conexion, 'cliente', 'optica')) {
        $condicionesBusqueda[] = "optica LIKE '%$busqueda%'";
    }
    $query = mysqli_query(
        $conexion,
        "SELECT $clienteQuery FROM cliente
         WHERE estado = 1
         AND (" . implode(' OR ', $condicionesBusqueda) . ")
         ORDER BY nombre ASC
         LIMIT 20"
    );

    while ($row = mysqli_fetch_assoc($query)) {
        $cc = mayorista_obtener_cuenta_corriente($conexion, (int) $row['idcliente']);
        $label = !empty($row['optica'])
            ? trim($row['optica'] . ' - ' . $row['nombre'])
            : $row['nombre'];
        $datos[] = array(
            'id' => (int) $row['idcliente'],
            'label' => $label,
            'dni' => $row['dni'],
            'direccion' => $row['direccion'],
            'telefono' => $row['telefono'],
            'optica' => $row['optica'] ?? '',
            'localidad' => $row['localidad'] ?? '',
            'codigo_postal' => $row['codigo_postal'] ?? '',
            'provincia' => $row['provincia'] ?? '',
            'cuit' => $row['cuit'] ?? '',
            'condicion_iva' => $row['condicion_iva'] ?? 'Consumidor Final',
            'tipo_documento' => (int) ($row['tipo_documento'] ?? 96),
            'saldo_cc' => (float) $cc['saldo_actual'],
            'limite_credito' => (float) $cc['limite_credito'],
        );
    }

    ajax_json($datos);
}

if (isset($_GET['cliente_cc'])) {
    $idCliente = (int) $_GET['cliente_cc'];
    if ($idCliente <= 0) {
        ajax_json(array('saldo_actual' => 0, 'limite_credito' => 0, 'activo' => 0));
    }

    $cuenta = mayorista_obtener_cuenta_corriente($conexion, $idCliente);
    ajax_json(array(
        'id' => $cuenta['id'],
        'saldo_actual' => (float) $cuenta['saldo_actual'],
        'limite_credito' => (float) $cuenta['limite_credito'],
        'activo' => (int) $cuenta['activo'],
    ));
}

if (isset($_GET['pro'])) {
    $datos = array();
    $nombre = mysqli_real_escape_string($conexion, trim($_GET['pro']));
    $tipoVenta = mayorista_tipo_venta_valido($_GET['tipo_venta'] ?? 'minorista');
    $productoQuery = ajax_get_producto_query($conexion);
    $condicionesBusqueda = array(
        "codigo LIKE '%$nombre%'",
        "descripcion LIKE '%$nombre%'",
        "marca LIKE '%$nombre%'"
    );

    if (mayorista_column_exists($conexion, 'producto', 'modelo')) {
        $condicionesBusqueda[] = "modelo LIKE '%$nombre%'";
    }
    if (mayorista_column_exists($conexion, 'producto', 'tipo_material')) {
        $condicionesBusqueda[] = "tipo_material LIKE '%$nombre%'";
    }

    $producto = mysqli_query(
        $conexion,
        "SELECT $productoQuery
         FROM producto
         WHERE estado = 1
         AND existencia > 0
         AND (" . implode(' OR ', $condicionesBusqueda) . ")
         ORDER BY descripcion ASC
         LIMIT 20"
    );

    while ($row = mysqli_fetch_assoc($producto)) {
        $datos[] = array(
            'id' => (int) $row['codproducto'],
            'label' => $row['codigo'] . ' - ' . $row['descripcion'],
            'value' => $row['descripcion'],
            'precio' => mayorista_precio_producto($row, $tipoVenta),
            'precio_minorista' => (float) $row['precio'],
            'precio_mayorista' => isset($row['precio_mayorista']) ? (float) $row['precio_mayorista'] : (float) $row['precio'],
            'existencia' => (int) $row['existencia'],
            'marca' => $row['marca'] ?? '',
            'modelo' => $row['modelo'] ?? '',
            'tipo_material' => $row['tipo_material'] ?? '',
            'tipo' => $row['tipo'] ?? 'receta',
            'costo' => isset($row['costo']) ? (int) $row['costo'] : 0,
        );
    }

    ajax_json($datos);
}

if (isset($_GET['detalle'])) {
    $datos = array();
    $query = mysqli_query(
        $conexion,
        "SELECT d.id, d.id_producto, d.cantidad, d.precio_venta, d.total, p.codigo, p.descripcion
         FROM detalle_temp d
         INNER JOIN producto p ON d.id_producto = p.codproducto
         WHERE d.id_usuario = $id_user
         ORDER BY d.id ASC"
    );

    while ($row = mysqli_fetch_assoc($query)) {
        $datos[] = array(
            'id' => (int) $row['id'],
            'id_producto' => (int) $row['id_producto'],
            'codigo' => $row['codigo'],
            'descripcion' => $row['descripcion'],
            'cantidad' => (int) $row['cantidad'],
            'precio_venta' => (float) $row['precio_venta'],
            'sub_total' => (float) $row['total'],
            'precio_editado' => ajax_precio_detalle_editado($row['id']),
        );
    }

    ajax_json($datos);
}

if (isset($_GET['delete_detalle'])) {
    $id_detalle = (int) $_GET['id'];
    $verificar = mysqli_query($conexion, "SELECT * FROM detalle_temp WHERE id = $id_detalle AND id_usuario = $id_user");
    $datos = $verificar ? mysqli_fetch_assoc($verificar) : null;

    if (!$datos) {
        echo 'error';
        exit();
    }

    if ((int) $datos['cantidad'] > 1) {
        $cantidad = (int) $datos['cantidad'] - 1;
        $precio = (float) $datos['precio_venta'];
        $total = $cantidad * $precio;
        $query = mysqli_query(
            $conexion,
            "UPDATE detalle_temp
             SET cantidad = $cantidad, total = $total
             WHERE id = $id_detalle AND id_usuario = $id_user"
        );
        echo $query ? 'restado' : 'error';
        exit();
    }

    $query = mysqli_query($conexion, "DELETE FROM detalle_temp WHERE id = $id_detalle AND id_usuario = $id_user");
    if ($query) {
        ajax_limpiar_precio_detalle_editado($id_detalle);
    }
    echo $query ? 'ok' : 'error';
    exit();
}

if (isset($_POST['ajustar_cantidad_detalle'])) {
    $id_detalle = (int) ($_POST['id'] ?? 0);
    $delta = (int) ($_POST['delta'] ?? 0);
    if ($id_detalle <= 0 || !in_array($delta, array(-1, 1), true)) {
        ajax_json('error');
    }

    $detalleQuery = mysqli_query(
        $conexion,
        "SELECT d.id, d.id_producto, d.cantidad, d.precio_venta, p.existencia
         FROM detalle_temp d
         INNER JOIN producto p ON d.id_producto = p.codproducto
         WHERE d.id = $id_detalle
         AND d.id_usuario = $id_user
         LIMIT 1"
    );
    $detalle = $detalleQuery ? mysqli_fetch_assoc($detalleQuery) : null;
    if (!$detalle) {
        ajax_json('error');
    }

    $cantidadActual = (int) $detalle['cantidad'];
    $nuevaCantidad = $cantidadActual + $delta;
    if ($nuevaCantidad <= 0) {
        $delete = mysqli_query($conexion, "DELETE FROM detalle_temp WHERE id = $id_detalle AND id_usuario = $id_user");
        if ($delete) {
            ajax_limpiar_precio_detalle_editado($id_detalle);
        }
        ajax_json($delete ? 'eliminado' : 'error');
    }

    $existencia = (int) $detalle['existencia'];
    if ($delta > 0 && $nuevaCantidad > $existencia) {
        ajax_json('stock_insuficiente');
    }

    $precio = (float) $detalle['precio_venta'];
    $nuevoTotal = $nuevaCantidad * $precio;
    $update = mysqli_query(
        $conexion,
        "UPDATE detalle_temp
         SET cantidad = $nuevaCantidad, total = $nuevoTotal
         WHERE id = $id_detalle AND id_usuario = $id_user"
    );
    ajax_json($update ? ($delta > 0 ? 'sumado' : 'restado') : 'error');
}

if (isset($_POST['update_precio'])) {
    $id_detalle = (int) $_POST['id'];
    $nuevo_precio = (float) $_POST['precio'];

    if ($nuevo_precio < 0) {
        echo 'error';
        exit();
    }

    $verificar = mysqli_query($conexion, "SELECT cantidad, precio_venta FROM detalle_temp WHERE id = $id_detalle AND id_usuario = $id_user");
    $datos = $verificar ? mysqli_fetch_assoc($verificar) : null;
    if (!$datos) {
        echo 'error';
        exit();
    }

    $precioActual = round((float) $datos['precio_venta'], 2);
    if (abs($nuevo_precio - $precioActual) <= 0.009) {
        echo 'ok';
        exit();
    }

    $cantidad = (int) $datos['cantidad'];
    $nuevo_total = $nuevo_precio * $cantidad;
    $query = mysqli_query(
        $conexion,
        "UPDATE detalle_temp
         SET precio_venta = $nuevo_precio, total = $nuevo_total
         WHERE id = $id_detalle AND id_usuario = $id_user"
    );

    if ($query) {
        ajax_marcar_precio_detalle_editado($id_detalle);
    }

    echo $query ? 'ok' : 'error';
    exit();
}

if (isset($_POST['actualizar_tipo_venta'])) {
    $tipoVenta = mayorista_tipo_venta_valido($_POST['tipo_venta'] ?? 'minorista');
    $queryFields = ajax_get_producto_query($conexion);
    $detalle = mysqli_query(
        $conexion,
        "SELECT d.id, d.cantidad, p.codproducto, $queryFields
         FROM detalle_temp d
         INNER JOIN producto p ON d.id_producto = p.codproducto
         WHERE d.id_usuario = $id_user
         ORDER BY d.id ASC"
    );

    if (!$detalle) {
        ajax_json(array('success' => false, 'mensaje' => 'No se pudo actualizar el tipo de venta.'), 500);
    }

    $actualizados = 0;
    while ($row = mysqli_fetch_assoc($detalle)) {
        $idDetalle = (int) $row['id'];
        if (ajax_precio_detalle_editado($idDetalle)) {
            continue;
        }

        $precio = round((float) mayorista_precio_producto($row, $tipoVenta), 2);
        $cantidad = (int) $row['cantidad'];
        $total = $precio * $cantidad;
        $update = mysqli_query(
            $conexion,
            "UPDATE detalle_temp
             SET precio_venta = $precio, total = $total
             WHERE id = $idDetalle AND id_usuario = $id_user"
        );

        if ($update) {
            $actualizados++;
        }
    }

    ajax_json(array(
        'success' => true,
        'actualizados' => $actualizados,
        'tipo_venta' => $tipoVenta,
    ));
}

if (isset($_POST['action'])) {
    $id = (int) $_POST['id'];
    $cant = max(1, (int) $_POST['cant']);
    $tipoVenta = mayorista_tipo_venta_valido($_POST['tipo_venta'] ?? 'minorista');

    $queryFields = ajax_get_producto_query($conexion);
    $verificar_producto = mysqli_query(
        $conexion,
        "SELECT $queryFields
         FROM producto
         WHERE codproducto = $id
         AND estado = 1"
    );
    $producto = $verificar_producto ? mysqli_fetch_assoc($verificar_producto) : null;

    if (!$producto) {
        ajax_json("producto_no_existe");
    }

    $precio = isset($_POST['precio']) && $_POST['precio'] !== ''
        ? (float) $_POST['precio']
        : mayorista_precio_producto($producto, $tipoVenta);

    $existencia = (int) $producto['existencia'];
    $verificar = mysqli_query($conexion, "SELECT * FROM detalle_temp WHERE id_producto = $id AND id_usuario = $id_user");
    $datos = $verificar ? mysqli_fetch_assoc($verificar) : null;

    if ($datos) {
        $nueva_cantidad = (int) $datos['cantidad'] + $cant;
        if ($existencia < $nueva_cantidad) {
            ajax_json("stock_insuficiente");
        }

        $precioActual = (float) $datos['precio_venta'];
        $total_precio = $nueva_cantidad * $precioActual;
        $query = mysqli_query(
            $conexion,
            "UPDATE detalle_temp
             SET cantidad = $nueva_cantidad, total = $total_precio
             WHERE id_producto = $id AND id_usuario = $id_user"
        );
        ajax_json($query ? "actualizado" : "error");
    }

    if ($existencia < $cant) {
        ajax_json("stock_insuficiente");
    }

    $total = $precio * $cant;
    $query = mysqli_query(
        $conexion,
        "INSERT INTO detalle_temp(id_usuario, id_producto, cantidad, precio_venta, total)
         VALUES ($id_user, $id, $cant, $precio, $total)"
    );

    if ($query) {
        ajax_limpiar_precio_detalle_editado(mysqli_insert_id($conexion));
    }

    ajax_json($query ? "registrado" : "error");
}

if (isset($_POST['procesarVenta'])) {
    $id_cliente = (int) $_POST['id'];
    $abona = round((float) $_POST['abona'], 2);
    $ventaToken = trim((string) ($_POST['venta_token'] ?? ''));
    $tipoVenta = mayorista_tipo_venta_valido($_POST['tipo_venta'] ?? 'minorista');
    $metodo_pago = (int) ($_POST['metodo_pago'] ?? 1);
    $modoDespacho = trim($_POST['modo_despacho'] ?? 'A convenir');
    $observacion = mysqli_real_escape_string($conexion, trim($_POST['observacion'] ?? ''));
    $fechaVentaInput = trim((string) ($_POST['fecha_venta'] ?? date('Y-m-d')));
    $chequePlazoDias = (int) ($_POST['cheque_plazo_dias'] ?? 30);
    $chequeFechaDeposito = trim((string) ($_POST['cheque_fecha_deposito'] ?? ''));
    if (!mayorista_fecha_iso_valida($fechaVentaInput)) {
        ajax_json(array('mensaje' => 'error', 'detalle' => 'La fecha de la venta no es válida.'));
    }
    if ($fechaVentaInput > date('Y-m-d')) {
        ajax_json(array('mensaje' => 'error', 'detalle' => 'La fecha de la venta no puede ser futura.'));
    }
    $fecha = mayorista_fecha_hora_desde_iso($fechaVentaInput);
    if (!in_array($modoDespacho, mayorista_modos_despacho(), true)) {
        $modoDespacho = 'A convenir';
    }
    $modoDespacho = mysqli_real_escape_string($conexion, $modoDespacho);

    if ($id_cliente <= 0) {
        ajax_json(array('mensaje' => 'error', 'detalle' => 'Selecciona un cliente válido.'));
    }

    if (!mayorista_validar_token_venta($ventaToken)) {
        ajax_json(array('mensaje' => 'error', 'detalle' => 'La venta ya fue procesada o la sesion expiro. Recarga la pagina antes de intentar nuevamente.'), 409);
    }

    if ($abona < 0) {
        ajax_json(array('mensaje' => 'error', 'detalle' => 'El importe abonado no puede ser negativo.'));
    }

    if ($metodo_pago === 5 && $abona > 0) {
        if (!mayorista_schema_finanzas_operativas_listo($conexion)) {
            ajax_json(array('mensaje' => 'error', 'detalle' => 'Primero tenés que aplicar la migración financiera desde configuración para usar cheques.'));
        }
        if (!in_array($chequePlazoDias, array(30, 60, 90, 120), true)) {
            $chequePlazoDias = 30;
        }
        if (!mayorista_fecha_iso_valida($chequeFechaDeposito)) {
            ajax_json(array('mensaje' => 'error', 'detalle' => 'La fecha esperada de depósito del cheque no es válida.'));
        }
    }

    $cliente = mysqli_query($conexion, "SELECT idcliente FROM cliente WHERE idcliente = $id_cliente AND estado = 1");
    if (!$cliente || mysqli_num_rows($cliente) === 0) {
        ajax_json(array('mensaje' => 'error', 'detalle' => 'El cliente seleccionado no existe.'));
    }

    $queryFields = ajax_get_producto_query($conexion);
    $detalle = mysqli_query(
        $conexion,
        "SELECT d.id, d.id_producto, d.cantidad, d.precio_venta, d.total, p.codigo, p.descripcion, $queryFields
         FROM detalle_temp d
         INNER JOIN producto p ON d.id_producto = p.codproducto
         WHERE d.id_usuario = $id_user
         ORDER BY d.id ASC"
    );

    if (!$detalle || mysqli_num_rows($detalle) === 0) {
        ajax_json(array('mensaje' => 'error', 'detalle' => 'No hay productos cargados en el pedido.'));
    }

    $items = array();
    $total = 0;
    $precioModificado = 0;

    while ($row = mysqli_fetch_assoc($detalle)) {
        $items[] = $row;
        $total += (float) $row['total'];
    }

    usort($items, function ($a, $b) {
        return ((int) $a['id_producto']) <=> ((int) $b['id_producto']);
    });

    $total = round($total, 2);
    if ($abona > $total) {
        ajax_json(array('mensaje' => 'error', 'detalle' => 'El importe abonado no puede superar el total de la venta.'));
    }

    $montoCc = round($total - $abona, 2);
    $saldoCcCliente = 0;

    mysqli_begin_transaction($conexion);

    try {
        $camposVenta = array('id_cliente', 'total', 'id_usuario', 'abona', 'resto', 'obrasocial', 'fecha', 'id_metodo');
        $valoresVenta = array($id_cliente, $total, $id_user, $abona, $montoCc, 0, "'$fecha'", $metodo_pago);

        if (mayorista_column_exists($conexion, 'ventas', 'tipo_venta')) {
            $camposVenta[] = 'tipo_venta';
            $valoresVenta[] = "'$tipoVenta'";
        }
        if (mayorista_column_exists($conexion, 'ventas', 'modo_despacho')) {
            $camposVenta[] = 'modo_despacho';
            $valoresVenta[] = "'$modoDespacho'";
        }
        if (mayorista_column_exists($conexion, 'ventas', 'precio_modificado')) {
            $camposVenta[] = 'precio_modificado';
            $valoresVenta[] = 0;
        }
        if (mayorista_column_exists($conexion, 'ventas', 'monto_cc')) {
            $camposVenta[] = 'monto_cc';
            $valoresVenta[] = $montoCc;
        }
        if (mayorista_column_exists($conexion, 'ventas', 'saldo_cc_cliente')) {
            $camposVenta[] = 'saldo_cc_cliente';
            $valoresVenta[] = 0;
        }

        $insertVenta = mysqli_query(
            $conexion,
            "INSERT INTO ventas(" . implode(', ', $camposVenta) . ")
             VALUES (" . implode(', ', $valoresVenta) . ")"
        );

        if (!$insertVenta) {
            throw new Exception('No se pudo guardar la venta: ' . mysqli_error($conexion));
        }

        $idVenta = mysqli_insert_id($conexion);

        if ($abona > 0 && $metodo_pago !== 5) {
            $insertIngreso = mysqli_query(
                $conexion,
                "INSERT INTO ingresos(ingresos, fecha, id_venta, id_cliente, id_metodo)
                 VALUES ($abona, '$fecha', '$idVenta', $id_cliente, $metodo_pago)"
            );
            if (!$insertIngreso) {
                throw new Exception('No se pudo registrar el ingreso inicial: ' . mysqli_error($conexion));
            }
        }

        foreach ($items as $item) {
            $idProducto = (int) $item['id_producto'];
            $cantidad = (int) $item['cantidad'];
            $precioAplicado = round((float) $item['precio_venta'], 2);
            $productoActualQuery = mysqli_query(
                $conexion,
                "SELECT $queryFields
                 FROM producto
                 WHERE codproducto = $idProducto AND estado = 1
                 LIMIT 1
                 FOR UPDATE"
            );
            $productoActual = $productoActualQuery ? mysqli_fetch_assoc($productoActualQuery) : null;
            if (!$productoActual) {
                throw new Exception('El producto ya no está disponible.');
            }

            $precioBase = round(mayorista_precio_producto($productoActual, $tipoVenta), 2);
            $precioPersonalizado = abs($precioAplicado - $precioBase) > 0.009 ? $precioAplicado : null;

            if ($precioPersonalizado !== null) {
                $precioModificado = 1;
            }

            $stockDisponible = (int) $productoActual['existencia'];
            if ($stockDisponible < $cantidad) {
                throw new Exception('Stock insuficiente para ' . ($productoActual['descripcion'] ?? $item['descripcion']));
            }

            $camposDetalle = array(
                'id_producto',
                'id_venta',
                'cantidad',
                'precio',
                'precio_original',
                'abona',
                'resto',
                'obrasocial'
            );
            $valoresDetalle = array(
                $idProducto,
                $idVenta,
                $cantidad,
                $precioAplicado,
                $precioBase,
                $abona,
                $montoCc,
                0
            );

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
                $valoresDetalle[] = "'$tipoVenta'";
            }

            $insertDetalle = mysqli_query(
                $conexion,
                "INSERT INTO detalle_venta(" . implode(', ', $camposDetalle) . ")
                 VALUES (" . implode(', ', $valoresDetalle) . ")"
            );

            if (!$insertDetalle) {
                throw new Exception('No se pudo guardar el detalle de venta: ' . mysqli_error($conexion));
            }

            $actualizarStock = mysqli_query(
                $conexion,
                "UPDATE producto
                 SET existencia = existencia - $cantidad,
                     estado = IF(existencia - $cantidad <= 0, 0, estado)
                 WHERE codproducto = $idProducto
                 AND existencia >= $cantidad"
            );

            if (!$actualizarStock || mysqli_affected_rows($conexion) !== 1) {
                throw new Exception('No se pudo actualizar el stock: ' . mysqli_error($conexion));
            }
        }

        if (mayorista_table_exists($conexion, 'postpagos')) {
            mysqli_query(
                $conexion,
                "INSERT INTO postpagos(id_venta, id_cliente, abona, resto, precio, precio_original)
                 VALUES ($idVenta, $id_cliente, $abona, $montoCc, $total, $total)"
            );
        }

        if ($abona > 0 && $metodo_pago === 5) {
            mayorista_registrar_compromiso_financiero($conexion, array(
                'tipo' => 'cheque_recibido',
                'id_cliente' => $id_cliente,
                'id_venta' => $idVenta,
                'id_metodo' => 5,
                'monto_total' => $abona,
                'saldo_pendiente' => $abona,
                'estado' => 'pendiente_confirmacion',
                'fecha_compromiso' => $fechaVentaInput,
                'fecha_vencimiento' => $chequeFechaDeposito,
                'fecha_deposito' => $chequeFechaDeposito,
                'descripcion' => 'Cheque recibido venta #' . $idVenta . ' (' . $chequePlazoDias . ' dias)',
                'observaciones' => $observacion,
                'id_usuario' => $id_user,
            ));
        }

        if ($montoCc > 0) {
            $validacionCc = mayorista_validar_nuevo_cargo_cc($conexion, $id_cliente, $montoCc);
            if (!$validacionCc['permitido']) {
                throw new Exception(
                    'El cargo en cuenta corriente supera el limite de credito del cliente. ' .
                    'Saldo actual: ' . mayorista_formatear_moneda($validacionCc['saldo_actual']) .
                    ' | Limite: ' . mayorista_formatear_moneda($validacionCc['limite_credito']) .
                    ' | Saldo proyectado: ' . mayorista_formatear_moneda($validacionCc['saldo_proyectado'])
                );
            }

            $saldoCcCliente = mayorista_registrar_movimiento_cc(
                $conexion,
                $id_cliente,
                'cargo',
                $montoCc,
                'Venta #' . $idVenta . ($observacion !== '' ? ' - ' . $observacion : ''),
                $id_user,
                $idVenta,
                $fecha
            );
        } else {
            $cuenta = mayorista_obtener_cuenta_corriente($conexion, $id_cliente);
            $saldoCcCliente = (float) $cuenta['saldo_actual'];
        }

        if (mayorista_column_exists($conexion, 'ventas', 'precio_modificado')
            || mayorista_column_exists($conexion, 'ventas', 'monto_cc')
            || mayorista_column_exists($conexion, 'ventas', 'saldo_cc_cliente')) {
            $updates = array();
            if (mayorista_column_exists($conexion, 'ventas', 'precio_modificado')) {
                $updates[] = "precio_modificado = $precioModificado";
            }
            if (mayorista_column_exists($conexion, 'ventas', 'monto_cc')) {
                $updates[] = "monto_cc = $montoCc";
            }
            if (mayorista_column_exists($conexion, 'ventas', 'saldo_cc_cliente')) {
                $updates[] = "saldo_cc_cliente = $saldoCcCliente";
            }
            if (!empty($updates)) {
                mysqli_query($conexion, "UPDATE ventas SET " . implode(', ', $updates) . " WHERE id = $idVenta");
            }
        }

        mayorista_invalidar_token_venta();
        ajax_limpiar_temporales($conexion, $id_user);
        mysqli_commit($conexion);

        ajax_json(array(
            'id_cliente' => $id_cliente,
            'id_venta' => $idVenta,
            'total' => $total,
            'abona' => $abona,
            'monto_cc' => $montoCc,
            'modo_despacho' => $modoDespacho,
            'saldo_cc_cliente' => $saldoCcCliente,
            'metodo_pago' => $metodo_pago,
        ));
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        ajax_json(array('mensaje' => 'error', 'detalle' => $e->getMessage()), 500);
    }
}

if (isset($_POST['cambio'])) {
    $actual = (string) ($_POST['actual'] ?? '');
    $nueva = (string) ($_POST['nueva'] ?? '');
    $verificar = mysqli_query($conexion, "SELECT clave FROM usuario WHERE idusuario = $id_user LIMIT 1");
    $usuario = $verificar ? mysqli_fetch_assoc($verificar) : null;
    $passwordCheck = mayorista_verificar_password($actual, $usuario['clave'] ?? '', $conexion);

    if (!$passwordCheck['valido']) {
        echo 'dif';
        exit();
    }

    echo mayorista_actualizar_password_usuario($conexion, $id_user, $nueva) ? 'ok' : 'error';
    exit();
}

if (isset($_POST['nuevo_cliente'])) {
    $nombre = mysqli_real_escape_string($conexion, trim($_POST['nombre_cliente'] ?? ''));
    $telefono = mysqli_real_escape_string($conexion, trim($_POST['telefono_cliente'] ?? ''));
    $direccion = mysqli_real_escape_string($conexion, trim($_POST['direccion_cliente'] ?? ''));
    $dni = mysqli_real_escape_string($conexion, trim($_POST['dni_cliente'] ?? ''));
    $cuit = mysqli_real_escape_string($conexion, trim($_POST['cuit_cliente'] ?? ''));
    $optica = mysqli_real_escape_string($conexion, trim($_POST['optica_cliente'] ?? ''));
    $localidad = mysqli_real_escape_string($conexion, trim($_POST['localidad_cliente'] ?? ''));
    $codigoPostal = mysqli_real_escape_string($conexion, trim($_POST['codigo_postal_cliente'] ?? ''));
    $provincia = mysqli_real_escape_string($conexion, trim($_POST['provincia_cliente'] ?? ''));
    $condicionIva = trim($_POST['condicion_iva_cliente'] ?? 'Consumidor Final');
    $tipoDocumento = (int) ($_POST['tipo_documento_cliente'] ?? 96);

    if (!in_array($condicionIva, array(
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
    ), true)) {
        $condicionIva = 'Consumidor Final';
    }
    if (!in_array($tipoDocumento, array(80, 96), true)) {
        $tipoDocumento = 96;
    }
    $condicionIva = mysqli_real_escape_string($conexion, $condicionIva);

    if ($nombre === '' || $telefono === '' || $direccion === '') {
        ajax_json(array('success' => false, 'mensaje' => 'Nombre, teléfono y dirección son obligatorios.'));
    }
    if (mayorista_column_exists($conexion, 'cliente', 'optica') && ($optica === '' || $localidad === '' || $codigoPostal === '' || $provincia === '')) {
        ajax_json(array('success' => false, 'mensaje' => 'Completá óptica, localidad, código postal y provincia.'));
    }
    if (mayorista_column_exists($conexion, 'cliente', 'tipo_documento') && $tipoDocumento === 80 && $cuit === '') {
        ajax_json(array('success' => false, 'mensaje' => 'Para tipo CUIT, el campo CUIT es obligatorio.'));
    }
    if (mayorista_column_exists($conexion, 'cliente', 'tipo_documento') && $tipoDocumento === 96 && $dni === '') {
        ajax_json(array('success' => false, 'mensaje' => 'Para tipo DNI, el campo DNI es obligatorio.'));
    }

    $insertColumns = array('nombre', 'telefono', 'direccion', 'usuario_id', 'dni', 'estado');
    $insertValues = array("'$nombre'", "'$telefono'", "'$direccion'", $id_user, "'$dni'", 1);
    if (mayorista_column_exists($conexion, 'cliente', 'optica')) {
        $insertColumns[] = 'optica';
        $insertValues[] = "'$optica'";
    }
    if (mayorista_column_exists($conexion, 'cliente', 'localidad')) {
        $insertColumns[] = 'localidad';
        $insertValues[] = "'$localidad'";
    }
    if (mayorista_column_exists($conexion, 'cliente', 'codigo_postal')) {
        $insertColumns[] = 'codigo_postal';
        $insertValues[] = "'$codigoPostal'";
    }
    if (mayorista_column_exists($conexion, 'cliente', 'provincia')) {
        $insertColumns[] = 'provincia';
        $insertValues[] = "'$provincia'";
    }
    if (mayorista_column_exists($conexion, 'cliente', 'cuit')) {
        $insertColumns[] = 'cuit';
        $insertValues[] = "'$cuit'";
    }
    if (mayorista_column_exists($conexion, 'cliente', 'condicion_iva')) {
        $insertColumns[] = 'condicion_iva';
        $insertValues[] = "'$condicionIva'";
    }
    if (mayorista_column_exists($conexion, 'cliente', 'tipo_documento')) {
        $insertColumns[] = 'tipo_documento';
        $insertValues[] = $tipoDocumento;
    }

    $query_insert = mysqli_query(
        $conexion,
        "INSERT INTO cliente(" . implode(', ', $insertColumns) . ")
         VALUES (" . implode(', ', $insertValues) . ")"
    );

    if (!$query_insert) {
        ajax_json(array('success' => false, 'mensaje' => 'No se pudo guardar el cliente.'));
    }

    $idCliente = mysqli_insert_id($conexion);
    mayorista_asegurar_cuenta_corriente($conexion, $idCliente);

    ajax_json(array(
        'success' => true,
        'mensaje' => 'Cliente creado correctamente.',
        'cliente' => array(
            'id' => $idCliente,
            'label' => $optica !== '' ? trim($optica . ' - ' . $nombre) : $nombre,
            'telefono' => $telefono,
            'direccion' => $direccion,
            'saldo_cc' => 0,
            'limite_credito' => 0,
        ),
    ));
}

ajax_json(array('mensaje' => 'Operacion no reconocida.'), 400);
