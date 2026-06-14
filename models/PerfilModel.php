<?php

class PerfilModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function obterPerfilCompleto(int $utilizadorId): array
    {
        $this->garantirPerfilUtilizador($utilizadorId);

        $sql = 'SELECT
                    u.id,
                    u.nome,
                    u.email,
                    u.perfil,
                    pu.foto,
                    pu.telefone,
                    pu.endereco,
                    pu.sobre_mim,
                    u.senha_temporaria,
                    u.ultimo_acesso
                FROM utilizadores u
                LEFT JOIN perfil_utilizador pu ON pu.utilizador_id = u.id
                WHERE u.id = :id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $utilizadorId]);
        $perfil = $stmt->fetch();

        if ($perfil) {
            return $perfil;
        }

        return [
            'id' => $utilizadorId,
            'nome' => (string) ($_SESSION['usuario_nome'] ?? 'Utilizador'),
            'email' => '',
            'perfil' => (string) ($_SESSION['perfil'] ?? ''),
            'foto' => null,
            'telefone' => null,
            'endereco' => null,
            'sobre_mim' => null,
            'senha_temporaria' => 0,
            'ultimo_acesso' => null
        ];
    }

    public function atualizarPerfil(int $utilizadorId, array $dados): bool
    {
        $this->garantirPerfilUtilizador($utilizadorId);

        $sqlUtilizador = 'UPDATE utilizadores SET nome = :nome WHERE id = :id';
        $stmtUtilizador = $this->db->prepare($sqlUtilizador);
        $okUtilizador = $stmtUtilizador->execute([
            'nome' => $dados['nome'],
            'id' => $utilizadorId
        ]);

        $sqlPerfil = 'UPDATE perfil_utilizador
                      SET telefone = :telefone,
                          endereco = :endereco,
                          sobre_mim = :sobre_mim,
                          foto = :foto
                      WHERE utilizador_id = :utilizador_id';

        $stmtPerfil = $this->db->prepare($sqlPerfil);
        $okPerfil = $stmtPerfil->execute([
            'telefone' => $dados['telefone'] !== '' ? $dados['telefone'] : null,
            'endereco' => $dados['endereco'] !== '' ? $dados['endereco'] : null,
            'sobre_mim' => $dados['sobre_mim'] !== '' ? $dados['sobre_mim'] : null,
            'foto' => $dados['foto'] !== '' ? $dados['foto'] : null,
            'utilizador_id' => $utilizadorId
        ]);

        return $okUtilizador && $okPerfil;
    }

    public function atualizarSenha(int $utilizadorId, string $senhaAtual, string $novaSenha): array
    {
        $sql = 'SELECT senha FROM utilizadores WHERE id = :id LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $utilizadorId]);
        $hashAtual = (string) $stmt->fetchColumn();

        if ($hashAtual === '' || !password_verify($senhaAtual, $hashAtual)) {
            return ['sucesso' => false, 'mensagem' => 'A senha atual esta incorreta.'];
        }

        $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $stmtUpdate = $this->db->prepare('UPDATE utilizadores SET senha = :senha, senha_temporaria = 0, senha_valida_ate = NULL, senha_ativa = 1 WHERE id = :id');
        $ok = $stmtUpdate->execute([
            'senha' => $novoHash,
            'id' => $utilizadorId
        ]);

        try {
            $stmtAluno = $this->db->prepare('UPDATE alunos SET senha_valida_ate = NULL, senha_ativa = 1 WHERE utilizador_id = :id');
            $stmtAluno->execute(['id' => $utilizadorId]);
        } catch (Throwable $erro) {
        }

        if (!$ok) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel atualizar a senha.'];
        }

        return ['sucesso' => true, 'mensagem' => 'Senha atualizada com sucesso.'];
    }

    public function listarHistorico(int $utilizadorId, int $limite = 10): array
    {
        $limite = max(1, $limite);

        try {
            $sql = "SELECT acao, detalhe, ip, criado_em
                    FROM historico_atividades
                    WHERE utilizador_id = :utilizador_id
                    ORDER BY criado_em DESC
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
                'acao' => 'Sem historico ainda',
                'detalhe' => 'As atividades recentes aparecerao aqui.',
                'ip' => '-',
                'criado_em' => date('Y-m-d H:i:s')
            ]
        ];
    }

    public function registarAtividade(int $utilizadorId, string $acao, string $detalhe = ''): void
    {
        try {
            $sql = 'INSERT INTO historico_atividades (utilizador_id, acao, detalhe, ip)
                    VALUES (:utilizador_id, :acao, :detalhe, :ip)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'utilizador_id' => $utilizadorId,
                'acao' => $acao,
                'detalhe' => $detalhe !== '' ? $detalhe : null,
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? null)
            ]);
        } catch (Throwable $erro) {
        }
    }

    private function garantirPerfilUtilizador(int $utilizadorId): void
    {
        try {
            $sql = 'INSERT INTO perfil_utilizador (utilizador_id)
                    VALUES (:utilizador_id)
                    ON DUPLICATE KEY UPDATE utilizador_id = VALUES(utilizador_id)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['utilizador_id' => $utilizadorId]);
        } catch (Throwable $erro) {
        }
    }
}
