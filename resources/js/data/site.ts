/**
 * Conteúdo institucional do site público (abrasilsistemas.com.br).
 *
 * Fonte única de serviços, produtos, processo e tecnologias. Para adicionar um
 * serviço ou produto: inclua a entrada aqui e o slug/SEO correspondente em
 * app/Support/PublicPages.php (rota e sitemap saem de lá).
 *
 * Regra editorial: nada de números, clientes ou resultados inventados. Primeiro o
 * problema e o resultado para a empresa; depois a tecnologia.
 */

import type { HeroImageKey } from '@/data/hero-images';

export type VisualKind =
    'system' | 'site' | 'landing' | 'app' | 'automation' | 'integration';

export type Service = {
    slug: string;
    /** Rótulo curto em menus e cards. */
    label: string;
    /** Uma frase para cards da home e da página de soluções. */
    summary: string;
    /** Valor gravado em `leads.product` quando o visitante vem desta página. */
    contactType: string;
    visual: VisualKind;
    /** Foto do hero (ver data/hero-images.ts). */
    heroImage: HeroImageKey;
    hero: { eyebrow: string; title: string; lead: string };
    /** Situações em que o serviço faz sentido — a dor da empresa. */
    problems: { title: string; items: string[] };
    /** O que entregamos, em linguagem de resultado. */
    offerings: { title: string; description: string }[];
    /** O que muda para a empresa. */
    outcomes: string[];
    /** Fluxos práticos exibidos como diagrama. */
    flows?: { title: string; steps: string[] }[];
    /** Etapas específicas do serviço (quando diferem do processo geral). */
    steps?: { title: string; description: string }[];
    faq: { question: string; answer: string }[];
    related: string[];
};

export type Product = {
    slug: string;
    name: string;
    segment: string;
    tagline: string;
    logo: string;
    logoClass: string;
    /** Cor de destaque do produto (apenas detalhes; o site mantém a paleta institucional). */
    accent: string;
    problem: string;
    pains: string[];
    features: { title: string; description: string }[];
    outcomes: string[];
    platforms: string[];
    deployment: string;
    integrations: string[];
    screenshot?: { src: string; alt: string; width: number; height: number };
    /** Foto do segmento no hero (ver data/hero-images.ts). */
    heroImage: HeroImageKey;
    externalUrl: string;
    contactType: string;
    faq: { question: string; answer: string }[];
};

/** Formato previsto para cases: Problema → Solução → Tecnologia → Resultado. */
export type CaseStudy = {
    slug: string;
    title: string;
    segment: string;
    problem: string;
    solution: string;
    technology: string[];
    /** Só resultados verificáveis e autorizados pelo cliente. */
    result: string;
    image?: string;
};

