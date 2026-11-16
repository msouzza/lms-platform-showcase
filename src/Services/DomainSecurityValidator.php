<?php

namespace App\Services;

class DomainSecurityValidator
{
    /**
     * Valida se a origem/domínio é permitida segundo a política estrita de CORS/Host do sistema.
     * Rejeita domínios maliciosos com bypass de substring (ex: evil-contextopolicial.com ou contextopolicial.com.attacker.org).
     *
     * @param string $origin
     * @return bool
     */
    public function isAllowedOrigin(string $origin): bool
    {
        if (trim($origin) === '') {
            return false;
        }

        // Se contiver esquema (http://, https://), extrai o host; caso contrário usa a string direta
        $host = parse_url($origin, PHP_URL_HOST);
        if (!$host) {
            $host = parse_url('http://' . $origin, PHP_URL_HOST) ?: $origin;
        }

        if (!$host || !is_string($host)) {
            return false;
        }

        // Regex estrita do sistema:
        // Apenas subdomínios válidos ou domínio raiz de contextopolicial.com ou adrenalinapolicial.com (com opcional .br)
        return (bool) preg_match('/^(?:[a-z0-9-]+\.)?(contextopolicial\.com|adrenalinapolicial\.com)(?:\.br)?$/i', $host);
    }
}
