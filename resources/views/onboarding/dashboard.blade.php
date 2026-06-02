<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('zatca::onboarding.title') }}</title>
    <style>
#dashboard-app {
            --canvas: #f4efe6;
            --ink: #11212d;
            --ink-soft: #415865;
            --card: rgba(255, 252, 247, 0.82);
            --card-strong: #fffaf2;
            --line: rgba(17, 33, 45, 0.12);
            --accent: #d06224;
            --accent-soft: #f3c8aa;
            --teal: #0d7c80;
            --gold: #d6a53a;
            --danger: #bb3e3e;
            --warning: #c07f1f;
            --success: #227453;
            --shadow: 0 24px 60px rgba(17, 33, 45, 0.12);
            --radius-xl: 28px;
            --radius-lg: 20px;
            --radius-md: 14px;
            --font-ui: "Segoe UI Variable", "Noto Sans Arabic", "Tahoma", sans-serif;
            --font-display: "Trebuchet MS", "Segoe UI Variable", "Noto Sans Arabic", sans-serif;
        }

        #dashboard-app,
        #dashboard-app * {
            box-sizing: border-box;
        }

        #dashboard-app {
            font-family: var(--font-ui);
            color: var(--ink);
            background:
                radial-gradient(circle at top {{ $direction === 'rtl' ? 'right' : 'left' }}, rgba(208, 98, 36, 0.16), transparent 28%),
                radial-gradient(circle at 85% 18%, rgba(13, 124, 128, 0.12), transparent 24%),
                linear-gradient(180deg, #f8f3eb 0%, var(--canvas) 48%, #efe6d7 100%);
            min-height: 100vh;
        }

        #dashboard-app .shell {
            display: grid;
            grid-template-columns: 340px minmax(0, 1fr);
            min-height: 100vh;
        }

        #dashboard-app .sidebar {
            position: sticky;
            top: 0;
            min-height: 100vh;
            padding: 28px 24px;
            backdrop-filter: blur(18px);
            background: linear-gradient(180deg, rgba(17, 33, 45, 0.94), rgba(20, 47, 59, 0.9));
            color: #fdf8ef;
            border-inline-end: 1px solid rgba(255, 255, 255, 0.08);
        }

        #dashboard-app .brand {
            display: grid;
            gap: 10px;
            margin-bottom: 24px;
        }

        #dashboard-app .brand-badge {
            width: 62px;
            height: 62px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--accent), #efb062);
            color: white;
            font-weight: 800;
            letter-spacing: 0.08em;
            box-shadow: 0 14px 30px rgba(208, 98, 36, 0.35);
        }

        #dashboard-app .brand h1 {
            margin: 0;
            font-family: var(--font-display);
            font-size: 1.3rem;
            line-height: 1.2;
        }

        #dashboard-app .brand p {
            margin: 0;
            color: rgba(253, 248, 239, 0.72);
            line-height: 1.5;
            font-size: 0.95rem;
        }

        #dashboard-app .toolbar,
        #dashboard-app .locale-switch {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        #dashboard-app .sidebar-search {
            margin-top: 18px;
        }

        #dashboard-app .toolbar { margin-bottom: 18px; }

        #dashboard-app .button,
        #dashboard-app button,
        #dashboard-app .input,
        #dashboard-app select,
        #dashboard-app textarea {
            font: inherit;
        }

        #dashboard-app .button {
            border: 0;
            border-radius: 999px;
            padding: 12px 16px;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        #dashboard-app .button:hover { transform: translateY(-1px); }

        #dashboard-app .button:disabled {
            opacity: 0.56;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        #dashboard-app .button-primary {
            background: linear-gradient(135deg, var(--accent), #f09f5a);
            color: white;
            box-shadow: 0 12px 24px rgba(208, 98, 36, 0.24);
        }

        #dashboard-app .button-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #fff7ea;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        #dashboard-app .button-ghost {
            background: rgba(17, 33, 45, 0.06);
            color: var(--ink);
            border: 1px solid var(--line);
        }

        #dashboard-app .tenant-list {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }

        #dashboard-app .tenant-card {
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.04);
            cursor: pointer;
            transition: background 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
        }

        #dashboard-app .tenant-card:hover,
        #dashboard-app .tenant-card.active {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(240, 159, 90, 0.42);
            transform: translateY(-1px);
        }

        #dashboard-app .tenant-card h3 {
            margin: 0 0 6px;
            font-size: 1rem;
        }

        #dashboard-app .tenant-card p {
            margin: 0;
            color: rgba(253, 248, 239, 0.72);
            font-size: 0.9rem;
        }

        #dashboard-app .tenant-card small {
            display: block;
            margin-top: 10px;
            color: rgba(253, 248, 239, 0.6);
        }

        #dashboard-app .chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        #dashboard-app .chip {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 248, 234, 0.88);
        }

        #dashboard-app .main {
            padding: 34px clamp(18px, 3vw, 36px);
            display: grid;
            gap: 20px;
        }

        #dashboard-app .hero {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-xl);
            padding: 28px;
            background:
                linear-gradient(130deg, rgba(255, 250, 242, 0.96), rgba(248, 241, 230, 0.82)),
                linear-gradient(135deg, rgba(13, 124, 128, 0.18), rgba(208, 98, 36, 0.18));
            border: 1px solid rgba(255,255,255,0.72);
            box-shadow: var(--shadow);
        }

        #dashboard-app .hero::after {
            content: "";
            position: absolute;
            inset: auto -60px -80px auto;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(208, 98, 36, 0.2), transparent 68%);
        }

        #dashboard-app .hero h2 {
            margin: 0;
            font-family: var(--font-display);
            font-size: clamp(1.8rem, 2vw, 2.5rem);
            line-height: 1.1;
            max-width: 720px;
        }

        #dashboard-app .hero p {
            margin: 12px 0 0;
            max-width: 760px;
            color: var(--ink-soft);
            line-height: 1.6;
        }

        #dashboard-app .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.9fr);
            gap: 18px;
            align-items: start;
        }

        #dashboard-app .hero-copy {
            display: grid;
            gap: 10px;
        }

        #dashboard-app .hero-kicker {
            display: inline-flex;
            width: fit-content;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(13, 124, 128, 0.12);
            color: var(--teal);
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
        }

        #dashboard-app .hero-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        #dashboard-app .hero-metric {
            border-radius: 20px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(255,255,255,0.8);
            box-shadow: 0 12px 24px rgba(17, 33, 45, 0.08);
        }

        #dashboard-app .hero-metric span {
            display: block;
            color: var(--ink-soft);
            font-size: 0.78rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        #dashboard-app .hero-metric strong {
            display: block;
            margin-top: 10px;
            font-size: 1rem;
            line-height: 1.3;
        }

        #dashboard-app .workspace-nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        #dashboard-app .workspace-tab {
            border: 1px solid rgba(17, 33, 45, 0.1);
            background: rgba(255, 255, 255, 0.78);
            color: var(--ink);
            border-radius: 999px;
            padding: 12px 16px;
            cursor: pointer;
            transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
        }

        #dashboard-app .workspace-tab:hover,
        #dashboard-app .workspace-tab.active {
            transform: translateY(-1px);
            border-color: rgba(208, 98, 36, 0.3);
            background: linear-gradient(135deg, rgba(208, 98, 36, 0.12), rgba(240, 159, 90, 0.16));
            color: var(--accent);
        }

        #dashboard-app .grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 18px;
        }

        #dashboard-app .workspace-view {
            display: none;
        }

        #dashboard-app .workspace-view.active {
            display: grid;
        }

        #dashboard-app .panel {
            grid-column: span 12;
            background: var(--card);
            border: 1px solid rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(12px);
            border-radius: var(--radius-lg);
            padding: 22px;
            box-shadow: var(--shadow);
        }

        #dashboard-app .panel h3 {
            margin: 0 0 14px;
            font-size: 1.05rem;
        }

        #dashboard-app .panel-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: start;
            margin-bottom: 16px;
        }

        #dashboard-app .panel-head p {
            margin: 4px 0 0;
            color: var(--ink-soft);
            line-height: 1.5;
        }

        #dashboard-app .panel.profile { grid-column: span 7; }
        #dashboard-app .panel.actions { grid-column: span 5; }
        #dashboard-app .panel.invoice-submit { grid-column: span 7; }
        #dashboard-app .panel.invoice-history { grid-column: span 5; }
        #dashboard-app .panel.alerts { grid-column: span 12; }
        #dashboard-app .panel.notifications { grid-column: span 12; }
        #dashboard-app .panel.health,
        #dashboard-app .panel.credentials,
        #dashboard-app .panel.invoice-state { grid-column: span 4; }

        #dashboard-app .metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        #dashboard-app .metric {
            border-radius: 18px;
            padding: 16px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.8), rgba(245, 238, 226, 0.88));
            border: 1px solid var(--line);
        }

        #dashboard-app .metric-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--ink-soft);
        }

        #dashboard-app .metric-value {
            margin-top: 8px;
            font-size: 1.1rem;
            font-weight: 700;
        }

        #dashboard-app .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        #dashboard-app .field {
            display: grid;
            gap: 8px;
        }

        #dashboard-app .field.span-2 { grid-column: span 2; }

        #dashboard-app .field label {
            font-size: 0.86rem;
            color: var(--ink-soft);
        }

        #dashboard-app .input,
        #dashboard-app select,
        #dashboard-app textarea {
            width: 100%;
            border-radius: 14px;
            border: 1px solid rgba(17, 33, 45, 0.12);
            background: rgba(255, 255, 255, 0.9);
            padding: 12px 14px;
            color: var(--ink);
        }

        #dashboard-app textarea { min-height: 90px; resize: vertical; }

        #dashboard-app .stack {
            display: grid;
            gap: 14px;
        }

        #dashboard-app .credential-card,
        #dashboard-app .health-card,
        #dashboard-app .state-card {
            border-radius: 18px;
            padding: 16px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.74);
        }

        #dashboard-app .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        #dashboard-app .status-healthy { background: rgba(34, 116, 83, 0.12); color: var(--success); }
        #dashboard-app .status-warning { background: rgba(192, 127, 31, 0.12); color: var(--warning); }
        #dashboard-app .status-error { background: rgba(187, 62, 62, 0.12); color: var(--danger); }

        #dashboard-app .issue-list {
            margin: 12px 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 8px;
        }

        #dashboard-app .issue-list li {
            border-radius: 12px;
            padding: 10px 12px;
            background: rgba(17, 33, 45, 0.04);
            color: var(--ink-soft);
        }

        #dashboard-app .filter-row {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }

        #dashboard-app .filter-row.compact {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            align-items: end;
        }

        #dashboard-app .filter-actions {
            grid-column: span 2;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        #dashboard-app .filter-actions .button {
            flex: 1 1 180px;
            min-width: 0;
        }

        #dashboard-app .pagination-row {
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        #dashboard-app .pagination-row p {
            margin: 0;
            color: var(--ink-soft);
        }

        #dashboard-app .hook-card {
            border-radius: 18px;
            padding: 16px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.82);
        }

        #dashboard-app .step-card {
            border-radius: 20px;
            padding: 18px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(248, 243, 234, 0.92));
        }

        #dashboard-app .step-head {
            display: flex;
            gap: 14px;
            align-items: start;
            margin-bottom: 14px;
        }

        #dashboard-app .step-badge {
            width: 38px;
            height: 38px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: rgba(208, 98, 36, 0.14);
            color: var(--accent);
            font-weight: 800;
        }

        #dashboard-app .step-head h4 {
            margin: 0;
            font-size: 1rem;
        }

        #dashboard-app .step-head p {
            margin: 4px 0 0;
            color: var(--ink-soft);
            line-height: 1.5;
        }

        #dashboard-app details.advanced {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px dashed rgba(17, 33, 45, 0.12);
        }

        #dashboard-app details.advanced summary {
            cursor: pointer;
            color: var(--teal);
            font-weight: 600;
            margin-bottom: 12px;
        }

        #dashboard-app .alert-card {
            border-radius: 18px;
            padding: 16px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.82);
        }

        #dashboard-app .alert-card.warning {
            border-color: rgba(192, 127, 31, 0.25);
            background: rgba(255, 246, 229, 0.92);
        }

        #dashboard-app .alert-card.error {
            border-color: rgba(187, 62, 62, 0.25);
            background: rgba(255, 241, 241, 0.94);
        }

        #dashboard-app .alert-card h4 {
            margin: 0 0 8px;
            font-size: 0.96rem;
        }

        #dashboard-app .alert-card p {
            margin: 0;
            color: var(--ink-soft);
            line-height: 1.55;
        }

        #dashboard-app .check-row {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
            color: var(--ink-soft);
        }

        #dashboard-app .check-row label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #dashboard-app .meta-list {
            display: grid;
            gap: 10px;
        }

        #dashboard-app .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: start;
            border-bottom: 1px dashed rgba(17, 33, 45, 0.08);
            padding-bottom: 10px;
        }

        #dashboard-app .meta-row:last-child { border-bottom: 0; padding-bottom: 0; }

        #dashboard-app .meta-row span:first-child { color: var(--ink-soft); }

        #dashboard-app .notice {
            border-radius: 18px;
            padding: 14px 16px;
            background: rgba(13, 124, 128, 0.08);
            border: 1px solid rgba(13, 124, 128, 0.18);
            color: var(--ink);
        }

        #dashboard-app .notice.warning {
            background: rgba(192, 127, 31, 0.1);
            border-color: rgba(192, 127, 31, 0.24);
        }

        #dashboard-app .response-box {
            white-space: pre-wrap;
            word-break: break-word;
            border-radius: 18px;
            background: rgba(17, 33, 45, 0.92);
            color: #fef8ed;
            padding: 18px;
            min-height: 150px;
            overflow: auto;
            font-size: 0.9rem;
            line-height: 1.55;
        }

        #dashboard-app .invoice-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        #dashboard-app .link-button {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 9px 12px;
            text-decoration: none;
            color: var(--ink);
            background: rgba(255, 255, 255, 0.7);
        }

        #dashboard-app .drawer {
            position: fixed;
            inset: 0;
            display: grid;
            justify-items: end;
            background: rgba(17, 33, 45, 0.42);
            backdrop-filter: blur(8px);
            z-index: 40;
        }

        #dashboard-app .drawer-panel {
            width: min(760px, 100vw);
            height: 100vh;
            overflow: auto;
            padding: 28px 24px;
            background: linear-gradient(180deg, rgba(255, 250, 242, 0.98), rgba(244, 239, 230, 0.98));
            box-shadow: -24px 0 60px rgba(17, 33, 45, 0.16);
            display: grid;
            gap: 18px;
        }

        #dashboard-app .drawer-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 14px;
        }

        #dashboard-app .drawer-card {
            border-radius: 18px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.8);
            padding: 16px;
        }

        #dashboard-app .drawer-card h4 {
            margin: 0 0 12px;
            font-size: 0.95rem;
        }

        #dashboard-app .drawer-card pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 0.85rem;
            line-height: 1.55;
            color: var(--ink-soft);
        }

        #dashboard-app .hidden { display: none !important; }

        @media (max-width: 1200px) {
            #dashboard-app .panel.profile,
        #dashboard-app .panel.actions,
        #dashboard-app .panel.invoice-submit,
        #dashboard-app .panel.invoice-history,
        #dashboard-app .panel.health,
        #dashboard-app .panel.credentials,
        #dashboard-app .panel.invoice-state { grid-column: span 12; }
        }

        @media (max-width: 900px) {
            #dashboard-app .shell { grid-template-columns: 1fr; }
            #dashboard-app .sidebar { position: static; min-height: auto; }
            #dashboard-app .hero-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 720px) {
            #dashboard-app .metrics,
        #dashboard-app .form-grid { grid-template-columns: 1fr; }
            #dashboard-app .filter-row { grid-template-columns: 1fr; }
            #dashboard-app .field.span-2 { grid-column: span 1; }
            #dashboard-app .main { padding: 20px 14px 30px; }
            #dashboard-app .sidebar { padding: 18px 14px; }
        }
    </style>
