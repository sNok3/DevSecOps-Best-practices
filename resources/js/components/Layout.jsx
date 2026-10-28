import { Link, router, usePage } from '@inertiajs/react';

export default function Layout({ title, children }) {
    const { auth, flash, errors } = usePage().props;
    const user = auth.user;

    return (
        <>
            <header className="bar">
                <Link className="brand" href="/">DevSecOps Vault</Link>
                <nav>
                    {user ? (
                        <>
                            <Link href="/notes">Notes</Link>
                            <Link href="/files">Files</Link>
                            {user.isAdmin && <Link href="/admin/users">Users</Link>}
                            {user.isAdmin && <Link href="/admin/security-log">Security log</Link>}
                            <button type="button" className="link" onClick={() => router.post('/logout')}>
                                Log out ({user.name})
                            </button>
                        </>
                    ) : (
                        <>
                            <Link href="/login">Log in</Link>
                            <Link href="/register">Register</Link>
                        </>
                    )}
                </nav>
            </header>
            <main>
                {title && <title>{`${title} · DevSecOps Vault`}</title>}
                {flash.status && <p className="flash">{flash.status}</p>}
                {errors.user && <p className="errors">{errors.user}</p>}
                {children}
            </main>
        </>
    );
}
