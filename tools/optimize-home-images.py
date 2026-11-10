from pathlib import Path
from PIL import Image
import json

source = Path('public/assets/figma/home')
target = Path('public/assets/optimized-home')
target.mkdir(exist_ok=True)
manifest = {}
for path in sorted(source.glob('*.png')):
    image = Image.open(path).convert('RGBA' if Image.open(path).mode == 'RGBA' else 'RGB')
    width, height = image.size
    widths = [n for n in (160,320,640,960) if n < width] + [width]
    if path.stem == 'texture': widths = [622,1244,1866]
    if path.stem.startswith(('holiday-', 'category-')) or path.stem == 'basket': widths = [160,320,640]
    if path.stem == 'hero': widths = [640,width]
    variants = []
    for size in widths:
        resized = image if size == width else image.resize((size,round(height*size/width)),Image.Resampling.LANCZOS)
        output = target / f'{path.stem}-{size}.webp'
        resized.save(output,'WEBP',lossless=True,exact=True,method=4,icc_profile=Image.open(path).info.get('icc_profile', b''))
        restored = Image.open(output).convert(resized.mode)
        assert restored.tobytes() == resized.tobytes(), f'Lossless verification failed: {output}'
        if output.stat().st_size >= path.stat().st_size:
            continue
        variants.append({'src':str(output.relative_to('public')).replace('\\','/'),'width':size,'height':resized.height,'bytes':output.stat().st_size})
    if width not in [variant['width'] for variant in variants] and path.stem != 'texture':
        variants.append({'src':str(path.relative_to('public')).replace('\\','/'),'width':width,'height':height,'bytes':path.stat().st_size})
    manifest[path.name] = {'width':width,'height':height,'original_bytes':path.stat().st_size,'variants':variants}
    print(path.name, 'verified lossless', flush=True)
Path('resources/content/home-images.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')

used = {variant['src'] for entry in manifest.values() for variant in entry['variants']}
for output in target.glob('*.webp'):
    if output.resolve().is_relative_to(target.resolve()) and str(output.relative_to('public')).replace('\\','/') not in used:
        output.unlink()
