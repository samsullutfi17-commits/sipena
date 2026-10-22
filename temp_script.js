
        function confirmDelete(msg) {
            try {
                return window.confirm(msg);
            } catch (e) {
                return true;
            }
        }

        const soalListDb = [{"indikator":"Mampu mengidentifikasi fungsi protokol TCP/IP dalam pengiriman data di internet","pertanyaan":"Dalam arsitektur internet, protokol apa yang bertanggung jawab untuk memecah data menjadi paket-paket kecil dan memastikan paket tersebut tiba di tujuan dengan urutan yang benar?","data_tambahan":null,"id":1,"pembahasan":"TCP/IP (Transmission Control Protocol/Internet Protocol) adalah protokol inti yang mengatur pengiriman data di internet. TCP memecah data menjadi paket dan memastikan integritas serta urutan pengiriman, sedangkan IP menangani alamat dan routing paket.","cpmk":"Menjelaskan konsep dasar internet dan protokol komunikasi data","id_ujian":3,"materi_pokok":"Konsep Internet","poin":4,"jenis_soal":"pg","level_kognitif":"C2","urutan":1,"tingkat_kesulitan":"mudah","opsi":[{"benar":false,"teks_opsi":"HTTP","id_soal":1,"id":1,"urutan":1},{"urutan":2,"id":2,"id_soal":1,"teks_opsi":"TCP/IP","benar":true},{"urutan":3,"teks_opsi":"FTP","id":3,"benar":false,"id_soal":1},{"urutan":4,"id":4,"id_soal":1,"benar":false,"teks_opsi":"SMTP"},{"benar":false,"id_soal":1,"urutan":5,"id":5,"teks_opsi":"DNS"}]},{"pembahasan":"Crawler atau spider adalah program yang secara otomatis menjelajahi web, mengikuti tautan, dan mengumpulkan konten halaman. Data yang dikumpulkan kemudian diproses oleh indexer untuk disimpan di database agar dapat dicari.","materi_pokok":"Search Engine","urutan":2,"tingkat_kesulitan":"sedang","indikator":"Mampu membedakan antara crawler, indexer, dan database dalam proses pencarian informasi","cpmk":"Menganalisis mekanisme kerja search engine","id":2,"level_kognitif":"C3","pertanyaan":"Ketika pengguna memasukkan kata kunci ke dalam search engine, komponen mana yang secara aktif mengunjungi halaman web untuk mengumpulkan data baru dan memperbarui indeks?","poin":4,"data_tambahan":null,"jenis_soal":"pg","id_ujian":3,"opsi":[{"urutan":1,"benar":false,"id":6,"id_soal":2,"teks_opsi":"Indexer"},{"urutan":2,"id":7,"teks_opsi":"Database","id_soal":2,"benar":false},{"urutan":3,"teks_opsi":"Crawler (Spider)","benar":true,"id":8,"id_soal":2},{"benar":false,"urutan":4,"id_soal":2,"teks_opsi":"Ranking Algorithm","id":9},{"urutan":5,"teks_opsi":"User Interface","id":10,"id_soal":2,"benar":false}]},{"data_tambahan":null,"pertanyaan":"Seorang dosen mengirim dokumen sensitif berisi data mahasiswa melalui email tanpa enkripsi ke alamat email pribadi mahasiswa. Dari perspektif etika profesi dan keamanan siber, pelanggaran utama yang terjadi adalah...","id":3,"indikator":"Mampu menilai kepatuhan terhadap protokol keamanan dan privasi dalam pengiriman email","pembahasan":"Pengiriman data sensitif tanpa enkripsi melanggar prinsip kerahasiaan (confidentiality) dalam keamanan informasi. Hal ini berisiko tinggi terhadap kebocoran data pribadi (PII) dan melanggar etika profesi serta regulasi perlindungan data.","level_kognitif":"C4","materi_pokok":"Email","urutan":3,"jenis_soal":"pg","cpmk":"Mengevaluasi etika penggunaan email dalam konteks profesional","tingkat_kesulitan":"sulit","poin":4,"id_ujian":3,"opsi":[{"id":11,"urutan":1,"benar":false,"teks_opsi":"Penggunaan protokol SMTP yang salah","id_soal":3},{"id_soal":3,"teks_opsi":"Kehilangan integritas data akibat transmisi tidak terenkripsi","benar":true,"id":12,"urutan":2},{"benar":false,"teks_opsi":"Pelanggaran terhadap hak cipta materi ajar","urutan":3,"id_soal":3,"id":13},{"id":14,"urutan":4,"benar":false,"teks_opsi":"Kesalahan dalam penulisan subjek email","id_soal":3},{"urutan":5,"teks_opsi":"Gangguan pada server mail institusi","id_soal":3,"id":15,"benar":false}]},{"materi_pokok":"Browsing","pembahasan":"Pernyataan benar. Browser adalah perangkat lunak klien yang bertindak sebagai perantara antara pengguna dan server web, dengan tugas utama merender (rendering) konten web menjadi antarmuka grafis.","id":4,"pertanyaan":"Browser web berfungsi sebagai klien yang menerjemahkan kode HTML, CSS, dan JavaScript menjadi tampilan visual yang dapat dibaca oleh pengguna di perangkatnya.","tingkat_kesulitan":"mudah","data_tambahan":null,"poin":4,"urutan":4,"jenis_soal":"tf","level_kognitif":"C2","cpmk":"Menjelaskan fungsi dan karakteristik browsing web","indikator":"Mampu menentukan kebenaran pernyataan mengenai fungsi browser web","id_ujian":3,"opsi":[{"urutan":1,"teks_opsi":"Benar","id":16,"benar":true,"id_soal":4},{"id":17,"urutan":2,"benar":false,"id_soal":4,"teks_opsi":"Salah"}]},{"pembahasan":"Pernyataan salah. Fenomena ini dikenal sebagai 'filter bubble' atau 'echo chamber', di mana algoritma personalisasi cenderung menyajikan konten yang sesuai dengan preferensi pengguna sebelumnya, sehingga mempersempit paparan terhadap perspektif yang berbeda.","data_tambahan":null,"urutan":5,"pertanyaan":"Penggunaan search engine yang sama secara berulang dengan preferensi yang sama akan menghasilkan variasi sumber informasi yang semakin luas dan objektif bagi pengguna.","indikator":"Mampu mengidentifikasi potensi bias atau filter bubble dalam hasil pencarian","cpmk":"Menganalisis dampak algoritma search engine terhadap akses informasi","materi_pokok":"Search Engine","poin":4,"id_ujian":3,"tingkat_kesulitan":"sedang","jenis_soal":"tf","level_kognitif":"C3","id":5,"opsi":[{"teks_opsi":"Benar","benar":false,"urutan":1,"id_soal":5,"id":18},{"id_soal":5,"teks_opsi":"Salah","id":19,"urutan":2,"benar":true}]},{"cpmk":"Mengevaluasi keamanan protokol email","urutan":6,"poin":4,"tingkat_kesulitan":"sulit","level_kognitif":"C4","id":6,"id_ujian":3,"jenis_soal":"tf","data_tambahan":null,"pembahasan":"Pernyataan benar. SMTP standar mengirim data dalam teks polos (plaintext) yang rentan terhadap penyadapan. SMTPS menggunakan enkripsi SSL/TLS untuk melindungi integritas dan kerahasiaan data selama transmisi.","pertanyaan":"Protokol SMTPS (SMTP over SSL/TLS) menjamin bahwa isi email tidak dapat dibaca oleh pihak ketiga selama dalam transit jaringan, berbeda dengan SMTP standar.","indikator":"Mampu membedakan protokol pengiriman dan protokol keamanan email","materi_pokok":"Email","opsi":[{"urutan":1,"teks_opsi":"Benar","id":20,"benar":true,"id_soal":6},{"urutan":2,"id_soal":6,"teks_opsi":"Salah","benar":false,"id":21}]},{"urutan":7,"poin":4,"materi_pokok":"Konsep Internet","jenis_soal":"menjodohkan","cpmk":"Menjelaskan komponen dasar infrastruktur internet","indikator":"Mampu menjodohkan istilah teknis dengan definisi atau fungsinya yang tepat","pertanyaan":"Jodohkan istilah berikut dengan definisi yang tepat:\n1. Domain Name System (DNS)\n2. IP Address\n3. Web Server\n4. Router","id_ujian":3,"data_tambahan":{"pairs":{"Domain Name System (DNS)":"Sistem yang menerjemahkan nama domain menjadi alamat IP","IP Address":"Identifikasi unik numerik untuk perangkat di jaringan","Web Server":"Komputer yang menyimpan dan menyajikan halaman web","Router":"Perangkat yang mengarahkan paket data antar jaringan"}},"id":7,"tingkat_kesulitan":"mudah","level_kognitif":"C2","pembahasan":"DNS menerjemahkan nama mudah diingat (domain) menjadi IP. IP adalah alamat unik perangkat. Web server menyimpan konten. Router mengarahkan lalu lintas data antar jaringan.","opsi":[{"id":22,"benar":true,"teks_opsi":"1 - Sistem yang menerjemahkan nama domain menjadi alamat IP","urutan":1,"id_soal":7},{"benar":true,"teks_opsi":"2 - Identifikasi unik numerik untuk perangkat di jaringan","id_soal":7,"id":23,"urutan":2},{"id":24,"benar":true,"urutan":3,"id_soal":7,"teks_opsi":"3 - Komputer yang menyimpan dan menyajikan halaman web"},{"teks_opsi":"4 - Perangkat yang mengarahkan paket data antar jaringan","id_soal":7,"urutan":4,"id":25,"benar":true}],"matching_pairs":{"Domain Name System (DNS)":"Sistem yang menerjemahkan nama domain menjadi alamat IP","IP Address":"Identifikasi unik numerik untuk perangkat di jaringan","Web Server":"Komputer yang menyimpan dan menyajikan halaman web","Router":"Perangkat yang mengarahkan paket data antar jaringan"}},{"data_tambahan":{"pairs":{"Crawling":"Mengunjungi halaman web dan menyimpan salinan data","Indexing":"Menyusun data yang dikumpulkan ke dalam struktur database yang dapat dicari","Ranking":"Mengurutkan hasil pencarian berdasarkan relevansi dan faktor SEO"}},"jenis_soal":"menjodohkan","tingkat_kesulitan":"sedang","materi_pokok":"Search Engine","indikator":"Mampu menjodohkan tahapan proses pencarian dengan aktivitas spesifiknya","pertanyaan":"Jodohkan tahapan proses kerja search engine dengan deskripsi aktivitasnya:\n1. Crawling\n2. Indexing\n3. Ranking","level_kognitif":"C3","id":8,"poin":4,"pembahasan":"Kunci jawaban yang tepat didasarkan pada kesesuaian prinsip metodologis dan kaidah standar pada materi Search Engine.","cpmk":"Menganalisis proses indexing pada search engine","id_ujian":3,"urutan":8,"opsi":[{"id":26,"benar":true,"teks_opsi":"1 - Mengunjungi halaman web dan menyimpan salinan data","id_soal":8,"urutan":1},{"id":27,"id_soal":8,"urutan":2,"benar":true,"teks_opsi":"2 - Menyusun data yang dikumpulkan ke dalam struktur database yang dapat dicari"},{"urutan":3,"teks_opsi":"3 - Mengurutkan hasil pencarian berdasarkan relevansi dan faktor SEO","id_soal":8,"benar":true,"id":28}],"matching_pairs":{"Crawling":"Mengunjungi halaman web dan menyimpan salinan data","Indexing":"Menyusun data yang dikumpulkan ke dalam struktur database yang dapat dicari","Ranking":"Mengurutkan hasil pencarian berdasarkan relevansi dan faktor SEO"}},{"materi_pokok":"Pemanfaatn Platform atau Tools AI - Pemanfaatn Tools AI untuk pengembangan produk media pembelajaran","pembahasan":"Jawaban yang benar adalah B. Dalam pengembangan media pembelajaran, akurasi data adalah prioritas utama. Model AI generatif (LLM) memiliki potensi 'hallucination' atau kesalahan faktual, terutama dalam perhitungan statistik numerik. Oleh karena itu, prinsip 'Human-in-the-loop' mengharuskan pengembang untuk memverifikasi output AI dengan alat analisis yang deterministik dan teruji (seperti R, Python, atau SPSS) serta memeriksa bias interpretasi sebelum konten tersebut disajikan kepada peserta didik. Opsi lain mengandung risiko etika (kerahasiaan data), pedagogis (beban kognitif tidak relevan), atau teknis (ketidakakuratan).","cpmk":"Mampu mengevaluasi keandalan dan etika penggunaan output AI dalam pengembangan konten pembelajaran.","pertanyaan":"Sebuah tim pengembang e-learning di sebuah universitas sedang merancang modul interaktif untuk mata kuliah 'Statistik Terapan'. Mereka memiliki data mentah berupa 500 nilai ujian mahasiswa. Tim tersebut mempertimbangkan penggunaan platform AI generatif seperti ChatGPT atau Claude untuk membantu proses analisis data dan pembuatan narasi interpretasi hasil. Dalam konteks pengembangan produk media pembelajaran, langkah mana yang paling tepat dan etis dilakukan oleh tim pengembang sebelum mengintegrasikan output AI ke dalam modul final?","gambar":null,"id_ujian":3,"id":10,"urutan":9,"poin":4,"indikator":"Mampu menentukan prosedur validasi dan verifikasi data AI untuk memastikan akurasi konten pembelajaran.","data_tambahan":null,"level_kognitif":"C3","tingkat_kesulitan":"proporsional","jenis_soal":"pg","opsi":[{"id_soal":10,"id":30,"urutan":1,"teks_opsi":"Menggunakan output AI secara langsung karena model bahasa besar memiliki akurasi statistik yang tinggi dan tidak perlu diverifikasi ulang.","benar":false},{"id_soal":10,"benar":true,"urutan":2,"teks_opsi":"Melakukan verifikasi silang (cross-verification) terhadap hasil analisis statistik dan narasi interpretasi menggunakan software statistik konvensional (seperti SPSS atau R) serta memastikan tidak ada bias data sebelum dipublikasikan.","id":31},{"urutan":3,"id_soal":10,"benar":false,"teks_opsi":"Menghapus seluruh data mentah dan hanya menampilkan grafik yang dihasilkan oleh AI untuk menjaga kerahasiaan data mahasiswa.","id":32},{"id":33,"urutan":4,"id_soal":10,"teks_opsi":"Meminta mahasiswa untuk mengoreksi sendiri hasil analisis AI sebagai bagian dari tugas belajar tanpa intervensi dosen pengembang.","benar":false},{"teks_opsi":"Menggunakan AI hanya untuk menerjemahkan istilah statistik ke dalam bahasa Indonesia tanpa melibatkan proses analisis data apa pun.","id_soal":10,"urutan":5,"benar":false,"id":34}]},{"cpmk":"Mampu mengintegrasikan output AI dengan prinsip validitas konten ilmiah dalam desain media pembelajaran.","poin":4,"jenis_soal":"pg","id":11,"id_ujian":3,"indikator":"Mampu merancang strategi mitigasi kesalahan visual AI dengan menggabungkan aset AI dan aset vektor/animasi manual.","tingkat_kesulitan":"proporsional","pertanyaan":"Perhatikan skenario berikut: Seorang dosen ingin membuat video pembelajaran mikro (micro-learning) berdurasi 3 menit tentang 'Fotosintesis'. Ia menggunakan tool AI video generator seperti Runway Gen-2 atau Pika Labs untuk membuat visualisasi proses penyerapan cahaya oleh klorofil. Namun, hasil render AI sering kali menampilkan struktur sel yang tidak akurat secara biologis (misalnya, bentuk klorofil yang menyerupai bola kristal). Berdasarkan prinsip desain media pembelajaran berbasis AI, strategi koreksi yang paling efektif untuk meningkatkan validitas ilmiah video tersebut adalah...","materi_pokok":"Pemanfaatn Platform atau Tools AI - Pemanfaatn Tools AI untuk pengembangan produk media pembelajaran","data_tambahan":null,"level_kognitif":"C4","gambar":null,"pembahasan":"Jawaban yang benar adalah B. AI video generator saat ini masih lemah dalam memahami struktur biologis mikro yang spesifik. Strategi terbaik adalah hybrid approach: menggunakan kekuatan AI untuk estetika dan dinamika latar belakang, namun menggunakan aset vektor/animasi yang dikontrol penuh oleh pengembang (yang sudah divalidasi secara ilmiah) untuk elemen inti pembelajaran. Opsi A tidak menjamin akurasi karena LLM/video AI tidak memiliki database biologis yang presisi. Opsi D adalah cara yang pasif dan tidak menyelesaikan masalah miskonsepsi visual. Opsi C menghilangkan keunggulan interaktif AI.","urutan":10,"opsi":[{"teks_opsi":"Mengubah prompt menjadi lebih spesifik dengan menambahkan kata 'scientifically accurate' dan 'high resolution' agar AI memahami konteks biologis.","urutan":1,"id_soal":11,"benar":false,"id":35},{"id":36,"benar":true,"id_soal":11,"teks_opsi":"Menggunakan output AI hanya sebagai latar belakang (background) abstrak, kemudian meng-overlay (menimpa) animasi vektor 2D atau 3D yang telah divalidasi secara ilmiah di atasnya untuk menjelaskan mekanisme fotosintesis.","urutan":2},{"id":37,"teks_opsi":"Menghapus seluruh elemen visual AI dan menggantinya dengan foto statis dari buku teks agar lebih akurat.","urutan":3,"id_soal":11,"benar":false},{"urutan":4,"id_soal":11,"id":38,"benar":false,"teks_opsi":"Menambahkan teks disclaimer di bawah video yang menyatakan bahwa visualisasi bersifat ilustratif dan tidak harus akurat secara anatomis."},{"teks_opsi":"Mengulang proses generasi video hingga 100 kali sampai AI menghasilkan gambar klorofil yang sempurna tanpa campur tangan manusia.","id":39,"urutan":5,"id_soal":11,"benar":false}]},{"id_ujian":3,"pembahasan":"Pernyataan tersebut SALAH. Meskipun AI dapat menghasilkan soal dalam jumlah besar, konsistensi tingkat kesulitan (item difficulty) dan kesesuaian level kognitif (Bloom's Taxonomy) tidak terjamin secara otomatis. AI cenderung menghasilkan soal yang bersifat deskriptif (C1-C2) dan sering kali gagal membedakan nuansa antara level C3 (aplikasi) dan C4 (analisis) tanpa instruksi (prompt) yang sangat spesifik dan terstruktur. Selain itu, diperlukan kalibrasi manual (expert review) untuk memastikan validitas isi dan tidak adanya ambiguitas jawaban. Oleh karena itu, klaim 'tanpa perlu kalibrasi manual' adalah keliru.","indikator":"Mampu mengidentifikasi validitas klaim tentang konsistensi kualitas output AI dalam konteks asesmen.","pertanyaan":"Sebuah startup edtech sedang mengembangkan aplikasi kuis adaptif. Mereka menggunakan API AI untuk menghasilkan soal-soal baru secara otomatis berdasarkan topik yang diinputkan. Pernyataan berikut menyatakan: 'Penggunaan AI untuk generasi soal otomatis menjamin bahwa tingkat kesulitan soal akan selalu konsisten dan sesuai dengan taksonomi Bloom yang diinginkan tanpa perlu kalibrasi manual oleh ahli materi.'","materi_pokok":"Pemanfaatn Platform atau Tools AI - Pemanfaatn Tools AI untuk pengembangan produk media pembelajaran","level_kognitif":"C2","tingkat_kesulitan":"proporsional","gambar":null,"id":12,"urutan":11,"cpmk":"Mampu menganalisis keterbatasan teknis dan pedagogis dari otomatisasi AI dalam penyusunan instrumen asesmen.","poin":4,"data_tambahan":null,"jenis_soal":"tf","opsi":[{"id_soal":12,"urutan":1,"benar":false,"teks_opsi":"Benar","id":40},{"teks_opsi":"Salah","benar":true,"urutan":2,"id_soal":12,"id":41}]}];

        function openEditSoalModalById(soalId) {
            const soal = soalListDb.find(s => s.id === Number(soalId));
            if (!soal) return;
            openEditSoalModal(soal);
        }

        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function openConfirmDeleteSoal(id, no) {
            document.getElementById('deleteSoalId').value = id;
            document.getElementById('deleteSoalNumber').innerText = 'Nomor ' + no;
            document.getElementById('modalDeleteSoalOverlay').style.display = 'flex';
        }

        function closeConfirmDeleteSoal() {
            document.getElementById('modalDeleteSoalOverlay').style.display = 'none';
        }

        function switchSoalType(type, prefix) {
            const pgContainer = document.getElementById(prefix + '_type_pg_container');
            const tfContainer = document.getElementById(prefix + '_type_tf_container');
            const matchContainer = document.getElementById(prefix + '_type_menjodohkan_container');
            if (pgContainer) pgContainer.style.display = (type === 'pg' || type === 'pg_kompleks') ? 'block' : 'none';
            if (tfContainer) tfContainer.style.display = (type === 'tf') ? 'block' : 'none';
            if (matchContainer) matchContainer.style.display = (type === 'menjodohkan' || type === 'matching') ? 'block' : 'none';
        }

        function addMatchingRow(wrapId) {
            const wrap = document.getElementById(wrapId);
            if (!wrap) return;
            const count = wrap.querySelectorAll('.matching-pair-row').length + 1;
            const row = document.createElement('div');
            row.className = 'matching-pair-row';
            row.style.cssText = 'display: flex; gap: 8px; align-items: center;';
            row.innerHTML = `
                <span style="font-weight: 700; color: #9333ea; width: 20px;">${count}.</span>
                <input type="text" name="matching_keys[]" placeholder="Premis ${count} (Kolom Kiri)" class="form-control" style="flex: 1; padding: 8px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                <span style="color: #a855f7;"><i class="fas fa-arrow-right"></i></span>
                <input type="text" name="matching_values[]" placeholder="Kunci Pasangan ${count} (Kolom Kanan)" class="form-control" style="flex: 1; padding: 8px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px;">
            `;
            wrap.appendChild(row);
        }

        function openEditSoalModal(soal) {
            document.getElementById('edit_soal_id').value = soal.id;
            document.getElementById('edit_soal_poin').value = soal.poin || 4;
            document.getElementById('edit_soal_kesulitan').value = soal.tingkat_kesulitan || 'sedang';
            document.getElementById('edit_soal_kognitif').value = soal.level_kognitif || 'C1';
            document.getElementById('edit_soal_pertanyaan').value = (soal.pertanyaan || '').replace(/<[^>]*>?/gm, '');
            document.getElementById('edit_soal_pembahasan').value = soal.pembahasan || '';

            // Handle Question Image in Edit Modal
            const gUrl = soal.gambar || soal.gambar_soal || '';
            const editUrlInput = document.getElementById('edit_gambar_url');
            const editPreviewBox = document.getElementById('edit_gambar_preview_box');
            const editPreviewImg = document.getElementById('edit_gambar_preview_img');
            const editFileInput = document.getElementById('edit_gambar_file');
            const editStatusMsg = document.getElementById('edit_gambar_status_msg');
            if (editFileInput) editFileInput.value = '';
            if (editStatusMsg) editStatusMsg.style.display = 'none';

            if (gUrl) {
                if (editUrlInput) editUrlInput.value = gUrl;
                if (editPreviewImg) editPreviewImg.src = gUrl;
                if (editPreviewBox) editPreviewBox.style.display = 'block';
            } else {
                if (editUrlInput) editUrlInput.value = '';
                if (editPreviewImg) editPreviewImg.src = '';
                if (editPreviewBox) editPreviewBox.style.display = 'none';
            }

            const jType = (soal.jenis_soal || 'pg').toLowerCase();
            const pgContainer = document.getElementById('edit_type_pg_container');
            const matchContainer = document.getElementById('edit_type_menjodohkan_container');

            if (jType === 'menjodohkan' || jType === 'matching') {
                if (pgContainer) pgContainer.style.display = 'none';
                if (matchContainer) {
                    matchContainer.style.display = 'block';
                    const wrap = document.getElementById('edit_matching_rows_wrap');
                    wrap.innerHTML = '';
                    const pairs = (soal.data_tambahan && soal.data_tambahan.pairs) || soal.matching_pairs || {};
                    let entries = Object.entries(pairs);
                    if (entries.length === 0) {
                        entries = [['', ''], ['', ''], ['', '']];
                    }
                    entries.forEach(([k, v], idx) => {
                        const row = document.createElement('div');
                        row.className = 'matching-pair-row';
                        row.style.cssText = 'display: flex; gap: 8px; align-items: center;';
                        row.innerHTML = `
                            <span style="font-weight: 700; color: #9333ea; width: 20px;">${idx + 1}.</span>
                            <input type="text" name="matching_keys[]" value="${k.replace(/"/g, '&quot;')}" placeholder="Premis (Kiri)" class="form-control" style="flex: 1; padding: 8px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                            <span style="color: #a855f7;"><i class="fas fa-arrow-right"></i></span>
                            <input type="text" name="matching_values[]" value="${v.replace(/"/g, '&quot;')}" placeholder="Kunci Pasangan (Kanan)" class="form-control" style="flex: 1; padding: 8px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                        `;
                        wrap.appendChild(row);
                    });
                }
            } else {
                if (matchContainer) matchContainer.style.display = 'none';
                if (soal.opsi && Array.isArray(soal.opsi)) {
                    if (pgContainer) pgContainer.style.display = 'block';
                    soal.opsi.forEach((o, idx) => {
                        const textInput = document.getElementById('edit_opsi_teks_' + idx);
                        const radioInput = document.getElementById('edit_opsi_benar_' + idx);
                        if (textInput) textInput.value = o.teks_opsi || o.teks || '';
                        if (radioInput && o.benar) radioInput.checked = true;
                    });
                } else {
                    if (pgContainer) pgContainer.style.display = 'none';
                }
            }

            openModal('edit-soal-modal');
        }

        // Image Attachment & AI Generator Handlers
        async function handleImageFileSelected(input, prefix) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            const previewBox = document.getElementById(prefix + '_gambar_preview_box');
            const previewImg = document.getElementById(prefix + '_gambar_preview_img');
            const urlInput = document.getElementById(prefix + '_gambar_url');

            // Show instant local preview
            const reader = new FileReader();
            reader.onload = function(e) {
                if (previewImg) previewImg.src = e.target.result;
                if (previewBox) previewBox.style.display = 'block';
            };
            reader.readAsDataURL(file);

            // Asynchronously upload to server for permanent URL storage
            try {
                const formData = new FormData();
                formData.append('image', file);
                const res = await fetch('/admin/upload_soal_image', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success && data.url) {
                    if (urlInput) urlInput.value = data.url;
                }
            } catch (e) {
                console.warn('Upload image async note:', e);
            }
        }

        function handleImageUrlInput(val, prefix) {
            const previewBox = document.getElementById(prefix + '_gambar_preview_box');
            const previewImg = document.getElementById(prefix + '_gambar_preview_img');
            const clean = (val || '').trim();
            if (clean && (clean.startsWith('http://') || clean.startsWith('https://') || clean.startsWith('/') || clean.startsWith('data:image/'))) {
                if (previewImg) previewImg.src = clean;
                if (previewBox) previewBox.style.display = 'block';
            } else if (!clean) {
                const fileInput = document.getElementById(prefix + '_gambar_file');
                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                    if (previewBox) previewBox.style.display = 'none';
                }
            }
        }

        function removeQuestionImage(prefix) {
            const fileInput = document.getElementById(prefix + '_gambar_file');
            const urlInput = document.getElementById(prefix + '_gambar_url');
            const previewBox = document.getElementById(prefix + '_gambar_preview_box');
            const previewImg = document.getElementById(prefix + '_gambar_preview_img');
            const statusMsg = document.getElementById(prefix + '_gambar_status_msg');

            if (fileInput) fileInput.value = '';
            if (urlInput) urlInput.value = '';
            if (previewImg) previewImg.src = '';
            if (previewBox) previewBox.style.display = 'none';

            if (statusMsg) {
                statusMsg.style.display = 'block';
                statusMsg.style.background = '#fef2f2';
                statusMsg.style.border = '1px solid #fecaca';
                statusMsg.style.color = '#b91c1c';
                statusMsg.innerHTML = '<i class="fas fa-trash"></i> Gambar stimulus telah dilepas. Klik Simpan untuk memperbarui.';
            }
        }

        // Open Dedicated In-Page Modal for AI Image Generation (iFrame safe, zero window.prompt)
        function openAiImageGeneratorModal(prefix) {
            const targetPrefixInput = document.getElementById('single_ai_img_target_prefix');
            if (targetPrefixInput) targetPrefixInput.value = prefix;

            const pertInput = prefix === 'edit'
                ? document.getElementById('edit_soal_pertanyaan')
                : document.querySelector('#create-soal-modal textarea[name="pertanyaan"]');
            const currentText = pertInput ? pertInput.value.trim() : '';
            const defaultTopic = "KUIS – Etika Profesi";

            const promptInput = document.getElementById('single_ai_img_prompt');
            if (promptInput) {
                promptInput.value = currentText.substring(0, 90) || defaultTopic;
            }

            const banner = document.getElementById('single_ai_img_banner');
            const existingUrl = document.getElementById(prefix + '_gambar_url') ? document.getElementById(prefix + '_gambar_url').value : '';
            if (banner) {
                if (existingUrl) {
                    banner.innerHTML = '<i class="fas fa-arrows-rotate"></i> <strong>Mode Ganti / Regenerate:</strong> Butir soal ini sudah memiliki stimulus gambar. Diagram baru dari AI akan langsung menggantikan gambar saat ini.';
                    banner.style.background = '#fef3c7';
                    banner.style.borderColor = '#fde68a';
                    banner.style.color = '#92400e';
                } else {
                    banner.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> <strong>Rancang Diagram Stimulus:</strong> Tuliskan topik atau konsep visual diagram yang ingin dibuat oleh AI.';
                    banner.style.background = '#eff6ff';
                    banner.style.borderColor = '#bfdbfe';
                    banner.style.color = '#1e40af';
                }
            }

            const statusBox = document.getElementById('single_ai_img_status');
            if (statusBox) statusBox.style.display = 'none';

            const btn = document.getElementById('btn_execute_single_ai_img');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sparkles"></i> Rancang & Terapkan Gambar';
            }

            openModal('ai-single-img-modal');
        }

        // Backward compatibility
        function triggerAiGenerateImage(prefix) {
            openAiImageGeneratorModal(prefix);
        }

        // Execute AI Image Generation with full resilience
        async function executeSingleAiImageGenerate() {
            const prefix = document.getElementById('single_ai_img_target_prefix').value || 'edit';
            const promptInput = document.getElementById('single_ai_img_prompt');
            const styleInput = document.getElementById('single_ai_img_style');
            const statusBox = document.getElementById('single_ai_img_status');
            const btn = document.getElementById('btn_execute_single_ai_img');

            const promptVal = promptInput ? promptInput.value.trim() : '';
            const styleVal = styleInput ? styleInput.value : 'auto';
            const defaultTopic = "KUIS – Etika Profesi";

            const pertInput = prefix === 'edit'
                ? document.getElementById('edit_soal_pertanyaan')
                : document.querySelector('#create-soal-modal textarea[name="pertanyaan"]');
            const currentText = pertInput ? pertInput.value.trim() : '';
            const soalId = prefix === 'edit' ? (document.getElementById('edit_soal_id') ? document.getElementById('edit_soal_id').value : null) : null;

            if (!promptVal) {
                if (statusBox) {
                    statusBox.style.display = 'block';
                    statusBox.style.background = '#fee2e2';
                    statusBox.style.border = '1px solid #fecaca';
                    statusBox.style.color = '#dc2626';
                    statusBox.innerHTML = '<i class="fas fa-triangle-exclamation"></i> Masukkan deskripsi diagram terlebih dahulu.';
                }
                return;
            }

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Merancang Diagram SVG...';
            }
            if (statusBox) {
                statusBox.style.display = 'block';
                statusBox.style.background = '#eef6fc';
                statusBox.style.border = '1px solid #bfdbfe';
                statusBox.style.color = '#1e40af';
                statusBox.innerHTML = '<i class="fas fa-brain fa-fade" style="margin-right: 6px;"></i> AI sedang merancang diagram vektor SVG presisi tinggi...';
            }

            try {
                const res = await fetch('/admin/generate_soal_image_ai', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        prompt: promptVal,
                        topik: defaultTopic,
                        pertanyaan: currentText,
                        style: styleVal,
                        soal_id: soalId
                    })
                });
                const data = await res.json();
                if (data.success && data.url) {
                    const urlInput = document.getElementById(prefix + '_gambar_url');
                    const previewBox = document.getElementById(prefix + '_gambar_preview_box');
                    const previewImg = document.getElementById(prefix + '_gambar_preview_img');
                    const fileInput = document.getElementById(prefix + '_gambar_file');

                    if (urlInput) urlInput.value = data.url;
                    if (previewImg) previewImg.src = data.url;
                    if (previewBox) previewBox.style.display = 'block';
                    if (fileInput) fileInput.value = '';

                    const statusMsg = document.getElementById(prefix + '_gambar_status_msg');
                    if (statusMsg) {
                        statusMsg.style.display = 'block';
                        statusMsg.style.background = '#dcfce7';
                        statusMsg.style.border = '1px solid #bbf7d0';
                        statusMsg.style.color = '#15803d';
                        statusMsg.innerHTML = '<i class="fas fa-check-circle"></i> <strong>Berhasil!</strong> Diagram stimulus AI baru telah dipasang. Klik "Simpan Perubahan" untuk memfinalisasi.';
                    }

                    closeModal('ai-single-img-modal');
                } else {
                    if (statusBox) {
                        statusBox.style.display = 'block';
                        statusBox.style.background = '#fee2e2';
                        statusBox.style.border = '1px solid #fecaca';
                        statusBox.style.color = '#dc2626';
                        statusBox.innerHTML = '<i class="fas fa-circle-xmark"></i> ' + (data.message || 'Gagal merancang gambar.');
                    }
                }
            } catch (err) {
                if (statusBox) {
                    statusBox.style.display = 'block';
                    statusBox.style.background = '#fee2e2';
                    statusBox.style.border = '1px solid #fecaca';
                    statusBox.style.color = '#dc2626';
                    statusBox.innerHTML = '<i class="fas fa-circle-xmark"></i> Terjadi kendala teknis: ' + err.message;
                }
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sparkles"></i> Rancang & Terapkan Gambar';
                }
        }

        // Dropdown Cetak Handler
        function toggleCetakMenuSoal(e) {
            e.stopPropagation();
            const menu = document.getElementById('cetakDropdownSoal');
            if (menu) menu.classList.toggle('show');
        }

        // Checkbox helpers for multi-select jenis soal & bloom levels
        function setAllCheckboxes(className, checked) {
            document.querySelectorAll('.' + className).forEach(cb => cb.checked = checked);
        }

        function setOnlyCheckbox(className, targetVal) {
            document.querySelectorAll('.' + className).forEach(cb => {
                cb.checked = (cb.value === targetVal);
            });
        }

        function setPresetJenisSoal() {
            document.querySelectorAll('.cb-soal-jenis').forEach(cb => {
                cb.checked = ['pg', 'esai', 'tf'].includes(cb.value);
            });
        }

        function setHotsOnly(className) {
            document.querySelectorAll('.' + className).forEach(cb => {
                cb.checked = ['C4', 'C5', 'C6'].includes(cb.value);
            });
        }

        function setBasicApplied(className) {
            document.querySelectorAll('.' + className).forEach(cb => {
                cb.checked = ['C1', 'C2', 'C3'].includes(cb.value);
            });
        }

        // Helpers for Targeted AI Question & Stimulus Image Generation
        function toggleAiGambarConfig(checked) {
            const panel = document.getElementById('ai-gambar-config-panel');
            if (panel) panel.style.display = checked ? 'block' : 'none';
            updateAiGambarSummary();
        }

        function handleAiGambarTargetModeChange(val) {
            const typesWrap = document.getElementById('ai-gambar-specific-types-wrap');
            if (typesWrap) {
                typesWrap.style.display = (val === 'specific_types') ? 'block' : 'none';
            }
            updateAiGambarSummary();
        }

        function updateAiGambarSummary() {
            const summaryText = document.getElementById('ai-gambar-summary-text');
            const chk = document.getElementById('ai-generate-gambar');
            const totalCount = parseInt(document.getElementById('ai-count') ? document.getElementById('ai-count').value : '5', 10) || 5;
            if (!summaryText) return;

            if (!chk || !chk.checked) {
                summaryText.innerHTML = `<strong>Teks Murni (Tanpa Gambar):</strong> Seluruh ${totalCount} butir naskah soal akan disusun sebagai teks mandiri (self-contained) tanpa stimulus gambar.`;
                return;
            }

            const targetMode = document.getElementById('ai-gambar-target-mode') ? document.getElementById('ai-gambar-target-mode').value : 'hots_only';
            const rawMax = parseInt(document.getElementById('ai-gambar-max-count') ? document.getElementById('ai-gambar-max-count').value : '2', 10) || 2;
            const maxGambar = Math.min(totalCount, rawMax);
            const style = document.getElementById('ai-gambar-style') ? document.getElementById('ai-gambar-style').value : 'auto';
            const customPrompt = document.getElementById('ai-gambar-custom-prompt') ? document.getElementById('ai-gambar-custom-prompt').value.trim() : '';

            let targetDesc = '';
            if (targetMode === 'hots_only') {
                targetDesc = 'hanya butir soal <strong>HOTS (C4-C6)</strong>';
            } else if (targetMode === 'specific_types') {
                const types = getCheckedValues('cb-gambar-type', ['pg']);
                const typeLabels = types.map(t => {
                    if (t === 'pg') return 'PG';
                    if (t === 'pg_kompleks') return 'PG Kompleks';
                    if (t === 'esai') return 'Esai';
                    if (t === 'menjodohkan') return 'Menjodohkan';
                    return t;
                }).join(', ');
                targetDesc = `hanya bentuk <strong>[${typeLabels || 'PG'}]</strong>`;
            } else if (targetMode === 'custom_count') {
                targetDesc = `hanya untuk <strong>${maxGambar} butir pertama</strong>`;
            } else {
                targetDesc = 'seluruh butir soal yang di-generate';
            }

            let styleDesc = '';
            if (style === 'process') styleDesc = 'Alur Proses / Flowchart';
            else if (style === 'architecture') styleDesc = 'Arsitektur Sistem';
            else if (style === 'network') styleDesc = 'Topologi Jaringan';
            else if (style === 'hierarchy') styleDesc = 'Pohon Hirarki';
            else if (style === 'venn') styleDesc = 'Diagram Venn';
            else styleDesc = 'Otomatis Sesuai Materi';

            const textOnlyCount = Math.max(0, totalCount - maxGambar);
            let summary = `<strong>Distribusi:</strong> Maksimal <strong>${maxGambar} butir</strong> bergambar (${targetDesc}, format: <em>${styleDesc}</em>)`;
            if (textOnlyCount > 0) {
                summary += ` & <strong>${textOnlyCount} butir</strong> naskah teks mandiri (tanpa gambar).`;
            } else {
                summary += `.`;
            }
            if (customPrompt) {
                summary += ` <em>(Fokus: "${customPrompt}")</em>`;
            }
            summaryText.innerHTML = summary;
        }

        function getCheckedValues(className, defaultValues = []) {
            const checked = Array.from(document.querySelectorAll('.' + className + ':checked')).map(cb => cb.value);
            return checked.length > 0 ? checked : defaultValues;
        }

        async function handleAiGenerate(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-generate-ai');
            const status = document.getElementById('ai-status');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyusun Soal Akademik...';
            status.style.display = 'block';
            status.style.background = '#eef6fc';
            status.style.color = '#1e429f';
            status.innerHTML = '<i class="fas fa-brain fa-fade" style="margin-right: 6px;"></i> AI sedang menyusun butir soal terarah, stimulus diagram presisi, dan kunci valid...';

            const autoSave = document.getElementById('ai-auto-save') ? document.getElementById('ai-auto-save').checked : true;
            const generateGambar = document.getElementById('ai-generate-gambar') ? document.getElementById('ai-generate-gambar').checked : true;
            const gambarTargetMode = document.getElementById('ai-gambar-target-mode') ? document.getElementById('ai-gambar-target-mode').value : 'hots_only';
            const gambarTargetTypes = getCheckedValues('cb-gambar-type', ['pg', 'pg_kompleks', 'esai']);
            const gambarMaxCount = parseInt(document.getElementById('ai-gambar-max-count') ? document.getElementById('ai-gambar-max-count').value : '2', 10) || 2;
            const gambarStyle = document.getElementById('ai-gambar-style') ? document.getElementById('ai-gambar-style').value : 'auto';
            const gambarCustomPrompt = document.getElementById('ai-gambar-custom-prompt') ? document.getElementById('ai-gambar-custom-prompt').value.trim() : '';
            const gayaNaskah = document.getElementById('ai-gaya-naskah') ? document.getElementById('ai-gaya-naskah').value : 'kasus';

            const jenisSoalList = getCheckedValues('cb-soal-jenis', ['pg']);
            const kognitifList = getCheckedValues('cb-soal-kognitif', ['C2', 'C3', 'C4']);

            try {
                const res = await fetch('/admin/ai_generate_api', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ujian_id: '3',
                        topik: document.getElementById('ai-topic').value,
                        sub_topik: document.getElementById('ai-subtopic') ? document.getElementById('ai-subtopic').value : '',
                        gaya_naskah: gayaNaskah,
                        jenis_soal_list: jenisSoalList,
                        kognitif_list: kognitifList,
                        jumlah: parseInt(document.getElementById('ai-count').value, 10) || 5,
                        poin: parseInt(document.getElementById('ai-poin').value, 10) || 4,
                        kesulitan: document.getElementById('ai-kesulitan').value,
                        generate_gambar: generateGambar,
                        gambar_target_mode: gambarTargetMode,
                        gambar_target_types: gambarTargetTypes,
                        gambar_max_count: gambarMaxCount,
                        gambar_style: gambarStyle,
                        gambar_custom_prompt: gambarCustomPrompt,
                        apply: autoSave
                    })
                });
                const result = await res.json();
                if (result.success || (Array.isArray(result) && result.length > 0)) {
                    const count = result.count || (Array.isArray(result) ? result.length : 1);
                    status.style.background = '#dcfce7';
                    status.style.color = '#15803d';
                    status.innerHTML = `<i class="fas fa-circle-check" style="margin-right: 6px;"></i> Berhasil menghasilkan <strong>${count}</strong> butir soal siap uji dan tersimpan permanen! Memperbarui halaman...`;
                    setTimeout(() => window.location.reload(), 850);
                } else {
                    throw new Error(result.message || 'Format output AI tidak valid');
                }
            } catch (err) {
                status.style.background = '#fee2e2';
                status.style.color = '#b91c1c';
                status.innerHTML = '<i class="fas fa-circle-exclamation" style="margin-right: 6px;"></i> Gagal menghasilkan soal: ' + err.message;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sparkles"></i> Generate & Simpan Permanen';
            }
        }

        // Searchable Custom Select Penilaian in Soal
        function toggleSearchablePenilaianSoal(e) {
            e.stopPropagation();
            const dd = document.getElementById('dropdownPilihPenilaianSoal');
            const chevron = document.getElementById('chevronPenilaianSoal');
            const isShown = dd.classList.contains('show');
            if (isShown) {
                dd.classList.remove('show');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            } else {
                dd.classList.add('show');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
                const input = document.getElementById('searchPenilaianSoalInput');
                if (input) {
                    input.value = '';
                    filterOptionsPenilaianSoal('');
                    setTimeout(() => input.focus(), 60);
                }
            }
        }

        function filterOptionsPenilaianSoal(query) {
            const q = (query || '').toLowerCase().trim();
            const items = document.querySelectorAll('#listPenilaianSoal .searchable-option-item');
            let matchCount = 0;
            items.forEach(item => {
                const title = item.getAttribute('data-title') || '';
                if (title.includes(q)) {
                    item.style.display = 'flex';
                    matchCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            const emptyHint = document.getElementById('emptyPenilaianSoalHint');
            if (emptyHint) {
                emptyHint.style.display = matchCount === 0 ? 'block' : 'none';
            }
        }

        function selectPenilaianSoal(ujianId) {
            window.location.href = '/admin/soal?ujian_id=' + ujianId;
        }

        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('custom-modal-overlay')) {
                e.target.style.display = 'none';
            }
            const cetakMenu = document.getElementById('cetakDropdownSoal');
            if (cetakMenu && cetakMenu.classList.contains('show') && !e.target.closest('.dropdown-cetak')) {
                cetakMenu.classList.remove('show');
            }
            const wrapPenilaian = document.getElementById('wrapPilihPenilaianSoal');
            if (wrapPenilaian && !wrapPenilaian.contains(e.target)) {
                const dd = document.getElementById('dropdownPilihPenilaianSoal');
                const chevron = document.getElementById('chevronPenilaianSoal');
                if (dd) dd.classList.remove('show');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            }
        });

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.custom-modal-overlay').forEach(m => m.style.display = 'none');
            }
        });
    