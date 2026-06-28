<?php
/**
 * GET                 -> lista resumida do histórico
 * GET ?id=XXX         -> registro completo (para re-render)
 * GET ?acao=excluir&id=XXX -> remove um registro
 */
require __DIR__ . '/../src/bootstrap.php';

use App\History;

try {
    $history = new History();
    $acao    = $_GET['acao'] ?? '';
    $id      = trim($_GET['id'] ?? '');

    if ($acao === 'excluir' && $id !== '') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'erro' => 'Exclusão exige POST.'], 405);
        }
        requireCsrf();
        $history->excluir($id);
        jsonResponse(['ok' => true]);
    }

    if ($id !== '') {
        $rec = $history->get($id);
        if (!$rec) jsonResponse(['ok' => false, 'erro' => 'Análise não encontrada.'], 404);
        jsonResponse(['ok' => true, 'registro' => $rec]);
    }

    jsonResponse(['ok' => true, 'itens' => $history->listar()]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'erro' => $e->getMessage()], 500);
}
