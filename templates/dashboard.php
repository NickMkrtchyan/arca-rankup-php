<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArCa Gateway — Dashboard</title>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
:root {
  --bg:#0f1117; --surface:#1a1d27; --surface2:#22263a; --surface3:#2a2f47;
  --border:#2e3248; --accent:#4f6ef7; --accent2:#7c3aed;
  --green:#22c55e; --yellow:#eab308; --red:#ef4444; --orange:#f97316; --cyan:#06b6d4;
  --text:#e2e8f0; --text2:#94a3b8; --text3:#64748b;
  --radius:10px; --font:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:var(--font);font-size:14px;min-height:100vh;}

/* Topbar */
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:0 28px;height:60px;
        display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10;}
.topbar-logo{display:flex;align-items:center;gap:12px;font-weight:700;font-size:17px;}
.logo-badge{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;
            border-radius:8px;padding:4px 10px;font-size:12px;font-weight:700;}
.topbar-right{display:flex;align-items:center;gap:14px;}
.status-dot{width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block;}
.status-dot.loading{background:var(--yellow);animation:pulse 1s infinite;}
.status-label{color:var(--text2);font-size:13px;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.4;}}
.refresh-btn{background:var(--surface2);border:1px solid var(--border);color:var(--text);
             padding:6px 14px;border-radius:8px;font-size:13px;cursor:pointer;transition:background .15s;}
.refresh-btn:hover{background:var(--surface3);}
.refresh-btn:disabled{opacity:.5;cursor:default;}
.spin{animation:spin .8s linear infinite;display:inline-block;}
@keyframes spin{to{transform:rotate(360deg);}}

/* Layout */
.main{max-width:1200px;margin:0 auto;padding:28px 24px;}

/* Stats grid */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:22px 20px;}
.stat-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text3);margin-bottom:10px;}
.stat-value{font-size:28px;font-weight:800;color:var(--text);}
.stat-sub{font-size:12px;color:var(--text3);margin-top:4px;}
.stat-card.accent .stat-value{color:var(--accent);}
.stat-card.green  .stat-value{color:var(--green);}
.stat-card.yellow .stat-value{color:var(--yellow);}
.stat-card.red    .stat-value{color:var(--red);}

/* Config pills */
.config-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:28px;}
.config-pill{background:var(--surface);border:1px solid var(--border);border-radius:20px;
             padding:5px 14px;font-size:12px;color:var(--text2);}
.config-pill span{color:var(--text);font-weight:600;}

/* Table */
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.table-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;
              align-items:center;justify-content:space-between;}
.table-title{font-size:15px;font-weight:700;}
.table-meta{font-size:12px;color:var(--text3);}
table{width:100%;border-collapse:collapse;}
thead th{padding:10px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;
         letter-spacing:.6px;color:var(--text3);background:var(--surface2);border-bottom:1px solid var(--border);}
tbody td{padding:12px 16px;border-bottom:1px solid var(--border);color:var(--text2);font-size:13px;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:var(--surface2);}
td code{font-family:monospace;font-size:12px;background:var(--surface2);padding:2px 6px;
        border-radius:4px;border:1px solid var(--border);color:var(--cyan);}

/* Status badges */
.badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.badge-captured   {background:rgba(34,197,94,.15);   color:var(--green);}
.badge-authorized {background:rgba(79,110,247,.15);   color:var(--accent);}
.badge-pending    {background:rgba(234,179,8,.15);    color:var(--yellow);}
.badge-declined   {background:rgba(239,68,68,.15);    color:var(--red);}
.badge-canceled   {background:rgba(100,116,139,.15);  color:var(--text3);}
.badge-deleted    {background:rgba(100,116,139,.10);  color:var(--text3);}

