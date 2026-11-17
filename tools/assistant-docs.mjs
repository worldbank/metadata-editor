// Mirror the editor's documentation into Markdown for the server assistant to search.
//
//   node tools/assistant-docs.mjs        (from the editor's folder; Node 18+, needs internet access)
//
// Writes assistant-docs/usage.md (the editor user guide), schema-guide.md (the metadata schema guide)
// and api.md (the editor API reference, from the in-tree OpenAPI spec). The FastAPI service reads them
// through ASSISTANT_DOCS (README-assistant.md there). They are generated, so they are not committed;
// re-run this when the published docs change, then restart the service so it re-indexes them.
//
// HTML becomes Markdown here, which is also what makes a retrieved section mean one topic instead of a
// menu. The OpenAPI spec is YAML; its human text is extracted line by line rather than taking a parser
// dependency.
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { createHash } from "node:crypto";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), "..");

const OUT = join(ROOT, "assistant-docs");
const ROOTS = [
  ["usage", "https://worldbank.github.io/metadata-editor-docs/", 200],
  ["schema-guide", "https://worldbank.github.io/schema-guide/", 120],
];
const decode = (s) => s.replace(/&nbsp;/g, " ").replace(/&amp;/g, "&").replace(/&lt;/g, "<")
  .replace(/&gt;/g, ">").replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&[a-z#0-9]+;/gi, " ");

function toMarkdown(html) {
  const body = html.replace(/<script[\s\S]*?<\/script>/gi, "").replace(/<style[\s\S]*?<\/style>/gi, "")
    .replace(/<(nav|header|footer)[\s\S]*?<\/\1>/gi, "");
  const lines = [];
  for (const m of body.matchAll(/<(h[1-6]|p|li|pre|td|th)\b[^>]*>([\s\S]*?)<\/\1>/gi)) {
    const tag = m[1].toLowerCase();
    const text = decode(m[2].replace(/<[^>]+>/g, " ")).replace(/\s+/g, " ").trim();
    if (text.length < 2) continue;
    if (tag.startsWith("h")) lines.push(`${"#".repeat(Number(tag[1]))} ${text}`);
    else if (tag === "li") lines.push(`- ${text}`);
    else if (tag === "td" || tag === "th") lines.push(`| ${text}`);
    else lines.push(text);
  }
  return lines.join("\n\n");
}

const page = (html) => (html.match(/<title[^>]*>([\s\S]*?)<\/title>/i)?.[1] ?? "").replace(/\s+/g, " ").trim();
mkdirSync(OUT, { recursive: true });
const summary = [];

for (const [name, root, cap] of ROOTS) {
  const seen = new Set(); const queue = [root]; const parts = []; let pages = 0;
  while (queue.length && pages < cap) {
    const url = queue.shift();
    if (seen.has(url)) continue; seen.add(url);
    let html;
    try {
      const res = await fetch(url, { redirect: "follow" });
      if (!res.ok || !/html/.test(res.headers.get("content-type") || "")) continue;
      html = await res.text();
    } catch { continue; }
    const md = toMarkdown(html);
    if (md.length < 400) continue;
    pages++;
    const slug = new URL(url).pathname.split("/").filter(Boolean).pop()?.replace(/\.html?$/, "") || "index";
    parts.push(`## ${page(html) || slug}\n\nSource: ${url}\n\n${md}`);
    for (const m of html.matchAll(/href="([^"#?]+)"/g)) {
      const abs = new URL(m[1], url).href;
      if (abs.startsWith(root) && !seen.has(abs) && /\.html?$|html?$/i.test(abs)) queue.push(abs);
    }
  }
  const text = parts.join("\n\n");
  writeFileSync(`${OUT}/${name}.md`, `${text}\n`);
  summary.push({ name, pages, bytes: Buffer.byteLength(text), source: root });
  console.log(`${name.padEnd(13)} ${String(pages).padStart(3)} pages, ${(Buffer.byteLength(text) / 1024).toFixed(0)} KB`);
}

// OpenAPI: keep the prose an engineer would ask about. Folded scalars (>- and |) are joined.
const yamlLines = readFileSync(join(ROOT, "api-documentation/editor/openapi.yaml"), "utf8").split("\n");
const api = [];
let paths = 0;
for (let i = 0; i < yamlLines.length; i++) {
  const line = yamlLines[i];
  const route = line.match(/^ {2}(\/[^:]+):\s*$/);
  if (route) { paths++; api.push(`## Endpoint ${route[1]}`); continue; }
  const field = line.match(/^(\s*)(summary|description|title):\s*(.*)$/);
  if (!field) continue;
  let text = field[3].trim();
  if (/^[>|][-+]?$/.test(text)) {
    const indent = field[1].length + 2;
    const block = [];
    while (i + 1 < yamlLines.length && (yamlLines[i + 1].trim() === "" || yamlLines[i + 1].search(/\S/) >= indent)) {
      block.push(yamlLines[++i].trim());
    }
    text = block.join(" ").trim();
  }
  text = decode(text);
  if (text.length > 3) api.push(field[2] === "title" ? `# ${text}` : text);
}
const apiText = api.join("\n\n");
writeFileSync(`${OUT}/api.md`, `${apiText}\n`);
summary.push({ name: "api", pages: paths, bytes: Buffer.byteLength(apiText), source: "in-tree openapi.yaml" });
console.log(`${"api".padEnd(13)} ${String(paths).padStart(3)} paths, ${(Buffer.byteLength(apiText) / 1024).toFixed(0)} KB`);

const total = summary.reduce((s, x) => s + x.bytes, 0);
const version = createHash("sha256").update(summary.map((s) => `${s.name}:${s.bytes}`).join("|")).digest("hex").slice(0, 16);
writeFileSync(`${OUT}/index.json`, `${JSON.stringify({ version, totalBytes: total, sources: summary }, null, 2)}\n`);
console.log(`\ntotal ${(total / 1024).toFixed(0)} KB, version ${version}, ~${Math.round(total / 480)} chunks at 480 chars`);
