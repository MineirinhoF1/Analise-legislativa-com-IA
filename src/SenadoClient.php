<?php
/**
 * Consulta processos legislativos nos Dados Abertos do Senado Federal.
 */

namespace App;

class SenadoClient
{
    private const BASE = 'https://legis.senado.leg.br/dadosabertos';

    public function buscar(string $tipo, int $numero, int $ano): array
    {
        $tipo = strtoupper(trim($tipo));
        if ($tipo === '' || $numero <= 0 || $ano <= 0) {
            return ['encontrado' => false, 'itens' => []];
        }

        $json = $this->get('/processo', [
            'sigla' => $tipo,
            'numero' => $numero,
            'ano' => $ano,
        ]);
        $itens = is_array($json) ? $json : [];
        $itens = array_values(array_filter($itens, fn($i) => is_array($i)));
        $normalizados = [];
        foreach (array_slice($itens, 0, 5) as $item) {
            $normalizado = $this->normalizar($item);
            $normalizado['votacoes'] = $this->votacoesMateria(
                (int)$normalizado['codigo_materia'],
                $normalizado['tipo'] ?: $tipo,
                (int)($normalizado['numero'] ?: $numero),
                (int)($normalizado['ano'] ?: $ano)
            );
            $normalizados[] = $normalizado;
        }

        return [
            'encontrado' => count($itens) > 0,
            'fonte' => 'Senado Federal (Dados Abertos)',
            'itens' => $normalizados,
            'votacoes' => $normalizados[0]['votacoes'] ?? [],
            'links' => [
                'processo' => self::BASE . '/processo?' . http_build_query([
                    'sigla' => $tipo,
                    'numero' => $numero,
                    'ano' => $ano,
                ]),
            ],
        ];
    }

    public function fallbackComoProposicao(string $tipo, int $numero, int $ano): ?array
    {
        $senado = $this->buscar($tipo, $numero, $ano);
        if (empty($senado['encontrado']) || empty($senado['itens'][0])) {
            return null;
        }
        $p = $senado['itens'][0];
        return [
            'id' => 0,
            'tipo' => $p['tipo'] ?: $tipo,
            'numero' => $p['numero'] ?: $numero,
            'ano' => $p['ano'] ?: $ano,
            'ementa' => $p['ementa'],
            'ementa_detalhada' => '',
            'keywords' => '',
            'situacao' => $p['situacao'],
            'tramitacao' => $p['ultima_informacao'],
            'autores' => array_filter([$p['autoria']]),
            'autores_detalhados' => [],
            'partidos_autores' => [],
            'inteiro_teor' => $p['url_documento'],
            'tramitacoes' => $p['data_situacao'] ? [[
                'data' => $p['data_situacao'],
                'orgao' => 'Senado',
                'situacao' => $p['situacao'],
                'despacho' => $p['ultima_informacao'],
            ]] : [],
            'votacoes' => $p['votacoes'] ?? [],
            'votacoes_meta' => [
                'status' => 'id_invalido',
                'mensagem' => 'Proposição carregada pelo Senado; não há ID da Câmara para consultar /proposicoes/{id}/votacoes.',
                'erro' => null,
                'endpoint' => '/proposicoes/{id}/votacoes',
                'url' => '',
                'links_oficiais' => [
                    'proposicao' => '',
                    'votacoes' => '',
                ],
                'total_retornado' => 0,
                'total_exibido' => 0,
                'total_enriquecido' => 0,
                'limite' => 8,
                'fonte' => 'Câmara dos Deputados (Dados Abertos)',
            ],
            'senado' => $senado,
            'fonte' => 'Senado Federal (Dados Abertos)',
            'links' => $p['links'] ?? [],
        ];
    }

    private function normalizar(array $i): array
    {
        $ident = (string)($i['identificacao'] ?? '');
        preg_match('/^([A-Z]+)\s+0*(\d+)\/(\d{4})/u', $ident, $m);
        return [
            'id' => (int)($i['id'] ?? 0),
            'codigo_materia' => (int)($i['codigoMateria'] ?? 0),
            'tipo' => $m[1] ?? (string)($i['tipoDocumento'] ?? ''),
            'numero' => isset($m[2]) ? (int)$m[2] : 0,
            'ano' => isset($m[3]) ? (int)$m[3] : 0,
            'identificacao' => $ident,
            'autoria' => (string)($i['autoria'] ?? ''),
            'ementa' => (string)($i['ementa'] ?? ''),
            'situacao' => (string)($i['situacaoAtual'] ?? ''),
            'data_apresentacao' => (string)($i['dataApresentacao'] ?? ''),
            'data_situacao' => (string)($i['dataSituacaoAtual'] ?? ''),
            'tramitando' => (string)($i['tramitando'] ?? ''),
            'objetivo' => (string)($i['objetivo'] ?? ''),
            'casa' => (string)($i['casaIdentificadora'] ?? ''),
            'ultima_informacao' => (string)($i['ultimaInformacaoAtualizada'] ?? ''),
            'url_documento' => (string)($i['urlDocumento'] ?? ''),
            'links' => [
                'documento' => (string)($i['urlDocumento'] ?? ''),
                'processo' => self::BASE . '/processo?' . http_build_query([
                    'sigla' => $m[1] ?? (string)($i['tipoDocumento'] ?? ''),
                    'numero' => isset($m[2]) ? (int)$m[2] : 0,
                    'ano' => isset($m[3]) ? (int)$m[3] : 0,
                ]),
                'votacoes' => !empty($i['codigoMateria'])
                    ? self::BASE . '/materia/votacoes/' . rawurlencode((string)$i['codigoMateria'])
                    : '',
            ],
        ];
    }

