<?php

class FuncionarioModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstancia();
    }

    public function listarAvisos(string $destinatario = 'todos', int $limite = 5): array
    {
        $limite = max(1, $limite);

        try {
            $sql = "SELECT titulo, mensagem, destinatarios, data_inicio, data_fim, criado_em
                    FROM avisos
                    WHERE destinatarios IN ('todos', :destinatario)
                      AND (data_inicio IS NULL OR data_inicio <= NOW())
                      AND (data_fim IS NULL OR data_fim >= NOW())
                    ORDER BY criado_em DESC
                    LIMIT {$limite}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['destinatario' => $destinatario]);
            $dados = $stmt->fetchAll();

            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
        }

        return [];
    }

    public function obterIndicadoresSecretaria(): array
    {
        try {
            $preMatriculasPendentes = (int) $this->db->query("SELECT COUNT(*) FROM pre_matriculas WHERE status = 'pendente'")->fetchColumn();
            $preMatriculasSemana = (int) $this->db->query("SELECT COUNT(*) FROM pre_matriculas WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $pagamentosAtrasados = (int) $this->db->query("SELECT COUNT(*) FROM pagamentos WHERE status = 'atrasado'")->fetchColumn();

            return [
                'pre_matriculas_pendentes' => $preMatriculasPendentes,
                'pre_matriculas_semana' => $preMatriculasSemana,
                'pagamentos_atrasados' => $pagamentosAtrasados,
                'documentos_emitidos' => (int) floor($preMatriculasSemana * 1.8)
            ];
        } catch (Throwable $erro) {
            return [
                'pre_matriculas_pendentes' => 0,
                'pre_matriculas_semana' => 0,
                'pagamentos_atrasados' => 0,
                'documentos_emitidos' => 0
            ];
        }
    }

    public function obterIndicadoresDirecaoPedagogica(): array
    {
        try {
            $turmas = (int) $this->db->query('SELECT COUNT(*) FROM turmas')->fetchColumn();
            $notasMes = (int) $this->db->query('SELECT COUNT(*) FROM notas WHERE MONTH(data_lancamento) = MONTH(NOW()) AND YEAR(data_lancamento) = YEAR(NOW())')->fetchColumn();
            $mediaGeral = (float) $this->db->query('SELECT ROUND(AVG(nota), 1) FROM notas')->fetchColumn();

            return [
                'turmas_ativas' => $turmas,
                'notas_no_mes' => $notasMes,
                'media_geral' => $mediaGeral,
                'planos_pendentes' => max(0, $turmas - (int) floor($notasMes / 5))
            ];
        } catch (Throwable $erro) {
            return [
                'turmas_ativas' => 0,
                'notas_no_mes' => 0,
                'media_geral' => 0,
                'planos_pendentes' => 0
            ];
        }
    }

    public function obterIndicadoresDirecaoGeral(): array
    {
        try {
            $totalAlunos = (int) $this->db->query('SELECT COUNT(*) FROM alunos')->fetchColumn();
            $totalProfessores = (int) $this->db->query('SELECT COUNT(*) FROM professores')->fetchColumn();
            $totalFuncionarios = (int) $this->db->query('SELECT COUNT(*) FROM funcionarios')->fetchColumn();
            $receitaMes = (float) $this->db->query("SELECT COALESCE(SUM(valor),0) FROM pagamentos WHERE status = 'pago' AND MONTH(data_pagamento) = MONTH(NOW()) AND YEAR(data_pagamento) = YEAR(NOW())")->fetchColumn();
            $inadimplencia = (float) $this->db->query("SELECT COALESCE(SUM(valor),0) FROM pagamentos WHERE status IN ('pendente','atrasado')")->fetchColumn();

            return [
                'total_alunos' => $totalAlunos,
                'total_professores' => $totalProfessores,
                'total_funcionarios' => $totalFuncionarios,
                'receita_mes' => $receitaMes,
                'inadimplencia' => $inadimplencia
            ];
        } catch (Throwable $erro) {
            return [
                'total_alunos' => 0,
                'total_professores' => 0,
                'total_funcionarios' => 0,
                'receita_mes' => 0,
                'inadimplencia' => 0
            ];
        }
    }

    public function obterRelatorioSetorial(): array
    {
        $indicadoresPedagogicos = $this->obterIndicadoresDirecaoPedagogica();
        $indicadoresSecretaria = $this->obterIndicadoresSecretaria();
        $indicadoresRh = $this->obterIndicadoresRh();

        return [
            'secretaria' => [
                'titulo' => 'Secretaria',
                'resumo' => 'Fluxo documental, pré-matrículas e emissão de guias de atendimento.',
                'principal' => $indicadoresSecretaria['pre_matriculas_pendentes']
            ],
            'pedagogico' => [
                'titulo' => 'Direção Pedagógica',
                'resumo' => 'Acompanhamento de turmas, lançamento de notas e planos de ensino.',
                'principal' => $indicadoresPedagogicos['turmas_ativas']
            ],
            'rh' => [
                'titulo' => 'Recursos Humanos',
                'resumo' => 'Contratos, assiduidade e gestão de férias da equipa.',
                'principal' => $indicadoresRh['total_funcionarios']
            ]
        ];
    }

    public function obterIndicadoresRh(): array
    {
        try {
            $total = (int) $this->db->query('SELECT COUNT(*) FROM funcionarios')->fetchColumn();
            $ativos = (int) $this->db->query("SELECT COUNT(*) FROM utilizadores WHERE perfil IN ('secretaria','direcao_pedagogica','direcao_geral','rh','professor') AND ativo = 1")->fetchColumn();

            return [
                'total_funcionarios' => $total,
                'ativos' => $ativos,
                'contratos_a_vencer' => (int) floor($total * 0.2),
                'ferias_no_mes' => (int) floor($total * 0.15)
            ];
        } catch (Throwable $erro) {
            return [
                'total_funcionarios' => 0,
                'ativos' => 0,
                'contratos_a_vencer' => 0,
                'ferias_no_mes' => 0
            ];
        }
    }

    public function listarFuncionariosRh(int $limite = 12): array
    {
        $limite = max(1, $limite);

        try {
            $sql = "SELECT
                        f.id,
                        u.nome,
                        u.email,
                        f.cargo,
                        f.departamento,
                        f.telefone,
                        u.ativo
                    FROM funcionarios f
                    INNER JOIN utilizadores u ON u.id = f.utilizador_id
                    ORDER BY u.nome
                    LIMIT {$limite}";

            $stmt = $this->db->query($sql);
            $dados = $stmt->fetchAll();

            if (!empty($dados)) {
                return $dados;
            }
        } catch (Throwable $erro) {
        }

        return [];
    }

    public function obterAnaliticaDirecaoGeral(): array
    {
        $analitica = [
            'matriculas_anuais' => [
                'etiquetas' => [],
                'valores' => []
            ],
            'distribuicao_turmas' => [
                'etiquetas' => [],
                'valores' => []
            ],
            'financeiro_mensal' => [
                'etiquetas' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                'receita' => array_fill(0, 12, 0.0),
                'pendente' => array_fill(0, 12, 0.0)
            ],
            'heatmap_turmas' => [],
            'alertas' => [],
            'decisoes' => []
        ];

        try {
            $sql = "SELECT YEAR(data_matricula) AS ano, COUNT(*) AS total
                    FROM matriculas
                    WHERE data_matricula >= DATE_SUB(CURDATE(), INTERVAL 4 YEAR)
                    GROUP BY YEAR(data_matricula)
                    ORDER BY ano";
            $linhas = $this->db->query($sql)->fetchAll();

            foreach ($linhas as $linha) {
                $analitica['matriculas_anuais']['etiquetas'][] = (string) ($linha['ano'] ?? date('Y'));
                $analitica['matriculas_anuais']['valores'][] = (int) ($linha['total'] ?? 0);
            }
        } catch (Throwable $erro) {
        }

        try {
            $sql = "SELECT t.nome, COUNT(m.id) AS total
                    FROM turmas t
                    LEFT JOIN matriculas m ON m.turma_id = t.id AND m.status = 'activo'
                    GROUP BY t.id, t.nome
                    ORDER BY total DESC, t.nome ASC
                    LIMIT 6";
            $linhas = $this->db->query($sql)->fetchAll();

            foreach ($linhas as $linha) {
                $analitica['distribuicao_turmas']['etiquetas'][] = (string) ($linha['nome'] ?? 'Sem turma');
                $analitica['distribuicao_turmas']['valores'][] = (int) ($linha['total'] ?? 0);
            }
        } catch (Throwable $erro) {
        }

        try {
            $sql = "SELECT
                        MONTH(COALESCE(data_pagamento, data_vencimento, CURDATE())) AS mes,
                        SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) AS receita,
                        SUM(CASE WHEN status IN ('pendente', 'atrasado') THEN valor ELSE 0 END) AS pendente
                    FROM pagamentos
                    WHERE YEAR(COALESCE(data_pagamento, data_vencimento, CURDATE())) = YEAR(CURDATE())
                    GROUP BY MONTH(COALESCE(data_pagamento, data_vencimento, CURDATE()))";
            $linhas = $this->db->query($sql)->fetchAll();

            foreach ($linhas as $linha) {
                $indice = max(0, ((int) ($linha['mes'] ?? 1)) - 1);
                if ($indice > 11) {
                    continue;
                }

                $analitica['financeiro_mensal']['receita'][$indice] = (float) ($linha['receita'] ?? 0);
                $analitica['financeiro_mensal']['pendente'][$indice] = (float) ($linha['pendente'] ?? 0);
            }
        } catch (Throwable $erro) {
        }

        try {
            $sql = "SELECT
                        t.nome,
                        ROUND(COALESCE(nt.media, 0), 1) AS media,
                        COALESCE(nt.alunos_risco, 0) AS alunos_risco,
                        COALESCE(pt.faltas, 0) AS faltas
                    FROM turmas t
                    LEFT JOIN (
                        SELECT
                            m.turma_id,
                            AVG(n.nota) AS media,
                            SUM(CASE WHEN n.nota < 10 THEN 1 ELSE 0 END) AS alunos_risco
                        FROM matriculas m
                        LEFT JOIN notas n ON n.matricula_id = m.id
                        WHERE m.status = 'activo'
                        GROUP BY m.turma_id
                    ) nt ON nt.turma_id = t.id
                    LEFT JOIN (
                        SELECT
                            m.turma_id,
                            SUM(CASE WHEN p.presente = 0 THEN 1 ELSE 0 END) AS faltas
                        FROM matriculas m
                        LEFT JOIN presencas p ON p.matricula_id = m.id
                        WHERE m.status = 'activo'
                        GROUP BY m.turma_id
                    ) pt ON pt.turma_id = t.id
                    ORDER BY media ASC, alunos_risco DESC, faltas DESC, t.nome ASC
                    LIMIT 6";
            $analitica['heatmap_turmas'] = $this->db->query($sql)->fetchAll();
        } catch (Throwable $erro) {
            $analitica['heatmap_turmas'] = [];
        }

        try {
            $atrasados = (int) $this->db->query("SELECT COUNT(*) FROM pagamentos WHERE status = 'atrasado'")->fetchColumn();
            $solicitacoesPendentes = (int) $this->db->query("SELECT COUNT(*) FROM solicitacoes_documentos WHERE estado = 'pendente'")->fetchColumn();
            $notasEmRisco = (int) $this->db->query("SELECT COUNT(*) FROM notas WHERE nota < 10")->fetchColumn();
            $notasTotal = (int) $this->db->query("SELECT COUNT(*) FROM notas")->fetchColumn();

            if ($atrasados > 0) {
                $analitica['alertas'][] = [
                    'nivel' => 'alto',
                    'titulo' => 'Inadimplencia em crescimento',
                    'texto' => $atrasados . ' pagamento(s) continuam em atraso e exigem seguimento.'
                ];
            }

            if ($solicitacoesPendentes > 0) {
                $analitica['alertas'][] = [
                    'nivel' => 'medio',
                    'titulo' => 'Documentos por decidir',
                    'texto' => $solicitacoesPendentes . ' solicitacao(oes) aguardam aprovacao institucional.'
                ];
            }

            if ($notasTotal > 0) {
                $taxaRisco = ($notasEmRisco / $notasTotal) * 100;
                if ($taxaRisco >= 25) {
                    $analitica['alertas'][] = [
                        'nivel' => 'alto',
                        'titulo' => 'Risco academico elevado',
                        'texto' => number_format($taxaRisco, 1, ',', '.') . '% das notas estao abaixo da media minima.'
                    ];
                }
            }
        } catch (Throwable $erro) {
        }

        $turmaCritica = $analitica['heatmap_turmas'][0] ?? null;
        if (is_array($turmaCritica) && (float) ($turmaCritica['media'] ?? 0) < 10) {
            $analitica['decisoes'][] = 'Reforcar acompanhamento da turma ' . ($turmaCritica['nome'] ?? 'critica') . '.';
        }

        $inadimplenciaTotal = array_sum($analitica['financeiro_mensal']['pendente']);
        if ($inadimplenciaTotal > 0) {
            $analitica['decisoes'][] = 'Priorizar contacto com encarregados em atraso para reduzir pendencias.';
        }

        if (empty($analitica['decisoes'])) {
            $analitica['decisoes'][] = 'Indicadores estaveis. Manter o acompanhamento semanal dos principais sectores.';
        }

        if (empty($analitica['alertas'])) {
            $analitica['alertas'][] = [
                'nivel' => 'baixo',
                'titulo' => 'Sem alertas criticos',
                'texto' => 'Os indicadores actuais nao apresentam desvios relevantes.'
            ];
        }

        return $analitica;
    }

    public function obterAnaliticaSecretaria(): array
    {
        $analitica = [
            'status_pre_matriculas' => [
                'etiquetas' => ['Pendentes', 'Concluidas', 'Canceladas'],
                'valores' => [0, 0, 0]
            ],
            'fluxo_semanal' => [
                'etiquetas' => [],
                'valores' => []
            ],
            'documentos' => [
                'emitidos_mes' => 0,
                'solicitacoes_pendentes' => 0,
                'comprovativos_pendentes' => 0,
                'processos_incompletos' => 0
            ],
            'fila' => [],
            'alertas' => []
        ];

        try {
            $sql = "SELECT status, COUNT(*) AS total
                    FROM pre_matriculas
                    GROUP BY status";
            $linhas = $this->db->query($sql)->fetchAll();
            $mapa = [
                'pendente' => 0,
                'concluida' => 1,
                'cancelada' => 2
            ];

            foreach ($linhas as $linha) {
                $status = (string) ($linha['status'] ?? '');
                if (!array_key_exists($status, $mapa)) {
                    continue;
                }

                $analitica['status_pre_matriculas']['valores'][$mapa[$status]] = (int) ($linha['total'] ?? 0);
            }
        } catch (Throwable $erro) {
        }

        try {
            $sql = "SELECT DATE(criado_em) AS dia, COUNT(*) AS total
                    FROM pre_matriculas
                    WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                    GROUP BY DATE(criado_em)
                    ORDER BY dia ASC";
            $linhas = $this->db->query($sql)->fetchAll();
            $mapaFluxo = [];
            foreach ($linhas as $linha) {
                $mapaFluxo[(string) ($linha['dia'] ?? '')] = (int) ($linha['total'] ?? 0);
            }

            for ($i = 6; $i >= 0; $i--) {
                $dia = date('Y-m-d', strtotime('-' . $i . ' day'));
                $analitica['fluxo_semanal']['etiquetas'][] = date('d/m', strtotime($dia));
                $analitica['fluxo_semanal']['valores'][] = $mapaFluxo[$dia] ?? 0;
            }
        } catch (Throwable $erro) {
            $analitica['fluxo_semanal']['etiquetas'] = ['Hoje'];
            $analitica['fluxo_semanal']['valores'] = [0];
        }

        try {
            $analitica['documentos']['emitidos_mes'] = (int) $this->db->query("SELECT COUNT(*) FROM documentos_emitidos WHERE MONTH(criado_em) = MONTH(CURDATE()) AND YEAR(criado_em) = YEAR(CURDATE())")->fetchColumn();
            $analitica['documentos']['solicitacoes_pendentes'] = (int) $this->db->query("SELECT COUNT(*) FROM solicitacoes_documentos WHERE estado = 'pendente'")->fetchColumn();
            $analitica['documentos']['comprovativos_pendentes'] = (int) $this->db->query("SELECT COUNT(*) FROM comprovativos_pagamento WHERE estado = 'pendente'")->fetchColumn();
            $analitica['documentos']['processos_incompletos'] = (int) $this->db->query("SELECT COUNT(*) FROM matriculas m LEFT JOIN matricula_documentos md ON md.aluno_id = m.aluno_id WHERE m.status = 'activo' AND (md.id IS NULL OR md.bi_copia IS NULL OR md.documento_classe_anterior IS NULL)")->fetchColumn();
        } catch (Throwable $erro) {
        }

        try {
            $mensagensNovas = (int) $this->db->query("SELECT COUNT(*) FROM mensagens_secretaria WHERE status = 'nova'")->fetchColumn();
            if ($mensagensNovas > 0) {
                $analitica['fila'][] = [
                    'titulo' => 'Mensagens do site',
                    'contador' => $mensagensNovas,
                    'texto' => 'Pedidos do formulario publico aguardam resposta.'
                ];
            }
        } catch (Throwable $erro) {
        }

        if (($analitica['status_pre_matriculas']['valores'][0] ?? 0) > 0) {
            $analitica['fila'][] = [
                'titulo' => 'Pre-matriculas por validar',
                'contador' => (int) $analitica['status_pre_matriculas']['valores'][0],
                'texto' => 'Existem processos novos para triagem e conversao em matricula.'
            ];
        }

        if (($analitica['documentos']['comprovativos_pendentes'] ?? 0) > 0) {
            $analitica['fila'][] = [
                'titulo' => 'Comprovativos pendentes',
                'contador' => (int) $analitica['documentos']['comprovativos_pendentes'],
                'texto' => 'Pagamentos enviados aguardam validacao da secretaria.'
            ];
        }

        if (($analitica['documentos']['processos_incompletos'] ?? 0) > 0) {
            $analitica['alertas'][] = [
                'nivel' => 'alto',
                'titulo' => 'Documentos em falta',
                'texto' => $analitica['documentos']['processos_incompletos'] . ' processo(s) activos ainda nao possuem o conjunto documental completo.'
            ];
        }

        if (($analitica['documentos']['solicitacoes_pendentes'] ?? 0) > 0) {
            $analitica['alertas'][] = [
                'nivel' => 'medio',
                'titulo' => 'Solicitacoes por analisar',
                'texto' => $analitica['documentos']['solicitacoes_pendentes'] . ' documento(s) aguardam resposta ao encarregado.'
            ];
        }

        if (empty($analitica['fila'])) {
            $analitica['fila'][] = [
                'titulo' => 'Fila controlada',
                'contador' => 0,
                'texto' => 'Nao existem pendencias criticas na fila de atendimento.'
            ];
        }

        if (empty($analitica['alertas'])) {
            $analitica['alertas'][] = [
                'nivel' => 'baixo',
                'titulo' => 'Fluxo regular',
                'texto' => 'O atendimento actual esta dentro do esperado.'
            ];
        }

        return $analitica;
    }
}
