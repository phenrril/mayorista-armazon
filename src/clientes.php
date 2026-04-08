<?php 
session_start();
include "../conexion.php";
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
$permiso = "clientes";
$permiso_escaped = mysqli_real_escape_string($conexion, $permiso);
$sql = mysqli_query($conexion, "SELECT p.*, d.* FROM permisos p INNER JOIN detalle_permisos d ON p.id = d.id_permiso WHERE d.id_usuario = $id_user AND p.nombre = '$permiso_escaped'");
$existe = mysqli_fetch_all($sql);
if (empty($existe) && $id_user != 1) {
    header("Location: permisos.php");
    exit();
}

$condicionesIva = array(
    'Consumidor Final',
    'IVA Responsable Inscripto',
    'Responsable Monotributo',
    'IVA Sujeto Exento',
    'IVA Responsable no Inscripto',
    'IVA no Responsable',
    'Sujeto no Categorizado',
    'Proveedor del Exterior',
    'Cliente del Exterior',
    'IVA Liberado - Ley N° 19.640',
);

$hasClienteCuit = mayorista_column_exists($conexion, 'cliente', 'cuit');
$hasClienteCondicionIva = mayorista_column_exists($conexion, 'cliente', 'condicion_iva');
$hasClienteTipoDocumento = mayorista_column_exists($conexion, 'cliente', 'tipo_documento');
$hasClienteOptica = mayorista_column_exists($conexion, 'cliente', 'optica');
$hasClienteLocalidad = mayorista_column_exists($conexion, 'cliente', 'localidad');
$hasClienteCodigoPostal = mayorista_column_exists($conexion, 'cliente', 'codigo_postal');
$hasClienteProvincia = mayorista_column_exists($conexion, 'cliente', 'provincia');
$clienteFiscalSchemaReady = $hasClienteCuit && $hasClienteCondicionIva && $hasClienteTipoDocumento;
$clienteRemitoSchemaReady = $hasClienteOptica && $hasClienteLocalidad && $hasClienteCodigoPostal && $hasClienteProvincia;

