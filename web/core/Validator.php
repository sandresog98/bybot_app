<?php
/**
 * Validator - Clase para validación de datos
 * ByBot v2.0
 */

class Validator {
    private $data = [];
    private $errors = [];
    private $rules = [];
    
    /**
     * Constructor
     */
    public function __construct(array $data = []) {
        $this->data = $data;
    }
    
    /**
     * Establecer datos a validar
     */
    public function setData(array $data) {
        $this->data = $data;
        $this->errors = [];
        return $this;
    }
    
    /**
     * Agregar regla de validación
     */
    public function rule($field, $rules) {
        $this->rules[$field] = is_array($rules) ? $rules : explode('|', $rules);
        return $this;
    }
    
    /**
     * Agregar múltiples reglas
     */
    public function rules(array $rules) {
        foreach ($rules as $field => $fieldRules) {
            $this->rule($field, $fieldRules);
        }
        return $this;
    }
    
    /**
     * Ejecutar validación
     */
    public function validate() {
        $this->errors = [];
        
        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;
            
            foreach ($rules as $rule) {
                $params = [];
                
                // Parsear regla con parámetros (ej: min:5, max:100)
                if (strpos($rule, ':') !== false) {
                    list($rule, $paramStr) = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }
                
                $method = 'validate' . ucfirst($rule);
                
                if (method_exists($this, $method)) {
                    $error = $this->$method($field, $value, $params);
                    if ($error) {
                        $this->errors[$field] = $error;
                        break; // Solo un error por campo
                    }
                }
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Verificar si pasó validación
     */
    public function passes() {
        return $this->validate();
    }
    
    /**
     * Verificar si falló validación
     */
    public function fails() {
        return !$this->validate();
    }
    
    /**
     * Obtener errores
     */
    public function errors() {
        return $this->errors;
    }
    
    /**
     * Obtener primer error
     */
    public function firstError() {
        return reset($this->errors) ?: null;
    }
    
    // =============================================
    // REGLAS DE VALIDACIÓN
    // =============================================
    
    protected function validateRequired($field, $value, $params) {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return "El campo '$field' es requerido";
        }
        return null;
    }
    
    protected function validateEmail($field, $value, $params) {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "El campo '$field' debe ser un email válido";
        }
        return null;
    }
    
    protected function validateMin($field, $value, $params) {
        $min = (int)($params[0] ?? 0);
        
        if (is_string($value) && strlen($value) < $min) {
            return "El campo '$field' debe tener al menos $min caracteres";
        }
        
        if (is_numeric($value) && $value < $min) {
            return "El campo '$field' debe ser al menos $min";
        }
        
        if (is_array($value) && count($value) < $min) {
            return "El campo '$field' debe tener al menos $min elementos";
        }
        
        return null;
    }
    
    protected function validateMax($field, $value, $params) {
        $max = (int)($params[0] ?? PHP_INT_MAX);
        
        if (is_string($value) && strlen($value) > $max) {
            return "El campo '$field' no debe exceder $max caracteres";
        }
        
        if (is_numeric($value) && $value > $max) {
            return "El campo '$field' no debe ser mayor a $max";
        }
        
        if (is_array($value) && count($value) > $max) {
            return "El campo '$field' no debe tener más de $max elementos";
        }
        
        return null;
    }
    
    protected function validateNumeric($field, $value, $params) {
        if ($value && !is_numeric($value)) {
            return "El campo '$field' debe ser numérico";
        }
        return null;
    }
    
    protected function validateInteger($field, $value, $params) {
        if ($value && !filter_var($value, FILTER_VALIDATE_INT)) {
            return "El campo '$field' debe ser un número entero";
        }
        return null;
    }
    
    protected function validateAlpha($field, $value, $params) {
        if ($value && !ctype_alpha(str_replace(' ', '', $value))) {
            return "El campo '$field' solo debe contener letras";
        }
        return null;
    }
    
