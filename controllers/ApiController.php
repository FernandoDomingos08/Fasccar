<?php

class ApiController
{
    private NotificacaoModel $notificacaoModel;
    private FuncionarioModel $funcionarioModel;
    private ConfiguracaoModel $configuracaoModel;
    private PainelOperacionalModel $painelOperacionalModel;
    private PDO $db;

    public function __construct()
    {
        $this->notificacaoModel = new NotificacaoModel();
        $this->funcionarioModel = new FuncionarioModel();
        $this->configuracaoModel = new ConfiguracaoModel();
        $this->painelOperacionalModel = new PainelOperacionalModel();
        $this->db = Database::getInstancia();
    }

    public function index(): void
    {
        responder_json([
            'sucesso' => false,
            'mensagem' => 'Endpoint API nao encontrado.'
        ], 404);
    }

    public function instituicao(?string $acao = null): void
    {
        $acao = strtolower(trim((string) $acao));
        if ($acao === '' || $acao === 'info') {
            responder_json([
                'sucesso' => true,
                'dados' => $this->configuracaoModel->obter()
            ]);
        }

        responder_json([
            'sucesso' => false,
            'mensagem' => 'Acao de instituicao nao suportada.'
        ], 404);
    }

    public function notificacoes(?string $acao = null): void
    {
        $this->exigirLogin();
        $acao = strtolower(trim((string) $acao));
        $utilizadorId = (int) ($_SESSION['usuario_id'] ?? 0);
        $perfil = perfil_atual();

        if ($acao === '' || $acao === 'contagem') {
            responder_json([
                'sucesso' => true,
                'total' => $this->notificacaoModel->contarPendentes($utilizadorId, $perfil)
            ]);
        }

        if ($acao === 'listar') {
            $itens = $this->notificacaoModel->listarPendentes($utilizadorId, $perfil, 50);
            responder_json([
                'sucesso' => true,
                'total' => count($itens),
                'itens' => $itens
            ]);
        }

        if ($acao === 'marcar-lida') {
            $this->exigirMetodo('POST');
            $tipo = trim((string) ($_POST['tipo'] ?? ''));
            $origemId = (int) ($_POST['origem_id'] ?? 0);
            $ok = $this->notificacaoModel->marcarComoLida($utilizadorId, $perfil, $tipo, $origemId);

            responder_json([
                'sucesso' => $ok,
                'mensagem' => $ok ? 'Notificacao marcada como lida.' : 'Nao foi possivel marcar a notificacao.'
            ], $ok ? 200 : 422);
        }

        if ($acao === 'marcar-todas') {
            $this->exigirMetodo('POST');
            $ok = $this->notificacaoModel->marcarTodasComoLidas($utilizadorId, $perfil);
            responder_json([
                'sucesso' => $ok
            ], $ok ? 200 : 422);
        }

        responder_json([
            'sucesso' => false,
            'mensagem' => 'Acao de notificacoes nao suportada.'
        ], 404);
    }

    public function dashboard(?string $acao = null): void
    {
        $this->exigirLogin();
        $acao = strtolower(trim((string) $acao));

        if ($acao === '' || $acao === 'kpis') {
            $kpis = [
                'total_alunos' => $this->contarTabela('alunos'),
                'total_professores' => $this->contarTabela('professores'),
                'total_turmas' => $this->contarTabela('turmas')
            ];

            responder_json([
                'sucesso' => true,
                'dados' => $kpis
            ]);
        }

        if ($acao === 'graficos') {
            $dados = $this->funcionarioModel->obterAnaliticaDirecaoGeral();
            responder_json([
                'sucesso' => true,
                'dados' => $dados
            ]);
        }

        responder_json([
            'sucesso' => false,
            'mensagem' => 'Acao de dashboard nao suportada.'
        ], 404);
    }

