/**
 * Fotos dos heroes do site institucional.
 *
 * Arquivos em public/images/site/heroes/{chave}-{640|1280|1920}.{avif|webp}
 * (recorte 16:9) e {chave}-og.jpg (1200×630, para redes sociais).
 * Origem e licença de cada foto: docs/imagens-do-site.md.
 * O texto alternativo é o mesmo de App\Support\PublicPages::HERO_IMAGES
 * (um teste garante que os dois não se separem).
 */
export type HeroImageKey =
    | 'home'
    | 'sistemas'
    | 'sistemas-sob-medida'
    | 'automacao'
    | 'integracoes'
    | 'aplicativos'
    | 'sites'
    | 'landing-pages'
    | 'vetoros'
    | 'vetorpet'
    | 'sobre'
    | 'contato';

export type HeroImage = {
    key: HeroImageKey;
    alt: string;
    /** Ponto de foco (object-position) quando o hero corta a foto: no celular e nos heroes baixos. */
    position: string;
};

export const HERO_IMAGE_WIDTHS = [640, 1280, 1920] as const;

export const heroImages: Record<HeroImageKey, HeroImage> = {
    home: {
        key: 'home',
        alt: 'Equipe de desenvolvimento conversando ao lado de estações de trabalho com código na tela, em um escritório de tecnologia',
        position: '62% 40%',
    },
    sistemas: {
        key: 'sistemas',
        alt: 'Pessoa usando um sistema de gestão em um notebook sobre a mesa de trabalho',
        position: '55% center',
    },
    'sistemas-sob-medida': {
        key: 'sistemas-sob-medida',
        alt: 'Duas pessoas desenvolvendo software com código-fonte aberto nos monitores, em um escritório claro',
        position: '60% center',
    },
    automacao: {
        key: 'automacao',
        alt: 'Profissional registrando uma operação no notebook em um depósito com paletes e balança',
        position: '35% center',
    },
    integracoes: {
        key: 'integracoes',
        alt: 'Cabos de rede organizados e conectados a um painel de infraestrutura',
        position: 'center',
    },
    aplicativos: {
        key: 'aplicativos',
        alt: 'Mão segurando um smartphone com um aplicativo de painel de dados, diante de um computador',
        position: '55% center',
    },
    sites: {
        key: 'sites',
        alt: 'Pessoas trabalhando juntas no site de uma empresa aberto em um notebook',
        position: '45% center',
    },
    'landing-pages': {
        key: 'landing-pages',
        alt: 'Notebook exibindo um painel de métricas de acesso e conversão',
        position: '35% center',
    },
    vetoros: {
        key: 'vetoros',
        alt: 'Técnicos consertando equipamentos eletrônicos na bancada de uma assistência técnica',
        position: '45% 35%',
    },
    vetorpet: {
        key: 'vetorpet',
        alt: 'Cão deitado ao lado de um pote de ração em um ambiente doméstico',
        position: '60% center',
    },
    sobre: {
        key: 'sobre',
        alt: 'Dois colegas analisando juntos um projeto no notebook, com diagramas no quadro ao fundo',
        position: '55% 30%',
    },
    contato: {
        key: 'contato',
        alt: 'Profissional conversando ao telefone com um cliente em frente ao notebook',
        position: '60% 15%',
    },
};

export function heroImageSrcSet(key: HeroImageKey, format: 'avif' | 'webp') {
    return HERO_IMAGE_WIDTHS.map(
        (width) => `/images/site/heroes/${key}-${width}.${format} ${width}w`,
    ).join(', ');
}
