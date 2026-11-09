"""Migrate current homepage news and the creative catalogue; preserve deployed article URLs.

Requires PHP and Pillow at import time. All reading content is committed for deployment.
"""
from html.parser import HTMLParser
import hashlib
import html
import io
import json
import os
from pathlib import Path
import re
import subprocess
import urllib.parse
import urllib.request

from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parents[1]
CONTENT = ROOT / 'resources/content/publications'
ASSETS = ROOT / 'public/assets/publications'
CACHE = ROOT.parent / 'news-sources'
PHP = os.environ.get('PHP_BINARY', 'php')
CONTENT.mkdir(exist_ok=True)
(CONTENT / 'pages').mkdir(exist_ok=True)
CACHE.mkdir(exist_ok=True)


def absolute(url, base='https://www.svit.in.ua/'):
    parts = urllib.parse.urlsplit(urllib.parse.urljoin(base, html.unescape(url)))
    if parts.hostname in ['svit.in.ua', 'www.svit.in.ua']:
        return urllib.parse.urlunsplit(('https', 'www.svit.in.ua', parts.path, parts.query, parts.fragment))
    return urllib.parse.urlunsplit(parts)


def fetch(url, refresh=False):
    key = CACHE / hashlib.sha256(url.encode()).hexdigest()
    if refresh or not key.exists():
        request = urllib.request.Request(urllib.parse.quote(url, safe=':/?=&%'), headers={'User-Agent': 'RidnaViraMigration/1.0 (+https://ridnavira.com.ua)'})
        with urllib.request.urlopen(request, timeout=60) as response:
            key.write_bytes(response.read())
    return key.read_bytes()


def source(url, refresh=False):
    raw = fetch(url, refresh)
    try:
        return raw.decode('utf-8')
    except UnicodeDecodeError:
        return raw.decode('cp1251')


def php(script, value):
    return subprocess.run([PHP, str(ROOT / 'scripts' / script)], input=json.dumps(value, ensure_ascii=False), text=True,
                          encoding='utf-8', capture_output=True, check=True).stdout


def plain(value):
    return re.sub(r'\s+', ' ', html.unescape(re.sub('<[^>]+>', ' ', value))).strip()


images = {}
unavailable = []


def image(url):
    if url in images:
        return images[url]
    try:
        raw = fetch(url)
    except urllib.error.HTTPError as error:
        if error.code == 404:
            unavailable.append(url)
            return None
        raise
    original = Image.open(io.BytesIO(raw))
    directory = ASSETS / 'images'
    directory.mkdir(parents=True, exist_ok=True)
    name = hashlib.sha256(url.encode()).hexdigest()[:14]
    if getattr(original, 'is_animated', False):
        filename = name + '.gif'
        (directory / filename).write_bytes(raw)
        result = {'src': '/assets/publications/images/' + filename, 'width': original.width, 'height': original.height}
    else:
        original = ImageOps.exif_transpose(original).convert('RGB')
        variants = []
        for width in sorted(set([min(320, original.width), min(640, original.width), min(1280, original.width)])):
            resized = original.copy()
            resized.thumbnail((width, 2200))
            filename = f'{name}-{resized.width}.webp'
            if not (directory / filename).exists():
                resized.save(directory / filename, 'WEBP', quality=85, method=6)
            variants.append({'src': '/assets/publications/images/' + filename, 'width': resized.width, 'height': resized.height})
        result = {**variants[-1], 'variants': variants}
    images[url] = result
    return result


previous = json.loads((CONTENT / 'catalogue.json').read_text(encoding='utf-8')) if (CONTENT / 'catalogue.json').exists() else {}
catalogue = {'news': previous.get('news', {}), 'creativity': {}, 'articles': {}}
news = json.loads(php('extract-publications.php', {'mode': 'news', 'html': source(absolute('index.htm'), refresh=True)}))
creative = json.loads(php('extract-publications.php', {'mode': 'creative', 'html': source(absolute('tvo.htm'), refresh=True)}))
prayer_aliases = {'yakovenko': 'yana-yakovenko', 'pashnyk2': 'svitovyt-pashnyk', 'marosha': 'marosha-liutytsia',
                  'rodomysl': 'rodomysl-bahanetskyi', 'arkusha': 'ihor-oksana-arkushi', 'yaromyr': 'yaromyr-sicheslavskyi'}
for item in news:
    slug = item['date'] + '-' + hashlib.sha256((item['date'] + item['title']).encode()).hexdigest()[:8]
    catalogue['news'][slug] = {**item, 'slug': slug, 'source': 'index.htm'}
for item in creative:
    slug = Path(item['source']).stem
    if slug == 'mal4':
        item.update(title='Іван Просяник — художник і зільник', author='Іван Просяник')
    catalogue['creativity'][slug] = {**item, 'slug': slug}
    if slug in prayer_aliases:
        catalogue['creativity'][slug]['redirect'] = '/ridna-vira/molytvy/' + prayer_aliases[slug]

