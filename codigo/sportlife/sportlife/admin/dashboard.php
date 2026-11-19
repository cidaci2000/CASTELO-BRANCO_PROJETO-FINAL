<?php
// ============================================================
// admin/dashboard.php — Painel do Administrador
// ============================================================
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

exigir_login(['admin']);

$user = usuario_logado();

// Fuso horário alinhado com o MySQL
$pdo->exec("SET time_zone = '-03:00'");

// ============================================================
// Admin logado
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ============================================================
// Estatísticas de usuários
// ============================================================
$stats = [];

$stats['total_usuarios'] = (int)$pdo
    ->query("SELECT COUNT(*) FROM usuarios")
    ->fetchColumn();

$stats['total_admins'] = (int)$pdo
    ->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'admin'")
    ->fetchColumn();

$stats['total_instrutores'] = (int)$pdo
    ->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'instrutor'")
    ->fetchColumn();

$stats['total_usuarios_comuns'] = (int)$pdo
    ->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'usuario'")
    ->fetchColumn();

// ============================================================
// Estatísticas de aulas
// ============================================================
$stats['aulas_agendadas'] = (int)$pdo
    ->query("SELECT COUNT(*) FROM aulas WHERE status = 'agendada' AND data_aula >= NOW()")
    ->fetchColumn();

$stats['aulas_hoje'] = (int)$pdo
    ->query("SELECT COUNT(*) FROM aulas WHERE DATE(data_aula) = CURDATE() AND status IN ('agendada','em_andamento')")
    ->fetchColumn();

$stats['inscricoes_confirmadas'] = (int)$pdo
    ->query("SELECT COUNT(*) FROM inscricoes_aulas WHERE status = 'confirmada'")
    ->fetchColumn();

