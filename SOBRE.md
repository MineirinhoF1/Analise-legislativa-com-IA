# Sobre o Projeto e Manifesto de Transparência

O **Resumo Transparente** é um sistema web independente de auditoria cidadã. Ele usa inteligência artificial para traduzir, resumir e analisar proposições legislativas, atuação de deputados federais, fornecedores de despesas públicas e perfis/cadastros políticos publicados em fontes abertas.

O objetivo é facilitar leitura crítica e investigação cidadã sem substituir fontes oficiais, parecer jurídico, auditoria formal ou trabalho jornalístico.

---

## 0. Sumário da Transparência

O sistema existe para transformar dados públicos dispersos em uma página de leitura mais clara. A transparência aqui não é uma nota automática nem uma acusação: é a capacidade de enxergar **de onde veio o dado**, **o que foi inferido**, **o que ficou de fora** e **onde conferir**.

Cada relatório deve ser lido em seis camadas:

1. **Fonte:** qual órgão, página, PDF ou API forneceu o dado.
2. **Fato observado:** metadados, textos, despesas, proposições, votações ou links que foram carregados.
3. **Leitura da IA:** resumo e interpretação feitos apenas com o material disponível.
4. **Cobrança de efetividade:** quem tende a responder por regulamentar, executar, fiscalizar ou prestar contas, quando o texto permite essa leitura.
5. **Ponto de auditoria:** risco, concentração ou lacuna que merece verificação adicional.
6. **Limite:** o que a consulta não cobre, como amostra parcial, API indisponível, PDF sem OCR ou ausência de dados estruturados.

A aba **Sumário**, posicionada ao final da navegação, apresenta esse mapa em linguagem direta. Ela explica os módulos, os tipos de proposição, o escopo dos dados parlamentares e a conversão dos dados públicos em relatórios compreensíveis.

---

## 1. Manifesto das Chaves do Usuário

O projeto adota chaves de API locais fornecidas pelo usuário.

Diferente de plataformas centralizadas que escondem o custo da IA em assinaturas, o sistema roda no servidor do cidadão ou em ambiente local. O usuário escolhe o provedor, o modelo e o limite de tokens, além de poder cadastrar o token grátis do Portal da Transparência.

- **Autonomia:** o usuário controla quais APIs usa e quanto pretende gastar.
- **Privacidade:** as chaves ficam no servidor, por variáveis de ambiente ou em `storage/private/config.json`.
- **Consciência de custo:** cache e histórico deixam claro quando uma análise foi reaproveitada sem nova chamada de IA.
- **Portabilidade:** o sistema não depende de conta central própria do projeto.

---

## 2. Fontes de Dados e Rastreabilidade

O sistema prioriza bases públicas oficiais e expõe limites quando os dados são parciais.

| Recurso | Origem | Como é usado |
|---------|--------|--------------|
| Metadados legislativos | Câmara dos Deputados | Proposições, autores, partidos, situação, tramitação e votações relacionadas. |
| Votações de proposições | Câmara dos Deputados e Senado Federal | Detalhes da votação, efeitos quando disponíveis, proposições afetadas, votos nominais, contagens por voto/partido/UF, amostra nominal e links oficiais para conferência. |
| Fallback legislativo | CSV anual da Câmara e Senado Federal | Recuperação complementar quando a busca principal não retorna dados suficientes. |
| Inteiro teor | PDFs oficiais | Extração de texto selecionável, sem OCR. |
| Perfil parlamentar | Câmara dos Deputados | Dados cadastrais, proposições de autoria, votos nominais recuperáveis, comissões, frentes e cota parlamentar. |
| Senadores em exercício | Senado Federal | Perfil atual, mandato, partido, UF, foto, e-mail e links oficiais. |
| Eleitos sem base legislativa nacional única | TSE | Cadastro e resultado eleitoral de vereadores, deputados estaduais/distritais e senadores eleitos. |
| Emendas | Portal da Transparência | Consulta por autor quando o token está configurado. |
| Fornecedores | Câmara, BrasilAPI, Portal da Transparência e Compras.gov.br | Despesas parlamentares, cadastro de CNPJ, sanções/renúncias e resultados PNCP. |
| Patrimônio e contas | TSE | Link oficial no perfil; em links compatíveis, conversão de dados públicos em texto auditável. |
| Discursos e falas | Câmara dos Deputados | Link oficial para pesquisa de discursos e notas taquigráficas por parlamentar. |
| Perfis por link | Páginas e PDFs públicos | Extração best-effort com bloqueio de URLs locais, privadas ou reservadas; podem ser fontes não oficiais e devem ser tratadas como página de origem. |