</head>
<body>
@php
    $ui = [
        'title' => __('zatca::onboarding.title'),
        'subtitle' => __('zatca::onboarding.subtitle'),
        'newTenant' => __('zatca::onboarding.new_tenant'),
        'refresh' => __('zatca::onboarding.refresh'),
        'emptyState' => __('zatca::onboarding.empty_state'),
        'profile' => __('zatca::onboarding.profile'),
        'health' => __('zatca::onboarding.health'),
        'credentials' => __('zatca::onboarding.credentials'),
        'invoiceState' => __('zatca::onboarding.invoice_state'),
        'invoiceSubmission' => __('zatca::onboarding.invoice_submission'),
        'recentInvoices' => __('zatca::onboarding.recent_invoices'),
        'actions' => __('zatca::onboarding.actions'),
        'createTenant' => __('zatca::onboarding.create_tenant'),
        'updateTenant' => __('zatca::onboarding.update_tenant'),
        'generateCsr' => __('zatca::onboarding.generate_csr'),
        'issueCompliance' => __('zatca::onboarding.issue_compliance'),
        'issueProduction' => __('zatca::onboarding.issue_production'),
        'locale' => __('zatca::onboarding.locale'),
        'timezone' => __('zatca::onboarding.timezone'),
        'environment' => __('zatca::onboarding.environment'),
        'mode' => __('zatca::onboarding.mode'),
        'status' => __('zatca::onboarding.status'),
        'key' => __('zatca::onboarding.key'),
        'healthStatus' => __('zatca::onboarding.health_status'),
        'certificateVat' => __('zatca::onboarding.certificate_vat'),
        'certificateExpiry' => __('zatca::onboarding.certificate_expiry'),
        'sellerName' => __('zatca::onboarding.seller_name'),
        'sellerNameAr' => __('zatca::onboarding.seller_name_ar'),
        'legalName' => __('zatca::onboarding.legal_name'),
        'legalNameAr' => __('zatca::onboarding.legal_name_ar'),
        'branchName' => __('zatca::onboarding.branch_name'),
        'branchNameAr' => __('zatca::onboarding.branch_name_ar'),
        'vatNumber' => __('zatca::onboarding.vat_number'),
        'crn' => __('zatca::onboarding.crn'),
        'street' => __('zatca::onboarding.street'),
        'district' => __('zatca::onboarding.district'),
        'city' => __('zatca::onboarding.city'),
        'buildingNumber' => __('zatca::onboarding.building_number'),
        'additionalNumber' => __('zatca::onboarding.additional_number'),
        'postalCode' => __('zatca::onboarding.postal_code'),
        'countryCode' => __('zatca::onboarding.country_code'),
        'commonName' => __('zatca::onboarding.common_name'),
        'serialNumber' => __('zatca::onboarding.serial_number'),
        'organizationIdentifier' => __('zatca::onboarding.organization_identifier'),
        'organizationUnitName' => __('zatca::onboarding.organization_unit_name'),
        'organizationName' => __('zatca::onboarding.organization_name'),
        'countryName' => __('zatca::onboarding.country_name'),
        'invoiceType' => __('zatca::onboarding.invoice_type'),
        'locationAddress' => __('zatca::onboarding.location_address'),
        'industryBusinessCategory' => __('zatca::onboarding.industry_business_category'),
        'simulation' => __('zatca::onboarding.simulation'),
        'nonProduction' => __('zatca::onboarding.non_production'),
        'otp' => __('zatca::onboarding.otp'),
        'buyerName' => __('zatca::onboarding.buyer_name'),
        'buyerVatNumber' => __('zatca::onboarding.buyer_vat_number'),
        'buyerCity' => __('zatca::onboarding.buyer_city'),
        'buyerStreet' => __('zatca::onboarding.buyer_street'),
        'itemName' => __('zatca::onboarding.item_name'),
        'quantity' => __('zatca::onboarding.quantity'),
        'unitPrice' => __('zatca::onboarding.unit_price'),
        'taxPercent' => __('zatca::onboarding.tax_percent'),
        'submitInvoice' => __('zatca::onboarding.submit_invoice'),
        'yes' => __('zatca::onboarding.yes'),
        'no' => __('zatca::onboarding.no'),
        'selectTenant' => __('zatca::onboarding.select_tenant'),
        'submissionSuccess' => __('zatca::onboarding.submission_success'),
        'submissionFailed' => __('zatca::onboarding.submission_failed'),
        'issuedAt' => __('zatca::onboarding.issued_at'),
        'nextIcv' => __('zatca::onboarding.next_icv'),
        'previousInvoiceHash' => __('zatca::onboarding.previous_invoice_hash'),
        'lastValidatedAt' => __('zatca::onboarding.last_validated_at'),
        'liveResponse' => __('zatca::onboarding.live_response'),
        'liveResponseSubtitle' => __('zatca::onboarding.live_response_subtitle'),
        'signer' => __('zatca::onboarding.signer'),
        'privateKeyPresent' => __('zatca::onboarding.private_key_present'),
        'complianceCsidPresent' => __('zatca::onboarding.compliance_csid_present'),
        'productionCsidPresent' => __('zatca::onboarding.production_csid_present'),
        'complianceRequestId' => __('zatca::onboarding.compliance_request_id'),
        'lastInvoiceUuid' => __('zatca::onboarding.last_invoice_uuid'),
        'lastInvoiceHash' => __('zatca::onboarding.last_invoice_hash'),
        'reporting' => __('zatca::onboarding.reporting'),
        'clearance' => __('zatca::onboarding.clearance'),
        'submittedAt' => __('zatca::onboarding.submitted_at'),
        'reportingStatus' => __('zatca::onboarding.reporting_status'),
        'clearanceStatus' => __('zatca::onboarding.clearance_status'),
        'invoiceNumber' => __('zatca::onboarding.invoice_number'),
        'invoiceUuid' => __('zatca::onboarding.invoice_uuid'),
        'invoiceTypeCode' => __('zatca::onboarding.invoice_type_code'),
        'invoiceNotes' => __('zatca::onboarding.invoice_notes'),
        'invoiceHistoryEmpty' => __('zatca::onboarding.invoice_history_empty'),
        'liveInvoiceFlow' => __('zatca::onboarding.live_invoice_flow'),
        'invoiceDetail' => __('zatca::onboarding.invoice_detail'),
        'openInvoiceDetail' => __('zatca::onboarding.open_invoice_detail'),
        'downloadXml' => __('zatca::onboarding.download_xml'),
        'downloadSignedXml' => __('zatca::onboarding.download_signed_xml'),
        'downloadApiResponse' => __('zatca::onboarding.download_api_response'),
        'savedXml' => __('zatca::onboarding.saved_xml'),
        'signedXml' => __('zatca::onboarding.signed_xml'),
        'rawApiResponse' => __('zatca::onboarding.raw_api_response'),
        'close' => __('zatca::onboarding.close'),
        'alerts' => __('zatca::onboarding.alerts'),
        'alertsEmpty' => __('zatca::onboarding.alerts_empty'),
        'searchTenants' => __('zatca::onboarding.search_tenants'),
        'searchInvoices' => __('zatca::onboarding.search_invoices'),
        'filterMode' => __('zatca::onboarding.filter_mode'),
        'filterStatus' => __('zatca::onboarding.filter_status'),
        'filterDateFrom' => __('zatca::onboarding.filter_date_from'),
        'filterDateTo' => __('zatca::onboarding.filter_date_to'),
        'allModes' => __('zatca::onboarding.all_modes'),
        'allStatuses' => __('zatca::onboarding.all_statuses'),
        'submitted' => __('zatca::onboarding.submitted'),
        'failed' => __('zatca::onboarding.failed'),
        'invoiceFilters' => __('zatca::onboarding.invoice_filters'),
        'healthAlert' => __('zatca::onboarding.health_alert'),
        'submissionAlert' => __('zatca::onboarding.submission_alert'),
        'applyFilters' => __('zatca::onboarding.apply_filters'),
        'clearFilters' => __('zatca::onboarding.clear_filters'),
        'paginationSummary' => __('zatca::onboarding.pagination_summary'),
        'previousPage' => __('zatca::onboarding.previous_page'),
        'nextPage' => __('zatca::onboarding.next_page'),
        'notificationHooks' => __('zatca::onboarding.notification_hooks'),
        'notificationHooksSubtitle' => __('zatca::onboarding.notification_hooks_subtitle'),
        'notificationHooksEmpty' => __('zatca::onboarding.notification_hooks_empty'),
        'hookName' => __('zatca::onboarding.hook_name'),
        'hookChannel' => __('zatca::onboarding.hook_channel'),
        'hookEvents' => __('zatca::onboarding.hook_events'),
        'hookTargetUrl' => __('zatca::onboarding.hook_target_url'),
        'hookSecret' => __('zatca::onboarding.hook_secret'),
        'hookActive' => __('zatca::onboarding.hook_active'),
        'saveHook' => __('zatca::onboarding.save_hook'),
        'updateHook' => __('zatca::onboarding.update_hook'),
        'deleteHook' => __('zatca::onboarding.delete_hook'),
        'editHook' => __('zatca::onboarding.edit_hook'),
        'cancelEdit' => __('zatca::onboarding.cancel_edit'),
        'notificationHookSaved' => __('zatca::onboarding.notification_hook_saved'),
        'notificationHookDeleted' => __('zatca::onboarding.notification_hook_deleted'),
        'lastNotifiedAt' => __('zatca::onboarding.last_notified_at'),
        'workspaceOverview' => __('zatca::onboarding.workspace_overview'),
        'workspaceSetup' => __('zatca::onboarding.workspace_setup'),
        'workspaceInvoices' => __('zatca::onboarding.workspace_invoices'),
        'workspaceMonitoring' => __('zatca::onboarding.workspace_monitoring'),
        'workspaceStatus' => __('zatca::onboarding.workspace_status'),
        'workspaceKey' => __('zatca::onboarding.workspace_key'),
        'workspaceEnvironment' => __('zatca::onboarding.workspace_environment'),
        'workspaceChain' => __('zatca::onboarding.workspace_chain'),
        'ready' => __('zatca::onboarding.ready'),
        'invoiceSubmissionLocked' => __('zatca::onboarding.invoice_submission_locked') !== 'zatca::onboarding.invoice_submission_locked'
            ? __('zatca::onboarding.invoice_submission_locked')
            : 'Invoice submission is locked for this environment until onboarding is complete.',
        'invoiceSubmissionReady' => __('zatca::onboarding.invoice_submission_ready') !== 'zatca::onboarding.invoice_submission_ready'
            ? __('zatca::onboarding.invoice_submission_ready')
            : 'This environment is ready for invoice submission.',
        'invoiceSubmissionSelectEnvironment' => __('zatca::onboarding.invoice_submission_select_environment') !== 'zatca::onboarding.invoice_submission_select_environment'
            ? __('zatca::onboarding.invoice_submission_select_environment')
            : 'Choose an environment to check invoice readiness.',
    ];
