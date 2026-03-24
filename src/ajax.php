<?php
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";
session_start();

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'No autorizado'));
    exit();
}

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
    $fields = array('codproducto', 'codigo', 'descripcion', 'precio', 'existencia', 'estado');
    if (mayorista_column_exists($conexion, 'producto', 'precio_mayorista')) {
        $fields[] = 'precio_mayorista';
    }
    if (mayorista_column_exists($conexion, 'producto', 'tipo')) {
        $fields[] = 'tipo';
    }
    if (mayorista_column_exists($conexion, 'producto', 'costo')) {
        $fields[] = 'costo';
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
    $query = mysqli_query(
        $conexion,
        "SELECT * FROM cliente
         WHERE estado = 1
         AND (nombre LIKE '%$busqueda%' OR dni LIKE '%$busqueda%' OR telefono LIKE '%$busqueda%')
         ORDER BY nombre ASC
         LIMIT 20"
    );

    while ($row = mysqli_fetch_assoc($query)) {
        $cc = mayorista_obtener_cuenta_corriente($conexion, (int) $row['idcliente']);
        $datos[] = array(
            'id' => (int) $row['idcliente'],
            'label' => $row['nombre'],
            'dni' => $row['dni'],
            'direccion' => $row['direccion'],
            'telefono' => $row['telefono'],
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

    $producto = mysqli_query(
        $conexion,
        "SELECT $productoQuery
         FROM producto
         WHERE estado = 1
         AND existencia > 0
         AND (codigo LIKE '%$nombre%' OR descripcion LIKE '%$nombre%')
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
            'tipo' => $row['tipo'] ?? 'armazon',
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

    if (ajax_precio_detalle_editado($id_detalle)) {
        echo 'limite_edicion';
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
    $tipoVenta = mayorista_tipo_venta_valido($_POST['tipo_venta'] ?? 'minorista');
    $metodo_pago = (int) ($_POST['metodo_pago'] ?? 1);
    $observacion = mysqli_real_escape_string($conexion, trim($_POST['observacion'] ?? ''));
    $fecha = date('Y-m-d H:i:s');

    if ($id_cliente <= 0) {
        ajax_json(array('mensaje' => 'error', 'detalle' => 'Selecciona un cliente válido.'));
    }

    if ($abona < 0) {
        ajax_json(array('mensaje' => 'error', 'detalle' => 'El importe abonado no puede ser negativo.'));
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

        if ($abona > 0) {
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
            $precioBase = round(mayorista_precio_producto($item, $tipoVenta), 2);
            $precioPersonalizado = abs($precioAplicado - $precioBase) > 0.009 ? $precioAplicado : null;

            if ($precioPersonalizado !== null) {
                $precioModificado = 1;
            }

            $stockDisponible = (int) $item['existencia'];
            if ($stockDisponible < $cantidad) {
                throw new Exception('Stock insuficiente para ' . $item['descripcion']);
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

            $stockNuevo = $stockDisponible - $cantidad;
            $actualizarStock = mysqli_query(
                $conexion,
                "UPDATE producto
                 SET existencia = $stockNuevo, estado = IF($stockNuevo <= 0, 0, estado)
                 WHERE codproducto = $idProducto"
            );

            if (!$actualizarStock) {
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
                $idVenta
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

        ajax_limpiar_temporales($conexion, $id_user);
        mysqli_commit($conexion);

        ajax_json(array(
            'id_cliente' => $id_cliente,
            'id_venta' => $idVenta,
            'total' => $total,
            'abona' => $abona,
            'monto_cc' => $montoCc,
            'saldo_cc_cliente' => $saldoCcCliente,
        ));
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        ajax_json(array('mensaje' => 'error', 'detalle' => $e->getMessage()), 500);
    }
}

if (isset($_POST['cambio'])) {
    $actual = md5($_POST['actual']);
    $nueva = md5($_POST['nueva']);
    $verificar = mysqli_query($conexion, "SELECT * FROM usuario WHERE clave = '$actual' AND idusuario = $id_user");
    if (mysqli_num_rows($verificar) > 0) {
        $query = mysqli_query($conexion, "UPDATE usuario SET clave = '$nueva' WHERE idusuario = $id_user");
        echo $query ? 'ok' : 'error';
    } else {
        echo 'dif';
    }
    exit();
}

if (isset($_POST['nuevo_cliente'])) {
    $nombre = mysqli_real_escape_string($conexion, trim($_POST['nombre_cliente'] ?? ''));
    $telefono = mysqli_real_escape_string($conexion, trim($_POST['telefono_cliente'] ?? ''));
    $direccion = mysqli_real_escape_string($conexion, trim($_POST['direccion_cliente'] ?? ''));
    $dni = mysqli_real_escape_string($conexion, trim($_POST['dni_cliente'] ?? ''));

    if ($nombre === '' || $telefono === '' || $direccion === '') {
        ajax_json(array('success' => false, 'mensaje' => 'Nombre, teléfono y dirección son obligatorios.'));
    }

    $query_insert = mysqli_query(
        $conexion,
        "INSERT INTO cliente(nombre, telefono, direccion, usuario_id, dni, estado)
         VALUES ('$nombre', '$telefono', '$direccion', $id_user, '$dni', 1)"
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
            'label' => $nombre,
            'telefono' => $telefono,
            'direccion' => $direccion,
            'saldo_cc' => 0,
            'limite_credito' => 0,
        ),
    ));
}

ajax_json(array('mensaje' => 'Operacion no reconocida.'), 400);
