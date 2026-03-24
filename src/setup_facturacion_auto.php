<?php
/**
 * Script de Instalación Automática de Facturación Electrónica
 * UN SOLO USO - Se desactiva después de ejecutar exitosamente
 */

// Asegurar que se envía JSON desde el principio
header('Content-Type: application/json; charset=utf-8');

// Configurar para mostrar errores en desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar en pantalla, solo capturar
ini_set('log_errors', 1);

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error fatal en el servidor',
            'errors' => ['Error PHP: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']],
            'log' => [],
            'warnings' => []
        ]);
    }
});

session_start();

// Verificar que la conexión existe
if (!file_exists("../conexion.php")) {
    echo json_encode([
        'success' => false,
        'message' => 'Archivo de conexión no encontrado',
        'errors' => ['El archivo conexion.php no existe'],
        'log' => [],
        'warnings' => []
    ]);
    exit();
}

require_once "../conexion.php";

// Verificar conexión a base de datos
if (!isset($conexion) || !$conexion) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos',
        'errors' => ['No se pudo conectar a MySQL'],
        'log' => [],
        'warnings' => []
    ]);
    exit();
}

// Solo admin puede ejecutar
if (!isset($_SESSION['idUser']) || $_SESSION['idUser'] != 1) {
    $debug_session = isset($_SESSION['idUser']) ? $_SESSION['idUser'] : 'no definido';
    echo json_encode([
        'success' => false, 
        'message' => 'Solo el administrador puede ejecutar la instalación',
        'errors' => ['Acceso denegado. Tu ID de usuario es: ' . $debug_session],
        'log' => [
            '❌ ACCESO DENEGADO',
            'Usuario en sesión: ' . (isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Desconocido'),
            'ID de usuario: ' . $debug_session,
            'Se requiere: ID = 1 (Administrador)'
        ],
        'warnings' => ['Debés estar logueado como administrador principal']
    ]);
    exit();
}

// Verificar si ya fue ejecutado (permitir reinstalación)
$check_installed = @mysqli_query($conexion, "SHOW TABLES LIKE 'facturacion_config'");
$already_installed = ($check_installed && mysqli_num_rows($check_installed) > 0);

// Comentado - permitir reinstalar siempre
/*
if ($already_installed && !isset($_POST['force'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'El sistema de facturación electrónica ya está instalado',
        'already_installed' => true,
        'log' => [
            '⚠️ SISTEMA YA INSTALADO',
            'La tabla facturacion_config ya existe en la base de datos.',
            'Si querés reinstalar, agregá el parámetro force=1'
        ]
    ]);
    exit();
}
*/

// Array para almacenar el log de acciones
$log = [];
$errors = [];
$warnings = [];

$log[] = "🚀 Iniciando instalación del sistema de facturación electrónica...";
$log[] = "📅 Fecha: " . date('Y-m-d H:i:s');
$log[] = "👤 Usuario: " . (isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Admin');
$log[] = "🔑 ID Usuario: " . (isset($_SESSION['idUser']) ? $_SESSION['idUser'] : 'N/A');
$log[] = "";

// =====================================================
// PASO 1: Crear tablas de facturación
// =====================================================
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$log[] = "📊 PASO 1: Creando tablas de base de datos...";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

try {
    // Leer archivo SQL
    $sql_file = __DIR__ . '/../sql/setup_facturacion_electronica.sql';
    $log[] = "📂 Buscando archivo SQL: $sql_file";
    
    if (!file_exists($sql_file)) {
        $errors[] = "❌ No se encontró el archivo SQL: $sql_file";
        $log[] = "❌ Archivo SQL no existe";
        throw new Exception("Archivo SQL no encontrado");
    }
    
    $log[] = "✅ Archivo SQL encontrado";
    $sql_content = file_get_contents($sql_file);
    $log[] = "📏 Tamaño del archivo: " . strlen($sql_content) . " bytes";
    
    // Reemplazar nombre de base de datos si es necesario
    $db_name = getenv('DB_NAME') ?: "c2880275_ventas";
    $log[] = "🗄️ Base de datos destino: $db_name";
    $sql_content = str_replace('c2880275_ventas', $db_name, $sql_content);
    
    // Dividir en queries individuales (mejor manejo)
    $log[] = "📝 Procesando queries SQL...";
    $sql_content = str_replace(["\r\n", "\r"], "\n", $sql_content); // Normalizar saltos de línea
    
    // Remover comentarios de línea
    $lines = explode("\n", $sql_content);
    $clean_lines = [];
    foreach ($lines as $line) {
        $line = trim($line);
        // Saltar líneas vacías y comentarios
        if (empty($line) || strpos($line, '--') === 0 || strpos($line, '#') === 0) {
            continue;
        }
        $clean_lines[] = $line;
    }
    $sql_content = implode("\n", $clean_lines);
    
    // Dividir por punto y coma
    $queries = explode(';', $sql_content);
    $log[] = "📋 Total de queries a procesar: " . count($queries);
    
    $queries_ejecutadas = 0;
    $queries_fallidas = 0;
    $query_num = 0;
    
    foreach ($queries as $query) {
        $query_num++;
        // Limpiar query
        $query = trim($query);
        
        // Saltar queries vacías
        if (empty($query) || strlen($query) < 10) {
            continue;
        }
        
        // Ejecutar query con manejo individual de errores
        try {
            $result = @mysqli_query($conexion, $query);
            if ($result) {
                $queries_ejecutadas++;
            } else {
                $error = mysqli_error($conexion);
                // Ignorar errores esperados
                if (strpos($error, 'already exists') !== false || 
                    strpos($error, 'Duplicate column') !== false ||
                    strpos($error, 'Duplicate entry') !== false ||
                    strpos($error, 'Duplicate key') !== false) {
                    // Tabla/columna ya existe, contar como éxito
                    $queries_ejecutadas++;
                    $log[] = "   ℹ️ Query " . $query_num . ": Ya existe (ignorado)";
                } else if (strpos($error, 'Unknown database') === false) {
                    $queries_fallidas++;
                    $warnings[] = "⚠️ Error en query " . $query_num . ": " . substr($error, 0, 100);
                }
            }
        } catch (Exception $e_query) {
            $error_msg = $e_query->getMessage();
            // Ignorar errores esperados en excepciones
            if (strpos($error_msg, 'already exists') !== false || 
                strpos($error_msg, 'Duplicate column') !== false ||
                strpos($error_msg, 'Duplicate entry') !== false) {
                // Error esperado, contar como éxito
                $queries_ejecutadas++;
                $log[] = "   ℹ️ Query " . $query_num . ": Ya existe (ignorado)";
            } else {
                $queries_fallidas++;
                $warnings[] = "⚠️ Excepción en query " . $query_num . ": " . substr($error_msg, 0, 100);
            }
        }
    }
    
    $log[] = "";
    $log[] = "📊 Resumen de ejecución SQL:";
    $log[] = "   • Queries procesadas: " . count($queries);
    $log[] = "   • Queries ejecutadas: $queries_ejecutadas";
    $log[] = "   • Queries fallidas: $queries_fallidas";
    
    if ($queries_ejecutadas > 0) {
        $log[] = "✅ Base de datos configurada exitosamente";
        
        // Verificar que las tablas principales se crearon
        $log[] = "";
        $log[] = "🔍 Verificando tablas creadas...";
        
        $tabla_config = @mysqli_query($conexion, "SHOW TABLES LIKE 'facturacion_config'");
        $tabla_facturas = @mysqli_query($conexion, "SHOW TABLES LIKE 'facturas_electronicas'");
        $tabla_tipos = @mysqli_query($conexion, "SHOW TABLES LIKE 'tipos_comprobante'");
        
        if ($tabla_config && mysqli_num_rows($tabla_config) > 0) {
            $log[] = "   ✅ Tabla 'facturacion_config' → OK";
        } else {
            $warnings[] = "⚠️ Tabla 'facturacion_config' no encontrada";
            $log[] = "   ❌ Tabla 'facturacion_config' → NO EXISTE";
        }
        
        if ($tabla_facturas && mysqli_num_rows($tabla_facturas) > 0) {
            $log[] = "   ✅ Tabla 'facturas_electronicas' → OK";
        } else {
            $warnings[] = "⚠️ Tabla 'facturas_electronicas' no encontrada";
            $log[] = "   ❌ Tabla 'facturas_electronicas' → NO EXISTE";
        }
        
        if ($tabla_tipos && mysqli_num_rows($tabla_tipos) > 0) {
            $log[] = "   ✅ Tabla 'tipos_comprobante' → OK";
        } else {
            $log[] = "   ⚠️ Tabla 'tipos_comprobante' → NO EXISTE";
        }
    } else {
        $errors[] = "❌ No se ejecutó ninguna query correctamente";
        $log[] = "❌ FALLÓ: No se ejecutó ninguna query";
    }
    
} catch (Exception $e) {
    $error_msg = $e->getMessage();
    
    // Ignorar errores esperados (duplicados)
    if (strpos($error_msg, 'Duplicate column') !== false || 
        strpos($error_msg, 'already exists') !== false ||
        strpos($error_msg, 'Duplicate entry') !== false) {
        $log[] = "ℹ️ Tablas/columnas ya existen (normal en reinstalación)";
    } else {
        // Error real
        $errors[] = "❌ Error en base de datos: " . $error_msg;
        $log[] = "❌ EXCEPCIÓN CAPTURADA: " . $error_msg;
        $log[] = "   Archivo: " . $e->getFile();
        $log[] = "   Línea: " . $e->getLine();
    }
}

// =====================================================
// PASO 2: Crear directorios necesarios
// =====================================================
$log[] = "";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$log[] = "📁 PASO 2: Creando directorios necesarios...";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$directories = [
    __DIR__ . '/../storage/afip_ta',
    __DIR__ . '/../storage/logs',
    __DIR__ . '/../storage/facturas_pdf',
    __DIR__ . '/../certificados-afip'
];

$dirs_creados = 0;
$dirs_existentes = 0;

foreach ($directories as $dir) {
    $dir_name = basename($dir);
    $log[] = "📂 Verificando: $dir_name";
    
    if (!file_exists($dir)) {
        if (@mkdir($dir, 0755, true)) {
            $log[] = "   ✅ Creado exitosamente";
            $dirs_creados++;
        } else {
            $warnings[] = "⚠️ No se pudo crear directorio: $dir_name";
            $log[] = "   ❌ Error al crear";
        }
    } else {
        $log[] = "   ℹ️ Ya existe";
        $dirs_existentes++;
    }
}

$log[] = "";
$log[] = "📊 Directorios: $dirs_creados creados, $dirs_existentes ya existían";

// =====================================================
// PASO 3: Verificar/Instalar Composer
// =====================================================
$log[] = "";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$log[] = "📦 PASO 3: Verificando Composer...";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$composer_path = __DIR__ . '/../composer.phar';
$has_composer = false;

// Verificar si exec() está disponible (en cPanel/hosting compartido suele estar deshabilitado)
$exec_disponible = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));

