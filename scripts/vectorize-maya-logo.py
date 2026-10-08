"""Create portable, transparent vector logos from the approved Maya artwork."""
from pathlib import Path
import sys
import re
import xml.etree.ElementTree as ET

import numpy as np
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tmp/logo-vector-tools'))
import vtracer

OUT = ROOT / 'output'
TEMP = ROOT / 'tmp/maya-logo'
TEMP.mkdir(exist_ok=True)
pixels = np.asarray(Image.open(OUT / 'ferienwohnung-maya-logo.png').convert('RGB')).astype(int)
r, g, b = pixels[:, :, 0], pixels[:, :, 1], pixels[:, :, 2]
masks = {
    'symbol': (r > 150) & (g > 80) & (b < 170) & ((r - b) > 45),
    'wordmark': pixels.max(axis=2) < 160,
}
paths = {}
# Match the caption's right edge to the upright stem, keeping glyphs undistorted.
caption_right = np.where(masks['wordmark'][175:235, 1230:])[1].max() + 1230 + 1
stem_right = np.where(masks['wordmark'][380:441, 1800:])[1].max() + 1800 + 1
tracking_reduction = float(caption_right - stem_right)
for name, mask in masks.items():
    image_path = TEMP / f'{name}.png'
    svg_path = TEMP / f'{name}.svg'
    Image.fromarray(np.where(mask, 0, 255).astype('uint8')).save(image_path)
    vtracer.convert_image_to_svg_py(str(image_path), str(svg_path), colormode='binary',
        mode='spline', filter_speckle=12, corner_threshold=60, length_threshold=3.5,
        max_iterations=10, splice_threshold=45, path_precision=2)
    root = ET.parse(svg_path).getroot()
    elements = list(root.iter('{http://www.w3.org/2000/svg}path'))
    caption = []
    if name == 'wordmark':
        for element in elements:
            origin = [float(n) for n in re.findall(r'-?\d+(?:\.\d+)?', element.get('transform', ''))]
            if len(origin) == 2 and origin[0] > 1200 and origin[1] < 240:
                caption.append((origin[0], element))
    caption.sort(key=lambda item: item[0])
    offsets = {id(element): -tracking_reduction * i / (len(caption) - 1)
               for i, (_, element) in enumerate(caption)}
    paths[name] = []
    for path in elements:
        attrs = {key: value for key, value in path.attrib.items() if key != 'fill'}
        markup = '<path ' + ' '.join(f'{key}="{value}"' for key, value in attrs.items()) + '/>'
        if id(path) in offsets:
            markup = f'<g transform="translate({offsets[id(path)]:.3f} 0)">{markup}</g>'
        paths[name].append(markup)

ys, xs = np.where(masks['symbol'] | masks['wordmark'])
x, y = int(xs.min()) - 24, int(ys.min()) - 24
w, h = int(xs.max()) - x + 25, int(ys.max()) - y + 25
for variant, text_color in [('original', '#15232D'), ('hell', '#F7F4ED')]:
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="{x} {y} {w} {h}" role="img" aria-labelledby="title desc">
<title id="title">Ferienwohnung Maya</title>
<desc id="desc">Goldenes Dach mit Sonne und Seewelle neben dem Schriftzug Ferienwohnung Maya. Transparenter Hintergrund.</desc>
<g id="symbol" fill="#D8AB62">{''.join(paths['symbol'])}</g>
<g id="wordmark" fill="{text_color}">{''.join(paths['wordmark'])}</g>
</svg>
'''
    dest = OUT / f'ferienwohnung-maya-logo-{variant}.svg'
    dest.write_text(svg, encoding='utf-8')
    parsed = ET.fromstring(svg)
    assert '<image' not in svg and '<text' not in svg and 'data:' not in svg
    print(f'{dest.name}: {dest.stat().st_size} bytes; {len(paths["symbol"])} symbol paths, {len(paths["wordmark"])} lettering paths')

preview = '''<!doctype html><html lang="de"><meta charset="utf-8"><title>Maya – Logo-Varianten</title>
<style>*{box-sizing:border-box}body{margin:0;padding:36px;background:#e8e7e4;font:16px system-ui;color:#15232d}section{padding:30px 42px;margin:0 0 24px;border-radius:12px;background:#fff}section.dark{background:#15232d;color:#f7f4ed}h2{font-size:14px;font-weight:500;margin:0 0 26px}img{display:block;width:100%;max-width:860px;height:auto;margin:auto}.small{width:250px;margin:30px 0 0}</style>
<section><h2>Original · für helle Hintergründe</h2><img src="ferienwohnung-maya-logo-original.svg"><img class="small" src="ferienwohnung-maya-logo-original.svg"></section>
<section class="dark"><h2>Helle Variante · für dunkle Hintergründe</h2><img src="ferienwohnung-maya-logo-hell.svg"><img class="small" src="ferienwohnung-maya-logo-hell.svg"></section></html>'''
(OUT / 'ferienwohnung-maya-logo-vorschau.html').write_text(preview, encoding='utf-8')
