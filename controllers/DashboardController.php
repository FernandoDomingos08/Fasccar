<?php

class DashboardController
{
    private $alunoModel;
    private $encarregadoModel;
    private $professorModel;
    private $funcionarioModel;
    private $preMatriculaModel;
    private $turmaModel;
    private $notaModel;
    private $mensagemSecretariaModel;
    private $painelEstrategicoModel;
    private $perfilModel;
    private $painelOperacionalModel;
    private $configuracaoModel;
    private $backupModel;
    private $backupStatus;

    public function __construct()
    {
        if (!usuario_logado()) {
            definir_flash('erro_login', 'Sessao expirada. Faca login novamente.');
            redirecionar('login');
        }

        $this->alunoModel = new AlunoModel();
        $this->encarregadoModel = new EncarregadoModel();
        $this->professorModel = new ProfessorModel();
        $this->funcionarioModel = new FuncionarioModel();
        $this->preMatriculaModel = new PreMatriculaModel();
        $this->turmaModel = new TurmaModel();
        $this->notaModel = new NotaModel();
        $this->mensagemSecretariaModel = new MensagemSecretariaModel();
        $this->painelEstrategicoModel = new PainelEstrategicoModel();
        $this->perfilModel = new PerfilModel();
        $this->painelOperacionalModel = new PainelOperacionalModel();
        $this->configuracaoModel = new ConfiguracaoModel();
      
        $this->backupModel = new BackupModel();
        $this->backupStatus = $this->backupModel->executarSeNecessario();

        if (($this->backupStatus['executado'] ?? false) && isset($_SESSION['usuario_id'])) {
            $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Backup automatico', 'Backup gerado: ' . ($this->backupStatus['ficheiro'] ?? ''));
        }
    }

    public function index(): void
    {
        redirecionar($this->rotaPorPerfil(perfil_atual()));
    }

    public function analitica_json(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            responder_json([
                'sucesso' => false,
                'mensagem' => 'Metodo nao suportado.'
            ], 405);
        }

        $utilizadorId = (int) ($_SESSION['usuario_id'] ?? 0);
        $perfil = perfil_atual();
        $dados = [];

        switch ($perfil) {
            case 'direcao_geral':
                $dados = $this->funcionarioModel->obterAnaliticaDirecaoGeral();
                break;
            case 'secretaria':
                $dados = $this->funcionarioModel->obterAnaliticaSecretaria();
                break;
            case 'professor':
                $dados = $this->professorModel->obterAnaliticaProfessor($utilizadorId);
                break;
            case 'encarregado':
                $dados = $this->encarregadoModel->obterAnaliticaEncarregado($utilizadorId);
                break;
            case 'aluno':
                $dados = [
                    'resumo' => $this->alunoModel->obterResumoPainel($utilizadorId),
                    'grafico' => $this->alunoModel->obterDadosGrafico($utilizadorId)
                ];
                break;
            default:
                $dados = [
                    'zonas' => $this->painelEstrategicoModel->obterZonasPainel($perfil, $utilizadorId)
                ];
                break;
        }

