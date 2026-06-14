<?php

use Dompdf\Dompdf;

class PagamentoController
{
    private DashboardController $dashboard;
    private PainelOperacionalModel $painelOperacionalModel;
    private PerfilModel $perfilModel;
    private PDO $db;

    public function __construct()
    {
        if (!usuario_logado()) {
            definir_flash('erro_login', 'Sessão expirada. Faça login novamente.');
            redirecionar('login');
        }

        $this->dashboard = new DashboardController();
        $this->painelOperacionalModel = new PainelOperacionalModel();
        $this->perfilModel = new PerfilModel();
        $this->db = Database::getInstancia();

        if (is_file(CAMINHO_RAIZ . '/vendor/autoload.php')) {
            require_once CAMINHO_RAIZ . '/vendor/autoload.php';
        }
    }

    public function enviar_comprovativo(): void
    {
        $this->dashboard->encarregado_enviar_comprovativo_pagamento();
    }

    public function reclamar(): void
    {
        $this->dashboard->encarregado_reclamar_pagamento();
    }

    public function analisar(): void
    {
        $this->dashboard->secretaria_analisar_comprovativo();
    }

    public function simulador(): void
    {
        $this->exigirPerfis(['encarregado']);

        $token = trim((string) ($_GET['t'] ?? ''));
        $dadosPagamento = $this->descodificarTokenPagamento($token);
        if (empty($dadosPagamento)) {
            definir_flash('erro_painel', 'Dados inválidos para o simulador de pagamento.');
            redirecionar('painel/encarregado?pagina=financeiro');
        }

        if (!$this->encarregadoTemAcessoAosAlunos((int) $dadosPagamento['encarregado_id'], (array) $dadosPagamento['alunos'])) {
            definir_flash('erro_painel', 'Os alunos selecionados não estão associados ao encarregado.');
            redirecionar('painel/encarregado?pagina=financeiro');
        }

        $valorTotal = (float) ($dadosPagamento['valor_total'] ?? 0);
        if ($valorTotal <= 0) {
            definir_flash('erro_painel', 'Valor inválido para pagamento.');
            redirecionar('painel/encarregado?pagina=financeiro');
        }

        $metodoSelecionado = trim((string) ($dadosPagamento['metodo_pagamento'] ?? 'referencia'));
        if (!in_array($metodoSelecionado, ['referencia', 'autorizacao'], true)) {
            $metodoSelecionado = 'referencia';
        }

        $nomesAlunos = $this->obterNomesAlunos((array) $dadosPagamento['alunos']);
        $atividadeTema = '';
        if ((string) ($dadosPagamento['tipo_pagamento'] ?? 'mensalidade') === 'atividade') {
            $atividadeTema = $this->obterTemaAtividade((int) ($dadosPagamento['atividade_id'] ?? 0));
        }

        require CAMINHO_RAIZ . '/views/pagamento/simulador.php';
    }

    public function concluir_referencia(): void
    {
        $this->concluirPagamento('referencia');
    }

    public function concluir_autorizacao(): void
    {
        $this->concluirPagamento('autorizacao');
    }

