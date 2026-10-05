import express from 'express';
import session from 'express-session';
import cookieParser from 'cookie-parser';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';
import bcrypt from 'bcryptjs';
import { GoogleGenAI } from '@google/genai';
import { generateExamDocx, generateMatrixDocx } from './utils/docxGenerator.js';
import { getUjianFormatCetak } from './utils/formatCetakHelper.js';
import multer from 'multer';
import * as XLSX from 'xlsx';
import { db } from './data/store.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = 3000;

// Configure multer for file uploads (XLSX import)
const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 10 * 1024 * 1024 }
});

// Configure multer for Logo uploads
const logoStorage = multer.diskStorage({
  destination: (req, file, cb) => {
    const dir = path.join(__dirname, 'admin/uploads');
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    cb(null, dir);
  },
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase() || '.png';
    const cleanName = path.basename(file.originalname, ext).replace(/[^a-zA-Z0-9_-]/g, '_');
    cb(null, `logo_${Date.now()}_${cleanName}${ext}`);
  }
});
const uploadLogo = multer({
  storage: logoStorage,
  limits: { fileSize: 10 * 1024 * 1024 }
});

// Enable trust proxy for Cloud Run iframe environment
app.set('trust proxy', 1);

// Setup template engine & static folders
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

app.use(express.urlencoded({ extended: true, limit: '20mb' }));
app.use(express.json({ limit: '20mb' }));
app.use(cookieParser());

// Auto-persist middleware for POST requests that alter state
app.use((req, res, next) => {
  if (req.method === 'POST') {
    res.on('finish', () => {
      if (res.statusCode >= 200 && res.statusCode < 400) {
        try { db.save(); } catch (e) {}
      }
    });
  }
  next();
});

// Periodic save to keep disk persistence synced
const persistInterval = setInterval(() => {
  try { db.save(); } catch (e) {}
}, 10000);
persistInterval.unref();

// In-memory cross-request session cache (resilient to 3rd-party cookie blocking in iframes)
const activeSessions = new Map();
let lastActiveAdminSession = null;
let lastActivePesertaSession = null;

app.use(session({
  name: 'sipena_session',
  secret: process.env.SESSION_SECRET || 'sipena-secret-key-2026',
  resave: true,
  saveUninitialized: true,
  proxy: true,
  cookie: {
    maxAge: 24 * 60 * 60 * 1000,
    httpOnly: true,
    sameSite: 'none',
    secure: true
  }
}));

// Session synchronizer middleware
app.use((req, res, next) => {
  const sid = req.query.sid || req.body?.sid || req.headers['x-session-id'] || req.cookies?.sipena_session_id;
  if (sid && activeSessions.has(sid)) {
    const cached = activeSessions.get(sid);
    for (const [k, v] of Object.entries(cached)) {
      if (req.session[k] === undefined || req.session[k] === null) {
        req.session[k] = v;
      }
    }
  }

  // Fallback to active sessions in container if session lost in iframe (only on protected pages)
  const isAuthPage = req.path.startsWith('/admin/login') || req.path.startsWith('/peserta_login') || req.path.startsWith('/lupa_password');
  if (!isAuthPage) {
    if (!req.session.admin_id && lastActiveAdminSession) {
      Object.assign(req.session, lastActiveAdminSession);
    }
    if (!req.session.peserta_id && lastActivePesertaSession) {
      Object.assign(req.session, lastActivePesertaSession);
    }
  }

  res.saveToActiveSessions = () => {
    const curSid = req.sessionID || sid;
    if (curSid) {
      activeSessions.set(curSid, {
        admin_id: req.session.admin_id,
        admin_username: req.session.admin_username,
        admin_nama: req.session.admin_nama,
        admin_role: req.session.admin_role,
        peserta_id: req.session.peserta_id,
        peserta_username: req.session.peserta_username,
        peserta_nama: req.session.peserta_nama,
        peserta_nim: req.session.peserta_nim,
        sesi_id: req.session.sesi_id,
        ujian_id: req.session.ujian_id,
        mahasiswa_id: req.session.mahasiswa_id,
        exam_started: req.session.exam_started,
        exam_start_time: req.session.exam_start_time,
        exam_duration: req.session.exam_duration,
        nama_peserta: req.session.nama_peserta,
        nim: req.session.nim,
        prodi: req.session.prodi,
        kelas: req.session.kelas,
        semester: req.session.semester,
        mata_kuliah: req.session.mata_kuliah,
        judul_ujian: req.session.judul_ujian,
        shuffled_options: req.session.shuffled_options,
        answers: req.session.answers,
        resume_soal: req.session.resume_soal
      });
      res.cookie('sipena_session_id', curSid, {
        maxAge: 24 * 60 * 60 * 1000,
        sameSite: 'none',
        secure: true
      });
    }
    return curSid;
  };

  const originalRedirect = res.redirect.bind(res);
  res.redirect = (url) => {
    res.saveToActiveSessions();
    return originalRedirect(url);
  };

  res.locals.sid = req.sessionID || sid || '';
  res.locals.adminRole = req.session.admin_role || (lastActiveAdminSession ? lastActiveAdminSession.admin_role : '');
  res.locals.adminNama = req.session.admin_nama || (lastActiveAdminSession ? lastActiveAdminSession.admin_nama : '');

  next();
});

// Static files
app.use('/assets', express.static(path.join(__dirname, 'assets')));
app.use('/admin/uploads', express.static(path.join(__dirname, 'admin/uploads')));

// Middleware to clean up expired sessions periodically
app.use((req, res, next) => {
  db.cleanupExpiredSessions();
  next();
});

// Middleware for Admin Authentication
const requireAdmin = (req, res, next) => {
  const sid = req.query.sid || req.body?.sid || req.cookies?.sipena_session_id;
  if (!req.session.admin_id && sid && activeSessions.has(sid)) {
    const cached = activeSessions.get(sid);
    if (cached.admin_id) {
      Object.assign(req.session, cached);
    }
  }

  if (!req.session.admin_id && lastActiveAdminSession) {
    Object.assign(req.session, lastActiveAdminSession);
  }

  if (req.session.admin_id) {
    res.locals.adminRole = req.session.admin_role || '';
    res.locals.adminNama = req.session.admin_nama || '';
    return next();
  }

  return res.redirect('/admin/login');
};

const requireAdminOnly = (req, res, next) => {
  const sid = req.query.sid || req.body?.sid || req.cookies?.sipena_session_id;
  if (!req.session.admin_id && sid && activeSessions.has(sid)) {
    const cached = activeSessions.get(sid);
    if (cached.admin_id) {
      Object.assign(req.session, cached);
    }
  }

  if (!req.session.admin_id && lastActiveAdminSession) {
    Object.assign(req.session, lastActiveAdminSession);
  }

  if (!req.session.admin_id) {
    return res.redirect('/admin/login');
  }

  if (req.session.admin_role !== 'admin') {
    return res.redirect('/admin/dashboard');
  }

  res.locals.adminRole = req.session.admin_role || '';
  res.locals.adminNama = req.session.admin_nama || '';
  next();
};

/* ==========================================================================
   PARTICIPANT / STUDENT ROUTES
   ========================================================================== */

// GET / - Select Exam / Homepage
app.get(['/', '/index.php'], (req, res) => {
  const sid = req.query.sid || req.cookies?.sipena_session_id;
  if (!req.session.peserta_id && sid && activeSessions.has(sid)) {
    const cached = activeSessions.get(sid);
    if (cached.peserta_id) Object.assign(req.session, cached);
  }

  if (!req.session.peserta_id && lastActivePesertaSession) {
    Object.assign(req.session, lastActivePesertaSession);
  }

  if (req.session.sesi_id && req.session.exam_started) {
    return res.redirect('/ujian');
  }

  if (!req.session.peserta_id) {
    return res.redirect('/peserta_login');
  }

  const prodiList = db.getProdi();

  // Find student's assigned kelas and prodi for smart automatic pre-selection
  let pesertaKelasId = null;
  let pesertaProdiId = null;
  let pesertaKelasNama = '';
  const user = db.users.find(u => u.id === req.session.peserta_id);
  const mhs = db.mahasiswa.find(m => m.id_user === req.session.peserta_id || (req.session.peserta_nim && m.nim === req.session.peserta_nim));
  const kId = (user && user.id_kelas) || (mhs && mhs.id_kelas);
  if (kId) {
    const k = db.kelas.find(kls => kls.id === kId);
    if (k) {
      pesertaKelasId = k.id;
      pesertaProdiId = k.id_program_studi;
      pesertaKelasNama = k.nama_kelas;
    }
  }

  res.render('index', {
    pesertaNama: req.session.peserta_nama,
    pesertaNim: req.session.peserta_nim,
    pesertaKelasId,
    pesertaProdiId,
    pesertaKelasNama,
    prodiList,
    error: req.query.error || ''
  });
});

// POST / or /mulai_ujian - Start Exam Session
app.post(['/', '/mulai_ujian'], (req, res) => {
  if (!req.session.peserta_id) {
    return res.redirect('/peserta_login');
  }

  const nama = (req.body.nama || '').trim();
  const nim = (req.body.nim || '').trim();
  const prodiId = parseInt(req.body.prodi_id, 10);
  const kelasId = parseInt(req.body.kelas_id, 10);
  const ujianId = parseInt(req.body.ujian_id, 10);

  if (!nama || !nim || !prodiId || !kelasId || !ujianId) {
    return res.redirect('/?error=' + encodeURIComponent('Semua field wajib diisi!'));
  }

  const mahasiswaId = db.getOrCreateMahasiswa(nim, nama, kelasId, req.session.peserta_id);

  const existingSesi = db.checkExistingSesi(ujianId, mahasiswaId);
  const ongoingSesi = db.getActiveSesi(ujianId, mahasiswaId);

  if (existingSesi) {
    return res.redirect('/?error=' + encodeURIComponent('Anda sudah melakukan ujian sebelumnya dan tidak dapat mengikuti ujian ini kembali.'));
  }

  if (ongoingSesi) {
    return res.redirect('/?error=' + encodeURIComponent('Anda sudah memiliki sesi ujian ini yang sedang berlangsung.'));
  }

  const soalList = db.getSoalByUjian(ujianId);
  const shuffledOptions = db.shuffleOptionsForSesi(soalList);

  const newSesiId = db.sesi_ujian.length ? Math.max(...db.sesi_ujian.map(s => s.id)) + 1 : 1;
  const newSesi = {
    id: newSesiId,
    id_ujian: ujianId,
    id_mahasiswa: mahasiswaId,
    waktu_mulai: new Date(),
    waktu_selesai: null,
    nilai_total: 0,
    status: 'berlangsung',
    shuffled_options: shuffledOptions,
    jawaban_draft: {},
    soal_terakhir: 1
  };
  db.sesi_ujian.push(newSesi);
  db.save();

  const ujianData = db.ujian.find(u => u.id === ujianId);
  const matkul = ujianData ? db.mata_kuliah.find(m => m.id === ujianData.id_mata_kuliah) : null;
  const kelasData = db.kelas.find(k => k.id === kelasId);
  const prodiData = kelasData ? db.program_studi.find(p => p.id === kelasData.id_program_studi) : null;

  req.session.sesi_id = newSesiId;
  req.session.ujian_id = ujianId;
  req.session.mahasiswa_id = mahasiswaId;
  req.session.exam_started = true;
  req.session.exam_start_time = Date.now();
  req.session.exam_duration = (ujianData ? ujianData.durasi_menit : 90) * 60;
  req.session.nama_peserta = nama;
  req.session.nim = nim;
  req.session.prodi = prodiData ? prodiData.nama_prodi : '-';
  req.session.kelas = kelasData ? kelasData.nama_kelas : '-';
  req.session.semester = kelasData ? kelasData.semester : '-';
  req.session.mata_kuliah = matkul ? matkul.nama_mk : '-';
  req.session.judul_ujian = ujianData ? ujianData.judul_ujian : '-';
  req.session.shuffled_options = shuffledOptions;
  req.session.answers = {};

  res.redirect('/ujian');
});

// GET /peserta_login - Student Login Page
app.get(['/peserta_login', '/peserta_login.php'], (req, res) => {
  if (req.session.peserta_id) {
    return res.redirect('/');
  }

  if (req.session.sesi_id && req.session.exam_started) {
    return res.redirect('/ujian');
  }

  res.render('peserta_login', {
    status: req.query.status || '',
    error: req.query.error || '',
    username: req.query.username || ''
  });
});

// POST /peserta_login - Handle Student Authentication
app.post(['/peserta_login', '/peserta_login.php'], (req, res) => {
  const username = (req.body.username || '').trim();
  const password = req.body.password || '';

  if (!username || !password) {
    return res.render('peserta_login', {
      status: '',
      error: 'Username dan password wajib diisi!',
      username
    });
  }

  const user = db.verifyCredentials(username, password);
  if (!user) {
    return res.render('peserta_login', {
      status: '',
      error: 'Username atau password salah! Silakan gunakan akun demo yang tertera di bawah.',
      username
    });
  }

  // If role is admin / dosen / pengawas, seamlessly authenticate to admin dashboard
  if (['admin', 'dosen', 'pengawas'].includes(user.role)) {
    req.session.admin_id = user.id;
    req.session.admin_username = user.username;
    req.session.admin_nama = user.nama_lengkap;
    req.session.admin_role = user.role;
    lastActiveAdminSession = {
      admin_id: user.id,
      admin_username: user.username,
      admin_nama: user.nama_lengkap,
      admin_role: user.role
    };
    res.saveToActiveSessions();
    return res.redirect('/admin/dashboard');
  }

  // Peserta / Mahasiswa authentication
  user.is_logged_in = true;
  user.last_login = new Date();
  db.save();

  req.session.peserta_id = user.id;
  req.session.peserta_username = user.username;
  req.session.peserta_nama = user.nama_lengkap;
  req.session.peserta_nim = user.nim || user.username;
  lastActivePesertaSession = {
    peserta_id: user.id,
    peserta_username: user.username,
    peserta_nama: user.nama_lengkap,
    peserta_nim: user.nim || user.username
  };

  // Check ongoing session
  const activeSesi = db.getActiveSesiByUserId(user.id);
  if (activeSesi) {
    const startEpoch = new Date(activeSesi.waktu_mulai).getTime();
    const durasiDetik = (activeSesi.durasi_menit || 90) * 60;
    const elapsed = Math.floor((Date.now() - startEpoch) / 1000);
    const remaining = durasiDetik - elapsed;

    if (remaining > 0) {
      req.session.sesi_id = activeSesi.id;
      req.session.ujian_id = activeSesi.id_ujian;
      req.session.mahasiswa_id = activeSesi.id_mahasiswa;
      req.session.exam_started = true;
      req.session.exam_start_time = startEpoch;
      req.session.exam_duration = durasiDetik;
      req.session.nama_peserta = activeSesi.nama_lengkap;
      req.session.nim = activeSesi.nim;
      req.session.prodi = activeSesi.nama_prodi;
      req.session.kelas = activeSesi.nama_kelas;
      req.session.semester = activeSesi.semester;
      req.session.mata_kuliah = activeSesi.nama_mk;
      req.session.judul_ujian = activeSesi.judul_ujian;
      req.session.shuffled_options = activeSesi.shuffled_options || {};
      req.session.answers = activeSesi.jawaban_draft || {};
      req.session.resume_soal = activeSesi.soal_terakhir || 1;

      res.saveToActiveSessions();
      return res.redirect('/ujian?no=' + req.session.resume_soal);
    } else {
      activeSesi.status = 'timeout';
      activeSesi.waktu_selesai = new Date();
    }
  }

  res.saveToActiveSessions();
  return res.redirect('/');
});

// GET /peserta_logout
app.get(['/peserta_logout', '/peserta_logout.php', '/logout.php'], (req, res) => {
  lastActivePesertaSession = null;
  const sid = req.query.sid || req.sessionID;
  if (sid && activeSessions.has(sid)) {
    activeSessions.delete(sid);
  }
  res.clearCookie('sipena_session_id');
  req.session.destroy(() => {
    res.redirect('/peserta_login');
  });
});

// GET /ujian - Student Exam Interface
app.get(['/ujian', '/ujian.php'], (req, res) => {
  if (!req.session.sesi_id || !req.session.exam_started) {
    return res.redirect('/');
  }

  const startTime = req.session.exam_start_time;
  const duration = req.session.exam_duration || 5400;
  const elapsed = Math.floor((Date.now() - startTime) / 1000);
  const remaining = Math.max(0, duration - elapsed);

  if (remaining <= 0) {
    return res.redirect('/submit?auto=1');
  }

  const soalList = db.getSoalByUjian(req.session.ujian_id);
  if (!soalList || soalList.length === 0) {
    return res.send('Belum ada soal pada ujian ini.');
  }

  const dbSesi = db.sesi_ujian.find(s => s.id === req.session.sesi_id);
  const shuffledOptions = (dbSesi && dbSesi.shuffled_options) || req.session.shuffled_options || {};
  const answers = req.session.answers || {};

  let currentSoalNo = parseInt(req.query.no, 10) || 1;
  currentSoalNo = Math.max(1, Math.min(currentSoalNo, soalList.length));
  const current = soalList[currentSoalNo - 1];

  res.render('ujian', {
    sesiId: req.session.sesi_id,
    ujianId: req.session.ujian_id,
    namaPeserta: req.session.nama_peserta,
    nimPeserta: req.session.nim,
    prodi: req.session.prodi,
    kelas: req.session.kelas,
    semester: req.session.semester,
    mataKuliah: req.session.mata_kuliah,
    judulUjian: req.session.judul_ujian,
    soalList,
    currentSoalNo,
    current,
    shuffledOptions,
    answers,
    remaining
  });
});

// POST /ujian or /api/save_answer - Save Question Answer
app.post(['/ujian', '/ujian.php', '/api/save_answer'], (req, res) => {
  const soalId = parseInt(req.body.soal_id, 10);
  const jawaban = req.body.jawaban;
  const noSoal = parseInt(req.body.no_soal, 10) || 1;

  if (req.session.sesi_id) {
    if (!req.session.answers) req.session.answers = {};
    req.session.answers[soalId] = jawaban;

    const sesi = db.sesi_ujian.find(s => s.id === req.session.sesi_id);
    if (sesi) {
      if (!sesi.jawaban_draft) sesi.jawaban_draft = {};
      sesi.jawaban_draft[soalId] = jawaban;
      sesi.soal_terakhir = noSoal;
      db.save();
    }
    return res.json({ success: true });
  }

  res.status(400).json({ success: false, message: 'Session not active' });
});

// POST or GET /submit - Complete Exam
app.all(['/submit', '/submit.php'], (req, res) => {
  if (!req.session.sesi_id) {
    return res.redirect('/');
  }

  const sesiId = req.session.sesi_id;
  const ujianId = req.session.ujian_id;
  const dbSesi = db.sesi_ujian.find(s => s.id === sesiId);

  if (!dbSesi || dbSesi.status === 'selesai') {
    return res.redirect('/peserta_login?status=selesai');
  }

  const answers = req.session.answers || dbSesi.jawaban_draft || {};
  const shuffledOptions = dbSesi.shuffled_options || req.session.shuffled_options || {};
  const soalList = db.getSoalByUjian(ujianId);

  const scores = db.calculateScore(sesiId, answers, shuffledOptions, soalList);

  dbSesi.waktu_selesai = new Date();
  dbSesi.nilai_total = scores.total;
  dbSesi.status = 'selesai';
  db.save();

  req.session.exam_result = scores;
  req.session.exam_completed = true;
  delete req.session.exam_started;
  delete req.session.exam_start_time;

  res.redirect('/hasil');
});