if (!$exec_disponible) {
    $log[] = "ℹ️ exec() no está disponible en este servidor (hosting compartido)";
    $log[] = "ℹ️ PASO 3: Se omite verificación de Composer (no necesario con implementación actual)";
    $warnings[] = "⚠️ exec() deshabilitado: Si necesitás instalar dependencias Composer, hacelo por SSH o desde el panel de cPanel";
} else {
    $log[] = "🔍 Buscando Composer global...";
    @exec('composer --version 2>&1', $output, $return_code);
    if ($return_code === 0 && !empty($output)) {
        $has_composer = true;
        $log[] = "✅ Composer encontrado (instalación global)";
        $log[] = "   Versión: " . (isset($output[0]) ? $output[0] : 'Desconocida');
    } else {
        $log[] = "❌ Composer no encontrado globalmente";
        $log[] = "🔍 Buscando Composer local en: " . basename(dirname($composer_path));

        if (file_exists($composer_path)) {
            $has_composer = true;
            $log[] = "✅ Composer encontrado (archivo local: composer.phar)";
        } else {
            $log[] = "❌ Composer local no encontrado";
            $log[] = "⏳ Intentando descargar Composer...";
            $installer = @file_get_contents('https://getcomposer.org/installer');
            if ($installer) {
                @file_put_contents(__DIR__ . '/../composer-setup.php', $installer);
                @exec('php ' . __DIR__ . '/../composer-setup.php 2>&1', $composer_output, $composer_code);
                if (file_exists($composer_path)) {
                    $has_composer = true;
                    $log[] = "✅ Composer descargado exitosamente";
                    @unlink(__DIR__ . '/../composer-setup.php');
                } else {
                    $warnings[] = "⚠️ No se pudo descargar Composer automáticamente";
                }
            } else {
                $warnings[] = "⚠️ No se pudo conectar para descargar Composer";
            }
        }
    }
}

