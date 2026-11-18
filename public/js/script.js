const DB_THEME='lk_theme';
let users=[], reports=[], pendingPhoto=null, currentView='';
let session=window.__USER__||null;
// Diisi oleh Laravel (resources/views/app.blade.php)
const BASE=(window.__BASE__||'').replace(/\/$/,'');   // awalan folder bila dipasang di subfolder
const SCHOOL=window.__SCHOOL__||{name:'Sekolah',city:''};
const DENAH_IMG=window.__DENAH__||(BASE+'/images/denah.jpg');   // dengan nomor versi agar denah baru tidak tersangkut cache
const $=id=>document.getElementById(id);

/* ---- Layar pembuka: 5 detik, sekali per tab browser ---- */
(function splash(){
  const el=$('splash'); if(!el) return;
  const texts=['Memuat laporan terbaru…','Menyiapkan denah sekolah…','Hampir siap…'];
  let i=0;
  const tick=setInterval(()=>{ i=(i+1)%texts.length; const s=$('splashText'); if(s) s.textContent=texts[i]; },1700);
  setTimeout(()=>{
    clearInterval(tick);
    el.classList.add('hide'); document.body.classList.remove('splashing');
    try{ sessionStorage.setItem('lk_splash','1'); }catch(e){}
    setTimeout(()=>el.remove(),600);
  },5000);
})();

/* Panggilan ke server Laravel (sesi + CSRF) */
function csrfMeta(){ return document.querySelector('meta[name="csrf-token"]'); }
async function refreshCsrf(){
  try{ const r=await fetch(BASE+'/api/csrf',{credentials:'same-origin',headers:{'Accept':'application/json'}}); const d=await r.json(); if(d&&d.csrf){ csrfMeta().content=d.csrf; return true; } }catch(e){}
  return false;
}
async function api(method,url,body,retried){
  const opt={method:method,credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrfMeta().content}};
  if(body!==undefined){ opt.headers['Content-Type']='application/json'; opt.body=JSON.stringify(body); }
  let res; try{ res=await fetch(BASE+url,opt); }catch(e){ throw new Error('Tidak dapat terhubung ke server.'); }
  // Sesi lama sudah habis (token CSRF tidak cocok): ambil token baru lalu ulangi sekali.
  if(res.status===419 && !retried && await refreshCsrf()) return api(method,url,body,true);
  let data=null; try{ data=await res.json(); }catch(e){}
  if(!res.ok){
    let msg=(data&&data.message)||('Terjadi kendala ('+res.status+').');
    if(data&&data.errors){ const f=Object.values(data.errors)[0]; if(f&&f[0]) msg=f[0]; }
    if(res.status===419) msg='Halaman kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    if(res.status===429) msg='Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.';
    if(res.status===401 && session){ resetUI(); msg='Sesi berakhir, silakan masuk lagi.'; }
    const err=new Error(msg); err.status=res.status; throw err;
  }
  return data;
}
function resizeImage(file,max,q,square){
  return new Promise((res,rej)=>{
    const rd=new FileReader();
    rd.onerror=()=>rej(new Error('Gagal membaca file.'));
    rd.onload=ev=>{ const img=new Image(); img.onerror=()=>rej(new Error('Gambar tidak bisa dibuka.'));
      img.onload=()=>{ const c=document.createElement('canvas'); let sx=0,sy=0,sw=img.width,sh=img.height;
        if(square){ const m=Math.min(img.width,img.height); sx=(img.width-m)/2; sy=(img.height-m)/2; sw=sh=m; c.width=c.height=max; }
        else{ const k=Math.min(1,max/Math.max(img.width,img.height)); c.width=Math.round(img.width*k); c.height=Math.round(img.height*k); }
        c.getContext('2d').drawImage(img,sx,sy,sw,sh,0,0,c.width,c.height); res(c.toDataURL('image/jpeg',q)); };
      img.src=ev.target.result; };
    rd.readAsDataURL(file);
  });
}
function safeImg(p){ return (typeof p==='string' && p.startsWith('data:image/') && p.indexOf('"')<0 && p.indexOf("'")<0 && p.indexOf('<')<0 && p.indexOf(')')<0)?p:''; }
/* Foto profil dari Google berupa URL https (bukan data URL), jadi diperiksa terpisah.
   Hanya domain foto milik Google yang diterima agar tidak bisa dipakai menyisipkan URL lain. */
function safeAvatarUrl(u){
  if(typeof u!=='string' || !u.startsWith('https://')) return '';
  if(u.indexOf('"')>=0 || u.indexOf("'")>=0 || u.indexOf('<')>=0 || u.indexOf(')')>=0) return '';
  try{ const h=new URL(u).hostname;
    return (h.endsWith('.googleusercontent.com')||h==='lh3.googleusercontent.com')?u:'';
  }catch(e){ return ''; }
}
function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

function toast(msg){const t=$('toast'); t.textContent=msg; t.classList.add('show'); clearTimeout(toast._t); toast._t=setTimeout(()=>t.classList.remove('show'),2600);}

/* Notifikasi real time (pojok layar): laporan baru, perubahan status.
   kind: new | proc | done | rej | info, menentukan warna garis dan ikonnya. */
const NOTIF_IC={new:'i-bell',proc:'i-tool',done:'i-check',rej:'i-x',info:'i-bell'};
function notify(kind,title,text,onClick){
  const box=$('notifStack'), el=document.createElement('div');
  el.className='notif k-'+kind;
  el.innerHTML=`<div class="n-ic">${ic(NOTIF_IC[kind]||'i-bell')}</div><div class="n-body"><b>${escapeHtml(title)}</b><span>${escapeHtml(text)}</span></div><button type="button" class="n-x" aria-label="Tutup">${ic('i-x')}</button>`;
  el.querySelector('.n-x').onclick=e=>{ e.stopPropagation(); el.remove(); };
  if(onClick){ el.classList.add('click'); el.onclick=()=>{ onClick(); el.remove(); }; }
  box.prepend(el);
  while(box.children.length>3) box.lastElementChild.remove();
  setTimeout(()=>{ el.classList.add('out'); setTimeout(()=>el.remove(),400); },7000);
}

/* Waktu relatif ("5 menit lalu"), memakai jam server agar tetap tepat */
let clockSkew=0;
function nowMs(){ return Date.now()+clockSkew; }
function ago(ts){
  const s=Math.max(0,(nowMs()-ts)/1000);
  if(s<60) return 'baru saja';
  const m=Math.floor(s/60); if(m<60) return m+' menit lalu';
  const h=Math.floor(m/60); if(h<24) return h+' jam lalu';
  const d=Math.floor(h/24); if(d<7) return d+' hari lalu';
  return fmtDate(ts);
}
function fmtDate(ts,long){ return new Date(ts).toLocaleDateString('id-ID',{day:'numeric',month:long?'long':'short',year:'numeric'}); }
function fmtDateTime(ts){ return new Date(ts).toLocaleString('id-ID',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}); }

/* Ikon garis dari sprite SVG di app.blade.php */
const ic=(name,cls)=>`<svg class="ico${cls?' '+cls:''}" aria-hidden="true"><use href="#${name}"/></svg>`;
const CAT_ICON={'Listrik & AC':'c-listrik','Air & Sanitasi':'c-air','Furnitur':'c-furnitur','Kebersihan':'c-bersih','IT & Komputer':'c-it','Bangunan':'c-bangunan','Lainnya':'c-lain'};
const catIcon=c=>ic(CAT_ICON[c]||'c-lain');
const TINGKAT=['Ringan','Sedang','Berat','Darurat'];
// Nomor laporan seperti di buku catatan sarpras, mis. LP-0007
const lpNo=id=>'LP-'+String(id).padStart(4,'0');
// Tingkat kerusakan: empat batang, makin parah makin banyak yang terisi. Kosong = belum dinilai admin.
const sevHtml=t=>t
  ? `<span class="sev sev-${escapeHtml(t)}"><span class="sev-m" aria-hidden="true"><i></i><i></i><i></i><i></i></span>${escapeHtml(t)}</span>`
  : '<span class="sev sev-none" title="Tingkat kerusakan ditentukan petugas setelah dicek"><span class="sev-m" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Belum dinilai</span>';

/* ---- Masuk / daftar (jendela, muncul hanya saat dibutuhkan) ---- */
let authNext=null;
function openAuth(tab,note,next){
  authNext=next||null;
  const n=$('loginNote');
  if(note){ n.textContent=note; n.style.display='block'; } else n.style.display='none';
  switchTab(tab||'login');
  $('auth').classList.add('on'); document.body.classList.add('modal-open');
  setTimeout(()=>{ const f=$(tab==='register'?'regName':'loginUser'); if(f && window.innerWidth>=760) f.focus({preventScroll:true}); },60);
}
function closeAuth(){ $('auth').classList.remove('on'); document.body.classList.remove('modal-open'); authNext=null; }

function switchTab(tab){
  $('tabLogin').classList.toggle('active', tab==='login');
  $('tabRegister').classList.toggle('active', tab==='register');
  $('formLogin').style.display = tab==='login' ? 'block':'none';
  $('formRegister').style.display = tab==='register' ? 'block':'none';
  $('formForgot').style.display = tab==='forgot' ? 'block':'none';
  if(tab==='forgot'){
    $('errForgot').style.display='none'; $('okForgot').style.display='none';
    const u=$('loginUser').value.trim(); if(u) $('fgUser').value=u;
  }
}
function validEmail(e){ return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(e||'').trim()); }

