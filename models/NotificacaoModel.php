<?php

class NotificacaoModel
{
    private PDO $db;
    private static bool $estruturaGarantida = false;

    public function __construct()
    {
        $this->db = Database::getInstancia();
        $this->garantirEstrutura();
    }

    public function resumoPainel(int $utilizadorId, string $perfil, int $limite = 20): array
    {
        $itens = $this->listarPendentes($utilizadorId, $perfil, $limite);

        return [
            'total' => count($itens),
            'itens' => $itens
        ];
    }

    public function contarPendentes(int $utilizadorId, string $perfil): int
    {
        if ($utilizadorId <= 0) {
            return 0;
        }

        $destinatarioAvisos = $this->destinatarioAvisos($perfil);
        $perfilDestino = $this->perfilMensagens($perfil);
        $total = 0;

        try {
            $stmtAvisos = $this->db->prepare(
                "SELECT COUNT(*)
                 FROM avisos a
                 LEFT JOIN avisos_lidos al
                    ON al.aviso_id = a.id
                   AND al.utilizador_id = :utilizador_id
                 WHERE al.id IS NULL
                   AND a.destinatarios IN ('todos', :destinatario)
                   AND (a.data_inicio IS NULL OR a.data_inicio <= NOW())
                   AND (a.data_fim IS NULL OR a.data_fim >= NOW())"
            );
            $stmtAvisos->execute([
                'utilizador_id' => $utilizadorId,
                'destinatario' => $destinatarioAvisos
            ]);
            $total += (int) $stmtAvisos->fetchColumn();
        } catch (Throwable $erro) {
        }

        try {
            $stmtMensagens = $this->db->prepare(
                "SELECT COUNT(*)
                 FROM mensagens_internas m
                 LEFT JOIN mensagens_internas_lidas ml
                    ON ml.mensagem_id = m.id
                   AND ml.utilizador_id = :utilizador_id
                 WHERE m.remetente_id <> :utilizador_id
                   AND (
                        (m.destinatario_id = :utilizador_id AND m.status = 'nao_lida')
                        OR
                        (m.destinatario_id IS NULL AND m.perfil_destino IN ('todos', :perfil_destino) AND ml.id IS NULL)
                   )"
            );
            $stmtMensagens->execute([
                'utilizador_id' => $utilizadorId,
                'perfil_destino' => $perfilDestino
            ]);
            $total += (int) $stmtMensagens->fetchColumn();
        } catch (Throwable $erro) {
        }

        return max(0, $total);
    }

    public function listarPendentes(int $utilizadorId, string $perfil, int $limite = 20): array
    {
        if ($utilizadorId <= 0) {
            return [];
        }

        $limite = max(1, $limite);
        $destinatarioAvisos = $this->destinatarioAvisos($perfil);
        $perfilDestino = $this->perfilMensagens($perfil);
        $itens = [];

        try {
            $sql = "SELECT
                        a.id,
                        a.titulo,
                        a.mensagem,
                        a.criado_em
                    FROM avisos a
                    LEFT JOIN avisos_lidos al
                        ON al.aviso_id = a.id
                       AND al.utilizador_id = :utilizador_id
                    WHERE al.id IS NULL
                      AND a.destinatarios IN ('todos', :destinatario)
                      AND (a.data_inicio IS NULL OR a.data_inicio <= NOW())
                      AND (a.data_fim IS NULL OR a.data_fim >= NOW())
                    ORDER BY a.criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'utilizador_id' => $utilizadorId,
                'destinatario' => $destinatarioAvisos
            ]);

