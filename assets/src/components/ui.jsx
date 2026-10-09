const { useState, useEffect, useRef } = wp.element;
const { __ } = wp.i18n;
export function Icon({ name }) {
  return <span aria-hidden="true" className={"dashicons dashicons-" + name} />;
}
export function Button({
  icon,
  children,
  className = "",
  primary = false,
  ...props
}) {
  return (
    <button
      type="button"
      className={"vm-button " + (primary ? "vm-primary " : "") + className}
      {...props}
    >
      {icon && <Icon name={icon} />}
      {children}
    </button>
  );
}
export function bytes(value) {
  if (value === null || value === undefined)
    return __("Unknown size", "vtx-media");
  if (value === 0) return "0 B";
  const n = Math.min(3, Math.floor(Math.log(value) / Math.log(1024)));
  return (
    new Intl.NumberFormat(undefined, {
      maximumFractionDigits: n ? 1 : 0,
    }).format(value / 1024 ** n) +
    " " +
    ["B", "KB", "MB", "GB"][n]
  );
}
export function date(value) {
  return new Date(value).toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}
export function Thumb({ item, large = false }) {
  const [failed, setFailed] = useState(false);
  const src = large ? item.preview : item.thumbnail;
  useEffect(() => setFailed(false), [src]);
  return (
    <div className={"vm-thumb" + (large ? " vm-thumb-large" : "")}>
      {src && !failed ? (
        <img src={src} alt="" loading="lazy" onError={() => setFailed(true)} />
      ) : (
        <div className="vm-file">
          <Icon
            name={
              item.mime.startsWith("audio/")
                ? "media-audio"
                : item.mime.startsWith("video/")
                  ? "media-video"
                  : item.image
                    ? "format-image"
                    : "media-document"
            }
          />
          <span>{item.extension || item.mime}</span>
        </div>
      )}
    </div>
  );
}
export function Dialog({ title, children, onClose, busy = false }) {
  const ref = useRef();
  useEffect(() => {
    const dialog = ref.current;
    const previous = document.activeElement;
    dialog.showModal();
    return () => {
      dialog.close();
      if (previous?.isConnected) previous.focus();
    };
  }, []);
  return (
    <dialog
      className="vm-dialog"
      ref={ref}
      aria-labelledby="vm-dialog-title"
      onCancel={(e) => {
        e.preventDefault();
        if (!busy) onClose();
      }}
    >
      <header>
        <h2 id="vm-dialog-title">{title}</h2>
        <Button
          icon="no-alt"
          aria-label={__("Close dialog", "vtx-media")}
          disabled={busy}
          onClick={onClose}
        />
      </header>
      {children}
    </dialog>
  );
}
