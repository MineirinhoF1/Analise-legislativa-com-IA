<?php
/** GET: extrai texto público de um link de lei, proposição, página oficial ou PDF. */
require __DIR__ . '/../src/bootstrap.php';

use App\UrlExtractor;

try {
    requireCsrf();
    $url = trim($_GET['url'] ?? '');
    if ($url === '') {
        jsonResponse(['ok' => false, 'erro' => 'Informe o link público para extração.'], 422);
    }

    $dados = (new UrlExtractor())->extrair($url);
    jsonResponse(['ok' => true, 'dados' => $dados]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'erro' => $e->getMessage()], 500);
}
