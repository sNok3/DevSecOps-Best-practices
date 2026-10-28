import { useForm } from '@inertiajs/react';
import Field from '../../components/Field';
import Layout from '../../components/Layout';

export default function Register() {
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
    const set = (key) => (e) => form.setData(key, e.target.value);

    const submit = (e) => {
        e.preventDefault();
        form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    // The client-side limits only help the user; the server validates everything again (RegisterRequest).
    return (
        <Layout title="Register">
            <h1>Create an account</h1>
            <form onSubmit={submit} className="card">
                <Field label="Name" error={form.errors.name}>
                    <input value={form.data.name} maxLength={60} required autoComplete="name" onChange={set('name')} />
                </Field>
                <Field label="E-mail" error={form.errors.email}>
                    <input type="email" value={form.data.email} maxLength={254} required autoComplete="email" onChange={set('email')} />
                </Field>
                <Field label="Password (at least 12 characters, a passphrase is fine)" error={form.errors.password}>
                    <input type="password" value={form.data.password} minLength={12} maxLength={128} required autoComplete="new-password" onChange={set('password')} />
                </Field>
                <Field label="Repeat password">
                    <input type="password" value={form.data.password_confirmation} maxLength={128} required autoComplete="new-password" onChange={set('password_confirmation')} />
                </Field>
                <button type="submit" disabled={form.processing}>Register</button>
            </form>
        </Layout>
    );
}
