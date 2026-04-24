const userElements = {
    alertForm: document.getElementById('alertForm'),
    alertPrice: document.getElementById('alertPrice'),
    alertType: document.getElementById('alertType'),
    alertRegion: document.getElementById('alertRegion'),
    alertMessage: document.getElementById('alertFormMessage'),
    userAlertsBody: document.getElementById('userAlertsBody')
};

document.addEventListener('DOMContentLoaded', () => {
    if (userElements.alertForm) {
        bindUserAlertForm();
    }
    loadUserAlerts();
    loadNotificaciones();
    synchronizeAlertRegion();
});

function bindUserAlertForm() {
    userElements.alertForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const precioLimite = parseFloat(userElements.alertPrice.value);
        const tipo = userElements.alertType.value;
        const region = userElements.alertRegion.value;

        if (!currentPid) {
            showAlertMessage('Selecciona primero un producto para asociar la alerta.', false);
            return;
        }

        if (isNaN(precioLimite) || precioLimite <= 0) {
            showAlertMessage('Ingresa un precio límite válido.', false);
            return;
        }

        try {
            const resp = await fetch(API.alertas, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ producto_id: currentPid, tipo, precio_limite: precioLimite, region })
            });
            const data = await resp.json();
            if (!data.success) {
                showAlertMessage(data.error || 'No se pudo guardar la alerta.', false);
                return;
            }
            showAlertMessage('Alerta guardada correctamente.', true);
            userElements.alertForm.reset();
            if (currentPid) loadUserAlerts();
        } catch (error) {
            console.error('Error creando alerta:', error);
            showAlertMessage('Error al enviar la alerta. Intenta de nuevo.', false);
        }
    });
}

function showAlertMessage(message, success = true) {
    if (!userElements.alertMessage) return;
    userElements.alertMessage.textContent = message;
    userElements.alertMessage.style.display = 'block';
    userElements.alertMessage.style.background = success ? 'rgba(38, 166, 154, 0.14)' : 'rgba(248, 81, 73, 0.12)';
    userElements.alertMessage.style.borderColor = success ? 'rgba(38, 166, 154, 0.2)' : 'rgba(248, 81, 73, 0.2)';
    userElements.alertMessage.style.color = success ? '#8ef2cd' : '#ffb8b8';
    setTimeout(() => {
        if (userElements.alertMessage) userElements.alertMessage.style.display = 'none';
    }, 4500);
}

async function loadUserAlerts() {
    if (!userElements.userAlertsBody) return;
    try {
        const resp = await fetch(`${API.alertas}?mine=1&general=1`);
        const data = await resp.json();
        if (!data.success) {
            userElements.userAlertsBody.innerHTML = '<p>No fue posible cargar tus alertas.</p>';
            return;
        }

        if (!Array.isArray(data.data) || data.data.length === 0) {
            userElements.userAlertsBody.innerHTML = '<p>No hay alertas activas para ti.</p>';
            return;
        }

        userElements.userAlertsBody.innerHTML = `<table class="my-alerts-table">
            <thead><tr><th>Producto</th><th>Región</th><th>Tipo</th><th>Límite</th><th>Precio actual</th><th>Estado</th><th>Tipo alerta</th><th>Fecha</th></tr></thead>
            <tbody>${data.data.map(alerta => `
                <tr>
                    <td>${alerta.emoji} ${alerta.producto_nombre}</td>
                    <td>${alerta.region}</td>
                    <td>${alerta.tipo}</td>
                    <td>$${parseFloat(alerta.precio_limite).toFixed(2)}</td>
                    <td>$${parseFloat(alerta.precio_actual).toFixed(2)}</td>
                    <td class="alert-status ${alerta.disparada ? 'disparada' : 'ok'}">${alerta.disparada ? 'DISPARADA' : 'ACTIVA'}</td>
                    <td style="font-size: 0.85rem; color: var(--text2);">${alerta.usuario_nombre ? 'Personal' : 'General'}</td>
                    <td>${alerta.created_at}</td>
                </tr>`).join('')}</tbody>
        </table>`;
    } catch (error) {
        console.error('Error cargando alertas de usuario:', error);
        userElements.userAlertsBody.innerHTML = '<p>Error cargando tus alertas.</p>';
    }
}

