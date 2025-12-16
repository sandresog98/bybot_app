<?php
/**
 * Modelo CrearCoop - ByBot App
 */

require_once __DIR__ . '/../../../../config/database.php';

class CrearCoop {
    private $conn;
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    /**
     * Generar código único para proceso
     */
    private function generarCodigo() {
        $prefix = 'COOP';
        $year = date('Y');
        $month = date('m');
        
        // Contar procesos del mes
        try {
            $stmt = $this->conn->query("
                SELECT COUNT(*) as total 
                FROM crear_coop_procesos 
                WHERE YEAR(fecha_creacion) = $year 
                AND MONTH(fecha_creacion) = $month
            ");
            $result = $stmt->fetch();
            $numero = ($result['total'] ?? 0) + 1;
        } catch (Exception $e) {
            $numero = 1;
        }
        
        return $prefix . $year . $month . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Crear nuevo proceso
     */
    public function crearProceso($data) {
        try {
            $codigo = $this->generarCodigo();
            
            $stmt = $this->conn->prepare("
                INSERT INTO crear_coop_procesos (
                    codigo, estado, archivo_pagare_original, archivo_estado_cuenta, 
                    archivo_anexos_original, creado_por
                ) VALUES (?, 'creado', ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $codigo,
                $data['archivo_pagare'] ?? null,
                $data['archivo_estado_cuenta'] ?? null,
                $data['archivo_anexos'] ?? null,
                $data['creado_por'] ?? null
            ]);
            
            $procesoId = $this->conn->lastInsertId();
            
            // Guardar TODOS los anexos en la tabla crear_coop_anexos (incluido el primero)
            if (!empty($data['archivo_anexos'])) {
                // Guardar el primer anexo
                $this->guardarAnexo($procesoId, $data['archivo_anexos'], 'anexo_original');
            }
            
            // Guardar anexos adicionales si existen
            if (!empty($data['anexos_adicionales'])) {
                foreach ($data['anexos_adicionales'] as $anexo) {
                    $this->guardarAnexo($procesoId, $anexo, 'anexo_original');
                }
            }
            
            return [
                'success' => true,
                'id' => $procesoId,
                'codigo' => $codigo
            ];
        } catch (PDOException $e) {
            error_log('CrearCoop::crearProceso error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al crear el proceso: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Guardar anexo
     * @param string $rutaArchivo Ruta relativa desde DOCUMENT_ROOT (ej: /projects/bybot_app/uploads/...)
     */
    public function guardarAnexo($procesoId, $rutaArchivo, $tipo = 'anexo_original') {
        try {
            $nombreArchivo = basename($rutaArchivo);
            
            // Convertir ruta relativa a absoluta para obtener tamaño
            $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/opt/lampp/htdocs';
            $rutaAbsoluta = $docRoot . $rutaArchivo;
            $tamanio = file_exists($rutaAbsoluta) ? filesize($rutaAbsoluta) : null;
            
            $stmt = $this->conn->prepare("
                INSERT INTO crear_coop_anexos (proceso_id, nombre_archivo, ruta_archivo, tipo, tamanio_bytes)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $procesoId,
                $nombreArchivo,
                $rutaArchivo, // Guardar ruta relativa en BD
                $tipo,
                $tamanio
            ]);
            
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log('CrearCoop::guardarAnexo error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener proceso por ID
     */
    public function obtenerProceso($procesoId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT p.*, u.nombre_completo as creado_por_nombre
                FROM crear_coop_procesos p
                LEFT JOIN control_usuarios u ON p.creado_por = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$procesoId]);
            $proceso = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($proceso) {
                // Obtener datos de IA si existen
                $datosIA = $this->obtenerDatosIA($procesoId);
                if ($datosIA) {
                    // Combinar datos de IA con el proceso
                    $proceso['datos_ia'] = $datosIA;
                }
            }
            
            return $proceso;
        } catch (PDOException $e) {
            error_log('CrearCoop::obtenerProceso error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener datos de IA de un proceso
     */
    public function obtenerDatosIA($procesoId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM crear_coop_datos_ia 
                WHERE proceso_id = ?
                ORDER BY fecha_analisis DESC
                LIMIT 1
            ");
            $stmt->execute([$procesoId]);
            $datosIA = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($datosIA) {
                // Decodificar JSON
                $datosIA['datos_originales'] = json_decode($datosIA['datos_originales'], true);
                $datosIA['datos_validados'] = $datosIA['datos_validados'] 
                    ? json_decode($datosIA['datos_validados'], true) 
                    : null;
                $datosIA['metadata'] = $datosIA['metadata'] 
                    ? json_decode($datosIA['metadata'], true) 
                    : null;
            }
            
            return $datosIA;
        } catch (PDOException $e) {
            error_log('CrearCoop::obtenerDatosIA error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener datos para mostrar (validados si existen, sino originales)
     * Mezcla datos validados con originales: si un campo está validado, usa el validado; sino usa el original
     */
    public function obtenerDatosParaMostrar($procesoId) {
        $datosIA = $this->obtenerDatosIA($procesoId);
        if (!$datosIA) {
            return null;
        }
        
        $datosOriginales = $datosIA['datos_originales'];
        $datosValidados = $datosIA['datos_validados'] ?? null;
        
        // Mezclar: usar validados si existen, sino usar originales
        // Esto permite que si solo se validó una sección, las demás sigan mostrando los originales
        $datos = $datosOriginales; // Empezar con originales
        
        if ($datosValidados) {
            // Mezclar datos validados sobre los originales
            if (isset($datosValidados['estado_cuenta'])) {
                $datos['estado_cuenta'] = array_merge($datos['estado_cuenta'] ?? [], $datosValidados['estado_cuenta']);
            }
            if (isset($datosValidados['deudor'])) {
                $datos['deudor'] = array_merge($datos['deudor'] ?? [], $datosValidados['deudor']);
            }
            if (isset($datosValidados['codeudor'])) {
                $datos['codeudor'] = array_merge($datos['codeudor'] ?? [], $datosValidados['codeudor']);
            }
        }
        
        // Aplanar estructura para compatibilidad con código existente
        $resultado = [];
        
        // Estado de cuenta
        if (isset($datos['estado_cuenta'])) {
            $ec = $datos['estado_cuenta'];
            $resultado['fecha_causacion'] = $ec['fecha_causacion'] ?? null;
            $resultado['saldo_capital'] = $ec['saldo_capital'] ?? null;
            $resultado['saldo_interes'] = $ec['saldo_interes'] ?? null;
            $resultado['saldo_mora'] = $ec['saldo_mora'] ?? null;
            $resultado['tasa_interes_efectiva_anual'] = $ec['tasa_interes_efectiva_anual'] ?? null;
        }
        
        // Deudor
        if (isset($datos['deudor'])) {
            $deudor = $datos['deudor'];
            $resultado['deudor_tipo_identificacion'] = $deudor['tipo_identificacion'] ?? null;
            $resultado['deudor_numero_identificacion'] = $deudor['numero_identificacion'] ?? null;
            $resultado['deudor_nombres'] = $deudor['nombres'] ?? null;
            $resultado['deudor_apellidos'] = $deudor['apellidos'] ?? null;
            $resultado['deudor_fecha_expedicion_cedula'] = $deudor['fecha_expedicion_cedula'] ?? null;
            $resultado['deudor_fecha_nacimiento'] = $deudor['fecha_nacimiento'] ?? null;
            $resultado['deudor_telefono'] = $deudor['telefono'] ?? null;
            $resultado['deudor_direccion'] = $deudor['direccion'] ?? null;
            $resultado['deudor_correo'] = $deudor['correo'] ?? null;
        }
        
        // Codeudor
        if (isset($datos['codeudor'])) {
            $codeudor = $datos['codeudor'];
            $resultado['codeudor_tipo_identificacion'] = $codeudor['tipo_identificacion'] ?? null;
            $resultado['codeudor_numero_identificacion'] = $codeudor['numero_identificacion'] ?? null;
            $resultado['codeudor_nombres'] = $codeudor['nombres'] ?? null;
            $resultado['codeudor_apellidos'] = $codeudor['apellidos'] ?? null;
            $resultado['codeudor_fecha_expedicion_cedula'] = $codeudor['fecha_expedicion_cedula'] ?? null;
            $resultado['codeudor_fecha_nacimiento'] = $codeudor['fecha_nacimiento'] ?? null;
            $resultado['codeudor_telefono'] = $codeudor['telefono'] ?? null;
            $resultado['codeudor_direccion'] = $codeudor['direccion'] ?? null;
            $resultado['codeudor_correo'] = $codeudor['correo'] ?? null;
        }
        
        // Agregar fecha_analisis_ia para compatibilidad
        $resultado['fecha_analisis_ia'] = $datosIA['fecha_analisis'] ?? null;
        
        return $resultado;
    }
    
    /**
     * Obtener todos los procesos
     */
    public function obtenerProcesos($filtros = []) {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filtros['estado'])) {
                $where[] = "p.estado = ?";
                $params[] = $filtros['estado'];
            }
            
            if (!empty($filtros['codigo'])) {
                $where[] = "p.codigo LIKE ?";
                $params[] = '%' . $filtros['codigo'] . '%';
            }
            
            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            $sql = "
                SELECT p.*, u.nombre_completo as creado_por_nombre
                FROM crear_coop_procesos p
                LEFT JOIN control_usuarios u ON p.creado_por = u.id
                $whereClause
                ORDER BY p.fecha_creacion DESC
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CrearCoop::obtenerProcesos error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Actualizar estado del proceso
     */
    public function actualizarEstado($procesoId, $nuevoEstado) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE crear_coop_procesos 
                SET estado = ?, fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            if ($nuevoEstado === 'analizado_con_ia') {
                $stmt = $this->conn->prepare("
                    UPDATE crear_coop_procesos 
                    SET estado = ?, fecha_analisis_ia = CURRENT_TIMESTAMP, fecha_actualizacion = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
            }
            
            return $stmt->execute([$nuevoEstado, $procesoId]);
        } catch (PDOException $e) {
            error_log('CrearCoop::actualizarEstado error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validar/editar datos de IA
     * @param bool $cambiarEstado Si es true, cambia el estado a 'informacion_ia_validada'
     */
    public function validarDatosIA($procesoId, $datos, $usuarioId, $cambiarEstado = false) {
        try {
            // Obtener datos originales y validados existentes
            $datosIA = $this->obtenerDatosIA($procesoId);
            if (!$datosIA) {
                return false;
            }
            
            // Obtener datos validados existentes (si existen)
            // Si no hay datos validados, empezamos con un array vacío y solo actualizamos lo que se envía
            $datosValidadosExistentes = $datosIA['datos_validados'] ?? null;
            $datosOriginales = $datosIA['datos_originales'];
            
            // Inicializar datos validados: si existen, usarlos; si no, crear estructura vacía
            // pero cuando comparamos para mostrar "Editado", siempre comparamos con los originales
            if ($datosValidadosExistentes === null) {
                $datosValidadosExistentes = [
                    'estado_cuenta' => [],
                    'deudor' => [],
                    'codeudor' => []
                ];
            }
            
            // Normalizar valores numéricos (convertir strings vacíos a null, convertir a float)
            $normalizarNumero = function($valor) {
                if ($valor === null || $valor === '' || $valor === 'null') {
                    return null;
                }
                $float = (float)$valor;
                return $float == 0 ? null : $float;
            };
            
            // Normalizar strings (convertir strings vacíos a null, trim)
            $normalizarString = function($valor) {
                if ($valor === null || $valor === '' || $valor === 'null') {
                    return null;
                }
                $trimmed = trim((string)$valor);
                return $trimmed === '' ? null : $trimmed;
            };
            
            // Inicializar con datos existentes
            $datosValidados = $datosValidadosExistentes;
            
            // Detectar qué sección se está editando basándose en los campos enviados
            $tieneEstadoCuenta = isset($datos['fecha_causacion']) || isset($datos['saldo_capital']) || 
                                 isset($datos['saldo_interes']) || isset($datos['saldo_mora']) || 
                                 isset($datos['tasa_interes_efectiva_anual']);
            
            $tieneDeudor = isset($datos['deudor_tipo_identificacion']) || isset($datos['deudor_numero_identificacion']) ||
                          isset($datos['deudor_nombres']) || isset($datos['deudor_apellidos']) ||
                          isset($datos['deudor_fecha_expedicion_cedula']) || isset($datos['deudor_fecha_nacimiento']) ||
                          isset($datos['deudor_telefono']) || isset($datos['deudor_direccion']) || isset($datos['deudor_correo']);
            
            $tieneCodeudor = isset($datos['codeudor_tipo_identificacion']) || isset($datos['codeudor_numero_identificacion']) ||
                            isset($datos['codeudor_nombres']) || isset($datos['codeudor_apellidos']) ||
                            isset($datos['codeudor_fecha_expedicion_cedula']) || isset($datos['codeudor_fecha_nacimiento']) ||
                            isset($datos['codeudor_telefono']) || isset($datos['codeudor_direccion']) || isset($datos['codeudor_correo']);
            
            // Actualizar solo la sección que se está editando
            // Si una sección tiene campos en $datos, actualizamos solo esa sección
            // Las demás secciones se mantienen intactas (se copian de datos validados existentes o se dejan vacías)
            
            // Asegurar que todas las secciones existan en el array
            if (!isset($datosValidados['estado_cuenta'])) {
                $datosValidados['estado_cuenta'] = $datosValidadosExistentes['estado_cuenta'] ?? [];
            }
            if (!isset($datosValidados['deudor'])) {
                $datosValidados['deudor'] = $datosValidadosExistentes['deudor'] ?? [];
            }
            if (!isset($datosValidados['codeudor'])) {
                $datosValidados['codeudor'] = $datosValidadosExistentes['codeudor'] ?? [];
            }
            
            if ($tieneEstadoCuenta) {
                // Actualizar solo campos de estado_cuenta que están en $datos
                if (isset($datos['fecha_causacion'])) {
                    $datosValidados['estado_cuenta']['fecha_causacion'] = !empty($datos['fecha_causacion']) ? $datos['fecha_causacion'] : null;
                }
                if (isset($datos['saldo_capital'])) {
                    $datosValidados['estado_cuenta']['saldo_capital'] = $normalizarNumero($datos['saldo_capital']);
                }
                if (isset($datos['saldo_interes'])) {
                    $datosValidados['estado_cuenta']['saldo_interes'] = $normalizarNumero($datos['saldo_interes']);
                }
                if (isset($datos['saldo_mora'])) {
                    $datosValidados['estado_cuenta']['saldo_mora'] = $normalizarNumero($datos['saldo_mora']);
                }
                if (isset($datos['tasa_interes_efectiva_anual'])) {
                    $datosValidados['estado_cuenta']['tasa_interes_efectiva_anual'] = $normalizarNumero($datos['tasa_interes_efectiva_anual']);
                }
            }
            
            if ($tieneDeudor) {
                // Actualizar solo campos de deudor que están en $datos
                if (isset($datos['deudor_tipo_identificacion'])) {
                    $datosValidados['deudor']['tipo_identificacion'] = $normalizarString($datos['deudor_tipo_identificacion']);
                }
                if (isset($datos['deudor_numero_identificacion'])) {
                    $datosValidados['deudor']['numero_identificacion'] = $normalizarString($datos['deudor_numero_identificacion']);
                }
                if (isset($datos['deudor_nombres'])) {
                    $datosValidados['deudor']['nombres'] = $normalizarString($datos['deudor_nombres']);
                }
                if (isset($datos['deudor_apellidos'])) {
                    $datosValidados['deudor']['apellidos'] = $normalizarString($datos['deudor_apellidos']);
                }
                if (isset($datos['deudor_fecha_expedicion_cedula'])) {
                    $datosValidados['deudor']['fecha_expedicion_cedula'] = !empty($datos['deudor_fecha_expedicion_cedula']) ? $datos['deudor_fecha_expedicion_cedula'] : null;
                }
                if (isset($datos['deudor_fecha_nacimiento'])) {
                    $datosValidados['deudor']['fecha_nacimiento'] = !empty($datos['deudor_fecha_nacimiento']) ? $datos['deudor_fecha_nacimiento'] : null;
                }
                if (isset($datos['deudor_telefono'])) {
                    $datosValidados['deudor']['telefono'] = $normalizarString($datos['deudor_telefono']);
                }
                if (isset($datos['deudor_direccion'])) {
                    $datosValidados['deudor']['direccion'] = $normalizarString($datos['deudor_direccion']);
                }
                if (isset($datos['deudor_correo'])) {
                    $datosValidados['deudor']['correo'] = $normalizarString($datos['deudor_correo']);
                }
            }
            
            if ($tieneCodeudor) {
                // Actualizar solo campos de codeudor que están en $datos
                if (isset($datos['codeudor_tipo_identificacion'])) {
                    $datosValidados['codeudor']['tipo_identificacion'] = $normalizarString($datos['codeudor_tipo_identificacion']);
                }
                if (isset($datos['codeudor_numero_identificacion'])) {
                    $datosValidados['codeudor']['numero_identificacion'] = $normalizarString($datos['codeudor_numero_identificacion']);
                }
                if (isset($datos['codeudor_nombres'])) {
                    $datosValidados['codeudor']['nombres'] = $normalizarString($datos['codeudor_nombres']);
                }
                if (isset($datos['codeudor_apellidos'])) {
                    $datosValidados['codeudor']['apellidos'] = $normalizarString($datos['codeudor_apellidos']);
                }
                if (isset($datos['codeudor_fecha_expedicion_cedula'])) {
                    $datosValidados['codeudor']['fecha_expedicion_cedula'] = !empty($datos['codeudor_fecha_expedicion_cedula']) ? $datos['codeudor_fecha_expedicion_cedula'] : null;
                }
                if (isset($datos['codeudor_fecha_nacimiento'])) {
                    $datosValidados['codeudor']['fecha_nacimiento'] = !empty($datos['codeudor_fecha_nacimiento']) ? $datos['codeudor_fecha_nacimiento'] : null;
                }
                if (isset($datos['codeudor_telefono'])) {
                    $datosValidados['codeudor']['telefono'] = $normalizarString($datos['codeudor_telefono']);
                }
                if (isset($datos['codeudor_direccion'])) {
                    $datosValidados['codeudor']['direccion'] = $normalizarString($datos['codeudor_direccion']);
                }
                if (isset($datos['codeudor_correo'])) {
                    $datosValidados['codeudor']['correo'] = $normalizarString($datos['codeudor_correo']);
                }
            }
            
            $datosValidadosJson = json_encode($datosValidados, JSON_UNESCAPED_UNICODE);
            
            $stmt = $this->conn->prepare("
                UPDATE crear_coop_datos_ia 
                SET datos_validados = ?,
                    fecha_validacion = CURRENT_TIMESTAMP,
                    validado_por = ?
                WHERE proceso_id = ?
            ");
            
            return $stmt->execute([$datosValidadosJson, $usuarioId, $procesoId]);
        } catch (PDOException $e) {
            error_log('CrearCoop::validarDatosIA error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualizar datos extraídos de IA (DEPRECATED - usar crear_coop_datos_ia)
     */
    public function actualizarDatosIA($procesoId, $datos) {
        try {
            $fields = [];
            $params = [];
            
            // Datos del estado de cuenta
            if (isset($datos['fecha_causacion'])) {
                $fields[] = "fecha_causacion = ?";
                $params[] = $datos['fecha_causacion'];
            }
            if (isset($datos['saldo_capital'])) {
                $fields[] = "saldo_capital = ?";
                $params[] = $datos['saldo_capital'];
            }
            if (isset($datos['saldo_interes'])) {
                $fields[] = "saldo_interes = ?";
                $params[] = $datos['saldo_interes'];
            }
            if (isset($datos['saldo_mora'])) {
                $fields[] = "saldo_mora = ?";
                $params[] = $datos['saldo_mora'];
            }
            if (isset($datos['tasa_interes_efectiva_anual'])) {
                $fields[] = "tasa_interes_efectiva_anual = ?";
                $params[] = $datos['tasa_interes_efectiva_anual'];
            }
            
            // Datos del deudor
            if (isset($datos['deudor_tipo_identificacion'])) {
                $fields[] = "deudor_tipo_identificacion = ?";
                $params[] = $datos['deudor_tipo_identificacion'];
            }
            if (isset($datos['deudor_numero_identificacion'])) {
                $fields[] = "deudor_numero_identificacion = ?";
                $params[] = $datos['deudor_numero_identificacion'];
            }
            if (isset($datos['deudor_nombres'])) {
                $fields[] = "deudor_nombres = ?";
                $params[] = $datos['deudor_nombres'];
            }
            if (isset($datos['deudor_apellidos'])) {
                $fields[] = "deudor_apellidos = ?";
                $params[] = $datos['deudor_apellidos'];
            }
            if (isset($datos['deudor_fecha_expedicion_cedula'])) {
                $fields[] = "deudor_fecha_expedicion_cedula = ?";
                $params[] = $datos['deudor_fecha_expedicion_cedula'];
            }
            if (isset($datos['deudor_fecha_nacimiento'])) {
                $fields[] = "deudor_fecha_nacimiento = ?";
                $params[] = $datos['deudor_fecha_nacimiento'];
            }
            if (isset($datos['deudor_telefono'])) {
                $fields[] = "deudor_telefono = ?";
                $params[] = $datos['deudor_telefono'];
            }
            if (isset($datos['deudor_direccion'])) {
                $fields[] = "deudor_direccion = ?";
                $params[] = $datos['deudor_direccion'];
            }
            if (isset($datos['deudor_correo'])) {
                $fields[] = "deudor_correo = ?";
                $params[] = $datos['deudor_correo'];
            }
            
            // Datos del codeudor
            if (isset($datos['codeudor_tipo_identificacion'])) {
                $fields[] = "codeudor_tipo_identificacion = ?";
                $params[] = $datos['codeudor_tipo_identificacion'];
            }
            if (isset($datos['codeudor_numero_identificacion'])) {
                $fields[] = "codeudor_numero_identificacion = ?";
                $params[] = $datos['codeudor_numero_identificacion'];
            }
            if (isset($datos['codeudor_nombres'])) {
                $fields[] = "codeudor_nombres = ?";
                $params[] = $datos['codeudor_nombres'];
            }
            if (isset($datos['codeudor_apellidos'])) {
                $fields[] = "codeudor_apellidos = ?";
                $params[] = $datos['codeudor_apellidos'];
            }
            if (isset($datos['codeudor_fecha_expedicion_cedula'])) {
                $fields[] = "codeudor_fecha_expedicion_cedula = ?";
                $params[] = $datos['codeudor_fecha_expedicion_cedula'];
            }
            if (isset($datos['codeudor_fecha_nacimiento'])) {
                $fields[] = "codeudor_fecha_nacimiento = ?";
                $params[] = $datos['codeudor_fecha_nacimiento'];
            }
            if (isset($datos['codeudor_telefono'])) {
                $fields[] = "codeudor_telefono = ?";
                $params[] = $datos['codeudor_telefono'];
            }
            if (isset($datos['codeudor_direccion'])) {
                $fields[] = "codeudor_direccion = ?";
                $params[] = $datos['codeudor_direccion'];
            }
            if (isset($datos['codeudor_correo'])) {
                $fields[] = "codeudor_correo = ?";
                $params[] = $datos['codeudor_correo'];
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $params[] = $procesoId;
            $sql = "UPDATE crear_coop_procesos SET " . implode(", ", $fields) . " WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('CrearCoop::actualizarDatosIA error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener anexos de un proceso
     */
    public function obtenerAnexos($procesoId, $tipo = null) {
        try {
            $sql = "SELECT * FROM crear_coop_anexos WHERE proceso_id = ?";
            $params = [$procesoId];
            
            if ($tipo) {
                $sql .= " AND tipo = ?";
                $params[] = $tipo;
            }
            
            $sql .= " ORDER BY fecha_subida ASC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CrearCoop::obtenerAnexos error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener un anexo por su ID
     */
    public function obtenerAnexoPorId($anexoId) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM crear_coop_anexos WHERE id = ?");
            $stmt->execute([$anexoId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CrearCoop::obtenerAnexoPorId error: ' . $e->getMessage());
            return false;
        }
    }
}
?>

