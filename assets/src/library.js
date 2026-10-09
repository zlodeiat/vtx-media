const { __ } = wp.i18n;
export const defaults = {
  collection: "",
  view: "all",
  type: "all",
  folder: 0,
  search: "",
  date: "",
  author: 0,
  sort: "newest",
  page: 1,
};
export const libraryItems = [
  ["all", __("All Media", "vtx-media"), "portfolio", "all"],
  ["image", __("Images", "vtx-media"), "format-image", "image"],
  ["video", __("Video", "vtx-media"), "format-video", "video"],
  ["audio", __("Audio", "vtx-media"), "format-audio", "audio"],
  ["document", __("Documents", "vtx-media"), "media-document", "document"],
  ["favorites", __("Favorites", "vtx-media"), "star-empty", "all"],
  ["recent", __("Recently Added", "vtx-media"), "clock", "all"],
  ["unorganized", __("Unorganized", "vtx-media"), "archive", "all"],
];
export function initialFilters() {
  const p = new URLSearchParams(location.search);
  const f = { ...defaults };
  Object.keys(f).forEach((k) => {
    if (p.has("vm_" + k))
      f[k] =
        typeof f[k] === "number" ? Number(p.get("vm_" + k)) : p.get("vm_" + k);
  });
  return f;
}