// GET /hasil - Result Page
app.get(['/hasil', '/hasil.php'], (req, res) => {
  if (!req.session.sesi_id || !req.session.exam_completed) {
    return res.redirect('/');
  }

  const sesi = db.sesi_ujian.find(s => s.id === req.session.sesi_id);
  const ujian = db.ujian.find(u => u.id === req.session.ujian_id);
  const matkul = ujian ? db.mata_kuliah.find(m => m.id === ujian.id_mata_kuliah) : null;
  const soalList = db.getSoalByUjian(req.session.ujian_id);
  const scores = req.session.exam_result || { total: sesi ? sesi.nilai_total : 0, details: {} };
  const answers = req.session.answers || (sesi ? sesi.jawaban_draft : {}) || {};

  res.render('hasil', {
    ujian: { ...ujian, nama_mk: matkul ? matkul.nama_mk : '-' },
    sesi,
    soalList,
    scores,
    answers,
    namaPeserta: req.session.nama_peserta,
    nimPeserta: req.session.nim
  });
});

/* ==========================================================================
   ADMIN & LECTURER ROUTES
   ========================================================================== */

app.get(['/admin', '/admin/'], (req, res) => {
  res.redirect('/admin/dashboard');
});

app.get(['/admin/login', '/admin/login.php'], (req, res) => {
  const sid = req.query.sid || req.cookies?.sipena_session_id;
  if (!req.session.admin_id && sid && activeSessions.has(sid)) {
    const cached = activeSessions.get(sid);
    if (cached.admin_id) Object.assign(req.session, cached);
  }

  if (req.session.admin_id) {
    return res.redirect('/admin/dashboard');
  }
  res.render('admin/login', { error: req.query.error || '' });
});

app.post(['/admin/login', '/admin/login.php'], (req, res) => {
  const username = (req.body.username || '').trim();
  const password = req.body.password || '';

  if (!username || !password) {
    return res.render('admin/login', { error: 'Username dan password wajib diisi!' });
  }

  const user = db.verifyCredentials(username, password);
  if (!user) {
    return res.render('admin/login', { error: 'Username atau password salah! Silakan gunakan akun demo yang tertera di bawah.' });
  }

  if (['admin', 'dosen', 'pengawas'].includes(user.role)) {
    req.session.admin_id = user.id;
    req.session.admin_username = user.username;
    req.session.admin_nama = user.nama_lengkap;
    req.session.admin_role = user.role;
    lastActiveAdminSession = {
      admin_id: user.id,
      admin_username: user.username,
      admin_nama: user.nama_lengkap,
      admin_role: user.role
    };
    res.saveToActiveSessions();
    return res.redirect('/admin/dashboard');
  }

  // If student tries login on admin page, seamlessly authenticate and send to exam portal
  if (user.role === 'peserta') {
    user.is_logged_in = true;
    user.last_login = new Date();
    db.save();

    req.session.peserta_id = user.id;
    req.session.peserta_username = user.username;
    req.session.peserta_nama = user.nama_lengkap;
    req.session.peserta_nim = user.nim || user.username;
    lastActivePesertaSession = {
      peserta_id: user.id,
      peserta_username: user.username,
      peserta_nama: user.nama_lengkap,
      peserta_nim: user.nim || user.username
    };
    res.saveToActiveSessions();
    return res.redirect('/');
  }

  res.render('admin/login', { error: 'Username atau password salah!' });
});

app.get(['/logout', '/peserta_logout'], (req, res) => {
  if (req.session.peserta_id) {
    const user = db.users.find(u => u.id === req.session.peserta_id);
    if (user) {
      user.is_logged_in = false;
      db.save();
    }
  }
  lastActivePesertaSession = null;
  const sid = req.query.sid || req.sessionID;
  if (sid && activeSessions.has(sid)) {
    activeSessions.delete(sid);
  }
  delete req.session.peserta_id;
  delete req.session.peserta_username;
  delete req.session.peserta_nama;
  delete req.session.peserta_nim;
  delete req.session.sesi_id;
  delete req.session.exam_started;
  res.redirect('/peserta_login');
});

app.get(['/admin/logout', '/admin/logout.php'], (req, res) => {
  lastActiveAdminSession = null;
  const sid = req.query.sid || req.sessionID;
  if (sid && activeSessions.has(sid)) {
    activeSessions.delete(sid);
  }
  delete req.session.admin_id;
  delete req.session.admin_username;
  delete req.session.admin_nama;
  delete req.session.admin_role;
  res.clearCookie('sipena_session_id');
  res.redirect('/admin/login');
});

// Admin Dashboard
app.get(['/admin/dashboard', '/admin/dashboard.php'], requireAdmin, (req, res) => {
  const totalUjian = db.ujian.length;
  const totalSoal = db.soal.length;
  const totalPeserta = db.sesi_ujian.filter(s => s.status === 'selesai').length;
  const totalMahasiswa = db.mahasiswa.length;

  const recentExams = db.ujian.slice(0, 5).map(u => {
    const matkul = db.mata_kuliah.find(m => m.id === u.id_mata_kuliah);
    const count = db.sesi_ujian.filter(s => s.id_ujian === u.id && s.status === 'selesai').length;
    return {
      ...u,
      nama_mk: matkul ? matkul.nama_mk : '-',
      peserta_count: count
    };
  });

  res.render('admin/dashboard', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    totalUjian,
    totalSoal,
    totalPeserta,
    totalMahasiswa,
    recentExams
  });
});

// Admin Ujian Management
app.get(['/admin/ujian', '/admin/ujian.php'], requireAdmin, (req, res) => {
  const exams = db.ujian.map(u => {
    const matkul = db.mata_kuliah.find(m => m.id === u.id_mata_kuliah);
    const totalPoin = db.soal.filter(s => s.id_ujian === u.id).reduce((sum, s) => sum + (s.poin || 0), 0);
    const assignedKelasIds = db.ujian_kelas.filter(uk => uk.id_ujian === u.id).map(uk => uk.id_kelas);
    const assignedKelas = db.kelas.filter(k => assignedKelasIds.includes(k.id));
    return {
      ...u,
      nama_mk: matkul ? matkul.nama_mk : '-',
      total_nilai: totalPoin || u.total_nilai,
      kelas_list: assignedKelas
    };
  });

  res.render('admin/ujian', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    exams,
    matkulList: db.mata_kuliah,
    kelasList: db.kelas,
    message: req.query.msg || '',
    error: req.query.err || ''
  });
});

app.post(['/admin/ujian', '/admin/ujian.php'], requireAdmin, (req, res) => {
  const action = req.body.action;

  if (action === 'create') {
    const judul = (req.body.judul_ujian || '').trim();
    const matkulId = parseInt(req.body.id_mata_kuliah, 10);
    const jenis = req.body.jenis_ujian || 'UTS';
    const durasi = parseInt(req.body.durasi_menit, 10) || 90;
    const totalNilai = parseInt(req.body.total_nilai, 10) || 100;
    const nilaiLulus = parseInt(req.body.nilai_lulus, 10) || 60;
    const kelasIds = Array.isArray(req.body.kelas_ids) ? req.body.kelas_ids.map(Number) : (req.body.kelas_ids ? [Number(req.body.kelas_ids)] : []);

    if (judul && matkulId) {
      const newId = db.ujian.length ? Math.max(...db.ujian.map(u => u.id)) + 1 : 1;
      const currentUser = req.session && req.session.admin_id ? db.users.find(u => u.id === req.session.admin_id) : null;
      const creatorName = (currentUser && currentUser.nama_lengkap) ? currentUser.nama_lengkap : 'Samsul Lutfi, S.Pd., M.Pd';
      db.ujian.push({
        id: newId,
        judul_ujian: judul,
        id_mata_kuliah: matkulId,
        jenis_ujian: jenis,
        durasi_menit: durasi,
        total_nilai: totalNilai,
        nilai_lulus: nilaiLulus,
        aktif: true,
        pengampu: (req.body.pengampu && req.body.pengampu.trim()) || creatorName,
        dosen_pembuat: creatorName,
        id_dosen: currentUser ? currentUser.id : null,
        created_at: new Date()
      });

      kelasIds.forEach(kId => {
        db.ujian_kelas.push({
          id: db.ujian_kelas.length + 1,
          id_ujian: newId,
          id_kelas: kId
        });
      });
      db.save();

      return res.redirect('/admin/ujian?msg=' + encodeURIComponent('Ujian berhasil ditambahkan!'));
    }
  } else if (action === 'edit') {
    const id = parseInt(req.body.ujian_id, 10);
    const u = db.ujian.find(x => x.id === id);
    if (u) {
      u.judul_ujian = (req.body.judul_ujian || '').trim() || u.judul_ujian;
      u.id_mata_kuliah = parseInt(req.body.id_mata_kuliah, 10) || u.id_mata_kuliah;
      u.jenis_ujian = req.body.jenis_ujian || u.jenis_ujian;
      u.durasi_menit = parseInt(req.body.durasi_menit, 10) || u.durasi_menit;
      u.total_nilai = parseInt(req.body.total_nilai, 10) || u.total_nilai;
      u.nilai_lulus = parseInt(req.body.nilai_lulus, 10) || u.nilai_lulus;

      if (req.body.kelas_ids !== undefined) {
        db.ujian_kelas = db.ujian_kelas.filter(uk => uk.id_ujian !== id);
        const kelasIds = Array.isArray(req.body.kelas_ids) ? req.body.kelas_ids.map(Number) : (req.body.kelas_ids ? [Number(req.body.kelas_ids)] : []);
        kelasIds.forEach(kId => {
          db.ujian_kelas.push({
            id: db.ujian_kelas.length + 1,
            id_ujian: id,
            id_kelas: kId
          });
        });
      }
      db.save();
      return res.redirect('/admin/ujian?msg=' + encodeURIComponent('Data penilaian / ujian berhasil diperbarui!'));
    }
  } else if (action === 'toggle_active') {
    const id = parseInt(req.body.ujian_id, 10);
    const u = db.ujian.find(x => x.id === id);
    if (u) {
      u.aktif = !u.aktif;
      db.save();
      return res.redirect('/admin/ujian?msg=' + encodeURIComponent('Status ujian berhasil diubah!'));
    }
  } else if (action === 'delete') {
    const id = parseInt(req.body.ujian_id || req.body.id, 10);
    if (id) {
      db.ujian = db.ujian.filter(u => u.id !== id);
      db.ujian_kelas = db.ujian_kelas.filter(uk => uk.id_ujian !== id);
      const sesiIds = db.sesi_ujian.filter(s => s.id_ujian === id).map(s => s.id);
      db.jawaban_peserta = db.jawaban_peserta.filter(j => !sesiIds.includes(j.id_sesi));
      db.sesi_ujian = db.sesi_ujian.filter(s => s.id_ujian !== id);
      const soalIds = db.soal.filter(s => s.id_ujian === id).map(s => s.id);
      db.opsi_jawaban = db.opsi_jawaban.filter(o => !soalIds.includes(o.id_soal));
      db.soal = db.soal.filter(s => s.id_ujian !== id);
      db.save();
      return res.redirect('/admin/ujian?msg=' + encodeURIComponent('Ujian berhasil dihapus!'));
    }
  }

  res.redirect('/admin/ujian');
});

// Admin Bank Soal Management
app.get(['/admin/soal', '/admin/soal.php'], requireAdmin, (req, res) => {
  const ujianId = parseInt(req.query.ujian_id, 10) || (db.ujian[0] ? db.ujian[0].id : 0);
  const currentUjian = db.ujian.find(u => u.id === ujianId);
  const soalList = db.getSoalByUjian(ujianId);

  res.render('admin/soal', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    ujianList: db.ujian,
    selectedUjianId: ujianId,
    currentUjian,
    soalList,
    message: req.query.msg || '',
    error: req.query.err || ''
  });
});

app.post(['/admin/soal', '/admin/soal.php'], requireAdmin, (req, res) => {
  const action = req.body.action;
  const targetUjianId = parseInt(req.body.id_ujian, 10);

  if (action === 'create') {
    const jenis = req.body.jenis_soal || 'pg';
    const pertanyaan = (req.body.pertanyaan || '').trim();
    const pembahasan = (req.body.pembahasan || '').trim();
    const poin = parseInt(req.body.poin, 10) || 4;
    const tingkat = req.body.tingkat_kesulitan || 'sedang';
    const kognitif = req.body.level_kognitif || 'C1';
    const cpmk = (req.body.cpmk || '').trim();

    if (pertanyaan && targetUjianId) {
      const newSoalId = db.soal.length ? Math.max(...db.soal.map(s => s.id)) + 1 : 1;
      let dataTambahan = null;

      if (jenis === 'tf') {
        dataTambahan = { jawaban_benar: req.body.jawaban_tf === 'true' };
      } else if (jenis === 'short' || jenis === 'esai') {
        dataTambahan = { keywords: req.body.keywords || '' };
      } else if (jenis === 'ordering') {
        const items = (req.body.ordering_items || '').split('\n').map(s => s.trim()).filter(Boolean);
        dataTambahan = { correct_order: items };
      } else if (jenis === 'matching') {
        const keys = (req.body.matching_keys || '').split('\n').map(s => s.trim()).filter(Boolean);
        const vals = (req.body.matching_values || '').split('\n').map(s => s.trim()).filter(Boolean);
        const pairs = {};
        keys.forEach((k, idx) => {
          if (vals[idx]) pairs[k] = vals[idx];
        });
        dataTambahan = { pairs };
      }

      db.soal.push({
        id: newSoalId,
        id_ujian: targetUjianId,
        jenis_soal: jenis,
        pertanyaan,
        pembahasan,
        poin,
        urutan: db.soal.filter(s => s.id_ujian === targetUjianId).length + 1,
        data_tambahan: dataTambahan,
        tingkat_kesulitan: tingkat,
        level_kognitif: kognitif,
        cpmk
      });

      if (['pg', 'multiple'].includes(jenis)) {
        const teksOpsi = req.body.opsi_teks || [];
        const benarOpsi = req.body.opsi_benar;

        teksOpsi.forEach((teks, idx) => {
          if (teks && teks.trim()) {
            const isBenar = Array.isArray(benarOpsi)
              ? benarOpsi.includes(String(idx))
              : String(benarOpsi) === String(idx);
            db.opsi_jawaban.push({
              id: db.opsi_jawaban.length + 1,
              id_soal: newSoalId,
              teks_opsi: teks.trim(),
              benar: isBenar,
              urutan: idx + 1
            });
          }
        });
      }
      db.save();

      return res.redirect(`/admin/soal?ujian_id=${targetUjianId}&msg=` + encodeURIComponent('Soal berhasil ditambahkan!'));
    }
  } else if (action === 'edit') {
    const soalId = parseInt(req.body.soal_id, 10);
    const s = db.soal.find(x => x.id === soalId);
    if (s) {
      s.pertanyaan = (req.body.pertanyaan || '').trim() || s.pertanyaan;
      s.pembahasan = (req.body.pembahasan || '').trim();
      s.poin = parseInt(req.body.poin, 10) || s.poin;
      s.tingkat_kesulitan = req.body.tingkat_kesulitan || s.tingkat_kesulitan;
      s.level_kognitif = req.body.level_kognitif || s.level_kognitif;

      if (['pg', 'multiple'].includes(s.jenis_soal) && req.body.opsi_teks) {
        db.opsi_jawaban = db.opsi_jawaban.filter(o => o.id_soal !== soalId);
        const teksOpsi = req.body.opsi_teks || [];
        const benarOpsi = req.body.opsi_benar;

        teksOpsi.forEach((teks, idx) => {
          if (teks && teks.trim()) {
            const isBenar = Array.isArray(benarOpsi)
              ? benarOpsi.includes(String(idx))
              : String(benarOpsi) === String(idx);
            db.opsi_jawaban.push({
              id: db.opsi_jawaban.length + 1,
              id_soal: soalId,
              teks_opsi: teks.trim(),
              benar: isBenar,
              urutan: idx + 1
            });
          }
        });
      }
      db.save();
      return res.redirect(`/admin/soal?ujian_id=${targetUjianId}&msg=` + encodeURIComponent('Butir soal berhasil diperbarui!'));
    }
  } else if (action === 'delete') {
    const soalId = parseInt(req.body.soal_id || req.body.id, 10);
    if (soalId) {
      const s = db.soal.find(x => x.id === soalId);
      const finalUjianId = targetUjianId || (s ? s.id_ujian : 0);
      db.soal = db.soal.filter(x => x.id !== soalId);
      db.opsi_jawaban = db.opsi_jawaban.filter(o => o.id_soal !== soalId);
      db.save();
      return res.redirect(`/admin/soal?ujian_id=${finalUjianId}&msg=` + encodeURIComponent('Soal berhasil dihapus!'));
    }
  }

  res.redirect(`/admin/soal?ujian_id=${targetUjianId}`);
});

// Admin Hasil Penilaian
app.get(['/admin/hasil', '/admin/hasil.php'], requireAdmin, (req, res) => {
  const ujianId = parseInt(req.query.ujian_id, 10) || 0;
  const kelasId = parseInt(req.query.kelas_id, 10) || 0;
  const search = (req.query.search || '').toLowerCase();

  let results = db.sesi_ujian.map(s => {
    const mhs = db.mahasiswa.find(m => m.id === s.id_mahasiswa);
    const kelas = mhs ? db.kelas.find(k => k.id === mhs.id_kelas) : null;
    const prodi = kelas ? db.program_studi.find(p => p.id === kelas.id_program_studi) : null;
    const ujian = db.ujian.find(u => u.id === s.id_ujian);
    const passingGrade = (ujian && ujian.nilai_lulus) || 60;
    return {
      ...s,
      nama_lengkap: mhs ? mhs.nama_lengkap : 'Peserta',
      nim: mhs ? mhs.nim : '-',
      nama_kelas: kelas ? kelas.nama_kelas : '-',
      nama_prodi: prodi ? prodi.nama_prodi : '-',
      judul_ujian: ujian ? ujian.judul_ujian : '-',
      nilai_lulus: passingGrade,
      status_lulus: (s.nilai_total || 0) >= passingGrade ? 'Lulus' : 'Tidak Lulus'
    };
  });

  if (ujianId) results = results.filter(r => r.id_ujian === ujianId);
  if (kelasId) results = results.filter(r => {
    const mhs = db.mahasiswa.find(m => m.id === r.id_mahasiswa);
    return mhs && mhs.id_kelas === kelasId;
  });
  if (search) {
    results = results.filter(r => r.nama_lengkap.toLowerCase().includes(search) || r.nim.toLowerCase().includes(search));
  }

  res.render('admin/hasil', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    results,
    ujianList: db.ujian,
    kelasList: db.kelas,
    selectedUjianId: ujianId,
    selectedKelasId: kelasId,
    search,
    message: req.query.msg || ''
  });
});

