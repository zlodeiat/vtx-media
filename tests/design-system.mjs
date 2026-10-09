import assert from "node:assert/strict";
import { build } from "esbuild";
import vm from "node:vm";
import fs from "node:fs";
const { outputFiles } = await build({
  entryPoints: ["assets/src/components/design.jsx"],
  bundle: true,
  write: false,
  format: "cjs",
  jsxFactory: "wp.element.createElement",
  jsxFragment: "wp.element.Fragment",
});
let calls = 0;
const scope = {
  module: { exports: {} },
  wp: {
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
const {
  MetricCard,
  PageHeader,
  Card,
  SectionHeader,
  StatusBadge,
  DataList,
  EmptyState,
} = scope.module.exports;
const nodes = (tree) =>
  tree && typeof tree === "object"
    ? [tree, ...(tree.children || []).flatMap(nodes)]
    : [];
const text = (tree) =>
  typeof tree === "string" || typeof tree === "number"
    ? String(tree)
    : (tree?.children || []).map(text).join("");
const metric = MetricCard({
  label: "Files",
  value: 72,
  onClick: () => calls++,
  "aria-pressed": true,
});
assert.equal(metric.type, "button");
assert.equal(metric.props.type, "button");
assert.equal(text(metric), "72Files");
assert.equal(metric.props["aria-pressed"], true);
metric.props.onClick();
assert.equal(calls, 1);
assert.equal(MetricCard({ label: "Files", value: 0 }).type, "article");
assert(
  nodes(PageHeader({ title: "Analytics", eyebrow: "Media" })).some(
    (n) => n.type === "h2",
  ),
);
assert(
  nodes(Card({ title: "Storage", children: "facts" })).some(
    (n) => n.type === "h3",
  ),
);
assert(
  nodes(SectionHeader({ title: "Nested", level: 4 })).some(
    (n) => n.type === "h4",
  ),
);
assert.equal(
  text(StatusBadge({ children: "Failed", tone: "danger" })),
  "Failed",
);
assert(
  StatusBadge({
    children: "Unknown",
    tone: "bad-input",
  }).props.className.endsWith("neutral"),
);
const list = DataList({
  rows: [{ label: "Media", value: 72, onClick: () => calls++ }],
});
nodes(list)
  .find((n) => n.type === "button")
  .props.onClick();
assert.equal(calls, 2);
assert(
  text(
    EmptyState({ title: "No history", children: "Real snapshots only" }),
  ).includes("Real snapshots only"),
);
const source = fs.readFileSync("assets/src/components/design.jsx", "utf8");
assert(!/\b(fetch|api|useEffect)\(/.test(source));
const css = fs.readFileSync("assets/src/design.css", "utf8");
assert(css.includes("repeat(4"));
assert(css.includes("max-width:520px"));
assert(css.includes(".vmc-danger"));
assert(css.includes("focus-visible"));
console.log(
  "PASS: metric values/actions, heading hierarchy, textual statuses, safe tone fallback, data-list actions, empty state, responsive/focus/destructive contracts, request-free primitives.",
);
