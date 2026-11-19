<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// ============================================================
// Inicializar variáveis
// ============================================================
$error       = '';
$success     = '';
$form_origem = $_POST['acao'] ?? $_GET['form'] ?? 'login';

// Se já estiver logado, redireciona direto
if (isset($_SESSION['usuario'])) {
    switch ($_SESSION['usuario']['tipo']) {
        case 'admin':    header('Location: ../admin/dashboard.php');    exit;
        case 'instrutor':header('Location: ../instrutor/dashboard.php');exit;
        default:         header('Location: ../aluno/dashboard.php');    exit;
    }
}

// ============================================================
// PROCESSAMENTO DO POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? 'login';

    /* ==================== LOGIN ==================== */
    if ($acao === 'login') {
        $form_origem = 'login';
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            $error = 'Informe e-mail e senha.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'E-mail inválido.';
        } else {
            $stmt = $conn->prepare(
                "SELECT id, nome_completo, email, senha, tipo_usuario
                 FROM usuarios WHERE email = ? LIMIT 1"
            );
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user || !password_verify($senha, $user['senha'])) {
                $error = 'E-mail ou senha incorretos.';
            } else {
                $_SESSION['usuario'] = [
                    'id'    => $user['id'],
                    'nome'  => $user['nome_completo'],
                    'tipo'  => $user['tipo_usuario'],
                    'email' => $user['email'],
                ];

                switch ($user['tipo_usuario']) {
                    case 'admin':
                        header('Location: ../admin/dashboard.php'); break;
                    case 'instrutor':
                        header('Location: ../instrutor/dashboard.php'); break;
                    default:
                        header('Location: ../aluno/dashboard.php'); break;
                }
                exit;
            }
        }
    }

    /* ==================== CADASTRO ==================== */
    elseif ($acao === 'cadastro') {
        $form_origem = 'cadastro';

        $nome_completo   = trim($_POST['nome_completo'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $senha           = $_POST['senha'] ?? '';
        $confirmar_senha = $_POST['confirmar_senha'] ?? '';
        $modalidade      = $_POST['modalidade'] ?? '';
        $telefone        = trim($_POST['telefone'] ?? '');
        $data_nascimento = $_POST['data_nascimento'] ?? '';
        $cpf             = trim($_POST['cpf'] ?? '');
        $tipo_usuario    = $_POST['tipo_usuario'] ?? 'usuario';

        if (!in_array($tipo_usuario, ['usuario', 'instrutor'], true)) {
            $tipo_usuario = 'usuario';
        }

        if (empty($nome_completo) || empty($email) || empty($senha) || empty($modalidade)) {
            $error = 'Nome, E-mail, Senha e Modalidade são obrigatórios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'E-mail inválido.';
        } elseif ($senha !== $confirmar_senha) {
            $error = 'As senhas não coincidem.';
        } elseif (strlen($senha) < 6) {
            $error = 'A senha deve ter pelo menos 6 caracteres.';
        } elseif (!empty($telefone) && !preg_match('/^\(?\d{2}\)?\s?\d{4,5}-?\d{4}$/', $telefone)) {
            $error = 'Telefone inválido. Use o formato (XX) XXXXX-XXXX';
        } elseif (!empty($cpf) && !preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $cpf)) {
            $error = 'CPF inválido. Use o formato XXX.XXX.XXX-XX';
        } elseif (!empty($data_nascimento)) {
            $dt = DateTime::createFromFormat('Y-m-d', $data_nascimento);
            if (!$dt || $dt->format('Y-m-d') !== $data_nascimento) {
                $error = 'Data de nascimento inválida.';
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $error = 'Este e-mail já está cadastrado.';
            $stmt->close();
        }

        if (empty($error) && !empty($cpf)) {
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE cpf = ? LIMIT 1");
            $stmt->bind_param("s", $cpf);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $error = 'Este CPF já está cadastrado.';
            $stmt->close();
        }

        if (empty($error)) {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO usuarios
                 (nome_completo, email, senha, telefone, data_nascimento, cpf, modalidade, tipo_usuario)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssssssss",
                $nome_completo, $email, $senha_hash,
                $telefone, $data_nascimento, $cpf,
                $modalidade, $tipo_usuario
            );

            if ($stmt->execute()) {
                $success     = 'Conta criada com sucesso! Faça login.';
                $form_origem = 'login';
            } else {
                $error = 'Erro ao criar conta. Tente novamente.';
            }
            $stmt->close();
        }
    }
}

