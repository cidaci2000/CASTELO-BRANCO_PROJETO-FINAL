<?php
// config/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Exige que o usuário esteja logado e seja de um dos tipos permitidos.
 * @param array $tipos_permitidos Ex: ['admin'] ou ['admin','instrutor']
 */
function exigir_login(array $tipos_permitidos = []) {
    if (!isset($_SESSION['usuario'])) {
        header('Location: ../php/login.php');
        exit;
    }
    if (!empty($tipos_permitidos) && !in_array($_SESSION['usuario']['tipo'], $tipos_permitidos, true)) {
        header('Location: ../php/login.php');
        exit;
    }
}

function usuario_logado(): array {
    return $_SESSION['usuario'] ?? [];
}