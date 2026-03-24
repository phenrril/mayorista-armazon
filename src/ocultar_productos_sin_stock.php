<?php
/**
 * Script para ocultar automáticamente todos los productos sin stock en la base de datos
 * Este script puede ejecutarse una vez para actualizar productos existentes
 */

// Usar la conexión centralizada
require_once "../conexion.php";

// Detectar si se llama desde web (AJAX) o desde terminal
$is_web = isset($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST) || php_sapi_name() !== 'cli';

// Contar productos inactivos antes de ocultar
$count_inactivos = mysqli_query($conexion, "SELECT COUNT(*) as total FROM producto WHERE estado = 0");
$stats_inactivos = mysqli_fetch_assoc($count_inactivos);
$total_inactivos_before = $stats_inactivos['total'];

// Ocultar productos sin stock (estado = 0)
$query = mysqli_query($conexion, "UPDATE producto SET estado = 0 WHERE existencia = 0");

if ($query) {
    $filas_afectadas = mysqli_affected_rows($conexion);
    
    // Contar productos inactivos después de ocultar
    $count_inactivos_after = mysqli_query($conexion, "SELECT COUNT(*) as total FROM producto WHERE estado = 0");
    $stats_inactivos_after = mysqli_fetch_assoc($count_inactivos_after);
    $total_inactivos = $stats_inactivos_after['total'];
    
    // Obtener estadísticas después de ocultar
    $stats_query = mysqli_query($conexion, "SELECT 
        COUNT(*) as total_productos,
        SUM(CASE WHEN estado = 1 AND existencia > 0 THEN 1 ELSE 0 END) as activos_con_stock,
        SUM(CASE WHEN existencia = 0 THEN 1 ELSE 0 END) as sin_stock,
        SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) as inactivos
    FROM producto");
    
    $stats = mysqli_fetch_assoc($stats_query);
    
    if ($is_web) {
        // Respuesta para web (JSON)
        $response = [
            'success' => true,
            'message' => "Se ocultaron productos. Total inactivos: $total_inactivos",
            'html' => "
                <div class='alert alert-success'>
                    <h5><i class='fas fa-check-circle'></i> Proceso completado exitosamente</h5>
                    <hr>
                    <p><strong>Productos ocultados en esta operación:</strong> $filas_afectadas</p>
                    <p><strong>Total productos inactivos (ocultos):</strong> $total_inactivos</p>
                    <p><strong>Productos activos con stock:</strong> {$stats['activos_con_stock']}</p>
                    <p><strong>Productos sin stock:</strong> {$stats['sin_stock']}</p>
                </div>
            ",
            'stats' => $stats
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        // Respuesta para terminal
        echo "=== Ocultando Productos Sin Stock e Inactivos ===\n\n";
        echo "✓ Se ocultaron $filas_afectadas productos sin stock.\n";
        echo "✓ Total de productos inactivos (ocultos): $total_inactivos\n";
        echo "\n=== Estadísticas Actuales ===\n";
        echo "Productos activos con stock: {$stats['activos_con_stock']}\n";
        echo "Productos sin stock: {$stats['sin_stock']}\n";
        echo "Productos inactivos (ocultos): {$stats['inactivos']}\n";
        echo "\n=== Proceso Completado ===\n";
    }
    
} else {
    if ($is_web) {
        $response = [
            'success' => false,
            'message' => 'Error al ocultar productos',
            'html' => "<div class='alert alert-danger'>Error al ocultar productos: " . mysqli_error($conexion) . "</div>"
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        echo "✗ Error al ocultar productos: " . mysqli_error($conexion) . "\n";
    }
}

// La conexión será cerrada automáticamente al final del script o puede cerrarse manualmente si es necesario
if (isset($conexion)) {
    mysqli_close($conexion);
}