include_once "includes/header.php";
if (!empty($_POST)) {
    $alert = "";
    if (empty($_POST['nombre']) || empty($_POST['telefono']) || empty($_POST['direccion'])) {
        $alert = '<div class="alert alert-danger" role="alert">
                                    Complete los campos obligatorios
                                </div>';
    } else {
        $action = $_POST['action'] ?? 'crear_cliente';
        $nombre = mysqli_real_escape_string($conexion, trim($_POST['nombre']));
        $telefono = mysqli_real_escape_string($conexion, trim($_POST['telefono']));
        $direccion = mysqli_real_escape_string($conexion, trim($_POST['direccion']));
        $usuario_id = (int) $_SESSION['idUser'];
        $dni = mysqli_real_escape_string($conexion, trim($_POST['dni'] ?? ''));
        $cuit = mysqli_real_escape_string($conexion, trim($_POST['cuit'] ?? ''));
        $optica = mysqli_real_escape_string($conexion, trim($_POST['optica'] ?? ''));
        $localidad = mysqli_real_escape_string($conexion, trim($_POST['localidad'] ?? ''));
        $codigo_postal = mysqli_real_escape_string($conexion, trim($_POST['codigo_postal'] ?? ''));
        $provincia = mysqli_real_escape_string($conexion, trim($_POST['provincia'] ?? ''));
        $condicion_iva = trim($_POST['condicion_iva'] ?? 'Consumidor Final');
        if (!in_array($condicion_iva, $condicionesIva, true)) {
            $condicion_iva = 'Consumidor Final';
        }
        $condicion_iva = mysqli_real_escape_string($conexion, $condicion_iva);
        $tipo_documento = (int) ($_POST['tipo_documento'] ?? 96);
        if (!in_array($tipo_documento, array(80, 96), true)) {
            $tipo_documento = 96;
        }

        if ($clienteRemitoSchemaReady && ($optica === '' || $localidad === '' || $codigo_postal === '' || $provincia === '')) {
            $alert = '<div class="alert alert-danger" role="alert">
                                    Complete óptica, localidad, código postal y provincia
                                </div>';
        } elseif ($clienteFiscalSchemaReady && $tipo_documento === 80 && $cuit === '') {
            $alert = '<div class="alert alert-danger" role="alert">
                                    Para tipo CUIT el campo CUIT es obligatorio
                                </div>';
        } elseif ($clienteFiscalSchemaReady && $tipo_documento === 96 && $dni === '') {
            $alert = '<div class="alert alert-danger" role="alert">
                                    Para tipo DNI el campo DNI es obligatorio
                                </div>';
        } elseif ($action === 'editar_cliente') {
            $idcliente = (int) ($_POST['idcliente'] ?? 0);
            $camposUpdate = array(
                "nombre='$nombre'",
                "telefono='$telefono'",
                "direccion='$direccion'",
                "dni='$dni'",
            );
            if ($hasClienteOptica) {
                $camposUpdate[] = "optica='$optica'";
            }
            if ($hasClienteLocalidad) {
                $camposUpdate[] = "localidad='$localidad'";
            }
            if ($hasClienteCodigoPostal) {
                $camposUpdate[] = "codigo_postal='$codigo_postal'";
            }
            if ($hasClienteProvincia) {
                $camposUpdate[] = "provincia='$provincia'";
            }
            if ($hasClienteCuit) {
                $camposUpdate[] = "cuit='$cuit'";
            }
            if ($hasClienteCondicionIva) {
                $camposUpdate[] = "condicion_iva='$condicion_iva'";
            }
            if ($hasClienteTipoDocumento) {
                $camposUpdate[] = "tipo_documento=$tipo_documento";
            }
            $sql_update = mysqli_query(
                $conexion,
                "UPDATE cliente
                 SET " . implode(",\n                     ", $camposUpdate) . "
                 WHERE idcliente=$idcliente"
            );
            if ($sql_update) {
                $alert = '<div class="alert alert-success" role="alert">
                                    Cliente actualizado
                                </div>';
            } else {
                $alert = '<div class="alert alert-danger" role="alert">
                                    Error al actualizar
                            </div>';
            }
        } else {
            $query = mysqli_query($conexion, "SELECT 1 FROM cliente WHERE nombre = '$nombre' LIMIT 1");
            if ($query && mysqli_num_rows($query) > 0) {
                $alert = '<div class="alert alert-danger" role="alert">
                                    El cliente ya existe
                                </div>';
            } else {
                $insertColumns = array('nombre', 'telefono', 'direccion', 'usuario_id', 'dni');
                $insertValues = array("'$nombre'", "'$telefono'", "'$direccion'", "'$usuario_id'", "'$dni'");
                if ($hasClienteOptica) {
                    $insertColumns[] = 'optica';
                    $insertValues[] = "'$optica'";
                }
                if ($hasClienteLocalidad) {
                    $insertColumns[] = 'localidad';
                    $insertValues[] = "'$localidad'";
                }
                if ($hasClienteCodigoPostal) {
                    $insertColumns[] = 'codigo_postal';
                    $insertValues[] = "'$codigo_postal'";
                }
                if ($hasClienteProvincia) {
                    $insertColumns[] = 'provincia';
                    $insertValues[] = "'$provincia'";
                }
                if ($hasClienteCuit) {
                    $insertColumns[] = 'cuit';
                    $insertValues[] = "'$cuit'";
                }
                if ($hasClienteCondicionIva) {
                    $insertColumns[] = 'condicion_iva';
                    $insertValues[] = "'$condicion_iva'";
                }
                if ($hasClienteTipoDocumento) {
                    $insertColumns[] = 'tipo_documento';
                    $insertValues[] = (string) $tipo_documento;
                }
                $query_insert = mysqli_query(
                    $conexion,
                    "INSERT INTO cliente(" . implode(',', $insertColumns) . ")
                     VALUES (" . implode(', ', $insertValues) . ")"
                );
                if ($query_insert) {
                    $alert = '<div class="alert alert-success" role="alert">
                                        Cliente registrado
                                    </div>';
                } else {
                    $alert = '<div class="alert alert-danger" role="alert">
                                        Error al registrar
                                </div>';
                }
            }
        }
    }
}
?>
<style>
.clientes-container #tbl {
    width: 100% !important;
    margin: 0 auto;
    font-size: 0.92rem;
}

