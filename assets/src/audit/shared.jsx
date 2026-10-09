import { api } from "../api";
const { __, sprintf } = wp.i18n;
export const labels = {
  critical: __("Critical", "vtx-media"),
  high: __("High", "vtx-media"),
  medium: __("Medium", "vtx-media"),
  low: __("Low", "vtx-media"),
  info: __("Info", "vtx-media"),
  metadata: __("Metadata", "vtx-media"),
  accessibility: __("Accessibility", "vtx-media"),
  files: __("Files", "vtx-media"),
  performance: __("Performance", "vtx-media"),
  open: __("Open", "vtx-media"),
  ignored: __("Ignored", "vtx-media"),
  resolved: __("Resolved", "vtx-media"),
  running: __("Running", "vtx-media"),
  paused: __("Paused", "vtx-media"),
  cancelled: __("Cancelled", "vtx-media"),
  completed: __("Complete", "vtx-media"),
  failed: __("Incomplete", "vtx-media"),
  stale: __("Needs refresh", "vtx-media"),
  pending: __("Pending", "vtx-media"),
  "not-analyzed": __("Not analyzed", "vtx-media"),
  complete: __("Analyzed", "vtx-media"),
};
export function Badge({ value }) {
  return (
    <span className={"vm-audit-badge is-" + value}>
      {labels[value] || value}
    </span>
  );
}
export async function action(name, ids) {
  if (
    name === "decorative" &&
    !window.confirm(
      __(
        "Mark these images as decorative? This deliberately clears their WordPress ALT text. It does not change existing post HTML.",
        "vtx-media",
      ),
    )
  )
    return null;
  const result = await api("audit/actions", {
    method: "POST",
    body: { action: name, ids },
  });
  window.dispatchEvent(new Event("vtx-media-audit-changed"));
  return result;
}
export function outcome(result) {
  return (
    sprintf(
      /* translators: 1: Successful actions, 2: Failed actions. */ __(
        "%1$d updated; %2$d failed.",
        "vtx-media",
      ),
      result.success,
      result.failed,
    ) +
    (result.failed
      ? " " +
        result.results
          .filter((r) => !r.success)
          .map((r) => `#${r.id}: ${r.message}`)
          .join(" ")
      : "")
  );
}
export function Evidence({ data }) {
  const names = {
    alt: __("ALT", "vtx-media"),
    filename: __("Filename", "vtx-media"),
    title: __("Title", "vtx-media"),
    caption: __("Caption", "vtx-media"),
    description: __("Description", "vtx-media"),
    bytes: __("File size", "vtx-media"),
    threshold: __("Threshold", "vtx-media"),
    level: __("Size review", "vtx-media"),
    width: __("Width", "vtx-media"),
    height: __("Height", "vtx-media"),
    max_width: __("Width threshold", "vtx-media"),
    max_height: __("Height threshold", "vtx-media"),
    length: __("Characters", "vtx-media"),
    reason: __("Reason", "vtx-media"),
    metadata: __("Stored dimensions", "vtx-media"),
    file: __("File dimensions", "vtx-media"),
    source_state: __("Storage verification", "vtx-media"),
  };
  const values = {
    "very-large": __("Very large", "vtx-media"),
    large: __("Large", "vtx-media"),
    unknown: __("Unable to verify", "vtx-media"),
    missing: __("File missing", "vtx-media"),
    "missing-original": __("Original file missing", "vtx-media"),
    "missing-dimensions": __("Dimensions missing or malformed", "vtx-media"),
    "dimension-mismatch": __("Dimensions disagree", "vtx-media"),
  };
  const format = (key, value) => {
    if (value === "") return __("Empty", "vtx-media");
    if ((key === "bytes" || key === "threshold") && data.bytes !== undefined)
      return `${(Number(value) / 1048576).toLocaleString(undefined, { maximumFractionDigits: 2 })} MiB`;
    if (value && typeof value === "object" && "width" in value)
      return `${value.width} × ${value.height}`;
    return typeof value === "object"
      ? JSON.stringify(value)
      : values[value] || String(value);
  };
  return (
    <dl className="vm-evidence">
      {Object.entries(data || {}).map(([key, value]) => (
        <div key={key}>
          <dt>{names[key] || key}</dt>
          <dd>{format(key, value)}</dd>
        </div>
      ))}
    </dl>
  );
}
