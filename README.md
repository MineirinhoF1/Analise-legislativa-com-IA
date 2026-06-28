# 🏛️ Resumo Transparente (v2)
> **Auditoria cidadã e análise legislativa inteligente de dados públicos oficiais**

[![PHP Version](https://img.shields.io/badge/php-%E2%89%A5_8.2-8892BF.svg?style=flat-square)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square)](LICENSE)
[![No Composer](https://img.shields.io/badge/dependencies-none_/_pure_php-brightgreen.svg?style=flat-square)](#tecnologias)

O **Resumo Transparente v2** é uma aplicação web em PHP puro e independente voltada para a auditoria cívica. O sistema coleta dados reais e oficiais de portais públicos brasileiros (Câmara, Senado, TSE, Portal da Transparência, PNCP) e utiliza Inteligência Artificial para gerar relatórios estruturados, imparciais e didáticos sobre propostas de leis, atuação parlamentar, gastos públicos e fornecedores.

Esta versão **v2** é totalmente autônoma (*standalone*), simplificada e otimizada para implantação rápida.

---

## 🔍 Manifesto de Transparência e Rastreabilidade

O princípio central do projeto é a **não-adulteração de fatos**. A IA não é tratada como fonte primária de verdade, mas sim como uma intérprete neutra de dados brutos que o próprio sistema puxa de APIs governamentais.

### O Método de Leitura em 6 Camadas
Cada análise e painel do sistema orienta-se em torno de seis pilares, explicados de forma detalhada na aba **Sumário** do painel principal:
1. **Fonte Oficial:** Identificação do órgão de origem e link direto de conferência (Câmara, Senado, TSE, etc.).
2. **Fato Observado:** Dados brutos e imutáveis coletados da API (votos nominais, despesas faturadas, autoria, ementas).
3. **Leitura da IA:** Resumo estruturado do texto-base da proposição ou atuação, sem adjetivações ou viés ideológico/partidário.
4. **Cobrança de Efetividade:** Indicação explícita de qual esfera (Federal, Estadual, Municipal ou Compartilhada) e órgão devem ser cobrados pela regulamentação, financiamento e execução prática da proposta.
5. **Pontos de Auditoria:** Alertas automáticos baseados em dados (ex: despesa atípica, concentração de gastos em fornecedor único, proximidade eleitoral, projetos sem texto digitalizado legível).
6. **Limitação de Escopo:** Aviso visível sobre dados ausentes ou APIs fora do ar (ex: avisos claros de que dados do TSE não medem atuação legislativa ativa, ou que o Senado não fornece detalhamento idêntico ao da Câmara).

---

## 🔑 Configuração Simplificada (Apenas Chaves)

Na versão **v2**, a configuração foi simplificada ao máximo. Você não precisa mexer em linhas de código ou arquivos de configuração complexos para começar a rodar. Há duas formas de colocar o sistema para funcionar:

### 1. Diretamente pela Web UI (Sem tocar no código)
Ao abrir a aplicação pela primeira vez:
1. Vá até a aba **Configurações**.
2. Escolha o seu provedor de IA favorito (Anthropic, OpenAI, DeepSeek ou Google Gemini) e o modelo desejado.
3. Insira sua **API Key**.
4. *(Opcional)* Cole o token gratuito do Portal da Transparência.
5. Clique em **Salvar**.

> [!IMPORTANT]
> **Privacidade Total:** As chaves ficam salvas de forma estritamente segura no servidor em `storage/private/config.json` (fora da área pública de navegação se configurado). O frontend **nunca** tem acesso à sua chave real; o painel de configurações exibe apenas uma máscara segura (ex: `••••••••5678`).

### 2. Via Variáveis de Ambiente / Arquivo `.env`
Se você estiver subindo a aplicação em um servidor de produção ou Docker, basta copiar o arquivo `.env.example` para `.env` e preencher as variáveis:
```bash
# Provedor padrão ativo (anthropic, openai, deepseek ou google)
RESUMO_PROVIDER=google

# Chaves de API das IAs (preencha a do provedor que for utilizar)
ANTHROPIC_API_KEY=sua_chave_aqui
OPENAI_API_KEY=sua_chave_aqui
DEEPSEEK_API_KEY=sua_chave_aqui
GOOGLE_API_KEY=sua_chave_aqui

# Token gratuito do Portal da Transparência (opcional)
PORTAL_TRANSPARENCIA_TOKEN=seu_token_aqui

# Diretório para armazenamento privado de dados e configurações
RESUMO_STORAGE_DIR=/caminho/fora/do/webroot/resumo-transparente-storage
```

---

## 🚀 Funcionalidades Principais

### 📄 1. Leis & Proposições
* **Busca direta e indexação:** Pesquisa por número e ano (ex: PL 2630/2020) conectando-se diretamente à API da Câmara e Senado.
* **Extração inteligente de texto:** Lê PDFs de inteiro teor diretamente do repositório oficial (com fallback para arquivos locais ou texto colado).
* **Análise estruturada:** Retorna resumo didático, objetivos, impactos de trabalho/sociais/econômicos, riscos fiscais, beneficiados/prejudicados diretos e nota de transparência do texto.
* **Votações Detalhadas:** Apresenta dados factuais de votações nominais da Câmara e Senado, com contagem agregada de votos Sim/Não/Abstenção por partido e estado, além do link direto para verificação oficial.

### 👤 2. Perfis Parlamentares
* **Deputados Federais:** Análise completa da atuação política baseada nos dados da API da Câmara (projetos propostos, gastos detalhados da cota parlamentar por ano/mês, participação em comissões e frentes parlamentares, votos em proposições analisadas).
* **Senadores em Exercício:** Perfil institucional do Senado Federal (mandato, partido, UF, e-mail, foto) com avisos explícitos de limitações de dados estruturados em relação à Câmara.
* **TSE (Candidatos e Eleitos):** Consulta integrada para vereadores, deputados estaduais, governadores e senadores com base nos cadastros eleitorais, exibindo link direto para declaração de patrimônio e prestação de contas no portal oficial DivulgaCandContas.

### 🏢 3. Varredura de Empresas & Cota
* Pesquisa fornecedores de despesas parlamentares por CNPJ, CPF ou Razão Social.
* Mostra quais deputados federais realizaram gastos na cota parlamentar com a referida empresa.
* Integração com a base de dados do Portal da Transparência (para verificação de sanções, acordos de leniência e impedimentos de licitar) e com o PNCP (Portal Nacional de Contratações Públicas).

### 💾 4. Histórico & Caching Inteligente
Para evitar custos desnecessários com tokens de APIs de IA, o sistema implementou um motor de cache em arquivos locais:
* As consultas são salvas com base no *hash* dos dados oficiais + modelo + versão do prompt.
* Se os dados do governo ou o prompt não mudarem, a resposta é reaberta instantaneamente, sem gerar novas chamadas pagas.
* O usuário pode forçar a atualização dos dados do governo mantendo a resposta da IA em cache, ou forçar uma nova análise completa se desejar.

---

## 🛠️ Tecnologias Utilizadas

O sistema foi desenhado sob o lema da **simplicidade arquitetural** e **portabilidade extrema**:
- **Backend:** PHP 8.2 puro, sem dependência do Composer, sem necessidade de banco de dados relacional complexo (utiliza arquivos flat JSON protegidos em disco local).
- **Frontend:** HTML5, CSS3 moderno (Vanilla CSS com layout fluido e responsivo) e JavaScript puro (Vanilla JS), proporcionando carregamento ultra-rápido e zero dependências de bibliotecas de terceiros no cliente.
- **Cache & Configuração:** Armazenamento estruturado local em diretório privado (`storage/private/` ou caminho customizado).

---

## 📦 Estrutura do Projeto

```text
resumotransparente/
├── index.php                    # Entrada principal do painel (SPA compacta e dinâmica)
├── README.md                    # Este arquivo de documentação
├── DOCUMENTACAO.md              # Documentação técnica detalhada das classes e fluxos
├── SOBRE.md                     # Manifesto de transparência e limites éticos
├── CONFIGURACAO_PARLAMENTARES.md# Detalhes de configuração dos dados de parlamentares
├── SECURITY.md                  # Políticas de segurança e reporte de vulnerabilidades
├── api/                         # Endpoints REST leves
│   ├── analisar.php             # Envia dados das leis para a IA
│   ├── analisar_parlamentar.php # Envia dados do parlamentar para a IA
│   ├── buscar.php               # Busca de proposições nas APIs governamentais
│   ├── parlamentar.php          # Busca dados do parlamentar
│   ├── empresas.php             # Busca e cruza dados de fornecedores/empresas
│   ├── extrair.php              # Extrai texto de páginas web
│   ├── inteiro_teor.php         # Extrai texto de PDFs das leis
│   ├── historico.php            # Gerencia salvamento e leitura do cache local
│   └── config.php               # Endpoint de leitura/escrita mascarada das chaves
├── src/                         # Classes PHP orientadas a objetos
│   ├── bootstrap.php            # Inicializador de ambiente, constantes e autoloader
│   ├── Settings.php             # Leitor/gravador de chaves e controle de provedores
│   ├── AiClient.php             # Abstração de chamadas aos SDKs de IA
│   ├── AnaliseService.php       # Montador de prompts, estruturas JSON e processador IA
│   ├── CamaraClient.php         # Cliente de integração com a API Dados Abertos Câmara
│   ├── SenadoClient.php         # Cliente de integração com a API Dados Abertos Senado
│   ├── SenadoParlamentarClient.php  # Dados de perfil e mandatos de senadores
│   ├── TseEleitosClient.php     # Dados eleitorais do TSE
│   ├── PortalTransparenciaClient.php # Consulta a sanções e emendas no Portal da Transparência
│   ├── ComprasGovClient.php     # Consulta PNCP (Compras governamentais)
│   ├── History.php              # Gerenciador de cache local de análises
│   ├── UrlGuard.php             # Proteção sanitária contra SSRF e URLs internas
│   ├── PdfText.php              # Conversor binário básico de fluxo de dados de PDF em texto
│   └── UrlExtractor.php         # Leitor e limpador de HTML público externo
├── assets/                      # Arquivos estáticos do frontend
│   ├── app.js                   # Toda a lógica da Single Page Application (UI e requisições)
│   └── style.css                # Design system responsivo e moderno (CSS puro)
└── storage/                     # Pasta de escrita da aplicação
    └── .htaccess                # Bloqueio de acesso web direto para segurança
```

---

## ⚡ Como Rodar Localmente

### Pré-requisitos
* PHP 8.2 ou superior instalado.
* Servidor web (Apache, Nginx) ou simplesmente o servidor embutido do PHP.

### Passo a Passo Rápido
1. Baixe ou clone esta pasta (`v2/`) em seu diretório de execução:
   ```bash
   git clone <URL_DO_REPOSITORIO> resumo-transparente
   cd resumo-transparente
   ```
2. Inicialize o servidor embutido do PHP para testes rápidos:
   ```bash
   php -S localhost:8000
   ```
3. Abra `http://localhost:8000` em seu navegador.
4. Vá na aba **Configurações**, configure a sua chave do provedor de IA de preferência e comece a analisar!

---

## 🤖 Provedores e Modelos Suportados na Interface

O sistema conta com mapeamento nativo para os principais LLMs do mercado. O usuário pode alternar entre eles a qualquer momento pelo painel:

| Provedor | Modelos Homologados no Painel | Onde Obter a Chave |
| :--- | :--- | :--- |
| **Google Gemini** | `gemini-3.5-flash`, `gemini-flash-latest`, `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-2.5-flash-lite` | [Google AI Studio](https://aistudio.google.com/) |
| **Anthropic Claude** | `claude-fable-5`, `claude-opus-4-8`, `claude-sonnet-4-6`, `claude-haiku-4-5` | [Anthropic Console](https://console.anthropic.com/) |
| **OpenAI GPT** | `gpt-5.5`, `gpt-5.4`, `gpt-5.4-mini`, `gpt-5.4-nano` | [OpenAI Platform](https://platform.openai.com/) |
| **DeepSeek** | `deepseek-v4-pro`, `deepseek-v4-flash` | [DeepSeek Platform](https://platform.deepseek.com/) |

---

## ⚠️ Isenção de Responsabilidade (Disclaimer)

O **Resumo Transparente** é uma ferramenta de auxílio de leitura e consolidação de dados de utilidade pública. 
* As análises geradas por IA baseiam-se única e exclusivamente no material textual fornecido na consulta e podem conter limitações inerentes aos modelos estatísticos de linguagem.
* As marcações de "Alerta de Auditoria", "Pontos de Atenção" ou "Nota de Transparência" representam indicativos baseados nos critérios estipulados de forma imparcial no código-fonte, não consistindo em acusações de atos ilícitos ou julgamentos morais e políticos.
* Recomenda-se enfaticamente o cruzamento de informações utilizando os **links diretos para os portais do Governo** disponibilizados ao final de cada relatório gerado pelo sistema.
