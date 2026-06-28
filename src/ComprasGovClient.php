<?php
/**
 * Consulta dados abertos do Compras.gov.br.
 * Fonte aberta, sem chave: https://dadosabertos.compras.gov.br/v3/api-docs
 */

namespace App;

class ComprasGovClient
{
    private const BASE = 'https://dadosabertos.compras.gov.br';

    public function contextoCnpj(string $cnpj): array
    {
        $cnpj = preg_replace('/\D+/', '', $cnpj) ?: '';
        if (strlen($cnpj) !== 14) {
            return ['disponivel' => false, 'motivo' => 'cnpj_invalido'];
        }

        $cache = \storagePath('compras-gov/' . $cnpj . '.json');
        if (is_file($cache) && filemtime($cache) > time() - 86400) {
            $cached = json_decode((string)file_get_contents($cache), true);
            if (is_array($cached) && (int)($cached['schema_version'] ?? 0) === 3) {
                return $cached;
            }
        }

        $fornecedor = $this->consultarFornecedor($cnpj);
        $resultados = $this->consultarResultadosPncp($cnpj);
        if (!empty($fornecedor['erro']) && !empty($resultados['erro'])) {
            return [
                'disponivel' => false,
                'motivo' => 'api_indisponivel',
                'erro' => $fornecedor['erro'],
                'fonte' => 'Compras.gov.br Dados Abertos',
            ];
        }
        $out = [
            'disponivel' => true,
            'schema_version' => 3,
            'cnpj' => $cnpj,
            'fonte' => 'Compras.gov.br Dados Abertos',
            'consultado_em' => date('c'),
            'fornecedor' => $fornecedor,
            'resultados_pncp' => $resultados,
            'total_registros_pncp' => (int)($resultados['total_registros'] ?? 0),
        ];

        $dir = dirname($cache);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($cache, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $out;
    }

    private function consultarFornecedor(string $cnpj): array
    {
        $res = $this->get('/modulo-fornecedor/1_consultarFornecedor', [
            'cnpj' => $cnpj,
            'ativo' => 'true',
            'pagina' => 1,
            'tamanhoPagina' => 10,
        ]);
        if (!empty($res['_erro'])) {
            return ['qtd' => 0, 'amostra' => [], 'erro' => $res['_erro']];
        }
        $itens = $this->resultado($res);
        return [
            'qtd' => count($itens),
            'amostra' => array_map(fn($i) => $this->normalizarFornecedor($i), array_slice($itens, 0, 5)),
        ];
    }

    private function consultarResultadosPncp(string $cnpj): array
    {
        $fim = date('Y-m-d');
        $inicio = date('Y-m-d', strtotime('-365 days'));
        $res = $this->get('/modulo-contratacoes/3_consultarResultadoItensContratacoes_PNCP_14133', [
            'niFornecedor' => $cnpj,
            'dataResultadoPncpInicial' => $inicio,
            'dataResultadoPncpFinal' => $fim,
            'pagina' => 1,
            'tamanhoPagina' => 10,
        ]);
        if (!empty($res['_erro'])) {
            return [
                'periodo_inicio' => $inicio,
                'periodo_fim' => $fim,
                'total_registros' => 0,
                'valor_amostra' => 0,
                'amostra' => [],
                'erro' => $res['_erro'],
            ];
        }
        $itens = $this->resultado($res);
        $total = (int)($res['totalRegistros'] ?? count($itens));
        $valor = 0.0;
        foreach ($itens as $i) {
            $valor += $this->valor($i['valorTotalHomologado'] ?? 0);
        }
        return [
            'periodo_inicio' => $inicio,
            'periodo_fim' => $fim,
            'total_registros' => $total,
            'valor_amostra' => round($valor, 2),
            'amostra' => array_map(fn($i) => $this->normalizarResultado($i), array_slice($itens, 0, 10)),
        ];
    }

    private function normalizarFornecedor(array $i): array
    {
        return [
            'nome' => (string)($i['nomeRazaoSocialFornecedor'] ?? $i['nomeRazaoSocial'] ?? $i['razaoSocial'] ?? ''),
            'cnpj' => preg_replace('/\D+/', '', (string)($i['cnpj'] ?? $i['niFornecedor'] ?? '')) ?: '',
            'porte' => (string)($i['porteEmpresaNome'] ?? $i['porteFornecedorNome'] ?? ''),
            'natureza' => (string)($i['naturezaJuridicaNome'] ?? ''),
            'ativo' => $i['ativo'] ?? null,
        ];
    }

    private function normalizarResultado(array $i): array
    {
        return [
            'data' => substr((string)($i['dataResultadoPncp'] ?? $i['dataInclusaoPncp'] ?? ''), 0, 10),
            'orgao' => (string)($i['orgaoEntidadeRazaoSocial'] ?? $i['unidadeOrgaoNomeUnidade'] ?? ''),
            'uf' => (string)($i['unidadeOrgaoUfSigla'] ?? ''),
            'item' => (string)($i['descricaoResumida'] ?? $i['descricaodetalhada'] ?? ''),
            'valor' => round($this->valor($i['valorTotalHomologado'] ?? 0), 2),
            'numero_controle' => (string)($i['numeroControlePNCPCompra'] ?? ''),
        ];
    }

    private function resultado(array $res): array
    {
        $itens = $res['resultado'] ?? [];
        return is_array($itens) ? $itens : [];
    }

    private function get(string $path, array $params): array
    {
        $url = self::BASE . $path . '?' . http_build_query($params);
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
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            return ['_erro' => $body === false ? ($error ?: 'Falha de conexão') : "HTTP {$code}"];
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['_erro' => 'JSON inválido na resposta da API'];
        }
        return $json;
    }

    private function valor(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float)$v;
        }
        $s = str_replace(',', '.', trim((string)$v));
        return is_numeric($s) ? (float)$s : 0.0;
    }
}
