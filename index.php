<?php require __DIR__ . '/src/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <title>Resumo Transparente v2 — Análise Legislativa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<button id="navToggle" class="nav-toggle" type="button" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false">☰</button>
<div id="navBackdrop" class="nav-backdrop hidden" aria-hidden="true"></div>
<div class="app">
<aside class="sidebar" id="sidebar" aria-label="Menu principal">
    <div class="sidebar-brand">
        <span class="logo">⚖️</span>
        <div class="brand-text">
            <h1>Resumo Transparente v2</h1>
            <span>Análise legislativa e dados públicos</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Análises</div>
        <button class="nav-item active" data-view="leis"><span class="ni-ico">📜</span> Leis &amp; Proposições</button>
        <button class="nav-item" data-view="parlamentar"><span class="ni-ico">👤</span> Parlamentares</button>
        <button class="nav-item" data-view="empresas"><span class="ni-ico">🏢</span> Empresas</button>
        <div class="nav-section">Registros</div>
        <button class="nav-item" data-view="historico"><span class="ni-ico">🕘</span> Histórico</button>
        <div class="nav-section">Guia</div>
        <button class="nav-item" data-view="sumario"><span class="ni-ico">🧭</span> Sumário</button>
    </nav>
    <div class="sidebar-foot">
        <div class="prov-badge" id="provBadge">—</div>
        <button id="themeToggle" class="btn-ghost theme-toggle" type="button" aria-label="Alternar modo escuro"><span>🌙</span> Tema</button>
        <button id="btnConfig" class="btn-ghost full"><span>⚙️</span> Configurações</button>
    </div>
</aside>

<div class="main-area">
<header class="page-head">
    <div>
        <h2 id="pageTitle">Leis &amp; Proposições</h2>
        <p id="pageSub">Busque a proposição por número, revise o texto carregado e gere uma análise objetiva com IA.</p>
    </div>
</header>
<div class="content">

