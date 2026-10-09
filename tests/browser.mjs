import { chromium, expect } from "@playwright/test";
import fs from "node:fs";
import { execFileSync } from "node:child_process";
const wp = (file) =>
  execFileSync("wp", ["eval-file", file, "--allow-root"], { encoding: "utf8" });
wp("tests/auth.php");
wp("tests/browser-fixtures.php");
const fixtures = JSON.parse(fs.readFileSync("tests/artifacts/fixtures.json"));
const { tag, ids } = fixtures;
const browser = await chromium.launch({
  headless: true,
  args: ["--no-sandbox"],
});
const context = await browser.newContext({
  ignoreHTTPSErrors: true,
  viewport: { width: 1440, height: 1050 },
  permissions: ["clipboard-read", "clipboard-write"],
});
await context.addCookies(
  JSON.parse(fs.readFileSync("tests/artifacts/auth.json")).cookies,
);
const page = await context.newPage();
page.setDefaultTimeout(12000);
const errors = [];
page.on("pageerror", (e) => errors.push(e.message));
page.on("console", (m) => {
  if (m.type() === "error") errors.push(m.text());
});
let passed = 0;
const pass = (label) => {
  passed++;
  console.log("PASS:", label);
};
const ready = () =>
  expect(page.locator(".vm-results")).toHaveAttribute("aria-busy", "false");
const search = async (value) => {
  await page.getByRole("searchbox", { name: "Search media" }).fill(value);
  await expect(page).toHaveURL(
    new RegExp(value ? `vm_search=${value}` : "upload.php"),
  );
  await ready();
};
const rest = async (path, method = "GET", body) =>
  page.evaluate(
    async ({ path, method, body }) => {
      const c = window.vtxMediaConfig;
      const u = new URL(c.api + path);
      const r = await fetch(u, {
        method,
        headers: { "Content-Type": "application/json", "X-WP-Nonce": c.nonce },
        body: body ? JSON.stringify(body) : undefined,
      });
      return { status: r.status, data: await r.json() };
    },
    { path, method, body },
  );
