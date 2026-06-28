# Resumo Transparente v2

Versão v2 do sistema web em PHP para análise legislativa com IA e dados públicos oficiais. Esta pasta é standalone e preparada para publicação no GitHub sem chaves, caches ou histórico local.

## O que o sistema faz

- **Leis & Proposições:** busca proposições por número, extrai texto de páginas ou PDFs públicos e gera análise padronizada com resumo, objetivo, impactos, votações relacionadas, pontos positivos, pontos negativos, riscos, alertas de auditoria, cobrança de efetividade e parecer geral.
- **Parlamentares:** busca deputados federais, senadores em exercício e cadastros eleitorais do TSE. Deputados federais recebem análise completa de atuação; Senado e TSE aparecem com escopo limitado e aviso claro sobre o que a fonte não cobre.
- **Senadores e TSE:** permite buscar senadores em exercício pelo Senado e cadastros eleitorais do TSE para vereadores, deputados estaduais/distritais e senadores eleitos. Esses escopos são identificados como perfil/cadastro quando ainda não há atuação legislativa detalhada.
- **Empresas:** pesquisa fornecedores por nome, CNPJ ou CPF nas despesas da cota parlamentar e mostra deputados relacionados na amostra oficial lida.
- **Histórico:** salva e reabre análises já feitas, reaproveitando cache quando a consulta, o provedor, o modelo e os dados não mudaram.

## Sumário explicativo do sistema

A aba **Sumário** fica no final da navegação e funciona como guia profissional de leitura. Ela explica como os dados públicos entram no sistema, como são convertidos em texto-base e como a IA transforma esse material em relatório estruturado.

| Aba | Pergunta que responde | O que mostra | O que não conclui sozinha |
|-----|-----------------------|--------------|----------------------------|
| **Leis & Proposições** | O que uma proposta muda, quais riscos aparecem e quem deve responder pela efetividade? | Resumo, objetivo, tramitação, votações, impactos, alertas, esfera/órgão a cobrar e parecer com base no texto carregado. Nas votações da Câmara, mostra detalhes, votos nominais, contagens e amostra quando a API retorna esses dados. | Não substitui parecer jurídico nem confirma efeitos futuros ou culpa institucional automática. |
| **Parlamentares** | Como está a atuação pública de um deputado federal ou qual é o cadastro oficial de senador/eleito? | Para deputados federais: projetos, gastos, comissões, frentes, votos recuperáveis, emendas e links oficiais. Para Senado/TSE: perfil, mandato/cadastro, UF, partido e links oficiais. | TSE não mede atuação legislativa; Senado ainda não coleta produção, despesas, comissões e votos detalhados. |
| **Empresas** | Quais fornecedores aparecem nas despesas parlamentares pesquisadas? | Parlamentares relacionados, valores, lançamentos e contexto de CNPJ quando disponível. | Não é auditoria exaustiva nem prova de irregularidade. |
| **Histórico** | O que já foi analisado? | Cache, reabertura de relatórios e reaproveitamento sem nova chamada de IA. | Não atualiza automaticamente dados oficiais sem ação do usuário. |
| **Sumário** | Como interpretar tudo isso? | Mapa das áreas, fontes, limites, tipos de proposição e regras de leitura. | Não executa consulta nem análise. |

### Tipos de proposição na busca

- **PL — Projeto de Lei:** proposta para criar ou alterar uma lei comum. É o tipo mais usado para programas, regras administrativas, direitos, obrigações e políticas públicas.
- **PEC — Proposta de Emenda à Constituição:** proposta que altera o texto constitucional. Tem rito mais rígido e impacto institucional maior.
- **PLP — Projeto de Lei Complementar:** regulamenta temas que a Constituição exige tratar por lei complementar, geralmente regras estruturais, fiscais, federativas ou institucionais.
- **MPV — Medida Provisória:** ato do Poder Executivo com força imediata de lei, usado em casos de relevância e urgência, mas que precisa ser analisado pelo Congresso.
- **PDL — Projeto de Decreto Legislativo:** instrumento do Congresso para matérias de sua competência, como sustar atos do Executivo, aprovar acordos ou autorizações e tratar decisões sem sanção presidencial.

