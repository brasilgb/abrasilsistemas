# Execução de `correio.md`: paleta de cores do site ABrasilSistemas

Data: 2026-11-04. Base: `correio.md` alterado em relação à execução anterior (blog, rascunhos de serviços). O conteúdo atual trata somente de cores e não cria, altera nem publica nada no banco.

**Status: executado localmente, sem commit e sem publicação.** Alterações apenas na árvore de trabalho. Verificação visual no navegador (desktop, celular e capturas) **não foi feita**, pelo motivo descrito na seção 4.

## 1. Tokens centralizados

Em `resources/css/app.css`, bloco `@theme`, foram criados os tokens da marca:

| Token | Valor | Uso |
|---|---|---|
| `--color-brand-navy` | `#0f172a` | cabeçalho, rodapé, fundo escuro do hero |
| `--color-brand-blue` | `#2563eb` | botões principais, links, destaques |
| `--color-brand-blue-hover` | `#1d4ed8` | hover dos botões principais |
| `--color-brand-cyan` | `#38bdf8` | detalhes tecnológicos e indicadores ativos |
| `--color-brand-surface` | `#ffffff` | fundo principal |
| `--color-brand-surface-muted` | `#f8fafc` | fundo de seções alternadas |
| `--color-brand-ink` | `#0f172a` | títulos sobre fundo claro |
| `--color-brand-body` | `#475569` | texto sobre fundo claro |
| `--color-brand-on-dark` | `#f8fafc` | texto sobre fundo escuro |
| `--color-brand-on-dark-muted` | `#cbd5e1` | texto secundário sobre fundo escuro |
| `--color-brand-line` | `#e2e8f0` | bordas e divisórias claras |

Também foram reapontados os tokens do tema claro e escuro (`:root` e `.dark`): `--primary`, `--ring`, `--secondary`, `--accent`, `--chart-*`, `--sidebar-*` e os fundos do modo escuro. Antes, o primário era `#0d47a1` e o ciano era `#00b4ff`.

Os cinzas usados no site (`slate-50`, `slate-200`, `slate-600`, `slate-900`, `slate-300`) já coincidem exatamente com os valores da paleta, então foram mantidos como estão.

## 2. Aplicação

Foram alterados `app.css` e mais 21 arquivos de página e componentes públicos (`components/site/*`, `components/public-footer.tsx`, `blog-public-layout`, páginas de início, sobre, serviços, contato, blog, legais, 404, serviços/sistemas/SEO e layouts de autenticação):

- `#0b1220` e `#070c17` (fundos escuros, inclusive o gradiente do carrossel) → `brand-navy`.
- `bg-blue-700` / `hover:bg-blue-800` → `brand-blue` / `brand-blue-hover` nos botões principais.
- `text-blue-700` (links e destaques) → `brand-blue`; `text-blue-800` e `group-hover` → `brand-blue-hover`.
- `sky-200/300` e `sky-400/x` (detalhes em fundo escuro) → `brand-cyan`.
- `to-cyan-500` no placeholder do artigo em destaque → `brand-cyan`.
- Texto azul sobre pílulas `bg-blue-100` foi escurecido para `brand-blue-hover`, para atingir AA (2563eb sobre dbeafe dava 4,24:1).

Preservados: cores dos logotipos, identidades de VetorOS, VetorSales e VetorLeads, cores semânticas (verde de sucesso e âmbar de alerta), `#1a0dab` / `#8ab4f8` da prévia do Google (intencional), e os tons de mockup internos de `visuals.tsx` (`#111a2e`, `#0e1626` etc.), que representam telas de produto. Os valores azuis desses mockups passaram a usar o token azul.

Não alterado: `text-sky-700` em `resources/js/data/site.ts` (tom escuro de céu, fora da paleta; decisão a confirmar).

## 3. Contraste (WCAG)

Calculado com a fórmula de luminância relativa:

| Par | Razão | Resultado |
|---|---|---|
| branco / `#2563eb` (botão) | 5,17:1 | AA |
| branco / `#1d4ed8` (hover) | 6,70:1 | AA |
| `#2563eb` / branco (link) | 5,17:1 | AA |
| `#2563eb` / `#f8fafc` | 4,94:1 | AA |
| `#475569` / branco | 7,58:1 | AA |
| `#475569` / `#f8fafc` | 7,24:1 | AA |
| `#0f172a` / branco | 17,85:1 | AA |
| `#38bdf8` / `#0f172a` | 8,33:1 | AA |
| `#cbd5e1` / `#0f172a` | 12,02:1 | AA |
| `#f8fafc` / `#0f172a` | 17,06:1 | AA |
| `#2563eb` / `#dbeafe` (pílula) | 4,24:1 | abaixo de AA, corrigido com `#1d4ed8` (5,49:1) |
| `#38bdf8` / branco | 2,14:1 | **não usado em texto sobre branco**; o ciano aparece só em fundos escuros |

O foco de teclado sobre fundo claro usa `#2563eb` (5,17:1). Sobre o carrossel escuro, o contorno de foco é ciano.

## 4. Verificação e limitações

- **Compilação do CSS:** `resources/css/app.css` compila com `@tailwindcss/node`, e os utilitários `bg-brand-*`, `text-brand-*`, `hover:bg-brand-blue-hover`, `from/via/to-brand-*` e `outline-brand-cyan` são gerados.
- **Build completo do Vite não concluiu.** Falha por `@/routes/*` ausente, arquivos gerados pelo Wayfinder (`php artisan wayfinder:generate`). O projeto não tem `vendor/` e não há Composer neste ambiente, então não foi possível gerar as rotas. Este erro existe independentemente das alterações de cor.
- **Prettier:** `app.css`, `blog/index.tsx` e `site/sistema.tsx` já estavam fora do formato no HEAD. Não foram reformatados, para manter o diff restrito à paleta.
- **Navegador e capturas:** não executados. Não há Chromium nem Playwright instalados, e a aplicação não sobe sem PHP com dependências. Por isso não há capturas desktop/celular nem conferência de todos os slides do carrossel. **Recomendado:** rodar `composer install`, `php artisan wayfinder:generate --with-form`, `npm run build` e revisar as páginas no navegador.
- Docker não foi usado, conforme o pedido. Nenhum container foi tocado.

## 5. Estado

- Alterações locais em `resources/css/app.css` e nos 21 arquivos listados. Nenhum commit foi feito.
- Nada foi publicado nem implantado. Produção não foi alterada.
- `correio.md` continua com alterações não commitadas, como estava no início.
