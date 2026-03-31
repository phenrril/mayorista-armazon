<?php

function mayorista_column_exists($conexion, $table, $column)
{
    $table = mysqli_real_escape_string($conexion, $table);
    $column = mysqli_real_escape_string($conexion, $column);
    $result = mysqli_query($conexion, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function mayorista_table_exists($conexion, $table)
{
    $table = mysqli_real_escape_string($conexion, $table);
    $result = mysqli_query($conexion, "SHOW TABLES LIKE '$table'");
    return $result && mysqli_num_rows($result) > 0;
}

function mayorista_tiene_permiso($conexion, $idUsuario, $permisos)
{
    $idUsuario = (int) $idUsuario;
    if ($idUsuario === 1) {
        return true;
    }

    $permisos = (array) $permisos;
    $permisos = array_values(array_filter(array_map('trim', $permisos)));
    if (empty($permisos)) {
        return false;
    }

    $quoted = array();
    foreach ($permisos as $permiso) {
        $quoted[] = "'" . mysqli_real_escape_string($conexion, $permiso) . "'";
    }

    $sql = "SELECT 1
        FROM permisos p
        INNER JOIN detalle_permisos d ON p.id = d.id_permiso
        WHERE d.id_usuario = $idUsuario
        AND p.nombre IN (" . implode(',', $quoted) . ")
        LIMIT 1";

    $result = mysqli_query($conexion, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

function mayorista_es_admin($idUsuario)
{
    return (int) $idUsuario === 1;
}

function mayorista_generar_token_reset_sistema()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['reset_sistema_token']) || !is_string($_SESSION['reset_sistema_token'])) {
        $_SESSION['reset_sistema_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['reset_sistema_token'];
}

function mayorista_validar_token_reset_sistema($token)
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['reset_sistema_token'])) {
        return false;
    }

    return hash_equals($_SESSION['reset_sistema_token'], (string) $token);
}

function mayorista_invalidar_token_reset_sistema()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['reset_sistema_token']);
    }
}

