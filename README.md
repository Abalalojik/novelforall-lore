# Novel For All Lore

WordPress plugin for novelforall.online: story structure (story › arc › tome › chapter) with continuous chapter navigation and an automatic table of contents, plus a world wiki per verse with `[[wiki links]]`, glossary tooltips in chapters, and spoiler control tied to published chapters.

## Layout

| Path | Content |
|---|---|
| `nfa-lore/` | The plugin itself (what goes in the zip). |
| `scripts/build.py` | Builds `C:\ClaudeCode\Builds\NovelForAllLore\nfa-lore-<version>.zip`. |
| `tests/` | Local WordPress Playground test site: blueprint, seed data, and two local-only helpers (debug log, login). Never shipped. |

## Features

### Stories (0.2.0)

- **Stories** (`nfa_story`, hierarchical): a top-level term is a story, its children arcs, their children tomes. Siblings are ordered by the term's *Order* field, or by the first number in its name ("Arc 0 — The Intern", "Tome 1"). A story is linked to its world (verse) once; its chapters inherit it for glossary tooltips.
- **Chapters** are posts filed under their tome, with a **chapter number**. Reading order follows the tree, then the number, never the publication date.
- **Blocks**: *Chapter Navigation* (previous · contents · next, continuous across tomes and arcs), *Story Contents* (grouped by arc and tome), *Story Breadcrumb*.
- **Story pages** at `/stories/{story}/{arc}/{tome}/`: title, description (synopsis) and contents.
- **No spoilers**: only published chapters count. An arc or tome without a published chapter is invisible (hidden from contents and REST, its page redirects to the story).

### Wiki (0.1.0)

- **Lore entries** (`nfa_lore`) classified by **verse** (`nfa_verse`, shared with chapters). URLs: `/wiki/{verse}/{entry}/`, verse wiki home at `/wiki/{verse}/`.
- **Wiki links** in lore entries only: `[[Entry]]`, `[[Entry|text]]`, `[[Entry#Section]]`. A link only appears when it leads somewhere; otherwise readers get plain text (editors see it in red).
- **Glossary**: an entry marked "Glossary term" (plus aliases) gets a tooltip on its first occurrence in chapters and entries of the same verse. Definition = the entry's excerpt. Skips headings, links, code and blockquotes (status windows).
- **Spoilers**: "Visible from chapter" per entry, and a **Spoiler** block per passage. Until the chapter is published (scheduled does not count), the content is invisible to readers: absent from lists, search, REST, sitemaps; a direct visit redirects to the verse wiki home.

## Build and test

```bash
python scripts/build.py
```

Local test server (Node 22 is needed by the Playground CLI; a local copy lives in the Builds folder):

```bash
cd /c/ClaudeCode/Builds/NovelForAllLore
MSYS_NO_PATHCONV=1 node_modules/node/bin/node.exe node_modules/@wp-playground/cli/wp-playground.js server --port 9400 --mount-dir "C:\ClaudeCode\Projects\NovelForAllLore\nfa-lore" /wordpress/wp-content/plugins/nfa-lore --mount-dir "C:\ClaudeCode\Projects\NovelForAllLore\tests" /wordpress/wp-content/nfa-tests --blueprint "C:\ClaudeCode\Projects\NovelForAllLore\tests\blueprint.json"
```

Then `http://127.0.0.1:9400/wp-content/nfa-tests/login.php?to=edit.php%3Fpost_type%3Dnfa_lore` for the admin side.
