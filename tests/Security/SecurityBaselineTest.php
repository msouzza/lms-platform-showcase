<?php

namespace Tests\Security;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\HttpTestClient;

/**
 * Suíte de Auditoria de Segurança (AppSec Baseline)
 * Valida controles de acesso, proteção de conteúdo estático, CORS, XSS e integridade de notas.
 */
class SecurityBaselineTest extends TestCase
{
    private PDO $pdo;
    private HttpTestClient $client;
    private int $escolaAtivaId;
    private array $testAlunoIds = [];

    protected function setUp(): void
    {
        $this->pdo    = getTestPdo();
        $this->client = new HttpTestClient();

        $stmt = $this->pdo->prepare("SELECT id FROM config_escolas WHERE slug = 'bm' LIMIT 1");
        $stmt->execute();
        $this->escolaAtivaId = (int) ($stmt->fetchColumn() ?: 1);
    }

    protected function tearDown(): void
    {
        if (!empty($this->testAlunoIds)) {
            $placeholders = implode(',', array_fill(0, count($this->testAlunoIds), '?'));
            $this->pdo->prepare("DELETE FROM progresso_alunos WHERE aluno_id IN ($placeholders)")->execute($this->testAlunoIds);
            $this->pdo->prepare("DELETE FROM anotacoes_alunos WHERE aluno_id IN ($placeholders)")->execute($this->testAlunoIds);
            $this->pdo->prepare("DELETE FROM resultados_simulados WHERE aluno_id IN ($placeholders)")->execute($this->testAlunoIds);
            $this->pdo->prepare("DELETE FROM alunos WHERE id IN ($placeholders)")->execute($this->testAlunoIds);
        }
    }

    private function criarELogarAluno(?string $nome = 'Aluno Sec'): array
    {
        $this->client->resetCookies();
        $email = 'sec_aluno_' . bin2hex(random_bytes(5)) . '@contextopolicial.com';
        $senha = 'SecPass@123';
        $stmt  = $this->pdo->prepare("
            INSERT INTO alunos (nome, email, senha, status, escola_origem, data_expiracao)
            VALUES (?, ?, ?, 'approved', ?, ?)
        ");
        $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $this->escolaAtivaId, date('Y-m-d H:i:s', strtotime('+10 days'))]);
        $id = (int) $this->pdo->lastInsertId();
        $this->testAlunoIds[] = $id;

