<?php
/**
 * Script de prueba para facturación electrónica
 * USO: Solo para testing, NO ejecutar en producción con ventas reales
 */

// Solo ejecutar en modo CLI o con confirmación
if (php_sapi_name() !== 'cli') {
    echo "Este script solo debe ejecutarse desde línea de comandos<br>";
    echo "Para ejecutar: php src/test_facturacion.php<br>";
    exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/classes/FacturacionElectronica.php';

echo "================================\n";
echo "TEST DE FACTURACIÓN ELECTRÓNICA\n";
echo "================================\n\n";

// 1. Verificar configuración
echo "1. Verificando configuración...\n";
$query_config = mysqli_query($conexion, "SELECT * FROM facturacion_config LIMIT 1");
if ($query_config && mysqli_num_rows($query_config) > 0) {
    $config = mysqli_fetch_assoc($query_config);
    echo "   ✓ Configuración encontrada\n";
    echo "   - CUIT: " . $config['cuit'] . "\n";
    echo "   - Razón Social: " . $config['razon_social'] . "\n";
    echo "   - Punto de Venta: " . $config['punto_venta'] . "\n";
    echo "   - Modo: " . ($config['produccion'] ? 'PRODUCCIÓN' : 'TESTING') . "\n";
    
    if ($config['produccion']) {
        echo "\n   ⚠️  ADVERTENCIA: Estás en modo PRODUCCIÓN\n";
        echo "   Las facturas generadas serán reales y consumirán CAE\n";
    }
} else {
    echo "   ✗ No se encontró configuración\n";
    echo "   Ejecutá el script SQL o configurá desde el panel web\n";
    exit(1);
}

// 2. Verificar certificados
echo "\n2. Verificando certificados...\n";
if (file_exists($config['cert_path'])) {
    echo "   ✓ Certificado (.crt) encontrado\n";
} else {
    echo "   ✗ Certificado (.crt) NO encontrado en: " . $config['cert_path'] . "\n";
}

if (file_exists($config['key_path'])) {
    echo "   ✓ Clave privada (.key) encontrada\n";
} else {
    echo "   ✗ Clave privada (.key) NO encontrada en: " . $config['key_path'] . "\n";
}

// 3. Verificar tablas
echo "\n3. Verificando tablas...\n";
$tablas_requeridas = ['facturas_electronicas', 'tipos_comprobante', 'condiciones_iva'];
foreach ($tablas_requeridas as $tabla) {
    $query = mysqli_query($conexion, "SHOW TABLES LIKE '$tabla'");
    if ($query && mysqli_num_rows($query) > 0) {
        echo "   ✓ Tabla '$tabla' existe\n";
    } else {
        echo "   ✗ Tabla '$tabla' NO existe\n";
    }
}

// 4. Verificar ventas de prueba
echo "\n4. Buscando ventas de prueba...\n";
$query_ventas = mysqli_query($conexion, "SELECT v.id, v.total, c.nombre 
                                         FROM ventas v 
                                         LEFT JOIN cliente c ON v.id_cliente = c.idcliente 
                                         ORDER BY v.id DESC LIMIT 5");

if ($query_ventas && mysqli_num_rows($query_ventas) > 0) {
    echo "   Últimas 5 ventas:\n";
    while ($venta = mysqli_fetch_assoc($query_ventas)) {
        // Verificar si ya tiene factura
        $id_v = $venta['id'];
        $query_fact = mysqli_query($conexion, "SELECT id FROM facturas_electronicas WHERE id_venta = $id_v");
        $tiene_factura = ($query_fact && mysqli_num_rows($query_fact) > 0);
        
        echo "   - Venta #" . $venta['id'] . " - $" . $venta['total'] . " - " . $venta['nombre'];
        echo ($tiene_factura ? " [YA FACTURADA]" : " [SIN FACTURAR]");
        echo "\n";
    }
} else {
    echo "   ✗ No se encontraron ventas\n";
}

// 5. Menú de opciones
echo "\n================================\n";
echo "OPCIONES DE PRUEBA\n";
echo "================================\n";
echo "1. Probar generación de factura (simulada)\n";
echo "2. Consultar tipos de comprobante disponibles\n";
echo "3. Ver configuración completa\n";
echo "4. Salir\n";
echo "\nElegí una opción: ";

$opcion = trim(fgets(STDIN));

switch ($opcion) {
    case '1':
        echo "\nIngresá el ID de la venta a facturar: ";
        $id_venta = trim(fgets(STDIN));
        
        if (!is_numeric($id_venta)) {
            echo "ID inválido\n";
            exit(1);
        }
        
        echo "\n¿Estás seguro? Esta operación generará una factura ";
        echo ($config['produccion'] ? "REAL" : "de PRUEBA");
        echo " (s/n): ";
        $confirmar = trim(fgets(STDIN));
        
        if (strtolower($confirmar) !== 's') {
            echo "Cancelado\n";
            exit(0);
        }
        
        echo "\nGenerando factura...\n";
        try {
            $facturacion = new \App\FacturacionElectronica($conexion);
            $resultado = $facturacion->generarFactura((int)$id_venta);
            
            echo "\n✓ FACTURA GENERADA EXITOSAMENTE\n";
            echo "================================\n";
            echo "CAE: " . $resultado['cae'] . "\n";
            echo "Vencimiento: " . $resultado['vencimiento_cae'] . "\n";
            echo "Tipo: " . $resultado['tipo_comprobante'] . "\n";
            echo "Número: " . sprintf("%04d-%08d", $resultado['punto_venta'], $resultado['numero_comprobante']) . "\n";
            
        } catch (Exception $e) {
            echo "\n✗ ERROR: " . $e->getMessage() . "\n";
        }
        break;
        
    case '2':
        echo "\nTipos de Comprobante:\n";
        $query_tipos = mysqli_query($conexion, "SELECT * FROM tipos_comprobante");
        while ($tipo = mysqli_fetch_assoc($query_tipos)) {
            echo "   " . $tipo['id'] . " - " . $tipo['descripcion'] . " (" . $tipo['codigo'] . ")\n";
        }
        break;
        
    case '3':
        echo "\nConfiguración Completa:\n";
        echo "================================\n";
        foreach ($config as $key => $value) {
            if ($key !== 'id' && $key !== 'updated_at') {
                echo str_pad($key, 20) . ": " . $value . "\n";
            }
        }
        break;
        
    case '4':
        echo "Saliendo...\n";
        exit(0);
        
    default:
        echo "Opción inválida\n";
}

echo "\n";

