/** Formats an ISO timestamp from the server; the server clock is the only source of time. */
export function formatDate(iso) {
    return new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}