async function doForgot(e){
  e.preventDefault();
  const err=$('errForgot'), ok=$('okForgot');
  const show=m=>{ ok.style.display='none'; err.textContent=m; err.style.display='block'; };
  const user=$('fgUser').value.trim();
  if(!user){ show('Isi username atau email akunmu.'); return false; }
  const btn=e.target.querySelector('.btn-primary'); btn.disabled=true;
  try{
    const d=await api('POST','/api/forgot-password',{username:user});
    err.style.display='none'; ok.className='note'+(d.mode==='email'?'':' warn'); ok.textContent=d.message; ok.style.display='block';
    if(!user.includes('@')) $('loginUser').value=user;
  }catch(ex){ show(ex.message); }
  btn.disabled=false;
  return false;
}

async function doRegister(e){
  e.preventDefault();
  const err=$('errRegister');
  const show=m=>{ err.textContent=m; err.style.display='block'; };
  const name=($('regName').value||'').trim();
  const user=($('regUser').value||'').trim();
  const email=($('regEmail').value||'').trim();
  const pass=$('regPass').value||'';
  if(!name||!user||!email||!pass){ show('Semua kolom wajib diisi.'); return false; }
  if(!/^[A-Za-z0-9._-]{3,20}$/.test(user)){ show('Username 3-20 karakter: huruf, angka, titik, garis bawah, atau strip (tanpa spasi).'); return false; }
  if(!validEmail(email)){ show('Alamat email tidak valid. Contoh: nama@gmail.com.'); return false; }
  if(pass.length<6){ show('Kata sandi minimal 6 karakter.'); return false; }
  try{
    await api('POST','/api/register',{name:name,username:user,email:email,password:pass});
    err.style.display='none';
    toast('Akun berhasil dibuat. Silakan masuk.');
    switchTab('login');
    $('loginUser').value=user; $('loginPass').value='';
    $('regName').value=''; $('regUser').value=''; $('regEmail').value=''; $('regPass').value='';
  }catch(ex){ show(ex.message); }
  return false;
}

async function doLogin(e){
  e.preventDefault();
  const err=$('errLogin');
  const show=m=>{ err.textContent=m; err.style.display='block'; };
  const user=($('loginUser').value||'').trim();
  const pass=$('loginPass').value||'';
  if(!user||!pass){ show('Username dan kata sandi wajib diisi.'); return false; }
  authBusy=true;   // jeda pembaruan otomatis selama proses masuk
  try{
    const d=await api('POST','/api/login',{username:user,password:pass});
    authBusy=false;
    err.style.display='none';
    session=d.user;
    const next=authNext; closeAuth();
    $('loginPass').value='';
    // Masuk dengan kata sandi sementara dari email: langsung ke Profil untuk membuat kata sandi baru.
    await enterApp(d.mustChange?'profile':(next || (isAdmin()?'admin':'home')));
    if(d.mustChange){
      $('pwOld').value=pass;
      $('pwForm').scrollIntoView({behavior:'smooth',block:'center'});
      toast('Kamu masuk dengan kata sandi sementara. Buat kata sandi baru di sini.');
    }else toast('Selamat datang, '+session.name.split(' ')[0]+'!');
  }catch(ex){ show(ex.message); }
  authBusy=false;
  return false;
}

async function doLogout(){
  authBusy=true;
  try{ const d=await api('POST','/api/logout'); if(d&&d.csrf) csrfMeta().content=d.csrf; }catch(e){}
  authBusy=false;
  resetUI();
  toast('Kamu sudah keluar.');
}
function resetUI(){
  session=null; reports=[]; users=[]; myKnown=null; clearMapPick();
  document.body.classList.remove('is-admin'); document.body.classList.add('guest');
  $('formLogin').reset();
  switchTab('login');
  go('home');
}

async function enterApp(next){
  document.body.classList.remove('guest');
  document.body.classList.toggle('is-admin',isAdmin());
  $('railName').textContent=session.name;
  refreshAvatars();
  $('railRole').textContent=isAdmin()?'Admin':'Siswa';
  $('greet').textContent='Halo, '+session.name.split(' ')[0];
  await go(next||'home');
}

function isAdmin(){ return !!(session && session.admin===true); }

const LOGIN_VIEWS=['create','mine','detail','profile'], ADMIN_VIEWS=['admin','adm-users'];
async function go(view){
  if(view==='dashboard') view='home';
  // Pengunjung bebas melihat Beranda; akun baru diminta saat ingin melapor atau membuka halaman pribadi.
  if(!session && (LOGIN_VIEWS.includes(view)||ADMIN_VIEWS.includes(view))){
    openAuth('login', view==='create'?'Masuk atau daftar dulu untuk membuat laporan.':'Masuk dulu untuk membuka halaman ini.', view);
    return;
  }
  if(isAdmin() && ['create','mine','detail'].includes(view)) view='admin';
  if(session && !isAdmin() && ADMIN_VIEWS.includes(view)) view='home';
  currentView=view;
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('on'));
  $('v-'+view).classList.add('on');
  document.querySelectorAll('.rail nav button').forEach(b=>b.classList.toggle('active', b.dataset.v===view));
  window.scrollTo(0,0);
  if(view==='create') return;
  if(view==='info') return;
  try{
    if(view==='home') await Promise.all([loadFeed(false), session?loadData():null]);
    else await loadData();
  }catch(ex){ toast(ex.message); }
  if(currentView!==view) return;
  if(view==='home') renderHome();
  if(view==='admin') renderAdmin();
  if(view==='adm-users') renderAdmUsers();
  if(view==='mine') renderMine();
  if(view==='profile'){ renderProfile(); if(isAdmin()){ loadMail(); loadGoogle(); } }
}
async function loadData(){
  if(!session) return;
  const d=await api('GET','/api/reports');
  reports=d.reports||[]; users=d.users||[];
  updateBadges();
}
function updateBadges(){
  if(!isAdmin()) return;
  const w=reports.filter(r=>r.status==='Menunggu').length, rr=users.filter(u=>u.resetRequested).length;
  const bw=$('badgeWait'), br=$('badgeReset');
  bw.textContent=w; bw.hidden=!w; br.textContent=rr; br.hidden=!rr;
}

function myReports(){ return session?reports.filter(r=>r.owner===session.id).sort((a,b)=>b.ts-a.ts):[]; }

/* ---- Beranda publik ---- */
let feed=[], feedStats=null, feedKnown=null, feedFilter='all', feedLimit=12, freshIds=new Set(), feedSig='';
async function loadFeed(isPoll){
  const d=await api('GET','/api/public/reports');
  if(d.now) clockSkew=d.now-Date.now();
  const list=d.reports||[];
  if(isPoll && feedKnown) announceFeedChanges(list);
  feedKnown=new Map(list.map(r=>[r.id,r.status]));
  feed=list; feedStats=d.stats||null;
  const sig=JSON.stringify(list.map(r=>[r.id,r.status,r.updatedTs]));
  const changed=sig!==feedSig; feedSig=sig;
  return changed;
}
// Laporan baru dan perubahan status sejak pengecekan terakhir
let mySubmitted=new Set();
function announceFeedChanges(list){
  const mineIds=new Set(myReports().map(r=>r.id));
  const added=list.filter(r=>!feedKnown.has(r.id) && !mySubmitted.has(r.id));
  added.slice(0,3).forEach(r=>{
    freshIds.add(r.id);
    notify('new', isAdmin()?'Laporan baru menunggu persetujuan':'Laporan baru dari '+r.reporter, r.title+' · '+r.location, ()=>isAdmin()?go('admin'):openFeed(r.id));
  });
  list.forEach(r=>{
    const before=feedKnown.get(r.id);
    if(before && before!==r.status && !mineIds.has(r.id)){
      freshIds.add(r.id);
      if(!session||isAdmin()) return; // admin sendiri yang mengubah statusnya
      notify(r.status==='Selesai'?'done':'proc', r.status==='Selesai'?'Perbaikan selesai':'Sedang diperbaiki', r.title+' · '+r.location, ()=>openFeed(r.id));
    }
  });
  if(added.length || freshIds.size) setTimeout(()=>{ freshIds.clear(); if(currentView==='home') renderFeed(); },9000);
}