// =====================================================
// PASO 4: Instalar dependencias
// =====================================================
$log[] = "";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$log[] = "📚 PASO 4: Instalando dependencias PHP...";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

// Verificar si vendor/autoload.php ya existe (dependencias ya instaladas)
$project_root = realpath(__DIR__ . '/..');
$vendor_exists = $project_root && file_exists($project_root . '/vendor/autoload.php');

if ($vendor_exists) {
    $log[] = "✅ vendor/autoload.php ya existe - dependencias previamente instaladas";
} elseif (!$exec_disponible) {
    $log[] = "ℹ️ PASO 4: exec() no disponible - omitiendo instalación automática de Composer";
    $log[] = "ℹ️ Las clases AFIP (AfipWsaa.php / AfipWsfe.php) no requieren Composer";
    $warnings[] = "⚠️ Si necesitás vendor/: instalá Composer manualmente por SSH en el servidor";
} elseif ($has_composer) {
    $log[] = "✅ Composer disponible, procediendo con instalación...";
    if ($project_root) {
        @chdir($project_root);
        $log[] = "⏳ Ejecutando: composer install --no-dev";
        if (file_exists($composer_path)) {
            @exec('php composer.phar install --no-dev 2>&1', $install_output, $install_code);
        } else {
            @exec('composer install --no-dev 2>&1', $install_output, $install_code);
        }
        if (file_exists($project_root . '/vendor/autoload.php')) {
            $log[] = "✅ Dependencias instaladas correctamente";
        } else {
            $warnings[] = "⚠️ Dependencias no instaladas. Ejecutá manualmente: composer install";
            if (!empty($install_output)) {
                $log[] = "   Output: " . implode(' | ', array_slice($install_output, 0, 3));
            }
        }
    }
} else {
    $log[] = "ℹ️ Composer no disponible - no se instalarán dependencias adicionales";
    $log[] = "ℹ️ Las clases AFIP propias no requieren Composer para funcionar";
}

