export const whatsappMessageTemplates = {
    vetoros: `Olá, tudo bem?

Sou Anderson, da AB Sistemas. Depois de mais de 15 anos trabalhando com assistência técnica de informática e celulares, criei o VetorOS para resolver problemas que eu mesmo vivi no balcão: OS desorganizada, controle manual, perda de histórico e demora no atendimento.

O VetorOS ajuda sua assistência a organizar ordens de serviço, clientes, equipamentos, orçamentos e acompanhamentos em um só lugar.

Você pode conhecer e testar aqui:
vetoros.com.br

Teste com calma e, se fizer sentido, podemos marcar uma conversa em um sábado para tirar dúvidas e ver como o VetorOS se encaixa na sua rotina.`,
    vetorpet: `Olá, tudo bem?

Sou Anderson, da AB Sistemas. Também desenvolvi o VetorPet, um sistema para ajudar pet shops e serviços pet a organizarem clientes, atendimentos, vendas e a rotina do negócio em um só lugar.

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
