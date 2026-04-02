<?php
// Habilitar captura de errores temprano
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Registrar función para capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        // Error fatal ocurrió
        header('Content-Type: application/json');
        echo json_encode(array(
            'error' => true,
            'type' => 'fatal_error',
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ));
    }
});

session_start();

if (!empty($_SESSION['active'])) {
    header('location: src/');
} else {
    if (!empty($_POST)) {
        $alert = '';
        if (empty($_POST['usuario']) || empty($_POST['clave'])) {
            $alert = '<div class="alert alert-danger" role="alert">
            Ingrese su usuario y su clave
            </div>';
        } else {
            require_once "conexion.php";
            
            // Verificar si hubo error de conexión
            if (isset($GLOBALS['db_connection_error'])) {
                $error_info = $GLOBALS['db_connection_error'];
                $error_details = json_encode($error_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                
                $alert = '<div class="alert alert-danger" role="alert">
                <strong>Error de conexión a la base de datos</strong><br>
                Por favor, verifique la configuración de conexión.
                </div>';
                
                // Log detallado en consola
                echo '<script>';
                echo 'console.group("❌ Error de Conexión a Base de Datos");';
                echo 'console.error("Mensaje:", ' . json_encode($error_info['message']) . ');';
                echo 'console.error("Código:", ' . json_encode($error_info['code']) . ');';
                echo 'console.error("Host:", ' . json_encode($error_info['host']) . ');';
                echo 'console.error("Puerto:", ' . json_encode($error_info['port']) . ');';
                echo 'console.error("Base de datos:", ' . json_encode($error_info['database']) . ');';
                echo 'console.error("Usuario:", ' . json_encode($error_info['user']) . ');';
                echo 'console.error("Detalles completos:", ' . $error_details . ');';
                echo 'console.groupEnd();';
                echo '</script>';
            } elseif (!$conexion || mysqli_connect_errno()) {
                $error_msg = mysqli_connect_error();
                $error_code = mysqli_connect_errno();
                
                $alert = '<div class="alert alert-danger" role="alert">
                <strong>Error de conexión a la base de datos</strong><br>
                Por favor, verifique la configuración de conexión.
                </div>';
                
                // Log de error en consola del navegador
                echo '<script>';
                echo 'console.group("❌ Error de Conexión MySQLi");';
                echo 'console.error("Mensaje:", ' . json_encode($error_msg) . ');';
                echo 'console.error("Código:", ' . json_encode($error_code) . ');';
                echo 'console.groupEnd();';
                echo '</script>';
            } elseif ($conexion && is_object($conexion)) {
                // Validar que $conexion es un objeto mysqli válido
                $user = mysqli_real_escape_string($conexion, $_POST['usuario']);
                $clave = md5(mysqli_real_escape_string($conexion, $_POST['clave']));
                $query = mysqli_query($conexion, "SELECT * FROM usuario WHERE usuario = '$user' AND clave = '$clave' AND estado = 1");
                
                // Verificar si la consulta se ejecutó correctamente
                if ($query === false) {
                    $alert = '<div class="alert alert-danger" role="alert">
                    Error al procesar la consulta. Por favor, intente más tarde.
                    </div>';
                    // Log de error en consola del navegador
                    echo '<script>console.error("SQL query error:", ' . json_encode(mysqli_error($conexion)) . ', "Consulta:", ' . json_encode("SELECT * FROM usuario WHERE usuario = '[sanitizado]' AND clave = MD5('[sanitizado]') AND estado = 1") . ');</script>';
                } else {
                    $resultado = mysqli_num_rows($query);
                    if ($resultado > 0) {
                        $dato = mysqli_fetch_array($query);
                        $_SESSION['active'] = true;
                        $_SESSION['idUser'] = $dato['idusuario'];
                        $_SESSION['nombre'] = $dato['nombre'];
                        $_SESSION['user'] = $dato['usuario'];
                        mysqli_close($conexion);
                        header('location: src/');
                        exit();
                    } else {
                        $alert = '<div class="alert alert-danger" role="alert">
                        Usuario o Contraseña Incorrecta
                        </div>';
                        session_destroy();
                    }
                }
                // Cerrar conexión solo si no se redirigió
                if (isset($conexion) && is_object($conexion)) {
                    mysqli_close($conexion);
                }
            } else {
                // Caso inesperado: conexión no es válida pero no se capturó error
                $alert = '<div class="alert alert-danger" role="alert">
                <strong>Error inesperado de conexión</strong><br>
                Estado de conexión no válido.
                </div>';
                echo '<script>console.error("Estado de conexión no válido");</script>';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Panel mayorista de armazones" />
    <meta name="author" content="" />
    <title>Acceso | Armazón</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet" />
    <link href="assets/css/dark-premium.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-frame">
                    <img src="assets/img/logo-login.png" alt="Argen Optik" class="logo">
                </div>
            </div>
            
            <div class="login-header">
                <h1><i class="fas fa-sign-in-alt"></i> Acceso</h1>
                <p>Ingresá al panel mayorista para gestionar ventas, stock y clientes.</p>
            </div>
            
            <form action="" method="POST" id="loginForm" class="login-form">
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input 
                        type="text" 
                        name="usuario" 
                        id="usuario" 
                        placeholder="Usuario" 
                        required="required"
                        autocomplete="username"
                    />
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input 
                        type="password" 
                        name="clave" 
                        id="clave" 
                        placeholder="Contraseña" 
                        required="required"
                        autocomplete="current-password"
                    />
                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                </div>
                
                <?php if(isset($alert)): ?>
                    <div class="alert-message">
                        <?php echo $alert; ?>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="login-button">
                    <span class="button-text">Ingresar</span>
                    <i class="fas fa-arrow-right button-icon"></i>
                </button>
            </form>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('clave');
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.querySelector('.login-button');
    
    // Toggle password visibility
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
    
    // Form submission animation
    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            loginButton.classList.add('loading');
            loginButton.disabled = true;
        });
    }
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Add auto-focus to first input if empty
    const usuarioInput = document.getElementById('usuario');
    if (usuarioInput && !usuarioInput.value) {
        setTimeout(() => usuarioInput.focus(), 100);
    }
});
</script>
</body>
</html>
