<!doctype html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CRUD Administrativo - FASCAL</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= base_url('assets/css/estilo.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/modo-escuro.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/paginas/administracao-painel.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/paginas/administracao.css') ?>">
</head>
<body>
  <div class="layout-painel">
    <aside class="menu-lateral">
      <h1>CRUD Administrativo</h1>
      <p><?= escapar($_SESSION['usuario_nome'] ?? '') ?></p>
      <nav>
        <a class="activo" href="<?= base_url('administracao') ?>">Gestao de dados</a>
        <a href="<?= base_url('documentos') ?>">Documentos oficiais</a>
        <a href="<?= base_url('painel') ?>">Voltar ao painel</a>
        <a href="<?= base_url('login/sair') ?>">Terminar sessao</a>
      </nav>
    </aside>

    <main class="conteudo-painel">
      <header class="topo-painel">
        <div>
          <h2>Operacoes administrativas por perfil</h2>
          <p class="texto-pequeno">Crie, actualize e remova registos de alunos, turmas, disciplinas e pagamentos.</p>
        </div>
        <button class="botao" type="button" data-toggle-tema>Modo escuro</button>
      </header>

      <?php if (!empty($mensagem)): ?>
        <div class="alerta sucesso"><?= escapar($mensagem) ?></div>
      <?php endif; ?>

      <?php if (!empty($erro)): ?>
        <div class="alerta erro"><?= escapar($erro) ?></div>
      <?php endif; ?>

      <section class="cartao abas-admin">
        <a class="aba <?= $aba === 'alunos' ? 'activa' : '' ?> <?= in_array('alunos', $abasPermitidas, true) ? '' : 'oculta' ?>"
           href="<?= base_url('administracao?aba=alunos') ?>">Alunos</a>
        <a class="aba <?= $aba === 'turmas' ? 'activa' : '' ?> <?= in_array('turmas', $abasPermitidas, true) ? '' : 'oculta' ?>"
           href="<?= base_url('administracao?aba=turmas') ?>">Turmas</a>
        <a class="aba <?= $aba === 'disciplinas' ? 'activa' : '' ?> <?= in_array('disciplinas', $abasPermitidas, true) ? '' : 'oculta' ?>"
           href="<?= base_url('administracao?aba=disciplinas') ?>">Disciplinas</a>
        <a class="aba <?= $aba === 'pagamentos' ? 'activa' : '' ?> <?= in_array('pagamentos', $abasPermitidas, true) ? '' : 'oculta' ?>"
           href="<?= base_url('administracao?aba=pagamentos') ?>">Pagamentos</a>
      </section>

      <?php if ($aba === 'alunos'): ?>
        <section class="cartao secao-painel">
          <h3>Novo aluno</h3>
          <form class="form-admin" method="POST" action="<?= base_url('administracao/alunos-criar') ?>">
            <?= csrf_field() ?>
            <div class="grade col-3">
              <div class="form-grupo"><label>Nome</label><input name="nome" required></div>
              <div class="form-grupo"><label>Email</label><input name="email" type="email" required></div>
              <div class="form-grupo"><label>Senha inicial</label><input name="senha" placeholder="12345678"></div>
              <div class="form-grupo"><label>BI</label><input name="bi"></div>
              <div class="form-grupo"><label>Data nascimento</label><input name="data_nascimento" type="date" required></div>
              <div class="form-grupo"><label>Contacto</label><input name="contato"></div>
              <div class="form-grupo">
                <label>Genero</label>
                <select name="genero">
                  <option value="">Não definido</option>
                  <option value="M">Masculino</option>
                  <option value="F">Feminino</option>
                </select>
              </div>
              <div class="form-grupo">
                <label>Turma</label>
                <select name="turma_id">
                  <option value="0">Sem turma</option>
                  <?php foreach ($turmas as $turma): ?>
                    <option value="<?= (int) $turma['id'] ?>"><?= escapar($turma['nome']) ?> (<?= escapar($turma['ano_letivo']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button class="botao" type="submit">Criar aluno</button>
          </form>
        </section>

        <section class="cartao secao-painel">
          <h3>Actualizar aluno</h3>
          <form id="form-editar-aluno" class="form-admin" method="POST" action="<?= base_url('administracao/alunos-actualizar') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aluno_id" id="editar-aluno-id">
            <div class="grade col-3">
              <div class="form-grupo"><label>Nome</label><input name="nome" id="editar-aluno-nome" required></div>
              <div class="form-grupo"><label>Email</label><input name="email" type="email" id="editar-aluno-email" required></div>
              <div class="form-grupo"><label>Nova senha</label><input name="senha" id="editar-aluno-senha" placeholder="Opcional"></div>
              <div class="form-grupo"><label>BI</label><input name="bi" id="editar-aluno-bi"></div>
              <div class="form-grupo"><label>Data nascimento</label><input name="data_nascimento" type="date" id="editar-aluno-data" required></div>
              <div class="form-grupo"><label>Contacto</label><input name="contato" id="editar-aluno-contacto"></div>
              <div class="form-grupo">
                <label>Genero</label>
                <select name="genero" id="editar-aluno-genero">
                  <option value="">Não definido</option>
                  <option value="M">Masculino</option>
                  <option value="F">Feminino</option>
                </select>
              </div>
              <div class="form-grupo">
                <label>Turma</label>
                <select name="turma_id" id="editar-aluno-turma">
                  <option value="0">Sem turma</option>
                  <?php foreach ($turmas as $turma): ?>
                    <option value="<?= (int) $turma['id'] ?>"><?= escapar($turma['nome']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button class="botao" type="submit">Guardar alteracoes</button>
          </form>
        </section>

        <section class="cartao secao-painel">
          <h3>Lista de alunos</h3>
          <div class="tabela-responsiva">
            <table>
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Email</th>
                  <th>BI</th>
                  <th>Nascimento</th>
                  <th>Contacto</th>
                  <th>Turma</th>
                  <th>Acoes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($alunos as $item): ?>
                  <tr>
                    <td><?= escapar($item['nome']) ?></td>
                    <td><?= escapar($item['email']) ?></td>
                    <td><?= escapar($item['bi']) ?></td>
                    <td><?= formatar_data($item['data_nascimento']) ?></td>
                    <td><?= escapar($item['contato']) ?></td>
                    <td><?= escapar($item['turma']) ?></td>
                    <td>
                      <button class="botao pequeno js-editar-aluno"
                              type="button"
                              data-id="<?= (int) $item['id'] ?>"
                              data-nome="<?= escapar($item['nome']) ?>"
                              data-email="<?= escapar($item['email']) ?>"
                              data-bi="<?= escapar($item['bi']) ?>"
                              data-data="<?= escapar((string) $item['data_nascimento']) ?>"
                              data-contacto="<?= escapar($item['contato']) ?>"
                              data-turma="<?= (int) $item['turma_id'] ?>">
                        Editar
                      </button>
                      <form method="POST" action="<?= base_url('administracao/alunos-remover') ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aluno_id" value="<?= (int) $item['id'] ?>">
                        <button class="botao secundario pequeno" type="submit">Remover</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($aba === 'turmas'): ?>
        <section class="cartao secao-painel">
          <h3>Nova turma</h3>
          <form class="form-admin" method="POST" action="<?= base_url('administracao/turmas-criar') ?>">
            <?= csrf_field() ?>
            <div class="grade col-3">
              <div class="form-grupo"><label>Nome</label><input name="nome" required></div>
              <div class="form-grupo"><label>Ano lectivo</label><input name="ano_letivo" placeholder="2026/2027" required></div>
              <div class="form-grupo"><label>Capacidade</label><input name="capacidade" type="number" min="1" required></div>
              <div class="form-grupo">
                <label>Professor responsavel</label>
                <select name="professor_id">
                  <option value="0">Sem professor</option>
                  <?php foreach ($professores as $prof): ?>
                    <option value="<?= (int) $prof['id'] ?>"><?= escapar($prof['nome']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button class="botao" type="submit">Criar turma</button>
          </form>
        </section>

        <section class="cartao secao-painel">
          <h3>Actualizar turma</h3>
          <form id="form-editar-turma" class="form-admin" method="POST" action="<?= base_url('administracao/turmas-actualizar') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="turma_id" id="editar-turma-id">
            <div class="grade col-3">
              <div class="form-grupo"><label>Nome</label><input name="nome" id="editar-turma-nome" required></div>
              <div class="form-grupo"><label>Ano lectivo</label><input name="ano_letivo" id="editar-turma-ano" required></div>
              <div class="form-grupo"><label>Capacidade</label><input name="capacidade" type="number" min="1" id="editar-turma-capacidade" required></div>
              <div class="form-grupo">
                <label>Professor</label>
                <select name="professor_id" id="editar-turma-professor">
                  <option value="0">Sem professor</option>
                  <?php foreach ($professores as $prof): ?>
                    <option value="<?= (int) $prof['id'] ?>"><?= escapar($prof['nome']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button class="botao" type="submit">Guardar alteracoes</button>
          </form>
        </section>

        <section class="cartao secao-painel">
          <h3>Lista de turmas</h3>
          <div class="tabela-responsiva">
            <table>
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Ano lectivo</th>
                  <th>Capacidade</th>
                  <th>Professor</th>
                  <th>Total alunos</th>
                  <th>Acoes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($turmas as $item): ?>
                  <tr>
                    <td><?= escapar($item['nome']) ?></td>
                    <td><?= escapar($item['ano_letivo']) ?></td>
                    <td><?= (int) $item['capacidade'] ?></td>
                    <td><?= escapar($item['professor']) ?></td>
                    <td><?= (int) $item['total_alunos'] ?></td>
                    <td>
                      <button class="botao pequeno js-editar-turma"
                              type="button"
                              data-id="<?= (int) $item['id'] ?>"
                              data-nome="<?= escapar($item['nome']) ?>"
                              data-ano="<?= escapar($item['ano_letivo']) ?>"
                              data-capacidade="<?= (int) $item['capacidade'] ?>"
                              data-professor="<?= (int) $item['professor_id'] ?>">Editar</button>
                      <form method="POST" action="<?= base_url('administracao/turmas-remover') ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="turma_id" value="<?= (int) $item['id'] ?>">
                        <button class="botao secundario pequeno" type="submit">Remover</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($aba === 'disciplinas'): ?>
        <section class="cartao secao-painel">
          <h3>Nova disciplina</h3>
          <form class="form-admin" method="POST" action="<?= base_url('administracao/disciplinas-criar') ?>">
            <?= csrf_field() ?>
            <div class="grade col-3">
              <div class="form-grupo"><label>Nome</label><input name="nome" required></div>
              <div class="form-grupo"><label>Carga horaria semanal</label><input name="carga_horaria" type="number" min="1" required></div>
            </div>
            <button class="botao" type="submit">Criar disciplina</button>
          </form>
        </section>

        <section class="cartao secao-painel">
          <h3>Actualizar disciplina</h3>
          <form id="form-editar-disciplina" class="form-admin" method="POST" action="<?= base_url('administracao/disciplinas-actualizar') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="disciplina_id" id="editar-disciplina-id">
            <div class="grade col-3">
              <div class="form-grupo"><label>Nome</label><input name="nome" id="editar-disciplina-nome" required></div>
              <div class="form-grupo"><label>Carga horaria</label><input name="carga_horaria" type="number" min="1" id="editar-disciplina-carga" required></div>
            </div>
            <button class="botao" type="submit">Guardar alteracoes</button>
          </form>
        </section>

        <section class="cartao secao-painel">
          <h3>Lista de disciplinas</h3>
          <div class="tabela-responsiva">
            <table>
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Carga horaria</th>
                  <th>Acoes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($disciplinas as $item): ?>
                  <tr>
                    <td><?= escapar($item['nome']) ?></td>
                    <td><?= (int) $item['carga_horaria'] ?></td>
                    <td>
                      <button class="botao pequeno js-editar-disciplina"
                              type="button"
                              data-id="<?= (int) $item['id'] ?>"
                              data-nome="<?= escapar($item['nome']) ?>"
                              data-carga="<?= (int) $item['carga_horaria'] ?>">Editar</button>
                      <form method="POST" action="<?= base_url('administracao/disciplinas-remover') ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="disciplina_id" value="<?= (int) $item['id'] ?>">
                        <button class="botao secundario pequeno" type="submit">Remover</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($aba === 'pagamentos'): ?>
        <section class="cartao secao-painel">
          <h3>Novo pagamento</h3>
          <form class="form-admin" method="POST" action="<?= base_url('administracao/pagamentos-criar') ?>">
            <?= csrf_field() ?>
            <div class="grade col-3">
              <div class="form-grupo">
                <label>Matricula activa</label>
                <select name="matricula_id" required>
                  <option value="">Selecione</option>
                  <?php foreach ($matriculasAtivas as $mat): ?>
                    <option value="<?= (int) $mat['id'] ?>"><?= escapar($mat['referencia']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-grupo"><label>Descrição</label><input name="descricao" required></div>
              <div class="form-grupo"><label>Valor (AOA)</label><input name="valor" type="number" min="0" step="0.01" required></div>
              <div class="form-grupo"><label>Data de vencimento</label><input name="data_vencimento" type="date" required></div>
              <div class="form-grupo">
                <label>Estado</label>
                <select name="status">
                  <option value="pendente">Pendente</option>
                  <option value="pago">Pago</option>
                  <option value="atrasado">Atrasado</option>
                </select>
              </div>
            </div>
            <button class="botao" type="submit">Registar pagamento</button>
          </form>
        </section>

        <section class="cartao secao-painel">
          <h3>Actualizar pagamento</h3>
          <form id="form-editar-pagamento" class="form-admin" method="POST" action="<?= base_url('administracao/pagamentos-actualizar') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="pagamento_id" id="editar-pagamento-id">
            <div class="grade col-3">
              <div class="form-grupo"><label>Descrição</label><input name="descricao" id="editar-pagamento-descricao" required></div>
              <div class="form-grupo"><label>Valor</label><input name="valor" type="number" min="0" step="0.01" id="editar-pagamento-valor" required></div>
              <div class="form-grupo"><label>Data vencimento</label><input name="data_vencimento" type="date" id="editar-pagamento-vencimento"></div>
              <div class="form-grupo"><label>Data pagamento</label><input name="data_pagamento" type="date" id="editar-pagamento-data"></div>
              <div class="form-grupo">
                <label>Estado</label>
                <select name="status" id="editar-pagamento-status">
                  <option value="pendente">Pendente</option>
                  <option value="pago">Pago</option>
                  <option value="atrasado">Atrasado</option>
                </select>
              </div>
            </div>
            <button class="botao" type="submit">Guardar alteracoes</button>
          </form>
        </section>

        <section class="cartao secao-painel">
          <h3>Lista de pagamentos</h3>
          <div class="tabela-responsiva">
            <table>
              <thead>
                <tr>
                  <th>Aluno</th>
                  <th>Descrição</th>
                  <th>Valor</th>
                  <th>Vencimento</th>
                  <th>Pagamento</th>
                  <th>Estado</th>
                  <th>Acoes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pagamentos as $item): ?>
                  <tr>
                    <td><?= escapar($item['aluno']) ?></td>
                    <td><?= escapar($item['descricao']) ?></td>
                    <td><?= number_format((float) $item['valor'], 2, ',', '.') ?></td>
                    <td><?= formatar_data($item['data_vencimento']) ?></td>
                    <td><?= formatar_data($item['data_pagamento']) ?></td>
                    <td><?= escapar(ucfirst($item['status'])) ?></td>
                    <td>
                      <button class="botao pequeno js-editar-pagamento"
                              type="button"
                              data-id="<?= (int) $item['id'] ?>"
                              data-descricao="<?= escapar($item['descricao']) ?>"
                              data-valor="<?= escapar((string) $item['valor']) ?>"
                              data-vencimento="<?= escapar((string) $item['data_vencimento']) ?>"
                              data-pagamento="<?= escapar((string) $item['data_pagamento']) ?>"
                              data-status="<?= escapar($item['status']) ?>">Editar</button>
                      <form method="POST" action="<?= base_url('administracao/pagamentos-remover') ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="pagamento_id" value="<?= (int) $item['id'] ?>">
                        <button class="botao secundario pequeno" type="submit">Remover</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>
    </main>
  </div>

  <script src="<?= base_url('assets/js/main.js') ?>"></script>
  <script src="<?= base_url('assets/js/paginas/administracao.js') ?>"></script>
</body>
</html>
