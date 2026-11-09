"""Rebuild committed home derivatives: python -m pip install Pillow; python scripts/prepare-home-assets.py.

Original artwork stays in public/assets/figma/home. No Python is needed at runtime or deployment.
"""
from pathlib import Path
from PIL import Image, ImageOps

root = Path(__file__).resolve().parents[1]
source = root / "public/assets/figma/home"
target = root / "public/assets/home"
target.mkdir(parents=True, exist_ok=True)

for name, filename, widths in [
    ("hero", "hero.png", [640, 1280]),
    ("community", "communities-bg.png", [480, 960]),
    ("journal", "article-feature.png", [650]),
]:
    with Image.open(source / filename) as original:
        image = ImageOps.exif_transpose(original)
        for width in widths:
            height = round(image.height * width / image.width)
            image.resize((width, height), Image.Resampling.LANCZOS).save(
                target / f"{name}-{width}.webp", quality=86, method=6
            )

for filename in [*sorted(source.glob("holiday-*.png")), source / "wheel.png"]:
    with Image.open(filename) as image:
        image.resize((192, 192), Image.Resampling.LANCZOS).save(
            target / f"{filename.stem}-192.webp", quality=84, method=6
        )

print(f"Prepared {len(list(target.glob('*.webp')))} home images.")
