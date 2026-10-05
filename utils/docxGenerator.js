import {
  Document,
  Paragraph,
  TextRun,
  Table,
  TableRow,
  TableCell,
  WidthType,
  AlignmentType,
  BorderStyle,
  HeadingLevel,
  Packer
} from 'docx';
import { getUjianFormatCetak } from './formatCetakHelper.js';

/**
 * Generate official Word (.docx) document matching Image 2
 */
export async function generateExamDocx(ujian, soalList, settings, user = null) {
  const children = [];
  const format = getUjianFormatCetak(ujian, user, settings);

  // 1. KOP SURAT (If enabled)
  if (format.tampilkan_kop) {
    children.push(
      new Paragraph({
        alignment: AlignmentType.CENTER,
        spacing: { after: 40 },
        children: [
          new TextRun({
            text: format.nama_institusi || 'UNIVERSITAS HAMZANWADI',
            bold: true,
            size: 32, // 16pt
            font: 'Times New Roman'
          })
        ]
      })
    );

    if (format.sub_institusi) {
      children.push(
        new Paragraph({
          alignment: AlignmentType.CENTER,
          spacing: { after: 30 },
          children: [
            new TextRun({
              text: format.sub_institusi,
              bold: true,
              size: 24, // 12pt
              font: 'Times New Roman'
            })
          ]
        })
      );
    }

    if (format.alamat_institusi) {
      children.push(
        new Paragraph({
          alignment: AlignmentType.CENTER,
          spacing: { after: 30 },
          children: [
            new TextRun({
              text: format.alamat_institusi,
              size: 20, // 10pt
              font: 'Times New Roman'
            })
          ]
        })
      );
    }

    if (format.kontak_institusi) {
      children.push(
        new Paragraph({
          alignment: AlignmentType.CENTER,
          spacing: { after: 100 },
          children: [
            new TextRun({
              text: format.kontak_institusi,
              size: 19, // 9.5pt
              font: 'Times New Roman'
            })
          ]
        })
      );
    }

    // 2. DOUBLE DIVIDER LINE (If enabled)
    if (format.tampilkan_garis) {
      children.push(
        new Table({
          width: { size: 100, type: WidthType.PERCENTAGE },
          borders: {
            top: { style: BorderStyle.SINGLE, size: 24, color: '000000' },
            bottom: { style: BorderStyle.SINGLE, size: 8, color: '000000' },
            left: { style: BorderStyle.NONE },
            right: { style: BorderStyle.NONE },
            insideHorizontal: { style: BorderStyle.NONE },
            insideVertical: { style: BorderStyle.NONE }
          },
          rows: [
            new TableRow({
              children: [
                new TableCell({
                  children: [new Paragraph({ text: '', spacing: { before: 20, after: 20 } })]
                })
              ]
            })
          ]
        })
      );
    }

    children.push(new Paragraph({ text: '', spacing: { after: 140 } }));
  }

  // 3. EXAM TITLE
  children.push(
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 40 },
      children: [
        new TextRun({
          text: format.judul_utama || 'UJIAN AKHIR SEMESTER GANJIL UNIVERSITAS HAMZANWADI',
          bold: true,
          size: 24, // 12pt
          font: 'Times New Roman'
        })
      ]
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 200 },
      children: [
        new TextRun({
          text: format.sub_judul || `TAHUN AKADEMIK ${ujian.tahun_akademik || '2022/2023'}`,
          bold: true,
          size: 24, // 12pt
          font: 'Times New Roman'
        })
      ]
    })
  );

  // 4. BOXED 2-COLUMN TABLE MATCHING IMAGE 2 (DYNAMIC ROWS)
  const borderThin = { style: BorderStyle.SINGLE, size: 8, color: '000000' };
  const cellBorders = {
    top: borderThin,
    bottom: borderThin,
    left: borderThin,
    right: borderThin
  };

  const createMetaCell = (label, val) => {
    return new Paragraph({
      spacing: { before: 40, after: 40 },
      children: [
        new TextRun({ text: `${(label || '').padEnd(16, ' ')}: `, bold: false, size: 21, font: 'Times New Roman' }),
        new TextRun({ text: String(val || '-'), bold: false, size: 21, font: 'Times New Roman' })
      ]
    });
  };

  const identitasRows = (format.identitas_rows && Array.isArray(format.identitas_rows) && format.identitas_rows.length > 0)
    ? format.identitas_rows
    : [
        { kiri_label: 'Hari/Tanggal', kiri_val: ujian.hari_tanggal || 'Sabtu, 21 Januari 2023', kanan_label: 'Fakultas/Prodi', kanan_val: ujian.fakultas_prodi || 'MIPA/Pend. Informatika' },
        { kiri_label: 'Waktu', kiri_val: ujian.waktu || '09:15-11:45', kanan_label: 'Mata Kuliah', kanan_val: ujian.nama_mk || '-' },
        { kiri_label: 'Pengampu', kiri_val: ujian.pengampu || 'Samsul Lutfi, S.Pd., M.Pd', kanan_label: 'Smt/SKS', kanan_val: ujian.smt_sks || 'V (A,B) / 3 sks' }
      ];

  const metaTableRows = identitasRows.map(r => {
    return new TableRow({
      children: [
        new TableCell({
          width: { size: 50, type: WidthType.PERCENTAGE },
          borders: cellBorders,
          children: [createMetaCell(r.kiri_label || '', r.kiri_val || '-')]
        }),
        new TableCell({
          width: { size: 50, type: WidthType.PERCENTAGE },
          borders: cellBorders,
          children: [createMetaCell(r.kanan_label || '', r.kanan_val || '-')]
        })
      ]
    });
  });

  const metaTable = new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    borders: {
      top: borderThin,
      bottom: borderThin,
      left: borderThin,
      right: borderThin,
      insideHorizontal: borderThin,
      insideVertical: borderThin
    },
    rows: metaTableRows
  });

  children.push(metaTable);
  children.push(new Paragraph({ text: '', spacing: { after: 140 } }));

  // 5. PETUNJUK PENGERJAAN (DYNAMIC ITEMS)
  if (format.tampilkan_petunjuk && Array.isArray(format.petunjuk_items) && format.petunjuk_items.length > 0) {
    const letters = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
    format.petunjuk_items.forEach((p, pIdx) => {
      const prefix = letters[pIdx] ? `${letters[pIdx]}. ` : '- ';
      const textToDisplay = p.startsWith(prefix) ? p : `${prefix}${p}`;
      children.push(
        new Paragraph({
          spacing: { after: 30 },
          children: [
            new TextRun({
              text: textToDisplay,
              italics: true,
              size: 21,
              font: 'Times New Roman'
            })
          ]
        })
      );
    });
    children.push(new Paragraph({ text: '', spacing: { after: 140 } }));
  }

  // 6. SECTION HEADING: SOAL
  children.push(
    new Paragraph({
      spacing: { after: 140 },
      children: [
        new TextRun({
          text: 'SOAL',
          bold: true,
          italics: true,
          size: 24,
          font: 'Times New Roman'
        })
      ]
    })
  );

