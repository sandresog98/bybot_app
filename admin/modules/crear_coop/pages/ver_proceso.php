<?php
/**
 * Ver Proceso - Crear Coop
 */

require_once '../../../controllers/AuthController.php';
require_once '../../../config/paths.php';
require_once '../models/CrearCoop.php';

$authController = new AuthController();
$authController->requireModule('crear_coop.procesos');
$currentUser = $authController->getCurrentUser();

$crearCoopModel = new CrearCoop();

$procesoId = (int)($_GET['id'] ?? 0);
if ($procesoId <= 0) {
    header("Location: " . getBaseUrl() . "modules/crear_coop/pages/procesos.php");
    exit();
}

$proceso = $crearCoopModel->obtenerProceso($procesoId);
if (!$proceso) {
    header("Location: " . getBaseUrl() . "modules/crear_coop/pages/procesos.php");
    exit();
}

$anexos = $crearCoopModel->obtenerAnexos($procesoId, 'anexo_original');
$solicitudDeudor = $crearCoopModel->obtenerAnexos($procesoId, 'solicitud_vinculacion_deudor');
$solicitudCodeudor = $crearCoopModel->obtenerAnexos($procesoId, 'solicitud_vinculacion_codeudor');

// Obtener datos de IA para mostrar (validados si existen, sino originales)
$datosIA = $crearCoopModel->obtenerDatosParaMostrar($procesoId);
if ($datosIA) {
    // Combinar datos de IA con el proceso para compatibilidad
    $proceso = array_merge($proceso, $datosIA);
}

$estados = [
    'creado' => ['label' => 'Creado', 'class' => 'bg-secondary'],
    'analizando_con_ia' => ['label' => 'Analizando con IA', 'class' => 'bg-info'],
    'analizado_con_ia' => ['label' => 'Analizado con IA', 'class' => 'bg-primary'],
    'informacion_ia_validada' => ['label' => 'Información IA Validada', 'class' => 'bg-success'],
    'archivos_extraidos' => ['label' => 'Archivos Extraídos', 'class' => 'bg-warning text-dark'],
    'llenar_pagare' => ['label' => 'Llenar Pagaré', 'class' => 'bg-success'],
    'error_analisis' => ['label' => 'Error Análisis', 'class' => 'bg-danger']
];

// Obtener datos originales de IA para mostrar en la validación
$datosIACompletos = $crearCoopModel->obtenerDatosIA($procesoId);
$datosOriginales = null;
$datosValidados = null;
if ($datosIACompletos) {
    $datosOriginales = $datosIACompletos['datos_originales'];
    $datosValidados = $datosIACompletos['datos_validados'];
}

// Determinar si se puede editar (si está analizado_con_ia o informacion_ia_validada)
$puedeEditar = (in_array($proceso['estado'], ['analizado_con_ia', 'informacion_ia_validada']) && $datosIACompletos);

$pageTitle = 'Ver Proceso - Crear Coop';
$currentPage = 'crear_coop_procesos';
include '../../../views/layouts/header.php';

// Función helper para obtener valor validado de forma segura
function obtenerValorValidado($arrayValidado, $campo) {
    if ($arrayValidado === null || !is_array($arrayValidado) || !isset($arrayValidado[$campo])) {
        return null;
    }
    return $arrayValidado[$campo];
}

// Función helper para normalizar y comparar valores
function normalizarValor($valor, $tipo) {
    if ($valor === null || $valor === '' || $valor === 'null') {
        return null;
    }
    
    if ($tipo === 'decimal' || $tipo === 'number') {
        // Convertir a float y redondear a 2 decimales para comparación
        $float = (float)$valor;
        return $float == 0 ? null : round($float, 2);
    }
    
    if ($tipo === 'date') {
        return $valor;
    }
    
    return trim((string)$valor);
}

// Función helper para comparar si dos valores son iguales
function valoresIguales($valor1, $valor2, $tipo) {
    $norm1 = normalizarValor($valor1, $tipo);
    $norm2 = normalizarValor($valor2, $tipo);
    
    // Ambos null o vacíos = iguales
    if ($norm1 === null && $norm2 === null) {
        return true;
    }
    
    // Uno null y otro no = diferentes
    if ($norm1 === null || $norm2 === null) {
        return false;
    }
    
    // Comparar normalizados
    if ($tipo === 'decimal' || $tipo === 'number') {
        return abs($norm1 - $norm2) < 0.01; // Tolerancia para floats
    }
    
    return $norm1 === $norm2;
}

