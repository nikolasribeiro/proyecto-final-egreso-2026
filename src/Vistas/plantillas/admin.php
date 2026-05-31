<?php 
// Plantilla para el panel administrativo

/**
 * @var string titulo_pagina
 * @var string $nombre
 * @var string $rol
 * @var string $contenido
 */
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HC - $titulo_pagina</title>
    <style>
        /* ========== CSS Variables ========== */
        :root {
            --primary-blue: #0066cc;
            --primary-blue-dark: #004c99;
            --primary-blue-light: #e6f0fa;
            --secondary-gray: #6b7280;
            --light-gray: #f3f4f6;
            --border-gray: #e5e7eb;
            --white: #ffffff;
            --black: #1f2937;
            --success-green: #10b981;
            --success-green-light: #d1fae5;
            --danger-red: #ef4444;
            --danger-red-light: #fee2e2;
            --warning-yellow: #f59e0b;
            --warning-yellow-light: #fef3c7;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --sidebar-width: 260px;
            --header-height: 64px;
        }

        /* ========== Reset & Base ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family:
                -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu,
                sans-serif;
            background-color: var(--light-gray);
            color: var(--black);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ========== Layout ========== */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* ========== Sidebar ========== */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--white);
            border-right: 1px solid var(--border-gray);
            display: flex;
            flex-direction: column;
            z-index: 200;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .sidebar.open {
            transform: translateX(0);
        }

        @media (min-width: 1024px) {
            .sidebar {
                transform: translateX(0);
            }
        }

        .sidebar-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-gray);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-blue);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: bold;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .logo-text {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--primary-blue);
        }

        .logo-subtitle {
            font-size: 0.7rem;
            color: var(--secondary-gray);
            line-height: 1.2;
        }

        /* ========== Sidebar Navigation ========== */
        .sidebar-nav {
            flex: 1;
            padding: 1rem 0;
            overflow-y: auto;
        }

        .nav-section-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--secondary-gray);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.75rem 1.5rem 0.5rem;
        }

        .nav-list {
            list-style: none;
        }

        .nav-item {
            margin: 0.25rem 0.75rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: var(--secondary-gray);
            font-weight: 500;
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            color: var(--primary-blue);
            background: var(--primary-blue-light);
        }

        .nav-link.active {
            color: var(--primary-blue);
            background: var(--primary-blue-light);
            font-weight: 600;
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        /* ========== Sidebar Footer ========== */
        .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-gray);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--primary-blue-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-blue);
            font-weight: 600;
            flex-shrink: 0;
        }

        .user-details {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--black);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--secondary-gray);
        }

        /* ========== Overlay ========== */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 150;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }

        @media (min-width: 1024px) {
            .sidebar-overlay {
                display: none !important;
            }
        }

        /* ========== Main Content Area ========== */
        .main-wrapper {
            flex: 1;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        @media (min-width: 1024px) {
            .main-wrapper {
                margin-left: var(--sidebar-width);
            }
        }

        /* ========== Header ========== */
        .header {
            background: var(--white);
            padding: 0 1.5rem;
            height: var(--header-height);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-gray);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .hamburger {
            width: 40px;
            height: 40px;
            border: none;
            background: var(--light-gray);
            border-radius: var(--radius-sm);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary-gray);
            transition: all 0.2s ease;
        }

        .hamburger:hover {
            background: var(--border-gray);
            color: var(--black);
        }

        @media (min-width: 1024px) {
            .hamburger {
                display: none;
            }
        }

        .header-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--black);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-user {
            display: none;
            align-items: center;
            gap: 0.5rem;
        }

        @media (min-width: 1024px) {
            .header-user {
                display: flex;
            }
        }

        /* ========== Main Content ========== */
        .main {
            padding: 1.5rem;
        }

        /* ========== Section ========== */
        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

        .section-header {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 768px) {
            .section-header {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--black);
        }

        .section-description {
            color: var(--secondary-gray);
            font-size: 0.9rem;
        }

        /* ========== Buttons ========== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-blue-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--primary-blue);
            border: 2px solid var(--primary-blue);
        }

        .btn-secondary:hover {
            background: var(--primary-blue-light);
        }

        .btn-success {
            background: var(--success-green);
            color: var(--white);
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: var(--danger-red);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-warning {
            background: var(--warning-yellow);
            color: var(--white);
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-large {
            padding: 1.25rem 2rem;
            font-size: 1.125rem;
            width: 100%;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* ========== Cards ========== */
        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            border: 1px solid var(--border-gray);
        }

        .card-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .card-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1280px) {
            .card-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* ========== Documents Table ========== */
        .table-container {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-gray);
            overflow: hidden;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .documents-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .documents-table thead {
            background: var(--light-gray);
            border-bottom: 1px solid var(--border-gray);
        }

        .documents-table th {
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--secondary-gray);
        }

        .documents-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-gray);
            vertical-align: middle;
        }

        .documents-table tbody tr:last-child td {
            border-bottom: none;
        }

        .documents-table tbody tr:hover {
            background: var(--light-gray);
        }

        .document-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .document-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-blue-light);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-blue);
            flex-shrink: 0;
        }

        .document-icon svg {
            width: 20px;
            height: 20px;
        }

        .document-name {
            font-weight: 600;
            color: var(--black);
            font-size: 0.9375rem;
        }

        .document-type {
            font-size: 0.8125rem;
            color: var(--secondary-gray);
        }

        .document-size {
            font-size: 0.875rem;
            color: var(--black);
        }

        .document-date {
            font-size: 0.875rem;
            color: var(--secondary-gray);
        }

        /* Mobile: Stack table into cards */
        @media (max-width: 767px) {
            .documents-table {
                min-width: unset;
            }

            .documents-table thead {
                display: none;
            }

            .documents-table tbody tr {
                display: flex;
                flex-direction: column;
                padding: 1rem;
                border-bottom: 1px solid var(--border-gray);
                gap: 0.75rem;
            }

            .documents-table tbody tr:hover {
                background: var(--white);
            }

            .documents-table td {
                padding: 0;
                border-bottom: none;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .documents-table td::before {
                content: attr(data-label);
                font-weight: 600;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--secondary-gray);
                margin-right: 1rem;
            }

            .documents-table td:first-child::before {
                display: none;
            }

            .documents-table td:first-child {
                justify-content: flex-start;
            }

            .documents-table td:last-child {
                margin-top: 0.5rem;
                padding-top: 0.75rem;
                border-top: 1px solid var(--border-gray);
                justify-content: flex-end;
            }

            .documents-table td:last-child::before {
                display: none;
            }
        }

        /* ========== Transfer Cards ========== */
        .transfer-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .transfer-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .transfer-type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-patient {
            background: var(--primary-blue-light);
            color: var(--primary-blue);
        }

        .badge-equipment {
            background: var(--warning-yellow-light);
            color: var(--warning-yellow);
        }

        .transfer-id {
            font-size: 0.875rem;
            color: var(--secondary-gray);
        }

        .transfer-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .transfer-detail-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .transfer-detail-row svg {
            flex-shrink: 0;
            color: var(--secondary-gray);
        }

        .transfer-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: var(--success-green-light);
            border-radius: var(--radius-sm);
            color: var(--success-green);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success-green);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        /* ========== Modal ========== */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 1000;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: var(--white);
            border-radius: var(--radius-lg);
            max-width: 400px;
            width: 100%;
            box-shadow: var(--shadow-lg);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-gray);
        }

        .modal-title {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--light-gray);
            border-radius: var(--radius-sm);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary-gray);
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: var(--border-gray);
            color: var(--black);
        }

        .modal-body {
            padding: 1.5rem;
            text-align: center;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-gray);
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .qr-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .qr-code {
            padding: 1rem;
            background: var(--white);
            border: 2px solid var(--border-gray);
            border-radius: var(--radius-md);
        }

        .qr-code img {
            display: block;
        }

        .qr-document-name {
            font-weight: 600;
            color: var(--black);
        }

        /* ========== Forms ========== */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--black);
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            border: 2px solid var(--border-gray);
            border-radius: var(--radius-md);
            background: var(--white);
            transition: border-color 0.2s ease;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-blue);
        }

        .form-hint {
            font-size: 0.875rem;
            color: var(--secondary-gray);
            margin-top: 0.5rem;
        }

        .form-disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* ========== Alert ========== */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 640px) {
            .alert {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }

        .alert-warning {
            background: var(--warning-yellow-light);
            border: 1px solid var(--warning-yellow);
        }

        .alert-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-icon {
            flex-shrink: 0;
            color: var(--warning-yellow);
        }

        .alert-text {
            font-weight: 500;
            color: var(--black);
        }

        /* ========== Stepper ========== */
        .stepper {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        @media (min-width: 768px) {
            .stepper {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }

        .stepper-step {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .stepper-indicator {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
            border: 3px solid var(--border-gray);
            background: var(--white);
            color: var(--secondary-gray);
        }

        .stepper-step.completed .stepper-indicator {
            background: var(--success-green);
            border-color: var(--success-green);
            color: var(--white);
        }

        .stepper-step.active .stepper-indicator {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            color: var(--white);
            animation: pulse 2s infinite;
        }

        .stepper-content {
            flex: 1;
        }

        .stepper-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--secondary-gray);
        }

        .stepper-step.completed .stepper-title,
        .stepper-step.active .stepper-title {
            color: var(--black);
        }

        .stepper-connector {
            display: none;
            height: 3px;
            flex: 1;
            background: var(--border-gray);
            margin: 0 0.5rem;
        }

        .stepper-connector.completed {
            background: var(--success-green);
        }

        @media (min-width: 768px) {
            .stepper-connector {
                display: block;
            }

            .stepper-step {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }

            .stepper-content {
                order: 1;
            }
        }

        /* ========== Transfer Detail ========== */
        .transfer-detail-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-gray);
        }

        .transfer-detail-header {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-gray);
        }

        @media (min-width: 640px) {
            .transfer-detail-header {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }

        .transfer-detail-info h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .transfer-detail-info p {
            color: var(--secondary-gray);
        }

        .action-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-gray);
        }

        .action-section h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--secondary-gray);
        }

        .report-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-gray);
        }

        /* ========== Empty State ========== */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background: var(--white);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-gray);
        }

        .empty-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            background: var(--light-gray);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary-gray);
        }

        .empty-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-text {
            color: var(--secondary-gray);
            margin-bottom: 1.5rem;
        }

        /* ========== View Toggles ========== */
        .view-toggle {
            display: none;
        }

        .view-section {
            display: none;
        }

        .view-section.active {
            display: block;
        }

        /* ========== Progressive Disclosure ========== */
        .progressive-step {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 1.5rem;
            border-left: 2px solid var(--border-gray);
            margin-left: 0.75rem;
        }

        .progressive-step:last-child {
            border-left: 2px solid transparent;
        }

        .progressive-step::before {
            content: "";
            position: absolute;
            left: -0.5rem;
            top: 0;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: var(--border-gray);
            border: 3px solid var(--white);
        }

        .progressive-step.completed::before {
            background: var(--success-green);
        }

        .progressive-step.active::before {
            background: var(--primary-blue);
        }

        .progressive-hint {
            background: var(--light-gray);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
            color: var(--secondary-gray);
            font-size: 0.875rem;
            font-style: italic;
        }

        /* ========== Back Button ========== */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--secondary-gray);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 1.5rem;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 1rem;
        }

        .back-button:hover {
            color: var(--primary-blue);
        }

        /* ========== Icons ========== */
        .icon {
            width: 20px;
            height: 20px;
            display: inline-block;
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php componente('admin/sidebar'); ?>

        <!-- Main Content Wrapper -->
        <div class="main-wrapper">
            <!-- Header -->
            <?php componente('admin/header') ?>

            <!-- Main Content -->
            <main class="main">
                <!-- En contenido es donde se va a inyectar las etiquetas <section> y las diferentes vistas -->
                <?= $contenido ?>

                             

                <!-- MODULE 2: Ambulance Transfer Tracking -->
                <section id="transfers" class="section">
                    <!-- View: Transfer List -->
                    <div id="transfer-list" class="view-section active">
                        <div class="section-header">
                            <div>
                                <h2 class="section-title">Trazabilidad de Traslados</h2>
                                <p class="section-description">
                                    Gestione y monitoree los traslados activos
                                </p>
                            </div>
                            <button
                                class="btn btn-primary"
                                onclick="showView('new-transfer')">
                                <svg
                                    class="icon"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Solicitar Traslado
                            </button>
                        </div>

                        <h3
                            style="
                  font-size: 1rem;
                  color: var(--secondary-gray);
                  margin-bottom: 1rem;
                ">
                            Traslados Activos
                        </h3>

                        <div class="card-grid">
                            <!-- Active Transfer 1 -->
                            <div
                                class="card transfer-card"
                                onclick="showView('transfer-detail')"
                                style="cursor: pointer">
                                <div class="transfer-header">
                                    <span class="transfer-type-badge badge-patient">Paciente</span>
                                    <span class="transfer-id">#TRF-2024-0891</span>
                                </div>
                                <div class="transfer-details">
                                    <div class="transfer-detail-row">
                                        <svg
                                            class="icon"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Hospital Central - Clinica Norte
                                    </div>
                                    <div class="transfer-detail-row">
                                        <svg
                                            class="icon"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Chofer: Juan Perez
                                    </div>
                                </div>
                                <div class="transfer-status">
                                    <span class="status-dot"></span>
                                    En transito
                                </div>
                            </div>

                            <!-- Active Transfer 2 -->
                            <div class="card transfer-card" style="cursor: pointer">
                                <div class="transfer-header">
                                    <span class="transfer-type-badge badge-equipment">Equipamiento</span>
                                    <span class="transfer-id">#TRF-2024-0890</span>
                                </div>
                                <div class="transfer-details">
                                    <div class="transfer-detail-row">
                                        <svg
                                            class="icon"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Deposito - Hospital Central
                                    </div>
                                    <div class="transfer-detail-row">
                                        <svg
                                            class="icon"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Chofer: Maria Garcia
                                    </div>
                                </div>
                                <div class="transfer-status">
                                    <span class="status-dot"></span>
                                    Arribo al destino
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- View: New Transfer Form -->
                    <div id="new-transfer" class="view-section">
                        <button class="back-button" onclick="showView('transfer-list')">
                            <svg
                                class="icon"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 19l-7-7 7-7" />
                            </svg>
                            Volver a la lista
                        </button>

                        <div class="card">
                            <h2
                                style="
                    font-size: 1.25rem;
                    font-weight: 700;
                    margin-bottom: 1.5rem;
                  ">
                                Nuevo Traslado
                            </h2>

                            <!-- Alert: No vehicles -->
                            <div class="alert alert-warning">
                                <div class="alert-content">
                                    <svg
                                        class="alert-icon"
                                        width="24"
                                        height="24"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span class="alert-text">No se encuentran vehiculos disponibles en este
                                        momento</span>
                                </div>
                                <button class="btn btn-warning btn-small">
                                    Solicitar SAME
                                </button>
                            </div>

                            <form>
                                <!-- Step 1: Transfer Type -->
                                <div class="progressive-step completed">
                                    <div class="form-group">
                                        <label class="form-label">Elemento a trasladar</label>
                                        <select class="form-select">
                                            <option value="">Seleccione una opcion</option>
                                            <option value="patient" selected>Paciente</option>
                                            <option value="equipment">Equipamiento</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Step 2: Driver -->
                                <div class="progressive-step active">
                                    <div class="form-group">
                                        <label class="form-label">Chofer</label>
                                        <select class="form-select">
                                            <option value="">Seleccione un chofer</option>
                                            <option value="1">Juan Perez</option>
                                            <option value="2">Maria Garcia</option>
                                            <option value="3">Carlos Lopez</option>
                                        </select>
                                        <p class="form-hint">
                                            Seleccione el chofer asignado al traslado
                                        </p>
                                    </div>
                                </div>

                                <!-- Step 3: Nurse (disabled until driver selected) -->
                                <div class="progressive-step">
                                    <div class="form-group form-disabled">
                                        <label class="form-label">Enfermero</label>
                                        <div class="progressive-hint">
                                            Este campo se habilitara despues de seleccionar el
                                            chofer
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 4: Additional Details (disabled) -->
                                <div class="progressive-step">
                                    <div class="form-group form-disabled">
                                        <label class="form-label">Detalles adicionales</label>
                                        <div class="progressive-hint">
                                            Los campos adicionales apareceran secuencialmente al
                                            completar los anteriores
                                        </div>
                                    </div>
                                </div>

                                <button type="button" class="btn btn-primary btn-large">
                                    Confirmar Traslado
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- View: Transfer Detail -->
                    <div id="transfer-detail" class="view-section">
                        <button class="back-button" onclick="showView('transfer-list')">
                            <svg
                                class="icon"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 19l-7-7 7-7" />
                            </svg>
                            Volver a la lista
                        </button>

                        <div class="transfer-detail-section">
                            <div class="transfer-detail-header">
                                <div class="transfer-detail-info">
                                    <h3>Traslado #TRF-2024-0891</h3>
                                    <p>Paciente - Hospital Central - Clinica Norte</p>
                                </div>
                                <span class="transfer-type-badge badge-patient">Paciente</span>
                            </div>

                            <!-- Stepper -->
                            <div class="stepper">
                                <div class="stepper-step completed">
                                    <div class="stepper-indicator">
                                        <svg
                                            width="16"
                                            height="16"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="3"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <div class="stepper-content">
                                        <div class="stepper-title">En transito</div>
                                    </div>
                                </div>
                                <div class="stepper-connector completed"></div>

                                <div class="stepper-step active">
                                    <div class="stepper-indicator">2</div>
                                    <div class="stepper-content">
                                        <div class="stepper-title">Arribo al destino</div>
                                    </div>
                                </div>
                                <div class="stepper-connector"></div>

                                <div class="stepper-step">
                                    <div class="stepper-indicator">3</div>
                                    <div class="stepper-content">
                                        <div class="stepper-title">En transito regreso</div>
                                    </div>
                                </div>
                                <div class="stepper-connector"></div>

                                <div class="stepper-step">
                                    <div class="stepper-indicator">4</div>
                                    <div class="stepper-content">
                                        <div class="stepper-title">Arribo a Central</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Section -->
                            <div class="action-section">
                                <h4>Proxima accion</h4>
                                <button class="btn btn-success btn-large">
                                    <svg
                                        class="icon"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                    Registrar Arribo a Destino
                                </button>
                            </div>

                            <!-- Report Section -->
                            <div class="report-section">
                                <button class="btn btn-danger">
                                    <svg
                                        class="icon"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    Algo salio mal? Generar reporte
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div
        id="qr-modal"
        class="modal-overlay"
        onclick="closeModalOnOverlay(event)">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Codigo QR del Documento</h3>
                <button class="modal-close" onclick="closeModal('qr-modal')">
                    <svg
                        width="16"
                        height="16"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="qr-container">
                    <div class="qr-code">
                        <img
                            src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=placeholder-url"
                            alt="Codigo QR"
                            width="200"
                            height="200" />
                    </div>
                    <p class="qr-document-name" id="qr-document-name">
                        Nombre del documento
                    </p>
                    <p style="font-size: 0.875rem; color: var(--secondary-gray)">
                        Escanee este codigo para acceder al documento
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button
                    class="btn btn-secondary btn-small"
                    onclick="closeModal('qr-modal')">
                    Cerrar
                </button>
                <button class="btn btn-primary btn-small">
                    <svg
                        class="icon"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar QR
                </button>
            </div>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div
        id="upload-modal"
        class="modal-overlay"
        onclick="closeModalOnOverlay(event)">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Cargar Nuevo Documento</h3>
                <button class="modal-close" onclick="closeModal('upload-modal')">
                    <svg
                        width="16"
                        height="16"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div
                    class="empty-state"
                    style="border-style: dashed; cursor: pointer">
                    <div class="empty-icon">
                        <svg
                            width="32"
                            height="32"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                    </div>
                    <p class="empty-title">Arrastre un archivo aqui</p>
                    <p class="empty-text">o haga clic para seleccionar</p>
                </div>
            </div>
            <div class="modal-footer">
                <button
                    class="btn btn-secondary btn-small"
                    onclick="closeModal('upload-modal')">
                    Cancelar
                </button>
                <button class="btn btn-primary btn-small">Subir Documento</button>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle
        function toggleSidebar() {
            document.getElementById("sidebar").classList.toggle("open");
            document.getElementById("sidebar-overlay").classList.toggle("active");
        }

        function closeSidebar() {
            document.getElementById("sidebar").classList.remove("open");
            document.getElementById("sidebar-overlay").classList.remove("active");
        }

        // Navigation
        const sectionTitles = {
            documents: "Gestion de Documentos",
            transfers: "Trazabilidad de Traslados",
        };

        document.querySelectorAll(".nav-link").forEach((link) => {
            link.addEventListener("click", (e) => {
                e.preventDefault();
                const sectionId = link.dataset.section;

                // Update nav
                document
                    .querySelectorAll(".nav-link")
                    .forEach((l) => l.classList.remove("active"));
                link.classList.add("active");

                // Update sections
                document
                    .querySelectorAll(".section")
                    .forEach((s) => s.classList.remove("active"));
                document.getElementById(sectionId).classList.add("active");

                // Update header title
                document.getElementById("header-title").textContent =
                    sectionTitles[sectionId] || sectionId;

                // Reset transfer views when switching sections
                if (sectionId === "transfers") {
                    showView("transfer-list");
                }

                // Close sidebar on mobile after navigation
                closeSidebar();
            });
        });

        // View switching (for transfers module)
        function showView(viewId) {
            document
                .querySelectorAll("#transfers .view-section")
                .forEach((v) => v.classList.remove("active"));
            document.getElementById(viewId).classList.add("active");
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add("active");
            document.body.style.overflow = "hidden";
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove("active");
            document.body.style.overflow = "";
        }

        function closeModalOnOverlay(event) {
            if (event.target.classList.contains("modal-overlay")) {
                event.target.classList.remove("active");
                document.body.style.overflow = "";
            }
        }

        function openQRModal(documentName) {
            document.getElementById("qr-document-name").textContent = documentName;
            openModal("qr-modal");
        }

        // Close modal on Escape key
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                document
                    .querySelectorAll(".modal-overlay.active")
                    .forEach((modal) => {
                        modal.classList.remove("active");
                    });
                document.body.style.overflow = "";
                closeSidebar();
            }
        });
    </script>
</body>

</html>