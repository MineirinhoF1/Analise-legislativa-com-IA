<?php
/**
 * Cliente de IA multi-provedor.
 * Suporta: anthropic (Claude), openai (GPT/Codex), deepseek e google (Gemini).
 * Recebe um prompt de sistema + prompt do usuário e devolve o texto gerado.
 */

namespace App;

class AiClient
{
    public function __construct(
        private string $provider,
        private string $apiKey,
        private string $model,
        private int $maxTokens = 4096
    ) {}

    /**
     * Envia a requisição ao provedor ativo e retorna o texto da resposta.
     * @throws \RuntimeException em caso de erro de configuração ou da API.
     */
    public function complete(string $system, string $user): string
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException("Chave de API não configurada para o provedor '{$this->provider}'.");
        }
        if (trim($this->model) === '') {
            throw new \RuntimeException("Modelo não configurado para o provedor '{$this->provider}'.");
        }

        return match ($this->provider) {
            'anthropic' => $this->anthropic($system, $user),
            'openai', 'codex' => $this->openaiResponses($system, $user),
            'deepseek'  => $this->openaiCompatible('https://api.deepseek.com/chat/completions', $system, $user, 'max_tokens'),
            'google'    => $this->google($system, $user),
            default     => throw new \RuntimeException("Provedor desconhecido: {$this->provider}"),
        };
    }

    private function anthropic(string $system, string $user): string
    {
        $payload = [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ];
        $res = $this->request('https://api.anthropic.com/v1/messages', $payload, [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ]);
        return $res['content'][0]['text'] ?? throw new \RuntimeException('Resposta vazia da Anthropic.');
    }

    /** DeepSeek usa formato compatível com chat completions. */
    private function openaiCompatible(string $url, string $system, string $user, string $tokensParam = 'max_tokens'): string
    {
        $payload = [
            'model'    => $this->model,
            $tokensParam => $this->maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
        ];
        $res = $this->request($url, $payload, [
            'Authorization: Bearer ' . $this->apiKey,
            'content-type: application/json',
        ]);
        return $res['choices'][0]['message']['content'] ?? throw new \RuntimeException('Resposta vazia do provedor.');
    }

    private function openaiResponses(string $system, string $user): string
    {
        $payload = [
            'model' => $this->model,
            'max_output_tokens' => $this->maxTokens,
            'instructions' => $system,
            'input' => $user,
        ];
        $res = $this->request('https://api.openai.com/v1/responses', $payload, [
            'Authorization: Bearer ' . $this->apiKey,
            'content-type: application/json',
        ]);
        return $this->openaiResponseText($res);
    }

    private function openaiResponseText(array $res): string
    {
        if (isset($res['output_text']) && trim((string)$res['output_text']) !== '') {
            return (string)$res['output_text'];
        }

        $chunks = [];
        foreach (($res['output'] ?? []) as $item) {
            foreach (($item['content'] ?? []) as $part) {
                $text = $part['text'] ?? '';
                if (trim((string)$text) !== '') {
                    $chunks[] = (string)$text;
                }
            }
        }

        $text = trim(implode("\n", $chunks));
        if ($text !== '') {
            return $text;
        }
        throw new \RuntimeException('Resposta vazia da OpenAI.');
    }

    private function google(string $system, string $user): string
    {
        $model = trim($this->model);
        $modelPath = str_starts_with($model, 'models/')
            ? implode('/', array_map('rawurlencode', explode('/', $model)))
            : 'models/' . rawurlencode($model);
        $url = "https://generativelanguage.googleapis.com/v1beta/{$modelPath}:generateContent?key=" . urlencode($this->apiKey);
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => [
                'maxOutputTokens' => $this->maxTokens,
                'responseMimeType' => 'application/json',
            ],
        ];
        $res = $this->request($url, $payload, ['content-type: application/json']);
        return $this->googleText($res);
    }

    private function googleText(array $res): string
    {
        $chunks = [];
        foreach (($res['candidates'] ?? []) as $candidate) {
            foreach (($candidate['content']['parts'] ?? []) as $part) {
                if (isset($part['text']) && trim((string)$part['text']) !== '') {
                    $chunks[] = (string)$part['text'];
                }
            }
        }

        $text = trim(implode("\n", $chunks));
        if ($text !== '') {
            return $text;
        }

        $finish = $res['candidates'][0]['finishReason'] ?? '';
        $block = $res['promptFeedback']['blockReason'] ?? '';
        $details = array_filter([
            $finish ? "finishReason={$finish}" : '',
            $block ? "blockReason={$block}" : '',
        ]);
        $suffix = $details ? ' (' . implode(', ', $details) . ')' : '';
        throw new \RuntimeException('O Google Gemini retornou JSON, mas não retornou texto de resposta' . $suffix . '.');
    }

    /** Executa a chamada HTTP via cURL e devolve o JSON decodificado. */
    private function request(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException("Falha de conexão com a API: {$err}");
        }
        $json = json_decode($body, true);
        if ($code >= 400) {
            $msg = $json['error']['message'] ?? $json['error']['type'] ?? $body;
            throw new \RuntimeException("Erro da API ({$code}): {$msg}");
        }
        if (!is_array($json)) {
            throw new \RuntimeException('Resposta inválida da API (JSON malformado).');
        }
        return $json;
    }
}
