import { useForm } from '@inertiajs/react';
import Field from '../../components/Field';
import Layout from '../../components/Layout';

export default function Login() {
    const form = useForm({ email: '', password: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <Layout title="Log in">
            <h1>Log in</h1>
            <form onSubmit={submit} className="card">
                <Field label="E-mail" error={form.errors.email}>
                    <input type="email" value={form.data.email} maxLength={254} required autoFocus autoComplete="username"
                        onChange={(e) => form.setData('email', e.target.value)} />
                </Field>
                <Field label="Password" error={form.errors.password}>
                    <input type="password" value={form.data.password} maxLength={128} required autoComplete="current-password"
                        onChange={(e) => form.setData('password', e.target.value)} />
                </Field>
                <button type="submit" disabled={form.processing}>Log in</button>
            </form>
        </Layout>
    );
}
