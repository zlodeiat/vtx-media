/** Stateless presentation primitives. No requests, domain calculations or permissions. */
export function PageHeader({
  eyebrow,
  title,
  description,
  actions,
  children,
  className = "",
}) {
  return (
    <header className={`vm-page-header ${className}`}>
      {children || (
        <>
          <div>
            {eyebrow && <span className="vm-eyebrow">{eyebrow}</span>}
            <h2>{title}</h2>
            {description && <p>{description}</p>}
          </div>
          {actions && <div className="vm-action-bar">{actions}</div>}
        </>
      )}
    </header>
  );
}
export function SectionHeader({
  eyebrow,
  title,
  description,
  actions,
  level = 3,
}) {
  const Heading = `h${level}`;
  return (
    <header className="vm-section-header">
      <div>
        {eyebrow && <span className="vm-eyebrow">{eyebrow}</span>}
        <Heading>{title}</Heading>
        {description && <p>{description}</p>}
      </div>
      {actions}
    </header>
  );
}
export function Card({
  eyebrow,
  title,
  description,
  actions,
  children,
  className = "",
  level = 3,
}) {
  return (
    <section className={`vm-card-panel ${className}`}>
      {title && <SectionHeader {...{ eyebrow, title, description, level }} />}
      <div className="vm-card-body">{children}</div>
      {actions && <footer className="vm-card-actions">{actions}</footer>}
    </section>
  );
}
export function MetricCard({
  label,
  value,
  help,
  onClick,
  className = "",
  ...props
}) {
  const Tag = onClick ? "button" : "article";
  return (
    <Tag
      className={`vm-metric ${className}`}
      {...(onClick ? { type: "button", onClick } : {})}
      {...props}
    >
      <strong className="vm-metric-value">{value}</strong>
      <span className="vm-metric-label">{label}</span>
      {help && <small>{help}</small>}
    </Tag>
  );
}
export function StatusBadge({ children, tone = "neutral" }) {
  return (
    <span
      className={`vm-status vm-status-${["neutral", "success", "warning", "danger", "info"].includes(tone) ? tone : "neutral"}`}
    >
      {children}
    </span>
  );
}
export function DataList({ rows, className = "" }) {
  return (
    <dl className={`vm-data-list ${className}`}>
      {rows.map((row, i) => (
        <div key={row.id || i}>
          <dt>{row.label}</dt>
          <dd>
            {row.onClick ? (
              <button
                type="button"
                className="vm-data-action"
                aria-label={row.actionLabel}
                onClick={row.onClick}
              >
                {row.value}
                <span aria-hidden="true"> ↗</span>
              </button>
            ) : (
              row.value
            )}
          </dd>
        </div>
      ))}
    </dl>
  );
}
export function EmptyState({ title, children, action, level = 3 }) {
  const Heading = `h${level}`;
  return (
    <div className="vm-empty-state">
      <Heading>{title}</Heading>
      {children && <p>{children}</p>}
      {action}
    </div>
  );
}