function getJenisLabel(code) {
  const map = {
    'pg': 'Pilihan Ganda (PG)',
    'pg_kompleks': 'PG Kompleks',
    'tf': 'Benar / Salah',
    'menjodohkan': 'Menjodohkan',
    'short': 'Isian Singkat',
    'isian': 'Isian Singkat',
    'esai': 'Uraian / Esai',
    'essay': 'Uraian / Esai'
  };
  return map[(code || '').toLowerCase()] || (code ? code.toUpperCase() : 'Pilihan Ganda');
}

  // 7. QUESTIONS LIST
  const letters = ['a', 'b', 'c', 'd', 'e'];
  soalList.forEach((soal, idx) => {
    const cleanPertanyaan = (soal.pertanyaan || '').replace(/<[^>]*>?/gm, '').trim();
    children.push(
      new Paragraph({
        spacing: { before: 120, after: 60 },
        children: [
          new TextRun({
            text: `${idx + 1}.  `,
            bold: true,
            size: 22,
            font: 'Times New Roman'
          }),
          new TextRun({
            text: cleanPertanyaan,
            size: 22,
            font: 'Times New Roman'
          })
        ]
      })
    );

    if (soal.opsi && soal.opsi.length > 0) {
      soal.opsi.forEach((opt, oIdx) => {
        children.push(
          new Paragraph({
            indent: { left: 450 },
            spacing: { after: 40 },
            children: [
              new TextRun({
                text: `${letters[oIdx] || oIdx + 1}.  `,
                bold: false,
                size: 21,
                font: 'Times New Roman'
              }),
              new TextRun({
                text: opt.teks || opt.teks_opsi || '',
                size: 21,
                font: 'Times New Roman'
              })
            ]
          })
        );
      });
    } else {
      children.push(
        new Paragraph({
          indent: { left: 450 },
          spacing: { after: 60 },
          children: [
            new TextRun({
              text: '[ Jawaban / Uraian Mahasiswa: ............................................................................................ ]',
              italics: true,
              size: 20,
              font: 'Times New Roman'
            })
          ]
        })
      );
    }
  });

  // 8. LAMPIRAN I: KUNCI JAWABAN & PEDOMAN PENILAIAN
  const appendixBorder = { style: BorderStyle.SINGLE, size: 6, color: '000000' };
  const appendixBorders = {
    top: appendixBorder,
    bottom: appendixBorder,
    left: appendixBorder,
    right: appendixBorder
  };

  children.push(
    new Paragraph({
      pageBreakBefore: true,
      spacing: { before: 180, after: 120 },
      alignment: AlignmentType.CENTER,
      children: [
        new TextRun({
          text: 'LAMPIRAN I: KUNCI JAWABAN & PEDOMAN PENILAIAN',
          bold: true,
          size: 24,
          font: 'Times New Roman'
        })
      ]
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 180 },
      children: [
        new TextRun({
          text: `Naskah Evaluasi: ${ujian.judul_ujian} | Pengampu: ${ujian.pengampu || 'Dosen Pengampu'}`,
          italics: true,
          size: 20,
          font: 'Times New Roman'
        })
      ]
    })
  );

  const createAppendixHeaderCell = (text, widthPct) => new TableCell({
    width: { size: widthPct, type: WidthType.PERCENTAGE },
    borders: appendixBorders,
    shading: { fill: 'F1F5F9' },
    children: [
      new Paragraph({
        alignment: AlignmentType.CENTER,
        spacing: { before: 60, after: 60 },
        children: [new TextRun({ text, bold: true, size: 19, font: 'Times New Roman' })]
      })
    ]
  });

  const createAppendixCell = (text, widthPct, align = AlignmentType.LEFT) => new TableCell({
    width: { size: widthPct, type: WidthType.PERCENTAGE },
    borders: appendixBorders,
    children: [
      new Paragraph({
        alignment: align,
        spacing: { before: 40, after: 40 },
        children: [new TextRun({ text: String(text || '-'), size: 18, font: 'Times New Roman' })]
      })
    ]
  });

  const kunciRows = [
    new TableRow({
      children: [
        createAppendixHeaderCell('No', 6),
        createAppendixHeaderCell('Bentuk Soal', 16),
        createAppendixHeaderCell('Kognitif', 10),
        createAppendixHeaderCell('Bobot', 8),
        createAppendixHeaderCell('Kunci Jawaban', 28),
        createAppendixHeaderCell('Pembahasan & Pedoman Penskoran', 32)
      ]
    })
  ];

  soalList.forEach((s, idx) => {
    let kunci = '-';
    if (s.opsi && s.opsi.length > 0) {
      const correct = s.opsi.filter(o => o.benar);
      if (correct.length > 0) {
        kunci = correct.map(o => {
          const lIdx = s.opsi.indexOf(o);
          const letter = String.fromCharCode(65 + (lIdx >= 0 ? lIdx : 0));
          return `${letter}. ${o.teks || o.teks_opsi || ''}`;
        }).join('; ');
      }
    } else if (s.kunci_jawaban) {
      kunci = s.kunci_jawaban;
    } else if (s.pembahasan) {
      kunci = s.pembahasan;
    }

    kunciRows.push(
      new TableRow({
        children: [
          createAppendixCell(idx + 1, 6, AlignmentType.CENTER),
          createAppendixCell(getJenisLabel(s.jenis_soal), 16),
          createAppendixCell(s.level_kognitif || 'C3', 10, AlignmentType.CENTER),
          createAppendixCell(s.poin || 4, 8, AlignmentType.CENTER),
          createAppendixCell(kunci, 28),
          createAppendixCell(s.pembahasan || '-', 32)
        ]
      })
    );
  });

  children.push(
    new Table({
      width: { size: 100, type: WidthType.PERCENTAGE },
      rows: kunciRows
    })
  );

  // 9. LAMPIRAN II: MATRIKS KISI-KISI BUTIR SOAL
  children.push(
    new Paragraph({
      pageBreakBefore: true,
      spacing: { before: 180, after: 120 },
      alignment: AlignmentType.CENTER,
      children: [
        new TextRun({
          text: 'LAMPIRAN II: MATRIKS KISI-KISI PENULISAN SOAL',
          bold: true,
          size: 24,
          font: 'Times New Roman'
        })
      ]
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 180 },
      children: [
        new TextRun({
          text: `Sebaran Taksonomi Bloom & Indikator Capaian Mata Kuliah: ${ujian.nama_mk || ujian.judul_ujian}`,
          italics: true,
          size: 20,
          font: 'Times New Roman'
        })
      ]
    })
  );

  const matrixTableRows = [
    new TableRow({
      children: [
        createAppendixHeaderCell('No', 5),
        createAppendixHeaderCell('Capaian Pembelajaran (CPMK)', 25),
        createAppendixHeaderCell('Bahan Kajian / Materi', 20),
        createAppendixHeaderCell('Indikator Soal', 25),
        createAppendixHeaderCell('Bentuk', 10),
        createAppendixHeaderCell('Kognitif', 8),
        createAppendixHeaderCell('Bobot', 7)
      ]
    })
  ];

  soalList.forEach((s, idx) => {
    matrixTableRows.push(
      new TableRow({
        children: [
          createAppendixCell(idx + 1, 5, AlignmentType.CENTER),
          createAppendixCell(s.cpmk || 'Capaian Pembelajaran MK', 25),
          createAppendixCell(s.materi_pokok || ujian.nama_mk || 'Materi Pokok', 20),
          createAppendixCell(s.indikator || (s.pertanyaan ? s.pertanyaan.replace(/<[^>]*>?/gm, '').substring(0, 80) + '...' : '-'), 25),
          createAppendixCell(getJenisLabel(s.jenis_soal), 10, AlignmentType.CENTER),
          createAppendixCell(s.level_kognitif || 'C3', 8, AlignmentType.CENTER),
          createAppendixCell(s.poin || 4, 7, AlignmentType.CENTER)
        ]
      })
    );
  });

  children.push(
    new Table({
      width: { size: 100, type: WidthType.PERCENTAGE },
      rows: matrixTableRows
    })
  );

  const doc = new Document({
    sections: [
      {
        properties: {
          page: {
            margin: {
              top: 1134, // ~20mm
              bottom: 1134,
              left: 1134,
              right: 1134
            }
          }
        },
        children
      }
    ]
  });

  return await Packer.toBuffer(doc);
}

