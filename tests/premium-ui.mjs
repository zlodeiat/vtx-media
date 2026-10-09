import assert from "node:assert/strict";
import { build } from "esbuild";
import vm from "node:vm";
import fs from "node:fs";
const { outputFiles } = await build({
  entryPoints: ["assets/src/components/semantic.jsx"],
  bundle: true,
  write: false,
  format: "cjs",
  jsxFactory: "wp.element.createElement",
  jsxFragment: "wp.element.Fragment",
});
const scope = {
  module: { exports: {} },
  wp: {
    i18n: { __: (s) => s },
    element: {
      Fragment: "fragment",
      createElement: (type, props, ...children) =>
        typeof type === "function"
          ? type({
              ...props,
              children: children.length ? children : props?.children,
            })
          : { type, props: props || {}, children: children.flat(Infinity) },
    },
  },
};
vm.runInNewContext(outputFiles[0].text, scope);
const { Progress, DistributionBar, DataTable, RecordStatus, CapabilityStatus } =
  scope.module.exports;
const nodes = (t) =>
  t && typeof t === "object" ? [t, ...(t.children || []).flatMap(nodes)] : [];
const text = (t) =>
  typeof t === "string" || typeof t === "number"
    ? String(t)
    : (t?.children || []).map(text).join("");
for (const [value, total, width] of [
  [0, 100, "0%"],
  [1, 100, "1%"],
  [100, 100, "100%"],
  [500, 100, "100%"],
  [-1, 100, "0%"],
]) {
  const p = Progress({ value, total, label: "Progress" });
  assert.equal(p.children[0].props.style.width, width);
  assert.equal(p.props.role, "progressbar");
  assert.equal(p.props["aria-label"], "Progress");
}
assert.equal(
  Progress({ value: null, total: 100, label: "Not checked" }).props.role,
  "img",
);
assert.equal(
  Progress({ value: 0, total: 0, label: "No denominator" }).props.role,
  "img",
);
const segments = [
  { label: "In use", value: 46, tone: "success" },
  { label: "Review", value: 14, tone: "warning" },
  { label: "Not in use", value: 12 },
];
const bar = DistributionBar({ segments, total: 72, label: "Usage" });
assert(bar.props["aria-label"].includes("In use 46"));
assert.equal(bar.children.length, 3);
assert.equal(DistributionBar({ segments, total: 50, label: "Overlap" }), null);
assert.equal(DistributionBar({ segments: [], total: 0, label: "Empty" }), null);
assert.equal(text(CapabilityStatus({ available: true })), "Available");
assert.equal(text(CapabilityStatus({ available: false })), "Unavailable");
assert.equal(text(CapabilityStatus({ available: null })), "Not verified");
for (const [state, label, tone] of [
  ["completed", "Complete", "success"],
  ["running", "Running", "info"],
  ["paused", "Paused", "warning"],
  ["cancelled", "Cancelled", "neutral"],
  ["failed", "Failed", "danger"],
  ["unavailable", "Not installed", "neutral"],
]) {
  const s = RecordStatus({ value: state });
  assert.equal(text(s), label);
  assert(s.props.className.endsWith(tone));
}
let clicks = 0;
const table = DataTable({
  label: "Jobs",
  rows: [{ id: 1, name: "Audit", status: "completed" }],
  columns: [
    { id: "name", label: "Task" },
    {
      id: "status",
      label: "Status",
      render: (r) => RecordStatus({ value: r.status }),
    },
    {
      id: "action",
      label: "Action",
      render: () =>
        scope.wp.element.createElement(
          "button",
          { onClick: () => clicks++ },
          "View logs",
        ),
    },
  ],
});
assert(nodes(table).some((n) => n.type === "caption" && text(n) === "Jobs"));
assert.equal(
  nodes(table).filter((n) => n.type === "th" && n.props.scope === "col").length,
  3,
);
assert(nodes(table).some((n) => n.type === "th" && n.props.scope === "row"));
nodes(table)
  .find((n) => n.type === "button")
  .props.onClick();
assert.equal(clicks, 1);
assert(
  text(
    DataTable({ label: "Empty", rows: [], columns: [{ id: "a", label: "A" }] }),
  ).includes("No records"),
);
assert(
  !/\b(api|fetch|useEffect)\(/.test(
    fs.readFileSync("assets/src/components/semantic.jsx", "utf8"),
  ),
);
const css = fs.readFileSync("assets/src/premium.css", "utf8");
assert(css.includes(".vm-table-scroll"));
assert(/overflow:\s*auto/.test(css));
assert(/max-width:\s*1100px/.test(css));
assert(/max-width:\s*850px/.test(css));
assert(/max-width:\s*520px/.test(css));
console.log(
  "PASS: progress extremes/unknown, partition guard, textual capability/status states, accessible tables, preserved row callback, empty state, bounded responsive patterns, no component requests.",
);