export const services: Service[] = [
    {
        slug: 'desenvolvimento-de-sistemas',
        label: 'Sistemas sob medida',
        summary:
            'Software construído em cima do processo real da empresa, quando uma ferramenta pronta não atende.',
        contactType: 'sistema_sob_medida',
        visual: 'system',
        heroImage: 'sistemas-sob-medida',
        hero: {
            eyebrow: 'Desenvolvimento de sistemas sob medida',
            title: 'O sistema certo para o jeito que a sua empresa trabalha.',
            lead: 'Quando a operação depende de planilhas, controles paralelos ou de um software que não acompanha o negócio, desenvolvemos um sistema que organiza o processo de ponta a ponta — e evolui junto com a empresa.',
        },
        problems: {
            title: 'Quando faz sentido desenvolver um sistema',
            items: [
                'A operação depende de planilhas que só uma pessoa entende',
                'Processos manuais consomem horas da equipe toda semana',
                'Informações ficam espalhadas entre e-mail, WhatsApp e cadernos',
                'O software atual não atende e as adaptações viraram gambiarras',
                'Os sistemas da empresa não conversam entre si',
                'Falta visão consolidada para decidir com segurança',
            ],
        },
        offerings: [
            {
                title: 'Sistemas administrativos e operacionais',
                description:
                    'Cadastros, fluxos de aprovação, ordens, agendas e controles que hoje estão em planilhas.',
            },
            {
                title: 'ERP e CRM específicos',
                description:
                    'Gestão comercial, atendimento e retaguarda moldados ao processo da empresa, sem módulos que ninguém usa.',
            },
            {
                title: 'Portais internos e de clientes',
                description:
                    'Áreas restritas para equipe, parceiros ou clientes acompanharem pedidos, documentos e status.',
            },
            {
                title: 'Dashboards operacionais',
                description:
                    'Indicadores calculados a partir dos dados reais da operação, atualizados sem montar relatório à mão.',
            },
            {
                title: 'Plataformas SaaS',
                description:
                    'Produtos digitais multiempresa, com assinatura, permissões e isolamento de dados — como os nossos próprios sistemas.',
            },
            {
                title: 'Modernização de sistemas legados',
                description:
                    'Substituição gradual de sistemas antigos, preservando dados e sem parar a operação.',
            },
        ],
        outcomes: [
            'Processo único, com responsáveis e histórico registrados',
            'Menos retrabalho e menos erros de digitação',
            'Informação disponível para quem precisa, na hora certa',
            'Base pronta para integrar, automatizar e crescer',
        ],
        steps: [
            {
                title: 'Diagnóstico',
                description:
                    'Mapeamos o processo atual, as pessoas envolvidas e onde estão as perdas.',
            },
            {
                title: 'Planejamento',
                description:
                    'Definimos escopo, prioridades e a arquitetura da solução antes de escrever código.',
            },
            {
                title: 'Interface',
                description:
                    'Desenhamos as telas com quem vai usar o sistema no dia a dia.',
            },
            {
                title: 'Desenvolvimento',
                description:
                    'Construímos em entregas curtas, com validação a cada etapa.',
            },
            {
                title: 'Integração',
                description:
                    'Conectamos o sistema às ferramentas e dados que a empresa já usa.',
            },
            {
                title: 'Implantação',
                description:
                    'Migramos dados, treinamos a equipe e colocamos em produção.',
            },
            {
                title: 'Evolução',
                description:
                    'Acompanhamos o uso, corrigimos e evoluímos o sistema.',
            },
        ],
        faq: [
            {
                question: 'Quanto custa desenvolver um sistema sob medida?',
                answer: 'Depende do escopo, das integrações e do número de perfis de usuário. Começamos por um diagnóstico do processo; com ele, apresentamos uma proposta com etapas, prioridades e prazos definidos.',
            },
            {
                question: 'Preciso ter tudo definido antes de começar?',
                answer: 'Não. O diagnóstico existe justamente para transformar a necessidade em escopo. Muitas vezes começamos pelo módulo que resolve a maior dor e evoluímos a partir dele.',
            },
            {
                question: 'O sistema fica sendo da minha empresa?',
                answer: 'As condições de propriedade, hospedagem e manutenção ficam definidas em contrato antes do início do projeto.',
            },
            {
                question: 'Vocês dão suporte depois da implantação?',
                answer: 'Sim. Acompanhamos o sistema em produção, com correções e evolução contínua conforme a operação muda.',
            },
        ],
        related: [
            'integracao-de-sistemas-e-apis',
            'automacao-de-processos',
            'desenvolvimento-de-aplicativos',
        ],
    },
    {
        slug: 'automacao-de-processos',
        label: 'Automação de processos',
        summary:
            'Rotinas repetitivas executadas sozinhas: notificações, cobranças, follow-ups, documentos e movimentação de dados.',
        contactType: 'automacao',
        visual: 'automation',
        heroImage: 'automacao',
        hero: {
            eyebrow: 'Automação de processos empresariais',
            title: 'Sua equipe cuida do que importa. O repetitivo acontece sozinho.',
            lead: 'Automatizamos as rotinas que hoje dependem de alguém lembrar, copiar e colar: avisos, cobranças, follow-ups, documentos, relatórios e a passagem de informação entre áreas e sistemas.',
        },
        problems: {
            title: 'Sinais de que um processo pode ser automatizado',
            items: [
                'Alguém copia dados de um sistema para outro todos os dias',
                'Clientes deixam de receber retorno porque ninguém lembrou',
                'Cobranças e lembretes são enviados um a um, manualmente',
                'Relatórios semanais tomam horas para ficar prontos',
                'Erros de digitação geram retrabalho e prejuízo',
                'Um departamento espera o outro para seguir o processo',
            ],
        },
        offerings: [
            {
                title: 'Notificações automáticas',
                description:
                    'Avisos por e-mail ou WhatsApp disparados por eventos: pedido aprovado, prazo vencendo, status alterado.',
            },
            {
                title: 'Cobrança e follow-up',
                description:
                    'Lembretes de pagamento, retorno de orçamentos e acompanhamento de leads no momento certo.',
            },
            {
                title: 'Atendimento',
                description:
                    'Registro de mensagens recebidas, distribuição para a equipe e acompanhamento do tempo de resposta.',
            },
            {
                title: 'Documentos e relatórios',
                description:
                    'Geração de documentos, recibos e relatórios a partir dos dados do sistema, sem montagem manual.',
            },
            {
                title: 'Movimentação de dados',
                description:
                    'Informações que fluem entre sistemas, planilhas e departamentos sem redigitação.',
            },
            {
                title: 'IA aplicada ao processo',
                description:
                    'Quando agrega: classificação de mensagens, leitura de documentos e apoio à triagem — sempre conectada ao fluxo e com revisão humana onde é necessária.',
            },
        ],
        outcomes: [
            'Menos trabalho repetitivo e menos erros',
            'Prazos e retornos cumpridos sem depender de memória',
            'Departamentos integrados pelo mesmo fluxo de informação',
            'Equipe dedicada a atender, vender e decidir',
        ],
        flows: [
            {
                title: 'Captação comercial',
                steps: [
                    'Formulário do site',
                    'CRM',
                    'Notificação à equipe',
                    'Follow-up programado',
                    'Vendedor',
                ],
            },
            {
                title: 'Cobrança',
                steps: [
                    'Vencimento',
                    'Lembrete automático',
                    'Confirmação de pagamento',
                    'Baixa no financeiro',
                ],
            },
        ],
        faq: [
            {
                question: 'Automação é o mesmo que um robô de WhatsApp?',
                answer: 'Não. Mensagens automáticas são só uma parte. Automatizar é fazer a informação percorrer o processo sozinha — entre sistemas, pessoas e departamentos — com regras claras e registro do que aconteceu.',
            },
            {
                question: 'Que ferramentas vocês usam?',
                answer: 'Workflows, APIs, webhooks, filas e rotinas agendadas, escolhidos conforme o caso. Na nossa própria operação usamos n8n e integração com WhatsApp conectados ao CRM.',
            },
            {
                question:
                    'Dá para automatizar sem trocar os sistemas que já uso?',
                answer: 'Na maioria dos casos, sim. Quando o sistema atual oferece API, webhooks ou exportação de dados, a automação se conecta a ele.',
            },
            {
                question: 'E se a automação falhar?',
                answer: 'Os fluxos registram cada execução e falha, para que a equipe saiba o que aconteceu e nada se perca em silêncio.',
            },
        ],
        related: [
            'integracao-de-sistemas-e-apis',
            'desenvolvimento-de-sistemas',
            'criacao-de-landing-pages',
        ],
    },
    {
        slug: 'integracao-de-sistemas-e-apis',
        label: 'Integrações e APIs',
        summary:
            'Sistemas, plataformas e aplicativos trocando dados entre si, sem redigitação e sem planilha no meio.',
        contactType: 'integracao',
        visual: 'integration',
        heroImage: 'integracoes',
        hero: {
            eyebrow: 'Integração de sistemas e APIs',
            title: 'Seus sistemas trabalhando como um só.',
            lead: 'ERP, CRM, loja virtual, financeiro, WhatsApp e aplicativos: conectamos as ferramentas da empresa para que a informação seja registrada uma vez e chegue a todos os lugares onde precisa estar.',
        },
        problems: {
            title: 'Quando a integração resolve',
            items: [
                'Pedidos da loja virtual são digitados de novo no ERP',
                'O financeiro não enxerga o que o comercial vendeu',
                'Dados de clientes divergem entre sistemas',
                'A equipe usa cinco ferramentas que não se falam',
                'Um parceiro precisa receber ou enviar dados automaticamente',
                'O aplicativo precisa consultar o sistema principal em tempo real',
            ],
        },
        offerings: [
            {
                title: 'ERP, CRM e e-commerce',
                description:
                    'Pedidos, clientes, estoque e preços sincronizados entre as plataformas.',
            },
            {
                title: 'Gateways de pagamento',
                description:
                    'Confirmação de pagamento recebida automaticamente por webhook e refletida no sistema.',
            },
            {
                title: 'WhatsApp',
                description:
                    'Envio e recebimento de mensagens integrados ao sistema, com status de entrega e histórico por cliente.',
            },
            {
                title: 'APIs para aplicativos e parceiros',
                description:
                    'Criação de APIs seguras para que aplicativos, parceiros e outros sistemas consumam seus dados.',
            },
            {
                title: 'Bancos de dados e sistemas próprios',
                description:
                    'Leitura e escrita em bases existentes, inclusive de sistemas legados.',
            },
            {
                title: 'Serviços de terceiros',
                description:
                    'Consumo de APIs externas — pagamentos, comunicação, logística — com tratamento de falhas e registro.',
            },
        ],
        outcomes: [
            'Informação registrada uma única vez',
            'Fim da redigitação entre sistemas',
            'Dados consistentes em todas as áreas',
            'Base pronta para novos canais e aplicativos',
        ],
        flows: [
            {
                title: 'Operação conectada',
                steps: [
                    'ERP',
                    'Sistema',
                    'Aplicativo',
                    'WhatsApp',
                    'Financeiro',
                ],
            },
            {
                title: 'Do site à venda',
                steps: ['Site', 'Lead', 'CRM', 'Automação', 'Vendedor'],
            },
        ],
        faq: [
            {
                question: 'Meu sistema atual pode ser integrado?',
                answer: 'Se ele tiver API, webhooks, acesso ao banco de dados ou exportação de arquivos, quase sempre sim. Avaliamos isso no diagnóstico, antes de qualquer proposta.',
            },
            {
                question: 'Vocês também criam APIs?',
                answer: 'Sim. Desenvolvemos APIs REST autenticadas para aplicativos, parceiros e integrações entre sistemas próprios.',
            },
            {
                question: 'Integração é segura?',
                answer: 'Usamos autenticação por token, comunicação por HTTPS, validação dos dados recebidos e registro das chamadas. Credenciais ficam somente no servidor.',
            },
        ],
        related: [
            'automacao-de-processos',
            'desenvolvimento-de-sistemas',
            'desenvolvimento-de-aplicativos',
        ],
    },
    {
        slug: 'desenvolvimento-de-aplicativos',
        label: 'Aplicativos',
        summary:
            'Aplicativos Android e iOS para clientes, equipes de campo e operações internas, integrados aos seus sistemas.',
        contactType: 'aplicativo',
        visual: 'app',
        heroImage: 'aplicativos',
        hero: {
            eyebrow: 'Desenvolvimento de aplicativos',
            title: 'A operação da empresa no bolso de quem precisa dela.',
            lead: 'Aplicativos para vendedores, técnicos, equipes externas e clientes — conectados ao sistema principal, para que o que acontece na rua chegue ao escritório na mesma hora.',
        },
        problems: {
            title: 'Para quem um aplicativo faz diferença',
            items: [
                'Equipes externas que anotam no papel e digitam depois',
                'Vendedores sem histórico do cliente durante a visita',
                'Técnicos que precisam registrar atendimento em campo',
                'Clientes que querem acompanhar pedidos e serviços',
                'Programas de relacionamento e fidelização',
                'Rotinas internas que precisam de mobilidade',
            ],
        },
        offerings: [
            {
                title: 'Aplicativos para equipes',
                description:
                    'Vendas, visitas, atendimento técnico e serviços externos com dados do sistema central.',
            },
            {
                title: 'Aplicativos para clientes',
                description:
                    'Portal do cliente, acompanhamento de pedidos e serviços, agendamentos e fidelização.',
            },
            {
                title: 'Android e iOS',
                description:
                    'Uma base de código para as duas plataformas, com publicação nas lojas.',
            },
            {
                title: 'Integração com o que já existe',
                description:
                    'O aplicativo conversa com o sistema web, o ERP ou o banco de dados por meio de APIs.',
            },
        ],
        outcomes: [
            'Informação de campo registrada na hora',
            'Equipe externa com os dados de que precisa',
            'Clientes acompanhando sem precisar ligar',
            'Uma única fonte de dados para web e mobile',
        ],
        flows: [
            {
                title: 'Venda em campo',
                steps: [
                    'Visita',
                    'Pedido no aplicativo',
                    'API',
                    'Sistema central',
                    'Faturamento',
                ],
            },
        ],
        faq: [
            {
                question: 'Vocês desenvolvem para Android e iOS?',
                answer: 'Sim. Usamos tecnologia multiplataforma, o que permite publicar nas duas lojas a partir de uma base de código.',
            },
            {
                question: 'O aplicativo funciona integrado ao meu sistema?',
                answer: 'Esse é o caso mais comum. Quando o sistema ainda não tem API, ela faz parte do projeto.',
            },
            {
                question: 'Vocês já têm aplicativos em uso?',
                answer: 'Sim. O VetorPet, nosso produto para o mercado pet, tem aplicativo Android usado pelos vendedores em campo.',
            },
        ],
        related: [
            'integracao-de-sistemas-e-apis',
            'desenvolvimento-de-sistemas',
            'automacao-de-processos',
        ],
    },
    {
        slug: 'desenvolvimento-de-sites-para-empresas',
        label: 'Sites empresariais',
        summary:
            'Sites institucionais rápidos, encontráveis no Google e preparados para gerar contatos comerciais.',
        contactType: 'site',
        visual: 'site',
        heroImage: 'sites',
        hero: {
            eyebrow: 'Criação de sites profissionais',
            title: 'Um site que trabalha pela sua empresa.',
            lead: 'O site é muitas vezes o primeiro contato de um cliente com a empresa. Desenvolvemos sites rápidos, bem estruturados e encontráveis, que apresentam com clareza o que a empresa faz e levam o visitante ao contato.',
        },
        problems: {
            title: 'Quando é hora de refazer o site',
            items: [
                'O site atual não gera nenhum contato',
                'Ele é lento ou quebra no celular',
                'A empresa não aparece no Google',
                'O conteúdo não reflete mais o que a empresa faz',
                'Atualizar qualquer texto depende de terceiros',
                'Não há como saber quantos visitantes viraram contatos',
            ],
        },
        offerings: [
            {
                title: 'Sites institucionais e empresariais',
                description:
                    'Estrutura de páginas e textos pensada para explicar a empresa e conduzir ao contato.',
            },
            {
                title: 'Portais',
                description:
                    'Sites com áreas restritas, conteúdo dinâmico e integração com sistemas.',
            },
            {
                title: 'SEO técnico e performance',
                description:
                    'Estrutura semântica, metadados, sitemap, dados estruturados e carregamento rápido.',
            },
            {
                title: 'Formulários e integrações',
                description:
                    'Contatos enviados direto para o CRM, e-mail ou WhatsApp da equipe.',
            },
            {
                title: 'Gerenciamento de conteúdo',
                description:
                    'Painel para editar blog, portfólio e depoimentos, quando a empresa precisa atualizar com frequência.',
            },
            {
                title: 'Infraestrutura e manutenção',
                description:
                    'Domínio, hospedagem, certificado HTTPS, publicação, monitoramento e atualizações.',
            },
        ],
        outcomes: [
            'Presença profissional coerente com o tamanho da empresa',
            'Contatos chegando organizados para a equipe comercial',
            'Base técnica preparada para buscadores',
            'Dados de acesso e conversão para decidir investimentos',
        ],
        flows: [
            {
                title: 'Do visitante ao contato',
                steps: [
                    'Busca no Google',
                    'Site',
                    'Formulário',
                    'CRM',
                    'Retorno da equipe',
                ],
            },
        ],
        faq: [
            {
                question: 'Quanto custa um site profissional?',
                answer: 'Depende do número de páginas, das integrações e da necessidade de painel de conteúdo. Depois de uma conversa breve, enviamos uma proposta com escopo e prazo definidos.',
            },
            {
                question: 'O site vai aparecer no Google?',
                answer: 'Todo projeto nasce com SEO técnico, conteúdo estruturado e boa performance. O posicionamento também depende da concorrência e da produção contínua de conteúdo.',
            },
            {
                question: 'Vocês cuidam de domínio e hospedagem?',
                answer: 'Sim. Orientamos e configuramos domínio, hospedagem, certificado de segurança e publicação.',
            },
            {
                question: 'Consigo atualizar o site sozinho?',
                answer: 'Quando faz sentido, o site inclui painel para editar blog, portfólio e outros conteúdos. Para mudanças estruturais, a manutenção fica com a nossa equipe.',
            },
        ],
        related: [
            'criacao-de-landing-pages',
            'automacao-de-processos',
            'integracao-de-sistemas-e-apis',
        ],
    },
    {
        slug: 'criacao-de-landing-pages',
        label: 'Landing Pages',
        summary:
            'Páginas focadas em um objetivo — campanha, lançamento ou captação — com contato direto no CRM.',
        contactType: 'landing_page',
        visual: 'landing',
        heroImage: 'landing-pages',
        hero: {
            eyebrow: 'Criação de landing pages',
            title: 'Uma página, um objetivo, resultado mensurável.',
            lead: 'Para lançar um produto, divulgar um serviço, sustentar uma campanha ou validar uma ideia: landing pages rápidas, objetivas e conectadas ao processo comercial, com cada contato rastreado da origem ao atendimento.',
        },
        problems: {
            title: 'Quando usar uma landing page',
            items: [
                'Lançamento de produto ou serviço',
                'Campanhas de anúncios pagos',
                'Captação de leads para a equipe comercial',
                'Inscrições em eventos e treinamentos',
                'Validação de uma ideia antes de investir mais',
                'Apresentação de uma oferta específica',
            ],
        },
        offerings: [
            {
                title: 'Velocidade',
                description:
                    'Páginas leves, que carregam rápido no celular — onde a maior parte do tráfego de anúncios chega.',
            },
            {
                title: 'Foco em conversão',
                description:
                    'Mensagem clara, prova e chamada para ação, sem distrações.',
            },
            {
                title: 'Formulários integrados',
                description:
                    'Leads enviados direto para o CRM, com origem da campanha registrada.',
            },
            {
                title: 'WhatsApp',
                description:
                    'Contato imediato pelo WhatsApp, com mensagem já preenchida conforme a oferta.',
            },
            {
                title: 'Analytics e pixels',
                description:
                    'Configuração de ferramentas de análise e de pixels de anúncios para medir cada campanha.',
            },
            {
                title: 'SEO quando aplicável',
                description:
                    'Para páginas que também devem ser encontradas em buscas orgânicas.',
            },
        ],
        outcomes: [
            'Campanhas no ar rapidamente',
            'Leads organizados e com origem identificada',
            'Custo por contato mensurável',
            'Decisões de investimento baseadas em dados',
        ],
        flows: [
            {
                title: 'Campanha conectada',
                steps: [
                    'Anúncio',
                    'Landing page',
                    'Formulário ou WhatsApp',
                    'CRM',
                    'Follow-up',
                ],
            },
        ],
        faq: [
            {
                question: 'Qual a diferença entre site e landing page?',
                answer: 'O site apresenta a empresa como um todo. A landing page tem um único objetivo — uma oferta, uma campanha, uma inscrição — e é desenhada para converter.',
            },
            {
                question: 'Os contatos chegam aonde?',
                answer: 'No destino que fizer sentido: CRM, e-mail ou WhatsApp da equipe, com a origem da campanha registrada.',
            },
            {
                question: 'Vocês configuram os pixels de anúncios?',
                answer: 'Sim. Instalamos as ferramentas de análise e os pixels das plataformas de anúncio, respeitando o consentimento de cookies do visitante.',
            },
        ],
        related: [
            'desenvolvimento-de-sites-para-empresas',
            'automacao-de-processos',
            'integracao-de-sistemas-e-apis',
        ],
    },
];

