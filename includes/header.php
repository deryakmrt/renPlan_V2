<?php
// includes/header.php (FIXED VERSION)
require_once __DIR__ . '/helpers.php';
?>
<!doctype html>
<html lang="tr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>RenPlan -DEMO</title>
  <base href="<?= BASE_URL ?>/">
  <?php
    /* CSS sürüm cache-bust: dosya değişince otomatik güncellenir */
    function _css_v(string $path): string {
        $full = ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)) . $path;
        return is_file($full) ? '?v=' . filemtime($full) : '?v=1';
    }
  ?>
  <link rel="stylesheet" href="/assets/css/app.css<?= _css_v('/assets/css/app.css') ?>">
  <link rel="stylesheet" href="/assets/css/layout.css<?= _css_v('/assets/css/layout.css') ?>">
  <?php
  // orders.css — sipariş sayfalarında her zaman yükle
  $__page = basename($_SERVER['PHP_SELF'] ?? '');
  if (in_array($__page, ['orders.php','order_edit.php','order_add.php','order_view.php'])) {
    echo '<link rel="stylesheet" href="/assets/css/orders.css?v=' . (is_file(__DIR__.'/../assets/css/orders.css') ? filemtime(__DIR__.'/../assets/css/orders.css') : 1) . '">';
  }
  ?>
  <script src="/assets/js/dropdown_fix.js"></script>
  <style>
    /* Workpilot: Ürünler dropdown min-css */
    .dropdown {
      position: relative;
      display: inline-block
    }

    .dropdown>.dropdown-toggle {
      display: flex;
      align-items: center;
      gap: 6px
    }

    .dropdown .caret {
      border: solid currentColor;
      border-width: 0 2px 2px 0;
      display: inline-block;
      padding: 3px;
      transform: rotate(45deg);
      margin-top: -2px
    }

    .dropdown .menu {
      position: absolute;
      top: 100%;
      left: 0;
      min-width: 220px;
      background: #0b1222;
      border: 1px solid #1f2937;
      border-radius: 12px;
      padding: 6px;
      margin-top: 0;
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity .15s linear, transform .15s ease;
      transform: translateY(4px);
      z-index: 2000
    }

    .dropdown .menu a {
      display: block;
      padding: 10px 12px;
      border-radius: 8px;
      white-space: nowrap
    }

    .dropdown:hover .menu {
      display: block
    }

    @media (hover:none) {
      .dropdown.open .menu {
        display: block
      }
    }

    /* Kullanıcı menüsü: sağa hizala ve taşmayı engelle */
    .dropdown.user-menu {
      position: relative
    }

    .dropdown.user-menu .menu {
      left: auto;
      right: 0;
      max-width: calc(100vw - 12px);
      overflow-wrap: anywhere
    }

    .dropdown:hover .menu,
    .dropdown.open .menu,
    .dropdown:focus-within .menu {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
      transform: translateY(0);
    }

    .dropdown::after {
      content: "";
      position: absolute;
      left: 0;
      right: 0;
      top: 100%;
      height: 16px;
    }

    .renv2-logo img {
      display: block;
      width: 100%;
      height: auto;
    }

    body.renv2 {
      background: #e6ebf2 !important;
    }

    /* Hide original top nav completely (DOM stays for JS parsing) */
    .nav,
    .nav * {
      display: none !important;
    }

    /* Layout */
    html,
    body {
      height: 100%;
      margin: 0;
      padding: 0;
    }

    body.renv2 {
      background: #e6ebf2;
      padding-left: 100px;
    }

    /* sidebar width + gap */

    /* Leftbar */
    .renv2-leftbar {
      position: fixed;
      left: 0;
      top: 0;
      bottom: 0;
      width: 86px;
      background: #ee7422;
      z-index: 1000;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 14px 8px;
      gap: 18px;
      box-shadow: inset -1px 0 rgba(0, 0, 0, .12);
    }

    .renv2-logo {
      display: block;
      width: 72px;
      margin: 6px 0 12px 0;
    }

    .renv2-nav {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    /* Tile */
    .renv2-tile {
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-decoration: none;
      color: #fff;
      background: transparent;
      border: 1px solid rgba(255, 255, 255, .22);
      border-radius: 18px;
      padding: 12px 6px;
    }

    .renv2-tile:hover {
      background: transparent;
      border-color: rgba(255, 255, 255, .38);
    }

    .renv2-tile .icon {
      display: grid;
      place-items: center;
      margin: 0;
      padding: 0;
      background: transparent;
      border: none;
      box-shadow: none;
      width: auto;
      height: auto;
      border-radius: 0;
    }

    .renv2-tile .icon svg {
      width: 24px;
      height: 24px;
      fill: none;
      stroke: #fff;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
      display: block;
    }

    .renv2-tile .renv2-label {
      color: #fff !important;
      margin-top: 6px;
      text-align: center;
      font-weight: 600;
      font-size: 11px;
      line-height: 1.1;
      white-space: normal;
      overflow-wrap: anywhere;
      word-break: break-word;
      width: 100%;
    }

    /* Flyout */
    .renv2-fly {
      position: fixed;
      left: 96px;
      top: 100px;
      min-width: 260px;
      max-width: 320px;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .12);
      border: 1px solid rgba(0, 0, 0, .06);
      padding: 10px;
      z-index: 1100;
      display: none;
    }

    .renv2-fly.open {
      display: block;
    }

    .renv2-fly a {
      color: #0f172a;
      display: block;
      text-decoration: none;
      color: #0f172a;
      font-weight: 600;
      padding: 10px 12px;
      border-radius: 12px;
      margin: 2px 0;
    }

    .renv2-fly a:hover {
      background: #f3f4f6;
    }

    /* Content wrappers should not add extra left margin */
    .renv2 main,
    .renv2 .page,
    .renv2 .container,
    .renv2 #app,
    .renv2 .content {
      margin-left: 0 !important;
    }
  </style>
