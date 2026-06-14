
USE fascal_sistema_2026;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reclamacoes_notas;
DROP TABLE IF EXISTS professor_turma_disciplinas;
DROP TABLE IF EXISTS grupo_estudo_membros;
DROP TABLE IF EXISTS grupos_estudo;
DROP TABLE IF EXISTS pagamentos_atividades;
DROP TABLE IF EXISTS atividades_extracurriculares;
DROP TABLE IF EXISTS cursos_disciplinas;
DROP TABLE IF EXISTS cursos;
DROP TABLE IF EXISTS materiais_estudo;
DROP TABLE IF EXISTS comprovativos_pagamento;
DROP TABLE IF EXISTS solicitacoes_documentos;
DROP TABLE IF EXISTS anos_letivos;
DROP TABLE IF EXISTS historico_atividades;
DROP TABLE IF EXISTS perfil_utilizador;
DROP TABLE IF EXISTS documentos_emitidos;
DROP TABLE IF EXISTS tramitacoes_documentais;
DROP TABLE IF EXISTS tarefas_painel;
DROP TABLE IF EXISTS mensagens_internas;
DROP TABLE IF EXISTS candidaturas_professor;
DROP TABLE IF EXISTS pagamentos;
DROP TABLE IF EXISTS mensagens_secretaria;
DROP TABLE IF EXISTS avisos;
DROP TABLE IF EXISTS presencas;
DROP TABLE IF EXISTS notas;
DROP TABLE IF EXISTS matriculas;
DROP TABLE IF EXISTS disciplinas;
DROP TABLE IF EXISTS turmas;
DROP TABLE IF EXISTS encarregado_aluno;
DROP TABLE IF EXISTS encarregados;
DROP TABLE IF EXISTS funcionarios;
DROP TABLE IF EXISTS professores;
DROP TABLE IF EXISTS alunos;
DROP TABLE IF EXISTS matricula_documentos;
DROP TABLE IF EXISTS pre_matriculas;
DROP TABLE IF EXISTS utilizadores;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE utilizadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(120) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('aluno', 'encarregado', 'professor', 'secretaria', 'direcao_pedagogica', 'direcao_geral', 'rh') NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    token_recuperacao VARCHAR(120) NULL,
    token_expira DATETIME NULL,
    ultimo_acesso DATETIME NULL,
    tutorial_visto TINYINT(1) NOT NULL DEFAULT 0,
    senha_temporaria TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT UNIQUE NOT NULL,
    data_nascimento DATE NOT NULL,
    bi VARCHAR(30) UNIQUE,
    genero ENUM('M', 'F') NULL,
    endereco TEXT NULL,
    contato VARCHAR(20) NULL,
    foto VARCHAR(255) NULL,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
);

CREATE TABLE encarregados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT UNIQUE NOT NULL,
    telefone VARCHAR(20) NULL,
    endereco TEXT NULL,
    parentesco VARCHAR(50) NULL,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
);

CREATE TABLE encarregado_aluno (
    encarregado_id INT NOT NULL,
    aluno_id INT NOT NULL,
    PRIMARY KEY (encarregado_id, aluno_id),
    FOREIGN KEY (encarregado_id) REFERENCES encarregados(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
);

CREATE TABLE professores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT UNIQUE NOT NULL,
    especialidade VARCHAR(100) NULL,
    telefone VARCHAR(20) NULL,
    data_contratacao DATE NULL,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
);

CREATE TABLE funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT UNIQUE NOT NULL,
    cargo VARCHAR(100) NOT NULL,
    telefone VARCHAR(20) NULL,
    departamento VARCHAR(100) NOT NULL,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
);

CREATE TABLE turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    ano_letivo VARCHAR(9) NOT NULL,
    capacidade INT DEFAULT 35,
    professor_id INT NULL,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE SET NULL
);

CREATE TABLE disciplinas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    carga_horaria INT DEFAULT 0
);

