<?php
session_start();
include "../conexion.php";
require_once "includes/mayorista_helpers.php";

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('productos'));

$hasDescripcion = mayorista_column_exists($conexion, 'producto', 'descripcion');
$hasMayorista = mayorista_column_exists($conexion, 'producto', 'precio_mayorista');
$hasTipo = mayorista_column_exists($conexion, 'producto', 'tipo');
$hasModelo = mayorista_column_exists($conexion, 'producto', 'modelo');
$hasColor = mayorista_column_exists($conexion, 'producto', 'color');
$hasTipoMaterial = mayorista_column_exists($conexion, 'producto', 'tipo_material');
$hasPrecioBruto = mayorista_column_exists($conexion, 'producto', 'precio_bruto');
$hasCosto = mayorista_column_exists($conexion, 'producto', 'costo');
$tiposProducto = mayorista_tipos_producto();
$tiposMaterial = mayorista_tipos_material_producto();
$alert = '';
$flashMessage = '';
$previewProductos = array();

if (!empty($_SESSION['mensaje'])) {
    $flashMessage = '<div class="alert alert-info">' . htmlspecialchars((string) $_SESSION['mensaje']) . '</div>';
    unset($_SESSION['mensaje']);
}

function productos_normalizar_valor_filtro($valor)
{
    $valor = trim((string) $valor);
    return $valor === '' ? '' : $valor;
}

if (!empty($_POST['action']) && $_POST['action'] === 'crear_producto') {
    $codigo = mysqli_real_escape_string($conexion, trim($_POST['codigo'] ?? ''));
    $marca = mysqli_real_escape_string($conexion, trim($_POST['marca'] ?? ''));
    $modelo = mysqli_real_escape_string($conexion, trim($_POST['modelo'] ?? ''));
    $color = mysqli_real_escape_string($conexion, trim($_POST['color'] ?? ''));
    $tipoMaterial = trim($_POST['tipo_material'] ?? '');
    $precio = (float) ($_POST['precio'] ?? 0);
    $precioMayorista = (float) ($_POST['precio_mayorista'] ?? $precio);
    $cantidad = (int) ($_POST['cantidad'] ?? 0);
    $tipo = trim($_POST['tipo'] ?? 'receta');
    $precioBruto = (float) ($_POST['precio_bruto'] ?? 0);
    $costo = isset($_POST['costo']) ? 1 : 0;

    if (!in_array($tipo, $tiposProducto, true)) {
        $tipo = 'receta';
    }
    if (!in_array($tipoMaterial, $tiposMaterial, true)) {
        $tipoMaterial = '';
    }

    if (
        $codigo === '' || $marca === '' || $precio < 0 || $cantidad < 0
        || ($hasModelo && $modelo === '')
        || ($hasColor && $color === '')
        || ($hasTipoMaterial && $tipoMaterial === '')
    ) {
        $alert = '<div class="alert alert-warning">Completa codigo, marca, modelo, color, material, tipo, precio y stock inicial.</div>';
    } else {
        $check = mysqli_query($conexion, "SELECT 1 FROM producto WHERE codigo = '$codigo' LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            $alert = '<div class="alert alert-warning">Ya existe un producto con ese codigo.</div>';
        } else {
            $campos = array('codigo', 'marca', 'precio', 'existencia', 'usuario_id');
            $valores = array("'$codigo'", "'$marca'", $precio, $cantidad, $id_user);

            if ($hasModelo) {
                $campos[] = 'modelo';
                $valores[] = "'$modelo'";
            }
            if ($hasColor) {
                $campos[] = 'color';
                $valores[] = "'$color'";
            }
            if ($hasTipoMaterial) {
                $campos[] = 'tipo_material';
                $valores[] = $tipoMaterial === '' ? 'NULL' : "'" . mysqli_real_escape_string($conexion, $tipoMaterial) . "'";
            }

            if ($hasMayorista) {
                $campos[] = 'precio_mayorista';
                $valores[] = $precioMayorista;
            }
            if ($hasPrecioBruto) {
                $campos[] = 'precio_bruto';
                $valores[] = $precioBruto;
            }
            if ($hasTipo) {
                $campos[] = 'tipo';
                $valores[] = "'$tipo'";
            }
            if ($hasDescripcion) {
                $campos[] = 'descripcion';
                $valores[] = "'" . mysqli_real_escape_string($conexion, $tipo) . "'";
            }
            if ($hasCosto) {
                $campos[] = 'costo';
                $valores[] = $costo;
            }

            $insert = mysqli_query(
                $conexion,
                "INSERT INTO producto(" . implode(', ', $campos) . ")
                 VALUES (" . implode(', ', $valores) . ")"
            );

            if ($insert) {
                $alert = '<div class="alert alert-success">Producto creado correctamente.</div>';
            } else {
                $alert = '<div class="alert alert-danger">No se pudo crear el producto.</div>';
            }
        }
    }
}

