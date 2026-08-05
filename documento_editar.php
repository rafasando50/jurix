<?php
/**
 * Edición de Documento Existente
 * Ruta: /documento_editar.php
 */

// Incluir conexión a base de datos
require_once __DIR__ . '/config/db.php';

// Incluir cabecera
require_once __DIR__ . '/includes/header.php';

// Validar que el rol no sea 'usuario'
if ($_SESSION['user_rol'] === 'usuario') {
    header("Location: dashboard.php");
    exit;
}

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

    // Obtener personas acreditadas relacionadas
    $stmt_p = $pdo->prepare("SELECT p.nombre 
                             FROM personas p 
                             JOIN documento_personas dp ON p.id = dp.persona_id 
                             WHERE dp.documento_id = :id");
    $stmt_p->execute(['id' => $id]);
    $acreditados = $stmt_p->fetchAll(PDO::FETCH_COLUMN);
    $doc['personas_acreditadas'] = implode(', ', $acreditados);

} catch (PDOException $e) {
    error_log("Error al consultar documento para edición: " . $e->getMessage());
    $error_message = "Error en el servidor al cargar los datos del documento.";
}

// Obtener lista de poderes/actas para el selector de revocación (excluyendo el actual)
$documentos_candidatos = [];
try {
    $stmt_cand = $pdo->prepare("SELECT id, numero_instrumento, libro, tipo, concepto FROM documentos WHERE tipo IN ('poder', 'acta') AND id != :current_id ORDER BY numero_instrumento ASC");
    $stmt_cand->execute(['current_id' => $id]);
    $documentos_candidatos = $stmt_cand->fetchAll();
} catch (PDOException $e) {
    error_log("Error al obtener candidatos de revocación en edición: " . $e->getMessage());
}

// Obtener lista de empresas
$empresas = [];
try {
    $stmt_emp = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC");
    $empresas = $stmt_emp->fetchAll();
} catch (PDOException $e) {
    error_log("Error al obtener lista de empresas en edición: " . $e->getMessage());
}