app.post(['/admin/hasil', '/admin/hasil.php'], requireAdmin, (req, res) => {
  const action = req.body.action;

  if (action === 'reset_single') {
    const sesiId = parseInt(req.body.sesi_id, 10);
    const sesi = db.sesi_ujian.find(s => s.id === sesiId);
    if (sesi) {
      db.riwayat_sesi_ujian.push({
        id_sesi: sesi.id,
        id_ujian: sesi.id_ujian,
        id_mahasiswa: sesi.id_mahasiswa,
        nilai_total: sesi.nilai_total,
        waktu_selesai: sesi.waktu_selesai,
        alasan_reset: 'Reset Manual Individual',
        direset_oleh: req.session.admin_nama
      });
      db.jawaban_peserta = db.jawaban_peserta.filter(j => j.id_sesi !== sesiId);
      db.sesi_ujian = db.sesi_ujian.filter(s => s.id !== sesiId);
      db.save();
      return res.redirect('/admin/hasil?msg=' + encodeURIComponent('Sesi ujian peserta berhasil direset!'));
    }
  } else if (action === 'delete_single') {
    const sesiId = parseInt(req.body.sesi_id, 10);
    db.jawaban_peserta = db.jawaban_peserta.filter(j => j.id_sesi !== sesiId);
    db.sesi_ujian = db.sesi_ujian.filter(s => s.id !== sesiId);
    db.save();
    return res.redirect('/admin/hasil?msg=' + encodeURIComponent('Data hasil berhasil dihapus!'));
  } else if (action === 'edit_nilai') {
    const sesiId = parseInt(req.body.sesi_id, 10);
    const sesi = db.sesi_ujian.find(s => s.id === sesiId);
    if (sesi) {
      sesi.nilai_total = parseFloat(req.body.nilai_total) || 0;
      db.save();
      return res.redirect('/admin/hasil?msg=' + encodeURIComponent('Nilai ujian peserta berhasil diperbarui!'));
    }
  }

  res.redirect('/admin/hasil');
});

// Admin Detail Hasil
app.get(['/admin/detail_hasil', '/admin/detail_hasil.php'], requireAdmin, (req, res) => {
  const sesiId = parseInt(req.query.sesi_id, 10);
  const sesi = db.sesi_ujian.find(s => s.id === sesiId);
  if (!sesi) return res.redirect('/admin/hasil');

  const mhs = db.mahasiswa.find(m => m.id === sesi.id_mahasiswa);
  const ujian = db.ujian.find(u => u.id === sesi.id_ujian);
  const matkul = ujian ? db.mata_kuliah.find(m => m.id === ujian.id_mata_kuliah) : null;
  const soalList = db.getSoalByUjian(sesi.id_ujian);
  const jawabanList = db.jawaban_peserta.filter(j => j.id_sesi === sesiId);

  res.render('admin/detail_hasil', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    sesi,
    mahasiswa: mhs || {},
    ujian: { ...ujian, nama_mk: matkul ? matkul.nama_mk : '-' },
    soalList,
    jawabanList
  });
});

// Admin Kisi-Kisi
app.get(['/admin/kisi_kisi', '/admin/kisi_kisi.php'], requireAdmin, (req, res) => {
  const ujianId = parseInt(req.query.ujian_id, 10) || (db.ujian[0] ? db.ujian[0].id : 0);
  const currentUjian = db.ujian.find(u => u.id === ujianId) || db.ujian[0];
  const matkul = currentUjian ? db.mata_kuliah.find(m => m.id === currentUjian.id_mata_kuliah) : null;
  const soalList = db.getSoalByUjian(currentUjian ? currentUjian.id : 0);

  res.render('admin/kisi_kisi', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    ujianList: db.ujian,
    selectedUjianId: currentUjian ? currentUjian.id : 0,
    currentUjian: currentUjian ? { ...currentUjian, nama_mk: matkul ? matkul.nama_mk : '-' } : null,
    soalList,
    settings: db.pengaturan,
    message: req.query.msg || ''
  });
});

app.post(['/admin/kisi_kisi', '/admin/kisi_kisi.php'], requireAdmin, (req, res) => {
  const { action, soal_id, id, ujian_id, cpmk, materi_pokok, indikator, level_kognitif, tingkat_kesulitan, poin, pertanyaan } = req.body;
  let targetUjianId = parseInt(ujian_id, 10) || 0;
  const targetSoalId = parseInt(soal_id || id, 10) || 0;

  if (action === 'edit' && targetSoalId) {
    const s = db.soal.find(x => x.id === targetSoalId);
    if (s) {
      if (!targetUjianId) targetUjianId = s.id_ujian;
      if (cpmk !== undefined) s.cpmk = cpmk.trim();
      if (materi_pokok !== undefined) s.materi_pokok = materi_pokok.trim();
      if (indikator !== undefined) s.indikator = indikator.trim();
      if (level_kognitif !== undefined) s.level_kognitif = level_kognitif.trim();
      if (tingkat_kesulitan !== undefined) s.tingkat_kesulitan = tingkat_kesulitan.trim();
      if (poin !== undefined) s.poin = parseInt(poin, 10) || s.poin;
      if (pertanyaan !== undefined && pertanyaan.trim()) s.pertanyaan = pertanyaan.trim();
      db.save();
      return res.redirect(`/admin/kisi_kisi?ujian_id=${targetUjianId}&msg=` + encodeURIComponent('Kisi-kisi butir soal berhasil diperbarui!'));
    }
  } else if (action === 'delete' && targetSoalId) {
    const s = db.soal.find(x => x.id === targetSoalId);
    if (s && !targetUjianId) targetUjianId = s.id_ujian;
    db.soal = db.soal.filter(x => x.id !== targetSoalId);
    db.opsi_jawaban = db.opsi_jawaban.filter(o => o.id_soal !== targetSoalId);
    db.save();
    return res.redirect(`/admin/kisi_kisi?ujian_id=${targetUjianId}&msg=` + encodeURIComponent('Butir kisi-kisi soal berhasil dihapus!'));
  }

  res.redirect(`/admin/kisi_kisi?ujian_id=${targetUjianId}`);
});

// Export PDF / Print Preview Naskah Soal matching Image 2
app.get(['/admin/kisi_kisi/export_pdf', '/admin/soal/cetak_naskah'], requireAdmin, (req, res) => {
  const ujianId = parseInt(req.query.ujian_id, 10) || (db.ujian[0] ? db.ujian[0].id : 0);
  const ujian = db.ujian.find(u => u.id === ujianId) || db.ujian[0];
  const matkul = ujian ? db.mata_kuliah.find(m => m.id === ujian.id_mata_kuliah) : null;
  const soalList = db.getSoalByUjian(ujian ? ujian.id : 0);
  const currentUser = req.session && req.session.admin_id ? db.users.find(u => u.id === req.session.admin_id) : null;

  // Auto-assign creator lecturer name if exam lecturer name is generic or unset
  if (currentUser && currentUser.nama_lengkap && (!ujian.dosen_pembuat || ujian.dosen_pembuat === 'Dosen Pengampu' || !ujian.pengampu || ujian.pengampu === 'Dosen Pengampu')) {
    ujian.dosen_pembuat = ujian.dosen_pembuat && ujian.dosen_pembuat !== 'Dosen Pengampu' ? ujian.dosen_pembuat : currentUser.nama_lengkap;
    ujian.pengampu = ujian.pengampu && ujian.pengampu !== 'Dosen Pengampu' ? ujian.pengampu : currentUser.nama_lengkap;
  }

  const formatCetak = getUjianFormatCetak(ujian, currentUser, db.pengaturan);

  res.render('admin/cetak_naskah', {
    ujian: { ...ujian, nama_mk: matkul ? matkul.nama_mk : '-' },
    formatCetak,
    currentUser,
    soalList,
    settings: db.pengaturan,
    autoPrint: req.query.auto === 'true'
  });
});

// Export PDF / Print Preview Matriks Kisi-Kisi Soal
app.get(['/admin/kisi_kisi/cetak_matriks', '/admin/kisi_kisi/export_matrix_pdf'], requireAdmin, (req, res) => {
  const ujianId = parseInt(req.query.ujian_id, 10) || (db.ujian[0] ? db.ujian[0].id : 0);
  const ujian = db.ujian.find(u => u.id === ujianId) || db.ujian[0];
  const matkul = ujian ? db.mata_kuliah.find(m => m.id === ujian.id_mata_kuliah) : null;
  const soalList = db.getSoalByUjian(ujian ? ujian.id : 0);
  const currentUser = req.session && req.session.admin_id ? db.users.find(u => u.id === req.session.admin_id) : null;

  // Auto-assign creator lecturer name if exam lecturer name is generic or unset
  if (currentUser && currentUser.nama_lengkap && (!ujian.dosen_pembuat || ujian.dosen_pembuat === 'Dosen Pengampu' || !ujian.pengampu || ujian.pengampu === 'Dosen Pengampu')) {
    ujian.dosen_pembuat = ujian.dosen_pembuat && ujian.dosen_pembuat !== 'Dosen Pengampu' ? ujian.dosen_pembuat : currentUser.nama_lengkap;
    ujian.pengampu = ujian.pengampu && ujian.pengampu !== 'Dosen Pengampu' ? ujian.pengampu : currentUser.nama_lengkap;
  }

  const formatCetak = getUjianFormatCetak(ujian, currentUser, db.pengaturan);

  res.render('admin/cetak_matriks', {
    ujian: { ...ujian, nama_mk: matkul ? matkul.nama_mk : '-', sks: matkul ? matkul.sks : 3, kode_mk: matkul ? matkul.kode_mk : 'MK01' },
    formatCetak,
    currentUser,
    soalList,
    settings: db.pengaturan,
    autoPrint: req.query.auto === 'true'
  });
});

// API: Simpan Format Kop & Identitas Cetak (Bisa per-ujian atau per-role/user)
app.post(['/admin/api/simpan_format_cetak'], requireAdmin, (req, res) => {
  try {
    const { ujian_id, format_cetak, jadikan_default_saya, jadikan_default_kampus } = req.body;
    const ujianId = parseInt(ujian_id, 10);
    const ujian = db.ujian.find(u => u.id === ujianId);

    if (!ujian) {
      return res.status(404).json({ success: false, message: 'Data ujian tidak ditemukan' });
    }

    if (!format_cetak || typeof format_cetak !== 'object') {
      return res.status(400).json({ success: false, message: 'Format cetak tidak valid' });
    }

    // 1. Simpan format_cetak ke data ujian spesifik
    ujian.format_cetak = format_cetak;

    // Sinkronisasi data dasar ujian bila diperbarui pada form identitas
    if (format_cetak.identitas_rows && Array.isArray(format_cetak.identitas_rows)) {
      format_cetak.identitas_rows.forEach(r => {
        const kLabel = (r.kiri_label || '').toLowerCase();
        const rLabel = (r.kanan_label || '').toLowerCase();
        if (kLabel.includes('tanggal')) ujian.hari_tanggal = r.kiri_val;
        if (rLabel.includes('tanggal')) ujian.hari_tanggal = r.kanan_val;
        if (kLabel.includes('waktu')) ujian.waktu = r.kiri_val;
        if (rLabel.includes('waktu')) ujian.waktu = r.kanan_val;
        if (kLabel.includes('pengampu') || kLabel.includes('dosen')) ujian.pengampu = r.kiri_val;
        if (rLabel.includes('pengampu') || rLabel.includes('dosen')) ujian.pengampu = r.kanan_val;
        if (kLabel.includes('sks') || kLabel.includes('smt')) ujian.smt_sks = r.kiri_val;
        if (rLabel.includes('sks') || rLabel.includes('smt')) ujian.smt_sks = r.kanan_val;
        if (kLabel.includes('prodi') || kLabel.includes('fakultas')) ujian.fakultas_prodi = r.kiri_val;
        if (rLabel.includes('prodi') || rLabel.includes('fakultas')) ujian.fakultas_prodi = r.kanan_val;
      });
    }

    if (format_cetak.sub_judul && format_cetak.sub_judul.includes('20')) {
      const match = format_cetak.sub_judul.match(/20\d\d\/20\d\d/);
      if (match) ujian.tahun_akademik = match[0];
    }

    // 2. Simpan sebagai template bawaan Dosen / Admin yang sedang login jika dipilih
    const currentUser = req.session && req.session.admin_id ? db.users.find(u => u.id === req.session.admin_id) : null;
    if (jadikan_default_saya && currentUser) {
      currentUser.default_format_cetak = JSON.parse(JSON.stringify(format_cetak));
    }

    // 3. Jika admin memilih jadikan default institusi/kampus
    if (jadikan_default_kampus && currentUser && currentUser.role === 'admin') {
      if (format_cetak.nama_institusi) db.pengaturan.nama_institusi = format_cetak.nama_institusi;
      if (format_cetak.alamat_institusi) db.pengaturan.alamat_institusi = format_cetak.alamat_institusi;
      if (format_cetak.kontak_institusi) db.pengaturan.telepon_institusi = format_cetak.kontak_institusi;
      if (format_cetak.logo_path) db.pengaturan.logo_path = format_cetak.logo_path;
      if (format_cetak.sub_institusi) db.pengaturan.fakultas_institusi = format_cetak.sub_institusi;
    }

    db.save();
    return res.json({
      success: true,
      message: 'Format korps dan identitas soal berhasil disimpan!',
      formatCetak: getUjianFormatCetak(ujian, currentUser, db.pengaturan)
    });
  } catch (err) {
    console.error('simpan_format_cetak error:', err);
    return res.status(500).json({ success: false, message: 'Gagal menyimpan format: ' + err.message });
  }
});

// API: Reset Format Cetak ke Standar
app.post(['/admin/api/reset_format_cetak'], requireAdmin, (req, res) => {
  try {
    const { ujian_id } = req.body;
    const ujianId = parseInt(ujian_id, 10);
    const ujian = db.ujian.find(u => u.id === ujianId);

    if (!ujian) {
      return res.status(404).json({ success: false, message: 'Data ujian tidak ditemukan' });
    }

    delete ujian.format_cetak;
    db.save();

    const currentUser = req.session && req.session.admin_id ? db.users.find(u => u.id === req.session.admin_id) : null;
    return res.json({
      success: true,
      message: 'Format berhasil direset ke standar institusi!',
      formatCetak: getUjianFormatCetak(ujian, currentUser, db.pengaturan)
    });
  } catch (err) {
    return res.status(500).json({ success: false, message: 'Gagal mereset format: ' + err.message });
  }
});

// API: Unggah Logo Institusi (Mendukung upload file multipart atau base64)
app.post('/admin/api/upload_logo', requireAdmin, (req, res) => {
  uploadLogo.single('logo_file')(req, res, (err) => {
    if (err) {
      console.error('Multer upload error:', err);
      return res.status(400).json({ success: false, message: 'Gagal mengunggah logo: ' + err.message });
    }

    try {
      // 1. Jika diunggah via multipart file input
      if (req.file) {
        const fileUrl = `/admin/uploads/${req.file.filename}`;
        return res.json({
          success: true,
          message: 'Logo berhasil diunggah!',
          url: fileUrl
        });
      }

      // 2. Jika diunggah via JSON base64
      if (req.body && req.body.logo_base64) {
        const base64Data = req.body.logo_base64.replace(/^data:image\/\w+;base64,/, '');
        const extMatch = req.body.logo_base64.match(/^data:image\/(\w+);base64,/);
        const ext = extMatch ? `.${extMatch[1]}` : '.png';
        const filename = `logo_${Date.now()}_custom${ext}`;
        const uploadDir = path.join(__dirname, 'admin/uploads');
        if (!fs.existsSync(uploadDir)) fs.mkdirSync(uploadDir, { recursive: true });
        const filePath = path.join(uploadDir, filename);
        fs.writeFileSync(filePath, Buffer.from(base64Data, 'base64'));

        const fileUrl = `/admin/uploads/${filename}`;
        return res.json({
          success: true,
          message: 'Logo berhasil disimpan!',
          url: fileUrl
        });
      }

      return res.status(400).json({ success: false, message: 'Tidak ada berkas logo yang dikirimkan' });
    } catch (saveErr) {
      console.error('Error saving logo:', saveErr);
      return res.status(500).json({ success: false, message: 'Gagal menyimpan logo: ' + saveErr.message });
    }
  });
});

// Export Word (.docx) matching Image 2
app.get(['/admin/kisi_kisi/export_word'], requireAdmin, async (req, res) => {
  try {
    const ujianId = parseInt(req.query.ujian_id, 10) || (db.ujian[0] ? db.ujian[0].id : 0);
    const ujian = db.ujian.find(u => u.id === ujianId) || db.ujian[0];
    const matkul = ujian ? db.mata_kuliah.find(m => m.id === ujian.id_mata_kuliah) : null;
    const soalList = db.getSoalByUjian(ujian ? ujian.id : 0);
    const currentUser = req.session && req.session.admin_id ? db.users.find(u => u.id === req.session.admin_id) : null;
    const ujianData = { ...ujian, nama_mk: matkul ? matkul.nama_mk : '-' };

    const docxBuffer = await generateExamDocx(ujianData, soalList, db.pengaturan, currentUser);
    const filename = `Naskah_Soal_${(ujian.judul_ujian || 'Ujian').replace(/[^a-zA-Z0-9_-]/g, '_')}.docx`;

    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    res.setHeader('Content-Disposition', `attachment; filename="${filename}"`);
    return res.send(docxBuffer);
  } catch (err) {
    console.error('Word export error:', err);
    res.status(500).send('Gagal mengekspor dokumen Word: ' + err.message);
  }
});

// Export Matriks Kisi-Kisi Word (.docx)
app.get(['/admin/kisi_kisi/export_matrix_word'], requireAdmin, async (req, res) => {
  try {
    const ujianId = parseInt(req.query.ujian_id, 10) || (db.ujian[0] ? db.ujian[0].id : 0);
    const ujian = db.ujian.find(u => u.id === ujianId) || db.ujian[0];
    const matkul = ujian ? db.mata_kuliah.find(m => m.id === ujian.id_mata_kuliah) : null;
    const soalList = db.getSoalByUjian(ujian ? ujian.id : 0);
    const currentUser = req.session && req.session.admin_id ? db.users.find(u => u.id === req.session.admin_id) : null;
    const ujianData = { ...ujian, nama_mk: matkul ? matkul.nama_mk : '-' };

    const docxBuffer = await generateMatrixDocx(ujianData, soalList, db.pengaturan, currentUser);
    const filename = `Matriks_Kisi_Kisi_${(ujian.judul_ujian || 'Ujian').replace(/[^a-zA-Z0-9_-]/g, '_')}.docx`;

    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    res.setHeader('Content-Disposition', `attachment; filename="${filename}"`);
    return res.send(docxBuffer);
  } catch (err) {
    console.error('Matrix Word export error:', err);
    res.status(500).send('Gagal mengekspor matriks kisi-kisi: ' + err.message);
  }
});

// Helper for readable question type labels
function getJenisSoalLabel(code) {
  const map = {
    'pg': 'Pilihan Ganda (PG)',
    'pg_kompleks': 'Pilihan Ganda Kompleks',
    'tf': 'Benar / Salah',
    'menjodohkan': 'Menjodohkan',
    'short': 'Isian Singkat',
    'isian': 'Isian Singkat',
    'esai': 'Uraian / Esai',
    'essay': 'Uraian / Esai'
  };
  return map[(code || '').toLowerCase()] || (code ? code.toUpperCase() : 'Pilihan Ganda');
}

