<?php
// config/database.php

$host    = 'localhost';
$usuario = 'root';
$senha   = '';        // XAMPP padrão = vazio
$banco   = 'sportlife'; // nome do seu banco

$conn = new mysqli($host, $usuario, $senha, $banco);

// Verifica conexão
if ($conn->connect_error) {
    die('Erro de conexão: ' . $conn->connect_error);
}

// Charset UTF-8
$conn->set_charset('utf8mb4');