@endphp
<div id="dashboard-app"
     data-locale='@json($locale)'
     data-direction='@json($direction)'
     data-api-prefix='@json($apiPrefix)'
     data-dashboard-prefix='@json($dashboardPrefix)'
     data-can-manage-tenants='@json($canManageTenants)'
     data-show-tenant-switcher='@json($showTenantSwitcher)'
     data-simple-mode='@json($simpleMode ?? false)'
     data-ui='@json($ui)'
     data-tenants='@json($tenants)'
     data-selected='@json($selectedTenant)'
     data-invoices='@json($selectedInvoices)'>
    <div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-badge">ZT</div>
            <div>
                <h1>{{ __('zatca::onboarding.title') }}</h1>
                <p>{{ __('zatca::onboarding.subtitle') }}</p>
            </div>
        </div>

        <div class="toolbar">
            @if ($canManageTenants)
                <button class="button button-primary" type="button" id="new-tenant-toggle">{{ __('zatca::onboarding.new_tenant') }}</button>
            @endif
            <button class="button button-secondary" type="button" id="refresh-tenants">{{ __('zatca::onboarding.refresh') }}</button>
        </div>

        <div class="locale-switch">
            <a class="button button-secondary" href="{{ $dashboardPrefix }}?lang=en">EN</a>
            <a class="button button-secondary" href="{{ $dashboardPrefix }}?lang=ar">AR</a>
        </div>

        <div class="sidebar-search{{ $showTenantSwitcher ? '' : ' hidden' }}">
            <input class="input" id="tenant-search" type="search" placeholder="{{ __('zatca::onboarding.search_tenants') }}">
        </div>

        <div class="tenant-list{{ $showTenantSwitcher ? '' : ' hidden' }}" id="tenant-list"></div>
    </aside>

    <main class="main">
        <section class="hero">
            <div class="hero-grid">
                <div class="hero-copy">
                    <span class="hero-kicker">ZATCA Workspace</span>
                    <h2 id="hero-title">{{ __('zatca::onboarding.select_tenant') }}</h2>
                    <p id="hero-subtitle">{{ __('zatca::onboarding.subtitle') }}</p>
                </div>
                <div class="hero-metrics">
                    <article class="hero-metric">
                        <span>{{ __('zatca::onboarding.workspace_status') }}</span>
                        <strong id="hero-metric-status">{{ __('zatca::onboarding.select_tenant') }}</strong>
                    </article>
                    <article class="hero-metric">
                        <span>{{ __('zatca::onboarding.workspace_key') }}</span>
                        <strong id="hero-metric-key">-</strong>
                    </article>
                    <article class="hero-metric">
                        <span>{{ __('zatca::onboarding.workspace_environment') }}</span>
                        <strong id="hero-metric-environment">-</strong>
                    </article>
                    <article class="hero-metric">
                        <span>{{ __('zatca::onboarding.workspace_chain') }}</span>
                        <strong id="hero-metric-chain">-</strong>
                    </article>
                </div>
            </div>
        </section>

        <div class="notice hidden" id="feedback-box"></div>

        @if ($canManageTenants)
            <section class="panel hidden" id="create-tenant-panel">
                <div class="panel-head">
                    <div>
                        <h3>{{ __('zatca::onboarding.create_tenant') }}</h3>
                        <p>{{ __('zatca::onboarding.subtitle') }}</p>
                    </div>
                </div>

                <form class="form-grid" id="create-tenant-form">
                    <div class="field"><label>Key</label><input class="input" name="key" required></div>
                    <div class="field"><label>{{ __('zatca::onboarding.locale') }}</label><select class="input" name="locale"><option value="en">English</option><option value="ar">العربية</option></select></div>
                    <div class="field"><label>{{ __('zatca::onboarding.legal_name') }}</label><input class="input" name="legal_name" required></div>
                    <div class="field"><label>{{ __('zatca::onboarding.legal_name_ar') }}</label><input class="input" name="legal_name_ar"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.seller_name') }}</label><input class="input" name="seller_name"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.seller_name_ar') }}</label><input class="input" name="seller_name_ar"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.vat_number') }}</label><input class="input" name="vat_number" required></div>
                    <div class="field"><label>{{ __('zatca::onboarding.crn') }}</label><input class="input" name="crn"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.branch_name') }}</label><input class="input" name="branch_name"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.branch_name_ar') }}</label><input class="input" name="branch_name_ar"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.street') }}</label><input class="input" name="street"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.district') }}</label><input class="input" name="district"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.city') }}</label><input class="input" name="city"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.postal_code') }}</label><input class="input" name="postal_code"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.building_number') }}</label><input class="input" name="building_number"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.additional_number') }}</label><input class="input" name="additional_number"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.country_code') }}</label><input class="input" name="country_code" value="SA"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.timezone') }}</label><input class="input" name="timezone" value="Asia/Riyadh"></div>
                    <div class="field span-2">
                        <button class="button button-primary" type="submit">{{ __('zatca::onboarding.create_tenant') }}</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="workspace-nav" id="workspace-nav">
            <button class="workspace-tab active" type="button" data-view="overview">{{ __('zatca::onboarding.workspace_overview') }}</button>
            <button class="workspace-tab" type="button" data-view="setup">{{ __('zatca::onboarding.workspace_setup') }}</button>
            <button class="workspace-tab" type="button" data-view="invoices">{{ __('zatca::onboarding.workspace_invoices') }}</button>
            <button class="workspace-tab" type="button" data-view="monitoring">{{ __('zatca::onboarding.workspace_monitoring') }}</button>
        </section>

        <div class="grid workspace-view" data-view-panel="setup">
            <section class="panel profile">
                <div class="panel-head">
                    <div>
                        <h3>{{ __('zatca::onboarding.profile') }}</h3>
                        <p>{{ __('zatca::onboarding.subtitle') }}</p>
                    </div>
                    <span class="status-pill" id="tenant-status-pill"></span>
                </div>

                <form class="form-grid" id="profile-form">
                    <div class="field"><label>{{ __('zatca::onboarding.legal_name') }}</label><input class="input" name="legal_name"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.legal_name_ar') }}</label><input class="input" name="legal_name_ar"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.seller_name') }}</label><input class="input" name="seller_name"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.seller_name_ar') }}</label><input class="input" name="seller_name_ar"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.branch_name') }}</label><input class="input" name="branch_name"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.branch_name_ar') }}</label><input class="input" name="branch_name_ar"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.vat_number') }}</label><input class="input" name="vat_number"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.crn') }}</label><input class="input" name="crn"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.street') }}</label><input class="input" name="street"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.district') }}</label><input class="input" name="district"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.city') }}</label><input class="input" name="city"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.postal_code') }}</label><input class="input" name="postal_code"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.building_number') }}</label><input class="input" name="building_number"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.additional_number') }}</label><input class="input" name="additional_number"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.locale') }}</label><select class="input" name="locale"><option value="en">English</option><option value="ar">العربية</option></select></div>
                    <div class="field"><label>{{ __('zatca::onboarding.timezone') }}</label><input class="input" name="timezone"></div>
                    <div class="field span-2"><button class="button button-ghost" type="submit">{{ __('zatca::onboarding.update_tenant') }}</button></div>
                </form>
            </section>

            <section class="panel actions">
                <div class="panel-head">
                    <div>
                        <h3>{{ __('zatca::onboarding.actions') }}</h3>
                        <p>{{ __('zatca::onboarding.workspace_setup') }}</p>
                    </div>
                </div>

                <div class="stack">
                    <form class="step-card" id="csr-form">
                        <div class="step-head">
                            <div class="step-badge">01</div>
                            <div>
                                <h4>{{ __('zatca::onboarding.generate_csr') }}</h4>
                                <p>{{ __('zatca::onboarding.setup_csr_hint') }}</p>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field span-2"><label>{{ __('zatca::onboarding.environment') }}</label><select class="input" name="environment"><option value="sandbox">Sandbox</option><option value="simulation">Simulation</option><option value="production">Production</option></select></div>
                            <div class="field"><label>{{ __('zatca::onboarding.common_name') }}</label><input class="input" name="common_name" required></div>
                            <div class="field"><label>{{ __('zatca::onboarding.serial_number') }}</label><input class="input" name="serial_number" required></div>
                            <div class="field"><label>{{ __('zatca::onboarding.location_address') }}</label><input class="input" name="location_address" required></div>
                            <div class="field"><label>{{ __('zatca::onboarding.industry_business_category') }}</label><input class="input" name="industry_business_category" required></div>
                            <details class="advanced field span-2">
                                <summary>{{ __('zatca::onboarding.advanced_options') }}</summary>
                                <div class="form-grid">
                                    <div class="field"><label>{{ __('zatca::onboarding.organization_identifier') }}</label><input class="input" name="organization_identifier"></div>
                                    <div class="field"><label>{{ __('zatca::onboarding.organization_unit_name') }}</label><input class="input" name="organization_unit_name"></div>
                                    <div class="field"><label>{{ __('zatca::onboarding.organization_name') }}</label><input class="input" name="organization_name"></div>
                                    <div class="field"><label>{{ __('zatca::onboarding.country_name') }}</label><input class="input" name="country_name" value="SA"></div>
                                    <div class="field"><label>{{ __('zatca::onboarding.invoice_type') }}</label><input class="input" name="invoice_type" value="1100"></div>
                                    <div class="field"><label>{{ __('zatca::onboarding.simulation') }}</label><select class="input" name="simulation"><option value="false">{{ __('zatca::onboarding.no') }}</option><option value="true">{{ __('zatca::onboarding.yes') }}</option></select></div>
                                    <div class="field"><label>{{ __('zatca::onboarding.non_production') }}</label><select class="input" name="non_production"><option value="false">{{ __('zatca::onboarding.no') }}</option><option value="true">{{ __('zatca::onboarding.yes') }}</option></select></div>
                                </div>
                            </details>
                            <div class="field span-2"><button class="button button-primary" type="submit">{{ __('zatca::onboarding.generate_csr') }}</button></div>
                        </div>
                    </form>

                    <form class="step-card" id="compliance-form">
                        <div class="step-head">
                            <div class="step-badge">02</div>
                            <div>
                                <h4>{{ __('zatca::onboarding.issue_compliance') }}</h4>
                                <p>{{ __('zatca::onboarding.setup_compliance_hint') }}</p>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field span-2"><label>{{ __('zatca::onboarding.environment') }}</label><select class="input" name="environment"><option value="sandbox">Sandbox</option><option value="simulation">Simulation</option><option value="production">Production</option></select></div>
                            <div class="field span-2"><label>{{ __('zatca::onboarding.otp') }}</label><input class="input" name="otp" required></div>
                            <div class="field span-2"><button class="button button-primary" type="submit">{{ __('zatca::onboarding.issue_compliance') }}</button></div>
                        </div>
                    </form>

                    <form class="step-card" id="production-form">
                        <div class="step-head">
                            <div class="step-badge">03</div>
                            <div>
                                <h4>{{ __('zatca::onboarding.issue_production') }}</h4>
                                <p>{{ __('zatca::onboarding.setup_production_hint') }}</p>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field span-2"><label>{{ __('zatca::onboarding.environment') }}</label><select class="input" name="environment"><option value="sandbox">Sandbox</option><option value="simulation">Simulation</option><option value="production">Production</option></select></div>
                            <div class="field span-2"><button class="button button-primary" type="submit">{{ __('zatca::onboarding.issue_production') }}</button></div>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="grid workspace-view" data-view-panel="invoices">
            <section class="panel invoice-submit">
                <div class="panel-head">
                    <div>
                        <h3>{{ __('zatca::onboarding.invoice_submission') }}</h3>
                        <p>{{ __('zatca::onboarding.live_invoice_flow') }}</p>
                    </div>
                </div>
                <div class="notice warning" id="invoice-readiness-notice">{{ __('zatca::onboarding.invoice_submission_locked') !== 'zatca::onboarding.invoice_submission_locked' ? __('zatca::onboarding.invoice_submission_locked') : 'Invoice submission is locked for this environment until onboarding is complete.' }}</div>

                <form class="form-grid" id="invoice-form">
                    <div class="field"><label>{{ __('zatca::onboarding.environment') }}</label><select class="input" name="environment"><option value="sandbox">Sandbox</option><option value="simulation">Simulation</option><option value="production">Production</option></select></div>
                    <div class="field"><label>{{ __('zatca::onboarding.mode') }}</label><select class="input" name="mode"><option value="reporting">{{ __('zatca::onboarding.reporting') }}</option><option value="clearance">{{ __('zatca::onboarding.clearance') }}</option></select></div>
                    <div class="field"><label>{{ __('zatca::onboarding.invoice_number') }}</label><input class="input" name="invoice_number"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.issued_at') }}</label><input class="input" name="issued_at" type="datetime-local"></div>
                    <div class="field">
                        <label>{{ __('zatca::onboarding.invoice_type_code') }}</label>
                        <select class="input" name="type">
                            <option value="388">{{ __('zatca::onboarding.invoice_type_standard') !== 'zatca::onboarding.invoice_type_standard' ? __('zatca::onboarding.invoice_type_standard') : 'Tax invoice (388)' }}</option>
                            <option value="381">{{ __('zatca::onboarding.invoice_type_credit_note') !== 'zatca::onboarding.invoice_type_credit_note' ? __('zatca::onboarding.invoice_type_credit_note') : 'Credit note (381)' }}</option>
                            <option value="383">{{ __('zatca::onboarding.invoice_type_debit_note') !== 'zatca::onboarding.invoice_type_debit_note' ? __('zatca::onboarding.invoice_type_debit_note') : 'Debit note (383)' }}</option>
                        </select>
                    </div>
                    <div class="field"><label>{{ __('zatca::onboarding.invoice_notes') }}</label><input class="input" name="notes"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.buyer_name') }}</label><input class="input" name="buyer.name"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.buyer_vat_number') }}</label><input class="input" name="buyer.vat_number"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.buyer_city') }}</label><input class="input" name="buyer.city"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.buyer_street') }}</label><input class="input" name="buyer.street"></div>
                    <div class="field"><label>{{ __('zatca::onboarding.item_name') }}</label><input class="input" name="items[0][name]" required></div>
                    <div class="field"><label>{{ __('zatca::onboarding.quantity') }}</label><input class="input" name="items[0][quantity]" type="number" step="0.01" value="1" required></div>
                    <div class="field"><label>{{ __('zatca::onboarding.unit_price') }}</label><input class="input" name="items[0][unit_price]" type="number" step="0.01" value="100" required></div>
                    <div class="field"><label>{{ __('zatca::onboarding.tax_percent') }}</label><input class="input" name="items[0][tax_percent]" type="number" step="0.01" value="15" required></div>
                    <div class="field span-2"><button class="button button-primary" type="submit" id="invoice-submit-button">{{ __('zatca::onboarding.submit_invoice') }}</button></div>
                </form>
            </section>

            <section class="panel invoice-history">
                <div class="panel-head">
                    <div>
                        <h3>{{ __('zatca::onboarding.recent_invoices') }}</h3>
                        <p>{{ __('zatca::onboarding.invoice_filters') }}</p>
                    </div>
                </div>
                <div class="filter-row">
                    <input class="input" id="invoice-search" type="search" placeholder="{{ __('zatca::onboarding.search_invoices') }}">
                    <select class="input" id="invoice-mode-filter">
                        <option value="">{{ __('zatca::onboarding.all_modes') }}</option>
                        <option value="reporting">{{ __('zatca::onboarding.reporting') }}</option>
                        <option value="clearance">{{ __('zatca::onboarding.clearance') }}</option>
                    </select>
                    <select class="input" id="invoice-status-filter">
                        <option value="">{{ __('zatca::onboarding.all_statuses') }}</option>
                        <option value="submitted">{{ __('zatca::onboarding.submitted') }}</option>
                        <option value="failed">{{ __('zatca::onboarding.failed') }}</option>
                    </select>
                </div>
                <div class="filter-row compact">
                    <input class="input" id="invoice-date-from" type="date" aria-label="{{ __('zatca::onboarding.filter_date_from') }}">
                    <input class="input" id="invoice-date-to" type="date" aria-label="{{ __('zatca::onboarding.filter_date_to') }}">
                    <div class="filter-actions">
                        <button class="button button-ghost" type="button" id="apply-invoice-filters">{{ __('zatca::onboarding.apply_filters') }}</button>
                        <button class="button button-ghost" type="button" id="clear-invoice-filters">{{ __('zatca::onboarding.clear_filters') }}</button>
                    </div>
                </div>
                <div class="stack" id="invoice-history-stack"></div>
                <div class="pagination-row">
                    <p id="invoice-pagination-summary">{{ __('zatca::onboarding.invoice_history_empty') }}</p>
                    <div class="toolbar">
                        <button class="button button-ghost" type="button" id="invoice-page-prev">{{ __('zatca::onboarding.previous_page') }}</button>
                        <button class="button button-ghost" type="button" id="invoice-page-next">{{ __('zatca::onboarding.next_page') }}</button>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid workspace-view active" data-view-panel="overview">
            <section class="panel alerts">
                <div class="panel-head">
                    <div>
                        <h3>{{ __('zatca::onboarding.alerts') }}</h3>
                        <p>{{ __('zatca::onboarding.health_status') }}</p>
                    </div>
                </div>
                <div class="stack" id="alerts-stack"></div>
            </section>

            <section class="panel health">
                <div class="panel-head">
                    <div>
                        <h3>{{ __('zatca::onboarding.health') }}</h3>
                        <p>{{ __('zatca::onboarding.health_status') }}</p>
                    </div>
                </div>
                <div class="stack" id="health-stack"></div>
            </section>

            <section class="panel credentials">
                <div class="panel-head">
                    <div>
                        <h3>{{ __('zatca::onboarding.credentials') }}</h3>
                        <p>{{ __('zatca::onboarding.status') }}</p>
                    </div>
                </div>
                <div class="stack" id="credential-stack"></div>
            </section>

            <section class="panel invoice-state">
                <div class="panel-head">
                    <div>
                        <h3>{{ __('zatca::onboarding.invoice_state') }}</h3>
                        <p>{{ __('zatca::onboarding.previous_invoice_hash') }}</p>
                    </div>
                </div>
                <div class="stack" id="invoice-state-stack"></div>
            </section>
        </div>

        <div class="grid workspace-view" data-view-panel="monitoring">
            <section class="panel notifications{{ ($showNotificationHooks ?? true) ? '' : ' hidden' }}">
                <div class="panel-head">
                    <div>
                        <h3>{{ __('zatca::onboarding.notification_hooks') }}</h3>
                        <p>{{ __('zatca::onboarding.notification_hooks_subtitle') }}</p>
                    </div>
                </div>
                <form class="form-grid" id="notification-hook-form">
                    <input type="hidden" name="hook_id">
                    <div class="field"><label>{{ __('zatca::onboarding.hook_name') }}</label><input class="input" name="name" required></div>
                    <div class="field"><label>{{ __('zatca::onboarding.hook_target_url') }}</label><input class="input" name="target_url" type="url" required></div>
                    <div class="field"><label>{{ __('zatca::onboarding.hook_channel') }}</label><select class="input" name="channel"><option value="webhook">Webhook</option></select></div>
                    <div class="field"><label>{{ __('zatca::onboarding.hook_secret') }}</label><input class="input" name="secret"></div>
                    <div class="field span-2 check-row">
                        <label><input type="checkbox" name="events[]" value="health_alert" checked> {{ __('zatca::onboarding.health_alert') }}</label>
                        <label><input type="checkbox" name="events[]" value="submission_failed" checked> {{ __('zatca::onboarding.submission_alert') }}</label>
                        <label><input type="checkbox" name="is_active" value="1" checked> {{ __('zatca::onboarding.hook_active') }}</label>
                    </div>
                    <div class="field span-2 toolbar">
                        <button class="button button-primary" type="submit" id="notification-hook-submit">{{ __('zatca::onboarding.save_hook') }}</button>
                        <button class="button button-ghost hidden" type="button" id="notification-hook-cancel">{{ __('zatca::onboarding.cancel_edit') }}</button>
                    </div>
                </form>
                <div class="stack" id="notification-hook-stack"></div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h3>{{ __('zatca::onboarding.live_response') }}</h3>
                        <p>{{ __('zatca::onboarding.live_response_subtitle') }}</p>
                    </div>
                </div>
                <div class="response-box" id="response-box">{{ __('zatca::onboarding.ready') }}</div>
            </section>
        </div>
    </main>
    </div>

