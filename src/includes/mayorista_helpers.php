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

/**
 * Indica si debe mostrarse un enlace del menú lateral para el usuario actual.
 * Requiere sesión válida; sin conexión a BD no se filtra (compat. con includes mínimos).
 */
function mayorista_nav_link_visible($conexion, $idUsuario, $permisos)
{
    $idUsuario = (int) $idUsuario;
    if ($idUsuario <= 0) {
        return false;
    }
    if (!($conexion instanceof mysqli) || !function_exists('mayorista_tiene_permiso')) {
        return true;
    }
    return mayorista_tiene_permiso($conexion, $idUsuario, (array) $permisos);
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

function mayorista_usuario_clave_max_length($conexion)
{
    if (!($conexion instanceof mysqli)) {
        return 0;
    }

    $result = mysqli_query($conexion, "SHOW COLUMNS FROM `usuario` LIKE 'clave'");
    $column = $result ? mysqli_fetch_assoc($result) : null;
    if (!$column || empty($column['Type'])) {
        return 0;
    }

    if (preg_match('/\((\d+)\)/', (string) $column['Type'], $matches)) {
        return (int) $matches[1];
    }

    return 0;
}

function mayorista_password_hash_moderno_disponible($conexion)
{
    return mayorista_usuario_clave_max_length($conexion) >= 60;
}

function mayorista_hash_password($plainPassword, $conexion = null)
{
    $plainPassword = (string) $plainPassword;
    if ($conexion instanceof mysqli && !mayorista_password_hash_moderno_disponible($conexion)) {
        return md5($plainPassword);
    }

    return password_hash($plainPassword, PASSWORD_DEFAULT);
}

function mayorista_verificar_password($plainPassword, $storedHash, $conexion = null)
{
    $plainPassword = (string) $plainPassword;
    $storedHash = trim((string) $storedHash);
    if ($storedHash === '') {
        return array('valido' => false, 'rehash' => false);
    }

    $hashInfo = password_get_info($storedHash);
    if (!empty($hashInfo['algo'])) {
        $valido = password_verify($plainPassword, $storedHash);
        return array(
            'valido' => $valido,
            'rehash' => $valido
                && $conexion instanceof mysqli
                && mayorista_password_hash_moderno_disponible($conexion)
                && password_needs_rehash($storedHash, PASSWORD_DEFAULT),
        );
    }

    $legacyHash = md5($plainPassword);
    $valido = hash_equals(strtolower($storedHash), $legacyHash);
    return array(
        'valido' => $valido,
        'rehash' => $valido && $conexion instanceof mysqli && mayorista_password_hash_moderno_disponible($conexion),
    );
}

function mayorista_actualizar_password_usuario($conexion, $idUsuario, $plainPassword)
{
    if (!($conexion instanceof mysqli)) {
        return false;
    }

    $idUsuario = (int) $idUsuario;
    if ($idUsuario <= 0) {
        return false;
    }

    $hash = mysqli_real_escape_string($conexion, mayorista_hash_password($plainPassword, $conexion));
    return mysqli_query($conexion, "UPDATE usuario SET clave = '$hash' WHERE idusuario = $idUsuario") !== false;
}

function mayorista_generar_token_venta()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['venta_token']) || !is_string($_SESSION['venta_token'])) {
        $_SESSION['venta_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['venta_token'];
}

function mayorista_validar_token_venta($token)
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['venta_token'])) {
        return false;
    }

    return hash_equals($_SESSION['venta_token'], (string) $token);
}

function mayorista_invalidar_token_venta()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['venta_token']);
    }
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

function mayorista_generar_token_migracion_remito()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['migracion_remito_token']) || !is_string($_SESSION['migracion_remito_token'])) {
        $_SESSION['migracion_remito_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['migracion_remito_token'];
}

function mayorista_validar_token_migracion_remito($token)
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['migracion_remito_token'])) {
        return false;
    }

    return hash_equals($_SESSION['migracion_remito_token'], (string) $token);
}

function mayorista_invalidar_token_migracion_remito()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['migracion_remito_token']);
    }
}

function mayorista_generar_token_migracion_finanzas()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['migracion_finanzas_token']) || !is_string($_SESSION['migracion_finanzas_token'])) {
        $_SESSION['migracion_finanzas_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['migracion_finanzas_token'];
}

function mayorista_validar_token_migracion_finanzas($token)
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['migracion_finanzas_token'])) {
        return false;
    }

    return hash_equals($_SESSION['migracion_finanzas_token'], (string) $token);
}

function mayorista_invalidar_token_migracion_finanzas()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['migracion_finanzas_token']);
    }
}

function mayorista_generar_token_migracion_vencimientos_venta()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['migracion_vencimientos_venta_token']) || !is_string($_SESSION['migracion_vencimientos_venta_token'])) {
        $_SESSION['migracion_vencimientos_venta_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['migracion_vencimientos_venta_token'];
}

function mayorista_validar_token_migracion_vencimientos_venta($token)
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['migracion_vencimientos_venta_token'])) {
        return false;
    }

    return hash_equals($_SESSION['migracion_vencimientos_venta_token'], (string) $token);
}

function mayorista_invalidar_token_migracion_vencimientos_venta()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['migracion_vencimientos_venta_token']);
    }
}

function mayorista_generar_token_importacion_productos()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['importacion_productos_token']) || !is_string($_SESSION['importacion_productos_token'])) {
        $_SESSION['importacion_productos_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['importacion_productos_token'];
}

function mayorista_validar_token_importacion_productos($token)
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['importacion_productos_token'])) {
        return false;
    }

    return hash_equals($_SESSION['importacion_productos_token'], (string) $token);
}

function mayorista_invalidar_token_importacion_productos()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['importacion_productos_token']);
    }
}

function mayorista_generar_token_importacion_clientes()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['importacion_clientes_token']) || !is_string($_SESSION['importacion_clientes_token'])) {
        $_SESSION['importacion_clientes_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['importacion_clientes_token'];
}

function mayorista_validar_token_importacion_clientes($token)
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['importacion_clientes_token'])) {
        return false;
    }

    return hash_equals($_SESSION['importacion_clientes_token'], (string) $token);
}

