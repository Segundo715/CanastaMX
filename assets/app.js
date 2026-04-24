const BASE = window.location.origin + '/CanastaMX';
const API = {
    productos: BASE + '/api/productos.php',
    historial: BASE + '/api/historial.php',
    comparativa: BASE + '/api/comparativa.php',
    costo_canasta: BASE + '/api/costo_canasta.php',
    alertas: BASE + '/api/alertas.php',
    notificaciones: BASE + '/api/notificaciones.php'
};

const STORE_COLORS = {
    INEGI: '#58a6ff',
    Walmart: '#f59e0b',
    Soriana: '#10b981',
    Chedraui: '#ef4444',
    'Bodega Aurrera': '#a855f7',
    'La Comer': '#14b8a6',
    MercadoLibre: '#f97316'
};

let currentPid = null;
let regionActual = 'Nacional';
let chartInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

async function initApp() {
    await loadProducts(); 
    loadCostoCanastaTotal();
    bindEvents();
    initGeoLocation();
    loadAdminAlerts();
    loadAdminNotifications();
    checkUnreadNotifications();
}

function bindEvents() {
    const regionSelect = document.getElementById('regionSelect');
    if(regionSelect) {
        regionSelect.addEventListener('change', e => {
            regionActual = e.target.value;
            if (currentPid) refreshActiveData();
        });
    }

    const adminAlertForm = document.getElementById('adminAlertForm');
    if (adminAlertForm) {
        adminAlertForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await createAdminAlert();
        });
    }
}

async function loadProducts() {
    try {
        const resp = await fetch(API.productos);
        const json = await resp.json();
        console.log('Products loaded:', json);
        if (json.success && json.data.length > 0) {
            const p = json.data[0];
            openPriceChart(p.id, p.nombre, p.emoji, p.unidad, p.precio_base);
        }
    } catch (e) { console.error("Error productos:", e); }
}

function openPriceChart(id, nombre, emoji, unidad, precioBase) {
    currentPid = id;
    document.getElementById('hProdName').innerText = nombre;
    document.getElementById('hEmoji').innerText = emoji;
    document.getElementById('hProdUnit').innerText = unidad;
    document.getElementById('hProdPrice').innerText = `$${parseFloat(precioBase).toFixed(2)}`;

    const adminAlertProduct = document.getElementById('adminAlertProduct');
    if (adminAlertProduct) {
        adminAlertProduct.textContent = `${emoji} ${nombre}`;
    }

    const userAlertProduct = document.getElementById('alertProduct');
    if (userAlertProduct) {
        userAlertProduct.textContent = `${emoji} ${nombre}`;
    }
    
    refreshActiveData();
}

function refreshActiveData() {
    const btnActivo = document.querySelector('.btn-period.active');
    const periodo = btnActivo ? btnActivo.getAttribute('data-period') : '12m';
    
    cargarDataGrafica(currentPid, periodo);
    loadComparativa(currentPid);
    loadCostoCanastaTotal();
    loadAdminAlerts();
    loadAdminNotifications();
}

async function cargarDataGrafica(id, periodo) {
    try {
        const resp = await fetch(`${API.historial}?producto_id=${id}&periodo=${periodo}&region=${regionActual}`);
        const res = await resp.json();
        console.log('Fetched data:', res);
        if (res.success) {
            renderizarMiGrafica('chart', res.data);
            actualizarTarjetasResumen(res.data);
        }
    } catch (e) { console.error("Error historial:", e); }
}

function renderizarMiGrafica(canvasId, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.error('Canvas not found');
        return;
    }

    const fuentes = Object.keys(data);
    if (fuentes.length === 0) return;

    const labels = data[fuentes[0]].map(p => p.fecha);
    const datasets = fuentes.map((fuente) => {
        const color = STORE_COLORS[fuente] || '#8b949e';
        return {
            label: fuente,
            data: data[fuente].map(p => parseFloat(p.precio)),
            borderColor: color,
            backgroundColor: fuente === 'INEGI' ? 'transparent' : `${color}22`,
            fill: fuente !== 'INEGI',
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: color,
            pointBorderColor: '#0d1117',
            pointBorderWidth: 1,
            borderWidth: fuente === 'INEGI' ? 3 : 2,
            borderDash: fuente === 'INEGI' ? [6, 4] : [],
            spanGaps: true
        };
    });

    if (chartInstance) {
        chartInstance.destroy();
    }

    chartInstance = new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'rectRounded',
                        padding: 14,
                        color: '#c9d1d9',
                        boxWidth: 14,
                        boxHeight: 14
                    }
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(15, 23, 42, 0.98)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(148,163,184,0.25)',
                    borderWidth: 1,
                    callbacks: {
                        label: ctx => `${ctx.dataset.label}: $${ctx.parsed.y.toFixed(2)}`
                    }
                }
            },
            layout: {
                padding: {
                    top: 10,
                    right: 16,
                    left: 8,
                    bottom: 8
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(148,163,184,0.08)' },
                    ticks: { color: '#c9d1d9', maxRotation: 0, minRotation: 0 },
                    border: { color: 'rgba(148,163,184,0.2)' }
                },
                y: {
                    grid: { color: 'rgba(148,163,184,0.08)' },
                    ticks: {
                        color: '#c9d1d9',
                        callback: value => `$${value}`
                    },
                    border: { color: 'rgba(148,163,184,0.2)' }
                }
            }
        }
    });

    renderHistoryLegend(data);
}

