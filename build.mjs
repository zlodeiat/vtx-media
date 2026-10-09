import { build } from "esbuild";
import { readFile, writeFile } from "node:fs/promises";
await build({
  entryPoints: ["assets/src/admin.jsx"],
  bundle: true,
  minify: true,
  target: ["es2020"],
  outfile: "assets/dist/admin.js",
  jsxFactory: "wp.element.createElement",
  jsxFragment: "wp.element.Fragment",
  legalComments: "none",
});
await writeFile(
  "assets/dist/admin.css",
  (await readFile("assets/src/admin.css", "utf8")) +
    "\n" +
    (await readFile("assets/src/design.css", "utf8")) +
    "\n" +
    (await readFile("assets/src/premium.css", "utf8")),
);
