<?php

use Dompdf\Dompdf;

class DocumentosController
{
    private $documentoModel;

    public function __construct()
    {
        if (!usuario_logado()) {
            definir_flash('erro_login', 'Sessao expirada. Inicie sessao novamente.');
            redirecionar('login');
        }

        if (!in_array(perfil_atual(), ['secretaria', 'direcao_pedagogica'], true)) {
            definir_flash('erro_login', 'Nao tem permissao para emitir documentos oficiais.');
            redirecionar('painel');
        }

        if (is_file(CAMINHO_RAIZ . '/vendor/autoload.php')) {
            require_once CAMINHO_RAIZ . '/vendor/autoload.php';
        }

        $this->documentoModel = new DocumentoModel();
    }

    public function index(): void
    {
        $alunos = $this->documentoModel->listarAlunosDisponiveis();
        $historico = $this->documentoModel->listarHistorico(12);
        $mensagem = obter_flash('sucesso_documento');
        $erro = obter_flash('erro_documento');

        require CAMINHO_RAIZ . '/views/documentos.php';
    }

    public function dados_aluno(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        if ($alunoId <= 0) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Aluno invalido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $dadosAluno = $this->documentoModel->obterDadosAlunoDocumento($alunoId);
        if (!$dadosAluno) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Aluno nao encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $resumo = $this->documentoModel->obterResumoAcademico($alunoId);
        $notas = $this->documentoModel->obterNotasBoletim($alunoId);

        echo json_encode([
            'sucesso' => true,
            'aluno' => $dadosAluno,
            'resumo' => $resumo,
            'notas' => $notas
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function gerar_pdf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('documentos');
        }

        $tipoDocumento = trim((string) ($_POST['tipo_documento'] ?? ''));
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

        $tiposValidos = ['declaracao', 'certificado', 'boletim'];
        if (!in_array($tipoDocumento, $tiposValidos, true) || $alunoId <= 0) {
            definir_flash('erro_documento', 'Selecione um aluno e um tipo de documento valido.');
            redirecionar('documentos');
        }

        $dadosAluno = $this->documentoModel->obterDadosAlunoDocumento($alunoId);
        if (!$dadosAluno) {
            definir_flash('erro_documento', 'Aluno nao encontrado para emissao do documento.');
            redirecionar('documentos');
        }

        $resumo = $this->documentoModel->obterResumoAcademico($alunoId);
        $notas = $this->documentoModel->obterNotasBoletim($alunoId);

        $html = $this->montarTemplateDocumento($tipoDocumento, $dadosAluno, $resumo, $notas, $observacoes);
        $nomeFicheiro = $this->gerarNomeFicheiro($tipoDocumento, $dadosAluno['nome_aluno']);

        if (!class_exists(Dompdf::class)) {
            definir_flash('erro_documento', 'Biblioteca de PDF indisponivel. Execute composer install e tente novamente.');
            redirecionar('documentos');
        }

        error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        $dompdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->documentoModel->registarEmissao($tipoDocumento, $alunoId, (int) $_SESSION['usuario_id'], $nomeFicheiro);
        (new PerfilModel())->registarAtividade((int) $_SESSION['usuario_id'], 'Emitiu documento', $tipoDocumento . ' para aluno #' . $alunoId);

        $dompdf->stream($nomeFicheiro, ['Attachment' => true]);
        exit;
    }

    private function gerarNomeFicheiro(string $tipoDocumento, string $nomeAluno): string
    {
        $nomeSeguro = preg_replace('/[^a-z0-9]+/i', '-', strtolower($nomeAluno));
        $nomeSeguro = trim((string) $nomeSeguro, '-');
        return $tipoDocumento . '-' . $nomeSeguro . '-' . date('Ymd-His') . '.pdf';
    }

    private function montarTemplateDocumento(
        string $tipoDocumento,
        array $dadosAluno,
        array $resumo,
        array $notas,
        string $observacoes
    ): string {
        $cabecalho = $this->templateCabecalho($tipoDocumento);
        $rodape = $this->templateRodape();

        if ($tipoDocumento === 'boletim') {
            $corpo = $this->templateBoletim($dadosAluno, $resumo, $notas, $observacoes);
        } elseif ($tipoDocumento === 'certificado') {
            $corpo = $this->templateCertificado($dadosAluno, $resumo, $observacoes);
        } else {
            $corpo = $this->templateDeclaracao($dadosAluno, $resumo, $observacoes);
        }

        return '
        <!doctype html>
        <html lang="pt">
        <head>
          <meta charset="UTF-8">
          <style>
            body { font-family: DejaVu Sans, Arial, sans-serif; margin: 36px; color: #212121; }
            .topo { border-bottom: 3px solid #0056a3; padding-bottom: 12px; margin-bottom: 20px; }
            .topo h1 { margin: 0; color: #0056a3; font-size: 22px; }
            .topo p { margin: 4px 0 0; color: #424242; }
            .destaque { background: #ffe082; padding: 10px; border-left: 4px solid #ffc107; margin: 14px 0; }
            .assinatura { margin-top: 46px; }
            .assinatura p { margin: 2px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            th, td { border: 1px solid #e0e0e0; padding: 7px; text-align: left; }
            th { background: #f5f5f5; }
            .rodape { margin-top: 26px; border-top: 1px solid #e0e0e0; padding-top: 10px; color: #424242; font-size: 12px; }
          </style>
        </head>
        <body>' . $cabecalho . $corpo . $rodape . '</body>
        </html>';
    }

    private function templateCabecalho(string $tipoDocumento): string
    {
        $titulos = [
            'declaracao' => 'Declaracao de Frequencia',
            'certificado' => 'Certificado Escolar',
            'boletim' => 'Boletim de Aproveitamento'
        ];

        $titulo = $titulos[$tipoDocumento] ?? 'Documento Escolar';

        return '
        <div class="topo">
          <h1>FASCAL</h1>
          <p>' . $titulo . '</p>
          <p>Emitido em: ' . date('d/m/Y H:i') . '</p>
        </div>';
    }

    private function templateDeclaracao(array $dadosAluno, array $resumo, string $observacoes): string
    {
        $textoObs = $observacoes !== '' ? '<p><strong>Observacoes:</strong> ' . escapar($observacoes) . '</p>' : '';

        return '
        <p>Declaramos para os devidos efeitos que o(a) aluno(a) <strong>' . escapar($dadosAluno['nome_aluno']) . '</strong>,
        BI n. ' . escapar($dadosAluno['bi'] ??  'Nao informado') . ', encontra-se regularmente matriculado(a) na turma
        <strong>' . escapar($dadosAluno['turma']) . '</strong> do ano lectivo ' . escapar($dadosAluno['ano_letivo'] ??  'em curso') . '.</p>

        <div class="destaque">
          Media geral actual: <strong>' . number_format((float) $resumo['media_geral'], 1, ',', '.') . '</strong> |
          Faltas registadas: <strong>' . (int) $resumo['faltas'] . '</strong>
        </div>

        <p>Por ser verdade, passa-se a presente declaracao que vai assinada e carimbada para os fins convenientes.</p>
        ' . $textoObs . '
        <div class="assinatura">
          <p>_______________________________</p>
          <p>Secretaria / Direcao</p>
        </div>';
    }

    private function templateCertificado(array $dadosAluno, array $resumo, string $observacoes): string
    {
        $desempenho = (float) $resumo['media_geral'] >= 14 ? 'Excelente' : ((float) $resumo['media_geral'] >= 10 ? 'Satisfatorio' : 'Em desenvolvimento');
        $textoObs = $observacoes !== '' ? '<p><strong>Observacoes:</strong> ' . escapar($observacoes) . '</p>' : '';

        return '
        <p>Certificamos que o(a) aluno(a) <strong>' . escapar($dadosAluno['nome_aluno']) . '</strong>, nascido(a) em
        ' . formatar_data($dadosAluno['data_nascimento']) . ', concluiu com aproveitamento o periodo lectivo avaliado
        na FASCAL.</p>

        <div class="destaque">
          Classificacao global: <strong>' . number_format((float) $resumo['media_geral'], 1, ',', '.') . '</strong>
          (' . $desempenho . ') - Turma: ' . escapar($dadosAluno['turma']) . '
        </div>

        <p>Este certificado e emitido para os efeitos legais e administrativos aplicaveis.</p>
        ' . $textoObs . '
        <div class="assinatura">
          <p>_______________________________</p>
          <p>Direcao Geral</p>
        </div>';
    }

    private function templateBoletim(array $dadosAluno, array $resumo, array $notas, string $observacoes): string
    {
        $linhas = '';
        foreach ($notas as $nota) {
            $linhas .= '<tr>
                <td>' . escapar($nota['disciplina']) . '</td>
                <td>' . escapar((string) ($nota['trimestre_1'] ?? '-')) . '</td>
                <td>' . escapar((string) ($nota['trimestre_2'] ?? '-')) . '</td>
                <td>' . escapar((string) ($nota['trimestre_3'] ?? '-')) . '</td>
                <td>' . escapar((string) ($nota['media'] ?? '-')) . '</td>
            </tr>';
        }

        if ($linhas === '') {
            $linhas = '<tr><td colspan="5">Sem notas lancadas para este aluno.</td></tr>';
        }

        $textoObs = $observacoes !== '' ? '<p><strong>Observacoes:</strong> ' . escapar($observacoes) . '</p>' : '';

        return '
        <p><strong>Aluno:</strong> ' . escapar($dadosAluno['nome_aluno']) . '<br>
        <strong>Encarregado:</strong> ' . escapar($dadosAluno['nome_encarregado']) . '<br>
        <strong>Turma:</strong> ' . escapar($dadosAluno['turma']) . '</p>

        <table>
          <thead>
            <tr>
              <th>Disciplina</th>
              <th>1o Trimestre</th>
              <th>2o Trimestre</th>
              <th>3o Trimestre</th>
              <th>Media</th>
            </tr>
          </thead>
          <tbody>' . $linhas . '</tbody>
        </table>

        <div class="destaque">
          Media geral: <strong>' . number_format((float) $resumo['media_geral'], 1, ',', '.') . '</strong>
          | Faltas: <strong>' . (int) $resumo['faltas'] . '</strong>
        </div>
        ' . $textoObs . '
        <div class="assinatura">
          <p>_______________________________</p>
          <p>Direcao Pedagogica</p>
        </div>';
    }

    private function templateRodape(): string
    {
        return '
        <div class="rodape">
          FASCAL | Talatona, na Rua Direita da Camama | secretaria@fascal.ao
        </div>';
    }
}