/* Empty / Skeleton */
.empty{text-align:center;padding:48px;color:var(--text3);font-size:14px;}
.skeleton{background:var(--surface2);border-radius:4px;animation:shimmer 1.4s infinite;}
.skeleton-row td{padding:14px 16px;}
@keyframes shimmer{0%,100%{opacity:.6;}50%{opacity:1;}}
.sk{background:var(--surface3);border-radius:3px;height:14px;display:inline-block;}

/* Pagination */
.pagination{display:flex;align-items:center;gap:8px;padding:12px 20px;border-top:1px solid var(--border);}
.page-btn{background:var(--surface2);border:1px solid var(--border);color:var(--text2);
          padding:4px 10px;border-radius:6px;font-size:12px;cursor:pointer;}
.page-btn:hover{color:var(--text);}
.page-btn:disabled{opacity:.4;cursor:default;}
.page-info{font-size:12px;color:var(--text3);}

/* Filter */
.filter-bar{display:flex;gap:8px;align-items:center;}
.filter-select{background:var(--surface2);border:1px solid var(--border);color:var(--text2);
               padding:5px 10px;border-radius:7px;font-size:12px;cursor:pointer;}
</style>
</head>
<body x-data="dashboard()" x-init="init()">

<!-- Topbar -->
<header class="topbar">
  <div class="topbar-logo">
    <span class="logo-badge">ArCa</span>
    Payment Gateway
  </div>
  <div class="topbar-right">
    <span :class="['status-dot', loading ? 'loading' : '']"></span>
    <span class="status-label" x-text="loading ? 'Refreshing...' : 'Live'"></span>

    <div class="filter-bar">
      <select class="filter-select" x-model="filterStatus" @change="applyFilter()">
        <option value="">All statuses</option>
        <option value="captured">Captured</option>
        <option value="authorized">Authorized</option>
        <option value="pending">Pending</option>
        <option value="declined">Declined</option>
        <option value="canceled">Canceled</option>
      </select>
    </div>

    <button class="refresh-btn" @click="refresh()" :disabled="loading">
      <span :class="loading ? 'spin' : ''">⟳</span>
      Refresh
    </button>
  </div>
</header>