// Export Matriks Kisi-Kisi & Soal to Excel (.xlsx)
app.get(['/admin/kisi_kisi/export_excel', '/admin/kisi_kisi/export_xlsx'], requireAdmin, (req, res) => {
  try {
    const xlsxLib = XLSX.default || XLSX;
    const wb = xlsxLib.utils.book_new();

    const ujianId = parseInt(req.query.ujian_id, 10) || (db.ujian[0] ? db.ujian[0].id : 0);
    const ujian = db.ujian.find(u => u.id === ujianId) || db.ujian[0];
    const matkul = ujian ? db.mata_kuliah.find(m => m.id === ujian.id_mata_kuliah) : null;
    const soalList = db.getSoalByUjian(ujian ? ujian.id : 0);
    const ujianNama = ujian ? (ujian.nama_mk || ujian.judul_ujian) : 'Ujian';

    // 1. Sheet: Matriks_Kisi_Kisi
    const matrixRows = soalList.map((s, idx) => {
      let kunciJawaban = '-';
      if (s.opsi && s.opsi.length > 0) {
        const correctList = s.opsi.filter(o => o.benar);
        if (correctList.length > 0) {
          kunciJawaban = correctList.map(o => {
            const letterIdx = s.opsi.indexOf(o);
            const letter = String.fromCharCode(65 + (letterIdx >= 0 ? letterIdx : 0));
            return `${letter}. ${o.teks || o.teks_opsi || ''}`;
          }).join(' | ');
        }
      } else if (s.pembahasan) {
        kunciJawaban = s.pembahasan;
      }

      return {
        'No': idx + 1,
        'Capaian Pembelajaran (CPMK)': s.cpmk || 'Capaian Pembelajaran Mata Kuliah',
        'Bahan Kajian / Materi Pokok': s.materi_pokok || ujianNama,
        'Indikator Pencapaian Butir Soal': s.indikator || (s.pertanyaan ? s.pertanyaan.replace(/<[^>]*>?/gm, '').substring(0, 95) : '-'),
        'Bentuk / Jenis Soal': getJenisSoalLabel(s.jenis_soal),
        'Level Kognitif (Bloom)': s.level_kognitif || 'C3',
        'Tingkat Kesulitan': (s.tingkat_kesulitan || 'sedang').toUpperCase(),
        'Nomor Soal': idx + 1,
        'Bobot Poin': s.poin || 4,
        'Kunci Jawaban': kunciJawaban,
        'Pedoman Penskoran / Pembahasan': s.pembahasan || '-'
      };
    });

    const wsMatrix = xlsxLib.utils.json_to_sheet(matrixRows);
    wsMatrix['!cols'] = [
      { wch: 6 },
      { wch: 32 },
      { wch: 28 },
      { wch: 38 },
      { wch: 22 },
      { wch: 16 },
      { wch: 16 },
      { wch: 12 },
      { wch: 12 },
      { wch: 35 },
      { wch: 45 }
    ];
    xlsxLib.utils.book_append_sheet(wb, wsMatrix, 'Matriks_Kisi_Kisi');

    // 2. Sheet: Naskah_Bank_Soal
    const naskahRows = soalList.map((s, idx) => {
      const row = {
        'No': idx + 1,
        'Bentuk Soal': getJenisSoalLabel(s.jenis_soal),
        'Level Kognitif': s.level_kognitif || 'C3',
        'Tingkat Kesulitan': (s.tingkat_kesulitan || 'sedang').toUpperCase(),
        'Bobot Poin': s.poin || 4,
        'Butir Pertanyaan': (s.pertanyaan || '').replace(/<[^>]*>?/gm, '').trim(),
        'Opsi A': '',
        'Opsi B': '',
        'Opsi C': '',
        'Opsi D': '',
        'Opsi E': '',
        'Kunci Jawaban': '',
        'Pembahasan / Rubrik': s.pembahasan || '-'
      };

      if (s.opsi && s.opsi.length > 0) {
        const letters = ['A', 'B', 'C', 'D', 'E'];
        const correctLetters = [];
        s.opsi.forEach((o, oIdx) => {
          const l = letters[oIdx] || `Opsi ${oIdx + 1}`;
          if (row.hasOwnProperty(`Opsi ${l}`)) {
            row[`Opsi ${l}`] = o.teks || o.teks_opsi || '';
          }
          if (o.benar) correctLetters.push(l);
        });
        row['Kunci Jawaban'] = correctLetters.join(', ');
      } else {
        row['Kunci Jawaban'] = s.pembahasan || '-';
      }

      return row;
    });

    const wsNaskah = xlsxLib.utils.json_to_sheet(naskahRows);
    wsNaskah['!cols'] = [
      { wch: 6 },
      { wch: 22 },
      { wch: 15 },
      { wch: 16 },
      { wch: 12 },
      { wch: 55 },
      { wch: 26 },
      { wch: 26 },
      { wch: 26 },
      { wch: 26 },
      { wch: 26 },
      { wch: 18 },
      { wch: 45 }
    ];
    xlsxLib.utils.book_append_sheet(wb, wsNaskah, 'Naskah_Soal');

    const buffer = xlsxLib.write(wb, { type: 'buffer', bookType: 'xlsx' });
    const filename = `Matriks_Kisi_Kisi_dan_Soal_${(ujian.judul_ujian || 'Ujian').replace(/[^a-zA-Z0-9_-]/g, '_')}.xlsx`;

    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    res.setHeader('Content-Disposition', `attachment; filename="${filename}"`);
    return res.send(buffer);
  } catch (err) {
    console.error('Excel export error:', err);
    res.status(500).send('Gagal mengekspor berkas Excel: ' + err.message);
  }
});

// Export Bank Soal Excel (.xlsx) from Bank Soal view
app.get(['/admin/soal/export_excel', '/admin/soal/export_xlsx'], requireAdmin, (req, res) => {
  res.redirect(`/admin/kisi_kisi/export_excel?ujian_id=${req.query.ujian_id || ''}`);
});

// Export Naskah Soal Word (.docx) from Bank Soal view
app.get(['/admin/soal/export_word'], requireAdmin, (req, res) => {
  res.redirect(`/admin/kisi_kisi/export_word?ujian_id=${req.query.ujian_id || ''}`);
});

// ==========================================================================
// UNIVERSAL HIGH-PRECISION AI ENGINE & CONTEXTUAL CURRICULUM GENERATOR
// ==========================================================================

function parseAIJson(rawText) {
  if (!rawText || typeof rawText !== 'string') return null;
  const text = rawText.replace(/```json/gi, '').replace(/```/g, '').trim();
  try {
    return JSON.parse(text);
  } catch (e) {
    const firstBrace = text.indexOf('{');
    const lastBrace = text.lastIndexOf('}');
    if (firstBrace !== -1 && lastBrace > firstBrace) {
      try {
        return JSON.parse(text.substring(firstBrace, lastBrace + 1));
      } catch (e2) {}
    }
    const firstBracket = text.indexOf('[');
    const lastBracket = text.lastIndexOf(']');
    if (firstBracket !== -1 && lastBracket > firstBracket) {
      try {
        return JSON.parse(text.substring(firstBracket, lastBracket + 1));
      } catch (e3) {}
    }
    return null;
  }
}

// Clean and validate questions across all supported types without manual review
function sanitizeQuestions(questions, defaultTopic, defaultPoin = 4) {
  if (!Array.isArray(questions)) return [];
  return questions.map((q, idx) => {
    let stem = (q.pertanyaan || '').trim();
    stem = stem.replace(/^\[(kisi-kisi|ai)\]\s*/i, '');
    stem = stem.replace(/\n\s*[A-E][\.\)]\s*[\s\S]*$/, '').trim();

    const rawType = (q.jenis_soal || q.bentuk_soal || 'pg').toLowerCase();
    let jenis = 'pg';
    if (rawType.includes('kompleks')) jenis = 'pg_kompleks';
    else if (rawType.includes('benar') || rawType === 'tf') jenis = 'tf';
    else if (rawType.includes('jodoh') || rawType === 'menjodohkan') jenis = 'menjodohkan';
    else if (rawType.includes('singkat') || rawType.includes('isian') || rawType === 'short') jenis = 'short';
    else if (rawType.includes('esai') || rawType.includes('uraian') || rawType === 'essay') jenis = 'esai';

    let opsiList = [];
    if (jenis === 'tf') {
      // Benar / Salah has exactly 2 options
      let correctVal = true;
      if (Array.isArray(q.opsi) && q.opsi.length > 0) {
        const falseOpt = q.opsi.find(o => o.benar && (o.teks || o.teks_opsi || '').toLowerCase().includes('salah'));
        if (falseOpt) correctVal = false;
      }
      opsiList = [
        { teks: 'Benar', benar: correctVal === true },
        { teks: 'Salah', benar: correctVal === false }
      ];
    } else if (jenis === 'esai' || jenis === 'short') {
      // Essay or short answer has no options or single key
      if (Array.isArray(q.opsi) && q.opsi.length > 0) {
        opsiList = q.opsi.map(o => ({
          teks: (typeof o === 'string' ? o : (o.teks || o.teks_opsi || '')).trim(),
          benar: Boolean(o.benar)
        }));
      }
    } else if (jenis === 'pg_kompleks') {
      // Multiple correct options
      if (Array.isArray(q.opsi) && q.opsi.length >= 3) {
        opsiList = q.opsi.map(o => ({
          teks: (typeof o === 'string' ? o : (o.teks || o.teks_opsi || '')).trim().replace(/^[A-E][\.\)]\s*/i, ''),
          benar: Boolean(o.benar)
        }));
      } else {
        opsiList = [
          { teks: `Pernyataan 1: Konsep dasar ${defaultTopic} diterapkan secara tepat dalam analisis kasus`, benar: true },
          { teks: `Pernyataan 2: Luaran instrumen memenuhi parameter validitas dan reliabilitas pengujian`, benar: true },
          { teks: `Pernyataan 3: Prosedur operasional mengabaikan kaidah standar mutu kurikulum`, benar: false },
          { teks: `Pernyataan 4: Evaluasi berkala dilakukan dengan dukungan data autentik dan rubrik terstandar`, benar: true }
        ];
      }
      if (opsiList.filter(o => o.benar).length < 2) {
        if (opsiList[0]) opsiList[0].benar = true;
        if (opsiList[1]) opsiList[1].benar = true;
      }
    } else if (jenis === 'menjodohkan') {
      if (Array.isArray(q.opsi) && q.opsi.length > 0) {
        opsiList = q.opsi.map(o => ({
          teks: (typeof o === 'string' ? o : (o.teks || o.teks_opsi || '')).trim(),
          benar: Boolean(o.benar)
        }));
      } else {
        opsiList = [
          { teks: 'Premis 1 (Konsep Utama) <=> Pasangan A (Kaidah Penerapan)', benar: true },
          { teks: 'Premis 2 (Metodologi Uji) <=> Pasangan B (Prosedur Validasi)', benar: true },
          { teks: 'Premis 3 (Instrumen Evaluasi) <=> Pasangan C (Standar Reliabilitas)', benar: true }
        ];
      }
    } else {
      // Standard Multiple Choice PG (5 options A-E, 1 correct)
      if (Array.isArray(q.opsi) && q.opsi.length > 0) {
        opsiList = q.opsi.map(o => {
          let text = (typeof o === 'string' ? o : (o.teks || o.teks_opsi || '')).trim();
          text = text.replace(/^[A-E][\.\)]\s*/i, '').trim();
          return { teks: text, benar: Boolean(o.benar) };
        });
      }
      const letters = ['A', 'B', 'C', 'D', 'E'];
      if (opsiList.length < 5) {
        while (opsiList.length < 5) {
          opsiList.push({
            teks: `Alternatif analisis komparatif ${letters[opsiList.length]} terkait materi ${defaultTopic}`,
            benar: false
          });
        }
      } else if (opsiList.length > 5) {
        opsiList = opsiList.slice(0, 5);
      }
      const trueCount = opsiList.filter(o => o.benar).length;
      if (trueCount === 0) {
        opsiList[0].benar = true;
      } else if (trueCount > 1) {
        let foundFirst = false;
        opsiList.forEach(o => {
          if (o.benar) {
            if (!foundFirst) foundFirst = true;
            else o.benar = false;
          }
        });
      }
    }

    return {
      pertanyaan: stem || `Berdasarkan kajian analisis pada bidang ${defaultTopic}, analisislah butir studi kasus ke-${idx + 1} berikut ini:`,
      jenis_soal: jenis,
      cpmk: q.cpmk || `Menguasai analisis teoritis dan terapan materi ${defaultTopic}`,
      materi_pokok: q.materi_pokok || defaultTopic,
      indikator: q.indikator || `Mampu mengevaluasi dan menerapkan kaidah ${defaultTopic} secara komprehensif`,
      level_kognitif: q.level_kognitif || 'C3',
      tingkat_kesulitan: q.tingkat_kesulitan || 'sedang',
      poin: parseInt(q.poin, 10) || defaultPoin,
      opsi: opsiList,
      pembahasan: q.pembahasan || `Kunci jawaban yang tepat didasarkan pada kesesuaian prinsip metodologis dan kaidah standar pada materi ${defaultTopic}.`
    };
  });
}

// Academic Fallback Generator: Realistic multi-type questions across Bloom's levels
function generateAcademicQuestionsFallback({ courseName, topic, subTopic, cpmk, count = 5, poin = 4, difficulty = 'sedang', cognitive = 'C3', jenisList = ['pg'], cognitiveList = [] }) {
  const effectiveTopic = topic || courseName || 'Pengembangan Pembelajaran';
  const effectiveSub = subTopic ? `${effectiveTopic} - ${subTopic}` : effectiveTopic;
  const results = [];
  const selectedTypes = (Array.isArray(jenisList) && jenisList.length > 0) ? jenisList : ['pg'];
  const bloomLevels = (Array.isArray(cognitiveList) && cognitiveList.length > 0) 
    ? cognitiveList 
    : (cognitive && cognitive !== 'proporsional' ? [cognitive] : ['C2', 'C3', 'C4', 'C5', 'C3']);
  const diffs = ['mudah', 'sedang', 'sedang', 'sulit', 'sedang'];

  const scenarioPool = [
    {
      stem: `Dalam implementasi ${effectiveSub}, seorang tenaga profesional mendapati kendala berupa inkonsistensi luaran kerja pada tahapan evaluasi berkala. Tindakan preventif dan solutif manakah yang paling selaras dengan kaidah standar untuk menanggulangi permasalahan tersebut?`,
      correct: `Melakukan audit instrumen secara komprehensif dan menyelaraskan ulang seluruh parameter dengan indikator acuan mutu yang telah divalidasi`,
      distractors: [
        `Mengabaikan temuan audit dan langsung menerapkan sistem baru tanpa studi kelayakan`,
        `Mengurangi batas minimal kelulusan agar seluruh luaran tampak memenuhi kriteria standar`,
        `Menghentikan proses evaluasi secara permanen untuk memangkas anggaran operasional`,
        `Melakukan penyesuaian nilai akhir secara manual tanpa dasar pertimbangan rubrik baku`
      ],
      pembahasan: `Langkah solutif yang tepat menuntut audit instrumen komprehensif dan penyelarasan ulang parameter dengan indikator mutu terakreditasi.`
    },
    {
      stem: `Berdasarkan prinsip metodologis pada materi ${effectiveTopic}, kriteria utama manakah yang menjamin bahwa rancangan evaluasi yang dikembangkan memiliki tingkat reliabilitas dan validitas konstruk yang memadai?`,
      correct: `Setiap butir asesmen diturunkan secara langsung dari capaian pembelajaran terukur (CPMK) dan telah melalui validasi ahli serta uji reliabilitas empiris`,
      distractors: [
        `Seluruh butir soal disusun hanya berdasarkan intuisi pengajar tanpa kisi-kisi penulisan`,
        `Tingkat kesulitan seluruh butir soal diturunkan ke level terendah agar tidak terjadi kegagalan`,
        `Penyusunan naskah hanya mengandalkan rangkuman materi dari sumber internet yang belum terverifikasi`,
        `Penilaian dilakukan tanpa adanya kunci jawaban dan pedoman penskoran yang seragam`
      ],
      pembahasan: `Validitas konstruk dan reliabilitas instrumen dijamin apabila butir diturunkan dari CPMK terukur serta divalidasi oleh pakar bidang keahlian.`
    },
    {
      stem: `Ketika melakukan analisis komparatif terhadap efektivitas implementasi ${effectiveTopic} di lingkungan perguruan tinggi, manakah indikator performa yang paling objektif untuk mengukur keberhasilan program?`,
      correct: `Ketercapaian indikator kinerja pembelajaran terverifikasi yang didukung data evaluasi autentik dan portofolio kompetensi mahasiswa`,
      distractors: [
        `Tingkat kepuasan subjektif mahasiswa semata tanpa adanya data pengujian capaian keterampilan riil`,
        `Kecepatan waktu penyelesaian ujian tanpa memperhatikan ketepatan dan kedalaman analisis respon`,
        `Jumlah materi tayang yang disampaikan tanpa mempertimbangkan keterpahaman peserta didik`,
        `Persentase kehadiran fisik tanpa adanya evaluasi terhadap partisipasi aktif dalam diskusi akademik`
      ],
      pembahasan: `Indikator objektif keberhasilan program diukur dari ketercapaian capaian pembelajaran autentik dan portofolio kompetensi mahasiswa.`
    },
    {
      stem: `Dalam kajian taksonomi kognitif Bloom, seorang penguji ingin mengukur kemampuan analisis mahasiswa (C4) pada topik ${effectiveSub}. Bentuk pertanyaan atau stimulus manakah yang paling tepat digunakan?`,
      correct: `Menyajikan studi kasus faktual yang memuat anomali data, kemudian meminta mahasiswa mengidentifikasi akar penyebab dan merekonstruksi alur pemecahan masalah`,
      distractors: [
        `Meminta mahasiswa menyebutkan kembali definisi dasar dan istilah teknis persis sesuai teks buku acuan`,
        `Menanyakan tahun penerbitan teori pertama kali tanpa keterkaitan dengan aplikasi praktis`,
        `Meminta mahasiswa menghafal seluruh daftar formula matematika tanpa memahami konteks penggunaannya`,
        `Menyajikan pilihan benar/salah sederhana tanpa memerlukan penalaran analitis mendalam`
      ],
      pembahasan: `Pengukuran ranah analisis (C4) efektif dilakukan melalui studi kasus anomali data di mana peserta didik dituntut membedah akar permasalahan dan merekonstruksi solusi.`
    },
    {
      stem: `Pada situasi di mana ${effectiveTopic} diterapkan dalam skala luas dengan heterogenitas kemampuan peserta, pendekatan diferensiasi instruksional manakah yang paling efektif menjamin kesetaraan akses pembelajaran?`,
      correct: `Menyediakan media dan scaffold pembelajaran yang bervariasi sesuai modalitas belajar serta menyusun target capaian bertahap yang adaptif`,
      distractors: [
        `Menyamaratakan seluruh metode penyampaian tanpa mempertimbangkan variasi latar belakang peserta didik`,
        `Mengurangi alokasi waktu pendampingan bagi kelompok peserta didik yang mengalami kesulitan belajar`,
        `Hanya memfokuskan proses pembelajaran pada kelompok peserta didik dengan kemampuan di atas rata-rata`,
        `Meniadakan pengujian formatif agar seluruh peserta didik merasa setara tanpa umpan balik perbaikan`
      ],
      pembahasan: `Diferensiasi instruksional yang berkeadilan dilakukan dengan memvariasikan media, menyediakan scaffolding adaptif, dan target capaian bertahap.`
    }
  ];

  for (let i = 0; i < count; i++) {
    const s = scenarioPool[i % scenarioPool.length];
    const lvl = bloomLevels[i % bloomLevels.length];
    const diff = difficulty && difficulty !== 'proporsional' ? difficulty : diffs[i % diffs.length];
    const currentType = selectedTypes[i % selectedTypes.length];

    let opsi = [];
    let stem = s.stem;
    let pembahasan = s.pembahasan;

    if (currentType === 'tf') {
      stem = `Pernyataan: "Dalam penerapan ${effectiveSub}, ${s.correct.toLowerCase()} merupakan langkah metodologis utama yang menjamin keberhasilan mutu." Apakah pernyataan ini Benar atau Salah?`;
      opsi = [
        { teks: 'Benar', benar: true },
        { teks: 'Salah', benar: false }
      ];
    } else if (currentType === 'pg_kompleks') {
      stem = `Pada analisis komprehensif implementasi ${effectiveSub}, analisislah pernyataan-pernyataan berikut ini dan pilihlah SEMUA pernyataan yang tepat:`;
      opsi = [
        { teks: s.correct, benar: true },
        { teks: `Melakukan telaah dokumen berkala bersama pakar kurikulum dan stakeholder terkait`, benar: true },
        { teks: s.distractors[0], benar: false },
        { teks: s.distractors[1], benar: false }
      ];
    } else if (currentType === 'short') {
      stem = `Dalam konteks evaluasi ${effectiveSub}, sebutkan prinsip kunci yang menjamin keselarasan antara capaian pembelajaran terukur dengan instrumen asesmen!`;
      opsi = [{ teks: 'Validitas Konstruk dan Reliabilitas Asesmen', benar: true }];
      pembahasan = `Kunci jawaban singkat: Validitas Konstruk / CPMK Terukur. ${s.pembahasan}`;
    } else if (currentType === 'esai') {
      stem = `Uraikan secara komprehensif studi kasus implementasi ${effectiveSub} berikut:\n${s.stem}\nJelaskan tahapan identifikasi masalah, formulasi solusi, serta rubrik evaluasi yang menjamin keberlanjutan hasil!`;
      opsi = [];
      pembahasan = `Pedoman Penskoran Uraian (Skor Maksimal 100):\n1. Identifikasi anomali data & akar masalah (30 poin)\n2. Perumusan solusi metodologis berbasis kaidah standar (40 poin)\n3. Rancangan instrumen audit mutu terverifikasi (30 poin)`;
    } else if (currentType === 'menjodohkan') {
      stem = `Jodohkanlah premis konsep pada bidang ${effectiveTopic} di sebelah kiri dengan penerapan praktis yang tepat di sebelah kanan:`;
      opsi = [
        { teks: 'Konsep Validitas Konstruk <=> Keselarasan butir dengan CPMK terukur', benar: true },
        { teks: 'Uji Reliabilitas Empiris <=> Konsistensi skor asesmen pada pengujian berulang', benar: true },
        { teks: 'Diferensiasi Instruksional <=> Penyesuaian media & scaffolding sesuai modalitas peserta', benar: true }
      ];
    } else {
      // pg
      const allOptions = [
        { teks: s.correct, benar: true },
        ...s.distractors.map(d => ({ teks: d, benar: false }))
      ];
      const correctIdx = (i * 2 + 1) % 5;
      const swapped = [...allOptions];
      const temp = swapped[0];
      swapped[0] = swapped[correctIdx];
      swapped[correctIdx] = temp;
      opsi = swapped;
    }

    results.push({
      pertanyaan: stem,
      jenis_soal: currentType,
      cpmk: cpmk || `Menguasai analisis teoritis dan terapan materi ${effectiveTopic}`,
      materi_pokok: effectiveSub,
      indikator: `Mampu menganalisis dan menyelesaikan instrumen pada ranah ${lvl} (${getJenisSoalLabel(currentType)})`,
      level_kognitif: lvl,
      tingkat_kesulitan: diff,
      poin: poin,
      opsi,
      pembahasan
    });
  }

  return results;
}

