<?php

class PreMatriculaModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function inserir(array $dados, string $codigo): bool
    {
        $temCursoPretendido = $this->colunaExiste('pre_matriculas', 'curso_pretendido');

        try {
            if ($temCursoPretendido) {
                $sql = 'INSERT INTO pre_matriculas
                    (codigo, nome_encarregado, email_encarregado, telefone_encarregado, nome_aluno, data_nascimento_aluno, ano_pretendido, curso_pretendido, observacoes, status)
                    VALUES
                    (:codigo, :nome_encarregado, :email_encarregado, :telefone_encarregado, :nome_aluno, :data_nascimento_aluno, :ano_pretendido, :curso_pretendido, :observacoes, :status)';
            } else {
                $sql = 'INSERT INTO pre_matriculas
                    (codigo, nome_encarregado, email_encarregado, telefone_encarregado, nome_aluno, data_nascimento_aluno, ano_pretendido, observacoes, status)
                    VALUES
                    (:codigo, :nome_encarregado, :email_encarregado, :telefone_encarregado, :nome_aluno, :data_nascimento_aluno, :ano_pretendido, :observacoes, :status)';
            }

            $stmt = $this->db->prepare($sql);
            $params = [
                'codigo' => $codigo,
                'nome_encarregado' => $dados['nome_encarregado'],
                'email_encarregado' => $dados['email_encarregado'],
                'telefone_encarregado' => $dados['telefone_encarregado'],
                'nome_aluno' => $dados['nome_aluno'],
                'data_nascimento_aluno' => $dados['data_nascimento_aluno'],
                'ano_pretendido' => $dados['ano_pretendido'],
                'observacoes' => $dados['observacoes'] ?? null,
                'status' => 'pendente'
            ];

            if ($temCursoPretendido) {
                $params['curso_pretendido'] = $dados['curso_pretendido'] ?? null;
            }

            return $stmt->execute($params);
        } catch (Throwable $erro) {
            if ($temCursoPretendido) {
                $sql = 'INSERT INTO pre_matriculas
                    (codigo, nome_encarregado, email_encarregado, telefone_encarregado, nome_aluno, data_nascimento_aluno, ano_pretendido, curso_pretendido, status)
                    VALUES
                    (:codigo, :nome_encarregado, :email_encarregado, :telefone_encarregado, :nome_aluno, :data_nascimento_aluno, :ano_pretendido, :curso_pretendido, :status)';
            } else {
                $sql = 'INSERT INTO pre_matriculas
                    (codigo, nome_encarregado, email_encarregado, telefone_encarregado, nome_aluno, data_nascimento_aluno, ano_pretendido, status)
                    VALUES
                    (:codigo, :nome_encarregado, :email_encarregado, :telefone_encarregado, :nome_aluno, :data_nascimento_aluno, :ano_pretendido, :status)';
            }

            $stmt = $this->db->prepare($sql);
            $params = [
                'codigo' => $codigo,
                'nome_encarregado' => $dados['nome_encarregado'],
                'email_encarregado' => $dados['email_encarregado'],
                'telefone_encarregado' => $dados['telefone_encarregado'],
                'nome_aluno' => $dados['nome_aluno'],
                'data_nascimento_aluno' => $dados['data_nascimento_aluno'],
                'ano_pretendido' => $dados['ano_pretendido'],
                'status' => 'pendente'
            ];

            if ($temCursoPretendido) {
                $params['curso_pretendido'] = $dados['curso_pretendido'] ?? null;
            }

            return $stmt->execute($params);
        }
    }

    public function buscarPorCodigo(string $codigo): ?array
    {
        $sql = 'SELECT * FROM pre_matriculas WHERE codigo = :codigo LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);

        $registo = $stmt->fetch();
        return $registo ?: null;
    }

    public function existePorEmailEAluno(string $email, string $nomeAluno): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM pre_matriculas
                WHERE email_encarregado = :email
                  AND nome_aluno = :nome_aluno
                  AND status <> :cancelada';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'email' => $email,
            'nome_aluno' => $nomeAluno,
            'cancelada' => 'cancelada'
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function listarRecentes(int $limite = 10): array
    {
        $limite = max(1, $limite);
        $temCursoPretendido = $this->colunaExiste('pre_matriculas', 'curso_pretendido');
        $cursoSql = $temCursoPretendido ? 'curso_pretendido' : 'NULL AS curso_pretendido';

        $sql = "SELECT codigo, nome_encarregado, nome_aluno, ano_pretendido, {$cursoSql}, status, criado_em
                FROM pre_matriculas
                ORDER BY criado_em DESC
                LIMIT {$limite}";

        $stmt = $this->db->query($sql);
        $dados = $stmt->fetchAll();

        return !empty($dados) ? $dados : [];
    }

    public function gerarCodigoAtendimento(): string
    {
        $ano = date('Y');

        $sql = 'SELECT COUNT(*)
                FROM pre_matriculas
                WHERE YEAR(criado_em) = :ano';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ano' => $ano]);
        $sequencia = (int) $stmt->fetchColumn() + 1;

        return 'PRE-' . $ano . '-' . str_pad((string) $sequencia, 6, '0', STR_PAD_LEFT);
    }

    public function contarPorEstado(string $estado): int
    {
        $sql = 'SELECT COUNT(*) FROM pre_matriculas WHERE status = :status';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $estado]);

        return (int) $stmt->fetchColumn();
    }

    public function contarUltimosDias(int $dias): int
    {
        $dias = max(1, $dias);

        $sql = 'SELECT COUNT(*)
                FROM pre_matriculas
                WHERE criado_em >= DATE_SUB(NOW(), INTERVAL :dias DAY)';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
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
