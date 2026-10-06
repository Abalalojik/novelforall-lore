"""Package the plugin as an installable zip in C:\\ClaudeCode\\Builds\\NovelForAllLore (never in the sources)."""
import pathlib
import re
import zipfile

ROOT = pathlib.Path(__file__).resolve().parent.parent
PLUGIN = ROOT / "nfa-lore"
OUT = pathlib.Path(r"C:\ClaudeCode\Builds\NovelForAllLore")

version = re.search(r"Version:\s*([\d.]+)", (PLUGIN / "nfa-lore.php").read_text(encoding="utf8")).group(1)
OUT.mkdir(parents=True, exist_ok=True)
target = OUT / f"nfa-lore-{version}.zip"

with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as zf:
    for path in sorted(PLUGIN.rglob("*")):
        if path.is_file():
            zf.write(path, pathlib.PurePosixPath("nfa-lore", path.relative_to(PLUGIN).as_posix()))

print(target)
