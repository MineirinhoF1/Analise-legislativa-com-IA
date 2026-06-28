<?php
/**
 * Cadastro eleitoral via Repositorio de Dados Eleitorais do TSE.
 *
 * Uso inicial: localizar eleitos/candidatos para cargos que nao tem API
 * legislativa nacional padronizada. Isto nao substitui dados de atuacao.
 */
namespace App;

class TseEleitosClient
{
    private const BASE_ZIP = 'https://cdn.tse.jus.br/estatistica/sead/odsele/consulta_cand/consulta_cand_%d.zip';

    private const CARGO_ANO_PADRAO = [
        'vereador' => 2024,
        'deputado_estadual' => 2022,
        'senador' => 2022,
    ];

    public function buscar(string $cargo, string $nome = '', string $partido = '', string $uf = '', string $municipio = '', int $ano = 0): array
    {
        $cargo = $this->normalizarCargo($cargo);
        $ano = $ano ?: (self::CARGO_ANO_PADRAO[$cargo] ?? 2024);
        $nomeNorm = $this->normalizar($nome);
        $partido = mb_strtoupper(trim($partido), 'UTF-8');
        $uf = mb_strtoupper(trim($uf), 'UTF-8');
        $municipioNorm = $this->normalizar($municipio);

        $out = [];
        foreach ($this->iterarCandidatos($ano) as $row) {
            if (!$this->cargoBate($cargo, (string)($row['DS_CARGO'] ?? ''))) {
                continue;
            }
            if ($nomeNorm !== '') {
                $nomeCand = $this->normalizar(($row['NM_CANDIDATO'] ?? '') . ' ' . ($row['NM_URNA_CANDIDATO'] ?? ''));
                if (!str_contains($nomeCand, $nomeNorm)) {
                    continue;
                }
            }
            if ($partido !== '' && mb_strtoupper((string)($row['SG_PARTIDO'] ?? ''), 'UTF-8') !== $partido) {
                continue;
            }
            if ($uf !== '' && mb_strtoupper((string)($row['SG_UF'] ?? ''), 'UTF-8') !== $uf) {
                continue;
            }
            if ($municipioNorm !== '' && !str_contains($this->normalizar((string)($row['NM_UE'] ?? '')), $municipioNorm)) {
                continue;
            }
            if (!$this->situacaoEleitaOuUtil($row)) {
                continue;
            }
            $out[] = $this->linhaParaResultado($row, $cargo, $ano);
            if (count($out) >= 80) {
                break;
            }
        }

        return $out;
    }

    public function perfil(string $id): array
    {
        $parts = explode(':', $id);
        if (count($parts) < 4 || $parts[0] !== 'tse') {
            throw new \RuntimeException('ID do cadastro TSE inválido.');
        }
        [, $anoRaw, $cargoRaw, $sq] = $parts;
        $ano = (int)$anoRaw;
        $cargo = $this->normalizarCargo($cargoRaw);
        $sq = preg_replace('/\D+/', '', $sq) ?: '';
        if ($ano <= 0 || $sq === '') {
            throw new \RuntimeException('ID do cadastro TSE incompleto.');
        }

        foreach ($this->iterarCandidatos($ano) as $row) {
            if ((string)($row['SQ_CANDIDATO'] ?? '') !== $sq) {
                continue;
            }
            if (!$this->cargoBate($cargo, (string)($row['DS_CARGO'] ?? ''))) {
                continue;
            }
            $p = $this->linhaParaPerfil($row, $cargo, $ano);
            $p['texto_base'] = self::montarTexto($p);
            return $p;
        }
        throw new \RuntimeException('Registro TSE não encontrado no cache/dataset oficial.');
    }

    public static function montarTexto(array $p): string
    {
        $l = [];
        $l[] = "CADASTRO ELEITORAL TSE: {$p['nome']} ({$p['partido']}-{$p['uf']})";
        $l[] = "Cargo: {$p['cargo']} · Eleição: {$p['eleicao_ano']} · Município/UF: {$p['municipio']}/{$p['uf']}";
        $l[] = "Nome completo: {$p['nome_civil']}";
        $l[] = "Nome de urna: {$p['nome']}";
        $l[] = "Número: {$p['numero_candidato']} · Situação no turno: {$p['situacao_eleitoral']}";
        $l[] = "";
        $l[] = "ESCOPO DOS DADOS: cadastro e resultado eleitoral publicados pelo TSE.";
        $l[] = "LIMITAÇÃO: estes dados indicam candidatura/resultado eleitoral. Não incluem atuação legislativa, projetos, votos, gastos de mandato, comissões ou fornecedores do parlamentar eleito.";
        foreach ($p['links_externos'] ?? [] as $link) {
            $l[] = "FONTE OFICIAL: {$link['rotulo']} - {$link['url']}";
        }
        return implode("\n", $l);
    }

