<?php
/**
 * Funciones Helper Globales
 */

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function jsonSuccess($message, $data = []) {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data), 200);
}

function jsonError($message, $statusCode = 400, $data = []) {
    jsonResponse(array_merge(['success' => false, 'message' => $message], $data), $statusCode);
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        jsonError('Campos requeridos faltantes: ' . implode(', ', $missing), 400);
    }
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function logError($message, $context = []) {
    $log = date('[Y-m-d H:i:s] ') . $message;
    if (!empty($context)) {
        $log .= ' | Context: ' . json_encode($context);
    }
    $log .= PHP_EOL;

    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/app.log';
    error_log($log, 3, $logFile);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

