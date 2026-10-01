import sys
import json
import os
import random
from docx import Document
from docx.shared import Pt, Inches, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

# Question type display order and labels
TYPE_ORDER  = ['pg', 'multiple', 'tf', 'short', 'matching', 'ordering', 'esai', 'studi_kasus']
TYPE_LABELS = {
    'pg':          'Pilihan Ganda',
    'multiple':    'Pilihan Ganda Kompleks',
    'tf':          'Benar/Salah',
    'short':       'Jawaban Singkat',
    'matching':    'Menjodohkan',
    'ordering':    'Penyusunan Urutan',
    'esai':        'Esai',
    'studi_kasus': 'Studi Kasus',
}
SECTION_LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'

# A4 content width in twips:
#   A4 = 11906 twips, left=3cm≈1701, right=2cm≈1134 → content≈9071 twips
CONTENT_W   = 9071   # total content width
LOGO_COL_W  = 1620   # ~1.125 in
TEXT_COL_W  = CONTENT_W - LOGO_COL_W   # ~7451 twips (~5.17 in)


# ─── XML / table helpers ─────────────────────────────────────────────────────

def _set_tbl_fixed(tbl, total_twips):
    """Force table to fixed layout and set total width."""
    tblPr = tbl.tblPr
    if tblPr is None:
        tblPr = OxmlElement('w:tblPr')
        tbl.insert(0, tblPr)
    # tblLayout = fixed
    lay = OxmlElement('w:tblLayout')
    lay.set(qn('w:type'), 'fixed')
    tblPr.append(lay)
    # tblW
    tblW = OxmlElement('w:tblW')
    tblW.set(qn('w:w'), str(total_twips))
    tblW.set(qn('w:type'), 'dxa')
    tblPr.append(tblW)


def _set_tbl_grid(tbl, col_widths):
    """Insert w:tblGrid with explicit column widths."""
    grid = OxmlElement('w:tblGrid')
    for w in col_widths:
        gc = OxmlElement('w:gridCol')
        gc.set(qn('w:w'), str(w))
        grid.append(gc)
    # insert after tblPr (index 1)
    tbl.insert(1, grid)


def _set_cell_width(cell, twips):
    tc   = cell._tc
    tcPr = tc.get_or_add_tcPr()
    for old in tcPr.findall(qn('w:tcW')):
        tcPr.remove(old)
    tcW = OxmlElement('w:tcW')
    tcW.set(qn('w:w'), str(twips))
    tcW.set(qn('w:type'), 'dxa')
    tcPr.insert(0, tcW)


def remove_table_borders(table):
    tbl   = table._tbl
    tblPr = tbl.tblPr
    if tblPr is None:
        tblPr = OxmlElement('w:tblPr')
        tbl.insert(0, tblPr)
    tblBorders = OxmlElement('w:tblBorders')
    for name in ['top', 'left', 'bottom', 'right', 'insideH', 'insideV']:
        b = OxmlElement(f'w:{name}')
        b.set(qn('w:val'), 'none')
        b.set(qn('w:sz'), '0')
        b.set(qn('w:space'), '0')
        b.set(qn('w:color'), 'auto')
        tblBorders.append(b)
    tblPr.append(tblBorders)


def set_cell_vertical_align(cell, align='center'):
    tc   = cell._tc
    tcPr = tc.get_or_add_tcPr()
    v    = OxmlElement('w:vAlign')
    v.set(qn('w:val'), align)
    tcPr.append(v)


def add_double_separator(doc):
    p    = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after  = Pt(2)
    pPr  = p._p.get_or_add_pPr()
    pBdr = OxmlElement('w:pBdr')
    bot  = OxmlElement('w:bottom')
    bot.set(qn('w:val'), 'double')
    bot.set(qn('w:sz'), '6')
    bot.set(qn('w:space'), '1')
    bot.set(qn('w:color'), '000000')
    pBdr.append(bot)
    pPr.append(pBdr)


# ─── Kop Surat ───────────────────────────────────────────────────────────────

