<?php

class PainelOperacionalModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
        $this->garantirEstruturasAcademicas();
    }

    public function listarTurmasSimples(): array
    {
        try {
            return $this->db->query('SELECT id, nome, ano_letivo, sala FROM turmas ORDER BY id')->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function obterTurmaPorId(int $turmaId): ?array
    {
        if ($turmaId <= 0) {
            return null;
        }

        try {
            $stmt = $this->db->prepare('SELECT id, nome, ano_letivo, sala, capacidade, professor_id FROM turmas WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $turmaId]);
            $turma = $stmt->fetch();
            return $turma ?: null;
        } catch (Throwable $erro) {
            return null;
        }
    }

    public function listarDisciplinasSimples(): array
    {
        try {
            return $this->db->query('SELECT id, nome FROM disciplinas ORDER BY nome')->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarDisciplinasProfessor(int $utilizadorId, int $turmaId = 0): array
    {
        if ($utilizadorId <= 0) {
            return [];
        }

        try {
            if ($this->professorUsaAtribuicoesDisciplina($utilizadorId)) {
                $sql = 'SELECT DISTINCT d.id, d.nome
                        FROM professor_turma_disciplinas ptd
                        INNER JOIN professores p ON p.id = ptd.professor_id
                        INNER JOIN disciplinas d ON d.id = ptd.disciplina_id
                        WHERE p.utilizador_id = :utilizador_id';
                $params = ['utilizador_id' => $utilizadorId];

                if ($turmaId > 0) {
                    $sql .= ' AND ptd.turma_id = :turma_id';
                    $params['turma_id'] = $turmaId;
                }

                $sql .= ' ORDER BY d.nome';
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $dados = $stmt->fetchAll() ?: [];
                if (!empty($dados)) {
                    return $dados;
                }
            }
        } catch (Throwable $erro) {
        }

        $turmasProfessor = $this->obterTurmasProfessorIds($utilizadorId);
        if (empty($turmasProfessor)) {
            return [];
        }

        return $this->listarDisciplinasSimples();
    }

    public function listarAnosLetivos(): array
    {
        try {
            return $this->db->query('SELECT id, referencia, ativo, criado_em FROM anos_letivos ORDER BY referencia DESC')->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function criarAnoLetivo(string $referencia, int $criadoPor): bool
    {
        try {
            $stmt = $this->db->prepare('INSERT INTO anos_letivos (referencia, ativo, criado_por) VALUES (:referencia, 0, :criado_por)');
            return $stmt->execute([
                'referencia' => $referencia,
                'criado_por' => $criadoPor
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function definirAnoLetivoAtivo(int $anoId): bool
    {
        try {
            $this->db->beginTransaction();
            $this->db->exec('UPDATE anos_letivos SET ativo = 0');
            $stmt = $this->db->prepare('UPDATE anos_letivos SET ativo = 1 WHERE id = :id');
            $stmt->execute(['id' => $anoId]);
            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function atualizarStatusCandidaturaDocente(int $id, string $status): bool
    {
        $permitidos = ['nova', 'em_analise', 'entrevista', 'aprovada', 'rejeitada'];
        if (!in_array($status, $permitidos, true)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare('UPDATE candidaturas_professor SET status = :status WHERE id = :id');
            return $stmt->execute(['status' => $status, 'id' => $id]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function registarFuncionario(array $dados): array
    {
        $emailInformado = trim((string) ($dados['email'] ?? ''));
        if ($emailInformado === '' || !filter_var($emailInformado, FILTER_VALIDATE_EMAIL)) {
            return ['sucesso' => false, 'mensagem' => 'Informe um email institucional valido para o funcionario.'];
        }

        if ($this->emailExiste($emailInformado)) {
            return ['sucesso' => false, 'mensagem' => 'Ja existe um utilizador com este email.'];
        }

        try {
            $this->db->beginTransaction();

            $email = $emailInformado;
            $senhaInicial = $dados['senha_inicial'] !== '' ? $dados['senha_inicial'] : $this->gerarSenhaInicial();

            $sqlUtilizador = 'INSERT INTO utilizadores (nome, email, senha, perfil, ativo, senha_temporaria)
                              VALUES (:nome, :email, :senha, :perfil, 1, 1)';
            $stmtUtilizador = $this->db->prepare($sqlUtilizador);
            $stmtUtilizador->execute([
                'nome' => $dados['nome'],
                'email' => $email,
                'senha' => password_hash($senhaInicial, PASSWORD_DEFAULT),
                'perfil' => $dados['perfil_acesso']
            ]);
            $utilizadorId = (int) $this->db->lastInsertId();

            if ($dados['perfil_acesso'] === 'professor') {
                $stmtProfessor = $this->db->prepare(
                    'INSERT INTO professores (utilizador_id, especialidade, telefone, data_contratacao)
                     VALUES (:utilizador_id, :especialidade, :telefone, :data_contratacao)'
                );
                $stmtProfessor->execute([
                    'utilizador_id' => $utilizadorId,
                    'especialidade' => $dados['cargo'],
                    'telefone' => $dados['telefone'] !== '' ? $dados['telefone'] : null,
                    'data_contratacao' => $dados['data_contratacao'] !== '' ? $dados['data_contratacao'] : date('Y-m-d')
                ]);
            }

            $stmtFuncionario = $this->db->prepare(
                'INSERT INTO funcionarios (utilizador_id, cargo, telefone, departamento)
                 VALUES (:utilizador_id, :cargo, :telefone, :departamento)'
            );
            $stmtFuncionario->execute([
                'utilizador_id' => $utilizadorId,
                'cargo' => $dados['cargo'],
                'telefone' => $dados['telefone'] !== '' ? $dados['telefone'] : null,
                'departamento' => $dados['departamento']
            ]);

            $this->db->commit();
            return [
                'sucesso' => true,
                'mensagem' => 'Funcionario registado com sucesso.',
                'email' => $email,
                'senha' => $senhaInicial
            ];
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel registar o funcionario.'];
        }
    }

    public function registarDemissao(int $funcionarioId): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT utilizador_id FROM funcionarios WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $funcionarioId]);
            $utilizadorId = (int) $stmt->fetchColumn();

            if ($utilizadorId <= 0) {
                return false;
            }

            $stmtUser = $this->db->prepare('UPDATE utilizadores SET ativo = 0 WHERE id = :id');
            return $stmtUser->execute(['id' => $utilizadorId]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function criarComunicado(array $dados): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO avisos (titulo, mensagem, destinatarios, data_inicio, data_fim, criado_por)
                 VALUES (:titulo, :mensagem, :destinatarios, :data_inicio, :data_fim, :criado_por)'
            );
            return $stmt->execute([
                'titulo' => $dados['titulo'],
                'mensagem' => $dados['mensagem'],
                'destinatarios' => $dados['destinatarios'],
                'data_inicio' => $dados['data_inicio'] !== '' ? $dados['data_inicio'] : null,
                'data_fim' => $dados['data_fim'] !== '' ? $dados['data_fim'] : null,
                'criado_por' => $dados['criado_por']
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function criarMensagemMassa(int $remetenteId, string $perfilDestino, string $assunto, string $mensagem): bool
    {
        $permitidos = ['todos', 'aluno', 'encarregado', 'professor', 'secretaria', 'direcao_pedagogica', 'direcao_geral', 'rh'];
        if (!in_array($perfilDestino, $permitidos, true)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO mensagens_internas (remetente_id, destinatario_id, perfil_destino, assunto, mensagem, status, criado_em)
                 VALUES (:remetente_id, NULL, :perfil_destino, :assunto, :mensagem, "nao_lida", NOW())'
            );
            return $stmt->execute([
                'remetente_id' => $remetenteId,
                'perfil_destino' => $perfilDestino,
                'assunto' => $assunto,
                'mensagem' => $mensagem
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarComunicadosPorUtilizador(int $utilizadorId, int $limite = 20): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT titulo, mensagem, destinatarios, criado_em
                    FROM avisos
                    WHERE criado_por = :criado_por
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['criado_por' => $utilizadorId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarSolicitacoesDocumentos(
        int $limite = 30,
        array $tipos = [],
        array $estados = ['pendente']
    ): array
    {
        $limite = max(1, $limite);
        try {
            $params = [];
            $where = [];

            if (!empty($tipos)) {
                $tipos = array_values(array_filter(array_map('strval', $tipos)));
                $placeholders = [];
                foreach ($tipos as $indice => $tipo) {
                    $chave = 'tipo_' . $indice;
                    $placeholders[] = ':' . $chave;
                    $params[$chave] = $tipo;
                }
                if (!empty($placeholders)) {
                    $where[] = 's.tipo_documento IN (' . implode(', ', $placeholders) . ')';
                }
            }

            if (!empty($estados)) {
                $estados = array_values(array_filter(array_map('strval', $estados)));
                $placeholders = [];
                foreach ($estados as $indice => $estado) {
                    $chave = 'estado_' . $indice;
                    $placeholders[] = ':' . $chave;
                    $params[$chave] = $estado;
                }
                if (!empty($placeholders)) {
                    $where[] = 's.estado IN (' . implode(', ', $placeholders) . ')';
                }
            }

            $whereSql = '';
            if (!empty($where)) {
                $whereSql = 'WHERE ' . implode(' AND ', $where);
            }

            $sql = "SELECT
                        s.id,
                        s.tipo_documento,
                        s.estado,
                        s.observacao,
                        s.criado_em,
                        s.autorizado_em,
                        ua.nome AS aluno
                    FROM solicitacoes_documentos s
                    INNER JOIN alunos a ON a.id = s.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    {$whereSql}
                    ORDER BY s.criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function processarSolicitacaoDocumento(int $id, string $estado, int $autorizadoPor, string $observacao): bool
    {
        $permitidos = ['autorizado', 'rejeitado', 'disponibilizado', 'pendente'];
        if (!in_array($estado, $permitidos, true)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                'UPDATE solicitacoes_documentos
                 SET estado = :estado,
                     observacao = :observacao,
                     autorizado_por = :autorizado_por,
                     autorizado_em = NOW()
                 WHERE id = :id'
            );
            return $stmt->execute([
                'estado' => $estado,
                'observacao' => $observacao !== '' ? $observacao : null,
                'autorizado_por' => $autorizadoPor,
                'id' => $id
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function resumirSolicitacoes(array $tipos = []): array
    {
        $resultado = [
            'pendente' => 0,
            'autorizado' => 0,
            'rejeitado' => 0
        ];

        try {
            $params = [];
            $whereSql = '';
            if (!empty($tipos)) {
                $tipos = array_values(array_filter(array_map('strval', $tipos)));
                $placeholders = [];
                foreach ($tipos as $indice => $tipo) {
                    $chave = 'tipo_' . $indice;
                    $placeholders[] = ':' . $chave;
                    $params[$chave] = $tipo;
                }
                if (!empty($placeholders)) {
                    $whereSql = 'WHERE tipo_documento IN (' . implode(', ', $placeholders) . ')';
                }
            }

            $sql = "SELECT estado, COUNT(*) AS total
                    FROM solicitacoes_documentos
                    {$whereSql}
                    GROUP BY estado";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            foreach (($stmt->fetchAll() ?: []) as $linha) {
                $estado = (string) ($linha['estado'] ?? '');
                if (array_key_exists($estado, $resultado)) {
                    $resultado[$estado] = (int) ($linha['total'] ?? 0);
                }
            }
        } catch (Throwable $erro) {
        }

        return $resultado;
    }

    public function listarCursos(int $limite = 50): array
    {
        $limite = max(1, $limite);
        $temDescricao = $this->colunaExiste('cursos', 'descricao');
        $temCor = $this->colunaExiste('cursos', 'cor');
        $temImagem = $this->colunaExiste('cursos', 'imagem_path');
        $descricaoSql = $temDescricao ? 'c.descricao AS descricao' : 'NULL AS descricao';
        $corSql = $temCor ? 'c.cor AS cor' : 'NULL AS cor';
        $imagemSql = $temImagem ? 'c.imagem_path AS imagem_path' : 'NULL AS imagem_path';
        $grupoExtra = [];
        if ($temDescricao) {
            $grupoExtra[] = 'c.descricao';
        }
        if ($temCor) {
            $grupoExtra[] = 'c.cor';
        }
        if ($temImagem) {
            $grupoExtra[] = 'c.imagem_path';
        }
        $grupoSql = '';
        if (!empty($grupoExtra)) {
            $grupoSql = ', ' . implode(', ', $grupoExtra);
        }
        try {
            $sql = "SELECT
                        c.id,
                        c.nome,
                        c.ano_curso,
                        c.quantidade_disciplinas,
                        {$descricaoSql},
                        {$corSql},
                        {$imagemSql},
                        c.publicado_index,
                        GROUP_CONCAT(d.nome ORDER BY d.nome SEPARATOR ', ') AS disciplinas
                    FROM cursos c
                    LEFT JOIN cursos_disciplinas cd ON cd.curso_id = c.id
                    LEFT JOIN disciplinas d ON d.id = cd.disciplina_id
                    GROUP BY c.id, c.nome, c.ano_curso, c.quantidade_disciplinas, c.publicado_index{$grupoSql}
                    ORDER BY c.criado_em DESC
                    LIMIT {$limite}";
            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function criarCurso(array $dados): bool
    {
        try {
            $this->db->beginTransaction();
            $temDescricao = $this->colunaExiste('cursos', 'descricao');
            $temCor = $this->colunaExiste('cursos', 'cor');
            $temImagem = $this->colunaExiste('cursos', 'imagem_path');

            if ($temDescricao || $temCor || $temImagem) {
                $colunas = ['nome', 'ano_curso', 'quantidade_disciplinas'];
                $valores = [':nome', ':ano_curso', ':quantidade_disciplinas'];
                $params = [
                    'nome' => $dados['nome'],
                    'ano_curso' => $dados['ano_curso'],
                    'quantidade_disciplinas' => (int) $dados['quantidade_disciplinas']
                ];

                if ($temDescricao) {
                    $colunas[] = 'descricao';
                    $valores[] = ':descricao';
                    $params['descricao'] = trim((string) ($dados['descricao'] ?? '')) !== '' ? trim((string) ($dados['descricao'] ?? '')) : null;
                }
                if ($temCor) {
                    $colunas[] = 'cor';
                    $valores[] = ':cor';
                    $params['cor'] = trim((string) ($dados['cor'] ?? '')) !== '' ? trim((string) ($dados['cor'] ?? '')) : null;
                }
                if ($temImagem) {
                    $colunas[] = 'imagem_path';
                    $valores[] = ':imagem_path';
                    $params['imagem_path'] = trim((string) ($dados['imagem_path'] ?? '')) !== '' ? trim((string) ($dados['imagem_path'] ?? '')) : null;
                }

                $colunas[] = 'publicado_index';
                $valores[] = ':publicado_index';
                $params['publicado_index'] = (int) $dados['publicado_index'];
                $colunas[] = 'criado_por';
                $valores[] = ':criado_por';
                $params['criado_por'] = (int) $dados['criado_por'];

                $sql = 'INSERT INTO cursos (' . implode(', ', $colunas) . ') VALUES (' . implode(', ', $valores) . ')';
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO cursos (nome, ano_curso, quantidade_disciplinas, publicado_index, criado_por)
                     VALUES (:nome, :ano_curso, :quantidade_disciplinas, :publicado_index, :criado_por)'
                );
                $stmt->execute([
                    'nome' => $dados['nome'],
                    'ano_curso' => $dados['ano_curso'],
                    'quantidade_disciplinas' => (int) $dados['quantidade_disciplinas'],
                    'publicado_index' => (int) $dados['publicado_index'],
                    'criado_por' => (int) $dados['criado_por']
                ]);
            }

            $cursoId = (int) $this->db->lastInsertId();
            $this->atualizarDisciplinasCurso($cursoId, $dados['disciplinas']);
            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function atualizarCurso(array $dados): bool
    {
        try {
            $this->db->beginTransaction();
            $temDescricao = $this->colunaExiste('cursos', 'descricao');
            $temCor = $this->colunaExiste('cursos', 'cor');
            $temImagem = $this->colunaExiste('cursos', 'imagem_path');

            $campos = [
                'nome = :nome',
                'ano_curso = :ano_curso',
                'quantidade_disciplinas = :quantidade_disciplinas',
                'publicado_index = :publicado_index'
            ];
            $params = [
                'nome' => $dados['nome'],
                'ano_curso' => $dados['ano_curso'],
                'quantidade_disciplinas' => (int) $dados['quantidade_disciplinas'],
                'publicado_index' => (int) $dados['publicado_index'],
                'id' => (int) $dados['id']
            ];

            if ($temDescricao) {
                $campos[] = 'descricao = :descricao';
                $params['descricao'] = trim((string) ($dados['descricao'] ?? '')) !== '' ? trim((string) ($dados['descricao'] ?? '')) : null;
            }
            if ($temCor) {
                $campos[] = 'cor = :cor';
                $params['cor'] = trim((string) ($dados['cor'] ?? '')) !== '' ? trim((string) ($dados['cor'] ?? '')) : null;
            }
            if ($temImagem) {
                $campos[] = 'imagem_path = :imagem_path';
                $params['imagem_path'] = trim((string) ($dados['imagem_path'] ?? '')) !== '' ? trim((string) ($dados['imagem_path'] ?? '')) : null;
            }

            $sql = 'UPDATE cursos SET ' . implode(', ', $campos) . ' WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $this->atualizarDisciplinasCurso((int) $dados['id'], $dados['disciplinas']);
            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function removerCurso(int $cursoId): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM cursos WHERE id = :id');
            return $stmt->execute(['id' => $cursoId]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarComprovativosPagamento(int $limite = 40, array $estados = []): array
    {
        $limite = max(1, $limite);
        $temMetodo = $this->colunaExiste('comprovativos_pagamento', 'metodo_pagamento');
        $temRecibo = $this->colunaExiste('comprovativos_pagamento', 'recibo_pdf');
        $metodoSql = $temMetodo ? 'cp.metodo_pagamento' : '"referencia" AS metodo_pagamento';
        $reciboSql = $temRecibo ? 'cp.recibo_pdf' : 'NULL AS recibo_pdf';
        try {
            $where = '';
            $params = [];
            $estados = array_values(array_filter(array_map('strval', $estados), static function (string $estado): bool {
                return in_array($estado, ['pendente', 'aprovado', 'rejeitado'], true);
            }));

            if (!empty($estados)) {
                $placeholders = [];
                foreach ($estados as $indice => $estado) {
                    $chave = 'estado_' . $indice;
                    $placeholders[] = ':' . $chave;
                    $params[$chave] = $estado;
                }
                $where = 'WHERE cp.estado IN (' . implode(', ', $placeholders) . ')';
            }

            $sql = "SELECT
                        cp.id,
                        cp.codigo_referencia,
                        cp.mes_referencia,
                        cp.valor,
                        cp.estado,
                        cp.comprovativo_path,
                        {$metodoSql},
                        {$reciboSql},
                        cp.criado_em,
                        cp.pagamento_id,
                        ua.nome AS aluno,
                        ue.nome AS encarregado
                    FROM comprovativos_pagamento cp
                    INNER JOIN alunos a ON a.id = cp.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    INNER JOIN encarregados e ON e.id = cp.encarregado_id
                    INNER JOIN utilizadores ue ON ue.id = e.utilizador_id
                    {$where}
                    ORDER BY cp.criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function analisarComprovativo(int $id, string $estado, string $observacao, int $analisadoPor): bool
    {
        $permitidos = ['aprovado', 'rejeitado'];
        if (!in_array($estado, $permitidos, true)) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                'UPDATE comprovativos_pagamento
                 SET estado = :estado,
                     observacao_secretaria = :observacao,
                     analisado_por = :analisado_por,
                     analisado_em = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'estado' => $estado,
                'observacao' => $observacao !== '' ? $observacao : null,
                'analisado_por' => $analisadoPor,
                'id' => $id
            ]);

            if ($estado === 'aprovado') {
                $stmtPag = $this->db->prepare('SELECT pagamento_id FROM comprovativos_pagamento WHERE id = :id LIMIT 1');
                $stmtPag->execute(['id' => $id]);
                $pagamentoId = (int) $stmtPag->fetchColumn();

                if ($pagamentoId > 0) {
                    $stmtUpdatePagamento = $this->db->prepare('UPDATE pagamentos SET status = "pago", data_pagamento = CURDATE() WHERE id = :id');
                    $stmtUpdatePagamento->execute(['id' => $pagamentoId]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function listarAlunosDisponiveisSecretaria(int $limite = 120): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT
                        a.id,
                        a.utilizador_id,
                        u.nome AS aluno,
                        COALESCE(t.nome, 'Sem turma') AS turma,
                        COALESCE(ue.nome, 'Sem encarregado') AS encarregado,
                        e.id AS encarregado_id
                    FROM alunos a
                    INNER JOIN utilizadores u ON u.id = a.utilizador_id
                    LEFT JOIN matriculas m ON m.aluno_id = a.id AND m.status = 'activo'
                    LEFT JOIN turmas t ON t.id = m.turma_id
                    LEFT JOIN encarregado_aluno ea ON ea.aluno_id = a.id
                    LEFT JOIN encarregados e ON e.id = ea.encarregado_id
                    LEFT JOIN utilizadores ue ON ue.id = e.utilizador_id
                    ORDER BY u.nome
                    LIMIT {$limite}";
            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarDocumentosPartilhadosSecretaria(int $limite = 30): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT
                        dp.id,
                        dp.titulo,
                        dp.descricao,
                        dp.perfil_destino,
                        dp.ficheiro_path,
                        dp.criado_em,
                        ua.nome AS aluno,
                        ue.nome AS encarregado,
                        ur.nome AS criado_por_nome
                    FROM documentos_partilhados dp
                    LEFT JOIN alunos a ON a.id = dp.aluno_id
                    LEFT JOIN utilizadores ua ON ua.id = a.utilizador_id
                    LEFT JOIN encarregados e ON e.id = dp.encarregado_id
                    LEFT JOIN utilizadores ue ON ue.id = e.utilizador_id
                    LEFT JOIN utilizadores ur ON ur.id = dp.criado_por
                    WHERE dp.lixeira = 0
                    ORDER BY dp.criado_em DESC
                    LIMIT {$limite}";
            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function partilharDocumentoSecretaria(array $dados): array
    {
        $perfilDestino = trim((string) ($dados['perfil_destino'] ?? ''));
        $titulo = trim((string) ($dados['titulo'] ?? ''));
        $ficheiroPath = trim((string) ($dados['ficheiro_path'] ?? ''));

        if ($titulo === '' || $ficheiroPath === '') {
            return ['sucesso' => false, 'mensagem' => 'Informe o titulo e o ficheiro do documento.'];
        }

        if (!in_array($perfilDestino, ['aluno', 'encarregado'], true)) {
            return ['sucesso' => false, 'mensagem' => 'Selecione um destinatario valido para o documento.'];
        }

        $alunoId = $perfilDestino === 'aluno' ? (int) ($dados['aluno_id'] ?? 0) : 0;
        $encarregadoId = $perfilDestino === 'encarregado' ? (int) ($dados['encarregado_id'] ?? 0) : 0;
        if ($alunoId <= 0 && $encarregadoId <= 0) {
            return ['sucesso' => false, 'mensagem' => 'Selecione o destinatario do documento.'];
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO documentos_partilhados
                 (titulo, descricao, perfil_destino, aluno_id, encarregado_id, ficheiro_path, criado_por, lixeira, criado_em)
                 VALUES (:titulo, :descricao, :perfil_destino, :aluno_id, :encarregado_id, :ficheiro_path, :criado_por, 0, NOW())'
            );
            $ok = $stmt->execute([
                'titulo' => $titulo,
                'descricao' => (($dados['descricao'] ?? '') !== '') ? (string) $dados['descricao'] : null,
                'perfil_destino' => $perfilDestino,
                'aluno_id' => $alunoId > 0 ? $alunoId : null,
                'encarregado_id' => $encarregadoId > 0 ? $encarregadoId : null,
                'ficheiro_path' => $ficheiroPath,
                'criado_por' => (int) ($dados['criado_por'] ?? 0),
            ]);

            if (!$ok) {
                return ['sucesso' => false, 'mensagem' => 'Nao foi possivel partilhar o documento.'];
            }

            $destinatarioUtilizadorId = 0;
            if ($perfilDestino === 'aluno' && $alunoId > 0) {
                $stmtAluno = $this->db->prepare('SELECT utilizador_id FROM alunos WHERE id = :id LIMIT 1');
                $stmtAluno->execute(['id' => $alunoId]);
                $destinatarioUtilizadorId = (int) $stmtAluno->fetchColumn();
            }

            if ($perfilDestino === 'encarregado' && $encarregadoId > 0) {
                $stmtEncarregado = $this->db->prepare('SELECT utilizador_id FROM encarregados WHERE id = :id LIMIT 1');
                $stmtEncarregado->execute(['id' => $encarregadoId]);
                $destinatarioUtilizadorId = (int) $stmtEncarregado->fetchColumn();
            }

            if ($destinatarioUtilizadorId > 0) {
                $this->enviarMensagemInterna(
                    (int) ($dados['criado_por'] ?? 0),
                    $destinatarioUtilizadorId,
                    $perfilDestino,
                    'Novo documento enviado pela secretaria',
                    'O documento "' . $titulo . '" ja esta disponivel no seu painel.'
                );
            }

            return ['sucesso' => true, 'mensagem' => 'Documento partilhado com sucesso.'];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel partilhar o documento.'];
        }
    }

    public function listarDocumentosRecebidosAluno(int $alunoId, int $limite = 20): array
    {
        $limite = max(1, $limite);
        if ($alunoId <= 0) {
            return [];
        }

        try {
            $sql = "SELECT titulo, descricao, ficheiro_path, criado_em
                    FROM documentos_partilhados
                    WHERE perfil_destino = 'aluno'
                      AND aluno_id = :aluno_id
                      AND lixeira = 0
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['aluno_id' => $alunoId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarDocumentosRecebidosEncarregado(int $encarregadoId, int $limite = 20): array
    {
        $limite = max(1, $limite);
        if ($encarregadoId <= 0) {
            return [];
        }

        try {
            $sql = "SELECT titulo, descricao, ficheiro_path, criado_em
                    FROM documentos_partilhados
                    WHERE perfil_destino = 'encarregado'
                      AND encarregado_id = :encarregado_id
                      AND lixeira = 0
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['encarregado_id' => $encarregadoId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function enviarMensagemEncarregadoSecretaria(int $remetenteId, int $encarregadoId, string $assunto, string $mensagem): bool
    {
        if ($encarregadoId <= 0 || trim($mensagem) === '') {
            return false;
        }

        try {
            $stmt = $this->db->prepare('SELECT utilizador_id FROM encarregados WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $encarregadoId]);
            $destinatarioId = (int) $stmt->fetchColumn();
            if ($destinatarioId <= 0) {
                return false;
            }

            return $this->enviarMensagemInterna($remetenteId, $destinatarioId, 'encarregado', $assunto, $mensagem);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarAtividadesExtracurriculares(int $limite = 50): array
    {
        $limite = max(1, $limite);
        $temCategoria = $this->colunaExiste('atividades_extracurriculares', 'categoria');
        $temDataAtividade = $this->colunaExiste('atividades_extracurriculares', 'data_atividade');
        $categoriaSql = $temCategoria ? 'categoria' : '"eventos" AS categoria';
        $dataSql = $temDataAtividade ? 'data_atividade' : 'DATE(criado_em) AS data_atividade';
        try {
            $sql = "SELECT id, tema, descricao, preco, imagem_path, {$categoriaSql}, {$dataSql}, publicado_index, criado_em
                    FROM atividades_extracurriculares
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function criarAtividadeExtracurricular(array $dados): bool
    {
        try {
            $temCategoria = $this->colunaExiste('atividades_extracurriculares', 'categoria');
            $temDataAtividade = $this->colunaExiste('atividades_extracurriculares', 'data_atividade');

            $colunas = ['tema', 'descricao', 'preco', 'imagem_path', 'publicado_index', 'criado_por'];
            $valores = [':tema', ':descricao', ':preco', ':imagem_path', ':publicado_index', ':criado_por'];
            $params = [
                'tema' => $dados['tema'],
                'descricao' => $dados['descricao'],
                'preco' => (float) $dados['preco'],
                'imagem_path' => $dados['imagem_path'] !== '' ? $dados['imagem_path'] : null,
                'publicado_index' => (int) $dados['publicado_index'],
                'criado_por' => (int) $dados['criado_por']
            ];

            if ($temCategoria) {
                $colunas[] = 'categoria';
                $valores[] = ':categoria';
                $params['categoria'] = trim((string) ($dados['categoria'] ?? 'eventos'));
            }

            if ($temDataAtividade) {
                $colunas[] = 'data_atividade';
                $valores[] = ':data_atividade';
                $params['data_atividade'] = (($dados['data_atividade'] ?? '') !== '') ? (string) $dados['data_atividade'] : null;
            }

            $stmt = $this->db->prepare(
                'INSERT INTO atividades_extracurriculares (' . implode(', ', $colunas) . ')
                 VALUES (' . implode(', ', $valores) . ')'
            );
            return $stmt->execute($params);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function atualizarAtividadeExtracurricular(array $dados): bool
    {
        try {
            $temCategoria = $this->colunaExiste('atividades_extracurriculares', 'categoria');
            $temDataAtividade = $this->colunaExiste('atividades_extracurriculares', 'data_atividade');

            $campos = [
                'tema = :tema',
                'descricao = :descricao',
                'preco = :preco',
                'imagem_path = :imagem_path',
                'publicado_index = :publicado_index'
            ];
            $params = [
                'tema' => $dados['tema'],
                'descricao' => $dados['descricao'],
                'preco' => (float) $dados['preco'],
                'imagem_path' => $dados['imagem_path'] !== '' ? $dados['imagem_path'] : null,
                'publicado_index' => (int) $dados['publicado_index'],
                'id' => (int) $dados['id']
            ];

            if ($temCategoria) {
                $campos[] = 'categoria = :categoria';
                $params['categoria'] = trim((string) ($dados['categoria'] ?? 'eventos'));
            }

            if ($temDataAtividade) {
                $campos[] = 'data_atividade = :data_atividade';
                $params['data_atividade'] = (($dados['data_atividade'] ?? '') !== '') ? (string) $dados['data_atividade'] : null;
            }

            $stmt = $this->db->prepare(
                'UPDATE atividades_extracurriculares
                 SET ' . implode(', ', $campos) . '
                 WHERE id = :id'
            );
            return $stmt->execute($params);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function removerAtividadeExtracurricular(int $atividadeId): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM atividades_extracurriculares WHERE id = :id');
            return $stmt->execute(['id' => $atividadeId]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarAtividadesPublicadasIndex(int $limite = 8): array
    {
        $limite = max(1, $limite);
        $temCategoria = $this->colunaExiste('atividades_extracurriculares', 'categoria');
        $temDataAtividade = $this->colunaExiste('atividades_extracurriculares', 'data_atividade');
        $categoriaSql = $temCategoria ? 'categoria' : '"eventos" AS categoria';
        $dataSql = $temDataAtividade ? 'data_atividade' : 'DATE(criado_em) AS data_atividade';
        try {
            $sql = "SELECT id, tema, descricao, preco, imagem_path, {$categoriaSql}, {$dataSql}
                    FROM atividades_extracurriculares
                    WHERE publicado_index = 1
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function obterAnaliticaAtividades(int $limite = 20): array
    {
        $limite = max(1, $limite);

        $resultado = [
            'atividades' => [],
            'grafico' => [
                'etiquetas' => [],
                'valores' => []
            ],
            'total_pago' => 0.0,
            'total_pagamentos' => 0
        ];

        try {
            $sql = "SELECT
                        ae.id,
                        ae.tema,
                        ae.preco,
                        COUNT(CASE WHEN pa.estado = 'pago' THEN 1 END) AS pagamentos_confirmados,
                        COALESCE(SUM(CASE WHEN pa.estado = 'pago' THEN pa.valor ELSE 0 END), 0) AS total_recebido
                    FROM atividades_extracurriculares ae
                    LEFT JOIN pagamentos_atividades pa ON pa.atividade_id = ae.id
                    GROUP BY ae.id, ae.tema, ae.preco
                    ORDER BY ae.criado_em DESC
                    LIMIT {$limite}";
            $dados = $this->db->query($sql)->fetchAll() ?: [];

            foreach ($dados as $linha) {
                $resultado['atividades'][] = $linha;
                $resultado['grafico']['etiquetas'][] = (string) ($linha['tema'] ?? 'Atividade');
                $resultado['grafico']['valores'][] = (float) ($linha['total_recebido'] ?? 0);
                $resultado['total_pago'] += (float) ($linha['total_recebido'] ?? 0);
                $resultado['total_pagamentos'] += (int) ($linha['pagamentos_confirmados'] ?? 0);
            }
        } catch (Throwable $erro) {
        }

        return $resultado;
    }

    public function listarCursosPublicadosIndex(int $limite = 8): array
    {
        $limite = max(1, $limite);
        $temDescricao = $this->colunaExiste('cursos', 'descricao');
        $temCor = $this->colunaExiste('cursos', 'cor');
        $temImagem = $this->colunaExiste('cursos', 'imagem_path');
        $descricaoSql = $temDescricao ? 'descricao' : 'NULL AS descricao';
        $corSql = $temCor ? 'cor' : 'NULL AS cor';
        $imagemSql = $temImagem ? 'imagem_path' : 'NULL AS imagem_path';
        try {
            $sql = "SELECT id, nome, ano_curso, quantidade_disciplinas, {$descricaoSql}, {$corSql}, {$imagemSql}
                    FROM cursos
                    WHERE publicado_index = 1
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarPreMatriculasDetalhadas(int $limite = 30): array
    {
        $limite = max(1, $limite);
        $temCursoPretendido = $this->colunaExiste('pre_matriculas', 'curso_pretendido');
        $cursoSql = $temCursoPretendido ? 'curso_pretendido' : 'NULL AS curso_pretendido';
        try {
            $sql = "SELECT
                        id,
                        codigo,
                        nome_encarregado,
                        email_encarregado,
                        telefone_encarregado,
                        nome_aluno,
                        data_nascimento_aluno,
                        ano_pretendido,
                        {$cursoSql},
                        observacoes,
                        status,
                        criado_em
                    FROM pre_matriculas
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarEncarregadosDisponiveis(int $limite = 100): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT e.id, e.utilizador_id, e.telefone, u.nome, u.email
                    FROM encarregados e
                    INNER JOIN utilizadores u ON u.id = e.utilizador_id
                    ORDER BY u.nome
                    LIMIT {$limite}";
            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarProfessoresResumoPedagogico(int $limite = 120): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT
                        p.id,
                        u.nome,
                        u.email,
                        p.especialidade,
                        p.data_contratacao,
                        TIMESTAMPDIFF(YEAR, p.data_contratacao, CURDATE()) AS anos_na_instituicao,
                        GROUP_CONCAT(DISTINCT d.nome ORDER BY d.nome SEPARATOR ', ') AS disciplinas
                    FROM professores p
                    INNER JOIN utilizadores u ON u.id = p.utilizador_id
                    LEFT JOIN professor_turma_disciplinas ptd ON ptd.professor_id = p.id
                    LEFT JOIN disciplinas d ON d.id = ptd.disciplina_id
                    WHERE u.ativo = 1
                    GROUP BY p.id, u.nome, u.email, p.especialidade, p.data_contratacao
                    ORDER BY u.nome ASC
                    LIMIT {$limite}";
            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function concluirMatriculaCompleta(array $dados): array
    {
        try {
            $this->db->beginTransaction();

            $encarregadoId = 0;
            $credenciaisEncarregado = null;

            if ((int) $dados['encarregado_id_existente'] > 0) {
                $encarregadoId = (int) $dados['encarregado_id_existente'];
            } else {
                $emailEncarregadoInformado = trim((string) ($dados['email_encarregado'] ?? ''));
                $emailEncarregado = $emailEncarregadoInformado;
                if ($emailEncarregado === '' || !filter_var($emailEncarregado, FILTER_VALIDATE_EMAIL)) {
                    $emailEncarregado = $this->gerarEmailSequencial('encarregado', 'fascal.ao');
                }

                if ($this->emailExiste($emailEncarregado)) {
                    $stmtExistente = $this->db->prepare(
                        'SELECT e.id
                         FROM encarregados e
                         INNER JOIN utilizadores u ON u.id = e.utilizador_id
                         WHERE u.email = :email
                           AND u.perfil = "encarregado"
                         LIMIT 1'
                    );
                    $stmtExistente->execute(['email' => $emailEncarregado]);
                    $encarregadoExistente = (int) $stmtExistente->fetchColumn();
                    if ($encarregadoExistente > 0) {
                        $encarregadoId = $encarregadoExistente;
                    } else {
                        $emailEncarregado = $this->gerarEmailSequencial('encarregado', 'fascal.ao');
                    }
                }

                $senhaEncarregado = $this->gerarSenhaInicial();
                $senhaEncarregadoExpira = date('Y-m-d H:i:s', strtotime('+24 hours'));

                if ($encarregadoId <= 0) {
                    $stmtUE = $this->db->prepare(
                        'INSERT INTO utilizadores (nome, email, senha, perfil, ativo, senha_temporaria, senha_valida_ate, senha_ativa)
                         VALUES (:nome, :email, :senha, "encarregado", 1, 1, :senha_valida_ate, 1)'
                    );
                    $stmtUE->execute([
                        'nome' => $dados['nome_encarregado'],
                        'email' => $emailEncarregado,
                        'senha' => password_hash($senhaEncarregado, PASSWORD_DEFAULT),
                        'senha_valida_ate' => $senhaEncarregadoExpira
                    ]);
                    $utilizadorEncarregadoId = (int) $this->db->lastInsertId();

                    $stmtE = $this->db->prepare(
                        'INSERT INTO encarregados (utilizador_id, telefone, endereco, parentesco)
                         VALUES (:utilizador_id, :telefone, :endereco, :parentesco)'
                    );
                    $stmtE->execute([
                        'utilizador_id' => $utilizadorEncarregadoId,
                        'telefone' => $dados['telefone_encarregado'] !== '' ? $dados['telefone_encarregado'] : null,
                        'endereco' => $dados['endereco_encarregado'] !== '' ? $dados['endereco_encarregado'] : null,
                        'parentesco' => $dados['parentesco'] !== '' ? $dados['parentesco'] : 'Encarregado'
                    ]);
                    $encarregadoId = (int) $this->db->lastInsertId();

                    $credenciaisEncarregado = [
                        'id' => $encarregadoId,
                        'email' => $emailEncarregado,
                        'senha' => $senhaEncarregado,
                        'senha_valida_ate' => $senhaEncarregadoExpira
                    ];
                }
            }

            $emailAluno = $this->gerarEmailInstitucionalMatricula((string) $dados['nome_encarregado'], (string) $dados['nome_aluno']);
            $senhaAluno = $this->gerarSenhaInicial();
            $senhaAlunoExpira = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmtUA = $this->db->prepare(
                'INSERT INTO utilizadores (nome, email, senha, perfil, ativo, senha_temporaria, senha_valida_ate, senha_ativa)
                 VALUES (:nome, :email, :senha, "aluno", 1, 1, :senha_valida_ate, 1)'
            );
            $stmtUA->execute([
                'nome' => $dados['nome_aluno'],
                'email' => $emailAluno,
                'senha' => password_hash($senhaAluno, PASSWORD_DEFAULT),
                'senha_valida_ate' => $senhaAlunoExpira
            ]);
            $utilizadorAlunoId = (int) $this->db->lastInsertId();

            $stmtA = $this->db->prepare(
                'INSERT INTO alunos (utilizador_id, data_nascimento, bi, genero, endereco, contato, email_institucional, senha_valida_ate, senha_ativa)
                 VALUES (:utilizador_id, :data_nascimento, :bi, :genero, :endereco, :contato, :email_institucional, :senha_valida_ate, 1)'
            );
            $stmtA->execute([
                'utilizador_id' => $utilizadorAlunoId,
                'data_nascimento' => $dados['data_nascimento_aluno'],
                'bi' => $dados['bi_aluno'] !== '' ? $dados['bi_aluno'] : null,
                'genero' => $dados['genero_aluno'] !== '' ? $dados['genero_aluno'] : null,
                'endereco' => $dados['endereco_aluno'] !== '' ? $dados['endereco_aluno'] : null,
                'contato' => $dados['contacto_aluno'] !== '' ? $dados['contacto_aluno'] : null,
                'email_institucional' => $emailAluno,
                'senha_valida_ate' => $senhaAlunoExpira
            ]);
            $alunoId = (int) $this->db->lastInsertId();

            $stmtEA = $this->db->prepare('INSERT INTO encarregado_aluno (encarregado_id, aluno_id) VALUES (:encarregado_id, :aluno_id)');
            $stmtEA->execute([
                'encarregado_id' => $encarregadoId,
                'aluno_id' => $alunoId
            ]);

            $stmtM = $this->db->prepare('INSERT INTO matriculas (aluno_id, turma_id, data_matricula, status) VALUES (:aluno_id, :turma_id, CURDATE(), "activo")');
            $stmtM->execute([
                'aluno_id' => $alunoId,
                'turma_id' => (int) $dados['turma_id']
            ]);

            $professoresTurma = $this->listarUtilizadoresProfessoresTurma((int) $dados['turma_id']);
            if (!empty($professoresTurma)) {
                $stmtNotificacao = $this->db->prepare(
                    'INSERT INTO mensagens_internas
                     (remetente_id, destinatario_id, perfil_destino, assunto, mensagem, status, criado_em)
                     VALUES (NULL, :destinatario_id, "professor", :assunto, :mensagem, "nao_lida", NOW())'
                );

                foreach ($professoresTurma as $utilizadorProfessor) {
                    $stmtNotificacao->execute([
                        'destinatario_id' => $utilizadorProfessor,
                        'assunto' => 'Novo aluno matriculado',
                        'mensagem' => 'A secretaria matriculou o aluno ' . $dados['nome_aluno'] . ' na turma atribuida.'
                    ]);
                }
            }

            if ((int) $dados['pre_matricula_id'] > 0) {
                $stmtP = $this->db->prepare('UPDATE pre_matriculas SET status = "concluida" WHERE id = :id');
                $stmtP->execute(['id' => (int) $dados['pre_matricula_id']]);
            }

            $this->db->commit();

            $this->registarEmailMatriculaLog(
                $dados,
                [
                    'email' => $emailAluno,
                    'senha' => $senhaAluno,
                    'senha_valida_ate' => $senhaAlunoExpira
                ],
                $credenciaisEncarregado
            );

            return [
                'sucesso' => true,
                'mensagem' => 'Matricula completa concluida.',
                'aluno_id' => $alunoId,
                'credenciais_aluno' => ['email' => $emailAluno, 'senha' => $senhaAluno],
                'credenciais_encarregado' => $credenciaisEncarregado
            ];
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel concluir a matricula completa.'];
        }
    }

    public function guardarDocumentosMatricula(int $alunoId, array $dados): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO matricula_documentos
                 (aluno_id, foto_1, foto_2, foto_3, foto_4, bi_copia, atestado_medico, documento_classe_anterior, observacoes)
                 VALUES
                 (:aluno_id, :foto_1, :foto_2, :foto_3, :foto_4, :bi_copia, :atestado_medico, :documento_classe_anterior, :observacoes)
                 ON DUPLICATE KEY UPDATE
                    foto_1 = VALUES(foto_1),
                    foto_2 = VALUES(foto_2),
                    foto_3 = VALUES(foto_3),
                    foto_4 = VALUES(foto_4),
                    bi_copia = VALUES(bi_copia),
                    atestado_medico = VALUES(atestado_medico),
                    documento_classe_anterior = VALUES(documento_classe_anterior),
                    observacoes = VALUES(observacoes)'
            );
            return $stmt->execute([
                'aluno_id' => $alunoId,
                'foto_1' => (($dados['foto_1'] ?? '') !== '') ? (string) $dados['foto_1'] : null,
                'foto_2' => (($dados['foto_2'] ?? '') !== '') ? (string) $dados['foto_2'] : null,
                'foto_3' => (($dados['foto_3'] ?? '') !== '') ? (string) $dados['foto_3'] : null,
                'foto_4' => (($dados['foto_4'] ?? '') !== '') ? (string) $dados['foto_4'] : null,
                'bi_copia' => (($dados['bi_copia'] ?? '') !== '') ? (string) $dados['bi_copia'] : null,
                'atestado_medico' => (($dados['atestado_medico'] ?? '') !== '') ? (string) $dados['atestado_medico'] : null,
                'documento_classe_anterior' => (($dados['documento_classe_anterior'] ?? '') !== '') ? (string) $dados['documento_classe_anterior'] : null,
                'observacoes' => (($dados['observacoes'] ?? '') !== '') ? (string) $dados['observacoes'] : null
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarMatriculasTurmasProfessor(int $utilizadorId): array
    {
        $turmasIds = $this->obterTurmasProfessorIds($utilizadorId);
        $filtro = $this->criarParametrosListaInteiros($turmasIds, 'turma_prof_');
        if ($filtro['sql'] === '') {
            return [];
        }

        try {
            $sql = 'SELECT
                        m.id,
                        m.turma_id,
                        t.nome AS turma,
                        ua.nome AS aluno,
                        CONCAT(ua.nome, " - ", t.nome) AS referencia
                    FROM matriculas m
                    INNER JOIN turmas t ON t.id = m.turma_id
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    WHERE m.status = "activo"
                      AND m.turma_id IN (' . $filtro['sql'] . ')
                    ORDER BY t.nome, ua.nome';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($filtro['params']);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarAlunosTurmaProfessor(int $utilizadorId, int $turmaId, int $disciplinaId = 0): array
    {
        if ($turmaId <= 0 || !$this->professorPodeGerirTurma($utilizadorId, $turmaId, $disciplinaId)) {
            return [];
        }

        try {
            $temDisciplina = $this->colunaExiste('presencas', 'disciplina_id');
            $sql = 'SELECT
                        m.id AS matricula_id,
                        ua.nome AS aluno_nome,
                        ua.nome AS aluno,
                        COALESCE(SUM(CASE WHEN p.presente = 0 THEN 1 ELSE 0 END), 0) AS faltas
                    FROM matriculas m
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    LEFT JOIN presencas p ON p.matricula_id = m.id
                    WHERE m.status = "activo"
                      AND m.turma_id = :turma_id';
            $params = ['turma_id' => $turmaId];
            if ($temDisciplina && $disciplinaId > 0) {
                $sql .= ' AND (p.disciplina_id = :disciplina_id OR p.disciplina_id IS NULL)';
                $params['disciplina_id'] = $disciplinaId;
            }

            $sql .= '
                    GROUP BY m.id, ua.nome
                    ORDER BY ua.nome';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarNotasTurmaDetalhadas(int $utilizadorId, int $turmaId, int $disciplinaId, int $trimestre): array
    {
        if (
            $turmaId <= 0
            || $disciplinaId <= 0
            || $trimestre < 1
            || $trimestre > 3
            || !$this->professorPodeGerirTurma($utilizadorId, $turmaId, $disciplinaId)
        ) {
            return [];
        }

        $temTeste = $this->colunaExiste('notas', 'teste');
        $temTrabalho = $this->colunaExiste('notas', 'trabalho');
        $temParticipacao = $this->colunaExiste('notas', 'participacao');
        $temSituacao = $this->colunaExiste('notas', 'situacao');

        $testeSql = $temTeste ? 'n.teste' : 'NULL';
        $trabalhoSql = $temTrabalho ? 'n.trabalho' : 'NULL';
        $participacaoSql = $temParticipacao ? 'n.participacao' : 'NULL';
        $situacaoSql = $temSituacao ? 'n.situacao' : 'NULL';

        try {
            $sql = "SELECT
                        m.id AS matricula_id,
                        ua.nome AS aluno_nome,
                        ua.nome AS aluno,
                        {$testeSql} AS teste,
                        {$trabalhoSql} AS trabalho,
                        {$participacaoSql} AS participacao,
                        n.nota AS media,
                        {$situacaoSql} AS situacao
                    FROM matriculas m
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    LEFT JOIN notas n
                      ON n.matricula_id = m.id
                     AND n.disciplina_id = :disciplina_id
                     AND n.trimestre = :trimestre
                    WHERE m.status = 'activo'
                      AND m.turma_id = :turma_id
                    ORDER BY ua.nome";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'turma_id' => $turmaId,
                'disciplina_id' => $disciplinaId,
                'trimestre' => $trimestre
            ]);
            $linhas = $stmt->fetchAll() ?: [];

            foreach ($linhas as &$linha) {
                $media = isset($linha['media']) ? (float) $linha['media'] : null;
                if ($linha['teste'] === null && $media !== null) {
                    $linha['teste'] = $media;
                }
                if ($linha['trabalho'] === null && $media !== null) {
                    $linha['trabalho'] = $media;
                }
                if ($linha['participacao'] === null && $media !== null) {
                    $linha['participacao'] = $media;
                }
                if (($linha['situacao'] ?? null) === null && $media !== null) {
                    $linha['situacao'] = $this->calcularSituacao($media);
                }
            }
            unset($linha);

            return $linhas;
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function guardarNotasTurmaDetalhadas(int $utilizadorId, int $turmaId, int $disciplinaId, int $trimestre, array $linhas): bool
    {
        if (
            $turmaId <= 0
            || $disciplinaId <= 0
            || $trimestre < 1
            || $trimestre > 3
            || empty($linhas)
            || !$this->professorPodeGerirTurma($utilizadorId, $turmaId, $disciplinaId)
        ) {
            return false;
        }

        $matriculasPermitidas = [];
        foreach ($this->listarAlunosTurmaProfessor($utilizadorId, $turmaId, $disciplinaId) as $alunoTurma) {
            $matriculasPermitidas[(int) ($alunoTurma['matricula_id'] ?? 0)] = true;
        }
        if (empty($matriculasPermitidas)) {
            return false;
        }

        $temTeste = $this->colunaExiste('notas', 'teste');
        $temTrabalho = $this->colunaExiste('notas', 'trabalho');
        $temParticipacao = $this->colunaExiste('notas', 'participacao');
        $temSituacao = $this->colunaExiste('notas', 'situacao');

        try {
            $this->db->beginTransaction();

            $stmtExiste = $this->db->prepare(
                'SELECT id FROM notas WHERE matricula_id = :matricula_id AND disciplina_id = :disciplina_id AND trimestre = :trimestre LIMIT 1'
            );

            foreach ($linhas as $linha) {
                $matriculaId = (int) ($linha['matricula_id'] ?? 0);
                if (!isset($matriculasPermitidas[$matriculaId])) {
                    continue;
                }

                $teste = max(0, min(20, (float) ($linha['teste'] ?? 0)));
                $trabalho = max(0, min(20, (float) ($linha['trabalho'] ?? 0)));
                $participacao = max(0, min(20, (float) ($linha['participacao'] ?? 0)));
                $media = round(($teste + $trabalho + $participacao) / 3, 1);
                $situacao = $this->calcularSituacao($media);

                $stmtExiste->execute([
                    'matricula_id' => $matriculaId,
                    'disciplina_id' => $disciplinaId,
                    'trimestre' => $trimestre
                ]);
                $notaId = (int) $stmtExiste->fetchColumn();

                if ($notaId > 0) {
                    $campos = ['nota = :media', 'data_lancamento = NOW()'];
                    $params = [
                        'id' => $notaId,
                        'media' => $media
                    ];

                    if ($temTeste) {
                        $campos[] = 'teste = :teste';
                        $params['teste'] = $teste;
                    }
                    if ($temTrabalho) {
                        $campos[] = 'trabalho = :trabalho';
                        $params['trabalho'] = $trabalho;
                    }
                    if ($temParticipacao) {
                        $campos[] = 'participacao = :participacao';
                        $params['participacao'] = $participacao;
                    }
                    if ($temSituacao) {
                        $campos[] = 'situacao = :situacao';
                        $params['situacao'] = $situacao;
                    }

                    $sqlUpdate = 'UPDATE notas SET ' . implode(', ', $campos) . ' WHERE id = :id';
                    $stmtUpdate = $this->db->prepare($sqlUpdate);
                    $stmtUpdate->execute($params);
                    continue;
                }

                if ($temTeste || $temTrabalho || $temParticipacao || $temSituacao) {
                    $colunas = ['matricula_id', 'disciplina_id', 'trimestre', 'nota', 'data_lancamento'];
                    $valores = [':matricula_id', ':disciplina_id', ':trimestre', ':media', 'NOW()'];
                    $paramsInsert = [
                        'matricula_id' => $matriculaId,
                        'disciplina_id' => $disciplinaId,
                        'trimestre' => $trimestre,
                        'media' => $media
                    ];

                    if ($temTeste) {
                        $colunas[] = 'teste';
                        $valores[] = ':teste';
                        $paramsInsert['teste'] = $teste;
                    }
                    if ($temTrabalho) {
                        $colunas[] = 'trabalho';
                        $valores[] = ':trabalho';
                        $paramsInsert['trabalho'] = $trabalho;
                    }
                    if ($temParticipacao) {
                        $colunas[] = 'participacao';
                        $valores[] = ':participacao';
                        $paramsInsert['participacao'] = $participacao;
                    }
                    if ($temSituacao) {
                        $colunas[] = 'situacao';
                        $valores[] = ':situacao';
                        $paramsInsert['situacao'] = $situacao;
                    }

                    $sqlInsert = 'INSERT INTO notas (' . implode(', ', $colunas) . ') VALUES (' . implode(', ', $valores) . ')';
                    $stmtInsert = $this->db->prepare($sqlInsert);
                    $stmtInsert->execute($paramsInsert);
                } else {
                    $stmtInsert = $this->db->prepare(
                        'INSERT INTO notas (matricula_id, disciplina_id, trimestre, nota, data_lancamento)
                         VALUES (:matricula_id, :disciplina_id, :trimestre, :media, NOW())'
                    );
                    $stmtInsert->execute([
                        'matricula_id' => $matriculaId,
                        'disciplina_id' => $disciplinaId,
                        'trimestre' => $trimestre,
                        'media' => $media
                    ]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function lancarNotaProfessor(array $dados): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO notas (matricula_id, disciplina_id, trimestre, nota, data_lancamento)
                 VALUES (:matricula_id, :disciplina_id, :trimestre, :nota, NOW())'
            );
            return $stmt->execute([
                'matricula_id' => (int) $dados['matricula_id'],
                'disciplina_id' => (int) $dados['disciplina_id'],
                'trimestre' => (int) $dados['trimestre'],
                'nota' => (float) $dados['nota']
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function registarPresencasTurma(int $utilizadorId, int $turmaId, string $data, array $linhas, int $disciplinaId = 0): bool
    {
        if (
            $turmaId <= 0
            || $data === ''
            || empty($linhas)
            || !$this->professorPodeGerirTurma($utilizadorId, $turmaId, $disciplinaId)
        ) {
            return false;
        }

        $matriculasPermitidas = [];
        foreach ($this->listarAlunosTurmaProfessor($utilizadorId, $turmaId, $disciplinaId) as $alunoTurma) {
            $matriculasPermitidas[(int) ($alunoTurma['matricula_id'] ?? 0)] = true;
        }
        if (empty($matriculasPermitidas)) {
            return false;
        }

        $temJustificativa = $this->colunaExiste('presencas', 'justificativa');
        $temDisciplina = $this->colunaExiste('presencas', 'disciplina_id');
        $temProfessor = $this->colunaExiste('presencas', 'professor_id');
        $professorId = $this->obterProfessorIdPorUtilizador($utilizadorId);

        try {
            $this->db->beginTransaction();
            if ($temDisciplina && $disciplinaId > 0) {
                $stmtExiste = $this->db->prepare(
                    'SELECT id
                     FROM presencas
                     WHERE matricula_id = :matricula_id
                       AND data = :data
                       AND disciplina_id = :disciplina_id
                     LIMIT 1'
                );
            } else {
                $stmtExiste = $this->db->prepare(
                    'SELECT id FROM presencas WHERE matricula_id = :matricula_id AND data = :data LIMIT 1'
                );
            }

            foreach ($linhas as $linha) {
                $matriculaId = (int) ($linha['matricula_id'] ?? 0);
                if (!isset($matriculasPermitidas[$matriculaId])) {
                    continue;
                }

                $presente = ((int) ($linha['presente'] ?? 0)) === 1 ? 1 : 0;
                $justificativa = trim((string) ($linha['justificativa'] ?? ''));

                $stmtExiste->execute([
                    'matricula_id' => $matriculaId,
                    'data' => $data,
                    ...(($temDisciplina && $disciplinaId > 0) ? ['disciplina_id' => $disciplinaId] : [])
                ]);
                $presencaId = (int) $stmtExiste->fetchColumn();

                if ($presencaId > 0) {
                    $campos = ['presente = :presente'];
                    $paramsUpdate = [
                        'presente' => $presente,
                        'id' => $presencaId
                    ];
                    if ($temJustificativa) {
                        $campos[] = 'justificativa = :justificativa';
                        $campos[] = 'justificativa_status = NULL';
                        $campos[] = 'justificativa_analisada_por = NULL';
                        $campos[] = 'justificativa_analisada_em = NULL';
                        $paramsUpdate['justificativa'] = $justificativa !== '' ? $justificativa : null;
                    }
                    if ($temProfessor && $professorId > 0) {
                        $campos[] = 'professor_id = :professor_id';
                        $paramsUpdate['professor_id'] = $professorId;
                    }
                    if ($temDisciplina && $disciplinaId > 0) {
                        $campos[] = 'disciplina_id = :disciplina_id';
                        $paramsUpdate['disciplina_id'] = $disciplinaId;
                    }

                    $stmtUpdate = $this->db->prepare('UPDATE presencas SET ' . implode(', ', $campos) . ' WHERE id = :id');
                    $stmtUpdate->execute($paramsUpdate);
                    continue;
                }

                $colunas = ['matricula_id', 'data', 'presente'];
                $valores = [':matricula_id', ':data', ':presente'];
                $paramsInsert = [
                    'matricula_id' => $matriculaId,
                    'data' => $data,
                    'presente' => $presente
                ];

                if ($temJustificativa) {
                    $colunas[] = 'justificativa';
                    $valores[] = ':justificativa';
                    $paramsInsert['justificativa'] = $justificativa !== '' ? $justificativa : null;
                }
                if ($temProfessor && $professorId > 0) {
                    $colunas[] = 'professor_id';
                    $valores[] = ':professor_id';
                    $paramsInsert['professor_id'] = $professorId;
                }
                if ($temDisciplina && $disciplinaId > 0) {
                    $colunas[] = 'disciplina_id';
                    $valores[] = ':disciplina_id';
                    $paramsInsert['disciplina_id'] = $disciplinaId;
                }

                $stmtInsert = $this->db->prepare(
                    'INSERT INTO presencas (' . implode(', ', $colunas) . ')
                     VALUES (' . implode(', ', $valores) . ')'
                );
                $stmtInsert->execute($paramsInsert);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function listarPresencasTurmaSemana(int $utilizadorId, int $turmaId, string $dataBase, int $disciplinaId = 0): array
    {
        if ($turmaId <= 0 || !$this->professorPodeGerirTurma($utilizadorId, $turmaId, $disciplinaId)) {
            return [];
        }

        $timestamp = strtotime($dataBase);
        if ($timestamp === false) {
            $timestamp = time();
        }

        $inicioSemana = date('Y-m-d', strtotime('monday this week', $timestamp));
        $fimSemana = date('Y-m-d', strtotime('sunday this week', $timestamp));
        $temJustificativa = $this->colunaExiste('presencas', 'justificativa');
        $temDisciplina = $this->colunaExiste('presencas', 'disciplina_id');
        $justificativaSql = $temJustificativa ? 'p.justificativa' : 'NULL';
        $disciplinaSql = $temDisciplina ? 'd.nome' : 'NULL';

        try {
            $sql = "SELECT
                        p.data,
                        ua.nome AS aluno_nome,
                        ua.nome AS aluno,
                        p.presente,
                        {$justificativaSql} AS justificativa,
                        {$disciplinaSql} AS disciplina
                    FROM matriculas m
                    INNER JOIN turmas t ON t.id = m.turma_id
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    INNER JOIN presencas p ON p.matricula_id = m.id
                    LEFT JOIN disciplinas d ON d.id = p.disciplina_id
                    WHERE m.status = 'activo'
                      AND t.id = :turma_id
                      AND p.data BETWEEN :inicio AND :fim
                    ORDER BY p.data DESC, ua.nome";
            $params = [
                'turma_id' => $turmaId,
                'inicio' => $inicioSemana,
                'fim' => $fimSemana
            ];

            if ($temDisciplina && $disciplinaId > 0) {
                $sql = str_replace('ORDER BY p.data DESC, ua.nome', 'AND p.disciplina_id = :disciplina_id ORDER BY p.data DESC, ua.nome', $sql);
                $params['disciplina_id'] = $disciplinaId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function registrarPresenca(array $dados): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO presencas (matricula_id, data, presente)
                 VALUES (:matricula_id, :data, :presente)'
            );
            return $stmt->execute([
                'matricula_id' => (int) $dados['matricula_id'],
                'data' => $dados['data'],
                'presente' => (int) $dados['presente']
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function enviarMaterialEstudo(array $dados): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO materiais_estudo (professor_id, turma_id, titulo, descricao, ficheiro_path, criado_em)
                 VALUES (:professor_id, :turma_id, :titulo, :descricao, :ficheiro_path, NOW())'
            );
            return $stmt->execute([
                'professor_id' => (int) $dados['professor_id'],
                'turma_id' => (int) $dados['turma_id'],
                'titulo' => $dados['titulo'],
                'descricao' => $dados['descricao'] !== '' ? $dados['descricao'] : null,
                'ficheiro_path' => $dados['ficheiro_path'] !== '' ? $dados['ficheiro_path'] : null
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarMateriaisProfessor(int $utilizadorId, int $limite = 20): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT
                        me.id,
                        me.titulo,
                        me.descricao,
                        me.ficheiro_path,
                        me.criado_em,
                        t.nome AS turma
                    FROM materiais_estudo me
                    INNER JOIN professores p ON p.id = me.professor_id
                    INNER JOIN turmas t ON t.id = me.turma_id
                    WHERE p.utilizador_id = :utilizador_id
                    ORDER BY me.criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarEncarregadosTurmasProfessor(int $utilizadorId, int $limite = 40): array
    {
        $limite = max(1, $limite);
        $turmasIds = $this->obterTurmasProfessorIds($utilizadorId);
        $filtro = $this->criarParametrosListaInteiros($turmasIds, 'enc_turma_');
        if ($filtro['sql'] === '') {
            return [];
        }

        try {
            $sql = "SELECT DISTINCT
                        ue.id AS utilizador_id,
                        ue.nome AS encarregado,
                        ua.nome AS aluno
                    FROM matriculas m
                    INNER JOIN turmas t ON t.id = m.turma_id
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    INNER JOIN encarregado_aluno ea ON ea.aluno_id = a.id
                    INNER JOIN encarregados e ON e.id = ea.encarregado_id
                    INNER JOIN utilizadores ue ON ue.id = e.utilizador_id
                    WHERE m.status = 'activo'
                      AND m.turma_id IN ({$filtro['sql']})
                    ORDER BY ue.nome
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($filtro['params']);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function obterResumoSalarioProfessor(int $utilizadorId): array
    {
        try {
            $turmas = count($this->obterTurmasProfessorIds($utilizadorId));
            $disciplinas = count($this->listarDisciplinasProfessor($utilizadorId));

            $base = 140000;
            $bruto = $base + ($turmas * 5000) + ($disciplinas * 2500);
            $pendente = (int) floor($bruto * 0.15);

            return [
                'pago' => max(0, $bruto - $pendente),
                'pendente' => max(0, $pendente)
            ];
        } catch (Throwable $erro) {
            return ['pago' => 0, 'pendente' => 0];
        }
    }

    public function enviarMensagemInterna(int $remetenteId, ?int $destinatarioId, string $perfilDestino, string $assunto, string $mensagem): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO mensagens_internas (remetente_id, destinatario_id, perfil_destino, assunto, mensagem, status, criado_em)
                 VALUES (:remetente_id, :destinatario_id, :perfil_destino, :assunto, :mensagem, "nao_lida", NOW())'
            );
            return $stmt->execute([
                'remetente_id' => $remetenteId,
                'destinatario_id' => $destinatarioId,
                'perfil_destino' => $perfilDestino,
                'assunto' => $assunto,
                'mensagem' => $mensagem
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarUtilizadoresComunicacaoInterna(int $utilizadorId): array
    {
        if ($utilizadorId <= 0) {
            return [];
        }

        $perfisPermitidos = $this->perfisComunicacaoInterna();
        $placeholders = [];
        $params = ['utilizador_id' => $utilizadorId];
        foreach ($perfisPermitidos as $indice => $perfil) {
            $chave = 'perfil_' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $perfil;
        }

        try {
            $sql = 'SELECT id, nome, email, perfil
                    FROM utilizadores
                    WHERE ativo = 1
                      AND id <> :utilizador_id
                      AND perfil IN (' . implode(', ', $placeholders) . ')
                    ORDER BY nome';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function contarMensagensInternasPainelNaoLidas(int $utilizadorId): int
    {
        if ($utilizadorId <= 0) {
            return 0;
        }

        $perfisPermitidos = $this->perfisComunicacaoInterna();
        $placeholders = [];
        $params = ['utilizador_id' => $utilizadorId];
        foreach ($perfisPermitidos as $indice => $perfil) {
            $chave = 'perfil_' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $perfil;
        }

        try {
            $sql = 'SELECT COUNT(*)
                    FROM mensagens_internas m
                    INNER JOIN utilizadores ur ON ur.id = m.remetente_id
                    INNER JOIN utilizadores ud ON ud.id = m.destinatario_id
                    WHERE m.destinatario_id = :utilizador_id
                      AND m.status = "nao_lida"
                      AND ur.perfil IN (' . implode(', ', $placeholders) . ')
                      AND ud.perfil IN (' . implode(', ', $placeholders) . ')';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $erro) {
            return 0;
        }
    }

    public function listarMensagensInternasPainel(int $utilizadorId, int $limite = 120, string $estadoFiltro = 'todas'): array
    {
        if ($utilizadorId <= 0) {
            return [];
        }

        $limite = max(1, $limite);
        $perfisPermitidos = $this->perfisComunicacaoInterna();
        $placeholders = [];
        $params = ['utilizador_id' => $utilizadorId];
        foreach ($perfisPermitidos as $indice => $perfil) {
            $chave = 'perfil_' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $perfil;
        }

        $estadoFiltro = strtolower(trim($estadoFiltro));
        $filtroEstadoSql = '';
        $params['estado_filtro'] = $estadoFiltro;
        if (in_array($estadoFiltro, ['nao_lida', 'lida'], true)) {
            $filtroEstadoSql = ' AND m.status = :estado_filtro';
        } else {
            $estadoFiltro = 'todas';
        }

        try {
            $sql = 'SELECT
                        m.id,
                        m.remetente_id,
                        m.destinatario_id,
                        m.assunto,
                        m.mensagem,
                        m.status,
                        m.criado_em,
                        m.resposta_a_id,
                        ur.nome AS remetente_nome,
                        ur.perfil AS remetente_perfil,
                        ud.nome AS destinatario_nome,
                        ud.perfil AS destinatario_perfil,
                        mr.assunto AS resposta_assunto
                    FROM mensagens_internas m
                    INNER JOIN utilizadores ur ON ur.id = m.remetente_id
                    INNER JOIN utilizadores ud ON ud.id = m.destinatario_id
                    LEFT JOIN mensagens_internas mr ON mr.id = m.resposta_a_id
                    WHERE m.destinatario_id IS NOT NULL
                      AND (m.destinatario_id = :utilizador_id OR m.remetente_id = :utilizador_id)
                      AND ur.perfil IN (' . implode(', ', $placeholders) . ')
                      AND ud.perfil IN (' . implode(', ', $placeholders) . ')
                      ' . $filtroEstadoSql . '
                    ORDER BY m.criado_em DESC
                    LIMIT ' . $limite;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $mensagens = $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }

        if (empty($mensagens)) {
            return [];
        }

        $ids = array_values(array_filter(array_map(static fn(array $linha): int => (int) ($linha['id'] ?? 0), $mensagens), static fn(int $id): bool => $id > 0));
        $anexosPorMensagem = $this->listarAnexosMensagensInternas($ids);

        foreach ($mensagens as &$mensagem) {
            $idMensagem = (int) ($mensagem['id'] ?? 0);
            $mensagem['anexos'] = $anexosPorMensagem[$idMensagem] ?? [];
        }
        unset($mensagem);

        return $mensagens;
    }

    public function marcarMensagensInternasPainelComoLidas(int $utilizadorId, array $mensagensIds = []): bool
    {
        if ($utilizadorId <= 0) {
            return false;
        }

        $ids = array_values(array_filter(array_map('intval', $mensagensIds), static fn(int $id): bool => $id > 0));
        $params = ['utilizador_id' => $utilizadorId];

        $filtroIds = '';
        if (!empty($ids)) {
            $placeholders = [];
            foreach ($ids as $indice => $idMensagem) {
                $chave = 'm' . $indice;
                $placeholders[] = ':' . $chave;
                $params[$chave] = $idMensagem;
            }
            $filtroIds = ' AND id IN (' . implode(', ', $placeholders) . ')';
        }

        try {
            $sql = 'UPDATE mensagens_internas
                    SET status = "lida"
                    WHERE destinatario_id = :utilizador_id
                      AND status = "nao_lida"' . $filtroIds;
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function enviarMensagemInternaPainel(array $dados): array
    {
        $remetenteId = (int) ($dados['remetente_id'] ?? 0);
        $destinatarioId = (int) ($dados['destinatario_id'] ?? 0);
        $assunto = trim((string) ($dados['assunto'] ?? ''));
        $mensagem = trim((string) ($dados['mensagem'] ?? ''));
        $respostaAId = (int) ($dados['resposta_a_id'] ?? 0);
        $anexos = (array) ($dados['anexos'] ?? []);

        if ($remetenteId <= 0 || $destinatarioId <= 0) {
            return ['sucesso' => false, 'mensagem' => 'Remetente ou destinatario invalido.', 'mensagem_id' => 0];
        }

        if ($assunto === '') {
            return ['sucesso' => false, 'mensagem' => 'Informe o assunto da mensagem.', 'mensagem_id' => 0];
        }

        if ($mensagem === '' && empty($anexos)) {
            return ['sucesso' => false, 'mensagem' => 'Escreva a mensagem ou anexe pelo menos um ficheiro.', 'mensagem_id' => 0];
        }

        $remetente = $this->obterUtilizadorParaComunicacaoInterna($remetenteId);
        $destinatario = $this->obterUtilizadorParaComunicacaoInterna($destinatarioId);
        if ($remetente === null || $destinatario === null) {
            return ['sucesso' => false, 'mensagem' => 'Comunicacao permitida apenas entre paineis internos autorizados.', 'mensagem_id' => 0];
        }

        if ($respostaAId > 0) {
            $mensagemReferencia = $this->obterMensagemInternaPainelPorId($respostaAId);
            if ($mensagemReferencia === null) {
                return ['sucesso' => false, 'mensagem' => 'Mensagem de resposta nao encontrada.', 'mensagem_id' => 0];
            }

            $participantes = [
                (int) ($mensagemReferencia['remetente_id'] ?? 0),
                (int) ($mensagemReferencia['destinatario_id'] ?? 0)
            ];
            if (!in_array($remetenteId, $participantes, true) || !in_array($destinatarioId, $participantes, true)) {
                return ['sucesso' => false, 'mensagem' => 'Resposta invalida para os participantes selecionados.', 'mensagem_id' => 0];
            }
        } else {
            $respostaAId = null;
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                'INSERT INTO mensagens_internas
                 (remetente_id, destinatario_id, resposta_a_id, perfil_destino, assunto, mensagem, status, criado_em)
                 VALUES (:remetente_id, :destinatario_id, :resposta_a_id, :perfil_destino, :assunto, :mensagem, "nao_lida", NOW())'
            );
            $stmt->execute([
                'remetente_id' => $remetenteId,
                'destinatario_id' => $destinatarioId,
                'resposta_a_id' => $respostaAId,
                'perfil_destino' => (string) ($destinatario['perfil'] ?? 'todos'),
                'assunto' => mb_substr($assunto, 0, 180),
                'mensagem' => $mensagem !== '' ? $mensagem : '[Mensagem com anexo]'
            ]);

            $mensagemId = (int) $this->db->lastInsertId();
            if ($mensagemId <= 0) {
                throw new RuntimeException('Falha ao registar mensagem interna.');
            }

            if (!empty($anexos)) {
                $stmtAnexo = $this->db->prepare(
                    'INSERT INTO mensagens_internas_anexos
                     (mensagem_id, caminho_ficheiro, nome_original, tipo_mime, tamanho_bytes, criado_em)
                     VALUES (:mensagem_id, :caminho_ficheiro, :nome_original, :tipo_mime, :tamanho_bytes, NOW())'
                );

                foreach ($anexos as $anexo) {
                    $caminho = trim((string) ($anexo['caminho_ficheiro'] ?? ''));
                    if ($caminho === '') {
                        continue;
                    }

                    $stmtAnexo->execute([
                        'mensagem_id' => $mensagemId,
                        'caminho_ficheiro' => $caminho,
                        'nome_original' => trim((string) ($anexo['nome_original'] ?? 'ficheiro')),
                        'tipo_mime' => trim((string) ($anexo['tipo_mime'] ?? 'application/octet-stream')),
                        'tamanho_bytes' => max(0, (int) ($anexo['tamanho_bytes'] ?? 0))
                    ]);
                }
            }

            $this->db->commit();
            return ['sucesso' => true, 'mensagem' => 'Mensagem interna enviada com sucesso.', 'mensagem_id' => $mensagemId];
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel enviar a mensagem interna.', 'mensagem_id' => 0];
        }
    }

    public function enviarComunicadoTurma(int $remetenteId, int $turmaId, string $assunto, string $mensagem): bool
    {
        if ($turmaId <= 0 || $mensagem === '') {
            return false;
        }

        try {
            $this->db->beginTransaction();
            $stmtDest = $this->db->prepare(
                'SELECT DISTINCT ua.id AS utilizador_id
                 FROM matriculas m
                 INNER JOIN alunos a ON a.id = m.aluno_id
                 INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                 WHERE m.turma_id = :turma_id
                   AND m.status = "activo"'
            );
            $stmtDest->execute(['turma_id' => $turmaId]);
            $destinatarios = $stmtDest->fetchAll() ?: [];

            if (empty($destinatarios)) {
                $this->db->rollBack();
                return false;
            }

            $stmtMensagem = $this->db->prepare(
                'INSERT INTO mensagens_internas (remetente_id, destinatario_id, perfil_destino, assunto, mensagem, status, criado_em)
                 VALUES (:remetente_id, :destinatario_id, "aluno", :assunto, :mensagem, "nao_lida", NOW())'
            );

            foreach ($destinatarios as $destinatario) {
                $stmtMensagem->execute([
                    'remetente_id' => $remetenteId,
                    'destinatario_id' => (int) ($destinatario['utilizador_id'] ?? 0),
                    'assunto' => $assunto,
                    'mensagem' => $mensagem
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function notificarMaterialTurma(int $remetenteId, int $turmaId, string $tituloMaterial): bool
    {
        return $this->enviarComunicadoTurma(
            $remetenteId,
            $turmaId,
            'Novo material de estudo',
            'Foi publicado novo material de estudo: ' . $tituloMaterial
        );
    }

    public function obterEmailUtilizador(int $utilizadorId): ?string
    {
        if ($utilizadorId <= 0) {
            return null;
        }

        try {
            $stmt = $this->db->prepare('SELECT email FROM utilizadores WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $utilizadorId]);
            $email = (string) $stmt->fetchColumn();
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        } catch (Throwable $erro) {
            return null;
        }
    }

    public function obterUtilizadorEncarregadoPorComprovativo(int $comprovativoId): int
    {
        if ($comprovativoId <= 0) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT ue.id
                 FROM comprovativos_pagamento cp
                 INNER JOIN encarregados e ON e.id = cp.encarregado_id
                 INNER JOIN utilizadores ue ON ue.id = e.utilizador_id
                 WHERE cp.id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $comprovativoId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $erro) {
            return 0;
        }
    }

    public function obterAlunoIdPorUtilizador(int $utilizadorId): int
    {
        try {
            $stmt = $this->db->prepare('SELECT id FROM alunos WHERE utilizador_id = :id LIMIT 1');
            $stmt->execute(['id' => $utilizadorId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $erro) {
            return 0;
        }
    }

    public function obterEncarregadoIdPorUtilizador(int $utilizadorId): int
    {
        try {
            $stmt = $this->db->prepare('SELECT id FROM encarregados WHERE utilizador_id = :id LIMIT 1');
            $stmt->execute(['id' => $utilizadorId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $erro) {
            return 0;
        }
    }

    public function obterProfessorIdPorUtilizador(int $utilizadorId): int
    {
        try {
            $stmt = $this->db->prepare('SELECT id FROM professores WHERE utilizador_id = :id LIMIT 1');
            $stmt->execute(['id' => $utilizadorId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $erro) {
            return 0;
        }
    }

    public function listarUtilizadoresPorPerfil(string $perfil, int $limite = 50): array
    {
        $limite = max(1, $limite);
        $perfisPermitidos = ['aluno', 'encarregado', 'professor', 'secretaria', 'direcao_pedagogica', 'direcao_geral', 'rh'];
        if (!in_array($perfil, $perfisPermitidos, true)) {
            return [];
        }

        try {
            $sql = "SELECT id, nome, email
                    FROM utilizadores
                    WHERE perfil = :perfil
                      AND ativo = 1
                    ORDER BY nome
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['perfil' => $perfil]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarPagamentosAluno(int $utilizadorId): array
    {
        try {
            $sql = "SELECT p.id, p.descricao, p.valor, p.status, p.data_vencimento, p.data_pagamento
                    FROM alunos a
                    INNER JOIN matriculas m ON m.aluno_id = a.id AND m.status = 'activo'
                    INNER JOIN pagamentos p ON p.matricula_id = m.id
                    WHERE a.utilizador_id = :utilizador_id
                    ORDER BY p.data_vencimento DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarFinanceiroSecretaria(array $filtros = [], int $limite = 300): array
    {
        $limite = max(1, $limite);
        $params = [];
        $where = [];

        $classe = trim((string) ($filtros['classe'] ?? ''));
        if ($classe !== '') {
            $where[] = 't.nome LIKE :classe';
            $params['classe'] = $classe . '%';
        }

        $turmaId = (int) ($filtros['turma_id'] ?? 0);
        if ($turmaId > 0) {
            $where[] = 't.id = :turma_id';
            $params['turma_id'] = $turmaId;
        }

        $periodo = trim((string) ($filtros['periodo'] ?? ''));
        if (preg_match('/^\\d{4}-\\d{2}$/', $periodo) === 1) {
            $where[] = 'DATE_FORMAT(p.data_vencimento, "%Y-%m") = :periodo';
            $params['periodo'] = $periodo;
        }

        $matriculaId = (int) ($filtros['matricula_id'] ?? 0);
        if ($matriculaId > 0) {
            $where[] = 'm.id = :matricula_id';
            $params['matricula_id'] = $matriculaId;
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        try {
            $sql = "SELECT
                        p.id,
                        p.matricula_id,
                        p.descricao,
                        p.valor,
                        p.status,
                        p.data_vencimento,
                        p.data_pagamento,
                        a.id AS aluno_id,
                        u.nome AS aluno,
                        m.id AS numero_matricula,
                        t.id AS turma_id,
                        t.nome AS turma,
                        ue.nome AS encarregado
                    FROM pagamentos p
                    INNER JOIN matriculas m ON m.id = p.matricula_id
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores u ON u.id = a.utilizador_id
                    LEFT JOIN turmas t ON t.id = m.turma_id
                    LEFT JOIN encarregado_aluno ea ON ea.aluno_id = a.id
                    LEFT JOIN encarregados e ON e.id = ea.encarregado_id
                    LEFT JOIN utilizadores ue ON ue.id = e.utilizador_id
                    {$whereSql}
                    ORDER BY p.data_vencimento DESC, p.id DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function obterResumoFinanceiroAlunoSecretaria(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return ['pago' => [], 'pendente' => []];
        }

        $resultado = ['pago' => [], 'pendente' => []];
        try {
            $sql = "SELECT
                        p.descricao,
                        p.valor,
                        p.status,
                        p.data_vencimento,
                        p.data_pagamento
                    FROM pagamentos p
                    INNER JOIN matriculas m ON m.id = p.matricula_id
                    WHERE m.aluno_id = :aluno_id
                    ORDER BY p.data_vencimento ASC, p.id ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['aluno_id' => $alunoId]);
            foreach (($stmt->fetchAll() ?: []) as $linha) {
                $chave = in_array((string) ($linha['status'] ?? ''), ['pago'], true) ? 'pago' : 'pendente';
                $resultado[$chave][] = $linha;
            }
        } catch (Throwable $erro) {
        }

        return $resultado;
    }

    public function enviarAvisoPagamentoEncarregado(int $alunoId, string $mensagem, int $remetenteId): bool
    {
        if ($alunoId <= 0 || trim($mensagem) === '') {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT ue.id
                 FROM encarregado_aluno ea
                 INNER JOIN encarregados e ON e.id = ea.encarregado_id
                 INNER JOIN utilizadores ue ON ue.id = e.utilizador_id
                 WHERE ea.aluno_id = :aluno_id
                 LIMIT 1'
            );
            $stmt->execute(['aluno_id' => $alunoId]);
            $destinatarioId = (int) $stmt->fetchColumn();
            if ($destinatarioId <= 0) {
                return false;
            }

            return $this->enviarMensagemInterna(
                $remetenteId,
                $destinatarioId,
                'encarregado',
                'Aviso de pagamento pendente',
                $mensagem
            );
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function marcarPagamentoComoPagoSecretaria(int $pagamentoId, int $utilizadorId): bool
    {
        if ($pagamentoId <= 0 || $utilizadorId <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                'SELECT
                    p.id,
                    p.descricao,
                    p.valor,
                    m.aluno_id,
                    u.nome AS aluno
                 FROM pagamentos p
                 INNER JOIN matriculas m ON m.id = p.matricula_id
                 INNER JOIN alunos a ON a.id = m.aluno_id
                 INNER JOIN utilizadores u ON u.id = a.utilizador_id
                 WHERE p.id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $pagamentoId]);
            $dados = $stmt->fetch();
            if (!is_array($dados)) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return false;
            }

            $stmtUpdate = $this->db->prepare(
                'UPDATE pagamentos
                 SET status = "pago",
                     data_pagamento = CURDATE()
                 WHERE id = :id'
            );
            $stmtUpdate->execute(['id' => $pagamentoId]);

            $mensagemAluno = 'Pagamento confirmado: ' . (string) ($dados['descricao'] ?? 'mensalidade') . '.';
            $this->enviarMensagemInterna(
                $utilizadorId,
                (int) $this->obterUtilizadorAlunoPorAlunoId((int) ($dados['aluno_id'] ?? 0)),
                'aluno',
                'Pagamento confirmado',
                $mensagemAluno
            );

            $this->enviarAvisoPagamentoEncarregado(
                (int) ($dados['aluno_id'] ?? 0),
                'O pagamento de ' . (string) ($dados['descricao'] ?? 'mensalidade') . ' foi confirmado pela secretaria.',
                $utilizadorId
            );

            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function criarComprovativoPagamento(array $dados): array
    {
        try {
            $codigo = $this->gerarCodigoReferencia();
            $estado = trim((string) ($dados['estado'] ?? 'pendente'));
            if (!in_array($estado, ['pendente', 'aprovado', 'rejeitado'], true)) {
                $estado = 'pendente';
            }

            $temMetodo = $this->colunaExiste('comprovativos_pagamento', 'metodo_pagamento');
            $temRecibo = $this->colunaExiste('comprovativos_pagamento', 'recibo_pdf');

            $colunas = ['pagamento_id', 'aluno_id', 'encarregado_id', 'mes_referencia', 'valor', 'codigo_referencia', 'comprovativo_path', 'estado', 'criado_em'];
            $valores = [':pagamento_id', ':aluno_id', ':encarregado_id', ':mes_referencia', ':valor', ':codigo_referencia', ':comprovativo_path', ':estado', 'NOW()'];
            $params = [
                'pagamento_id' => (int) $dados['pagamento_id'] > 0 ? (int) $dados['pagamento_id'] : null,
                'aluno_id' => (int) $dados['aluno_id'],
                'encarregado_id' => (int) $dados['encarregado_id'],
                'mes_referencia' => $dados['mes_referencia'],
                'valor' => (float) $dados['valor'],
                'codigo_referencia' => $codigo,
                'comprovativo_path' => (($dados['comprovativo_path'] ?? '') !== '') ? (string) $dados['comprovativo_path'] : null,
                'estado' => $estado
            ];

            if ($temMetodo) {
                $colunas[] = 'metodo_pagamento';
                $valores[] = ':metodo_pagamento';
                $params['metodo_pagamento'] = trim((string) ($dados['metodo_pagamento'] ?? 'referencia'));
            }

            if ($temRecibo) {
                $colunas[] = 'recibo_pdf';
                $valores[] = ':recibo_pdf';
                $params['recibo_pdf'] = (($dados['recibo_pdf'] ?? '') !== '') ? (string) $dados['recibo_pdf'] : null;
            }

            $sql = 'INSERT INTO comprovativos_pagamento (' . implode(', ', $colunas) . ') VALUES (' . implode(', ', $valores) . ')';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return ['sucesso' => true, 'codigo' => $codigo, 'id' => (int) $this->db->lastInsertId()];
        } catch (Throwable $erro) {
            return ['sucesso' => false, 'codigo' => null, 'id' => 0];
        }
    }

    public function tutorialJaFoiVisto(int $utilizadorId): bool
    {
        if ($utilizadorId <= 0) {
            return true;
        }

        try {
            $stmt = $this->db->prepare('SELECT tutorial_visto FROM utilizadores WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $utilizadorId]);
            return (int) $stmt->fetchColumn() === 1;
        } catch (Throwable $erro) {
            return true;
        }
    }

    public function marcarTutorialComoVisto(int $utilizadorId): bool
    {
        if ($utilizadorId <= 0) {
            return false;
        }

        try {
            $stmt = $this->db->prepare('UPDATE utilizadores SET tutorial_visto = 1 WHERE id = :id');
            return $stmt->execute(['id' => $utilizadorId]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function criarPagamentoSimulado(array $dados): array
    {
        return $this->criarComprovativoPagamento($dados);
    }

    public function concluirPagamentoSimulado(array $dados): array
    {
        $encarregadoId = (int) ($dados['encarregado_id'] ?? 0);
        $alunos = array_values(array_filter(array_map('intval', (array) ($dados['alunos'] ?? [])), static fn(int $id): bool => $id > 0));
        $valorTotal = max(0, (float) ($dados['valor_total'] ?? 0));
        $mesReferencia = trim((string) ($dados['mes_referencia'] ?? date('Y-m')));
        $tipoPagamento = trim((string) ($dados['tipo_pagamento'] ?? 'mensalidade'));
        $metodo = trim((string) ($dados['metodo_pagamento'] ?? 'referencia'));
        $atividadeId = (int) ($dados['atividade_id'] ?? 0);
        $reciboPdf = trim((string) ($dados['recibo_pdf'] ?? ''));
        $comprovativoPath = trim((string) ($dados['comprovativo_path'] ?? ''));
        $observacao = trim((string) ($dados['observacao'] ?? ''));

        if ($encarregadoId <= 0 || empty($alunos) || $valorTotal <= 0) {
            return ['sucesso' => false, 'mensagem' => 'Dados invÃ¡lidos para concluir o pagamento.', 'codigos' => []];
        }

        if (!in_array($tipoPagamento, ['mensalidade', 'atividade'], true)) {
            $tipoPagamento = 'mensalidade';
        }

        if (!in_array($metodo, ['referencia', 'autorizacao'], true)) {
            $metodo = 'referencia';
        }

        if ($tipoPagamento === 'atividade' && $atividadeId <= 0) {
            return ['sucesso' => false, 'mensagem' => 'Selecione uma atividade vÃ¡lida para pagamento.', 'codigos' => []];
        }

        $valorPorAluno = round($valorTotal / max(1, count($alunos)), 2);
        $codigos = [];
        $nomesAlunos = [];

        try {
            $this->db->beginTransaction();

            foreach ($alunos as $alunoId) {
                $pagamentoId = null;
                $mesLinha = $mesReferencia;

                if ($tipoPagamento === 'mensalidade') {
                    $matriculaId = $this->obterMatriculaAtivaPorAluno($alunoId);
                    if ($matriculaId <= 0) {
                        continue;
                    }

                    $descricaoPagamento = 'Propina ' . $this->normalizarMesReferencia($mesReferencia);
                    $stmtPagamento = $this->db->prepare(
                        'SELECT id
                         FROM pagamentos
                         WHERE matricula_id = :matricula_id
                           AND descricao = :descricao
                         LIMIT 1'
                    );
                    $stmtPagamento->execute([
                        'matricula_id' => $matriculaId,
                        'descricao' => $descricaoPagamento
                    ]);
                    $pagamentoId = (int) $stmtPagamento->fetchColumn();

                    if ($pagamentoId > 0) {
                        $stmtUpdate = $this->db->prepare(
                            'UPDATE pagamentos
                             SET valor = :valor,
                                 status = "pago",
                                 data_pagamento = CURDATE()
                             WHERE id = :id'
                        );
                        $stmtUpdate->execute([
                            'valor' => $valorPorAluno,
                            'id' => $pagamentoId
                        ]);
                    } else {
                        $stmtInsert = $this->db->prepare(
                            'INSERT INTO pagamentos
                             (matricula_id, descricao, valor, data_vencimento, data_pagamento, status)
                             VALUES (:matricula_id, :descricao, :valor, CURDATE(), CURDATE(), "pago")'
                        );
                        $stmtInsert->execute([
                            'matricula_id' => $matriculaId,
                            'descricao' => $descricaoPagamento,
                            'valor' => $valorPorAluno
                        ]);
                        $pagamentoId = (int) $this->db->lastInsertId();
                    }
                } else {
                    $stmtAtividade = $this->db->prepare(
                        'SELECT id
                         FROM pagamentos_atividades
                         WHERE atividade_id = :atividade_id
                           AND aluno_id = :aluno_id
                           AND encarregado_id = :encarregado_id
                         LIMIT 1'
                    );
                    $stmtAtividade->execute([
                        'atividade_id' => $atividadeId,
                        'aluno_id' => $alunoId,
                        'encarregado_id' => $encarregadoId
                    ]);
                    $idPagamentoAtividade = (int) $stmtAtividade->fetchColumn();

                    if ($idPagamentoAtividade > 0) {
                        $stmtUpdateAtividade = $this->db->prepare(
                            'UPDATE pagamentos_atividades
                             SET valor = :valor,
                                 estado = "pago"
                             WHERE id = :id'
                        );
                        $stmtUpdateAtividade->execute([
                            'valor' => $valorPorAluno,
                            'id' => $idPagamentoAtividade
                        ]);
                    } else {
                        $stmtInsertAtividade = $this->db->prepare(
                            'INSERT INTO pagamentos_atividades
                             (atividade_id, aluno_id, encarregado_id, valor, estado, criado_em)
                             VALUES (:atividade_id, :aluno_id, :encarregado_id, :valor, "pago", NOW())'
                        );
                        $stmtInsertAtividade->execute([
                            'atividade_id' => $atividadeId,
                            'aluno_id' => $alunoId,
                            'encarregado_id' => $encarregadoId,
                            'valor' => $valorPorAluno
                        ]);
                    }

                    $mesLinha = 'ATV-' . $atividadeId . '-' . date('Ym');
                }

                $resultadoComprovativo = $this->criarComprovativoPagamento([
                    'pagamento_id' => $pagamentoId,
                    'aluno_id' => $alunoId,
                    'encarregado_id' => $encarregadoId,
                    'mes_referencia' => $mesLinha,
                    'valor' => $valorPorAluno,
                    'comprovativo_path' => $comprovativoPath,
                    'recibo_pdf' => $reciboPdf,
                    'metodo_pagamento' => $metodo,
                    'estado' => 'aprovado'
                ]);

                if (!($resultadoComprovativo['sucesso'] ?? false)) {
                    throw new RuntimeException('Falha ao registar comprovativo.');
                }

                $codigos[] = (string) ($resultadoComprovativo['codigo'] ?? '');
                $nomesAlunos[] = $this->obterNomeAlunoPorId($alunoId);
            }

            if (empty($codigos)) {
                throw new RuntimeException('Nenhum pagamento foi processado.');
            }

            $nomeEncarregado = $this->obterNomeEncarregadoPorId($encarregadoId);
            $assunto = $tipoPagamento === 'atividade'
                ? 'Pagamento de atividade confirmado'
                : 'Pagamento de mensalidade confirmado';
            $mensagem = $nomeEncarregado . ' confirmou pagamento via ' . $metodo . ' para: ' . implode(', ', array_filter($nomesAlunos)) . '.';
            if ($observacao !== '') {
                $mensagem .= ' ' . $observacao;
            }

            $this->enviarNotificacaoParaPerfis(['secretaria', 'direcao_pedagogica'], $assunto, $mensagem);
            $this->db->commit();

            return [
                'sucesso' => true,
                'mensagem' => 'Pagamento simulado concluÃ­do com sucesso.',
                'codigos' => $codigos
            ];
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'sucesso' => false,
                'mensagem' => 'NÃ£o foi possÃ­vel concluir o pagamento simulado.',
                'codigos' => []
            ];
        }
    }

    public function atualizarReciboComprovativosPorCodigos(array $codigos, string $reciboPath): bool
    {
        $codigos = array_values(array_filter(array_map(static fn($item): string => trim((string) $item), $codigos)));
        $reciboPath = trim($reciboPath);

        if (empty($codigos) || $reciboPath === '') {
            return false;
        }

        $temRecibo = $this->colunaExiste('comprovativos_pagamento', 'recibo_pdf');
        if (!$temRecibo) {
            return false;
        }

        $placeholders = [];
        $params = ['recibo_pdf' => $reciboPath];
        foreach ($codigos as $indice => $codigo) {
            $chave = 'c' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $codigo;
        }

        try {
            $sql = 'UPDATE comprovativos_pagamento
                    SET recibo_pdf = :recibo_pdf
                    WHERE codigo_referencia IN (' . implode(', ', $placeholders) . ')';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarComprovativosEncarregado(int $encarregadoId, int $limite = 30): array
    {
        $limite = max(1, $limite);
        $temMetodo = $this->colunaExiste('comprovativos_pagamento', 'metodo_pagamento');
        $temRecibo = $this->colunaExiste('comprovativos_pagamento', 'recibo_pdf');
        $metodoSql = $temMetodo ? 'metodo_pagamento' : '"referencia" AS metodo_pagamento';
        $reciboSql = $temRecibo ? 'recibo_pdf' : 'NULL AS recibo_pdf';
        try {
            $sql = "SELECT codigo_referencia, mes_referencia, valor, estado, {$metodoSql}, {$reciboSql}, criado_em
                    FROM comprovativos_pagamento
                    WHERE encarregado_id = :encarregado_id
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['encarregado_id' => $encarregadoId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function solicitarDocumento(array $dados): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO solicitacoes_documentos
                 (aluno_id, encarregado_id, solicitado_por, tipo_documento, estado, observacao, criado_em)
                 VALUES (:aluno_id, :encarregado_id, :solicitado_por, :tipo_documento, "pendente", :observacao, NOW())'
            );
            return $stmt->execute([
                'aluno_id' => (int) $dados['aluno_id'],
                'encarregado_id' => (int) $dados['encarregado_id'] > 0 ? (int) $dados['encarregado_id'] : null,
                'solicitado_por' => (int) $dados['solicitado_por'],
                'tipo_documento' => $dados['tipo_documento'],
                'observacao' => $dados['observacao'] !== '' ? $dados['observacao'] : null
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function solicitarBoletim(int $alunoId, string $periodo, int $solicitadoPor, ?int $encarregadoId = null): bool
    {
        if ($alunoId <= 0 || trim($periodo) === '' || $solicitadoPor <= 0) {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO boletins (aluno_id, periodo, data_entrega, status, arquivo_pdf, criado_em)
                 VALUES (:aluno_id, :periodo, NULL, "solicitado", NULL, NOW())
                 ON DUPLICATE KEY UPDATE
                    periodo = VALUES(periodo),
                    status = "solicitado",
                    data_entrega = NULL'
            );

            return $stmt->execute([
                'aluno_id' => $alunoId,
                'periodo' => $periodo,
            ]);
        } catch (Throwable $erro) {
            try {
                $stmt = $this->db->prepare(
                    'INSERT INTO boletins (aluno_id, periodo, data_entrega, status, arquivo_pdf)
                     VALUES (:aluno_id, :periodo, NULL, "solicitado", NULL)'
                );
                return $stmt->execute([
                    'aluno_id' => $alunoId,
                    'periodo' => $periodo,
                ]);
            } catch (Throwable $erroSecundario) {
                return false;
            }
        }
    }

    public function listarBoletinsAluno(int $alunoId, int $limite = 20): array
    {
        $limite = max(1, $limite);
        if ($alunoId <= 0) {
            return [];
        }

        try {
            $sql = "SELECT id, periodo, data_entrega, status, arquivo_pdf, criado_em
                    FROM boletins
                    WHERE aluno_id = :aluno_id
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['aluno_id' => $alunoId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarBoletinsEncarregado(int $encarregadoId, int $limite = 20): array
    {
        $limite = max(1, $limite);
        if ($encarregadoId <= 0) {
            return [];
        }

        try {
            $sql = "SELECT
                        b.id,
                        b.periodo,
                        b.data_entrega,
                        b.status,
                        b.arquivo_pdf,
                        b.criado_em,
                        ua.nome AS aluno
                    FROM boletins b
                    INNER JOIN alunos a ON a.id = b.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    INNER JOIN encarregado_aluno ea ON ea.aluno_id = a.id
                    INNER JOIN encarregados e ON e.id = ea.encarregado_id
                    WHERE e.id = :encarregado_id
                    ORDER BY b.criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['encarregado_id' => $encarregadoId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarSolicitacoesPorUtilizador(int $utilizadorId, int $limite = 20): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT aluno_id, tipo_documento, estado, observacao, criado_em
                    FROM solicitacoes_documentos
                    WHERE solicitado_por = :solicitado_por
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['solicitado_por' => $utilizadorId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarMateriaisAluno(int $utilizadorId, int $limite = 30): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT me.id, me.titulo, me.descricao, me.ficheiro_path, me.criado_em, t.nome AS turma
                    FROM alunos a
                    INNER JOIN matriculas m ON m.aluno_id = a.id AND m.status = 'activo'
                    INNER JOIN turmas t ON t.id = m.turma_id
                    INNER JOIN materiais_estudo me ON me.turma_id = t.id
                    LEFT JOIN materiais_aluno_ocultos mao
                        ON mao.material_id = me.id
                       AND mao.aluno_id = a.id
                    WHERE a.utilizador_id = :utilizador_id
                      AND mao.id IS NULL
                    ORDER BY me.criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarRankingTurmaAluno(int $utilizadorId, int $limite = 10): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT
                        ua.nome AS aluno,
                        ROUND(AVG(n.nota), 1) AS media_geral
                    FROM alunos a_ref
                    INNER JOIN matriculas m_ref ON m_ref.aluno_id = a_ref.id AND m_ref.status = 'activo'
                    INNER JOIN matriculas m ON m.turma_id = m_ref.turma_id AND m.status = 'activo'
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    LEFT JOIN notas n ON n.matricula_id = m.id
                    WHERE a_ref.utilizador_id = :utilizador_id
                    GROUP BY a.id, ua.nome
                    ORDER BY media_geral DESC, ua.nome
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function criarGrupoEstudo(int $alunoId, string $nome, string $descricao): bool
    {
        try {
            $this->db->beginTransaction();
            $stmtG = $this->db->prepare('INSERT INTO grupos_estudo (criador_aluno_id, nome, descricao) VALUES (:criador_aluno_id, :nome, :descricao)');
            $stmtG->execute([
                'criador_aluno_id' => $alunoId,
                'nome' => $nome,
                'descricao' => $descricao !== '' ? $descricao : null
            ]);
            $grupoId = (int) $this->db->lastInsertId();

            $stmtM = $this->db->prepare('INSERT INTO grupo_estudo_membros (grupo_id, aluno_id) VALUES (:grupo_id, :aluno_id)');
            $stmtM->execute(['grupo_id' => $grupoId, 'aluno_id' => $alunoId]);

            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function listarGruposEstudoAluno(int $alunoId, int $limite = 20): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT
                        g.id,
                        g.criador_aluno_id,
                        g.nome,
                        g.descricao,
                        g.criado_em,
                        COUNT(gm2.aluno_id) AS total_membros
                    FROM grupo_estudo_membros gm
                    INNER JOIN grupos_estudo g ON g.id = gm.grupo_id
                    LEFT JOIN grupo_estudo_membros gm2 ON gm2.grupo_id = g.id
                    WHERE gm.aluno_id = :aluno_id
                    GROUP BY g.id, g.criador_aluno_id, g.nome, g.descricao, g.criado_em
                    ORDER BY g.criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['aluno_id' => $alunoId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarAvisosAluno(int $utilizadorId, int $limite = 20): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT id, titulo, mensagem, data_inicio, data_fim, criado_em
                    FROM avisos
                    WHERE destinatarios IN ('todos', 'alunos')
                      AND (data_inicio IS NULL OR data_inicio <= NOW())
                      AND (data_fim IS NULL OR data_fim >= NOW())
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarColegasTurmaAluno(int $utilizadorId, int $limite = 120): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT
                        u.id AS utilizador_id,
                        a.id AS aluno_id,
                        u.nome,
                        t.nome AS turma
                    FROM alunos a_ref
                    INNER JOIN matriculas m_ref ON m_ref.aluno_id = a_ref.id AND m_ref.status = 'activo'
                    INNER JOIN turmas t ON t.id = m_ref.turma_id
                    INNER JOIN matriculas m ON m.turma_id = t.id AND m.status = 'activo'
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores u ON u.id = a.utilizador_id
                    WHERE a_ref.utilizador_id = :utilizador_id
                      AND u.id <> :utilizador_id
                    GROUP BY u.id, a.id, u.nome, t.nome
                    ORDER BY u.nome
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarMensagensAluno(int $utilizadorId, int $limite = 80, string $estadoFiltro = 'todas'): array
    {
        $limite = max(1, $limite);
        $estadoFiltro = strtolower(trim($estadoFiltro));
        try {
            $estadoSql = '';
            $params = ['utilizador_id' => $utilizadorId];
            if (in_array($estadoFiltro, ['nao_lida', 'lida'], true)) {
                $estadoSql = ' AND m.status = :estado_filtro';
                $params['estado_filtro'] = $estadoFiltro;
            }

            $sql = "SELECT
                        m.id,
                        m.assunto,
                        m.mensagem,
                        m.status,
                        m.criado_em,
                        m.remetente_id,
                        m.destinatario_id,
                        ur.nome AS remetente_nome,
                        ud.nome AS destinatario_nome
                    FROM mensagens_internas m
                    LEFT JOIN utilizadores ur ON ur.id = m.remetente_id
                    LEFT JOIN utilizadores ud ON ud.id = m.destinatario_id
                    WHERE (
                        m.destinatario_id = :utilizador_id
                        OR m.remetente_id = :utilizador_id
                        OR (m.destinatario_id IS NULL AND m.perfil_destino IN ('todos', 'aluno'))
                    ){$estadoSql}
                    ORDER BY m.criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function enviarMensagemColegaTurma(int $remetenteUtilizadorId, int $destinatarioUtilizadorId, string $assunto, string $mensagem): bool
    {
        if ($remetenteUtilizadorId <= 0 || $destinatarioUtilizadorId <= 0 || $mensagem === '') {
            return false;
        }

        $colegas = $this->listarColegasTurmaAluno($remetenteUtilizadorId, 250);
        $permitido = false;
        foreach ($colegas as $colega) {
            if ((int) ($colega['utilizador_id'] ?? 0) === $destinatarioUtilizadorId) {
                $permitido = true;
                break;
            }
        }

        if (!$permitido) {
            return false;
        }

        return $this->enviarMensagemInterna($remetenteUtilizadorId, $destinatarioUtilizadorId, 'aluno', $assunto, $mensagem);
    }

    public function listarPresencasAluno(int $utilizadorId, int $limite = 180): array
    {
        $limite = max(1, $limite);
        $temJustificativa = $this->colunaExiste('presencas', 'justificativa');
        $temStatus = $this->colunaExiste('presencas', 'justificativa_status');
        $justificativaSql = $temJustificativa ? 'p.justificativa' : 'NULL';
        $statusSql = $temStatus ? 'p.justificativa_status' : "'sem_estado'";

        try {
            $sql = "SELECT
                        p.id,
                        p.data,
                        p.presente,
                        {$justificativaSql} AS justificativa,
                        {$statusSql} AS justificativa_status
                    FROM alunos a
                    INNER JOIN matriculas m ON m.aluno_id = a.id
                    INNER JOIN presencas p ON p.matricula_id = m.id
                    WHERE a.utilizador_id = :utilizador_id
                    ORDER BY p.data DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function enviarJustificativaFalta(int $utilizadorId, int $presencaId, string $justificativa): bool
    {
        if ($utilizadorId <= 0 || $presencaId <= 0 || trim($justificativa) === '') {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE presencas p
                 INNER JOIN matriculas m ON m.id = p.matricula_id
                 INNER JOIN alunos a ON a.id = m.aluno_id
                 SET p.justificativa = :justificativa,
                     p.justificativa_status = 'pendente',
                     p.justificativa_analisada_por = NULL,
                     p.justificativa_analisada_em = NULL
                 WHERE p.id = :presenca_id
                   AND a.utilizador_id = :utilizador_id
                   AND p.presente = 0"
            );
            $stmt->execute([
                'justificativa' => trim($justificativa),
                'presenca_id' => $presencaId,
                'utilizador_id' => $utilizadorId
            ]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarJustificativasPendentesProfessor(int $utilizadorId, int $turmaId = 0, int $disciplinaId = 0): array
    {
        if ($utilizadorId <= 0) {
            return [];
        }

        try {
            $temDisciplina = $this->colunaExiste('presencas', 'disciplina_id');
            $sql = "SELECT
                        p.id AS presenca_id,
                        p.data,
                        p.presente,
                        p.justificativa,
                        p.justificativa_status,
                        ua.nome AS aluno_nome,
                        t.nome AS turma_nome,
                        m.id AS matricula_id,
                        d.nome AS disciplina
                    FROM matriculas m
                    INNER JOIN turmas t ON t.id = m.turma_id
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    INNER JOIN presencas p ON p.matricula_id = m.id
                    LEFT JOIN disciplinas d ON d.id = p.disciplina_id
                    WHERE m.status = 'activo'
                      AND p.presente = 0
                      AND p.justificativa IS NOT NULL
                      AND p.justificativa <> ''
                      AND p.justificativa_status = 'pendente'";
            $params = [];

            if ($turmaId > 0) {
                if (!$this->professorPodeGerirTurma($utilizadorId, $turmaId, $disciplinaId)) {
                    return [];
                }
                $sql .= ' AND t.id = :turma_id';
                $params['turma_id'] = $turmaId;
            } else {
                $turmasIds = $this->obterTurmasProfessorIds($utilizadorId);
                $filtro = $this->criarParametrosListaInteiros($turmasIds, 'just_turma_');
                if ($filtro['sql'] === '') {
                    return [];
                }
                $sql .= ' AND t.id IN (' . $filtro['sql'] . ')';
                $params = array_merge($params, $filtro['params']);
            }

            if ($temDisciplina && $disciplinaId > 0) {
                $sql .= ' AND p.disciplina_id = :disciplina_id';
                $params['disciplina_id'] = $disciplinaId;
            }

            $sql .= ' ORDER BY p.data DESC, ua.nome ASC LIMIT 120';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function analisarJustificativaProfessor(int $utilizadorId, int $presencaId, string $decisao, string $observacao = ''): bool
    {
        if ($utilizadorId <= 0 || $presencaId <= 0) {
            return false;
        }

        $decisao = strtolower(trim($decisao));
        if (!in_array($decisao, ['remover_falta', 'manter_falta'], true)) {
            return false;
        }

        try {
            $sql = "SELECT t.id AS turma_id, COALESCE(p.disciplina_id, 0) AS disciplina_id
                    FROM presencas p
                    INNER JOIN matriculas m ON m.id = p.matricula_id
                    INNER JOIN turmas t ON t.id = m.turma_id
                    WHERE p.id = :presenca_id
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'presenca_id' => $presencaId
            ]);
            $acesso = $stmt->fetch();
            $turmaId = (int) ($acesso['turma_id'] ?? 0);
            $disciplinaId = (int) ($acesso['disciplina_id'] ?? 0);
            if ($turmaId <= 0 || !$this->professorPodeGerirTurma($utilizadorId, $turmaId, $disciplinaId)) {
                return false;
            }

            if ($decisao === 'remover_falta') {
                $stmtUpdate = $this->db->prepare(
                    "UPDATE presencas
                     SET presente = 1,
                         justificativa_status = 'aceite',
                         justificativa_analisada_por = :utilizador_id,
                         justificativa_analisada_em = NOW()
                     WHERE id = :presenca_id"
                );
                return $stmtUpdate->execute([
                    'utilizador_id' => $utilizadorId,
                    'presenca_id' => $presencaId
                ]);
            }

            $stmtUpdate = $this->db->prepare(
                "UPDATE presencas
                 SET presente = 0,
                     justificativa = NULL,
                     justificativa_status = 'rejeitada',
                     justificativa_analisada_por = :utilizador_id,
                     justificativa_analisada_em = NOW()
                 WHERE id = :presenca_id"
            );
            return $stmtUpdate->execute([
                'utilizador_id' => $utilizadorId,
                'presenca_id' => $presencaId
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarMembrosGrupoEstudo(int $grupoId, int $alunoContextoId): array
    {
        if ($grupoId <= 0 || $alunoContextoId <= 0) {
            return [];
        }

        try {
            $stmtAcesso = $this->db->prepare(
                'SELECT COUNT(*) FROM grupo_estudo_membros WHERE grupo_id = :grupo_id AND aluno_id = :aluno_id'
            );
            $stmtAcesso->execute([
                'grupo_id' => $grupoId,
                'aluno_id' => $alunoContextoId
            ]);
            if ((int) $stmtAcesso->fetchColumn() <= 0) {
                return [];
            }

            $stmt = $this->db->prepare(
                "SELECT
                    gm.aluno_id,
                    g.criador_aluno_id,
                    u.nome AS aluno_nome,
                    u.id AS utilizador_id
                 FROM grupo_estudo_membros gm
                 INNER JOIN grupos_estudo g ON g.id = gm.grupo_id
                 INNER JOIN alunos a ON a.id = gm.aluno_id
                 INNER JOIN utilizadores u ON u.id = a.utilizador_id
                 WHERE gm.grupo_id = :grupo_id
                 ORDER BY u.nome"
            );
            $stmt->execute(['grupo_id' => $grupoId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function adicionarMembroGrupoEstudo(int $grupoId, int $alunoSolicitanteId, int $alunoId): bool
    {
        if ($grupoId <= 0 || $alunoSolicitanteId <= 0 || $alunoId <= 0) {
            return false;
        }

        try {
            $stmtCriador = $this->db->prepare('SELECT criador_aluno_id FROM grupos_estudo WHERE id = :grupo_id LIMIT 1');
            $stmtCriador->execute(['grupo_id' => $grupoId]);
            $criadorId = (int) $stmtCriador->fetchColumn();
            if ($criadorId <= 0 || $criadorId !== $alunoSolicitanteId) {
                return false;
            }

            $stmt = $this->db->prepare('INSERT IGNORE INTO grupo_estudo_membros (grupo_id, aluno_id) VALUES (:grupo_id, :aluno_id)');
            return $stmt->execute([
                'grupo_id' => $grupoId,
                'aluno_id' => $alunoId
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function removerMembroGrupoEstudo(int $grupoId, int $alunoSolicitanteId, int $alunoId): bool
    {
        if ($grupoId <= 0 || $alunoSolicitanteId <= 0 || $alunoId <= 0) {
            return false;
        }

        try {
            $stmtCriador = $this->db->prepare('SELECT criador_aluno_id FROM grupos_estudo WHERE id = :grupo_id LIMIT 1');
            $stmtCriador->execute(['grupo_id' => $grupoId]);
            $criadorId = (int) $stmtCriador->fetchColumn();
            if ($criadorId <= 0 || $criadorId !== $alunoSolicitanteId) {
                return false;
            }

            $stmt = $this->db->prepare('DELETE FROM grupo_estudo_membros WHERE grupo_id = :grupo_id AND aluno_id = :aluno_id');
            return $stmt->execute([
                'grupo_id' => $grupoId,
                'aluno_id' => $alunoId
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function sairGrupoEstudo(int $grupoId, int $alunoId): bool
    {
        if ($grupoId <= 0 || $alunoId <= 0) {
            return false;
        }

        try {
            $stmtCriador = $this->db->prepare('SELECT criador_aluno_id FROM grupos_estudo WHERE id = :grupo_id LIMIT 1');
            $stmtCriador->execute(['grupo_id' => $grupoId]);
            $criadorId = (int) $stmtCriador->fetchColumn();
            if ($criadorId === $alunoId) {
                $stmtDelete = $this->db->prepare('DELETE FROM grupos_estudo WHERE id = :grupo_id');
                return $stmtDelete->execute(['grupo_id' => $grupoId]);
            }

            $stmt = $this->db->prepare('DELETE FROM grupo_estudo_membros WHERE grupo_id = :grupo_id AND aluno_id = :aluno_id');
            return $stmt->execute([
                'grupo_id' => $grupoId,
                'aluno_id' => $alunoId
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function eliminarGrupoEstudo(int $grupoId, int $alunoSolicitanteId): bool
    {
        if ($grupoId <= 0 || $alunoSolicitanteId <= 0) {
            return false;
        }

        try {
            $stmt = $this->db->prepare('DELETE FROM grupos_estudo WHERE id = :grupo_id AND criador_aluno_id = :criador_aluno_id');
            return $stmt->execute([
                'grupo_id' => $grupoId,
                'criador_aluno_id' => $alunoSolicitanteId
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarMensagensGrupoEstudo(int $grupoId, int $alunoId, int $limite = 80): array
    {
        if ($grupoId <= 0 || $alunoId <= 0) {
            return [];
        }

        $limite = max(1, $limite);

        try {
            $stmtAcesso = $this->db->prepare('SELECT COUNT(*) FROM grupo_estudo_membros WHERE grupo_id = :grupo_id AND aluno_id = :aluno_id');
            $stmtAcesso->execute([
                'grupo_id' => $grupoId,
                'aluno_id' => $alunoId
            ]);
            if ((int) $stmtAcesso->fetchColumn() <= 0) {
                return [];
            }

            $sql = "SELECT
                        gm.id,
                        gm.mensagem,
                        gm.criado_em,
                        gm.remetente_id,
                        u.nome AS remetente_nome
                    FROM grupo_mensagens gm
                    INNER JOIN alunos a ON a.id = gm.remetente_id
                    INNER JOIN utilizadores u ON u.id = a.utilizador_id
                    WHERE gm.grupo_id = :grupo_id
                    ORDER BY gm.criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['grupo_id' => $grupoId]);
            return array_reverse($stmt->fetchAll() ?: []);
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function enviarMensagemGrupoEstudo(int $grupoId, int $alunoId, string $mensagem): bool
    {
        if ($grupoId <= 0 || $alunoId <= 0 || trim($mensagem) === '') {
            return false;
        }

        try {
            $stmtAcesso = $this->db->prepare('SELECT COUNT(*) FROM grupo_estudo_membros WHERE grupo_id = :grupo_id AND aluno_id = :aluno_id');
            $stmtAcesso->execute([
                'grupo_id' => $grupoId,
                'aluno_id' => $alunoId
            ]);
            if ((int) $stmtAcesso->fetchColumn() <= 0) {
                return false;
            }

            $stmt = $this->db->prepare(
                'INSERT INTO grupo_mensagens (grupo_id, remetente_id, mensagem, criado_em)
                 VALUES (:grupo_id, :remetente_id, :mensagem, NOW())'
            );
            return $stmt->execute([
                'grupo_id' => $grupoId,
                'remetente_id' => $alunoId,
                'mensagem' => trim($mensagem)
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function ocultarMaterialAluno(int $utilizadorId, int $materialId): bool
    {
        if ($utilizadorId <= 0 || $materialId <= 0) {
            return false;
        }

        $alunoId = $this->obterAlunoIdPorUtilizador($utilizadorId);
        if ($alunoId <= 0) {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT IGNORE INTO materiais_aluno_ocultos (material_id, aluno_id, criado_em)
                 VALUES (:material_id, :aluno_id, NOW())'
            );
            return $stmt->execute([
                'material_id' => $materialId,
                'aluno_id' => $alunoId
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function removerMaterialProfessor(int $utilizadorId, int $materialId): bool
    {
        if ($utilizadorId <= 0 || $materialId <= 0) {
            return false;
        }

        try {
            $sql = "DELETE me
                    FROM materiais_estudo me
                    INNER JOIN professores p ON p.id = me.professor_id
                    WHERE me.id = :material_id
                      AND p.utilizador_id = :utilizador_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'material_id' => $materialId,
                'utilizador_id' => $utilizadorId
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarTramitacoesPorDestino(string $perfilDestino, int $limite = 30): array
    {
        $limite = max(1, $limite);
        $destinos = [$perfilDestino];
        if ($perfilDestino === 'direcao_geral') {
            $destinos[] = 'publico';
        }

        $placeholders = [];
        $params = [];
        foreach ($destinos as $indice => $destino) {
            $chave = 'destino_' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $destino;
        }

        try {
            $sql = "SELECT
                        id,
                        codigo,
                        tipo_documento,
                        origem_setor,
                        destino_setor,
                        status,
                        observacao,
                        atualizado_em
                    FROM tramitacoes_documentais
                    WHERE destino_setor IN (" . implode(',', $placeholders) . ")
                    ORDER BY atualizado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function aplicarAcaoTramitacao(int $id, string $perfil, int $utilizadorId, string $acao, string $observacao = ''): bool
    {
        if ($id <= 0 || $utilizadorId <= 0) {
            return false;
        }

        $mapa = [
            'aprovar' => 'aprovado',
            'reprovar' => 'rejeitado',
            'rejeitar' => 'rejeitado',
            'publicar' => 'publicado'
        ];

        $acao = strtolower(trim($acao));
        if (!isset($mapa[$acao])) {
            return false;
        }

        try {
            $stmt = $this->db->prepare('SELECT id, codigo, destino_setor, observacao FROM tramitacoes_documentais WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $linha = $stmt->fetch();
            if (!is_array($linha)) {
                return false;
            }

            $destino = (string) ($linha['destino_setor'] ?? '');
            $permitido = $destino === $perfil || ($perfil === 'direcao_geral' && $destino === 'publico');
            if (!$permitido) {
                return false;
            }

            $novoStatus = $mapa[$acao];
            if ($novoStatus === 'publicado' && $destino !== 'publico') {
                return false;
            }

            $registro = '[' . date('Y-m-d H:i:s') . '] Utilizador #' . $utilizadorId . ' => ' . strtoupper($novoStatus);
            if ($observacao !== '') {
                $registro .= ' | ' . $observacao;
            }

            $observacaoAtual = trim((string) ($linha['observacao'] ?? ''));
            $observacaoFinal = $observacaoAtual === '' ? $registro : ($observacaoAtual . PHP_EOL . $registro);

            $this->db->beginTransaction();
            $stmtUpdate = $this->db->prepare(
                'UPDATE tramitacoes_documentais
                 SET status = :status, observacao = :observacao, atualizado_em = NOW()
                 WHERE id = :id'
            );
            $stmtUpdate->execute([
                'status' => $novoStatus,
                'observacao' => $observacaoFinal,
                'id' => $id
            ]);

            $stmtLog = $this->db->prepare(
                'INSERT INTO historico_atividades (utilizador_id, acao, detalhe, ip, criado_em)
                 VALUES (:utilizador_id, :acao, :detalhe, :ip, NOW())'
            );
            $stmtLog->execute([
                'utilizador_id' => $utilizadorId,
                'acao' => 'Tramitacao documental',
                'detalhe' => 'Documento ' . ((string) ($linha['codigo'] ?? ('#' . $id))) . ' atualizado para ' . $novoStatus,
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function reclamarNota(int $alunoId, int $disciplinaId, int $trimestre, string $mensagem): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO reclamacoes_notas (aluno_id, disciplina_id, trimestre, mensagem, estado, criado_em)
                 VALUES (:aluno_id, :disciplina_id, :trimestre, :mensagem, "aberta", NOW())'
            );
            return $stmt->execute([
                'aluno_id' => $alunoId,
                'disciplina_id' => $disciplinaId > 0 ? $disciplinaId : null,
                'trimestre' => $trimestre > 0 ? $trimestre : null,
                'mensagem' => $mensagem
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarReclamacoesAluno(int $alunoId, int $limite = 20): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT r.estado, r.mensagem, r.trimestre, d.nome AS disciplina, r.criado_em
                    FROM reclamacoes_notas r
                    LEFT JOIN disciplinas d ON d.id = r.disciplina_id
                    WHERE r.aluno_id = :aluno_id
                    ORDER BY r.criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['aluno_id' => $alunoId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarDocumentosDisponiveisAluno(int $alunoId, int $limite = 20): array
    {
        $limite = max(1, $limite);
        try {
            $sql = "SELECT tipo_documento, estado, observacao, autorizado_em, criado_em
                    FROM solicitacoes_documentos
                    WHERE aluno_id = :aluno_id
                      AND estado IN ('autorizado', 'disponibilizado')
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['aluno_id' => $alunoId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarPagamentosAtividadesAluno(int $utilizadorId): array
    {
        try {
            $sql = "SELECT ae.tema, pa.valor, pa.estado
                    FROM alunos a
                    INNER JOIN pagamentos_atividades pa ON pa.aluno_id = a.id
                    INNER JOIN atividades_extracurriculares ae ON ae.id = pa.atividade_id
                    WHERE a.utilizador_id = :utilizador_id
                    ORDER BY pa.criado_em DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function listarPagamentosAtividadesEncarregado(int $encarregadoId): array
    {
        if ($encarregadoId <= 0) {
            return [];
        }

        try {
            $sql = "SELECT
                        pa.id,
                        pa.aluno_id,
                        pa.atividade_id,
                        pa.valor,
                        pa.estado,
                        pa.criado_em,
                        ae.tema AS atividade,
                        ua.nome AS aluno
                    FROM pagamentos_atividades pa
                    INNER JOIN atividades_extracurriculares ae ON ae.id = pa.atividade_id
                    INNER JOIN alunos a ON a.id = pa.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    WHERE pa.encarregado_id = :encarregado_id
                    ORDER BY pa.criado_em DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['encarregado_id' => $encarregadoId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    private function atualizarDisciplinasCurso(int $cursoId, array $disciplinas): void
    {
        $stmtDelete = $this->db->prepare('DELETE FROM cursos_disciplinas WHERE curso_id = :curso_id');
        $stmtDelete->execute(['curso_id' => $cursoId]);

        $stmtInsert = $this->db->prepare('INSERT INTO cursos_disciplinas (curso_id, disciplina_id) VALUES (:curso_id, :disciplina_id)');
        foreach ($disciplinas as $disciplinaId) {
            $disciplinaId = (int) $disciplinaId;
            if ($disciplinaId <= 0) {
                continue;
            }
            $stmtInsert->execute([
                'curso_id' => $cursoId,
                'disciplina_id' => $disciplinaId
            ]);
        }
    }

    private function gerarCodigoReferencia(): string
    {
        do {
            $codigo = 'REF-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while ($this->codigoComprovativoExiste($codigo));

        return $codigo;
    }

    private function codigoComprovativoExiste(string $codigo): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM comprovativos_pagamento WHERE codigo_referencia = :codigo');
            $stmt->execute(['codigo' => $codigo]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $erro) {
            return false;
        }
    }

    private function obterMatriculaAtivaPorAluno(int $alunoId): int
    {
        if ($alunoId <= 0) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT id
                 FROM matriculas
                 WHERE aluno_id = :aluno_id
                   AND status = "activo"
                 ORDER BY data_matricula DESC, id DESC
                 LIMIT 1'
            );
            $stmt->execute(['aluno_id' => $alunoId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $erro) {
            return 0;
        }
    }

    private function normalizarMesReferencia(string $mesReferencia): string
    {
        $mesReferencia = trim($mesReferencia);
        if ($mesReferencia === '') {
            return date('m/Y');
        }

        if (preg_match('/^\d{4}-\d{2}$/', $mesReferencia) === 1) {
            $partes = explode('-', $mesReferencia);
            return $partes[1] . '/' . $partes[0];
        }

        if (preg_match('/^\d{2}\/\d{4}$/', $mesReferencia) === 1) {
            return $mesReferencia;
        }

        $timestamp = strtotime($mesReferencia . '-01');
        if ($timestamp !== false) {
            return date('m/Y', $timestamp);
        }

        return $mesReferencia;
    }

    private function obterNomeAlunoPorId(int $alunoId): string
    {
        if ($alunoId <= 0) {
            return '';
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT u.nome
                 FROM alunos a
                 INNER JOIN utilizadores u ON u.id = a.utilizador_id
                 WHERE a.id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $alunoId]);
            return (string) ($stmt->fetchColumn() ?: '');
        } catch (Throwable $erro) {
            return '';
        }
    }

    private function obterUtilizadorAlunoPorAlunoId(int $alunoId): int
    {
        if ($alunoId <= 0) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare('SELECT utilizador_id FROM alunos WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $alunoId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $erro) {
            return 0;
        }
    }

    private function obterNomeEncarregadoPorId(int $encarregadoId): string
    {
        if ($encarregadoId <= 0) {
            return '';
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT u.nome
                 FROM encarregados e
                 INNER JOIN utilizadores u ON u.id = e.utilizador_id
                 WHERE e.id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $encarregadoId]);
            return (string) ($stmt->fetchColumn() ?: '');
        } catch (Throwable $erro) {
            return '';
        }
    }

    private function enviarNotificacaoParaPerfis(array $perfis, string $assunto, string $mensagem): bool
    {
        $perfis = array_values(array_unique(array_filter(array_map(static fn($perfil): string => trim((string) $perfil), $perfis))));
        if (empty($perfis) || trim($assunto) === '' || trim($mensagem) === '') {
            return false;
        }

        $placeholders = [];
        $params = [];
        foreach ($perfis as $indice => $perfil) {
            $chave = 'p' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $perfil;
        }

        try {
            $sqlUtilizadores = 'SELECT id, perfil
                                FROM utilizadores
                                WHERE ativo = 1
                                  AND perfil IN (' . implode(', ', $placeholders) . ')';
            $stmtUtilizadores = $this->db->prepare($sqlUtilizadores);
            $stmtUtilizadores->execute($params);
            $utilizadores = $stmtUtilizadores->fetchAll() ?: [];
            if (empty($utilizadores)) {
                return false;
            }

            $stmtInsert = $this->db->prepare(
                'INSERT INTO mensagens_internas (remetente_id, destinatario_id, perfil_destino, assunto, mensagem, status, criado_em)
                 VALUES (:remetente_id, :destinatario_id, :perfil_destino, :assunto, :mensagem, "nao_lida", NOW())'
            );

            foreach ($utilizadores as $utilizador) {
                $stmtInsert->execute([
                    'remetente_id' => null,
                    'destinatario_id' => (int) ($utilizador['id'] ?? 0),
                    'perfil_destino' => (string) ($utilizador['perfil'] ?? ''),
                    'assunto' => $assunto,
                    'mensagem' => $mensagem
                ]);
            }

            return true;
        } catch (Throwable $erro) {
            return false;
        }
    }

    private function gerarSenhaInicial(): string
    {
        return gerar_senha_temporaria(8);
    }

    private function gerarEmailUnico(string $nome, string $dominio): string
    {
        $base = strtolower(trim($nome));
        $base = preg_replace('/[^a-z0-9]+/i', '.', $base);
        $base = trim((string) $base, '.');
        if ($base === '') {
            $base = 'utilizador';
        }

        $email = $base . '@' . $dominio;
        $contador = 1;

        while ($this->emailExiste($email)) {
            $email = $base . $contador . '@' . $dominio;
            $contador++;
        }

        return $email;
    }

    private function gerarEmailSequencial(string $prefixo, string $dominio): string
    {
        $prefixo = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $prefixo) ?? '', '-'));
        if ($prefixo === '') {
            $prefixo = 'utilizador';
        }

        $contador = 1;
        $email = $prefixo . '@' . $dominio;
        while ($this->emailExiste($email)) {
            $email = $prefixo . str_pad((string) $contador, 3, '0', STR_PAD_LEFT) . '@' . $dominio;
            $contador++;
        }

        return $email;
    }

    private function gerarEmailInstitucionalMatricula(string $nomeEncarregado, string $nomeAluno): string
    {
        $base = $this->normalizarBaseEmail($nomeEncarregado) . '.' . $this->normalizarBaseEmail($nomeAluno);
        $contador = 1;
        $dominio = 'escola.com';

        do {
            $email = $base . $contador . '@' . $dominio;
            $contador++;
        } while ($this->emailExiste($email));

        return $email;
    }

    private function normalizarBaseEmail(string $nome): string
    {
        $nome = trim(strip_tags($nome));
        $partes = preg_split('/\s+/u', $nome) ?: [];
        $base = strtolower((string) ($partes[0] ?? 'utilizador'));

        if (function_exists('transliterator_transliterate')) {
            $normalizado = transliterator_transliterate('Any-Latin; Latin-ASCII', $base);
            if (is_string($normalizado) && $normalizado !== '') {
                $base = $normalizado;
            }
        } else {
            $normalizado = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
            if (is_string($normalizado) && $normalizado !== '') {
                $base = $normalizado;
            }
        }

        $base = preg_replace('/[^a-z0-9]+/i', '', $base) ?? '';
        return $base !== '' ? $base : 'utilizador';
    }

    private function registarEmailMatriculaLog(array $dados, array $credenciaisAluno, ?array $credenciaisEncarregado = null): void
    {
        try {
            $diretorio = CAMINHO_RAIZ . '/storage/logs';
            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0775, true);
            }

            $ficheiro = $diretorio . '/emails_matricula.log';
            $linhas = [
                '[' . date('Y-m-d H:i:s') . '] Nova matricula concluida',
                'Aluno: ' . (string) ($dados['nome_aluno'] ?? ''),
                'Email aluno: ' . (string) ($credenciaisAluno['email'] ?? ''),
                'Senha aluno: ' . (string) ($credenciaisAluno['senha'] ?? ''),
                'Validade: ' . (string) ($credenciaisAluno['senha_valida_ate'] ?? ''),
            ];

            if (is_array($credenciaisEncarregado)) {
                $linhas[] = 'Enc. email: ' . (string) ($credenciaisEncarregado['email'] ?? '');
                $linhas[] = 'Enc. senha: ' . (string) ($credenciaisEncarregado['senha'] ?? '');
            }

            $linhas[] = str_repeat('-', 72);
            file_put_contents($ficheiro, implode(PHP_EOL, $linhas) . PHP_EOL, FILE_APPEND);
        } catch (Throwable $erro) {
        }
    }

    private function emailExiste(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM utilizadores WHERE email = :email');
        $stmt->execute(['email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function tabelaExiste(string $tabela): bool
    {
        static $cache = [];
        if (array_key_exists($tabela, $cache)) {
            return $cache[$tabela];
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :tabela'
            );
            $stmt->execute(['tabela' => $tabela]);
            $cache[$tabela] = (int) $stmt->fetchColumn() > 0;
            return $cache[$tabela];
        } catch (Throwable $erro) {
            $cache[$tabela] = false;
            return false;
        }
    }

    private function professorUsaAtribuicoesDisciplina(int $utilizadorId): bool
    {
        if ($utilizadorId <= 0 || !$this->tabelaExiste('professor_turma_disciplinas')) {
            return false;
        }

        static $cache = [];
        if (array_key_exists($utilizadorId, $cache)) {
            return $cache[$utilizadorId];
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM professor_turma_disciplinas ptd
                 INNER JOIN professores p ON p.id = ptd.professor_id
                 WHERE p.utilizador_id = :utilizador_id'
            );
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $cache[$utilizadorId] = (int) $stmt->fetchColumn() > 0;
            return $cache[$utilizadorId];
        } catch (Throwable $erro) {
            $cache[$utilizadorId] = false;
            return false;
        }
    }

    private function obterTurmasProfessorIds(int $utilizadorId): array
    {
        if ($utilizadorId <= 0) {
            return [];
        }

        try {
            if ($this->professorUsaAtribuicoesDisciplina($utilizadorId)) {
                $stmt = $this->db->prepare(
                    'SELECT DISTINCT ptd.turma_id
                     FROM professor_turma_disciplinas ptd
                     INNER JOIN professores p ON p.id = ptd.professor_id
                     WHERE p.utilizador_id = :utilizador_id
                     ORDER BY ptd.turma_id'
                );
            } else {
                $stmt = $this->db->prepare(
                    'SELECT DISTINCT t.id
                     FROM turmas t
                     INNER JOIN professores p ON p.id = t.professor_id
                     WHERE p.utilizador_id = :utilizador_id
                     ORDER BY t.id'
                );
            }

            $stmt->execute(['utilizador_id' => $utilizadorId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $erro) {
            return [];
        }
    }

    private function professorPodeGerirTurma(int $utilizadorId, int $turmaId, int $disciplinaId = 0): bool
    {
        if ($utilizadorId <= 0 || $turmaId <= 0) {
            return false;
        }

        try {
            if ($this->professorUsaAtribuicoesDisciplina($utilizadorId)) {
                $sql = 'SELECT COUNT(*)
                        FROM professor_turma_disciplinas ptd
                        INNER JOIN professores p ON p.id = ptd.professor_id
                        WHERE p.utilizador_id = :utilizador_id
                          AND ptd.turma_id = :turma_id';
                $params = [
                    'utilizador_id' => $utilizadorId,
                    'turma_id' => $turmaId
                ];
                if ($disciplinaId > 0) {
                    $sql .= ' AND ptd.disciplina_id = :disciplina_id';
                    $params['disciplina_id'] = $disciplinaId;
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                return (int) $stmt->fetchColumn() > 0;
            }

            $stmt = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM turmas t
                 INNER JOIN professores p ON p.id = t.professor_id
                 WHERE p.utilizador_id = :utilizador_id
                   AND t.id = :turma_id'
            );
            $stmt->execute([
                'utilizador_id' => $utilizadorId,
                'turma_id' => $turmaId
            ]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $erro) {
            return false;
        }
    }

    private function listarUtilizadoresProfessoresTurma(int $turmaId): array
    {
        if ($turmaId <= 0) {
            return [];
        }

        try {
            $utilizadores = [];

            if ($this->tabelaExiste('professor_turma_disciplinas')) {
                $stmt = $this->db->prepare(
                    'SELECT DISTINCT p.utilizador_id
                     FROM professor_turma_disciplinas ptd
                     INNER JOIN professores p ON p.id = ptd.professor_id
                     WHERE ptd.turma_id = :turma_id'
                );
                $stmt->execute(['turma_id' => $turmaId]);
                $utilizadores = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            }

            $stmtLegacy = $this->db->prepare(
                'SELECT DISTINCT p.utilizador_id
                 FROM turmas t
                 INNER JOIN professores p ON p.id = t.professor_id
                 WHERE t.id = :turma_id'
            );
            $stmtLegacy->execute(['turma_id' => $turmaId]);
            $utilizadores = array_merge($utilizadores, array_map('intval', $stmtLegacy->fetchAll(PDO::FETCH_COLUMN) ?: []));

            $utilizadores = array_values(array_unique(array_filter($utilizadores, static fn(int $id): bool => $id > 0)));
            sort($utilizadores);
            return $utilizadores;
        } catch (Throwable $erro) {
            return [];
        }
    }

    private function criarParametrosListaInteiros(array $ids, string $prefixo): array
    {
        $placeholders = [];
        $params = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));

        foreach ($ids as $indice => $id) {
            $chave = $prefixo . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $id;
        }

        return [
            'sql' => implode(', ', $placeholders),
            'params' => $params
        ];
    }

    private function calcularSituacao(float $media): string
    {
        if ($media >= 14) {
            return 'Aprovado';
        }
        if ($media >= 10) {
            return 'Recuperacao';
        }
        return 'Reprovado';
    }

    private function listarAnexosMensagensInternas(array $mensagensIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $mensagensIds), static fn(int $id): bool => $id > 0));
        if (empty($ids) || !$this->tabelaExiste('mensagens_internas_anexos')) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $indice => $idMensagem) {
            $chave = 'm' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $idMensagem;
        }

        try {
            $sql = 'SELECT id, mensagem_id, caminho_ficheiro, nome_original, tipo_mime, tamanho_bytes, criado_em
                    FROM mensagens_internas_anexos
                    WHERE mensagem_id IN (' . implode(', ', $placeholders) . ')
                    ORDER BY id ASC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $linhas = $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }

        $resultado = [];
        foreach ($linhas as $linha) {
            $mensagemId = (int) ($linha['mensagem_id'] ?? 0);
            if ($mensagemId <= 0) {
                continue;
            }

            if (!isset($resultado[$mensagemId])) {
                $resultado[$mensagemId] = [];
            }

            $resultado[$mensagemId][] = $linha;
        }

        return $resultado;
    }

    private function obterUtilizadorParaComunicacaoInterna(int $utilizadorId): ?array
    {
        if ($utilizadorId <= 0) {
            return null;
        }

        $perfisPermitidos = $this->perfisComunicacaoInterna();
        $placeholders = [];
        $params = ['id' => $utilizadorId];
        foreach ($perfisPermitidos as $indice => $perfil) {
            $chave = 'perfil_' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $perfil;
        }

        try {
            $sql = 'SELECT id, nome, perfil
                    FROM utilizadores
                    WHERE id = :id
                      AND ativo = 1
                      AND perfil IN (' . implode(', ', $placeholders) . ')
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetch();
            return is_array($dados) ? $dados : null;
        } catch (Throwable $erro) {
            return null;
        }
    }

    private function obterMensagemInternaPainelPorId(int $mensagemId): ?array
    {
        if ($mensagemId <= 0) {
            return null;
        }

        $perfisPermitidos = $this->perfisComunicacaoInterna();
        $placeholders = [];
        $params = ['id' => $mensagemId];
        foreach ($perfisPermitidos as $indice => $perfil) {
            $chave = 'perfil_' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $perfil;
        }

        try {
            $sql = 'SELECT m.id, m.remetente_id, m.destinatario_id
                    FROM mensagens_internas m
                    INNER JOIN utilizadores ur ON ur.id = m.remetente_id
                    INNER JOIN utilizadores ud ON ud.id = m.destinatario_id
                    WHERE m.id = :id
                      AND m.destinatario_id IS NOT NULL
                      AND ur.perfil IN (' . implode(', ', $placeholders) . ')
                      AND ud.perfil IN (' . implode(', ', $placeholders) . ')
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetch();
            return is_array($dados) ? $dados : null;
        } catch (Throwable $erro) {
            return null;
        }
    }

    private function perfisComunicacaoInterna(): array
    {
        return [
            'aluno',
            'encarregado',
            'secretaria',
            'rh',
            'professor',
            'direcao_geral',
            'direcao_pedagogica'
        ];
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :tabela
                   AND COLUMN_NAME = :coluna'
            );
            $stmt->execute([
                'tabela' => $tabela,
                'coluna' => $coluna
            ]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $erro) {
            return false;
        }
    }

    private function garantirEstruturasAcademicas(): void
    {
        static $estruturasCriadas = false;
        if ($estruturasCriadas) {
            return;
        }

        $sqls = [
            "CREATE TABLE IF NOT EXISTS grupo_mensagens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                grupo_id INT NOT NULL,
                remetente_id INT NOT NULL,
                mensagem TEXT NOT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (grupo_id) REFERENCES grupos_estudo(id) ON DELETE CASCADE,
                FOREIGN KEY (remetente_id) REFERENCES alunos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS materiais_aluno_ocultos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                material_id INT NOT NULL,
                aluno_id INT NOT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_material_aluno (material_id, aluno_id),
                FOREIGN KEY (material_id) REFERENCES materiais_estudo(id) ON DELETE CASCADE,
                FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS documentos_partilhados (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(180) NOT NULL,
                descricao TEXT NULL,
                perfil_destino ENUM('aluno','encarregado') NOT NULL,
                aluno_id INT NULL,
                encarregado_id INT NULL,
                ficheiro_path VARCHAR(255) NOT NULL,
                criado_por INT NOT NULL,
                lixeira TINYINT(1) NOT NULL DEFAULT 0,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
                FOREIGN KEY (encarregado_id) REFERENCES encarregados(id) ON DELETE CASCADE,
                FOREIGN KEY (criado_por) REFERENCES utilizadores(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS professor_turma_disciplinas (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS mensagens_internas_anexos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mensagem_id INT NOT NULL,
                caminho_ficheiro VARCHAR(255) NOT NULL,
                nome_original VARCHAR(255) NOT NULL,
                tipo_mime VARCHAR(120) NOT NULL,
                tamanho_bytes INT NOT NULL DEFAULT 0,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_msg_interna_anexo_mensagem (mensagem_id),
                FOREIGN KEY (mensagem_id) REFERENCES mensagens_internas(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];

        foreach ($sqls as $sql) {
            try {
                $this->db->exec($sql);
            } catch (Throwable $erro) {
            }
        }

        $alteracoes = [
            ['presencas', 'justificativa', 'ALTER TABLE presencas ADD COLUMN justificativa TEXT NULL AFTER presente'],
            ['presencas', 'disciplina_id', 'ALTER TABLE presencas ADD COLUMN disciplina_id INT NULL AFTER data'],
            ['presencas', 'professor_id', 'ALTER TABLE presencas ADD COLUMN professor_id INT NULL AFTER disciplina_id'],
            ['presencas', 'justificativa_status', "ALTER TABLE presencas ADD COLUMN justificativa_status ENUM('pendente','aceite','rejeitada') NULL AFTER justificativa"],
            ['presencas', 'justificativa_analisada_por', 'ALTER TABLE presencas ADD COLUMN justificativa_analisada_por INT NULL AFTER justificativa_status'],
            ['presencas', 'justificativa_analisada_em', 'ALTER TABLE presencas ADD COLUMN justificativa_analisada_em DATETIME NULL AFTER justificativa_analisada_por'],
            ['utilizadores', 'tutorial_visto', 'ALTER TABLE utilizadores ADD COLUMN tutorial_visto TINYINT(1) NOT NULL DEFAULT 0 AFTER ultimo_acesso'],
            ['utilizadores', 'senha_temporaria', 'ALTER TABLE utilizadores ADD COLUMN senha_temporaria TINYINT(1) NOT NULL DEFAULT 0 AFTER tutorial_visto'],
            ['notas', 'teste', 'ALTER TABLE notas ADD COLUMN teste DECIMAL(5,2) NULL AFTER nota'],
            ['notas', 'trabalho', 'ALTER TABLE notas ADD COLUMN trabalho DECIMAL(5,2) NULL AFTER teste'],
            ['notas', 'participacao', 'ALTER TABLE notas ADD COLUMN participacao DECIMAL(5,2) NULL AFTER trabalho'],
            ['notas', 'situacao', 'ALTER TABLE notas ADD COLUMN situacao VARCHAR(40) NULL AFTER participacao'],
            ['atividades_extracurriculares', 'categoria', 'ALTER TABLE atividades_extracurriculares ADD COLUMN categoria VARCHAR(80) NULL AFTER descricao'],
            ['atividades_extracurriculares', 'data_atividade', 'ALTER TABLE atividades_extracurriculares ADD COLUMN data_atividade DATE NULL AFTER categoria'],
            ['mensagens_internas', 'resposta_a_id', 'ALTER TABLE mensagens_internas ADD COLUMN resposta_a_id INT NULL AFTER destinatario_id'],
            ['comprovativos_pagamento', 'metodo_pagamento', "ALTER TABLE comprovativos_pagamento ADD COLUMN metodo_pagamento ENUM('referencia','autorizacao') NULL AFTER comprovativo_path"],
            ['comprovativos_pagamento', 'recibo_pdf', 'ALTER TABLE comprovativos_pagamento ADD COLUMN recibo_pdf VARCHAR(255) NULL AFTER metodo_pagamento']
        ];

        foreach ($alteracoes as [$tabela, $coluna, $sql]) {
            if ($this->colunaExiste($tabela, $coluna)) {
                continue;
            }

            try {
                $this->db->exec($sql);
            } catch (Throwable $erro) {
                
            }
        }

        try {
            $this->db->exec('ALTER TABLE mensagens_internas ADD INDEX idx_msg_interna_resposta (resposta_a_id)');
        } catch (Throwable $erro) {
            
        }

        try {
            $this->db->exec('ALTER TABLE mensagens_internas ADD CONSTRAINT fk_msg_interna_resposta FOREIGN KEY (resposta_a_id) REFERENCES mensagens_internas(id) ON DELETE SET NULL');
        } catch (Throwable $erro) {
        }

        $estruturasCriadas = true;
    }

}

