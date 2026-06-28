<?php
/**
 * Extrai texto de uma URL publica (HTML, texto puro ou PDF).
 */
namespace App;

class UrlExtractor
{
    private const MAX_CHARS = 40000;

    /** Retorna ['titulo','texto','fonte','url']. */
    public function extrair(string $url): array
    {
        $url = trim($url);
        UrlGuard::assertPublicHttpUrl($url);

        // Intercepta e trata links do TSE DivulgaCandContas (SPA/Hash)
        if (str_contains($url, 'divulgacandcontas.tse.jus.br') && preg_match('#/candidato/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)#', $url, $matches)) {
            $codigoEleicao = $matches[3];
            $sqCandidato = $matches[4];
            $ano = $matches[5];
            $codigoMunicipio = $matches[6];

            $apiUrl = "https://divulgacandcontas.tse.jus.br/divulga/rest/v1/candidatura/buscar/{$ano}/{$codigoMunicipio}/{$codigoEleicao}/candidato/{$sqCandidato}";
            return $this->extrairTseCandidato($apiUrl, $url);
        }

        [$body, $contentType, $effectiveUrl] = $this->fetch($url);

        $ehPdf = stripos($contentType, 'pdf') !== false
              || preg_match('#\.pdf($|\?)#i', $effectiveUrl)
              || str_starts_with($body, '%PDF');

        if ($ehPdf) {
            $texto = (new PdfText())->fromString($body);
            if (mb_strlen(trim($texto)) < 30) {
                throw new \RuntimeException(
                    'O PDF parece ser digitalizado (imagem) e nao tem texto selecionavel. Copie o conteudo e cole na aba "Colar texto".'
                );
            }
            return [
                'titulo' => $this->dominio($effectiveUrl) . ' (PDF)',
                'texto'  => $this->limitar($texto),
                'fonte'  => $this->dominio($effectiveUrl),
                'url'    => $effectiveUrl,
            ];
        }

        if (stripos($contentType, 'html') === false && $body !== '' && $body[0] !== '<') {
            return [
                'titulo' => $this->dominio($effectiveUrl),
                'texto'  => $this->limitar($this->normalizar($body)),
                'fonte'  => $this->dominio($effectiveUrl),
                'url'    => $effectiveUrl,
            ];
        }

        [$titulo, $texto] = $this->extrairHtml($body);
        if (mb_strlen($texto) < 30) {
            throw new \RuntimeException('Nao foi possivel extrair texto legivel desta pagina. Tente colar o texto manualmente.');
        }

        return [
            'titulo' => $titulo ?: $this->dominio($effectiveUrl),
            'texto'  => $this->limitar($texto),
            'fonte'  => $this->dominio($effectiveUrl),
            'url'    => $effectiveUrl,
        ];
    }

    /** Faz download e devolve [corpo, content-type, url-final]. */
    private function fetch(string $url): array
    {
        UrlGuard::assertPublicHttpUrl($url);

        $current = $url;
        for ($i = 0; $i <= 5; $i++) {
            UrlGuard::assertPublicHttpUrl($current);

            $curlOptions = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HEADER         => true,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_MAXFILESIZE    => 50 * 1024 * 1024, // evita downloads gigantes
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ResumoTransparente/1.0)',
                CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml,*/*'],
            ];
            $resolve = UrlGuard::curlResolve($current);
            if ($resolve) {
                $curlOptions[CURLOPT_RESOLVE] = $resolve;
            }

            $ch = curl_init($current);
            curl_setopt_array($ch, $curlOptions);
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                throw new \RuntimeException("Falha ao acessar o link: {$err}");
            }

            $headers = substr((string)$raw, 0, $headerSize);
            $body = substr((string)$raw, $headerSize);

            if ($code >= 300 && $code < 400 && preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
                $current = $this->resolveUrl($current, trim($m[1]));
                continue;
            }

            if ($code >= 400) {
                throw new \RuntimeException("O servidor retornou HTTP {$code} ao acessar o link.");
            }

            return [$body, $type, $current];
        }

        throw new \RuntimeException('Redirecionamentos demais ao acessar o link.');
    }

    /** Extrai titulo e texto principal do HTML, removendo ruido. */
    private function extrairHtml(string $html): array
    {
        if (!mb_detect_encoding($html, 'UTF-8', true)) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1, Windows-1252, UTF-8');
        }

        $titulo = '';
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $titulo = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//script|//style|//nav|//header|//footer|//noscript|//form|//svg') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $texto = $body ? $body->textContent : $dom->textContent;

        return [$titulo, $this->normalizar($texto)];
    }

