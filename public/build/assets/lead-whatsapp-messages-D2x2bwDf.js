var e={vetoros:`Olá, tudo bem?

Sou Anderson, da AB Sistemas. Depois de mais de 15 anos trabalhando com assistência técnica de informática e celulares, criei o VetorOS para resolver problemas que eu mesmo vivi no balcão: OS desorganizada, controle manual, perda de histórico e demora no atendimento.

O VetorOS ajuda sua assistência a organizar ordens de serviço, clientes, equipamentos, orçamentos e acompanhamentos em um só lugar.

Você pode conhecer e testar aqui:
vetoros.com.br

Teste com calma e, se fizer sentido, podemos marcar uma conversa em um sábado para tirar dúvidas e ver como o VetorOS se encaixa na sua rotina.`,vetorpet:`Olá, tudo bem?

Sou Anderson, da AB Sistemas. Também desenvolvi o VetorPet, um sistema para ajudar pet shops e serviços pet a organizarem clientes, atendimentos, vendas e a rotina do negócio em um só lugar.

A ideia é reduzir controles manuais, melhorar o acompanhamento dos clientes e deixar o dia a dia mais organizado.

Você pode conhecer e testar aqui:
vetorpet.com.br

Teste com calma e, se fizer sentido, podemos marcar uma conversa em um sábado para tirar dúvidas e ver como o VetorPet se encaixa na sua rotina.`},t=`leads.whatsappMessages`;function n(){if(typeof window>`u`)return e;let n=window.localStorage.getItem(t);if(!n)return e;try{return{...e,...JSON.parse(n)}}catch{return e}}function r(e){window.localStorage.setItem(t,JSON.stringify(e))}export{e as n,r,n as t};