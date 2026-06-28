<?php
/** POST: recebe texto da lei + contexto e retorna a análise da IA. */
require __DIR__ . '/../src/bootstrap.php';

use App\Settings;
use App\AiClient;
use App\AnaliseService;
use App\History;

try {
    requireCsrf();
    $in       = jsonInput();
    $texto    = trim($in['texto'] ?? '');
    $contexto = is_array($in['contexto'] ?? null) ? $in['contexto'] : [];
    $forcar   = !empty($in['atualizar']);

    if (mb_strlen($texto) < 20) {
        jsonResponse(['ok' => false, 'erro' => 'Texto muito curto. Cole a lei/PEC/PL ou busque por número.'], 422);
    }

    $settings = new Settings();
    $prov     = $settings->activeProvider();
    $aiKey    = $prov['name'] . ':' . $prov['model'];

    // Cache: consulta idêntica reaproveita o resultado salvo (economiza tokens)
    $history = new History();
    $key     = 'lei:' . AnaliseService::VERSION_LEI . ':' . $aiKey . ':' . sha1(History::normalizar($texto));
    if (!$forcar && ($rec = $history->find($key))) {
        jsonResponse(['ok' => true, 'cache' => true, 'id' => $rec['id'], 'provedor' => $rec['provedor'],
            'modelo' => $rec['modelo'], 'gerado_em' => $rec['gerado_em'], 'analise' => $rec['payload']]);
    }

    if ($prov['api_key'] === '') {
        jsonResponse([
            'ok'   => false,
            'erro' => "Chave de API não configurada para '{$prov['name']}'. Abra as Configurações e cadastre a chave.",
        ], 400);
    }

    $client  = new AiClient($prov['name'], $prov['api_key'], $prov['model'], $prov['max_tokens']);
    $service = new AnaliseService($client);
    $analise = $service->analisar($texto, $contexto);

    $rec = $history->save($key, 'lei', $analise['titulo'] ?? 'Análise de lei', $analise,
        ['provedor' => $prov['name'], 'modelo' => $prov['model']], ['texto' => $texto, 'contexto' => $contexto]);

    jsonResponse(['ok' => true, 'cache' => false, 'id' => $rec['id'], 'provedor' => $prov['name'],
        'modelo' => $prov['model'], 'gerado_em' => $rec['gerado_em'], 'analise' => $analise]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'erro' => $e->getMessage()], 500);
}
