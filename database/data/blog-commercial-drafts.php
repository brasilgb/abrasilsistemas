<?php

/*
 * Pautas comerciais do blog (ABRASIL-COMERCIAL-01), criadas como RASCUNHO por `php artisan blog:seo-drafts`.
 * Cada uma responde a uma dúvida de quem está perto de contratar e leva à página de serviço
 * correspondente (ver docs/estrategia-editorial.md). Sem números, clientes ou resultados inventados.
 */

return [
    [
        'slug' => 'quanto-custa-um-sistema-sob-medida',
        'title' => 'Quanto custa um sistema sob medida? O que realmente forma o preço',
        'excerpt' => 'Duas empresas pedem “um sistema de pedidos” e recebem propostas muito diferentes. Veja o que muda o valor e como pedir um orçamento comparável.',
        'focus_keyword' => 'quanto custa um sistema sob medida',
        'secondary_keywords' => ['orçamento de sistema', 'desenvolvimento de software'],
        'category' => 'sistemas-e-automacao',
        'search_intent' => 'desenvolvimento',
        'related_pages' => ['page:servico:desenvolvimento-de-sistemas'],
        'body' => <<<'HTML'
<p>Duas empresas pedem “um sistema de pedidos”. Uma quer registrar vendas de balcão em um computador. A outra precisa que vendedores em campo lancem pedidos no celular, com tabela de preço por cliente, limite de crédito e envio ao ERP. O nome do projeto é o mesmo; o trabalho, não. Por isso não existe tabela de preço honesta para software sob medida, mas existem fatores que qualquer empresa consegue avaliar antes de pedir uma proposta.</p>
<h2>O que mais pesa no valor</h2>
<h3>Regras de negócio e exceções</h3>
<p>Telas de cadastro são rápidas de construir. O tempo vai para as regras: quem aprova um desconto, o que acontece quando o pedido é alterado depois de faturado, como a comissão é dividida. Cada “depende” do processo vira código e teste.</p>
<h3>Integrações</h3>
<p>Conectar o sistema ao ERP, à loja virtual ou ao banco muda o projeto conforme o outro lado. Se há API documentada e ambiente de teste, o caminho é previsível. Se a única saída é um arquivo exportado à mão, parte do esforço será contornar essa limitação.</p>
<h3>Perfis de acesso</h3>
<p>Um sistema usado por três pessoas com a mesma função é bem diferente de outro em que vendedor, gerente, financeiro e cliente enxergam coisas distintas.</p>
<h3>Dados antigos</h3>
<p>Migrar cadastros de planilhas ou de um sistema anterior exige limpeza: duplicados, campos faltando, nomes escritos de formas diferentes. Quanto mais antiga a base, mais vale separar essa etapa na proposta.</p>
<h2>O que costuma ser esquecido</h2>
<ul>
<li>hospedagem, domínio e cópias de segurança;</li>
<li>treinamento de quem vai usar;</li>
<li>correções e evolução depois da entrega;</li>
<li>propriedade do código e dos dados, que deve estar no contrato.</li>
</ul>
<h2>Como pedir propostas comparáveis</h2>
<p>Envie a mesma descrição a todos os fornecedores: o problema, quem usa, quais sistemas participam, o que é indispensável na primeira entrega e o que pode esperar. Peça que a proposta separe etapas e diga o que <strong>não</strong> está incluído. Uma proposta barata sem essa lista costuma ficar cara no primeiro pedido de mudança.</p>
<h2>Comece pelo módulo que dói mais</h2>
<p>Dividir o projeto reduz o risco: a primeira entrega resolve a maior perda e mostra, com o uso real, o que faz sentido construir depois.</p>
<p>Na ABrasil Sistemas, o <a href="/desenvolvimento-de-sistemas">desenvolvimento de sistemas sob medida</a> começa por um diagnóstico do processo, e a proposta sai com etapas, entregas e prazos por escrito.</p>
HTML,
    ],
    [
        'slug' => 'como-mapear-um-processo-antes-de-automatizar',
        'title' => 'Como mapear um processo antes de automatizar',
        'excerpt' => 'Automatizar um processo confuso só faz a confusão andar mais rápido. Um roteiro de uma tarde para enxergar o que vale automatizar.',
        'focus_keyword' => 'mapear processo',
        'secondary_keywords' => ['mapeamento de processos', 'automação empresarial'],
        'category' => 'gestao-e-produtividade',
        'search_intent' => 'automacao',
        'related_pages' => ['page:servico:automacao-de-processos'],
        'body' => <<<'HTML'
<p>Automatizar um processo confuso só faz a confusão andar mais rápido. Antes de escolher ferramenta, vale uma tarde com papel e as pessoas que executam a rotina. O objetivo não é um diagrama bonito: é descobrir onde a informação para, se repete ou se perde.</p>
<h2>1. Escolha um processo com começo e fim</h2>
<p>“O financeiro” é um departamento. “Da emissão da nota até a baixa do pagamento” é um processo. Delimite o evento que inicia e o resultado que encerra.</p>
<h2>2. Pergunte a quem faz</h2>
<p>O procedimento escrito raramente é o praticado. Peça à pessoa que execute um caso real e anote cada passo, inclusive os que ela faz “só por garantia”. Planilhas paralelas e mensagens para confirmar dados são sinais de que o sistema oficial não resolve.</p>
<h2>3. Para cada passo, registre quatro coisas</h2>
<ul>
<li><strong>Entrada:</strong> que informação chega e de onde;</li>
<li><strong>Decisão:</strong> existe uma regra ou depende de julgamento?</li>
<li><strong>Saída:</strong> o que é gerado e para quem vai;</li>
<li><strong>Espera:</strong> quanto tempo fica parado até alguém agir.</li>
</ul>
<h2>4. Separe o que é regra do que é julgamento</h2>
<p>Passos com regra clara são candidatos a automação: enviar lembrete três dias antes do vencimento, criar a tarefa quando o pedido é aprovado, copiar o cadastro de um sistema para outro. Passos de julgamento, como negociar um prazo com um cliente antigo, devem continuar com pessoas, mas recebendo a informação pronta.</p>
<h2>5. Corte antes de automatizar</h2>
<p>Alguns passos existem por causa de um problema que já não existe. Se ninguém sabe dizer o que acontece quando o passo é pulado, teste sem ele.</p>
<h2>6. Defina como saber se funcionou</h2>
<p>Escolha uma medida simples do antes: quantas vezes por semana a tarefa acontece, quanto tempo toma, quantos erros voltam. Sem isso, não há como decidir se o próximo processo merece o mesmo investimento.</p>
<p>Com esse mapa, a conversa sobre <a href="/automacao-de-processos">automação de processos</a> fica objetiva: quais passos, com quais regras e entre quais sistemas. Quando o gargalo está na troca de dados, o caminho é a <a href="/integracao-de-sistemas-e-apis">integração de sistemas</a>.</p>
HTML,
    ],
    [
        'slug' => 'integracao-de-sistemas-o-que-levantar-antes-do-orcamento',
        'title' => 'Integração de sistemas: o que levantar antes de pedir um orçamento',
        'excerpt' => 'Seis respostas que evitam surpresas em um projeto de integração, mesmo para quem não é da área técnica.',
        'focus_keyword' => 'integração de sistemas',
        'secondary_keywords' => ['integração por API', 'webhook'],
        'category' => 'sistemas-e-automacao',
        'search_intent' => 'automacao',
        'related_pages' => ['page:servico:integracao-de-sistemas-e-apis'],
        'body' => <<<'HTML'
<p>“Quero integrar a loja com o ERP” parece um pedido completo, mas deixa em aberto quase tudo o que define o prazo. As perguntas abaixo podem ser respondidas por quem conhece a operação, sem conhecimento técnico, e deixam a proposta muito mais precisa.</p>
<h2>1. Que informação precisa circular?</h2>
<p>Liste os dados, não os sistemas: pedidos, estoque, preços, clientes, status de entrega. Cada um pode ter um caminho e uma frequência diferentes.</p>
<h2>2. Quem é o dono de cada dado?</h2>
<p>Se o preço pode ser alterado na loja e no ERP, qual vale? Toda integração precisa de um sistema de referência por informação. Sem essa decisão, um lado apaga o que o outro acabou de gravar.</p>
<h2>3. Com que rapidez?</h2>
<p>Estoque de um item muito procurado pede atualização quase imediata. Um relatório de vendas pode ser enviado uma vez por dia. Nem tudo precisa ser em tempo real, e o que não precisa fica mais simples.</p>
<h2>4. Como cada sistema se comunica?</h2>
<p>Pergunte ao fornecedor de cada ferramenta:</p>
<ul>
<li>há API? O seu plano dá acesso a ela?</li>
<li>há webhooks, isto é, avisos automáticos quando algo muda?</li>
<li>há ambiente de testes?</li>
<li>existe limite de chamadas?</li>
</ul>
<p>É comum a API existir, mas estar disponível só em um plano superior. Melhor descobrir antes.</p>
<h2>5. O que acontece quando falha?</h2>
<p>Sistemas saem do ar. Uma integração bem-feita tenta de novo, não duplica o pedido e avisa alguém quando desiste. Defina quem recebe esse aviso.</p>
<h2>6. Os cadastros batem?</h2>
<p>O mesmo produto tem o mesmo código nos dois lados? O cliente é identificado por CNPJ, e-mail ou telefone? Divergência de cadastro é um problema frequente e pode ser corrigida antes de a primeira linha de código ser escrita.</p>
<h2>Com as respostas em mãos</h2>
<p>O projeto de <a href="/integracao-de-sistemas-e-apis">integração de sistemas e APIs</a> pode ser dividido em fluxos independentes, começando pelo que mais gera redigitação.</p>
HTML,
    ],
    [
        'slug' => 'landing-page-ou-site-qual-escolher',
        'title' => 'Landing page ou site institucional: qual a sua empresa precisa agora?',
        'excerpt' => 'A escolha depende de onde vem o visitante e do que ele precisa decidir. Um critério simples para não pagar pela página errada.',
        'focus_keyword' => 'landing page ou site',
        'secondary_keywords' => ['site institucional', 'página de campanha'],
        'category' => 'sites-e-presenca-digital',
        'search_intent' => 'web_mobile',
        'related_pages' => ['page:servico:criacao-de-landing-pages', 'page:servico:desenvolvimento-de-sites-para-empresas'],
        'body' => <<<'HTML'
<p>As duas são páginas na internet, mas respondem a momentos diferentes. O site atende quem quer conhecer a empresa. A landing page atende quem chegou por uma oferta específica e precisa decidir sobre ela.</p>
<h2>Comece pela origem do visitante</h2>
<p><strong>Se a pessoa pesquisou pelo nome ou pelo tipo de serviço</strong>, ela quer comparar: quem são vocês, o que fazem, onde atendem, como falar. Isso é papel do site, com uma página por serviço.</p>
<p><strong>Se a pessoa clicou em um anúncio</strong>, ela já viu uma promessa. Levá-la à página inicial obriga a procurar de novo o que o anúncio mostrou. Uma landing page continua a conversa do ponto em que o anúncio parou.</p>
<h2>Quando o site vem primeiro</h2>
<ul>
<li>a empresa ainda não tem presença própria, só redes sociais;</li>
<li>a venda depende de confiança e envolve mais de uma conversa;</li>
<li>há vários serviços, para públicos diferentes;</li>
<li>o objetivo é ser encontrado nas buscas ao longo do tempo.</li>
</ul>
<h2>Quando a landing page resolve</h2>
<ul>
<li>há uma campanha com data para começar;</li>
<li>é preciso testar uma oferta antes de investir mais;</li>
<li>o público de uma ação não é o público geral da empresa;</li>
<li>é importante saber quantos contatos cada anúncio gerou.</li>
</ul>
<h2>O que as duas precisam ter</h2>
<p>Carregar rápido no celular, dizer em uma frase o que é oferecido, pedir só os dados necessários e entregar o contato a alguém que vá responder. Um formulário que cai em uma caixa de e-mail esquecida desperdiça o investimento em qualquer das duas.</p>
<h2>E quando usar as duas</h2>
<p>É o arranjo mais comum: o site sustenta a reputação e as buscas; as landing pages entram e saem conforme as campanhas, marcando a origem de cada contato pelos parâmetros UTM do link.</p>
<p>Conheça como trabalhamos a <a href="/criacao-de-landing-pages">criação de landing pages</a> e o <a href="/desenvolvimento-de-sites-para-empresas">desenvolvimento de sites para empresas</a>.</p>
HTML,
    ],
    [
        'slug' => 'aplicativo-ou-sistema-web-para-equipe-de-campo',
        'title' => 'Aplicativo ou sistema web: do que a sua equipe de campo precisa?',
        'excerpt' => 'Nem toda equipe externa precisa de um aplicativo na loja. Quatro perguntas ajudam a decidir antes de investir.',
        'focus_keyword' => 'aplicativo ou sistema web',
        'secondary_keywords' => ['aplicativo para equipe de campo', 'sistema responsivo'],
        'category' => 'tecnologia-e-inovacao',
        'search_intent' => 'web_mobile',
        'related_pages' => ['page:servico:desenvolvimento-de-aplicativos', 'page:servico:desenvolvimento-de-sistemas'],
        'body' => <<<'HTML'
<p>Quando a equipe trabalha na rua, a primeira ideia costuma ser “precisamos de um aplicativo”. Às vezes é isso mesmo. Em outras, um sistema web bem adaptado ao celular resolve com menos custo de manutenção. A diferença está nas condições de uso.</p>
<h2>1. Há internet onde o trabalho acontece?</h2>
<p>Se o técnico atende em subsolo ou o vendedor visita áreas sem sinal, o registro precisa funcionar desconectado e sincronizar depois. Esse é o argumento mais forte a favor do aplicativo. Se a conexão é estável, o navegador atende.</p>
<h2>2. O celular precisa fazer algo além de mostrar telas?</h2>
<p>Receber avisos com a tela bloqueada, ler código de barras em sequência, registrar localização durante o trajeto: recursos assim funcionam melhor em um aplicativo. Tirar uma foto ou anexar um documento o navegador já faz.</p>
<h2>3. Quem usa e com que frequência?</h2>
<p>Uma equipe que abre a ferramenta dezenas de vezes por dia ganha com a agilidade do aplicativo. Um cliente que consulta o andamento do serviço uma vez por mês dificilmente vai instalar algo; um link resolve.</p>
<h2>4. Quem mantém?</h2>
<p>Aplicativo publicado tem rotina própria: contas nas lojas, aprovação de versões, aparelhos antigos, atualização de sistema operacional. O sistema web muda para todos ao mesmo tempo.</p>
<h2>O que não muda: a base</h2>
<p>Nos dois casos, os dados precisam chegar a um sistema central por uma API segura. Por isso é possível começar pelo sistema web e acrescentar o aplicativo quando o uso real mostrar a necessidade, sem refazer o que já existe.</p>
<h2>Um caminho prático</h2>
<ol>
<li>acompanhe um dia de trabalho em campo;</li>
<li>anote o que é feito no papel ou lembrado de cabeça;</li>
<li>marque onde não há sinal;</li>
<li>decida pelas condições encontradas, não pela tecnologia.</li>
</ol>
<p>Veja quando indicamos o <a href="/desenvolvimento-de-aplicativos">desenvolvimento de aplicativos</a> e quando um <a href="/desenvolvimento-de-sistemas">sistema sob medida</a> no navegador é suficiente.</p>
HTML,
    ],
];