function renderHistoryLegend(data) {
    const legendEl = document.getElementById('historyLegend');
    if (!legendEl) return;
    legendEl.innerHTML = Object.entries(STORE_COLORS).map(([name, color]) => `
        <span class="legend-chip">
            <span class="legend-dot" style="background:${color}"></span>
            ${name}
        </span>
    `).join('');
}

function actualizarTarjetasResumen(data) {
    const precios = Object.values(data).flatMap(t => t.map(p => parseFloat(p.precio)));
    
    if (precios.length > 0) {
        const max = Math.max(...precios);
        const min = Math.min(...precios);
        const avg = precios.reduce((a, b) => a + b, 0) / precios.length;

        document.getElementById('valPromedio').innerText = `$${avg.toFixed(2)}`;
        document.getElementById('valMaximo').innerText = `$${max.toFixed(2)}`;
        document.getElementById('valMinimo').innerText = `$${min.toFixed(2)}`;
    }

    const cambio = calcularCambio7D(data);
    const cambioEl = document.getElementById('sChg');
    cambioEl.innerText = `${cambio.porcentaje}%`;
    cambioEl.classList.toggle('text-red', cambio.delta > 0);
    cambioEl.classList.toggle('text-green', cambio.delta < 0);
}

function calcularCambio7D(data) {
    const source = data['Walmart'] || Object.values(data)[1] || Object.values(data)[0];
    if (!source || source.length < 2) {
        return { delta: 0, porcentaje: '0.00' };
    }

    const primero = parseFloat(source[0].precio);
    const ultimo = parseFloat(source[source.length - 1].precio);
    const delta = ultimo - primero;
    const porcentaje = primero === 0 ? 0 : (delta / primero) * 100;
    return { delta, porcentaje: (porcentaje >= 0 ? '+' : '') + porcentaje.toFixed(2) };
}

