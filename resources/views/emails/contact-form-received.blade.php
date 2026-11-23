<x-mail::message>
# Novo contato pelo site

**{{ $lead->contact_name ?? 'Sem nome informado' }}**{{ $lead->company_name ? ', da empresa '.$lead->company_name.',' : '' }} entrou em contato pelo formulário do site.

<x-mail::table>
| Campo | Valor |
| :- | :- |
| Serviço de interesse | {{ $serviceLabel }} |
| Telefone / WhatsApp | {{ $lead->whatsapp ?? '—' }} |
| E-mail | {{ $lead->email ?? '—' }} |
| Página de origem | {{ $lead->source_page ?? '—' }} |
| Origem comercial | {{ $lead->commercialOrigin() }} |
</x-mail::table>

**Mensagem:**

{{ $contactMessage }}

<x-mail::button :url="$adminUrl">
Abrir a solicitação
</x-mail::button>

A solicitação está no painel, em Solicitações, com a situação "{{ \App\Models\Lead::REQUEST_STATUSES[$lead->request_status] ?? 'Novo contato' }}".

Atenciosamente,<br>
{{ config('app.name') }}
</x-mail::message>
