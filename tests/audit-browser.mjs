import { chromium, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";
import fs from "node:fs";
import { execFileSync } from "node:child_process";
const wp = (file) =>
  execFileSync("wp", ["eval-file", file, "--allow-root"], { encoding: "utf8" });
const browser = await chromium.launch({ args: ["--no-sandbox"] });
const context = await browser.newContext({
  ignoreHTTPSErrors: true,
  viewport: { width: 1440, height: 1050 },
});
await context.addCookies(
  JSON.parse(fs.readFileSync("tests/artifacts/auth.json")).cookies,
);
const page = await context.newPage();
page.setDefaultTimeout(20000);
const errors = [];
page.on("pageerror", (e) => errors.push(e.message));
page.on("console", (m) => {
  if (m.type() === "error") errors.push(m.text());
});
let checks = 0,
  fixtures = false;
const pass = (label) => {
  checks++;
  console.log("PASS:", label);
};
const rest = async (path, method = "GET", body) =>
  page.evaluate(
    async ({ path, method, body }) => {
      const c = window.vtxMediaConfig;
      const u = new URL(c.api + path.split("?")[0]);
      if (path.includes("?"))
        new URLSearchParams(path.split("?")[1]).forEach((v, k) =>
          u.searchParams.set(k, v),
        );
      const r = await fetch(u, {
        method,
        headers: { "Content-Type": "application/json", "X-WP-Nonce": c.nonce },
        body: body ? JSON.stringify(body) : undefined,
      });
      if (!r.ok) throw new Error(await r.text());
      return r.json();
    },
    { path, method, body },
  );
const audit = async () => {
  await page.getByRole("button", { name: "Health", exact: true }).click();
  await expect(page.locator(".vm-health-cards")).toBeVisible();
};
try {
  await page.goto(
    "https://testplugin.youneed.dev/wp-admin/upload.php?page=vtx-media&vm_screen=audit",
  );
  await expect(page.locator(".vm-health-cards")).toBeVisible();
  pass("Audit navigation and real coverage render");
  const initial = await rest("audit/overview");
  if (initial.summary.analyzed === 0 && initial.summary.failed === 0) {
    await expect(
      page.getByText("Media Health has not been analyzed yet.", {
        exact: true,
      }),
    ).toBeVisible();
    pass("First-run state has no fabricated health");
  }
  wp("tests/browser-fixtures.php");
  fixtures = true;
  const { ids, tag } = JSON.parse(
    fs.readFileSync("tests/artifacts/fixtures.json"),
  );
  await page.reload();
  await expect(page.locator(".vm-health-cards")).toBeVisible();
  let job = (await rest("jobs")).job;
  if (job && ["running", "paused"].includes(job.status))
    await rest(`jobs/${job.id}/control`, "POST", { action: "cancel" });
  await page.reload();
  await page.getByLabel("Batch size", { exact: true }).evaluate((el) => {
    el.closest("details").open = true;
  });
  await page.getByLabel("Batch size", { exact: true }).selectOption("10");
  await page
    .getByRole("button", { name: /^(Scan Media Library|Run again)$/ })
    .click();
  await expect(
    page.getByRole("button", { name: "Pause", exact: true }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Pause", exact: true }).click();
  await expect(
    page.getByRole("button", { name: "Resume", exact: true }),
  ).toBeVisible();
  job = (await rest("jobs")).job;
  expect(job.status).toBe("paused");
  pass("Real scan start and pause");
  await page.reload();
  await expect(
    page.getByRole("button", { name: "Resume", exact: true }),
  ).toBeVisible();
  expect((await rest("jobs")).job.id).toBe(job.id);
  pass("Persisted paused state survives browser refresh");
  await page.getByRole("button", { name: "Resume", exact: true }).click();
  await expect(
    page.getByRole("button", { name: "Pause", exact: true }),
  ).toBeVisible();
  pass("Resume scan");
  await page.getByRole("button", { name: "Cancel scan", exact: true }).click();
  await expect(
    page.getByRole("button", { name: "Run again", exact: true }),
  ).toBeVisible();
  expect((await rest("jobs")).job.status).toBe("cancelled");
  pass("Cancel stops future batches");
  await page.getByLabel("Batch size", { exact: true }).evaluate((el) => {
    el.closest("details").open = true;
  });
  await page.getByLabel("Batch size", { exact: true }).selectOption("50");
  await page.getByRole("button", { name: "Run again", exact: true }).click();
  await expect(
    page.getByRole("button", { name: "Pause", exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Run again", exact: true }),
  ).toBeVisible({ timeout: 90000 });
  job = (await rest("jobs")).job;
  expect(job.status).toBe("completed");
  expect(job.processed + job.skipped).toBe(job.total);
  pass("Background batches complete with persisted real progress");
  await page
    .getByLabel("Issue type", { exact: true })
    .selectOption("missing-alt");
  await page
    .getByRole("searchbox", { name: "Search filename or title" })
    .fill(tag);
  await expect(page.locator(".vm-issue-table tbody tr")).toHaveCount(1);
  pass("Issue rule filter and filename search");
  await page
    .getByLabel("Select findings on this page", { exact: true })
    .check();
  await page
    .getByRole("button", { name: "Ignore findings", exact: true })
    .click();
  await expect(page.locator(".vm-issue-table tbody tr")).toHaveCount(0);
  await page.getByLabel("Status", { exact: true }).selectOption("ignored");
  await expect(page.locator(".vm-issue-table tbody tr")).toHaveCount(1);
  pass("Bulk ignore and ignored issue browser");
  await page
    .getByLabel("Select findings on this page", { exact: true })
    .check();
  await page
    .getByRole("button", { name: "Restore findings", exact: true })
    .click();
  await expect(page.locator(".vm-issue-table tbody tr")).toHaveCount(0);
  await page.getByLabel("Status", { exact: true }).selectOption("open");
  await expect(page.locator(".vm-issue-table tbody tr")).toHaveCount(1);
  pass("Restore ignored findings");
  await page.locator(".vm-issue-media").click();
  await expect(page.locator(".vm-inspector")).toBeVisible();
  pass("Open affected media in existing inspector with real score");
  await page
    .getByLabel("Alternative text", { exact: true })
    .fill("A test image with deliberate metadata");
  await page.getByRole("button", { name: "Save changes", exact: true }).click();
  await expect(page.locator(".vm-inspector")).not.toContainText(
    "ALT not reviewed",
  );
  pass("Metadata edit re-audits the attachment immediately");
  page.once("dialog", (d) => d.accept());
  await page
    .getByRole("button", { name: "Mark as decorative", exact: true })
    .click();
  await expect(
    page.getByLabel("Alternative text", { exact: true }),
  ).toHaveValue("");
  await expect(
    page.getByRole("button", { name: "Remove decorative status", exact: true }),
  ).toBeVisible();
  pass("Decorative action clears ALT and resolves review finding");
  await page
    .getByRole("button", { name: "Remove decorative status", exact: true })
    .click();
  await expect(page.locator(".vm-inspector")).toContainText("ALT not reviewed");
  pass("Remove decorative status restores ALT review");
  await page
    .locator(".vm-health-finding")
    .filter({ hasText: "ALT not reviewed" })
    .getByRole("button", { name: "Edit metadata", exact: true })
    .click();
  await expect(
    page.getByLabel("Alternative text", { exact: true }),
  ).toBeFocused();
  pass("Audit action focuses existing metadata editor");
  await audit();
  await page
    .getByRole("button", { name: "Audit settings", exact: true })
    .click();
  const width = page.getByLabel("Width threshold (pixels)");
  const original = await width.inputValue();
  await width.fill("7000");
  await page
    .getByRole("button", { name: "Save thresholds", exact: true })
    .click();
  await expect(
    page.getByText(
      "Thresholds saved. Run an audit to refresh outdated results.",
      { exact: true },
    ),
  ).toBeVisible();
  expect((await rest("audit/settings")).values.max_width).toBe(7000);
  await width.fill(original);
  await page
    .getByRole("button", { name: "Save thresholds", exact: true })
    .click();
  pass("Validated threshold settings persist");
  await page
    .getByRole("button", { name: "Close settings", exact: true })
    .click();
  await page.getByLabel("Issue type", { exact: true }).selectOption("");
  await page
    .getByRole("searchbox", { name: "Search filename or title" })
    .fill("");
  await expect(page.locator(".vm-issue-table-wrap")).toHaveAttribute(
    "aria-busy",
    "false",
  );
  const visual = [];
  for (const w of [1440, 1024, 768, 390]) {
    await page.setViewportSize({ width: w, height: 1050 });
    await page.screenshot({
      path: `tests/artifacts/audit-${w}.png`,
      fullPage: true,
    });
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true);
    const a = await new AxeBuilder({ page })
      .include("#vtx-media-app")
      .withTags(["wcag2a", "wcag2aa", "wcag21aa"])
      .analyze();
    visual.push({ width: w, violations: a.violations });
    expect(a.violations).toEqual([]);
    pass(`Audit responsive layout and accessibility at ${w}px`);
  }
  fs.writeFileSync(
    "tests/artifacts/audit-visual.json",
    JSON.stringify(visual, null, 2),
  );
  expect(errors).toEqual([]);
  pass("No JavaScript runtime or console errors");
  expect((await rest(`audit/media/${ids[0]}`)).attachment_id).toBe(ids[0]);
  console.log(`Completed ${checks} audit browser checks.`);
} finally {
  if (fixtures) wp("tests/browser-cleanup.php");
  await browser.close();
}
