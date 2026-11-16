-- Schema de Testes & CI para a Plataforma LMS (sistema_adrenalina)
CREATE DATABASE IF NOT EXISTS `sistema_adrenalina` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sistema_adrenalina`;

CREATE TABLE IF NOT EXISTS `config_escolas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `nome_display` VARCHAR(120) NOT NULL,
    `familia_sessao` VARCHAR(60) NOT NULL,
    `tipo` VARCHAR(30) DEFAULT 'escola',
    `url_vitrine` VARCHAR(255) DEFAULT NULL,
    `url_hub` VARCHAR(255) DEFAULT NULL,
    `smtp_user` VARCHAR(120) DEFAULT NULL,
    `smtp_pass` VARCHAR(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alunos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `senha` VARCHAR(255) NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'approved',
    `escola_origem` INT NOT NULL DEFAULT 1,
    `data_expiracao` DATETIME DEFAULT NULL,
    `reset_token` VARCHAR(100) DEFAULT NULL,
    `reset_token_expiracao` DATETIME DEFAULT NULL,
    INDEX `idx_alunos_email` (`email`),
    INDEX `idx_alunos_escola` (`escola_origem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `progresso_alunos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `aluno_id` INT NOT NULL,
    `escola_id` INT NOT NULL,
    `conteudo_ref` TEXT NOT NULL,
    `tipo_conteudo` VARCHAR(50) NOT NULL DEFAULT 'conteudo',
    `data_conclusao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_progresso_aluno_escola_tipo_ref` (`aluno_id`, `escola_id`, `tipo_conteudo`, `conteudo_ref`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `anotacoes_alunos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `aluno_id` INT NOT NULL,
    `escola_id` INT NOT NULL,
    `resumo_ref` VARCHAR(255) NOT NULL,
    `tipo` VARCHAR(30) NOT NULL,
    `dados_json` LONGTEXT NOT NULL,
    `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_anotacoes_busca` (`aluno_id`, `escola_id`, `resumo_ref`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `historico_taf` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `aluno_id` INT NOT NULL,
    `escola_id` INT NOT NULL,
    `data_treino` DATE NOT NULL,
    `sexo` VARCHAR(20) NOT NULL,
    `tipo_teste` VARCHAR(50) NOT NULL,
    `resultado` DECIMAL(10,2) NOT NULL,
    UNIQUE KEY `uq_taf_treino` (`aluno_id`, `escola_id`, `data_treino`, `sexo`, `tipo_teste`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `resultados_quizzes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `aluno_id` INT NOT NULL,
    `escola_id` INT NOT NULL,
    `quiz_ref` VARCHAR(150) NOT NULL,
    `titulo` VARCHAR(255) NOT NULL,
    `acertos` INT NOT NULL,
    `total_questoes` INT NOT NULL,
    `nota` DECIMAL(6,2) NOT NULL,
    `data_realizacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `detalhes_json` LONGTEXT DEFAULT NULL,
    INDEX `idx_quizzes_aluno_escola` (`aluno_id`, `escola_id`, `data_realizacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `resultados_simulados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `aluno_id` INT NOT NULL,
    `escola_id` INT NOT NULL,
    `simulado_ref` VARCHAR(150) NOT NULL,
    `titulo` VARCHAR(255) NOT NULL,
    `acertos` INT NOT NULL,
    `total_questoes` INT NOT NULL,
    `nota` DECIMAL(6,2) NOT NULL,
    `data_inicio` VARCHAR(50) DEFAULT NULL,
    `data_fim` VARCHAR(50) DEFAULT NULL,
    `tempo_decorrido` VARCHAR(50) DEFAULT NULL,
    `detalhes_json` LONGTEXT DEFAULT NULL,
    INDEX `idx_simulados_aluno_escola` (`aluno_id`, `escola_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de escolas para testes de isolamento multi-tenant
INSERT IGNORE INTO `config_escolas` (`id`, `slug`, `nome_display`, `familia_sessao`, `tipo`, `url_vitrine`, `url_hub`) VALUES
(1, 'bm', 'Bombeiro Militar - Contexto Policial', 'contexto', 'vitrine', 'https://bm.contextopolicial.com', 'https://bm.contextopolicial.com'),
(2, 'adrenalina', 'Método Adrenalina', 'contexto', 'vitrine', 'https://localhost', 'https://localhost'),
(99, 'outra_escola', 'Outra Escola Isolada', 'familia_externa', 'escola', 'https://outra.escola.com', 'https://outra.escola.com');
