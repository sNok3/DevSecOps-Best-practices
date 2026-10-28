import { useForm } from '@inertiajs/react';
import Field from '../../components/Field';
import Layout from '../../components/Layout';

export default function Form({ note }) {
    const form = useForm({ title: note?.title ?? '', body: note?.body ?? '' });

    const submit = (e) => {
        e.preventDefault();
        note ? form.put(`/notes/${note.id}`) : form.post('/notes');
    };

    return (
        <Layout title={note ? 'Edit note' : 'New note'}>
            <h1>{note ? 'Edit note' : 'New note'}</h1>
            <form onSubmit={submit} className="card">
                <Field label="Title" error={form.errors.title}>
                    <input value={form.data.title} maxLength={120} required onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label="Text" error={form.errors.body}>
                    <textarea rows={12} value={form.data.body} maxLength={10000} required onChange={(e) => form.setData('body', e.target.value)} />
                </Field>
                <button type="submit" disabled={form.processing}>Save</button>
            </form>
        </Layout>
    );
}
