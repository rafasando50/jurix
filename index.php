<?php
/**
 * Pantalla de Login - Sistema de Gestión Documental y Poderes
 * Ruta: /index.php
 */

session_start();

// Si el usuario ya está autenticado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Obtener y limpiar errores de login anteriores si existen
$error_message = "";
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Limpiar el error para que no aparezca al recargar
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SISCORL</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Estilos Personalizados -->
    <link rel="stylesheet" href="assets/css/style.css?v=1.1">
</head>
<body>

    <div class="container d-flex align-items-center justify-content-center min-vh-100 p-3">
        <div class="login-card animate-fade-in">
            <!-- Icono Decorativo -->
            <div class="logo-container">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            
            <!-- Encabezado -->
            <h2 class="text-center fw-bold mb-1 fs-3 text-dark">Bienvenido</h2>
            <p class="text-center text-muted mb-4 fs-6">SISCORL</p>

            <!-- Mensaje de Error (si existe) -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger d-flex align-items-center border-0 bg-danger bg-opacity-25 text-danger-emphasis rounded-3 mb-4 animate-fade-in" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
                    <div>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulario de Login -->
            <form action="auth/login.php" method="POST" autocomplete="off">
                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted" style="border-radius: 12px 0 0 12px; border-color: #cbd5e1;">
                            <i class="fa-regular fa-envelope"></i>
                        </span>
                        <input type="email" class="form-control border-start-0" id="email" name="email" placeholder="nombre@correo.com" required style="border-radius: 0 12px 12px 0;">
                    </div>
                </div>

                <!-- Contraseña -->
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted" style="border-radius: 12px 0 0 12px; border-color: #cbd5e1;">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="••••••••" required style="border-radius: 0 12px 12px 0;">
                    </div>
                </div>

                <!-- Botón de Ingreso -->
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary-custom py-2 text-uppercase tracking-wider">
                        Ingresar <i class="fa-solid fa-arrow-right-to-bracket ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
