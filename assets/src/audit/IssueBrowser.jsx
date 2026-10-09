import { TechnicalDetails } from "../components/presentation";
import { api, query } from "../api";
import { Button, Icon } from "../components/ui";
import { Badge, labels, action, outcome, Evidence } from "./shared";
const { useState, useEffect } = wp.element;
const { __, sprintf } = wp.i18n;
export function IssueBrowser({
  rules,
  filters,
  setFilters,
  revision,
  onOpen,
  onChanged,
}) {
  const [data, setData] = useState(null),
    [error, setError] = useState(""),
    [loading, setLoading] = useState(true),
    [selected, setSelected] = useState([]),
    [busy, setBusy] = useState(false),
    [message, setMessage] = useState("");
  useEffect(() => {
    const abort = new AbortController();
    setLoading(true);

    api(query("audit/findings", filters), { signal: abort.signal })
      .then((r) => {
        setData(r);
        setError("");
      })
      .catch((e) => {
        if (e.name !== "AbortError") setError(e.message);
      })
      .finally(() => {
        if (!abort.signal.aborted) setLoading(false);
      });
    return () => abort.abort();
  }, [filters, revision]);
  useEffect(() => setSelected([]), [filters]);
  const change = (key, value) =>
    setFilters((f) => ({ ...f, [key]: value, page: 1 }));
  const run = async (name) => {
    setBusy(true);
    setMessage("");
    try {
      const ids = ["ignore", "restore"].includes(name)
        ? selected
        : [
            ...new Set(
              data.items
                .filter((i) => selected.includes(i.id))
                .map((i) => i.attachment_id),
            ),
          ];
      const result = await action(name, ids);
      if (result) {
        setMessage(outcome(result));
        setSelected([]);
        onChanged();
      }
    } catch (e) {
      setMessage(e.message);
    } finally {
      setBusy(false);
    }
  };
  const select = (label, key, options) => (
    <label>
      {label}
      <select
        aria-label={label}
        value={filters[key]}
        onChange={(e) => change(key, e.target.value)}
      >
        {options.map(([value, text]) => (
          <option value={value} key={value}>
            {text}
          </option>
        ))}
      </select>
    </label>
  );
  const any = __("All", "vtx-media");
  return (
    <section
      className="vm-issue-browser"
      aria-label={__("Health issues", "vtx-media")}
    >
      <div className="vm-audit-section-title">
        <h3>{__("Health issues", "vtx-media")}</h3>
        <span>
          {data
            ? sprintf(
                /* translators: %d: Number of matching findings. */ __(
                  "%1$d issues across %2$d files",
                  "vtx-media",
                ),
                data.pagination.total,
                data.pagination.media_total,
              )
            : __("Loading…", "vtx-media")}
        </span>
      </div>
      {filters.group && (
        <Button
          aria-label={__("Clear issue group filter", "vtx-media")}
          onClick={() => change("group", "")}
        >
          {filters.group === "priority"
            ? __("Critical / High issues", "vtx-media")
            : __("Recommendations", "vtx-media")}{" "}
          ×
        </Button>
      )}
      <div className="vm-issue-filters">
        <label className="vm-issue-search">
          {__("Search filename or title", "vtx-media")}
          <input
            type="search"
            value={filters.search}
            onChange={(e) => change("search", e.target.value)}
          />
        </label>
        {select(__("Category", "vtx-media"), "category", [
          ["", any],
          ...[...new Set(rules.map((r) => r.category))].map((v) => [
            v,
            labels[v] || v,
          ]),
        ])}
        {select(__("Severity", "vtx-media"), "severity", [
          ["", any],
          ...["critical", "high", "medium", "low", "info"].map((v) => [
            v,
            labels[v],
          ]),
        ])}
        {select(__("Issue type", "vtx-media"), "rule_id", [
          ["", any],
          ...rules.map((r) => [r.id, r.title]),
        ])}
        {select(__("Status", "vtx-media"), "status", [
          ...["open", "ignored", "resolved"].map((v) => [v, labels[v]]),
          ["", any],
        ])}
        {select(__("Media type", "vtx-media"), "type", [
          ["", any],
          ["image", __("Images", "vtx-media")],
          ["video", __("Video", "vtx-media")],
          ["audio", __("Audio", "vtx-media")],
          ["application", __("Documents", "vtx-media")],
          ["text", __("Text", "vtx-media")],
        ])}
        <details className="vm-technical">
          <summary>{__("Advanced filters", "vtx-media")}</summary>{" "}
          {select(__("Confidence", "vtx-media"), "confidence", [
            [0, any],
            [0.9, __("90% or above", "vtx-media")],
            [1, __("100%", "vtx-media")],
          ])}
          {select(__("Sort findings", "vtx-media"), "sort", [
            ["severity", __("Severity", "vtx-media")],
            ["health", __("Health score", "vtx-media")],
            ["filename", __("Filename", "vtx-media")],
            ["recent", __("Recently audited", "vtx-media")],
          ])}
          <label>
            <input
              type="checkbox"
              checked={filters.current}
              onChange={(e) => change("current", e.target.checked)}
            />
            {__("Current results only", "vtx-media")}
          </label>
        </details>
      </div>
      {selected.length > 0 && (
        <div className="vm-audit-bulk">
          <strong>
            {sprintf(__("%d selected", "vtx-media"), selected.length)}
          </strong>
          {[
            ["rescan", __("Re-scan selected", "vtx-media")],
            ["ignore", __("Ignore findings", "vtx-media")],
            ["restore", __("Restore findings", "vtx-media")],
            ["decorative", __("Mark decorative", "vtx-media")],
            ["remove-decorative", __("Remove decorative status", "vtx-media")],
          ].map(([name, label]) => (
            <Button
              key={name}
              disabled={busy || loading}
              onClick={() => run(name)}
            >
              {label}
            </Button>
          ))}
        </div>
      )}
      {message && (
        <p role="status" className="vm-audit-feedback">
          {message}
        </p>
      )}
      {error && (
        <p role="alert">
          {error}{" "}
          <Button onClick={onChanged}>{__("Retry", "vtx-media")}</Button>
        </p>
      )}
      <div
        className="vm-issue-table-wrap"
        tabIndex="0"
        role="region"
        aria-label={__("Paginated audit findings", "vtx-media")}
        aria-busy={loading}
      >
        <table className="vm-issue-table">
          <thead>
            <tr>
              <th>
                <input
                  type="checkbox"
                  aria-label={__("Select findings on this page", "vtx-media")}
                  checked={
                    !!data?.items.length &&
                    selected.length === data.items.length
                  }
                  disabled={loading || busy}
                  onChange={(e) =>
                    setSelected(
                      e.target.checked ? data.items.map((i) => i.id) : [],
                    )
                  }
                />
              </th>
              <th>{__("Media", "vtx-media")}</th>
              <th>{__("Finding & recommendation", "vtx-media")}</th>
              <th>{__("Priority", "vtx-media")}</th>
              <th>{__("Health", "vtx-media")}</th>
            </tr>
          </thead>
          <tbody>
            {data?.items.map((item) => (
              <tr key={item.id}>
                <td>
                  <input
                    type="checkbox"
                    disabled={busy || loading}
                    checked={selected.includes(item.id)}
                    aria-label={sprintf(
                      /* translators: %s: Media filename. */ __(
                        "Select finding for %s",
                        "vtx-media",
                      ),
                      item.filename,
                    )}
                    onChange={(e) =>
                      setSelected((s) =>
                        e.target.checked
                          ? [...s, item.id]
                          : s.filter((id) => id !== item.id),
                      )
                    }
                  />
                </td>
                <td>
                  <button
                    className="vm-issue-media"
                    onClick={() => onOpen(item.attachment_id)}
                  >
                    {item.thumbnail ? (
                      <img src={item.thumbnail} alt="" loading="lazy" />
                    ) : (
                      <Icon name="media-default" />
                    )}
                    <span>{item.filename || item.post_title}</span>
                  </button>
                </td>
                <td>
                  <strong>{item.title}</strong>
                  <p>{item.recommendation}</p>
                  <small>
                    {labels[item.category] || item.category} ·{" "}
                    {labels[item.status]}
                  </small>
                  <details>
                    <summary>{__("Details", "vtx-media")}</summary>
                    <p>{item.explanation}</p>
                    <Evidence data={item.data} />
                    <small>{item.audited_at} UTC</small>
                  </details>
                </td>
                <td>
                  <Badge value={item.severity} />
                  <TechnicalDetails>
                    {" "}
                    <small>
                      {Math.round(item.confidence * 100)}%{" "}
                      {__("confidence", "vtx-media")}
                    </small>
                    <small>
                      {item.kind === "heuristic"
                        ? __("Heuristic", "vtx-media")
                        : __("Objective check", "vtx-media")}
                    </small>
                    <p>
                      {item.rule_id} / {item.rule_version}
                    </p>
                  </TechnicalDetails>
                </td>
                <td>
                  <strong>
                    {item.score === null ? "—" : `${item.score}/100`}
                  </strong>
                  {item.stale && (
                    <small>{__("Needs refresh", "vtx-media")}</small>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {!loading && !data?.items.length && (
        <div className="vm-audit-empty">
          <Icon name="search" />
          <h3>{__("No matching findings", "vtx-media")}</h3>
          <p>
            {__(
              "Try another filter, or scan pending media to review its health.",
              "vtx-media",
            )}
          </p>
        </div>
      )}
      {data && (
        <footer className="vm-audit-pagination">
          <span>
            {sprintf(
              __("Page %1$d of %2$d", "vtx-media"),
              filters.page,
              Math.max(1, data.pagination.pages),
            )}
          </span>
          <div>
            <Button
              disabled={loading || filters.page <= 1}
              onClick={() => setFilters((f) => ({ ...f, page: f.page - 1 }))}
            >
              {__("Previous", "vtx-media")}
            </Button>
            <Button
              disabled={loading || filters.page >= data.pagination.pages}
              onClick={() => setFilters((f) => ({ ...f, page: f.page + 1 }))}
            >
              {__("Next", "vtx-media")}
            </Button>
          </div>
        </footer>
      )}
    </section>
  );
}
