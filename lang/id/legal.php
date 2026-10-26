<?php

return array (
  'document_label' => 'Informasi hukum PERINGKAT IDOL',
  'footer_navigation' => 'Dokumen hukum',
  'footer_privacy' => 'Kebijakan Privasi',
  'footer_terms' => 'Ketentuan Layanan',
  'effective_date_label' => 'Tanggal berlaku',
  'effective_date_placeholder' => '[Operator yang harus melengkapi: YYYY-MM-DD]',
  'privacy_title' => 'Kebijakan Privasi',
  'privacy_seo_title' => 'Kebijakan Privasi | IdolRanking',
  'privacy_seo_description' => 'Pelajari bagaimana IdolRanking menangani data akun, login Google, cookie, analitik pengunjung, unggahan publik, dan permintaan privasi.',
  'privacy_intro' => 'Kebijakan ini menjelaskan informasi yang diproses IdolRanking untuk mengoperasikan layanan, mengapa informasi tersebut digunakan, berapa lama informasi tersebut disimpan, penyedia mana yang dapat memprosesnya, dan bagaimana permintaan dapat diajukan. Item yang berada dalam tanda kurung harus diverifikasi dan dilengkapi oleh operator sebelum dipublikasikan.',
  'privacy_sections' =>
  array (
    0 =>
    array (
      'title' => '1. Operator dan kontak',
      'items' =>
      array (
        0 => 'Operator layanan: [Diperlukan tinjauan operator: nama badan hukum atau individu dan detail registrasi]',
        1 => 'Alamat bisnis: [Operator wajib melengkapi: alamat pos lengkap]',
        2 => 'Penanggung jawab atau kontak yang bertanggung jawab atas privasi: [Operator wajib melengkapi: nama, peran, dan cara menghubungi]',
        3 => 'Email kontak privasi: help@idolranking.com — [Operator akan mengkonfirmasi bahwa kotak masuk ini dipantau dan menugaskan penanggung jawab]',
      ),
    ),
    1 =>
    array (
      'title' => '2. Informasi yang diproses dan bagaimana informasi tersebut dikumpulkan',
      'paragraphs' =>
      array (
        0 => 'Proses pendaftaran email menyimpan alamat email, hash kata sandi, nama panggilan yang dipilih, kode negara yang dipilih, status dan waktu verifikasi email, serta stempel waktu akun. Layanan ini tidak menyimpan kata sandi dalam bentuk teks biasa. Atribusi rujukan juga dapat disimpan ketika anggota bergabung melalui rujukan.',
        1 => 'Jika Anda memilih masuk menggunakan Google, layanan tersebut akan meminta openid, email, dan cakupan profil. Layanan tersebut menerima pengidentifikasi akun Google dan alamat email terverifikasi untuk menautkan atau membuat akun. Nama tampilan Google dapat digunakan sebagai saran nama panggilan sementara selama pendaftaran; nama tersebut tidak secara otomatis dipublikasikan sebagai pengganti nama panggilan yang Anda pilih.',
        2 => 'Layanan ini memproses unggahan penggemar, komentar, pesan kehadiran, suka dan rekomendasi, suara, permintaan pendaftaran idola/grup, pilihan bahasa, gambar profil, dan kolom profil publik seperti nama panggilan, kode anggota, negara, dan gambar profil yang dikirimkan oleh anggota untuk menyediakan fitur-fitur tersebut.',
        3 => 'Untuk pengoperasian layanan dan keamanan, aplikasi mencatat jalur permintaan, string User-Agent, asosiasi anggota, dan stempel waktu. Log akses halaman publik tidak menyimpan alamat IP mentah, tetapi riwayat upaya login dan catatan permintaan pengaturan ulang kata sandi menyimpan alamat IP dan string User-Agent. Log hosting atau server web juga dapat berisi alamat IP; isi dan retensi pastinya bergantung pada konfigurasi hosting: [Operator harus memverifikasi log dan retensi aktual].',
        4 => 'Verifikasi email dan pemulihan kata sandi dapat memproses alamat email yang relevan, status verifikasi, token kedaluwarsa yang di-hash, waktu permintaan, dan IP permintaan. Kata sandi dan token dalam bentuk teks biasa tidak disimpan.',
      ),
    ),
    2 =>
    array (
      'title' => '3. Tujuan',
      'items' =>
      array (
        0 => 'Pendaftaran akun dan masuk melalui email atau Google, verifikasi email, pemulihan kata sandi, dan keamanan akun.',
        1 => 'Menyediakan dan memoderasi profil, unggahan penggemar, komentar, pesan kehadiran, suka, rekomendasi, dan permintaan pendaftaran idola/grup.',
        2 => 'Mengelola hadiah Heart dan voting idola/grup, mendeteksi aktivitas duplikat atau penyalahgunaan, dan menanggapi permintaan dukungan.',
        3 => 'Mengukur kunjungan halaman publik dan keandalan layanan. Statistik pengunjung harian menggunakan batas tanggal Asia/Seoul.',
        4 => 'Menerjemahkan unggahan penggemar, komentar, atau pesan kehadiran ketika pengguna meminta terjemahan, dan menyimpan hasilnya untuk ditampilkan.',
        5 => 'Mematuhi hukum, meninjau laporan hak akses, menyelesaikan sengketa, dan melindungi keamanan sistem.',
      ),
    ),
    3 =>
    array (
      'title' => '4. Cookie, sesi, dan analitik pengunjung',
      'paragraphs' =>
      array (
        0 => 'Layanan ini menggunakan cookie sesi untuk mempertahankan status masuk, perlindungan CSRF, pilihan bahasa, dan status permintaan terkait. Penyimpanan dan kedaluwarsa sesi bergantung pada konfigurasi server saat ini. Cookie terpisah menyimpan waktu tampilan Fan Post terbaru untuk membatasi jumlah tampilan duplikat; cookie ini berlaku hingga satu jam.',
        1 => 'Statistik pengunjung menggunakan cookie pihak pertama idolranking_visitor, yang menyimpan pengidentifikasi acak dan saat ini memiliki masa berlaku yang dikonfigurasi selama dua tahun. UV menghitung pengidentifikasi sekali sehari; PV menghitung navigasi halaman HTML publik yang memenuhi syarat. Administrator, akun palsu, dan bot yang diidentifikasi dari penanda User-Agent dikecualikan dari total UV/PV. Klasifikasi User-Agent tidak sempurna dan tidak dapat mengidentifikasi setiap permintaan otomatis.',
        2 => 'Pengidentifikasi pengunjung tidak berasal dari alamat IP. Catatan keamanan login terpisah mungkin berisi alamat IP. Membatasi cookie di browser Anda dapat memengaruhi proses masuk, penyimpanan bahasa, atau kemampuan untuk membedakan pengunjung unik.',
      ),
    ),
    4 =>
    array (
      'title' => '5. Penyedia pihak ketiga dan transfer internasional',
      'paragraphs' =>
      array (
        0 => 'Google OAuth digunakan untuk masuk ke akun Google. Saat pengguna meminta terjemahan, teks yang akan diterjemahkan dan informasi bahasa dikirim ke Google Cloud Translation API. Jika Fan Post menyertakan thumbnail video YouTube, browser dapat meminta gambar langsung dari server gambar YouTube.',
        1 => 'Email verifikasi dan pemulihan kata sandi dapat dikirim melalui penyedia email yang dikonfigurasi untuk produksi. Aplikasi dan basis data dihosting di lingkungan Hostinger. Entitas hukum penyedia, kategori data, lokasi, retensi, subprosesor, dan pengamanan transfer harus dilengkapi dari kontrak aktual dan pengaturan produksi: [Operator melengkapi penyedia, negara, dan pengamanan]. Situs eksternal yang ditautkan dari layanan diatur oleh pemberitahuan privasi mereka sendiri.',
      ),
    ),
    5 =>
    array (
      'title' => '6. Penyimpanan dan penghapusan',
      'paragraphs' =>
      array (
        0 => 'Informasi akun dan konten terkait disimpan sesuai kebutuhan untuk mengoperasikan akun dan memelihara catatan layanan. Aplikasi saat ini tidak menyediakan fitur penutupan akun mandiri. Proses untuk menerima dan menangani permintaan penutupan/penghapusan, postingan terkait, catatan Suka/suara/laporan, dan catatan yang disimpan secara hukum adalah [Operator yang menentukan: ruang lingkup, pengecualian, tenggat waktu, dan kontak yang bertanggung jawab]. Catatan yang dibutuhkan oleh hukum atau untuk melindungi orang lain dapat disimpan secara terpisah atau dengan akses terbatas sesuai dengan aturan yang berlaku.',
        1 => 'Aplikasi dikonfigurasi untuk menghapus baris log akses halaman publik setelah 90 hari. Tidak ditemukan pembersihan otomatis untuk riwayat login, catatan IP permintaan pengaturan ulang kata sandi, atau agregat UV/PV harian dan pengidentifikasi pengunjung; periode retensi dan tugas penghapusannya yang tepat adalah [Operator menentukan per jenis catatan]. Cache terjemahan mungkin tetap ada selama konten sumber tersedia.',
        2 => 'Aturan penyimpanan dan penghapusan bukti laporan hak akses dan file yang diunggah adalah [Operator harus melengkapi: penyimpanan berdasarkan jenis file, akses yang diizinkan, dan metode penghapusan].',
      ),
    ),
    6 =>
    array (
      'title' => '7. Hak dan permintaan Anda',
      'paragraphs' =>
      array (
        0 => 'Saat ini, anggota dapat mengedit beberapa informasi profil dan kata sandi mereka di Halaman Saya. Untuk meminta akses, koreksi, penghapusan, pembatasan, atau penarikan persetujuan berdasarkan hukum yang berlaku, hubungi petugas privasi setelah operator mengkonfirmasi saluran dan prosesnya. Kami dapat memverifikasi identitas dan dapat membatasi permintaan jika hak orang lain atau kewajiban penyimpanan data berdasarkan hukum terpengaruh.',
        1 => 'Fitur penghapusan akun mandiri dan tenggat waktu permintaan-respons belum diimplementasikan dalam aplikasi saat ini. Sebelum dipublikasikan, operator harus menetapkan saluran penerimaan sebenarnya, pemeriksaan identitas, aturan penanganan, dan waktu respons: [Tindakan operator diperlukan].',
      ),
    ),
    7 =>
    array (
      'title' => '8. Konten dan profil publik',
      'paragraphs' =>
      array (
        0 => 'Unggahan publik, komentar, dan kolom profil seperti nama panggilan, negara, dan gambar profil mungkin terlihat oleh pengunjung yang tidak masuk dan mesin pencari. Alamat email, hash kata sandi, dan catatan masuk/keamanan tidak ditampilkan pada profil publik. Setelah konten dihapus atau disembunyikan, salinannya mungkin tetap ada untuk sementara waktu dalam cadangan, bukti laporan hak cipta, cache, atau catatan yang disimpan oleh hukum; operator harus menentukan periode tersebut.',
      ),
    ),
    8 =>
    array (
      'title' => '9. Langkah-langkah keamanan',
      'paragraphs' =>
      array (
        0 => 'Layanan ini menggunakan hashing kata sandi, perlindungan terhadap pemalsuan permintaan, regenerasi ID sesi setelah masuk, pemeriksaan otorisasi, dan penyimpanan file publik/pribadi yang terpisah. Organisasi keamanan yang bertanggung jawab, proses pemberitahuan insiden, jadwal peninjauan akses, dan kontak pelanggaran adalah [Operator yang akan memverifikasi]. Tidak ada layanan internet yang dapat menghilangkan setiap risiko keamanan.',
      ),
    ),
    9 =>
    array (
      'title' => '10. Kontak dan perubahan kebijakan',
      'paragraphs' =>
      array (
        0 => 'Kontak privasi: help@idolranking.com ([Operator akan mengkonfirmasi bahwa ini adalah alamat resmi yang dipantau]). Jangan mengirimkan kata sandi atau token otentikasi. Berikan hanya informasi akun minimum yang diperlukan untuk menangani permintaan Anda. Kontak yang bertanggung jawab, alamat, hukum yang berlaku, batas waktu respons, dan proses pemberitahuan perubahan adalah [Operator akan melengkapi].',
        1 => 'Apabila kebijakan ini berubah, penyedia layanan akan memperbarui halaman ini dan tanggal efektifnya. Operator harus menentukan bagaimana pemberitahuan sebelumnya dan persetujuan perpanjangan yang diwajibkan secara hukum akan ditangani: [Diperlukan tinjauan operator].',
      ),
    ),
  ),
  'terms_title' => 'Ketentuan Layanan',
  'terms_seo_title' => 'Syarat dan Ketentuan Layanan | IdolRanking',
  'terms_seo_description' => 'Bacalah peraturan untuk akun IdolRanking, unggahan penggemar, komentar, permintaan idola, hadiah hati, pemungutan suara, dan penggunaan layanan.',
  'terms_intro' => 'Syarat dan ketentuan ini menjelaskan fitur-fitur yang saat ini disediakan oleh IdolRanking dan tanggung jawab dasar pengguna serta operator. Detail hukum dan operasional dalam tanda kurung harus diselesaikan oleh operator.',
  'terms_sections' =>
  array (
    0 =>
    array (
      'title' => '1. Layanan dan operator',
      'paragraphs' =>
      array (
        0 => 'IdolRanking adalah layanan komunitas yang menyediakan peringkat idola/grup, unggahan dan komentar penggemar, pesan kehadiran, hadiah Hati dan pemungutan suara, serta permintaan pendaftaran idola/grup.',
        1 => 'Operator: [Operator perlu meninjau: nama badan hukum atau individu]; alamat: [Operator harus melengkapi]; kontak: help@idolranking.com ([Operator harus mengkonfirmasi bahwa ini adalah saluran kontak resmi]).',
      ),
    ),
    1 =>
    array (
      'title' => '2. Akun dan kelayakan',
      'paragraphs' =>
      array (
        0 => 'Pendaftaran melalui email memerlukan verifikasi email. Pendaftaran melalui Google menggunakan alamat email yang telah diverifikasi oleh Google dan pengenal tautan akun. Anggota harus memberikan informasi yang akurat dan melindungi kredensial mereka. Anggota yang mendaftar melalui Google memilih nama panggilan dan negara mereka sendiri.',
        1 => 'Persyaratan usia minimum dan persetujuan wali adalah [Operator akan menentukan untuk setiap yurisdiksi yang berlaku]. Jangan menyatakan layanan ini cocok untuk kelompok usia tertentu sampai aturan ini dikonfirmasi.',
      ),
    ),
    2 =>
    array (
      'title' => '3. Tanggung jawab anggota dan perilaku yang dilarang',
      'items' =>
      array (
        0 => 'Menggunakan akun orang lain atau melewati keamanan akun, otentikasi, pembatasan akses, atau kontrol layanan.',
        1 => 'Menggunakan otomatisasi, banyak akun, atau aktivitas terkoordinasi untuk memanipulasi hadiah Heart, suara, peringkat, atau statistik pengunjung, atau memperoleh keuntungan yang tidak semestinya.',
        2 => 'Mengunggah materi ilegal, curang, mengancam, melecehkan, penuh kebencian, memfitnah, melanggar privasi, atau bersifat kasar yang ditargetkan.',
        3 => 'Mengirimkan materi yang melanggar hak cipta, merek dagang, hak potret, privasi, atau ketentuan layanan pihak ketiga.',
        4 => 'Pengumpulan atau pengambilan data secara berlebihan, pencarian kerentanan, pengiriman kode berbahaya, atau gangguan pada sistem.',
      ),
    ),
    3 =>
    array (
      'title' => '4. Unggahan penggemar, komentar, dan konten pengguna',
      'paragraphs' =>
      array (
        0 => 'Anggota harus memiliki hak dan izin yang diperlukan untuk unggahan penggemar, komentar, pesan kehadiran, gambar, dan permintaan pendaftaran, serta harus menghormati hukum dan hak orang lain. Penulis tetap mempertahankan haknya; pengunggahan tidak mengalihkan kepemilikan kepada operator.',
        1 => 'Untuk mengoperasikan layanan ini, anggota memberikan izin kepada operator yang tidak eksklusif dan terbatas pada layanan tertentu untuk menyimpan dan mencadangkan konten, mengonversinya ke format yang diperlukan, mengirimkannya, dan menampilkannya kepada pengguna dan pengunjung layanan. Batas waktu pasti izin ini dan durasi salinan cadangan/bukti laporan memerlukan [peninjauan hukum oleh Operator]. Teks ini tidak memberikan izin penggunaan untuk iklan atau penjualan di luar layanan.',
        2 => 'Permohonan pendaftaran idola/grup adalah proposal untuk ditinjau, bukan janji persetujuan, waktu pendaftaran, atau imbalan tertentu. Operator dapat meninjau, mengedit, atau menolak permohonan karena alasan keakuratan, duplikasi, atau masalah hak cipta.',
      ),
    ),
    4 =>
    array (
      'title' => '5. Hak cipta, hak potret, laporan, dan moderasi',
      'paragraphs' =>
      array (
        0 => 'Jangan memposting karya, foto, gambar, atau informasi pribadi orang lain tanpa izin. Layanan ini menyediakan fitur pelaporan untuk postingan/komentar penggemar dan gambar profil. Sertakan konten spesifik dan alasan pelaporan. Operator dapat meninjau materi tersebut dan menyembunyikan, mengembalikan, atau menghapusnya, atau meminta informasi lebih lanjut.',
        1 => 'Rincian pemberitahuan hukum yang diperlukan, tenggat waktu tanggapan, banding, dan prosedur pelanggar berulang adalah [Operator wajib melengkapi sesuai dengan hukum yang berlaku]. Alat pelaporan yang diimplementasikan tidak mengklaim untuk menggantikan setiap proses pemberitahuan dan penghapusan yang diatur dalam undang-undang.',
      ),
    ),
    5 =>
    array (
      'title' => '6. Hadiah hati dan pemungutan suara',
      'paragraphs' =>
      array (
        0 => 'Hati adalah nilai dalam layanan yang digunakan untuk hadiah aktivitas yang saat ini diterapkan dan pemungutan suara idola/grup. Hati tidak memiliki nilai uang, tidak dapat ditukarkan atau ditransfer, dan saat ini tidak dapat dibeli. Jenis hadiah, rentang, batasan harian, dan kelayakan mengikuti pengaturan fitur dan administrator yang berlaku; pengaturan dapat mengubah ketentuan hadiah di masa mendatang.',
        1 => 'Suara diproses berdasarkan aturan pemungutan suara saat ini dan saldo Hati yang tersedia. Hadiah atau suara yang diperoleh melalui otomatisasi, duplikasi, atau aktivitas palsu dapat ditinjau dan dikoreksi atau dibatalkan. Proses pemberitahuan dan banding untuk hadiah yang salah adalah [Diperlukan tinjauan operator: tentukan kriteria penanganan].',
      ),
    ),
    6 =>
    array (
      'title' => '7. Pengukuran konten dan akun',
      'paragraphs' =>
      array (
        0 => 'Untuk alasan hukum, perlindungan hak, keamanan, atau dugaan pelanggaran, operator dapat menyembunyikan atau menghapus unggahan, komentar, gambar, atau permintaan pendaftaran, menyesuaikan hadiah, atau membatasi/menangguhkan akses akun. Jika memungkinkan, operator akan memberikan alasan dan metode kontak; kasus keamanan atau hukum yang mendesak mungkin memerlukan tindakan segera.',
        1 => 'Durasi penangguhan, saluran banding, penutupan/penghapusan akun atas permintaan anggota, dan penanganan Heart yang tidak digunakan adalah [Operator akan menyelesaikan]. Aplikasi saat ini tidak menyediakan fitur penghapusan akun mandiri.',
      ),
    ),
    7 =>
    array (
      'title' => '8. Perubahan dan ketersediaan layanan',
      'paragraphs' =>
      array (
        0 => 'Operator dapat mengubah atau menghentikan fitur, layar, pengaturan hadiah, atau tampilan konten karena alasan hukum, keamanan, teknis, atau operasional. Pemeliharaan atau gangguan dapat mengganggu layanan untuk sementara waktu. Layanan eksternal seperti Google, penyedia email, dan YouTube dikendalikan oleh penyedia masing-masing.',
        1 => 'Sejauh diizinkan oleh hukum yang berlaku, tanggung jawab dapat dibatasi untuk kegagalan eksternal, perilaku pengguna, atau peristiwa di luar kendali wajar operator. Tidak ada yang membatasi hak konsumen atau privasi yang wajib. Hukum yang berlaku dan ketentuan tanggung jawab akhir memerlukan [peninjauan hukum Operator].',
      ),
    ),
    8 =>
    array (
      'title' => '9. Catatan perubahan dan penerimaan',
      'paragraphs' =>
      array (
        0 => 'Perubahan akan diposting di halaman ini beserta isi dan tanggal efektifnya. Operator harus menentukan pemberitahuan terlebih dahulu, kapan perubahan berlaku untuk anggota yang sudah ada, dan kapan persetujuan ulang diperlukan: [Operator akan menyelesaikan]. Aplikasi saat ini tampaknya tidak mencatat penerimaan berdasarkan versi kebijakan, jadi operator harus memutuskan bagaimana penerimaan akan disajikan dan dicatat.',
      ),
    ),
    9 =>
    array (
      'title' => '10. Kontak',
      'paragraphs' =>
      array (
        0 => 'Untuk persyaratan, laporan konten, atau pembatasan akun: help@idolranking.com ([Operator akan mengkonfirmasi penanggung jawab, kotak masuk yang dipantau, dan proses respons]). Identitas operator, alamat bisnis, hukum yang berlaku, tempat sengketa, dan batas waktu respons harus diisi sebelum publikasi: [Tindakan operator diperlukan].',
      ),
    ),
  ),
);
