<?php

use Dompdf\Dompdf;

class MatriculaController
{
    private $preMatriculaModel;

    public function __construct()
    {
        if (is_file(CAMINHO_RAIZ . '/vendor/autoload.php')) {
            require_once CAMINHO_RAIZ . '/vendor/autoload.php';
        }

        $this->preMatriculaModel = new PreMatriculaModel();
    }

    public function index(): void
    {
        require CAMINHO_RAIZ . '/views/matricula.php';
    }

    public function processar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('matricula');
        }

        $dados = [
            'nome_encarregado' => sanitizar_texto_simples((string) ($_POST['nome_encarregado'] ?? ''), 120),
            'email_encarregado' => filter_var($_POST['email_encarregado'] ?? '', FILTER_SANITIZE_EMAIL) ?: '',
            'telefone_encarregado' => sanitizar_texto_simples((string) ($_POST['telefone_encarregado'] ?? ''), 20),
            'nome_aluno' => sanitizar_texto_simples((string) ($_POST['nome_aluno'] ?? ''), 120),
            'data_nascimento_aluno' => trim((string) ($_POST['data_nascimento_aluno'] ?? '')),
            'ano_pretendido' => sanitizar_texto_simples((string) ($_POST['ano_pretendido'] ?? ''), 30),
            'curso_pretendido' => sanitizar_texto_simples((string) ($_POST['curso_pretendido'] ?? ''), 100),
            'observacoes' => sanitizar_texto_simples((string) ($_POST['observacoes'] ?? ''), 3000)
        ];

        if (
            $dados['nome_encarregado'] === '' ||
            !nome_humano_valido($dados['nome_encarregado']) ||
            !filter_var($dados['email_encarregado'], FILTER_VALIDATE_EMAIL) ||
            $dados['telefone_encarregado'] === '' ||
            $dados['nome_aluno'] === '' ||
            !nome_humano_valido($dados['nome_aluno']) ||
            $dados['data_nascimento_aluno'] === '' ||
            $dados['ano_pretendido'] === '' ||
            $dados['curso_pretendido'] === ''
        ) {
            definir_flash('erro_matricula', 'Preencha corretamente os nomes e os campos obrigatorios da pre-matricula.');
            redirecionar('matricula');
        }

        if ($this->preMatriculaModel->existePorEmailEAluno($dados['email_encarregado'], $dados['nome_aluno'])) {
            definir_flash('erro_matricula', 'Ja existe um pedido para este encarregado e aluno.');
            redirecionar('matricula');
        }

        $codigo = $this->preMatriculaModel->gerarCodigoAtendimento();

        if (!$this->preMatriculaModel->inserir($dados, $codigo)) {
            definir_flash('erro_matricula', 'Ocorreu um erro ao registar a pre-matricula. Tente novamente.');
            redirecionar('matricula');
        }

        $_SESSION['codigo_pre_matricula'] = $codigo;
        definir_flash('sucesso_matricula', 'Pre-matricula registada com sucesso.');

        redirecionar('comprovante?codigo=' . urlencode($codigo));
    }

    public function comprovante(): void
    {
        $codigo = trim((string) ($_GET['codigo'] ?? ($_SESSION['codigo_pre_matricula'] ?? '')));

        if ($codigo === '') {
            redirecionar('matricula');
        }

        $preMatricula = $this->preMatriculaModel->buscarPorCodigo($codigo);

        if (!$preMatricula) {
            definir_flash('erro_matricula', 'Comprovante nao encontrado.');
            redirecionar('matricula');
        }

        require CAMINHO_RAIZ . '/views/comprovante.php';
    }

    public function gerar_pdf(): void
    {
        $codigo = trim((string) ($_GET['codigo'] ?? ''));
        if ($codigo === '') {
            redirecionar('matricula');
        }

        $preMatricula = $this->preMatriculaModel->buscarPorCodigo($codigo);

        if (!$preMatricula) {
            definir_flash('erro_matricula', 'Nao foi possivel gerar o PDF.');
            redirecionar('matricula');
        }

        $html = $this->gerarHtmlComprovante($preMatricula);

        if (!class_exists(Dompdf::class)) {
            definir_flash('erro_matricula', 'Biblioteca de PDF indisponivel. Execute composer install e tente novamente.');
            redirecionar('matricula');
        }

        error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        $dompdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans'
        ]);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('comprovante-' . $codigo . '.pdf', ['Attachment' => true]);
        exit;
    }

    public function validar_email(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $email = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_EMAIL) ?: '';
        $nomeAluno = sanitizar_texto_simples((string) ($_GET['nome_aluno'] ?? ''), 120);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $nomeAluno === '' || !nome_humano_valido($nomeAluno)) {
            echo json_encode([
                'valido' => false,
                'mensagem' => 'Informe um email valido e o nome do aluno com pelo menos 3 letras.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $existe = $this->preMatriculaModel->existePorEmailEAluno($email, $nomeAluno);

        echo json_encode([
            'valido' => !$existe,
            'mensagem' => $existe
                ? 'Ja existe uma pre-matricula associada a este aluno e encarregado.'
                : 'Email disponivel para este aluno.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function gerarHtmlComprovante(array $dados): string
    {
        $codigo = escapar($dados['codigo']);
        $encarregado = escapar($dados['nome_encarregado']);
        $aluno = escapar($dados['nome_aluno']);
        $classe = escapar((string) ($dados['ano_pretendido'] ?? ''));
        $curso = escapar((string) ($dados['curso_pretendido'] ?? ''));
        $logoPath = CAMINHO_RAIZ . '/assets/imagens/icones/logo.png';
        $logoData = '';
        if (is_file($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath));
        }

        $conteudo = "
        <div style='text-align:center;margin-bottom:10px;'>
            " . ($logoData !== '' ? "<img src='{$logoData}' alt='Logo FASCCAR' style='width:72px;height:72px;object-fit:contain;display:block;margin:0 auto 8px;'>" : '') . "
            <h1 style='text-align:center;color:#0056a3;margin:0 0 6px 0;'>COLEGIO MUNDO NOVO - FASCCAR</h1>
        </div>
        <hr>
        <p>Ola, <strong>{$encarregado}</strong>,</p>
        <p>Agradecemos o seu interesse em matricular <strong>{$aluno}</strong> na FASCCAR.
        O processo de pre-matricula foi iniciado com sucesso.</p>
        <p><strong>Classe pretendida:</strong> {$classe}<br><strong>Curso pretendido:</strong> {$curso}</p>
        <p style='background:#ffe082;padding:14px;border-left:4px solid #ffc107;'>
        O SEU CODIGO DE ATENDIMENTO E: <strong>{$codigo}</strong></p>

        <h3>Instrucoes para finalizacao da matricula</h3>
        <ol>
            <li>Compareca na secretaria no dia 28 de Marco de 2026, entre as 9h00 e as 11h00.</li>
            <li>Endereco: Talatona, na Rua Direita da Camama.</li>
            <li>Documentos: BI do aluno, certidao de nascimento, boletim de vacinas, atestado medico, certificado de habilitacoes, 2 fotos 3x4 e BI do encarregado.</li>
            <li>Contacto da secretaria: 999 999 999 | secretaria@fascal.ao</li>
        </ol>

        <p><strong>O que acontece depois?</strong><br>
        Apos a conferencia documental, a matricula sera concluida e as credenciais do portal serao entregues.</p>

        <p>Atenciosamente,<br>Equipa de Admissoes<br>FASCCAR</p>
        ";

        return "
        <!doctype html>
        <html lang='pt'>
        <head>
            <meta charset='UTF-8'>
            <title>Comprovante de Pre-matricula</title>
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12pt; margin: 36px; color: #212121; }
                h1, h3 { margin: 0 0 12px; }
                p, li { line-height: 1.6; }
                hr { border: none; border-top: 1px solid #e0e0e0; margin: 16px 0 20px; }
            </style>
        </head>
        <body>{$conteudo}</body>
        </html>
        ";
    }
}
