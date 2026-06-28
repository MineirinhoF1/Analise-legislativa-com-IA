<?php
/**
 * Gerencia as configurações do sistema (provedor de IA, chaves, modelos).
 * Persiste fora do webroot. O storage antigo dentro da pasta pública é migrado
 * automaticamente apenas para compatibilidade.
 */

namespace App;

class Settings
{
    private string $path;

    /** Modelos permitidos na UI e na validação do backend. */
    public const MODEL_OPTIONS = [
        'anthropic' => [
            'claude-fable-5',
            'claude-opus-4-8',
            'claude-sonnet-4-6',
            'claude-haiku-4-5',
        ],
        'openai' => [
            'gpt-5.5',
            'gpt-5.4',
            'gpt-5.4-mini',
            'gpt-5.4-nano',
        ],
        'deepseek' => [
            'deepseek-v4-pro',
            'deepseek-v4-flash',
        ],
        'google' => [
            'gemini-3.5-flash',
            'gemini-flash-latest',
            'gemini-2.5-pro',
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
        ],
    ];

    /** Estrutura padrão das configurações. */
    private const DEFAULTS = [
        'provider' => 'anthropic',
        'providers' => [
            'anthropic' => ['api_key' => '', 'model' => 'claude-sonnet-4-6'],
            'openai'    => ['api_key' => '', 'model' => 'gpt-5.5'],
            'deepseek'  => ['api_key' => '', 'model' => 'deepseek-v4-flash'],
            'google'    => ['api_key' => '', 'model' => 'gemini-3.5-flash'],
        ],
        'max_tokens' => 8192,
        'portal_transparencia_token' => '', // chave grátis: api.portaldatransparencia.gov.br
    ];

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? \storagePath('config.json');
        $this->migrarStorageAntigo();
    }

    /** Lê as configurações mescladas com os padrões. */
    public function all(): array
    {
        return $this->applyEnvOverrides($this->storedConfig());
    }

    private function storedConfig(): array
    {
        $data = [];
        if (is_file($this->path)) {
            $json = json_decode((string)file_get_contents($this->path), true);
            if (is_array($json)) {
                $data = $json;
            }
        }
        return $this->normalize($this->merge(self::DEFAULTS, $data));
    }

    /** Salva configurações (mescladas com o que já existe). */
    public function save(array $incoming): array
    {
        $current = $this->storedConfig();
        $merged  = $this->normalize($this->merge($current, $incoming));

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->writeJson($this->path, $merged);
        return $this->applyEnvOverrides($merged);
    }

    public static function modelOptions(): array
    {
        return self::MODEL_OPTIONS;
    }

    /** Retorna o provedor ativo e suas credenciais. */
    public function activeProvider(): array
    {
        $cfg  = $this->all();
        $name = $cfg['provider'] ?? 'anthropic';
        $prov = $cfg['providers'][$name] ?? [];
        return [
            'name'       => $name,
            'api_key'    => $prov['api_key'] ?? '',
            'model'      => $prov['model'] ?? '',
            'max_tokens' => (int)($cfg['max_tokens'] ?? self::DEFAULTS['max_tokens']),
        ];
    }

    /** Token do Portal da Transparência (server-side). */
    public function portalToken(): string
    {
        return (string)($this->all()['portal_transparencia_token'] ?? '');
    }

    /**
     * Versão segura para enviar ao frontend: mascara as chaves de API.
     */
    public function publicView(): array
    {
        $cfg = $this->all();
        foreach ($cfg['providers'] as &$p) {
            $key = $p['api_key'] ?? '';
            $p['api_key']     = '';                 // nunca expor a chave
            $p['has_key']     = $key !== '';
            $p['key_preview'] = $key !== '' ? '••••••••' . substr($key, -4) : '';
        }
        unset($p);
        $tok = $cfg['portal_transparencia_token'] ?? '';
        $cfg['portal_transparencia_token'] = '';
        $cfg['portal_has_token'] = $tok !== '';
        $cfg['model_options'] = self::MODEL_OPTIONS;
        return $cfg;
    }

    /** Mescla recursivamente, preservando chaves vazias enviadas. */
    private function merge(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = $this->merge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }

    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Não foi possível salvar as configurações.');
        }
    }

    private function normalize(array $cfg): array
    {
        $allowedProviders = array_keys(self::MODEL_OPTIONS);
        if (!in_array((string)($cfg['provider'] ?? ''), $allowedProviders, true)) {
            $cfg['provider'] = self::DEFAULTS['provider'];
        }

        foreach (self::MODEL_OPTIONS as $name => $models) {
            if (!isset($cfg['providers'][$name]) || !is_array($cfg['providers'][$name])) {
                $cfg['providers'][$name] = self::DEFAULTS['providers'][$name];
            }
            $model = trim((string)($cfg['providers'][$name]['model'] ?? ''));
            if (!in_array($model, $models, true)) {
                $cfg['providers'][$name]['model'] = self::DEFAULTS['providers'][$name]['model'];
            }
            $cfg['providers'][$name]['api_key'] = (string)($cfg['providers'][$name]['api_key'] ?? '');
        }

        $cfg['max_tokens'] = max(2048, min(24000, (int)($cfg['max_tokens'] ?? self::DEFAULTS['max_tokens'])));
        $cfg['portal_transparencia_token'] = (string)($cfg['portal_transparencia_token'] ?? '');
        return $cfg;
    }

    private function migrarStorageAntigo(): void
    {
        if (is_file($this->path)) {
            return;
        }
        $old = RESUMO_APP_ROOT . '/storage/config.json';
        if (!is_file($old)) {
            return;
        }
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        copy($old, $this->path);
    }

    private function applyEnvOverrides(array $cfg): array
    {
        $provider = \envValue('RESUMO_PROVIDER');
        if ($provider !== '' && isset(self::MODEL_OPTIONS[$provider])) {
            $cfg['provider'] = $provider;
        }

        $envKeys = [
            'anthropic' => 'ANTHROPIC_API_KEY',
            'openai' => 'OPENAI_API_KEY',
            'deepseek' => 'DEEPSEEK_API_KEY',
            'google' => 'GOOGLE_API_KEY',
        ];
        foreach ($envKeys as $name => $env) {
            $key = \envValue($env);
            if ($key !== '') {
                $cfg['providers'][$name]['api_key'] = $key;
            }
        }

        $portalToken = \envValue('PORTAL_TRANSPARENCIA_TOKEN');
        if ($portalToken !== '') {
            $cfg['portal_transparencia_token'] = $portalToken;
        }

        $maxTokens = \envValue('RESUMO_MAX_TOKENS');
        if ($maxTokens !== '') {
            $cfg['max_tokens'] = max(2048, min(24000, (int)$maxTokens));
        }

        return $cfg;
    }
}
