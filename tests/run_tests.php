<?php
/**
 * Runner Standalone de Testes (Zero-Dependency Fallback)
 * Permite rodar as suítes RegressionTest e SecurityBaselineTest direto pelo PHP do XAMPP
 * (`C:\xampp\php\php.exe tests/run_tests.php`) mesmo sem precisar instalar o Composer localmente.
 */

namespace PHPUnit\Framework {
    if (!class_exists('PHPUnit\Framework\TestCase')) {
        class AssertionFailedError extends \Exception {}

        abstract class TestCase
        {
            protected function setUp(): void {}
            protected function tearDown(): void {}

            public function runSingleTest(string $method): void
            {
                $this->setUp();
                try {
                    $this->$method();
                } finally {
                    $this->tearDown();
                }
            }

            protected function assertNull($actual, string $message = ''): void
            {
                if ($actual !== null) {
                    throw new AssertionFailedError($message ?: "Esperado null, recebido: " . var_export($actual, true));
                }
            }

            protected function assertSame($expected, $actual, string $message = ''): void
            {
                if ($expected !== $actual) {
                    throw new AssertionFailedError($message ?: sprintf("Esperado %s, recebido %s", var_export($expected, true), var_export($actual, true)));
                }
            }

            protected function assertNotSame($expected, $actual, string $message = ''): void
            {
                if ($expected === $actual) {
                    throw new AssertionFailedError($message ?: sprintf("Valor não deveria ser igual a %s", var_export($expected, true)));
                }
            }

            protected function assertTrue($condition, string $message = ''): void
            {
                if ($condition !== true) {
                    throw new AssertionFailedError($message ?: "Esperado true, recebido false.");
                }
            }

            protected function assertContains($needle, iterable $haystack, string $message = ''): void
            {
                $arr = is_array($haystack) ? $haystack : iterator_to_array($haystack);
                if (!in_array($needle, $arr, true)) {
                    throw new AssertionFailedError($message ?: sprintf("Valor %s não encontrado na lista %s", var_export($needle, true), json_encode($arr)));
                }
            }

            protected function assertNotEmpty($actual, string $message = ''): void
            {
                if (empty($actual)) {
                    throw new AssertionFailedError($message ?: "Esperado valor não-vazio.");
                }
            }

            protected function assertGreaterThan($expected, $actual, string $message = ''): void
            {
                if (!($actual > $expected)) {
                    throw new AssertionFailedError($message ?: sprintf("Esperado valor maior que %s, recebido %s", $expected, $actual));
                }
            }

            protected function assertLessThanOrEqual($expected, $actual, string $message = ''): void
            {
                if (!($actual <= $expected)) {
                    throw new AssertionFailedError($message ?: sprintf("Esperado <= %s, recebido %s", $expected, $actual));
                }
            }

            protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
            {
                if (strpos($haystack, $needle) === false) {
                    throw new AssertionFailedError($message ?: sprintf("String '%s' não encontrada na resposta.", $needle));
                }
            }

            protected function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
            {
                if (strpos($haystack, $needle) !== false) {
                    throw new AssertionFailedError($message ?: sprintf("String proibida '%s' foi encontrada na resposta.", $needle));
                }
            }

            protected function assertFalse($condition, string $message = ''): void
            {
                if ($condition !== false) {
                    throw new AssertionFailedError($message ?: "Esperado false, recebido true.");
                }
            }

            protected function assertEquals($expected, $actual, string $message = ''): void
            {
                if ($expected != $actual) {
                    throw new AssertionFailedError($message ?: sprintf("Esperado %s, recebido %s", var_export($expected, true), var_export($actual, true)));
                }
            }

            protected function assertCount(int $expectedCount, $haystack, string $message = ''): void
            {
                $actualCount = is_countable($haystack) ? count($haystack) : 0;
                if ($actualCount !== $expectedCount) {
                    throw new AssertionFailedError($message ?: sprintf("Esperado %d elementos, recebido %d", $expectedCount, $actualCount));
                }
            }

            protected function assertIsArray($actual, string $message = ''): void
            {
                if (!is_array($actual)) {
                    throw new AssertionFailedError($message ?: sprintf("Esperado array, recebido %s", gettype($actual)));
                }
            }

            protected function assertArrayHasKey($key, array $array, string $message = ''): void
            {
                if (!array_key_exists($key, $array)) {
                    throw new AssertionFailedError($message ?: sprintf("Chave '%s' não encontrada no array.", $key));
                }
            }

            protected function assertFileExists(string $filename, string $message = ''): void
            {
                if (!file_exists($filename)) {
                    throw new AssertionFailedError($message ?: sprintf("Arquivo '%s' não existe.", $filename));
                }
            }

            protected function assertNotFalse($actual, string $message = ''): void
            {
                if ($actual === false) {
                    throw new AssertionFailedError($message ?: "Esperado valor diferente de false.");
                }
            }

            protected function assertStringStartsWith(string $prefix, string $string, string $message = ''): void
            {
                if (strpos($string, $prefix) !== 0) {
                    throw new AssertionFailedError($message ?: sprintf("String '%s' não inicia com '%s'.", $string, $prefix));
                }
            }
        }
    }
}

namespace {
    require_once __DIR__ . '/bootstrap.php';

    $suiteFilter = strtolower($argv[1] ?? 'all'); // 'all', 'unit', 'regression', 'security', 'contract'

    $suites = [];
    if (($suiteFilter === 'all' || $suiteFilter === 'unit') && class_exists(\Tests\Unit\ServicesUnitTest::class)) {
        $suites['Unit (Testes Unitários de Serviços)'] = \Tests\Unit\ServicesUnitTest::class;
    }
    if (($suiteFilter === 'all' || $suiteFilter === 'contract' || $suiteFilter === 'swagger') && class_exists(\Tests\Integration\SwaggerContractTest::class)) {
        $suites['SwaggerContract (Contratos OpenAPI / Swagger)'] = \Tests\Integration\SwaggerContractTest::class;
    }
    if ($suiteFilter === 'all' || $suiteFilter === 'regression') {
        $suites['Regression (Fluxo Feliz / Regressão)'] = \Tests\Integration\RegressionTest::class;
    }
    if ($suiteFilter === 'all' || $suiteFilter === 'security') {
        $suites['SecurityAudit (Auditoria AppSec)'] = \Tests\Security\SecurityBaselineTest::class;
    }


    $passed = 0;
    $failed = 0;

    foreach ($suites as $suiteName => $className) {
        echo "\n============================================================\n";
        echo "🧪 Suíte: {$suiteName}\n";
        echo "============================================================\n";

        $ref = new \ReflectionClass($className);
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if (strpos($name, 'test') !== 0) {
                continue;
            }

            $instance = new $className();
            try {
                if (method_exists($instance, 'runSingleTest')) {
                    $instance->runSingleTest($name);
                } else {
                    $instance->$name();
                }
                echo "  ✅ PASS: {$name}\n";
                $passed++;
            } catch (\Throwable $e) {
                echo "  ❌ FAIL: {$name}\n";
                echo "     ↳ " . $e->getMessage() . "\n";
                $failed++;
            }
        }
    }

    echo "\n------------------------------------------------------------\n";
    echo "Resumo: {$passed} passaram | {$failed} falharam\n";
    echo "------------------------------------------------------------\n";

    exit($failed > 0 ? 1 : 0);
}
