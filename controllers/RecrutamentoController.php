<?php

class RecrutamentoController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function index(): void
    {
        $mensagemSucesso = obter_flash('sucesso_recrutamento');
        $mensagemErro = obter_flash('erro_recrutamento');
        require CAMINHO_RAIZ . '/views/recrutamento.php';
    }

    public function candidatar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('recrutamento');
        }

        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL) ?: '';
        $telefone = trim((string) ($_POST['telefone'] ?? ''));
        $area = trim((string) ($_POST['area_candidatura'] ?? ''));
        $experiencia = trim((string) ($_POST['experiencia'] ?? ''));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $area === '') {
            definir_flash('erro_recrutamento', 'Preencha os campos obrigatorios da candidatura.');
            redirecionar('recrutamento');
        }

        $cvPath = $this->processarUploadPdf($_FILES['cv_pdf'] ?? [], 'cv');
        if ($cvPath === null) {
            definir_flash('erro_recrutamento', 'Envie o CV em formato PDF (maximo 8 MB).');
            redirecionar('recrutamento');
        }

        $certificadosPath = null;
        if (isset($_FILES['certificados_pdf']) && ($_FILES['certificados_pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $certificadosPath = $this->processarUploadPdf($_FILES['certificados_pdf'], 'cert');
            if ($certificadosPath === null) {
                definir_flash('erro_recrutamento', 'O ficheiro de certificados deve estar em PDF (maximo 8 MB).');
                redirecionar('recrutamento');
            }
        }

        try {
            $descricao = trim($experiencia . "\n\n" . $mensagem);
            $stmt = $this->db->prepare(
                'INSERT INTO candidaturas_professor
                 (nome, email, telefone, disciplina, cv_path, certificados_path, mensagem, status, criado_em)
                 VALUES
                 (:nome, :email, :telefone, :disciplina, :cv_path, :certificados_path, :mensagem, "nova", NOW())'
            );
            $stmt->execute([
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone !== '' ? $telefone : null,
                'disciplina' => $area,
                'cv_path' => $cvPath,
                'certificados_path' => $certificadosPath,
                'mensagem' => $descricao !== '' ? $descricao : null
            ]);
            $candidaturaId = (int) $this->db->lastInsertId();
        } catch (Throwable $erro) {
            definir_flash('erro_recrutamento', 'Nao foi possivel enviar a candidatura neste momento.');
            redirecionar('recrutamento');
        }

        $protocolo = 'CAD-' . date('Y') . '-' . str_pad((string) max(1, $candidaturaId), 6, '0', STR_PAD_LEFT);
        definir_flash(
            'sucesso_recrutamento',
            'Agradecemos o seu interesse. Entraremos em contacto assim que possivel. ' .
            'Protocolo: ' . $protocolo . '. Guarde este codigo para acompanhamento. '
            . 'Caso seja convocado, apresente este protocolo na instituicao.'
        );
        redirecionar('recrutamento');
    }

    private function processarUploadPdf(array $ficheiro, string $prefixo): ?string
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

        if ((int) ($ficheiro['size'] ?? 0) > 8 * 1024 * 1024) {
            return null;
        }

        $mime = mime_content_type($tmp) ?: '';
        if ($mime !== 'application/pdf') {
            return null;
        }

        $pasta = CAMINHO_RAIZ . '/uploads/candidaturas';
        if (!is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }

        $nomeFicheiro = $prefixo . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.pdf';
        $destino = $pasta . '/' . $nomeFicheiro;
        if (!move_uploaded_file($tmp, $destino)) {
            return null;
        }

        return 'uploads/candidaturas/' . $nomeFicheiro;
    }
}