    private function votacoesMateria(int $codigoMateria, string $tipo = '', int $numero = 0, int $ano = 0): array
    {
        if ($codigoMateria <= 0) {
            return [];
        }

        $novas = $this->votacoesServicoAtual($codigoMateria, $tipo, $numero, $ano);
        if ($novas) {
            return $novas;
        }

        $json = $this->get('/materia/votacoes/' . rawurlencode((string)$codigoMateria));
        $votacoes = $json['VotacaoMateria']['Materia']['Votacoes']['Votacao'] ?? [];
        $out = [];
        foreach ($this->lista($votacoes) as $votacao) {
            $out[] = $this->normalizarVotacao($votacao, $codigoMateria);
        }
        usort($out, fn($a, $b) => strcmp((string)($b['data'] ?? ''), (string)($a['data'] ?? '')));
        return $out;
    }

    private function votacoesServicoAtual(int $codigoMateria, string $tipo, int $numero, int $ano): array
    {
        if ($tipo === '' || $numero <= 0 || $ano <= 0) {
            return [];
        }
        $json = $this->get('/votacao', [
            'sigla' => $tipo,
            'numero' => $numero,
            'ano' => $ano,
        ]);
        if (!is_array($json)) {
            return [];
        }

        $out = [];
        foreach ($this->lista($json) as $votacao) {
            if ((int)($votacao['codigoMateria'] ?? 0) !== $codigoMateria) {
                continue;
            }
            $out[] = $this->normalizarVotacaoAtual($votacao, $codigoMateria, $tipo, $numero, $ano);
        }
        usort($out, fn($a, $b) => strcmp((string)($b['data'] ?? ''), (string)($a['data'] ?? '')));
        return $out;
    }

    private function normalizarVotacaoAtual(array $v, int $codigoMateria, string $tipo, int $numero, int $ano): array
    {
        $id = (string)($v['codigoSessaoVotacao'] ?? '');
        $data = substr((string)($v['dataSessao'] ?? ''), 0, 10);
        $resultado = $this->resultadoVotacaoAtual((string)($v['resultadoVotacao'] ?? ''));
        $url = self::BASE . '/votacao?' . http_build_query([
            'sigla' => $tipo,
            'numero' => $numero,
            'ano' => $ano,
        ]);
        $descricao = trim((string)($v['descricaoVotacao'] ?? ''));
        $votos = $this->lista($v['votos'] ?? []);

        return [
            'id' => $id !== '' ? 'senado-' . $id : 'senado-materia-' . $codigoMateria,
            'codigo_sessao_votacao' => $id,
            'codigo_materia' => $codigoMateria,
            'data' => $data,
            'orgao' => (string)($v['casaSessao'] ?? 'SF'),
            'proposicao_objeto' => $descricao,
            'descricao' => $descricao,
            'resultado' => $resultado,
            'aprovacao' => $this->resultadoAprovacao($resultado),
            'uri' => $url,
            'uri_votos' => $url,
            'fonte' => 'Senado Federal (Dados Abertos)',
            'links' => [
                'votacoes' => $url,
                'materia_votacoes' => self::BASE . '/materia/votacoes/' . rawurlencode((string)$codigoMateria),
            ],
            'votos' => $this->resumirVotosAtuais($votos, $data),
        ];
    }

