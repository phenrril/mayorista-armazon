<?php
// La sesión ya debería estar iniciada desde el archivo que incluye este header
// session_start(); ya no es necesario aquí
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Armazón | Panel</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet" />
    <link href="../assets/css/dataTables.bootstrap4.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/js/jquery-ui/jquery-ui.min.css">
    <link href="../assets/css/dark-premium.css" rel="stylesheet" />
    
    <!-- Cargar Font Awesome de forma asíncrona para no bloquear renderizado -->
    <script src="../assets/js/all.min.js" defer></script>
    
</head>

<body class="sb-nav-fixed app-shell">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <a class="navbar-brand" href="index.php" aria-label="Argen Optik">
            <span class="navbar-brand-frame">
                <img src="../assets/img/logo-login.png" alt="Argen Optik" class="navbar-brand-logo">
            </span>
        </a>
        <button class="btn btn-link btn-sm order-1 order-lg-0" id="sidebarToggle" href="#"><i class="fas fa-bars"></i></button>

        <!-- Navbar-->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" id="userDropdown" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-user fa-fw"></i></a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#nuevo_pass">Perfil</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="salir.php">Cerrar Sesión</a>
                </div>
            </li>
        </ul>
    </nav>
    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <?php
                        if (!function_exists('mayorista_nav_link_visible')) {
                            require_once __DIR__ . '/mayorista_helpers.php';
                        }
                        $navUid = isset($_SESSION['idUser']) ? (int) $_SESSION['idUser'] : 0;
                        $navCx = isset($conexion) && $conexion instanceof mysqli ? $conexion : null;
                        ?>
                        <?php if (mayorista_nav_link_visible($navCx, $navUid, array('nueva_venta', 'ventas'))) { ?>
                        <a class="nav-link" href="ventas.php"><div class="sb-nav-link-icon"><i class="fas fa-shopping-cart"></i></div> Nueva venta</a>
                        <?php } ?>
                        <?php if (mayorista_nav_link_visible($navCx, $navUid, array('ventas'))) { ?>
                        <a class="nav-link" href="lista_ventas.php"><div class="sb-nav-link-icon"><i class="fas fa-receipt"></i></div> Ventas</a>
                        <?php } ?>
                        <?php if (mayorista_nav_link_visible($navCx, $navUid, array('clientes'))) { ?>
                        <a class="nav-link" href="clientes.php"><div class="sb-nav-link-icon"><i class="fas fa-users"></i></div> Clientes</a>
                        <?php } ?>
                        <?php if (mayorista_nav_link_visible($navCx, $navUid, array('cuenta_corriente', 'clientes'))) { ?>
                        <a class="nav-link" href="cuenta_corriente.php"><div class="sb-nav-link-icon"><i class="fas fa-file-invoice-dollar"></i></div> Cuenta corriente</a>
                        <?php } ?>
                        <?php if (mayorista_nav_link_visible($navCx, $navUid, array('productos'))) { ?>
                        <a class="nav-link" href="productos.php"><div class="sb-nav-link-icon"><i class="fas fa-glasses"></i></div> Productos</a>
                        <?php } ?>
                        <?php if (mayorista_nav_link_visible($navCx, $navUid, array('estadisticas'))) { ?>
                        <a class="nav-link" href="estadisticas.php"><div class="sb-nav-link-icon"><i class="fas fa-chart-line"></i></div> Estadisticas</a>
                        <?php } ?>
                        <?php if (mayorista_nav_link_visible($navCx, $navUid, array('reportes', 'reporte'))) { ?>
                        <a class="nav-link" href="reporte.php"><div class="sb-nav-link-icon"><i class="fas fa-chart-bar"></i></div> Reportes</a>
                        <?php } ?>
                        <?php if (mayorista_nav_link_visible($navCx, $navUid, array('api_config'))) { ?>
                        <a class="nav-link" href="api_config.php"><div class="sb-nav-link-icon"><i class="fas fa-key"></i></div> API</a>
                        <?php } ?>
                        <?php if (mayorista_nav_link_visible($navCx, $navUid, array('usuarios'))) { ?>
                        <a class="nav-link" href="usuarios.php"><div class="sb-nav-link-icon"><i class="fas fa-user"></i></div> Usuarios </a>
                        <?php } ?>
                        <?php if (mayorista_nav_link_visible($navCx, $navUid, array('configuracion'))) { ?>
                        <a class="nav-link" href="configuracion_sistema.php"><div class="sb-nav-link-icon"><i class="fas fa-cogs"></i></div> Configuracion </a>
                        <?php } ?>
                    </div>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid mt-2">
                    <?php
                    $alertasFinancierasHeader = array();
                    if (isset($conexion) && ($conexion instanceof mysqli) && function_exists('mayorista_obtener_alertas_financieras')) {
                        $alertasFinancierasHeader = mayorista_obtener_alertas_financieras($conexion, 3);
                    }
                    if (!empty($alertasFinancierasHeader)) {
                    ?>
                    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap" role="alert">
                        <div>
                            <strong>Recordatorios pendientes:</strong>
                            Tenés <?php echo count($alertasFinancierasHeader); ?> compromiso<?php echo count($alertasFinancierasHeader) === 1 ? '' : 's'; ?> para revisar hoy.
                        </div>
                        <a class="btn btn-sm btn-outline-dark mt-2 mt-md-0" href="reporte.php#vencimientos-financieros">Ver vencimientos</a>
                    </div>
                    <?php } ?>