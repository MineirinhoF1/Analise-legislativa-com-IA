<?php
/**
 * Extrator de texto de PDF best-effort.
 * Usa pdftotext quando disponivel e cai para parser PHP simples.
 * Nao faz OCR; PDFs digitalizados tendem a retornar vazio.
 */
namespace App;

class PdfText
{
    private const MAX_CHARS = 60000;
    private const MAX_DOWNLOAD_BYTES = 160 * 1024 * 1024;
    private const PDFTOTEXT_TIMEOUT = 30;

    /** Baixa um PDF de uma URL publica e extrai o texto. */
    public function fromUrl(string $url, int $timeout = 60): string
    {
        UrlGuard::assertPublicHttpUrl($url);
        $timeout = max(5, min(240, $timeout));

        $current = $url;
        for ($i = 0; $i <= 5; $i++) {
            UrlGuard::assertPublicHttpUrl($current);

            $curlOptions = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HEADER         => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_MAXFILESIZE    => self::MAX_DOWNLOAD_BYTES,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ResumoTransparente/1.0)',
            ];
            $resolve = UrlGuard::curlResolve($current);
            if ($resolve) {
                $curlOptions[CURLOPT_RESOLVE] = $resolve;
            }

            $ch = curl_init($current);
            curl_setopt_array($ch, $curlOptions);
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                throw new \RuntimeException("Nao foi possivel baixar o PDF: {$err}");
            }
            if ($code >= 400) {
                throw new \RuntimeException("Nao foi possivel baixar o PDF (HTTP {$code}).");
            }

            $headers = substr((string)$raw, 0, $headerSize);
            $data = substr((string)$raw, $headerSize);
            if ($code >= 300 && $code < 400 && preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
                $current = $this->resolveUrl($current, trim($m[1]));
                continue;
            }

            return $this->fromString($data);
        }

        throw new \RuntimeException('Redirecionamentos demais ao baixar o PDF.');
    }

    /** Extrai texto de um PDF ja em memoria. */
    public function fromString(string $pdf): string
    {
        $texto = $this->fromStringWithPdftotext($pdf);
        if (mb_strlen(trim($texto)) >= 30) {
            return $this->limitar($texto);
        }

        $texto = '';
        if (preg_match_all('/stream(?:\r\n|\n|\r)(.*?)(?:\r\n|\n|\r)endstream/s', $pdf, $m)) {
            foreach ($m[1] as $stream) {
                $stream = trim($stream, "\r\n");
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }
                if ($decoded === false) {
                    $decoded = $stream;
                }
                $texto .= $this->extrairOperadores($decoded) . "\n";
            }
        }
        return $this->limitar($this->normalizar($texto));
    }

    /** Extrai texto dos operadores Tj e TJ. */
    private function extrairOperadores(string $content): string
    {
        $out = '';

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $arr)) {
            foreach ($arr[1] as $bloco) {
                if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $bloco, $parts)) {
                    foreach ($parts[0] as $p) {
                        $out .= $this->decodePdfString(substr($p, 1, -1));
                    }
                    $out .= ' ';
                }
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $arrHex)) {
            foreach ($arrHex[1] as $bloco) {
                if (preg_match_all('/<([0-9A-Fa-f\s]+)>/s', $bloco, $parts)) {
                    foreach ($parts[1] as $p) {
                        $out .= $this->decodeHexString($p);
                    }
                    $out .= ' ';
                }
            }
        }

        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj/s', $content, $tj)) {
            foreach ($tj[0] as $p) {
                $str = substr($p, 0, strrpos($p, ')'));
                $out .= $this->decodePdfString(substr($str, strpos($str, '(') + 1)) . ' ';
            }
        }

        if (preg_match_all('/<([0-9A-Fa-f\s]+)>\s*Tj/s', $content, $tjHex)) {
            foreach ($tjHex[1] as $p) {
                $out .= $this->decodeHexString($p) . ' ';
            }
        }

        return $out;
    }

    private function fromStringWithPdftotext(string $pdf): string
    {
        $bin = $this->pdftotextPath();
        if ($bin === null || !function_exists('proc_open')) {
            return '';
        }

        $input = tempnam(sys_get_temp_dir(), 'rt_pdf_in_');
        $output = tempnam(sys_get_temp_dir(), 'rt_pdf_out_');
        if ($input === false || $output === false) {
            if (is_string($input) && is_file($input)) @unlink($input);
            if (is_string($output) && is_file($output)) @unlink($output);
            return '';
        }

        @unlink($output);

        try {
            if (file_put_contents($input, $pdf) === false) {
                return '';
            }

            $cmd = [$bin, '-layout', '-nopgbrk', '-enc', 'UTF-8', $input, $output];
            $pipes = [];
            $proc = @proc_open($cmd, [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);
            if (!is_resource($proc)) {
                return '';
            }

            foreach ($pipes as $pipe) {
                stream_set_blocking($pipe, false);
            }

            $start = time();
            do {
                $status = proc_get_status($proc);
                if (!$status['running']) {
                    break;
                }
                if (time() - $start > self::PDFTOTEXT_TIMEOUT) {
                    proc_terminate($proc);
                    break;
                }
                usleep(100000);
            } while (true);

            foreach ($pipes as $pipe) {
                stream_get_contents($pipe);
                fclose($pipe);
            }
            proc_close($proc);

            if (!is_file($output)) {
                return '';
            }

            return $this->normalizar((string)file_get_contents($output));
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    private function pdftotextPath(): ?string
    {
        foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        return null;
    }

    /** Trata escapes de string PDF: \n \( \) \\ \ddd. */
    private function decodePdfString(string $s): string
    {
        $s = preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3})/', function ($m) {
            return match ($m[1]) {
                'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => '', 'f' => '',
                '(' => '(', ')' => ')', '\\' => '\\',
                default => chr(octdec($m[1])),
            };
        }, $s);
        if ($s === null || $s === '') {
            return '';
        }
        if (str_starts_with($s, "\xFE\xFF")) {
            return mb_convert_encoding(substr($s, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with($s, "\xFF\xFE")) {
            return mb_convert_encoding(substr($s, 2), 'UTF-8', 'UTF-16LE');
        }
        if (!mb_detect_encoding($s, 'UTF-8', true)) {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252, ISO-8859-1');
        }
        return $s;
    }

    private function decodeHexString(string $hex): string
    {
        $hex = preg_replace('/\s+/', '', $hex) ?? '';
        if ($hex === '') {
            return '';
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }
        $bytes = @hex2bin($hex);
        if ($bytes === false) {
            return '';
        }
        if (str_starts_with($bytes, "\xFE\xFF")) {
            return mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with($bytes, "\xFF\xFE")) {
            return mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16LE');
        }
        if (!mb_detect_encoding($bytes, 'UTF-8', true)) {
            $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252, ISO-8859-1');
        }
        return $bytes;
    }

    private function normalizar(string $t): string
    {
        if (!mb_detect_encoding($t, 'UTF-8', true)) {
            $t = mb_convert_encoding($t, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
        }
        $t = preg_replace('/[ \t]+/u', ' ', $t) ?? $t;
        $t = preg_replace('/\n\s*\n\s*\n+/u', "\n\n", $t) ?? $t;
        return trim($t);
    }

    private function limitar(string $texto): string
    {
        return mb_strlen($texto) > self::MAX_CHARS
            ? mb_substr($texto, 0, self::MAX_CHARS) . "\n\n[... texto truncado ...]"
            : $texto;
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
}
