import { getScreen, LibrarySidebar } from "./extensions";
import { api, query, config } from "./api";
import { defaults, libraryItems, initialFilters } from "./library";
import { Icon, Button, Thumb, bytes, date } from "./components/ui";
import {
  FolderBranch,
  FolderDialog,
  MoveDialog,
  DeleteDialog,
} from "./components/folders";
import { AuthorFilter } from "./components/AuthorFilter";
import { Inspector } from "./components/Inspector";
const { useState, useEffect, useRef, useCallback } = wp.element;
const { __, _n, sprintf } = wp.i18n;
export default function App() {
  const [screen, setScreen] = useState(
    () =>
      new URLSearchParams(window.location.search).get("vm_screen") || "library",
  );
  const [, setExtensionRevision] = useState(0);
  useEffect(() => {
    const update = () => setExtensionRevision((n) => n + 1);
    window.addEventListener("vtx-media-extensions", update);
    return () => window.removeEventListener("vtx-media-extensions", update);
  }, []);
  const Screen = config.modules?.some((m) => m.id === screen)
    ? getScreen(screen)
    : null;
  const navigateScreen = (id) => {
    if (!guard()) return;
    setActive(0);
    setScreen(id);
    const url = new URL(window.location.href);
    url.searchParams.set("vm_screen", id);
    window.history.replaceState(null, "", url);
  };
  const [filters, setFilters] = useState(initialFilters),
    [search, setSearch] = useState(() => initialFilters().search),
    [layout, setLayout] = useState(() => {
      try {
        return localStorage.getItem("vtx-media-layout") === "list"
          ? "list"
          : "grid";
      } catch {
        return "grid";
      }
    }),
    [data, setData] = useState({ items: [], total: 0, pages: 0 }),
    [loading, setLoading] = useState(true),
    [error, setError] = useState(""),
    [selected, setSelected] = useState([]),
    [active, setActive] = useState(0),
    [folder, setFolder] = useState(null),
    [revision, setRevision] = useState(0),
    [modal, setModal] = useState(null),
    [notice, setNotice] = useState(""),
    [busy, setBusy] = useState(false),
    [indexing, setIndexing] = useState(null),
    [navOpen, setNavOpen] = useState(false);
  const dirty = useRef(false),
    onDirty = useCallback((value) => {
      dirty.current = value;
    }, []),
    abortRef = useRef(),
    selectAnchor = useRef(0);
  const guard = () =>
    !dirty.current ||
    window.confirm(__("Discard unsaved metadata changes?", "vtx-media"));
  useEffect(() => {
    const navigate = (e) => {
      const { screen: next, section } = e.detail || {};
      if (!config.modules?.some((m) => m.id === next) || !guard()) return;
      const url = new URL(location.href);
      url.searchParams.set("vm_screen", next);
      if (section && /^[a-z][a-z0-9-]{1,63}$/.test(section))
        url.searchParams.set("vm_advanced", section);
      history.replaceState(null, "", url);
      setActive(0);
      if (next === "library" && section)
        setFilters({ ...defaults, collection: section });
      setScreen(next);
    };
    window.addEventListener("vtx-media-navigate", navigate);
    return () => window.removeEventListener("vtx-media-navigate", navigate);
  }, []);
  const change = (patch) => {
    if (!guard()) return;
    setActive(0);
    setSelected([]);
    setFilters((f) => ({
      ...f,
      ...((patch.view || patch.folder !== undefined) &&
      patch.collection === undefined
        ? { collection: "" }
        : {}),
      ...patch,
      page: patch.page || 1,
    }));
    setNavOpen(false);
  };
  useEffect(() => {
    const timer = setTimeout(() => {
      if (search !== filters.search) {
        if (guard()) {
          setActive(0);
          setFilters((f) => ({ ...f, search, page: 1 }));
        } else setSearch(filters.search);
      }
    }, 300);
    return () => clearTimeout(timer);
  }, [search, filters.search]);
  useEffect(() => {
    const params = new URLSearchParams(location.search);
    Object.keys(filters).forEach((k) => {
      if (filters[k] === defaults[k]) params.delete("vm_" + k);
      else params.set("vm_" + k, filters[k]);
    });
    history.replaceState(null, "", location.pathname + "?" + params);
  }, [filters]);
  useEffect(() => {
    try {
      localStorage.setItem("vtx-media-layout", layout);
    } catch {
      /* Optional preference storage. */
    }
  }, [layout]);
  useEffect(() => {
    if (!filters.folder) {
      setFolder(null);
      return;
    }
    const abort = new AbortController();
    api("folders/" + filters.folder, { signal: abort.signal })
      .then(setFolder)
      .catch((e) => {
        if (e.name !== "AbortError") setError(e.message);
      });
    return () => abort.abort();
  }, [filters.folder, revision]);
  useEffect(() => {
    const abort = new AbortController();
    abortRef.current = abort;
    setLoading(true);
    setError("");
    setSelected([]);
    (async () => {
      try {
        if (["largest", "smallest"].includes(filters.sort)) {
          let result;
          setIndexing(__("Preparing file sizes…", "vtx-media"));
          do {
            result = await api("size-index", {
              method: "POST",
              signal: abort.signal,
            });
            if (result.remaining)
              setIndexing(
                sprintf(
                  /* translators: %d is the number of attachments still awaiting a size fact. */ __(
                    "%d file sizes remaining…",
                    "vtx-media",
                  ),
                  result.remaining,
                ),
              );
          } while (result.remaining && !abort.signal.aborted);
        }
        setIndexing(null);
        const result = await api(query("media", filters), {
          signal: abort.signal,
        });
        if (result.pages && filters.page > result.pages) {
          setFilters((f) => ({ ...f, page: result.pages }));
          return;
        }
        setData(result);
      } catch (e) {
        if (e.name !== "AbortError") setError(e.message);
      } finally {
        if (!abort.signal.aborted) {
          setLoading(false);
          setIndexing(null);
        }
      }
    })();
    return () => abort.abort();
  }, [filters, revision]);
  useEffect(() => {
    if (!notice) return;
    const timer = setTimeout(() => setNotice(""), 5000);
    return () => clearTimeout(timer);
  }, [notice]);
  const refresh = (message) => {
    setModal(null);
    setRevision((r) => r + 1);
    if (message) setNotice(message);
  };
  const favorite = async (ids, value) => {
    if (busy) return;
    setBusy(true);
    try {
      await api("favorites", {
        method: "POST",
        body: { ids, favorite: value },
      });
      refresh(
        value
          ? __("Added to your favorites.", "vtx-media")
          : __("Removed from your favorites.", "vtx-media"),
      );
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };
  const move = (ids) => setModal({ type: "move", ids });
  const drop = async (e, target) => {
    if (busy) return;
    const folderId = Number(e.dataTransfer.getData("application/x-vtx-folder"));
    let ids;
    try {
      ids = JSON.parse(
        e.dataTransfer.getData("application/x-vtx-media") || "[]",
      );
    } catch {
      return;
    }
    if (!folderId && !ids.length) return;
    setBusy(true);
    try {
      if (folderId) {
        await api("folders/" + folderId, {
          method: "PATCH",
          body: { parent_id: target },
        });
        refresh(__("Folder moved.", "vtx-media"));
      } else {
        await api("move", { method: "POST", body: { ids, folder_id: target } });
        refresh(__("Media moved.", "vtx-media"));
      }
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };
  const choose = (item, event, index) => {
    if (event?.shiftKey) {
      const start = Math.min(selectAnchor.current, index),
        end = Math.max(selectAnchor.current, index);
      setSelected((old) => [
        ...new Set([
          ...old,
          ...data.items
            .slice(start, end + 1)
            .filter((i) => i.editable)
            .map((i) => i.id),
        ]),
      ]);
      return;
    }
    selectAnchor.current = index;
    if (!guard()) return;
    setActive(item.id);
  };
  const toggle = (id) =>
    setSelected((old) =>
      old.includes(id) ? old.filter((v) => v !== id) : [...old, id],
    );
  const navKey = filters.folder
    ? "folder"
    : filters.view === "all"
      ? filters.type
      : filters.view;
  const title =
    (filters.collection ? data.collection_label || "Smart Folder" : null) ||
    folder?.name ||
    libraryItems.find((i) => i[0] === navKey)?.[1] ||
    __("All Media", "vtx-media");
  const editable = data.items.filter((i) => i.editable).map((i) => i.id);
  return (
    <>
      <header className="vm-appbar">
        <div className="vm-brand">
          <img src={config.logo} alt="youneed.dev" />
          <div className="vm-brand-copy">
            <span className="vm-kicker">
              {__("VTX Labs / WordPress Tools", "vtx-media")}
            </span>
            <div className="vm-title-row">
              <h1>{__("Media", "vtx-media")}</h1>
              <span className="vm-version">v{config.version}</span>
            </div>
            <p>{__("A clear view of every file.", "vtx-media")}</p>
          </div>
        </div>
        <div className="vm-header-actions">
          <a className="vm-button" href={config.native}>
            <Icon name="external" />
            {__("WordPress Library", "vtx-media")}
          </a>
          <a className="vm-button vm-primary" href={config.upload}>
            <Icon name="upload" />
            {__("Upload media", "vtx-media")}
          </a>
        </div>
      </header>
      <div className="vm-workspace">
        <div className="vm-workspace-bar">
          <nav
            className="vm-module-nav"
            aria-label={__("VTX Media sections", "vtx-media")}
          >
            {(config.modules || [])
              .filter((m) => m.id === "library" || getScreen(m.id))
              .map((m) => (
                <button
                  key={m.id}
                  aria-current={screen === m.id ? "page" : undefined}
                  onClick={() => navigateScreen(m.id)}
                >
                  <Icon name={m.icon} />
                  {m.label}
                </button>
              ))}
          </nav>
          <small>
            <span className="vm-status-dot" />
            {__("Connected to WordPress", "vtx-media")}
          </small>
        </div>
        {Screen && (
          <Screen
            onOpen={(id) => {
              if (guard()) {
                setScreen("library");
                setActive(id);
                const url = new URL(window.location.href);
                url.searchParams.set("vm_screen", "library");
                window.history.replaceState(null, "", url);
              }
            }}
          />
        )}
        <div
          hidden={!!Screen}
          className={"vm-columns" + (active ? " has-inspector" : "")}
        >
          <aside className={"vm-sidebar" + (navOpen ? " is-open" : "")}>
            <button
              className="vm-mobile-nav"
              aria-expanded={navOpen}
              onClick={() => setNavOpen(!navOpen)}
            >
              <Icon name="menu" />
              {__("Library & folders", "vtx-media")}
              <Icon name={navOpen ? "arrow-up-alt2" : "arrow-down-alt2"} />
            </button>
            <div className="vm-sidebar-content">
              <span className="vm-label">{__("Library", "vtx-media")}</span>
              <nav aria-label={__("Library views", "vtx-media")}>
                {libraryItems.map(([key, label, icon, type]) => (
                  <button
                    key={key}
                    className={
                      "vm-nav-item" + (navKey === key ? " is-active" : "")
                    }
                    aria-current={navKey === key ? "page" : undefined}
                    title={
                      key === "recent"
                        ? __("Uploaded in the last 30 days", "vtx-media")
                        : key === "favorites"
                          ? __("Your personal favorites", "vtx-media")
                          : undefined
                    }
                    onClick={() =>
                      change({
                        view: ["favorites", "recent", "unorganized"].includes(
                          key,
                        )
                          ? key
                          : "all",
                        type,
                        folder: 0,
                      })
                    }
                    onDragOver={(e) => {
                      if (
                        key === "unorganized" &&
                        e.dataTransfer.types.includes("application/x-vtx-media")
                      )
                        e.preventDefault();
                    }}
                    onDrop={(e) => {
                      if (key === "unorganized") {
                        e.preventDefault();
                        drop(e, 0);
                      }
                    }}
                  >
                    <Icon name={icon} />
                    <span>{label}</span>
                  </button>
                ))}
              </nav>
              <LibrarySidebar
                collection={filters.collection}
                onSelect={(collection) => change({ ...defaults, collection })}
                revision={revision}
              />
              <div className="vm-folder-heading">
                <span className="vm-label">{__("Folders", "vtx-media")}</span>
                {config.manageFolders && (
                  <button
                    aria-label={__("New folder", "vtx-media")}
                    onClick={() =>
                      setModal({
                        type: "folder",
                        parent: folder
                          ? { id: folder.id, name: folder.name }
                          : null,
                      })
                    }
                  >
                    <Icon name="plus-alt2" />
                  </button>
                )}
              </div>
              <FolderBranch
                selected={filters.folder}
                onSelect={(f) => {
                  change({ view: "all", type: "all", folder: f.id });
                }}
                onEdit={(f) =>
                  setModal({
                    type: "folder",
                    folder: f,
                    parent: {
                      id: f.parent_id,
                      name: f.parent_name || __("Top level", "vtx-media"),
                    },
                  })
                }
                onDrop={drop}
                revision={revision}
              />
              {config.manageFolders && (
                <Button
                  className="vm-new-folder"
                  icon="plus-alt2"
                  onClick={() =>
                    setModal({
                      type: "folder",
                      parent: folder
                        ? { id: folder.id, name: folder.name }
                        : null,
                    })
                  }
                >
                  {__("New folder", "vtx-media")}
                </Button>
              )}
              <p className="vm-sidebar-note">
                <Icon name="shield" />
                {__(
                  "Virtual folders. Original files and URLs stay in place.",
                  "vtx-media",
                )}
              </p>
            </div>
          </aside>
          <main
            className="vm-browser"
            aria-label={__("Media browser", "vtx-media")}
          >
            <header className="vm-browser-heading">
              <div>
                <span className="vm-kicker">
                  {filters.folder
                    ? __("Folder", "vtx-media")
                    : __("Your library", "vtx-media")}
                </span>
                <h2 title={title}>{title}</h2>
                <p>
                  {loading
                    ? __("Loading media…", "vtx-media")
                    : sprintf(
                        /* translators: %d is the attachment count. */ _n(
                          "%d item",
                          "%d items",
                          data.total,
                          "vtx-media",
                        ),
                        data.total,
                      )}
                  {filters.view === "recent"
                    ? " · " + __("Last 30 days", "vtx-media")
                    : filters.view === "favorites"
                      ? " · " + __("Only your favorites", "vtx-media")
                      : ""}
                </p>
              </div>
              <div className="vm-heading-actions">
                {folder && config.manageFolders && (
                  <>
                    <Button
                      icon="edit"
                      aria-label={__("Edit current folder", "vtx-media")}
                      onClick={() =>
                        setModal({
                          type: "folder",
                          folder,
                          parent: {
                            id: folder.parent_id,
                            name: folder.parent_id
                              ? sprintf(
                                  /* translators: %d is a VTX folder ID. */ __(
                                    "Folder #%d",
                                    "vtx-media",
                                  ),
                                  folder.parent_id,
                                )
                              : __("Top level", "vtx-media"),
                          },
                        })
                      }
                    />
                    <Button
                      icon="trash"
                      aria-label={__("Delete current folder", "vtx-media")}
                      onClick={() => setModal({ type: "delete", folder })}
                    />
                  </>
                )}
                <div
                  className="vm-view-toggle"
                  role="group"
                  aria-label={__("View layout", "vtx-media")}
                >
                  <Button
                    icon="grid-view"
                    aria-label={__("Grid view", "vtx-media")}
                    aria-pressed={layout === "grid"}
                    onClick={() => setLayout("grid")}
                  />
                  <Button
                    icon="list-view"
                    aria-label={__("List view", "vtx-media")}
                    aria-pressed={layout === "list"}
                    onClick={() => setLayout("list")}
                  />
                </div>
              </div>
            </header>
            <div className="vm-toolbar">
              <label className="vm-search">
                <span className="screen-reader-text">
                  {__("Search media", "vtx-media")}
                </span>
                <Icon name="search" />
                <input
                  type="search"
                  placeholder={__(
                    "Search names, captions, descriptions…",
                    "vtx-media",
                  )}
                  maxLength={191}
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                />
              </label>
              <label className="vm-sort">
                <span className="screen-reader-text">
                  {__("Sort media", "vtx-media")}
                </span>
                <select
                  aria-label={__("Sort media", "vtx-media")}
                  value={filters.sort}
                  onChange={(e) => change({ sort: e.target.value })}
                >
                  {[
                    ["newest", __("Newest first", "vtx-media")],
                    ["oldest", __("Oldest first", "vtx-media")],
                    ["name_asc", __("Name A–Z", "vtx-media")],
                    ["name_desc", __("Name Z–A", "vtx-media")],
                    ["largest", __("Largest first", "vtx-media")],
                    ["smallest", __("Smallest first", "vtx-media")],
                  ].map(([v, label]) => (
                    <option value={v} key={v}>
                      {label}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <div className="vm-filters">
              <label>
                <span className="screen-reader-text">
                  {__("Media type", "vtx-media")}
                </span>
                <select
                  aria-label={__("Media type", "vtx-media")}
                  value={filters.type}
                  onChange={(e) => change({ type: e.target.value })}
                >
                  {[
                    ["all", __("All types", "vtx-media")],
                    ["image", __("Images", "vtx-media")],
                    ["video", __("Video", "vtx-media")],
                    ["audio", __("Audio", "vtx-media")],
                    ["document", __("Documents", "vtx-media")],
                  ].map(([v, l]) => (
                    <option key={v} value={v}>
                      {l}
                    </option>
                  ))}
                </select>
              </label>
              <label className="vm-month">
                <span>{__("Month", "vtx-media")}</span>
                <input
                  type="month"
                  aria-label={__("Upload month", "vtx-media")}
                  value={filters.date}
                  onChange={(e) => change({ date: e.target.value })}
                />
              </label>
              {config.allMedia && (
                <AuthorFilter
                  value={filters.author}
                  onChange={(author) => change({ author })}
                />
              )}
              <Button
                className="vm-text-button"
                icon="image-rotate"
                onClick={() => {
                  if (!guard()) return;
                  setSearch("");
                  setFilters({ ...defaults });
                  setActive(0);
                }}
              >
                {__("Reset filters", "vtx-media")}
              </Button>
            </div>
            {notice && (
              <div className="vm-notice" role="status">
                <Icon name="yes-alt" />
                {notice}
              </div>
            )}
            {error && (
              <div className="vm-error" role="alert">
                {error}
                <Button onClick={() => setRevision((r) => r + 1)}>
                  {__("Retry", "vtx-media")}
                </Button>
              </div>
            )}
            <div className="vm-selection-bar">
              <label>
                <input
                  type="checkbox"
                  aria-label={__("Select all items on this page", "vtx-media")}
                  disabled={loading || !editable.length}
                  checked={
                    !!editable.length &&
                    editable.every((id) => selected.includes(id))
                  }
                  onChange={() =>
                    setSelected(
                      editable.every((id) => selected.includes(id))
                        ? []
                        : editable,
                    )
                  }
                />
                <span>
                  {selected.length
                    ? sprintf(
                        /* translators: %d is the selected attachment count. */ __(
                          "%d selected",
                          "vtx-media",
                        ),
                        selected.length,
                      )
                    : __("Select page", "vtx-media")}
                </span>
              </label>
              {selected.length > 0 ? (
                <div>
                  <Button
                    icon="category"
                    disabled={busy || !config.canOrganize}
                    onClick={() => move(selected)}
                  >
                    {__("Move", "vtx-media")}
                  </Button>
                  <Button
                    icon="star-empty"
                    disabled={busy || !config.canOrganize}
                    onClick={() => favorite(selected, true)}
                  >
                    {__("Favorite", "vtx-media")}
                  </Button>
                  <Button
                    icon="star-filled"
                    disabled={busy || !config.canOrganize}
                    onClick={() => favorite(selected, false)}
                  >
                    {__("Unfavorite", "vtx-media")}
                  </Button>
                  <Button onClick={() => setSelected([])}>
                    {__("Clear", "vtx-media")}
                  </Button>
                </div>
              ) : (
                <span className="vm-selection-hint">
                  {__(
                    "Select a file to inspect. Drag to organize.",
                    "vtx-media",
                  )}
                </span>
              )}
              {active > 0 && (
                <a className="vm-jump" href="#vm-inspector">
                  {__("Go to inspector", "vtx-media")}
                </a>
              )}
            </div>
            {indexing && (
              <div className="vm-indexing" role="status">
                <Icon name="update" />
                {indexing}
                <Button onClick={() => change({ sort: "newest" })}>
                  {__("Cancel", "vtx-media")}
                </Button>
              </div>
            )}
            <div aria-busy={loading} className="vm-results">
              {loading ? (
                <div className="vm-loading" role="status">
                  <span className="vm-spinner" />
                  {__("Loading your library…", "vtx-media")}
                </div>
              ) : !data.items.length ? (
                <div className="vm-empty">
                  <span className="vm-empty-icon">
                    <Icon
                      name={
                        filters.view === "favorites"
                          ? "star-empty"
                          : "format-gallery"
                      }
                    />
                  </span>
                  <h3>
                    {filters.collection
                      ? __("No media matches this Smart Folder.", "vtx-media")
                      : __("No media here yet", "vtx-media")}
                  </h3>
                  <p>
                    {search ||
                    filters.date ||
                    filters.type !== "all" ||
                    filters.author
                      ? __(
                          "Try another search or adjust your filters.",
                          "vtx-media",
                        )
                      : filters.view === "favorites"
                        ? __(
                            "Star a file to keep it close. Your favorites are personal.",
                            "vtx-media",
                          )
                        : filters.folder
                          ? __(
                              "Move media into this folder using drag and drop or the Move action.",
                              "vtx-media",
                            )
                          : __(
                              "Upload media or choose another library view.",
                              "vtx-media",
                            )}
                  </p>
                  <Button
                    onClick={() => {
                      setSearch("");
                      change({ ...defaults });
                    }}
                  >
                    {__("View all media", "vtx-media")}
                  </Button>
                </div>
              ) : layout === "grid" ? (
                <div className="vm-grid">
                  {data.items.map((item, index) => (
                    <article
                      key={item.id}
                      data-media-id={item.id}
                      className={
                        "vm-card" +
                        (active === item.id ? " is-inspected" : "") +
                        (selected.includes(item.id) ? " is-selected" : "")
                      }
                      draggable={item.editable}
                      onDragStart={(e) => {
                        e.dataTransfer.setData(
                          "application/x-vtx-media",
                          JSON.stringify(
                            selected.includes(item.id) ? selected : [item.id],
                          ),
                        );
                        e.dataTransfer.effectAllowed = "move";
                      }}
                    >
                      <div className="vm-card-visual">
                        <button
                          className="vm-open-card"
                          aria-label={sprintf(
                            /* translators: %s is the attachment filename. */ __(
                              "Inspect %s",
                              "vtx-media",
                            ),
                            item.filename,
                          )}
                          aria-pressed={active === item.id}
                          onClick={(e) => choose(item, e, index)}
                        >
                          <Thumb item={item} />
                        </button>
                        <input
                          className="vm-card-check"
                          type="checkbox"
                          aria-label={sprintf(
                            /* translators: %s is the attachment filename. */ __(
                              "Select %s",
                              "vtx-media",
                            ),
                            item.filename,
                          )}
                          disabled={!item.editable}
                          checked={selected.includes(item.id)}
                          onChange={() => toggle(item.id)}
                        />
                        <button
                          className={
                            "vm-card-star" +
                            (item.favorite ? " is-favorite" : "")
                          }
                          disabled={
                            busy || !item.editable || !config.canOrganize
                          }
                          aria-label={
                            item.favorite
                              ? sprintf(
                                  /* translators: %s is the attachment filename. */ __(
                                    "Unfavorite %s",
                                    "vtx-media",
                                  ),
                                  item.filename,
                                )
                              : sprintf(
                                  /* translators: %s is the attachment filename. */ __(
                                    "Favorite %s",
                                    "vtx-media",
                                  ),
                                  item.filename,
                                )
                          }
                          aria-pressed={item.favorite}
                          onClick={() => favorite([item.id], !item.favorite)}
                        >
                          <Icon
                            name={item.favorite ? "star-filled" : "star-empty"}
                          />
                        </button>
                      </div>
                      <button
                        className="vm-card-caption"
                        onClick={(e) => choose(item, e, index)}
                      >
                        <strong title={item.filename}>{item.filename}</strong>
                        <span>
                          {item.extension.toUpperCase() || item.mime} <i>·</i>{" "}
                          {bytes(item.bytes)}
                        </span>
                      </button>
                    </article>
                  ))}
                </div>
              ) : (
                <div className="vm-table-wrap">
                  <table className="vm-table">
                    <thead>
                      <tr>
                        <th>
                          <span className="screen-reader-text">
                            {__("Select", "vtx-media")}
                          </span>
                        </th>
                        <th>{__("File", "vtx-media")}</th>
                        <th>{__("Size", "vtx-media")}</th>
                        <th>{__("Uploaded", "vtx-media")}</th>
                        <th>{__("Favorite", "vtx-media")}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.items.map((item, index) => (
                        <tr
                          key={item.id}
                          data-media-id={item.id}
                          className={
                            selected.includes(item.id) || active === item.id
                              ? "is-selected"
                              : ""
                          }
                          draggable={item.editable}
                          onDragStart={(e) => {
                            e.dataTransfer.setData(
                              "application/x-vtx-media",
                              JSON.stringify(
                                selected.includes(item.id)
                                  ? selected
                                  : [item.id],
                              ),
                            );
                            e.dataTransfer.effectAllowed = "move";
                          }}
                        >
                          <td>
                            <input
                              type="checkbox"
                              aria-label={sprintf(
                                /* translators: %s is the attachment filename. */ __(
                                  "Select %s",
                                  "vtx-media",
                                ),
                                item.filename,
                              )}
                              disabled={!item.editable}
                              checked={selected.includes(item.id)}
                              onChange={() => toggle(item.id)}
                            />
                          </td>
                          <td>
                            <button
                              className="vm-list-file"
                              aria-label={sprintf(
                                /* translators: %s is the attachment filename. */ __(
                                  "Inspect %s",
                                  "vtx-media",
                                ),
                                item.filename,
                              )}
                              onClick={(e) => choose(item, e, index)}
                            >
                              <Thumb item={item} />
                              <span>
                                <strong>{item.filename}</strong>
                                <small>
                                  {item.mime} ·{" "}
                                  {item.folder_name ||
                                    __("Unorganized", "vtx-media")}
                                </small>
                              </span>
                            </button>
                          </td>
                          <td>{bytes(item.bytes)}</td>
                          <td>{date(item.date)}</td>
                          <td>
                            <Button
                              icon={
                                item.favorite ? "star-filled" : "star-empty"
                              }
                              className={item.favorite ? "vm-star-active" : ""}
                              disabled={
                                busy || !item.editable || !config.canOrganize
                              }
                              aria-label={sprintf(
                                item.favorite
                                  ? /* translators: %s is the attachment filename. */ __(
                                      "Unfavorite %s",
                                      "vtx-media",
                                    )
                                  : /* translators: %s is the attachment filename. */ __(
                                      "Favorite %s",
                                      "vtx-media",
                                    ),
                                item.filename,
                              )}
                              aria-pressed={item.favorite}
                              onClick={() =>
                                favorite([item.id], !item.favorite)
                              }
                            />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
            <footer className="vm-pagination">
              <span>
                {data.total
                  ? sprintf(
                      /* translators: 1: First displayed item, 2: Last displayed item, 3: Total items. */ __(
                        "%1$d–%2$d of %3$d",
                        "vtx-media",
                      ),
                      (filters.page - 1) * 48 + 1,
                      Math.min(filters.page * 48, data.total),
                      data.total,
                    )
                  : __("0 items", "vtx-media")}
              </span>
              <div>
                <Button
                  icon="arrow-left-alt2"
                  aria-label={__("Previous page", "vtx-media")}
                  disabled={loading || filters.page <= 1}
                  onClick={() => change({ page: filters.page - 1 })}
                />
                <span>
                  {sprintf(
                    /* translators: 1: Current page, 2: Total pages. */ __(
                      "Page %1$d of %2$d",
                      "vtx-media",
                    ),
                    filters.page,
                    Math.max(1, data.pages),
                  )}
                </span>
                <Button
                  icon="arrow-right-alt2"
                  aria-label={__("Next page", "vtx-media")}
                  disabled={loading || filters.page >= data.pages}
                  onClick={() => change({ page: filters.page + 1 })}
                />
              </div>
            </footer>
          </main>
          {active ? (
            <Inspector
              key={active}
              id={active}
              revision={revision}
              onClose={() => {
                if (guard()) setActive(0);
              }}
              onSaved={refresh}
              onMove={move}
              onFavorite={favorite}
              onDirty={onDirty}
            />
          ) : (
            <aside className="vm-inspector vm-inspector-empty">
              <span className="vm-kicker">
                {__("Media Inspector", "vtx-media")}
              </span>
              <div>
                <span className="vm-empty-icon">
                  <Icon name="search" />
                </span>
                <h3>{__("Every detail, in view.", "vtx-media")}</h3>
                <p>
                  {__(
                    "Select a file to see its information, organize it, and edit its metadata.",
                    "vtx-media",
                  )}
                </p>
                <small>
                  {__(
                    "Your WordPress media. One clear workspace.",
                    "vtx-media",
                  )}
                </small>
              </div>
            </aside>
          )}
        </div>
      </div>
      <p className="vm-footer-note">
        {__("VTX Media · Built around your WordPress library", "vtx-media")}
      </p>
      {modal?.type === "folder" && (
        <FolderDialog
          folder={modal.folder}
          parent={modal.parent}
          onClose={() => setModal(null)}
          onSaved={() => refresh(__("Folder saved.", "vtx-media"))}
        />
      )}
      {modal?.type === "move" && (
        <MoveDialog
          ids={modal.ids}
          onClose={() => setModal(null)}
          onSaved={() => refresh(__("Media moved.", "vtx-media"))}
        />
      )}
      {modal?.type === "delete" && (
        <DeleteDialog
          folder={modal.folder}
          onClose={() => setModal(null)}
          onSaved={() => {
            setFilters((f) => ({
              ...f,
              folder: 0,
              view: "unorganized",
              page: 1,
            }));
            refresh(__("Folder deleted. Media files are safe.", "vtx-media"));
          }}
        />
      )}
    </>
  );
}
