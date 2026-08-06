<?php
/**
 * Listado de Documentos con Filtros y Búsqueda
 * Ruta: /documentos.php
 */

// Incluir conexión a base de datos
require_once __DIR__ . '/config/db.php';

// Incluir cabecera
require_once __DIR__ . '/includes/header.php';

// Incluir barra lateral
require_once __DIR__ . '/includes/sidebar.php';

// Obtener parámetros de búsqueda y filtros
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$subtipo = isset($_GET['subtipo']) ? trim($_GET['subtipo']) : '';
$vencimiento = isset($_GET['vencimiento']) ? trim($_GET['vencimiento']) : '';
$empresa_id = isset($_GET['empresa_id']) ? trim($_GET['empresa_id']) : '';

// Obtener lista de empresas para el filtro
$empresas = [];
try {
    $stmt_emp = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC");
    $empresas = $stmt_emp->fetchAll();
} catch (PDOException $e) {
    error_log("Error al obtener lista de empresas para filtro: " . $e->getMessage());
}

// Construir consulta SQL con LEFT JOIN para identificar revocaciones y traer el nombre de la empresa
$sql = "SELECT d.*, e.nombre AS empresa_nombre, r.id AS revocacion_id, r.numero_instrumento AS revocacion_instrumento, r.libro AS revocacion_libro 
        FROM documentos d 
        LEFT JOIN empresas e ON d.empresa_id = e.id
        LEFT JOIN (
            SELECT id, numero_instrumento, libro, revoca_documento_id 
            FROM documentos 
            WHERE tipo = 'revocacion' AND revoca_documento_id IS NOT NULL
        ) r ON d.id = r.revoca_documento_id 
        WHERE 1=1";
$params = [];

if ($empresa_id !== '') {
    $sql .= " AND d.empresa_id = :empresa_id";
    $params['empresa_id'] = (int)$empresa_id;
}

if (!empty($tipo)) {
    $sql .= " AND d.tipo = :tipo";
    $params['tipo'] = $tipo;
}

if (!empty($subtipo)) {
    $sql .= " AND d.subtipo = :subtipo";
    $params['subtipo'] = $subtipo;
}

if (!empty($q)) {
    $sql .= " AND (d.numero_instrumento LIKE :q OR d.libro LIKE :q OR d.notaria LIKE :q OR d.ciudad_notaria LIKE :q OR d.estado_notaria LIKE :q OR d.notario LIKE :q OR d.concepto LIKE :q OR d.id IN (
        SELECT dp.documento_id 
        FROM documento_personas dp 
        JOIN personas p ON dp.persona_id = p.id 
        WHERE p.nombre LIKE :q
    ) OR d.id IN (
        SELECT ds.documento_id
        FROM documento_socios ds
        WHERE ds.nombre LIKE :q
    ))";
    $params['q'] = '%' . $q . '%';
}

$hoy = date('Y-m-d');
if ($vencimiento === 'vigente') {
    $sql .= " AND (d.vigencia IS NULL OR d.vigencia >= :hoy)";
    $params['hoy'] = $hoy;
} elseif ($vencimiento === 'expirado') {
    $sql .= " AND (d.vigencia IS NOT NULL AND d.vigencia < :hoy)";
    $params['hoy'] = $hoy;
} elseif ($vencimiento === 'permanente') {
    $sql .= " AND d.vigencia IS NULL";
}