        responder_json([
            'sucesso' => true,
            'perfil' => $perfil,
            'dados' => $dados
        ]);
    }

    public function aluno(?string $pagina = null): void
    {
        $this->permitirPerfis(['aluno']);
        $paginasPermitidas = ['dashboard', 'notas', 'faltas', 'graficos', 'calendario', 'calendario-provas', 'reclamacoes', 'avisos', 'comunicacao', 'mensagens', 'documentos', 'solicitar-documento', 'perfil'];
        $paginaPainel = $this->resolverPaginaPainel($pagina, $paginasPermitidas, 'dashboard');

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $alunoId = $this->painelOperacionalModel->obterAlunoIdPorUtilizador($utilizadorId);
        $resumo = $this->alunoModel->obterResumoPainel($utilizadorId);
        $notas = $this->alunoModel->obterNotasTrimestrais($utilizadorId);
        $dadosGrafico = $this->alunoModel->obterDadosGrafico($utilizadorId);
        $avisos = $this->funcionarioModel->listarAvisos('alunos', 5);
        $avisosAluno = $this->painelOperacionalModel->listarAvisosAluno($utilizadorId, 20);
        $zonasPainel = $this->painelEstrategicoModel->obterZonasPainel('aluno', $utilizadorId);
        $pagamentosAluno = $this->painelOperacionalModel->listarPagamentosAluno($utilizadorId);
        $atividadesPublicadas = $this->painelOperacionalModel->listarAtividadesPublicadasIndex(12);
        $pagamentosAtividadesAluno = $this->painelOperacionalModel->listarPagamentosAtividadesAluno($utilizadorId);
        $materiaisAluno = $this->painelOperacionalModel->listarMateriaisAluno($utilizadorId, 12);
        $rankingTurma = $this->painelOperacionalModel->listarRankingTurmaAluno($utilizadorId, 10);
        $gruposEstudoAluno = $this->painelOperacionalModel->listarGruposEstudoAluno($alunoId, 12);
        $colegasTurmaAluno = $this->painelOperacionalModel->listarColegasTurmaAluno($utilizadorId, 80);
        $estadoMensagens = strtolower(trim((string) ($_GET['estado_mensagem'] ?? 'todas')));
        $mensagensAluno = $this->painelOperacionalModel->listarMensagensAluno($utilizadorId, 80, $estadoMensagens);
        $secretariasAtivas = $this->painelOperacionalModel->listarUtilizadoresPorPerfil('secretaria', 10);
        $reclamacoesAluno = $this->painelOperacionalModel->listarReclamacoesAluno($alunoId, 12);
        $boletinsAluno = $this->painelOperacionalModel->listarBoletinsAluno($alunoId, 12);
        $documentosDisponiveisAluno = $this->painelOperacionalModel->listarDocumentosDisponiveisAluno($alunoId, 12);
        $documentosRecebidosAluno = $this->painelOperacionalModel->listarDocumentosRecebidosAluno($alunoId, 12);
        $disciplinasDisponiveis = $this->painelOperacionalModel->listarDisciplinasSimples();
        $justificativasPendentesAluno = $this->painelOperacionalModel->listarPresencasAluno($utilizadorId, 180);

        if (!isset($dadosGrafico['presencas']) || !is_array($dadosGrafico['presencas'])) {
            $dadosGrafico['presencas'] = $justificativasPendentesAluno;
        }

        $grupoSelecionadoId = (int) ($_GET['grupo_id'] ?? 0);
        if ($grupoSelecionadoId <= 0 && !empty($gruposEstudoAluno)) {
            $grupoSelecionadoId = (int) ($gruposEstudoAluno[0]['id'] ?? 0);
        }
        $membrosGrupoSelecionado = $this->painelOperacionalModel->listarMembrosGrupoEstudo($grupoSelecionadoId, $alunoId);
        $mensagensGrupoSelecionado = $this->painelOperacionalModel->listarMensagensGrupoEstudo($grupoSelecionadoId, $alunoId, 80);
        extract($this->dadosPerfilComum($utilizadorId, 'painel/aluno/' . $paginaPainel), EXTR_OVERWRITE);

        require CAMINHO_RAIZ . '/views/painel/aluno.php';
    }

    public function aluno_criar_grupo_estudo(): void
    {
        $this->permitirPerfis(['aluno']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/aluno');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $alunoId = $this->painelOperacionalModel->obterAlunoIdPorUtilizador($utilizadorId);
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));

        if ($alunoId <= 0 || $nome === '') {
            definir_flash('erro_painel', 'Informe os dados do grupo de estudo.');
            redirecionar('painel/aluno?pagina=comunidade');
        }

        if (!$this->painelOperacionalModel->criarGrupoEstudo($alunoId, $nome, $descricao)) {
            definir_flash('erro_painel', 'Nao foi possivel criar o grupo de estudo.');
            redirecionar('painel/aluno?pagina=comunidade');
        }

        $this->perfilModel->registarAtividade($utilizadorId, 'Criou grupo de estudo', $nome);
        definir_flash('sucesso_painel', 'Grupo de estudo criado com sucesso.');
        redirecionar('painel/aluno?pagina=comunidade');
    }

    public function aluno_reclamar_nota(): void
    {
        $this->permitirPerfis(['aluno']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/aluno');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $alunoId = $this->painelOperacionalModel->obterAlunoIdPorUtilizador($utilizadorId);
        $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
        $trimestre = (int) ($_POST['trimestre'] ?? 0);
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));

        if ($alunoId <= 0 || $mensagem === '') {
            definir_flash('erro_painel', 'Preencha os dados da reclamacao de nota.');
            redirecionar('painel/aluno?pagina=reclamacoes');
        }

        if (!$this->painelOperacionalModel->reclamarNota($alunoId, $disciplinaId, $trimestre, $mensagem)) {
            definir_flash('erro_painel', 'Nao foi possivel registar a reclamacao de nota.');
            redirecionar('painel/aluno?pagina=reclamacoes');
        }

        $this->perfilModel->registarAtividade($utilizadorId, 'Reclamou nota', 'Disciplina #' . $disciplinaId);
        definir_flash('sucesso_painel', 'Reclamacao de nota enviada com sucesso.');
        redirecionar('painel/aluno?pagina=reclamacoes');
    }

    public function aluno_enviar_mensagem_turma(): void
    {
        $this->permitirPerfis(['aluno']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/aluno');
        }

        $assunto = trim((string) ($_POST['assunto'] ?? 'Mensagem da turma'));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));

        if ($mensagem === '') {
            definir_flash('erro_painel', 'Escreva a mensagem para a turma.');
            redirecionar('painel/aluno?pagina=comunidade');
        }

        $ok = $this->painelOperacionalModel->enviarMensagemInterna((int) $_SESSION['usuario_id'], null, 'professor', $assunto, $mensagem);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel enviar a mensagem.');
            redirecionar('painel/aluno?pagina=comunidade');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Enviou mensagem na turma', $assunto);
        definir_flash('sucesso_painel', 'Mensagem da turma enviada com sucesso.');
        redirecionar('painel/aluno?pagina=comunidade');
    }

    public function aluno_enviar_mensagem_colega(): void
    {
        $this->permitirPerfis(['aluno']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/aluno?pagina=mensagens');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $destinatarioId = (int) ($_POST['destinatario_id'] ?? 0);
        $assunto = trim((string) ($_POST['assunto'] ?? 'Mensagem entre colegas'));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));

        if ($destinatarioId <= 0 || $mensagem === '') {
            definir_flash('erro_painel', 'Selecione um colega e preencha a mensagem.');
            redirecionar('painel/aluno?pagina=mensagens');
        }

        $ok = $this->painelOperacionalModel->enviarMensagemColegaTurma($utilizadorId, $destinatarioId, $assunto, $mensagem);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel enviar a mensagem ao colega.');
            redirecionar('painel/aluno?pagina=mensagens');
        }

        $this->perfilModel->registarAtividade($utilizadorId, 'Enviou mensagem para colega', $assunto);
        definir_flash('sucesso_painel', 'Mensagem enviada com sucesso.');
        redirecionar('painel/aluno?pagina=mensagens');
    }

    public function aluno_enviar_mensagem_secretaria(): void
    {
        $this->permitirPerfis(['aluno']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/aluno?pagina=mensagens');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $destinatarioId = (int) ($_POST['destinatario_id'] ?? 0);
        $assunto = trim((string) ($_POST['assunto'] ?? 'Mensagem para secretaria'));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        $respostaAId = (int) ($_POST['resposta_a_id'] ?? 0);

        if ($destinatarioId <= 0 || $mensagem === '') {
            definir_flash('erro_painel', 'Selecione a secretaria e escreva a mensagem.');
            redirecionar('painel/aluno?pagina=mensagens');
        }

        $resultadoUploads = $this->processarUploadMultiplosArquivos(
            $_FILES['anexos'] ?? [],
            'storage/anexos',
            [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            5 * 1024 * 1024,
            'msg-aluno'
        );

        if (!empty($resultadoUploads['erros'])) {
            definir_flash('erro_painel', (string) $resultadoUploads['erros'][0]);
            redirecionar('painel/aluno?pagina=mensagens');
        }

        $resultado = $this->painelOperacionalModel->enviarMensagemInternaPainel([
            'remetente_id' => $utilizadorId,
            'destinatario_id' => $destinatarioId,
            'assunto' => sanitizar_texto_simples($assunto, 180),
            'mensagem' => sanitizar_texto_simples($mensagem, 5000),
            'resposta_a_id' => $respostaAId,
            'anexos' => (array) ($resultadoUploads['ficheiros'] ?? [])
        ]);

        if (!($resultado['sucesso'] ?? false)) {
            definir_flash('erro_painel', (string) ($resultado['mensagem'] ?? 'Nao foi possivel enviar a mensagem.'));
            redirecionar('painel/aluno?pagina=mensagens');
        }

        $this->perfilModel->registarAtividade($utilizadorId, 'Enviou mensagem para secretaria', $assunto);
        definir_flash('sucesso_painel', (string) ($resultado['mensagem'] ?? 'Mensagem enviada com sucesso.'));
        redirecionar('painel/aluno?pagina=mensagens');
    }

    public function aluno_justificar_falta(): void
    {
        $this->permitirPerfis(['aluno']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/aluno?pagina=faltas');
        }

        $presencaId = (int) ($_POST['presenca_id'] ?? 0);
        $justificativa = trim((string) ($_POST['justificativa'] ?? ''));
        if ($presencaId <= 0 || $justificativa === '') {
            definir_flash('erro_painel', 'Informe uma justificativa valida para a falta.');
            redirecionar('painel/aluno?pagina=faltas');
        }

        $ok = $this->painelOperacionalModel->enviarJustificativaFalta((int) $_SESSION['usuario_id'], $presencaId, $justificativa);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel enviar a justificativa da falta.');
            redirecionar('painel/aluno?pagina=faltas');
        }

        definir_flash('sucesso_painel', 'Justificativa enviada para analise do professor.');
        redirecionar('painel/aluno?pagina=faltas');
    }

    public function aluno_adicionar_membro_grupo(): void
    {
        $this->permitirPerfis(['aluno']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/aluno?pagina=grupos-estudo');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $alunoId = $this->painelOperacionalModel->obterAlunoIdPorUtilizador($utilizadorId);
        $grupoId = (int) ($_POST['grupo_id'] ?? 0);
        $novoAlunoId = (int) ($_POST['aluno_id'] ?? 0);

        if ($grupoId <= 0 || $novoAlunoId <= 0) {
            definir_flash('erro_painel', 'Selecione grupo e colega para adicionar.');
            redirecionar('painel/aluno?pagina=grupos-estudo');
        }

        $ok = $this->painelOperacionalModel->adicionarMembroGrupoEstudo($grupoId, $alunoId, $novoAlunoId);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel adicionar o colega ao grupo.');
            redirecionar('painel/aluno?pagina=grupos-estudo&grupo_id=' . $grupoId);
        }

        definir_flash('sucesso_painel', 'Colega adicionado ao grupo com sucesso.');
        redirecionar('painel/aluno?pagina=grupos-estudo&grupo_id=' . $grupoId);
    }

    public function aluno_remover_membro_grupo(): void
    {
        $this->permitirPerfis(['aluno']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/aluno?pagina=grupos-estudo');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $alunoId = $this->painelOperacionalModel->obterAlunoIdPorUtilizador($utilizadorId);
        $grupoId = (int) ($_POST['grupo_id'] ?? 0);
        $membroId = (int) ($_POST['membro_aluno_id'] ?? 0);
        if ($grupoId <= 0 || $membroId <= 0) {
            definir_flash('erro_painel', 'Dados invalidos para remover membro.');
            redirecionar('painel/aluno?pagina=grupos-estudo');
        }

        $ok = $this->painelOperacionalModel->removerMembroGrupoEstudo($grupoId, $alunoId, $membroId);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel remover o membro do grupo.');
            redirecionar('painel/aluno?pagina=grupos-estudo&grupo_id=' . $grupoId);
        }

        definir_flash('sucesso_painel', 'Membro removido do grupo.');
        redirecionar('painel/aluno?pagina=grupos-estudo&grupo_id=' . $grupoId);
    }

    public function aluno_sair_grupo(): void
    {
        $this->permitirPerfis(['aluno']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/aluno?pagina=grupos-estudo');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $alunoId = $this->painelOperacionalModel->obterAlunoIdPorUtilizador($utilizadorId);
        $grupoId = (int) ($_POST['grupo_id'] ?? 0);
        if ($grupoId <= 0) {
            definir_flash('erro_painel', 'Grupo invalido para sair.');
            redirecionar('painel/aluno?pagina=grupos-estudo');
        }

        $ok = $this->painelOperacionalModel->sairGrupoEstudo($grupoId, $alunoId);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel sair do grupo.');
            redirecionar('painel/aluno?pagina=grupos-estudo&grupo_id=' . $grupoId);
        }

        definir_flash('sucesso_painel', 'Saiu do grupo com sucesso.');
        redirecionar('painel/aluno?pagina=grupos-estudo');
    }

    public function aluno_eliminar_grupo(): void
    {
        $this->permitirPerfis(['aluno']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/aluno?pagina=grupos-estudo');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $alunoId = $this->painelOperacionalModel->obterAlunoIdPorUtilizador($utilizadorId);
        $grupoId = (int) ($_POST['grupo_id'] ?? 0);
        if ($grupoId <= 0) {
            definir_flash('erro_painel', 'Grupo invalido para eliminar.');
            redirecionar('painel/aluno?pagina=grupos-estudo');
        }

        $ok = $this->painelOperacionalModel->eliminarGrupoEstudo($grupoId, $alunoId);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel eliminar o grupo.');
            redirecionar('painel/aluno?pagina=grupos-estudo&grupo_id=' . $grupoId);
        }

        definir_flash('sucesso_painel', 'Grupo eliminado com sucesso.');
        redirecionar('painel/aluno?pagina=grupos-estudo');
    }

    public function aluno_enviar_mensagem_grupo(): void
    {
        $this->permitirPerfis(['aluno']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/aluno?pagina=grupos-estudo');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $alunoId = $this->painelOperacionalModel->obterAlunoIdPorUtilizador($utilizadorId);
        $grupoId = (int) ($_POST['grupo_id'] ?? 0);
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        if ($grupoId <= 0 || $mensagem === '') {
            definir_flash('erro_painel', 'Preencha a mensagem do grupo.');
            redirecionar('painel/aluno?pagina=grupos-estudo');
        }

        $ok = $this->painelOperacionalModel->enviarMensagemGrupoEstudo($grupoId, $alunoId, $mensagem);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel enviar mensagem ao grupo.');
            redirecionar('painel/aluno?pagina=grupos-estudo&grupo_id=' . $grupoId);
        }

        definir_flash('sucesso_painel', 'Mensagem enviada para o grupo.');
        redirecionar('painel/aluno?pagina=grupos-estudo&grupo_id=' . $grupoId);
    }

    public function aluno_eliminar_material(): void
    {
        $this->permitirPerfis(['aluno']);
        definir_flash('erro_painel', 'A secao de materiais foi descontinuada.');
        redirecionar('painel/aluno?pagina=documentos');
    }

    public function encarregado(?string $pagina = null): void
    {
        $this->permitirPerfis(['encarregado']);
        $paginasPermitidas = ['dashboard', 'educandos', 'notas', 'faltas', 'financeiro', 'pagamentos', 'comprovativos', 'documentos', 'comunicacao', 'mensagens', 'perfil'];
        $paginaPainel = $this->resolverPaginaPainel($pagina, $paginasPermitidas, 'dashboard');

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $encarregadoId = $this->painelOperacionalModel->obterEncarregadoIdPorUtilizador($utilizadorId);
        $educandos = $this->encarregadoModel->listarEducandos($utilizadorId);
        $educandosAtivos = array_filter($educandos, static function ($item): bool {
            return (int) ($item['aluno_id'] ?? 0) > 0;
        });

        if (empty($educandosAtivos)) {
            try {
                $db = Database::getInstancia();
                $stmt = $db->prepare('UPDATE utilizadores SET ativo = 0 WHERE id = :id');
                $stmt->execute(['id' => $utilizadorId]);
            } catch (Throwable $erro) {
                // Se falhar, segue com aviso sem bloquear.
            }

            definir_flash('erro_login', 'Perfil de encarregado desativado por ausencia de educandos ativos.');
            redirecionar('login/sair');
        }

        $resumoFinanceiro = $this->encarregadoModel->obterResumoFinanceiro($utilizadorId);
        $pagamentos = $this->encarregadoModel->listarPagamentosEducandos($utilizadorId, 8);
        $avisos = $this->funcionarioModel->listarAvisos('encarregados', 5);
        $zonasPainel = $this->painelEstrategicoModel->obterZonasPainel('encarregado', $utilizadorId);
        $comprovativosEncarregado = $this->painelOperacionalModel->listarComprovativosEncarregado($encarregadoId, 12);
        $solicitacoesDocumentosEncarregado = $this->painelOperacionalModel->listarSolicitacoesPorUtilizador($utilizadorId, 12);
        $documentosRecebidosEncarregado = $this->painelOperacionalModel->listarDocumentosRecebidosEncarregado($encarregadoId, 12);
        $boletinsEducandos = $this->painelOperacionalModel->listarBoletinsEncarregado($encarregadoId, 20);
        $professoresAtivos = $this->painelOperacionalModel->listarUtilizadoresPorPerfil('professor', 30);
        $secretariasAtivas = $this->painelOperacionalModel->listarUtilizadoresPorPerfil('secretaria', 10);
        $atividadesPublicadas = $this->painelOperacionalModel->listarAtividadesPublicadasIndex(20);
        $pagamentosAtividadesEncarregado = $this->painelOperacionalModel->listarPagamentosAtividadesEncarregado($encarregadoId);
        $mesesMensalidades = [
            ['valor' => '2026-01', 'rotulo' => 'Janeiro'],
            ['valor' => '2026-02', 'rotulo' => 'Fevereiro'],
            ['valor' => '2026-03', 'rotulo' => 'MarÃ§o'],
            ['valor' => '2026-04', 'rotulo' => 'Abril'],
            ['valor' => '2026-05', 'rotulo' => 'Maio'],
            ['valor' => '2026-06', 'rotulo' => 'Junho'],
            ['valor' => '2026-07', 'rotulo' => 'Julho'],
            ['valor' => '2026-08', 'rotulo' => 'Agosto'],
            ['valor' => '2026-09', 'rotulo' => 'Setembro'],
            ['valor' => '2026-10', 'rotulo' => 'Outubro'],
            ['valor' => '2026-11', 'rotulo' => 'Novembro'],
            ['valor' => '2026-12', 'rotulo' => 'Dezembro']
        ];
        extract($this->dadosPerfilComum($utilizadorId, 'painel/encarregado/' . $paginaPainel), EXTR_OVERWRITE);

        require CAMINHO_RAIZ . '/views/painel/encarregado.php';
    }

    public function encarregado_enviar_mensagem(): void
    {
        $this->permitirPerfis(['encarregado']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/encarregado');
        }

        $destinatarioId = (int) ($_POST['destinatario_id'] ?? 0);
        $perfilDestino = trim((string) ($_POST['perfil_destino'] ?? 'secretaria'));
        $assunto = trim((string) ($_POST['assunto'] ?? 'Mensagem do encarregado'));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        $respostaAId = (int) ($_POST['resposta_a_id'] ?? 0);

        if ($destinatarioId <= 0 || $mensagem === '') {
            definir_flash('erro_painel', 'Selecione o destinatario e preencha a mensagem.');
            redirecionar('painel/encarregado?pagina=mensagens');
        }

        $resultadoUploads = $this->processarUploadMultiplosArquivos(
            $_FILES['anexos'] ?? [],
            'storage/anexos',
            [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            5 * 1024 * 1024,
            'msg-encarregado'
        );

        if (!empty($resultadoUploads['erros'])) {
            definir_flash('erro_painel', (string) $resultadoUploads['erros'][0]);
            redirecionar('painel/encarregado?pagina=mensagens');
        }

        $resultado = $this->painelOperacionalModel->enviarMensagemInternaPainel([
            'remetente_id' => (int) $_SESSION['usuario_id'],
            'destinatario_id' => $destinatarioId,
            'assunto' => sanitizar_texto_simples($assunto, 180),
            'mensagem' => sanitizar_texto_simples($mensagem, 5000),
            'resposta_a_id' => $respostaAId,
            'anexos' => (array) ($resultadoUploads['ficheiros'] ?? [])
        ]);
        if (!($resultado['sucesso'] ?? false)) {
            definir_flash('erro_painel', 'Nao foi possivel enviar a mensagem.');
            redirecionar('painel/encarregado?pagina=mensagens');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Enviou mensagem interna', $assunto);
        definir_flash('sucesso_painel', (string) ($resultado['mensagem'] ?? 'Mensagem enviada com sucesso.'));
        redirecionar('painel/encarregado?pagina=mensagens');
    }

    public function encarregado_enviar_comprovativo_pagamento(): void
    {
        $this->permitirPerfis(['encarregado']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/encarregado');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $encarregadoId = $this->painelOperacionalModel->obterEncarregadoIdPorUtilizador($utilizadorId);
        if ($encarregadoId <= 0) {
            definir_flash('erro_painel', 'Encarregado nao encontrado para envio de comprovativo.');
            redirecionar('painel/encarregado?pagina=pagamentos');
        }

        $comprovativoFicheiro = $_FILES['comprovativo'] ?? ($_FILES['comprovativo_ficheiro'] ?? []);
        $comprovativoPath = $this->processarUploadArquivo($comprovativoFicheiro, 'uploads/comprovativos', ['application/pdf', 'image/jpeg', 'image/png'], 50 * 1024 * 1024, 'comprovativo');
        $dados = [
            'pagamento_id' => (int) ($_POST['pagamento_id'] ?? 0),
            'aluno_id' => (int) ($_POST['aluno_id'] ?? 0),
            'encarregado_id' => $encarregadoId,
            'mes_referencia' => trim((string) ($_POST['mes_referencia'] ?? '')),
            'valor' => (float) ($_POST['valor'] ?? 0),
            'comprovativo_path' => $comprovativoPath ?? ''
        ];

        if ($dados['aluno_id'] <= 0 || $dados['mes_referencia'] === '' || $dados['valor'] <= 0) {
            definir_flash('erro_painel', 'Preencha os dados do comprovativo de pagamento.');
            redirecionar('painel/encarregado?pagina=pagamentos');
        }

        if (!$this->encarregadoTemAcessoAoAluno($encarregadoId, (int) $dados['aluno_id'])) {
            definir_flash('erro_painel', 'Este educando nao esta associado ao seu perfil.');
            redirecionar('painel/encarregado?pagina=pagamentos');
        }

        $resultado = $this->painelOperacionalModel->criarComprovativoPagamento($dados);
        if (!($resultado['sucesso'] ?? false)) {
            definir_flash('erro_painel', 'Nao foi possivel registar o comprovativo de pagamento.');
            redirecionar('painel/encarregado?pagina=pagamentos');
        }

        $codigo = (string) ($resultado['codigo'] ?? '');
        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Enviou comprovativo de pagamento', 'Referencia ' . $codigo);
        definir_flash('sucesso_painel', 'Comprovativo enviado para analise da secretaria. Referencia: ' . $codigo);
        redirecionar('painel/encarregado?pagina=pagamentos');
    }

    public function encarregado_pagamento_simulado(): void
    {
        $this->permitirPerfis(['encarregado']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirecionar('painel/encarregado?pagina=financeiro');
        }

        $utilizadorId = (int) ($_SESSION['usuario_id'] ?? 0);
        $encarregadoId = $this->painelOperacionalModel->obterEncarregadoIdPorUtilizador($utilizadorId);
        if ($encarregadoId <= 0) {
            definir_flash('erro_painel', 'Encarregado nÃ£o encontrado para processar o pagamento.');
            redirecionar('painel/encarregado?pagina=financeiro');
        }

        $alunos = array_values(array_filter(array_map('intval', (array) ($_POST['alunos'] ?? [])), static fn(int $id): bool => $id > 0));
        if (empty($alunos)) {
            $alunoUnico = (int) ($_POST['aluno_id'] ?? 0);
            if ($alunoUnico > 0) {
                $alunos[] = $alunoUnico;
            }
        }

        $tipoPagamento = trim((string) ($_POST['tipo_pagamento'] ?? 'mensalidade'));
        if (!in_array($tipoPagamento, ['mensalidade', 'atividade'], true)) {
            $tipoPagamento = 'mensalidade';
        }

        $metodoPagamento = trim((string) ($_POST['metodo_pagamento'] ?? 'referencia'));
        if (!in_array($metodoPagamento, ['referencia', 'autorizacao'], true)) {
            $metodoPagamento = 'referencia';
        }

        $mesReferencia = trim((string) ($_POST['mes_referencia'] ?? date('Y-m')));
        if ($mesReferencia === '') {
            $mesReferencia = date('Y-m');
        }

        $atividadeId = (int) ($_POST['atividade_id'] ?? 0);
        if ($tipoPagamento === 'atividade' && $atividadeId <= 0) {
            definir_flash('erro_painel', 'Selecione uma actividade vÃ¡lida para pagamento.');
            redirecionar('painel/encarregado?pagina=financeiro');
        }

        $valorTotal = (float) ($_POST['valor_total'] ?? 0);
        if ($valorTotal <= 0) {
            $valorTotal = (float) ($_POST['valor'] ?? 0);
        }

        if (empty($alunos) || $valorTotal <= 0) {
            definir_flash('erro_painel', 'Informe alunos e valor para iniciar o pagamento.');
            redirecionar('painel/encarregado?pagina=financeiro');
        }

        $payload = [
            'encarregado_id' => $encarregadoId,
            'alunos' => $alunos,
            'tipo_pagamento' => $tipoPagamento,
            'metodo_pagamento' => $metodoPagamento,
            'mes_referencia' => $mesReferencia,
            'valor_total' => $valorTotal,
            'atividade_id' => $atividadeId
        ];

        $token = rtrim(strtr(base64_encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        redirecionar('pagamento/simulador?t=' . urlencode($token));
    }

    public function encarregado_solicitar_documento(): void
    {
        $this->permitirPerfis(['encarregado']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/encarregado');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $encarregadoId = $this->painelOperacionalModel->obterEncarregadoIdPorUtilizador($utilizadorId);
        $dados = [
            'aluno_id' => (int) ($_POST['aluno_id'] ?? 0),
            'encarregado_id' => $encarregadoId,
            'solicitado_por' => $utilizadorId,
            'tipo_documento' => trim((string) ($_POST['tipo_documento'] ?? 'boletim')),
            'observacao' => trim((string) ($_POST['observacao'] ?? ''))
        ];

        if ($dados['aluno_id'] <= 0) {
            definir_flash('erro_painel', 'Selecione um educando para solicitar documento.');
            redirecionar('painel/encarregado?pagina=documentos');
        }

        if (!$this->encarregadoTemAcessoAoAluno($encarregadoId, (int) $dados['aluno_id'])) {
            definir_flash('erro_painel', 'Este educando nao esta associado ao seu perfil.');
            redirecionar('painel/encarregado?pagina=documentos');
        }

        if ($dados['tipo_documento'] === 'boletim') {
            $this->painelOperacionalModel->solicitarBoletim((int) $dados['aluno_id'], '2o Trimestre', $utilizadorId, $encarregadoId);
        }

        if (!$this->painelOperacionalModel->solicitarDocumento($dados)) {
            definir_flash('erro_painel', 'Nao foi possivel criar a solicitacao de documento.');
            redirecionar('painel/encarregado?pagina=documentos');
        }

        $this->perfilModel->registarAtividade($utilizadorId, 'Solicitou documento', $dados['tipo_documento']);
        definir_flash('sucesso_painel', 'Solicitacao de documento enviada para a direcao pedagogica.');
        redirecionar('painel/encarregado?pagina=documentos');
    }

    public function encarregado_reclamar_pagamento(): void
    {
        $this->permitirPerfis(['encarregado']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/encarregado');
        }

        $assunto = trim((string) ($_POST['assunto'] ?? 'Reclamacao de pagamento'));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        if ($mensagem === '') {
            definir_flash('erro_painel', 'Escreva a reclamacao de pagamento.');
            redirecionar('painel/encarregado?pagina=pagamentos');
        }

        $secretarias = $this->painelOperacionalModel->listarUtilizadoresPorPerfil('secretaria', 1);
        $destinatarioId = (int) ($secretarias[0]['id'] ?? 0);
        if ($destinatarioId <= 0) {
            definir_flash('erro_painel', 'Nao foi encontrado utilizador de secretaria para receber reclamacao.');
            redirecionar('painel/encarregado?pagina=pagamentos');
        }

        $ok = $this->painelOperacionalModel->enviarMensagemInterna((int) $_SESSION['usuario_id'], $destinatarioId, 'secretaria', $assunto, $mensagem);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel enviar a reclamacao de pagamento.');
            redirecionar('painel/encarregado?pagina=pagamentos');
        }

        definir_flash('sucesso_painel', 'Reclamacao de pagamento enviada para a secretaria.');
        redirecionar('painel/encarregado?pagina=pagamentos');
    }

    public function professor(?string $pagina = null): void
    {
        $this->permitirPerfis(['professor']);
        $paginasPermitidas = ['dashboard', 'notas', 'faltas', 'sumarios', 'turmas', 'lancamentos', 'avaliacoes', 'presenca', 'comunicacao', 'relatorios', 'perfil'];
        $paginaPainel = $this->resolverPaginaPainel($pagina, $paginasPermitidas, 'dashboard');

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $resumoProfessor = $this->professorModel->obterResumoProfessor($utilizadorId);
        $turmasProfessor = $this->professorModel->listarTurmasProfessor($utilizadorId);
        $lancamentosRecentes = $this->professorModel->listarLancamentosRecentes($utilizadorId, 10);
        $avisos = $this->funcionarioModel->listarAvisos('professores', 5);
        $zonasPainel = $this->painelEstrategicoModel->obterZonasPainel('professor', $utilizadorId);
        $matriculasProfessor = $this->painelOperacionalModel->listarMatriculasTurmasProfessor($utilizadorId);
        $materiaisProfessor = $this->painelOperacionalModel->listarMateriaisProfessor($utilizadorId, 10);
        $encarregadosTurma = $this->painelOperacionalModel->listarEncarregadosTurmasProfessor($utilizadorId, 20);
        $salarioProfessor = $this->painelOperacionalModel->obterResumoSalarioProfessor($utilizadorId);
        $analiticaProfessor = $this->professorModel->obterAnaliticaProfessor($utilizadorId);

        $turmaSelecionada = (int) ($_GET['turma_id'] ?? 0);
        if ($turmaSelecionada <= 0 && !empty($turmasProfessor)) {
            $turmaSelecionada = (int) ($turmasProfessor[0]['id'] ?? 0);
        }

        $disciplinasDisponiveis = $this->painelOperacionalModel->listarDisciplinasProfessor($utilizadorId, $turmaSelecionada);
        $disciplinaSelecionada = (int) ($_GET['disciplina_id'] ?? 0);
        $disciplinasIds = array_map(static fn(array $disciplina): int => (int) ($disciplina['id'] ?? 0), (array) $disciplinasDisponiveis);
        if (($disciplinaSelecionada <= 0 || !in_array($disciplinaSelecionada, $disciplinasIds, true)) && !empty($disciplinasDisponiveis)) {
            $disciplinaSelecionada = (int) ($disciplinasDisponiveis[0]['id'] ?? 0);
        }

        $trimestreSelecionado = (int) ($_GET['trimestre'] ?? 1);
        if ($trimestreSelecionado < 1 || $trimestreSelecionado > 3) {
            $trimestreSelecionado = 1;
        }

        $dataPresenca = trim((string) ($_GET['data'] ?? date('Y-m-d')));
        if ($dataPresenca === '' || strtotime($dataPresenca) === false) {
            $dataPresenca = date('Y-m-d');
        }

        $alunosTurmaPresenca = $this->painelOperacionalModel->listarAlunosTurmaProfessor($utilizadorId, $turmaSelecionada, $disciplinaSelecionada);
        $notasTurmaDetalhadas = $this->painelOperacionalModel->listarNotasTurmaDetalhadas(
            $utilizadorId,
            $turmaSelecionada,
            $disciplinaSelecionada,
            $trimestreSelecionado
        );
        $historicoPresencaSemana = $this->painelOperacionalModel->listarPresencasTurmaSemana(
            $utilizadorId,
            $turmaSelecionada,
            $dataPresenca,
            $disciplinaSelecionada
        );
        $justificativasPendentesProfessor = $this->painelOperacionalModel->listarJustificativasPendentesProfessor($utilizadorId, $turmaSelecionada, $disciplinaSelecionada);
        $comunicacaoInternaDestinatarios = $this->painelOperacionalModel->listarUtilizadoresComunicacaoInterna($utilizadorId);
        $estadoMensagens = strtolower(trim((string) ($_GET['estado_mensagem'] ?? 'todas')));
        $comunicacaoInternaMensagens = $this->painelOperacionalModel->listarMensagensInternasPainel($utilizadorId, 120, $estadoMensagens);
        $comunicacaoInternaNaoLidas = $this->painelOperacionalModel->contarMensagensInternasPainelNaoLidas($utilizadorId);
        extract($this->dadosPerfilComum($utilizadorId, 'painel/professor/' . $paginaPainel), EXTR_OVERWRITE);

        require CAMINHO_RAIZ . '/views/painel/professor.php';
    }

    public function professor_lancar_nota(): void
    {
        $this->permitirPerfis(['professor']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/professor');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
        $trimestre = (int) ($_POST['trimestre'] ?? 0);

        $matriculas = (array) ($_POST['matricula_id'] ?? []);
        $testes = (array) ($_POST['teste'] ?? []);
        $trabalhos = (array) ($_POST['trabalho'] ?? []);
        $participacoes = (array) ($_POST['participacao'] ?? []);

        if ($turmaId <= 0 || $disciplinaId <= 0 || $trimestre < 1 || $trimestre > 3 || empty($matriculas)) {
            definir_flash('erro_painel', 'Selecione turma, disciplina e trimestre para lancar as notas.');
            redirecionar('painel/professor?pagina=avaliacoes');
        }

        $linhas = [];
        foreach ($matriculas as $indice => $matriculaId) {
            $linhas[] = [
                'matricula_id' => (int) $matriculaId,
                'teste' => (float) ($testes[$indice] ?? 0),
                'trabalho' => (float) ($trabalhos[$indice] ?? 0),
                'participacao' => (float) ($participacoes[$indice] ?? 0),
            ];
        }

        if (!$this->painelOperacionalModel->guardarNotasTurmaDetalhadas($utilizadorId, $turmaId, $disciplinaId, $trimestre, $linhas)) {
            definir_flash('erro_painel', 'Nao foi possivel lancar as notas da turma.');
            redirecionar('painel/professor?pagina=avaliacoes');
        }

        $this->perfilModel->registarAtividade($utilizadorId, 'Lancou notas em lote', 'Turma #' . $turmaId . ', disciplina #' . $disciplinaId . ', trimestre ' . $trimestre);
        definir_flash('sucesso_painel', 'Notas lancadas com sucesso.');
        redirecionar('painel/professor?pagina=avaliacoes&turma_id=' . $turmaId . '&disciplina_id=' . $disciplinaId . '&trimestre=' . $trimestre . '#secao-lancar-nota');
    }

    public function professor_registrar_presenca(): void
    {
        $this->permitirPerfis(['professor']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/professor');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
        $data = trim((string) ($_POST['data'] ?? ''));

        $matriculas = (array) ($_POST['matricula_id'] ?? []);
        $estados = (array) ($_POST['presenca_estado'] ?? []);
        $justificativas = (array) ($_POST['justificativa'] ?? []);

        if ($turmaId <= 0 || $disciplinaId <= 0 || $data === '' || strtotime($data) === false || empty($matriculas)) {
            definir_flash('erro_painel', 'Selecione turma, disciplina e data para registar presencas.');
            redirecionar('painel/professor?pagina=presenca');
        }

        $linhas = [];
        foreach ($matriculas as $matriculaId) {
            $matriculaId = (int) $matriculaId;
            $linhas[] = [
                'matricula_id' => $matriculaId,
                'presente' => (int) ($estados[$matriculaId] ?? 1),
                'justificativa' => (string) ($justificativas[$matriculaId] ?? '')
            ];
        }

        if (!$this->painelOperacionalModel->registarPresencasTurma($utilizadorId, $turmaId, $data, $linhas, $disciplinaId)) {
            definir_flash('erro_painel', 'Nao foi possivel guardar o registo de presencas.');
            redirecionar('painel/professor?pagina=presenca');
        }

        $this->perfilModel->registarAtividade($utilizadorId, 'Registou presencas da turma', 'Turma #' . $turmaId . ', disciplina #' . $disciplinaId . ' em ' . $data);
        definir_flash('sucesso_painel', 'Registo de presencas guardado com sucesso.');
        redirecionar('painel/professor?pagina=presenca&turma_id=' . $turmaId . '&disciplina_id=' . $disciplinaId . '&data=' . urlencode($data) . '#secao-presenca');
    }

    public function professor_enviar_material(): void
    {
        $this->permitirPerfis(['professor']);
        definir_flash('erro_painel', 'A secao de materiais foi descontinuada.');
        redirecionar('painel/professor?pagina=relatorios');
    }

    public function professor_remover_material(): void
    {
        $this->permitirPerfis(['professor']);
        definir_flash('erro_painel', 'A secao de materiais foi descontinuada.');
        redirecionar('painel/professor?pagina=relatorios');
    }

    public function professor_analisar_justificativa(): void
    {
        $this->permitirPerfis(['professor']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/professor?pagina=faltas');
        }

        $presencaId = (int) ($_POST['presenca_id'] ?? 0);
        $decisao = trim((string) ($_POST['decisao'] ?? ''));
        $observacao = trim((string) ($_POST['observacao'] ?? ''));
        if ($presencaId <= 0 || $decisao === '') {
            definir_flash('erro_painel', 'Dados invalidos para analisar justificativa.');
            redirecionar('painel/professor?pagina=faltas');
        }

        $ok = $this->painelOperacionalModel->analisarJustificativaProfessor((int) $_SESSION['usuario_id'], $presencaId, $decisao, $observacao);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel analisar a justificativa.');
            redirecionar('painel/professor?pagina=faltas');
        }

        definir_flash('sucesso_painel', 'Justificativa analisada com sucesso.');
        redirecionar('painel/professor?pagina=faltas');
    }

    public function professor_enviar_aviso_comportamento(): void
    {
        $this->permitirPerfis(['professor']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/professor');
        }

        $destinatarioId = (int) ($_POST['destinatario_id'] ?? 0);
        $assunto = trim((string) ($_POST['assunto'] ?? 'Aviso de comportamento'));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));

        if ($destinatarioId <= 0 || $mensagem === '') {
            definir_flash('erro_painel', 'Informe encarregado e mensagem do aviso.');
            redirecionar('painel/professor?pagina=comunicacao');
        }

        $ok = $this->painelOperacionalModel->enviarMensagemInterna((int) $_SESSION['usuario_id'], $destinatarioId, 'encarregado', $assunto, $mensagem);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel enviar o aviso ao encarregado.');
            redirecionar('painel/professor?pagina=comunicacao');
        }

        $emailDestino = $this->painelOperacionalModel->obterEmailUtilizador($destinatarioId);
        if ($emailDestino) {
            @mail(
                $emailDestino,
                '[FASCAL] ' . $assunto,
                $mensagem,
                'From: noreply@fascal.ao' . "\r\n" . 'Content-Type: text/plain; charset=UTF-8'
            );
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Enviou aviso de comportamento', 'Destinatario #' . $destinatarioId);
        definir_flash('sucesso_painel', 'Aviso de comportamento enviado com sucesso.');
        redirecionar('painel/professor?pagina=comunicacao');
    }

    public function professor_publicar_comunicado(): void
    {
        $this->permitirPerfis(['professor']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/professor');
        }

        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? 'Comunicado da turma'));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        if ($turmaId <= 0 || $mensagem === '') {
            definir_flash('erro_painel', 'Informe a mensagem do comunicado.');
            redirecionar('painel/professor?pagina=comunicacao');
        }

        $ok = $this->painelOperacionalModel->enviarComunicadoTurma((int) $_SESSION['usuario_id'], $turmaId, $titulo, $mensagem);
        if ($ok) {
            $this->painelOperacionalModel->criarComunicado([
            'titulo' => $titulo,
            'mensagem' => '[Turma #' . $turmaId . '] ' . $mensagem,
            'destinatarios' => 'alunos',
            'data_inicio' => '',
            'data_fim' => '',
            'criado_por' => (int) $_SESSION['usuario_id']
            ]);
        }

        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel publicar comunicado da turma.');
            redirecionar('painel/professor?pagina=comunicacao');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Publicou comunicado de turma', 'Turma #' . $turmaId);
        definir_flash('sucesso_painel', 'Comunicado publicado para todos os alunos da turma.');
        redirecionar('painel/professor?pagina=comunicacao');
    }

    public function professor_enviar_pauta(): void
    {
        $this->permitirPerfis(['professor']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/professor');
        }

        $tipo = trim((string) ($_POST['tipo_documento'] ?? 'Pauta de Notas'));
        $observacao = trim((string) ($_POST['observacao'] ?? 'Submissao de pauta para validacao pedagogica.'));
        $codigo = 'DOC-PAUTA-' . date('YmdHis');

        try {
            $db = Database::getInstancia();
            $stmt = $db->prepare(
                'INSERT INTO tramitacoes_documentais
                 (codigo, tipo_documento, origem_setor, destino_setor, referencia_id, status, observacao, criado_em, atualizado_em)
                 VALUES (:codigo, :tipo_documento, "professor", "direcao_pedagogica", NULL, "submetido", :observacao, NOW(), NOW())'
            );
            $stmt->execute([
                'codigo' => $codigo,
                'tipo_documento' => $tipo,
                'observacao' => $observacao
            ]);
        } catch (Throwable $erro) {
            definir_flash('erro_painel', 'Nao foi possivel submeter a pauta para a direcao pedagogica.');
            redirecionar('painel/professor?pagina=avaliacoes');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Enviou pauta para pedagogica', $codigo);
        definir_flash('sucesso_painel', 'Pauta enviada para a direcao pedagogica com sucesso.');
        redirecionar('painel/professor?pagina=avaliacoes');
    }

    public function secretaria(?string $pagina = null): void
    {
        $this->permitirPerfis(['secretaria']);
        $paginasPermitidas = ['dashboard', 'alunos', 'pre-matriculas', 'matriculas', 'documentos', 'financeiro', 'comunicacao', 'encarregados', 'comprovativos', 'atividades', 'perfil'];
        $paginaPainel = $this->resolverPaginaPainel($pagina, $paginasPermitidas, 'dashboard');

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $indicadoresSecretaria = $this->funcionarioModel->obterIndicadoresSecretaria();
        $preMatriculasRecentes = $this->preMatriculaModel->listarRecentes(10);
        $avisos = $this->funcionarioModel->listarAvisos('funcionarios', 5);
        $mensagensSecretaria = $this->mensagemSecretariaModel->listarRecentes(10);
        $totalMensagensNaoLidas = $this->mensagemSecretariaModel->contarNaoLidas();
        $zonasPainel = $this->painelEstrategicoModel->obterZonasPainel('secretaria', $utilizadorId);
        $tramitacoesDocumentais = $this->painelEstrategicoModel->listarTramitacoesDocumentais(8);
        $preMatriculasDetalhadas = $this->painelOperacionalModel->listarPreMatriculasDetalhadas(12);
        $turmasDisponiveis = $this->painelOperacionalModel->listarTurmasSimples();
        $encarregadosDisponiveis = $this->painelOperacionalModel->listarEncarregadosDisponiveis(50);
        $alunosDisponiveisSecretaria = $this->painelOperacionalModel->listarAlunosDisponiveisSecretaria(80);
        $documentosPartilhadosSecretaria = $this->painelOperacionalModel->listarDocumentosPartilhadosSecretaria(20);
        $comprovativosPagamento = $this->painelOperacionalModel->listarComprovativosPagamento(15, ['pendente']);
        $comprovativosHistorico = $this->painelOperacionalModel->listarComprovativosPagamento(20, ['aprovado', 'rejeitado']);
        $atividadesExtracurriculares = $this->painelOperacionalModel->listarAtividadesExtracurriculares(12);
        $comunicacaoInternaDestinatarios = $this->painelOperacionalModel->listarUtilizadoresComunicacaoInterna($utilizadorId);
        $estadoMensagens = strtolower(trim((string) ($_GET['estado_mensagem'] ?? 'todas')));
        $comunicacaoInternaMensagens = $this->painelOperacionalModel->listarMensagensInternasPainel($utilizadorId, 120, $estadoMensagens);
        $comunicacaoInternaNaoLidas = $this->painelOperacionalModel->contarMensagensInternasPainelNaoLidas($utilizadorId);
        extract($this->dadosPerfilComum($utilizadorId, 'painel/secretaria/' . $paginaPainel), EXTR_OVERWRITE);

        require CAMINHO_RAIZ . '/views/painel/secretaria.php';
    }

    public function secretaria_concluir_matricula(): void
    {
        $this->permitirPerfis(['secretaria']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/secretaria');
        }

        $dados = [
            'pre_matricula_id' => (int) ($_POST['pre_matricula_id'] ?? 0),
            'turma_id' => (int) ($_POST['turma_id'] ?? 0),
            'nome_aluno' => trim((string) ($_POST['nome_aluno'] ?? '')),
            'data_nascimento_aluno' => trim((string) ($_POST['data_nascimento_aluno'] ?? '')),
            'bi_aluno' => trim((string) ($_POST['bi_aluno'] ?? '')),
            'genero_aluno' => trim((string) ($_POST['genero_aluno'] ?? '')),
            'endereco_aluno' => trim((string) ($_POST['endereco_aluno'] ?? '')),
            'contacto_aluno' => trim((string) ($_POST['contacto_aluno'] ?? '')),
            'encarregado_id_existente' => (int) ($_POST['encarregado_id_existente'] ?? 0),
            'nome_encarregado' => trim((string) ($_POST['nome_encarregado'] ?? '')),
            'email_encarregado' => trim((string) ($_POST['email_encarregado'] ?? '')),
            'telefone_encarregado' => trim((string) ($_POST['telefone_encarregado'] ?? '')),
            'endereco_encarregado' => trim((string) ($_POST['endereco_encarregado'] ?? '')),
            'parentesco' => trim((string) ($_POST['parentesco'] ?? '')),
        ];

        if ($dados['turma_id'] <= 0 || $dados['nome_aluno'] === '' || $dados['data_nascimento_aluno'] === '') {
            definir_flash('erro_painel', 'Preencha os dados obrigatorios da matricula completa.');
            redirecionar('painel/secretaria?pagina=matriculas');
        }

        if ($dados['encarregado_id_existente'] <= 0 && $dados['nome_encarregado'] === '') {
            definir_flash('erro_painel', 'Informe o encarregado ou selecione um ID existente.');
            redirecionar('painel/secretaria?pagina=matriculas');
        }

        $resultado = $this->painelOperacionalModel->concluirMatriculaCompleta($dados);
        if (!($resultado['sucesso'] ?? false)) {
            definir_flash('erro_painel', (string) ($resultado['mensagem'] ?? 'Nao foi possivel concluir matricula.'));
            redirecionar('painel/secretaria?pagina=matriculas');
        }

        $alunoId = (int) ($resultado['aluno_id'] ?? 0);
        if ($alunoId > 0) {
            $docs = [
                'foto_1' => $this->processarUploadArquivo($_FILES['foto_1'] ?? [], 'uploads/matriculas', ['image/jpeg', 'image/png', 'image/webp'], 4 * 1024 * 1024, 'foto1'),
                'bi_copia' => $this->processarUploadArquivo($_FILES['bi_copia'] ?? [], 'uploads/matriculas', ['application/pdf', 'image/jpeg', 'image/png'], 8 * 1024 * 1024, 'bi'),
                'documento_classe_anterior' => $this->processarUploadArquivo($_FILES['doc_classe_anterior'] ?? [], 'uploads/matriculas', ['application/pdf', 'image/jpeg', 'image/png'], 8 * 1024 * 1024, 'classe'),
                'observacoes' => trim((string) ($_POST['observacoes_documentos'] ?? ''))
            ];
            $this->painelOperacionalModel->guardarDocumentosMatricula($alunoId, $docs);
        }

        $credenciaisAluno = $resultado['credenciais_aluno'] ?? [];
        $credenciaisEncarregado = $resultado['credenciais_encarregado'] ?? null;
        $turmaSelecionada = $this->painelOperacionalModel->obterTurmaPorId((int) $dados['turma_id']);
        $turmaTexto = '';
        if (!empty($turmaSelecionada)) {
            $partesTurma = [trim((string) ($turmaSelecionada['nome'] ?? ''))];
            if (!empty($turmaSelecionada['sala'])) {
                $partesTurma[] = 'Sala ' . trim((string) $turmaSelecionada['sala']);
            }
            if (!empty($turmaSelecionada['ano_letivo'])) {
                $partesTurma[] = trim((string) $turmaSelecionada['ano_letivo']);
            }
            $turmaTexto = implode(' • ', array_filter($partesTurma, static fn(string $valor): bool => $valor !== ''));
        }

        $mensagem = "Conta do aluno criada com sucesso.\n";
        if ($turmaTexto !== '') {
            $mensagem .= 'Turma atribuída: ' . $turmaTexto . "\n";
        }
        $mensagem .= 'Aluno: ' . (($credenciaisAluno['email'] ?? '') ?: '-') . ' / ' . (($credenciaisAluno['senha'] ?? '') ?: '-') . "\n";
        if (is_array($credenciaisEncarregado)) {
            $mensagem .= 'Encarregado: ' . (($credenciaisEncarregado['email'] ?? '') ?: '-') . ' / ' . (($credenciaisEncarregado['senha'] ?? '') ?: '-') . "\n";
        } else {
            $mensagem .= "Encarregado associado por ID existente.\n";
        }
        $mensagem .= 'As credenciais são temporárias e a senha deve ser alterada no primeiro login.';

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Concluiu matricula', 'Aluno: ' . $dados['nome_aluno']);
        definir_flash('sucesso_painel', $mensagem);
        redirecionar('painel/secretaria?pagina=matriculas');
    }

    public function secretaria_analisar_comprovativo(): void
    {
        $this->permitirPerfis(['secretaria']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/secretaria');
        }

        $id = (int) ($_POST['comprovativo_id'] ?? 0);
        $estado = trim((string) ($_POST['estado'] ?? ''));
        $observacao = trim((string) ($_POST['observacao'] ?? ''));

        if ($id <= 0 || $estado === '') {
            definir_flash('erro_painel', 'Dados invalidos para analise do comprovativo.');
            redirecionar('painel/secretaria?pagina=comprovativos');
        }

        if (!$this->painelOperacionalModel->analisarComprovativo($id, $estado, $observacao, (int) $_SESSION['usuario_id'])) {
            definir_flash('erro_painel', 'Nao foi possivel analisar o comprovativo.');
            redirecionar('painel/secretaria?pagina=comprovativos');
        }

        $encarregadoUtilizadorId = $this->painelOperacionalModel->obterUtilizadorEncarregadoPorComprovativo($id);
        if ($encarregadoUtilizadorId > 0) {
            $mensagemEstado = $estado === 'aprovado'
                ? 'Pagamento aprovado com sucesso.'
                : 'Pagamento rejeitado. Verifique as observacoes da secretaria.';
            $this->painelOperacionalModel->enviarMensagemInterna(
                (int) $_SESSION['usuario_id'],
                $encarregadoUtilizadorId,
                'encarregado',
                'Atualizacao de comprovativo de pagamento',
                $mensagemEstado . ($observacao !== '' ? ' ' . $observacao : '')
            );
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Analisou comprovativo', 'Comprovativo #' . $id . ' -> ' . $estado);
        definir_flash('sucesso_painel', 'Comprovativo analisado com sucesso.');
        redirecionar('painel/secretaria?pagina=comprovativos');
    }

    public function secretaria_partilhar_documento(): void
    {
        $this->permitirPerfis(['secretaria']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/secretaria?pagina=documentos');
        }

        $ficheiro = $this->processarUploadArquivo(
            $_FILES['documento_ficheiro'] ?? [],
            'uploads/documentos',
            [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            10 * 1024 * 1024,
            'documento'
        );

        $resultado = $this->painelOperacionalModel->partilharDocumentoSecretaria([
            'perfil_destino' => trim((string) ($_POST['perfil_destino'] ?? '')),
            'aluno_id' => (int) ($_POST['aluno_id'] ?? 0),
            'encarregado_id' => (int) ($_POST['encarregado_id'] ?? 0),
            'titulo' => trim((string) ($_POST['titulo'] ?? '')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'ficheiro_path' => $ficheiro ?? '',
            'criado_por' => (int) $_SESSION['usuario_id'],
        ]);

        if (!($resultado['sucesso'] ?? false)) {
            definir_flash('erro_painel', (string) ($resultado['mensagem'] ?? 'Nao foi possivel partilhar o documento.'));
            redirecionar('painel/secretaria?pagina=documentos');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Partilhou documento', (string) ($_POST['titulo'] ?? 'Documento'));
        definir_flash('sucesso_painel', (string) ($resultado['mensagem'] ?? 'Documento partilhado com sucesso.'));
        redirecionar('painel/secretaria?pagina=documentos');
    }

    public function secretaria_enviar_mensagem_encarregado(): void
    {
        $this->permitirPerfis(['secretaria']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/secretaria?pagina=encarregados');
        }

        $encarregadoId = (int) ($_POST['encarregado_id'] ?? 0);
        $assunto = trim((string) ($_POST['assunto'] ?? 'Mensagem da secretaria'));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        $respostaAId = (int) ($_POST['resposta_a_id'] ?? 0);

        if ($encarregadoId <= 0 || $mensagem === '') {
            definir_flash('erro_painel', 'Selecione um encarregado e escreva a mensagem.');
            redirecionar('painel/secretaria?pagina=encarregados');
        }

        $resultadoUploads = $this->processarUploadMultiplosArquivos(
            $_FILES['anexos'] ?? [],
            'storage/anexos',
            [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            5 * 1024 * 1024,
            'msg-secretaria'
        );

        if (!empty($resultadoUploads['erros'])) {
            definir_flash('erro_painel', (string) $resultadoUploads['erros'][0]);
            redirecionar('painel/secretaria?pagina=encarregados');
        }

        $resultado = $this->painelOperacionalModel->enviarMensagemInternaPainel([
            'remetente_id' => (int) $_SESSION['usuario_id'],
            'destinatario_id' => $encarregadoId,
            'assunto' => sanitizar_texto_simples($assunto, 180),
            'mensagem' => sanitizar_texto_simples($mensagem, 5000),
            'resposta_a_id' => $respostaAId,
            'anexos' => (array) ($resultadoUploads['ficheiros'] ?? [])
        ]);

        if (!($resultado['sucesso'] ?? false)) {
            definir_flash('erro_painel', 'Nao foi possivel enviar a mensagem ao encarregado.');
            redirecionar('painel/secretaria?pagina=encarregados');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Enviou mensagem a encarregado', $assunto);
        definir_flash('sucesso_painel', (string) ($resultado['mensagem'] ?? 'Mensagem enviada ao encarregado com sucesso.'));
        redirecionar('painel/secretaria?pagina=encarregados');
    }

    public function secretaria_enviar_mensagem_aluno(): void
    {
        $this->permitirPerfis(['secretaria']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/secretaria?pagina=alunos');
        }

        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $assunto = trim((string) ($_POST['assunto'] ?? 'Mensagem da secretaria'));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        $respostaAId = (int) ($_POST['resposta_a_id'] ?? 0);

        if ($alunoId <= 0 || $mensagem === '') {
            definir_flash('erro_painel', 'Selecione um aluno e escreva a mensagem.');
            redirecionar('painel/secretaria?pagina=alunos');
        }

        $resultadoUploads = $this->processarUploadMultiplosArquivos(
            $_FILES['anexos'] ?? [],
            'storage/anexos',
            [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            5 * 1024 * 1024,
            'msg-secretaria-aluno'
        );

        if (!empty($resultadoUploads['erros'])) {
            definir_flash('erro_painel', (string) $resultadoUploads['erros'][0]);
            redirecionar('painel/secretaria?pagina=alunos');
        }

        $resultado = $this->painelOperacionalModel->enviarMensagemInternaPainel([
            'remetente_id' => (int) $_SESSION['usuario_id'],
            'destinatario_id' => $alunoId,
            'assunto' => sanitizar_texto_simples($assunto, 180),
            'mensagem' => sanitizar_texto_simples($mensagem, 5000),
            'resposta_a_id' => $respostaAId,
            'anexos' => (array) ($resultadoUploads['ficheiros'] ?? [])
        ]);

        if (!($resultado['sucesso'] ?? false)) {
            definir_flash('erro_painel', 'Nao foi possivel enviar a mensagem ao aluno.');
            redirecionar('painel/secretaria?pagina=alunos');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Enviou mensagem a aluno', $assunto);
        definir_flash('sucesso_painel', (string) ($resultado['mensagem'] ?? 'Mensagem enviada ao aluno com sucesso.'));
        redirecionar('painel/secretaria?pagina=alunos');
    }

    public function secretaria_criar_atividade(): void
    {
        $this->permitirPerfis(['secretaria']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/secretaria');
        }

        $imagem = $this->processarUploadArquivo($_FILES['imagem_atividade'] ?? [], 'uploads/atividades', ['image/jpeg', 'image/png', 'image/webp'], 5 * 1024 * 1024, 'atividade');
        $dados = [
            'tema' => trim((string) ($_POST['tema'] ?? '')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'categoria' => trim((string) ($_POST['categoria'] ?? 'eventos')),
            'preco' => (float) ($_POST['preco'] ?? 0),
            'imagem_path' => $imagem ?? '',
            'publicado_index' => isset($_POST['publicado_index']) ? 1 : 0,
            'criado_por' => (int) $_SESSION['usuario_id']
        ];

        if ($dados['tema'] === '' || $dados['descricao'] === '') {
            definir_flash('erro_painel', 'Tema e descricao sao obrigatorios para atividade.');
            redirecionar('painel/secretaria?pagina=atividades');
        }

        if (!$this->painelOperacionalModel->criarAtividadeExtracurricular($dados)) {
            definir_flash('erro_painel', 'Nao foi possivel criar a atividade.');
            redirecionar('painel/secretaria?pagina=atividades');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Criou atividade extracurricular', $dados['tema']);
        definir_flash('sucesso_painel', 'Atividade criada com sucesso.');
        redirecionar('painel/secretaria?pagina=atividades');
    }

    public function secretaria_atualizar_atividade(): void
    {
        $this->permitirPerfis(['secretaria']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/secretaria');
        }

        $atividadeId = (int) ($_POST['atividade_id'] ?? 0);
        $imagemNova = $this->processarUploadArquivo($_FILES['imagem_atividade'] ?? [], 'uploads/atividades', ['image/jpeg', 'image/png', 'image/webp'], 5 * 1024 * 1024, 'atividade');
        $imagemAtual = trim((string) ($_POST['imagem_atual'] ?? ''));
        $dados = [
            'id' => $atividadeId,
            'tema' => trim((string) ($_POST['tema'] ?? '')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'categoria' => trim((string) ($_POST['categoria'] ?? 'eventos')),
            'preco' => (float) ($_POST['preco'] ?? 0),
            'imagem_path' => $imagemNova ?? $imagemAtual,
            'publicado_index' => isset($_POST['publicado_index']) ? 1 : 0
        ];

        if ($atividadeId <= 0 || $dados['tema'] === '' || $dados['descricao'] === '') {
            definir_flash('erro_painel', 'Dados invalidos para atualizar atividade.');
            redirecionar('painel/secretaria?pagina=atividades');
        }

        if (!$this->painelOperacionalModel->atualizarAtividadeExtracurricular($dados)) {
            definir_flash('erro_painel', 'Nao foi possivel atualizar atividade.');
            redirecionar('painel/secretaria?pagina=atividades');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Atualizou atividade extracurricular', $dados['tema']);
        definir_flash('sucesso_painel', 'Atividade atualizada com sucesso.');
        redirecionar('painel/secretaria?pagina=atividades');
    }

    public function secretaria_remover_atividade(): void
    {
        $this->permitirPerfis(['secretaria']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/secretaria');
        }

        $atividadeId = (int) ($_POST['atividade_id'] ?? 0);
        if ($atividadeId <= 0) {
            definir_flash('erro_painel', 'Atividade invalida para remocao.');
            redirecionar('painel/secretaria?pagina=atividades');
        }

        if (!$this->painelOperacionalModel->removerAtividadeExtracurricular($atividadeId)) {
            definir_flash('erro_painel', 'Nao foi possivel remover atividade.');
            redirecionar('painel/secretaria?pagina=atividades');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Removeu atividade extracurricular', 'Atividade #' . $atividadeId . ' removida.');
        definir_flash('sucesso_painel', 'Atividade removida com sucesso.');
        redirecionar('painel/secretaria?pagina=atividades');
    }

    public function direcao_pedagogica(?string $pagina = null): void
    {
        $this->permitirPerfis(['direcao_pedagogica']);
        $paginasPermitidas = ['dashboard', 'dashboard-pedagogico', 'cursos', 'cursos-disciplinas', 'disciplinas', 'professores', 'avaliacoes', 'calendario', 'calendario-academico', 'relatorios', 'relatorios-pedagogicos', 'notificacoes', 'solicitacoes', 'atividades', 'perfil'];
        $paginaPainel = $this->resolverPaginaPainel($pagina, $paginasPermitidas, 'dashboard');

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $indicadoresPedagogicos = $this->funcionarioModel->obterIndicadoresDirecaoPedagogica();
        $turmasAtivas = $this->turmaModel->listarTurmasAtivas(8);
        $rankingDisciplinas = $this->notaModel->rankingDisciplinas(6);
        $avisos = $this->funcionarioModel->listarAvisos('funcionarios', 5);
        $zonasPainel = $this->painelEstrategicoModel->obterZonasPainel('direcao_pedagogica', $utilizadorId);
        $solicitacoesDocumentos = $this->painelOperacionalModel->listarSolicitacoesDocumentos(15);
        $cursosPedagogicos = $this->painelOperacionalModel->listarCursos(15);
        $disciplinasDisponiveis = $this->painelOperacionalModel->listarDisciplinasSimples();
        $atividadesExtracurriculares = $this->painelOperacionalModel->listarAtividadesExtracurriculares(12);
        $analiticaAtividades = $this->painelOperacionalModel->obterAnaliticaAtividades(20);
        $comunicacaoInternaDestinatarios = $this->painelOperacionalModel->listarUtilizadoresComunicacaoInterna($utilizadorId);
        $estadoMensagens = strtolower(trim((string) ($_GET['estado_mensagem'] ?? 'todas')));
        $comunicacaoInternaMensagens = $this->painelOperacionalModel->listarMensagensInternasPainel($utilizadorId, 120, $estadoMensagens);
        $comunicacaoInternaNaoLidas = $this->painelOperacionalModel->contarMensagensInternasPainelNaoLidas($utilizadorId);
        extract($this->dadosPerfilComum($utilizadorId, 'painel/direcao-pedagogica/' . $paginaPainel), EXTR_OVERWRITE);

        require CAMINHO_RAIZ . '/views/painel/direcao-pedagogica.php';
    }

    public function pedagogica_processar_solicitacao(): void
    {
        $this->permitirPerfis(['direcao_pedagogica']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/direcao-pedagogica');
        }

        $id = (int) ($_POST['solicitacao_id'] ?? 0);
        $estado = trim((string) ($_POST['estado'] ?? ''));
        $observacao = trim((string) ($_POST['observacao'] ?? ''));

        if ($id <= 0 || $estado === '') {
            definir_flash('erro_painel', 'Dados invalidos para processar solicitacao.');
            redirecionar('painel/direcao-pedagogica?pagina=solicitacoes#secao-solicitacoes');
        }

        $ok = $this->painelOperacionalModel->processarSolicitacaoDocumento($id, $estado, (int) $_SESSION['usuario_id'], $observacao);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel atualizar a solicitacao.');
            redirecionar('painel/direcao-pedagogica?pagina=solicitacoes#secao-solicitacoes');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Processou solicitacao documental', 'Solicitacao #' . $id . ' -> ' . $estado);
        definir_flash('sucesso_painel', 'Solicitacao documental atualizada com sucesso.');
        redirecionar('painel/direcao-pedagogica?pagina=solicitacoes#secao-solicitacoes');
    }

    public function pedagogica_criar_curso(): void
    {
        $this->permitirPerfis(['direcao_pedagogica']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/direcao-pedagogica');
        }

        $imagemCurso = $this->processarUploadArquivo(
            $_FILES['imagem_curso'] ?? [],
            'uploads/cursos',
            ['image/jpeg', 'image/png', 'image/webp'],
            5 * 1024 * 1024,
            'curso'
        );

        $dados = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'cor' => trim((string) ($_POST['cor'] ?? '')),
            'imagem_path' => $imagemCurso ?? '',
            'ano_curso' => trim((string) ($_POST['ano_curso'] ?? '')),
            'quantidade_disciplinas' => (int) ($_POST['quantidade_disciplinas'] ?? 0),
            'publicado_index' => isset($_POST['publicado_index']) ? 1 : 0,
            'disciplinas' => array_map('intval', (array) ($_POST['disciplinas'] ?? [])),
            'criado_por' => (int) $_SESSION['usuario_id']
        ];

        if ($dados['nome'] === '' || $dados['ano_curso'] === '' || $dados['quantidade_disciplinas'] <= 0) {
            definir_flash('erro_painel', 'Preencha os dados obrigatorios do curso.');
            redirecionar('painel/direcao-pedagogica?pagina=cursos#secao-cursos');
        }

        if (!$this->painelOperacionalModel->criarCurso($dados)) {
            definir_flash('erro_painel', 'Nao foi possivel criar o curso.');
            redirecionar('painel/direcao-pedagogica?pagina=cursos#secao-cursos');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Criou curso', $dados['nome']);
        definir_flash('sucesso_painel', 'Curso criado com sucesso.');
        redirecionar('painel/direcao-pedagogica?pagina=cursos#secao-cursos');
    }

    public function pedagogica_atualizar_curso(): void
    {
        $this->permitirPerfis(['direcao_pedagogica']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/direcao-pedagogica');
        }

        $imagemNova = $this->processarUploadArquivo(
            $_FILES['imagem_curso'] ?? [],
            'uploads/cursos',
            ['image/jpeg', 'image/png', 'image/webp'],
            5 * 1024 * 1024,
            'curso'
        );

        $dados = [
            'id' => (int) ($_POST['curso_id'] ?? 0),
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'cor' => trim((string) ($_POST['cor'] ?? '')),
            'imagem_path' => $imagemNova ?? trim((string) ($_POST['imagem_atual'] ?? '')),
            'ano_curso' => trim((string) ($_POST['ano_curso'] ?? '')),
            'quantidade_disciplinas' => (int) ($_POST['quantidade_disciplinas'] ?? 0),
            'publicado_index' => isset($_POST['publicado_index']) ? 1 : 0,
            'disciplinas' => array_map('intval', (array) ($_POST['disciplinas'] ?? []))
        ];

        if ($dados['id'] <= 0 || $dados['nome'] === '' || $dados['ano_curso'] === '' || $dados['quantidade_disciplinas'] <= 0) {
            definir_flash('erro_painel', 'Dados invalidos para atualizar o curso.');
            redirecionar('painel/direcao-pedagogica?pagina=cursos#secao-cursos');
        }

        if (!$this->painelOperacionalModel->atualizarCurso($dados)) {
            definir_flash('erro_painel', 'Nao foi possivel atualizar o curso.');
            redirecionar('painel/direcao-pedagogica?pagina=cursos#secao-cursos');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Atualizou curso', $dados['nome']);
        definir_flash('sucesso_painel', 'Curso atualizado com sucesso.');
        redirecionar('painel/direcao-pedagogica?pagina=cursos#secao-cursos');
    }

    public function pedagogica_remover_curso(): void
    {
        $this->permitirPerfis(['direcao_pedagogica']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/direcao-pedagogica');
        }

        $cursoId = (int) ($_POST['curso_id'] ?? 0);
        if ($cursoId <= 0) {
            definir_flash('erro_painel', 'Curso invalido para remocao.');
            redirecionar('painel/direcao-pedagogica?pagina=cursos#secao-cursos');
        }

        if (!$this->painelOperacionalModel->removerCurso($cursoId)) {
            definir_flash('erro_painel', 'Nao foi possivel remover o curso.');
            redirecionar('painel/direcao-pedagogica?pagina=cursos#secao-cursos');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Removeu curso', 'Curso #' . $cursoId . ' removido.');
        definir_flash('sucesso_painel', 'Curso removido com sucesso.');
        redirecionar('painel/direcao-pedagogica?pagina=cursos#secao-cursos');
    }

    public function pedagogica_criar_atividade(): void
    {
        $this->permitirPerfis(['direcao_pedagogica']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/direcao-pedagogica');
        }

        $imagem = $this->processarUploadArquivo($_FILES['imagem_atividade'] ?? [], 'uploads/atividades', ['image/jpeg', 'image/png', 'image/webp'], 5 * 1024 * 1024, 'atividade');
        $dados = [
            'tema' => trim((string) ($_POST['tema'] ?? '')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'categoria' => trim((string) ($_POST['categoria'] ?? 'eventos')),
            'data_atividade' => trim((string) ($_POST['data_atividade'] ?? '')),
            'preco' => (float) ($_POST['preco'] ?? 0),
            'imagem_path' => $imagem ?? '',
            'publicado_index' => isset($_POST['publicado_index']) ? 1 : 0,
            'criado_por' => (int) $_SESSION['usuario_id']
        ];

        if ($dados['tema'] === '' || $dados['descricao'] === '') {
            definir_flash('erro_painel', 'Tema e descricao sao obrigatorios para atividade.');
            redirecionar('painel/direcao-pedagogica?pagina=atividades#secao-atividades');
        }

        if (!$this->painelOperacionalModel->criarAtividadeExtracurricular($dados)) {
            definir_flash('erro_painel', 'Nao foi possivel criar atividade.');
            redirecionar('painel/direcao-pedagogica?pagina=atividades#secao-atividades');
        }

        definir_flash('sucesso_painel', 'Atividade extracurricular criada com sucesso.');
        redirecionar('painel/direcao-pedagogica?pagina=atividades#secao-atividades');
    }

    public function pedagogica_atualizar_atividade(): void
    {
        $this->permitirPerfis(['direcao_pedagogica']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/direcao-pedagogica');
        }

        $atividadeId = (int) ($_POST['atividade_id'] ?? 0);
        $imagemNova = $this->processarUploadArquivo($_FILES['imagem_atividade'] ?? [], 'uploads/atividades', ['image/jpeg', 'image/png', 'image/webp'], 5 * 1024 * 1024, 'atividade');
        $dados = [
            'id' => $atividadeId,
            'tema' => trim((string) ($_POST['tema'] ?? '')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'categoria' => trim((string) ($_POST['categoria'] ?? 'eventos')),
            'data_atividade' => trim((string) ($_POST['data_atividade'] ?? '')),
            'preco' => (float) ($_POST['preco'] ?? 0),
            'imagem_path' => $imagemNova ?? trim((string) ($_POST['imagem_atual'] ?? '')),
            'publicado_index' => isset($_POST['publicado_index']) ? 1 : 0
        ];

        if ($atividadeId <= 0 || $dados['tema'] === '' || $dados['descricao'] === '') {
            definir_flash('erro_painel', 'Dados invalidos para atualizar atividade.');
            redirecionar('painel/direcao-pedagogica?pagina=atividades#secao-atividades');
        }

        if (!$this->painelOperacionalModel->atualizarAtividadeExtracurricular($dados)) {
            definir_flash('erro_painel', 'Nao foi possivel atualizar atividade.');
            redirecionar('painel/direcao-pedagogica?pagina=atividades#secao-atividades');
        }

        definir_flash('sucesso_painel', 'Atividade extracurricular atualizada com sucesso.');
        redirecionar('painel/direcao-pedagogica?pagina=atividades#secao-atividades');
    }

    public function pedagogica_remover_atividade(): void
    {
        $this->permitirPerfis(['direcao_pedagogica']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/direcao-pedagogica');
        }

        $atividadeId = (int) ($_POST['atividade_id'] ?? 0);
        if ($atividadeId <= 0) {
            definir_flash('erro_painel', 'Atividade invalida para remocao.');
            redirecionar('painel/direcao-pedagogica?pagina=atividades#secao-atividades');
        }

        if (!$this->painelOperacionalModel->removerAtividadeExtracurricular($atividadeId)) {
            definir_flash('erro_painel', 'Nao foi possivel remover atividade.');
            redirecionar('painel/direcao-pedagogica?pagina=atividades#secao-atividades');
        }

        definir_flash('sucesso_painel', 'Atividade extracurricular removida com sucesso.');
        redirecionar('painel/direcao-pedagogica?pagina=atividades#secao-atividades');
    }

    public function direcao_geral(?string $pagina = null): void
    {
        $this->permitirPerfis(['direcao_geral']);
        $paginasPermitidas = ['dashboard', 'instituicao', 'instituicao-config', 'anos-letivos', 'organigrama', 'rel-financeiro', 'rel-academico', 'rel-rh', 'rel-indicadores', 'indicadores', 'emitir-comunicado', 'gerir-avisos', 'comunicados', 'avisos', 'aprovacoes', 'gerir-perfis', 'utilizadores', 'auditoria', 'notificacoes', 'mensagens', 'perfil'];
        $paginaPainel = $this->resolverPaginaPainel($pagina, $paginasPermitidas, 'dashboard');

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $indicadoresGerais = $this->funcionarioModel->obterIndicadoresDirecaoGeral();
        $relatorioSetorial = $this->funcionarioModel->obterRelatorioSetorial();
        $avisos = $this->funcionarioModel->listarAvisos('todos', 6);
        $zonasPainel = $this->painelEstrategicoModel->obterZonasPainel('direcao_geral', $utilizadorId);
        $historicoComunicados = $this->painelOperacionalModel->listarComunicadosPorUtilizador($utilizadorId, 12);
        $anosLetivos = $this->painelOperacionalModel->listarAnosLetivos();
        $tramitacoesDocumentais = $this->painelOperacionalModel->listarTramitacoesPorDestino('direcao_geral', 20);
        $analiticaDirecaoGeral = $this->funcionarioModel->obterAnaliticaDirecaoGeral();
        $backupStatus = $this->backupModel->obterStatus();
        $comunicacaoInternaDestinatarios = $this->painelOperacionalModel->listarUtilizadoresComunicacaoInterna($utilizadorId);
        $estadoMensagens = strtolower(trim((string) ($_GET['estado_mensagem'] ?? 'todas')));
        $comunicacaoInternaMensagens = $this->painelOperacionalModel->listarMensagensInternasPainel($utilizadorId, 120, $estadoMensagens);
        $comunicacaoInternaNaoLidas = $this->painelOperacionalModel->contarMensagensInternasPainelNaoLidas($utilizadorId);
        extract($this->dadosPerfilComum($utilizadorId, 'painel/direcao-geral/' . $paginaPainel), EXTR_OVERWRITE);

        require CAMINHO_RAIZ . '/views/painel/direcao-geral.php';
    }

    public function direcao_enviar_comunicado(): void
    {
        $this->permitirPerfis(['direcao_geral']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/direcao-geral');
        }

        $dados = [
            'titulo' => trim((string) ($_POST['titulo'] ?? '')),
            'mensagem' => trim((string) ($_POST['mensagem'] ?? '')),
            'destinatarios' => trim((string) ($_POST['destinatarios'] ?? 'todos')),
            'data_inicio' => trim((string) ($_POST['data_inicio'] ?? '')),
            'data_fim' => trim((string) ($_POST['data_fim'] ?? '')),
            'criado_por' => (int) $_SESSION['usuario_id']
        ];

        if ($dados['titulo'] === '' || $dados['mensagem'] === '') {
            definir_flash('erro_painel', 'Informe titulo e mensagem do comunicado.');
            redirecionar('painel/direcao-geral?pagina=comunicados');
        }

        if (!$this->painelOperacionalModel->criarComunicado($dados)) {
            definir_flash('erro_painel', 'Nao foi possivel publicar o comunicado.');
            redirecionar('painel/direcao-geral?pagina=comunicados');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Publicou comunicado', $dados['titulo']);
        definir_flash('sucesso_painel', 'Comunicado publicado com sucesso.');
        redirecionar('painel/direcao-geral?pagina=comunicados');
    }

    public function direcao_enviar_mensagem_massa(): void
    {
        $this->permitirPerfis(['direcao_geral']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/direcao-geral');
        }

        $perfilDestino = trim((string) ($_POST['perfil_destino'] ?? 'todos'));
        $assunto = trim((string) ($_POST['assunto'] ?? ''));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));

        if ($assunto === '' || $mensagem === '') {
            definir_flash('erro_painel', 'Preencha assunto e mensagem para envio em massa.');
            redirecionar('painel/direcao-geral?pagina=mensagens');
        }

        $ok = $this->painelOperacionalModel->criarMensagemMassa((int) $_SESSION['usuario_id'], $perfilDestino, $assunto, $mensagem);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel enviar a mensagem em massa.');
            redirecionar('painel/direcao-geral?pagina=mensagens');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Enviou mensagem em massa', $assunto . ' [' . $perfilDestino . ']');
        definir_flash('sucesso_painel', 'Mensagem em massa enviada com sucesso.');
        redirecionar('painel/direcao-geral?pagina=mensagens');
    }

    public function tramitacao_acao(): void
    {
        $this->permitirPerfis(['direcao_geral', 'direcao_pedagogica', 'secretaria', 'rh']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar($this->rotaPorPerfil(perfil_atual()));
        }

        $id = (int) ($_POST['tramitacao_id'] ?? 0);
        $acao = trim((string) ($_POST['acao'] ?? ''));
        $observacao = trim((string) ($_POST['observacao'] ?? ''));
        if ($id <= 0 || $acao === '') {
            definir_flash('erro_painel', 'Dados invalidos para atualizar a tramitacao.');
            redirecionar($this->rotaPorPerfil(perfil_atual()));
        }

        $ok = $this->painelOperacionalModel->aplicarAcaoTramitacao($id, perfil_atual(), (int) $_SESSION['usuario_id'], $acao, $observacao);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel atualizar a tramitacao.');
            redirecionar($this->rotaPorPerfil(perfil_atual()));
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Atualizou tramitacao documental', 'Tramitacao #' . $id . ' => ' . $acao);
        definir_flash('sucesso_painel', 'Tramitacao atualizada com sucesso.');
        redirecionar($this->rotaPorPerfil(perfil_atual()) . '?pagina=aprovacoes');
    }

    public function comunicacao_interna_enviar(): void
    {
        $this->permitirPerfis(['secretaria', 'rh', 'professor', 'direcao_geral', 'direcao_pedagogica']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar($this->rotaPorPerfil(perfil_atual()));
        }

        $utilizadorId = (int) ($_SESSION['usuario_id'] ?? 0);
        $origem = $this->resolverRotaOrigem((string) ($_POST['origem'] ?? ''));
        $destinatarioId = (int) ($_POST['destinatario_id'] ?? 0);
        $assunto = trim((string) ($_POST['assunto'] ?? ''));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        $respostaAId = (int) ($_POST['resposta_a_id'] ?? 0);

        $resultadoUploads = $this->processarUploadMultiplosArquivos(
            $_FILES['anexos'] ?? [],
            'uploads/mensagens-internas',
            [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain'
            ],
            12 * 1024 * 1024,
            'msgint'
        );

        if (!empty($resultadoUploads['erros'])) {
            foreach ((array) ($resultadoUploads['ficheiros'] ?? []) as $anexoValido) {
                $caminhoRelativo = trim((string) ($anexoValido['caminho_ficheiro'] ?? ''));
                if ($caminhoRelativo === '') {
                    continue;
                }

                $caminhoAbsoluto = CAMINHO_RAIZ . '/' . ltrim($caminhoRelativo, '/');
                if (is_file($caminhoAbsoluto)) {
                    @unlink($caminhoAbsoluto);
                }
            }

            definir_flash('erro_painel', (string) $resultadoUploads['erros'][0]);
            redirecionar($origem);
        }

        $anexos = (array) ($resultadoUploads['ficheiros'] ?? []);
        $resultado = $this->painelOperacionalModel->enviarMensagemInternaPainel([
            'remetente_id' => $utilizadorId,
            'destinatario_id' => $destinatarioId,
            'assunto' => $assunto,
            'mensagem' => $mensagem,
            'resposta_a_id' => $respostaAId,
            'anexos' => $anexos
        ]);

        if (!($resultado['sucesso'] ?? false)) {
            foreach ($anexos as $anexo) {
                $caminhoRelativo = trim((string) ($anexo['caminho_ficheiro'] ?? ''));
                if ($caminhoRelativo === '') {
                    continue;
                }

                $caminhoAbsoluto = CAMINHO_RAIZ . '/' . ltrim($caminhoRelativo, '/');
                if (is_file($caminhoAbsoluto)) {
                    @unlink($caminhoAbsoluto);
                }
            }

            definir_flash('erro_painel', (string) ($resultado['mensagem'] ?? 'Nao foi possivel enviar a mensagem interna.'));
            redirecionar($origem);
        }

        $this->perfilModel->registarAtividade($utilizadorId, 'Enviou comunicacao interna', $assunto !== '' ? $assunto : ('Mensagem #' . (int) ($resultado['mensagem_id'] ?? 0)));
        definir_flash('sucesso_painel', (string) ($resultado['mensagem'] ?? 'Mensagem enviada com sucesso.'));
        redirecionar($origem);
    }

    public function comunicacao_interna_marcar_lidas(): void
    {
        $this->permitirPerfis(['secretaria', 'rh', 'professor', 'direcao_geral', 'direcao_pedagogica']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar($this->rotaPorPerfil(perfil_atual()));
        }

        $utilizadorId = (int) ($_SESSION['usuario_id'] ?? 0);
        $origem = $this->resolverRotaOrigem((string) ($_POST['origem'] ?? ''));
        $ids = array_map('intval', (array) ($_POST['mensagens'] ?? []));

        $ok = $this->painelOperacionalModel->marcarMensagensInternasPainelComoLidas($utilizadorId, $ids);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel atualizar o estado das mensagens.');
            redirecionar($origem);
        }

        definir_flash('sucesso_painel', 'Mensagens atualizadas como lidas.');
        redirecionar($origem);
    }

    public function brevemente(): void
    {
        $perfil = (string) ($_SESSION['perfil'] ?? '');
        $nomePerfil = nome_perfil($perfil);
        $mensagem = 'Brevemente';
        require CAMINHO_RAIZ . '/views/painel/brevemente.php';
    }

    public function rh(?string $pagina = null): void
    {
        $this->permitirPerfis(['rh']);
        $paginasPermitidas = ['dashboard', 'dashboard-rh', 'funcionarios', 'contratos', 'assiduidade', 'ferias', 'candidaturas', 'relatorios', 'ano-letivo', 'perfil'];
        $paginaPainel = $this->resolverPaginaPainel($pagina, $paginasPermitidas, 'dashboard');

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $indicadoresRh = $this->funcionarioModel->obterIndicadoresRh();
        $funcionarios = $this->funcionarioModel->listarFuncionariosRh(12);
        $avisos = $this->funcionarioModel->listarAvisos('funcionarios', 5);
        $zonasPainel = $this->painelEstrategicoModel->obterZonasPainel('rh', $utilizadorId);
        $candidaturasDocentes = $this->painelEstrategicoModel->listarCandidaturasDocentes(10);
        $anosLetivos = $this->painelOperacionalModel->listarAnosLetivos();
        $comunicacaoInternaDestinatarios = $this->painelOperacionalModel->listarUtilizadoresComunicacaoInterna($utilizadorId);
        $estadoMensagens = strtolower(trim((string) ($_GET['estado_mensagem'] ?? 'todas')));
        $comunicacaoInternaMensagens = $this->painelOperacionalModel->listarMensagensInternasPainel($utilizadorId, 120, $estadoMensagens);
        $comunicacaoInternaNaoLidas = $this->painelOperacionalModel->contarMensagensInternasPainelNaoLidas($utilizadorId);
        extract($this->dadosPerfilComum($utilizadorId, 'painel/rh/' . $paginaPainel), EXTR_OVERWRITE);

        require CAMINHO_RAIZ . '/views/painel/rh.php';
    }

    public function rh_candidatura_estado(): void
    {
        $this->permitirPerfis(['rh']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/rh');
        }

        $candidaturaId = (int) ($_POST['candidatura_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));

        if ($candidaturaId <= 0 || $status === '') {
            definir_flash('erro_painel', 'Dados invalidos para atualizar candidatura.');
            redirecionar('painel/rh');
        }

        if (!$this->painelOperacionalModel->atualizarStatusCandidaturaDocente($candidaturaId, $status)) {
            definir_flash('erro_painel', 'Nao foi possivel atualizar o estado da candidatura.');
            redirecionar('painel/rh');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Atualizou candidatura', 'Candidatura #' . $candidaturaId . ' alterada para ' . $status . '.');
        definir_flash('sucesso_painel', 'Estado da candidatura atualizado com sucesso.');
        redirecionar('painel/rh?pagina=candidaturas#secao-candidaturas');
    }

    public function rh_registar_funcionario(): void
    {
        $this->permitirPerfis(['rh']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/rh');
        }

        $dados = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'email' => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL) ?: '',
            'senha_inicial' => trim((string) ($_POST['senha_inicial'] ?? '')),
            'perfil_acesso' => trim((string) ($_POST['perfil_acesso'] ?? 'rh')),
            'cargo' => trim((string) ($_POST['cargo'] ?? '')),
            'departamento' => trim((string) ($_POST['departamento'] ?? 'Recursos Humanos')),
            'telefone' => trim((string) ($_POST['telefone'] ?? '')),
            'data_contratacao' => trim((string) ($_POST['data_contratacao'] ?? ''))
        ];

        if ($dados['nome'] === '' || $dados['cargo'] === '' || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            definir_flash('erro_painel', 'Preencha nome, cargo e um email institucional valido para registar funcionario.');
            redirecionar('painel/rh?pagina=funcionarios#secao-equipa');
        }

        $resultado = $this->painelOperacionalModel->registarFuncionario($dados);
        if (!($resultado['sucesso'] ?? false)) {
            definir_flash('erro_painel', (string) ($resultado['mensagem'] ?? 'Falha ao registar funcionario.'));
            redirecionar('painel/rh?pagina=funcionarios#secao-equipa');
        }

        $mensagem = (string) ($resultado['mensagem'] ?? 'Funcionario registado com sucesso.');
        if (!empty($resultado['email']) && !empty($resultado['senha'])) {
            $mensagem .= ' Credenciais: ' . $resultado['email'] . ' / ' . $resultado['senha'];
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Registou funcionario', 'Novo colaborador: ' . $dados['nome']);
        definir_flash('sucesso_painel', $mensagem);
        redirecionar('painel/rh?pagina=funcionarios#secao-equipa');
    }

    public function rh_registar_demissao(): void
    {
        $this->permitirPerfis(['rh']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/rh');
        }

        $funcionarioId = (int) ($_POST['funcionario_id'] ?? 0);
        if ($funcionarioId <= 0) {
            definir_flash('erro_painel', 'Funcionario invalido para demissao.');
            redirecionar('painel/rh?pagina=funcionarios#secao-equipa');
        }

        if (!$this->painelOperacionalModel->registarDemissao($funcionarioId)) {
            definir_flash('erro_painel', 'Nao foi possivel registar a demissao.');
            redirecionar('painel/rh?pagina=funcionarios#secao-equipa');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Registou demissao', 'Funcionario #' . $funcionarioId . ' foi desativado.');
        definir_flash('sucesso_painel', 'Demissao registada com sucesso.');
        redirecionar('painel/rh?pagina=funcionarios#secao-equipa');
    }

    public function rh_criar_ano_letivo(): void
    {
        $this->permitirPerfis(['rh']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/rh');
        }

        $referencia = trim((string) ($_POST['referencia'] ?? ''));
        if ($referencia === '') {
            definir_flash('erro_painel', 'Informe a referencia do ano letivo.');
            redirecionar('painel/rh?pagina=ano-letivo#secao-ano-letivo');
        }

        $ok = $this->painelOperacionalModel->criarAnoLetivo($referencia, (int) $_SESSION['usuario_id']);
        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel criar o ano letivo.');
            redirecionar('painel/rh?pagina=ano-letivo#secao-ano-letivo');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Criou ano letivo', 'Ano letivo ' . $referencia . ' registado.');
        definir_flash('sucesso_painel', 'Ano letivo criado com sucesso.');
        redirecionar('painel/rh?pagina=ano-letivo#secao-ano-letivo');
    }

    public function rh_ativar_ano_letivo(): void
    {
        $this->permitirPerfis(['rh']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel/rh');
        }

        $anoId = (int) ($_POST['ano_id'] ?? 0);
        if ($anoId <= 0) {
            definir_flash('erro_painel', 'Selecione um ano letivo valido.');
            redirecionar('painel/rh?pagina=ano-letivo#secao-ano-letivo');
        }

        if (!$this->painelOperacionalModel->definirAnoLetivoAtivo($anoId)) {
            definir_flash('erro_painel', 'Nao foi possivel ativar o ano letivo.');
            redirecionar('painel/rh?pagina=ano-letivo#secao-ano-letivo');
        }

        $this->perfilModel->registarAtividade((int) $_SESSION['usuario_id'], 'Atualizou ano letivo', 'Ano letivo ativo alterado.');
        definir_flash('sucesso_painel', 'Ano letivo ativo atualizado com sucesso.');
        redirecionar('painel/rh?pagina=ano-letivo#secao-ano-letivo');
    }

    public function atualizar_perfil(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $origem = $this->resolverRotaOrigem((string) ($_POST['origem'] ?? ''));

        $nome = trim((string) ($_POST['nome'] ?? ''));
        $telefone = trim((string) ($_POST['telefone'] ?? ''));
        $endereco = trim((string) ($_POST['endereco'] ?? ''));
        $sobreMim = trim((string) ($_POST['sobre_mim'] ?? ''));

        if ($nome === '') {
            definir_flash('erro_painel', 'Informe o nome para atualizar o perfil.');
            redirecionar($origem);
        }

        $perfilAtual = $this->perfilModel->obterPerfilCompleto($utilizadorId);
        $fotoAtual = (string) ($perfilAtual['foto'] ?? '');
        $novaFoto = $this->processarUploadImagemPerfil($_FILES['foto'] ?? [], $utilizadorId);
        $fotoFinal = $novaFoto !== null ? $novaFoto : $fotoAtual;

        $ok = $this->perfilModel->atualizarPerfil($utilizadorId, [
            'nome' => $nome,
            'telefone' => $telefone,
            'endereco' => $endereco,
            'sobre_mim' => $sobreMim,
            'foto' => $fotoFinal
        ]);

        if (!$ok) {
            definir_flash('erro_painel', 'Nao foi possivel atualizar o perfil.');
            redirecionar($origem);
        }

        $_SESSION['usuario_nome'] = $nome;
        $this->perfilModel->registarAtividade($utilizadorId, 'Atualizou perfil', 'Dados pessoais atualizados no painel.');
        definir_flash('sucesso_painel', 'Perfil atualizado com sucesso.');
        redirecionar($origem);
    }

    public function alterar_senha(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('painel');
        }

        $utilizadorId = (int) $_SESSION['usuario_id'];
        $origem = $this->resolverRotaOrigem((string) ($_POST['origem'] ?? ''));

        $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
        $novaSenha = (string) ($_POST['nova_senha'] ?? '');
        $confirmacao = (string) ($_POST['confirmacao_senha'] ?? '');

        if ($senhaAtual === '' || $novaSenha === '' || $confirmacao === '') {
            definir_flash('erro_painel', 'Preencha os campos de senha.');
            redirecionar($origem);
        }

        if (strlen($novaSenha) < 8) {
            definir_flash('erro_painel', 'A nova senha deve ter pelo menos 8 caracteres.');
            redirecionar($origem);
        }

        if ($novaSenha !== $confirmacao) {
            definir_flash('erro_painel', 'A confirmacao da senha nao confere.');
            redirecionar($origem);
        }

        $resultado = $this->perfilModel->atualizarSenha($utilizadorId, $senhaAtual, $novaSenha);
        if (!($resultado['sucesso'] ?? false)) {
            definir_flash('erro_painel', (string) ($resultado['mensagem'] ?? 'Nao foi possivel atualizar a senha.'));
            redirecionar($origem);
        }

        $this->perfilModel->registarAtividade($utilizadorId, 'Alterou senha', 'Senha de acesso atualizada.');
        definir_flash('sucesso_painel', 'Senha alterada com sucesso.');
        redirecionar($origem);
    }

    private function permitirPerfis(array $perfis): void
    {
        if (!in_array(perfil_atual(), $perfis, true)) {
            definir_flash('erro_login', 'Nao tem permissao para aceder a esta area.');
            redirecionar($this->rotaPorPerfil(perfil_atual()));
        }
    }

    private function dadosPerfilComum(int $utilizadorId, string $rotaPainelAtual): array
    {
        $perfilUtilizador = $this->perfilModel->obterPerfilCompleto($utilizadorId);

        return [
            'perfilUtilizador' => $perfilUtilizador,
            'historicoAtividades' => $this->perfilModel->listarHistorico($utilizadorId, 8),
            'configEscola' => $this->configuracaoModel->obter(),
            'sucessoPainel' => obter_flash('sucesso_painel'),
            'erroPainel' => obter_flash('erro_painel'),
            'alertaSenhaTemporaria' => (int) ($perfilUtilizador['senha_temporaria'] ?? 0) === 1,
            'rotaPainelAtual' => $rotaPainelAtual
        ];
    }

    private function resolverRotaOrigem(string $origem): string
    {
        $origem = trim($origem);
        if ($origem === '' || !str_starts_with($origem, 'painel/')) {
            return $this->rotaPorPerfil(perfil_atual());
        }

        return $origem;
    }

    private function resolverPaginaPainel(?string $paginaRota, array $paginasPermitidas, string $paginaPadrao): string
    {
        $paginaCandidata = trim((string) $paginaRota);
        if ($paginaCandidata === '') {
            $paginaCandidata = trim((string) ($_GET['pagina'] ?? ''));
        }

        $paginaCandidata = strtolower($paginaCandidata);
        $paginaCandidata = str_replace('_', '-', $paginaCandidata);
        $paginaCandidata = preg_replace('/[^a-z0-9\-]+/', '-', $paginaCandidata) ?? '';
        $paginaCandidata = trim(preg_replace('/-+/', '-', $paginaCandidata) ?? '', '-');

        if ($paginaCandidata === '' || !in_array($paginaCandidata, $paginasPermitidas, true)) {
            return $paginaPadrao;
        }

        return $paginaCandidata;
    }

    private function encarregadoTemAcessoAoAluno(int $encarregadoId, int $alunoId): bool
    {
        if ($encarregadoId <= 0 || $alunoId <= 0) {
            return false;
        }

        try {
            $db = Database::getInstancia();
            $stmt = $db->prepare('SELECT COUNT(*) FROM encarregado_aluno WHERE encarregado_id = :encarregado_id AND aluno_id = :aluno_id');
            $stmt->execute([
                'encarregado_id' => $encarregadoId,
                'aluno_id' => $alunoId
            ]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $erro) {
            return false;
        }
    }

    private function processarUploadImagemPerfil(array $ficheiro, int $utilizadorId): ?string
    {
        if (($ficheiro['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($ficheiro['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmp = (string) ($ficheiro['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return null;
        }

        if ((int) ($ficheiro['size'] ?? 0) > 2 * 1024 * 1024) {
            return null;
        }

        $mime = mime_content_type($tmp) ?: '';
        $mapaMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($mapaMime[$mime])) {
            return null;
        }

        $pastaFisica = CAMINHO_RAIZ . '/uploads/perfis';
        if (!is_dir($pastaFisica)) {
            mkdir($pastaFisica, 0775, true);
        }

        $extensao = $mapaMime[$mime];
        $nomeFicheiro = 'perfil-' . $utilizadorId . '-' . time() . '.' . $extensao;
        $destino = $pastaFisica . '/' . $nomeFicheiro;

        if (!move_uploaded_file($tmp, $destino)) {
            return null;
        }

        return 'uploads/perfis/' . $nomeFicheiro;
    }

    private function processarUploadArquivo(array $ficheiro, string $pastaRelativa, array $mimesPermitidos, int $maxBytes, string $prefixo): ?string
    {
        if (($ficheiro['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($ficheiro['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmp = (string) ($ficheiro['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return null;
        }

        if ((int) ($ficheiro['size'] ?? 0) > $maxBytes) {
            return null;
        }

        $mime = mime_content_type($tmp) ?: '';
        if (!in_array($mime, $mimesPermitidos, true)) {
            return null;
        }

        $mapaExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt'
        ];
        $ext = $mapaExt[$mime] ?? pathinfo((string) ($ficheiro['name'] ?? ''), PATHINFO_EXTENSION);
        $ext = strtolower((string) $ext);
        if ($ext === '') {
            $ext = 'bin';
        }

        $pastaFisica = CAMINHO_RAIZ . '/' . trim($pastaRelativa, '/');
        if (!is_dir($pastaFisica)) {
            mkdir($pastaFisica, 0775, true);
        }

        $nomeFicheiro = $prefixo . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $pastaFisica . '/' . $nomeFicheiro;
        if (!move_uploaded_file($tmp, $destino)) {
            return null;
        }

        return trim($pastaRelativa, '/') . '/' . $nomeFicheiro;
    }

    private function processarUploadMultiplosArquivos(
        array $ficheirosEntrada,
        string $pastaRelativa,
        array $mimesPermitidos,
        int $maxBytes,
        string $prefixo
    ): array {
        $resultado = [
            'ficheiros' => [],
            'erros' => []
        ];

        $nomes = $ficheirosEntrada['name'] ?? [];
        $tipos = $ficheirosEntrada['type'] ?? [];
        $tmpNames = $ficheirosEntrada['tmp_name'] ?? [];
        $erros = $ficheirosEntrada['error'] ?? [];
        $tamanhos = $ficheirosEntrada['size'] ?? [];

        if (!is_array($nomes) || !is_array($tmpNames) || !is_array($erros) || !is_array($tamanhos)) {
            return $resultado;
        }

        foreach (array_keys($nomes) as $indice) {
            $erroUpload = (int) ($erros[$indice] ?? UPLOAD_ERR_NO_FILE);
            if ($erroUpload === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($erroUpload !== UPLOAD_ERR_OK) {
                $resultado['erros'][] = 'Falha no envio de um dos anexos.';
                continue;
            }

            $ficheiroIndividual = [
                'name' => (string) ($nomes[$indice] ?? ''),
                'type' => (string) ($tipos[$indice] ?? ''),
                'tmp_name' => (string) ($tmpNames[$indice] ?? ''),
                'error' => $erroUpload,
                'size' => (int) ($tamanhos[$indice] ?? 0)
            ];
            $tipoMimeDetectado = (string) (mime_content_type((string) ($ficheiroIndividual['tmp_name'] ?? '')) ?: '');

            $caminhoRelativo = $this->processarUploadArquivo(
                $ficheiroIndividual,
                $pastaRelativa,
                $mimesPermitidos,
                $maxBytes,
                $prefixo
            );

            if ($caminhoRelativo === null) {
                $resultado['erros'][] = 'Um anexo nao foi aceite. Verifique formato e tamanho.';
                continue;
            }

            $resultado['ficheiros'][] = [
                'caminho_ficheiro' => $caminhoRelativo,
                'nome_original' => (string) ($ficheiroIndividual['name'] ?? 'ficheiro'),
                'tipo_mime' => $tipoMimeDetectado !== '' ? $tipoMimeDetectado : ((string) ($ficheiroIndividual['type'] ?? 'application/octet-stream')),
                'tamanho_bytes' => (int) ($ficheiroIndividual['size'] ?? 0)
            ];
        }

        return $resultado;
    }

    private function rotaPorPerfil(string $perfil): string
    {
        $mapa = [
            'aluno' => 'painel/aluno',
            'encarregado' => 'painel/encarregado',
            'professor' => 'painel/professor',
            'secretaria' => 'painel/secretaria',
            'direcao_pedagogica' => 'painel/direcao-pedagogica',
            'direcao_geral' => 'painel/direcao-geral',
            'rh' => 'painel/rh'
        ];

        return $mapa[$perfil] ?? 'painel';
    }
}