### Conversão dos dados em relatório

1. O sistema coleta dados públicos e texto disponível.
2. Os dados são normalizados em um texto-base com contexto, fonte e limites.
3. A IA analisa esse texto-base e devolve uma estrutura padronizada.
4. O frontend separa dados objetivos, leitura da IA, alertas, fontes e limitações.
5. Em Leis & Proposições, o relatório também destaca quem deve ser cobrado por efetividade: esfera federal, estadual, municipal, compartilhada ou indeterminada, quando o texto permitir essa leitura.

Links oficiais carregados das APIs e páginas públicas são preservados como caminhos de conferência. A IA não vira fonte primária: ela interpreta apenas o material fornecido e deve ser confrontada com os links e dados objetivos exibidos.

Alertas são pontos de verificação, não acusações.

## Dados coletados

- Metadados, autores, partidos dos autores, situação, tramitação e votações relacionadas de proposições na API de Dados Abertos da Câmara.
- Dados de votação da Câmara com resumo da votação, detalhes oficiais, votos nominais, contagens por tipo de voto, partido e UF, amostra nominal e links oficiais para a votação e para os votos, quando disponíveis.
- Texto do inteiro teor em PDF quando houver texto selecionável no arquivo oficial.
- Fallback por arquivo CSV anual da Câmara quando a busca principal de proposição falhar.
- Complemento ou fallback do Senado Federal quando a proposição existir nos Dados Abertos do Senado, incluindo votações nominais de matéria quando o serviço oficial retorna esses dados.
- Perfil de deputado federal, proposições de autoria, links oficiais para análise de proposições, votos nominais recuperáveis, frentes, comissões detalhadas e cota parlamentar.
- Perfil atual de senadores em exercício pelo Senado Federal, com mandato, partido, UF, foto, e-mail e links oficiais.
- Cadastro e resultado eleitoral do TSE para vereadores, deputados estaduais/distritais e senadores eleitos.
- Emendas parlamentares e dados de sanções/renúncias por CNPJ pelo Portal da Transparência, quando houver token configurado.
- Cadastro de fornecedor e resultados PNCP pelo Compras.gov.br.
- Detalhes cadastrais de CNPJ via BrasilAPI, com cache local.
- Link oficial do TSE para patrimônio e contas de campanha no perfil parlamentar.
- Link oficial de discursos e notas taquigráficas da Câmara para pesquisa manual por parlamentar.

## Tecnologias

- **Backend:** PHP 8.2 puro, sem Composer e sem framework.
- **Frontend:** HTML, CSS e JavaScript vanilla.
- **IA:** Anthropic Claude, OpenAI, DeepSeek e Google Gemini, com provedor e modelo escolhidos no painel.
- **Armazenamento local:** arquivos JSON em `storage/private/` por padrão, ou no caminho definido por `RESUMO_STORAGE_DIR`.

## Como rodar

1. Coloque a pasta `v2` acessível pelo Apache/PHP, ou inicialize um repositório Git diretamente dentro dela.
2. Inicie o **Apache** no XAMPP.
3. Acesse o caminho configurado para a pasta.
4. Opcional: copie `.env.example` para `.env` e preencha `RESUMO_STORAGE_DIR` e as chaves do provedor.
5. Alternativamente, abra **Configurações**, escolha o provedor, selecione o modelo e salve a chave pelo painel.
6. Opcionalmente, configure o token grátis do Portal da Transparência para emendas e consultas complementares de CNPJ.

## Chaves e modelos

| Provedor | Onde gerar a chave | Modelos permitidos na UI |
|----------|--------------------|--------------------------|
| Anthropic | `console.anthropic.com` | `claude-fable-5`, `claude-opus-4-8`, `claude-sonnet-4-6`, `claude-haiku-4-5` |
| OpenAI | `platform.openai.com/api-keys` | `gpt-5.5`, `gpt-5.4`, `gpt-5.4-mini`, `gpt-5.4-nano` |
| DeepSeek | `platform.deepseek.com` | `deepseek-v4-pro`, `deepseek-v4-flash` |
| Google Gemini | `aistudio.google.com/app/apikey` | `gemini-3.5-flash`, `gemini-flash-latest`, `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-2.5-flash-lite` |

