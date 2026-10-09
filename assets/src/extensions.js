import {
  Progress,
  DistributionBar,
  DataTable,
  RecordStatus,
  CapabilityStatus,
} from "./components/semantic";
import {
  PageHeader,
  SectionHeader,
  Card,
  MetricCard,
  StatusBadge,
  DataList,
  EmptyState,
} from "./components/design";
import {
  TechnicalDetails,
  RelativeTime,
  Drawer,
  Pager,
} from "./components/presentation";
import { api, query, config } from "./api";
import { Button, Icon, Thumb, bytes, date, Dialog } from "./components/ui";
// Executable components are registered by enqueued scripts, never PHP manifests.
const sidebar = new Map();
export const registerLibrarySidebar = (id, component) =>
  registerPart(sidebar, id, { component });
export function LibrarySidebar(props) {
  return [...sidebar.values()].map((p) =>
    wp.element.createElement(p.component, { key: p.id, ...props }),
  );
}
const screens = new Map();
export function registerScreen(id, component) {
  if (
    !/^[a-z][a-z0-9-]{1,63}$/.test(id) ||
    typeof component !== "function" ||
    screens.has(id)
  )
    throw new Error("Invalid or duplicate VTX screen");
  screens.set(id, component);
  window.dispatchEvent(new Event("vtx-media-extensions"));
}
export function getScreen(id) {
  return screens.get(id);
}
export function unregisterScreen(id) {
  screens.delete(id);
  window.dispatchEvent(new Event("vtx-media-extensions"));
}

const advanced = new Map(),
  inspector = new Map();
function registerPart(registry, id, definition) {
  if (
    !/^[a-z][a-z0-9-]{1,63}$/.test(id) ||
    registry.has(id) ||
    typeof definition.component !== "function"
  )
    throw new Error("Invalid VTX extension section");
  registry.set(id, { ...definition, id });
  window.dispatchEvent(new Event("vtx-media-extensions"));
}
export const registerAdvancedSection = (id, definition) =>
  registerPart(advanced, id, definition);
export const getAdvancedSections = () =>
  [...advanced.values()].sort((a, b) => (a.position || 0) - (b.position || 0));
export const registerInspectorSection = (id, slot, component, position = 0) => {
  if (
    ![
      "health",
      "seo",
      "usage",
      "organization",
      "optimization",
      "advanced",
    ].includes(slot)
  )
    throw new Error("Invalid Inspector slot");
  registerPart(inspector, id, { slot, component, position });
};
export function InspectorSlot({ slot, item, context }) {
  return [...inspector.values()]
    .filter((p) => p.slot === slot)
    .sort((a, b) => a.position - b.position)
    .map((p) =>
      wp.element.createElement(p.component, { key: p.id, item, context }),
    );
}
export function navigate(screen, section) {
  window.dispatchEvent(
    new CustomEvent("vtx-media-navigate", { detail: { screen, section } }),
  );
}

window.vtxMediaExtensions = Object.freeze({
  registerLibrarySidebar,
  registerScreen,
  registerAdvancedSection,
  registerInspectorSection,
  navigate,
  unregisterScreen,
  api,
  query,
  config,
  ui: Object.freeze({
    Progress,
    DistributionBar,
    DataTable,
    RecordStatus,
    CapabilityStatus,
    PageHeader,
    SectionHeader,
    Card,
    MetricCard,
    StatusBadge,
    DataList,
    EmptyState,
    Button,
    Icon,
    Thumb,
    bytes,
    date,
    Dialog,
    TechnicalDetails,
    RelativeTime,
    Drawer,
    Pager,
  }),
});
