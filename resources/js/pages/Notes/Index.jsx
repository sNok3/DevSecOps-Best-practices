import { Link } from '@inertiajs/react';
import Layout from '../../components/Layout';
import { formatDate } from '../../utils';

export default function Index({ notes }) {
    return (
        <Layout title="Notes">
            <div className="head">
                <h1>My notes</h1>
                <Link className="button" href="/notes/create">New note</Link>
            </div>
            {notes.length === 0 && <p className="muted">No notes yet.</p>}
            {notes.map((note) => (
                <Link key={note.id} className="row" href={`/notes/${note.id}`}>
                    {note.title} <small>{formatDate(note.updatedAt)}</small>
                </Link>
            ))}
        </Layout>
    );
}
