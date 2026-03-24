<?php 
require "../conexion.php";
session_start();


$id_venta = $_POST['idventa'];
$id_abona = $_POST['idabona'];
if($id_venta == "" || $id_abona == ""){
    echo "<script>Swal.fire({
        position: 'top-mid',
        icon: 'error',
        title: 'Complete ambos campos',
        showConfirmButton: false,
        timer: 2000
    })</script>;";
    exit;
}
$query = mysqli_query($conexion, "SELECT * FROM postpagos WHERE id_venta = $id_venta");
$valueventa = mysqli_fetch_assoc($query);

if(mysqli_num_rows($query) == 0){
    echo "<script>Swal.fire({
        position: 'top-mid',
        icon: 'error',
        title: 'Venta inexistente',
        showConfirmButton: false,
        timer: 2000
    })</script>;";
    exit;
}
if (mysqli_num_rows($query) > 0) {
    
    if($valueventa['resto'] == 0){
        echo "<script>Swal.fire({
            position: 'top-mid',
            icon: 'error',
            title: 'La venta no tiene resto que abonar',
            showConfirmButton: false,
            timer: 2000
        })</script>;";

        exit;
    }
    else{
$id_cliente = $valueventa['id_cliente'];
$abonatabla = $valueventa['abona'];
$abonatotal = $abonatabla + $id_abona;
$resto = $valueventa['resto'];
if($resto < $id_abona){
    echo "<script>Swal.fire({
        position: 'top-mid',
        icon: 'error',
        title: 'El abono es mayor al resto',
        showConfirmButton: false,
        timer: 2000
    })</script>;";
    exit;
}else{
    $resto = $resto - $id_abona;
}

$update = mysqli_query($conexion, "UPDATE postpagos SET abona = '".$abonatotal."', resto = '".$resto."' WHERE id_venta = '".$id_venta."'");
$update2 = mysqli_query($conexion, "UPDATE ventas SET abona = '".$abonatotal."', resto = '".$resto."' WHERE id = '".$id_venta."'");
$update3 = mysqli_query($conexion, "UPDATE detalle_venta SET abona = '".$abonatotal."', resto = '".$resto."' WHERE id_venta = '".$id_venta."'");

//if($update && $update2 && $update3)
if ($update !== false && $update2 !== false && $update3 !== false){
    $result = mysqli_affected_rows($conexion);
    if($result > 0){
        echo "<script>Swal.fire({
            position: 'top-mid',
            icon: 'success',
            title: 'Abono realizado',
            showConfirmButton: false,
            timer: 2000
        })</script>;";
        echo "<br><br><br><div class='row justify-content-center'><div class='alert alert-success w-20'><div class='col-md-12 text-center'>VER PDF</div></div></div>";
        echo "  <div class='row justify-content-center'>
                    <a href='pdf/generar.php?cl=$id_cliente&v=$id_venta' target='_blank' class='btn btn-danger'><i class='fas fa-file-pdf'></i></a>
                <div>";    
    }
        else{
            echo "<script>Swal.fire({
                position: 'top-mid',
                icon: 'error',
                title: 'Error actualizando venta',
                showConfirmButton: false,
                timer: 2000
            })</script>;";
        }
    }

else{
    echo "<script>Swal.fire({
        position: 'top-mid',
        icon: 'error',
        title: 'Error actualizando la venta',
        showConfirmButton: false,
        timer: 2000
    })</script>;";    
}
    }
}
?>
