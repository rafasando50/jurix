<?php
/**
 * Layout Sidebar - Barra lateral de navegación
 * Ruta: /includes/sidebar.php
 */
?>
<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Consultar notificaciones de documentos por vencer (próximos 15 días)
$notify_count = 0;
$expiring_docs = [];
$hoy = date('Y-m-d');
$limite = date('Y-m-d', strtotime('+15 days'));

if (isset($pdo)) {
    try {
        $stmt_notify = $pdo->prepare("
            SELECT id, numero_instrumento, libro, tipo, subtipo, concepto, vigencia 
            FROM documentos 
            WHERE vigencia IS NOT NULL 
              AND vigencia >= :hoy 
              AND vigencia <= :limite
            ORDER BY vigencia ASC
        ");
        $stmt_notify->execute(['hoy' => $hoy, 'limite' => $limite]);
        $expiring_docs = $stmt_notify->fetchAll();
        $notify_count = count($expiring_docs);
    } catch (PDOException $e) {
        error_log("Error al consultar notificaciones en sidebar: " . $e->getMessage());
    }
}
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <div class="logo-container m-0" style="width: 40px; height: 40px; border-radius: 10px;">
            <i class="fa-solid fa-folder-open" style="font-size: 1.2rem;"></i>
        </div>
        <span class="fw-bold fs-5 tracking-wide text-dark">SGD Poderes</span>
    </div>

    <ul class="components">
        <li class="<?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <a href="dashboard.php">
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="mt-4">
            <span class="text-uppercase text-muted px-3 fw-bold" style="font-size: 0.75rem; letter-spacing: 1px;">Gestión Documental</span>
        </li>
        <li class="<?php echo ($current_page === 'documentos.php' && $current_tipo === '') ? 'active' : ''; ?>">
            <a href="documentos.php">
                <i class="fa-solid fa-folder-open"></i>
                <span>Todos los Documentos</span>
            </a>
        </li>
        <li class="<?php echo ($current_page === 'documentos.php' && $current_tipo === 'acta') ? 'active' : ''; ?>">
            <a href="documentos.php?tipo=acta">
                <i class="fa-solid fa-file-signature"></i>
                <span>Actas y Asambleas</span>
            </a>
        </li>
        <li class="<?php echo ($current_page === 'documentos.php' && $current_tipo === 'poder') ? 'active' : ''; ?>">
            <a href="documentos.php?tipo=poder">
                <i class="fa-solid fa-scroll"></i>
                <span>Poderes Jurídicos</span>
            </a>
        </li>
        <li class="<?php echo ($current_page === 'documentos.php' && $current_tipo === 'revocacion') ? 'active' : ''; ?>">
            <a href="documentos.php?tipo=revocacion">
                <i class="fa-solid fa-file-circle-xmark"></i>
                <span>Revocaciones</span>
            </a>
        </li>
        <li class="<?php echo ($current_page === 'acreditados.php') ? 'active' : ''; ?>">
            <a href="acreditados.php">
                <i class="fa-solid fa-users"></i>
                <span>Acreditados</span>
            </a>
        </li>
        <li class="position-relative">
            <a href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNotifications" aria-controls="offcanvasNotifications" class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-bell"></i>
                    <span>Notificaciones</span>
                </div>
                <?php if ($notify_count > 0): ?>
                    <span class="badge bg-danger rounded-pill px-2 py-1" style="font-size: 0.75rem;"><?php echo $notify_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php if ($_SESSION['user_rol'] !== 'usuario'): ?>
        <li class="mt-4">
            <span class="text-uppercase text-muted px-3 fw-bold" style="font-size: 0.75rem; letter-spacing: 1px;">Configuración</span>
        </li>
        <li class="<?php echo ($current_page === 'usuarios.php') ? 'active' : ''; ?>">
            <a href="usuarios.php">
                <i class="fa-solid fa-users-gear"></i>
                <span>Usuarios</span>
            </a>
        </li>
        <li class="<?php echo ($current_page === 'empresas.php') ? 'active' : ''; ?>">
            <a href="empresas.php">
                <i class="fa-solid fa-building"></i>
                <span>Empresas</span>
            </a>
        </li>
        <?php endif; ?>
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

<!-- Drawer de Notificaciones -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNotifications" aria-labelledby="offcanvasNotificationsLabel" style="border-left: 1px solid var(--border-color); background: var(--bg-body); max-width: 100%; width: 380px;">
    <div class="offcanvas-header border-bottom py-3" style="background: #ffffff;">
        <h5 class="offcanvas-title fw-bold text-dark d-flex align-items-center gap-2" id="offcanvasNotificationsLabel">
            <i class="fa-solid fa-bell text-primary fa-fade"></i> Alertas de Vencimiento
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-3">
        <?php if ($notify_count > 0): ?>
            <div class="alert alert-warning border-0 rounded-3 mb-3 d-flex align-items-start gap-2 animate-fade-in" style="font-size: 0.85rem; background-color: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2) !important;">
                <i class="fa-solid fa-circle-exclamation fs-5 text-warning mt-0.5"></i>
                <div class="text-dark">
                    Tienes <strong><?php echo $notify_count; ?></strong> documento(s) próximo(s) a vencer en los siguientes 15 días.
                </div>
            </div>
            
            <div class="d-flex flex-column gap-3">
                <?php foreach ($expiring_docs as $doc): 
                    // Calcular días restantes
                    $diff = strtotime($doc['vigencia']) - strtotime($hoy);
                    $days = (int)floor($diff / (60 * 60 * 24));
                    
                    // Definir color del badge de urgencia
                    $badge_class = 'bg-danger text-light'; // 0-4 días
                    $badge_style = '';
                    if ($days >= 10) {
                        $badge_class = 'bg-info text-light'; // 10-15 días
                    } elseif ($days >= 5) {
                        $badge_class = '';
                        $badge_style = 'background-color: #f97316; color: #ffffff;'; // 5-9 días (Naranja)
                    }
                    
                    // Mapeo de tipos de documentos para mejor visualización
                    $tipo_label = 'Documento';
                    $tipo_color = 'primary';
                    if ($doc['tipo'] === 'acta') {
                        $tipo_label = 'Acta / Asamblea';
                        $tipo_color = 'info';
                    } elseif ($doc['tipo'] === 'poder') {
                        $tipo_label = 'Poder Jurídico';
                        $tipo_color = 'success';
                    } elseif ($doc['tipo'] === 'revocacion') {
                        $tipo_label = 'Revocación';
                        $tipo_color = 'danger';
                    }
                ?>
                    <div class="card border-0 rounded-3 p-3 shadow-sm animate-fade-in" style="background: #ffffff; border-left: 5px solid <?php 
                        if ($doc['tipo'] === 'acta') echo '#0e7490';
                        elseif ($doc['tipo'] === 'poder' && $doc['subtipo'] === 'poder_amplio') echo '#15803d';
                        elseif ($doc['tipo'] === 'poder') echo '#b45309';
                        else echo '#1d4ed8';
                    ?> !important; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-<?php echo $tipo_color; ?> bg-opacity-10 text-<?php echo $tipo_color; ?> rounded-pill" style="font-size: 0.75rem;">
                                <?php echo $tipo_label; ?>
                            </span>
                            <span class="badge rounded-pill <?php echo $badge_class; ?>" style="font-size: 0.75rem; <?php echo $badge_style; ?>">
                                <?php 
                                if ($days === 0) {
                                    echo 'Vence hoy';
                                } elseif ($days === 1) {
                                    echo 'Vence mañana';
                                } else {
                                    echo 'Vence en ' . $days . ' días';
                                }
                                ?>
                            </span>
                        </div>
                        
                        <h6 class="fw-bold text-dark mb-1" style="font-size: 0.9rem;">
                            Instrumento: <?php echo htmlspecialchars($doc['numero_instrumento']); ?> (Libro <?php echo htmlspecialchars($doc['libro']); ?>)
                        </h6>
                        <p class="text-muted mb-2" style="font-size: 0.8rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; white-space: normal;" title="<?php echo htmlspecialchars($doc['concepto']); ?>">
                            <?php echo htmlspecialchars($doc['concepto']); ?>
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top" style="border-color: #f1f5f9 !important;">
                            <span class="text-muted" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-calendar me-1"></i> Vence: <?php echo date('d/m/Y', strtotime($doc['vigencia'])); ?>
                            </span>
                            <a href="documento_editar.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2 rounded-2" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-pen-to-square"></i> Ver / Editar
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-muted animate-fade-in" style="width: 60px; height: 60px;">
                    <i class="fa-solid fa-bell-slash fs-4"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1 animate-fade-in">Sin alertas de vencimiento</h6>
                <p class="text-muted mb-0 px-3 animate-fade-in" style="font-size: 0.8rem;">No hay documentos próximos a vencer en los siguientes 15 días.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

