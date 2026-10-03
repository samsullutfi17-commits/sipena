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

/**
 * Generate official Word (.docx) document matching Image 2
 */
export async function generateExamDocx(ujian, soalList, settings) {
  const children = [];

  // 1. KOP SURAT
  children.push(
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 40 },
      children: [
        new TextRun({
          text: settings.nama_institusi || 'UNIVERSITAS HAMZANWADI',
          bold: true,
          size: 32, // 16pt
          font: 'Times New Roman'
        })
      ]
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 40 },
      children: [
        new TextRun({
          text: settings.alamat_institusi || 'Jln. TGKH. Muhammad Zainuddin Abdul Madjid No. 132 Pancor, Selong Lombok Timur 83612',
          size: 20, // 10pt
          font: 'Times New Roman'
        })
      ]
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 120 },
      children: [
        new TextRun({
          text: 'Telp. (0376) 22954, Website: http://hamzanwadi.ac.id, email: universitas@hamzanwadi.ac.id',
          size: 19, // 9.5pt
          font: 'Times New Roman'
        })
      ]
    })
  );

  // 2. DOUBLE DIVIDER LINE (Simulated via Paragraph border or styled table)
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

  children.push(new Paragraph({ text: '', spacing: { after: 160 } }));

  // 3. EXAM TITLE
  const jenisTitle = ujian.jenis_ujian === 'UAS' 
    ? 'UJIAN AKHIR SEMESTER GANJIL UNIVERSITAS HAMZANWADI'
    : 'UJIAN TENGAH SEMESTER GANJIL UNIVERSITAS HAMZANWADI';

  children.push(
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 40 },
      children: [
        new TextRun({
          text: jenisTitle,
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
          text: `TAHUN AKADEMIK ${ujian.tahun_akademik || '2022/2023'}`,
          bold: true,
          size: 24, // 12pt
          font: 'Times New Roman'
        })
      ]
    })
  );

  // 4. BOXED 2-COLUMN TABLE MATCHING IMAGE 2
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
        new TextRun({ text: `${label.padEnd(16, ' ')}: `, bold: false, size: 21, font: 'Times New Roman' }),
        new TextRun({ text: val || '-', bold: false, size: 21, font: 'Times New Roman' })
      ]
    });
  };

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
    rows: [
      new TableRow({
        children: [
          new TableCell({
            width: { size: 50, type: WidthType.PERCENTAGE },
            borders: cellBorders,
            children: [createMetaCell('Hari/Tanggal', ujian.hari_tanggal || 'Sabtu, 21 Januari 2023')]
          }),
          new TableCell({
            width: { size: 50, type: WidthType.PERCENTAGE },
            borders: cellBorders,
            children: [createMetaCell('Fakultas/Prodi', ujian.fakultas_prodi || 'MIPA/Pend. Informatika')]
          })
        ]
      }),
      new TableRow({
        children: [
          new TableCell({
            width: { size: 50, type: WidthType.PERCENTAGE },
            borders: cellBorders,
            children: [createMetaCell('Waktu', ujian.waktu || '09:15-11:45')]
          }),
          new TableCell({
            width: { size: 50, type: WidthType.PERCENTAGE },
            borders: cellBorders,
            children: [createMetaCell('Mata Kuliah', ujian.nama_mk || '-')]
          })
        ]
      }),
      new TableRow({
        children: [
          new TableCell({
            width: { size: 50, type: WidthType.PERCENTAGE },
            borders: cellBorders,
            children: [createMetaCell('Pengampu', ujian.pengampu || 'Samsul Lutfi, S.Pd., M.Pd')]
          }),
          new TableCell({
            width: { size: 50, type: WidthType.PERCENTAGE },
            borders: cellBorders,
            children: [createMetaCell('Smt/SKS', ujian.smt_sks || 'V (A,B) / 3 sks')]
          })
        ]
      })
    ]
  });

  children.push(metaTable);
  children.push(new Paragraph({ text: '', spacing: { after: 140 } }));

  // 5. PETUNJUK PENGERJAAN MATCHING IMAGE 2
  const petunjukItems = [
    "a. Berdo'a sebelum mengerjakan soal !",
    "b. Isi identitas Anda terlebih dahulu !",
    "c. Soal Ujian ini kerjakan di rumah (Take Home).",
    "d. Terakhir, semoga Anda selamat & Sukses dari ujian ini."
  ];

  petunjukItems.forEach(p => {
    children.push(
      new Paragraph({
        spacing: { after: 30 },
        children: [
          new TextRun({
            text: p,
            italics: true,
            size: 21,
            font: 'Times New Roman'
          })
        ]
      })
    );
  });

  children.push(new Paragraph({ text: '', spacing: { after: 140 } }));

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

  // 7. QUESTIONS LIST
  const letters = ['a', 'b', 'c', 'd', 'e'];
  soalList.forEach((soal, idx) => {
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
            text: soal.pertanyaan,
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
                text: opt.teks,
                size: 21,
                font: 'Times New Roman'
              })
            ]
          })
        );
      });
    }
  });

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
export async function generateMatrixDocx(ujian, soalList, settings) {
  const children = [];

  // Kop Surat
  children.push(
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 40 },
      children: [
        new TextRun({
          text: settings.nama_institusi || 'UNIVERSITAS HAMZANWADI',
          bold: true,
          size: 28,
          font: 'Times New Roman'
        })
      ]
    }),
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
          text: `Mata Kuliah: ${ujian.nama_mk || '-'} | Ujian: ${ujian.judul_ujian}`,
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
