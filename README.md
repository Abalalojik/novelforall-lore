# Novel For All Lore

WordPress plugin for novelforall.online: a world wiki per verse, `[[wiki links]]`, glossary tooltips in chapters, and spoiler control tied to published chapters.

## Layout

| Path | Content |
|---|---|
| `nfa-lore/` | The plugin itself (what goes in the zip). |
| `scripts/build.py` | Builds `C:\ClaudeCode\Builds\NovelForAllLore\nfa-lore-<version>.zip`. |
| `tests/` | Local WordPress Playground test site: blueprint, seed data, and two local-only helpers (debug log, login). Never shipped. |

## Features (0.1.0)

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
