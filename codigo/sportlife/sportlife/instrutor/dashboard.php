<?php
// ============================================================
// instrutor/dashboard.php — Painel do Instrutor
// MOSTRA APENAS:
//   1) Alunos cuja modalidade == modalidade do instrutor
//   2) Agenda do instrutor (aulas que ele ministra)
// ============================================================
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

exigir_login(['instrutor']);

$user = usuario_logado();
$instrutor_id = (int)$user['id'];

$pdo->exec("SET time_zone = '-03:00'");

// ---------- Instrutor logado ----------
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
$stmt->execute([$instrutor_id]);
$instrutor = $stmt->fetch();

if (!$instrutor) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$modalidade_instrutor = $instrutor['modalidade'] ?? '';
$email_instrutor      = $instrutor['email']      ?? '';

// ============================================================
// 1) ALUNOS DA MODALIDADE DO INSTRUTOR
//    Filtro: tipo_usuario='usuario' AND modalidade = instrutor.modalidade
// ============================================================
$stmt = $pdo->prepare("
    SELECT id, nome_completo, email, telefone, modalidade, data_criacao
    FROM usuarios
    WHERE tipo_usuario = 'usuario'
      AND modalidade = :mod
      AND ativo = 1
    ORDER BY nome_completo ASC
");
$stmt->execute([':mod' => $modalidade_instrutor]);
$alunos = $stmt->fetchAll();

$total_alunos = count($alunos);

// ============================================================
// 2) AGENDA DO INSTRUTOR
// ============================================================
$stmt = $pdo->prepare("
    SELECT a.id, a.titulo, a.modalidade, a.data_aula, a.duracao,
           a.local, a.capacidade_maxima, a.vagas_disponiveis, a.status,
           (a.capacidade_maxima - a.vagas_disponiveis) AS ocupadas,
           (SELECT COUNT(*) FROM inscricoes_aulas i
            WHERE i.aula_id = a.id AND i.status = 'confirmada') AS inscritos
    FROM aulas a
    WHERE a.instrutor_id = ?
    ORDER BY a.data_aula ASC
");
$stmt->execute([$instrutor_id]);
$agenda = $stmt->fetchAll();

$agora = new DateTime();
$proximas = [];
$passadas = [];
foreach ($agenda as $a) {
    if (new DateTime($a['data_aula']) >= $agora) {
        $proximas[] = $a;
    } else {
        $passadas[] = $a;
    }
}

// ============================================================
// DEBUG TEMPORÁRIO (remova depois)
// ============================================================
// echo "<pre style='color:#0f0;background:#000;padding:10px;'>";
// echo "Instrutor ID: $instrutor_id\n";
// echo "Modalidade: $modalidade_instrutor\n";
// echo "Alunos encontrados: $total_alunos\n";
// print_r($alunos);
// echo "</pre>";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SportLife Instrutor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --bg-dark: #06060a;
            --card-bg: rgba(10, 10, 15, 0.7);
            --card-border: rgba(255, 255, 255, 0.06);
            --text-main: #f8fafc;
            --text-muted: #64748b;
            --green: #22c55e;
            --red: #ef4444;
            --amber: #eab308;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-dark); color: var(--text-main); min-height: 100vh; padding: 30px; }
        .container { max-width: 1280px; margin: 0 auto; }

        .header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 0; border-bottom: 1px solid var(--card-border);
            margin-bottom: 30px; flex-wrap: wrap; gap: 15px;
        }
        .header h1 {
            font-family: 'Bebas Neue', sans-serif; font-size: 2.5rem;
            background: linear-gradient(180deg, #ffffff 20%, #86efac 65%, #22c55e 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .user-info {
            display: flex; align-items: center; gap: 12px;
            background: var(--card-bg); padding: 10px 20px;
            border-radius: 12px; border: 1px solid var(--card-border);
            flex-wrap: wrap;
        }
        .user-info .avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: var(--green); display: flex;
            align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.2rem; flex-shrink: 0;
        }
        .user-info .user-meta { display: flex; flex-direction: column; line-height: 1.25; }
        .user-info .name { font-weight: 700; font-size: 0.92rem; }
        .user-info .email { font-size: 0.72rem; color: var(--text-muted); }
        .user-info .badge {
            background: rgba(34, 197, 94, 0.2); padding: 4px 12px;
            border-radius: 20px; font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .user-info .badge.modalidade {
            background: rgba(79, 70, 229, 0.2);
            color: #a5b4fc;
            border-color: rgba(79, 70, 229, 0.3);
        }
        .logout-btn {
            color: var(--text-muted); text-decoration: none;
            padding: 8px 16px; border-radius: 8px;
            border: 1px solid var(--card-border);
            transition: all 0.3s ease; font-size: 0.85rem;
        }
        .logout-btn:hover {
            color: var(--red); border-color: var(--red);
            background: rgba(239, 68, 68, 0.1);
        }

        .section-title {
            font-family: 'Bebas Neue', sans-serif; font-size: 1.6rem;
            letter-spacing: 1.5px; text-transform: uppercase;
            margin: 35px 0 18px; padding-left: 12px;
            border-left: 3px solid var(--green);
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .section-title .count {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.7rem; font-weight: 700; letter-spacing: 1px;
            background: rgba(34, 197, 94, 0.15); color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
            padding: 3px 10px; border-radius: 20px;
        }

        .table-container {
            background: var(--card-bg); border: 1px solid var(--card-border);
            border-radius: 16px; padding: 20px; overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; }
        table th {
            text-align: left; padding: 12px 16px; color: var(--text-muted);
            font-size: 0.72rem; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 1px solid var(--card-border);
        }
        table td { padding: 12px 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.03); font-size: 0.88rem; }
        table tr:hover td { background: rgba(255, 255, 255, 0.02); }
        table tr:last-child td { border-bottom: none; }

        .badge-modalidade {
            padding: 4px 12px; border-radius: 20px; font-size: 0.7rem;
            background: rgba(255, 255, 255, 0.05); border: 1px solid var(--card-border);
        }
        .badge-status {
            padding: 4px 10px; border-radius: 20px;
            font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
        }
        .st-agendada     { background: rgba(34,197,94,0.15); color: #86efac; border: 1px solid rgba(34,197,94,0.3); }
        .st-em_andamento { background: rgba(79,70,229,0.15); color: #818cf8; border: 1px solid rgba(79,70,229,0.3); }
        .st-finalizada   { background: rgba(100,116,139,0.15); color: #cbd5e1; border: 1px solid rgba(100,116,139,0.3); }
        .st-cancelada    { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }

        .empty { text-align: center; color: var(--text-muted); padding: 35px; font-size: 0.85rem; }
        .empty strong { color: #86efac; }

        .info-banner {
            background: rgba(34, 197, 94, 0.08);
            border: 1px solid rgba(34, 197, 94, 0.25);
            border-radius: 12px;
            padding: 14px 20px;
            margin-bottom: 25px;
            font-size: 0.82rem;
            color: #bbf7d0;
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        }
        .info-banner strong { color: #86efac; }

        .ocupa-cell { display: flex; align-items: center; gap: 8px; min-width: 130px; }
        .ocupa-bar {
            flex: 1; height: 5px; background: rgba(255,255,255,0.05);
            border-radius: 50px; overflow: hidden;
        }
        .ocupa-bar span { display: block; height: 100%; background: var(--green); border-radius: 50px; }
        .ocupa-txt { font-size: 0.72rem; color: var(--text-muted); white-space: nowrap; }

        @media (max-width: 768px) {
            body { padding: 15px; }
            .header { flex-direction: column; align-items: stretch; text-align: center; }
            .user-info { justify-content: center; flex-wrap: wrap; }
            .user-info .user-meta { align-items: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>🏋️ Painel do Instrutor</h1>
            <div class="user-info">
                <div class="avatar"><?= htmlspecialchars(mb_substr($instrutor['nome_completo'], 0, 1)) ?></div>
                <div class="user-meta">
                    <span class="name"><?= htmlspecialchars($instrutor['nome_completo']) ?></span>
                    <span class="email"><?= htmlspecialchars($email_instrutor) ?></span>
                </div>
                <span class="badge">Instrutor</span>
                <span class="badge modalidade">🏆 <?= htmlspecialchars($modalidade_instrutor) ?></span>
                <a href="../logout.php" class="logout-btn" onclick="return confirm('Deseja realmente sair?')">Sair</a>
            </div>
        </div>

        <div class="info-banner">
            <span>🎯</span>
            <span>
                Exibindo somente alunos e aulas da modalidade
                <strong><?= htmlspecialchars($modalidade_instrutor) ?></strong>
                vinculados a <strong><?= htmlspecialchars($instrutor['nome_completo']) ?></strong>.
            </span>
        </div>

        <!-- ============================================================ -->
        <!-- 1) ALUNOS DA MODALIDADE                                      -->
        <!-- ============================================================ -->
        <h2 class="section-title">
            👥 Alunos da Modalidade <?= htmlspecialchars($modalidade_instrutor) ?>
            <span class="count"><?= $total_alunos ?> aluno<?= $total_alunos !== 1 ? 's' : '' ?></span>
        </h2>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Modalidade</th>
                        <th>Cadastro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alunos)): ?>
                        <tr>
                            <td colspan="6" class="empty">
                                Nenhum aluno cadastrado na modalidade
                                <strong><?= htmlspecialchars($modalidade_instrutor) ?></strong>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($alunos as $al): ?>
                        <tr>
                            <td>#<?= (int)$al['id'] ?></td>
                            <td><?= htmlspecialchars($al['nome_completo']) ?></td>
                            <td><?= htmlspecialchars($al['email']) ?></td>
                            <td><?= htmlspecialchars($al['telefone'] ?? '—') ?></td>
                            <td><span class="badge-modalidade"><?= htmlspecialchars($al['modalidade']) ?></span></td>
                            <td><?= date('d/m/Y', strtotime($al['data_criacao'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ============================================================ -->
        <!-- 2) AGENDA DO INSTRUTOR                                       -->
        <!-- ============================================================ -->
        <h2 class="section-title">
            📅 Minha Agenda
            <span class="count"><?= count($agenda) ?> aula<?= count($agenda) !== 1 ? 's' : '' ?></span>
        </h2>

        <!-- PRÓXIMAS AULAS -->
        <div class="table-container" style="margin-bottom: 20px;">
            <h3 style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">
                🔜 Próximas Aulas (<?= count($proximas) ?>)
            </h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Aula</th>
                        <th>Modalidade</th>
                        <th>Data / Hora</th>
                        <th>Duração</th>
                        <th>Local</th>
                        <th>Status</th>
                        <th>Ocupação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($proximas)): ?>
                        <tr><td colspan="8" class="empty">Nenhuma aula futura agendada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($proximas as $a): 
                            $perc = $a['capacidade_maxima'] > 0
                                ? ($a['ocupadas'] / $a['capacidade_maxima']) * 100 : 0;
                        ?>
                        <tr>
                            <td>#<?= (int)$a['id'] ?></td>
                            <td><?= htmlspecialchars($a['titulo']) ?></td>
                            <td><span class="badge-modalidade"><?= htmlspecialchars($a['modalidade']) ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($a['data_aula'])) ?></td>
                            <td><?= (int)$a['duracao'] ?> min</td>
                            <td><?= htmlspecialchars($a['local'] ?? '—') ?></td>
                            <td>
                                <span class="badge-status st-<?= htmlspecialchars($a['status']) ?>">
                                    <?= htmlspecialchars($a['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="ocupa-cell">
                                    <div class="ocupa-bar"><span style="width: <?= $perc ?>%"></span></div>
                                    <span class="ocupa-txt"><?= (int)$a['inscritos'] ?>/<?= (int)$a['capacidade_maxima'] ?></span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- AULAS PASSADAS -->
        <div class="table-container">
            <h3 style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">
                ⏳ Aulas Passadas (<?= count($passadas) ?>)
            </h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Aula</th>
                        <th>Modalidade</th>
                        <th>Data / Hora</th>
                        <th>Duração</th>
                        <th>Local</th>
                        <th>Status</th>
                        <th>Inscritos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($passadas)): ?>
                        <tr><td colspan="8" class="empty">Nenhuma aula passada registrada.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_reverse($passadas) as $a): ?>
                        <tr>
                            <td>#<?= (int)$a['id'] ?></td>
                            <td><?= htmlspecialchars($a['titulo']) ?></td>
                            <td><span class="badge-modalidade"><?= htmlspecialchars($a['modalidade']) ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($a['data_aula'])) ?></td>
                            <td><?= (int)$a['duracao'] ?> min</td>
                            <td><?= htmlspecialchars($a['local'] ?? '—') ?></td>
                            <td>
                                <span class="badge-status st-<?= htmlspecialchars($a['status']) ?>">
                                    <?= htmlspecialchars($a['status']) ?>
                                </span>
                            </td>
                            <td><?= (int)$a['inscritos'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>