.clientes-container .table-responsive {
    overflow-x: visible;
}

.clientes-container #tbl thead th,
.clientes-container #tbl tbody td {
    padding: 0.55rem 0.4rem;
}

.clientes-container .dataTables_wrapper {
    font-size: 0.92rem;
}

.clientes-container .dataTables_wrapper .dataTables_length,
.clientes-container .dataTables_wrapper .dataTables_filter,
.clientes-container .dataTables_wrapper .dataTables_info,
.clientes-container .dataTables_wrapper .dataTables_paginate {
    font-size: 0.88rem;
}

.clientes-container .dataTables_wrapper .dataTables_filter input,
.clientes-container .dataTables_wrapper .dataTables_length select {
    font-size: 0.88rem;
    padding-top: 0.2rem;
    padding-bottom: 0.2rem;
}

.acciones-cliente-col {
    white-space: nowrap;
    min-width: 150px;
}

.acciones-cliente-group {
    display: inline-flex;
    align-items: center;
    flex-wrap: nowrap;
    gap: 0.35rem;
}

.acciones-cliente-group .btn-action {
    margin-right: 0 !important;
}
</style>
<div class="clientes-container fade-in-container">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
        <h2><i class="fas fa-users mr-2"></i> Gestión de Clientes</h2>
        <p class="mb-0 mt-2">Administrá clientes con sus datos fiscales y acceso a cuenta corriente.</p>
        </div>
        <div class="d-flex align-items-center flex-wrap mt-3 mt-md-0">
            <button class="btn btn-primary-modern btn-modern mr-2 mb-2 mb-md-0" type="button" data-toggle="modal" data-target="#nuevo_cliente">
                <i class="fas fa-plus mr-2"></i> Nuevo cliente
            </button>
            <div class="badge-soft">
                <i class="fas fa-address-book"></i>
                Padron comercial
            </div>
        </div>
    </div>

    <?php if (!$clienteFiscalSchemaReady) { ?>
        <div class="alert alert-warning mb-4" role="alert">
            Los campos fiscales avanzados del cliente todavía no están disponibles. Si los necesitás, aplicá `sql/setup_facturacion_electronica.sql`.
        </div>
    <?php } ?>

    <?php
    $query_count = mysqli_query($conexion, "SELECT COUNT(*) as total FROM cliente WHERE estado = 1");
    $total_clientes = mysqli_fetch_assoc($query_count);
    if ($hasClienteCuit) {
        $query_fiscales = mysqli_query($conexion, "SELECT COUNT(*) as total FROM cliente WHERE estado = 1 AND IFNULL(cuit, '') <> ''");
        $total_fiscales = mysqli_fetch_assoc($query_fiscales);
    } else {
        $total_fiscales = array('total' => 0);
    }
    ?>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="stat-box">
                <span>Clientes activos</span>
                <strong><?php echo (int) $total_clientes['total']; ?></strong>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-box">
                <span>Con CUIT cargado</span>
                <strong><?php echo (int) $total_fiscales['total']; ?></strong>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-box d-flex align-items-center justify-content-center">
                <span class="text-muted text-center">Alta rápida disponible desde el encabezado.</span>
            </div>
        </div>
    </div>

    <?php echo isset($alert) ? $alert : ''; ?>

    <div class="card-modern">
        <div class="card-header-modern">
            <i class="fas fa-list mr-2"></i> Clientes
        </div>
        <div class="card-body-modern">
            <div class="table-responsive">
                <table class="table table-modern" id="tbl">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre *</th>
                            <?php if ($hasClienteOptica) { ?><th>Óptica</th><?php } ?>
                            <th>Teléfono *</th>
                            <th>Dirección *</th>
                            <?php if ($hasClienteLocalidad) { ?><th>Localidad</th><?php } ?>
                            <?php if ($hasClienteProvincia) { ?><th>Provincia</th><?php } ?>
                            <?php if ($hasClienteCodigoPostal) { ?><th>CP</th><?php } ?>
                            <th>DNI</th>
                            <th>CUIT</th>
                            <th>Condición IVA</th>
                            <th>Estado</th>
                            <th class="acciones-cliente-col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = mysqli_query(
                            $conexion,
                            "SELECT
                                idcliente,
                                nombre,
                                telefono,
                                direccion,
                                estado,
                                dni,
                                " . ($hasClienteOptica ? "optica" : "'' AS optica") . ",
                                " . ($hasClienteLocalidad ? "localidad" : "'' AS localidad") . ",
                                " . ($hasClienteProvincia ? "provincia" : "'' AS provincia") . ",
                                " . ($hasClienteCodigoPostal ? "codigo_postal" : "'' AS codigo_postal") . ",
                                " . ($hasClienteCuit ? "cuit" : "'' AS cuit") . ",
                                " . ($hasClienteCondicionIva ? "condicion_iva" : "'Consumidor Final' AS condicion_iva") . ",
                                " . ($hasClienteTipoDocumento ? "tipo_documento" : "96 AS tipo_documento") . "
                             FROM cliente
                             ORDER BY idcliente DESC"
                        );
                        $result = mysqli_num_rows($query);
                        if ($result > 0) {
                            while ($data = mysqli_fetch_assoc($query)) {
                                if ($data['estado'] == 1) {
                                    $estado = '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Activo</span>';
                                } else {
                                    $estado = '<span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i>Inactivo</span>';
                                }
                        ?>
                                <tr>
                                    <td><?php echo $data['idcliente']; ?></td>
                                    <td><i class="fas fa-user-circle text-primary mr-2"></i><?php echo htmlspecialchars($data['nombre']); ?></td>
                                    <?php if ($hasClienteOptica) { ?><td><?php echo htmlspecialchars($data['optica'] ?: '-'); ?></td><?php } ?>
                                    <td><i class="fas fa-phone text-success mr-2"></i><?php echo htmlspecialchars($data['telefono']); ?></td>
                                    <td><i class="fas fa-map-marker-alt text-info mr-2"></i><?php echo htmlspecialchars($data['direccion']); ?></td>
                                    <?php if ($hasClienteLocalidad) { ?><td><?php echo htmlspecialchars($data['localidad'] ?: '-'); ?></td><?php } ?>
                                    <?php if ($hasClienteProvincia) { ?><td><?php echo htmlspecialchars($data['provincia'] ?: '-'); ?></td><?php } ?>
                                    <?php if ($hasClienteCodigoPostal) { ?><td><?php echo htmlspecialchars($data['codigo_postal'] ?: '-'); ?></td><?php } ?>
                                    <td><?php echo htmlspecialchars($data['dni'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($data['cuit'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($data['condicion_iva'] ?: 'Consumidor Final'); ?></td>
                                    <td><?php echo $estado; ?></td>
                                    <td class="acciones-cliente-col">
                                        <div class="btn-group acciones-cliente-group" role="group">
                                            <?php if ($data['estado'] == 1) { ?>
                                                <a href="cuenta_corriente.php?cliente=<?php echo $data['idcliente']; ?>" class="btn btn-info btn-sm btn-action" title="Cuenta corriente">
                                                    <i class='fas fa-file-invoice-dollar'></i>
                                                </a>
                                                <button
                                                    type="button"
                                                    class="btn btn-success btn-sm btn-action btn-editar-cliente"
                                                    title="Editar"
                                                    data-toggle="modal"
                                                    data-target="#editar_cliente_modal"
                                                    data-idcliente="<?php echo (int) $data['idcliente']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($data['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-optica="<?php echo htmlspecialchars($data['optica'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-telefono="<?php echo htmlspecialchars($data['telefono'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-direccion="<?php echo htmlspecialchars($data['direccion'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-localidad="<?php echo htmlspecialchars($data['localidad'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-provincia="<?php echo htmlspecialchars($data['provincia'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-codigopostal="<?php echo htmlspecialchars($data['codigo_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-dni="<?php echo htmlspecialchars($data['dni'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-cuit="<?php echo htmlspecialchars($data['cuit'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-condicioniva="<?php echo htmlspecialchars($data['condicion_iva'] ?? 'Consumidor Final', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-tipodocumento="<?php echo (int) ($data['tipo_documento'] ?? 96); ?>"
                                                >
                                                    <i class='fas fa-edit'></i>
                                                </button>
                                                <form action="eliminar_cliente.php?id=<?php echo $data['idcliente']; ?>" method="post" class="confirmar d-inline">
                                                    <button class="btn btn-danger btn-sm btn-action" type="submit" title="Eliminar">
                                                        <i class='fas fa-trash-alt'></i>
                                                    </button>
                                                </form>
                                            <?php } ?>

                                            <?php if ($data['estado'] == 0) { ?>
                                                <form action="reactivar_cliente.php?id=<?php echo $data['idcliente']; ?>" method="post" class="d-inline">
                                                    <button class="btn btn-warning btn-sm btn-action" type="submit" title="Reactivar">
                                                        <i class='fas fa-redo'></i>
                                                    </button>
                                                </form>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>
                        <?php }
                        } else { ?>
                            <tr>
                                <td colspan="13" class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted">No hay clientes registrados</p>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Cliente -->
<div id="nuevo_cliente" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="my-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="my-modal-title">
                    <i class="fas fa-user-plus mr-2"></i> Nuevo Cliente
                </h5>
                <button class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="" method="post" autocomplete="off">
                    <input type="hidden" name="action" value="crear_cliente">
                    <div class="row">
                        <?php if ($hasClienteOptica) { ?>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="optica"><i class="fas fa-store mr-2 text-secondary"></i>Óptica *</label>
                                <input type="text" placeholder="Ingrese nombre de la óptica" name="optica" id="optica" class="form-control" required>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre"><i class="fas fa-user mr-2 text-primary"></i>Nombre *</label>
                                <input type="text" placeholder="Ingrese nombre del cliente" name="nombre" id="nombre" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="telefono"><i class="fas fa-phone mr-2 text-success"></i>Teléfono *</label>
                                <input type="text" placeholder="Ingrese teléfono" name="telefono" id="telefono" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="direccion"><i class="fas fa-map-marker-alt mr-2 text-info"></i>Dirección *</label>
                                <input type="text" placeholder="Ingrese dirección" name="direccion" id="direccion" class="form-control" required>
                            </div>
                        </div>
                        <?php if ($hasClienteLocalidad) { ?>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="localidad"><i class="fas fa-city mr-2 text-info"></i>Localidad *</label>
                                <input type="text" placeholder="Ingrese localidad" name="localidad" id="localidad" class="form-control" required>
                            </div>
                        </div>
                        <?php } ?>
                        <?php if ($hasClienteProvincia) { ?>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="provincia"><i class="fas fa-map mr-2 text-info"></i>Provincia *</label>
                                <input type="text" placeholder="Ingrese provincia" name="provincia" id="provincia" class="form-control" required>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="row">
                        <?php if ($hasClienteCodigoPostal) { ?>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="codigo_postal"><i class="fas fa-mail-bulk mr-2 text-dark"></i>Código postal *</label>
                                <input type="text" placeholder="Ingrese código postal" name="codigo_postal" id="codigo_postal" class="form-control" required>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label for="dni"><i class="fas fa-id-card mr-2 text-warning"></i>DNI</label>
                                <input type="text" placeholder="Ingrese DNI" name="dni" id="dni" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cuit"><i class="fas fa-file-invoice mr-2 text-secondary"></i>CUIT</label>
                                <input type="text" placeholder="Ingrese CUIT" name="cuit" id="cuit" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="tipo_documento"><i class="fas fa-id-badge mr-2 text-dark"></i>Tipo doc.</label>
                                <select name="tipo_documento" id="tipo_documento" class="form-control">
                                    <option value="96">DNI</option>
                                    <option value="80">CUIT</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="condicion_iva"><i class="fas fa-receipt mr-2 text-success"></i>Condición IVA</label>
                                <select name="condicion_iva" id="condicion_iva" class="form-control">
                                    <?php foreach ($condicionesIva as $condicionIva) { ?>
                                        <option value="<?php echo htmlspecialchars($condicionIva); ?>" <?php echo $condicionIva === 'Consumidor Final' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($condicionIva); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-2"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i> Guardar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="editar_cliente_modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="editar-cliente-title" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="editar-cliente-title">
                    <i class="fas fa-user-edit mr-2"></i> Editar Cliente
                </h5>
                <button class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="" method="post" autocomplete="off" id="form_editar_cliente">
                    <input type="hidden" name="action" value="editar_cliente">
                    <input type="hidden" name="idcliente" id="edit_idcliente">
                    <div class="row">
                        <?php if ($hasClienteOptica) { ?>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_optica"><i class="fas fa-store mr-2 text-secondary"></i>Óptica *</label>
                                <input type="text" placeholder="Ingrese nombre de la óptica" name="optica" id="edit_optica" class="form-control" required>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_nombre"><i class="fas fa-user mr-2 text-primary"></i>Nombre *</label>
                                <input type="text" placeholder="Ingrese nombre del cliente" name="nombre" id="edit_nombre" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_telefono"><i class="fas fa-phone mr-2 text-success"></i>Teléfono *</label>
                                <input type="text" placeholder="Ingrese teléfono" name="telefono" id="edit_telefono" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_direccion"><i class="fas fa-map-marker-alt mr-2 text-info"></i>Dirección *</label>
                                <input type="text" placeholder="Ingrese dirección" name="direccion" id="edit_direccion" class="form-control" required>
                            </div>
                        </div>
                        <?php if ($hasClienteLocalidad) { ?>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="edit_localidad"><i class="fas fa-city mr-2 text-info"></i>Localidad *</label>
                                <input type="text" placeholder="Ingrese localidad" name="localidad" id="edit_localidad" class="form-control" required>
                            </div>
                        </div>
                        <?php } ?>
                        <?php if ($hasClienteProvincia) { ?>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="edit_provincia"><i class="fas fa-map mr-2 text-info"></i>Provincia *</label>
                                <input type="text" placeholder="Ingrese provincia" name="provincia" id="edit_provincia" class="form-control" required>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="row">
                        <?php if ($hasClienteCodigoPostal) { ?>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="edit_codigo_postal"><i class="fas fa-mail-bulk mr-2 text-dark"></i>Código postal *</label>
                                <input type="text" placeholder="Ingrese código postal" name="codigo_postal" id="edit_codigo_postal" class="form-control" required>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label for="edit_dni"><i class="fas fa-id-card mr-2 text-warning"></i>DNI</label>
                                <input type="text" placeholder="Ingrese DNI" name="dni" id="edit_dni" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_cuit"><i class="fas fa-file-invoice mr-2 text-secondary"></i>CUIT</label>
                                <input type="text" placeholder="Ingrese CUIT" name="cuit" id="edit_cuit" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="edit_tipo_documento"><i class="fas fa-id-badge mr-2 text-dark"></i>Tipo doc.</label>
                                <select name="tipo_documento" id="edit_tipo_documento" class="form-control">
                                    <option value="96">DNI</option>
                                    <option value="80">CUIT</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="edit_condicion_iva"><i class="fas fa-receipt mr-2 text-success"></i>Condición IVA</label>
                                <select name="condicion_iva" id="edit_condicion_iva" class="form-control">
                                    <?php foreach ($condicionesIva as $condicionIva) { ?>
                                        <option value="<?php echo htmlspecialchars($condicionIva); ?>">
                                            <?php echo htmlspecialchars($condicionIva); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-2"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save mr-2"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    if ($('#tbl').length && !$('#tbl').hasClass('custom-dt-init')) {
        $('#tbl').DataTable({
            autoWidth: false
        });
    }

    $('.btn-editar-cliente').on('click', function () {
        const button = $(this);
        $('#edit_idcliente').val(button.data('idcliente'));
        $('#edit_nombre').val(button.data('nombre'));
        $('#edit_optica').val(button.data('optica'));
        $('#edit_telefono').val(button.data('telefono'));
        $('#edit_direccion').val(button.data('direccion'));
        $('#edit_localidad').val(button.data('localidad'));
        $('#edit_provincia').val(button.data('provincia'));
        $('#edit_codigo_postal').val(button.data('codigopostal'));
        $('#edit_dni').val(button.data('dni'));
        $('#edit_cuit').val(button.data('cuit'));
        $('#edit_condicion_iva').val(button.data('condicioniva'));
        $('#edit_tipo_documento').val(String(button.data('tipodocumento') || 96));
    });
});
</script>

<?php include_once "includes/footer.php"; ?>