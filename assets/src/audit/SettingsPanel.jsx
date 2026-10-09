import { api, config } from "../api";
import { Button } from "../components/ui";
const { useState, useEffect } = wp.element;
const { __ } = wp.i18n;
export function SettingsPanel({ onSaved }) {
  const [data, setData] = useState(null),
    [defaults, setDefaults] = useState(null),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(false);
  useEffect(() => {
    api("audit/settings")
      .then((r) => {
        setData(r.values);
        setDefaults(r.defaults);
      })
      .catch((e) => setError(e.message));
  }, []);
  const fields = [
    [
      "large_bytes",
      __("Large image threshold (MiB)", "vtx-media"),
      0.25,
      100,
      1048576,
    ],
    [
      "very_large_bytes",
      __("Very large image threshold (MiB)", "vtx-media"),
      0.5,
      500,
      1048576,
    ],
    ["max_width", __("Width threshold (pixels)", "vtx-media"), 1000, 50000, 1],
    [
      "max_height",
      __("Height threshold (pixels)", "vtx-media"),
      1000,
      50000,
      1,
    ],
    [
      "alt_length",
      __("ALT length review (characters)", "vtx-media"),
      100,
      2000,
      1,
    ],
  ];
  const save = async (values) => {
    setBusy(true);
    setError("");
    try {
      const result = await api("audit/settings", {
        method: "POST",
        body: values,
      });
      setData(result);
      onSaved();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };
  return (
    <section className="vm-audit-settings">
      <h3>{__("Audit thresholds", "vtx-media")}</h3>
      <p>
        {__(
          "Thresholds identify opportunities for review. Changing them marks existing scores outdated; run an audit to refresh the library.",
          "vtx-media",
        )}
      </p>
      {data && (
        <form
          onSubmit={(e) => {
            e.preventDefault();
            save(data);
          }}
        >
          <div className="vm-settings-grid">
            {fields.map(([key, label, min, max, unit]) => (
              <label key={key}>
                {label}
                <input
                  type="number"
                  min={min}
                  max={max}
                  step={unit === 1 ? 1 : 0.25}
                  required
                  value={data[key] / unit}
                  disabled={!config.manageSettings || busy}
                  onChange={(e) =>
                    setData({
                      ...data,
                      [key]: Math.round(Number(e.target.value) * unit),
                    })
                  }
                />
              </label>
            ))}
          </div>
          {config.manageSettings && (
            <div className="vm-audit-actions">
              <Button primary disabled={busy} type="submit">
                {__("Save thresholds", "vtx-media")}
              </Button>
              <Button disabled={busy} onClick={() => save(defaults)}>
                {__("Reset to defaults", "vtx-media")}
              </Button>
            </div>
          )}
        </form>
      )}
      {error && <p role="alert">{error}</p>}
      {wp.hooks.applyFilters("vtx_media.auditSettingsSections", [], {
        onSaved,
      })}
    </section>
  );
}
