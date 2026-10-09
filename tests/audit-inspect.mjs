import { chromium } from "@playwright/test";
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
await p.goto(
  "https://testplugin.youneed.dev/wp-admin/upload.php?page=vtx-media&vm_screen=audit",
);
await p.waitForTimeout(4000);
console.log(await p.locator(".vm-audit-app").innerText());
console.log(p.url());
await p.screenshot({
  path: "tests/artifacts/audit-inspect.png",
  fullPage: true,
});
await b.close();
