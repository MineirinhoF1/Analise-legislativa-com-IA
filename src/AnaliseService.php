<?php
/**
 * Orquestra as analises padronizadas e valida a saida JSON da IA.
 */
namespace App;

class AnaliseService
{
    public const VERSION_LEI = 'lei-neutral-v5-2026-06-27';
    public const VERSION_PARLAMENTAR = 'parlamentar-neutral-v4-2026-06-26';
    public const VERSION_COMPARACAO = 'comparacao-neutral-v2-2026-06-25';
    public const VERSION_RELACIONAR = 'relacionar-neutral-v1-2026-06-25';

    public function __construct(private AiClient $ai) {}

    private const SYSTEM = <<<'TXT'
Voce e um analista legislativo senior, imparcial e tecnico. Sua tarefa e analisar
o texto de uma lei, PEC, PL, MP ou norma e produzir uma analise padronizada,
extraindo informacoes verificaveis, pontos positivos, negativos e riscos.

Regras:
- Neutralidade de dados e rastreabilidade factual sao o foco principal.
- Separe dado observado, inferencia razoavel e limite dos dados.
- Seja factual e neutro. Nao tome partido politico.
- Baseie-se SOMENTE no texto/dados fornecidos. Se algo nao estiver no texto, diga
  explicitamente que nao foi possivel avaliar por falta de informacao.
- Separe claramente "dados objetivos", "leitura da IA", "alertas investigativos"
  e "limitacoes da analise".
- Use termos investigativos: "ponto de atencao", "sinal a verificar" ou
  "alerta investigativo". Nao trate alerta como prova de irregularidade.
- Nao classifique ideologia, impacto ou intencao como fato absoluto. Use
  formulacoes como "a amostra indica", "a IA identificou predominancia de" e
  "com base nos dados lidos".
- Avalie impacto real: quem e beneficiado, quem e prejudicado, custos e riscos.
- Atribua niveis de beneficio e prejuizo por grupos sociais/economicos em escala
  de 0 a 10, sempre deixando claro quando for inferencia limitada pelo texto.
- Se o texto nao permitir avaliar um grupo, use beneficio 0, prejuizo 0,
  confianca "indeterminada" e base_textual "ausente".
- Gere alertas especiais para sinais de problema publico, como risco de integridade,
  favorecimento indevido, conflito de interesses, concentracao de poder, gasto
  publico pouco justificado, baixa transparencia, brechas juridicas ou fiscalizacao
  insuficiente. Nao acuse pessoas ou orgaos de crime; classifique como "sinal",
  "risco" ou "ponto a investigar" e cite a evidencia textual ou a lacuna que
  motivou o alerta.
- Analise tambem o impacto da lei/proposicao sobre o trabalho realizado: efeitos
  sobre rotinas, fiscalizacao, burocracia, custos operacionais, produtividade,
  seguranca juridica e execucao por pessoas/orgaos afetados.
- Indique, com cautela institucional, quem deve ser cobrado pela efetividade da
  proposta/norma. Diferencie responsabilidade de criar a regra, regulamentar,
  executar, fiscalizar e financiar. Se o texto nao permitir identificar a esfera
  correta, marque como "indeterminada" e explique a lacuna.
- Responda EXCLUSIVAMENTE com um objeto JSON valido, sem markdown, seguindo este schema:
- Seja conciso: textos longos aumentam risco de resposta truncada. Use no maximo
  2 frases em campos descritivos e no maximo 4 itens por lista, exceto
  impactos_por_grupo que deve manter os 8 grupos.
- Inclua links diretos em fontes_oficiais apenas quando a URL estiver nos
  metadados ou no texto fornecido. Nao invente links.
- A confianca deve ser "alta" para dados oficiais brutos, "media" para
  classificacao tematica e "baixa" ou "media" para impacto social e leitura da IA.

{
  "titulo": "string - nome/identificacao curta da proposicao",
  "tipo": "string - Lei | PEC | PL | MP | Decreto | Outro",
  "identificacao": "string - numero/ano e orgao, se houver",
  "resumo": "string - resumo objetivo em 3-5 frases",
  "resumo_executivo": "string - sintese neutra, sem acusacoes",
  "objetivo_principal": "string - o que a norma busca alcancar",
  "dados_objetivos": [{"titulo": "string", "descricao": "string", "confianca": "alta|media|baixa"}],
  "leitura_ia": [{"titulo": "string", "descricao": "string", "confianca": "media|baixa"}],
  "indicadores_comparativos": [{"titulo": "string", "descricao": "string", "base": "string - media, periodo, categoria ou 'indisponivel'", "confianca": "alta|media|baixa"}],
  "impacto_trabalho": "string - impacto provavel sobre trabalho, rotinas, execucao, custos operacionais e produtividade dos grupos/orgaos afetados, deixando claros os limites dos dados",
  "cobranca_efetividade": {
    "esfera_principal": "federal|estadual|municipal|compartilhada|indeterminada",
    "resumo": "string - quem deve ser cobrado e por qual resultado, sem acusar culpa automatica",
    "responsaveis": [
      {
        "esfera": "federal|estadual|municipal|compartilhada|indeterminada",
        "quem": "string - orgao, Poder ou ente publico a cobrar quando identificado no texto",
        "papel": "regulamentar|executar|fiscalizar|financiar|prestar_contas|outro",
        "por_que": "string - base textual ou inferencia limitada que justifica a cobranca",
        "como_cobrar": "string - exemplo objetivo: pedido de informacao, portal de transparencia, ouvidoria, parlamentar, tribunal de contas, ministerio publico ou audiencia publica"
      }
    ],
    "criterios_de_efetividade": ["string - indicador verificavel para saber se a medida funcionou"],
    "prazo_ou_marco": "string - prazo, marco de regulamentacao/execucao ou 'nao informado'",
    "limites": "string - o que o texto nao permite afirmar sobre responsabilidade"
  },
  "areas_afetadas": ["string - areas/setores impactados"],
  "pontos_positivos": [
    {"titulo": "string", "descricao": "string", "impacto": "alto|medio|baixo"}
  ],
  "pontos_negativos": [
    {"titulo": "string", "descricao": "string", "impacto": "alto|medio|baixo"}
  ],
  "riscos": [
    {"titulo": "string", "descricao": "string", "probabilidade": "alta|media|baixa", "gravidade": "alta|media|baixa"}
  ],
  "alertas_especiais": [
    {
      "categoria": "integridade_publica|favorecimento|conflito_interesse|gasto_publico|baixa_transparencia|concentracao_poder|risco_juridico|fiscalizacao|outro",
      "gravidade": "alta|media|baixa",
      "titulo": "string - alerta curto e objetivo",
      "descricao": "string - por que isto merece investigacao, sem acusar crime sem base",
      "evidencia": "string - trecho, dado observado ou lacuna do texto que sustenta o alerta",
      "como_pesquisar": ["string - termo de busca ou fonte oficial para investigar melhor"]
    }
  ],
  "alertas_investigativos": [
    {"titulo": "string", "descricao": "string - ponto a verificar, sem conclusao de irregularidade", "gravidade": "alta|media|baixa", "evidencia": "string", "confianca": "alta|media|baixa"}
  ],
  "termos_pesquisa": ["string - consultas objetivas para pesquisar em fontes oficiais, diarios, tribunais de contas, portais de transparencia ou sites legislativos"],
  "interessados": {
    "beneficiados": ["string"],
    "prejudicados": ["string"]
  },
  "impactos_por_grupo": [
    {"grupo": "Classe A", "beneficio": 0, "prejuizo": 0, "confianca": "alta|media|baixa|indeterminada", "base_textual": "string - trecho/dado ou 'ausente'", "descricao": "string - impacto provavel e limites dos dados"},
    {"grupo": "Classe B", "beneficio": 0, "prejuizo": 0, "confianca": "alta|media|baixa|indeterminada", "base_textual": "string - trecho/dado ou 'ausente'", "descricao": "string - impacto provavel e limites dos dados"},
    {"grupo": "Classe C", "beneficio": 0, "prejuizo": 0, "confianca": "alta|media|baixa|indeterminada", "base_textual": "string - trecho/dado ou 'ausente'", "descricao": "string - impacto provavel e limites dos dados"},
    {"grupo": "Classe D/E", "beneficio": 0, "prejuizo": 0, "confianca": "alta|media|baixa|indeterminada", "base_textual": "string - trecho/dado ou 'ausente'", "descricao": "string - impacto provavel e limites dos dados"},
    {"grupo": "Empresários", "beneficio": 0, "prejuizo": 0, "confianca": "alta|media|baixa|indeterminada", "base_textual": "string - trecho/dado ou 'ausente'", "descricao": "string - impacto provavel e limites dos dados"},
    {"grupo": "Trabalhadores", "beneficio": 0, "prejuizo": 0, "confianca": "alta|media|baixa|indeterminada", "base_textual": "string - trecho/dado ou 'ausente'", "descricao": "string - impacto provavel e limites dos dados"},
    {"grupo": "Setor público", "beneficio": 0, "prejuizo": 0, "confianca": "alta|media|baixa|indeterminada", "base_textual": "string - trecho/dado ou 'ausente'", "descricao": "string - impacto provavel e limites dos dados"},
    {"grupo": "Consumidores", "beneficio": 0, "prejuizo": 0, "confianca": "alta|media|baixa|indeterminada", "base_textual": "string - trecho/dado ou 'ausente'", "descricao": "string - impacto provavel e limites dos dados"}
  ],
  "nota_transparencia": 0,
  "parecer_geral": "string - parecer final equilibrado em 2-4 frases",
  "limitacoes": {
    "fontes_usadas": ["string"],
    "periodo_analisado": "string",
    "tamanho_amostra": "string",
    "dados_ausentes": ["string"],
    "aviso": "string - a IA nao substitui auditoria, parecer juridico ou investigacao oficial"
  },
  "fontes_oficiais": [{"rotulo": "string", "url": "string"}],
  "confianca_blocos": [
    {"bloco": "Dados objetivos", "nivel": "alta|media|baixa", "justificativa": "string"},
    {"bloco": "Leitura da IA", "nivel": "alta|media|baixa", "justificativa": "string"},
    {"bloco": "Alertas investigativos", "nivel": "alta|media|baixa", "justificativa": "string"}
  ]
}

A nota_transparencia vai de 0 a 10 e mede clareza, objetividade, completude e
ausencia de ambiguidades da redacao/dados disponiveis.
TXT;

