/** Small, request-free patterns for aligned records and truthful visual ratios. */
import { StatusBadge } from "./design";
const { __ } = wp.i18n;
export function Progress({ value, total = 100, label, tone = "info" }) {
  const known =
    Number.isFinite(Number(value)) &&
    Number.isFinite(Number(total)) &&
    Number(total) > 0 &&
    value !== null;
  const percent = known
    ? Math.max(0, Math.min(100, (Number(value) / Number(total)) * 100))
    : 0;
  return (
    <div
      className={`vm-progress vm-tone-${tone}`}
      role={known ? "progressbar" : "img"}
      aria-label={label}
      aria-valuemin={known ? 0 : undefined}
      aria-valuemax={known ? Number(total) : undefined}
      aria-valuenow={
        known ? Math.max(0, Math.min(Number(total), Number(value))) : undefined
      }
    >
      <span style={{ width: `${percent}%` }} />
    </div>
  );
}
export function DistributionBar({ segments, total, label }) {
  const sum = segments.reduce(
    (n, s) => n + Math.max(0, Number(s.value) || 0),
    0,
  );
  // Never make an overlapping/incomplete set look like a complete partition.
  if (!(Number(total) > 0) || sum > Number(total)) return null;
  return (
    <div
      className="vm-distribution"
      role="img"
      aria-label={`${label}: ${segments.map((s) => `${s.label} ${s.value}`).join(", ")}`}
    >
      {segments.map((s) => (
        <span
          key={s.id || s.label}
          className={`vm-tone-${s.tone || "neutral"}`}
          style={{
            width: `${(Math.max(0, Number(s.value) || 0) / Number(total)) * 100}%`,
          }}
        />
      ))}
    </div>
  );
}
export function DataTable({
  label,
  columns,
  rows,
  rowKey = "id",
  empty = __("No records to show.", "vtx-media"),
  className = "",
}) {
  return (
    <div
      className={`vm-table-scroll ${className}`}
      role="region"
      aria-label={label}
      tabIndex={0}
    >
      <table className="vm-data-table">
        <caption className="screen-reader-text">{label}</caption>
        <thead>
          <tr>
            {columns.map((c) => (
              <th key={c.id} scope="col">
                {c.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length ? (
            rows.map((r, i) => (
              <tr key={r[rowKey] ?? i}>
                {columns.map((c, j) => {
                  const Tag = j === 0 ? "th" : "td";
                  return (
                    <Tag
                      key={c.id}
                      scope={j === 0 ? "row" : undefined}
                      className={c.className}
                    >
                      {c.render ? c.render(r) : r[c.id]}
                    </Tag>
                  );
                })}
              </tr>
            ))
          ) : (
            <tr>
              <td colSpan={columns.length} className="vm-table-empty">
                {empty}
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
export function RecordStatus({ value }) {
  const labels = {
    completed: __("Complete", "vtx-media"),
    complete: __("Complete", "vtx-media"),
    running: __("Running", "vtx-media"),
    queued: __("Queued", "vtx-media"),
    pending: __("Pending", "vtx-media"),
    paused: __("Paused", "vtx-media"),
    cancelled: __("Cancelled", "vtx-media"),
    failed: __("Failed", "vtx-media"),
    partial: __("Needs review", "vtx-media"),
    skipped: __("Skipped", "vtx-media"),
    unavailable: __("Not installed", "vtx-media"),
  };
  const tone = ["complete", "completed"].includes(value)
    ? "success"
    : ["failed"].includes(value)
      ? "danger"
      : ["paused", "partial"].includes(value)
        ? "warning"
        : ["running", "queued", "pending"].includes(value)
          ? "info"
          : "neutral";
  return (
    <StatusBadge tone={tone}>
      {labels[value] ||
        String(value || __("Not checked", "vtx-media")).replaceAll("_", " ")}
    </StatusBadge>
  );
}
export function CapabilityStatus({
  available,
  unavailable = __("Unavailable", "vtx-media"),
}) {
  return (
    <StatusBadge tone={available === true ? "success" : "neutral"}>
      {available === true
        ? __("Available", "vtx-media")
        : available === false
          ? unavailable
          : __("Not verified", "vtx-media")}
    </StatusBadge>
  );
}
