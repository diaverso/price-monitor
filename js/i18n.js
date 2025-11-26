// Sistema de Internacionalización (i18n)
class I18n {
    constructor() {
        this.translations = {};
        this.currentLang = this.getStoredLanguage() || this.detectBrowserLanguage();
        this.defaultLang = 'es';
    }

    detectBrowserLanguage() {
        const browserLang = navigator.language || navigator.userLanguage;
        const langCode = browserLang.split('-')[0];
        return ['es', 'en'].includes(langCode) ? langCode : this.defaultLang;
    }

    getStoredLanguage() {
        return localStorage.getItem('language');
    }

    setStoredLanguage(lang) {
        localStorage.setItem('language', lang);
    }

    async loadTranslations() {
        try {
            const response = await fetch('translations.json');
            if (!response.ok) throw new Error('No se pudo cargar el archivo de traducciones');
            this.translations = await response.json();
            return true;
        } catch (error) {
            console.error('Error cargando traducciones:', error);
            return false;
        }
    }

    t(key) {
        const keys = key.split('.');
        let value = this.translations[this.currentLang];
        for (const k of keys) {
            if (value && value[k]) {
                value = value[k];
            } else {
                console.warn('Traduccion no encontrada: ' + key);
                return key;
            }
        }
        return value;
    }

    async changeLang(newLang) {
        if (!['es', 'en'].includes(newLang)) return false;
        this.currentLang = newLang;
        this.setStoredLanguage(newLang);
        document.documentElement.lang = newLang;
        this.updatePageTitle();

        // Reload dynamic content if loadURLs function exists (dashboard page)
        if (typeof loadURLs === "function") {
            await loadURLs();
        }

        // Reload price chart if loadPriceHistory function exists (price-chart page)
        if (typeof loadPriceHistory === "function" && typeof currentPeriod !== "undefined") {
            await loadPriceHistory(currentPeriod);
        }

        // Apply translations AFTER reloading dynamic content
        this.applyTranslations();

        // Update issues dropdown to reflect new language
        this.createIssuesDropdown();

        return true;
    }

    applyTranslations() {
        document.querySelectorAll('[data-i18n]').forEach(element => {
            const key = element.getAttribute('data-i18n');
            const translation = this.t(key);
            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                if (element.hasAttribute('placeholder')) {
                    element.placeholder = translation;
                } else {
                    element.value = translation;
                }
            } else {
                element.textContent = translation;
            }
        });

        document.querySelectorAll('[data-i18n-placeholder]').forEach(element => {
            const key = element.getAttribute('data-i18n-placeholder');
            element.placeholder = this.t(key);
        });