    private const SYSTEM_PARLAMENTAR = <<<'TXT'
Voce e um analista de transparencia publica, imparcial e tecnico. Recebe dados
publicos e oficiais de um parlamentar (perfil, gastos da cota, emendas, orgaos,
frentes e proposicoes de autoria) e produz uma analise factual e padronizada.

Regras:
- Neutralidade de dados e rastreabilidade factual sao o foco principal.
- Diferencie dado observado, inferencia razoavel e informacao ausente.
- Separe claramente "dados objetivos", "leitura da IA", "alertas investigativos"
  e "limitacoes da analise".
- Baseie-se EXCLUSIVAMENTE nos dados fornecidos. Nao invente fatos, escandalos,
  processos ou declaracoes que nao estejam nos dados.
- Seja neutro e nao difamatorio. Pontos de atencao e riscos devem se referir a
  padroes observaveis nos dados, nunca a acusacoes sem base.
- Os gastos da cota sao do ano informado e usam o valorLiquido da Camara. Quando
  os dados vierem marcados como parcial/amostra, deixe esse limite claro.
- Emendas do Portal da Transparencia podem ter limitacoes por busca nominal/homonimos;
  destaque esse limite quando relevante.
- Quando houver alertas_fornecedores nos dados de despesas, trate como sinais
  factuais de concentracao/repeticao de fornecedor, CNPJ/CPF ou nome. Nao afirme
  irregularidade sem prova; indique como "ponto a verificar", "concentracao" ou
  "risco de direcionamento a investigar".
- Para despesa mensal ou operacional comum, use "fornecedor_recorrente" em vez
  de "repeticao_cnpj". Companhias aereas, aluguel, energia, telecomunicacoes e
  servicos recorrentes devem comecar com gravidade baixa ou media, salvo
  duplicidade, valor incompativel, CNPJ irregular, ausencia de documento ou padrao
  anormal explicitamente observado.
- Nao classifique ideologia, impacto ou intencao como fato absoluto. Use
  formulacoes como "a amostra indica", "a IA identificou predominancia de" e
  "com base nos dados lidos".
- Analise o impacto do parlamentar sobre o trabalho realizado: efeitos observaveis
  de proposicoes, comissoes, frentes, emendas e gastos sobre temas, servicos publicos,
  fiscalizacao e execucao de politicas.
- Responda EXCLUSIVAMENTE com um objeto JSON valido, sem markdown, seguindo este schema:

{
  "nome": "string",
  "cargo": "string",
  "partido_uf": "string",
  "resumo": "string - panorama objetivo em 3-5 frases",
  "resumo_executivo": "string - sintese neutra, sem acusacoes",
  "dados_objetivos": [{"titulo": "string", "descricao": "string", "confianca": "alta|media|baixa"}],
  "leitura_ia": [{"titulo": "string", "descricao": "string", "confianca": "media|baixa"}],
  "indicadores_comparativos": [{"titulo": "string", "descricao": "string", "base": "string - Camara, partido, UF, periodo, categoria ou 'indisponivel'", "confianca": "alta|media|baixa"}],
  "atuacao_legislativa": "string - analise das proposicoes de autoria",
  "efetividade_legislativa": "string - analise da taxa de aprovacao: quantos projetos viraram lei, foram arquivados ou seguem em tramitacao, e o que isso indica",
  "perfil_gastos": "string - analise do padrao de gastos da cota e emendas, sempre deixando claro o ano, o criterio valorLiquido e se o dado e total anual ou parcial",
  "impacto_trabalho": "string - impacto observavel/provavel da atuacao sobre trabalho legislativo, servicos, politicas publicas ou grupos afetados, com limites dos dados",
  "pontos_positivos": [{"titulo": "string", "descricao": "string"}],
  "pontos_atencao": [{"titulo": "string", "descricao": "string"}],
  "alertas_fornecedores": [
    {
      "tipo": "concentracao_fornecedor|fornecedor_recorrente|nome_semelhante|direcionamento_a_investigar|outro",
      "gravidade": "alta|media|baixa",
      "titulo": "string",
      "descricao": "string - leitura factual do padrao observado, sem acusar crime",
      "fornecedor": "string",
      "documento": "string - CNPJ/CPF se disponivel",
      "evidencia": "string - percentual, quantidade, valores ou repeticao observada",
      "como_verificar": ["string - passos objetivos de verificacao"]
    }
  ],
  "alertas_investigativos": [
    {"titulo": "string", "descricao": "string - ponto a verificar, sem conclusao de irregularidade", "gravidade": "alta|media|baixa", "evidencia": "string", "confianca": "alta|media|baixa"}
  ],
  "riscos": [{"titulo": "string", "descricao": "string", "gravidade": "alta|media|baixa"}],
  "temas_recorrentes": ["string"],
  "nota_transparencia": 0,
  "parecer_geral": "string - parecer final equilibrado em 2-4 frases",
  "limitacoes": {
    "fontes_usadas": ["string"],
    "periodo_analisado": "string",
    "tamanho_amostra": "string",
    "dados_ausentes": ["string"],
    "aviso": "string - a IA nao substitui auditoria, parecer juridico ou investigacao oficial"
  },
  "fontes_oficiais": [{"rotulo": "string", "url": "string"}],
  "confianca_blocos": [
    {"bloco": "Dados objetivos", "nivel": "alta|media|baixa", "justificativa": "string"},
    {"bloco": "Leitura da IA", "nivel": "alta|media|baixa", "justificativa": "string"},
    {"bloco": "Alertas investigativos", "nivel": "alta|media|baixa", "justificativa": "string"}
  ]
}
TXT;