def fill_institution_cell(cell, nama_institusi, alamat, kontak):
    """Right column: institution name (large, bold, left) + address + contact."""
    if nama_institusi:
        p = cell.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.space_after  = Pt(2)
        run = p.add_run(nama_institusi.upper())
        run.bold      = True
        run.font.size = Pt(20)
    if alamat:
        p = cell.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.space_after  = Pt(1)
        run = p.add_run(alamat)
        run.font.size = Pt(10)
    if kontak:
        p = cell.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.space_after  = Pt(0)
        run = p.add_run(kontak)
        run.font.size = Pt(10)


def add_institution_centered(doc, nama_institusi, alamat, kontak):
    """Fallback (no logo): centred block."""
    if nama_institusi:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.space_after  = Pt(2)
        run = p.add_run(nama_institusi.upper())
        run.bold      = True
        run.font.size = Pt(20)
    for txt in [alamat, kontak]:
        if txt:
            p = doc.add_paragraph()
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p.paragraph_format.space_before = Pt(0)
            p.paragraph_format.space_after  = Pt(2)
            run = p.add_run(txt)
            run.font.size = Pt(10)


def add_identity_table(doc, exam_info):
    rows_data = [
        ('Hari/Tanggal',   exam_info.get('hari_tanggal', '-'),
         'Organisasi/Program', exam_info.get('nama_prodi', '-')),
        ('Waktu',          exam_info.get('waktu', '-'),
         'Materi',         exam_info.get('nama_mk', '-')),
        ('Pengampu',       exam_info.get('pengampu', '-'),
         'Periode/Bobot',  exam_info.get('sks', '-')),
    ]
    table = doc.add_table(rows=3, cols=4)
    table.style = 'Table Grid'
    remove_table_borders(table)
    for row_idx, (l1, v1, l2, v2) in enumerate(rows_data):
        row = table.rows[row_idx]
        for ci, txt in enumerate([l1, f': {v1}', l2, f': {v2}']):
            cell = row.cells[ci]
            p    = cell.paragraphs[0]
            p.paragraph_format.space_before = Pt(2)
            p.paragraph_format.space_after  = Pt(2)
            run  = p.add_run(txt)
            run.font.size = Pt(10)


def add_kop_surat(doc, settings, exam_info):
    nama_institusi = settings.get('nama_institusi', '')
    alamat         = settings.get('alamat', '')
    kontak         = settings.get('kontak', '')
    logo_path      = settings.get('logo_path', '')
    has_logo       = bool(logo_path and os.path.exists(logo_path))

    if has_logo:
        tbl = doc.add_table(rows=1, cols=2)
        remove_table_borders(tbl)

        # Fixed layout with explicit column widths
        _set_tbl_fixed(tbl._tbl, CONTENT_W)
        _set_tbl_grid(tbl._tbl, [LOGO_COL_W, TEXT_COL_W])

        # Logo cell
        cell_logo = tbl.cell(0, 0)
        _set_cell_width(cell_logo, LOGO_COL_W)
        set_cell_vertical_align(cell_logo, 'center')
        p   = cell_logo.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run = p.add_run()
        try:
            run.add_picture(logo_path, width=Inches(1.05))
        except Exception:
            pass

        # Text cell
        cell_text = tbl.cell(0, 1)
        _set_cell_width(cell_text, TEXT_COL_W)
        set_cell_vertical_align(cell_text, 'center')
        fill_institution_cell(cell_text, nama_institusi, alamat, kontak)
    else:
        add_institution_centered(doc, nama_institusi, alamat, kontak)

    # Double separator
    add_double_separator(doc)

    # Exam title (centred, bold)
    judul_header   = settings.get('judul_header', '').strip()
    tahun_akademik = settings.get('tahun_akademik', '').strip()
    if judul_header:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_before = Pt(6)
        p.paragraph_format.space_after  = Pt(0)
        run = p.add_run(judul_header.upper())
        run.bold      = True
        run.font.size = Pt(12)
    if tahun_akademik:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.space_after  = Pt(6)
        run = p.add_run(f'TAHUN AKADEMIK {tahun_akademik}')
        run.bold      = True
        run.font.size = Pt(12)

    add_identity_table(doc, exam_info)


# ─── Petunjuk / Pengumpulan sections ─────────────────────────────────────────