<!-- ================= ÁREA 1: LEIS ================= -->
<section class="view active" id="view-leis">
    <div class="workflow-shell">
    <div class="card input-card">
        <div class="flow-head">
            <span class="flow-kicker">Fluxo guiado</span>
            <h3>Analisar uma proposição</h3>
            <p>Comece pela busca oficial da Câmara. Se precisar, use link ou texto manual como alternativa.</p>
        </div>

        <div class="source-layout">
        <section class="source-option primary">
            <div class="source-option-head">
                <span class="source-icon">🔎</span>
                <div>
                    <h4>Buscar por número</h4>
                    <p>Principal: consulta a API de Dados Abertos da Câmara dos Deputados.</p>
                </div>
            </div>
            <div class="grid-3">
                <div>
                    <label>Tipo</label>
                    <select id="b-tipo">
                        <option>PL</option><option>PEC</option><option>PLP</option>
                        <option>MPV</option><option>PDL</option><option>AI</option>
                    </select>
                </div>
                <div><label>Número</label><input id="b-numero" type="number" placeholder="1234"></div>
                <div><label>Ano</label><input id="b-ano" type="number" placeholder="2023"></div>
            </div>
            <div class="search-actions">
                <button id="btnBuscar" class="btn-secondary">Buscar proposição</button>
                <span id="searchStatus" class="search-status">Digite tipo, número e ano.</span>
            </div>
            <div id="buscaResultado" class="fetch-result hidden"></div>
        </section>

        <section class="source-option">
            <div class="source-option-head">
                <span class="source-icon">🔗</span>
                <div>
                    <h4>Extrair de link oficial</h4>
                    <p>Use quando a proposição estiver em uma página do Planalto, Câmara, Senado ou Diário Oficial.</p>
                </div>
            </div>
            <div class="inline-field">
                <input id="b-url" type="url" placeholder="https://www.planalto.gov.br/ccivil_03/_ato.../lei.htm">
                <button id="btnExtrair" class="btn-secondary">Extrair</button>
            </div>
            <div id="linkResultado" class="fetch-result hidden"></div>
        </section>

        <section class="source-option">
            <div class="source-option-head">
                <span class="source-icon">📝</span>
                <div>
                    <h4>Texto para revisão</h4>
                    <p>O resultado da busca ou extração aparece aqui. Também dá para colar texto manualmente.</p>
                </div>
            </div>
            <label>Texto da Lei / PEC / PL (ou ementa)</label>
            <textarea id="texto" rows="9" placeholder="Busque por número ou cole aqui o texto integral/ementa da proposição..."></textarea>
            <div class="char-count"><span id="charCount">0</span> caracteres</div>
        </section>
        </div>

        <div class="actions">
            <button id="btnAnalisar" class="btn-primary">
                <span class="btn-ico">🤖</span> Analisar com IA
            </button>
            <button id="btnLimpar" class="btn-text">Limpar</button>
        </div>
    </div>

    <aside class="guide-panel">
        <div class="guide-section">
            <span class="guide-label">Progresso</span>
            <ol class="step-list" id="leiSteps">
                <li class="active"><b>1</b><span>Inserir fonte</span></li>
                <li><b>2</b><span>Revisar texto</span></li>
                <li><b>3</b><span>Analisar com IA</span></li>
                <li><b>4</b><span>Ler relatório</span></li>
            </ol>
        </div>
        <div class="guide-section">
            <span class="guide-label">Fonte atual</span>
            <div id="sourceSummary" class="source-summary">
                <strong>Nenhuma fonte carregada</strong>
                <p>Busque por número na Câmara ou use link/texto como alternativa.</p>
            </div>
        </div>
        <div class="guide-section compact">
            <span class="guide-label">Saída esperada</span>
            <div class="mini-metrics">
                <span>Resumo</span>
                <span>Impactos</span>
                <span>Riscos</span>
                <span>Parecer</span>
            </div>
        </div>
    </aside>
    </div>

    <section id="loading" class="loading hidden">
        <div class="spinner"></div>
        <p>Analisando a proposição...</p>
        <span>Isso pode levar alguns segundos.</span>
    </section>

    <section id="erro" class="erro hidden"></section>

    <section id="resultado" class="resultado hidden"></section>

    <section id="emptyState" class="empty-state">
        <div class="empty-ico">📄</div>
        <h3>Nenhuma análise ainda</h3>
        <p>Busque uma proposição por número, revise o texto e clique em <strong>Analisar com IA</strong>.</p>
    </section>
</section>

