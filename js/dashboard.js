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
// Cargar URLs del usuario
async function loadURLs() {
    console.log('[loadURLs] Iniciando carga de URLs...');
    const container = document.getElementById('urlsList');

    try {
        console.log('[loadURLs] Haciendo fetch a api/urls.php...');
        const response = await fetch('api/urls.php');
        console.log('[loadURLs] Response recibida:', response.status);
        const data = await response.json();
        console.log('[loadURLs] Data parseada:', data);

        if (!data.success) {
            container.innerHTML = '<div class="error-state" style="padding: 40px; text-align: center; color: #f44336;">' + t('dashboard.errorLoading', 'Error al cargar las URLs') + '</div>';
            showError(t('dashboard.errorLoading', 'Error al cargar las URLs'));
            return;
        }

        await displayURLs(data.data);
    } catch (error) {
        console.error('Error loading URLs:', error);
        container.innerHTML = '<div class="error-state" style="padding: 40px; text-align: center; color: #f44336;">' + t('dashboard.connectionError', 'Error de conexión') + '</div>';
        showError(t('dashboard.connectionError', 'Error de conexión'));
    }
}

// Helper para escapar HTML en atributos
function escapeHtml(text) {
    return text.replace(/&/g, '&amp;')
               .replace(/</g, '&lt;')
               .replace(/>/g, '&gt;')
               .replace(/"/g, '&quot;')
               .replace(/'/g, '&#039;');
}

// Agrupar productos por similitud de nombre
async function groupSimilarProducts(urls) {
    if (urls.length === 0) return [];

    // Primero obtener agrupaciones manuales
    let manualGroups = {};
    try {
        const response = await fetch('api/manual-grouping.php');
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.groups) {
                // Crear un mapa de url_id -> group_id para agrupaciones manuales
                data.groups.forEach(group => {
                    const urlIds = group.url_ids.split(',').map(id => parseInt(id));
                    urlIds.forEach(urlId => {
                        manualGroups[urlId] = group.group_id;
                    });
                });
            }
        } else {
            console.log('No se pudieron cargar agrupaciones manuales (esperado si no hay sesión)');
        }
    } catch (error) {
        console.error('Error loading manual groups:', error);
        // Continuar sin agrupaciones manuales
    }

    const groups = [];
    const processed = new Set();

    urls.forEach((url, index) => {
        if (processed.has(index)) return;

        const group = {
            mainProduct: url,
            variants: [],
            isManualGroup: false,
            groupId: null
        };

        // Si este producto tiene una agrupación manual, buscar todos los productos del mismo grupo
        if (manualGroups[url.id]) {
            const groupId = manualGroups[url.id];
            group.isManualGroup = true;
            group.groupId = groupId;

            urls.forEach((otherUrl, otherIndex) => {
                if (index === otherIndex || processed.has(otherIndex)) return;

                // Si está en el mismo grupo manual, agregarlo
                if (manualGroups[otherUrl.id] === groupId) {
                    group.variants.push(otherUrl);
                    processed.add(otherIndex);
                }
            });
        } else {
            // Si no tiene agrupación manual, usar agrupación automática
            urls.forEach((otherUrl, otherIndex) => {
                if (index === otherIndex || processed.has(otherIndex)) return;

                // No agrupar automáticamente si el otro producto tiene agrupación manual
                if (manualGroups[otherUrl.id]) return;

                // Calcular similitud simple por nombre
                const similarity = calculateNameSimilarity(url.product_name, otherUrl.product_name);

                if (similarity > 0.7) { // 70% de similitud
                    group.variants.push(otherUrl);
                    processed.add(otherIndex);
                }
            });
        }

        processed.add(index);
        groups.push(group);
    });

    return groups;
}

