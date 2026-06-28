<?php
/**
 * Busca proposições na API de Dados Abertos da Câmara dos Deputados.
 * https://dadosabertos.camara.leg.br/api/v2
 * Retorna ementa, autor, status e link para o inteiro teor.
 */

namespace App;

class CamaraClient
{
    private const BASE = 'https://dadosabertos.camara.leg.br/api/v2';

    /**
     * Busca uma proposição por tipo/número/ano (ex: PL 1234 / 2023).
     * Retorna metadados + texto base (ementa) para alimentar a análise.
     */
    public function buscar(string $tipo, int $numero, int $ano): array
    {
        $tipo = strtoupper(trim($tipo));
        $url  = self::BASE . '/proposicoes?' . http_build_query([
            'siglaTipo' => $tipo,
            'numero'    => $numero,
            'ano'       => $ano,
            'ordem'     => 'DESC',
            'ordenarPor'=> 'id',
        ]);
        try {
            $lista = $this->get($url, 2)['dados'] ?? [];
        } catch (\Throwable $e) {
            try {
                $csvFallback = $this->buscarNoCsv($tipo, $numero, $ano);
                if ($csvFallback) {
                    return $csvFallback;
                }
            } catch (\Throwable) {
                // O CSV anual e o Senado sao fallbacks independentes.
            }
            $fallbackSenado = (new SenadoClient())->fallbackComoProposicao($tipo, $numero, $ano);
            if ($fallbackSenado) {
                return $fallbackSenado;
            }
            throw $e;
        }
        if (!$lista) {
            $fallbackSenado = (new SenadoClient())->fallbackComoProposicao($tipo, $numero, $ano);
            if ($fallbackSenado) {
                return $fallbackSenado;
            }
            throw new \RuntimeException("Nenhuma proposição encontrada para {$tipo} {$numero}/{$ano} na Câmara ou no Senado.");
        }

        $id      = $lista[0]['id'];
        try {
            $detalhe = $this->get(self::BASE . '/proposicoes/' . $id)['dados'] ?? [];
        } catch (\Throwable) {
            $detalhe = $lista[0];
        }

        try {
            $autores = $this->get(self::BASE . '/proposicoes/' . $id . '/autores')['dados'] ?? [];
        } catch (\Throwable) {
            $autores = [];
        }

        $autoresDetalhados = $this->detalharAutores($autores);
        $nomesAutores = array_map(fn($a) => $this->formatarAutor($a), $autoresDetalhados);
        $partidosAutores = array_values(array_unique(array_filter(array_map(
            fn($a) => $a['partido'] ?? '',
            $autoresDetalhados
        ))));

        $senado = (new SenadoClient())->buscar($tipo, $numero, $ano);

        $votacoesDiagnostico = $this->votacoesProposicaoComDiagnostico((int)$id);
        $votacoes = $votacoesDiagnostico['votacoes'];

        return [
            'id'            => $id,
            'tipo'          => $detalhe['siglaTipo'] ?? $tipo,
            'numero'        => $detalhe['numero'] ?? $numero,
            'ano'           => $detalhe['ano'] ?? $ano,
            'ementa'        => $detalhe['ementa'] ?? '',
            'ementa_detalhada' => $detalhe['ementaDetalhada'] ?? '',
            'keywords'      => $detalhe['keywords'] ?? '',
            'situacao'      => $detalhe['statusProposicao']['descricaoSituacao'] ?? '',
            'tramitacao'    => $detalhe['statusProposicao']['descricaoTramitacao'] ?? '',
            'autores'       => array_values(array_filter($nomesAutores)),
            'autores_detalhados' => $autoresDetalhados,
            'partidos_autores' => $partidosAutores,
            'inteiro_teor'  => $detalhe['urlInteiroTeor'] ?? '',
            'tramitacoes'   => $this->tramitacoes($id),
            'votacoes'      => $votacoes,
            'votacoes_meta' => $votacoesDiagnostico['meta'],
            'senado'        => $senado,
            'fonte'         => 'Câmara dos Deputados (Dados Abertos)',
        ];
    }

