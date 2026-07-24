<?php
/**
 * Edición de Documento Existente
 * Ruta: /documento_editar.php
 */

// Incluir conexión a base de datos
require_once __DIR__ . '/config/db.php';

// Incluir cabecera
require_once __DIR__ . '/includes/header.php';

// Incluir barra lateral
require_once __DIR__ . '/includes/sidebar.php';

$error_message = "";
$success_message = "";
$doc = null;

// Obtener el ID del documento
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo "<script>window.location.href = 'documentos.php';</script>";
    exit;
}

// Cargar datos actuales del documento
try {
    $stmt = $pdo->prepare("SELECT * FROM documentos WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        echo "<script>window.location.href = 'documentos.php';</script>";
        exit;
    }
} catch (PDOException $e) {
    error_log("Error al consultar documento para edición: " . $e->getMessage());
    $error_message = "Error en el servidor al cargar los datos del documento.";
}

// Procesar el formulario si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doc) {
    // Sanitizar y recibir inputs
    $numero_instrumento = isset($_POST['numero_instrumento']) ? trim($_POST['numero_instrumento']) : '';
    $libro = isset($_POST['libro']) ? trim($_POST['libro']) : '';
    $fecha_expedicion = isset($_POST['fecha_expedicion']) ? trim($_POST['fecha_expedicion']) : '';
    $notaria = isset($_POST['notaria']) ? trim($_POST['notaria']) : '';
    $ciudad_notaria = isset($_POST['ciudad_notaria']) ? trim($_POST['ciudad_notaria']) : '';
    $notario = isset($_POST['notario']) ? trim($_POST['notario']) : '';
    $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';
    $subtipo = isset($_POST['subtipo']) ? trim($_POST['subtipo']) : 'ninguno';
    $concepto = isset($_POST['concepto']) ? trim($_POST['concepto']) : '';
    
    // Vigencia
    $tiene_vigencia = isset($_POST['tiene_vigencia']) ? true : false;
    $vigencia = ($tiene_vigencia && !empty($_POST['vigencia'])) ? $_POST['vigencia'] : null;

    // Validar campos obligatorios
    if (empty($numero_instrumento) || empty($libro) || empty($fecha_expedicion) || empty($notaria) || empty($ciudad_notaria) || empty($notario) || empty($tipo) || empty($concepto)) {
        $error_message = "Por favor, complete todos los campos obligatorios.";
    } else {
        // Mantener la ruta del archivo anterior
        $archivo_path = $doc['archivo_path'];
        
        // Manejar subida de archivo PDF si se seleccionó uno nuevo
        if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['archivo_pdf']['tmp_name'];
            $file_name = $_FILES['archivo_pdf']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if ($file_ext !== 'pdf') {
                $error_message = "Solo se permiten archivos en formato PDF.";
            } else {
                $upload_dir = __DIR__ . '/assets/uploads';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Generar un nombre único para evitar colisiones
                $new_file_name = 'doc_' . time() . '_' . uniqid() . '.pdf';
                $dest_path = $upload_dir . '/' . $new_file_name;

                if (move_uploaded_file($file_tmp, $dest_path)) {
                    // Borrar el archivo anterior físicamente para evitar basura
                    if (!empty($doc['archivo_path']) && file_exists(__DIR__ . '/' . $doc['archivo_path'])) {
                        unlink(__DIR__ . '/' . $doc['archivo_path']);
                    }
                    $archivo_path = 'assets/uploads/' . $new_file_name;
                } else {
                    $error_message = "Ocurrió un error al subir el nuevo archivo al servidor.";
                }
            }
        }

        // Si no hay errores, actualizar en la base de datos
        if (empty($error_message)) {
            try {
                $stmt = $pdo->prepare("UPDATE documentos SET 
                    numero_instrumento = :numero_instrumento, 
                    libro = :libro, 
                    fecha_expedicion = :fecha_expedicion, 
                    notaria = :notaria, 
                    ciudad_notaria = :ciudad_notaria, 
                    notario = :notario, 
                    tipo = :tipo, 
                    subtipo = :subtipo, 
                    concepto = :concepto, 
                    vigencia = :vigencia, 
                    archivo_path = :archivo_path 
                    WHERE id = :id");
                
                $stmt->execute([
                    'numero_instrumento' => $numero_instrumento,
                    'libro' => $libro,
                    'fecha_expedicion' => $fecha_expedicion,
                    'notaria' => $notaria,
                    'ciudad_notaria' => $ciudad_notaria,
                    'notario' => $notario,
                    'tipo' => $tipo,
                    'subtipo' => $subtipo,
                    'concepto' => $concepto,
                    'vigencia' => $vigencia,
                    'archivo_path' => $archivo_path,
                    'id' => $id
                ]);

                $success_message = "Documento actualizado exitosamente.";
                
                // Recargar los nuevos datos locales
                $doc['numero_instrumento'] = $numero_instrumento;
                $doc['libro'] = $libro;
                $doc['fecha_expedicion'] = $fecha_expedicion;
                $doc['notaria'] = $notaria;
                $doc['ciudad_notaria'] = $ciudad_notaria;
                $doc['notario'] = $notario;
                $doc['tipo'] = $tipo;
                $doc['subtipo'] = $subtipo;
                $doc['concepto'] = $concepto;
                $doc['vigencia'] = $vigencia;
                $doc['archivo_path'] = $archivo_path;

                // Redirigir al listado después de 1.5 segundos
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'documentos.php';
                    }, 1500);
                </script>";
            } catch (PDOException $e) {
                error_log("Error al actualizar documento: " . $e->getMessage());
                $error_message = "Error en el servidor al intentar actualizar el documento: " . $e->getMessage();
            }
        }
    }
}
?>

