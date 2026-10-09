import {
  PageHeader,
  DataList,
  SectionHeader,
  StatusBadge,
} from "../components/design";
import { DataTable, Progress, RecordStatus } from "../components/semantic";
import { api, query } from "../api";
import { Button } from "../components/ui";
import {
  TechnicalDetails,
  RelativeTime,
  Drawer,
} from "../components/presentation";
import { registerAdvancedSection, getAdvancedSections } from "../extensions";
const { useState, useEffect } = wp.element;
const { __ } = wp.i18n;
function useData(path) {
  const [data, setData] = useState(null),
    [error, setError] = useState("");
  useEffect(() => {
    const abort = new AbortController();
    setData(null);
    api(path, { signal: abort.signal })
      .then(setData)
      .catch((e) => {
        if (e.name !== "AbortError") setError(e.message);
      });
    return () => abort.abort();
  }, [path]);
  return [data, error];
}
function Loading({ error }) {
  return (
    <p role={error ? "alert" : "status"}>
      {error || __("Loading diagnostics…", "vtx-media")}
    </p>
  );
}
function Overview({ developer = false }) {
  const [data, error] = useData("advanced/overview"),
    [copied, setCopied] = useState(false),
    [copyError, setCopyError] = useState("");
  if (!data) return <Loading error={error} />;
  return (
    <section className="vm-system-overview">
      <SectionHeader
        eyebrow={__("SYSTEM", "vtx-media")}
        title={
          developer
            ? __("Developer", "vtx-media")
            : __("System overview", "vtx-media")
        }
        actions={
          <Button
            onClick={async () => {
              try {
                await navigator.clipboard.writeText(
                  JSON.stringify(data, null, 2),
                );
                setCopied(true);
              } catch {
                setCopyError(
                  __(
                    "Copy is unavailable. Select the technical details to copy them.",
                    "vtx-media",
                  ),
                );
              }
            }}
          >
            {__("Copy diagnostics", "vtx-media")}
          </Button>
        }
      />
      <div className="vm-diagnostic-grid">
        <section className="vm-diagnostic-group">
          <h4>{__("Installed software", "vtx-media")}</h4>
          <DataList
            rows={Object.entries({
              Core: data.core,
              ...data.extensions,
              WordPress: data.wordpress,
              PHP: data.php,
            }).map(([label, value]) => ({ label, value }))}
          />
        </section>
        <section className="vm-diagnostic-group">
          <h4>{__("Access & persistence", "vtx-media")}</h4>
          <DataList
            rows={[
              {
                label: __("Core database schema", "vtx-media"),
                value: data.schema,
              },
              {
                label: __("Developer entitlement", "vtx-media"),
                value: (
                  <StatusBadge tone={data.developer_mode ? "info" : "neutral"}>
                    {data.developer_mode
                      ? __("Enabled", "vtx-media")
                      : __("Disabled", "vtx-media")}
                  </StatusBadge>
                ),
              },
            ]}
          />
          <p>
            {__(
              "Registered modules remain governed by WordPress capabilities and entitlements.",
              "vtx-media",
            )}
          </p>
        </section>
      </div>
      {copied && <p role="status">{__("Diagnostics copied.", "vtx-media")}</p>}
      {copyError && <p role="alert">{copyError}</p>}
      {developer ? (
        <div className="vm-diagnostic-grid">
          <section>
            <h4>{__("Registered modules", "vtx-media")}</h4>
            <DataTable
              label={__("Registered modules", "vtx-media")}
              rows={data.modules}
              columns={[
                { id: "label", label: __("Module", "vtx-media") },
                {
                  id: "id",
                  label: __("Identifier", "vtx-media"),
                  render: (r) => <code>{r.id}</code>,
                },
                { id: "feature", label: __("Feature", "vtx-media") },
              ]}
            />
          </section>
          <section>
            <h4>{__("Registered features", "vtx-media")}</h4>
            <DataTable
              label={__("Registered features", "vtx-media")}
              rows={data.features}
              columns={[
                { id: "id", label: __("Feature", "vtx-media") },
                {
                  id: "entitled",
                  label: __("Access", "vtx-media"),
                  render: (r) => (
                    <StatusBadge tone={r.entitled ? "success" : "neutral"}>
                      {r.entitled
                        ? __("Available", "vtx-media")
                        : __("Not entitled", "vtx-media")}
                    </StatusBadge>
                  ),
                },
              ]}
            />
          </section>
        </div>
      ) : (
        <section className="vm-module-register">
          <h4>{__("Media intelligence", "vtx-media")}</h4>
          {data.modules.map((m) => (
            <span key={m.id}>{m.label}</span>
          ))}
        </section>
      )}
      <TechnicalDetails>
        <pre>{JSON.stringify(data, null, 2)}</pre>
      </TechnicalDetails>
    </section>
  );
}
export function Logs({ jobId = 0, errorsOnly = false }) {
  const [before, setBefore] = useState(0),
    [errors, setErrors] = useState(errorsOnly),
    [search, setSearch] = useState(""),
    [detail, setDetail] = useState(null);
  const [data, error] = useData(
    query("advanced/logs", { job_id: jobId, before, errors }),
  );
  return (
    <section>
      <SectionHeader
        eyebrow={__("EVENT HISTORY", "vtx-media")}
        title={__("Scan logs", "vtx-media")}
      />
      <div className="vm-record-toolbar">
        <label>
          {__("Search this page", "vtx-media")}
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </label>
        <label className="vm-check-label">
          <input
            type="checkbox"
            checked={errors}
            onChange={(e) => {
              setErrors(e.target.checked);
              setBefore(0);
            }}
          />
          {__("Problems only", "vtx-media")}
        </label>
      </div>
      {!data ? (
        <Loading error={error} />
      ) : (
        <>
          <DataTable
            label={__("Scan logs", "vtx-media")}
            rows={data.items.filter((l) =>
              [l.event, l.rule_id, l.attachment_id, l.job_id]
                .join(" ")
                .toLowerCase()
                .includes(search.toLowerCase()),
            )}
            columns={[
              {
                id: "event",
                label: __("Event", "vtx-media"),
                render: (r) => (
                  <span className="vm-event-name">
                    {r.event.replaceAll("_", " ")}
                  </span>
                ),
              },
              {
                id: "time",
                label: __("Recorded", "vtx-media"),
                render: (r) => <RelativeTime value={r.created_at} />,
              },
              {
                id: "attachment_id",
                label: __("Media", "vtx-media"),
                render: (r) =>
                  Number(r.attachment_id) > 0 ? `#${r.attachment_id}` : "—",
              },
              { id: "job_id", label: __("Job", "vtx-media") },
              {
                id: "rule_id",
                label: __("Rule", "vtx-media"),
                render: (r) => r.rule_id || "—",
              },
              {
                id: "actions",
                label: __("Details", "vtx-media"),
                render: (r) => (
                  <Button
                    onClick={() => setDetail(r)}
                    aria-label={`${__("View event", "vtx-media")}: ${r.event}`}
                  >
                    {__("View", "vtx-media")}
                  </Button>
                ),
              },
            ]}
            empty={__("No events match this view.", "vtx-media")}
          />
          <div className="vm-record-footer">
            <Button disabled={!before} onClick={() => setBefore(0)}>
              {__("Latest", "vtx-media")}
            </Button>
            <Button disabled={!data.next} onClick={() => setBefore(data.next)}>
              {__("Older events", "vtx-media")}
            </Button>
          </div>
        </>
      )}
      {detail && (
        <Drawer
          title={__("Event details", "vtx-media")}
          onClose={() => setDetail(null)}
        >
          <DataList
            rows={Object.entries({
              Event: detail.event,
              Job: detail.job_id,
              Attachment: detail.attachment_id,
              Rule: detail.rule_id,
              Recorded: detail.created_at,
              Source: detail.context?.source_type,
              "Source ID": detail.context?.source_id,
              Reason: detail.context?.reason,
            }).map(([label, value]) => ({
              label,
              value: String(value ?? "—"),
            }))}
          />
        </Drawer>
      )}
    </section>
  );
}
const jobNames = {
  "media-audit": "Media audit",
  "usage-scan": "Usage scan",
  "image-optimization": "Image optimization",
  "cleanup-scan": "Cleanup analysis",
  "ai-generation": "AI suggestions",
  "cleanup-quarantine": "Move to Quarantine",
  "cleanup-restore": "Restore from Quarantine",
  "cleanup-delete": "Permanent deletion",
  "cleanup-hash": "Exact file hashing",
  "ai-generate": "AI suggestions",
  automation: "Automation",
};
function Jobs() {
  const [page, setPage] = useState(1),
    [log, setLog] = useState(null),
    [detail, setDetail] = useState(null),
    [revision, setRevision] = useState(0),
    [status, setStatus] = useState("");
  const [data, error] = useData(
    query("advanced/jobs", { page, refresh: revision }),
  );
  return (
    <section>
      <SectionHeader
        eyebrow={__("BACKGROUND ACTIVITY", "vtx-media")}
        title={__("Scans & jobs", "vtx-media")}
        actions={
          <Button onClick={() => setRevision((n) => n + 1)}>
            {__("Refresh", "vtx-media")}
          </Button>
        }
      />
      <div className="vm-record-toolbar">
        <label>
          {__("Status on this page", "vtx-media")}
          <select value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">{__("All statuses", "vtx-media")}</option>
            {["running", "paused", "completed", "failed", "cancelled"].map(
              (v) => (
                <option key={v} value={v}>
                  {v[0].toUpperCase() + v.slice(1)}
                </option>
              ),
            )}
          </select>
        </label>
      </div>
      {!data ? (
        <Loading error={error} />
      ) : (
        <>
          <DataTable
            label={__("Background jobs", "vtx-media")}
            rows={data.items.filter((j) => !status || j.status === status)}
            columns={[
              {
                id: "type",
                label: __("Task", "vtx-media"),
                render: (j) =>
                  jobNames[j.type] ||
                  j.type.replaceAll("-", " ").replaceAll("_", " "),
              },
              {
                id: "status",
                label: __("Status", "vtx-media"),
                render: (j) => <RecordStatus value={j.status} />,
              },
              {
                id: "progress",
                label: __("Progress", "vtx-media"),
                render: (j) => (
                  <div className="vm-job-progress">
                    <span>
                      {j.processed} / {j.total}
                    </span>
                    <Progress
                      value={j.processed}
                      total={j.total}
                      label={`${j.type}: ${j.processed} / ${j.total}`}
                      tone={
                        j.status === "failed"
                          ? "danger"
                          : j.status === "completed"
                            ? "success"
                            : "info"
                      }
                    />
                  </div>
                ),
              },
              {
                id: "updated_at",
                label: __("Last activity", "vtx-media"),
                render: (j) => <RelativeTime value={j.updated_at} />,
              },
              {
                id: "failed",
                label: __("Failures", "vtx-media"),
                render: (j) =>
                  Number(j.failed) > 0 ? (
                    <StatusBadge tone="danger">{j.failed}</StatusBadge>
                  ) : (
                    0
                  ),
              },
              {
                id: "actions",
                label: __("Actions", "vtx-media"),
                render: (j) => (
                  <div className="vm-row-actions">
                    <Button onClick={() => setLog(j.id)}>
                      {__("View logs", "vtx-media")}
                    </Button>
                    <Button
                      aria-label={`${__("Details", "vtx-media")}: ${j.type}`}
                      onClick={() => setDetail(j)}
                    >
                      {__("Details", "vtx-media")}
                    </Button>
                  </div>
                ),
              },
            ]}
          />
          <div className="vm-record-footer">
            <Button disabled={page === 1} onClick={() => setPage((p) => p - 1)}>
              {__("Previous", "vtx-media")}
            </Button>
            <span>
              {__("Page", "vtx-media")} {page}
            </span>
            <Button
              disabled={!data.has_more}
              onClick={() => setPage((p) => p + 1)}
            >
              {__("Next", "vtx-media")}
            </Button>
          </div>
        </>
      )}
      {log !== null && (
        <Drawer
          title={__("Job activity", "vtx-media")}
          onClose={() => setLog(null)}
        >
          <Logs key={log} jobId={log} />
        </Drawer>
      )}
      {detail && (
        <Drawer
          title={__("Job details", "vtx-media")}
          onClose={() => setDetail(null)}
        >
          <DataList
            rows={Object.entries(detail).map(([label, value]) => ({
              label,
              value: String(value ?? "—"),
            }))}
          />
        </Drawer>
      )}
    </section>
  );
}
function Rules() {
  const [data, error] = useData("advanced/rules"),
    [search, setSearch] = useState(""),
    [category, setCategory] = useState(""),
    [detail, setDetail] = useState(null);
  return (
    <section>
      <SectionHeader
        eyebrow={__("REGISTERED CHECKS", "vtx-media")}
        title={__("Audit rules", "vtx-media")}
        description={__(
          "Read-only rule definitions. Configuration and findings remain in Health.",
          "vtx-media",
        )}
      />
      <div className="vm-record-toolbar">
        <label>
          {__("Find a rule", "vtx-media")}
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </label>
        <label>
          {__("Category", "vtx-media")}
          <select
            value={category}
            onChange={(e) => setCategory(e.target.value)}
          >
            <option value="">{__("All categories", "vtx-media")}</option>
            {[...new Set(data?.items.map((r) => r.category) || [])].map((c) => (
              <option key={c}>{c}</option>
            ))}
          </select>
        </label>
      </div>
      {!data ? (
        <Loading error={error} />
      ) : (
        <DataTable
          label={__("Audit rules", "vtx-media")}
          rows={data.items.filter(
            (r) =>
              (!category || r.category === category) &&
              `${r.title} ${r.explanation}`
                .toLowerCase()
                .includes(search.toLowerCase()),
          )}
          columns={[
            { id: "title", label: __("Rule", "vtx-media") },
            { id: "category", label: __("Category", "vtx-media") },
            {
              id: "severity",
              label: __("Severity", "vtx-media"),
              render: (r) => (
                <StatusBadge
                  tone={
                    ["critical", "high"].includes(r.severity)
                      ? "danger"
                      : r.severity === "medium"
                        ? "warning"
                        : "neutral"
                  }
                >
                  {r.severity}
                </StatusBadge>
              ),
            },
            {
              id: "explanation",
              label: __("Purpose", "vtx-media"),
              render: (r) => (
                <span className="vm-clamp-text">{r.explanation}</span>
              ),
            },
            {
              id: "actions",
              label: __("Details", "vtx-media"),
              render: (r) => (
                <Button
                  aria-label={`${__("Review rule", "vtx-media")}: ${r.title}`}
                  onClick={() => setDetail(r)}
                >
                  {__("View", "vtx-media")}
                </Button>
              ),
            },
          ]}
        />
      )}
      {detail && (
        <Drawer title={detail.title} onClose={() => setDetail(null)}>
          <p>{detail.explanation}</p>
          <DataList
            rows={["id", "version", "category", "severity", "types"].map(
              (label) => ({ label, value: String(detail[label] ?? "—") }),
            )}
          />
        </Drawer>
      )}
    </section>
  );
}
registerAdvancedSection("overview", {
  label: __("Overview", "vtx-media"),
  component: Overview,
  position: 0,
});
registerAdvancedSection("jobs", {
  label: __("Scans & Jobs", "vtx-media"),
  component: Jobs,
  position: 20,
});
registerAdvancedSection("audit", {
  label: __("Audit Rules", "vtx-media"),
  component: Rules,
  position: 30,
});
registerAdvancedSection("logs", {
  label: __("Logs", "vtx-media"),
  component: Logs,
  position: 40,
});
registerAdvancedSection("developer", {
  label: __("Developer", "vtx-media"),
  component: () => <Overview developer />,
  position: 50,
});
export function AdvancedScreen() {
  const [selected, setSelected] = useState(
      () =>
        new URLSearchParams(location.search).get("vm_advanced") || "overview",
    ),
    [, update] = useState(0);
  useEffect(() => {
    const fn = () => update((n) => n + 1);
    window.addEventListener("vtx-media-extensions", fn);
    return () => window.removeEventListener("vtx-media-extensions", fn);
  }, []);
  const sections = getAdvancedSections(),
    active = sections.find((s) => s.id === selected) || sections[0],
    Component = active.component;
  return (
    <main className="vm-audit-app vm-advanced">
      <PageHeader>
        <div>
          <span className="vm-kicker">
            {__("Advanced on demand", "vtx-media")}
          </span>
          <h2>{__("Advanced", "vtx-media")}</h2>
          <p>
            {__(
              "Inspect scan activity, source coverage and system details when you need them.",
              "vtx-media",
            )}
          </p>
        </div>
      </PageHeader>
      <nav
        className="vm-secondary-nav"
        aria-label={__("Advanced sections", "vtx-media")}
      >
        {sections.map((s) => (
          <Button
            key={s.id}
            aria-pressed={s.id === active.id}
            onClick={() => {
              setSelected(s.id);
              const url = new URL(location.href);
              url.searchParams.set("vm_advanced", s.id);
              history.replaceState(null, "", url);
            }}
          >
            {s.label}
          </Button>
        ))}
      </nav>
      <div className="vm-advanced-panel">
        <Component key={active.id} />
      </div>
    </main>
  );
}
