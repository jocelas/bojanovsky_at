# Gallery

`index.php` serves the gallery at `/gallery/`. The existing
`index_old_2.php` URL works too. Serve these files with PHP.

Add original photographs to `photos/` and optional captions in matching `.txt`
files (for example, `bus.jpg` and `bus.txt`). Then run:

```sh
python3 gallery/generate_thumbnails.py
```

Caption files are tracked in Git; original photographs remain ignored.

The script requires Pillow (`python3 -m pip install Pillow`). It creates small,
EXIF-oriented, square WebP previews in `thumbnails/` and skips unchanged files.
Upload the thumbnails along with the PHP, CSS, and JavaScript files. Originals
stay unchanged and are loaded when a visitor opens the viewer. If a preview is
missing, the gallery falls back to the original so new photos still appear.

Both the homepage and gallery follow the device's system color scheme. The
sun/moon button switches themes for the current page; a new visit or system
theme change follows the system again.
