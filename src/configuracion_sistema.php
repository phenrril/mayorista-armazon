<?php 
session_start();
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";
if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}
if (!($conexion instanceof mysqli)) {
    include_once "includes/header.php";
    echo '<div class="alert alert-danger mt-4" role="alert">No se pudo establecer la conexión con la base de datos.</div>';
    include_once "includes/footer.php";
    exit();
}
$id_user = $_SESSION['idUser'];
$permiso = "configuracion";
$permiso_escaped = mysqli_real_escape_string($conexion, $permiso);
$sql = mysqli_query($conexion, "SELECT p.*, d.* FROM permisos p INNER JOIN detalle_permisos d ON p.id = d.id_permiso WHERE d.id_usuario = $id_user AND p.nombre = '$permiso_escaped'");
$existe = mysqli_fetch_all($sql);
if (empty($existe) && $id_user != 1){   
    header("location:permisos.php");
    exit();
}
include_once "includes/header.php";
$query = mysqli_query($conexion, "SELECT * FROM configuracion");
$data = mysqli_fetch_assoc($query);
$reset_sistema_habilitado = mayorista_es_admin($id_user);
$reset_sistema_ejecutado = $reset_sistema_habilitado ? mayorista_reset_sistema_fue_ejecutado($conexion) : false;
$reset_sistema_token = $reset_sistema_habilitado ? mayorista_generar_token_reset_sistema() : '';
$migracion_remito_pendiente = !mayorista_schema_remito_productos_listo($conexion);
$migracion_remito_token = $migracion_remito_pendiente ? mayorista_generar_token_migracion_remito() : '';
$migracion_finanzas_pendiente = !mayorista_schema_finanzas_operativas_listo($conexion);
$migracion_finanzas_token = $migracion_finanzas_pendiente ? mayorista_generar_token_migracion_finanzas() : '';
$importacion_productos_pendiente = !mayorista_importacion_productos_fue_ejecutada($conexion);
$importacion_productos_token = $importacion_productos_pendiente ? mayorista_generar_token_importacion_productos() : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_configuracion'])) {
    $alert = '';
    if (empty($_POST['nombre']) || empty($_POST['telefono']) || empty($_POST['email']) || empty($_POST['direccion'])) {
        $alert = '<div class="alert alert-danger" role="alert">
            Todo los campos son obligatorios
        </div>';
    }else{
        $nombre = $_POST['nombre'];
        $telefono = $_POST['telefono'];
        $email = $_POST['email'];
        $direccion = $_POST['direccion'];
        $id = $_POST['id'];
        $update = mysqli_query($conexion, "UPDATE configuracion SET nombre = '$nombre', telefono = '$telefono', email = '$email', direccion = '$direccion' WHERE id = $id");
        if ($update) {
            $alert = '<div class="alert alert-success" role="alert">
            Datos modificado
        </div>';
        }
    }
}
?>