if (!empty($_POST['action']) && $_POST['action'] === 'ajuste_masivo') {
    $objetivo = $_POST['objetivo_precio'] ?? 'minorista';
    $porcentaje = (float) ($_POST['porcentaje'] ?? 0);
    $precioFijoRaw = trim((string) ($_POST['precio_fijo'] ?? ''));
    $precioFijo = $precioFijoRaw !== '' ? (float) $precioFijoRaw : null;
    $filtroMarca = mysqli_real_escape_string($conexion, trim($_POST['filtro_marca'] ?? ''));
    $filtroModelo = mysqli_real_escape_string($conexion, trim($_POST['filtro_modelo'] ?? ''));
    $filtroMaterial = trim($_POST['filtro_material'] ?? '');
    $filtroTipo = trim($_POST['filtro_tipo'] ?? '');
    $nuevaMarca = mysqli_real_escape_string($conexion, trim($_POST['nueva_marca'] ?? ''));
    $nuevoModelo = mysqli_real_escape_string($conexion, trim($_POST['nuevo_modelo'] ?? ''));
    $nuevoMaterial = trim($_POST['nuevo_material'] ?? '');
    $nuevoTipo = trim($_POST['nuevo_tipo'] ?? '');
    $modoOperacion = $_POST['modo_operacion'] ?? 'porcentaje';
    $accionMasiva = $_POST['accion_masiva'] ?? 'aplicar';

    if (!in_array($filtroMaterial, array_merge(array(''), $tiposMaterial), true)) {
        $filtroMaterial = '';
    }
    if (!in_array($filtroTipo, array_merge(array(''), $tiposProducto), true)) {
        $filtroTipo = '';
    }
    if (!in_array($nuevoMaterial, array_merge(array(''), $tiposMaterial), true)) {
        $nuevoMaterial = '';
    }
    if (!in_array($nuevoTipo, array_merge(array(''), $tiposProducto), true)) {
        $nuevoTipo = '';
    }

    $where = array('1=1');
    if ($filtroMarca !== '') {
        $where[] = "marca LIKE '%$filtroMarca%'";
    }
    if ($hasModelo && $filtroModelo !== '') {
        $where[] = "modelo LIKE '%$filtroModelo%'";
    }
    if ($hasTipoMaterial && $filtroMaterial !== '') {
        $where[] = "tipo_material = '" . mysqli_real_escape_string($conexion, $filtroMaterial) . "'";
    }
    if ($hasTipo && $filtroTipo !== '') {
        $where[] = "tipo = '" . mysqli_real_escape_string($conexion, $filtroTipo) . "'";
    }

    $campo = ($objetivo === 'mayorista' && $hasMayorista) ? 'precio_mayorista' : 'precio';
    $updates = array();

    if ($modoOperacion === 'porcentaje') {
        $factor = 1 + ($porcentaje / 100);
        if ($factor <= 0) {
            $alert = '<div class="alert alert-warning">El porcentaje ingresado genera un precio invalido.</div>';
        } elseif ($porcentaje != 0) {
            $updates[] = "$campo = ROUND($campo * $factor, 2)";
        }
    } else {
        if ($precioFijo !== null && $precioFijo >= 0) {
            $updates[] = "$campo = " . round($precioFijo, 2);
        }
    }

    if ($nuevaMarca !== '') {
        $updates[] = "marca = '$nuevaMarca'";
    }
    if ($hasModelo && $nuevoModelo !== '') {
        $updates[] = "modelo = '$nuevoModelo'";
    }
    if ($hasTipoMaterial && $nuevoMaterial !== '') {
        $updates[] = "tipo_material = '" . mysqli_real_escape_string($conexion, $nuevoMaterial) . "'";
    }
    if ($hasTipo && $nuevoTipo !== '') {
        $updates[] = "tipo = '" . mysqli_real_escape_string($conexion, $nuevoTipo) . "'";
    }

    $whereSql = implode(' AND ', $where);
    $previewQuery = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM producto WHERE $whereSql");
    $previewData = $previewQuery ? mysqli_fetch_assoc($previewQuery) : array('total' => 0);
    $totalAfectados = (int) ($previewData['total'] ?? 0);

    if ($alert === '') {
        if ($totalAfectados <= 0) {
            $alert = '<div class="alert alert-warning">No hay productos que coincidan con los filtros seleccionados.</div>';
        } elseif (empty($updates)) {
            $alert = '<div class="alert alert-warning">Definí al menos un cambio para aplicar en la edición masiva.</div>';
        } elseif ($accionMasiva === 'preview') {
            $alert = '<div class="alert alert-info">Se previsualizaron ' . $totalAfectados . ' producto(s) afectados por la edición masiva.</div>';
            $camposPreview = array('codproducto', 'codigo', 'marca', 'precio', 'existencia');
            if ($hasModelo) {
                $camposPreview[] = 'modelo';
            }
            if ($hasColor) {
                $camposPreview[] = 'color';
            }
            if ($hasTipoMaterial) {
                $camposPreview[] = 'tipo_material';
            }
            if ($hasTipo) {
                $camposPreview[] = 'tipo';
            }
            if ($hasMayorista) {
                $camposPreview[] = 'precio_mayorista';
            }

            $previewListado = mysqli_query(
                $conexion,
                "SELECT " . implode(', ', $camposPreview) . "
                 FROM producto
                 WHERE $whereSql
                 ORDER BY marca ASC, codigo ASC
                 LIMIT 15"
            );
            if ($previewListado) {
                while ($rowPreview = mysqli_fetch_assoc($previewListado)) {
                    $previewProductos[] = $rowPreview;
                }
            }
        } else {
            $update = mysqli_query(
                $conexion,
                "UPDATE producto
                 SET " . implode(', ', $updates) . "
                 WHERE $whereSql"
            );
            $alert = $update
                ? '<div class="alert alert-success">Edicion masiva aplicada sobre ' . $totalAfectados . ' producto(s).</div>'
                : '<div class="alert alert-danger">No se pudo aplicar la edición masiva.</div>';
        }
    }
}