def write_numbered_section(doc, roman, heading, body_text):
    """
    Render one numbered section like:
      I.  Petunjuk Umum:
          a.  Baca soal dengan seksama.
          b.  ...
    body_text is newline-separated bullet points.
    """
    if not body_text or not body_text.strip():
        return

    # Heading line:  "I.   Petunjuk Umum:"
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(4)
    p.paragraph_format.space_after  = Pt(2)
    run_num = p.add_run(f'{roman}.\t')
    run_num.bold      = True
    run_num.font.size = Pt(11)
    run_hd = p.add_run(heading)
    run_hd.bold      = True
    run_hd.font.size = Pt(11)

    # Bullet lines
    letters = 'abcdefghijklmnopqrstuvwxyz'
    lines   = [ln.strip() for ln in body_text.strip().splitlines() if ln.strip()]
    for i, line in enumerate(lines):
        ltr = letters[i] if i < len(letters) else str(i + 1)
        p   = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.space_after  = Pt(0)
        p.paragraph_format.left_indent  = Inches(0.35)
        run = p.add_run(f'{ltr}.\t{line}')
        run.italic    = True
        run.font.size = Pt(10)


# ─── Kisi-Kisi Table ──────────────────────────────────────────────────────────

def write_kisi_kisi_table(doc, soal_list):
    """Write a kisi-kisi table before petunjuk/instrumen soal sections."""
    if not soal_list:
        return

    LEVEL_LABELS = {
        'C1': 'C1 – Mengingat', 'C2': 'C2 – Memahami',
        'C3': 'C3 – Menerapkan', 'C4': 'C4 – Menganalisis',
        'C5': 'C5 – Mengevaluasi', 'C6': 'C6 – Mencipta',
    }
    KESULITAN_LABELS = {'mudah': 'Mudah', 'sedang': 'Sedang', 'sulit': 'Sulit'}

    # Section heading
    p_head = doc.add_paragraph()
    p_head.paragraph_format.space_before = Pt(4)
    p_head.paragraph_format.space_after  = Pt(6)
    rh = p_head.add_run('KISI-KISI SOAL')
    rh.bold = True
    rh.font.size = Pt(11)

    headers = ['No', 'CPMK / Tujuan Pembelajaran', 'Indikator', 'Level Kognitif',
               'Tingkat Kesulitan', 'Jenis Soal', 'Bobot']
    col_widths = [450, 1900, 1900, 1350, 1200, 1250, 700]

    table = doc.add_table(rows=1 + len(soal_list) + 1, cols=len(headers))
    table.style = 'Table Grid'
    _set_tbl_fixed(table._tbl, sum(col_widths))
    _set_tbl_grid(table._tbl, col_widths)

    # Header row
    hdr = table.rows[0]
    for i, (h, w) in enumerate(zip(headers, col_widths)):
        cell = hdr.cells[i]
        _set_cell_width(cell, w)
        # Dark green fill
        tcPr = cell._tc.get_or_add_tcPr()
        shd = OxmlElement('w:shd')
        shd.set(qn('w:val'), 'clear')
        shd.set(qn('w:color'), 'auto')
        shd.set(qn('w:fill'), '1F5C3E')
        tcPr.append(shd)
        cp = cell.paragraphs[0]
        cp.alignment = WD_ALIGN_PARAGRAPH.CENTER
        cp.paragraph_format.space_before = Pt(2)
        cp.paragraph_format.space_after  = Pt(2)
        r = cp.add_run(h)
        r.bold = True
        r.font.size = Pt(9)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)

    # Data rows
    total_poin = 0
    for i, soal in enumerate(soal_list):
        row = table.rows[i + 1]
        level_raw  = soal.get('level_kognitif') or ''
        level_text = ', '.join(
            LEVEL_LABELS.get(lv.strip(), lv.strip())
            for lv in level_raw.split(',') if lv.strip()
        ) or '—'
        ks_raw = (soal.get('tingkat_kesulitan') or 'sedang').lower()
        poin   = soal.get('poin', 0) or 0
        total_poin += int(poin)
        values = [
            str(i + 1),
            soal.get('cpmk') or '—',
            soal.get('indikator') or '—',
            level_text,
            KESULITAN_LABELS.get(ks_raw, ks_raw.capitalize()),
            TYPE_LABELS.get(soal.get('jenis_soal', ''), soal.get('jenis_soal', '—')),
            str(poin),
        ]
        aligns = [WD_ALIGN_PARAGRAPH.CENTER, WD_ALIGN_PARAGRAPH.LEFT,
                  WD_ALIGN_PARAGRAPH.LEFT,   WD_ALIGN_PARAGRAPH.LEFT,
                  WD_ALIGN_PARAGRAPH.CENTER,  WD_ALIGN_PARAGRAPH.LEFT,
                  WD_ALIGN_PARAGRAPH.CENTER]
        fill = 'F7FAF8' if i % 2 == 1 else 'FFFFFF'
        for j, (val, align, w) in enumerate(zip(values, aligns, col_widths)):
            cell = row.cells[j]
            _set_cell_width(cell, w)
            tcPr = cell._tc.get_or_add_tcPr()
            shd  = OxmlElement('w:shd')
            shd.set(qn('w:val'), 'clear')
            shd.set(qn('w:color'), 'auto')
            shd.set(qn('w:fill'), fill)
            tcPr.append(shd)
            cp = cell.paragraphs[0]
            cp.alignment = align
            cp.paragraph_format.space_before = Pt(1)
            cp.paragraph_format.space_after  = Pt(1)
            r = cp.add_run(val)
            r.font.size = Pt(9)

    # Footer total row
    foot = table.rows[-1]
    _set_cell_width(foot.cells[0], col_widths[0])
    label_cell = foot.cells[0]
    for k in range(1, len(headers) - 1):
        label_cell = label_cell.merge(foot.cells[k])
    label_cell.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.RIGHT
    label_cell.paragraphs[0].paragraph_format.space_before = Pt(2)
    label_cell.paragraphs[0].paragraph_format.space_after  = Pt(2)
    rr = label_cell.paragraphs[0].add_run('Total Bobot')
    rr.bold = True
    rr.font.size = Pt(9)
    tot_cell = foot.cells[-1]
    _set_cell_width(tot_cell, col_widths[-1])
    tot_cell.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER
    tot_cell.paragraphs[0].paragraph_format.space_before = Pt(2)
    tot_cell.paragraphs[0].paragraph_format.space_after  = Pt(2)
    rt = tot_cell.paragraphs[0].add_run(str(total_poin))
    rt.bold = True
    rt.font.size = Pt(9)

    doc.add_paragraph()


