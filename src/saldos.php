<?php 
require "../conexion.php";
session_start();
$id = $_SESSION['idUser'];
$valor = $_POST['valor'];
if($_POST['valor'] == '0' || $_POST['valor'] == 'null'){
    echo "
    <script>
    swal.fire
    ({
        position: 'top-end',
        showConfirmButton: false,
        title: 'Error',
        text: 'El valor no puede ser 0',
        icon: 'error'
    }) 
    </script>";
    die();
}
$tipo = $_POST['tipo'];
$descripcion = $_POST['descripcion'];
$fecha = date("Y-m-d");
$idcliente = 0;
$idmetodo = 1;

if($tipo == 'ingreso'){
    $ingresos =  mysqli_query($conexion,"INSERT INTO ingresos (ingresos, descripcion, fecha, id_cliente, id_metodo) VALUES ('$valor', '$descripcion', '$fecha', '$idcliente', '$idmetodo' )");
}elseif($tipo == 'egreso'){
    $valor = -abs($valor);
    $egresos =  mysqli_query($conexion,"INSERT INTO egresos (egresos, descripcion, fecha, id_cliente, id_metodo) VALUES ('$valor', '$descripcion', '$fecha', '$idcliente', '$idmetodo')");
}

switch($tipo){

case 'ingreso':
    if($ingresos){
    echo "
    <script>
    swal.fire
    ({
        position: 'top-end',
        showConfirmButton: false,
        title: 'Ingreso agregado',
        text: 'El Ingreso se ha agregado correctamente',
        icon: 'success'
    }) 
    </script>";
    break;
}

case 'egreso':
    if($egresos){
    echo "
    <script>
    swal.fire
    ({
        position: 'top-end',
        showConfirmButton: false,
        title: 'Egreso Agregado',
        text: 'El Egreso se ha agregado correctamente',
        icon: 'success'
    }) 
    </script>";
    break;
}
default: 
    echo "
    <script>
    swal.fire
    ({
        position: 'top-end',
        showConfirmButton: false,
        title: 'Error',
        text: 'Error al agregar',
        icon: 'error'
    }) 
    </script>";
}


?>