function mayorista_invalidar_token_importacion_clientes()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['importacion_clientes_token']);
    }
}

function mayorista_generar_token_reset_cc_masivo()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['reset_cc_masivo_token']) || !is_string($_SESSION['reset_cc_masivo_token'])) {
        $_SESSION['reset_cc_masivo_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['reset_cc_masivo_token'];
}

function mayorista_validar_token_reset_cc_masivo($token)
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['reset_cc_masivo_token'])) {
        return false;
    }

    return hash_equals($_SESSION['reset_cc_masivo_token'], (string) $token);
}

function mayorista_invalidar_token_reset_cc_masivo()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['reset_cc_masivo_token']);
    }
}

function mayorista_tipos_material_producto()
{
    return array('Acetato', 'Tr90', 'Metal', 'Inyeccion');
}

function mayorista_tipos_producto()
{
    return array('receta', 'sol', 'clip-on');
}

function mayorista_formatear_tipo_producto($tipo)
{
    $tipo = trim((string) $tipo);
    if ($tipo === '') {
        return '';
    }

    return ucfirst(str_replace('-', ' ', $tipo));
}

function mayorista_nombre_producto($producto, $incluirCodigo = false)
{
    $codigo = trim((string) ($producto['codigo'] ?? ''));
    $marca = trim((string) ($producto['marca'] ?? ''));
    $modelo = trim((string) ($producto['modelo'] ?? ''));
    $color = trim((string) ($producto['color'] ?? ''));
    $tipo = mayorista_formatear_tipo_producto($producto['tipo'] ?? '');

    $partes = array();
    foreach (array($marca, $modelo, $color, $tipo) as $parte) {
        if ($parte === '') {
            continue;
        }
        if (!in_array($parte, $partes, true)) {
            $partes[] = $parte;
        }
    }

    $nombre = implode(' ', $partes);
    if ($nombre === '') {
        $nombre = trim((string) ($producto['descripcion'] ?? ''));
    }
    if ($nombre === '') {
        $nombre = $codigo !== '' ? $codigo : 'Producto';
    }

    if ($incluirCodigo && $codigo !== '' && stripos($nombre, $codigo) !== 0) {
        return $codigo . ' - ' . $nombre;
    }

    return $nombre;
}

function mayorista_modos_despacho()
{
    return array(
        'Andreani',
        'Via cargo',
        'Credifin',
        'Correo argentino',
        'Oca',
        'Buspack',
        'Send box',
        'A convenir',
    );
}

function mayorista_obtener_tipo_columna($conexion, $table, $column)
{
    $table = mysqli_real_escape_string($conexion, $table);
    $column = mysqli_real_escape_string($conexion, $column);
    $result = mysqli_query($conexion, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$result || mysqli_num_rows($result) === 0) {
        return '';
    }

    $row = mysqli_fetch_assoc($result);
    return strtolower((string) ($row['Type'] ?? ''));
}

function mayorista_schema_remito_productos_listo($conexion)
{
    $clienteListo =
        mayorista_column_exists($conexion, 'cliente', 'optica')
        && mayorista_column_exists($conexion, 'cliente', 'localidad')
        && mayorista_column_exists($conexion, 'cliente', 'codigo_postal')
        && mayorista_column_exists($conexion, 'cliente', 'provincia')
        && mayorista_column_exists($conexion, 'cliente', 'cuit')
        && mayorista_column_exists($conexion, 'cliente', 'condicion_iva')
        && mayorista_column_exists($conexion, 'cliente', 'tipo_documento');

    $productoListo =
        mayorista_column_exists($conexion, 'producto', 'marca')
        && mayorista_column_exists($conexion, 'producto', 'modelo')
        && mayorista_column_exists($conexion, 'producto', 'color')
        && mayorista_column_exists($conexion, 'producto', 'tipo_material');

    $tipoColumna = mayorista_obtener_tipo_columna($conexion, 'producto', 'tipo');
    $tipoProductoListo =
        strpos($tipoColumna, 'receta') !== false
        && strpos($tipoColumna, 'clip-on') !== false
        && strpos($tipoColumna, 'sol') !== false;

    $ventasListo = mayorista_column_exists($conexion, 'ventas', 'modo_despacho');

    return $clienteListo && $productoListo && $tipoProductoListo && $ventasListo;
}

function mayorista_migracion_remito_productos_fue_ejecutada($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $query = mysqli_query(
        $conexion,
        "SELECT valor
         FROM sistema_flags
         WHERE clave = 'migracion_remito_productos_2026'
         LIMIT 1"
    );

    if (!$query || mysqli_num_rows($query) === 0) {
        return false;
    }

    $row = mysqli_fetch_assoc($query);
    return isset($row['valor']) && $row['valor'] === '1';
}

function mayorista_marcar_migracion_remito_productos_ejecutada($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $sql = "INSERT INTO sistema_flags (clave, valor)
        VALUES ('migracion_remito_productos_2026', '1')
        ON DUPLICATE KEY UPDATE
            valor = VALUES(valor),
            updated_at = CURRENT_TIMESTAMP";

    return mysqli_query($conexion, $sql) !== false;
}

function mayorista_schema_finanzas_operativas_listo($conexion)
{
    return mayorista_table_exists($conexion, 'proveedores')
        && mayorista_table_exists($conexion, 'compromisos_financieros')
        && mayorista_table_exists($conexion, 'compromisos_financieros_pagos')
        && mayorista_column_exists($conexion, 'ingresos', 'descripcion')
        && mayorista_column_exists($conexion, 'egresos', 'descripcion');
}

function mayorista_schema_vencimientos_venta_listo($conexion)
{
    return mayorista_table_exists($conexion, 'venta_vencimientos')
        && mayorista_column_exists($conexion, 'venta_vencimientos', 'id_venta')
        && mayorista_column_exists($conexion, 'venta_vencimientos', 'fecha_vencimiento')
        && mayorista_column_exists($conexion, 'venta_vencimientos', 'monto')
        && mayorista_column_exists($conexion, 'venta_vencimientos', 'nota_interna')
        && mayorista_column_exists($conexion, 'venta_vencimientos', 'estado')
        && mayorista_column_exists($conexion, 'venta_vencimientos', 'fecha_ultimo_recordatorio')
        && mayorista_column_exists($conexion, 'venta_vencimientos', 'id_usuario');
}

