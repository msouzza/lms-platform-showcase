<?php

namespace Tests\Unit;

use App\Services\DomainSecurityValidator;
use App\Services\JsonSecurityEscaper;
use App\Services\ScoreCalculator;
use App\Services\TafEvaluator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ServicesUnitTest extends TestCase
{
    // =========================================================================
    // 1. TESTES UNITÁRIOS: ScoreCalculator
    // =========================================================================

    public function testScoreCalculatorCalculoCaminhoNormal(): void
    {
        $calc = new ScoreCalculator();

        $this->assertSame(80.0, $calc->calculate(8, 10));
        $this->assertSame(70.0, $calc->calculate(35, 50));
        $this->assertSame(100.0, $calc->calculate(10, 10));
        $this->assertSame(0.0, $calc->calculate(0, 10));
        $this->assertSame(33.33, $calc->calculate(1, 3));
        $this->assertSame(66.67, $calc->calculate(2, 3));
        $this->assertSame(75.0, $calc->calculate(7.5, 10));
    }

    public function testScoreCalculatorProtecaoDivisaoPorZeroEValoresExtremos(): void
    {
        $calc = new ScoreCalculator();

        // Total zero ou negativo deve retornar 0.0 sem emitir divisão por zero
        $this->assertSame(0.0, $calc->calculate(10, 0));
        $this->assertSame(0.0, $calc->calculate(5, -10));
        $this->assertSame(0.0, $calc->calculate(0, 0));

        // Acertos negativos são forçados a 0.0
        $this->assertSame(0.0, $calc->calculate(-5, 10));

        // Acertos maiores que total são limitados (clamp) a 100.0
        $this->assertSame(100.0, $calc->calculate(150, 100));
        $this->assertSame(100.0, $calc->calculate(9999, 10));
    }

    // =========================================================================
    // 2. TESTES UNITÁRIOS: DomainSecurityValidator
    // =========================================================================

    public function testDomainSecurityValidatorDominiosValidos(): void
    {
        $validator = new DomainSecurityValidator();

        $this->assertTrue($validator->isAllowedOrigin('https://contextopolicial.com'));
        $this->assertTrue($validator->isAllowedOrigin('http://contextopolicial.com'));
        $this->assertTrue($validator->isAllowedOrigin('https://bm.contextopolicial.com'));
        $this->assertTrue($validator->isAllowedOrigin('https://aluno.adrenalinapolicial.com'));
        $this->assertTrue($validator->isAllowedOrigin('https://simulados.contextopolicial.com.br'));
        $this->assertTrue($validator->isAllowedOrigin('https://app.adrenalinapolicial.com.br'));
        $this->assertTrue($validator->isAllowedOrigin('bm.contextopolicial.com'));
    }

    public function testDomainSecurityValidatorBypassETentativasMaliciosas(): void
    {
        $validator = new DomainSecurityValidator();

        // Substring matches maliciosos devem ser sumariamente rejeitados
        $this->assertFalse($validator->isAllowedOrigin('https://evil-contextopolicial.com.attacker.org'));
        $this->assertFalse($validator->isAllowedOrigin('https://contextopolicial.com.attacker.com'));
        $this->assertFalse($validator->isAllowedOrigin('https://fakeadrenalinapolicial.com'));
        $this->assertFalse($validator->isAllowedOrigin('https://eviladrenalinapolicial.com.br.attacker.io'));
        $this->assertFalse($validator->isAllowedOrigin('https://google.com'));
        $this->assertFalse($validator->isAllowedOrigin('javascript:alert(1)'));
        $this->assertFalse($validator->isAllowedOrigin('data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='));
        $this->assertFalse($validator->isAllowedOrigin(''));
        $this->assertFalse($validator->isAllowedOrigin('   '));
    }

    // =========================================================================
    // 3. TESTES UNITÁRIOS: TafEvaluator
    // =========================================================================

    public function testTafEvaluatorMasculino(): void
    {
        $evaluator = new TafEvaluator();

        // Barra: mínimo 3
        $resBarraOk = $evaluator->evaluate('masculino', 'barra', 3.0);
        $this->assertSame('APROVADO', $resBarraOk['status']);
        $this->assertTrue($resBarraOk['aprovado']);
        $this->assertSame(3.0, $resBarraOk['minimo']);
        $this->assertSame(0.0, $resBarraOk['diferenca']);

        $resBarraFail = $evaluator->evaluate('masculino', 'barra', 2.0);
        $this->assertSame('REPROVADO', $resBarraFail['status']);
        $this->assertFalse($resBarraFail['aprovado']);
        $this->assertSame(-1.0, $resBarraFail['diferenca']);

        // Abdominal: mínimo 31
        $resAbdOk = $evaluator->evaluate('masculino', 'abdominal', 35.0);
        $this->assertSame('APROVADO', $resAbdOk['status']);
        $this->assertSame(4.0, $resAbdOk['diferenca']);

        $resAbdFail = $evaluator->evaluate('masculino', 'abdominal', 30.0);
        $this->assertSame('REPROVADO', $resAbdFail['status']);

        // Impulsão: mínimo 1.86m
        $resImpOk = $evaluator->evaluate('masculino', 'impulsao', 1.90);
        $this->assertSame('APROVADO', $resImpOk['status']);
        $resImpFail = $evaluator->evaluate('masculino', 'impulsao', 1.85);
        $this->assertSame('REPROVADO', $resImpFail['status']);

        // Corrida (12 min): mínimo 2400m
        $resCorridaOk = $evaluator->evaluate('masculino', 'corrida', 2400.0);
        $this->assertSame('APROVADO', $resCorridaOk['status']);
        $resCorridaFail = $evaluator->evaluate('masculino', 'corrida_12min', 2399.0);
        $this->assertSame('REPROVADO', $resCorridaFail['status']);
    }

    public function testTafEvaluatorFeminino(): void
    {
        $evaluator = new TafEvaluator();

        // Barra isométrica: mínimo 10 segundos
        $resBarraOk = $evaluator->evaluate('feminino', 'barra', 10.0);
        $this->assertSame('APROVADO', $resBarraOk['status']);
        $this->assertTrue($resBarraOk['aprovado']);

        $resBarraFail = $evaluator->evaluate('feminino', 'barra', 9.5);
        $this->assertSame('REPROVADO', $resBarraFail['status']);
        $this->assertFalse($resBarraFail['aprovado']);

        // Abdominal: mínimo 25
        $resAbdOk = $evaluator->evaluate('feminino', 'abdominal', 25.0);
        $this->assertSame('APROVADO', $resAbdOk['status']);

        $resAbdFail = $evaluator->evaluate('feminino', 'abdominal', 24.0);
        $this->assertSame('REPROVADO', $resAbdFail['status']);

        // Corrida (12 min): mínimo 2000m
        $resCorridaOk = $evaluator->evaluate('feminino', 'corrida', 2050.0);
        $this->assertSame('APROVADO', $resCorridaOk['status']);
        $this->assertSame(50.0, $resCorridaOk['diferenca']);

        $resCorridaFail = $evaluator->evaluate('feminino', 'corrida_12min', 1999.0);
        $this->assertSame('REPROVADO', $resCorridaFail['status']);
    }

    public function testTafEvaluatorNormalizacaoEErrosDeParametro(): void
    {
        $evaluator = new TafEvaluator();

        // Case insensitivity e espaços
        $res = $evaluator->evaluate(' MASCULINO ', '  BARRA  ', 5.0);
        $this->assertSame('APROVADO', $res['status']);

        // Sexo inválido lança InvalidArgumentException
        $exceptionLancada = false;
        try {
            $evaluator->evaluate('invalido', 'barra', 5.0);
        } catch (InvalidArgumentException $e) {
            $exceptionLancada = true;
        }
        $this->assertTrue($exceptionLancada, 'Deveria ter lançado InvalidArgumentException para sexo desconhecido');

        // Teste inválido lança InvalidArgumentException
        $exceptionLancadaTeste = false;
        try {
            $evaluator->evaluate('masculino', 'teste_inexistente', 10.0);
        } catch (InvalidArgumentException $e) {
            $exceptionLancadaTeste = true;
        }
        $this->assertTrue($exceptionLancadaTeste, 'Deveria ter lançado InvalidArgumentException para tipo de teste inexistente');
    }

    // =========================================================================
    // 4. TESTES UNITÁRIOS: JsonSecurityEscaper
    // =========================================================================

    public function testJsonSecurityEscaperBlindagemContraXss(): void
    {
        $escaper = new JsonSecurityEscaper();

        $payloadsMaliciosos = [
            'stored_tag'   => '</script><script>alert("XSS")</script>',
            'stored_attr'  => '" onmouseover="alert(1)"',
            'stored_single'=> "' onclick='stealCookies()'",
            'stored_amp'   => 'a & b < c > d',
            'acento'       => 'Português: aprovação no BM-MG!',
        ];

        $jsonSeguro = $escaper->escapeForHtml($payloadsMaliciosos);

        // Tags < e > devem ser convertidas para hex unicode (\u003C / \u003E)
        $this->assertStringNotContainsString('<script>', $jsonSeguro);
        $this->assertStringNotContainsString('</script>', $jsonSeguro);
        $this->assertTrue(
            stripos($jsonSeguro, '\u003cscript\u003e') !== false || stripos($jsonSeguro, '\u003c') !== false,
            'Tag de abertura <script> deve ser escapada em unicode hex.'
        );
        $this->assertTrue(
            stripos($jsonSeguro, '\u003c\/script\u003e') !== false || stripos($jsonSeguro, '\u003c/script\u003e') !== false,
            'Tag de fechamento </script> deve ser escapada em unicode hex.'
        );

        // Aspas simples e duplas internas e & devem ser escapadas
        $this->assertTrue(stripos($jsonSeguro, '\u0026') !== false, '& deve ser escapado como \u0026');
        $this->assertTrue(stripos($jsonSeguro, '\u0027') !== false, "' deve ser escapado como \u0027");

        // Acentos devem permanecer legíveis em UTF-8 (JSON_UNESCAPED_UNICODE)
        $this->assertStringContainsString('aprovação', $jsonSeguro);

        // Garante que é JSON perfeitamente válido ao decodificar de volta
        $decoded = json_decode($jsonSeguro, true);
        $this->assertSame($payloadsMaliciosos['stored_tag'], $decoded['stored_tag']);
        $this->assertSame($payloadsMaliciosos['acento'], $decoded['acento']);
    }
}