# ─── Instrumen Soal ───────────────────────────────────────────────────────────

def group_by_type(soal_list):
    groups, seen = {}, []
    for soal in soal_list:
        t = soal.get('jenis_soal', 'pg')
        if t not in groups:
            groups[t] = []
            seen.append(t)
        groups[t].append(soal)
    ordered = sorted(seen, key=lambda t: TYPE_ORDER.index(t) if t in TYPE_ORDER else 99)
    return ordered, groups


def write_instrumen_soal(doc, ordered_types, groups, section_number):
    """Write all questions grouped by type. Returns the section number used."""
    roman = _to_roman(section_number)
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(4)
    p.paragraph_format.space_after  = Pt(2)
    run_num = p.add_run(f'{roman}.\t')
    run_num.bold      = True
    run_num.font.size = Pt(11)
    run_hd = p.add_run('Instrumen Soal')
    run_hd.bold      = True
    run_hd.font.size = Pt(11)

    global_num = 0
    for sec_idx, t in enumerate(ordered_types):
        soal_group = groups[t]
        label      = TYPE_LABELS.get(t, t)
        sec_letter = SECTION_LETTERS[sec_idx] if sec_idx < len(SECTION_LETTERS) else str(sec_idx + 1)

        # "Bagian A: Pilihan Ganda (10 Soal)"
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(8)
        p.paragraph_format.space_after  = Pt(4)
        p.paragraph_format.left_indent  = Inches(0.35)
        run = p.add_run(f'Bagian {sec_letter}: {label} ({len(soal_group)} Soal)')
        run.bold      = True
        run.font.size = Pt(11)

        for soal in soal_group:
            global_num  += 1
            jenis         = soal.get('jenis_soal', '')
            data_tambahan = soal.get('data_tambahan_parsed') or {}

            # Question line: "1)  Pertanyaan..."
            p = doc.add_paragraph()
            p.paragraph_format.space_before = Pt(4)
            p.paragraph_format.space_after  = Pt(2)
            p.paragraph_format.left_indent  = Inches(0.35)
            rn = p.add_run(f'{global_num})\t')
            rn.bold = True; rn.font.size = Pt(11)
            rq = p.add_run(soal.get('pertanyaan', ''))
            rq.font.size = Pt(11)

            if jenis in ('pg', 'multiple') and soal.get('opsi'):
                for j, opsi in enumerate(soal['opsi']):
                    ltr = chr(65 + j)
                    p   = doc.add_paragraph()
                    p.paragraph_format.space_before = Pt(0)
                    p.paragraph_format.space_after  = Pt(0)
                    p.paragraph_format.left_indent  = Inches(0.6)
                    run = p.add_run(f'{ltr}\t{opsi.get("teks_opsi", "")}')
                    run.font.size = Pt(11)

            elif jenis == 'tf':
                p = doc.add_paragraph()
                p.paragraph_format.left_indent  = Inches(0.6)
                p.paragraph_format.space_before = Pt(0)
                p.paragraph_format.space_after  = Pt(0)
                run = p.add_run('( BENAR  /  SALAH )')
                run.italic = True; run.font.size = Pt(10)

            elif jenis == 'short':
                p = doc.add_paragraph()
                p.paragraph_format.left_indent  = Inches(0.6)
                p.paragraph_format.space_before = Pt(0)
                p.paragraph_format.space_after  = Pt(0)
                run = p.add_run('Jawaban: ___________________________________________')
                run.font.size = Pt(10)

            elif jenis == 'matching':
                pairs = data_tambahan.get('pairs', {})
                if pairs:
                    keys       = list(pairs.keys())
                    vals       = list(pairs.values())
                    shuf_vals  = vals[:]
                    random.shuffle(shuf_vals)
                    p = doc.add_paragraph()
                    p.paragraph_format.left_indent  = Inches(0.6)
                    p.paragraph_format.space_before = Pt(2)
                    run = p.add_run('Kolom A' + ' ' * 20 + 'Kolom B')
                    run.bold = True; run.font.size = Pt(10)
                    for i, (k, sv) in enumerate(zip(keys, shuf_vals)):
                        p = doc.add_paragraph()
                        p.paragraph_format.left_indent  = Inches(0.6)
                        p.paragraph_format.space_before = Pt(0)
                        p.paragraph_format.space_after  = Pt(0)
                        run = p.add_run(f'{i+1}. {k}' + ' ' * 15 + f'{chr(65+i)}. {sv}')
                        run.font.size = Pt(10)

            elif jenis == 'ordering':
                items = list(data_tambahan.get('correct_order', []))
                random.shuffle(items)
                for item in items:
                    p = doc.add_paragraph()
                    p.paragraph_format.left_indent  = Inches(0.6)
                    p.paragraph_format.space_before = Pt(0)
                    p.paragraph_format.space_after  = Pt(0)
                    run = p.add_run(f'[ ]  {item}')
                    run.font.size = Pt(10)


