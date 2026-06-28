# Documentação — Resumo Transparente v2

Documentação técnica e funcional do sistema. Para uso rápido, veja [README.md](README.md). Para propósito e limites, veja [SOBRE.md](SOBRE.md).

---

## 1. Visão Geral

**Resumo Transparente v2** é uma aplicação web de transparência legislativa assistida por IA. O sistema coleta dados públicos oficiais, monta textos-base auditáveis e pede à IA uma análise estruturada, neutra e factual.

- **Backend:** PHP 8.2 puro, sem framework e sem Composer.
- **Frontend:** HTML, CSS e JavaScript vanilla.
- **IA:** Anthropic Claude, OpenAI, DeepSeek e Google Gemini.
- **Armazenamento:** JSON local em `storage/private/` por padrão, ou fora do webroot via `RESUMO_STORAGE_DIR`.
- **Fontes:** Câmara dos Deputados, Senado Federal, Portal da Transparência, Compras.gov.br, BrasilAPI, TSE e páginas/PDFs públicos.

### Princípio de design

O sistema deve diferenciar dados observados, inferências razoáveis e limites das fontes. A IA recebe instruções explícitas para não inventar fatos, não acusar crimes sem prova e apontar sinais de risco como pontos de verificação. Links oficiais carregados das integrações devem ser preservados como referência primária; a resposta da IA nunca deve ser tratada como fonte.

### Sumário explicativo

A aba **Sumário** fica no final da navegação. Ela não chama APIs; sua função é explicar, em linguagem operacional, o que cada módulo faz, quais fontes alimentam os relatórios e como interpretar a conversão dos dados públicos em análise.

| Elemento | Papel na transparência |
|----------|------------------------|
| Dados objetivos | Blocos factuais obtidos das fontes carregadas, como ementa, tramitação, despesas, proposições, votações e links oficiais. |
| Leitura da IA | Interpretação estruturada do material fornecido; deve ser conferida contra os dados exibidos. |
| Cobrança de efetividade | Indicação cautelosa de esfera, órgão ou Poder a cobrar por regulamentação, execução, fiscalização ou prestação de contas. |
| Alertas investigativos | Sinais de risco, concentração, baixa transparência ou inconsistência que merecem pesquisa adicional. |
| Fontes oficiais | Links e rótulos para voltar às bases públicas usadas ou indicadas pelo sistema. |
| Limitações | Escopo, período, amostra, dados ausentes e avisos sobre o que não foi possível verificar. |
| Cache/histórico | Registro local que informa quando uma análise foi reaproveitada sem gastar tokens. |

Regra de leitura: **um alerta indica ponto de verificação, não conclusão de irregularidade**. O sistema organiza evidências e perguntas; a conclusão depende de fonte oficial, auditoria, jornalismo ou parecer especializado.

Fluxo apresentado no sumário:

1. **Coleta:** consulta APIs e páginas públicas autorizadas.
2. **Normalização:** transforma respostas heterogêneas em texto-base auditável.
3. **Análise:** envia o texto-base ao provedor de IA configurado.
4. **Conferência:** renderiza dados, leitura, alertas, fontes e limitações em blocos separados.

Tipos de proposição explicados na interface:

- **PL — Projeto de Lei:** cria ou altera lei ordinária; costuma tratar de regras gerais, direitos, obrigações, programas e políticas públicas.
- **PEC — Proposta de Emenda à Constituição:** altera a Constituição; exige rito próprio e deve ser lida com atenção ao impacto institucional.
- **PLP — Projeto de Lei Complementar:** detalha matéria que a Constituição reservou para lei complementar; aparece em temas fiscais, federativos e estruturais.
- **MPV — Medida Provisória:** norma editada pelo Executivo com efeito imediato, dependente de apreciação do Congresso para continuar valendo.
- **PDL — Projeto de Decreto Legislativo:** instrumento usado pelo Congresso em matérias próprias, como sustação de atos, acordos, autorizações e decisões sem sanção presidencial.
- **AI — Ato Institucional (Histórico):** decreto-lei de caráter excepcional emitido no período militar (1964-1985). Por se tratarem de legislações do passado, eles não constam nas APIs de dados abertos atuais da Câmara ou do Senado para busca estruturada automática, mas a plataforma está plenamente capacitada a analisá-los a partir do campo de texto livre ("Colar texto") ou pela extração direta de URLs/PDFs de portais históricos (como o do Planalto).

---

