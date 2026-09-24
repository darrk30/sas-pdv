<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Crear cuenta — ' . config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
    :root {
        --rg-primary:  #123B72;
        --rg-primary2: #1E5AA8;
        --rg-accent:   #F28C28;
        --rg-success:  #17A673;
        --rg-text:     #142033;
        --rg-muted:    #738095;
        --rg-border:   #E4EAF2;
        --rg-surface:  #FFFFFF;
        --rg-shadow:   0 24px 70px rgba(31,56,88,.12);
        --rg-radius:   22px;
    }
    *,*::before,*::after { box-sizing:border-box }
    body {
        margin: 0;
        font-family: 'Inter', sans-serif;
        background:
            radial-gradient(circle at 12% 10%, rgba(30,90,168,.10), transparent 26%),
            radial-gradient(circle at 88% 92%, rgba(242,140,40,.08), transparent 24%),
            linear-gradient(135deg, #F8FAFD 0%, #EEF3F9 100%) !important;
        background-color: #F4F7FB !important;
    }

    /* ── Page layout ─────────────────────────── */
    .rg-page {
        min-height: 100vh;
        display: grid;
        grid-template-columns: 1.05fr 1.7fr;
    }

    /* ── Hero ─────────────────────────────────── */
    .rg-hero {
        position: sticky;
        top: 0;
        height: 100vh;
        overflow: hidden;
        align-self: start;
    }
    .rg-hero-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        display: block;
    }

    /* ── Content ──────────────────────────────── */
    .rg-content {
        padding: 40px 44px;
        display: flex; align-items: flex-start; justify-content: center;
        min-height: 100vh;
    }
    .rg-shell { width: min(760px, 100%); padding-top: 8px; }

    /* ── Steps ────────────────────────────────── */
    .rg-steps {
        position: relative;
        display: grid; grid-template-columns: repeat(3, 1fr);
        margin-bottom: 28px; padding: 0 8px;
    }
    .rg-steps-track {
        position: absolute; left: 16.5%; right: 16.5%; top: 21px;
        height: 2px; background: #DCE4EE; z-index: 0;
    }
    .rg-steps-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--rg-primary), var(--rg-accent));
        transition: width .4s ease;
    }
    .rg-step { position: relative; z-index: 2; text-align: center; }
    .rg-step-circle {
        width: 42px; height: 42px; margin: 0 auto 9px;
        border-radius: 50%; display: grid; place-items: center;
        font-weight: 700; font-size: 14px; color: #8793A5;
        background: var(--rg-surface); border: 2px solid #DCE4EE;
        transition: all .25s ease;
    }
    .rg-step.active .rg-step-circle {
        color: #fff;
        background: linear-gradient(135deg, var(--rg-primary), var(--rg-primary2));
        border-color: transparent; box-shadow: 0 6px 18px rgba(30,90,168,.22);
    }
    .rg-step.done .rg-step-circle {
        color: #fff; background: var(--rg-success); border-color: transparent;
    }
    .rg-step-label { font-size: 12.5px; font-weight: 700; color: #8995A7; }
    .rg-step.active .rg-step-label,
    .rg-step.done   .rg-step-label { color: var(--rg-text); }

    /* ── Card ─────────────────────────────────── */
    .rg-card {
        background: rgba(255,255,255,.97);
        border: 1px solid rgba(229,235,243,.9);
        border-radius: var(--rg-radius);
        box-shadow: var(--rg-shadow);
        padding: 36px 40px;
        min-height: 480px;
    }
    .rg-eyebrow {
        display: inline-block; font-size: 11px; font-weight: 800;
        letter-spacing: .1em; text-transform: uppercase;
        color: var(--rg-primary2); margin-bottom: 7px;
    }
    .rg-card-title { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -.7px; color: var(--rg-text); }
    .rg-card-sub { margin: 8px 0 26px; color: var(--rg-muted); font-size: 13.5px; line-height: 1.65; }

    /* ── Form fields ──────────────────────────── */
    .rg-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
    .rg-col2 { grid-column: 1 / -1; }
    .rg-field-wrap { display: flex; flex-direction: column; }
    .rg-label { display: block; margin-bottom: 7px; font-size: 12.5px; font-weight: 700; color: #435066; }
    .rg-label-opt { font-weight: 500; color: var(--rg-muted); }
    .rg-required { color: #E35252; }
    .rg-field {
        width: 100%; height: 50px;
        border: 1px solid var(--rg-border); border-radius: 13px;
        background: #FAFBFD; padding: 0 15px;
        font-family: 'Inter', sans-serif; font-size: 14px; color: var(--rg-text);
        outline: none; transition: border-color .2s, box-shadow .2s, background .2s;
        appearance: none;
    }
    .rg-field:focus {
        background: #fff; border-color: #75A7DF;
        box-shadow: 0 0 0 4px rgba(30,90,168,.08);
    }
    .rg-field-error { margin-top: 5px; font-size: 11.5px; color: #D94848; font-weight: 500; }

    /* ── Billing toggle ───────────────────────── */
    .rg-billing-wrap {
        display: flex; align-items: center; justify-content: space-between;
        gap: 16px; margin: 16px 0 6px; padding: 14px 16px;
        border: 1px solid var(--rg-border); border-radius: 15px; background: #F8FAFD;
    }
    .rg-billing-text strong { display: block; font-size: 13px; margin-bottom: 3px; color: var(--rg-text); }
    .rg-billing-text span   { color: var(--rg-muted); font-size: 11.5px; }
    .rg-billing-toggle {
        display: inline-flex; padding: 4px; background: #EAF0F7;
        border-radius: 11px; gap: 3px; flex-shrink: 0;
    }
    .rg-billing-btn {
        border: 0; background: transparent; color: #667387;
        padding: 8px 14px; border-radius: 8px;
        font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 800;
        cursor: pointer; transition: all .2s ease; white-space: nowrap;
        display: flex; align-items: center; gap: 5px;
    }
    .rg-billing-btn.active {
        color: #fff;
        background: linear-gradient(135deg, var(--rg-primary), var(--rg-primary2));
        box-shadow: 0 5px 14px rgba(30,90,168,.18);
    }
    .rg-save-pill {
        display: inline-flex; align-items: center;
        padding: 2px 6px; border-radius: 999px;
        background: #E8F8F1; color: #13835E;
        font-size: 9.5px; font-weight: 900;
    }
    .rg-billing-btn.active .rg-save-pill { background: rgba(255,255,255,.22); color: #fff; }

    /* ── Trial banner ─────────────────────────── */
    .rg-trial-banner {
        display: flex; align-items: flex-start; gap: 13px;
        margin: 14px 0 4px; padding: 14px 16px;
        border: 1px solid #C8E8DA; border-radius: 14px;
        background: linear-gradient(135deg, #F0FAF5, #FBFFFE);
    }
    .rg-trial-icon {
        width: 36px; height: 36px; border-radius: 10px;
        display: grid; place-items: center; flex-shrink: 0;
        background: var(--rg-success); color: #fff; font-size: 17px;
    }
    .rg-trial-banner strong { display: block; font-size: 13px; color: #184D3C; margin-bottom: 3px; }
    .rg-trial-banner span   { display: block; font-size: 11.5px; line-height: 1.55; color: #5D786D; }

    /* ── Plan cards ───────────────────────────── */
    .rg-plans {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
        gap: 14px; margin-top: 18px;
    }
    .rg-plan {
        position: relative; border: 1.5px solid var(--rg-border);
        border-radius: 17px; padding: 20px 16px; cursor: pointer;
        background: #fff; transition: all .2s ease;
        text-align: left; font-family: 'Inter', sans-serif; overflow: hidden; width: 100%;
        display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start;
    }
    .rg-plan:hover { transform: translateY(-3px); border-color: #A9C3E4; box-shadow: 0 14px 28px rgba(24,50,85,.07); }
    .rg-plan.selected {
        border-color: var(--rg-primary2);
        background: linear-gradient(180deg, #F6FAFF, #FFFFFF);
        box-shadow: 0 10px 26px rgba(30,90,168,.11);
    }
    .rg-plan-check {
        position: absolute; top: 12px; right: 12px;
        width: 26px; height: 26px; border-radius: 50%;
        background: var(--rg-primary2); color: #fff;
        display: grid; place-items: center; font-size: 12px; font-weight: 800;
        opacity: 0; transform: scale(.7); transition: all .2s ease;
    }
    .rg-plan.selected .rg-plan-check { opacity: 1; transform: scale(1); }
    .rg-plan-name {
        font-size: 15px; font-weight: 800; color: var(--rg-text);
        margin-bottom: 10px; display: flex; align-items: center; gap: 7px; flex-wrap: wrap;
    }
    .rg-trial-pill {
        display: inline-flex; align-items: center; gap: 4px;
        background: #E8F8F1; color: #13835E;
        font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 999px;
    }
    .rg-price { display: flex; align-items: baseline; gap: 4px; margin-bottom: 4px; }
    .rg-price-amount { font-size: 30px; font-weight: 800; letter-spacing: -1px; color: var(--rg-text); line-height: 1; }
    .rg-price-period { font-size: 12px; color: var(--rg-muted); }
    .rg-price-note   { font-size: 11px; color: var(--rg-muted); margin-bottom: 14px; min-height: 15px; }
    .rg-plan-features { list-style: none; padding: 0; margin: 0; display: grid; gap: 9px; }
    .rg-plan-features li {
        font-size: 12px; color: #667387;
        display: flex; align-items: flex-start; gap: 7px; line-height: 1.4;
    }
    .rg-plan-features li::before { content:"✓"; color:var(--rg-success); font-weight:900; font-size:11px; flex-shrink:0; margin-top:1px; }

    /* ── Summary ──────────────────────────────── */
    .rg-summary { background: #F7F9FC; border: 1px solid var(--rg-border); border-radius: 15px; padding: 16px 18px; margin-top: 20px; }
    .rg-summary-row {
        display: flex; justify-content: space-between; align-items: center;
        gap: 16px; padding: 9px 0; font-size: 13px; color: #667387;
        border-bottom: 1px dashed #DDE5EF;
    }
    .rg-summary-row:last-child { border-bottom: 0; }
    .rg-summary-row strong { color: var(--rg-text); font-weight: 700; }
    .rg-summary-free { color: var(--rg-success); font-weight: 700; }

    /* ── Actions ──────────────────────────────── */
    .rg-actions {
        display: flex; justify-content: space-between; align-items: center;
        gap: 12px; margin-top: 28px; padding-top: 22px; border-top: 1px solid #EEF1F5;
    }
    .rg-btn {
        height: 48px; border: none; border-radius: 13px; padding: 0 22px;
        font-family: 'Inter', sans-serif; font-size: 14px; font-weight: 700;
        cursor: pointer; transition: all .2s ease;
        display: inline-flex; align-items: center; justify-content: center;
        gap: 8px; text-decoration: none; white-space: nowrap;
    }
    .rg-btn:disabled { opacity: .55; cursor: not-allowed; }
    .rg-btn-secondary { background: #F0F3F8; color: #536176; border: 1px solid #DEE5EF; }
    .rg-btn-secondary:hover:not(:disabled) { background: #E6ECF5; }
    .rg-btn-primary {
        color: #fff;
        background: linear-gradient(135deg, var(--rg-primary), var(--rg-primary2));
        box-shadow: 0 8px 20px rgba(30,90,168,.22); margin-left: auto; min-width: 160px;
    }
    .rg-btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(30,90,168,.28); }
    .rg-btn-final { background: linear-gradient(135deg, #E8821A, #F5A044); box-shadow: 0 8px 20px rgba(242,140,40,.24); }
    .rg-btn-final:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(242,140,40,.3); }

    /* ── Login hint ───────────────────────────── */
    .rg-login-hint { text-align: center; font-size: 13px; color: var(--rg-muted); margin-top: 20px; }
    .rg-login-hint a { color: var(--rg-primary2); font-weight: 700; text-decoration: none; }
    .rg-login-hint a:hover { text-decoration: underline; }

    /* ── State screens ────────────────────────── */
    .rg-state {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; text-align: center; padding: 40px 20px; min-height: 400px;
    }
    .rg-state-icon { width: 68px; height: 68px; border-radius: 50%; display: grid; place-items: center; margin-bottom: 22px; }
    .rg-state-icon.loading { background: #EBF1FC; }
    .rg-state-icon.success { background: #E8F7F0; }
    .rg-state-icon.error   { background: #FDF0F0; }
    .rg-state h2 { margin: 0 0 8px; font-size: 22px; font-weight: 800; letter-spacing: -.5px; color: var(--rg-text); }
    .rg-state p  { margin: 0 0 22px; color: var(--rg-muted); font-size: 14px; max-width: 340px; line-height: 1.6; }

    /* ── Select custom arrow ─────────────────── */
    .rg-select-wrap { position: relative; }
    .rg-select-wrap select.rg-field {
        padding-right: 40px;
        cursor: pointer;
    }
    .rg-select-wrap::after {
        content: "";
        position: absolute;
        right: 15px; top: 50%; transform: translateY(-50%);
        width: 16px; height: 16px; pointer-events: none;
        background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23738095' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") center/contain no-repeat;
    }

    /* ── RUC toggle (checkbox estilo pill) ────── */
    .rg-ruc-toggle { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
    .rg-ruc-toggle input[type="checkbox"] { display: none; }
    .rg-ruc-toggle-track {
        width: 42px; height: 24px; border-radius: 999px;
        background: #DDE5EF; position: relative; cursor: pointer;
        transition: background .2s ease; flex-shrink: 0;
    }
    .rg-ruc-toggle-track::after {
        content: ""; position: absolute;
        width: 18px; height: 18px; border-radius: 50%;
        background: #fff; top: 3px; left: 3px;
        transition: transform .2s ease;
        box-shadow: 0 1px 4px rgba(0,0,0,.18);
    }
    .rg-ruc-toggle-track.on { background: var(--rg-primary2); }
    .rg-ruc-toggle-track.on::after { transform: translateX(18px); }
    .rg-ruc-toggle-label { font-size: 13px; font-weight: 600; color: var(--rg-text); cursor: pointer; user-select: none; }

    /* ── Collapse extra data ─────────────────── */
    .rg-collapse-trigger {
        display: flex; align-items: center; gap: 9px;
        width: 100%; background: #F4F7FB;
        border: 1px solid var(--rg-border); border-radius: 12px;
        padding: 11px 16px; cursor: pointer;
        font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 600;
        color: #536176; transition: all .2s ease; margin-top: 18px;
    }
    .rg-collapse-trigger:hover { background: #ECF1F9; border-color: #C2D0E4; }
    .rg-collapse-trigger.open  { background: #EBF2FF; border-color: #A8C5E8; color: var(--rg-primary2); border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
    .rg-collapse-trigger-icon  { width: 20px; height: 20px; display: grid; place-items: center; flex-shrink: 0; }
    .rg-collapse-trigger-chevron { margin-left: auto; opacity: .6; transition: transform .2s ease; }
    .rg-collapse-trigger.open .rg-collapse-trigger-chevron { transform: rotate(180deg); }
    .rg-collapse-body {
        border: 1px solid #A8C5E8; border-top: 0;
        border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;
        background: #F8FBFF; padding: 18px 16px 4px;
    }

    /* ── File input ───────────────────────────── */
    .rg-file-wrap { position: relative; }
    .rg-file-btn {
        position: relative;
        display: flex; align-items: center; gap: 10px;
        height: 50px; border: 1.5px dashed var(--rg-border); border-radius: 13px;
        background: #FAFBFD; padding: 0 16px; cursor: pointer;
        font-family: 'Inter', sans-serif; font-size: 13px; color: var(--rg-muted);
        transition: all .2s; width: 100%; box-sizing: border-box;
    }
    .rg-file-btn:hover { border-color: #75A7DF; background: #F4F8FE; color: var(--rg-primary2); }
    .rg-file-btn input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
    .rg-logo-preview { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
    .rg-logo-preview img { height: 48px; border-radius: 8px; object-fit: contain; border: 1px solid var(--rg-border); padding: 3px; background: #fff; }
    .rg-logo-preview span { font-size: 12px; color: var(--rg-muted); }

    /* ── Verificación de correo ──────────────── */
    .rg-verify-box {
        background: #EBF2FF; border: 1px solid #A8C5E8; border-radius: 13px;
        padding: 18px 20px; display: flex; align-items: flex-start; gap: 14px; margin-top: 4px;
    }
    .rg-verify-icon { font-size: 26px; line-height: 1; flex-shrink: 0; margin-top: 2px; }
    .rg-verify-msg  { font-size: 13.5px; color: #1a3860; line-height: 1.6; margin: 0; }
    .rg-verified-badge {
        display: inline-flex; align-items: center; gap: 8px;
        background: #EDFAF4; border: 1px solid #7DDBB4; border-radius: 10px;
        padding: 9px 14px; font-size: 13px; color: #0e5c3f; font-weight: 500;
        margin-top: 4px;
    }
    .rg-verified-badge svg { color: #17A673; flex-shrink: 0; }
    .rg-code-input {
        letter-spacing: 8px; font-size: 22px; font-weight: 700;
        text-align: center; font-family: 'Courier New', monospace;
    }

    /* ── Input con ojo ────────────────────────── */
    .rg-input-eye { position: relative; }
    .rg-input-eye .rg-field { padding-right: 44px; }
    .rg-eye-btn {
        position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
        background: none; border: none; cursor: pointer; padding: 4px;
        color: var(--rg-muted); display: flex; align-items: center;
    }
    .rg-eye-btn:hover { color: var(--rg-text); }

    /* ── Fortaleza contraseña ─────────────────── */
    .rg-strength { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
    .rg-strength-bars { display: flex; gap: 4px; }
    .rg-strength-bar {
        width: 36px; height: 4px; border-radius: 99px; background: var(--rg-border);
        transition: background .2s;
    }
    .rg-strength-bar.weak   { background: #E05252; }
    .rg-strength-bar.medium { background: #F5A623; }
    .rg-strength-bar.strong { background: #17A673; }
    .rg-strength-label { font-size: 11.5px; font-weight: 700; }
    .rg-strength-label.weak   { color: #E05252; }
    .rg-strength-label.medium { color: #F5A623; }
    .rg-strength-label.strong { color: #17A673; }
    .rg-field-hint { font-size: 11.5px; color: var(--rg-muted); margin: 5px 0 0; }

    /* ── Términos y condiciones ───────────────── */
    .rg-terms-wrap { margin: 18px 0 4px; }
    .rg-terms-label {
        display: flex; align-items: flex-start; gap: 10px; cursor: pointer;
        font-size: 13px; color: var(--rg-text); line-height: 1.55;
    }
    .rg-terms-check {
        width: 17px; height: 17px; border-radius: 5px; flex-shrink: 0; margin-top: 1px;
        accent-color: var(--rg-primary2); cursor: pointer;
    }
    .rg-terms-link { color: var(--rg-primary2); text-decoration: underline; font-weight: 600; }
    .rg-terms-link:hover { color: var(--rg-primary); }

    /* ── Botón volver ────────────────────────── */
    .rg-back-btn {
        position: fixed; top: 16px; left: 16px; z-index: 100;
        display: inline-flex; align-items: center; gap: 7px;
        padding: 8px 14px 8px 10px;
        background: rgba(255,255,255,.92); backdrop-filter: blur(8px);
        border: 1px solid rgba(0,0,0,.10);
        border-radius: 999px;
        font-size: 13px; font-weight: 600; color: var(--rg-text);
        text-decoration: none;
        box-shadow: 0 2px 10px rgba(0,0,0,.10);
        transition: background .15s, box-shadow .15s;
    }
    .rg-back-btn:hover { background: #fff; box-shadow: 0 4px 16px rgba(0,0,0,.14); }
    @media (prefers-color-scheme: dark) {
        .rg-back-btn { background: rgba(30,30,40,.88); border-color: rgba(255,255,255,.1); color: #e8e8e8; }
        .rg-back-btn:hover { background: rgba(40,40,55,.95); }
    }

    /* ── Responsive ───────────────────────────── */
    @media (max-width: 1040px) {
        .rg-page { grid-template-columns: 1fr; }
        .rg-hero { display: none; }
        .rg-content { padding: 28px 20px; align-items: flex-start; }
    }
    @media (max-width: 680px) {
        .rg-content { padding: 18px 14px; }
        .rg-card { padding: 24px 18px; min-height: auto; }
        .rg-grid { grid-template-columns: 1fr; }
        .rg-col2 { grid-column: auto; }
        .rg-plans { grid-template-columns: 1fr; }
        .rg-billing-wrap { flex-direction: column; align-items: flex-start; }
        .rg-billing-toggle { width: 100%; }
        .rg-billing-btn { flex: 1; justify-content: center; }
        .rg-card-title { font-size: 22px; }
        .rg-actions { flex-wrap: wrap; }
        .rg-btn-primary { margin-left: 0; flex: 1; }
        .rg-btn-secondary { flex: 1; }
    }
    </style>
</head>
<body>
    {{ $slot }}
    @livewireScripts
</body>
</html>
