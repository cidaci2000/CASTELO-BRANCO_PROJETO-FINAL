<?php
/**
 * api/get_aulas.php
 * Retorna a grade de aulas agrupada por dia da semana e horário.
 * 
 * Parâmetros GET (opcionais):
 *   - modalidade: filtra por modalidade (ex: "Yoga", "Spinning")
 *                 use "todas" para não filtrar
 */

// ------------------------------------------------------------
// DEBUG — remova as 2 linhas abaixo em produção
// ------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ------------------------------------------------------------
// HEADERS
// ------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ------------------------------------------------------------
// CONEXÃO
// ------------------------------------------------------------
require __DIR__ . '/../config/database.php';

// ------------------------------------------------------------
// PARÂMETROS
// ------------------------------------------------------------
$modalidade = $_GET['modalidade'] ?? 'todas';

// ------------------------------------------------------------
// QUERY
// ------------------------------------------------------------
$sql = "SELECT 
            a.id,
            a.titulo,
            a.modalidade,
            a.data_aula,
            a.duracao,
            a.vagas_disponiveis,
            a.capacidade_maxima,
            a.local,
            a.status,
            u.nome_completo AS instrutor
        FROM aulas a
        LEFT JOIN usuarios u ON a.instrutor_id = u.id
        WHERE a.status = 'agendada'
          AND a.data_aula >= CURDATE()
          AND a.data_aula < DATE_ADD(CURDATE(), INTERVAL 7 DAY)";

$params = [];

if ($modalidade !== 'todas' && $modalidade !== '') {
    $sql .= " AND a.modalidade = :modalidade";
    $params[':modalidade'] = $modalidade;
}

$sql .= " ORDER BY a.data_aula ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $aulas = $stmt->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso'  => false,
        'mensagem' => 'Erro na consulta: ' . $e->getMessage(),
        'sql'      => $sql
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------------------------------------
// MONTA GRADE
// ------------------------------------------------------------
$diasMap = [
    1 => 'SEG',
    2 => 'TER',
    3 => 'QUA',
    4 => 'QUI',
    5 => 'SEX',
    6 => 'SAB',
    7 => 'DOM'
];

$grade    = [];
$horarios = [];

foreach ($aulas as $aula) {
    $dt        = new DateTime($aula['data_aula']);
    $diaSemana = (int)$dt->format('N'); // 1=Seg ... 7=Dom
    $hora      = $dt->format('H:i');

    if (!in_array($hora, $horarios, true)) {
        $horarios[] = $hora;
    }

    $grade[$diaSemana][$hora][] = [
        'id'                => (int)$aula['id'],
        'titulo'            => $aula['titulo'],
        'instrutor'         => $aula['instrutor'] ?? 'A definir',
        'modalidade'        => $aula['modalidade'],
        'local'             => $aula['local'] ?? '-',
        'vagas_disponiveis' => (int)$aula['vagas_disponiveis'],
        'capacidade_maxima' => (int)$aula['capacidade_maxima'],
        'duracao'           => (int)$aula['duracao'],
    ];
}

// Ordena horários (06:00 antes de 20:00)
sort($horarios);

// ------------------------------------------------------------
// RESPOSTA
// ------------------------------------------------------------
echo json_encode([
    'sucesso'      => true,
    'total_aulas'  => count($aulas),
    'dias'         => $diasMap,
    'horarios'     => $horarios,
    'grade'        => $grade,
    'filtros'      => [
        'modalidade' => $modalidade
    ]
], JSON_UNESCAPED_UNICODE);