function mayorista_estado_vencimiento_venta_valido($estado)
{
    return in_array((string) $estado, array('pendiente', 'cumplido', 'cancelado'), true);
}

function mayorista_obtener_vencimientos_venta($conexion, $idVenta)
{
    $idVenta = (int) $idVenta;
    if ($idVenta <= 0 || !mayorista_schema_vencimientos_venta_listo($conexion)) {
        return array();
    }

    $query = mysqli_query(
        $conexion,
        "SELECT *
         FROM venta_vencimientos
         WHERE id_venta = $idVenta
         ORDER BY fecha_vencimiento ASC, id ASC"
    );

    if (!$query) {
        return array();
    }

    $items = array();
    while ($row = mysqli_fetch_assoc($query)) {
        $items[] = $row;
    }

    return $items;
}

function mayorista_guardar_vencimientos_venta($conexion, $idVenta, array $vencimientos, $idUsuario)
{
    $idVenta = (int) $idVenta;
    $idUsuario = (int) $idUsuario;
    if ($idVenta <= 0 || $idUsuario <= 0) {
        throw new InvalidArgumentException('No se pudo asociar los vencimientos a la venta.');
    }
    if (!mayorista_schema_vencimientos_venta_listo($conexion)) {
        throw new RuntimeException('Primero aplicá la migración de vencimientos internos desde configuración.');
    }

    $existentes = array();
    $queryExistentes = mysqli_query(
        $conexion,
        "SELECT id
         FROM venta_vencimientos
         WHERE id_venta = $idVenta"
    );
    if ($queryExistentes) {
        while ($row = mysqli_fetch_assoc($queryExistentes)) {
            $existentes[(int) $row['id']] = true;
        }
    }

    $idsConservados = array();
    foreach ($vencimientos as $vencimiento) {
        $idVencimiento = (int) ($vencimiento['id'] ?? 0);
        $fechaVencimiento = trim((string) ($vencimiento['fecha_vencimiento'] ?? ''));
        $notaInterna = mysqli_real_escape_string($conexion, trim((string) ($vencimiento['nota_interna'] ?? '')));
        $estado = trim((string) ($vencimiento['estado'] ?? 'pendiente'));
        $montoRaw = trim((string) ($vencimiento['monto'] ?? ''));

        if ($fechaVencimiento === '') {
            throw new InvalidArgumentException('Cada vencimiento interno debe tener una fecha.');
        }
        if (!mayorista_fecha_iso_valida($fechaVencimiento)) {
            throw new InvalidArgumentException('Una de las fechas de vencimiento internas no es válida.');
        }
        if (!mayorista_estado_vencimiento_venta_valido($estado)) {
            $estado = 'pendiente';
        }

        $montoSql = 'NULL';
        if ($montoRaw !== '') {
            if (!is_numeric($montoRaw)) {
                throw new InvalidArgumentException('Uno de los montos de vencimiento no es válido.');
            }
            $monto = round((float) $montoRaw, 2);
            if ($monto < 0) {
                throw new InvalidArgumentException('El monto de un vencimiento no puede ser negativo.');
            }
            $montoSql = (string) $monto;
        }

        if ($idVencimiento > 0 && isset($existentes[$idVencimiento])) {
            $updateOk = mysqli_query(
                $conexion,
                "UPDATE venta_vencimientos
                 SET fecha_vencimiento = '" . mysqli_real_escape_string($conexion, $fechaVencimiento) . "',
                     monto = $montoSql,
                     nota_interna = '$notaInterna',
                     estado = '" . mysqli_real_escape_string($conexion, $estado) . "',
                     fecha_ultimo_recordatorio = IF(
                        fecha_vencimiento <> '" . mysqli_real_escape_string($conexion, $fechaVencimiento) . "'
                        OR estado <> '" . mysqli_real_escape_string($conexion, $estado) . "',
                        NULL,
                        fecha_ultimo_recordatorio
                     ),
                     updated_at = NOW()
                 WHERE id = $idVencimiento
                 AND id_venta = $idVenta"
            );
            if (!$updateOk) {
                throw new RuntimeException('No se pudo actualizar un vencimiento interno: ' . mysqli_error($conexion));
            }
            $idsConservados[$idVencimiento] = true;
            continue;
        }

        $insertOk = mysqli_query(
            $conexion,
            "INSERT INTO venta_vencimientos (
                id_venta,
                fecha_vencimiento,
                monto,
                nota_interna,
                estado,
                id_usuario
            ) VALUES (
                $idVenta,
                '" . mysqli_real_escape_string($conexion, $fechaVencimiento) . "',
                $montoSql,
                '$notaInterna',
                '" . mysqli_real_escape_string($conexion, $estado) . "',
                $idUsuario
            )"
        );
        if (!$insertOk) {
            throw new RuntimeException('No se pudo guardar un vencimiento interno: ' . mysqli_error($conexion));
        }
        $idsConservados[(int) mysqli_insert_id($conexion)] = true;
    }

    $idsEliminar = array_diff_key($existentes, $idsConservados);
    if (!empty($idsEliminar)) {
        $deleteOk = mysqli_query(
            $conexion,
            "DELETE FROM venta_vencimientos
             WHERE id_venta = $idVenta
             AND id IN (" . implode(',', array_map('intval', array_keys($idsEliminar))) . ")"
        );
        if (!$deleteOk) {
            throw new RuntimeException('No se pudieron eliminar vencimientos internos antiguos: ' . mysqli_error($conexion));
        }
    }

    return true;
}

function mayorista_diferir_recordatorio_vencimiento_venta($conexion, $idVencimiento)
{
    $idVencimiento = (int) $idVencimiento;
    if ($idVencimiento <= 0 || !mayorista_schema_vencimientos_venta_listo($conexion)) {
        return false;
    }

    return mysqli_query(
        $conexion,
        "UPDATE venta_vencimientos
         SET fecha_ultimo_recordatorio = CURDATE(),
             updated_at = NOW()
         WHERE id = $idVencimiento"
    ) !== false;
}

