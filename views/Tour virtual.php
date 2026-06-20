<?php
$icone = imagem_url('icones');
$baseUrl = base_url('');
$placeholderImagem = base_url('assets/Placeholder de imagem.jpeg');
$audioPlaceholder = audio_url('administrativa.mp3');
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
  <title>Tour Virtual | Instituto Politécnico Privado Mundo Novo II</title>
  <link rel="icon" href="<?= $icone ?>/logo.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <style>
    /* ========== VARIÁVEIS & RESET ========== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --azul-profundo: #0a2b4e;
      --azul-principal: #1e4a76;
      --azul-claro: #eef2ff;
      --destaque: #f59e0b;
      --destaque-hover: #d97706;
      --cinza-suave: #f8fafc;
      --cinza-texto: #334155;
      --cinza-borda: #e2e8f0;
      --branco: #ffffff;
      --sombra-padrao: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
      --sombra-media: 0 20px 25px -12px rgba(0, 0, 0, 0.1);
      --transicao: all 0.25s ease;
    }

    body {
      font-family: system-ui, 'Segoe UI', 'Inter', -apple-system, sans-serif;
      background: var(--cinza-suave);
      color: var(--cinza-texto);
      line-height: 1.5;
    }

    .container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 1.5rem;
    }

    /* ========== HEADER ========== */
    .pagina-topo {
      background: var(--branco);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid var(--cinza-borda);
      position: sticky;
      top: 0;
      z-index: 20;
    }

    .topo-grid {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 2rem;
      padding: 0.75rem 0;
    }

    .logo-topo {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
    }

    .logo-topo img {
      width: 48px;
      height: 48px;
      object-fit: contain;
    }

    .logo-texto span {
      font-size: 0.8rem;
      color: #64748b;
    }

    .logo-texto strong {
      font-size: 1rem;
      color: var(--azul-principal);
    }

    .topo-links {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    .topo-links a {
      padding: 0.5rem 1rem;
      border-radius: 40px;
      font-weight: 500;
      text-decoration: none;
      color: var(--azul-principal);
      transition: var(--transicao);
      background: transparent;
    }

    .topo-links a:hover {
      background: var(--azul-claro);
    }

    .topo-links .link-acao {
      background: var(--azul-principal);
      color: white;
    }

    .topo-links .link-acao:hover {
      background: var(--azul-profundo);
      transform: translateY(-2px);
    }

    /* ========== HERO ========== */
    .hero {
      padding: 4rem 0 5rem;
      background: linear-gradient(135deg, rgba(10, 43, 78, 0.85), rgba(30, 74, 118, 0.75)), var(--hero-image);
      background-size: cover;
      background-position: center;
      color: white;
    }

    .hero-grid {
      display: grid;
      grid-template-columns: 1fr 0.8fr;
      gap: 3rem;
      align-items: center;
    }

    .badge {
      display: inline-flex;
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(4px);
      padding: 0.3rem 1rem;
      border-radius: 60px;
      font-size: 0.8rem;
      font-weight: 500;
      margin-bottom: 1rem;
    }

    .hero-texto h1 {
      font-size: clamp(2rem, 5vw, 3rem);
      margin-bottom: 1rem;
      font-weight: 700;
      line-height: 1.2;
    }

    .hero-acoes {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-top: 2rem;
    }

    .botao {
      background: white;
      color: var(--azul-principal);
      padding: 0.75rem 1.8rem;
      border-radius: 48px;
      font-weight: 600;
      text-decoration: none;
      transition: var(--transicao);
      box-shadow: var(--sombra-padrao);
    }

    .botao:hover {
      transform: translateY(-3px);
      box-shadow: var(--sombra-media);
    }

    .botao.secundario {
      background: transparent;
      color: white;
      border: 1.5px solid white;
      box-shadow: none;
    }

    .botao.secundario:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateY(-3px);
    }

    .hero-card {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(12px);
      border-radius: 32px;
      padding: 1.8rem;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* ========== SEÇÃO GERAL ========== */
    .secao {
      padding: 4rem 0;
    }

    .secao-cabecalho {
      text-align: center;
      margin-bottom: 2.5rem;
    }

    .secao-cabecalho h2 {
      font-size: 2rem;
      color: var(--azul-profundo);
      margin-bottom: 0.5rem;
    }

    .video-frame {
      border-radius: 28px;
      overflow: hidden;
      box-shadow: var(--sombra-media);
    }

    video {
      width: 100%;
      display: block;
    }

    /* ========== TOUR LAYOUT ========== */
    .tour-layout {
      display: grid;
      grid-template-columns: 1.4fr 0.6fr;
      gap: 2rem;
    }

    .tour-visual {
      background: var(--branco);
      border-radius: 28px;
      box-shadow: var(--sombra-padrao);
      padding: 1.5rem;
      transition: var(--transicao);
    }

    .tour-viewport {
      position: relative;
      overflow: hidden;
      border-radius: 20px;
      background: #0f172a;
      height: 400px;
      cursor: grab;
    }

    .tour-viewport:active {
      cursor: grabbing;
    }

    .tour-media {
      position: absolute;
      inset: 0;
      transform-origin: center;
      transition: transform 0.1s linear;
    }

    #tour-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .tour-hotspots {
      position: absolute;
      inset: 0;
    }

    .tour-hotspot {
      position: absolute;
      width: 28px;
      height: 28px;
      background: var(--destaque);
      border: 2px solid white;
      border-radius: 50%;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
      transform: translate(-50%, -50%);
    }

    .tour-hotspot:hover {
      transform: translate(-50%, -50%) scale(1.2);
      background: var(--destaque-hover);
    }

    .tour-controls {
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 0.8rem;
      margin-top: 1rem;
    }

    .zoom-controls, .nav-controls {
      display: flex;
      gap: 0.5rem;
    }

    .tour-controls button {
      background: var(--cinza-suave);
      border: none;
      padding: 0.6rem 1rem;
      border-radius: 40px;
      font-weight: 500;
      color: var(--azul-principal);
      cursor: pointer;
      transition: var(--transicao);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.9rem;
    }

    .tour-controls button i {
      font-size: 0.9rem;
    }

    .tour-controls button:hover {
      background: var(--azul-claro);
      transform: translateY(-2px);
    }

    .tour-caption {
      margin-top: 1.2rem;
    }

    .tour-caption h3 {
      color: var(--azul-profundo);
      margin-bottom: 0.25rem;
    }

    .tour-audio {
      margin-top: 0.8rem;
    }

    .tour-audio span {
      font-size: 0.8rem;
      font-weight: 500;
      display: block;
      margin-bottom: 0.3rem;
    }

    audio {
      width: 100%;
      border-radius: 40px;
    }

    .tour-detail {
      margin-top: 1.2rem;
      background: var(--azul-claro);
      border-radius: 20px;
      padding: 1rem;
      animation: fadeIn 0.2s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px);}
      to { opacity: 1; transform: translateY(0);}
    }

    .tour-detail[hidden] {
      display: none;
    }

    .tour-detail-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.8rem;
    }

    .tour-detail-header strong {
      color: var(--azul-profundo);
    }

    #tour-detail-close {
      background: none;
      border: none;
      font-size: 1.2rem;
      cursor: pointer;
      color: #64748b;
    }

    .tour-detail-body img {
      width: 100%;
      border-radius: 16px;
      max-height: 160px;
      object-fit: cover;
      margin-bottom: 0.8rem;
    }

    /* Sidebar */
    .tour-sidebar {
      background: var(--branco);
      border-radius: 28px;
      box-shadow: var(--sombra-padrao);
      padding: 1.5rem;
    }

    .tour-sidebar h3 {
      color: var(--azul-profundo);
      margin-bottom: 1rem;
      font-size: 1.3rem;
    }

    .tour-areas {
      display: flex;
      flex-direction: column;
      gap: 0.7rem;
    }

    .tour-area {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.7rem;
      border-radius: 20px;
      background: var(--cinza-suave);
      border: 1px solid transparent;
      cursor: pointer;
      transition: var(--transicao);
      text-align: left;
    }

    .tour-area img {
      width: 56px;
      height: 56px;
      object-fit: cover;
      border-radius: 16px;
    }

    .tour-area span {
      font-weight: 500;
      color: var(--cinza-texto);
    }

    .tour-area.ativo {
      background: var(--azul-claro);
      border-color: var(--azul-principal);
      box-shadow: var(--sombra-padrao);
    }

    .tour-area.ativo span {
      color: var(--azul-principal);
      font-weight: 600;
    }

    /* ========== FOOTER ========== */
    .footer {
      background: var(--azul-profundo);
      color: white;
      padding: 4rem 0 2rem;
      margin-top: 4rem;
      border-top-left-radius: 32px;
      border-top-right-radius: 32px;
    }

    .footer-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 2rem;
    }

    .footer-logo {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .logo-footer {
      width: 50px;
      border-radius: 50%;
    }

    .titulo-footer {
      font-size: 1rem;
      font-weight: 600;
    }

    .redes-sociais {
      display: flex;
      gap: 0.8rem;
      margin-top: 1.5rem;
    }

    .rede-social {
      background: rgba(255,255,255,0.1);
      width: 38px;
      height: 38px;
      border-radius: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      transition: var(--transicao);
    }

    .rede-social:hover {
      background: var(--destaque);
      transform: translateY(-3px);
    }

    .titulo-coluna {
      color: var(--destaque);
      margin-bottom: 1rem;
      font-size: 1.1rem;
    }

    .footer-links, .footer-contatos {
      list-style: none;
    }

    .footer-links li, .footer-contatos li {
      margin-bottom: 0.7rem;
    }

    .footer-links a {
      color: #cbd5e1;
      text-decoration: none;
      transition: var(--transicao);
    }

    .footer-links a:hover {
      color: white;
      padding-left: 5px;
    }

    .footer-contatos i {
      width: 24px;
      color: var(--destaque);
    }

    .footer-inferior {
      border-top: 1px solid rgba(255,255,255,0.1);
      margin-top: 2rem;
      padding-top: 2rem;
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }

    @media (max-width: 1024px) {
      .footer-grid { grid-template-columns: repeat(2,1fr); }
      .tour-layout { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .topo-grid { flex-direction: column; align-items: stretch; }
      .hero-grid { grid-template-columns: 1fr; }
      .footer-grid { grid-template-columns: 1fr; }
      .tour-viewport { height: 280px; }
    }
    @media (max-width: 480px) {
      .tour-area { flex-direction: column; text-align: center; }
      .tour-area img { width: 80px; height: 80px; }
    }
  </style>
</head>
<body data-base-url="<?= $baseUrl ?>/">

<header class="pagina-topo">
  <div class="container topo-grid">
    <a class="logo-topo" href="<?= base_url('') ?>">
      <img src="<?= $icone ?>/logo.png" alt="Logo" />
      <div class="logo-texto">
        <span>Instituto Politécnico Privado</span>
        <strong>Mundo Novo II</strong>
      </div>
    </a>
    <nav class="topo-links">
      <a href="<?= base_url('') ?>"><i class="fas fa-home"></i> Início</a>
      <a href="<?= base_url('') ?>#cursos">Cursos</a>
      <a href="<?= base_url('') ?>#vida">Vida Estudantil</a>
      <a class="link-acao" href="<?= base_url('matricula') ?>"><i class="fas fa-pen-alt"></i> Matrícula</a>
    </nav>
  </div>
</header>

<main>
  <section class="hero" style="--hero-image: url('<?= escapar($placeholderImagem) ?>');">
    <div class="container hero-grid">
      <div class="hero-texto">
        <span class="badge"><i class="fas fa-vr-cardboard"></i> Experiência Imersiva</span>
        <h1>Faça um Tour Virtual pelo Instituto</h1>
        <p>Explore cada ambiente com imagens reais, hotspots interativos e narração guiada.</p>
        <div class="hero-acoes">
          <a class="botao" href="#tour-interativo"><i class="fas fa-play"></i> Iniciar tour</a>
          <a class="botao secundario" href="<?= base_url('') ?>#contacto-secretaria">Agendar visita</a>
        </div>
      </div>
      <div class="hero-card">
        <h2><i class="fas fa-building"></i> Estrutura completa</h2>
        <p>Conheça salas, laboratórios, biblioteca, áreas desportivas e muito mais — tudo em alta resolução.</p>
      </div>
    </div>
  </section>

  <section class="secao" id="tour-interativo">
    <div class="container">
      <div class="secao-cabecalho">
        <h2>Tour Virtual Interativo</h2>
        <p>Clique nos pontos amarelos para ver detalhes. Use zoom + arrastar.</p>
      </div>
      <div class="tour-layout">
        <div class="tour-visual">
          <div class="tour-viewport" id="tour-viewport">
            <div class="tour-media" id="tour-media">
              <img id="tour-image" src="<?= escapar($placeholderImagem) ?>" alt="Ambiente do tour" />
              <div class="tour-hotspots" id="tour-hotspots"></div>
            </div>
          </div>
          <div class="tour-controls">
            <div class="zoom-controls">
              <button id="zoom-in" title="Aproximar"><i class="fas fa-search-plus"></i> Zoom +</button>
              <button id="zoom-out" title="Afastar"><i class="fas fa-search-minus"></i> Zoom -</button>
              <button id="zoom-reset" title="Redefinir"><i class="fas fa-sync-alt"></i> Reset</button>
            </div>
            <div class="nav-controls">
              <button id="tour-prev"><i class="fas fa-arrow-left"></i> Anterior</button>
              <button id="tour-next">Próximo <i class="fas fa-arrow-right"></i></button>
            </div>
          </div>
          <div class="tour-caption">
            <h3 id="tour-title">Entrada principal</h3>
            <p id="tour-desc">Recepção organizada e acesso controlado.</p>
            <div class="tour-audio">
              <span><i class="fas fa-headphones"></i> Áudio guiado</span>
              <audio id="tour-audio" controls preload="none">
                <source id="tour-audio-source" src="<?= escapar($audioPlaceholder) ?>" type="audio/mpeg" />
              </audio>
            </div>
          </div>
          <div class="tour-detail" id="tour-detail" hidden>
            <div class="tour-detail-header">
              <strong id="tour-detail-title">Detalhe</strong>
              <button id="tour-detail-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="tour-detail-body">
              <img id="tour-detail-image" src="<?= escapar($placeholderImagem) ?>" alt="Detalhe" />
              <p id="tour-detail-text">Clique em um ponto amarelo para ver informações.</p>
            </div>
          </div>
        </div>
        <aside class="tour-sidebar">
          <h3><i class="fas fa-map-marker-alt"></i> Áreas do tour</h3>
          <div class="tour-areas" id="tour-areas">
            <button class="tour-area ativo" data-area="entrada" data-title="Entrada principal" data-desc="Recepção moderna, acesso seguro e orientação imediata." data-image="<?= escapar($placeholderImagem) ?>" data-audio="<?= escapar($audioPlaceholder) ?>"><img src="<?= escapar($placeholderImagem) ?>" alt="Entrada" /><span>Entrada</span></button>
            <button class="tour-area" data-area="salas" data-title="Salas de aula" data-desc="Salas climatizadas com projetores e carteiras ergonômicas." data-image="<?= escapar($placeholderImagem) ?>" data-audio="<?= escapar($audioPlaceholder) ?>"><img src="<?= escapar($placeholderImagem) ?>" alt="Salas" /><span>Salas de aula</span></button>
            <button class="tour-area" data-area="laboratorio" data-title="Laboratório" data-desc="Equipamentos de informática e ciências." data-image="<?= escapar($placeholderImagem) ?>" data-audio="<?= escapar($audioPlaceholder) ?>"><img src="<?= escapar($placeholderImagem) ?>" alt="Lab" /><span>Laboratório</span></button>
            <button class="tour-area" data-area="biblioteca" data-title="Biblioteca" data-desc="Acervo diverso e área de estudo silenciosa." data-image="<?= escapar($placeholderImagem) ?>" data-audio="<?= escapar($audioPlaceholder) ?>"><img src="<?= escapar($placeholderImagem) ?>" alt="Biblioteca" /><span>Biblioteca</span></button>
            <button class="tour-area" data-area="administrativa" data-title="Área administrativa" data-desc="Atendimento rápido e eficiente." data-image="<?= escapar($placeholderImagem) ?>" data-audio="<?= escapar($audioPlaceholder) ?>"><img src="<?= escapar($placeholderImagem) ?>" alt="Admin" /><span>Administrativo</span></button>
            <button class="tour-area" data-area="recreacao" data-title="Pátio e recreação" data-desc="Espaço de convívio e lazer." data-image="<?= escapar($placeholderImagem) ?>" data-audio="<?= escapar($audioPlaceholder) ?>"><img src="<?= escapar($placeholderImagem) ?>" alt="Pátio" /><span>Pátio</span></button>
            <button class="tour-area" data-area="desportiva" data-title="Área desportiva" data-desc="Quadras e equipamentos esportivos." data-image="<?= escapar($placeholderImagem) ?>" data-audio="<?= escapar($audioPlaceholder) ?>"><img src="<?= escapar($placeholderImagem) ?>" alt="Desporto" /><span>Desportiva</span></button>
          </div>
        </aside>
      </div>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-col">
        <div class="footer-logo">
          <img src="<?= $icone ?>/logo.png" alt="Logo" class="logo-footer" />
          <h2 class="titulo-footer">Instituto Politécnico Privado Mundo Novo II</h2>
        </div>
        <p>Excelência em ensino, infraestrutura moderna e formação cidadã.</p>
        <div class="redes-sociais">
          <a href="#" class="rede-social"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="rede-social"><i class="fab fa-instagram"></i></a>
          <a href="#" class="rede-social"><i class="fab fa-whatsapp"></i></a>
        </div>
      </div>
      <div class="footer-col">
        <h3 class="titulo-coluna">Links rápidos</h3>
        <ul class="footer-links">
          <li><a href="<?= base_url('') ?>#cursos">Cursos</a></li>
          <li><a href="<?= base_url('matricula') ?>">Matrícula online</a></li>
          <li><a href="<?= base_url('tour-virtual') ?>">Tour virtual</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h3 class="titulo-coluna">Contacto</h3>
        <ul class="footer-contatos">
          <li><i class="fas fa-map-marker-alt"></i> Talatona, Rua Direita da Camama</li>
          <li><i class="fas fa-phone-alt"></i> +244 921 660 962</li>
          <li><i class="fas fa-envelope"></i> fernandomateusdomingos08@gmail.com</li>
        </ul>
      </div>
      <div class="footer-col">
        <h3 class="titulo-coluna">Horário</h3>
        <p>Segunda a Sexta: 7h - 18h<br>Sábado: 8h - 13h</p>
      </div>
    </div>
    <div class="footer-inferior">
      <p>&copy; 2025 Instituto Politécnico Privado Mundo Novo II</p>
      <div><a href="#" style="color:#94a3b8;">Política de privacidade</a></div>
    </div>
  </div>
</footer>

<script>
  // Tour Virtual Interativo com zoom/pan/hotspots (mesma funcionalidade melhorada)
  document.addEventListener('DOMContentLoaded', () => {
    const viewport = document.getElementById('tour-viewport');
    const media = document.getElementById('tour-media');
    const image = document.getElementById('tour-image');
    const hotspotsEl = document.getElementById('tour-hotspots');
    const titleEl = document.getElementById('tour-title');
    const descEl = document.getElementById('tour-desc');
    const audioEl = document.getElementById('tour-audio');
    const audioSource = document.getElementById('tour-audio-source');
    const zoomIn = document.getElementById('zoom-in');
    const zoomOut = document.getElementById('zoom-out');
    const zoomReset = document.getElementById('zoom-reset');
    const prevBtn = document.getElementById('tour-prev');
    const nextBtn = document.getElementById('tour-next');
    const detailPanel = document.getElementById('tour-detail');
    const detailTitle = document.getElementById('tour-detail-title');
    const detailText = document.getElementById('tour-detail-text');
    const detailImage = document.getElementById('tour-detail-image');
    const detailClose = document.getElementById('tour-detail-close');
    const areaBtns = Array.from(document.querySelectorAll('#tour-areas .tour-area'));

    if (!viewport || !media) return;

    let currentIdx = 0;
    let scale = 1, panX = 0, panY = 0;
    let dragging = false, dragStart = { x: 0, y: 0 };
    let userInteracted = false;

    const hotspotsData = {
      entrada: [{ id:1, x:54, y:58, title:'Recepção', desc:'Balcão de atendimento e segurança.', image:'<?= $placeholderImagem ?>' }],
      salas: [{ id:2, x:48, y:42, title:'Quadro interativo', desc:'Tecnologia para aulas dinâmicas.', image:'<?= $placeholderImagem ?>' }],
      laboratorio: [{ id:3, x:62, y:46, title:'Computadores', desc:'Laboratório totalmente equipado.', image:'<?= $placeholderImagem ?>' }],
      biblioteca: [{ id:4, x:34, y:58, title:'Acervo', desc:'Livros atualizados.', image:'<?= $placeholderImagem ?>' }],
      administrativa: [{ id:5, x:60, y:58, title:'Atendimento', desc:'Secretaria eficiente.', image:'<?= $placeholderImagem ?>' }],
      recreacao: [{ id:6, x:46, y:66, title:'Área de lazer', desc:'Espaço com bancos e jardim.', image:'<?= $placeholderImagem ?>' }],
      desportiva: [{ id:7, x:36, y:48, title:'Quadra', desc:'Poliesportiva coberta.', image:'<?= $placeholderImagem ?>' }]
    };

    function applyTransform() {
      media.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
    }

    function clampPan() {
      if(scale <= 1) { panX = 0; panY = 0; return; }
      const rect = viewport.getBoundingClientRect();
      const maxX = (rect.width * (scale - 1)) / 2;
      const maxY = (rect.height * (scale - 1)) / 2;
      panX = Math.max(-maxX, Math.min(maxX, panX));
      panY = Math.max(-maxY, Math.min(maxY, panY));
    }

    function setScale(newScale, originEvent) {
      let s = Math.min(3, Math.max(1, newScale));
      if(s === scale) return;
      if(originEvent) {
        const rect = viewport.getBoundingClientRect();
        const x = ((originEvent.clientX - rect.left) / rect.width) * 100;
        const y = ((originEvent.clientY - rect.top) / rect.height) * 100;
        media.style.transformOrigin = `${x}% ${y}%`;
      } else {
        media.style.transformOrigin = 'center';
      }
      scale = s;
      clampPan();
      applyTransform();
    }

    function resetZoom() { scale = 1; panX = 0; panY = 0; media.style.transformOrigin = 'center'; applyTransform(); }

    function renderHotspots(areaId) {
      hotspotsEl.innerHTML = '';
      const list = hotspotsData[areaId] || [];
      list.forEach(h => {
        const btn = document.createElement('button');
        btn.className = 'tour-hotspot';
        btn.style.left = `${h.x}%`;
        btn.style.top = `${h.y}%`;
        btn.title = h.title;
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          userInteracted = true;
          detailTitle.innerText = h.title;
          detailText.innerText = h.desc;
          detailImage.src = h.image;
          detailPanel.hidden = false;
        });
        hotspotsEl.appendChild(btn);
      });
      if(!list.length) detailPanel.hidden = true;
    }

    function updateAudio(src) {
      if(audioSource) audioSource.src = src || '';
      audioEl?.load();
      if(userInteracted) audioEl?.play().catch(()=>{});
    }

    function applyArea(index) {
      const btn = areaBtns[index];
      if(!btn) return;
      currentIdx = index;
      areaBtns.forEach((b,i) => b.classList.toggle('ativo', i===index));
      const areaId = btn.dataset.area;
      const newTitle = btn.dataset.title;
      const newDesc = btn.dataset.desc;
      const newImg = btn.dataset.image;
      const newAudio = btn.dataset.audio;
      image.src = newImg;
      titleEl.innerText = newTitle;
      descEl.innerText = newDesc;
      renderHotspots(areaId);
      updateAudio(newAudio);
      detailPanel.hidden = true;
      resetZoom();
    }

    areaBtns.forEach((btn,i) => btn.addEventListener('click', () => { userInteracted=true; applyArea(i); }));
    if(nextBtn) nextBtn.addEventListener('click', () => { userInteracted=true; applyArea((currentIdx+1)%areaBtns.length); });
    if(prevBtn) prevBtn.addEventListener('click', () => { userInteracted=true; applyArea((currentIdx-1+areaBtns.length)%areaBtns.length); });
    zoomIn?.addEventListener('click', () => setScale(scale+0.2, null));
    zoomOut?.addEventListener('click', () => setScale(scale-0.2, null));
    zoomReset?.addEventListener('click', resetZoom);
    detailClose?.addEventListener('click', () => detailPanel.hidden = true);

    viewport.addEventListener('wheel', (e) => { e.preventDefault(); setScale(scale - e.deltaY*0.005, e); }, { passive: false });
    viewport.addEventListener('pointerdown', (e) => {
      if(scale<=1 || e.target.closest('.tour-hotspot')) return;
      dragging = true;
      dragStart.x = e.clientX - panX;
      dragStart.y = e.clientY - panY;
      viewport.setPointerCapture(e.pointerId);
    });
    viewport.addEventListener('pointermove', (e) => {
      if(!dragging) return;
      panX = e.clientX - dragStart.x;
      panY = e.clientY - dragStart.y;
      clampPan();
      applyTransform();
    });
    viewport.addEventListener('pointerup', () => { dragging = false; });
    applyArea(0);
  });
</script>
</body>
</html>