    /** Complementa autores parlamentares com partido/UF a partir da URI do deputado. */
    private function detalharAutores(array $autores): array
    {
        $out = [];
        foreach ($autores as $autor) {
            $nome = trim((string)($autor['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $item = [
                'nome' => $nome,
                'tipo' => (string)($autor['tipo'] ?? ''),
                'partido' => '',
                'uf' => '',
                'proponente' => !empty($autor['proponente']),
                'ordem' => (int)($autor['ordemAssinatura'] ?? 0),
            ];

            $uri = (string)($autor['uri'] ?? '');
            if (preg_match('~/deputados/(\d+)~', $uri, $m)) {
                try {
                    $dep = $this->get(self::BASE . '/deputados/' . (int)$m[1], 1)['dados'] ?? [];
                    $status = $dep['ultimoStatus'] ?? [];
                    $item['partido'] = (string)($status['siglaPartido'] ?? '');
                    $item['uf'] = (string)($status['siglaUf'] ?? '');
                } catch (\Throwable) {
                    // Mantém o autor mesmo se a consulta complementar falhar.
                }
            }
            $out[] = $item;
        }
        return $out;
    }

    private function formatarAutor(array $autor): string
    {
        $nome = (string)($autor['nome'] ?? '');
        $partido = (string)($autor['partido'] ?? '');
        $uf = (string)($autor['uf'] ?? '');
        if ($partido !== '' && $uf !== '') {
            return "{$nome} ({$partido}/{$uf})";
        }
        if ($partido !== '') {
            return "{$nome} ({$partido})";
        }
        return $nome;
    }

    /** Linha do tempo da tramitação (mais recentes primeiro). */
    public function tramitacoes(int $id): array
    {
        try {
            $dados = $this->get(self::BASE . "/proposicoes/{$id}/tramitacoes")['dados'] ?? [];
        } catch (\Throwable) {
            return [];
        }
        $out = array_map(fn($t) => [
            'data'      => substr($t['dataHora'] ?? '', 0, 10),
            'orgao'     => $t['siglaOrgao'] ?? '',
            'situacao'  => $t['descricaoSituacao'] ?? '',
            'despacho'  => trim($t['despacho'] ?? ''),
        ], $dados);
        // Mais recente primeiro e limita para não poluir
        $out = array_reverse($out);
        return array_slice($out, 0, 12);
    }

    /** Votações relacionadas à proposição. */
    public function votacoesProposicao(int $id, int $limite = 8, int $limiteEnriquecimento = 4): array
    {
        return $this->votacoesProposicaoComDiagnostico($id, $limite, $limiteEnriquecimento)['votacoes'];
    }

    /** Votações relacionadas + diagnóstico da consulta oficial. */
    private function votacoesProposicaoComDiagnostico(int $id, int $limite = 8, int $limiteEnriquecimento = 4): array
    {
        if ($id <= 0) {
            return [
                'votacoes' => [],
                'meta' => $this->metaVotacoesProposicao($id, 'id_invalido', 'ID da proposição inválido para consulta de votações.', $limite),
            ];
        }
        $url = self::BASE . "/proposicoes/{$id}/votacoes";
        try {
            $json = $this->get($url);
            $dados = is_array($json['dados'] ?? null) ? $json['dados'] : [];
        } catch (\Throwable $e) {
            return [
                'votacoes' => [],
                'meta' => $this->metaVotacoesProposicao(
                    $id,
                    'erro_api',
                    'Falha ao consultar votações relacionadas na API oficial da Câmara.',
                    $limite,
                    0,
                    0,
                    0,
                    $e->getMessage()
                ),
            ];
        }
        usort($dados, fn($a, $b) => strcmp((string)($b['dataHoraRegistro'] ?? $b['data'] ?? ''), (string)($a['dataHoraRegistro'] ?? $a['data'] ?? '')));

        $out = [];
        foreach (array_slice($dados, 0, $limite) as $idx => $v) {
            $resumo = $this->normalizarVotacaoResumo($v);
            $out[] = $idx < $limiteEnriquecimento ? $this->enriquecerVotacao($resumo) : $resumo;
        }
        $totalRetornado = count($dados);
        $status = $totalRetornado > 0 ? 'sucesso' : 'sem_votacoes';
        $mensagem = $totalRetornado > 0
            ? 'Votações relacionadas carregadas da API oficial da Câmara.'
            : 'Consulta realizada com sucesso; a API oficial da Câmara não retornou votações relacionadas para esta proposição.';

        return [
            'votacoes' => $out,
            'meta' => $this->metaVotacoesProposicao(
                $id,
                $status,
                $mensagem,
                $limite,
                $totalRetornado,
                count($out),
                min(count($out), $limiteEnriquecimento)
            ),
        ];
    }

    private function metaVotacoesProposicao(
        int $id,
        string $status,
        string $mensagem,
        int $limite,
        int $totalRetornado = 0,
        int $totalExibido = 0,
        int $totalEnriquecido = 0,
        ?string $erro = null
    ): array {
        $endpoint = $id > 0 ? "/proposicoes/{$id}/votacoes" : '/proposicoes/{id}/votacoes';
        $url = $id > 0 ? self::BASE . $endpoint : '';

        return [
            'status' => $status,
            'mensagem' => $mensagem,
            'erro' => $erro,
            'endpoint' => $endpoint,
            'url' => $url,
            'links_oficiais' => [
                'proposicao' => $id > 0 ? self::BASE . "/proposicoes/{$id}" : '',
                'votacoes' => $url,
            ],
            'total_retornado' => $totalRetornado,
            'total_exibido' => $totalExibido,
            'total_enriquecido' => $totalEnriquecido,
            'limite' => $limite,
            'fonte' => 'Câmara dos Deputados (Dados Abertos)',
        ];
    }

    private function normalizarVotacaoResumo(array $v): array
    {
        $id = (string)($v['id'] ?? '');
        return [
            'id' => $id,
            'data' => substr((string)($v['dataHoraRegistro'] ?? $v['data'] ?? ''), 0, 10),
            'orgao' => (string)($v['siglaOrgao'] ?? ''),
            'proposicao_objeto' => (string)($v['proposicaoObjeto'] ?? ''),
            'descricao' => trim((string)($v['descricao'] ?? '')),
            'aprovacao' => isset($v['aprovacao']) ? (int)$v['aprovacao'] : null,
            'uriEvento' => (string)($v['uriEvento'] ?? ''),
            'uriOrgao' => (string)($v['uriOrgao'] ?? ''),
            'uriProposicaoObjeto' => (string)($v['uriProposicaoObjeto'] ?? ''),
            'idOrgao' => isset($v['idOrgao']) ? (int)$v['idOrgao'] : null,
            'uri_evento' => (string)($v['uriEvento'] ?? ''),
            'uri_orgao' => (string)($v['uriOrgao'] ?? ''),
            'uri_proposicao_objeto' => (string)($v['uriProposicaoObjeto'] ?? ''),
            'id_orgao' => isset($v['idOrgao']) ? (int)$v['idOrgao'] : null,
            'uri' => (string)($v['uri'] ?? ($id !== '' ? self::BASE . '/votacoes/' . rawurlencode($id) : '')),
            'uri_votos' => $id !== '' ? self::BASE . '/votacoes/' . rawurlencode($id) . '/votos' : '',
        ];
    }

    private function enriquecerVotacao(array $votacao): array
    {
        $id = (string)($votacao['id'] ?? '');
        if ($id === '') {
            return $votacao;
        }

        try {
            $detalhe = $this->getRapido(self::BASE . '/votacoes/' . rawurlencode($id), 8)['dados'] ?? [];
            if (is_array($detalhe) && $detalhe) {
                $votacao['uriEvento'] = (string)($detalhe['uriEvento'] ?? ($votacao['uriEvento'] ?? ''));
                $votacao['uriOrgao'] = (string)($detalhe['uriOrgao'] ?? ($votacao['uriOrgao'] ?? ''));
                $votacao['uriProposicaoObjeto'] = (string)($detalhe['uriProposicaoObjeto'] ?? ($votacao['uriProposicaoObjeto'] ?? ''));
                $votacao['idOrgao'] = isset($detalhe['idOrgao']) ? (int)$detalhe['idOrgao'] : ($votacao['idOrgao'] ?? null);
                $votacao['uri_evento'] = (string)($detalhe['uriEvento'] ?? ($votacao['uri_evento'] ?? ''));
                $votacao['uri_orgao'] = (string)($detalhe['uriOrgao'] ?? ($votacao['uri_orgao'] ?? ''));
                $votacao['uri_proposicao_objeto'] = (string)($detalhe['uriProposicaoObjeto'] ?? ($votacao['uri_proposicao_objeto'] ?? ''));
                $votacao['id_orgao'] = isset($detalhe['idOrgao']) ? (int)$detalhe['idOrgao'] : ($votacao['id_orgao'] ?? null);
                $votacao['efeitos'] = $this->normalizarEfeitosVotacao($detalhe['efeitosRegistrados'] ?? []);
                $votacao['proposicoes_afetadas'] = $this->normalizarProposicoesAfetadas($detalhe['proposicoesAfetadas'] ?? []);
                $votacao['ultima_apresentacao'] = [
                    'data' => substr((string)($detalhe['ultimaApresentacaoProposicao']['dataHoraRegistro'] ?? ''), 0, 10),
                    'descricao' => trim((string)($detalhe['ultimaApresentacaoProposicao']['descricao'] ?? '')),
                    'uri' => (string)($detalhe['ultimaApresentacaoProposicao']['uriProposicaoCitada'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            $votacao['detalhes_erro'] = $e->getMessage();
        }

        try {
            $votos = $this->getRapido(self::BASE . '/votacoes/' . rawurlencode($id) . '/votos', 10)['dados'] ?? [];
            $votacao['votos'] = $this->resumirVotosNominais(is_array($votos) ? $votos : []);
        } catch (\Throwable $e) {
            $votacao['votos_erro'] = $e->getMessage();
            $votacao['votos'] = [
                'total' => 0,
                'por_tipo' => [],
                'por_partido' => [],
                'por_uf' => [],
                'amostra' => [],
            ];
        }

        return $votacao;
    }

    private function normalizarEfeitosVotacao(mixed $efeitos): array
    {
        if (!is_array($efeitos)) {
            return [];
        }
        return array_values(array_filter(array_map(fn($e) => [
            'proposicao' => (string)($e['tituloProposicao'] ?? ''),
            'resultado' => trim((string)($e['descResultado'] ?? '')),
            'data' => substr((string)($e['dataHoraResultado'] ?? ''), 0, 10),
            'uri' => (string)($e['uriProposicao'] ?? ''),
        ], $efeitos), fn($e) => $e['resultado'] !== '' || $e['proposicao'] !== ''));
    }

    private function normalizarProposicoesAfetadas(mixed $proposicoes): array
    {
        if (!is_array($proposicoes)) {
            return [];
        }
        return array_values(array_filter(array_map(fn($p) => [
            'id' => (int)($p['id'] ?? 0),
            'titulo' => trim((string)($p['siglaTipo'] ?? '') . ' ' . (string)($p['numero'] ?? '') . '/' . (string)($p['ano'] ?? '')),
            'ementa' => trim((string)($p['ementa'] ?? '')),
            'uri' => (string)($p['uri'] ?? ''),
        ], $proposicoes), fn($p) => $p['titulo'] !== '/' || $p['ementa'] !== ''));
    }

    private function resumirVotosNominais(array $votos): array
    {
        $porTipo = [];
        $porPartido = [];
        $porUf = [];
        $amostra = [];

        foreach ($votos as $voto) {
            if (!is_array($voto)) {
                continue;
            }
            $dep = is_array($voto['deputado_'] ?? null) ? $voto['deputado_'] : [];
            $tipo = trim((string)($voto['tipoVoto'] ?? '')) ?: 'Não informado';
            $partido = trim((string)($dep['siglaPartido'] ?? '')) ?: 'S.PART.';
            $uf = trim((string)($dep['siglaUf'] ?? '')) ?: 'UF não informada';

            $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + 1;
            $porPartido[$partido] = ($porPartido[$partido] ?? 0) + 1;
            $porUf[$uf] = ($porUf[$uf] ?? 0) + 1;

            if (count($amostra) < 40) {
                $amostra[] = [
                    'nome' => (string)($dep['nome'] ?? ''),
                    'partido' => $partido,
                    'uf' => $uf,
                    'voto' => $tipo,
                    'data' => substr((string)($voto['dataRegistroVoto'] ?? ''), 0, 19),
                    'uri' => (string)($dep['uri'] ?? ''),
                ];
            }
        }

        return [
            'total' => count($votos),
            'por_tipo' => $this->ordenarContagem($porTipo, 'tipo'),
            'por_partido' => array_slice($this->ordenarContagem($porPartido, 'sigla'), 0, 16),
            'por_uf' => array_slice($this->ordenarContagem($porUf, 'uf'), 0, 27),
            'amostra' => $amostra,
        ];
    }

    private function ordenarContagem(array $dados, string $campo): array
    {
        arsort($dados);
        $out = [];
        foreach ($dados as $chave => $total) {
            $out[] = [$campo => (string)$chave, 'total' => (int)$total];
        }
        return $out;
    }

    /** Monta o texto base a partir dos metadados, para enviar à IA. */
    public static function montarTexto(array $dados): string
    {
        $partes = [];
        if ($dados['ementa'])           $partes[] = "EMENTA: " . $dados['ementa'];
        if ($dados['ementa_detalhada']) $partes[] = "EMENTA DETALHADA: " . $dados['ementa_detalhada'];
        if ($dados['keywords'])         $partes[] = "PALAVRAS-CHAVE: " . $dados['keywords'];
        if ($dados['situacao'])         $partes[] = "SITUAÇÃO: " . $dados['situacao'];
        if ($dados['autores'])          $partes[] = "AUTOR(ES): " . implode(', ', $dados['autores']);
        if (!empty($dados['partidos_autores'])) $partes[] = "PARTIDO(S) DO(S) AUTOR(ES): " . implode(', ', $dados['partidos_autores']);
        if (!empty($dados['tramitacoes'])) {
            $partes[] = "TRAMITAÇÃO RECENTE:";
            foreach (array_slice($dados['tramitacoes'], 0, 6) as $t) {
                $partes[] = "  - {$t['data']} ({$t['orgao']}) {$t['situacao']}: " . mb_substr($t['despacho'], 0, 160);
            }
        }
        if (!empty($dados['votacoes'])) {
            $partes[] = "VOTAÇÕES RELACIONADAS:";
            foreach (array_slice($dados['votacoes'], 0, 6) as $v) {
                $aprovacao = $v['aprovacao'] ?? null;
                $resultado = $aprovacao === 1 ? 'votação aprovada' : ($aprovacao === 0 ? 'votação não aprovada' : 'resultado da votação não informado');
                $partes[] = "  - {$v['data']} ({$v['orgao']}) {$resultado}: " . mb_substr((string)$v['descricao'], 0, 220);
                if (!empty($v['proposicao_objeto'])) {
                    $partes[] = "    Proposição objeto: " . mb_substr((string)$v['proposicao_objeto'], 0, 160);
                }
                if (!empty($v['votos']['total'])) {
                    $contagens = array_map(
                        fn($c) => ($c['tipo'] ?? 'Voto') . ': ' . (int)($c['total'] ?? 0),
                        $v['votos']['por_tipo'] ?? []
                    );
                    $partes[] = "    Votos nominais carregados: " . (int)$v['votos']['total'] . ($contagens ? " (" . implode('; ', $contagens) . ")" : '');
                }
                foreach (array_slice($v['efeitos'] ?? [], 0, 2) as $efeito) {
                    if (!empty($efeito['resultado'])) {
                        $partes[] = "    Efeito registrado: " . mb_substr((string)$efeito['resultado'], 0, 180);
                    }
                }
            }
        }
        if (!empty($dados['senado']['encontrado'])) {
            $partes[] = "DADOS COMPLEMENTARES DO SENADO:";
            foreach (array_slice($dados['senado']['itens'] ?? [], 0, 3) as $s) {
                $partes[] = "  - {$s['identificacao']} · {$s['situacao']} · Autoria: {$s['autoria']}";
                if (!empty($s['url_documento'])) {
                    $partes[] = "    Documento Senado: {$s['url_documento']}";
                }
            }
            foreach (array_slice($dados['senado']['votacoes'] ?? [], 0, 4) as $v) {
                $aprovacao = $v['aprovacao'] ?? null;
                $resultado = $aprovacao === 1 ? 'votação aprovada' : ($aprovacao === 0 ? 'votação não aprovada' : 'resultado da votação não informado');
                $partes[] = "  - Votação Senado {$v['data']} ({$v['orgao']}) {$resultado}: " . mb_substr((string)($v['descricao'] ?? ''), 0, 220);
                if (!empty($v['votos']['total'])) {
                    $contagens = array_map(
                        fn($c) => ($c['tipo'] ?? 'Voto') . ': ' . (int)($c['total'] ?? 0),
                        $v['votos']['por_tipo'] ?? []
                    );
                    $partes[] = "    Votos nominais Senado: " . (int)$v['votos']['total'] . ($contagens ? " (" . implode('; ', $contagens) . ")" : '');
                }
            }
        }
        if (!empty($dados['texto_inteiro_teor'])) {
            $partes[] = "INTEIRO TEOR EXTRAIDO DO PDF:";
            $partes[] = trim((string)$dados['texto_inteiro_teor']);
        }
        return implode("\n", $partes);
    }

    private function buscarNoCsv(string $tipo, int $numero, int $ano): ?array
    {
        $path = $this->cachedCsv($ano);
        $fh = fopen($path, 'r');
        if (!$fh) {
            return null;
        }

        $header = fgetcsv($fh, 0, ';');
        if (!is_array($header)) {
            fclose($fh);
            return null;
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
        $header = array_map(fn($h) => trim((string)$h, "\" \t\n\r\0\x0B"), $header);

        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $item = array_combine($header, $row);
            if (!is_array($item)) {
                continue;
            }
            if (strtoupper((string)($item['siglaTipo'] ?? '')) !== $tipo) {
                continue;
            }
            if ((int)($item['numero'] ?? 0) !== $numero || (int)($item['ano'] ?? 0) !== $ano) {
                continue;
            }
            fclose($fh);
            return $this->fromCsvRow($item);
        }

        fclose($fh);
        return null;
    }

    private function fromCsvRow(array $item): array
    {
        $id = (int)($item['id'] ?? 0);
        $tramitacoes = [];
        if (!empty($item['ultimoStatus_dataHora']) || !empty($item['ultimoStatus_despacho'])) {
            $tramitacoes[] = [
                'data' => substr((string)($item['ultimoStatus_dataHora'] ?? ''), 0, 10),
                'orgao' => (string)($item['ultimoStatus_siglaOrgao'] ?? ''),
                'situacao' => (string)($item['ultimoStatus_descricaoSituacao'] ?? ''),
                'despacho' => trim((string)($item['ultimoStatus_despacho'] ?? '')),
            ];
        }

        return [
            'id' => $id,
            'tipo' => (string)($item['siglaTipo'] ?? ''),
            'numero' => (int)($item['numero'] ?? 0),
            'ano' => (int)($item['ano'] ?? 0),
            'ementa' => (string)($item['ementa'] ?? ''),
            'ementa_detalhada' => (string)($item['ementaDetalhada'] ?? ''),
            'keywords' => (string)($item['keywords'] ?? ''),
            'situacao' => (string)($item['ultimoStatus_descricaoSituacao'] ?? ''),
            'tramitacao' => (string)($item['ultimoStatus_descricaoTramitacao'] ?? ''),
            'autores' => [],
            'autores_detalhados' => [],
            'partidos_autores' => [],
            'inteiro_teor' => (string)($item['urlInteiroTeor'] ?? ''),
            'ultimo_status_url' => (string)($item['ultimoStatus_url'] ?? ''),
            'tramitacoes' => $tramitacoes,
            'votacoes' => [],
            'votacoes_meta' => $this->metaVotacoesProposicao(
                $id,
                'nao_consultado_csv',
                'Proposição carregada pelo arquivo anual da Câmara; votações relacionadas não foram consultadas automaticamente neste fallback.',
                8
            ),
            'senado' => (new SenadoClient())->buscar((string)($item['siglaTipo'] ?? ''), (int)($item['numero'] ?? 0), (int)($item['ano'] ?? 0)),
            'fonte' => 'Câmara dos Deputados (Dados Abertos - arquivo anual)',
        ];
    }

    private function cachedCsv(int $ano): string
    {
        $dir = \storagePath('camara');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . "/proposicoes-{$ano}.csv";
        if (is_file($path) && filesize($path) > 1024 && filemtime($path) > time() - 86400) {
            return $path;
        }

        $url = "https://dadosabertos.camara.leg.br/arquivos/proposicoes/csv/proposicoes-{$ano}.csv";
        $tmp = tempnam($dir, basename($path) . '.');
        if ($tmp === false) {
            throw new \RuntimeException('Não foi possível criar cache local da Câmara.');
        }
        $fp = fopen($tmp, 'w');
        if (!$fp) {
            @unlink($tmp);
            throw new \RuntimeException('Não foi possível criar cache local da Câmara.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => ['Accept: text/csv,*/*'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 180,
        ]);
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $code >= 400 || !is_file($tmp) || filesize($tmp) < 1024) {
            @unlink($tmp);
            $detail = $err !== '' ? " Detalhe: {$err}" : '';
            throw new \RuntimeException("Falha ao baixar arquivo anual da Câmara (HTTP {$code}).{$detail}");
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Não foi possível finalizar o cache local da Câmara.');
        }
        return $path;
    }

    private function get(string $url, int $maxAttempts = 3): array
    {
        $lastCode = 0;
        $lastError = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
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

            if (!$this->shouldRetry($lastCode) || $attempt === $maxAttempts) {
                break;
            }
            usleep($attempt * 350000);
        }

        $detail = $lastError !== '' ? " Detalhe: {$lastError}" : '';
        throw new \RuntimeException("Falha ao consultar a API da Câmara (HTTP {$lastCode}) após {$maxAttempts} tentativa(s). Tente novamente em alguns instantes.{$detail}");
    }

    private function getRapido(string $url, int $timeout = 10): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            $detail = $err !== '' ? " Detalhe: {$err}" : '';
            throw new \RuntimeException("Falha ao consultar detalhes da votação (HTTP {$code}).{$detail}");
        }

        return json_decode((string)$body, true) ?: [];
    }

    private function shouldRetry(int $code): bool
    {
        return $code === 0 || $code === 408 || $code === 429 || ($code >= 500 && $code <= 599);
    }
}