<!-- ================= ÁREA 2: PARLAMENTARES ================= -->
<section class="view" id="view-parlamentar">
    <div class="card input-card">
        <div class="flow-head">
            <span class="flow-kicker">Busca política</span>
            <h3>Encontrar parlamentar</h3>
            <p>Escolha o escopo, filtre por nome, partido, UF ou município e selecione um resultado para analisar.</p>
        </div>

        <div class="tab-workspace parlamentar-workspace">
            <div class="form-panel">
                <div class="parlamentar-search">
                    <div>
                        <label>Fonte / escopo</label>
                        <select id="p-fonte">
                            <option value="camara">Deputado federal — Câmara</option>
                            <option value="senado">Senador — Senado</option>
                            <option value="tse">Cadastro eleitoral — TSE</option>
                        </select>
                    </div>
                    <div>
                        <label>Cargo TSE</label>
                        <select id="p-cargo">
                            <option value="vereador">Vereador — eleição 2024</option>
                            <option value="deputado_estadual">Deputado estadual/distrital — eleição 2022</option>
                            <option value="senador">Senador eleito — eleição 2022</option>
                        </select>
                    </div>
                    <div class="field-wide">
                        <label>Nome</label>
                        <input id="p-nome" type="text" placeholder="Ex: Maria, João Silva...">
                    </div>
                    <div>
                        <label>Partido</label>
                        <select id="p-partido">
                            <option value="">Todos</option>
                            <option value="AGIR">AGIR</option>
                            <option value="AVANTE">AVANTE</option>
                            <option value="CIDADANIA">CIDADANIA</option>
                            <option value="DC">DC</option>
                            <option value="MDB">MDB</option>
                            <option value="MOBILIZA">MOBILIZA</option>
                            <option value="NOVO">NOVO</option>
                            <option value="PCdoB">PCdoB</option>
                            <option value="PDT">PDT</option>
                            <option value="PL">PL</option>
                            <option value="PODE">PODE</option>
                            <option value="PP">PP</option>
                            <option value="PRD">PRD</option>
                            <option value="PSB">PSB</option>
                            <option value="PSD">PSD</option>
                            <option value="PSDB">PSDB</option>
                            <option value="PSOL">PSOL</option>
                            <option value="PT">PT</option>
                            <option value="PV">PV</option>
                            <option value="REDE">REDE</option>
                            <option value="REPUBLICANOS">REPUBLICANOS</option>
                            <option value="SOLIDARIEDADE">SOLIDARIEDADE</option>
                            <option value="UNIÃO">UNIÃO</option>
                        </select>
                    </div>
                    <div>
                        <label>UF</label>
                        <select id="p-uf">
                            <option value="">Todas</option>
                            <option value="AC">AC</option><option value="AL">AL</option><option value="AP">AP</option><option value="AM">AM</option>
                            <option value="BA">BA</option><option value="CE">CE</option><option value="DF">DF</option><option value="ES">ES</option>
                            <option value="GO">GO</option><option value="MA">MA</option><option value="MT">MT</option><option value="MS">MS</option>
                            <option value="MG">MG</option><option value="PA">PA</option><option value="PB">PB</option><option value="PR">PR</option>
                            <option value="PE">PE</option><option value="PI">PI</option><option value="RJ">RJ</option><option value="RN">RN</option>
                            <option value="RS">RS</option><option value="RO">RO</option><option value="RR">RR</option><option value="SC">SC</option>
                            <option value="SP">SP</option><option value="SE">SE</option><option value="TO">TO</option>
                        </select>
                    </div>
                    <div>
                        <label>Município (TSE)</label>
                        <input id="p-municipio" type="text" placeholder="Ex: São Paulo">
                    </div>
                    <div>
                        <label>Ano TSE</label>
                        <select id="p-ano">
                            <option value="">Automático</option>
                            <option value="2024">2024</option>
                            <option value="2022">2022</option>
                            <option value="2020">2020</option>
                        </select>
                    </div>
                    <button id="btnBuscarParlamentar" class="btn-secondary submit-cell">Buscar</button>
                </div>
                <p id="partidoStatus" class="hint">Câmara e Senado trazem dados de mandato. TSE traz cadastro/resultado eleitoral e não mede atuação legislativa.</p>
                <div id="parlamentarLista" class="dep-grid"></div>
                <div id="parlamentarSel" class="dep-selected hidden"></div>
            </div>

            <aside class="context-panel">
                <span class="guide-label">Escopos</span>
                <div class="info-stack">
                    <div class="info-card"><b>Câmara</b><span>Deputados federais, mandato, despesas e proposições.</span></div>
                    <div class="info-card"><b>Senado</b><span>Identificação e mandato de senadores em exercício.</span></div>
                    <div class="info-card"><b>TSE</b><span>Cadastro eleitoral; não representa atuação legislativa.</span></div>
                </div>
            </aside>
        </div>
    </div>

    <section id="loadingP" class="loading hidden">
        <div class="spinner"></div>
        <p>Analisando a atuação do parlamentar...</p>
        <span>Coletando gastos e proposições. Pode levar alguns segundos.</span>
    </section>

    <section id="erroP" class="erro hidden"></section>
    <section id="resultadoP" class="resultado hidden"></section>

    <section id="emptyStateP" class="empty-state">
        <div class="empty-ico">👤</div>
        <h3>Nenhum parlamentar analisado</h3>
        <p>Busque por um nome, selecione o parlamentar e clique em <strong>Analisar atuação</strong>.</p>
    </section>
</section>