CREATE TABLE professor_turma_disciplinas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professor_id INT NOT NULL,
    turma_id INT NOT NULL,
    disciplina_id INT NOT NULL,
    titular TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_professor_turma_disciplina (professor_id, turma_id, disciplina_id),
    UNIQUE KEY uq_turma_disciplina (turma_id, disciplina_id),
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE
);

CREATE TABLE matriculas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    turma_id INT NOT NULL,
    data_matricula DATE NOT NULL,
    status ENUM('activo', 'concluido', 'transferido', 'cancelado') DEFAULT 'activo',
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
);

CREATE TABLE notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula_id INT NOT NULL,
    disciplina_id INT NOT NULL,
    trimestre TINYINT NOT NULL,
    nota DECIMAL(4,2) NULL,
    teste DECIMAL(5,2) NULL,
    trabalho DECIMAL(5,2) NULL,
    participacao DECIMAL(5,2) NULL,
    situacao VARCHAR(40) NULL,
    data_lancamento DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE
);

CREATE TABLE presencas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula_id INT NOT NULL,
    data DATE NOT NULL,
    disciplina_id INT NULL,
    professor_id INT NULL,
    presente TINYINT(1) DEFAULT 0,
    justificativa TEXT NULL,
    justificativa_status ENUM('pendente', 'aceite', 'rejeitada') NULL,
    justificativa_analisada_por INT NULL,
    justificativa_analisada_em DATETIME NULL,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE SET NULL,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE SET NULL
);

CREATE TABLE pre_matriculas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nome_encarregado VARCHAR(120) NOT NULL,
    email_encarregado VARCHAR(120) NOT NULL,
    telefone_encarregado VARCHAR(20) NULL,
    nome_aluno VARCHAR(120) NOT NULL,
    data_nascimento_aluno DATE NOT NULL,
    ano_pretendido VARCHAR(30) NOT NULL,
    observacoes TEXT NULL,
    status ENUM('pendente', 'concluida', 'cancelada') DEFAULT 'pendente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE mensagens_secretaria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL,
    assunto VARCHAR(150) NOT NULL,
    mensagem TEXT NOT NULL,
    status ENUM('nova', 'lida', 'respondida') DEFAULT 'nova',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE avisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT NOT NULL,
    destinatarios ENUM('todos', 'alunos', 'encarregados', 'professores', 'funcionarios') DEFAULT 'todos',
    data_inicio DATETIME NULL,
    data_fim DATETIME NULL,
    criado_por INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES utilizadores(id) ON DELETE SET NULL
);

CREATE TABLE pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula_id INT NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NULL,
    data_pagamento DATE NULL,
    status ENUM('pendente', 'pago', 'atrasado') DEFAULT 'pendente',
    recibo_pdf VARCHAR(255) NULL,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE
);

CREATE TABLE candidaturas_professor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL,
    telefone VARCHAR(20) NULL,
    disciplina VARCHAR(100) NOT NULL,
    cv_path VARCHAR(255) NULL,
    certificados_path VARCHAR(255) NULL,
    mensagem TEXT NULL,
    status ENUM('nova', 'em_analise', 'entrevista', 'aprovada', 'rejeitada') DEFAULT 'nova',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE matricula_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT UNIQUE NOT NULL,
    foto_1 VARCHAR(255) NULL,
    foto_2 VARCHAR(255) NULL,
    foto_3 VARCHAR(255) NULL,
    foto_4 VARCHAR(255) NULL,
    bi_copia VARCHAR(255) NULL,
    atestado_medico VARCHAR(255) NULL,
    documento_classe_anterior VARCHAR(255) NULL,
    observacoes TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
);

CREATE TABLE mensagens_internas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remetente_id INT NULL,
    destinatario_id INT NULL,
    perfil_destino ENUM('todos', 'aluno', 'encarregado', 'professor', 'secretaria', 'direcao_pedagogica', 'direcao_geral', 'rh') DEFAULT 'todos',
    assunto VARCHAR(180) NOT NULL,
    mensagem TEXT NOT NULL,
    status ENUM('nao_lida', 'lida') DEFAULT 'nao_lida',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (remetente_id) REFERENCES utilizadores(id) ON DELETE SET NULL,
    FOREIGN KEY (destinatario_id) REFERENCES utilizadores(id) ON DELETE SET NULL
);