## 2. Funcionalidades

| Área | O que faz | Fontes principais |
|------|-----------|-------------------|
| **Leis & Proposições** | Analisa texto, link ou proposição buscada por tipo/número/ano; exibe tramitação, votações relacionadas, detalhes/votos/contagens/amostra quando disponíveis e cobrança de efetividade | Câmara, Senado, HTML/PDF público |
| **Parlamentares** | Analisa atuação de deputado federal e consulta perfis/cadastros limitados de Senado/TSE | Câmara, Portal da Transparência, Senado, TSE |
| **Senado/TSE** | Consulta senadores atuais e cadastros eleitorais para vereadores, deputados estaduais/distritais e senadores eleitos | Senado, TSE |
| **Empresas** | Pesquisa fornecedores nas despesas da cota parlamentar | Câmara, BrasilAPI, Portal da Transparência, Compras.gov.br |
| **Histórico** | Lista, reabre e exclui análises salvas | Cache local |

### Entrada de leis e proposições

1. **Buscar por número:** consulta Câmara por tipo, número e ano; se necessário tenta CSV anual da Câmara e fallback do Senado.
2. **Extrair link:** baixa HTML, texto puro ou PDF público permitido.
3. **Colar texto:** envia o texto manualmente revisado pelo usuário.

Na busca estruturada por número, o sistema também carrega tramitação recente, autores, partidos dos autores e votações relacionadas. Para votações da Câmara, tenta enriquecer cada item com dados de `/votacoes/{id}` e `/votacoes/{id}/votos`, exibindo detalhes oficiais, efeitos registrados, proposições afetadas, contagens por tipo de voto, partido e UF, amostra nominal e links oficiais para conferência. O contexto inclui `votacoes_meta` para diferenciar consulta bem-sucedida sem retorno, falha da API, ID inválido ou fallback CSV sem consulta automática. Quando há complemento ou fallback do Senado, também tenta ler votações de matéria pelo serviço `/votacao`. Quando a proposição aparece no perfil parlamentar, o link **Ler análise da proposição** abre a área de Leis & Proposições, preenche tipo/número/ano, busca os dados oficiais e inicia a análise.

---

## 3. Arquitetura

```text
resumotransparente/
├── index.php                  # Página única com views e modal de configuração
├── assets/
│   ├── style.css              # Design, tema claro/escuro e componentes visuais
│   └── app.js                 # Controlador SPA simples, renderização e chamadas de API
├── api/
│   ├── analisar.php           # POST: análise de lei/proposição com cache
│   ├── analisar_parlamentar.php # POST: análise de parlamentar com cache
│   ├── buscar.php             # GET: busca proposição e tenta extrair inteiro teor
│   ├── parlamentar.php        # GET: busca deputados, senadores, cadastros TSE, partidos e perfis
│   ├── empresas.php           # GET: busca fornecedores em despesas parlamentares
│   ├── extrair.php            # GET: extrai texto de URL pública
│   ├── inteiro_teor.php       # GET: extrai texto de PDF do inteiro teor
│   ├── historico.php          # GET/POST: lista, abre e exclui histórico
│   └── config.php             # GET/POST: lê e salva configuração local
├── src/
│   ├── bootstrap.php          # Autoload, sessão, CSRF, JSON helpers e headers
│   ├── Settings.php           # Configuração validada, modelos permitidos e migração
│   ├── AiClient.php           # Cliente multi-provedor de IA
│   ├── AnaliseService.php     # Prompts, schemas e normalização do JSON da IA
│   ├── CamaraClient.php       # Proposições, autores, tramitação, votações e CSV anual
│   ├── ParlamentarClient.php  # Perfil, gastos, proposições, votos, frentes, comissões e empresas
│   ├── PortalTransparenciaClient.php
│   ├── ComprasGovClient.php
│   ├── SenadoClient.php
│   ├── SenadoParlamentarClient.php
│   ├── TseEleitosClient.php
│   ├── History.php
│   ├── UrlGuard.php
│   ├── PdfText.php
│   └── UrlExtractor.php
└── storage/                   # .htaccess e runtime privado ignorado pelo Git
```

Armazenamento runtime:

```text
storage/private/
├── config.json                # Chaves e preferências; não versionar
├── analises/                  # Histórico/cache por id sha1
├── camara/                    # CSV anual e lista de deputados atuais em cache
├── senado/                    # Lista atual de senadores em cache
├── tse/                       # ZIP/CSV oficial de candidaturas em cache
└── empresas/                  # Cache de detalhes de CNPJ
```

