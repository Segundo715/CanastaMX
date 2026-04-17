<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db/conexion.php';
$db = getDB();

$estados = $db->query('SELECT nombre FROM estados ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN);
$productos = $db->query('SELECT id,nombre,unidad,categoria,emoji,precio_base FROM productos ORDER BY categoria,nombre')->fetchAll(PDO::FETCH_ASSOC);
$usuarios = $db->query("SELECT id, usuario FROM usuarios WHERE rol = 'user' ORDER BY usuario")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CanastaMX - Monitor de Precios</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    /* Estilos de la Cuadrícula de Estados */
    .grid-estados {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 12px;
        padding: 15px;
        background: var(--bg2);
        border-radius: 8px;
    }
    .estado-card {
        background: #161b22;
        border: 1px solid #30363d;
        padding: 12px;
        border-radius: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: transform 0.2s;
    }
    .est-info { display: flex; flex-direction: column; }
    .est-name { font-size: 0.65rem; color: #8b949e; text-transform: uppercase; margin-bottom: 4px; }
    .est-price { font-size: 1.1rem; font-weight: 800; color: #e6edf3; }
    .est-trend { font-size: 0.75rem; font-weight: bold; padding: 2px 6px; border-radius: 4px; }
    .est-trend.down { background: rgba(63, 185, 80, 0.1); color: #3fb950; }
    .est-trend.up { background: rgba(248, 81, 73, 0.1); color: #f85149; }

    /* Botones de Periodo */
    .btn-period {
        background: var(--bg3);
        border: 1px solid var(--border);
        color: var(--text2);
        padding: 5px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.75rem;
    }
    .btn-period.active {
        background: var(--blue) !important;
        color: white !important;
        border-color: var(--blue) !important;
    }
    .btn-primary {
        background: var(--blue);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 0.85rem 1rem;
        cursor: pointer;
        font-weight: 700;
    }
    .btn-primary:hover {
        opacity: 0.92;
    }
    .simple-tag {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 0.75rem;
        border-radius: 999px;
        background: rgba(56,139,253,0.08);
        color: var(--blue);
        font-size: 0.92rem;
        border: 1px solid rgba(56,139,253,0.18);
    }
    .text-red { color: #f85149 !important; }
    .text-green { color: #3fb950 !important; }
  </style>
  <script src="assets/chart.umd.min.js" defer></script>
  <script src="assets/app.js" defer></script>
<body>

<header class="header">
  <a href="#" class="logo">
      <div class="logo-icon">🛒</div>
      <div class="logo-text">
          <div class="logo-name">CanastaMX</div>
          <div class="logo-sub">Monitor de Precios</div>
      </div>
  </a>
  
  <div style="display:flex;align-items:center;gap:.5rem;margin-left:1.2rem">
    <div class="region-wrap">
      <label>Estado:</label>
      <select id="regionSelect" class="region-select">
        <option value="Nacional">Nacional</option>
        <?php foreach ($estados as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="header-stat">
    <span id="costoCanastaHeader">$--</span>
    <small>COSTO CANASTA</small>
  </div>
  
  <a href="logout.php" class="btn-logout">Salir</a>
</header>

<div class="notifications-banner" id="notificationsBanner" style="display: none;">
  <span class="notif-badge" id="notifBadge">0</span>
  <span style="flex: 1;"></span>
  <button onclick="toggleNotificationsPanel()" class="toggle-notif-btn">🔔 Ver notificaciones</button>
</div>

<div class="notifications-panel" id="notificationsPanel" style="display: none;">
  <div class="notif-panel-header">
    <h3>🔔 Notificaciones de Precios</h3>
    <button onclick="toggleNotificationsPanel()" style="background: none; border: none; color: var(--text2); cursor: pointer; font-size: 1.2rem;">×</button>
  </div>
  <div id="notificationsBody" class="notif-panel-body">Cargando notificaciones...</div>
</div>

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
            <p id="hProdUnit">Cargando...</p>
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
                <button class="btn-period active" data-period="7d" onclick="cambiarPeriodoPrincipal('7d', event)">Días</button>
                <button class="btn-period" data-period="1m" onclick="cambiarPeriodoPrincipal('1m', event)">Meses</button>
                <button class="btn-period" data-period="12m" onclick="cambiarPeriodoPrincipal('12m', event)">Años</button>
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
        <div class="card-hd"><div class="card-title">📍 Costo por Estado (Canasta Completa)</div></div>
        <div id="listaCostoEstados" class="grid-estados">Cargando datos...</div>
    </div>

    <div class="card" style="margin-top:1rem">
        <div class="card-hd"><div class="card-title">📊 Comparativa Regional por Tienda</div></div>
        <div id="comparativaBody">Cargando tabla...</div>
    </div>

    <div class="card" style="margin-top:1rem">
        <div class="card-hd"><div class="card-title">⚠️ Crear alerta de precio</div></div>
        <div class="alert-card">
            <form id="adminAlertForm">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Producto actual</label>
                        <div id="adminAlertProduct" class="simple-tag">Selecciona un producto</div>
                    </div>
                    <div class="input-group">
                        <label for="adminAlertType">Tipo</label>
                        <select id="adminAlertType" required>
                            <option value="BAJA">BAJA</option>
                            <option value="SUBE">SUBE</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="adminAlertPrice">Precio límite</label>
                        <input type="number" id="adminAlertPrice" placeholder="Ej. 45.00" min="0" step="0.01" required>
                    </div>
                    <div class="input-group">
                        <label for="adminAlertRegion">Región</label>
                        <select id="adminAlertRegion">
                            <option value="Nacional">Nacional</option>
                            <?php foreach ($estados as $e): ?>
                                <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="adminAlertUser">Enviar alertas a</label>
                        <select id="adminAlertUser">
                            <option value="">✉️ Todos los usuarios</option>
                            <optgroup label="O un usuario específico:">
                                <?php foreach ($usuarios as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['usuario']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                </div>
                <div style="margin-top:1rem; display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
                    <button type="submit" class="btn-primary">Crear alerta</button>
                    <div id="adminAlertMessage" class="message-box" style="display:none;"></div>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:1rem">
        <div class="card-hd"><div class="card-title">📣 Alertas para Admin</div></div>
        <div id="alertsBody">Cargando alertas...</div>
    </div>
  </main>
</div>

</body>
</html>