# Execução de `correio.md`: ABRASIL-COMERCIAL-03 (publicação e ativação comercial)

Data: 23/11/2026. O `correio.md` é diferente do anterior (o último executado foi ABRASIL-COMERCIAL-02) e foi executado.

**Situação: publicada em produção e funcionando**, com formulário e notificação testados. Ficam pendentes verificações que exigem login, caixa de e-mail ou navegador (seção 7).

## 1. Versão publicada

| Item | Valor |
|---|---|
| Código | `effa997` (COMERCIAL-01 e 02), fast-forward sobre o `84ad8c8` que estava no ar |
| Ajuste junto | `Dockerfile`: upload de 5 MB no PHP, já em produção desde a manhã e agora commitado |
| Branch | `site/vetorsales-vetorleads`. O `main` **não** foi alterado (ver pendência 1) |
| Containers recriados | `abrasilsistema`, `-worker` e `-scheduler`. Nenhum outro produto foi recriado |
| Transição | cerca de 5 s entre recriar, migrar e recarregar o nginx |

O commit deste relatório contém `Dockerfile`, `correio.md` e `executed.md`.

## 2. Backup e reversão

- Pasta: `/opt/infra-abrasil/backups/abrasilsistemas_20261123-comercial03` (checksums em `SHA256SUMS`).
- `banco-final.sql.gz`: tirado segundos antes do deploy. `banco.sql.gz`: de minutos antes, **restaurado com sucesso** num MySQL 8.4 descartável. Também há cópias dos uploads, dos assets e das configurações.
- As duas migrations foram aplicadas, revertidas e reaplicadas nessa cópia dos dados reais.
- Imagens anteriores: tag `rollback-20261123-comercial03` nos três serviços.
- **Reverter:** `migrate:rollback --step=2` com o código novo no ar; restaurar as tags `rollback-20261123-comercial03` como `latest`; `docker compose up -d --no-build --no-deps` nos três serviços; `apps.conf` da pasta de backup e `nginx -s reload`.

## 3. Migrations

Aplicadas, 0 pendentes: `2026_11_23_120000_add_commercial_origin_to_leads_table` e `2026_11_23_180000_adopt_commercial_request_statuses`. A única solicitação existente (nº 122) passou de Respondida para Em atendimento. Nenhum dado foi apagado.

## 4. Ambiente e nginx

- `APP_ENV=production` e `APP_DEBUG=false` já estão fixados só para este serviço no compose. A variável compartilhada não foi alterada.
- SMTP (Hostinger, 465), fila `database` e worker funcionando.
- nginx, **somente** no bloco HTTPS de `abrasilsistemas.com.br`: gzip em HTML, CSS, JS, JSON e XML; cache de 1 ano (`immutable`) em `/build/assets/`; HTML mantém `no-cache, private`; `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`; `X-Powered-By` removido.
- Home: 73.949 bytes sem compressão, 16.158 com gzip.
- HTTP e `www` redirecionam (301) para o domínio canônico em HTTPS.
- HSTS não foi ligado: é difícil de desfazer e depende de decisão.

## 5. Verificações no domínio público

| Item | Resultado |
|---|---|
| 32 URLs do sitemap e 3 páginas legais | todas 200, com um H1, canonical correto e viewport |
| Dados estruturados | JSON-LD válido nas 32 URLs do sitemap. As páginas legais são `noindex` e não têm JSON-LD |
| Conteúdo sem JavaScript | home 1.111 palavras, VetorOS 588, contato 241; nenhum marcador sem preencher |
| robots.txt e sitemap | abertos e coerentes |
| `seo:check --live` | tudo aprovado, "Pronto para indexação" |
| `site:preflight` | aprovado, com 5 avisos (seção 6) |
| Página inexistente | 404 |
| `/solicitacoes` sem login | redireciona para `/login` |
| Imagem enviada (`/storage`) | 200 |
| Widget VetorChat | script presente nas 35 páginas públicas verificadas e respondendo 200 |
| VetorOS, VetorLeads, VetorSales, VetorSmart, Desgarrados, n8n, WAHA | mesmos códigos de antes |

**Envio de homologação (um só):** solicitação nº 123, "HOMOLOGACAO COMERCIAL-03", com o próprio e-mail da empresa. Confirmação na tela, gravação com UTM e página de entrada, e o worker concluiu o `ContactFormReceived` em 2 s, aceito pelo SMTP. Ela está em **Novo contato** e pode ser arquivada pelo painel.

## 6. SEO e dados institucionais

- As configurações gravadas no painel **prevalecem** sobre o código. Nenhuma página tem metadados próprios no banco, e a home já exibe o título novo.
- Diferem do padrão atual: `default_title` ("Software, sistemas e integração para empresas") e `default_description`, que **ainda cita o VetorPet**. Só valem como reserva para páginas sem título próprio. Não foram alterados; revisar em Painel → SEO → Configurações.
- Pré-render (conferido no código): contato e dados institucionais são preenchidos a cada requisição. Textos de serviços e produtos mudam só em novo build. Depoimentos, portfólio e últimos artigos da home dependem de JavaScript.
- Nada foi presumido. Razão social, CNPJ, município/UF e horário estão vazios e não aparecem; a área de atendimento usa o padrão "Brasil".
- **Para preencher:** Painel → SEO → Configurações: razão social, CNPJ (validado), município e UF (só publicados se a opção for marcada), área de atendimento, e-mail, telefone e horário.
- Outros avisos do preflight: `SESSION_SECURE_COOKIE` não está definido (os cookies já saem com `secure`) e 2 tarefas com falha de 15 e 16/10, por senha SMTP recusada na época. Não foram reenviadas nem apagadas.

## 7. Pendências

1. **`main`:** decidir se avança para esta versão (`git push origin site/vetorsales-vetorleads:main`, fast-forward).
2. **Repositório da infra:** `nginx/conf.d/apps.conf` está aplicado, mas sem commit. Depende da montagem em `docker-compose.yml`, que tem outras alterações pendentes de outros produtos; o ponteiro do submódulo também não foi atualizado.
3. **Caixa de entrada:** confirmar a chegada do e-mail em `contato@abrasilsistemas.com.br` (o servidor aceitou; a caixa não foi consultada).
4. **Painel de Solicitações:** abrir a nº 123, testar filtros e observação, e arquivar. Exige login.
5. **Celular e widget no navegador:** não verificados. O navegador headless na VPS foi bloqueado pelo controle de permissões. Só foram conferidos viewport, HTML e carregamento do script.
6. Preencher dados institucionais e revisar título e descrição padrão de SEO.
7. Decidir sobre HSTS e sobre as 2 tarefas antigas com falha.

## Não executado

A suíte de testes não foi repetida, conforme o pedido. Reenvio duplicado e falha de SMTP não foram provocados em produção.
