# Execução de `correio.md`: ABRASIL-PROD-CONFIG-01, correção do ambiente e ativação de integrações

Data: 2026-10-22. Base: `correio.md` alterado em relação à execução anterior (ABRASIL-SEO-FINAL-01).

**Status: execução concluída no que depende da infra, com uma pendência externa.** O ambiente de produção foi corrigido, as três chaves foram ligadas ao container e os containers do ABrasil foram recriados. Falta apenas validar o reconhecimento do OAuth no painel SEO, que exige login no painel administrativo.

## 1. Segurança de produção

- Ambiente efetivo antes da correção: `local`, confirmado com `php artisan about --only=environment` no container `infra-abrasil-abrasilsistema-1`. Debug estava OFF.
- `APP_ENV=local` vinha do `.env` da raiz e do padrão `${APP_ENV:-local}` do bloco `laravel-environment`. `LOG_LEVEL=debug` vinha do `.env.example` referenciado pelo `env_file`.
- **Correção aplicada** em `/opt/infra-abrasil/docker-compose.yml`, somente no serviço `abrasilsistema` (o `environment` sobrescreve o `env_file`):
  - `APP_ENV: production`
  - `APP_DEBUG: "false"`
  - `LOG_LEVEL: warning`
- O bloco compartilhado `laravel-environment` não foi alterado, então VetorOS, VetorPet e Desgarrados mantêm os valores anteriores.
- `APP_KEY`, `APP_URL`, credenciais, banco e demais variáveis não foram alterados.
- Após a recriação, `php artisan about --only=environment` no container: **Environment: production**, **Debug Mode: OFF**.

## 2. Configuração Laravel e containers

- Cache de configuração: não existia (`bootstrap/cache` sem `config.php`). `config:cache` não foi gerado, porque a configuração é lida do ambiente a cada requisição.
- **Primeira recriação:** `docker compose up -d --no-deps --force-recreate abrasilsistema abrasilsistema-worker abrasilsistema-scheduler`, sem build. Aplicou o ambiente de produção.
- **Segunda recriação:** mesmo comando, após vincular as chaves do Google Search Console e do VetorChat (ver item 3 e 4).
- Não foram recriados outros serviços (VetorOS, VetorPet, Desgarrados, VetorLeads, WAHA, MySQL).
- Estado final: `infra-abrasil-abrasilsistema-1`, `-worker-1` e `-scheduler-1` estão `Up` e `healthy`.
- `storage/logs` está vazio no container. Não há `laravel.log` para analisar.

## 3. Google Search Console

- `GOOGLE_SEARCH_CONSOLE_CLIENT_ID`: presente no container (72 caracteres).
- `GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET`: presente no container (35 caracteres).
- Causa do valor vazio antes: as chaves estavam só no `.env` da aplicação, que o container não lê. Foram movidas para o `.env` da raiz, e o serviço `abrasilsistema` no `docker-compose.yml` passou a referenciá-las com `${VAR:-}`.
- Reconhecimento no painel SEO → Integrações e URI OAuth `https://abrasilsistemas.com.br/admin/seo/integracoes/google/callback`: **não verificados** (exige login no painel administrativo). Nenhuma credencial foi alterada.

## 4. VetorChat

- `VETORCHAT_SCRIPT_URL`: presente (`https://vetorleads.com.br/widget.js`).
- `VETORCHAT_WIDGET_KEY`: presente no container (48 caracteres), com a mesma causa e correção da seção 3.
- A home pública já referencia o `widget.js` (1 ocorrência no HTML).
- Registro do widget no tenant, domínio autorizado e produto ABrasil: não verificados (dependem do painel VetorLeads).

## 5. WAHA e n8n

- `WAHA_BASE_URL=http://waha:3000`, com `WAHA_API_KEY` e `WAHA_WEBHOOK_HMAC_KEY` presentes.
- `N8N_WHATSAPP_WEBHOOK_URL` e `N8N_WHATSAPP_WEBHOOK_TOKEN` presentes.
- Nada removido e nenhum envio automático ativado.

## 6. Validação objetiva

| Item | Resultado |
|---|---|
| Laravel em modo produção | Sim (`production`) |
| Debug desativado | Sim (OFF) |
| Página pública funcionando | Sim: `https://abrasilsistemas.com.br/` responde HTTP 200 |
| `https://vetorleads.com.br/widget.js` | HTTP 200 |
| Widget referenciado na home | Sim (script presente no HTML) |
| Painel SEO reconhecendo OAuth | Não verificado (exige login no painel) |
| Erros críticos nos logs | Sem `error`, `critical`, `emergency` ou `alert` nas últimas 300 linhas de `docker logs infra-abrasil-abrasilsistema-1` |

Testes não foram executados (conforme o item 6 do correio).

## 7. Relatório

**Configurações corrigidas:**
- `APP_ENV=production`, `APP_DEBUG=false` e `LOG_LEVEL=warning` no serviço `abrasilsistema` do `docker-compose.yml`.
- `GOOGLE_SEARCH_CONSOLE_CLIENT_ID`, `GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET` e `VETORCHAT_WIDGET_KEY` passaram a ser lidas do `.env` da raiz e chegam ao container.

**Ambiente efetivo identificado:** antes, container `infra-abrasil-abrasilsistema-1` em `local`, debug OFF. Depois, `production`, debug OFF.

**Chaves presentes ou ausentes (sem valores):**
- Presentes no container: `GOOGLE_SEARCH_CONSOLE_CLIENT_ID`, `GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET`, `VETORCHAT_WIDGET_KEY`, `VETORCHAT_SCRIPT_URL`, `WAHA_API_KEY`, `WAHA_WEBHOOK_HMAC_KEY`, `N8N_WHATSAPP_WEBHOOK_URL`, `N8N_WHATSAPP_WEBHOOK_TOKEN`, `APP_KEY`.
- Ausentes ou vazias: nenhuma.

**Rebuild ou restart:** recriados apenas `abrasilsistema`, `abrasilsistema-worker` e `abrasilsistema-scheduler`, sem build de imagem, em duas etapas.

**Resultado das verificações:** ambiente production e debug OFF confirmados; página pública e `widget.js` com HTTP 200; widget referenciado na home; containers `healthy`; logs sem erros críticos recentes. Painel SEO/OAuth não verificado.

**Pendências:**
1. Validar no painel SEO → Integrações que as credenciais são reconhecidas e que a URI `https://abrasilsistemas.com.br/admin/seo/integracoes/google/callback` está cadastrada no Google.
2. Confirmar no VetorLeads que o widget está registrado no tenant institucional, com o domínio autorizado e o produto ABrasil identificado. Sem isso, o widget pode não carregar a configuração completa.
3. Limpar a duplicidade das três chaves no `.env` local da aplicação (`gateway/abrasilsistemas/.env`), que já não é lido pelo container.

**Reversão:** remover as três linhas de ambiente (`APP_ENV`, `APP_DEBUG`, `LOG_LEVEL`) e as três de integração do serviço `abrasilsistema` no `docker-compose.yml` e recriar os containers.
