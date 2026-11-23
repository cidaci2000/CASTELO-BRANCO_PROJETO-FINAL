<?php
// ============================================================
// admin/nova_aula.php — Cadastrar Nova Aula
// ============================================================
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
exigir_login(['admin']);

$msg = ''; $msg_tipo = 'success';

// -------- POST: Criar Aula --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo        = trim($_POST['titulo'] ?? '');
    $descricao     = trim($_POST['descricao'] ?? '');
    $modalidade    = trim($_POST['modalidade'] ?? '');
    $instrutor_id  = (int)($_POST['instrutor_id'] ?? 0);
    $data_aula     = $_POST['data_aula'] ?? '';
    $duracao       = (int)($_POST['duracao'] ?? 60);
    $capacidade    = (int)($_POST['capacidade_maxima'] ?? 20);
    $local         = trim($_POST['local'] ?? '');

    if ($titulo === '' || $modalidade === '' || $instrutor_id <= 0 || $data_aula === '') {
        $msg = 'Preencha todos os campos obrigatórios (Título, Modalidade, Instrutor, Data).'; $msg_tipo = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO aulas (titulo, descricao, modalidade, instrutor_id, data_aula, duracao, capacidade_maxima, vagas_disponiveis, local, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'agendada')
            ");
            $stmt->execute([
                $titulo, $descricao, $modalidade, $instrutor_id, $data_aula, 
                $duracao, $capacidade, $capacidade, $local
            ]);
            $msg = 'Aula cadastrada com sucesso!';
            // Limpa o formulário
            $_POST = []; 
        } catch (PDOException $e) {
            $msg = 'Erro ao cadastrar a aula. Tente novamente.'; $msg_tipo = 'error';
        }
    }
}

// Buscar modalidades e instrutores para o formulário
$modalidades = $pdo->query("SELECT nome FROM modalidades WHERE ativo = 1 ORDER BY nome ASC")->fetchAll(PDO::FETCH_COLUMN);
$instrutores = $pdo->query("SELECT id, nome_completo FROM usuarios WHERE tipo_usuario = 'instrutor' AND ativo = 1 ORDER BY nome_completo ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Aula | SportLife Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&family=Bebas+Neue&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-glow: rgba(79, 70, 229, 0.4);
            --bg-dark: #06060a;
            --card-bg: rgba(10, 10, 15, 0.7);
            --card-border: rgba(255, 255, 255, 0.06);
            --text-main: #f8fafc;
            --text-muted: #64748b;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-dark); color: var(--text-main); min-height: 100vh; padding: 30px; display: flex; justify-content: center; align-items: flex-start; }
        .container { max-width: 600px; width: 100%; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-family: 'Bebas Neue', sans-serif; font-size: 2.5rem; background: linear-gradient(180deg, #ffffff 20%, #818cf8 65%, #4f46e5 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .btn-back { color: var(--text-muted); text-decoration: none; font-size: 0.85rem; transition: 0.3s; padding: 8px 16px; border: 1px solid var(--card-border); border-radius: 8px; }
        .btn-back:hover { color: var(--primary); border-color: var(--primary); }

        .card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 35px; }
        
        .alert { padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; margin-bottom: 25px; border-left: 4px solid; }
        .alert-success { background: rgba(34,197,94,0.1); border-color: #22c55e; color: #86efac; }
        .alert-error   { background: rgba(239,68,68,0.1); border-color: #ef4444; color: #fca5a5; }

        .input-group { margin-bottom: 18px; display: flex; flex-direction: column; gap: 6px; }
        .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        input, select, textarea {
            width: 100%; padding: 14px 16px; border-radius: 12px;
            border: 1px solid var(--card-border); background: rgba(255,255,255,0.02);
            color: var(--text-main); font-size: 0.95rem; outline: none; transition: 0.3s;
        }
        textarea { resize: vertical; min-height: 80px; }
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary); background: rgba(79,70,229,0.04); box-shadow: 0 0 0 4px var(--primary-glow);
        }
        select option { background: #1a1a2e; color: white; }
        
        button {
            width: 100%; padding: 16px; margin-top: 10px; background: var(--primary);
            border: none; border-radius: 12px; color: white; font-size: 0.95rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
            cursor: pointer; transition: 0.3s; box-shadow: 0 4px 20px rgba(79,70,229,0.3);
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(79,70,229,0.5); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📅 Nova Aula</h1>
            <a href="dashboard.php" class="btn-back">← Voltar ao Painel</a>
        </div>

        <div class="card">
            <?php if ($msg): ?>
                <div class="alert alert-<?= $msg_tipo ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label>Título da Aula *</label>
                    <input type="text" name="titulo" required placeholder="Ex: Treino Funcional Intenso" value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>">
                </div>

                <div class="input-group">
                    <label>Descrição</label>
                    <textarea name="descricao" placeholder="Detalhes da aula..."><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
                </div>

                <div class="input-row">
                    <div class="input-group">
                        <label>Modalidade *</label>
                        <select name="modalidade" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($modalidades as $mod): ?>
                                <option value="<?= htmlspecialchars($mod) ?>" <?= (($_POST['modalidade'] ?? '') === $mod) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mod) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Instrutor *</label>
                        <select name="instrutor_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($instrutores as $ins): ?>
                                <option value="<?= $ins['id'] ?>" <?= (($_POST['instrutor_id'] ?? '') == $ins['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ins['nome_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="input-group">
                    <label>Data e Hora *</label>
                    <input type="datetime-local" name="data_aula" required value="<?= htmlspecialchars($_POST['data_aula'] ?? '') ?>">
                </div>

                <div class="input-row">
                    <div class="input-group">
                        <label>Duração (min)</label>
                        <input type="number" name="duracao" value="<?= htmlspecialchars($_POST['duracao'] ?? '60') ?>" min="15" step="15">
                    </div>
                    <div class="input-group">
                        <label>Capacidade (vagas)</label>
                        <input type="number" name="capacidade_maxima" value="<?= htmlspecialchars($_POST['capacidade_maxima'] ?? '20') ?>" min="1">
                    </div>
                </div>

                <div class="input-group">
                    <label>Local</label>
                    <input type="text" name="local" placeholder="Ex: Sala 1, Quadra, etc." value="<?= htmlspecialchars($_POST['local'] ?? '') ?>">
                </div>

                <button type="submit">Cadastrar Aula</button>
            </form>
        </div>
    </div>
</body>
</html>