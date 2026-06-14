<?php

class DocumentoModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function listarAlunosDisponiveis(): array
    {
        try {
            $sql = 'SELECT
                        a.id,
                        u.nome AS aluno,
                        COALESCE(t.nome, "Sem turma") AS turma,
                        COALESCE(eu.nome, "Sem encarregado") AS encarregado
                    FROM alunos a
                    INNER JOIN utilizadores u ON u.id = a.utilizador_id
                    LEFT JOIN matriculas m ON m.aluno_id = a.id AND m.status = "activo"
                    LEFT JOIN turmas t ON t.id = m.turma_id
                    LEFT JOIN encarregado_aluno ea ON ea.aluno_id = a.id
                    LEFT JOIN encarregados e ON e.id = ea.encarregado_id
                    LEFT JOIN utilizadores eu ON eu.id = e.utilizador_id
                    ORDER BY u.nome';

            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function obterDadosAlunoDocumento(int $alunoId): ?array
    {
        try {
            $sql = 'SELECT
                        a.id,
                        u.nome AS nome_aluno,
                        a.bi,
                        a.data_nascimento,
                        COALESCE(t.nome, "Sem turma") AS turma,
                        t.ano_letivo,
                        COALESCE(eu.nome, "Sem encarregado") AS nome_encarregado
                    FROM alunos a
                    INNER JOIN utilizadores u ON u.id = a.utilizador_id
                    LEFT JOIN matriculas m ON m.aluno_id = a.id AND m.status = "activo"
                    LEFT JOIN turmas t ON t.id = m.turma_id
                    LEFT JOIN encarregado_aluno ea ON ea.aluno_id = a.id
                    LEFT JOIN encarregados e ON e.id = ea.encarregado_id
                    LEFT JOIN utilizadores eu ON eu.id = e.utilizador_id
                    WHERE a.id = :aluno_id
                    LIMIT 1';

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['aluno_id' => $alunoId]);
            $dados = $stmt->fetch();

            return $dados ?: null;
        } catch (Throwable $erro) {
            return null;
        }
    }

    public function obterNotasBoletim(int $alunoId): array
    {
        try {
            $sql = 'SELECT
                        d.nome AS disciplina,
                        MAX(CASE WHEN n.trimestre = 1 THEN n.nota END) AS trimestre_1,
                        MAX(CASE WHEN n.trimestre = 2 THEN n.nota END) AS trimestre_2,
                        MAX(CASE WHEN n.trimestre = 3 THEN n.nota END) AS trimestre_3,
                        ROUND(AVG(n.nota), 1) AS media
                    FROM matriculas m
                    INNER JOIN notas n ON n.matricula_id = m.id
                    INNER JOIN disciplinas d ON d.id = n.disciplina_id
                    WHERE m.aluno_id = :aluno_id
                    GROUP BY d.id, d.nome
                    ORDER BY d.nome';

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['aluno_id' => $alunoId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    public function obterResumoAcademico(int $alunoId): array
    {
        try {
            $sql = 'SELECT
                        ROUND(AVG(n.nota), 1) AS media_geral,
                        SUM(CASE WHEN p.presente = 0 THEN 1 ELSE 0 END) AS faltas,
                        COUNT(DISTINCT d.id) AS total_disciplinas
                    FROM matriculas m
                    LEFT JOIN notas n ON n.matricula_id = m.id
                    LEFT JOIN disciplinas d ON d.id = n.disciplina_id
                    LEFT JOIN presencas p ON p.matricula_id = m.id
                    WHERE m.aluno_id = :aluno_id';

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['aluno_id' => $alunoId]);
            $resumo = $stmt->fetch();

            return [
                'media_geral' => isset($resumo['media_geral']) ? (float) $resumo['media_geral'] : 0,
                'faltas' => isset($resumo['faltas']) ? (int) $resumo['faltas'] : 0,
                'total_disciplinas' => isset($resumo['total_disciplinas']) ? (int) $resumo['total_disciplinas'] : 0
            ];
        } catch (Throwable $erro) {
            return [
                'media_geral' => 0,
                'faltas' => 0,
                'total_disciplinas' => 0
            ];
        }
    }

    public function registarEmissao(string $tipoDocumento, int $alunoId, int $emitidoPor, string $nomeFicheiro): void
    {
        try {
            $sql = 'INSERT INTO documentos_emitidos (tipo_documento, aluno_id, emitido_por, nome_ficheiro)
                    VALUES (:tipo_documento, :aluno_id, :emitido_por, :nome_ficheiro)';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'tipo_documento' => $tipoDocumento,
                'aluno_id' => $alunoId,
                'emitido_por' => $emitidoPor,
                'nome_ficheiro' => $nomeFicheiro
            ]);
        } catch (Throwable $erro) {
        }
    }

    public function listarHistorico(int $limite = 12): array
    {
        $limite = max(1, $limite);

        try {
            $sql = "SELECT
                        d.tipo_documento,
                        d.nome_ficheiro,
                        d.criado_em,
                        ua.nome AS aluno,
                        ue.nome AS emitido_por
                    FROM documentos_emitidos d
                    INNER JOIN alunos a ON a.id = d.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    INNER JOIN utilizadores ue ON ue.id = d.emitido_por
                    ORDER BY d.criado_em DESC
                    LIMIT {$limite}";

            return $this->db->query($sql)->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }
}
