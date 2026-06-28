<?php
/**
 * Validação de URLs externas para reduzir risco de SSRF.
 */
namespace App;

class UrlGuard
{
    /** Valida URL http(s), bloqueando hosts locais, privados e reservados. */
    public static function assertPublicHttpUrl(string $url): void
    {
        self::publicEndpoint($url);
    }

    /** Retorna uma entrada CURLOPT_RESOLVE para fixar o IP validado no cURL. */
    public static function curlResolve(string $url): array
    {
        $endpoint = self::publicEndpoint($url);
        if (filter_var($endpoint['host'], FILTER_VALIDATE_IP)) {
            return [];
        }

        $ip = $endpoint['ips'][0] ?? '';
        if ($ip === '') {
            return [];
        }

        return [$endpoint['host'] . ':' . $endpoint['port'] . ':' . $ip];
    }

    private static function publicEndpoint(string $url): array
    {
        if (strlen($url) > 2048) {
            throw new \RuntimeException('URL longa demais para extração.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = (string)($parts['host'] ?? '');
        $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \RuntimeException('Informe uma URL válida começando com http:// ou https://');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \RuntimeException('URLs com usuário ou senha não são permitidas.');
        }
        if (!in_array($port, [80, 443], true)) {
            throw new \RuntimeException('A extração permite apenas portas HTTP/HTTPS padrão.');
        }

        $host = trim($host, "[] \t\n\r\0\x0B.");
        $hostLower = strtolower($host);
        if ($host === '' || $hostLower === 'localhost' || str_ends_with($hostLower, '.localhost')) {
            throw new \RuntimeException('URLs locais não são permitidas para extração.');
        }

        $ips = self::resolveIps($host);
        if (!$ips) {
            throw new \RuntimeException('Não foi possível resolver o domínio informado.');
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException('URLs para redes locais, privadas ou reservadas não são permitidas.');
            }
        }

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'ips' => $ips,
        ];
    }

    private static function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = gethostbynamel($host) ?: [];
        if (function_exists('dns_get_record')) {
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $rec) {
                if (!empty($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }
        return array_values(array_unique($ips));
    }
}