// Universal AI Client with Zero-Downtime Multi-Tier Fallback
async function generateWithAI({ prompt, systemInstruction = 'Anda adalah dosen ahli penyusun instrumen ujian akademik. Respon HANYA berupa JSON valid.', temperature = 0.2 }) {
  // Tier 1: Groq API with High-Speed Models (Qwen 2.5/3.8, GPT-OSS) - ~300ms ultra-fast response
  if (process.env.GROQ_API_KEY) {
    const groqModels = ['qwen/qwen3.8-27b', 'openai/gpt-oss-120b', 'openai/gpt-oss-20b'];
    for (const model of groqModels) {
      try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 9000);
        const response = await fetch('https://api.groq.com/openai/v1/chat/completions', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${process.env.GROQ_API_KEY.trim()}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            model,
            messages: [
              { role: 'system', content: `${systemInstruction} Respon HANYA berupa JSON valid tanpa teks pembuka atau markdown.` },
              { role: 'user', content: prompt }
            ],
            response_format: { type: 'json_object' },
            temperature
          }),
          signal: controller.signal
        });
        clearTimeout(timeoutId);

        if (response.ok) {
          const data = await response.json();
          const content = data.choices && data.choices[0]?.message?.content;
          if (content) {
            const parsed = parseAIJson(content);
            if (parsed) return parsed;
          }
        }
      } catch (err) {
        console.warn(`Groq tier (${model}) attempt note:`, err.message);
      }
    }
  }

  // Tier 2: Google Gemini (gemini-3.8-flash) via official @google/genai SDK
  if (process.env.GEMINI_API_KEY) {
    try {
      const ai = new GoogleGenAI({ apiKey: process.env.GEMINI_API_KEY });
      const geminiPromise = ai.models.generateContent({
        model: 'gemini-3.8-flash',
        contents: prompt,
        config: {
          systemInstruction,
          responseMimeType: 'application/json',
          temperature
        }
      });
      // 8 second timeout to prevent indefinite blocking on high-demand spikes
      const timeoutPromise = new Promise((_, reject) => setTimeout(() => reject(new Error('Gemini timeout')), 8000));
      const response = await Promise.race([geminiPromise, timeoutPromise]);
      const text = response.text || '';
      if (text) {
        const parsed = parseAIJson(text);
        if (parsed) return parsed;
      }
    } catch (err) {
      console.warn('Gemini 3.8 Flash attempt note:', err.message);
    }
  }

  return null;
}

// API: Generate Kisi-Kisi AI (Multi-Type Question & Custom Bloom Levels, No Jenjang)
app.post(['/admin/api/generate_kisi_kisi'], requireAdmin, async (req, res) => {
  const { ujian_id, topik, sub_topik, cpmk, jumlah, kesulitan, jenis_soal_list, kognitif_list, apply } = req.body;
  const count = parseInt(jumlah, 10) || 5;
  const targetUjian = db.ujian.find(u => u.id === parseInt(ujian_id, 10)) || db.ujian[0];
  const matkul = targetUjian ? db.mata_kuliah.find(m => m.id === targetUjian.id_mata_kuliah) : null;
  const effectiveTopik = topik || (matkul ? matkul.nama_mk : (targetUjian ? targetUjian.judul_ujian : 'Mata Kuliah'));

  // Parse multi-select question types and cognitive levels
  let selectedTypes = ['pg'];
  if (Array.isArray(jenis_soal_list) && jenis_soal_list.length > 0) {
    selectedTypes = jenis_soal_list;
  } else if (typeof jenis_soal_list === 'string' && jenis_soal_list.trim()) {
    selectedTypes = jenis_soal_list.split(',').map(s => s.trim()).filter(Boolean);
  }

  let selectedCognitives = ['C2', 'C3', 'C4'];
  if (Array.isArray(kognitif_list) && kognitif_list.length > 0) {
    selectedCognitives = kognitif_list;
  } else if (typeof kognitif_list === 'string' && kognitif_list.trim()) {
    selectedCognitives = kognitif_list.split(',').map(s => s.trim()).filter(Boolean);
  }

  const typesReadable = selectedTypes.map(t => getJenisSoalLabel(t)).join(', ');
  const cognitivesReadable = selectedCognitives.join(', ');

  let generatedKisi = [];

  const prompt = `Anda adalah dosen pakar kurikulum dan evaluasi pendidikan perguruan tinggi di Indonesia.
Rancanglah ${count} baris matriks kisi-kisi penulisan soal ujian akademik yang komprehensif, terstruktur, dan selaras dengan Standar Nasional Pendidikan Tinggi (SN-Dikti).
Mata Kuliah / Topik: "${effectiveTopik}".
Ruang Lingkup & Sub-Materi: "${sub_topik || 'Kaidah konseptual dan implementasi praktis standar'}".
Capaian Pembelajaran (CPMK): "${cpmk || 'Menguasai konsep teoritis dan kemampuan analisis terapan pada bidang terkait'}".
Distribusi Kesulitan: "${kesulitan || 'proporsional'}".
BENTUK / JENIS SOAL YANG DIPILIH: [${typesReadable}].
PENTING: Anda WAJIB menyusun ${count} butir soal dengan mendistribusikan bentuk soal di antara jenis-jenis yang dipilih di atas secara proporsional.
LEVEL KOGNITIF BLOOM YANG DIPILIH: [${cognitivesReadable}].
PENTING: Setiap butir soal WAJIB menggunakan salah satu level kognitif dari daftar yang dipilih di atas!

PANDUAN FORMAT BERDASARKAN BENTUK SOAL:
- "pg" (Pilihan Ganda): 5 opsi (A, B, C, D, E) dengan tepat 1 kunci benar. Teks pertanyaan tanpa huruf opsi di dalamnya.
- "pg_kompleks" (Pilihan Ganda Kompleks): 4-5 pilihan pernyataan dengan 2 atau lebih jawaban benar (peserta memilih lebih dari satu).
- "tf" (Benar / Salah): tepat 2 opsi [{"teks": "Benar", "benar": true/false}, {"teks": "Salah", "benar": false/true}].
- "menjodohkan" (Menjodohkan): premis dan pasangan kunci respon yang selaras.
- "short" (Isian Singkat): pertanyaan langsung dengan frasa kunci jawaban terukur pada opsi atau pembahasan.
- "esai" (Uraian / Esai): studi kasus mendalam lengkap dengan kriteria rubrik penskoran pada kolom pembahasan.

Format respon HANYA berupa JSON valid:
{
  "kisi_kisi": [
    {
      "cpmk": "rumusan capaian pembelajaran spesifik",
      "materi_pokok": "materi atau sub-materi pokok",
      "indikator": "indikator ketercapaian kompetensi butir soal",
      "bentuk_soal": "pg | pg_kompleks | tf | menjodohkan | short | esai",
      "level_kognitif": "${selectedCognitives[0] || 'C3'}",
      "tingkat_kesulitan": "mudah | sedang | sulit",
      "bobot": 4,
      "pertanyaan": "teks stimulus studi kasus dan pertanyaan langsung yang jelas",
      "opsi": [
        {"teks": "rumusan jawaban A", "benar": true},
        {"teks": "rumusan jawaban B", "benar": false}
      ],
      "pembahasan": "penjelasan ilmiah terperinci / rubrik penilaian"
    }
  ]
}`;

  const parsed = await generateWithAI({
    prompt,
    systemInstruction: 'Anda adalah dosen pakar kurikulum dan instrumen evaluasi perguruan tinggi. Balas HANYA JSON valid.'
  });

  if (parsed && Array.isArray(parsed.kisi_kisi) && parsed.kisi_kisi.length > 0) {
    generatedKisi = parsed.kisi_kisi;
  }

  // Fallback high-quality academic indicators & questions if needed
  if (generatedKisi.length === 0) {
    const fallbackQuestions = generateAcademicQuestionsFallback({
      courseName: effectiveTopik,
      topic: effectiveTopik,
      subTopic: sub_topik,
      cpmk,
      count,
      difficulty: kesulitan,
      cognitive: selectedCognitives[0] || 'C3',
      jenisList: selectedTypes,
      cognitiveList: selectedCognitives
    });

    generatedKisi = fallbackQuestions.map((q) => ({
      cpmk: q.cpmk,
      materi_pokok: q.materi_pokok,
      indikator: q.indikator,
      bentuk_soal: q.jenis_soal || 'pg',
      level_kognitif: q.level_kognitif,
      tingkat_kesulitan: q.tingkat_kesulitan,
      bobot: q.poin || 4,
      pertanyaan: q.pertanyaan,
      opsi: q.opsi,
      pembahasan: q.pembahasan
    }));
  }

  // Sanitize each item's questions and options
  generatedKisi = generatedKisi.map((item) => {
    const sanitized = sanitizeQuestions([item], item.materi_pokok || effectiveTopik, item.bobot || 4)[0];
    return {
      cpmk: item.cpmk || sanitized.cpmk,
      materi_pokok: item.materi_pokok || sanitized.materi_pokok,
      indikator: item.indikator || sanitized.indikator,
      bentuk_soal: sanitized.jenis_soal || item.bentuk_soal || 'pg',
      level_kognitif: item.level_kognitif || sanitized.level_kognitif,
      tingkat_kesulitan: item.tingkat_kesulitan || sanitized.tingkat_kesulitan,
      bobot: parseInt(item.bobot, 10) || sanitized.poin || 4,
      pertanyaan: sanitized.pertanyaan,
      opsi: sanitized.opsi,
      pembahasan: sanitized.pembahasan
    };
  });

  // Permanently save to database by default (apply !== false)
  const shouldApply = apply !== false;
  if (shouldApply && targetUjian) {
    const existingSoal = db.soal.filter(s => s.id_ujian === targetUjian.id);
    let nextSoalId = db.soal.length ? Math.max(...db.soal.map(s => s.id)) + 1 : 1;
    let nextOpsiId = db.opsi_jawaban.length ? Math.max(...db.opsi_jawaban.map(o => o.id)) + 1 : 1;

    generatedKisi.forEach((k, idx) => {
      if (existingSoal[idx]) {
        // Update existing question
        existingSoal[idx].cpmk = k.cpmk;
        existingSoal[idx].materi_pokok = k.materi_pokok;
        existingSoal[idx].indikator = k.indikator;
        existingSoal[idx].jenis_soal = k.bentuk_soal || 'pg';
        existingSoal[idx].level_kognitif = k.level_kognitif || 'C3';
        existingSoal[idx].tingkat_kesulitan = k.tingkat_kesulitan || 'sedang';
        existingSoal[idx].poin = k.bobot || existingSoal[idx].poin || 4;
        if (k.pertanyaan && !k.pertanyaan.startsWith('[Kisi-Kisi]')) {
          existingSoal[idx].pertanyaan = k.pertanyaan;
        }
        if (k.pembahasan) {
          existingSoal[idx].pembahasan = k.pembahasan;
        }
        if (Array.isArray(k.opsi) && k.opsi.length > 0) {
          db.opsi_jawaban = db.opsi_jawaban.filter(o => o.id_soal !== existingSoal[idx].id);
          k.opsi.forEach((o, oIdx) => {
            db.opsi_jawaban.push({
              id: nextOpsiId++,
              id_soal: existingSoal[idx].id,
              teks_opsi: o.teks,
              benar: Boolean(o.benar),
              urutan: oIdx + 1
            });
          });
        }
      } else {
        // Add new test-ready question
        const newSoalId = nextSoalId++;
        db.soal.push({
          id: newSoalId,
          id_ujian: targetUjian.id,
          jenis_soal: k.bentuk_soal || 'pg',
          pertanyaan: k.pertanyaan,
          pembahasan: k.pembahasan || 'Pembahasan kunci jawaban.',
          poin: k.bobot || 4,
          urutan: db.soal.filter(s => s.id_ujian === targetUjian.id).length + 1,
          data_tambahan: null,
          tingkat_kesulitan: k.tingkat_kesulitan || 'sedang',
          level_kognitif: k.level_kognitif || 'C3',
          cpmk: k.cpmk || 'Capaian Pembelajaran MK',
          materi_pokok: k.materi_pokok || targetUjian.judul_ujian,
          indikator: k.indikator || 'Indikator ketercapaian soal'
        });

        if (Array.isArray(k.opsi) && k.opsi.length > 0) {
          k.opsi.forEach((o, oIdx) => {
            db.opsi_jawaban.push({
              id: nextOpsiId++,
              id_soal: newSoalId,
              teks_opsi: o.teks,
              benar: Boolean(o.benar),
              urutan: oIdx + 1
            });
          });
        }
      }
    });

    db.save(); // Synchronous atomic persistence to disk
  }

  return res.json({ success: true, count: generatedKisi.length, data: generatedKisi, saved: shouldApply });
});

// API: Save / Apply approved Kisi-Kisi matrix after user preview
app.post(['/admin/api/save_kisi_kisi'], requireAdmin, (req, res) => {
  const { ujian_id, items } = req.body;
  const targetUjian = db.ujian.find(u => u.id === parseInt(ujian_id, 10));
  if (!targetUjian || !Array.isArray(items)) {
    return res.status(400).json({ success: false, message: 'Data tidak valid' });
  }

  const existingSoal = db.soal.filter(s => s.id_ujian === targetUjian.id);
  let nextSoalId = db.soal.length ? Math.max(...db.soal.map(s => s.id)) + 1 : 1;
  let nextOpsiId = db.opsi_jawaban.length ? Math.max(...db.opsi_jawaban.map(o => o.id)) + 1 : 1;

  items.forEach((k, idx) => {
    if (existingSoal[idx]) {
      existingSoal[idx].cpmk = k.cpmk;
      existingSoal[idx].materi_pokok = k.materi_pokok;
      existingSoal[idx].indikator = k.indikator;
      existingSoal[idx].jenis_soal = k.bentuk_soal || existingSoal[idx].jenis_soal || 'pg';
      existingSoal[idx].level_kognitif = k.level_kognitif || 'C3';
      existingSoal[idx].tingkat_kesulitan = k.tingkat_kesulitan || 'sedang';
      if (k.bobot) existingSoal[idx].poin = parseInt(k.bobot, 10) || existingSoal[idx].poin;
      if (k.pertanyaan) existingSoal[idx].pertanyaan = k.pertanyaan;
      if (k.pembahasan) existingSoal[idx].pembahasan = k.pembahasan;
      if (Array.isArray(k.opsi) && k.opsi.length > 0) {
        db.opsi_jawaban = db.opsi_jawaban.filter(o => o.id_soal !== existingSoal[idx].id);
        k.opsi.forEach((o, oIdx) => {
          db.opsi_jawaban.push({
            id: nextOpsiId++,
            id_soal: existingSoal[idx].id,
            teks_opsi: o.teks || o.teks_opsi,
            benar: Boolean(o.benar),
            urutan: oIdx + 1
          });
        });
      }
    } else {
      const newSoalId = nextSoalId++;
      db.soal.push({
        id: newSoalId,
        id_ujian: targetUjian.id,
        jenis_soal: k.bentuk_soal || 'pg',
        pertanyaan: k.pertanyaan || `Berdasarkan indikator ${k.indikator}, manakah rumusan pemecahan kasus yang paling tepat?`,
        pembahasan: k.pembahasan || 'Pembahasan indikator capaian kompetensi.',
        poin: parseInt(k.bobot, 10) || 4,
        urutan: db.soal.filter(s => s.id_ujian === targetUjian.id).length + 1,
        tingkat_kesulitan: k.tingkat_kesulitan || 'sedang',
        level_kognitif: k.level_kognitif || 'C3',
        cpmk: k.cpmk || 'Capaian Pembelajaran MK',
        materi_pokok: k.materi_pokok || targetUjian.judul_ujian,
        indikator: k.indikator || 'Indikator capaian soal'
      });
      if (Array.isArray(k.opsi) && k.opsi.length > 0) {
        k.opsi.forEach((o, oIdx) => {
          db.opsi_jawaban.push({
            id: nextOpsiId++,
            id_soal: newSoalId,
            teks_opsi: o.teks || o.teks_opsi,
            benar: Boolean(o.benar),
            urutan: oIdx + 1
          });
        });
      } else {
        ['A', 'B', 'C', 'D', 'E'].forEach((letter, oIdx) => {
          db.opsi_jawaban.push({
            id: nextOpsiId++,
            id_soal: newSoalId,
            teks_opsi: `Analisis opsi ${letter} terkait ${k.materi_pokok || 'materi ujian'}`,
            benar: oIdx === 0,
            urutan: oIdx + 1
          });
        });
      }
    }
  });

  db.save();
  return res.json({ success: true, count: items.length });
});

