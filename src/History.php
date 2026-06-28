<?php
/**
 * Histórico + cache de análises.
 * Cada análise é salva por uma CHAVE determinística (derivada da consulta).
 * Consultas idênticas reaproveitam o resultado salvo, evitando gastar tokens.
 * O usuário pode forçar atualização (regrava a mesma chave).
 */

namespace App;

class History
{
    private string $dir;
    private string $indexFile;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? \storagePath('analises');
        $this->indexFile = $this->dir . '/index.json';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        $this->migrarStorageAntigo();
    }

    /** Normaliza um texto para compor a chave de cache. */
    public static function normalizar(string $t): string
    {
        $t = mb_strtolower(trim($t), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $t) ?? $t;
    }

    /** Busca um registro pela chave de consulta. Retorna null se não houver. */
    public function find(string $key): ?array
    {
        return $this->get(sha1($key));
    }

    /** Lê um registro completo pelo id. */
    public function get(string $id): ?array
    {
        $file = $this->dir . '/' . $this->safeId($id) . '.json';
        if (!is_file($file)) return null;
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Salva (ou sobrescreve) um registro e atualiza o índice.
     * @return array o registro salvo (inclui id e gerado_em).
     */
    public function save(string $key, string $tipo, string $titulo, array $payload, array $meta = [], array $extra = []): array
    {
        $id = sha1($key);
        $rec = [
            'id'        => $id,
            'tipo'      => $tipo,                       // lei | parlamentar | comparacao | relacao
            'titulo'    => $titulo ?: 'Análise',
            'provedor'  => $meta['provedor'] ?? '',
            'modelo'    => $meta['modelo'] ?? '',
            'gerado_em' => date('c'),
            'payload'   => $payload,                    // resultado da IA (para re-render)
            'extra'     => $extra,                      // dados auxiliares (perfil, perfis, etc.)
        ];
        $this->writeJson($this->dir . '/' . $id . '.json', $rec);
        $this->withIndexLock(fn() => $this->atualizarIndice($rec));
        return $rec;
    }

    /** Lista resumida das análises (mais recentes primeiro). */
    public function listar(int $limit = 100): array
    {
        $idx = $this->lerIndice();
        return array_slice($idx, 0, $limit);
    }

    /** Exclui uma análise do histórico. */
    public function excluir(string $id): void
    {
        $id = $this->safeId($id);
        $file = $this->dir . '/' . $id . '.json';
        if (is_file($file)) unlink($file);
        $this->withIndexLock(function () use ($id) {
            $idx = array_values(array_filter($this->lerIndice(), fn($e) => ($e['id'] ?? '') !== $id));
            $this->writeJson($this->indexFile, $idx);
        });
    }

    private function atualizarIndice(array $rec): void
    {
        $resumo = [
            'id'        => $rec['id'],
            'tipo'      => $rec['tipo'],
            'titulo'    => $rec['titulo'],
            'provedor'  => $rec['provedor'],
            'modelo'    => $rec['modelo'],
            'gerado_em' => $rec['gerado_em'],
        ];
        $idx = array_values(array_filter($this->lerIndice(), fn($e) => ($e['id'] ?? '') !== $rec['id']));
        array_unshift($idx, $resumo);
        $idx = array_slice($idx, 0, 200);
        $this->writeJson($this->indexFile, $idx);
    }

    private function lerIndice(): array
    {
        if (!is_file($this->indexFile)) return [];
        $idx = json_decode((string)file_get_contents($this->indexFile), true);
        return is_array($idx) ? $idx : [];
    }

    private function safeId(string $id): string
    {
        return preg_replace('/[^a-f0-9]/i', '', $id) ?: 'invalido';
    }

    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Não foi possível salvar o histórico.');
        }
    }

    private function withIndexLock(callable $callback): void
    {
        $lockPath = $this->dir . '/index.lock';
        $fh = fopen($lockPath, 'c');
        if (!$fh) {
            throw new \RuntimeException('Não foi possível bloquear o índice do histórico.');
        }

        try {
            if (!flock($fh, LOCK_EX)) {
                throw new \RuntimeException('Não foi possível bloquear o índice do histórico.');
            }
            $callback();
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private function migrarStorageAntigo(): void
    {
        if (is_file($this->indexFile)) {
            return;
        }
        $oldDir = RESUMO_APP_ROOT . '/storage/analises';
        if (!is_dir($oldDir)) {
            return;
        }
        foreach (glob($oldDir . '/*.json') ?: [] as $oldFile) {
            $target = $this->dir . '/' . basename($oldFile);
            if (!is_file($target)) {
                copy($oldFile, $target);
            }
        }
    }
}
