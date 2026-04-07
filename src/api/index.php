<?php
require_once '../../conexion.php';
require_once '../includes/mayorista_helpers.php';
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

function api_response($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function api_get_route()
{
    if (!empty($_SERVER['PATH_INFO'])) {
        return trim($_SERVER['PATH_INFO'], '/');
    }

    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $route = str_replace($script, '', $uri);
    return trim($route, '/');
}

function api_request_data()
{
    $input = file_get_contents('php://input');
    $json = json_decode($input, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST;
}

if (!($conexion instanceof mysqli)) {
    api_response(array('success' => false, 'message' => 'No se pudo conectar a la base de datos'), 500);
}
/** @var mysqli $conexion */

$apiKey = mayorista_get_api_key();
if ($apiKey === '') {
    api_response(array(
        'success' => false,
        'message' => 'API no configurada. Defini MAYORISTA_API_KEY antes de habilitarla.',
    ), 503);
}

$providedKey = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($providedKey === '' || !hash_equals($apiKey, $providedKey)) {
    api_response(array('success' => false, 'message' => 'API key invalida'), 401);
}

$route = api_get_route();
$method = $_SERVER['REQUEST_METHOD'];
$segments = $route === '' ? array() : explode('/', $route);

if (empty($segments)) {
    api_response(array(
        'success' => true,
        'message' => 'API mayorista activa',
        'routes' => array(
            'POST /clientes',
            'GET /clientes?q=nombre',
            'GET /clientes/{id}/cc',
            'POST /clientes/{id}/cc/pago',
            'GET /productos?q=nombre',
            'GET /estadisticas/resumen',
        ),
    ));
}

if ($segments[0] === 'clientes' && $method === 'POST' && count($segments) === 1) {
    $data = api_request_data();
    $nombre = mysqli_real_escape_string($conexion, trim($data['nombre'] ?? ''));
    $telefono = mysqli_real_escape_string($conexion, trim($data['telefono'] ?? ''));
    $direccion = mysqli_real_escape_string($conexion, trim($data['direccion'] ?? ''));
    $dni = mysqli_real_escape_string($conexion, trim($data['dni'] ?? ''));

    if ($nombre === '' || $telefono === '' || $direccion === '') {
        api_response(array('success' => false, 'message' => 'nombre, telefono y direccion son obligatorios'), 422);
    }

    $insert = mysqli_query(
        $conexion,
        "INSERT INTO cliente(nombre, telefono, direccion, usuario_id, dni, estado)
         VALUES ('$nombre', '$telefono', '$direccion', 1, '$dni', 1)"
    );

    if (!$insert) {
        api_response(array('success' => false, 'message' => 'No se pudo crear el cliente'), 500);
    }

    $idCliente = mysqli_insert_id($conexion);
    mayorista_asegurar_cuenta_corriente($conexion, $idCliente);

    api_response(array(
        'success' => true,
        'cliente' => array(
            'id' => $idCliente,
            'nombre' => $nombre,
            'telefono' => $telefono,
            'direccion' => $direccion,
            'dni' => $dni,
        ),
    ), 201);
}

if ($segments[0] === 'clientes' && $method === 'GET' && count($segments) === 1) {
    $q = mysqli_real_escape_string($conexion, trim($_GET['q'] ?? ''));
    $result = mysqli_query(
        $conexion,
        "SELECT idcliente, nombre, telefono, direccion, dni
         FROM cliente
         WHERE estado = 1
         AND (nombre LIKE '%$q%' OR dni LIKE '%$q%' OR telefono LIKE '%$q%')
         ORDER BY nombre ASC
         LIMIT 20"
    );

    $clientes = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $clientes[] = $row;
    }

    api_response(array('success' => true, 'clientes' => $clientes));
}

if ($segments[0] === 'clientes' && count($segments) >= 3 && (int) $segments[1] > 0) {
    $idCliente = (int) $segments[1];

    if ($segments[2] === 'cc' && $method === 'GET' && count($segments) === 3) {
        $cuenta = mayorista_obtener_cuenta_corriente($conexion, $idCliente);
        api_response(array(
            'success' => true,
            'cuenta_corriente' => array(
                'id' => $cuenta['id'],
                'saldo_actual' => (float) $cuenta['saldo_actual'],
                'limite_credito' => (float) $cuenta['limite_credito'],
                'activo' => (int) $cuenta['activo'],
            ),
        ));
    }

    if ($segments[2] === 'cc' && isset($segments[3]) && $segments[3] === 'pago' && $method === 'POST') {
        $data = api_request_data();
        $monto = round((float) ($data['monto'] ?? 0), 2);
        $descripcion = trim($data['descripcion'] ?? 'Pago API');
        $metodo = (int) ($data['metodo_pago'] ?? 4);

        if ($monto <= 0) {
            api_response(array('success' => false, 'message' => 'El monto debe ser mayor a cero'), 422);
        }

        mysqli_begin_transaction($conexion);
        $saldo = mayorista_registrar_movimiento_cc($conexion, $idCliente, 'pago', $monto, $descripcion, 1, null);
        $ref = 'API-CC-' . $idCliente . '-' . time();
        mysqli_query(
            $conexion,
            "INSERT INTO ingresos(ingresos, fecha, id_venta, id_cliente, id_metodo)
             VALUES ($monto, NOW(), '$ref', $idCliente, $metodo)"
        );
        mysqli_commit($conexion);

        api_response(array(
            'success' => true,
            'message' => 'Pago registrado',
            'saldo_actual' => (float) $saldo,
        ), 201);
    }
}

if ($segments[0] === 'productos' && $method === 'GET') {
    $q = mysqli_real_escape_string($conexion, trim($_GET['q'] ?? ''));
    $result = mysqli_query(
        $conexion,
        "SELECT codproducto, codigo, descripcion, precio, existencia, marca" .
        (mayorista_column_exists($conexion, 'producto', 'precio_mayorista') ? ", precio_mayorista" : "") .
        (mayorista_column_exists($conexion, 'producto', 'modelo') ? ", modelo" : "") .
        (mayorista_column_exists($conexion, 'producto', 'color') ? ", color" : "") .
        (mayorista_column_exists($conexion, 'producto', 'tipo_material') ? ", tipo_material" : "") .
        (mayorista_column_exists($conexion, 'producto', 'tipo') ? ", tipo" : "") .
        " FROM producto
         WHERE estado = 1
         AND (
            codigo LIKE '%$q%'
            OR marca LIKE '%$q%'" .
            (mayorista_column_exists($conexion, 'producto', 'modelo') ? " OR modelo LIKE '%$q%'" : "") .
            (mayorista_column_exists($conexion, 'producto', 'color') ? " OR color LIKE '%$q%'" : "") .
            (mayorista_column_exists($conexion, 'producto', 'tipo_material') ? " OR tipo_material LIKE '%$q%'" : "") .
            (mayorista_column_exists($conexion, 'producto', 'tipo') ? " OR tipo LIKE '%$q%'" : "") .
         ")
         ORDER BY marca ASC, codigo ASC
         LIMIT 20"
    );

    $productos = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $productos[] = array(
            'id' => (int) $row['codproducto'],
            'codigo' => $row['codigo'],
            'nombre' => mayorista_nombre_producto($row),
            'precio_minorista' => (float) $row['precio'],
            'precio_mayorista' => isset($row['precio_mayorista']) ? (float) $row['precio_mayorista'] : (float) $row['precio'],
            'stock' => (int) $row['existencia'],
            'marca' => $row['marca'] ?? '',
            'modelo' => $row['modelo'] ?? '',
            'color' => $row['color'] ?? '',
            'tipo_material' => $row['tipo_material'] ?? '',
            'tipo' => $row['tipo'] ?? 'receta',
        );
    }

    api_response(array('success' => true, 'productos' => $productos));
}

if ($segments[0] === 'estadisticas' && isset($segments[1]) && $segments[1] === 'resumen' && $method === 'GET') {
    $hoy = date('Y-m-d');
    $ventas = mysqli_fetch_assoc(mysqli_query(
        $conexion,
        "SELECT COUNT(*) AS operaciones, IFNULL(SUM(total),0) AS monto
         FROM ventas
         WHERE DATE(fecha) = '$hoy'"
    ));
    $cc = mayorista_table_exists($conexion, 'cuenta_corriente')
        ? mysqli_fetch_assoc(mysqli_query($conexion, "SELECT IFNULL(SUM(saldo_actual),0) AS pendiente FROM cuenta_corriente WHERE saldo_actual > 0"))
        : array('pendiente' => 0);

    api_response(array(
        'success' => true,
        'resumen' => array(
            'fecha' => $hoy,
            'ventas_operaciones' => (int) $ventas['operaciones'],
            'ventas_monto' => (float) $ventas['monto'],
            'cuenta_corriente_pendiente' => (float) $cc['pendiente'],
        ),
    ));
}

api_response(array('success' => false, 'message' => 'Endpoint no encontrado'), 404);
