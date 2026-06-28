<?php
/** GET: retorna config mascarada. POST: salva config validada. */
require __DIR__ . '/../src/bootstrap.php';

use App\Settings;

$settings = new Settings();

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') {
        requireCsrf();

        $in = jsonInput();
        $modelOptions = Settings::modelOptions();
        $allowedProviders = array_keys($modelOptions);

        $provider = (string)($in['provider'] ?? '');
        $clean = [
            'provider' => in_array($provider, $allowedProviders, true) ? $provider : 'anthropic',
            'providers' => [],
            'max_tokens' => max(2048, min(24000, (int)($in['max_tokens'] ?? 8192))),
        ];

        $incomingProviders = is_array($in['providers'] ?? null) ? $in['providers'] : [];
        foreach ($allowedProviders as $name) {
            $prov = is_array($incomingProviders[$name] ?? null) ? $incomingProviders[$name] : [];

            $model = trim((string)($prov['model'] ?? ''));
            if ($model !== '' && in_array($model, $modelOptions[$name], true)) {
                $clean['providers'][$name]['model'] = mb_substr($model, 0, 120);
            }

            // Campo em branco preserva a chave ja cadastrada.
            $apiKey = trim((string)($prov['api_key'] ?? ''));
            if ($apiKey !== '') {
                $clean['providers'][$name]['api_key'] = $apiKey;
            }
        }

        $portalToken = trim((string)($in['portal_transparencia_token'] ?? ''));
        if ($portalToken !== '') {
            $clean['portal_transparencia_token'] = $portalToken;
        }

        $settings->save($clean);
        jsonResponse(['ok' => true, 'config' => $settings->publicView()]);
    }

    jsonResponse(['ok' => true, 'config' => $settings->publicView()]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'erro' => $e->getMessage()], 500);
}