function mayorista_obtener_alertas_vencimientos_venta($conexion, $limit = 5)
{
    $limit = max(1, (int) $limit);
    if (!mayorista_schema_vencimientos_venta_listo($conexion)) {
        return array();
    }

    $query = mysqli_query(
        $conexion,
        "SELECT vv.*, v.id_cliente, c.nombre AS cliente_nombre
         FROM venta_vencimientos vv
         INNER JOIN ventas v ON vv.id_venta = v.id
         LEFT JOIN cliente c ON v.id_cliente = c.idcliente
         WHERE vv.estado = 'pendiente'
         AND vv.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
         AND (vv.fecha_ultimo_recordatorio IS NULL OR vv.fecha_ultimo_recordatorio < CURDATE())
         ORDER BY vv.fecha_vencimiento ASC, vv.id ASC
         LIMIT $limit"
    );

    $items = array();
    if (!$query) {
        return $items;
    }

    while ($row = mysqli_fetch_assoc($query)) {
        $monto = isset($row['monto']) ? (float) $row['monto'] : 0;
        $items[] = array(
            'id' => (int) $row['id'],
            'origen' => 'venta_vencimiento',
            'tipo' => 'venta_vencimiento',
            'estado' => (string) ($row['estado'] ?? 'pendiente'),
            'descripcion' => 'Vencimiento interno venta #' . (int) $row['id_venta'],
            'cliente_nombre' => (string) ($row['cliente_nombre'] ?? ''),
            'proveedor_nombre' => '',
            'fecha_vencimiento' => $row['fecha_vencimiento'] ?? null,
            'fecha_deposito' => null,
            'saldo_pendiente' => $monto,
            'monto_total' => $monto,
            'nota_interna' => (string) ($row['nota_interna'] ?? ''),
            'id_venta' => (int) ($row['id_venta'] ?? 0),
        );
    }

    return $items;
}

function mayorista_migracion_finanzas_operativas_fue_ejecutada($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $query = mysqli_query(
        $conexion,
        "SELECT valor
         FROM sistema_flags
         WHERE clave = 'migracion_finanzas_operativas_2026'
         LIMIT 1"
    );

    if (!$query || mysqli_num_rows($query) === 0) {
        return false;
    }

    $row = mysqli_fetch_assoc($query);
    return isset($row['valor']) && $row['valor'] === '1';
}

function mayorista_marcar_migracion_finanzas_operativas_ejecutada($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $sql = "INSERT INTO sistema_flags (clave, valor)
        VALUES ('migracion_finanzas_operativas_2026', '1')
        ON DUPLICATE KEY UPDATE
            valor = VALUES(valor),
            updated_at = CURRENT_TIMESTAMP";

    return mysqli_query($conexion, $sql) !== false;
}

function mayorista_importacion_productos_fue_ejecutada($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $query = mysqli_query(
        $conexion,
        "SELECT valor
         FROM sistema_flags
         WHERE clave = 'importacion_productos_xlsx_2026'
         LIMIT 1"
    );

    if (!$query || mysqli_num_rows($query) === 0) {
        return false;
    }

    $row = mysqli_fetch_assoc($query);
    return isset($row['valor']) && $row['valor'] === '1';
}

function mayorista_marcar_importacion_productos_ejecutada($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $sql = "INSERT INTO sistema_flags (clave, valor)
        VALUES ('importacion_productos_xlsx_2026', '1')
        ON DUPLICATE KEY UPDATE
            valor = VALUES(valor),
            updated_at = CURRENT_TIMESTAMP";

    return mysqli_query($conexion, $sql) !== false;
}

function mayorista_importacion_clientes_fue_ejecutada($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $query = mysqli_query(
        $conexion,
        "SELECT valor
         FROM sistema_flags
         WHERE clave = 'importacion_clientes_xlsx_2026'
         LIMIT 1"
    );

    if (!$query || mysqli_num_rows($query) === 0) {
        return false;
    }

    $row = mysqli_fetch_assoc($query);
    return isset($row['valor']) && $row['valor'] === '1';
}

function mayorista_marcar_importacion_clientes_ejecutada($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $sql = "INSERT INTO sistema_flags (clave, valor)
        VALUES ('importacion_clientes_xlsx_2026', '1')
        ON DUPLICATE KEY UPDATE
            valor = VALUES(valor),
            updated_at = CURRENT_TIMESTAMP";

    return mysqli_query($conexion, $sql) !== false;
}

function mayorista_reset_cc_masivo_fue_ejecutado($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $query = mysqli_query(
        $conexion,
        "SELECT valor
         FROM sistema_flags
         WHERE clave = 'reset_cuentas_corrientes_masivo_2026'
         LIMIT 1"
    );

    if (!$query || mysqli_num_rows($query) === 0) {
        return false;
    }

    $row = mysqli_fetch_assoc($query);
    return isset($row['valor']) && $row['valor'] === '1';
}