<!-- Main -->
<div class="main">

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card accent">
      <div class="stat-label">Total Orders</div>
      <div class="stat-value" x-text="stats.totals?.total ?? '—'"></div>
      <div class="stat-sub">all time</div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Captured</div>
      <div class="stat-value" x-text="stats.totals?.captured ?? '—'"></div>
      <div class="stat-sub">successful payments</div>
    </div>
    <div class="stat-card yellow">
      <div class="stat-label">Pending</div>
      <div class="stat-value" x-text="stats.totals?.pending ?? '—'"></div>
      <div class="stat-sub">awaiting completion</div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">Expired</div>
      <div class="stat-value" x-text="stats.totals?.expired ?? '—'"></div>
      <div class="stat-sub">auto-purged</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Revenue</div>
      <div class="stat-value" x-text="formatAMD(stats.revenue?.total_amd)"></div>
      <div class="stat-sub">AMD · captured + authorized</div>
    </div>
  </div>

  <!-- Config pills -->
  <div class="config-bar" x-show="cfg">
    <div class="config-pill">Mode: <span x-text="cfg?.arca?.authMode == 1 ? 'PreAuth' : 'Sale'"></span></div>
    <div class="config-pill">Store: <span x-text="cfg?.shopify?.store"></span></div>
    <div class="config-pill">API: <span x-text="cfg?.shopify?.apiVersion"></span></div>
    <div class="config-pill">Currency: <span x-text="cfg?.currency?.shop_currency + ' → AMD'"></span></div>
    <div class="config-pill">Auto-cancel: <span x-text="cfg?.cancelAfterMinutes + ' min'"></span></div>
    <div class="config-pill">Last refresh: <span x-text="lastRefresh"></span></div>
  </div>

  <!-- Transactions table -->
  <div class="table-wrap">
    <div class="table-header">
      <div class="table-title">Recent Transactions</div>
      <div class="table-meta" x-text="'Showing ' + displayed.length + ' of ' + filtered.length"></div>
    </div>

    <table>
      <thead>
        <tr>
          <th>ArCa Order ID</th>
          <th>Shopify Order</th>
          <th>Email</th>
          <th>Amount (AMD)</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <!-- Skeleton loader -->
        <template x-if="loading && !stats.recent">
          <template x-for="i in 6" :key="i">
            <tr class="skeleton-row">
              <td><span class="sk" style="width:160px"></span></td>
              <td><span class="sk" style="width:80px"></span></td>
              <td><span class="sk" style="width:140px"></span></td>
              <td><span class="sk" style="width:70px"></span></td>
              <td><span class="sk" style="width:80px"></span></td>
              <td><span class="sk" style="width:120px"></span></td>
            </tr>
          </template>
        </template>

        <!-- Data rows -->
        <template x-for="tx in displayed" :key="tx.externaltrid">
          <tr>
            <td><code x-text="tx.externaltrid?.slice(0,8) + '…'"></code></td>
            <td x-text="'#' + tx.orderid"></td>
            <td x-text="tx.email || '—'"></td>
            <td x-text="formatAMD(tx.price)"></td>
            <td>
              <span :class="'badge badge-' + tx.trstatus" x-text="tx.trstatus"></span>
            </td>
            <td x-text="formatDate(tx.created)"></td>
          </tr>
        </template>

        <!-- Empty state -->
        <template x-if="!loading && displayed.length === 0">
          <tr>
            <td colspan="6">
              <div class="empty">
                <div style="font-size:32px;margin-bottom:8px">💳</div>
                <div x-text="filterStatus ? 'No transactions with status: ' + filterStatus : 'No transactions yet'"></div>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination" x-show="filtered.length > pageSize">
      <button class="page-btn" @click="prevPage()" :disabled="page === 1">← Prev</button>
      <span class="page-info" x-text="'Page ' + page + ' of ' + totalPages"></span>
      <button class="page-btn" @click="nextPage()" :disabled="page >= totalPages">Next →</button>
    </div>
  </div>

</div><!-- /main -->

<script>
function dashboard() {
  return {
    stats: {},
    cfg: null,
    loading: false,
    lastRefresh: '—',
    filterStatus: '',
    page: 1,
    pageSize: 15,

    get filtered() {
      const rows = this.stats.recent ?? [];
      if (!this.filterStatus) return rows;
      return rows.filter(r => r.trstatus === this.filterStatus);
    },
    get totalPages() {
      return Math.max(1, Math.ceil(this.filtered.length / this.pageSize));
    },
    get displayed() {
      const start = (this.page - 1) * this.pageSize;
      return this.filtered.slice(start, start + this.pageSize);
    },

    async init() {
      await this.refresh();
      setInterval(() => this.refresh(), 30000);
    },

    async refresh() {
      this.loading = true;
      try {
        const [statsRes, cfgRes] = await Promise.all([
          fetch('/api/stats').then(r => r.json()),
          fetch('/api/config').then(r => r.json()),
        ]);
        this.stats = statsRes;
        this.cfg   = cfgRes;
        this.lastRefresh = new Date().toLocaleTimeString();
        this.page = 1;
      } catch (e) {
        console.error('Dashboard refresh failed:', e);
      } finally {
        this.loading = false;
      }
    },

    applyFilter() {
      this.page = 1;
    },

    prevPage() { if (this.page > 1) this.page--; },
    nextPage() { if (this.page < this.totalPages) this.page++; },

    formatAMD(val) {
      if (val == null) return '—';
      return new Intl.NumberFormat('hy-AM', { minimumFractionDigits: 0 }).format(Math.round(val)) + ' ֏';
    },

    formatDate(dt) {
      if (!dt) return '—';
      return new Date(dt).toLocaleString('ru-RU', {
        day: '2-digit', month: '2-digit', year: '2-digit',
        hour: '2-digit', minute: '2-digit',
      });
    },
  };
}
</script>
</body>
</html>
