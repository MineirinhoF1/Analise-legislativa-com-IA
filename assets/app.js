/* Resumo Transparente — lógica do frontend */

const $ = (s) => document.querySelector(s);
const $$ = (s) => document.querySelectorAll(s);
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

function apiFetch(url, options = {}) {
    const headers = new Headers(options.headers || {});
    if (CSRF_TOKEN) headers.set('X-CSRF-Token', CSRF_TOKEN);
    return fetch(url, { ...options, headers });
}

/* Toast notifications (substitui alert) */
function toast(msg, tipo = '') {
    let wrap = $('.toast-wrap');
    if (!wrap) { wrap = document.createElement('div'); wrap.className = 'toast-wrap'; document.body.appendChild(wrap); }
    const el = document.createElement('div');
    const ico = tipo === 'ok' ? '✅' : tipo === 'err' ? '⚠️' : 'ℹ️';
    el.className = 'toast ' + tipo;
    el.innerHTML = `<span>${ico}</span><span>${esc(msg)}</span>`;
    wrap.appendChild(el);
    setTimeout(() => { el.classList.add('fade-out'); setTimeout(() => el.remove(), 300); }, 3800);
}

const PROVIDERS = {
    anthropic: 'Claude (Anthropic)',
    openai: 'OpenAI (GPT / Codex)',
    deepseek: 'DeepSeek',
    google: 'Google Gemini',
};

const MODEL_LABELS = {
    'claude-fable-5': 'Claude Fable 5 - mais capaz',
    'claude-opus-4-8': 'Claude Opus 4.8 - raciocínio complexo',
    'claude-sonnet-4-6': 'Claude Sonnet 4.6 - equilibrado',
    'claude-haiku-4-5': 'Claude Haiku 4.5 - rápido',
    'gpt-5.5': 'GPT-5.5 - flagship',
    'gpt-5.4': 'GPT-5.4 - avançado',
    'gpt-5.4-mini': 'GPT-5.4 mini - custo/velocidade',
    'gpt-5.4-nano': 'GPT-5.4 nano - mais leve',
    'deepseek-v4-pro': 'DeepSeek V4 Pro - mais completo',
    'deepseek-v4-flash': 'DeepSeek V4 Flash - rápido',
    'gemini-3.5-flash': 'Gemini 3.5 Flash - estável atual',
    'gemini-flash-latest': 'Gemini Flash Latest - alias mais recente',
    'gemini-2.5-pro': 'Gemini 2.5 Pro - raciocínio',
    'gemini-2.5-flash': 'Gemini 2.5 Flash - custo/velocidade',
    'gemini-2.5-flash-lite': 'Gemini 2.5 Flash-Lite - econômico',
};

const PROVIDER_HELP = {
    anthropic: 'Use quando quiser análises longas e conservadoras.',
    openai: 'Use para respostas rápidas e boa aderência ao JSON.',
    deepseek: 'Use deepseek-chat para análise geral ou deepseek-reasoner para raciocínio mais forte.',
    google: 'Use Gemini Flash para custo/velocidade ou Pro para análises mais completas.',
};

const HISTORICAL_AIS = {
    1: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-01-64.htm', titulo: 'Ato Institucional nº 1', ano: 1964 },
    2: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-02-65.htm', titulo: 'Ato Institucional nº 2', ano: 1965 },
    3: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-03-66.htm', titulo: 'Ato Institucional nº 3', ano: 1966 },
    4: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-04-66.htm', titulo: 'Ato Institucional nº 4', ano: 1966 },
    5: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-05-68.htm', titulo: 'Ato Institucional nº 5', ano: 1968 },
    6: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-06-69.htm', titulo: 'Ato Institucional nº 6', ano: 1969 },
    7: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-07-69.htm', titulo: 'Ato Institucional nº 7', ano: 1969 },
    8: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-08-69.htm', titulo: 'Ato Institucional nº 8', ano: 1969 },
    9: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-09-69.htm', titulo: 'Ato Institucional nº 9', ano: 1969 },
    10: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-10-69.htm', titulo: 'Ato Institucional nº 10', ano: 1969 },
    11: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-11-69.htm', titulo: 'Ato Institucional nº 11', ano: 1969 },
    12: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-12-69.htm', titulo: 'Ato Institucional nº 12', ano: 1969 },
    13: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-13-69.htm', titulo: 'Ato Institucional nº 13', ano: 1969 },
    14: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-14-69.htm', titulo: 'Ato Institucional nº 14', ano: 1969 },
    15: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-15-69.htm', titulo: 'Ato Institucional nº 15', ano: 1969 },
    16: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-16-69.htm', titulo: 'Ato Institucional nº 16', ano: 1969 },
    17: { url: 'https://www.planalto.gov.br/ccivil_03/ait/ait-17-69.htm', titulo: 'Ato Institucional nº 17', ano: 1969 }
};

let contexto = null;      // metadados da última busca/extração (leis)
let perfilAtual = null;   // perfil do parlamentar selecionado
let lastParlamentarAnalise = null;
let lastParlamentarMeta = null;
let currentConfig = null;

/* ---------- Tema ---------- */
const THEME_KEY = 'resumo_transparente_theme';

function applyTheme(theme) {
    const isDark = theme === 'dark';
    document.body.classList.toggle('theme-dark', isDark);
    const btn = $('#themeToggle');
    if (btn) btn.innerHTML = isDark ? '<span>☀️</span> Claro' : '<span>🌙</span> Escuro';
}

const savedTheme = localStorage.getItem(THEME_KEY)
    || (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
applyTheme(savedTheme);

$('#themeToggle')?.addEventListener('click', () => {
    const next = document.body.classList.contains('theme-dark') ? 'light' : 'dark';
    localStorage.setItem(THEME_KEY, next);
    applyTheme(next);
});

/* ---------- Navegação lateral ---------- */
function setLeiStep(step) {
    const items = $$('#leiSteps li');
    items.forEach((li, index) => {
        li.classList.toggle('done', index + 1 < step);
        li.classList.toggle('active', index + 1 === step);
    });
}

function updateSourceSummary(kind = '') {
    const box = $('#sourceSummary');
    if (!box) return;
    const chars = $('#texto')?.value.length || 0;
    const title = contexto?.titulo || contexto?.ementa || contexto?.fonte || '';
    const label = title || (chars ? 'Texto colado manualmente' : 'Nenhuma fonte carregada');
    const detail = chars
        ? `${chars.toLocaleString('pt-BR')} caracteres prontos para revisao${kind ? ` - ${kind}` : ''}.`
        : 'Busque por numero na Camara ou use link/texto como alternativa.';
    box.innerHTML = `<strong>${esc(label)}</strong><p>${esc(detail)}</p>`;
    setLeiStep(chars >= 20 ? 2 : 1);
}

const PAGE_INFO = {
    sumario:     { t: 'Sumário do sistema', s: 'Guia funcional sobre módulos, fontes, tipos de proposição, dados parlamentares e conversão dos dados em relatório.' },
    leis:        { t: 'Leis & Proposições', s: 'Busque a proposição por número, revise o texto carregado e gere uma análise objetiva com IA.' },
    parlamentar: { t: 'Parlamentares', s: 'Busque deputados federais, senadores ou cadastros eleitorais do TSE com indicação clara do escopo dos dados.' },
    empresas:    { t: 'Empresas fornecedoras', s: 'Pesquise fornecedores das cotas parlamentares e veja quais deputados aparecem na amostra oficial.' },
    historico:   { t: 'Histórico de análises', s: 'Análises já feitas são salvas aqui. Reabra sem gastar tokens ou atualize quando precisar.' },
};

/* Barra de metadados (versão usada + cache) exibida no topo de cada resultado. */
function metaBar(meta, options = {}) {
    const data = meta.gerado_em ? new Date(meta.gerado_em).toLocaleString('pt-BR') : new Date().toLocaleString('pt-BR');
    const cache = meta.sem_ia
        ? '<span class="cache-tag rec">🔄 dados oficiais atualizados sem IA</span>'
        : meta.cache
        ? '<span class="cache-tag rec">♻️ reaproveitado do histórico (sem gastar tokens)</span>'
        : '<span class="cache-tag new">✨ nova análise</span>';
    const info = meta.sem_ia
        ? `Câmara dos Deputados · sem IA · ${data}`
        : `${PROVIDERS[meta.provedor] || meta.provedor || '—'} · ${esc(meta.modelo || '')} · ${data}`;
    const actions = options.noUpdate
        ? ''
        : options.dualUpdate
        ? `<div class="meta-actions">
            <button class="btn-secondary res-atualizar">🔄 Atualizar dados sem IA</button>
            <button class="btn-primary res-atualizar-ia">🤖 Atualizar com IA</button>
        </div>`
        : '<button class="btn-secondary res-atualizar">🤖 Atualizar com IA</button>';
    return `<div class="meta-bar">
        ${cache}
        <span class="meta-info">${info}</span>
        ${actions}
    </div>`;
}
function setMenuOpen(open) {
    const sidebar = $('#sidebar');
    const toggle = $('#navToggle');
    const backdrop = $('#navBackdrop');
    sidebar?.classList.toggle('open', open);
    toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle?.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    if (toggle) toggle.textContent = open ? '×' : '☰';
    backdrop?.classList.toggle('hidden', !open);
    document.body.classList.toggle('menu-open', open);
}

$$('.nav-item').forEach((b) => b.addEventListener('click', () => {
    $$('.nav-item').forEach((x) => x.classList.remove('active'));
    $$('.view').forEach((x) => x.classList.remove('active'));
    b.classList.add('active');
    const v = b.dataset.view;
    const view = $('#view-' + v);
    if (!view) return;
    view.classList.add('active');
    const info = PAGE_INFO[v];
    if (info) { $('#pageTitle').textContent = info.t; $('#pageSub').textContent = info.s; }
    setMenuOpen(false); // fecha no mobile
    if (v === 'historico') carregarHistorico();
}));

/* Toggle do menu lateral (mobile) */
$('#navToggle')?.addEventListener('click', () => setMenuOpen(!$('#sidebar')?.classList.contains('open')));
$('#navBackdrop')?.addEventListener('click', () => setMenuOpen(false));
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') setMenuOpen(false);
});

/* ---------- Contador de caracteres ---------- */
$('#texto').addEventListener('input', () => {
    const valor = $('#texto').value;
    $('#charCount').textContent = valor.length.toLocaleString('pt-BR');
    if (valor.trim().length) contexto = contexto || { titulo: 'Texto colado manualmente' };
    else contexto = null; // texto apagado: metadados da busca anterior não valem mais
    updateSourceSummary('revisao');
});

/* ---------- Extrair texto de um link ---------- */
$('#btnExtrair').addEventListener('click', async () => {
    const url = $('#b-url').value.trim();
    const box = $('#linkResultado');
    if (!url) { toast('Cole o link da lei/PL/PEC.', 'err'); return; }

    box.classList.remove('hidden');
    box.innerHTML = '⏳ Extraindo conteúdo do link...';
    try {
        const r = await apiFetch('api/extrair.php?url=' + encodeURIComponent(url));
        const j = await r.json();
        if (!j.ok) throw new Error(j.erro);
        const d = j.dados;
        contexto = { fonte: d.fonte, url: d.url, titulo: d.titulo };
        $('#texto').value = d.texto;
        $('#charCount').textContent = d.texto.length.toLocaleString('pt-BR');
        updateSourceSummary('link extraido');
        box.innerHTML = `
            <span class="ok-tag">✓ Conteúdo extraído</span> — <b>${d.titulo}</b><br>
            Fonte: ${d.fonte} · ${d.texto.length.toLocaleString('pt-BR')} caracteres carregados.<br>
            <span class="hint">Revise no campo de texto, se necessário, e clique em "Analisar com IA".</span>`;
    } catch (e) {
        showError(box, e.message);
        contexto = null;
    }
});

/* ---------- Buscar na Câmara ---------- */
function updateSearchStatus(texto, tipo = '') {
    const el = $('#searchStatus');
    if (!el) return;
    el.className = `search-status ${tipo}`;
    el.textContent = texto;
}

function handleTipoChange() {
    const tipo = $('#b-tipo').value;
    const numInput = $('#b-numero');
    const anoInput = $('#b-ano');
    if (tipo === 'AI') {
        numInput.placeholder = 'ex: 5';
        anoInput.disabled = true;
        updateYearForAI();
    } else {
        numInput.placeholder = '1234';
        anoInput.disabled = false;
        if (['1964', '1965', '1966', '1968', '1969'].includes(anoInput.value)) {
            anoInput.value = '';
        }
        const numero = numInput.value, ano = anoInput.value;
        updateSearchStatus(numero && ano ? `${tipo} ${numero}/${ano} pronto para buscar.` : 'Digite tipo, número e ano.', numero && ano ? 'ok' : '');
    }
}

function handleNumeroInputForAI() {
    if ($('#b-tipo').value === 'AI') {
        updateYearForAI();
    }
}

function updateYearForAI() {
    const num = parseInt($('#b-numero').value, 10);
    const anoInput = $('#b-ano');
    const mapping = {
        1: 1964, 2: 1965, 3: 1966, 4: 1966, 5: 1968, 6: 1969, 7: 1969, 8: 1969,
        9: 1969, 10: 1969, 11: 1969, 12: 1969, 13: 1969, 14: 1969, 15: 1969, 16: 1969, 17: 1969
    };
    if (num >= 1 && num <= 17) {
        anoInput.value = mapping[num];
        updateSearchStatus(`AI ${num}/${mapping[num]} pronto para carregar do Planalto.`, 'ok');
    } else {
        anoInput.value = '';
        updateSearchStatus('Digite o número do Ato Institucional (1 a 17).', '');
    }
}

$('#b-tipo')?.addEventListener('change', handleTipoChange);
$('#b-numero')?.addEventListener('input', handleNumeroInputForAI);

['#b-tipo', '#b-numero', '#b-ano'].forEach((sel) => {
    $(sel)?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') buscarProposicao();
    });
    $(sel)?.addEventListener('input', () => {
        const tipo = $('#b-tipo').value, numero = $('#b-numero').value, ano = $('#b-ano').value;
        if (tipo !== 'AI') {
            updateSearchStatus(numero && ano ? `${tipo} ${numero}/${ano} pronto para buscar.` : 'Digite tipo, número e ano.', numero && ano ? 'ok' : '');
        }
    });
});

$('#btnBuscar').addEventListener('click', buscarProposicao);

let buscarProposicaoSeq = 0;
let buscarProposicaoController = null;