function mayorista_marcar_reset_cc_masivo_ejecutado($conexion)
{
    if (!mayorista_asegurar_tabla_flags($conexion)) {
        return false;
    }

    $sql = "INSERT INTO sistema_flags (clave, valor)
        VALUES ('reset_cuentas_corrientes_masivo_2026', '1')
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

/**
 * Catálogo de permisos asignables desde rol.php (excluye módulos retirados: historia clínica, calendario, cristales).
 * Cada ítem usa `token` como valor del formulario; puede expandirse a varios nombres en base de datos.
 *
 * @return array<int, array{titulo: string, items: array<int, array<string, mixed>>}>
 */
function mayorista_permisos_catalogo_para_rol()
{
    return array(
        array(
            'titulo' => 'Administración y acceso',
            'items' => array(
                array('token' => 'configuracion', 'etiqueta' => 'Configuración del sistema', 'icon' => 'cogs'),
                array('token' => 'usuarios', 'etiqueta' => 'Gestión de usuarios', 'icon' => 'users-cog'),
                array('token' => 'api_config', 'etiqueta' => 'API e integraciones', 'icon' => 'plug'),
            ),
        ),
        array(
            'titulo' => 'Clientes y cobranzas',
            'items' => array(
                array('token' => 'clientes', 'etiqueta' => 'Clientes', 'icon' => 'users'),
                array('token' => 'cuenta_corriente', 'etiqueta' => 'Cuenta corriente', 'icon' => 'file-invoice-dollar'),
            ),
        ),
        array(
            'titulo' => 'Stock y ventas',
            'items' => array(
                array('token' => 'productos', 'etiqueta' => 'Productos', 'icon' => 'glasses'),
                array('token' => 'nueva_venta', 'etiqueta' => 'Nueva venta', 'icon' => 'cart-plus'),
                array('token' => 'ventas', 'etiqueta' => 'Listado y edición de ventas', 'icon' => 'receipt'),
            ),
        ),
        array(
            'titulo' => 'Análisis, reportes y tesorería',
            'items' => array(
                array('token' => 'estadisticas', 'etiqueta' => 'Estadísticas', 'icon' => 'chart-line'),
                array(
                    'token' => '__reportes_unificado__',
                    'etiqueta' => 'Reportes',
                    'icon' => 'chart-bar',
                    'expandir_a' => array('reporte', 'reportes'),
                ),
                array('token' => 'reporte_costo', 'etiqueta' => 'Reporte de costos', 'icon' => 'balance-scale'),
                array('token' => 'tesoreria', 'etiqueta' => 'Tesorería (movimientos manuales, cheques)', 'icon' => 'university'),
            ),
        ),
    );
}

/**
 * @return array<string, array<int, string>> token del formulario => nombres en tabla permisos
 */
function mayorista_permisos_tokens_a_nombres_rol()
{
    $map = array();
    foreach (mayorista_permisos_catalogo_para_rol() as $grupo) {
        foreach ($grupo['items'] as $item) {
            $token = $item['token'];
            if (!empty($item['expandir_a']) && is_array($item['expandir_a'])) {
                $map[$token] = array_values($item['expandir_a']);
            } else {
                $map[$token] = array($token);
            }
        }
    }
    return $map;
}

/**
 * @return string[]
 */
function mayorista_permisos_nombres_gestionables_rol()
{
    $nombres = array();
    foreach (mayorista_permisos_tokens_a_nombres_rol() as $lista) {
        $nombres = array_merge($nombres, $lista);
    }
    return array_values(array_unique($nombres));
}

/**
 * Inserta en `permisos` las filas del catálogo que aún no existan.
 */
function mayorista_asegurar_permisos_catalogo_rol($conexion)
{
    if (!$conexion || !is_object($conexion)) {
        return;
    }
    foreach (mayorista_permisos_nombres_gestionables_rol() as $nombre) {
        $esc = mysqli_real_escape_string($conexion, $nombre);
        $sql = "INSERT INTO permisos (nombre)
            SELECT '$esc'
            WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE nombre = '$esc')";
        mysqli_query($conexion, $sql);
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

function mayorista_fecha_iso_valida($fecha)
{
    if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return false;
    }

    $partes = explode('-', $fecha);
    return count($partes) === 3 && checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0]);
}

function mayorista_fecha_hora_desde_iso($fecha, $horaBase = null)
{
    if (!mayorista_fecha_iso_valida($fecha)) {
        return null;
    }

    $hora = date('H:i:s');
    if (is_string($horaBase) && preg_match('/\b(\d{2}:\d{2}:\d{2})\b/', $horaBase, $matches)) {
        $hora = $matches[1];
    }

    return $fecha . ' ' . $hora;
}

function mayorista_normalizar_importe($valor)
{
    if (is_string($valor)) {
        $valor = str_replace(',', '.', trim($valor));
    }

    if (!is_numeric($valor)) {
        return null;
    }

    return round((float) $valor, 2);
}

function mayorista_limpiar_descripcion($descripcion, $maxLength = 255)
{
    $descripcion = trim((string) $descripcion);
    if ($descripcion === '') {
        return '';
    }

    $descripcion = preg_replace('/\s+/', ' ', $descripcion);
    return substr((string) $descripcion, 0, $maxLength);
}

function mayorista_obtener_o_crear_proveedor($conexion, $nombre)
{
    $nombre = mayorista_limpiar_descripcion($nombre, 150);
    if ($nombre === '' || !mayorista_table_exists($conexion, 'proveedores')) {
        return 0;
    }

    $nombreEscapado = mysqli_real_escape_string($conexion, $nombre);
    $query = mysqli_query(
        $conexion,
        "SELECT id
         FROM proveedores
         WHERE nombre = '$nombreEscapado'
         LIMIT 1"
    );

    if ($query && mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        return (int) ($row['id'] ?? 0);
    }

    $insert = mysqli_query(
        $conexion,
        "INSERT INTO proveedores (nombre, estado, created_at, updated_at)
         VALUES ('$nombreEscapado', 1, NOW(), NOW())"
    );

    return $insert ? (int) mysqli_insert_id($conexion) : 0;
}

