<?php

/*
 * Pautas de SEO do blog (ABRASIL-SEO-FINAL-01), criadas como RASCUNHO pelo comando
 * `php artisan blog:seo-drafts`. Nada aqui é publicado automaticamente: a equipe revisa,
 * ajusta e publica pelo painel. Regra editorial: sem números, clientes ou resultados
 * inventados e sem promessa de posição no Google.
 */

$seoPage = '/seo-tecnico-e-otimizacao-de-sites';

return [
    [
        'slug' => 'por-que-sua-empresa-precisa-de-seo-tecnico',
        'title' => 'Por que sua empresa precisa de SEO técnico?',
        'excerpt' => 'Antes de qualquer conteúdo, o buscador precisa conseguir ler, entender e confiar no site. É isso que o SEO técnico resolve.',
        'focus_keyword' => 'SEO técnico',
        'secondary_keywords' => ['SEO para empresas', 'indexação de sites'],
        'body' => <<<HTML
<p>Muitas empresas investem em um site bonito e em bons textos e, mesmo assim, não aparecem quando alguém pesquisa pelo serviço que oferecem. Na maior parte das vezes, o problema não está no conteúdo, e sim na base técnica: o buscador não consegue ler o site direito, não entende qual página é a principal ou nem sabe que certas páginas existem.</p>
<h2>O que é SEO técnico</h2>
<p>SEO técnico é o conjunto de ajustes que permite aos mecanismos de busca rastrear, interpretar e indexar as páginas de um site. Ele não substitui um bom conteúdo, mas é a condição para que esse conteúdo seja encontrado.</p>
<p>Na prática, envolve itens como:</p>
<ul>
<li>títulos e descrições únicos para cada página;</li>
<li>endereços (URLs) claros e estáveis;</li>
<li>sitemap XML atualizado e um robots.txt correto;</li>
<li>indicação da página canônica quando há conteúdo parecido;</li>
<li>dados estruturados que descrevem a empresa e os serviços;</li>
<li>carregamento rápido, principalmente no celular;</li>
<li>redirecionamentos corretos quando uma página muda de endereço.</li>
</ul>
<h2>Por que isso importa para uma empresa</h2>
<p>Quem pesquisa no Google por um serviço normalmente já tem a necessidade. Se o site da empresa não aparece, essa pessoa encontra o concorrente. O SEO técnico não garante a primeira posição (nenhum trabalho sério garante), mas remove os obstáculos que impedem o site de disputar essas buscas.</p>
<h3>Erros comuns que passam despercebidos</h3>
<ul>
<li>todas as páginas com o mesmo título;</li>
<li>páginas importantes bloqueadas por engano para os buscadores;</li>
<li>áreas internas, como painéis e áreas de cliente, abertas à indexação;</li>
<li>links antigos que levam a páginas de erro depois de uma reformulação do site;</li>
<li>imagens pesadas que deixam o site lento no celular.</li>
</ul>
<h2>Por onde começar</h2>
<p>O primeiro passo é uma auditoria: verificar o que o buscador enxerga hoje, quais páginas estão indexadas, o que está bloqueado e o que está lento. Com isso, as correções são priorizadas pelo impacto.</p>
<p>Na ABrasil Sistemas, todo site novo já sai com <a href="/desenvolvimento-de-sites-para-empresas">SEO técnico incluído</a>. Para sites que já estão no ar, oferecemos o serviço de <a href="{$seoPage}">SEO técnico e otimização de sites</a>, com auditoria, correções e acompanhamento.</p>
HTML,
    ],
    [
        'slug' => 'como-aparecer-nas-pesquisas-do-google',
        'title' => 'Como aparecer nas pesquisas do Google?',
        'excerpt' => 'Aparecer no Google depende de três coisas: o site ser encontrado, ser entendido e responder bem ao que a pessoa procura.',
        'focus_keyword' => 'aparecer no Google',
        'secondary_keywords' => ['Google Search Console', 'indexação'],
        'body' => <<<HTML
<p>“Como faço minha empresa aparecer no Google?” é uma das perguntas mais comuns de quem tem um site. A resposta curta é: o site precisa ser encontrado, ser entendido e ser útil para quem pesquisa. Nenhum desses passos é instantâneo, mas todos dependem de decisões que a empresa controla.</p>
<h2>1. O Google precisa encontrar o site</h2>
<p>O buscador descobre páginas seguindo links e lendo o sitemap do site. Se a página não tem nenhum link apontando para ela, está fora do sitemap ou foi bloqueada no robots.txt, ela pode simplesmente não ser descoberta.</p>
<p>A ferramenta oficial para acompanhar isso é o <strong>Google Search Console</strong>. Nele é possível enviar o sitemap, ver quais páginas foram indexadas e entender por que outras ficaram de fora.</p>
<h2>2. O Google precisa entender o site</h2>
<p>Depois de encontrar a página, o buscador tenta entender do que ela trata. Ajudam nisso:</p>
<ul>
<li>um título que descreve a página com as palavras que o cliente usa;</li>
<li>uma estrutura de subtítulos organizada;</li>
<li>textos que explicam o serviço de forma concreta;</li>
<li>dados estruturados que informam quem é a empresa e o que ela oferece.</li>
</ul>
<h2>3. A página precisa responder bem à pesquisa</h2>
<p>Entre várias páginas que tratam do mesmo assunto, o Google tenta mostrar as que respondem melhor. Isso passa por conteúdo claro, site rápido, boa experiência no celular e informações confiáveis sobre a empresa.</p>
<h3>E o Perfil da Empresa no Google?</h3>
<p>Para negócios com atendimento local, o Perfil da Empresa no Google (o antigo Google Meu Negócio) é complementar ao site e aparece no mapa e nas buscas locais. Os dois funcionam melhor juntos.</p>
<h2>Quanto tempo leva?</h2>
<p>Correções técnicas costumam ser percebidas em algumas semanas, conforme o Google volta a rastrear as páginas. O crescimento das visitas orgânicas depende da concorrência e do conteúdo, e normalmente acontece ao longo de meses. Desconfie de quem promete posição garantida.</p>
<p>Se o seu site já existe e não aparece, uma <a href="{$seoPage}">análise de SEO</a> mostra o que está impedindo. Se ainda não tem site, todos os <a href="/desenvolvimento-de-sites-para-empresas">sites que desenvolvemos</a> já saem preparados para os buscadores.</p>
HTML,
    ],
    [
        'slug' => 'seo-para-sites-empresariais-o-que-realmente-importa',
        'title' => 'SEO para sites empresariais: o que realmente importa?',
        'excerpt' => 'Entre tantas dicas de SEO, poucas fazem diferença para o site de uma empresa. Veja onde concentrar o esforço.',
        'focus_keyword' => 'SEO para sites empresariais',
        'secondary_keywords' => ['site institucional', 'palavras-chave'],
        'body' => <<<HTML
<p>Quem começa a pesquisar sobre SEO encontra listas enormes de recomendações, muitas delas contraditórias. Para um site empresarial, que apresenta serviços e quer gerar contatos, o que faz diferença é um conjunto bem menor de cuidados.</p>
<h2>Uma página para cada serviço importante</h2>
<p>Quando todos os serviços estão resumidos em uma única página, fica difícil o buscador associar o site a cada um deles. Páginas próprias, com título, descrição e texto específicos, permitem que a empresa apareça para pesquisas diferentes.</p>
<h2>Escrever com as palavras do cliente</h2>
<p>O cliente pesquisa pelo problema, não pelo nome técnico da solução. Vale entender como ele descreve a necessidade e usar esses termos nos títulos e textos, sem repetição forçada. Uma mesma palavra-chave mirada por várias páginas faz as páginas competirem entre si.</p>
<h2>Base técnica correta</h2>
<ul>
<li>títulos e descrições únicos;</li>
<li>sitemap e robots.txt configurados;</li>
<li>canonical nas páginas com conteúdo parecido;</li>
<li>dados estruturados da organização e dos serviços;</li>
<li>HTTPS e redirecionamentos corretos.</li>
</ul>
<h2>Velocidade e celular</h2>
<p>Grande parte das visitas chega pelo celular. Imagens otimizadas, poucos scripts de terceiros e um layout que funciona em telas pequenas melhoram a experiência e os indicadores que o Google observa.</p>
<h2>Conteúdo que gera confiança</h2>
<p>Explicar como a empresa trabalha, mostrar projetos reais, responder dúvidas frequentes e manter um blog com conteúdo útil ajudam o visitante a decidir e mostram ao buscador que o site é uma fonte confiável.</p>
<h2>Medir com dados oficiais</h2>
<p>O Search Console mostra as pesquisas que levam ao site e o Google Analytics, o que o visitante faz depois que chega. Sem esses dados, as decisões ficam no achismo.</p>
<p>Na ABrasil Sistemas, esses cuidados fazem parte de todo <a href="/desenvolvimento-de-sites-para-empresas">site empresarial</a> que desenvolvemos. Para sites existentes, o serviço de <a href="{$seoPage}">SEO profissional</a> cobre auditoria, correções e acompanhamento.</p>
HTML,
    ],
    [
        'slug' => 'como-melhorar-o-desempenho-e-a-indexacao-de-um-site',
        'title' => 'Como melhorar o desempenho e a indexação de um site?',
        'excerpt' => 'Um site lento ou com páginas fora do índice perde visitas. Veja como diagnosticar e corrigir os dois problemas.',
        'focus_keyword' => 'desempenho e indexação de site',
        'secondary_keywords' => ['Core Web Vitals', 'sitemap XML'],
        'body' => <<<HTML
<p>Desempenho e indexação são dois problemas diferentes, mas costumam aparecer juntos. Um site lento afasta o visitante; um site com páginas fora do índice nem chega a recebê-lo. A boa notícia é que os dois podem ser diagnosticados com ferramentas gratuitas e oficiais.</p>
<h2>Diagnóstico de indexação</h2>
<p>No Google Search Console, o relatório de indexação de páginas mostra quais endereços estão no índice e o motivo de cada exclusão. Os casos mais comuns são:</p>
<ul>
<li><strong>bloqueada pelo robots.txt</strong>: a página foi impedida de ser rastreada;</li>
<li><strong>marcada como noindex</strong>: a própria página pede para ficar fora do índice;</li>
<li><strong>cópia sem canônica selecionada</strong>: conteúdo parecido com outra página;</li>
<li><strong>não encontrada (404)</strong>: links apontando para páginas que não existem mais.</li>
</ul>
<p>Nem toda exclusão é um erro. Áreas de login, painéis e páginas de busca interna devem ficar fora do índice. O importante é que as páginas comerciais estejam dentro.</p>
<h2>Corrigindo a indexação</h2>
<ul>
<li>mantenha o sitemap XML apenas com as páginas que devem ser indexadas;</li>
<li>revise o robots.txt e as diretivas noindex;</li>
<li>use redirecionamento 301 quando uma página mudar de endereço;</li>
<li>indique a página canônica quando houver versões parecidas;</li>
<li>crie links internos para as páginas importantes.</li>
</ul>
<h2>Diagnóstico de desempenho</h2>
<p>O PageSpeed Insights e o relatório de Core Web Vitals do Search Console medem como o site se comporta para usuários reais: tempo para exibir o conteúdo principal, estabilidade do layout e resposta às interações.</p>
<h3>Correções mais frequentes</h3>
<ul>
<li>imagens em formatos modernos (WebP ou AVIF) e no tamanho certo para cada tela;</li>
<li>carregamento prioritário apenas para a imagem principal;</li>
<li>menos scripts de terceiros, carregados só quando necessários;</li>
<li>dimensões definidas para imagens, evitando que o layout “pule”;</li>
<li>cache e compressão configurados no servidor.</li>
</ul>
<h2>Acompanhe depois de corrigir</h2>
<p>Depois das correções, é possível pedir uma nova validação no Search Console e acompanhar a evolução. Mudanças no site, como um redesenho ou uma troca de plataforma, merecem uma nova verificação.</p>
<p>Se preferir que alguém faça esse trabalho, conheça nosso serviço de <a href="{$seoPage}">SEO técnico e performance</a>. Para sistemas com áreas públicas, também cuidamos disso no <a href="/desenvolvimento-de-sistemas">desenvolvimento sob medida</a>.</p>
HTML,
    ],
    [
        'slug' => 'qual-a-diferenca-entre-seo-tecnico-e-anuncios-pagos',
        'title' => 'Qual a diferença entre SEO técnico e anúncios pagos?',
        'excerpt' => 'Anúncios trazem visitas enquanto há verba; SEO constrói presença orgânica. Entenda quando usar cada um.',
        'focus_keyword' => 'SEO ou anúncios pagos',
        'secondary_keywords' => ['Google Ads', 'tráfego orgânico'],
        'body' => <<<HTML
<p>Quem quer atrair clientes pela internet geralmente se depara com duas opções: investir em anúncios pagos, como o Google Ads, ou trabalhar o SEO para aparecer nos resultados orgânicos. Não são caminhos excludentes. Cada um tem uma função diferente.</p>
<h2>Como funcionam os anúncios pagos</h2>
<p>Nos anúncios, a empresa paga para aparecer em posições reservadas da página de resultados, normalmente por clique. A vantagem é a velocidade: a campanha pode estar no ar no mesmo dia, com controle de orçamento e de público.</p>
<p>A limitação é que as visitas dependem da verba. Quando a campanha para, os anúncios deixam de aparecer.</p>
<h2>Como funciona o SEO</h2>
<p>O SEO trabalha para que as páginas do site apareçam nos resultados orgânicos, que não são pagos. Isso envolve a base técnica do site, o desempenho e o conteúdo. O resultado leva mais tempo para aparecer, mas não há custo por clique: uma página bem posicionada continua recebendo visitas.</p>
<h2>Comparando lado a lado</h2>
<ul>
<li><strong>Tempo</strong>: anúncios são imediatos; SEO costuma levar meses para mostrar crescimento consistente.</li>
<li><strong>Custo</strong>: anúncios cobram por clique; SEO exige investimento em técnica e conteúdo, sem pagamento por visita.</li>
<li><strong>Continuidade</strong>: anúncios param com a verba; o resultado orgânico tende a se manter enquanto o site é cuidado.</li>
<li><strong>Controle</strong>: nos anúncios, a empresa escolhe quando aparecer; no orgânico, quem decide o ranqueamento é o buscador.</li>
</ul>
<h2>Quando usar cada um</h2>
<p>Anúncios fazem sentido para lançamentos, campanhas com prazo e para testar uma oferta rapidamente, de preferência com uma <a href="/criacao-de-landing-pages">landing page</a> dedicada. O SEO faz sentido para quem quer um canal permanente de contatos, sobretudo para serviços que as pessoas pesquisam com frequência.</p>
<p>As duas estratégias se complementam: os dados das campanhas mostram quais termos convertem, e esses termos orientam o conteúdo do site.</p>
<h2>E o SEO técnico nisso?</h2>
<p>Mesmo quem investe só em anúncios se beneficia de um site rápido e bem estruturado: a página carrega antes de o visitante desistir e a experiência é melhor. Por isso, todo projeto web da ABrasil já inclui SEO técnico. Para sites existentes, veja o serviço de <a href="{$seoPage}">SEO técnico e otimização de sites</a>.</p>
HTML,
    ],
];
