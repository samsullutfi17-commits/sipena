<?php

function initDatabase($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS exam_sessions (
            id SERIAL PRIMARY KEY,
            nama_peserta VARCHAR(255) NOT NULL,
            npm VARCHAR(50) NOT NULL,
            waktu_mulai TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            waktu_selesai TIMESTAMP,
            nilai_pg INTEGER DEFAULT 0,
            nilai_esai INTEGER DEFAULT 0,
            nilai_total INTEGER DEFAULT 0,
            status_selesai BOOLEAN DEFAULT FALSE,
            shuffled_options JSONB
        );
        
        CREATE TABLE IF NOT EXISTS exam_answers (
            id SERIAL PRIMARY KEY,
            session_id INTEGER REFERENCES exam_sessions(id) ON DELETE CASCADE,
            soal_id INTEGER NOT NULL,
            jawaban_peserta TEXT,
            is_correct BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS soal (
            id SERIAL PRIMARY KEY,
            tipe VARCHAR(10) NOT NULL,
            pertanyaan TEXT NOT NULL,
            opsi_a TEXT,
            opsi_b TEXT,
            opsi_c TEXT,
            opsi_d TEXT,
            opsi_e TEXT,
            jawaban_benar VARCHAR(1),
            pembahasan TEXT,
            poin_kunci TEXT
        );
    ");
}

function getSoal($pdo) {
    $stmt = $pdo->query("SELECT * FROM soal ORDER BY id");
    return $stmt->fetchAll();
}

function shuffleOptions($soal, $sessionId) {
    $shuffled = [];
    foreach ($soal as $s) {
        if ($s['tipe'] === 'pg') {
            $options = [
                'A' => $s['opsi_a'],
                'B' => $s['opsi_b'],
                'C' => $s['opsi_c'],
                'D' => $s['opsi_d'],
                'E' => $s['opsi_e']
            ];
            $keys = array_keys($options);
            shuffle($keys);
            $shuffledOptions = [];
            $mapping = [];
            $newLabels = ['A', 'B', 'C', 'D', 'E'];
            foreach ($keys as $idx => $originalKey) {
                $shuffledOptions[$newLabels[$idx]] = $options[$originalKey];
                $mapping[$newLabels[$idx]] = $originalKey;
            }
            $shuffled[$s['id']] = [
                'options' => $shuffledOptions,
                'mapping' => $mapping
            ];
        }
    }
    return $shuffled;
}

function calculateScore($pdo, $sessionId, $answers, $shuffledOptions) {
    $stmt = $pdo->prepare("DELETE FROM exam_answers WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    
    $soal = getSoal($pdo);
    $pgScore = 0;
    $essayScore = 0;
    $results = [];
    
    foreach ($soal as $s) {
        $jawaban = isset($answers[$s['id']]) ? $answers[$s['id']] : '';
        $isCorrect = false;
        
        if ($s['tipe'] === 'pg' && !empty($jawaban)) {
            if (isset($shuffledOptions[$s['id']]['mapping'][$jawaban])) {
                $originalAnswer = $shuffledOptions[$s['id']]['mapping'][$jawaban];
                if ($originalAnswer === $s['jawaban_benar']) {
                    $isCorrect = true;
                    $pgScore += PG_POINTS;
                }
            }
        } elseif ($s['tipe'] === 'esai' && !empty($jawaban)) {
            $keywords = array_map('trim', explode(',', strtolower($s['poin_kunci'] ?? '')));
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
                $essayScore += round($percentage * ESSAY_POINTS);
                $isCorrect = $percentage >= 0.5;
            }
        }
        
        $results[$s['id']] = [
            'jawaban' => $jawaban,
            'is_correct' => $isCorrect
        ];
        
        $stmt = $pdo->prepare("INSERT INTO exam_answers (session_id, soal_id, jawaban_peserta, is_correct) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sessionId, $s['id'], $jawaban, $isCorrect]);
    }
    
    return [
        'pg' => $pgScore,
        'esai' => $essayScore,
        'total' => $pgScore + $essayScore,
        'details' => $results
    ];
}

function seedSoal($pdo) {
    $count = $pdo->query("SELECT COUNT(*) FROM soal")->fetchColumn();
    if ($count > 0) return;
    
    $soalPG = [
        ['Apa yang dimaksud dengan etika profesi?', 'Aturan main dalam permainan', 'Seperangkat nilai dan norma yang mengatur perilaku profesional dalam menjalankan tugasnya', 'Hukum yang berlaku di Indonesia', 'Peraturan perusahaan', 'Standar gaji karyawan', 'B', 'Etika profesi adalah seperangkat nilai, norma, dan prinsip moral yang mengatur perilaku profesional dalam menjalankan tugas dan tanggung jawabnya.'],
        ['Salah satu tujuan etika profesi adalah...', 'Meningkatkan keuntungan perusahaan', 'Menjaga martabat dan kehormatan profesi', 'Mengurangi beban kerja', 'Meningkatkan gaji karyawan', 'Menghindari pajak', 'B', 'Tujuan utama etika profesi adalah menjaga martabat, kehormatan, dan integritas profesi di mata masyarakat.'],
        ['Kode etik profesi berfungsi sebagai...', 'Alat untuk menghukum', 'Pedoman perilaku profesional', 'Aturan untuk menaikkan gaji', 'Cara untuk menghindari pekerjaan', 'Standar promosi jabatan', 'B', 'Kode etik profesi berfungsi sebagai pedoman dan acuan perilaku bagi para profesional dalam menjalankan tugasnya.'],
        ['Prinsip dasar etika profesi yang mengutamakan kepentingan klien adalah...', 'Egoisme', 'Altruisme', 'Hedonisme', 'Nihilisme', 'Pragmatisme', 'B', 'Altruisme adalah prinsip mengutamakan kepentingan orang lain (klien) di atas kepentingan pribadi.'],
        ['Tanggung jawab profesional meliputi...', 'Hanya terhadap atasan', 'Terhadap profesi, klien, dan masyarakat', 'Hanya terhadap diri sendiri', 'Hanya terhadap rekan kerja', 'Tidak ada tanggung jawab', 'B', 'Tanggung jawab profesional bersifat komprehensif, mencakup tanggung jawab terhadap profesi, klien, dan masyarakat luas.'],
        ['Integritas dalam profesi berarti...', 'Kemampuan teknis yang tinggi', 'Kejujuran dan konsistensi dalam tindakan', 'Kemampuan berkomunikasi', 'Kecepatan dalam bekerja', 'Kemampuan memimpin', 'B', 'Integritas adalah kualitas moral yang mencakup kejujuran, konsistensi antara perkataan dan perbuatan.'],
        ['Konflik kepentingan dalam profesi terjadi ketika...', 'Ada perbedaan pendapat dengan rekan', 'Kepentingan pribadi bertentangan dengan tugas profesional', 'Terjadi persaingan antar perusahaan', 'Ada deadline yang ketat', 'Terjadi kenaikan harga', 'B', 'Konflik kepentingan terjadi ketika kepentingan pribadi atau pihak lain mempengaruhi objektivitas dalam menjalankan tugas profesional.'],
        ['Sanksi pelanggaran etika profesi dapat berupa...', 'Hanya teguran lisan', 'Teguran hingga pencabutan izin praktik', 'Tidak ada sanksi', 'Hanya denda uang', 'Hanya penurunan jabatan', 'B', 'Sanksi pelanggaran etika bervariasi mulai dari teguran lisan, tertulis, skorsing, hingga pencabutan izin praktik.'],
        ['Profesionalisme ditunjukkan melalui...', 'Pakaian yang mahal', 'Kompetensi, integritas, dan dedikasi', 'Jabatan yang tinggi', 'Gaji yang besar', 'Kantor yang mewah', 'B', 'Profesionalisme tercermin dari kompetensi teknis, integritas moral, dan dedikasi terhadap pekerjaan.'],
        ['Kerahasiaan informasi klien merupakan bagian dari...', 'Etika pergaulan', 'Etika profesi', 'Etika bermedia sosial', 'Etika berkendara', 'Etika makan', 'B', 'Menjaga kerahasiaan informasi klien adalah salah satu prinsip fundamental dalam etika profesi.'],
        ['Whistle blowing dalam konteks etika adalah...', 'Bermain peluit', 'Melaporkan pelanggaran atau kecurangan', 'Memberikan pujian', 'Mengkritik atasan', 'Menolak pekerjaan', 'B', 'Whistle blowing adalah tindakan melaporkan pelanggaran, kecurangan, atau praktik tidak etis yang terjadi di organisasi.'],
        ['Dilema etika terjadi ketika...', 'Tidak ada pekerjaan', 'Terdapat konflik antara dua nilai moral', 'Gaji tidak cukup', 'Rekan kerja tidak ramah', 'Kantor terlalu jauh', 'B', 'Dilema etika muncul ketika seseorang dihadapkan pada situasi dimana terdapat konflik antara dua atau lebih nilai moral.'],
        ['Organisasi profesi berperan dalam...', 'Menaikkan gaji anggota', 'Mengawasi dan menegakkan kode etik', 'Mencari pekerjaan untuk anggota', 'Memberikan pinjaman', 'Mengadakan pesta', 'B', 'Organisasi profesi berperan mengawasi, membina, dan menegakkan kode etik profesi bagi anggotanya.'],
        ['Kompetensi profesional mencakup...', 'Hanya pengetahuan teoritis', 'Pengetahuan, keterampilan, dan sikap', 'Hanya pengalaman kerja', 'Hanya sertifikasi', 'Hanya ijazah', 'B', 'Kompetensi profesional meliputi tiga aspek: pengetahuan (knowledge), keterampilan (skill), dan sikap (attitude).'],
        ['Prinsip non-maleficence berarti...', 'Berbuat baik', 'Tidak merugikan orang lain', 'Berlaku adil', 'Menghormati otonomi', 'Menjaga kerahasiaan', 'B', 'Non-maleficence adalah prinsip untuk tidak melakukan tindakan yang dapat merugikan atau membahayakan orang lain.'],
        ['Etika deontologi menekankan pada...', 'Hasil atau konsekuensi', 'Kewajiban dan aturan moral', 'Kebahagiaan maksimal', 'Kepentingan pribadi', 'Tradisi dan budaya', 'B', 'Etika deontologi menekankan pada kewajiban moral dan kepatuhan terhadap aturan, terlepas dari konsekuensinya.'],
        ['Beneficence dalam etika profesi adalah...', 'Tidak merugikan', 'Berbuat baik untuk kepentingan klien', 'Berlaku jujur', 'Menjaga rahasia', 'Berlaku adil', 'B', 'Beneficence adalah prinsip untuk selalu berbuat baik dan mengutamakan kepentingan serta kesejahteraan klien.'],
        ['Akuntabilitas profesional berarti...', 'Kemampuan menghitung', 'Bertanggung jawab atas tindakan profesional', 'Kemampuan membuat laporan', 'Kemampuan berbicara', 'Kemampuan menulis', 'B', 'Akuntabilitas adalah kesiapan untuk bertanggung jawab dan mempertanggungjawabkan setiap tindakan profesional.'],
        ['Loyalitas dalam profesi harus diimbangi dengan...', 'Kepatuhan buta', 'Integritas dan etika', 'Kepentingan pribadi', 'Ambisi karier', 'Keinginan untuk terkenal', 'B', 'Loyalitas profesional harus seimbang dengan integritas dan kepatuhan terhadap etika, bukan loyalitas buta.'],
        ['Continuous professional development penting untuk...', 'Meningkatkan gaji', 'Menjaga dan meningkatkan kompetensi', 'Mendapat promosi', 'Pindah kerja', 'Pensiun dini', 'B', 'Pengembangan profesional berkelanjutan penting untuk menjaga dan meningkatkan kompetensi sesuai perkembangan zaman.']
    ];
    
    $soalEsai = [
        ['Jelaskan mengapa etika profesi penting dalam dunia kerja modern! Berikan contoh penerapannya.', 'kepercayaan,integritas,profesional,tanggung jawab,masyarakat', 'Etika profesi penting karena membangun kepercayaan publik, menjaga integritas profesi, dan memastikan tanggung jawab profesional terhadap masyarakat.'],
        ['Bagaimana cara mengatasi dilema etika dalam situasi kerja? Jelaskan langkah-langkahnya!', 'identifikasi,analisis,alternatif,konsekuensi,keputusan,evaluasi', 'Langkah mengatasi dilema etika: identifikasi masalah, analisis nilai yang berkonflik, pertimbangkan alternatif, evaluasi konsekuensi, dan ambil keputusan yang paling etis.'],
        ['Apa peran organisasi profesi dalam menegakkan etika? Berikan contoh konkret!', 'kode etik,pengawasan,sanksi,pembinaan,standar,sertifikasi', 'Organisasi profesi berperan menyusun kode etik, melakukan pengawasan, memberikan sanksi, pembinaan anggota, dan menetapkan standar profesi.'],
        ['Jelaskan hubungan antara etika profesi dan tanggung jawab sosial perusahaan (CSR)!', 'masyarakat,lingkungan,berkelanjutan,stakeholder,dampak,keberlanjutan', 'Etika profesi dan CSR saling terkait dalam memastikan tanggung jawab terhadap masyarakat, lingkungan, dan keberlanjutan usaha dengan memperhatikan kepentingan stakeholder.'],
        ['Bagaimana teknologi digital mempengaruhi penerapan etika profesi? Jelaskan tantangan dan solusinya!', 'privasi,data,keamanan,transparansi,digital,cyber,regulasi', 'Teknologi digital menghadirkan tantangan baru seperti privasi data, keamanan cyber, dan transparansi. Solusinya meliputi regulasi yang tepat dan penerapan etika digital.']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO soal (tipe, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, jawaban_benar, pembahasan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($soalPG as $s) {
        $stmt->execute(['pg', $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7]]);
    }
    
    $stmt = $pdo->prepare("INSERT INTO soal (tipe, pertanyaan, poin_kunci, pembahasan) VALUES (?, ?, ?, ?)");
    foreach ($soalEsai as $s) {
        $stmt->execute(['esai', $s[0], $s[1], $s[2]]);
    }
}
