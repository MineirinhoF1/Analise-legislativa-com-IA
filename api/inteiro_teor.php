<?php
/** GET: baixa e extrai o texto do PDF do inteiro teor de uma proposição. */
require __DIR__ . '/../src/bootstrap.php';

use App\UrlGuard;
use App\PdfText;

try {
    requireCsrf();
    $url = trim($_GET['url'] ?? '');
    if ($url === '') {
        jsonResponse(['ok' => false, 'erro' => 'URL do inteiro teor inválida.'], 422);
    }
    UrlGuard::assertPublicHttpUrl($url);

    $texto = (new PdfText())->fromUrl($url, 180);
    if (mb_strlen(trim($texto)) < 30) {
        jsonResponse(['ok' => false, 'erro' => 'O PDF parece digitalizado (sem texto selecionável).'], 422);
    }
    jsonResponse(['ok' => true, 'texto' => $texto, 'chars' => mb_strlen($texto)]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'erro' => $e->getMessage()], 500);
}