function mayorista_registrar_movimiento_tesoreria($conexion, $tipo, $monto, $descripcion, $fecha, $idCliente = 0, $idMetodo = 1, $idVentaRef = null)
{
    $tipo = $tipo === 'egreso' ? 'egreso' : ($tipo === 'ingreso' ? 'ingreso' : '');
    $monto = mayorista_normalizar_importe($monto);
    $descripcion = mayorista_limpiar_descripcion($descripcion);
    $idCliente = (int) $idCliente;
    $idMetodo = max(1, (int) $idMetodo);

    if ($tipo === '') {
        throw new InvalidArgumentException('Tipo de movimiento inválido.');
    }
    if ($monto === null || $monto <= 0) {
        throw new InvalidArgumentException('El monto debe ser mayor a cero.');
    }
    if (!mayorista_fecha_iso_valida($fecha)) {
        throw new InvalidArgumentException('La fecha indicada no es válida.');
    }
    if ($descripcion === '') {
        throw new InvalidArgumentException('La descripción es obligatoria.');
    }

    $tabla = $tipo === 'ingreso' ? 'ingresos' : 'egresos';
    $campoMonto = $tipo === 'ingreso' ? 'ingresos' : 'egresos';
    if (!mayorista_table_exists($conexion, $tabla)) {
        throw new RuntimeException('La tabla requerida para tesorería no existe.');
    }

    $montoGuardado = abs($monto);
    $campos = array($campoMonto, 'descripcion', 'fecha', 'id_cliente', 'id_metodo');
    $valores = array(
        $montoGuardado,
        "'" . mysqli_real_escape_string($conexion, $descripcion) . "'",
        "'" . mysqli_real_escape_string($conexion, $fecha) . "'",
        $idCliente,
        $idMetodo,
    );

    if (mayorista_column_exists($conexion, $tabla, 'id_venta')) {
        $referencia = trim((string) $idVentaRef);
        if ($referencia === '') {
            $referencia = strtoupper($tipo) . '-MANUAL-' . date('YmdHis');
        }
        $campos[] = 'id_venta';
        $valores[] = "'" . mysqli_real_escape_string($conexion, $referencia) . "'";
    }

    $sql = "INSERT INTO $tabla (" . implode(', ', $campos) . ")
        VALUES (" . implode(', ', $valores) . ")";
    $ok = mysqli_query($conexion, $sql);

    if (!$ok) {
        throw new RuntimeException('No se pudo registrar el movimiento de tesorería: ' . mysqli_error($conexion));
    }

    return true;
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

function mayorista_registrar_movimiento_cc($conexion, $idCliente, $tipo, $monto, $descripcion, $idUsuario, $idVenta = null, $fecha = null)
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
    $fechaSql = 'NOW()';
    if (is_string($fecha)) {
        $fecha = trim($fecha);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = mayorista_fecha_hora_desde_iso($fecha);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fecha)) {
            $fechaSql = "'" . mysqli_real_escape_string($conexion, $fecha) . "'";
        }
    }

    $insert = mysqli_query(
        $conexion,
        "INSERT INTO movimientos_cc (
            id_cuenta_corriente, id_venta, tipo, monto, descripcion, id_usuario, fecha
         ) VALUES (
            $idCuenta, $ventaValue, '$tipo', $monto, '$descripcion', $idUsuario, $fechaSql
         )"
    );

    if (!$insert) {
        return 0;
    }

    return mayorista_actualizar_saldo_cc($conexion, $idCuenta);
}

function mayorista_venta_tiene_factura_aprobada($conexion, $idVenta)
{
    $idVenta = (int) $idVenta;
    if ($idVenta <= 0 || !mayorista_table_exists($conexion, 'facturas_electronicas')) {
        return false;
    }

    $query = mysqli_query(
        $conexion,
        "SELECT 1
         FROM facturas_electronicas
         WHERE id_venta = $idVenta
         AND estado = 'aprobado'
         LIMIT 1"
    );

    return $query && mysqli_num_rows($query) > 0;
}

function mayorista_registrar_compromiso_financiero($conexion, array $data)
{
    if (!mayorista_table_exists($conexion, 'compromisos_financieros')) {
        throw new RuntimeException('La estructura financiera aún no está disponible.');
    }

    $tiposValidos = array('cheque_recibido', 'cheque_emitido', 'deuda_proveedor', 'compromiso_pago');
    $estadosValidos = array('pendiente', 'parcial', 'cumplido', 'vencido', 'pendiente_confirmacion');

    $tipo = in_array($data['tipo'] ?? '', $tiposValidos, true) ? $data['tipo'] : '';
    $estado = in_array($data['estado'] ?? '', $estadosValidos, true) ? $data['estado'] : 'pendiente';
    $montoTotal = mayorista_normalizar_importe($data['monto_total'] ?? null);
    $saldoPendiente = mayorista_normalizar_importe($data['saldo_pendiente'] ?? $montoTotal);
    $fechaCompromiso = $data['fecha_compromiso'] ?? date('Y-m-d');
    $fechaVencimiento = $data['fecha_vencimiento'] ?? $fechaCompromiso;
    $fechaDeposito = $data['fecha_deposito'] ?? null;
    $descripcion = mayorista_limpiar_descripcion($data['descripcion'] ?? '', 255);
    $observaciones = mayorista_limpiar_descripcion($data['observaciones'] ?? '', 500);
    $idCliente = (int) ($data['id_cliente'] ?? 0);
    $idProveedor = (int) ($data['id_proveedor'] ?? 0);
    $idVenta = (int) ($data['id_venta'] ?? 0);
    $idMetodo = (int) ($data['id_metodo'] ?? 0);
    $idUsuario = (int) ($data['id_usuario'] ?? 0);

    if ($tipo === '') {
        throw new InvalidArgumentException('El tipo de compromiso no es válido.');
    }
    if ($montoTotal === null || $montoTotal <= 0) {
        throw new InvalidArgumentException('El monto total debe ser mayor a cero.');
    }
    if ($saldoPendiente === null || $saldoPendiente < 0) {
        throw new InvalidArgumentException('El saldo pendiente no es válido.');
    }
    if (!mayorista_fecha_iso_valida($fechaCompromiso) || !mayorista_fecha_iso_valida($fechaVencimiento)) {
        throw new InvalidArgumentException('Las fechas del compromiso no son válidas.');
    }
    if ($fechaDeposito !== null && $fechaDeposito !== '' && !mayorista_fecha_iso_valida($fechaDeposito)) {
        throw new InvalidArgumentException('La fecha de depósito no es válida.');
    }
    if ($descripcion === '') {
        throw new InvalidArgumentException('La descripción del compromiso es obligatoria.');
    }

    if (in_array($tipo, array('deuda_proveedor', 'cheque_emitido'), true) && $idProveedor <= 0) {
        throw new InvalidArgumentException('Debés indicar un proveedor para este tipo de compromiso.');
    }

    $tipoSql = "'" . mysqli_real_escape_string($conexion, $tipo) . "'";
    $estadoSql = "'" . mysqli_real_escape_string($conexion, $estado) . "'";
    $fechaCompromisoSql = "'" . mysqli_real_escape_string($conexion, $fechaCompromiso) . "'";
    $fechaVencimientoSql = "'" . mysqli_real_escape_string($conexion, $fechaVencimiento) . "'";
    $fechaDepositoSql = ($fechaDeposito === null || $fechaDeposito === '') ? 'NULL' : "'" . mysqli_real_escape_string($conexion, $fechaDeposito) . "'";
    $descripcionSql = "'" . mysqli_real_escape_string($conexion, $descripcion) . "'";
    $observacionesSql = $observaciones === '' ? 'NULL' : "'" . mysqli_real_escape_string($conexion, $observaciones) . "'";
    $idClienteSql = $idCliente > 0 ? (string) $idCliente : 'NULL';
    $idProveedorSql = $idProveedor > 0 ? (string) $idProveedor : 'NULL';
    $idVentaSql = $idVenta > 0 ? (string) $idVenta : 'NULL';
    $idMetodoSql = $idMetodo > 0 ? (string) $idMetodo : 'NULL';

    $sql = "INSERT INTO compromisos_financieros (
        tipo, id_cliente, id_proveedor, id_venta, id_metodo, monto_total, saldo_pendiente,
        estado, fecha_compromiso, fecha_vencimiento, fecha_deposito, fecha_ultimo_recordatorio,
        descripcion, observaciones, id_usuario, created_at, updated_at
    ) VALUES (
        $tipoSql, $idClienteSql, $idProveedorSql, $idVentaSql, $idMetodoSql, $montoTotal, $saldoPendiente,
        $estadoSql, $fechaCompromisoSql, $fechaVencimientoSql, $fechaDepositoSql, NULL,
        $descripcionSql, $observacionesSql, $idUsuario, NOW(), NOW()
    )";

    $ok = mysqli_query($conexion, $sql);
    $nuevoId = $ok ? (int) mysqli_insert_id($conexion) : 0;

    if (!$ok) {
        throw new RuntimeException('No se pudo registrar el compromiso financiero: ' . mysqli_error($conexion));
    }

    return $nuevoId;
}

