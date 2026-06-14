<?php

class AdministracaoController
{
    private $administracaoModel;

    public function __construct()
    {
        if (!usuario_logado()) {
            definir_flash('erro_login', 'Sessao expirada. Inicie sessao novamente.');
            redirecionar('login');
        }

        if (!in_array(perfil_atual(), ['secretaria', 'direcao_pedagogica'], true)) {
            definir_flash('erro_login', 'Nao tem permissao para aceder ao modulo administrativo.');
            redirecionar('painel');
        }

        $this->administracaoModel = new AdministracaoModel();
    }

    public function index(): void
    {
        $abasPermitidas = $this->abasPermitidasPerfil();
        $aba = trim((string) ($_GET['aba'] ?? ''));

        if ($aba === '' || !in_array($aba, $abasPermitidas, true)) {
            $aba = $abasPermitidas[0];
        }

        $alunos = $this->administracaoModel->listarAlunos();
        $turmas = $this->administracaoModel->listarTurmas();
        $disciplinas = $this->administracaoModel->listarDisciplinas();
        $pagamentos = $this->administracaoModel->listarPagamentos();
        $professores = $this->administracaoModel->listarProfessores();
        $matriculasAtivas = $this->administracaoModel->listarMatriculasAtivas();

        $mensagem = obter_flash('sucesso_admin');
        $erro = obter_flash('erro_admin');

        require CAMINHO_RAIZ . '/views/administracao.php';
    }

    public function alunos_criar(): void
    {
        $this->exigirAba('alunos');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=alunos');
        }

