<?php

class ProfessorController
{
    private $dashboard;

    public function __construct()
    {
        $this->dashboard = new DashboardController();
    }

    public function index(): void
    {
        $this->dashboard->professor();
    }

    public function lancar_nota(): void
    {
        $this->dashboard->professor_lancar_nota();
    }

    public function registrar_presenca(): void
    {
        $this->dashboard->professor_registrar_presenca();
    }

    public function enviar_material(): void
    {
        $this->dashboard->professor_enviar_material();
    }

    public function remover_material(): void
    {
        $this->dashboard->professor_remover_material();
    }

    public function enviar_pauta(): void
    {
        $this->dashboard->professor_enviar_pauta();
    }

    public function publicar_comunicado(): void
    {
        $this->dashboard->professor_publicar_comunicado();
    }

    public function enviar_aviso_comportamento(): void
    {
        $this->dashboard->professor_enviar_aviso_comportamento();
    }

    public function analisar_justificativa(): void
    {
        $this->dashboard->professor_analisar_justificativa();
    }
}
