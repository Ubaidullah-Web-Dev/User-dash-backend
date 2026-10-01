"""Re-encodes images whose contents don't match their extension (used by build.sh).

InfinityFree re-processes every uploaded image and locks (chmod 000 -> "403 Forbidden") files it can't
read as the format their extension claims, e.g. an AVIF photo saved as "photo.jpg".

    python fix_images.py PUBLIC_DIR
"""
import os
import sys

from PIL import Image

FORMATS = {'.jpg': 'JPEG', '.jpeg': 'JPEG', '.png': 'PNG', '.gif': 'GIF', '.webp': 'WEBP'}

root = sys.argv[1]
for dirpath, _, filenames in os.walk(root):
    for name in filenames:
        expected = FORMATS.get(os.path.splitext(name)[1].lower())
        if not expected:
            continue
        path = os.path.join(dirpath, name)
        try:
            with Image.open(path) as im:
                actual = im.format
                if actual == expected:
                    continue
                im.load()
                out = im.convert('RGB') if expected == 'JPEG' else im
                out.save(path + '.tmp', expected, **({'quality': 90, 'optimize': True} if expected == 'JPEG' else {}))
        except Exception as exc:
            print(f'  could not check {path}: {exc}', file=sys.stderr)
            continue
        os.replace(path + '.tmp', path)
        print(f'  {os.path.relpath(path, root)}: {actual} -> {expected}')
