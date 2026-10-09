import { TechnicalDetails } from "../components/presentation";
import { Button } from "../components/ui";
import { action, outcome, Badge, labels, Evidence } from "./shared";
const { useState } = wp.element;
const { __ } = wp.i18n;
export function HealthSection({ item, context, seo = false }) {
  const [busy, setBusy] = useState(false),
    [message, setMessage] = useState("");
  const health = item.audit;
  if (!health) return null;
  const run = async (name, ids) => {
    setBusy(true);
    setMessage("");
    try {
      const result = await action(name, ids);
      if (result) {
        setMessage(outcome(result));
        context.onSaved();
      }
    } catch (e) {
      setMessage(e.message);
    } finally {
      setBusy(false);
    }
  };
  const findings = health.findings.filter(
    (f) =>
      f.status !== "resolved" &&
      ["metadata", "accessibility"].includes(f.category) === seo,
  );
  const optional = (f) =>
    ["missing-caption", "missing-description"].includes(f.rule_id);
  const renderFinding = (finding) => (
    <article className="vm-health-finding" key={finding.id}>
      <div>
        <Badge value={finding.severity} /> <Badge value={finding.status} />
        <span className="vm-deduction">
          {health.score !== null && finding.status === "open"
            ? `−${health.deductions[finding.rule_id] || 0}`
            : ""}
        </span>
      </div>
      <h4>{finding.title}</h4>
      <p>{finding.recommendation}</p>
      <TechnicalDetails title={__("Health details", "vtx-media")}>
        {" "}
        <small>
          {Math.round(finding.confidence * 100)}%{" "}
          {__("confidence", "vtx-media")} ·{" "}
          {finding.kind === "heuristic"
            ? __("Heuristic", "vtx-media")
            : __("Objective check", "vtx-media")}
        </small>
        <p>
          {finding.rule_id} / {finding.rule_version}
        </p>
      </TechnicalDetails>
      <details>
        <summary>{__("Details", "vtx-media")}</summary>
        <p>{finding.explanation}</p>
        <Evidence data={finding.data} />
      </details>
      <div className="vm-audit-actions">
        {finding.remediation
          .filter((a) => a.startsWith("edit-"))
          .map((a) => (
            <Button key={a} onClick={() => context.focusField(a.slice(5))}>
              {__("Edit metadata", "vtx-media")}
            </Button>
          ))}
        <Button
          disabled={busy || context.dirty || !item.editable}
          onClick={() =>
            run(finding.status === "ignored" ? "restore" : "ignore", [
              finding.id,
            ])
          }
        >
          {finding.status === "ignored"
            ? __("Restore issue", "vtx-media")
            : __("Ignore", "vtx-media")}
        </Button>
      </div>
    </article>
  );
  return (
    <section
      className="vm-health-section"
      aria-label={__("Media health", "vtx-media")}
    >
      <div className="vm-health-heading">
        <h3>
          {seo
            ? __("SEO & Accessibility review", "vtx-media")
            : __("Health", "vtx-media")}
        </h3>
        {!seo && (
          <strong>
            {health.score === null
              ? labels[health.state]
              : `${health.score}/100`}
          </strong>
        )}
      </div>
      {seo && health.decorative && (
        <span className="vm-audit-badge">
          {__("Decorative image — ALT intentionally left empty.", "vtx-media")}
        </span>
      )}
      {health.errors?.length > 0 && (
        <div>
          <p>
            {__(
              "Some checks could not finish. Re-scan this file to try again.",
              "vtx-media",
            )}
          </p>
          <TechnicalDetails title={__("Check errors", "vtx-media")}>
            {health.errors.join(", ")}
          </TechnicalDetails>
        </div>
      )}
      <div className="vm-audit-actions">
        {!seo && (
          <Button
            disabled={busy || context.dirty || !item.editable}
            onClick={() => run("rescan", [item.id])}
          >
            {__("Re-scan", "vtx-media")}
          </Button>
        )}
        {seo && item.image && (
          <Button
            disabled={busy || context.dirty || !item.editable}
            onClick={() =>
              run(health.decorative ? "remove-decorative" : "decorative", [
                item.id,
              ])
            }
          >
            {health.decorative
              ? __("Remove decorative status", "vtx-media")
              : __("Mark as decorative", "vtx-media")}
          </Button>
        )}
      </div>
      {context.dirty && (
        <p>
          {__("Save metadata changes before using audit actions.", "vtx-media")}
        </p>
      )}
      {message && <p role="status">{message}</p>}
      {findings.filter((f) => !optional(f)).map(renderFinding)}
      {findings.some(optional) && (
        <details className="vm-score-help">
          <summary>{__("Optional metadata improvements", "vtx-media")}</summary>
          {findings.filter(optional).map(renderFinding)}
        </details>
      )}
      {!seo && (
        <details className="vm-score-help">
          <summary>{__("Why this score?", "vtx-media")}</summary>
          {health.score !== null && (
            <ul>
              {health.findings
                .filter((f) => f.status === "open")
                .map((f) => (
                  <li key={f.id}>
                    {f.title}{" "}
                    <strong>−{health.deductions[f.rule_id] || 0}</strong>
                  </li>
                ))}
            </ul>
          )}
          <p>
            {__(
              "Start at 100. Open findings deduct Critical 60, High 20, Medium 10, Low 3 or Info 1. Category limits: Files 80, Accessibility 30, Performance 25, Metadata 8; other categories 20. Ignored and resolved findings deduct nothing. Incomplete or outdated audits have no current score.",
              "vtx-media",
            )}
          </p>
        </details>
      )}
    </section>
  );
}

export function SEOSection(props) {
  return <HealthSection {...props} seo />;
}
