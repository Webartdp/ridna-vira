"""Import the approved rituals catalogue. Requires PHP and Pillow; no runtime network access.

Run from the repository: python scripts/import-svit-rituals.py
Set PHP_BINARY if php is not on PATH. Source downloads are cached outside the repo.
"""
import hashlib
import html
from html.parser import HTMLParser
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
CONTENT = ROOT / 'resources/content/rituals'
ASSETS = ROOT / 'public/assets/rituals'
CACHE = ROOT.parent / 'ritual-sources'
CACHE.mkdir(exist_ok=True)
BASE = 'https://www.svit.in.ua/'
catalogue = json.loads((CONTENT / 'catalogue.json').read_text(encoding='utf-8'))


def absolute(url, base=BASE):
    result = urllib.parse.urljoin(base, html.unescape(url))
    parsed = urllib.parse.urlsplit(result)
    if parsed.hostname in ('svit.in.ua', 'www.svit.in.ua'):
        result = urllib.parse.urlunsplit(('https', 'www.svit.in.ua', parsed.path, parsed.query, parsed.fragment))
    return result


def fetch(url):
    url = absolute(url)
    cache = CACHE / hashlib.sha256(url.encode()).hexdigest()
    if not cache.exists():
        request = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(request, timeout=60) as response:
            data = response.read()
        cache.write_bytes(data)
    return cache.read_bytes()


def document(url):
    data = fetch(url)
    extension = Path(urllib.parse.urlsplit(url).path).suffix.lower()
    if extension == '.pdf' and not data.startswith(b'%PDF'):
        raise ValueError('Not a PDF: ' + url)
    if extension == '.docx' and not data.startswith(b'PK'):
        raise ValueError('Not a DOCX: ' + url)
    name = hashlib.sha256(url.encode()).hexdigest()[:10] + '-' + Path(urllib.parse.urlsplit(url).path).name
    destination = ASSETS / 'documents' / name
    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_bytes(data)
    return {'path': 'documents/' + name, 'format': extension[1:], 'bytes': len(data)}


routes = {}
for slug, entry in catalogue['entries'].items():
    routes.setdefault(absolute(entry['source']), '/ridna-vira/obriady/' + slug)
documents = {}
images = {}


def image(url):
    if url in images:
        return images[url]
    try:
        data = fetch(url)
    except urllib.error.HTTPError:
        if '/obr/obr/' not in url:
            raise
        data = fetch(url.replace('/obr/obr/', '/obr/'))
    original = ImageOps.exif_transpose(Image.open(io.BytesIO(data))).convert('RGB')
    name = hashlib.sha256(url.encode()).hexdigest()[:12]
    destination = ASSETS / 'images'
    destination.mkdir(parents=True, exist_ok=True)
    variants = []
    for width in sorted(set([min(480, original.width), min(960, original.width)])):
        resized = original.copy()
        resized.thumbnail((width, 2000))
        filename = f'{name}-{resized.width}.webp'
        resized.save(destination / filename, 'WEBP', quality=84, method=6)
        variants.append((resized.width, resized.height, '/assets/rituals/images/' + filename))
    width, height, src = variants[-1]
    result = {'src': src, 'width': str(width), 'height': str(height), 'loading': 'lazy', 'decoding': 'async',
              'srcset': ', '.join(f'{src} {w}w' for w, h, src in variants),
              'sizes': '(min-width: 900px) 760px, calc(100vw - 64px)'}
    images[url] = result
    return result


