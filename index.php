<?php
// index.php (Yeow.ai - ultra minimal)
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Yeow.ai — AI Assessment + AI Profile</title>
  <meta name="description" content="Make your business legible to AI. Free AI assessment and free AI profile." />
  <meta name="robots" content="index,follow" />
  <link rel="canonical" href="https://yeow.ai/" />

  <!-- Favicons (adjust paths if you placed them elsewhere) -->
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/yeow-favicon-32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/yeow-favicon-180.png">
  <link rel="icon" type="image/png" sizes="192x192" href="/assets/yeow-favicon-192.png">

  <style>
    :root{
      --bg:#fff;
      --text:#0f172a;
      --muted:#64748b;
      --line:#e2e8f0;
      --soft:#f8fafc;
      --blue:#2563eb;
      --blue2:#1d4ed8;
      --shadow: 0 14px 30px rgba(15,23,42,.10);
      --radius: 999px;
      --radius2: 18px;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
    }
    a{ color:inherit; text-decoration:none; }
    a:hover{ text-decoration:underline; }

    /* Top bar */
    .topbar{
      height:56px;
      display:flex;
      align-items:center;
      justify-content:flex-end;
      padding: 0 14px;
      gap: 10px;
    }
    .toplink{
      font-size: 13px;
      color: #334155;
      padding: 8px 10px;
      border-radius: 10px;
    }
    .toplink:hover{ background: var(--soft); text-decoration:none; }

    /* 9-dot launcher */
    .launcher{ position: relative; }
    .dots{
      width: 40px; height: 40px;
      border-radius: 999px;
      border: 1px solid transparent;
      background: transparent;
      cursor: pointer;
      display:grid;
      place-items:center;
    }
    .dots:hover{ background: var(--soft); border-color: var(--line); }
    .dotgrid{
      width: 18px; height: 18px;
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      grid-template-rows: repeat(3, 1fr);
      gap: 3px;
    }
    .dotgrid span{
      width: 100%;
      height: 100%;
      border-radius: 999px;
      background: #64748b;
      opacity: .9;
    }
    .menu{
      position:absolute;
      right:0;
      top:48px;
      width: 260px;
      border: 1px solid var(--line);
      border-radius: 16px;
      box-shadow: var(--shadow);
      background:#fff;
      padding: 8px;
      display:none;
      z-index: 50;
    }
    .menu a{
      display:block;
      padding: 10px 12px;
      border-radius: 12px;
      font-weight: 850;
      color: #0f172a;
    }
    .menu a:hover{
      background: var(--soft);
      text-decoration:none;
    }
    .menu .smallcap{
      font-size: 12px;
      color: var(--muted);
      padding: 8px 12px 4px;
      font-weight: 850;
      text-transform: uppercase;
      letter-spacing: .06em;
    }

    /* Center */
    .wrap{
      min-height: calc(100vh - 56px);
      display:flex;
      align-items:flex-start;
      justify-content:center;
      padding: 26px 16px 70px;
    }
    .center{
      width: min(860px, 100%);
      text-align:center;
      margin-top: clamp(14px, 6vh, 70px);
    }
    .logo{
      width: 400px;
      height: 280px;
      border-radius: 30px;
      object-fit: cover;
      border: 1px solid var(--line);
      box-shadow: 0 10px 22px rgba(15,23,42,.08);
      background:#FFF200;
    }
    h1{
      margin: 16px 0 0;
      font-size: 34px;
      letter-spacing:-.8px;
      line-height: 1.1;
    }
    .micro{
      margin-top: 8px;
      color: var(--muted);
      font-size: 14px;
      line-height: 1.4;
    }

    /* Search bar */
    .searchRow{
      margin: 18px auto 10px;
      width: min(720px, 100%);
      display:flex;
      align-items:center;
      gap: 10px;
      padding: 12px 14px;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: 0 10px 26px rgba(15,23,42,.06);
      background:#fff;
    }
    .searchIcon{
      width: 18px; height: 18px;
      border-radius: 999px;
      border: 2px solid #64748b;
      position: relative;
      flex: 0 0 auto;
      opacity:.9;
    }
    .searchIcon:after{
      content:"";
      position:absolute;
      width: 9px; height: 2px;
      background:#64748b;
      right:-8px; bottom:-4px;
      transform: rotate(45deg);
      border-radius: 2px;
    }
    .input{
      flex: 1 1 auto;
      border: none;
      outline:none;
      font-size: 16px;
      padding: 6px 4px;
      min-width: 140px;
    }
    .btn{
      flex: 0 0 auto;
      border: 1px solid var(--line);
      background: var(--soft);
      color: #0f172a;
      font-weight: 900;
      border-radius: 999px;
      padding: 10px 14px;
      cursor:pointer;
      white-space:nowrap;
    }
    .btn:hover{ filter: brightness(0.98); }
    .btn-primary{
      background: linear-gradient(135deg, var(--blue), var(--blue2));
      color:#fff;
      border-color: rgba(37,99,235,.35);
    }
    .btn-primary:hover{ filter: brightness(1.05); }

    .ctaRow{
      display:flex;
      justify-content:center;
      gap:10px;
      flex-wrap:wrap;
      margin-top: 10px;
    }
    .ctaLink{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding: 10px 14px;
      border-radius: 999px;
      border:1px solid var(--line);
      background:#fff;
      font-weight: 900;
    }
    .ctaLink:hover{ background: var(--soft); text-decoration:none; }

    /* Errors + results */
    .err{
      margin: 14px auto 0;
      width: min(820px, 100%);
      padding: 12px 14px;
      border-radius: 14px;
      border: 1px solid rgba(239,68,68,.25);
      background: rgba(239,68,68,.06);
      color: #991b1b;
      display:none;
      font-weight: 900;
      text-align:left;
    }
    .card{
      margin: 18px auto 0;
      width: min(820px, 100%);
      border:1px solid var(--line);
      border-radius: var(--radius2);
      box-shadow: 0 14px 30px rgba(15,23,42,.08);
      background:#fff;
      padding: 16px;
      text-align:left;
      display:none;
    }
    .row{
      display:flex;
      justify-content:space-between;
      gap: 12px;
      flex-wrap:wrap;
      align-items:flex-start;
    }
    .pill{
      display:inline-block;
      font-size: 12px;
      padding: 6px 10px;
      border-radius: 999px;
      border:1px solid var(--line);
      background: var(--soft);
      color: #475569;
      font-weight: 850;
    }
    .score{
      font-size: 40px;
      font-weight: 950;
      letter-spacing:-1px;
      line-height:1;
      text-align:right;
    }
    .small{
      font-size: 12px;
      color: var(--muted);
      margin-top: 6px;
    }
    .h{
      margin: 14px 0 8px;
      font-weight: 950;
    }
    ul{ margin: 8px 0 0; padding-left: 18px; color: #475569; }
    li{ margin: 6px 0; }

    /* Optional video below fold */
    .videoWrap{
      margin: 18px auto 0;
      width: min(820px, 100%);
      border:1px solid var(--line);
      border-radius: var(--radius2);
      box-shadow: 0 14px 30px rgba(15,23,42,.08);
      overflow:hidden;
      background:#fff;
      text-align:left;
    }
    .videoHeader{
      padding: 12px 14px;
      border-bottom:1px solid var(--line);
      background: var(--soft);
      font-weight: 900;
      color: #0f172a;
      font-size: 14px;
    }
    video{ width:100%; display:block; background:#000; }

    footer{
      margin-top: 18px;
      color: var(--muted);
      font-size: 12px;
      text-align:center;
      line-height:1.45;
    }
    code{
      background: var(--soft);
      border:1px solid var(--line);
      padding: 2px 6px;
      border-radius: 8px;
      font-size: 12px;
    }
  </style>
</head>

<body>

  <div class="topbar">
    <a class="toplink" href="/browse">Browse</a>
    <a class="toplink" href="/submit">Get profile</a>

    <div class="launcher">
      <button class="dots" id="dotsBtn" aria-label="Menu">
        <div class="dotgrid" aria-hidden="true">
          <span></span><span></span><span></span>
          <span></span><span></span><span></span>
          <span></span><span></span><span></span>
        </div>
      </button>

<div class="menu" id="menu">
  <div class="smallcap">Yeow</div>
  <a href="/submit">Create / claim your profile</a>
  <a href="/browse">Browse & search profiles</a>
  <a href="dir//dallas/plumbers">Dallas plumbers</a>
  <a href="/site/theprosperplumber.com">Example profile</a>

  <div class="smallcap" style="margin-top:6px;">Videos</div>
  <a href="/videos/what-is-yeow">What is Yeow?</a>
</div>

    </div>
  </div>

  <div class="wrap">
    <div class="center">

      <img class="logo" src="/assets/yeow_logo6.jpg" alt="Yeow.ai logo">

      <h1>AI Booster only $10</h1>
      <div class="micro">FREE assessment + FREE profile • No website changes</div>

      <form id="gradeForm" class="searchRow">
        <div class="searchIcon" aria-hidden="true"></div>
        <input class="input" id="url" name="url" placeholder="Enter your business website" autocomplete="off" required />
        <button class="btn btn-primary" type="submit">Get assessment</button>
      </form>

      <!--div class="ctaRow">
        <a class="ctaLink" href="/submit">Get your free AI profile today. More Calls. More Jobs.</a>
        <!--a class="ctaLink" href="/site/theprosperplumber.com">See example</a-->
      </div-->

      <div class="err" id="err"></div>

      <section class="card" id="result">
        <div class="row">
          <div>
            <div class="pill" id="siteLabel"></div>
            <div class="small" id="fetchedLabel"></div>
          </div>
          <div>
            <div class="pill" id="tier"></div>
            <div class="score">
              <span id="score">--</span><span style="font-size:14px;font-weight:900;color:#64748b;">/100</span>
            </div>
          </div>
        </div>

        <div class="h">Top recommendations</div>
        <ul id="recs"></ul>

        <div class="h">Detected</div>
        <ul id="signals"></ul>

        <div class="h">Next</div>
        <div class="small">Create your profile at <code>/site/yourdomain.com</code>.</div>
        <div class="ctaRow" style="justify-content:flex-start; margin-top:10px;">
          <a class="btn btn-primary" href="/submit">Get your AI profile</a>
          <a class="btn" href="/browse">Browse</a>
        </div>
      </section>

      

      <footer>
        yeow.ai &copy; <?php echo date('Y');?>
      </footer>

    </div>
  </div>

  <script>
    // App launcher menu
    const dotsBtn = document.getElementById('dotsBtn');
    const menu = document.getElementById('menu');

    function closeMenu() { menu.style.display = 'none'; }
    function toggleMenu() {
      menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }
    dotsBtn.addEventListener('click', (e) => { e.preventDefault(); toggleMenu(); });
    document.addEventListener('click', (e) => {
      if (!menu.contains(e.target) && !dotsBtn.contains(e.target)) closeMenu();
    });

    // Grader
    const form = document.getElementById('gradeForm');
    const err = document.getElementById('err');
    const result = document.getElementById('result');

    const scoreEl = document.getElementById('score');
    const tierEl = document.getElementById('tier');
    const siteLabel = document.getElementById('siteLabel');
    const fetchedLabel = document.getElementById('fetchedLabel');
    const recs = document.getElementById('recs');
    const signals = document.getElementById('signals');

    function setError(msg) {
      err.style.display = 'block';
      err.textContent = msg;
    }
    function clearError() {
      err.style.display = 'none';
      err.textContent = '';
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearError();

      result.style.display = 'none';
      recs.innerHTML = '';
      signals.innerHTML = '';
      scoreEl.textContent = '--';
      tierEl.textContent = 'Scoring…';

      const url = document.getElementById('url').value.trim();

      try {
        const res = await fetch('/api/grade.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ url })
        });

        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || 'Unable to score this site.');

        siteLabel.textContent = data.site || 'Site';
        fetchedLabel.textContent = data.fetched_url ? `Fetched: ${data.fetched_url}` : '';
        scoreEl.textContent = (data.score !== undefined && data.score !== null) ? data.score : '--';
        tierEl.textContent = data.tier || '';

        (data.recommendations || []).forEach(r => {
          const li = document.createElement('li');
          li.textContent = r;
          recs.appendChild(li);
        });

        (data.signals || []).forEach(s => {
          const li = document.createElement('li');
          li.textContent = s;
          signals.appendChild(li);
        });

        result.style.display = 'block';
        result.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (ex) {
        setError(ex.message || 'Something went wrong.');
        tierEl.textContent = '';
      }
    });
  </script>

</body>
</html>
