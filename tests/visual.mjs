import { chromium, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";
import fs from "node:fs";
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
const errors = [];
p.on("pageerror", (e) => errors.push(e.message));
p.on("console", (m) => {
  if (m.type() === "error") errors.push(m.text());
});
const results = [];
try {
  await p.goto(
    "https://testplugin.youneed.dev/wp-admin/upload.php?page=vtx-media",
  );
  await expect(p.locator(".vm-results")).toHaveAttribute("aria-busy", "false");
  await p
    .locator(".vm-open-card")
    .filter({ has: p.locator("img") })
    .first()
    .click();
  await expect(
    p.getByLabel("Alternative text", { exact: true }),
  ).toBeAttached();
  await expect(p.locator("#vm-inspector .vm-thumb img")).toBeVisible();
  for (const width of [1440, 1024, 768, 390]) {
    await p.setViewportSize({ width, height: 1050 });
    await p.screenshot({
      path: `tests/artifacts/final-${width}.png`,
      fullPage: true,
    });
    expect(
      await p.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true);
    if (width === 1440) {
      await expect(p.locator(".vm-pagination")).toBeInViewport();
      const bounds = await p.evaluate(() => ({
        panel: document.querySelector(".vm-columns").getBoundingClientRect()
          .bottom,
        pager: document.querySelector(".vm-pagination").getBoundingClientRect()
          .bottom,
      }));
      expect(bounds.pager).toBeLessThanOrEqual(bounds.panel + 1);
    }
    const a = await new AxeBuilder({ page: p })
      .include("#vtx-media-app")
      .withTags(["wcag2a", "wcag2aa", "wcag21aa"])
      .analyze();
    results.push({
      width,
      violations: a.violations.map((v) => ({
        id: v.id,
        impact: v.impact,
        description: v.description,
        nodes: v.nodes.map((n) => ({
          target: n.target,
          summary: n.failureSummary,
        })),
      })),
    });
  }
  await p.setViewportSize({ width: 1440, height: 1050 });
  await p
    .getByRole("button", { name: "New folder", exact: true })
    .last()
    .click();
  await expect(p.getByRole("dialog")).toBeVisible();
  expect(
    await p.evaluate(() => document.activeElement.closest("dialog") !== null),
  ).toBe(true);
  await p.keyboard.press("Tab");
  expect(
    await p.evaluate(() => document.activeElement.closest("dialog") !== null),
  ).toBe(true);
  await p.keyboard.press("Escape");
  await expect(p.getByRole("dialog")).toHaveCount(0);
  results.push({ keyboard: "Dialog focus and Escape passed", errors });
  fs.writeFileSync(
    "tests/artifacts/visual-results.json",
    JSON.stringify(results, null, 2),
  );
  console.log(JSON.stringify(results, null, 2));
  expect(results.flatMap((r) => r.violations || [])).toEqual([]);
  expect(errors).toEqual([]);
} finally {
  await b.close();
}
