"""Shrinks a PDF for hosts with an upload size limit (used by build.sh).

1. Drops Illustrator's private editing data (/PieceInfo): invisible, often more than half the file.
2. Re-encodes images as JPEG, downscaled to at most MAX_PX on the long side (soft masks are kept, losslessly).
3. Losslessly compresses streams that were stored uncompressed, and drops unreferenced objects.
Text and vector art are untouched, so text stays sharp and selectable.

    python shrink_pdf.py IN.pdf OUT.pdf [MAX_PX] [JPEG_QUALITY]
"""
import io
import sys
import zlib

from PIL import Image
from pypdf import PdfReader, PdfWriter
from pypdf.generic import DictionaryObject, NameObject, NumberObject, StreamObject

src, dst = sys.argv[1], sys.argv[2]
max_px = int(sys.argv[3]) if len(sys.argv) > 3 else 2400
quality = int(sys.argv[4]) if len(sys.argv) > 4 else 82

writer = PdfWriter(clone_from=PdfReader(src))
# Pages and every form XObject can carry its own /PieceInfo.
for obj in writer._objects:
    if isinstance(obj, DictionaryObject) and '/PieceInfo' in obj:
        del obj['/PieceInfo']


def jpeg_bytes(im):
    out = io.BytesIO()
    im.save(out, 'JPEG', quality=quality, optimize=True)
    return out.getvalue()


def plain_colorspace(xobject):
    cs = xobject.get('/ColorSpace')
    cs = cs.get_object() if cs is not None else None
    if isinstance(cs, list):
        return cs[0] == '/ICCBased' and cs[1].get_object().get('/N') in (3, 4)
    return cs in ('/DeviceRGB', '/DeviceCMYK')


def shrink_masked(xobject, im):
    """Colour -> JPEG, soft mask -> Flate greyscale at the same size, so transparency is kept."""
    smask = xobject['/SMask'].get_object()
    alpha = im.getchannel('A') if im.mode in ('RGBA', 'LA') else smask.decode_as_image().convert('L')
    color = im.convert('RGB')
    if max(color.size) > max_px:
        color.thumbnail((max_px, max_px), Image.LANCZOS)
    alpha = alpha.resize(color.size, Image.LANCZOS)
    for key in ('/DecodeParms', '/Decode', '/Interpolate'):
        for obj in (xobject, smask):
            if key in obj:
                del obj[key]
    xobject._data = jpeg_bytes(color)
    xobject.update({NameObject('/Filter'): NameObject('/DCTDecode'), NameObject('/ColorSpace'): NameObject('/DeviceRGB'),
                    NameObject('/BitsPerComponent'): NumberObject(8),
                    NameObject('/Width'): NumberObject(color.width), NameObject('/Height'): NumberObject(color.height)})
    smask._data = zlib.compress(alpha.tobytes(), 9)
    smask.update({NameObject('/Filter'): NameObject('/FlateDecode'), NameObject('/ColorSpace'): NameObject('/DeviceGray'),
                  NameObject('/BitsPerComponent'): NumberObject(8),
                  NameObject('/Width'): NumberObject(alpha.width), NameObject('/Height'): NumberObject(alpha.height)})


for page in writer.pages:
    for img in page.images:
        xobject = img.indirect_reference.get_object() if img.indirect_reference else {}
        try:
            im = img.image
            if '/Mask' in xobject:
                continue  # stencil / colour-key masks: leave as they are
            if '/SMask' in xobject:
                if plain_colorspace(xobject):  # Separation/Indexed inks would come out inverted
                    shrink_masked(xobject, im)
                continue
            if im.mode not in ('RGB', 'L'):
                im = im.convert('RGB')
            if max(im.size) > max_px:
                im.thumbnail((max_px, max_px), Image.LANCZOS)
            img.replace(im, quality=quality)
        except Exception as exc:  # leave images pypdf cannot decode as they are
            print(f'kept image as-is: {exc}', file=sys.stderr)
    page.compress_content_streams()
writer.compress_identical_objects(remove_duplicates=True, remove_unreferenced=True)

# 3. Flate-compress (lossless) every stream the source left uncompressed, e.g. Illustrator's vector forms.
for obj in writer._objects:
    if isinstance(obj, StreamObject) and '/Filter' not in obj:
        obj._data = zlib.compress(obj._data, 9)
        obj[NameObject('/Filter')] = NameObject('/FlateDecode')
# 4. Re-clone so only objects reachable from the pages are written (drops the orphaned originals).
buffer = io.BytesIO()
writer.write(buffer)
final = PdfWriter(clone_from=PdfReader(buffer))
with open(dst, 'wb') as fh:
    final.write(fh)