// Calcular similitud entre dos nombres de productos
function calculateNameSimilarity(name1, name2) {
    if (!name1 || !name2) return 0;

    // Normalizar nombres
    const normalize = (str) => str.toLowerCase()
        .replace(/[^\w\s]/g, '')
        .replace(/\s+/g, ' ')
        .trim();

    const n1 = normalize(name1);
    const n2 = normalize(name2);

    // Si son exactamente iguales
    if (n1 === n2) return 1.0;

    const words1 = n1.split(' ');
    const words2 = n2.split(' ');

    // Palabras ignoradas (stop words comunes en descripciones de productos)
    const stopWords = new Set([
        'y', 'o', 'de', 'la', 'el', 'en', 'con', 'para', 'negro', 'blanco',
        'rojo', 'azul', 'gris', 'digital', 'super', 'ultra', 'pro', 'plus',
        'ai', 'assistant', 'alexa', 'google', 'vision', 'atmos', 'dolby'
    ]);

    // Detectar códigos de modelo (alfanuméricos de 6+ caracteres CON números)
    // Debe tener al menos 1 número para ser código de modelo
    const isModelCode = (word) => {
        return /^[a-z0-9]{6,}$/.test(word) && /\d/.test(word);
    };

    // Detectar marcas conocidas
    const knownBrands = new Set(['lg', 'samsung', 'sony', 'panasonic', 'philips', 'toshiba', 'apple', 'xiaomi', 'huawei']);

    // Filtrar y clasificar palabras
    const filterWords = (words) => {
        const modelCodes = [];
        const brands = [];
        const important = [];
        const normal = [];

        words.forEach(word => {
            if (stopWords.has(word)) return; // Ignorar stop words

            if (isModelCode(word)) {
                modelCodes.push(word);
            } else if (knownBrands.has(word)) {
                brands.push(word);
            } else if (word.length >= 4) { // Palabras importantes (4+ chars)
                important.push(word);
            } else if (word.length >= 2) { // Palabras normales
                normal.push(word);
            }
        });

        return { modelCodes, brands, important, normal };
    };

    const classified1 = filterWords(words1);
    const classified2 = filterWords(words2);

    // Calcular coincidencias con pesos
    let score = 0;
    let maxScore = 0;

    // 1. Códigos de modelo (peso: 10.0) - CRÍTICO
    const modelCodesSet1 = new Set(classified1.modelCodes);
    const modelCodesSet2 = new Set(classified2.modelCodes);
    const modelMatches = [...modelCodesSet1].filter(x => modelCodesSet2.has(x)).length;
    const modelTotal = Math.max(modelCodesSet1.size, modelCodesSet2.size);

    if (modelTotal > 0) {
        score += modelMatches * 10.0;
        maxScore += modelTotal * 10.0;
    }

    // 2. Marcas (peso: 5.0) - MUY IMPORTANTE
    const brandsSet1 = new Set(classified1.brands);
    const brandsSet2 = new Set(classified2.brands);
    const brandMatches = [...brandsSet1].filter(x => brandsSet2.has(x)).length;
    const brandTotal = Math.max(brandsSet1.size, brandsSet2.size);

    if (brandTotal > 0) {
        score += brandMatches * 5.0;
        maxScore += brandTotal * 5.0;
    }

    // 3. Palabras importantes (peso: 1.0)
    const importantSet1 = new Set(classified1.important);
    const importantSet2 = new Set(classified2.important);
    const importantMatches = [...importantSet1].filter(x => importantSet2.has(x)).length;
    const importantTotal = Math.max(importantSet1.size, importantSet2.size);

    if (importantTotal > 0) {
        score += importantMatches * 1.0;
        maxScore += importantTotal * 1.0;
    }

    // 4. Palabras normales (peso: 0.5)
    const normalSet1 = new Set(classified1.normal);
    const normalSet2 = new Set(classified2.normal);
    const normalMatches = [...normalSet1].filter(x => normalSet2.has(x)).length;
    const normalTotal = Math.max(normalSet1.size, normalSet2.size);

    if (normalTotal > 0) {
        score += normalMatches * 0.5;
        maxScore += normalTotal * 0.5;
    }

    // Si no hay palabras para comparar, retornar 0
    if (maxScore === 0) return 0;

    // Retornar score normalizado (0-1)
    return score / maxScore;
}

// Variable global para almacenar los grupos
let currentGroups = [];

