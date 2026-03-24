<?php 
session_start();
require_once "../conexion.php";
if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (isset($_POST['permisos'])) {
    $id_user = $_GET['id'];
    $permisos = $_POST['permisos'];
    mysqli_query($conexion, "DELETE FROM detalle_permisos WHERE id_usuario = $id_user");
    
    $success = false;
    if (isset($permisos) && !empty($permisos)) {
        foreach ($permisos as $permiso) {
            $sql = mysqli_query($conexion, "INSERT INTO detalle_permisos(id_usuario, id_permiso) VALUES ('$id_user','$permiso')");
            $success = $sql;
        }
    } else {
        // Si no hay permisos, el DELETE ya se ejecutó, considerar como éxito
        $success = true;
    }
    
    if ($success) {
        header("Location: rol.php?id=".$id_user."&m=si");
        exit();
    }
}

include_once "includes/header.php";

$sqlpermisos = mysqli_query($conexion, "SELECT * FROM permisos");
$usuarios = mysqli_query($conexion, "SELECT * FROM usuario WHERE idusuario = $id");
$consulta = mysqli_query($conexion, "SELECT * FROM detalle_permisos WHERE id_usuario = $id");
$resultUsuario = mysqli_num_rows($usuarios);
if (empty($resultUsuario)) {
    header("Location: usuarios.php");
    exit();
}
$datos = array();
foreach ($consulta as $asignado) {
    $datos[$asignado['id_permiso']] = true;
}
?>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header bg-warning text-white">
                Permisos
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <?php if(isset($_GET['m']) && $_GET['m'] == 'si') { ?>
                        <div class="alert alert-success" role="alert">
                            Permisos actualizado
                        </div>

                    <?php } ?>
                    <?php while ($row = mysqli_fetch_assoc($sqlpermisos)) { ?>
                        <div class="form-check form-check-inline m-4">
                            <label for="permisos" class="p-2 text-uppercase"><?php echo $row['nombre']; ?></label>
                            <input id="permisos" type="checkbox" name="permisos[]" value="<?php echo $row['id']; ?>"<?php
                                                    if (isset($datos[$row['id']])) {
                                                    echo "checked";
                                                                    }
                                                                    ?>>
                        </div>
                    <?php } ?>
                    <br>
                    <button class="btn btn-primary btn-block" type="submit">Modificar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>