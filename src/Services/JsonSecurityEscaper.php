<?php

namespace App\Services;

class JsonSecurityEscaper
{
    /**
     * Serializa dados em JSON seguro para injeção direta em HTML ou tags <script>,
     * prevenindo ataques de Stored XSS com escape rigoroso de caracteres especiais.
     *
     * @param mixed $data
     * @return string
     */
    public function escapeForHtml(mixed $data): string
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
        return json_encode($data, $flags);
    }
}