class LocalContent(HTMLParser):
    def __init__(self, source, title):
        super().__init__(convert_charrefs=False)
        self.source = source
        self.title = title
        self.parts = []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'a':
            url = absolute(attrs.get('href', ''), self.source)
            parsed = urllib.parse.urlsplit(url)
            key = urllib.parse.urlunsplit((parsed.scheme, parsed.netloc, parsed.path, parsed.query, ''))
            if key in routes:
                attrs['href'] = routes[key] + ('#' + parsed.fragment if parsed.fragment else '')
            elif key in documents:
                attrs['href'] = '/assets/rituals/' + documents[key]['path']
            else:
                attrs['href'] = url
                if parsed.scheme in ('http', 'https'):
                    attrs.update(target='_blank', rel='noopener noreferrer')
        elif tag == 'img':
            url = absolute(attrs['src'], self.source)
            attrs = {**image(url), 'alt': attrs.get('alt') or self.title}
        self.parts.append('<' + tag + ''.join(f' {k}="{html.escape(str(v), quote=True)}"' for k, v in attrs.items() if v is not None) + '>')

    def handle_endtag(self, tag):
        self.parts.append('</' + tag + '>')

    def handle_data(self, data):
        self.parts.append(re.sub(r'\s+', ' ', data))

    def handle_entityref(self, name):
        self.parts.append('&' + name + ';')

    def handle_charref(self, name):
        self.parts.append('&#' + name + ';')


# Archive primary documents linked by the catalogue and the four main ritual pages.
for entry in catalogue['entries'].values():
    if entry['source'].endswith('.pdf'):
        url = absolute(entry['source'])
        documents.setdefault(url, document(url))
for stem in ['bogoslav', 'posviata', 'vinchannia', 'pohovannia']:
    for extension in ['pdf', 'docx']:
        url = absolute(f'kny/pashnyk/pashnyk_{stem}.{extension}')
        if url not in documents:
            documents[url] = document(url)

pages = CONTENT / 'pages'
pages.mkdir(exist_ok=True)
for slug, entry in catalogue['entries'].items():
    url = absolute(entry['source'])
    if entry['source'].endswith('.pdf'):
        entry['files'] = [documents[url]]
        print(slug, documents[url]['bytes'], 'bytes')
        continue
    data = fetch(url)
    try:
        source = data.decode('utf-8')
    except UnicodeDecodeError:
        source = data.decode('cp1251')
    result = subprocess.run([os.environ.get('PHP_BINARY', 'php'), str(ROOT / 'scripts/clean-ritual-page.php')],
                            input=json.dumps({'html': source, 'title': entry['title'], 'source': url}, ensure_ascii=False),
                            text=True, encoding='utf-8', capture_output=True, check=True)
    parser = LocalContent(url, entry['title'])
    parser.feed(result.stdout)
    content = ''.join(parser.parts).strip()
    toc = []
    def heading(match):
        anchor = 'section-' + str(len(toc) + 1)
        title = html.unescape(re.sub('<[^>]+>', '', match[2])).strip()
        if not title:
            return match[2] if '<img' in match[2] else ''
        toc.append({'anchor': anchor, 'title': title})
        return '<' + match[1] + ' id="' + anchor + '">' + match[2] + '</' + match[1] + '>'
    content = re.sub(r'<(h[234])>(.*?)</\1>', heading, content, flags=re.S)
    entry['toc'] = toc
    if len(re.sub('<[^>]+>', '', content)) < 500:
        raise ValueError('Incomplete text: ' + slug)
    content = re.sub(r'(</(?:p|h[234]|blockquote|ul|ol)>)', r'\1\n', content)
    (pages / (slug + '.html')).write_text(content.rstrip() + '\n', encoding='utf-8')
    entry['page'] = 'pages/' + slug + '.html'
    primary = {
        'bohoslavlennia': 'bogoslav', 'posviachennia': 'posviata',
        'vinchannia': 'vinchannia', 'pokhovannia': 'pohovannia',
        'kupalo': 'kupalo', 'perun': 'perun',
    }.get(slug)
    entry['files'] = [file for link, file in documents.items()
                      if primary and Path(urllib.parse.urlsplit(link).path).stem == 'pashnyk_' + primary]
    print(slug, len(content), 'characters')

catalogue['imported_at'] = '2026-11-09'
(CONTENT / 'catalogue.json').write_text(json.dumps(catalogue, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print('Complete:', len(catalogue['entries']), 'entries,', len(documents), 'documents,', len(images), 'images')
