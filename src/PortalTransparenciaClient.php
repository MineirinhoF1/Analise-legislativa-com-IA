<?php
/**
 * Consulta o Portal da Transparência do Governo Federal.
 * Foco: emendas parlamentares (quanto de verba o autor destinou).
 * Requer token grátis: https://portaldatransparencia.gov.br/api-de-dados/cadastrar-email
 */

namespace App;

class PortalTransparenciaClient
{
    private const BASE = 'https://api.portaldatransparencia.gov.br/api-de-dados';

    public function __construct(private string $token) {}

    public function temToken(): bool
    {
        return trim($this->token) !== '';
    }

    /**
     * Complementa uma consulta de CNPJ com bases oficiais do Portal da Transparência.
     * Retorna blocos compactos para exibição e análise; falhas pontuais não derrubam a busca principal.
     */
    public function contextoCnpj(string $cnpj): array
    {
        $cnpj = preg_replace('/\D+/', '', $cnpj) ?: '';
        if (strlen($cnpj) !== 14) {
            return ['disponivel' => false, 'motivo' => 'cnpj_invalido'];
        }
        if (!$this->temToken()) {
            return ['disponivel' => false, 'motivo' => 'sem_token'];
        }

        $tokenHash = substr(hash('sha256', trim($this->token)), 0, 16);
        $cache = \storagePath('portal-cnpj/' . $cnpj . '.json');
        if (is_file($cache) && filemtime($cache) > time() - 86400) {
            $cached = json_decode((string)file_get_contents($cache), true);
            if (
                is_array($cached)
                && (int)($cached['schema_version'] ?? 0) === 2
                && (string)($cached['token_hash'] ?? '') === $tokenHash
            ) {
                return $cached;
            }
        }

        $modulos = [
            'ceis' => [
                'rotulo' => 'CEIS',
                'desc' => 'Cadastro de empresas inidôneas e suspensas',
                'path' => '/ceis',
                'params' => ['codigoSancionado' => $cnpj, 'pagina' => 1],
            ],
            'cnep' => [
                'rotulo' => 'CNEP',
                'desc' => 'Cadastro de empresas punidas',
                'path' => '/cnep',
                'params' => ['codigoSancionado' => $cnpj, 'pagina' => 1],
            ],
            'cepim' => [
                'rotulo' => 'CEPIM',
                'desc' => 'Entidades impedidas',
                'path' => '/cepim',
                'params' => ['cnpjSancionado' => $cnpj, 'pagina' => 1],
            ],
            'acordos_leniencia' => [
                'rotulo' => 'Acordos de leniência',
                'desc' => 'Registros de acordos por CNPJ sancionado',
                'path' => '/acordos-leniencia',
                'params' => ['cnpjSancionado' => $cnpj, 'pagina' => 1],
            ],
            'renuncias_valor' => [
                'rotulo' => 'Renúncias fiscais - valores',
                'desc' => 'Valores renunciados vinculados ao CNPJ',
                'path' => '/renuncias-valor',
                'params' => ['cnpj' => $cnpj, 'pagina' => 1],
            ],
            'imunes_isentas' => [
                'rotulo' => 'PJ imunes/isentas',
                'desc' => 'Pessoas jurídicas imunes ou isentas',
                'path' => '/renuncias-fiscais-empresas-imunes-isentas',
                'params' => ['cnpj' => $cnpj, 'pagina' => 1],
            ],
            'beneficios_fiscais' => [
                'rotulo' => 'Benefícios fiscais',
                'desc' => 'Pessoas jurídicas habilitadas a benefício fiscal',
                'path' => '/renuncias-fiscais-empresas-habilitadas-beneficios-fiscais',
                'params' => ['cnpj' => $cnpj, 'pagina' => 1],
            ],
        ];

        $out = [
            'disponivel' => true,
            'schema_version' => 2,
            'token_hash' => $tokenHash,
            'cnpj' => $cnpj,
            'fonte' => 'Portal da Transparência',
            'consultado_em' => date('c'),
            'modulos' => [],
            'total_registros' => 0,
            'total_erros' => 0,
        ];

        foreach ($modulos as $key => $cfg) {
            try {
                $itens = $this->get($cfg['path'] . '?' . http_build_query($cfg['params']));
                $itens = is_array($itens) ? $itens : [];
                $amostra = array_map(fn($i) => $this->resumirRegistro(is_array($i) ? $i : []), array_slice($itens, 0, 5));
                $out['modulos'][$key] = [
                    'rotulo' => $cfg['rotulo'],
                    'descricao' => $cfg['desc'],
                    'qtd' => count($itens),
                    'amostra' => array_values(array_filter($amostra)),
                ];
                $out['total_registros'] += count($itens);
            } catch (\Throwable $e) {
                $out['modulos'][$key] = [
                    'rotulo' => $cfg['rotulo'],
                    'descricao' => $cfg['desc'],
                    'qtd' => 0,
                    'erro' => $e->getMessage(),
                    'amostra' => [],
                ];
                $out['total_erros']++;
            }
        }

        if ($out['total_erros'] >= count($modulos)) {
            $first = reset($out['modulos']);
            return [
                'disponivel' => false,
                'motivo' => 'api_indisponivel',
                'erro' => is_array($first) ? (string)($first['erro'] ?? 'Falha ao consultar o Portal da Transparência') : 'Falha ao consultar o Portal da Transparência',
                'cnpj' => $cnpj,
                'fonte' => 'Portal da Transparência',
            ];
        }

        if ($out['total_erros'] === 0) {
            $dir = dirname($cache);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($cache, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
        return $out;
    }

    /**
     * Busca emendas por nome de autor, somando valores por ano.
     * Retorna ['disponivel'=>bool, 'total_empenhado','total_pago','qtd','por_ano','amostra', 'erro'?].
     */
    public function emendas(string $nomeAutor, int $anos = 3): array
    {
        if (!$this->temToken()) {
            return ['disponivel' => false, 'motivo' => 'sem_token'];
        }

        $totalEmp = 0.0; $totalPago = 0.0; $qtd = 0;
        $porAno = []; $porAnoPago = []; $porFuncao = []; $porFuncaoPago = []; $amostra = [];
        $anoAtual = (int)date('Y');

        // Na base de emendas o autor é registrado em MAIÚSCULAS
        $nomeAutor = mb_strtoupper($nomeAutor, 'UTF-8');

        for ($ano = $anoAtual; $ano > $anoAtual - $anos; $ano--) {
            for ($pagina = 1; $pagina <= 3; $pagina++) {
                $url = self::BASE . '/emendas?' . http_build_query([
                    'nomeAutor' => $nomeAutor,
                    'ano'       => $ano,
                    'pagina'    => $pagina,
                ]);
                $itens = $this->get($url);
                if (!is_array($itens) || !$itens) break;

                foreach ($itens as $e) {
                    $emp  = $this->valor($e['valorEmpenhado'] ?? 0);
                    $pago = $this->valor($e['valorPago'] ?? 0);
                    $totalEmp  += $emp;
                    $totalPago += $pago;
                    $porAno[$ano] = ($porAno[$ano] ?? 0) + $emp;
                    $porAnoPago[$ano] = ($porAnoPago[$ano] ?? 0) + $pago;
                    $funcao = $e['funcao'] ?? ($e['nomeFuncao'] ?? 'Area nao informada');
                    $porFuncao[$funcao] = ($porFuncao[$funcao] ?? 0) + $emp;
                    $porFuncaoPago[$funcao] = ($porFuncaoPago[$funcao] ?? 0) + $pago;
                    $qtd++;
                    if (count($amostra) < 15) {
                        $amostra[] = [
                            'ano'       => $ano,
                            'funcao'    => $funcao,
                            'programa'  => $e['programa'] ?? ($e['nomePrograma'] ?? ''),
                            'acao'      => $e['acao'] ?? ($e['nomeAcao'] ?? ''),
                            'localidade'=> $e['localidadeDoGasto'] ?? ($e['municipio'] ?? ($e['uf'] ?? '')),
                            'favorecido'=> $e['favorecido'] ?? ($e['nomeFavorecido'] ?? ($e['beneficiario'] ?? '')),
                            'empenhado' => $emp,
                            'pago'      => $pago,
                        ];
                    }
                }
                if (count($itens) < 15) break; // página incompleta = última
            }
        }

        arsort($porFuncao);
        arsort($porFuncaoPago);

        return [
            'disponivel'     => true,
            'total_empenhado'=> round($totalEmp, 2),
            'total_pago'     => round($totalPago, 2),
            'qtd'            => $qtd,
            'por_ano'        => $porAno,
            'por_ano_pago'   => $porAnoPago,
            'por_funcao'     => $porFuncao,
            'por_funcao_pago'=> $porFuncaoPago,
            'amostra'        => $amostra,
        ];
    }

    /** Converte valores em float, aceitando número nativo, "200.000,00" (BR) ou "200000.50" (US). */
    private function valor(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float)$v;
        }
        $s = trim((string)$v);
        if ($s === '') {
            return 0.0;
        }
        if (str_contains($s, ',')) {
            // formato BR: ponto é milhar, vírgula é decimal
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            // só pontos agrupando de 3 em 3: milhar BR sem decimais ("1.234.567")
            $s = str_replace('.', '', $s);
        }
        // demais casos ("200000.5", "1234"): ponto já é decimal
        return (float)$s;
    }

