import { createI18n } from 'vue-i18n';
import enCommon from './en/common.json';
import enAuth from './en/auth.json';
import esCommon from './es/common.json';
import esAuth from './es/auth.json';

function detectLocale() {
    const saved = localStorage.getItem('locale');
    if (saved === 'en' || saved === 'es') return saved;
    const browser = (navigator.language || 'en').toLowerCase();
    return browser.startsWith('es') ? 'es' : 'en';
}

const i18n = createI18n({
    legacy: false,
    locale: detectLocale(),
    fallbackLocale: 'en',
    messages: {
        en: { ...enCommon, auth: enAuth },
        es: { ...esCommon, auth: esAuth },
    },
});

export function setLocale(code) {
    i18n.global.locale.value = code;
    localStorage.setItem('locale', code);
    document.documentElement.lang = code;
}

export default i18n;