            foreach (($stmt->fetchAll() ?: []) as $linha) {
                $itens[] = [
                    'tipo' => 'aviso',
                    'origem_id' => (int) ($linha['id'] ?? 0),
                    'titulo' => (string) ($linha['titulo'] ?? 'Aviso'),
                    'texto' => (string) ($linha['mensagem'] ?? ''),
                    'mensagem' => (string) ($linha['mensagem'] ?? ''),
                    'data' => (string) ($linha['criado_em'] ?? ''),
                    'link' => $this->rotaNotificacaoPorPerfil($perfil),
                    'remetente' => ''
                ];
            }
        } catch (Throwable $erro) {
        }

        try {
            $sql = "SELECT
                        m.id,
                        m.assunto,
                        m.mensagem,
                        m.criado_em,
                        m.destinatario_id,
                        m.status,
                        u.nome AS remetente_nome,
                        ml.id AS leitura_id
                    FROM mensagens_internas m
                    LEFT JOIN utilizadores u ON u.id = m.remetente_id
                    LEFT JOIN mensagens_internas_lidas ml
                        ON ml.mensagem_id = m.id
                       AND ml.utilizador_id = :utilizador_id
                    WHERE m.remetente_id <> :utilizador_id
                      AND (
                           (m.destinatario_id = :utilizador_id AND m.status = 'nao_lida')
                           OR
                           (m.destinatario_id IS NULL AND m.perfil_destino IN ('todos', :perfil_destino) AND ml.id IS NULL)
                      )
                    ORDER BY m.criado_em DESC
                    LIMIT {$limite}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'utilizador_id' => $utilizadorId,
                'perfil_destino' => $perfilDestino
            ]);

            foreach (($stmt->fetchAll() ?: []) as $linha) {
                $itens[] = [
                    'tipo' => 'mensagem',
                    'origem_id' => (int) ($linha['id'] ?? 0),
                    'titulo' => (string) ($linha['assunto'] ?? 'Mensagem'),
                    'texto' => (string) ($linha['mensagem'] ?? ''),
                    'mensagem' => (string) ($linha['mensagem'] ?? ''),
                    'data' => (string) ($linha['criado_em'] ?? ''),
                    'link' => $this->rotaNotificacaoPorPerfil($perfil),
                    'remetente' => (string) ($linha['remetente_nome'] ?? '')
                ];
            }
        } catch (Throwable $erro) {
        }

        usort($itens, static function (array $a, array $b): int {
            return strcmp((string) ($b['data'] ?? ''), (string) ($a['data'] ?? ''));
        });

        if (count($itens) > $limite) {
            $itens = array_slice($itens, 0, $limite);
        }

        return $itens;
    }

    public function marcarComoLida(int $utilizadorId, string $perfil, string $tipo, int $origemId): bool
    {
        if ($utilizadorId <= 0 || $origemId <= 0) {
            return false;
        }

        $tipo = strtolower(trim($tipo));
        if ($tipo === 'aviso') {
            try {
                $stmt = $this->db->prepare(
                    'INSERT IGNORE INTO avisos_lidos (aviso_id, utilizador_id, lido_em)
                     VALUES (:aviso_id, :utilizador_id, NOW())'
                );
                return $stmt->execute([
                    'aviso_id' => $origemId,
                    'utilizador_id' => $utilizadorId
                ]);
            } catch (Throwable $erro) {
                return false;
            }
        }

        if ($tipo !== 'mensagem') {
            return false;
        }

        $perfilDestino = $this->perfilMensagens($perfil);

        try {
            $stmt = $this->db->prepare(
                'SELECT id, destinatario_id, perfil_destino
                 FROM mensagens_internas
                 WHERE id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $origemId]);
            $mensagem = $stmt->fetch();
            if (!is_array($mensagem)) {
                return false;
            }

            $destinatarioId = (int) ($mensagem['destinatario_id'] ?? 0);
            $perfilLinha = (string) ($mensagem['perfil_destino'] ?? '');

            if ($destinatarioId === $utilizadorId) {
                $stmtUpdate = $this->db->prepare(
                    "UPDATE mensagens_internas
                     SET status = 'lida'
                     WHERE id = :id
                       AND destinatario_id = :utilizador_id"
                );
                return $stmtUpdate->execute([
                    'id' => $origemId,
                    'utilizador_id' => $utilizadorId
                ]);
            }

            if ($destinatarioId === 0 && in_array($perfilLinha, ['todos', $perfilDestino], true)) {
                $stmtRead = $this->db->prepare(
                    'INSERT IGNORE INTO mensagens_internas_lidas (mensagem_id, utilizador_id, lido_em)
                     VALUES (:mensagem_id, :utilizador_id, NOW())'
                );
                return $stmtRead->execute([
                    'mensagem_id' => $origemId,
                    'utilizador_id' => $utilizadorId
                ]);
            }
        } catch (Throwable $erro) {
            return false;
        }

        return false;
    }

    public function marcarTodasComoLidas(int $utilizadorId, string $perfil): bool
    {
        if ($utilizadorId <= 0) {
            return false;
        }

        $perfilDestino = $this->perfilMensagens($perfil);
        $destinatarioAvisos = $this->destinatarioAvisos($perfil);

        try {
            $this->db->beginTransaction();

            $stmtAvisos = $this->db->prepare(
                "INSERT IGNORE INTO avisos_lidos (aviso_id, utilizador_id, lido_em)
                 SELECT a.id, :utilizador_id, NOW()
                 FROM avisos a
                 WHERE a.destinatarios IN ('todos', :destinatario)
                   AND (a.data_inicio IS NULL OR a.data_inicio <= NOW())
                   AND (a.data_fim IS NULL OR a.data_fim >= NOW())"
            );
            $stmtAvisos->execute([
                'utilizador_id' => $utilizadorId,
                'destinatario' => $destinatarioAvisos
            ]);

            $stmtDiretas = $this->db->prepare(
                "UPDATE mensagens_internas
                 SET status = 'lida'
                 WHERE destinatario_id = :utilizador_id
                   AND status = 'nao_lida'"
            );
            $stmtDiretas->execute(['utilizador_id' => $utilizadorId]);

            $stmtPerfil = $this->db->prepare(
                "INSERT IGNORE INTO mensagens_internas_lidas (mensagem_id, utilizador_id, lido_em)
                 SELECT m.id, :utilizador_id, NOW()
                 FROM mensagens_internas m
                 WHERE m.destinatario_id IS NULL
                   AND m.perfil_destino IN ('todos', :perfil_destino)"
            );
            $stmtPerfil->execute([
                'utilizador_id' => $utilizadorId,
                'perfil_destino' => $perfilDestino
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
    }

    private function destinatarioAvisos(string $perfil): string
    {
        $mapa = [
            'aluno' => 'alunos',
            'encarregado' => 'encarregados',
            'professor' => 'professores',
            'secretaria' => 'funcionarios',
            'direcao_pedagogica' => 'funcionarios',
            'direcao_geral' => 'funcionarios',
            'rh' => 'funcionarios'
        ];

        $perfil = $this->perfilMensagens($perfil);
        return $mapa[$perfil] ?? 'todos';
    }

    private function perfilMensagens(string $perfil): string
    {
        $perfil = strtolower(trim($perfil));
        $permitidos = [
            'aluno',
            'encarregado',
            'professor',
            'secretaria',
            'direcao_pedagogica',
            'direcao_geral',
            'rh'
        ];

        return in_array($perfil, $permitidos, true) ? $perfil : 'aluno';
    }

    private function rotaNotificacaoPorPerfil(string $perfil): string
    {
        $mapa = [
            'aluno' => 'painel/aluno/avisos',
            'encarregado' => 'painel/encarregado/mensagens',
            'professor' => 'painel/professor/comunicacao',
            'secretaria' => 'painel/secretaria/comunicacao',
            'direcao_pedagogica' => 'painel/direcao-pedagogica/notificacoes',
            'direcao_geral' => 'painel/direcao-geral/notificacoes',
            'rh' => 'painel/rh/dashboard'
        ];

        $perfilNormalizado = $this->perfilMensagens($perfil);
        return base_url($mapa[$perfilNormalizado] ?? 'painel');
    }

    private function garantirEstrutura(): void
    {
        if (self::$estruturaGarantida) {
            return;
        }

        $sqls = [
            "CREATE TABLE IF NOT EXISTS avisos_lidos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aviso_id INT NOT NULL,
                utilizador_id INT NOT NULL,
                lido_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_aviso_lido (aviso_id, utilizador_id),
                CONSTRAINT fk_avisos_lidos_aviso FOREIGN KEY (aviso_id) REFERENCES avisos(id) ON DELETE CASCADE,
                CONSTRAINT fk_avisos_lidos_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS mensagens_internas_lidas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mensagem_id INT NOT NULL,
                utilizador_id INT NOT NULL,
                lido_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_mensagem_lida (mensagem_id, utilizador_id),
                CONSTRAINT fk_msg_lida_mensagem FOREIGN KEY (mensagem_id) REFERENCES mensagens_internas(id) ON DELETE CASCADE,
                CONSTRAINT fk_msg_lida_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];

        foreach ($sqls as $sql) {
            try {
                $this->db->exec($sql);
            } catch (Throwable $erro) {
            }
        }

        self::$estruturaGarantida = true;
    }
}