---

## 4. Fluxos

### 4.1 Análise de lei/proposição

```text
Frontend
  -> POST api/analisar.php { texto, contexto, atualizar }
Backend
  -> valida CSRF e tamanho mínimo
  -> monta chave: lei + versão do prompt + provedor:modelo + sha1(texto normalizado)
  -> se existir cache e atualizar=false, retorna histórico
  -> chama AiClient com o provedor ativo
  -> AnaliseService extrai, repara quando possível e normaliza JSON
  -> History salva payload e extras
  -> frontend renderiza relatório, alertas e gráficos
```

Quando a análise nasce de uma busca estruturada da Câmara, o `contexto` preserva metadados da proposição, tramitação, Senado e votações relacionadas. Nas votações, o contexto pode incluir resumo, detalhes oficiais, votos nominais, contagens e amostra nominal. Esses dados são renderizados como evidência factual e parte deles entra no texto-base enviado à IA.

### 4.2 Perfil parlamentar

```text
GET api/parlamentar.php?acao=buscar
  -> lista deputados por nome/partido, senadores por nome/partido/UF ou cadastros TSE por cargo/nome/partido/UF/município

GET api/parlamentar.php?acao=perfil&id=...
  -> para Câmara: coleta perfil, despesas, projetos, votos recuperáveis, frentes, comissões detalhadas, emendas e links externos
  -> para Senado: retorna perfil atual, mandato e links oficiais
  -> para TSE: retorna cadastro e resultado eleitoral
  -> monta texto_base auditável

POST api/analisar_parlamentar.php
  -> usa cache por versão + provedor:modelo + id + hash do texto_base
  -> chama IA apenas quando necessário
```

O perfil parlamentar inclui uma seção factual de **Atividade legislativa complementar**:

- votos nominais recuperados em votações relacionadas às proposições exibidas;
- comissões e órgãos com sigla, nome, função/título e período;
- link oficial para pesquisa de discursos e notas taquigráficas na Câmara.

Limite importante: a Câmara não oferece, neste fluxo, um endpoint direto e completo de votações por deputado. O sistema tenta localizar o parlamentar nas votações vinculadas às proposições de autoria exibidas, com timeout curto para não travar o perfil. Perfis Senado/TSE aparecem como escopo limitado e não exibem produção legislativa detalhada, despesas, comissões ou votos.

### 4.3 Empresas

```text
GET api/empresas.php?termo=...&ano=...&limite=...&paginas=...
  -> varre deputados atuais em escopo controlado
  -> busca fornecedor por nome normalizado ou documento
  -> se não encontrar no ano pedido, tenta anos anteriores quando fallback estiver ativo
  -> se identificar CNPJ, consulta BrasilAPI, Portal da Transparência e Compras.gov.br
```

---

## 5. Cache e Histórico

O cache fica em `History.php` e usa chaves determinísticas:

| Tipo | Componentes da chave |
|------|----------------------|
| Lei | versão do prompt, provedor, modelo e `sha1` do texto normalizado |
| Parlamentar | versão do prompt, provedor, modelo, id do parlamentar/registro e `sha1` do texto-base |
| Perfil por link | fluxo de parlamentar com id sintético derivado da URL/texto extraído |

Benefícios:

- consulta idêntica volta sem gastar tokens;
- troca de modelo/provedor não reaproveita análise de outro modelo;
- mudanças nos dados oficiais geram novo hash e nova análise;
- o histórico armazena `extra` para reabrir perfis, fontes e contexto.

---

## 6. Fontes e APIs

### 6.1 Câmara dos Deputados

Base: `https://dadosabertos.camara.leg.br/api/v2`

Usos:

- `/proposicoes`
- `/proposicoes/{id}`
- `/proposicoes/{id}/autores`
- `/proposicoes/{id}/tramitacoes`
- `/proposicoes/{id}/votacoes`
- `/votacoes/{id}`
- `/votacoes/{id}/votos`
- `/deputados`
- `/deputados/{id}`
- `/deputados/{id}/despesas`
- `/deputados/{id}/frentes`
- `/deputados/{id}/orgaos`

Fallback de proposições:

- `https://dadosabertos.camara.leg.br/arquivos/proposicoes/csv/proposicoes-{ano}.csv`
- cache local em `storage/private/camara/` ou em `RESUMO_STORAGE_DIR/camara/`.