function synchronizeAlertRegion() {
    const regionSelect = document.getElementById('regionSelect');
    if (!regionSelect || !userElements.alertRegion) return;
    regionSelect.addEventListener('change', (e) => {
        userElements.alertRegion.value = e.target.value;
    });
}

// ── FUNCIONES DE NOTIFICACIONES ──────────────────────────
async function loadNotificaciones() {
    const notificacionesBody = document.getElementById('notificacionesBody');
    if (!notificacionesBody) return;

    try {
        const resp = await fetch(`${API.notificaciones}?action=listar`);
        const data = await resp.json();

        if (!data.success) {
            notificacionesBody.innerHTML = '<p style="padding: 1rem; color: var(--text2);">No se pudieron cargar las notificaciones.</p>';
            return;
        }

        if (!Array.isArray(data.data) || data.data.length === 0) {
            notificacionesBody.innerHTML = '<p style="padding: 1rem; color: var(--text2);">No tienes notificaciones.</p>';
            return;
        }

        notificacionesBody.innerHTML = data.data.map(notif => {
            const fecha = new Date(notif.created_at).toLocaleDateString('es-MX', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const emoji = notif.tipo.includes('ALZA') ? '📈' : '📉';
            const claseLeida = notif.leida ? '' : 'style="border-left: 4px solid var(--red); background: rgba(248, 81, 73, 0.05);"';

            return `
                <div class="alert-card" ${claseLeida}>
                    <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                        <span style="font-size: 1.5rem;">${emoji}</span>
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                <h4 style="margin: 0; color: var(--text1); font-size: 0.95rem;">${notif.producto_nombre}</h4>
                                <button onclick="marcarComoLeida(${notif.id}, this)" style="background: none; border: none; color: var(--text2); cursor: pointer; font-size: 1.2rem;" title="Marcar como leída">✓</button>
                            </div>
                            <p style="margin: 0.25rem 0; font-size: 0.85rem; color: var(--blue);">🌎 ${notif.region}</p>
                            <p style="margin: 0.25rem 0; font-size: 0.9rem; color: var(--text);">${notif.mensaje}</p>
                            <div style="display: flex; gap: 1rem; margin-top: 0.5rem; font-size: 0.8rem;">
                                <span>💰 Anterior: $${parseFloat(notif.precio_anterior).toFixed(2)}</span>
                                <span>💲 Actual: $${parseFloat(notif.precio_actual).toFixed(2)}</span>
                            </div>
                            <p style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--text2);">📅 ${fecha}</p>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Error cargando notificaciones:', error);
        notificacionesBody.innerHTML = '<p style="padding: 1rem; color: var(--red);">Error cargando las notificaciones.</p>';
    }
}

async function marcarComoLeida(notifId, button) {
    try {
        const resp = await fetch(API.notificaciones, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: notifId })
        });
        const data = await resp.json();
        if (data.success) {
            button.closest('.alert-card').style.borderLeft = 'none';
            button.style.color = 'var(--green)';
        }
    } catch (error) {
        console.error('Error marcando notificación como leída:', error);
    }
}

async function marcarTodasComoLeidas() {
    try {
        const notificaciones = document.querySelectorAll('.alert-card');
        for (const notif of notificaciones) {
            const button = notif.querySelector('button');
            if (button && button.parentElement.parentElement.querySelector('button').style.color !== 'var(--green)') {
                await marcarComoLeida(
                    parseInt(button.getAttribute('onclick').match(/\d+/)[0]),
                    button
                );
            }
        }
        // Recargar para refrescar
        setTimeout(loadNotificaciones, 500);
    } catch (error) {
        console.error('Error marcando todas como leídas:', error);
    }
}
