import { api, query, config } from "../api";
import { Icon, Button, Dialog } from "./ui";
const { useState, useEffect, useRef } = wp.element;
const { __, sprintf } = wp.i18n;
export function FolderPicker({
  value,
  onChange,
  exclude = 0,
  rootLabel = __("Unorganized", "vtx-media"),
}) {
  const [search, setSearch] = useState(""),
    [rows, setRows] = useState([]),
    [page, setPage] = useState(1),
    [more, setMore] = useState(false),
    [loading, setLoading] = useState(true),
    [error, setError] = useState("");
  useEffect(() => {
    const abort = new AbortController();
    setLoading(true);
    const timer = setTimeout(
      () =>
        api(query("folders", { search, page, parent: 0 }), {
          signal: abort.signal,
        })
          .then((data) => {
            setRows((old) =>
              page === 1 ? data.items : [...old, ...data.items],
            );
            setMore(data.more);
            setError("");
          })
          .catch((e) => {
            if (e.name !== "AbortError") setError(e.message);
          })
          .finally(() => {
            if (!abort.signal.aborted) setLoading(false);
          }),
      200,
    );
    return () => {
      clearTimeout(timer);
      abort.abort();
    };
  }, [search, page]);
  return (
    <div className="vm-picker">
      <label>
        {__("Find a destination", "vtx-media")}
        <input
          type="search"
          placeholder={__("Search all folder names…", "vtx-media")}
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(1);
          }}
        />
      </label>
      <p className="vm-hint">
        {__(
          "Root folders are listed below. Search by name to find any nested folder.",
          "vtx-media",
        )}
      </p>
      <div
        className="vm-picker-options"
        role="group"
        aria-label={__("Destination folders", "vtx-media")}
      >
        <button
          type="button"
          className={!value.id ? "is-active" : ""}
          onClick={() => onChange({ id: 0, name: rootLabel })}
        >
          <Icon name="portfolio" />
          {rootLabel}
          {!value.id && <Icon name="yes" />}
        </button>
        {value.id && !rows.some((f) => f.id === value.id) && (
          <button
            type="button"
            className="is-active"
            onClick={() => onChange(value)}
          >
            <Icon name="category" />
            {value.name}
            <Icon name="yes" />
          </button>
        )}
        {rows
          .filter((f) => f.id !== exclude)
          .map((f) => (
            <button
              type="button"
              key={f.id}
              className={value.id === f.id ? "is-active" : ""}
              onClick={() => onChange({ id: f.id, name: f.name })}
            >
              <Icon name="category" />
              <span>
                {f.name}
                <small>
                  {f.parent_name || __("Root folder", "vtx-media")} · #{f.id}
                </small>
              </span>
              {value.id === f.id && <Icon name="yes" />}
            </button>
          ))}
        {loading && <p role="status">{__("Loading folders…", "vtx-media")}</p>}
        {error && (
          <p role="alert" className="vm-error">
            {error}
          </p>
        )}
        {!loading && more && (
          <Button onClick={() => setPage((p) => p + 1)}>
            {__("Load more folders", "vtx-media")}
          </Button>
        )}
        {!loading && !rows.length && search && (
          <p>{__("No matching folders.", "vtx-media")}</p>
        )}
      </div>
      <p className="vm-hint">
        {__("Destination:", "vtx-media")}{" "}
        <strong>{value.name || rootLabel}</strong>
      </p>
    </div>
  );
}
export function FolderDialog({ folder, parent, onClose, onSaved }) {
  const [name, setName] = useState(folder?.name || ""),
    [target, setTarget] = useState(
      parent || { id: 0, name: __("Top level", "vtx-media") },
    ),
    [position, setPosition] = useState(folder?.position || 0),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const save = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      await api(folder ? "folders/" + folder.id : "folders", {
        method: folder ? "PATCH" : "POST",
        body: { name, parent_id: target.id, position: Number(position) },
      });
      onSaved();
    } catch (e) {
      setError(e.message);
      setBusy(false);
    }
  };
  return (
    <Dialog
      title={
        folder ? __("Edit folder", "vtx-media") : __("New folder", "vtx-media")
      }
      onClose={onClose}
      busy={busy}
    >
      <form onSubmit={save}>
        <label>
          {__("Folder name", "vtx-media")}
          <input
            required
            autoFocus
            maxLength={191}
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
        </label>
        <FolderPicker
          value={target}
          onChange={setTarget}
          exclude={folder?.id}
          rootLabel={__("Top level", "vtx-media")}
        />
        <label>
          {__("Sort position", "vtx-media")}
          <input
            type="number"
            min="0"
            max="1000000"
            required
            value={position}
            onChange={(e) => setPosition(e.target.value)}
          />
        </label>
        <p className="vm-hint">
          {__(
            "Lower positions appear first. Equal positions are sorted by name.",
            "vtx-media",
          )}
        </p>
        {error && (
          <p role="alert" className="vm-error">
            {error}
          </p>
        )}
        <footer>
          <Button disabled={busy} onClick={onClose}>
            {__("Cancel", "vtx-media")}
          </Button>
          <button className="vm-button vm-primary" disabled={busy}>
            {busy ? __("Saving…", "vtx-media") : __("Save folder", "vtx-media")}
          </button>
        </footer>
      </form>
    </Dialog>
  );
}
export function MoveDialog({ ids, onClose, onSaved }) {
  const [target, setTarget] = useState({
      id: 0,
      name: __("Unorganized", "vtx-media"),
    }),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  return (
    <Dialog
      title={sprintf(
        /* translators: %d is the number of selected attachments. */ __(
          "Move %d selected items",
          "vtx-media",
        ),
        ids.length,
      )}
      onClose={onClose}
      busy={busy}
    >
      <p>
        {__(
          "Choose a folder for the selected media. Files and URLs stay the same.",
          "vtx-media",
        )}
      </p>
      <FolderPicker value={target} onChange={setTarget} />
      {error && (
        <p className="vm-error" role="alert">
          {error}
        </p>
      )}
      <footer>
        <Button disabled={busy} onClick={onClose}>
          {__("Cancel", "vtx-media")}
        </Button>
        <Button
          className="vm-primary"
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            try {
              await api("move", {
                method: "POST",
                body: { ids, folder_id: target.id },
              });
              onSaved();
            } catch (e) {
              setError(e.message);
              setBusy(false);
            }
          }}
        >
          {busy ? __("Moving…", "vtx-media") : __("Move media", "vtx-media")}
        </Button>
      </footer>
    </Dialog>
  );
}
export function DeleteDialog({ folder, onClose, onSaved }) {
  const [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  return (
    <Dialog
      title={__("Delete folder?", "vtx-media")}
      onClose={onClose}
      busy={busy}
    >
      <p>
        <strong>{folder.name}</strong>
      </p>
      <p>
        {__(
          "Media in this folder will become Unorganized. Child folders will move up one level. No media files will be deleted.",
          "vtx-media",
        )}
      </p>
      {error && (
        <p role="alert" className="vm-error">
          {error}
        </p>
      )}
      <footer>
        <Button disabled={busy} onClick={onClose}>
          {__("Cancel", "vtx-media")}
        </Button>
        <Button
          className="vm-danger"
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            try {
              await api("folders/" + folder.id, { method: "DELETE" });
              onSaved();
            } catch (e) {
              setError(e.message);
              setBusy(false);
            }
          }}
        >
          {busy
            ? __("Deleting…", "vtx-media")
            : __("Delete folder", "vtx-media")}
        </Button>
      </footer>
    </Dialog>
  );
}
export function FolderBranch({
  parent = 0,
  depth = 0,
  selected,
  onSelect,
  onEdit,
  onDrop,
  revision,
}) {
  const [rows, setRows] = useState([]),
    [page, setPage] = useState(1),
    [more, setMore] = useState(false),
    [open, setOpen] = useState({}),
    [loading, setLoading] = useState(true),
    [error, setError] = useState(""),
    [drag, setDrag] = useState(0),
    [retry, setRetry] = useState(0);
  const version = useRef(revision);
  useEffect(() => {
    if (version.current !== revision) {
      version.current = revision;
      if (page !== 1) {
        setPage(1);
        return;
      }
    }
    const abort = new AbortController();
    setLoading(true);
    api(query("folders", { parent, page }), { signal: abort.signal })
      .then((data) => {
        setRows((old) => (page === 1 ? data.items : [...old, ...data.items]));
        setMore(data.more);
        setError("");
      })
      .catch((e) => {
        if (e.name !== "AbortError") setError(e.message);
      })
      .finally(() => {
        if (!abort.signal.aborted) setLoading(false);
      });
    return () => abort.abort();
  }, [parent, page, revision, retry]);
  return (
    <ul
      className="vm-tree"
      aria-label={
        depth ? __("Subfolders", "vtx-media") : __("Folders", "vtx-media")
      }
    >
      {rows.map((f) => (
        <li key={f.id}>
          <div
            className={
              "vm-folder-row" +
              (selected === f.id ? " is-active" : "") +
              (drag === f.id ? " is-drop" : "")
            }
            style={{ "--depth": Math.min(depth, 8) }}
            draggable={config.manageFolders}
            onDragStart={(e) => {
              e.stopPropagation();
              e.dataTransfer.setData("application/x-vtx-folder", String(f.id));
              e.dataTransfer.effectAllowed = "move";
            }}
            onDragOver={(e) => {
              if (
                [...e.dataTransfer.types].some((v) =>
                  v.startsWith("application/x-vtx-"),
                )
              ) {
                e.preventDefault();
                e.dataTransfer.dropEffect = "move";
                setDrag(f.id);
              }
            }}
            onDragLeave={(e) => {
              if (!e.currentTarget.contains(e.relatedTarget)) setDrag(0);
            }}
            onDrop={(e) => {
              e.preventDefault();
              e.stopPropagation();
              setDrag(0);
              onDrop(e, f.id);
            }}
          >
            {f.children > 0 ? (
              <button
                className="vm-expander"
                aria-label={sprintf(
                  open[f.id]
                    ? /* translators: %s is a folder name. */ __(
                        "Collapse %s",
                        "vtx-media",
                      )
                    : /* translators: %s is a folder name. */ __(
                        "Expand %s",
                        "vtx-media",
                      ),
                  f.name,
                )}
                aria-expanded={!!open[f.id]}
                onClick={() => setOpen((o) => ({ ...o, [f.id]: !o[f.id] }))}
              >
                <Icon
                  name={open[f.id] ? "arrow-down-alt2" : "arrow-right-alt2"}
                />
              </button>
            ) : (
              <span className="vm-expander" />
            )}
            <button
              className="vm-folder-select"
              title={f.name}
              aria-current={selected === f.id ? "page" : undefined}
              onClick={() => onSelect(f)}
            >
              <Icon name="category" />
              <span>{f.name}</span>
              <small aria-label={__("Direct media count", "vtx-media")}>
                {f.count}
              </small>
            </button>
            {config.manageFolders && (
              <button
                className="vm-folder-edit"
                aria-label={sprintf(
                  /* translators: %s is a folder name. */ __(
                    "Edit folder %s",
                    "vtx-media",
                  ),
                  f.name,
                )}
                onClick={() => onEdit(f)}
              >
                <Icon name="ellipsis" />
              </button>
            )}
          </div>
          {open[f.id] && (
            <FolderBranch
              parent={f.id}
              depth={depth + 1}
              selected={selected}
              onSelect={onSelect}
              onEdit={onEdit}
              onDrop={onDrop}
              revision={revision}
            />
          )}
        </li>
      ))}
      {loading && (
        <li className="vm-tree-message" role="status">
          {__("Loading folders…", "vtx-media")}
        </li>
      )}
      {error && (
        <li className="vm-error" role="alert">
          {error}
          <Button
            onClick={() => {
              setRetry((r) => r + 1);
            }}
          >
            {__("Retry", "vtx-media")}
          </Button>
        </li>
      )}
      {!loading && !rows.length && !parent && (
        <li className="vm-tree-message">
          {__("A place for every file. Create your first folder.", "vtx-media")}
        </li>
      )}
      {more && !loading && (
        <li>
          <Button
            className="vm-tree-more"
            onClick={() => setPage((p) => p + 1)}
          >
            {__("Load more folders", "vtx-media")}
          </Button>
        </li>
      )}
    </ul>
  );
}
