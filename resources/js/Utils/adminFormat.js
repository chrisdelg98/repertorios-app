/**
 * Formatting the panel shares across its screens.
 *
 * Kept out of the pages because the same number shown two ways on two screens
 * is how a figure starts looking like two different figures.
 */

export function formatBytes(bytes) {
    const value = Number(bytes) || 0;

    if (value >= 1024 ** 3) return `${(value / 1024 ** 3).toFixed(2)} GB`;
    if (value >= 1024 ** 2) return `${(value / 1024 ** 2).toFixed(1)} MB`;
    if (value >= 1024) return `${Math.round(value / 1024)} KB`;

    return `${value} B`;
}

export function formatMb(megabytes) {
    const value = Number(megabytes) || 0;

    return value >= 1024 ? `${(value / 1024).toFixed(value % 1024 ? 1 : 0)} GB` : `${value} MB`;
}

/**
 * How long since someone was here.
 *
 * Never seen is its own answer, not zero days: it means the band predates the
 * measurement or never came back, and both are worth knowing apart.
 */
export function formatSince(iso) {
    if (!iso) return 'Sin registro de acceso';

    const days = Math.floor((Date.now() - Date.parse(iso)) / 86400000);

    if (days <= 0) return 'Hoy';
    if (days === 1) return 'Ayer';
    if (days < 30) return `Hace ${days} días`;
    if (days < 365) return `Hace ${Math.floor(days / 30)} meses`;

    return `Hace más de un año`;
}

export function formatDate(iso) {
    if (!iso) return '—';

    return new Date(iso).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}
