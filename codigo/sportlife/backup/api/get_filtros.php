<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../config/database.php';

$modalidades = $pdo->query(
    "SELECT DISTINCT modalidade FROM aulas WHERE status='agendada' ORDER BY modalidade"
)->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'sucesso'     => true,
    'modalidades' => $modalidades,
    'unidades'    => ['Curitiba', 'Pinhais', 'Batel'] // fixo por enquanto
], JSON_UNESCAPED_UNICODE);