        $dados = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'email' => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL) ?: '',
            'senha' => trim((string) ($_POST['senha'] ?? '')),
            'bi' => trim((string) ($_POST['bi'] ?? '')),
            'data_nascimento' => trim((string) ($_POST['data_nascimento'] ?? '')),
            'contato' => trim((string) ($_POST['contato'] ?? '')),
            'genero' => trim((string) ($_POST['genero'] ?? '')),
            'turma_id' => (int) ($_POST['turma_id'] ?? 0)
        ];

        if ($dados['nome'] === '' || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL) || $dados['data_nascimento'] === '') {
            definir_flash('erro_admin', 'Preencha os campos obrigatorios do aluno.');
            redirecionar('administracao?aba=alunos');
        }

        if ($dados['senha'] === '') {
            $dados['senha'] = '12345678';
        } elseif (strlen($dados['senha']) < 8) {
            definir_flash('erro_admin', 'A senha deve ter pelo menos 8 caracteres.');
            redirecionar('administracao?aba=alunos');
        }

        $resultado = $this->administracaoModel->criarAluno($dados);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=alunos');
    }

    public function alunos_actualizar(): void
    {
        $this->exigirAba('alunos');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=alunos');
        }

        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $dados = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'email' => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL) ?: '',
            'senha' => trim((string) ($_POST['senha'] ?? '')),
            'bi' => trim((string) ($_POST['bi'] ?? '')),
            'data_nascimento' => trim((string) ($_POST['data_nascimento'] ?? '')),
            'contato' => trim((string) ($_POST['contato'] ?? '')),
            'genero' => trim((string) ($_POST['genero'] ?? '')),
            'turma_id' => (int) ($_POST['turma_id'] ?? 0)
        ];

        if ($alunoId <= 0 || $dados['nome'] === '' || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            definir_flash('erro_admin', 'Dados invalidos para actualizar o aluno.');
            redirecionar('administracao?aba=alunos');
        }

        if ($dados['senha'] !== '' && strlen($dados['senha']) < 8) {
            definir_flash('erro_admin', 'A senha deve ter pelo menos 8 caracteres.');
            redirecionar('administracao?aba=alunos');
        }

        $resultado = $this->administracaoModel->atualizarAluno($alunoId, $dados);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=alunos');
    }

    public function alunos_remover(): void
    {
        $this->exigirAba('alunos');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=alunos');
        }

        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $resultado = $this->administracaoModel->removerAluno($alunoId);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=alunos');
    }

    public function turmas_criar(): void
    {
        $this->exigirAba('turmas');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=turmas');
        }

        $dados = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'ano_letivo' => trim((string) ($_POST['ano_letivo'] ?? '')),
            'capacidade' => (int) ($_POST['capacidade'] ?? 0),
            'professor_id' => (int) ($_POST['professor_id'] ?? 0)
        ];

        if ($dados['nome'] === '' || $dados['ano_letivo'] === '' || $dados['capacidade'] <= 0) {
            definir_flash('erro_admin', 'Preencha corretamente os dados da turma.');
            redirecionar('administracao?aba=turmas');
        }

        $resultado = $this->administracaoModel->criarTurma($dados);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=turmas');
    }

    public function turmas_actualizar(): void
    {
        $this->exigirAba('turmas');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=turmas');
        }

        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        $dados = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'ano_letivo' => trim((string) ($_POST['ano_letivo'] ?? '')),
            'capacidade' => (int) ($_POST['capacidade'] ?? 0),
            'professor_id' => (int) ($_POST['professor_id'] ?? 0)
        ];

        if ($turmaId <= 0 || $dados['nome'] === '' || $dados['ano_letivo'] === '') {
            definir_flash('erro_admin', 'Dados invalidos para actualizar a turma.');
            redirecionar('administracao?aba=turmas');
        }

        $resultado = $this->administracaoModel->atualizarTurma($turmaId, $dados);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=turmas');
    }

    public function turmas_remover(): void
    {
        $this->exigirAba('turmas');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=turmas');
        }

        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        $resultado = $this->administracaoModel->removerTurma($turmaId);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=turmas');
    }

    public function disciplinas_criar(): void
    {
        $this->exigirAba('disciplinas');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=disciplinas');
        }

        $dados = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'carga_horaria' => (int) ($_POST['carga_horaria'] ?? 0)
        ];

        if ($dados['nome'] === '' || $dados['carga_horaria'] <= 0) {
            definir_flash('erro_admin', 'Preencha corretamente os dados da disciplina.');
            redirecionar('administracao?aba=disciplinas');
        }

        $resultado = $this->administracaoModel->criarDisciplina($dados);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=disciplinas');
    }

    public function disciplinas_actualizar(): void
    {
        $this->exigirAba('disciplinas');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=disciplinas');
        }

        $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
        $dados = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'carga_horaria' => (int) ($_POST['carga_horaria'] ?? 0)
        ];

        if ($disciplinaId <= 0 || $dados['nome'] === '') {
            definir_flash('erro_admin', 'Dados invalidos para actualizar a disciplina.');
            redirecionar('administracao?aba=disciplinas');
        }

        $resultado = $this->administracaoModel->atualizarDisciplina($disciplinaId, $dados);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=disciplinas');
    }

    public function disciplinas_remover(): void
    {
        $this->exigirAba('disciplinas');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=disciplinas');
        }

        $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
        $resultado = $this->administracaoModel->removerDisciplina($disciplinaId);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=disciplinas');
    }

    public function pagamentos_criar(): void
    {
        $this->exigirAba('pagamentos');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=pagamentos');
        }

        $dados = [
            'matricula_id' => (int) ($_POST['matricula_id'] ?? 0),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'valor' => (float) ($_POST['valor'] ?? 0),
            'data_vencimento' => trim((string) ($_POST['data_vencimento'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'pendente'))
        ];

        if ($dados['matricula_id'] <= 0 || $dados['descricao'] === '' || $dados['valor'] <= 0) {
            definir_flash('erro_admin', 'Preencha corretamente os dados do pagamento.');
            redirecionar('administracao?aba=pagamentos');
        }

        $resultado = $this->administracaoModel->criarPagamento($dados);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=pagamentos');
    }

    public function pagamentos_actualizar(): void
    {
        $this->exigirAba('pagamentos');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=pagamentos');
        }

        $pagamentoId = (int) ($_POST['pagamento_id'] ?? 0);
        $dados = [
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'valor' => (float) ($_POST['valor'] ?? 0),
            'data_vencimento' => trim((string) ($_POST['data_vencimento'] ?? '')),
            'data_pagamento' => trim((string) ($_POST['data_pagamento'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'pendente'))
        ];

        if ($pagamentoId <= 0 || $dados['descricao'] === '' || $dados['valor'] <= 0) {
            definir_flash('erro_admin', 'Dados invalidos para actualizar o pagamento.');
            redirecionar('administracao?aba=pagamentos');
        }

        $resultado = $this->administracaoModel->atualizarPagamento($pagamentoId, $dados);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=pagamentos');
    }

    public function pagamentos_remover(): void
    {
        $this->exigirAba('pagamentos');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('administracao?aba=pagamentos');
        }

        $pagamentoId = (int) ($_POST['pagamento_id'] ?? 0);
        $resultado = $this->administracaoModel->removerPagamento($pagamentoId);
        $this->flashResultado($resultado);
        redirecionar('administracao?aba=pagamentos');
    }

    private function abasPermitidasPerfil(): array
    {
        $mapa = [
            'secretaria' => ['alunos', 'pagamentos'],
            'direcao_pedagogica' => ['turmas', 'disciplinas'],
            'direcao_geral' => ['alunos', 'turmas', 'disciplinas', 'pagamentos']
        ];

        return $mapa[perfil_atual()] ?? ['alunos'];
    }

    private function exigirAba(string $aba): void
    {
        if (!in_array($aba, $this->abasPermitidasPerfil(), true)) {
            definir_flash('erro_admin', 'Nao tem permissao para executar esta operacao.');
            redirecionar('administracao');
        }
    }

    private function flashResultado(array $resultado): void
    {
        if (!empty($resultado['sucesso'])) {
            definir_flash('sucesso_admin', (string) ($resultado['mensagem'] ?? 'Operacao concluida com sucesso.'));
            return;
        }

        definir_flash('erro_admin', (string) ($resultado['mensagem'] ?? 'Nao foi possivel concluir a operacao.'));
    }
}
