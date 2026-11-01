import hashlib
import json
import sys
import zipfile
from pathlib import Path
from xml.etree import ElementTree as ET

source, destination = map(Path, sys.argv[1:3])
ns = {'w': 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}
with zipfile.ZipFile(source) as archive:
    body = ET.fromstring(archive.read('word/document.xml')).find('w:body', ns)

def text(element):
    return ''.join(element.itertext()) if element.tag.endswith('}t') else ''.join(t.text or '' for t in element.findall('.//w:t', ns))

def rows(element):
    return [[text(cell) for cell in row.findall('w:tc', ns)] for row in element.findall('w:tr', ns)]

blocks = list(body)
def between(start, end):
    first = next(i for i, b in enumerate(blocks) if text(b).strip() == start)
    last = next(i for i, b in enumerate(blocks[first + 1:], first + 1) if text(b).strip() == end)
    return [text(b) for b in blocks[first + 1:last] if b.tag.endswith('}p') and text(b).strip()]
def criteria(index):
    return [{'code': f'item_{i:02}', 'title': row[1], 'indicator': row[2]} for i, row in enumerate(rows(blocks[index - 1])[1:], 1) if len(row) >= 3 and row[0].strip().isdigit()]

data = {
    'source': source.name,
    'sha256': hashlib.sha256(source.read_bytes()).hexdigest(),
    'revision': '2026-11-01',
    'self_assessment': criteria(548),
    'preceptor_assessment': criteria(602),
    'internal_assessment': criteria(621),
    'general_rubric': rows(blocks[641]),
    'product_rubric': rows(blocks[643]),
    'vision_mission': between('VISI DAN MISI', 'TATA TERTIB PKPA'),
    'rules': between('TATA TERTIB PKPA', 'IDENTITAS MAHASISWA'),
    'integrity_pact': [text(b) for b in blocks[111:116] if text(b).strip()],
    'reference_tables': {str(i): rows(blocks[i - 1]) for i in [182, 207, 212, 225, 230, 236, 248, 258, 265, 270, 279, 287, 297, 301, 312, 337, 343, 349, 354, 359, 364, 369, 380, 404, 409, 414, 419, 424, 444, 454, 456, 462, 464, 475, 487, 524, 568, 669]},
}
assert [len(data[k]) for k in ['self_assessment', 'preceptor_assessment', 'internal_assessment']] == [20, 22, 13]
destination.parent.mkdir(parents=True, exist_ok=True)
destination.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(f'Extracted 20 self-assessment, 22 preceptor, 13 internal criteria; SHA256 {data["sha256"]}')
