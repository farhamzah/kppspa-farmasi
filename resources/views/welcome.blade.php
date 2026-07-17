@php
    $logo = asset('images/logo-fakultas-farmasi-ubp.png');
    $heroImage = asset('images/my-pspa/hero-pkpa-pspa-v2.png');
    $accountUrls = config('my_pspa.account_urls', []);
    $wahana = [
        ['Apotek', 'Pelayanan kefarmasian komunitas dan manajemen apotek.'],
        ['Puskesmas', 'Pelayanan primer, program kesehatan masyarakat, dan farmasi klinik dasar.'],
        ['PBF', 'Distribusi sediaan farmasi melalui Pedagang Besar Farmasi.'],
        ['Rumah Sakit', 'Farmasi klinik, instalasi farmasi, dan keselamatan pasien.'],
        ['Industri', 'Produksi, pemastian mutu, registrasi, dan sistem mutu industri.'],
        ['Pemerintahan', 'Loka POM atau Dinas Kesehatan sesuai penempatan program.'],
    ];
    $guides = ['Panduan PKPA', 'Panduan Logbook', 'Format Laporan', 'SOP dan Formulir', 'Peraturan dan Literatur', 'Informasi Mitra'];
    $icons = [
        'login' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>',
        'book' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        'spark' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2l-2 7-7 2 7 2 2 7 2-7 7-2-7-2-2-7z"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-5"/></svg>',
        'doc' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-5"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>',
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MY PSPA - Sistem Informasi Program Studi Profesi Apoteker</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        :root {
            color-scheme: light;
            --ink: #0b1327;
            --navy: #111a33;
            --muted: #52657d;
            --line: #d9e7ec;
            --paper: #ffffff;
            --teal: #08786f;
            --teal-2: #0f9488;
            --mint: #effdfb;
            --soft: #f7fbfb;
            --shadow-sm: 0 12px 28px rgba(15, 23, 42, .07);
            --shadow-md: 0 20px 58px rgba(15, 23, 42, .09);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 12% 10%, rgba(45, 212, 191, .18), transparent 30%),
                radial-gradient(circle at 88% 18%, rgba(250, 204, 21, .14), transparent 28%),
                linear-gradient(180deg, #f9fffe 0%, #f4fbfb 46%, #fbfcfd 100%);
        }
        body::before {
            position: fixed;
            inset: 0;
            z-index: -1;
            content: "";
            background-image:
                linear-gradient(rgba(15, 23, 42, .04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, .04) 1px, transparent 1px);
            background-size: 32px 32px;
            mask-image: linear-gradient(180deg, black 0%, transparent 82%);
        }
        a { color: inherit; text-decoration: none; }
        button, input { font: inherit; }
        svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .container { width: min(1440px, calc(100% - 40px)); margin: 0 auto; }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 50;
            border-bottom: 1px solid rgba(217, 231, 236, .9);
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(20px);
        }
        .nav-inner {
            min-height: 82px;
            display: grid;
            grid-template-columns: minmax(260px, 1fr) auto minmax(190px, 1fr);
            align-items: center;
            gap: 18px;
        }
        .brand { display: inline-flex; align-items: center; gap: 13px; min-width: 0; }
        .brand-logo {
            width: 56px;
            height: 56px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(217, 231, 236, .95);
            border-radius: 18px;
            background: #fff;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .brand-logo img { width: 44px; height: 44px; object-fit: contain; }
        .brand strong { display: block; color: var(--navy); font-size: 23px; font-weight: 950; line-height: 1.04; }
        .brand small { display: block; margin-top: 3px; color: #0f4f4a; font-size: 13px; font-weight: 850; }
        .nav-links { display: flex; align-items: center; justify-content: center; gap: 6px; }
        .nav-links a {
            position: relative;
            border-radius: 999px;
            padding: 11px 15px;
            color: #24344d;
            font-weight: 900;
            white-space: nowrap;
        }
        .nav-links a::after {
            position: absolute;
            left: 18px;
            right: 18px;
            bottom: 4px;
            height: 3px;
            border-radius: 999px;
            content: "";
            transform: scaleX(0);
            background: linear-gradient(90deg, var(--teal), #12a66f);
            transition: transform .2s ease;
        }
        .nav-links a:hover, .nav-links a.is-active { color: var(--teal); background: var(--mint); }
        .nav-links a:hover::after, .nav-links a.is-active::after { transform: scaleX(1); }
        .nav-actions { display: flex; justify-content: flex-end; align-items: center; gap: 10px; }
        .mobile-menu { display: none; }
        .hamburger { display: none; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 48px;
            border: 1px solid var(--line);
            border-radius: 15px;
            padding: 0 20px;
            background: #fff;
            color: var(--navy);
            font-weight: 950;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 18px 40px rgba(15, 23, 42, .09); }
        .btn-primary {
            border-color: rgba(8, 120, 111, .18);
            background: linear-gradient(135deg, #0d8e84, #06796f);
            color: #fff;
            box-shadow: 0 18px 44px rgba(8, 120, 111, .24);
        }
        .btn-secondary { color: var(--teal); }

        .hero { padding: 34px 0 22px; }
        .hero-grid {
            display: grid;
            grid-template-columns: minmax(430px, 1.08fr) minmax(420px, .98fr) minmax(320px, .62fr);
            gap: 20px;
            align-items: start;
        }
        .hero-panel, .visual-panel, .login-card, .glass-strip, .section-card {
            border: 1px solid rgba(217, 231, 236, .94);
            background: rgba(255, 255, 255, .92);
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(16px);
        }
        .hero-panel {
            min-height: 440px;
            border-radius: 28px;
            padding: clamp(28px, 2.7vw, 38px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            border: 1px solid #99f6e4;
            border-radius: 999px;
            background: var(--mint);
            color: var(--teal);
            padding: 8px 13px;
            font-size: 12px;
            font-weight: 950;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        h1 {
            margin: 22px 0 0;
            color: var(--navy);
            font-size: clamp(38px, 2.85vw, 50px);
            line-height: 1.05;
            letter-spacing: 0;
            font-weight: 950;
        }
        h1 .accent { display: block; color: var(--teal); }
        .lead {
            margin: 20px 0 0;
            max-width: 560px;
            color: var(--muted);
            font-size: clamp(17px, 1.18vw, 21px);
            line-height: 1.55;
            font-weight: 500;
        }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        .trust-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 18px;
            margin-top: 24px;
        }
        .trust-row span {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: #344256;
            font-weight: 900;
            min-width: 0;
        }
        .trust-row span::before {
            content: "OK";
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 27px;
            height: 27px;
            flex: none;
            border: 1px solid #5eead4;
            border-radius: 999px;
            background: #ecfdf5;
            color: var(--teal);
            font-size: 10px;
            font-weight: 950;
        }

        .visual-panel {
            position: relative;
            min-height: 440px;
            overflow: hidden;
            border-radius: 28px;
            padding: 30px 0 0;
        }
        .visual-panel::before {
            position: absolute;
            inset: 34px 34px auto;
            height: 150px;
            border: 1px solid rgba(217, 231, 236, .9);
            border-radius: 25px 25px 0 0;
            content: "";
            background:
                linear-gradient(rgba(15, 23, 42, .035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, .035) 1px, transparent 1px),
                rgba(244, 251, 251, .85);
            background-size: 32px 32px;
        }
        .visual-kicker {
            position: relative;
            z-index: 2;
            display: inline-flex;
            margin-left: 44px;
            border: 1px solid #99f6e4;
            border-radius: 999px;
            background: #f9fffe;
            color: var(--teal);
            padding: 9px 16px;
            font-size: 12px;
            font-weight: 950;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .student-illustration {
            position: relative;
            z-index: 1;
            display: block;
            width: 100%;
            height: 300px;
            margin-top: 28px;
            object-fit: cover;
            object-position: center center;
            box-shadow: 0 18px 48px rgba(15, 23, 42, .11);
        }
        .visual-floating {
            position: relative;
            z-index: 3;
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: -22px 26px 0;
        }
        .mini-card {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 166px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(255, 255, 255, .94);
            padding: 11px 14px;
            box-shadow: var(--shadow-sm);
            color: var(--navy);
            font-size: 13px;
            font-weight: 950;
        }
        .mini-card small { display: block; margin-top: 2px; color: var(--muted); font-size: 11px; font-weight: 700; }
        .icon-box {
            display: inline-grid;
            place-items: center;
            width: 52px;
            height: 52px;
            flex: none;
            border: 1px solid #99f6e4;
            border-radius: 16px;
            background: #dffbf7;
            color: var(--teal);
        }
        .icon-box.small { width: 38px; height: 38px; border-radius: 12px; }

        .login-card {
            border-radius: 28px;
            padding: 28px;
            min-height: 440px;
        }
        .login-head { display: grid; grid-template-columns: 58px minmax(0, 1fr); gap: 18px; align-items: start; }
        .login-head h2 { margin: 0; color: var(--navy); font-size: 28px; line-height: 1.14; font-weight: 950; }
        .login-head p { margin: 10px 0 0; color: var(--muted); font-size: 16px; line-height: 1.45; }
        .login-card form { display: grid; gap: 15px; margin-top: 24px; }
        .field label { display: block; margin-bottom: 8px; color: #27354b; font-size: 13px; font-weight: 950; }
        .input-wrap { position: relative; }
        .input-wrap input {
            width: 100%;
            min-height: 58px;
            border: 1px solid #cfe0ec;
            border-radius: 17px;
            background: #eaf3ff;
            color: var(--navy);
            padding: 0 58px 0 18px;
            outline: none;
            font-size: 17px;
            font-weight: 650;
        }
        .input-wrap input:focus { border-color: var(--teal-2); box-shadow: 0 0 0 4px rgba(15, 148, 136, .14); }
        .input-suffix {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #607089;
            font-weight: 950;
            cursor: pointer;
        }
        .login-card .btn { width: 100%; min-height: 56px; font-size: 17px; }
        .login-note {
            display: grid;
            grid-template-columns: 46px minmax(0, 1fr);
            gap: 14px;
            align-items: start;
            margin: 18px 0 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.45;
        }
        .error-box, .status-box {
            margin-top: 18px;
            border-radius: 14px;
            padding: 12px 14px;
            font-weight: 800;
            line-height: 1.45;
        }
        .error-box { border: 1px solid #fecaca; background: #fff1f2; color: #b91c1c; }
        .status-box { border: 1px solid #bbf7d0; background: #ecfdf5; color: #166534; }

        .glass-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            overflow: hidden;
            border-radius: 24px;
            margin-top: 20px;
        }
        .feature-item {
            display: grid;
            grid-template-columns: 46px minmax(0, 1fr);
            gap: 13px;
            padding: 18px;
            border-right: 1px solid var(--line);
        }
        .feature-item:last-child { border-right: 0; }
        .feature-item strong { display: block; font-weight: 950; color: var(--navy); }
        .feature-item span span { display: block; margin-top: 4px; color: var(--muted); font-size: 13px; line-height: 1.4; }

        .section { padding: 34px 0; }
        .section-card { border-radius: 30px; padding: 30px; }
        .section-head { display: flex; justify-content: space-between; gap: 18px; align-items: end; margin-bottom: 22px; }
        .section h2 { margin: 14px 0 0; color: var(--navy); font-size: clamp(28px, 3vw, 46px); line-height: 1.05; font-weight: 950; }
        .section p { margin: 12px 0 0; color: var(--muted); line-height: 1.6; font-size: 16px; }
        .workflow, .wahana-grid, .guide-grid, .info-grid, .faq-grid {
            display: grid;
            gap: 14px;
        }
        .workflow { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .wahana-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .guide-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .info-grid { grid-template-columns: 1.1fr .9fr; }
        .faq-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .step-card, .wahana-card, .guide-card, .info-card, .faq-card {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
            padding: 18px;
            box-shadow: var(--shadow-sm);
        }
        .step-card strong, .wahana-card strong, .guide-card strong, .info-card strong, .faq-card strong {
            display: block;
            color: var(--navy);
            font-size: 17px;
            font-weight: 950;
        }
        .step-card span, .wahana-card span, .guide-card span, .info-card span, .faq-card span {
            display: block;
            margin-top: 8px;
            color: var(--muted);
            line-height: 1.5;
            font-size: 14px;
        }
        .guide-card small {
            display: inline-flex;
            margin-top: 14px;
            border-radius: 999px;
            background: var(--mint);
            color: var(--teal);
            padding: 6px 10px;
            font-weight: 950;
        }
        .core-links { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        .core-links a, .core-links span {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: #fff;
            padding: 9px 12px;
            color: var(--teal);
            font-weight: 900;
            font-size: 13px;
        }
        .footer {
            border-top: 1px solid var(--line);
            padding: 30px 0;
            background: rgba(255, 255, 255, .72);
        }
        .footer-grid { display: grid; grid-template-columns: 1fr auto 1fr; gap: 20px; align-items: center; }
        .footer-links { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; color: #344256; font-weight: 850; }
        .footer p { margin: 6px 0 0; color: var(--muted); line-height: 1.5; }

        @media (max-width: 1240px) {
            .nav-inner { grid-template-columns: 1fr auto; }
            .nav-links { display: none; }
            .hamburger {
                display: inline-flex;
                min-height: 46px;
                border: 1px solid var(--line);
                border-radius: 14px;
                padding: 0 14px;
                background: #fff;
                color: var(--navy);
                font-weight: 950;
                box-shadow: var(--shadow-sm);
                cursor: pointer;
            }
            .mobile-menu.is-open { display: grid; gap: 8px; padding: 0 0 14px; }
            .mobile-menu a { border-radius: 14px; background: var(--mint); padding: 12px; color: var(--teal); font-weight: 900; }
            .hero-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
            .login-card { grid-column: 1 / -1; min-height: auto; }
            .workflow, .wahana-grid, .guide-grid, .faq-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 820px) {
            .container { width: min(100% - 24px, 1440px); }
            .nav-actions .btn-primary { display: none; }
            .brand strong { font-size: 19px; }
            .brand small { font-size: 12px; }
            .hero { padding-top: 20px; }
            .hero-grid, .info-grid, .footer-grid { grid-template-columns: 1fr; }
            .hero-panel, .visual-panel, .login-card, .section-card { border-radius: 22px; padding: 20px; }
            .visual-panel { padding: 24px 0 18px; }
            .visual-kicker { margin-left: 20px; }
            .student-illustration { height: 250px; margin-top: 26px; }
            .visual-floating { display: grid; margin: -12px 18px 0; }
            .mini-card { width: 100%; }
            h1 { font-size: 40px; }
            .lead { font-size: 16px; }
            .hero-actions .btn { width: 100%; }
            .trust-row, .glass-strip, .workflow, .wahana-grid, .guide-grid, .faq-grid { grid-template-columns: 1fr; }
            .feature-item { border-right: 0; border-bottom: 1px solid var(--line); }
            .feature-item:last-child { border-bottom: 0; }
            .section-head { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <div class="nav-inner">
                <a class="brand" href="#beranda" aria-label="Beranda MY PSPA">
                    <span class="brand-logo"><img src="{{ $logo }}" alt="Logo Fakultas Farmasi UBP"></span>
                    <span><strong>MY PSPA</strong><small>Farmasi UBP Karawang</small></span>
                </a>
                <nav class="nav-links" aria-label="Navigasi landing">
                    <a class="is-active" href="#beranda">Beranda</a>
                    <a href="#alur">Alur PKPA</a>
                    <a href="#wahana">Wahana</a>
                    <a href="#panduan">Buku Panduan</a>
                    <a href="#informasi">Informasi</a>
                    <a href="#faq">FAQ/Kontak</a>
                </nav>
                <div class="nav-actions">
                    <button class="hamburger" type="button" data-menu-toggle>Menu</button>
                    <a class="btn btn-primary" href="{{ route('login') }}">{!! $icons['login'] !!}<span>Masuk ke Portal</span></a>
                </div>
            </div>
            <nav class="mobile-menu" data-mobile-menu aria-label="Navigasi mobile">
                <a href="#beranda">Beranda</a>
                <a href="#alur">Alur PKPA</a>
                <a href="#wahana">Wahana</a>
                <a href="#panduan">Buku Panduan</a>
                <a href="#informasi">Informasi</a>
                <a href="#faq">FAQ/Kontak</a>
                <a href="{{ route('login') }}">Masuk ke Portal</a>
            </nav>
        </div>
    </header>

    <main id="beranda">
        <section class="hero">
            <div class="container hero-grid">
                <section class="hero-panel" aria-labelledby="landing-title">
                    <span class="eyebrow">{!! $icons['spark'] !!} Sistem Informasi Program Studi Profesi Apoteker</span>
                    <h1 id="landing-title">MY PSPA <span class="accent">Portal PKPA UBP</span></h1>
                    <p class="lead">Sistem terpadu untuk mengelola Program Studi Profesi Apoteker, mulai dari pembekalan, kesiapan tempat praktik, penempatan enam wahana, logbook, laporan, ujian, sampai nilai akhir.</p>
                    <div class="hero-actions">
                        <a class="btn btn-primary" href="{{ route('login') }}">{!! $icons['login'] !!}<span>Masuk ke Portal</span></a>
                        <a class="btn btn-secondary" href="#panduan">{!! $icons['book'] !!}<span>Lihat Panduan</span></a>
                    </div>
                    <div class="trust-row" aria-label="Keunggulan ringkas">
                        <span>Terintegrasi Core Farmasi</span>
                        <span>Akses berbasis peran</span>
                        <span>Enam wahana terpantau</span>
                        <span>Audit penempatan siap</span>
                    </div>
                </section>

                <section class="visual-panel" aria-label="Ilustrasi ruang kerja PKPA">
                    <span class="visual-kicker">Ruang Kerja PKPA</span>
                    <img class="student-illustration" src="{{ $heroImage }}" alt="Ilustrasi mahasiswa farmasi menggunakan ruang kerja akademik digital">
                    <div class="visual-floating">
                        <div class="mini-card"><span class="icon-box small">{!! $icons['doc'] !!}</span><span><strong>Rotasi Terstruktur</strong><small>6 wahana PKPA</small></span></div>
                        <div class="mini-card"><span class="icon-box small">{!! $icons['shield'] !!}</span><span><strong>Akses Aman</strong><small>Peran dari Core</small></span></div>
                    </div>
                </section>

                <aside class="login-card" aria-label="Login MY PSPA">
                    <div class="login-head">
                        <span class="icon-box">{!! $icons['lock'] !!}</span>
                        <div>
                            <h2>Masuk ke Portal PKPA</h2>
                            <p>Gunakan akun Core Farmasi.</p>
                        </div>
                    </div>
                    @if(session('status'))
                        <div class="status-box">{{ session('status') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="error-box">{{ $errors->first() }}</div>
                    @endif
                    <form method="POST" action="{{ route('login.store') }}">
                        @csrf
                        <div class="field">
                            <label for="landing-email">Email akun Core Farmasi</label>
                            <div class="input-wrap">
                                <input id="landing-email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" placeholder="nama@ubpkarawang.ac.id" required>
                                <span class="input-suffix">{!! $icons['users'] !!}</span>
                            </div>
                        </div>
                        <div class="field">
                            <label for="landing-password">Password</label>
                            <div class="input-wrap">
                                <input id="landing-password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required>
                                <button class="input-suffix" type="button" data-password-toggle aria-label="Tampilkan password">Lihat</button>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit">Masuk ke Dashboard</button>
                    </form>
                    <p class="login-note"><span class="icon-box small">{!! $icons['shield'] !!}</span><span>Akses dashboard ditentukan otomatis berdasarkan peran Core dan penugasan PKPA Anda.</span></p>
                </aside>
            </div>

            <div class="container">
                <div class="glass-strip" aria-label="Nilai utama MY PSPA">
                    <article class="feature-item"><span class="icon-box">{!! $icons['shield'] !!}</span><span><strong>Terhubung Core Farmasi</strong><span>Akun, password, status, dan peran mengikuti Core Farmasi.</span></span></article>
                    <article class="feature-item"><span class="icon-box">{!! $icons['calendar'] !!}</span><span><strong>Rotasi PKPA</strong><span>Jadwal dan penempatan dipantau per wahana.</span></span></article>
                    <article class="feature-item"><span class="icon-box">{!! $icons['doc'] !!}</span><span><strong>Logbook & Laporan</strong><span>Bukti kegiatan dan dokumen tersusun rapi.</span></span></article>
                    <article class="feature-item"><span class="icon-box">{!! $icons['users'] !!}</span><span><strong>Dikelola Koordinator</strong><span>Penempatan disusun oleh Koordinator PKPA.</span></span></article>
                </div>
            </div>
        </section>

        <section class="section" id="alur">
            <div class="container section-card">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">{!! $icons['spark'] !!} Alur PKPA</span>
                        <h2>Proses PKPA dibuat jelas dari pembekalan sampai nilai akhir.</h2>
                        <p>Mahasiswa tidak memilih tempat sendiri. Koordinator PKPA menyusun penempatan, mahasiswa melihat hasil setelah dipublikasikan.</p>
                    </div>
                </div>
                <div class="workflow">
                    @foreach (['Pembekalan', 'Penempatan', 'Rotasi Wahana', 'Logbook', 'Laporan', 'Ujian', 'Penilaian', 'Rekap'] as $step)
                        <article class="step-card"><span class="icon-box small">{!! $icons['doc'] !!}</span><strong>{{ $step }}</strong><span>Diproses sesuai jadwal dan peran yang valid dari Core Farmasi.</span></article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section" id="wahana">
            <div class="container section-card">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">{!! $icons['calendar'] !!} Enam Wahana</span>
                        <h2>Semua kewajiban wahana PKPA terpantau dalam satu portal.</h2>
                    </div>
                </div>
                <div class="wahana-grid">
                    @foreach ($wahana as [$name, $body])
                        <article class="wahana-card"><strong>{{ $name }}</strong><span>{{ $body }}</span></article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section" id="panduan">
            <div class="container info-grid">
                <article class="section-card">
                    <div class="section-head">
                        <div>
                            <span class="eyebrow">{!! $icons['book'] !!} Buku Panduan</span>
                            <h2>Literatur dan formulir PKPA mudah ditemukan.</h2>
                        </div>
                    </div>
                    <div class="guide-grid">
                        @foreach ($guides as $guide)
                            <article class="guide-card"><strong>{{ $guide }}</strong><span>Dokumen publik atau tautan resmi dapat dikurasi oleh pengelola.</span><small>Publik</small></article>
                        @endforeach
                    </div>
                </article>
                <article class="section-card" id="informasi">
                    <div class="section-head">
                        <div>
                            <span class="eyebrow">{!! $icons['doc'] !!} Informasi</span>
                            <h2>Pengumuman, jadwal, dan berita PKPA.</h2>
                            <p>Bagian ini disiapkan untuk pengumuman, jadwal, pembekalan, rotasi, berita, dan informasi mitra.</p>
                        </div>
                    </div>
                    <div class="info-grid" style="grid-template-columns:1fr">
                        @foreach (['Pengumuman PKPA', 'Jadwal Pembekalan', 'Publikasi Penempatan', 'Informasi Mitra'] as $info)
                            <article class="info-card"><strong>{{ $info }}</strong><span>Konten akan mengikuti pengelolaan publik MY PSPA.</span></article>
                        @endforeach
                    </div>
                    <div class="core-links">
                        @if($accountUrls['register'] ?? null)<a href="{{ $accountUrls['register'] }}">Daftar akun Core</a>@endif
                        @if($accountUrls['forgot_password'] ?? null)<a href="{{ $accountUrls['forgot_password'] }}">Lupa password</a>@endif
                        @if($accountUrls['manage'] ?? null)<a href="{{ $accountUrls['manage'] }}">Kelola akun</a>@endif
                        @unless(($accountUrls['register'] ?? null) || ($accountUrls['forgot_password'] ?? null) || ($accountUrls['manage'] ?? null))
                            <span>Tautan akun Core belum dikonfigurasi.</span>
                        @endunless
                    </div>
                </article>
            </div>
        </section>

        <section class="section" id="faq">
            <div class="container section-card">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">{!! $icons['shield'] !!} FAQ / Kontak</span>
                        <h2>Bantuan singkat sebelum masuk portal.</h2>
                    </div>
                </div>
                <div class="faq-grid">
                    <article class="faq-card"><strong>Bagaimana cara login?</strong><span>Gunakan akun Core Farmasi yang sudah diberi akses aplikasi MY PSPA.</span></article>
                    <article class="faq-card"><strong>Siapa menentukan tempat praktik?</strong><span>Penempatan disusun oleh Koordinator PKPA, bukan sistem rebut kuota atau siapa cepat dia dapat.</span></article>
                    <article class="faq-card"><strong>Bagaimana jika peran belum muncul?</strong><span>Hubungi administrator Core Farmasi atau pengelola Program PKPA.</span></article>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container footer-grid">
            <a class="brand" href="#beranda">
                <span class="brand-logo"><img src="{{ $logo }}" alt="Logo Fakultas Farmasi UBP"></span>
                <span><strong>MY PSPA</strong><small>Farmasi UBP Karawang</small></span>
            </a>
            <nav class="footer-links" aria-label="Quick links footer">
                <a href="#beranda">Beranda</a>
                <a href="#alur">Alur PKPA</a>
                <a href="#wahana">Wahana</a>
                <a href="{{ route('login') }}">Masuk Portal</a>
            </nav>
            <div>
                <strong>Sistem Informasi Program Studi Profesi Apoteker</strong>
                <p>Platform resmi pengelolaan PKPA Fakultas Farmasi UBP Karawang.</p>
            </div>
        </div>
    </footer>

    <script>
        document.querySelector('[data-menu-toggle]')?.addEventListener('click', function () {
            document.querySelector('[data-mobile-menu]')?.classList.toggle('is-open');
        });

        document.querySelector('[data-password-toggle]')?.addEventListener('click', function () {
            const input = document.getElementById('landing-password');
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            this.textContent = input.type === 'password' ? 'Lihat' : 'Tutup';
        });
    </script>
</body>
</html>
