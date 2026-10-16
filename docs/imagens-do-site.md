# Imagens do site institucional

Fotos dos heroes do site (ABRASIL-WEB-03). Origem, licença e onde cada uma é usada.

## Licença

Todas as fotos vêm do **Unsplash** e estão sob a [Licença Unsplash](https://unsplash.com/license):
uso comercial e não comercial gratuito, sem pedir permissão e sem atribuição obrigatória.
A licença proíbe vender a foto sem alteração e usá-la para montar um serviço concorrente ao
Unsplash. Nenhuma das fotos é do **Unsplash+** (conteúdo pago, com outra licença): esse
conteúdo foi filtrado na busca.

A atribuição abaixo é um registro interno. A licença não obriga a exibir créditos no site.

## Fotos

| Chave | Página | Autor | Foto original | Original |
|---|---|---|---|---|
| `home` | Home | Austin Distel | [mpN7xjKQ_Ns](https://unsplash.com/photos/three-men-sitting-on-chair-beside-tables-mpN7xjKQ_Ns) | 5300×3975 |
| `sistemas` | Sistemas prontos | Gabriel Weyand | [pPpUCaudT5Q](https://unsplash.com/photos/person-working-on-a-laptop-at-a-desk-pPpUCaudT5Q) | 7008×4672 |
| `sistemas-sob-medida` | Sistemas sob medida | Compagnons | [Im_cQ6hQo10](https://unsplash.com/photos/people-coding-at-monitors-in-workspace-Im_cQ6hQo10) | 5184×3456 |
| `automacao` | Automação de processos | TheStandingDesk | [pn6tf73O2As](https://unsplash.com/photos/man-in-blue-polo-shirt-using-laptop-computer-pn6tf73O2As) | 6720×3776 |
| `integracoes` | Integrações e APIs | Albert Stoynov | [yhJVLxcquEY](https://unsplash.com/photos/yellow-and-green-cables-are-neatly-connected-yhJVLxcquEY) | 5634×4024 |
| `aplicativos` | Desenvolvimento de aplicativos | Balázs Kétyi | [sScmok4Iq1o](https://unsplash.com/photos/smartphone-displaying-environmental-data-dashboard-sScmok4Iq1o) | 4843×3632 |
| `sites` | Sites empresariais | The Connected Narrative | [aEv8ednzJ2E](https://unsplash.com/photos/a-person-working-on-a-laptop-aEv8ednzJ2E) | 6016×4016 |
| `landing-pages` | Landing pages | Carlos Muza | [hpjSkU2UYSU](https://unsplash.com/photos/laptop-computer-on-glass-top-table-hpjSkU2UYSU) | 2426×1728 |
| `vetoros` | VetorOS | uBreakifix North - Las Vegas | [_AIT3BLFIag](https://unsplash.com/photos/man-in-black-t-shirt-using-computer-_AIT3BLFIag) | 5041×3780 |
| `vetorpet` | VetorPet | Ayla Verschueren | [jnq52JjkLAg](https://unsplash.com/photos/a-brown-and-white-dog-sitting-next-to-a-bowl-of-food-jnq52JjkLAg) | 4608×3072 |
| `sobre` | Sobre | Vitaly Gariev | [tp_SwN8pC0A](https://unsplash.com/photos/two-colleagues-collaborating-on-a-laptop-at-a-desk-tp_SwN8pC0A) | 3840×2160 |
| `contato` | Contato | Vitaly Gariev | [L0rCfOSyUlQ](https://unsplash.com/photos/man-talking-on-phone-in-workspace-L0rCfOSyUlQ) | 3840×2160 |

Capturas reais de produto: `public/images/dashboard-vetoros.webp` (painel do VetorOS, já usado
desde a ABRASIL-WEB-01) continua na home, em Sistemas prontos e na página do VetorOS.

## Arquivos

`public/images/site/heroes/`:

- `{chave}-{640|1280|1920}.avif` e `.webp`: recorte 16:9, para o `srcset` do hero. O navegador
  escolhe AVIF quando suporta e a largura pela tela (640 no celular).
- `{chave}-og.jpg`: 1200×630, usado como `og:image` (JPG porque nem toda rede social lê AVIF/WebP).

Texto alternativo: `App\Support\PublicPages::HERO_IMAGES` (servidor, `og:image:alt`) e
`resources/js/data/hero-images.ts` (`alt` da imagem). Um teste garante que os dois coincidam.

## Como trocar uma foto

1. Baixar a foto nova (licença comercial; registrar autor e link nesta tabela).
2. Gerar as 7 variantes com os mesmos nomes (`{chave}-640/1280/1920.avif|webp` e `{chave}-og.jpg`).
   Referência: WebP qualidade ~68, AVIF ~48, OG em JPG ~78. Abaixo de 250 KB por arquivo (há teste).
3. Atualizar o `alt` nos dois lugares acima e, se o enquadramento mudar, o `position` em
   `hero-images.ts`.

## Pendências

- **VetorPet sem captura real de tela.** Não há print do sistema no repositório (nem no projeto
  `vetorpet`). A página do produto usa só a foto do segmento; nenhuma tela foi inventada. Quando
  houver um print real, ele entra como `screenshot` do produto em `resources/js/data/site.ts`,
  igual ao VetorOS.
- Marcas pequenas e ilegíveis sob o véu navy: uma lata sobre a mesa (`home`) e um logotipo branco
  na manga da camiseta (`vetoros`). Não aparecem de forma reconhecível no site; se incomodarem, a
  troca é só seguir "Como trocar uma foto".