function renderHome(){
  const mb=$('mineBox');
  if(session && !isAdmin()){
    const mine=myReports();
    mb.hidden=false;
    $('emailNote').style.display=session.email?'none':'block';
    $('statAll').textContent=mine.length;
    $('statWait').textContent=mine.filter(r=>r.status==='Menunggu').length;
    $('statProc').textContent=mine.filter(r=>r.status==='Diproses').length;
    $('statDone').textContent=mine.filter(r=>r.status==='Selesai').length;
  } else mb.hidden=true;
  const s=feedStats||{};
  $('pubAll').textContent=s.total??0; $('pubWait').textContent=s.Menunggu??0; $('pubProc').textContent=s.Diproses??0; $('pubDone').textContent=s.Selesai??0;
  renderFeed(); renderActivity(); renderSideMap(); renderBars();
}
function setFeedFilter(f){
  feedFilter=f; feedLimit=12;
  document.querySelectorAll('#feedChips .chip').forEach(c=>c.classList.toggle('on',c.dataset.f===f));
  renderFeed();
}
function feedVisible(){
  const q=($('feedSearch').value||'').trim().toLowerCase();
  return feed.filter(r=>(feedFilter==='all'||r.status===feedFilter) && (!q||(r.title+' '+r.location+' '+r.category).toLowerCase().includes(q)));
}
function renderFeed(){
  const box=$('feedList'), list=feedVisible();
  if(!feed.length){ box.innerHTML=`<div class="feed-empty"><b>Belum ada laporan.</b><p>Kalau kamu menemukan fasilitas yang rusak, laporkan di sini.</p><button type="button" class="btn-primary" onclick="go('create')">${ic('i-plus')}Buat laporan</button></div>`; return; }
  if(!list.length){ box.innerHTML='<div class="feed-empty">Tidak ada laporan yang cocok dengan pencarian atau filter ini.</div>'; return; }
  box.innerHTML=list.slice(0,feedLimit).map(feedRow).join('')
    +(list.length>feedLimit?`<button type="button" class="feed-more" onclick="feedLimit+=12;renderFeed()">Tampilkan lebih banyak · ${list.length-feedLimit} lagi</button>`:'');
}
function feedRow(r){
  const fresh=freshIds.has(r.id), urgent=r.tingkat==='Darurat'&&r.status!=='Selesai';
  return `<article class="ri${fresh?' is-new':''}${urgent?' urgent':''}" onclick="openFeed(${r.id})" tabindex="0" onkeydown="if(event.key==='Enter')openFeed(${r.id})">
    <div class="ri-thumb">${r.hasPhoto?`<img src="${BASE}/api/public/reports/${r.id}/photo" loading="lazy" alt="">`:catIcon(r.category)}</div>
    <div class="ri-main"><div class="ri-title">${escapeHtml(r.title)}${fresh?'<span class="tag-new">Baru</span>':''}</div>
      <div class="ri-meta"><span class="mono">${lpNo(r.id)}</span> · ${escapeHtml(r.location)} · ${escapeHtml(r.category)}</div></div>
    <div class="ri-sev">${sevHtml(r.tingkat)}</div>
    <div class="ri-status"><span class="badge ${r.status}">${r.status}</span></div>
    <div class="ri-time"><span class="ri-by">${escapeHtml(r.reporter)}</span><span class="ri-ago">${ago(r.ts)}</span></div>
  </article>`;
}
function renderActivity(){
  const ev=[];
  feed.forEach(r=>{
    ev.push({ts:r.ts,k:'new',html:`<b>${escapeHtml(r.reporter)}</b> melaporkan <b>${escapeHtml(r.title)}</b>`,id:r.id});
    if(r.status!=='Menunggu' && r.updatedTs>r.ts+1000)
      ev.push({ts:r.updatedTs,k:r.status==='Selesai'?'done':'proc',html:`<b>${escapeHtml(r.title)}</b> ${r.status==='Selesai'?'selesai diperbaiki':'sedang diperbaiki'}`,id:r.id});
  });
  ev.sort((a,b)=>b.ts-a.ts);
  $('activity').innerHTML=ev.length?ev.slice(0,7).map(e=>`<li onclick="openFeed(${e.id})"><span class="act-dot k-${e.k}"></span><div><div>${e.html}</div><small>${ago(e.ts)}</small></div></li>`).join(''):'<li class="act-empty">Belum ada aktivitas.</li>';
}
function renderSideMap(){
  const act=feed.filter(r=>r.map && r.status!=='Selesai');
  $('sideMapPins').innerHTML=act.map(r=>pinHtml(r.map,r.title,'sm')).join('');
}
function renderBars(){
  const s=feedStats||{}, sev=s.tingkat||{}, cat=s.kategori||{};
  const bars=(rows,cls)=>{ const max=Math.max(1,...rows.map(r=>r[1])); return rows.map(([k,v])=>`<div class="bar-row"><span class="bar-k">${k}</span><span class="bar-track"><span class="bar-fill ${cls?cls(k):''}" style="width:${Math.round(v/max*100)}%"></span></span><b>${v}</b></div>`).join(''); };
  const sevRows=TINGKAT.map(t=>[t,sev[t]||0]);
  $('sevBars').innerHTML=sevRows.some(r=>r[1])?bars(sevRows,k=>'sevfill-'+k):'<div class="side-hint">Semua laporan sudah ditangani.</div>';
  const catRows=Object.entries(cat);
  $('catBars').innerHTML=catRows.length?bars(catRows.map(([k,v])=>[escapeHtml(k),v])):'<div class="side-hint">Belum ada data.</div>';
}

/* Detail laporan publik */
function stepsHtml(status){
  const st=['Menunggu','Diproses','Selesai'], idx=st.indexOf(status), last=st.length-1;
  return `<div class="steps">${st.map((s,i)=>{
    const done=i<idx||(i===idx&&i===last), cur=i===idx&&i!==last;
    return `<div class="step${done?' done':''}${cur?' cur':''}"><span>${done?ic('i-check'):i+1}</span>${s}</div>`;
  }).join('')}</div>`;
}
function openFeed(id){
  const r=feed.find(x=>x.id===id); if(!r) return;
  $('fmTitle').textContent=r.title;
  $('fmBody').innerHTML=`<p class="doc-no">${lpNo(r.id)}</p>
    ${r.hasPhoto?`<img class="detail-photo" src="${BASE}/api/public/reports/${r.id}/photo" alt="Foto laporan">`:''}
    ${stepsHtml(r.status)}
    <div class="detail-row"><div class="k">Tingkat</div><div>${sevHtml(r.tingkat)}</div></div>
    <div class="detail-row"><div class="k">Kategori</div><div>${escapeHtml(r.category)}</div></div>
    <div class="detail-row"><div class="k">Lokasi</div><div>${escapeHtml(r.location)}${r.map?`<div class="map mini" onclick="showFeedOnMap(${r.id})" title="Ketuk untuk memperbesar"><img src="${DENAH_IMG}" alt="Denah sekolah" draggable="false">${pinHtml(r.map,r.location,'')}</div>`:''}</div></div>
    <div class="detail-row"><div class="k">Pelapor</div><div>${escapeHtml(r.reporter)}</div></div>
    <div class="detail-row"><div class="k">Dilaporkan</div><div>${fmtDate(r.ts,true)} · ${ago(r.ts)}</div></div>
    <div class="detail-row"><div class="k">Deskripsi</div><div>${escapeHtml(r.desc)}</div></div>
    ${r.tanggapan?`<div class="reply-box"><strong>Tanggapan petugas:</strong><br>${escapeHtml(r.tanggapan)}</div>`:''}
    ${session?'':`<div class="feed-cta"><span>Menemukan kerusakan lain?</span><button type="button" class="btn-primary" onclick="closeFeed();go('create')">${ic('i-plus')}Laporkan</button></div>`}`;
  $('feedModal').classList.add('on'); document.body.classList.add('modal-open');
}
function closeFeed(){ $('feedModal').classList.remove('on'); if(!$('auth').classList.contains('on')) document.body.classList.remove('modal-open'); }
function showFeedOnMap(id){
  const r=feed.find(x=>x.id===id); if(!r||!r.map) return;
  closeFeed();
  const room=roomAt(r.map.x,r.map.y);
  openMapModal(r.title,'Lokasi: '+r.location,'<button class="btn-primary" style="margin:0" onclick="closeMap()">Tutup</button>');
  mapEl('mapPins').innerHTML=pinHtml(r.map,r.location,'big pulse');
  showHl(room); centerPin();
}
function showPublicMap(){
  const list=feed.filter(r=>r.map);
  if(!list.length){ toast('Belum ada laporan dengan titik di denah.'); return; }
  const done=s=>s==='Selesai';
  openMapModal('Peta laporan',list.length+' laporan. Merah = belum selesai, abu-abu = selesai. Ketuk tanda untuk melihat detailnya.','<button class="btn-primary" style="margin:0" onclick="closeMap()">Tutup</button>');
  mapEl('mapPins').innerHTML=list.slice().sort((a,b)=>done(b.status)-done(a.status)).map(r=>pinHtml(r.map,r.title+' ('+r.status+')',done(r.status)?'done':'',r.id)).join('');
  mapEl('mapPins').onclick=e=>{ const id=Number(e.target.dataset.id); if(id){ closeMap(); openFeed(id); } };
}

/* ---- Real time: cek laporan baru & perubahan status setiap 10 detik ---- */
let myKnown=null, pollTimer=null, lastPoll=0, adminSig='', authBusy=false;
async function poll(){
  if(document.visibilityState!=='visible' || authBusy) return;
  lastPoll=Date.now();
  let feedChanged=false, mineChanged=false;
  try{ feedChanged=await loadFeed(true); }catch(e){ return; }
  if(session){
    try{
      const before=myKnown; await loadData();
      const sig=JSON.stringify(reports.map(r=>[r.id,r.status]))+users.map(u=>u.resetRequested?u.uid:'').join();
      mineChanged=sig!==adminSig; adminSig=sig;
      if(!isAdmin()) announceMyChanges(before);
    }catch(e){}
  }
  if(currentView==='home' && (feedChanged||mineChanged||freshIds.size)) renderHome();
  else if(currentView==='home') { renderActivity(); renderFeedTimes(); }
  if(currentView==='admin' && mineChanged) renderAdmin();
  if(currentView==='adm-users' && mineChanged) renderAdmUsers();
  if(currentView==='mine' && mineChanged) renderMine();
}
function renderFeedTimes(){ /* waktu relatif ikut diperbarui tanpa menggambar ulang daftar */
  const list=feedVisible();
  document.querySelectorAll('#feedList .ri').forEach((el,i)=>{ const r=list[i], a=el.querySelector('.ri-ago'); if(r&&a) a.textContent=ago(r.ts); });
}
// Siswa: beri tahu saat laporannya ditanggapi admin
function announceMyChanges(before){
  const now=new Map(myReports().map(r=>[r.id,r.status]));
  if(before) myReports().forEach(r=>{
    const old=before.get(r.id);
    if(old && old!==r.status){
      const t={Diproses:['proc','Laporanmu disetujui'],Selesai:['done','Laporanmu selesai diperbaiki'],Ditolak:['rej','Laporanmu ditolak']}[r.status]||['info','Status laporan berubah'];
      notify(t[0],t[1],r.title+(r.tanggapan?' · '+r.tanggapan:''),()=>{ go('mine').then(()=>openDetail(r.id)); });
    }
  });
  myKnown=now;
}
function startPolling(){
  clearInterval(pollTimer);
  pollTimer=setInterval(poll,10000);
}
document.addEventListener('visibilitychange',()=>{ if(document.visibilityState==='visible' && Date.now()-lastPoll>5000) poll(); });