Limite: cobre deputados federais. Vereadores e deputados estaduais não estão nessa base.

Votações de proposição:

- a lista vem de `/proposicoes/{id}/votacoes`;
- cada votação pode ser complementada por `/votacoes/{id}` para detalhes, efeitos e proposições afetadas;
- os votos nominais vêm de `/votacoes/{id}/votos`;
- `votacoes_meta` registra status da consulta (`sucesso`, `sem_votacoes`, `erro_api`, `id_invalido` ou `nao_consultado_csv`), mensagem exibível e link oficial do endpoint;
- o frontend preserva links oficiais para a votação e para os votos;
- as contagens por tipo de voto, partido e UF e a amostra nominal são resumos do que foi lido da API da Câmara, não inferências da IA.

Discursos e notas taquigráficas:

- link oficial: `https://www2.camara.leg.br/atividade-legislativa/discursos-e-notas-taquigraficas`
- usado como atalho de conferência no perfil parlamentar; a pesquisa detalhada por orador é feita no portal oficial.

### 6.2 Senado Federal

Base: `https://legis.senado.leg.br/dadosabertos`

- `/processo?sigla=&numero=&ano=`
- usado como complemento e fallback estruturado para proposições.
- `/votacao?sigla=&numero=&ano=`
- usado para votações nominais de matéria quando a proposição existe no Senado; o endpoint antigo `/materia/votacoes/{codigoMateria}` fica como fallback técnico.
- `/senador/lista/atual`
- usado na aba Parlamentares para listar senadores em exercício por nome, partido ou UF.

Limite atual: a integração de parlamentar no Senado carrega cadastro, mandato, foto, e-mail e links oficiais. Produção legislativa detalhada, comissões, votações e despesas ainda não são coletadas.

### 6.3 Portal da Transparência

Base: `https://api.portaldatransparencia.gov.br/api-de-dados`

Requer token grátis no header `chave-api-dados`.

Usos:

- emendas parlamentares por autor e ano;
- contexto de CNPJ na aba Empresas: CEIS, CNEP, CEPIM, acordos de leniência e renúncias.

Limite: emendas por nome podem sofrer com homônimos.

### 6.4 Compras.gov.br

Base: `https://dadosabertos.compras.gov.br`

- cadastro de fornecedor por CNPJ;
- resultados PNCP por `niFornecedor`.

### 6.5 BrasilAPI

Base: `https://brasilapi.com.br/api/cnpj/v1/{cnpj}`

Usada para enriquecer contexto cadastral de CNPJ identificado. O resultado é cacheado por até 7 dias.

### 6.6 TSE

Fontes:

- `https://cdn.tse.jus.br/estatistica/sead/odsele/consulta_cand/consulta_cand_{ano}.zip`
- `https://dadosabertos.tse.jus.br/`
- links compatíveis do DivulgaCandContas no modo por link.

Usos:

- localizar eleitos para vereador, deputado estadual/distrital e senador;
- exibir nome de urna, nome completo, partido, UF, município, número, cargo, eleição e situação do turno;
- transformar cadastro/resultado eleitoral em texto-base auditável.

Limite: TSE não informa atuação de mandato, projetos, votos, despesas parlamentares, comissões ou fornecedores. O sistema deve apresentar esse resultado como cadastro eleitoral, não como análise completa de parlamentar.

### 6.7 Extração de texto

- `UrlGuard` permite apenas URLs públicas `http`/`https`, bloqueia credenciais, hosts locais/privados/reservados, portas não permitidas e URLs longas demais.
- Nos fluxos de link e PDF, o IP público validado é fixado no cURL com `CURLOPT_RESOLVE` para reduzir janela de DNS rebinding.
- `UrlExtractor` remove ruído de HTML e limita o texto a 40.000 caracteres.
- `PdfText` tenta usar `pdftotext` quando disponível e cai para extração PHP best-effort, sem OCR.

---

## 7. Provedores e Arquitetura de IA (Inteligência Artificial)

O Resumo Transparente delega a análise interpretativa de leis e parlamentares para modelos de linguagem avançados (LLMs) configurados no painel de configurações.

### Integração Técnica por Provedor

