import { createI18n } from 'vue-i18n';
import en from './en/common.json';
import es from './es/common.json';

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
    messages: { en, es },
});

export function setLocale(code) {
    i18n.global.locale.value = code;
    localStorage.setItem('locale', code);
    document.documentElement.lang = code;
}

export default i18n;