As chaves ficam somente no servidor. A v2 aceita variáveis de ambiente (`ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `DEEPSEEK_API_KEY`, `GOOGLE_API_KEY`) e também pode salvar configuração local em `storage/private/config.json`. O frontend recebe apenas indicação mascarada.

## Estrutura

```text
resumotransparente/
├── index.php
├── README.md
├── DOCUMENTACAO.md
├── SOBRE.md
├── CONFIGURACAO_PARLAMENTARES.md
├── VARREDURA_SISTEMA.md
├── api/
│   ├── analisar.php
│   ├── analisar_parlamentar.php
│   ├── buscar.php
│   ├── parlamentar.php
│   ├── empresas.php
│   ├── extrair.php
│   ├── inteiro_teor.php
│   ├── historico.php
│   └── config.php
├── src/
│   ├── bootstrap.php
│   ├── Settings.php
│   ├── AiClient.php
│   ├── AnaliseService.php
│   ├── CamaraClient.php
│   ├── ParlamentarClient.php
│   ├── PortalTransparenciaClient.php
│   ├── ComprasGovClient.php
│   ├── SenadoClient.php
│   ├── SenadoParlamentarClient.php
│   ├── TseEleitosClient.php
│   ├── History.php
│   ├── UrlGuard.php
│   ├── PdfText.php
│   └── UrlExtractor.php
├── assets/
│   ├── app.js
│   └── style.css
└── storage/
    └── .htaccess
```

Dados reais e cache ficam em runtime local e não devem ser versionados:

```text
storage/private/
├── config.json
├── analises/
├── camara/
├── senado/
├── tse/
├── empresas/
├── compras-gov/
└── portal-cnpj/
```

Em produção, prefira definir `RESUMO_STORAGE_DIR` para um diretório fora do webroot.

## O que a análise retorna

Para leis e proposições, a IA retorna resumo, objetivo, áreas afetadas, impacto no trabalho, cobrança de efetividade, pontos positivos, pontos negativos, riscos, alertas especiais de auditoria, termos para pesquisa, beneficiados/prejudicados, impacto por grupo social/econômico, nota de transparência e parecer geral. O frontend também mostra dados factuais da Câmara e do Senado, como tramitação recente e votações relacionadas, quando disponíveis. Em votações, a página pode abrir os dados lidos com detalhes, efeitos, proposições afetadas, contagens, amostra nominal e links oficiais da Câmara ou do Senado. Quando a Câmara não retorna votações, o relatório diferencia retorno vazio oficial, falha de API e fallback sem consulta automática.

Para deputados federais, retorna panorama da atuação, efetividade legislativa, perfil de gastos, impacto observado, pontos positivos, pontos de atenção, alertas de fornecedores, riscos, temas recorrentes, nota de transparência e parecer geral. O relatório exibe ainda blocos factuais de projetos por situação, links para leitura/análise de cada proposição, atividade legislativa complementar, comissões/órgãos e discursos.

Para Senado e TSE, o relatório deixa claro que o escopo é limitado: Senado traz perfil atual e mandato; TSE traz cadastro/resultado eleitoral. Esses registros não devem ser lidos como análise completa de atuação legislativa.

## Observações

- O sistema não faz OCR. PDFs digitalizados como imagem precisam ter o texto copiado manualmente.
- A base estruturada completa de atuação parlamentar cobre deputados federais. Vereadores, deputados estaduais/distritais e senadores eleitos podem ser localizados via TSE/Senado, mas com escopo limitado de cadastro ou perfil.
- Votos nominais de parlamentar são recuperados de forma limitada a votações ligadas às proposições exibidas. A Câmara não fornece, neste fluxo, uma busca direta completa de votações por deputado.
- Consultas do Portal da Transparência por nome podem sofrer com homônimos.
- A busca de empresas é uma varredura controlada da cota parlamentar; ela mostra a amostra lida, não uma auditoria exaustiva de todo o histórico.
- A análise gerada por IA é apoio informativo e não substitui parecer jurídico, auditoria formal ou consulta às fontes oficiais; links oficiais devem ser preservados como referência primária sempre que estiverem disponíveis.
