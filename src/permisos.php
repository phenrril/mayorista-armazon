<?php include "includes/header.php"; ?>
<div class="access-denied-container">
    <div class="card access-denied-card">
        <div class="card-header-beautiful">
            <i class="fas fa-shield-alt"></i>
            <h2>Acceso Restringido</h2>
            <p>No tienes permisos para acceder a esta sección</p>
        </div>
        <div class="card-body-beautiful">
            <i class="fas fa-lock decorative-icon text-primary"></i>
            <p class="message-text">
                Lo sentimos, no cuentas con los permisos necesarios para ver esta página. 
                Si necesitas acceso a esta funcionalidad, contacta con el administrador del sistema.
            </p>
            <a href="index.php" class="btn-return-beautiful">
                <i class="fas fa-arrow-left mr-2"></i> Volver al Inicio
            </a>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>