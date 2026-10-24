#!/usr/bin/env python3
"""Generate the lockup B artwork with the wordmark as SVG outlines (story 41).

Writes assets/lockup-b.svg (light: transparent, navy + grey text) and assets/lockup-b-dark.svg
(the band #33496b behind, white + #c3ccd9 text). The icon is copied verbatim from assets/icon.svg;
the wordmark "Post Quality" (Inter wght 600) and "Manager" (Inter wght 400) is outlined from the
installed Inter variable font (SIL OFL 1.1), so the files carry no <text> and need no web font.

Geometry (docs/brand.md § Lockup, in units of the tile side T = 32): wordmark set at 0.63 T, so its
cap height matches the Q's ring; gap icon → text 0.375 T; clear space 0.5 T on all four sides.
The cap height is centred on the ring's centre line.

Run from the repository root:
    uv run --no-cache --with fonttools python -I bin/outline-wordmark.py [--font PATH] [--out-dir assets]

Kerning: the GPOS "kern" feature's pair-adjustment lookups (PairPos format 1 and 2) are applied for
each adjacent pair inside a word; contextual or chained lookups are not (Inter has none for Latin
kerning). Coordinates are rounded to 2 decimals.
"""

import argparse
import os
import re
import sys

try:
    from fontTools.pens.svgPathPen import SVGPathPen
    from fontTools.pens.transformPen import TransformPen
    from fontTools.ttLib import TTFont
    from fontTools.varLib.instancer import instantiateVariableFont
except ImportError:  # pragma: no cover
    sys.exit("fontTools is required: run with `uv run --no-cache --with fonttools python -I bin/outline-wordmark.py`")

TILE = 32.0  # T
EM = 0.63 * TILE  # wordmark size (font-size) in T-units
GAP = 0.375 * TILE  # icon → text
CLEAR = 0.5 * TILE  # clear space on all four sides
OPSZ = 20  # Inter's optical size axis, at the wordmark's px size

RUNS = [
    # text, wght, (light colour, dark colour)
    ("Post Quality ", 600, ("#1b2a41", "#ffffff")),
    ("Manager", 400, ("#50575e", "#c3ccd9")),
]
BAND = "#33496b"
LICENCE = "<!-- Wordmark: Inter (SIL OFL 1.1), outlined; regenerate with bin/outline-wordmark.py -->"


def default_font_path():
    local = os.environ.get("LOCALAPPDATA", "")
    return os.path.join(local, "Microsoft", "Windows", "Fonts", "Inter-VariableFont_opsz,wght.ttf")


def ntos(value):
    """Number to string, 2 decimals, no trailing zeros, no negative zero."""
    text = ("%.2f" % value).rstrip("0").rstrip(".")
    return "0" if text in ("", "-0") else text


def pair_kern(gpos, first, second, class_of):
    """Horizontal advance adjustment for the pair (first, second) from the kern feature's PairPos lookups."""
    lookup_indices = []
    for record in gpos.FeatureList.FeatureRecord:
        if record.FeatureTag == "kern":
            for index in record.Feature.LookupListIndex:
                if index not in lookup_indices:
                    lookup_indices.append(index)
    total = 0
    for index in lookup_indices:
        lookup = gpos.LookupList.Lookup[index]
        subtables = lookup.SubTable
        if lookup.LookupType == 9:  # extension
            subtables = [st.ExtSubTable for st in subtables]
        if lookup.LookupType not in (2, 9):
            continue
        for subtable in subtables:
            if getattr(subtable, "Format", None) not in (1, 2) or first not in subtable.Coverage.glyphs:
                continue
            value = None
            if subtable.Format == 1:
                pair_set = subtable.PairSet[subtable.Coverage.glyphs.index(first)]
                for pair in pair_set.PairValueRecord:
                    if pair.SecondGlyph == second:
                        value = pair.Value1
                        break
            else:
                c1 = class_of(subtable.ClassDef1, first)
                c2 = class_of(subtable.ClassDef2, second)
                if c1 < subtable.Class1Count and c2 < subtable.Class2Count:
                    value = subtable.Class1Record[c1].Class2Record[c2].Value1
            if value is not None:
                total += getattr(value, "XAdvance", 0) or 0
                break  # the first matching subtable of a lookup applies
    return total


def class_lookup(class_def, glyph):
    if class_def is None:
        return 0
    return class_def.classDefs.get(glyph, 0)


