import { TechnicalDetails, RelativeTime } from "../components/presentation";
import { api } from "../api";
import { Button } from "../components/ui";
import { Badge } from "./shared";
const { useState } = wp.element;
const { __, sprintf } = wp.i18n;
export function ScanPanel({ job, onChange }) {
  const [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [batch, setBatch] = useState(25),
    [logs, setLogs] = useState(null);
  const run = async (command) => {
    setBusy(true);
    setError("");
    try {
      if (command === "start")
        await api("jobs", {
          method: "POST",
          body: { type: "media-audit", batch_size: batch },
        });
      else
        await api(`jobs/${job.id}/control`, {
          method: "POST",
          body: { action: command },
        });
      setLogs(null);
      onChange();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };
  const loadLogs = async () => {
    try {
      const data = await api(
        `jobs/${job.id}/logs?after=${logs?.[logs.length - 1]?.id || 0}`,
      );
      setLogs((old) => [...(old || []), ...data.items]);
    } catch (e) {
      setError(e.message);
    }
  };
  const active = job && ["running", "paused"].includes(job.status);
  const progress = job?.total
    ? Math.min(
        100,
        Math.round(((job.processed + job.skipped) / job.total) * 100),
      )
    : job?.status === "completed"
      ? 100
      : 0;
  return (
    <section
      className="vm-scan-panel vm-status-panel"
      aria-label={__("Media audit scan", "vtx-media")}
    >
      <div className="vm-scan-title">
        <div>
          <span className="vm-kicker">{__("Health check", "vtx-media")}</span>
          <h3>
            {job?.status === "completed"
              ? __("Media health check complete", "vtx-media")
              : active
                ? __("Checking media health…", "vtx-media")
                : __("Review your library", "vtx-media")}
          </h3>
        </div>
        {job && <Badge value={job.status} />}
      </div>
      {job && !active && (
        <p className="vm-status-context">
          {job.processed} / {job.total} {__("files checked", "vtx-media")} ·{" "}
          <RelativeTime value={job.updated_at} />
        </p>
      )}
      {active && (
        <>
          <progress
            max="100"
            value={progress}
            aria-label={__("Audit progress", "vtx-media")}
          />
          <p aria-live="polite">
            {sprintf(
              /* translators: First number: checked files or areas; second: total. */
              __("%1$d / %2$d files checked", "vtx-media"),
              job.processed,
              job.total,
            )}{" "}
            · {progress}%
          </p>
        </>
      )}
      {job?.failed > 0 && (
        <Button onClick={loadLogs}>
          {job.failed}{" "}
          {__("files couldn’t be checked — view problems", "vtx-media")}
        </Button>
      )}
      {job?.stalled && (
        <p>
          {__(
            "The scan was interrupted. Resume to continue where it stopped.",
            "vtx-media",
          )}
        </p>
      )}
      <div className="vm-audit-actions">
        {!active ? (
          <Button primary disabled={busy} onClick={() => run("start")}>
            {job
              ? __("Run again", "vtx-media")
              : __("Scan Media Library", "vtx-media")}
          </Button>
        ) : (
          <>
            <Button
              primary
              disabled={busy}
              onClick={() =>
                run(job.status === "paused" || job.stalled ? "resume" : "pause")
              }
            >
              {job.status === "paused" || job.stalled
                ? __("Resume", "vtx-media")
                : __("Pause", "vtx-media")}
            </Button>
            <Button disabled={busy} onClick={() => run("cancel")}>
              {__("Cancel scan", "vtx-media")}
            </Button>
          </>
        )}
        {job?.status === "completed" && (
          <Button
            onClick={() =>
              document
                .querySelector(".vm-issue-browser")
                ?.scrollIntoView({ block: "start" })
            }
          >
            {__("Review issues", "vtx-media")}
          </Button>
        )}
      </div>
      <TechnicalDetails>
        {!active && (
          <label className="vm-inline-label">
            {__("Batch size", "vtx-media")}
            <select
              aria-label={__("Batch size", "vtx-media")}
              value={batch}
              onChange={(e) => setBatch(Number(e.target.value))}
            >
              {[10, 25, 50, 100].map((n) => (
                <option key={n}>{n}</option>
              ))}
            </select>
          </label>
        )}
        {job && (
          <>
            <p>
              {job.scope_label} · {__("Last activity:", "vtx-media")}{" "}
              <RelativeTime value={job.updated_at} />
            </p>
            <p>
              {sprintf(
                /* translators: Numbers: batch limit, failed items, and no-longer-eligible items. */
                __(
                  "Batch limit: %1$d · Failed: %2$d · No longer eligible: %3$d",
                  "vtx-media",
                ),
                job.batch_size,
                job.failed,
                job.skipped,
              )}
            </p>
            <Button disabled={busy} onClick={loadLogs}>
              {logs
                ? __("Load more diagnostics", "vtx-media")
                : __("View diagnostics", "vtx-media")}
            </Button>
          </>
        )}
      </TechnicalDetails>
      {logs && (
        <div className="vm-job-logs" role="log">
          {logs.length ? (
            logs.map((log) => (
              <p key={log.id}>
                {log.created_at} UTC · {log.event}
                {Number(log.attachment_id) > 0
                  ? ` · #${log.attachment_id}`
                  : ""}{" "}
                {log.rule_id}
              </p>
            ))
          ) : (
            <p>{__("No diagnostic events.", "vtx-media")}</p>
          )}
        </div>
      )}
      {error && (
        <p role="alert" className="vm-audit-error">
          {error}
        </p>
      )}
    </section>
  );
}
