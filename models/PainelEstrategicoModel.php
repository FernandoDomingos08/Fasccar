<?php

class PainelEstrategicoModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function obterZonasPainel(string $perfil, int $utilizadorId = 0): array
    {
        return [
            'notificacoes' => $this->listarNotificacoesPerfil($perfil, 6),
            'mensagens' => $this->listarMensagensPerfil($perfil, $utilizadorId, 6),
            'tarefas' => $this->listarTarefasPerfil($perfil, 6),
            'atalhos' => $this->listarAtalhosPerfil($perfil)
        ];
    }

    public function listarCandidaturasDocentes(int $limite = 8): array
    {
        $limite = max(1, $limite);

        try {
            $sql = "SELECT id, nome, email, telefone, disciplina, status, criado_em
                    FROM candidaturas_professor
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

    public function listarTramitacoesDocumentais(int $limite = 10): array
    {
        $limite = max(1, $limite);

        try {
            $sql = "SELECT codigo, tipo_documento, origem_setor, destino_setor, status, atualizado_em
                    FROM tramitacoes_documentais
                    ORDER BY atualizado_em DESC
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

    private function listarNotificacoesPerfil(string $perfil, int $limite = 6): array
    {
        $limite = max(1, $limite);
        $destinatario = $this->destinatarioAvisoPorPerfil($perfil);

        try {
            $sql = "SELECT titulo, mensagem, criado_em
                    FROM avisos
                    WHERE destinatarios IN ('todos', :destinatario)
                      AND (data_inicio IS NULL OR data_inicio <= NOW())
                      AND (data_fim IS NULL OR data_fim >= NOW())
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['destinatario' => $destinatario]);
            $dados = $stmt->fetchAll();

            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
        }

        return [];
    }

    private function listarMensagensPerfil(string $perfil, int $utilizadorId, int $limite = 6): array
    {
        $limite = max(1, $limite);
        $perfilNormalizado = $this->normalizarPerfil($perfil);

        try {
            $sql = "SELECT
                        m.assunto,
                        m.mensagem,
                        m.status,
                        m.criado_em,
                        u.nome AS remetente_nome
                    FROM mensagens_internas m
                    LEFT JOIN utilizadores u ON u.id = m.remetente_id
                    WHERE (
                        m.perfil_destino IN ('todos', :perfil_destino)
                        OR m.remetente_id = :utilizador_remetente
                        OR m.destinatario_id = :utilizador_destino
                    )
                    ORDER BY m.criado_em DESC
                    LIMIT {$limite}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'perfil_destino' => $perfilNormalizado,
                'utilizador_remetente' => $utilizadorId,
                'utilizador_destino' => $utilizadorId
            ]);

            $dados = $stmt->fetchAll();
            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
        }

        return [];
    }

    private function listarTarefasPerfil(string $perfil, int $limite = 6): array
    {
        $limite = max(1, $limite);
        $setor = $this->normalizarPerfil($perfil);

        try {
            $sql = "SELECT titulo, descricao, prioridade, status, prazo
                    FROM tarefas_painel
                    WHERE setor IN ('todos', :setor)
                      AND status IN ('pendente', 'em_andamento')
                    ORDER BY FIELD(prioridade, 'alta', 'media', 'baixa'),
                             prazo IS NULL,
                             prazo ASC,
                             criado_em DESC
                    LIMIT {$limite}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['setor' => $setor]);
            $dados = $stmt->fetchAll();

            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
        }

        return [];
    }

    private function listarAtalhosPerfil(string $perfil): array
    {
        $perfilNormalizado = $this->normalizarPerfil($perfil);

        $mapaAtalhos = [
            'aluno' => [
                ['rotulo' => 'Notas', 'rota' => 'painel/aluno/notas'],
                ['rotulo' => 'Materiais', 'rota' => 'painel/aluno/materiais'],
                ['rotulo' => 'Mensagens', 'rota' => 'painel/aluno/mensagens']
            ],
            'encarregado' => [
                ['rotulo' => 'Educandos', 'rota' => 'painel/encarregado/educandos'],
                ['rotulo' => 'Financeiro', 'rota' => 'painel/encarregado/financeiro'],
                ['rotulo' => 'Mensagens', 'rota' => 'painel/encarregado/mensagens']
            ],
            'professor' => [
                ['rotulo' => 'Turmas', 'rota' => 'painel/professor/turmas'],
                ['rotulo' => 'Notas', 'rota' => 'painel/professor/notas'],
                ['rotulo' => 'Orientacoes', 'rota' => 'painel/professor']
            ],
            'secretaria' => [
                ['rotulo' => 'Pre-matriculas', 'rota' => 'painel/secretaria/pre-matriculas'],
                ['rotulo' => 'Matriculas', 'rota' => 'painel/secretaria/matriculas'],
                ['rotulo' => 'Documentos', 'rota' => 'painel/secretaria/documentos']
            ],
            'direcao_pedagogica' => [
                ['rotulo' => 'Cursos', 'rota' => 'painel/direcao-pedagogica/cursos'],
                ['rotulo' => 'Avaliacoes', 'rota' => 'painel/direcao-pedagogica/avaliacoes'],
                ['rotulo' => 'Relatorios', 'rota' => 'painel/direcao-pedagogica/relatorios']
            ],
            'direcao_geral' => [
                ['rotulo' => 'Financeiro', 'rota' => 'painel/direcao-geral/rel-financeiro'],
                ['rotulo' => 'Academico', 'rota' => 'painel/direcao-geral/rel-academico'],
                ['rotulo' => 'Comunicados', 'rota' => 'painel/direcao-geral/comunicados']
            ],
            'rh' => [
                ['rotulo' => 'Equipa', 'rota' => 'painel/rh/funcionarios'],
                ['rotulo' => 'Candidaturas', 'rota' => 'painel/rh/candidaturas'],
                ['rotulo' => 'Relatorios', 'rota' => 'painel/rh/relatorios']
            ]
        ];

        return $mapaAtalhos[$perfilNormalizado] ?? [];
    }

    private function destinatarioAvisoPorPerfil(string $perfil): string
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

        $perfilNormalizado = $this->normalizarPerfil($perfil);
        return $mapa[$perfilNormalizado] ?? 'todos';
    }

    private function normalizarPerfil(string $perfil): string
    {
        $perfilNormalizado = trim(strtolower($perfil));
        $permitidos = [
            'aluno',
            'encarregado',
            'professor',
            'secretaria',
            'direcao_pedagogica',
            'direcao_geral',
            'rh'
        ];

        return in_array($perfilNormalizado, $permitidos, true) ? $perfilNormalizado : 'aluno';
    }
}