def outline_run(font, text, x, baseline, scale):
    """Draw `text` with `font` into one SVG path, starting at x. Returns (path d, next x, glyph count)."""
    glyph_set = font.getGlyphSet()
    cmap = font.getBestCmap()
    hmtx = font["hmtx"]
    gpos = font["GPOS"].table if "GPOS" in font else None
    glyphs = []
    for char in text:
        name = cmap.get(ord(char))
        if name is None:
            sys.exit("Inter has no glyph for %r" % char)
        glyphs.append(name)
    pen = SVGPathPen(glyph_set, ntos=ntos)
    for i, name in enumerate(glyphs):
        glyph_set[name].draw(TransformPen(pen, (scale, 0, 0, -scale, x, baseline)))
        advance = hmtx[name][0]
        if gpos is not None and i + 1 < len(glyphs):
            advance += pair_kern(gpos, name, glyphs[i + 1], class_lookup)
        x += advance * scale
    return pen.getCommands(), x, len(glyphs)


def icon_artwork(icon_path):
    """The tile rect and the mark group from assets/icon.svg, verbatim."""
    with open(icon_path, encoding="utf-8") as handle:
        svg = handle.read()
    match = re.search(r'(<rect width="20" height="20" fill="#1b2a41"/>)(<g transform="translate\(3 3\) scale\(0\.7\)">.*?</g>)', svg, re.S)
    if not match:
        sys.exit("Could not find the tile rect and the mark group in %s" % icon_path)
    return match.group(1) + match.group(2)


def ring_centre_y(icon_path):
    """The Q ring's centre, in tile units from the tile's top edge, read from the icon's first arc path."""
    with open(icon_path, encoding="utf-8") as handle:
        svg = handle.read()
    match = re.search(r'translate\(([\d.]+) ([\d.]+)\) scale\(([\d.]+)\)">\s*<path[^>]*d="M [\d.]+ ([\d.]+) A', svg)
    if not match:
        sys.exit("Could not read the ring geometry from %s" % icon_path)
    _, ty, s, ring_y = (float(v) for v in match.groups())
    return (ty + ring_y * s) * TILE / 20.0  # the icon is drawn on a 20-unit grid


def main():
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--font", default=default_font_path(), help="path to Inter-VariableFont_opsz,wght.ttf")
    parser.add_argument("--icon", default=os.path.join("assets", "icon.svg"))
    parser.add_argument("--out-dir", default="assets")
    args = parser.parse_args()

    if not os.path.isfile(args.font):
        sys.exit("Inter variable font not found at %s (pass --font PATH)" % args.font)

    variable = TTFont(args.font)
    upm = variable["head"].unitsPerEm
    cap_height = variable["OS/2"].sCapHeight
    scale = EM / upm
    ring_y = CLEAR + ring_centre_y(args.icon)
    baseline = ring_y + cap_height * scale / 2.0

    instances = {}
    for _, wght, _ in RUNS:
        if wght not in instances:
            instances[wght] = instantiateVariableFont(TTFont(args.font), {"wght": wght, "opsz": OPSZ}, inplace=False)

    x = CLEAR + TILE + GAP
    paths = []
    glyph_count = 0
    for text, wght, colours in RUNS:
        d, x, count = outline_run(instances[wght], text, x, baseline, scale)
        paths.append((d, colours))
        glyph_count += count
    width = round(x + CLEAR + 0.005, 2)
    height = TILE + 2 * CLEAR
    view_box = "0 0 %s %s" % (ntos(width), ntos(height))

    icon = icon_artwork(args.icon)
    icon_group = '<g transform="translate(%s %s) scale(%s)">%s</g>' % (ntos(CLEAR), ntos(CLEAR), ntos(TILE / 20.0), icon)

    for variant, filename in ((0, "lockup-b.svg"), (1, "lockup-b-dark.svg")):
        parts = ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s">' % view_box, "<title>Post Quality Manager</title>", LICENCE]
        if variant == 1:
            parts.append('<rect width="%s" height="%s" fill="%s"/>' % (ntos(width), ntos(height), BAND))
        parts.append(icon_group)
        for d, colours in paths:
            parts.append('<path fill="%s" d="%s"/>' % (colours[variant], d))
        parts.append("</svg>")
        out = os.path.join(args.out_dir, filename)
        with open(out, "w", encoding="utf-8", newline="\n") as handle:
            handle.write("".join(parts) + "\n")
        print("%s viewBox=%s" % (out, view_box))
    print("glyphs outlined: %d (cap height %d/%d em, scale %.5f)" % (glyph_count, cap_height, upm, scale))


if __name__ == "__main__":
    main()
