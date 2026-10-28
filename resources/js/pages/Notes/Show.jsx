import { Link, router } from '@inertiajs/react';
import Layout from '../../components/Layout';

export default function Show({ note }) {
    return (
        <Layout title={note.title}>
            <div className="head">
                <h1>{note.title}</h1>
                <Link className="button" href={`/notes/${note.id}/edit`}>Edit</Link>
            </div>
            {/* Rendered as text: a note containing <script> is displayed, never executed (no dangerouslySetInnerHTML anywhere). */}
            <pre className="note">{note.body}</pre>
            <button type="button" className="danger" onClick={() => router.delete(`/notes/${note.id}`)}>Delete note</button>
        </Layout>
    );
}
