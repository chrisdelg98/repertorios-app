<div align="center">

# 🎶 Repertorios App
### Worship Band Repertoire Platform

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-9553E9?style=for-the-badge&logo=inertia&logoColor=white)](https://inertiajs.com)
[![Vue 3](https://img.shields.io/badge/Vue-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
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

- **Laravel 12** with **Inertia.js** — single project, no separate API
- **Vue 3** + **Vite** + **TailwindCSS** — modern, mobile-first UI
- **MySQL** — relational data store
- **PWA** — installable, offline-friendly
- **Hostinger Shared Hosting compatible** — no Node.js needed at runtime

### 📁 Project Structure

```
repertorios-app/
├── app/
│   ├── Actions/             # Single-purpose operations
│   ├── Services/            # Reusable business logic
│   ├── Models/
│   ├── Policies/
│   └── Http/
│       ├── Controllers/
│       ├── Requests/
│       └── Middleware/
│
├── resources/
│   ├── js/
│   │   ├── Pages/           # Inertia pages (Vue components)
│   │   ├── Components/      # Shared UI components
│   │   ├── Composables/
│   │   ├── Layouts/
│   │   └── i18n/
│   │       ├── en/
│   │       └── es/
│   ├── css/
│   └── views/
│       └── app.blade.php    # Inertia root template
│
├── routes/
│   ├── web.php              # All routes (Inertia)
│   └── auth.php
│
└── database/
    ├── migrations/
    └── seeders/
```

### 🚀 Getting Started

#### Prerequisites
- PHP **8.2+**
- Composer **2.x**
- Node **20+** (build time only)
- MySQL **8+**

#### Install
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build       # or: npm run dev (for hot reload)
php artisan serve
```

Open **http://localhost:8000**

### 🚢 Deployment (Hostinger Shared Hosting)

1. **Build locally** (Node not needed on the server):
   ```bash
   npm run build
   ```
2. Upload the project via FTP / Git deploy, **including `public/build/`**.
3. Point your subdomain to the project's `public/` directory.
4. Run `composer install --no-dev --optimize-autoloader` on the server (SSH).
5. Run `php artisan migrate --force` and `php artisan storage:link`.

Only PHP + MySQL are required at runtime.

### 🗺️ Roadmap

- [x] Project spec
- [x] Setup & Foundation (Laravel + Inertia + Vue + Tailwind)
- [ ] i18n + PWA base
- [ ] Bands + Authentication (admin + PIN)
- [ ] Songs + Versions with smart duplicate detection
- [ ] Services, repertoires, schedule templates
- [ ] Duplicate service flow
- [ ] Sharing links + WhatsApp prefill
- [ ] Mobile UX (bottom nav, gestures)
- [ ] PWA install + offline
- [ ] **Future:** Spotify integration, PDF export, musician assignments

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
| 🧠 **Sugerencias inteligentes** | Detecta duplicados ignorando acentos/mayúsculas |
| 👥 **Multi-banda** | Cada banda tiene sus datos, código, PIN y admin |
| 🌐 **Bilingüe** | Detecta el idioma del navegador (EN/ES), cambio manual |
| 📱 **Gestos móviles** | Swipe izquierda = eliminar · derecha = duplicar · long press = reordenar |
| ♿ **Accesible** | HTML semántico, ARIA, navegación por teclado, focus visible |

### 🛠️ Stack

- **Laravel 12** con **Inertia.js** — un solo proyecto, sin API separada
- **Vue 3** + **Vite** + **TailwindCSS** — UI moderna y mobile-first
- **MySQL** — base de datos relacional
- **PWA** — instalable, funciona offline
- **Compatible con Hostinger Shared Hosting** — no requiere Node.js en producción

### 🚀 Inicio Rápido

#### Requisitos
- PHP **8.2+**
- Composer **2.x**
- Node **20+** (solo para build)
- MySQL **8+**

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build       # o: npm run dev (para hot reload)
php artisan serve
```

Abrí **http://localhost:8000**

### 🚢 Despliegue (Hostinger Shared Hosting)

1. **Compila local** (Node no se necesita en el servidor):
   ```bash
   npm run build
   ```
2. Sube el proyecto por FTP / Git deploy, **incluyendo `public/build/`**.
3. Apunta el subdominio a la carpeta `public/` del proyecto.
4. Por SSH corre `composer install --no-dev --optimize-autoloader`.
5. Corre `php artisan migrate --force` y `php artisan storage:link`.

En producción solo se necesita PHP + MySQL.

### 🗺️ Hoja de Ruta

- [x] Especificación del proyecto
- [x] Setup & Foundation (Laravel + Inertia + Vue + Tailwind)
- [ ] i18n + PWA base
- [ ] Bandas + Auth (admin + PIN)
- [ ] Canciones + versiones con detección de duplicados
- [ ] Servicios, repertorios, plantillas
- [ ] Flujo de duplicar servicio
- [ ] Links de compartir + WhatsApp
- [ ] UX móvil (bottom nav, gestos)
- [ ] Instalación PWA + offline
- [ ] **Futuro:** integración Spotify, export PDF, asignación de músicos

### 📜 Licencia

MIT — ver [LICENSE](LICENSE).

---

<div align="center">

**Made with 🎵 for worship teams**

⭐ If this project helps your band, give it a star!

</div>