    public function manual(?string $acao = null): void
    {
        $this->exigirLogin();
        $acao = strtolower(trim((string) $acao));
        $utilizadorId = (int) ($_SESSION['usuario_id'] ?? 0);
        $perfil = perfil_atual();

        if ($acao === '' || $acao === 'perfil') {
            $primeiroAcesso = !$this->painelOperacionalModel->tutorialJaFoiVisto($utilizadorId);
            responder_json([
                'sucesso' => true,
                'perfil' => $perfil,
                'primeiro_acesso' => $primeiroAcesso,
                'titulo' => 'Bem-vindo ao painel',
                'conteudo' => $this->obterManualPerfil($perfil)
            ]);
        }

        if ($acao === 'marcar-visto') {
            $this->exigirMetodo('POST');
            $ok = $this->painelOperacionalModel->marcarTutorialComoVisto($utilizadorId);
            responder_json([
                'sucesso' => $ok
            ], $ok ? 200 : 422);
        }

        responder_json([
            'sucesso' => false,
            'mensagem' => 'Acao do manual nao suportada.'
        ], 404);
    }

    public function tramitacoes(?string $id = null, ?string $acao = null): void
    {
        $this->exigirLogin();
        $perfil = perfil_atual();
        $utilizadorId = (int) ($_SESSION['usuario_id'] ?? 0);
        $idNumerico = (int) ($id ?? 0);
        $acao = strtolower(trim((string) $acao));

        if ($idNumerico <= 0 && ($acao === '' || $acao === 'listar')) {
            $dados = $this->listarTramitacoesDestino($perfil);
            responder_json([
                'sucesso' => true,
                'dados' => $dados
            ]);
        }

        if ($idNumerico > 0 && $acao === 'acao') {
            $this->exigirMetodo('POST');
            $acaoFormulario = strtolower(trim((string) ($_POST['acao'] ?? '')));
            $observacao = trim((string) ($_POST['observacao'] ?? ''));
            $ok = $this->aplicarAcaoTramitacao($idNumerico, $perfil, $utilizadorId, $acaoFormulario, $observacao);

            responder_json([
                'sucesso' => $ok,
                'mensagem' => $ok ? 'Tramitacao atualizada com sucesso.' : 'Nao foi possivel atualizar a tramitacao.'
            ], $ok ? 200 : 422);
        }

        responder_json([
            'sucesso' => false,
            'mensagem' => 'Acao de tramitacoes nao suportada.'
        ], 404);
    }