CREATE TABLE tarefas_painel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    setor ENUM('todos', 'aluno', 'encarregado', 'professor', 'secretaria', 'direcao_pedagogica', 'direcao_geral', 'rh') NOT NULL,
    prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media',
    status ENUM('pendente', 'em_andamento', 'concluida') DEFAULT 'pendente',
    prazo DATE NULL,
    criado_por INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES utilizadores(id) ON DELETE SET NULL
);

CREATE TABLE tramitacoes_documentais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) UNIQUE NOT NULL,
    tipo_documento VARCHAR(140) NOT NULL,
    origem_setor ENUM('professor', 'secretaria', 'direcao_pedagogica', 'direcao_geral', 'rh') NOT NULL,
    destino_setor ENUM('secretaria', 'direcao_pedagogica', 'direcao_geral', 'rh', 'publico') NOT NULL,
    referencia_id INT NULL,
    status ENUM('submetido', 'em_analise', 'aprovado', 'rejeitado', 'publicado') DEFAULT 'submetido',
    observacao TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE documentos_emitidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento ENUM('declaracao', 'certificado', 'boletim', 'outro') NOT NULL,
    aluno_id INT NOT NULL,
    emitido_por INT NOT NULL,
    nome_ficheiro VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (emitido_por) REFERENCES utilizadores(id) ON DELETE CASCADE
);

CREATE TABLE perfil_utilizador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT UNIQUE NOT NULL,
    foto VARCHAR(255) NULL,
    telefone VARCHAR(20) NULL,
    endereco VARCHAR(255) NULL,
    sobre_mim TEXT NULL,
    data_actualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
);

CREATE TABLE historico_atividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT NOT NULL,
    acao VARCHAR(180) NOT NULL,
    detalhe TEXT NULL,
    ip VARCHAR(45) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
);

CREATE TABLE anos_letivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referencia VARCHAR(20) UNIQUE NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 0,
    criado_por INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES utilizadores(id) ON DELETE SET NULL
);

CREATE TABLE solicitacoes_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    encarregado_id INT NULL,
    solicitado_por INT NOT NULL,
    tipo_documento ENUM('boletim', 'declaracao', 'certificado', 'outro') NOT NULL,
    estado ENUM('pendente', 'autorizado', 'rejeitado', 'disponibilizado') DEFAULT 'pendente',
    observacao TEXT NULL,
    autorizado_por INT NULL,
    autorizado_em DATETIME NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (encarregado_id) REFERENCES encarregados(id) ON DELETE SET NULL,
    FOREIGN KEY (solicitado_por) REFERENCES utilizadores(id) ON DELETE CASCADE,
    FOREIGN KEY (autorizado_por) REFERENCES utilizadores(id) ON DELETE SET NULL
);

CREATE TABLE comprovativos_pagamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pagamento_id INT NULL,
    aluno_id INT NOT NULL,
    encarregado_id INT NOT NULL,
    mes_referencia VARCHAR(20) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    codigo_referencia VARCHAR(40) UNIQUE NOT NULL,
    comprovativo_path VARCHAR(255) NULL,
    metodo_pagamento ENUM('referencia', 'autorizacao') NULL,
    recibo_pdf VARCHAR(255) NULL,
    estado ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
    observacao_secretaria TEXT NULL,
    analisado_por INT NULL,
    analisado_em DATETIME NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pagamento_id) REFERENCES pagamentos(id) ON DELETE SET NULL,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (encarregado_id) REFERENCES encarregados(id) ON DELETE CASCADE,
    FOREIGN KEY (analisado_por) REFERENCES utilizadores(id) ON DELETE SET NULL
);