    private function concluirPagamento(string $metodo): void
    {
        $this->exigirPerfis(['encarregado']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirecionar('painel/encarregado?pagina=financeiro');
        }

        $token = trim((string) ($_POST['token_pagamento'] ?? ''));
        $dadosPagamento = $this->descodificarTokenPagamento($token);
        if (empty($dadosPagamento)) {
            definir_flash('erro_painel', 'Não foi possível validar os dados do pagamento.');
            redirecionar('painel/encarregado?pagina=financeiro');
        }

        $encarregadoId = (int) ($dadosPagamento['encarregado_id'] ?? 0);
        $alunos = (array) ($dadosPagamento['alunos'] ?? []);
        if (!$this->encarregadoTemAcessoAosAlunos($encarregadoId, $alunos)) {
            definir_flash('erro_painel', 'Validação de segurança falhou para os alunos selecionados.');
            redirecionar('painel/encarregado?pagina=financeiro');
        }

        if ($metodo === 'referencia') {
            $entidade = trim((string) ($_POST['entidade'] ?? ''));
            $referencia = trim((string) ($_POST['referencia_pagamento'] ?? ''));
            if ($entidade === '' || $referencia === '') {
                definir_flash('erro_painel', 'Preencha entidade e referência para concluir o pagamento.');
                redirecionar('pagamento/simulador?t=' . urlencode($token));
            }
        } else {
            $pin = trim((string) ($_POST['pin_autorizacao'] ?? ''));
            if ($pin === '' || strlen($pin) < 4) {
                definir_flash('erro_painel', 'PIN inválido para autorização do pagamento.');
                redirecionar('pagamento/simulador?t=' . urlencode($token));
            }
        }

        $pastaComprovativos = CAMINHO_RAIZ . '/storage/comprovativos';
        if (!is_dir($pastaComprovativos)) {
            @mkdir($pastaComprovativos, 0775, true);
        }

        $nomeRecibo = 'recibo-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.pdf';
        $reciboRelativo = 'storage/comprovativos/' . $nomeRecibo;

        $resultado = $this->painelOperacionalModel->concluirPagamentoSimulado([
            'encarregado_id' => $encarregadoId,
            'alunos' => $alunos,
            'valor_total' => (float) ($dadosPagamento['valor_total'] ?? 0),
            'mes_referencia' => (string) ($dadosPagamento['mes_referencia'] ?? date('Y-m')),
            'tipo_pagamento' => (string) ($dadosPagamento['tipo_pagamento'] ?? 'mensalidade'),
            'metodo_pagamento' => $metodo,
            'atividade_id' => (int) ($dadosPagamento['atividade_id'] ?? 0),
            'recibo_pdf' => $reciboRelativo,
            'observacao' => $metodo === 'referencia'
                ? 'Pagamento por referência no simulador Multicaixa.'
                : 'Pagamento por autorização no simulador Multicaixa.'
        ]);

        if (!($resultado['sucesso'] ?? false)) {
            definir_flash('erro_painel', (string) ($resultado['mensagem'] ?? 'Falha ao concluir pagamento simulado.'));
            redirecionar('painel/encarregado?pagina=financeiro');
        }

        $this->gerarReciboPdf($reciboRelativo, $dadosPagamento, $resultado, $metodo);
        $this->painelOperacionalModel->atualizarReciboComprovativosPorCodigos((array) ($resultado['codigos'] ?? []), $reciboRelativo);

        $this->perfilModel->registarAtividade(
            (int) ($_SESSION['usuario_id'] ?? 0),
            'Concluiu pagamento simulado',
            strtoupper($metodo) . ' | ' . implode(', ', (array) ($resultado['codigos'] ?? []))
        );

        $mensagem = 'Pagamento confirmado com sucesso. Referências: ' . implode(', ', (array) ($resultado['codigos'] ?? []));
        definir_flash('sucesso_painel', $mensagem);
        redirecionar('painel/encarregado?pagina=comprovativos');
    }

