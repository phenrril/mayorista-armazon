<?php
session_start();
require_once "../conexion.php";
require_once "includes/mayorista_helpers.php";

if (!isset($_SESSION['idUser']) || empty($_SESSION['idUser'])) {
    header("Location: ../");
    exit();
}

$id_editor = (int) $_SESSION['idUser'];
$permiso = "usuarios";
$permiso_escaped = mysqli_real_escape_string($conexion, $permiso);
$sqlPerm = mysqli_query(
    $conexion,
    "SELECT p.*, d.* FROM permisos p INNER JOIN detalle_permisos d ON p.id = d.id_permiso
     WHERE d.id_usuario = $id_editor AND p.nombre = '$permiso_escaped'"
);
$existe = mysqli_fetch_all($sqlPerm);
if (empty($existe) && $id_editor !== 1) {
    header("Location: permisos.php");
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header("Location: usuarios.php");
    exit();
}

mayorista_asegurar_permisos_catalogo_rol($conexion);

$tokensMap = mayorista_permisos_tokens_a_nombres_rol();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_permisos'])) {
    $id_user = $id;
    $tokensRecibidos = isset($_POST['permiso_tok']) ? (array) $_POST['permiso_tok'] : array();
    $tokensValidos = array_keys($tokensMap);
    $nombresSeleccionados = array();
    foreach ($tokensRecibidos as $tok) {
        $tok = (string) $tok;
        if (!in_array($tok, $tokensValidos, true)) {
            continue;
        }
        $nombresSeleccionados = array_merge($nombresSeleccionados, $tokensMap[$tok]);
    }
    $nombresSeleccionados = array_values(array_unique($nombresSeleccionados));

    $quotedNombres = array();
    foreach ($nombresSeleccionados as $nom) {
        $quotedNombres[] = "'" . mysqli_real_escape_string($conexion, $nom) . "'";
    }

    mysqli_query($conexion, "DELETE FROM detalle_permisos WHERE id_usuario = $id_user");

    $success = true;
    if (!empty($quotedNombres)) {
        $inList = implode(',', $quotedNombres);
        $qIds = mysqli_query($conexion, "SELECT id FROM permisos WHERE nombre IN ($inList)");
        if ($qIds) {
            while ($row = mysqli_fetch_assoc($qIds)) {
                $pid = (int) $row['id'];
                if ($pid <= 0) {
                    continue;
                }
                $ins = mysqli_query(
                    $conexion,
                    "INSERT INTO detalle_permisos(id_usuario, id_permiso) VALUES ($id_user, $pid)"
                );
                $success = $success && $ins;
            }
        }
    }

    if ($success) {
        header("Location: rol.php?id=" . $id_user . "&m=si");
        exit();
    }
}

include_once "includes/header.php";

$usuarios = mysqli_query($conexion, "SELECT nombre, usuario FROM usuario WHERE idusuario = $id LIMIT 1");
$filaUsuario = $usuarios ? mysqli_fetch_assoc($usuarios) : null;
if (empty($filaUsuario)) {
    header("Location: usuarios.php");
    exit();
}

$consulta = mysqli_query($conexion, "SELECT d.id_permiso FROM detalle_permisos d WHERE d.id_usuario = $id");
$asignadosPorId = array();
if ($consulta) {
    while ($row = mysqli_fetch_assoc($consulta)) {
        $asignadosPorId[(int) $row['id_permiso']] = true;
    }
}

$nombresGestion = mayorista_permisos_nombres_gestionables_rol();
$quotedGestion = array();
foreach ($nombresGestion as $nom) {
    $quotedGestion[] = "'" . mysqli_real_escape_string($conexion, $nom) . "'";
}
$idsPorNombre = array();
if (!empty($quotedGestion)) {
    $inG = implode(',', $quotedGestion);
    $qMap = mysqli_query($conexion, "SELECT id, nombre FROM permisos WHERE nombre IN ($inG)");
    if ($qMap) {
        while ($row = mysqli_fetch_assoc($qMap)) {
            $idsPorNombre[$row['nombre']] = (int) $row['id'];
        }
    }
}