# ─── Kunci Jawaban ────────────────────────────────────────────────────────────

def get_answer_text(soal):
    jenis         = soal.get('jenis_soal', '')
    data_tambahan = soal.get('data_tambahan_parsed') or {}
    opsi          = soal.get('opsi', []) or []
    if jenis in ('pg', 'multiple'):
        correct = [chr(65 + i) for i, o in enumerate(opsi) if o.get('benar')]
        return ', '.join(correct) if correct else '-'
    elif jenis == 'tf':
        return 'BENAR' if data_tambahan.get('jawaban_benar') else 'SALAH'
    elif jenis == 'short':
        return data_tambahan.get('kunci_jawaban', '-')
    elif jenis == 'matching':
        pairs = data_tambahan.get('pairs', {})
        return ' | '.join(f'{k} → {v}' for k, v in pairs.items()) if pairs else '-'
    elif jenis == 'ordering':
        order = data_tambahan.get('correct_order', [])
        return ' → '.join(str(x) for x in order) if order else '-'
    else:
        return '(penilaian subjektif)'


def write_kunci_jawaban(doc, ordered_types, groups):
    # Page break
    p   = doc.add_paragraph()
    run = p.add_run()
    br  = OxmlElement('w:br')
    br.set(qn('w:type'), 'page')
    run._r.append(br)

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(0)
    p.paragraph_format.space_after  = Pt(8)
    run = p.add_run('KUNCI JAWABAN DAN PEMBAHASAN')
    run.bold = True; run.font.size = Pt(13)

    global_num = 0
    for sec_idx, t in enumerate(ordered_types):
        soal_group = groups[t]
        label      = TYPE_LABELS.get(t, t)
        sec_letter = SECTION_LETTERS[sec_idx] if sec_idx < len(SECTION_LETTERS) else str(sec_idx + 1)

        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(8)
        p.paragraph_format.space_after  = Pt(4)
        run = p.add_run(f'Bagian {sec_letter}: {label}')
        run.bold = True; run.font.size = Pt(11)

        for soal in soal_group:
            global_num += 1
            jawaban    = get_answer_text(soal)
            pembahasan = soal.get('pembahasan', '')

            p   = doc.add_paragraph()
            p.paragraph_format.space_before = Pt(4)
            p.paragraph_format.space_after  = Pt(0)
            rn  = p.add_run(f'{global_num}.\t')
            rn.bold = True; rn.font.size = Pt(11)
            rj  = p.add_run(f'Jawaban: {jawaban}')
            rj.font.size = Pt(11)

            if pembahasan:
                p = doc.add_paragraph()
                p.paragraph_format.space_before = Pt(0)
                p.paragraph_format.space_after  = Pt(2)
                p.paragraph_format.left_indent  = Inches(0.35)
                rb  = p.add_run('Pembahasan: ')
                rb.bold = True; rb.font.size = Pt(11)
                rb2 = p.add_run(pembahasan)
                rb2.font.size = Pt(11)


