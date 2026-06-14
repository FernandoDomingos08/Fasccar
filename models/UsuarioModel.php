<?php

class UsuarioModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function buscarPorEmail(string $email): ?array
    {
        $sql = 'SELECT * FROM utilizadores WHERE email = :email LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);

        $utilizador = $stmt->fetch();
        return $utilizador ?: null;
    }

    public function autenticar(string $email, string $senha): ?array
    {
        $utilizador = $this->buscarPorEmail($email);

        if ($utilizador && password_verify($senha, $utilizador['senha']) && !$this->senhaTemporariaExpirada($utilizador)) {
            return $utilizador;
        }

        return null;
    }

    public function senhaTemporariaExpirada(array $utilizador): bool
    {
        $ativa = (int) ($utilizador['senha_ativa'] ?? 1) === 1;
        if (!$ativa) {
            return true;
        }

        $dataValidaAte = trim((string) ($utilizador['senha_valida_ate'] ?? ''));
        if ($dataValidaAte === '') {
            return false;
        }

        $timestampExpira = strtotime($dataValidaAte);
        if ($timestampExpira === false) {
            return false;
        }

        return $timestampExpira < time();
    }

    public function guardarTokenRecuperacao(string $email, string $token, string $expira): bool
    {
        $sql = 'UPDATE utilizadores
                SET token_recuperacao = :token, token_expira = :expira
                WHERE email = :email';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'token' => $token,
            'expira' => $expira,
            'email' => $email
        ]);
    }

    public function validarTokenRecuperacao(string $token): bool
    {
        $sql = 'SELECT id
                FROM utilizadores
                WHERE token_recuperacao = :token
                  AND token_expira IS NOT NULL
                  AND token_expira > NOW()
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['token' => $token]);

        return (bool) $stmt->fetchColumn();
    }

    public function redefinirSenha(string $token, string $novaSenha): bool
    {
        $sql = 'SELECT id FROM utilizadores
                WHERE token_recuperacao = :token
                  AND token_expira > NOW()
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['token' => $token]);
        $utilizadorId = $stmt->fetchColumn();

        if (!$utilizadorId) {
            return false;
        }

        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);

        $sqlAtualizar = 'UPDATE utilizadores
                         SET senha = :senha,
                             token_recuperacao = NULL,
                             token_expira = NULL,
                             senha_temporaria = 0,
                             senha_valida_ate = NULL,
                             senha_ativa = 1
                         WHERE id = :id';

        $stmtAtualizar = $this->db->prepare($sqlAtualizar);
        return $stmtAtualizar->execute([
            'senha' => $hash,
            'id' => $utilizadorId
        ]);
    }

    public function atualizarUltimoAcesso(int $id): void
    {
        try {
            $sql = 'UPDATE utilizadores SET ultimo_acesso = NOW() WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
        } catch (Throwable $erro) {
        }
    }
}