CREATE TABLE materiais_estudo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professor_id INT NOT NULL,
    turma_id INT NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    ficheiro_path VARCHAR(255) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
);

CREATE TABLE cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    ano_curso VARCHAR(20) NOT NULL,
    quantidade_disciplinas INT NOT NULL DEFAULT 0,
    publicado_index TINYINT(1) NOT NULL DEFAULT 0,
    criado_por INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES utilizadores(id) ON DELETE SET NULL
);

CREATE TABLE cursos_disciplinas (
    curso_id INT NOT NULL,
    disciplina_id INT NOT NULL,
    PRIMARY KEY (curso_id, disciplina_id),
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE
);

CREATE TABLE atividades_extracurriculares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tema VARCHAR(150) NOT NULL,
    descricao TEXT NOT NULL,
    categoria VARCHAR(80) NULL,
    data_atividade DATE NULL,
    preco DECIMAL(10,2) NOT NULL DEFAULT 0,
    imagem_path VARCHAR(255) NULL,
    publicado_index TINYINT(1) NOT NULL DEFAULT 1,
    criado_por INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES utilizadores(id) ON DELETE SET NULL
);

CREATE TABLE pagamentos_atividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    atividade_id INT NOT NULL,
    aluno_id INT NOT NULL,
    encarregado_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    estado ENUM('pendente', 'pago') DEFAULT 'pendente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (atividade_id) REFERENCES atividades_extracurriculares(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (encarregado_id) REFERENCES encarregados(id) ON DELETE CASCADE
);

CREATE TABLE grupos_estudo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    criador_aluno_id INT NOT NULL,
    nome VARCHAR(140) NOT NULL,
    descricao TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criador_aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
);

CREATE TABLE grupo_estudo_membros (
    grupo_id INT NOT NULL,
    aluno_id INT NOT NULL,
    PRIMARY KEY (grupo_id, aluno_id),
    FOREIGN KEY (grupo_id) REFERENCES grupos_estudo(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
);

CREATE TABLE reclamacoes_notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    disciplina_id INT NULL,
    trimestre TINYINT NULL,
    mensagem TEXT NOT NULL,
    estado ENUM('aberta', 'em_analise', 'resolvida') DEFAULT 'aberta',
    respondido_por INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE SET NULL,
    FOREIGN KEY (respondido_por) REFERENCES utilizadores(id) ON DELETE SET NULL
);


-- Ajustes de compatibilidade com a base actual
ALTER TABLE utilizadores ADD COLUMN IF NOT EXISTS senha_valida_ate DATETIME NULL AFTER senha_temporaria;
ALTER TABLE utilizadores ADD COLUMN IF NOT EXISTS senha_ativa TINYINT(1) NOT NULL DEFAULT 1 AFTER senha_valida_ate;
ALTER TABLE alunos ADD COLUMN IF NOT EXISTS email_institucional VARCHAR(120) NULL AFTER foto;
ALTER TABLE alunos ADD COLUMN IF NOT EXISTS senha_valida_ate DATETIME NULL AFTER email_institucional;
ALTER TABLE alunos ADD COLUMN IF NOT EXISTS senha_ativa TINYINT(1) NOT NULL DEFAULT 1 AFTER senha_valida_ate;
ALTER TABLE notas ADD COLUMN IF NOT EXISTS faltas INT NOT NULL DEFAULT 0 AFTER participacao;
ALTER TABLE pre_matriculas ADD COLUMN IF NOT EXISTS curso_pretendido VARCHAR(100) NULL AFTER ano_pretendido;
ALTER TABLE mensagens_internas ADD COLUMN IF NOT EXISTS resposta_a_id INT NULL AFTER destinatario_id;
ALTER TABLE turmas ADD COLUMN IF NOT EXISTS sala VARCHAR(40) NULL AFTER ano_letivo;

