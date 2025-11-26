/**
 * Sistema de Cuenta Atrás para Descuentos de Amazon
 */

class CountdownTimer {
    constructor(targetTime, elementId) {
        this.targetTime = new Date(targetTime).getTime();
        this.elementId = elementId;
        this.interval = null;
    }

    start() {
        this.update();
        this.interval = setInterval(() => this.update(), 1000);
    }

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
        }
    }

    update() {
        const now = new Date().getTime();
        const distance = this.targetTime - now;

        if (distance < 0) {
            this.displayExpired();
            this.stop();
            return;
        }

        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        this.display(hours, minutes, seconds);
    }

    display(hours, minutes, seconds) {
        const element = document.getElementById(this.elementId);
        if (element) {
            const hoursStr = String(hours).padStart(2, '0');
            const minutesStr = String(minutes).padStart(2, '0');
            const secondsStr = String(seconds).padStart(2, '0');

            element.innerHTML = `
                <span class="countdown-badge">
                    ⏰ Oferta termina en:
                    <strong>${hoursStr}:${minutesStr}:${secondsStr}</strong>
                </span>
            `;
        }
    }

    displayExpired() {
        const element = document.getElementById(this.elementId);
        if (element) {
            element.innerHTML = `
                <span class="countdown-badge expired">
                    ⚠️ Oferta expirada
                </span>
            `;
        }
    }
}

// Almacenar todos los timers activos
window.activeCountdowns = window.activeCountdowns || {};

/**
 * Iniciar cuenta atrás para un producto
 * @param {string} targetTime - Timestamp ISO 8601 (ej: "2025-10-19T21:59:59Z")
 * @param {string} elementId - ID del elemento donde mostrar el countdown
 */
function startCountdown(targetTime, elementId) {
    // Detener countdown previo si existe
    if (window.activeCountdowns[elementId]) {
        window.activeCountdowns[elementId].stop();
    }

    // Crear y iniciar nuevo countdown
    const countdown = new CountdownTimer(targetTime, elementId);
    countdown.start();

    // Guardar referencia
    window.activeCountdowns[elementId] = countdown;
}

/**
 * Detener todas las cuentas atrás activas
 */
function stopAllCountdowns() {
    for (let elementId in window.activeCountdowns) {
        window.activeCountdowns[elementId].stop();
    }
    window.activeCountdowns = {};
}

/**
 * Formatear timestamp para mostrar fecha legible
 * @param {string} targetTime - Timestamp ISO 8601
 * @returns {string} Fecha formateada
 */
function formatCountdownDate(targetTime) {
    const date = new Date(targetTime);
    const options = {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleString('es-ES', options);
}

// Estilos CSS para el countdown badge
if (!document.getElementById('countdown-styles')) {
    const style = document.createElement('style');
    style.id = 'countdown-styles';
    style.textContent = `
        .countdown-badge {
            display: inline-block;
            padding: 6px 12px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            margin-top: 8px;
            animation: pulse 2s ease-in-out infinite;
        }

        .countdown-badge strong {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .countdown-badge.expired {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            animation: none;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(255, 107, 107, 0.7);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(255, 107, 107, 0);
            }
        }

        .discount-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            margin: 10px 0;
            border-radius: 4px;
        }

        .discount-info .discount-label {
            font-size: 12px;
            color: #856404;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .discount-info .discount-value {
            font-size: 20px;
            color: #d39e00;
            font-weight: 700;
        }
    `;
    document.head.appendChild(style);
}