for slug, title, path in [
    ('struktura-zamovliannia-u-slovian', 'Структура замовляння у слов’ян', 'stat/st60.htm'),
    ('dytyna-u-tradytsiinykh-zvychaiakh', 'Дитина в традиційних звичаях і віруваннях українців Закарпаття', 'stat/st57.htm'),
    ('sosnu-pidpalyly', 'Сосну підпалили — Галю заміж віддали', 'stat/st73.htm'),
    ('vesillia-u-folklornii-tradytsii', 'Весілля в українській фольклорній традиції', 'stat/st25.htm'),
]:
    catalogue['articles'][slug] = {'slug': slug, 'title': title, 'source': path, 'source_url': absolute(path), 'extension': 'html', 'bundled': True}

routes = {absolute(e['source']): '/' + ('tvorchist' if section == 'creativity' else 'statti') + '/' + slug
          for section in ['creativity', 'articles'] for slug, e in catalogue[section].items()}
routes.update({absolute('mol.htm'): '/ridna-vira/molytvy', absolute('obr.htm'): '/ridna-vira/obriady',
               absolute('sta.htm'): '/statti', absolute('tvo.htm'): '/tvorchist'})


class LocalHTML(HTMLParser):
    def __init__(self, url):
        super().__init__(convert_charrefs=False)
        self.url, self.parts, self.images = url, [], []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'img':
            image_alt = attrs.get('alt', '')
            visual = image(absolute(attrs['src'], self.url))
            if visual is None:
                return
            self.images.append(visual)
            attrs = {k: v for k, v in visual.items() if k != 'variants'}
            attrs.update(alt=image_alt, loading='lazy', decoding='async')
            if 'variants' in visual:
                attrs.update(srcset=', '.join(f"{v['src']} {v['width']}w" for v in visual['variants']), sizes='(min-width: 900px) 760px, calc(100vw - 64px)')
        if tag == 'a':
            url = absolute(attrs.get('href', ''), self.url)
            if url in routes:
                attrs['href'] = routes[url]
            elif re.search(r'\.(?:jpe?g|png|gif|webp)$', urllib.parse.urlsplit(url).path, re.I):
                visual = image(url)
                attrs['href'] = visual['src'] if visual else '#'
            elif 'oru.org.ua' in urllib.parse.urlsplit(url).netloc:
                attrs = {}
            else:
                attrs.update(href=url, target='_blank', rel='noopener noreferrer')
        self.parts.append('<' + tag + ''.join(f' {key}="{html.escape(str(value), quote=True)}"' for key, value in attrs.items() if value is not None) + '>')

    def handle_endtag(self, tag):
        self.parts.append('</' + tag + '>')

    def handle_data(self, value):
        self.parts.append(re.sub(r'\s+', ' ', value))

    def handle_entityref(self, name):
        self.parts.append('&' + name + ';')

    def handle_charref(self, name):
        self.parts.append('&#' + name + ';')


for section, entries in catalogue.items():
    for slug, entry in entries.items():
        if section == 'news' and 'html' not in entry and entry.get('path') and (CONTENT / entry['path']).exists():
            continue
        if entry.get('redirect'):
            entry['excerpt'] = 'Авторські молитви та славлення. ' + entry['author'] + '.'
            continue
        url = absolute(entry['source'])
        original = entry.pop('html', None) if section == 'news' else source(url)
        entry['videos'] = list(dict.fromkeys(entry.get('videos', []) + re.findall(r'youtube\.com/embed/([a-zA-Z0-9_-]{11})', original)))
        cleaned = php('clean-ritual-page.php', {'html': original, 'title': entry['title'], 'source': url, 'gallery': section == 'creativity'})
        parser = LocalHTML(url)
        parser.feed(cleaned)
        content = ''.join(parser.parts).strip()
        if not plain(content) and not parser.images and not entry['videos']:
            raise ValueError('Empty material: ' + slug)
        path = 'pages/' + section + '-' + slug + '.html'
        (CONTENT / path).write_text(re.sub(r'(</(?:p|h[234]|blockquote|ul|ol)>)', r'\1\n', content).rstrip() + '\n', encoding='utf-8')
        entry['path'] = path
        entry['excerpt'] = plain(content)[:187].rsplit(' ', 1)[0] + '…' if len(plain(content)) > 190 else plain(content)
        entry['image'] = parser.images[0] if parser.images else None
        print(section, slug, len(content), 'characters', len(parser.images), 'images', flush=True)

for entry in catalogue['news'].values():
    if not entry.get('image') and entry.get('videos'):
        video = entry['videos'][0]
        for quality in ['maxresdefault', 'hqdefault']:
            cover = image(f'https://i.ytimg.com/vi/{video}/{quality}.jpg')
            if cover is not None:
                entry['image'] = cover
                entry['image_origin'] = 'youtube'
                entry['video_cover_id'] = video
                break

catalogue['featured_creativity'] = ['vakulenko', 'rush1', 'vir4', 'mal1']
catalogue['imported_at'] = '2026-11-09'
catalogue['unavailable_images'] = sorted(set(unavailable))
(CONTENT / 'catalogue.json').write_text(json.dumps(catalogue, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print('Complete:', len(news), 'news,', len(creative), 'creative entries,', len(images), 'images', flush=True)