try {
  await page.goto(
    "https://testplugin.youneed.dev/wp-admin/upload.php?page=vtx-media",
  );
  await ready();
  await expect(page.locator(".vm-card").first()).toBeVisible();
  pass("Admin page shows existing WordPress media");
  await page.getByRole("button", { name: "Next page", exact: true }).click();
  await ready();
  await expect(page).toHaveURL(/vm_page=2/);
  pass("Pagination navigation");
  await page
    .getByRole("button", { name: "Previous page", exact: true })
    .click();
  await ready();
  await search(tag);
  await expect(page.locator(".vm-card")).toHaveCount(4);
  pass("Search in browser");
  await page.getByRole("button", { name: "List view", exact: true }).click();
  await expect(page.locator(".vm-table tbody tr")).toHaveCount(4);
  await page.reload();
  await ready();
  await expect(page.locator(".vm-table")).toBeVisible();
  pass("List view and filters persist after reload");
  await page.getByRole("button", { name: "Grid view", exact: true }).click();
  await page.getByLabel("Media type", { exact: true }).selectOption("document");
  await ready();
  await expect(page.locator(".vm-card")).toHaveCount(1);
  await expect(page.locator(".vm-card")).toContainText("brief.pdf");
  pass("Document filter and non-image card");
  await page.getByLabel("Media type", { exact: true }).selectOption("all");
  await ready();
  await page.getByLabel("Sort media", { exact: true }).selectOption("name_asc");
  await ready();
  await expect(page.locator(".vm-card").first()).toHaveAttribute(
    "data-media-id",
    String(ids[0]),
  );
  await page.getByLabel("Sort media", { exact: true }).selectOption("largest");
  await ready();
  await expect(page.locator(".vm-card").first()).toHaveAttribute(
    "data-media-id",
    String(ids[0]),
  );
  pass("Name and size sorting");
  await page.getByLabel("Sort media", { exact: true }).selectOption("newest");
  await ready();
  await page
    .getByRole("button", { name: "New folder", exact: true })
    .last()
    .click();
  await page.getByLabel("Folder name", { exact: true }).fill(tag + " Root");
  await page.getByRole("button", { name: "Save folder", exact: true }).click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await expect(
    page.locator(".vm-folder-select").filter({ hasText: tag + " Root" }),
  ).toBeVisible();
  pass("Folder creation dialog");
  await page
    .locator(".vm-folder-select")
    .filter({ hasText: tag + " Root" })
    .click();
  await ready();
  await page
    .getByRole("button", { name: "New folder", exact: true })
    .last()
    .click();
  await page.getByLabel("Folder name", { exact: true }).fill(tag + " Child");
  await page.getByRole("button", { name: "Save folder", exact: true }).click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await page
    .getByRole("button", { name: "Expand " + tag + " Root", exact: true })
    .click();
  await expect(
    page.locator(".vm-folder-select").filter({ hasText: tag + " Child" }),
  ).toBeVisible();
  pass("Nested folder creation and lazy expansion");
  let folders = (await rest("folders?search=" + tag)).data.items;
  const root = folders.find((f) => f.name.endsWith("Root")),
    child = folders.find((f) => f.name.endsWith("Child"));
  await page.getByRole("button", { name: "All Media", exact: true }).click();
  await ready();
  await expect(page.locator(".vm-card")).toHaveCount(4);
  await page
    .locator(`[data-media-id="${ids[0]}"] input[type=checkbox]`)
    .check();
  await page
    .locator(`[data-media-id="${ids[1]}"] input[type=checkbox]`)
    .check();
  await page.getByRole("button", { name: "Move", exact: true }).click();
  await page
    .getByRole("dialog")
    .getByRole("button")
    .filter({ hasText: tag + " Root" })
    .click();
  await page.getByRole("button", { name: "Move media", exact: true }).click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await ready();
  expect((await rest("media/" + ids[0])).data.folder_id).toBe(root.id);
  expect((await rest("media/" + ids[1])).data.folder_id).toBe(root.id);
  pass("Accessible bulk move persists");
  // Exercise native DataTransfer handlers through actual browser drag events.
  const transfer = await page.evaluateHandle((id) => {
    const d = new DataTransfer();
    d.setData("application/x-vtx-media", JSON.stringify([id]));
    return d;
  }, ids[2]);
  const target = page.locator(".vm-folder-row").filter({
    has: page.locator(".vm-folder-select").filter({ hasText: tag + " Child" }),
  });
  await target.dispatchEvent("dragover", { dataTransfer: transfer });
  await expect(target).toHaveClass(/is-drop/);
  await target.dispatchEvent("drop", { dataTransfer: transfer });
  await expect
    .poll(async () => (await rest("media/" + ids[2])).data.folder_id)
    .toBe(child.id);
  pass("Media drag/drop and visual drop state");
  await page.getByRole("button", { name: "Unorganized", exact: true }).click();
  await ready();
  await expect(page.locator(".vm-card")).toHaveCount(1);
  await expect(page.locator(".vm-card")).toHaveAttribute(
    "data-media-id",
    String(ids[3]),
  );
  pass("Unorganized view after real moves");
  await page.getByRole("button", { name: "All Media", exact: true }).click();
  await ready();
  await page.locator(`[data-media-id="${ids[0]}"] .vm-card-star`).click();
  await ready();
  await page.getByRole("button", { name: "Favorites", exact: true }).click();
  await ready();
  await expect(page.locator(".vm-card")).toHaveCount(1);
  pass("Favorite action and personal favorites view");
  await page.locator(".vm-open-card").click();
  await expect(
    page.getByLabel("Alternative text", { exact: true }),
  ).toBeVisible();
  await page.getByLabel("Title", { exact: true }).fill(tag + " edited");
  await page
    .getByLabel("Alternative text", { exact: true })
    .fill("Browser-tested ALT");
  await page.getByLabel("Caption", { exact: true }).fill("Browser caption");
  await page
    .getByLabel("Description", { exact: true })
    .fill("Browser description");
  await page.getByRole("button", { name: "Save changes", exact: true }).click();
  await expect(
    page.getByText("Metadata saved.", { exact: true }),
  ).toBeVisible();
  pass("Inspector metadata form saves");
  await page.getByRole("button", { name: "Copy URL", exact: true }).click();
  await expect(page.getByText("URL copied.", { exact: true })).toBeVisible();
  pass("Copy URL");
  await page.reload();
  await ready();
  await page.locator(".vm-open-card").click();
  await expect(
    page.getByLabel("Alternative text", { exact: true }),
  ).toHaveValue("Browser-tested ALT");
  pass("Metadata persists after refresh");
  await page
    .getByLabel("Alternative text", { exact: true })
    .fill("Unsaved change");
  page.once("dialog", (d) => d.dismiss());
  await page.getByRole("button", { name: "All Media", exact: true }).click();
  await expect(
    page.getByLabel("Alternative text", { exact: true }),
  ).toHaveValue("Unsaved change");
  await page.getByRole("button", { name: "Reset", exact: true }).click();
  pass("Dirty metadata navigation protection");
  await page.getByRole("button", { name: "All Media", exact: true }).click();
  await ready();
  await page.locator(`[data-media-id="${ids[1]}"] .vm-open-card`).click();
  await expect(page.locator("#vm-inspector")).toContainText("application/pdf");
  await expect(page.locator("#vm-inspector")).toContainText(
    "No accessible local file",
  );
  pass("Non-image inspector with honest file status");
  if (
    !(await page
      .locator(".vm-folder-select")
      .filter({ hasText: tag + " Child" })
      .count())
  ) {
    await page
      .getByRole("button", { name: "Expand " + tag + " Root", exact: true })
      .click();
  }
  await page
    .locator(".vm-folder-select")
    .filter({ hasText: tag + " Child" })
    .click();
  await ready();
  await page
    .getByRole("button", { name: "Edit current folder", exact: true })
    .click();
  await page.getByLabel("Folder name", { exact: true }).fill(tag + " Renamed");
  await page.getByLabel("Sort position", { exact: true }).fill("2");
  await page
    .getByRole("dialog")
    .getByRole("button", { name: "Top level", exact: true })
    .click();
  await page.getByRole("button", { name: "Save folder", exact: true }).click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await ready();
  expect((await rest("folders/" + child.id)).data.parent_id).toBe(0);
  pass("Rename, reorder and move folder through keyboard-accessible form");
  const folderTransfer = await page.evaluateHandle((id) => {
    const d = new DataTransfer();
    d.setData("application/x-vtx-folder", String(id));
    return d;
  }, child.id);
  await page
    .locator(".vm-folder-row")
    .filter({
      has: page.locator(".vm-folder-select").filter({ hasText: tag + " Root" }),
    })
    .dispatchEvent("drop", { dataTransfer: folderTransfer });
  await expect
    .poll(async () => (await rest("folders/" + child.id)).data.parent_id)
    .toBe(root.id);
  pass("Folder drag/drop");
  await page
    .locator(".vm-folder-select")
    .filter({ hasText: tag + " Root" })
    .click();
  await ready();
  await page
    .getByRole("button", { name: "Delete current folder", exact: true })
    .click();
  await expect(page.getByRole("dialog")).toContainText(
    "No media files will be deleted",
  );
  await page
    .getByRole("button", { name: "Delete folder", exact: true })
    .click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await ready();
  expect((await rest("folders/" + child.id)).data.parent_id).toBe(0);
  expect((await rest("media/" + ids[0])).data.folder_id).toBe(0);
  pass("Safe folder deletion preserves files and children");
  await page
    .getByRole("button", { name: "Reset filters", exact: true })
    .click();
  await ready();
  await page
    .locator(".vm-open-card")
    .filter({ has: page.locator("img") })
    .first()
    .click();
  await expect(page.locator("#vm-inspector h2")).toHaveText("File details");
  for (const width of [1440, 1024, 768, 390]) {
    await page.setViewportSize({ width, height: 1050 });
    await page.waitForTimeout(200);
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > innerWidth,
    );
    expect(overflow).toBe(false);
    await page.screenshot({
      path: `tests/artifacts/media-${width}.png`,
      fullPage: true,
    });
    if (width === 390) {
      await page
        .getByRole("button", { name: "Library & folders", exact: true })
        .click();
      await expect(
        page.getByRole("button", { name: "Images", exact: true }),
      ).toBeVisible();
      await page.getByRole("button", { name: "Images", exact: true }).click();
      await ready();
      await expect(
        page.getByRole("button", { name: "Library & folders", exact: true }),
      ).toHaveAttribute("aria-expanded", "false");
    }
    pass("Responsive rendering and overflow: " + width);
  }
  await page.setViewportSize({ width: 1440, height: 1050 });
  await page.goto("https://testplugin.youneed.dev/wp-admin/upload.php");
  await expect(page.locator("#wpbody-content")).toBeVisible();
  expect(await page.locator("#vtx-media-app").count()).toBe(0);
  expect(await page.locator('script[src*="vtx-media/assets"]').count()).toBe(0);
  pass("Native Media Library renders without VTX assets");
  expect(errors).toEqual([]);
  pass("No browser JavaScript or console errors");
  fs.writeFileSync(
    "tests/artifacts/browser-results.json",
    JSON.stringify({ passed, errors, date: new Date().toISOString() }, null, 2),
  );
  console.log(`Completed ${passed} browser checks.`);
} catch (error) {
  await page.screenshot({
    path: "tests/artifacts/browser-failure.png",
    fullPage: true,
  });
  throw error;
} finally {
  await browser.close();
  wp("tests/browser-cleanup.php");
}