    private function resumirRegistro(array $i): array
    {
        $titulo = $this->firstString($i, [
            'sancionado.nome',
            'pessoa.nome',
            'pessoaJuridica.nome',
            'beneficiarioNome',
            'razaoSocial',
            'nomeFantasia',
            'orgaoResponsavel',
            'orgaoSancionador.nome',
        ]);
        $tipo = $this->firstString($i, [
            'tipoSancao.descricaoResumida',
            'tipoSancao.descricaoPortal',
            'tipoRenuncia',
            'descricaoBeneficioFiscal',
            'motivo',
            'situacaoAcordo',
            'tipoBeneficio',
            'modalidade',
        ]);
        $orgao = $this->firstString($i, [
            'orgaoSancionador.nome',
            'orgaoResponsavel',
            'orgaoSuperior.nome',
            'fonteSancao.nomeExibicao',
        ]);
        $data = $this->firstString($i, [
            'dataInicioSancao',
            'dataPublicacaoSancao',
            'dataReferencia',
            'dataInicioAcordo',
        ]);
        $valor = $this->firstString($i, [
            'valorMulta',
            'valor',
            'valorRenunciado',
            'valorTotal',
        ]);
        $processo = $this->firstString($i, ['numeroProcesso', 'processo']);

        return [
            'titulo' => $titulo ?: ($tipo ?: 'Registro encontrado'),
            'tipo' => $tipo,
            'orgao' => $orgao,
            'data' => $data,
            'valor' => $valor,
            'processo' => $processo,
        ];
    }