async function buscarProposicao() {
    const tipo = $('#b-tipo').value, numero = $('#b-numero').value, ano = $('#b-ano').value;
    const box = $('#buscaResultado');
    if (tipo === 'AI') {
        const num = parseInt(numero, 10);
        if (isNaN(num) || num < 1 || num > 17) {
            toast('Ato Institucional inválido. Informe de 1 a 17.', 'err');
            return;
        }
    } else {
        if (!numero || !ano) { toast('Informe número e ano.', 'err'); return; }
    }

    $('#texto').value = '';
    $('#charCount').textContent = '0';
    contexto = null;
    updateSourceSummary();
    $('#resultado').classList.add('hidden');
    $('#erro').classList.add('hidden');
    $('#emptyState').classList.add('hidden');
    $('#linkResultado').classList.add('hidden');
    $('#linkResultado').innerHTML = '';

    const seq = ++buscarProposicaoSeq;
    if (buscarProposicaoController) buscarProposicaoController.abort();
    buscarProposicaoController = new AbortController();

    if (tipo === 'AI') {
        const num = parseInt(numero, 10);
        const aiData = HISTORICAL_AIS[num];
        box.classList.remove('hidden');
        box.innerHTML = `⏳ Carregando ${aiData.titulo} (${aiData.ano}) do site do Planalto...`;
        updateSearchStatus(`Carregando ${aiData.titulo}...`, 'loading');
        try {
            const params = new URLSearchParams({ url: aiData.url });
            const r = await apiFetch(`api/extrair.php?${params.toString()}`, { signal: buscarProposicaoController.signal });
            const j = await r.json();
            if (seq !== buscarProposicaoSeq) return false;
            if (!j.ok) throw new Error(j.erro);
            const d = j.dados;
            contexto = {
                id: `ai-${num}`,
                tipo: 'AI',
                numero: num,
                ano: aiData.ano,
                ementa: `Texto completo do ${aiData.titulo} publicado oficialmente em ${aiData.ano}.`,
                situacao: 'Histórico (Regime Militar)',
                autores: ['Poder Executivo (Governo Militar)'],
                partidos_autores: [],
                inteiro_teor: aiData.url,
                fonte: 'Portal da Legislação — Planalto',
                texto_base: d.texto
            };
            $('#texto').value = d.texto;
            $('#charCount').textContent = d.texto.length.toLocaleString('pt-BR');
            updateSourceSummary('Ato Institucional (Planalto)');
            box.innerHTML = `
                <span class="ok-tag">✓ Encontrado</span> — <b>${esc(aiData.titulo)} (${esc(aiData.ano)})</b> · Histórico<br>
                <b>Ementa/Descrição:</b> ${esc(contexto.ementa)}<br>
                <b>Fonte oficial:</b> ${externalLink(aiData.url, 'Link no Planalto')}<br>
                <div class="it-desc" style="margin-top: 10px; padding: 10px; border-left: 3px solid var(--border); background: var(--card-bg);">
                    O texto completo do Ato Institucional foi extraído com sucesso e está pronto para análise.
                </div>`;
            updateSearchStatus(`${aiData.titulo} carregado para revisão.`, 'ok');
            return true;
        } catch (e) {
            if (e.name === 'AbortError') return false;
            if (seq !== buscarProposicaoSeq) return false;
            showError(box, e.message);
            updateSourceSummary();
            $('#emptyState').classList.remove('hidden');
            updateSearchStatus('Não foi possível carregar o Ato Institucional.', 'err');
            return false;
        } finally {
            if (seq === buscarProposicaoSeq) buscarProposicaoController = null;
        }
    }

    box.classList.remove('hidden');
    box.innerHTML = '⏳ Buscando proposição...';
    updateSearchStatus(`Buscando ${tipo} ${numero}/${ano}...`, 'loading');
    try {
        const params = new URLSearchParams({ tipo, numero, ano });
        const r = await fetch(`api/buscar.php?${params.toString()}`, { signal: buscarProposicaoController.signal });
        const j = await r.json();
        if (seq !== buscarProposicaoSeq) return false;
        if (!j.ok) throw new Error(j.erro);
        const d = j.dados;
        contexto = d;
        $('#texto').value = d.texto_base;
        $('#charCount').textContent = d.texto_base.length.toLocaleString('pt-BR');
        updateSourceSummary('dados da Camara');
        const timeline = (d.tramitacoes || []).length ? `
            <div class="timeline">
                <b>🕒 Tramitação recente</b>
                ${d.tramitacoes.slice(0, 6).map((t) => `
                    <div class="tl-item">
                        <span class="tl-date">${esc(t.data)}</span>
                        <div><b>${esc(t.orgao)}</b> ${esc(t.situacao)}<div class="it-desc">${esc((t.despacho || '').slice(0, 160))}</div></div>
                    </div>`).join('')}
            </div>` : '';
        const senadoItens = d.senado?.encontrado && Array.isArray(d.senado.itens) ? d.senado.itens : [];
        const senadoBox = senadoItens.length ? `
            <div class="timeline">
                <b>🏛️ Senado Federal</b>
                ${senadoItens.slice(0, 3).map((s) => `
                    <div class="tl-item">
                        <span class="tl-date">${esc(s.data_situacao || s.data_apresentacao || '')}</span>
                        <div><b>${esc(s.identificacao || '')}</b> ${esc(s.situacao || '')}<div class="it-desc">${esc([s.autoria, s.tramitando ? `Tramitando: ${s.tramitando}` : '', s.url_documento ? 'documento disponível' : ''].filter(Boolean).join(' · '))}</div></div>
                    </div>`).join('')}
                ${renderVotacoesSenado(d.senado, true)}
            </div>` : '';
        const votacoesBox = renderVotacoesProposicao(d, true);
        const inteiroTeorUrl = safeUrl(d.inteiro_teor);
        const inteiroTeorAcao = inteiroTeorUrl
            ? d.inteiro_teor_extraido
                ? `${externalLink(inteiroTeorUrl, '📄 Abrir PDF')} · <span class="ok-tag">✓ Texto completo extraído automaticamente (${Number(d.inteiro_teor_chars || 0).toLocaleString('pt-BR')} caracteres)</span>`
                : `${externalLink(inteiroTeorUrl, '📄 Abrir PDF')} · <button class="btn-text tl-load" data-url="${esc(inteiroTeorUrl)}">⬇️ Tentar carregar texto completo</button>${d.inteiro_teor_erro ? `<br><span class="hint">Extração automática indisponível: ${esc(d.inteiro_teor_erro)}</span>` : ''}`
            : '';

        const partidosAutores = (d.partidos_autores || []).filter(Boolean);
        const partidoLinha = partidosAutores.length
            ? `<b>Partido(s) do(s) autor(es):</b> ${esc(partidosAutores.join(', '))}<br>`
            : '';

        box.innerHTML = `
            <span class="ok-tag">✓ Encontrada</span> — <b>${esc(d.tipo)} ${esc(d.numero)}/${esc(d.ano)}</b> · ${esc(d.situacao) || 'situação não informada'}<br>
            <b>Ementa:</b> ${esc(d.ementa) || '—'}<br>
            <b>Autor(es):</b> ${esc((d.autores || []).join(', ')) || '—'}<br>
            ${partidoLinha}
            ${inteiroTeorAcao}
            ${timeline}
            ${votacoesBox}
            ${senadoBox}`;

        const loadBtn = box.querySelector('.tl-load');
        if (loadBtn) loadBtn.addEventListener('click', () => carregarInteiroTeor(loadBtn));
        updateSearchStatus(`${d.tipo} ${d.numero}/${d.ano} carregada para revisão.`, 'ok');
        return true;
    } catch (e) {
        if (e.name === 'AbortError') return false;
        if (seq !== buscarProposicaoSeq) return false;
        showError(box, e.message);
        updateSourceSummary();
        $('#emptyState').classList.remove('hidden');
        updateSearchStatus('Não foi possível carregar essa proposição.', 'err');
        return false;
    } finally {
        if (seq === buscarProposicaoSeq) buscarProposicaoController = null;
    }
}

async function abrirAnaliseProposicao(tipo, numero, ano) {
    if (!tipo || !numero || !ano) {
        toast('Dados da proposição incompletos para análise.', 'err');
        return;
    }

    irParaView('leis');
    $('#b-tipo').value = tipo;
    $('#b-numero').value = numero;
    $('#b-ano').value = ano;
    $('#resultado').classList.add('hidden');
    $('#erro').classList.add('hidden');
    $('#emptyState').classList.add('hidden');

    try {
        const carregou = await buscarProposicao();
        if (!carregou) return;
        await analisarLei(false);
    } catch (e) {
        toast(e.message || 'Não foi possível abrir a análise da proposição.', 'err');
    }
}

/* Baixa e extrai o texto completo do PDF do inteiro teor */
async function carregarInteiroTeor(btn) {
    const url = safeUrl(btn.dataset.url);
    if (!url) {
        btn.textContent = '❌ Link inválido';
        btn.disabled = false;
        return;
    }
    btn.textContent = '⏳ Extraindo texto do PDF...';
    btn.disabled = true;
    try {
        const params = new URLSearchParams({ url });
        const r = await apiFetch(`api/inteiro_teor.php?${params.toString()}`);
        const j = await r.json();
        if (!j.ok) throw new Error(j.erro);
        $('#texto').value = j.texto;
        $('#charCount').textContent = j.chars.toLocaleString('pt-BR');
        updateSourceSummary('PDF completo');
        btn.textContent = `✓ ${j.chars.toLocaleString('pt-BR')} caracteres carregados`;
        btn.style.color = 'var(--green)';
    } catch (e) {
        btn.textContent = '❌ ' + e.message;
        btn.style.color = 'var(--red)';
        btn.disabled = false;
    }
}

/* ---------- Limpar ---------- */
$('#btnLimpar').addEventListener('click', () => {
    $('#texto').value = '';
    $('#charCount').textContent = '0';
    contexto = null;
    updateSourceSummary();
    ['#linkResultado', '#buscaResultado'].forEach((s) => { $(s).classList.add('hidden'); $(s).innerHTML = ''; });
    $('#resultado').classList.add('hidden');
    $('#erro').classList.add('hidden');
    $('#emptyState').classList.remove('hidden');
});

/* ---------- Analisar ---------- */
let lastLei = null;
$('#btnAnalisar').addEventListener('click', () => analisarLei(false));

async function analisarLei(force) {
    const texto = force ? (lastLei?.texto || '') : $('#texto').value.trim();
    if (texto.length < 20) { toast('Busque por número, extraia um link ou cole o texto da proposição.', 'err'); return; }
    lastLei = { texto, contexto: contexto || {} };

    $('#resultado').classList.add('hidden');
    $('#erro').classList.add('hidden');
    $('#emptyState').classList.add('hidden');
    $('#loading').classList.remove('hidden');
    setLeiStep(3);

    try {
        const r = await apiFetch('api/analisar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ texto, contexto: lastLei.contexto, atualizar: force }),
        });
        const j = await r.json();
        if (!j.ok) throw new Error(j.erro);
        renderResultado(j.analise, j);
    } catch (e) {
        showError('#erro', e.message);
        $('#erro').classList.remove('hidden');
    } finally {
        $('#loading').classList.add('hidden');
    }
}

/* ---------- Render dos resultados ---------- */
/* Classe CSS segura derivada de valor vindo da IA (ex: "alto", "media") */
function badgeClass(v) {
    return String(v).toLowerCase().replace(/[^a-z0-9_-]/g, '');
}
/* Nota 0-10 garantida, mesmo se a IA devolver fora da faixa */
function clampNota(v) {
    return Math.max(0, Math.min(10, Number(v) || 0));
}

function overviewCard(label, value, note = '', cls = '') {
    return `<div class="overview-card ${cls}">
        <span>${esc(label)}</span>
        <strong>${esc(value)}</strong>
        ${note ? `<small>${esc(note)}</small>` : ''}
    </div>`;
}

function overviewGrid(items) {
    return `<div class="overview-grid">${items.join('')}</div>`;
}

function renderVotacoesProposicao(ctx = {}, compact = false) {
    const votacoes = Array.isArray(ctx.votacoes) ? ctx.votacoes : [];
    if (!votacoes.length) {
        return compact
            ? ''
            : renderVotacoesProposicaoEmpty(ctx);
    }
    const itens = votacoes.slice(0, compact ? 4 : 8).map((v) => {
        const resultado = v.aprovacao === 1 ? 'aprovada' : (v.aprovacao === 0 ? 'não aprovada' : 'resultado não informado');
        const votos = v.votos && typeof v.votos === 'object' ? v.votos : null;
        const contagem = votos?.por_tipo?.length
            ? `<div class="vote-counts">${votos.por_tipo.map((c) => `<span><b>${esc(c.total)}</b> ${esc(c.tipo)}</span>`).join('')}</div>`
            : '';
        const detalhes = renderDetalhesVotacao(v, compact);
        return `<div class="proj-item">
            <b>${esc(v.data || 'sem data')} · ${esc(v.orgao || 'órgão não informado')}</b>
            <span class="proj-sit">${esc(resultado)}</span>
            <div class="it-desc">${esc(v.descricao || 'Sem descrição.')}</div>
            ${contagem}
            ${detalhes}
            <div class="proj-actions">
                ${externalLink(v.uri, 'Dados oficiais da votação')}
                ${externalLink(v.uri_votos, 'Votos oficiais')}
            </div>
        </div>`;
    }).join('');
    if (compact) {
        return `<div class="timeline"><b>🗳️ Votações relacionadas</b>${itens}</div>`;
    }
    return `<div class="box full" id="lei-votacoes"><h3>🗳️ Votações relacionadas</h3>${itens}</div>`;
}

function renderVotacoesProposicaoEmpty(ctx = {}) {
    const meta = ctx.votacoes_meta && typeof ctx.votacoes_meta === 'object' ? ctx.votacoes_meta : {};
    const status = meta.status || '';
    const senadoVotacoes = Array.isArray(ctx.senado?.votacoes) ? ctx.senado.votacoes : [];
    const endpoint = meta.url || meta.links_oficiais?.votacoes || (ctx.id ? `https://dadosabertos.camara.leg.br/api/v2/proposicoes/${encodeURIComponent(ctx.id)}/votacoes` : '');
    let mensagem = meta.mensagem || 'Votações da Câmara não foram carregadas neste contexto.';

    if (status === 'erro_api') {
        mensagem = 'Falha ao consultar as votações relacionadas na API oficial da Câmara; isso não confirma ausência de votações.';
    } else if (status === 'sem_votacoes') {
        mensagem = 'A API oficial da Câmara foi consultada e não retornou votações relacionadas para esta proposição.';
    } else if (status === 'id_invalido') {
        mensagem = 'Não foi possível consultar votações da Câmara porque o ID da proposição não está disponível.';
    } else if (status === 'nao_consultado_csv') {
        mensagem = 'A proposição foi carregada pelo arquivo anual da Câmara; as votações da Câmara não foram consultadas automaticamente nesse fallback.';
    } else if (!status) {
        mensagem = 'Nenhuma votação da Câmara foi carregada neste contexto; isso não confirma ausência de votações.';
    }

    const totalSenado = senadoVotacoes.length;
    const senadoResumo = totalSenado === 1
        ? '1 votação do Senado foi carregada em seção própria.'
        : `${Number(totalSenado).toLocaleString('pt-BR')} votações do Senado foram carregadas em seção própria.`;
    const senadoInfo = totalSenado
        ? `<p class="hint">${senadoResumo} <a href="#lei-votacoes-senado">Ver votações no Senado</a>.</p>`
        : '';
    const erroInfo = meta.erro ? `<p class="hint">Detalhe técnico da consulta: ${esc(meta.erro)}</p>` : '';
    const endpointLink = endpoint ? `<div class="proj-actions">${externalLink(endpoint, 'Consultar endpoint oficial da Câmara')}</div>` : '';

    return `<div class="box full" id="lei-votacoes">
        <h3>🗳️ Votações na Câmara</h3>
        <div class="hint">${esc(mensagem)}</div>
        ${senadoInfo}
        ${erroInfo}
        ${endpointLink}
    </div>`;
}

function renderVotacoesSenado(senado = {}, compact = false) {
    const votacoes = Array.isArray(senado?.votacoes) ? senado.votacoes : [];
    if (!votacoes.length) return '';
    const itens = votacoes.slice(0, compact ? 3 : 6).map((v) => {
        const resultado = v.aprovacao === 1 ? 'aprovada' : (v.aprovacao === 0 ? 'não aprovada' : (v.resultado || 'resultado não informado'));
        const votos = v.votos && typeof v.votos === 'object' ? v.votos : null;
        const contagem = votos?.por_tipo?.length
            ? `<div class="vote-counts">${votos.por_tipo.map((c) => `<span><b>${esc(c.total)}</b> ${esc(c.tipo)}</span>`).join('')}</div>`
            : '';
        return `<div class="proj-item">
            <b>${esc(v.data || 'sem data')} · ${esc(v.orgao || 'Senado')}</b>
            <span class="proj-sit">${esc(resultado)}</span>
            <div class="it-desc">${esc(v.descricao || 'Sem descrição.')}</div>
            ${contagem}
            ${renderDetalhesVotacao(v, compact)}
            <div class="proj-actions">
                ${externalLink(v.uri, 'Dados oficiais no Senado')}
                ${externalLink(v.uri_votos, 'Votos oficiais no Senado')}
            </div>
        </div>`;
    }).join('');

    if (compact) {
        return `<div class="timeline"><b>🗳️ Votações no Senado</b>${itens}</div>`;
    }
    return `<div class="box full" id="lei-votacoes-senado"><h3>🗳️ Votações no Senado</h3>${itens}</div>`;
}

