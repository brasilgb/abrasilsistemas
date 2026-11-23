# Estratégia editorial do blog

Objetivo: responder às dúvidas de quem está perto de contratar e levar o leitor à página do serviço.

## Como o artigo leva ao serviço

1. Links no texto para uma ou duas páginas de serviço, onde o assunto pede.
2. Chamada ao fim do artigo, escolhida pela categoria (`blogCategoryServices` em `resources/js/data/site.ts`), com "Conhecer o serviço" e "Solicitar orçamento".
3. "Páginas relacionadas" nos metadados do artigo. O painel (SEO → Palavras-chave) acusa artigo sem ligação com a página comercial da mesma intenção.

## Temas, categorias e destino

| Tema | Categoria | Serviço indicado |
|---|---|---|
| Desenvolvimento de software | `desenvolvimento-de-sistemas`, `tecnologia-e-inovacao` | Sistemas sob medida |
| Automação e integrações | `sistemas-e-automacao` | Automação de processos |
| Produtividade empresarial | `gestao-e-produtividade` | Automação de processos |
| Sites e presença digital | `sites-e-presenca-digital` | Sites empresariais |
| SEO | `seo-e-performance` | SEO técnico |
| Outras | — | Lista de serviços |

## Publicados no site em 23/11/2026 (pelo sitemap)

Sistema personalizado ou software pronto; quando a planilha deixa de ser suficiente; automação de tarefas repetitivas; clientes, pedidos e equipes de vendas; site que transforma visitas em contatos; e cinco de SEO. A base está coberta: **não** repetir esses assuntos.

## Próximas pautas (rascunhos prontos)

Em `database/data/blog-commercial-drafts.php`, criadas como rascunho por `php artisan blog:seo-drafts` (não duplica nem publica):

| Pauta | Momento do leitor | Destino |
|---|---|---|
| Quanto custa um sistema sob medida? | Pedindo orçamento | Sistemas sob medida |
| Como mapear um processo antes de automatizar | Entendendo o problema | Automação |
| Integração de sistemas: o que levantar antes do orçamento | Pedindo orçamento | Integrações e APIs |
| Landing page ou site institucional? | Comparando | Landing pages e sites |
| Aplicativo ou sistema web para a equipe de campo? | Comparando | Aplicativos e sistemas |

## Regras

- Uma pergunta real por artigo; se outro já responde, atualizar o existente.
- Nada de números, clientes ou resultados inventados, nem promessa de posição no Google.
- Publicar um por vez, após revisão. Sem conteúdo novo a dizer, não publicar.
- Divulgar com UTM (`?utm_source=linkedin&utm_medium=social&utm_campaign=nome`): a origem aparece na solicitação.