<div class="config-container fade-in-container">
    <!-- Encabezado -->
    <div class="page-header-modern">
        <h2><i class="fas fa-cog mr-2"></i> Configuración del Sistema</h2>
        <p class="mb-0 mt-2"><i class="fas fa-info-circle mr-1"></i> Gestión de datos de la empresa y configuración</p>
    </div>

    <div class="row">
        <div class="col-md-6">
            <!-- Formulario Datos Empresa -->
            <div class="card card-modern">
                <div class="card-header-modern">
                    <i class="fas fa-building mr-2"></i> Datos de la Empresa
                </div>
                <div class="card-body card-body-modern">
                    <form action="" method="post">
                        <div class="form-group">
                            <label><i class="fas fa-building mr-2 text-primary"></i> Nombre *</label>
                            <input type="hidden" name="id" value="<?php echo $data['id'] ?>">
                            <input type="text" name="nombre" class="form-control form-control-modern" value="<?php echo htmlspecialchars($data['nombre']); ?>" placeholder="Nombre de la Empresa" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-phone mr-2 text-success"></i> Teléfono *</label>
                            <input type="text" name="telefono" class="form-control form-control-modern" value="<?php echo htmlspecialchars($data['telefono']); ?>" placeholder="Teléfono de la Empresa" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope mr-2 text-info"></i> Correo Electrónico *</label>
                            <input type="email" name="email" class="form-control form-control-modern" value="<?php echo htmlspecialchars($data['email']); ?>" placeholder="Correo de la Empresa" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt mr-2 text-warning"></i> Dirección *</label>
                            <input type="text" name="direccion" class="form-control form-control-modern" value="<?php echo htmlspecialchars($data['direccion']); ?>" placeholder="Dirección de la Empresa" required>
                        </div>
                        <?php echo isset($alert) ? $alert : ''; ?>
                        <div class="text-center">
                            <button type="submit" name="guardar_configuracion" value="1" class="btn btn-modern btn-modern-primary">
                                <i class="fas fa-save mr-2"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <?php if ($migracion_remito_pendiente) { ?>
            <div class="card card-modern mb-4" id="cardMigracionRemito">
                <div class="card-header-modern card-header-modern-warning">
                    <i class="fas fa-database mr-2"></i> Migración Remito / Clientes / Productos
                </div>
                <div class="card-body card-body-modern">
                    <p class="mb-3">
                        <i class="fas fa-info-circle text-info mr-2"></i>
                        Ejecutá una sola vez la migración para habilitar los nuevos campos de clientes, productos, ventas y el nuevo remito.
                    </p>
                    <div id="resultado-migracion-remito" class="mb-3"></div>
                    <button
                        type="button"
                        class="btn btn-modern btn-modern-warning"
                        id="btnMigracionRemito"
                        data-endpoint="ejecutar_migracion_remito.php"
                        data-token="<?php echo htmlspecialchars($migracion_remito_token, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <i class="fas fa-play mr-2"></i> Ejecutar migración única
                    </button>
                    <small class="text-muted d-block mt-3">
                        El botón se ocultará automáticamente cuando la migración quede aplicada correctamente.
                    </small>
                </div>
            </div>
            <?php } ?>

            <?php if ($migracion_finanzas_pendiente) { ?>
            <div class="card card-modern mb-4" id="cardMigracionFinanzas">
                <div class="card-header-modern card-header-modern-warning">
                    <i class="fas fa-coins mr-2"></i> Migración Finanzas Operativas
                </div>
                <div class="card-body card-body-modern">
                    <p class="mb-3">
                        <i class="fas fa-info-circle text-info mr-2"></i>
                        Ejecutá una sola vez esta migración para habilitar proveedores, compromisos financieros, cheques y pagos parciales.
                    </p>
                    <div id="resultado-migracion-finanzas" class="mb-3"></div>
                    <button
                        type="button"
                        class="btn btn-modern btn-modern-warning"
                        id="btnMigracionFinanzas"
                        data-endpoint="ejecutar_migracion_finanzas.php"
                        data-token="<?php echo htmlspecialchars($migracion_finanzas_token, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <i class="fas fa-play mr-2"></i> Ejecutar migración única
                    </button>
                    <small class="text-muted d-block mt-3">
                        El botón se ocultará automáticamente cuando la estructura financiera quede aplicada correctamente.
                    </small>
                </div>
            </div>
            <?php } ?>

            <?php if ($importacion_productos_pendiente) { ?>
            <div class="card card-modern mb-4" id="cardImportacionProductos">
                <div class="card-header-modern card-header-modern-warning">
                    <i class="fas fa-file-excel mr-2"></i> Importación inicial de productos
                </div>
                <div class="card-body card-body-modern">
                    <p class="mb-3">
                        <i class="fas fa-info-circle text-info mr-2"></i>
                        Seleccioná la planilla XLSX de productos para importar todo el catálogo una sola vez. Si un código ya existe, se actualizará.
                    </p>
                    <div class="form-group">
                        <label for="archivoImportacionProductos">Archivo XLSX</label>
                        <input
                            type="file"
                            class="form-control-file"
                            id="archivoImportacionProductos"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        >
                    </div>
                    <div id="resultado-importacion-productos" class="mb-3"></div>
                    <button
                        type="button"
                        class="btn btn-modern btn-modern-warning"
                        id="btnImportacionProductos"
                        data-endpoint="importar_productos_xlsx.php"
                        data-token="<?php echo htmlspecialchars($importacion_productos_token, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <i class="fas fa-upload mr-2"></i> Importar productos una sola vez
                    </button>
                    <small class="text-muted d-block mt-3">
                        Antes de ejecutar se mostrará una previsualización y se pedirá escribir <code>IMPORTAR PRODUCTOS</code>.
                    </small>
                </div>
            </div>
            <?php } ?>

            <!-- Gestión de Productos -->
            <div class="card card-modern">
                <div class="card-header-modern card-header-modern-warning">
                    <i class="fas fa-box-open mr-2"></i> Gestión de Productos
                </div>
                <div class="card-body card-body-modern">
                    <p class="mb-4"><i class="fas fa-info-circle text-info mr-2"></i>Ocultar automáticamente todos los productos sin stock en la base de datos.</p>
                    <div id="resultado-ocultar" class="mb-3"></div>
                    <button type="button" class="btn btn-modern btn-modern-warning" id="btnOcultarProductos">
                        <i class="fas fa-eye-slash mr-2"></i> Ocultar Productos Sin Stock
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($reset_sistema_habilitado) { ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card card-modern border border-danger">
                <div class="card-header-modern bg-danger text-white">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Zona peligrosa
                </div>
                <div class="card-body card-body-modern">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="text-danger mb-3">
                                <i class="fas fa-radiation mr-2"></i> Reset definitivo del sistema
                            </h5>
                            <p class="mb-2">
                                Esta acción elimina todos los datos operativos, recrea un único usuario administrador y deja el reset bloqueado para siempre.
                            </p>
                            <ul class="mb-3">
                                <li>Borra clientes, productos, ventas, usuarios y movimientos operativos.</li>
                                <li>Recrea únicamente el usuario <strong>admin</strong>.</li>
                                <li>Exige escribir la frase exacta <code>ELIMINAR TODO</code>.</li>
                            </ul>
                            <?php if ($reset_sistema_ejecutado) { ?>
                            <div class="alert alert-success mb-0" role="alert">
                                <i class="fas fa-lock mr-2"></i> El reset ya fue ejecutado y quedó bloqueado permanentemente.
                            </div>
                            <?php } else { ?>
                            <div class="alert alert-danger mb-0" role="alert">
                                <i class="fas fa-shield-alt mr-2"></i> Disponible solo para el administrador principal (<code>idusuario = 1</code>) y protegido por validación de servidor.
                            </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 text-center d-flex flex-column justify-content-center mt-3 mt-md-0">
                            <button
                                type="button"
                                class="btn btn-lg btn-danger"
                                id="btnResetSistema"
                                data-endpoint="reset_sistema.php"
                                data-token="<?php echo htmlspecialchars($reset_sistema_token, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $reset_sistema_ejecutado ? 'disabled' : ''; ?>
                            >
                                <i class="fas fa-trash-alt mr-2"></i>
                                <?php echo $reset_sistema_ejecutado ? 'Reset bloqueado' : 'Ejecutar reset único'; ?>
                            </button>
                            <small class="text-muted mt-3">
                                Acción irreversible y de un solo uso.
                            </small>
                        </div>
                    </div>
                    <div id="resultado-reset-sistema" class="mt-4"></div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php 
    // Verificar si el sistema de facturación ya está instalado
    $facturacion_instalada = file_exists(__DIR__ . '/../.facturacion_installed');
    $tabla_existe = mysqli_query($conexion, "SHOW TABLES LIKE 'facturacion_config'");
    $sistema_instalado = $facturacion_instalada || ($tabla_existe && mysqli_num_rows($tabla_existe) > 0);
    ?>
    
    <!-- Instalación de Facturación Electrónica -->
    <div class="row mt-4">
        <div class="col-12">
            <?php if ($sistema_instalado) { ?>
            <!-- Sistema ya instalado - Mostrar estado y opción de reinstalar -->
            <div class="alert setup-status-alert alert-success mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5><i class="fas fa-check-circle mr-2"></i> Sistema de Facturación Electrónica Instalado</h5>
                        <p class="mb-0">El sistema de facturación electrónica está instalado y listo para usar.</p>
                    </div>
                    <a href="configuracion_facturacion.php" class="btn btn-light">
                        <i class="fas fa-cog mr-2"></i> Configurar
                    </a>
                </div>
            </div>
            <?php } ?>
            
            <!-- Card de instalación (siempre visible) -->
            <div class="card card-modern setup-install-card <?php echo $sistema_instalado ? 'is-installed' : 'is-pending'; ?>">
                <div class="card-header-modern <?php echo $sistema_instalado ? '' : 'card-header-modern-warning'; ?>">
                    <i class="fas fa-<?php echo $sistema_instalado ? 'sync' : 'bolt'; ?> mr-2"></i> 
                    <?php echo $sistema_instalado ? 'Reinstalar / Verificar' : 'Instalación Automática'; ?> - Facturación Electrónica ARCA/AFIP
                </div>
                <div class="card-body card-body-modern">
                    <div class="row">
                        <div class="col-md-8">
                            <h5>
                                <i class="fas fa-<?php echo $sistema_instalado ? 'redo' : 'magic'; ?> mr-2 text-primary"></i> 
                                <?php echo $sistema_instalado ? 'Reinstalación o Verificación' : 'Instalación de Un Solo Click'; ?>
                            </h5>
                            <p class="mb-3">Este botón <?php echo $sistema_instalado ? 'reinstalará o verificará' : 'instalará y configurará automáticamente'; ?> todo lo necesario para la facturación electrónica:</p>
                            <ul class="mb-3">
                                <li><i class="fas fa-check-circle text-success mr-2"></i> Crear/Verificar tablas en la base de datos</li>
                                <li><i class="fas fa-check-circle text-success mr-2"></i> Instalar Composer (si no está instalado)</li>
                                <li><i class="fas fa-check-circle text-success mr-2"></i> Descargar dependencias necesarias</li>
                                <li><i class="fas fa-check-circle text-success mr-2"></i> Crear directorios requeridos</li>
                                <li><i class="fas fa-check-circle text-success mr-2"></i> Configuración inicial del sistema</li>
                            </ul>
                            <?php if ($sistema_instalado) { ?>
                            <div class="alert alert-warning setup-note is-warning">
                                <i class="fas fa-exclamation-triangle mr-2"></i> <strong>Atención:</strong> Solo reinstalá si tuviste problemas en la instalación anterior o si borraste tablas/directorios.
                            </div>
                            <?php } else { ?>
                            <div class="alert alert-info setup-note is-info">
                                <i class="fas fa-info-circle mr-2"></i> <strong>Nota:</strong> Este proceso instalará todo lo necesario para la facturación electrónica.
                            </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 text-center d-flex flex-column justify-content-center">
                            <button type="button" class="btn btn-lg btn-modern btn-modern-primary setup-cta mb-3" id="btnInstalarFacturacion">
                                <i class="fas fa-<?php echo $sistema_instalado ? 'sync' : 'rocket'; ?> mr-2"></i> 
                                <?php echo $sistema_instalado ? 'Reinstalar' : 'Instalar Ahora'; ?>
                            </button>
                            <small class="text-muted">
                                <i class="fas fa-clock mr-1"></i> Tiempo estimado: 2-3 minutos
                            </small>
                        </div>
                    </div>
                    
                    <!-- Área de resultados (siempre visible para debug) -->
                    <div id="resultado-instalacion" class="mt-4">
                        <hr>
                        <h6><i class="fas fa-terminal mr-2"></i> Log de Instalación:</h6>
                        <div id="log-instalacion" class="setup-log">
                            <span class="setup-log-placeholder">Esperando instalación...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once "includes/footer.php"; ?>

<script>
// IMPORTANTE: Este script se ejecuta DESPUÉS del footer donde se cargan jQuery y SweetAlert2

// Esperar a que TODO esté cargado
window.addEventListener('load', function() {
    console.log('🎯 Window load event ejecutado');
    
    // Debug: Verificar que jQuery esté cargado
    if (typeof jQuery === 'undefined') {
        console.error('❌ jQuery no está cargado');
        alert('Error: jQuery no está cargado. Revisá la consola.');
        return;
    } else {
        console.log('✅ jQuery cargado:', jQuery.fn.jquery);
    }

    // Debug: Verificar que SweetAlert2 esté cargado
    if (typeof Swal === 'undefined') {
        console.error('❌ SweetAlert2 no está cargado');
        alert('Error: SweetAlert2 no está cargado.');
        return;
    } else {
        console.log('✅ SweetAlert2 cargado');
    }

    initResetSistema();
    initMigracionRemito();
    initMigracionFinanzas();
    initImportacionProductos();
    initInstalador();
});

function initResetSistema() {
    const $button = $('#btnResetSistema');
    if ($button.length === 0) {
        return;
    }

    $button.on('click', function() {
        Swal.fire({
            title: 'Reset definitivo del sistema',
            html: `
                <div class="text-left">
                    <p>Esta acción va a borrar los datos operativos y dejar un único usuario administrador.</p>
                    <p class="text-danger mb-2"><strong>No se puede deshacer.</strong></p>
                    <p class="mb-0">Para continuar escribí exactamente: <code>ELIMINAR TODO</code></p>
                </div>
            `,
            icon: 'warning',
            input: 'text',
            inputLabel: 'Frase de confirmación',
            inputPlaceholder: 'ELIMINAR TODO',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt mr-2"></i>Ejecutar reset',
            cancelButtonText: 'Cancelar',
            allowOutsideClick: false,
            inputValidator: function(value) {
                if (value !== 'ELIMINAR TODO') {
                    return 'La frase no coincide exactamente.';
                }
            }
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            ejecutarResetSistema($button, result.value);
        });
    });
}

function ejecutarResetSistema($button, confirmacion) {
    const endpoint = $button.data('endpoint');
    const token = $button.data('token');
    const textoOriginal = '<i class="fas fa-trash-alt mr-2"></i>Ejecutar reset único';

    $button.prop('disabled', true);
    $button.html('<i class="fas fa-spinner fa-spin mr-2"></i>Ejecutando...');
    $('#resultado-reset-sistema').html('');

    $.ajax({
        url: endpoint,
        type: 'POST',
        dataType: 'json',
        timeout: 180000,
        data: {
            confirmacion: confirmacion,
            csrf_token: token
        },
        success: function(response) {
            const success = !!(response && response.success);

            if (!success) {
                const message = response && response.message ? response.message : 'No se pudo ejecutar el reset.';
                $('#resultado-reset-sistema').html('<div class="alert alert-danger" role="alert">' + message + '</div>');
                $button.prop('disabled', !!(response && response.blocked));
                $button.html(response && response.blocked ? '<i class="fas fa-lock mr-2"></i>Reset bloqueado' : textoOriginal);

                Swal.fire({
                    icon: 'error',
                    title: 'Reset no ejecutado',
                    text: message,
                    confirmButtonColor: '#d33'
                });
                return;
            }

            $('#resultado-reset-sistema').html('<div class="alert alert-success" role="alert">' + response.message + '</div>');
            $button.html('<i class="fas fa-lock mr-2"></i>Reset completado');

            Swal.fire({
                icon: 'success',
                title: 'Reset completado',
                text: response.message,
                allowOutsideClick: false,
                confirmButtonColor: '#198754'
            }).then(() => {
                window.location.href = response.redirect || '../';
            });
        },
        error: function(jqXHR, textStatus) {
            let message = 'Error al ejecutar el reset.';
            if (textStatus === 'timeout') {
                message = 'El reset tardó demasiado y no se pudo confirmar el resultado.';
            } else if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                message = jqXHR.responseJSON.message;
            }

            $('#resultado-reset-sistema').html('<div class="alert alert-danger" role="alert">' + message + '</div>');
            $button.prop('disabled', false);
            $button.html(textoOriginal);

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                confirmButtonColor: '#d33'
            });
        }
    });
}

