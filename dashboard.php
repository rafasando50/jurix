<?php
/**
 * Panel Principal (Dashboard) - Vista Protegida
 * Ruta: /dashboard.php
 */

// Incluir conexión a base de datos
require_once __DIR__ . '/config/db.php';

// Incluir cabecera (incluye validación de sesión y estilos)
require_once __DIR__ . '/includes/header.php';

// Incluir barra lateral de navegación
require_once __DIR__ . '/includes/sidebar.php';

// Obtener parámetro de empresa
$empresa_id = isset($_GET['empresa_id']) ? trim($_GET['empresa_id']) : '';

// Obtener lista de empresas para el selector
$empresas = [];
try {
    $stmt_emp = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC");
    $empresas = $stmt_emp->fetchAll();
} catch (PDOException $e) {
    error_log("Error al obtener lista de empresas para dashboard: " . $e->getMessage());
}

// Consultas para estadísticas
$total_docs = 0;
$total_actas = 0;
$total_poderes_amplios = 0;
$total_poderes_especiales = 0;
$ultimos_documentos = [];

try {
    // Total Documentos
    if ($empresa_id !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE empresa_id = :empresa_id");
        $stmt->execute(['empresa_id' => $empresa_id]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM documentos");
    }
    $total_docs = (int)$stmt->fetchColumn();

    // Total Actas/Asambleas
    if ($empresa_id !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE tipo = 'acta' AND empresa_id = :empresa_id");
        $stmt->execute(['empresa_id' => $empresa_id]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM documentos WHERE tipo = 'acta'");
    }
    $total_actas = (int)$stmt->fetchColumn();

    // Total Poderes Amplios
    if ($empresa_id !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE tipo = 'poder' AND subtipo = 'poder_amplio' AND empresa_id = :empresa_id");
        $stmt->execute(['empresa_id' => $empresa_id]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM documentos WHERE tipo = 'poder' AND subtipo = 'poder_amplio'");
    }
    $total_poderes_amplios = (int)$stmt->fetchColumn();

    // Total Poderes Especiales (Específicos o de Actas Administrativas)
    if ($empresa_id !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE tipo = 'poder' AND subtipo IN ('poder_especifico', 'poder_actas_administrativas') AND empresa_id = :empresa_id");
        $stmt->execute(['empresa_id' => $empresa_id]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM documentos WHERE tipo = 'poder' AND subtipo IN ('poder_especifico', 'poder_actas_administrativas')");
    }
    $total_poderes_especiales = (int)$stmt->fetchColumn();

    // Obtener los últimos 5 documentos registrados con LEFT JOIN para identificar revocaciones
    if ($empresa_id !== '') {
        $stmt = $pdo->prepare("SELECT d.*, e.nombre AS empresa_nombre, r.id AS revocacion_id, r.numero_instrumento AS revocacion_instrumento, r.libro AS revocacion_libro 
                             FROM documentos d 
                             LEFT JOIN empresas e ON d.empresa_id = e.id
                             LEFT JOIN (
                                 SELECT id, numero_instrumento, libro, revoca_documento_id 
                                 FROM documentos 
                                 WHERE tipo = 'revocacion' AND revoca_documento_id IS NOT NULL
                             ) r ON d.id = r.revoca_documento_id 
                             WHERE d.empresa_id = :empresa_id
                             ORDER BY d.created_at DESC LIMIT 5");
        $stmt->execute(['empresa_id' => $empresa_id]);
    } else {
        $stmt = $pdo->query("SELECT d.*, e.nombre AS empresa_nombre, r.id AS revocacion_id, r.numero_instrumento AS revocacion_instrumento, r.libro AS revocacion_libro 
                             FROM documentos d 
                             LEFT JOIN empresas e ON d.empresa_id = e.id
                             LEFT JOIN (
                                 SELECT id, numero_instrumento, libro, revoca_documento_id 
                                 FROM documentos 
                                 WHERE tipo = 'revocacion' AND revoca_documento_id IS NOT NULL
                             ) r ON d.id = r.revoca_documento_id 
                             ORDER BY d.created_at DESC LIMIT 5");
    }
    $ultimos_documentos = $stmt->fetchAll();

    // Obtener personas acreditadas para los últimos documentos de manera eficiente
    $acreditados_map = [];
    // Obtener socios para los últimos documentos de manera eficiente
    $socios_map = [];
    if (!empty($ultimos_documentos)) {
        $doc_ids = array_column($ultimos_documentos, 'id');
        $placeholders = implode(',', array_fill(0, count($doc_ids), '?'));
        
        $stmt_acred = $pdo->prepare("SELECT dp.documento_id, p.nombre 
                                     FROM documento_personas dp 
                                     JOIN personas p ON dp.persona_id = p.id 
                                     WHERE dp.documento_id IN ($placeholders)");
        $stmt_acred->execute($doc_ids);
        while ($row_acred = $stmt_acred->fetch()) {
            $acreditados_map[$row_acred['documento_id']][] = $row_acred['nombre'];
        }

        $stmt_soc = $pdo->prepare("SELECT documento_id, nombre, numero_acciones, valor_nominal, tipo_capital 
                                   FROM documento_socios 
                                   WHERE documento_id IN ($placeholders)");
        $stmt_soc->execute($doc_ids);
        while ($row_soc = $stmt_soc->fetch()) {
            $socios_map[$row_soc['documento_id']][] = $row_soc;
        }
    }

    // Obtener documentos próximos a vencer (vigencia en los siguientes 30 días y que no estén revocados)
    $vencimientos_proximos = [];
    $hoy = date('Y-m-d');
    $limite_vencimiento = date('Y-m-d', strtotime('+30 days'));
    if ($empresa_id !== '') {
        $stmt_vence = $pdo->prepare("
            SELECT d.* 
            FROM documentos d
            LEFT JOIN documentos r ON d.id = r.revoca_documento_id AND r.tipo = 'revocacion'
            WHERE d.vigencia IS NOT NULL 
              AND d.vigencia >= :hoy 
              AND d.vigencia <= :limite 
              AND d.empresa_id = :empresa_id
              AND r.id IS NULL
            ORDER BY d.vigencia ASC
            LIMIT 5
        ");
        $stmt_vence->execute([
            'hoy' => $hoy,
            'limite' => $limite_vencimiento,
            'empresa_id' => $empresa_id
        ]);
    } else {
        $stmt_vence = $pdo->prepare("
            SELECT d.* 
            FROM documentos d
            LEFT JOIN documentos r ON d.id = r.revoca_documento_id AND r.tipo = 'revocacion'
            WHERE d.vigencia IS NOT NULL 
              AND d.vigencia >= :hoy 
              AND d.vigencia <= :limite 
              AND r.id IS NULL
            ORDER BY d.vigencia ASC
            LIMIT 5
        ");
        $stmt_vence->execute([
            'hoy' => $hoy,
            'limite' => $limite_vencimiento
        ]);
    }
    $vencimientos_proximos = $stmt_vence->fetchAll();
} catch (PDOException $e) {
    // En caso de que falle
    error_log("Error al cargar estadísticas del dashboard: " . $e->getMessage());
}
?>

<!-- Área de Contenido Principal -->
<div class="content-area">
    
    <!-- Barra Superior de Navegación Rápida -->
    <nav class="navbar navbar-top navbar-expand-lg navbar-light bg-transparent">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1 fw-bold fs-4">Dashboard Principal</span>
            <div class="ms-auto d-flex align-items-center gap-3 flex-wrap">
                <!-- Selector de Empresa -->
                <form method="GET" action="dashboard.php" id="form-empresa" class="d-flex align-items-center gap-2 m-0">
                    <label for="empresa_id" class="text-muted fw-semibold mb-0" style="font-size: 0.85rem; white-space: nowrap;"><i class="fa-solid fa-building me-1"></i> Empresa:</label>
                    <select class="form-select form-select-sm rounded-3" name="empresa_id" id="empresa_id" onchange="document.getElementById('form-empresa').submit()" style="min-width: 200px; padding: 0.35rem 2rem 0.35rem 0.75rem; border-color: #cbd5e1;">
                        <option value="">Todas las Empresas</option>
                        <?php foreach ($empresas as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo ($empresa_id == $emp['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

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
                    <p class="text-muted mb-0 fs-5">Bienvenido al Sistema de Gestión Documental y Poderes Jurídicos. Desde aquí puedes gestionar las actas, poderes y alcances legales.</p>
                </div>
            </div>
        </div>

        <!-- Fila de Estadísticas Rápidas (Poderes y Actas) -->
        <div class="row g-4 mb-4">
            <!-- Total de Documentos -->
            <div class="col-md-6 col-xl-3">
                <div class="stat-card p-3 h-100 d-flex flex-column justify-content-center" style="border-left: 5px solid #1d4ed8 !important;">
                    <span class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px; line-height: 1.2;">Documentos</span>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size: 1.8rem; line-height: 1;"><?php echo $total_docs; ?></h3>
                </div>
            </div>

            <!-- Actas Constitutivas -->
            <div class="col-md-6 col-xl-3">
                <div class="stat-card p-3 h-100 d-flex flex-column justify-content-center" style="border-left: 5px solid #0e7490 !important;">
                    <span class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px; line-height: 1.2;">Actas / Asambleas</span>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size: 1.8rem; line-height: 1;"><?php echo $total_actas; ?></h3>
                </div>
            </div>

            <!-- Poderes Amplios -->
            <div class="col-md-6 col-xl-3">
                <div class="stat-card p-3 h-100 d-flex flex-column justify-content-center" style="border-left: 5px solid #15803d !important;">
                    <span class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px; line-height: 1.2;">Poderes Amplios</span>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size: 1.8rem; line-height: 1;"><?php echo $total_poderes_amplios; ?></h3>
                </div>
            </div>

            <!-- Poderes Especiales -->
            <div class="col-md-6 col-xl-3">
                <div class="stat-card p-3 h-100 d-flex flex-column justify-content-center" style="border-left: 5px solid #b45309 !important;">
                    <span class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px; line-height: 1.2;">Poderes Especiales/Adm</span>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size: 1.8rem; line-height: 1;"><?php echo $total_poderes_especiales; ?></h3>
                </div>
            </div>
        </div>

        <!-- Accesos Rápidos por Categoría -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="p-4 rounded-4 bg-white" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-tags text-primary me-2"></i>Accesos Rápidos por Tipo de Documento</h5>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="documentos.php?subtipo=constitutiva" class="btn btn-outline-primary rounded-3 py-2 px-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-file-signature"></i>
                            <span>Acta Constitutiva</span>
                        </a>
                        <a href="documentos.php?subtipo=asamblea_ordinaria" class="btn btn-outline-primary rounded-3 py-2 px-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-users-rectangle"></i>
                            <span>Asamblea Ordinaria</span>
                        </a>
                        <a href="documentos.php?subtipo=asamblea_extraordinaria" class="btn btn-outline-primary rounded-3 py-2 px-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-nodes"></i>
                            <span>Asamblea Extraordinaria</span>
                        </a>
                        <a href="documentos.php?subtipo=poder_amplio" class="btn btn-outline-success rounded-3 py-2 px-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>Poder Amplio</span>
                        </a>
                        <a href="documentos.php?subtipo=poder_especifico" class="btn btn-outline-success rounded-3 py-2 px-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-key"></i>
                            <span>Poder Específico</span>
                        </a>
                        <a href="documentos.php?subtipo=poder_actas_administrativas" class="btn btn-outline-success rounded-3 py-2 px-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-file-invoice"></i>
                            <span>Poder Actas Adm.</span>
                        </a>
                        <a href="documentos.php?tipo=revocacion" class="btn btn-outline-danger rounded-3 py-2 px-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-file-circle-xmark"></i>
                            <span>Revocación de Poderes</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fila de Accesos Rápidos y Última Actividad -->
        <div class="row g-4">
            <!-- Módulo de Documentos - Contexto del Negocio -->
            <div class="col-xl-8">
                <div class="p-4 rounded-4 h-100 d-flex flex-column" style="background: #ffffff; border: 1px solid #e2e8f0; min-height: 350px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h4 class="fw-bold text-dark mb-0">Últimos Documentos Registrados</h4>
                        <?php if ($_SESSION['user_rol'] !== 'usuario'): ?>
                        <a href="documento_nuevo.php" class="btn btn-primary-custom btn-sm py-2 px-3 rounded-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Capturar Documento
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="table-responsive flex-grow-1">
                        <table class="table table-hover align-middle mb-0" style="--bs-table-bg: transparent; --bs-table-border-color: #e2e8f0;">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Instrumento / Libro</th>
                                    <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Tipo / Subtipo</th>
                                    <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Concepto</th>
                                    <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">PDF</th>
                                    <?php if ($_SESSION['user_rol'] !== 'usuario'): ?>
                                    <th scope="col" class="text-muted fw-semibold py-3 text-end" style="font-size: 0.85rem;">Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ultimos_documentos)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fa-regular fa-folder-open d-block fs-1 mb-3 opacity-50"></i>
                                            Aún no hay documentos registrados en el sistema.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ultimos_documentos as $doc): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark">No. <?php echo htmlspecialchars($doc['numero_instrumento']); ?></div>
                                                <small class="text-muted d-block">Libro: <?php echo htmlspecialchars($doc['libro']); ?></small>
                                                <?php if ($doc['tipo'] === 'acta' && !empty($doc['fme'])): ?>
                                                    <small class="text-dark d-block" style="font-size: 0.75rem;"><strong class="text-secondary">FME:</strong> <?php echo htmlspecialchars($doc['fme']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $tipoLabel = '';
                                                $badgeClass = '';
                                                if ($doc['tipo'] === 'acta') {
                                                    $sub = $doc['subtipo'];
                                                    if ($sub === 'constitutiva') $tipoLabel = 'Acta Constitutiva';
                                                    elseif ($sub === 'asamblea_ordinaria') $tipoLabel = 'Asamblea Ordinaria';
                                                    elseif ($sub === 'asamblea_extraordinaria') $tipoLabel = 'Asamblea Extraordinaria';
                                                    else $tipoLabel = 'Acta';
                                                    $badgeClass = 'bg-primary';
                                                } elseif ($doc['tipo'] === 'poder') {
                                                    $sub = $doc['subtipo'];
                                                    if ($sub === 'poder_amplio') $tipoLabel = 'Poder Amplio';
                                                    elseif ($sub === 'poder_especifico') $tipoLabel = 'Poder Específico';
                                                    elseif ($sub === 'poder_actas_administrativas') $tipoLabel = 'Poder Actas Adm.';
                                                    else $tipoLabel = 'Poder';
                                                    $badgeClass = 'bg-success';
                                                } else {
                                                    $tipoLabel = 'Revocación';
                                                    $badgeClass = 'bg-danger';
                                                }
                                                ?>
                                                <div class="d-flex flex-column gap-1 align-items-start">
                                                    <div class="d-flex gap-1 flex-wrap">
                                                        <span class="badge <?php echo $badgeClass; ?> rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                                                            <?php echo htmlspecialchars($tipoLabel); ?>
                                                        </span>
                                                        <?php if (!empty($doc['revocacion_id'])): ?>
                                                            <span class="badge bg-dark rounded-pill px-2 py-1" style="font-size: 0.75rem; background-color: #1e293b !important;" title="Revocado por Instrumento No. <?php echo htmlspecialchars($doc['revocacion_instrumento']); ?>">
                                                                <i class="fa-solid fa-ban text-danger me-1"></i> Revocado
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1" style="font-size: 0.7rem;" title="Empresa / Entidad">
                                                        <i class="fa-solid fa-building me-1" style="font-size: 0.65rem;"></i><?php echo htmlspecialchars($doc['empresa_nombre'] ?? 'N/A'); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-dark fw-semibold" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($doc['concepto']); ?>">
                                                    <?php echo htmlspecialchars($doc['concepto']); ?>
                                                </div>
                                                <?php 
                                                $personas_list = isset($acreditados_map[$doc['id']]) ? implode(', ', $acreditados_map[$doc['id']]) : '';
                                                if (!empty($personas_list)): 
                                                ?>
                                                    <div class="mt-1" style="font-size: 0.75rem;">
                                                        <strong class="text-dark"><i class="fa-solid fa-users me-1 text-primary"></i>Acreditados:</strong>
                                                        <span class="text-muted" title="<?php echo htmlspecialchars($personas_list); ?>">
                                                            <?php 
                                                            $acred = htmlspecialchars($personas_list);
                                                            if (strlen($acred) > 40) {
                                                                echo substr($acred, 0, 37) . '...';
                                                            } else {
                                                                echo $acred;
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($doc['tipo'] === 'acta' && isset($socios_map[$doc['id']])): 
                                                    $socios_list = array_map(function($s) {
                                                        return $s['nombre'] . ' (' . $s['numero_acciones'] . ' acc., $' . number_format($s['valor_nominal'], 2) . ' ' . $s['tipo_capital'] . ')';
                                                    }, $socios_map[$doc['id']]);
                                                    $socios_str = implode(', ', $socios_list);
                                                ?>
                                                    <div class="mt-1" style="font-size: 0.75rem;">
                                                        <strong class="text-dark"><i class="fa-solid fa-users me-1 text-primary"></i>Socios:</strong>
                                                        <span class="text-muted" title="<?php echo htmlspecialchars($socios_str); ?>">
                                                            <?php 
                                                            $socs = htmlspecialchars($socios_str);
                                                            if (strlen($socs) > 40) {
                                                                echo substr($socs, 0, 37) . '...';
                                                            } else {
                                                                echo $socs;
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($doc['archivo_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($doc['archivo_path']); ?>" target="_blank" class="btn btn-outline-danger btn-sm rounded-3 py-1 px-2">
                                                        <i class="fa-solid fa-file-pdf"></i> PDF
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 0.8rem;">Ninguno</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($_SESSION['user_rol'] !== 'usuario'): ?>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <a href="documento_editar.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-warning btn-sm rounded-3 py-1 px-2" title="Editar">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger btn-sm rounded-3 py-1 px-2" title="Eliminar" onclick="confirmarEliminar(<?php echo $doc['id']; ?>)">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Próximos Vencimientos -->
            <div class="col-xl-4">
                <div class="p-4 rounded-4 h-100 d-flex flex-column" style="background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-calendar-days text-danger me-2"></i>Vencimientos Próximos</h5>
                    <div class="d-flex flex-column gap-3 flex-grow-1 <?php echo empty($vencimientos_proximos) ? 'justify-content-center' : ''; ?>">
                        <?php if (empty($vencimientos_proximos)): ?>
                            <div class="text-center py-4">
                                <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex p-3 mb-2">
                                    <i class="fa-solid fa-circle-check fs-4"></i>
                                </div>
                                <h6 class="fw-semibold text-dark mb-1">Todo al día</h6>
                                <p class="text-muted mb-0" style="font-size: 0.8rem;">No hay documentos próximos a vencer en los siguientes 30 días.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($vencimientos_proximos as $doc): ?>
                                <?php
                                $dias_restantes = (int)round((strtotime($doc['vigencia']) - strtotime($hoy)) / 86400);
                                $badge_color = 'bg-primary';
                                $icon_color = 'text-primary';
                                $bg_icon = 'bg-primary bg-opacity-10';
                                
                                if ($dias_restantes <= 7) {
                                    $badge_color = 'bg-danger';
                                    $icon_color = 'text-danger';
                                    $bg_icon = 'bg-danger bg-opacity-10';
                                } elseif ($dias_restantes <= 15) {
                                    $badge_color = 'bg-warning text-dark';
                                    $icon_color = 'text-warning';
                                    $bg_icon = 'bg-warning bg-opacity-10';
                                }
                                
                                $tipo_label = '';
                                $tipo_icon = 'fa-file-lines';
                                if ($doc['tipo'] === 'acta') {
                                    $tipo_label = 'Acta';
                                    $tipo_icon = 'fa-landmark';
                                } elseif ($doc['tipo'] === 'poder') {
                                    $tipo_label = 'Poder';
                                    $tipo_icon = 'fa-scale-balanced';
                                }
                                ?>
                                <div class="p-3 rounded-3 d-flex align-items-center justify-content-between border" style="background-color: #f8fafc; border-color: #e2e8f0 !important;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="<?php echo $bg_icon; ?> <?php echo $icon_color; ?> p-2 rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fa-solid <?php echo $tipo_icon; ?> fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0" style="font-size: 0.85rem;">Inst. No. <?php echo htmlspecialchars($doc['numero_instrumento']); ?></h6>
                                            <small class="text-muted d-block" style="font-size: 0.75rem;"><?php echo htmlspecialchars($tipo_label); ?> - Libro <?php echo htmlspecialchars($doc['libro']); ?></small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?php echo $badge_color; ?> rounded-pill mb-1 d-block" style="font-size: 0.7rem;">
                                            <?php 
                                            if ($dias_restantes === 0) echo 'Vence hoy';
                                            elseif ($dias_restantes === 1) echo 'Vence mañana';
                                            else echo 'Vence en ' . $dias_restantes . ' días';
                                            ?>
                                        </span>
                                        <a href="documento_editar.php?id=<?php echo $doc['id']; ?>" class="text-decoration-none fw-semibold" style="font-size: 0.75rem; color: var(--primary-color);">
                                            Ver detalles <i class="fa-solid fa-chevron-right ms-1" style="font-size: 0.65rem;"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function confirmarEliminar(id) {
    if (confirm("¿Está seguro de que desea eliminar este documento? Esta acción no se puede deshacer.")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'documento_eliminar.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id';
        input.value = id;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
// Incluir el pie de página (cierra contenedores e importa JS)
require_once __DIR__ . '/includes/footer.php';
?>