<div class="drawer hidden" id="invoice-drawer">
    <aside class="drawer-panel">
        <div class="panel-head">
            <div>
                <h3 id="invoice-drawer-title">{{ __('zatca::onboarding.invoice_detail') }}</h3>
                <p id="invoice-drawer-subtitle">{{ __('zatca::onboarding.live_invoice_flow') }}</p>
            </div>
            <button class="button button-ghost" type="button" id="invoice-drawer-close">{{ __('zatca::onboarding.close') }}</button>
        </div>
        <div class="drawer-meta" id="invoice-drawer-meta"></div>
        <div class="invoice-actions" id="invoice-drawer-actions"></div>
        <div class="drawer-card">
            <h4>{{ __('zatca::onboarding.saved_xml') }}</h4>
            <pre id="invoice-drawer-xml"></pre>
        </div>
        <div class="drawer-card">
            <h4>{{ __('zatca::onboarding.signed_xml') }}</h4>
            <pre id="invoice-drawer-signed-xml"></pre>
        </div>
        <div class="drawer-card">
            <h4>{{ __('zatca::onboarding.raw_api_response') }}</h4>
            <pre id="invoice-drawer-response"></pre>
        </div>
    </aside>
</div>
</div>

<script>
(() => {
    const app = document.getElementById('dashboard-app');
    const ui = JSON.parse(app.dataset.ui);
    const apiPrefix = JSON.parse(app.dataset.apiPrefix);
    const dashboardPrefix = JSON.parse(app.dataset.dashboardPrefix);
    const locale = JSON.parse(app.dataset.locale);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const canManageTenants = JSON.parse(app.dataset.canManageTenants);
    const showTenantSwitcher = JSON.parse(app.dataset.showTenantSwitcher);
    let tenants = JSON.parse(app.dataset.tenants);
    let selectedTenant = JSON.parse(app.dataset.selected);
    let invoices = JSON.parse(app.dataset.invoices);
    let invoiceMeta = {
        current_page: 1,
        last_page: 1,
        from: invoices.length ? 1 : 0,
        to: invoices.length,
        total: invoices.length,
    };

    const tenantList = document.getElementById('tenant-list');
    const heroTitle = document.getElementById('hero-title');
    const heroSubtitle = document.getElementById('hero-subtitle');
    const heroMetricStatus = document.getElementById('hero-metric-status');
    const heroMetricKey = document.getElementById('hero-metric-key');
    const heroMetricEnvironment = document.getElementById('hero-metric-environment');
    const heroMetricChain = document.getElementById('hero-metric-chain');
    const feedbackBox = document.getElementById('feedback-box');
    const responseBox = document.getElementById('response-box');
    const createTenantPanel = document.getElementById('create-tenant-panel');
    const newTenantToggle = document.getElementById('new-tenant-toggle');
    const tenantStatusPill = document.getElementById('tenant-status-pill');
    const workspaceTabs = Array.from(app.querySelectorAll('[data-view]'));
    const workspacePanels = Array.from(app.querySelectorAll('[data-view-panel]'));
    const tenantSearch = document.getElementById('tenant-search');
    const healthStack = document.getElementById('health-stack');
    const credentialStack = document.getElementById('credential-stack');
    const invoiceStateStack = document.getElementById('invoice-state-stack');
    const invoiceHistoryStack = document.getElementById('invoice-history-stack');
    const alertsStack = document.getElementById('alerts-stack');
    const invoiceDrawer = document.getElementById('invoice-drawer');
    const invoiceDrawerTitle = document.getElementById('invoice-drawer-title');
    const invoiceDrawerSubtitle = document.getElementById('invoice-drawer-subtitle');
    const invoiceDrawerMeta = document.getElementById('invoice-drawer-meta');
    const invoiceDrawerActions = document.getElementById('invoice-drawer-actions');
    const invoiceDrawerXml = document.getElementById('invoice-drawer-xml');
    const invoiceDrawerSignedXml = document.getElementById('invoice-drawer-signed-xml');
    const invoiceDrawerResponse = document.getElementById('invoice-drawer-response');
    const invoiceDrawerClose = document.getElementById('invoice-drawer-close');

    const profileForm = document.getElementById('profile-form');
    const createTenantForm = document.getElementById('create-tenant-form');
    const csrForm = document.getElementById('csr-form');
    const complianceForm = document.getElementById('compliance-form');
    const productionForm = document.getElementById('production-form');
    const invoiceForm = document.getElementById('invoice-form');
    const invoiceSearch = document.getElementById('invoice-search');
    const invoiceModeFilter = document.getElementById('invoice-mode-filter');
    const invoiceStatusFilter = document.getElementById('invoice-status-filter');
    const invoiceDateFrom = document.getElementById('invoice-date-from');
    const invoiceDateTo = document.getElementById('invoice-date-to');
    const applyInvoiceFiltersButton = document.getElementById('apply-invoice-filters');
    const clearInvoiceFiltersButton = document.getElementById('clear-invoice-filters');
    const invoicePaginationSummary = document.getElementById('invoice-pagination-summary');
    const invoicePagePrev = document.getElementById('invoice-page-prev');
    const invoicePageNext = document.getElementById('invoice-page-next');
    const invoiceReadinessNotice = document.getElementById('invoice-readiness-notice');
    const invoiceSubmitButton = document.getElementById('invoice-submit-button');
    const notificationHookForm = document.getElementById('notification-hook-form');
    const notificationHookSubmit = document.getElementById('notification-hook-submit');
    const notificationHookCancel = document.getElementById('notification-hook-cancel');
    const notificationHookStack = document.getElementById('notification-hook-stack');

    function bind(element, event, handler) {
        if (element) {
            element.addEventListener(event, handler);
        }
    }

    if (newTenantToggle && createTenantPanel) {
        newTenantToggle.addEventListener('click', () => {
            createTenantPanel.classList.toggle('hidden');
            setActiveView('setup');
        });
    }

    bind(document.getElementById('refresh-tenants'), 'click', async () => {
        try {
            await refreshTenants(selectedTenant?.key ?? null);
            showFeedback(ui.refresh || 'Refreshed.', 'success');
        } catch (error) {
            showFeedback(error.message, 'error');
        }
    });

    if (tenantSearch) {
        tenantSearch.addEventListener('input', renderTenantList);
    }
    bind(invoiceSearch, 'keydown', async (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            try {
                await refreshInvoices(1);
            } catch (error) {
                showFeedback(error.message, 'error');
            }
        }
    });
    bind(invoiceModeFilter, 'change', async () => {
        try {
            await refreshInvoices(1);
        } catch (error) {
            showFeedback(error.message, 'error');
        }
    });
    bind(invoiceStatusFilter, 'change', async () => {
        try {
            await refreshInvoices(1);
        } catch (error) {
            showFeedback(error.message, 'error');
        }
    });
    bind(applyInvoiceFiltersButton, 'click', async () => {
        try {
            await refreshInvoices(1);
        } catch (error) {
            showFeedback(error.message, 'error');
        }
    });
    bind(clearInvoiceFiltersButton, 'click', async () => {
        invoiceSearch.value = '';
        invoiceModeFilter.value = '';
        invoiceStatusFilter.value = '';
        invoiceDateFrom.value = '';
        invoiceDateTo.value = '';
        try {
            await refreshInvoices(1);
        } catch (error) {
            showFeedback(error.message, 'error');
        }
    });
    bind(invoicePagePrev, 'click', async () => {
        if (invoiceMeta.current_page > 1) {
            try {
                await refreshInvoices(invoiceMeta.current_page - 1);
            } catch (error) {
                showFeedback(error.message, 'error');
            }
        }
    });
    bind(invoicePageNext, 'click', async () => {
        if (invoiceMeta.current_page < invoiceMeta.last_page) {
            try {
                await refreshInvoices(invoiceMeta.current_page + 1);
            } catch (error) {
                showFeedback(error.message, 'error');
            }
        }
    });

    bind(invoiceDrawerClose, 'click', closeInvoiceDrawer);
    bind(invoiceForm?.elements?.environment, 'change', () => {
        syncInvoiceSubmissionState();
    });
    bind(invoiceDrawer, 'click', (event) => {
        if (event.target === invoiceDrawer) {
            closeInvoiceDrawer();
        }
    });

    workspaceTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            setActiveView(tab.dataset.view);
        });
    });

    function encodeKey(key) {
        return encodeURIComponent(key);
    }

    function statusClass(status) {
        return `status-${status || 'warning'}`;
    }

    function showFeedback(message, kind = 'success') {
        if (!feedbackBox) {
            return;
        }
        feedbackBox.textContent = message;
        feedbackBox.classList.remove('hidden');
        feedbackBox.style.background = kind === 'error' ? 'rgba(187, 62, 62, 0.12)' : 'rgba(13, 124, 128, 0.08)';
        feedbackBox.style.borderColor = kind === 'error' ? 'rgba(187, 62, 62, 0.22)' : 'rgba(13, 124, 128, 0.18)';
    }

    function setResponse(payload) {
        if (!responseBox) {
            return;
        }
        responseBox.textContent = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2);
    }

    function setActiveView(view) {
        workspaceTabs.forEach((tab) => {
            tab.classList.toggle('active', tab.dataset.view === view);
        });

        workspacePanels.forEach((panel) => {
            panel.classList.toggle('active', panel.dataset.viewPanel === view);
        });
    }

    function replaceSummary(template, params) {
        return Object.entries(params).reduce((message, [key, value]) => {
            return message.replace(`:${key}`, value);
        }, template);
    }

    function closeInvoiceDrawer() {
        invoiceDrawer.classList.add('hidden');
    }

    function renderHeroMetrics() {
        if (!selectedTenant) {
            heroMetricStatus.textContent = ui.selectTenant;
            heroMetricKey.textContent = '-';
            heroMetricEnvironment.textContent = '-';
            heroMetricChain.textContent = '-';
            return;
        }

        const state = (selectedTenant.invoice_states || [])[0] || null;

        heroMetricStatus.textContent = selectedTenant.onboarding_status_labels?.[locale] || selectedTenant.onboarding_status || '-';
        heroMetricKey.textContent = value(selectedTenant.key);
        heroMetricEnvironment.textContent = value(selectedTenant.default_environment);
        heroMetricChain.textContent = value(state?.next_icv);
    }

    function openInvoiceDrawer(invoice) {
        invoiceDrawerTitle.textContent = value(invoice.invoice_number, ui.invoiceDetail);
        invoiceDrawerSubtitle.textContent = `${invoice.mode === 'clearance' ? ui.clearance : ui.reporting} · ${value(invoice.environment)}`;
        invoiceDrawerMeta.innerHTML = `
            <div class="drawer-card"><strong>${ui.invoiceUuid}</strong><pre>${value(invoice.uuid)}</pre></div>
            <div class="drawer-card"><strong>${ui.submittedAt}</strong><pre>${value(invoice.submitted_at)}</pre></div>
            <div class="drawer-card"><strong>${ui.reportingStatus}</strong><pre>${value(invoice.reporting_status)}</pre></div>
            <div class="drawer-card"><strong>${ui.clearanceStatus}</strong><pre>${value(invoice.clearance_status)}</pre></div>
        `;
        invoiceDrawerActions.innerHTML = `
            <a class="link-button" href="${invoice.download_urls?.xml || '#'}">${ui.downloadXml}</a>
            <a class="link-button" href="${invoice.download_urls?.signed_xml || '#'}">${ui.downloadSignedXml}</a>
            <a class="link-button" href="${invoice.download_urls?.api_response || '#'}">${ui.downloadApiResponse}</a>
        `;
        invoiceDrawerXml.textContent = invoice.xml || '-';
        invoiceDrawerSignedXml.textContent = invoice.signed_xml || '-';
        invoiceDrawerResponse.textContent = JSON.stringify(invoice.api_response || {}, null, 2);
        invoiceDrawer.classList.remove('hidden');
    }

    function value(item, fallback = '-') {
        return item === null || item === undefined || item === '' ? fallback : item;
    }

    function syncProfileForm() {
        const source = selectedTenant || {};
        const address = source.address || {};
        for (const [name, input] of Object.entries(Object.fromEntries(Array.from(profileForm.elements).filter((el) => el.name).map((el) => [el.name, el])))) {
            input.value = source[name] ?? address[name] ?? '';
        }
    }

    function syncOnboardingForms() {
        if (!selectedTenant) {
            return;
        }

        const csrDefaults = selectedTenant.metadata?.csr_defaults || {};
        const sellerName = selectedTenant.seller_name || selectedTenant.legal_name || '';
        const branchName = selectedTenant.branch_name || 'Main Branch';
        const vatNumber = selectedTenant.vat_number || '';
        const crn = selectedTenant.crn || selectedTenant.key || 'TENANT';
        const serialPrefix = csrDefaults.serial_number_prefix || '1-TST|2-TST|3-';

        csrForm.elements.common_name.value = csrDefaults.common_name || `TST-${crn}-${vatNumber}`.replace(/\s+/g, '');
        csrForm.elements.serial_number.value = `${serialPrefix}${crypto.randomUUID()}`;
        csrForm.elements.organization_identifier.value = csrDefaults.organization_identifier || vatNumber;
        csrForm.elements.organization_unit_name.value = csrDefaults.organization_unit_name || branchName;
        csrForm.elements.organization_name.value = csrDefaults.organization_name || sellerName;
        csrForm.elements.country_name.value = csrDefaults.country_name || 'SA';
        csrForm.elements.invoice_type.value = csrDefaults.invoice_type || csrForm.elements.invoice_type.value || '1100';
        csrForm.elements.location_address.value = csrDefaults.location_address || csrForm.elements.location_address.value || '';
        csrForm.elements.industry_business_category.value = csrDefaults.industry_business_category || csrForm.elements.industry_business_category.value || '';
    }

    function invoiceCredentialForEnvironment(environment) {
        if (!selectedTenant) {
            return null;
        }

        return (selectedTenant.credentials || []).find((credential) => credential.environment === environment) || null;
    }

    function invoiceSubmissionReadinessForEnvironment(environment) {
        if (!selectedTenant) {
            return {
                ready: false,
                message: ui.selectTenant,
            };
        }

        if (!environment) {
            return {
                ready: false,
                message: ui.invoiceSubmissionSelectEnvironment,
            };
        }

        const readiness = selectedTenant.invoice_submission_readiness?.[environment];

        if (readiness && readiness.ready) {
            return {
                ready: true,
                message: ui.invoiceSubmissionReady,
            };
        }

        const credential = invoiceCredentialForEnvironment(environment);

        if (!credential) {
            return {
                ready: false,
                message: `The ${environment} invoice credential is not ready yet. Finish the Setup steps for this tenant before submitting invoices.`,
            };
        }

        if (!credential.has_private_key) {
            return {
                ready: false,
                message: `The ${environment} invoice credential is missing its private key. Generate the CSR again or complete onboarding before submitting invoices.`,
            };
        }

        if (!credential.has_compliance_csid && !credential.has_production_csid) {
            return {
                ready: false,
                message: `The ${environment} invoice credential does not have a CSID certificate yet. Complete Compliance or Production issuance in Setup before submitting invoices.`,
            };
        }

        return {
            ready: true,
            message: ui.invoiceSubmissionReady,
        };
    }

    function syncInvoiceSubmissionState() {
        if (!invoiceForm || !invoiceSubmitButton || !invoiceReadinessNotice) {
            return;
        }

        if (!selectedTenant) {
            invoiceReadinessNotice.textContent = ui.selectTenant;
            invoiceReadinessNotice.classList.add('warning');
            invoiceSubmitButton.disabled = true;
            return;
        }

        const environment = invoiceForm.elements.environment?.value || selectedTenant.default_environment || 'sandbox';
        const readiness = invoiceSubmissionReadinessForEnvironment(environment);

        invoiceReadinessNotice.textContent = readiness.message;
        invoiceReadinessNotice.classList.toggle('warning', !readiness.ready);
        invoiceSubmitButton.disabled = !readiness.ready;
    }

    function renderTenantList() {
        if (!tenantList) {
            return;
        }

        const filteredTenants = tenants.filter((tenant) => {
            const query = tenantSearch ? tenantSearch.value.trim().toLowerCase() : '';
            if (!query) {
                return true;
            }

            return [
                tenant.seller_name,
                tenant.legal_name,
                tenant.vat_number,
                tenant.key,
            ].filter(Boolean).some((value) => String(value).toLowerCase().includes(query));
        });

        if (!filteredTenants.length) {
            tenantList.innerHTML = `<div class="tenant-card"><p>${ui.emptyState}</p></div>`;
            return;
        }

        tenantList.innerHTML = filteredTenants.map((tenant) => {
            const active = selectedTenant && tenant.key === selectedTenant.key ? 'active' : '';
            return `
                <article class="tenant-card ${active}" data-tenant-key="${tenant.key}">
                    <h3>${tenant.seller_name || tenant.legal_name}</h3>
                    <p>${tenant.vat_number}</p>
                    <div class="chips">
                        <span class="chip">${tenant.default_environment}</span>
                        <span class="chip">${tenant.onboarding_status_labels?.[locale] || tenant.onboarding_status}</span>
                    </div>
                    <small>${tenant.key}</small>
                </article>
            `;
        }).join('');

        tenantList.querySelectorAll('.tenant-card[data-tenant-key]').forEach((card) => {
            card.addEventListener('click', async () => {
                await selectTenant(card.dataset.tenantKey);
            });
        });
    }

    function renderSelectedTenant() {
        if (!selectedTenant) {
            heroTitle.textContent = ui.selectTenant;
            heroSubtitle.textContent = ui.emptyState;
            renderHeroMetrics();
            tenantStatusPill.className = 'status-pill status-warning';
            tenantStatusPill.textContent = ui.status;
            healthStack.innerHTML = '';
            credentialStack.innerHTML = '';
            invoiceStateStack.innerHTML = '';
            invoiceHistoryStack.innerHTML = `<div class="state-card"><strong>${ui.invoiceHistoryEmpty}</strong></div>`;
            alertsStack.innerHTML = `<div class="alert-card"><p>${ui.alertsEmpty}</p></div>`;
            notificationHookStack.innerHTML = `<div class="hook-card"><p>${ui.notificationHooksEmpty}</p></div>`;
            invoicePaginationSummary.textContent = ui.invoiceHistoryEmpty;
            invoicePagePrev.disabled = true;
            invoicePageNext.disabled = true;
            invoiceForm.reset();
            syncInvoiceSubmissionState();
            notificationHookForm.reset();
            notificationHookForm.elements.hook_id.value = '';
            notificationHookSubmit.textContent = ui.saveHook;
            notificationHookCancel.classList.add('hidden');
            closeInvoiceDrawer();
            return;
        }

        heroTitle.textContent = selectedTenant.seller_name || selectedTenant.legal_name;
        heroSubtitle.textContent = `${value(selectedTenant.vat_number)} · ${value(selectedTenant.branch_name)} · ${value(selectedTenant.address?.city)}`;
        renderHeroMetrics();
        tenantStatusPill.className = `status-pill ${statusClass(selectedTenant.credentials?.some((item) => item.health?.status === 'error') ? 'error' : (selectedTenant.credentials?.some((item) => item.health?.status === 'warning') ? 'warning' : 'healthy'))}`;
        tenantStatusPill.textContent = selectedTenant.onboarding_status_labels?.[locale] || selectedTenant.onboarding_status;

        syncProfileForm();
        syncOnboardingForms();
        if (invoiceForm?.elements?.environment) {
            invoiceForm.elements.environment.value = selectedTenant.default_environment || invoiceForm.elements.environment.value || 'sandbox';
        }
        syncInvoiceSubmissionState();
        renderHealth();
        renderCredentials();
        renderInvoiceState();
        renderInvoiceHistory();
        renderAlerts();
        renderNotificationHooks();
    }

    function renderHealth() {
        const credentials = selectedTenant?.credentials || [];
        healthStack.innerHTML = credentials.map((credential) => {
            const health = credential.health || { status: 'warning', labels: { en: ui.healthStatus }, issues: [] };
            const issues = (health.issues || []).map((issue) => `<li>${issue.message?.[locale] || issue.message?.en || issue.code}</li>`).join('');
            return `
                <article class="health-card">
                    <div class="panel-head">
                        <div>
                            <h3>${credential.environment}</h3>
                            <p>${ui.health}</p>
                        </div>
                        <span class="status-pill ${statusClass(health.status)}">${health.labels?.[locale] || health.status}</span>
                    </div>
                    <div class="meta-list">
                        <div class="meta-row"><span>${ui.certificateVat}</span><strong>${value(health.certificate?.vat_number)}</strong></div>
                        <div class="meta-row"><span>${ui.certificateExpiry}</span><strong>${value(health.certificate?.valid_to)}</strong></div>
                        <div class="meta-row"><span>${ui.lastValidatedAt}</span><strong>${value(credential.last_validated_at)}</strong></div>
                    </div>
                    <ul class="issue-list">${issues || `<li>${ui.submissionSuccess}</li>`}</ul>
                </article>
            `;
        }).join('');
    }

    function renderCredentials() {
        const credentials = selectedTenant?.credentials || [];
        credentialStack.innerHTML = credentials.map((credential) => `
            <article class="credential-card">
                <div class="panel-head">
                    <div>
                        <h3>${credential.environment}</h3>
                        <p>${ui.credentials}</p>
                    </div>
                    <span class="status-pill ${statusClass(credential.health?.status || 'warning')}">${credential.status}</span>
                </div>
                <div class="meta-list">
                    <div class="meta-row"><span>${ui.signer}</span><strong>${value(credential.signer)}</strong></div>
                    <div class="meta-row"><span>${ui.status}</span><strong>${value(credential.status)}</strong></div>
                    <div class="meta-row"><span>${ui.complianceRequestId}</span><strong>${value(credential.compliance_request_id)}</strong></div>
                    <div class="meta-row"><span>${ui.privateKeyPresent}</span><strong>${credential.has_private_key ? ui.yes : ui.no}</strong></div>
                    <div class="meta-row"><span>${ui.complianceCsidPresent}</span><strong>${credential.has_compliance_csid ? ui.yes : ui.no}</strong></div>
                    <div class="meta-row"><span>${ui.productionCsidPresent}</span><strong>${credential.has_production_csid ? ui.yes : ui.no}</strong></div>
                </div>
            </article>
        `).join('');
    }

    function renderInvoiceState() {
        const states = selectedTenant?.invoice_states || [];
        invoiceStateStack.innerHTML = states.map((state) => `
            <article class="state-card">
                <div class="panel-head">
                    <div>
                        <h3>${state.environment}</h3>
                        <p>${ui.invoiceState}</p>
                    </div>
                    <span class="status-pill status-healthy">${ui.nextIcv}: ${value(state.next_icv)}</span>
                </div>
                <div class="meta-list">
                    <div class="meta-row"><span>${ui.nextIcv}</span><strong>${value(state.next_icv)}</strong></div>
                    <div class="meta-row"><span>${ui.previousInvoiceHash}</span><strong>${value(state.previous_invoice_hash)}</strong></div>
                    <div class="meta-row"><span>${ui.lastInvoiceUuid}</span><strong>${value(state.last_invoice_uuid)}</strong></div>
                    <div class="meta-row"><span>${ui.lastInvoiceHash}</span><strong>${value(state.last_invoice_hash)}</strong></div>
                </div>
            </article>
        `).join('');
    }

    function renderInvoiceHistory() {
        if (!selectedTenant) {
            invoiceHistoryStack.innerHTML = `<div class="state-card"><strong>${ui.selectTenant}</strong></div>`;
            invoicePaginationSummary.textContent = ui.invoiceHistoryEmpty;
            return;
        }

        if (!invoices.length) {
            invoiceHistoryStack.innerHTML = `<div class="state-card"><strong>${ui.invoiceHistoryEmpty}</strong></div>`;
            invoicePaginationSummary.textContent = ui.invoiceHistoryEmpty;
            invoicePagePrev.disabled = true;
            invoicePageNext.disabled = true;
            return;
        }

        invoiceHistoryStack.innerHTML = invoices.map((invoice) => `
            <article class="state-card">
                <div class="panel-head">
                    <div>
                        <h3>${value(invoice.invoice_number, ui.invoiceNumber)}</h3>
                        <p>${invoice.mode === 'clearance' ? ui.clearance : ui.reporting}</p>
                    </div>
                    <span class="status-pill ${statusClass(invoice.status === 'failed' ? 'error' : 'healthy')}">${value(invoice.status)}</span>
                </div>
                <div class="meta-list">
                    <div class="meta-row"><span>${ui.environment}</span><strong>${value(invoice.environment)}</strong></div>
                    <div class="meta-row"><span>${ui.invoiceUuid}</span><strong>${value(invoice.uuid)}</strong></div>
                    <div class="meta-row"><span>${ui.reportingStatus}</span><strong>${value(invoice.reporting_status)}</strong></div>
                    <div class="meta-row"><span>${ui.clearanceStatus}</span><strong>${value(invoice.clearance_status)}</strong></div>
                    <div class="meta-row"><span>${ui.submittedAt}</span><strong>${value(invoice.submitted_at)}</strong></div>
                </div>
                <div class="invoice-actions">
                    <button class="button button-ghost" type="button" data-invoice-detail="${invoice.id}">${ui.openInvoiceDetail}</button>
                    <a class="link-button" href="${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/invoices/${invoice.id}/xml">${ui.downloadXml}</a>
                    <a class="link-button" href="${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/invoices/${invoice.id}/signed-xml">${ui.downloadSignedXml}</a>
                    <a class="link-button" href="${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/invoices/${invoice.id}/api-response">${ui.downloadApiResponse}</a>
                </div>
            </article>
        `).join('');

        invoicePaginationSummary.textContent = replaceSummary(ui.paginationSummary, {
            from: invoiceMeta.from || 0,
            to: invoiceMeta.to || 0,
            total: invoiceMeta.total || invoices.length,
        });
        invoicePagePrev.disabled = invoiceMeta.current_page <= 1;
        invoicePageNext.disabled = invoiceMeta.current_page >= invoiceMeta.last_page;

        invoiceHistoryStack.querySelectorAll('[data-invoice-detail]').forEach((button) => {
            button.addEventListener('click', async () => {
                const payload = await requestJson(`${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/invoices/${button.dataset.invoiceDetail}`);
                openInvoiceDrawer(payload.data);
            });
        });
    }

    function renderNotificationHooks() {
        if (!selectedTenant) {
            notificationHookStack.innerHTML = `<div class="hook-card"><p>${ui.selectTenant}</p></div>`;
            return;
        }

        const hooks = selectedTenant.notification_hooks || [];

        if (!hooks.length) {
            notificationHookStack.innerHTML = `<div class="hook-card"><p>${ui.notificationHooksEmpty}</p></div>`;
            return;
        }

        notificationHookStack.innerHTML = hooks.map((hook) => `
            <article class="hook-card">
                <div class="panel-head">
                    <div>
                        <h3>${value(hook.name, ui.hookName)}</h3>
                        <p>${value(hook.target_url)}</p>
                    </div>
                    <span class="status-pill ${statusClass(hook.is_active ? 'healthy' : 'warning')}">${hook.is_active ? ui.yes : ui.no}</span>
                </div>
                <div class="meta-list">
                    <div class="meta-row"><span>${ui.hookChannel}</span><strong>${value(hook.channel)}</strong></div>
                    <div class="meta-row"><span>${ui.hookEvents}</span><strong>${(hook.events || []).join(', ') || '-'}</strong></div>
                    <div class="meta-row"><span>${ui.lastNotifiedAt}</span><strong>${value(hook.last_notified_at)}</strong></div>
                    <div class="meta-row"><span>${ui.status}</span><strong>${hook.last_error ? hook.last_error : ui.ready}</strong></div>
                </div>
                <div class="invoice-actions">
                    <button class="button button-ghost" type="button" data-hook-edit="${hook.id}">${ui.editHook}</button>
                    <button class="button button-ghost" type="button" data-hook-delete="${hook.id}">${ui.deleteHook}</button>
                </div>
            </article>
        `).join('');

        notificationHookStack.querySelectorAll('[data-hook-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const hook = hooks.find((item) => item.id === button.dataset.hookEdit);

                if (!hook) {
                    return;
                }

                notificationHookForm.elements.hook_id.value = hook.id;
                notificationHookForm.elements.name.value = hook.name || '';
                notificationHookForm.elements.target_url.value = hook.target_url || '';
                notificationHookForm.elements.channel.value = hook.channel || 'webhook';
                notificationHookForm.elements.secret.value = '';
                notificationHookForm.querySelectorAll('input[name=\"events[]\"]').forEach((input) => {
                    input.checked = (hook.events || []).includes(input.value);
                });
                notificationHookForm.elements.is_active.checked = !!hook.is_active;
                notificationHookSubmit.textContent = ui.updateHook;
                notificationHookCancel.classList.remove('hidden');
            });
        });

        notificationHookStack.querySelectorAll('[data-hook-delete]').forEach((button) => {
            button.addEventListener('click', async () => {
                if (!selectedTenant) {
                    return;
                }

                try {
                    const payload = await requestJson(`${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/notification-hooks/${button.dataset.hookDelete}`, {
                        method: 'DELETE',
                    });
                    selectedTenant = payload.tenant;
                    showFeedback(payload.message || ui.notificationHookDeleted, 'success');
                    resetNotificationHookForm();
                    renderTenantList();
                    renderSelectedTenant();
                } catch (error) {
                    showFeedback(error.message, 'error');
                }
            });
        });
    }

    function renderAlerts() {
        if (!selectedTenant) {
            alertsStack.innerHTML = `<div class="alert-card"><p>${ui.selectTenant}</p></div>`;
            return;
        }

        const healthAlerts = (selectedTenant.credentials || []).flatMap((credential) => {
            const health = credential.health || {};

            return (health.issues || []).map((issue) => ({
                severity: issue.severity || health.status || 'warning',
                title: `${ui.healthAlert}: ${credential.environment}`,
                body: issue.message?.[locale] || issue.message?.en || issue.code || ui.health,
            }));
        });

        const submissionAlerts = invoices
            .filter((invoice) => invoice.status === 'failed' || invoice.last_error)
            .map((invoice) => ({
                severity: 'error',
                title: `${ui.submissionAlert}: ${value(invoice.invoice_number, ui.invoiceNumber)}`,
                body: invoice.last_error || value(invoice.clearance_status || invoice.reporting_status, ui.submissionFailed),
            }));

        const alerts = [...healthAlerts, ...submissionAlerts];

        if (!alerts.length) {
            alertsStack.innerHTML = `<div class="alert-card"><p>${ui.alertsEmpty}</p></div>`;
            return;
        }

        alertsStack.innerHTML = alerts.map((alert) => `
            <article class="alert-card ${alert.severity === 'error' ? 'error' : 'warning'}">
                <h4>${alert.title}</h4>
                <p>${alert.body}</p>
            </article>
        `).join('');
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            ...options,
        });

        const payload = await response.json().catch(() => ({}));
        setResponse(payload);

        if (!response.ok) {
            const message = payload.message || payload.error || ui.submissionFailed;
            throw new Error(message);
        }

        return payload;
    }

    async function refreshTenants(preferredKey = null) {
        const payload = await requestJson(`${apiPrefix}/tenants`);
        tenants = payload.data || [];

        if (preferredKey) {
            selectedTenant = tenants.find((item) => item.key === preferredKey) || tenants[0] || null;
        } else if (selectedTenant) {
            selectedTenant = tenants.find((item) => item.key === selectedTenant.key) || tenants[0] || null;
        } else {
            selectedTenant = tenants[0] || null;
        }

        renderTenantList();

        if (selectedTenant?.key) {
            await selectTenant(selectedTenant.key, false);
            return;
        }

        invoices = [];
        renderSelectedTenant();
    }

    function invoiceQueryString(page = 1) {
        const params = new URLSearchParams({
            page: String(page),
            per_page: '8',
        });

        if (invoiceSearch.value.trim()) {
            params.set('search', invoiceSearch.value.trim());
        }
        if (invoiceModeFilter.value) {
            params.set('mode', invoiceModeFilter.value);
        }
        if (invoiceStatusFilter.value) {
            params.set('status', invoiceStatusFilter.value);
        }
        if (invoiceDateFrom.value) {
            params.set('date_from', invoiceDateFrom.value);
        }
        if (invoiceDateTo.value) {
            params.set('date_to', invoiceDateTo.value);
        }

        return params.toString();
    }

    async function refreshInvoices(page = 1) {
        if (!selectedTenant) {
            invoices = [];
            invoiceMeta = { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 };
            renderInvoiceHistory();
            renderAlerts();
            return;
        }

        const payload = await requestJson(`${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/invoices?${invoiceQueryString(page)}`);
        invoices = payload.data || [];
        invoiceMeta = payload.meta || { current_page: 1, last_page: 1, from: invoices.length ? 1 : 0, to: invoices.length, total: invoices.length };
        renderInvoiceHistory();
        renderAlerts();
    }

    async function selectTenant(key, updateHistory = true) {
        const tenantPayload = await requestJson(`${apiPrefix}/tenants/${encodeKey(key)}`);

        selectedTenant = tenantPayload.data;
        renderTenantList();
        renderSelectedTenant();
        await refreshInvoices(1);

        if (updateHistory) {
            history.replaceState({}, '', `${dashboardPrefix}/${encodeKey(key)}?lang=${locale}`);
        }
    }

    function formToJson(form) {
        const data = {};
        new FormData(form).forEach((value, key) => {
            if (value === '') {
                return;
            }
            if (value === 'true') {
                data[key] = true;
                return;
            }
            if (value === 'false') {
                data[key] = false;
                return;
            }
            data[key] = value;
        });
        return data;
    }

    function invoiceFormToJson(form) {
        const raw = formToJson(form);
        const mode = raw.mode || 'reporting';

        return {
            environment: raw.environment,
            mode,
            invoice_number: raw.invoice_number,
            issued_at: raw.issued_at ? new Date(raw.issued_at).toISOString() : undefined,
            type: raw.type || '388',
            notes: raw.notes,
            meta: {
                transaction_type_code: mode === 'clearance' ? '0100000' : '0200000',
            },
            buyer: {
                name: raw['buyer.name'],
                vat_number: raw['buyer.vat_number'],
                city: raw['buyer.city'],
                street: raw['buyer.street'],
            },
            items: [
                {
                    name: raw['items[0][name]'],
                    quantity: raw['items[0][quantity]'] ? Number(raw['items[0][quantity]']) : undefined,
                    unit_price: raw['items[0][unit_price]'] ? Number(raw['items[0][unit_price]']) : undefined,
                    tax_percent: raw['items[0][tax_percent]'] ? Number(raw['items[0][tax_percent]']) : undefined,
                },
            ],
        };
    }

    if (createTenantForm) {
        createTenantForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const payload = await requestJson(`${apiPrefix}/tenants`, {
                    method: 'POST',
                    body: JSON.stringify(formToJson(createTenantForm)),
                });
                showFeedback(ui.submissionSuccess, 'success');
                createTenantForm.reset();
                if (createTenantPanel) {
                    createTenantPanel.classList.add('hidden');
                }
                await refreshTenants(payload.data?.key || null);
            } catch (error) {
                showFeedback(error.message, 'error');
            }
        });
    }

    profileForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedTenant) return;
        try {
            const payload = await requestJson(`${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}`, {
                method: 'PATCH',
                body: JSON.stringify(formToJson(profileForm)),
            });
            showFeedback(ui.submissionSuccess, 'success');
            selectedTenant = payload.data;
            await refreshTenants(selectedTenant.key);
        } catch (error) {
            showFeedback(error.message, 'error');
        }
    });

    csrForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedTenant) return;
        try {
            const payload = await requestJson(`${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/csr`, {
                method: 'POST',
                body: JSON.stringify(formToJson(csrForm)),
            });
            showFeedback(ui.submissionSuccess, 'success');
            selectedTenant = payload.tenant;
            await refreshTenants(selectedTenant.key);
        } catch (error) {
            showFeedback(error.message, 'error');
        }
    });

    complianceForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedTenant) return;
        try {
            const payload = await requestJson(`${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/compliance-csid`, {
                method: 'POST',
                body: JSON.stringify(formToJson(complianceForm)),
            });
            showFeedback(ui.submissionSuccess, 'success');
            selectedTenant = payload.tenant;
            await refreshTenants(selectedTenant.key);
        } catch (error) {
            showFeedback(error.message, 'error');
        }
    });

    productionForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedTenant) return;
        try {
            const payload = await requestJson(`${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/production-csid`, {
                method: 'POST',
                body: JSON.stringify(formToJson(productionForm)),
            });
            showFeedback(ui.submissionSuccess, 'success');
            selectedTenant = payload.tenant;
            await refreshTenants(selectedTenant.key);
        } catch (error) {
            showFeedback(error.message, 'error');
        }
    });

    invoiceForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedTenant) return;
        const readiness = invoiceSubmissionReadinessForEnvironment(invoiceForm.elements.environment?.value || selectedTenant.default_environment || 'sandbox');
        if (!readiness.ready) {
            showFeedback(readiness.message, 'error');
            syncInvoiceSubmissionState();
            return;
        }
        try {
            const payload = await requestJson(`${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/invoices`, {
                method: 'POST',
                body: JSON.stringify(invoiceFormToJson(invoiceForm)),
            });
            showFeedback(payload.message || ui.submissionSuccess, 'success');
            selectedTenant = payload.tenant;
            renderTenantList();
            renderSelectedTenant();
            invoiceForm.reset();
            await refreshInvoices(1);
        } catch (error) {
            showFeedback(error.message, 'error');
        }
    });

    function notificationHookFormToJson(form) {
        const formData = new FormData(form);

        return {
            name: String(formData.get('name') || ''),
            target_url: String(formData.get('target_url') || ''),
            channel: String(formData.get('channel') || 'webhook'),
            secret: String(formData.get('secret') || ''),
            events: formData.getAll('events[]'),
            is_active: formData.get('is_active') === '1',
        };
    }

    function resetNotificationHookForm() {
        notificationHookForm.reset();
        notificationHookForm.elements.hook_id.value = '';
        notificationHookForm.querySelectorAll('input[name="events[]"]').forEach((input) => {
            input.checked = true;
        });
        notificationHookForm.elements.is_active.checked = true;
        notificationHookSubmit.textContent = ui.saveHook;
        notificationHookCancel.classList.add('hidden');
    }

    bind(notificationHookCancel, 'click', () => {
        resetNotificationHookForm();
    });

    notificationHookForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedTenant) return;

        const hookId = notificationHookForm.elements.hook_id.value;
        const method = hookId ? 'PATCH' : 'POST';
        const url = hookId
            ? `${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/notification-hooks/${hookId}`
            : `${apiPrefix}/tenants/${encodeKey(selectedTenant.key)}/notification-hooks`;

        try {
            const payload = await requestJson(url, {
                method,
                body: JSON.stringify(notificationHookFormToJson(notificationHookForm)),
            });
            selectedTenant = payload.tenant;
            showFeedback(payload.message || ui.notificationHookSaved, 'success');
            resetNotificationHookForm();
            renderTenantList();
            renderSelectedTenant();
        } catch (error) {
            showFeedback(error.message, 'error');
        }
    });

    (async () => {
        try {
            renderTenantList();
            renderSelectedTenant();

            if (selectedTenant?.key || tenants.length > 0 || showTenantSwitcher || canManageTenants) {
                await refreshTenants(selectedTenant?.key ?? null);
            }
        } catch (error) {
            console.error(error);
            showFeedback(error.message || 'Dashboard failed to initialize.', 'error');
        }
    })();
})();
</script>
</body>
</html>


