<?php
/**
 * Busca dados públicos de parlamentares na API de Dados Abertos da Câmara.
 * Cobre DEPUTADOS FEDERAIS (a Câmara não publica vereadores/deputados estaduais).
 * Reúne perfil, gastos da cota parlamentar e proposições de autoria.
 */

namespace App;

class ParlamentarClient
{
    private const BASE = 'https://dadosabertos.camara.leg.br/api/v2';
    private const DESPESAS_ITENS_POR_PAGINA = 100;
    private const DESPESAS_MAX_PAGINAS_PERFIL = 25;

    /** @param ?PortalTransparenciaClient $portal opcional, para emendas parlamentares */
    public function __construct(private ?PortalTransparenciaClient $portal = null) {}

    /** Busca deputados por nome e/ou partido. Retorna lista resumida para escolha. */
    public function buscarPorNome(string $nome, string $partido = ''): array
    {
        $params = [
            'ordem'      => 'ASC',
            'ordenarPor' => 'nome',
            'itens'      => 60,
        ];
        if ($nome !== '') {
            $params['nome'] = $nome;
        }
        if ($partido !== '') {
            $params['siglaPartido'] = $partido;
        }

        $url = self::BASE . '/deputados?' . http_build_query($params);
        $dados = $this->get($url)['dados'] ?? [];

        return array_map(fn($d) => [
            'id'      => $d['id'],
            'nome'    => $d['nome'] ?? '',
            'partido' => $d['siglaPartido'] ?? '',
            'uf'      => $d['siglaUf'] ?? '',
            'foto'    => $d['urlFoto'] ?? '',
            'email'   => $d['email'] ?? '',
        ], $dados);
    }

    /** Lista siglas partidárias atualmente presentes na Câmara. */
    public function partidos(): array
    {
        $siglas = [];
        foreach ($this->deputadosAtuais() as $dep) {
            $sigla = trim((string)($dep['siglaPartido'] ?? ''));
            if ($sigla !== '') {
                $siglas[$sigla] = true;
            }
        }
        $siglas = array_keys($siglas);
        sort($siglas, SORT_NATURAL | SORT_FLAG_CASE);
        return $siglas;
    }

    /** Monta o perfil completo de um deputado para análise. */
    public function perfil(int $id): array
    {
        $det = $this->get(self::BASE . '/deputados/' . $id)['dados'] ?? [];
        if (!$det) {
            throw new \RuntimeException('Parlamentar não encontrado.');
        }
        $status = $det['ultimoStatus'] ?? [];

        $ano      = (int)date('Y');
        $despesas = $this->resumirDespesas($id, $ano);
        if ($despesas['total'] == 0) {            // início de ano: tenta o anterior
            $ano--;
            $despesas = $this->resumirDespesas($id, $ano);
        }

        $proposicoes = $this->proposicoes($id);
        $comissoesDetalhadas = $this->comissoesDetalhadas($id);

        return [
            'id'          => $id,
            'nome'        => $status['nome'] ?? ($det['nomeCivil'] ?? ''),
            'nome_civil'  => $det['nomeCivil'] ?? '',
            'partido'     => $status['siglaPartido'] ?? '',
            'uf'          => $status['siglaUf'] ?? '',
            'cargo'       => 'Deputado(a) Federal',
            'situacao'    => $status['situacao'] ?? '',
            'condicao'    => $status['condicaoEleitoral'] ?? '',
            'foto'        => $status['urlFoto'] ?? '',
            'email'       => $status['gabinete']['email'] ?? ($status['email'] ?? ''),
            'escolaridade'=> $det['escolaridade'] ?? '',
            'nascimento'  => $det['dataNascimento'] ?? '',
            'despesas'    => $despesas,
            'proposicoes' => $proposicoes,
            'prop_resumo' => $this->resumirProposicoes($proposicoes),
            'frentes'     => $this->frentes($id),
            'comissoes'   => $this->nomesComissoes($comissoesDetalhadas),
            'comissoes_detalhadas' => $comissoesDetalhadas,
            'votos_recentes' => $this->votosRecentes($id, $proposicoes),
            'emendas'     => $this->emendas($status['nome'] ?? ($det['nomeCivil'] ?? '')),
            'links_externos' => $this->linksExternos($status['nome'] ?? '', $status['siglaUf'] ?? ''),
            'fonte'       => 'Câmara dos Deputados (Dados Abertos)',
        ];
    }

    /**
     * Procura um fornecedor nas despesas da cota parlamentar de deputados federais.
     * A busca e feita no backend com dados estruturados; nao usa IA nem tokens.
     */
    public function buscarFornecedor(string $termo, int $ano = 0, int $limiteDeputados = 120, int $paginasPorDeputado = 1, int $deputadoPrioritario = 0): array
    {
        $termo = trim($termo);
        $docTermo = preg_replace('/\D+/', '', $termo) ?: '';
        $nomeTermo = $this->normalizarTexto($termo);
        $empresaConsultada = strlen($docTermo) === 14 ? $this->detalharCnpj($docTermo) : null;
        if (mb_strlen($nomeTermo) < 2 && mb_strlen($docTermo) < 4) {
            throw new \InvalidArgumentException('Digite ao menos 2 letras do fornecedor ou 4 dígitos do CNPJ/CPF.');
        }

        $ano = $ano > 0 ? $ano : (int)date('Y');
        $limiteDeputados = max(30, min(520, $limiteDeputados));
        $paginasPorDeputado = max(1, min(3, $paginasPorDeputado));

        $todosDeputados = $this->deputadosAtuais();
        $deputados = array_slice($todosDeputados, 0, $limiteDeputados);
        if ($deputadoPrioritario > 0) {
            foreach ($todosDeputados as $dep) {
                if ((int)($dep['id'] ?? 0) === $deputadoPrioritario) {
                    $prioritario = $this->buscarFornecedorEmDeputados(
                        $termo,
                        $ano,
                        [$dep],
                        max($paginasPorDeputado, 5),
                        $docTermo,
                        $nomeTermo,
                        $empresaConsultada
                    );
                    if (($prioritario['parlamentares_count'] ?? 0) > 0) {
                        $prioritario['escopo']['modo'] = 'deputado_prioritario';
                        return $prioritario;
                    }
                    array_unshift($deputados, $dep);
                    break;
                }
            }
            $vistos = [];
            $deputados = array_values(array_filter($deputados, function ($dep) use (&$vistos) {
                $id = (int)($dep['id'] ?? 0);
                if ($id <= 0 || isset($vistos[$id])) return false;
                $vistos[$id] = true;
                return true;
            }));
        }
        return $this->buscarFornecedorEmDeputados($termo, $ano, $deputados, $paginasPorDeputado, $docTermo, $nomeTermo, $empresaConsultada);
    }

