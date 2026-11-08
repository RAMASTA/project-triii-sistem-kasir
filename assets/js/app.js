(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var root = document.documentElement;
  function icons() { if (window.lucide) lucide.createIcons(); }
  icons();

  // Dark mode
  var tb = $('#themeBtn');
  if (tb) tb.addEventListener('click', function () {
    var n = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', n);
    try { localStorage.setItem('mt-theme', n); } catch (e) {}
    applyChartTheme();
  });

  // Sidebar mobile
  var sb = $('#sidebar'), ov = $('#overlay'), mb = $('#menuBtn');
  function toggleMenu(open) { sb.classList.toggle('open', open); ov.classList.toggle('open', open); }
  if (mb) { mb.addEventListener('click', function () { toggleMenu(!sb.classList.contains('open')); }); ov.addEventListener('click', function () { toggleMenu(false); }); }

  // Modal CRUD
  var modal = $('#modal'), form = $('#dataForm');
  function openModal(row) {
    form.reset();
    form.elements.id.value = row ? row.id : 0;
    $('#modalTitle').textContent = row ? 'Edit Data' : 'Tambah Data';
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || ['csrf', 'action', 'id', 'photo', 'caption'].indexOf(el.name) > -1) return;
      if (row) el.value = row[el.name] == null ? '' : row[el.name];
      else if (el.dataset.today) el.value = new Date().toISOString().slice(0, 10);
    });
    modal.classList.add('open');
  }
  if (modal) {
    var add = $('#addBtn'); if (add) add.addEventListener('click', function () { openModal(null); });
    document.querySelectorAll('.edit').forEach(function (b) { b.addEventListener('click', function () { openModal(JSON.parse(b.dataset.row)); }); });
    document.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', function () { modal.classList.remove('open'); }); });
    modal.addEventListener('mousedown', function (e) { if (e.target === modal) modal.classList.remove('open'); });
    var df = $('#delForm');
    document.querySelectorAll('.del').forEach(function (b) {
      b.addEventListener('click', function () {
        if (confirm('Hapus data ini? Tindakan tidak dapat dibatalkan.')) { df.elements.id.value = b.dataset.id; df.submit(); }
      });
    });
  }

  // Charts
  var charts = [], D = window.MT_PAGE || {};
  var colors = ['#84CC16', '#14532D', '#0EA5E9', '#F59E0B', '#EF4444', '#8B5CF6'];
  function css(n) { return getComputedStyle(root).getPropertyValue(n).trim(); }
  function applyChartTheme() {
    if (!window.Chart) return;
    Chart.defaults.color = css('--muted'); Chart.defaults.borderColor = css('--line');
    charts.forEach(function (c) { c.update(); });
  }
  function make(id, cfg) {
    var el = document.getElementById(id); if (!el || !window.Chart) return;
    cfg.options = Object.assign({ responsive: true, maintainAspectRatio: false, animation: { duration: 400 } }, cfg.options || {});
    charts.push(new Chart(el, cfg));
  }
  function growthCfg(sets) {
    return { type: 'line', data: { datasets: sets.map(function (s, i) { return { label: s.label, data: s.data, borderColor: colors[i % 6], backgroundColor: colors[i % 6], tension: .3, pointRadius: 3 }; }) },
      options: { scales: { x: { type: 'linear', title: { display: true, text: 'Umur (hari)' } }, y: { title: { display: true, text: 'Berat sampel (g)' }, beginAtZero: true } } } };
  }
  if (window.Chart) {
    applyChartTheme();
    if (D.growth && D.growth.length) make('growthChart', growthCfg(D.growth));
    if (D.feed && D.feed.labels.length) make('feedChart', { type: 'bar', data: { labels: D.feed.labels, datasets: [{ label: 'Pakan (kg)', data: D.feed.values, backgroundColor: '#84CC16', borderRadius: 6 }] }, options: { plugins: { legend: { display: false } } } });
    if (D.chart && D.chart.type === 'growth') make('pageChart', growthCfg(D.chart.sets));
    if (D.chart && D.chart.type === 'env') make('pageChart', { type: 'line', data: { labels: D.chart.labels, datasets: [
      { label: 'Suhu (°C)', data: D.chart.temp, borderColor: '#EF4444', backgroundColor: '#EF4444', yAxisID: 'y', tension: .3 },
      { label: 'Kelembapan (%)', data: D.chart.hum, borderColor: '#0EA5E9', backgroundColor: '#0EA5E9', yAxisID: 'y1', tension: .3 }] },
      options: { scales: { y: { position: 'left', title: { display: true, text: '°C' } }, y1: { position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '%' } } } } });
    if (D.monthly && D.monthly.labels.length) make('monthlyChart', { type: 'bar', data: { labels: D.monthly.labels, datasets: [
      { label: 'Pakan (kg)', data: D.monthly.feed, backgroundColor: '#14532D', borderRadius: 6 }, { label: 'Panen (kg)', data: D.monthly.harvest, backgroundColor: '#84CC16', borderRadius: 6 }] } });
    if (D.batches && D.batches.labels.length) make('batchChart', { type: 'bar', data: { labels: D.batches.labels, datasets: [
      { label: 'Pakan (kg)', data: D.batches.feed, backgroundColor: '#14532D', borderRadius: 6 }, { label: 'Panen (kg)', data: D.batches.harvest, backgroundColor: '#84CC16', borderRadius: 6 }] } });
  }
})();
