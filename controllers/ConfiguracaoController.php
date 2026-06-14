<?php

class ConfiguracaoController
{
    private $configuracaoModel;

    public function __construct()
    {
        if (!usuario_logado()) {
            definir_flash('erro_login', 'Sessao expirada. Inicie sessao novamente.');
            redirecionar('login');
        }

        if (!in_array(perfil_atual(), ['secretaria', 'direcao_geral'], true)) {
            definir_flash('erro_login', 'Nao tem permissao para aceder as configuracoes.');
            redirecionar('painel');
        }

        $this->configuracaoModel = new ConfiguracaoModel();
    }

    public function index(): void
    {
        $config = $this->configuracaoModel->obter();
        $mensagem = obter_flash('sucesso_configuracoes');
        $erro = obter_flash('erro_configuracoes');

        require CAMINHO_RAIZ . '/views/configuracoes.php';
    }

    public function atualizar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('configuracoes');
        }

        $resultado = $this->configuracaoModel->atualizar($_POST);
        if (!empty($resultado['sucesso'])) {
            definir_flash('sucesso_configuracoes', (string) ($resultado['mensagem'] ?? 'Configuracoes atualizadas.'));
        } else {
            definir_flash('erro_configuracoes', (string) ($resultado['mensagem'] ?? 'Nao foi possivel atualizar as configuracoes.'));
        }

        redirecionar('configuracoes');
    }
}
