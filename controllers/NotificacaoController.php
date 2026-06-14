<?php

class NotificacaoController
{
    private ?DashboardController $dashboard = null;
    private NotificacaoModel $notificacaoModel;

    public function __construct()
    {
        $this->notificacaoModel = new NotificacaoModel();
    }

    public function comunicado_global(): void
    {
        $this->dashboard()->direcao_enviar_comunicado();
    }

    public function mensagem_massa(): void
    {
        $this->dashboard()->direcao_enviar_mensagem_massa();
    }

    public function comunicado_turma(): void
    {
        $this->dashboard()->professor_publicar_comunicado();
    }

    public function aviso_comportamento(): void
    {
        $this->dashboard()->professor_enviar_aviso_comportamento();
    }

    public function painel_resumo(): void
    {
        $this->responderResumo();
    }

    public function contagem(): void
    {
        $this->responderResumo(true);
    }

    public function listar(): void
    {
        if (!usuario_logado()) {
            responder_json([
                'sucesso' => false,
                'mensagem' => 'Sessao expirada.'
            ], 401);
        }

        $utilizadorId = (int) ($_SESSION['usuario_id'] ?? 0);
        $perfil = perfil_atual();
        $itens = $this->notificacaoModel->listarPendentes($utilizadorId, $perfil, 50);

        responder_json([
            'sucesso' => true,
            'perfil' => $perfil,
            'total' => count($itens),
            'itens' => $itens
        ]);
    }

    public function marcar_lida(): void
    {
        if (!usuario_logado()) {
            responder_json([
                'sucesso' => false,
                'mensagem' => 'Sessao expirada.'
            ], 401);
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            responder_json([
                'sucesso' => false,
                'mensagem' => 'Metodo nao suportado.'
            ], 405);
        }

        $tipo = trim((string) ($_POST['tipo'] ?? ''));
        $origemId = (int) ($_POST['origem_id'] ?? 0);
        $ok = $this->notificacaoModel->marcarComoLida((int) $_SESSION['usuario_id'], perfil_atual(), $tipo, $origemId);

        responder_json([
            'sucesso' => $ok,
            'mensagem' => $ok ? 'Notificacao marcada como lida.' : 'Nao foi possivel marcar a notificacao.'
        ], $ok ? 200 : 422);
    }

    public function marcar_todas_lidas(): void
    {
        if (!usuario_logado()) {
            responder_json([
                'sucesso' => false,
                'mensagem' => 'Sessao expirada.'
            ], 401);
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            responder_json([
                'sucesso' => false,
                'mensagem' => 'Metodo nao suportado.'
            ], 405);
        }

        $ok = $this->notificacaoModel->marcarTodasComoLidas((int) $_SESSION['usuario_id'], perfil_atual());
        responder_json([
            'sucesso' => $ok,
            'mensagem' => $ok ? 'Todas as notificacoes foram marcadas como lidas.' : 'Nao foi possivel concluir a operacao.'
        ], $ok ? 200 : 422);
    }

    private function responderResumo(bool $somenteTotal = false): void
    {
        if (!usuario_logado()) {
            responder_json([
                'sucesso' => false,
                'mensagem' => 'Sessao expirada.'
            ], 401);
        }

        $perfil = perfil_atual();
        $utilizadorId = (int) ($_SESSION['usuario_id'] ?? 0);
        $resumo = $this->notificacaoModel->resumoPainel($utilizadorId, $perfil, 20);

        $payload = [
            'sucesso' => true,
            'perfil' => $perfil,
            'total' => (int) ($resumo['total'] ?? 0)
        ];

        if (!$somenteTotal) {
            $payload['itens'] = (array) ($resumo['itens'] ?? []);
            $payload['tarefas'] = 0;
        }

        responder_json($payload);
    }

    private function dashboard(): DashboardController
    {
        if ($this->dashboard === null) {
            $this->dashboard = new DashboardController();
        }

        return $this->dashboard;
    }
}