    private function normalizar(string $t): string
    {
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/[ \t]+/u', ' ', $t) ?? $t;
        $t = preg_replace('/\n\s*\n\s*\n+/u', "\n\n", $t) ?? $t;
        return trim($t);
    }

    private function limitar(string $t): string
    {
        return mb_strlen($t) > self::MAX_CHARS
            ? mb_substr($t, 0, self::MAX_CHARS) . "\n\n[... texto truncado ...]"
            : $t;
    }

    private function dominio(string $url): string
    {
        return parse_url($url, PHP_URL_HOST) ?: $url;
    }

    private function resolveUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$port}{$location}";
        }

        $path = $parts['path'] ?? '/';
        $dir = preg_replace('#/[^/]*$#', '/', $path) ?? '/';
        return "{$scheme}://{$host}{$port}{$dir}{$location}";
    }

    private function extrairTseCandidato(string $apiUrl, string $originalUrl): array
    {
        UrlGuard::assertPublicHttpUrl($apiUrl);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ResumoTransparente/1.0)',
        ];
        $resolve = UrlGuard::curlResolve($apiUrl);
        if ($resolve) {
            $curlOptions[CURLOPT_RESOLVE] = $resolve;
        }

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, $curlOptions);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            throw new \RuntimeException("Erro ao consultar a API do TSE (HTTP {$code}).");
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new \RuntimeException("Resposta inválida da API do TSE.");
        }

        $l = [];
        $l[] = "NOME COMPLETO: " . ($json['nomeCompleto'] ?? '');
        $l[] = "NOME DE URNA: " . ($json['nomeUrna'] ?? '');
        $l[] = "CARGO: " . ($json['cargo']['nome'] ?? '');
        $l[] = "PARTIDO / COLIGAÇÃO: " . ($json['partido']['sigla'] ?? '') . " - " . ($json['partido']['nome'] ?? '') . " / " . ($json['coligacao']['nome'] ?? '');
        $l[] = "SITUAÇÃO DA CANDIDATURA: " . ($json['descricaoSituacaoCandidatura'] ?? '');
        $l[] = "ESTADO: " . ($json['uf'] ?? '');
        $l[] = "MUNICÍPIO: " . ($json['localidade'] ?? '');
        $l[] = "DATA DE NASCIMENTO: " . ($json['dataNascimento'] ?? '');
        $l[] = "OCUPAÇÃO: " . ($json['ocupacao'] ?? '');
        $l[] = "ESCOLARIDADE: " . ($json['grauInstrucao'] ?? '');
        $l[] = "GÊNERO: " . ($json['descricaoSexo'] ?? '');
        $l[] = "ETNIA / RAÇA: " . ($json['descricaoCorRaca'] ?? '');

        $bens = $json['bens'] ?? [];
        if ($bens) {
            $l[] = "";
            $l[] = "DECLARAÇÃO DE BENS:";
            $totalBens = 0.0;
            foreach ($bens as $b) {
                $valor = (float)($b['valor'] ?? 0);
                $totalBens += $valor;
                $l[] = "  - " . ($b['descricaoDeTipo'] ?? 'Outros') . ": " . ($b['descricao'] ?? '') . " · R$ " . number_format($valor, 2, ',', '.');
            }
            $l[] = "TOTAL DE BENS DECLARADOS: R$ " . number_format($totalBens, 2, ',', '.');
        }

        return [
            'titulo' => ($json['nomeUrna'] ?? '') . ' (' . ($json['partido']['sigla'] ?? '') . ')',
            'texto'  => implode("\n", $l),
            'fonte'  => 'Tribunal Superior Eleitoral (TSE)',
            'url'    => $originalUrl,
        ];
    }
}