<!-- Área de Contenido Principal -->
<div class="content-area">
    
    <!-- Barra Superior de Navegación Rápida -->
    <nav class="navbar navbar-top navbar-expand-lg navbar-light bg-transparent">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1 fw-bold fs-4">Editar Documento</span>
            <div class="ms-auto d-flex align-items-center gap-3">
                <a href="documentos.php" class="btn btn-outline-secondary py-2 px-3 rounded-3 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-arrow-left"></i> Volver al Listado
                </a>
            </div>
        </div>
    </nav>

    <!-- Contenido del Formulario -->
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-lg-10 col-xl-8">
                <div class="p-4 p-md-5 rounded-4 bg-white" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
                    
                    <!-- Alertas -->
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger d-flex align-items-center border-0 bg-danger bg-opacity-15 text-danger rounded-3 mb-4 animate-fade-in" role="alert">
                            <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                            <div><?php echo htmlspecialchars($error_message); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success d-flex align-items-center border-0 bg-success bg-opacity-15 text-success rounded-3 mb-4 animate-fade-in" role="alert">
                            <i class="fa-solid fa-circle-check me-2 fs-5"></i>
                            <div><?php echo htmlspecialchars($success_message); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($doc): ?>
                    <form action="documento_editar.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data" autocomplete="off">
                        
                        <!-- Sección 1: Clasificación del Documento -->
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-4"><i class="fa-solid fa-tags text-primary me-2"></i>Clasificación del Documento</h5>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="tipo" class="form-label">Tipo de Documento *</label>
                                <select class="form-control" id="tipo" name="tipo" required onchange="actualizarSubtipos()">
                                    <option value="acta" <?php echo ($doc['tipo'] === 'acta') ? 'selected' : ''; ?>>Acta / Asamblea</option>
                                    <option value="poder" <?php echo ($doc['tipo'] === 'poder') ? 'selected' : ''; ?>>Poder Jurídico</option>
                                    <option value="revocacion" <?php echo ($doc['tipo'] === 'revocacion') ? 'selected' : ''; ?>>Revocación de Poder</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6" id="subtipo-container">
                                <label for="subtipo" class="form-label">Subtipo de Documento *</label>
                                <select class="form-control" id="subtipo" name="subtipo" required>
                                    <!-- Se llenará dinámicamente mediante javascript -->
                                </select>
                            </div>
                        </div>

                        <!-- Sección 2: Datos del Instrumento -->
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-4"><i class="fa-solid fa-file-lines text-primary me-2"></i>Datos de Expedición</h5>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="numero_instrumento" class="form-label">Número de Instrumento *</label>
                                <input type="text" class="form-control" id="numero_instrumento" name="numero_instrumento" placeholder="Ej. 15,245" value="<?php echo htmlspecialchars($doc['numero_instrumento']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="libro" class="form-label">Libro *</label>
                                <input type="text" class="form-control" id="libro" name="libro" placeholder="Ej. 104" value="<?php echo htmlspecialchars($doc['libro']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="fecha_expedicion" class="form-label">Fecha de Expedición *</label>
                                <input type="date" class="form-control" id="fecha_expedicion" name="fecha_expedicion" value="<?php echo htmlspecialchars($doc['fecha_expedicion']); ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="notaria" class="form-label">Notaría Pública No. *</label>
                                <input type="text" class="form-control" id="notaria" name="notaria" placeholder="Ej. 4" value="<?php echo htmlspecialchars($doc['notaria']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="ciudad_notaria" class="form-label">Ciudad de la Notaría *</label>
                                <input type="text" class="form-control" id="ciudad_notaria" name="ciudad_notaria" placeholder="Ej. Monterrey" value="<?php echo htmlspecialchars($doc['ciudad_notaria']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="notario" class="form-label">Notario Titular *</label>
                                <input type="text" class="form-control" id="notario" name="notario" placeholder="Ej. Lic. Juan Pérez" value="<?php echo htmlspecialchars($doc['notario']); ?>" required>
                            </div>
                        </div>

                        <!-- Sección 3: Concepto y Vigencia -->
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-4"><i class="fa-solid fa-calendar-days text-primary me-2"></i>Concepto y Vigencia</h5>
                        
                        <div class="mb-3">
                            <label for="concepto" class="form-label">Concepto / Descripción del Documento *</label>
                            <textarea class="form-control" id="concepto" name="concepto" rows="3" placeholder="Describe el alcance del acta o las facultades otorgadas..." required><?php echo htmlspecialchars($doc['concepto']); ?></textarea>
                        </div>

                        <div class="row g-3 align-items-center mb-4">
                            <div class="col-md-6">
                                <div class="form-check form-switch pt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="tiene_vigencia" name="tiene_vigencia" onchange="toggleVigenciaInput()" <?php echo !empty($doc['vigencia']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-medium text-dark" for="tiene_vigencia">¿Este documento tiene fecha de vencimiento/vigencia?</label>
                                </div>
                            </div>
                            <div class="col-md-6 <?php echo empty($doc['vigencia']) ? 'd-none' : ''; ?>" id="vigencia-container">
                                <label for="vigencia" class="form-label">Fecha de Vencimiento / Vigencia</label>
                                <input type="date" class="form-control" id="vigencia" name="vigencia" value="<?php echo htmlspecialchars($doc['vigencia'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Sección 4: Archivo Adjunto -->
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-4"><i class="fa-solid fa-cloud-arrow-up text-primary me-2"></i>Archivo Adjunto</h5>
                        
                        <div class="mb-4">
                            <?php if (!empty($doc['archivo_path'])): ?>
                                <div class="p-3 mb-3 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-file-pdf text-danger fs-4"></i>
                                        <div>
                                            <div class="fw-semibold text-dark" style="font-size: 0.9rem;">Archivo actual registrado</div>
                                            <a href="<?php echo htmlspecialchars($doc['archivo_path']); ?>" target="_blank" class="text-primary text-decoration-none" style="font-size: 0.8rem;">Ver documento PDF actual</a>
                                        </div>
                                    </div>
                                    <span class="badge bg-secondary">Registrado</span>
                                </div>
                            <?php endif; ?>
                            
                            <label for="archivo_pdf" class="form-label">Subir nuevo PDF para reemplazar (Opcional)</label>
                            <input type="file" class="form-control" id="archivo_pdf" name="archivo_pdf" accept="application/pdf">
                            <small class="text-muted d-block mt-1">Deje este campo vacío si no desea modificar el archivo actual.</small>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-flex justify-content-end gap-3 border-top pt-4">
                            <a href="documentos.php" class="btn btn-light border px-4 py-2 rounded-3 text-dark">Cancelar</a>
                            <button type="submit" class="btn btn-primary-custom px-5 py-2">Guardar Cambios</button>
                        </div>

                    </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lógica dinámica de selección -->
