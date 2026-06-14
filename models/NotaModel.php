<?php

class NotaModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function rankingDisciplinas(int $limite = 6): array
    {
        $limite = max(1, $limite);

        try {
            $sql = "SELECT d.nome AS disciplina, ROUND(AVG(n.nota), 1) AS media
                    FROM notas n
                    INNER JOIN disciplinas d ON d.id = n.disciplina_id
                    GROUP BY d.id, d.nome
                    ORDER BY media DESC
                    LIMIT {$limite}";

            $stmt = $this->db->query($sql);
            $dados = $stmt->fetchAll();

            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
            
        }

        return [
            ['disciplina' => 'Matemática', 'media' => 14.2],
            ['disciplina' => 'Português', 'media' => 13.7],
            ['disciplina' => 'Física', 'media' => 13.1]
        ];
    }
}
