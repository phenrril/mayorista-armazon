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
$query = mysqli_query($conexion, "SELECT * FROM producto WHERE codproducto = $idProducto LIMIT 1");
$producto = $query ? mysqli_fetch_assoc($query) : null;
if (!$producto) {
    header("Location: productos.php");
    exit();
}

$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cantidad = max(0, (int) ($_POST['cantidad'] ?? 0));
    $precio = isset($_POST['precio']) ? (float) $_POST['precio'] : (float) $producto['precio'];
    $precioMayorista = isset($_POST['precio_mayorista']) ? (float) $_POST['precio_mayorista'] : (float) ($producto['precio_mayorista'] ?? $precio);

    $nuevoStock = (int) $producto['existencia'] + $cantidad;
    $updates = array("existencia = $nuevoStock", "precio = $precio");

    if ($hasMayorista) {
        $updates[] = "precio_mayorista = $precioMayorista";
    }

    $update = mysqli_query($conexion, "UPDATE producto SET " . implode(', ', $updates) . " WHERE codproducto = $idProducto");
    if ($update) {
        $alert = '<div class="alert alert-success">Stock actualizado correctamente.</div>';
        $query = mysqli_query($conexion, "SELECT * FROM producto WHERE codproducto = $idProducto LIMIT 1");
        $producto = mysqli_fetch_assoc($query);
    } else {
        $alert = '<div class="alert alert-danger">No se pudo actualizar el stock.</div>';
    }
}

include_once "includes/header.php";
?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">Actualizar stock y precios</div>
            <div class="card-body">
                <?php echo $alert; ?>
                <form method="post">
                    <div class="form-group">
                        <label>Producto</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(mayorista_nombre_producto($producto)); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Stock actual</label>
                        <input type="text" class="form-control" value="<?php echo (int) $producto['existencia']; ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Agregar unidades</label>
                        <input type="number" name="cantidad" min="0" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label>Precio minorista</label>
                        <input type="number" step="0.01" min="0" name="precio" class="form-control" value="<?php echo (float) $producto['precio']; ?>">
                    </div>
                    <?php if ($hasMayorista) { ?>
                        <div class="form-group">
                            <label>Precio mayorista</label>
                            <input type="number" step="0.01" min="0" name="precio_mayorista" class="form-control" value="<?php echo (float) $producto['precio_mayorista']; ?>">
                        </div>
                    <?php } ?>
                    <button class="btn btn-primary" type="submit">Guardar</button>
                    <a class="btn btn-secondary" href="productos.php">Volver</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
