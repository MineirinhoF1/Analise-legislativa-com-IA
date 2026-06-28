# Configuração e conclusão da expansão de parlamentares

Este arquivo explica como operar a expansão da aba **Parlamentares** para além de deputados federais.

## 1. O que foi implementado

### Deputados federais

- Fonte: Câmara dos Deputados — Dados Abertos.
- Escopo: atuação parlamentar com perfil, proposições, cota parlamentar, fornecedores, comissões, frentes, votos recuperáveis e emendas quando houver token do Portal da Transparência.
- Status: completo dentro dos limites já existentes do projeto.

### Senadores

- Fonte: Senado Federal — Dados Abertos.
- Endpoint usado: `https://legis.senado.leg.br/dadosabertos/senador/lista/atual`.
- Escopo atual: cadastro/perfil atual, partido, UF, foto, página oficial e links de conferência.
- Limite atual: a integração inicial ainda não coleta produção legislativa detalhada, votações, comissões, emendas ou despesas de senadores.

### TSE — vereadores, deputados estaduais/distritais e senadores eleitos

- Fonte: Repositório de Dados Eleitorais do TSE.
- Base usada: arquivos `consulta_cand_{ano}.zip`.
- URLs oficiais:
  - `https://cdn.tse.jus.br/estatistica/sead/odsele/consulta_cand/consulta_cand_2024.zip`
  - `https://cdn.tse.jus.br/estatistica/sead/odsele/consulta_cand/consulta_cand_2022.zip`
- Escopo atual: cadastro eleitoral e situação do candidato/eleito.
- Limite atual: TSE não traz atuação de mandato, projetos, votos, gastos parlamentares, comissões ou fornecedores.

## 2. Configuração necessária

### Permissões de escrita

O sistema precisa conseguir escrever em:

```text
/storage/private/
```

Novos caches criados:

```text
storage/private/senado/
storage/private/tse/
```

Em produção, prefira configurar `RESUMO_STORAGE_DIR` para um diretório fora do webroot.

### Extensões PHP necessárias

Além das já usadas pelo projeto, a expansão TSE exige:

```text
zip
```

Verifique com:

```bash
php -m | grep -i zip
```

No ambiente atual, `zip`, `curl`, `SimpleXML`, `xml`, `dom` e `mbstring` estão disponíveis.

### Primeiro uso do TSE

A primeira busca em **Cadastro eleitoral — TSE** baixa e extrai o ZIP oficial do ano escolhido.

Tamanhos aproximados:

- `consulta_cand_2024.zip`: cerca de 60 MB.
- `consulta_cand_2022.zip`: cerca de 4 MB.

Depois do primeiro download, o CSV fica cacheado localmente e as buscas seguintes usam o cache.

## 3. Como usar na interface

Na aba **Parlamentares**, use o campo **Fonte / escopo**:

- **Deputado federal — Câmara:** análise mais completa de atuação.
- **Senador — Senado:** perfil atual e links oficiais do Senado.
- **Cadastro eleitoral — TSE:** cadastro de vereadores, deputados estaduais/distritais e senadores eleitos.

Para TSE:

1. Escolha **Cadastro eleitoral — TSE**.
2. Escolha o cargo:
   - Vereador — eleição 2024.
   - Deputado estadual/distrital — eleição 2022.
   - Senador eleito — eleição 2022.
3. Informe nome, partido, UF ou município.
4. Clique em **Buscar**.
5. Selecione o registro.
6. Clique em **Analisar cadastro**.

## 4. Próximas etapas para cobertura completa

### Senadores

Para deixar senadores com análise equivalente aos deputados federais, implementar:

1. Proposições de autoria do senador.
2. Relatorias e matérias em tramitação.
3. Votações nominais.
4. Comissões e cargos no Senado.
5. Discursos e links oficiais adicionais.
6. Eventual integração de emendas por nome, com alerta de homônimos.

### Deputados estaduais

Não existe API nacional padronizada. A conclusão depende de adapters por Assembleia Legislativa.

Prioridade sugerida:

1. ALESP.
2. ALERJ.
3. ALMG.
4. ALEP.
5. CLDF, quando tratar deputado distrital.

Cada adapter deve mapear:

- busca de parlamentar;
- perfil;
- proposições;
- votações, se houver;
- despesas/verbas, se houver;
- comissões;
- links oficiais.

### Vereadores

Também não existe API nacional padronizada. A conclusão depende de adapters por Câmara Municipal.

Prioridade sugerida:

1. Câmara Municipal de São Paulo.
2. Câmara Municipal do Rio de Janeiro.
3. Câmara Municipal de Belo Horizonte.
4. Câmara Municipal de Curitiba.
5. Demais cidades conforme demanda.

## 5. Regra de comunicação ao usuário

Sempre diferenciar:

- **Atuação parlamentar:** dados de mandato, projetos, votos, gastos e comissões.
- **Cadastro eleitoral:** dados do TSE sobre candidatura/eleição.
- **Perfil atual:** dados cadastrais de órgão legislativo, ainda sem atuação detalhada.

O relatório não deve afirmar que vereador, deputado estadual ou senador tem atuação analisada quando a fonte usada for apenas o TSE.

Links oficiais do Senado, TSE e Câmara devem ser preservados como referência primária. A leitura da IA é apoio interpretativo e não deve ser registrada como fonte factual.
