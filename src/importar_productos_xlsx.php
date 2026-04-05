<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";

header('Content-Type: application/json; charset=utf-8');

function importacion_productos_json($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function importacion_productos_normalizar_texto($texto)
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

function importacion_productos_leer_zip_entry($xlsxPath, $entry)
{
    $command = 'unzip -p ' . escapeshellarg($xlsxPath) . ' ' . escapeshellarg($entry) . ' 2>/dev/null';
    $content = shell_exec($command);
    return is_string($content) ? $content : '';
}

function importacion_productos_cargar_xml($content, $label)
{
    if (trim($content) === '') {
        throw new RuntimeException('No se pudo leer ' . $label . ' del archivo XLSX.');
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    if ($xml === false) {
        throw new RuntimeException('El archivo XLSX tiene un formato inválido en ' . $label . '.');
    }

    return $xml;
}

function importacion_productos_columna_a_indice($referenciaCelda)
{
    $letras = preg_replace('/[^A-Z]/i', '', (string) $referenciaCelda);
    $letras = strtoupper($letras);
    $indice = 0;

    for ($i = 0; $i < strlen($letras); $i++) {
        $indice = ($indice * 26) + (ord($letras[$i]) - 64);
    }

    return max(1, $indice) - 1;
}

function importacion_productos_texto_si($valor)
{
    return in_array(importacion_productos_normalizar_texto($valor), array('si', 's', 'yes', '1'), true);
}

function importacion_productos_inferir_tipo_material($descripcion, $nombre, $tipoServicio)
{
    $texto = strtoupper(trim($descripcion . ' ' . $nombre . ' ' . $tipoServicio));
    if (strpos($texto, 'ACETATO') !== false) {
        return 'Acetato';
    }
    if (strpos($texto, 'TR90') !== false) {
        return 'Tr90';
    }
    if (strpos($texto, 'METAL') !== false) {
        return 'Metal';
    }
    if (strpos($texto, 'INYECCION') !== false || strpos($texto, 'INYECCION') !== false) {
        return 'Inyeccion';
    }

    return null;
}

function importacion_productos_inferir_tipo($tipoServicio)
{
    $texto = strtoupper(trim((string) $tipoServicio));
    if (strpos($texto, 'CLIP') !== false) {
        return 'clip-on';
    }
    if (strpos($texto, 'SOL') !== false) {
        return 'sol';
    }

    return 'receta';
}

function importacion_productos_inferir_modelo_y_color($codigo)
{
    $codigo = mayorista_limpiar_descripcion($codigo, 120);
    $resultado = array(
        'modelo' => $codigo,
        'color' => null,
    );

    if (preg_match('/^(.*?)[\s\-]+(C\d+[A-Z0-9]*)$/i', $codigo, $matches)) {
        $resultado['modelo'] = mayorista_limpiar_descripcion($matches[1], 120);
        $resultado['color'] = mayorista_limpiar_descripcion(strtoupper($matches[2]), 120);
    }

    if ($resultado['modelo'] === '') {
        $resultado['modelo'] = $codigo;
    }

    return $resultado;
}

function importacion_productos_obtener_hoja_principal($xlsxPath)
{
    $workbookXml = importacion_productos_leer_zip_entry($xlsxPath, 'xl/workbook.xml');
    $relsXml = importacion_productos_leer_zip_entry($xlsxPath, 'xl/_rels/workbook.xml.rels');

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

function importacion_productos_obtener_shared_strings($xlsxPath)
{
    $content = importacion_productos_leer_zip_entry($xlsxPath, 'xl/sharedStrings.xml');
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

function importacion_productos_valor_celda_desde_xml($cellXml, $tipo, array $sharedStrings)
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

function importacion_productos_leer_filas($xlsxPath)
{
    $sharedStrings = importacion_productos_obtener_shared_strings($xlsxPath);
    $sheetPath = importacion_productos_obtener_hoja_principal($xlsxPath);
    $sheetXml = importacion_productos_leer_zip_entry($xlsxPath, $sheetPath);
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

                $indice = importacion_productos_columna_a_indice($refMatch[1]);
                $rowValues[$indice] = importacion_productos_valor_celda_desde_xml($cellXml, $tipo, $sharedStrings);
            }
        }

        if ($rowIndex === 0) {
            foreach ($rowValues as $indice => $valor) {
                $headers[$indice] = importacion_productos_normalizar_texto($valor);
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

function importacion_productos_resolver_archivo_fuente()
{
    if (isset($_FILES['archivo_productos']) && is_array($_FILES['archivo_productos'])) {
        $archivo = $_FILES['archivo_productos'];
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

    $candidatos = glob(dirname(__DIR__) . DIRECTORY_SEPARATOR . '*Listado de Productos y Servicios*.xlsx');
    if (!empty($candidatos) && is_file($candidatos[0])) {
        return array(
            'path' => $candidatos[0],
            'source' => 'bundled',
            'label' => basename($candidatos[0]),
        );
    }

    throw new RuntimeException('Debes seleccionar un archivo XLSX o dejar una planilla compatible dentro de la raíz del proyecto.');
}

function importacion_productos_resumen($conexion, array $rows)
{
    $validos = array();
    $omitidos = 0;
    $omitidosInvalidos = 0;

    foreach ($rows as $row) {
        $tipoFila = $row['tipo'] ?? '';
        if (importacion_productos_normalizar_texto($tipoFila) !== 'producto') {
            $omitidos++;
            continue;
        }

        $codigo = mayorista_limpiar_descripcion($row['codigo'] ?? '', 20);
        $marca = mayorista_limpiar_descripcion($row['nombre'] ?? '', 200);
        $precioVenta = mayorista_normalizar_importe($row['precio de venta'] ?? null);

        if ($codigo === '' || $marca === '' || $precioVenta === null) {
            $omitidos++;
            $omitidosInvalidos++;
            continue;
        }

        $validos[] = array('codigo' => $codigo);
    }

    $existentes = array();
    if (!empty($validos)) {
        $codigosUnicos = array_values(array_unique(array_map(function ($item) {
            return $item['codigo'];
        }, $validos)));

        foreach (array_chunk($codigosUnicos, 400) as $bloqueCodigos) {
            $codigosSql = array();
            foreach ($bloqueCodigos as $codigo) {
                $codigosSql[] = "'" . mysqli_real_escape_string($conexion, $codigo) . "'";
            }

            $query = mysqli_query(
                $conexion,
                "SELECT codigo
                 FROM producto
                 WHERE codigo IN (" . implode(', ', $codigosSql) . ")"
            );

            if ($query) {
                while ($rowExistente = mysqli_fetch_assoc($query)) {
                    $existentes[(string) $rowExistente['codigo']] = true;
                }
            }
        }
    }

    $actualizar = 0;
    $insertar = 0;
    foreach ($validos as $itemValido) {
        if (isset($existentes[$itemValido['codigo']])) {
            $actualizar++;
        } else {
            $insertar++;
        }
    }

    return array(
        'filas_validas' => count($validos),
        'insertar_estimado' => $insertar,
        'actualizar_estimado' => $actualizar,
        'omitidos' => $omitidos,
        'omitidos_invalidos' => $omitidosInvalidos,
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    importacion_productos_json(array('success' => false, 'message' => 'Metodo no permitido.'), 405);
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    importacion_productos_json(array('success' => false, 'message' => 'No autorizado.'), 403);
}

if (!($conexion instanceof mysqli)) {
    importacion_productos_json(array('success' => false, 'message' => 'No se pudo establecer la conexion a la base de datos.'), 500);
}

$idUser = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $idUser, array('configuracion'))) {
    importacion_productos_json(array('success' => false, 'message' => 'No tenes permisos para ejecutar esta importacion.'), 403);
}

$token = $_POST['csrf_token'] ?? '';
if (!mayorista_validar_token_importacion_productos($token)) {
    importacion_productos_json(array('success' => false, 'message' => 'Token de seguridad invalido o vencido.'), 403);
}

$previewOnly = !empty($_POST['preview_only']);

if (mayorista_importacion_productos_fue_ejecutada($conexion)) {
    mayorista_invalidar_token_importacion_productos();
    importacion_productos_json(array(
        'success' => true,
        'message' => 'La importacion de productos ya fue ejecutada anteriormente.',
        'already_applied' => true,
    ));
}

if (!mayorista_table_exists($conexion, 'producto')) {
    importacion_productos_json(array('success' => false, 'message' => 'La tabla de productos no existe en la base actual.'), 500);
}

try {
    $archivoFuente = importacion_productos_resolver_archivo_fuente();
    $rows = importacion_productos_leer_filas($archivoFuente['path']);
    if (empty($rows)) {
        throw new RuntimeException('La planilla no contiene filas de productos para importar.');
    }

    if ($previewOnly) {
        $resumen = importacion_productos_resumen($conexion, $rows);
        importacion_productos_json(array(
            'success' => true,
            'message' => 'Previsualización generada correctamente.',
            'source' => $archivoFuente['source'],
            'label' => $archivoFuente['label'],
            'preview' => $resumen,
        ));
    }

    $hasMayorista = mayorista_column_exists($conexion, 'producto', 'precio_mayorista');
    $hasModelo = mayorista_column_exists($conexion, 'producto', 'modelo');
    $hasColor = mayorista_column_exists($conexion, 'producto', 'color');
    $hasTipoMaterial = mayorista_column_exists($conexion, 'producto', 'tipo_material');
    $hasTipo = mayorista_column_exists($conexion, 'producto', 'tipo');
    $hasPrecioBruto = mayorista_column_exists($conexion, 'producto', 'precio_bruto');
    $hasCosto = mayorista_column_exists($conexion, 'producto', 'costo');

    $insertados = 0;
    $actualizados = 0;
    $omitidos = 0;
    $procesados = 0;
    $omitidosInvalidos = 0;

    mysqli_begin_transaction($conexion);

    foreach ($rows as $numeroFila => $row) {
        $tipoFila = $row['tipo'] ?? '';
        if (importacion_productos_normalizar_texto($tipoFila) !== 'producto') {
            $omitidos++;
            continue;
        }

        $codigo = mayorista_limpiar_descripcion($row['codigo'] ?? '', 20);
        $marca = mayorista_limpiar_descripcion($row['nombre'] ?? '', 200);
        $descripcion = mayorista_limpiar_descripcion($row['descripcion'] ?? '', 200);
        $tipoServicio = mayorista_limpiar_descripcion($row['tipo de producto servicio'] ?? '', 120);
        $precioVenta = mayorista_normalizar_importe($row['precio de venta'] ?? null);
        $precioCosto = mayorista_normalizar_importe($row['costo'] ?? null);
        $stockRaw = str_replace(',', '.', trim((string) ($row['stock'] ?? '0')));
        $stock = is_numeric($stockRaw) ? (int) round((float) $stockRaw) : 0;
        $estado = importacion_productos_texto_si($row['activo'] ?? '') ? 1 : 0;

        if ($codigo === '' || $marca === '' || $precioVenta === null) {
            $omitidos++;
            $omitidosInvalidos++;
            continue;
        }

        if ($descripcion === '') {
            $descripcion = $marca . ' ' . $codigo;
        }

        $extraCodigo = importacion_productos_inferir_modelo_y_color($codigo);
        $tipoMaterial = importacion_productos_inferir_tipo_material($descripcion, $marca, $tipoServicio);
        $tipoProducto = importacion_productos_inferir_tipo($tipoServicio);
        $precioBruto = $precioCosto !== null ? $precioCosto : 0;

        $campos = array(
            'descripcion' => "'" . mysqli_real_escape_string($conexion, $descripcion) . "'",
            'precio' => $precioVenta,
            'existencia' => max(0, $stock),
            'usuario_id' => $idUser,
            'estado' => $estado,
            'marca' => "'" . mysqli_real_escape_string($conexion, $marca) . "'",
        );

        if ($hasMayorista) {
            $campos['precio_mayorista'] = $precioVenta;
        }
        if ($hasPrecioBruto) {
            $campos['precio_bruto'] = $precioBruto;
        }
        if ($hasModelo) {
            $modelo = mayorista_limpiar_descripcion($extraCodigo['modelo'] ?? '', 120);
            $campos['modelo'] = $modelo === '' ? 'NULL' : "'" . mysqli_real_escape_string($conexion, $modelo) . "'";
        }
        if ($hasColor) {
            $color = mayorista_limpiar_descripcion($extraCodigo['color'] ?? '', 120);
            $campos['color'] = $color === '' ? 'NULL' : "'" . mysqli_real_escape_string($conexion, $color) . "'";
        }
        if ($hasTipoMaterial) {
            $campos['tipo_material'] = $tipoMaterial === null ? 'NULL' : "'" . mysqli_real_escape_string($conexion, $tipoMaterial) . "'";
        }
        if ($hasTipo) {
            $campos['tipo'] = "'" . mysqli_real_escape_string($conexion, $tipoProducto) . "'";
        }
        if ($hasCosto) {
            $campos['costo'] = 0;
        }

        $codigoEscapado = mysqli_real_escape_string($conexion, $codigo);
        $existente = mysqli_query(
            $conexion,
            "SELECT codproducto
             FROM producto
             WHERE codigo = '$codigoEscapado'
             LIMIT 1"
        );

        if ($existente && mysqli_num_rows($existente) > 0) {
            $sets = array();
            foreach ($campos as $campo => $valor) {
                $sets[] = $campo . ' = ' . $valor;
            }

            $ok = mysqli_query(
                $conexion,
                "UPDATE producto
                 SET " . implode(', ', $sets) . "
                 WHERE codigo = '$codigoEscapado'"
            );
            if (!$ok) {
                throw new RuntimeException('No se pudo actualizar el producto ' . $codigo . ': ' . mysqli_error($conexion));
            }
            $actualizados++;
        } else {
            $camposInsert = array_merge(array('codigo' => "'" . $codigoEscapado . "'"), $campos);
            $ok = mysqli_query(
                $conexion,
                "INSERT INTO producto (" . implode(', ', array_keys($camposInsert)) . ")
                 VALUES (" . implode(', ', array_values($camposInsert)) . ")"
            );
            if (!$ok) {
                throw new RuntimeException('No se pudo insertar el producto ' . $codigo . ': ' . mysqli_error($conexion));
            }
            $insertados++;
        }

        $procesados++;
    }

    if ($procesados <= 0) {
        throw new RuntimeException('No se encontraron filas de tipo Producto para importar.');
    }

    if (!mayorista_marcar_importacion_productos_ejecutada($conexion)) {
        throw new RuntimeException('No se pudo registrar el estado final de la importacion.');
    }

    mysqli_commit($conexion);
    mayorista_invalidar_token_importacion_productos();

    importacion_productos_json(array(
        'success' => true,
        'message' => 'Importacion completada desde ' . $archivoFuente['label'] . '. Insertados: ' . $insertados . ', actualizados: ' . $actualizados . ', omitidos: ' . $omitidos . '.',
        'insertados' => $insertados,
        'actualizados' => $actualizados,
        'omitidos' => $omitidos,
        'omitidos_invalidos' => $omitidosInvalidos,
        'procesados' => $procesados,
        'source' => $archivoFuente['source'],
    ));
} catch (Exception $e) {
    @mysqli_rollback($conexion);
    importacion_productos_json(array(
        'success' => false,
        'message' => 'No se pudo completar la importacion: ' . $e->getMessage(),
    ), 500);
}