function renderDetalhesVotacao(v = {}, compact = false) {
    const votos = v.votos && typeof v.votos === 'object' ? v.votos : null;
    const efeitos = Array.isArray(v.efeitos) ? v.efeitos : [];
    const afetadas = Array.isArray(v.proposicoes_afetadas) ? v.proposicoes_afetadas : [];
    const hasDetalhes = votos?.total || efeitos.length || afetadas.length || v.votos_erro || v.detalhes_erro;
    if (!hasDetalhes) return '';

    const partidoTop = votos?.por_partido?.length
        ? renderVoteChips(votos.por_partido.slice(0, compact ? 8 : 16), 'sigla')
        : '';
    const ufTop = votos?.por_uf?.length
        ? renderVoteChips(votos.por_uf.slice(0, compact ? 8 : 12), 'uf')
        : '';
    const amostra = votos?.amostra?.length
        ? `<div class="vote-sample">
            <b>Amostra nominal (${votos.amostra.length} de ${Number(votos.total || 0).toLocaleString('pt-BR')})</b>
            ${votos.amostra.slice(0, compact ? 12 : 40).map((item) => `
                <div class="vote-row">
                    <span>${esc(item.nome || 'Deputado não informado')}</span>
                    <em>${esc([item.partido, item.uf].filter(Boolean).join('/'))}</em>
                    <strong>${esc(item.voto || '')}</strong>
                </div>`).join('')}
        </div>`
        : '';
    const efeitosHtml = efeitos.length
        ? `<div class="vote-effects">
            <b>Efeitos registrados</b>
            ${efeitos.slice(0, compact ? 2 : 5).map((e) => `<p>${esc([e.proposicao, e.resultado].filter(Boolean).join(' - '))}</p>`).join('')}
        </div>`
        : '';
    const afetadasHtml = afetadas.length
        ? `<div class="vote-affected">
            <b>Proposições afetadas</b>
            ${afetadas.slice(0, compact ? 2 : 6).map((p) => `<p>${esc([p.titulo, p.ementa].filter(Boolean).join(' - '))}</p>`).join('')}
        </div>`
        : '';
    const erros = [v.detalhes_erro ? `Detalhes: ${v.detalhes_erro}` : '', v.votos_erro ? `Votos: ${v.votos_erro}` : ''].filter(Boolean);

    return `<details class="vote-details">
        <summary>Ver dados lidos da votação${votos?.total ? ` (${Number(votos.total).toLocaleString('pt-BR')} voto(s) nominal(is))` : ''}</summary>
        ${partidoTop ? `<div class="vote-block"><span>Por partido</span>${partidoTop}</div>` : ''}
        ${ufTop ? `<div class="vote-block"><span>Por UF</span>${ufTop}</div>` : ''}
        ${efeitosHtml}
        ${afetadasHtml}
        ${amostra}
        ${erros.length ? `<p class="hint">${esc(erros.join(' · '))}</p>` : ''}
    </details>`;
}

function renderVoteChips(items = [], key = 'sigla') {
    return `<div class="vote-chips">${items.map((item) => `
        <span><b>${esc(item[key] || '—')}</b> ${Number(item.total || 0).toLocaleString('pt-BR')}</span>
    `).join('')}</div>`;
}

function despesaEscopo(despesas = {}) {
    const ano = despesas.ano || '—';
    const qtd = Number(despesas.qtd || 0);
    const paginas = Number(despesas.paginas_lidas || 0);
    const periodo = despesas.periodo_inicio && despesas.periodo_fim
        ? `${despesas.periodo_inicio} a ${despesas.periodo_fim}`
        : '';
    const completo = despesas.completo !== false;
    const base = completo ? `total líquido de ${ano}` : `parcial de ${ano}`;
    const detalhe = `${qtd.toLocaleString('pt-BR')} lançamento(s)${paginas ? ` · ${paginas} página(s)` : ''}${periodo ? ` · ${periodo}` : ''}`;
    return { completo, base, detalhe };
}

function resumoProjetosOficial(perfil = {}) {
    const projetos = Array.isArray(perfil.proposicoes) ? perfil.proposicoes : [];
    const base = perfil.prop_resumo && typeof perfil.prop_resumo === 'object' ? perfil.prop_resumo : {};
    const computado = projetos.reduce((acc, p) => {
        const status = p.status || 'tramitando';
        acc.total += 1;
        if (status === 'aprovada') acc.aprovada += 1;
        else if (status === 'arquivada') acc.arquivada += 1;
        else acc.tramitando += 1;
        return acc;
    }, { total: 0, aprovada: 0, arquivada: 0, tramitando: 0 });

    const totalBase = Number(base.total || 0);
    if (totalBase > 0 || !projetos.length) {
        const texto = String(perfil.texto_base || '');
        const m = texto.match(/PRODU[ÇC][ÃA]O LEGISLATIVA[^:]*:\s*(\d+)\s+proposi[çc][õo]es?\s*[—-]\s*(\d+)\s+aprovada[\s\S]*?,\s*(\d+)\s+arquivada[\s\S]*?,\s*(\d+)\s+em tramita/i);
        if (!totalBase && m) {
            return {
                total: Number(m[1] || 0),
                aprovada: Number(m[2] || 0),
                arquivada: Number(m[3] || 0),
                tramitando: Number(m[4] || 0),
                fonte: 'texto_base',
            };
        }
        return {
            total: totalBase,
            aprovada: Number(base.aprovada || 0),
            arquivada: Number(base.arquivada || 0),
            tramitando: Number(base.tramitando || 0),
            fonte: totalBase > 0 ? 'prop_resumo' : 'vazio',
        };
    }
    return { ...computado, fonte: 'proposicoes' };
}

function fmtBRL(v) {
    return 'R$ ' + (Number(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}

function clampLevel(v) {
    if (typeof v === 'string') {
        const normalized = v.trim().toLowerCase();
        if (['alto', 'alta'].includes(normalized)) return 8;
        if (['medio', 'médio', 'media', 'média'].includes(normalized)) return 5;
        if (['baixo', 'baixa'].includes(normalized)) return 2;
    }
    const n = Number(v);
    return Number.isFinite(n) ? Math.max(0, Math.min(10, n)) : null;
}

const IMPACT_GROUPS = [
    'Classe A',
    'Classe B',
    'Classe C',
    'Classe D/E',
    'Empresários',
    'Trabalhadores',
    'Setor público',
    'Consumidores',
];

function normalizeImpactosSociais(a) {
    const fromAi = Array.isArray(a.impactos_por_grupo) ? a.impactos_por_grupo : [];
    const byName = new Map(fromAi.map((g) => [String(g.grupo || '').toLowerCase(), g]));

    return IMPACT_GROUPS.map((nome) => {
        const ai = byName.get(nome.toLowerCase());
        if (!ai) return null;
        const bom = clampLevel(ai.beneficio ?? ai.bom);
        const ruim = clampLevel(ai.prejuizo ?? ai.ruim);
        if (bom == null || ruim == null) return null;
        return {
            grupo: nome,
            bom,
            ruim,
            descricao: ai.descricao || 'Impacto informado pela IA com base no texto analisado.',
            fonte: 'ia',
        };
    }).filter(Boolean);
}

function renderImpactosUnavailable() {
    return `<div class="impact-unavailable">
        <h4>Recorte por classe/grupo ainda não disponível</h4>
        <p>Esta análise não trouxe dados estruturados por Classe A, B, C, D/E, empresários e demais grupos. Clique em <b>Atualizar com IA</b> para gerar novamente com o novo formato.</p>
    </div>`;
}

function renderAnalyticsSection(a) {
    const grupos = normalizeImpactosSociais(a);
    const hasGroups = grupos.length > 0;
    return `<div class="box full analytics-box" id="lei-grupos">
        <h3>📊 Impacto por grupo social e econômico</h3>
        <div class="analytics-grid ${hasGroups ? '' : 'single'}">
            <div>
                <p class="chart-note">${hasGroups ? 'Níveis de benefício e prejuízo em escala de 0 a 10. Clique em um grupo para ver a leitura.' : 'Sem estimativas automáticas: este painel mostra apenas dados explícitos retornados pela análise.'}</p>
                ${hasGroups ? renderImpactosSociais(grupos) : renderImpactosUnavailable()}
            </div>
            <div>
                <p class="chart-note">Distribuição simples dos principais elementos do relatório.</p>
                ${renderBalanceChart(a)}
            </div>
        </div>
    </div>`;
}

function renderImpactosSociais(grupos) {
    if (!grupos.length) return renderImpactosUnavailable();
    return `<div class="impact-chart" data-impact-chart>
        <div class="impact-list">
            ${grupos.map((g, i) => `
                <button type="button" class="impact-row ${i === 0 ? 'active' : ''}" data-impact-index="${i}" data-grupo="${esc(g.grupo)}" data-desc="${esc(g.descricao)}">
                    <span class="impact-name">${esc(g.grupo)}</span>
                    <span class="impact-bars">
                        <span class="impact-bar good"><i style="width:${g.bom * 10}%"></i></span>
                        <span class="impact-bar bad"><i style="width:${g.ruim * 10}%"></i></span>
                    </span>
                    <span class="impact-score"><b>${g.bom}</b><em>${g.ruim}</em></span>
                </button>`).join('')}
        </div>
        <div class="impact-detail">
            <span class="guide-label">Leitura do grupo</span>
            <h4>${esc(grupos[0].grupo)}</h4>
            <p>${esc(grupos[0].descricao)}</p>
            <div class="impact-legend">
                <span><i class="good-dot"></i> Bom / benefício</span>
                <span><i class="bad-dot"></i> Ruim / prejuízo</span>
            </div>
        </div>
    </div>`;
}
function renderBalanceChart(a) {
    const dados = [
        ['Positivos', (a.pontos_positivos || []).length, 'good'],
        ['Negativos', (a.pontos_negativos || []).length, 'bad'],
        ['Riscos', (a.riscos || []).length, 'warn'],
    ];
    const max = Math.max(1, ...dados.map((d) => d[1]));
    return `<div class="mini-chart" aria-label="Resumo visual do resultado">
        ${dados.map(([label, value, cls]) => `
            <div class="mini-chart-row ${cls}">
                <span>${label}</span>
                <div class="mini-chart-track"><i style="width:${Math.round((value / max) * 100)}%"></i></div>
                <b>${value}</b>
            </div>`).join('')}
    </div>`;
}

function renderAlertasEspeciais(a) {
    const alertas = [
        ...(Array.isArray(a.alertas_investigativos) ? a.alertas_investigativos : []),
        ...(Array.isArray(a.alertas_especiais) ? a.alertas_especiais : []),
    ];
    const termos = Array.isArray(a.termos_pesquisa) ? a.termos_pesquisa : [];
    const categoriaLabel = {
        corrupcao: 'Integridade pública',
        integridade_publica: 'Integridade pública',
        favorecimento: 'Favorecimento',
        conflito_interesse: 'Conflito de interesse',
        gasto_publico: 'Gasto público',
        baixa_transparencia: 'Baixa transparência',
        concentracao_poder: 'Concentração de poder',
        risco_juridico: 'Risco jurídico',
        fiscalizacao: 'Fiscalização',
        outro: 'Outro',
    };
    const alertaHtml = alertas.length ? alertas.map((al) => {
        const cat = categoriaLabel[al.categoria] || al.categoria || 'Alerta';
        const gravidade = al.gravidade || 'media';
        const grav = badgeClass(gravidade);
        const termosAlerta = Array.isArray(al.como_pesquisar)
            ? al.como_pesquisar
            : (Array.isArray(al.como_verificar) ? al.como_verificar : []);
        const pesquisas = termosAlerta.map((t) => `<button type="button" class="research-chip" data-term="${esc(t)}">${esc(t)}</button>`).join('');
        return `<div class="alert-card ${grav}">
            <div class="alert-head">
                <span class="alert-cat">${esc(cat)}</span>
                <span class="badge ${grav}">gravidade: ${esc(gravidade)}</span>
            </div>
            <h4>${esc(al.titulo) || 'Ponto a investigar'}</h4>
            <p>${esc(al.descricao) || '—'}</p>
            ${al.evidencia ? `<div class="alert-evidence"><b>Evidência/lacuna:</b> ${esc(al.evidencia)}</div>` : ''}
            ${pesquisas ? `<div class="research-list">${pesquisas}</div>` : ''}
        </div>`;
    }).join('') : `<div class="impact-unavailable">
        <h4>Nenhum alerta especial estruturado</h4>
        <p>Esta análise não retornou pontos estruturados de integridade pública, favorecimento, conflito de interesse ou outros temas de auditoria. Em análises antigas, clique em <b>Atualizar com IA</b> para gerar com o novo formato.</p>
    </div>`;

    const termosHtml = termos.length ? `
        <div class="research-panel">
            <span class="guide-label">Termos para pesquisar melhor</span>
            <div class="research-list">
                ${termos.map((t) => `<button type="button" class="research-chip" data-term="${esc(t)}">${esc(t)}</button>`).join('')}
            </div>
            <button type="button" class="btn-secondary copy-research">Copiar termos</button>
        </div>` : '';

    return `<div class="box full special-alerts" id="lei-alertas">
        <h3>Alertas investigativos</h3>
        <p class="chart-note">São sinais para pesquisa e conferência, não acusações nem prova de irregularidade. A leitura deve ser confirmada em fontes oficiais.</p>
        <div class="alert-grid">${alertaHtml}</div>
        ${termosHtml}
    </div>`;
}

function itemDescricao(it) {
    if (it == null) return '';
    if (typeof it === 'string') return it;
    return it.descricao || it.valor || it.texto || it.base || '';
}

function itemTitulo(it, fallback = 'Item') {
    if (it == null) return fallback;
    if (typeof it === 'string') return fallback;
    return it.titulo || it.bloco || it.categoria || it.rotulo || fallback;
}

function renderStructuredItems(items, emptyText = 'Nada informado.') {
    const arr = Array.isArray(items) ? items.filter(Boolean) : [];
    if (!arr.length) return `<div class="hint">${esc(emptyText)}</div>`;
    return arr.map((it) => {
        const badges = [];
        if (it && typeof it === 'object') {
            if (it.confianca) badges.push(`<span class="badge ${badgeClass(it.confianca)}">confiança: ${esc(it.confianca)}</span>`);
            if (it.nivel) badges.push(`<span class="badge ${badgeClass(it.nivel)}">confiança: ${esc(it.nivel)}</span>`);
            if (it.gravidade) badges.push(`<span class="badge ${badgeClass(it.gravidade)}">gravidade: ${esc(it.gravidade)}</span>`);
            if (it.base) badges.push(`<span class="badge">base: ${esc(it.base)}</span>`);
        }
        const desc = itemDescricao(it);
        const extra = it && typeof it === 'object' && it.justificativa ? `<div class="it-desc">${esc(it.justificativa)}</div>` : '';
        return `<div class="item">
            <div class="it-head"><span class="it-title">${esc(itemTitulo(it))}</span>${badges.join(' ')}</div>
            ${desc ? `<div class="it-desc">${esc(desc)}</div>` : ''}
            ${extra}
        </div>`;
    }).join('');
}

function renderStructuredBlock(title, items, emptyText, id = '', note = '') {
    return `<div class="box full"${id ? ` id="${id}"` : ''}>
        <h3>${esc(title)}</h3>
        ${note ? `<p class="chart-note">${esc(note)}</p>` : ''}
        ${renderStructuredItems(items, emptyText)}
    </div>`;
}

function fallbackConfiancaBlocos(tipo = 'geral') {
    const isParlamentar = tipo === 'parlamentar';
    return [
        {
            titulo: 'Dados objetivos',
            descricao: isParlamentar
                ? 'Identificação, mandato, despesas, proposições e links oficiais carregados diretamente das fontes disponíveis mantêm confiança alta quando exibidos como dados factuais.'
                : 'Metadados, tramitação, autores e votações carregados de fontes oficiais mantêm confiança alta quando exibidos como dados factuais.',
            confianca: 'alta',
        },
        {
            titulo: 'Leitura da IA',
            descricao: 'Resumo, parecer, impactos e riscos são interpretação sobre o material carregado; devem ser tratados como apoio para revisão humana.',
            confianca: 'media',
        },
        {
            titulo: 'Comparativos e ausências',
            descricao: 'Médias por partido, UF, período, categoria ou histórico completo só têm confiança alta quando a base comparativa aparece explicitamente carregada no relatório.',
            confianca: 'baixa',
        },
    ];
}

function renderConfiancaBlock(items, tipo = 'geral') {
    const arr = Array.isArray(items) ? items.filter(Boolean) : [];
    const dados = arr.length ? arr : fallbackConfiancaBlocos(tipo);
    const note = arr.length
        ? 'Classificação retornada pela análise atual.'
        : 'Estimativa local baseada nos blocos disponíveis. Para obter a confiança detalhada do prompt atual, use Atualizar com IA.';
    return renderStructuredBlock('Confiança por bloco', dados, 'Sem confiança estruturada.', '', note);
}

function renderLimitacoes(a, fallback = {}) {
    const l = a?.limitacoes && typeof a.limitacoes === 'object' ? a.limitacoes : {};
    const fontes = Array.isArray(l.fontes_usadas) && l.fontes_usadas.length ? l.fontes_usadas : (fallback.fontes || []);
    const ausentes = Array.isArray(l.dados_ausentes) ? l.dados_ausentes : [];
    const periodo = l.periodo_analisado || fallback.periodo || 'Não informado';
    const amostra = l.tamanho_amostra || fallback.amostra || 'Não informado';
    const aviso = l.aviso || 'A IA não substitui auditoria, parecer jurídico ou investigação oficial.';
    return `<div class="box full">
        <h3>Limitações da análise</h3>
        <div class="item"><div class="it-head"><span class="it-title">Fontes usadas</span><span class="badge ${badgeClass('alta')}">dados oficiais quando disponíveis</span></div><div class="it-desc">${esc(fontes.join(', ') || 'Não informado')}</div></div>
        <div class="item"><div class="it-head"><span class="it-title">Período e amostra</span></div><div class="it-desc">Período: ${esc(periodo)} · Amostra: ${esc(amostra)}</div></div>
        ${ausentes.length ? `<div class="item"><div class="it-head"><span class="it-title">Dados ausentes</span></div><div class="it-desc">${esc(ausentes.join('; '))}</div></div>` : ''}
        <p class="gen-note">${esc(aviso)}</p>
    </div>`;
}

function pushFonteOficial(fontes, rotulo, url) {
    const href = safeUrl(url);
    if (!href) return;
    fontes.push({ rotulo, url: href });
}

function fontesOficiaisFromContext(ctx = {}) {
    const fontes = [];
    if (ctx.id) {
        pushFonteOficial(fontes, 'Dados Abertos da Câmara - proposição', `https://dadosabertos.camara.leg.br/api/v2/proposicoes/${encodeURIComponent(ctx.id)}`);
    }
    const votacoesMeta = ctx.votacoes_meta && typeof ctx.votacoes_meta === 'object' ? ctx.votacoes_meta : {};
    pushFonteOficial(
        fontes,
        'Dados Abertos da Câmara - votações da proposição',
        votacoesMeta.url || votacoesMeta.links_oficiais?.votacoes || (ctx.id ? `https://dadosabertos.camara.leg.br/api/v2/proposicoes/${encodeURIComponent(ctx.id)}/votacoes` : '')
    );
    pushFonteOficial(fontes, 'Inteiro teor da proposição', ctx.inteiro_teor);
    if (ctx.url && !ctx.id) {
        pushFonteOficial(fontes, ctx.fonte || ctx.titulo || 'Fonte original', ctx.url);
    }

    const senadoItens = ctx.senado?.encontrado && Array.isArray(ctx.senado.itens) ? ctx.senado.itens : [];
    senadoItens.forEach((s, index) => {
        pushFonteOficial(fontes, `Senado Federal - ${s.identificacao || `documento ${index + 1}`}`, s.url_documento);
    });
    const senadoVotacoes = Array.isArray(ctx.senado?.votacoes) ? ctx.senado.votacoes : [];
    senadoVotacoes.forEach((v, index) => {
        const sufixo = [v.data, v.orgao].filter(Boolean).join(' - ') || `votação Senado ${index + 1}`;
        pushFonteOficial(fontes, `Dados oficiais da votação no Senado - ${sufixo}`, v.uri);
        pushFonteOficial(fontes, `Votos oficiais no Senado - ${sufixo}`, v.uri_votos);
    });

    const votacoes = Array.isArray(ctx.votacoes) ? ctx.votacoes : [];
    votacoes.forEach((v, index) => {
        const sufixo = [v.data, v.orgao].filter(Boolean).join(' - ') || `votação ${index + 1}`;
        pushFonteOficial(fontes, `Dados oficiais da votação - ${sufixo}`, v.uri);
        pushFonteOficial(fontes, `Votos oficiais - ${sufixo}`, v.uri_votos);
    });
    return fontes;
}

function normalizeFonteOficial(f) {
    if (!f) return null;
    if (typeof f === 'string') {
        const url = safeUrl(f);
        return url ? { rotulo: f, url } : { rotulo: f, url: '' };
    }
    if (typeof f !== 'object') return null;
    const rotulo = f.rotulo || f.titulo || f.desc || f.url || f.link || 'Fonte';
    return { rotulo, url: safeUrl(f.url || f.link || '') };
}

function mergeFontesOficiais(...grupos) {
    const vistas = new Set();
    const fontes = [];
    grupos.flat().forEach((fonte) => {
        const f = normalizeFonteOficial(fonte);
        if (!f?.rotulo && !f?.url) return;
        const chave = f.url ? `url:${f.url.toLowerCase()}` : `rotulo:${String(f.rotulo).trim().toLowerCase()}`;
        if (vistas.has(chave)) return;
        vistas.add(chave);
        fontes.push(f);
    });
    return fontes;
}

function renderFontesOficiais(a, fallbackLinks = []) {
    const oficiais = Array.isArray(a?.fontes_oficiais) ? a.fontes_oficiais : [];
    const fontes = mergeFontesOficiais(fallbackLinks, oficiais);
    if (!fontes.length) return '';
    return `<div class="box full">
        <h3>Fontes oficiais e links de conferência</h3>
        ${fontes.map((f) => {
            const rotulo = f.rotulo || f.url || 'Fonte';
            return `<div class="item">${f.url
                ? `${externalLink(f.url, `${rotulo} ↗`, 'ext-link')}`
                : `<div class="it-head"><span class="it-title">${esc(rotulo)}</span></div>`}</div>`;
        }).join('')}
    </div>`;
}

function bindImpactCharts(container) {
    const root = $(container);
    if (!root) return;
    root.querySelectorAll('[data-impact-chart]').forEach((chart) => {
        const detail = chart.querySelector('.impact-detail');
        chart.querySelectorAll('.impact-row').forEach((row) => row.addEventListener('click', () => {
            chart.querySelectorAll('.impact-row').forEach((r) => r.classList.remove('active'));
            row.classList.add('active');
            if (detail) {
                detail.querySelector('h4').textContent = row.dataset.grupo || '';
                detail.querySelector('p').textContent = row.dataset.desc || '';
            }
        }));
    });
}

async function copyText(text) {
    const value = String(text || '');
    if (!value.trim()) throw new Error('Nada para copiar.');

    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(value);
            return;
        } catch (e) {
            // Em HTTP, iframes ou permissões bloqueadas, tenta o método clássico abaixo.
        }
    }

    const area = document.createElement('textarea');
    area.value = value;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.top = '0';
    area.style.left = '0';
    area.style.width = '1px';
    area.style.height = '1px';
    area.style.opacity = '0';
    document.body.appendChild(area);
    try {
        area.focus({ preventScroll: true });
    } catch (e) {
        area.focus();
    }
    area.select();
    area.setSelectionRange(0, area.value.length);
    const ok = document.execCommand('copy');
    area.remove();
    if (!ok) throw new Error('Falha ao copiar.');
}