    private function linhaParaResultado(array $row, string $cargo, int $ano): array
    {
        return [
            'id' => 'tse:' . $ano . ':' . $cargo . ':' . ($row['SQ_CANDIDATO'] ?? ''),
            'fonte_tipo' => 'tse',
            'nome' => (string)($row['NM_URNA_CANDIDATO'] ?: $row['NM_CANDIDATO'] ?? ''),
            'partido' => (string)($row['SG_PARTIDO'] ?? ''),
            'uf' => (string)($row['SG_UF'] ?? ''),
            'foto' => '',
            'email' => '',
            'cargo' => $this->cargoLabel($cargo),
            'subtitulo' => trim((string)($row['NM_UE'] ?? '') . ' · ' . (string)($row['DS_SIT_TOT_TURNO'] ?? '')),
        ];
    }

    private function linhaParaPerfil(array $row, string $cargo, int $ano): array
    {
        return [
            'id' => 'tse:' . $ano . ':' . $cargo . ':' . ($row['SQ_CANDIDATO'] ?? ''),
            'origem' => 'tse',
            'nome' => (string)($row['NM_URNA_CANDIDATO'] ?: $row['NM_CANDIDATO'] ?? ''),
            'nome_civil' => (string)($row['NM_CANDIDATO'] ?? ''),
            'partido' => (string)($row['SG_PARTIDO'] ?? ''),
            'uf' => (string)($row['SG_UF'] ?? ''),
            'municipio' => (string)($row['NM_UE'] ?? ''),
            'cargo' => $this->cargoLabel($cargo),
            'situacao' => (string)($row['DS_SIT_TOT_TURNO'] ?? ''),
            'condicao' => 'Cadastro eleitoral TSE',
            'foto' => '',
            'email' => '',
            'escolaridade' => (string)($row['DS_GRAU_INSTRUCAO'] ?? ''),
            'nascimento' => (string)($row['DT_NASCIMENTO'] ?? ''),
            'numero_candidato' => (string)($row['NR_CANDIDATO'] ?? ''),
            'situacao_eleitoral' => (string)($row['DS_SIT_TOT_TURNO'] ?? ''),
            'eleicao_ano' => $ano,
            'despesas' => $this->despesasVazias('TSE'),
            'proposicoes' => [],
            'prop_resumo' => ['total' => 0, 'aprovada' => 0, 'arquivada' => 0, 'tramitando' => 0],
            'frentes' => [],
            'comissoes' => [],
            'comissoes_detalhadas' => [],
            'votos_recentes' => [],
            'emendas' => ['disponivel' => false],
            'links_externos' => [
                ['rotulo' => 'Repositório de Dados Eleitorais do TSE', 'url' => 'https://dadosabertos.tse.jus.br/', 'desc' => 'Base oficial de dados eleitorais.'],
                ['rotulo' => 'Arquivo consulta_cand_' . $ano, 'url' => sprintf(self::BASE_ZIP, $ano), 'desc' => 'Arquivo oficial de candidaturas do ano eleitoral.'],
            ],
            'fonte' => 'Tribunal Superior Eleitoral (Dados Abertos)',
        ];
    }