// API: Generate Soal dari Matriks Kisi-Kisi (Multi-Type Question & Custom Bloom Levels, No Jenjang)
app.post(['/admin/api/generate_soal_kisi_kisi'], requireAdmin, async (req, res) => {
  const { ujian_id, jumlah, poin, fokus_materi, jenis_soal_list, kesulitan, kognitif_list, apply } = req.body;
  const count = parseInt(jumlah, 10) || 5;
  const eachPoin = parseInt(poin, 10) || 4;
  const targetUjian = db.ujian.find(u => u.id === parseInt(ujian_id, 10)) || db.ujian[0];
  const matkul = targetUjian ? db.mata_kuliah.find(m => m.id === targetUjian.id_mata_kuliah) : null;
  const courseName = matkul ? matkul.nama_mk : (targetUjian ? targetUjian.judul_ujian : 'Mata Kuliah');

  // Parse multi-select question types and cognitive levels
  let selectedTypes = ['pg'];
  if (Array.isArray(jenis_soal_list) && jenis_soal_list.length > 0) {
    selectedTypes = jenis_soal_list;
  } else if (typeof jenis_soal_list === 'string' && jenis_soal_list.trim()) {
    selectedTypes = jenis_soal_list.split(',').map(s => s.trim()).filter(Boolean);
  }

  let selectedCognitives = ['C2', 'C3', 'C4'];
  if (Array.isArray(kognitif_list) && kognitif_list.length > 0) {
    selectedCognitives = kognitif_list;
  } else if (typeof kognitif_list === 'string' && kognitif_list.trim()) {
    selectedCognitives = kognitif_list.split(',').map(s => s.trim()).filter(Boolean);
  }

  const typesReadable = selectedTypes.map(t => getJenisSoalLabel(t)).join(', ');
  const cognitivesReadable = selectedCognitives.join(', ');

  let generatedQuestions = [];

  const prompt = `Anda adalah dosen pakar penyusun naskah ujian perguruan tinggi di Indonesia.
Susunlah ${count} butir naskah soal akademik berkualitas tinggi dan SIAP DIUJIKAN LANGSUNG KEPADA PESERTA untuk mata kuliah "${courseName}".
Fokus Ruang Lingkup Materi: "${fokus_materi || courseName}".
Tingkat Kesulitan: "${kesulitan || 'proporsional'}".
PILIHAN BENTUK/JENIS SOAL: [${typesReadable}].
PENTING: Anda WAJIB menyusun ${count} butir soal dengan mendistribusikan bentuk soal di antara jenis-jenis yang dipilih di atas secara seimbang.
LEVEL KOGNITIF BLOOM YANG DIPILIH: [${cognitivesReadable}].
PENTING: Setiap butir soal WAJIB menggunakan salah satu level kognitif dari daftar yang ditentukan di atas!

PERSYARATAN FORMAT SESUAI BENTUK SOAL:
1. "pg" (Pilihan Ganda): teks pertanyaan tanpa huruf pilihan + 5 opsi (A-E) dengan TEPAT SATU kunci benar.
2. "pg_kompleks" (Pilihan Ganda Kompleks): teks pertanyaan/pernyataan kasus + 4-5 pilihan di mana 2 atau lebih bernilai benar.
3. "tf" (Benar / Salah): 2 opsi: [{"teks": "Benar", "benar": true/false}, {"teks": "Salah", "benar": false/true}].
4. "menjodohkan" (Menjodohkan): premis dan pasangan kunci respon yang selaras.
5. "short" (Isian Singkat): pertanyaan langsung dengan frasa kunci jawaban terukur pada opsi atau pembahasan.
6. "esai" (Uraian / Esai): permasalahan mendalam lengkap dengan kriteria rubrik penskoran pada kolom pembahasan.

Format respon HANYA berupa JSON valid:
{
  "soal": [
    {
      "pertanyaan": "teks narasi stimulus dan pertanyaan yang jelas tanpa opsi huruf di dalamnya",
      "jenis_soal": "pg | pg_kompleks | tf | menjodohkan | short | esai",
      "cpmk": "capaian pembelajaran mata kuliah spesifik",
      "materi_pokok": "materi pokok pembahasan",
      "indikator": "indikator ketercapaian kompetensi butir soal",
      "level_kognitif": "${selectedCognitives[0] || 'C3'}",
      "tingkat_kesulitan": "sedang",
      "opsi": [
        {"teks": "rumusan jawaban A", "benar": true},
        {"teks": "rumusan jawaban B", "benar": false}
      ],
      "pembahasan": "penjelasan ilmiah mendalam / rubrik penilaian"
    }
  ]
}`;

  const parsed = await generateWithAI({
    prompt,
    systemInstruction: 'Anda adalah dosen pakar penyusun soal ujian universitas. Balas HANYA JSON valid.'
  });

  if (parsed && Array.isArray(parsed.soal) && parsed.soal.length > 0) {
    generatedQuestions = parsed.soal;
  }

  // Fallback high-quality questions if needed
  if (generatedQuestions.length === 0) {
    generatedQuestions = generateAcademicQuestionsFallback({
      courseName,
      topic: courseName,
      subTopic: fokus_materi,
      count,
      poin: eachPoin,
      difficulty: kesulitan,
      cognitive: selectedCognitives[0] || 'C3',
      jenisList: selectedTypes,
      cognitiveList: selectedCognitives
    });
  }

  // Sanitize questions
  generatedQuestions = sanitizeQuestions(generatedQuestions, courseName, eachPoin);

  // Permanently auto-save to database by default (apply !== false)
  const shouldApply = apply !== false;
  if (shouldApply && targetUjian) {
    let nextSoalId = db.soal.length ? Math.max(...db.soal.map(s => s.id)) + 1 : 1;
    let nextOpsiId = db.opsi_jawaban.length ? Math.max(...db.opsi_jawaban.map(o => o.id)) + 1 : 1;
    const currentCount = db.soal.filter(s => s.id_ujian === targetUjian.id).length;

    generatedQuestions.forEach((q, idx) => {
      const soalId = nextSoalId++;
      db.soal.push({
        id: soalId,
        id_ujian: targetUjian.id,
        jenis_soal: q.jenis_soal || 'pg',
        pertanyaan: q.pertanyaan,
        pembahasan: q.pembahasan || 'Pembahasan kunci jawaban.',
        poin: q.poin || eachPoin,
        urutan: currentCount + idx + 1,
        data_tambahan: null,
        tingkat_kesulitan: q.tingkat_kesulitan || 'sedang',
        level_kognitif: q.level_kognitif || 'C3',
        cpmk: q.cpmk || 'Capaian Pembelajaran MK',
        materi_pokok: q.materi_pokok || courseName,
        indikator: q.indikator || 'Indikator ketercapaian soal'
      });

      if (Array.isArray(q.opsi) && q.opsi.length > 0) {
        q.opsi.forEach((o, oIdx) => {
          db.opsi_jawaban.push({
            id: nextOpsiId++,
            id_soal: soalId,
            teks_opsi: o.teks || o.teks_opsi,
            benar: Boolean(o.benar),
            urutan: oIdx + 1
          });
        });
      }
    });

    db.save(); // Synchronous atomic persistence to disk
  }

  return res.json({ success: true, count: generatedQuestions.length, data: generatedQuestions, saved: shouldApply });
});

// API: Save / Apply approved questions from Kisi-Kisi
app.post(['/admin/api/save_soal_kisi_kisi'], requireAdmin, (req, res) => {
  const { ujian_id, questions, poin } = req.body;
  const targetUjian = db.ujian.find(u => u.id === parseInt(ujian_id, 10));
  if (!targetUjian || !Array.isArray(questions)) {
    return res.status(400).json({ success: false, message: 'Data tidak valid' });
  }

  const eachPoin = parseInt(poin, 10) || 4;
  let nextSoalId = db.soal.length ? Math.max(...db.soal.map(s => s.id)) + 1 : 1;
  let nextOpsiId = db.opsi_jawaban.length ? Math.max(...db.opsi_jawaban.map(o => o.id)) + 1 : 1;
  const currentCount = db.soal.filter(s => s.id_ujian === targetUjian.id).length;

  questions.forEach((q, idx) => {
    const soalId = nextSoalId++;
    db.soal.push({
      id: soalId,
      id_ujian: targetUjian.id,
      jenis_soal: q.jenis_soal || 'pg',
      pertanyaan: q.pertanyaan,
      pembahasan: q.pembahasan || 'Pembahasan kunci jawaban.',
      poin: q.poin || eachPoin,
      urutan: currentCount + idx + 1,
      data_tambahan: null,
      tingkat_kesulitan: q.tingkat_kesulitan || 'sedang',
      level_kognitif: q.level_kognitif || 'C3',
      cpmk: q.cpmk || 'Capaian Pembelajaran MK',
      materi_pokok: q.materi_pokok || targetUjian.judul_ujian,
      indikator: q.indikator || 'Indikator ketercapaian soal'
    });

    if (Array.isArray(q.opsi)) {
      q.opsi.forEach((o, oIdx) => {
        db.opsi_jawaban.push({
          id: nextOpsiId++,
          id_soal: soalId,
          teks_opsi: o.teks || o.teks_opsi,
          benar: Boolean(o.benar),
          urutan: oIdx + 1
        });
      });
    }
  });

  db.save();
  return res.json({ success: true, count: questions.length });
});

// Admin Riwayat & Arsip
app.get(['/admin/arsip', '/admin/arsip.php'], requireAdmin, (req, res) => {
  res.render('admin/arsip', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    riwayat: db.riwayat_sesi_ujian
  });
});

// Admin Master Data: Fakultas
app.get(['/admin/fakultas', '/admin/fakultas.php'], requireAdminOnly, (req, res) => {
  res.render('admin/fakultas', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    fakultasList: db.fakultas,
    message: req.query.msg || ''
  });
});

app.post(['/admin/fakultas', '/admin/fakultas.php'], requireAdminOnly, (req, res) => {
  const { action, kode_fakultas, nama_fakultas, id } = req.body;
  if (action === 'create' && kode_fakultas && nama_fakultas) {
    const newId = db.fakultas.length ? Math.max(...db.fakultas.map(f => f.id)) + 1 : 1;
    db.fakultas.push({ id: newId, kode_fakultas: kode_fakultas.trim(), nama_fakultas: nama_fakultas.trim() });
    db.save();
  } else if (action === 'edit' && id && kode_fakultas && nama_fakultas) {
    const targetId = parseInt(id, 10);
    const f = db.fakultas.find(x => x.id === targetId);
    if (f) {
      f.kode_fakultas = kode_fakultas.trim();
      f.nama_fakultas = nama_fakultas.trim();
      db.save();
    }
  } else if (action === 'delete') {
    db.fakultas = db.fakultas.filter(f => f.id !== parseInt(id, 10));
    db.save();
  }
  res.redirect('/admin/fakultas?msg=' + encodeURIComponent('Data fakultas berhasil diperbarui!'));
});

// Admin Master Data: Prodi
app.get(['/admin/prodi', '/admin/prodi.php'], requireAdminOnly, (req, res) => {
  const prodis = db.program_studi.map(p => {
    const f = db.fakultas.find(fak => fak.id === p.id_fakultas);
    return { ...p, nama_fakultas: f ? f.nama_fakultas : '-' };
  });

  res.render('admin/prodi', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    prodiList: prodis,
    fakultasList: db.fakultas,
    message: req.query.msg || ''
  });
});

app.post(['/admin/prodi', '/admin/prodi.php'], requireAdminOnly, (req, res) => {
  const { action, kode_prodi, nama_prodi, id_fakultas, id } = req.body;
  if (action === 'create' && kode_prodi && nama_prodi) {
    const newId = db.program_studi.length ? Math.max(...db.program_studi.map(p => p.id)) + 1 : 1;
    db.program_studi.push({ id: newId, kode_prodi: kode_prodi.trim(), nama_prodi: nama_prodi.trim(), id_fakultas: parseInt(id_fakultas, 10) || null });
    db.save();
  } else if (action === 'edit' && id && kode_prodi && nama_prodi) {
    const targetId = parseInt(id, 10);
    const p = db.program_studi.find(prodi => prodi.id === targetId);
    if (p) {
      p.kode_prodi = kode_prodi.trim();
      p.nama_prodi = nama_prodi.trim();
      p.id_fakultas = parseInt(id_fakultas, 10) || p.id_fakultas;
      db.save();
    }
  } else if (action === 'delete') {
    db.program_studi = db.program_studi.filter(p => p.id !== parseInt(id, 10));
    db.save();
  }
  res.redirect('/admin/prodi?msg=' + encodeURIComponent('Data prodi berhasil diperbarui!'));
});

// Admin Master Data: Kelas
app.get(['/admin/kelas', '/admin/kelas.php'], requireAdminOnly, (req, res) => {
  const classes = db.kelas.map(k => {
    const p = db.program_studi.find(prodi => prodi.id === k.id_program_studi);
    return { ...k, nama_prodi: p ? p.nama_prodi : '-' };
  });

  res.render('admin/kelas', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    kelasList: classes,
    prodiList: db.program_studi,
    message: req.query.msg || ''
  });
});

app.post(['/admin/kelas', '/admin/kelas.php'], requireAdminOnly, (req, res) => {
  const { action, nama_kelas, angkatan, semester, id_program_studi, id } = req.body;
  if (action === 'create' && nama_kelas && id_program_studi) {
    const newId = db.kelas.length ? Math.max(...db.kelas.map(k => k.id)) + 1 : 1;
    db.kelas.push({
      id: newId,
      nama_kelas: nama_kelas.trim(),
      angkatan: parseInt(angkatan, 10) || new Date().getFullYear(),
      semester: parseInt(semester, 10) || 1,
      id_program_studi: parseInt(id_program_studi, 10)
    });
    db.save();
  } else if (action === 'edit' && id && nama_kelas && id_program_studi) {
    const targetId = parseInt(id, 10);
    const k = db.kelas.find(item => item.id === targetId);
    if (k) {
      k.nama_kelas = nama_kelas.trim();
      k.angkatan = parseInt(angkatan, 10) || k.angkatan;
      k.semester = parseInt(semester, 10) || k.semester;
      k.id_program_studi = parseInt(id_program_studi, 10);
      db.save();
    }
  } else if (action === 'delete') {
    db.kelas = db.kelas.filter(k => k.id !== parseInt(id, 10));
    db.save();
  }
  res.redirect('/admin/kelas?msg=' + encodeURIComponent('Data kelas berhasil diperbarui!'));
});

// Admin Master Data: Matkul
app.get(['/admin/matkul', '/admin/matkul.php'], requireAdminOnly, (req, res) => {
  res.render('admin/matkul', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    matkulList: db.mata_kuliah,
    message: req.query.msg || ''
  });
});

app.post(['/admin/matkul', '/admin/matkul.php'], requireAdminOnly, (req, res) => {
  const { action, kode_mk, nama_mk, sks, id } = req.body;
  if (action === 'create' && kode_mk && nama_mk) {
    const newId = db.mata_kuliah.length ? Math.max(...db.mata_kuliah.map(m => m.id)) + 1 : 1;
    db.mata_kuliah.push({
      id: newId,
      kode_mk: kode_mk.trim(),
      nama_mk: nama_mk.trim(),
      sks: parseInt(sks, 10) || 3
    });
    db.save();
  } else if (action === 'edit' && id && kode_mk && nama_mk) {
    const targetId = parseInt(id, 10);
    const mk = db.mata_kuliah.find(m => m.id === targetId);
    if (mk) {
      mk.kode_mk = kode_mk.trim();
      mk.nama_mk = nama_mk.trim();
      mk.sks = parseInt(sks, 10) || mk.sks;
      db.save();
    }
  } else if (action === 'delete') {
    db.mata_kuliah = db.mata_kuliah.filter(m => m.id !== parseInt(id, 10));
    db.save();
  }
  res.redirect('/admin/matkul?msg=' + encodeURIComponent('Data materi/mata kuliah berhasil diperbarui!'));
});

// Helper function to build detailed participant and exam session status data
function getStatusPesertaData() {
  // Ensure all peserta users have a corresponding mahasiswa record
  db.users.filter(u => u.role === 'peserta').forEach(u => {
    const studentNim = u.nim || u.username;
    db.getOrCreateMahasiswa(studentNim, u.nama_lengkap, u.id_kelas || 1, u.id);
  });

  return db.mahasiswa.map((m, idx) => {
    const user = db.users.find(u => u.id === m.id_user || (m.nim && u.username === m.nim));
    let k = db.kelas.find(kls => kls.id === m.id_kelas);
    if (!k && user && user.id_kelas) {
      k = db.kelas.find(kls => kls.id === user.id_kelas);
    }
    const sesi = db.sesi_ujian.find(s => s.id_mahasiswa === m.id) ||
                 db.riwayat_sesi_ujian.find(r => r.id_mahasiswa === m.id);

    let status = 'belum';
    let status_label = 'Belum Ujian';
    let nilai_total = null;
    let waktu_mulai = '-';
    let waktu_selesai = '-';
    let durasi_pengerjaan = '-';
    let durasi_detik = 0;
    let sesi_id = null;
    let id_ujian = null;
    let judul_ujian = '-';

    const isLoggedIn = user && user.is_logged_in;

    if (sesi) {
      sesi_id = sesi.id;
      id_ujian = sesi.id_ujian;
      const ujianObj = db.ujian.find(u => u.id === sesi.id_ujian);
      if (ujianObj) judul_ujian = ujianObj.judul_ujian;

      status = sesi.status || 'berlangsung';
      nilai_total = sesi.nilai_total !== undefined && sesi.nilai_total !== null ? sesi.nilai_total : null;

      if (sesi.waktu_mulai) {
        const dm = new Date(sesi.waktu_mulai);
        waktu_mulai = dm.toLocaleDateString('id-ID') + ' ' + dm.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      }

      if (sesi.waktu_selesai) {
        const ds = new Date(sesi.waktu_selesai);
        waktu_selesai = ds.toLocaleDateString('id-ID') + ' ' + ds.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      }

      // Hitung Durasi Pengerjaan Tes
      if (sesi.waktu_mulai && sesi.waktu_selesai) {
        const start = new Date(sesi.waktu_mulai).getTime();
        const end = new Date(sesi.waktu_selesai).getTime();
        durasi_detik = Math.max(0, Math.floor((end - start) / 1000));
        const jam = Math.floor(durasi_detik / 3600);
        const mnt = Math.floor((durasi_detik % 3600) / 60);
        const dtk = durasi_detik % 60;
        durasi_pengerjaan = (jam > 0 ? jam + ' jam ' : '') + mnt + ' mnt ' + dtk + ' dtk';
      } else if (sesi.waktu_mulai && status === 'berlangsung') {
        const start = new Date(sesi.waktu_mulai).getTime();
        durasi_detik = Math.max(0, Math.floor((Date.now() - start) / 1000));
        const jam = Math.floor(durasi_detik / 3600);
        const mnt = Math.floor((durasi_detik % 3600) / 60);
        const dtk = durasi_detik % 60;
        durasi_pengerjaan = (jam > 0 ? jam + ' jam ' : '') + mnt + ' mnt ' + dtk + ' dtk (Aktif)';
      }

      if (status === 'berlangsung') {
        status_label = 'Test Sedang Dikerjakan';
      } else if (status === 'selesai') {
        status_label = 'Test Selesai';
      } else if (status === 'timeout') {
        status_label = 'Waktu Habis';
      }
    } else if (isLoggedIn) {
      status = 'sedang_login';
      status_label = 'Sedang Login';
      if (user.last_login) {
        const dl = new Date(user.last_login);
        waktu_mulai = 'Login: ' + dl.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      }
    }

    return {
      no: idx + 1,
      id: m.id,
      user_id: user ? user.id : null,
      nim: m.nim,
      nama_lengkap: m.nama_lengkap,
      id_kelas: m.id_kelas || (k ? k.id : null),
      nama_kelas: k ? k.nama_kelas : (user && user.nama_kelas ? user.nama_kelas : '-'),
      status,
      status_label,
      is_logged_in: !!isLoggedIn,
      is_login_only: !sesi && !!isLoggedIn,
      nilai_total,
      waktu_mulai,
      waktu_selesai,
      waktu_mulai_iso: sesi && sesi.waktu_mulai ? new Date(sesi.waktu_mulai).toISOString() : (user && user.last_login ? new Date(user.last_login).toISOString() : null),
      waktu_selesai_iso: sesi && sesi.waktu_selesai ? new Date(sesi.waktu_selesai).toISOString() : null,
      durasi_pengerjaan,
      durasi_detik,
      sesi_id,
      id_ujian,
      judul_ujian
    };
  });
}

