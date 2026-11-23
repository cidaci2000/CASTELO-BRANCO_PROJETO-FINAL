<?php
require_once __DIR__ . '/../config/auth.php';
exigir_login(['instrutor']);

$user = usuario_logado();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Instrutor — SportLife</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&family=Bebas+Neue&family=Syncopate:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-purple: #4f46e5;
            --deep-purple: #1e1b4b;
            --neon-glow: #818cf8;
            --bg-black: #0a0a0f;
            --card-gray: rgba(255, 255, 255, 0.03);
            --border: rgba(79, 70, 229, 0.15);
            --text-muted: #94a3b8;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-black); color: white; line-height: 1.6; min-height: 100vh; }
        .glow { position: fixed; width: 400px; height: 400px; background: #3730a3; filter: blur(150px); border-radius: 50%; opacity: 0.12; z-index: -1; }
        .glow.top { top: -100px; right: -100px; }
        .glow.bottom { bottom: -100px; left: -100px; }

        header {
            padding: 20px 5%; display: flex; justify-content: space-between; align-items: center;
            background: rgba(10,10,15,0.6); backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100;
        }
        .logo {
            font-family: 'Syncopate', sans-serif; font-size: 1.3rem; letter-spacing: 4px;
            background: linear-gradient(to right, #e2e8f0, var(--primary-purple));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-decoration: none;
        }
        .user-area { display: flex; align-items: center; gap: 15px; }
        .user-greeting { color: var(--text-muted); font-size: 0.8rem; letter-spacing: 1px; }
        .user-greeting strong { color: white; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 50px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .badge-instrutor { background: rgba(59,130,246,0.15); color: #93c5fd; border: 1px solid #3b82f6; }
        .btn-logout {
            background: transparent; color: rgba(255,255,255,0.7);
            border: 1px solid var(--border); padding: 8px 18px; border-radius: 50px;
            font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px;
            transition: 0.3s; cursor: pointer; text-decoration: none;
        }
        .btn-logout:hover { color: #ef4444; border-color: #ef4444; }

        .container { max-width: 1200px; margin: 0 auto; padding: 50px 5%; }
        .page-title {
            font-family: 'Bebas Neue', sans-serif; font-size: clamp(2.5rem, 6vw, 4rem);
            letter-spacing: 2px; text-transform: uppercase; margin-bottom: 8px;
        }
        .page-title span { color: var(--primary-purple); }
        .page-sub { color: var(--text-muted); margin-bottom: 40px; letter-spacing: 1px; font-size: 0.85rem; }

        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 50px; }
        .card {
            background: var(--card-gray); border: 1px solid var(--border);
            padding: 30px; border-radius: 16px; transition: 0.4s;
        }
        .card:hover { border-color: var(--primary-purple); transform: translateY(-5px); }
        .card h3 {
            font-family: 'Bebas Neue', sans-serif; font-size: 1.5rem;
            letter-spacing: 2px; text-transform: uppercase; margin-bottom: 10px; color: var(--neon-glow);
        }
        .card p { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 20px; }
        .btn {
            display: inline-block; padding: 12px 24px; border-radius: 10px;
            font-weight: 800; text-transform: uppercase; letter-spacing: 2px;
            font-size: 0.7rem; cursor: pointer; border: none; transition: 0.4s;
            text-decoration: none; color: white; background: var(--primary-purple);
            box-shadow: 0 4px 20px rgba(79,70,229,0.3);
        }
        .btn:hover { background: white; color: black; transform: translateY(-3px); }

        .section-title {
            font-family: 'Bebas Neue', sans-serif; font-size: 1.8rem;
            letter-spacing: 2px; text-transform: uppercase; margin-bottom: 20px;
            border-left: 3px solid var(--primary-purple); padding-left: 15px;
        }

        .schedule-list { background: var(--card-gray); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
        .schedule-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 25px; border-bottom: 1px solid var(--border);
        }
        .schedule-item:last-child { border-bottom: none; }
        .schedule-time { font-family: 'Bebas Neue', sans-serif; font-size: 1.5rem; color: var(--neon-glow); letter-spacing: 2px; min-width: 80px; }
        .schedule-info strong { display: block; font-size: 0.95rem; }
        .schedule-info span { color: var(--text-muted); font-size: 0.75rem; }
        .tag { display: inline-block; padding: 3px 10px; border-radius: 50px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; background: rgba(79,70,229,0.15); color: var(--neon-glow); }

        @media (max-width: 640px) {
            header { flex-direction: column; gap: 12px; text-align: center; }
            .schedule-item { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>
<div class="glow top"></div>
<div class="glow bottom"></div>

<header>
    <a href="dashboard.php" class="logo">SPORTLIFE</a>
    <div class="user-area">
        <span class="user-greeting">Olá, <strong><?= htmlspecialchars($user['nome']) ?></strong></span>
        <span class="badge badge-instrutor">Instrutor</span>
        <a href="../php/logout.php" class="btn-logout">Sair</a>
    </div>
</header>

<div class="container">
    <h1 class="page-title">Painel <span>Instrutor</span></h1>
    <p class="page-sub">Gerencie suas aulas e alunos</p>

    <div class="cards-grid">
        <div class="card">
            <h3>Minhas Aulas</h3>
            <p>Veja e gerencie as aulas agendadas para hoje e esta semana.</p>
            <a href="#" class="btn">Ver aulas</a>
        </div>
        <div class="card">
            <h3>Meus Alunos</h3>
            <p>Acompanhe o progresso e a frequência dos seus alunos.</p>
            <a href="#" class="btn">Ver alunos</a>
        </div>
        <div class="card">
            <h3>Treinos</h3>
            <p>Crie e edite planos de treino personalizados.</p>
            <a href="#" class="btn">Gerenciar</a>
        </div>
    </div>

    <h2 class="section-title">Agenda de hoje</h2>
    <div class="schedule-list">
        <div class="schedule-item">
            <div class="schedule-time">07:00</div>
            <div class="schedule-info">
                <strong>Musculação — Turma A</strong>
                <span>12 alunos · Sala 1</span>
            </div>
            <span class="tag">Confirmada</span>
        </div>
        <div class="schedule-item">
            <div class="schedule-time">09:30</div>
            <div class="schedule-info">
                <strong>Funcional — Turma B</strong>
                <span>8 alunos · Área externa</span>
            </div>
            <span class="tag">Confirmada</span>
        </div>
        <div class="schedule-item">
            <div class="schedule-time">18:00</div>
            <div class="schedule-info">
                <strong>CrossFit — Turma C</strong>
                <span>15 alunos · Box</span>
            </div>
            <span class="tag">Confirmada</span>
        </div>
    </div>
</div>
</body>
</html>