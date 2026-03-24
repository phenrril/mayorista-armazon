<?php include "includes/header.php"; ?>

<style>
.access-denied-container {
    min-height: 60vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

.access-denied-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    max-width: 550px;
    margin: 0 auto;
    animation: slideIn 0.6s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card-header-beautiful {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-align: center;
    padding: 40px 30px;
    border: none;
}

.card-header-beautiful i {
    font-size: 80px;
    margin-bottom: 20px;
    display: block;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

.card-header-beautiful h2 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 10px 0;
}

.card-header-beautiful p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.card-body-beautiful {
    padding: 40px 30px;
    background: white;
    text-align: center;
}

.message-text {
    font-size: 1.1rem;
    color: #555;
    margin-bottom: 35px;
    line-height: 1.6;
}

.btn-return-beautiful {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 12px;
    padding: 15px 50px;
    font-size: 1.1rem;
    font-weight: 600;
    color: white;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}

.btn-return-beautiful:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6);
    color: white;
    text-decoration: none;
}

.decorative-icon {
    font-size: 60px;
    opacity: 0.1;
    margin-bottom: 20px;
}
</style>

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