    /** @return \Generator<array<string,string>> */
    private function iterarCandidatos(int $ano): \Generator
    {
        $csv = $this->csvPath($ano);
        $fh = fopen($csv, 'r');
        if (!$fh) {
            throw new \RuntimeException('Não foi possível abrir o arquivo do TSE.');
        }
        try {
            $header = fgetcsv($fh, 0, ';');
            if (!is_array($header)) {
                return;
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
            $header = array_map(fn($h) => trim($this->toUtf8((string)$h), "\" \t\n\r\0\x0B"), $header);

            while (($row = fgetcsv($fh, 0, ';')) !== false) {
                if (count($row) !== count($header)) {
                    continue;
                }
                $row = array_map(fn($v) => $this->toUtf8((string)$v), $row);
                $item = array_combine($header, $row);
                if (is_array($item)) {
                    yield $item;
                }
            }
        } finally {
            fclose($fh);
        }
    }

    private function csvPath(int $ano): string
    {
        $dir = \storagePath('tse');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $csv = $dir . "/consulta_cand_{$ano}_BRASIL.csv";
        if (is_file($csv) && filesize($csv) > 1024) {
            return $csv;
        }

        $zip = $dir . "/consulta_cand_{$ano}.zip";
        if (!is_file($zip) || filesize($zip) < 1024) {
            $this->download(sprintf(self::BASE_ZIP, $ano), $zip);
        }

        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Extensão PHP zip indisponível para abrir o arquivo do TSE.');
        }

        $za = new \ZipArchive();
        if ($za->open($zip) !== true) {
            throw new \RuntimeException('Não foi possível abrir o ZIP do TSE.');
        }
        try {
            $targetIndex = false;
            for ($i = 0; $i < $za->numFiles; $i++) {
                $name = $za->getNameIndex($i);
                if (preg_match('/consulta_cand_' . preg_quote((string)$ano, '/') . '_BRASIL\.csv$/i', $name)) {
                    $targetIndex = $i;
                    break;
                }
            }
            if ($targetIndex === false) {
                throw new \RuntimeException('CSV Brasil não encontrado no ZIP do TSE.');
            }
            $entryName = $za->getNameIndex($targetIndex);
            if ($entryName === false) {
                throw new \RuntimeException('Entrada CSV não encontrada no ZIP do TSE.');
            }
            $stream = $za->getStream($entryName);
            if (!$stream) {
                throw new \RuntimeException('Não foi possível ler o CSV do TSE.');
            }
            $out = fopen($csv, 'w');
            if (!$out) {
                fclose($stream);
                throw new \RuntimeException('Não foi possível criar cache do CSV do TSE.');
            }
            if (stream_copy_to_stream($stream, $out) === false) {
                fclose($stream);
                fclose($out);
                throw new \RuntimeException('Não foi possível gravar o CSV do TSE.');
            }
            fclose($stream);
            fclose($out);
        } finally {
            $za->close();
        }
        return $csv;
    }

    private function download(string $url, string $path): void
    {
        $dir = dirname($path);
        $tmp = tempnam($dir, basename($path) . '.');
        if ($tmp === false) {
            throw new \RuntimeException('Não foi possível criar arquivo temporário do TSE.');
        }
        $fp = fopen($tmp, 'w');
        if (!$fp) {
            @unlink($tmp);
            throw new \RuntimeException('Não foi possível criar arquivo temporário do TSE.');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 240,
            CURLOPT_HTTPHEADER => ['Accept: application/zip,*/*'],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ResumoTransparente/1.0)',
        ]);
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if (!$ok || $code >= 400) {
            @unlink($tmp);
            throw new \RuntimeException("Não foi possível baixar dados do TSE" . ($err ? ": {$err}" : " (HTTP {$code})") . '.');
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Não foi possível finalizar o cache baixado do TSE.');
        }
    }

    private function situacaoEleitaOuUtil(array $row): bool
    {
        $sit = mb_strtoupper((string)($row['DS_SIT_TOT_TURNO'] ?? ''), 'UTF-8');
        if ($sit === '' || $sit === '#NULO#') {
            return true;
        }
        return str_starts_with($sit, 'ELEITO');
    }

    private function cargoBate(string $cargo, string $dsCargo): bool
    {
        $ds = $this->normalizar($dsCargo);
        return match ($cargo) {
            'vereador' => $ds === 'vereador',
            'deputado_estadual' => $ds === 'deputado estadual' || $ds === 'deputado distrital',
            'senador' => $ds === 'senador',
            default => false,
        };
    }

    private function normalizarCargo(string $cargo): string
    {
        $cargo = $this->normalizar($cargo);
        return match ($cargo) {
            'vereador' => 'vereador',
            'deputado estadual', 'deputado_estadual', 'estadual', 'deputado distrital' => 'deputado_estadual',
            'senador' => 'senador',
            default => throw new \InvalidArgumentException('Cargo TSE inválido.'),
        };
    }

    private function cargoLabel(string $cargo): string
    {
        return match ($cargo) {
            'vereador' => 'Vereador(a)',
            'deputado_estadual' => 'Deputado(a) Estadual/Distrital',
            'senador' => 'Senador(a) eleito(a)',
            default => 'Parlamentar',
        };
    }

    private function despesasVazias(string $fonte): array
    {
        return [
            'ano' => (int)date('Y'),
            'total' => 0,
            'qtd' => 0,
            'paginas_lidas' => 0,
            'completo' => false,
            'fonte' => $fonte,
            'por_tipo' => [],
            'por_fornecedor' => [],
            'maiores_lancamentos' => [],
            'alertas_fornecedores' => [],
        ];
    }

    private function toUtf8(string $value): string
    {
        if (mb_detect_encoding($value, 'UTF-8', true)) {
            return $value;
        }
        return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1, Windows-1252, UTF-8');
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($ascii !== false) {
            $texto = $ascii;
        }
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $texto) ?: '');
    }
}
