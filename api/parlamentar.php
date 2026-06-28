<?php
/** GET: busca deputados por nome/partido, lista partidos ou monta perfil. */
require __DIR__ . '/../src/bootstrap.php';

use App\ParlamentarClient;
use App\SenadoParlamentarClient;
use App\TseEleitosClient;
use App\PortalTransparenciaClient;
use App\Settings;

try {
    $acao   = $_GET['acao'] ?? 'buscar';
    $fonte  = trim((string)($_GET['fonte'] ?? 'camara'));
    $portal = new PortalTransparenciaClient((new Settings())->portalToken());
    $client = new ParlamentarClient($portal);

    if ($acao === 'perfil') {
        $rawId = trim((string)($_GET['id'] ?? ''));
        if ($rawId === '') {
            jsonResponse(['ok' => false, 'erro' => 'ID do parlamentar inválido.'], 422);
        }

        if (str_starts_with($rawId, 'senado:')) {
            $perfil = (new SenadoParlamentarClient())->perfil(substr($rawId, 7));
            $perfil['texto_base'] = SenadoParlamentarClient::montarTexto($perfil);
            jsonResponse(['ok' => true, 'perfil' => $perfil]);
        }

        if (str_starts_with($rawId, 'tse:')) {
            $perfil = (new TseEleitosClient())->perfil($rawId);
            jsonResponse(['ok' => true, 'perfil' => $perfil]);
        }

        $id = (int)$rawId;
        if ($id <= 0) {
            jsonResponse(['ok' => false, 'erro' => 'ID do parlamentar inválido.'], 422);
        }
        $perfil = $client->perfil($id);
        $perfil['origem'] = 'camara';
        $perfil['texto_base'] = ParlamentarClient::montarTexto($perfil);
        jsonResponse(['ok' => true, 'perfil' => $perfil]);
    }

    if ($acao === 'partidos') {
        jsonResponse(['ok' => true, 'partidos' => $client->partidos()]);
    }

    $nome = trim($_GET['nome'] ?? '');
    $partido = trim($_GET['partido'] ?? '');
    $uf = strtoupper(trim((string)($_GET['uf'] ?? '')));
    $municipio = trim((string)($_GET['municipio'] ?? ''));
    $cargo = trim((string)($_GET['cargo'] ?? ''));
    $ano = (int)($_GET['ano'] ?? 0);

    if ($partido !== '' && !preg_match('/^[\pL0-9-]{1,20}$/u', $partido)) {
        jsonResponse(['ok' => false, 'erro' => 'Sigla do partido inválida.'], 422);
    }
    if ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf)) {
        jsonResponse(['ok' => false, 'erro' => 'UF inválida. Use a sigla com 2 letras.'], 422);
    }
    if ($nome === '' && $partido === '') {
        if (($fonte === 'tse' && ($uf !== '' || $municipio !== '')) || ($fonte === 'senado' && $uf !== '')) {
            // permite buscar TSE por UF/município e Senado por UF sem nome
        } else {
            jsonResponse(['ok' => false, 'erro' => 'Digite ao menos 2 letras do nome ou escolha um partido.'], 422);
        }
    }
    if ($nome !== '' && mb_strlen($nome) < 2) {
        jsonResponse(['ok' => false, 'erro' => 'Digite ao menos 2 letras do nome.'], 422);
    }

    if ($fonte === 'senado') {
        $lista = (new SenadoParlamentarClient())->buscar($nome, $partido, $uf);
        jsonResponse(['ok' => true, 'resultados' => $lista, 'fonte' => 'senado']);
    }

    if ($fonte === 'tse') {
        if ($cargo === '') {
            jsonResponse(['ok' => false, 'erro' => 'Escolha o cargo para consulta no TSE.'], 422);
        }
        $lista = (new TseEleitosClient())->buscar($cargo, $nome, $partido, $uf, $municipio, $ano);
        jsonResponse(['ok' => true, 'resultados' => $lista, 'fonte' => 'tse']);
    }

    $lista = $client->buscarPorNome($nome, $partido);
    foreach ($lista as &$item) {
        $item['fonte_tipo'] = 'camara';
        $item['cargo'] = 'Deputado(a) Federal';
    }
    jsonResponse(['ok' => true, 'resultados' => $lista]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'erro' => $e->getMessage()], 500);
}
