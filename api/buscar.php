<?php
/** GET: busca proposição na Câmara por tipo/número/ano. */
require __DIR__ . '/../src/bootstrap.php';

use App\CamaraClient;
use App\PdfText;

try {
    $tipo   = strtoupper(trim($_GET['tipo'] ?? 'PL'));
    $numero = (int)($_GET['numero'] ?? 0);
    $ano    = (int)($_GET['ano'] ?? 0);

    if ($numero <= 0 || $ano <= 0) {
        jsonResponse(['ok' => false, 'erro' => 'Informe número e ano válidos.'], 422);
    }

    $dados = (new CamaraClient())->buscar($tipo, $numero, $ano);

    if (!empty($dados['inteiro_teor'])) {
        try {
            $textoPdf = (new PdfText())->fromUrl($dados['inteiro_teor'], 20);
            if (mb_strlen(trim($textoPdf)) >= 30) {
                $dados['texto_inteiro_teor'] = $textoPdf;
                $dados['inteiro_teor_extraido'] = true;
                $dados['inteiro_teor_chars'] = mb_strlen($textoPdf);
            } else {
                $dados['inteiro_teor_extraido'] = false;
                $dados['inteiro_teor_erro'] = 'PDF sem texto selecionável.';
            }
        } catch (\Throwable $e) {
            $dados['inteiro_teor_extraido'] = false;
            $dados['inteiro_teor_erro'] = $e->getMessage();
        }
    }

    $dados['texto_base'] = CamaraClient::montarTexto($dados);

    jsonResponse(['ok' => true, 'dados' => $dados]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'erro' => $e->getMessage()], 500);
}