/* ---- Laporan saya ---- */
function renderMine(){
  const q=($('mineSearch').value||'').trim().toLowerCase();
  const all=myReports();
  if(!myKnown) myKnown=new Map(all.map(r=>[r.id,r.status]));
  const mine=all.filter(r=>!q||(r.title+' '+r.category+' '+r.location).toLowerCase().includes(q));
  const list=$('mineList');
  if(all.length===0){ list.innerHTML=`<div class="empty">Kamu belum membuat laporan.<button type="button" class="btn-primary" onclick="go('create')">${ic('i-plus')}Buat laporan</button></div>`; return; }
  if(mine.length===0){ list.innerHTML='<div class="empty">Tidak ada laporan yang cocok.</div>'; return; }
  list.innerHTML=mine.map(r=>rowHtml(r)).join('');
}

function rowHtml(r){
  const locked=r.status==='Diproses';
  return `<article class="ri has-act" onclick="openDetail('${r.id}')" tabindex="0" onkeydown="if(event.key==='Enter')openDetail('${r.id}')">
    <div class="ri-thumb">${r.hasPhoto?`<img src="${BASE}/api/reports/${r.id}/photo" loading="lazy" alt="">`:catIcon(r.category)}</div>
    <div class="ri-main"><div class="ri-title">${escapeHtml(r.title)}</div><div class="ri-meta"><span class="mono">${lpNo(r.id)}</span> · ${fmtDate(r.ts)} · ${escapeHtml(r.location)} · ${escapeHtml(r.category)}</div></div>
    <div class="ri-sev">${sevHtml(r.tingkat)}</div>
    <div class="ri-status"><span class="badge ${r.status}">${r.status}</span></div>
    <div class="ri-act"><button type="button" class="ri-del${locked?' off':''}" title="${locked?'Sedang diperbaiki, belum bisa dihapus':'Hapus laporan'}" aria-label="Hapus laporan ${escapeHtml(r.title)}" onclick="event.stopPropagation();askRemove('${r.id}')" onkeydown="event.stopPropagation()">${ic('i-trash')}</button></div>
  </article>`;
}

const FILE_EMPTY=$('fileText').innerHTML;   // isi kotak foto sebelum ada foto dipilih
async function previewPhoto(e){
  const file=e.target.files[0];
  if(!file) return;
  try{
    pendingPhoto=await resizeImage(file,1000,.75,false);
    $('filePick').classList.remove('need');
    $('fileText').innerHTML='Foto terpilih: '+escapeHtml(file.name)+'<br><img src="'+pendingPhoto+'">';
  }catch(ex){ pendingPhoto=null; toast(ex.message); }
}

/* Tingkat kerusakan: 4 level, ditentukan admin saat menyetujui laporan */
const SEV_INFO={
  Ringan:'Masih bisa dipakai, cukup perbaikan kecil (mis. engsel longgar, cat mengelupas).',
  Sedang:'Fungsinya terganggu dan perlu segera diperbaiki (mis. AC kurang dingin, kran menetes).',
  Berat:'Tidak bisa dipakai sama sekali (mis. proyektor mati, kursi patah).',
  Darurat:'Berbahaya bagi orang di sekitarnya (mis. kabel terkelupas, plafon hampir runtuh, kaca pecah). Amankan area secepatnya.'
};
function updateDecideSev(){
  const v=(document.querySelector('input[name="dmTingkat"]:checked')||{}).value, h=$('dmSevHint');
  h.className='sev-hint '+(v||''); h.innerHTML=v?'<b>'+v+':</b> '+SEV_INFO[v]:'Pilih sesuai kondisi yang kamu lihat saat mengecek.';
}

async function submitReport(e){
  e.preventDefault();
  const form=e.target, btn=form.querySelector('.btn-primary');
  const body={
    title:$('fTitle').value.trim(),
    category:$('fCategory').value,
    location:$('fLocation').value.trim(),
    desc:$('fDesc').value.trim(),
    photo:pendingPhoto||null,
    map_x:mapPick?mapPick.x:null,
    map_y:mapPick?mapPick.y:null
  };
  if(!body.photo){
    // Foto wajib sebagai bukti kerusakan
    $('filePick').classList.add('need');
    $('filePick').scrollIntoView({behavior:'smooth',block:'center'});
    toast('Tambahkan foto kerusakan sebagai bukti.');
    return false;
  }
  if(btn) btn.disabled=true;
  try{
    const r=await api('POST','/api/reports',body);
    if(r && r.id) mySubmitted.add(r.id);
    form.reset();
    $('fileText').innerHTML=FILE_EMPTY;
    pendingPhoto=null; clearMapPick();
    toast('Laporan berhasil dikirim dan langsung tampil di Beranda.');
    await go('mine');
  }catch(ex){ toast(ex.message); }
  if(btn) btn.disabled=false;
  return false;
}

function openDetail(id){
  const r=reports.find(x=>String(x.id)===String(id));
  if(!r) return;
  currentView='detail';
  $('detailBody').innerHTML=`
    <p class="doc-no">${lpNo(r.id)}</p>
    <h3 class="detail-title">${escapeHtml(r.title)}</h3>
    ${r.hasPhoto?`<img class="detail-photo" src="${BASE}/api/reports/${r.id}/photo" alt="Foto laporan">`:''}
    ${r.status!=='Ditolak'?stepsHtml(r.status):''}
    <div class="detail-row"><div class="k">Status</div><span class="badge ${r.status}">${r.status}</span></div>
    <div class="detail-row"><div class="k">Kategori</div><div>${escapeHtml(r.category)}</div></div>
    <div class="detail-row"><div class="k">Lokasi</div><div>${escapeHtml(r.location)}${r.map?`<div class="map mini" onclick="showOnMap('${r.id}')" title="Ketuk untuk memperbesar"><img src="${DENAH_IMG}" alt="Denah sekolah" draggable="false">${pinHtml(r.map,r.location,'')}</div>`:''}</div></div>
    <div class="detail-row"><div class="k">Tingkat kerusakan</div><div>${sevHtml(r.tingkat)}</div></div>
    <div class="detail-row"><div class="k">Tanggal</div><div>${fmtDate(r.ts,true)}</div></div>
    <div class="detail-row"><div class="k">Deskripsi</div><div>${escapeHtml(r.desc)}</div></div>
    <div class="reply-box"><strong>Tanggapan petugas:</strong><br>${r.tanggapan? escapeHtml(r.tanggapan) : 'Belum ada tanggapan dari petugas.'}</div>
    ${r.status==='Diproses'
      ? `<div class="hint left" style="margin-top:14px;">${NO_DELETE_MSG}</div>`
      : `<div class="detail-actions"><button class="btn-ghost danger" onclick="askRemove('${r.id}')">${ic('i-trash')}Hapus laporan</button></div>`}
  `;
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('on'));
  $('v-detail').classList.add('on');
  document.querySelectorAll('.rail nav button').forEach(b=>b.classList.toggle('active',b.dataset.v==='mine'));
  window.scrollTo(0,0);
}

function renderProfile(){
  editName(false);
  refreshAvatars();
  $('pName').textContent=session.name;
  $('pUser').textContent=session.user;
  $('pRole').textContent=isAdmin()?'Admin':'Siswa';
  $('pTotalRow').style.display=isAdmin()?'none':'flex';
  $('pEmailRow').style.display=isAdmin()?'none':'flex';
  $('forgotOldLink').parentElement.style.display=isAdmin()?'none':'block';
  $('pEmail').textContent=session.email||'Belum diisi';
  $('pEmailEdit').textContent=session.email?'Ubah':'Isi';
  editEmail(false);
  renderPwForm();
  if(!isAdmin()) $('pTotal').textContent=myReports().length;
}

/* Akun Google yang belum punya kata sandi sendiri: kolom "kata sandi lama" disembunyikan
   dan judulnya menjadi "Buat kata sandi", karena sandinya diisi acak oleh sistem. */
function renderPwForm(){
  const pertamaKali=!!(session&&session.mustSetPassword);
  const oldField=$('pwOld').closest('.field');
  if(oldField) oldField.style.display=pertamaKali?'none':'';
  const judul=$('pwTitle'); if(judul) judul.textContent=pertamaKali?'Buat kata sandi':'Ubah kata sandi';
  const tombol=$('pwSubmit'); if(tombol) tombol.textContent=pertamaKali?'Buat kata sandi':'Simpan kata sandi';
  const ket=$('pwGoogleNote');
  if(ket){
    ket.style.display=pertamaKali?'block':'none';
    if(pertamaKali) ket.textContent='Akunmu dibuat lewat Google, jadi belum punya kata sandi. Buat kata sandi di sini kalau sewaktu-waktu ingin masuk tanpa akun Google.';
  }
}

function toggleTheme(){
  const cur=document.documentElement.getAttribute('data-theme')||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
  const next = cur==='dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  try{ localStorage.setItem(DB_THEME, next); }catch(e){}
  $('themeBtn').textContent = next==='dark' ? 'Nonaktifkan' : 'Aktifkan';
}
(function initTheme(){
  let t=null; try{t=localStorage.getItem(DB_THEME);}catch(e){}
  if(t) document.documentElement.setAttribute('data-theme', t);
  const th=t||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
  $('themeBtn').textContent=th==='dark'?'Nonaktifkan':'Aktifkan';
})();

const EYE='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
const EYE_OFF='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.9 10.9 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.1 9.1 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
function togglePw(btn){
  const inp=btn.parentNode.querySelector('input'); const show=inp.type==='password';
  inp.type=show?'text':'password'; btn.innerHTML=show?EYE_OFF:EYE;
  btn.setAttribute('aria-pressed',show); btn.setAttribute('aria-label',show?'Sembunyikan kata sandi':'Tampilkan kata sandi');
}
document.querySelectorAll('.eye').forEach(b=>{ b.innerHTML=EYE; b.addEventListener('mousedown',e=>e.preventDefault()); }); // tombol mata tidak menutup keyboard

