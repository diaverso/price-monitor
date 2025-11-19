// Helper para obtener traducciones en dashboard
function getTranslation(key, fallback) {
    if (window.i18n && i18n.t) {
        const translation = i18n.t(key);
        // If translation returns the key itself, it means translation was not found
        if (translation === key) {
            console.warn(`Translation not found for: ${key}, using fallback: ${fallback}`);
            return fallback;
        }
        return translation;
    }
    console.warn(`i18n not available, using fallback for: ${key} => ${fallback}`);
    return fallback;
}

// Exportar funciones para usar en dashboard.js
window.dt = getTranslation;
