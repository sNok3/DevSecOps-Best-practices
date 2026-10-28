import { Link } from '@inertiajs/react';

const messages = {
    403: 'You are not allowed to do this.',
    404: 'This page does not exist.',
    429: 'Too many requests. Please wait a moment.',
    500: 'Something went wrong on our side.',
    503: 'The vault is down for maintenance.',
};

export default function Error({ status, requestId }) {
    return (
        <main className="error">
            <h1>{status}</h1>
            <p>{messages[status] ?? 'Error'}</p>
            {/* Only a reference for support; details stay in the server log under this id. */}
            <p className="muted">Reference: <code>{requestId}</code></p>
            <p><Link href="/">Back to the vault</Link></p>
        </main>
    );
}
