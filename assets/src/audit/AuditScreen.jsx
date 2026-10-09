import { Progress } from "../components/semantic";
import { PageHeader, MetricCard, SectionHeader } from "../components/design";
import { api } from "../api";
import { Button, Icon } from "../components/ui";
import { ScanPanel } from "./ScanPanel";
import { SettingsPanel } from "./SettingsPanel";
import { IssueBrowser } from "./IssueBrowser";
import { labels } from "./shared";
const { useState, useEffect, useCallback } = wp.element;
const { __, sprintf } = wp.i18n;
export function AuditScreen({ onOpen }) {
  const [overview, setOverview] = useState(null),
    [error, setError] = useState(""),
    [revision, setRevision] = useState(0),
    [settings, setSettings] = useState(false),
    [notice, setNotice] = useState("");
  const [filters, setFilters] = useState({
    page: 1,
    per_page: 25,
    search: "",
    category: "",
    severity: "",
    rule_id: "",
    type: "",
    status: "open",
    confidence: 0,
    sort: "severity",
    current: true,
    group: "",
  });
  const refresh = useCallback(() => setRevision((r) => r + 1), []);
  useEffect(() => {
    let live = true;
    api("audit/overview")
      .then((r) => {
        if (live) {
          setOverview(r);
          setError("");
        }
      })
      .catch((e) => {
        if (live) setError(e.message);
      });
    return () => {
      live = false;
    };
  }, [revision]);
  useEffect(() => {
    window.addEventListener("vtx-media-audit-changed", refresh);
    return () => window.removeEventListener("vtx-media-audit-changed", refresh);
  }, [refresh]);
  useEffect(() => {
    if (overview?.job?.status !== "running") return;
    let stopped = false,
      timer;
    const poll = async () => {
      try {
        await api(`jobs/${overview.job.id}/tick`, { method: "POST", body: {} });
        if (!stopped) refresh();
      } catch (e) {
        if (!stopped) setError(e.message);
      } finally {
        if (!stopped) timer = setTimeout(poll, 2000);
      }
    };
    timer = setTimeout(poll, 1000);
    return () => {
      stopped = true;
      clearTimeout(timer);
    };
  }, [overview?.job?.id, overview?.job?.status, refresh]);
  const filter = (key, value) => {
    setFilters((f) => ({
      ...f,
      search: "",
      type: "",
      confidence: 0,
      group: "",
      current: true,
      category: "",
      severity: "",
      rule_id: "",
      [key]: value,
      status: "open",
      page: 1,
    }));
    document
      .querySelector(".vm-issue-browser")
      ?.scrollIntoView({ block: "start", behavior: "smooth" });
  };
  const s = overview?.summary;
  return (
    <main className="vm-audit-app">
      <PageHeader>
        <div>
          <span className="vm-kicker">
            {__("Understand your library", "vtx-media")}
          </span>
          <h2>{__("Media Health", "vtx-media")}</h2>
          <p>
            {__(
              "Metadata, accessibility review, file integrity and performance opportunities.",
              "vtx-media",
            )}
          </p>
        </div>
        <Button
          onClick={() => setSettings(!settings)}
          aria-expanded={settings}
          icon="admin-settings"
        >
          {settings
            ? __("Close settings", "vtx-media")
            : __("Audit settings", "vtx-media")}
        </Button>
      </PageHeader>
      {notice && (
        <p role="status" className="vm-audit-feedback">
          {notice}
        </p>
      )}
      {error && (
        <p role="alert">
          {error} <Button onClick={refresh}>{__("Retry", "vtx-media")}</Button>
        </p>
      )}
      {settings && (
        <SettingsPanel
          onSaved={() => {
            setNotice(
              __(
                "Thresholds saved. Run an audit to refresh outdated results.",
                "vtx-media",
              ),
            );
            refresh();
          }}
        />
      )}
      {!overview && !error && (
        <p role="status">{__("Loading media health…", "vtx-media")}</p>
      )}
      {s && (
        <>
          {s.previously_analyzed === 0 && (
            <div className="vm-audit-welcome">
              <span className="vm-empty-icon">
                <Icon name="shield" />
              </span>
              <div>
                <h3>
                  {__("Media Health has not been analyzed yet.", "vtx-media")}
                </h3>
                <p>
                  {__(
                    "Run an audit to review metadata, accessibility, file integrity and performance opportunities. No media files are changed.",
                    "vtx-media",
                  )}
                </p>
              </div>
            </div>
          )}
          <div className="vm-health-cards">
            <MetricCard
              label={__("Overall health", "vtx-media")}
              value={
                s.score === null ? (
                  __("Not analyzed", "vtx-media")
                ) : (
                  <>
                    <span>
                      {s.score}
                      <small className="vm-score-denominator">/100</small>
                    </span>
                    <Progress
                      value={s.score}
                      label={__("Media Health score", "vtx-media")}
                    />
                  </>
                )
              }
              help={sprintf(
                __("%1$d / %2$d files analyzed", "vtx-media"),
                s.analyzed,
                s.total,
              )}
            />
            {[
              [__("Files needing attention", "vtx-media"), s.attention, ""],
              [
                __("Critical / High issues", "vtx-media"),
                s.priority,
                "priority",
              ],
              [
                __("Recommendations", "vtx-media"),
                s.recommendations,
                "recommendations",
              ],
            ].map(([label, value, group]) => (
              <MetricCard
                key={label}
                label={label}
                value={value}
                help={__("Review issues →", "vtx-media")}
                onClick={() => filter("group", group)}
              />
            ))}
          </div>
          <p className="vm-coverage">
            {sprintf(
              /* translators: First number: checked files or areas; second: total. */
              __(
                "%1$d / %2$d files analyzed. Health reflects the files with complete, current checks.",
                "vtx-media",
              ),
              s.analyzed,
              s.total,
            )}
            {s.pending > 0 &&
              " " +
                sprintf(
                  /* translators: %d: number of items indicated by the following label. */
                  __("%d files await a fresh check.", "vtx-media"),
                  s.pending,
                )}
            {s.failed > 0 &&
              " " +
                sprintf(
                  /* translators: %d: number of items indicated by the following label. */
                  __("%d files could not be fully checked.", "vtx-media"),
                  s.failed,
                )}
          </p>
          <ScanPanel job={overview.job} onChange={refresh} />
          <div className="vm-audit-breakdowns">
            <section className="vm-card-panel">
              <SectionHeader
                eyebrow={__("HEALTH", "vtx-media")}
                title={__("Issue distribution", "vtx-media")}
                description={__(
                  "Current findings by category, compared with the largest category.",
                  "vtx-media",
                )}
              />
              <div className="vm-breakdown-grid">
                {[
                  "metadata",
                  "accessibility",
                  "files",
                  "performance",
                  ...s.categories
                    .map((c) => c.id)
                    .filter(
                      (v) =>
                        ![
                          "metadata",
                          "accessibility",
                          "files",
                          "performance",
                        ].includes(v),
                    ),
                ].map((id) => (
                  <button key={id} onClick={() => filter("category", id)}>
                    <span>{labels[id] || id}</span>
                    <strong>
                      {s.categories.find((c) => c.id === id)?.count || 0}
                    </strong>
                    <Progress
                      value={s.categories.find((c) => c.id === id)?.count || 0}
                      total={Math.max(
                        1,
                        ...s.categories.map((c) => Number(c.count)),
                      )}
                      tone={id === "accessibility" ? "warning" : "info"}
                      label={`${labels[id] || id}: ${s.categories.find((c) => c.id === id)?.count || 0} findings`}
                    />
                  </button>
                ))}
              </div>
            </section>
            <section className="vm-card-panel">
              <SectionHeader
                eyebrow={__("CONTENT QUALITY", "vtx-media")}
                title={__("SEO & Accessibility", "vtx-media")}
              />
              <p>
                {__(
                  "Review alternative text and clear file names. Captions and attachment descriptions are optional, depending on your content and workflow.",
                  "vtx-media",
                )}
              </p>
              <div className="vm-rule-breakdown">
                {s.rules
                  .filter(
                    (rule) =>
                      ["metadata", "accessibility"].includes(
                        overview.rules.find((r) => r.id === rule.id)?.category,
                      ) &&
                      !["missing-caption", "missing-description"].includes(
                        rule.id,
                      ),
                  )
                  .map((rule) => (
                    <button
                      key={rule.id}
                      onClick={() => filter("rule_id", rule.id)}
                    >
                      <span>
                        {overview.rules.find((r) => r.id === rule.id)?.title ||
                          rule.id}
                      </span>
                      <strong>{rule.count}</strong>
                    </button>
                  ))}
                {!s.rules.length && (
                  <p>{__("No current open issues.", "vtx-media")}</p>
                )}
                {s.rules.some((rule) =>
                  ["missing-caption", "missing-description"].includes(rule.id),
                ) && (
                  <details className="vm-technical">
                    <summary>{__("Optional metadata", "vtx-media")}</summary>
                    {s.rules
                      .filter((rule) =>
                        ["missing-caption", "missing-description"].includes(
                          rule.id,
                        ),
                      )
                      .map((rule) => (
                        <button
                          key={rule.id}
                          onClick={() => filter("rule_id", rule.id)}
                        >
                          <span>
                            {overview.rules.find((r) => r.id === rule.id)
                              ?.title || rule.id}
                          </span>
                          <strong>{rule.count}</strong>
                        </button>
                      ))}
                  </details>
                )}
              </div>
            </section>
          </div>
          <details className="vm-technical">
            <summary>{__("Severity breakdown", "vtx-media")}</summary>
            <div className="vm-severity-summary">
              {s.severities.map((v) => (
                <button key={v.id} onClick={() => filter("severity", v.id)}>
                  {labels[v.id]} <strong>{v.count}</strong>
                </button>
              ))}
            </div>
          </details>
          <IssueBrowser
            rules={overview.rules}
            filters={filters}
            setFilters={setFilters}
            revision={revision}
            onOpen={onOpen}
            onChanged={refresh}
          />
          <details className="vm-score-help">
            <summary>{__("How health is calculated", "vtx-media")}</summary>
            <p>
              {__(
                "Each complete audit starts at 100. Open issues deduct Critical 60, High 20, Medium 10, Low 3 or Info 1. Category deductions are capped at Files 80, Accessibility 30, Performance 25 and Metadata 8 (other categories 20). The score cannot fall below zero. Ignored and resolved findings deduct nothing. The library score is the rounded mean of current complete scores; serious issue counts remain visible independently.",
                "vtx-media",
              )}
            </p>
          </details>
        </>
      )}
    </main>
  );
}