/* ---- HP: saat keyboard terbuka, menu bawah disembunyikan dan kolom yang diketik digulir ke tengah ---- */
const TYPING='input:not([type=radio]):not([type=checkbox]):not([type=file]):not([type=button]):not([type=submit]),textarea,select';
document.addEventListener('focusin',e=>{
  if(window.innerWidth>=760 || !e.target.matches || !e.target.matches(TYPING)) return;
  document.body.classList.add('kb-open');
  const t=e.target;
  setTimeout(()=>{ try{ t.scrollIntoView({block:'center',behavior:'smooth'}); }catch(_){} },320);
});
document.addEventListener('focusout',()=>{
  setTimeout(()=>{ const a=document.activeElement; if(!a || !a.matches || !a.matches(TYPING)) document.body.classList.remove('kb-open'); },150);
});

/* ---- Admin ---- */
let admFilter='wait';
function setAdm(f){admFilter=f; $('aChipWait').classList.toggle('on',f==='wait'); $('aChipAll').classList.toggle('on',f==='all'); renderAdmin();}
/* Rentang tanggal dikirim (input tanggal, jam lokal). Bila terbalik, otomatis ditukar. */
function admDateRange(){
  let f=$('admFrom').value, t=$('admTo').value;
  if(f && t && f>t) [f,t]=[t,f];
  return {from:f, to:t, fromTs:f?new Date(f+'T00:00:00').getTime():null, toTs:t?new Date(t+'T23:59:59.999').getTime():null};
}
function clearAdmDates(){ $('admFrom').value=''; $('admTo').value=''; renderAdmin(); }
/* Laporan yang tampil di halaman Persetujuan (tab + tanggal dikirim + pencarian) */
function admVisible(){
  const q=($('admSearch').value||'').trim().toLowerCase(), d=admDateRange();
  const nameOf=r=>{ const u=users.find(x=>x.uid===r.owner); return u?u.name:'Siswa'; };
  return reports.filter(r=>(admFilter==='all'||r.status==='Menunggu')
    && (!d.fromTs||r.ts>=d.fromTs) && (!d.toTs||r.ts<=d.toTs)
    && (!q||(r.title+' '+r.category+' '+r.location+' '+nameOf(r)).toLowerCase().includes(q))).sort((a,b)=>b.ts-a.ts);
}
/* Unduh Excel sesuai filter yang sedang dipakai */
async function exportExcel(){
  const d=admDateRange(), p=new URLSearchParams({status:admFilter});
  if(d.from) p.set('from',d.from);
  if(d.to) p.set('to',d.to);
  const q=$('admSearch').value.trim(); if(q) p.set('q',q);
  const btn=$('btnExport'); btn.disabled=true;
  try{
    const res=await fetch(BASE+'/api/reports-export?'+p,{credentials:'same-origin',headers:{'Accept':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json'}});
    if(!res.ok){ let m='Gagal membuat file Excel ('+res.status+').'; try{ const j=await res.json(); if(j.message) m=j.message; }catch(e){} throw new Error(m); }
    const blob=await res.blob(), cd=res.headers.get('Content-Disposition')||'', m=/filename="?([^";]+)"?/.exec(cd);
    const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=m?m[1]:'laporan-kerusakan.xlsx';
    document.body.appendChild(a); a.click();
    setTimeout(()=>{ URL.revokeObjectURL(a.href); a.remove(); },1500);
    toast('File Excel diunduh.');
  }catch(ex){ toast(ex.message); }
  btn.disabled=false;
}
function renderAdmin(){
  if(!isAdmin()) return;
  $('aWait').textContent=reports.filter(r=>r.status==='Menunggu').length;
  $('aOk').textContent=reports.filter(r=>r.status==='Diproses'||r.status==='Selesai').length;
  $('aNo').textContent=reports.filter(r=>r.status==='Ditolak').length;
  const q=($('admSearch').value||'').trim(), d=admDateRange();
  const list=admVisible();
  const box=$('admList');
  $('admDateClear').hidden=!(d.from||d.to);
  const dl=s=>new Date(s+'T00:00:00').toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'});
  $('admCount').textContent=list.length+' laporan'+(admFilter==='wait'?' menunggu':'')
    +(d.from||d.to?' · dikirim '+(d.from?dl(d.from):'sejak awal')+' s.d. '+(d.to?dl(d.to):'sekarang'):'')
    +'. File Excel berisi laporan yang sama dengan daftar ini.';
  if(!list.length){box.innerHTML='<div class="card"><div class="empty">'+(q||d.from||d.to?'Tidak ada laporan yang cocok dengan filter ini.':'Tidak ada laporan yang menunggu persetujuan.')+'</div></div>'; return;}
  box.innerHTML=list.map(r=>{
    const u=users.find(x=>x.uid===r.owner), nm=u?u.name:'Siswa';
    let act='';
    if(r.status==='Menunggu') act=`<button class="btn-ok" onclick="askDecide('${r.id}','Diproses')">Setujui</button><button class="btn-ghost danger" onclick="askDecide('${r.id}','Ditolak')">Tolak</button>`;
    else if(r.status==='Diproses') act=`<button class="btn-ok" onclick="askDecide('${r.id}','Selesai')">Tandai selesai</button><button class="btn-ghost" onclick="printNota('${r.id}')">${ic('i-print')}Cetak nota</button>`;
    else if(r.status==='Selesai') act=`<button class="btn-ghost" onclick="printNota('${r.id}')">${ic('i-print')}Cetak nota</button>`;
    return `<div class="card adm-item${r.tingkat==='Darurat'&&r.status==='Diproses'?' urgent':''}${freshIds.has(r.id)?' is-new':''}"><div class="adm-head"><div class="adm-who">${avatarFor(u?u.photo:'',nm,u?u.avatarUrl:'')}<div><h4>${escapeHtml(r.title)}</h4><div class="adm-meta"><span class="mono">${lpNo(r.id)}</span> · ${escapeHtml(nm)} · ${fmtDateTime(r.ts)}</div><div class="adm-meta">${escapeHtml(r.location)} · ${escapeHtml(r.category)}</div></div></div><span class="badge ${r.status}">${r.status}</span></div>
    <div class="adm-sev">${sevHtml(r.tingkat)}${r.map?`<a href="#" class="map-link" onclick="showOnMap('${r.id}');return false;">${ic('i-pin')}Lihat di denah</a>`:''}</div>
    <p>${escapeHtml(r.desc)}</p>${r.hasPhoto?`<img src="${BASE}/api/reports/${r.id}/photo" loading="lazy" alt="Foto laporan">`:''}${act?`<div class="adm-actions">${act}</div>`:''}</div>`;
  }).join('');
}
/* Admin memutuskan laporan, boleh disertai catatan untuk pelapor (mis. alasan penolakan).
   Saat menyetujui, admin menentukan tingkat kerusakan dari hasil pengecekan. */
const DECIDE={
  Diproses:{title:'Setujui laporan?',label:'Catatan untuk pelapor (opsional)',btn:'Setujui',cls:'btn-ok',sev:'Tingkat kerusakan (hasil pengecekan, wajib)'},
  Ditolak:{title:'Tolak laporan?',label:'Alasan penolakan (opsional, akan dilihat pelapor)',btn:'Tolak',cls:'btn-danger'},
  Selesai:{title:'Tandai selesai?',label:'Catatan perbaikan (opsional)',btn:'Tandai selesai',cls:'btn-ok',sev:'Tingkat kerusakan (ubah bila perlu)'}
};
let decideJob=null;
function askDecide(id,status){
  const r=reports.find(x=>String(x.id)===String(id)), c=DECIDE[status];
  if(!r||!c||!isAdmin()) return;
  decideJob={id:id,status:status};
  $('dmTitle').textContent=c.title;
  $('dmText').textContent='Laporan: "'+r.title+'" ('+lpNo(r.id)+')';
  $('dmLabel').textContent=c.label;
  $('dmSevWrap').style.display=c.sev?'block':'none';
  if(c.sev){
    $('dmSevLbl').textContent=c.sev;
    document.querySelectorAll('input[name="dmTingkat"]').forEach(i=>{ i.checked=i.value===r.tingkat; });
    updateDecideSev();
  }
  const yes=$('dmYes'); yes.textContent=c.btn; yes.className=c.cls;
  const note=$('dmNote'); note.value='';
  $('decideModal').classList.add('on');
}
function closeDecide(){ decideJob=null; $('decideModal').classList.remove('on'); }
async function doDecide(){
  const job=decideJob; if(!job) return;
  const tingkat=(document.querySelector('input[name="dmTingkat"]:checked')||{}).value||null;
  if(DECIDE[job.status].sev && !tingkat){ const h=$('dmSevHint'); h.className='sev-hint Darurat'; h.textContent='Pilih tingkat kerusakan dulu.'; return; }
  const note=$('dmNote').value.trim();
  closeDecide();
  await decide(job.id,job.status,note,job.status==='Ditolak'?null:tingkat);
}
async function decide(id,status,note,tingkat){
  if(!isAdmin()) return;
  try{
    await api('PATCH','/api/reports/'+id+'/status',{status:status,tanggapan:note||null,tingkat:tingkat||null});
    toast(status==='Ditolak'?'Laporan ditolak.':status==='Selesai'?'Laporan ditandai selesai.':'Laporan disetujui. Nota bisa dicetak.');
    await loadData(); renderAdmin();
  }catch(ex){ toast(ex.message); }
}

/* ---- Denah sekolah: siswa memilih titik lokasi, admin melihat tanda merah ---- */
let mapPick=null, mapDraft=null;   // titik terpilih: {x, y, room} dalam persen dari ukuran gambar
const mapEl=id=>document.getElementById(id);
function roomAt(x,y){ return DENAH_ROOMS.find(r=>x>=r.x&&x<=r.x+r.w&&y>=r.y&&y<=r.y+r.h)||null; }
// Ruangan terdekat dari titik di luar ruangan (lorong, halaman). Jarak dihitung dengan
// perbandingan tinggi:lebar gambar denah agar persen di sumbu x dan y setara.
const DENAH_RATIO=1296/934;
function nearestRoom(x,y){
  let best=null, bestD=Infinity;
  for(const r of DENAH_ROOMS){
    const dx=Math.max(r.x-x,0,x-(r.x+r.w)), dy=Math.max(r.y-y,0,y-(r.y+r.h))*DENAH_RATIO, d=dx*dx+dy*dy;
    if(d<bestD){ bestD=d; best=r; }
  }
  return best;
}
// Nama lokasi untuk kolom Lokasi: nama ruangan, atau "Dekat <ruangan>" bila titik di luar ruangan.
function placeName(x,y){ const r=roomAt(x,y); if(r) return r.n; const n=nearestRoom(x,y); return n?'Dekat '+n.n:'Titik di denah'; }
function pinHtml(p,title,cls,id){ return `<span class="pin ${cls||''}" style="left:${p.x}%;top:${p.y}%" title="${escapeHtml(title||'')}"${id!==undefined?` data-id="${id}"`:''}></span>`; }
function showHl(room){
  const hl=mapEl('mapHl');
  if(!room){ hl.style.display='none'; return; }
  hl.style.cssText=`display:block;left:${room.x}%;top:${room.y}%;width:${room.w}%;height:${room.h}%`;
}
function setMapZoom(on){ mapEl('mapCanvas').classList.toggle('zoom',on); mapEl('mmZoom').textContent=on?'Perkecil':'Perbesar'; }
function toggleMapZoom(){ setMapZoom(!mapEl('mapCanvas').classList.contains('zoom')); centerPin(); }
function centerPin(){ const p=mapEl('mapPins').querySelector('.pin.pulse')||mapEl('mapPins').querySelector('.pin'); if(p) setTimeout(()=>p.scrollIntoView({block:'center',inline:'center'}),30); }
function openMapModal(title,text,actions){
  mapEl('mmTitle').textContent=title; mapEl('mmText').textContent=text;
  mapEl('mmActions').innerHTML=actions;
  mapEl('mapPins').innerHTML=''; showHl(null);
  setMapZoom(window.innerWidth<760);   // di HP denah langsung diperbesar agar ruangan mudah diketuk
  mapEl('mapModal').classList.add('on');
}
function closeMap(){
  mapEl('mapModal').classList.remove('on');
  const c=mapEl('mapCanvas'); c.onclick=null; c.classList.remove('picking'); mapEl('mapPins').onclick=null;
}

/* Siswa: pilih titik saat membuat laporan */
function openMapPicker(){
  mapDraft=mapPick?Object.assign({},mapPick):null;
  openMapModal('Pilih lokasi di denah','',
    '<button class="btn-ghost" onclick="closeMap()">Batal</button><button class="btn-primary" id="mmUse" style="margin:0" onclick="useMapPick()">Gunakan lokasi ini</button>');
  const c=mapEl('mapCanvas'); c.classList.add('picking');
  c.onclick=e=>{
    const b=c.getBoundingClientRect(), k=v=>Math.min(100,Math.max(0,Math.round(v*10000)/100));
    const x=k((e.clientX-b.left)/b.width), y=k((e.clientY-b.top)/b.height);
    mapDraft={x:x,y:y,room:roomAt(x,y),name:placeName(x,y)};
    renderDraft();
  };
  renderDraft(); centerPin();
}
function renderDraft(){
  const d=mapDraft;
  mapEl('mapPins').innerHTML=d?pinHtml(d,d.name,'pulse'):'';
  showHl(d&&d.room);
  mapEl('mmText').textContent=!d?'Ketuk ruangan tempat kerusakan berada. Tanda merah akan muncul di titik yang kamu pilih.'
    :'Dipilih: '+d.name;
  const use=mapEl('mmUse'); if(use) use.disabled=!d;
}
function useMapPick(){
  if(!mapDraft) return;
  mapPick=mapDraft; closeMap();
  mapEl('fLocation').value=mapPick.name;   // kolom Lokasi terisi otomatis, tidak perlu diketik
  renderMapInfo();
}
function clearMapPick(){ mapPick=null; renderMapInfo(); }
function renderMapInfo(){
  const el=mapEl('fMapInfo'); if(!el) return;
  if(!mapPick){ el.style.display='none'; return; }
  el.innerHTML=ic('i-pin')+' Titik di denah: <strong>'+escapeHtml(mapPick.name)+'</strong> · <a href="#" onclick="openMapPicker();return false;">Ubah</a> · <a href="#" onclick="clearMapPick();return false;">Hapus</a>';
  el.style.display='block';
}

/* Siswa & admin: lihat letak satu laporan */
function showOnMap(id){
  const r=reports.find(x=>String(x.id)===String(id));
  if(!r||!r.map) return;
  const room=roomAt(r.map.x,r.map.y);
  openMapModal(r.title,'Lokasi: '+r.location+(room&&room.n!==r.location?' (di denah: '+room.n+')':''),'<button class="btn-primary" style="margin:0" onclick="closeMap()">Tutup</button>');
  mapEl('mapPins').innerHTML=pinHtml(r.map,r.location,'big pulse');
  showHl(room); centerPin();
}

/* Admin: semua laporan yang sedang tampil, sekaligus di satu denah */
function showAllOnMap(){
  if(!isAdmin()) return;
  const list=admVisible().filter(r=>r.map);
  if(!list.length){ toast('Belum ada laporan dengan titik di denah pada daftar ini.'); return; }
  const done=s=>s==='Selesai'||s==='Ditolak';
  openMapModal('Denah laporan',list.length+' laporan. Merah = belum selesai, abu-abu = selesai/ditolak. Ketuk tanda untuk melihat judulnya.',
    '<button class="btn-primary" style="margin:0" onclick="closeMap()">Tutup</button>');
  // Laporan yang belum selesai digambar terakhir supaya tanda merahnya di atas.
  mapEl('mapPins').innerHTML=list.slice().sort((a,b)=>done(b.status)-done(a.status))
    .map(r=>pinHtml(r.map,r.title+' · '+r.location+' ('+r.status+')',done(r.status)?'done':'',r.id)).join('');
  mapEl('mapPins').onclick=e=>{
    const r=reports.find(x=>String(x.id)===e.target.dataset.id);
    if(r) toast(r.title+' · '+r.location+' ('+r.status+')');
  };
}

/* ---- Foto profil ---- */
const AV_COLORS=['#17587A','#2C6B44','#9A5200','#5B4B8A','#8E3B2F','#3F6B73'];
/* Foto profil yang sedang dipakai: unggahan sendiri lebih diutamakan, lalu foto Google. */
function getPP(){ return safeImg(session&&session.photo)||safeAvatarUrl(session&&session.avatarUrl)||null; }
function paintAvatar(el){
  const pp=getPP();
  if(pp){ el.textContent=''; el.style.backgroundColor=''; el.style.backgroundImage='url("'+pp+'")'; }
  else{
    const n=(session.name||'?').trim(); let h=0; for(const c of n) h+=c.charCodeAt(0);
    el.style.backgroundImage=''; el.style.backgroundColor=AV_COLORS[h%AV_COLORS.length]; el.textContent=n.charAt(0).toUpperCase();
  }
}
function avatarFor(photo,name,avatarUrl){
  const pp=safeImg(photo)||safeAvatarUrl(avatarUrl);
  const n=(name||'?').trim(); let h=0; for(const c of n) h+=c.charCodeAt(0);
  if(pp) return `<div class="avatar" style="background-image:url('${pp}')"></div>`;
  return `<div class="avatar" style="background-color:${AV_COLORS[h%AV_COLORS.length]}">${escapeHtml(n.charAt(0).toUpperCase())}</div>`;
}
function refreshAvatars(){
  if(!session) return;
  paintAvatar($('railAv')); paintAvatar($('ppBig'));
  $('ppName').textContent=session.name;
  $('ppDel').style.display=getPP()?'inline-block':'none';
}
async function setPhoto(e){
  const file=e.target.files[0]; e.target.value='';
  if(!file) return;
  if(!file.type.startsWith('image/')){ toast('File harus berupa gambar.'); return; }
  try{
    const photo=await resizeImage(file,256,.85,true);
    const d=await api('PUT','/api/profile/photo',{photo:photo});
    session=d.user; refreshAvatars(); toast('Foto profil diperbarui.');
  }catch(ex){ toast(ex.message); }
}
async function delPhoto(){
  try{ const d=await api('DELETE','/api/profile/photo'); session=d.user; refreshAvatars(); toast('Foto profil dihapus.'); }
  catch(ex){ toast(ex.message); }
}

/* ---- Ubah nama (siswa dan admin) ---- */
function editName(on){
  $('nameForm').style.display=on?'block':'none';
  $('ppName').style.display=on?'none':'block';
  $('ppEdit').style.display=on?'none':'inline-block';
  if(on){ const i=$('nameInput'); i.value=session.name; i.focus(); i.select(); }
}
async function saveName(e){
  e.preventDefault();
  const n=$('nameInput').value.trim().replace(/\s+/g,' ');
  if(n.length<2){ toast('Nama minimal 2 karakter.'); return false; }
  try{
    const d=await api('PATCH','/api/profile/name',{name:n});
    session=d.user;
    $('railName').textContent=session.name;
    $('greet').textContent='Halo, '+session.name.split(' ')[0];
    renderProfile();
    toast('Nama berhasil diubah.');
  }catch(ex){ toast(ex.message); }
  return false;
}

/* ---- Hapus laporan sendiri (kecuali yang sedang diperbaiki) ---- */
const NO_DELETE_MSG='Laporan sedang diperbaiki petugas, jadi belum bisa dihapus. Kamu bisa menghapusnya setelah statusnya Selesai.';
let removeId=null;
function askRemove(id){
  const r=reports.find(x=>String(x.id)===String(id));
  if(!r) return;
  if(r.status==='Diproses'){ toast(NO_DELETE_MSG); return; }
  removeId=id;
  $('rmvText').textContent=r.status==='Menunggu'
    ? 'Laporan "'+r.title+'" belum ditinjau admin. Kalau dihapus, laporan ini tidak akan sampai ke petugas.'
    : 'Laporan "'+r.title+'" akan dihapus dari riwayatmu dan dari Beranda. Tindakan ini tidak bisa dibatalkan.';
  $('removeModal').classList.add('on');
}
function closeRemove(){ removeId=null; $('removeModal').classList.remove('on'); }
async function doRemove(){
  const id=removeId; closeRemove();
  if(id===null) return;
  try{ await api('DELETE','/api/reports/'+id); toast('Laporan dihapus.'); }
  catch(ex){ toast(ex.message); }
  go('mine');
}

/* ---- Email (untuk lupa kata sandi) ---- */
function editEmail(on){
  $('emailForm').style.display=on?'block':'none';
  if(on){ const i=$('emailInput'); i.value=session.email||''; i.focus(); }
}
async function saveEmail(e){
  e.preventDefault();
  const v=$('emailInput').value.trim();
  if(!validEmail(v)){ toast('Alamat email tidak valid. Contoh: nama@gmail.com.'); return false; }
  try{
    const d=await api('PATCH','/api/profile/email',{email:v});
    session=d.user; renderProfile(); toast('Email disimpan.');
  }catch(ex){ toast(ex.message); }
  return false;
}

/* ---- Ubah kata sandi + lupa kata sandi lama ---- */
async function savePassword(e){
  e.preventDefault();
  const err=$('errPw'), show=m=>{ err.textContent=m; err.style.display='block'; };
  const old=$('pwOld').value, nw=$('pwNew').value, nw2=$('pwNew2').value;
  // Akun Google yang belum pernah membuat kata sandi: kolom "kata sandi lama" disembunyikan.
  const pertamaKali=!!(session&&session.mustSetPassword);
  if((!pertamaKali&&!old)||!nw||!nw2){ show('Semua kolom wajib diisi.'); return false; }
  if(nw.length<6){ show('Kata sandi baru minimal 6 karakter.'); return false; }
  if(nw!==nw2){ show('Konfirmasi kata sandi tidak sama.'); return false; }
  try{
    const d=await api('PATCH','/api/profile/password',{current_password:pertamaKali?'':old,password:nw,password_confirmation:nw2});
    if(d&&d.user){ session=d.user; }
    err.style.display='none'; $('okPw').style.display='none'; $('pwForm').reset();
    document.activeElement && document.activeElement.blur && document.activeElement.blur();
    renderProfile();
    toast(pertamaKali?'Kata sandi berhasil dibuat. Sekarang kamu juga bisa masuk tanpa Google.':'Kata sandi berhasil diubah.');
  }catch(ex){ show(ex.message); }
  return false;
}
async function forgotOldPw(){
  const ok=$('okPw'), link=$('forgotOldLink');
  link.style.pointerEvents='none';
  try{
    const d=await api('POST','/api/profile/forgot-password');
    ok.className='note'+(d.sent?'':' warn'); ok.textContent=d.message;
  }catch(ex){ ok.className='note warn'; ok.textContent=ex.message; }
  ok.style.display='block'; link.style.pointerEvents='';
}

/* ---- Admin: email pengirim untuk lupa kata sandi ---- */
function fillMail(s){
  $('mUser').value=s.username||''; $('mFrom').value=s.from_name||'Laporin';
  $('mHost').value=s.host||'smtp.gmail.com'; $('mPort').value=s.port||587; $('mPass').value='';
  $('mPass').placeholder=s.hasPassword?'•••••••• tersimpan (kosongkan bila tidak diganti)':'abcd efgh ijkl mnop';
  const st=$('mailStatus'), when=iso=>iso?new Date(iso).toLocaleString('id-ID',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}):'';
  const from=' dari <b>'+escapeHtml(s.username)+'</b>';
  if(s.status==='ok'){
    st.className='mail-status on';
    st.innerHTML='<b>Aktif.</b> Kata sandi sementara dikirim otomatis'+from+'.'+(s.lastSentAt?' Terakhir berhasil mengirim '+when(s.lastSentAt)+'.':' Login ke Gmail berhasil diperiksa '+when(s.verifiedAt)+'.');
  }else if(s.status==='error'){
    st.className='mail-status bad';
    st.innerHTML='<b>Bermasalah.</b> Pengiriman terakhir'+from+' gagal ('+when(s.lastErrorAt)+'): '+escapeHtml(s.lastError)+' Siswa yang lupa kata sandi sementara diteruskan ke menu Pengguna.';
  }else if(s.status==='unverified'){
    st.className='mail-status off';
    st.innerHTML='<b>Belum diperiksa.</b> Email'+from+' tersimpan tetapi belum pernah dicoba. Tekan <b>Simpan</b> untuk memeriksa login ke Gmail, lalu <b>Kirim uji</b>.';
  }else{
    st.className='mail-status off';
    st.innerHTML='<b>Belum aktif.</b> Email tidak bisa dikirim, jadi siswa yang lupa kata sandi diteruskan ke menu Pengguna dan admin memberikan kata sandi baru secara langsung.';
  }
  $('mailOff').style.display=s.active?'inline-flex':'none';
  if(!$('mTo').value) $('mTo').value=s.username||'';
}
async function loadMail(){ try{ fillMail(await api('GET','/api/settings/mail')); }catch(e){} }
async function saveMail(e){
  e.preventDefault();
  const err=$('errMail'); err.style.display='none';
  const body={host:$('mHost').value.trim(),port:parseInt($('mPort').value,10)||587,username:$('mUser').value.trim(),password:$('mPass').value||null,from_name:$('mFrom').value.trim()||null};
  if(!validEmail(body.username)){ err.textContent='Isi alamat email pengirim yang valid.'; err.style.display='block'; return false; }
  if(body.password && body.password.replace(/\s+/g,'').length!==16 && /gmail\.com$/i.test(body.username)){
    err.textContent='Sandi Aplikasi Google selalu 16 huruf (contoh: abcd efgh ijkl mnop). Yang kamu isi '+body.password.replace(/\s+/g,'').length+' karakter; mungkin itu sandi Gmail biasa.'; err.style.display='block'; return false;
  }
  const btn=e.target.querySelector('.btn-primary'); btn.disabled=true; btn.textContent='Memeriksa login Gmail…';
  try{ const d=await api('PUT','/api/settings/mail',body); fillMail(d); toast(d.message||'Email pengirim disimpan.'); }
  catch(ex){ err.textContent=ex.message; err.style.display='block'; }
  btn.disabled=false; btn.textContent='Simpan';
  return false;
}
async function testMail(){
  const n=$('mailTestNote'), to=$('mTo').value.trim();
  if(!validEmail(to)){ n.className='note warn'; n.textContent='Isi alamat email tujuan yang valid.'; n.style.display='block'; return; }
  n.className='note'; n.textContent='Mengirim email uji…'; n.style.display='block';
  try{ const d=await api('POST','/api/settings/mail/test',{to:to}); n.className='note'; n.textContent=d.message; }
  catch(ex){ n.className='note warn'; n.textContent=ex.message; }
  loadMail();
}
async function disableMail(){
  try{ fillMail(await api('DELETE','/api/settings/mail')); toast('Pengiriman email otomatis dimatikan.'); }catch(ex){ toast(ex.message); }
}

