<?php
use App\Helpers\View;

$pageTitle = 'Home';
$isLoggedIn = $isLoggedIn ?? false;
$userName = $userName ?? '';
$userRole = $userRole ?? '';
$campuses = $campuses ?? [];
$officeCount = $officeCount ?? 0;
$applianceCount = $applianceCount ?? 0;
$totals = $totals ?? ['total_kwh' => 0, 'total_cost' => 0, 'total_co2' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eco Metrics | Carlos Hilado Memorial State University</title>
    <link rel="icon" type="image/jpeg" href="/assets/img/chmsu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --chmsu-forest: #0c2d18;
            --chmsu-deep: #071f11;
            --chmsu-green: #1d5f34;
            --chmsu-emerald: #50c878;
            --chmsu-accent: #22c55e;
            --chmsu-card-bg: rgba(11, 38, 22, 0.90);
            --chmsu-item-bg: rgba(7, 26, 15, 0.72);
            --chmsu-border: rgba(80, 200, 120, 0.22);
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1f2937;
            background-color: #f8faf8;
            overflow-x: hidden;
        }

        /* Top Navigation Bar */
        .chmsu-nav {
            background-color: var(--chmsu-forest);
            border-bottom: 1px solid rgba(80, 200, 120, 0.15);
            padding: 0.75rem 0;
            position: sticky;
            top: 0;
            z-index: 1050;
            backdrop-filter: blur(8px);
        }

        .chmsu-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #fff;
        }

        .chmsu-brand img {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.85);
            background: #fff;
        }

        .chmsu-brand-title {
            font-size: 1.08rem;
            font-weight: 700;
            line-height: 1.2;
            color: #ffffff;
        }

        .chmsu-brand-subtitle {
            font-size: 0.73rem;
            color: rgba(255, 255, 255, 0.7);
            letter-spacing: 0.02em;
        }

        .chmsu-nav-link {
            color: rgba(255, 255, 255, 0.82) !important;
            font-size: 0.92rem;
            font-weight: 500;
            padding: 0.5rem 0.9rem !important;
            transition: color 0.15s ease;
        }

        .chmsu-nav-link:hover {
            color: #50c878 !important;
        }

        .btn-chmsu-login {
            background-color: #ffffff;
            color: var(--chmsu-forest);
            font-weight: 700;
            font-size: 0.92rem;
            padding: 0.45rem 1.4rem;
            border-radius: 50rem;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-chmsu-login:hover {
            background-color: #e5f5ea;
            color: var(--chmsu-deep);
            transform: translateY(-1px);
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            background: linear-gradient(135deg, rgba(6, 26, 13, 0.93) 0%, rgba(12, 45, 23, 0.90) 50%, rgba(8, 30, 16, 0.95) 100%),
                        url('/assets/img/chmsu-campus.jpg') center center / cover no-repeat;
            color: #fff;
            padding: 4.5rem 0 5rem;
            min-height: calc(100vh - 72px);
            display: flex;
            align-items: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(80, 200, 120, 0.14);
            border: 1px solid rgba(80, 200, 120, 0.35);
            color: #6fe096;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.4rem 1rem;
            border-radius: 50rem;
            margin-bottom: 1.5rem;
        }

        .hero-title {
            font-size: clamp(2rem, 3.8vw, 3.2rem);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin-bottom: 1.25rem;
            color: #ffffff;
        }

        .hero-subtitle {
            font-size: 1.05rem;
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.82);
            margin-bottom: 2rem;
            max-width: 560px;
        }

        .hero-buttons {
            display: flex;
            gap: 0.85rem;
            flex-wrap: wrap;
        }

        .btn-hero-primary {
            background: #198754;
            color: #ffffff;
            font-weight: 700;
            padding: 0.75rem 1.6rem;
            border-radius: 0.65rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-hero-primary:hover {
            background: #146c43;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(25, 135, 84, 0.4);
        }

        .btn-hero-secondary {
            background: rgba(15, 45, 27, 0.65);
            color: #ffffff;
            font-weight: 600;
            padding: 0.75rem 1.6rem;
            border-radius: 0.65rem;
            border: 1px solid rgba(80, 200, 120, 0.35);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-hero-secondary:hover {
            background: rgba(25, 75, 45, 0.85);
            color: #ffffff;
            border-color: rgba(80, 200, 120, 0.6);
            transform: translateY(-2px);
        }

        /* Right Hero Guide Card (Matches Screenshot 2 Exactly) */
        .guidance-card {
            background: var(--chmsu-card-bg);
            border: 1px solid var(--chmsu-border);
            border-radius: 1.4rem;
            padding: 1.75rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.45);
        }

        .guidance-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
        }

        .guidance-tag {
            font-size: 0.8rem;
            color: #50c878;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .guidance-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0;
            line-height: 1.25;
        }

        .guidance-icon {
            font-size: 1.6rem;
            color: #facc15;
        }

        .guidance-items {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .guidance-item {
            background: var(--chmsu-item-bg);
            border: 1px solid rgba(80, 200, 120, 0.16);
            border-radius: 0.85rem;
            padding: 0.85rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s ease;
        }

        .guidance-item:hover {
            border-color: rgba(80, 200, 120, 0.45);
            background: rgba(14, 45, 28, 0.85);
            transform: translateX(3px);
        }

        .guidance-item-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.65rem;
            background: rgba(80, 200, 120, 0.15);
            color: #50c878;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .guidance-item-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.15rem;
        }

        .guidance-item-desc {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.72);
            line-height: 1.35;
            margin-bottom: 0;
        }

        /* Stats Bar */
        .stats-bar {
            background: #ffffff;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            padding: 2.25rem 0;
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem 1rem;
        }

        .stat-number {
            font-size: 2.1rem;
            font-weight: 800;
            color: var(--chmsu-forest);
            line-height: 1;
            margin-bottom: 0.35rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Features Section */
        .features-section {
            padding: 5rem 0;
            background-color: #f4f7f4;
        }

        .section-tag {
            display: inline-block;
            font-size: 0.8rem;
            font-weight: 700;
            color: #198754;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.5rem;
        }

        .section-title {
            font-size: 2.1rem;
            font-weight: 800;
            color: #111827;
            margin-bottom: 0.85rem;
        }

        .section-desc {
            font-size: 1rem;
            color: #6b7280;
            max-width: 600px;
            margin: 0 auto 3.5rem;
        }

        .feature-box {
            background: #ffffff;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            padding: 1.85rem;
            height: 100%;
            transition: all 0.25s ease;
        }

        .feature-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.06);
            border-color: rgba(80, 200, 120, 0.45);
        }

        .feature-icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 0.75rem;
            background: #eaf8ee;
            color: #198754;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1.25rem;
        }

        .feature-title {
            font-size: 1.12rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.65rem;
        }

        .feature-text {
            font-size: 0.88rem;
            color: #6b7280;
            line-height: 1.55;
            margin-bottom: 0;
        }

        /* Campuses Section */
        .campuses-section {
            padding: 5rem 0;
            background: #ffffff;
        }

        .campus-card {
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.25s ease;
            background: #ffffff;
            height: 100%;
        }

        .campus-card:hover {
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.06);
            border-color: #50c878;
            transform: translateY(-3px);
        }

        .campus-card-header {
            background: var(--chmsu-forest);
            color: #ffffff;
            padding: 1.25rem 1.5rem;
        }

        .campus-card-body {
            padding: 1.5rem;
        }

        /* Footer */
        .chmsu-footer {
            background-color: var(--chmsu-deep);
            color: rgba(255, 255, 255, 0.7);
            padding: 3.5rem 0 2rem;
            font-size: 0.88rem;
            border-top: 1px solid rgba(80, 200, 120, 0.15);
        }

        .chmsu-footer a {
            color: rgba(255, 255, 255, 0.75);
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .chmsu-footer a:hover {
            color: #50c878;
        }

        @media (max-width: 991.98px) {
            .hero-section {
                padding: 3.5rem 0 4rem;
                min-height: auto;
            }
            .hero-title {
                font-size: 2.2rem;
            }
            .guidance-card {
                margin-top: 2rem;
            }
        }
    </style>
</head>
<body>

    <!-- Top Navigation Bar (Matches Screenshot 2) -->
    <header class="chmsu-nav">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="/" class="chmsu-brand">
                <img src="/assets/img/chmsu-logo.jpg" alt="CHMSU Logo">
                <div>
                    <div class="chmsu-brand-title">Eco Metrics Management System</div>
                    <div class="chmsu-brand-subtitle">Carlos Hilado Memorial State University</div>
                </div>
            </a>

            <div class="d-none d-lg-flex align-items-center gap-1">
                <a href="#home" class="chmsu-nav-link">Home</a>
                <a href="#features" class="chmsu-nav-link">Features</a>
                <a href="#campuses" class="chmsu-nav-link">Campuses</a>
                <a href="#how-it-works" class="chmsu-nav-link">How It Works</a>
                <a href="#contact" class="chmsu-nav-link">Contact</a>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if ($isLoggedIn): ?>
                    <a href="/dashboard" class="btn btn-chmsu-login d-flex align-items-center gap-2">
                        <i class="bi bi-speedometer2"></i>Dashboard
                    </a>
                <?php else: ?>
                    <a href="/login" class="btn btn-chmsu-login">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section (Matches Screenshot 2 Layout with Real CHMSU Campus Photo) -->
    <section class="hero-section" id="home">
        <div class="container">
            <div class="row align-items-center g-4 g-lg-5">
                <!-- Left Column: Title, Subtitle, Action Buttons -->
                <div class="col-lg-6">
                    <div class="hero-badge">
                        <i class="bi bi-shield-check"></i>
                        <span>Carlos Hilado Memorial State University</span>
                    </div>

                    <h1 class="hero-title">
                        Energy &amp; Carbon Footprint Monitoring System
                    </h1>

                    <p class="hero-subtitle">
                        A centralized institutional platform for monitoring electricity consumption, auditing appliance energy efficiency, tracking greenhouse gas emissions, and supporting sustainable energy governance across all CHMSU campuses and offices.
                    </p>

                    <div class="hero-buttons">
                        <?php if ($isLoggedIn): ?>
                            <a href="/dashboard" class="btn-hero-primary">
                                <i class="bi bi-speedometer2"></i>Access Dashboard
                            </a>
                        <?php else: ?>
                            <a href="/login" class="btn-hero-primary">
                                <i class="bi bi-box-arrow-in-right"></i>Access Portal
                            </a>
                        <?php endif; ?>
                        <a href="#features" class="btn-hero-secondary">
                            <i class="bi bi-info-circle"></i>System Overview
                        </a>
                    </div>
                </div>

                <!-- Right Column: System Guidance Card (Exact Structure from Screenshot 2) -->
                <div class="col-lg-6">
                    <div class="guidance-card">
                        <div class="guidance-header">
                            <div>
                                <div class="guidance-tag">System Guidance</div>
                                <h3 class="guidance-title">Smart Energy Governance</h3>
                            </div>
                            <div class="guidance-icon">
                                <i class="bi bi-lightning-charge-fill"></i>
                            </div>
                        </div>

                        <div class="guidance-items">
                            <!-- Item 1 -->
                            <div class="guidance-item">
                                <div class="guidance-item-icon">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                                <div>
                                    <div class="guidance-item-title">Real-Time Consumption Tracking</div>
                                    <p class="guidance-item-desc">Automated calculation of kilowatt-hours, electricity costs, and CO2 emissions from appliance runtimes.</p>
                                </div>
                            </div>

                            <!-- Item 2 -->
                            <div class="guidance-item">
                                <div class="guidance-item-icon">
                                    <i class="bi bi-buildings"></i>
                                </div>
                                <div>
                                    <div class="guidance-item-title">Specific Campus &amp; Office Scope</div>
                                    <p class="guidance-item-desc">Segregated tracking across Talisay, Alijis, Fortune Towne, and Binalbagan campuses to avoid confusion.</p>
                                </div>
                            </div>

                            <!-- Item 3 -->
                            <div class="guidance-item">
                                <div class="guidance-item-icon">
                                    <i class="bi bi-plug"></i>
                                </div>
                                <div>
                                    <div class="guidance-item-title">Appliance Inventory Auditing</div>
                                    <p class="guidance-item-desc">Detailed registry of appliance wattage, operating schedules, and high-load power consumers.</p>
                                </div>
                            </div>

                            <!-- Item 4 -->
                            <div class="guidance-item">
                                <div class="guidance-item-icon">
                                    <i class="bi bi-sliders"></i>
                                </div>
                                <div>
                                    <div class="guidance-item-title">Threshold &amp; Anomaly Alerts</div>
                                    <p class="guidance-item-desc">Instant notification warnings whenever energy usage approaches or exceeds defined limits.</p>
                                </div>
                            </div>

                            <!-- Item 5 -->
                            <div class="guidance-item">
                                <div class="guidance-item-icon">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </div>
                                <div>
                                    <div class="guidance-item-title">Audited Reporting &amp; Compliance</div>
                                    <p class="guidance-item-desc">Generates immutable historical records and audit-ready PDF reports for university administration.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Bar -->
    <section class="stats-bar">
        <div class="container">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= count($campuses) > 0 ? count($campuses) : '4' ?></div>
                        <div class="stat-label">Campuses Monitored</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($officeCount) ?></div>
                        <div class="stat-label">Offices &amp; Units</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($applianceCount) ?></div>
                        <div class="stat-label">Audited Appliances</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format((float) ($totals['total_kwh'] ?? 0), 1) ?></div>
                        <div class="stat-label">Total kWh Recorded</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="text-center">
                <span class="section-tag">System Capabilities</span>
                <h2 class="section-title">Built for Institutional Sustainability</h2>
                <p class="section-desc">Designed exclusively for CHMSU to streamline institutional energy tracking, policy compliance, and green campus operations.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-box">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-calculator"></i>
                        </div>
                        <h4 class="feature-title">Mathematical Formula Modeling</h4>
                        <p class="feature-text">Applies standardized energy equations: <code>kWh = (Qty × Watts × Hours) ÷ 1000</code> with dynamic electricity rate and emission factor multiplication.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-box">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h4 class="feature-title">Role &amp; Scope Access Control</h4>
                        <p class="feature-text">Provides strictly scoped views for campus directors and office coordinators, ensuring staff manage only their authorized organizational assets.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-box">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-cloud-haze2"></i>
                        </div>
                        <h4 class="feature-title">Carbon Emission Accounting</h4>
                        <p class="feature-text">Translates raw electricity usage directly into kilograms of CO2 equivalent using official grid emission factors for environmental reporting.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-box">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-bell"></i>
                        </div>
                        <h4 class="feature-title">Early Threshold Warnings</h4>
                        <p class="feature-text">Configurable percentage thresholds alert administrators when an office or campus approaches its monthly electricity allocation.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-box">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h4 class="feature-title">Immutable Historical Records</h4>
                        <p class="feature-text">Snapshot architecture freezes all historical calculations so past reports remain accurate even when electricity rates or appliance configurations change.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-box">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-printer"></i>
                        </div>
                        <h4 class="feature-title">Administrative PDF Export</h4>
                        <p class="feature-text">Generates polished, multi-page PDF reports for campus consumption summaries, appliance inventories, and institutional budget reviews.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Campuses Section -->
    <section class="campuses-section" id="campuses">
        <div class="container">
            <div class="text-center">
                <span class="section-tag">University Network</span>
                <h2 class="section-title">Participating CHMSU Campuses</h2>
                <p class="section-desc">Unified energy tracking across all major Carlos Hilado Memorial State University campuses.</p>
            </div>

            <div class="row g-4">
                <?php if ($campuses === []): ?>
                    <div class="col-12 text-center text-muted py-4">No campus data available.</div>
                <?php else: ?>
                    <?php foreach ($campuses as $campus): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="campus-card">
                                <div class="campus-card-header">
                                    <div class="small text-white-50">Campus Code: <?= View::e($campus['code'] ?? 'CHMSU') ?></div>
                                    <h5 class="mb-0 fw-bold"><?= View::e($campus['name']) ?></h5>
                                </div>
                                <div class="campus-card-body">
                                    <div class="d-flex align-items-center text-muted small mb-2">
                                        <i class="bi bi-geo-alt me-2 text-success"></i>Negros Occidental, Philippines
                                    </div>
                                    <div class="d-flex align-items-center text-muted small">
                                        <i class="bi bi-building-check me-2 text-success"></i>Integrated Energy Scopes
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="features-section" id="how-it-works">
        <div class="container">
            <div class="text-center">
                <span class="section-tag">Operational Workflow</span>
                <h2 class="section-title">How Eco Metrics Operates</h2>
                <p class="section-desc">A structured four-step methodology ensuring accurate, reliable energy intelligence.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-3">
                    <div class="feature-box text-center">
                        <div class="feature-icon-wrapper mx-auto">1</div>
                        <h5 class="feature-title">Catalog Inventory</h5>
                        <p class="feature-text">Register appliances per campus and office with nameplate wattage and operational schedules.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-box text-center">
                        <div class="feature-icon-wrapper mx-auto">2</div>
                        <h5 class="feature-title">Record Usage</h5>
                        <p class="feature-text">Log reporting cycles and operational hours to automatically compute energy consumption.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-box text-center">
                        <div class="feature-icon-wrapper mx-auto">3</div>
                        <h5 class="feature-title">Monitor &amp; Alert</h5>
                        <p class="feature-text">Compare recorded totals against defined thresholds with automated warning triggers.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-box text-center">
                        <div class="feature-icon-wrapper mx-auto">4</div>
                        <h5 class="feature-title">Report &amp; Conserve</h5>
                        <p class="feature-text">Export executive PDF reports and analyze trend data to drive energy conservation decisions.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="chmsu-footer" id="contact">
        <div class="container">
            <div class="row g-4 mb-4">
                <div class="col-lg-5">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="/assets/img/chmsu-logo.jpg" alt="CHMSU Logo" style="width: 38px; height: 38px; border-radius: 50%; background: #fff;">
                        <span class="text-white fw-bold fs-5">Eco Metrics</span>
                    </div>
                    <p class="mb-2">Energy &amp; Carbon Footprint Monitoring System for Carlos Hilado Memorial State University.</p>
                    <p class="small text-white-50 mb-0">Empowering institutional sustainability through data-driven energy accounting.</p>
                </div>

                <div class="col-sm-6 col-lg-3 offset-lg-1">
                    <h6 class="text-white fw-bold mb-3">Quick Navigation</h6>
                    <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#campuses">Campuses</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="/login">Portal Login</a></li>
                    </ul>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <h6 class="text-white fw-bold mb-3">Institution</h6>
                    <div class="small mb-2">Carlos Hilado Memorial State University</div>
                    <div class="small mb-2">Talisay City, Negros Occidental, Philippines</div>
                    <div class="small"><i class="bi bi-envelope me-1"></i> info@chmsu.edu.ph</div>
                </div>
            </div>

            <hr class="border-secondary opacity-25 my-4">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small">
                <div>&copy; <?= date('Y') ?> Carlos Hilado Memorial State University. All rights reserved.</div>
                <div>Eco Metrics Campus Energy Portal</div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
