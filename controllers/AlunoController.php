<?php

class AlunoController
{
    private $dashboard;

    public function __construct()
    {
        $this->dashboard = new DashboardController();
    }

    public function index(): void
    {
        $this->dashboard->aluno();
    }

    public function enviar_mensagem_turma(): void
    {
        $this->dashboard->aluno_enviar_mensagem_turma();
    }

    public function enviar_mensagem_colega(): void
    {
        $this->dashboard->aluno_enviar_mensagem_colega();
    }

    public function criar_grupo_estudo(): void
    {
        $this->dashboard->aluno_criar_grupo_estudo();
    }

    public function adicionar_membro_grupo(): void
    {
        $this->dashboard->aluno_adicionar_membro_grupo();
    }

    public function remover_membro_grupo(): void
    {
        $this->dashboard->aluno_remover_membro_grupo();
    }

    public function sair_grupo(): void
    {
        $this->dashboard->aluno_sair_grupo();
    }

    public function eliminar_grupo(): void
    {
        $this->dashboard->aluno_eliminar_grupo();
    }

    public function enviar_mensagem_grupo(): void
    {
        $this->dashboard->aluno_enviar_mensagem_grupo();
    }

    public function justificar_falta(): void
    {
        $this->dashboard->aluno_justificar_falta();
    }

    public function eliminar_material(): void
    {
        $this->dashboard->aluno_eliminar_material();
    }

    public function reclamar_nota(): void
    {
        $this->dashboard->aluno_reclamar_nota();
    }
}