<!-- ================= ÁREA 3: EMPRESAS ================= -->
<section class="view" id="view-empresas">
    <div class="card input-card">
        <div class="flow-head">
            <span class="flow-kicker">Fornecedores</span>
            <h3>Pesquisar empresa nas cotas parlamentares</h3>
            <p>Busque por nome ou CNPJ/CPF do fornecedor e veja quais deputados aparecem na amostra oficial da Câmara.</p>
        </div>

        <div class="tab-workspace empresa-workspace">
            <div class="form-panel">
                <div class="filter-grid">
                    <div class="field-wide">
                        <label>Nome da empresa ou CNPJ</label>
                        <input id="empresaTermo" type="search" placeholder="Ex: gráfica, posto, 00.000.000/0001-00">
                    </div>
                    <div>
                        <label>Ano inicial</label>
                        <input id="empresaAno" type="number" value="<?= (int)date('Y') ?>">
                    </div>
                    <div>
                        <label>Deputados verificados</label>
                        <select id="empresaLimite">
                            <option value="520">Todos — recomendado</option>
                            <option value="250">250 — médio</option>
                            <option value="120">120 — rápido</option>
                        </select>
                    </div>
                    <div>
                        <label>Páginas por deputado</label>
                        <select id="empresaPaginas">
                            <option value="1">1 página recente</option>
                            <option value="2">2 páginas</option>
                            <option value="3">3 páginas</option>
                            <option value="5">5 páginas — mais completo</option>
                        </select>
                    </div>
                </div>

                <div class="actions">
                    <button id="btnBuscarEmpresa" class="btn-primary"><span class="btn-ico">🔎</span> Buscar empresa</button>
                    <button id="btnLimparEmpresa" class="btn-text">Limpar</button>
                </div>
                <p class="hint">Esta busca não usa IA nem tokens. Se não encontrar no ano inicial, o sistema tenta automaticamente os dois anos anteriores.</p>
            </div>

            <aside class="context-panel">
                <span class="guide-label">Leitura dos dados</span>
                <div class="info-stack">
                    <div class="info-card"><b>Amostra oficial</b><span>Consulta despesas declaradas por deputados na Câmara.</span></div>
                    <div class="info-card"><b>Escopo ajustável</b><span>Mais deputados e páginas aumentam cobertura e tempo de busca.</span></div>
                    <div class="info-card"><b>Interpretação</b><span>Recorrência indica ponto de auditoria, não conclusão isolada.</span></div>
                </div>
            </aside>
        </div>
    </div>

    <section id="loadingE" class="loading hidden">
        <div class="spinner"></div>
        <p>Buscando fornecedor nas despesas...</p>
        <span>Consultando dados oficiais da Câmara. Escopos maiores podem levar mais tempo.</span>
    </section>

    <section id="erroE" class="erro hidden"></section>
    <section id="resultadoE" class="resultado hidden"></section>

    <section id="emptyStateE" class="empty-state">
        <div class="empty-ico">🏢</div>
        <h3>Nenhuma empresa pesquisada</h3>
        <p>Digite o nome ou CNPJ de um fornecedor para ver parlamentares que aparecem na amostra de despesas.</p>
    </section>
</section>

<!-- ================= ÁREA 4: HISTÓRICO ================= -->
<section class="view" id="view-historico">
    <div class="card input-card">
        <div class="hist-toolbar">
            <div>
                <span class="flow-kicker">Acervo</span>
                <h3>Histórico inteligente</h3>
                <p class="hint">Reabrir não gasta tokens; use atualizar dentro de cada análise para refazer.</p>
            </div>
            <button id="btnRecarregarHist" class="btn-ghost">🔄 Recarregar lista</button>
        </div>
        <div class="history-layout">
            <div class="form-panel">
                <div class="hist-filters">
                    <input id="histBusca" type="search" placeholder="Buscar por título, modelo ou provedor...">
                    <select id="histTipo">
                        <option value="">Todos os tipos</option>
                        <option value="lei">Leis / proposições</option>
                        <option value="parlamentar">Parlamentares</option>
                    </select>
                </div>
                <div id="histStats" class="hist-stats"></div>
                <div id="histLista" class="hist-lista"></div>
            </div>

            <aside class="context-panel">
                <span class="guide-label">Uso rápido</span>
                <div class="info-stack">
                    <div class="info-card"><b>Reabrir</b><span>Mostra a análise salva sem consumir tokens.</span></div>
                    <div class="info-card"><b>Atualizar</b><span>Refaz a consulta dentro do relatório aberto.</span></div>
                    <div class="info-card"><b>Filtrar</b><span>Use tipo e busca textual para localizar registros antigos.</span></div>
                </div>
            </aside>
        </div>
    </div>

    <section id="histResultado" class="resultado hidden"></section>