async function loadGoogle(){
  try{ fillGoogle(await api('GET','/api/settings/google')); }catch(e){}
}
async function saveGoogle(e){
  e.preventDefault();
  const err=$('errGoogle'); err.style.display='none';
  const client=$('gClient').value.trim(), secret=$('gSecret').value.trim()||null;
  if(!client){ err.textContent='Isi Client ID.'; err.style.display='block'; return false; }
  if(!secret){
    // biarkan null -> controller akan pakai secret tersimpan
  }
  const btn=e.target.querySelector('.btn-primary'); btn.disabled=true; btn.textContent='Memverifikasi kredensial…';
  try{
    const d=await api('PUT','/api/settings/google',{client_id:client,client_secret:secret||''});
    fillGoogle(d);
    toast(d.message||'Kredensial Google disimpan.');
  }catch(ex){ err.textContent=ex.message; err.style.display='block'; }
  btn.disabled=false; btn.textContent='Simpan';
  return false;
}
async function disableGoogle(){
  try{ fillGoogle(await api('DELETE','/api/settings/google')); toast('Masuk dengan Google dimatikan.'); }catch(ex){ toast(ex.message); }
}
function fillGoogle(s){
  const st=s.status||'off';
  const stBox=$('googleStatus');
  if(stBox){
    stBox.className='mail-status '+st;
    const lab={'off':'Belum diaktifkan','ok':'Aktif ✓','error':'Bermasalah','unverified':'Belum diverifikasi'};
    stBox.textContent='Status: '+lab[st];
  }
  const c=s.client_id||'';
  if(c){ $('gClient').value=c; }
}