// =====================================================
// PASO 5: Crear archivo .gitignore si no existe
// =====================================================
$log[] = "";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$log[] = "📝 PASO 5: Verificando .gitignore...";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$gitignore_path = __DIR__ . '/../.gitignore';
$log[] = "🔍 Buscando: .gitignore";

if (!file_exists($gitignore_path)) {
    $log[] = "❌ .gitignore no encontrado, creando...";
    
    $gitignore_example = __DIR__ . '/../.gitignore.example';
    if (file_exists($gitignore_example)) {
        $gitignore_content = file_get_contents($gitignore_example);
        $log[] = "✅ Usando plantilla .gitignore.example";
    } else {
        // Crear básico
        $gitignore_content = "/certificados-afip/\n*.crt\n*.key\n/vendor/\n/storage/\n.facturacion_installed\n";
        $log[] = "📝 Usando .gitignore básico";
    }
    
    if (@file_put_contents($gitignore_path, $gitignore_content)) {
        $log[] = "✅ .gitignore creado exitosamente";
    } else {
        $log[] = "⚠️ No se pudo crear .gitignore";
    }
} else {
    $log[] = "✅ .gitignore ya existe";
}

// =====================================================
// PASO 6: Insertar configuración inicial
// =====================================================
$log[] = "";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$log[] = "⚙️ PASO 6: Configurando datos iniciales...";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