// Admin Master Data: Users
app.get(['/admin/users', '/admin/users.php'], requireAdminOnly, (req, res) => {
  // Enrich users with id_kelas and nama_kelas
  const usersWithMeta = db.users.map(u => {
    let id_kelas = u.id_kelas;
    let nama_kelas = '';
    const mhs = db.mahasiswa.find(m => m.id_user === u.id || (u.nim && m.nim === u.nim));
    if (mhs) {
      id_kelas = id_kelas || mhs.id_kelas;
      const k = db.kelas.find(kls => kls.id === mhs.id_kelas);
      nama_kelas = k ? k.nama_kelas : '';
    } else if (id_kelas) {
      const k = db.kelas.find(kls => kls.id === id_kelas);
      nama_kelas = k ? k.nama_kelas : '';
    }
    return {
      ...u,
      id_kelas: id_kelas || '',
      nama_kelas
    };
  });

  // Prepare status peserta for tab "Status Peserta"
  const statusPesertaList = getStatusPesertaData();

  res.render('admin/users', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    usersList: usersWithMeta,
    kelasList: db.kelas,
    statusPesertaList,
    activeTab: req.query.tab || 'daftar',
    message: req.query.msg || '',
    error: req.query.err || ''
  });
});

// Real-time API endpoint for Status Peserta live polling
app.get(['/admin/api/status_peserta'], requireAdminOnly, (req, res) => {
  const data = getStatusPesertaData();
  res.json({
    success: true,
    server_time: new Date().toISOString(),
    server_epoch: Date.now(),
    total: data.length,
    active_count: data.filter(d => d.status === 'berlangsung').length,
    login_count: data.filter(d => d.status === 'sedang_login').length,
    completed_count: data.filter(d => d.status === 'selesai').length,
    data
  });
});

app.post(['/admin/users', '/admin/users.php'], requireAdminOnly, (req, res) => {
  const { action, username, password, nama_lengkap, role, nim, id_kelas, user_id, sesi_id } = req.body;

  if (action === 'reset_password') {
    const targetUserId = parseInt(user_id, 10);
    const targetUser = db.users.find(u => u.id === targetUserId);
    if (targetUser) {
      db.resetUserPassword(targetUserId, '12345*');
      return res.redirect('/admin/users?msg=' + encodeURIComponent(`Password akun "${targetUser.username}" berhasil direset ke password standar: 12345*`));
    }
  } else if (action === 'create' && username && nama_lengkap) {
    if (db.users.some(u => u.username.toLowerCase() === username.trim().toLowerCase())) {
      return res.redirect('/admin/users?err=' + encodeURIComponent('Username sudah digunakan! Gunakan username unik lain.'));
    }
    const newId = db.users.length ? Math.max(...db.users.map(u => u.id)) + 1 : 1;
    const finalPassword = (password || '').trim() || '12345*';
    const kelasNumber = id_kelas ? parseInt(id_kelas, 10) : 1;

    db.users.push({
      id: newId,
      username: username.trim(),
      password: bcrypt.hashSync(finalPassword, 10),
      nama_lengkap: nama_lengkap.trim(),
      role: role || 'peserta',
      nim: nim ? nim.trim() : (role === 'peserta' ? username.trim() : null),
      id_kelas: role === 'peserta' ? kelasNumber : null,
      created_at: new Date()
    });

    if (role === 'peserta') {
      db.getOrCreateMahasiswa(nim ? nim.trim() : username.trim(), nama_lengkap.trim(), kelasNumber, newId);
    }
    db.save();
    return res.redirect('/admin/users?msg=' + encodeURIComponent(`Pengguna baru "${nama_lengkap.trim()}" berhasil ditambahkan!`));
  } else if (action === 'edit' && user_id && nama_lengkap) {
    const targetId = parseInt(user_id, 10);
    const user = db.users.find(u => u.id === targetId);
    if (user) {
      const newUsername = (username || '').trim();
      if (newUsername && newUsername.toLowerCase() !== user.username.toLowerCase()) {
        const usernameExists = db.users.some(u => u.id !== targetId && u.username.toLowerCase() === newUsername.toLowerCase());
        if (usernameExists) {
          return res.redirect('/admin/users?err=' + encodeURIComponent(`Username "${newUsername}" sudah digunakan oleh akun lain!`));
        }
        user.username = newUsername;
        if (req.session.admin_id === user.id) {
          req.session.admin_username = newUsername;
        }
      }

      user.nama_lengkap = nama_lengkap.trim();
      user.role = role || user.role;
      user.nim = nim ? nim.trim() : user.nim;
      if (id_kelas) {
        user.id_kelas = parseInt(id_kelas, 10);
      }
      if (user.role === 'peserta') {
        const studentNim = user.nim || user.username;
        db.getOrCreateMahasiswa(studentNim, user.nama_lengkap, user.id_kelas || 1, user.id);
      }
      db.save();
      return res.redirect('/admin/users?msg=' + encodeURIComponent(`Data pengguna "${user.username}" berhasil diperbarui!`));
    }
  } else if (action === 'delete' && user_id) {
    const targetId = parseInt(user_id, 10);
    const user = db.users.find(u => u.id === targetId);
    if (user && (user.username === 'admin' || user.username === 'samsul')) {
      return res.redirect('/admin/users?err=' + encodeURIComponent('Akun Administrator Utama tidak boleh dihapus demi keamanan sistem.'));
    }
    db.users = db.users.filter(u => u.id !== targetId);
    db.mahasiswa = db.mahasiswa.filter(m => m.id_user !== targetId);
    db.save();
    return res.redirect('/admin/users?msg=' + encodeURIComponent('Pengguna berhasil dihapus dari sistem!'));
  } else if (action === 'reset_sesi' && sesi_id) {
    const sId = parseInt(sesi_id, 10);
    db.sesi_ujian = db.sesi_ujian.filter(s => s.id !== sId);
    db.riwayat_sesi_ujian = db.riwayat_sesi_ujian.filter(s => s.id !== sId);
    db.jawaban_peserta = db.jawaban_peserta.filter(j => j.id_sesi !== sId);
    db.save();
    return res.redirect('/admin/users?tab=status&msg=' + encodeURIComponent('Sesi ujian mahasiswa berhasil direset! Mahasiswa dapat memulai kembali ujian.'));
  } else if (action === 'paksa_keluar' && user_id) {
    const targetUserId = parseInt(user_id, 10);
    const targetUser = db.users.find(u => u.id === targetUserId);
    if (targetUser) {
      targetUser.is_logged_in = false;
      for (const [sid, sessionData] of activeSessions.entries()) {
        if (sessionData.peserta_id === targetUserId) {
          activeSessions.delete(sid);
        }
      }
      db.save();
    }
    return res.redirect('/admin/users?tab=status&msg=' + encodeURIComponent('Peserta berhasil dipaksa keluar dari sesi login!'));
  } else if (action === 'akhiri_sesi' && sesi_id) {
    const sId = parseInt(sesi_id, 10);
    const sesi = db.sesi_ujian.find(s => s.id === sId);
    if (sesi) {
      const answers = sesi.jawaban_draft || {};
      const shuffledOptions = sesi.shuffled_options || {};
      const soalList = db.getSoalByUjian(sesi.id_ujian);
      const scores = db.calculateScore(sesi.id, answers, shuffledOptions, soalList);

      sesi.status = 'selesai';
      sesi.waktu_selesai = new Date();
      sesi.nilai_total = scores.total;

      const inRiwayat = db.riwayat_sesi_ujian.find(r => r.id === sId);
      if (!inRiwayat) {
        db.riwayat_sesi_ujian.push({ ...sesi });
      } else {
        Object.assign(inRiwayat, sesi);
      }
      db.save();
    }
    return res.redirect('/admin/users?tab=status&msg=' + encodeURIComponent('Sesi ujian peserta berhasil diakhiri secara paksa!'));
  } else if (action === 'tandai_aktif' && sesi_id) {
    const sId = parseInt(sesi_id, 10);
    const sesi = db.sesi_ujian.find(s => s.id === sId) || db.riwayat_sesi_ujian.find(r => r.id === sId);
    if (sesi) {
      sesi.status = 'berlangsung';
      sesi.waktu_selesai = null;
      if (!db.sesi_ujian.some(s => s.id === sId)) {
        db.sesi_ujian.push(sesi);
      }
      db.save();
    }
    return res.redirect('/admin/users?tab=status&msg=' + encodeURIComponent('Sesi ujian peserta berhasil ditandai aktif!'));
  }

  db.save();
  res.redirect('/admin/users?msg=' + encodeURIComponent('Data pengguna berhasil diperbarui!'));
});

// JSON API endpoint for fast actions (reset password, delete, reset sesi, paksa keluar, akhiri sesi, tandai aktif)
app.post(['/admin/api/users/action'], requireAdminOnly, (req, res) => {
  const { action, user_id, sesi_id } = req.body;

  if (action === 'reset_password') {
    const targetUserId = parseInt(user_id, 10);
    const targetUser = db.users.find(u => u.id === targetUserId);
    if (!targetUser) {
      return res.status(404).json({ success: false, message: 'Pengguna tidak ditemukan' });
    }
    db.resetUserPassword(targetUserId, '12345*');
    db.save();
    return res.json({
      success: true,
      message: `Password akun "${targetUser.username}" berhasil direset ke password standar: 12345*`
    });
  }

  if (action === 'delete') {
    const targetUserId = parseInt(user_id, 10);
    const targetUser = db.users.find(u => u.id === targetUserId);
    if (!targetUser) {
      return res.status(404).json({ success: false, message: 'Pengguna tidak ditemukan' });
    }
    if (targetUser.username === 'admin' || targetUser.username === 'samsul') {
      return res.status(403).json({ success: false, message: 'Akun Administrator Utama tidak boleh dihapus demi keamanan.' });
    }
    db.users = db.users.filter(u => u.id !== targetUserId);
    db.mahasiswa = db.mahasiswa.filter(m => m.id_user !== targetUserId);
    db.save();
    return res.json({
      success: true,
      message: `Pengguna "${targetUser.username}" berhasil dihapus!`
    });
  }

  if (action === 'reset_sesi') {
    const sId = parseInt(sesi_id, 10);
    db.sesi_ujian = db.sesi_ujian.filter(s => s.id !== sId);
    db.riwayat_sesi_ujian = db.riwayat_sesi_ujian.filter(s => s.id !== sId);
    db.jawaban_peserta = db.jawaban_peserta.filter(j => j.id_sesi !== sId);
    db.save();
    return res.json({
      success: true,
      message: 'Sesi ujian mahasiswa berhasil direset! Mahasiswa dapat memulai kembali ujian.'
    });
  }

  if (action === 'paksa_keluar') {
    const targetUserId = parseInt(user_id, 10);
    const targetUser = db.users.find(u => u.id === targetUserId);
    if (!targetUser) {
      return res.status(404).json({ success: false, message: 'Pengguna tidak ditemukan' });
    }
    targetUser.is_logged_in = false;
    for (const [sid, sessionData] of activeSessions.entries()) {
      if (sessionData.peserta_id === targetUserId) {
        activeSessions.delete(sid);
      }
    }
    db.save();
    return res.json({
      success: true,
      message: `Peserta "${targetUser.nama_lengkap || targetUser.username}" berhasil dipaksa keluar dari sesi login!`
    });
  }

  if (action === 'akhiri_sesi') {
    const sId = parseInt(sesi_id, 10);
    const sesi = db.sesi_ujian.find(s => s.id === sId) || db.riwayat_sesi_ujian.find(r => r.id === sId);
    if (!sesi) {
      return res.status(404).json({ success: false, message: 'Sesi ujian tidak ditemukan' });
    }
    const answers = sesi.jawaban_draft || {};
    const shuffledOptions = sesi.shuffled_options || {};
    const soalList = db.getSoalByUjian(sesi.id_ujian);
    const scores = db.calculateScore(sesi.id, answers, shuffledOptions, soalList);

    sesi.status = 'selesai';
    sesi.waktu_selesai = new Date();
    sesi.nilai_total = scores.total;

    const inRiwayat = db.riwayat_sesi_ujian.find(r => r.id === sId);
    if (!inRiwayat) {
      db.riwayat_sesi_ujian.push({ ...sesi });
    } else {
      Object.assign(inRiwayat, sesi);
    }
    db.save();

    return res.json({
      success: true,
      message: `Sesi ujian berhasil diakhiri secara paksa! Nilai akhir: ${scores.total}`
    });
  }

  if (action === 'tandai_aktif') {
    const sId = parseInt(sesi_id, 10);
    const sesi = db.sesi_ujian.find(s => s.id === sId) || db.riwayat_sesi_ujian.find(r => r.id === sId);
    if (!sesi) {
      return res.status(404).json({ success: false, message: 'Sesi ujian tidak ditemukan' });
    }
    sesi.status = 'berlangsung';
    sesi.waktu_selesai = null;
    if (!db.sesi_ujian.some(s => s.id === sId)) {
      db.sesi_ujian.push(sesi);
    }
    db.save();

    return res.json({
      success: true,
      message: 'Sesi ujian berhasil ditandai sebagai aktif / sedang berlangsung!'
    });
  }

  return res.status(400).json({ success: false, message: 'Aksi tidak valid' });
});

// Download XLSX Template for Import
app.get(['/admin/users/template_xlsx'], requireAdminOnly, (req, res) => {
  const xlsxLib = XLSX.default || XLSX;
  const wb = xlsxLib.utils.book_new();

  // 1. Sheet: Template_Pengguna
  const sampleClasses = db.kelas.map(k => k.nama_kelas);
  const sampleData = [
    {
      'Username': '240305013',
      'Password': '',
      'Nama Lengkap': 'MOH ANWAR KHALID',
      'Role': 'peserta',
      'NIM': '240305013',
      'Kelas': sampleClasses[0] || 'V (A,B)'
    },
    {
      'Username': '240305018',
      'Password': '',
      'Nama Lengkap': 'Riyan Ferdianto',
      'Role': 'peserta',
      'NIM': '240305018',
      'Kelas': sampleClasses[0] || 'V (A,B)'
    },
    {
      'Username': '230102400',
      'Password': '',
      'Nama Lengkap': 'Ziadatul Ilmi',
      'Role': 'peserta',
      'NIM': '230102400',
      'Kelas': sampleClasses[1] || 'TI-A'
    },
    {
      'Username': '230102401',
      'Password': '',
      'Nama Lengkap': 'Ahmad Fauzi',
      'Role': 'peserta',
      'NIM': '230102401',
      'Kelas': sampleClasses[2] || 'TI-B'
    },
    {
      'Username': 'dosen_informatika',
      'Password': '',
      'Nama Lengkap': 'Dr. H. Sudirman, M.Pd.',
      'Role': 'dosen',
      'NIM': '',
      'Kelas': ''
    },
    {
      'Username': 'pengawas_lab',
      'Password': '',
      'Nama Lengkap': 'Dewi Lestari, S.Kom.',
      'Role': 'pengawas',
      'NIM': '',
      'Kelas': ''
    }
  ];

  const ws = xlsxLib.utils.json_to_sheet(sampleData);
  ws['!cols'] = [
    { wch: 18 },
    { wch: 14 },
    { wch: 32 },
    { wch: 14 },
    { wch: 18 },
    { wch: 22 }
  ];
  xlsxLib.utils.book_append_sheet(wb, ws, 'Template_Pengguna');

  // 2. Sheet: Daftar_Kelas_Referensi (Reference Sheet)
  const kelasRefData = db.kelas.map(k => {
    const prodi = db.program_studi.find(p => p.id === k.id_program_studi);
    return {
      'ID Kelas': k.id,
      'Nama Kelas (Isikan ke Kolom Kelas)': k.nama_kelas,
      'Program Studi': prodi ? prodi.nama_prodi : '-',
      'Angkatan': k.angkatan || '-',
      'Semester': k.semester || 1
    };
  });
  const wsKelas = xlsxLib.utils.json_to_sheet(kelasRefData);
  wsKelas['!cols'] = [
    { wch: 10 },
    { wch: 36 },
    { wch: 32 },
    { wch: 12 },
    { wch: 12 }
  ];
  xlsxLib.utils.book_append_sheet(wb, wsKelas, 'Daftar_Kelas_Referensi');

  // 3. Sheet: Panduan_Pengisian
  const panduanData = [
    { 'Kolom': 'Username', 'Wajib': 'Ya', 'Keterangan': 'Username akun untuk login (untuk mahasiswa gunakan NIM)' },
    { 'Kolom': 'Password', 'Wajib': 'Tidak', 'Keterangan': 'Bila dikosongkan, password otomatis disetel default: 12345*' },
    { 'Kolom': 'Nama Lengkap', 'Wajib': 'Ya', 'Keterangan': 'Nama lengkap pengguna beserta gelar' },
    { 'Kolom': 'Role', 'Wajib': 'Ya', 'Keterangan': 'Pilihan peran: peserta, dosen, pengawas, atau admin' },
    { 'Kolom': 'NIM', 'Wajib': 'Khusus Peserta', 'Keterangan': 'Nomor Induk Mahasiswa peserta ujian' },
    { 'Kolom': 'Kelas', 'Wajib': 'Khusus Peserta', 'Keterangan': 'Nama kelas atau ID kelas (lihat sheet Daftar_Kelas_Referensi). Kelas ini otomatis menghubungkan mahasiswa ke ujian dan filter sesi!' }
  ];
  const wsPanduan = xlsxLib.utils.json_to_sheet(panduanData);
  wsPanduan['!cols'] = [
    { wch: 18 },
    { wch: 16 },
    { wch: 65 }
  ];
  xlsxLib.utils.book_append_sheet(wb, wsPanduan, 'Panduan_Pengisian');

  const buffer = xlsxLib.write(wb, { type: 'buffer', bookType: 'xlsx' });

  res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  res.setHeader('Content-Disposition', 'attachment; filename="template_import_pengguna_dan_kelas.xlsx"');
  return res.send(buffer);
});