$stats['taxa_ocupacao'] = 0;
$row = $pdo->query("
    SELECT
        COALESCE(SUM(capacidade_maxima), 0) AS cap,
        COALESCE(SUM(capacidade_maxima - vagas_disponiveis), 0) AS ocup
    FROM aulas
    WHERE status = 'agendada' AND data_aula >= NOW()
")->fetch();

if ($row && (int)$row['cap'] > 0) {
    $stats['taxa_ocupacao'] = round(((int)$row['ocup'] / (int)$row['cap']) * 100);
}

// ============================================================
// Últimos usuários cadastrados
// ============================================================
$ultimos_usuarios = $pdo->query("
    SELECT id, nome_completo, email, modalidade, tipo_usuario, data_criacao
    FROM usuarios
    ORDER BY data_criacao DESC
    LIMIT 8
")->fetchAll();

// ============================================================
// Próximas aulas (com instrutor e ocupação)
// ============================================================
$proximas_aulas = $pdo->query("
    SELECT a.id, a.titulo, a.modalidade, a.data_aula, a.duracao,
           a.local, a.capacidade_maxima, a.vagas_disponiveis,
           u.nome_completo AS instrutor,
           (a.capacidade_maxima - a.vagas_disponiveis) AS ocupadas
    FROM aulas a
    LEFT JOIN usuarios u ON u.id = a.instrutor_id
    WHERE a.status = 'agendada' AND a.data_aula >= NOW()
    ORDER BY a.data_aula ASC
    LIMIT 6
")->fetchAll();

// ============================================================
// Inscrições recentes
// ============================================================
$inscricoes_recentes = $pdo->query("
    SELECT i.id, i.status, i.data_inscricao,
           u.nome_completo AS aluno,
           a.titulo AS aula,
           a.modalidade,
           a.data_aula
    FROM inscricoes_aulas i
    JOIN usuarios u ON u.id = i.aluno_id
    JOIN aulas a ON a.id = i.aula_id
    ORDER BY i.data_inscricao DESC
    LIMIT 8
")->fetchAll();

// ============================================================
// Cores por modalidade (para os cards)
// ============================================================
$cores_mod = [
    'Musculação' => '#4f46e5',
    'Funcional'  => '#22c55e',
    'CrossFit'   => '#ef4444',
    'Yoga'       => '#eab308',
    'Luta'       => '#f97316',
    'Pilates'    => '#06b6d4',
];
function cor_mod($m, $map) { return $map[$m] ?? '#818cf8'; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SportLife Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-glow: rgba(79, 70, 229, 0.4);
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
            background: linear-gradient(180deg, #ffffff 20%, #818cf8 65%, #4f46e5 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .user-info {
            display: flex; align-items: center; gap: 15px;
            background: var(--card-bg); padding: 10px 20px;
            border-radius: 12px; border: 1px solid var(--card-border);
        }
        .user-info .avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: var(--primary); display: flex;
            align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.2rem;
        }
        .user-info .name { font-weight: 600; }
        .user-info .badge {
            background: rgba(79, 70, 229, 0.2); padding: 4px 12px;
            border-radius: 20px; font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; color: #818cf8;
            border: 1px solid rgba(79, 70, 229, 0.3);
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

        /* ===== SEÇÃO ===== */
        .section-title {
            font-family: 'Bebas Neue', sans-serif; font-size: 1.6rem;
            letter-spacing: 1.5px; text-transform: uppercase;
            margin: 30px 0 15px; padding-left: 12px;
            border-left: 3px solid var(--primary);
        }

        /* ===== STATS ===== */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
        }
        .stat-card {
            background: var(--card-bg); border: 1px solid var(--card-border);
            border-radius: 16px; padding: 22px; text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover { border-color: var(--primary); box-shadow: 0 0 30px var(--primary-glow); }
        .stat-number {
            font-size: 2.3rem; font-weight: 800;
            background: linear-gradient(180deg, #ffffff, #818cf8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .stat-label { color: var(--text-muted); font-size: 0.8rem; font-weight: 500; margin-top: 5px; }
        .stat-icon { font-size: 1.8rem; margin-bottom: 8px; }

        /* ===== AULAS ===== */
        .aulas-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        .aula-card {
            background: var(--card-bg); border: 1px solid var(--card-border);
            border-radius: 16px; padding: 20px;
            position: relative; overflow: hidden;
            transition: 0.3s;
        }
        .aula-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
            background: var(--cor);
        }
        .aula-card:hover { border-color: var(--primary); transform: translateY(-3px); }
        .aula-modalidade {
            font-family: 'Bebas Neue', sans-serif; font-size: 1.2rem;
            letter-spacing: 2px; text-transform: uppercase; color: var(--cor);
            margin-bottom: 2px;
        }
        .aula-titulo { font-size: 0.95rem; font-weight: 700; margin-bottom: 10px; }
        .aula-info { color: var(--text-muted); font-size: 0.78rem; line-height: 1.8; margin-bottom: 12px; }
        .aula-info strong { color: var(--text-main); }
        .vagas-bar {
            width: 100%; height: 6px; background: rgba(255,255,255,0.05);
            border-radius: 50px; overflow: hidden; margin: 8px 0 6px;
        }
        .vagas-bar span { display: block; height: 100%; background: var(--cor); border-radius: 50px; }
        .vagas-label { font-size: 0.72rem; color: var(--text-muted); text-align: right; }

        /* ===== TABELA ===== */
        .table-container {
            background: var(--card-bg); border: 1px solid var(--card-border);
            border-radius: 16px; padding: 24px; overflow-x: auto;
        }
        .table-container h2 { font-size: 1.1rem; margin-bottom: 16px; color: var(--text-muted); }
        table { width: 100%; border-collapse: collapse; }
        table th {
            text-align: left; padding: 12px 16px; color: var(--text-muted);
            font-size: 0.72rem; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 1px solid var(--card-border);
        }
        table td { padding: 12px 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.03); font-size: 0.88rem; }
        table tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .badge-tipo {
            padding: 4px 12px; border-radius: 20px;
            font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
        }
        .badge-admin     { background: rgba(239,68,68,0.2); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
        .badge-instrutor { background: rgba(34,197,94,0.2); color: #86efac; border: 1px solid rgba(34,197,94,0.3); }
        .badge-usuario   { background: rgba(79,70,229,0.2); color: #818cf8; border: 1px solid rgba(79,70,229,0.3); }

        .badge-modalidade {
            padding: 4px 12px; border-radius: 20px; font-size: 0.7rem;
            background: rgba(255, 255, 255, 0.05); border: 1px solid var(--card-border);
        }

        .badge-status {
            padding: 4px 10px; border-radius: 20px;
            font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
        }
        .st-confirmada { background: rgba(34,197,94,0.15); color: #86efac; border: 1px solid rgba(34,197,94,0.3); }
        .st-presente   { background: rgba(79,70,229,0.15); color: #818cf8; border: 1px solid rgba(79,70,229,0.3); }
        .st-falta      { background: rgba(234,179,8,0.15); color: #fde047; border: 1px solid rgba(234,179,8,0.3); }
        .st-cancelada  { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }

        .empty { text-align: center; color: var(--text-muted); padding: 30px; font-size: 0.85rem; }

        @media (max-width: 768px) {
            body { padding: 15px; }
            .header { flex-direction: column; align-items: stretch; text-align: center; }
            .user-info { justify-content: center; flex-wrap: wrap; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚡ Painel Admin</h1>
            <div class="user-info">
                <div class="avatar"><?= htmlspecialchars(mb_substr($admin['nome_completo'], 0, 1)) ?></div>
                <span class="name"><?= htmlspecialchars($admin['nome_completo']) ?></span>
                <span class="badge">Administrador</span>
                <a href="../logout.php" class="logout-btn" onclick="return confirm('Deseja realmente sair?')">Sair</a>
            </div>
        </div>

        <!-- ===== STATS GERAIS ===== -->
        <h2 class="section-title">Visão Geral</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?= $stats['total_usuarios'] ?></div>
                <div class="stat-label">Total de Usuários</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👤</div>
                <div class="stat-number"><?= $stats['total_usuarios_comuns'] ?></div>
                <div class="stat-label">Alunos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏋️</div>
                <div class="stat-number"><?= $stats['total_instrutores'] ?></div>
                <div class="stat-label">Instrutores</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚙️</div>
                <div class="stat-number"><?= $stats['total_admins'] ?></div>
                <div class="stat-label">Administradores</div>
            </div>
        </div>

        <!-- ===== STATS DE AULAS ===== -->
        <h2 class="section-title">Agenda</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-number"><?= $stats['aulas_agendadas'] ?></div>
                <div class="stat-label">Aulas Futuras</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔔</div>
                <div class="stat-number"><?= $stats['aulas_hoje'] ?></div>
                <div class="stat-label">Aulas Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?= $stats['inscricoes_confirmadas'] ?></div>
                <div class="stat-label">Inscrições Confirmadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?= $stats['taxa_ocupacao'] ?>%</div>
                <div class="stat-label">Ocupação Média</div>
            </div>
        </div>

        <!-- ===== PRÓXIMAS AULAS ===== -->
        <h2 class="section-title">Próximas Aulas</h2>
        <?php if (empty($proximas_aulas)): ?>
            <div class="table-container"><div class="empty">Nenhuma aula futura agendada.</div></div>
        <?php else: ?>
            <div class="aulas-grid">
                <?php foreach ($proximas_aulas as $a): 
                    $cor = cor_mod($a['modalidade'], $cores_mod);
                    $perc = $a['capacidade_maxima'] > 0 
                        ? ($a['ocupadas'] / $a['capacidade_maxima']) * 100 
                        : 0;
                    $hora = date('H:i', strtotime($a['data_aula']));
                    $dataFmt = date('d/m', strtotime($a['data_aula']));
                ?>
                <div class="aula-card" style="--cor: <?= $cor ?>">
                    <div class="aula-modalidade"><?= htmlspecialchars($a['modalidade']) ?></div>
                    <div class="aula-titulo"><?= htmlspecialchars($a['titulo']) ?></div>
                    <div class="aula-info">
                        📅 <strong><?= $dataFmt ?></strong> às <strong><?= $hora ?></strong><br>
                        ⏱ <?= (int)$a['duracao'] ?> min · 📍 <?= htmlspecialchars($a['local'] ?? '—') ?><br>
                        🧑‍🏫 <?= htmlspecialchars($a['instrutor'] ?? 'Sem instrutor') ?>
                    </div>
                    <div class="vagas-bar"><span style="width: <?= $perc ?>%"></span></div>
                    <div class="vagas-label">
                        <?= (int)$a['ocupadas'] ?> / <?= (int)$a['capacidade_maxima'] ?> vagas ocupadas
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ===== INSCRIÇÕES RECENTES ===== -->
        <h2 class="section-title">Inscrições Recentes</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Aluno</th>
                        <th>Aula</th>
                        <th>Modalidade</th>
                        <th>Data da Aula</th>
                        <th>Status</th>
                        <th>Inscrição em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inscricoes_recentes)): ?>
                        <tr><td colspan="7" class="empty">Nenhuma inscrição registrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($inscricoes_recentes as $i): ?>
                        <tr>
                            <td>#<?= (int)$i['id'] ?></td>
                            <td><?= htmlspecialchars($i['aluno']) ?></td>
                            <td><?= htmlspecialchars($i['aula']) ?></td>
                            <td><span class="badge-modalidade"><?= htmlspecialchars($i['modalidade']) ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($i['data_aula'])) ?></td>
                            <td>
                                <span class="badge-status st-<?= htmlspecialchars($i['status']) ?>">
                                    <?= htmlspecialchars($i['status']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($i['data_inscricao'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ===== ÚLTIMOS USUÁRIOS ===== -->
        <h2 class="section-title">Últimos Usuários Cadastrados</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Modalidade</th>
                        <th>Tipo</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimos_usuarios)): ?>
                        <tr><td colspan="6" class="empty">Nenhum usuário cadastrado ainda.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ultimos_usuarios as $usuario): ?>
                        <tr>
                            <td>#<?= (int)$usuario['id'] ?></td>
                            <td><?= htmlspecialchars($usuario['nome_completo']) ?></td>
                            <td><?= htmlspecialchars($usuario['email']) ?></td>
                            <td><span class="badge-modalidade"><?= htmlspecialchars($usuario['modalidade']) ?></span></td>
                            <td>
                                <span class="badge-tipo badge-<?= htmlspecialchars($usuario['tipo_usuario']) ?>">
                                    <?php
                                        $tipos = [
                                            'admin'     => 'Admin',
                                            'instrutor' => 'Instrutor',
                                            'usuario'   => 'Usuário',
                                        ];
                                        echo $tipos[$usuario['tipo_usuario']] ?? $usuario['tipo_usuario'];
                                    ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($usuario['data_criacao'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>