/* ---- Admin: daftar pengguna + reset kata sandi ---- */
function renderAdmUsers(){
  if(!isAdmin()) return;
  const q=($('usrSearch').value||'').trim().toLowerCase();
  const list=users.filter(u=>!q||(u.name+' '+u.user+' '+(u.email||'')).toLowerCase().includes(q));
  const box=$('admUsers');
  if(!users.length){ box.innerHTML='<div class="card"><div class="empty">Belum ada siswa yang mendaftar.</div></div>'; return; }
  if(!list.length){ box.innerHTML='<div class="card"><div class="empty">Tidak ada pengguna yang cocok.</div></div>'; return; }
  box.innerHTML=list.map(u=>`<div class="card usr-row"><div class="adm-who">${avatarFor(u.photo,u.name,u.avatarUrl)}<div><h4>${escapeHtml(u.name)}</h4><div class="adm-meta">@${escapeHtml(u.user)} · ${u.count||0} laporan</div><div class="adm-meta usr-email">${u.email?`Email: <a class="map-link" href="mailto:${escapeHtml(u.email)}">${escapeHtml(u.email)}</a>`:'Email: belum diisi'}</div>${u.resetRequested?'<div><span class="tag-warn">Minta reset kata sandi</span></div>':''}</div></div><div class="usr-actions"><button class="btn-ghost sm${u.resetRequested?' urgent':''}" onclick="askReset(${u.uid})">Reset sandi</button><button class="btn-ghost sm danger" onclick="askDelete(${u.uid})">Hapus akun</button></div></div>`).join('');
}
/* Admin: hapus akun siswa (laporannya ikut terhapus) */
let deleteUser=null;
function askDelete(uid){
  const u=users.find(x=>x.uid===uid); if(!u) return;
  deleteUser=u;
  const n=u.count||0;
  $('rmTitle').textContent='Hapus akun '+u.name+'?';
  $('rmText').innerHTML='Akun <strong>@'+escapeHtml(u.user)+'</strong> akan dihapus permanen'
    +(n?' beserta <strong>'+n+' laporan</strong> yang pernah dikirimnya (juga hilang dari Beranda)':'')
    +'. Siswa ini tidak bisa masuk lagi dan harus mendaftar ulang. Tindakan ini tidak bisa dibatalkan.';
  $('rmActions').innerHTML='<button class="btn-ghost" onclick="closeReset()">Batal</button><button class="btn-danger" onclick="doDelete()">Ya, hapus akun</button>';
  $('resetModal').classList.add('on');
}
async function doDelete(){
  const u=deleteUser; closeReset();
  if(!u) return;
  try{
    const d=await api('DELETE','/api/users/'+u.uid);
    toast(d.message);
    await loadData(); renderAdmUsers();
  }catch(ex){ toast(ex.message); }
}
let resetUser=null;
function askReset(uid){
  const u=users.find(x=>x.uid===uid); if(!u) return;
  resetUser=u;
  $('rmTitle').textContent='Reset kata sandi?';
  $('rmText').textContent='Kata sandi '+u.name+' akan diganti dengan kata sandi baru'+(u.email?' yang langsung dikirim ke '+u.email:'')+'. Siswa yang sedang masuk akan otomatis keluar.';
  $('rmActions').innerHTML='<button class="btn-ghost" onclick="closeReset()">Batal</button><button class="btn-danger" onclick="doReset()">Ya, reset</button>';
  $('resetModal').classList.add('on');
}
function closeReset(){ resetUser=null; deleteUser=null; $('resetModal').classList.remove('on'); }
function copyTmpPw(){
  const t=($('tmpPw')||{}).textContent||'';
  const done=()=>toast('Kata sandi disalin.');
  try{ navigator.clipboard.writeText(t).then(done,()=>toast('Pilih lalu salin kata sandinya secara manual.')); }catch(e){ toast('Pilih lalu salin kata sandinya secara manual.'); }
}
async function doReset(){
  const u=resetUser; if(!u) return;
  try{
    const d=await api('POST','/api/users/'+u.uid+'/reset-password');
    $('rmTitle').textContent='Kata sandi sudah direset';
    const pw='<span class="tmp-pw" id="tmpPw">'+escapeHtml(d.password)+'</span>';
    const nm='<strong>'+escapeHtml(u.name)+'</strong>';
    // Kata sandi selalu sudah diganti. Email hanya cara menyampaikannya ke siswa.
    $('rmText').innerHTML=d.sent
      ? 'Kata sandi baru untuk '+nm+' sudah dikirim otomatis ke '+escapeHtml(u.email)+'. Kata sandi lamanya tidak berlaku lagi.'+pw
      : !u.email
        ? nm+' belum mengisi email, jadi kata sandi tidak bisa dikirim. Berikan kata sandi di bawah langsung, lalu minta ia menggantinya di menu Profil.'+pw
        : d.mailError
          ? 'Kata sandi '+nm+' sudah diganti, tetapi <b>email otomatis gagal terkirim</b>: '+escapeHtml(d.mailError)+' Berikan kata sandi di bawah langsung ke siswa, atau kirim lewat aplikasi email.'+pw
          : 'Kata sandi '+nm+' sudah diganti. Email pengirim belum diatur di Profil, jadi email tidak terkirim otomatis. Berikan kata sandi di bawah langsung ke siswa, atau kirim lewat aplikasi email.'+pw;
    $('rmActions').innerHTML='<button class="btn-ghost" onclick="copyTmpPw()">Salin kata sandi</button>'
      +(d.mailLink?'<a class="btn-ghost mail-btn" href="'+escapeHtml(d.mailLink)+'">'+ic('i-mail')+'Buka aplikasi email</a>':'')
      +'<button class="btn-primary" onclick="closeReset()">Selesai</button>';
    await loadData(); renderAdmUsers();
  }catch(ex){ closeReset(); toast(ex.message); }
}
document.addEventListener('keydown',e=>{
  if(e.key!=='Escape') return;
  closeDecide(); closeMap(); closeRemove(); closeReset(); closeFeed();
  if($('auth').classList.contains('on')) closeAuth();
});

