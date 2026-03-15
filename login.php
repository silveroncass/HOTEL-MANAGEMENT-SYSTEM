<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GrandStay — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--green-dark:#1a3028;--green-mid:#2d5016;--green-main:#2e6b3e;--green-pale:#e8f5e9;--green-ghost:#f0f7f1;--gold:#c9a84c;--text-dark:#1a2e1e;--text-mid:#4a5e4f;--text-light:#8aab90}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;background:var(--green-dark);overflow:hidden}
.login-art{flex:1;background:linear-gradient(160deg,#1a3028 0%,#2d5016 40%,#1e4520 100%);display:flex;flex-direction:column;justify-content:center;align-items:center;padding:60px;position:relative;overflow:hidden}
.login-art::before{content:'';position:absolute;width:600px;height:600px;border-radius:50%;border:1px solid rgba(201,168,76,0.15);top:-100px;left:-100px}
.login-art::after{content:'';position:absolute;width:400px;height:400px;border-radius:50%;border:1px solid rgba(255,255,255,0.06);bottom:-80px;right:-80px}
.art-hotel-name{font-family:'Playfair Display',serif;font-size:3.5rem;font-weight:700;color:#fff;line-height:1.1;margin-bottom:16px;position:relative;z-index:1}
.art-hotel-name span{color:var(--gold)}
.art-tagline{font-size:1rem;color:rgba(255,255,255,0.55);letter-spacing:0.15em;text-transform:uppercase;position:relative;z-index:1;margin-bottom:50px}
.art-stats{display:grid;grid-template-columns:1fr 1fr;gap:20px;width:100%;max-width:340px;position:relative;z-index:1}
.art-stat-card{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:20px;backdrop-filter:blur(10px)}
.art-stat-num{font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;color:#fff}
.art-stat-label{font-size:0.75rem;color:rgba(255,255,255,0.5);margin-top:4px}
.art-divider{width:40px;height:2px;background:var(--gold);margin:30px 0;position:relative;z-index:1}
.login-form-wrap{width:480px;background:#fff;display:flex;flex-direction:column;justify-content:center;padding:60px 50px;position:relative}
.login-form-wrap::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--green-main),var(--gold))}
.login-logo{display:flex;align-items:center;gap:12px;margin-bottom:36px}
.login-logo-icon{width:44px;height:44px;background:var(--green-dark);border-radius:12px;display:flex;align-items:center;justify-content:center}
.login-logo-icon i{color:#fff;font-size:1.2rem}
.login-logo-text{font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--green-dark)}
.login-logo-sub{font-size:0.7rem;color:var(--text-light);letter-spacing:0.1em;text-transform:uppercase}
h2.login-title{font-family:'Playfair Display',serif;font-size:2rem;color:var(--text-dark);margin-bottom:6px}
.login-subtitle{color:var(--text-light);font-size:0.9rem;margin-bottom:28px}
.role-tabs{display:flex;gap:8px;margin-bottom:28px;background:var(--green-ghost);padding:5px;border-radius:12px}
.role-tab{flex:1;text-align:center;padding:9px 12px;border-radius:9px;font-size:0.83rem;font-weight:600;cursor:pointer;color:var(--text-mid);transition:all 0.2s;user-select:none}
.role-tab.active{background:var(--green-dark);color:#fff;box-shadow:0 2px 8px rgba(26,48,40,0.2)}
.form-label-custom{font-size:0.8rem;font-weight:600;color:var(--text-mid);letter-spacing:0.05em;text-transform:uppercase;margin-bottom:6px}
.input-wrap{position:relative;margin-bottom:20px}
.input-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--text-light);font-size:1rem;pointer-events:none;z-index:2}
.input-wrap input{width:100%;padding:14px 48px 14px 44px;border:1.5px solid #e0ebe2;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.95rem;color:var(--text-dark);background:var(--green-ghost);transition:all 0.2s;outline:none}
.input-wrap input:focus{border-color:var(--green-main);background:#fff;box-shadow:0 0 0 4px rgba(46,107,62,0.08)}
.toggle-pw{position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-light);z-index:2;background:none;border:none;padding:4px;line-height:1;display:flex;align-items:center}
.toggle-pw:hover{color:var(--green-main)}
.btn-login{width:100%;padding:15px;background:var(--green-dark);color:#fff;border:none;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:600;cursor:pointer;letter-spacing:0.03em;transition:all 0.25s;margin-top:8px;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-login:hover{background:var(--green-main);transform:translateY(-1px);box-shadow:0 8px 24px rgba(46,107,62,0.3)}
.login-footer{margin-top:18px;text-align:center;font-size:0.85rem;color:var(--text-light)}
.login-footer a{color:var(--green-main);text-decoration:none;font-weight:600}
.alert-custom{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:0.875rem;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.alert-success{background:var(--green-pale)!important;border-color:#a7d7b0!important;color:var(--green-main)!important}
@media(max-width:768px){.login-art{display:none}.login-form-wrap{width:100%;padding:40px 30px}}
</style>
</head>
<body>
<div class="login-art">
  <div>
    <div class="art-hotel-name">Grand<span>Stay</span></div>
    <div class="art-tagline">Hotel Management System</div>
    <div class="art-divider"></div>
    <div class="art-stats">
      <div class="art-stat-card"><div class="art-stat-num">9</div><div class="art-stat-label">Total Rooms</div></div>
      <div class="art-stat-card"><div class="art-stat-num">6</div><div class="art-stat-label">Room Types</div></div>
      <div class="art-stat-card"><div class="art-stat-num">3</div><div class="art-stat-label">Access Roles</div></div>
      <div class="art-stat-card"><div class="art-stat-num">24/7</div><div class="art-stat-label">Management</div></div>
    </div>
  </div>
</div>
<div class="login-form-wrap">
  <div class="login-logo">
    <div class="login-logo-icon"><i class="bi bi-building"></i></div>
    <div>
      <div class="login-logo-text">GrandStay</div>
      <div class="login-logo-sub">Hotel Management System</div>
    </div>
  </div>
  <h2 class="login-title">Welcome back</h2>
  <p class="login-subtitle">Sign in to your account to continue</p>
  <div class="role-tabs">
    <div class="role-tab active" onclick="setRole('admin',this)"><i class="bi bi-shield-person me-1"></i>Staff / Admin</div>
    <div class="role-tab" onclick="setRole('user',this)"><i class="bi bi-person me-1"></i>Guest</div>
  </div>
  <div id="alert-box" style="display:none"></div>
  <form id="loginForm" onsubmit="handleLogin(event)">
    <input type="hidden" id="role_input" name="role" value="admin">
    <div>
      <div class="form-label-custom">Username</div>
      <div class="input-wrap">
        <i class="bi bi-person input-icon"></i>
        <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
      </div>
    </div>
    <div>
      <div class="form-label-custom">Password</div>
      <div class="input-wrap">
        <i class="bi bi-lock input-icon"></i>
        <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
        <button type="button" class="toggle-pw" onclick="togglePw()" tabindex="-1"><i class="bi bi-eye" id="pw-eye"></i></button>
      </div>
    </div>
    <button class="btn-login" type="submit" id="loginBtn"><i class="bi bi-box-arrow-in-right"></i> Sign In</button>
  </form>
  <div class="login-footer" id="guest-hint" style="display:none">
    Don't have an account? <a href="user/booking.php">Register on the booking page</a>
  </div>
</div>
<script>
function setRole(role,el){
  document.querySelectorAll('.role-tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('role_input').value=role;
  document.getElementById('guest-hint').style.display=role==='user'?'block':'none';
}
function togglePw(){
  const pw=document.getElementById('password');
  const eye=document.getElementById('pw-eye');
  if(pw.type==='password'){pw.type='text';eye.className='bi bi-eye-slash';}
  else{pw.type='password';eye.className='bi bi-eye';}
}
async function handleLogin(e){
  e.preventDefault();
  const btn=document.getElementById('loginBtn');
  btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Signing in...';
  btn.disabled=true;
  const data=new FormData(document.getElementById('loginForm'));
  const res=await fetch('login_process.php',{method:'POST',body:data});
  const json=await res.json();
  const box=document.getElementById('alert-box');
  if(json.success){
    box.className='alert-custom alert-success';
    box.innerHTML='<i class="bi bi-check-circle"></i> Login successful! Redirecting...';
    box.style.display='flex';
    setTimeout(()=>{window.location.href=json.redirect;},800);
  } else {
    box.className='alert-custom';
    box.innerHTML='<i class="bi bi-exclamation-circle"></i> '+json.message;
    box.style.display='flex';
    btn.innerHTML='<i class="bi bi-box-arrow-in-right"></i> Sign In';
    btn.disabled=false;
  }
}
</script>
</body>
</html>
