<?php

namespace App\Services;

/**
 * Resolução DNS da API de cadastro (CRM-WA-29), isolada para os testes trocarem por uma fixa.
 * Só IPv4: a chamada é presa ao endereço conferido (CURLOPT_RESOLVE), então IPv6 nunca é usado.
 */
class HostResolver
{
    /** @return list<string> */
    public function ipv4(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return [$host];
        }

        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : $addresses;
    }
}