    protected function validateAlphanumeric($field, $value, $params) {
        if ($value && !ctype_alnum(str_replace(' ', '', $value))) {
            return "El campo '$field' solo debe contener letras y números";
        }
        return null;
    }
    
    protected function validateIn($field, $value, $params) {
        if ($value && !in_array($value, $params)) {
            return "El campo '$field' debe ser uno de: " . implode(', ', $params);
        }
        return null;
    }
    
    protected function validateDate($field, $value, $params) {
        if ($value) {
            $format = $params[0] ?? 'Y-m-d';
            $d = DateTime::createFromFormat($format, $value);
            if (!$d || $d->format($format) !== $value) {
                return "El campo '$field' debe ser una fecha válida ($format)";
            }
        }
        return null;
    }
    
    protected function validateUrl($field, $value, $params) {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            return "El campo '$field' debe ser una URL válida";
        }
        return null;
    }
    
    protected function validateRegex($field, $value, $params) {
        $pattern = $params[0] ?? '';
        if ($value && $pattern && !preg_match($pattern, $value)) {
            return "El campo '$field' tiene un formato inválido";
        }
        return null;
    }
    
    protected function validateConfirmed($field, $value, $params) {
        $confirmField = $field . '_confirmation';
        $confirmValue = $this->data[$confirmField] ?? null;
        
        if ($value !== $confirmValue) {
            return "La confirmación de '$field' no coincide";
        }
        return null;
    }
    
    protected function validateUnique($field, $value, $params) {
        if ($value && !empty($params)) {
            $table = $params[0];
            $column = $params[1] ?? $field;
            $exceptId = $params[2] ?? null;
            
            $db = Database::getInstance();
            $sql = "SELECT COUNT(*) as total FROM $table WHERE $column = ?";
            $sqlParams = [$value];
            
            if ($exceptId) {
                $sql .= " AND id != ?";
                $sqlParams[] = $exceptId;
            }
            
            $result = $db->queryOne($sql, $sqlParams);
            
            if ($result['total'] > 0) {
                return "El valor de '$field' ya está en uso";
            }
        }
        return null;
    }
    
    protected function validateExists($field, $value, $params) {
        if ($value && !empty($params)) {
            $table = $params[0];
            $column = $params[1] ?? 'id';
            
            $db = Database::getInstance();
            $sql = "SELECT COUNT(*) as total FROM $table WHERE $column = ?";
            $result = $db->queryOne($sql, [$value]);
            
            if ($result['total'] == 0) {
                return "El valor de '$field' no existe";
            }
        }
        return null;
    }
    
    protected function validateJson($field, $value, $params) {
        if ($value) {
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return "El campo '$field' debe ser un JSON válido";
            }
        }
        return null;
    }
    
    protected function validateFile($field, $value, $params) {
        if (isset($_FILES[$field])) {
            $file = $_FILES[$field];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return "Error al subir el archivo '$field'";
            }
        }
        return null;
    }
    
    protected function validateMimes($field, $value, $params) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$field];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, $params)) {
                return "El archivo '$field' debe ser de tipo: " . implode(', ', $params);
            }
        }
        return null;
    }
    
    protected function validateMaxSize($field, $value, $params) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$field];
            $maxSize = (int)($params[0] ?? 0);
            
            if ($file['size'] > $maxSize) {
                $maxMB = round($maxSize / 1024 / 1024, 2);
                return "El archivo '$field' no debe exceder {$maxMB}MB";
            }
        }
        return null;
    }
    
    // =============================================
    // MÉTODOS ESTÁTICOS DE CONVENIENCIA
    // =============================================
    
    /**
     * Validar datos con reglas
     */
    public static function make(array $data, array $rules) {
        $validator = new self($data);
        $validator->rules($rules);
        return $validator;
    }
    
    /**
     * Validar un solo campo
     */
    public static function validateField($value, $rules) {
        $validator = new self(['field' => $value]);
        $validator->rule('field', $rules);
        return $validator->validate() ? true : $validator->firstError();
    }
}

