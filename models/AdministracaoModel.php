<?php

class AdministracaoModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function listarAlunos(): array
    {
        try {
            $sql = 'SELECT
                        a.id,
                        a.utilizador_id,
                        u.nome,
                        u.email,
                        a.bi,
                        a.data_nascimento,
                        a.contato,
                        COALESCE(t.id, 0) AS turma_id,
                        COALESCE(t.nome, "Sem turma") AS turma
                    FROM alunos a
                    INNER JOIN utilizadores u ON u.id = a.utilizador_id
                    LEFT JOIN matriculas m ON m.aluno_id = a.id AND m.status = "activo"
                    LEFT JOIN turmas t ON t.id = m.turma_id
                    ORDER BY u.nome';

            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarTurmas(): array
    {
        try {
            $sql = 'SELECT
                        t.id,
                        t.nome,
                        t.ano_letivo,
                        t.capacidade,
                        t.professor_id,
                        COALESCE(u.nome, "Sem professor") AS professor,
                        COUNT(m.id) AS total_alunos
                    FROM turmas t
                    LEFT JOIN professores p ON p.id = t.professor_id
                    LEFT JOIN utilizadores u ON u.id = p.utilizador_id
                    LEFT JOIN matriculas m ON m.turma_id = t.id AND m.status = "activo"
                    GROUP BY t.id, t.nome, t.ano_letivo, t.capacidade, t.professor_id, u.nome
                    ORDER BY t.nome';

            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarDisciplinas(): array
    {
        try {
            $sql = 'SELECT id, nome, carga_horaria
                    FROM disciplinas
                    ORDER BY nome';

            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarPagamentos(): array
    {
        try {
            $sql = 'SELECT
                        p.id,
                        p.matricula_id,
                        p.descricao,
                        p.valor,
                        p.data_vencimento,
                        p.data_pagamento,
                        p.status,
                        u.nome AS aluno
                    FROM pagamentos p
                    INNER JOIN matriculas m ON m.id = p.matricula_id
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores u ON u.id = a.utilizador_id
                    ORDER BY p.data_vencimento DESC';

            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarProfessores(): array
    {
        try {
            $sql = 'SELECT p.id, u.nome
                    FROM professores p
                    INNER JOIN utilizadores u ON u.id = p.utilizador_id
                    ORDER BY u.nome';

            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarMatriculasAtivas(): array
    {
        try {
            $sql = 'SELECT
                        m.id,
                        CONCAT(u.nome, " - ", t.nome) AS referencia
                    FROM matriculas m
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores u ON u.id = a.utilizador_id
                    INNER JOIN turmas t ON t.id = m.turma_id
                    WHERE m.status = "activo"
                    ORDER BY u.nome';

            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function criarAluno(array $dados): array
    {
        if (!nome_humano_valido((string) ($dados['nome'] ?? ''))) {
            return ['sucesso' => false, 'mensagem' => 'O nome do aluno deve ter pelo menos 3 letras e nao pode ser apenas numeros.'];
        }

        try {
            $this->db->beginTransaction();

            $sqlUtilizador = 'INSERT INTO utilizadores (nome, email, senha, perfil, ativo)
                              VALUES (:nome, :email, :senha, "aluno", 1)';
            $stmtUtilizador = $this->db->prepare($sqlUtilizador);
            $stmtUtilizador->execute([
                'nome' => $dados['nome'],
                'email' => $dados['email'],
                'senha' => password_hash($dados['senha'], PASSWORD_DEFAULT)
            ]);

            $utilizadorId = (int) $this->db->lastInsertId();

            $sqlAluno = 'INSERT INTO alunos (utilizador_id, data_nascimento, bi, contato, genero)
                         VALUES (:utilizador_id, :data_nascimento, :bi, :contato, :genero)';
            $stmtAluno = $this->db->prepare($sqlAluno);
            $stmtAluno->execute([
                'utilizador_id' => $utilizadorId,
                'data_nascimento' => $dados['data_nascimento'],
                'bi' => $dados['bi'] ??  null,
                'contato' => $dados['contato'] ??  null,
                'genero' => $dados['genero'] ??  null
            ]);

            $alunoId = (int) $this->db->lastInsertId();

            if (!empty($dados['turma_id'])) {
                $sqlMatricula = 'INSERT INTO matriculas (aluno_id, turma_id, data_matricula, status)
                                 VALUES (:aluno_id, :turma_id, CURDATE(), "activo")';
                $stmtMatricula = $this->db->prepare($sqlMatricula);
                $stmtMatricula->execute([
                    'aluno_id' => $alunoId,
                    'turma_id' => (int) $dados['turma_id']
                ]);
            }

            $this->db->commit();
            return ['sucesso' => true, 'mensagem' => 'Aluno criado com sucesso.'];
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel criar o aluno.'];
        }
    }

    public function atualizarAluno(int $alunoId, array $dados): array
    {
        if (!nome_humano_valido((string) ($dados['nome'] ?? ''))) {
            return ['sucesso' => false, 'mensagem' => 'O nome do aluno deve ter pelo menos 3 letras e nao pode ser apenas numeros.'];
        }

        try {
            $this->db->beginTransaction();

            $sqlAluno = 'SELECT utilizador_id FROM alunos WHERE id = :id LIMIT 1';
            $stmtAluno = $this->db->prepare($sqlAluno);
            $stmtAluno->execute(['id' => $alunoId]);
            $utilizadorId = (int) $stmtAluno->fetchColumn();

            if ($utilizadorId <= 0) {
                throw new RuntimeException('Aluno nao encontrado.');
            }

            $sqlUtilizador = 'UPDATE utilizadores SET nome = :nome, email = :email WHERE id = :id';
            $stmtUtilizador = $this->db->prepare($sqlUtilizador);
            $stmtUtilizador->execute([
                'nome' => $dados['nome'],
                'email' => $dados['email'],
                'id' => $utilizadorId
            ]);

            $sqlDadosAluno = 'UPDATE alunos
                              SET bi = :bi, data_nascimento = :data_nascimento, contato = :contato, genero = :genero
                              WHERE id = :id';
            $stmtDadosAluno = $this->db->prepare($sqlDadosAluno);
            $stmtDadosAluno->execute([
                'bi' => $dados['bi'] ??  null,
                'data_nascimento' => $dados['data_nascimento'],
                'contato' => $dados['contato'] ??  null,
                'genero' => $dados['genero'] ??  null,
                'id' => $alunoId
            ]);

            $this->atualizarTurmaAluno($alunoId, (int) $dados['turma_id']);

            if (!empty($dados['senha'])) {
                $sqlSenha = 'UPDATE utilizadores SET senha = :senha WHERE id = :id';
                $stmtSenha = $this->db->prepare($sqlSenha);
                $stmtSenha->execute([
                    'senha' => password_hash($dados['senha'], PASSWORD_DEFAULT),
                    'id' => $utilizadorId
                ]);
            }

            $this->db->commit();
            return ['sucesso' => true, 'mensagem' => 'Aluno actualizado com sucesso.'];
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel actualizar o aluno.'];
        }
    }

    public function removerAluno(int $alunoId): array
    {
        try {
            $sql = 'SELECT utilizador_id FROM alunos WHERE id = :id LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $alunoId]);
            $utilizadorId = (int) $stmt->fetchColumn();

            if ($utilizadorId <= 0) {
                return ['sucesso' => false, 'mensagem' => 'Aluno nao encontrado.'];
            }

            $stmtDelete = $this->db->prepare('DELETE FROM utilizadores WHERE id = :id');
            $stmtDelete->execute(['id' => $utilizadorId]);

            return ['sucesso' => true, 'mensagem' => 'Aluno removido com sucesso.'];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel remover o aluno.'];
        }
    }

    public function criarTurma(array $dados): array
    {
        if (!nome_humano_valido((string) ($dados['nome'] ?? ''))) {
            return ['sucesso' => false, 'mensagem' => 'O nome da turma deve ser valido e ter pelo menos 3 caracteres.'];
        }

        try {
            $sql = 'INSERT INTO turmas (nome, ano_letivo, capacidade, professor_id)
                    VALUES (:nome, :ano_letivo, :capacidade, :professor_id)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nome' => $dados['nome'],
                'ano_letivo' => $dados['ano_letivo'],
                'capacidade' => (int) $dados['capacidade'],
                'professor_id' => !empty($dados['professor_id']) ? (int) $dados['professor_id'] : null
            ]);

            return ['sucesso' => true, 'mensagem' => 'Turma criada com sucesso.'];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel criar a turma.'];
        }
    }

    public function atualizarTurma(int $turmaId, array $dados): array
    {
        if (!nome_humano_valido((string) ($dados['nome'] ?? ''))) {
            return ['sucesso' => false, 'mensagem' => 'O nome da turma deve ser valido e ter pelo menos 3 caracteres.'];
        }

        try {
            $sql = 'UPDATE turmas
                    SET nome = :nome, ano_letivo = :ano_letivo, capacidade = :capacidade, professor_id = :professor_id
                    WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nome' => $dados['nome'],
                'ano_letivo' => $dados['ano_letivo'],
                'capacidade' => (int) $dados['capacidade'],
                'professor_id' => !empty($dados['professor_id']) ? (int) $dados['professor_id'] : null,
                'id' => $turmaId
            ]);

            return ['sucesso' => true, 'mensagem' => 'Turma actualizada com sucesso.'];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel actualizar a turma.'];
        }
    }

    public function removerTurma(int $turmaId): array
    {
        try {
            $stmtLigacoes = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM matriculas
                 WHERE turma_id = :turma_id
                   AND status = "activo"'
            );
            $stmtLigacoes->execute(['turma_id' => $turmaId]);
            if ((int) $stmtLigacoes->fetchColumn() > 0) {
                return ['sucesso' => false, 'mensagem' => 'Nao e possivel remover a turma porque existem alunos associados.'];
            }

            $sql = 'DELETE FROM turmas WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $turmaId]);

            return ['sucesso' => true, 'mensagem' => 'Turma removida com sucesso.'];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel remover a turma.'];
        }
    }

    public function criarDisciplina(array $dados): array
    {
        try {
            $sql = 'INSERT INTO disciplinas (nome, carga_horaria)
                    VALUES (:nome, :carga_horaria)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nome' => $dados['nome'],
                'carga_horaria' => (int) $dados['carga_horaria']
            ]);

            return ['sucesso' => true, 'mensagem' => 'Disciplina criada com sucesso.'];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel criar a disciplina.'];
        }
    }

    public function atualizarDisciplina(int $disciplinaId, array $dados): array
    {
        try {
            $sql = 'UPDATE disciplinas
                    SET nome = :nome, carga_horaria = :carga_horaria
                    WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nome' => $dados['nome'],
                'carga_horaria' => (int) $dados['carga_horaria'],
                'id' => $disciplinaId
            ]);

            return ['sucesso' => true, 'mensagem' => 'Disciplina actualizada com sucesso.'];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel actualizar a disciplina.'];
        }
    }

    public function removerDisciplina(int $disciplinaId): array
    {
        try {
            $sql = 'DELETE FROM disciplinas WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $disciplinaId]);

            return ['sucesso' => true, 'mensagem' => 'Disciplina removida com sucesso.'];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel remover a disciplina.'];
        }
    }

    public function criarPagamento(array $dados): array
    {
        try {
            $sql = 'INSERT INTO pagamentos (matricula_id, descricao, valor, data_vencimento, status)
                    VALUES (:matricula_id, :descricao, :valor, :data_vencimento, :status)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'matricula_id' => (int) $dados['matricula_id'],
                'descricao' => $dados['descricao'],
                'valor' => (float) $dados['valor'],
                'data_vencimento' => $dados['data_vencimento'],
                'status' => $dados['status']
            ]);

            return ['sucesso' => true, 'mensagem' => 'Pagamento registado com sucesso.'];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel registar o pagamento.'];
        }
    }

    public function atualizarPagamento(int $pagamentoId, array $dados): array
    {
        try {
            $sql = 'UPDATE pagamentos
                    SET descricao = :descricao,
                        valor = :valor,
                        data_vencimento = :data_vencimento,
                        data_pagamento = :data_pagamento,
                        status = :status
                    WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'descricao' => $dados['descricao'],
                'valor' => (float) $dados['valor'],
                'data_vencimento' => $dados['data_vencimento'] ??  null,
                'data_pagamento' => $dados['data_pagamento'] ??  null,
                'status' => $dados['status'],
                'id' => $pagamentoId
            ]);

            return ['sucesso' => true, 'mensagem' => 'Pagamento actualizado com sucesso.'];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel actualizar o pagamento.'];
        }
    }

    public function removerPagamento(int $pagamentoId): array
    {
        try {
            $sql = 'DELETE FROM pagamentos WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $pagamentoId]);

            return ['sucesso' => true, 'mensagem' => 'Pagamento removido com sucesso.'];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel remover o pagamento.'];
        }
    }

    private function atualizarTurmaAluno(int $alunoId, int $turmaId): void
    {
        $sqlMatriculaAtiva = 'SELECT id FROM matriculas WHERE aluno_id = :aluno_id AND status = "activo" LIMIT 1';
        $stmt = $this->db->prepare($sqlMatriculaAtiva);
        $stmt->execute(['aluno_id' => $alunoId]);
        $matriculaId = (int) $stmt->fetchColumn();

        if ($turmaId > 0) {
            if ($matriculaId > 0) {
                $stmtAtualiza = $this->db->prepare('UPDATE matriculas SET turma_id = :turma_id WHERE id = :id');
                $stmtAtualiza->execute(['turma_id' => $turmaId, 'id' => $matriculaId]);
            } else {
                $stmtNovo = $this->db->prepare('INSERT INTO matriculas (aluno_id, turma_id, data_matricula, status) VALUES (:aluno_id, :turma_id, CURDATE(), "activo")');
                $stmtNovo->execute(['aluno_id' => $alunoId, 'turma_id' => $turmaId]);
            }
            return;
        }

        if ($matriculaId > 0) {
            $stmtCancelar = $this->db->prepare('UPDATE matriculas SET status = "transferido" WHERE id = :id');
            $stmtCancelar->execute(['id' => $matriculaId]);
        }
    }
}
