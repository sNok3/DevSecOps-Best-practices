// React escapes every value it renders, so user input in labels/errors can never become HTML.
export default function Field({ label, error, children }) {
    return (
        <label>
            {label}
            {children}
            {error && <span className="field-error">{error}</span>}
        </label>
    );
}