O backend ([AiClient.php](file:///mnt/c/Servidor/web/ResumoTransparente/src/AiClient.php)) faz chamadas HTTPS nativas diretamente a cada provedor via cURL, sem bibliotecas externas:

- **Anthropic (Claude):** Utiliza a API de mensagens (`/v1/messages`) enviando as instruções do sistema no parâmetro `system` e a entrada do usuário como mensagem.
- **OpenAI (GPT):** Utiliza o novo endpoint de respostas unificadas (`/v1/responses`). Esse endpoint envia as instruções no parâmetro `instructions` e os dados do usuário no parâmetro `input`, permitindo que os novos modelos de raciocínio da OpenAI processem regras complexas e gerem saídas estruturadas baseadas no formato do parâmetro `output`.
- **DeepSeek:** Utiliza o endpoint padrão de completions compatível com a API da OpenAI (`/v1/chat/completions`) passando as mensagens de sistema e usuário.
- **Google Gemini:** Utiliza o endpoint `generateContent` via API v1beta. Para garantir que as saídas atendam rigorosamente ao formato JSON requisitado pelo sistema, é configurado o parâmetro `generationConfig` com `responseMimeType: "application/json"`.

### Versionamento dos Prompts e Lógica de Neutralidade

Os prompts de sistema aplicados a cada análise estão concentrados em [AnaliseService.php](file:///mnt/c/Servidor/web/ResumoTransparente/src/AnaliseService.php). O versionamento é controlado por constantes de versão para invalidar caches antigos sempre que a instrução do prompt é atualizada:
- `VERSION_LEI = 'lei-neutral-v5-2026-06-27'`
- `VERSION_PARLAMENTAR = 'parlamentar-neutral-v4-2026-06-26'`
- `VERSION_COMPARACAO = 'comparacao-neutral-v2-2026-06-25'`
- `VERSION_RELACIONAR = 'relacionar-neutral-v1-2026-06-25'`

As regras dos prompts de sistema obrigam o modelo a manter neutralidade absoluta, separar dados de inferências, não acusar órgãos/pessoas de crime (usando termos como "risco a verificar"), e atribuir notas e escalas de impacto apenas com base em dados concretos fornecidos.

### Algoritmo de Recuperação de JSON Truncado

Como o sistema lida com saídas complexas em JSON, há risco de a resposta da IA ser cortada pelo limite de tokens (`max_tokens`). Para contornar isso de forma resiliente, o método `repararJsonTruncado()` em [AnaliseService.php](file:///mnt/c/Servidor/web/ResumoTransparente/src/AnaliseService.php) analisa a string inacabada caractere por caractere:
1. Rastrea se o cursor está dentro de uma string de JSON, ignorando caracteres escapados.
2. Mantém uma pilha (`stack`) de chaves `{` e colchetes `[` abertos que ainda não foram fechados.
3. Se a string foi interrompida no meio de uma chave ou valor, ela limpa vírgulas e dois-pontos pendentes no final.
4. Completa a string fechando as aspas abertas e desempilhando os caracteres de fechamento apropriados (`}` ou `]`) até obter uma estrutura sintaticamente válida para que a decodificação JSON do PHP não falhe.

### Computação do Cache Determinístico

O histórico evita chamadas redundantes à API de IA computando uma chave hash baseada em SHA1 que combina:
- A constante de versão do prompt.
- O nome do provedor de IA e o modelo selecionado.
- Os IDs específicos dos parlamentares/proposições (se houver).
- O hash `sha1` dos dados normalizados de entrada (por exemplo, o texto da lei).

Se os dados oficiais forem alterados nas bases públicas, o hash da entrada muda, invalidando o cache automaticamente e forçando uma nova análise na próxima solicitação.

`max_tokens` é limitado pelo backend entre `2048` e `24000`; o padrão atual é `8192`.

---

## 8. Schemas de Saída

### Lei/proposição

Campos principais:

- `titulo`, `tipo`, `identificacao`
- `resumo`
- `objetivo_principal`
- `impacto_trabalho`
- `cobranca_efetividade`
- `areas_afetadas`
- `pontos_positivos`
- `pontos_negativos`
- `riscos`
- `alertas_especiais`
- `termos_pesquisa`
- `interessados`
- `impactos_por_grupo`
- `nota_transparencia`
- `parecer_geral`

`impactos_por_grupo` espera leitura para Classe A, Classe B, Classe C, Classe D/E, empresários, trabalhadores, setor público e consumidores, com benefício e prejuízo em escala de 0 a 10.

`cobranca_efetividade` identifica, quando o texto permitir, a esfera principal a cobrar (`federal`, `estadual`, `municipal`, `compartilhada` ou `indeterminada`), responsáveis institucionais, papel esperado, forma objetiva de cobrança, critérios de efetividade e limites da inferência.

### Parlamentar

Campos principais:

- `nome`, `cargo`, `partido_uf`
- `resumo`
- `atuacao_legislativa`
- `efetividade_legislativa`
- `perfil_gastos`
- `impacto_trabalho`
- `pontos_positivos`
- `pontos_atencao`
- `alertas_fornecedores`
- `riscos`
- `temas_recorrentes`
- `nota_transparencia`
- `parecer_geral`

Alertas de fornecedores são sinais de concentração, repetição ou padrão a verificar. Não indicam irregularidade por si só.

Além do JSON interpretativo da IA, o frontend mostra blocos factuais vindos do perfil oficial:

- projetos por situação;
- links para análise individual de cada proposição;
- votos nominais recuperados em proposições relacionadas;
- comissões/órgãos com função e período;
- link oficial para discursos e notas taquigráficas;
- emendas, cota parlamentar e alertas de fornecedores.

Para Senado/TSE, o mesmo schema é normalizado, mas os blocos factuais indicam escopo limitado. O relatório não deve sugerir que cadastro eleitoral do TSE equivale a atuação legislativa.

Regra de fonte: `fontes_oficiais` deve apontar para órgãos, APIs, documentos ou páginas oficiais efetivamente usados ou preservados. Conteúdo gerado pela IA, resumo textual e leitura interpretativa não devem ser listados como fonte oficial.

---

## 9. Segurança

- Chaves de API e token do Portal ficam fora do webroot.
- O frontend recebe apenas `has_key` e `key_preview`, nunca a chave completa.
- Campos de chave enviados em branco preservam valores já cadastrados.
- Endpoints mutáveis e downloads de URL externa exigem `X-CSRF-Token`.
- Corpos JSON acima de 2 MB são rejeitados.
- O histórico usa lock no índice para reduzir perda de entrada em gravações simultâneas.
- Downloads grandes de CSV/ZIP usam temporários únicos antes do `rename`.
- Sessão usa cookie `HttpOnly` e `SameSite=Lax`; em HTTPS recebe `Secure`.
- Respostas enviam `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` e `Permissions-Policy`.
- Endpoints legados de comparação e relação retornam `410 Gone`.

---

## 10. Configuração

Exemplo de `storage/private/config.json`:

```json
{
    "provider": "anthropic",
    "providers": {
        "anthropic": { "api_key": "", "model": "claude-sonnet-4-6" },
        "openai": { "api_key": "", "model": "gpt-5.5" },
        "deepseek": { "api_key": "", "model": "deepseek-v4-flash" },
        "google": { "api_key": "", "model": "gemini-3.5-flash" }
    },
    "max_tokens": 8192,
    "portal_transparencia_token": ""
}
```

Requisitos: PHP 8.2 com extensões `curl`, `zip`, `zlib`, `mbstring`, `dom`, `xml`/`SimpleXML` e `libxml`.

---

## 11. Limitações Conhecidas

- PDFs digitalizados não são lidos porque não há OCR.
- A extração de páginas SPA pode vir incompleta.
- A base parlamentar estruturada completa é federal e centrada na Câmara; Senado e TSE existem como perfis/cadastros limitados.
- Votos nominais por parlamentar são uma recuperação limitada a proposições exibidas no perfil; não representam todo o histórico de votações.
- A busca de empresas lê uma amostra controlada por limite de deputados e páginas por deputado.
- Dados do Portal da Transparência dependem de token e podem ter ambiguidade por nome.
- APIs externas podem mudar formato, limitar chamadas ou ficar indisponíveis.
- As análises são apoio ao controle social, não parecer jurídico ou auditoria formal.
- **Busca de Atos Institucionais (AI) históricos:** Atos institucionais do regime militar (ex: AI-5) não estão indexados nas APIs de dados abertos atuais da Câmara ou Senado. A busca estruturada por número falhará nesses casos, exigindo que o usuário insira o texto manualmente ou use o extrator de URLs públicas.

---

## 12. Pontos de Extensão

- Expiração automática de cache por tipo de consulta.
- Cobertura detalhada de produção, votações, despesas e comissões de senadores.
- Ampliação da recuperação de votações nominais para medir padrões de voto com cobertura mais completa.
- Exportação nativa de PDF.
- Integração TSE adicional para patrimônio, contas de campanha e financiadores.
- Métricas de completude por fonte consultada.
