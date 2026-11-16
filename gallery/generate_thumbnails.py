#!/usr/bin/env python3
"""Generate gallery previews. Requires Pillow: python3 -m pip install Pillow."""
from pathlib import Path

from PIL import Image, ImageOps

GALLERY = Path(__file__).resolve().parent
PHOTO_DIRECTORY = GALLERY / "photos"
THUMBNAIL_DIRECTORY = GALLERY / "thumbnails"
EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}
SIZE = 640


def main():
    THUMBNAIL_DIRECTORY.mkdir(exist_ok=True)
    count = 0
    original_bytes = 0
    thumbnail_bytes = 0
    for source in sorted(PHOTO_DIRECTORY.iterdir()):
        if not source.is_file() or source.suffix.lower() not in EXTENSIONS:
            continue
        target = THUMBNAIL_DIRECTORY / (source.name + ".webp")
        if not target.exists() or source.stat().st_mtime > target.stat().st_mtime:
            with Image.open(source) as image:
                image = ImageOps.exif_transpose(image).convert("RGB")
                side = min(SIZE, *image.size)
                thumbnail = ImageOps.fit(image, (side, side), method=Image.Resampling.LANCZOS)
                thumbnail.save(target, "WEBP", quality=78, method=6)
        count += 1
        original_bytes += source.stat().st_size
        thumbnail_bytes += target.stat().st_size
    print(f"{count} thumbnails: {thumbnail_bytes / 1_000_000:.2f} MB "
          f"(originals: {original_bytes / 1_000_000:.2f} MB)")


if __name__ == "__main__":
    main()