async function createAdminAlert() {
    const messageBox = document.getElementById('adminAlertMessage');
    if (!currentPid) {
        return showAlertMessage(messageBox, 'Selecciona primero un producto.', false);
    }

    const tipo = document.getElementById('adminAlertType').value;
    const precioLimite = parseFloat(document.getElementById('adminAlertPrice').value);
    const region = document.getElementById('adminAlertRegion').value;
    const usuarioId = document.getElementById('adminAlertUser').value || null;

    if (!precioLimite || precioLimite <= 0) {
        return showAlertMessage(messageBox, 'Ingresa un precio límite válido.', false);
    }

    try {
        const payload = {
            producto_id: currentPid,
            tipo,
            precio_limite: precioLimite,
            region
        };
        if (usuarioId) {
            payload.usuario_id = parseInt(usuarioId, 10);
        }
        const resp = await fetch(API.alertas, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (!data.success) {
            return showAlertMessage(messageBox, data.error || 'No se pudo crear la alerta.', false);
        }
        document.getElementById('adminAlertPrice').value = '';
        showAlertMessage(messageBox, 'Alerta creada correctamente.', true);
        loadAdminAlerts();
    } catch (error) {
        console.error('Error creando alerta:', error);
        showAlertMessage(messageBox, 'Error al crear la alerta. Intenta nuevamente.', false);
    }
}

function showAlertMessage(element, message, success = true) {
    if (!element) return;
    element.textContent = message;
    element.style.display = 'block';
    element.style.background = success ? 'rgba(38, 166, 154, 0.14)' : 'rgba(248, 81, 73, 0.12)';
    element.style.borderColor = success ? 'rgba(38, 166, 154, 0.2)' : 'rgba(248, 81, 73, 0.2)';
    element.style.color = success ? '#8ef2cd' : '#ffb8b8';
    setTimeout(() => {
        element.style.display = 'none';
    }, 4500);
}

async function loadCostoCanastaTotal() {
    const cont = document.getElementById('listaCostoEstados');
    try {
        const resp = await fetch(API.costo_canasta);
        const j = await resp.json();
        if (j.success) {
            if (!j.estados || j.estados.length === 0) {
                cont.innerHTML = '<p>No hay datos disponibles.</p>';
                return;
            }

            let selectedCost = null;
            cont.innerHTML = j.estados.map(est => {
                const isCurrent = est.nombre === regionActual;
                const trendClass = est.tendencia > 0 ? 'text-red' : 'text-green';
                if (isCurrent) {
                    selectedCost = parseFloat(est.costo_total);
                }
                return `
                    <div class="estado-card${isCurrent ? ' estado-active' : ''}">
                        <div class="est-info">
                            <div class="est-name">${est.nombre}${isCurrent ? ' · ESTÁS AQUÍ' : ''}</div>
                            <div class="est-price">$${parseFloat(est.costo_total).toFixed(2)}</div>
                        </div>
                        <div class="est-trend ${trendClass}">${est.tendencia > 0 ? '▲' : '▼'} ${Math.abs(parseFloat(est.tendencia).toFixed(2))}%</div>
                    </div>
                `;
            }).join('');
            const headerValue = selectedCost !== null ? selectedCost : parseFloat(j.promedio_nacional);
            document.getElementById('costoCanastaHeader').innerText = `$${headerValue.toFixed(2)}`;
        }
    } catch (e) { console.error(e); }
}

async function loadAdminAlerts() {
    const box = document.getElementById('alertsBody');
    if (!box) return;
    try {
        const resp = await fetch(`${API.alertas}?activas=1`);
        const j = await resp.json();
        if (j.success && Array.isArray(j.data)) {
            if (j.data.length === 0) {
                box.innerHTML = '<p>No hay alertas activas.</p>';
                return;
            }
            box.innerHTML = `<table class="prices-table comparisons-table">
                <thead><tr><th>Producto</th><th>Región</th><th>Tipo</th><th>Límite</th><th>Precio real</th><th>Usuario</th><th>Estado</th><th>Creado</th></tr></thead>
                <tbody>${j.data.map(alerta => `
                    <tr>
                        <td>${alerta.emoji} ${alerta.producto_nombre}</td>
                        <td>${alerta.region}</td>
                        <td>${alerta.tipo}</td>
                        <td>$${parseFloat(alerta.precio_limite).toFixed(2)}</td>
                        <td>$${parseFloat(alerta.precio_actual).toFixed(2)}</td>
                        <td>${alerta.usuario_nombre || 'Usuario'}</td>
                        <td>${alerta.disparada ? '<span class="text-red">' + alerta.status + '</span>' : '<span class="text-green">' + alerta.status + '</span>'}</td>
                        <td>${alerta.created_at}</td>
                    </tr>`).join('')}</tbody></table>`;
        }
    } catch (e) { console.error('Error cargando alertas:', e); }
}

async function loadAdminNotifications() {
    const box = document.getElementById('notificationsBody');
    if (!box) return;
    try {
        const resp = await fetch(`${API.notificaciones}?action=listar&limit=8`);
        const j = await resp.json();
        if (!j.success || !Array.isArray(j.data)) {
            box.innerHTML = '<p>No hay notificaciones.</p>';
            return;
        }
        if (j.data.length === 0) {
            box.innerHTML = '<p>No hay cambios de precio recientes.</p>';
            return;
        }
        box.innerHTML = j.data.map(notif => {
            const isUnread = !notif.leida;
            const tipoBg = notif.tipo === 'ALZA DE PRECIO' ? 'rgba(248, 81, 73, 0.12)' : 'rgba(63, 185, 80, 0.12)';
            const tipoColor = notif.tipo === 'ALZA DE PRECIO' ? '#f85149' : '#3fb950';
            const icon = notif.tipo === 'ALZA DE PRECIO' ? '📈' : '📉';
            return `<div class="notif-item${isUnread ? ' notif-unread' : ''}" style="background:${tipoBg}">
                <div class="notif-icon">${icon}</div>
                <div class="notif-content">
                    <div class="notif-title">${notif.producto_nombre}</div>
                    <div class="notif-region">${notif.region}</div>
                    <div class="notif-detail">Límite: $${parseFloat(notif.precio_anterior).toFixed(2)} → Actual: $${parseFloat(notif.precio_actual).toFixed(2)}</div>
                    <div class="notif-time">${new Date(notif.created_at).toLocaleString('es-MX')}</div>
                </div>
            </div>`;
        }).join('');
    } catch (e) { console.error('Error cargando notificaciones:', e); }
}

function cambiarPeriodoPrincipal(p, e) {
    document.querySelectorAll('.btn-period').forEach(b => b.classList.remove('active'));
    e.target.classList.add('active');
    cargarDataGrafica(currentPid, p);
}

function toggleNotificationsPanel() {
    const panel = document.getElementById('notificationsPanel');
    if (panel) {
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    }
}

async function checkUnreadNotifications() {
    try {
        const resp = await fetch(`${API.notificaciones}?action=sin_leer`);
        const data = await resp.json();
        if (data.success && data.sin_leer > 0) {
            const banner = document.getElementById('notificationsBanner');
            const badge = document.getElementById('notifBadge');
            if (banner && badge) {
                banner.style.display = 'flex';
                badge.textContent = data.sin_leer;
            }
        } else {
            const banner = document.getElementById('notificationsBanner');
            if (banner) banner.style.display = 'none';
        }
    } catch (e) { console.error('Error checking notifications:', e); }
}

function addLocationBadge(estado) {
    const existing = document.getElementById('locationBadge');
    if (existing) {
        existing.textContent = `📍 Estás aquí: ${estado}`;
        return;
    }
    const badge = document.createElement('div');
    badge.id = 'locationBadge';
    badge.textContent = `📍 Estás aquí: ${estado}`;
    badge.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        padding: 12px 16px;
        border-radius: 12px;
        background: rgba(16, 185, 129, 0.12);
        border: 1px solid rgba(16, 185, 129, 0.35);
        color: #10b981;
        font-weight: 700;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15);
    `;
    document.body.appendChild(badge);
}

async function loadComparativa(id) {
    const box = document.getElementById('comparativaBody');
    try {
        const resp = await fetch(`${API.comparativa}?producto_id=${id}`);
        const j = await resp.json();
        if(j.success) {
            const colors = j.tiendas_colors || STORE_COLORS;
            const headerCells = j.tiendas_keys.map(t => `<th style="color:${colors[t] || '#8b949e'}">${t}</th>`).join('');
            box.innerHTML = `<div class="comparativa-legend">${j.tiendas_keys.map(t => {
                const color = colors[t] || STORE_COLORS[t] || '#8b949e';
                return `<span class="legend-chip"><span class="legend-dot" style="background:${color}"></span>${t}</span>`;
            }).join('')}</div>
            <table class="prices-table comparisons-table">
                <thead><tr><th>Estado</th>${headerCells}<th>Promedio</th></tr></thead>
                <tbody>${j.data.map(r => {
                    const precioValues = j.tiendas_keys.map(t => r.precios[t]);
                    const maxPrecio = Math.max(...precioValues);
                    const minPrecio = Math.min(...precioValues);
                    
                    const currentStateRow = r.estado === regionActual;
            return `<tr class="comparativa-row${currentStateRow ? ' comparativa-row-active' : ''}">
                        <td>${r.emoji} ${r.estado}${currentStateRow ? ' · ESTÁS AQUÍ' : ''}</td>
                        ${j.tiendas_keys.map(t => {
                            const price = r.precios[t];
                            const color = colors[t] || STORE_COLORS[t] || '#8b949e';
                            let bgColor = 'transparent';
                            let fgColor = color;
                            
                            if (price === maxPrecio) {
                                bgColor = 'rgba(248, 81, 73, 0.12)';
                                fgColor = '#f85149';
                            } else if (price === minPrecio) {
                                bgColor = 'rgba(63, 185, 80, 0.12)';
                                fgColor = '#3fb950';
                            }
                            
                            return `<td style="color:${fgColor}; font-weight:700; background:${bgColor}; border-radius:6px; padding:8px 4px;">$${price.toFixed(2)}</td>`;
                        }).join('')}
                        <td style="color:var(--blue); font-weight:700;">$${r.promedio.toFixed(2)}</td>
                    </tr>`;
                }).join('')}</tbody></table>`;
        }
    } catch(e) { console.error(e); }
}

function initGeoLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(async (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            await detectUserLocation(lat, lng);
        }, (error) => {
            console.log('Geolocation error:', error);
        });
    }
}

async function detectUserLocation(lat, lng) {
    const apiKey = 'AIzaSyD_jRXe9f0sNvCFjzI7N2xBk2d3z8q9vJ0';
    const url = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${apiKey}`;
    
    try {
        const resp = await fetch(url);
        const data = await resp.json();
        
        if (data.results && data.results.length > 0) {
            const location = data.results[0].formatted_address;
            let estado = 'Ubicación desconocida';
            
            for (const component of data.results[0].address_components) {
                if (component.types.includes('administrative_area_level_1')) {
                    estado = component.long_name;
                    break;
                }
            }
            
            addLocationBadge(estado);
            
            if (document.getElementById('regionSelect')) {
                document.getElementById('regionSelect').value = estado;
                regionActual = estado;
                if (currentPid) refreshActiveData();
            }
        }
    } catch (error) {
        console.error('Error detecting location:', error);
    }
}