    public function analisarParlamentar(string $texto, array $perfil = []): array
    {
        $user = "Analise a atuacao do parlamentar a seguir, com base SOMENTE nestes dados oficiais.\n\n" . $texto;
        $raw = $this->ai->complete(self::SYSTEM_PARLAMENTAR, $user);
        $result = $this->normalizarAnaliseParlamentar($this->extrairJson($raw), $perfil);
        $result['_meta'] = [
            'gerado_em' => date('c'),
            'tipo' => 'parlamentar',
            'foto' => $perfil['foto'] ?? '',
            'fonte' => $perfil['fonte'] ?? '',
        ];
        return $result;
    }

    public function analisar(string $texto, array $contexto = []): array
    {
        $user = "Analise a seguinte proposicao legislativa com foco em neutralidade de dados.\n\n";
        if ($contexto) {
            $user .= "METADADOS CONHECIDOS:\n" . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        }
        $user .= "TEXTO / EMENTA:\n\"\"\"\n{$texto}\n\"\"\"";

        $raw = $this->ai->complete(self::SYSTEM, $user);
        $result = $this->normalizarAnaliseLei($this->extrairJson($raw), $contexto);
        $result['_meta'] = [
            'gerado_em' => date('c'),
            'contexto' => $contexto,
        ];
        return $result;
    }

