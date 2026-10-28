import { router, useForm, usePage } from '@inertiajs/react';
import Field from '../../components/Field';
import Layout from '../../components/Layout';
import { formatDate } from '../../utils';

export default function Index({ files }) {
    const { limits } = usePage().props;
    const form = useForm({ file: null });

    const submit = (e) => {
        e.preventDefault();
        form.post('/files', { forceFormData: true, onSuccess: () => form.reset() });
    };

    return (
        <Layout title="Files">
            <h1>My files</h1>
            <form onSubmit={submit} className="card">
                <Field label={`File (${limits.types.join(', ').toUpperCase()}, max ${Math.floor(limits.maxUploadKb / 1024)} MB)`} error={form.errors.file}>
                    <input type="file" accept={limits.types.map((t) => `.${t}`).join(',')} required
                        onChange={(e) => form.setData('file', e.target.files[0] ?? null)} />
                </Field>
                <button type="submit" disabled={form.processing}>Upload encrypted</button>
            </form>
            <table>
                <thead><tr><th>Name</th><th>Size</th><th>Uploaded</th><th /></tr></thead>
                <tbody>
                    {files.length === 0 && <tr><td colSpan={4} className="muted">No files yet.</td></tr>}
                    {files.map((file) => (
                        <tr key={file.id}>
                            {/* A normal link, not an Inertia visit: the server answers with an attachment download. */}
                            <td><a href={`/files/${file.id}`}>{file.name}</a></td>
                            <td>{(file.size / 1024).toFixed(1)} KB</td>
                            <td>{formatDate(file.createdAt)}</td>
                            <td><button type="button" className="danger small" onClick={() => router.delete(`/files/${file.id}`)}>Delete</button></td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Layout>
    );
}
