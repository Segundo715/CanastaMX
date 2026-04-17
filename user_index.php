<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: user_login.php');
    exit;
}

require_once __DIR__ . '/db/conexion.php';
$db = getDB();
$estados = $db->query('SELECT nombre FROM estados ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN);
$productos = $db->query('SELECT id,nombre,unidad,categoria,emoji,precio_base FROM productos ORDER BY categoria,nombre')->fetchAll(PDO::FETCH_ASSOC);
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Consumidor');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CanastaMX - Panel de Usuario</title>
<link rel="stylesheet" href="assets/style.css">
<style>
  .user-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
  .user-welcome { display: grid; gap: 0.25rem; }
  .user-welcome strong { color: var(--blue); }
  .user-actions { display: flex; gap: 0.5rem; align-items: center; }
  .alert-card { padding: 1rem; background: var(--bg2); border: 1px solid var(--border); border-radius: 14px; }
  .alert-card h3 { margin-top: 0; font-size: 1rem; }
  .form-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
  .form-grid .input-group { margin: 0; }
  .btn-primary { background: var(--blue); color: #fff; border: none; border-radius: 10px; padding: 0.9rem 1rem; cursor: pointer; font-weight: 700; }
  .btn-primary:hover { opacity: 0.95; }
  .my-alerts-table { width: 100%; border-collapse: collapse; }
  .my-alerts-table th, .my-alerts-table td { padding: 0.85rem 0.8rem; border-bottom: 1px solid rgba(148,163,184,0.12); }
  .my-alerts-table th { text-align: left; color: #94a3b8; font-size: 0.81rem; }
  .my-alerts-table td { color: #e6edf3; }
  .simple-tag { display: inline-flex; gap: 0.33rem; align-items: center; padding: 0.35rem 0.65rem; border-radius: 999px; font-size: 0.82rem; background: rgba(56,139,253,.12); color: var(--blue); }
  .alert-status.ok { color: #3fb950; }
  .alert-status.disparada { color: #f85149; }
  .message-box { margin-top: 0.75rem; padding: 0.95rem 1rem; border-radius: 12px; background: rgba(56,139,253,.08); border: 1px solid rgba(56,139,253,.16); color: #cfe2ff; }
</style>
</head>
<body>
<header class="header">
  <a href="#" class="logo">
      <div class="logo-icon">🛒</div>
      <div class="logo-text">
          <div class="logo-name">CanastaMX</div>
          <div class="logo-sub">Panel de Usuario</div>
      </div>
  </a>
  <div class="user-header">
    <div class="user-welcome">
      <span class="sl">Hola, <strong><?= $userName ?></strong></span>
      <span class="text-muted">Revisa precios y elige tu estado.</span>
    </div>
    <div class="user-actions">
      <div class="region-wrap">
        <label>Estado:</label>
        <select id="regionSelect" class="region-select">
            <option value="Nacional">Nacional</option>
            <?php foreach ($estados as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      <a href="user_logout.php" class="btn-logout">Cerrar sesión</a>
    </div>
  </div>
  <div class="header-stat">
    <span id="costoCanastaHeader">$--</span>
    <small>COSTO CANASTA</small>
  </div>
</header>

<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-hd">
      <div class="search-wrap"><input id="searchInput" placeholder="Buscar producto..." /></div>
    </div>
    <div class="product-list" id="productList">
      <?php foreach ($productos as $p): ?>
        <div class="prod-item" 
             data-id="<?= $p['id'] ?>" 
             onclick="openPriceChart('<?= $p['id'] ?>', '<?= htmlspecialchars($p['nombre']) ?>', '<?= htmlspecialchars($p['emoji']) ?>', '<?= htmlspecialchars($p['unidad']) ?>', '<?= $p['precio_base'] ?>')">
          <div class="pi-emoji"><?= htmlspecialchars($p['emoji']) ?></div>
          <div class="pi-info">
              <div class="pi-name"><?= htmlspecialchars($p['nombre']) ?></div>
              <div class="pi-unit"><?= htmlspecialchars($p['unidad']) ?></div>
          </div>
          <div class="pi-price">$<?= number_format($p['precio_base'],2) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>

  <main class="main">
    <div class="prod-hd">
        <div class="ph-emoji" id="hEmoji">🛒</div>
        <div>
            <h2 id="hProdName">Selecciona un producto</h2>
            <p id="hProdUnit">Cargando datos...</p>
        </div>
        <div class="ph-price">
            <div class="ph-big" id="hProdPrice">$0.00</div>
        </div>
    </div>

    <div class="resumen-grid">
        <div class="card card-valor">
            <p class="sl">PROMEDIO</p>
            <span id="valPromedio" class="sv">$--</span>
        </div>
        <div class="card card-valor">
            <p class="sl">MÁXIMO</p>
            <span id="valMaximo" class="sv text-red">$--</span>
        </div>
        <div class="card card-valor">
            <p class="sl">MÍNIMO</p>
            <span id="valMinimo" class="sv text-green">$--</span>
        </div>
        <div class="card card-valor">
            <p class="sl">CAMBIO 7D</p>
            <span id="sChg" class="sv">0%</span>
        </div>
    </div>

    <div class="card">
        <div class="card-hd">
            <div class="card-title">📈 Historial de Precios</div>
            <div class="chart-actions">
                <button class="btn-period" data-period="7d" onclick="cambiarPeriodoPrincipal('7d', event)">7 Días</button>
                <button class="btn-period" data-period="1m" onclick="cambiarPeriodoPrincipal('1m', event)">1 Mes</button>
                <button class="btn-period active" data-period="12m" onclick="cambiarPeriodoPrincipal('12m', event)">12 Meses</button>
                <button class="btn-period" data-period="all" onclick="cambiarPeriodoPrincipal('all', event)">Años</button>
            </div>
        </div>
        <div class="chart-wrap">
            <canvas id="chart" width="800" height="350"></canvas>
        </div>
        <div class="history-meta">
            <div id="historyLegend" class="history-legend">Cargando leyenda...</div>
        </div>
    </div>

    <div class="card" style="margin-top:1rem">
        <div class="card-hd"><div class="card-title"> Mis alertas</div></div>
        <div id="userAlertsBody">Cargando tus alertas...</div>
    </div>

    <div class="card" style="margin-top:1rem">
        <div class="card-hd"><div class="card-title">📍 Costo por Estado (Canasta Completa)</div></div>
        <div id="listaCostoEstados" class="grid-estados">Cargando datos...</div>
    </div>

    <div class="card" style="margin-top:1rem">
        <div class="card-hd"><div class="card-title">📊 Comparativa Regional por Tienda</div></div>
        <div id="comparativaBody">Cargando tabla...</div>
    </div>
  </main>
</div>

<script src="assets/chart.umd.min.js" defer></script>
<script src="assets/app.js" defer></script>
<script src="assets/user.js" defer></script>
</body>
</html>
