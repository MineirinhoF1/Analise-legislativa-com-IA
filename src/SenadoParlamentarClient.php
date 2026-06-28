<?php
/**
 * Dados Abertos do Senado para senadores em exercicio.
 */
namespace App;

class SenadoParlamentarClient
{
    private const LISTA_ATUAL = 'https://legis.senado.leg.br/dadosabertos/senador/lista/atual';

    public function buscar(string $nome = '', string $partido = '', string $uf = ''): array
    {
        $nomeNorm = $this->normalizar($nome);
        $partido = mb_strtoupper(trim($partido), 'UTF-8');
        $uf = mb_strtoupper(trim($uf), 'UTF-8');

        $out = [];
        foreach ($this->senadoresAtuais() as $s) {
            if ($nomeNorm !== '' && !str_contains($this->normalizar($s['nome']), $nomeNorm)) {
                continue;
            }
            if ($partido !== '' && mb_strtoupper($s['partido'], 'UTF-8') !== $partido) {
                continue;
            }
            if ($uf !== '' && mb_strtoupper($s['uf'], 'UTF-8') !== $uf) {
                continue;
            }
            $out[] = [
                'id' => 'senado:' . $s['codigo'],
                'fonte_tipo' => 'senado',
                'nome' => $s['nome'],
                'partido' => $s['partido'],
                'uf' => $s['uf'],
                'foto' => $this->normalizarUrl($s['foto']),
                'email' => $s['email'],
                'cargo' => 'Senador(a)',
            ];
        }

        usort($out, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));
        return array_slice($out, 0, 80);
    }

    public function perfil(string $codigo): array
    {
        $codigo = preg_replace('/\D+/', '', $codigo) ?: '';
        foreach ($this->senadoresAtuais() as $s) {
            if ($s['codigo'] !== $codigo) {
                continue;
            }

            $comissoesData = $this->comissoes($codigo);
            $proposicoes = $this->proposicoes($codigo);
            $relatorias = $this->relatorias($codigo);
            $votos = $this->votosRecentes($codigo);

            return [
                'id' => 'senado:' . $s['codigo'],
                'origem' => 'senado',
                'nome' => $s['nome'],
                'nome_civil' => $s['nome_completo'],
                'partido' => $s['partido'],
                'uf' => $s['uf'],
                'cargo' => 'Senador(a)',
                'situacao' => 'Em exercício',
                'condicao' => $s['mandato'],
                'foto' => $this->normalizarUrl($s['foto']),
                'email' => $s['email'],
                'escolaridade' => '',
                'nascimento' => '',
                'despesas' => $this->despesasVazias('Senado Federal'),
                'proposicoes' => $proposicoes,
                'prop_resumo' => ['total' => count($proposicoes), 'aprovada' => 0, 'arquivada' => 0, 'tramitando' => count($proposicoes)],
                'relatorias' => $relatorias,
                'frentes' => [],
                'comissoes' => $comissoesData['comissoes'],
                'comissoes_detalhadas' => $comissoesData['comissoes_detalhadas'],
                'votos_recentes' => $votos,
                'emendas' => ['disponivel' => false],
                'links_externos' => array_values(array_filter([
                    $s['pagina'] ? ['rotulo' => 'Página oficial no Senado', 'url' => $this->normalizarUrl($s['pagina']), 'desc' => 'Perfil oficial do senador no Senado Federal.'] : null,
                    ['rotulo' => 'Dados Abertos do Senado', 'url' => self::LISTA_ATUAL, 'desc' => 'Lista oficial de senadores em exercício.'],
                ])),
                'fonte' => 'Senado Federal (Dados Abertos)',
            ];
        }
        throw new \RuntimeException('Senador não encontrado na lista atual do Senado.');
    }

    public static function montarTexto(array $p): string
    {
        $l = [];
        $l[] = "PARLAMENTAR: {$p['nome']} ({$p['partido']}-{$p['uf']})";
        if (!empty($p['nome_civil']) && $p['nome_civil'] !== $p['nome']) {
            $l[] = "Nome civil: {$p['nome_civil']}";
        }
        $l[] = "Cargo: {$p['cargo']} · Situação: {$p['situacao']} · {$p['condicao']}";
        $l[] = "";
        $l[] = "ESCOPO DOS DADOS: atuação legislativa e perfil do senador obtido nos Dados Abertos do Senado.";

        if (!empty($p['comissoes_detalhadas'])) {
            $l[] = "";
            $l[] = "COMISSÕES QUE INTEGRA OU INTEGROU:";
            foreach ($p['comissoes_detalhadas'] as $com) {
                $l[] = "- {$com['sigla']} ({$com['nome']}) - Função: {$com['titulo']} - Período: {$com['periodo']}";
            }
        }

        if (!empty($p['proposicoes'])) {
            $l[] = "";
            $l[] = "PROPOSIÇÕES DE AUTORIA (AMOSTRA):";
            foreach (array_slice($p['proposicoes'], 0, 15) as $pr) {
                $l[] = "- {$pr['tipo']} {$pr['numero']}/{$pr['ano']} [{$pr['situacao']}] - Ementa: {$pr['ementa']}";
            }
        }

        if (!empty($p['relatorias'])) {
            $l[] = "";
            $l[] = "RELATORIAS DE MATÉRIAS (AMOSTRA):";
            foreach (array_slice($p['relatorias'], 0, 15) as $rel) {
                $designacao = $rel['designacao'] ? " em {$rel['designacao']}" : "";
                $l[] = "- {$rel['materia']} na comissão {$rel['comissao']} (Designado {$rel['funcao']}{$designacao}) - Ementa: {$rel['ementa']}";
            }
        }

        if (!empty($p['votos_recentes'])) {
            $l[] = "";
            $l[] = "VOTOS NOMINAIS REGISTRADOS:";
            foreach (array_slice($p['votos_recentes'], 0, 15) as $v) {
                $l[] = "- Data: {$v['data']} - Matéria: {$v['proposicao']} - Voto: {$v['voto']} - Resumo da votação: {$v['resumo']}";
            }
        }

        $l[] = "";
        foreach ($p['links_externos'] ?? [] as $link) {
            $l[] = "FONTE OFICIAL: {$link['rotulo']} - {$link['url']}";
        }
        return implode("\n", $l);
    }

    private function comissoes(string $codigo): array
    {
        $comissoes = [];
        $comissoesDetalhadas = [];
        try {
            $url = "https://legis.senado.leg.br/dadosabertos/senador/{$codigo}/comissoes";
            $xml = $this->fetchXml($url);
            foreach ($xml->xpath('//Comissao') ?: [] as $node) {
                $idCom = $node->IdentificacaoComissao ?? null;
                if (!$idCom) continue;
                $sigla = trim((string)($idCom->SiglaComissao ?? ''));
                $nome = trim((string)($idCom->NomeComissao ?? ''));
                if ($sigla === '') continue;

                $comissoes[] = $sigla;
                $participacao = trim((string)($node->DescricaoParticipacao ?? 'Membro'));
                $inicio = trim((string)($node->DataInicio ?? ''));
                $fim = trim((string)($node->DataFim ?? ''));
                $periodo = $inicio;
                if ($fim !== '') {
                    $periodo .= " a {$fim}";
                } elseif ($inicio !== '') {
                    $periodo = "desde {$inicio}";
                }

                $comissoesDetalhadas[] = [
                    'sigla' => $sigla,
                    'nome' => $nome,
                    'titulo' => $participacao,
                    'periodo' => $periodo,
                ];
            }
        } catch (\Throwable) {
            // fail safe
        }
        return [
            'comissoes' => array_values(array_unique($comissoes)),
            'comissoes_detalhadas' => $comissoesDetalhadas,
        ];
    }

    private function proposicoes(string $codigo): array
    {
        $out = [];
        try {
            $url = "https://legis.senado.leg.br/dadosabertos/senador/{$codigo}/autorias";
            $xml = $this->fetchXml($url);
            foreach ($xml->xpath('//Autoria') ?: [] as $node) {
                $mat = $node->Materia ?? null;
                if (!$mat) continue;
                $cod = trim((string)($mat->Codigo ?? ''));
                $sigla = trim((string)($mat->Sigla ?? ''));
                $num = (int)($mat->Numero ?? 0);
                $ano = (int)($mat->Ano ?? 0);
                if ($sigla === '' || $num === 0) continue;

                $ementa = trim((string)($mat->Ementa ?? ''));
                $principal = trim((string)($node->IndicadorAutorPrincipal ?? 'Não')) === 'Sim';

                $out[] = [
                    'id' => $cod,
                    'tipo' => $sigla,
                    'numero' => $num,
                    'ano' => $ano,
                    'ementa' => $ementa,
                    'situacao' => $principal ? 'Autor Principal' : 'Coautor',
                    'url' => "https://www25.senado.leg.br/web/atividade/materias/-/materia/{$cod}",
                ];
            }
        } catch (\Throwable) {
            // fail safe
        }
        return $out;
    }

    private function relatorias(string $codigo): array
    {
        $out = [];
        try {
            $url = "https://legis.senado.leg.br/dadosabertos/senador/{$codigo}/relatorias";
            $xml = $this->fetchXml($url);
            foreach ($xml->xpath('//Relatoria') ?: [] as $node) {
                $mat = $node->Materia ?? null;
                if (!$mat) continue;
                $cod = trim((string)($mat->Codigo ?? ''));
                $sigla = trim((string)($mat->Sigla ?? ''));
                $num = (int)($mat->Numero ?? 0);
                $ano = (int)($mat->Ano ?? 0);
                if ($sigla === '' || $num === 0) continue;

                $ementa = trim((string)($mat->Ementa ?? ''));
                $com = $node->Comissao ?? null;
                $comissaoSigla = $com ? trim((string)($com->Sigla ?? '')) : '';

                $out[] = [
                    'materia' => "{$sigla} {$num}/{$ano}",
                    'funcao' => trim((string)($node->DescricaoTipoRelator ?? 'Relator')),
                    'comissao' => $comissaoSigla,
                    'designacao' => trim((string)($node->DataDesignacao ?? '')),
                    'ementa' => $ementa,
                ];
            }
        } catch (\Throwable) {
            // fail safe
        }
        return $out;
    }

    private function votosRecentes(string $codigo): array
    {
        $out = [];
        try {
            $url = "https://legis.senado.leg.br/dadosabertos/senador/{$codigo}/votacoes";
            $xml = $this->fetchXml($url);
            foreach ($xml->xpath('//Votacao') ?: [] as $node) {
                $mat = $node->Materia ?? null;
                if (!$mat) continue;
                $sigla = trim((string)($mat->Sigla ?? ''));
                $num = (int)($mat->Numero ?? 0);
                $ano = (int)($mat->Ano ?? 0);
                $codMat = trim((string)($mat->Codigo ?? ''));
                if ($sigla === '' || $num === 0) continue;

                $sessao = $node->SessaoPlenaria ?? null;
                $data = $sessao ? trim((string)($sessao->DataSessao ?? '')) : '';
                $voto = trim((string)($node->SiglaDescricaoVoto ?? '—'));
                $desc = trim((string)($node->DescricaoVotacao ?? ''));
                if ($desc === '') {
                    $desc = trim((string)($mat->Ementa ?? ''));
                }

                $out[] = [
                    'data' => $data,
                    'proposicao' => "{$sigla} {$num}/{$ano}",
                    'voto' => $voto,
                    'resumo' => $desc,
                    'url' => $codMat ? "https://www25.senado.leg.br/web/atividade/materias/-/materia/{$codMat}" : '',
                ];
            }
        } catch (\Throwable) {
            // fail safe
        }
        return $out;
    }

    private function senadoresAtuais(): array
    {
        $cache = \storagePath('senado/senadores-atuais.json');
        if (is_file($cache) && filemtime($cache) > time() - 21600) {
            $data = json_decode((string)file_get_contents($cache), true);
            if (is_array($data)) {
                return $data;
            }
        }

        $xml = $this->fetchXml(self::LISTA_ATUAL);
        $items = [];
        foreach ($xml->xpath('//Parlamentar') ?: [] as $node) {
            $id = $node->IdentificacaoParlamentar ?? null;
            if (!$id) {
                continue;
            }
            $codigo = trim((string)($id->CodigoParlamentar ?? ''));
            $nome = trim((string)($id->NomeParlamentar ?? ''));
            if ($codigo === '' || $nome === '') {
                continue;
            }
            $items[] = [
                'codigo' => $codigo,
                'nome' => $nome,
                'nome_completo' => trim((string)($id->NomeCompletoParlamentar ?? $nome)),
                'partido' => trim((string)($id->SiglaPartidoParlamentar ?? '')),
                'uf' => trim((string)($id->UfParlamentar ?? '')),
                'foto' => $this->normalizarUrl((string)($id->UrlFotoParlamentar ?? '')),
                'pagina' => $this->normalizarUrl((string)($id->UrlPaginaParlamentar ?? '')),
                'email' => trim((string)($id->EmailParlamentar ?? '')),
                'mandato' => $this->mandatoResumo($node),
            ];
        }

        $dir = dirname($cache);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($cache, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $items;
    }

    private function mandatoResumo(\SimpleXMLElement $node): string
    {
        $mandato = $node->Mandato ?? null;
        if (!$mandato) {
            return 'Mandato atual';
        }
        $inicio = trim((string)($mandato->PrimeiraLegislaturaDoMandato->DataInicio ?? ''));
        $fim = trim((string)($mandato->SegundaLegislaturaDoMandato->DataFim ?? ''));
        return trim('Mandato ' . ($inicio ?: '') . ($fim ? ' a ' . $fim : ''));
    }

    private function fetchXml(string $url): \SimpleXMLElement
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/xml,text/xml,*/*'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ResumoTransparente/1.0)',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            throw new \RuntimeException("Não foi possível consultar o Senado Federal" . ($err ? ": {$err}" : " (HTTP {$code})") . '.');
        }
        $xml = @simplexml_load_string((string)$body);
        if (!$xml) {
            throw new \RuntimeException('Resposta inválida do Senado Federal.');
        }
        return $xml;
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

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($ascii !== false) {
            $texto = $ascii;
        }
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $texto) ?: '');
    }

    private function normalizarUrl(string $url): string
    {
        $url = trim($url);
        if (str_starts_with($url, 'http://')) {
            return 'https://' . substr($url, 7);
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        return $url;
    }
}