    private function buscarFornecedorEmDeputados(
        string $termo,
        int $ano,
        array $deputados,
        int $paginasPorDeputado,
        string $docTermo,
        string $nomeTermo,
        ?array $empresaConsultada
    ): array {
        $urls = [];
        $mapa = [];
        foreach ($deputados as $dep) {
            $id = (int)($dep['id'] ?? 0);
            if ($id <= 0) continue;
            for ($pagina = 1; $pagina <= $paginasPorDeputado; $pagina++) {
                $key = $id . ':' . $pagina;
                $urls[$key] = self::BASE . "/deputados/{$id}/despesas?" . http_build_query([
                    'ano'        => $ano,
                    'itens'      => 100,
                    'pagina'     => $pagina,
                    'ordem'      => 'DESC',
                    'ordenarPor' => 'dataDocumento',
                ]);
                $mapa[$key] = $dep;
            }
        }

        $respostas = $this->getMany($urls, 10);
        $falhasConsulta = max(0, count($urls) - count($respostas));
        $porDeputado = [];
        $porFornecedor = [];
        $lancamentosLidos = 0;

        foreach ($respostas as $key => $resp) {
            $dep = $mapa[$key] ?? null;
            if (!$dep) continue;
            $itens = $resp['dados'] ?? [];
            $lancamentosLidos += count($itens);
            foreach ($itens as $d) {
                $fornecedor = trim((string)($d['nomeFornecedor'] ?? 'Fornecedor nao informado'));
                $docFornecedor = preg_replace('/\D+/', '', (string)($d['cnpjCpfFornecedor'] ?? '')) ?: '';
                $nomeFornecedor = $this->normalizarTexto($fornecedor);
                $bateNome = $nomeTermo !== '' && (
                    str_contains($nomeFornecedor, $nomeTermo)
                    || $this->textoContemTokens($nomeFornecedor, $nomeTermo)
                );
                $bateDoc = $docTermo !== '' && str_contains($docFornecedor, $docTermo);
                if (!$bateNome && !$bateDoc) continue;

                $id = (int)$dep['id'];
                $valor = $this->valorDespesa($d);
                $tipo = (string)($d['tipoDespesa'] ?? 'Outros');
                $porDeputado[$id] ??= [
                    'id' => $id,
                    'nome' => $dep['nome'] ?? '',
                    'partido' => $dep['siglaPartido'] ?? '',
                    'uf' => $dep['siglaUf'] ?? '',
                    'foto' => $dep['urlFoto'] ?? '',
                    'total' => 0.0,
                    'qtd' => 0,
                    'fornecedores' => [],
                    'documentos' => [],
                    'tipos' => [],
                    'maiores_lancamentos' => [],
                ];
                $porDeputado[$id]['total'] += $valor;
                $porDeputado[$id]['qtd']++;
                $porDeputado[$id]['fornecedores'][$fornecedor] = true;
                if ($docFornecedor !== '') $porDeputado[$id]['documentos']['d' . $docFornecedor] = $docFornecedor;
                $porDeputado[$id]['tipos'][$tipo] = ($porDeputado[$id]['tipos'][$tipo] ?? 0) + $valor;
                $porDeputado[$id]['maiores_lancamentos'][] = [
                    'data' => $d['dataDocumento'] ?? '',
                    'ano_consulta' => $ano,
                    'tipo' => $tipo,
                    'fornecedor' => $fornecedor,
                    'documento_fornecedor' => $docFornecedor,
                    'valor' => $valor,
                    'documento' => $d['numDocumento'] ?? '',
                ];

                $chaveFornecedor = $docFornecedor !== '' ? $docFornecedor : $this->normalizarTexto($fornecedor);
                $porFornecedor[$chaveFornecedor] ??= [
                    'fornecedor' => $fornecedor,
                    'documento' => $docFornecedor,
                    'total' => 0.0,
                    'qtd' => 0,
                    'parlamentares' => [],
                ];
                $porFornecedor[$chaveFornecedor]['total'] += $valor;
                $porFornecedor[$chaveFornecedor]['qtd']++;
                $porFornecedor[$chaveFornecedor]['parlamentares'][$id] = $dep['nome'] ?? '';
            }
        }

        $resultados = array_values(array_map(function ($r) {
            arsort($r['tipos']);
            usort($r['maiores_lancamentos'], fn($a, $b) => $b['valor'] <=> $a['valor']);
            $r['total'] = round($r['total'], 2);
            $r['fornecedores'] = array_values(array_keys($r['fornecedores']));
            $r['documentos'] = array_values($r['documentos']);
            $r['tipos'] = array_slice($r['tipos'], 0, 5, true);
            $r['maiores_lancamentos'] = array_slice($r['maiores_lancamentos'], 0, 5);
            return $r;
        }, $porDeputado));
        usort($resultados, fn($a, $b) => $b['total'] <=> $a['total']);

        $fornecedores = array_values(array_map(function ($f) {
            $f['total'] = round($f['total'], 2);
            $f['parlamentares'] = array_values(array_unique(array_filter($f['parlamentares'])));
            if (strlen((string)($f['documento'] ?? '')) === 14) {
                $detalhe = $this->detalharCnpj((string)$f['documento']);
                if ($detalhe) {
                    $f['detalhe'] = $detalhe;
                }
            }
            return $f;
        }, array_slice($porFornecedor, 0, 10, true)));
        usort($fornecedores, fn($a, $b) => $b['total'] <=> $a['total']);
        if ($empresaConsultada === null && !empty($fornecedores[0]['detalhe'])) {
            $empresaConsultada = $fornecedores[0]['detalhe'];
        }

        return [
            'termo' => $termo,
            'ano' => $ano,
            'escopo' => [
                'deputados_verificados' => count($deputados),
                'paginas_por_deputado' => $paginasPorDeputado,
                'lancamentos_lidos' => $lancamentosLidos,
                'consultas_falhas' => $falhasConsulta,
            ],
            'total' => round(array_sum(array_column($resultados, 'total')), 2),
            'qtd' => array_sum(array_column($resultados, 'qtd')),
            'parlamentares_count' => count($resultados),
            'fornecedores_count' => count($fornecedores),
            'fornecedores' => array_slice($fornecedores, 0, 10),
            'resultados' => array_slice($resultados, 0, 60),
            'empresa_consultada' => $empresaConsultada,
        ];
    }

