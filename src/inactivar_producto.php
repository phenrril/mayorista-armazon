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

if (!empty($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id > 0) {
        $query_update = mysqli_query($conexion, "UPDATE producto SET estado = 0 WHERE codproducto = $id");
        if ($query_update) {
            $_SESSION['mensaje'] = 'Producto inactivado exitosamente';
        } else {
            $_SESSION['mensaje'] = 'Error al inactivar el producto: ' . mysqli_error($conexion);
        }
    }
}
header("Location: productos.php");
exit();
?>

