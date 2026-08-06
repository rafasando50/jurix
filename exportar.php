<?php
/**
 * Script de Exportación de Documentos (Excel / PDF)
 * Ruta: /exportar.php
 */

session_start();

// Validar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Acceso denegado. Por favor, inicie sesión.");
}

// Incluir conexión a base de datos
require_once __DIR__ . '/config/db.php';

// Obtener parámetros
$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$formato = isset($_GET['formato']) ? trim($_GET['formato']) : '';

// Validar parámetros mínimos
if (!in_array($formato, ['excel', 'pdf'])) {
    http_response_code(400);
    die("Formato inválido.");
}

$hoy = date('Y-m-d');
$limite = date('Y-m-d', strtotime('+15 days'));
$documentos = [];
$acreditados_map = [];
$socios_map = [];

// 1. Obtener los documentos a exportar
try {
    if ($tipo === 'expirar') {
        // Consultar documentos que expiran en los siguientes 15 días (Alertas)
        $sql = "SELECT d.*, e.nombre AS empresa_nombre 
                FROM documentos d 
                LEFT JOIN empresas e ON d.empresa_id = e.id 
                WHERE d.vigencia IS NOT NULL 
                  AND d.vigencia >= :hoy 
                  AND d.vigencia <= :limite 
                ORDER BY d.vigencia ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['hoy' => $hoy, 'limite' => $limite]);
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Consultar documentos filtrados de manera idéntica a documentos.php
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $tipo_filter = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
        $subtipo = isset($_GET['subtipo']) ? trim($_GET['subtipo']) : '';
        $vencimiento = isset($_GET['vencimiento']) ? trim($_GET['vencimiento']) : '';
        $empresa_id = isset($_GET['empresa_id']) ? trim($_GET['empresa_id']) : '';

        $sql = "SELECT d.*, e.nombre AS empresa_nombre 
                FROM documentos d 
                LEFT JOIN empresas e ON d.empresa_id = e.id 
                WHERE 1=1";
        $params = [];

        if ($empresa_id !== '') {
            $sql .= " AND d.empresa_id = :empresa_id";
            $params['empresa_id'] = (int)$empresa_id;
        }

        if (!empty($tipo_filter)) {
            $sql .= " AND d.tipo = :tipo";
            $params['tipo'] = $tipo_filter;
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
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. Obtener acreditados y socios para los documentos de manera eficiente
    if (!empty($documentos)) {
        $doc_ids = array_column($documentos, 'id');
        $placeholders = implode(',', array_fill(0, count($doc_ids), '?'));

        // Obtener Acreditados
        $stmt_acred = $pdo->prepare("SELECT dp.documento_id, p.nombre 
                                     FROM documento_personas dp 
                                     JOIN personas p ON dp.persona_id = p.id 
                                     WHERE dp.documento_id IN ($placeholders)");
        $stmt_acred->execute($doc_ids);
        while ($row_acred = $stmt_acred->fetch(PDO::FETCH_ASSOC)) {
            $acreditados_map[$row_acred['documento_id']][] = $row_acred['nombre'];
        }

        // Obtener Socios
        $stmt_soc = $pdo->prepare("SELECT documento_id, nombre, nacionalidad, domicilio_social, numero_acciones, valor_nominal, tipo_capital 
                                   FROM documento_socios 
                                   WHERE documento_id IN ($placeholders)");
        $stmt_soc->execute($doc_ids);
        while ($row_soc = $stmt_soc->fetch(PDO::FETCH_ASSOC)) {
            $socios_map[$row_soc['documento_id']][] = $row_soc;
        }
    }
} catch (PDOException $e) {
    error_log("Error al consultar datos de exportación: " . $e->getMessage());
    die("Ocurrió un error al procesar el reporte.");
}

// 3. Generar la exportación en Excel (CSV)
if ($formato === 'excel') {
    if ($tipo === 'expirar') {
        $filename = "documentos_vencidos_alertas_" . date('Ymd_His') . ".csv";
        $header = ['Tipo', 'Subtipo', 'No. Instrumento', 'Libro', 'Nombre / Razón Social', 'Fecha Expedición', 'Concepto', 'Vigencia', 'Días Restantes', 'Notaría', 'Notario', 'Administrador Único', 'Comisario', 'Detalle (Acreditados/Socios)'];
        
        $data = [];
        foreach ($documentos as $doc) {
            $tipoLabel = '';
            if ($doc['tipo'] === 'acta') {
                $sub = $doc['subtipo'];
                if ($sub === 'constitutiva') $tipoLabel = 'Acta Constitutiva';
                elseif ($sub === 'asamblea_ordinaria') $tipoLabel = 'Asamblea Ordinaria';
                elseif ($sub === 'asamblea_extraordinaria') $tipoLabel = 'Asamblea Extraordinaria';
                else $tipoLabel = 'Acta';
            } elseif ($doc['tipo'] === 'poder') {
                $sub = $doc['subtipo'];
                if ($sub === 'poder_amplio') $tipoLabel = 'Poder Amplio';
                elseif ($sub === 'poder_especifico') $tipoLabel = 'Poder Específico';
                elseif ($sub === 'poder_actas_administrativas') $tipoLabel = 'Poder Actas Adm.';
                else $tipoLabel = 'Poder';
            } else {
                $tipoLabel = 'Revocación';
            }

            $diff = strtotime($doc['vigencia']) - strtotime($hoy);
            $dias_restantes = (int)floor($diff / (60 * 60 * 24));

            $detalle = '';
            if ($doc['tipo'] === 'acta' && isset($socios_map[$doc['id']])) {
                $socios_list = array_map(function($s) {
                    $nacionalidad = !empty($s['nacionalidad']) ? $s['nacionalidad'] : 'Mexicana';
                    $domicilio = !empty($s['domicilio_social']) ? ', ' . $s['domicilio_social'] : '';
                    return $s['nombre'] . ' (' . $nacionalidad . $domicilio . ', ' . $s['numero_acciones'] . ' acc., $' . number_format($s['valor_nominal'], 2) . ' ' . $s['tipo_capital'] . ')';
                }, $socios_map[$doc['id']]);
                $detalle = implode(', ', $socios_list);
            } else {
                $detalle = isset($acreditados_map[$doc['id']]) ? implode(', ', $acreditados_map[$doc['id']]) : '';
            }

            $data[] = [
                $tipoLabel,
                $doc['subtipo'] !== 'ninguno' ? $doc['subtipo'] : '',
                $doc['numero_instrumento'],
                $doc['libro'],
                date('d/m/Y', strtotime($doc['fecha_expedicion'])),
                $doc['empresa_nombre'] ?? 'N/A',
                $doc['concepto'],
                date('d/m/Y', strtotime($doc['vigencia'])),
                $dias_restantes . ' días',
                'No. ' . $doc['notaria'],
                $doc['notario'],
                $doc['administrador_unico'] ?? '',
                $doc['comisario'] ?? '',
                $detalle
            ];
        }
    } else {
        $filename = "reporte_documentos_" . date('Ymd_His') . ".csv";
        $header = ['Tipo', 'Subtipo', 'No. Instrumento', 'Libro', 'Fecha Expedición', 'Nombre / Razón Social', 'Notaría', 'Notario', 'Ciudad/Estado', 'Concepto', 'Vigencia', 'FME', 'Fecha Reg. RPC', 'Administrador Único', 'Comisario', 'Detalle (Acreditados/Socios)'];
        
        $data = [];
        foreach ($documentos as $doc) {
            $tipoLabel = '';
            if ($doc['tipo'] === 'acta') {
                $tipoLabel = 'Acta / Asamblea';
            } elseif ($doc['tipo'] === 'poder') {
                $tipoLabel = 'Poder Jurídico';
            } elseif ($doc['tipo'] === 'revocacion') {
                $tipoLabel = 'Revocación';
            } else {
                $tipoLabel = ucfirst($doc['tipo']);
            }

            $subtipoLabel = '';
            if ($doc['subtipo'] === 'constitutiva') $subtipoLabel = 'Constitutiva';
            elseif ($doc['subtipo'] === 'asamblea_ordinaria') $subtipoLabel = 'Asamblea Ordinaria';
            elseif ($doc['subtipo'] === 'asamblea_extraordinaria') $subtipoLabel = 'Asamblea Extraordinaria';
            elseif ($doc['subtipo'] === 'poder_amplio') $subtipoLabel = 'Poder Amplio';
            elseif ($doc['subtipo'] === 'poder_especifico') $subtipoLabel = 'Poder Específico';
            elseif ($doc['subtipo'] === 'poder_actas_administrativas') $subtipoLabel = 'Poder Actas Adm.';

            $lugar = $doc['ciudad_notaria'] . (!empty($doc['estado_notaria']) ? ', ' . $doc['estado_notaria'] : '');
            $vigencia = empty($doc['vigencia']) ? 'Permanente' : date('d/m/Y', strtotime($doc['vigencia']));
            
            $detalle = '';
            if ($doc['tipo'] === 'acta' && isset($socios_map[$doc['id']])) {
                $socios_list = array_map(function($s) {
                    $nacionalidad = !empty($s['nacionalidad']) ? $s['nacionalidad'] : 'Mexicana';
                    $domicilio = !empty($s['domicilio_social']) ? ', ' . $s['domicilio_social'] : '';
                    return $s['nombre'] . ' (' . $nacionalidad . $domicilio . ', ' . $s['numero_acciones'] . ' acc., $' . number_format($s['valor_nominal'], 2) . ' ' . $s['tipo_capital'] . ')';
                }, $socios_map[$doc['id']]);
                $detalle = implode(', ', $socios_list);
            } else {
                $detalle = isset($acreditados_map[$doc['id']]) ? implode(', ', $acreditados_map[$doc['id']]) : '';
            }

            $data[] = [
                $tipoLabel,
                $subtipoLabel,
                $doc['numero_instrumento'],
                $doc['libro'],
                date('d/m/Y', strtotime($doc['fecha_expedicion'])),
                $doc['empresa_nombre'] ?? 'N/A',
                'No. ' . $doc['notaria'],
                $doc['notario'],
                $lugar,
                $doc['concepto'],
                $vigencia,
                $doc['fme'] ?? '',
                !empty($doc['fecha_registro_rpc']) ? date('d/m/Y', strtotime($doc['fecha_registro_rpc'])) : '',
                $doc['administrador_unico'] ?? '',
                $doc['comisario'] ?? '',
                $detalle
            ];
        }
    }

    // Configurar cabeceras de descarga de Excel/CSV
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    // Output UTF-8 BOM so Excel opens it with correct encoding
    echo "\xEF\xBB\xBF";
    $output = fopen("php://output", "w");
    fputcsv($output, $header);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// 4. Generar la vista imprimible para PDF
if ($formato === 'pdf'):
    $title = 'Reporte de Documentos';
    if ($tipo === 'expirar') {
        $title = 'Alertas de Vencimiento de Documentos';
    } elseif (!empty($_GET['tipo'])) {
        if ($_GET['tipo'] === 'poder') $title = 'Reporte de Poderes Jurídicos';
        elseif ($_GET['tipo'] === 'acta') $title = 'Reporte de Actas y Asambleas';
        elseif ($_GET['tipo'] === 'revocacion') $title = 'Reporte de Revocaciones';
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #ffffff;
            font-size: 0.85rem;
            color: #1e293b;
        }
        .report-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .table th {
            background-color: #f8fafc !important;
            color: #0f172a !important;
            font-weight: 700;
            border-bottom: 2px solid #cbd5e1 !important;
        }
        .table td {
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-size: 0.75rem;
            }
            .table-responsive {
                overflow: visible !important;
            }
        }
    </style>
</head>
<body>

    <!-- Panel de control de impresión superior -->
    <div class="no-print bg-light border-bottom py-3 px-4 mb-4 d-flex align-items-center justify-content-between">
        <div>
            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-file-pdf text-danger me-2"></i>Vista de Impresión / Guardar PDF</h5>
            <small class="text-muted">Utilice el diálogo de impresión de su navegador para guardar este reporte como PDF o imprimirlo.</small>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print();" class="btn btn-primary d-flex align-items-center gap-2 px-3 py-2 rounded-3">
                <i class="fa-solid fa-print"></i> Imprimir / Guardar PDF
            </button>
            <button onclick="window.close();" class="btn btn-outline-secondary px-3 py-2 rounded-3">
                Cerrar Ventana
            </button>
        </div>
    </div>

    <div class="container-fluid py-2 px-4">
        <!-- Encabezado del Reporte -->
        <div class="report-header d-flex align-items-center justify-content-between pb-3 mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1">Reporte</h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">
                    Tipo: <strong><?php echo htmlspecialchars($title); ?></strong> | Generado el: <strong><?php echo date('d/m/Y H:i'); ?></strong> | Usuario: <?php echo htmlspecialchars($_SESSION['user_nombre']); ?>
                </p>
            </div>
            <div class="text-end">
                <h4 class="fw-bold text-primary mb-0">SISCORL</h4>
                <small class="text-muted">Einsur Supply S.A. de C.V.</small>
            </div>
        </div>

        <?php if (empty($documentos)): ?>
            <div class="alert alert-info text-center py-5 rounded-4">
                <i class="fa-regular fa-folder-open d-block fs-1 mb-3 opacity-50"></i>
                No hay documentos registrados para esta sección.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <?php if ($tipo === 'expirar'): ?>
                    <!-- Tabla de Alertas de Vencimiento -->
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Tipo Documento</th>
                                <th style="width: 10%;">Inst./Libro</th>
                                <th style="width: 15%;">Nombre / Razón Social</th>
                                <th style="width: 10%;">Fecha Exped.</th>
                                <th style="width: 25%;">Concepto</th>
                                <th style="width: 13%;">Vigencia</th>
                                <th style="width: 12%;">Días Restantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documentos as $doc): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $tipoLabel = '';
                                        if ($doc['tipo'] === 'acta') {
                                            $sub = $doc['subtipo'];
                                            if ($sub === 'constitutiva') $tipoLabel = 'Acta Constitutiva';
                                            elseif ($sub === 'asamblea_ordinaria') $tipoLabel = 'Asamblea Ordinaria';
                                            elseif ($sub === 'asamblea_extraordinaria') $tipoLabel = 'Asamblea Extraordinaria';
                                            else $tipoLabel = 'Acta';
                                        } elseif ($doc['tipo'] === 'poder') {
                                            $sub = $doc['subtipo'];
                                            if ($sub === 'poder_amplio') $tipoLabel = 'Poder Amplio';
                                            elseif ($sub === 'poder_especifico') $tipoLabel = 'Poder Específico';
                                            elseif ($sub === 'poder_actas_administrativas') $tipoLabel = 'Poder Actas Adm.';
                                            else $tipoLabel = 'Poder';
                                        } else {
                                            $tipoLabel = 'Revocación';
                                        }
                                        echo htmlspecialchars($tipoLabel);
                                        ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold">No. <?php echo htmlspecialchars($doc['numero_instrumento']); ?></div>
                                        <small class="text-muted">L: <?php echo htmlspecialchars($doc['libro']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($doc['empresa_nombre'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($doc['fecha_expedicion'])); ?></td>
                                    <td>
                                        <div class="fw-medium mb-1"><?php echo htmlspecialchars($doc['concepto']); ?></div>
                                        
                                        <?php if ($doc['tipo'] === 'acta'): ?>
                                            <?php if (isset($socios_map[$doc['id']])): 
                                                $socios_list = array_map(function($s) {
                                                    $nacionalidad = !empty($s['nacionalidad']) ? $s['nacionalidad'] : 'Mexicana';
                                                    $domicilio = !empty($s['domicilio_social']) ? ', ' . $s['domicilio_social'] : '';
                                                    return $s['nombre'] . ' (' . $nacionalidad . $domicilio . ', ' . $s['numero_acciones'] . ' acc., $' . number_format($s['valor_nominal'], 2) . ')';
                                                }, $socios_map[$doc['id']]);
                                            ?>
                                                <div style="font-size: 0.75rem;" class="text-info mt-1">
                                                    <strong>Socios:</strong> <?php echo htmlspecialchars(implode(', ', $socios_list)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($doc['administrador_unico']) || !empty($doc['comisario'])): ?>
                                                <div style="font-size: 0.75rem;" class="text-info mt-1">
                                                    <?php if (!empty($doc['administrador_unico'])): ?>
                                                        <strong>Adm. Único:</strong> <?php echo htmlspecialchars($doc['administrador_unico']); ?> &nbsp;&nbsp;
                                                    <?php endif; ?>
                                                    <?php if (!empty($doc['comisario'])): ?>
                                                        <strong>Comisario:</strong> <?php echo htmlspecialchars($doc['comisario']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif (isset($acreditados_map[$doc['id']])): ?>
                                            <div style="font-size: 0.75rem;" class="text-success mt-1">
                                                <strong>Acreditados:</strong> <?php echo htmlspecialchars(implode(', ', $acreditados_map[$doc['id']])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-danger fw-semibold"><?php echo date('d/m/Y', strtotime($doc['vigencia'])); ?></td>
                                    <td>
                                        <?php 
                                        $diff = strtotime($doc['vigencia']) - strtotime($hoy);
                                        $dias = (int)floor($diff / (60 * 60 * 24));
                                        
                                        if ($dias <= 4) {
                                            $badge_class = 'bg-danger';
                                        } elseif ($dias <= 9) {
                                            $badge_class = 'bg-warning text-dark';
                                        } else {
                                            $badge_class = 'bg-info text-light';
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo $dias; ?> días
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <!-- Tabla General de Documentos -->
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 12%;">Tipo / Subtipo</th>
                                <th style="width: 10%;">Inst. / Libro</th>
                                <th style="width: 15%;">Nombre / Razón Social</th>
                                <th style="width: 10%;">Fecha Exped.</th>
                                <th style="width: 12%;">Notaría / Ciudad</th>
                                <th style="width: 25%;">Concepto / Detalle</th>
                                <th style="width: 8%;">Vigencia</th>
                                <th style="width: 8%;">FME / RPC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documentos as $doc): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold">
                                            <?php 
                                            if ($doc['tipo'] === 'acta') echo 'Acta';
                                            elseif ($doc['tipo'] === 'poder') echo 'Poder';
                                            elseif ($doc['tipo'] === 'revocacion') echo 'Revocación';
                                            else echo htmlspecialchars(ucfirst($doc['tipo']));
                                            ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php
                                            if ($doc['subtipo'] === 'constitutiva') echo 'Constitutiva';
                                            elseif ($doc['subtipo'] === 'asamblea_ordinaria') echo 'Asamblea Ord.';
                                            elseif ($doc['subtipo'] === 'asamblea_extraordinaria') echo 'Asamblea Extra.';
                                            elseif ($doc['subtipo'] === 'poder_amplio') echo 'Poder Amplio';
                                            elseif ($doc['subtipo'] === 'poder_especifico') echo 'Poder Espec.';
                                            elseif ($doc['subtipo'] === 'poder_actas_administrativas') echo 'Poder Actas Adm.';
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="fw-bold">No. <?php echo htmlspecialchars($doc['numero_instrumento']); ?></div>
                                        <small class="text-muted">L: <?php echo htmlspecialchars($doc['libro']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($doc['empresa_nombre'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($doc['fecha_expedicion'])); ?></td>
                                    <td>
                                        <div>No. <?php echo htmlspecialchars($doc['notaria']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($doc['ciudad_notaria'] . (!empty($doc['estado_notaria']) ? ', ' . $doc['estado_notaria'] : '')); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-medium mb-1"><?php echo htmlspecialchars($doc['concepto']); ?></div>
                                        
                                        <?php if ($doc['tipo'] === 'acta'): ?>
                                            <?php if (isset($socios_map[$doc['id']])): 
                                                $socios_list = array_map(function($s) {
                                                    $nacionalidad = !empty($s['nacionalidad']) ? $s['nacionalidad'] : 'Mexicana';
                                                    $domicilio = !empty($s['domicilio_social']) ? ', ' . $s['domicilio_social'] : '';
                                                    return $s['nombre'] . ' (' . $nacionalidad . $domicilio . ', ' . $s['numero_acciones'] . ' acc., $' . number_format($s['valor_nominal'], 2) . ')';
                                                }, $socios_map[$doc['id']]);
                                            ?>
                                                <div style="font-size: 0.75rem;" class="text-info mt-1">
                                                    <strong>Socios:</strong> <?php echo htmlspecialchars(implode(', ', $socios_list)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($doc['administrador_unico']) || !empty($doc['comisario'])): ?>
                                                <div style="font-size: 0.75rem;" class="text-info mt-1">
                                                    <?php if (!empty($doc['administrador_unico'])): ?>
                                                        <strong>Adm. Único:</strong> <?php echo htmlspecialchars($doc['administrador_unico']); ?> &nbsp;&nbsp;
                                                    <?php endif; ?>
                                                    <?php if (!empty($doc['comisario'])): ?>
                                                        <strong>Comisario:</strong> <?php echo htmlspecialchars($doc['comisario']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif (isset($acreditados_map[$doc['id']])): ?>
                                            <div style="font-size: 0.75rem;" class="text-success mt-1">
                                                <strong>Acreditados:</strong> <?php echo htmlspecialchars(implode(', ', $acreditados_map[$doc['id']])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (empty($doc['vigencia'])): ?>
                                            <span class="badge bg-secondary"><i class="fa-solid fa-infinity me-1"></i> Permanente</span>
                                        <?php else: ?>
                                            <span><?php echo date('d/m/Y', strtotime($doc['vigencia'])); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                         <?php if (!empty($doc['fme']) || !empty($doc['fecha_registro_rpc'])): ?>
                                             <div>FME: <?php echo htmlspecialchars($doc['fme'] ?? '-'); ?></div>
                                             <small class="text-muted">RPC: <?php echo !empty($doc['fecha_registro_rpc']) ? date('d/m/Y', strtotime($doc['fecha_registro_rpc'])) : '-'; ?></small>
                                         <?php else: ?>
                                             <span class="text-muted">-</span>
                                         <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Pie de página del reporte (Impresión) -->
        <div class="mt-5 text-center text-muted border-top pt-3" style="font-size: 0.75rem;">
            Reporte generado automáticamente desde SISCORL de Einsur Supply S.A. de C.V.
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Disparar diálogo de impresión automáticamente al cargar la página
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
<?php endif; ?>
