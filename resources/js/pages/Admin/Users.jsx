import { router } from '@inertiajs/react';
import Layout from '../../components/Layout';

export default function Users({ users }) {
    return (
        <Layout title="Users">
            <h1>Users</h1>
            <p className="muted">Admins manage accounts; they cannot read other users' notes or files.</p>
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>E-mail</th><th>Role</th><th>Status</th><th /></tr></thead>
                <tbody>
                    {users.map((u) => (
                        <tr key={u.id}>
                            <td>{u.id}</td><td>{u.name}</td><td>{u.email}</td><td>{u.role}</td>
                            <td>{u.active ? 'active' : 'disabled'}</td>
                            <td>
                                <button type="button" className="small" onClick={() => router.patch(`/admin/users/${u.id}/toggle`)}>
                                    {u.active ? 'Disable' : 'Enable'}
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Layout>
    );
}
