import Layout from '../../components/Layout';

export default function SecurityLog({ events }) {
    return (
        <Layout title="Security log">
            <h1>Security log (today, newest first)</h1>
            <table className="log">
                <thead><tr><th>Time</th><th>Level</th><th>Event</th><th>Outcome</th><th>User</th><th>IP</th><th>Request</th><th>Details</th></tr></thead>
                <tbody>
                    {events.length === 0 && <tr><td colSpan={8} className="muted">No events today.</td></tr>}
                    {events.map((e, i) => (
                        <tr key={i} className={`lvl-${(e.level_name ?? '').toLowerCase()}`}>
                            <td>{e.datetime}</td>
                            <td>{e.level_name}</td>
                            <td>{e.message}</td>
                            <td>{e.context?.outcome}</td>
                            <td>{e.context?.user_id ?? '-'}</td>
                            <td>{e.context?.ip}</td>
                            <td><code>{(e.context?.request_id ?? '').slice(0, 8)}</code></td>
                            <td><code>{JSON.stringify(e.context?.data ?? {})}</code></td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Layout>
    );
}
