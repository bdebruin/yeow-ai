<?php
// /submit/index.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create AI Profile | Yeow.ai</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="index,follow">
  <link rel="canonical" href="https://yeow.ai/submit">

  <style>
    :root{
      --text:#0f172a;
      --muted:#64748b;
      --line:#e2e8f0;
      --blue:#2563eb;
      --blue2:#1d4ed8;
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background:#fff;
      color:var(--text);
    }

    /* Header */
    .top{
      height:56px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:0 16px;
    }
    .brand{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:900;
    }
    .brand img{
      width:32px;
      height:32px;
      border-radius:8px;
      border:1px solid var(--line);
    }
    .nav a{
      font-size:13px;
      padding:8px 10px;
      border-radius:10px;
      color:#334155;
      text-decoration:none;
    }
    .nav a:hover{
      background:#f8fafc;
    }

    /* Center */
    .wrap{
      min-height:calc(100vh - 56px);
      display:flex;
      justify-content:center;
      padding:40px 16px;
    }
    .center{
      width:min(680px,100%);
      text-align:center;
      margin-top:6vh;
    }

    .logo{
      width:140px;
      height:140px;
      border-radius:30px;
      border:1px solid var(--line);
      margin-bottom:16px;
    }

    h1{
      margin:0;
      font-size:32px;
      letter-spacing:-.8px;
    }

    /* Form */
    form{
      margin-top:24px;
      display:flex;
      gap:10px;
      padding:12px 14px;
      border:1px solid var(--line);
      border-radius:999px;
    }
    input{
      flex:1;
      border:none;
      outline:none;
      font-size:16px;
      padding:6px;
    }
    button{
      border:none;
      background:linear-gradient(135deg,var(--blue),var(--blue2));
      color:#fff;
      font-weight:900;
      padding:10px 16px;
      border-radius:999px;
      cursor:pointer;
    }
    button:hover{ filter:brightness(1.05); }

    footer{
      margin-top:36px;
      font-size:12px;
      color:var(--muted);
    }
  </style>
</head>

<body>

  <div class="top">
    <div class="brand">
      <img src="/assets/yeow_logo.jpg" alt="Yeow">
      Yeow
    </div>
    <div class="nav">
      <a href="/">Home</a>
      <a href="/browse">Browse</a>
    </div>
  </div>

  <div class="wrap">
    <div class="center">

      <img class="logo" src="/assets/yeow_logo.jpg" alt="Yeow logo">

      <h1>Create your AI profile</h1>

      <form method="post" action="/submit/submit.php">
        <input
  type="text"
  name="url"
  placeholder="yourbusiness.com"
  required
  autocomplete="off"
  inputmode="url"
>
        <button type="submit">Create</button>
      </form>

      <footer>
        Free • No website changes
      </footer>

    </div>
  </div>

</body>
</html>