function bindResearchActions(container, termos = []) {
    const root = $(container);
    if (!root) return;
    root.querySelectorAll('.research-chip').forEach((btn) => btn.addEventListener('click', async () => {
        const term = btn.dataset.term || btn.textContent || '';
        try {
            await copyText(term);
            toast('Termo copiado para pesquisar.', 'ok');
        } catch (e) {
            toast('Não foi possível copiar o termo.', 'err');
        }
    }));
    const copyBtn = root.querySelector('.copy-research');
    if (copyBtn) copyBtn.addEventListener('click', async () => {
        const text = termos.join('\n');
        try {
            await copyText(text);
            toast('Termos de pesquisa copiados.', 'ok');
        } catch (e) {
            toast('Não foi possível copiar os termos.', 'err');
        }
    });
}

function countAlertas(a) {
    return Array.isArray(a.alertas_especiais) ? a.alertas_especiais.length : 0;
}

function collectAlertasFornecedores(analise, despesas) {
    const alertas = [
        ...(Array.isArray(despesas?.alertas_fornecedores) ? despesas.alertas_fornecedores : []),
        ...(Array.isArray(analise?.alertas_fornecedores) ? analise.alertas_fornecedores : []),
    ];
    const unicos = [];
    const vistos = new Set();
    alertas.forEach((al) => {
        if (!al || typeof al !== 'object') return;
        const chave = [
            al.tipo || '',
            al.fornecedor || '',
            al.documento || '',
            al.evidencia || '',
            al.percentual || '',
            al.qtd || '',
        ].join('|').toLowerCase();
        if (vistos.has(chave)) return;
        vistos.add(chave);
        unicos.push(al);
    });
    return unicos;
}

function countAlertasFornecedores(analise, despesas) {
    return collectAlertasFornecedores(analise, despesas).length;
}

function bindCopyResumo(container, texto) {
    const btn = $(container)?.querySelector('.copy-summary');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        try {
            await copyText(texto);
            toast('Resumo copiado.', 'ok');
        } catch (e) {
            toast('Não foi possível copiar automaticamente.', 'err');
        }
    });
}

