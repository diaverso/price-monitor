// Verificar autenticación al cargar
window.addEventListener('DOMContentLoaded', async () => {
    await checkAuth();
    await loadURLs();
});


// Helper to get translated text
function t(key, fallback = '') {
    if (window.i18n && window.i18n.t) {
        const translation = i18n.t(key);
        console.log(`t('${key}') => '${translation}' (lang: ${i18n.currentLang})`);
        return translation;
    }
    console.warn(`t('${key}') => fallback: '${fallback}' (i18n not available)`);
    return fallback;
}

// Verificar si el usuario está autenticado
async function checkAuth() {
    try {
        const response = await fetch('api/auth.php?action=check');
        const data = await response.json();

        if (!data.success) {
            window.location.href = 'login.html';
            return;
        }

        document.getElementById('userInfo').textContent = `Hola, ${data.data.username}`;
    } catch (error) {
        window.location.href = 'login.html';
    }
}

// Cerrar sesión
async function logout() {
    try {
        await fetch('api/auth.php?action=logout');
        window.location.href = 'login.html';
    } catch (error) {
        console.error('Error al cerrar sesión:', error);
    }
}

// Cargar URLs del usuario
async function loadURLs() {
    try {
        const response = await fetch('api/urls.php');
        const data = await response.json();

        if (!data.success) {
            showError(t('dashboard.errorLoading', 'Error al cargar las URLs'));
            return;
        }

        displayURLs(data.data);
    } catch (error) {
        showError(t('dashboard.connectionError', 'Error de conexión'));
    }
}

