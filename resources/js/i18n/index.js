import { createI18n } from 'vue-i18n';
import enCommon from './en/common.json';
import enAuth from './en/auth.json';
import enServices from './en/services.json';
import enSongs from './en/songs.json';
import enSettings from './en/settings.json';
import esCommon from './es/common.json';
import esAuth from './es/auth.json';
import esServices from './es/services.json';
import esSongs from './es/songs.json';
import esSettings from './es/settings.json';

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
        en: { ...enCommon, auth: enAuth, services: enServices, songs: enSongs, settings: enSettings },
        es: { ...esCommon, auth: esAuth, services: esServices, songs: esSongs, settings: esSettings },
    },
});

export function setLocale(code) {
    i18n.global.locale.value = code;
    localStorage.setItem('locale', code);
    document.documentElement.lang = code;
}

export default i18n;
