<?php
/** POST: recebe o perfil de um parlamentar e retorna a análise da IA. */
require __DIR__ . '/../src/bootstrap.php';

use App\Settings;
use App\AiClient;
use App\AnaliseService;
use App\History;

try {
    requireCsrf();
    $in     = jsonInput();
    $texto  = trim($in['texto'] ?? '');
    $perfil = is_array($in['perfil'] ?? null) ? $in['perfil'] : [];
    $forcar = !empty($in['atualizar']);

    if (mb_strlen($texto) < 20) {
        jsonResponse(['ok' => false, 'erro' => 'Dados do parlamentar insuficientes para análise.'], 422);
    }

    $settings = new Settings();
    $prov     = $settings->activeProvider();
    $aiKey    = $prov['name'] . ':' . $prov['model'];

    $history = new History();
    $key     = 'parlamentar:' . AnaliseService::VERSION_PARLAMENTAR . ':' . $aiKey . ':' . ($perfil['id'] ?? 'sem-id') . ':' . sha1(History::normalizar($texto));
    if (!$forcar && ($rec = $history->find($key))) {
        jsonResponse(['ok' => true, 'cache' => true, 'id' => $rec['id'], 'provedor' => $rec['provedor'],
            'modelo' => $rec['modelo'], 'gerado_em' => $rec['gerado_em'], 'analise' => $rec['payload'], 'perfil' => $rec['extra']]);
    }

    if ($prov['api_key'] === '') {
        jsonResponse([
            'ok'   => false,
            'erro' => "Chave de API não configurada para '{$prov['name']}'. Abra as Configurações e cadastre a chave.",
        ], 400);
    }

    $client  = new AiClient($prov['name'], $prov['api_key'], $prov['model'], $prov['max_tokens']);
    $service = new AnaliseService($client);
    $analise = $service->analisarParlamentar($texto, $perfil);

    $rec = $history->save($key, 'parlamentar', $analise['nome'] ?? ($perfil['nome'] ?? 'Parlamentar'),
        $analise, ['provedor' => $prov['name'], 'modelo' => $prov['model']], $perfil);

    jsonResponse(['ok' => true, 'cache' => false, 'id' => $rec['id'], 'provedor' => $prov['name'],
        'modelo' => $prov['model'], 'gerado_em' => $rec['gerado_em'], 'analise' => $analise]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'erro' => $e->getMessage()], 500);
}
