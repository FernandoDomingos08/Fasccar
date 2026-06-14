<?php

class TurmaModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function listarTurmasAtivas(int $limite = 8): array
    {
        $limite = max(1, $limite);

        try {
            $sql = "SELECT
                        t.nome,
                        t.ano_letivo,
                        COUNT(m.id) AS total_matriculas,
                        u.nome AS professor
                    FROM turmas t
                    LEFT JOIN matriculas m ON m.turma_id = t.id AND m.status = 'activo'
                    LEFT JOIN professores p ON p.id = t.professor_id
                    LEFT JOIN utilizadores u ON u.id = p.utilizador_id
                    GROUP BY t.id, t.nome, t.ano_letivo, u.nome
                    ORDER BY t.nome
                    LIMIT {$limite}";

            $stmt = $this->db->query($sql);
            $dados = $stmt->fetchAll();

            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
        }

        return [
            [
                'nome' => 'Sem turmas ativas',
                'ano_letivo' => date('Y') . '/' . (date('Y') + 1),
                'total_matriculas' => 0,
                'professor' => 'Sem professor'
            ]
        ];
    }
}