// Download CSV Template for Import (Includes Kelas)
app.get(['/admin/users/template_csv'], requireAdminOnly, (req, res) => {
  const sampleClasses = db.kelas.map(k => k.nama_kelas);
  const rows = [
    ['Username', 'Password', 'Nama Lengkap', 'Role', 'NIM', 'Kelas'],
    ['240305013', '', 'MOH ANWAR KHALID', 'peserta', '240305013', sampleClasses[0] || 'V (A,B)'],
    ['240305018', '', 'Riyan Ferdianto', 'peserta', '240305018', sampleClasses[0] || 'V (A,B)'],
    ['230102400', '', 'Ziadatul Ilmi', 'peserta', '230102400', sampleClasses[1] || 'TI-A'],
    ['230102401', '', 'Ahmad Fauzi', 'peserta', '230102401', sampleClasses[2] || 'TI-B'],
    ['dosen_informatika', '', 'Dr. H. Sudirman, M.Pd.', 'dosen', '', ''],
    ['pengawas_lab', '', 'Dewi Lestari, S.Kom.', 'pengawas', '', '']
  ];
  const csvContent = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\r\n');
  res.setHeader('Content-Type', 'text/csv');
  res.setHeader('Content-Disposition', 'attachment; filename="template_import_pengguna_dan_kelas.csv"');
  return res.send(csvContent);
});

// Export Current Users & Participants with Kelas to XLSX
app.get(['/admin/users/export_xlsx'], requireAdminOnly, (req, res) => {
  const xlsxLib = XLSX.default || XLSX;
  const wb = xlsxLib.utils.book_new();

  const exportData = db.users.map((u, idx) => {
    let nama_kelas = '';
    const mhs = db.mahasiswa.find(m => m.id_user === u.id || (u.nim && m.nim === u.nim));
    const kId = u.id_kelas || (mhs ? mhs.id_kelas : null);
    if (kId) {
      const k = db.kelas.find(kls => kls.id === kId);
      nama_kelas = k ? k.nama_kelas : '';
    }
    return {
      'No': idx + 1,
      'Username': u.username,
      'Nama Lengkap': u.nama_lengkap,
      'Role': u.role,
      'NIM': u.nim || (mhs ? mhs.nim : ''),
      'Kelas': nama_kelas || (u.role === 'peserta' ? '-' : ''),
      'Tanggal Dibuat': u.created_at ? new Date(u.created_at).toLocaleDateString('id-ID') : '-'
    };
  });

  const ws = xlsxLib.utils.json_to_sheet(exportData);
  ws['!cols'] = [
    { wch: 6 },
    { wch: 18 },
    { wch: 32 },
    { wch: 14 },
    { wch: 18 },
    { wch: 22 },
    { wch: 16 }
  ];
  xlsxLib.utils.book_append_sheet(wb, ws, 'Daftar_Pengguna');

  const buffer = xlsxLib.write(wb, { type: 'buffer', bookType: 'xlsx' });
  res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  res.setHeader('Content-Disposition', 'attachment; filename="data_pengguna_dan_kelas.xlsx"');
  return res.send(buffer);
});

// Import Users from XLSX
app.post(['/admin/users/import_xlsx'], requireAdminOnly, upload.single('file_xlsx'), (req, res) => {
  if (!req.file || !req.file.buffer) {
    return res.redirect('/admin/users?err=' + encodeURIComponent('Silakan pilih berkas Excel (.xlsx / .csv) terlebih dahulu!'));
  }

  try {
    const xlsxLib = XLSX.default || XLSX;
    const workbook = xlsxLib.read(req.file.buffer, { type: 'buffer' });
    const sheetName = workbook.SheetNames[0];
    const worksheet = workbook.Sheets[sheetName];
    const rows = xlsxLib.utils.sheet_to_json(worksheet);

    if (!rows || rows.length === 0) {
      return res.redirect('/admin/users?err=' + encodeURIComponent('Berkas Excel kosong atau format tidak sesuai!'));
    }

    let addedCount = 0;
    let updatedCount = 0;

    rows.forEach(row => {
      const username = String(row['Username'] || row['username'] || row['USERNAME'] || '').trim();
      if (!username) return;

      const passwordRaw = String(row['Password'] || row['password'] || row['PASSWORD'] || '').trim() || '12345*';
      const nama = String(row['Nama Lengkap'] || row['Nama'] || row['nama_lengkap'] || row['NAMA'] || username).trim();
      let role = String(row['Role'] || row['role'] || row['ROLE'] || 'peserta').trim().toLowerCase();
      if (!['admin', 'dosen', 'pengawas', 'peserta'].includes(role)) {
        role = 'peserta';
      }
      const nim = String(row['NIM'] || row['nim'] || (role === 'peserta' ? username : '')).trim();
      const kelasRaw = String(row['Kelas'] || row['kelas'] || row['KELAS'] || row['Nama Kelas'] || row['nama_kelas'] || row['Nama Kelas (Isikan ke Kolom Kelas)'] || '').trim();

      // Find matching class
      let targetKelas = null;
      if (kelasRaw) {
        targetKelas = db.kelas.find(k => 
          k.nama_kelas.toLowerCase() === kelasRaw.toLowerCase() || 
          String(k.id) === kelasRaw
        );

        // If class is specified but doesn't exist yet in db.kelas, auto-create it so student's class is preserved!
        if (!targetKelas && role === 'peserta') {
          const newKelasId = db.kelas.length ? Math.max(...db.kelas.map(k => k.id)) + 1 : 1;
          targetKelas = {
            id: newKelasId,
            nama_kelas: kelasRaw,
            angkatan: new Date().getFullYear(),
            semester: 1,
            id_program_studi: db.program_studi[0] ? db.program_studi[0].id : 1
          };
          db.kelas.push(targetKelas);
        }
      }

      const idKelas = targetKelas ? targetKelas.id : (role === 'peserta' ? (db.kelas[0] ? db.kelas[0].id : 1) : null);

      const existingUser = db.users.find(u => u.username.toLowerCase() === username.toLowerCase());
      if (existingUser) {
        existingUser.nama_lengkap = nama;
        existingUser.role = role;
        existingUser.nim = nim || null;
        if (idKelas) existingUser.id_kelas = idKelas;
        if (passwordRaw !== '12345*') {
          existingUser.password = bcrypt.hashSync(passwordRaw, 10);
        }
        if (role === 'peserta') {
          const m = db.mahasiswa.find(mhs => mhs.id_user === existingUser.id || (existingUser.nim && mhs.nim === existingUser.nim));
          if (m) {
            m.nama_lengkap = nama;
            if (idKelas) m.id_kelas = idKelas;
            if (nim) m.nim = nim;
          } else {
            db.getOrCreateMahasiswa(nim || username, nama, idKelas || 1, existingUser.id);
          }
        }
        updatedCount++;
      } else {
        const newId = db.users.length ? Math.max(...db.users.map(u => u.id)) + 1 : 1;
        db.users.push({
          id: newId,
          username: username,
          password: bcrypt.hashSync(passwordRaw, 10),
          nama_lengkap: nama,
          role: role,
          nim: nim || null,
          id_kelas: idKelas,
          created_at: new Date()
        });
        if (role === 'peserta') {
          db.getOrCreateMahasiswa(nim || username, nama, idKelas || 1, newId);
        }
        addedCount++;
      }
    });

    db.save();
    const msg = `Berhasil memproses import: ${addedCount} pengguna baru ditambahkan, ${updatedCount} pengguna diperbarui! Data kelas peserta berhasil disinkronkan.`;
    return res.redirect('/admin/users?msg=' + encodeURIComponent(msg));
  } catch (err) {
    console.error('Import XLSX error:', err);
    return res.redirect('/admin/users?err=' + encodeURIComponent('Gagal membaca berkas Excel: ' + err.message));
  }
});

// Admin Ganti Password Pribadi
app.get(['/admin/ganti_password', '/admin/ganti_password.php'], requireAdmin, (req, res) => {
  res.render('admin/ganti_password', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    message: req.query.msg || '',
    error: req.query.err || ''
  });
});

app.post(['/admin/ganti_password', '/admin/ganti_password.php'], requireAdmin, (req, res) => {
  const { old_password, new_password, confirm_password } = req.body;
  if (!old_password || !new_password || !confirm_password) {
    return res.redirect('/admin/ganti_password?err=' + encodeURIComponent('Semua bidang password wajib diisi!'));
  }
  if (new_password !== confirm_password) {
    return res.redirect('/admin/ganti_password?err=' + encodeURIComponent('Konfirmasi password baru tidak cocok!'));
  }
  const result = db.changePassword(req.session.admin_id, old_password, new_password);
  if (!result.success) {
    return res.redirect('/admin/ganti_password?err=' + encodeURIComponent(result.message));
  }
  res.redirect('/admin/ganti_password?msg=' + encodeURIComponent('Password Anda berhasil diubah! Silakan simpan password baru ini dengan aman.'));
});

// Lupa Password / Reset Password Mandiri
app.get(['/lupa_password', '/admin/lupa_password'], (req, res) => {
  res.render('lupa_password', {
    message: req.query.msg || '',
    error: req.query.err || ''
  });
});

app.post(['/lupa_password', '/admin/lupa_password'], (req, res) => {
  const identifier = (req.body.identifier || '').trim();
  if (!identifier) {
    return res.render('lupa_password', { error: 'Username atau identitas akun wajib diisi!', message: '' });
  }
  const user = db.findUser(identifier);
  if (!user) {
    return res.render('lupa_password', { error: 'Akun dengan username atau identitas tersebut tidak ditemukan!', message: '' });
  }
  db.resetUserPassword(user.id, '12345*');
  res.render('lupa_password', {
    message: `Password untuk akun "${user.nama_lengkap}" (${user.username}) berhasil direset ke password standar: 12345*. Anda dapat menggunakannya untuk login sekarang.`,
    error: ''
  });
});

// Admin Settings
app.get(['/admin/pengaturan', '/admin/pengaturan.php'], requireAdmin, (req, res) => {
  res.render('admin/pengaturan', {
    adminNama: req.session.admin_nama,
    adminRole: req.session.admin_role,
    settings: db.pengaturan,
    message: req.query.msg || ''
  });
});

app.post(['/admin/pengaturan', '/admin/pengaturan.php'], requireAdmin, (req, res) => {
  db.pengaturan = {
    ...db.pengaturan,
    nama_institusi: req.body.nama_institusi || db.pengaturan.nama_institusi,
    fakultas_institusi: req.body.fakultas_institusi || db.pengaturan.fakultas_institusi,
    alamat_institusi: req.body.alamat_institusi || db.pengaturan.alamat_institusi,
    telepon_institusi: req.body.telepon_institusi || db.pengaturan.telepon_institusi,
    website_institusi: req.body.website_institusi || db.pengaturan.website_institusi
  };
  db.save();
  res.redirect('/admin/pengaturan?msg=' + encodeURIComponent('Pengaturan institusi berhasil disimpan!'));
});

// AI Question Generator API (Multi-Type Question & Custom Bloom Levels, No Jenjang)
app.post(['/admin/ai_generate_api', '/admin/ai_generate_api.php'], requireAdmin, async (req, res) => {
  const ujianId = req.body.ujian_id ? parseInt(req.body.ujian_id, 10) : null;
  const topic = req.body.topik || req.body.topic || 'Etika Profesi';
  const subTopic = req.body.sub_topik || '';
  const count = parseInt(req.body.jumlah || req.body.count, 10) || 3;
  const kesulitan = req.body.kesulitan || 'sedang';
  const poin = parseInt(req.body.poin, 10) || 4;
  const apply = req.body.apply !== false;

  // Parse multi-select question types and cognitive levels
  let selectedTypes = ['pg'];
  if (Array.isArray(req.body.jenis_soal_list) && req.body.jenis_soal_list.length > 0) {
    selectedTypes = req.body.jenis_soal_list;
  } else if (typeof req.body.jenis_soal_list === 'string' && req.body.jenis_soal_list.trim()) {
    selectedTypes = req.body.jenis_soal_list.split(',').map(s => s.trim()).filter(Boolean);
  } else if (req.body.jenis) {
    selectedTypes = [req.body.jenis];
  }

  let selectedCognitives = ['C2', 'C3', 'C4'];
  if (Array.isArray(req.body.kognitif_list) && req.body.kognitif_list.length > 0) {
    selectedCognitives = req.body.kognitif_list;
  } else if (typeof req.body.kognitif_list === 'string' && req.body.kognitif_list.trim()) {
    selectedCognitives = req.body.kognitif_list.split(',').map(s => s.trim()).filter(Boolean);
  } else if (req.body.kognitif) {
    selectedCognitives = [req.body.kognitif];
  }

  const typesReadable = selectedTypes.map(t => getJenisSoalLabel(t)).join(', ');
  const cognitivesReadable = selectedCognitives.join(', ');

  const prompt = `Anda adalah dosen pakar penyusun instrumen ujian akademik perguruan tinggi di Indonesia.
Buatkan ${count} butir naskah soal ujian akademik berkualitas tinggi dan SIAP DIUJIKAN LANGSUNG KEPADA PESERTA tentang mata kuliah / topik "${topic}".
Fokus Ruang Lingkup Materi: "${subTopic || topic}".
Tingkat Kesulitan: "${kesulitan}".
PILIHAN BENTUK/JENIS SOAL: [${typesReadable}].
PENTING: Anda WAJIB menyusun ${count} butir soal dengan mendistribusikan bentuk soal di antara jenis-jenis yang dipilih di atas secara seimbang.
LEVEL KOGNITIF BLOOM YANG DIPILIH: [${cognitivesReadable}].
PENTING: Setiap butir soal WAJIB menggunakan salah satu level kognitif dari daftar yang ditentukan di atas!

PERSYARATAN FORMAT SESUAI BENTUK SOAL:
1. "pg" (Pilihan Ganda): teks pertanyaan tanpa huruf pilihan + 5 opsi (A-E) dengan TEPAT SATU kunci benar.
2. "pg_kompleks" (Pilihan Ganda Kompleks): teks pertanyaan/pernyataan kasus + 4-5 pilihan di mana 2 atau lebih bernilai benar.
3. "tf" (Benar / Salah): 2 opsi: [{"teks": "Benar", "benar": true/false}, {"teks": "Salah", "benar": false/true}].
4. "menjodohkan" (Menjodohkan): premis dan pasangan kunci respon yang selaras.
5. "short" (Isian Singkat): pertanyaan langsung dengan frasa kunci jawaban terukur pada opsi atau pembahasan.
6. "esai" (Uraian / Esai): permasalahan mendalam lengkap dengan kriteria rubrik penskoran pada kolom pembahasan.

Format respon HANYA berupa JSON object:
{
  "soal": [
    {
      "pertanyaan": "teks narasi stimulus kasus dan pertanyaan inti tanpa opsi di dalamnya",
      "jenis_soal": "pg | pg_kompleks | tf | menjodohkan | short | esai",
      "poin": ${poin},
      "tingkat_kesulitan": "${kesulitan}",
      "level_kognitif": "${selectedCognitives[0] || 'C3'}",
      "cpmk": "capaian pembelajaran mata kuliah spesifik",
      "materi_pokok": "${subTopic ? topic + ' - ' + subTopic : topic}",
      "indikator": "indikator ketercapaian kompetensi butir soal",
      "opsi": [
        {"teks": "rumusan jawaban A", "benar": true},
        {"teks": "rumusan jawaban B", "benar": false}
      ],
      "pembahasan": "penjelasan ilmiah mendalam / rubrik penilaian"
    }
  ]
}`;

  const parsed = await generateWithAI({
    prompt,
    systemInstruction: 'Anda adalah dosen ahli penyusun butir soal ujian akademik perguruan tinggi. Balas HANYA JSON valid.'
  });

  let generated = [];
  if (parsed && (parsed.soal || Array.isArray(parsed))) {
    generated = parsed.soal || parsed;
  }

  // Fallback high-quality academic question generator
  if (!Array.isArray(generated) || generated.length === 0) {
    generated = generateAcademicQuestionsFallback({
      courseName: topic,
      topic,
      subTopic,
      count,
      poin,
      difficulty: kesulitan,
      cognitive: selectedCognitives[0] || 'C3',
      jenisList: selectedTypes,
      cognitiveList: selectedCognitives
    });
  }

  // Sanitize
  generated = sanitizeQuestions(generated, topic, poin);

  // If ujian_id provided and apply is true, save directly to database
  if (ujianId && apply) {
    const targetUjian = db.ujian.find(u => u.id === ujianId) || db.ujian[0];
    if (targetUjian) {
      let nextSoalId = db.soal.length ? Math.max(...db.soal.map(s => s.id)) + 1 : 1;
      let nextOpsiId = db.opsi_jawaban.length ? Math.max(...db.opsi_jawaban.map(o => o.id)) + 1 : 1;
      const currentCount = db.soal.filter(s => s.id_ujian === targetUjian.id).length;

      generated.forEach((q, idx) => {
        const soalId = nextSoalId++;
        db.soal.push({
          id: soalId,
          id_ujian: targetUjian.id,
          jenis_soal: q.jenis_soal || selectedTypes[0] || 'pg',
          pertanyaan: q.pertanyaan,
          pembahasan: q.pembahasan || 'Pembahasan kunci jawaban.',
          poin: q.poin || poin,
          urutan: currentCount + idx + 1,
          data_tambahan: null,
          tingkat_kesulitan: q.tingkat_kesulitan || kesulitan,
          level_kognitif: q.level_kognitif || selectedCognitives[0] || 'C3',
          cpmk: q.cpmk || 'Capaian Pembelajaran MK',
          materi_pokok: q.materi_pokok || targetUjian.judul_ujian,
          indikator: q.indikator || 'Indikator ketercapaian soal'
        });

        if (Array.isArray(q.opsi) && q.opsi.length > 0) {
          q.opsi.forEach((o, oIdx) => {
            db.opsi_jawaban.push({
              id: nextOpsiId++,
              id_soal: soalId,
              teks_opsi: o.teks || o.teks_opsi,
              benar: Boolean(o.benar),
              urutan: oIdx + 1
            });
          });
        }
      });

      db.save(); // Permanently save to disk
      return res.json({ success: true, count: generated.length, data: generated, saved: true });
    }
  }

  // Return generated array for client handling
  res.json({ success: true, count: generated.length, data: generated, saved: false });
});

/* ==========================================================================
   DYNAMIC API ENDPOINTS (api.php)
   ========================================================================== */

app.get(['/api.php', '/api'], (req, res) => {
  const action = req.query.action || '';

  if (action === 'get_kelas') {
    const prodiId = parseInt(req.query.prodi_id, 10);
    const kelasList = db.getKelasByProdi(prodiId);
    return res.json({ success: true, data: kelasList });
  }

  if (action === 'get_ujian') {
    const kelasId = parseInt(req.query.kelas_id, 10);
    const exams = db.getUjianAktifByKelas(kelasId);
    return res.json({ success: true, data: exams });
  }

  if (action === 'get_matkul_kelas') {
    const matkulId = parseInt(req.query.matkul_id, 10);
    const classIds = db.mata_kuliah_kelas
      .filter(mk => mk.id_mata_kuliah === matkulId)
      .map(mk => mk.id_kelas);
    return res.json({ success: true, data: classIds });
  }

  res.json({ success: false, message: 'Aksi API tidak valid' });
});

app.post(['/api.php', '/api'], (req, res) => {
  const action = req.query.action || req.body.action || '';
  if (action === 'save_answer') {
    const soalId = parseInt(req.body.soal_id, 10);
    const jawaban = req.body.jawaban;
    if (req.session.sesi_id) {
      if (!req.session.answers) req.session.answers = {};
      req.session.answers[soalId] = jawaban;
      const sesi = db.sesi_ujian.find(s => s.id === req.session.sesi_id);
      if (sesi) {
        if (!sesi.jawaban_draft) sesi.jawaban_draft = {};
        sesi.jawaban_draft[soalId] = jawaban;
        db.save();
      }
      return res.json({ success: true });
    }
    return res.json({ success: false, message: 'Sesi tidak valid' });
  }
  res.json({ success: false, message: 'Aksi API tidak valid' });
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`SIPENA application server listening on http://0.0.0.0:${PORT}`);
});
