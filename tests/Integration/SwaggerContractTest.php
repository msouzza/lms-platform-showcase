<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\HttpTestClient;

class SwaggerContractTest extends TestCase
{
    private HttpTestClient $client;
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->client = new HttpTestClient();
        $this->projectRoot = realpath(__DIR__ . '/../../');
    }

    public function testOpenApiJsonExistsAndIsValidJson(): void
    {
        $jsonPath = $this->projectRoot . '/docs/api/openapi.json';
        $this->assertFileExists($jsonPath, "O arquivo docs/api/openapi.json deve existir.");

        $content = file_get_contents($jsonPath);
        $this->assertNotFalse($content, "Deve ser possível ler o conteúdo de docs/api/openapi.json.");

        $spec = json_decode($content, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), "O arquivo openapi.json deve ser um JSON válido: " . json_last_error_msg());

        // Valida chaves obrigatórias da OpenAPI 3.0
        $this->assertArrayHasKey('openapi', $spec, "O JSON da OpenAPI deve conter a chave 'openapi'.");
        $this->assertArrayHasKey('info', $spec, "O JSON da OpenAPI deve conter a chave 'info'.");
        $this->assertArrayHasKey('paths', $spec, "O JSON da OpenAPI deve conter a chave 'paths'.");
        $this->assertArrayHasKey('components', $spec, "O JSON da OpenAPI deve conter a chave 'components'.");

        // Verifica se a versão da spec começa com '3.'
        $this->assertStringStartsWith('3.', $spec['openapi'], "A versão da especificação OpenAPI deve começar com '3.'.");
    }

    public function testDocumentedEndpointsExistInCodebase(): void
    {
        $jsonPath = $this->projectRoot . '/docs/api/openapi.json';
        $this->assertFileExists($jsonPath);

        $spec = json_decode(file_get_contents($jsonPath), true);
        $this->assertIsArray($spec['paths'] ?? null, "A chave 'paths' deve estar presente no documento OpenAPI.");

        foreach (array_keys($spec['paths']) as $route) {
            // Remove a barra inicial e parâmetros dinâmicos se houver (ex: /api/alunos/{id} -> api/alunos)
            // Ou testa o arquivo base / script correspondente
            $cleanRoute = ltrim($route, '/');
            
            // Se for rota com parâmetros ou específica, podemos verificar o arquivo base ou o mapeamento
            // Por exemplo, /api/auth/login.php ou api/auth/login.php ou index.php
            $filePath = $this->projectRoot . '/' . $cleanRoute;

            // Se o caminho exato não existir, tenta verificar se existe um arquivo PHP correspondente
            if (!file_exists($filePath)) {
                // Tenta achar sem parâmetros de rota exatos (ex: substituir {id} por algo ou verificar diretório)
                $baseRoute = preg_replace('/\{[^}]+\}/', '', $cleanRoute);
                $baseRoute = rtrim($baseRoute, '/');
                $filePathBase = $this->projectRoot . '/' . $baseRoute . '.php';
                $filePathIndex = $this->projectRoot . '/' . $baseRoute . '/index.php';

                $exists = file_exists($filePath) || file_exists($filePathBase) || file_exists($filePathIndex) || file_exists($this->projectRoot . '/' . $cleanRoute . '.php');
                
                $this->assertTrue($exists, "O endpoint documentado '{$route}' deve ter um arquivo correspondente na raiz do projeto.");
            } else {
                $this->assertFileExists($filePath, "O arquivo correspondente ao endpoint '{$route}' deve existir fisicamente.");
            }
        }
    }

    public function testSwaggerUiHtmlExistsAndLoadsSpec(): void
    {
        $htmlPath = $this->projectRoot . '/docs/api/index.html';
        $this->assertFileExists($htmlPath, "O arquivo docs/api/index.html do Swagger UI deve existir.");

        $htmlContent = file_get_contents($htmlPath);
        $this->assertNotFalse($htmlContent);

        // Verifica referências a swagger-ui e openapi.json
        $this->assertStringContainsString('swagger-ui', strtolower($htmlContent), "O HTML do Swagger UI deve conter referências ao swagger-ui.");
        $this->assertStringContainsString('openapi.json', $htmlContent, "O HTML do Swagger UI deve fazer referência ao arquivo openapi.json.");
    }

    public function testContractUnauthenticatedAccessMatchesSpec(): void
    {
        $jsonPath = $this->projectRoot . '/docs/api/openapi.json';
        $spec = json_decode(file_get_contents($jsonPath), true);

        // Valida que todos os endpoints protegidos documentam respostas de erro (401 ou 403)
        foreach ($spec['paths'] as $route => $methods) {
            foreach ($methods as $method => $details) {
                if (in_array($method, ['parameters', 'summary', 'description'], true)) {
                    continue;
                }

                $hasSecurity = isset($details['security']) || str_contains($route, '/api');
                if ($hasSecurity) {
                    $responses = $details['responses'] ?? [];
                    $hasAuthErrorSchema = isset($responses['401']) || isset($responses['403']);
                    $this->assertTrue(
                        $hasAuthErrorSchema,
                        "O endpoint protegido {$method} {$route} deve documentar resposta de segurança (401 ou 403) no OpenAPI spec."
                    );

                    // Se houver um servidor HTTP de teste respondendo (status > 0), valida contrato em tempo de execução
                    if (strtoupper($method) === 'GET') {
                        $res = $this->client->get($route);
                        if ($res['status'] > 0) {
                            $this->assertContains(
                                $res['status'],
                                [302, 401, 403],
                                "O endpoint não autenticado {$method} {$route} deve retornar código HTTP compatível com segurança (401, 403, 302)."
                            );
                        }
                    }
                }
            }
        }
    }
}