---

## 3. Metodologia de Análise Imparcial

A IA é instruída em [AnaliseService.php](src/AnaliseService.php) a seguir uma metodologia conservadora:

1. **Neutralidade:** não emitir opinião partidária, ideológica ou acusatória.
2. **Base restrita aos dados fornecidos:** não inventar fatos, processos, escândalos, declarações ou números ausentes.
3. **Separação de evidência e inferência:** indicar o que é dado observado, o que é inferência razoável e o que não pode ser avaliado.
4. **Pontos de auditoria:** classificar sinais como risco, concentração, baixa transparência ou ponto a investigar, sem afirmar irregularidade sem prova.
5. **Impacto prático:** analisar efeitos sobre trabalho, fiscalização, execução de políticas públicas, custos operacionais, grupos sociais e setores afetados.

Links oficiais preservados pelo sistema são a trilha primária de conferência. A IA resume e interpreta o material carregado, mas não substitui a fonte oficial nem deve ser apresentada como origem factual.

Em leis e proposições, o relatório inclui impactos por grupos sociais/econômicos em escala de benefício e prejuízo. Quando o texto não permite avaliar um grupo, a análise deve apontar baixa confiança ou ausência de base textual.

Também em leis e proposições, o relatório destaca quem deve ser cobrado pela efetividade prática da medida. Essa cobrança é institucional e transparente: aponta esfera federal, estadual, municipal, compartilhada ou indeterminada, diferenciando criar a regra, regulamentar, executar, fiscalizar, financiar e prestar contas. Não é atribuição automática de culpa.

Nos perfis parlamentares de deputados federais, o relatório combina leitura da IA com blocos factuais simplificados: projetos por situação, links para análise individual de proposições, comissões/órgãos, emendas, cota parlamentar e votos nominais recuperados quando houver base técnica suficiente. Esses blocos servem como evidência para conferência, não como julgamento automático de mérito político.

Nos perfis de Senado e TSE, o sistema explicita o limite: Senado traz perfil atual e mandato; TSE traz cadastro e resultado eleitoral. Esses dados ajudam a localizar e conferir a pessoa, mas não medem atuação legislativa completa.

---

## 4. Cache e Sustentabilidade

O cache em [History.php](src/History.php) reduz custo e repetição:

- análises idênticas são reaproveitadas;
- a chave inclui versão do prompt, provedor, modelo e hash dos dados;
- trocar modelo ou atualizar dados oficiais gera nova análise;
- o histórico permite reabrir resultados sem gastar tokens;
- o usuário pode atualizar dados oficiais sem IA ou forçar nova chamada quando quiser.

Esse desenho evita desperdício e deixa o custo computacional explícito.

---

## 5. Limitações Técnicas e Éticas

- **Escopo parlamentar:** a base estruturada completa cobre deputados federais. Senadores e eleitos via TSE aparecem em escopo limitado; vereadores e deputados estaduais dependem de portais legislativos locais para atuação de mandato.
- **Votos nominais:** a Câmara não oferece, neste fluxo, uma busca completa direta por deputado. O sistema tenta recuperar votos em votações ligadas às proposições exibidas e mostra a limitação quando não encontra dados.
- **PDFs sem OCR:** documentos escaneados como imagem não têm texto extraído automaticamente.
- **Busca de empresas:** a varredura é controlada por limites técnicos de deputados e páginas; o resultado é uma amostra auditável, não uma prova exaustiva.
- **Emendas por nome:** homônimos podem misturar autores no Portal da Transparência.
- **Dados de CNPJ:** enriquecimentos de BrasilAPI, Portal da Transparência e Compras.gov.br dependem da disponibilidade dessas APIs.
- **IA:** o relatório pode errar interpretação; deve ser conferido contra as fontes oficiais exibidas.

---

## 6. Compromisso do Projeto

O **Resumo Transparente** busca tornar dados públicos mais legíveis sem transformar IA em autoridade final. Seu papel é organizar evidências, levantar perguntas melhores e ajudar o cidadão a voltar às fontes oficiais com mais contexto.

As análises são instrumentos de apoio ao controle social, não conclusões definitivas sobre legalidade, mérito político ou responsabilidade individual.