function mayorista_registrar_pago_compromiso($conexion, $idCompromiso, $monto, $fechaPago, $descripcion, $idUsuario, $idMetodo = 1)
{
    if (!mayorista_table_exists($conexion, 'compromisos_financieros') || !mayorista_table_exists($conexion, 'compromisos_financieros_pagos')) {
        throw new RuntimeException('La estructura financiera aún no está disponible.');
    }

    $idCompromiso = (int) $idCompromiso;
    $monto = mayorista_normalizar_importe($monto);
    $descripcion = mayorista_limpiar_descripcion($descripcion);
    $idUsuario = (int) $idUsuario;
    $idMetodo = max(1, (int) $idMetodo);

    if ($idCompromiso <= 0) {
        throw new InvalidArgumentException('Compromiso inválido.');
    }
    if ($monto === null || $monto <= 0) {
        throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
    }
    if (!mayorista_fecha_iso_valida($fechaPago)) {
        throw new InvalidArgumentException('La fecha del pago no es válida.');
    }
    if ($descripcion === '') {
        throw new InvalidArgumentException('La descripción del pago es obligatoria.');
    }

    $query = mysqli_query(
        $conexion,
        "SELECT tipo, saldo_pendiente
         FROM compromisos_financieros
         WHERE id = $idCompromiso
         LIMIT 1"
    );
    $compromiso = $query ? mysqli_fetch_assoc($query) : null;
    if (!$compromiso) {
        throw new InvalidArgumentException('No se encontró el compromiso indicado.');
    }
    if (in_array($compromiso['tipo'] ?? '', array('cheque_recibido', 'cheque_emitido'), true)) {
        throw new InvalidArgumentException('Los cheques deben confirmarse desde los recordatorios, no como pago parcial.');
    }

    $saldoActual = (float) ($compromiso['saldo_pendiente'] ?? 0);
    if ($monto > ($saldoActual + 0.009)) {
        throw new InvalidArgumentException('El pago no puede superar el saldo pendiente.');
    }

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO compromisos_financieros_pagos (
            id_compromiso, monto, fecha_pago, descripcion, id_metodo, id_usuario, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar el pago del compromiso.');
    }

    mysqli_stmt_bind_param($stmt, 'idssii', $idCompromiso, $monto, $fechaPago, $descripcion, $idMetodo, $idUsuario);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        throw new RuntimeException('No se pudo registrar el pago del compromiso.');
    }

    $nuevoSaldo = max(0, round($saldoActual - $monto, 2));
    $nuevoEstado = $nuevoSaldo <= 0 ? 'cumplido' : 'parcial';
    mysqli_query(
        $conexion,
        "UPDATE compromisos_financieros
         SET saldo_pendiente = $nuevoSaldo,
             estado = '$nuevoEstado',
             updated_at = NOW()
         WHERE id = $idCompromiso"
    );

    return $nuevoSaldo;
}

function mayorista_diferir_recordatorio_compromiso($conexion, $idCompromiso)
{
    $idCompromiso = (int) $idCompromiso;
    if ($idCompromiso <= 0 || !mayorista_table_exists($conexion, 'compromisos_financieros')) {
        return false;
    }

    return mysqli_query(
        $conexion,
        "UPDATE compromisos_financieros
         SET fecha_ultimo_recordatorio = CURDATE(),
             updated_at = NOW()
         WHERE id = $idCompromiso"
    ) !== false;
}

function mayorista_confirmar_cheque_recibido($conexion, $idCompromiso, $fechaIngreso = null)
{
    $idCompromiso = (int) $idCompromiso;
    if ($idCompromiso <= 0) {
        throw new InvalidArgumentException('Cheque inválido.');
    }
    if (!mayorista_table_exists($conexion, 'compromisos_financieros')) {
        throw new RuntimeException('La estructura financiera aún no está disponible.');
    }

    $query = mysqli_query(
        $conexion,
        "SELECT *
         FROM compromisos_financieros
         WHERE id = $idCompromiso
         AND tipo = 'cheque_recibido'
         LIMIT 1"
    );
    $cheque = $query ? mysqli_fetch_assoc($query) : null;
    if (!$cheque) {
        throw new InvalidArgumentException('No se encontró el cheque recibido.');
    }
    if (($cheque['estado'] ?? '') === 'cumplido' || (float) ($cheque['saldo_pendiente'] ?? 0) <= 0.009) {
        throw new InvalidArgumentException('Ese cheque ya fue confirmado anteriormente.');
    }

    $fechaIngreso = $fechaIngreso ?: date('Y-m-d');
    if (!mayorista_fecha_iso_valida($fechaIngreso)) {
        throw new InvalidArgumentException('La fecha de confirmación no es válida.');
    }

    mayorista_registrar_movimiento_tesoreria(
        $conexion,
        'ingreso',
        (float) $cheque['monto_total'],
        'Cheque acreditado: ' . ($cheque['descripcion'] ?? ('Compromiso #' . $idCompromiso)),
        $fechaIngreso,
        (int) ($cheque['id_cliente'] ?? 0),
        (int) ($cheque['id_metodo'] ?? 5),
        trim((string) ($cheque['id_venta'] ?? '')) !== '' ? (string) $cheque['id_venta'] : ('CHEQUE-' . $idCompromiso)
    );

    mysqli_query(
        $conexion,
        "UPDATE compromisos_financieros
         SET saldo_pendiente = 0,
             estado = 'cumplido',
             fecha_ultimo_recordatorio = CURDATE(),
             updated_at = NOW()
         WHERE id = $idCompromiso"
    );

    return true;
}