export const products: Product[] = [
    {
        slug: 'vetoros',
        heroImage: 'vetoros',
        name: 'VetorOS',
        segment: 'Assistências técnicas',
        tagline: 'A operação da assistência técnica em um só sistema.',
        logo: '/images/logo_os.png',
        logoClass: 'rounded-xl object-contain',
        accent: 'text-sky-700',
        problem:
            'Assistências técnicas costumam controlar ordens de serviço em cadernos, grupos de WhatsApp e planilhas. O resultado é cliente ligando para saber do aparelho, peça que some do estoque e caixa que não fecha.',
        pains: [
            'Informações de clientes e equipamentos espalhadas',
            'Clientes ligando o dia inteiro para saber o status',
            'Estoque de peças sem controle real',
            'Financeiro desconectado da operação',
        ],
        features: [
            {
                title: 'Ordens de serviço e orçamentos',
                description:
                    'Do recebimento do equipamento à entrega, com orçamento, aprovação, pagamentos e recibos.',
            },
            {
                title: 'Clientes e equipamentos',
                description:
                    'Histórico completo de cada cliente e de cada aparelho atendido.',
            },
            {
                title: 'Portal do cliente',
                description:
                    'O cliente acompanha a ordem de serviço por um link, sem precisar ligar.',
            },
            {
                title: 'Follow-ups e cobranças',
                description:
                    'Acompanhamento de orçamentos pendentes e de valores a receber.',
            },
            {
                title: 'Peças, estoque e vendas',
                description:
                    'Entrada e saída de peças e produtos, vendas de balcão e controle de estoque.',
            },
            {
                title: 'Financeiro e indicadores',
                description:
                    'Caixa, despesas, garantia, avaliações de clientes e painel com as prioridades do dia.',
            },
        ],
        outcomes: [
            'Histórico de cliente e equipamento em um só lugar',
            'Status da OS visível sem a equipe parar para responder',
            'Estoque com entrada, saída e alerta de reposição',
            'Financeiro conectado à operação',
        ],
        platforms: [
            'Web (computador, tablet e celular)',
            'Portal do cliente por link',
        ],
        deployment:
            'SaaS na nuvem: cadastro online, sem instalação, com 14 dias de teste gratuito sem cartão de crédito.',
        integrations: [
            'Envio de e-mails com SMTP próprio da empresa',
            'Atalho para os emissores fiscais oficiais',
        ],
        screenshot: {
            src: '/images/dashboard-vetoros.webp',
            alt: 'Painel do VetorOS com prioridades do dia, clientes, ordens, agenda e mensagens',
            width: 1926,
            height: 934,
        },
        externalUrl: 'https://vetoros.com.br',
        contactType: 'vetoros',
        faq: [
            {
                question: 'Preciso instalar alguma coisa?',
                answer: 'Não. O VetorOS funciona no navegador, em computador, tablet ou celular.',
            },
            {
                question: 'Como funciona o teste?',
                answer: 'O cadastro é feito online e libera 14 dias de acesso completo, sem pedir cartão de crédito.',
            },
            {
                question: 'Posso pedir uma demonstração?',
                answer: 'Sim. Fale com a equipe e apresentamos o sistema com base na rotina da sua assistência.',
            },
        ],
    },
    {
        slug: 'vetorpet',
        heroImage: 'vetorpet',
        name: 'VetorPet',
        segment: 'Distribuidores e representantes do mercado pet',
        tagline: 'Da visita ao pedido, com a equipe comercial sob controle.',
        logo: '/images/logo_pet.png',
        logoClass: 'rounded-xl bg-white object-contain p-1',
        accent: 'text-violet-700',
        problem:
            'Quem vende para o mercado pet costuma anotar pedidos no papel, digitar depois e calcular comissão à mão — enquanto o gestor fica sem enxergar o que a equipe faz em campo.',
        pains: [
            'Pedido anotado no papel e digitado depois, com erro',
            'Vendedor sem histórico do cliente na visita',
            'Comissões calculadas manualmente',
            'Gestor sem visibilidade da equipe de campo',
        ],
        features: [
            {
                title: 'Carteira de clientes e regiões',
                description:
                    'Clientes organizados por vendedor e região, com histórico de compras.',
            },
            {
                title: 'Catálogo e pedidos em campo',
                description:
                    'Pedido lançado no aplicativo durante a visita, com o catálogo atualizado.',
            },
            {
                title: 'Visitas',
                description:
                    'Registro e acompanhamento das visitas da equipe comercial.',
            },
            {
                title: 'Comissões',
                description:
                    'Comissões calculadas automaticamente a partir dos pedidos.',
            },
            {
                title: 'Painel de resultados',
                description: 'Indicadores por equipe, região e período.',
            },
            {
                title: 'Painel web e aplicativo Android',
                description:
                    'Gestão no computador e operação de campo no celular.',
            },
        ],
        outcomes: [
            'Pedido registrado ainda na visita',
            'Carteira e histórico na mão do vendedor',
            'Comissões sem planilha',
            'Visão clara da equipe em campo',
        ],
        platforms: ['Painel web', 'Aplicativo Android'],
        deployment:
            'SaaS na nuvem: cadastro online, sem instalação, com 14 dias de teste gratuito sem cartão de crédito.',
        integrations: ['Aplicativo Android sincronizado com o painel web'],
        externalUrl: 'https://vetorpet.com.br',
        contactType: 'vetorpet',
        faq: [
            {
                question: 'O aplicativo funciona em qualquer celular?',
                answer: 'O aplicativo de campo é para Android; a gestão é feita no painel web.',
            },
            {
                question: 'Como funciona o teste?',
                answer: 'O cadastro é feito online e libera 14 dias de acesso completo, sem pedir cartão de crédito.',
            },
            {
                question: 'Posso pedir uma demonstração?',
                answer: 'Sim. Fale com a equipe e mostramos o sistema com base na rotina da sua equipe comercial.',
            },
        ],
    },
];

