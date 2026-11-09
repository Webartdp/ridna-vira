"""Import the approved prayers section; requires PHP and Pillow only during migration.

python scripts/import-svit-prayers.py (PHP_BINARY can override the PHP executable).
Review generated reading blocks and keep corrections in prayers/editorial.json.
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
CONTENT = ROOT / 'resources/content/prayers'
ASSETS = ROOT / 'public/assets/prayers'
CACHE = ROOT.parent / 'prayer-sources'
CACHE.mkdir(exist_ok=True)
catalogue = json.loads((CONTENT / 'catalogue.json').read_text(encoding='utf-8'))
editorial = json.loads((CONTENT / 'editorial.json').read_text(encoding='utf-8')) if (CONTENT / 'editorial.json').exists() else {}
PHP = os.environ.get('PHP_BINARY', 'php')


def absolute(url, base='https://www.svit.in.ua/'):
    url = urllib.parse.urljoin(base, html.unescape(url))
    parts = urllib.parse.urlsplit(url)
    if parts.hostname in ['svit.in.ua', 'www.svit.in.ua']:
        url = urllib.parse.urlunsplit(('https', 'www.svit.in.ua', parts.path, parts.query, parts.fragment))
    return url


def fetch(url):
    path = CACHE / hashlib.sha256(url.encode()).hexdigest()
    if not path.exists():
        # Quote non-ASCII paths while retaining URL punctuation.
        request = urllib.request.Request(urllib.parse.quote(url, safe=':/?=&%'), headers={'User-Agent': 'Mozilla/5.0'})
        try:
            with urllib.request.urlopen(request, timeout=60) as response:
                path.write_bytes(response.read())
        except Exception as error:
            raise RuntimeError(url) from error
    return path.read_bytes()


files = {}
images = {}
missing_images = []
routes = {absolute(entry['source']): '/ridna-vira/molytvy/' + slug for slug, entry in catalogue['entries'].items()}
routes[absolute('mol.htm')] = '/ridna-vira/molytvy'
routes[absolute('obr.htm')] = '/ridna-vira/obriady'


def local_file(url):
    if url not in files:
        data = fetch(url)
        extension = Path(urllib.parse.urlsplit(url).path).suffix.lower()[1:]
        if extension == 'pdf' and not data.startswith(b'%PDF'):
            raise ValueError('Invalid PDF: ' + url)
        if extension == 'djvu' and not data.startswith(b'AT&TFORM'):
            raise ValueError('Invalid DJVU: ' + url)
        name = hashlib.sha256(url.encode()).hexdigest()[:10] + '-' + Path(urllib.parse.urlsplit(url).path).name
        destination = ASSETS / 'files' / name
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_bytes(data)
        files[url] = {'path': 'files/' + name, 'format': extension, 'bytes': len(data)}
    return files[url]


def local_image(url):
    # The original Word page points to a missing preview; its VML image is available.
    if url.endswith('/molytva/ml1.files/image002.jpg'):
        url = url.replace('image002.jpg', 'image001.jpg')
    if url not in images:
        original = ImageOps.exif_transpose(Image.open(io.BytesIO(fetch(url)))).convert('RGB')
        name = hashlib.sha256(url.encode()).hexdigest()[:12]
        directory = ASSETS / 'images'
        directory.mkdir(parents=True, exist_ok=True)
        variants = []
        for width in sorted(set([min(480, original.width), min(960, original.width)])):
            resized = original.copy()
            resized.thumbnail((width, 2000))
            filename = f'{name}-{resized.width}.webp'
            resized.save(directory / filename, 'WEBP', quality=84, method=6)
            variants.append((resized.width, resized.height, '/assets/prayers/images/' + filename))
        w, h, src = variants[-1]
        images[url] = {'src': src, 'width': w, 'height': h, 'loading': 'lazy', 'decoding': 'async',
                       'srcset': ', '.join(f'{src} {w}w' for w, h, src in variants),
                       'sizes': '(min-width: 900px) 760px, calc(100vw - 64px)'}
    return images[url]


class LocalHTML(HTMLParser):
    def __init__(self, source, title):
        super().__init__(convert_charrefs=False)
        self.source, self.title, self.parts = source, title, []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'a':
            url = absolute(attrs.get('href', ''), self.source)
            if 'oru.org.ua' in urllib.parse.urlsplit(url).netloc:
                attrs = {}
            elif url in routes:
                attrs['href'] = routes[url]
            elif Path(urllib.parse.urlsplit(url).path).suffix.lower() in ['.mp3', '.djvu']:
                attrs['href'] = '/assets/prayers/' + local_file(url)['path']
            else:
                attrs.update(href=url, target='_blank', rel='noopener noreferrer')
        elif tag == 'img':
            url = absolute(attrs['src'], self.source)
            try:
                attrs = {**local_image(url), 'alt': attrs.get('alt') or self.title}
            except RuntimeError as error:
                if isinstance(error.__cause__, urllib.error.HTTPError) and error.__cause__.code == 404:
                    missing_images.append(url)
                    return
                raise
        self.parts.append('<' + tag + ''.join(f' {k}="{html.escape(str(v), quote=True)}"' for k, v in attrs.items() if v is not None) + '>')

    def handle_endtag(self, tag):
        self.parts.append('</' + tag + '>')

    def handle_data(self, data):
        self.parts.append(re.sub(r'\s+', ' ', data))

    def handle_entityref(self, name):
        self.parts.append('&' + name + ';')

    def handle_charref(self, name):
        self.parts.append('&#' + name + ';')


def text(fragment):
    return re.sub(r'\s+', ' ', html.unescape(re.sub('<[^>]+>', '', fragment))).strip()


known_videos = {v['id']: v['title'] for v in catalogue['videos']}
(CONTENT / 'pages').mkdir(exist_ok=True)
for slug, entry in catalogue['entries'].items():
    url = absolute(entry['source'])
    entry['files'], entry['audio'], entry['videos'] = [], [], []
    if entry['source'].endswith('.pdf'):
        entry['files'] = [local_file(url)]
        continue
    raw = fetch(url)
    try:
        source = raw.decode('utf-8')
    except UnicodeDecodeError:
        source = raw.decode('cp1251')
    for index, video in enumerate(dict.fromkeys(re.findall(r'youtube\.com/embed/([a-zA-Z0-9_-]{11})', source))):
        entry['videos'].append({'id': video, 'title': known_videos.get(video, 'Відеомолитва · ' + entry.get('author', entry['title']) + ' · ' + str(index + 1))})
    cleaned = subprocess.run([PHP, str(ROOT / 'scripts/clean-ritual-page.php')],
                             input=json.dumps({'html': source, 'title': entry['title'], 'source': url}, ensure_ascii=False),
                             text=True, encoding='utf-8', capture_output=True, check=True).stdout
    parser = LocalHTML(url, entry['title'])
    parser.feed(cleaned)
    local = ''.join(parser.parts)
    raw_blocks = json.loads(subprocess.run([PHP, str(ROOT / 'scripts/extract-prayer-blocks.php')], input=local,
                                          text=True, encoding='utf-8', capture_output=True, check=True).stdout)
    original_text = ''.join(b.get('title') or b.get('html') or ''.join(b.get('lines', [])) for b in raw_blocks)
    original_text = re.sub(r'\s+', '', html.unescape(re.sub('<[^>]+>', '', original_text)))
    entry['source_text_fingerprint'] = hashlib.sha256(original_text.encode()).hexdigest()
    (CACHE / (slug + '-blocks.json')).write_text(json.dumps(raw_blocks, ensure_ascii=False, indent=2), encoding='utf-8')
    blocks = []
    research = entry['category'] == 'research'
    def add(kind, fragment):
        if text(fragment) or '<img' in fragment:
            blocks.append({'type': kind, 'html': fragment.strip()})
    for original in raw_blocks:
        if original['type'] == 'heading':
            if original['title'].strip():
                blocks.append({'type': 'heading', 'title': original['title'].strip()})
            continue
        if original['type'] == 'prose':
            add('prose', original['html'])
            continue
        stanza = []
        def flush():
            if stanza:
                add('prose' if research else 'prayer', '<br>'.join(stanza))
                stanza.clear()
        for line in original['lines']:
            t = text(line)
            bold = re.fullmatch(r'\s*(?:<(?:strong|em)>\s*)+(.+?)(?:\s*</(?:strong|em)>)+\s*', line, re.S)
            if not t:
                flush()
                if '<img' in line:
                    add('prose', line)
            elif bold and '<strong>' in line and len(t) < 140 and not t.startswith(('(', '-', '–')):
                flush()
                if re.search(r'\d{4}|Родомисл,|Малюнок|мал\.|Дивись також|Відео|Видиво', t):
                    add('prose', line)
                else:
                    blocks.append({'type': 'heading', 'title': t})
            elif not research and (t.startswith(('Увага!', 'Взяти нову ляльку', '- тричі', 'Складено для')) or (t.startswith('(') and t.endswith(')')) or (bold and len(t) >= 140)):
                flush()
                add('prose' if t.startswith('Увага!') else ('note' if t.startswith(('(варіант', '(молитва', '(камінь', 'Складено для')) else 'direction'), line)
            else:
                stanza.append(line)
        flush()
    historical_spells = False
    for index, block in enumerate(blocks):
        if slug == 'ukrainski-zamovliannia' and block.get('title') == 'Марина Новикова':
            historical_spells = not historical_spells
        if slug == 'ukrainski-zamovliannia' and block.get('title') == 'Коментар':
            historical_spells = False
        if historical_spells and block['type'] == 'prose':
            block['type'] = 'spell'
        if block['type'] == 'heading' and (block['title'].startswith(('м. ', 'Дивись також', 'Відеомолитви', 'Текст взятий', 'З повагою')) or block['title'] in [entry.get('author'), 'Яна (Ясна) Яковенко', 'жриця Мароша Лютиця', '(Ольга Плечун)']):
            block['html'] = html.escape(block.pop('title'))
            block['type'] = 'prose'
        override = editorial.get(slug, {}).get(str(index))
        if override:
            block['type'] = override
        block['id'] = 'reading-' + str(index + 1)
    entry['toc'] = [{'anchor': b['id'], 'title': b['title']} for b in blocks if b['type'] == 'heading']
    entry['page'] = 'pages/' + slug + '.json'
    (CONTENT / entry['page']).write_text(json.dumps(blocks, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    for file_url, file in files.items():
        if file['format'] == 'mp3' and file_url in [absolute(h, url) for h in re.findall(r'href="([^"]+)"', cleaned)]:
            entry['audio'].append({**file, 'title': 'Хай піде дощ!' if 'doshch' in file_url else 'До бою!'})
        if file['format'] == 'djvu' and slug == 'ukrainski-zamovliannia':
            entry['files'].append(file)
    print(slug, len(blocks), 'blocks', len(entry['toc']), 'headings')
catalogue['imported_at'] = '2026-11-09'
catalogue['unavailable_source_images'] = sorted(set(missing_images))
(CONTENT / 'catalogue.json').write_text(json.dumps(catalogue, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print('Complete:', len(catalogue['entries']), 'entries,', len(files), 'files,', len(images), 'images')
