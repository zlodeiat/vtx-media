import { config } from "../api";
import { Button } from "./ui";
const { useEffect, useRef, useState } = wp.element;
const { __ } = wp.i18n;
export function TechnicalDetails({
  children,
  title = __("Technical details", "vtx-media"),
}) {
  return (
    <details className="vm-technical">
      <summary>{title}</summary>
      <div>{children}</div>
    </details>
  );
}
export function RelativeTime({ value }) {
  const [, tick] = useState(0);
  useEffect(() => {
    const timer = setInterval(() => tick((n) => n + 1), 60000);
    return () => clearInterval(timer);
  }, []);
  if (!value) return <span>{__("Not checked yet", "vtx-media")}</span>;
  const iso = /[zZ]$|[+-]\d\d:\d\d$/.test(value)
    ? value
    : value.replace(" ", "T") + "Z";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime()))
    return <span>{__("Date unavailable", "vtx-media")}</span>;
  let locale;
  try {
    locale = Intl.getCanonicalLocales(
      config.locale || document.documentElement.lang || "en",
    )[0];
  } catch {
    locale = undefined;
  }
  const delta = (date.getTime() - Date.now()) / 1000;
  const unit =
    Math.abs(delta) < 3600
      ? "minute"
      : Math.abs(delta) < 86400
        ? "hour"
        : "day";
  const divisor = { minute: 60, hour: 3600, day: 86400 }[unit];
  const label =
    Math.abs(delta) < 60
      ? __("Just now", "vtx-media")
      : new Intl.RelativeTimeFormat(locale, { numeric: "auto" }).format(
          Math.round(delta / divisor),
          unit,
        );
  let exact;
  const offset = /^([+-])(\d{2}):(\d{2})$/.exec(config.timezone || "");
  if (offset) {
    const minutes =
      (Number(offset[2]) * 60 + Number(offset[3])) *
      (offset[1] === "-" ? -1 : 1);
    exact =
      new Intl.DateTimeFormat(locale, {
        dateStyle: "medium",
        timeStyle: "medium",
        timeZone: "UTC",
      }).format(new Date(date.getTime() + minutes * 60000)) +
      " GMT" +
      config.timezone;
  } else
    try {
      exact = new Intl.DateTimeFormat(locale, {
        dateStyle: "medium",
        timeStyle: "long",
        timeZone: config.timezone || "UTC",
      }).format(date);
    } catch {
      exact = new Intl.DateTimeFormat(locale, {
        dateStyle: "medium",
        timeStyle: "long",
        timeZone: "UTC",
      }).format(date);
    }
  return (
    <time
      dateTime={date.toISOString()}
      title={exact}
      aria-label={`${label} — ${exact}`}
    >
      {label}
    </time>
  );
}
export function Drawer({ title, children, onClose }) {
  const ref = useRef();
  const titleId = useRef(`vm-drawer-${Math.random().toString(36).slice(2)}`);
  useEffect(() => {
    const previous = document.activeElement;
    const dialog = ref.current;
    dialog.showModal();
    dialog.querySelector("button")?.focus();
    return () => {
      dialog.close();
      if (previous?.isConnected) previous.focus();
    };
  }, []);
  return (
    <dialog
      ref={ref}
      className="vm-drawer"
      aria-labelledby={titleId.current}
      onKeyDown={(e)=>{
        if(e.key!=="Tab")return;
        const nodes=[...ref.current.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),summary,[tabindex]:not([tabindex="-1"])')].filter(el=>el.getClientRects().length>0);
        if(!nodes.length){e.preventDefault();return;}
        if(e.shiftKey&&document.activeElement===nodes[0]){e.preventDefault();nodes[nodes.length-1].focus();}
        else if(!e.shiftKey&&document.activeElement===nodes[nodes.length-1]){e.preventDefault();nodes[0].focus();}
      }}
      onCancel={(e) => {
        e.preventDefault();
        onClose();
      }}
    >
      <header>
        <h2 id={titleId.current}>{title}</h2>
        <Button
          icon="no-alt"
          aria-label={__("Close details", "vtx-media")}
          onClick={onClose}
        />
      </header>
      <div className="vm-drawer-body">{children}</div>
    </dialog>
  );
}
export function Pager({
  pagination,
  onPage,
  label = __("Results", "vtx-media"),
}) {
  return (
    <nav className="vm-result-pager" aria-label={label}>
      <span>
        {pagination.total.toLocaleString()} {label.toLocaleLowerCase()}
      </span>
      <Button
        disabled={pagination.page <= 1}
        onClick={() => onPage(pagination.page - 1)}
      >
        {__("Previous", "vtx-media")}
      </Button>
      <span>
        {pagination.page} / {Math.max(1, pagination.pages)}
      </span>
      <Button
        disabled={pagination.page >= pagination.pages}
        onClick={() => onPage(pagination.page + 1)}
      >
        {__("Next", "vtx-media")}
      </Button>
    </nav>
  );
}
