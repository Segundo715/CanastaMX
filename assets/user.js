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