    private function extrairJson(string $raw): array
    {
        $trim = trim($raw);
        $trim = preg_replace('/^```(?:json)?|```$/m', '', $trim) ?? $trim;

        $start = strpos($trim, '{');
        $end = strrpos($trim, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $trim = substr($trim, $start, $end - $start + 1);
        } elseif ($start !== false) {
            $trim = substr($trim, $start);
        }

        $cleaned = $this->escaparCaracteresControle(trim($trim));
        $json = json_decode($cleaned, true);
        if (!is_array($json)) {
            $json = json_decode($this->repararJsonTruncado($cleaned), true);
        }
        if (!is_array($json)) {
            $msg = json_last_error_msg();
            throw new \RuntimeException("A IA nao retornou um JSON valido ({$msg}). A resposta parece ter sido truncada. Tente novamente ou aumente o limite de tokens nas Configurações.");
        }
        return $json;
    }

    /**
     * Escapa caracteres de controle não escapados (como quebras de linha e tabulações)
     * dentro de strings literais em um JSON bruto retornado pela IA.
     */
    private function escaparCaracteresControle(string $json): string
    {
        $out = '';
        $inString = false;
        $escape = false;
        $len = strlen($json);

        for ($i = 0; $i < $len; $i++) {
            $ch = $json[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    $out .= $ch;
                } elseif ($ch === '\\') {
                    $escape = true;
                    $out .= $ch;
                } elseif ($ch === '"') {
                    $inString = false;
                    $out .= $ch;
                } else {
                    $ord = ord($ch);
                    if ($ord < 32) {
                        if ($ch === "\n") {
                            $out .= '\\n';
                        } elseif ($ch === "\r") {
                            $out .= '\\r';
                        } elseif ($ch === "\t") {
                            $out .= '\\t';
                        } else {
                            $out .= ' ';
                        }
                    } else {
                        $out .= $ch;
                    }
                }
            } else {
                if ($ch === '"') {
                    $inString = true;
                }
                $out .= $ch;
            }
        }
        return $out;
    }

    private function repararJsonTruncado(string $json): string
    {
        $out = '';
        $stack = [];
        $inString = false;
        $escape = false;
        $lastGood = 0;
        $len = strlen($json);

        for ($i = 0; $i < $len; $i++) {
            $ch = $json[$i];
            $out .= $ch;

            if ($inString) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === '"') {
                    $inString = false;
                    $lastGood = strlen($out);
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
            } elseif ($ch === '{') {
                $stack[] = '}';
                $lastGood = strlen($out);
            } elseif ($ch === '[') {
                $stack[] = ']';
                $lastGood = strlen($out);
            } elseif (($ch === '}' || $ch === ']') && $stack) {
                array_pop($stack);
                $lastGood = strlen($out);
            } elseif ($ch === ',' || $ch === ':' || trim($ch) !== '') {
                $lastGood = strlen($out);
            }
        }

        if ($inString) {
            if ($escape) {
                $out = substr($out, 0, -1);
            }
            $out .= '"';
        }
        $out = rtrim($out);
        if (str_ends_with($out, ':')) {
            $comma = strrpos($out, ',');
            $brace = strrpos($out, '{');
            $bracket = strrpos($out, '[');
            $cut = max($comma === false ? -1 : $comma, $brace === false ? -1 : $brace, $bracket === false ? -1 : $bracket);
            if ($cut >= 0) {
                $out = substr($out, 0, $cut + ($out[$cut] === ',' ? 0 : 1));
            }
        }
        $out = preg_replace('/,\s*$/', '', $out) ?? $out;

        while ($stack) {
            $closer = array_pop($stack);
            $out = rtrim($out);
            $out = preg_replace('/,\s*$/', '', $out) ?? $out;
            $out .= $closer;
        }
        return $out;
    }

    private function normalizarLista($valor): array
    {
        if (!is_array($valor)) return [];
        return array_values(array_filter($valor, function ($v) {
            if (is_array($v)) return true;
            return trim((string)$v) !== '';
        }));
    }

    private function normalizarLimitacoes($valor, array $fontesPadrao = [], string $periodoPadrao = '', string $amostraPadrao = ''): array
    {
        $l = is_array($valor) ? $valor : [];
        $fontes = $this->normalizarLista($l['fontes_usadas'] ?? []);
        if (!$fontes) $fontes = $fontesPadrao;

        return [
            'fontes_usadas' => $fontes,
            'periodo_analisado' => (string)($l['periodo_analisado'] ?? $periodoPadrao),
            'tamanho_amostra' => (string)($l['tamanho_amostra'] ?? $amostraPadrao),
            'dados_ausentes' => $this->normalizarLista($l['dados_ausentes'] ?? []),
            'aviso' => (string)($l['aviso'] ?? 'A IA nao substitui auditoria, parecer juridico ou investigacao oficial.'),
        ];
    }

    private function normalizarFontesOficiais($valor, array $padrao = []): array
    {
        $fontes = $this->normalizarLista($valor);
        if (!$fontes) $fontes = $padrao;
        return array_values(array_filter(array_map(function ($f) {
            if (is_string($f)) {
                return ['rotulo' => $f, 'url' => ''];
            }
            if (!is_array($f)) return null;
            return [
                'rotulo' => (string)($f['rotulo'] ?? $f['titulo'] ?? $f['fonte'] ?? 'Fonte oficial'),
                'url' => (string)($f['url'] ?? $f['link'] ?? ''),
            ];
        }, $fontes)));
    }

    private function normalizarAnaliseLei(array $a, array $contexto = []): array
    {
        $a['titulo'] = (string)($a['titulo'] ?? 'Análise de proposição');
        $a['tipo'] = (string)($a['tipo'] ?? 'Outro');
        $a['identificacao'] = (string)($a['identificacao'] ?? '');
        $a['resumo'] = (string)($a['resumo'] ?? '');
        $a['resumo_executivo'] = (string)($a['resumo_executivo'] ?? $a['resumo']);
        $a['objetivo_principal'] = (string)($a['objetivo_principal'] ?? '');
        $a['dados_objetivos'] = $this->normalizarLista($a['dados_objetivos'] ?? []);
        $a['leitura_ia'] = $this->normalizarLista($a['leitura_ia'] ?? []);
        $a['indicadores_comparativos'] = $this->normalizarLista($a['indicadores_comparativos'] ?? []);
        $a['impacto_trabalho'] = (string)($a['impacto_trabalho'] ?? '');
        $a['cobranca_efetividade'] = $this->normalizarCobrancaEfetividade($a['cobranca_efetividade'] ?? []);
        $a['areas_afetadas'] = array_values(array_filter(is_array($a['areas_afetadas'] ?? null) ? $a['areas_afetadas'] : []));
        $a['pontos_positivos'] = is_array($a['pontos_positivos'] ?? null) ? $a['pontos_positivos'] : [];
        $a['pontos_negativos'] = is_array($a['pontos_negativos'] ?? null) ? $a['pontos_negativos'] : [];
        $a['riscos'] = is_array($a['riscos'] ?? null) ? $a['riscos'] : [];
        $a['alertas_especiais'] = is_array($a['alertas_especiais'] ?? null) ? $a['alertas_especiais'] : [];
        $a['alertas_investigativos'] = $this->normalizarLista($a['alertas_investigativos'] ?? []);
        $a['termos_pesquisa'] = is_array($a['termos_pesquisa'] ?? null) ? $a['termos_pesquisa'] : [];
        $a['interessados'] = is_array($a['interessados'] ?? null) ? $a['interessados'] : [];
        $a['interessados']['beneficiados'] = is_array($a['interessados']['beneficiados'] ?? null) ? $a['interessados']['beneficiados'] : [];
        $a['interessados']['prejudicados'] = is_array($a['interessados']['prejudicados'] ?? null) ? $a['interessados']['prejudicados'] : [];
        $a['impactos_por_grupo'] = is_array($a['impactos_por_grupo'] ?? null) ? $a['impactos_por_grupo'] : [];
        $a['nota_transparencia'] = max(0, min(10, (float)($a['nota_transparencia'] ?? 0)));
        $a['parecer_geral'] = (string)($a['parecer_geral'] ?? '');
        $fontePadrao = [];
        if (!empty($contexto['url'])) {
            $fontePadrao[] = [
                'rotulo' => (string)($contexto['fonte'] ?? $contexto['titulo'] ?? 'Fonte original'),
                'url' => (string)$contexto['url'],
            ];
        }
        $a['fontes_oficiais'] = $this->normalizarFontesOficiais($a['fontes_oficiais'] ?? [], $fontePadrao);
        $a['confianca_blocos'] = $this->normalizarLista($a['confianca_blocos'] ?? []);
        $a['limitacoes'] = $this->normalizarLimitacoes(
            $a['limitacoes'] ?? [],
            $fontePadrao ? array_column($fontePadrao, 'rotulo') : ['Texto informado pelo usuario ou fonte carregada'],
            (string)($contexto['ano'] ?? ''),
            strlen((string)($contexto['texto_base'] ?? '')) ? strlen((string)$contexto['texto_base']) . ' caracteres' : ''
        );
        return $a;
    }

    private function normalizarCobrancaEfetividade($valor): array
    {
        $c = is_array($valor) ? $valor : [];
        $responsaveis = is_array($c['responsaveis'] ?? null) ? $c['responsaveis'] : [];

        return [
            'esfera_principal' => (string)($c['esfera_principal'] ?? 'indeterminada'),
            'resumo' => (string)($c['resumo'] ?? ''),
            'responsaveis' => array_values(array_filter(array_map(function ($r) {
                if (!is_array($r)) return null;
                return [
                    'esfera' => (string)($r['esfera'] ?? 'indeterminada'),
                    'quem' => (string)($r['quem'] ?? ''),
                    'papel' => (string)($r['papel'] ?? ''),
                    'por_que' => (string)($r['por_que'] ?? ''),
                    'como_cobrar' => (string)($r['como_cobrar'] ?? ''),
                ];
            }, $responsaveis))),
            'criterios_de_efetividade' => $this->normalizarLista($c['criterios_de_efetividade'] ?? []),
            'prazo_ou_marco' => (string)($c['prazo_ou_marco'] ?? ''),
            'limites' => (string)($c['limites'] ?? ''),
        ];
    }

    private function normalizarAnaliseParlamentar(array $a, array $perfil = []): array
    {
        $a['nome'] = (string)($a['nome'] ?? $perfil['nome'] ?? 'Parlamentar');
        $a['cargo'] = (string)($a['cargo'] ?? $perfil['cargo'] ?? '');
        $a['partido_uf'] = (string)($a['partido_uf'] ?? '');
        $a['resumo'] = (string)($a['resumo'] ?? '');
        $a['resumo_executivo'] = (string)($a['resumo_executivo'] ?? $a['resumo']);
        $a['dados_objetivos'] = $this->normalizarLista($a['dados_objetivos'] ?? []);
        $a['leitura_ia'] = $this->normalizarLista($a['leitura_ia'] ?? []);
        $a['indicadores_comparativos'] = $this->normalizarLista($a['indicadores_comparativos'] ?? []);
        $a['atuacao_legislativa'] = (string)($a['atuacao_legislativa'] ?? '');
        $a['efetividade_legislativa'] = (string)($a['efetividade_legislativa'] ?? '');
        $a['perfil_gastos'] = (string)($a['perfil_gastos'] ?? '');
        $a['impacto_trabalho'] = (string)($a['impacto_trabalho'] ?? '');
        $a['pontos_positivos'] = is_array($a['pontos_positivos'] ?? null) ? $a['pontos_positivos'] : [];
        $a['pontos_atencao'] = is_array($a['pontos_atencao'] ?? null) ? $a['pontos_atencao'] : [];
        $a['alertas_fornecedores'] = is_array($a['alertas_fornecedores'] ?? null) ? $a['alertas_fornecedores'] : [];
        foreach ($a['alertas_fornecedores'] as &$alerta) {
            if (is_array($alerta) && ($alerta['tipo'] ?? '') === 'repeticao_cnpj') {
                $alerta['tipo'] = 'fornecedor_recorrente';
            }
        }
        unset($alerta);
        $a['alertas_investigativos'] = $this->normalizarLista($a['alertas_investigativos'] ?? []);
        $a['riscos'] = is_array($a['riscos'] ?? null) ? $a['riscos'] : [];
        $a['temas_recorrentes'] = is_array($a['temas_recorrentes'] ?? null) ? $a['temas_recorrentes'] : [];
        $a['nota_transparencia'] = max(0, min(10, (float)($a['nota_transparencia'] ?? 0)));
        $a['parecer_geral'] = (string)($a['parecer_geral'] ?? '');

        $fontePadrao = [];
        foreach (($perfil['links_externos'] ?? []) as $link) {
            if (!is_array($link) || empty($link['url'])) continue;
            $fontePadrao[] = [
                'rotulo' => (string)($link['rotulo'] ?? $link['desc'] ?? 'Fonte oficial'),
                'url' => (string)$link['url'],
            ];
        }
        if (!$fontePadrao && !empty($perfil['fonte'])) {
            $fontePadrao[] = ['rotulo' => (string)$perfil['fonte'], 'url' => ''];
        }
        $a['fontes_oficiais'] = $this->normalizarFontesOficiais($a['fontes_oficiais'] ?? [], $fontePadrao);
        $a['confianca_blocos'] = $this->normalizarLista($a['confianca_blocos'] ?? []);
        $a['limitacoes'] = $this->normalizarLimitacoes(
            $a['limitacoes'] ?? [],
            $fontePadrao ? array_column($fontePadrao, 'rotulo') : ['Camara dos Deputados'],
            (string)($perfil['despesas']['ano'] ?? ''),
            !empty($perfil['despesas']['qtd']) ? $perfil['despesas']['qtd'] . ' lancamentos de despesa lidos' : ''
        );
        return $a;
    }
}