/** Processo de trabalho da ABrasil, usado na home e nas páginas institucionais. */
export const processSteps = [
    {
        title: 'Entendimento',
        description:
            'Entendemos o processo, as pessoas envolvidas e o problema a resolver.',
    },
    {
        title: 'Planejamento',
        description:
            'Definimos solução, arquitetura, prioridades e etapas de entrega.',
    },
    {
        title: 'Desenvolvimento',
        description: 'Construímos em etapas, com validação a cada entrega.',
    },
    {
        title: 'Integração',
        description:
            'Conectamos os sistemas e ferramentas que a operação já usa.',
    },
    {
        title: 'Implantação',
        description:
            'Colocamos em produção, migramos dados e preparamos a equipe.',
    },
    {
        title: 'Evolução',
        description: 'Mantemos, acompanhamos e melhoramos continuamente.',
    },
] as const;

/**
 * Tecnologias efetivamente usadas nos projetos do ecossistema ABrasil
 * (auditadas nos repositórios em 2026-10). Só incluir o que estiver em uso.
 */
export const technologyGroups = [
    {
        title: 'Aplicações web',
        items: [
            'PHP',
            'Laravel',
            'React',
            'TypeScript',
            'Inertia.js',
            'Tailwind CSS',
        ],
    },
    { title: 'Aplicativos', items: ['React Native', 'Expo', 'Android'] },
    { title: 'Dados', items: ['MySQL', 'Redis', 'SQLite'] },
    {
        title: 'Integração e automação',
        items: ['APIs REST', 'Webhooks', 'Filas', 'n8n', 'WhatsApp'],
    },
    {
        title: 'Infraestrutura',
        items: ['Linux', 'Docker', 'Nginx', 'HTTPS / Let’s Encrypt'],
    },
] as const;

/** Cases publicados. Vazio até haver projetos autorizados pelos clientes. */
export const caseStudies: CaseStudy[] = [];

export function findService(slug: string): Service | undefined {
    return services.find((service) => service.slug === slug);
}

export function findProduct(slug: string): Product | undefined {
    return products.find((product) => product.slug === slug);
}

export function contactHref(type?: string): string {
    return type ? `/contato?tipo=${type}#formulario` : '/contato#formulario';
}
