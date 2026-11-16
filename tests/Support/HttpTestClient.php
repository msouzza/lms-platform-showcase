<?php

namespace Tests\Support;

class HttpTestClient
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?: (getenv('APP_TEST_URL') ?: 'http://127.0.0.1:8089'), '/');
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'lms_test_cookie_');
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function resetCookies(): void
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'lms_test_cookie_');
    }

    /**
     * Realiza uma requisição HTTP GET
     */
    public function get(string $path, array $headers = [], bool $followRedirects = false): array
    {
        return $this->request('GET', $path, null, $headers, $followRedirects);
    }

    /**
     * Realiza uma requisição HTTP POST com formulário (application/x-www-form-urlencoded)
     */
    public function postForm(string $path, array $formData, array $headers = [], bool $followRedirects = false): array
    {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        return $this->request('POST', $path, http_build_query($formData), $headers, $followRedirects);
    }

    /**
     * Realiza uma requisição HTTP POST com JSON (application/json)
     */
    public function postJson(string $path, array $payload, array $headers = []): array
    {
        $headers[] = 'Content-Type: application/json';
        return $this->request('POST', $path, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, false);
    }

    private function request(string $method, string $path, ?string $body, array $headers, bool $followRedirects): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$responseHeaders) {
                $len = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $responseHeaders[$name] = trim($parts[1]);
                }
                return $len;
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawBody = curl_exec($ch);
        $status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error   = curl_error($ch);
        curl_close($ch);

        return [
            'status'  => $status,
            'headers' => $responseHeaders,
            'body'    => (string) $rawBody,
            'json'    => json_decode((string) $rawBody, true),
            'error'   => $error,
        ];
    }
}
