"""Build responsive portraits from the high-resolution PNG masters.

Run once when artwork changes: python -m pip install Pillow; python scripts/prepare-god-images.py.
WebP files are committed; deployment needs neither Python nor an image-processing library.
Never use the old tiny WebP files as sources.
"""
from pathlib import Path
from PIL import Image, ImageOps

root = Path(__file__).resolve().parents[1] / "public/assets/gods"
count = 0
for folder in sorted(root.iterdir()):
    source = folder / "portrait.png"
    if not source.is_file():
        continue
    with Image.open(source) as original:
        image = ImageOps.exif_transpose(original).convert("RGB")
        if image.width < 960 or image.height < 1200:
            raise ValueError(f"Master is too small: {source}")
        for width, quality, filename in [
            (320, 84, "card-320.webp"),
            (640, 86, "card-640.webp"),
            (960, 90, "portrait.webp"),
        ]:
            resized = ImageOps.fit(image, (width, width * 5 // 4), Image.Resampling.LANCZOS, centering=(0.5, 0.5))
            resized.save(folder / filename, "WEBP", quality=quality, method=6)
            with Image.open(folder / filename) as check:
                check.load()
                if check.size != (width, width * 5 // 4):
                    raise ValueError(f"Unexpected image dimensions: {folder / filename}")
    count += 1

print(f"Prepared {count} portraits in three sizes.")
