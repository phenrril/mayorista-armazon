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
// Solo permiso explícito de API: "configuracion" no alcanza (evita ver claves sin rol API).
mayorista_requiere_permiso($conexion, $id_user, array('api_config'));

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$apiBase = $baseUrl . '/api/index.php';
$apiKey = mayorista_get_api_key();
$apiConfigurada = mayorista_api_key_configurada();
$apiKeyDisplay = $apiConfigurada
    ? str_repeat('*', max(strlen($apiKey) - 4, 0)) . substr($apiKey, -4)
    : 'No configurada';

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
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($apiKeyDisplay); ?>" readonly>
            </div>
            <div class="alert <?php echo $apiConfigurada ? 'alert-success' : 'alert-danger'; ?> mb-0">
                <?php if ($apiConfigurada) { ?>
                    La API esta habilitada mediante `MAYORISTA_API_KEY`.
                <?php } else { ?>
                    La API esta deshabilitada hasta definir `MAYORISTA_API_KEY` en el entorno.
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