// Primero verificar si la tabla existe
$check_table = @mysqli_query($conexion, "SHOW TABLES LIKE 'facturacion_config'");
if ($check_table && mysqli_num_rows($check_table) > 0) {
    // La tabla existe, verificar si tiene datos
    $check_config = @mysqli_query($conexion, "SELECT COUNT(*) as count FROM facturacion_config");
    if ($check_config) {
        $config_data = mysqli_fetch_assoc($check_config);
        if ($config_data['count'] == 0) {
            // Insertar configuración de ejemplo
            $insert_config = @mysqli_query($conexion, 
                "INSERT INTO facturacion_config 
                (cuit, razon_social, punto_venta, cert_path, key_path, produccion, iva_condition) 
                VALUES 
                (0, 'Configurar datos fiscales', 1, '/ruta/al/certificado.crt', '/ruta/a/la/clave.key', 0, 'IVA Responsable Inscripto')");
            
            if ($insert_config) {
                $log[] = "✅ Configuración inicial creada (debes completar los datos)";
            } else {
                $warnings[] = "⚠️ No se pudo crear configuración inicial: " . mysqli_error($conexion);
            }
        } else {
            $log[] = "ℹ️ Configuración ya existe";
        }
    } else {
        $warnings[] = "⚠️ No se pudo verificar configuración existente";
    }
} else {
    $warnings[] = "⚠️ Tabla facturacion_config no existe aún (se creará con el SQL)";
}

// =====================================================
// PASO 7: Crear archivo de estado de instalación
// =====================================================
$log[] = "";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$log[] = "✅ PASO 7: Finalizando instalación...";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$install_file = __DIR__ . '/../.facturacion_installed';
$timestamp = date('Y-m-d H:i:s');

if (@file_put_contents($install_file, $timestamp)) {
    $log[] = "✅ Archivo de instalación creado (.facturacion_installed)";
    $log[] = "   Timestamp: $timestamp";
} else {
    $warnings[] = "⚠️ No se pudo crear archivo de estado";
    $log[] = "⚠️ No se pudo crear .facturacion_installed";
}

$log[] = "";
$log[] = "🎉 Proceso de instalación finalizado";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

// =====================================================
// RESULTADO FINAL
// =====================================================

$success = empty($errors);

// Agregar resumen final al log
$log[] = "";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$log[] = "📊 RESUMEN FINAL DE INSTALACIÓN";
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$log[] = "   • Total de logs: " . count($log);
$log[] = "   • Advertencias: " . count($warnings);
$log[] = "   • Errores: " . count($errors);
$log[] = "   • Estado final: " . ($success ? "✅ EXITOSO" : "❌ CON ERRORES");
$log[] = "   • Timestamp: " . date('Y-m-d H:i:s');
$log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$result = [
    'success' => $success,
    'log' => $log,
    'warnings' => $warnings,
    'errors' => $errors,
    'next_steps' => [
        '1. Obtener certificados digitales de ARCA',
        '2. Configurar datos en "Configuración de Facturación"',
        '3. Actualizar datos de clientes (CUIT, condición IVA)',
        '4. Probar en modo Testing primero'
    ],
    'debug' => [
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'log_count' => count($log),
        'warnings_count' => count($warnings),
        'errors_count' => count($errors)
    ]
];

if ($success) {
    $result['message'] = '¡Instalación completada exitosamente! 🎉';
} else {
    $result['message'] = 'Instalación completada con errores';
}

// Debug: Guardar log en archivo
$debug_file = __DIR__ . '/../storage/logs/instalacion_' . date('Ymd_His') . '.json';
@file_put_contents($debug_file, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Log final de debug
error_log("INSTALADOR: Enviando respuesta JSON con " . count($result['log']) . " logs");

// Enviar JSON (header ya enviado al inicio del archivo)
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit();
exit();
?>

