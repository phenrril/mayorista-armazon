<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";

header('Content-Type: application/json; charset=utf-8');

function importacion_clientes_json($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function importacion_clientes_normalizar_texto($texto)
{
    $texto = trim((string) $texto);
    if ($texto === '') {
        return '';
    }

    $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    if ($convertido !== false) {
        $texto = $convertido;
    }

    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
    return trim((string) $texto);
}

function importacion_clientes_leer_zip_entry($xlsxPath, $entry)
{
    $command = 'unzip -p ' . escapeshellarg($xlsxPath) . ' ' . escapeshellarg($entry) . ' 2>/dev/null';
    $content = shell_exec($command);
    return is_string($content) ? $content : '';
}

function importacion_clientes_columna_a_indice($referenciaCelda)
{
    $letras = preg_replace('/[^A-Z]/i', '', (string) $referenciaCelda);
    $letras = strtoupper($letras);
    $indice = 0;

    for ($i = 0; $i < strlen($letras); $i++) {
        $indice = ($indice * 26) + (ord($letras[$i]) - 64);
    }

    return max(1, $indice) - 1;
}

function importacion_clientes_obtener_hoja_principal($xlsxPath)
{
    $workbookXml = importacion_clientes_leer_zip_entry($xlsxPath, 'xl/workbook.xml');
    $relsXml = importacion_clientes_leer_zip_entry($xlsxPath, 'xl/_rels/workbook.xml.rels');

    if (!preg_match('/<sheet\b[^>]*\br:id="([^"]+)"/i', $workbookXml, $sheetMatch)) {
        throw new RuntimeException('El archivo XLSX no contiene hojas.');
    }

    $relId = $sheetMatch[1];
    $pattern = '/<Relationship\b(?=[^>]*\bId="' . preg_quote($relId, '/') . '")(?=[^>]*\bTarget="([^"]+)")[^>]*\/?>/i';
    if (preg_match($pattern, $relsXml, $relMatch)) {
        $target = ltrim($relMatch[1], '/');
        return strpos($target, 'xl/') === 0 ? $target : 'xl/' . $target;
    }

    throw new RuntimeException('No se pudo localizar el contenido de la hoja principal.');
}

function importacion_clientes_obtener_shared_strings($xlsxPath)
{
    $content = importacion_clientes_leer_zip_entry($xlsxPath, 'xl/sharedStrings.xml');
    if (trim($content) === '') {
        return array();
    }

    $items = array();
    if (preg_match_all('/<si\b[^>]*>(.*?)<\/si>/si', $content, $matches)) {
        foreach ($matches[1] as $itemXml) {
            $texto = html_entity_decode(strip_tags($itemXml), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $items[] = trim((string) $texto);
        }
    }

    return $items;
}

function importacion_clientes_valor_celda_desde_xml($cellXml, $tipo, array $sharedStrings)
{
    if ($tipo === 'inlineStr') {
        return trim((string) html_entity_decode(strip_tags($cellXml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    $valor = '';
    if (preg_match('/<v>(.*?)<\/v>/si', $cellXml, $match)) {
        $valor = html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
    if ($tipo === 's') {
        $indice = (int) $valor;
        return $sharedStrings[$indice] ?? '';
    }

    return trim((string) $valor);
}

function importacion_clientes_leer_filas($xlsxPath)
{
    $sharedStrings = importacion_clientes_obtener_shared_strings($xlsxPath);
    $sheetPath = importacion_clientes_obtener_hoja_principal($xlsxPath);
    $sheetXml = importacion_clientes_leer_zip_entry($xlsxPath, $sheetPath);
    if (trim($sheetXml) === '') {
        throw new RuntimeException('No se pudo leer la hoja principal del XLSX.');
    }

    if (!preg_match_all('/<row\b[^>]*>(.*?)<\/row>/si', $sheetXml, $rowMatches)) {
        return array();
    }

    $headers = array();
    $rows = array();
    foreach ($rowMatches[1] as $rowIndex => $rowXml) {
        $rowValues = array();
        if (preg_match_all('/<c\b([^>]*?)(?:\/>|>(.*?)<\/c>)/si', $rowXml, $cellMatches, PREG_SET_ORDER)) {
            foreach ($cellMatches as $cellMatch) {
                $atributos = $cellMatch[1];
                $cellXml = $cellMatch[2] ?? '';
                if (!preg_match('/\br="([^"]+)"/i', $atributos, $refMatch)) {
                    continue;
                }

                $tipo = '';
                if (preg_match('/\bt="([^"]+)"/i', $atributos, $typeMatch)) {
                    $tipo = $typeMatch[1];
                }

                $indice = importacion_clientes_columna_a_indice($refMatch[1]);
                $rowValues[$indice] = importacion_clientes_valor_celda_desde_xml($cellXml, $tipo, $sharedStrings);
            }
        }

        if ($rowIndex === 0) {
            foreach ($rowValues as $indice => $valor) {
                $headers[$indice] = importacion_clientes_normalizar_texto($valor);
            }
            continue;
        }

        $registro = array();
        foreach ($headers as $indice => $header) {
            if ($header === '') {
                continue;
            }
            $registro[$header] = $rowValues[$indice] ?? '';
        }

        if (!array_filter($registro, function ($value) {
            return trim((string) $value) !== '';
        })) {
            continue;
        }

        $rows[] = $registro;
    }

    return $rows;
}

function importacion_clientes_resolver_archivo_fuente()
{
    if (isset($_FILES['archivo_clientes']) && is_array($_FILES['archivo_clientes'])) {
        $archivo = $_FILES['archivo_clientes'];
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $nombreArchivo = (string) ($archivo['name'] ?? '');
            $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
            $tmpFile = (string) ($archivo['tmp_name'] ?? '');

            if ($extension !== 'xlsx') {
                throw new RuntimeException('El archivo debe ser un XLSX válido.');
            }
            if ($tmpFile === '' || !is_uploaded_file($tmpFile)) {
                throw new RuntimeException('No se pudo acceder al archivo subido.');
            }

            return array(
                'path' => $tmpFile,
                'source' => 'upload',
                'label' => $nombreArchivo !== '' ? $nombreArchivo : 'archivo subido',
            );
        }

        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('No se pudo recibir el archivo seleccionado.');
        }
    }

    $candidatos = glob(dirname(__DIR__) . DIRECTORY_SEPARATOR . '*Listado de Clientes*.xlsx');
    if (!empty($candidatos) && is_file($candidatos[0])) {
        return array(
            'path' => $candidatos[0],
            'source' => 'bundled',
            'label' => basename($candidatos[0]),
        );
    }

    throw new RuntimeException('Debes seleccionar un archivo XLSX o dejar una planilla compatible dentro de la raíz del proyecto.');
}

function importacion_clientes_limpiar($valor, $maxLength = 200)
{
    return mayorista_limpiar_descripcion((string) $valor, $maxLength);
}

function importacion_clientes_limpiar_digitos($valor, $maxLength)
{
    $valor = preg_replace('/\D+/', '', (string) $valor);
    return substr((string) $valor, 0, $maxLength);
}

function importacion_clientes_normalizar_telefono($telefono, $telefonoAlternativo = '')
{
    $telefono = importacion_clientes_limpiar_digitos($telefono, 15);
    $telefonoAlternativo = importacion_clientes_limpiar_digitos($telefonoAlternativo, 15);

    if ($telefono !== '') {
        return $telefono;
    }

    return $telefonoAlternativo;
}

function importacion_clientes_normalizar_condicion_iva($valor)
{
    $valorLimpio = importacion_clientes_limpiar($valor, 50);
    if ($valorLimpio === '') {
        return 'Consumidor Final';
    }

    $normalizado = importacion_clientes_normalizar_texto($valorLimpio);
    $mapa = array(
        'consumidor final' => 'Consumidor Final',
        'cf' => 'Consumidor Final',
        'iva responsable inscripto' => 'IVA Responsable Inscripto',
        'responsable inscripto' => 'IVA Responsable Inscripto',
        'ri' => 'IVA Responsable Inscripto',
        'responsable monotributo' => 'Responsable Monotributo',
        'monotributo' => 'Responsable Monotributo',
        'monotributista' => 'Responsable Monotributo',
        'iva sujeto exento' => 'IVA Sujeto Exento',
        'exento' => 'IVA Sujeto Exento',
        'iva responsable no inscripto' => 'IVA Responsable no Inscripto',
        'iva no responsable' => 'IVA no Responsable',
        'sujeto no categorizado' => 'Sujeto no Categorizado',
        'proveedor del exterior' => 'Proveedor del Exterior',
        'cliente del exterior' => 'Cliente del Exterior',
        'iva liberado ley n 19 640' => 'IVA Liberado - Ley N° 19.640',
    );

    return $mapa[$normalizado] ?? $valorLimpio;
}

function importacion_clientes_normalizar_fecha($valor)
{
    $valor = trim((string) $valor);
    if ($valor === '') {
        return null;
    }

    if (is_numeric($valor)) {
        $dias = (float) $valor;
        $timestamp = (int) round(($dias - 25569) * 86400);
        if ($timestamp > 0) {
            return gmdate('Y-m-d H:i:s', $timestamp);
        }
    }

    $timestamp = strtotime($valor);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function importacion_clientes_contar_movimientos_cc($conexion, $idCliente)
{
    $idCliente = (int) $idCliente;
    if (
        $idCliente <= 0
        || !mayorista_table_exists($conexion, 'cuenta_corriente')
        || !mayorista_table_exists($conexion, 'movimientos_cc')
    ) {
        return 0;
    }

    $query = mysqli_query(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM movimientos_cc m
         INNER JOIN cuenta_corriente cc ON cc.id = m.id_cuenta_corriente
         WHERE cc.id_cliente = $idCliente"
    );

    if (!$query) {
        return 0;
    }

    $row = mysqli_fetch_assoc($query);
    return (int) ($row['total'] ?? 0);
}

function importacion_clientes_puede_registrar_saldo_inicial($conexion, $idCliente)
{
    $idCliente = (int) $idCliente;
    if (
        $idCliente <= 0
        || !mayorista_table_exists($conexion, 'cuenta_corriente')
        || !mayorista_table_exists($conexion, 'movimientos_cc')
    ) {
        return false;
    }

    return importacion_clientes_contar_movimientos_cc($conexion, $idCliente) === 0;
}

function importacion_clientes_mapear(array $row)
{
    $optica = importacion_clientes_limpiar($row['cliente'] ?? ($row['razon social'] ?? ''), 150);
    $nombre = trim(importacion_clientes_limpiar(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? ''), 100));
    if ($nombre === '') {
        $nombre = importacion_clientes_limpiar($optica !== '' ? $optica : ($row['razon social'] ?? ''), 100);
    }

    $telefono = importacion_clientes_normalizar_telefono($row['telefono'] ?? '', $row['telefono 2'] ?? '');

    $direccion = importacion_clientes_limpiar($row['direccion'] ?? '', 200);
    if ($direccion === '') {
        $direccion = importacion_clientes_limpiar($row['domicilio fiscal'] ?? '', 200);
    }

    $localidad = importacion_clientes_limpiar($row['localidad'] ?? '', 120);
    if ($localidad === '') {
        $localidad = importacion_clientes_limpiar($row['localidad fiscal'] ?? '', 120);
    }

    $provincia = importacion_clientes_limpiar($row['provincia'] ?? '', 120);
    if ($provincia === '') {
        $provincia = importacion_clientes_limpiar($row['provincia fiscal'] ?? '', 120);
    }

    $codigoPostal = importacion_clientes_limpiar_digitos($row['codigo postal fiscal'] ?? '', 20);
    $dni = importacion_clientes_limpiar_digitos($row['dni'] ?? '', 12);
    $cuit = importacion_clientes_limpiar_digitos($row['cuit'] ?? '', 13);
    $condicionIva = importacion_clientes_normalizar_condicion_iva($row['condicion de iva'] ?? '');
    $saldoInicial = mayorista_normalizar_importe($row['saldo inicial'] ?? null);
    if ($saldoInicial === null) {
        $saldoInicial = 0.0;
    }
    $observaciones = importacion_clientes_limpiar($row['observaciones'] ?? '', 255);
    $fechaSaldoInicial = importacion_clientes_normalizar_fecha($row['fecha saldo inicial'] ?? '');

    return array(
        'optica' => $optica,
        'nombre' => $nombre,
        'telefono' => $telefono,
        'direccion' => $direccion,
        'localidad' => $localidad,
        'provincia' => $provincia,
        'codigo_postal' => $codigoPostal,
        'dni' => $dni,
        'cuit' => $cuit,
        'condicion_iva' => $condicionIva,
        'tipo_documento' => $cuit !== '' ? 80 : 96,
        'estado' => 1,
        'saldo_inicial' => (float) $saldoInicial,
        'fecha_saldo_inicial' => $fechaSaldoInicial,
        'observaciones' => $observaciones,
    );
}

function importacion_clientes_resolver_existente($conexion, array $cliente)
{
    if ($cliente['cuit'] !== '') {
        $cuit = mysqli_real_escape_string($conexion, $cliente['cuit']);
        $query = mysqli_query($conexion, "SELECT idcliente FROM cliente WHERE cuit = '$cuit' LIMIT 1");
        if ($query && mysqli_num_rows($query) > 0) {
            $row = mysqli_fetch_assoc($query);
            return (int) ($row['idcliente'] ?? 0);
        }
    }

    if ($cliente['dni'] !== '') {
        $dni = mysqli_real_escape_string($conexion, $cliente['dni']);
        $query = mysqli_query($conexion, "SELECT idcliente FROM cliente WHERE dni = '$dni' LIMIT 1");
        if ($query && mysqli_num_rows($query) > 0) {
            $row = mysqli_fetch_assoc($query);
            return (int) ($row['idcliente'] ?? 0);
        }
    }

    if ($cliente['optica'] !== '' && $cliente['nombre'] !== '') {
        $optica = mysqli_real_escape_string($conexion, $cliente['optica']);
        $nombre = mysqli_real_escape_string($conexion, $cliente['nombre']);
        $query = mysqli_query(
            $conexion,
            "SELECT idcliente
             FROM cliente
             WHERE optica = '$optica'
             AND nombre = '$nombre'
             LIMIT 1"
        );
        if ($query && mysqli_num_rows($query) > 0) {
            $row = mysqli_fetch_assoc($query);
            return (int) ($row['idcliente'] ?? 0);
        }
    }

    if ($cliente['optica'] !== '') {
        $optica = mysqli_real_escape_string($conexion, $cliente['optica']);
        $query = mysqli_query($conexion, "SELECT idcliente FROM cliente WHERE optica = '$optica' LIMIT 1");
        if ($query && mysqli_num_rows($query) > 0) {
            $row = mysqli_fetch_assoc($query);
            return (int) ($row['idcliente'] ?? 0);
        }
    }

    return 0;
}

function importacion_clientes_resumen($conexion, array $rows)
{
    $insertar = 0;
    $actualizar = 0;
    $omitidos = 0;
    $omitidosInvalidos = 0;
    $conSaldoInicial = 0;
    $movimientosCcEstimados = 0;
    $saldosOmitidos = 0;

    foreach ($rows as $row) {
        $cliente = importacion_clientes_mapear($row);
        if ($cliente['nombre'] === '' && $cliente['optica'] === '') {
            $omitidos++;
            $omitidosInvalidos++;
            continue;
        }

        $existente = importacion_clientes_resolver_existente($conexion, $cliente);
        if ($existente > 0) {
            $actualizar++;
        } else {
            $insertar++;
        }

        if (abs((float) $cliente['saldo_inicial']) > 0.0001) {
            $conSaldoInicial++;
            if ($existente > 0) {
                if (importacion_clientes_puede_registrar_saldo_inicial($conexion, $existente)) {
                    $movimientosCcEstimados++;
                } else {
                    $saldosOmitidos++;
                }
            } elseif (
                mayorista_table_exists($conexion, 'cuenta_corriente')
                && mayorista_table_exists($conexion, 'movimientos_cc')
            ) {
                $movimientosCcEstimados++;
            } else {
                $saldosOmitidos++;
            }
        }
    }

    return array(
        'filas_validas' => $insertar + $actualizar,
        'insertar_estimado' => $insertar,
        'actualizar_estimado' => $actualizar,
        'omitidos' => $omitidos,
        'omitidos_invalidos' => $omitidosInvalidos,
        'con_saldo_inicial' => $conSaldoInicial,
        'movimientos_cc_estimados' => $movimientosCcEstimados,
        'saldos_omitidos' => $saldosOmitidos,
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    importacion_clientes_json(array('success' => false, 'message' => 'Metodo no permitido.'), 405);
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    importacion_clientes_json(array('success' => false, 'message' => 'No autorizado.'), 403);
}

if (!($conexion instanceof mysqli)) {
    importacion_clientes_json(array('success' => false, 'message' => 'No se pudo establecer la conexion a la base de datos.'), 500);
}

$idUser = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $idUser, array('configuracion'))) {
    importacion_clientes_json(array('success' => false, 'message' => 'No tenes permisos para ejecutar esta importacion.'), 403);
}

$token = $_POST['csrf_token'] ?? '';
if (!mayorista_validar_token_importacion_clientes($token)) {
    importacion_clientes_json(array('success' => false, 'message' => 'Token de seguridad invalido o vencido.'), 403);
}

$previewOnly = !empty($_POST['preview_only']);

if (mayorista_importacion_clientes_fue_ejecutada($conexion)) {
    mayorista_invalidar_token_importacion_clientes();
    importacion_clientes_json(array(
        'success' => true,
        'message' => 'La importacion de clientes ya fue ejecutada anteriormente.',
        'already_applied' => true,
    ));
}

if (!mayorista_table_exists($conexion, 'cliente')) {
    importacion_clientes_json(array('success' => false, 'message' => 'La tabla de clientes no existe en la base actual.'), 500);
}

try {
    $archivoFuente = importacion_clientes_resolver_archivo_fuente();
    $rows = importacion_clientes_leer_filas($archivoFuente['path']);
    if (empty($rows)) {
        throw new RuntimeException('La planilla no contiene filas de clientes para importar.');
    }

    if ($previewOnly) {
        $resumen = importacion_clientes_resumen($conexion, $rows);
        importacion_clientes_json(array(
            'success' => true,
            'message' => 'Previsualización generada correctamente.',
            'source' => $archivoFuente['source'],
            'label' => $archivoFuente['label'],
            'preview' => $resumen,
        ));
    }

    $insertados = 0;
    $actualizados = 0;
    $omitidos = 0;
    $omitidosInvalidos = 0;
    $movimientosCcCreados = 0;
    $saldosOmitidos = 0;

    mysqli_begin_transaction($conexion);

    foreach ($rows as $row) {
        $cliente = importacion_clientes_mapear($row);
        if ($cliente['nombre'] === '' && $cliente['optica'] === '') {
            $omitidos++;
            $omitidosInvalidos++;
            continue;
        }

        $campos = array(
            'nombre' => "'" . mysqli_real_escape_string($conexion, $cliente['nombre'] !== '' ? $cliente['nombre'] : $cliente['optica']) . "'",
            'optica' => $cliente['optica'] !== '' ? "'" . mysqli_real_escape_string($conexion, $cliente['optica']) . "'" : 'NULL',
            'telefono' => "'" . mysqli_real_escape_string($conexion, $cliente['telefono']) . "'",
            'direccion' => "'" . mysqli_real_escape_string($conexion, $cliente['direccion']) . "'",
            'localidad' => $cliente['localidad'] !== '' ? "'" . mysqli_real_escape_string($conexion, $cliente['localidad']) . "'" : 'NULL',
            'codigo_postal' => $cliente['codigo_postal'] !== '' ? "'" . mysqli_real_escape_string($conexion, $cliente['codigo_postal']) . "'" : 'NULL',
            'provincia' => $cliente['provincia'] !== '' ? "'" . mysqli_real_escape_string($conexion, $cliente['provincia']) . "'" : 'NULL',
            'usuario_id' => $idUser,
            'estado' => 1,
            'dni' => "'" . mysqli_real_escape_string($conexion, $cliente['dni']) . "'",
            'cuit' => $cliente['cuit'] !== '' ? "'" . mysqli_real_escape_string($conexion, $cliente['cuit']) . "'" : 'NULL',
            'condicion_iva' => "'" . mysqli_real_escape_string($conexion, $cliente['condicion_iva']) . "'",
            'tipo_documento' => (int) $cliente['tipo_documento'],
        );

        $idExistente = importacion_clientes_resolver_existente($conexion, $cliente);
        if ($idExistente > 0) {
            $sets = array();
            foreach ($campos as $campo => $valor) {
                $sets[] = $campo . ' = ' . $valor;
            }

            $ok = mysqli_query(
                $conexion,
                "UPDATE cliente
                 SET " . implode(', ', $sets) . "
                 WHERE idcliente = " . (int) $idExistente
            );
            if (!$ok) {
                throw new RuntimeException('No se pudo actualizar el cliente #' . (int) $idExistente . ': ' . mysqli_error($conexion));
            }
            $actualizados++;
        } else {
            $ok = mysqli_query(
                $conexion,
                "INSERT INTO cliente (" . implode(', ', array_keys($campos)) . ")
                 VALUES (" . implode(', ', array_values($campos)) . ")"
            );
            if (!$ok) {
                throw new RuntimeException('No se pudo insertar el cliente ' . ($cliente['optica'] !== '' ? $cliente['optica'] : $cliente['nombre']) . ': ' . mysqli_error($conexion));
            }
            $idExistente = (int) mysqli_insert_id($conexion);
            $insertados++;
        }

        if (abs((float) $cliente['saldo_inicial']) > 0.0001) {
            if (importacion_clientes_puede_registrar_saldo_inicial($conexion, $idExistente)) {
                $descripcionSaldo = 'Saldo inicial importado desde XLSX';
                if ($cliente['observaciones'] !== '') {
                    $descripcionSaldo .= ' - ' . $cliente['observaciones'];
                }

                $fechaMovimiento = $cliente['fecha_saldo_inicial'] ?: date('Y-m-d H:i:s');
                $tipoMovimiento = $cliente['saldo_inicial'] >= 0 ? 'cargo' : 'pago';
                $montoMovimiento = abs((float) $cliente['saldo_inicial']);

                $cuenta = mayorista_asegurar_cuenta_corriente($conexion, $idExistente);
                if (!$cuenta) {
                    throw new RuntimeException('No se pudo preparar la cuenta corriente del cliente #' . (int) $idExistente . '.');
                }

                $idCuenta = (int) ($cuenta['id'] ?? 0);
                $descripcionSql = mysqli_real_escape_string($conexion, $descripcionSaldo);
                $ok = mysqli_query(
                    $conexion,
                    "INSERT INTO movimientos_cc (
                        id_cuenta_corriente, id_venta, tipo, monto, descripcion, id_usuario, fecha
                     ) VALUES (
                        $idCuenta, NULL, '$tipoMovimiento', $montoMovimiento, '$descripcionSql', $idUser, '" . mysqli_real_escape_string($conexion, $fechaMovimiento) . "'
                     )"
                );

                if (!$ok) {
                    throw new RuntimeException('No se pudo registrar el saldo inicial del cliente #' . (int) $idExistente . ': ' . mysqli_error($conexion));
                }

                mayorista_actualizar_saldo_cc($conexion, $idCuenta);
                $movimientosCcCreados++;
            } else {
                $saldosOmitidos++;
            }
        }
    }

    if (($insertados + $actualizados) <= 0) {
        throw new RuntimeException('No se encontraron filas válidas de clientes para importar.');
    }

    if (!mayorista_marcar_importacion_clientes_ejecutada($conexion)) {
        throw new RuntimeException('No se pudo registrar el estado final de la importacion de clientes.');
    }

    mysqli_commit($conexion);
    mayorista_invalidar_token_importacion_clientes();

    importacion_clientes_json(array(
        'success' => true,
        'message' => 'Importacion de clientes completada desde ' . $archivoFuente['label'] . '. Insertados: ' . $insertados . ', actualizados: ' . $actualizados . ', movimientos de cuenta corriente: ' . $movimientosCcCreados . ', omitidos: ' . $omitidos . ', saldos omitidos: ' . $saldosOmitidos . '.',
        'insertados' => $insertados,
        'actualizados' => $actualizados,
        'omitidos' => $omitidos,
        'omitidos_invalidos' => $omitidosInvalidos,
        'movimientos_cc_creados' => $movimientosCcCreados,
        'saldos_omitidos' => $saldosOmitidos,
        'source' => $archivoFuente['source'],
    ));
} catch (Exception $e) {
    @mysqli_rollback($conexion);
    importacion_clientes_json(array(
        'success' => false,
        'message' => 'No se pudo completar la importacion de clientes: ' . $e->getMessage(),
    ), 500);
}