function initInstalador() {
    console.log('✅ Inicializando instalador');
    
    // Debug: Verificar que el botón existe
    if ($('#btnInstalarFacturacion').length > 0) {
        console.log('✅ Botón #btnInstalarFacturacion encontrado');
    } else {
        console.log('❌ Botón #btnInstalarFacturacion NO encontrado (puede estar oculto porque ya está instalado)');
    }
    console.log('✅ Document ready ejecutado');
    
    // Debug: Verificar que el botón existe
    if ($('#btnInstalarFacturacion').length > 0) {
        console.log('✅ Botón #btnInstalarFacturacion encontrado');
    } else {
        console.log('❌ Botón #btnInstalarFacturacion NO encontrado (puede estar oculto porque ya está instalado)');
    }
    
    // Ocultar productos sin stock
    $('#btnOcultarProductos').click(function() {
        // Deshabilitar el botón mientras se procesa
        $(this).prop('disabled', true);
        $(this).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');
        
        // Limpiar resultado anterior
        $('#resultado-ocultar').html('');
        
        // Hacer la petición AJAX
        $.ajax({
            url: 'ocultar_productos_sin_stock.php',
            type: 'POST',
            data: {},
            dataType: 'json',
            success: function(response) {
                try {
                    if (!response || response.success === false) {
                        console.error('Error en respuesta de ocultar_productos_sin_stock:', response);
                    } else {
                        console.log('Éxito ocultar_productos_sin_stock:', response);
                    }
                } catch (e) {
                    console.error('Excepción procesando respuesta:', e);
                }
                $('#resultado-ocultar').html(response.html);
                $('#btnOcultarProductos').prop('disabled', false);
                $('#btnOcultarProductos').html('<i class="fas fa-eye-slash"></i> Ocultar Productos Sin Stock');
                
                // Si fue exitoso, mostrar SweetAlert
                if (response.success) {
                    Swal.fire({
                        position: 'center',
                        toast: false,
                        icon: 'success',
                        title: 'Productos Ocultados',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error ocultar_productos_sin_stock:', { status: jqXHR.status, textStatus: textStatus, error: errorThrown, responseText: jqXHR.responseText });
                $('#resultado-ocultar').html('<div class="alert alert-danger">Error al procesar la solicitud</div>');
                $('#btnOcultarProductos').prop('disabled', false);
                $('#btnOcultarProductos').html('<i class="fas fa-eye-slash"></i> Ocultar Productos Sin Stock');
            }
        });
    });
    
    // =====================================================
    // INSTALACIÓN DE FACTURACIÓN ELECTRÓNICA
    // =====================================================
    $('#btnInstalarFacturacion').click(function() {
        console.log('🚀 Click en botón Instalar detectado!');
        
        // Verificar SweetAlert2
        if (typeof Swal === 'undefined') {
            alert('Error: SweetAlert2 no está cargado. No se puede mostrar el modal.');
            console.error('❌ SweetAlert2 no disponible');
            return;
        }
        
        // Confirmación antes de instalar
        Swal.fire({
            title: '¿Instalar Sistema de Facturación?',
            html: `
                <div class="text-left">
                    <p>Este proceso instalará:</p>
                    <ul>
                        <li>Tablas en la base de datos</li>
                        <li>Composer y dependencias</li>
                        <li>Directorios necesarios</li>
                        <li>Configuración inicial</li>
                    </ul>
                    <p class="text-warning"><i class="fas fa-exclamation-triangle mr-2"></i><strong>Este proceso puede tomar 2-3 minutos.</strong></p>
                    <p class="text-info"><i class="fas fa-info-circle mr-2"></i>Solo puede ejecutarse una vez.</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            cancelButtonColor: '#d33',
            confirmButtonText: '<i class="fas fa-rocket mr-2"></i>Sí, Instalar',
            cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                ejecutarInstalacion();
            }
        });
    });
    
    function ejecutarInstalacion() {
        console.log('🚀 Iniciando función ejecutarInstalacion()');
        
        // Deshabilitar botón
        $('#btnInstalarFacturacion').prop('disabled', true);
        $('#btnInstalarFacturacion').html('<i class="fas fa-spinner fa-spin mr-2"></i> Instalando...');
        
        // Mostrar área de resultados
        console.log('📋 Mostrando área de resultados');
        $('#resultado-instalacion').show(); // Asegurar que sea visible
        $('#log-instalacion').html('<div class="text-center" style="color: #00ff00;"><i class="fas fa-spinner fa-spin fa-3x mb-3"></i><br><br>⏳ Iniciando instalación...<br><br>Por favor esperá...</div>');
        
        console.log('🌐 Enviando petición AJAX a setup_facturacion_auto.php');
        
        // Ejecutar instalación
        $.ajax({
            url: 'setup_facturacion_auto.php',
            type: 'POST',
            data: {},
            dataType: 'json',
            timeout: 180000, // 3 minutos
            beforeSend: function() {
                console.log('📤 Petición enviada al servidor');
            },
            success: function(response) {
                console.log('✅ Respuesta recibida del servidor:', response);
                
                // Verificar si la respuesta es válida
                if (!response) {
                    console.error('❌ Respuesta vacía del servidor');
                    $('#log-instalacion').html('<span style="color: #ff0000;">❌ Error: Respuesta vacía del servidor</span>');
                    return;
                }
                
                // Construir log HTML
                let logHtml = '';
                
                // Log de acciones
                if (response.log && response.log.length > 0) {
                    console.log('📝 Log recibido:', response.log.length, 'líneas');
                    response.log.forEach(function(item) {
                        logHtml += item + '<br>';
                    });
                } else {
                    console.warn('⚠️ No hay log en la respuesta');
                    logHtml += '<span style="color: #ffa500;">⚠️ No se recibió log del servidor</span><br>';
                }
                
                // Warnings
                if (response.warnings && response.warnings.length > 0) {
                    console.log('⚠️ Warnings:', response.warnings.length);
                    logHtml += '<br><span style="color: #ffa500;">━━━ ADVERTENCIAS ━━━</span><br>';
                    response.warnings.forEach(function(item) {
                        logHtml += '<span style="color: #ffa500;">' + item + '</span><br>';
                    });
                }
                
                // Errores
                if (response.errors && response.errors.length > 0) {
                    console.log('❌ Errores:', response.errors.length);
                    logHtml += '<br><span style="color: #ff0000;">━━━ ERRORES ━━━</span><br>';
                    response.errors.forEach(function(item) {
                        logHtml += '<span style="color: #ff0000;">' + item + '</span><br>';
                    });
                }
                
                // Próximos pasos
                if (response.next_steps && response.next_steps.length > 0) {
                    logHtml += '<br><span style="color: #00ffff;">━━━ PRÓXIMOS PASOS ━━━</span><br>';
                    response.next_steps.forEach(function(item) {
                        logHtml += '<span style="color: #00ffff;">' + item + '</span><br>';
                    });
                }
                
                // Si no hay nada que mostrar
                if (!logHtml || logHtml.trim() === '') {
                    logHtml = '<span style="color: #ffa500;">⚠️ No se recibió información del servidor</span><br>';
                    logHtml += '<span style="color: #ffffff;">Respuesta completa: ' + JSON.stringify(response) + '</span>';
                }
                
                console.log('📋 Actualizando log HTML');
                $('#log-instalacion').html(logHtml);
                
                // Scroll automático al final
                $('#log-instalacion').scrollTop($('#log-instalacion')[0].scrollHeight);
                
                // Mostrar resultado
                console.log('🎯 Mostrando resultado. Success:', response.success);
                
                if (response.success) {
                    // Agregar mensaje final al log
                    $('#log-instalacion').append('<br><br><span style="color: #00ff00; font-size: 1.2rem;">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span><br>');
                    $('#log-instalacion').append('<span style="color: #00ff00; font-size: 1.2rem;">✅ ¡INSTALACIÓN COMPLETADA! 🎉</span><br>');
                    $('#log-instalacion').append('<span style="color: #00ff00; font-size: 1.2rem;">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span><br>');
                    
                    Swal.fire({
                        icon: 'success',
                        title: '¡Instalación Completada!',
                        html: `
                            <div class="text-left">
                                <p>${response.message}</p>
                                <hr>
                                <h6>Próximos pasos:</h6>
                                <ol>
                                    ${response.next_steps ? response.next_steps.map(step => '<li>' + step + '</li>').join('') : '<li>Configurar el sistema</li>'}
                                </ol>
                            </div>
                        `,
                        confirmButtonColor: '#667eea',
                        confirmButtonText: 'Entendido'
                    }).then(() => {
                        // Cambiar botón sin recargar
                        $('#btnInstalarFacturacion').html('<i class="fas fa-sync mr-2"></i> Reinstalar');
                        $('#btnInstalarFacturacion').prop('disabled', false);
                        
                        // Mostrar mensaje de éxito permanente
                        $('#log-instalacion').prepend('<span style="color: #00ff00; font-size: 1.1rem;">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span><br>');
                    });
                } else {
                    console.log('⚠️ Instalación con advertencias o errores');
                    
                    // Agregar mensaje al log
                    $('#log-instalacion').append('<br><br><span style="color: #ffa500; font-size: 1.2rem;">⚠️ Instalación completada con advertencias</span><br>');
                    
                    Swal.fire({
                        icon: 'warning',
                        title: 'Instalación Completada con Advertencias',
                        html: `
                            <div class="text-left">
                                <p>${response.message || 'Hubo algunos problemas'}</p>
                                <p class="text-muted">Revisá el log para más detalles.</p>
                            </div>
                        `,
                        confirmButtonColor: '#ffa500'
                    });
                    
                    $('#btnInstalarFacturacion').prop('disabled', false);
                    $('#btnInstalarFacturacion').html('<i class="fas fa-rocket mr-2"></i> Reintentar Instalación');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error AJAX completo:', { 
                    status: jqXHR.status, 
                    textStatus: textStatus, 
                    error: errorThrown,
                    responseText: jqXHR.responseText 
                });
                
                let errorMsg = 'Error al ejecutar la instalación';
                let detalles = '';
                
                if (textStatus === 'timeout') {
                    errorMsg = 'La instalación tardó demasiado. Es posible que se haya completado. Recargá la página para verificar.';
                    detalles = 'Timeout después de 3 minutos';
                } else if (jqXHR.status === 500) {
                    errorMsg = 'Error interno del servidor (500)';
                    detalles = jqXHR.responseText || 'Ver consola del navegador para más detalles';
                } else if (jqXHR.status === 404) {
                    errorMsg = 'Archivo no encontrado (404)';
                    detalles = 'El archivo setup_facturacion_auto.php no existe';
                } else {
                    errorMsg = 'Error ' + jqXHR.status + ': ' + textStatus;
                    detalles = jqXHR.responseText || errorThrown;
                }
                
                // Intentar parsear respuesta JSON
                try {
                    let jsonResponse = JSON.parse(jqXHR.responseText);
                    if (jsonResponse.errors && jsonResponse.errors.length > 0) {
                        detalles = jsonResponse.errors.join('<br>');
                    }
                    if (jsonResponse.message) {
                        errorMsg = jsonResponse.message;
                    }
                } catch(e) {
                    // No es JSON, usar responseText directamente
                }
                
                $('#log-instalacion').html(
                    '<span style="color: #ff0000;">❌ ERROR: ' + errorMsg + '</span><br><br>' +
                    '<span style="color: #ffa500;">Detalles:</span><br>' +
                    '<span style="color: #ffffff;">' + detalles + '</span><br><br>' +
                    '<span style="color: #00ffff;">💡 Sugerencia: Ejecutá manualmente el script SQL desde phpMyAdmin</span>'
                );
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error en Instalación',
                    html: '<div class="text-left"><p>' + errorMsg + '</p><p class="text-muted small">' + detalles + '</p></div>',
                    confirmButtonColor: '#d33',
                    footer: '<a href="sql/README.md" target="_blank">Ver guía de instalación manual</a>'
                });
                
                $('#btnInstalarFacturacion').prop('disabled', false);
                $('#btnInstalarFacturacion').html('<i class="fas fa-rocket mr-2"></i> Reintentar Instalación');
            }
        });
    }
}

function initMigracionRemito() {
    const $button = $('#btnMigracionRemito');
    if ($button.length === 0) {
        return;
    }

    $button.on('click', function() {
        Swal.fire({
            title: '¿Ejecutar migración?',
            html: `
                <div class="text-left">
                    <p>Esto actualizará la base para habilitar:</p>
                    <ul>
                        <li>Nuevos campos de clientes</li>
                        <li>Nuevos atributos de productos</li>
                        <li>Modo de despacho en ventas</li>
                        <li>Nuevo formato de remito</li>
                    </ul>
                    <p class="mb-0 text-warning"><strong>La acción es de un solo uso desde esta UI.</strong></p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-play mr-2"></i>Ejecutar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#c48b2f',
            cancelButtonColor: '#6c757d',
            allowOutsideClick: false
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            ejecutarMigracionRemito($button);
        });
    });
}

function ejecutarMigracionRemito($button) {
    const endpoint = $button.data('endpoint');
    const token = $button.data('token');
    const htmlOriginal = '<i class="fas fa-play mr-2"></i> Ejecutar migración única';

    $button.prop('disabled', true);
    $button.html('<i class="fas fa-spinner fa-spin mr-2"></i> Ejecutando...');
    $('#resultado-migracion-remito').html('');

    $.ajax({
        url: endpoint,
        type: 'POST',
        dataType: 'json',
        data: {
            csrf_token: token
        },
        success: function(response) {
            if (!response || !response.success) {
                const message = response && response.message ? response.message : 'No se pudo ejecutar la migración.';
                $('#resultado-migracion-remito').html('<div class="alert alert-danger" role="alert">' + message + '</div>');
                $button.prop('disabled', false).html(htmlOriginal);
                Swal.fire({
                    icon: 'error',
                    title: 'Migración no completada',
                    text: message,
                    confirmButtonColor: '#d33'
                });
                return;
            }

            $('#resultado-migracion-remito').html('<div class="alert alert-success" role="alert">' + response.message + '</div>');
            $('#cardMigracionRemito').slideUp(250);

            Swal.fire({
                icon: 'success',
                title: 'Migración aplicada',
                text: response.message,
                confirmButtonColor: '#198754'
            });
        },
        error: function(xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Error al ejecutar la migración.';
            $('#resultado-migracion-remito').html('<div class="alert alert-danger" role="alert">' + message + '</div>');
            $button.prop('disabled', false).html(htmlOriginal);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                confirmButtonColor: '#d33'
            });
        }
    });
}

function initMigracionFinanzas() {
    const $button = $('#btnMigracionFinanzas');
    if ($button.length === 0) {
        return;
    }

    $button.on('click', function() {
        Swal.fire({
            title: '¿Ejecutar migración financiera?',
            html: `
                <div class="text-left">
                    <p>Esto habilitará:</p>
                    <ul>
                        <li>Alta de proveedores</li>
                        <li>Compromisos y deudas pendientes</li>
                        <li>Cheques recibidos y emitidos</li>
                        <li>Pagos parciales y recordatorios</li>
                    </ul>
                    <p class="mb-0 text-warning"><strong>La acción es de un solo uso desde esta UI.</strong></p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-play mr-2"></i>Ejecutar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#c48b2f',
            cancelButtonColor: '#6c757d',
            allowOutsideClick: false
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            ejecutarMigracionFinanzas($button);
        });
    });
}

function ejecutarMigracionFinanzas($button) {
    const endpoint = $button.data('endpoint');
    const token = $button.data('token');
    const htmlOriginal = '<i class="fas fa-play mr-2"></i> Ejecutar migración única';

    $button.prop('disabled', true);
    $button.html('<i class="fas fa-spinner fa-spin mr-2"></i> Ejecutando...');
    $('#resultado-migracion-finanzas').html('');

    $.ajax({
        url: endpoint,
        type: 'POST',
        dataType: 'json',
        data: {
            csrf_token: token
        },
        success: function(response) {
            if (!response || !response.success) {
                const message = response && response.message ? response.message : 'No se pudo ejecutar la migración financiera.';
                $('#resultado-migracion-finanzas').html('<div class="alert alert-danger" role="alert">' + message + '</div>');
                $button.prop('disabled', false).html(htmlOriginal);
                Swal.fire({
                    icon: 'error',
                    title: 'Migración no completada',
                    text: message,
                    confirmButtonColor: '#d33'
                });
                return;
            }

            $('#resultado-migracion-finanzas').html('<div class="alert alert-success" role="alert">' + response.message + '</div>');
            $('#cardMigracionFinanzas').slideUp(250);

            Swal.fire({
                icon: 'success',
                title: 'Migración aplicada',
                text: response.message,
                confirmButtonColor: '#198754'
            });
        },
        error: function(xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Error al ejecutar la migración financiera.';
            $('#resultado-migracion-finanzas').html('<div class="alert alert-danger" role="alert">' + message + '</div>');
            $button.prop('disabled', false).html(htmlOriginal);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                confirmButtonColor: '#d33'
            });
        }
    });
}

function initImportacionProductos() {
    const $button = $('#btnImportacionProductos');
    if ($button.length === 0) {
        return;
    }

    $button.on('click', function() {
        const fileInput = document.getElementById('archivoImportacionProductos');
        const file = fileInput && fileInput.files ? fileInput.files[0] : null;

        if (!file) {
            $('#resultado-importacion-productos').html('<div class="alert alert-warning" role="alert">Seleccioná primero la planilla XLSX a importar.</div>');
            return;
        }

        previsualizarImportacionProductos($button, file);
    });
}

function previsualizarImportacionProductos($button, file) {
    const endpoint = $button.data('endpoint');
    const token = $button.data('token');
    const formData = new FormData();
    formData.append('csrf_token', token);
    formData.append('archivo_productos', file);
    formData.append('preview_only', '1');

    $('#resultado-importacion-productos').html('<div class="alert alert-info" role="alert">Analizando planilla para generar la previsualización...</div>');

    $.ajax({
        url: endpoint,
        type: 'POST',
        data: formData,
        dataType: 'json',
        processData: false,
        contentType: false,
        timeout: 180000,
        success: function(response) {
            if (!response || !response.success || !response.preview) {
                const message = response && response.message ? response.message : 'No se pudo generar la previsualización.';
                $('#resultado-importacion-productos').html('<div class="alert alert-danger" role="alert">' + message + '</div>');
                Swal.fire({
                    icon: 'error',
                    title: 'Previsualización no disponible',
                    text: message,
                    confirmButtonColor: '#d33'
                });
                return;
            }

            const preview = response.preview;
            Swal.fire({
                title: 'Confirmar importación masiva',
                html: `
                    <div class="text-left">
                        <p><strong>Archivo:</strong> ${response.label || file.name}</p>
                        <ul>
                            <li>Filas válidas: <strong>${preview.filas_validas || 0}</strong></li>
                            <li>Insertar estimado: <strong>${preview.insertar_estimado || 0}</strong></li>
                            <li>Actualizar estimado: <strong>${preview.actualizar_estimado || 0}</strong></li>
                            <li>Omitidas: <strong>${preview.omitidos || 0}</strong></li>
                        </ul>
                        <p class="mb-2 text-warning"><strong>Acción de una sola vez en esta base.</strong></p>
                        <p class="mb-0">Para continuar escribí exactamente: <code>IMPORTAR PRODUCTOS</code></p>
                    </div>
                `,
                icon: 'warning',
                input: 'text',
                inputLabel: 'Frase de confirmación',
                inputPlaceholder: 'IMPORTAR PRODUCTOS',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-upload mr-2"></i>Importar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#c48b2f',
                cancelButtonColor: '#6c757d',
                allowOutsideClick: false,
                inputValidator: function(value) {
                    if (value !== 'IMPORTAR PRODUCTOS') {
                        return 'La frase no coincide exactamente.';
                    }
                }
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                ejecutarImportacionProductos($button, file);
            });
        },
        error: function(xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Error al generar la previsualización.';
            $('#resultado-importacion-productos').html('<div class="alert alert-danger" role="alert">' + message + '</div>');
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                confirmButtonColor: '#d33'
            });
        }
    });
}

function ejecutarImportacionProductos($button, file) {
    const endpoint = $button.data('endpoint');
    const token = $button.data('token');
    const textoOriginal = '<i class="fas fa-upload mr-2"></i> Importar productos una sola vez';
    const formData = new FormData();
    formData.append('csrf_token', token);
    formData.append('archivo_productos', file);

    $button.prop('disabled', true);
    $button.html('<i class="fas fa-spinner fa-spin mr-2"></i> Importando...');
    $('#resultado-importacion-productos').html('');

    $.ajax({
        url: endpoint,
        type: 'POST',
        data: formData,
        dataType: 'json',
        processData: false,
        contentType: false,
        timeout: 180000,
        success: function(response) {
            if (!response || !response.success) {
                const message = response && response.message ? response.message : 'No se pudo completar la importación.';
                $('#resultado-importacion-productos').html('<div class="alert alert-danger" role="alert">' + message + '</div>');
                $button.prop('disabled', false).html(textoOriginal);
                Swal.fire({
                    icon: 'error',
                    title: 'Importación no completada',
                    text: message,
                    confirmButtonColor: '#d33'
                });
                return;
            }

            $('#resultado-importacion-productos').html('<div class="alert alert-success" role="alert">' + response.message + '</div>');
            $('#cardImportacionProductos').slideUp(250);

            Swal.fire({
                icon: 'success',
                title: 'Importación aplicada',
                text: response.message,
                confirmButtonColor: '#198754'
            });
        },
        error: function(xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Error al ejecutar la importación.';
            $('#resultado-importacion-productos').html('<div class="alert alert-danger" role="alert">' + message + '</div>');
            $button.prop('disabled', false).html(textoOriginal);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                confirmButtonColor: '#d33'
            });
        }
    });
}
</script>