$query = mysqli_query($conexion, "SELECT * FROM producto ORDER BY codproducto DESC");
$productosListado = array();
$filtrosCatalogo = array(
    'marca' => array(),
    'modelo' => array(),
    'color' => array(),
    'tipo_material' => array(),
    'tipo' => array(),
);
if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $productosListado[] = $row;
        foreach (array_keys($filtrosCatalogo) as $campoFiltro) {
            $valorFiltro = productos_normalizar_valor_filtro($row[$campoFiltro] ?? '');
            if ($valorFiltro !== '') {
                $filtrosCatalogo[$campoFiltro][$valorFiltro] = true;
            }
        }
    }
}
foreach ($filtrosCatalogo as $campoFiltro => $valoresFiltro) {
    $filtrosCatalogo[$campoFiltro] = array_keys($valoresFiltro);
    natcasesort($filtrosCatalogo[$campoFiltro]);
    $filtrosCatalogo[$campoFiltro] = array_values($filtrosCatalogo[$campoFiltro]);
}

$totalProductosCatalogo = count($productosListado);
$totalUnidadesCatalogo = 0;
foreach ($productosListado as $productoListado) {
    $totalUnidadesCatalogo += (int) ($productoListado['existencia'] ?? 0);
}

$columnaMarca = 3;
$columnaActual = 4;
$columnaModelo = $hasModelo ? $columnaActual++ : null;
$columnaColor = $hasColor ? $columnaActual++ : null;
$columnaMaterial = $hasTipoMaterial ? $columnaActual++ : null;
$columnaTipo = $hasTipo ? $columnaActual++ : null;
include_once "includes/header.php";
?>
<style>
.productos-tabla-shell {
    position: relative;
    min-height: 420px;
}

.productos-tabla-shell .table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding: 0.3rem 0.6rem 0.45rem;
    border-radius: 18px;
}

#tbl {
    width: 100% !important;
    min-width: 1120px;
    margin: 0 auto;
    font-size: 0.92rem;
}

.productos-tabla-shell .dataTables_wrapper {
    width: 100%;
    margin: 0 auto;
    font-size: 0.92rem;
}

#tbl thead th,
#tbl tbody td {
    padding: 0.55rem 0.4rem;
}

.productos-tabla-shell .dataTables_wrapper .dataTables_length,
.productos-tabla-shell .dataTables_wrapper .dataTables_filter,
.productos-tabla-shell .dataTables_wrapper .dataTables_info,
.productos-tabla-shell .dataTables_wrapper .dataTables_paginate {
    font-size: 0.88rem;
}

.productos-tabla-shell .dataTables_wrapper .dataTables_filter input,
.productos-tabla-shell .dataTables_wrapper .dataTables_length select {
    font-size: 0.88rem;
    padding-top: 0.2rem;
    padding-bottom: 0.2rem;
}

.productos-tabla-shell.is-pending .table-responsive,
.productos-tabla-shell.is-pending .dataTables_wrapper {
    opacity: 0;
}

.productos-tabla-shell.is-pending::after {
    content: "Cargando catalogo...";
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.72);
    font-weight: 600;
    letter-spacing: 0.02em;
    background: rgba(9, 11, 18, 0.28);
    border-radius: 18px;
    pointer-events: none;
}

#tbl th:last-child,
#tbl td.productos-acciones {
    white-space: nowrap;
    min-width: 0;
    width: auto;
}

#tbl td.productos-acciones .btn {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

#tbl td.productos-acciones {
    padding-right: 1rem;
}

#tbl td.productos-acciones .productos-acciones-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: nowrap;
    min-width: 0;
}

.js-stock-input {
    width: 76px;
    max-width: 100%;
    min-height: 30px;
    padding: 0.2rem 0.35rem;
    border-radius: 0.35rem;
}

.js-stock-input.is-saving {
    opacity: 0.7;
    pointer-events: none;
}