    private function detalharCnpj(string $cnpj): ?array
    {
        $cnpj = preg_replace('/\D+/', '', $cnpj) ?: '';
        if (strlen($cnpj) !== 14) {
            return null;
        }

        $dir = \storagePath('empresas');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $cache = $dir . '/' . $cnpj . '.json';
        if (is_file($cache) && filemtime($cache) > time() - 604800) {
            $data = json_decode((string)file_get_contents($cache), true);
            return is_array($data) ? $data : null;
        }

        try {
            $ch = curl_init('https://brasilapi.com.br/api/cnpj/v1/' . $cnpj);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 12,
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code >= 400) {
                return null;
            }
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return null;
            }
            $out = [
                'razao_social' => (string)($json['razao_social'] ?? ''),
                'nome_fantasia' => (string)($json['nome_fantasia'] ?? ''),
                'situacao' => (string)($json['descricao_situacao_cadastral'] ?? ''),
                'atividade' => (string)($json['cnae_fiscal_descricao'] ?? ''),
                'municipio_uf' => trim((string)($json['municipio'] ?? '') . '/' . (string)($json['uf'] ?? ''), '/'),
                'inicio_atividade' => (string)($json['data_inicio_atividade'] ?? ''),
                'fonte' => 'BrasilAPI CNPJ',
            ];
            file_put_contents($cache, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            return $out;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Emendas parlamentares (Portal da Transparência), se houver token. */
    private function emendas(string $nome): array
    {
        if (!$this->portal || !$this->portal->temToken() || $nome === '') {
            return ['disponivel' => false];
        }
        try {
            return $this->portal->emendas($nome);
        } catch (\Throwable $e) {
            return ['disponivel' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Lista deputados em exercício para varreduras controladas. */
    private function deputadosAtuais(): array
    {
        $cache = \storagePath('camara/deputados-atuais.json');
        if (is_file($cache) && filemtime($cache) > time() - 21600) {
            $cached = json_decode((string)file_get_contents($cache), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $todos = [];
        for ($pagina = 1; $pagina <= 6; $pagina++) {
            $dados = $this->get(self::BASE . '/deputados?' . http_build_query([
                'itens' => 100,
                'pagina' => $pagina,
                'ordem' => 'ASC',
                'ordenarPor' => 'nome',
            ]))['dados'] ?? [];
            if (!$dados) break;
            foreach ($dados as $d) {
                $todos[] = $d;
            }
            if (count($dados) < 100) break;
        }
        $dir = dirname($cache);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($cache, json_encode($todos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $todos;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($ascii !== false) $texto = $ascii;
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?: '';
        return trim(preg_replace('/\s+/', ' ', $texto) ?: '');
    }

    private function textoContemTokens(string $textoNormalizado, string $buscaNormalizada): bool
    {
        $tokens = array_values(array_filter(
            explode(' ', $buscaNormalizada),
            fn($t) => mb_strlen($t, 'UTF-8') >= 3 && !in_array($t, ['ltda', 'eireli', 'me', 'sa'], true)
        ));
        if (!$tokens) return false;
        foreach ($tokens as $token) {
            if (!str_contains($textoNormalizado, $token)) return false;
        }
        return true;
    }

    /** Links de fontes externas verificadas (TSE, etc.) — sem dados inventados. */
    private function linksExternos(string $nome, string $uf): array
    {
        $links = [];
        if ($nome !== '') {
            // Portal oficial do TSE: usuário pesquisa o nome (deep-link por eleição é instável)
            $links[] = [
                'rotulo' => 'Patrimônio e contas (TSE)',
                'url'    => 'https://divulgacandcontas.tse.jus.br/divulga/#/home',
                'desc'   => 'Bens declarados e prestação de contas de campanha — busque por "' . $nome . '" no portal oficial do TSE.',
            ];
            $links[] = [
                'rotulo' => 'Discursos e notas taquigráficas',
                'url'    => 'https://www2.camara.leg.br/atividade-legislativa/discursos-e-notas-taquigraficas',
                'desc'   => 'Pesquisa oficial de discursos, sessões e reuniões — use o filtro de orador para "' . $nome . '".',
            ];
        }
        return $links;
    }

    /** Classifica a situação de tramitação em aprovada / arquivada / tramitando. */
    private function classificar(string $situacao): string
    {
        $s = mb_strtoupper($situacao, 'UTF-8');
        foreach (['TRANSFORMAD', 'PROMULGAD', 'SANCIONAD', 'APROVAD', 'NORMA JUR'] as $t) {
            if (str_contains($s, $t)) return 'aprovada';
        }
        foreach (['ARQUIVAD', 'REJEITAD', 'VETAD', 'DEVOLVID', 'RETIRAD', 'PREJUDICAD'] as $t) {
            if (str_contains($s, $t)) return 'arquivada';
        }
        return 'tramitando';
    }

    /** Conta proposições por status. */
    private function resumirProposicoes(array $props): array
    {
        $r = ['total' => count($props), 'aprovada' => 0, 'arquivada' => 0, 'tramitando' => 0];
        foreach ($props as $p) {
            $r[$p['status']] = ($r[$p['status']] ?? 0) + 1;
        }
        return $r;
    }

    /** Frentes parlamentares (temas de interesse). */
    private function frentes(int $id): array
    {
        try {
            $dados = $this->get(self::BASE . "/deputados/{$id}/frentes")['dados'] ?? [];
            $titulos = array_map(fn($f) => $f['titulo'] ?? '', $dados);
            return array_slice(array_values(array_filter(array_unique($titulos))), 0, 30);
        } catch (\Throwable) {
            return [];
        }
    }

    private function nomesComissoes(array $detalhadas): array
    {
        $nomes = [];
        foreach ($detalhadas as $o) {
            $sigla = $o['sigla'] ?? '';
            $papel = $o['titulo'] ?? '';
            $nomes[] = trim($sigla . ($papel ? " ({$papel})" : ''));
        }
        return array_values(array_unique(array_filter($nomes)));
    }

    /** Comissões/órgãos em que atua atualmente, com cargo e período. */
    private function comissoesDetalhadas(int $id): array
    {
        try {
            $hoje  = date('Y-m-d');
            $dados = $this->get(self::BASE . "/deputados/{$id}/orgaos?" . http_build_query([
                'dataInicio' => date('Y-m-d', strtotime('-1 year')),
                'itens'      => 100,
            ]))['dados'] ?? [];
            $out = [];
            foreach ($dados as $o) {
                // Mantém apenas órgãos em que o mandato ainda está ativo
                if (empty($o['dataFim']) || $o['dataFim'] >= $hoje) {
                    $out[] = [
                        'id' => (int)($o['idOrgao'] ?? 0),
                        'sigla' => (string)($o['siglaOrgao'] ?? ''),
                        'nome' => (string)($o['nomePublicacao'] ?? $o['nomeOrgao'] ?? ''),
                        'titulo' => (string)($o['titulo'] ?? ''),
                        'data_inicio' => substr((string)($o['dataInicio'] ?? ''), 0, 10),
                        'data_fim' => substr((string)($o['dataFim'] ?? ''), 0, 10),
                        'uri' => (string)($o['uriOrgao'] ?? ''),
                    ];
                }
            }
            return array_slice($out, 0, 20);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Soma as despesas da cota parlamentar por tipo, paginando o ano inteiro quando possível. */
    private function resumirDespesas(int $id, int $ano): array
    {
        $porTipo = [];
        $porFornecedor = [];
        $porFornecedorDetalhe = [];
        $porDocumento = [];
        $maiores = [];
        $datas = [];
        $total   = 0.0;
        $qtd     = 0;
        $paginasLidas = 0;
        $limiteAtingido = false;

        for ($pagina = 1; $pagina <= self::DESPESAS_MAX_PAGINAS_PERFIL; $pagina++) {
            $url = self::BASE . "/deputados/{$id}/despesas?" . http_build_query([
                'ano'        => $ano,
                'itens'      => self::DESPESAS_ITENS_POR_PAGINA,
                'pagina'     => $pagina,
                'ordem'      => 'DESC',
                'ordenarPor' => 'dataDocumento',
            ]);
            $itens = $this->get($url)['dados'] ?? [];
            if (!$itens) break;
            $paginasLidas++;
            foreach ($itens as $d) {
                $valor = $this->valorDespesa($d);
                $tipo  = $d['tipoDespesa'] ?? 'Outros';
                $fornecedor = $d['nomeFornecedor'] ?? ($d['cnpjCpfFornecedor'] ?? 'Fornecedor nao informado');
                $docFornecedor = preg_replace('/\D+/', '', (string)($d['cnpjCpfFornecedor'] ?? '')) ?: '';
                if (!empty($d['dataDocumento'])) {
                    $datas[] = substr((string)$d['dataDocumento'], 0, 10);
                }
                $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + $valor;
                $porFornecedor[$fornecedor] = ($porFornecedor[$fornecedor] ?? 0) + $valor;
                $porFornecedorDetalhe[$fornecedor] ??= [
                    'fornecedor' => $fornecedor,
                    'documento' => $docFornecedor,
                    'total' => 0.0,
                    'qtd' => 0,
                    'tipos' => [],
                ];
                $porFornecedorDetalhe[$fornecedor]['total'] += $valor;
                $porFornecedorDetalhe[$fornecedor]['qtd']++;
                $porFornecedorDetalhe[$fornecedor]['tipos'][$tipo] = ($porFornecedorDetalhe[$fornecedor]['tipos'][$tipo] ?? 0) + $valor;
                if ($docFornecedor !== '') {
                    $porDocumento[$docFornecedor] ??= ['documento' => $docFornecedor, 'total' => 0.0, 'qtd' => 0, 'fornecedores' => []];
                    $porDocumento[$docFornecedor]['total'] += $valor;
                    $porDocumento[$docFornecedor]['qtd']++;
                    $porDocumento[$docFornecedor]['fornecedores'][$fornecedor] = true;
                }
                $maiores[] = [
                    'data' => $d['dataDocumento'] ?? '',
                    'tipo' => $tipo,
                    'fornecedor' => $fornecedor,
                    'documento_fornecedor' => $docFornecedor,
                    'valor' => $valor,
                    'documento' => $d['numDocumento'] ?? '',
                ];
                $total += $valor;
                $qtd++;
            }
            if (count($itens) < self::DESPESAS_ITENS_POR_PAGINA) break;
            if ($pagina === self::DESPESAS_MAX_PAGINAS_PERFIL) {
                $limiteAtingido = true;
            }
        }
        arsort($porTipo);
        arsort($porFornecedor);
        uasort($porFornecedorDetalhe, fn($a, $b) => $b['total'] <=> $a['total']);
        uasort($porDocumento, fn($a, $b) => $b['total'] <=> $a['total']);
        usort($maiores, fn($a, $b) => $b['valor'] <=> $a['valor']);
        $fornecedoresDetalhados = array_values(array_map(function ($f) use ($total) {
            arsort($f['tipos']);
            $f['total'] = round($f['total'], 2);
            $f['percentual'] = $total > 0 ? round(($f['total'] / $total) * 100, 2) : 0;
            $f['tipos'] = array_slice($f['tipos'], 0, 5, true);
            return $f;
        }, array_slice($porFornecedorDetalhe, 0, 12, true)));
        $documentosDetalhados = array_values(array_map(function ($d) use ($total) {
            $d['total'] = round($d['total'], 2);
            $d['percentual'] = $total > 0 ? round(($d['total'] / $total) * 100, 2) : 0;
            $d['fornecedores'] = array_keys($d['fornecedores']);
            return $d;
        }, array_slice($porDocumento, 0, 12, true)));
        return [
            'ano' => $ano,
            'total' => round($total, 2),
            'qtd' => $qtd,
            'paginas_lidas' => $paginasLidas,
            'limite_paginas' => self::DESPESAS_MAX_PAGINAS_PERFIL,
            'completo' => !$limiteAtingido,
            'periodo_inicio' => $datas ? min($datas) : '',
            'periodo_fim' => $datas ? max($datas) : '',
            'criterio_valor' => 'valorLiquido',
            'por_tipo' => $porTipo,
            'por_fornecedor' => array_slice($porFornecedor, 0, 10, true),
            'fornecedores_detalhados' => $fornecedoresDetalhados,
            'documentos_detalhados' => $documentosDetalhados,
            'alertas_fornecedores' => $this->alertasFornecedores($fornecedoresDetalhados, $documentosDetalhados, round($total, 2), $qtd),
            'maiores_lancamentos' => array_slice($maiores, 0, 12),
        ];
    }

    /**
     * Alertas factuais sobre concentracao/repeticao de fornecedores.
     * Nao indicam irregularidade por si so; servem para orientar verificacao.
     */
    private function despesaOperacionalRecorrente(array $fornecedor): bool
    {
        $texto = mb_strtolower((string)($fornecedor['fornecedor'] ?? ''));
        foreach (array_keys($fornecedor['tipos'] ?? []) as $tipo) {
            $texto .= ' ' . mb_strtolower((string)$tipo);
        }

        $marcadores = [
            'passagem aerea',
            'passagem aérea',
            'passagens aereas',
            'passagens aéreas',
            'companhia aerea',
            'companhia aérea',
            'latam',
            'tam linhas',
            'gol linhas',
            'azul linhas',
            'aluguel',
            'locacao',
            'locação',
            'energia eletrica',
            'energia elétrica',
            'telefonia',
            'telecom',
            'internet',
            'servico postal',
            'correios',
            'combustivel',
            'combustível',
            'condominio',
            'condomínio',
            'manutencao de escritorio',
            'manutenção de escritório',
        ];
        foreach ($marcadores as $m) {
            if (str_contains($texto, $m)) return true;
        }
        return false;
    }

    private function alertasFornecedores(array $fornecedores, array $documentos, float $total, int $qtd): array
    {
        if ($total <= 0 || $qtd <= 0) return [];
        $alertas = [];
        foreach ($fornecedores as $f) {
            $perc = (float)($f['percentual'] ?? 0);
            $qtdFornecedor = (int)($f['qtd'] ?? 0);
            if ($perc >= 35 || ($perc >= 20 && $qtdFornecedor >= 3)) {
                $operacional = $this->despesaOperacionalRecorrente($f);
                $gravidade = $operacional
                    ? ($perc >= 50 ? 'media' : 'baixa')
                    : ($perc >= 50 ? 'alta' : 'media');
                $alertas[] = [
                    'tipo' => 'concentracao_fornecedor',
                    'gravidade' => $gravidade,
                    'titulo' => $operacional ? 'Fornecedor operacional recorrente' : 'Concentração de fornecedor',
                    'fornecedor' => $f['fornecedor'] ?? '',
                    'documento' => $f['documento'] ?? '',
                    'total' => $f['total'] ?? 0,
                    'percentual' => $perc,
                    'qtd' => $qtdFornecedor,
                    'descricao' => $operacional
                        ? 'Fornecedor aparece de forma recorrente em despesa operacional comum; é ponto de contexto, não indicação isolada de irregularidade.'
                        : 'Fornecedor concentra parcela relevante das despesas lidas da cota parlamentar.',
                    'evidencia' => number_format($perc, 2, ',', '.') . "% dos dados lidos; {$qtdFornecedor} lancamento(s).",
                    'confianca' => 'alta',
                    'como_verificar' => [
                        'Conferir notas fiscais e descrição dos serviços na Câmara',
                        'Comparar recorrência do fornecedor em outros períodos',
                        'Verificar CNPJ, quadro societário e atividade econômica',
                    ],
                ];
            }
        }
        foreach ($documentos as $d) {
            if ((int)($d['qtd'] ?? 0) >= 4 && (float)($d['percentual'] ?? 0) >= 15) {
                $percentual = (float)($d['percentual'] ?? 0);
                $qtdDoc = (int)($d['qtd'] ?? 0);
                $alertas[] = [
                    'tipo' => 'fornecedor_recorrente',
                    'gravidade' => $percentual >= 35 ? 'media' : 'baixa',
                    'titulo' => 'Fornecedor recorrente',
                    'fornecedor' => implode(', ', $d['fornecedores'] ?? []),
                    'documento' => $d['documento'] ?? '',
                    'total' => $d['total'] ?? 0,
                    'percentual' => $percentual,
                    'qtd' => $qtdDoc,
                    'descricao' => 'Mesmo documento de fornecedor aparece de forma recorrente nas despesas lidas; pode refletir despesa mensal ou operacional comum.',
                    'evidencia' => number_format($percentual, 2, ',', '.') . "% dos dados lidos; {$qtdDoc} lancamento(s).",
                    'confianca' => 'alta',
                    'como_verificar' => [
                        'Pesquisar o CNPJ na Receita Federal',
                        'Verificar se há nomes fantasia ou fornecedores relacionados',
                        'Conferir se os serviços prestados são compatíveis com a atividade declarada',
                    ],
                ];
            }
        }
        return array_slice($alertas, 0, 8);
    }

    /**
     * Proposições de autoria, já com a situação de tramitação.
     * Amostra as MAIS RECENTES (em tramitação) e as MAIS ANTIGAS (já concluídas:
     * aprovadas/arquivadas), para dar um retrato com desfechos reais.
     */
    private function proposicoes(int $id): array
    {
        // Só projetos propriamente ditos (exclui requerimentos, pareceres, emendas, indicações)
        $tiposProjeto = ['PL', 'PEC', 'PLP', 'PLV', 'PDL', 'PDC', 'MPV', 'PRC'];
        $base = self::BASE . '/proposicoes?idDeputadoAutor=' . $id . '&ordenarPor=id&itens=20&ordem=';
        $recentes = $this->get($base . 'DESC')['dados'] ?? [];
        $antigas  = $this->get($base . 'ASC')['dados'] ?? [];

        // Mescla sem duplicar e mantém só os tipos de projeto
        $dados = [];
        foreach (array_merge($recentes, $antigas) as $p) {
            if (in_array($p['siglaTipo'] ?? '', $tiposProjeto, true)) {
                $dados[$p['id']] = $p;
            }
        }
        $dados = array_slice($dados, 0, 26, true); // limita o nº de chamadas de detalhe

        // Cada proposição precisa do detalhe para conhecer a situação atual.
        // As chamadas são feitas em paralelo (curl_multi) — sequencial levava vários segundos.
        $urls = [];
        foreach ($dados as $p) {
            $urls[$p['id']] = self::BASE . '/proposicoes/' . $p['id'];
        }
        $detalhes = $this->getMany($urls);

        $out = [];
        foreach ($dados as $p) {
            $det = $detalhes[$p['id']] ?? [];
            $situacao = $det['dados']['statusProposicao']['descricaoSituacao'] ?? '';
            $propId = (int)($p['id'] ?? 0);
            $out[] = [
                'id'       => $propId,
                'tipo'     => $p['siglaTipo'] ?? '',
                'numero'   => $p['numero'] ?? '',
                'ano'      => $p['ano'] ?? '',
                'ementa'   => $p['ementa'] ?? '',
                'situacao' => $situacao,
                'status'   => $this->classificar($situacao),
                'uri'      => $p['uri'] ?? ($propId ? self::BASE . '/proposicoes/' . $propId : ''),
                'url'      => $propId ? 'https://www.camara.leg.br/propostas-legislativas/' . $propId : '',
            ];
        }
        return $out;
    }

    /**
     * Tenta recuperar votos nominais do parlamentar em votações ligadas às proposições exibidas.
     * A Câmara não oferece um endpoint direto /deputados/{id}/votacoes; por isso a busca é limitada.
     */
    private function votosRecentes(int $id, array $proposicoes): array
    {
        $props = array_slice(array_values(array_filter($proposicoes, fn($p) => (int)($p['id'] ?? 0) > 0)), 0, 5);
        if (!$props) {
            return [];
        }

        $urls = [];
        foreach ($props as $p) {
            $urls[(int)$p['id']] = self::BASE . '/proposicoes/' . (int)$p['id'] . '/votacoes';
        }
        $respostas = $this->getMany($urls, 4);
        $votacoes = [];
        foreach ($props as $p) {
            $propId = (int)$p['id'];
            foreach (($respostas[$propId]['dados'] ?? []) as $v) {
                if (empty($v['id'])) continue;
                $votacoes[] = [
                    'id' => (string)$v['id'],
                    'data' => substr((string)($v['dataHoraRegistro'] ?? $v['data'] ?? ''), 0, 10),
                    'orgao' => (string)($v['siglaOrgao'] ?? ''),
                    'descricao' => trim((string)($v['descricao'] ?? '')),
                    'aprovacao' => isset($v['aprovacao']) ? (int)$v['aprovacao'] : null,
                    'proposicao' => trim(($p['tipo'] ?? '') . ' ' . ($p['numero'] ?? '') . '/' . ($p['ano'] ?? '')),
                    'ementa' => (string)($p['ementa'] ?? ''),
                    'uri' => (string)($v['uri'] ?? ''),
                ];
            }
        }

        usort($votacoes, fn($a, $b) => strcmp((string)$b['data'], (string)$a['data']));
        $out = [];
        foreach (array_slice($votacoes, 0, 8) as $votacao) {
            $voto = $this->votoDeputadoNaVotacao($votacao['id'], $id);
            if (!$voto) continue;
            $out[] = $votacao + $voto;
            if (count($out) >= 6) break;
        }
        return $out;
    }

    private function votoDeputadoNaVotacao(string $votacaoId, int $deputadoId): ?array
    {
        $json = $this->getRapido(self::BASE . '/votacoes/' . rawurlencode($votacaoId) . '/votos', 6);
        foreach (($json['dados'] ?? []) as $v) {
            $dep = is_array($v['deputado_'] ?? null) ? $v['deputado_'] : (is_array($v['deputado'] ?? null) ? $v['deputado'] : []);
            $id = (int)($dep['id'] ?? $v['idDeputado'] ?? 0);
            if ($id !== $deputadoId) continue;
            return [
                'voto' => (string)($v['tipoVoto'] ?? $v['voto'] ?? ''),
                'partido' => (string)($dep['siglaPartido'] ?? $v['siglaPartido'] ?? ''),
                'uf' => (string)($dep['siglaUf'] ?? $v['siglaUf'] ?? ''),
            ];
        }
        return null;
    }

    /**
     * Busca várias URLs em paralelo, com uma repescagem das que falharem
     * (a API da Câmara pode recusar parte das chamadas concorrentes).
     * Retorna [chave => json decodificado]; falha persistente vira entrada ausente.
     */
    private function getMany(array $urls, int $concurrency = 8): array
    {
        $results = $this->getManyOnce($urls, $concurrency);
        $faltantes = array_diff_key($urls, $results);
        if ($faltantes) {
            $results += $this->getManyOnce($faltantes, 2);
        }
        return $results;
    }

    /** Uma passada de downloads paralelos. */
    private function getManyOnce(array $urls, int $concurrency): array
    {
        $results = [];
        $mh = curl_multi_init();
        $queue = $urls;
        $active = [];

        $addOne = function () use (&$queue, &$active, $mh): void {
            $key = array_key_first($queue);
            if ($key === null) return;
            $url = $queue[$key];
            unset($queue[$key]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_TIMEOUT        => 40,
            ]);
            curl_multi_add_handle($mh, $ch);
            $active[spl_object_id($ch)] = ['key' => $key, 'ch' => $ch];
        };

        for ($i = 0; $i < $concurrency && $queue; $i++) {
            $addOne();
        }

        do {
            curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $entry = $active[spl_object_id($ch)] ?? null;
                if ($entry) {
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $body = curl_multi_getcontent($ch);
                    if ($info['result'] === CURLE_OK && $code < 400 && $body !== null) {
                        $json = json_decode($body, true);
                        if (is_array($json)) {
                            $results[$entry['key']] = $json;
                        }
                    }
                    unset($active[spl_object_id($ch)]);
                }
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                if ($queue) {
                    $addOne();
                }
            }
        } while ($running || $active || $queue);

        curl_multi_close($mh);
        return $results;
    }

    private function getRapido(string $url, int $timeout = 6): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            return [];
        }
        return json_decode($body, true) ?: [];
    }

    /** Monta o texto de contexto enviado à IA. */
    public static function montarTexto(array $p): string
    {
        $l = [];
        $l[] = "PARLAMENTAR: {$p['nome']} ({$p['partido']}-{$p['uf']})";
        if ($p['nome_civil'] && $p['nome_civil'] !== $p['nome']) $l[] = "Nome civil: {$p['nome_civil']}";
        $l[] = "Cargo: {$p['cargo']} · Situação: {$p['situacao']} {$p['condicao']}";
        if ($p['escolaridade']) $l[] = "Escolaridade: {$p['escolaridade']}";

        $dp = $p['despesas'];
        $escopoCota = !empty($dp['completo'])
            ? "total liquido de {$dp['qtd']} lancamentos"
            : "amostra parcial de {$dp['qtd']} lancamentos";
        $periodoCota = (!empty($dp['periodo_inicio']) && !empty($dp['periodo_fim']))
            ? " entre {$dp['periodo_inicio']} e {$dp['periodo_fim']}"
            : '';
        $l[] = "";
        $l[] = "GASTOS DA COTA PARLAMENTAR em {$dp['ano']} ({$escopoCota}{$periodoCota}; criterio: valorLiquido da Camara): R$ " . number_format($dp['total'], 2, ',', '.');
        foreach ($dp['por_tipo'] as $tipo => $valor) {
            $l[] = "  - {$tipo}: R$ " . number_format($valor, 2, ',', '.');
        }
        if (!empty($dp['por_fornecedor'])) {
            $l[] = "PRINCIPAIS FORNECEDORES NAS DESPESAS LIDAS DA COTA:";
            foreach ($dp['por_fornecedor'] as $fornecedor => $valor) {
                $l[] = "  - {$fornecedor}: R$ " . number_format($valor, 2, ',', '.');
            }
        }
        if (!empty($dp['maiores_lancamentos'])) {
            $l[] = "MAIORES LANCAMENTOS INDIVIDUAIS DA COTA (despesas lidas):";
            foreach ($dp['maiores_lancamentos'] as $g) {
                $data = $g['data'] ? substr((string)$g['data'], 0, 10) : 'data nao informada';
                $l[] = "  - {$data} · {$g['tipo']} · {$g['fornecedor']}: R$ "
                    . number_format((float)$g['valor'], 2, ',', '.');
            }
        }

        $r = $p['prop_resumo'] ?? [];
        $l[] = "";
        $l[] = "PRODUÇÃO LEGISLATIVA (amostra de autoria): {$r['total']} proposições — "
             . "{$r['aprovada']} aprovada(s)/virou lei, {$r['arquivada']} arquivada(s)/rejeitada(s), {$r['tramitando']} em tramitação.";

        $grupos = [
            'aprovada'   => 'APROVADAS / VIRARAM LEI',
            'arquivada'  => 'ARQUIVADAS / REJEITADAS',
            'tramitando' => 'EM TRAMITAÇÃO',
        ];
        foreach ($grupos as $st => $rotulo) {
            $itens = array_filter($p['proposicoes'], fn($pr) => $pr['status'] === $st);
            if (!$itens) continue;
            $l[] = "";
            $l[] = "{$rotulo}:";
            foreach ($itens as $pr) {
                $sit = $pr['situacao'] ? " [{$pr['situacao']}]" : '';
                $l[] = "  - {$pr['tipo']} {$pr['numero']}/{$pr['ano']}{$sit}: {$pr['ementa']}";
            }
        }

        if (!empty($p['comissoes'])) {
            $l[] = "";
            $l[] = "COMISSÕES/ÓRGÃOS: " . implode(', ', $p['comissoes']);
        }
        if (!empty($p['comissoes_detalhadas'])) {
            $l[] = "DETALHAMENTO DE COMISSÕES/ÓRGÃOS:";
            foreach (array_slice($p['comissoes_detalhadas'], 0, 12) as $c) {
                $periodo = trim(($c['data_inicio'] ?? '') . ' a ' . (($c['data_fim'] ?? '') ?: 'atual'), ' a');
                $l[] = "  - {$c['sigla']} · {$c['titulo']} · {$c['nome']}" . ($periodo ? " · {$periodo}" : '');
            }
        }
        if (!empty($p['votos_recentes'])) {
            $l[] = "";
            $l[] = "VOTOS NOMINAIS RECUPERADOS EM PROPOSIÇÕES RELACIONADAS:";
            foreach (array_slice($p['votos_recentes'], 0, 6) as $v) {
                $resultado = $v['aprovacao'] === 1 ? 'votação aprovada' : ($v['aprovacao'] === 0 ? 'votação não aprovada' : 'resultado não informado');
                $l[] = "  - {$v['data']} · {$v['proposicao']} · voto {$v['voto']} · {$resultado}: " . mb_substr((string)$v['descricao'], 0, 180);
            }
        }
        if (!empty($p['frentes'])) {
            $l[] = "";
            $l[] = "FRENTES PARLAMENTARES (temas de atuação): " . implode('; ', array_slice($p['frentes'], 0, 20));
        }

        $em = $p['emendas'] ?? [];
        if (!empty($em['disponivel'])) {
            $l[] = "";
            $l[] = "EMENDAS PARLAMENTARES (Portal da Transparência): {$em['qtd']} emendas, "
                 . "R$ " . number_format($em['total_empenhado'], 2, ',', '.') . " empenhado, "
                 . "R$ " . number_format($em['total_pago'], 2, ',', '.') . " pago.";
            foreach (($em['amostra'] ?? []) as $a) {
                $l[] = "  - {$a['ano']} · " . ($a['funcao'] ?: 'área não informada') . ": R$ " . number_format($a['empenhado'], 2, ',', '.');
            }
        }
        if (!empty($em['disponivel'])) {
            if (!empty($em['por_funcao'])) {
                $l[] = "EMENDAS POR AREA/FUNCAO:";
                foreach ($em['por_funcao'] as $funcao => $valor) {
                    $pago = $em['por_funcao_pago'][$funcao] ?? 0;
                    $l[] = "  - {$funcao}: R$ " . number_format((float)$valor, 2, ',', '.')
                        . " empenhado; R$ " . number_format((float)$pago, 2, ',', '.') . " pago.";
                }
            }
            if (!empty($em['amostra'])) {
                $l[] = "DETALHES DAS EMENDAS NA AMOSTRA:";
                foreach ($em['amostra'] as $a) {
                    $detalhes = array_filter([
                        $a['funcao'] ?? '',
                        $a['programa'] ?? '',
                        $a['acao'] ?? '',
                        $a['localidade'] ?? '',
                        $a['favorecido'] ?? '',
                    ]);
                    $rotulo = $detalhes ? implode(' · ', $detalhes) : 'area nao informada';
                    $l[] = "  - {$a['ano']} · {$rotulo}: R$ "
                        . number_format((float)($a['empenhado'] ?? 0), 2, ',', '.') . " empenhado; R$ "
                        . number_format((float)($a['pago'] ?? 0), 2, ',', '.') . " pago.";
                }
            }
        }
        return implode("\n", $l);
    }

    /** Valor líquido reembolsado pela Câmara; fallback cobre respostas antigas/incompletas. */
    private function valorDespesa(array $d): float
    {
        if (array_key_exists('valorLiquido', $d)) {
            return $this->valorNumerico($d['valorLiquido']);
        }
        $documento = $this->valorNumerico($d['valorDocumento'] ?? 0);
        $glosa = $this->valorNumerico($d['valorGlosa'] ?? 0);
        return $documento - $glosa;
    }

    private function valorNumerico(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float)$v;
        }
        $s = trim((string)$v);
        if ($s === '') {
            return 0.0;
        }
        if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d+$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? (float)$s : 0.0;
    }

    private function get(string $url): array
    {
        $lastCode = 0;
        $lastError = '';

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 30,
            ]);
            $body = curl_exec($ch);
            $lastCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $lastError = curl_error($ch);
            curl_close($ch);

            if ($body !== false && $lastCode < 400) {
                return json_decode($body, true) ?: [];
            }

            if (!$this->shouldRetry($lastCode) || $attempt === 3) {
                break;
            }
            usleep($attempt * 350000);
        }

        $detail = $lastError !== '' ? " Detalhe: {$lastError}" : '';
        throw new \RuntimeException("Falha ao consultar a API da Câmara (HTTP {$lastCode}) após 3 tentativas. Tente novamente em alguns instantes.{$detail}");
    }

    private function shouldRetry(int $code): bool
    {
        return $code === 0 || $code === 408 || $code === 429 || ($code >= 500 && $code <= 599);
    }
}
