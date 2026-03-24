<?php
    // Configurar zona horaria
    date_default_timezone_set('America/Argentina/Buenos_Aires');
    
    // Habilitar reporte de errores para depuración (en producción debería ser 0)
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // No mostrar en pantalla, solo capturar
    ini_set('log_errors', 1);

    // Configuración de base de datos
    // En hosting compartido, 'localhost' suele usar socket Unix automáticamente
    // Si DB_PORT está vacío o es null, mysqli_connect usará socket Unix en lugar de TCP
    $host = getenv('DB_HOST') ?: "localhost"; // Cambiar a localhost para hosting compartido
    $user = getenv('DB_USER') ?: "c2880275_ventas";
    $clave = getenv('DB_PASSWORD') ?: "wego76FIfe";
    $bd = getenv('DB_NAME') ?: "c2880275_ventas";

    // Puerto: null o vacío hace que use socket Unix en 'localhost'
    // Si necesitas TCP explícito, usa un puerto (ej: 3306)
    $db_port = getenv('DB_PORT');
    $port = ($db_port !== false && $db_port !== '') ? (int) $db_port : null;

    // Inicializar variable de conexión
    $conexion = false;
    $error_conexion = null;

    // Intentar conexión mysqli
    // Si $port es null, mysqli_connect usará socket Unix cuando host='localhost'
    try {
        if ($port === null) {
            // Sin puerto: usa socket Unix automático (recomendado para hosting compartido)
            $conexion = @mysqli_connect($host, $user, $clave, $bd);
        } else {
            // Con puerto: usa TCP/IP explícitamente
            $conexion = @mysqli_connect($host, $user, $clave, $bd, $port);
        }
        
        if (!$conexion) {
            $error_conexion = mysqli_connect_error();
            $error_code = mysqli_connect_errno();
            
            // Guardar error para que pueda ser capturado por el script que incluye este archivo
            if (!isset($GLOBALS['db_connection_error'])) {
                $GLOBALS['db_connection_error'] = array(
                    'message' => $error_conexion,
                    'code' => $error_code,
                    'host' => $host,
                    'port' => $port,
                    'database' => $bd,
                    'user' => $user
                );
            }
        } else {
            // Establecer charset solo si la conexión fue exitosa
            if (!mysqli_set_charset($conexion, "utf8")) {
                // Si falla, no abortamos toda la app, pero dejamos constancia
                error_log("No se pudo establecer el charset UTF-8: " . mysqli_error($conexion));
            }
        }
    } catch (Exception $e) {
        $error_conexion = $e->getMessage();
        if (!isset($GLOBALS['db_connection_error'])) {
            $GLOBALS['db_connection_error'] = array(
                'message' => $error_conexion,
                'code' => 'EXCEPTION',
                'exception' => $e->getMessage()
            );
        }
    }
?>