function rol_permiso_item_activo($nombresFila, $idsPorNombre, $asignadosPorId)
{
    foreach ($nombresFila as $nombre) {
        if (empty($idsPorNombre[$nombre])) {
            continue;
        }
        $pid = $idsPorNombre[$nombre];
        if (!empty($asignadosPorId[$pid])) {
            return true;
        }
    }
    return false;
}

$catalogo = mayorista_permisos_catalogo_para_rol();
?>

<div class="rol-permisos-page fade-in-container">
    <div class="page-header-modern mb-4">
        <h2><i class="fas fa-key mr-2"></i> Permisos del usuario</h2>
        <p class="mb-0 mt-2">
            <i class="fas fa-user mr-1 text-primary"></i>
            <?php echo htmlspecialchars($filaUsuario['nombre']); ?>
            <span class="text-muted">(<?php echo htmlspecialchars($filaUsuario['usuario']); ?>)</span>
        </p>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="card card-modern rol-permisos-card">
                <div class="card-body-modern">
                    <form method="post" action="rol.php?id=<?php echo $id; ?>">
                        <?php if (isset($_GET['m']) && $_GET['m'] === 'si') { ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle mr-2"></i>Permisos actualizados correctamente.
                            </div>
                        <?php } ?>

                        <p class="text-muted small mb-4">
                            Activá o desactivá el acceso a cada módulo. Los permisos obsoletos (historia clínica, calendario, cristales) ya no se muestran.
                        </p>

                        <?php foreach ($catalogo as $grupo) { ?>
                            <div class="rol-permisos-grupo mb-4">
                                <h3 class="rol-permisos-grupo-titulo">
                                    <i class="fas fa-layer-group mr-2"></i><?php echo htmlspecialchars($grupo['titulo']); ?>
                                </h3>
                                <div class="row">
                                    <?php foreach ($grupo['items'] as $item) {
                                        $token = $item['token'];
                                        $nombresFila = isset($item['expandir_a']) && is_array($item['expandir_a'])
                                            ? $item['expandir_a']
                                            : array($token);
                                        $activo = rol_permiso_item_activo($nombresFila, $idsPorNombre, $asignadosPorId);
                                        $icon = isset($item['icon']) ? $item['icon'] : 'shield-alt';
                                        $idInput = 'perm_tok_' . preg_replace('/[^a-z0-9_]/i', '_', $token);
                                        ?>
                                        <div class="col-12 col-md-6 col-lg-4 mb-3">
                                            <div class="rol-permiso-item d-flex align-items-center justify-content-between">
                                                <label class="rol-permiso-label mb-0" for="<?php echo htmlspecialchars($idInput); ?>">
                                                    <i class="fas fa-<?php echo htmlspecialchars($icon); ?> rol-permiso-icon"></i>
                                                    <span><?php echo htmlspecialchars($item['etiqueta']); ?></span>
                                                </label>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" name="permiso_tok[]"
                                                           value="<?php echo htmlspecialchars($token); ?>"
                                                           id="<?php echo htmlspecialchars($idInput); ?>"
                                                        <?php echo $activo ? ' checked' : ''; ?>>
                                                    <label class="custom-control-label" for="<?php echo htmlspecialchars($idInput); ?>"></label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>

                        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between pt-2 border-top border-secondary rol-permisos-acciones">
                            <a href="usuarios.php" class="btn btn-outline-secondary mb-2 mb-sm-0">
                                <i class="fas fa-arrow-left mr-2"></i>Volver a usuarios
                            </a>
                            <button class="btn btn-modern-primary btn-modern-icon" type="submit" name="guardar_permisos" value="1">
                                <i class="fas fa-save mr-2"></i>Guardar permisos
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once "includes/footer.php"; ?>