</section>

<!-- ================= SUMÁRIO ================= -->
<section class="view" id="view-sumario">
    <div class="summary-hero">
        <div>
            <span class="flow-kicker">Guia do sistema</span>
            <h3>Como ler o Resumo Transparente</h3>
            <p>O sistema transforma dados públicos em relatórios explicativos. Ele organiza evidências, mostra limites e ajuda a entender leis, parlamentares e fornecedores sem tratar análise automatizada como prova ou parecer oficial.</p>
        </div>
        <div class="summary-hero-panel">
            <b>Regra central</b>
            <span>Fato oficial, leitura da IA e alerta de auditoria são coisas diferentes.</span>
        </div>
    </div>

    <div class="summary-flow" aria-label="Fluxo de conversão dos dados">
        <div class="summary-step accent">
            <b>1</b>
            <h4>Coleta</h4>
            <p>Busca dados em APIs e páginas públicas: Câmara, Senado, Portal da Transparência, Compras.gov.br, BrasilAPI, TSE, HTML e PDFs públicos.</p>
        </div>
        <div class="summary-step">
            <b>2</b>
            <h4>Normalização</h4>
            <p>Converte respostas diferentes em um texto-base legível com fonte, período, identificação, contexto e limites da consulta.</p>
        </div>
        <div class="summary-step">
            <b>3</b>
            <h4>Análise</h4>
            <p>A IA interpreta somente o texto-base recebido e devolve resumo, impactos, riscos, alertas, limitações, cobrança de efetividade e parecer geral.</p>
        </div>
        <div class="summary-step">
            <b>4</b>
            <h4>Conferência</h4>
            <p>A interface separa dados objetivos, leitura da IA, fontes oficiais e limitações para facilitar validação do usuário.</p>
        </div>
    </div>

    <div class="summary-section-head">
        <span class="guide-label">Tipos de proposição</span>
        <h4>O que significa PL, PEC, PLP, MPV e PDL</h4>
        <p>Essas siglas definem a natureza da proposta legislativa. O tipo muda o caminho de tramitação, o peso jurídico e a forma correta de interpretar o relatório.</p>
    </div>

    <div class="proposal-grid">
        <article class="proposal-card">
            <span>PL</span>
            <h5>Projeto de Lei</h5>
            <p>Proposta para criar ou alterar uma lei comum. É o tipo mais frequente para temas como direitos, obrigações, programas, regras administrativas e políticas públicas.</p>
            <small>Exemplo de leitura: qual problema tenta resolver e quem será afetado.</small>
        </article>
        <article class="proposal-card">
            <span>PEC</span>
            <h5>Proposta de Emenda à Constituição</h5>
            <p>Altera o texto constitucional. Tem rito mais rígido e impacto institucional maior, porque mexe nas regras centrais do Estado.</p>
            <small>Exemplo de leitura: quais garantias, competências ou limites constitucionais mudam.</small>
        </article>
        <article class="proposal-card">
            <span>PLP</span>
            <h5>Projeto de Lei Complementar</h5>
            <p>Regulamenta temas que a Constituição exige tratar por lei complementar. Costuma envolver regras estruturais, fiscais, federativas ou institucionais.</p>
            <small>Exemplo de leitura: qual norma constitucional será detalhada.</small>
        </article>
        <article class="proposal-card">
            <span>MPV</span>
            <h5>Medida Provisória</h5>
            <p>Ato do Poder Executivo com força imediata de lei, usado em casos de relevância e urgência. Precisa ser analisado pelo Congresso para permanecer válido.</p>
            <small>Exemplo de leitura: urgência declarada, prazo, custo e efeitos imediatos.</small>
        </article>
        <article class="proposal-card">
            <span>PDL</span>
            <h5>Projeto de Decreto Legislativo</h5>
            <p>Instrumento do Congresso para tratar temas de sua competência, como sustar atos do Executivo, aprovar acordos, autorizações e decisões sem sanção presidencial.</p>
            <small>Exemplo de leitura: qual ato ou decisão legislativa está sendo validada ou sustada.</small>
        </article>
    </div>

    <div class="summary-section-head">
        <span class="guide-label">Módulos</span>
        <h4>O que cada aba apresenta</h4>
    </div>

    <div class="module-grid">
        <article class="module-card">
            <div class="module-head"><span class="summary-icon">📜</span><h4>Leis &amp; Proposições</h4></div>
            <p>Recebe uma proposição oficial, link público ou texto manual e explica objetivo, áreas afetadas, impactos, riscos, pontos de auditoria e quem cobrar pela efetividade.</p>
            <dl>
                <dt>Mostra</dt><dd>ementa, autores, tramitação, votações relacionadas, resumo, riscos, esfera responsável pela efetividade e parecer.</dd>
                <dt>Leia como</dt><dd>triagem para entender a proposta antes de abrir o texto oficial completo.</dd>
            </dl>
        </article>
        <article class="module-card">
            <div class="module-head"><span class="summary-icon">👤</span><h4>Parlamentares</h4></div>
            <p>Consulta três escopos: deputado federal pela Câmara, senador em exercício pelo Senado e cadastro eleitoral do TSE para cargos sem base legislativa nacional única.</p>
            <dl>
                <dt>Mostra</dt><dd>para deputados federais: proposições, cota, fornecedores, comissões, frentes, votos recuperáveis, emendas e links oficiais. Para Senado/TSE: identificação, mandato/cadastro, UF, partido e links oficiais.</dd>
                <dt>Limite</dt><dd>TSE informa candidatura/resultado eleitoral, não atuação de mandato. Senado ainda não carrega produção legislativa, despesas, comissões e votações detalhadas.</dd>
            </dl>
        </article>
        <article class="module-card">
            <div class="module-head"><span class="summary-icon">🏢</span><h4>Empresas</h4></div>
            <p>Procura fornecedores nas despesas parlamentares por nome, CNPJ ou CPF dentro da amostra configurada.</p>
            <dl>
                <dt>Mostra</dt><dd>parlamentares relacionados, valores, documentos, lançamentos e contexto de CNPJ quando disponível.</dd>
                <dt>Leia como</dt><dd>sinal de recorrência ou concentração que precisa de conferência, não prova isolada.</dd>
            </dl>
        </article>
        <article class="module-card">
            <div class="module-head"><span class="summary-icon">🕘</span><h4>Histórico</h4></div>
            <p>Reabre relatórios já gerados e informa quando uma resposta veio do cache local sem nova chamada de IA.</p>
            <dl>
                <dt>Mostra</dt><dd>tipo, título, provedor, modelo, data, conteúdo salvo e perfil/contexto usado quando disponível.</dd>
                <dt>Leia como</dt><dd>registro de consulta. O histórico foi reiniciado em 2026-06-27, com backup preservado fora da pasta ativa.</dd>
            </dl>
        </article>
        <article class="module-card">
            <div class="module-head"><span class="summary-icon">⚙️</span><h4>Configuração</h4></div>
            <p>Controla provedor de IA, modelo, limite de tokens e token opcional do Portal da Transparência.</p>
            <dl>
                <dt>Mostra</dt><dd>chaves mascaradas, modelo ativo e integrações opcionais.</dd>
                <dt>Segurança</dt><dd>as chaves ficam no servidor local e não são exibidas completas no navegador.</dd>
            </dl>
        </article>
    </div>

    <div class="summary-table-wrap">
        <table class="summary-table">
            <thead>
                <tr>
                    <th>Bloco do relatório</th>
                    <th>O que significa</th>
                    <th>Como usar</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Dados objetivos</td>
                    <td>Informações factuais coletadas das fontes carregadas.</td>
                    <td>Use como ponto principal de conferência.</td>
                </tr>
                <tr>
                    <td>Leitura da IA</td>
                    <td>Resumo interpretativo gerado a partir do texto-base.</td>
                    <td>Use como explicação inicial, não como conclusão definitiva.</td>
                </tr>
                <tr>
                    <td>Alertas</td>
                    <td>Sinais de risco, baixa transparência, concentração ou lacuna.</td>
                    <td>Use para orientar pesquisa, pedido de informação ou auditoria.</td>
                </tr>
                <tr>
                    <td>Limitações</td>
                    <td>Escopo, amostra, dados ausentes e restrições técnicas, como PDF sem OCR ou fonte eleitoral sem dados de mandato.</td>
                    <td>Leia antes de comparar pessoas, empresas ou proposições.</td>
                </tr>
                <tr>
                    <td>Fontes oficiais</td>
                    <td>Links e nomes das bases usadas ou indicadas.</td>
                    <td>Abra para validar o conteúdo original.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="summary-band">
        <div>
            <span class="guide-label">Regra profissional de leitura</span>
            <h4>O sistema organiza evidências, não sentencia fatos</h4>
            <p>Uma análise pode apontar risco, impacto provável ou baixa transparência, mas não afirma crime, dolo, ilegalidade ou responsabilidade individual sem fonte oficial suficiente. O resultado deve ser usado como triagem para leitura crítica, jornalismo, controle social, auditoria ou consulta jurídica.</p>
        </div>
        <div class="summary-points">
            <span>Fato oficial separado de interpretação</span>
            <span>Alertas tratados como hipóteses de verificação</span>
            <span>Amostra e limitações sempre relevantes</span>
            <span>Fonte original continua sendo a referência</span>
        </div>
    </div>
