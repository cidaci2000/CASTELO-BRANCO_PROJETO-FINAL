// ============================================================
// ESTADO GLOBAL
// ============================================================
let aulaSelecionada = null;
const diasSemana = ['SEG','TER','QUA','QUI','SEX','SAB','DOM'];

// Cache das aulas carregadas (id -> dados) para não precisar
// re-serializar objetos no HTML
let aulasCache = {};

// ============================================================
// CARREGAR AGENDA
// ============================================================
async function carregarAgenda() {
    const grade = document.getElementById('grade-aulas');
    if (!grade) {
        console.error('Elemento #grade-aulas não encontrado!');
        return;
    }

    grade.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;">Carregando...</div>';

    const params = new URLSearchParams({
        unidade:    document.getElementById('filtro-unidade').value,
        modalidade: document.getElementById('filtro-modalidade').value,
        nivel:      document.getElementById('filtro-nivel').value
    });

    try {
        const res = await fetch(`api/get_aulas.php?${params}`);
        const texto = await res.text(); // pega como texto primeiro

        // Se o PHP retornar erro HTML, avisa no console
        let data;
        try {
            data = JSON.parse(texto);
        } catch (e) {
            console.error('Resposta do servidor não é JSON:', texto);
            throw new Error('Servidor retornou resposta inválida. Verifique o PHP.');
        }

        if (!data.sucesso) throw new Error(data.mensagem || 'Erro ao carregar');

        renderizarGrade(data);
    } catch (e) {
        console.error('Erro carregarAgenda:', e);
        grade.innerHTML = `<div style="grid-column:1/-1;color:#f87171;text-align:center;padding:40px;">
            Erro: ${e.message}
        </div>`;
    }
}

// ============================================================
// RENDERIZAR GRADE
// ============================================================
function renderizarGrade(data) {
    const grade = document.getElementById('grade-aulas');
    aulasCache = {}; // limpa cache

    let html = '<div></div>'; // canto vazio

    // Cabeçalho dos dias
    diasSemana.forEach(d => {
        html += `<div class="day">${d}</div>`;
    });

    if (!data.horarios || data.horarios.length === 0) {
        grade.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted);">Nenhuma aula encontrada.</div>';
        return;
    }

    // Linhas por horário
    data.horarios.forEach(hora => {
        html += `<div class="time">${hora}</div>`;

        for (let dia = 1; dia <= 7; dia++) {
            const aulas = data.grade[dia]?.[hora] || [];

            if (aulas.length === 0) {
                html += `<div class="class empty">--</div>`;
            } else {
                aulas.forEach(aula => {
                    // Guarda no cache pelo id
                    aulasCache[aula.id] = aula;

                    const esgotada = aula.vagas_disponiveis <= 0;
                    const classe = esgotada ? 'class empty' : 'class highlight';

                    html += `
                        <div class="${classe}"
                             data-aula-id="${aula.id}"
                             style="cursor:${esgotada ? 'not-allowed' : 'pointer'};">
                            <span class="class-name">${escapeHtml(aula.titulo)}</span>
                            <span class="instructor">${escapeHtml(aula.instrutor || '')}</span>
                            <span class="instructor" style="color:var(--neon-glow);font-size:10px;margin-top:4px;">
                                ${esgotada ? 'ESGOTADO' : `${aula.vagas_disponiveis} vagas`}
                            </span>
                        </div>`;
                });
            }
        }
    });

    grade.innerHTML = html;

    // ✅ AQUI ESTÁ A CORREÇÃO: adiciona os listeners DEPOIS de renderizar
    grade.querySelectorAll('[data-aula-id]').forEach(el => {
        el.addEventListener('click', () => {
            const id = parseInt(el.dataset.aulaId, 10);
            const aula = aulasCache[id];
            if (aula && aula.vagas_disponiveis > 0) {
                abrirModal(aula);
            }
        });
    });
}

// Helper para evitar XSS
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ============================================================
// MODAL
// ============================================================
function abrirModal(aula) {
    aulaSelecionada = aula;

    const modal = document.getElementById('modal-inscricao');
    if (!modal) {
        console.error('Modal #modal-inscricao não encontrado!');
        return;
    }

    document.getElementById('modal-titulo').textContent = aula.titulo;
    document.getElementById('modal-info').innerHTML = `
        <strong>Instrutor:</strong> ${escapeHtml(aula.instrutor || 'N/A')}<br>
        <strong>Local:</strong> ${escapeHtml(aula.local || 'N/A')}<br>
        <strong>Duração:</strong> ${aula.duracao} min<br>
        <strong>Vagas:</strong> ${aula.vagas_disponiveis}/${aula.capacidade_maxima}
    `;
    modal.style.display = 'flex';
}

function fecharModal() {
    document.getElementById('modal-inscricao').style.display = 'none';
    aulaSelecionada = null;
}

// Expõe globalmente para o onclick="fecharModal()" do HTML
window.fecharModal = fecharModal;
window.abrirModal = abrirModal;

// ============================================================
// INSCRIÇÃO
// ============================================================
async function confirmarInscricao() {
    if (!aulaSelecionada) {
        alert('Nenhuma aula selecionada.');
        return;
    }

    const btn = event?.target;
    if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }

    try {
        const res = await fetch('api/inscrever.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({aula_id: aulaSelecionada.id})
        });
        const data = await res.json();
        alert(data.mensagem);
        fecharModal();
        if (data.sucesso) carregarAgenda();
    } catch (e) {
        console.error(e);
        alert('Erro ao inscrever: ' + e.message);
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Confirmar'; }
    }
}
window.confirmarInscricao = confirmarInscricao;

// ============================================================
// FILTROS
// ============================================================
async function carregarFiltros() {
    try {
        const res = await fetch('api/get_filtros.php');
        const data = await res.json();
        if (!data.sucesso) return;

        const select = document.getElementById('filtro-modalidade');
        // Limpa opções (exceto "Todas")
        select.innerHTML = '<option value="todas">Modalidade: Todas</option>';

        data.modalidades.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m;
            opt.textContent = m;
            select.appendChild(opt);
        });
    } catch (e) {
        console.warn('Não foi possível carregar filtros dinâmicos:', e);
    }
}

// ============================================================
// INICIALIZAÇÃO — espera o DOM estar pronto
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    // Listeners dos filtros
    const fUnidade = document.getElementById('filtro-unidade');
    const fMod = document.getElementById('filtro-modalidade');
    const fNiv = document.getElementById('filtro-nivel');

    if (fUnidade) fUnidade.addEventListener('change', carregarAgenda);
    if (fMod)     fMod.addEventListener('change', carregarAgenda);
    if (fNiv)     fNiv.addEventListener('change', carregarAgenda);

    // Carrega dados
    carregarFiltros();
    carregarAgenda();
});