<script>
// Guardar el subtipo actual del documento para pre-seleccionarlo
const subtipoActual = '<?php echo $doc['subtipo'] ?? ''; ?>';

function actualizarSubtipos() {
    const tipoSelect = document.getElementById('tipo');
    const subtipoSelect = document.getElementById('subtipo');
    const subtipoContainer = document.getElementById('subtipo-container');
    
    const valorTipo = tipoSelect.value;
    
    // Limpiar opciones
    subtipoSelect.innerHTML = '';
    
    if (valorTipo === 'acta') {
        subtipoContainer.style.display = 'block';
        subtipoSelect.required = true;
        
        const opciones = [
            { val: 'constitutiva', text: 'Acta Constitutiva' },
            { val: 'asamblea_ordinaria', text: 'Asamblea Ordinaria' },
            { val: 'asamblea_extraordinaria', text: 'Asamblea Extraordinaria' }
        ];
        
        opciones.forEach(op => {
            const el = document.createElement('option');
            el.value = op.val;
            el.textContent = op.text;
            if (op.val === subtipoActual) {
                el.selected = true;
            }
            subtipoSelect.appendChild(el);
        });
    } else if (valorTipo === 'poder') {
        subtipoContainer.style.display = 'block';
        subtipoSelect.required = true;
        
        const opciones = [
            { val: 'poder_amplio', text: 'Poder Amplio' },
            { val: 'poder_especifico', text: 'Poder Específico' },
            { val: 'poder_actas_administrativas', text: 'Poder de Actas Administrativas' }
        ];
        
        opciones.forEach(op => {
            const el = document.createElement('option');
            el.value = op.val;
            el.textContent = op.text;
            if (op.val === subtipoActual) {
                el.selected = true;
            }
            subtipoSelect.appendChild(el);
        });
    } else {
        subtipoContainer.style.display = 'block';
        subtipoSelect.required = false;
        
        const el = document.createElement('option');
        el.value = 'ninguno';
        el.textContent = 'No aplica (Revocación)';
        if ('ninguno' === subtipoActual) {
            el.selected = true;
        }
        subtipoSelect.appendChild(el);
    }
}

function toggleVigenciaInput() {
    const tieneVigenciaCheckbox = document.getElementById('tiene_vigencia');
    const vigenciaContainer = document.getElementById('vigencia-container');
    const vigenciaInput = document.getElementById('vigencia');
    
    if (tieneVigenciaCheckbox.checked) {
        vigenciaContainer.classList.remove('d-none');
        vigenciaInput.required = true;
    } else {
        vigenciaContainer.classList.add('d-none');
        vigenciaInput.required = false;
        vigenciaInput.value = '';
    }
}

// Inicializar el formulario con los datos pre-cargados
window.onload = function() {
    actualizarSubtipos();
    // En caso de que se haya cargado una revocación u otro tipo
    // la llamada a actualizarSubtipos ya establece el valor correcto
};
</script>

<?php
// Incluir el pie de página
require_once __DIR__ . '/includes/footer.php';
?>
