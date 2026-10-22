/**
 * Generator SVG Diagram Stimulus Akademik untuk Butir Soal Ujian
 * Menghasilkan visual vector SVG mandiri beresolusi tinggi, modern, dan informatif.
 */

export function generateAcademicDiagramSvg({ topic = 'Sistem Informasi', title = '', context = '', type = 'flowchart' }) {
  const displayTitle = title || topic || 'Diagram Alur Konseptual';
  const cleanTitle = displayTitle.replace(/["<>]/g, '').trim().substring(0, 50);
  const cleanContext = (context || topic || 'Analisis butir stimulus instrumen evaluasi').replace(/["<>]/g, '').trim().substring(0, 75);

  // Palet Warna Elegan Akademik
  const colors = {
    bg: '#f8fafc',
    border: '#cbd5e1',
    primary: '#1e3a8a',
    primaryLight: '#eff6ff',
    primaryBorder: '#93c5fd',
    accent: '#0d9488',
    accentLight: '#f0fdfa',
    purple: '#7e22ce',
    purpleLight: '#faf5ff',
    amber: '#b45309',
    amberLight: '#fffbeb',
    textMain: '#0f172a',
    textMuted: '#64748b'
  };

  // Tentukan variasi diagram berdasarkan parameter eksplisit atau kata kunci
  let diagramType = (type || 'auto').toLowerCase();
  if (diagramType === 'flowchart') diagramType = 'process';
  
  if (diagramType === 'auto' || !['process', 'network', 'hierarchy', 'architecture', 'venn'].includes(diagramType)) {
    const lower = (topic + ' ' + title + ' ' + context).toLowerCase();
    diagramType = 'architecture';
    if (lower.includes('alur') || lower.includes('proses') || lower.includes('flow') || lower.includes('siklus') || lower.includes('tahap') || lower.includes('langkah')) {
      diagramType = 'process';
    } else if (lower.includes('jaringan') || lower.includes('topologi') || lower.includes('server') || lower.includes('komunikasi') || lower.includes('node')) {
      diagramType = 'network';
    } else if (lower.includes('hirarki') || lower.includes('organisasi') || lower.includes('struktur') || lower.includes('pohon') || lower.includes('klasifikasi')) {
      diagramType = 'hierarchy';
    } else if (lower.includes('venn') || lower.includes('irisan') || lower.includes('relasi') || lower.includes('perbandingan') || lower.includes('himpunan')) {
      diagramType = 'venn';
    }
  }

  let bodySvg = '';

  if (diagramType === 'process') {
    // 4-Stage Horizontal Process Flow
    const steps = [
      { num: '01', title: 'Identifikasi Masukan', sub: 'Spesifikasi & parameter awal', col: colors.primary, bg: colors.primaryLight },
      { num: '02', title: 'Pemrosesan Inti', sub: 'Eksekusi aturan & validasi', col: colors.accent, bg: colors.accentLight },
      { num: '03', title: 'Analisis & Verifikasi', sub: 'Pengujian integritas mutu', col: colors.purple, bg: colors.purpleLight },
      { num: '04', title: 'Luaran & Evaluasi', sub: 'Capaian & tindak lanjut', col: colors.amber, bg: colors.amberLight }
    ];

    bodySvg = steps.map((s, idx) => {
      const x = 30 + idx * 145;
      const arrowSvg = idx < 3 ? `
        <g transform="translate(${x + 128}, 165)">
          <line x1="0" y1="0" x2="14" y2="0" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round"/>
          <polygon points="12,-4 20,0 12,4" fill="#94a3b8"/>
        </g>
      ` : '';

      return `
        <g>
          <rect x="${x}" y="115" width="128" height="105" rx="10" fill="${s.bg}" stroke="${s.col}" stroke-width="1.8" filter="url(#drop-shadow)"/>
          <circle cx="${x + 22}" cy="138" r="13" fill="${s.col}"/>
          <text x="${x + 22}" y="142" font-size="10" font-weight="bold" fill="#ffffff" text-anchor="middle" font-family="system-ui, sans-serif">${s.num}</text>
          <text x="${x + 42}" y="141" font-size="11" font-weight="bold" fill="${colors.textMain}" font-family="system-ui, sans-serif">${s.title}</text>
          <line x1="${x + 10}" y1="160" x2="${x + 118}" y2="160" stroke="${s.col}" stroke-width="1" stroke-opacity="0.3"/>
          <text x="${x + 12}" y="180" font-size="9.5" fill="${colors.textMuted}" font-family="system-ui, sans-serif">
            <tspan x="${x + 12}" dy="0">${s.sub.split('&')[0]}</tspan>
            <tspan x="${x + 12}" dy="14">${s.sub.split('&')[1] || ''}</tspan>
          </text>
        </g>
        ${arrowSvg}
      `;
    }).join('');

  } else if (diagramType === 'network') {
    // Topologi / Relasi Model
    bodySvg = `
      <g>
        <!-- Center Hub -->
        <line x1="310" y1="175" x2="110" y2="140" stroke="#94a3b8" stroke-width="2" stroke-dasharray="4,4"/>
        <line x1="310" y1="175" x2="510" y2="140" stroke="#94a3b8" stroke-width="2" stroke-dasharray="4,4"/>
        <line x1="310" y1="175" x2="170" y2="250" stroke="#94a3b8" stroke-width="2"/>
        <line x1="310" y1="175" x2="450" y2="250" stroke="#94a3b8" stroke-width="2"/>

        <!-- Hub Core -->
        <rect x="235" y="140" width="150" height="70" rx="10" fill="#eff6ff" stroke="#2563eb" stroke-width="2" filter="url(#drop-shadow)"/>
        <text x="310" y="168" font-size="12" font-weight="bold" fill="#1e3a8a" text-anchor="middle" font-family="system-ui, sans-serif">MODUL PUSAT (CORE)</text>
        <text x="310" y="188" font-size="10" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">Penyelarasan & Manajemen State</text>

        <!-- Node 1 -->
        <rect x="40" y="110" width="130" height="60" rx="8" fill="#f0fdfa" stroke="#0d9488" stroke-width="1.8" filter="url(#drop-shadow)"/>
        <text x="105" y="136" font-size="11" font-weight="bold" fill="#0f766e" text-anchor="middle" font-family="system-ui, sans-serif">Node Input A</text>
        <text x="105" y="153" font-size="9" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">Sumber Data Primer</text>

        <!-- Node 2 -->
        <rect x="440" y="110" width="130" height="60" rx="8" fill="#faf5ff" stroke="#7e22ce" stroke-width="1.8" filter="url(#drop-shadow)"/>
        <text x="505" y="136" font-size="11" font-weight="bold" fill="#6b21a8" text-anchor="middle" font-family="system-ui, sans-serif">Layanan Pemroses</text>
        <text x="505" y="153" font-size="9" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">Transformasi Logika</text>

        <!-- Node 3 -->
        <rect x="100" y="225" width="130" height="55" rx="8" fill="#fffbeb" stroke="#b45309" stroke-width="1.8" filter="url(#drop-shadow)"/>
        <text x="165" y="250" font-size="11" font-weight="bold" fill="#92400e" text-anchor="middle" font-family="system-ui, sans-serif">Penyimpanan / DB</text>
        <text x="165" y="266" font-size="9" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">Persistensi Data</text>

        <!-- Node 4 -->
        <rect x="380" y="225" width="130" height="55" rx="8" fill="#ecfdf5" stroke="#16a34a" stroke-width="1.8" filter="url(#drop-shadow)"/>
        <text x="445" y="250" font-size="11" font-weight="bold" fill="#15803d" text-anchor="middle" font-family="system-ui, sans-serif">Antarmuka / Client</text>
        <text x="445" y="266" font-size="9" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">Visualisasi & Interaksi</text>
      </g>
    `;
  } else if (diagramType === 'hierarchy') {
    // Struktur Hirarki / Tree
    bodySvg = `
      <g>
        <!-- Connector Lines -->
        <line x1="320" y1="135" x2="320" y2="160" stroke="#94a3b8" stroke-width="2"/>
        <line x1="160" y1="160" x2="480" y2="160" stroke="#94a3b8" stroke-width="2"/>
        <line x1="160" y1="160" x2="160" y2="185" stroke="#94a3b8" stroke-width="2"/>
        <line x1="320" y1="160" x2="320" y2="185" stroke="#94a3b8" stroke-width="2"/>
        <line x1="480" y1="160" x2="480" y2="185" stroke="#94a3b8" stroke-width="2"/>

        <!-- Root Node -->
        <rect x="220" y="85" width="200" height="50" rx="8" fill="#eff6ff" stroke="#2563eb" stroke-width="2" filter="url(#drop-shadow)"/>
        <text x="320" y="108" font-size="12" font-weight="bold" fill="#1e3a8a" text-anchor="middle" font-family="system-ui, sans-serif">ENTITAS UTAMA (INDUK)</text>
        <text x="320" y="125" font-size="9.5" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">Standar Kebijakan & Prinsip</text>

        <!-- Branch 1 -->
        <rect x="70" y="185" width="180" height="85" rx="8" fill="#f0fdfa" stroke="#0d9488" stroke-width="1.8" filter="url(#drop-shadow)"/>
        <rect x="70" y="185" width="180" height="24" rx="8" fill="#ccfbf1"/>
        <text x="160" y="201" font-size="10.5" font-weight="bold" fill="#0f766e" text-anchor="middle" font-family="system-ui, sans-serif">Cabang A: Manajerial</text>
        <text x="80" y="225" font-size="9" fill="#334155" font-family="system-ui, sans-serif">• Tata kelola operasional</text>
        <text x="80" y="242" font-size="9" fill="#334155" font-family="system-ui, sans-serif">• Kepatuhan regulasi</text>
        <text x="80" y="259" font-size="9" fill="#334155" font-family="system-ui, sans-serif">• Pengawasan akuntabilitas</text>

        <!-- Branch 2 -->
        <rect x="260" y="185" width="180" height="85" rx="8" fill="#faf5ff" stroke="#7e22ce" stroke-width="1.8" filter="url(#drop-shadow)"/>
        <rect x="260" y="185" width="180" height="24" rx="8" fill="#f3e8ff"/>
        <text x="350" y="201" font-size="10.5" font-weight="bold" fill="#6b21a8" text-anchor="middle" font-family="system-ui, sans-serif">Cabang B: Fungsional</text>
        <text x="270" y="225" font-size="9" fill="#334155" font-family="system-ui, sans-serif">• Penerapan metode teknis</text>
        <text x="270" y="242" font-size="9" fill="#334155" font-family="system-ui, sans-serif">• Pemeliharaan sistem</text>
        <text x="270" y="259" font-size="9" fill="#334155" font-family="system-ui, sans-serif">• Dokumentasi kode etik</text>

        <!-- Branch 3 -->
        <rect x="450" y="185" width="150" height="85" rx="8" fill="#fffbeb" stroke="#b45309" stroke-width="1.8" filter="url(#drop-shadow)"/>
        <rect x="450" y="185" width="150" height="24" rx="8" fill="#fef3c7"/>
        <text x="525" y="201" font-size="10.5" font-weight="bold" fill="#92400e" text-anchor="middle" font-family="system-ui, sans-serif">Cabang C: Audit</text>
        <text x="460" y="225" font-size="9" fill="#334155" font-family="system-ui, sans-serif">• Penilaian risiko</text>
        <text x="460" y="242" font-size="9" fill="#334155" font-family="system-ui, sans-serif">• Pengujian integritas</text>
        <text x="460" y="259" font-size="9" fill="#334155" font-family="system-ui, sans-serif">• Pelaporan hasil</text>
      </g>
    `;
  } else if (diagramType === 'venn') {
    // Diagram Venn / Relasi Irisan Konseptual
    bodySvg = `
      <g>
        <!-- Circle Left -->
        <circle cx="250" cy="180" r="95" fill="#3b82f6" fill-opacity="0.22" stroke="#2563eb" stroke-width="2.5"/>
        <!-- Circle Right -->
        <circle cx="390" cy="180" r="95" fill="#10b981" fill-opacity="0.22" stroke="#059669" stroke-width="2.5"/>
        
        <!-- Label Domain A -->
        <text x="200" y="145" font-size="12" font-weight="bold" fill="#1e3a8a" text-anchor="middle" font-family="system-ui, sans-serif">Domain Teori / Konsep</text>
        <text x="200" y="170" font-size="9.5" fill="#334155" text-anchor="middle" font-family="system-ui, sans-serif">• Landasan Ilmiah</text>
        <text x="200" y="188" font-size="9.5" fill="#334155" text-anchor="middle" font-family="system-ui, sans-serif">• Kerangka Teoretis</text>
        <text x="200" y="206" font-size="9.5" fill="#334155" text-anchor="middle" font-family="system-ui, sans-serif">• Asas Fundamental</text>

        <!-- Label Domain B -->
        <text x="440" y="145" font-size="12" font-weight="bold" fill="#065f46" text-anchor="middle" font-family="system-ui, sans-serif">Domain Terapan / Kasus</text>
        <text x="440" y="170" font-size="9.5" fill="#334155" text-anchor="middle" font-family="system-ui, sans-serif">• Praktik di Lapangan</text>
        <text x="440" y="188" font-size="9.5" fill="#334155" text-anchor="middle" font-family="system-ui, sans-serif">• Solusi Kontekstual</text>
        <text x="440" y="206" font-size="9.5" fill="#334155" text-anchor="middle" font-family="system-ui, sans-serif">• Bukti Empiris</text>

        <!-- Intersection Area -->
        <rect x="290" y="145" width="60" height="70" rx="6" fill="#ffffff" fill-opacity="0.9" stroke="#7e22ce" stroke-width="1.5" filter="url(#drop-shadow)"/>
        <text x="320" y="165" font-size="10" font-weight="bold" fill="#7e22ce" text-anchor="middle" font-family="system-ui, sans-serif">IRISAN</text>
        <text x="320" y="182" font-size="8.5" font-weight="600" fill="#0f172a" text-anchor="middle" font-family="system-ui, sans-serif">Sintesis</text>
        <text x="320" y="196" font-size="8" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">Kompetensi</text>
        <text x="320" y="208" font-size="8" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">Optimal</text>
      </g>
    `;
  } else {
    // 3-Tier Layered Architecture / Model Konseptual
    bodySvg = `
      <g>
        <!-- Tier 1: Presentation / Input -->
        <g>
          <rect x="40" y="105" width="160" height="120" rx="10" fill="#f0f9ff" stroke="#0284c7" stroke-width="1.8" filter="url(#drop-shadow)"/>
          <rect x="40" y="105" width="160" height="28" rx="8" fill="#e0f2fe"/>
          <text x="120" y="124" font-size="11" font-weight="bold" fill="#0369a1" text-anchor="middle" font-family="system-ui, sans-serif">1. Lapisan Presentasi</text>
          <text x="120" y="155" font-size="10" font-weight="600" fill="#0f172a" text-anchor="middle" font-family="system-ui, sans-serif">Antarmuka Pengguna</text>
          <text x="120" y="175" font-size="9.5" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">• Penerimaan Input</text>
          <text x="120" y="195" font-size="9.5" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">• Validasi Form Awal</text>
        </g>

        <!-- Connector 1 -->
        <g transform="translate(202, 160)">
          <line x1="0" y1="0" x2="34" y2="0" stroke="#0284c7" stroke-width="2" marker-end="url(#arrowhead)"/>
          <line x1="34" y1="10" x2="0" y2="10" stroke="#0284c7" stroke-width="2" marker-end="url(#arrowhead)"/>
        </g>

        <!-- Tier 2: Business Logic / Engine -->
        <g>
          <rect x="240" y="95" width="160" height="140" rx="10" fill="#f0fdf4" stroke="#16a34a" stroke-width="2" filter="url(#drop-shadow)"/>
          <rect x="240" y="95" width="160" height="28" rx="8" fill="#dcfce7"/>
          <text x="320" y="114" font-size="11" font-weight="bold" fill="#15803d" text-anchor="middle" font-family="system-ui, sans-serif">2. Logika Komputasi</text>
          <text x="320" y="145" font-size="10" font-weight="600" fill="#0f172a" text-anchor="middle" font-family="system-ui, sans-serif">Controller & Domain</text>
          <text x="320" y="165" font-size="9.5" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">• Aturan Bisnis Pokok</text>
          <text x="320" y="185" font-size="9.5" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">• Pemrosesan Algoritma</text>
          <text x="320" y="205" font-size="9.5" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">• Enkripsi & Integritas</text>
        </g>

        <!-- Connector 2 -->
        <g transform="translate(402, 160)">
          <line x1="0" y1="0" x2="34" y2="0" stroke="#16a34a" stroke-width="2" marker-end="url(#arrowhead)"/>
          <line x1="34" y1="10" x2="0" y2="10" stroke="#16a34a" stroke-width="2" marker-end="url(#arrowhead)"/>
        </g>

        <!-- Tier 3: Persistence / Data -->
        <g>
          <rect x="440" y="105" width="160" height="120" rx="10" fill="#faf5ff" stroke="#7e22ce" stroke-width="1.8" filter="url(#drop-shadow)"/>
          <rect x="440" y="105" width="160" height="28" rx="8" fill="#f3e8ff"/>
          <text x="520" y="124" font-size="11" font-weight="bold" fill="#6b21a8" text-anchor="middle" font-family="system-ui, sans-serif">3. Lapisan Data</text>
          <text x="520" y="155" font-size="10" font-weight="600" fill="#0f172a" text-anchor="middle" font-family="system-ui, sans-serif">Database & Penyimpanan</text>
          <text x="520" y="175" font-size="9.5" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">• Relasi Tabel & Schema</text>
          <text x="520" y="195" font-size="9.5" fill="#64748b" text-anchor="middle" font-family="system-ui, sans-serif">• Backup & Transaksi ACID</text>
        </g>
      </g>
    `;
  }

  return `<?xml version="1.0" encoding="UTF-8"?>
<svg viewBox="0 0 640 340" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="background: ${colors.bg}; border-radius: 8px;">
  <defs>
    <filter id="drop-shadow" x="-5%" y="-5%" width="115%" height="115%">
      <feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#0f172a" flood-opacity="0.08"/>
    </filter>
    <marker id="arrowhead" markerWidth="7" markerHeight="7" refX="6" refY="3.5" orient="auto">
      <polygon points="0 0, 7 3.5, 0 7" fill="#64748b"/>
    </marker>
  </defs>

  <!-- Frame Background with Soft Grid -->
  <rect x="0" y="0" width="640" height="340" fill="${colors.bg}" stroke="${colors.border}" stroke-width="1.5" rx="8"/>
  
  <!-- Header Banner -->
  <rect x="0" y="0" width="640" height="58" fill="#ffffff" rx="8 8 0 0"/>
  <line x1="0" y1="58" x2="640" y2="58" stroke="#e2e8f0" stroke-width="1.2"/>

  <!-- Badge Stimulus -->
  <rect x="24" y="14" width="140" height="22" rx="11" fill="#e0f2fe"/>
  <text x="94" y="29" font-size="9" font-weight="bold" fill="#0369a1" text-anchor="middle" font-family="system-ui, sans-serif">STIMULUS AKADEMIK</text>

  <!-- Title & Context -->
  <text x="176" y="29" font-size="13" font-weight="bold" fill="${colors.textMain}" font-family="system-ui, sans-serif">${cleanTitle}</text>
  <text x="26" y="48" font-size="10" fill="${colors.textMuted}" font-family="system-ui, sans-serif">Fokus Stimulus: ${cleanContext}</text>

  <!-- Main Diagram Body -->
  ${bodySvg}

  <!-- Footer / Caption Bar -->
  <line x1="20" y1="298" x2="620" y2="298" stroke="#e2e8f0" stroke-width="1"/>
  <text x="24" y="318" font-size="9" fill="${colors.textMuted}" font-family="system-ui, sans-serif">
    <tspan font-weight="bold" fill="#334155">Petunjuk Analisis:</tspan> Evaluasilah struktur hubungan dan tahapan komponen pada diagram di atas untuk merumuskan jawaban yang tepat.
  </text>
  <text x="616" y="318" font-size="9" fill="#94a3b8" text-anchor="end" font-family="system-ui, sans-serif">Instrumen SIPENA</text>
</svg>`;
}
