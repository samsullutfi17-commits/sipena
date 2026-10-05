/**
 * Helper to build and merge format_cetak configuration for Kop Surat & Identitas Ujian
 * Allows Admin, Dosen, and Pengampu to customize Kop, Title, 2-column Identity table, and Instructions per-exam or per-role.
 */
export function getUjianFormatCetak(ujian = {}, user = null, settings = {}) {
  const matkulNama = ujian.nama_mk || '-';

  // Automatically detect and populate the creator/lecturer name
  let namaDosen = '';
  if (ujian && ujian.dosen_pembuat && ujian.dosen_pembuat.trim() && ujian.dosen_pembuat !== 'Dosen Pengampu') {
    namaDosen = ujian.dosen_pembuat.trim();
  } else if (ujian && ujian.pengampu && ujian.pengampu.trim() && ujian.pengampu !== 'Dosen Pengampu' && ujian.pengampu !== 'Tim Dosen Pengampu') {
    namaDosen = ujian.pengampu.trim();
  } else if (user && user.role === 'dosen' && user.nama_lengkap) {
    namaDosen = user.nama_lengkap.trim();
  } else if (user && user.nama_lengkap && user.role !== 'peserta') {
    namaDosen = user.nama_lengkap.trim();
  } else if (settings && settings.nama_dosen) {
    namaDosen = settings.nama_dosen.trim();
  } else {
    namaDosen = 'Samsul Lutfi, S.Pd., M.Pd';
  }

  // 1. Default institutional layout matching screenshot
  const defaultFormat = {
    // Kop Surat (Korps)
    tampilkan_kop: true,
    tampilkan_logo: true,
    logo_path: (settings && settings.logo_path) || '/assets/img/logo_hamzanwadi.svg',
    nama_institusi: (settings && settings.nama_institusi) || 'UNIVERSITAS HAMZANWADI',
    sub_institusi: (settings && settings.fakultas_institusi) || '',
    alamat_institusi: (settings && settings.alamat_institusi) || 'Jl. TGKH. Muhammad Zainuddin Abdul Madjid No. 132, Pancor, Kec. Selong, Kabupaten Lombok Timur, Nusa Tenggara Barat 83611',
    kontak_institusi: (settings && settings.telepon_institusi) || 'Telp. (0376) 22954, Website: http://hamzanwadi.ac.id, email: universitas@hamzanwadi.ac.id',
    tampilkan_garis: true,

    // Judul Naskah
    judul_utama: ujian.jenis_ujian === 'UAS' 
      ? 'UJIAN AKHIR SEMESTER GANJIL UNIVERSITAS HAMZANWADI'
      : 'UJIAN TENGAH SEMESTER GANJIL UNIVERSITAS HAMZANWADI',
    sub_judul: `TAHUN AKADEMIK ${ujian.tahun_akademik || '2022/2023'}`,

    // Dynamic 2-column Identity table rows
    identitas_rows: [
      {
        kiri_label: 'Hari/Tanggal',
        kiri_val: ujian.hari_tanggal || 'Senin, 14 November 2022',
        kanan_label: 'Fakultas/Prodi',
        kanan_val: ujian.fakultas_prodi || 'MIPA / Pend. Informatika'
      },
      {
        kiri_label: 'Waktu',
        kiri_val: ujian.waktu || '08:00 - 09:30',
        kanan_label: 'Mata Kuliah',
        kanan_val: matkulNama
      },
      {
        kiri_label: 'Dosen Pengampu',
        kiri_val: namaDosen,
        kanan_label: 'Smt/SKS',
        kanan_val: ujian.smt_sks || 'V (A,B) / 3 sks'
      }
    ],

    // Petunjuk Pengerjaan
    tampilkan_petunjuk: true,
    judul_petunjuk: 'PETUNJUK:',
    petunjuk_items: [
      "Berdo'a sebelum mengerjakan soal !",
      "Isi identitas Anda terlebih dahulu !",
      "Soal Ujian ini kerjakan di rumah (Take Home).",
      "Terakhir, semoga Anda selamat & Sukses dari ujian ini."
    ],

    // Matriks Kisi-Kisi & Tanda Tangan
    judul_matriks: 'KISI-KISI PENULISAN BUTIR SOAL UJIAN',
    tampilkan_ttd: true,
    kota_ttd: 'Selong',
    tanggal_ttd: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }),
    jabatan_kiri: 'Ketua Program Studi',
    nama_pejabat_kiri: (settings && settings.nama_kaprodi) || 'Dr. H. M. Zain, M.Pd.',
    nip_kiri: (settings && settings.nidn_kaprodi) || '0812048501',
    jabatan_kanan: 'Dosen Pengampu Mata Kuliah',
    nama_pejabat_kanan: namaDosen,
    nip_kanan: (settings && settings.nidn_dosen) || '0821098902'
  };

  // 2. User/Role custom default template
  const userDefault = user && user.default_format_cetak ? user.default_format_cetak : {};

  // 3. Exam specific custom format
  const examFormat = ujian && ujian.format_cetak ? ujian.format_cetak : {};

  const merged = {
    ...defaultFormat,
    ...userDefault,
    ...examFormat
  };

  // Resolve arrays safely
  if (Array.isArray(examFormat.identitas_rows) && examFormat.identitas_rows.length > 0) {
    merged.identitas_rows = JSON.parse(JSON.stringify(examFormat.identitas_rows));
  } else if (Array.isArray(userDefault.identitas_rows) && userDefault.identitas_rows.length > 0) {
    merged.identitas_rows = JSON.parse(JSON.stringify(userDefault.identitas_rows));
  }

  // Ensure lecturer row is synchronized with creator lecturer if still generic
  if (Array.isArray(merged.identitas_rows)) {
    let hasPengampuRow = false;
    merged.identitas_rows.forEach(r => {
      const kLabel = (r.kiri_label || '').toLowerCase();
      const rLabel = (r.kanan_label || '').toLowerCase();
      if (kLabel.includes('pengampu') || kLabel.includes('dosen')) {
        hasPengampuRow = true;
        if (!r.kiri_val || r.kiri_val === 'Dosen Pengampu' || r.kiri_val === 'Tim Dosen Pengampu') {
          r.kiri_val = namaDosen;
        }
      }
      if (rLabel.includes('pengampu') || rLabel.includes('dosen')) {
        hasPengampuRow = true;
        if (!r.kanan_val || r.kanan_val === 'Dosen Pengampu' || r.kanan_val === 'Tim Dosen Pengampu') {
          r.kanan_val = namaDosen;
        }
      }
    });

    if (!hasPengampuRow) {
      merged.identitas_rows.push({
        kiri_label: 'Dosen Pengampu',
        kiri_val: namaDosen,
        kanan_label: 'Status Naskah',
        kanan_val: 'Resmi / Terverifikasi'
      });
    }
  }

  if (Array.isArray(examFormat.petunjuk_items) && examFormat.petunjuk_items.length > 0) {
    merged.petunjuk_items = examFormat.petunjuk_items;
  } else if (Array.isArray(userDefault.petunjuk_items) && userDefault.petunjuk_items.length > 0) {
    merged.petunjuk_items = userDefault.petunjuk_items;
  }

  // Synchronize signature lecturer name if generic
  if (!merged.nama_pejabat_kanan || merged.nama_pejabat_kanan === 'Dosen Pengampu' || merged.nama_pejabat_kanan === 'Tim Dosen Pengampu') {
    merged.nama_pejabat_kanan = namaDosen;
  }

  return merged;
}
