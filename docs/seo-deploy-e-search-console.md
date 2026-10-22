# SEO: deploy e Google Search Console

Checklist da ABRASIL-SEO-FINAL-01 para publicar o site indexável e ligar o Search Console.
Nenhuma credencial vai neste arquivo nem no código.

## 1. Antes do deploy: o domínio canônico

O container `abrasilsistema` lê o `.env.example` deste repositório (`env_file` no
`docker-compose.yml` da infra), mas o **`APP_URL` vem do compose**:

```yaml
APP_URL: ${ABRASILSISTEMA_APP_URL:-http://abrasilsistema.localhost}
```

Se `ABRASILSISTEMA_APP_URL` não estiver no `.env` da infra na VPS, o domínio canônico vira
`abrasilsistema.localhost` e **todas as páginas saem com `noindex` e o robots.txt com `Disallow: /`**.

Confira na VPS (sem abrir os segredos):

```bash
grep -E '^ABRASILSISTEMA_APP_URL=' /caminho/da/infra-abrasil/.env
# esperado: ABRASILSISTEMA_APP_URL=https://abrasilsistemas.com.br
```

Também precisam estar assim:

| Item | Valor em produção |
|---|---|
| `SEO_BLOCK_INDEXING` (`.env.example`) | `false` (`true` só em homologação) |
| Painel → SEO → Configurações → domínio canônico | vazio (usa o `APP_URL`) ou `https://abrasilsistemas.com.br` |
| Painel → SEO → Configurações → bloquear indexação | desligado |

O nginx já redireciona `http://` e `www.` para `https://abrasilsistemas.com.br` (301).

## 2. Deploy

Depois de autorizado, publique o código e rode no container da aplicação:

```bash
php artisan migrate --force          # cria a categoria "SEO e performance" (só se não existir)
php artisan optimize:clear
php artisan blog:seo-drafts --dry-run  # mostra as pautas que seriam criadas
php artisan blog:seo-drafts            # cria as pautas como RASCUNHO (não publica)
php artisan seo:check                  # simula as páginas no domínio canônico
php artisan seo:check --live           # confere o site publicado por HTTPS
```

O `seo:check` falha (código 1) se encontrar: domínio canônico sem HTTPS ou não público,
`SEO_BLOCK_INDEXING` ligado, bloqueio do painel ligado, sitemap vazio ou com URL de outro domínio,
JSON-LD inválido, robots.txt fechado, página principal com `noindex` ou canonical errado.

O `blog:seo-drafts` pula a pauta quando já existe artigo com o mesmo endereço, título parecido
ou a mesma palavra-chave principal. Rodar de novo não duplica nada.

## 3. Search Console (propriedade já cadastrada)

1. Em <https://search.google.com/search-console>, abra a propriedade `abrasilsistemas.com.br`.
2. Se ainda não estiver verificada: em propriedade de **domínio**, use o registro TXT no DNS. Em
   propriedade de **prefixo de URL**, cole o código da meta tag em Painel → SEO → Integrações →
   "Verificação do Google".
3. **Sitemaps** → envie `https://abrasilsistemas.com.br/sitemap.xml`.
4. **Inspeção de URL** → teste `https://abrasilsistemas.com.br/` e
   `https://abrasilsistemas.com.br/seo-tecnico-e-otimizacao-de-sites` e peça a indexação.
5. Acompanhe **Indexação de páginas** nos dias seguintes. Áreas privadas (login, painel,
   atendimento, solicitações) aparecem como excluídas por `noindex`/robots, e isso é o esperado.

## 4. OAuth do Search Console e do GA4 no painel (opcional)

Serve para o painel mostrar cliques, impressões e consultas. A integração **não está ativa** até
estes passos serem feitos e testados.

1. No [Google Cloud Console](https://console.cloud.google.com/), crie (ou escolha) um projeto.
2. **APIs e serviços → Biblioteca**: ative a **Google Search Console API** e, se for usar o GA4, a
   **Google Analytics Data API**.
3. **Tela de consentimento OAuth**: tipo *Externo* (ou *Interno*, se houver Google Workspace), com o
   nome do app, o e-mail de suporte e o domínio `abrasilsistemas.com.br`. Escopos (só leitura):
   - `https://www.googleapis.com/auth/webmasters.readonly`
   - `https://www.googleapis.com/auth/analytics.readonly`

   Em modo *Teste*, cadastre como usuário de teste a conta Google que tem acesso à propriedade.
4. **Credenciais → Criar credenciais → ID do cliente OAuth → Aplicativo da Web**:
   - Origem JavaScript autorizada: `https://abrasilsistemas.com.br`
   - URI de redirecionamento autorizado:
     `https://abrasilsistemas.com.br/admin/seo/integracoes/google/callback`
5. Leve o *Client ID* e o *Client secret* para o servidor como `GOOGLE_SEARCH_CONSOLE_CLIENT_ID` e
   `GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET`. **Não** coloque os valores no `.env.example` (que é
   versionado). O caminho seguro é colocá-los no `.env` da infra e mapeá-los no bloco
   `environment` do serviço `abrasilsistema` no compose. Isso altera a configuração do container e
   depende de autorização.
6. Painel → SEO → Integrações → **Conectar Google**, autorize com a conta da propriedade e escolha a
   propriedade do Search Console (e o ID da propriedade do GA4, se houver).
7. Teste: a tela deve mostrar os dados do período. O Search Console tem cerca de 3 dias de atraso.

## 5. Depois de publicado

- Revise e publique os rascunhos de SEO pelo painel (Blog → Artigos), um por vez, conferindo a
  análise editorial de cada um.
- Rode a auditoria em Painel → SEO → Auditoria do site (precisa do worker da fila).
- Nenhuma ferramenta garante posição no Google. O que se acompanha é indexação, impressões,
  cliques e consultas no Search Console.