// Función helper para renderizar campo de validación
function renderCampoValidacion($label, $campo, $valorOriginal, $valorValidado, $tipo = 'text', $puedeEditar = false, $hayDatosValidados = false) {
    $valorMostrar = $valorValidado ?? $valorOriginal;
    $valorOriginalMostrar = $valorOriginal ?? null;
    
    // Determinar si fue editado
    // Solo considerar editado si:
    // 1. Hay datos validados (se ha guardado algo)
    // 2. El campo existe en los datos validados (fue enviado en algún formulario)
    // 3. El valor validado es diferente del original
    $fueEditado = false;
    if ($hayDatosValidados && $valorValidado !== null) {
        // Si $valorValidado es null, significa que el campo no está en datos_validados
        // (no fue editado nunca), así que no debe mostrar "Editado"
        // Solo si $valorValidado tiene un valor (incluso si es string vacío o null explícito)
        // y es diferente del original, entonces fue editado
        $fueEditado = !valoresIguales($valorOriginal, $valorValidado, $tipo);
    }
    
    $html = '<div class="mb-2 pb-2 border-bottom">';
    $html .= '<div class="d-flex align-items-start">';
    $html .= '<strong class="text-muted me-2" style="min-width: 180px;">' . htmlspecialchars($label) . ':</strong>';
    $html .= '<div class="flex-grow-1">';
    
    if ($puedeEditar) {
        // Modo edición: mostrar original y campo editable en formato simple
        // Mostrar valor original de IA arriba
        if ($valorOriginalMostrar !== null && $valorOriginalMostrar !== '') {
            $html .= '<div class="mb-2">';
            $html .= '<small class="text-muted d-block">';
            $html .= '<i class="fas fa-robot me-1"></i>Valor IA: ';
            if ($tipo === 'date') {
                $html .= date('d/m/Y', strtotime($valorOriginalMostrar));
            } elseif ($tipo === 'number' || $tipo === 'decimal') {
                $html .= number_format($valorOriginalMostrar, 2, ',', '.');
            } else {
                $html .= htmlspecialchars($valorOriginalMostrar);
            }
            $html .= '</small>';
            $html .= '</div>';
        } else {
            $html .= '<div class="mb-2">';
            $html .= '<small class="text-muted d-block fst-italic">';
            $html .= '<i class="fas fa-robot me-1"></i>Valor IA: No encontrado';
            $html .= '</small>';
            $html .= '</div>';
        }
        
        // Campo editable
        $inputValue = $valorMostrar !== null && $valorMostrar !== '' ? $valorMostrar : '';
        if ($tipo === 'date') {
            $dateValue = '';
            if ($inputValue) {
                try {
                    $dateValue = date('Y-m-d', strtotime($inputValue));
                } catch (Exception $e) {
                    $dateValue = '';
                }
            }
            $html .= '<input type="date" name="' . htmlspecialchars($campo) . '" class="form-control form-control-sm" value="' . htmlspecialchars($dateValue) . '">';
        } elseif ($tipo === 'decimal' || $tipo === 'number') {
            $numValue = $inputValue !== '' && $inputValue !== null ? (float)$inputValue : '';
            $html .= '<input type="number" step="0.01" name="' . htmlspecialchars($campo) . '" class="form-control form-control-sm" value="' . htmlspecialchars($numValue) . '">';
        } elseif ($tipo === 'textarea') {
            $html .= '<textarea name="' . htmlspecialchars($campo) . '" class="form-control form-control-sm" rows="2">' . htmlspecialchars($inputValue) . '</textarea>';
        } else {
            $html .= '<input type="text" name="' . htmlspecialchars($campo) . '" class="form-control form-control-sm" value="' . htmlspecialchars($inputValue) . '">';
        }
    } else {
        // Modo solo lectura - formato simple: campo: valor
        if ($valorMostrar !== null && $valorMostrar !== '') {
            if ($tipo === 'date') {
                try {
                    $html .= date('d/m/Y', strtotime($valorMostrar));
                } catch (Exception $e) {
                    $html .= htmlspecialchars($valorMostrar);
                }
            } elseif ($tipo === 'decimal' || $tipo === 'number') {
                $html .= number_format((float)$valorMostrar, 2, ',', '.');
            } else {
                $html .= htmlspecialchars($valorMostrar);
            }
            
            // Mostrar si fue editado con el valor original de IA
            if ($fueEditado) {
                $html .= ' <small class="text-success ms-2">';
                $html .= '<i class="fas fa-check-circle me-1"></i>Editado';
                
                // Mostrar valor original de IA si existe
                if ($valorOriginalMostrar !== null && $valorOriginalMostrar !== '') {
                    $html .= ' <span class="text-muted">(IA: ';
                    if ($tipo === 'date') {
                        try {
                            $html .= date('d/m/Y', strtotime($valorOriginalMostrar));
                        } catch (Exception $e) {
                            $html .= htmlspecialchars($valorOriginalMostrar);
                        }
                    } elseif ($tipo === 'decimal' || $tipo === 'number') {
                        $html .= number_format((float)$valorOriginalMostrar, 2, ',', '.');
                    } else {
                        $html .= htmlspecialchars($valorOriginalMostrar);
                    }
                    $html .= ')</span>';
                } else {
                    $html .= ' <span class="text-muted">(IA: No encontrado)</span>';
                }
                $html .= '</small>';
            }
        } else {
            $html .= '<span class="text-muted fst-italic">No encontrado</span>';
        }
    }
    
    $html .= '</div>'; // flex-grow-1
    $html .= '</div>'; // d-flex
    $html .= '</div>'; // mb-2 pb-2 border-bottom
    return $html;
}
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../../views/layouts/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-eye me-2" style="color: var(--primary-color);"></i>
                        Proceso: <?php echo htmlspecialchars($proceso['codigo']); ?>
                    </h1>
                    <p class="text-muted mb-0">Detalles del proceso</p>
                </div>
                <div>
                    <?php if ($proceso['estado'] === 'analizado_con_ia'): ?>
                    <button type="button" class="btn btn-success me-2" id="btnMarcarValidado">
                        <i class="fas fa-check-circle me-2"></i>Marcar como Información IA Validada
                    </button>
                    <?php endif; ?>
                    <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/pages/procesos.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                </div>
            </div>
            
            <!-- Información General -->
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información General</h6>
                </div>
                <div class="card-body py-2">
                    <div class="row g-2">
                        <div class="col-md-2 col-lg-1">
                            <small class="text-muted d-block">Código</small>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($proceso['codigo']); ?></span>
                        </div>
                        <div class="col-md-2 col-lg-1">
                            <small class="text-muted d-block">Estado</small>
                            <span class="badge <?php echo $estados[$proceso['estado']]['class'] ?? 'bg-secondary'; ?>">
                                <?php echo $estados[$proceso['estado']]['label'] ?? ucfirst($proceso['estado']); ?>
                            </span>
                        </div>
                        <div class="col-md-2 col-lg-2">
                            <small class="text-muted d-block">Creado Por</small>
                            <span><?php echo htmlspecialchars($proceso['creado_por_nombre'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="col-md-2 col-lg-2">
                            <small class="text-muted d-block">Fecha Creación</small>
                            <span><?php echo date('d/m/Y H:i', strtotime($proceso['fecha_creacion'])); ?></span>
                        </div>
                        <?php if ($proceso['fecha_actualizacion']): ?>
                        <div class="col-md-2 col-lg-2">
                            <small class="text-muted d-block">Última Actualización</small>
                            <span><?php echo date('d/m/Y H:i', strtotime($proceso['fecha_actualizacion'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($proceso['fecha_analisis_ia']) && $proceso['fecha_analisis_ia']): ?>
                        <div class="col-md-2 col-lg-2">
                            <small class="text-muted d-block">Fecha Análisis IA</small>
                            <span><?php echo date('d/m/Y H:i', strtotime($proceso['fecha_analisis_ia'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($proceso['datos_ia']['metadata'])): ?>
                        <div class="col-md-12 col-lg-3">
                            <small class="text-muted d-block">Tokens Análisis</small>
                            <small>
                                <?php 
                                $metadata = $proceso['datos_ia']['metadata'];
                                echo "E: " . number_format($metadata['tokens_entrada'] ?? 0, 0, ',', '.') . " | ";
                                echo "S: " . number_format($metadata['tokens_salida'] ?? 0, 0, ',', '.') . " | ";
                                echo "T: " . number_format($metadata['tokens_total'] ?? 0, 0, ',', '.');
                                ?>
                            </small>
                            <?php if (isset($metadata['tokens_identificacion_vinculacion'])): ?>
                            <br>
                            <small class="text-muted d-block mt-1">Tokens Identificación Vinculación</small>
                            <small>
                                <?php 
                                $tokensVinculacion = $metadata['tokens_identificacion_vinculacion'];
                                echo "E: " . number_format($tokensVinculacion['tokens_entrada'] ?? 0, 0, ',', '.') . " | ";
                                echo "S: " . number_format($tokensVinculacion['tokens_salida'] ?? 0, 0, ',', '.') . " | ";
                                echo "T: " . number_format($tokensVinculacion['tokens_total'] ?? 0, 0, ',', '.');
                                ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Vista de Solo Lectura -->
            <?php if (isset($proceso['fecha_analisis_ia']) && $proceso['fecha_analisis_ia'] && $datosOriginales): ?>
            <div id="vistaSoloLectura">
                <!-- Archivos y Datos del Estado de Cuenta lado a lado -->
                <div class="row g-3 mb-3">
                    <!-- Archivos -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header py-2">
                                <h6 class="mb-0"><i class="fas fa-file me-2"></i>Archivos</h6>
                            </div>
                            <div class="card-body py-3">
                                <?php if ($proceso['archivo_pagare_original']): ?>
                                <div class="mb-3 pb-2 border-bottom">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <strong class="text-muted">Pagaré Original</strong>
                                        <div>
                                            <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=pagare&ver=1" 
                                               target="_blank"
                                               class="btn btn-sm btn-outline-info me-1" 
                                               title="Ver archivo">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=pagare" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Descargar archivo">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($proceso['archivo_pagare_llenado']): ?>
                                <div class="mb-3 pb-2 border-top pt-3 mt-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <strong class="text-success">
                                                <i class="fas fa-file-signature me-2"></i>Pagaré Llenado
                                            </strong>
                                            <small class="text-muted d-block">Completado automáticamente por IA</small>
                                        </div>
                                        <div>
                                            <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=pagare_llenado&ver=1" 
                                               target="_blank"
                                               class="btn btn-sm btn-outline-info me-1" 
                                               title="Ver archivo">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=pagare_llenado" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Descargar archivo">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($proceso['archivo_estado_cuenta']): ?>
                                <div class="mb-3 pb-2 border-bottom">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <strong class="text-muted">Estado de Cuenta</strong>
                                        <div>
                                            <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=estado_cuenta&ver=1" 
                                               target="_blank"
                                               class="btn btn-sm btn-outline-info me-1" 
                                               title="Ver archivo">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=estado_cuenta" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Descargar archivo">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($anexos)): ?>
                                    <?php foreach ($anexos as $index => $anexo): ?>
                                    <div class="mb-3 pb-2 <?php echo $index < count($anexos) - 1 ? 'border-bottom' : ''; ?>">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <strong class="text-muted">Anexo<?php echo count($anexos) > 1 ? ' ' . ($index + 1) : ''; ?></strong>
                                            <div>
                                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $anexo['id']; ?>&ver=1" 
                                                   target="_blank"
                                                   class="btn btn-sm btn-outline-info me-1" 
                                                   title="Ver archivo">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $anexo['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   title="Descargar archivo">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <p class="text-muted mb-0">No hay anexos registrados para este proceso.</p>
                                <?php endif; ?>
                                
                                <?php if (!empty($solicitudDeudor)): ?>
                                    <?php foreach ($solicitudDeudor as $solicitud): ?>
                                    <div class="mb-3 pb-2 border-top pt-3 mt-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <strong class="text-success">
                                                    <i class="fas fa-file-contract me-2"></i>Solicitud de Vinculación - Deudor
                                                </strong>
                                                <small class="text-muted d-block">Extraída automáticamente por IA</small>
                                            </div>
                                            <div>
                                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $solicitud['id']; ?>&ver=1" 
                                                   target="_blank"
                                                   class="btn btn-sm btn-outline-info me-1" 
                                                   title="Ver archivo">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $solicitud['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   title="Descargar archivo">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <?php if (!empty($solicitudCodeudor)): ?>
                                    <?php foreach ($solicitudCodeudor as $solicitud): ?>
                                    <div class="mb-3 pb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <strong class="text-success">
                                                    <i class="fas fa-file-contract me-2"></i>Solicitud de Vinculación - Codeudor
                                                </strong>
                                                <small class="text-muted d-block">Extraída automáticamente por IA</small>
                                            </div>
                                            <div>
                                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $solicitud['id']; ?>&ver=1" 
                                                   target="_blank"
                                                   class="btn btn-sm btn-outline-info me-1" 
                                                   title="Ver archivo">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $solicitud['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   title="Descargar archivo">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Datos del Estado de Cuenta -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Datos del Estado de Cuenta</h6>
                                <?php if ($puedeEditar): ?>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalEstadoCuenta">
                                    <i class="fas fa-edit me-1"></i>Editar
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body py-3">
                                <?php
                                $ecOriginal = $datosOriginales['estado_cuenta'] ?? [];
                                $ecValidado = ($datosValidados && isset($datosValidados['estado_cuenta'])) ? $datosValidados['estado_cuenta'] : null;
                                
                                // Solo pasar el valor validado si realmente existe en el array (fue editado)
                                echo renderCampoValidacion('Fecha Causación', 'fecha_causacion', $ecOriginal['fecha_causacion'] ?? null, (isset($ecValidado) && array_key_exists('fecha_causacion', $ecValidado)) ? $ecValidado['fecha_causacion'] : null, 'date', false, $datosValidados !== null);
                                echo renderCampoValidacion('Saldo Capital', 'saldo_capital', $ecOriginal['saldo_capital'] ?? null, (isset($ecValidado) && array_key_exists('saldo_capital', $ecValidado)) ? $ecValidado['saldo_capital'] : null, 'decimal', false, $datosValidados !== null);
                                echo renderCampoValidacion('Saldo Interés', 'saldo_interes', $ecOriginal['saldo_interes'] ?? null, (isset($ecValidado) && array_key_exists('saldo_interes', $ecValidado)) ? $ecValidado['saldo_interes'] : null, 'decimal', false, $datosValidados !== null);
                                echo renderCampoValidacion('Saldo Mora', 'saldo_mora', $ecOriginal['saldo_mora'] ?? null, (isset($ecValidado) && array_key_exists('saldo_mora', $ecValidado)) ? $ecValidado['saldo_mora'] : null, 'decimal', false, $datosValidados !== null);
                                echo renderCampoValidacion('Tasa Interés Efectiva Anual (TEA)', 'tasa_interes_efectiva_anual', $ecOriginal['tasa_interes_efectiva_anual'] ?? null, (isset($ecValidado) && array_key_exists('tasa_interes_efectiva_anual', $ecValidado)) ? $ecValidado['tasa_interes_efectiva_anual'] : null, 'decimal', false, $datosValidados !== null);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Datos del Deudor y Codeudor lado a lado -->
                <div class="row g-3 mb-3">
                    <!-- Datos del Deudor -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Datos del Deudor/Solicitante</h6>
                                <?php if ($puedeEditar): ?>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalDeudor">
                                    <i class="fas fa-edit me-1"></i>Editar
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body py-3">
                                <?php
                                $deudorOriginal = $datosOriginales['deudor'] ?? [];
                                $deudorValidado = ($datosValidados && isset($datosValidados['deudor'])) ? $datosValidados['deudor'] : null;
                                
                                // Solo pasar el valor validado si realmente existe en el array (fue editado)
                                $getDeudorValidado = function($campo) use ($deudorValidado) {
                                    return (isset($deudorValidado) && array_key_exists($campo, $deudorValidado)) ? $deudorValidado[$campo] : null;
                                };
                                
                                echo renderCampoValidacion('Tipo Identificación', 'deudor_tipo_identificacion', $deudorOriginal['tipo_identificacion'] ?? null, $getDeudorValidado('tipo_identificacion'), 'text', false, $datosValidados !== null);
                                echo renderCampoValidacion('Número Identificación', 'deudor_numero_identificacion', $deudorOriginal['numero_identificacion'] ?? null, $getDeudorValidado('numero_identificacion'), 'text', false, $datosValidados !== null);
                                echo renderCampoValidacion('Nombres', 'deudor_nombres', $deudorOriginal['nombres'] ?? null, $getDeudorValidado('nombres'), 'text', false, $datosValidados !== null);
                                echo renderCampoValidacion('Apellidos', 'deudor_apellidos', $deudorOriginal['apellidos'] ?? null, $getDeudorValidado('apellidos'), 'text', false, $datosValidados !== null);
                                echo renderCampoValidacion('Fecha Expedición Cédula', 'deudor_fecha_expedicion_cedula', $deudorOriginal['fecha_expedicion_cedula'] ?? null, $getDeudorValidado('fecha_expedicion_cedula'), 'date', false, $datosValidados !== null);
                                echo renderCampoValidacion('Fecha Nacimiento', 'deudor_fecha_nacimiento', $deudorOriginal['fecha_nacimiento'] ?? null, $getDeudorValidado('fecha_nacimiento'), 'date', false, $datosValidados !== null);
                                echo renderCampoValidacion('Teléfono/Celular', 'deudor_telefono', $deudorOriginal['telefono'] ?? null, $getDeudorValidado('telefono'), 'text', false, $datosValidados !== null);
                                echo renderCampoValidacion('Dirección', 'deudor_direccion', $deudorOriginal['direccion'] ?? null, $getDeudorValidado('direccion'), 'textarea', false, $datosValidados !== null);
                                echo renderCampoValidacion('Correo Electrónico', 'deudor_correo', $deudorOriginal['correo'] ?? null, $getDeudorValidado('correo'), 'text', false, $datosValidados !== null);
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Datos del Codeudor -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fas fa-user-friends me-2"></i>Datos del Codeudor</h6>
                                <?php if ($puedeEditar): ?>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCodeudor">
                                    <i class="fas fa-edit me-1"></i>Editar
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body py-3">
                                <?php
                                $codeudorOriginal = $datosOriginales['codeudor'] ?? [];
                                $codeudorValidado = ($datosValidados && isset($datosValidados['codeudor'])) ? $datosValidados['codeudor'] : null;
                                
                                // Solo pasar el valor validado si realmente existe en el array (fue editado)
                                $getCodeudorValidado = function($campo) use ($codeudorValidado) {
                                    return (isset($codeudorValidado) && array_key_exists($campo, $codeudorValidado)) ? $codeudorValidado[$campo] : null;
                                };
                                
                                echo renderCampoValidacion('Tipo Identificación', 'codeudor_tipo_identificacion', $codeudorOriginal['tipo_identificacion'] ?? null, $getCodeudorValidado('tipo_identificacion'), 'text', false, $datosValidados !== null);
                                echo renderCampoValidacion('Número Identificación', 'codeudor_numero_identificacion', $codeudorOriginal['numero_identificacion'] ?? null, $getCodeudorValidado('numero_identificacion'), 'text', false, $datosValidados !== null);
                                echo renderCampoValidacion('Nombres', 'codeudor_nombres', $codeudorOriginal['nombres'] ?? null, $getCodeudorValidado('nombres'), 'text', false, $datosValidados !== null);
                                echo renderCampoValidacion('Apellidos', 'codeudor_apellidos', $codeudorOriginal['apellidos'] ?? null, $getCodeudorValidado('apellidos'), 'text', false, $datosValidados !== null);
                                echo renderCampoValidacion('Fecha Expedición Cédula', 'codeudor_fecha_expedicion_cedula', $codeudorOriginal['fecha_expedicion_cedula'] ?? null, $getCodeudorValidado('fecha_expedicion_cedula'), 'date', false, $datosValidados !== null);
                                echo renderCampoValidacion('Fecha Nacimiento', 'codeudor_fecha_nacimiento', $codeudorOriginal['fecha_nacimiento'] ?? null, $getCodeudorValidado('fecha_nacimiento'), 'date', false, $datosValidados !== null);
                                echo renderCampoValidacion('Teléfono/Celular', 'codeudor_telefono', $codeudorOriginal['telefono'] ?? null, $getCodeudorValidado('telefono'), 'text', false, $datosValidados !== null);
                                echo renderCampoValidacion('Dirección', 'codeudor_direccion', $codeudorOriginal['direccion'] ?? null, $getCodeudorValidado('direccion'), 'textarea', false, $datosValidados !== null);
                                echo renderCampoValidacion('Correo Electrónico', 'codeudor_correo', $codeudorOriginal['correo'] ?? null, $getCodeudorValidado('correo'), 'text', false, $datosValidados !== null);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Modales para Edición -->
            <?php if (isset($proceso['fecha_analisis_ia']) && $proceso['fecha_analisis_ia'] && $datosOriginales && $puedeEditar): ?>
            
            <!-- Modal: Estado de Cuenta -->
            <div class="modal fade" id="modalEstadoCuenta" tabindex="-1" aria-labelledby="modalEstadoCuentaLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalEstadoCuentaLabel">
                                <i class="fas fa-file-invoice-dollar me-2"></i>Editar Datos del Estado de Cuenta
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formEstadoCuenta">
                            <input type="hidden" name="proceso_id" value="<?php echo $procesoId; ?>">
                            <div class="modal-body">
                                <?php
                                $ecOriginal = $datosOriginales['estado_cuenta'] ?? [];
                                $ecValidado = $datosValidados['estado_cuenta'] ?? null;
                                
                                echo renderCampoValidacion('Fecha Causación', 'fecha_causacion', $ecOriginal['fecha_causacion'] ?? null, $ecValidado['fecha_causacion'] ?? null, 'date', true, $datosValidados !== null);
                                echo renderCampoValidacion('Saldo Capital', 'saldo_capital', $ecOriginal['saldo_capital'] ?? null, $ecValidado['saldo_capital'] ?? null, 'decimal', true, $datosValidados !== null);
                                echo renderCampoValidacion('Saldo Interés', 'saldo_interes', $ecOriginal['saldo_interes'] ?? null, $ecValidado['saldo_interes'] ?? null, 'decimal', true, $datosValidados !== null);
                                echo renderCampoValidacion('Saldo Mora', 'saldo_mora', $ecOriginal['saldo_mora'] ?? null, $ecValidado['saldo_mora'] ?? null, 'decimal', true, $datosValidados !== null);
                                echo renderCampoValidacion('Tasa Interés Efectiva Anual (TEA)', 'tasa_interes_efectiva_anual', $ecOriginal['tasa_interes_efectiva_anual'] ?? null, $ecValidado['tasa_interes_efectiva_anual'] ?? null, 'decimal', true, $datosValidados !== null);
                                ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Modal: Deudor -->
            <div class="modal fade" id="modalDeudor" tabindex="-1" aria-labelledby="modalDeudorLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalDeudorLabel">
                                <i class="fas fa-user me-2"></i>Editar Datos del Deudor/Solicitante
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formDeudor">
                            <input type="hidden" name="proceso_id" value="<?php echo $procesoId; ?>">
                            <div class="modal-body">
                                <?php
                                $deudorOriginal = $datosOriginales['deudor'] ?? [];
                                $deudorValidado = ($datosValidados && isset($datosValidados['deudor'])) ? $datosValidados['deudor'] : null;
                                
                                echo renderCampoValidacion('Tipo Identificación', 'deudor_tipo_identificacion', $deudorOriginal['tipo_identificacion'] ?? null, obtenerValorValidado($deudorValidado, 'tipo_identificacion'), 'text', true, $datosValidados !== null);
                                echo renderCampoValidacion('Número Identificación', 'deudor_numero_identificacion', $deudorOriginal['numero_identificacion'] ?? null, obtenerValorValidado($deudorValidado, 'numero_identificacion'), 'text', true, $datosValidados !== null);
                                echo renderCampoValidacion('Nombres', 'deudor_nombres', $deudorOriginal['nombres'] ?? null, obtenerValorValidado($deudorValidado, 'nombres'), 'text', true, $datosValidados !== null);
                                echo renderCampoValidacion('Apellidos', 'deudor_apellidos', $deudorOriginal['apellidos'] ?? null, obtenerValorValidado($deudorValidado, 'apellidos'), 'text', true, $datosValidados !== null);
                                echo renderCampoValidacion('Fecha Expedición Cédula', 'deudor_fecha_expedicion_cedula', $deudorOriginal['fecha_expedicion_cedula'] ?? null, obtenerValorValidado($deudorValidado, 'fecha_expedicion_cedula'), 'date', true, $datosValidados !== null);
                                echo renderCampoValidacion('Fecha Nacimiento', 'deudor_fecha_nacimiento', $deudorOriginal['fecha_nacimiento'] ?? null, obtenerValorValidado($deudorValidado, 'fecha_nacimiento'), 'date', true, $datosValidados !== null);
                                echo renderCampoValidacion('Teléfono/Celular', 'deudor_telefono', $deudorOriginal['telefono'] ?? null, obtenerValorValidado($deudorValidado, 'telefono'), 'text', true, $datosValidados !== null);
                                echo renderCampoValidacion('Dirección', 'deudor_direccion', $deudorOriginal['direccion'] ?? null, obtenerValorValidado($deudorValidado, 'direccion'), 'textarea', true, $datosValidados !== null);
                                echo renderCampoValidacion('Correo Electrónico', 'deudor_correo', $deudorOriginal['correo'] ?? null, obtenerValorValidado($deudorValidado, 'correo'), 'text', true, $datosValidados !== null);
                                ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Modal: Codeudor -->
            <div class="modal fade" id="modalCodeudor" tabindex="-1" aria-labelledby="modalCodeudorLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalCodeudorLabel">
                                <i class="fas fa-user-friends me-2"></i>Editar Datos del Codeudor
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formCodeudor">
                            <input type="hidden" name="proceso_id" value="<?php echo $procesoId; ?>">
                            <div class="modal-body">
                                <?php
                                $codeudorOriginal = $datosOriginales['codeudor'] ?? [];
                                $codeudorValidado = ($datosValidados && isset($datosValidados['codeudor'])) ? $datosValidados['codeudor'] : null;
                                
                                echo renderCampoValidacion('Tipo Identificación', 'codeudor_tipo_identificacion', $codeudorOriginal['tipo_identificacion'] ?? null, obtenerValorValidado($codeudorValidado, 'tipo_identificacion'), 'text', true, $datosValidados !== null);
                                echo renderCampoValidacion('Número Identificación', 'codeudor_numero_identificacion', $codeudorOriginal['numero_identificacion'] ?? null, obtenerValorValidado($codeudorValidado, 'numero_identificacion'), 'text', true, $datosValidados !== null);
                                echo renderCampoValidacion('Nombres', 'codeudor_nombres', $codeudorOriginal['nombres'] ?? null, obtenerValorValidado($codeudorValidado, 'nombres'), 'text', true, $datosValidados !== null);
                                echo renderCampoValidacion('Apellidos', 'codeudor_apellidos', $codeudorOriginal['apellidos'] ?? null, obtenerValorValidado($codeudorValidado, 'apellidos'), 'text', true, $datosValidados !== null);
                                echo renderCampoValidacion('Fecha Expedición Cédula', 'codeudor_fecha_expedicion_cedula', $codeudorOriginal['fecha_expedicion_cedula'] ?? null, obtenerValorValidado($codeudorValidado, 'fecha_expedicion_cedula'), 'date', true, $datosValidados !== null);
                                echo renderCampoValidacion('Fecha Nacimiento', 'codeudor_fecha_nacimiento', $codeudorOriginal['fecha_nacimiento'] ?? null, obtenerValorValidado($codeudorValidado, 'fecha_nacimiento'), 'date', true, $datosValidados !== null);
                                echo renderCampoValidacion('Teléfono/Celular', 'codeudor_telefono', $codeudorOriginal['telefono'] ?? null, obtenerValorValidado($codeudorValidado, 'telefono'), 'text', true, $datosValidados !== null);
                                echo renderCampoValidacion('Dirección', 'codeudor_direccion', $codeudorOriginal['direccion'] ?? null, obtenerValorValidado($codeudorValidado, 'direccion'), 'textarea', true, $datosValidados !== null);
                                echo renderCampoValidacion('Correo Electrónico', 'codeudor_correo', $codeudorOriginal['correo'] ?? null, obtenerValorValidado($codeudorValidado, 'correo'), 'text', true, $datosValidados !== null);
                                ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
            
            <!-- Archivos (si no hay datos de IA) -->
            <?php if (!isset($proceso['fecha_analisis_ia']) || !$proceso['fecha_analisis_ia'] || !$datosOriginales): ?>
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="fas fa-file me-2"></i>Archivos</h6>
                </div>
                <div class="card-body py-3">
                    <?php if ($proceso['archivo_pagare_original']): ?>
                    <div class="mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center justify-content-between">
                            <strong class="text-muted">Pagaré Original</strong>
                            <div>
                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=pagare&ver=1" 
                                   target="_blank"
                                   class="btn btn-sm btn-outline-info me-1" 
                                   title="Ver archivo">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=pagare" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="Descargar archivo">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($proceso['archivo_estado_cuenta']): ?>
                    <div class="mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center justify-content-between">
                            <strong class="text-muted">Estado de Cuenta</strong>
                            <div>
                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=estado_cuenta&ver=1" 
                                   target="_blank"
                                   class="btn btn-sm btn-outline-info me-1" 
                                   title="Ver archivo">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=estado_cuenta" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="Descargar archivo">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($anexos)): ?>
                        <?php foreach ($anexos as $index => $anexo): ?>
                        <div class="mb-3 pb-2 <?php echo $index < count($anexos) - 1 ? 'border-bottom' : ''; ?>">
                            <div class="d-flex align-items-center justify-content-between">
                                <strong class="text-muted">Anexo<?php echo count($anexos) > 1 ? ' ' . ($index + 1) : ''; ?></strong>
                                <div>
                                    <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $anexo['id']; ?>&ver=1" 
                                       target="_blank"
                                       class="btn btn-sm btn-outline-info me-1" 
                                       title="Ver archivo">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $anexo['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Descargar archivo">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <p class="text-muted mb-0">No hay anexos registrados para este proceso.</p>
                    <?php endif; ?>
                    
                    <?php if (!empty($solicitudDeudor)): ?>
                        <?php foreach ($solicitudDeudor as $solicitud): ?>
                        <div class="mb-3 pb-2 border-top pt-3 mt-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <strong class="text-success">
                                        <i class="fas fa-file-contract me-2"></i>Solicitud de Vinculación - Deudor
                                    </strong>
                                    <small class="text-muted d-block">Extraída automáticamente por IA</small>
                                </div>
                                <div>
                                    <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $solicitud['id']; ?>&ver=1" 
                                       target="_blank"
                                       class="btn btn-sm btn-outline-info me-1" 
                                       title="Ver archivo">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $solicitud['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Descargar archivo">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($solicitudCodeudor)): ?>
                        <?php foreach ($solicitudCodeudor as $solicitud): ?>
                        <div class="mb-3 pb-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <strong class="text-success">
                                        <i class="fas fa-file-contract me-2"></i>Solicitud de Vinculación - Codeudor
                                    </strong>
                                    <small class="text-muted d-block">Extraída automáticamente por IA</small>
                                </div>
                                <div>
                                    <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $solicitud['id']; ?>&ver=1" 
                                       target="_blank"
                                       class="btn btn-sm btn-outline-info me-1" 
                                       title="Ver archivo">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/api/descargar_archivo.php?proceso_id=<?php echo $procesoId; ?>&tipo=anexo&id=<?php echo $solicitud['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Descargar archivo">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
// Función para guardar datos de un formulario modal
async function guardarDatosModal(formId, procesoId) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const formData = new FormData(form);
    const data = {};
    
    // Recopilar todos los campos del formulario
    formData.forEach((value, key) => {
        if (key !== 'proceso_id') {
            data[key] = value || null;
        }
    });
    
    try {
        const response = await fetch('<?php echo getBaseUrl(); ?>modules/crear_coop/api/validar_datos_ia.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                proceso_id: procesoId,
                datos: data,
                accion: 'guardar' // Solo guardar, no cambiar estado
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Cambios guardados exitosamente.');
            // Cerrar el modal
            const modal = bootstrap.Modal.getInstance(form.closest('.modal'));
            if (modal) {
                modal.hide();
            }
            // Recargar la página para ver los cambios
            window.location.reload();
        } else {
            alert('❌ Error al guardar los cambios: ' + (result.message || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error al enviar los cambios. Por favor, intente nuevamente.');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Formulario Estado de Cuenta
    const formEstadoCuenta = document.getElementById('formEstadoCuenta');
    if (formEstadoCuenta) {
        formEstadoCuenta.addEventListener('submit', async function(e) {
            e.preventDefault();
            const procesoId = formEstadoCuenta.querySelector('input[name="proceso_id"]').value;
            await guardarDatosModal('formEstadoCuenta', procesoId);
        });
    }
    
    // Formulario Deudor
    const formDeudor = document.getElementById('formDeudor');
    if (formDeudor) {
        formDeudor.addEventListener('submit', async function(e) {
            e.preventDefault();
            const procesoId = formDeudor.querySelector('input[name="proceso_id"]').value;
            await guardarDatosModal('formDeudor', procesoId);
        });
    }
    
    // Formulario Codeudor
    const formCodeudor = document.getElementById('formCodeudor');
    if (formCodeudor) {
        formCodeudor.addEventListener('submit', async function(e) {
            e.preventDefault();
            const procesoId = formCodeudor.querySelector('input[name="proceso_id"]').value;
            await guardarDatosModal('formCodeudor', procesoId);
        });
    }
    
    // Botón Marcar como Validado (cambia el estado)
    const btnMarcarValidado = document.getElementById('btnMarcarValidado');
    if (btnMarcarValidado) {
        btnMarcarValidado.addEventListener('click', async function() {
            if (!confirm('¿Está seguro de marcar este proceso como "Información IA Validada"?\n\nEsto cambiará el estado del proceso.')) {
                return;
            }
            
            // Obtener proceso_id del primer input hidden que encontremos
            const procesoIdInput = document.querySelector('input[name="proceso_id"]');
            const procesoId = procesoIdInput ? procesoIdInput.value : null;
            
            if (!procesoId) {
                alert('❌ No se pudo obtener el ID del proceso.');
                return;
            }
            
            try {
                const response = await fetch('<?php echo getBaseUrl(); ?>modules/crear_coop/api/validar_datos_ia.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        proceso_id: procesoId,
                        accion: 'marcar_validado' // Solo cambiar estado
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Proceso marcado como "Información IA Validada" exitosamente.');
                    window.location.reload();
                } else {
                    alert('❌ Error al cambiar el estado: ' + (result.message || 'Error desconocido'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al cambiar el estado. Por favor, intente nuevamente.');
            }
        });
    }
});
</script>

<?php include '../../../views/layouts/footer.php'; ?>