// Mostrar URLs en el dashboard
async function displayURLs(urls) {
    console.log('[displayURLs] Iniciando con', urls.length, 'URLs');
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

    // Agrupar productos similares
    console.log('[displayURLs] Agrupando productos...');
    currentGroups = await groupSimilarProducts(urls);
    console.log('[displayURLs] Grupos creados:', currentGroups.length);

    container.innerHTML = currentGroups.map((group, groupIndex) => {
        const url = group.mainProduct;
        const hasVariants = group.variants.length > 0;

        return `
        <div class="url-card">
            <div class="url-header">
                <div style="display: flex; gap: 20px; align-items: start; flex: 1;">
                    ${url.product_image ? `
                        <img src="${url.product_image}"
                             alt="${url.product_name || dt('dashboard.product', 'Producto')}"
                             style="width: 120px; height: 120px; object-fit: contain; border-radius: 8px; border: 1px solid #e0e0e0;">
                    ` : ''}
                    <div style="flex: 1;">
                        <div class="url-title">
                            ${url.product_name || dt('dashboard.product', 'Producto sin nombre')}
                            ${hasVariants ? `<span style="background: #4CAF50; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px;">+${group.variants.length} tienda${group.variants.length > 1 ? 's' : ''}</span>` : ''}
                        </div>
                        <a href="${url.url}" target="_blank" class="url-link">${url.url}</a>

                        ${hasVariants ? `
                            <div style="margin-top: 10px;">
                                <button onclick="toggleVariants(${groupIndex})" class="btn" style="background: #2196F3; color: white; padding: 6px 12px; font-size: 13px;">
                                    <span id="toggle-icon-${groupIndex}">▼</span> Ver en otras tiendas
                                </button>
                            </div>
                            <div id="variants-${groupIndex}" style="display: none; margin-top: 15px; padding-left: 20px; border-left: 3px solid #2196F3;">
                                ${group.variants.map(variant =>
                                    '<div style="padding: 10px; background: #f5f5f5; border-radius: 6px; margin-bottom: 8px;">' +
                                        '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">' +
                                            '<div style="flex: 1;">' +
                                                '<div style="font-weight: 600; color: #333; margin-bottom: 4px;">' + getStoreName(variant.url) + '</div>' +
                                                '<a href="' + variant.url + '" target="_blank" style="font-size: 12px; color: #666; text-decoration: none;">' +
                                                    '🔗 ' + truncateUrl(variant.url, 60) +
                                                '</a>' +
                                            '</div>' +
                                            '<div style="text-align: right; margin-left: 15px;">' +
                                                '<div style="font-size: 20px; font-weight: bold; color: #4CAF50;">' +
                                                    (variant.current_price ? '€' + variant.current_price : 'Pendiente') +
                                                '</div>' +
                                                (variant.product_discount ? '<div style="font-size: 11px; color: #f44336; font-weight: 600;">-' + variant.product_discount + '%</div>' : '') +
                                            '</div>' +
                                        '</div>' +
                                        '<div style="display: flex; gap: 8px;">' +
                                            '<button onclick="viewHistory(' + variant.id + ')" style="background: #4CAF50; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; flex: 1;">📊 Ver Historial</button>' +
                                        '</div>' +
                                    '</div>'
                                ).join('')}
                            </div>
                        ` : ''}
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
                <button class="btn btn-edit" onclick="runScrapingForGroup(${groupIndex})" style="background: #2196F3;" data-i18n-text="dashboard.updateData" data-emoji="🔄">🔄 Actualizar Datos</button>
                <button class="btn btn-edit" onclick="searchInOtherStores(this)" data-url-id="${url.id}" data-product-name="${escapeHtml(url.product_name || '')}" style="background: #FF9800;" data-i18n-text="dashboard.searchOtherStores" data-emoji="🔍">🔍 Buscar en otras tiendas</button>
                ${group.isManualGroup ? `
                    <button class="btn btn-edit" onclick="ungroupProduct(${url.id})" style="background: #9C27B0; color: white;" title="Desagrupar este producto">🔓 Desagrupar</button>
                    <button class="btn btn-edit" onclick="ungroupAll(${group.groupId})" style="background: #E91E63; color: white;" title="Desagrupar todos">🔓 Desagrupar Todos</button>
                ` : `
                    <button class="btn btn-edit" onclick="showManualGroupModal(${url.id}, '${escapeHtml(url.product_name || '')}')" style="background: #673AB7; color: white;" title="Agrupar manualmente con otros productos">🔗 Agrupar Manualmente</button>
                `}
                <button class="btn btn-edit" onclick="editURL(${url.id})" data-i18n="common.edit">Editar</button>
                <button class="btn btn-delete" onclick="deleteURL(${url.id})" data-i18n="common.delete">Eliminar</button>
            </div>
        </div>
    `;
    }).join('');

    console.log('[displayURLs] HTML generado, aplicando traducciones...');
    // Apply translations for dynamic content
    applyDynamicTranslations();
    console.log('[displayURLs] Completado');
}

// Toggle mostrar/ocultar variantes de productos
function toggleVariants(groupIndex) {
    const variantsDiv = document.getElementById(`variants-${groupIndex}`);
    const icon = document.getElementById(`toggle-icon-${groupIndex}`);

    if (variantsDiv.style.display === 'none') {
        variantsDiv.style.display = 'block';
        icon.textContent = '▲';
    } else {
        variantsDiv.style.display = 'none';
        icon.textContent = '▼';
    }
}

