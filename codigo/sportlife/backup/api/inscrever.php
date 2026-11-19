<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../config/database.php';

session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para se inscrever.']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);
$aulaId  = (int)($dados['aula_id'] ?? 0);
$alunoId = (int)$_SESSION['usuario_id'];

if ($aulaId <= 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Aula inválida.']);
    exit;
}

try {
    // Chama a procedure que você já criou!
    $stmt = $pdo->prepare("CALL sp_inscrever_aula(:aula_id, :aluno_id)");
    $stmt->execute([':aula_id' => $aulaId, ':aluno_id' => $alunoId]);
    $result = $stmt->fetch();
    $stmt->closeCursor();

    echo json_encode([
        'sucesso'  => true,
        'mensagem' => $result['mensagem'] ?? 'Inscrição processada.'
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        'sucesso'  => false,
        'mensagem' => 'Erro: ' . $e->getMessage()
    ]);
}