function mayorista_asegurar_tabla_flags($conexion)
{
    if (mayorista_table_exists($conexion, 'sistema_flags')) {
        return true;
    }

    $sql = "CREATE TABLE IF NOT EXISTS sistema_flags (
        clave VARCHAR(100) NOT NULL,
        valor VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (clave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    return mysqli_query($conexion, $sql) !== false;
}

function mayorista_reset_sistema_fue_ejecutado($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $query = mysqli_query(
        $conexion,
        "SELECT valor
         FROM sistema_flags
         WHERE clave = 'reset_sistema_ejecutado'
         LIMIT 1"
    );

    if (!$query || mysqli_num_rows($query) === 0) {
        return false;
    }

    $row = mysqli_fetch_assoc($query);
    return isset($row['valor']) && $row['valor'] === '1';
}

function mayorista_marcar_reset_sistema_ejecutado($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $sql = "INSERT INTO sistema_flags (clave, valor)
        VALUES ('reset_sistema_ejecutado', '1')
        ON DUPLICATE KEY UPDATE
            valor = VALUES(valor),
            updated_at = CURRENT_TIMESTAMP";

    return mysqli_query($conexion, $sql) !== false;
}

function mayorista_requiere_permiso($conexion, $idUsuario, $permisos)
{
    if (!mayorista_tiene_permiso($conexion, $idUsuario, $permisos)) {
        header("Location: permisos.php");
        exit();
    }
}

function mayorista_tipo_venta_valido($tipoVenta)
{
    return $tipoVenta === 'mayorista' ? 'mayorista' : 'minorista';
}

function mayorista_precio_producto($producto, $tipoVenta)
{
    $tipoVenta = mayorista_tipo_venta_valido($tipoVenta);
    $precioMinorista = isset($producto['precio']) ? (float) $producto['precio'] : 0;
    $precioMayorista = isset($producto['precio_mayorista']) ? (float) $producto['precio_mayorista'] : 0;

    if ($tipoVenta === 'mayorista' && $precioMayorista > 0) {
        return $precioMayorista;
    }

    return $precioMinorista;
}

function mayorista_formatear_moneda($monto)
{
    return '$' . number_format((float) $monto, 2, ',', '.');
}

function mayorista_asegurar_cuenta_corriente($conexion, $idCliente)
{
    $idCliente = (int) $idCliente;
    if ($idCliente <= 0 || !mayorista_table_exists($conexion, 'cuenta_corriente')) {
        return null;
    }

    $query = mysqli_query($conexion, "SELECT * FROM cuenta_corriente WHERE id_cliente = $idCliente LIMIT 1");
    if ($query && mysqli_num_rows($query) > 0) {
        return mysqli_fetch_assoc($query);
    }

    $insert = mysqli_query(
        $conexion,
        "INSERT INTO cuenta_corriente (id_cliente, limite_credito, saldo_actual, fecha_creacion, activo)
         VALUES ($idCliente, 0, 0, NOW(), 1)"
    );

    if (!$insert) {
        return null;
    }

    return array(
        'id' => mysqli_insert_id($conexion),
        'id_cliente' => $idCliente,
        'limite_credito' => 0,
        'saldo_actual' => 0,
        'activo' => 1,
    );
}

function mayorista_actualizar_saldo_cc($conexion, $idCuentaCorriente)
{
    $idCuentaCorriente = (int) $idCuentaCorriente;
    if ($idCuentaCorriente <= 0 || !mayorista_table_exists($conexion, 'movimientos_cc')) {
        return 0;
    }

    $query = mysqli_query(
        $conexion,
        "SELECT
            SUM(CASE WHEN tipo = 'cargo' THEN monto ELSE 0 END) AS cargos,
            SUM(CASE WHEN tipo = 'pago' THEN monto ELSE 0 END) AS pagos
         FROM movimientos_cc
         WHERE id_cuenta_corriente = $idCuentaCorriente"
    );

    $data = $query ? mysqli_fetch_assoc($query) : array('cargos' => 0, 'pagos' => 0);
    $saldo = (float) ($data['cargos'] ?? 0) - (float) ($data['pagos'] ?? 0);

    mysqli_query(
        $conexion,
        "UPDATE cuenta_corriente
         SET saldo_actual = $saldo
         WHERE id = $idCuentaCorriente"
    );

    return $saldo;
}

function mayorista_obtener_cuenta_corriente($conexion, $idCliente)
{
    $cuenta = mayorista_asegurar_cuenta_corriente($conexion, $idCliente);
    if (!$cuenta) {
        return array(
            'id' => null,
            'id_cliente' => (int) $idCliente,
            'limite_credito' => 0,
            'saldo_actual' => 0,
            'activo' => 0,
        );
    }

    $saldo = mayorista_actualizar_saldo_cc($conexion, $cuenta['id']);
    $cuenta['saldo_actual'] = $saldo;
    return $cuenta;
}

function mayorista_validar_nuevo_cargo_cc($conexion, $idCliente, $montoCargo)
{
    $montoCargo = round((float) $montoCargo, 2);
    if ($montoCargo <= 0 || !mayorista_table_exists($conexion, 'cuenta_corriente')) {
        return array(
            'permitido' => true,
            'limite_configurado' => false,
            'saldo_actual' => 0,
            'saldo_proyectado' => 0,
            'limite_credito' => 0,
        );
    }

    $cuenta = mayorista_obtener_cuenta_corriente($conexion, $idCliente);
    $saldoActual = (float) ($cuenta['saldo_actual'] ?? 0);
    $limiteCredito = (float) ($cuenta['limite_credito'] ?? 0);
    $saldoProyectado = $saldoActual + $montoCargo;
    $limiteConfigurado = $limiteCredito > 0;

    return array(
        'permitido' => !$limiteConfigurado || $saldoProyectado <= ($limiteCredito + 0.009),
        'limite_configurado' => $limiteConfigurado,
        'saldo_actual' => $saldoActual,
        'saldo_proyectado' => $saldoProyectado,
        'limite_credito' => $limiteCredito,
    );
}

function mayorista_registrar_movimiento_cc($conexion, $idCliente, $tipo, $monto, $descripcion, $idUsuario, $idVenta = null)
{
    if (!mayorista_table_exists($conexion, 'cuenta_corriente') || !mayorista_table_exists($conexion, 'movimientos_cc')) {
        return 0;
    }

    $idCliente = (int) $idCliente;
    $idUsuario = (int) $idUsuario;
    $idVenta = $idVenta !== null ? (int) $idVenta : 'NULL';
    $monto = (float) $monto;
    $tipo = $tipo === 'pago' ? 'pago' : 'cargo';
    $descripcion = mysqli_real_escape_string($conexion, $descripcion);

    if ($idCliente <= 0 || $monto <= 0) {
        return 0;
    }

    $cuenta = mayorista_asegurar_cuenta_corriente($conexion, $idCliente);
    if (!$cuenta) {
        return 0;
    }

    $idCuenta = (int) $cuenta['id'];
    $ventaValue = $idVenta === 'NULL' ? 'NULL' : $idVenta;

    $insert = mysqli_query(
        $conexion,
        "INSERT INTO movimientos_cc (
            id_cuenta_corriente, id_venta, tipo, monto, descripcion, id_usuario, fecha
         ) VALUES (
            $idCuenta, $ventaValue, '$tipo', $monto, '$descripcion', $idUsuario, NOW()
         )"
    );

    if (!$insert) {
        return 0;
    }

    return mayorista_actualizar_saldo_cc($conexion, $idCuenta);
}

function mayorista_venta_tiene_precio_modificado($conexion, $idVenta)
{
    $idVenta = (int) $idVenta;
    if ($idVenta <= 0 || !mayorista_column_exists($conexion, 'detalle_venta', 'precio_personalizado')) {
        return false;
    }

    $query = mysqli_query(
        $conexion,
        "SELECT 1
         FROM detalle_venta
         WHERE id_venta = $idVenta
         AND precio_personalizado IS NOT NULL
         LIMIT 1"
    );

    return $query && mysqli_num_rows($query) > 0;
}