</section>

<footer class="footer">
    <p>Resumo Transparente · Análises geradas por IA servem como apoio, não como parecer jurídico oficial.</p>
</footer>
</div><!-- /content -->
</div><!-- /main-area -->
</div><!-- /app -->

<!-- Modal de configurações -->
<div id="modalConfig" class="modal hidden">
    <div class="modal-box">
        <div class="modal-head">
            <h2>⚙️ Configurações de IA</h2>
            <button id="closeConfig" class="btn-ghost">✕</button>
        </div>

        <label>Provedor ativo</label>
        <select id="cfgProvider">
            <option value="anthropic">Claude (Anthropic)</option>
            <option value="openai">OpenAI (GPT / Codex)</option>
            <option value="deepseek">DeepSeek</option>
            <option value="google">Google Gemini</option>
        </select>

        <div id="provedoresFields"></div>

        <label>Máx. de tokens da resposta</label>
        <input id="cfgMaxTokens" type="number" value="8192" min="2048" max="24000" step="512">

        <div class="prov-field" style="margin-top:16px">
            <h4>🏛️ Portal da Transparência (emendas parlamentares)</h4>
            <label>Token <span id="portalStatus"></span></label>
            <input id="cfgPortalToken" type="password" placeholder="Cole o token (grátis)">
            <p class="hint">Opcional. Habilita as emendas parlamentares no perfil. Gere grátis em portaldatransparencia.gov.br › API de Dados.</p>
        </div>

        <p class="hint" style="margin-top:14px">As chaves ficam salvas apenas no servidor (protegido). Deixe um campo em branco para manter a chave já cadastrada.</p>

        <div class="modal-actions">
            <button id="salvarConfig" class="btn-primary">Salvar</button>
            <span id="cfgStatus"></span>
        </div>
    </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>