/* ---- Cetak nota persetujuan (admin) ---- */
function printNota(id){
  if(!isAdmin()) return;
  const r=reports.find(x=>String(x.id)===String(id));
  if(!r || !r.approvedAt){ toast('Nota hanya untuk laporan yang sudah disetujui.'); return; }
  const u=users.find(x=>x.uid===r.owner), nm=u?u.name:'Siswa', un=u?'@'+u.user:'';
  const ap=new Date(r.approvedAt), fmt=d=>d.toLocaleDateString('id-ID',{day:'numeric',month:'long',year:'numeric'});
  const no='NP/'+ap.getFullYear()+'/'+String(ap.getMonth()+1).padStart(2,'0')+'/'+String(r.id).padStart(4,'0');
  const status=r.status==='Selesai'?'DISETUJUI — SUDAH DIPERBAIKI':'DISETUJUI — DALAM PERBAIKAN';
  const row=(k,v)=>`<tr><td>${k}</td><td>${v}</td></tr>`;
  const el=$('notaPrint');
  el.innerHTML=`<div class="nota">
    <div class="nota-kop"><img src="${BASE}/images/logo-icon.png" alt=""><div><h1>${escapeHtml(SCHOOL.name.toUpperCase())}</h1><p>Laporan Kerusakan Fasilitas Sekolah — Aplikasi Laporin</p></div></div>
    <h2>NOTA PERSETUJUAN LAPORAN</h2>
    <div class="nota-no">Nomor: ${no}</div>
    <table>
      ${row('Nama pelapor',escapeHtml(nm)+' ('+escapeHtml(un)+')')}
      ${row('Tanggal laporan',fmt(new Date(r.ts)))}
      ${row('Judul laporan',escapeHtml(r.title))}
      ${row('Kategori sarana',escapeHtml(r.category))}
      ${row('Lokasi',escapeHtml(r.location))}
      ${row('Tingkat kerusakan',escapeHtml(r.tingkat||'Belum dinilai'))}
      ${row('Deskripsi',escapeHtml(r.desc))}
      ${row('Status',`<span class="nota-stamp">${status}</span>`)}
      ${row('Tanggal persetujuan',fmt(ap))}
    </table>
    <p class="nota-ket">Laporan di atas telah diperiksa dan disetujui untuk ditindaklanjuti oleh petugas sarana dan prasarana sekolah.</p>
    <div class="nota-ttd">
      <div>&nbsp;<br>Pelapor,<div class="sp"></div>( ${escapeHtml(nm)} )</div>
      <div>${SCHOOL.city?escapeHtml(SCHOOL.city)+', ':''}${fmt(ap)}<br>Disetujui oleh,<div class="sp"></div>( ${escapeHtml(session.name)} )</div>
    </div>
    <div class="nota-foot">Dicetak dari aplikasi Laporin pada ${fmt(new Date())}</div>
  </div>`;
  const old=document.title; document.title='Nota '+no.replace(/\//g,'-');
  window.addEventListener('afterprint',()=>{ document.title=old; },{once:true});
  const img=el.querySelector('img'), run=()=>window.print();
  if(img && !img.complete){ img.onload=run; img.onerror=run; } else run();
}

/* ---- Mulai: semua orang langsung ke Beranda ---- */
if(session){ enterApp('home'); }
else{
  go('home');
}
startPolling();

/* ---- Pesan hasil "Masuk dengan Google" ----
   Controller mengalihkan kembali ke "/" dengan parameter ?google=... .
   Parameter dibaca lalu dibuang dari URL agar tidak muncul lagi saat halaman dimuat ulang. */
(function handleGoogleResult(){
  const q=new URLSearchParams(location.search); const r=q.get('google');
  if(!r) return;
  q.delete('google');
  history.replaceState(null,'',location.pathname+(q.toString()?'?'+q:''));
  if(r==='berhasil'){ toast('Berhasil masuk dengan akun Google.'); return; }
  const pesan={
    'belum-diatur':'Masuk dengan Google belum diaktifkan oleh admin. Pakai username dan kata sandi dulu.',
    'dibatalkan':'Masuk dengan Google dibatalkan.',
    'diblokir':'Akun ini diblokir oleh admin. Hubungi admin sekolah.',
    'sesi-hilang':'Proses masuk terputus karena sesi kedaluwarsa. Coba tekan tombol Google sekali lagi, dan pastikan cookie tidak diblokir.',
    'gagal':'Masuk dengan Google gagal. Coba lagi atau pakai username dan kata sandi.'
  };
  openAuth('login', pesan[r] || pesan['gagal']);
})();
