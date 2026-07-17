<?php
/**
 * Layout Sidebar - Barra lateral de navegación
 * Ruta: /includes/sidebar.php
 */
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <div class="logo-container m-0" style="width: 40px; height: 40px; border-radius: 10px;">
            <i class="fa-solid fa-folder-open" style="font-size: 1.2rem;"></i>
        </div>
        <span class="fw-bold fs-5 tracking-wide text-dark">SGD Poderes</span>
    </div>

    <ul class="components">
        <li class="active">
            <a href="dashboard.php">
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="mt-4">
            <span class="text-uppercase text-muted px-3 fw-bold" style="font-size: 0.75rem; letter-spacing: 1px;">Gestión de Poderes</span>
        </li>
        <li>
            <a href="#">
                <i class="fa-solid fa-file-signature"></i>
                <span>Actas Constitutivas</span>
            </a>
        </li>
        <li>
            <a href="#">
                <i class="fa-solid fa-users-rectangle"></i>
                <span>Asambleas</span>
            </a>
        </li>
        <li>
            <a href="#">
                <i class="fa-solid fa-scroll"></i>
                <span>Poderes Otorgados</span>
            </a>
        </li>
        <li class="mt-4">
            <span class="text-uppercase text-muted px-3 fw-bold" style="font-size: 0.75rem; letter-spacing: 1px;">Configuración</span>
        </li>
        <li>
            <a href="#">
                <i class="fa-solid fa-users-gear"></i>
                <span>Usuarios</span>
            </a>
        </li>
    </ul>

    <!-- Info del usuario y Logout en la parte inferior del Sidebar -->
    <div class="p-3 border-top mt-auto" style="border-color: var(--border-color) !important;">
        <div class="d-flex align-items-center mb-3">
            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2 text-primary" style="width: 36px; height: 36px;">
                <i class="fa-regular fa-user"></i>
            </div>
            <div class="overflow-hidden">
                <div class="fw-bold text-dark text-truncate" style="font-size: 0.85rem;" title="<?php echo htmlspecialchars($_SESSION['user_nombre']); ?>">
                    <?php echo htmlspecialchars($_SESSION['user_nombre']); ?>
                </div>
                <div class="text-muted text-truncate" style="font-size: 0.75rem;" title="<?php echo htmlspecialchars($_SESSION['user_email']); ?>">
                    <?php echo htmlspecialchars($_SESSION['user_email']); ?>
                </div>
            </div>
        </div>
        <a href="auth/logout.php" class="btn btn-outline-danger btn-sm w-100 rounded-3 d-flex align-items-center justify-content-center gap-2 py-2">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</nav>
