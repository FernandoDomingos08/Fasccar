<?php

class MensagemSecretariaModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function inserir(array $dados): bool
    {
        try {
            $sql = 'INSERT INTO mensagens_secretaria (nome, email, assunto, mensagem, status)
                    VALUES (:nome, :email, :assunto, :mensagem, :status)';

            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                'nome' => $dados['nome'],
                'email' => $dados['email'],
                'assunto' => $dados['assunto'],
                'mensagem' => $dados['mensagem'],
                'status' => 'nova'
            ]);
        } catch (Throwable $erro) {
            return false;
        }
    }

    public function listarRecentes(int $limite = 10): array
    {
        $limite = max(1, $limite);

        try {
            $sql = "SELECT id, nome, email, assunto, mensagem, status, criado_em
                    FROM mensagens_secretaria
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";

            $stmt = $this->db->query($sql);
            $dados = $stmt->fetchAll();

            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
        }

        return [];
    }

    public function contarNaoLidas(): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM mensagens_secretaria WHERE status = 'nova'";
            return (int) $this->db->query($sql)->fetchColumn();
        } catch (Throwable $erro) {
            return 0;
        }
    }
}