    private function firstString(array $data, array $paths): string
    {
        foreach ($paths as $path) {
            $value = $this->pathValue($data, $path);
            if (is_string($value) || is_numeric($value)) {
                $s = trim((string)$value);
                if ($s !== '') {
                    return $s;
                }
            }
        }
        return '';
    }

    private function pathValue(array $data, string $path): mixed
    {
        $value = $data;
        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }
        return $value;
    }

    private function get(string $url): mixed
    {
        $ch = curl_init(str_starts_with($url, 'http') ? $url : self::BASE . $url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'chave-api-dados: ' . $this->token,
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($code === 401 || $code === 403) {
            throw new \RuntimeException('Token do Portal da Transparência inválido ou sem permissão.');
        }
        if ($body === false || $code >= 400) {
            $msg = $body === false ? ($error ?: 'Falha de conexão') : trim(mb_substr(strip_tags((string)$body), 0, 180));
            throw new \RuntimeException($msg !== '' ? "HTTP {$code}: {$msg}" : "HTTP {$code}");
        }
        $json = json_decode($body, true);
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            $converted = @mb_convert_encoding($body, 'UTF-8', 'ISO-8859-1');
            if (is_string($converted)) {
                $json = json_decode($converted, true);
            }
        }
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Resposta inválida do Portal da Transparência (JSON malformado).');
        }
        return $json;
    }
}
