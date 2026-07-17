<?php
/**
 * Panel Principal (Dashboard) - Vista Protegida
 * Ruta: /dashboard.php
 */

// Incluir cabecera (incluye validación de sesión y estilos)
require_once __DIR__ . '/includes/header.php';

// Incluir barra lateral de navegación
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Área de Contenido Principal -->
<div class="content-area">
    
    <!-- Barra Superior de Navegación Rápida -->
    <nav class="navbar navbar-top navbar-expand-lg navbar-light bg-transparent">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1 fw-bold fs-4">Dashboard Principal</span>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="badge bg-success rounded-pill px-3 py-2 d-flex align-items-center gap-2">
                    <span class="spinner-grow spinner-grow-sm text-light" role="status" style="width: 8px; height: 8px; animation-duration: 1.5s;"></span>
                    Sistema Online
                </span>
            </div>
        </div>
    </nav>

    <!-- Contenido del Dashboard -->
    <div class="container-fluid p-0">
        
        <!-- Tarjeta de Bienvenida -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="p-4 rounded-4" style="background: linear-gradient(135deg, rgba(29, 78, 216, 0.08) 0%, rgba(30, 64, 175, 0.04) 100%); border: 1px solid rgba(29, 78, 216, 0.15);">
                    <h2 class="fw-bold text-dark mb-2">¡Hola de nuevo, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?>!</h2>
                    <p class="text-muted mb-0 fs-5">Bienvenido al Sistema de Gestión Documental y Poderes Jurídicos. Desde aquí puedes gestionar las actas, asambleas y alcances legales.</p>
                </div>
            </div>
        </div>

        <!-- Fila de Estadísticas Rápidas (Poderes y Actas) -->
        <div class="row g-4 mb-4">
            <!-- Total de Documentos -->
            <div class="col-md-6 col-lg-3">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Documentos</span>
                        <h3 class="fw-bold mb-0 text-dark">0</h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4" style="font-size: 1.5rem;">
                        <i class="fa-solid fa-file-pdf"></i>
                    </div>
                </div>
            </div>

            <!-- Actas Constitutivas -->
            <div class="col-md-6 col-lg-3">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Actas / Asambleas</span>
                        <h3 class="fw-bold mb-0 text-dark">0</h3>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-4" style="font-size: 1.5rem;">
                        <i class="fa-solid fa-gavel"></i>
                    </div>
                </div>
            </div>

            <!-- Poderes Amplios -->
            <div class="col-md-6 col-lg-3">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Poderes Amplios</span>
                        <h3 class="fw-bold mb-0 text-dark">0</h3>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-4" style="font-size: 1.5rem;">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                </div>
            </div>

            <!-- Poderes Especiales -->
            <div class="col-md-6 col-lg-3">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Poderes Especiales</span>
                        <h3 class="fw-bold mb-0 text-dark">0</h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-4" style="font-size: 1.5rem;">
                        <i class="fa-solid fa-key"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fila de Accesos Rápidos y Última Actividad -->
        <div class="row g-4">
            <!-- Módulo de Poderes - Contexto del Negocio -->
            <div class="col-lg-8">
                <div class="p-4 rounded-4" style="background: #ffffff; border: 1px solid #e2e8f0; min-height: 350px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h4 class="fw-bold text-dark mb-0">Últimos Poderes Capturados</h4>
                        <button class="btn btn-primary-custom btn-sm py-2 px-3 rounded-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Capturar Poder
                        </button>
                    </div>
                    
                    <!-- Tabla vacía ilustrativa -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="--bs-table-bg: transparent; --bs-table-border-color: #e2e8f0;">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Origen (Acta/Asamblea)</th>
                                    <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Alcance</th>
                                    <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Facultades</th>
                                    <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">PDF Documento</th>
                                    <th scope="col" class="text-muted fw-semibold py-3 text-end" style="font-size: 0.85rem;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fa-regular fa-folder-open d-block fs-1 mb-3 opacity-50"></i>
                                        Aún no hay poderes jurídicos registrados en el sistema.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Accesos directos y ayuda -->
            <div class="col-lg-4">
                <div class="p-4 rounded-4 mb-4" style="background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
                    <h5 class="fw-bold text-dark mb-3">Información del Negocio</h5>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 mt-1">
                                <i class="fa-solid fa-landmark"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.9rem;">Origen Legal</h6>
                                <p class="text-muted mb-0" style="font-size: 0.8rem;">Los documentos capturados corresponden a Actas Constitutivas o Asambleas Extraordinarias/Ordinarias.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <div class="bg-success bg-opacity-10 text-success p-2 rounded-3 mt-1">
                                <i class="fa-solid fa-scale-balanced"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.9rem;">Alcance de Poderes</h6>
                                <p class="text-muted mb-0" style="font-size: 0.8rem;">Clasificación obligatoria en Poder Amplio o Poder Especial con facultades bien definidas.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <div class="bg-warning bg-opacity-10 text-warning p-2 rounded-3 mt-1">
                                <i class="fa-solid fa-list-check"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.9rem;">Facultades Clave</h6>
                                <p class="text-muted mb-0" style="font-size: 0.8rem;">Facultad de Actos de Administración, Pleitos y Cobranzas, Actos de Dominio, Títulos de Crédito, etc.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
// Incluir el pie de página (cierra contenedores e importa JS)
require_once __DIR__ . '/includes/footer.php';
?>
