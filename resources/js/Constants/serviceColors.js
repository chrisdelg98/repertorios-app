/**
 * Accent palette for services. Picking one is optional — every service starts
 * on `indigo`, the brand colour, and only changes if someone chooses to.
 *
 * Class names are written out in full on purpose. Tailwind scans source files
 * for literal strings, so a built name like `bg-${key}-50` would never make it
 * into the stylesheet.
 *
 * The keys here must stay in sync with Service::COLORS on the PHP side.
 */
export const SERVICE_COLORS = {
    indigo: {
        key: 'indigo',
        tile: 'bg-indigo-50',
        tileHover: 'group-hover:bg-indigo-100',
        icon: 'text-indigo-600',
        swatch: 'bg-indigo-500',
        ring: 'ring-indigo-500',
        // Indigo keeps the original two-tone brand gradient so nothing shifts
        // for services that never touched the setting.
        gradient: 'from-indigo-600 to-violet-600',
        shadow: 'shadow-indigo-200',
    },
    violet: {
        key: 'violet',
        tile: 'bg-violet-50',
        tileHover: 'group-hover:bg-violet-100',
        icon: 'text-violet-600',
        swatch: 'bg-violet-500',
        ring: 'ring-violet-500',
        gradient: 'from-violet-600 to-fuchsia-600',
        shadow: 'shadow-violet-200',
    },
    sky: {
        key: 'sky',
        tile: 'bg-sky-50',
        tileHover: 'group-hover:bg-sky-100',
        icon: 'text-sky-600',
        swatch: 'bg-sky-500',
        ring: 'ring-sky-500',
        gradient: 'from-sky-600 to-cyan-600',
        shadow: 'shadow-sky-200',
    },
    emerald: {
        key: 'emerald',
        tile: 'bg-emerald-50',
        tileHover: 'group-hover:bg-emerald-100',
        icon: 'text-emerald-600',
        swatch: 'bg-emerald-500',
        ring: 'ring-emerald-500',
        gradient: 'from-emerald-600 to-teal-600',
        shadow: 'shadow-emerald-200',
    },
    amber: {
        key: 'amber',
        tile: 'bg-amber-50',
        tileHover: 'group-hover:bg-amber-100',
        icon: 'text-amber-600',
        swatch: 'bg-amber-500',
        ring: 'ring-amber-500',
        gradient: 'from-amber-500 to-orange-500',
        shadow: 'shadow-amber-200',
    },
    orange: {
        key: 'orange',
        tile: 'bg-orange-50',
        tileHover: 'group-hover:bg-orange-100',
        icon: 'text-orange-600',
        swatch: 'bg-orange-500',
        ring: 'ring-orange-500',
        gradient: 'from-orange-600 to-rose-500',
        shadow: 'shadow-orange-200',
    },
    rose: {
        key: 'rose',
        tile: 'bg-rose-50',
        tileHover: 'group-hover:bg-rose-100',
        icon: 'text-rose-600',
        swatch: 'bg-rose-500',
        ring: 'ring-rose-500',
        gradient: 'from-rose-600 to-pink-600',
        shadow: 'shadow-rose-200',
    },
    slate: {
        key: 'slate',
        tile: 'bg-slate-100',
        tileHover: 'group-hover:bg-slate-200',
        icon: 'text-slate-600',
        swatch: 'bg-slate-500',
        ring: 'ring-slate-500',
        gradient: 'from-slate-700 to-slate-600',
        shadow: 'shadow-slate-200',
    },
};

export const DEFAULT_SERVICE_COLOR = 'indigo';

export const SERVICE_COLOR_KEYS = Object.keys(SERVICE_COLORS);

/** Always returns a usable palette entry, even for a null or unknown key. */
export function serviceColor(key) {
    return SERVICE_COLORS[key] ?? SERVICE_COLORS[DEFAULT_SERVICE_COLOR];
}