    private function gerarReciboPdf(string $caminhoRelativo, array $dadosPagamento, array $resultado, string $metodo): void
    {
        if (!class_exists(Dompdf::class)) {
            return;
        }

        $caminhoAbsoluto = CAMINHO_RAIZ . '/' . ltrim($caminhoRelativo, '/');
        $pasta = dirname($caminhoAbsoluto);
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0775, true);
        }

        $encarregadoNome = $this->obterNomeEncarregado((int) ($dadosPagamento['encarregado_id'] ?? 0));
        $nomesAlunos = $this->obterNomesAlunos((array) ($dadosPagamento['alunos'] ?? []));
        $tipoPagamento = (string) ($dadosPagamento['tipo_pagamento'] ?? 'mensalidade');
        $mes = (string) ($dadosPagamento['mes_referencia'] ?? date('Y-m'));
        $valor = number_format((float) ($dadosPagamento['valor_total'] ?? 0), 2, ',', '.');
        $linhasCodigos = implode(', ', (array) ($resultado['codigos'] ?? []));

        $detalheTipo = $tipoPagamento === 'atividade'
            ? 'Atividade extracurricular'
            : 'Mensalidade';

        $html = '
        <!doctype html>
        <html lang="pt">
          <head>
            <meta charset="UTF-8">
            <style>
              body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1b1b1b; margin: 30px; }
              .topo { text-align: center; border-bottom: 2px solid #0056a3; padding-bottom: 12px; margin-bottom: 18px; }
              .topo h1 { margin: 0; font-size: 18px; color: #0056a3; }
              .topo h2 { margin: 6px 0 0; font-size: 15px; color: #d98200; }
              .bloco { margin-bottom: 12px; }
              .etq { color: #555; }
              .valor { font-weight: bold; color: #0056a3; }
              .rodape { margin-top: 28px; border-top: 1px solid #d0d7e2; padding-top: 8px; color: #555; }
            </style>
          </head>
          <body>
            <div class="topo">
              <h1>República de Angola - Instituto Politécnico Privado Mundo Novo II</h1>
              <h2>Comprovativo de Pagamento</h2>
            </div>
            <div class="bloco"><span class="etq">Data e hora:</span> ' . date('d/m/Y H:i:s') . '</div>
            <div class="bloco"><span class="etq">Encarregado:</span> ' . escapar($encarregadoNome !== '' ? $encarregadoNome : 'Não identificado') . '</div>
            <div class="bloco"><span class="etq">Educando(s):</span> ' . escapar(implode(', ', $nomesAlunos)) . '</div>
            <div class="bloco"><span class="etq">Tipo:</span> ' . escapar($detalheTipo) . '</div>
            <div class="bloco"><span class="etq">Mês/Referência:</span> ' . escapar($mes) . '</div>
            <div class="bloco"><span class="etq">Método:</span> ' . escapar(strtoupper($metodo)) . '</div>
            <div class="bloco"><span class="etq">Valor total:</span> <span class="valor">' . $valor . ' Kz</span></div>
            <div class="bloco"><span class="etq">Códigos gerados:</span> ' . escapar($linhasCodigos) . '</div>
            <div class="rodape">
              Documento emitido automaticamente pelo sistema FASCAL.
            </div>
          </body>
        </html>';

        try {
            $dompdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            file_put_contents($caminhoAbsoluto, $dompdf->output());
        } catch (Throwable $erro) {
        }
    }

    private function descodificarTokenPagamento(string $token): array
    {
        if ($token === '') {
            return [];
        }

        $payload = base64_decode(strtr($token, '-_', '+/'), true);
        if ($payload === false) {
            return [];
        }

        $dados = json_decode($payload, true);
        if (!is_array($dados)) {
            return [];
        }

        $dados['alunos'] = array_values(array_filter(array_map('intval', (array) ($dados['alunos'] ?? [])), static fn(int $id): bool => $id > 0));
        $dados['encarregado_id'] = (int) ($dados['encarregado_id'] ?? 0);
        $dados['atividade_id'] = (int) ($dados['atividade_id'] ?? 0);
        $dados['valor_total'] = (float) ($dados['valor_total'] ?? 0);
        $dados['tipo_pagamento'] = trim((string) ($dados['tipo_pagamento'] ?? 'mensalidade'));
        $dados['metodo_pagamento'] = trim((string) ($dados['metodo_pagamento'] ?? 'referencia'));
        $dados['mes_referencia'] = trim((string) ($dados['mes_referencia'] ?? date('Y-m')));

        return $dados;
    }

    private function encarregadoTemAcessoAosAlunos(int $encarregadoId, array $alunos): bool
    {
        $alunos = array_values(array_filter(array_map('intval', $alunos), static fn(int $id): bool => $id > 0));
        if ($encarregadoId <= 0 || empty($alunos)) {
            return false;
        }

        $placeholders = [];
        $params = ['encarregado_id' => $encarregadoId];
        foreach ($alunos as $indice => $alunoId) {
            $chave = 'a' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $alunoId;
        }

        try {
            $sql = 'SELECT COUNT(*) AS total
                    FROM encarregado_aluno
                    WHERE encarregado_id = :encarregado_id
                      AND aluno_id IN (' . implode(', ', $placeholders) . ')';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();
            return $total === count($alunos);
        } catch (Throwable $erro) {
            return false;
        }
    }

    private function obterNomesAlunos(array $alunos): array
    {
        $alunos = array_values(array_filter(array_map('intval', $alunos), static fn(int $id): bool => $id > 0));
        if (empty($alunos)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($alunos as $indice => $alunoId) {
            $chave = 'a' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $alunoId;
        }

        try {
            $sql = 'SELECT u.nome
                    FROM alunos a
                    INNER JOIN utilizadores u ON u.id = a.utilizador_id
                    WHERE a.id IN (' . implode(', ', $placeholders) . ')
                    ORDER BY u.nome';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $linhas = $stmt->fetchAll() ?: [];
            return array_values(array_map(static fn(array $linha): string => (string) ($linha['nome'] ?? ''), $linhas));
        } catch (Throwable $erro) {
            return [];
        }
    }

    private function obterNomeEncarregado(int $encarregadoId): string
    {
        if ($encarregadoId <= 0) {
            return '';
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT u.nome
                 FROM encarregados e
                 INNER JOIN utilizadores u ON u.id = e.utilizador_id
                 WHERE e.id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $encarregadoId]);
            return (string) ($stmt->fetchColumn() ?: '');
        } catch (Throwable $erro) {
            return '';
        }
    }

    private function obterTemaAtividade(int $atividadeId): string
    {
        if ($atividadeId <= 0) {
            return '';
        }

        try {
            $stmt = $this->db->prepare('SELECT tema FROM atividades_extracurriculares WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $atividadeId]);
            return (string) ($stmt->fetchColumn() ?: '');
        } catch (Throwable $erro) {
            return '';
        }
    }

    private function exigirPerfis(array $perfis): void
    {
        if (in_array(perfil_atual(), $perfis, true)) {
            return;
        }

        definir_flash('erro_login', 'Não tem permissão para aceder a esta área.');
        redirecionar('painel');
    }
}