        // Handle buttons with emoji prefix (data-i18n-text)
        document.querySelectorAll('[data-i18n-text]').forEach(element => {
            const key = element.getAttribute('data-i18n-text');
            const emoji = element.getAttribute('data-emoji') || '';
            const translation = this.t(key);
            element.textContent = emoji + ' ' + translation;
        });
    }

    updatePageTitle() {
        const titleMeta = document.querySelector('meta[name="i18n-title"]');
        if (titleMeta) {
            const key = titleMeta.getAttribute('content');
            document.title = this.t(key);
        }
    }

    async init() {
        await this.loadTranslations();
        document.documentElement.lang = this.currentLang;
        this.applyTranslations();
        this.updatePageTitle();
        this.createLanguageSelector();
        this.createIssuesDropdown();
    }

    createLanguageSelector() {
        let container = document.getElementById('language-selector');
        if (!container) {
            console.warn('No se encontro el contenedor language-selector');
            return;
        }

        container.innerHTML = '';
        
        const languages = [
            { code: 'es', flag: '🇪🇸', name: 'Español' },
            { code: 'en', flag: '🇬🇧', name: 'English' }
        ];

        const wrapper = document.createElement('div');
        wrapper.className = 'language-dropdown';
        wrapper.style.cssText = 'position: relative; display: inline-block;';

        const button = document.createElement('button');
        button.className = 'language-dropdown-btn';
        const currentLang = languages.find(l => l.code === this.currentLang);
        button.innerHTML = currentLang.flag + ' ' + currentLang.code.toUpperCase() + ' ▼';
        button.style.cssText = 'background: rgba(255,255,255,0.2); border: 2px solid white; color: white; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 5px;';

        const dropdown = document.createElement('div');
        dropdown.className = 'language-dropdown-content';
        dropdown.style.cssText = 'display: none; position: absolute; right: 0; top: 100%; margin-top: 5px; background: white; min-width: 150px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 6px; overflow: hidden; z-index: 1000;';

        const self = this;
        languages.forEach(lang => {
            const option = document.createElement('div');
            option.className = 'language-option';
            option.innerHTML = lang.flag + ' ' + lang.name;
            const isActive = self.currentLang === lang.code;
            option.style.cssText = 'padding: 12px 16px; cursor: pointer; transition: background 0.2s; color: #333; font-size: 14px; background: ' + (isActive ? '#f0f0f0' : 'white') + ';';

            option.addEventListener('mouseenter', function() {
                if (!isActive) {
                    this.style.background = '#f8f8f8';
                }
            });

            option.addEventListener('mouseleave', function() {
                if (!isActive) {
                    this.style.background = 'white';
                }
            });

            option.addEventListener('click', async function() {
                if (self.currentLang !== lang.code) {
                    await self.changeLang(lang.code);
                    self.createLanguageSelector();
                }
                dropdown.style.display = 'none';
            });

            dropdown.appendChild(option);
        });

        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = dropdown.style.display === 'block';
            dropdown.style.display = isVisible ? 'none' : 'block';
        });

        button.addEventListener('mouseenter', function() {
            this.style.background = 'rgba(255,255,255,0.3)';
        });

        button.addEventListener('mouseleave', function() {
            this.style.background = 'rgba(255,255,255,0.2)';
        });

        document.addEventListener('click', function() {
            dropdown.style.display = 'none';
        });

        wrapper.appendChild(button);
        wrapper.appendChild(dropdown);
        container.appendChild(wrapper);
    }

    createIssuesDropdown() {
        let container = document.getElementById('issues-selector');
        if (!container) {
            return;
        }

        container.innerHTML = '';

        const issues = [
            {
                key: 'addWebsite',
                icon: '➕',
                url: 'https://github.com/diaverso/price-monitor/issues/new?labels=add-website&template=add-website.md'
            },
            {
                key: 'reportBug',
                icon: '🐛',
                url: 'https://github.com/diaverso/price-monitor/issues/new?labels=bug&template=bug_report.md'
            }
        ];

        const wrapper = document.createElement('div');
        wrapper.className = 'issues-dropdown';
        wrapper.style.cssText = 'position: relative; display: inline-block;';

        const button = document.createElement('button');
        button.className = 'issues-dropdown-btn';
        button.innerHTML = '📋 ' + this.t('common.issues') + ' ▼';
        button.style.cssText = 'background: rgba(255,255,255,0.2); border: 2px solid white; color: white; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 5px;';

        const dropdown = document.createElement('div');
        dropdown.className = 'issues-dropdown-content';
        dropdown.style.cssText = 'display: none; position: absolute; right: 0; top: 100%; margin-top: 5px; background: white; min-width: 180px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 6px; overflow: hidden; z-index: 1000;';

        issues.forEach(issue => {
            const option = document.createElement('a');
            option.className = 'issue-option';
            option.href = issue.url;
            option.target = '_blank';
            option.rel = 'noopener noreferrer';
            option.innerHTML = issue.icon + ' ' + this.t('common.' + issue.key);
            option.style.cssText = 'display: block; padding: 12px 16px; cursor: pointer; transition: background 0.2s; color: #333; font-size: 14px; text-decoration: none; background: white;';

            option.addEventListener('mouseenter', function() {
                this.style.background = '#f8f8f8';
            });

            option.addEventListener('mouseleave', function() {
                this.style.background = 'white';
            });

            dropdown.appendChild(option);
        });

        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = dropdown.style.display === 'block';
            dropdown.style.display = isVisible ? 'none' : 'block';
        });

        button.addEventListener('mouseenter', function() {
            this.style.background = 'rgba(255,255,255,0.3)';
        });

        button.addEventListener('mouseleave', function() {
            this.style.background = 'rgba(255,255,255,0.2)';
        });

        document.addEventListener('click', function() {
            dropdown.style.display = 'none';
        });

        wrapper.appendChild(button);
        wrapper.appendChild(dropdown);
        container.appendChild(wrapper);
    }

    getCurrentLang() {
        return this.currentLang;
    }
}

const i18n = new I18n();
window.i18n = i18n; // Export to global scope
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => i18n.init());
} else {
    i18n.init();
}
