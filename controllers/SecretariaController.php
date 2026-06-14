<?php

class SecretariaController
{
    private $dashboard;

    public function __construct()
    {
        $this->dashboard = new DashboardController();
    }

    public function index(): void
    {
        $this->dashboard->secretaria();
    }

    public function concluir_matricula(): void
    {
        $this->dashboard->secretaria_concluir_matricula();
    }

    public function analisar_comprovativo(): void
    {
        $this->dashboard->secretaria_analisar_comprovativo();
    }

    public function criar_atividade(): void
    {
        $this->dashboard->secretaria_criar_atividade();
    }

    public function atualizar_atividade(): void
    {
        $this->dashboard->secretaria_atualizar_atividade();
    }

    public function remover_atividade(): void
    {
        $this->dashboard->secretaria_remover_atividade();
    }
}
