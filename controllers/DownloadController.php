<?php

use Dompdf\Dompdf;

class DownloadController
{
    private $db;
    private $documentoModel;

    public function __construct()
    {
        if (!usuario_logado()) {
            definir_flash('erro_login', 'Sessao expirada. Inicie sessao novamente.');
            redirecionar('login');
        }

        if (is_file(CAMINHO_RAIZ . '/vendor/autoload.php')) {
            require_once CAMINHO_RAIZ . '/vendor/autoload.php';
        }

        $this->db = Database::getInstancia();
        $this->documentoModel = new DocumentoModel();
    }

    public function documento(): void
    {
        $perfil = perfil_atual();
        if (!in_array($perfil, ['aluno', 'encarregado'], true)) {
            http_response_code(403);
            echo 'Sem permissao para baixar documentos nesta area.';
            exit;
        }

        $tipo = trim((string) ($_GET['tipo'] ?? 'boletim'));
        $tiposValidos = ['boletim', 'declaracao', 'certificado'];
        if (!in_array($tipo, $tiposValidos, true)) {
            http_response_code(400);
            echo 'Tipo de documento invalido.';
            exit;
        }

        $alunoId = $this->resolverAlunoIdAcesso($perfil);
        if ($alunoId <= 0) {
            http_response_code(403);
            echo 'Aluno nao autorizado para este download.';
            exit;
        }

        if (!$this->documentoAutorizado($alunoId, $tipo)) {
            http_response_code(403);
            echo 'Documento ainda nao autorizado pela direcao pedagogica.';
            exit;
        }

        $dadosAluno = $this->documentoModel->obterDadosAlunoDocumento($alunoId);
        if (!$dadosAluno) {
            http_response_code(404);
            echo 'Dados do aluno nao encontrados.';
            exit;
        }

        $resumo = $this->documentoModel->obterResumoAcademico($alunoId);
        $notas = $this->documentoModel->obterNotasBoletim($alunoId);

        $html = $this->gerarDocumentoHtml($tipo, $dadosAluno, $resumo, $notas);
        $nomeFicheiro = $tipo . '-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) ($dadosAluno['nome_aluno'] ?? 'aluno'))) . '.pdf';

        if (!class_exists(Dompdf::class)) {
            http_response_code(500);
            echo 'Biblioteca de PDF indisponivel.';
            exit;
        }

        error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        $dompdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($nomeFicheiro, ['Attachment' => true]);
        exit;
    }

    private function resolverAlunoIdAcesso(string $perfil): int
    {
        $utilizadorId = (int) $_SESSION['usuario_id'];

        if ($perfil === 'aluno') {
            $stmt = $this->db->prepare('SELECT id FROM alunos WHERE utilizador_id = :utilizador_id LIMIT 1');
            $stmt->execute(['utilizador_id' => $utilizadorId]);
            return (int) $stmt->fetchColumn();
        }

        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        if ($alunoId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(*)
                FROM encarregados e
                INNER JOIN encarregado_aluno ea ON ea.encarregado_id = e.id
                WHERE e.utilizador_id = :utilizador_id
                  AND ea.aluno_id = :aluno_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'utilizador_id' => $utilizadorId,
            'aluno_id' => $alunoId
        ]);

        return (int) $stmt->fetchColumn() > 0 ? $alunoId : 0;
    }

    private function documentoAutorizado(int $alunoId, string $tipo): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM solicitacoes_documentos
                WHERE aluno_id = :aluno_id
                  AND tipo_documento = :tipo
                  AND estado IN ("autorizado", "disponibilizado")';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'aluno_id' => $alunoId,
            'tipo' => $tipo
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function gerarDocumentoHtml(string $tipo, array $dadosAluno, array $resumo, array $notas): string
    {
        $cabecalho = '
        <div style="border-bottom:2px solid #0056a3;padding-bottom:8px;margin-bottom:14px;">
          <h2 style="margin:0;color:#0056a3;">Instituto Politecnico Privado Mundo Novo II</h2>
          <p style="margin:4px 0 0;">Documento digital autorizado</p>
        </div>';

        if ($tipo === 'boletim') {
            $linhas = '';
            foreach ($notas as $nota) {
                $linhas .= '<tr>
                    <td>' . escapar((string) ($nota['disciplina'] ?? '-')) . '</td>
                    <td>' . escapar((string) ($nota['trimestre_1'] ?? '-')) . '</td>
                    <td>' . escapar((string) ($nota['trimestre_2'] ?? '-')) . '</td>
                    <td>' . escapar((string) ($nota['trimestre_3'] ?? '-')) . '</td>
                    <td>' . escapar((string) ($nota['media'] ?? '-')) . '</td>
                </tr>';
            }
            if ($linhas === '') {
                $linhas = '<tr><td colspan="5">Sem notas disponiveis.</td></tr>';
            }

            $corpo = '
            <h3>Boletim</h3>
            <p><strong>Aluno:</strong> ' . escapar((string) ($dadosAluno['nome_aluno'] ?? '-')) . '</p>
            <p><strong>Turma:</strong> ' . escapar((string) ($dadosAluno['turma'] ?? '-')) . '</p>
            <table style="width:100%;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="border:1px solid #ddd;padding:6px;">Disciplina</th>
                  <th style="border:1px solid #ddd;padding:6px;">1o</th>
                  <th style="border:1px solid #ddd;padding:6px;">2o</th>
                  <th style="border:1px solid #ddd;padding:6px;">3o</th>
                  <th style="border:1px solid #ddd;padding:6px;">Media</th>
                </tr>
              </thead>
              <tbody>' . $linhas . '</tbody>
            </table>
            <p style="margin-top:10px;"><strong>Media geral:</strong> ' . number_format((float) ($resumo['media_geral'] ?? 0), 1, ',', '.') . '</p>';
        } elseif ($tipo === 'certificado') {
            $corpo = '
            <h3>Certificado</h3>
            <p>Certificamos que <strong>' . escapar((string) ($dadosAluno['nome_aluno'] ?? '-')) . '</strong> frequentou regularmente a instituicao.</p>
            <p><strong>Turma:</strong> ' . escapar((string) ($dadosAluno['turma'] ?? '-')) . '</p>
            <p><strong>Media geral:</strong> ' . number_format((float) ($resumo['media_geral'] ?? 0), 1, ',', '.') . '</p>';
        } else {
            $corpo = '
            <h3>Declaracao</h3>
            <p>Declaramos que o(a) aluno(a) <strong>' . escapar((string) ($dadosAluno['nome_aluno'] ?? '-')) . '</strong> encontra-se matriculado(a) nesta instituicao.</p>
            <p><strong>Turma:</strong> ' . escapar((string) ($dadosAluno['turma'] ?? '-')) . '</p>';
        }

        return '
        <!doctype html>
        <html lang="pt">
        <head>
          <meta charset="UTF-8">
          <style>
            body { font-family: DejaVu Sans, Arial, sans-serif; margin: 30px; color: #212121; }
            table td { border: 1px solid #ddd; padding: 6px; }
          </style>
        </head>
        <body>' . $cabecalho . $corpo . '</body>
        </html>';
    }
}
