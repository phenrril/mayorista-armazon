<?php
session_start();
include "../conexion.php";

// Verificar permisos (solo administrador)
if (!isset($_SESSION['idUser']) || $_SESSION['idUser'] != 1) {
    header("Location: permisos.php");
    exit();
}

include_once "includes/header.php";

/**
 * Formatea un CUIT numérico (solo dígitos) a formato XX-XXXXXXXX-X
 */
function formatear_cuit($cuit_numero) {
    $solo_numeros = preg_replace('/\D/', '', (string) $cuit_numero);
    if (strlen($solo_numeros) !== 11) {
        return $solo_numeros;
    }
    return substr($solo_numeros, 0, 2) . '-' . substr($solo_numeros, 2, 8) . '-' . substr($solo_numeros, 10, 1);
}

// Obtener configuración actual
$query_config = mysqli_query($conexion, "SELECT * FROM facturacion_config LIMIT 1");
$config = ($query_config && mysqli_num_rows($query_config) > 0) ? mysqli_fetch_assoc($query_config) : null;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_config'])) {
    // Normalizar CUIT a solo números para que sea compatible con columna BIGINT y modo estricto de MySQL
    $cuit_input = isset($_POST['cuit']) ? $_POST['cuit'] : '';
    $cuit_numerico = preg_replace('/\D/', '', $cuit_input);

    if (strlen($cuit_numerico) !== 11) {
        echo '<script>
            Swal.fire({
                icon: "error",
                title: "CUIT inválido",
                text: "El CUIT debe tener exactamente 11 dígitos (sin contar guiones).",
                confirmButtonText: "Entendido"
            }).then(() => {
                window.history.back();
            });
        </script>';
        include_once "includes/footer.php";
        exit();
    }

    $cuit = mysqli_real_escape_string($conexion, $cuit_numerico);
    $razon_social = mysqli_real_escape_string($conexion, $_POST['razon_social']);
    $punto_venta = intval($_POST['punto_venta']);
    $cert_path = mysqli_real_escape_string($conexion, $_POST['cert_path']);
    $key_path = mysqli_real_escape_string($conexion, $_POST['key_path']);
    $produccion = isset($_POST['produccion']) ? 1 : 0;
    $inicio_actividades = mysqli_real_escape_string($conexion, $_POST['inicio_actividades']);
    $ingresos_brutos = mysqli_real_escape_string($conexion, $_POST['ingresos_brutos']);
    $iva_condition = mysqli_real_escape_string($conexion, $_POST['iva_condition']);
    
    if ($config) {
        // Actualizar
        $sql = "UPDATE facturacion_config SET 
                cuit = '$cuit',
                razon_social = '$razon_social',
                punto_venta = $punto_venta,
                cert_path = '$cert_path',
                key_path = '$key_path',
                produccion = $produccion,
                inicio_actividades = '$inicio_actividades',
                ingresos_brutos = '$ingresos_brutos',
                iva_condition = '$iva_condition',
                updated_at = NOW()
                WHERE id = " . $config['id'];
    } else {
        // Insertar
        $sql = "INSERT INTO facturacion_config 
                (cuit, razon_social, punto_venta, cert_path, key_path, produccion, inicio_actividades, ingresos_brutos, iva_condition) 
                VALUES 
                ('$cuit', '$razon_social', $punto_venta, '$cert_path', '$key_path', $produccion, '$inicio_actividades', '$ingresos_brutos', '$iva_condition')";
    }
    
    if (mysqli_query($conexion, $sql)) {
        echo '<script>
            Swal.fire({
                icon: "success",
                title: "Configuración guardada",
                text: "La configuración de facturación electrónica se guardó correctamente",
                confirmButtonText: "Aceptar"
            }).then(() => {
                window.location.href = "configuracion_facturacion.php";
            });
        </script>';
        include_once "includes/footer.php";
        exit();
    } else {
        echo '<script>
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "No se pudo guardar la configuración. Por favor revisá el log del servidor.",
                confirmButtonText: "Cerrar"
            });
        </script>';
        error_log("Error al guardar configuracion de facturacion: " . mysqli_error($conexion));
    }
}
?>
<div class="config-container">
    <div class="page-header">
        <h2><i class="fas fa-cog mr-3"></i>Configuración de Facturación Electrónica</h2>
        <p class="mb-0 mt-2">Configure los datos para conectarse con ARCA (ex AFIP)</p>
    </div>

    <div class="alert alert-info-modern">
        <h5><i class="fas fa-info-circle mr-2"></i>Información Importante</h5>
        <ul class="mb-0">
            <li>Necesitás tener un <strong>Certificado Digital</strong> (.crt) y una <strong>Clave Privada</strong> (.key)</li>
            <li>Podés obtenerlos desde el sitio de <strong>ARCA</strong> con tu CUIT y Clave Fiscal</li>
            <li>El <strong>Punto de Venta</strong> debe estar habilitado en ARCA para factura electrónica</li>
            <li>Primero configurá en <strong>Modo Testing</strong>, una vez que funcione, pasá a Producción</li>
        </ul>
    </div>

    <div class="card card-modern">
        <div class="card-header card-header-modern">
            <i class="fas fa-building mr-2"></i>Datos del Emisor
        </div>
        <div class="card-body p-4">
            <form method="POST" id="form_config">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cuit"><i class="fas fa-id-card mr-2"></i>CUIT *</label>
                            <input type="text" class="form-control" id="cuit" name="cuit" 
                                   value="<?php echo isset($config['cuit']) ? formatear_cuit($config['cuit']) : ''; ?>" 
                                   placeholder="20-12345678-9" required
                                   maxlength="13">
                            <small class="text-muted">Formato: XX-XXXXXXXX-X</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="razon_social"><i class="fas fa-briefcase mr-2"></i>Razón Social *</label>
                            <input type="text" class="form-control" id="razon_social" name="razon_social" 
                                   value="<?php echo $config['razon_social'] ?? ''; ?>" 
                                   placeholder="Nombre del negocio" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="iva_condition"><i class="fas fa-percent mr-2"></i>Condición IVA *</label>
                            <select class="form-control" id="iva_condition" name="iva_condition" required>
                                <option value="">Seleccione...</option>
                                <option value="IVA Responsable Inscripto" <?php echo (isset($config['iva_condition']) && $config['iva_condition'] == 'IVA Responsable Inscripto') ? 'selected' : ''; ?>>IVA Responsable Inscripto</option>
                                <option value="IVA Exento" <?php echo (isset($config['iva_condition']) && $config['iva_condition'] == 'IVA Exento') ? 'selected' : ''; ?>>IVA Exento</option>
                                <option value="Monotributo" <?php echo (isset($config['iva_condition']) && $config['iva_condition'] == 'Monotributo') ? 'selected' : ''; ?>>Monotributo</option>
                                <option value="Consumidor Final" <?php echo (isset($config['iva_condition']) && $config['iva_condition'] == 'Consumidor Final') ? 'selected' : ''; ?>>Consumidor Final</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="punto_venta"><i class="fas fa-cash-register mr-2"></i>Punto de Venta *</label>
                            <input type="number" class="form-control" id="punto_venta" name="punto_venta" 
                                   value="<?php echo $config['punto_venta'] ?? '1'; ?>" 
                                   placeholder="1" required min="1" max="9999">
                            <small class="text-muted">Número de punto de venta habilitado en ARCA</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="inicio_actividades"><i class="fas fa-calendar mr-2"></i>Inicio de Actividades</label>
                            <input type="date" class="form-control" id="inicio_actividades" name="inicio_actividades" 
                                   value="<?php echo $config['inicio_actividades'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ingresos_brutos"><i class="fas fa-money-bill mr-2"></i>Ingresos Brutos</label>
                            <input type="text" class="form-control" id="ingresos_brutos" name="ingresos_brutos" 
                                   value="<?php echo $config['ingresos_brutos'] ?? ''; ?>" 
                                   placeholder="N° de inscripción">
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3"><i class="fas fa-certificate mr-2"></i>Certificados Digitales</h5>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cert_path"><i class="fas fa-file-certificate mr-2"></i>Ruta Certificado (.crt) *</label>
                            <input type="text" class="form-control" id="cert_path" name="cert_path" 
                                   value="<?php echo $config['cert_path'] ?? ''; ?>" 
                                   placeholder="/ruta/al/certificado.crt" required>
                            <small class="text-muted">Ruta completa al archivo .crt</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="key_path"><i class="fas fa-key mr-2"></i>Ruta Clave Privada (.key) *</label>
                            <input type="text" class="form-control" id="key_path" name="key_path" 
                                   value="<?php echo $config['key_path'] ?? ''; ?>" 
                                   placeholder="/ruta/a/la/clave.key" required>
                            <small class="text-muted">Ruta completa al archivo .key</small>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="produccion" name="produccion" 
                               <?php echo (isset($config['produccion']) && $config['produccion'] == 1) ? 'checked' : ''; ?>>
                        <label class="custom-control-label" for="produccion">
                            <strong>Modo Producción</strong>
                            <br><small class="text-muted">Desactivado = Testing (recomendado para pruebas)</small>
                        </label>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" name="guardar_config" class="btn btn-lg btn-modern btn-success-modern">
                        <i class="fas fa-save mr-2"></i>Guardar Configuración
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Guía rápida -->
    <div class="card card-modern">
        <div class="card-header card-header-modern">
            <i class="fas fa-question-circle mr-2"></i>¿Cómo obtener los certificados?
        </div>
        <div class="card-body p-4">
            <ol>
                <li class="mb-2"><strong>Ingresá a ARCA con CUIT y Clave Fiscal</strong></li>
                <li class="mb-2"><strong>Menú "Administrador de Relaciones de Clave Fiscal"</strong></li>
                <li class="mb-2"><strong>Crear un nuevo certificado para "Web Services"</strong></li>
                <li class="mb-2"><strong>Generá la Solicitud de Certificado (CSR)</strong></li>
                <li class="mb-2"><strong>ARCA te dará un archivo .crt</strong></li>
                <li class="mb-2"><strong>Guardá el .crt y el .key en un directorio seguro del servidor</strong></li>
                <li class="mb-2"><strong>Ingresá las rutas completas en este formulario</strong></li>
            </ol>
        </div>
    </div>
</div>

<?php include_once "includes/footer.php"; ?>

