<?php
require "../conexion.php";
require_once "includes/mayorista_helpers.php";
session_start();

function postpagos_alerta($icon, $title, $timer = 2000)
{
    echo "<script>Swal.fire({
        position: 'center',
        toast: false,
        icon: '$icon',
        title: '" . addslashes($title) . "',
        showConfirmButton: false,
        timer: $timer
    })</script>;";
    exit();
}

if (!($conexion instanceof mysqli)) {
    postpagos_alerta('error', 'No se pudo conectar a la base de datos');
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    http_response_code(403);
    postpagos_alerta('error', 'No autorizado', 3000);
}

$id_user = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $id_user, array('ventas'))) {
    http_response_code(403);
    postpagos_alerta('error', 'No tenes permisos para registrar abonos', 3000);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    postpagos_alerta('error', 'Metodo no permitido', 3000);
}

$id_venta = isset($_POST['idventa']) ? (int) $_POST['idventa'] : 0;
$id_abona = round((float) ($_POST['idabona'] ?? 0), 2);

if ($id_venta <= 0 || $id_abona <= 0) {
    postpagos_alerta('error', 'Complete ambos campos');
}

mysqli_begin_transaction($conexion);

try {
    $query = mysqli_query(
        $conexion,
        "SELECT *
         FROM postpagos
         WHERE id_venta = $id_venta
         LIMIT 1
         FOR UPDATE"
    );
    $valueventa = $query ? mysqli_fetch_assoc($query) : null;

    if (!$valueventa) {
        throw new RuntimeException('Venta inexistente');
    }

    if ((float) $valueventa['resto'] <= 0) {
        throw new RuntimeException('La venta no tiene resto que abonar');
    }

    $id_cliente = (int) $valueventa['id_cliente'];
    $abonatabla = round((float) $valueventa['abona'], 2);
    $resto = round((float) $valueventa['resto'], 2);

    if ($resto < $id_abona) {
        throw new RuntimeException('El abono es mayor al resto');
    }

    $abonatotal = round($abonatabla + $id_abona, 2);
    $resto = round($resto - $id_abona, 2);

    $update = mysqli_query($conexion, "UPDATE postpagos SET abona = $abonatotal, resto = $resto WHERE id_venta = $id_venta");
    $update2 = mysqli_query($conexion, "UPDATE ventas SET abona = $abonatotal, resto = $resto WHERE id = $id_venta");
    $update3 = mysqli_query($conexion, "UPDATE detalle_venta SET abona = $abonatotal, resto = $resto WHERE id_venta = $id_venta");

    if ($update === false || $update2 === false || $update3 === false) {
        throw new RuntimeException('Error actualizando la venta');
    }

    mysqli_commit($conexion);

    echo "<script>Swal.fire({
        position: 'center',
        toast: false,
        icon: 'success',
        title: 'Abono realizado',
        showConfirmButton: false,
        timer: 2000
    })</script>;";
    echo "<br><br><br><div class='row justify-content-center'><div class='alert alert-success w-20'><div class='col-md-12 text-center'>VER PDF</div></div></div>";
    echo "<div class='row justify-content-center'>
            <a href='pdf/generar.php?cl=$id_cliente&v=$id_venta' target='_blank' class='btn btn-danger'><i class='fas fa-file-pdf'></i></a>
          <div>";
} catch (Throwable $e) {
    mysqli_rollback($conexion);
    postpagos_alerta('error', $e->getMessage());
}
