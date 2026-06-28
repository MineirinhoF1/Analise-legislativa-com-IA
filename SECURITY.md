# Segurança das chaves

Esta v2 aceita chaves por variaveis de ambiente e por configuracao local do painel.

Recomendado para producao:

1. Defina `RESUMO_STORAGE_DIR` fora do webroot.
2. Use variaveis de ambiente para as chaves (`ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `DEEPSEEK_API_KEY`, `GOOGLE_API_KEY`).
3. Nao publique `.env`, `storage/private/`, caches ou historicos.
4. Use HTTPS antes de salvar chaves pelo painel.
5. Restrinja acesso ao painel/rota `api/config.php` em producao, por IP, autenticação HTTP, VPN ou camada equivalente.
6. Rotacione qualquer chave que tenha aparecido em backup, print, log ou commit.
7. Evite logs de payloads enviados aos provedores de IA.

O frontend recebe apenas `has_key` e preview mascarado. A chave completa nunca deve ser retornada por `api/config.php`.
