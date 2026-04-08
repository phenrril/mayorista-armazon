<?php
session_start();
require("../conexion.php");

// Validar sesión
if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = intval($_SESSION['idUser']);
$permiso = "productos";
$permiso_escaped = mysqli_real_escape_string($conexion, $permiso);
$sql = mysqli_query($conexion, "SELECT p.*, d.* FROM permisos p INNER JOIN detalle_permisos d ON p.id = d.id_permiso WHERE d.id_usuario = $id_user AND p.nombre = '$permiso_escaped'");
$existe = mysqli_fetch_all($sql);
if (empty($existe) && $id_user != 1) {
    header("Location: permisos.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_GET['id'])) {
    $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id > 0) {
        // Eliminar el producto de la base de datos directamente
        $query_delete = mysqli_query($conexion, "DELETE FROM producto WHERE codproducto = $id");
        if ($query_delete) {
            $_SESSION['mensaje'] = 'Producto eliminado permanentemente de la base de datos';
        } else {
            $_SESSION['mensaje'] = 'Error al eliminar el producto: ' . mysqli_error($conexion);
        }
    }
}
header("Location: productos.php");
exit();
?>