$old = $_POST ?? [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SportLife — Acesso</title>

    <!-- Fontes usadas no CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&family=Bebas+Neue&family=Syncopate:wght@400;700&display=swap" rel="stylesheet">

    <style>
        /* ==================== VARIÁVEIS E RESET ==================== */
        :root {
            --primary-purple: #4f46e5;
            --deep-purple: #1e1b4b;
            --neon-glow: #818cf8;
            --bg-black: #0a0a0f;
            --card-gray: rgba(255, 255, 255, 0.03);
            --border: rgba(79, 70, 229, 0.15);
            --text-muted: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-black);
            color: white;
            overflow-x: hidden;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* ==================== GLOW DE FUNDO ==================== */
        .glow {
            position: fixed;
            width: 400px;
            height: 400px;
            background: #3730a3;
            filter: blur(150px);
            border-radius: 50%;
            opacity: 0.15;
            z-index: -1;
        }
        .glow.top    { top: -100px; left: -100px; }
        .glow.bottom { bottom: -100px; right: -100px; }

        /* ==================== CABEÇALHO ==================== */
        header {
            padding: 30px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 100;
            background: rgba(10, 10, 15, 0.5);
            backdrop-filter: blur(10px);
        }

        .logo {
            font-family: 'Syncopate', sans-serif;
            font-size: 1.5rem;
            letter-spacing: 4px;
            background: linear-gradient(to right, #e2e8f0, var(--primary-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }

        nav {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        nav a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 2px;
            transition: 0.3s;
        }

        nav a:hover { color: var(--primary-purple); }

        /* ==================== CARD PRINCIPAL ==================== */
        .auth-wrapper {
            width: 100%;
            max-width: 460px;
            margin: 100px auto 40px;
            z-index: 10;
            position: relative;
        }

        .auth-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(2.5rem, 8vw, 4rem);
            line-height: 0.9;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-align: center;
            margin-bottom: 10px;
        }

        .auth-title span {
            display: block;
            background: linear-gradient(90deg, #fff, var(--primary-purple), #fff);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shine 3s linear infinite;
        }

        @keyframes shine {
            to { background-position: 200% center; }
        }

        .auth-subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 30px;
        }

        .form-box {
            background: var(--card-gray);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px 35px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 40px rgba(79, 70, 229, 0.1);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-box h2 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 25px;
            text-align: center;
            color: var(--neon-glow);
        }

        /* ==================== INPUTS ==================== */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .form-box input,
        .form-box select {
            width: 100%;
            padding: 14px 16px;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: white;
            font-size: 0.95rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: 0.3s;
        }

        .form-box input::placeholder {
            color: #475569;
        }

        .form-box input:focus,
        .form-box select:focus {
            outline: none;
            border-color: var(--primary-purple);
            background: rgba(79, 70, 229, 0.05);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }

        .form-box select option {
            background: var(--bg-black);
            color: white;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* ==================== BOTÕES ==================== */
        .btn {
            width: 100%;
            padding: 16px;
            border-radius: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 0.8rem;
            cursor: pointer;
            border: none;
            transition: 0.4s;
            margin-top: 10px;
        }

        .btn-primary {
            background: var(--primary-purple);
            color: white;
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.4);
        }

        .btn-primary:hover {
            background: white;
            color: black;
            transform: translateY(-3px);
        }

        /* ==================== MENSAGENS ==================== */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border-color: #22c55e;
            color: #86efac;
        }

        /* ==================== SWITCH DE FORM ==================== */
        .form-switch {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid var(--border);
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .form-switch a {
            color: var(--neon-glow);
            text-decoration: none;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .form-switch a:hover {
            color: white;
            text-shadow: 0 0 10px var(--primary-purple);
        }

        /* ==================== RESPONSIVO ==================== */
        @media (max-width: 480px) {
            header {
                padding: 15px 5%;
            }
            .logo { font-size: 1rem; letter-spacing: 2px; }
            nav a { font-size: 0.65rem; letter-spacing: 1px; }
            .form-box { padding: 30px 20px; }
            .form-row { grid-template-columns: 1fr; }
            .auth-wrapper { margin-top: 90px; }
        }
    </style>
</head>
<body>

<!-- Glows de fundo -->
<div class="glow top"></div>
<div class="glow bottom"></div>

<!-- Cabeçalho -->
<header>
    <a href="../index.php" class="logo">SPORTLIFE</a>
    <nav>
        <a href="../index.php">Home</a>
        <a href="?form=login">Entrar</a>
        <a href="?form=cadastro">Cadastrar</a>
    </nav>
</header>

<!-- Conteúdo -->
<div class="auth-wrapper">

    <h1 class="auth-title">
        Bem-vindo
        <span>SportLife</span>
    </h1>
    <p class="auth-subtitle">Acesse sua conta ou crie uma nova</p>

    <!-- ============ FORM LOGIN ============ -->
    <div id="form-login" class="form-box" style="<?= $form_origem === 'login' ? '' : 'display:none' ?>">
        <h2>Entrar</h2>

        <?php if ($error && $form_origem === 'login'): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="acao" value="login">

            <div class="form-group">
                <label for="login-email">E-mail</label>
                <input type="email" id="login-email" name="email" placeholder="seu@email.com" required
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="login-senha">Senha</label>
                <input type="password" id="login-senha" name="senha" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>

        <p class="form-switch">
            Não tem conta? <a href="?form=cadastro">Cadastre-se</a>
        </p>
    </div>

    <!-- ============ FORM CADASTRO ============ -->
    <div id="form-cadastro" class="form-box" style="<?= $form_origem === 'cadastro' ? '' : 'display:none' ?>">
        <h2>Criar Conta</h2>

        <?php if ($error && $form_origem === 'cadastro'): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="acao" value="cadastro">

            <div class="form-group">
                <label for="cad-nome">Nome completo</label>
                <input type="text" id="cad-nome" name="nome_completo" placeholder="Seu nome" required
                       value="<?= htmlspecialchars($old['nome_completo'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="cad-email">E-mail</label>
                <input type="email" id="cad-email" name="email" placeholder="seu@email.com" required
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="cad-senha">Senha</label>
                    <input type="password" id="cad-senha" name="senha" placeholder="••••••••" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="cad-conf">Confirmar</label>
                    <input type="password" id="cad-conf" name="confirmar_senha" placeholder="••••••••" required minlength="6">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="cad-tel">Telefone</label>
                    <input type="text" id="cad-tel" name="telefone" placeholder="(XX) XXXXX-XXXX"
                           value="<?= htmlspecialchars($old['telefone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="cad-nasc">Nascimento</label>
                    <input type="date" id="cad-nasc" name="data_nascimento"
                           value="<?= htmlspecialchars($old['data_nascimento'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="cad-cpf">CPF</label>
                <input type="text" id="cad-cpf" name="cpf" placeholder="XXX.XXX.XXX-XX"
                       value="<?= htmlspecialchars($old['cpf'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="cad-modal">Modalidade</label>
                    <select id="cad-modal" name="modalidade" required>
                        <option value="">Selecione</option>
                        <option value="musculacao" <?= ($old['modalidade'] ?? '') === 'musculacao' ? 'selected' : '' ?>>Musculação</option>
                        <option value="crossfit"   <?= ($old['modalidade'] ?? '') === 'crossfit'   ? 'selected' : '' ?>>CrossFit</option>
                        <option value="funcional"  <?= ($old['modalidade'] ?? '') === 'funcional'  ? 'selected' : '' ?>>Funcional</option>
                        <option value="yoga"       <?= ($old['modalidade'] ?? '') === 'yoga'       ? 'selected' : '' ?>>Yoga</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cad-tipo">Tipo</label>
                    <select id="cad-tipo" name="tipo_usuario" required>
                        <option value="usuario"   <?= ($old['tipo_usuario'] ?? '') === 'usuario'   ? 'selected' : '' ?>>Aluno</option>
                        <option value="instrutor" <?= ($old['tipo_usuario'] ?? '') === 'instrutor' ? 'selected' : '' ?>>Instrutor</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Criar Conta</button>
        </form>

        <p class="form-switch">
            Já tem conta? <a href="?form=login">Entrar</a>
        </p>
    </div>

</div>

</body>
</html>