.js-stock-cell .stock-feedback {
    display: block;
    min-height: 1rem;
    font-size: 0.68rem;
    line-height: 1rem;
    opacity: 0;
    transition: opacity 0.15s ease;
}

.js-stock-cell .stock-feedback.is-visible {
    opacity: 1;
}
</style>
<div class="productos-container">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h2><i class="fas fa-glasses mr-2"></i> Productos</h2>
            <p class="mb-0">Catalogo listo para minorista y mayorista.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-light mr-2" data-toggle="modal" data-target="#modalProducto">
                <i class="fas fa-plus mr-1"></i> Nuevo producto
            </button>
            <button class="btn btn-outline-light" data-toggle="modal" data-target="#modalAjuste">
                <i class="fas fa-percentage mr-1"></i> Ajuste masivo
            </button>
        </div>
    </div>

    <?php if (!$hasMayorista || !$hasTipo || !$hasModelo || !$hasColor || !$hasTipoMaterial) { ?>
        <div class="alert alert-warning">
            Falta aplicar `sql/2026_remito_clientes_productos.sql` para habilitar todos los atributos nuevos de producto en la interfaz.
        </div>
    <?php } ?>

    <?php echo $flashMessage; ?>
    <?php echo $alert; ?>

    <?php if (!empty($previewProductos)) { ?>
        <div class="card card-modern mb-4">
            <div class="card-header card-header-modern">
                <i class="fas fa-eye mr-2"></i> Vista previa de edición masiva
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Se muestran los primeros <?php echo count($previewProductos); ?> productos que coinciden con los filtros elegidos.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-dark">
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Marca</th>
                                <?php if ($hasModelo) { ?><th>Modelo</th><?php } ?>
                                <?php if ($hasTipoMaterial) { ?><th>Material</th><?php } ?>
                                <?php if ($hasTipo) { ?><th>Tipo</th><?php } ?>
                                <th>Minorista</th>
                                <?php if ($hasMayorista) { ?><th>Mayorista</th><?php } ?>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewProductos as $previewProducto) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($previewProducto['codigo']); ?></td>
                                    <td><?php echo htmlspecialchars(mayorista_nombre_producto($previewProducto)); ?></td>
                                    <td><?php echo htmlspecialchars($previewProducto['marca']); ?></td>
                                    <?php if ($hasModelo) { ?><td><?php echo htmlspecialchars($previewProducto['modelo'] ?? '-'); ?></td><?php } ?>
                                    <?php if ($hasTipoMaterial) { ?><td><?php echo htmlspecialchars($previewProducto['tipo_material'] ?? '-'); ?></td><?php } ?>
                                    <?php if ($hasTipo) { ?><td><?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $previewProducto['tipo'] ?? ''))); ?></td><?php } ?>
                                    <td><?php echo number_format((float) $previewProducto['precio'], 2, ',', '.'); ?></td>
                                    <?php if ($hasMayorista) { ?><td><?php echo number_format((float) ($previewProducto['precio_mayorista'] ?? 0), 2, ',', '.'); ?></td><?php } ?>
                                    <td><?php echo (int) $previewProducto['existencia']; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="row">
        <div class="col-md-6 col-lg-3">
            <div class="stat-box">
                <span>Total productos</span>
                <strong id="js-total-productos"><?php echo $totalProductosCatalogo; ?></strong>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-box">
                <span>Total unidades</span>
                <strong id="js-total-unidades"><?php echo $totalUnidadesCatalogo; ?></strong>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-box">
                <span>Con stock</span>
                <strong><?php echo mysqli_num_rows(mysqli_query($conexion, "SELECT 1 FROM producto WHERE existencia > 0")); ?></strong>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-box">
                <span>Sin stock</span>
                <strong><?php echo mysqli_num_rows(mysqli_query($conexion, "SELECT 1 FROM producto WHERE existencia <= 0")); ?></strong>
            </div>
        </div>
    </div>

    <div class="card card-modern">
        <div class="card-header card-header-modern d-flex justify-content-between align-items-center flex-wrap">
            <span><i class="fas fa-list mr-2"></i> Catalogo</span>
            <button class="btn btn-sm btn-outline-light mt-2 mt-md-0" type="button" data-toggle="collapse" data-target="#catalogoFiltros" aria-expanded="false" aria-controls="catalogoFiltros">
                <i class="fas fa-filter mr-1"></i> Filtros
            </button>
        </div>
        <div class="card-body">
            <div class="collapse mb-4" id="catalogoFiltros">
                <div class="card card-body bg-light border-0">
                    <div class="form-row">
                        <div class="form-group col-md-4 col-lg-3">
                            <label class="mb-1">Marca</label>
                            <select class="form-control js-product-filter" data-column="<?php echo $columnaMarca; ?>">
                                <option value="">Todas</option>
                                <?php foreach ($filtrosCatalogo['marca'] as $valorMarca) { ?>
                                    <option value="<?php echo htmlspecialchars($valorMarca); ?>"><?php echo htmlspecialchars($valorMarca); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php if ($hasModelo) { ?>
                        <div class="form-group col-md-4 col-lg-3">
                            <label class="mb-1">Modelo</label>
                            <select class="form-control js-product-filter" data-column="<?php echo (int) $columnaModelo; ?>">
                                <option value="">Todos</option>
                                <?php foreach ($filtrosCatalogo['modelo'] as $valorModelo) { ?>
                                    <option value="<?php echo htmlspecialchars($valorModelo); ?>"><?php echo htmlspecialchars($valorModelo); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php } ?>
                        <?php if ($hasColor) { ?>
                        <div class="form-group col-md-4 col-lg-2">
                            <label class="mb-1">Color</label>
                            <select class="form-control js-product-filter" data-column="<?php echo (int) $columnaColor; ?>">
                                <option value="">Todos</option>
                                <?php foreach ($filtrosCatalogo['color'] as $valorColor) { ?>
                                    <option value="<?php echo htmlspecialchars($valorColor); ?>"><?php echo htmlspecialchars($valorColor); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php } ?>
                        <?php if ($hasTipoMaterial) { ?>
                        <div class="form-group col-md-4 col-lg-2">
                            <label class="mb-1">Material</label>
                            <select class="form-control js-product-filter" data-column="<?php echo (int) $columnaMaterial; ?>">
                                <option value="">Todos</option>
                                <?php foreach ($filtrosCatalogo['tipo_material'] as $valorMaterial) { ?>
                                    <option value="<?php echo htmlspecialchars($valorMaterial); ?>"><?php echo htmlspecialchars($valorMaterial); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php } ?>
                        <?php if ($hasTipo) { ?>
                        <div class="form-group col-md-4 col-lg-2">
                            <label class="mb-1">Tipo</label>
                            <select class="form-control js-product-filter" data-column="<?php echo (int) $columnaTipo; ?>">
                                <option value="">Todos</option>
                                <?php foreach ($filtrosCatalogo['tipo'] as $valorTipo) { ?>
                                    <?php $tipoFiltroLabel = mayorista_formatear_tipo_producto(is_array($valorTipo) ? '' : (string) $valorTipo); ?>
                                    <option value="<?php echo htmlspecialchars((string) $tipoFiltroLabel); ?>">
                                        <?php echo htmlspecialchars((string) $tipoFiltroLabel); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimpiarFiltrosCatalogo">
                            Limpiar filtros
                        </button>
                    </div>
                </div>
            </div>
            <div class="productos-tabla-shell is-pending" id="js-productos-tabla-shell">
            <div class="table-responsive">
                <table class="table table-hover custom-dt-init" id="tbl">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th>
                            <th>Codigo</th>
                            <th>Producto</th>
                            <th>Marca</th>
                            <?php if ($hasModelo) { ?><th>Modelo</th><?php } ?>
                            <?php if ($hasColor) { ?><th>Color</th><?php } ?>
                            <?php if ($hasTipoMaterial) { ?><th>Material</th><?php } ?>
                            <?php if ($hasTipo) { ?><th>Tipo</th><?php } ?>
                            <th>Minorista</th>
                            <?php if ($hasMayorista) { ?><th>Mayorista</th><?php } ?>
                            <?php if ($hasPrecioBruto) { ?><th>Base</th><?php } ?>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($productosListado)) {
                            foreach ($productosListado as $data) {
                                $estado = (int) $data['estado'] === 1
                                    ? '<span class="badge badge-success">Activo</span>'
                                    : '<span class="badge badge-secondary">Inactivo</span>';
                                $stockClass = (int) $data['existencia'] > 0 ? 'text-success' : 'text-danger';
                        ?>
                            <tr data-stock="<?php echo (int) $data['existencia']; ?>" data-producto-id="<?php echo (int) $data['codproducto']; ?>">
                                <td><?php echo $data['codproducto']; ?></td>
                                <td><?php echo htmlspecialchars($data['codigo']); ?></td>
                                <td><?php echo htmlspecialchars(mayorista_nombre_producto($data)); ?></td>
                                <td><?php echo htmlspecialchars($data['marca']); ?></td>
                                <?php if ($hasModelo) { ?><td><?php echo htmlspecialchars($data['modelo'] ?? '-'); ?></td><?php } ?>
                                <?php if ($hasColor) { ?><td><?php echo htmlspecialchars($data['color'] ?? '-'); ?></td><?php } ?>
                                <?php if ($hasTipoMaterial) { ?><td><?php echo htmlspecialchars($data['tipo_material'] ?? '-'); ?></td><?php } ?>
                                <?php if ($hasTipo) { ?><td><?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $data['tipo']))); ?></td><?php } ?>
                                <td><?php echo number_format((float) $data['precio'], 2, ',', '.'); ?></td>
                                <?php if ($hasMayorista) { ?><td><?php echo number_format((float) $data['precio_mayorista'], 2, ',', '.'); ?></td><?php } ?>
                                <?php if ($hasPrecioBruto) { ?><td><?php echo number_format((float) $data['precio_bruto'], 2, ',', '.'); ?></td><?php } ?>
                                <td class="<?php echo $stockClass; ?> js-stock-cell" data-order="<?php echo (int) $data['existencia']; ?>">
                                    <input
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="form-control form-control-sm js-stock-input"
                                        value="<?php echo (int) $data['existencia']; ?>"
                                        data-original="<?php echo (int) $data['existencia']; ?>"
                                        data-last-saved="<?php echo (int) $data['existencia']; ?>"
                                        aria-label="Stock producto <?php echo (int) $data['codproducto']; ?>">
                                    <span class="stock-feedback js-stock-feedback"></span>
                                </td>
                                <td><?php echo $estado; ?></td>
                                <td class="productos-acciones">
                                    <div class="productos-acciones-wrap">
                                        <a href="agregar_producto.php?id=<?php echo $data['codproducto']; ?>" class="btn btn-sm btn-primary" title="Stock">
                                            <i class="fas fa-layer-group"></i>
                                        </a>
                                        <a href="editar_producto.php?id=<?php echo $data['codproducto']; ?>" class="btn btn-sm btn-success" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-danger js-eliminar-producto"
                                            title="Eliminar"
                                            data-id="<?php echo (int) $data['codproducto']; ?>"
                                            data-producto="<?php echo htmlspecialchars(mayorista_nombre_producto($data), ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php if ((int) $data['estado'] === 1) { ?>
                                            <a href="inactivar_producto.php?id=<?php echo $data['codproducto']; ?>" class="btn btn-sm btn-warning confirmar-inactivar" title="Inactivar">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php } else { ?>
                                            <a href="activar_producto.php?id=<?php echo $data['codproducto']; ?>" class="btn btn-sm btn-info" title="Activar">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php }
                        } ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProducto" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Nuevo producto</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="crear_producto">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Codigo</label>
                                <input type="text" name="codigo" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Marca</label>
                                <input type="text" name="marca" class="form-control" required>
                            </div>
                        </div>
                        <?php if ($hasModelo) { ?>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Modelo</label>
                                <input type="text" name="modelo" class="form-control" required>
                            </div>
                        </div>
                        <?php } ?>
                        <?php if ($hasColor) { ?>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Color</label>
                                <input type="text" name="color" class="form-control" required>
                            </div>
                        </div>
                        <?php } ?>
                        <?php if ($hasTipoMaterial) { ?>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Material</label>
                                <select name="tipo_material" class="form-control" required>
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($tiposMaterial as $tipoMaterial) { ?>
                                        <option value="<?php echo htmlspecialchars($tipoMaterial); ?>"><?php echo htmlspecialchars($tipoMaterial); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Precio minorista</label>
                                <input type="number" step="0.01" min="0" name="precio" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Precio mayorista</label>
                                <input type="number" step="0.01" min="0" name="precio_mayorista" class="form-control" value="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Stock inicial</label>
                                <input type="number" min="0" name="cantidad" class="form-control" value="0">
                            </div>
                        </div>
                        <?php if ($hasPrecioBruto) { ?>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Precio base</label>
                                    <input type="number" step="0.01" min="0" name="precio_bruto" class="form-control" value="0">
                                </div>
                            </div>
                        <?php } ?>
                        <?php if ($hasTipo) { ?>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Tipo</label>
                                    <select name="tipo" class="form-control" required>
                                        <?php foreach ($tiposProducto as $tipoProducto) { ?>
                                            <option value="<?php echo htmlspecialchars($tipoProducto); ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $tipoProducto))); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if ($hasCosto) { ?>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="costo" id="costo">
                                    <label class="form-check-label" for="costo">Marcar como costo directo</label>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAjuste" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Edición masiva</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="ajuste_masivo">
                <div class="modal-body">
                    <h6 class="mb-3">Filtros</h6>
                    <div class="form-group">
                        <label>Marca</label>
                        <input type="text" name="filtro_marca" class="form-control" placeholder="Todas">
                    </div>
                    <?php if ($hasModelo) { ?>
                    <div class="form-group">
                        <label>Modelo</label>
                        <input type="text" name="filtro_modelo" class="form-control" placeholder="Todos">
                    </div>
                    <?php } ?>
                    <?php if ($hasTipoMaterial) { ?>
                    <div class="form-group">
                        <label>Material</label>
                        <select name="filtro_material" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($tiposMaterial as $tipoMaterial) { ?>
                                <option value="<?php echo htmlspecialchars($tipoMaterial); ?>"><?php echo htmlspecialchars($tipoMaterial); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php } ?>
                    <?php if ($hasTipo) { ?>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="filtro_tipo" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($tiposProducto as $tipoProducto) { ?>
                                <option value="<?php echo htmlspecialchars($tipoProducto); ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $tipoProducto))); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php } ?>

                    <hr>
                    <h6 class="mb-3">Cambios</h6>
                    <div class="form-group">
                        <label>Modo de actualización de precio</label>
                        <select name="modo_operacion" class="form-control">
                            <option value="porcentaje">Por porcentaje</option>
                            <option value="fijo">Precio fijo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Aplicar sobre</label>
                        <select name="objetivo_precio" class="form-control">
                            <option value="minorista">Precio minorista</option>
                            <?php if ($hasMayorista) { ?><option value="mayorista">Precio mayorista</option><?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Porcentaje</label>
                        <input type="number" step="0.01" name="porcentaje" class="form-control" placeholder="Ej: 12.5 para subir, -5 para bajar">
                    </div>
                    <div class="form-group">
                        <label>Precio fijo</label>
                        <input type="number" step="0.01" min="0" name="precio_fijo" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label>Nueva marca</label>
                        <input type="text" name="nueva_marca" class="form-control" placeholder="Sin cambios">
                    </div>
                    <?php if ($hasModelo) { ?>
                    <div class="form-group">
                        <label>Nuevo modelo</label>
                        <input type="text" name="nuevo_modelo" class="form-control" placeholder="Sin cambios">
                    </div>
                    <?php } ?>
                    <?php if ($hasTipoMaterial) { ?>
                    <div class="form-group">
                        <label>Nuevo material</label>
                        <select name="nuevo_material" class="form-control">
                            <option value="">Sin cambios</option>
                            <?php foreach ($tiposMaterial as $tipoMaterial) { ?>
                                <option value="<?php echo htmlspecialchars($tipoMaterial); ?>"><?php echo htmlspecialchars($tipoMaterial); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php } ?>
                    <?php if ($hasTipo) { ?>
                    <div class="form-group mb-0">
                        <label>Nuevo tipo</label>
                        <select name="nuevo_tipo" class="form-control">
                            <option value="">Sin cambios</option>
                            <?php foreach ($tiposProducto as $tipoProducto) { ?>
                                <option value="<?php echo htmlspecialchars($tipoProducto); ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $tipoProducto))); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-info" type="submit" name="accion_masiva" value="preview">Previsualizar</button>
                    <button class="btn btn-info text-white" type="submit" name="accion_masiva" value="aplicar">Aplicar edición</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const $ = window.jQuery;
    const tablaShell = document.getElementById('js-productos-tabla-shell');

    function marcarTablaLista() {
        if (tablaShell) {
            tablaShell.classList.remove('is-pending');
        }
    }

    if (!$) {
        marcarTablaLista();
        return;
    }

    const $table = $('#tbl');
    const $filtros = $('.js-product-filter');
    const $btnLimpiar = $('#btnLimpiarFiltrosCatalogo');
    const $totalProductos = $('#js-total-productos');
    const $totalUnidades = $('#js-total-unidades');
    const stockClassPositivo = 'text-success';
    const stockClassSinStock = 'text-danger';

    function actualizarTotales($filas) {
        if (!$totalProductos.length || !$totalUnidades.length) {
            return;
        }

        let totalProductos = 0;
        let totalUnidades = 0;

        $filas.each(function () {
            totalProductos += 1;
            totalUnidades += parseInt($(this).data('stock'), 10) || 0;
        });

        $totalProductos.text(totalProductos);
        $totalUnidades.text(totalUnidades);
    }

    function aplicarFiltrosFallback() {
        if (!$table.length) {
            return;
        }

        $table.find('tbody tr').each(function () {
            const $fila = $(this);
            let visible = true;

            $filtros.each(function () {
                const valor = String($(this).val() || '').trim().toLowerCase();
                if (!valor) {
                    return;
                }

                const columna = parseInt($(this).data('column'), 10);
                const textoCelda = String($fila.find('td').eq(columna).text() || '').trim().toLowerCase();
                if (textoCelda !== valor) {
                    visible = false;
                    return false;
                }
            });

            $fila.toggle(visible);
        });

        actualizarTotales($table.find('tbody tr:visible'));
    }

    function actualizarAparienciaStock($fila, $celdaStock, stock) {
        const valorStock = parseInt(stock, 10) || 0;
        $fila.attr('data-stock', valorStock);
        $celdaStock.attr('data-order', valorStock);
        $celdaStock
            .removeClass(stockClassPositivo + ' ' + stockClassSinStock)
            .addClass(valorStock > 0 ? stockClassPositivo : stockClassSinStock);
    }

    function mostrarFeedbackStock($celda, mensaje, esError) {
        const $feedback = $celda.find('.js-stock-feedback');
        if (!$feedback.length) {
            return;
        }

        $feedback
            .removeClass('text-success text-danger is-visible')
            .addClass(esError ? 'text-danger' : 'text-success')
            .text(mensaje || '')
            .addClass(mensaje ? 'is-visible' : '');

        if (!mensaje) {
            return;
        }

        window.setTimeout(function () {
            if ($feedback.text() === mensaje) {
                $feedback.removeClass('is-visible').text('');
            }
        }, 1800);
    }

    function guardarStock($input) {
        if (!$input || !$input.length || $input.hasClass('is-saving')) {
            return;
        }

        const $fila = $input.closest('tr');
        const $celdaStock = $input.closest('.js-stock-cell');
        const idProducto = parseInt($fila.data('producto-id'), 10) || 0;
        const stockPrevio = parseInt($input.attr('data-last-saved'), 10);
        const stockRaw = String($input.val() || '').trim();

        if (!idProducto) {
            mostrarFeedbackStock($celdaStock, 'ID inválido', true);
            $input.val(Number.isFinite(stockPrevio) ? stockPrevio : 0);
            return;
        }

        if (!/^\d+$/.test(stockRaw)) {
            mostrarFeedbackStock($celdaStock, 'Solo números >= 0', true);
            $input.val(Number.isFinite(stockPrevio) ? stockPrevio : 0);
            return;
        }

        const nuevoStock = parseInt(stockRaw, 10);
        const valorPrevio = Number.isFinite(stockPrevio) ? stockPrevio : 0;
        if (nuevoStock === valorPrevio) {
            $input.val(nuevoStock);
            return;
        }

        $input.addClass('is-saving');
        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            dataType: 'json',
            data: {
                update_stock_producto: 1,
                id_producto: idProducto,
                stock: nuevoStock
            }
        }).done(function (respuesta) {
            if (!respuesta || !respuesta.success) {
                throw new Error(respuesta && respuesta.mensaje ? respuesta.mensaje : 'No se pudo guardar');
            }

            const stockGuardado = parseInt(respuesta.stock, 10) || 0;
            $input
                .val(stockGuardado)
                .attr('data-original', stockGuardado)
                .attr('data-last-saved', stockGuardado);
            actualizarAparienciaStock($fila, $celdaStock, stockGuardado);
            mostrarFeedbackStock($celdaStock, 'Guardado', false);

            if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
                const tableApi = $table.DataTable();
                actualizarTotales($(tableApi.rows({ search: 'applied' }).nodes()));
            } else {
                actualizarTotales($table.find('tbody tr:visible'));
            }
        }).fail(function (xhr) {
            let mensaje = 'No se pudo guardar';
            if (xhr && xhr.responseJSON && xhr.responseJSON.mensaje) {
                mensaje = xhr.responseJSON.mensaje;
            }

            $input.val(valorPrevio);
            mostrarFeedbackStock($celdaStock, mensaje, true);
        }).always(function () {
            $input.removeClass('is-saving');
        });
    }

    function vincularFiltrosDataTable(tableApi) {
        const escapeRegex = $.fn.dataTable.util.escapeRegex;
        $filtros.on('change', function () {
            const columna = parseInt($(this).data('column'), 10);
            const valor = String($(this).val() || '').trim();
            tableApi
                .column(columna)
                .search(valor ? '^' + escapeRegex(valor) + '$' : '', true, false)
                .draw();
        });

        $btnLimpiar.on('click', function () {
            $filtros.val('');
            $filtros.trigger('change');
        });

        tableApi.on('draw', function () {
            actualizarTotales($(tableApi.rows({ search: 'applied' }).nodes()));
        });

        actualizarTotales($(tableApi.rows({ search: 'applied' }).nodes()));
    }

    $(document).on('click', '.js-eliminar-producto', function () {
        const idProducto = parseInt($(this).data('id'), 10);
        const nombreProducto = String($(this).data('producto') || 'este producto').trim();

        if (!idProducto) {
            return;
        }

        const ejecutarEliminacion = function () {
            const form = document.createElement('form');
            form.method = 'post';
            form.action = 'eliminar_producto.php';

            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'id';
            inputId.value = String(idProducto);
            form.appendChild(inputId);

            document.body.appendChild(form);
            form.submit();
        };

        if (window.Swal) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Eliminar producto',
                text: 'Seguro queres eliminar "' + nombreProducto + '"?',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    ejecutarEliminacion();
                }
            });
            return;
        }

        if (window.confirm('Seguro queres eliminar "' + nombreProducto + '"?')) {
            ejecutarEliminacion();
        }
    });

    $(document).on('keydown', '.js-stock-input', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        guardarStock($(this));
        $(this).trigger('blur');
    });

    $(document).on('blur', '.js-stock-input', function () {
        guardarStock($(this));
    });

    if ($.fn.DataTable && $table.length && !$.fn.DataTable.isDataTable($table)) {
        const tableApi = $table.DataTable({
            order: [[0, 'desc']],
            autoWidth: false,
            scrollX: true,
            columnDefs: [
                { targets: -1, orderable: false }
            ],
            initComplete: function () {
                marcarTablaLista();
            }
        });
        vincularFiltrosDataTable(tableApi);
        return;
    }

    $filtros.on('change', aplicarFiltrosFallback);
    $btnLimpiar.on('click', function () {
        $filtros.val('');
        aplicarFiltrosFallback();
    });
    marcarTablaLista();
});
</script>

<?php include_once "includes/footer.php"; ?>