function renderResultado(a, meta) {
    const lista = (arr, campos) => (arr || []).map((it) => {
        const b = campos.map((c) => it[c.k] ? `<span class="badge ${badgeClass(it[c.k])}">${c.label}: ${esc(it[c.k])}</span>` : '').join(' ');
        return `<div class="item"><div class="it-head"><span class="it-title">${esc(it.titulo)}</span>${b}</div><div class="it-desc">${esc(it.descricao)}</div></div>`;
    }).join('') || '<div class="hint">Nada identificado.</div>';

    const chips = (arr) => (arr || []).length
        ? (arr).map((x) => `<span class="chip">${esc(x)}</span>`).join('')
        : '<span class="hint">—</span>';

    const nota = clampNota(a.nota_transparencia);
    const ctx = meta?.contexto || contexto || {};
    const camaraVotacoes = Array.isArray(ctx.votacoes) ? ctx.votacoes : [];
    const senadoVotacoes = Array.isArray(ctx.senado?.votacoes) ? ctx.senado.votacoes : [];
    const votacoesJumpHref = !camaraVotacoes.length && senadoVotacoes.length ? '#lei-votacoes-senado' : '#lei-votacoes';
    const votacoesJumpLabel = !camaraVotacoes.length && senadoVotacoes.length ? 'Votações Senado' : 'Votações';
    const fonteLei = fontesOficiaisFromContext(ctx);
    const dadosObjetivos = (Array.isArray(a.dados_objetivos) && a.dados_objetivos.length) ? a.dados_objetivos : [
        { titulo: 'Identificação', descricao: [a.tipo, a.identificacao].filter(Boolean).join(' ') || 'Não informada', confianca: 'alta' },
        { titulo: 'Objetivo declarado', descricao: a.objetivo_principal || 'Não informado no retorno da análise.', confianca: a.objetivo_principal ? 'media' : 'baixa' },
    ];
    const leituraIa = (Array.isArray(a.leitura_ia) && a.leitura_ia.length) ? a.leitura_ia : [
        { titulo: 'Leitura sintética', descricao: a.impacto_trabalho || a.parecer_geral || a.resumo || 'Sem leitura interpretativa estruturada.', confianca: 'media' },
    ];
    const indicadores = (Array.isArray(a.indicadores_comparativos) && a.indicadores_comparativos.length) ? a.indicadores_comparativos : [
        { titulo: 'Comparativo contextual', descricao: 'Não há média, partido, estado, período ou categoria disponível nesta análise.', base: 'indisponível', confianca: 'baixa' },
    ];
    const resumoTexto = [
        a.titulo || 'Análise de proposição',
        a.resumo ? `Resumo: ${a.resumo}` : '',
        a.objetivo_principal ? `Objetivo: ${a.objetivo_principal}` : '',
        a.cobranca_efetividade?.resumo ? `Cobrança de efetividade: ${a.cobranca_efetividade.resumo}` : '',
        a.parecer_geral ? `Parecer: ${a.parecer_geral}` : '',
    ].filter(Boolean).join('\n\n');

    $('#resultado').innerHTML = `
        ${metaBar(meta || {})}
        <nav class="report-jump" aria-label="Navegacao do relatorio">
            <a href="#lei-resumo">Resumo</a>
            <a href="#lei-dados">Dados</a>
            <a href="${votacoesJumpHref}">${votacoesJumpLabel}</a>
            <a href="#lei-leitura">Leitura IA</a>
            <a href="#lei-cobranca">Cobrança</a>
            <a href="#lei-grupos">Grupos</a>
            <a href="#lei-alertas">Alertas</a>
            <a href="#lei-impactos">Impactos</a>
            <a href="#lei-riscos">Riscos</a>
            <a href="#lei-parecer">Parecer</a>
        </nav>
        <div class="res-head" id="lei-resumo">
            <div class="tags">
                <span class="tag type">${esc(a.tipo) || 'Proposição'}</span>
                ${a.identificacao ? `<span class="tag">${esc(a.identificacao)}</span>` : ''}
            </div>
            <h2 style="margin-top:12px">${esc(a.titulo) || 'Análise'}</h2>
            <div class="res-meta">
                <p><b>Resumo executivo:</b> ${esc(a.resumo_executivo || a.resumo) || '—'}</p>
                <p><b>Objetivo:</b> ${esc(a.objetivo_principal) || '—'}</p>
                ${a.impacto_trabalho ? `<p><b>Impacto no trabalho realizado:</b> ${esc(a.impacto_trabalho)}</p>` : ''}
            </div>
            <div class="nota-pill">
                <span class="tag">Transparência</span>
                <span class="nota-bar"><i style="width:${nota * 10}%"></i></span>
                <span class="nota-val">${nota}/10</span>
            </div>
        </div>

        ${overviewGrid([
            overviewCard('Transparência', `${nota}/10`, 'clareza dos dados', 'accent'),
            overviewCard('Pontos positivos', (a.pontos_positivos || []).length, 'itens identificados', 'ok-card'),
            overviewCard('Riscos', (a.riscos || []).length, 'pontos de atenção', 'warn-card'),
            overviewCard('Alertas', countAlertas(a), 'pontos a investigar', countAlertas(a) ? 'danger-card' : ''),
        ])}

        <div class="sec-grid">
            ${renderStructuredBlock('Dados objetivos', dadosObjetivos, 'Sem dados objetivos estruturados.', 'lei-dados', 'Bloco factual extraído das fontes fornecidas ou dos metadados carregados.')}
            ${renderVotacoesProposicao(ctx)}
            ${renderVotacoesSenado(ctx.senado)}
            ${renderStructuredBlock('Leitura da IA', leituraIa, 'Sem leitura interpretativa estruturada.', 'lei-leitura', 'Interpretação gerada com base nos dados lidos; não deve ser tratada como fato absoluto.')}
            ${renderCobrancaEfetividade(a)}
            ${renderStructuredBlock('Indicadores e comparativos', indicadores, 'Sem comparativos disponíveis.', 'lei-indicadores')}
            ${renderConfiancaBlock(a.confianca_blocos, 'lei')}
            ${renderAnalyticsSection(a)}
            ${renderAlertasEspeciais(a)}
            <div class="box pos full" id="lei-impactos"><h3>✅ Pontos positivos</h3>${lista(a.pontos_positivos, [{k:'impacto',label:'impacto'}])}</div>
            <div class="box neg full"><h3>⚠️ Pontos de atenção</h3>${lista(a.pontos_negativos, [{k:'impacto',label:'impacto'}])}</div>
            <div class="box risco full" id="lei-riscos"><h3>🚨 Riscos</h3>${lista(a.riscos, [{k:'probabilidade',label:'prob'},{k:'gravidade',label:'grav'}])}</div>

            <div class="box"><h3>👍 Quem é beneficiado</h3><div class="chips">${chips(a.interessados?.beneficiados)}</div></div>
            <div class="box"><h3>👎 Quem é prejudicado</h3><div class="chips">${chips(a.interessados?.prejudicados)}</div></div>

            <div class="box full"><h3>🏷️ Áreas afetadas</h3><div class="chips">${chips(a.areas_afetadas)}</div></div>
            ${renderLimitacoes(a, {
                fontes: fonteLei.map((f) => f.rotulo).filter(Boolean),
                periodo: ctx.ano || '',
                amostra: $('#texto')?.value ? `${$('#texto').value.length.toLocaleString('pt-BR')} caracteres analisados` : '',
            })}
            ${renderFontesOficiais(a, fonteLei)}

            <div class="box full" id="lei-parecer">
                <h3>📌 Parecer geral</h3>
                <p>${esc(a.parecer_geral) || '—'}</p>
                <div class="res-actions">
                    <button class="btn-secondary copy-summary">📋 Copiar resumo</button>
                    <button class="btn-secondary" onclick="window.print()">🖨️ Imprimir / PDF</button>
                </div>
            </div>
        </div>`;
    $('#resultado').classList.remove('hidden');
    setLeiStep(4);
    bindAtualizar('#resultado', () => analisarLei(true));
    bindCopyResumo('#resultado', resumoTexto);
    bindImpactCharts('#resultado');
    bindResearchActions('#resultado', Array.isArray(a.termos_pesquisa) ? a.termos_pesquisa : []);
    $('#resultado').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderCobrancaEfetividade(a) {
    const c = a?.cobranca_efetividade || {};
    const responsaveis = Array.isArray(c.responsaveis) ? c.responsaveis.filter((r) => r && (r.quem || r.papel || r.por_que || r.como_cobrar)) : [];
    const criterios = Array.isArray(c.criterios_de_efetividade) ? c.criterios_de_efetividade.filter(Boolean) : [];
    const esfera = c.esfera_principal || 'indeterminada';
    const badge = esfera === 'federal' ? 'federal' : esfera === 'estadual' || esfera === 'municipal' ? esfera : esfera === 'compartilhada' ? 'compartilhada' : 'indeterminada';

    const corpo = responsaveis.length ? responsaveis.map((r) => `
        <div class="item">
            <div class="it-head">
                <span class="it-title">${esc(r.quem || 'Responsável não identificado')}</span>
                ${r.esfera ? `<span class="badge ${badgeClass(r.esfera)}">${esc(r.esfera)}</span>` : ''}
                ${r.papel ? `<span class="badge">${esc(r.papel)}</span>` : ''}
            </div>
            ${r.por_que ? `<div class="it-desc"><b>Por que cobrar:</b> ${esc(r.por_que)}</div>` : ''}
            ${r.como_cobrar ? `<div class="it-desc"><b>Como cobrar:</b> ${esc(r.como_cobrar)}</div>` : ''}
        </div>
    `).join('') : `
        <div class="data-warning">
            <b>Responsável institucional não identificado com segurança.</b>
            <span>A análise atual não trouxe base suficiente para apontar uma esfera específica. Consulte a ementa, o texto integral e a tramitação antes de cobrar execução.</span>
        </div>`;

    return `
        <div class="box full accountability-box" id="lei-cobranca">
            <h3>🏛️ Quem cobrar pela efetividade</h3>
            <p class="chart-note">Este bloco indica a esfera ou órgão a cobrar por regulamentação, execução, fiscalização ou prestação de contas. Não atribui culpa automática.</p>
            <div class="source-summary" style="margin-bottom:12px">
                <strong>Esfera principal: <span class="badge ${badge}">${esc(esfera)}</span></strong>
                <p>${esc(c.resumo || 'Use este bloco para entender quem tende a responder pela efetividade prática da medida.')}</p>
            </div>
            ${corpo}
            ${criterios.length ? `<h4>Critérios para verificar se funcionou</h4><div class="chips">${criterios.map((x) => `<span class="chip">${esc(x)}</span>`).join('')}</div>` : ''}
            ${c.prazo_ou_marco ? `<p><b>Prazo ou marco:</b> ${esc(c.prazo_ou_marco)}</p>` : ''}
            ${c.limites ? `<p class="hint"><b>Limite da cobrança:</b> ${esc(c.limites)}</p>` : ''}
        </div>`;
}

/* Liga os botões de atualização da barra de meta. */
function bindAtualizar(container, handlers) {
    const root = $(container);
    if (!root) return;
    if (typeof handlers === 'function') {
        const btn = root.querySelector('.res-atualizar');
        if (btn) btn.addEventListener('click', handlers);
        return;
    }
    const btnSemIa = root.querySelector('.res-atualizar');
    const btnComIa = root.querySelector('.res-atualizar-ia');
    if (btnSemIa && handlers?.semIa) btnSemIa.addEventListener('click', handlers.semIa);
    if (btnComIa && handlers?.comIa) btnComIa.addEventListener('click', handlers.comIa);
}

/* Escapa HTML para evitar quebra de layout/injeção */
function esc(v) {
    if (v == null) return '';
    return String(v).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

function safeUrl(v) {
    if (!v) return '';
    try {
        const url = new URL(String(v), window.location.origin);
        if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
        return url.href;
    } catch (_) {
        return '';
    }
}

function safeHref(v) {
    const url = safeUrl(v);
    return url ? esc(url) : '';
}

function externalLink(url, label, className = '') {
    const href = safeHref(url);
    if (!href) return '';
    return `<a href="${href}" target="_blank" rel="noopener noreferrer"${className ? ` class="${esc(className)}"` : ''}>${esc(label)}</a>`;
}

function showError(target, msg, tag = 'span') {
    const el = typeof target === 'string' ? $(target) : target;
    if (!el) return;
    el.innerHTML = `<${tag} class="fail">Erro: ${esc(msg)}</${tag}>`;
}

/* ============ PARLAMENTARES ============ */

/* Buscar por nome e/ou partido */
$('#btnBuscarParlamentar').addEventListener('click', buscarParlamentar);
$('#p-nome').addEventListener('keydown', (e) => { if (e.key === 'Enter') buscarParlamentar(); });
$('#p-partido')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') buscarParlamentar(); });
$('#p-municipio')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') buscarParlamentar(); });
$('#p-partido')?.addEventListener('change', () => {
    if (!$('#p-nome').value.trim() && $('#p-partido').value) buscarParlamentar();
});
$('#p-fonte')?.addEventListener('change', updateParlamentarSourceHint);
$('#p-cargo')?.addEventListener('change', updateParlamentarSourceHint);
carregarPartidosParlamentares();
updateParlamentarSourceHint();

function updateParlamentarSourceHint() {
    const fonte = $('#p-fonte')?.value || 'camara';
    const cargo = $('#p-cargo')?.value || 'vereador';
    const status = $('#partidoStatus');
    if (!status) return;
    if (fonte === 'camara') {
        status.textContent = 'Deputados federais: usa Dados Abertos da Câmara, com atuação, despesas, proposições, comissões e votos recuperáveis.';
    } else if (fonte === 'senado') {
        status.textContent = 'Senadores: usa Dados Abertos do Senado. Esta primeira integração carrega perfil atual e links oficiais; produção detalhada do Senado fica como próxima etapa.';
    } else {
        const ano = cargo === 'vereador' ? '2024' : '2022';
        status.textContent = `TSE: usa cadastro eleitoral e resultado de ${ano}. Não inclui atuação, projetos, votos ou despesas do mandato.`;
    }
}

async function carregarPartidosParlamentares() {
    const select = $('#p-partido');
    const status = $('#partidoStatus');
    if (!select) return;
    try {
        const r = await fetch('api/parlamentar.php?acao=partidos');
        const j = await r.json();
        if (!j.ok) throw new Error(j.erro);
        const partidos = Array.isArray(j.partidos) ? j.partidos.filter(Boolean) : [];
        if (!partidos.length) return;
        const atual = select.value;
        select.innerHTML = '<option value="">Todos</option>' + partidos.map((p) => `<option value="${esc(p)}">${esc(p)}</option>`).join('');
        if (atual && partidos.includes(atual)) select.value = atual;
        if (status) status.textContent = `${partidos.length} partido(s) carregado(s) da API da Câmara.`;
    } catch (e) {
        if (status) status.textContent = 'Usando lista local de partidos; não foi possível atualizar pela API agora.';
    }
}

async function buscarParlamentar() {
    const nome = $('#p-nome').value.trim();
    const partido = $('#p-partido')?.value.trim() || '';
    const fonte = $('#p-fonte')?.value || 'camara';
    const cargo = $('#p-cargo')?.value || '';
    const uf = $('#p-uf')?.value || '';
    const municipio = $('#p-municipio')?.value.trim() || '';
    const ano = $('#p-ano')?.value || '';
    const grid = $('#parlamentarLista');
    if (nome.length < 2 && !partido && !((fonte === 'tse' && (uf || municipio)) || (fonte === 'senado' && uf))) { toast('Digite ao menos 2 letras do nome, escolha um partido ou filtre por UF/município no TSE/Senado.', 'err'); return; }
    if (nome.length === 1) { toast('Digite ao menos 2 letras do nome.', 'err'); return; }

    $('#parlamentarSel').classList.add('hidden');
    const fonteLabel = fonte === 'senado' ? 'Senado' : fonte === 'tse' ? 'TSE' : 'Câmara';
    const resumoBusca = [nome, partido ? `partido ${partido}` : '', uf, municipio, fonteLabel].filter(Boolean).join(' · ');
    grid.innerHTML = `<p class="hint">⏳ Buscando ${esc(resumoBusca)}...</p>`;
    try {
        const params = new URLSearchParams({ acao: 'buscar', fonte });
        if (nome) params.set('nome', nome);
        if (partido) params.set('partido', partido);
        if (cargo) params.set('cargo', cargo);
        if (uf) params.set('uf', uf);
        if (municipio) params.set('municipio', municipio);
        if (ano) params.set('ano', ano);
        const r = await fetch('api/parlamentar.php?' + params.toString());
        const j = await r.json();
        if (!j.ok) throw new Error(j.erro);
        if (!j.resultados.length) { grid.innerHTML = '<p class="hint">Nenhum registro encontrado com esses filtros.</p>'; return; }

        const total = j.resultados.length;
        grid.innerHTML = `
            <p class="hint dep-result-count">${total} registro(s) encontrado(s) em ${esc(fonteLabel)}${partido ? ` · ${esc(partido)}` : ''}.</p>
            ${j.resultados.map((d) => `
            <button class="dep-card" data-id="${esc(d.id)}">
                <img src="${esc(d.foto)}" alt="" onerror="this.style.visibility='hidden'">
                <div class="dep-info">
                    <div class="dep-nome">${esc(d.nome)}</div>
                    <div class="dep-sub">${esc(d.cargo || 'Parlamentar')} · ${esc(d.partido)}-${esc(d.uf)}${d.subtitulo ? ` · ${esc(d.subtitulo)}` : ''}</div>
                </div>
            </button>`).join('')}`;

        grid.querySelectorAll('.dep-card').forEach((c) =>
            c.addEventListener('click', () => selecionarParlamentar(c.dataset.id)));
    } catch (e) {
        showError(grid, e.message, 'p');
    }
}

/* Selecionar e carregar perfil */
async function selecionarParlamentar(id) {
    const grid = $('#parlamentarLista');
    const sel = $('#parlamentarSel');
    grid.innerHTML = '';
    sel.classList.remove('hidden');
    sel.innerHTML = '<p class="hint">⏳ Carregando perfil e dados disponíveis...</p>';
    try {
        const r = await fetch('api/parlamentar.php?acao=perfil&id=' + encodeURIComponent(id));
        const j = await r.json();
        if (!j.ok) throw new Error(j.erro);
        perfilAtual = j.perfil;
        const p = j.perfil;
        const total = (p.despesas?.total || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
        const escopoCota = despesaEscopo(p.despesas || {});
        const resumo = resumoProjetosOficial(p);
        const cadastroLimitado = p.origem === 'tse' || p.origem === 'senado';
        const stats = cadastroLimitado ? `
                    <div class="dep-stat"><b>${esc(p.uf || '—')}</b><span>UF</span></div>
                    <div class="dep-stat"><b>${esc(p.partido || '—')}</b><span>partido</span></div>
                    <div class="dep-stat"><b>${esc(p.origem === 'tse' ? (p.eleicao_ano || 'TSE') : 'Senado')}</b><span>fonte</span></div>
                    <div class="dep-stat"><b>${resumo.total || 0}</b><span>projetos carregados</span></div>`
            : `
                    <div class="dep-stat"><b>R$ ${total}</b><span>${esc(escopoCota.base)}</span></div>
                    <div class="dep-stat"><b>${resumo.total || 0}</b><span>projetos (amostra)</span></div>
                    <div class="dep-stat"><b class="ok">${resumo.aprovada || 0}</b><span>aprovados</span></div>
                    <div class="dep-stat"><b class="fail">${resumo.arquivada || 0}</b><span>arquivados</span></div>
                    <div class="dep-stat"><b>${resumo.tramitando || 0}</b><span>em tramitação</span></div>`;
        const actionText = p.origem === 'tse' ? 'Analisar cadastro' : 'Analisar atuação';
        const aviso = cadastroLimitado
            ? `<p class="hint">Este perfil tem escopo limitado: ${esc(p.fonte || 'fonte pública')}. A análise indicará lacunas quando não houver dados de atuação.</p>`
            : '';

        sel.innerHTML = `
            <img src="${esc(p.foto)}" alt="" onerror="this.style.visibility='hidden'">
            <div class="dep-meta">
                <h3>${esc(p.nome)}</h3>
                <p>${esc(p.cargo)} · ${esc(p.partido)}-${esc(p.uf)} · ${esc(p.situacao)}</p>
                <div class="dep-stats">
                    ${stats}
                </div>
                ${aviso}
            </div>
            <button class="btn-primary" id="btnAnalisarParlamentar"><span class="btn-ico">🤖</span> ${actionText}</button>`;
        $('#btnAnalisarParlamentar').addEventListener('click', () => analisarParlamentar(false));
    } catch (e) {
        showError(sel, e.message, 'p');
        perfilAtual = null;
    }
}

/* Analisar atuação */
async function analisarParlamentar(force) {
    if (!perfilAtual) return;
    $('#resultadoP').classList.add('hidden');
    $('#erroP').classList.add('hidden');
    $('#emptyStateP').classList.add('hidden');
    $('#loadingP').classList.remove('hidden');

    try {
        const r = await apiFetch('api/analisar_parlamentar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ texto: perfilAtual.texto_base, perfil: perfilAtual, atualizar: force }),
        });
        const j = await r.json();
        if (!j.ok) throw new Error(j.erro);
        if (j.perfil) perfilAtual = j.perfil; // cache trouxe o perfil salvo
        renderParlamentar(j.analise, j);
    } catch (e) {
        showError('#erroP', e.message);
        $('#erroP').classList.remove('hidden');
    } finally {
        $('#loadingP').classList.add('hidden');
    }
}

async function recarregarPerfilParlamentar() {
    if (!perfilAtual?.id) throw new Error('Nenhum parlamentar selecionado.');
    const r = await fetch('api/parlamentar.php?acao=perfil&id=' + encodeURIComponent(perfilAtual.id));
    const j = await r.json();
    if (!j.ok) throw new Error(j.erro);
    perfilAtual = j.perfil;
    return perfilAtual;
}

async function atualizarParlamentarSemIa() {
    if (!perfilAtual?.id) return;
    $('#erroP').classList.add('hidden');
    $('#loadingP').classList.remove('hidden');
    try {
        await recarregarPerfilParlamentar();
        if (lastParlamentarAnalise) {
            renderParlamentar(lastParlamentarAnalise, {
                ...(lastParlamentarMeta || {}),
                sem_ia: true,
                cache: true,
                gerado_em: new Date().toISOString(),
            });
        }
        toast('Dados oficiais atualizados sem IA.', 'ok');
    } catch (e) {
        showError('#erroP', e.message);
        $('#erroP').classList.remove('hidden');
    } finally {
        $('#loadingP').classList.add('hidden');
    }
}

async function atualizarParlamentarComIa() {
    if (!perfilAtual?.id) return;
    $('#erroP').classList.add('hidden');
    $('#loadingP').classList.remove('hidden');
    try {
        await recarregarPerfilParlamentar();
        await analisarParlamentar(true);
    } catch (e) {
        showError('#erroP', e.message);
        $('#erroP').classList.remove('hidden');
        $('#loadingP').classList.add('hidden');
    }
}

function renderParlamentar(a, meta) {
    lastParlamentarAnalise = a;
    lastParlamentarMeta = meta || {};
    const analiseTextualDesatualizada = !!meta?.sem_ia;
    const perfilLimitado = perfilAtual?.origem === 'tse' || perfilAtual?.origem === 'senado';
    const lista = (arr, campos) => (arr || []).map((it) => {
        const b = (campos || []).map((c) => it[c.k] ? `<span class="badge ${badgeClass(it[c.k])}">${c.label}: ${esc(it[c.k])}</span>` : '').join(' ');
        return `<div class="item"><div class="it-head"><span class="it-title">${esc(it.titulo)}</span>${b}</div><div class="it-desc">${esc(it.descricao)}</div></div>`;
    }).join('') || '<div class="hint">Nada identificado.</div>';
    const chips = (arr) => (arr || []).length ? arr.map((x) => `<span class="chip">${esc(x)}</span>`).join('') : '<span class="hint">—</span>';
    const nota = clampNota(a.nota_transparencia);
    const foto = a._meta?.foto || perfilAtual?.foto || '';

    // Lista factual de projetos por status (vinda direto dos dados oficiais)
    const projetos = Array.isArray(perfilAtual?.proposicoes) ? perfilAtual.proposicoes : [];
    const resumoProjetos = resumoProjetosOficial(perfilAtual || {});
    const linkAnaliseProposicao = (p) => {
        if (!p.tipo || !p.numero || !p.ano) return '';
        return `<div class="proj-actions">
            <a href="#view-leis" class="proj-analysis-link" data-tipo="${esc(p.tipo)}" data-numero="${esc(p.numero)}" data-ano="${esc(p.ano)}">Ler análise da proposição</a>
            ${externalLink(p.url, 'Ver na Câmara')}
        </div>`;
    };
    const grupoProjetos = (status, rotulo, cls) => {
        const itens = projetos.filter((p) => p.status === status);
        if (!itens.length) return '';
        return `<div class="proj-group">
            <div class="proj-head ${cls}">${rotulo} <span>${itens.length}</span></div>
            ${itens.map((p) => `<div class="proj-item"><b>${esc(p.tipo)} ${esc(p.numero)}/${esc(p.ano)}</b>${p.situacao ? ` <span class="proj-sit">${esc(p.situacao)}</span>` : ''}<div class="it-desc">${esc(p.ementa)}</div>${linkAnaliseProposicao(p)}</div>`).join('')}
        </div>`;
    };
    const boxProjetos = projetos.length ? `
        <div class="box full">
            <h3>🗳️ Projetos por situação <span class="hint" style="font-weight:400">(dados oficiais — amostra recente)</span></h3>
            ${grupoProjetos('aprovada', '✅ Aprovados / viraram lei', 'pos')}
            ${grupoProjetos('arquivada', '🗄️ Arquivados / rejeitados', 'neg')}
            ${grupoProjetos('tramitando', '⏳ Em tramitação', 'tram')}
        </div>` : `
        <div class="box full">
            <h3>🗳️ Projetos por situação</h3>
            ${perfilLimitado
                ? `<div class="data-warning"><b>Produção legislativa detalhada não carregada nesta fonte.</b><span>${perfilAtual?.origem === 'tse' ? 'O TSE informa cadastro/resultado eleitoral, não projetos de mandato.' : 'A integração inicial do Senado carrega perfil atual; projetos e votações detalhadas ficam para a próxima etapa.'}</span></div>`
                : resumoProjetos.total > 0
                ? `<div class="data-warning"><b>Lista detalhada indisponível neste registro.</b><span>A contagem foi recuperada do resumo salvo, mas a lista oficial de projetos não está neste cache. Use Atualizar dados sem IA para recarregar a lista.</span></div>`
                : `<div class="data-warning"><b>Nenhuma proposição de autoria encontrada.</b><span>A API da Câmara não retornou projetos dos tipos PL, PEC, PLP, PLV, PDL, PDC, MPV ou PRC na amostra consultada.</span></div>`}
        </div>`;

    // Emendas parlamentares (Portal da Transparência), se disponível
    const em = perfilAtual?.emendas || {};
    const fmt = (v) => 'R$ ' + (Number(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    const emendasPorFuncao = em.por_funcao ? Object.entries(em.por_funcao).slice(0, 8).map(([funcao, valor]) => {
        const pago = em.por_funcao_pago?.[funcao] || 0;
        return `<div class="proj-item"><b>${esc(funcao)}</b><div class="it-desc">Empenhado: ${fmt(valor)} · Pago: ${fmt(pago)}</div></div>`;
    }).join('') : '';
    const boxEmendas = em.disponivel ? `
        <div class="box full">
            <h3>🏛️ Emendas parlamentares <span class="hint" style="font-weight:400">(Portal da Transparência)</span></h3>
            <div class="dep-stats" style="margin-bottom:12px">
                <div class="dep-stat"><b>${em.qtd || 0}</b><span>emendas</span></div>
                <div class="dep-stat"><b>${fmt(em.total_empenhado)}</b><span>empenhado</span></div>
                <div class="dep-stat"><b class="ok">${fmt(em.total_pago)}</b><span>pago</span></div>
            </div>
            ${emendasPorFuncao ? `<h4>Distribuição por área/função</h4>${emendasPorFuncao}` : ''}
            ${(em.amostra || []).map((a) => {
                const detalhes = [a.programa, a.acao, a.localidade, a.favorecido].filter(Boolean).map(esc).join(' · ');
                return `<div class="proj-item"><b>${esc(a.funcao) || 'Área não informada'}</b> <span class="proj-sit">${a.ano}</span><div class="it-desc">Empenhado: ${fmt(a.empenhado)} · Pago: ${fmt(a.pago)}${detalhes ? ` · ${detalhes}` : ''}</div></div>`;
            }).join('')}
        </div>` : '';

    const votosRecentes = Array.isArray(perfilAtual?.votos_recentes) ? perfilAtual.votos_recentes : [];
    const renderVotoRecente = (v) => `<div class="proj-item">
            <b>${esc(v.proposicao || 'Proposição')} · voto: ${esc(v.voto || 'não informado')}</b>
            <span class="proj-sit">${esc([v.data, v.orgao].filter(Boolean).join(' · '))}</span>
            <div class="it-desc">${esc(v.descricao || v.ementa || v.resumo || 'Sem descrição.')}</div>
            ${safeUrl(v.uri) ? `<div class="proj-actions">${externalLink(v.uri, 'Dados da votação')}</div>` : ''}
        </div>`;
    const votosBox = votosRecentes.length
        ? votosRecentes.map(renderVotoRecente).join('')
        : perfilLimitado
        ? `<div class="data-warning"><b>Votações não disponíveis neste escopo.</b><span>${perfilAtual?.origem === 'tse' ? 'O cadastro do TSE contém dados eleitorais, não votos de mandato.' : 'O Senado foi consultado, mas não retornou votos nominais recentes para este perfil.'}</span></div>`
        : '<div class="data-warning"><b>Nenhum voto nominal encontrado na amostra automática.</b><span>Isso não significa que o parlamentar nunca votou. A Câmara não tem, neste fluxo, um endpoint direto completo por deputado; abra os projetos listados acima em “Ler análise da proposição” ou confira a página oficial da Câmara para validar votações específicas.</span></div>';
    const comissoesDetalhadas = Array.isArray(perfilAtual?.comissoes_detalhadas) ? perfilAtual.comissoes_detalhadas : [];
    const comissoesBox = perfilLimitado
        ? `<div class="data-warning"><b>Comissões não carregadas nesta fonte.</b><span>${perfilAtual?.origem === 'tse' ? 'O TSE não publica atuação legislativa, apenas dados eleitorais.' : 'A integração atual do Senado carrega cadastro e mandato, mas não órgãos/comissões detalhados.'}</span></div>`
        : comissoesDetalhadas.length
        ? comissoesDetalhadas.slice(0, 10).map((c) => `<div class="proj-item">
            <b>${esc(c.sigla || 'Órgão')} ${c.titulo ? `· ${esc(c.titulo)}` : ''}</b>
            <div class="it-desc">${esc(c.nome || '')}${c.data_inicio || c.data_fim ? ` · ${esc([c.data_inicio, c.data_fim || 'atual'].filter(Boolean).join(' a '))}` : ''}</div>
            ${safeUrl(c.uri) ? `<div class="proj-actions">${externalLink(c.uri, 'Dados do órgão')}</div>` : ''}
        </div>`).join('')
        : '<div class="hint">Nenhuma comissão ou órgão ativo encontrado na amostra consultada.</div>';
    const linkDiscursos = (perfilAtual?.links_externos || []).find((l) => String(l.rotulo || '').toLowerCase().includes('discurso'));
    const boxAtividadeLegislativa = `
        <div class="box full">
            <h3>🏛️ Atividade legislativa complementar</h3>
            <h4>Como votou em proposições relacionadas</h4>
            ${votosBox}
            <h4>Comissões e órgãos</h4>
            ${comissoesBox}
            ${linkDiscursos ? `<h4>Discursos e falas</h4><div class="proj-item"><b>${esc(linkDiscursos.rotulo)}</b><div class="it-desc">${esc(linkDiscursos.desc || 'Pesquisa oficial da Câmara.')}</div>${safeUrl(linkDiscursos.url) ? `<div class="proj-actions">${externalLink(linkDiscursos.url, 'Pesquisar discursos na Câmara')}</div>` : ''}</div>` : ''}
        </div>`;

    const despesas = perfilAtual?.despesas || {};
    const escopoCota = despesaEscopo(despesas);
    const alertasUnicos = collectAlertasFornecedores(a, despesas);
    const totalAlertasFornecedores = alertasUnicos.length;
    const boxAlertasFornecedores = alertasUnicos.length ? `
        <div class="box full special-alerts supplier-alerts">
            <h3>Alertas de fornecedores e CNPJ</h3>
            <p class="chart-note">Sinais de concentração, recorrência ou direcionamento a investigar. Não são acusação nem prova de irregularidade.</p>
            <div class="alert-grid">
                ${alertasUnicos.slice(0, 8).map((al) => {
                    const grav = badgeClass(al.gravidade || '');
                    const tipoAlerta = al.tipo === 'repeticao_cnpj' ? 'fornecedor_recorrente' : (al.tipo || '');
                    const titulo = al.titulo || (tipoAlerta === 'fornecedor_recorrente' ? 'Fornecedor recorrente' : 'Concentração de fornecedor');
                    const total = al.total ? fmt(al.total) : '';
                    const evidencia = al.evidencia || [
                        al.percentual ? `${Number(al.percentual).toLocaleString('pt-BR')}% dos dados lidos` : '',
                        al.qtd ? `${al.qtd} lançamento(s)` : '',
                        total,
                    ].filter(Boolean).join(' · ');
                    const termos = [
                        al.fornecedor,
                        al.documento,
                        perfilAtual?.nomeCivil || perfilAtual?.nome,
                    ].filter(Boolean).join(' ');
                    const passos = Array.isArray(al.como_verificar) ? al.como_verificar.slice(0, 4) : [];
                    const termoEmpresa = al.documento || al.fornecedor || '';
                    return `<div class="alert-card ${grav}">
                        <div class="alert-top">
                            <span class="badge ${grav}">gravidade: ${esc(al.gravidade || 'media')}</span>
                            ${tipoAlerta ? `<span class="badge">tipo: ${esc(tipoAlerta)}</span>` : ''}
                        </div>
                        <h4>${esc(titulo)}</h4>
                        <p>${esc(al.descricao) || 'Padrão de fornecedor/documento que merece conferência nos dados oficiais.'}</p>
                        ${al.fornecedor ? `<div class="alert-evidence"><b>Fornecedor:</b> ${esc(al.fornecedor)}</div>` : ''}
                        ${al.documento ? `<div class="alert-evidence"><b>CNPJ/CPF:</b> ${esc(al.documento)}</div>` : ''}
                        ${evidencia ? `<div class="alert-evidence"><b>Evidência:</b> ${esc(evidencia)}</div>` : ''}
                        ${passos.length ? `<div class="research-list">${passos.map((p) => `<span class="chip">${esc(p)}</span>`).join('')}</div>` : ''}
                        ${termos || termoEmpresa ? `<div class="research-list">
                            ${termos ? `<button type="button" class="research-chip" data-term="${esc(termos)}">Copiar busca</button>` : ''}
                            ${termoEmpresa ? `<button type="button" class="research-chip supplier-search" data-supplier-term="${esc(termoEmpresa)}" data-supplier-year="${esc(despesas.ano || '')}" data-deputado-id="${esc(perfilAtual?.id || '')}">Ver na aba Empresas</button>` : ''}
                        </div>` : ''}
                    </div>`;
                }).join('')}
            </div>
        </div>` : '';
    const fornecedores = despesas.por_fornecedor ? Object.entries(despesas.por_fornecedor).slice(0, 8).map(([nome, valor]) =>
        `<div class="proj-item"><b>${esc(nome)}</b><div class="it-desc">${fmt(valor)}</div><button type="button" class="btn-text supplier-search" data-supplier-term="${esc(nome)}" data-supplier-year="${esc(despesas.ano || '')}" data-deputado-id="${esc(perfilAtual?.id || '')}">Ver na aba Empresas</button></div>`
    ).join('') : '';
    const maioresGastos = (despesas.maiores_lancamentos || []).slice(0, 8).map((g) =>
        `<div class="proj-item"><b>${esc(g.tipo)}</b> <span class="proj-sit">${esc((g.data || '').slice(0, 10) || 'sem data')}</span><div class="it-desc">${esc(g.fornecedor)} · ${fmt(g.valor)}</div><button type="button" class="btn-text supplier-search" data-supplier-term="${esc(g.documento_fornecedor || g.fornecedor || '')}" data-supplier-year="${esc(despesas.ano || '')}" data-deputado-id="${esc(perfilAtual?.id || '')}">Ver na aba Empresas</button></div>`
    ).join('');
    const boxGastosDetalhados = (fornecedores || maioresGastos) ? `
        <div class="box full">
            <h3>Detalhamento da cota parlamentar <span class="hint" style="font-weight:400">(${esc(escopoCota.detalhe)})</span></h3>
            ${!escopoCota.completo ? `<div class="data-warning"><b>Valor parcial.</b><span>A consulta atingiu o limite de páginas configurado antes de confirmar o fim do ano.</span></div>` : ''}
            <p class="chart-note">Valores somados pelo campo oficial <b>valorLiquido</b> da Câmara dos Deputados.</p>
            ${fornecedores ? `<h4>Principais fornecedores</h4>${fornecedores}` : ''}
            ${maioresGastos ? `<h4>Maiores lançamentos individuais</h4>${maioresGastos}` : ''}
        </div>` : '';

    // Links externos verificados (TSE etc.)
    const links = perfilAtual?.links_externos || [];
    const resumoTexto = [
        a.nome || 'Análise parlamentar',
        a.resumo ? `Resumo: ${a.resumo}` : '',
        a.atuacao_legislativa ? `Atuação legislativa: ${a.atuacao_legislativa}` : '',
        a.parecer_geral ? `Parecer: ${a.parecer_geral}` : '',
    ].filter(Boolean).join('\n\n');
    const dadosObjetivos = (Array.isArray(a.dados_objetivos) && a.dados_objetivos.length) ? a.dados_objetivos : [
        { titulo: 'Identificação', descricao: `${a.nome || perfilAtual?.nome || 'Parlamentar'} · ${a.cargo || perfilAtual?.cargo || ''} · ${a.partido_uf || ''}`, confianca: 'alta' },
        { titulo: 'Cota parlamentar', descricao: `${fmt(despesas.total || 0)} · ${escopoCota.detalhe}`, confianca: 'alta' },
        { titulo: 'Produção legislativa lida', descricao: `${resumoProjetos.total || 0} proposição(ões) na amostra oficial exibida.`, confianca: 'alta' },
    ];
    const leituraIa = (Array.isArray(a.leitura_ia) && a.leitura_ia.length) ? a.leitura_ia : [
        { titulo: 'Atuação legislativa', descricao: a.atuacao_legislativa || 'Sem leitura estruturada.', confianca: 'media' },
        { titulo: 'Perfil de gastos', descricao: a.perfil_gastos || 'Sem leitura estruturada.', confianca: 'media' },
    ];
    const indicadores = (Array.isArray(a.indicadores_comparativos) && a.indicadores_comparativos.length) ? a.indicadores_comparativos : [
        { titulo: 'Comparativo contextual', descricao: 'Médias por Câmara, partido, UF, período ou categoria não foram calculadas nesta análise.', base: 'indisponível', confianca: 'baixa' },
    ];
    const parlamentarOverviewItems = perfilLimitado ? [
        overviewCard('Fonte', perfilAtual?.origem === 'tse' ? 'TSE' : 'Senado', 'escopo do registro', 'accent'),
        overviewCard('Cargo', perfilAtual?.cargo || a.cargo || '—', 'cadastro/perfil'),
        overviewCard('UF', perfilAtual?.uf || '—', perfilAtual?.municipio || 'unidade federativa'),
        overviewCard('Projetos carregados', resumoProjetos.total || 0, 'não disponível nesta fonte', 'warn-card'),
    ] : [
        overviewCard('Projetos', resumoProjetos.total || 0, resumoProjetos.fonte === 'proposicoes' ? 'calculado da lista oficial' : 'amostra oficial'),
        overviewCard('Aprovados', resumoProjetos.aprovada || 0, 'viraram lei/norma', 'ok-card'),
        overviewCard('Arquivados', resumoProjetos.arquivada || 0, 'rejeitados/prejudicados', (resumoProjetos.arquivada || 0) ? 'warn-card' : ''),
        overviewCard('Em tramitação', resumoProjetos.tramitando || 0, 'ainda em análise'),
        overviewCard('Cota parlamentar', fmt(despesas.total || 0), escopoCota.base),
        overviewCard('Alertas fornecedores', totalAlertasFornecedores, 'concentração/recorrência', totalAlertasFornecedores ? 'danger-card' : ''),
        overviewCard('Emendas', em.disponivel ? (em.qtd || 0) : '—', em.disponivel ? 'Portal da Transparência' : 'token não configurado', 'accent'),
    ];

    $('#resultadoP').innerHTML = `
        ${metaBar(meta || {}, { dualUpdate: true })}
        ${perfilLimitado ? `<div class="data-warning">
            <b>Escopo limitado da fonte.</b>
            <span>${perfilAtual?.origem === 'tse'
                ? 'Este registro vem do TSE e representa cadastro/resultado eleitoral, não atuação de mandato.'
                : 'Este registro vem do Senado e, nesta integração inicial, carrega perfil atual e links oficiais; produção detalhada fica como próxima etapa.'}</span>
        </div>` : ''}
        ${analiseTextualDesatualizada ? `<div class="data-warning">
            <b>Dados oficiais atualizados sem IA.</b>
            <span>Os blocos factuais abaixo foram recarregados. Resumo, parecer, pontos positivos, pontos de atenção e riscos continuam da última análise com IA.</span>
        </div>` : ''}
        <div class="res-head">
            <div class="dep-selected" style="background:transparent;border:0;padding:0;margin:0">
                <img src="${esc(foto)}" alt="" onerror="this.style.visibility='hidden'">
                <div class="dep-meta">
                    <h2 style="margin:0">${esc(a.nome)}</h2>
                    <p>${esc(a.cargo)} · ${esc(a.partido_uf)}</p>
                </div>
            </div>
            <div class="res-meta" style="margin-top:16px">
                <p><b>Resumo executivo:</b> ${esc(a.resumo_executivo || a.resumo) || '—'}</p>
                <p><b>Atuação legislativa:</b> ${esc(a.atuacao_legislativa) || '—'}</p>
                <p><b>Efetividade:</b> ${esc(a.efetividade_legislativa) || '—'}</p>
                <p><b>Perfil de gastos:</b> ${esc(a.perfil_gastos) || '—'}</p>
                ${a.impacto_trabalho ? `<p><b>Impacto no trabalho realizado:</b> ${esc(a.impacto_trabalho)}</p>` : ''}
            </div>
            <div class="nota-pill">
                <span class="tag">Transparência dos dados</span>
                <span class="nota-bar"><i style="width:${nota * 10}%"></i></span>
                <span class="nota-val">${nota}/10</span>
            </div>
        </div>

        ${overviewGrid(parlamentarOverviewItems)}

        <div class="sec-grid">
            ${renderStructuredBlock('Dados objetivos', dadosObjetivos, 'Sem dados objetivos estruturados.', '', 'Bloco factual extraído das fontes oficiais carregadas.')}
            ${renderStructuredBlock('Leitura da IA', leituraIa, 'Sem leitura interpretativa estruturada.', '', 'Interpretação gerada com base nos dados lidos; não deve ser tratada como fato absoluto.')}
            ${renderStructuredBlock('Indicadores e comparativos', indicadores, 'Sem comparativos disponíveis.')}
            ${renderConfiancaBlock(a.confianca_blocos, 'parlamentar')}
            ${boxProjetos}
            ${boxAtividadeLegislativa}
            ${boxAlertasFornecedores}
            ${boxGastosDetalhados}
            ${boxEmendas}
            <div class="box pos full"><h3>✅ Pontos positivos</h3>${lista(a.pontos_positivos)}</div>
            <div class="box neg full"><h3>⚠️ Pontos de atenção</h3>${lista(a.pontos_atencao)}</div>
            <div class="box risco full"><h3>🚨 Riscos</h3>${lista(a.riscos, [{k:'gravidade',label:'grav'}])}</div>
            <div class="box full"><h3>🏷️ Temas recorrentes</h3><div class="chips">${chips(a.temas_recorrentes)}</div></div>
            ${renderLimitacoes(a, {
                fontes: [perfilAtual?.fonte || 'Câmara dos Deputados', em.disponivel ? 'Portal da Transparência' : ''].filter(Boolean),
                periodo: despesas.ano || '',
                amostra: `${despesas.qtd || 0} lançamento(s) de despesa; ${resumoProjetos.total || 0} proposição(ões) na amostra`,
            })}
            ${renderFontesOficiais(a, links)}
            <div class="box full">
                <h3>📌 Parecer geral</h3>
                <p>${esc(a.parecer_geral) || '—'}</p>
                <p class="gen-note">Dados: ${esc(perfilAtual?.fonte || 'Câmara dos Deputados')}</p>
                <div class="res-actions"><button class="btn-secondary copy-summary">📋 Copiar resumo</button><button class="btn-secondary" onclick="window.print()">🖨️ Imprimir / PDF</button></div>
            </div>
        </div>`;
    $('#resultadoP').classList.remove('hidden');
    bindAtualizar('#resultadoP', {
        semIa: atualizarParlamentarSemIa,
        comIa: atualizarParlamentarComIa,
    });
    bindResearchActions('#resultadoP', []);
    bindSupplierSearch('#resultadoP');
    bindPropositionAnalysisLinks('#resultadoP');
    bindCopyResumo('#resultadoP', resumoTexto);
    $('#resultadoP').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function bindPropositionAnalysisLinks(container) {
    const root = $(container);
    if (!root) return;
    root.querySelectorAll('.proj-analysis-link').forEach((link) => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            abrirAnaliseProposicao(link.dataset.tipo, link.dataset.numero, link.dataset.ano);
        });
    });
}

/* ============ EMPRESAS / FORNECEDORES ============ */
$('#btnBuscarEmpresa')?.addEventListener('click', buscarEmpresa);
$('#empresaTermo')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') buscarEmpresa(); });
$('#btnLimparEmpresa')?.addEventListener('click', () => {
    $('#empresaTermo').value = '';
    $('#resultadoE').classList.add('hidden');
    $('#erroE').classList.add('hidden');
    $('#emptyStateE').classList.remove('hidden');
});

function bindSupplierSearch(container) {
    const root = $(container);
    if (!root) return;
    root.querySelectorAll('.supplier-search').forEach((btn) => {
        btn.addEventListener('click', () => {
            const termo = btn.dataset.supplierTerm || '';
            if (!termo) return;
            irParaView('empresas');
            $('#empresaTermo').value = termo;
            if (btn.dataset.supplierYear) $('#empresaAno').value = btn.dataset.supplierYear;
            $('#empresaLimite').value = '120';
            $('#empresaPaginas').value = '3';
            buscarEmpresa({ deputado: btn.dataset.deputadoId || '' });
        });
    });
}

async function safeJsonResponse(response, fallbackMessage = 'Resposta inválida do servidor.') {
    const text = await response.text();
    if (!text.trim()) {
        throw new Error(fallbackMessage + ' O servidor retornou resposta vazia.');
    }
    try {
        return JSON.parse(text);
    } catch (e) {
        const preview = text.replace(/\s+/g, ' ').slice(0, 180);
        throw new Error(`${fallbackMessage} JSON incompleto ou inválido. ${preview ? `Trecho recebido: ${preview}` : ''}`);
    }
}

async function buscarEmpresa(extra = {}) {
    const termo = $('#empresaTermo')?.value.trim() || '';
    const ano = $('#empresaAno')?.value || new Date().getFullYear();
    const limite = $('#empresaLimite')?.value || '520';
    const paginas = $('#empresaPaginas')?.value || '1';
    if (termo.length < 2) {
        toast('Digite o nome da empresa ou ao menos parte do CNPJ.', 'err');
        return;
    }

    $('#resultadoE').classList.add('hidden');
    $('#erroE').classList.add('hidden');
    $('#emptyStateE').classList.add('hidden');
    $('#loadingE').classList.remove('hidden');
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 260000);
    try {
        const qs = new URLSearchParams({ termo, ano, limite, paginas, ...extra });
        const r = await fetch('api/empresas.php?' + qs.toString(), { signal: controller.signal });
        const j = await safeJsonResponse(r, 'Falha ao buscar fornecedores.');
        if (!j.ok) throw new Error(j.erro);
        renderEmpresa(j.dados);
    } catch (e) {
        showError('#erroE', e.name === 'AbortError' ? 'A busca demorou demais. Tente reduzir deputados verificados ou páginas por deputado.' : e.message);
        $('#erroE').classList.remove('hidden');
    } finally {
        clearTimeout(timeout);
        $('#loadingE').classList.add('hidden');
    }
}

function renderEmpresa(d) {
    const resultados = Array.isArray(d.resultados) ? d.resultados : [];
    const fornecedores = Array.isArray(d.fornecedores) ? d.fornecedores : [];
    const empresaConsultada = d.empresa_consultada || null;
    const escopo = d.escopo || {};
    const anosVerificados = Array.isArray(d.anos_verificados) ? d.anos_verificados : [];
    const anosTexto = anosVerificados.length
        ? anosVerificados.map((a) => `${a.ano}: ${a.parlamentares || 0} parlamentar(es), ${a.lancamentos || 0} lançamento(s)`).join(' · ')
        : `ano ${d.ano || '—'}`;
    const falhasConsulta = Number(escopo.consultas_falhas || 0);
    const escopoModo = escopo.modo === 'deputado_prioritario'
        ? 'Busca priorizada no parlamentar de origem do fornecedor correlacionado.'
        : 'Varredura por deputados selecionados.';
    const maxTotal = Math.max(...resultados.map((r) => Number(r.total) || 0), 1);
    const chips = (arr) => (arr || []).length
        ? arr.slice(0, 4).map((x) => `<span class="chip">${esc(x)}</span>`).join('')
        : '<span class="hint">—</span>';
    const tipos = (obj) => Object.entries(obj || {}).slice(0, 3)
        .map(([tipo, valor]) => `${tipo}: ${fmtBRL(valor)}`).join(' · ');

    const fornecedoresBox = fornecedores.length ? `
        <div class="box full">
            <h3>Fornecedores encontrados</h3>
            <div class="supplier-list">
                ${fornecedores.slice(0, 10).map((f) => `
                    <div class="supplier-row">
                        <div>
                            <b>${esc(f.fornecedor)}</b>
                            <span>${f.documento ? `CNPJ/CPF ${esc(f.documento)} · ` : ''}${f.parlamentares?.length || 0} parlamentar(es)</span>
                            ${f.detalhe ? `<small>${esc([f.detalhe.nome_fantasia, f.detalhe.atividade, f.detalhe.municipio_uf, f.detalhe.situacao].filter(Boolean).join(' · '))}</small>` : ''}
                        </div>
                        <strong>${fmtBRL(f.total)}</strong>
                    </div>`).join('')}
            </div>
        </div>` : '';

    const portalBox = renderPortalCnpjBox(d.portal_transparencia_cnpj);
    const comprasBox = renderComprasGovBox(d.compras_gov_cnpj);
    const cnpjContexto = d.cnpj_contexto || null;
    const cnpjContextoNota = cnpjContexto?.cnpj
        ? `<div class="data-warning"><b>CNPJ complementar consultado:</b><span>${esc(cnpjContexto.cnpj)} · origem: ${esc(cnpjContexto.origem || 'não informada')}${cnpjContexto.fornecedor ? ` · ${esc(cnpjContexto.fornecedor)}` : ''}${cnpjContexto.parlamentar ? ` · ${esc(cnpjContexto.parlamentar)}` : ''}</span></div>`
        : '';

    const empresaBox = empresaConsultada ? `
        <div class="box full">
            <h3>Dados cadastrais do CNPJ consultado</h3>
            <div class="proj-item">
                <b>${esc(empresaConsultada.razao_social || 'Empresa consultada')}</b>
                <div class="it-desc">
                    ${esc([
                        empresaConsultada.nome_fantasia ? `Fantasia: ${empresaConsultada.nome_fantasia}` : '',
                        empresaConsultada.situacao ? `Situação: ${empresaConsultada.situacao}` : '',
                        empresaConsultada.atividade,
                        empresaConsultada.municipio_uf,
                        empresaConsultada.inicio_atividade ? `Início: ${empresaConsultada.inicio_atividade}` : '',
                    ].filter(Boolean).join(' · '))}
                </div>
                <p class="hint">Fonte cadastral: ${esc(empresaConsultada.fonte || 'consulta pública')}</p>
            </div>
        </div>` : '';

    const tabela = resultados.length ? `
        <div class="data-table-wrap">
            <table class="data-table company-table">
                <thead>
                    <tr>
                        <th>Parlamentar</th>
                        <th>Valor</th>
                        <th>Lançamentos</th>
                        <th>Fornecedor/documento</th>
                        <th>Tipos</th>
                    </tr>
                </thead>
                <tbody>
                    ${resultados.map((r) => {
                        const pct = Math.max(4, Math.round(((Number(r.total) || 0) / maxTotal) * 100));
                        return `<tr>
                            <td>
                                <div class="company-person">
                                    <img src="${esc(r.foto)}" alt="" onerror="this.style.visibility='hidden'">
                                    <div><b>${esc(r.nome)}</b><span>${esc(r.partido)}-${esc(r.uf)}</span></div>
                                </div>
                            </td>
                            <td>
                                <b>${fmtBRL(r.total)}</b>
                                <span class="company-bar"><i style="width:${pct}%"></i></span>
                            </td>
                            <td>${Number(r.qtd || 0).toLocaleString('pt-BR')}</td>
                            <td><div class="chips">${chips([...(r.fornecedores || []), ...(r.documentos || [])])}</div></td>
                            <td><span class="hint">${esc(tipos(r.tipos)) || '—'}</span></td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        </div>` : `<div class="impact-unavailable">
            <h4>Nenhum parlamentar encontrado nesta amostra</h4>
            <p>Foram verificados: ${esc(anosTexto)}. Tente usar CNPJ sem pontuação, reduzir o nome da empresa ou ampliar páginas por deputado.</p>
        </div>`;

    const lancamentos = resultados.flatMap((r) => (r.maiores_lancamentos || []).map((g) => ({ ...g, parlamentar: r.nome })))
        .sort((a, b) => (Number(b.valor) || 0) - (Number(a.valor) || 0))
        .slice(0, 8);
    const boxLancamentos = lancamentos.length ? `
        <div class="box full">
            <h3>Maiores lançamentos encontrados</h3>
            ${lancamentos.map((g) => {
                const dataDoc = (g.data || '').slice(0, 10);
                const anoDoc = dataDoc ? Number(dataDoc.slice(0, 4)) : 0;
                const comp = g.ano_consulta && anoDoc && Number(g.ano_consulta) !== anoDoc ? ` · competência ${g.ano_consulta}` : '';
                return `<div class="proj-item">
                <b>${esc(g.parlamentar)}</b> <span class="proj-sit">${esc(dataDoc || 'sem data')}${esc(comp)}</span>
                <div class="it-desc">${esc(g.fornecedor)} · ${esc(g.tipo)} · ${fmtBRL(g.valor)}${g.documento_fornecedor ? ` · ${esc(g.documento_fornecedor)}` : ''}</div>
            </div>`;
            }).join('')}
        </div>` : '';

    $('#resultadoE').innerHTML = `
        ${overviewGrid([
            overviewCard('Parlamentares', d.parlamentares_count || 0, 'com fornecedor na amostra', (d.parlamentares_count || 0) ? 'accent' : ''),
            overviewCard('Total encontrado', fmtBRL(d.total || 0), anosVerificados.length > 1 ? 'anos recentes' : `ano ${d.ano || '—'}`),
            overviewCard('Lançamentos', d.qtd || 0, 'despesas compatíveis'),
            overviewCard('Escopo', escopo.deputados_verificados || 0, `${escopo.paginas_por_deputado || 1} página(s) por deputado`),
        ])}
        <div class="box full special-alerts">
            <h3>Resultado da busca por empresa</h3>
            <p class="chart-note">Busca feita diretamente em despesas oficiais da Câmara. Não usa IA nem tokens. ${esc(escopoModo)} Anos verificados: ${esc(anosTexto)}. Lançamentos lidos no ano retornado: ${Number(escopo.lancamentos_lidos || 0).toLocaleString('pt-BR')}.</p>
            ${falhasConsulta ? `<div class="data-warning"><b>Cobertura parcial.</b><span>${falhasConsulta.toLocaleString('pt-BR')} consulta(s) à Câmara não responderam nesta varredura. Tente novamente para confirmar ausência de resultado.</span></div>` : ''}
            ${cnpjContextoNota}
            ${tabela}
        </div>
        <div class="sec-grid">
            ${empresaBox}
            ${portalBox}
            ${comprasBox}
            ${fornecedoresBox}
            ${boxLancamentos}
            ${renderLimitacoes({
                limitacoes: {
                    fontes_usadas: ['Câmara dos Deputados', empresaConsultada ? 'BrasilAPI CNPJ' : '', d.portal_transparencia_cnpj ? 'Portal da Transparência' : '', d.compras_gov_cnpj ? 'Compras.gov.br' : ''].filter(Boolean),
                    periodo_analisado: anosTexto,
                    tamanho_amostra: `${Number(escopo.lancamentos_lidos || d.qtd || 0).toLocaleString('pt-BR')} lançamento(s) lidos; ${Number(escopo.deputados_verificados || 0).toLocaleString('pt-BR')} deputado(s) verificado(s)`,
                    dados_ausentes: falhasConsulta ? [`${falhasConsulta.toLocaleString('pt-BR')} consulta(s) não responderam nesta varredura`] : [],
                    aviso: 'A busca por empresa organiza dados oficiais e não substitui auditoria, parecer jurídico ou investigação oficial.',
                }
            })}
        </div>`;
    $('#resultadoE').classList.remove('hidden');
    $('#resultadoE').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderPortalCnpjBox(portal) {
    if (!portal) return '';
    if (portal.disponivel === false) {
        const motivo = portal.motivo === 'sem_token'
            ? 'Token do Portal da Transparência não configurado.'
            : (portal.erro || 'CNPJ indisponível para consulta no Portal da Transparência.');
        return `<div class="box full">
            <h3>Portal da Transparência por CNPJ</h3>
            <div class="data-warning"><b>Consulta complementar não executada.</b><span>${esc(motivo)}</span></div>
        </div>`;
    }
    const modulos = Object.values(portal.modulos || {});
    const comDados = modulos.filter((m) => Number(m.qtd || 0) > 0);
    const comErro = modulos.filter((m) => m.erro);
    return `<div class="box full">
        <h3>Portal da Transparência por CNPJ</h3>
        <p class="chart-note">Consulta complementar em sanções e renúncias fiscais. Fonte: ${esc(portal.fonte || 'Portal da Transparência')}.</p>
        ${!comDados.length && comErro.length ? `<div class="data-warning"><b>Consulta complementar incompleta.</b><span>${esc(comErro[0].erro || 'Não foi possível consultar os módulos do Portal da Transparência agora.')}</span></div>` : ''}
        ${comDados.length ? comDados.map((m) => `
            <div class="proj-item">
                <b>${esc(m.rotulo)} · ${Number(m.qtd || 0).toLocaleString('pt-BR')} registro(s)</b>
                <div class="it-desc">${esc(m.descricao || '')}</div>
                ${(m.amostra || []).slice(0, 3).map((r) => `
                    <small>${esc([r.data, r.tipo, r.orgao, r.valor ? `Valor: ${r.valor}` : '', r.processo ? `Processo: ${r.processo}` : ''].filter(Boolean).join(' · '))}</small>
                `).join('')}
            </div>
        `).join('') : (!comErro.length ? '<div class="impact-unavailable"><h4>Nenhum registro encontrado nos módulos consultados</h4><p>Foram verificados CEIS, CNEP, CEPIM, acordos de leniência e bases de renúncia fiscal disponíveis para o CNPJ.</p></div>' : '')}
    </div>`;
}

function renderComprasGovBox(compras) {
    if (!compras) return '';
    if (compras.disponivel === false) {
        return `<div class="box full">
            <h3>Compras.gov.br por CNPJ</h3>
            <div class="data-warning"><b>Consulta complementar indisponível.</b><span>${esc(compras.erro || compras.motivo || 'Não foi possível consultar o Compras.gov.br agora.')}</span></div>
        </div>`;
    }
    const fornecedor = compras.fornecedor || {};
    const resultados = compras.resultados_pncp || {};
    const amostraFornecedor = Array.isArray(fornecedor.amostra) ? fornecedor.amostra : [];
    const amostraResultados = Array.isArray(resultados.amostra) ? resultados.amostra : [];
    return `<div class="box full">
        <h3>Compras.gov.br por CNPJ</h3>
        <p class="chart-note">Contexto complementar em cadastro de fornecedor e resultados PNCP. Fonte: ${esc(compras.fonte || 'Compras.gov.br')}.</p>
        ${amostraFornecedor.length ? amostraFornecedor.map((f) => `
            <div class="proj-item">
                <b>${esc(f.nome || 'Fornecedor registrado')}</b>
                <div class="it-desc">${esc([f.cnpj ? `CNPJ ${f.cnpj}` : '', f.porte, f.natureza].filter(Boolean).join(' · '))}</div>
            </div>
        `).join('') : '<div class="data-warning"><b>Cadastro de fornecedor não encontrado.</b><span>O Compras.gov.br não retornou registro ativo para o CNPJ nesta consulta.</span></div>'}
        <div class="proj-item">
            <b>Resultados PNCP nos últimos 365 dias: ${Number(resultados.total_registros || 0).toLocaleString('pt-BR')}</b>
            <div class="it-desc">Valor na amostra retornada: ${fmtBRL(resultados.valor_amostra || 0)} · período ${esc(resultados.periodo_inicio || '—')} a ${esc(resultados.periodo_fim || '—')}</div>
        </div>
        ${amostraResultados.slice(0, 5).map((r) => `
            <div class="proj-item">
                <b>${esc(r.item || 'Item contratado')}</b> <span class="proj-sit">${esc(r.data || 'sem data')}</span>
                <div class="it-desc">${esc([r.orgao, r.uf].filter(Boolean).join(' · '))} · ${fmtBRL(r.valor || 0)}${r.numero_controle ? ` · PNCP ${esc(r.numero_controle)}` : ''}</div>
            </div>
        `).join('')}
    </div>`;
}

/* ============ HISTÓRICO ============ */
const TIPO_INFO = {
    lei:         { ico: '📜', label: 'Lei/Proposição', view: 'leis' },
    parlamentar: { ico: '👤', label: 'Parlamentar', view: 'parlamentar' },
};

let historicoItens = [];

$('#btnRecarregarHist').addEventListener('click', carregarHistorico);
$('#histBusca')?.addEventListener('input', renderHistorico);
$('#histTipo')?.addEventListener('change', renderHistorico);

async function carregarHistorico() {
    const box = $('#histLista');
    box.innerHTML = '<p class="hint">⏳ Carregando...</p>';
    try {
        const r = await fetch('api/historico.php');
        const j = await r.json();
        if (!j.ok) throw new Error(j.erro);
        historicoItens = j.itens || [];
        renderHistorico();
    } catch (e) {
        showError(box, e.message, 'p');
    }
}

function renderHistorico() {
    const box = $('#histLista');
    const stats = $('#histStats');
    if (!box) return;
    const termo = ($('#histBusca')?.value || '').trim().toLowerCase();
    const tipoFiltro = $('#histTipo')?.value || '';
    const totalPorTipo = historicoItens.reduce((acc, it) => {
        acc[it.tipo] = (acc[it.tipo] || 0) + 1;
        return acc;
    }, {});

    if (stats) {
        const ativos = historicoItens.filter((it) => TIPO_INFO[it.tipo]);
        const chips = [
            ['Total', ativos.length],
            ['Leis', totalPorTipo.lei || 0],
            ['Parlamentares', totalPorTipo.parlamentar || 0],
        ];
        stats.innerHTML = chips.map(([label, value]) => `<span><b>${value}</b>${label}</span>`).join('');
    }

    const filtrados = historicoItens.filter((it) => {
        if (!TIPO_INFO[it.tipo]) return false;
        const texto = [
            it.titulo,
            it.tipo,
            it.provedor,
            it.modelo,
            TIPO_INFO[it.tipo]?.label,
        ].filter(Boolean).join(' ').toLowerCase();
        return (!tipoFiltro || it.tipo === tipoFiltro) && (!termo || texto.includes(termo));
    });

    if (!historicoItens.length) {
        box.innerHTML = '<p class="empty-state" style="padding:30px">Nenhuma análise salva ainda.</p>';
        return;
    }
    if (!filtrados.length) {
        box.innerHTML = '<p class="empty-state" style="padding:30px">Nenhum registro encontrado para este filtro.</p>';
        return;
    }

    box.innerHTML = filtrados.map((it) => {
        const t = TIPO_INFO[it.tipo] || { ico: '📄', label: it.tipo };
        const data = it.gerado_em ? new Date(it.gerado_em).toLocaleString('pt-BR') : '';
        return `<div class="hist-item" data-id="${esc(it.id)}" data-tipo="${esc(it.tipo)}">
            <div class="hist-ico">${t.ico}</div>
            <div class="hist-body">
                <div class="hist-titulo">${esc(it.titulo)}</div>
                <div class="hist-sub">${t.label} · ${PROVIDERS[it.provedor] || it.provedor || '—'} ${esc(it.modelo || '')} · ${data}</div>
            </div>
            <div class="hist-acoes">
                <button class="btn-secondary hist-abrir">Abrir</button>
                <button class="btn-text hist-excluir" title="Excluir">🗑️</button>
            </div>
        </div>`;
    }).join('');

    box.querySelectorAll('.hist-item').forEach((el) => {
        el.querySelector('.hist-abrir').addEventListener('click', () => abrirHistorico(el.dataset.id, el.dataset.tipo));
        el.querySelector('.hist-excluir').addEventListener('click', () => excluirHistorico(el.dataset.id, el));
    });
}

async function excluirHistorico(id, el) {
    if (!confirm('Excluir esta análise do histórico?')) return;
    try {
        const r = await apiFetch('api/historico.php?acao=excluir&id=' + encodeURIComponent(id), { method: 'POST' });
        const j = await r.json();
        if (!j.ok) throw new Error(j.erro || 'Falha ao excluir.');
        historicoItens = historicoItens.filter((it) => it.id !== id);
        renderHistorico();
    } catch (e) {
        toast(e.message, 'err');
    }
}

async function abrirHistorico(id) {
    const r = await fetch('api/historico.php?id=' + encodeURIComponent(id));
    const j = await r.json();
    if (!j.ok) { toast(j.erro, 'err'); return; }
    const rec = j.registro;
    if (!TIPO_INFO[rec.tipo]) {
        toast('Este tipo de análise foi removido da interface ativa.', 'err');
        return;
    }
    const meta = { cache: true, provedor: rec.provedor, modelo: rec.modelo, gerado_em: rec.gerado_em };

    // Vai para a view correspondente e re-renderiza com os dados salvos
    irParaView((TIPO_INFO[rec.tipo] || {}).view || 'leis');
    $$('.empty-state').forEach((e) => e.classList.add('hidden'));

    if (rec.tipo === 'lei') {
        lastLei = rec.extra || null;
        renderResultado(rec.payload, meta);
    } else if (rec.tipo === 'parlamentar') {
        if (!rec.extra) {
            perfilAtual = null;
            toast('Histórico antigo sem perfil parlamentar salvo. Refaça a análise para ver dados oficiais completos.', 'err');
            return;
        }
        perfilAtual = rec.extra;
        renderParlamentar(rec.payload, meta);
    }
}

/* Ativa uma view pelo nome (como clicar no menu). */
function irParaView(view) {
    const btn = document.querySelector(`.nav-item[data-view="${view}"]`);
    if (btn) btn.click();
}

/* ---------- Configurações ---------- */
const modal = $('#modalConfig');
$('#btnConfig').addEventListener('click', () => { carregarConfig(); modal.classList.remove('hidden'); });
$('#closeConfig').addEventListener('click', () => modal.classList.add('hidden'));
modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); });

async function carregarConfig() {
    const r = await fetch('api/config.php');
    const j = await r.json();
    if (!j.ok) {
        toast(j.erro || 'Não foi possível carregar as configurações.', 'err');
        return;
    }
    const c = j.config;
    currentConfig = c;
    $('#cfgStatus').textContent = '';
    $('#cfgProvider').value = c.provider;
    $('#cfgMaxTokens').value = c.max_tokens;
    updateProviderBadge(c.provider);
    $('#portalStatus').innerHTML = c.portal_has_token ? '<span class="ok">(configurado)</span>' : '<span class="fail">(não configurado)</span>';
    $('#cfgPortalToken').placeholder = c.portal_has_token ? 'Deixe em branco para manter' : 'Cole o token (grátis)';
    $('#cfgPortalToken').value = '';

    const tabs = `<div class="provider-tabs">${Object.keys(c.providers).map((nome) => `
        <button type="button" class="provider-tab ${nome === c.provider ? 'active' : ''}" data-select-provider="${nome}">
            ${esc(PROVIDERS[nome] || nome)}
        </button>`).join('')}</div>
        <p class="hint">Só o provedor ativo será usado nas próximas análises; os demais ficam salvos para troca rápida.</p>`;

    const fields = Object.entries(c.providers).map(([nome, p]) => {
        const modelos = c.model_options?.[nome] || [];
        return `<div class="prov-field cfg-provider-card ${nome === c.provider ? 'active' : 'hidden'}" data-prov-card="${nome}">
            <h4>${PROVIDERS[nome]}</h4>
            <p class="hint">${esc(PROVIDER_HELP[nome] || 'Configure a chave e o modelo deste provedor.')}</p>
            <label>Chave de API ${p.has_key ? `<span class="ok">(salva: ${p.key_preview})</span>` : '<span class="fail">(não configurada)</span>'}</label>
            <input data-prov="${nome}" data-f="api_key" type="password" placeholder="${p.has_key ? 'Deixe em branco para manter' : 'Cole a chave aqui'}">
            <label>Modelo</label>
            <select data-prov="${nome}" data-f="model">
                ${modelos.map((m) => `<option value="${esc(m)}" ${m === p.model ? 'selected' : ''}>${esc(MODEL_LABELS[m] || m)}</option>`).join('')}
            </select>
        </div>`;
    }).join('');

    $('#provedoresFields').innerHTML = tabs + fields;
    bindConfigControls();
}

function getConfiguredModel(provider) {
    const input = $(`#provedoresFields [data-prov="${provider}"][data-f="model"]`);
    return (input?.value || currentConfig?.providers?.[provider]?.model || '').trim();
}

function updateProviderBadge(provider = $('#cfgProvider')?.value) {
    if (!provider) return;
    const model = getConfiguredModel(provider);
    $('#provBadge').textContent = `${PROVIDERS[provider] || provider} · ${model || 'modelo não informado'}`;
}

function setActiveConfigProvider(provider) {
    if (!provider) return;
    $('#cfgProvider').value = provider;
    if (currentConfig) currentConfig.provider = provider;
    $$('#provedoresFields [data-prov-card]').forEach((card) => {
        card.classList.toggle('hidden', card.dataset.provCard !== provider);
        card.classList.toggle('active', card.dataset.provCard === provider);
    });
    $$('#provedoresFields [data-select-provider]').forEach((btn) => {
        btn.classList.toggle('active', btn.dataset.selectProvider === provider);
    });
    updateProviderBadge(provider);
}

function bindConfigControls() {
    $('#cfgProvider').onchange = (e) => setActiveConfigProvider(e.target.value);
    $$('#provedoresFields [data-select-provider]').forEach((btn) => {
        btn.addEventListener('click', () => setActiveConfigProvider(btn.dataset.selectProvider));
    });
    $$('#provedoresFields [data-f="model"]').forEach((select) => {
        select.addEventListener('change', () => {
            if (select.dataset.prov === $('#cfgProvider').value) updateProviderBadge(select.dataset.prov);
        });
    });
}

$('#salvarConfig').addEventListener('click', async () => {
    const btn = $('#salvarConfig');
    const providers = {};
    $$('#provedoresFields [data-prov]').forEach((el) => {
        const p = el.dataset.prov;
        providers[p] = providers[p] || {};
        providers[p][el.dataset.f] = el.value.trim();
    });
    const payload = {
        provider: $('#cfgProvider').value,
        max_tokens: parseInt($('#cfgMaxTokens').value, 10) || 8192,
        portal_transparencia_token: $('#cfgPortalToken').value.trim(),
        providers,
    };
    btn.disabled = true;
    $('#cfgStatus').innerHTML = '<span class="hint">Salvando...</span>';
    try {
        const r = await apiFetch('api/config.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
        });
        const j = await r.json();
        if (!j.ok) throw new Error(j.erro || 'Erro ao salvar');
        $('#cfgStatus').innerHTML = '<span class="ok">✓ Salvo</span>';
        toast('Configurações salvas com sucesso.', 'ok');
        await carregarConfig();
        $('#cfgStatus').innerHTML = '<span class="ok">✓ Salvo</span>';
    } catch (e) {
        $('#cfgStatus').innerHTML = `<span class="fail">${esc(e.message)}</span>`;
    } finally {
        btn.disabled = false;
    }
});

/* Carrega o badge do provedor ao iniciar */
carregarConfig();
updateSourceSummary();