CREATE TABLE IF NOT EXISTS boletins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    periodo VARCHAR(40) NOT NULL,
    data_entrega DATE NULL,
    status ENUM('pendente','entregue','solicitado') NOT NULL DEFAULT 'pendente',
    arquivo_pdf VARCHAR(255) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_boletins_aluno_periodo (aluno_id, periodo),
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mensagens_internas_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mensagem_id INT NOT NULL,
    caminho_ficheiro VARCHAR(255) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(120) NOT NULL,
    tamanho_bytes INT NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mensagem_id) REFERENCES mensagens_internas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO utilizadores (id, nome, email, senha, perfil, ativo, tutorial_visto, senha_temporaria, senha_valida_ate, senha_ativa, criado_em) VALUES
(1, 'Secretaria FASCCAR', 'secretaria@fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'secretaria', 1, 1, 0, NULL, 1, NOW()),
(2, 'Direcao Geral', 'direcao.geral@fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'direcao_geral', 1, 1, 0, NULL, 1, NOW()),
(3, 'Direcao Pedagogica', 'direcao.pedagogica@fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'direcao_pedagogica', 1, 1, 0, NULL, 1, NOW()),
(4, 'Recursos Humanos', 'rh@fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'rh', 1, 1, 0, NULL, 1, NOW()),
(5, 'Antonio Bengue', 'antonio.bengue@fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'professor', 1, 1, 0, NULL, 1, NOW()),
(6, 'Kialanda Filipe', 'kialanda.filipe@fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'professor', 1, 1, 0, NULL, 1, NOW()),
(7, 'Baptista Joao', 'baptista.joao@fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'professor', 1, 1, 0, NULL, 1, NOW()),
(8, 'Fernando Domingos', 'fernando-domingos@aluno.fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'aluno', 1, 1, 0, NULL, 1, NOW()),
(9, 'Kenia Vidigal', 'kenia-vidigal@aluno.fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'aluno', 1, 1, 0, NULL, 1, NOW()),
(10, 'Armindo Xinganeca', 'armindo-xinganeca@aluno.fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'aluno', 1, 1, 0, NULL, 1, NOW()),
(11, 'Lando Quimusseco', 'lando-quimusseco@aluno.fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'aluno', 1, 1, 0, NULL, 1, NOW()),
(12, 'Mateus Fernandes', 'mateus-fernandes@encarregado.fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'encarregado', 1, 1, 0, NULL, 1, NOW()),
(13, 'Vidigal Kenia', 'vidigal-kenia@encarregado.fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'encarregado', 1, 1, 0, NULL, 1, NOW()),
(14, 'Xinganeca Armindo', 'xinganeca-armindo@encarregado.fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'encarregado', 1, 1, 0, NULL, 1, NOW()),
(15, 'Quimusseco Lando', 'quimusseco-lando@encarregado.fascal.ao', '$2y$12$qaf5FJKrLsYYWwIAuygNju4YVLEIIncOF4i/idlJr22GhZlKqEb1u', 'encarregado', 1, 1, 0, NULL, 1, NOW());

INSERT INTO perfil_utilizador (utilizador_id, telefone, endereco, sobre_mim) VALUES
(1, '923000001', 'Luanda, Angola', 'Secretaria preparada para demo academica.'),
(2, '923000002', 'Luanda, Angola', 'Direccao geral preparada para demo academica.'),
(3, '923000003', 'Luanda, Angola', 'Direccao pedagogica preparada para demo academica.'),
(4, '923000004', 'Luanda, Angola', 'RH preparado para demo academica.'),
(5, '930000010', 'Luanda, Angola', 'Professor de Programacao.'),
(6, '930000011', 'Luanda, Angola', 'Professor de Matematica.'),
(7, '930000012', 'Luanda, Angola', 'Professor de Linguas.'),
(8, '951000001', 'Talatona, Luanda', 'Aluno demo 1.'),
(9, '951000002', 'Kilamba, Luanda', 'Aluno demo 2.'),
(10, '951000003', 'Benfica, Luanda', 'Aluno demo 3.'),
(11, '951000004', 'Maianga, Luanda', 'Aluno demo 4.'),
(12, '961000001', 'Talatona, Luanda', 'Encarregado demo 1.'),
(13, '961000002', 'Kilamba, Luanda', 'Encarregado demo 2.'),
(14, '961000003', 'Benfica, Luanda', 'Encarregado demo 3.'),
(15, '961000004', 'Maianga, Luanda', 'Encarregado demo 4.');

INSERT INTO funcionarios (utilizador_id, cargo, telefone, departamento) VALUES
(1, 'Secretario academico', '923000001', 'Secretaria'),
(2, 'Diretor geral', '923000002', 'Direcao Geral'),
(3, 'Diretor pedagogico', '923000003', 'Direcao Pedagogica'),
(4, 'Gestor de RH', '923000004', 'Recursos Humanos');

INSERT INTO professores (id, utilizador_id, especialidade, telefone, data_contratacao) VALUES
(1, 5, 'Programacao', '930000010', '2023-02-01'),
(2, 6, 'Matematica', '930000011', '2023-02-01'),
(3, 7, 'Linguas', '930000012', '2023-02-01');

INSERT INTO disciplinas (id, nome, carga_horaria) VALUES
(1, 'Lingua Portuguesa', 80),
(2, 'Matematica', 100),
(3, 'Programacao', 120),
(4, 'Sistemas Digitais', 90),
(5, 'Ingles Tecnico', 70);

INSERT INTO cursos (id, nome, ano_curso, quantidade_disciplinas, publicado_index, criado_por, criado_em) VALUES
(1, 'Informática', '10.ª-13.ª', 5, 1, 3, NOW()),
(2, 'Contabilidade', '10.ª-13.ª', 5, 1, 3, NOW()),
(3, 'Recursos Humanos', '10.ª-13.ª', 5, 1, 3, NOW()),
(4, 'Ciências Físicas e Biológicas', '10.ª-13.ª', 5, 1, 3, NOW());

INSERT INTO cursos_disciplinas (curso_id, disciplina_id) VALUES
(1,1), (1,2), (1,3), (1,4), (1,5),
(2,1), (2,2), (2,3), (2,4), (2,5),
(3,1), (3,2), (3,3), (3,4), (3,5),
(4,1), (4,2), (4,3), (4,4), (4,5);

INSERT INTO turmas (id, nome, ano_letivo, sala, capacidade, professor_id) VALUES
(1, '10.ª Informática', '2025/2026', 'Sala 10I', 35, 1),
(2, '11.ª Informática', '2025/2026', 'Sala 11I', 35, 2),
(3, '12.ª Informática', '2025/2026', 'Sala 12I', 35, 3),
(4, '13.ª Informática', '2025/2026', 'Sala 13I', 35, NULL),
(5, '10.ª Contabilidade', '2025/2026', 'Sala 10C', 35, NULL),
(6, '11.ª Contabilidade', '2025/2026', 'Sala 11C', 35, NULL),
(7, '12.ª Contabilidade', '2025/2026', 'Sala 12C', 35, NULL),
(8, '13.ª Contabilidade', '2025/2026', 'Sala 13C', 35, NULL),
(9, '10.ª Recursos Humanos', '2025/2026', 'Sala 10RH', 35, NULL),
(10, '11.ª Recursos Humanos', '2025/2026', 'Sala 11RH', 35, NULL),
(11, '12.ª Recursos Humanos', '2025/2026', 'Sala 12RH', 35, NULL),
(12, '13.ª Recursos Humanos', '2025/2026', 'Sala 13RH', 35, NULL),
(13, '10.ª Ciências Físicas e Biológicas', '2025/2026', 'Sala 10CFB', 35, NULL),
(14, '11.ª Ciências Físicas e Biológicas', '2025/2026', 'Sala 11CFB', 35, NULL),
(15, '12.ª Ciências Físicas e Biológicas', '2025/2026', 'Sala 12CFB', 35, NULL),
(16, '13.ª Ciências Físicas e Biológicas', '2025/2026', 'Sala 13CFB', 35, NULL);

INSERT INTO alunos (id, utilizador_id, data_nascimento, bi, genero, endereco, contato, foto, email_institucional, senha_valida_ate, senha_ativa) VALUES
(1, 8, '2008-03-12', 'BI2026001', 'M', 'Talatona, Luanda', '951000001', NULL, 'mateus.fernando1@escola.com', NULL, 1),
(2, 9, '2007-07-22', 'BI2026002', 'F', 'Kilamba, Luanda', '951000002', NULL, 'vidigal.kenia1@escola.com', NULL, 1),
(3, 10, '2006-05-08', 'BI2026003', 'M', 'Benfica, Luanda', '951000003', NULL, 'xinganeca.armindo1@escola.com', NULL, 1),
(4, 11, '2005-11-17', 'BI2026004', 'M', 'Maianga, Luanda', '951000004', NULL, 'quimusseco.lando1@escola.com', NULL, 1);

INSERT INTO encarregados (id, utilizador_id, telefone, endereco, parentesco) VALUES
(1, 12, '961000001', 'Talatona, Luanda', 'Pai'),
(2, 13, '961000002', 'Kilamba, Luanda', 'Mae'),
(3, 14, '961000003', 'Benfica, Luanda', 'Pai'),
(4, 15, '961000004', 'Maianga, Luanda', 'Tio');

INSERT INTO encarregado_aluno (encarregado_id, aluno_id) VALUES
(1, 1), (2, 2), (3, 3), (4, 4);

INSERT INTO professor_turma_disciplinas (professor_id, turma_id, disciplina_id, titular) VALUES
(1, 1, 1, 1), (2, 1, 2, 0), (3, 1, 3, 0), (1, 1, 4, 0), (2, 1, 5, 0),
(2, 2, 1, 1), (3, 2, 2, 0), (1, 2, 3, 0), (2, 2, 4, 0), (3, 2, 5, 0);

INSERT INTO matriculas (id, aluno_id, turma_id, data_matricula, status) VALUES
(1, 1, 1, '2025-09-02', 'activo'),
(2, 2, 2, '2025-09-02', 'activo'),
(3, 3, 1, '2025-09-02', 'activo'),
(4, 4, 2, '2025-09-02', 'activo');

INSERT INTO notas (matricula_id, disciplina_id, trimestre, nota, teste, trabalho, participacao, faltas, situacao) VALUES
(1,1,1,15.0,14.5,15.4,15.2,1,'Aprovado'), (1,1,2,16.0,15.5,16.4,16.2,0,'Aprovado'),
(1,2,1,13.5,13.0,13.9,13.7,0,'Aprovado'), (1,2,2,14.5,14.0,14.9,14.7,1,'Aprovado'),
(1,3,1,17.0,16.5,17.4,17.2,0,'Aprovado'), (1,3,2,17.5,17.0,17.9,17.7,0,'Aprovado'),
(2,1,1,12.0,11.5,12.4,12.2,1,'Aprovado'), (2,1,2,13.0,12.5,13.4,13.2,0,'Aprovado'),
(2,2,1,11.0,10.5,11.4,11.2,2,'Aprovado'), (2,2,2,12.0,11.5,12.4,12.2,1,'Aprovado'),
(2,3,1,14.0,13.5,14.4,14.2,0,'Aprovado'), (2,3,2,15.0,14.5,15.4,15.2,0,'Aprovado'),
(3,1,1,10.5,10.0,10.9,10.7,1,'Aprovado'), (3,1,2,11.5,11.0,11.9,11.7,1,'Aprovado'),
(3,2,1,9.5,9.0,9.9,9.7,2,'Recuperacao'), (3,2,2,10.5,10.0,10.9,10.7,1,'Aprovado'),
(3,3,1,12.5,12.0,12.9,12.7,0,'Aprovado'), (3,3,2,13.5,13.0,13.9,13.7,0,'Aprovado'),
(4,1,1,14.5,14.0,14.9,14.7,0,'Aprovado'), (4,1,2,15.5,15.0,15.9,15.7,0,'Aprovado'),
(4,2,1,13.0,12.5,13.4,13.2,1,'Aprovado'), (4,2,2,14.0,13.5,14.4,14.2,1,'Aprovado'),
(4,3,1,16.0,15.5,16.4,16.2,0,'Aprovado'), (4,3,2,17.0,16.5,17.4,17.2,0,'Aprovado');

INSERT INTO boletins (aluno_id, periodo, data_entrega, status, arquivo_pdf) VALUES
(1, '1o Trimestre', '2025-12-18', 'entregue', 'storage/boletins/boletim-aluno-1-t1.pdf'),
(1, '2o Trimestre', NULL, 'solicitado', 'storage/boletins/boletim-aluno-1-t2.pdf'),
(2, '1o Trimestre', '2025-12-18', 'entregue', 'storage/boletins/boletim-aluno-2-t1.pdf'),
(2, '2o Trimestre', NULL, 'pendente', 'storage/boletins/boletim-aluno-2-t2.pdf'),
(3, '1o Trimestre', '2025-12-18', 'entregue', 'storage/boletins/boletim-aluno-3-t1.pdf'),
(3, '2o Trimestre', '2026-04-22', 'entregue', 'storage/boletins/boletim-aluno-3-t2.pdf'),
(4, '1o Trimestre', '2025-12-18', 'entregue', 'storage/boletins/boletim-aluno-4-t1.pdf'),
(4, '2o Trimestre', NULL, 'solicitado', 'storage/boletins/boletim-aluno-4-t2.pdf');

INSERT INTO solicitacoes_documentos (aluno_id, encarregado_id, solicitado_por, tipo_documento, estado, observacao, autorizado_por, autorizado_em) VALUES
(1, 1, 12, 'boletim', 'disponibilizado', 'Solicitacao de boletim registada no seed.', 1, '2026-04-23 09:00:00'),
(2, 2, 13, 'boletim', 'pendente', 'Aguardando analise secretaria.', NULL, NULL),
(3, 3, 14, 'boletim', 'disponibilizado', 'Boletim entregue ao encarregado.', 1, '2026-04-23 09:10:00'),
(4, 4, 15, 'boletim', 'pendente', 'Solicitacao em processamento.', NULL, NULL);

INSERT INTO mensagens_internas (id, remetente_id, destinatario_id, resposta_a_id, perfil_destino, assunto, mensagem, status, criado_em) VALUES
(1, 1, 8, NULL, 'aluno', 'Credenciais e boletim disponiveis', 'O boletim e as credenciais institucionais estao disponiveis no painel.', 'nao_lida', NOW()),
(2, 1, 12, NULL, 'encarregado', 'Boletim do educando', 'O boletim do 2o trimestre pode ser consultado no painel.', 'nao_lida', NOW()),
(3, 13, 1, NULL, 'secretaria', 'Solicitacao de documento', 'Solicito confirmacao do boletim do educando.', 'lida', NOW()),
(4, 10, 1, NULL, 'secretaria', 'Pedido de apoio', 'Preciso de orientacao sobre a solicitacao do boletim.', 'nao_lida', NOW());

INSERT INTO mensagens_internas_anexos (mensagem_id, caminho_ficheiro, nome_original, tipo_mime, tamanho_bytes) VALUES
(1, 'uploads/mensagens-internas/demo-comunicado.pdf', 'demo-comunicado.pdf', 'application/pdf', 48);

INSERT INTO avisos (titulo, mensagem, destinatarios, data_inicio, data_fim, criado_por) VALUES
('Boletins disponiveis', 'Os boletins do 1o e 2o trimestre estao disponiveis para consulta.', 'todos', '2026-04-01 08:00:00', '2026-06-30 18:00:00', 1);

SET FOREIGN_KEY_CHECKS = 1;
