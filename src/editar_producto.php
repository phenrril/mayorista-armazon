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

$idProducto = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($idProducto <= 0) {
    header("Location: productos.php");
    exit();
}

$hasMayorista = mayorista_column_exists($conexion, 'producto', 'precio_mayorista');
$hasTipo = mayorista_column_exists($conexion, 'producto', 'tipo');
$hasModelo = mayorista_column_exists($conexion, 'producto', 'modelo');
$hasColor = mayorista_column_exists($conexion, 'producto', 'color');
$hasTipoMaterial = mayorista_column_exists($conexion, 'producto', 'tipo_material');
$hasPrecioBruto = mayorista_column_exists($conexion, 'producto', 'precio_bruto');
$hasCosto = mayorista_column_exists($conexion, 'producto', 'costo');
$tiposProducto = mayorista_tipos_producto();
$tiposMaterial = mayorista_tipos_material_producto();

$query = mysqli_query($conexion, "SELECT * FROM producto WHERE codproducto = $idProducto LIMIT 1");
$producto = $query ? mysqli_fetch_assoc($query) : null;
if (!$producto) {
    header("Location: productos.php");
    exit();
}

$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = mysqli_real_escape_string($conexion, trim($_POST['codigo'] ?? ''));
    $descripcion = mysqli_real_escape_string($conexion, trim($_POST['producto'] ?? ''));
    $marca = mysqli_real_escape_string($conexion, trim($_POST['marca'] ?? ''));
    $modelo = mysqli_real_escape_string($conexion, trim($_POST['modelo'] ?? ''));
    $color = mysqli_real_escape_string($conexion, trim($_POST['color'] ?? ''));
    $tipoMaterial = trim($_POST['tipo_material'] ?? '');
    $precio = (float) ($_POST['precio'] ?? 0);
    $precioMayorista = (float) ($_POST['precio_mayorista'] ?? $precio);
    $stock = (int) ($_POST['cantidad'] ?? 0);
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
        $codigo === '' || $descripcion === '' || $marca === '' || $precio < 0 || $stock < 0
        || ($hasModelo && $modelo === '')
        || ($hasColor && $color === '')
        || ($hasTipoMaterial && $tipoMaterial === '')
    ) {
        $alert = '<div class="alert alert-warning">Completa codigo, descripcion, marca, modelo, color, material, precio y stock.</div>';
    } else {
        $updates = array(
            "codigo = '$codigo'",
            "descripcion = '$descripcion'",
            "marca = '$marca'",
            "precio = $precio",
            "existencia = $stock"
        );

        if ($hasModelo) {
            $updates[] = "modelo = '$modelo'";
        }
        if ($hasColor) {
            $updates[] = "color = '$color'";
        }
        if ($hasTipoMaterial) {
            $updates[] = "tipo_material = " . ($tipoMaterial === '' ? 'NULL' : "'" . mysqli_real_escape_string($conexion, $tipoMaterial) . "'");
        }

        if ($hasMayorista) {
            $updates[] = "precio_mayorista = $precioMayorista";
        }
        if ($hasTipo) {
            $updates[] = "tipo = '$tipo'";
        }
        if ($hasPrecioBruto) {
            $updates[] = "precio_bruto = $precioBruto";
        }
        if ($hasCosto) {
            $updates[] = "costo = $costo";
        }

        $update = mysqli_query($conexion, "UPDATE producto SET " . implode(', ', $updates) . " WHERE codproducto = $idProducto");
        if ($update) {
            $alert = '<div class="alert alert-success">Producto actualizado correctamente.</div>';
            $query = mysqli_query($conexion, "SELECT * FROM producto WHERE codproducto = $idProducto LIMIT 1");
            $producto = mysqli_fetch_assoc($query);
        } else {
            $alert = '<div class="alert alert-danger">No se pudo actualizar el producto.</div>';
        }
    }
}

include_once "includes/header.php";
?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">Editar producto</div>
            <div class="card-body">
                <?php echo $alert; ?>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Codigo</label>
                            <input type="text" name="codigo" class="form-control" value="<?php echo htmlspecialchars($producto['codigo']); ?>" required>
                        </div>
                        <div class="form-group col-md-8">
                            <label>Descripcion</label>
                            <input type="text" name="producto" class="form-control" value="<?php echo htmlspecialchars($producto['descripcion']); ?>" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Marca</label>
                            <input type="text" name="marca" class="form-control" value="<?php echo htmlspecialchars($producto['marca']); ?>" required>
                        </div>
                        <?php if ($hasModelo) { ?>
                            <div class="form-group col-md-4">
                                <label>Modelo</label>
                                <input type="text" name="modelo" class="form-control" value="<?php echo htmlspecialchars($producto['modelo'] ?? ''); ?>" required>
                            </div>
                        <?php } ?>
                        <?php if ($hasColor) { ?>
                            <div class="form-group col-md-4">
                                <label>Color</label>
                                <input type="text" name="color" class="form-control" value="<?php echo htmlspecialchars($producto['color'] ?? ''); ?>" required>
                            </div>
                        <?php } ?>
                        <?php if ($hasTipoMaterial) { ?>
                            <div class="form-group col-md-4">
                                <label>Material</label>
                                <select name="tipo_material" class="form-control" required>
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($tiposMaterial as $tipoMaterial) { ?>
                                        <option value="<?php echo htmlspecialchars($tipoMaterial); ?>" <?php echo ($producto['tipo_material'] ?? '') === $tipoMaterial ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tipoMaterial); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        <?php } ?>
                        <?php if ($hasTipo) { ?>
                            <div class="form-group col-md-4">
                                <label>Tipo</label>
                                <select name="tipo" class="form-control">
                                    <?php foreach ($tiposProducto as $tipoProducto) { ?>
                                        <option value="<?php echo htmlspecialchars($tipoProducto); ?>" <?php echo ($producto['tipo'] ?? 'receta') === $tipoProducto ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $tipoProducto))); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        <?php } ?>
                        <div class="form-group col-md-4">
                            <label>Stock</label>
                            <input type="number" min="0" name="cantidad" class="form-control" value="<?php echo (int) $producto['existencia']; ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Precio minorista</label>
                            <input type="number" step="0.01" min="0" name="precio" class="form-control" value="<?php echo (float) $producto['precio']; ?>">
                        </div>
                        <?php if ($hasMayorista) { ?>
                            <div class="form-group col-md-4">
                                <label>Precio mayorista</label>
                                <input type="number" step="0.01" min="0" name="precio_mayorista" class="form-control" value="<?php echo (float) $producto['precio_mayorista']; ?>">
                            </div>
                        <?php } ?>
                        <?php if ($hasPrecioBruto) { ?>
                            <div class="form-group col-md-4">
                                <label>Precio base</label>
                                <input type="number" step="0.01" min="0" name="precio_bruto" class="form-control" value="<?php echo (float) $producto['precio_bruto']; ?>">
                            </div>
                        <?php } ?>
                        <?php if ($hasCosto) { ?>
                            <div class="form-group col-md-12">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="costo" id="costo" <?php echo (int) ($producto['costo'] ?? 0) === 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="costo">Marcar como costo directo</label>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <button class="btn btn-primary" type="submit">Guardar cambios</button>
                    <a class="btn btn-secondary" href="productos.php">Volver</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
