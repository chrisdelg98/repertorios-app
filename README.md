<div align="center">

# 🎶 Repertorios App
### Worship Band Repertoire Platform

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Vite](https://img.shields.io/badge/Vite-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![PWA](https://img.shields.io/badge/PWA-5A0FC8?style=for-the-badge&logo=pwa&logoColor=white)](https://web.dev/progressive-web-apps/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

**Build a full worship setlist in under 30 seconds — and open it from WhatsApp with one tap.**

[English](#-english) · [Español](#-español)

</div>

---

## 🇺🇸 English

### ✨ What is this?

A **mobile-first PWA** built for church worship bands to organize services, repertoires, songs, and rehearsals — without friction. Each band gets its own isolated space, accessible by a simple code + PIN.

### 🎯 Key Features

| | |
|---|---|
| 📅 **Services** | Plan Sunday AM/PM, Wednesday, rehearsals — with schedule templates |
| 🎵 **Songs & Versions** | Build a historical library per band (Original, Live, Acoustic, Cumbia...) |
| 📋 **Repertoires** | Ordered songs, notes, YouTube links, key signatures |
| 🔁 **Duplicate Anything** | Clone an entire service in one tap — only change the date |
| 🔗 **Smart Sharing** | Public signed links + auto-generated WhatsApp messages |
| 🧠 **Smart Suggestions** | Detects duplicates across accents/casing (`Océanos = OCEANOS = oceanos`) |
| 👥 **Multi-Band** | Each band has its own data, code, PIN, and admin |
| 🌐 **Bilingual** | Auto-detects browser language (EN/ES), manually switchable |
| 📱 **Mobile Gestures** | Swipe left = delete · swipe right = duplicate · long press = reorder |
| ♿ **Accessible** | Semantic HTML, ARIA labels, keyboard nav, visible focus |

### 🛠️ Stack

- **Backend:** Laravel 12 (API-first) + MySQL + Sanctum
- **Frontend:** Vue 3 + Vite + Pinia + vue-i18n + PWA
- **Auth:** Email/password for admins · Band code + PIN for members
- **Architecture:** Actions / Services / Policies / Form Requests

### 📁 Project Structure

```
repertorios-app/
├── backend/                 # Laravel 12 API
│   └── app/
│       ├── Actions/
│       ├── Services/
│       ├── Models/
│       ├── Policies/
│       └── Http/
│           ├── Controllers/
│           ├── Requests/
│           └── Resources/
│
└── frontend/                # Vue 3 + Vite PWA
    └── src/
        ├── modules/
        │   ├── services/
        │   ├── songs/
        │   ├── bands/
        │   └── auth/
        ├── components/
        ├── composables/
        ├── pages/
        ├── layouts/
        └── i18n/
            ├── en/
            └── es/
```

### 🚀 Getting Started

#### Prerequisites
- PHP **8.2+**
- Composer **2.x**
- Node **20+**
- MySQL **8+**

#### Backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

#### Frontend
```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

App runs at **http://localhost:5173** · API at **http://localhost:8000**

### 🗺️ Roadmap

- [x] Project spec
- [ ] Backend scaffolding + auth (admin + PIN)
- [ ] Bands, songs, song versions
- [ ] Services, repertoires, schedule templates
- [ ] Duplicate service flow
- [ ] Sharing links + WhatsApp prefill
- [ ] Mobile UX (bottom nav, gestures)
- [ ] PWA install + offline
- [ ] **Future:** Spotify integration, PDF export, musician assignments, notifications

### 📜 License

MIT — see [LICENSE](LICENSE).

---

## 🇪🇸 Español

### ✨ ¿Qué es esto?

Una **PWA mobile-first** para bandas de adoración: organiza servicios, repertorios, canciones y ensayos sin fricción. Cada banda tiene su espacio aislado, accesible con un código y PIN.

### 🎯 Funcionalidades Clave

| | |
|---|---|
| 📅 **Servicios** | Domingo AM/PM, miércoles, ensayos — con plantillas de horario |
| 🎵 **Canciones y versiones** | Biblioteca histórica por banda (Original, Live, Acústica, Cumbia...) |
| 📋 **Repertorios** | Canciones ordenadas, notas, links de YouTube, tonalidad |
| 🔁 **Duplicar todo** | Clona un servicio entero con un tap — solo cambia la fecha |
| 🔗 **Compartir inteligente** | Links públicos firmados + mensaje de WhatsApp prearmado |
| 🧠 **Sugerencias inteligentes** | Detecta duplicados ignorando acentos/mayúsculas (`Océanos = OCEANOS = oceanos`) |
| 👥 **Multi-banda** | Cada banda tiene sus datos, código, PIN y admin |
| 🌐 **Bilingüe** | Detecta el idioma del navegador (EN/ES), cambio manual |
| 📱 **Gestos móviles** | Swipe izquierda = eliminar · derecha = duplicar · long press = reordenar |
| ♿ **Accesible** | HTML semántico, ARIA, navegación por teclado, focus visible |

### 🛠️ Stack

- **Backend:** Laravel 12 (API-first) + MySQL + Sanctum
- **Frontend:** Vue 3 + Vite + Pinia + vue-i18n + PWA
- **Auth:** Email/contraseña para admins · Código de banda + PIN para miembros
- **Arquitectura:** Actions / Services / Policies / Form Requests

### 🚀 Inicio Rápido

#### Requisitos
- PHP **8.2+**
- Composer **2.x**
- Node **20+**
- MySQL **8+**

#### Backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

#### Frontend
```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

App en **http://localhost:5173** · API en **http://localhost:8000**

### 🗺️ Hoja de Ruta

- [x] Especificación del proyecto
- [ ] Scaffolding backend + auth (admin + PIN)
- [ ] Bandas, canciones, versiones
- [ ] Servicios, repertorios, plantillas
- [ ] Flujo de duplicar servicio
- [ ] Links de compartir + WhatsApp
- [ ] UX móvil (bottom nav, gestos)
- [ ] Instalación PWA + modo offline
- [ ] **Futuro:** integración Spotify, export PDF, asignación de músicos, notificaciones

### 📜 Licencia

MIT — ver [LICENSE](LICENSE).

---

<div align="center">

**Made with 🎵 for worship teams**

⭐ If this project helps your band, give it a star!

</div>
