import { InspectorSlot } from "../extensions";
import { TechnicalDetails } from "./presentation";
import { api, config } from "../api";
import { Icon, Button, Thumb, bytes, date } from "./ui";
const { useState, useEffect, useRef } = wp.element;
const { __ } = wp.i18n;
export function Inspector({
  id,
  revision,
  onClose,
  onSaved,
  onMove,
  onFavorite,
  onDirty,
}) {
  const [item, setItem] = useState(null),
    [draft, setDraft] = useState(null),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(false),
    [copied, setCopied] = useState(false),
    [loading, setLoading] = useState(true);
  const dirty = !!(
    item &&
    draft &&
    Object.keys(draft).some((k) => draft[k] !== item[k])
  );
  const dirtyRef = useRef(false);
  dirtyRef.current = dirty;
  useEffect(() => {
    onDirty(dirty);
    const before = (e) => {
      if (dirty) {
        e.preventDefault();
        e.returnValue = "";
      }
    };
    window.addEventListener("beforeunload", before);
    return () => {
      window.removeEventListener("beforeunload", before);
      onDirty(false);
    };
  }, [dirty, onDirty]);
  useEffect(() => {
    const abort = new AbortController();
    setLoading(true);
    api("media/" + id, { signal: abort.signal })
      .then((data) => {
        setItem(data);
        if (!dirtyRef.current)
          setDraft({
            title: data.title,
            alt: data.alt,
            caption: data.caption,
            description: data.description,
          });
        setError("");
      })
      .catch((e) => {
        if (e.name !== "AbortError") setError(e.message);
      })
      .finally(() => {
        if (!abort.signal.aborted) setLoading(false);
      });
    return () => abort.abort();
  }, [id, revision]);
  const save = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError("");
    const changed = Object.fromEntries(
      Object.entries(draft).filter(([k, v]) => v !== item[k]),
    );
    try {
      const data = await api("media/" + id, { method: "PATCH", body: changed });
      setItem(data);
      setDraft({
        title: data.title,
        alt: data.alt,
        caption: data.caption,
        description: data.description,
      });
      onSaved(__("Metadata saved.", "vtx-media"));
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };
  const context = {
    onSaved,
    dirty,
    focusField: (field) => {
      const input = document.getElementById("vm-field-" + field);
      if (input) {
        input.scrollIntoView({ block: "center" });
        input.focus();
      }
    },
  };
  return (
    <aside
      className="vm-inspector"
      id="vm-inspector"
      tabIndex="-1"
      aria-label={__("Media Inspector", "vtx-media")}
    >
      <header>
        <div>
          <span className="vm-kicker">
            {__("Media Inspector", "vtx-media")}
          </span>
          <h2>{__("File details", "vtx-media")}</h2>
        </div>
        <Button
          icon="no-alt"
          aria-label={__("Close inspector", "vtx-media")}
          onClick={onClose}
        />
      </header>
      {loading && !item && (
        <p role="status">{__("Loading file details…", "vtx-media")}</p>
      )}
      {error && (
        <p className="vm-error" role="alert">
          {error}
        </p>
      )}
      {item && (
        <>
          <Thumb item={item} large />
          <div className="vm-inspector-title">
            <h3>{item.filename}</h3>
            <Button
              icon={item.favorite ? "star-filled" : "star-empty"}
              className={item.favorite ? "vm-star-active" : ""}
              disabled={!item.editable || busy || !config.canOrganize}
              aria-label={
                item.favorite
                  ? __("Remove favorite", "vtx-media")
                  : __("Add favorite", "vtx-media")
              }
              aria-pressed={item.favorite}
              onClick={() => onFavorite([id], !item.favorite)}
            />
          </div>
          <span className="vm-file-badge">{item.mime}</span>
          <dl className="vm-facts">
            <dt>{__("File size", "vtx-media")}</dt>
            <dd>{bytes(item.bytes)}</dd>
            {item.width > 0 && (
              <>
                <dt>{__("Dimensions", "vtx-media")}</dt>
                <dd>
                  {item.width} × {item.height} px
                </dd>
              </>
            )}
          </dl>
          {!item.local && (
            <p className="vm-hint">
              {__(
                "No accessible local file. This media may be offloaded or missing.",
                "vtx-media",
              )}
            </p>
          )}
          <label>
            {__("File URL", "vtx-media")}
            <div className="vm-url">
              <input
                value={item.url || ""}
                readOnly
                onFocus={(e) => e.target.select()}
              />
              <Button
                icon={copied ? "yes" : "admin-links"}
                aria-label={__("Copy URL", "vtx-media")}
                onClick={async () => {
                  try {
                    await navigator.clipboard.writeText(item.url);
                    setCopied(true);
                    setTimeout(() => setCopied(false), 2000);
                  } catch {
                    setError(
                      __(
                        "Copy is unavailable. Select and copy the URL field.",
                        "vtx-media",
                      ),
                    );
                  }
                }}
              />
            </div>
          </label>
          {copied && (
            <p role="status" className="vm-hint">
              {__("URL copied.", "vtx-media")}
            </p>
          )}
          <InspectorSlot slot="health" item={item} context={context} />
          {draft && (
            <form className="vm-metadata" onSubmit={save}>
              <div className="vm-section-heading">
                <h3>{__("SEO & Accessibility", "vtx-media")}</h3>
                {dirty && (
                  <span className="vm-unsaved">
                    {__("Unsaved", "vtx-media")}
                  </span>
                )}
              </div>
              <fieldset disabled={!item.editable || busy}>
                <label>
                  {__("Title", "vtx-media")}
                  <input
                    maxLength={1000}
                    id="vm-field-title"
                    value={draft.title}
                    onChange={(e) =>
                      setDraft((d) => ({ ...d, title: e.target.value }))
                    }
                  />
                </label>
                {item.image && (
                  <label>
                    {__("Alternative text", "vtx-media")}
                    <textarea
                      aria-label={__("Alternative text", "vtx-media")}
                      aria-describedby="vm-alt-help"
                      rows="2"
                      maxLength={5000}
                      id="vm-field-alt"
                      value={draft.alt}
                      onChange={(e) =>
                        setDraft((d) => ({ ...d, alt: e.target.value }))
                      }
                    />
                    <small className="vm-alt-state">
                      {item.audit?.decorative
                        ? __(
                            "Decorative — alternative text intentionally empty.",
                            "vtx-media",
                          )
                        : item.audit?.findings?.some(
                              (f) =>
                                f.status === "open" &&
                                f.rule_id === "filename-as-alt",
                            )
                          ? __(
                              "Same as filename — review recommended.",
                              "vtx-media",
                            )
                          : item.audit?.findings?.some(
                                (f) =>
                                  f.status === "open" &&
                                  f.rule_id === "generic-alt",
                              )
                            ? __(
                                "Generic alternative text — review recommended.",
                                "vtx-media",
                              )
                            : item.alt
                              ? __("Alternative text added.", "vtx-media")
                              : __(
                                  "Needs review — decide whether this image is meaningful or decorative.",
                                  "vtx-media",
                                )}
                    </small>
                    <small id="vm-alt-help">
                      {__(
                        "Alternative text helps screen readers understand meaningful images and can provide image context to search engines. Leave empty when intentionally decorative.",
                        "vtx-media",
                      )}
                    </small>
                  </label>
                )}
                <label>
                  {__("Caption", "vtx-media")}{" "}
                  <small>
                    {__(
                      "Optional — use when it helps visitors understand the image.",
                      "vtx-media",
                    )}
                  </small>
                  <textarea
                    rows="2"
                    maxLength={50000}
                    id="vm-field-caption"
                    aria-label={__("Caption", "vtx-media")}
                    value={draft.caption}
                    onChange={(e) =>
                      setDraft((d) => ({ ...d, caption: e.target.value }))
                    }
                  />
                </label>
                <label>
                  {__("Description", "vtx-media")}{" "}
                  <small>
                    {__(
                      "Optional — useful for attachment pages or your media workflow.",
                      "vtx-media",
                    )}
                  </small>
                  <textarea
                    rows="3"
                    maxLength={100000}
                    id="vm-field-description"
                    aria-label={__("Description", "vtx-media")}
                    value={draft.description}
                    onChange={(e) =>
                      setDraft((d) => ({ ...d, description: e.target.value }))
                    }
                  />
                </label>
              </fieldset>
              <div className="vm-save">
                <button
                  className="vm-button vm-primary"
                  disabled={!dirty || busy || !item.editable}
                >
                  {busy
                    ? __("Saving…", "vtx-media")
                    : __("Save changes", "vtx-media")}
                </button>
                {dirty && (
                  <Button
                    disabled={busy}
                    onClick={() =>
                      setDraft({
                        title: item.title,
                        alt: item.alt,
                        caption: item.caption,
                        description: item.description,
                      })
                    }
                  >
                    {__("Reset", "vtx-media")}
                  </Button>
                )}
              </div>
            </form>
          )}
          <InspectorSlot slot="seo" item={item} context={context} />
          <InspectorSlot slot="usage" item={item} context={context} />
          <section className="vm-inspector-organization">
            <h3>{__("Organization", "vtx-media")}</h3>
            <dl className="vm-facts">
              {" "}
              <dt>{__("Uploaded", "vtx-media")}</dt>
              <dd>{date(item.date)}</dd>
              <dt>{__("Uploaded by", "vtx-media")}</dt>
              <dd>{item.author}</dd>
              <dt>{__("Folder", "vtx-media")}</dt>
              <dd>
                {item.folder_name || __("Unorganized", "vtx-media")}{" "}
                {item.editable && (
                  <button
                    className="vm-text-button"
                    disabled={!config.canOrganize}
                    onClick={() => onMove([id])}
                  >
                    {__("Change", "vtx-media")}
                  </button>
                )}
              </dd>
            </dl>
            <InspectorSlot slot="organization" item={item} context={context} />
          </section>
          <InspectorSlot slot="optimization" item={item} context={context} />
          <TechnicalDetails title={__("File technical details", "vtx-media")}>
            <dl className="vm-facts">
              {" "}
              <dt>{__("Attachment ID", "vtx-media")}</dt>
              <dd>#{item.id}</dd>
              {item.file && (
                <>
                  <dt>{__("File", "vtx-media")}</dt>
                  <dd className="vm-path">{item.file}</dd>
                </>
              )}
            </dl>
            <InspectorSlot slot="advanced" item={item} context={context} />
          </TechnicalDetails>
          {wp.hooks.applyFilters("vtx_media.inspectorSections", [], item, {
            onSaved,
            dirty,
            focusField: (field) => {
              const input = document.getElementById("vm-field-" + field);
              if (input) {
                input.scrollIntoView({ block: "center" });
                input.focus();
              }
            },
          })}
        </>
      )}
    </aside>
  );
}