function mayorista_confirmar_cheque_emitido($conexion, $idCompromiso, $fechaEgreso = null)
{
    $idCompromiso = (int) $idCompromiso;
    if ($idCompromiso <= 0) {
        throw new InvalidArgumentException('Cheque inválido.');
    }
    if (!mayorista_table_exists($conexion, 'compromisos_financieros')) {
        throw new RuntimeException('La estructura financiera aún no está disponible.');
    }

    $query = mysqli_query(
        $conexion,
        "SELECT *
         FROM compromisos_financieros
         WHERE id = $idCompromiso
         AND tipo = 'cheque_emitido'
         LIMIT 1"
    );
    $cheque = $query ? mysqli_fetch_assoc($query) : null;
    if (!$cheque) {
        throw new InvalidArgumentException('No se encontró el cheque emitido.');
    }
    if (($cheque['estado'] ?? '') === 'cumplido' || (float) ($cheque['saldo_pendiente'] ?? 0) <= 0.009) {
        throw new InvalidArgumentException('Ese cheque emitido ya fue confirmado anteriormente.');
    }

    $fechaEgreso = $fechaEgreso ?: date('Y-m-d');
    if (!mayorista_fecha_iso_valida($fechaEgreso)) {
        throw new InvalidArgumentException('La fecha de confirmación no es válida.');
    }

    mayorista_registrar_movimiento_tesoreria(
        $conexion,
        'egreso',
        (float) $cheque['monto_total'],
        'Cheque debitado: ' . ($cheque['descripcion'] ?? ('Compromiso #' . $idCompromiso)),
        $fechaEgreso,
        (int) ($cheque['id_cliente'] ?? 0),
        (int) ($cheque['id_metodo'] ?? 5),
        trim((string) ($cheque['id_venta'] ?? '')) !== '' ? (string) $cheque['id_venta'] : ('CHEQUE-EMITIDO-' . $idCompromiso)
    );

    mysqli_query(
        $conexion,
        "UPDATE compromisos_financieros
         SET saldo_pendiente = 0,
             estado = 'cumplido',
             fecha_ultimo_recordatorio = CURDATE(),
             updated_at = NOW()
         WHERE id = $idCompromiso"
    );

    return true;
}

function mayorista_obtener_alertas_financieras($conexion, $limit = 5)
{
    $limit = max(1, (int) $limit);
    $items = array();
    if (mayorista_table_exists($conexion, 'compromisos_financieros')) {
        $query = mysqli_query(
            $conexion,
            "SELECT cf.*, c.nombre AS cliente_nombre, p.nombre AS proveedor_nombre
             FROM compromisos_financieros cf
             LEFT JOIN cliente c ON cf.id_cliente = c.idcliente
             LEFT JOIN proveedores p ON cf.id_proveedor = p.id
             WHERE (
                (cf.tipo = 'cheque_recibido' AND cf.estado = 'pendiente_confirmacion' AND cf.fecha_deposito IS NOT NULL AND cf.fecha_deposito < CURDATE())
                OR
                (cf.tipo = 'cheque_emitido' AND cf.estado = 'pendiente_confirmacion' AND cf.fecha_vencimiento <= CURDATE())
                 OR
                 (cf.estado IN ('pendiente', 'parcial', 'vencido') AND cf.fecha_vencimiento <= CURDATE())
             )
             AND (cf.fecha_ultimo_recordatorio IS NULL OR cf.fecha_ultimo_recordatorio < CURDATE())
             ORDER BY
                 CASE WHEN cf.estado = 'pendiente_confirmacion' THEN 0 ELSE 1 END,
                CASE
                    WHEN cf.tipo = 'cheque_recibido' THEN cf.fecha_deposito
                    ELSE cf.fecha_vencimiento
                END ASC,
                cf.id ASC
             LIMIT $limit"
        );

        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $row['origen'] = 'compromiso_financiero';
                $row['nota_interna'] = (string) ($row['observaciones'] ?? '');
                $items[] = $row;
            }
        }
    }

    $items = array_merge($items, mayorista_obtener_alertas_vencimientos_venta($conexion, $limit));
    usort($items, function ($a, $b) {
        $fechaA = !empty($a['tipo']) && $a['tipo'] === 'cheque_recibido' && !empty($a['fecha_deposito'])
            ? $a['fecha_deposito']
            : ($a['fecha_vencimiento'] ?? '9999-12-31');
        $fechaB = !empty($b['tipo']) && $b['tipo'] === 'cheque_recibido' && !empty($b['fecha_deposito'])
            ? $b['fecha_deposito']
            : ($b['fecha_vencimiento'] ?? '9999-12-31');
        if ($fechaA === $fechaB) {
            $prioridadA = ($a['estado'] ?? '') === 'pendiente_confirmacion' ? 0 : 1;
            $prioridadB = ($b['estado'] ?? '') === 'pendiente_confirmacion' ? 0 : 1;
            if ($prioridadA === $prioridadB) {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
            return $prioridadA <=> $prioridadB;
        }
        return strcmp((string) $fechaA, (string) $fechaB);
    });

    if (count($items) > $limit) {
        $items = array_slice($items, 0, $limit);
    }

    return $items;
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