        $this->client->postForm('processa_login.php', ['email' => $email, 'senha' => $senha]);
        return ['id' => $id, 'email' => $email, 'senha' => $senha];
    }

    public function testApisRejeitamRequisicoesSemAutenticacao(): void
    {
        $this->client->resetCookies();

        $resProg = $this->client->postJson('api_progresso.php', ['acao' => 'listar']);
        $this->assertContains($resProg['status'], [401, 403]);

        $resAnot = $this->client->get('api/api_anotacoes.php?acao=carregar&ref=teste');
        $this->assertContains($resAnot['status'], [401, 403]);

        $resTaf = $this->client->postJson('api/api_taf.php', ['acao' => 'salvar', 'dados' => []]);
        $this->assertContains($resTaf['status'], [401, 403]);

        $resQuiz = $this->client->postJson('api/salvar_quiz.php', ['quiz_ref' => 'q1']);
        $this->assertContains($resQuiz['status'], [401, 403]);

        $resSim = $this->client->postJson('api/salvar_simulado.php', ['simulado_ref' => 's1']);
        $this->assertContains($resSim['status'], [401, 403]);
    }

    public function testDirectoryTraversalEmVerResumoEhBloqueado(): void
    {
        $this->criarELogarAluno();

        $res = $this->client->get('ver_resumo.php?q=' . urlencode('components/../../libs/config.php'));
        $this->assertStringContainsString('inválido', strtolower($res['body']));
    }

    public function testCorsNaoDevePermitirDominioFalsoComSubstringMatch(): void
    {
        $evilOrigin = 'https://evil-contextopolicial.com.attacker.org';
        $res = $this->client->postJson('api_progresso.php', ['acao' => 'listar'], [
            "Origin: {$evilOrigin}",
        ]);

        $allowOrigin = $res['headers']['access-control-allow-origin'] ?? '';
        $this->assertNotSame(
            $evilOrigin,
            $allowOrigin,
            'VULNERABILIDADE CORS: Domínio externo contendo a substring contextopolicial.com foi aceito no Access-Control-Allow-Origin!'
        );
    }

    public function testVerQuizDeveRestringirApenasArquivosDaPastaComponents(): void
    {
        $this->criarELogarAluno();

        $res = $this->client->get('ver_quiz.php?q=teste-acesso-admin.html');
        $this->assertStringNotContainsString(
            'Sistema de Quiz Iniciado',
            $res['body'],
            'VULNERABILIDADE LFI/PATH: ver_quiz.php permitiu carregar um arquivo HTML fora da pasta components/!'
        );
    }

    public function testSalvarSimuladoNaoDeveAceitarNotaAcimaDe100EnviadaPeloCliente(): void
    {
        $aluno = $this->criarELogarAluno();

        $res = $this->client->postJson('api/salvar_simulado.php', [
            'simulado_ref'   => 'simulado_01',
            'titulo'         => 'Simulado Adulterado',
            'acertos'        => 5,
            'total_questoes' => 10,
            'nota'           => 9999.99, // Tentativa de adulterar a média da escola
            'data_inicio'    => '2026-10-27 10:00:00',
            'data_fim'       => '2026-10-27 11:00:00',
            'tempo_decorrido'=> '01:00:00',
            'detalhes'       => [],
        ]);

        $stmt = $this->pdo->prepare("SELECT nota FROM resultados_simulados WHERE aluno_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$aluno['id']]);
        $notaSalva = (float) $stmt->fetchColumn();

        $this->assertLessThanOrEqual(
            100.0,
            $notaSalva,
            "VULNERABILIDADE DE INTEGRIDADE: salvar_simulado.php gravou nota {$notaSalva} (> 100) sem recalcular ou validar no servidor!"
        );
    }

    public function testOrgEstudosNaoDevePermitirStoredXssViaConfigOrg(): void
    {
        $this->criarELogarAluno();

        $this->client->postJson('api_progresso.php', [
            'acao' => 'marcar',
            'tipo' => 'ConfigOrg',
            'ref'  => '{"malicioso":"</script><script>alert(1)</script>"}',
        ]);

        $res = $this->client->get('orgEstudos.php');
        $this->assertStringNotContainsString(
            '</script><script>alert(1)</script>',
            $res['body'],
            'VULNERABILIDADE STORED XSS: orgEstudos.php injetou </script> sem escape dentro da tag <script>!'
        );
    }

    public function testConteudoPagoEmComponentsNaoDeveSerAcessivelDiretamenteSemLogin(): void
    {
        $this->client->resetCookies();

        $res = $this->client->get('components/resumos/Direito_DH/resumo_01.html');
        $this->assertContains(
            $res['status'],
            [401, 403, 404],
            "VAZAMENTO DE CONTEÚDO PAGO: components/resumos/Direito_DH/resumo_01.html retornou HTTP {$res['status']} sem login!"
        );
    }

    public function testApiAnotacoesRejeitaAcessoSemAutenticacao(): void
    {
        $this->client->resetCookies();

        // 1. GET carregar sem login deve retornar 401
        $resGet = $this->client->get('api/api_anotacoes.php?acao=carregar&ref=resumo_teste.html');
        $this->assertSame(401, $resGet['status']);
        $this->assertSame('Sessão expirada ou usuário não logado', $resGet['json']['erro'] ?? null);

        // 2. POST salvar sem login deve retornar 401
        $resSalvar = $this->client->postJson('api/api_anotacoes.php', [
            'acao'  => 'salvar',
            'ref'   => 'resumo_teste.html',
            'tipo'  => 'highlight',
            'dados' => ['text' => 'Tentativa deslogada'],
        ]);
        $this->assertSame(401, $resSalvar['status']);
        $this->assertSame('Sessão expirada ou usuário não logado', $resSalvar['json']['erro'] ?? null);

        // 3. POST excluir sem login deve retornar 401
        $resExcluir = $this->client->postJson('api/api_anotacoes.php', [
            'acao' => 'excluir',
            'id'   => 99999,
        ]);
        $this->assertSame(401, $resExcluir['status']);
        $this->assertSame('Sessão expirada ou usuário não logado', $resExcluir['json']['erro'] ?? null);
    }

    public function testIdorEmApiAnotacoesImpedeAlunoBDeLerOuExcluirAnotacaoDoAlunoA(): void
    {
        $resumoRef = 'Direito_DH/resumo_idor_' . bin2hex(random_bytes(4)) . '.html';

        // 1. Aluno A cria uma anotação privada
        $this->criarELogarAluno('Aluno A');
        $resSalvarA = $this->client->postJson('api/api_anotacoes.php', [
            'acao'  => 'salvar',
            'ref'   => $resumoRef,
            'tipo'  => 'annotation',
            'dados' => ['text' => 'Minha anotação confidencial do Aluno A'],
        ]);
        $idAnotacaoA = (int) ($resSalvarA['json']['id'] ?? 0);
        $this->assertGreaterThan(0, $idAnotacaoA);

        // 2. Aluno B faz login e tenta carregar as anotações do mesmo resumo
        $this->criarELogarAluno('Aluno B');
        $resCarregarB = $this->client->get('api/api_anotacoes.php?acao=carregar&ref=' . urlencode($resumoRef));
        $this->assertSame(200, $resCarregarB['status']);
        $this->assertStringNotContainsString('Minha anotação confidencial do Aluno A', $resCarregarB['body']);
        $this->assertSame([], $resCarregarB['json']['annotations'] ?? null);

        // 3. Aluno B tenta forçar a exclusão da anotação do Aluno A pelo ID
        $resExcluir = $this->client->postJson('api/api_anotacoes.php', [
            'acao' => 'excluir',
            'id'   => $idAnotacaoA,
        ]);
        $this->assertSame(200, $resExcluir['status']);

        // 4. Confirma no banco que a anotação do Aluno A NÃO foi apagada
        $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM anotacoes_alunos WHERE id = ?");
        $stmtCheck->execute([$idAnotacaoA]);
        $this->assertSame(1, (int) $stmtCheck->fetchColumn());
    }

    public function testSalvarQuizTambemRecalculaNotaNoServidorContraAdulteracao(): void
    {
        $aluno = $this->criarELogarAluno();

        $this->client->postJson('api/salvar_quiz.php', [
            'quiz_ref'       => 'quizzes_01',
            'titulo'         => 'Quiz Adulterado',
            'acertos'        => 3,
            'total_questoes' => 10,
            'nota'           => 5000.00,
            'detalhes'       => [],
        ]);

        $stmt = $this->pdo->prepare("SELECT nota FROM resultados_quizzes WHERE aluno_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$aluno['id']]);
        $notaSalva = (float) $stmt->fetchColumn();

        $this->assertSame(30.0, $notaSalva);
    }

    public function testIdorEmVerCorrecaoImpedeAlunoBDeVerGabaritoDoAlunoA(): void
    {
        // 1. Aluno A salva um simulado com dados exclusivos
        $this->criarELogarAluno('Aluno A');
        $resSimA = $this->client->postJson('api/salvar_simulado.php', [
            'simulado_ref'   => 'simulado_01',
            'titulo'         => 'Simulado Secreto do Aluno A',
            'acertos'        => 45,
            'total_questoes' => 50,
            'nota'           => 90,
            'data_inicio'    => '2026-10-27 08:00:00',
            'data_fim'       => '2026-10-27 10:00:00',
            'tempo_decorrido'=> '02:00:00',
            'detalhes'       => [
                [
                    'questao'         => 'Pergunta Confidencial Aluno A',
                    'respostaCorreta' => 'B',
                    'respostaAluno'   => 'B',
                    'acertou'         => true,
                    'comentario'      => 'Comentário privado',
                ],
            ],
        ]);
        $idSimuladoAlunoA = (int) ($resSimA['json']['id'] ?? 0);
        $this->assertGreaterThan(0, $idSimuladoAlunoA);

        // 2. Aluno B faz login e tenta acessar ver_correcao.php passando o ID do simulado do Aluno A
        $this->criarELogarAluno('Aluno B');
        $resIdor = $this->client->get("ver_correcao.php?id={$idSimuladoAlunoA}&tipo=simulado");

        $this->assertStringContainsString('Resultado não encontrado', $resIdor['body']);
        $this->assertStringNotContainsString('Pergunta Confidencial Aluno A', $resIdor['body']);
    }

    public function testIsolamentoDeResultadosIndividualEmMeusResultados(): void
    {
        // 1. Aluno A salva um simulado
        $this->criarELogarAluno('Aluno A');
        $this->client->postJson('api/salvar_simulado.php', [
            'simulado_ref'   => 'simulado_02',
            'titulo'         => 'Simulado Exclusivo Do Aluno A #991',
            'acertos'        => 35,
            'total_questoes' => 50,
            'nota'           => 70,
            'data_inicio'    => '2026-10-27 08:00:00',
            'data_fim'       => '2026-10-27 10:00:00',
            'tempo_decorrido'=> '02:00:00',
            'detalhes'       => [],
        ]);

        // 2. Aluno B abre meus_resultados.php e não deve ver o simulado do Aluno A na sua tabela
        $this->criarELogarAluno('Aluno B');
        $resB = $this->client->get('meus_resultados.php');
        $this->assertSame(200, $resB['status']);
        $this->assertStringNotContainsString('Simulado Exclusivo Do Aluno A #991', $resB['body']);
    }

    public function testTentativaDePathTraversalEmVerQuizEVerSimuladoEhBloqueada(): void
    {
        $this->criarELogarAluno();

        // 1. Tentativa de Path Traversal em ver_quiz.php
        $resQuiz = $this->client->get('ver_quiz.php?q=' . urlencode('../../../../etc/passwd'));
        $this->assertStringContainsString('inválido', strtolower($resQuiz['body']));
        $this->assertStringNotContainsString('Sistema de Quiz Iniciado', $resQuiz['body']);

        // 2. Tentativa de Path Traversal em ver_simulado.php usando parâmetro 'q' e 's'
        $resSimQ = $this->client->get('ver_simulado.php?q=' . urlencode('../../etc/passwd'));
        $this->assertStringContainsString('não permitido', strtolower($resSimQ['body']));
        $this->assertStringNotContainsString('Sistema Iniciado', $resSimQ['body']);

        $resSimS = $this->client->get('ver_simulado.php?s=' . urlencode('../../'));
        $this->assertStringContainsString('não permitido', strtolower($resSimS['body']));
        $this->assertStringNotContainsString('Sistema Iniciado', $resSimS['body']);
    }
}

