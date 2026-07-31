<?php
/**
 * Gestión de Empresas / Entidades
 * Ruta: /empresas.php
 */

// Incluir conexión a base de datos
require_once __DIR__ . '/config/db.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteger la página: verificar si la sesión está activa y no es un rol consulta
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_rol']) || $_SESSION['user_rol'] === 'usuario') {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

// Variables para el modo edición
$edit_mode = false;
$edit_id = 0;
$edit_nombre = '';
$edit_rfc = '';

// Cargar datos para edición si se solicita por GET
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $stmt_edit = $pdo->prepare("SELECT id, nombre, rfc FROM empresas WHERE id = :id");
        $stmt_edit->execute(['id' => $edit_id]);
        $emp_edit = $stmt_edit->fetch();
        if ($emp_edit) {
            $edit_mode = true;
            $edit_nombre = $emp_edit['nombre'];
            $edit_rfc = $emp_edit['rfc'];
        }
    } catch (PDOException $e) {
        error_log("Error al cargar empresa para editar: " . $e->getMessage());
    }
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // 1. Registrar / Crear Empresa
    if ($accion === 'registrar') {
        $nombre = trim($_POST['nombre']);
        $rfc = trim($_POST['rfc']);

        if (empty($nombre)) {
            $error = "El nombre de la empresa es obligatorio.";
        } else {
            try {
                // Verificar si ya existe una empresa con ese nombre
                $stmt = $pdo->prepare("SELECT id FROM empresas WHERE nombre = :nombre LIMIT 1");
                $stmt->execute(['nombre' => $nombre]);
                if ($stmt->fetch()) {
                    $error = "Ya existe una empresa registrada con ese nombre.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO empresas (nombre, rfc) VALUES (:nombre, :rfc)");
                    $stmt->execute([
                        'nombre' => $nombre,
                        'rfc' => !empty($rfc) ? $rfc : null
                    ]);
                    $success = "Empresa registrada exitosamente.";
                }
            } catch (PDOException $e) {
                $error = "Error al registrar la empresa: " . $e->getMessage();
            }
        }
    }

    // 2. Editar / Actualizar Empresa
    if ($accion === 'editar') {
        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre']);
        $rfc = trim($_POST['rfc']);

        if (empty($nombre)) {
            $error = "El nombre de la empresa es obligatorio.";
        } else {
            try {
                // Si la empresa es N/A no permitir cambiarle el nombre
                $stmt_chk = $pdo->prepare("SELECT nombre FROM empresas WHERE id = :id");
                $stmt_chk->execute(['id' => $id]);
                $old_name = $stmt_chk->fetchColumn();

                if ($old_name === 'N/A' && $nombre !== 'N/A') {
                    $error = "No se puede renombrar la empresa por defecto (N/A).";
                } else {
                    // Verificar nombre duplicado en otros registros
                    $stmt = $pdo->prepare("SELECT id FROM empresas WHERE nombre = :nombre AND id != :id LIMIT 1");
                    $stmt->execute(['nombre' => $nombre, 'id' => $id]);
                    if ($stmt->fetch()) {
                        $error = "Ya existe otra empresa registrada con ese nombre.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE empresas SET nombre = :nombre, rfc = :rfc WHERE id = :id");
                        $stmt->execute([
                            'nombre' => $nombre,
                            'rfc' => !empty($rfc) ? $rfc : null,
                            'id' => $id
                        ]);
                        $success = "Empresa actualizada exitosamente.";
                        $edit_mode = false; // Salir de modo edición
                    }
                }
            } catch (PDOException $e) {
                $error = "Error al actualizar la empresa: " . $e->getMessage();
            }
        }
    }

    // 3. Eliminar Empresa
    if ($accion === 'eliminar') {
        $id = (int)$_POST['id'];

        try {
            // Verificar nombre de la empresa
            $stmt = $pdo->prepare("SELECT nombre FROM empresas WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $emp_name = $stmt->fetchColumn();

            if ($emp_name === 'N/A') {
                $error = "No se puede eliminar la empresa por defecto (N/A).";
            } else {
                // Verificar si tiene documentos asociados
                $stmt_docs = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE empresa_id = :id");
                $stmt_docs->execute(['id' => $id]);
                $count_docs = (int)$stmt_docs->fetchColumn();

                if ($count_docs > 0) {
                    $error = "No se puede eliminar la empresa porque tiene {$count_docs} documento(s) asociado(s). Reasigne o elimine los documentos primero.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $success = "Empresa eliminada correctamente.";
                }
            }
        } catch (PDOException $e) {
            $error = "Error al eliminar la empresa: " . $e->getMessage();
        }
    }
}

// Obtener listado de todas las empresas con conteo de documentos
try {
    $sql = "SELECT e.id, e.nombre, e.rfc, e.created_at, COUNT(d.id) as total_documentos 
            FROM empresas e 
            LEFT JOIN documentos d ON e.id = d.empresa_id 
            GROUP BY e.id, e.nombre, e.rfc, e.created_at 
            ORDER BY e.nombre ASC";
    $stmt = $pdo->query($sql);
    $empresas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error al consultar empresas: " . $e->getMessage());
    $empresas = [];
}

// Incluir cabecera
require_once __DIR__ . '/includes/header.php';

