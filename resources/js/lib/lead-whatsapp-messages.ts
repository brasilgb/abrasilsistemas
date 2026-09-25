export const whatsappMessageTemplates = {
    vetoros: `Olá, tudo bem?

Sou Anderson, da ABrasil Sistemas. Depois de mais de 15 anos trabalhando com assistência técnica de informática e celulares, criei o VetorOS para resolver problemas que eu mesmo vivi no balcão: OS desorganizada, controle manual, perda de histórico e demora no atendimento.

O VetorOS ajuda sua assistência a organizar ordens de serviço, clientes, equipamentos, orçamentos e acompanhamentos em um só lugar.

Você pode conhecer e testar aqui:
vetoros.com.br

Teste com calma e, se fizer sentido, podemos marcar uma conversa em um sábado para tirar dúvidas e ver como o VetorOS se encaixa na sua rotina.`,
    vetorpet: `Olá, tudo bem?

Sou Anderson, da ABrasil Sistemas. Também desenvolvi o VetorPet, um sistema para ajudar pet shops e serviços pet a organizarem clientes, atendimentos, vendas e a rotina do negócio em um só lugar.

A ideia é reduzir controles manuais, melhorar o acompanhamento dos clientes e deixar o dia a dia mais organizado.

Você pode conhecer e testar aqui:
vetorpet.com.br

Teste com calma e, se fizer sentido, podemos marcar uma conversa em um sábado para tirar dúvidas e ver como o VetorPet se encaixa na sua rotina.`,
} satisfies Record<string, string>;

export type WhatsappProduct = keyof typeof whatsappMessageTemplates;

const messagesKey = 'leads.whatsappMessages';

export function readWhatsappMessages(): Record<WhatsappProduct, string> {
    if (typeof window === 'undefined') {
        return whatsappMessageTemplates;
    }

    const savedMessages = window.localStorage.getItem(messagesKey);

    if (!savedMessages) {
        return whatsappMessageTemplates;
    }

    try {
        return {
            ...whatsappMessageTemplates,
            ...JSON.parse(savedMessages),
        };
    } catch {
        return whatsappMessageTemplates;
    }
}

export function writeWhatsappMessages(
    messages: Record<WhatsappProduct, string>,
) {
    window.localStorage.setItem(messagesKey, JSON.stringify(messages));
}

/** Campos do lead que podem entrar numa mensagem rápida como {{variavel}}. */
export type WhatsappMessageLead = {
    contact_name: string | null;
    company_name: string;
    city: string | null;
};

export const whatsappMessageVariables = {
    nome: (lead: WhatsappMessageLead) => lead.contact_name,
    empresa: (lead: WhatsappMessageLead) => lead.company_name,
    cidade: (lead: WhatsappMessageLead) => lead.city,
} satisfies Record<string, (lead: WhatsappMessageLead) => string | null>;

export type WhatsappQuickMessage = {
    key: string;
    group: 'Primeiro contato' | 'Apresentação' | 'Follow-up';
    label: string;
    text: string;
};

const quickMessageTexts = {
    firstContact: `Olá, tudo bem? Falo com a {{empresa}}?

Sou Anderson, da ABrasil Sistemas. Gostaria de apresentar rapidamente uma solução que pode ajudar na rotina de vocês. Posso te enviar mais detalhes por aqui?`,
    followUp: `Olá, {{nome}}, tudo bem?

Passando para saber se você conseguiu dar uma olhada na apresentação que te enviei. Se fizer sentido, podemos marcar uma conversa rápida para tirar dúvidas. Qual o melhor dia para você?`,
    followUpLast: `Olá, {{nome}}, tudo bem?

Não quero ser inconveniente: se agora não for o momento para a {{empresa}}, sem problemas. Fico à disposição para quando fizer sentido conversarmos.`,
};

/**
 * Mensagens rápidas da conversa. As apresentações reutilizam os textos por produto
 * (os mesmos da listagem de leads, com a edição salva em Configurações > Leads).
 */
export function whatsappQuickMessages(
    presentations: Record<WhatsappProduct, string>,
): WhatsappQuickMessage[] {
    return [
        {
            key: 'first_contact',
            group: 'Primeiro contato',
            label: 'Primeiro contato',
            text: quickMessageTexts.firstContact,
        },
        {
            key: 'presentation_vetoros',
            group: 'Apresentação',
            label: 'Apresentação VetorOS',
            text: presentations.vetoros,
        },
        {
            key: 'presentation_vetorpet',
            group: 'Apresentação',
            label: 'Apresentação VetorPet',
            text: presentations.vetorpet,
        },
        {
            key: 'follow_up',
            group: 'Follow-up',
            label: 'Follow-up após apresentação',
            text: quickMessageTexts.followUp,
        },
        {
            key: 'follow_up_last',
            group: 'Follow-up',
            label: 'Último follow-up',
            text: quickMessageTexts.followUpLast,
        },
    ];
}

const variablePattern = /\{\{\s*([a-z_]+)\s*\}\}/gi;

/** Variáveis ainda não resolvidas no texto (ex.: {{nome}} de um lead sem contato). */
export function unresolvedWhatsappVariables(text: string): string[] {
    return [
        ...new Set(
            [...text.matchAll(variablePattern)].map((match) => match[0]),
        ),
    ];
}

/**
 * Substitui as variáveis pelos dados do lead. Uma variável sem valor no lead (ou desconhecida)
 * fica no texto, para o operador completar antes de enviar.
 */
export function fillWhatsappVariables(
    text: string,
    lead: WhatsappMessageLead,
): string {
    return text.replace(variablePattern, (placeholder, name: string) => {
        const resolve =
            whatsappMessageVariables[
                name.toLowerCase() as keyof typeof whatsappMessageVariables
            ];
        const value = resolve?.(lead)?.trim();

        return value ? value : placeholder;
    });
}
