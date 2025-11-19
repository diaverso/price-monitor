// Obtener URL ID desde la query string
const urlParams = new URLSearchParams(window.location.search);
const urlId = urlParams.get('id');

let priceChart = null;
let currentPeriod = 30;

// Helper to get translated text
function t(key, fallback = '') {
    if (window.i18n && window.i18n.t) {
        return i18n.t(key);
    }
    return fallback;
}

// Cargar datos al iniciar
window.addEventListener('DOMContentLoaded', async () => {
    if (!urlId) {
        showError(t('priceChart.noDataAvailable', 'No se especificó ID de URL'));
        return;
    }

    await loadPriceHistory(currentPeriod);
    setupPeriodButtons();
});

// Configurar botones de período
function setupPeriodButtons() {
    const buttons = document.querySelectorAll('.period-btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', async () => {
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            currentPeriod = parseInt(btn.dataset.period);
            await loadPriceHistory(currentPeriod);
        });
    });
}

// Cargar historial de precios
async function loadPriceHistory(period) {
    try {
        const response = await fetch(`api/price-history.php?url_id=${urlId}&period=${period}`);
        const data = await response.json();

        if (!data.success) {
            showError(data.message);
            return;
        }

        hideLoading();
        displayData(data.data);
    } catch (error) {
        showError(t('priceChart.errorLoadingHistory', 'Error al cargar el historial de precios'));
    }
}

// Mostrar datos
function displayData(data) {
    const { url_info, history, stats } = data;

    // Actualizar información del producto
    document.getElementById('productName').textContent = url_info.product_name || 'Producto sin nombre';
    document.getElementById('productUrl').textContent = url_info.url;
    document.getElementById('productUrl').href = url_info.url;

    // Mostrar imagen del producto si existe
    if (url_info.product_image) {
        const productImage = document.getElementById('productImage');
        productImage.src = url_info.product_image;
        productImage.style.display = 'block';
    }

    // Mostrar descuento y precio original si existen
    const productMeta = document.getElementById('productMeta');
    let metaHTML = '';

    if (url_info.product_discount) {
        metaHTML += '<span class="discount-badge">-' + url_info.product_discount + '% descuento</span>';
    }

    if (url_info.product_original_price) {
        metaHTML += '<span class="original-price">Precio original: €' + url_info.product_original_price + '</span>';
    }

    productMeta.innerHTML = metaHTML;

    // Actualizar estadísticas
    document.getElementById('currentPrice').textContent = url_info.current_price ? `€${url_info.current_price}` : 'Pendiente';
    document.getElementById('targetPrice').textContent = `€${url_info.target_price}`;
    document.getElementById('minPrice').textContent = stats.min ? `€${stats.min}` : '--';
    document.getElementById('maxPrice').textContent = stats.max ? `€${stats.max}` : '--';
    document.getElementById('avgPrice').textContent = stats.avg ? `€${stats.avg}` : '--';

    // Cambio de precio
    const changeAmount = document.getElementById('changeAmount');
    const changePercent = document.getElementById('changePercent');

    if (stats.change_absolute !== null) {
        const isNegative = stats.change_absolute < 0;
        const absChange = Math.abs(stats.change_absolute);

        changeAmount.textContent = `${isNegative ? '-' : '+'}€${absChange.toFixed(2)}`;
        changeAmount.parentElement.classList.add(isNegative ? 'negative' : 'positive');

        changePercent.textContent = `${isNegative ? '↓' : '↑'} ${Math.abs(stats.change_percent)}%`;
        changePercent.classList.add(isNegative ? 'negative' : 'positive');
    }

    // Crear gráfica
    createChart(history, url_info.target_price);

    // Mostrar contenedor
    document.getElementById('contentContainer').style.display = 'block';
}

// Crear gráfica
function createChart(history, targetPrice) {
    const ctx = document.getElementById('priceChart').getContext('2d');

    // Preparar datos
    const labels = history.map(h => {
        const date = new Date(h.checked_at);
        return date.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' });
    });

    const prices = history.map(h => h.price);

    // Destruir gráfica anterior si existe
    if (priceChart) {
        priceChart.destroy();
    }

    // Crear nueva gráfica
    priceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: t('priceChart.price', 'Precio'),
                    data: prices,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                },
                {
                    label: t('priceChart.targetPrice', 'Precio Objetivo'),
                    data: Array(labels.length).fill(targetPrice),
                    borderColor: '#4CAF50',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    fill: false,
                    pointRadius: 0,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 2.5,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += '€' + context.parsed.y.toFixed(2);
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: function(value) {
                            return '€' + value.toFixed(2);
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
}

// Ocultar loading
function hideLoading() {
    document.getElementById('loadingContainer').style.display = 'none';
}

// Mostrar error
function showError(message) {
    hideLoading();
    document.getElementById('errorContainer').textContent = message;
    document.getElementById('errorContainer').style.display = 'block';
}
