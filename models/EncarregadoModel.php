<?php

class EncarregadoModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function listarEducandos(int $utilizadorId): array
    {
        try {
            $sql = 'SELECT
                        a.id AS aluno_id,
                        ua.nome AS nome_aluno,
                        COALESCE(t.nome, "Sem turma") AS turma,
                        ROUND(AVG(n.nota), 1) AS media_geral
                    FROM encarregados e
                    INNER JOIN encarregado_aluno ea ON ea.encarregado_id = e.id
                    INNER JOIN alunos a ON a.id = ea.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    LEFT JOIN matriculas m ON m.aluno_id = a.id AND m.status = "activo"
                    LEFT JOIN turmas t ON t.id = m.turma_id
                    LEFT JOIN notas n ON n.matricula_id = m.id
                    WHERE e.utilizador_id = :utilizador_id
                    GROUP BY a.id, ua.nome, t.nome
                    ORDER BY ua.nome';

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $dados = $stmt->fetchAll();

            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
        }

        return [
            [
                'aluno_id' => 0,
                'nome_aluno' => 'Educando sem registo',
                'turma' => 'Sem turma',
                'media_geral' => 0.0
            ]
        ];
    }

    public function obterResumoFinanceiro(int $utilizadorId): array
    {
        try {
            $sql = 'SELECT
                        SUM(CASE WHEN pg.status = "pago" THEN pg.valor ELSE 0 END) AS total_pago,
                        SUM(CASE WHEN pg.status IN ("pendente", "atrasado") THEN pg.valor ELSE 0 END) AS total_pendente,
                        SUM(CASE WHEN pg.status <> "pago" THEN 1 ELSE 0 END) AS registos_abertos
                    FROM encarregados e
                    INNER JOIN encarregado_aluno ea ON ea.encarregado_id = e.id
                    INNER JOIN matriculas m ON m.aluno_id = ea.aluno_id
                    INNER JOIN pagamentos pg ON pg.matricula_id = m.id
                    WHERE e.utilizador_id = :utilizador_id';

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $resumo = $stmt->fetch();

            if ($resumo) {
                return [
                    'total_pago' => (float) ($resumo['total_pago'] ?? 0),
                    'total_pendente' => (float) ($resumo['total_pendente'] ?? 0),
                    'registos_abertos' => (int) ($resumo['registos_abertos'] ?? 0)
                ];
            }
        } catch (Throwable $erro) {
        }

        return [
            'total_pago' => 0.0,
            'total_pendente' => 0.0,
            'registos_abertos' => 0
        ];
    }

    public function listarPagamentosEducandos(int $utilizadorId, int $limite = 8): array
    {
        $limite = max(1, $limite);

        try {
            $sql = "SELECT
                        ua.nome AS aluno,
                        pg.descricao,
                        pg.valor,
                        pg.data_vencimento,
                        pg.status
                    FROM encarregados e
                    INNER JOIN encarregado_aluno ea ON ea.encarregado_id = e.id
                    INNER JOIN alunos a ON a.id = ea.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    INNER JOIN matriculas m ON m.aluno_id = a.id
                    INNER JOIN pagamentos pg ON pg.matricula_id = m.id
                    WHERE e.utilizador_id = :utilizador_id
                    ORDER BY pg.data_vencimento ASC
                    LIMIT {$limite}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $dados = $stmt->fetchAll();

            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
        }

        return [
            [
                'aluno' => 'Educando sem registo',
                'descricao' => 'Sem pagamentos lançados',
                'valor' => 0,
                'data_vencimento' => date('Y-m-d'),
                'status' => 'pendente'
            ]
        ];
    }

    public function obterAnaliticaEncarregado(int $utilizadorId): array
    {
        $analitica = [
            'educandos' => [],
            'pagamentos_status' => [
                'etiquetas' => ['Pago', 'Pendente', 'Atrasado'],
                'valores' => [0, 0, 0]
            ],
            'alertas' => []
        ];

        try {
            $sql = "SELECT
                        ua.nome AS educando,
                        COALESCE(t.nome, 'Sem turma') AS turma,
                        ROUND(COALESCE(notas_aluno.media, 0), 1) AS media_educando,
                        ROUND(COALESCE(media_turma.media_turma, 0), 1) AS media_turma,
                        COALESCE(presencas_aluno.faltas, 0) AS faltas
                    FROM encarregados e
                    INNER JOIN encarregado_aluno ea ON ea.encarregado_id = e.id
                    INNER JOIN alunos a ON a.id = ea.aluno_id
                    INNER JOIN utilizadores ua ON ua.id = a.utilizador_id
                    LEFT JOIN matriculas m ON m.aluno_id = a.id AND m.status = 'activo'
                    LEFT JOIN turmas t ON t.id = m.turma_id
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
                    LEFT JOIN (
                        SELECT
                            m2.turma_id,
                            ROUND(COALESCE(AVG(n2.nota), 0), 1) AS media_turma
                        FROM matriculas m2
                        LEFT JOIN notas n2 ON n2.matricula_id = m2.id
                        WHERE m2.status = 'activo'
                        GROUP BY m2.turma_id
                    ) media_turma ON media_turma.turma_id = m.turma_id
                    WHERE e.utilizador_id = :utilizador_id
                    ORDER BY ua.nome ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $analitica['educandos'] = $stmt->fetchAll();
        } catch (Throwable $erro) {
            $analitica['educandos'] = [];
        }

        try {
            $sql = "SELECT
                        SUM(CASE WHEN pg.status = 'pago' THEN 1 ELSE 0 END) AS pago,
                        SUM(CASE WHEN pg.status = 'pendente' THEN 1 ELSE 0 END) AS pendente,
                        SUM(CASE WHEN pg.status = 'atrasado' THEN 1 ELSE 0 END) AS atrasado
                    FROM encarregados e
                    INNER JOIN encarregado_aluno ea ON ea.encarregado_id = e.id
                    INNER JOIN matriculas m ON m.aluno_id = ea.aluno_id
                    INNER JOIN pagamentos pg ON pg.matricula_id = m.id
                    WHERE e.utilizador_id = :utilizador_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            $dados = $stmt->fetch();

            if ($dados) {
                $analitica['pagamentos_status']['valores'] = [
                    (int) ($dados['pago'] ?? 0),
                    (int) ($dados['pendente'] ?? 0),
                    (int) ($dados['atrasado'] ?? 0)
                ];
            }
        } catch (Throwable $erro) {
        }

        foreach ($analitica['educandos'] as $educando) {
            $media = (float) ($educando['media_educando'] ?? 0);
            $faltas = (int) ($educando['faltas'] ?? 0);
            $mediaTurma = (float) ($educando['media_turma'] ?? 0);

            if ($media < 10) {
                $analitica['alertas'][] = [
                    'nivel' => 'alto',
                    'titulo' => 'Desempenho academico em risco',
                    'texto' => ($educando['educando'] ?? 'Educando') . ' esta abaixo da media minima.'
                ];
                continue;
            }

            if ($faltas >= 3) {
                $analitica['alertas'][] = [
                    'nivel' => 'medio',
                    'titulo' => 'Faltas a acompanhar',
                    'texto' => ($educando['educando'] ?? 'Educando') . ' acumula ' . $faltas . ' falta(s) registada(s).'
                ];
                continue;
            }

            if ($mediaTurma > 0 && $media < $mediaTurma) {
                $analitica['alertas'][] = [
                    'nivel' => 'baixo',
                    'titulo' => 'Apoio recomendado',
                    'texto' => ($educando['educando'] ?? 'Educando') . ' esta abaixo da media da turma e pode beneficiar de reforco.'
                ];
            }
        }

        if (($analitica['pagamentos_status']['valores'][2] ?? 0) > 0) {
            $analitica['alertas'][] = [
                'nivel' => 'alto',
                'titulo' => 'Pagamentos em atraso',
                'texto' => $analitica['pagamentos_status']['valores'][2] . ' pagamento(s) estao com atraso e exigem regularizacao.'
            ];
        }

        if (empty($analitica['educandos'])) {
            $analitica['educandos'][] = [
                'educando' => 'Sem dados',
                'turma' => 'Sem turma',
                'media_educando' => 0,
                'media_turma' => 0,
                'faltas' => 0
            ];
        }

        if (empty($analitica['alertas'])) {
            $analitica['alertas'][] = [
                'nivel' => 'baixo',
                'titulo' => 'Sem alertas imediatos',
                'texto' => 'Os educandos associados estao sem ocorrencias criticas neste momento.'
            ];
        }

        return $analitica;
    }
}
