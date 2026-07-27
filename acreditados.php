<?php
/**
 * Catálogo de Acreditados / Titulares
 * Ruta: /acreditados.php
 */

// Incluir conexión a base de datos
require_once __DIR__ . '/config/db.php';

// Incluir cabecera
require_once __DIR__ . '/includes/header.php';

// Incluir barra lateral
require_once __DIR__ . '/includes/sidebar.php';

// Obtener parámetros de búsqueda
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Construir consulta SQL
$sql = "SELECT p.id, p.nombre, COUNT(dp.documento_id) as total_documentos 
        FROM personas p 
        LEFT JOIN documento_personas dp ON p.id = dp.persona_id";
$params = [];

if (!empty($q)) {
    $sql .= " WHERE p.nombre LIKE :q";
    $params['q'] = '%' . $q . '%';
}

$sql .= " GROUP BY p.id, p.nombre ORDER BY total_documentos DESC, p.nombre ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $personas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error al consultar personas acreditadas: " . $e->getMessage());
    $personas = [];
}
?>

<!-- Área de Contenido Principal -->
<div class="content-area">
    
    <!-- Barra Superior de Navegación Rápida -->
    <nav class="navbar navbar-top navbar-expand-lg navbar-light bg-transparent">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1 fw-bold fs-4">Acreditados / Titulares</span>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-semibold">
                    <i class="fa-solid fa-users me-1"></i> <?php echo count($personas); ?> Registrados
                </span>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container-fluid p-0">
        
        <!-- Tarjeta de Controladores: Búsqueda -->
        <div class="p-4 rounded-4 bg-white mb-4" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
            <form method="GET" action="acreditados.php" class="row g-3">
                <div class="col-md-9 col-lg-10">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted" style="border-radius: 12px 0 0 12px; border-color: #cbd5e1;">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" name="q" placeholder="Buscar acreditado por nombre..." value="<?php echo htmlspecialchars($q); ?>" style="border-radius: 0 12px 12px 0;">
                    </div>
                </div>
                <div class="col-md-3 col-lg-2 d-grid">
                    <button type="submit" class="btn btn-primary-custom py-2">Buscar</button>
                </div>
            </form>
        </div>

        <!-- Tarjeta de Listado -->
        <div class="p-4 rounded-4 bg-white" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
            
            <div class="table-responsive">
                <table class="table align-middle mb-0" style="border-color: #f1f5f9;">
                    <thead>
                        <tr class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            <th class="pb-3" style="width: 80px;">ID</th>
                            <th class="pb-3">Nombre Completo</th>
                            <th class="pb-3 text-center" style="width: 250px;">Documentos Asociados</th>
                            <th class="pb-3 text-end" style="width: 200px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($personas)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fa-regular fa-folder-open display-6 mb-3 d-block text-slate-300"></i>
                                        No se encontraron personas acreditadas.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($personas as $p): ?>
                                <tr>
                                    <td>
                                        <span class="text-muted fw-medium" style="font-size: 0.85rem;">#<?php echo $p['id']; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 text-primary fw-bold" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                                <?php 
                                                $initials = '';
                                                $words = explode(' ', $p['nombre']);
                                                $initials .= strtoupper(substr($words[0], 0, 1));
                                                if (isset($words[1])) {
                                                    $initials .= strtoupper(substr($words[1], 0, 1));
                                                }
                                                echo htmlspecialchars($initials);
                                                ?>
                                            </div>
                                            <div>
                                                <div class="text-dark fw-bold" style="font-size: 0.95rem;"><?php echo htmlspecialchars($p['nombre']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($p['total_documentos'] > 0): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold" style="font-size: 0.8rem;">
                                                <i class="fa-solid fa-file-shield me-1"></i> <?php echo $p['total_documentos']; ?> documento(s)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-2 fw-medium" style="font-size: 0.8rem;">
                                                Sin documentos
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="documentos.php?q=<?php echo urlencode($p['nombre']); ?>" class="btn btn-outline-primary btn-sm rounded-3 py-1 px-3 fw-semibold gap-1 d-inline-flex align-items-center">
                                            <i class="fa-solid fa-folder-open"></i> Ver Documentos
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div>
</div>

<?php
// Incluir el pie de página
require_once __DIR__ . '/includes/footer.php';
?>
