import { chromium, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import fs from "node:fs";
const wp = (file) =>
  execFileSync("wp", ["eval-file", file, "--allow-root"], { encoding: "utf8" });
wp("tests/browser-fixtures.php");
const { tag, ids } = JSON.parse(
  fs.readFileSync("tests/artifacts/fixtures.json"),
);
const b = await chromium.launch({ args: ["--no-sandbox"] });
const c = await b.newContext({
  ignoreHTTPSErrors: true,
  viewport: { width: 1440, height: 1050 },
});
await c.addCookies(
  JSON.parse(fs.readFileSync("tests/artifacts/auth.json")).cookies,
);
const p = await c.newPage();
p.setDefaultTimeout(15000);
const rest = async (path, method = "GET", body) =>
  p.evaluate(
    async ({ path, method, body }) => {
      const c = window.vtxMediaConfig;
      const r = await fetch(new URL(c.api + path), {
        method,
        headers: { "Content-Type": "application/json", "X-WP-Nonce": c.nonce },
        body: body ? JSON.stringify(body) : undefined,
      });
      const d = await r.json();
      if (!r.ok) throw new Error(d.message);
      return d;
    },
    { path, method, body },
  );
try {
  await p.goto(
    "https://testplugin.youneed.dev/wp-admin/upload.php?page=vtx-media&vm_search=" +
      tag,
  );
  await expect(p.locator(".vm-card")).toHaveCount(4);
  const root = await rest("folders", "POST", { name: tag + " Root" });
  const destination = await rest("folders", "POST", {
    name: tag + " Destination",
  });
  await p.reload();
  await expect(p.locator(".vm-card")).toHaveCount(4);
  await p.locator(`[data-media-id="${ids[0]}"] input[type=checkbox]`).check();
  await p.locator(`[data-media-id="${ids[1]}"] input[type=checkbox]`).check();
  const rootRow = p
    .locator(".vm-folder-row")
    .filter({ hasText: tag + " Root" });
  await p.locator(`[data-media-id="${ids[0]}"]`).dragTo(rootRow);
  await expect
    .poll(async () => (await rest("media/" + ids[0])).folder_id)
    .toBe(root.id);
  await expect
    .poll(async () => (await rest("media/" + ids[1])).folder_id)
    .toBe(root.id);
  console.log("PASS: Real pointer drag moves the selected media group");
  await rootRow.dragTo(
    p.locator(".vm-folder-row").filter({ hasText: tag + " Destination" }),
  );
  await expect
    .poll(async () => (await rest("folders/" + root.id)).parent_id)
    .toBe(destination.id);
  console.log("PASS: Real pointer drag reparents a folder");
  // A transient read failure must be visible and recoverable.
  let fail = true;
  await p.route("**/*", async (route) => {
    const u = route.request().url();
    if (fail && u.includes("/vtx-media/v1/media?")) {
      fail = false;
      return route.fulfill({
        status: 503,
        contentType: "application/json",
        body: JSON.stringify({ message: "Temporary test failure" }),
      });
    }
    return route.continue();
  });
  await p.getByRole("button", { name: "Favorites", exact: true }).click();
  await expect(p.getByRole("alert")).toContainText("Temporary test failure");
  await p.getByRole("button", { name: "Retry", exact: true }).click();
  await expect(p.getByRole("alert")).toHaveCount(0);
  console.log("PASS: Failed reads display errors and Retry recovers");
} finally {
  await b.close();
  wp("tests/browser-cleanup.php");
}
