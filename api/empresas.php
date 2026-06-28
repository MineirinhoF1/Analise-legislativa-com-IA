<?php
/** GET: busca fornecedores nas despesas da cota parlamentar. */
require __DIR__ . '/../src/bootstrap.php';

use App\ParlamentarClient;
use App\ComprasGovClient;
use App\PortalTransparenciaClient;
use App\Settings;

try {
    set_time_limit(240);
    ini_set('display_errors', '0');
    ob_start();

    $termo = trim($_GET['termo'] ?? '');
    $anoRaw = trim((string)($_GET['ano'] ?? ''));
    $ano = $anoRaw !== '' ? (int)$anoRaw : (int)date('Y');
    $limite = (int)($_GET['limite'] ?? 520);
    $paginas = (int)($_GET['paginas'] ?? 1);
    $deputado = (int)($_GET['deputado'] ?? 0);
    $fallback = ($_GET['fallback'] ?? '1') !== '0';

    $settings = new Settings();
    $client = new ParlamentarClient();
    $anos = [$ano];
    if ($fallback) {
        $anos[] = $ano - 1;
        $anos[] = $ano - 2;
    }
    $anos = array_values(array_unique(array_filter($anos, fn($a) => $a >= 2019 && $a <= ((int)date('Y') + 1))));

    $dados = null;
    $tentativas = [];
    foreach ($anos as $anoBusca) {
        $atual = $client->buscarFornecedor($termo, $anoBusca, $limite, $paginas, $deputado);
        $tentativas[] = [
            'ano' => $anoBusca,
            'parlamentares' => $atual['parlamentares_count'] ?? 0,
            'lancamentos' => $atual['qtd'] ?? 0,
        ];
        $dados = $atual;
        if (($atual['parlamentares_count'] ?? 0) > 0) {
            break;
        }
    }
    $dados['anos_verificados'] = $tentativas;
    $cnpjContexto = cnpjContextoEmpresa($termo, $dados);
    if (($cnpjContexto['cnpj'] ?? '') !== '') {
        $dados['cnpj_contexto'] = $cnpjContexto;
        $portal = new PortalTransparenciaClient($settings->portalToken());
        $dados['portal_transparencia_cnpj'] = $portal->contextoCnpj($cnpjContexto['cnpj']);
        $dados['compras_gov_cnpj'] = (new ComprasGovClient())->contextoCnpj($cnpjContexto['cnpj']);
    }
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    jsonResponse(['ok' => true, 'dados' => $dados]);
} catch (\InvalidArgumentException $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    jsonResponse(['ok' => false, 'erro' => $e->getMessage()], 422);
} catch (\Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    jsonResponse(['ok' => false, 'erro' => $e->getMessage()], 500);
}

function cnpjContextoEmpresa(string $termo, array $dados): array
{
    $doc = preg_replace('/\D+/', '', $termo) ?: '';
    if (strlen($doc) === 14) {
        return ['cnpj' => $doc, 'origem' => 'termo_digitado'];
    }
    foreach (($dados['fornecedores'] ?? []) as $f) {
        $doc = preg_replace('/\D+/', '', (string)($f['documento'] ?? '')) ?: '';
        if (strlen($doc) === 14) {
            return [
                'cnpj' => $doc,
                'origem' => 'fornecedor_encontrado',
                'fornecedor' => (string)($f['fornecedor'] ?? ''),
            ];
        }
    }
    foreach (($dados['resultados'] ?? []) as $r) {
        foreach (($r['documentos'] ?? []) as $d) {
            $doc = preg_replace('/\D+/', '', (string)$d) ?: '';
            if (strlen($doc) === 14) {
                return [
                    'cnpj' => $doc,
                    'origem' => 'documento_resultado_camara',
                    'parlamentar' => (string)($r['nome'] ?? ''),
                ];
            }
        }
    }
    return ['cnpj' => '', 'origem' => 'nao_encontrado'];
}
