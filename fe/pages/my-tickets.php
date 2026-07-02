<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$active_page = 'tickets';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vé của tôi - MovieFlex</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#DF1730;--sb:#0F172A;--sbw:240px;--bg:#F1F5F9;--card:#fff;--text:#0F172A;--muted:#64748B;--border:#E2E8F0;--r:14px;--sh:0 2px 16px rgba(15,23,42,.07)}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}

.main{margin-left:var(--sbw);flex:1}
.topbar{background:var(--card);border-bottom:1px solid var(--border);height:60px;display:flex;align-items:center;padding:0 26px;gap:14px;position:sticky;top:0;z-index:50}
.topbar h1{font-size:18px;font-weight:800}
.content{padding:24px 28px}

.tabs{display:flex;gap:4px;background:var(--card);border-radius:12px;padding:4px;box-shadow:var(--sh);width:fit-content;margin-bottom:24px}
.tab{padding:8px 22px;border-radius:9px;font-size:13.5px;font-weight:600;cursor:pointer;color:var(--muted);transition:all .2s;border:none;background:transparent;font-family:inherit}
.tab.active{background:var(--blue);color:#fff}

.ticket-list{display:flex;flex-direction:column;gap:16px}
.bk-card{background:var(--card);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;transition:box-shadow .2s}
.bk-card:hover{box-shadow:0 4px 28px rgba(15,23,42,.12)}
.bk-top{display:flex;gap:16px;padding:18px 20px;border-bottom:1px dashed var(--border)}
.bk-poster{width:64px;height:90px;border-radius:8px;object-fit:cover;flex-shrink:0;background:#e2e8f0}
.bk-poster-ph{width:64px;height:90px;border-radius:8px;flex-shrink:0;background:#1e293b;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.2);font-size:22px}
.bk-info{flex:1}
.bk-title{font-size:15px;font-weight:700;margin-bottom:6px}
.bk-meta{font-size:13px;color:var(--muted);line-height:1.7}
.bk-status{flex-shrink:0;display:flex;flex-direction:column;align-items:flex-end;gap:8px}
.status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700}
.bk-code{font-size:13px;font-weight:700;color:var(--muted)}
.bk-bot{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:var(--bg)}
.seats-row{display:flex;flex-wrap:wrap;gap:5px}
.seat-chip{background:#FAF0F1;color:var(--blue);font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:5px;border:1px solid #FCA5A5}
.bk-actions{display:flex;gap:8px}
.btn-sm{height:34px;padding:0 14px;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer;border:none;font-family:inherit;display:flex;align-items:center;gap:5px;text-decoration:none;transition:all .2s}
.btn-sm-blue{background:var(--blue);color:#fff}
.btn-sm-blue:hover{background:#C21025}
.btn-sm-red{background:#FEF2F2;color:#EF4444;border:1px solid #FECACA}
.btn-sm-red:hover{background:#FEE2E2}
.btn-sm-gray{background:var(--card);color:var(--muted);border:1px solid var(--border)}
.bk-price{font-size:16px;font-weight:800;color:var(--blue)}

.empty{text-align:center;padding:60px 20px;color:var(--muted)}
.empty i{font-size:48px;opacity:.2;display:block;margin-bottom:16px}
.empty h3{font-size:18px;font-weight:700;margin-bottom:8px}

.overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.8);z-index:200;align-items:center;justify-content:center}
.overlay.show{display:flex}
.modal{background:var(--card);border-radius:18px;padding:28px;max-width:440px;width:100%;box-shadow:0 16px 48px rgba(0,0,0,.4);text-align:center}
.modal-btns{display:flex;gap:10px}
.mbtn{flex:1;height:42px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:all .2s}
.mbtn:disabled{opacity:.6;cursor:not-allowed}
.mbtn-cancel{background:var(--bg);color:var(--text)}
.mbtn-confirm{background:#EF4444;color:#fff}

.alert{display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:12px;font-size:14px;font-weight:600;margin-bottom:20px}
.alert-success{background:#F0FDF4;color:#16A34A;border:1px solid #BBF7D0}
.alert-error{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA}

.skeleton{background:linear-gradient(90deg,#e2e8f0 25%,#f1f5f9 50%,#e2e8f0 75%);background-size:200% 100%;animation:shimmer 1.2s infinite}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
</style>
</head>
<body>
<?php include __DIR__ . '/../components/sidebar.php'; ?>

<div class="main">
  <div class="topbar"><h1><i class="fa-solid fa-receipt" style="color:var(--blue);margin-right:8px"></i>Vé của tôi</h1></div>
  <div class="content">
    <div id="alert-box"></div>

    <div class="tabs">
      <button class="tab active" id="tab-upcoming" onclick="switchTab('upcoming')">Sắp chiếu (<span id="cnt-upcoming">…</span>)</button>
      <button class="tab" id="tab-past" onclick="switchTab('past')">Đã chiếu (<span id="cnt-past">…</span>)</button>
      <button class="tab" id="tab-cancelled" onclick="switchTab('cancelled')">Đã hủy (<span id="cnt-cancelled">…</span>)</button>
    </div>

    <div id="ticket-container">
      <!-- skeleton -->
      ${[1,2].map(() => '').join('')}
      <div style="display:flex;flex-direction:column;gap:16px">
        <?php for($i=0;$i<3;$i++): ?>
        <div class="bk-card">
          <div class="bk-top">
            <div class="skeleton" style="width:64px;height:90px;border-radius:8px;flex-shrink:0"></div>
            <div style="flex:1;display:flex;flex-direction:column;gap:8px;padding-top:4px">
              <div class="skeleton" style="height:16px;border-radius:4px;width:60%"></div>
              <div class="skeleton" style="height:13px;border-radius:4px;width:80%"></div>
              <div class="skeleton" style="height:13px;border-radius:4px;width:50%"></div>
            </div>
          </div>
        </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</div>

<!-- Cancel Modal -->
<div class="overlay" id="cancel-overlay">
  <div class="modal">
    <div style="width:56px;height:56px;border-radius:50%;background:#FEF2F2;color:#EF4444;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 16px">
      <i class="fa-solid fa-triangle-exclamation"></i>
    </div>
    <h3 style="font-size:18px;font-weight:800;margin-bottom:6px">Xác nhận hủy vé & Hoàn tiền</h3>
    <p style="font-size:13.5px;color:#64748B;line-height:1.5;margin-bottom:20px">Bạn đang yêu cầu hủy vé xem phim. Vui lòng kiểm tra thông tin hoàn trả bên dưới.</p>
    <div style="background:#F8FAFC;border-radius:12px;padding:16px;text-align:left;font-size:13.5px;margin-bottom:24px;border:1.5px solid #E2E8F0;display:flex;flex-direction:column;gap:8px">
      <div style="display:flex;justify-content:space-between"><span style="color:#64748B">Mã đặt vé:</span><strong id="m-code"></strong></div>
      <div style="display:flex;justify-content:space-between"><span style="color:#64748B">Số tiền hoàn trả:</span><strong id="m-amount" style="color:#16A34A"></strong></div>
      <div style="display:flex;justify-content:space-between"><span style="color:#64748B">Phương thức hoàn:</span><strong id="m-method" style="color:#2563EB"></strong></div>
    </div>
    <div class="modal-btns">
      <button class="mbtn mbtn-cancel" onclick="closeCancel()">Không, giữ lại</button>
      <button class="mbtn mbtn-confirm" id="btn-confirm-cancel" onclick="doCancel()">Xác nhận hủy vé</button>
    </div>
  </div>
</div>

<!-- Review Modal -->
<div class="overlay" id="review-overlay">
  <div class="modal">
    <div style="width:56px;height:56px;border-radius:50%;background:#ECFDF5;color:#10B981;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 16px">
      <i class="fa-regular fa-face-smile"></i>
    </div>
    <h3 style="font-size:18px;font-weight:800;margin-bottom:4px">Đánh giá phim</h3>
    <p id="rev-movie-title" style="font-size:14px;font-weight:700;color:var(--blue);margin-bottom:20px"></p>
    <input type="hidden" id="rev-booking-code">
    <div style="margin-bottom:20px">
      <label style="display:block;font-size:12px;font-weight:700;color:#64748B;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">Điểm số của bạn:</label>
      <div style="display:flex;justify-content:center;gap:6px;font-size:22px" id="star-row"></div>
      <div id="rating-label" style="font-size:13px;font-weight:800;color:#F59E0B;margin-top:6px">10 / 10 - Xuất sắc</div>
    </div>
    <div style="margin-bottom:24px;text-align:left">
      <label style="display:block;font-size:12px;font-weight:700;color:#64748B;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Lời nhận xét:</label>
      <textarea id="rev-comment" rows="4" placeholder="Nhập cảm nghĩ của bạn về phim..."
        style="width:100%;border:1.5px solid #E2E8F0;border-radius:12px;padding:12px;font-family:inherit;font-size:13.5px;outline:none;resize:none"></textarea>
    </div>
    <div class="modal-btns">
      <button class="mbtn mbtn-cancel" onclick="closeReview()">Đóng</button>
      <button class="mbtn" id="btn-submit-review" onclick="submitReview()" style="background:#10B981;color:#fff">Gửi đánh giá</button>
    </div>
  </div>
</div>

<script>
const API = '/be/api.php';
const PAY_LABELS = {momo:'MoMo',vnpay:'VNPay',zalopay:'ZaloPay',cash:'Tiền mặt'};
const STATUS_COLOR = {confirmed:'#22C55E',cancelled:'#EF4444',checked_in:'#2563EB'};
const STATUS_LABEL = {confirmed:'Đã xác nhận',cancelled:'Đã hủy',checked_in:'Đã check-in'};
const STAR_LABELS = {1:'Tệ hại',2:'Rất tệ',3:'Kém',4:'Trung bình kém',5:'Tạm ổn',6:'Khá',7:'Tốt',8:'Rất tốt',9:'Tuyệt vời',10:'Xuất sắc'};

let allTickets = { upcoming: [], past: [], cancelled: [] };
let currentTab = 'upcoming';
let cancelCode = '';
let currentRating = 10;

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showAlert(type, message) {
  const box = document.getElementById('alert-box');
  const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
  box.innerHTML = `<div class="alert alert-${type}"><i class="fa-solid ${icon}"></i><span>${escHtml(message)}</span></div>`;
  box.scrollIntoView({behavior:'smooth',block:'start'});
}

// ── LOAD TICKETS ──────────────────────────────────────────────────────────
async function loadTickets() {
  try {
    const fd = new FormData();
    fd.append('action', 'my_tickets');
    const res = await fetch(API, {method:'POST', body:fd});
    const data = await res.json();
    if (!data.success) throw new Error(data.message);

    allTickets = {
      upcoming: data.upcoming || [],
      past: data.past || [],
      cancelled: data.cancelled || []
    };
    document.getElementById('cnt-upcoming').textContent = allTickets.upcoming.length;
    document.getElementById('cnt-past').textContent = allTickets.past.length;
    document.getElementById('cnt-cancelled').textContent = allTickets.cancelled.length;
    renderTab(currentTab);
  } catch(err) {
    document.getElementById('ticket-container').innerHTML =
      `<div class="empty"><i class="fa-solid fa-circle-exclamation"></i><h3>${escHtml(err.message)}</h3></div>`;
  }
}

function switchTab(tab) {
  currentTab = tab;
  ['upcoming','past','cancelled'].forEach(t => {
    document.getElementById(`tab-${t}`).classList.toggle('active', t === tab);
  });
  renderTab(tab);
}

function renderTab(tab) {
  const list = allTickets[tab] || [];
  const container = document.getElementById('ticket-container');

  if (list.length === 0) {
    const msgs = {
      upcoming: 'Bạn chưa có vé sắp chiếu. Hãy đặt vé ngay!',
      past: 'Bạn chưa có vé đã chiếu.',
      cancelled: 'Bạn chưa có vé đã hủy.'
    };
    container.innerHTML = `
      <div class="empty">
        <i class="fa-solid fa-ticket"></i>
        <h3>Chưa có vé nào</h3>
        <p>${msgs[tab]}</p>
        ${tab==='upcoming'?`<a href="home.php" style="display:inline-flex;align-items:center;gap:7px;margin-top:16px;padding:10px 22px;background:var(--blue);color:#fff;border-radius:10px;font-weight:700;text-decoration:none"><i class="fa-solid fa-film"></i> Xem phim ngay</a>`:''}
      </div>`;
    return;
  }

  const now = Math.floor(Date.now() / 1000);
  container.innerHTML = `<div class="ticket-list">${list.map(b => renderCard(b, tab, now)).join('')}</div>`;
}

function renderCard(b, tab, now) {
  const seats = JSON.parse(b.seats_json || '[]');
  const sc = STATUS_COLOR[b.status] || '#64748B';
  const sl = STATUS_LABEL[b.status] || b.status;
  const showtimeTs = Math.floor(new Date(`${b.show_date}T${b.start_time}`).getTime() / 1000);
  const minsLeft = (showtimeTs - now) / 60;
  const canCancel = b.status === 'confirmed' && minsLeft >= 60;

  const poster = b.poster_url
    ? `<img class="bk-poster" src="${escHtml(b.poster_url)}" alt="">`
    : `<div class="bk-poster-ph"><i class="fa-solid fa-film"></i></div>`;

  const seatsHtml = seats.map(s => `<span class="seat-chip">${escHtml(s)}</span>`).join('');
  const showDate = new Date(`${b.show_date}T00:00:00`).toLocaleDateString('vi-VN');

  const cancelBanner = b.status === 'cancelled' && b.cancel_reason ? `
    <div style="background:#FEF2F2;border-top:1px dashed #FECACA;border-bottom:1px dashed #FECACA;padding:12px 20px;font-size:13px;color:#991B1B;display:flex;flex-direction:column;gap:6px;text-align:left">
      <div style="display:flex;align-items:center;gap:8px"><i class="fa-solid fa-circle-exclamation" style="color:#EF4444;font-size:14px"></i>
        <span><strong>Lý do hủy vé:</strong> ${escHtml(b.cancel_reason)}</span></div>
      ${b.payment_status === 'refunded'
        ? `<div style="color:#16A34A;font-weight:700;display:flex;align-items:center;gap:8px"><i class="fa-solid fa-circle-check"></i><span>Đã hoàn tiền ${Number(b.total_amount).toLocaleString('vi-VN')}₫ về tài khoản <strong>${(b.payment_method||'').toUpperCase()}</strong></span></div>`
        : `<div style="color:#D97706;font-weight:700;display:flex;align-items:center;gap:8px"><i class="fa-solid fa-circle-notch fa-spin"></i><span>Đang xử lý hoàn tiền về tài khoản <strong>${(b.payment_method||'').toUpperCase()}</strong></span></div>`}
    </div>` : '';

  let cancelBtn = '';
  if (canCancel) {
    cancelBtn = `<button class="btn-sm btn-sm-red"
      onclick="confirmCancel('${escHtml(b.booking_code)}','${Number(b.total_amount).toLocaleString('vi-VN')}','${(b.payment_method||'').toUpperCase()}')">
      <i class="fa-solid fa-xmark"></i> Hủy vé
    </button>`;
  } else if (b.status === 'confirmed') {
    cancelBtn = `<span style="font-size:12px;color:var(--muted);font-weight:600;display:flex;align-items:center;gap:4px"><i class="fa-regular fa-clock"></i> Quá hạn hủy vé</span>`;
  }

  let reviewBtn = '';
  if (tab === 'past' && (b.status === 'confirmed' || b.status === 'checked_in')) {
    if (b.user_rating) {
      reviewBtn = `<button class="btn-sm btn-sm-gray"
        onclick="openReview('${escHtml(b.booking_code)}','${escHtml(b.title)}',${b.user_rating},'${escHtml((b.user_comment||'').replace(/'/g,"\\'"))}')">
        ⭐ ${b.user_rating}/10 (Sửa)
      </button>`;
    } else {
      reviewBtn = `<button class="btn-sm" style="background:#10B981;color:#fff"
        onclick="openReview('${escHtml(b.booking_code)}','${escHtml(b.title)}',10,'')">
        <i class="fa-regular fa-star"></i> Đánh giá
      </button>`;
    }
  }

  return `
    <div class="bk-card">
      <div class="bk-top">
        ${poster}
        <div class="bk-info">
          <div class="bk-title">${escHtml(b.title)}</div>
          <div class="bk-meta">
            📅 ${showDate} &nbsp;·&nbsp; ⏰ ${(b.start_time||'').substring(0,5)}<br>
            🎬 ${escHtml(b.format)} · ${escHtml(b.subtitle_type)} · ${escHtml(b.hall_name||'Phòng chiếu 1')}<br>
            📍 ${escHtml(b.cinema_name)}
          </div>
        </div>
        <div class="bk-status">
          <span class="status-badge" style="background:${sc}20;color:${sc}">
            <i class="fa-solid fa-circle" style="font-size:7px"></i>${sl}
          </span>
          <div class="bk-code">${escHtml(b.booking_code)}</div>
          <div class="bk-price">${Number(b.total_amount).toLocaleString('vi-VN')}₫</div>
        </div>
      </div>
      ${cancelBanner}
      <div class="bk-bot">
        <div class="seats-row">${seatsHtml}</div>
        <div class="bk-actions">
          <a href="booking-confirm.php?code=${encodeURIComponent(b.booking_code)}" class="btn-sm btn-sm-blue">
            <i class="fa-solid fa-eye"></i> Xem vé
          </a>
          ${cancelBtn}
          ${reviewBtn}
        </div>
      </div>
    </div>`;
}

// ── CANCEL ────────────────────────────────────────────────────────────────
function confirmCancel(code, amount, method) {
  cancelCode = code;
  document.getElementById('m-code').textContent = code;
  document.getElementById('m-amount').textContent = amount + '₫';
  document.getElementById('m-method').textContent = method;
  document.getElementById('cancel-overlay').classList.add('show');
}

function closeCancel() { document.getElementById('cancel-overlay').classList.remove('show'); }

async function doCancel() {
  const btn = document.getElementById('btn-confirm-cancel');
  btn.disabled = true; btn.textContent = 'Đang xử lý...';
  const fd = new FormData();
  fd.append('action', 'cancel_booking');
  fd.append('booking_code', cancelCode);
  try {
    const res = await fetch(API, {method:'POST', body:fd});
    const data = await res.json();
    closeCancel();
    if (data.success) {
      showAlert('success', data.message);
      setTimeout(() => { switchTab('cancelled'); loadTickets(); }, 1200);
    } else {
      showAlert('error', data.message);
    }
  } catch { showAlert('error', 'Lỗi kết nối. Vui lòng thử lại.'); }
  finally { btn.disabled = false; btn.innerHTML = 'Xác nhận hủy vé'; }
}

// ── REVIEW ────────────────────────────────────────────────────────────────
function buildStars(val) {
  const row = document.getElementById('star-row');
  if (!row) return;
  let html = '';
  for (let i = 1; i <= 10; i++) {
    html += `<span onclick="setStar(${i})" style="cursor:pointer;color:${i<=val?'#F59E0B':'#CBD5E1'};transition:all .15s;padding:0 2px">
      <i class="fa-solid fa-star"></i></span>`;
  }
  row.innerHTML = html;
  document.getElementById('rating-label').textContent = `${val} / 10 - ${STAR_LABELS[val]}`;
  currentRating = val;
}

function setStar(val) { buildStars(val); }

function openReview(code, title, rating, comment) {
  document.getElementById('rev-booking-code').value = code;
  document.getElementById('rev-movie-title').textContent = title;
  document.getElementById('rev-comment').value = comment;
  buildStars(parseInt(rating) || 10);
  document.getElementById('review-overlay').classList.add('show');
  setTimeout(() => document.getElementById('rev-comment').focus(), 100);
}

function closeReview() { document.getElementById('review-overlay').classList.remove('show'); }

async function submitReview() {
  const btn = document.getElementById('btn-submit-review');
  btn.disabled = true; btn.textContent = 'Đang gửi...';
  const fd = new FormData();
  fd.append('action', 'submit_review');
  fd.append('booking_code', document.getElementById('rev-booking-code').value);
  fd.append('rating', currentRating);
  fd.append('comment', document.getElementById('rev-comment').value);
  try {
    const res = await fetch(API, {method:'POST', body:fd});
    const data = await res.json();
    closeReview();
    if (data.success) {
      showAlert('success', data.message);
      setTimeout(() => loadTickets(), 1200);
    } else {
      showAlert('error', data.message);
    }
  } catch { showAlert('error', 'Lỗi kết nối.'); }
  finally { btn.disabled = false; btn.textContent = 'Gửi đánh giá'; }
}

buildStars(10);
loadTickets();
</script>
</body>
</html>