// Procesar el formulario si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doc) {
    // Sanitizar y recibir inputs
    $numero_instrumento = isset($_POST['numero_instrumento']) ? trim($_POST['numero_instrumento']) : '';
    $libro = isset($_POST['libro']) ? trim($_POST['libro']) : '';
    $fecha_expedicion = isset($_POST['fecha_expedicion']) ? trim($_POST['fecha_expedicion']) : '';
    $notaria = isset($_POST['notaria']) ? trim($_POST['notaria']) : '';
    $ciudad_notaria = isset($_POST['ciudad_notaria']) ? trim($_POST['ciudad_notaria']) : '';
    $estado_notaria = isset($_POST['estado_notaria']) ? trim($_POST['estado_notaria']) : '';
    $notario = isset($_POST['notario']) ? trim($_POST['notario']) : '';
    $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';
    $subtipo = isset($_POST['subtipo']) ? trim($_POST['subtipo']) : 'ninguno';
    $concepto = isset($_POST['concepto']) ? trim($_POST['concepto']) : '';
    $personas_acreditadas = isset($_POST['personas_acreditadas']) ? trim($_POST['personas_acreditadas']) : '';
    $revoca_documento_id = (isset($_POST['revoca_documento_id']) && $_POST['revoca_documento_id'] !== '') ? (int)$_POST['revoca_documento_id'] : null;
    $empresa_id = (isset($_POST['empresa_id']) && $_POST['empresa_id'] !== '') ? (int)$_POST['empresa_id'] : null;
    
    // Vigencia
    $tiene_vigencia = isset($_POST['tiene_vigencia']) ? true : false;
    $vigencia = ($tiene_vigencia && !empty($_POST['vigencia'])) ? $_POST['vigencia'] : null;

    // Validar campos obligatorios
    if (empty($numero_instrumento) || empty($libro) || empty($fecha_expedicion) || empty($notaria) || empty($ciudad_notaria) || empty($estado_notaria) || empty($notario) || empty($tipo) || empty($concepto)) {
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
                    estado_notaria = :estado_notaria, 
                    notario = :notario, 
                    tipo = :tipo, 
                    subtipo = :subtipo, 
                    concepto = :concepto, 
                    vigencia = :vigencia, 
                    archivo_path = :archivo_path,
                    revoca_documento_id = :revoca_documento_id,
                    empresa_id = :empresa_id
                    WHERE id = :id");
                
                $stmt->execute([
                    'numero_instrumento' => $numero_instrumento,
                    'libro' => $libro,
                    'fecha_expedicion' => $fecha_expedicion,
                    'notaria' => $notaria,
                    'ciudad_notaria' => $ciudad_notaria,
                    'estado_notaria' => $estado_notaria,
                    'notario' => $notario,
                    'tipo' => $tipo,
                    'subtipo' => $subtipo,
                    'concepto' => $concepto,
                    'vigencia' => $vigencia,
                    'archivo_path' => $archivo_path,
                    'revoca_documento_id' => $revoca_documento_id,
                    'empresa_id' => $empresa_id,
                    'id' => $id
                ]);

                // Actualizar personas acreditadas de forma relacional
                // 1. Eliminar relaciones antiguas
                $stmt_del_relations = $pdo->prepare("DELETE FROM documento_personas WHERE documento_id = :id");
                $stmt_del_relations->execute(['id' => $id]);

                // 2. Insertar las nuevas relaciones
                if (!empty($personas_acreditadas)) {
                    $names = array_filter(array_map('trim', explode(',', $personas_acreditadas)));
                    
                    $stmt_sel_persona = $pdo->prepare("SELECT id FROM personas WHERE nombre = :nombre");
                    $stmt_add_persona = $pdo->prepare("INSERT INTO personas (nombre) VALUES (:nombre)");
                    $stmt_ins_relation = $pdo->prepare("INSERT INTO documento_personas (documento_id, persona_id) VALUES (:documento_id, :persona_id)");
                    
                    foreach ($names as $name) {
                        if (empty($name)) continue;
                        
                        $stmt_sel_persona->execute(['nombre' => $name]);
                        $persona_id = $stmt_sel_persona->fetchColumn();
                        
                        if (!$persona_id) {
                            try {
                                $stmt_add_persona->execute(['nombre' => $name]);
                                $persona_id = $pdo->lastInsertId();
                            } catch (PDOException $ex) {
                                $stmt_sel_persona->execute(['nombre' => $name]);
                                $persona_id = $stmt_sel_persona->fetchColumn();
                            }
                        }
                        
                        if ($persona_id) {
                            try {
                                $stmt_ins_relation->execute([
                                    'documento_id' => $id,
                                    'persona_id' => $persona_id
                                ]);
                            } catch (PDOException $ex_rel) {
                                // Ignorar duplicados
                            }
                        }
                    }
                }

                $success_message = "Documento actualizado exitosamente.";
                
                // Recargar los nuevos datos locales
                $doc['numero_instrumento'] = $numero_instrumento;
                $doc['libro'] = $libro;
                $doc['fecha_expedicion'] = $fecha_expedicion;
                $doc['notaria'] = $notaria;
                $doc['ciudad_notaria'] = $ciudad_notaria;
                $doc['estado_notaria'] = $estado_notaria;
                $doc['notario'] = $notario;
                $doc['tipo'] = $tipo;
                $doc['subtipo'] = $subtipo;
                $doc['concepto'] = $concepto;
                $doc['personas_acreditadas'] = $personas_acreditadas;
                $doc['vigencia'] = $vigencia;
                $doc['archivo_path'] = $archivo_path;
                $doc['revoca_documento_id'] = $revoca_documento_id;

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
            <div class="col-12">
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
                            <div class="col-md-4">
                                <label for="tipo" class="form-label">Tipo de Documento *</label>
                                <select class="form-control" id="tipo" name="tipo" required onchange="actualizarSubtipos()">
                                    <option value="acta" <?php echo ($doc['tipo'] === 'acta') ? 'selected' : ''; ?>>Acta / Asamblea</option>
                                    <option value="poder" <?php echo ($doc['tipo'] === 'poder') ? 'selected' : ''; ?>>Poder Jurídico</option>
                                    <option value="revocacion" <?php echo ($doc['tipo'] === 'revocacion') ? 'selected' : ''; ?>>Revocación de Poder</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4" id="subtipo-container">
                                <label for="subtipo" class="form-label">Subtipo de Documento *</label>
                                <select class="form-control" id="subtipo" name="subtipo" required>
                                    <!-- Se llenará dinámicamente mediante javascript -->
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="empresa_id" class="form-label">Empresa / Entidad *</label>
                                <select class="form-control" id="empresa_id" name="empresa_id" required>
                                    <?php foreach ($empresas as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>" <?php echo ($doc['empresa_id'] == $emp['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 d-none" id="grupo_revocacion">
                                <label for="revoca_documento_id" class="form-label fw-bold text-dark">Poder / Acta que Revoca *</label>
                                <select class="form-control" id="revoca_documento_id" name="revoca_documento_id">
                                    <option value="">-- Seleccione el documento a revocar --</option>
                                    <?php foreach ($documentos_candidatos as $cand): ?>
                                        <option value="<?php echo $cand['id']; ?>" <?php echo ($doc['revoca_documento_id'] == $cand['id']) ? 'selected' : ''; ?>>
                                            [<?php echo ($cand['tipo'] === 'poder') ? 'Poder' : 'Acta'; ?>] Instrumento: <?php echo htmlspecialchars($cand['numero_instrumento']); ?>, Libro: <?php echo htmlspecialchars($cand['libro']); ?> - <?php echo htmlspecialchars(substr($cand['concepto'], 0, 80)); ?>...
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Seleccione el documento que quedará revocado (inhabilitado) por esta revocación.</small>
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
                            <div class="col-md-3">
                                <label for="notaria" class="form-label">Notaría Pública No. *</label>
                                <input type="text" class="form-control" id="notaria" name="notaria" placeholder="Ej. 4" value="<?php echo htmlspecialchars($doc['notaria']); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="ciudad_notaria" class="form-label">Ciudad de la Notaría *</label>
                                <input type="text" class="form-control" id="ciudad_notaria" name="ciudad_notaria" placeholder="Ej. Monterrey" value="<?php echo htmlspecialchars($doc['ciudad_notaria']); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="estado_notaria" class="form-label">Estado de la Notaría *</label>
                                <input type="text" class="form-control" id="estado_notaria" name="estado_notaria" placeholder="Ej. Nuevo León" value="<?php echo htmlspecialchars($doc['estado_notaria'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-3">
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

                        <div class="mb-3">
                            <label for="personas_acreditadas" class="form-label">Personas Acreditadas / Representantes / Titulares</label>
                            <textarea class="form-control" id="personas_acreditadas" name="personas_acreditadas" rows="2" placeholder="Escriba los nombres de las personas acreditadas o autorizadas en este documento..."><?php echo htmlspecialchars($doc['personas_acreditadas'] ?? ''); ?></textarea>
                            <small class="text-muted d-block mt-1">Escriba los nombres completos de las personas autorizadas o titulares de este documento. Puede separar múltiples nombres con comas.</small>
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

    // Mostrar/ocultar selector de revocación
    const grupoRevocacion = document.getElementById('grupo_revocacion');
    const revocaSelect = document.getElementById('revoca_documento_id');
    if (valorTipo === 'revocacion') {
        grupoRevocacion.classList.remove('d-none');
        revocaSelect.required = true;
    } else {
        grupoRevocacion.classList.add('d-none');
        revocaSelect.required = false;
        revocaSelect.value = '';
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
