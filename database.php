<?php

function initDatabase($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fakultas (
            id SERIAL PRIMARY KEY,
            kode_fakultas VARCHAR(20) NOT NULL UNIQUE,
            nama_fakultas VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS program_studi (
            id SERIAL PRIMARY KEY,
            kode_prodi VARCHAR(20) NOT NULL UNIQUE,
            nama_prodi VARCHAR(255) NOT NULL,
            fakultas VARCHAR(255),
            id_fakultas INTEGER REFERENCES fakultas(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS kelas (
            id SERIAL PRIMARY KEY,
            nama_kelas VARCHAR(100) NOT NULL,
            angkatan INTEGER,
            id_program_studi INTEGER REFERENCES program_studi(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS mahasiswa (
            id SERIAL PRIMARY KEY,
            nim VARCHAR(50) NOT NULL,
            nama_lengkap VARCHAR(255) NOT NULL,
            id_kelas INTEGER REFERENCES kelas(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS mata_kuliah (
            id SERIAL PRIMARY KEY,
            kode_mk VARCHAR(20) NOT NULL UNIQUE,
            nama_mk VARCHAR(255) NOT NULL,
            sks INTEGER DEFAULT 3
        );

        CREATE TABLE IF NOT EXISTS mata_kuliah_kelas (
            id SERIAL PRIMARY KEY,
            id_mata_kuliah INTEGER REFERENCES mata_kuliah(id) ON DELETE CASCADE,
            id_kelas INTEGER REFERENCES kelas(id) ON DELETE CASCADE,
            UNIQUE(id_mata_kuliah, id_kelas)
        );

        CREATE TABLE IF NOT EXISTS ujian (
            id SERIAL PRIMARY KEY,
            judul_ujian VARCHAR(255) NOT NULL,
            id_mata_kuliah INTEGER REFERENCES mata_kuliah(id) ON DELETE CASCADE,
            jenis_ujian VARCHAR(50) DEFAULT 'UTS',
            durasi_menit INTEGER DEFAULT 90,
            total_nilai INTEGER DEFAULT 100,
            aktif BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS ujian_kelas (
            id SERIAL PRIMARY KEY,
            id_ujian INTEGER REFERENCES ujian(id) ON DELETE CASCADE,
            id_kelas INTEGER REFERENCES kelas(id) ON DELETE CASCADE,
            UNIQUE(id_ujian, id_kelas)
        );

        CREATE TABLE IF NOT EXISTS soal (
            id SERIAL PRIMARY KEY,
            id_ujian INTEGER REFERENCES ujian(id) ON DELETE CASCADE,
            jenis_soal VARCHAR(30) NOT NULL,
            pertanyaan TEXT NOT NULL,
            pembahasan TEXT,
            poin INTEGER DEFAULT 4,
            urutan INTEGER DEFAULT 0,
            data_tambahan JSONB
        );

        CREATE TABLE IF NOT EXISTS opsi_jawaban (
            id SERIAL PRIMARY KEY,
            id_soal INTEGER REFERENCES soal(id) ON DELETE CASCADE,
            teks_opsi TEXT NOT NULL,
            benar BOOLEAN DEFAULT FALSE,
            urutan INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS sesi_ujian (
            id SERIAL PRIMARY KEY,
            id_ujian INTEGER REFERENCES ujian(id) ON DELETE CASCADE,
            id_mahasiswa INTEGER REFERENCES mahasiswa(id) ON DELETE CASCADE,
            waktu_mulai TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            waktu_selesai TIMESTAMP,
            nilai_total INTEGER DEFAULT 0,
            status VARCHAR(20) DEFAULT 'berlangsung',
            shuffled_options JSONB
        );

        CREATE TABLE IF NOT EXISTS jawaban_peserta (
            id SERIAL PRIMARY KEY,
            id_sesi INTEGER REFERENCES sesi_ujian(id) ON DELETE CASCADE,
            id_soal INTEGER REFERENCES soal(id) ON DELETE CASCADE,
            jawaban TEXT,
            nilai INTEGER DEFAULT 0,
            is_correct BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nama_lengkap VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'dosen',
            id_program_studi INTEGER REFERENCES program_studi(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS nim VARCHAR(50)");
    $pdo->exec("ALTER TABLE soal ADD COLUMN IF NOT EXISTS indikator TEXT");
    $pdo->exec("ALTER TABLE sesi_ujian ADD COLUMN IF NOT EXISTS jawaban_draft JSONB");
    $pdo->exec("ALTER TABLE sesi_ujian ADD COLUMN IF NOT EXISTS soal_terakhir INTEGER DEFAULT 1");
}

function seedAdminUser($pdo) {
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count > 0) return;
    
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', $password, 'Administrator', 'admin']);
    
    $password2 = password_hash('dosen123', PASSWORD_DEFAULT);
    $stmt->execute(['dosen1', $password2, 'Dr. Budi Santoso', 'dosen']);
}

function seedFakultas($pdo) {
    $count = $pdo->query("SELECT COUNT(*) FROM fakultas")->fetchColumn();
    if ($count > 0) return;
    
    $pdo->exec("
        INSERT INTO fakultas (kode_fakultas, nama_fakultas) VALUES
        ('FT', 'Fakultas Teknik'),
        ('FIP', 'Fakultas Ilmu Pendidikan'),
        ('FBSH', 'Fakultas Bahasa, Seni, dan Humaniora'),
        ('FISE', 'Fakultas Ilmu Sosial dan Ekonomi'),
        ('FMIPA', 'Fakultas Matematika dan Ilmu Pengetahuan Alam'),
        ('FKes', 'Fakultas Kesehatan'),
        ('FK', 'Fakultas Kedokteran'),
        ('PPs', 'Program Pascasarjana')
    ");
}

function seedMasterData($pdo) {
    $count = $pdo->query("SELECT COUNT(*) FROM program_studi")->fetchColumn();
    if ($count > 0) return;
    
    $pdo->exec("
        INSERT INTO program_studi (kode_prodi, nama_prodi, id_fakultas) 
        SELECT 'SI', 'S1 Sistem Informasi', id FROM fakultas WHERE kode_fakultas = 'FT'
        UNION ALL SELECT 'TI', 'S1 Teknik Informatika', id FROM fakultas WHERE kode_fakultas = 'FT'
        UNION ALL SELECT 'TK', 'S1 Teknik Komputer', id FROM fakultas WHERE kode_fakultas = 'FT'
        UNION ALL SELECT 'TL', 'S1 Teknik Lingkungan', id FROM fakultas WHERE kode_fakultas = 'FT'
        UNION ALL SELECT 'BK', 'S1 Bimbingan dan Konseling', id FROM fakultas WHERE kode_fakultas = 'FIP'
        UNION ALL SELECT 'PGSD', 'S1 Pendidikan Guru Sekolah Dasar', id FROM fakultas WHERE kode_fakultas = 'FIP'
        UNION ALL SELECT 'PGPAUD', 'S1 Pendidikan Guru Pendidikan Anak Usia Dini', id FROM fakultas WHERE kode_fakultas = 'FIP'
        UNION ALL SELECT 'PJKR', 'S1 Pendidikan Jasmani, Kesehatan, dan Rekreasi', id FROM fakultas WHERE kode_fakultas = 'FIP'
        UNION ALL SELECT 'PKh', 'S1 Pendidikan Khusus', id FROM fakultas WHERE kode_fakultas = 'FIP'
        UNION ALL SELECT 'PBINDO', 'S1 Pendidikan Bahasa Indonesia', id FROM fakultas WHERE kode_fakultas = 'FBSH'
        UNION ALL SELECT 'PBING', 'S1 Pendidikan Bahasa Inggris', id FROM fakultas WHERE kode_fakultas = 'FBSH'
        UNION ALL SELECT 'SENDRATASIK', 'S1 Pendidikan Sendratasik', id FROM fakultas WHERE kode_fakultas = 'FBSH'
        UNION ALL SELECT 'PAR', 'S1 Pariwisata', id FROM fakultas WHERE kode_fakultas = 'FBSH'
        UNION ALL SELECT 'PEKO', 'S1 Pendidikan Ekonomi', id FROM fakultas WHERE kode_fakultas = 'FISE'
        UNION ALL SELECT 'PGEO', 'S1 Pendidikan Geografi', id FROM fakultas WHERE kode_fakultas = 'FISE'
        UNION ALL SELECT 'PSEJ', 'S1 Pendidikan Sejarah', id FROM fakultas WHERE kode_fakultas = 'FISE'
        UNION ALL SELECT 'PSOS', 'S1 Pendidikan Sosiologi', id FROM fakultas WHERE kode_fakultas = 'FISE'
        UNION ALL SELECT 'PMAT', 'S1 Pendidikan Matematika', id FROM fakultas WHERE kode_fakultas = 'FMIPA'
        UNION ALL SELECT 'PBIO', 'S1 Pendidikan Biologi', id FROM fakultas WHERE kode_fakultas = 'FMIPA'
        UNION ALL SELECT 'PFIS', 'S1 Pendidikan Fisika', id FROM fakultas WHERE kode_fakultas = 'FMIPA'
        UNION ALL SELECT 'STAT', 'S1 Statistika', id FROM fakultas WHERE kode_fakultas = 'FMIPA'
        UNION ALL SELECT 'PIPA', 'S1 Pendidikan IPA', id FROM fakultas WHERE kode_fakultas = 'FMIPA'
        UNION ALL SELECT 'FARM', 'S1 Farmasi', id FROM fakultas WHERE kode_fakultas = 'FKes'
        UNION ALL SELECT 'KEP', 'S1 Keperawatan', id FROM fakultas WHERE kode_fakultas = 'FKes'
        UNION ALL SELECT 'KEB', 'D3 Kebidanan', id FROM fakultas WHERE kode_fakultas = 'FKes'
        UNION ALL SELECT 'MPD', 'S2 Magister Pendidikan Dasar', id FROM fakultas WHERE kode_fakultas = 'PPs'
        UNION ALL SELECT 'MMP', 'S2 Magister Manajemen Pendidikan', id FROM fakultas WHERE kode_fakultas = 'PPs';

        INSERT INTO mata_kuliah (kode_mk, nama_mk, sks) VALUES
        ('EP101', 'Etika Profesi', 2),
        ('PBO101', 'Pemrograman Berorientasi Objek', 3),
        ('BD101', 'Basis Data', 3),
        ('JK101', 'Jaringan Komputer', 3),
        ('SI101', 'Sistem Informasi', 3);
    ");
}

function seedUjianEtikaProfesi($pdo) {
    $count = $pdo->query("SELECT COUNT(*) FROM ujian")->fetchColumn();
    if ($count > 0) return;

    $stmt = $pdo->query("SELECT id FROM mata_kuliah WHERE kode_mk = 'EP101'");
    $matkul = $stmt->fetch();
    if (!$matkul) return;

    $pdo->prepare("INSERT INTO ujian (judul_ujian, id_mata_kuliah, jenis_ujian, durasi_menit, total_nilai) VALUES (?, ?, ?, ?, ?)")
        ->execute(['Ujian Tengah Semester - Etika Profesi', $matkul['id'], 'UTS', 90, 100]);
    
    $ujianId = $pdo->lastInsertId();

    $kelasIds = $pdo->query("SELECT id FROM kelas WHERE nama_kelas LIKE 'TI-%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($kelasIds as $kelasId) {
        $pdo->prepare("INSERT INTO ujian_kelas (id_ujian, id_kelas) VALUES (?, ?)")->execute([$ujianId, $kelasId]);
    }

    $soalPG = [
        ['Apa yang dimaksud dengan etika profesi?', 'Etika profesi adalah seperangkat nilai, norma, dan prinsip moral yang mengatur perilaku profesional.', 4, [
            ['Aturan main dalam permainan', false],
            ['Seperangkat nilai dan norma yang mengatur perilaku profesional dalam menjalankan tugasnya', true],
            ['Hukum yang berlaku di Indonesia', false],
            ['Peraturan perusahaan', false],
            ['Standar gaji karyawan', false]
        ]],
        ['Salah satu tujuan etika profesi adalah...', 'Tujuan utama etika profesi adalah menjaga martabat, kehormatan, dan integritas profesi.', 4, [
            ['Meningkatkan keuntungan perusahaan', false],
            ['Menjaga martabat dan kehormatan profesi', true],
            ['Mengurangi beban kerja', false],
            ['Meningkatkan gaji karyawan', false],
            ['Menghindari pajak', false]
        ]],
        ['Kode etik profesi berfungsi sebagai...', 'Kode etik profesi berfungsi sebagai pedoman dan acuan perilaku bagi para profesional.', 4, [
            ['Alat untuk menghukum', false],
            ['Pedoman perilaku profesional', true],
            ['Aturan untuk menaikkan gaji', false],
            ['Cara untuk menghindari pekerjaan', false],
            ['Standar promosi jabatan', false]
        ]],
        ['Prinsip dasar etika profesi yang mengutamakan kepentingan klien adalah...', 'Altruisme adalah prinsip mengutamakan kepentingan orang lain (klien) di atas kepentingan pribadi.', 4, [
            ['Egoisme', false],
            ['Altruisme', true],
            ['Hedonisme', false],
            ['Nihilisme', false],
            ['Pragmatisme', false]
        ]],
        ['Integritas dalam profesi berarti...', 'Integritas adalah kualitas moral yang mencakup kejujuran, konsistensi antara perkataan dan perbuatan.', 4, [
            ['Kemampuan teknis yang tinggi', false],
            ['Kejujuran dan konsistensi dalam tindakan', true],
            ['Kemampuan berkomunikasi', false],
            ['Kecepatan dalam bekerja', false],
            ['Kemampuan memimpin', false]
        ]],
        ['Konflik kepentingan dalam profesi terjadi ketika...', 'Konflik kepentingan terjadi ketika kepentingan pribadi mempengaruhi objektivitas tugas profesional.', 4, [
            ['Ada perbedaan pendapat dengan rekan', false],
            ['Kepentingan pribadi bertentangan dengan tugas profesional', true],
            ['Terjadi persaingan antar perusahaan', false],
            ['Ada deadline yang ketat', false],
            ['Terjadi kenaikan harga', false]
        ]],
        ['Sanksi pelanggaran etika profesi dapat berupa...', 'Sanksi pelanggaran etika bervariasi mulai dari teguran hingga pencabutan izin praktik.', 4, [
            ['Hanya teguran lisan', false],
            ['Teguran hingga pencabutan izin praktik', true],
            ['Tidak ada sanksi', false],
            ['Hanya denda uang', false],
            ['Hanya penurunan jabatan', false]
        ]],
        ['Profesionalisme ditunjukkan melalui...', 'Profesionalisme tercermin dari kompetensi teknis, integritas moral, dan dedikasi.', 4, [
            ['Pakaian yang mahal', false],
            ['Kompetensi, integritas, dan dedikasi', true],
            ['Jabatan yang tinggi', false],
            ['Gaji yang besar', false],
            ['Kantor yang mewah', false]
        ]]
    ];

    $soalTF = [
        ['Etika profesi hanya berlaku untuk dokter dan pengacara.', 'Etika profesi berlaku untuk semua jenis profesi, tidak hanya dokter dan pengacara.', 4, false],
        ['Kode etik profesi bersifat mengikat bagi anggota profesi tersebut.', 'Kode etik profesi memang bersifat mengikat bagi seluruh anggota profesi.', 4, true],
        ['Whistle blowing adalah tindakan yang tidak etis dalam dunia kerja.', 'Whistle blowing justru merupakan tindakan etis untuk melaporkan pelanggaran.', 4, false],
        ['Konflik kepentingan harus dihindari dalam menjalankan tugas profesional.', 'Benar, konflik kepentingan dapat mengganggu objektivitas profesional.', 4, true]
    ];

    $soalShort = [
        ['Sebutkan prinsip etika yang berarti "tidak merugikan orang lain"!', 'Non-maleficence adalah prinsip untuk tidak melakukan tindakan yang merugikan.', 4, 'non-maleficence,nonmaleficence,non maleficence'],
        ['Apa istilah untuk pengembangan profesional yang dilakukan secara berkelanjutan?', 'CPD adalah singkatan dari Continuous Professional Development.', 4, 'cpd,continuous professional development'],
        ['Sebutkan istilah untuk melaporkan pelanggaran atau kecurangan di organisasi!', 'Whistle blowing adalah tindakan melaporkan pelanggaran di organisasi.', 4, 'whistle blowing,whistleblowing,whistle-blowing']
    ];

    $soalMultipleAnswer = [
        ['Pilih yang termasuk prinsip dasar etika profesi! (Pilih semua yang benar)', 'Prinsip dasar etika profesi meliputi integritas, akuntabilitas, dan objektivitas.', 5, [
            ['Integritas', true],
            ['Korupsi', false],
            ['Akuntabilitas', true],
            ['Nepotisme', false],
            ['Objektivitas', true]
        ]],
        ['Manakah yang merupakan tanggung jawab seorang profesional? (Pilih semua yang benar)', 'Profesional bertanggung jawab kepada klien, profesi, dan masyarakat.', 5, [
            ['Tanggung jawab kepada klien', true],
            ['Tanggung jawab kepada profesi', true],
            ['Tanggung jawab kepada masyarakat', true],
            ['Tanggung jawab untuk memperkaya diri', false]
        ]]
    ];

    $soalMatching = [
        ['Jodohkan istilah etika dengan definisinya!', 'Menjodohkan istilah dengan definisi yang tepat.', 5, [
            'Beneficence' => 'Berbuat baik untuk kepentingan klien',
            'Non-maleficence' => 'Tidak merugikan orang lain',
            'Autonomy' => 'Menghormati hak otonomi individu',
            'Justice' => 'Berlaku adil kepada semua pihak'
        ]]
    ];

    $soalOrdering = [
        ['Urutkan langkah penyelesaian dilema etika dari awal hingga akhir!', 'Langkah menyelesaikan dilema etika secara sistematis.', 5, [
            'Identifikasi masalah etika',
            'Kumpulkan informasi yang relevan',
            'Analisis alternatif solusi',
            'Evaluasi konsekuensi setiap alternatif',
            'Ambil keputusan',
            'Evaluasi hasil keputusan'
        ]]
    ];

    $soalEsai = [
        ['Jelaskan mengapa etika profesi penting dalam dunia kerja modern! Berikan contoh penerapannya.', 'Etika profesi penting karena membangun kepercayaan publik, menjaga integritas, dan memastikan tanggung jawab profesional.', 10, 'kepercayaan,integritas,profesional,tanggung jawab,masyarakat'],
        ['Bagaimana cara mengatasi dilema etika dalam situasi kerja? Jelaskan langkah-langkahnya!', 'Langkah: identifikasi masalah, analisis nilai, pertimbangkan alternatif, evaluasi konsekuensi, ambil keputusan.', 10, 'identifikasi,analisis,alternatif,konsekuensi,keputusan,evaluasi']
    ];

    $urutan = 1;

    foreach ($soalPG as $s) {
        $stmt = $pdo->prepare("INSERT INTO soal (id_ujian, jenis_soal, pertanyaan, pembahasan, poin, urutan) VALUES (?, ?, ?, ?, ?, ?) RETURNING id");
        $stmt->execute([$ujianId, 'pg', $s[0], $s[1], $s[2], $urutan++]);
        $soalId = $stmt->fetch()['id'];
        
        $opsiUrutan = 1;
        foreach ($s[3] as $opsi) {
            $benar = $opsi[1] ? 't' : 'f';
            $pdo->prepare("INSERT INTO opsi_jawaban (id_soal, teks_opsi, benar, urutan) VALUES (?, ?, ?, ?)")
                ->execute([$soalId, $opsi[0], $benar, $opsiUrutan++]);
        }
    }

    foreach ($soalTF as $s) {
        $stmt = $pdo->prepare("INSERT INTO soal (id_ujian, jenis_soal, pertanyaan, pembahasan, poin, urutan, data_tambahan) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id");
        $stmt->execute([$ujianId, 'tf', $s[0], $s[1], $s[2], $urutan++, json_encode(['jawaban_benar' => $s[3]])]);
    }

    foreach ($soalShort as $s) {
        $stmt = $pdo->prepare("INSERT INTO soal (id_ujian, jenis_soal, pertanyaan, pembahasan, poin, urutan, data_tambahan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ujianId, 'short', $s[0], $s[1], $s[2], $urutan++, json_encode(['keywords' => $s[3]])]);
    }

    foreach ($soalMultipleAnswer as $s) {
        $stmt = $pdo->prepare("INSERT INTO soal (id_ujian, jenis_soal, pertanyaan, pembahasan, poin, urutan) VALUES (?, ?, ?, ?, ?, ?) RETURNING id");
        $stmt->execute([$ujianId, 'multiple', $s[0], $s[1], $s[2], $urutan++]);
        $soalId = $stmt->fetch()['id'];
        
        $opsiUrutan = 1;
        foreach ($s[3] as $opsi) {
            $benar = $opsi[1] ? 't' : 'f';
            $pdo->prepare("INSERT INTO opsi_jawaban (id_soal, teks_opsi, benar, urutan) VALUES (?, ?, ?, ?)")
                ->execute([$soalId, $opsi[0], $benar, $opsiUrutan++]);
        }
    }

    foreach ($soalMatching as $s) {
        $stmt = $pdo->prepare("INSERT INTO soal (id_ujian, jenis_soal, pertanyaan, pembahasan, poin, urutan, data_tambahan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ujianId, 'matching', $s[0], $s[1], $s[2], $urutan++, json_encode(['pairs' => $s[3]])]);
    }

    foreach ($soalOrdering as $s) {
        $stmt = $pdo->prepare("INSERT INTO soal (id_ujian, jenis_soal, pertanyaan, pembahasan, poin, urutan, data_tambahan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ujianId, 'ordering', $s[0], $s[1], $s[2], $urutan++, json_encode(['correct_order' => $s[3]])]);
    }

    foreach ($soalEsai as $s) {
        $stmt = $pdo->prepare("INSERT INTO soal (id_ujian, jenis_soal, pertanyaan, pembahasan, poin, urutan, data_tambahan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ujianId, 'esai', $s[0], $s[1], $s[2], $urutan++, json_encode(['keywords' => $s[3]])]);
    }
}

function getProdi($pdo) {
    return $pdo->query("SELECT * FROM program_studi ORDER BY nama_prodi")->fetchAll();
}

function getKelasByProdi($pdo, $prodiId) {
    $stmt = $pdo->prepare("SELECT * FROM kelas WHERE id_program_studi = ? ORDER BY angkatan DESC, nama_kelas");
    $stmt->execute([$prodiId]);
    return $stmt->fetchAll();
}

function getUjianAktifByKelas($pdo, $kelasId) {
    $stmt = $pdo->prepare("
        SELECT u.*, mk.nama_mk, mk.kode_mk,
               (SELECT SUM(poin) FROM soal WHERE id_ujian = u.id) as total_nilai
        FROM ujian u 
        JOIN ujian_kelas uk ON u.id = uk.id_ujian 
        JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id
        WHERE uk.id_kelas = ? AND u.aktif = TRUE
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$kelasId]);
    return $stmt->fetchAll();
}

function getSoalByUjian($pdo, $ujianId) {
    $stmt = $pdo->prepare("SELECT * FROM soal WHERE id_ujian = ? ORDER BY urutan");
    $stmt->execute([$ujianId]);
    $soalList = $stmt->fetchAll();
    
    foreach ($soalList as &$soal) {
        if (in_array($soal['jenis_soal'], ['pg', 'multiple'])) {
            $stmtOpsi = $pdo->prepare("SELECT * FROM opsi_jawaban WHERE id_soal = ? ORDER BY urutan");
            $stmtOpsi->execute([$soal['id']]);
            $soal['opsi'] = $stmtOpsi->fetchAll();
        }
        if ($soal['data_tambahan']) {
            $soal['data_tambahan'] = json_decode($soal['data_tambahan'], true);
        }
    }
    
    return $soalList;
}

function getOrCreateMahasiswa($pdo, $nim, $nama, $kelasId, $userId = null) {
    // Jika ada akun user terdaftar, cari mahasiswa berdasarkan id_user
    // agar tidak bertabrakan dengan NIM milik peserta lain
    if ($userId) {
        $stmt = $pdo->prepare("SELECT * FROM mahasiswa WHERE id_user = ?");
        $stmt->execute([$userId]);
        $mhs = $stmt->fetch();

        if ($mhs) {
            // Update nama, nim, dan kelas jika berbeda
            $pdo->prepare("UPDATE mahasiswa SET nim = ?, nama_lengkap = ?, id_kelas = ? WHERE id = ?")
                ->execute([$nim, $nama, $kelasId, $mhs['id']]);
            return $mhs['id'];
        }

        // Buat record mahasiswa baru dan tautkan ke akun user
        $stmt = $pdo->prepare("INSERT INTO mahasiswa (nim, nama_lengkap, id_kelas, id_user) VALUES (?, ?, ?, ?) RETURNING id");
        $stmt->execute([$nim, $nama, $kelasId, $userId]);
        return $stmt->fetch()['id'];
    }

    // Fallback: cari berdasarkan NIM (untuk peserta tanpa akun)
    $stmt = $pdo->prepare("SELECT * FROM mahasiswa WHERE nim = ?");
    $stmt->execute([$nim]);
    $mhs = $stmt->fetch();

    if ($mhs) {
        return $mhs['id'];
    }

    $stmt = $pdo->prepare("INSERT INTO mahasiswa (nim, nama_lengkap, id_kelas) VALUES (?, ?, ?) RETURNING id");
    $stmt->execute([$nim, $nama, $kelasId]);
    return $stmt->fetch()['id'];
}

/**
 * Auto-expire sessions whose time limit has passed.
 * Joins with ujian to get the real duration so no session variable is needed.
 * Call this at every entry-point (admin pages, peserta login, index).
 */
function cleanupExpiredSessions($pdo) {
    $pdo->exec("
        UPDATE sesi_ujian su
        SET    status        = 'timeout',
               waktu_selesai = NOW()
        FROM   ujian u
        WHERE  su.id_ujian  = u.id
          AND  su.status    = 'berlangsung'
          AND  su.waktu_mulai + (u.durasi_menit * INTERVAL '1 minute') + INTERVAL '2 minutes' < NOW()
    ");
}

function checkExistingSesi($pdo, $ujianId, $mahasiswaId) {
    // Block if already finished (selesai) OR timed-out (timeout)
    $stmt = $pdo->prepare("SELECT * FROM sesi_ujian WHERE id_ujian = ? AND id_mahasiswa = ? AND status IN ('selesai', 'timeout') ORDER BY waktu_mulai DESC LIMIT 1");
    $stmt->execute([$ujianId, $mahasiswaId]);
    return $stmt->fetch();
}

function getActiveSesi($pdo, $ujianId, $mahasiswaId) {
    $stmt = $pdo->prepare("SELECT * FROM sesi_ujian WHERE id_ujian = ? AND id_mahasiswa = ? AND status = 'berlangsung' ORDER BY waktu_mulai DESC LIMIT 1");
    $stmt->execute([$ujianId, $mahasiswaId]);
    return $stmt->fetch();
}

function getActiveSeziByUserId($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT su.*, u.judul_ujian, u.durasi_menit,
               mk.nama_mk,
               m.nim, m.nama_lengkap, m.id_kelas,
               ps.nama_prodi,
               k.nama_kelas, k.semester
        FROM sesi_ujian su
        JOIN ujian u ON u.id = su.id_ujian
        JOIN mata_kuliah mk ON mk.id = u.id_mata_kuliah
        JOIN mahasiswa m ON m.id = su.id_mahasiswa
        JOIN kelas k ON k.id = m.id_kelas
        JOIN program_studi ps ON ps.id = k.id_program_studi
        WHERE m.id_user = ? AND su.status = 'berlangsung'
        ORDER BY su.waktu_mulai DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function shuffleOptionsForSesi($soalList, $sesiId) {
    $shuffled = [];
    foreach ($soalList as $soal) {
        if (in_array($soal['jenis_soal'], ['pg', 'multiple']) && isset($soal['opsi'])) {
            $opsi = $soal['opsi'];
            $originalOrder = array_column($opsi, 'id');
            shuffle($opsi);
            $newOrder = array_column($opsi, 'id');
            
            $mapping = [];
            foreach ($newOrder as $idx => $id) {
                $mapping[$idx] = array_search($id, $originalOrder);
            }
            
            $shuffled[$soal['id']] = [
                'order' => $newOrder,
                'mapping' => $mapping
            ];
        } elseif ($soal['jenis_soal'] === 'matching' && isset($soal['data_tambahan']['pairs'])) {
            $pairs = $soal['data_tambahan']['pairs'];
            $values = array_values($pairs);
            shuffle($values);
            $shuffled[$soal['id']] = [
                'shuffled_values' => $values
            ];
        } elseif ($soal['jenis_soal'] === 'ordering' && isset($soal['data_tambahan']['correct_order'])) {
            $items = $soal['data_tambahan']['correct_order'];
            $shuffledItems = $items;
            shuffle($shuffledItems);
            $shuffled[$soal['id']] = [
                'display_order' => $shuffledItems
            ];
        }
    }
    return $shuffled;
}

function calculateScoreNew($pdo, $sesiId, $answers, $shuffledOptions, $soalList) {
    $stmt = $pdo->prepare("DELETE FROM jawaban_peserta WHERE id_sesi = ?");
    $stmt->execute([$sesiId]);
    
    $totalScore = 0;
    $results = [];
    
    foreach ($soalList as $soal) {
        $jawaban = isset($answers[$soal['id']]) ? $answers[$soal['id']] : '';
        $isCorrect = false;
        $nilai = 0;
        
        switch ($soal['jenis_soal']) {
            case 'pg':
                if (!empty($jawaban) && isset($soal['opsi'])) {
                    $correctOpsi = null;
                    foreach ($soal['opsi'] as $opsi) {
                        if ($opsi['benar']) {
                            $correctOpsi = $opsi['id'];
                            break;
                        }
                    }
                    if ((int)$jawaban === (int)$correctOpsi) {
                        $isCorrect = true;
                        $nilai = $soal['poin'];
                    }
                }
                break;
                
            case 'tf':
                if ($jawaban !== '') {
                    $correctAnswer = $soal['data_tambahan']['jawaban_benar'] ?? false;
                    $userAnswer = ($jawaban === 'true' || $jawaban === '1');
                    if ($userAnswer === $correctAnswer) {
                        $isCorrect = true;
                        $nilai = $soal['poin'];
                    }
                }
                break;
                
            case 'short':
                if (!empty($jawaban)) {
                    $keywords = explode(',', $soal['data_tambahan']['keywords'] ?? '');
                    $jawabanLower = strtolower(trim($jawaban));
                    foreach ($keywords as $keyword) {
                        if (strtolower(trim($keyword)) === $jawabanLower || strpos($jawabanLower, strtolower(trim($keyword))) !== false) {
                            $isCorrect = true;
                            $nilai = $soal['poin'];
                            break;
                        }
                    }
                }
                break;
                
            case 'multiple':
                if (!empty($jawaban) && isset($soal['opsi'])) {
                    $selectedIds = is_array($jawaban) ? $jawaban : explode(',', $jawaban);
                    $selectedIds = array_map('intval', $selectedIds);
                    
                    $correctIds = [];
                    foreach ($soal['opsi'] as $opsi) {
                        if ($opsi['benar']) {
                            $correctIds[] = (int)$opsi['id'];
                        }
                    }
                    
                    sort($selectedIds);
                    sort($correctIds);
                    
                    if ($selectedIds === $correctIds) {
                        $isCorrect = true;
                        $nilai = $soal['poin'];
                    } else {
                        $correctCount = count(array_intersect($selectedIds, $correctIds));
                        $wrongCount = count(array_diff($selectedIds, $correctIds));
                        $partialScore = max(0, ($correctCount - $wrongCount) / count($correctIds));
                        $nilai = round($partialScore * $soal['poin']);
                        $isCorrect = $partialScore >= 0.5;
                    }
                }
                break;
                
            case 'matching':
                if (!empty($jawaban)) {
                    $userMatches = is_array($jawaban) ? $jawaban : json_decode($jawaban, true);
                    $correctPairs = $soal['data_tambahan']['pairs'] ?? [];
                    
                    if (is_array($userMatches) && is_array($correctPairs)) {
                        $correctCount = 0;
                        foreach ($userMatches as $key => $value) {
                            if (isset($correctPairs[$key]) && $correctPairs[$key] === $value) {
                                $correctCount++;
                            }
                        }
                        $totalPairs = count($correctPairs);
                        if ($totalPairs > 0) {
                            $nilai = round(($correctCount / $totalPairs) * $soal['poin']);
                            $isCorrect = $correctCount === $totalPairs;
                        }
                    }
                }
                break;
                
            case 'ordering':
                if (!empty($jawaban)) {
                    $userOrder = is_array($jawaban) ? $jawaban : json_decode($jawaban, true);
                    $correctOrder = $soal['data_tambahan']['correct_order'] ?? [];
                    
                    if (is_array($userOrder) && $userOrder === $correctOrder) {
                        $isCorrect = true;
                        $nilai = $soal['poin'];
                    } else {
                        $correctPositions = 0;
                        foreach ($userOrder as $idx => $item) {
                            if (isset($correctOrder[$idx]) && $correctOrder[$idx] === $item) {
                                $correctPositions++;
                            }
                        }
                        $totalItems = count($correctOrder);
                        if ($totalItems > 0) {
                            $nilai = round(($correctPositions / $totalItems) * $soal['poin']);
                            $isCorrect = $correctPositions === $totalItems;
                        }
                    }
                }
                break;
                
            case 'esai':
                if (!empty($jawaban)) {
                    $keywords = array_map('trim', explode(',', strtolower($soal['data_tambahan']['keywords'] ?? '')));
                    $jawabanLower = strtolower($jawaban);
                    $matchedKeywords = 0;
                    foreach ($keywords as $keyword) {
                        if (!empty($keyword) && strpos($jawabanLower, $keyword) !== false) {
                            $matchedKeywords++;
                        }
                    }
                    $keywordCount = count(array_filter($keywords));
                    if ($keywordCount > 0) {
                        $percentage = $matchedKeywords / $keywordCount;
                        $nilai = round($percentage * $soal['poin']);
                        $isCorrect = $percentage >= 0.5;
                    }
                }
                break;
        }
        
        $totalScore += $nilai;
        $results[$soal['id']] = [
            'jawaban' => is_array($jawaban) ? json_encode($jawaban) : $jawaban,
            'nilai' => $nilai,
            'is_correct' => $isCorrect
        ];
        
        $jawabanStr = is_array($jawaban) ? json_encode($jawaban) : $jawaban;
        $isCorrectDb = $isCorrect ? 't' : 'f';
        $stmt = $pdo->prepare("INSERT INTO jawaban_peserta (id_sesi, id_soal, jawaban, nilai, is_correct) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sesiId, $soal['id'], $jawabanStr, $nilai, $isCorrectDb]);
    }
    
    return [
        'total' => $totalScore,
        'details' => $results
    ];
}