# ─── Roman numeral helper ─────────────────────────────────────────────────────

def _to_roman(n):
    vals = [(10,'X'),(9,'IX'),(5,'V'),(4,'IV'),(1,'I')]
    result = ''
    for v, s in vals:
        while n >= v:
            result += s
            n -= v
    return result


# ─── Main export ──────────────────────────────────────────────────────────────

def export_to_docx(data, output_path):
    doc = Document()
    for section in doc.sections:
        section.top_margin    = Cm(2.5)
        section.bottom_margin = Cm(2.5)
        section.left_margin   = Cm(3)
        section.right_margin  = Cm(2)

    settings  = data.get('settings', {})
    exam_info = data.get('exam_info', {})

    # ── Kop Surat ─────────────────────────────────────────────────────────────
    if settings.get('nama_institusi'):
        add_kop_surat(doc, settings, exam_info)
    else:
        title = doc.add_heading('Bank Soal – ' + data.get('judul_ujian', 'Ujian'), 0)
        title.alignment = WD_ALIGN_PARAGRAPH.CENTER
        doc.add_paragraph(f"Materi: {data.get('nama_mk', '-')}")
        doc.add_paragraph('-' * 50)

    soal_list = data.get('soal', [])
    if not soal_list:
        doc.add_paragraph('(Tidak ada soal)')
        doc.save(output_path)
        return

    ordered_types, groups = group_by_type(soal_list)

    doc.add_paragraph()  # breathing space after identity table

    # ── Kisi-Kisi Soal ────────────────────────────────────────────────────────
    write_kisi_kisi_table(doc, soal_list)

    # ── I. Petunjuk Umum ──────────────────────────────────────────────────────
    section_num = 1
    petunjuk    = settings.get('petunjuk_umum', '').strip()
    pengumpulan = settings.get('pengumpulan', '').strip()

    if petunjuk:
        write_numbered_section(doc, _to_roman(section_num), 'Petunjuk Umum:', petunjuk)
        section_num += 1

    # ── II. Pengumpulan ───────────────────────────────────────────────────────
    if pengumpulan:
        write_numbered_section(doc, _to_roman(section_num), 'Pengumpulan:', pengumpulan)
        section_num += 1

    # ── III. Instrumen Soal ───────────────────────────────────────────────────
    write_instrumen_soal(doc, ordered_types, groups, section_num)

    # ── Kunci Jawaban & Pembahasan (new page) ─────────────────────────────────
    write_kunci_jawaban(doc, ordered_types, groups)

    doc.save(output_path)


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python export_docx.py <json_data> <output_path>")
        sys.exit(1)
    try:
        data = json.loads(sys.argv[1])
        export_to_docx(data, sys.argv[2])
        print(f"Success: {sys.argv[2]}")
    except Exception as e:
        import traceback
        print(f"Error: {str(e)}")
        traceback.print_exc()
        sys.exit(1)