/**
 * Generate Kisi-Kisi Matrix Word (.docx) document
 */
export async function generateMatrixDocx(ujian, soalList, settings, user = null) {
  const children = [];
  const format = getUjianFormatCetak(ujian, user, settings);

  // Kop Surat
  if (format.tampilkan_kop) {
    children.push(
      new Paragraph({
        alignment: AlignmentType.CENTER,
        spacing: { after: 40 },
        children: [
          new TextRun({
            text: format.nama_institusi || 'UNIVERSITAS HAMZANWADI',
            bold: true,
            size: 28,
            font: 'Times New Roman'
          })
        ]
      })
    );

    if (format.sub_institusi) {
      children.push(
        new Paragraph({
          alignment: AlignmentType.CENTER,
          spacing: { after: 30 },
          children: [
            new TextRun({
              text: format.sub_institusi,
              bold: true,
              size: 22,
              font: 'Times New Roman'
            })
          ]
        })
      );
    }
  }

  children.push(
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 120 },
      children: [
        new TextRun({
          text: 'MATRIKS KISI-KISI PENULISAN SOAL EVALUASI AKADEMIK',
          bold: true,
          size: 24,
          font: 'Times New Roman'
        })
      ]
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 200 },
      children: [
        new TextRun({
          text: `Mata Kuliah: ${ujian.nama_mk || '-'} | Ujian: ${ujian.judul_ujian || 'Evaluasi'}`,
          italics: true,
          size: 20,
          font: 'Times New Roman'
        })
      ]
    })
  );

  const borderThin = { style: BorderStyle.SINGLE, size: 6, color: '000000' };
  const headerBorders = {
    top: borderThin,
    bottom: borderThin,
    left: borderThin,
    right: borderThin
  };

  const createHeaderCell = (text, widthPct) => new TableCell({
    width: { size: widthPct, type: WidthType.PERCENTAGE },
    borders: headerBorders,
    shading: { fill: 'E2E8F0' },
    children: [
      new Paragraph({
        alignment: AlignmentType.CENTER,
        spacing: { before: 60, after: 60 },
        children: [new TextRun({ text, bold: true, size: 19, font: 'Times New Roman' })]
      })
    ]
  });

  const createCell = (text, widthPct, align = AlignmentType.LEFT) => new TableCell({
    width: { size: widthPct, type: WidthType.PERCENTAGE },
    borders: headerBorders,
    children: [
      new Paragraph({
        alignment: align,
        spacing: { before: 40, after: 40 },
        children: [new TextRun({ text: String(text || '-'), size: 18, font: 'Times New Roman' })]
      })
    ]
  });

  const tableRows = [
    new TableRow({
      children: [
        createHeaderCell('No', 5),
        createHeaderCell('CPMK', 25),
        createHeaderCell('Materi Pokok', 20),
        createHeaderCell('Indikator Soal', 25),
        createHeaderCell('Bentuk', 8),
        createHeaderCell('Kognitif', 6),
        createHeaderCell('Kesulitan', 6),
        createHeaderCell('Bobot', 5)
      ]
    })
  ];

  soalList.forEach((s, idx) => {
    tableRows.push(
      new TableRow({
        children: [
          createCell(idx + 1, 5, AlignmentType.CENTER),
          createCell(s.cpmk || 'Capaian Pembelajaran MK', 25),
          createCell(s.materi_pokok || 'Materi Inti', 20),
          createCell(s.indikator || s.pertanyaan.substring(0, 70) + '...', 25),
          createCell(s.jenis_soal ? s.jenis_soal.toUpperCase() : 'PG', 8, AlignmentType.CENTER),
          createCell(s.level_kognitif || 'C2', 6, AlignmentType.CENTER),
          createCell(s.tingkat_kesulitan || 'sedang', 6, AlignmentType.CENTER),
          createCell(s.poin || 4, 5, AlignmentType.CENTER)
        ]
      })
    );
  });

  children.push(
    new Table({
      width: { size: 100, type: WidthType.PERCENTAGE },
      rows: tableRows
    })
  );

  const doc = new Document({
    sections: [
      {
        properties: {
          page: {
            size: { orientation: 'landscape' },
            margin: { top: 720, bottom: 720, left: 720, right: 720 }
          }
        },
        children
      }
    ]
  });

  return await Packer.toBuffer(doc);
}