    private function normalizarVotacao(array $v, int $codigoMateria): array
    {
        $sessao = is_array($v['SessaoPlenaria'] ?? null) ? $v['SessaoPlenaria'] : [];
        $id = (string)($v['CodigoSessaoVotacao'] ?? '');
        $data = substr((string)($sessao['DataSessao'] ?? ''), 0, 10);
        $resultado = trim((string)($v['DescricaoResultado'] ?? ''));
        $url = self::BASE . '/materia/votacoes/' . rawurlencode((string)$codigoMateria);
        $descricao = trim((string)($v['DescricaoVotacao'] ?? ''));
        $votos = $this->lista($v['Votos']['VotoParlamentar'] ?? []);

        return [
            'id' => $id !== '' ? 'senado-' . $id : 'senado-materia-' . $codigoMateria,
            'codigo_sessao_votacao' => $id,
            'codigo_materia' => $codigoMateria,
            'data' => $data,
            'orgao' => (string)($sessao['SiglaCasaSessao'] ?? $sessao['NomeCasaSessao'] ?? 'Senado'),
            'proposicao_objeto' => $descricao,
            'descricao' => $descricao,
            'resultado' => $resultado,
            'aprovacao' => $this->resultadoAprovacao($resultado),
            'uri' => $url,
            'uri_votos' => $url,
            'fonte' => 'Senado Federal (Dados Abertos)',
            'links' => [
                'votacoes' => $url,
                'tipos_voto' => (string)($votos[0]['UrlListaTiposVoto'] ?? ''),
            ],
            'votos' => $this->resumirVotos($votos, $data),
        ];
    }

    private function resumirVotos(array $votos, string $data): array
    {
        $porTipo = [];
        $porPartido = [];
        $porUf = [];
        $amostra = [];

        foreach ($votos as $voto) {
            if (!is_array($voto)) {
                continue;
            }
            $parlamentar = is_array($voto['IdentificacaoParlamentar'] ?? null) ? $voto['IdentificacaoParlamentar'] : [];
            $tipo = trim((string)($voto['SiglaVoto'] ?? $voto['DescricaoVoto'] ?? '')) ?: 'Nao informado';
            $partido = trim((string)($parlamentar['SiglaPartidoParlamentar'] ?? '')) ?: 'S.PART.';
            $uf = trim((string)($parlamentar['UfParlamentar'] ?? '')) ?: 'UF nao informada';

            $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + 1;
            $porPartido[$partido] = ($porPartido[$partido] ?? 0) + 1;
            $porUf[$uf] = ($porUf[$uf] ?? 0) + 1;

            if (count($amostra) < 40) {
                $amostra[] = [
                    'nome' => (string)($parlamentar['NomeParlamentar'] ?? ''),
                    'partido' => $partido,
                    'uf' => $uf,
                    'voto' => $tipo,
                    'descricao' => (string)($voto['DescricaoVoto'] ?? ''),
                    'data' => $data,
                    'uri' => (string)($parlamentar['UrlPaginaParlamentar'] ?? ''),
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

    private function resumirVotosAtuais(array $votos, string $data): array
    {
        $porTipo = [];
        $porPartido = [];
        $porUf = [];
        $amostra = [];

        foreach ($votos as $voto) {
            if (!is_array($voto)) {
                continue;
            }
            $tipo = trim((string)($voto['siglaVotoParlamentar'] ?? $voto['descricaoVotoParlamentar'] ?? '')) ?: 'Nao informado';
            $partido = trim((string)($voto['siglaPartidoParlamentar'] ?? '')) ?: 'S.PART.';
            $uf = trim((string)($voto['siglaUFParlamentar'] ?? '')) ?: 'UF nao informada';

            $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + 1;
            $porPartido[$partido] = ($porPartido[$partido] ?? 0) + 1;
            $porUf[$uf] = ($porUf[$uf] ?? 0) + 1;

            if (count($amostra) < 40) {
                $amostra[] = [
                    'nome' => (string)($voto['nomeParlamentar'] ?? ''),
                    'partido' => $partido,
                    'uf' => $uf,
                    'voto' => $tipo,
                    'descricao' => (string)($voto['descricaoVotoParlamentar'] ?? ''),
                    'data' => $data,
                    'uri' => '',
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

    private function resultadoAprovacao(string $resultado): ?int
    {
        if ($resultado === '') {
            return null;
        }
        if (preg_match('/n[aã]o\s+aprovad[oa]|rejeitad[oa]/iu', $resultado)) {
            return 0;
        }
        if (preg_match('/aprovad[oa]/iu', $resultado)) {
            return 1;
        }
        return null;
    }

    private function resultadoVotacaoAtual(string $codigo): string
    {
        return match (strtoupper(trim($codigo))) {
            'A' => 'Aprovado',
            'R' => 'Rejeitado',
            default => $codigo,
        };
    }

    private function lista(mixed $valor): array
    {
        if (!is_array($valor)) {
            return [];
        }
        if (array_is_list($valor)) {
            return array_values(array_filter($valor, fn($i) => is_array($i)));
        }
        return [$valor];
    }

    private function get(string $path, array $params = []): mixed
    {
        $url = self::BASE . $path . ($params ? '?' . http_build_query($params) : '');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            return null;
        }
        return json_decode($body, true);
    }
}
