<?php

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\HttpTestClient;

class RegressionTest extends TestCase
{
    private PDO $pdo;
    private HttpTestClient $client;
    private int $escolaAtivaId;
    private int $escolaExternaId;
    private string $familiaAtiva;
    private array $testAlunoIds = [];
    private string $senhaPadrao = 'SenhaForte@123';

    protected function setUp(): void
    {
        $this->pdo    = getTestPdo();
        $this->client = new HttpTestClient();

        // Garante que exista uma escola compatível tanto com /bm.contextopolicial.com-exame quanto com 127.0.0.1 / localhost
        $stmt = $this->pdo->prepare("SELECT id, familia_sessao FROM config_escolas WHERE slug = 'bm' LIMIT 1");
        $stmt->execute();
        $escolaBm = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($escolaBm) {
            $this->escolaAtivaId = (int) $escolaBm['id'];
            $this->familiaAtiva  = $escolaBm['familia_sessao'];
        } else {
            $this->familiaAtiva = 'adrenalina';
            $this->pdo->prepare("
                INSERT INTO config_escolas (slug, nome_display, familia_sessao, tipo, url_vitrine, url_hub)
                VALUES ('bm', 'Bombeiro Militar Teste', ?, 'vitrine', 'http://127.0.0.1', 'http://127.0.0.1')
            ")->execute([$this->familiaAtiva]);
            $this->escolaAtivaId = (int) $this->pdo->lastInsertId();
        }

        // Garante escola de outra família para testar bloqueio entre escolas diferentes
        $stmtExt = $this->pdo->prepare("SELECT id FROM config_escolas WHERE slug = 'escola_isolada_ci' LIMIT 1");
        $stmtExt->execute();
        $extId = $stmtExt->fetchColumn();
        if ($extId) {
            $this->escolaExternaId = (int) $extId;
        } else {
            $this->pdo->prepare("
                INSERT INTO config_escolas (slug, nome_display, familia_sessao, tipo, url_vitrine, url_hub)
                VALUES ('escola_isolada_ci', 'Escola Isolada CI', 'familia_diferente_ci', 'escola', 'https://isolada.local', 'https://isolada.local')
            ")->execute();
            $this->escolaExternaId = (int) $this->pdo->lastInsertId();
        }
    }

    protected function tearDown(): void
    {
        if (!empty($this->testAlunoIds)) {
            $placeholders = implode(',', array_fill(0, count($this->testAlunoIds), '?'));
            $this->pdo->prepare("DELETE FROM progresso_alunos WHERE aluno_id IN ($placeholders)")->execute($this->testAlunoIds);
            $this->pdo->prepare("DELETE FROM anotacoes_alunos WHERE aluno_id IN ($placeholders)")->execute($this->testAlunoIds);
            $this->pdo->prepare("DELETE FROM historico_taf WHERE aluno_id IN ($placeholders)")->execute($this->testAlunoIds);
            $this->pdo->prepare("DELETE FROM resultados_quizzes WHERE aluno_id IN ($placeholders)")->execute($this->testAlunoIds);
            $this->pdo->prepare("DELETE FROM resultados_simulados WHERE aluno_id IN ($placeholders)")->execute($this->testAlunoIds);
            $this->pdo->prepare("DELETE FROM alunos WHERE id IN ($placeholders)")->execute($this->testAlunoIds);
        }
    }

    private function criarAlunoFixture(string $status = 'approved', ?string $dataExpiracao = '+30 days', ?int $escolaId = null): array
    {
        $email = 'ci_aluno_' . bin2hex(random_bytes(5)) . '@contextopolicial.com';
        $hash  = password_hash($this->senhaPadrao, PASSWORD_DEFAULT);
        $exp   = $dataExpiracao ? date('Y-m-d H:i:s', strtotime($dataExpiracao)) : null;
        $esc   = $escolaId ?? $this->escolaAtivaId;

        $stmt = $this->pdo->prepare("
            INSERT INTO alunos (nome, email, senha, status, escola_origem, data_expiracao)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['Aluno Automatizado CI', $email, $hash, $status, $esc, $exp]);
        $id = (int) $this->pdo->lastInsertId();
        $this->testAlunoIds[] = $id;

        return ['id' => $id, 'email' => $email, 'senha' => $this->senhaPadrao];
    }

    private function autenticarAluno(array $aluno): array
    {
        return $this->client->postForm('processa_login.php', [
            'email' => $aluno['email'],
            'senha' => $aluno['senha'],
        ]);
    }

    public function testLoginComCredenciaisValidasRedirecionaParaAreaDoAluno(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $res   = $this->autenticarAluno($aluno);

        $this->assertContains($res['status'], [200, 302]);
        $this->assertStringContainsString('area_aluno.php', $res['headers']['location'] ?? '');

        $dash = $this->client->get('area_aluno.php');
        $this->assertSame(200, $dash['status']);
        $this->assertStringContainsString('CONCURSO BOMBEIRO MILITAR DE MG', $dash['body']);
        $this->assertStringNotContainsString('Acesso Bloqueado Temporariamente', $dash['body']);
    }

    public function testLoginComSenhaIncorretaEhBloqueado(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $res   = $this->client->postForm('processa_login.php', [
            'email' => $aluno['email'],
            'senha' => 'SenhaErrada999',
        ]);

        $this->assertStringContainsString('erro=dados_incorretos', $res['headers']['location'] ?? '');
    }

    public function testLoginComAssinaturaVencidaEhBloqueado(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '-5 days');
        $res   = $this->autenticarAluno($aluno);

        $this->assertStringContainsString('erro=assinatura_vencida', $res['headers']['location'] ?? '');
    }

    public function testLoginComContaPendenteEhBloqueado(): void
    {
        $aluno = $this->criarAlunoFixture('pending', '+30 days');
        $res   = $this->autenticarAluno($aluno);

        $this->assertStringContainsString('erro=conta_pendente', $res['headers']['location'] ?? '');
    }

    public function testLoginDeAlunoDeOutraEscolaEhBloqueado(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days', $this->escolaExternaId);
        $res   = $this->autenticarAluno($aluno);

        $this->assertStringContainsString('erro=acesso_negado_escola', $res['headers']['location'] ?? '');
    }

    public function testApiProgressoFluxoCompletoDeMarcarListarDesmarcarESalvarConfigOrg(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $this->autenticarAluno($aluno);

        // 1. Marcar um conteúdo como concluído
        $resMarcar = $this->client->postJson('api_progresso.php', [
            'acao' => 'marcar',
            'ref'  => 'components/resumos/Direito_DH/resumo_01.html',
            'tipo' => 'Resumo',
        ]);
        $this->assertSame(200, $resMarcar['status']);
        $this->assertSame('sucesso', $resMarcar['json']['status'] ?? null);

        // 2. Listar conteúdos concluídos
        $resListar = $this->client->postJson('api_progresso.php', ['acao' => 'listar']);
        $this->assertSame(200, $resListar['status']);
        $this->assertContains('components/resumos/Direito_DH/resumo_01.html', $resListar['json']['concluidas'] ?? []);

        // 3. Desmarcar conteúdo
        $resDesmarcar = $this->client->postJson('api_progresso.php', [
            'acao' => 'desmarcar',
            'ref'  => 'components/resumos/Direito_DH/resumo_01.html',
            'tipo' => 'Resumo',
        ]);
        $this->assertSame(200, $resDesmarcar['status']);
        $this->assertSame('sucesso', $resDesmarcar['json']['status'] ?? null);

        // 4. Salvar configuração do Organizador de Estudos (ConfigOrg)
        $resConfig = $this->client->postJson('api_progresso.php', [
            'acao'   => 'salvar_config_org',
            'config' => ['horasPorDia' => 3, 'dataInicio' => '2026-10-27'],
        ]);
        $this->assertSame(200, $resConfig['status']);
        $this->assertSame('sucesso', $resConfig['json']['status'] ?? null);
    }

    public function testApiProgressoSincronizarLoteEAtualizarAulaERefletirNoOrgEstudos(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $this->autenticarAluno($aluno);

        // 1. Sincronizar lote de tópicos e dia pulado do Organizador
        $resSync = $this->client->postJson('api_progresso.php', [
            'acao'  => 'sincronizar',
            'lista' => [
                ['ref' => 'topico_dh_01', 'tipo' => 'Organizador'],
                ['ref' => 'topico_mat_02', 'tipo' => 'Organizador'],
                ['ref' => '2026-10-28', 'tipo' => 'DiaPulado'],
            ],
        ]);
        $this->assertSame(200, $resSync['status']);
        $this->assertSame('sucesso', $resSync['json']['status'] ?? null);

        // 2. Verifica se orgEstudos.php carrega os tópicos sincronizados e o dia pulado
        $resOrg = $this->client->get('orgEstudos.php');
        $this->assertSame(200, $resOrg['status']);
        $this->assertStringContainsString('topico_dh_01', $resOrg['body']);
        $this->assertStringContainsString('2026-10-28', $resOrg['body']);

        // 3. Testa ação 'atualizar' (marcar concluído = true e depois false)
        $resAtuTrue = $this->client->postJson('api_progresso.php', [
            'acao'        => 'atualizar',
            'conteudo_ref'=> 'aula_BM_01',
            'tipo'        => 'aula',
            'concluido'   => true,
        ]);
        $this->assertSame('sucesso', $resAtuTrue['json']['status'] ?? null);

        $resAtuFalse = $this->client->postJson('api_progresso.php', [
            'acao'        => 'atualizar',
            'conteudo_ref'=> 'aula_BM_01',
            'tipo'        => 'aula',
            'concluido'   => false,
        ]);
        $this->assertSame('sucesso', $resAtuFalse['json']['status'] ?? null);
    }


    public function testApiAnotacoesSalvarCarregarEExcluir(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $this->autenticarAluno($aluno);

        $resumoRef = 'Direito_DH/resumo_01.html';

        // 1. Salvar grifo (highlight)
        $resSalvar = $this->client->postJson('api/api_anotacoes.php', [
            'acao'  => 'salvar',
            'ref'   => $resumoRef,
            'tipo'  => 'highlight',
            'dados' => ['text' => 'Artigo 5 da CF', 'selector' => 'p:nth-of-type(1)'],
        ]);
        $this->assertSame(200, $resSalvar['status']);
        $this->assertSame('sucesso', $resSalvar['json']['status'] ?? null);
        $anotacaoId = (int) ($resSalvar['json']['id'] ?? 0);
        $this->assertGreaterThan(0, $anotacaoId);

        // 2. Carregar anotações do resumo
        $resCarregar = $this->client->get('api/api_anotacoes.php?acao=carregar&ref=' . urlencode($resumoRef));
        $this->assertSame(200, $resCarregar['status']);
        $this->assertNotEmpty($resCarregar['json']['highlights'] ?? []);
        $this->assertSame('Artigo 5 da CF', $resCarregar['json']['highlights'][0]['text'] ?? null);

        // 3. Excluir anotação
        $resExcluir = $this->client->postJson('api/api_anotacoes.php', [
            'acao' => 'excluir',
            'id'   => $anotacaoId,
        ]);
        $this->assertSame(200, $resExcluir['status']);
        $this->assertSame('sucesso', $resExcluir['json']['status'] ?? null);
    }

    public function testApiAnotacoesFluxoCompletoDestaquesEAnotacoesValidandoDadosRetornados(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $this->autenticarAluno($aluno);

        $resumoRef = 'Direito_DH/resumo_exemplo_' . bin2hex(random_bytes(4)) . '.html';

        $dadosHighlight = [
            'text'     => 'Artigo 5º Todos são iguais perante a lei',
            'color'    => '#ffeb3b',
            'selector' => 'p.artigo-5',
            'offset'   => 42,
        ];

        $dadosAnnotation = [
            'text'       => 'Ponto fundamental para o concurso BM-MG',
            'nota'       => 'Lembrar de revisar este tópico na véspera',
            'posicaoTop' => 280,
            'corCard'    => 'amarelo',
        ];

        // 1. Salvar destaque (highlight)
        $resHighlight = $this->client->postJson('api/api_anotacoes.php', [
            'acao'  => 'salvar',
            'ref'   => $resumoRef,
            'tipo'  => 'highlight',
            'dados' => $dadosHighlight,
        ]);
        $this->assertSame(200, $resHighlight['status']);
        $this->assertSame('sucesso', $resHighlight['json']['status'] ?? null);
        $idHighlight = (int) ($resHighlight['json']['id'] ?? 0);
        $this->assertGreaterThan(0, $idHighlight);

        // 2. Salvar anotação (annotation)
        $resAnotacao = $this->client->postJson('api/api_anotacoes.php', [
            'acao'  => 'salvar',
            'ref'   => $resumoRef,
            'tipo'  => 'annotation',
            'dados' => $dadosAnnotation,
        ]);
        $this->assertSame(200, $resAnotacao['status']);
        $this->assertSame('sucesso', $resAnotacao['json']['status'] ?? null);
        $idAnotacao = (int) ($resAnotacao['json']['id'] ?? 0);
        $this->assertGreaterThan(0, $idAnotacao);

        // 3. Carregar as anotações e destaques do resumo
        $resCarregar = $this->client->get('api/api_anotacoes.php?acao=carregar&ref=' . urlencode($resumoRef));
        $this->assertSame(200, $resCarregar['status']);
        $this->assertNotEmpty($resCarregar['json']['highlights'] ?? []);
        $this->assertNotEmpty($resCarregar['json']['annotations'] ?? []);

        // Validar que os dados retornados do highlight batem exatamente com os enviados
        $itemHighlight = $resCarregar['json']['highlights'][0];
        $this->assertSame($idHighlight, (int) ($itemHighlight['db_id'] ?? 0));
        $this->assertSame($dadosHighlight['text'], $itemHighlight['text'] ?? null);
        $this->assertSame($dadosHighlight['color'], $itemHighlight['color'] ?? null);
        $this->assertSame($dadosHighlight['selector'], $itemHighlight['selector'] ?? null);
        $this->assertSame($dadosHighlight['offset'], $itemHighlight['offset'] ?? null);

        // Validar que os dados retornados da anotação batem exatamente com os enviados
        $itemAnotacao = $resCarregar['json']['annotations'][0];
        $this->assertSame($idAnotacao, (int) ($itemAnotacao['db_id'] ?? 0));
        $this->assertSame($dadosAnnotation['text'], $itemAnotacao['text'] ?? null);
        $this->assertSame($dadosAnnotation['nota'], $itemAnotacao['nota'] ?? null);
        $this->assertSame($dadosAnnotation['posicaoTop'], $itemAnotacao['posicaoTop'] ?? null);
        $this->assertSame($dadosAnnotation['corCard'], $itemAnotacao['corCard'] ?? null);

        // 4. Excluir os itens criados
        $resExcluirH = $this->client->postJson('api/api_anotacoes.php', [
            'acao' => 'excluir',
            'id'   => $idHighlight,
        ]);
        $this->assertSame(200, $resExcluirH['status']);
        $this->assertSame('sucesso', $resExcluirH['json']['status'] ?? null);

        $resExcluirA = $this->client->postJson('api/api_anotacoes.php', [
            'acao' => 'excluir',
            'id'   => $idAnotacao,
        ]);
        $this->assertSame(200, $resExcluirA['status']);
        $this->assertSame('sucesso', $resExcluirA['json']['status'] ?? null);

        // 5. Verificar que a lista ficou vazia
        $resPosExclusao = $this->client->get('api/api_anotacoes.php?acao=carregar&ref=' . urlencode($resumoRef));
        $this->assertSame(200, $resPosExclusao['status']);
        $this->assertSame([], $resPosExclusao['json']['highlights'] ?? null);
        $this->assertSame([], $resPosExclusao['json']['annotations'] ?? null);
    }

    public function testApiAnotacoesTratamentoDeErrosEParametrosInvalidos(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $this->autenticarAluno($aluno);

        // 1. Salvar sem parâmetro 'ref' (espera 500 Erro interno)
        $resSemRef = $this->client->postJson('api/api_anotacoes.php', [
            'acao'  => 'salvar',
            'tipo'  => 'highlight',
            'dados' => ['text' => 'Teste sem ref'],
        ]);
        $this->assertSame(500, $resSemRef['status']);
        $this->assertStringContainsString('Erro interno', $resSemRef['json']['erro'] ?? '');

        // 2. Salvar sem parâmetro 'tipo' (espera 500 Erro interno)
        $resSemTipo = $this->client->postJson('api/api_anotacoes.php', [
            'acao'  => 'salvar',
            'ref'   => 'resumo_teste.html',
            'dados' => ['text' => 'Teste sem tipo'],
        ]);
        $this->assertSame(500, $resSemTipo['status']);
        $this->assertStringContainsString('Erro interno', $resSemTipo['json']['erro'] ?? '');

        // 3. Carregar sem parâmetro 'ref' (espera 500 Erro interno)
        $resCarregarSemRef = $this->client->get('api/api_anotacoes.php?acao=carregar');
        $this->assertSame(500, $resCarregarSemRef['status']);
        $this->assertStringContainsString('Erro interno', $resCarregarSemRef['json']['erro'] ?? '');
    }

    public function testApiTafSalvarIgnorarDuplicataCarregarPainelEExcluirDiaDeTreino(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $this->autenticarAluno($aluno);

        $dataTreino = '2026-10-25';
        $payloadTreino = [
            'acao'  => 'salvar',
            'dados' => [
                ['data' => $dataTreino, 'sexo' => 'masculino', 'id' => 'barra', 'resultadoNumerico' => 10],
                ['data' => $dataTreino, 'sexo' => 'masculino', 'id' => 'abdominal', 'resultadoNumerico' => 45],
            ],
        ];

        // 1. Salvar bateria de exercícios do TAF (deve inserir 2 registros)
        $resSalvar = $this->client->postJson('api/api_taf.php', $payloadTreino);
        $this->assertSame(200, $resSalvar['status']);
        $this->assertSame('sucesso', $resSalvar['json']['status'] ?? null);
        $this->assertSame(2, $resSalvar['json']['inseridos'] ?? null);

        // 2. Enviar o mesmo treino novamente deve ignorar as duplicatas (inseridos = 0, ignorados = 2)
        $resDuplicado = $this->client->postJson('api/api_taf.php', $payloadTreino);
        $this->assertSame(0, $resDuplicado['json']['inseridos'] ?? null);
        $this->assertSame(2, $resDuplicado['json']['ignorados'] ?? null);

        // 3. Carregar a interface visual do TAF (teste_fisico/taf.php) e verificar que o histórico está presente
        $resPainelTaf = $this->client->get('teste_fisico/taf.php');
        $this->assertSame(200, $resPainelTaf['status']);
        $this->assertStringContainsString($dataTreino, $resPainelTaf['body']);

        // 4. Excluir o dia de treino
        $resExcluir = $this->client->postJson('api/api_taf.php', [
            'acao' => 'excluir_dia',
            'data' => $dataTreino,
            'sexo' => 'masculino',
        ]);
        $this->assertSame(200, $resExcluir['status']);
        $this->assertSame('sucesso', $resExcluir['json']['status'] ?? null);
    }

    public function testSalvarQuizESimuladoEVisualizarEmMeusResultadosEVerCorrecao(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $this->autenticarAluno($aluno);

        // 1. Salvar Quiz (enviando nota adulterada de 999 para validar que o servidor recalcula para 80.0)
        $resQuiz = $this->client->postJson('api/salvar_quiz.php', [
            'quiz_ref'       => 'quizzes_01',
            'titulo'         => 'Quiz Direito Constitucional',
            'acertos'        => 8,
            'total_questoes' => 10,
            'nota'           => 999.0, // Tentativa de nota arbitrária
            'detalhes'       => [
                [
                    'questao'         => 'Qual o artigo dos direitos fundamentais?',
                    'respostaCorreta' => 'Art. 5º',
                    'respostaAluno'   => 'Art. 5º',
                    'acertou'         => true,
                    'comentario'      => 'Correto, Art. 5º da CF/88.',
                ],
            ],
        ]);
        $this->assertSame(200, $resQuiz['status']);
        $this->assertTrue((bool) ($resQuiz['json']['success'] ?? false));
        $quizResultId = (int) ($resQuiz['json']['id'] ?? 0);
        $this->assertGreaterThan(0, $quizResultId);

        // Conferir no banco de dados se a nota gravada foi recalculada corretamente para 80.0
        $stmtQuizDb = $this->pdo->prepare("SELECT nota, acertos, total_questoes FROM resultados_quizzes WHERE id = ?");
        $stmtQuizDb->execute([$quizResultId]);
        $quizDb = $stmtQuizDb->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(80.0, (float) $quizDb['nota']);
        $this->assertSame(8, (int) $quizDb['acertos']);
        $this->assertSame(10, (int) $quizDb['total_questoes']);

        // 2. Salvar Simulado com tempo decorrido e detalhes
        $resSimulado = $this->client->postJson('api/salvar_simulado.php', [
            'simulado_ref'   => 'simulado_01',
            'titulo'         => '1º Simulado Oficial BM',
            'acertos'        => 40,
            'total_questoes' => 50,
            'nota'           => 80,
            'data_inicio'    => '2026-10-27 14:00:00',
            'data_fim'       => '2026-10-27 16:00:00',
            'tempo_decorrido'=> '02:00:00',
            'detalhes'       => [
                [
                    'questao'         => 'Questão 1 do Simulado',
                    'respostaCorreta' => 'A',
                    'respostaAluno'   => 'A',
                    'acertou'         => true,
                    'comentario'      => 'Gabarito Letra A.',
                ],
            ],
        ]);
        $this->assertSame(200, $resSimulado['status']);
        $this->assertTrue((bool) ($resSimulado['json']['success'] ?? false));
        $simuladoResultId = (int) ($resSimulado['json']['id'] ?? 0);
        $this->assertGreaterThan(0, $simuladoResultId);

        // Conferir no banco de dados se salvou com tempo_decorrido e detalhes_json
        $stmtSimDb = $this->pdo->prepare("SELECT nota, tempo_decorrido, detalhes_json FROM resultados_simulados WHERE id = ?");
        $stmtSimDb->execute([$simuladoResultId]);
        $simDb = $stmtSimDb->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(80.0, (float) $simDb['nota']);
        $this->assertSame('02:00:00', $simDb['tempo_decorrido']);
        $this->assertStringContainsString('Questão 1 do Simulado', $simDb['detalhes_json']);

        // 3. Verificar exibição em meus_resultados.php
        $resMeusResultados = $this->client->get('meus_resultados.php');
        $this->assertSame(200, $resMeusResultados['status']);
        $this->assertStringContainsString('Quiz Direito Constitucional', $resMeusResultados['body']);
        $this->assertStringContainsString('1º Simulado Oficial BM', $resMeusResultados['body']);

        // 4. Verificar gabarito detalhado em ver_correcao.php (Simulado e Quiz)
        $resCorrecaoSim = $this->client->get("ver_correcao.php?id={$simuladoResultId}&tipo=simulado");
        $this->assertSame(200, $resCorrecaoSim['status']);
        $this->assertStringContainsString('Questão 1 do Simulado', $resCorrecaoSim['body']);

        $resCorrecaoQuiz = $this->client->get("ver_correcao.php?id={$quizResultId}&tipo=quiz");
        $this->assertSame(200, $resCorrecaoQuiz['status']);
        $this->assertStringContainsString('Qual o artigo dos direitos fundamentais?', $resCorrecaoQuiz['body']);
    }

    public function testTodosOsQuatroWrappersDeConteudoCarregamQuandoAutenticado(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $this->autenticarAluno($aluno);

        // 1. Resumo
        $resResumo = $this->client->get('ver_resumo.php?q=' . urlencode('components/resumos/Direito_DH/resumo_01.html'));
        $this->assertSame(200, $resResumo['status']);
        $this->assertStringContainsString('window.resumoAppConfig', $resResumo['body']);

        // 2. Flashcard
        $resFlash = $this->client->get('ver_flashcard.php?q=' . urlencode('components/flashcard/Direito_DH/flash_01.html'));
        $this->assertSame(200, $resFlash['status']);
        $this->assertStringContainsString('Fechar Flashcard', $resFlash['body']);

        // 3. Quiz
        $resQuiz = $this->client->get('ver_quiz.php?q=' . urlencode('components/quizzes/Direito_DH/quizzes_01.html'));
        $this->assertSame(200, $resQuiz['status']);
        $this->assertStringContainsString('Sistema de Quiz Iniciado: quizzes_01', $resQuiz['body']);

        // 4. Simulado
        $resSimulado = $this->client->get('ver_simulado.php?q=' . urlencode('components/simulados/simulado_01.html'));
        $this->assertSame(200, $resSimulado['status']);
        $this->assertStringContainsString('/api/salvar_simulado.php', $resSimulado['body']);
    }

    public function testAlterarSenhaSemAutenticacaoRedirecionaParaLogin(): void
    {
        $res = $this->client->postForm('processa_perfil.php', [
            'acao' => 'alterar_senha',
            'senha_atual' => 'Qualquer123',
            'nova_senha' => 'NovaSenha@123',
            'confirmar_senha' => 'NovaSenha@123'
        ]);
        $location = $res['headers']['location'] ?? '';
        $this->assertTrue(
            str_contains($location, 'index.php') || str_contains($location, 'login.php'),
            "Deveria redirecionar para a tela de autenticação (index.php ou login.php). Recebido: {$location}"
        );
    }

    public function testEsqueciSenhaGetRedirecionaParaEsqueciSenhaPhp(): void
    {
        $res = $this->client->get('processa_esqueci_senha.php');
        $this->assertStringContainsString('esqueci_senha.php', $res['headers']['location'] ?? '');
    }

    public function testEsqueciSenhaComEmailVazioOuInexistenteRedirecionaComErroNaoEncontrado(): void
    {
        // E-mail vazio
        $resVazio = $this->client->postForm('processa_esqueci_senha.php', [
            'email' => ''
        ]);
        $this->assertStringContainsString('esqueci_senha.php?erro=nao_encontrado', $resVazio['headers']['location'] ?? '');

        // E-mail inexistente
        $resInexistente = $this->client->postForm('processa_esqueci_senha.php', [
            'email' => 'naoexiste@contextopolicial.com'
        ]);
        $this->assertStringContainsString('esqueci_senha.php?erro=nao_encontrado', $resInexistente['headers']['location'] ?? '');
    }

    public function testEsqueciSenhaComEmailValidoGeraTokenEDatasRedirecionaComSucesso(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');

        $res = $this->client->postForm('processa_esqueci_senha.php', [
            'email' => $aluno['email']
        ]);
        $location = $res['headers']['location'] ?? '';
        $this->assertTrue(strpos($location, 'esqueci_senha.php') !== false, 'Redirecionamento inesperado: ' . $location);

        // Verifica se gerou o reset_token e reset_token_expiracao no banco
        $stmt = $this->pdo->prepare("SELECT reset_token, reset_token_expiracao FROM alunos WHERE id = ?");
        $stmt->execute([$aluno['id']]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($dados['reset_token']);
        $this->assertNotEmpty($dados['reset_token_expiracao']);
        $this->assertGreaterThan(date('Y-m-d H:i:s'), $dados['reset_token_expiracao']);
    }

    public function testProcessaResetSemTokenRedirecionaParaIndex(): void
    {
        $res = $this->client->postForm('processa_reset.php', [
            'token' => '',
            'nova_senha' => 'NovaSenha@123',
            'confirma_senha' => 'NovaSenha@123'
        ]);
        $this->assertStringContainsString('index.php', $res['headers']['location'] ?? '');
    }

    public function testProcessaResetComSenhasDivergentesOuCurtas(): void
    {
        $tokenFalso = 'abc123token';

        // Senhas divergentes
        $resDivergentes = $this->client->postForm('processa_reset.php', [
            'token' => $tokenFalso,
            'nova_senha' => 'Senha123',
            'confirma_senha' => 'OutraSenha123'
        ]);
        $this->assertStringContainsString('erro=senhas_diferentes', $resDivergentes['headers']['location'] ?? '');

        // Senha curta
        $resCurta = $this->client->postForm('processa_reset.php', [
            'token' => $tokenFalso,
            'nova_senha' => '123',
            'confirma_senha' => '123'
        ]);
        $this->assertStringContainsString('erro=senha_curta', $resCurta['headers']['location'] ?? '');
    }

    public function testProcessaResetComTokenInexistenteOuVencidoRedireciona(): void
    {
        // Token inexistente
        $resInexistente = $this->client->postForm('processa_reset.php', [
            'token' => 'token_que_nao_existe_jamais',
            'nova_senha' => 'NovaSenha@123',
            'confirma_senha' => 'NovaSenha@123'
        ]);
        $this->assertStringContainsString('reset.php?token=', $resInexistente['headers']['location'] ?? '');

        // Token vencido
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $tokenVencido = bin2hex(random_bytes(16));
        $passado = date('Y-m-d H:i:s', strtotime('-2 hours'));
        
        $this->pdo->prepare("UPDATE alunos SET reset_token = ?, reset_token_expiracao = ? WHERE id = ?")
             ->execute([$tokenVencido, $passado, $aluno['id']]);

        $resVencido = $this->client->postForm('processa_reset.php', [
            'token' => $tokenVencido,
            'nova_senha' => 'NovaSenha@123',
            'confirma_senha' => 'NovaSenha@123'
        ]);
        $this->assertStringContainsString('reset.php?token=', $resVencido['headers']['location'] ?? '');
    }

    public function testProcessaResetComTokenValidoAtualizaSenhaLimpaTokenELogaComSucesso(): void
    {
        $aluno = $this->criarAlunoFixture('approved', '+30 days');
        $tokenValido = bin2hex(random_bytes(16));
        $futuro = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->pdo->prepare("UPDATE alunos SET reset_token = ?, reset_token_expiracao = ? WHERE id = ?")
             ->execute([$tokenValido, $futuro, $aluno['id']]);

        $res = $this->client->postForm('processa_reset.php', [
            'token' => $tokenValido,
            'nova_senha' => 'SenhaRedefinida@999',
            'confirma_senha' => 'SenhaRedefinida@999'
        ]);

        $this->assertStringContainsString('index.php?senha_atualizada=true', $res['headers']['location'] ?? '');

        // Verifica se limpou os tokens no banco
        $stmt = $this->pdo->prepare("SELECT senha, reset_token, reset_token_expiracao FROM alunos WHERE id = ?");
        $stmt->execute([$aluno['id']]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNull($dados['reset_token']);
        $this->assertNull($dados['reset_token_expiracao']);
        $this->assertTrue(password_verify('SenhaRedefinida@999', $dados['senha']));

        // Verifica se o aluno consegue logar com a nova senha redefinida
        $resLogin = $this->client->postForm('processa_login.php', [
            'email' => $aluno['email'],
            'senha' => 'SenhaRedefinida@999'
        ]);
        $this->assertStringContainsString('area_aluno.php', $resLogin['headers']['location'] ?? '');
    }

    public function testPainelComparativoCalculaMediaConcorrenciaExcluindoProprioAlunoEOutrasEscolas(): void
    {
        $stmtInsert = $this->pdo->prepare("
            INSERT INTO resultados_simulados (aluno_id, escola_id, simulado_ref, titulo, acertos, total_questoes, nota)
            VALUES (?, ?, 'sim_teste_comp', 'Simulado Comparativo', ?, 100, ?)
        ");

        // 1. Criar Aluno 1 (escola ativa) e registrar Simulado com nota 90.0
        $aluno1 = $this->criarAlunoFixture('approved', '+30 days', $this->escolaAtivaId);
        $stmtInsert->execute([$aluno1['id'], $this->escolaAtivaId, 90, 90.0]);

        // 2. Criar Aluno 2 (mesma escola ativa) e registrar Simulado com nota 70.0
        $aluno2 = $this->criarAlunoFixture('approved', '+30 days', $this->escolaAtivaId);
        $stmtInsert->execute([$aluno2['id'], $this->escolaAtivaId, 70, 70.0]);

        // 3. Criar Aluno 3 (mesma escola ativa) e registrar Simulado com nota 50.0
        $aluno3 = $this->criarAlunoFixture('approved', '+30 days', $this->escolaAtivaId);
        $stmtInsert->execute([$aluno3['id'], $this->escolaAtivaId, 50, 50.0]);

        // 4. Criar Aluno 4 (em $this->escolaExternaId) com nota 10.0 (não deve afetar a média da escola ativa)
        $aluno4 = $this->criarAlunoFixture('approved', '+30 days', $this->escolaExternaId);
        $stmtInsert->execute([$aluno4['id'], $this->escolaExternaId, 10, 10.0]);

        // 5. Autenticar como Aluno 1 e requisitar via GET meus_resultados.php
        $this->client->resetCookies();
        $this->client->postForm('processa_login.php', [
            'email' => $aluno1['email'],
            'senha' => $aluno1['senha'],
        ]);
        $res1 = $this->client->get('meus_resultados.php');
        $html1 = $res1['body'];

        $this->assertEquals(200, $res1['status']);
        $this->assertStringContainsString('90.0%', $html1);
        $this->assertStringContainsString('60.0%', $html1); // Média concorrência: (70 + 50) / 2 = 60.0%
        $this->assertStringContainsString('acima-media', $html1);
        $this->assertStringContainsString('Excelente! Você está acima da média', $html1);

        // 6. Autenticar como Aluno 3 e requisitar via GET meus_resultados.php
        $this->client->resetCookies();
        $this->client->postForm('processa_login.php', [
            'email' => $aluno3['email'],
            'senha' => $aluno3['senha'],
        ]);
        $res3 = $this->client->get('meus_resultados.php');
        $html3 = $res3['body'];

        $this->assertEquals(200, $res3['status']);
        $this->assertStringContainsString('50.0%', $html3);
        $this->assertStringContainsString('80.0%', $html3); // Média concorrência: (90 + 70) / 2 = 80.0%
        $this->assertStringContainsString('abaixo-media', $html3);
        $this->assertStringContainsString('Atenção! Você está abaixo da média geral', $html3);
    }
}

