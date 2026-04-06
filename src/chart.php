<?php
session_start();
include("../conexion.php");
require_once "includes/mayorista_helpers.php";

header('Content-Type: application/json; charset=utf-8');

function chart_response($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

if (!($conexion instanceof mysqli)) {
    chart_response(array('success' => false, 'message' => 'No se pudo conectar a la base de datos'), 500);
}

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    chart_response(array('success' => false, 'message' => 'No autorizado'), 403);
}

$id_user = (int) $_SESSION['idUser'];
if (!mayorista_tiene_permiso($conexion, $id_user, array('estadisticas'))) {
    chart_response(array('success' => false, 'message' => 'Sin permisos para ver estadisticas'), 403);
}

$action = $_POST['action'] ?? '';

if ($action === 'sales') {
    $arreglo = array();
    $query = mysqli_query(
        $conexion,
        "SELECT descripcion, existencia
         FROM producto
         WHERE existencia <= 10 AND existencia > 0 AND estado = 1
         ORDER BY existencia ASC
         LIMIT 10"
    );
    while ($data = mysqli_fetch_assoc($query)) {
        $arreglo[] = $data;
    }
    chart_response($arreglo);
}

if ($action === 'polarChart') {
    $arreglo = array();
    $query = mysqli_query(
        $conexion,
        "SELECT p.codproducto, p.descripcion, d.id_producto, d.cantidad, SUM(d.cantidad) as total
         FROM producto p
         INNER JOIN detalle_venta d ON p.codproducto = d.id_producto
         GROUP BY d.id_producto, p.codproducto, p.descripcion
         ORDER BY total DESC
         LIMIT 5"
    );
    while ($data = mysqli_fetch_assoc($query)) {
        $arreglo[] = $data;
    }
    chart_response($arreglo);
}

chart_response(array('success' => false, 'message' => 'Accion invalida'), 400);