// Incluir barra lateral
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Área de Contenido Principal -->
<div class="content-area">
    
    <!-- Barra Superior de Navegación Rápida -->
    <nav class="navbar navbar-top navbar-expand-lg navbar-light bg-transparent">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1 fw-bold fs-4">Gestión de Empresas / Entidades</span>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-semibold">
                    <i class="fa-solid fa-building me-1"></i> <?php echo count($empresas); ?> Registradas
                </span>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container-fluid p-0">

        <!-- Mensajes de Operaciones -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 animate-fade-in" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4 animate-fade-in" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- Columna Izquierda: Listado de Empresas (8/12) -->
            <div class="col-lg-8">
                <div class="p-4 rounded-4 bg-white" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
                    <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-list text-primary me-2"></i>Empresas y Razones Sociales</h5>
                    
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" style="border-color: #f1f5f9;">
                            <thead>
                                <tr class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                    <th class="pb-3" style="width: 70px;">ID</th>
                                    <th class="pb-3">Nombre Comercial / Razón Social</th>
                                    <th class="pb-3 text-center" style="width: 150px;">RFC</th>
                                    <th class="pb-3 text-center" style="width: 150px;">Documentos</th>
                                    <th class="pb-3 text-end" style="width: 150px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($empresas)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            No hay empresas registradas en el sistema.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($empresas as $e): ?>
                                        <tr>
                                            <td>
                                                <span class="text-muted fw-medium" style="font-size: 0.85rem;">#<?php echo $e['id']; ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 text-primary fw-bold" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                                        <?php 
                                                        $initials = '';
                                                        $words = explode(' ', $e['nombre']);
                                                        $initials .= strtoupper(substr($words[0], 0, 1));
                                                        if (isset($words[1]) && strtolower($words[0]) !== 'n/a') {
                                                            $initials .= strtoupper(substr($words[1], 0, 1));
                                                        }
                                                        echo htmlspecialchars(substr($initials, 0, 2));
                                                        ?>
                                                    </div>
                                                    <div>
                                                        <div class="text-dark fw-bold" style="font-size: 0.95rem;">
                                                            <?php echo htmlspecialchars($e['nombre']); ?>
                                                            <?php if ($e['nombre'] === 'N/A'): ?>
                                                                <span class="badge bg-secondary rounded-pill fw-medium ms-1" style="font-size: 0.7rem;">Defecto</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($e['rfc'])): ?>
                                                    <span class="font-monospace text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($e['rfc']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 0.85rem;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($e['total_documentos'] > 0): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold" style="font-size: 0.75rem;">
                                                        <i class="fa-solid fa-file-shield me-1"></i> <?php echo $e['total_documentos']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-2 fw-bold" style="font-size: 0.75rem;">
                                                        0
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end gap-2 align-items-center">
                                                    <!-- Botón de Editar -->
                                                    <a href="empresas.php?edit=<?php echo $e['id']; ?>" class="btn btn-outline-warning btn-sm rounded-3 py-1 px-2" title="Editar Empresa">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>

                                                    <!-- Botón de Eliminar (Deshabilitado si es la por defecto o tiene documentos) -->
                                                    <?php if ($e['nombre'] !== 'N/A' && $e['total_documentos'] == 0): ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm rounded-3 py-1 px-2" title="Eliminar Empresa" onclick="confirmarEliminar(<?php echo $e['id']; ?>)">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted px-2" title="<?php echo ($e['nombre'] === 'N/A') ? 'No se puede eliminar la empresa por defecto' : 'Tiene documentos asociados'; ?>">
                                                            <i class="fa-solid fa-lock"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Registrar/Editar Empresa (4/12) -->
            <div class="col-lg-4">
                <div class="p-4 rounded-4 bg-white" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
                    <h5 class="fw-bold text-dark mb-4">
                        <i class="fa-solid <?php echo $edit_mode ? 'fa-pen-to-square' : 'fa-plus'; ?> text-primary me-2"></i>
                        <?php echo $edit_mode ? 'Editar Empresa' : 'Registrar Empresa'; ?>
                    </h5>
                    
                    <form method="POST" action="empresas.php">
                        <input type="hidden" name="accion" value="<?php echo $edit_mode ? 'editar' : 'registrar'; ?>">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label fw-bold text-dark" style="font-size: 0.85rem;">Nombre / Razón Social *</label>
                            <input type="text" class="form-control rounded-3" id="nombre" name="nombre" placeholder="Ej. Einsur Supply S.A. de C.V." value="<?php echo htmlspecialchars($edit_nombre); ?>" required <?php echo ($edit_nombre === 'N/A') ? 'readonly' : ''; ?> style="border-color: #cbd5e1; padding: 0.6rem 0.8rem;">
                            <?php if ($edit_nombre === 'N/A'): ?>
                                <small class="text-muted">La empresa por defecto "N/A" no se puede renombrar.</small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label for="rfc" class="form-label fw-bold text-dark" style="font-size: 0.85rem;">RFC (Opcional)</label>
                            <input type="text" class="form-control rounded-3" id="rfc" name="rfc" placeholder="Ej. ESU120345AAA" value="<?php echo htmlspecialchars($edit_rfc); ?>" style="border-color: #cbd5e1; padding: 0.6rem 0.8rem;">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary-custom w-100 py-2.5 rounded-3 fw-bold">
                                <i class="fa-solid fa-save me-1"></i> <?php echo $edit_mode ? 'Guardar Cambios' : 'Guardar Empresa'; ?>
                            </button>
                            <?php if ($edit_mode): ?>
                                <a href="empresas.php" class="btn btn-outline-secondary w-100 py-2.5 rounded-3 fw-bold">
                                    Cancelar Edición
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
function confirmarEliminar(id) {
    if (confirm("¿Está seguro de que desea eliminar permanentemente esta empresa? Esta acción no se puede deshacer y solo es válida si la empresa no tiene documentos asociados.")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'empresas.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'accion';
        actionInput.value = 'eliminar';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
// Incluir el pie de página
require_once __DIR__ . '/includes/footer.php';
?>
