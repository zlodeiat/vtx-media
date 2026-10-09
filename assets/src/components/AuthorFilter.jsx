import { api, query } from "../api";
const { useState, useEffect } = wp.element;
const { __, sprintf } = wp.i18n;
export function AuthorFilter({ value, onChange }) {
  const [search, setSearch] = useState(""),
    [authors, setAuthors] = useState([]),
    [error, setError] = useState("");
  useEffect(() => {
    const abort = new AbortController();
    const timer = setTimeout(
      () =>
        api(query("authors", { search }), { signal: abort.signal })
          .then(setAuthors)
          .catch((e) => {
            if (e.name !== "AbortError") setError(e.message);
          }),
      200,
    );
    return () => {
      clearTimeout(timer);
      abort.abort();
    };
  }, [search]);
  return (
    <details className="vm-author">
      <summary>
        {value
          ? __("Author filter active", "vtx-media")
          : __("All authors", "vtx-media")}
      </summary>
      <div>
        <label>
          {__("Find author", "vtx-media")}
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </label>
        <label>
          {__("Uploaded by", "vtx-media")}
          <select
            value={value}
            onChange={(e) => onChange(Number(e.target.value))}
          >
            <option value="0">{__("All authors", "vtx-media")}</option>
            {value && !authors.some((a) => Number(a.id) === value) && (
              <option value={value}>
                {sprintf(
                  /* translators: %d is a WordPress user ID. */ __(
                    "Author #%d",
                    "vtx-media",
                  ),
                  value,
                )}
              </option>
            )}
            {authors.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </select>
        </label>
        {error && <p role="alert">{error}</p>}
      </div>
    </details>
  );
}
