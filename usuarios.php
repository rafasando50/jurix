<?php
/**
 * Gestión de Usuarios Administrativos con Roles Jerárquicos
 * Ruta: /usuarios.php
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

// Definir pesos de rol para control jerárquico
function obtenerRangoRol($rol) {
    if ($rol === 'superadmin') return 3;
    if ($rol === 'admin') return 2;
    return 1; // 'usuario'
}

$rango_propio = obtenerRangoRol($_SESSION['user_rol']);

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // 1. Registrar Nuevo Usuario
    if ($accion === 'registrar') {
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $rol = isset($_POST['rol']) ? trim($_POST['rol']) : 'usuario';

        $rango_objetivo = obtenerRangoRol($rol);

        if (empty($nombre) || empty($email) || empty($password)) {
            $error = "Todos los campos son obligatorios.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "El formato de correo electrónico no es válido.";
        } elseif ($rango_objetivo >= $rango_propio) {
            $error = "No tienes permisos para crear un usuario con nivel de acceso igual o superior al tuyo.";
        } else {
            // Verificar si el correo ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $error = "El correo electrónico ya está registrado.";
            } else {
                // Registrar usuario
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, activo) VALUES (:nombre, :email, :password, :rol, 1)");
                    $stmt->execute([
                        'nombre' => $nombre,
                        'email' => $email,
                        'password' => $hashed_password,
                        'rol' => $rol
                    ]);
                    $success = "Usuario registrado exitosamente.";
                } catch (PDOException $e) {
                    $error = "Error al guardar el usuario: " . $e->getMessage();
                }
            }
        }
    }

    // 2. Alternar Estado (Activo/Inactivo)
    if ($accion === 'toggle') {
        $user_id = (int)$_POST['id'];
        
        if ($user_id === (int)$_SESSION['user_id']) {
            $error = "No puedes desactivar tu propia cuenta.";
        } else {
            try {
                // Obtener datos del usuario objetivo
                $stmt = $pdo->prepare("SELECT rol, activo FROM usuarios WHERE id = :id");
                $stmt->execute(['id' => $user_id]);
                $u = $stmt->fetch();
                
                if ($u) {
                    $rango_objetivo = obtenerRangoRol($u['rol']);
                    if ($rango_propio <= $rango_objetivo) {
                        $error = "No tienes permisos para desactivar a este usuario (rango superior o igual).";
                    } else {
                        $nuevo_estado = $u['activo'] ? 0 : 1;
                        $stmt = $pdo->prepare("UPDATE usuarios SET activo = :activo WHERE id = :id");
                        $stmt->execute(['activo' => $nuevo_estado, 'id' => $user_id]);
                        $success = "Estado del usuario actualizado correctamente.";
                    }
                } else {
                    $error = "Usuario no encontrado.";
                }
            } catch (PDOException $e) {
                $error = "Error al actualizar estado: " . $e->getMessage();
            }
        }
    }

    // 3. Eliminar Usuario
    if ($accion === 'eliminar') {
        $user_id = (int)$_POST['id'];

        if ($user_id === (int)$_SESSION['user_id']) {
            $error = "No puedes eliminar tu propia cuenta.";
        } else {
            try {
                // Obtener datos del usuario objetivo
                $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id = :id");
                $stmt->execute(['id' => $user_id]);
                $u = $stmt->fetch();
                
                if ($u) {
                    $rango_objetivo = obtenerRangoRol($u['rol']);
                    if ($rango_propio <= $rango_objetivo) {
                        $error = "No tienes permisos para eliminar a este usuario (rango superior o igual).";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
                        $stmt->execute(['id' => $user_id]);
                        $success = "Usuario eliminado correctamente.";
                    }
                } else {
                    $error = "Usuario no encontrado.";
                }
            } catch (PDOException $e) {
                $error = "Error al eliminar usuario: " . $e->getMessage();
            }
        }
    }
}

// Obtener listado de todos los usuarios
try {
    $sql = "SELECT id, nombre, email, rol, activo, created_at FROM usuarios ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error al consultar usuarios: " . $e->getMessage());
    $usuarios = [];
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
            <span class="navbar-brand mb-0 h1 fw-bold fs-4">Gestión de Usuarios</span>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-semibold">
                    <i class="fa-solid fa-users-gear me-1"></i> <?php echo count($usuarios); ?> Cuentas en Sistema
                </span>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container-fluid p-0">

        <!-- Mensajes de Operaciones -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- Columna Izquierda: Listado de Usuarios (8/12) -->
            <div class="col-lg-8">
                <div class="p-4 rounded-4 bg-white" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
                    <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-list text-primary me-2"></i>Usuarios Registrados</h5>
                    
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" style="border-color: #f1f5f9;">
                            <thead>
                                <tr class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                    <th class="pb-3" style="width: 70px;">ID</th>
                                    <th class="pb-3">Nombre / Email</th>
                                    <th class="pb-3 text-center">Rol</th>
                                    <th class="pb-3 text-center" style="width: 130px;">Estado</th>
                                    <th class="pb-3 text-end" style="width: 150px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            No hay usuarios registrados en el sistema.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $u): ?>
                                        <tr>
                                            <td>
                                                <span class="text-muted fw-medium" style="font-size: 0.85rem;">#<?php echo $u['id']; ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 text-primary fw-bold" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                                        <?php 
                                                        $initials = '';
                                                        $words = explode(' ', $u['nombre']);
                                                        $initials .= strtoupper(substr($words[0], 0, 1));
                                                        if (isset($words[1])) {
                                                            $initials .= strtoupper(substr($words[1], 0, 1));
                                                        }
                                                        echo htmlspecialchars($initials);
                                                        ?>
                                                    </div>
                                                    <div>
                                                        <div class="text-dark fw-bold" style="font-size: 0.95rem;">
                                                            <?php echo htmlspecialchars($u['nombre']); ?>
                                                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                                                <span class="badge bg-secondary rounded-pill fw-medium ms-1" style="font-size: 0.7rem;">Tú</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($u['email']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($u['rol'] === 'superadmin'): ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2.5 py-1.5 fw-bold" style="font-size: 0.75rem;">
                                                        <i class="fa-solid fa-crown me-1"></i> Super Admin
                                                    </span>
                                                <?php elseif ($u['rol'] === 'admin'): ?>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1.5 fw-bold" style="font-size: 0.75rem;">
                                                        <i class="fa-solid fa-user-tie me-1"></i> Admin
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2.5 py-1.5 fw-bold" style="font-size: 0.75rem;">
                                                        <i class="fa-solid fa-user me-1"></i> Consulta
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($u['activo']): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold" style="font-size: 0.75rem;">
                                                        <i class="fa-solid fa-circle-check me-1"></i> Activo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2 fw-bold" style="font-size: 0.75rem;">
                                                        <i class="fa-solid fa-circle-xmark me-1"></i> Inactivo
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end gap-2 align-items-center">
                                                    <?php 
                                                    // Solo se puede modificar si el rango propio es estrictamente mayor al del objetivo
                                                    $rango_objetivo = obtenerRangoRol($u['rol']);
                                                    if ($rango_propio > $rango_objetivo && $u['id'] != $_SESSION['user_id']): 
                                                    ?>
                                                        <!-- Botón de Alternar Estado (Toggle) -->
                                                        <form method="POST" action="usuarios.php" style="display:inline;">
                                                            <input type="hidden" name="accion" value="toggle">
                                                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                                            <?php if ($u['activo']): ?>
                                                                <button type="submit" class="btn btn-outline-warning btn-sm rounded-3 py-1 px-2" title="Desactivar Usuario">
                                                                    <i class="fa-solid fa-user-slash"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="submit" class="btn btn-outline-success btn-sm rounded-3 py-1 px-2" title="Activar Usuario">
                                                                    <i class="fa-solid fa-user-check"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </form>

                                                        <!-- Botón de Eliminar -->
                                                        <button type="button" class="btn btn-outline-danger btn-sm rounded-3 py-1 px-2" title="Eliminar Usuario" onclick="confirmarEliminar(<?php echo $u['id']; ?>)">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- Icono de Candado si no tiene privilegios jerárquicos -->
                                                        <span class="text-muted px-2" title="Sin permisos sobre este rango (Protección Jerárquica)">
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

            <!-- Columna Derecha: Registrar Usuario (4/12) -->
            <div class="col-lg-4">
                <div class="p-4 rounded-4 bg-white" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);">
                    <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-user-plus text-primary me-2"></i>Registrar Usuario</h5>
                    
                    <form method="POST" action="usuarios.php">
                        <input type="hidden" name="accion" value="registrar">
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label fw-bold text-dark" style="font-size: 0.85rem;">Nombre Completo</label>
                            <input type="text" class="form-control rounded-3" id="nombre" name="nombre" placeholder="Ej. Juan Pérez" required style="border-color: #cbd5e1; padding: 0.6rem 0.8rem;">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold text-dark" style="font-size: 0.85rem;">Correo Electrónico</label>
                            <input type="email" class="form-control rounded-3" id="email" name="email" placeholder="Ej. juan@sistema.com" required style="border-color: #cbd5e1; padding: 0.6rem 0.8rem;">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-bold text-dark" style="font-size: 0.85rem;">Contraseña</label>
                            <input type="password" class="form-control rounded-3" id="password" name="password" placeholder="Mínimo 6 caracteres" minlength="6" required style="border-color: #cbd5e1; padding: 0.6rem 0.8rem;">
                        </div>

                        <div class="mb-4">
                            <?php if ($_SESSION['user_rol'] === 'superadmin'): ?>
                                <label for="rol" class="form-label fw-bold text-dark" style="font-size: 0.85rem;">Rol de Usuario</label>
                                <select class="form-select rounded-3" id="rol" name="rol" required style="border-color: #cbd5e1; padding: 0.6rem 0.8rem;">
                                    <option value="admin">Administrador (Manejo de documentos y usuarios consulta)</option>
                                    <option value="usuario" selected>Usuario Consulta (Solo ver/buscar documentos)</option>
                                </select>
                            <?php else: ?>
                                <label class="form-label fw-bold text-dark" style="font-size: 0.85rem;">Rol de Usuario</label>
                                <input type="text" class="form-control rounded-3 bg-light" value="Usuario Consulta (Solo ver/buscar)" readonly style="border-color: #cbd5e1; padding: 0.6rem 0.8rem;">
                                <input type="hidden" name="rol" value="usuario">
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary-custom w-100 py-2.5 rounded-3 fw-bold">
                            <i class="fa-solid fa-save me-1"></i> Guardar Usuario
                        </button>
                    </form>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
function confirmarEliminar(id) {
    if (confirm("¿Está seguro de que desea eliminar permanentemente este usuario? Esta acción no se puede deshacer.")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'usuarios.php';
        
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
