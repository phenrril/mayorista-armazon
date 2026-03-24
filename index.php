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
    <meta name="description" content="Sistema de Gestión Óptica" />
    <meta name="author" content="" />
    <title>Iniciar Sesión - Sistema Óptica</title>
    <link href="assets/css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <img src="assets/img/logo.png" alt="Logo" class="logo">
            </div>
            
            <div class="login-header">
                <h1><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</h1>
                <p>Ingrese sus credenciales para continuar</p>
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

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    width: 100%;
    height: 100%;
    font-family: 'Poppins', sans-serif;
    overflow: hidden;
}

body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
}

body::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23ffffff" fill-opacity="0.03"><circle cx="30" cy="30" r="1.5"/></g></svg>');
    animation: backgroundMove 20s linear infinite;
}

@keyframes backgroundMove {
    0% { transform: translate(0, 0); }
    100% { transform: translate(60px, 60px); }
}

.login-container {
    width: 100%;
    max-width: 420px;
    padding: 20px;
    z-index: 1;
}

.login-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 24px;
    padding: 40px 35px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.5s ease-out;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.logo-section {
    text-align: center;
    margin-bottom: 30px;
}

.logo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #667eea;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    transition: transform 0.3s ease;
}

.logo:hover {
    transform: scale(1.05);
}

.login-header {
    text-align: center;
    margin-bottom: 35px;
}

.login-header h1 {
    font-size: 28px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.login-header h1 i {
    color: #667eea;
}

.login-header p {
    color: #718096;
    font-size: 14px;
    font-weight: 400;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 18px;
    color: #667eea;
    font-size: 16px;
    z-index: 2;
    transition: color 0.3s ease;
}

.input-group input {
    width: 100%;
    padding: 14px 18px 14px 50px;
    font-size: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    background: #f7fafc;
    color: #2d3748;
    transition: all 0.3s ease;
    outline: none;
    font-family: 'Poppins', sans-serif;
}

.input-group input::placeholder {
    color: #a0aec0;
}

.input-group input:focus {
    border-color: #667eea;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.input-group input:focus + .toggle-password,
.input-group input:valid + .toggle-password {
    color: #667eea;
}

.toggle-password {
    position: absolute;
    right: 18px;
    color: #a0aec0;
    cursor: pointer;
    font-size: 16px;
    transition: color 0.3s ease;
    z-index: 2;
}

.toggle-password:hover {
    color: #667eea;
}

.alert-message {
    margin-top: -5px;
    animation: shake 0.5s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-danger {
    background: #fee;
    color: #c53030;
    border: 1px solid #feb2b2;
}

.login-button {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    position: relative;
    overflow: hidden;
    font-family: 'Poppins', sans-serif;
}

.login-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s;
}

.login-button:hover::before {
    left: 100%;
}

.login-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
}

.login-button:active {
    transform: translateY(0);
}

.button-text {
    transition: margin-right 0.3s ease;
}

.login-button:hover .button-icon {
    animation: arrowMove 0.6s ease infinite;
}

@keyframes arrowMove {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(5px); }
}

.login-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.login-button:disabled:hover {
    transform: none;
}

/* Responsive Design */
@media (max-width: 480px) {
    .login-container {
        padding: 15px;
    }
    
    .login-card {
        padding: 30px 25px;
    }
    
    .login-header h1 {
        font-size: 24px;
    }
    
    .logo {
        width: 80px;
        height: 80px;
    }
}

/* Loading state */
.login-button.loading .button-text {
    margin-right: 10px;
}

.login-button.loading .button-icon {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Focus visible for accessibility */
.login-button:focus-visible {
    outline: 3px solid rgba(102, 126, 234, 0.5);
    outline-offset: 2px;
}

input:focus-visible {
    outline: 3px solid rgba(102, 126, 234, 0.3);
    outline-offset: -1px;
}
</style>

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

<style>
@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}
</style>
