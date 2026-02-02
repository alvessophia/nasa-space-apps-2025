<?php
// Duas datas para comparar (formato YYYY-MM-DD)
$dataA = "2020-04-06"; // época 1
$dataB = "2023-04-06"; // época 2

// BBOX do seu link (abre já na mesma área)
$west  = -117.22918431566835;
$south =   45.932505344479885;
$east  = -116.92460548663762;
$north =   46.244956970383775;

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
  <!-- (REMOVIDO o script do Lottie) -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Globotany - NASA Space Apps 2025</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    html,body{height:100%;margin:0}
    body{background:#0b0b0b;color:#eaeaea;font:14px/1.4 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}

    /* Overlay de carregamento */
    #loadingOverlay{
      position:fixed; inset:0; display:none;
      place-items:center; z-index:3000;
      background:rgba(0,0,0,.65);
    }
    .loading-card{
      background:#0f1317; border:1px solid #232a30; border-radius:14px;
      padding:18px 18px 8px; text-align:center; color:#cfd7df;
      box-shadow:0 12px 36px rgba(0,0,0,.45);
    }
    .loading-card .hint{font:12px/1.2 system-ui; opacity:.8; margin-top:6px}

    /* Mapa ocupa toda a tela (o botão é overlay) */
    #map{
      position:fixed; /* cobre toda a viewport */
      inset:0;
    }

    /* Overlay do slider (permanece sobre o mapa) */
    .swipe-wrap{
      position:fixed;top:10px;left:50%;transform:translateX(-50%);
      z-index:2000;background:rgba(0,0,0,.5);padding:8px 10px;
      border-radius:10px;color:#fff;font:14px/1.2 sans-serif
    }
    .swipe-wrap input[type=range]{width:320px}

    /* ÚNICO botão, centralizado embaixo, como overlay */
    .fab{
      position:fixed;left:50%;bottom:16px;transform:translateX(-50%);
      z-index:2000;
    }
    .btn{
      appearance:none;border:0;cursor:pointer;
      padding:12px 18px;border-radius:12px;
      background:#1f6feb;color:#fff;font-weight:700;
      box-shadow:0 6px 20px rgba(0,0,0,.35);
    }
    /* correção do “salto” ao clicar */
    .fab .btn:active{ transform:scale(.98); transform-origin:center; }
  </style>

  <!-- ===== ADIÇÃO: estilos da galeria/rodapé ===== -->
  <style>
    .results{display:none;position:fixed;inset:0;overflow:auto;background:#0b0b0b;z-index:2200}
    .results-inner{max-width:1200px;margin:0 auto;padding:16px 16px 84px}
    .grid{display:grid;gap:16px;grid-template-columns:1fr 1fr;align-items:start}
    .shot{background:#0f1317;border:1px solid #232a30;border-radius:12px;overflow:hidden}
    .shot img{display:block;width:100%;height:auto}
    /* MOBILE: reduzir altura das imagens em ~50% da tela */
    @media screen and (orientation:portrait) and (max-width:768px){
      .shot{ display:flex; flex-direction:column }
      .shot img{
        width:100%;
        height:auto;
        max-height:30vh;
        max-height:30svh;
        max-height:30dvh;
        object-fit:contain;
      }
    }
    .caption{
      padding:8px 12px;background:#0f1317;color:#cfd7df;border-top:1px solid #232a30;
      text-align:center;font-weight:600
    }
    @media (orientation:portrait) and (max-width:768px){
      .grid{grid-template-columns:1fr}
    }
    .footer{
      position:fixed;left:0;right:0;bottom:0;background:#101214;border-top:1px solid #1e2226;
      display:flex;justify-content:center;gap:12px;padding:12px;z-index:2300
    }
    .btn.secondary{background:#30363d}
    .btn[disabled]{opacity:.7;cursor:progress}
  </style>
  <style>
    .modal{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;
      background:rgba(0,0,0,.6);z-index:2400}
    .modal[hidden]{display:none}
    .modal-inner{background:#0f1317;border:1px solid #232a30;border-radius:12px;
      max-width:680px;width:92%;padding:16px}
    .modal-inner pre{margin:0;max-height:50vh;overflow:auto;color:#cfd7df}
    .modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
  </style>
  <style>
    /* ---- PRINT ---- */
    #printView{display:none}
    @media print{
      @page{size:A4;margin:12mm}
      body{-webkit-print-color-adjust:exact;print-color-adjust:exact;background:#fff;color:#000}
      #map,.swipe-wrap,.fab,.results{display:none!important}
      #printView{display:block;padding:8mm 6mm 0}
      .pv-title{font:700 16px/1.2 system-ui;margin:0 0 4px}
      .pv-meta{font:11px/1.25 system-ui;margin:0 0 6px}
      .pv-grid{display:grid;grid-template-columns:1fr;gap:6px;margin:6px 0}
      .pv-grid figure{margin:0}
      .pv-grid img{width:100%;height:auto;border:1px solid #bbb}
      .pv-grid figcaption{font:11px/1.2 system-ui;text-align:center;margin-top:2px}
      .pv-report{
        border:1px solid #bbb;
        padding:8px;
        font:12px/1.3 system-ui;
        white-space:normal;
      }
      .pv-report h1,.pv-report h2,.pv-report h3,.pv-report h4,.pv-report h5,.pv-report h6{
        margin:6px 0 4px; line-height:1.2; font-weight:700;
      }
      .pv-report p{ margin:4px 0 }
      .pv-report ul,.pv-report ol{ margin:4px 0 4px 16px; padding:0 }
      .pv-report li{ margin:2px 0 }
      .pv-report br+br{ display:none }
      .pv-grid figure, .pv-report{ break-inside:avoid }
    }
  </style>

  <!-- ADIÇÃO: estilos do seletor de datas -->
  <style>
    .date-btn{
      background:transparent;border:0;color:#fff;font-weight:800;cursor:pointer;
      padding:0 4px;border-bottom:1px dashed rgba(255,255,255,.4)
    }
    .date-picker-pop{
      position:fixed;z-index:2500;background:#0f1317;border:1px solid #232a30;border-radius:10px;
      padding:8px;box-shadow:0 10px 30px rgba(0,0,0,.4)
    }
    .date-picker-pop[hidden]{display:none}
    .date-picker-pop input[type=date]{background:#0b0b0b;color:#eaeaea;border:1px solid #2a3036;border-radius:8px;padding:8px}
    /* centraliza o texto e o slider na caixa do swipe */
    .swipe-wrap{ text-align:center; }
    .swipe-wrap input[type=range]{ display:block; margin:4px auto 0; }
  </style>
</head>
<body>

  <!-- Janela do mapa (100% da tela) -->
  <div id="map"></div>

  <!-- Overlay do swipe -->
  <div class="swipe-wrap">
    <span>← <?= $dataA ?> | <?= $dataB ?> →</span><br>
    <input id="swipe" type="range" min="0" max="100" value="50" step="1">
  </div>

  <!-- Botão único, centralizado no rodapé (overlay) -->
  <div class="fab">
    <button class="btn" id="analisarBtn" type="button">Capture</button>
  </div>

  <!-- ===== ADIÇÃO: bloco da galeria de capturas ===== -->
  <div class="results" id="results" aria-live="polite">
    <div class="results-inner">
      <div class="grid">
        <figure class="shot">
          <img id="imgA" alt="Época A">
          <figcaption class="caption"><?= $dataA ?></figcaption>
          <div id="analysisReport" style="display:none;padding:12px 14px;border-top:1px solid #232a30;text-align:left"></div>
        </figure>
        <figure class="shot">
          <img id="imgB" alt="Época B">
          <figcaption class="caption"><?= $dataB ?></figcaption>
        </figure>
      </div>
    </div>
    <div class="footer">
      <button class="btn" id="analisar2" type="button">Analyze</button>
      <button class="btn secondary" id="imprimir2" type="button" style="display:none">Print</button>
      <button class="btn secondary" id="voltar2" type="button">Back</button>
    </div>
  </div>
  <!-- ===== FIM ADIÇÃO ===== -->

  <!-- Overlay de carregamento (AGORA SOMENTE SVG) -->
  <div id="loadingOverlay" aria-live="polite" style="position:fixed;inset:0;display:none;place-items:center;z-index:3000;background:rgba(0,0,0,.65)">
    <div class="loading-card" style="background:#0f1317;border:1px solid #232a30;border-radius:14px;padding:18px 18px 10px;text-align:center;color:#cfd7df;box-shadow:0 12px 36px rgba(0,0,0,.45)">

      <!-- SVG animado (astronauta minimalista) -->
      <div id="loadingSvg" style="width:160px;height:160px;margin:auto;display:block">
        <svg viewBox="0 0 160 160" width="160" height="160" role="img" aria-label="Astronauta flutuando">
          <defs>
            <!-- brilho atrás do astronauta -->
            <radialGradient id="glow" cx="50%" cy="45%" r="60%">
              <stop offset="0%" stop-color="#8fb6ff" stop-opacity="0.55"/>
              <stop offset="100%" stop-color="#1f6feb" stop-opacity="0.0"/>
            </radialGradient>

            <!-- gradiente do visor -->
            <linearGradient id="visorGrad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#9ad0ff" stop-opacity="0.9"/>
              <stop offset="100%" stop-color="#0b0b0b" stop-opacity="1"/>
            </linearGradient>

            <!-- brilho que “varre” o visor -->
            <linearGradient id="shineGrad" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stop-color="#ffffff" stop-opacity="0"/>
              <stop offset="50%" stop-color="#ffffff" stop-opacity="0.45"/>
              <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
            </linearGradient>

            <!-- sombra suave -->
            <filter id="soft">
              <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#000" flood-opacity=".35"/>
            </filter>
          </defs>

          <!-- leve brilho de fundo -->
          <circle cx="80" cy="70" r="56" fill="url(#glow)"/>

          <!-- algumas estrelas “twinkle” -->
          <g fill="#cfe0ff" opacity=".9">
            <circle class="tw" cx="20" cy="26" r="1.5"/>
            <circle class="tw" cx="46" cy="14" r="1.2"/>
            <circle class="tw" cx="136" cy="30" r="1.4"/>
            <circle class="tw" cx="120" cy="12" r="1"/>
            <circle class="tw" cx="18" cy="70" r="1.1"/>
            <circle class="tw" cx="142" cy="72" r="1.3"/>
          </g>

          <!-- planetinha ao fundo -->
          <g opacity=".25" stroke="#5d7fbf" fill="none">
            <circle cx="134" cy="134" r="38"/>
            <path d="M98,128 Q134,110 170,122" stroke-width="2"/>
            <path d="M95,138 Q134,156 173,146" stroke-width="2"/>
          </g>

          <!-- cabo -->
          <path d="M-10,110 C 20,96 38,92 60,86"
                stroke="#7aa7ff" stroke-opacity=".6" stroke-width="2"
                fill="none" stroke-linecap="round" />

          <!-- ASTRONAUTA -->
          <g id="astro" filter="url(#soft)" transform="translate(80,70)">
            <!-- mochila -->
            <rect x="-30" y="-10" rx="8" ry="8" width="22" height="34"
                  fill="#b7c4dd" stroke="#2a3036" stroke-width="2"/>

            <!-- tronco -->
            <rect x="-18" y="4" rx="10" ry="10" width="36" height="36"
                  fill="#e6eefc" stroke="#2a3036" stroke-width="2"/>

            <!-- painel no tronco -->
            <rect x="-8" y="14" rx="4" ry="4" width="16" height="10"
                  fill="#0f1317" stroke="#2a3036" stroke-width="2"/>
            <circle cx="-3" cy="19" r="1.6" fill="#ff6b6b"/>
            <circle cx="0" cy="19" r="1.6" fill="#ffd166"/>
            <circle cx="3" cy="19" r="1.6" fill="#06d6a0"/>

            <!-- cabeça/capacete -->
            <circle cx="0" cy="-14" r="18" fill="#e6eefc" stroke="#2a3036" stroke-width="2"/>
            <ellipse cx="0" cy="-14" rx="14" ry="12" fill="url(#visorGrad)" stroke="#1f2a36" stroke-width="2"/>

            <!-- brilho varrendo o visor -->
            <rect x="-14" y="-26" width="28" height="24" fill="url(#shineGrad)" opacity=".6">
              <animate attributeName="x" values="-28;14;-28" dur="2.8s" repeatCount="indefinite"/>
            </rect>

            <!-- braços -->
            <g stroke-linecap="round" stroke="#2a3036" stroke-width="4" fill="none">
              <path d="M-18,10 q-10,8 -20,0" />
              <path d="M18,10 q10,8 20,0" />
            </g>
            <!-- luvas -->
            <circle cx="-39" cy="10" r="4.5" fill="#b7c4dd" stroke="#2a3036" stroke-width="2"/>
            <circle cx="39"  cy="10" r="4.5" fill="#b7c4dd" stroke="#2a3036" stroke-width="2"/>

            <!-- pernas -->
            <g stroke-linecap="round" stroke="#2a3036" stroke-width="4" fill="none">
              <path d="M-10,40 q-4,12 -2,20"/>
              <path d="M10,40  q4,12  2,20"/>
            </g>
            <!-- botas -->
            <rect x="-17" y="59" width="12" height="7" rx="3" fill="#b7c4dd" stroke="#2a3036" stroke-width="2"/>
            <rect x="5"  y="59" width="12" height="7" rx="3" fill="#b7c4dd" stroke="#2a3036" stroke-width="2"/>

            <!-- cabo preso na mochila -->
            <path d="M-32,2 C -56,0 -68,12 -90,28"
                  stroke="#7aa7ff" stroke-opacity=".75" stroke-width="2"
                  fill="none" stroke-linecap="round"/>
          </g>

          <style>
            /* flutuação e leve inclinação */
            #astro{
              transform-origin: 80px 70px;
              animation: float 3.8s ease-in-out infinite alternate,
                        tilt  7s   ease-in-out infinite alternate;
            }
            @keyframes float{
              0%   { transform: translate(80px,70px) translateY(-3px); }
              100% { transform: translate(80px,70px) translateY(3px);  }
            }
            @keyframes tilt{
              0%   { transform: translate(80px,70px) rotate(-2deg); }
              100% { transform: translate(80px,70px) rotate(2deg);  }
            }

            /* estrelas piscando em tempos diferentes */
            .tw { animation: twinkle 1.8s ease-in-out infinite; }
            .tw:nth-child(2){ animation-duration: 2.2s; }
            .tw:nth-child(3){ animation-duration: 2.6s; }
            .tw:nth-child(4){ animation-duration: 2.0s; }
            .tw:nth-child(5){ animation-duration: 2.8s; }
            .tw:nth-child(6){ animation-duration: 2.4s; }
            @keyframes twinkle{
              0%,100% { opacity: .2; }
              50%     { opacity: 1;  }
            }
          </style>
        </svg>
      </div>


      <div style="font-weight:700;margin-top:6px">Analyzing imagery…</div>
      <div class="hint" style="font:12px/1.2 system-ui;opacity:.8;margin-top:6px">this could take one or two minutes...</div>
    </div>
  </div>

  <script>
    /* Funções do overlay — agora só mostram/ocultam o SVG */
    function showLoadingOverlay() {
      const overlay = document.getElementById("loadingOverlay");
      if (overlay) overlay.style.display = "grid";
    }
    function hideLoadingOverlay() {
      const overlay = document.getElementById("loadingOverlay");
      if (overlay) overlay.style.display = "none";
    }
  </script>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // Enquadra na área do seu link
    const bounds = L.latLngBounds([<?= $south ?>, <?= $west ?>], [<?= $north ?>, <?= $east ?>]);
    //const map = L.map("map", { zoomControl: true }).fitBounds(bounds);
    const map = L.map("map", { zoomControl: true }).setView([29.042, 73.805], 6);

    // === cache do último centro válido ===
    let lastCenter = map.getCenter();

    function isFiniteLatLng(c){
      return c && isFinite(c.lat) && isFinite(c.lng) &&
            c.lat >= -90 && c.lat <= 90 && c.lng >= -180 && c.lng <= 180;
    }
    function clampCenter(c){
      if(!c) return null;
      let {lat,lng} = c;
      if(lat < -90) lat = -90; if(lat > 90) lat = 90;
      if(lng < -180) lng = -180; if(lng > 180) lng = 180;
      return {lat, lng};
    }

    // atualiza o cache quando o usuário move/da zoom
    map.on("moveend zoomend", () => {
      const c = map.getCenter();
      if(isFiniteLatLng(c)) lastCenter = c;
    });

    // também após o primeiro render
    map.whenReady(() => {
      const c = map.getCenter();
      if(isFiniteLatLng(c)) lastCenter = c;
    });

    //map.setView([29.042, 73.805], 6);
    // Endpoint WMS (EPSG:3857)
    const WMS = "https://gibs.earthdata.nasa.gov/wms/epsg3857/best/wms.cgi?";

    // Camada "base" (época A)
    const leftLayer = L.tileLayer.wms(WMS, {
      layers: "VIIRS_NOAA20_CorrectedReflectance_TrueColor",
      format: "image/jpeg",
      transparent: false,
      time: "<?= $dataA ?>",
      tileSize: 512,
      attribution: "NASA GIBS"
    }).addTo(map);

    // Camada "superior" (época B)
    const rightLayer = L.tileLayer.wms(WMS, {
      layers: "VIIRS_NOAA20_CorrectedReflectance_TrueColor",
      format: "image/jpeg",
      transparent: false,
      time: "<?= $dataB ?>",
      tileSize: 512
    }).addTo(map);

    // Overlays (rótulos+costas)
    const ref = L.tileLayer.wms(WMS, {
      layers: "Coastlines_15m,Reference_Labels_15m",
      format: "image/png",
      transparent: true,
      tileSize: 512
    }).addTo(map);

    // --- Swipe sem plugin ---
    const slider = document.getElementById("swipe");
    function applyClip() {
      const pct = Number(slider.value) / 100;
      const size = map.getSize();
      const clipX = Math.round(size.x * pct);
      const c = rightLayer._container;
      if (!c) return;
      const p0 = map.containerPointToLayerPoint([0, 0]);
      const p1 = map.containerPointToLayerPoint([size.x, size.y]);
      const left = Math.round(map.containerPointToLayerPoint([clipX, 0]).x);
      c.style.clip = `rect(${p0.y}px, ${p1.x}px, ${p1.y}px, ${left}px)`;
    }
    slider.addEventListener("input", applyClip);
    map.on("resize move zoom", applyClip);
    rightLayer.on("load", applyClip);
    map.on("drag dragend", applyClip);
    let _swipeRAF = null;
    map.on("dragstart", () => {
      const tick = () => { applyClip(); _swipeRAF = requestAnimationFrame(tick); };
      _swipeRAF = requestAnimationFrame(tick);
    });
    map.on("dragend", () => {
      if (_swipeRAF) { cancelAnimationFrame(_swipeRAF); _swipeRAF = null; }
      applyClip();
    });
    (() => {
      const tilePane = map.getPanes().tilePane;
      let raf = null;
      const observer = new MutationObserver(() => {
        if (raf) return;
        raf = requestAnimationFrame(() => { raf = null; applyClip(); });
      });
      observer.observe(tilePane, { attributes: true, attributeFilter: ["style"] });
    })();
    let _panRAF = null;
    map.on("movestart", () => {
      const tick = () => { applyClip(); _panRAF = requestAnimationFrame(tick); };
      if (!_panRAF) _panRAF = requestAnimationFrame(tick);
    });
    map.on("moveend", () => {
      if (_panRAF) { cancelAnimationFrame(_panRAF); _panRAF = null; }
      applyClip();
    });
    setTimeout(()=>{ map.invalidateSize(); applyClip(); }, 100);

    document.getElementById("analisarBtn").addEventListener("click", () => {
      alert("Captura acionada.");
    });
  </script>

  <!-- ===== ADIÇÃO: script de captura + galeria ===== -->
  <script>
    (function(){
      const DATA_A = "<?= $dataA ?>";
      const DATA_B = "<?= $dataB ?>";
      const WMS_BASE = "https://gibs.earthdata.nasa.gov/wms/epsg3857/best/wms.cgi";
      const LAYER_TRUECOLOR = "VIIRS_NOAA20_CorrectedReflectance_TrueColor";
      const LAYER_OVER = "Coastlines_15m,Reference_Labels_15m";

      const resultsEl = document.getElementById("results");
      const imgA = document.getElementById("imgA");
      const imgB = document.getElementById("imgB");

      let analysisDone = false;
      let lastUrlA = null, lastUrlB = null;

      const oldBtn = document.getElementById("analisarBtn");
      const newBtn = oldBtn.cloneNode(true);
      oldBtn.parentNode.replaceChild(newBtn, oldBtn);

      function buildGetMapURL(dateStr){
        const size = map.getSize();
        const b = map.getBounds();
        const sw = L.CRS.EPSG3857.project(b.getSouthWest());
        const ne = L.CRS.EPSG3857.project(b.getNorthEast());
        const bbox = [sw.x, sw.y, ne.x, ne.y].map(v => v.toFixed(2)).join(",");
        const params = new URLSearchParams({
          service: "WMS",
          request: "GetMap",
          version: "1.3.0",
          format: "image/jpeg",
          transparent: "false",
          layers: `${LAYER_TRUECOLOR},${LAYER_OVER}`,
          styles: "",
          crs: "EPSG:3857",
          bbox,
          width: String(size.x),
          height: String(size.y),
          time: dateStr
        });
        return `${WMS_BASE}?${params.toString()}`;
      }

      // Datas atuais (prioriza layer; depois botões; senão, as do PHP)
      function getCurrentDates(){
        const a = (leftLayer?.wmsParams?.time)
               || document.getElementById("btnDateA")?.textContent?.trim()
               || "<?= $dataA ?>";
        const b = (rightLayer?.wmsParams?.time)
               || document.getElementById("btnDateB")?.textContent?.trim()
               || "<?= $dataB ?>";
        return { A:a, B:b };
      }

      function loadInto(imgEl, url){
        return new Promise((resolve, reject) => {
          imgEl.onload = () => resolve();
          imgEl.onerror = () => reject(new Error("Falha ao carregar imagem WMS."));
          imgEl.src = url;
        });
      }

      newBtn.addEventListener("click", async () => {
        // garante centro válido antes de esconder o mapa
        const cNow = map.getCenter();
        if(isFiniteLatLng(cNow)) lastCenter = cNow;

        analysisDone = false;
        const analyseBtn = document.getElementById("analisar2");
        analyseBtn.style.display = "";
        analyseBtn.disabled = false;
        analyseBtn.textContent = "Analyze";

        newBtn.disabled = true;
        const oldTxt = newBtn.textContent;
        newBtn.textContent = "Processing...";

        try{
          // usa as datas correntes escolhidas
          const { A: curA, B: curB } = getCurrentDates();

          const urlA = buildGetMapURL(curA);
          const urlB = buildGetMapURL(curB);
          lastUrlA = urlA;
          lastUrlB = urlB;

          await Promise.all([ loadInto(imgA, urlA), loadInto(imgB, urlB) ]);

          // reforça as legendas na galeria (caso o usuário tenha mudado antes)
          const capA = document.querySelector("figure .caption");
          if (capA) capA.textContent = curA;
          const caps = document.querySelectorAll("figure .caption");
          if (caps && caps[1]) caps[1].textContent = curB;

          document.getElementById("map").style.display = "none";
          document.querySelector(".swipe-wrap").style.display = "none";
          document.querySelector(".fab").style.display = "none";
          resultsEl.style.display = "block";
        }catch(e){
          console.error(e);
          alert("Não foi possível gerar as imagens agora. Tente novamente.");
        }finally{
          newBtn.textContent = oldTxt;
          newBtn.disabled = false;
        }
      });

      document.getElementById("voltar2").addEventListener("click", () => {
        const reportEl = document.getElementById("analysisReport");
        reportEl.innerHTML = "";
        reportEl.style.display = "none";
        resultsEl.style.display = "none";
        document.getElementById("map").style.display = "block";
        document.querySelector(".swipe-wrap").style.display = "block";
        document.querySelector(".fab").style.display = "block";
        setTimeout(()=>{ map.invalidateSize(); }, 30);
        requestAnimationFrame(()=>applyClip());
      });

      document.getElementById("analisar2").addEventListener("click", async () => {
        const btn = document.getElementById("analisar2");
        btn.disabled = true;
        const oldTxt = btn.textContent;
        btn.textContent = "Processing...";
        showLoadingOverlay();

        // MOSTRA overlay (mantido como no seu código)
        const overlay = document.getElementById("loadingOverlay");
        if (overlay) overlay.style.display = "grid";

        const trunc = (s, n = 4000) => (s.length > n ? s.slice(0, n) + "\n…[truncated]" : s);

        try {
          // centro válido (cache) para quando o mapa está oculto
          let c = isFiniteLatLng(lastCenter) ? lastCenter : map.getCenter();
          c = isFiniteLatLng(c) ? c : { lat: 0, lng: 0 };
          c = clampCenter(c);

          // datas correntes
          const { A: curA, B: curB } = getCurrentDates();

          const payload = {
            dateA: curA,
            dateB: curB,
            urlA: lastUrlA || buildGetMapURL(curA),
            urlB: lastUrlB || buildGetMapURL(curB),
            center: { lat: Number(c.lat.toFixed(6)), lng: Number(c.lng.toFixed(6)) }
          };

          const resp = await fetch("https://globotany.cwiz.com.br/analyse.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            mode: "cors",
            body: JSON.stringify(payload)
          });

          const rawText = await resp.text();
          let parsed; try { parsed = JSON.parse(rawText); } catch {}

          if (!resp.ok) {
            alert(`HTTP ERROR ${resp.status} ${resp.statusText}\nURL: https://globotany.cwiz.com.br/analyse.php\n\nBody:\n${trunc(parsed ? JSON.stringify(parsed, null, 2) : rawText || "<empty body>")}`);
            throw new Error(`HTTP ${resp.status}`);
          }
          if (!parsed || parsed.ok !== true) {
            alert(`API RESPONSE (ok != true)\n\n${trunc(parsed ? JSON.stringify(parsed, null, 2) : rawText || "<empty body>")}`);
            throw new Error(parsed?.error || "analysis_failed");
          }

          const reportEl = document.getElementById("analysisReport");
          reportEl.innerHTML = parsed.analysis_text || "<em>Nenhum conteúdo retornado.</em>";
          reportEl.style.display = "block";
          reportEl.scrollIntoView({ behavior: "smooth", block: "start" });

          analysisDone = true;
          btn.style.display = "none";

          const printBtn = document.getElementById("imprimir2");
          printBtn.style.display = "";

          printBtn.onclick = async () => {
            const pv = document.getElementById("printView");
            // usa as datas correntes novamente para garantir coerência
            const { A: curA2, B: curB2 } = getCurrentDates();
            const center = c; // já calculado acima
            const now = new Date();

            const urlA = (lastUrlA || buildGetMapURL(curA2)) + "&_=" + Date.now();
            const urlB = (lastUrlB || buildGetMapURL(curB2)) + "&_=" + Date.now();

            const toDataUrl = async (url) => {
              const res = await fetch(url, { mode: "cors", cache: "no-store" });
              if (!res.ok) throw new Error(`HTTP ${res.status} em ${url}`);
              const blob = await res.blob();
              await new Promise((ok) => setTimeout(ok, 0));
              return await new Promise((resolve, reject) => {
                const fr = new FileReader();
                fr.onload = () => resolve(fr.result);
                fr.onerror = () => reject(new Error("FileReader falhou"));
                fr.readAsDataURL(blob);
              });
            };

            let dataA, dataB;
            try {
              [dataA, dataB] = await Promise.all([toDataUrl(urlA), toDataUrl(urlB)]);
            } catch (e) {
              alert("Falha ao preparar imagens para impressão.\n\n" + (e.message || e));
              return;
            }

            pv.innerHTML = `
              <div class="pv-title">Globotany — NASA Space Apps 2025</div>
              <div class="pv-meta">
                Datas: <strong>${curA2}</strong> vs <strong>${curB2}</strong><br>
                Centro do mapa: ${center.lat.toFixed(6)}, ${center.lng.toFixed(6)}<br>
                Gerado em: ${now.toLocaleString(undefined, { hour12:false })}
              </div>

              <div class="pv-grid">
                <figure>
                  <img alt="Época A ${curA2}">
                  <figcaption>${curA2}</figcaption>
                </figure>
                <figure>
                  <img alt="Época B ${curB2}">
                  <figcaption>${curB2}</figcaption>
                </figure>
              </div>

              <div class="pv-report">${(document.getElementById("analysisReport")?.innerHTML || "<em>Sem conteúdo</em>")}</div>
            `;

            const imgs = pv.querySelectorAll("img");
            imgs[0].src = dataA;
            imgs[1].src = dataB;

            await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
            window.print();
          };
        } catch (e) {
          console.error(e);
          if (!/HTTP \d+/.test(String(e.message)) && !/analysis_failed/.test(String(e.message))) {
            alert(`Falha ao processar a análise.\n\n${e.message || e}`);
          }
        } finally {
          // ESCONDE overlay
          hideLoadingOverlay();
          if (!analysisDone) {
            btn.textContent = oldTxt;
            btn.disabled = false;
          }
        }
      });
    })();
  </script>

  <div class="modal" id="dataModal" hidden>
    <div class="modal-inner">
      <div style="font-weight:700;margin-bottom:8px">Prévia do payload</div>
      <pre id="payloadPreview"></pre>
      <div class="modal-actions">
        <button class="btn secondary" id="fecharModal" type="button">Fechar</button>
      </div>
    </div>
  </div>
  <script>
    document.getElementById("fecharModal").addEventListener("click", () => {
      document.getElementById("dataModal").hidden = true;
    });
  </script>

  <!-- ADIÇÃO: popover do calendário (deve vir ANTES do script do date picker) -->
  <div id="datePop" class="date-picker-pop" hidden>
    <input type="date" id="dateInput">
  </div>

  <!-- ADIÇÃO: script do date picker (torna datas clicáveis + setParams) -->
  <script>
(function(){
  const originalA = "<?= $dataA ?>";
  const originalB = "<?= $dataB ?>";

  let currentA = originalA;
  let currentB = originalB;

  const barSpan = document.querySelector(".swipe-wrap span");
  if(barSpan){
    barSpan.innerHTML = `&larr; <button class="date-btn" id="btnDateA" type="button" aria-label="Escolher data inicial">${currentA}</button> |
      <button class="date-btn" id="btnDateB" type="button" aria-label="Escolher data final">${currentB}</button> &rarr;`;
  }

  const pop = document.getElementById("datePop");
  const input = document.getElementById("dateInput");
  let activeSide = null;

  function openPicker(side, anchorBtn){
    activeSide = side;
    input.value = (side==="A" ? currentA : currentB);

    const r = anchorBtn.getBoundingClientRect();
    pop.style.left = Math.round(r.left + r.width/2 - 160) + "px";
    pop.style.top  = Math.round(r.bottom + 8 + window.scrollY) + "px";
    pop.hidden = false;

    input.focus();
    try{ input.showPicker && input.showPicker(); }catch{}
  }

  function closePicker(){
    pop.hidden = true;
    activeSide = null;
  }

  const btnA = document.getElementById("btnDateA");
  const btnB = document.getElementById("btnDateB");

  btnA?.addEventListener("click", () => openPicker("A", btnA));
  btnB?.addEventListener("click", () => openPicker("B", btnB));

  document.addEventListener("click", (ev) => {
    if(!pop.hidden && !pop.contains(ev.target) && ev.target !== btnA && ev.target !== btnB){
      closePicker();
    }
  });

  input.addEventListener("change", () => {
    if(!activeSide) return;
    const v = input.value;
    if(!/^\d{4}-\d{2}-\d{2}$/.test(v)) return;

    if(activeSide === "A"){
      currentA = v;
      btnA.textContent = currentA;
      if (typeof leftLayer !== "undefined" && leftLayer.setParams){
        leftLayer.setParams({ time: currentA });
      }
      const capA = document.querySelector("figure .caption");
      if(capA){ capA.textContent = currentA; }
    }else{
      currentB = v;
      btnB.textContent = currentB;
      if (typeof rightLayer !== "undefined" && rightLayer.setParams){
        rightLayer.setParams({ time: currentB });
      }
      const caps = document.querySelectorAll("figure .caption");
      if(caps && caps[1]){ caps[1].textContent = currentB; }
    }
    try{ applyClip && applyClip(); }catch{}
    closePicker();
  });

  ["zoomend","moveend","resize"].forEach(ev => {
    try{ map && map.on(ev, ()=>requestAnimationFrame(()=>applyClip && applyClip())); }catch{}
  });
})();
  </script>

  <!-- ADIÇÃO: limites via WMTS DescribeDomains + clamp (depois do date picker) -->
  <script>
  (function(){
    const pop  = document.getElementById("datePop");
    const input= document.getElementById("dateInput");
    const btnA = document.getElementById("btnDateA");
    const btnB = document.getElementById("btnDateB");
    if(!pop || !input || !btnA || !btnB) return;

    const DD_URL = "https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/wmts.cgi"
      + "?SERVICE=WMTS&REQUEST=DescribeDomains&VERSION=1.0.0"
      + "&LAYER=VIIRS_NOAA20_CorrectedReflectance_TrueColor"
      + "&TILEMATRIXSET=GoogleMapsCompatible_Level6"
      + "&TIME=all";

    let minDate = "2017-12-13"; // início do NOAA-20 TC
    let maxDate = null;

    function parseDomain(xmlTxt){
      const dom = new DOMParser().parseFromString(xmlTxt, "text/xml");
      const dd  = dom.getElementsByTagName("DimensionDomain")[0];
      if(!dd) return;
      const domainEl = dd.getElementsByTagName("Domain")[0];
      if(!domainEl) return;
      const chunks = domainEl.textContent.trim().split(",").map(s=>s.trim()).filter(Boolean);
      if(!chunks.length) return;

      const first = chunks[0].includes("/") ? chunks[0].split("/")[0] : chunks[0];
      const lastRaw = chunks[chunks.length-1];
      const last = lastRaw.includes("/") ? lastRaw.split("/")[1] : lastRaw;

      if(/^\d{4}-\d{2}-\d{2}$/.test(first)) minDate = first;
      if(/^\d{4}-\d{2}-\d{2}$/.test(last))  maxDate = last;
    }

    function applyLimitsAndClamp(){
      if(!maxDate) maxDate = new Date().toISOString().slice(0,10);

      input.min = minDate;
      input.max = maxDate;

      const clamp = (d) => (d < minDate ? minDate : (d > maxDate ? maxDate : d));

      const curA = btnA.textContent.trim();
      const curB = btnB.textContent.trim();
      const newA = clamp(curA);
      const newB = clamp(curB);

      if(newA !== curA){
        btnA.textContent = newA;
        if (typeof leftLayer !== "undefined" && leftLayer.setParams) leftLayer.setParams({ time: newA });
        const capA = document.querySelector("figure .caption"); if(capA) capA.textContent = newA;
      }
      if(newB !== curB){
        btnB.textContent = newB;
        if (typeof rightLayer !== "undefined" && rightLayer.setParams) rightLayer.setParams({ time: newB });
        const caps = document.querySelectorAll("figure .caption"); if(caps && caps[1]) caps[1].textContent = newB;
      }
      try{ applyClip && applyClip(); }catch{}
    }

    input.addEventListener("change", () => {
      if(!input.value) return;
      let v = input.value;
      if(v < minDate) v = minDate;
      if(v > maxDate) v = maxDate;
      if(v !== input.value) input.value = v;
    }, { capture:true });

    fetch(DD_URL, { mode:"cors" })
      .then(r => r.ok ? r.text() : Promise.reject(new Error("DescribeDomains HTTP "+r.status)))
      .then(txt => { parseDomain(txt); })
      .catch(() => { /* mantém fallbacks */ })
      .finally(() => { applyLimitsAndClamp(); });
  })();
  </script>

  <div id="printView" aria-hidden="true"></div>

</body>
</html>