</head>

<body class="renv2">


  <!-- renPlan v2 Leftbar -->
  <aside class="renv2-leftbar" id="renv2Leftbar">
    <a href="index.php" class="renv2-logo" aria-label="renPlan"><img src="/assets/logo.png" alt="renPlan Logo"></a>
    <nav class="renv2-nav" id="renv2Nav"></nav>
  </aside>
  <div class="renv2-fly" id="renv2Fly"></div>

  <!-- Original Navigation (Hidden but parsed by JS) -->
  <nav class="nav">
    <a class="brand" href="index.php">
      <img src="/assets/logo.png" alt="renPlan Logo" style="height:32px; vertical-align:middle; margin-right:8px;">
      <span class="muted">v.1.0</span>
    </a>
    <div class="menu-toggle">☰</div>
    <?php if (current_user()): ?>
      <?php if (has_role('admin') || has_role('sistem_yoneticisi') || has_role('muhasebe')): ?>
        <div class="menu-list">
          <a href="index.php">Panel</a>
          <?php if (!has_role('muhasebe')): ?>
            <div class="dropdown">
              <a href="#" class="dropdown-toggle">Ürünler<span class="caret"></span></a>
              <div class="menu">
                <a href="products.php">Ürünler</a>
                <a href="products.php?a=new">Ürün Ekle</a>
                <a href="taxonomies.php?t=brands">Markalar</a>
                <a href="taxonomies.php?t=categories">Kategoriler</a>
                <a href="attributes.php">Öznitelikler</a>
                <?php if (has_role('admin')): ?>
                  <a href="import_products.php">Ürünleri İçe Aktar</a>
                  <a href="export_products.php">Ürünleri Dışa Aktar</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
          <div class="dropdown">
            <a href="#" class="dropdown-toggle">Siparişler<span class="caret"></span></a>
            <div class="menu">
              <a href="orders.php?restore=1">Siparişler</a>
              <?php if (!has_role('muhasebe')): ?>
                <a href="projeler.php" class="<?= basename($_SERVER['PHP_SELF']) == 'projeler.php' ? 'active' : '' ?>">
                  <i class="fas fa-project-diagram"></i> Projeler
                </a>
                <a href="lazer_kesim.php">Lazer Kesim</a>
                <a href="calendar.php?a=new">Sipariş Takvimi</a>
              <?php endif; ?>
              <?php if (has_role('admin')): ?>
                <a href="import_orders.php">Siparişleri İçe Aktar</a>
                <a href="export_orders.php">Siparişleri Dışarı Aktar</a>
              <?php endif; ?>
            </div>
          </div>
          <?php if (has_role('admin') || has_role('muhasebe')): ?>
            <div class="dropdown">
              <a href="#" class="dropdown-toggle">Raporlar<span class="caret"></span></a>
              <div class="menu">
                <a href="/sales_reps.php">Satış ve Finans İstatistikleri</a>
                <a href="/production.php">Canlı Üretim Sahası</a>
                <a href="faturalar.php">Faturalar</a>
              </div>
            </div>
          <?php endif; ?>

          <div class="dropdown">
            <a href="#" class="dropdown-toggle">Müşteriler<span class="caret"></span></a>
            <div class="menu">
              <a href="customers.php">Müşteriler</a>
              <?php if (has_role('admin')): ?>
                <a href="customers_import.php">Müşterileri İçe Aktar</a>
                <a href="customers_export.php">Müşterileri Dışarı Aktar</a>
              <?php endif; ?>
            </div>
          </div>
          <?php if (!has_role('muhasebe')): ?>
            <div class="dropdown">
              <a href="#" class="dropdown-toggle">Satın Alma<span class="caret"></span></a>
              <div class="menu">
                <a href="satinalma-sys/talepler.php">Talepler</a>
                <a href="satinalma-sys/talep_olustur.php">Talep Oluştur</a>
                <?php if (has_role('admin')): ?>
                  <a href="satinalma-sys/satinalma_rapor.php">Satın Alma Raporu</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
          <?php if (has_role('admin')): ?>
            <div class="dropdown">
              <a href="#" class="dropdown-toggle">Yönetim<span class="caret"></span></a>
              <div class="menu">
                <a href="users_admin.php">Kullanıcılar</a>
                <a href="roles_permissions.php">Yetki Yönetimi</a>
                <a href="audit_log.php">Log Kayıtları</a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php elseif (has_role('musteri')): ?>
        <div class="menu-list">
          <div class="dropdown">
            <a href="orders.php" style="padding: 14px 16px; display: block; color: #fff; text-decoration: none; font-weight: bold;">Siparişlerim</a>
          </div>
        </div>
      <?php else: ?>
        <div class="menu-list">
          <a href="index.php">Panel</a>
          <div class="dropdown">
            <a href="#" class="dropdown-toggle">Ürün Yönetimi<span class="caret"></span></a>
            <div class="menu">
              <a href="products.php">Ürünler</a>
            </div>
          </div>
          <div class="dropdown">
            <a href="#" class="dropdown-toggle">Sipariş Yönetimi<span class="caret"></span></a>
            <div class="menu">
              <a href="orders.php">Siparişler</a>
              <a href="lazer_kesim.php">Lazer Kesim</a> <a href="calendar.php?a=new">Sipariş Takvimi</a>
            </div>
          </div>
          <div class="dropdown">
            <a href="#" class="dropdown-toggle">Satın Alma Yönetimi<span class="caret"></span></a>
            <div class="menu">
              <a href="satinalma-sys/talepler.php">Talepler</a>
              <a href="satinalma-sys/talep_olustur.php">Talep Oluştur</a>
            </div>
          </div>
        </div>
      <?php endif; ?>
      <div class="dropdown user-menu ml-auto">
        <a href="#" class="dropdown-toggle user-btn" aria-haspopup="true" aria-expanded="false" title="Hesap">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 12c2.9 0 5.25-2.35 5.25-5.25S14.9 1.5 12 1.5 6.75 3.85 6.75 6.75 9.1 12 12 12zm0 2.25c-4.7 0-8.25 2.9-8.25 6.5 0 .41.34.75.75.75h15c.41 0 .75-.34.75-.75 0-3.6-3.55-6.5-8.25-6.5z" />
          </svg>
        </a>
        <div class="menu user-menu-panel">
          <div class="user-card">
            <div class="user-name"><?= h($_SESSION['uname'] ?? '') ?></div>
            <div class="user-role"><?= h(role_label(current_role())) ?></div>
          </div>
          <a href="users.php">Şifre Değiştir</a>
          <a href="logout.php">Çıkış Yap</a>
        </div>
      </div>
    <?php else: ?>
      <span class="muted ml-auto">Giriş yapın</span>
    <?php endif; ?>
  </nav>

  <div class="wrap">
    <!-- Content starts here -->

    <script>
      document.addEventListener("DOMContentLoaded", function() {
        const isMobile = () => window.matchMedia('(max-width: 768px)').matches;
        const menuToggle = document.querySelector(".menu-toggle");
        const menuList = document.querySelector(".nav .menu-list");

        if (menuToggle && menuList) {
          menuToggle.addEventListener("click", function(e) {
            e.preventDefault();
            menuList.classList.toggle("show");
          });
        }

        document.querySelectorAll(".nav .menu-list .dropdown > .dropdown-toggle").forEach(function(a) {
          a.addEventListener("click", function(e) {
            if (isMobile()) {
              e.preventDefault();
              e.stopPropagation();
              const item = a.closest(".dropdown");
              item.classList.toggle("open");
            }
          });
        });

        document.addEventListener("click", function(e) {
          if (!isMobile()) return;
          if (!menuList) return;
          const inside = menuList.contains(e.target) || (menuToggle && menuToggle.contains(e.target));
          if (!inside) {
            menuList.classList.remove("show");
            menuList.querySelectorAll(".dropdown.open").forEach(function(d) {
              d.classList.remove("open");
            });
          }
        });

        // Sidebar menu builder
        var srcDropdowns = Array.from(document.querySelectorAll('.nav .dropdown'));
        var nav = document.getElementById('renv2Nav');
        var fly = document.getElementById('renv2Fly');
        var closeTimer = null;

        var ICONS = {
          home: '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',
          cube: '<path d="M12 3l8 4v10l-8 4-8-4V7z"/><path d="M12 3v10"/><path d="M4 7l8 6 8-6"/>',
          users: '<path d="M17 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
          doc: '<path d="M7 3h8l5 5v13H7z"/><path d="M15 3v6h6"/><path d="M10 13h6"/><path d="M10 17h6"/>',
          folder: '<path d="M3 7h6l2 2h10v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a1 1 0 0 1 1-1z"/>',
          bag: '<path d="M6 7h12l-1 14H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/><path d="M9 7c0-1.66 1.34-3 3-3s3 1.34 3 3"/>',
          search: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
          chart: '<path d="M18 20V10M12 20V4M6 20v-6"/><rect x="2" y="2" width="20" height="20" rx="2" ry="2"/>'
        };

        function pickIcon(label) {
          var t = label.toLowerCase();
          if (t.includes('ana sayfa') || t.includes('panel') || t.includes('dashboard')) return ICONS.home;
          if (t.includes('ürün')) return ICONS.cube;
          if (t.includes('müşteri') || t.includes('kullanıcı')) return ICONS.users;
          if (t.includes('talepler') || t.includes('talep')) return ICONS.doc;
          if (t.includes('satın alma') || t.includes('satinalma') || t.includes('purchase')) return ICONS.bag;
          if (t.includes('sipariş') || t.includes('siparis')) return ICONS.folder;
          if (t.includes('ara') || t.includes('search')) return ICONS.search;
          if (t.includes('rapor') || t.includes('analiz')) return ICONS.chart;
          return ICONS.folder;
        }

        function clearFly(immediate) {
          if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
          }

          function doClose() {
            fly.classList.remove('open');
            fly.innerHTML = '';
          }
          if (immediate) doClose();
          else closeTimer = setTimeout(doClose, 160);
        }

        function openFlyFor(tile, links) {
          if (!links || links.length === 0) {
            clearFly(true);
            return;
          }
          if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
          }

          var r = tile.getBoundingClientRect();
          fly.style.left = (r.right + 14) + 'px';
          fly.style.top = Math.max(16, r.top) + 'px';

          fly.innerHTML = '';
          links.forEach(function(a) {
            var x = document.createElement('a');
            x.href = a.getAttribute('href');
            x.textContent = a.textContent.trim();
            fly.appendChild(x);
          });
          fly.classList.add('open');
        }

        srcDropdowns.forEach(function(dd) {
          var titleEl = dd.querySelector('.dropdown-toggle');
          if (!titleEl) return;
          var title = titleEl.textContent.trim();
          var links = Array.from(dd.querySelectorAll('.menu a')).filter(function(a) {
            return a.getAttribute('href') && a.getAttribute('href') !== '#';
          });

          var tile = document.createElement('a');
          tile.href = '#';
          tile.className = 'renv2-tile';

          var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
          icon.setAttribute('viewBox', '0 0 24 24');
          icon.innerHTML = pickIcon(title);
          var iconWrap = document.createElement('span');
          iconWrap.className = 'icon';
          iconWrap.appendChild(icon);

          var label = document.createElement('span');
          label.className = 'renv2-label';
          label.textContent = title;

          tile.appendChild(iconWrap);
          tile.appendChild(label);
          nav.appendChild(tile);

          if (links.length === 0) {
            var href = titleEl.getAttribute('href');
            if (href && href !== '#') {
              tile.href = href;
              tile.addEventListener('click', function(e) {
                e.stopPropagation();
              });
            } else {
              tile.addEventListener('click', function(e) {
                e.preventDefault();
              });
            }
          } else {
            tile.addEventListener('mouseenter', function() {
              openFlyFor(tile, links);
            });
            tile.addEventListener('mouseleave', function() {
              clearFly(false);
            });
          }
        });

        // Last tile = Profil
        var _tiles = nav.querySelectorAll('.renv2-tile');
        if (_tiles.length) {
          var _last = _tiles[_tiles.length - 1];
          var _lbl = _last.querySelector('.renv2-label');
          if (_lbl) {
            _lbl.textContent = 'Profil';
          }
        }

        fly.addEventListener('mouseenter', function() {
          if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
          }
        });
        fly.addEventListener('mouseleave', function() {
          clearFly(false);
        });
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape') clearFly(true);
        });
        window.addEventListener('scroll', function() {
          clearFly(true);
        }, {
          passive: true
        });
      });
    </script>