// Obtener nombre de la tienda desde la URL
function getStoreName(url) {
    try {
        const hostname = new URL(url).hostname.toLowerCase();

        if (hostname.includes('amazon')) return '🛒 Amazon';
        if (hostname.includes('pccomponentes')) return '💻 PcComponentes';
        if (hostname.includes('mediamarkt')) return '📱 MediaMarkt';
        if (hostname.includes('elcorteingles')) return '🏬 El Corte Inglés';
        if (hostname.includes('coolmod')) return '🎮 Coolmod';
        if (hostname.includes('zalando')) return '👕 Zalando';
        if (hostname.includes('mercadona')) return '🛍️ Mercadona';
        if (hostname.includes('fnac')) return '📚 Fnac';

        return '🌐 ' + hostname.replace('www.', '');
    } catch (e) {
        return '🌐 Tienda';
    }
}

// Truncar URL para mostrar
function truncateUrl(url, maxLength) {
    if (url.length <= maxLength) return url;
    return url.substring(0, maxLength - 3) + '...';
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
        // Mostrar indicador de progreso si es una nueva URL (no edición)
        if (!urlId) {
            showScrapingProgress();
        }

        const response = await fetch('api/urls.php', {
            method: urlId ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        // Ocultar indicador de progreso si se mostró
        if (!urlId) {
            hideScrapingProgress();
        }

        if (data.success) {
            closeModal();

            // Si es nueva URL, mostrar mensaje especial sobre scraping
            if (!urlId) {
                showToast(
                    '✅ Producto añadido',
                    'El producto ha sido añadido y la información se ha extraído automáticamente',
                    'success',
                    5000
                );
            } else {
                showToast(
                    t('dashboard.updated', 'Actualizado'),
                    t('dashboard.urlUpdated', 'URL actualizada correctamente'),
                    'success'
                );
            }

            await loadURLs();
        } else {
            showError(data.message);
        }
    } catch (error) {
        // Ocultar indicador de progreso en caso de error
        hideScrapingProgress();
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

// Mostrar indicador de progreso durante scraping inicial
function showScrapingProgress() {
    // Crear modal de progreso si no existe
    let progressModal = document.getElementById('scrapingProgressModal');

    if (!progressModal) {
        progressModal = document.createElement('div');
        progressModal.id = 'scrapingProgressModal';
        progressModal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        `;

        progressModal.innerHTML = `
            <div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); max-width: 500px; width: 90%;">
                <h3 style="color: #007bff; margin: 0 0 20px 0; text-align: center;">
                    🔄 Extrayendo Información del Producto
                </h3>
                <div style="margin: 20px 0;">
                    <div class="progress-bar" style="width: 100%; background: #e9ecef; height: 30px; border-radius: 15px; overflow: hidden;">
                        <div id="scrapingProgressFill" style="width: 0%; height: 100%; background: linear-gradient(90deg, #007bff, #28a745); transition: width 0.3s;"></div>
                    </div>
                </div>
                <p id="scrapingProgressText" style="color: #666; text-align: center; margin: 15px 0;">
                    Conectando con la tienda...
                </p>
                <p style="color: #999; font-size: 12px; text-align: center; margin: 10px 0 0 0;">
                    Esto puede tardar unos segundos
                </p>
            </div>
        `;

        document.body.appendChild(progressModal);
    }

    // Animación de progreso
    const progressFill = document.getElementById('scrapingProgressFill');
    const progressText = document.getElementById('scrapingProgressText');

    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += 3;
        if (progress <= 90) {
            progressFill.style.width = progress + '%';
        }
    }, 100);

    // Guardar el intervalo para poder limpiarlo después
    progressModal.dataset.progressInterval = progressInterval;

    const messages = [
        'Conectando con la tienda...',
        'Descargando página del producto...',
        'Extrayendo nombre del producto...',
        'Extrayendo precio actual...',
        'Extrayendo imagen del producto...',
        'Guardando información...'
    ];

    let msgIndex = 0;
    const msgInterval = setInterval(() => {
        if (msgIndex < messages.length) {
            progressText.textContent = messages[msgIndex];
            msgIndex++;
        }
    }, 800);

    // Guardar el intervalo para poder limpiarlo después
    progressModal.dataset.msgInterval = msgInterval;
}

// Ocultar indicador de progreso
function hideScrapingProgress() {
    const progressModal = document.getElementById('scrapingProgressModal');

    if (progressModal) {
        // Limpiar intervalos
        if (progressModal.dataset.progressInterval) {
            clearInterval(parseInt(progressModal.dataset.progressInterval));
        }
        if (progressModal.dataset.msgInterval) {
            clearInterval(parseInt(progressModal.dataset.msgInterval));
        }

        // Completar la barra de progreso
        const progressFill = document.getElementById('scrapingProgressFill');
        const progressText = document.getElementById('scrapingProgressText');

        if (progressFill) {
            progressFill.style.width = '100%';
        }
        if (progressText) {
            progressText.textContent = '✅ Información extraída correctamente';
        }

        // Esperar un poco antes de cerrar para que el usuario vea el 100%
        setTimeout(() => {
            progressModal.remove();
        }, 1000);
    }
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
        info: 'ℹ',
        warning: '⚠'
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

// Ejecutar scraping manual para un solo producto
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
            return false;
        }

        const data = await response.json();

        if (data.success) {
            return true;
        } else {
            showToast('Error', data.message, 'error');
            return false;
        }
    } catch (error) {
        console.error('Error completo:', error);
        showToast('Error', t('dashboard.couldNotUpdate', 'No se pudo actualizar los datos'), 'error');
        return false;
    }
}

// Ejecutar scraping para un grupo completo (producto principal + variantes)
async function runScrapingForGroup(groupIndex) {
    if (!currentGroups || !currentGroups[groupIndex]) {
        console.error('Grupo no encontrado:', groupIndex);
        return;
    }

    const group = currentGroups[groupIndex];
    const allProducts = [group.mainProduct, ...group.variants];
    const totalProducts = allProducts.length;

    console.log(`Actualizando grupo con ${totalProducts} producto(s)`);

    let successCount = 0;
    let failCount = 0;

    for (let i = 0; i < totalProducts; i++) {
        const product = allProducts[i];
        const storeName = getStoreName(product.url);
        const current = i + 1;

        // Mostrar progreso
        showToast(
            'Actualizando',
            `Actualizando ${storeName} (${current}/${totalProducts})...`,
            'info'
        );

        // Ejecutar scraping para este producto
        const success = await runScraping(product.id);

        if (success) {
            successCount++;
        } else {
            failCount++;
        }

        // Pequeña pausa entre requests
        if (i < totalProducts - 1) {
            await new Promise(resolve => setTimeout(resolve, 500));
        }
    }

    // Mostrar resultado final
    if (failCount === 0) {
        showToast(
            '✅ Completado',
            `Todas las tiendas actualizadas correctamente (${successCount}/${totalProducts})`,
            'success'
        );
    } else if (successCount > 0) {
        showToast(
            '⚠️ Completado con errores',
            `${successCount} actualizadas, ${failCount} fallidas`,
            'warning'
        );
    } else {
        showToast(
            '❌ Error',
            `No se pudo actualizar ninguna tienda`,
            'error'
        );
    }

    // Recargar datos
    setTimeout(async () => {
        await loadURLs();
    }, 1000);
}

// Re-aplicar traducciones después de cargar contenido dinámico
function reapplyTranslations() {
    if (window.i18n) {
        setTimeout(() => {
            i18n.applyTranslations();
        }, 50);
    }
}

// Buscar producto en otras tiendas
function searchInOtherStores(button) {
    const urlId = button.getAttribute('data-url-id');
    const productName = button.getAttribute('data-product-name');

    if (typeof crossSiteSearch !== 'undefined') {
        crossSiteSearch.showSearchModal(urlId, productName);
    } else {
        console.error('crossSiteSearch no está cargado');
        showToast('Error', 'El módulo de búsqueda cross-site no está disponible', 'error');
    }
}

// ===== FUNCIONES DE AGRUPACIÓN MANUAL =====

/**
 * Mostrar modal para agrupar producto manualmente
 */
async function showManualGroupModal(urlId, productName) {
    try {
        // Obtener todos los productos del usuario
        const response = await fetch('api/urls.php');
        const data = await response.json();

        if (!data.success) {
            showToast('Error', 'No se pudieron cargar los productos', 'error');
            return;
        }

        const allProducts = data.data.filter(p => p.id !== urlId);

        if (allProducts.length === 0) {
            showToast('Info', 'No hay otros productos para agrupar', 'info');
            return;
        }

        // Crear modal
        const modal = document.createElement('div');
        modal.id = 'manualGroupModal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        `;

        modal.innerHTML = `
            <div style="background: white; border-radius: 12px; padding: 30px; max-width: 700px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin: 0 0 10px 0; color: #333;">🔗 Agrupar Manualmente</h2>
                <p style="color: #666; margin-bottom: 20px;">Producto: <strong>${productName}</strong></p>
                <p style="color: #666; margin-bottom: 15px; font-size: 14px;">Selecciona los productos que deseas agrupar con este:</p>

                <div id="productsList" style="margin-bottom: 20px; max-height: 400px; overflow-y: auto;">
                    ${allProducts.map(product => `
                        <label style="display: flex; align-items: center; padding: 12px; background: #f5f5f5; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: background 0.2s;"
                               onmouseover="this.style.background='#e8f5e9'"
                               onmouseout="this.style.background='#f5f5f5'">
                            <input type="checkbox"
                                   name="groupProduct"
                                   value="${product.id}"
                                   style="margin-right: 12px; width: 18px; height: 18px; cursor: pointer;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #333; margin-bottom: 4px;">${product.product_name || 'Sin nombre'}</div>
                                <div style="font-size: 12px; color: #666;">${getStoreName(product.url)} - €${product.current_price || '?'}</div>
                            </div>
                        </label>
                    `).join('')}
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button onclick="closeManualGroupModal()"
                            style="padding: 10px 20px; background: #666; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                        Cancelar
                    </button>
                    <button onclick="executeManualGrouping(${urlId})"
                            style="padding: 10px 20px; background: #673AB7; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                        🔗 Agrupar Seleccionados
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Cerrar al hacer clic fuera del modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeManualGroupModal();
            }
        });

    } catch (error) {
        console.error('Error showing manual group modal:', error);
        showToast('Error', 'Error al cargar el modal de agrupación', 'error');
    }
}

/**
 * Cerrar modal de agrupación manual
 */
function closeManualGroupModal() {
    const modal = document.getElementById('manualGroupModal');
    if (modal) {
        modal.remove();
    }
}

/**
 * Ejecutar agrupación manual
 */
async function executeManualGrouping(mainUrlId) {
    const checkboxes = document.querySelectorAll('input[name="groupProduct"]:checked');
    const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

    if (selectedIds.length === 0) {
        showToast('Info', 'Debes seleccionar al menos un producto para agrupar', 'info');
        return;
    }

    // Añadir el producto principal
    const allIds = [mainUrlId, ...selectedIds];

    try {
        const response = await fetch('api/manual-grouping.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'group',
                url_ids: allIds
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('Éxito', `${data.products_count} productos agrupados correctamente`, 'success');
            closeManualGroupModal();
            await loadURLs(); // Recargar dashboard
        } else {
            showToast('Error', data.error || 'No se pudo agrupar los productos', 'error');
        }
    } catch (error) {
        console.error('Error grouping products:', error);
        showToast('Error', 'Error al agrupar productos', 'error');
    }
}

/**
 * Desagrupar un producto específico
 */
async function ungroupProduct(urlId) {
    if (!confirm('¿Deseas desagrupar este producto? Se mostrará como una tarjeta separada.')) {
        return;
    }

    try {
        const response = await fetch('api/manual-grouping.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'ungroup',
                url_id: urlId
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('Éxito', 'Producto desagrupado correctamente', 'success');
            await loadURLs(); // Recargar dashboard
        } else {
            showToast('Error', data.error || 'No se pudo desagrupar el producto', 'error');
        }
    } catch (error) {
        console.error('Error ungrouping product:', error);
        showToast('Error', 'Error al desagrupar producto', 'error');
    }
}

/**
 * Desagrupar todos los productos de un grupo
 */
async function ungroupAll(groupId) {
    if (!confirm('¿Deseas desagrupar TODOS los productos de este grupo? Cada producto se mostrará como una tarjeta separada.')) {
        return;
    }

    try {
        const response = await fetch('api/manual-grouping.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'ungroup_all',
                group_id: groupId
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('Éxito', 'Todos los productos desagrupados correctamente', 'success');
            await loadURLs(); // Recargar dashboard
        } else {
            showToast('Error', data.error || 'No se pudo desagrupar los productos', 'error');
        }
    } catch (error) {
        console.error('Error ungrouping all products:', error);
        showToast('Error', 'Error al desagrupar productos', 'error');
    }
}