    private function listarTramitacoesDestino(string $perfil): array
    {
        $destinos = [$perfil];
        if ($perfil === 'direcao_geral') {
            $destinos[] = 'publico';
        }

        $placeholders = [];
        $params = [];
        foreach ($destinos as $indice => $destino) {
            $chave = 'd' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $destino;
        }

        $sql = 'SELECT
                    id,
                    codigo,
                    tipo_documento,
                    origem_setor,
                    destino_setor,
                    status,
                    observacao,
                    atualizado_em
                FROM tramitacoes_documentais
                WHERE destino_setor IN (' . implode(',', $placeholders) . ')
                  AND status IN ("submetido", "em_analise", "aprovado", "rejeitado", "publicado")
                ORDER BY atualizado_em DESC
                LIMIT 60';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $erro) {
            return [];
        }
    }

    private function aplicarAcaoTramitacao(int $id, string $perfil, int $utilizadorId, string $acao, string $observacao): bool
    {
        $mapa = [
            'aprovar' => 'aprovado',
            'reprovar' => 'rejeitado',
            'rejeitar' => 'rejeitado',
            'publicar' => 'publicado'
        ];
        if (!isset($mapa[$acao])) {
            return false;
        }

        try {
            $stmt = $this->db->prepare('SELECT id, codigo, destino_setor, status, observacao FROM tramitacoes_documentais WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $linha = $stmt->fetch();
            if (!is_array($linha)) {
                return false;
            }

            $destino = (string) ($linha['destino_setor'] ?? '');
            $podeAtuar = $destino === $perfil || ($perfil === 'direcao_geral' && $destino === 'publico');
            if (!$podeAtuar) {
                return false;
            }

            $novoStatus = $mapa[$acao];
            if ($novoStatus === 'publicado' && $destino !== 'publico') {
                return false;
            }

            $registro = '[' . date('Y-m-d H:i:s') . '] Utilizador #' . $utilizadorId . ' => ' . strtoupper($novoStatus);
            if ($observacao !== '') {
                $registro .= ' | ' . $observacao;
            }

            $obsAtual = trim((string) ($linha['observacao'] ?? ''));
            $obsFinal = $obsAtual === '' ? $registro : ($obsAtual . PHP_EOL . $registro);

            $this->db->beginTransaction();
            $stmtUpdate = $this->db->prepare(
                'UPDATE tramitacoes_documentais
                 SET status = :status, observacao = :observacao, atualizado_em = NOW()
                 WHERE id = :id'
            );
            $stmtUpdate->execute([
                'status' => $novoStatus,
                'observacao' => $obsFinal,
                'id' => $id
            ]);

            $stmtLog = $this->db->prepare(
                'INSERT INTO historico_atividades (utilizador_id, acao, detalhe, ip, criado_em)
                 VALUES (:utilizador_id, :acao, :detalhe, :ip, NOW())'
            );
            $stmtLog->execute([
                'utilizador_id' => $utilizadorId,
                'acao' => 'Tramitacao documental',
                'detalhe' => 'Documento ' . ((string) ($linha['codigo'] ?? ('#' . $id))) . ' alterado para ' . $novoStatus,
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
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

    private function contarTabela(string $tabela): int
    {
        try {
            $sql = 'SELECT COUNT(*) FROM ' . preg_replace('/[^a-z_]/', '', strtolower($tabela));
            return (int) $this->db->query($sql)->fetchColumn();
        } catch (Throwable $erro) {
            return 0;
        }
    }

    private function exigirLogin(): void
    {
        if (usuario_logado()) {
            return;
        }

        responder_json([
            'sucesso' => false,
            'mensagem' => 'Sessao expirada.'
        ], 401);
    }

    private function exigirMetodo(string $metodo): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === strtoupper($metodo)) {
            return;
        }

        responder_json([
            'sucesso' => false,
            'mensagem' => 'Metodo nao suportado.'
        ], 405);
    }

    private function obterManualPerfil(string $perfil): string
    {
        $manualComum = [
            'Use as secções de avisos e mensagens para acompanhar atualizações.',
            'No ícone de ajuda (?) encontrará este manual sempre que precisar.',
            'Revise os dados antes de guardar qualquer alteração.',
            'As ações importantes ficam no histórico para auditoria.'
        ];

        $manualPorPerfil = [
            'aluno' => [
                'Consulte notas, faltas e materiais por disciplina.',
                'Participe nos grupos de estudo e envie mensagens ao professor.',
                'Solicite documentos no painel e acompanhe o estado do pedido.'
            ],
            'encarregado' => [
                'Acompanhe todos os educandos num único painel.',
                'Pague mensalidades e atividades por referência ou autorização.',
                'Solicite documentos e acompanhe comprovativos e recibos.'
            ],
            'professor' => [
                'Lance notas e faltas por turma e trimestre.',
                'Envie materiais (até 50 MB) e comunicados para a turma.',
                'Exporte a pauta e submeta para validação pedagógica.'
            ],
            'secretaria' => [
                'Conclua matrículas e associe encarregado a cada aluno.',
                'Analise comprovativos e acompanhe pagamentos por turma.',
                'Tramite documentos oficiais e acompanhe o histórico.'
            ],
            'direcao_pedagogica' => [
                'Gerencie cursos, atividades e solicitações documentais.',
                'Acompanhe o painel financeiro das atividades extracurriculares.',
                'Monitore indicadores pedagógicos e notificações de pagamentos.'
            ],
            'direcao_geral' => [
                'Publique comunicados gerais e mensagens em massa.',
                'Acompanhe relatórios institucionais e indicadores estratégicos.',
                'Valide trâmites críticos e acompanhe auditoria.'
            ],
            'rh' => [
                'Cadastre e desligue funcionários com registo de atividades.',
                'Acompanhe assiduidade, contratos e relatórios de RH.',
                'Mantenha perfis e credenciais atualizados com segurança.'
            ]
        ];

        $linhas = $manualPorPerfil[$perfil] ?? [
            'Use os atalhos do painel para aceder rapidamente às áreas principais.',
            'Consulte notificações e histórico para acompanhar atividades.'
        ];

        $conteudo = array_merge($linhas, $manualComum);
        return implode("\n", $conteudo);
    }
}