// Mostrar URLs en el dashboard
function displayURLs(urls) {
    const container = document.getElementById('urlsList');

    if (urls.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <h3>${dt('dashboard.noUrls', 'No hay URLs monitorizadas')}</h3>
                <p>${dt('dashboard.addUrl', 'Haz clic en "Agregar Nueva URL" para comenzar')}</p>
            </div>
        `;
        return;
    }

    container.innerHTML = urls.map(url => `
        <div class="url-card">
            <div class="url-header">
                <div style="display: flex; gap: 20px; align-items: start; flex: 1;">
                    ${url.product_image ? `
                        <img src="${url.product_image}"
                             alt="${url.product_name || dt('dashboard.product', 'Producto')}"
                             style="width: 120px; height: 120px; object-fit: contain; border-radius: 8px; border: 1px solid #e0e0e0;">
                    ` : ''}
                    <div style="flex: 1;">
                        <div class="url-title">${url.product_name || dt('dashboard.product', 'Producto sin nombre')}</div>
                        <a href="${url.url}" target="_blank" class="url-link">${url.url}</a>
                    </div>
                </div>
                <span class="status-badge status-${url.status}">${getStatusText(url.status)}</span>
            </div>

            <div class="price-info">
                <div class="price-item">
                    <div class="price-label" data-i18n="dashboard.currentPrice">Precio Actual</div>
                    <div class="price-value price-current">
                        ${url.current_price ? '€' + url.current_price : dt('common.loading', 'Pendiente')}
                        ${url.product_discount ? `<span style="display: block; font-size: 12px; color: #f44336; font-weight: 600;">-${url.product_discount}% <span data-i18n="dashboard.discount">descuento</span></span>` : ''}
                    </div>
                </div>
                ${url.product_original_price ? `
                <div class="price-item">
                    <div class="price-label" data-i18n="dashboard.price">Precio Original</div>
                    <div class="price-value" style="color: #999; text-decoration: line-through;">
                        €${url.product_original_price}
                    </div>
                </div>
                ` : ''}
                <div class="price-item">
                    <div class="price-label" data-i18n="dashboard.targetPrice">Precio Objetivo</div>
                    <div class="price-value price-target">€${url.target_price}</div>
                </div>
                <div class="price-item">
                    <div class="price-label" data-i18n="dashboard.lastCheck">Última Verificación</div>
                    <div style="font-size: 14px; color: #666; margin-top: 8px;">
                        ${url.last_checked ? formatDate(url.last_checked) : dt('dashboard.lastCheck', 'Nunca')}
                    </div>
                </div>
            </div>

            <div class="notifications">
                <strong style="font-size: 14px; color: #666;"><span data-i18n="dashboard.notifications">Notificaciones</span>:</strong><br>
                ${url.notifications.map(n => `
                    <span class="notification-badge">${getNotificationIcon(n.method)} ${n.method}</span>
                `).join('')}
            </div>

            <div class="card-actions">
                <button class="btn btn-edit" onclick="viewHistory(${url.id})" style="background: #4CAF50;" data-i18n-text="dashboard.history" data-emoji="📊">📊 Ver Historial</button>
                <button class="btn btn-edit" onclick="runScraping(${url.id})" style="background: #2196F3;" data-i18n-text="dashboard.updateData" data-emoji="🔄">🔄 Actualizar Datos</button>
                <button class="btn btn-edit" onclick="editURL(${url.id})" data-i18n="common.edit">Editar</button>
                <button class="btn btn-delete" onclick="deleteURL(${url.id})" data-i18n="common.delete">Eliminar</button>
            </div>
        </div>
    `).join('');
    
    // Apply translations for dynamic content
    applyDynamicTranslations();
}

// Helper to apply translations to dynamic content
function applyDynamicTranslations() {
    if (window.i18n && window.i18n.applyTranslations) {
        setTimeout(() => {
            i18n.applyTranslations();
            // Handle buttons with emoji prefix (data-i18n-text)
            document.querySelectorAll('[data-i18n-text]').forEach(btn => {
                const key = btn.getAttribute('data-i18n-text');
                const emoji = btn.getAttribute('data-emoji') || '';
                const translation = i18n.t(key);
                btn.textContent = emoji + ' ' + translation;
            });
        }, 10);
    }
}

// Mostrar modal para agregar URL
function showAddModal() {
    document.getElementById('modalTitle').textContent = 'Agregar Nueva URL';
    document.getElementById('urlForm').reset();
    document.getElementById('urlId').value = '';
    document.getElementById('urlModal').classList.add('show');
}

// Cerrar modal
function closeModal() {
    document.getElementById('urlModal').classList.remove('show');
}

// Editar URL
async function editURL(id) {
    try {
        const response = await fetch('api/urls.php');
        const data = await response.json();

        if (!data.success) {
            showError(t('dashboard.errorLoading', 'Error al cargar la URL'));
            return;
        }

        const url = data.data.find(u => u.id === id);
        if (!url) {
            showError(t('dashboard.urlNotFound', 'URL no encontrada'));
            return;
        }

        // Llenar el formulario
        document.getElementById('modalTitle').textContent = 'Editar URL';
        document.getElementById('urlId').value = url.id;
        document.getElementById('urlInput').value = url.url;
        document.getElementById('productName').value = url.product_name || '';
        document.getElementById('targetPrice').value = url.target_price;
        document.getElementById('targetDiscount').value = url.target_discount_percentage || '';

        // Limpiar notificaciones
        document.querySelectorAll('.notification-item input[type="checkbox"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('.notification-item input[type="text"], .notification-item input[type="email"], .notification-item input[type="tel"]').forEach(input => input.value = '');

        // Llenar notificaciones
        url.notifications.forEach(notif => {
            if (notif.method === 'email') {
                document.getElementById('notifEmail').checked = true;
                document.getElementById('emailInput').value = notif.contact_info;
            } else if (notif.method === 'telegram') {
                document.getElementById('notifTelegram').checked = true;
                document.getElementById('telegramInput').value = notif.contact_info;
            } else if (notif.method === 'whatsapp') {
                document.getElementById('notifWhatsapp').checked = true;
                document.getElementById('whatsappInput').value = notif.contact_info;
            } else if (notif.method === 'sms') {
                document.getElementById('notifSMS').checked = true;
                document.getElementById('smsInput').value = notif.contact_info;
            }
        });

        document.getElementById('urlModal').classList.add('show');
    } catch (error) {
        showError(t('dashboard.errorLoading', 'Error al cargar la URL'));
    }
}

// Eliminar URL
async function deleteURL(id) {
    if (!confirm('¿Estás seguro de que quieres eliminar esta URL?')) {
        return;
    }

    try {
        const response = await fetch(`api/urls.php?id=${id}`, {
            method: 'DELETE'
        });

        const data = await response.json();

        if (data.success) {
            await loadURLs();
            showToast(t('dashboard.deleted', 'Eliminado'), t('dashboard.urlDeleted', 'URL eliminada correctamente'), 'success');
        } else {
            showError(data.message);
        }
    } catch (error) {
        showError(t('dashboard.errorDeleting', 'Error al eliminar la URL'));
    }
}

// Enviar formulario
document.getElementById('urlForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const urlId = document.getElementById('urlId').value;
    const url = document.getElementById('urlInput').value;
    const productName = document.getElementById('productName').value;
    const targetPrice = document.getElementById('targetPrice').value;

    const targetDiscount = document.getElementById('targetDiscount').value;
    // Recopilar notificaciones
    const notifications = [];

    if (document.getElementById('notifEmail').checked) {
        const email = document.getElementById('emailInput').value;
        if (email) notifications.push({ method: 'email', contact_info: email });
    }

    if (document.getElementById('notifTelegram').checked) {
        const telegram = document.getElementById('telegramInput').value;
        if (telegram) notifications.push({ method: 'telegram', contact_info: telegram });
    }

    if (document.getElementById('notifWhatsapp').checked) {
        const whatsapp = document.getElementById('whatsappInput').value;
        if (whatsapp) notifications.push({ method: 'whatsapp', contact_info: whatsapp });
    }

    if (document.getElementById('notifSMS').checked) {
        const sms = document.getElementById('smsInput').value;
        if (sms) notifications.push({ method: 'sms', contact_info: sms });
    }

    if (notifications.length === 0) {
        showToast(t('dashboard.attention', 'Atención'), t('dashboard.selectNotificationMethod', 'Debes seleccionar al menos un método de notificación'), 'error');
        return;
    }

    const payload = {
        url,
        product_name: productName,
        target_price: targetPrice,
        target_discount_percentage: targetDiscount || null,
        notifications
    };

    if (urlId) {
        payload.id = urlId;
        payload.status = 'active';
    }

    try {
        const response = await fetch('api/urls.php', {
            method: urlId ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (data.success) {
            closeModal();
            await loadURLs();
            showToast(urlId ? t('dashboard.updated', 'Actualizado') : t('dashboard.added', 'Agregado'), urlId ? t('dashboard.urlUpdated', 'URL actualizada correctamente') : t('dashboard.urlAdded', 'URL agregada correctamente'), 'success');
        } else {
            showError(data.message);
        }
    } catch (error) {
        showError(t('dashboard.errorAdding', 'Error al guardar la URL'));
    }
});

// Funciones auxiliares
function getStatusText(status) {
    const statusMap = {
        'active': 'Activo',
        'paused': 'Pausado',
        'error': 'Error'
    };
    return statusMap[status] || status;
}

function getNotificationIcon(method) {
    const icons = {
        'email': '📧',
        'telegram': '✈️',
        'whatsapp': '💬',
        'sms': '📱'
    };
    return icons[method] || '🔔';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showError(message) {
    showToast('Error', message, 'error');
}

function showToast(title, message, type = 'info') {
    const container = document.getElementById('toastContainer');

    // Limitar a máximo 3 toasts consecutivos
    const currentToasts = container.querySelectorAll('.toast');
    if (currentToasts.length >= 3) {
        // Eliminar el más antiguo (el primero)
        const oldestToast = currentToasts[0];
        oldestToast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => oldestToast.remove(), 300);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    const icons = {
        success: '✓',
        error: '✕',
        info: 'ℹ'
    };

    toast.innerHTML = `
        <div class="toast-icon">${icons[type] || 'ℹ'}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
    `;

    container.appendChild(toast);

    // Auto-eliminar después de 15 segundos
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 15000);
}

// Cerrar modal al hacer clic fuera
document.getElementById('urlModal').addEventListener('click', (e) => {
    if (e.target.id === 'urlModal') {
        closeModal();
    }
});

// Ver historial de precios
function viewHistory(urlId) {
    window.location.href = `price-chart.html?id=${urlId}`;
}

// Ejecutar scraping manual
async function runScraping(urlId) {
    showToast(t('dashboard.updating', 'Actualizando'), t('dashboard.gettingData', 'Obteniendo datos del producto...'), 'info');

    try {
        const response = await fetch('api/scrape.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ url_id: urlId })
        });

        if (!response.ok) {
            const text = await response.text();
            console.error('Error HTTP:', response.status, text);
            showToast('Error', t('dashboard.serverError', 'Error del servidor') + ': ' + response.status, 'error');
            return;
        }

        const data = await response.json();

        if (data.success) {
            showToast(t('dashboard.updated', '¡Actualizado!'), t('dashboard.dataUpdatedSuccess', 'Los datos se han actualizado correctamente'), 'success');
            await loadURLs();
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (error) {
        console.error('Error completo:', error);
        showToast(t('dashboard.connectionError', t('dashboard.connectionError', 'Error de conexión')), t('dashboard.couldNotUpdate', 'No se pudo actualizar los datos'), 'error');
    }
}

// Re-aplicar traducciones después de cargar contenido dinámico
function reapplyTranslations() {
    if (window.i18n) {
        setTimeout(() => {
            i18n.applyTranslations();
        }, 50);
    }
}