$sql .= " ORDER BY d.fecha_expedicion DESC, d.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $documentos = $stmt->fetchAll();
    
    // Obtener personas acreditadas para los documentos resultantes de manera eficiente
    $acreditados_map = [];
    // Obtener socios para los documentos resultantes de manera eficiente
    $socios_map = [];
    if (!empty($documentos)) {
        $doc_ids = array_column($documentos, 'id');
        $placeholders = implode(',', array_fill(0, count($doc_ids), '?'));
        
        $stmt_acred = $pdo->prepare("SELECT dp.documento_id, p.nombre 
                                     FROM documento_personas dp 
                                     JOIN personas p ON dp.persona_id = p.id 
                                     WHERE dp.documento_id IN ($placeholders)");
        $stmt_acred->execute($doc_ids);
        while ($row_acred = $stmt_acred->fetch()) {
            $acreditados_map[$row_acred['documento_id']][] = $row_acred['nombre'];
        }

        $stmt_soc = $pdo->prepare("SELECT documento_id, nombre, nacionalidad, domicilio_social, numero_acciones, valor_nominal, tipo_capital 
                                   FROM documento_socios 
                                   WHERE documento_id IN ($placeholders)");
        $stmt_soc->execute($doc_ids);
        while ($row_soc = $stmt_soc->fetch()) {
            $socios_map[$row_soc['documento_id']][] = $row_soc;
        }
    }
} catch (PDOException $e) {
    error_log("Error al consultar documentos: " . $e->getMessage());
    $documentos = [];
}

// Helper para construir enlaces de filtro preservando los parámetros existentes
function getFilterUrl($new_params) {
    $current_params = $_GET;
    foreach ($new_params as $key => $val) {
        if ($val === '') {
            unset($current_params[$key]);
        } else {
            $current_params[$key] = $val;
        }
    }
    return 'documentos.php' . (!empty($current_params) ? '?' . http_build_query($current_params) : '');
}

// Helper para determinar el estado de vigencia
function getVigenciaBadge($fecha_vigencia, $revocacion_id = null, $revocacion_instrumento = null) {
    if ($revocacion_id !== null) {
        return '<a href="documento_editar.php?id=' . $revocacion_id . '" class="badge bg-dark rounded-pill px-2 py-1 text-decoration-none" style="background-color: #1e293b !important;" title="Revocado por Instrumento ' . htmlspecialchars($revocacion_instrumento) . ' (Click para ver)"><i class="fa-solid fa-ban me-1 text-danger"></i> Revocado por No. ' . htmlspecialchars($revocacion_instrumento) . '</a>';
    }
    
    if (empty($fecha_vigencia)) {
        return '<span class="badge bg-secondary rounded-pill px-2 py-1"><i class="fa-solid fa-infinity me-1"></i> Permanente</span>';
    }
    
    $hoy = date('Y-m-d');
    if ($fecha_vigencia < $hoy) {
        return '<span class="badge bg-danger rounded-pill px-2 py-1"><i class="fa-solid fa-calendar-xmark me-1"></i> Expirado (' . date('d/m/Y', strtotime($fecha_vigencia)) . ')</span>';
    } else {
        return '<span class="badge bg-success rounded-pill px-2 py-1"><i class="fa-solid fa-calendar-check me-1"></i> Vigente (' . date('d/m/Y', strtotime($fecha_vigencia)) . ')</span>';
    }
}
?>

<!-- Área de Contenido Principal -->
<div class="content-area">
    
    <!-- Barra Superior de Navegación Rápida -->
    <nav class="navbar navbar-top navbar-expand-lg navbar-light bg-transparent">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1 fw-bold fs-4">Gestión de Documentos</span>
            <div class="ms-auto d-flex align-items-center gap-3">
                <?php
                // Construir los enlaces de exportación manteniendo todos los filtros actuales de la búsqueda en documentos.php
                $query_excel = $_GET;
                $query_excel['formato'] = 'excel';
                $link_excel = 'exportar.php?' . http_build_query($query_excel);

                $query_pdf = $_GET;
                $query_pdf['formato'] = 'pdf';
                $link_pdf = 'exportar.php?' . http_build_query($query_pdf);
                
                // Texto personalizado del botón según la sección
                $btn_text = 'Exportar Reporte';
                if ($tipo === 'poder') $btn_text = 'Exportar Poderes';
                elseif ($tipo === 'acta') $btn_text = 'Exportar Actas';
                elseif ($tipo === 'revocacion') $btn_text = 'Exportar Revocaciones';
                ?>
                <div class="dropdown">
                    <button class="btn btn-outline-primary py-2 px-3 rounded-3 d-flex align-items-center gap-2 dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-file-export"></i> <?php echo htmlspecialchars($btn_text); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3" aria-labelledby="exportDropdown" style="font-size: 0.85rem; border: 1px solid #e2e8f0 !important;">
                        <li><a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?php echo htmlspecialchars($link_excel); ?>"><i class="fa-solid fa-file-excel text-success fs-6"></i> Exportar a Excel</a></li>
                        <li><a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?php echo htmlspecialchars($link_pdf); ?>" target="_blank"><i class="fa-solid fa-file-pdf text-danger fs-6"></i> Exportar a PDF</a></li>
                    </ul>
                </div>
                <?php if ($_SESSION['user_rol'] !== 'usuario'): ?>
                <a href="documento_nuevo.php" class="btn btn-primary-custom py-2 px-3 rounded-3 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-plus"></i> Nuevo Documento
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container-fluid p-0">
        
        <!-- Tarjeta de Controladores: Filtros y Búsqueda -->
        <div class="p-4 rounded-4 bg-white mb-4" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
            
            <!-- Barra de Búsqueda -->
            <form method="GET" action="documentos.php" class="row g-3 mb-4">
                <?php if (!empty($tipo)): ?>
                    <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo); ?>">
                <?php endif; ?>
                <?php if (!empty($subtipo)): ?>
                    <input type="hidden" name="subtipo" value="<?php echo htmlspecialchars($subtipo); ?>">
                <?php endif; ?>
                <?php if (!empty($vencimiento)): ?>
                    <input type="hidden" name="vencimiento" value="<?php echo htmlspecialchars($vencimiento); ?>">
                <?php endif; ?>
                <?php if ($empresa_id !== ''): ?>
                    <input type="hidden" name="empresa_id" value="<?php echo htmlspecialchars($empresa_id); ?>">
                <?php endif; ?>
                <div class="col-md-9 col-lg-10">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted" style="border-radius: 12px 0 0 12px; border-color: #cbd5e1;">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" name="q" placeholder="Buscar por instrumento, libro, notaría, notario, ciudad, estado, concepto..." value="<?php echo htmlspecialchars($q); ?>" style="border-radius: 0 12px 12px 0;">
                    </div>
                </div>
                <div class="col-md-3 col-lg-2 d-grid">
                    <button type="submit" class="btn btn-primary-custom py-2">Buscar</button>
                </div>
            </form>

            <!-- Botón para mostrar/ocultar filtros en móviles y tablets -->
            <div class="d-lg-none mb-3">
                <button class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2 py-2.5 rounded-3 position-relative" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFiltros" aria-expanded="<?php echo (!empty($tipo) || !empty($subtipo) || !empty($vencimiento)) ? 'true' : 'false'; ?>" aria-controls="collapseFiltros">
                    <i class="fa-solid fa-filter"></i>
                    <span>Filtrar por Categoría / Vigencia</span>
                    <?php if (!empty($tipo) || !empty($subtipo) || !empty($vencimiento)): ?>
                        <span class="badge bg-danger rounded-pill ms-1">
                            <?php 
                            $count = 0;
                            if (!empty($tipo)) $count++;
                            if (!empty($subtipo)) $count++;
                            if (!empty($vencimiento)) $count++;
                            echo $count;
                            ?>
                        </span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- Filtros Rápidos (Pills) -->
            <div class="collapse d-lg-block <?php echo (!empty($tipo) || !empty($subtipo) || !empty($vencimiento)) ? 'show' : ''; ?>" id="collapseFiltros">
                <div class="d-flex flex-column gap-3 pt-2 pt-lg-0">
                <div>
                    <span class="text-muted fw-bold d-block mb-2" style="font-size: 0.8rem; letter-spacing: 0.5px; text-uppercase: true;">Filtrar por Categoría:</span>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?php echo getFilterUrl(['tipo' => '', 'subtipo' => '']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo (empty($tipo) && empty($subtipo)) ? 'btn-primary' : 'btn-light text-dark border'; ?>">
                            Todos
                        </a>
                        
                        <a href="<?php echo getFilterUrl(['tipo' => 'acta', 'subtipo' => 'constitutiva']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($subtipo === 'constitutiva') ? 'btn-primary' : 'btn-light text-dark border'; ?>">
                            Acta Constitutiva
                        </a>
                        
                        <a href="<?php echo getFilterUrl(['tipo' => 'acta', 'subtipo' => 'asamblea_ordinaria']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($subtipo === 'asamblea_ordinaria') ? 'btn-primary' : 'btn-light text-dark border'; ?>">
                            Asamblea Ordinaria
                        </a>
                        
                        <a href="<?php echo getFilterUrl(['tipo' => 'acta', 'subtipo' => 'asamblea_extraordinaria']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($subtipo === 'asamblea_extraordinaria') ? 'btn-primary' : 'btn-light text-dark border'; ?>">
                            Asamblea Extraordinaria
                        </a>
                        
                        <a href="<?php echo getFilterUrl(['tipo' => 'poder', 'subtipo' => 'poder_amplio']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($subtipo === 'poder_amplio') ? 'btn-success' : 'btn-light text-dark border'; ?>">
                            Poder Amplio
                        </a>
                        
                        <a href="<?php echo getFilterUrl(['tipo' => 'poder', 'subtipo' => 'poder_especifico']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($subtipo === 'poder_especifico') ? 'btn-success' : 'btn-light text-dark border'; ?>">
                            Poder Específico
                        </a>
                        
                        <a href="<?php echo getFilterUrl(['tipo' => 'poder', 'subtipo' => 'poder_actas_administrativas']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($subtipo === 'poder_actas_administrativas') ? 'btn-success' : 'btn-light text-dark border'; ?>">
                            Poder Actas Adm.
                        </a>
                        
                        <a href="<?php echo getFilterUrl(['tipo' => 'revocacion', 'subtipo' => '']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($tipo === 'revocacion') ? 'btn-danger' : 'btn-light text-dark border'; ?>">
                            Revocación de Poderes
                        </a>
                    </div>
                </div>

                <div>
                    <span class="text-muted fw-bold d-block mb-2" style="font-size: 0.8rem; letter-spacing: 0.5px; text-uppercase: true;">Filtrar por Vigencia:</span>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?php echo getFilterUrl(['vencimiento' => '']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo (empty($vencimiento)) ? 'btn-dark' : 'btn-light text-dark border'; ?>">
                            Cualquier estado
                        </a>
                        <a href="<?php echo getFilterUrl(['vencimiento' => 'vigente']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($vencimiento === 'vigente') ? 'btn-success' : 'btn-light text-dark border'; ?>">
                            <i class="fa-solid fa-calendar-check me-1"></i> Vigentes
                        </a>
                        <a href="<?php echo getFilterUrl(['vencimiento' => 'expirado']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($vencimiento === 'expirado') ? 'btn-danger' : 'btn-light text-dark border'; ?>">
                            <i class="fa-solid fa-calendar-xmark me-1"></i> Expirados
                        </a>
                        <a href="<?php echo getFilterUrl(['vencimiento' => 'permanente']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($vencimiento === 'permanente') ? 'btn-secondary' : 'btn-light text-dark border'; ?>">
                            <i class="fa-solid fa-infinity me-1"></i> Permanentes
                        </a>
                    </div>
                </div>

                <div>
                    <span class="text-muted fw-bold d-block mb-2" style="font-size: 0.8rem; letter-spacing: 0.5px; text-uppercase: true;">Filtrar por Nombre / Razón Social:</span>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?php echo getFilterUrl(['empresa_id' => '']); ?>" 
                           class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($empresa_id === '') ? 'btn-primary' : 'btn-light text-dark border'; ?>">
                            Todas
                        </a>
                        <?php foreach ($empresas as $emp): ?>
                            <a href="<?php echo getFilterUrl(['empresa_id' => $emp['id']]); ?>" 
                               class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($empresa_id == $emp['id']) ? 'btn-primary' : 'btn-light text-dark border'; ?>">
                                <?php echo htmlspecialchars($emp['nombre']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
            
        </div>

        <!-- Tabla de Documentos -->
        <div class="p-4 rounded-4 bg-white" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="--bs-table-bg: transparent; --bs-table-border-color: #e2e8f0;">
                    <thead>
                        <tr>
                            <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Instrumento / Libro</th>
                            <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Tipo / Subtipo</th>
                            <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Fecha Expedición</th>
                            <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Notaría / Notario / Ciudad</th>
                            <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Concepto</th>
                            <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">Vigencia</th>
                            <th scope="col" class="text-muted fw-semibold py-3" style="font-size: 0.85rem;">PDF</th>
                            <?php if ($_SESSION['user_rol'] !== 'usuario'): ?>
                            <th scope="col" class="text-muted fw-semibold py-3 text-end" style="font-size: 0.85rem;">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documentos)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-regular fa-folder-open d-block fs-1 mb-3 opacity-50"></i>
                                    No se encontraron documentos registrados con los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documentos as $doc): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">No. <?php echo htmlspecialchars($doc['numero_instrumento']); ?></div>
                                        <small class="text-muted d-block">Libro: <?php echo htmlspecialchars($doc['libro']); ?></small>
                                         <?php if (!empty($doc['fme'])): ?>
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
                                            <span class="badge <?php echo $badgeClass; ?> rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                                                <?php echo htmlspecialchars($tipoLabel); ?>
                                            </span>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1" style="font-size: 0.7rem;" title="Nombre / Razón Social">
                                                <i class="fa-solid fa-building me-1" style="font-size: 0.65rem;"></i><?php echo htmlspecialchars($doc['empresa_nombre'] ?? 'N/A'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-dark"><?php echo date('d/m/Y', strtotime($doc['fecha_expedicion'])); ?></div>
                                        <?php if ($doc['tipo'] === 'acta' && !empty($doc['fecha_registro_rpc'])): ?>
                                            <small class="text-muted d-block" style="font-size: 0.75rem;"><strong class="text-secondary">RPC:</strong> <?php echo date('d/m/Y', strtotime($doc['fecha_registro_rpc'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-dark fw-semibold" style="font-size: 0.9rem;">Notaría No. <?php echo htmlspecialchars($doc['notaria']); ?></div>
                                        <div class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($doc['notario']); ?></div>
                                        <small class="text-muted d-block" style="font-size: 0.75rem;"><i class="fa-solid fa-location-dot me-1"></i><?php echo htmlspecialchars($doc['ciudad_notaria'] . (!empty($doc['estado_notaria']) ? ', ' . $doc['estado_notaria'] : '')); ?></small>
                                    </td>
                                    <td>
                                        <div class="text-dark fw-medium" style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($doc['concepto']); ?>">
                                            <?php echo htmlspecialchars($doc['concepto']); ?>
                                        </div>
                                        <?php 
                                        $personas_list = isset($acreditados_map[$doc['id']]) ? implode(', ', $acreditados_map[$doc['id']]) : '';
                                        if (!empty($personas_list)): 
                                        ?>
                                            <div class="mt-1" style="font-size: 0.8rem;">
                                                <strong class="text-dark"><i class="fa-solid fa-users me-1 text-primary"></i>Acreditados:</strong>
                                                <span class="text-muted" title="<?php echo htmlspecialchars($personas_list); ?>">
                                                    <?php 
                                                    $acred = htmlspecialchars($personas_list);
                                                    if (strlen($acred) > 50) {
                                                        echo substr($acred, 0, 47) . '...';
                                                    } else {
                                                        echo $acred;
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($doc['tipo'] === 'acta' && isset($socios_map[$doc['id']])): 
                                             $socios_list = array_map(function($s) {
                                                 $nacionalidad = !empty($s['nacionalidad']) ? $s['nacionalidad'] : 'Mexicana';
                                                 $domicilio = !empty($s['domicilio_social']) ? ', ' . $s['domicilio_social'] : '';
                                                 return $s['nombre'] . ' (' . $nacionalidad . $domicilio . ', ' . $s['numero_acciones'] . ' acc., $' . number_format($s['valor_nominal'], 2) . ' ' . $s['tipo_capital'] . ')';
                                             }, $socios_map[$doc['id']]);
                                             $socios_str = implode(', ', $socios_list);
                                         ?>
                                            <div class="mt-1" style="font-size: 0.8rem;">
                                                <strong class="text-dark"><i class="fa-solid fa-users me-1 text-primary"></i>Socios:</strong>
                                                <span class="text-muted" title="<?php echo htmlspecialchars($socios_str); ?>">
                                                    <?php 
                                                    $socs = htmlspecialchars($socios_str);
                                                    if (strlen($socs) > 50) {
                                                        echo substr($socs, 0, 47) . '...';
                                                    } else {
                                                        echo $socs;
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($doc['tipo'] === 'acta' && (!empty($doc['administrador_unico']) || !empty($doc['comisario']))): ?>
                                            <div class="mt-1" style="font-size: 0.8rem;">
                                                <?php if (!empty($doc['administrador_unico'])): ?>
                                                    <div class="d-inline-block me-3">
                                                        <strong class="text-dark"><i class="fa-solid fa-user-tie me-1 text-primary"></i>Adm. Único:</strong>
                                                        <span class="text-muted"><?php echo htmlspecialchars($doc['administrador_unico']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($doc['comisario'])): ?>
                                                    <div class="d-inline-block">
                                                        <strong class="text-dark"><i class="fa-solid fa-user-shield me-1 text-primary"></i>Comisario:</strong>
                                                        <span class="text-muted"><?php echo htmlspecialchars($doc['comisario']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo getVigenciaBadge($doc['vigencia'], $doc['revocacion_id'], $doc['revocacion_instrumento']); ?>
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
</div>

<script>
function confirmarEliminar(id) {
    if (confirm("¿Está seguro de que desea eliminar este documento? Esta acción no se puede deshacer y borrará el archivo PDF asociado.")) {
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
// Incluir el pie de página
require_once __DIR__ . '/includes/footer.php';
?>
