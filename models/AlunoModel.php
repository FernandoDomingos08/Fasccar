<?php

class AlunoModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function obterResumoPainel(int $utilizadorId): array
    {
        try {
            $sql = 'SELECT
                        u.nome AS nome_aluno,
                        COALESCE(t.nome, "Sem turma") AS turma,
                        ROUND(COALESCE(notas_aluno.media_geral, 0), 1) AS media_geral,
                        COALESCE(presencas_aluno.faltas_total, 0) AS faltas_total,
                        COALESCE(pagamentos_aluno.pendencias, 0) AS pendencias
                    FROM alunos a
                    INNER JOIN utilizadores u ON u.id = a.utilizador_id
                    LEFT JOIN matriculas m ON m.aluno_id = a.id AND m.status = "activo"
                    LEFT JOIN turmas t ON t.id = m.turma_id
                    LEFT JOIN (
                        SELECT matricula_id, AVG(nota) AS media_geral
                        FROM notas
                        GROUP BY matricula_id
                    ) notas_aluno ON notas_aluno.matricula_id = m.id
                    LEFT JOIN (
                        SELECT matricula_id, SUM(CASE WHEN presente = 0 THEN 1 ELSE 0 END) AS faltas_total
                        FROM presencas
                        GROUP BY matricula_id
                    ) presencas_aluno ON presencas_aluno.matricula_id = m.id
                    LEFT JOIN (
                        SELECT matricula_id, SUM(CASE WHEN status IN ("pendente", "atrasado") THEN 1 ELSE 0 END) AS pendencias
                        FROM pagamentos
                        GROUP BY matricula_id
                    ) pagamentos_aluno ON pagamentos_aluno.matricula_id = m.id
                    WHERE a.utilizador_id = :utilizador_id
                    GROUP BY u.nome, t.nome, notas_aluno.media_geral, presencas_aluno.faltas_total, pagamentos_aluno.pendencias';

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $resumo = $stmt->fetch();

            if (is_array($resumo)) {
                $resumo['media_geral'] = $resumo['media_geral'] !== null ? (float) $resumo['media_geral'] : 0.0;
                $resumo['faltas_total'] = (int) ($resumo['faltas_total'] ?? 0);
                $resumo['pendencias'] = (int) ($resumo['pendencias'] ?? 0);
                return $resumo;
            }
        } catch (Throwable $erro) {
        }

        return [
            'nome_aluno' => $_SESSION['usuario_nome'] ?? 'Aluno',
            'turma' => 'Sem turma atribuida',
            'media_geral' => 0.0,
            'faltas_total' => 0,
            'pendencias' => 0
        ];
    }

    public function obterNotasTrimestrais(int $utilizadorId): array
    {
        try {
            $sql = 'SELECT
                        d.nome AS disciplina,
                        MAX(CASE WHEN n.trimestre = 1 THEN n.nota END) AS t1,
                        MAX(CASE WHEN n.trimestre = 2 THEN n.nota END) AS t2,
                        MAX(CASE WHEN n.trimestre = 3 THEN n.nota END) AS t3,
                        ROUND(AVG(n.nota), 1) AS media
                    FROM alunos a
                    INNER JOIN matriculas m ON m.aluno_id = a.id
                    INNER JOIN notas n ON n.matricula_id = m.id
                    INNER JOIN disciplinas d ON d.id = n.disciplina_id
                    WHERE a.utilizador_id = :utilizador_id
                    GROUP BY d.id, d.nome
                    ORDER BY d.nome ASC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function obterDadosGrafico(int $utilizadorId): array
    {
        $resultado = [
            'etiquetas' => [],
            'valores' => [],
            'presencas' => []
        ];

        try {
            $sql = 'SELECT trimestre, ROUND(AVG(nota), 1) AS media
                    FROM notas n
                    INNER JOIN matriculas m ON m.id = n.matricula_id
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    WHERE a.utilizador_id = :utilizador_id
                    GROUP BY trimestre
                    ORDER BY trimestre';

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $linhas = $stmt->fetchAll() ?: [];

            foreach ($linhas as $linha) {
                $resultado['etiquetas'][] = (string) ($linha['trimestre'] ?? '') . 'o Trimestre';
                $resultado['valores'][] = (float) ($linha['media'] ?? 0);
            }
        } catch (Throwable $erro) {
        }

        try {
            $temJustificativa = $this->colunaExiste('presencas', 'justificativa');
            $temStatus = $this->colunaExiste('presencas', 'justificativa_status');
            $temDisciplina = $this->colunaExiste('presencas', 'disciplina_id');
            $justificativaSql = $temJustificativa ? 'p.justificativa' : 'NULL';
            $statusSql = $temStatus ? 'p.justificativa_status' : "'sem_estado'";
            $disciplinaSql = $temDisciplina ? 'd.nome' : 'NULL';

            $sql = "SELECT
                        p.id,
                        p.data,
                        p.presente,
                        {$justificativaSql} AS justificativa,
                        {$statusSql} AS justificativa_status,
                        {$disciplinaSql} AS disciplina
                    FROM alunos a
                    INNER JOIN matriculas m ON m.aluno_id = a.id
                    INNER JOIN presencas p ON p.matricula_id = m.id
                    LEFT JOIN disciplinas d ON d.id = p.disciplina_id
                    WHERE a.utilizador_id = :utilizador_id
                    ORDER BY p.data DESC
                    LIMIT 180";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $resultado['presencas'] = $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            $resultado['presencas'] = [];
        }

        return $resultado;
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
}
