<?php
session_start();
include "../conexion.php";
require_once "includes/mayorista_helpers.php";
require_once "config.php";

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_user = (int) $_SESSION['idUser'];
mayorista_requiere_permiso($conexion, $id_user, array('api_config', 'configuracion'));

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$apiBase = $baseUrl . '/api/index.php';

include_once "includes/header.php";
?>
<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-dark text-white">Configuracion de API</div>
        <div class="card-body">
            <p>Usa esta API para integrar OpenClaw con clientes, productos, cuenta corriente y resumen diario.</p>
            <div class="form-group">
                <label>Base URL</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($apiBase); ?>" readonly>
            </div>
            <div class="form-group">
                <label>API Key</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars(mayorista_get_api_key()); ?>" readonly>
            </div>
            <div class="alert alert-warning mb-0">
                Define `MAYORISTA_API_KEY` en el entorno productivo para reemplazar la clave por defecto.
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
