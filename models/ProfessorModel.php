<?php

class ProfessorModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function obterResumoProfessor(int $utilizadorId): array
    {
        try {
            if ($this->professorUsaAtribuicoes($utilizadorId)) {
                $sql = 'SELECT
                            COUNT(DISTINCT ptd.turma_id) AS total_turmas,
                            COUNT(DISTINCT m.aluno_id) AS total_alunos,
                            SUM(CASE WHEN n.id IS NULL THEN 1 ELSE 0 END) AS pendencias_lancamento
                        FROM professores p
                        INNER JOIN professor_turma_disciplinas ptd ON ptd.professor_id = p.id
                        LEFT JOIN matriculas m ON m.turma_id = ptd.turma_id AND m.status = "activo"
                        LEFT JOIN notas n
                          ON n.matricula_id = m.id
                         AND n.disciplina_id = ptd.disciplina_id
                         AND n.trimestre = 1
                        WHERE p.utilizador_id = :utilizador_id';
            } else {
                $sql = 'SELECT
                            COUNT(DISTINCT t.id) AS total_turmas,
                            COUNT(DISTINCT m.aluno_id) AS total_alunos,
                            SUM(CASE WHEN n.id IS NULL THEN 1 ELSE 0 END) AS pendencias_lancamento
                        FROM professores p
                        LEFT JOIN turmas t ON t.professor_id = p.id
                        LEFT JOIN matriculas m ON m.turma_id = t.id AND m.status = "activo"
                        LEFT JOIN notas n ON n.matricula_id = m.id AND n.trimestre = 1
                        WHERE p.utilizador_id = :utilizador_id';
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $dados = $stmt->fetch();

            if ($dados) {
                return [
                    'total_turmas' => (int) ($dados['total_turmas'] ?? 0),
                    'total_alunos' => (int) ($dados['total_alunos'] ?? 0),
                    'pendencias_lancamento' => max(0, (int) ($dados['pendencias_lancamento'] ?? 0))
                ];
            }
        } catch (Throwable $erro) {
        }

        return [
            'total_turmas' => 0,
            'total_alunos' => 0,
            'pendencias_lancamento' => 0
        ];
    }

    public function listarTurmasProfessor(int $utilizadorId): array
    {
        try {
            if ($this->professorUsaAtribuicoes($utilizadorId)) {
                $sql = 'SELECT
                            t.id,
                            t.nome,
                            t.ano_letivo,
                            COUNT(DISTINCT m.id) AS total_matriculas,
                            GROUP_CONCAT(DISTINCT d.nome ORDER BY d.nome SEPARATOR ", ") AS disciplinas
                        FROM professores p
                        INNER JOIN professor_turma_disciplinas ptd ON ptd.professor_id = p.id
                        INNER JOIN turmas t ON t.id = ptd.turma_id
                        LEFT JOIN matriculas m ON m.turma_id = t.id AND m.status = "activo"
                        LEFT JOIN disciplinas d ON d.id = ptd.disciplina_id
                        WHERE p.utilizador_id = :utilizador_id
                        GROUP BY t.id, t.nome, t.ano_letivo
                        ORDER BY t.nome';
            } else {
                $sql = 'SELECT
                            t.id,
                            t.nome,
                            t.ano_letivo,
                            COUNT(m.id) AS total_matriculas,
                            NULL AS disciplinas
                        FROM professores p
                        INNER JOIN turmas t ON t.professor_id = p.id
                        LEFT JOIN matriculas m ON m.turma_id = t.id AND m.status = "activo"
                        WHERE p.utilizador_id = :utilizador_id
                        GROUP BY t.id, t.nome, t.ano_letivo
                        ORDER BY t.nome';
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $dados = $stmt->fetchAll();

            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
        }

        return [];
    }

    public function listarLancamentosRecentes(int $utilizadorId, int $limite = 10): array
    {
        $limite = max(1, $limite);

        try {
            if ($this->professorUsaAtribuicoes($utilizadorId)) {
                $sql = "SELECT
                            d.nome AS disciplina,
                            n.trimestre,
                            n.nota,
                            n.data_lancamento,
                            ua.nome AS aluno,
                            t.nome AS turma
                        FROM professores p
                        INNER JOIN professor_turma_disciplinas ptd ON ptd.professor_id = p.id
                        INNER JOIN turmas t ON t.id = ptd.turma_id
                        INNER JOIN matriculas m ON m.turma_id = ptd.turma_id AND m.status = 'activo'
                        INNER JOIN alunos a ON a.id = m.aluno_id
                        INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                        INNER JOIN notas n
                          ON n.matricula_id = m.id
                         AND n.disciplina_id = ptd.disciplina_id
                        INNER JOIN disciplinas d ON d.id = n.disciplina_id
                        WHERE p.utilizador_id = :utilizador_id
                        GROUP BY n.id, d.nome, n.trimestre, n.nota, n.data_lancamento, ua.nome, t.nome
                        ORDER BY n.data_lancamento DESC
                        LIMIT {$limite}";
            } else {
                $sql = "SELECT
                            d.nome AS disciplina,
                            n.trimestre,
                            n.nota,
                            n.data_lancamento,
                            ua.nome AS aluno,
                            t.nome AS turma
                        FROM professores p
                        INNER JOIN turmas t ON t.professor_id = p.id
                        INNER JOIN matriculas m ON m.turma_id = t.id
                        INNER JOIN alunos a ON a.id = m.aluno_id
                        INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                        INNER JOIN notas n ON n.matricula_id = m.id
                        INNER JOIN disciplinas d ON d.id = n.disciplina_id
                        WHERE p.utilizador_id = :utilizador_id
                        ORDER BY n.data_lancamento DESC
                        LIMIT {$limite}";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $dados = $stmt->fetchAll();

            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
        }

        return [];
    }

    public function obterAnaliticaProfessor(int $utilizadorId): array
    {
        $analitica = [
            'medias_turma' => [
                'etiquetas' => [],
                'valores' => []
            ],
            'distribuicao_notas' => [
                'etiquetas' => ['0-9', '10-13', '14-17', '18-20'],
                'valores' => [0, 0, 0, 0]
            ],
            'alunos_risco' => [],
            'sugestoes' => []
        ];

        $turmasIds = $this->obterTurmasProfessorIds($utilizadorId);
        if (empty($turmasIds)) {
            $analitica['sugestoes'][] = 'Sem turmas atribuidas no momento.';
            return $analitica;
        }

        $filtroTurmas = $this->criarParametrosListaInteiros($turmasIds, 'turma_');
        if ($filtroTurmas['sql'] === '') {
            $analitica['sugestoes'][] = 'Sem turmas atribuidas no momento.';
            return $analitica;
        }

        try {
            $sql = "SELECT
                        t.nome,
                        ROUND(COALESCE(AVG(n.nota), 0), 1) AS media
                    FROM turmas t
                    LEFT JOIN matriculas m ON m.turma_id = t.id AND m.status = 'activo'
                    LEFT JOIN notas n ON n.matricula_id = m.id
                    WHERE t.id IN ({$filtroTurmas['sql']})
                    GROUP BY t.id, t.nome
                    ORDER BY t.nome ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($filtroTurmas['params']);
            $linhas = $stmt->fetchAll();

            foreach ($linhas as $linha) {
                $analitica['medias_turma']['etiquetas'][] = (string) ($linha['nome'] ?? 'Turma');
                $analitica['medias_turma']['valores'][] = (float) ($linha['media'] ?? 0);
            }
        } catch (Throwable $erro) {
        }

        try {
            $sql = "SELECT
                        SUM(CASE WHEN n.nota < 10 THEN 1 ELSE 0 END) AS baixa,
                        SUM(CASE WHEN n.nota >= 10 AND n.nota < 14 THEN 1 ELSE 0 END) AS regular,
                        SUM(CASE WHEN n.nota >= 14 AND n.nota < 18 THEN 1 ELSE 0 END) AS boa,
                        SUM(CASE WHEN n.nota >= 18 THEN 1 ELSE 0 END) AS excelente
                    FROM matriculas m
                    LEFT JOIN notas n ON n.matricula_id = m.id
                    WHERE m.status = 'activo'
                      AND m.turma_id IN ({$filtroTurmas['sql']})";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($filtroTurmas['params']);
            $dados = $stmt->fetch();

            if ($dados) {
                $analitica['distribuicao_notas']['valores'] = [
                    (int) ($dados['baixa'] ?? 0),
                    (int) ($dados['regular'] ?? 0),
                    (int) ($dados['boa'] ?? 0),
                    (int) ($dados['excelente'] ?? 0)
                ];
            }
        } catch (Throwable $erro) {
        }

        try {
            $sql = "SELECT
                        ua.nome AS aluno,
                        t.nome AS turma,
                        ROUND(COALESCE(notas_aluno.media, 0), 1) AS media,
                        COALESCE(presencas_aluno.faltas, 0) AS faltas
                    FROM matriculas m
                    INNER JOIN turmas t ON t.id = m.turma_id
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    LEFT JOIN (
                        SELECT matricula_id, AVG(nota) AS media
                        FROM notas
                        GROUP BY matricula_id
                    ) notas_aluno ON notas_aluno.matricula_id = m.id
                    LEFT JOIN (
                        SELECT matricula_id, SUM(CASE WHEN presente = 0 THEN 1 ELSE 0 END) AS faltas
                        FROM presencas
                        GROUP BY matricula_id
                    ) presencas_aluno ON presencas_aluno.matricula_id = m.id
                    WHERE m.status = 'activo'
                      AND m.turma_id IN ({$filtroTurmas['sql']})
                      AND (
                        COALESCE(notas_aluno.media, 0) < 10
                        OR COALESCE(presencas_aluno.faltas, 0) >= 3
                      )
                    ORDER BY media ASC, faltas DESC, ua.nome ASC
                    LIMIT 5";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($filtroTurmas['params']);
            $analitica['alunos_risco'] = $stmt->fetchAll();
        } catch (Throwable $erro) {
            $analitica['alunos_risco'] = [];
        }

        if (!empty($analitica['alunos_risco'])) {
            $primeiro = $analitica['alunos_risco'][0];
            $analitica['sugestoes'][] = 'Dar seguimento imediato a ' . ($primeiro['aluno'] ?? 'um aluno') . ' da turma ' . ($primeiro['turma'] ?? '-') . '.';
        }

        $totalBaixa = (int) ($analitica['distribuicao_notas']['valores'][0] ?? 0);
        if ($totalBaixa > 0) {
            $analitica['sugestoes'][] = 'Rever os lancamentos com notas abaixo de 10 e planear recuperacao.';
        }

        if (empty($analitica['sugestoes'])) {
            $analitica['sugestoes'][] = 'Indicadores da turma estaveis. Continue a actualizar notas e presencas semanalmente.';
        }

        return $analitica;
    }

    private function professorUsaAtribuicoes(int $utilizadorId): bool
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
            if ($this->professorUsaAtribuicoes($utilizadorId)) {
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
}
