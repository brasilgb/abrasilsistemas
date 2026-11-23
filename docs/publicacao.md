# Publicação controlada

Roteiro da ABRASIL-COMERCIAL-02 §7. Cada etapa no servidor depende de autorização. Nenhuma senha entra aqui.
Os comandos de servidor não foram executados nesta entrega.

## 1. Antes

1. Anotar o commit em produção (`git rev-parse HEAD`) e guardar a imagem atual:
   `docker image tag <imagem do abrasilsistema> abrasilsistema:anterior`.
2. Backup do banco, fora do diretório publicado, e conferir que não está vazio:
   ```bash
   docker compose exec -T mysql sh -c 'mysqldump --single-transaction --no-tablespaces -uroot -p"$MYSQL_ROOT_PASSWORD" abrasilsistemas' | gzip > backup-abrasilsistemas-AAAA-MM-DD.sql.gz
   gzip -t backup-abrasilsistemas-AAAA-MM-DD.sql.gz && ls -lh backup-abrasilsistemas-*.sql.gz
   ```
3. Copiar `volumes/abrasilsistema` (imagens enviadas pelo painel).
4. Conferir no `.env` da infra, sem exibir segredos: `APP_ENV=production`, `APP_DEBUG=false` e
   `ABRASILSISTEMA_APP_URL=https://abrasilsistemas.com.br`. **O compose usa `APP_ENV=local` quando a
   variável não existe, e ela é comum a todos os sistemas da infra.**

## 2. Migrations desta entrega

| Migration | Efeito | Reversão |
|---|---|---|
| `2026_11_23_120000_add_commercial_origin_to_leads_table` | Colunas de origem e consentimento; empresa opcional | Remove as colunas (perde as origens gravadas) |
| `2026_11_23_180000_adopt_commercial_request_statuses` | Respondida → Em atendimento; Concluída → Arquivada, com histórico | Restaura as situações; as novas voltam à mais próxima |

A produção pode ter outras pendentes: confira com `php artisan migrate:status`.

## 3. Publicar

Reconstruir a imagem (o build gera os assets e o HTML inicial) e recriar `abrasilsistema`,
`abrasilsistema-worker` e `abrasilsistema-scheduler`. Depois, no container:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan site:preflight      # ambiente, migrations, assets, e-mail, fila e SEO gravado no banco
php artisan seo:check --live    # indexação no domínio publicado
```

Rode a migration **logo após** recriar o container: até lá, o formulário não consegue gravar (as colunas de
origem ainda não existem) e o visitante recebe a mensagem de erro com o e-mail de contato.

## 4. Conferir no ar

- [ ] `/`, um serviço, um produto, `/blog` e `/contato` abrem no celular.
- [ ] `curl -s https://abrasilsistemas.com.br/ | grep -c '<h1'` devolve 1 (conteúdo sem JavaScript).
- [ ] Um envio real do formulário: confirmação na tela, solicitação no painel e e-mail na caixa da equipe.
      Arquivar a solicitação de teste.
- [ ] Reenvio da mesma mensagem não duplica.
- [ ] Painel: filtros, mudança de situação e observação.
- [ ] Painel → SEO → Configurações: dados institucionais; revisar títulos gravados no banco, que
      prevalecem sobre os novos.

## 5. Reverter

1. Só o HTML inicial: `SEO_PRERENDER=false` e recriar o container.
2. Código e banco: `php artisan migrate:rollback --step=1` **com o código novo ainda no ar** (repetir só se
   também for preciso remover as colunas de origem), depois voltar para a imagem anterior e
   `php artisan optimize:clear`. O `--step` conta migrations, da mais recente para a mais antiga: confira
   `migrate:status` antes.
3. Banco corrompido: restaurar o backup. As solicitações recebidas depois dele se perdem; exporte antes.

## HTML inicial (pré-renderização)

Medição sem executar JavaScript (palavras no corpo da página):

| Página | Antes | Agora |
|---|---|---|
| `/` | 0 | cerca de 1.100 |
| `/desenvolvimento-de-sistemas` | 0 | cerca de 890 |
| `/seo-tecnico-e-otimizacao-de-sites` | 0 | cerca de 1.280 |
| `/sistemas/vetoros` | 0 | cerca de 560 |
| `/sobre` | 0 | cerca de 430 |

| Alternativa | Decisão |
|---|---|
| SSR do Inertia | Exige processo Node permanente. Descartado |
| Prerender externo ou Chromium no servidor | Serviço ou dependência a mais, e conteúdo diferente para robôs. Descartado |
| Repetir os textos em Blade | Duas fontes para o mesmo conteúdo. Só usado no blog, que já vem do banco |
| **React para HTML no build** | Adotado: `scripts/prerender.mjs` usa o Vite e o `react-dom` que o projeto já tem; o servidor só lê arquivos |

O mesmo HTML é entregue a pessoas e robôs. Contatos e dados institucionais são preenchidos a cada
requisição; textos de serviços e produtos mudam no próximo build, como já acontecia. Na página inicial,
depoimentos, portfólio e últimos artigos ainda aparecem só com JavaScript.
