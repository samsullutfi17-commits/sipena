import sys
import json
import os
import base64
from weasyprint import HTML

# Encode logo as base64 so it embeds cleanly in the PDF
_LOGO_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'uploads', 'logo_institusi.png')
def _logo_data_uri():
    try:
        with open(_LOGO_PATH, 'rb') as f:
            return 'data:image/png;base64,' + base64.b64encode(f.read()).decode()
    except Exception:
        return ''

KOP_CSS = """
  /* ── Kop Dokumen ──────────────────────────────── */
  .kop-wrap        { font-family: Arial, sans-serif; color: #000; margin-bottom: 10px; }
  .kop-line-top    { border-top: 3px solid #000; margin-bottom: 6px; }
  .kop-content     { display: flex; align-items: center; gap: 14px; padding: 2px 0 6px; }
  .kop-logo        { width: 82px; height: 82px; object-fit: contain; flex-shrink: 0; }
  .kop-text        { flex: 1; text-align: center; }
  .kop-name        { font-size: 20pt; font-weight: 900; letter-spacing: .5px;
                     text-transform: uppercase; line-height: 1.15; }
  .kop-akreditasi  { font-size: 10pt; font-weight: 700; margin-top: 3px; }
  .kop-addr        { font-size: 8.5pt; margin-top: 2px; line-height: 1.4; }
  .kop-line-thick  { border-top: 3px solid #000; margin-top: 6px; }
  .kop-line-thin   { border-top: 1px solid #000; margin-top: 2px; }
"""

def kop_html(logo_uri):
    return f"""
  <div class="kop-wrap">
    <div class="kop-line-top"></div>
    <div class="kop-content">
      <img class="kop-logo" src="{logo_uri}" alt="Logo">
      <div class="kop-text">
        <div class="kop-name">UNIVERSITAS HAMZANWADI</div>
        <div class="kop-akreditasi">AKREDITASI BAN-PT NOMOR : 1702/SK/BAN-PT/Ak.Ppj/PT/X/2022</div>
        <div class="kop-addr">Sekretariat : Jalan TGKH. Muhammad Zainuddin Abdul Madjid No. 132 Pancor (83611) Selong-Lombok Timur-NTB</div>
        <div class="kop-addr">Telp: (0376) 21394 22953 &nbsp;&nbsp; Fax. (0376) 22954 &nbsp;&nbsp; Email: universitas@hamzanwadi.ac.id</div>
        <div class="kop-addr">Website : www.hamzanwadi.ac.id</div>
      </div>
    </div>
    <div class="kop-line-thick"></div>
    <div class="kop-line-thin"></div>
  </div>
"""

def export_to_pdf(data, output_path):
    nilai_lulus = data.get('nilai_lulus', 60)
    logo_uri    = _logo_data_uri()

    rows_html = ''
    for i, h in enumerate(data['hasil'], 1):
        nilai       = int(h.get('nilai_total') or 0)
        nim         = h.get('nim') or '-'
        nama        = h.get('nama_lengkap') or '-'
        kelas       = h.get('nama_kelas') or '-'
        lulus       = nilai >= nilai_lulus
        badge_class = 'badge-success' if lulus else 'badge-danger'
        status_text = 'Lulus' if lulus else 'Tidak Lulus'
        rows_html += f"""
            <tr>
                <td>{i}</td>
                <td>{nim}</td>
                <td>{nama}</td>
                <td>{kelas}</td>
                <td style="text-align:center;font-weight:600;">{nilai}</td>
                <td><span class="badge {badge_class}">{status_text}</span></td>
            </tr>"""

    html_content = f"""<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body {{ font-family: Arial, sans-serif; padding: 24px; color: #222; font-size: 13px; }}

{KOP_CSS}

  /* ── Rekap header ── */
  .rekap-title {{ margin: 14px 0 4px; text-align: center; font-size: 14pt; font-weight: 700; color: #1b4d3e; }}
  .rekap-sub   {{ text-align: center; color: #555; margin-bottom: 16px; font-size: 12px; }}

  /* ── Stats ── */
  .stats      {{ display: flex; gap: 14px; margin-bottom: 20px; }}
  .stat-item  {{
      flex: 1; background: #f0faf5; padding: 12px; border-radius: 8px;
      text-align: center; border: 1px solid #c3e6d8;
  }}
  .stat-value {{ font-size: 24px; font-weight: bold; color: #1b4d3e; }}
  .stat-label {{ font-size: 10px; color: #555; margin-top: 4px; }}

  /* ── Table ── */
  table  {{ width: 100%; border-collapse: collapse; margin-top: 8px; }}
  th     {{ background: #1b4d3e; color: #fff; padding: 9px 10px; text-align: left; font-size: 11px; }}
  td     {{ padding: 7px 10px; border-bottom: 1px solid #e0e0e0; font-size: 11px; }}
  tr:nth-child(even) td {{ background: #f9f9f9; }}
  .badge {{ padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }}
  .badge-success {{ background: #d4edda; color: #155724; }}
  .badge-danger  {{ background: #f8d7da; color: #721c24; }}

  /* ── Footer ── */
  .footer {{ margin-top: 18px; font-size: 10px; color: #888; text-align: right; }}
</style>
</head>
<body>

  {kop_html(logo_uri)}

  <div class="rekap-title">Rekap Hasil Ujian</div>
  <div class="rekap-sub">
    <strong>{data['judul_ujian']}</strong> &nbsp;|&nbsp; {data['nama_mk']}
  </div>

  <div class="stats">
    <div class="stat-item">
      <div class="stat-value">{data['stats']['total_peserta']}</div>
      <div class="stat-label">Total Peserta</div>
    </div>
    <div class="stat-item">
      <div class="stat-value">{data['stats']['rata_rata']}</div>
      <div class="stat-label">Rata-rata Nilai</div>
    </div>
    <div class="stat-item">
      <div class="stat-value">{data['stats']['lulus']}</div>
      <div class="stat-label">Lulus (≥ {nilai_lulus})</div>
    </div>
    <div class="stat-item">
      <div class="stat-value">{data['stats']['total_peserta'] - data['stats']['lulus']}</div>
      <div class="stat-label">Tidak Lulus</div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:36px">No</th>
        <th>NPM / NIM</th>
        <th>Nama Lengkap</th>
        <th>Kelas</th>
        <th style="text-align:center;width:56px">Nilai</th>
        <th style="width:90px">Status</th>
      </tr>
    </thead>
    <tbody>
      {rows_html}
    </tbody>
  </table>

  <div class="footer">Dicetak oleh sistem SIPENA &nbsp;&bull;&nbsp; Universitas Hamzanwadi</div>
</body>
</html>"""

    HTML(string=html_content).write_pdf(output_path)


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: export_pdf.py <json_file> <output.pdf>", file=sys.stderr)
        sys.exit(1)

    json_file   = sys.argv[1]
    output_path = sys.argv[2]

    with open(json_file, 'r', encoding='utf-8') as f:
        data = json.load(f)

    export_to_pdf(data, output_path)
