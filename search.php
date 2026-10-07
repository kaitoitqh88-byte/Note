<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloudflare Domain Search - Tìm Kiếm Domain</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/rpg-shooter-theme.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }

        .search-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }

        .search-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .search-form {
            padding: 25px;
            background: #f8f9fa;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .search-input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .search-mode-toggle {
            position: absolute;
            top: -35px;
            right: 0;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .search-mode-toggle:hover {
            background: var(--secondary-color);
            color: white;
        }

        .search-mode-toggle.active {
            background: var(--accent-color);
            color: white;
        }

        .search-input-container {
            position: relative;
        }

        .multi-line-container {
            display: none;
        }

        .multi-line-container.active {
            display: block;
        }

        .single-line-container {
            display: block;
        }

        .search-input {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 15px 50px 15px 20px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-textarea {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 15px 20px;
            font-size: 16px;
            transition: all 0.3s ease;
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .search-input:focus, .search-textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(103, 126, 234, 0.25);
        }

        .search-help-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
            display: block;
        }

        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-color);
            border: none;
            border-radius: 10px;
            padding: 10px 15px;
            color: white;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-50%) scale(1.05);
        }

        .filter-section {
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .results-section {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .results-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px 25px;
            border-bottom: 2px solid #dee2e6;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .results-table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            font-size: 14px;
        }

        .results-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }

        .results-table tbody tr:hover {
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .domain-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 4px;
        }

        .domain-id {
            font-size: 12px;
            color: #6c757d;
            font-family: monospace;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }

        .status-moved {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .plan-badge {
            background: rgba(103, 126, 234, 0.1);
            color: var(--primary-color);
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .domain-actions {
            margin-left: auto;
        }

        .action-btn {
            padding: 4px 8px;
            border-radius: 6px;
            border: none;
            margin: 0 2px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .actions-cell {
            white-space: nowrap;
            width: 160px;
        }

        .relevance-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .relevance-score {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
        }

        /* Responsive table styles */
        @media (max-width: 768px) {
            .results-table th,
            .results-table td {
                padding: 8px 6px;
                font-size: 12px;
            }
            
            .domain-name {
                font-size: 14px;
            }
            
            .actions-cell {
                width: 80px;
            }
            
            .action-btn {
                padding: 3px 6px;
                font-size: 10px;
                margin: 0 1px;
            }
            
            .results-table th:nth-child(4),
            .results-table td:nth-child(4) {
                display: none; /* Hide created date on mobile */
            }
        }

        .pagination-wrapper {
            padding: 25px;
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
        }

        .search-stats {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 15px;
            z-index: 10;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .relevance-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 6px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            font-size: 11px;
            cursor: help;
        }

        .search-context-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 6px;
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 10px;
            font-size: 10px;
            cursor: help;
        }

        .bulk-search-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
        }

        .bulk-search-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 13px;
        }

        .bulk-stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .search-term-stat {
            margin-bottom: 3px;
            font-size: 12px;
        }

        .search-breakdown {
            border-top: 1px solid #dee2e6;
            padding-top: 8px;
            font-size: 12px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Enhanced status badges */
        .status-active { background-color: #28a745; color: white; }
        .status-pending { background-color: #ffc107; color: #212529; }
        .status-initializing { background-color: #17a2b8; color: white; }
        .status-moved { background-color: #6c757d; color: white; }
        .status-deleted { background-color: #dc3545; color: white; }
        .status-deactivated { background-color: #6f42c1; color: white; }

        /* Domain item hover effects */
        .domain-item:hover {
            background-color: #f8f9fa;
            transition: background-color 0.2s ease;
        }

        .domain-item[data-relevance="100"] {
            border-left: 4px solid #28a745;
        }

        .domain-item[data-relevance="90"], .domain-item[data-relevance="91"], .domain-item[data-relevance="92"], 
        .domain-item[data-relevance="93"], .domain-item[data-relevance="94"], .domain-item[data-relevance="95"],
        .domain-item[data-relevance="96"], .domain-item[data-relevance="97"], .domain-item[data-relevance="98"], 
        .domain-item[data-relevance="99"] {
            border-left: 4px solid #ffc107;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .search-header {
                padding: 20px 0;
            }
            
            .domain-meta {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .domain-actions {
                margin-left: 0;
                margin-top: 10px;
            }
        }

        /* Dashboard Actions Panel */
        .dashboard-actions-panel {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .action-group {
            background: rgba(0, 123, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .action-group:last-child {
            margin-bottom: 0;
        }

        /* Bulk Selection Styles */
        .bulk-select-cell {
            width: 50px;
        }

        .domain-checkbox:checked ~ label {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
        }

        .bulk-select-cell .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        /* HTTPS Status Styling */
        .https-enabled {
            color: #198754;
        }

        .https-disabled {
            color: #dc3545;
        }

        .https-unknown {
            color: #6c757d;
        }

        /* View Toggle Buttons */
        .btn-group .btn.active {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }

        /* Progress Indicator */
        #dashboardProgressIndicator {
            border-left: 4px solid #0d6efd;
            background: linear-gradient(90deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.05));
            padding: 15px;
            border-radius: 8px;
        }

        /* Action Buttons Hover Effects */
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(25, 135, 84, 0.3);
            transition: all 0.2s ease;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
            transition: all 0.2s ease;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(13, 202, 240, 0.3);
            transition: all 0.2s ease;
        }

        /* Mobile Responsiveness for Dashboard Actions */
        @media (max-width: 768px) {
            .action-group {
                margin-bottom: 20px;
            }
            
            .btn-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-group {
                margin-bottom: 10px;
                width: 100%;
            }
        }

        /* Enhanced Search Stats Panel */
        .search-stats-enhanced {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .search-results-summary {
            font-size: 1.05em;
        }

        .performance-metrics .badge {
            font-size: 0.75em;
        }

        .search-scope .badge {
            font-weight: normal;
        }

        /* Enhanced Loading Overlay */
        .loading-overlay-enhanced {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-radius: 15px;
        }

        .loading-content {
            text-align: center;
            padding: 20px;
        }

        .loading-spinner-enhanced {
            margin-bottom: 10px;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        .loading-text h5 {
            color: #495057;
            font-weight: 500;
        }

        /* Enhanced Empty State */
        .empty-state-enhanced {
            text-align: center;
            padding: 80px 20px;
            color: #6c757d;
        }

        .empty-state-content {
            max-width: 600px;
            margin: 0 auto;
        }

        .empty-icon i {
            font-size: 5rem;
            opacity: 0.3;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .quick-search-suggestions {
            background: rgba(102, 126, 234, 0.05);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .quick-search-suggestions h6 {
            color: var(--primary-color);
            font-weight: 600;
        }

        .debug-actions {
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
            margin-top: 20px;
        }

        /* Bulk Search Results Summary */
        #bulkSearchSummary {
            background: linear-gradient(45deg, rgba(13, 110, 253, 0.05), rgba(13, 202, 240, 0.05));
            border-radius: 8px;
            padding: 15px;
        }

        .bulk-stat-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 10px;
        }

        .bulk-stat-item h6 {
            margin-bottom: 5px;
            font-size: 0.9em;
        }

        .bulk-stat-item .stat-value {
            font-size: 1.1em;
            font-weight: 600;
        }

        /* Cache Indicators */
        .cache-indicator-success {
            color: #198754 !important;
        }

        .cache-indicator-warning {
            color: #fd7e14 !important;
        }

        .cache-indicator-secondary {
            color: #6c757d !important;
        }

        /* Progress Bars */
        .progress {
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            transition: width 0.3s ease;
        }

        /* Search Details Modal Styling */
        .search-detail-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .search-detail-item:last-child {
            border-bottom: none;
        }

        .search-detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 120px;
            display: inline-block;
        }

        .search-detail-value {
            color: #6c757d;
        }

        /* Enhanced Result Cards for Card View */
        .result-card-enhanced {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .result-card-enhanced:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .domain-card-header {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
        }

        .domain-card-body {
            padding: 20px;
        }

        .domain-name-large {
            font-size: 1.25em;
            font-weight: 600;
            color: var(--primary-color);
        }

        .domain-metadata-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .metadata-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .metadata-item .label {
            font-size: 0.8em;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .metadata-item .value {
            font-weight: 600;
        }

        /* Recent Searches Styling */
        .recent-searches-container {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
            max-height: 150px;
            overflow-y: auto;
        }

        .recent-searches-header {
            display: flex;
            align-items: center;
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .recent-searches-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .recent-search-item {
            display: inline-flex;
            align-items: center;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 15px;
            padding: 4px 10px;
            font-size: 0.85em;
            color: #495057;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .recent-search-item:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-1px);
            text-decoration: none;
        }
        
        /* Search Sidebar Layout */
        .search-layout {
            display: flex;
            gap: 0;
            min-height: calc(100vh - 200px);
        }
        
        .search-sidebar {
            width: 280px;
            background: rgba(0, 20, 40, 0.9);
            border-right: 2px solid var(--tactical-primary);
            padding: 1.5rem;
            position: sticky;
            top: 80px;
            height: fit-content;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }
        
        .search-main {
            flex: 1;
            width: calc(100% - 280px);
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-section {
            background: rgba(0, 255, 127, 0.05);
            border: 1px solid rgba(0, 255, 127, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .sidebar-title {
            color: var(--tactical-primary);
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--tactical-primary);
            padding-bottom: 0.5rem;
        }
        
        .sidebar-btn, .sidebar-link {
            display: block;
            color: var(--tactical-text);
            text-decoration: none;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            font-family: 'Roboto', sans-serif;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 255, 127, 0.2);
            background: rgba(0, 255, 127, 0.05);
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
            width: 100%;
        }
        
        .sidebar-btn:hover, .sidebar-link:hover {
            color: #000;
            background: var(--tactical-primary);
            border-color: var(--tactical-primary);
            text-decoration: none;
            transform: translateY(-2px);
        }
        
        /* Mobile Responsive */
        @media (max-width: 767px) {
            .search-layout {
                flex-direction: column;
            }
            
            .search-sidebar {
                width: 100%;
                position: static;
                max-height: none;
                height: auto;
            }
            
            .search-main {
                width: 100%;
                padding: 1rem;
            }
        }

        .recent-search-item .search-text {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .recent-search-item .search-count {
            font-size: 0.75em;
            margin-left: 6px;
            opacity: 0.7;
            background: rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 1px 4px;
        }

        .recent-search-item .remove-btn {
            margin-left: 6px;
            opacity: 0.6;
            cursor: pointer;
            font-size: 0.7em;
        }

        .recent-search-item .remove-btn:hover {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .recent-searches-list {
                gap: 4px;
            }
            
            .recent-search-item {
                font-size: 0.8em;
                padding: 3px 8px;
            }
            
            .recent-search-item .search-text {
                max-width: 80px;
            }
            color: #495057;
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'search'; // Set active page for navigation
    include 'includes/main_navigation.php'; 
    ?>

    <div class="main-wrapper">
        <!-- Header -->
        <div class="search-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-search me-3"></i>
                        Tìm Kiếm Domain
                    </h1>
                    <p class="mb-0 opacity-75">Tìm kiếm và quản lý các domain trong Cloudflare của bạn</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="domain_status_checker.php" class="btn btn-outline-light me-2">
                        <i class="fas fa-check-circle me-2"></i>Check Status
                    </a>
                    <a href="check_301/" class="btn btn-outline-light me-2">
                        <i class="fas fa-link me-2"></i>301 Chain Checker
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-light me-2">
                        <i class="fas fa-globe me-2"></i>Zones
                    </a>
                    <a href="index.php" class="btn btn-light btn-lg">
                        <i class="fas fa-home me-2"></i>Trang Chủ
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid p-0">
        <div class="search-layout">
            <!-- Sidebar -->
            <div class="search-sidebar">
                <!-- Search Actions -->
                <div class="sidebar-section">
                    <h6 class="sidebar-title">
                        <i class="fas fa-search me-2"></i>Search Actions
                    </h6>
                    <button type="button" class="sidebar-btn" onclick="submitSearchForm()">
                        <i class="fas fa-play"></i> Execute Search
                    </button>
                    <button type="button" class="sidebar-btn" onclick="clearSearchForm()">
                        <i class="fas fa-broom"></i> Clear Form
                    </button>
                    <button type="button" class="sidebar-btn" onclick="exportSearchResults()">
                        <i class="fas fa-download"></i> Export Results
                    </button>
                </div>
                
                <!-- Search Options -->
                <div class="sidebar-section">
                    <h6 class="sidebar-title">
                        <i class="fas fa-cogs me-2"></i>Options
                    </h6>
                    <button type="button" class="sidebar-btn" onclick="document.getElementById('multiModeToggle').click()">
                        <i class="fas fa-list"></i> Multi Domain Mode
                    </button>
                    <button type="button" class="sidebar-btn" onclick="loadRecentSearches()">
                        <i class="fas fa-history"></i> Recent Searches
                    </button>
                </div>
                
                <!-- Navigation -->
                <div class="sidebar-section">
                    <h6 class="sidebar-title">
                        <i class="fas fa-compass me-2"></i>Navigation
                    </h6>
                    <a href="dns_simple.php" class="sidebar-link">
                        <i class="fas fa-rocket"></i> DNS Deploy
                    </a>
                    <a href="dashboard.php" class="sidebar-link">
                        <i class="fas fa-globe"></i> Zones
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="search-main">

        <!-- Search Form -->
        <div class="search-card">
            <div class="search-form">
                <form id="searchForm" action="index.php" method="GET">
                    <input type="hidden" name="action" value="search">
                    <div class="search-input-group">
                        <!-- Toggle Button -->
                        <button type="button" id="multiModeToggle" class="search-mode-toggle" title="Chuyển sang tìm kiếm nhiều domain">
                            Nhiều Domain
                        </button>
                        
                        <!-- Single Line Search (Default) -->
                        <div class="single-line-container">
                            <input type="text" 
                                   name="q"
                                   id="searchInput" 
                                   class="form-control search-input" 
                                   placeholder="Nhập tên domain để tìm kiếm (ví dụ: example.com, *.jp, hoặc để trống để xem tất cả)..."
                                   value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES); ?>"
                                   autocomplete="off">
                        </div>
                        
                        <!-- Multi Line Search -->
                        <div class="multi-line-container">
                            <textarea name="q_multi"
                                      id="searchTextarea"
                                      class="form-control search-textarea"
                                      rows="5"
                                      placeholder="Nhập nhiều domain, mỗi domain trên một dòng:&#10;example.com&#10;test.org&#10;mydomain.net&#10;..."
                                      autocomplete="off"></textarea>
                            <small class="search-help-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Mỗi domain trên một dòng hoặc cách nhau bằng dấu phẩy. Tối đa 50 domains.
                            </small>
                        </div>
                        
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>

                    <!-- Recent Searches -->
                    <div id="recentSearches" class="recent-searches-container" style="display: none;">
                        <div class="recent-searches-header">
                            <i class="fas fa-history me-2"></i>
                            <span>Tìm kiếm gần đây:</span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-auto text-muted" id="clearAllHistoryBtn" title="Xóa tất cả">
                                <i class="fas fa-trash fa-xs"></i>
                            </button>
                        </div>
                        <div id="recentSearchesList" class="recent-searches-list">
                            <!-- Recent searches will be populated here -->
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="statusFilter" class="form-label">Trạng thái:</label>
                                <select name="status" id="statusFilter" class="form-select">
                                    <option value=""<?php echo ($_GET['status'] ?? '') === '' ? ' selected' : ''; ?>>Tất cả trạng thái</option>
                                    <option value="active"<?php echo ($_GET['status'] ?? '') === 'active' ? ' selected' : ''; ?>>Active</option>
                                    <option value="pending"<?php echo ($_GET['status'] ?? '') === 'pending' ? ' selected' : ''; ?>>Pending</option>
                                    <option value="initializing"<?php echo ($_GET['status'] ?? '') === 'initializing' ? ' selected' : ''; ?>>Initializing</option>
                                    <option value="moved"<?php echo ($_GET['status'] ?? '') === 'moved' ? ' selected' : ''; ?>>Moved</option>
                                    <option value="deleted"<?php echo ($_GET['status'] ?? '') === 'deleted' ? ' selected' : ''; ?>>Deleted</option>
                                    <option value="deactivated"<?php echo ($_GET['status'] ?? '') === 'deactivated' ? ' selected' : ''; ?>>Deactivated</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="perPageSelect" class="form-label">Kết quả mỗi trang:</label>
                                <select name="per_page" id="perPageSelect" class="form-select">
                                    <option value="10"<?php echo ($_GET['per_page'] ?? '20') === '10' ? ' selected' : ''; ?>>10 kết quả</option>
                                    <option value="20"<?php echo ($_GET['per_page'] ?? '20') === '20' ? ' selected' : ''; ?>>20 kết quả</option>
                                    <option value="50"<?php echo ($_GET['per_page'] ?? '20') === '50' ? ' selected' : ''; ?>>50 kết quả</option>
                                    <option value="100"<?php echo ($_GET['per_page'] ?? '20') === '100' ? ' selected' : ''; ?>>100 kết quả</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="button" id="clearBtn" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-times me-1"></i>Xóa bộ lọc
                        </button>
                        <button type="button" id="advancedToggle" class="btn btn-outline-info me-2">
                            <i class="fas fa-cog me-1"></i>Tùy chọn nâng cao
                        </button>
                        <button type="button" id="apiStatsBtn" class="btn btn-outline-success me-2">
                            <i class="fas fa-chart-line me-1"></i>API Stats
                        </button>
                        <button type="button" id="backgroundStatusBtn" class="btn btn-outline-warning">
                            <i class="fas fa-sync-alt me-1"></i>Background
                        </button>
                    </div>

                    <!-- Advanced Options Panel -->
                    <div id="advancedOptionsPanel" class="mt-3" style="display: none;">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-cogs me-2"></i>Tùy chọn tìm kiếm nâng cao
                                </h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="use_cache" id="useCache"<?php echo (($_GET['use_cache'] ?? 'on') === 'on' || ($_GET['use_cache'] ?? 'on') === '1') ? ' checked' : ''; ?>>
                                            <label class="form-check-label" for="useCache">
                                                <i class="fas fa-bolt text-warning me-1"></i>
                                                Sử dụng Cache (tăng tốc độ)
                                            </label>
                                        </div>
                                        <small class="text-muted">Giới thiệu dữ liệu từ cache để tăng tốc độ</small>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="bulk" id="bulkMode"<?php echo (($_GET['bulk'] ?? '') === 'on' || ($_GET['bulk'] ?? '') === '1') ? ' checked' : ''; ?>>
                                            <label class="form-check-label" for="bulkMode">
                                                <i class="fas fa-layer-group text-info me-1"></i>
                                                Bulk Search
                                            </label>
                                        </div>
                                        <small class="text-muted">Tìm kiếm nhiều từ khóa cùng lúc</small>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-warning" id="clearCacheBtn">
                                                <i class="fas fa-trash me-1"></i>Clear Cache
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" id="warmCacheBtn">
                                                <i class="fas fa-fire me-1"></i>Warm Cache
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="alert alert-info py-2">
                                            <small>
                                                <strong>Bulk Search:</strong> Nhập nhiều từ khóa cách nhau bằng dấu phẩy<br>
                                                <strong>Ví dụ:</strong> "example.com, test.net, demo.org"
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Enhanced Search Stats Panel -->
        <div id="searchStats" class="search-stats-enhanced" style="display: none;">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <div class="row align-items-center">
                        <div class="col-lg-5">
                            <div class="search-results-summary">
                                <span id="statsText" class="fw-bold"></span>
                                <div id="searchMetadata" class="mt-1"></div>
                            </div>
                        </div>
                        
                        <div class="col-lg-2 text-center">
                            <div class="performance-metrics">
                                <div id="performanceInfo" class="text-muted small"></div>
                                <div id="cacheIndicator" class="mt-1"></div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 text-center">
                            <div class="search-scope">
                                <small class="text-muted">
                                    <i class="fas fa-search me-1"></i>Tìm kiếm trong: 
                                    <span id="searchFields" class="badge bg-light text-dark"></span>
                                </small>
                            </div>
                        </div>
                        
                        <div class="col-lg-2 text-end">
                            <div class="search-actions">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="domainSearch.showSearchDetails()" id="searchDetailsBtn">
                                    <i class="fas fa-info-circle me-1"></i>Chi tiết
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bulk Search Results Summary -->
                    <div id="bulkSearchSummary" class="mt-3 pt-3 border-top" style="display: none;">
                        <div class="row">
                            <div class="col-12">
                                <h6 class="mb-2">
                                    <i class="fas fa-layer-group me-1 text-info"></i>Bulk Search Results
                                </h6>
                                <div id="bulkStatsContainer"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="results-section position-relative">
            <div class="results-header">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <h4 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Kết quả tìm kiếm
                        </h4>
                    </div>
                    <div class="col-md-8 text-end">
                        <div class="btn-toolbar justify-content-end" role="toolbar">
                            <!-- View Toggle -->
                            <div class="btn-group btn-group-sm me-2" role="group">
                                <button type="button" class="btn btn-outline-secondary active" id="tableViewBtn" onclick="domainSearch.toggleView('table')">
                                    <i class="fas fa-table me-1"></i>Table
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="cardViewBtn" onclick="domainSearch.toggleView('card')">
                                    <i class="fas fa-th me-1"></i>Cards
                                </button>
                            </div>
                            
                            <!-- Management Actions -->
                            <div class="btn-group btn-group-sm me-2" role="group">
                                <button type="button" id="dashboardActionsToggle" class="btn btn-outline-info" onclick="domainSearch.toggleDashboardActions()">
                                    <i class="fas fa-tools me-1"></i>Dashboard Actions
                                </button>
                                <button type="button" id="refreshBtn" class="btn btn-outline-primary" onclick="domainSearch.refreshResults()">
                                    <i class="fas fa-sync-alt me-1"></i>Làm mới
                                </button>
                            </div>
                            
                            <!-- Export Actions -->
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" id="exportBtn" class="btn btn-outline-success" onclick="domainSearch.exportResults()">
                                    <i class="fas fa-download me-1"></i>Export CSV
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="domainSearch.showExportOptions()">
                                    <i class="fas fa-file-export me-1"></i>Export Options
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Management Panel -->
            <div id="dashboardActionsPanel" class="dashboard-actions-panel mb-4" style="display: none;">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-tools me-2"></i>Quản lý Domain - Dashboard Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- SSL/HTTPS Management -->
                            <div class="col-lg-4">
                                <div class="action-group">
                                    <h6 class="text-info mb-3">
                                        <i class="fas fa-lock me-1"></i>SSL/HTTPS Management
                                    </h6>
                                    <div class="btn-group-vertical d-grid gap-2">
                                        <button type="button" class="btn btn-primary" id="checkAlwaysHttpsBtn" onclick="domainSearch.checkAlwaysHTTPS()">
                                            <i class="fas fa-search me-2"></i>Kiểm tra Always HTTPS Status
                                        </button>
                                        <button type="button" class="btn btn-success" id="activateAllHttpsBtn" onclick="domainSearch.activateAllHTTPS()">
                                            <i class="fas fa-shield-alt me-2"></i>Kích hoạt HTTPS toàn bộ
                                        </button>
                                        <button type="button" class="btn btn-warning" id="recheckSSLBtn" onclick="domainSearch.recheckAllSSL()">
                                            <i class="fas fa-sync-alt me-2"></i>Kiểm tra lại SSL Status
                                        </button>
                                        <button type="button" class="btn btn-danger d-none" id="stopSSLCheckBtn" onclick="domainSearch.stopSSLCheck()">
                                            <i class="fas fa-stop me-2"></i>Dừng kiểm tra
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Bulk Operations -->
                            <div class="col-lg-4">
                                <div class="action-group">
                                    <h6 class="text-info mb-3">
                                        <i class="fas fa-check-square me-1"></i>Bulk Operations
                                    </h6>
                                    <div class="btn-group-vertical d-grid gap-2">
                                        <button type="button" class="btn btn-primary" id="toggleBulkModeBtn" onclick="domainSearch.toggleBulkMode()">
                                            <i class="fas fa-check-double me-2"></i>Bật/Tắt chế độ chọn nhiều
                                        </button>
                                        <button type="button" class="btn btn-info disabled" id="bulkActionsBtn" onclick="domainSearch.showBulkActions()">
                                            <i class="fas fa-cogs me-2"></i>Actions cho domains đã chọn (<span id="selectedCount">0</span>)
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Cache Management -->
                            <div class="col-lg-4">
                                <div class="action-group">
                                    <h6 class="text-info mb-3">
                                        <i class="fas fa-database me-1"></i>Cache Management
                                    </h6>
                                    <div class="btn-group-vertical d-grid gap-2">
                                        <button type="button" class="btn btn-warning" id="clearSearchCacheBtn" onclick="domainSearch.clearSearchCache()">
                                            <i class="fas fa-trash-alt me-2"></i>Xóa Search Cache
                                        </button>
                                        <button type="button" class="btn btn-info" id="viewCacheStatsBtn" onclick="domainSearch.viewCacheStats()">
                                            <i class="fas fa-chart-bar me-2"></i>Xem thống kê Cache
                                        </button>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="enableCacheToggle" checked onchange="domainSearch.toggleCacheUsage()">
                                            <label class="form-check-label text-sm" for="enableCacheToggle">
                                                Sử dụng Cache
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cache Status Display -->
                        <div id="cacheStatusPanel" class="mt-3" style="display: none;">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Thông tin Cache</h6>
                                <div id="cacheStatusContent">Loading cache information...</div>
                            </div>
                        </div>
                        
                        <!-- Progress Indicator -->
                        <div id="dashboardProgressIndicator" class="mt-3" style="display: none;">
                            <div class="progress mb-2">
                                <div id="dashboardProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"></div>
                            </div>
                            <div id="dashboardProgressText" class="text-center text-muted"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="loadingOverlay" class="loading-overlay-enhanced" style="display: none;">
                <div class="loading-content">
                    <div class="loading-spinner-enhanced">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div class="loading-text mt-3">
                        <h5 class="mb-2">Đang tìm kiếm domains...</h5>
                        <div id="loadingProgress" class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div id="loadingDetails" class="text-muted small"></div>
                    </div>
                </div>
            </div>

            <div id="resultsContainer">
                <div class="empty-state-enhanced">
                    <div class="empty-state-content">
                        <div class="empty-icon mb-4">
                            <i class="fas fa-search"></i>
                        </div>
                        <h5 class="mb-3">Bắt đầu tìm kiếm domains</h5>
                        <p class="text-muted mb-4">
                            Nhập tên domain hoặc sử dụng bộ lọc để tìm kiếm domain của bạn.
                            <br><small>Hỗ trợ fuzzy search, bulk search (ngăn cách bằng dấu phẩy) và filter theo status/plan.</small>
                        </p>
                        
                        <!-- Quick Search Suggestions -->
                        <div class="quick-search-suggestions mb-4">
                            <h6 class="mb-2">Gợi ý tìm kiếm:</h6>
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="domainSearch.quickSearch('*')">
                                    <i class="fas fa-globe me-1"></i>Tất cả domains
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="domainSearch.quickSearch('', 'active')">
                                    <i class="fas fa-check-circle me-1"></i>Active domains
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="domainSearch.quickSearch('', 'pending')">
                                    <i class="fas fa-clock me-1"></i>Pending domains
                                </button>
                            </div>
                        </div>
                        
                        <!-- Debug Actions -->
                        <div class="debug-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="domainSearch.testSearch()" id="debugSearchBtn">
                                <i class="fas fa-bug me-1"></i>Test Search
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="domainSearch.testJavaScript()" id="debugJSBtn">
                                <i class="fas fa-code me-1"></i>Test JavaScript
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div id="paginationWrapper" class="pagination-wrapper" style="display: none;">
                <nav aria-label="Search pagination">
                    <ul id="pagination" class="pagination justify-content-center mb-0">
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Search JavaScript -->
    <script>
        class DomainSearch {
            constructor() {
                this.currentPage = 1;
                this.currentQuery = '';
                this.currentFilters = {};
                this.isLoading = false;
                this.results = [];
                this.apiStats = {};
                this.backgroundStatus = {};
                this.searchHistory = [];
                
                // Dashboard Integration Properties
                this.bulkMode = false;
                this.selectedDomains = [];
                this.currentView = 'table';
                
                // Cache Management Properties
                this.cacheEnabled = true;
                this.cacheStats = null;
                
                // Enhanced UI Properties
                this.lastSearchInfo = {};
                this.lastResultInfo = {};
                
                this.initializeEvents();
                this.loadInitialData();
                
                // Check background loader status with delay to avoid race conditions
                setTimeout(() => {
                    this.checkBackgroundStatus();
                }, 500);
            }

            initializeEvents() {
                // Search form submit
                const searchForm = document.getElementById('searchForm');
                if (searchForm) {
                    searchForm.addEventListener('submit', (e) => {
                        e.preventDefault();
                        
                        // Check if we're in multi-line mode
                        const multiContainer = document.querySelector('.multi-line-container');
                        const isMultiMode = multiContainer && multiContainer.classList.contains('active');
                        
                        if (isMultiMode) {
                            // Get domains from textarea
                            const searchTextarea = document.getElementById('searchTextarea');
                            if (searchTextarea) {
                                const domains = this.parseMultiDomains(searchTextarea.value.trim());
                                if (domains.length > 0) {
                                    this.performMultiSearch(domains);
                                } else {
                                    this.showNotification('Vui lòng nhập ít nhất một domain hợp lệ', 'warning');
                                }
                            }
                        } else {
                            // Regular single search
                            this.performSearch(1);
                        }
                    });
                }

                // Real-time search on input  
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    let searchTimeout;
                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(searchTimeout);
                        const query = e.target.value.trim();
                        
                        if (query.length >= 2) {
                            searchTimeout = setTimeout(() => {
                                this.performSearch(1);
                            }, 800); // Delay 800ms
                        }
                    });

                    // Enter key handling
                    searchInput.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            clearTimeout(searchTimeout);
                            this.performSearch(1);
                        }
                    });
                }

                // Filter change events
                const statusFilter = document.getElementById('statusFilter');
                if (statusFilter) {
                    statusFilter.addEventListener('change', () => {
                        this.performSearch(1);
                    });
                }

                const perPageSelect = document.getElementById('perPageSelect');
                if (perPageSelect) {
                    perPageSelect.addEventListener('change', () => {
                        this.performSearch(1);
                    });
                }

                // Advanced options toggle
                const advancedToggle = document.getElementById('advancedToggle');
                if (advancedToggle) {
                    advancedToggle.addEventListener('click', () => {
                        this.toggleAdvancedOptions();
                    });
                }

                // API Stats button
                const apiStatsBtn = document.getElementById('apiStatsBtn');
                if (apiStatsBtn) {
                    apiStatsBtn.addEventListener('click', () => {
                        this.showAPIStats();
                    });
                }

                // Background Status button
                const backgroundStatusBtn = document.getElementById('backgroundStatusBtn');
                if (backgroundStatusBtn) {
                    backgroundStatusBtn.addEventListener('click', () => {
                        this.showBackgroundStatus();
                    });
                }

                // Clear Cache button
                const clearCacheBtn = document.getElementById('clearCacheBtn');
                if (clearCacheBtn) {
                    clearCacheBtn.addEventListener('click', () => {
                        this.clearCache();
                    });
                }

                // Warm Cache button
                const warmCacheBtn = document.getElementById('warmCacheBtn');
                if (warmCacheBtn) {
                    warmCacheBtn.addEventListener('click', () => {
                        this.warmCache();
                    });
                }

                // Clear filters button
                const clearBtn = document.getElementById('clearBtn');
                if (clearBtn) {
                    clearBtn.addEventListener('click', () => {
                        this.clearFilters();
                    });
                }

                // Global error handler
                window.addEventListener('error', (e) => {
                    console.error('Search page error:', e.error);
                    this.showNotification('Đã xảy ra lỗi JavaScript. Vui lòng refresh trang.', 'error');
                });
                document.getElementById('searchInput').addEventListener('input', 
                    this.debounce(() => this.performSearch(1), 500)
                );

                // Filter changes
                document.getElementById('statusFilter').addEventListener('change', () => {
                    this.performSearch(1);
                });

                document.getElementById('perPageSelect').addEventListener('change', () => {
                    this.performSearch(1);
                });

                // Clear filters
                document.getElementById('clearBtn').addEventListener('click', () => {
                    this.clearFilters();
                });

                // Refresh
                document.getElementById('refreshBtn').addEventListener('click', () => {
                    this.refreshResults();
                });

                // Export
                document.getElementById('exportBtn').addEventListener('click', () => {
                    this.exportResults();
                });

                // Multi-line search mode toggle
                const multiModeToggle = document.getElementById('multiModeToggle');
                if (multiModeToggle) {
                    multiModeToggle.addEventListener('click', () => {
                        this.toggleSearchMode();
                    });
                }

                // Multi-line textarea handling
                const searchTextarea = document.getElementById('searchTextarea');
                if (searchTextarea) {
                    let searchTimeout;
                    searchTextarea.addEventListener('input', (e) => {
                        clearTimeout(searchTimeout);
                        const query = e.target.value.trim();
                        
                        if (query.length >= 2) {
                            // Count domains for validation
                            const domains = this.parseMultiDomains(query);
                            if (domains.length > 50) {
                                this.showNotification('Tối đa 50 domain cho mỗi lần tìm kiếm', 'warning');
                                return;
                            }
                            
                            searchTimeout = setTimeout(() => {
                                this.performMultiSearch(domains);
                            }, 1000); 
                        }
                    });

                    searchTextarea.addEventListener('keydown', (e) => {
                        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                            e.preventDefault();
                            clearTimeout(searchTimeout);
                            const domains = this.parseMultiDomains(e.target.value.trim());
                            this.performMultiSearch(domains);
                        }
                    });
                }
            }

            debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            async loadInitialData() {
                // Load initial data để hiển thị domains
                console.log('=== LOADING INITIAL DATA ===');
                console.log('Current URL:', window.location.href);
                console.log('URL search params:', window.location.search);
                
                // Check if there's a query in URL params
                const urlParams = new URLSearchParams(window.location.search);  
                const initialQuery = urlParams.get('q') || urlParams.get('query');
                const initialStatus = urlParams.get('status');
                const initialPerPage = urlParams.get('per_page');
                const initialUseCache = urlParams.get('use_cache');
                const initialBulk = urlParams.get('bulk');
                
                console.log('URL Parameters detected:', {
                    initialQuery,
                    initialStatus,
                    initialPerPage,
                    initialUseCache,
                    initialBulk
                });
                
                let needSearch = false;
                
                // Set search input
                if (initialQuery) {
                    const searchInput = document.getElementById('searchInput');
                    if (searchInput) {
                        searchInput.value = initialQuery;
                        needSearch = true;
                        console.log('Set initial query:', initialQuery);
                    }
                }
                
                // Set status filter
                if (initialStatus !== null) {
                    const statusFilter = document.getElementById('statusFilter');
                    if (statusFilter) {
                        statusFilter.value = initialStatus;
                        console.log('Set initial status:', initialStatus);
                        if (initialStatus && initialStatus !== '') {
                            needSearch = true;
                        }
                    }
                }
                
                // Set per page
                if (initialPerPage) {
                    const perPageSelect = document.getElementById('perPageSelect');
                    if (perPageSelect) {
                        perPageSelect.value = initialPerPage;
                        console.log('Set initial per_page:', initialPerPage);
                    }
                }
                
                // Set cache preference  
                if (initialUseCache) {
                    const useCacheCheckbox = document.getElementById('useCache');
                    if (useCacheCheckbox) {
                        useCacheCheckbox.checked = (initialUseCache === 'on' || initialUseCache === '1');
                        console.log('Set initial use_cache:', initialUseCache);
                    }
                }
                
                // Set bulk mode
                if (initialBulk) {
                    const bulkModeCheckbox = document.getElementById('bulkMode');
                    if (bulkModeCheckbox) {
                        bulkModeCheckbox.checked = (initialBulk === 'on' || initialBulk === '1');
                        console.log('Set initial bulk mode:', initialBulk);
                    }
                }
                
                // Perform search if we have URL parameters, hoặc auto-load một số domains
                if (needSearch || initialPerPage || initialUseCache || initialBulk) {
                    console.log('Auto-performing search from URL params...');
                    console.log('Params found:', { needSearch, initialPerPage, initialUseCache, initialBulk });
                    setTimeout(() => {
                        this.performSearch(1);
                    }, 100);
                } else {
                    // Auto-load some initial domains để hiển thị
                    console.log('Auto-loading initial domains...');
                    setTimeout(() => {
                        this.performSearch(1); // Load first page of domains
                    }, 500);
                }
            }

            async performSearch(page = 1, showLoading = true) {
                if (this.isLoading) {
                    console.log('Search already in progress, skipping...');
                    return;
                }

                // Validate form elements exist
                const searchInput = document.getElementById('searchInput');
                const perPageSelect = document.getElementById('perPageSelect');
                const statusFilter = document.getElementById('statusFilter');
                const useCache = document.getElementById('useCache');
                const bulkMode = document.getElementById('bulkMode');
                
                if (!searchInput || !perPageSelect || !statusFilter) {
                    this.showNotification('Lỗi: Form elements không tìm thấy. Vui lòng refresh trang.', 'error');
                    return;
                }

                this.isLoading = true;
                this.currentPage = page;
                
                if (showLoading) {
                    this.showLoading();
                }

                const startTime = performance.now();

                try {
                    const query = searchInput.value.trim();
                    const useCacheValue = useCache ? useCache.checked : true;
                    const bulkModeValue = bulkMode ? bulkMode.checked : false;

                    const params = new URLSearchParams({
                        action: 'search',
                        api: '1',
                        page: page,
                        per_page: perPageSelect.value || '20',
                        q: query,
                        status: statusFilter.value || ''
                    });

                    // Add advanced options
                    if (!useCacheValue || !this.cacheEnabled) {
                        params.append('no_cache', '1');
                    }
                    if (bulkModeValue && query) {
                        params.append('bulk', '1');
                    }

                    console.log('Performing search with params:', params.toString(), 'Cache enabled:', this.cacheEnabled);

                    const response = await fetch(`index.php?${params.toString()}`);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const data = await response.json();

                    const endTime = performance.now();
                    const clientTime = Math.round(endTime - startTime);

                    console.log('Search response received:');
                    console.log('- Status:', response.status);
                    console.log('- Data:', data);
                    console.log('- Client time:', clientTime + 'ms');

                    if (data.success) {
                        console.log('✅ Search successful');
                        this.handleSearchResults(data, clientTime);
                        this.updateSearchHistory(query, data.search);
                    } else {
                        console.log('❌ Search failed:', data.error);
                        this.handleSearchError(data.error || 'Lỗi không xác định', clientTime);
                    }
                } catch (error) {
                    const endTime = performance.now();
                    const clientTime = Math.round(endTime - startTime);
                    console.error('Search error:', error);
                    this.handleSearchError('Lỗi kết nối: ' + error.message, clientTime);
                } finally {
                    this.isLoading = false;
                    if (showLoading) {
                        this.hideLoading();
                    }
                }
            }

            handleSearchResults(response, clientTime = 0) {
                console.log('=== HANDLING SEARCH RESULTS ===');
                console.log('Full response:', response);
                
                // Check if response has expected structure
                if (!response || !response.data) {
                    console.error('Invalid response structure:', response);
                    this.handleSearchError('Invalid API response format', clientTime);
                    return;
                }
                
                this.results = response.data.result || [];
                const searchInfo = response.search || {};
                const resultInfo = response.data.result_info || {};

                // Save search info for showSearchDetails
                this.lastSearchInfo = searchInfo;
                this.lastResultInfo = resultInfo;
                
                console.log('Results:', this.results);
                console.log('Search info:', searchInfo);
                console.log('Result info:', resultInfo);

                this.updateSearchStats(searchInfo, resultInfo, clientTime);
                this.renderResults();
                this.renderPagination(resultInfo);
                
                // Update API stats if available
                if (searchInfo.api_stats) {
                    this.apiStats = searchInfo.api_stats;
                }
                
                console.log('=== SEARCH RESULTS HANDLED ===');
            }

            handleSearchError(error, clientTime = 0) {
                console.log('=== HANDLING SEARCH ERROR ===');
                console.error('Search error:', error);
                console.log('Client time:', clientTime + 'ms');

                // Clear previous results
                this.results = [];
                
                // Hide stats
                const statsEl = document.getElementById('searchStats');
                if (statsEl) {
                    statsEl.style.display = 'none';
                }

                // Show error message
                const container = document.getElementById('resultsContainer');
                if (container) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            <h5>Lỗi tìm kiếm</h5>
                            <p class="text-danger">${error}</p>
                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="domainSearch.performSearch(1)">
                                    <i class="fas fa-redo me-1"></i>Thử lại
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="domainSearch.testJavaScript()">
                                    <i class="fas fa-bug me-1"></i>Debug Test
                                </button>
                            </div>
                        </div>
                    `;
                }

                // Show notification
                this.showNotification('Lỗi tìm kiếm: ' + error, 'error');
                
                console.log('=== SEARCH ERROR HANDLED ===');
            }

            updateSearchStats(searchInfo, resultInfo, clientTime = 0) {
                const statsEl = document.getElementById('searchStats');
                const statsText = document.getElementById('statsText');
                const searchFields = document.getElementById('searchFields');
                const performanceInfo = document.getElementById('performanceInfo');
                const searchMetadata = document.getElementById('searchMetadata');
                const cacheIndicator = document.getElementById('cacheIndicator');
                const bulkSearchSummary = document.getElementById('bulkSearchSummary');

                if (resultInfo.total_count !== undefined) {
                    const query = searchInfo.query || 'tất cả';
                    const total = resultInfo.total_count;
                    const showing = this.results.length;
                    const page = resultInfo.page || 1;
                    const perPage = resultInfo.per_page || 20;

                    // Main stats text
                    statsText.innerHTML = `
                        Tìm thấy <strong class="text-primary">${total}</strong> domain cho 
                        <span class="badge bg-light text-dark">"${query}"</span>
                        <br><small class="text-muted">Hiển thị ${showing} kết quả (trang ${page}/${Math.ceil(total/perPage)})</small>
                    `;

                    // Search metadata
                    let metadataHTML = '';
                    if (resultInfo.page && resultInfo.total_pages) {
                        metadataHTML += `<small class="badge bg-info">Trang ${resultInfo.page}/${resultInfo.total_pages}</small> `;
                    }
                    if (searchInfo.client_filtered && searchInfo.client_filtered > 0) {
                        metadataHTML += `<small class="badge bg-warning">${searchInfo.client_filtered} đã lọc client-side</small> `;
                    }
                    searchMetadata.innerHTML = metadataHTML;

                    // Performance info
                    const serverTime = searchInfo.execution_time_ms || 0;
                    const apiRequests = searchInfo.api_stats?.total_requests || 0;
                    const cacheHits = searchInfo.api_stats?.cache_hits || 0;
                    
                    performanceInfo.innerHTML = `
                        <div class="d-flex flex-column text-center">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                Server: <span class="fw-bold">${serverTime}ms</span>
                            </small>
                            ${clientTime > 0 ? `
                            <small class="text-muted">
                                <i class="fas fa-desktop me-1"></i>
                                Client: <span class="fw-bold">${clientTime}ms</span>
                            </small>
                            ` : ''}
                            ${apiRequests > 0 ? `
                            <small class="text-muted">
                                <i class="fas fa-server me-1"></i>
                                ${apiRequests} API req
                            </small>
                            ` : ''}
                        </div>
                    `;

                    // Cache indicator với enhanced styling
                    if (searchInfo.cache_used) {
                        const cacheHit = searchInfo.cache_hit ? 'HIT' : 'MISS';
                        const cacheSource = searchInfo.cache_source || 'unknown';
                        const iconClass = searchInfo.cache_hit ? 'cache-indicator-success' : 'cache-indicator-warning';
                        
                        cacheIndicator.innerHTML = `
                            <div class="text-center">
                                <i class="fas fa-database ${iconClass} me-1"></i>
                                <small class="badge ${searchInfo.cache_hit ? 'bg-success' : 'bg-warning'}">
                                    Cache ${cacheHit}
                                </small>
                                <br><small class="text-muted">${cacheSource}</small>
                            </div>
                        `;
                    } else {
                        cacheIndicator.innerHTML = `
                            <div class="text-center">
                                <i class="fas fa-database cache-indicator-secondary me-1"></i>
                                <small class="badge bg-secondary">No Cache</small>
                                <br><small class="text-muted">Fresh API</small>
                            </div>
                        `;
                    }

                    // Search fields
                    searchFields.textContent = searchInfo.search_fields ? 
                        searchInfo.search_fields.join(', ') : 'name, status, plan';

                    // Bulk search summary
                    if (searchInfo.bulk_mode && resultInfo.is_bulk_search) {
                        this.showBulkSearchSummary(resultInfo);
                    } else {
                        bulkSearchSummary.style.display = 'none';
                    }

                    statsEl.style.display = 'block';
                } else {
                    statsEl.style.display = 'none';
                }

                // Show suggestions if no results
                if (this.results.length === 0 && searchInfo.suggestions && searchInfo.suggestions.length > 0) {
                    this.showSearchSuggestions(searchInfo.suggestions);
                }
            }

            showSearchDetails(domain) {
                // Implementation continues with modal display logic
                const modal = new bootstrap.Modal(document.getElementById('searchDetailsModal'));
                // ... rest of implementation
                modal.show();
            }

            renderResults() {
                console.log('=== RENDERING RESULTS ===');
                const container = document.getElementById('resultsContainer');
                console.log('Results container:', container);
                console.log('Results to render:', this.results);
                console.log('Results count:', this.results ? this.results.length : 0);
                console.log('Current view:', this.currentView);
                
                if (!container) {
                    console.error('Results container not found!');
                    return;
                }
                
                if (!this.results || this.results.length === 0) {
                    console.log('No results to display, showing enhanced empty state');
                    container.innerHTML = `
                        <div class="empty-state-enhanced">
                            <div class="empty-state-content">
                                <div class="empty-icon mb-4">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h5 class="mb-3">Không tìm thấy domain</h5>
                                <p class="text-muted mb-4">
                                    Thử thay đổi từ khóa tìm kiếm hoặc bộ lọc.
                                    <br><small>Gợi ý: Sử dụng * để hiển thị tất cả domains</small>
                                </p>
                                <button class="btn btn-primary" onclick="domainSearch.performSearch(1)">
                                    <i class="fas fa-search me-1"></i>Tải danh sách domains
                                </button>
                            </div>
                        </div>
                    `;
                    return;
                }

                // Render based on current view
                if (this.currentView === 'card') {
                    this.renderCardView();
                } else {
                    this.renderTableView();
                }
                
                console.log('=== RESULTS RENDERING COMPLETE ===');
            }

            renderTableView() {
                const container = document.getElementById('resultsContainer');
                
                const tableRows = this.results.map((domain, index) => {
                    // Enhanced domain item với relevance và context info
                    const relevanceScore = domain._relevance_score || 0;
                    const searchTerm = domain._search_term;
                    const hasContext = searchTerm && searchTerm !== domain.name;
                    
                    return `
                    <tr data-domain-id="${domain.id}" data-relevance="${relevanceScore}">
                        ${this.bulkMode ? `
                        <td class="text-center bulk-select-cell">
                            <div class="form-check">
                                <input class="form-check-input domain-checkbox" type="checkbox" 
                                       id="domain-${domain.id}" 
                                       value="${domain.id}" 
                                       onchange="domainSearch.updateBulkSelection()">
                                <label class="form-check-label" for="domain-${domain.id}"></label>
                            </div>
                        </td>` : ''}
                        <td>
                            <div class="domain-name">${domain.name}</div>
                            <div class="domain-id">ID: ${domain.id.substring(0, 12)}...</div>
                        </td>
                        <td>
                            <span class="status-badge status-${domain.status}">
                                ${this.formatStatus(domain.status)}
                            </span>
                        </td>
                        <td>
                            <span class="plan-badge">
                                ${domain.plan ? domain.plan.name : 'Unknown'}
                            </span>
                        </td>
                        <td>
                            ${this.formatHTTPS(domain)}
                        </td>
                        <td>
                            <small>${this.formatDate(domain.created_on)}</small>
                        </td>
                        <td>
                            <div class="relevance-indicator">
                                ${relevanceScore > 0 ? 
                                    `<span class="relevance-score">${Math.round(relevanceScore)}%</span>
                                    ${relevanceScore >= 90 ? '<i class="fas fa-star text-warning"></i>' : 
                                      relevanceScore >= 70 ? '<i class="fas fa-star-half-alt text-warning"></i>' : 
                                      '<i class="far fa-star text-muted"></i>'}` 
                                    : '<span class="text-muted">-</span>'}
                            </div>
                            ${hasContext ? 
                                `<div class="search-context mt-1">
                                    <small class="text-info"><i class="fas fa-search"></i> ${searchTerm}</small>
                                </div>` 
                                : ''}
                        </td>
                        <td class="actions-cell">
                            <button class="action-btn btn btn-outline-primary btn-sm" onclick="window.open('?action=dashboard&zone=${domain.id}', '_blank')" title="Mở Dashboard">
                                <i class="fas fa-cog"></i>
                            </button>
                            <button class="action-btn btn btn-outline-info btn-sm" onclick="domainSearch.showDomainDetails('${domain.id}')" title="Xem chi tiết">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        </td>
                    </tr>
                    `;
                }).join('');

                const tableHTML = `
                    <div class="table-responsive">
                        <table class="results-table">
                            <thead>
                                <tr>
                                    ${this.bulkMode ? '<th style="width: 5%;" class="text-center"><i class="fas fa-check-square"></i></th>' : ''}
                                    <th style="width: ${this.bulkMode ? '20%' : '22%'};"><i class="fas fa-globe me-2"></i>Domain</th>
                                    <th style="width: ${this.bulkMode ? '9%' : '10%'};"><i class="fas fa-circle me-2"></i>Trạng thái</th>
                                    <th style="width: ${this.bulkMode ? '11%' : '12%'};"><i class="fas fa-layer-group me-2"></i>Plan</th>
                                    <th style="width: ${this.bulkMode ? '11%' : '12%'};"><i class="fas fa-lock me-2"></i>Always HTTPS</th>
                                    <th style="width: ${this.bulkMode ? '11%' : '12%'};"><i class="fas fa-calendar me-2"></i>Tạo lúc</th>
                                    <th style="width: ${this.bulkMode ? '16%' : '17%'};"><i class="fas fa-star me-2"></i>Relevance</th>
                                    <th style="width: 15%;"><i class="fas fa-tools me-2"></i>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tableRows}
                            </tbody>
                        </table>
                    </div>
                `;

                console.log('Generated table HTML length:', tableHTML.length);
                console.log('About to set container innerHTML...');
                container.innerHTML = tableHTML;
                console.log('Table rendered successfully! Container now has', container.children.length, 'children');
            }

            renderPagination(resultInfo) {
                const wrapper = document.getElementById('paginationWrapper');
                const pagination = document.getElementById('pagination');

                if (!resultInfo || !resultInfo.total_pages || resultInfo.total_pages <= 1) {
                    wrapper.style.display = 'none';
                    return;
                }

                const currentPage = resultInfo.page || 1;
                const totalPages = resultInfo.total_pages;
                const maxVisiblePages = 5;

                let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
                let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

                if (endPage - startPage < maxVisiblePages - 1) {
                    startPage = Math.max(1, endPage - maxVisiblePages + 1);
                }

                let html = '';

                // Previous button
                html += `
                    <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="domainSearch.performSearch(${currentPage - 1}); return false;">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                `;

                // First page
                if (startPage > 2) {
                    html += `
                        <li class="page-item">
                            <a class="page-link" href="#" onclick="domainSearch.performSearch(1); return false;">1</a>
                        </li>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    `;
                }

                // Page numbers
                for (let i = startPage; i <= endPage; i++) {
                    html += `
                        <li class="page-item ${i === currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="domainSearch.performSearch(${i}); return false;">${i}</a>
                        </li>
                    `;
                }

                // Last page
                if (endPage < totalPages - 1) {
                    html += `
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <li class="page-item">
                            <a class="page-link" href="#" onclick="domainSearch.performSearch(${totalPages}); return false;">${totalPages}</a>
                        </li>
                    `;
                }

                // Next button
                html += `
                    <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="domainSearch.performSearch(${currentPage + 1}); return false;">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                `;

                pagination.innerHTML = html;
                wrapper.style.display = 'block';
            }

            formatStatus(status) {
                const statusMap = {
                    'active': 'Hoạt động',
                    'pending': 'Đang chờ',
                    'initializing': 'Đang khởi tạo',
                    'moved': 'Đã chuyển',
                    'deleted': 'Đã xóa',
                    'deactivated': 'Đã tắt'
                };
                return statusMap[status] || status;
            }

            formatDate(dateString) {
                try {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('vi-VN');
                } catch {
                    return dateString;
                }
            }

            formatHTTPS(domain) {
                // Check if domain has been checked for HTTPS
                const httpsChecked = domain.https_checked;
                
                // Check multiple possible properties for HTTPS status
                const httpsEnabled = domain.ssl?.always_use_https || 
                               domain.settings?.always_use_https ||
                               domain.always_use_https ||
                               domain.ssl_settings?.always_use_https;
                
                if (httpsEnabled === true || httpsEnabled === 'on') {
                    return `<span class="https-enabled"><i class="fas fa-lock text-success me-1"></i>Bật${httpsChecked ? ' ✓' : ''}</span>`;
                } else if (httpsEnabled === false || httpsEnabled === 'off') {
                    return `<span class="https-disabled"><i class="fas fa-unlock text-danger me-1"></i>Tắt${httpsChecked ? ' ✓' : ''}</span>`;
                } else {
                    const statusIcon = httpsChecked ? 
                        '<i class="fas fa-question-circle text-warning me-1"></i>' : 
                        '<i class="fas fa-question text-muted me-1"></i>';
                    const statusText = httpsChecked ? 'Chưa rõ ✓' : 'Chưa kiểm tra';
                    return `<span class="https-unknown">${statusIcon}${statusText}</span>`;
                }
            }

            clearFilters() {
                document.getElementById('searchInput').value = '';
                document.getElementById('statusFilter').value = '';
                document.getElementById('perPageSelect').value = '20';
                this.performSearch(1);
            }

            refreshResults() {
                this.performSearch(this.currentPage);
            }

            exportResults() {
                if (!this.results || this.results.length === 0) {
                    alert('Không có dữ liệu để xuất');
                    return;
                }

                // Create CSV content
                const headers = ['Tên Domain', 'Trạng thái', 'Gói dịch vụ', 'Ngày tạo', 'ID'];
                const csvContent = [
                    headers.join(','),
                    ...this.results.map(domain => [
                        domain.name,
                        domain.status,
                        domain.plan ? domain.plan.name : 'Unknown',
                        domain.created_on,
                        domain.id
                    ].join(','))
                ].join('\n');

                // Download file
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', `cloudflare-domains-${new Date().toISOString().split('T')[0]}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            showLoading() {
                const loadingOverlay = document.getElementById('loadingOverlay');
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'flex';
                } else {
                    console.warn('Loading overlay element not found');
                }
            }

            hideLoading() {
                const loadingOverlay = document.getElementById('loadingOverlay');
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'none';
                } else {
                    console.warn('Loading overlay element not found');
                }
            }

            // === NEW ENHANCED METHODS ===

            toggleAdvancedOptions() {
                const panel = document.getElementById('advancedOptionsPanel');
                const btn = document.getElementById('advancedToggle');
                
                if (!panel || !btn) {
                    console.error('Advanced options elements not found');
                    return;
                }
                
                const isVisible = panel.style.display !== 'none';
                panel.style.display = isVisible ? 'none' : 'block';
                
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = isVisible ? 'fas fa-cog me-1' : 'fas fa-times me-1';
                }
                btn.innerHTML = (icon ? icon.outerHTML : '<i class="fas fa-cog me-1"></i>') + 
                              (isVisible ? 'Tùy chọn nâng cao' : 'Ẩn tùy chọn');
            }

            async showAPIStats() {
                try {
                    const response = await fetch('index.php?action=api-stats&api=1&detailed=true');
                    const data = await response.json();

                    if (data.success) {
                        this.displayAPIStatsModal(data);
                    } else {
                        this.showNotification('Lỗi khi tải API stats: ' + data.error, 'error');
                    }
                } catch (error) {
                    this.showNotification('Lỗi kết nối: ' + error.message, 'error');
                }
            }

            displayAPIStatsModal(data) {
                const stats = data.api_stats;
                const detailed = data.detailed_metrics;

                const modalHTML = `
                    <div class="modal fade" id="apiStatsModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-chart-line me-2"></i>API Performance Statistics
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title">API Requests</h6>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Total Requests:</span>
                                                        <strong>${stats.total_requests}</strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Cache Hits:</span>
                                                        <strong class="text-success">${stats.cache_hits}</strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Cache Hit Rate:</span>
                                                        <strong class="text-primary">${stats.cache_hit_rate}%</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title">Cache Storage</h6>
                                                    ${detailed ? `
                                                    <div class="d-flex justify-content-between">
                                                        <span>Storage Used:</span>
                                                        <strong>${detailed.cache_storage.storage_used_mb} MB</strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Storage Limit:</span>
                                                        <strong>${detailed.cache_storage.storage_limit_mb} MB</strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Utilization:</span>
                                                        <strong>${detailed.cache_storage.storage_utilization_percent}%</strong>
                                                    </div>
                                                    ` : '<p class="text-muted">Detailed stats not available</p>'}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    ${detailed && detailed.recommendations.length > 0 ? `
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <h6><i class="fas fa-lightbulb me-2"></i>Recommendations:</h6>
                                                <ul class="mb-0">
                                                    ${detailed.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    ` : ''}
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-warning" onclick="domainSearch.clearCache()">
                                        Clear Cache
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="domainSearch.warmCache()">
                                        Warm Cache
                                    </button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal if any
                const existingModal = document.getElementById('apiStatsModal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Add modal to DOM and show
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                const modal = new bootstrap.Modal(document.getElementById('apiStatsModal'));
                modal.show();
            }

            async showBackgroundStatus() {
                try {
                    const response = await fetch('index.php?action=background&api=1&operation=status');
                    const data = await response.json();

                    if (data.success) {
                        this.displayBackgroundStatusModal(data.status);
                    } else {
                        this.showNotification('Lỗi khi tải background status: ' + data.error, 'error');
                    }
                } catch (error) {
                    this.showNotification('Lỗi kết nối: ' + error.message, 'error');
                }
            }

            displayBackgroundStatusModal(status) {
                const modalHTML = `
                    <div class="modal fade" id="backgroundStatusModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-sync-alt me-2"></i>Background Loader Status
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title">Status</h6>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Enabled:</span>
                                                        <strong class="${status.enabled ? 'text-success' : 'text-danger'}">
                                                            ${status.enabled ? 'Yes' : 'No'}
                                                        </strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Currently Running:</span>
                                                        <strong class="${status.is_running ? 'text-warning' : 'text-muted'}">
                                                            ${status.is_running ? 'Yes' : 'No'}
                                                        </strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Last Run:</span>
                                                        <strong>${status.last_run_formatted}</strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Next Run:</span>
                                                        <strong>${status.next_run_formatted}</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title">Tasks</h6>
                                                    ${Object.entries(status.config.tasks).map(([taskName, taskConfig]) => `
                                                        <div class="d-flex justify-content-between">
                                                            <span>${taskName}:</span>
                                                            <span class="badge ${taskConfig.enabled ? 'bg-success' : 'bg-secondary'}">
                                                                ${taskConfig.enabled ? 'Enabled' : 'Disabled'}
                                                            </span>
                                                        </div>
                                                    `).join('')}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-success" onclick="domainSearch.runBackgroundLoader()">
                                        <i class="fas fa-play me-1"></i>Run Now
                                    </button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal if any
                const existingModal = document.getElementById('backgroundStatusModal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Add modal to DOM and show
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                const modal = new bootstrap.Modal(document.getElementById('backgroundStatusModal'));
                modal.show();
            }

            async clearCache() {
                if (!confirm('Bạn có chắc muốn xóa toàn bộ cache? Điều này có thể làm chậm các request tiếp theo.')) {
                    return;
                }

                try {
                    const response = await fetch('index.php?action=cache&api=1&operation=clear', {
                        method: 'POST'
                    });
                    const data = await response.json();

                    if (data.success) {
                        this.showNotification(`Đã xóa ${data.cleared_items} cache items`, 'success');
                    } else {
                        this.showNotification('Lỗi khi xóa cache: ' + data.error, 'error');
                    }
                } catch (error) {
                    this.showNotification('Lỗi kết nối: ' + error.message, 'error');
                }
            }

            async warmCache() {
                this.showNotification('Đang warm cache... Vui lòng đợi', 'info');

                try {
                    const response = await fetch('index.php?action=cache&api=1&operation=warm', {
                        method: 'POST'
                    });
                    const data = await response.json();

                    if (data.success) {
                        const warmed = Object.values(data.warmed_data).reduce((sum, val) => {
                            return sum + (typeof val === 'number' ? val : 0);
                        }, 0);
                        this.showNotification(`Cache warming hoàn thành! Đã warm ${warmed} items`, 'success');
                    } else {
                        this.showNotification('Lỗi khi warm cache: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (error) {
                    this.showNotification('Lỗi kết nối: ' + error.message, 'error');
                }
            }

            async runBackgroundLoader() {
                this.showNotification('Đang chạy background loader...', 'info');

                try {
                    const response = await fetch('index.php?action=background&api=1&operation=run&force=true', {
                        method: 'POST'
                    });
                    const data = await response.json();

                    if (data.success) {
                        const totalTasks = Object.keys(data.result.results || {}).length;
                        this.showNotification(`Background loader hoàn thành! Đã xử lý ${totalTasks} tasks`, 'success');
                    } else {
                        this.showNotification('Lỗi background loader: ' + data.error, 'error');
                    }
                } catch (error) {
                    this.showNotification('Lỗi kết nối: ' + error.message, 'error');
                }
            }

            clearFilters() {
                const searchInput = document.getElementById('searchInput');
                const statusFilter = document.getElementById('statusFilter');
                const perPageSelect = document.getElementById('perPageSelect');
                const useCache = document.getElementById('useCache');
                const bulkMode = document.getElementById('bulkMode');
                
                if (searchInput) searchInput.value = '';
                if (statusFilter) statusFilter.value = '';
                if (perPageSelect) perPageSelect.value = '20';
                
                // Reset advanced options
                if (useCache) useCache.checked = true;
                if (bulkMode) bulkMode.checked = false;

                this.currentQuery = '';
                this.currentFilters = {};
                
                // Show initial empty state
                const resultsContainer = document.getElementById('resultsContainer');
                if (resultsContainer) {
                    resultsContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h5>Bắt đầu tìm kiếm</h5>
                            <p>Nhập tên domain hoặc sử dụng bộ lọc để tìm kiếm domain của bạn</p>
                        </div>
                    `;
                }
                
                const searchStats = document.getElementById('searchStats');
                const paginationWrapper = document.getElementById('paginationWrapper');
                if (searchStats) searchStats.style.display = 'none';
                if (paginationWrapper) paginationWrapper.style.display = 'none';
            }

            updateSearchHistory(query, searchInfo) {
                if (query.trim()) {
                    this.searchHistory.unshift({
                        query: query,
                        timestamp: Date.now(),
                        results_count: searchInfo.total_results || 0,
                        execution_time: searchInfo.execution_time_ms || 0
                    });
                    
                    // Keep only last 10 searches
                    this.searchHistory = this.searchHistory.slice(0, 10);
                }
            }

            showSearchSuggestions(suggestions) {
                if (!suggestions || suggestions.length === 0) return;

                const suggestionsHTML = `
                    <div class="alert alert-info">
                        <h6><i class="fas fa-lightbulb me-2"></i>Gợi ý tìm kiếm:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            ${suggestions.map(suggestion => `
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="domainSearch.searchSuggestion('${suggestion}')">
                                    ${suggestion}
                                </button>
                            `).join('')}
                        </div>
                    </div>
                `;

                const container = document.getElementById('resultsContainer');
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h5>Không tìm thấy kết quả</h5>
                        <p>Thử với các gợi ý bên dưới:</p>
                    </div>
                    ${suggestionsHTML}
                `;
            }

            searchSuggestion(suggestion) {
                document.getElementById('searchInput').value = suggestion;
                this.performSearch(1);
            }

            async checkBackgroundStatus() {
                try {
                    console.log('Checking background status...');
                    const response = await fetch('index.php?action=background&api=1&operation=status');
                    
                    if (!response.ok) {
                        console.log('Background status check failed - HTTP ' + response.status);
                        return;
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        console.log('Background status check failed - not JSON response');
                        return;
                    }
                    
                    const data = await response.json();

                    if (data.success) {
                        this.backgroundStatus = data.status;
                        this.updateBackgroundStatusIndicator();
                        console.log('Background status updated:', data.status);
                    } else {
                        console.log('Background status check failed:', data.error);
                    }
                } catch (error) {
                    console.log('Background status check failed:', error.message);
                    // Don't show error to user as this is not critical
                }
            }

            updateBackgroundStatusIndicator() {
                const btn = document.getElementById('backgroundStatusBtn');
                const icon = btn.querySelector('i');
                
                if (this.backgroundStatus.is_running) {
                    icon.className = 'fas fa-sync-alt fa-spin me-1';
                    btn.classList.remove('btn-outline-warning');
                    btn.classList.add('btn-warning');
                } else if (this.backgroundStatus.enabled) {
                    icon.className = 'fas fa-check me-1';
                    btn.classList.remove('btn-outline-warning');
                    btn.classList.add('btn-outline-success');
                } else {
                    icon.className = 'fas fa-pause me-1';
                    btn.classList.remove('btn-outline-success');
                    btn.classList.add('btn-outline-secondary');
                }
            }

            showNotification(message, type = 'info') {
                const alertClass = {
                    'success': 'alert-success',
                    'error': 'alert-danger',
                    'warning': 'alert-warning',
                    'info': 'alert-info'
                }[type] || 'alert-info';

                const notification = document.createElement('div');
                notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
                notification.style.cssText = 'top: 20px; right: 20px; z-index: 1050; max-width: 400px;';
                notification.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;

                document.body.appendChild(notification);

                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 5000);
            }

            // Enhanced method để show domain details
            async showDomainDetails(domainId) {
                this.showNotification('Đang tải thông tin domain...', 'info');

                try {
                    const response = await fetch(`index.php?action=zones&api=1&zone_id=${domainId}&include_dns=true`);
                    const data = await response.json();

                    if (data.success) {
                        this.displayDomainDetailsModal(data.data);
                    } else {
                        this.showNotification('Lỗi khi tải domain details: ' + data.error, 'error');
                    }
                } catch (error) {
                    this.showNotification('Lỗi kết nối: ' + error.message, 'error');
                }
            }

            displayDomainDetailsModal(domainData) {
                const zone = domainData.zone || domainData;
                const dnsRecords = domainData.dns_records || [];
                
                const modalHTML = `
                    <div class="modal fade" id="domainDetailsModal" tabindex="-1">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-globe me-2"></i>${zone.name} - Domain Details
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <!-- Domain Info -->
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <i class="fas fa-info-circle me-2"></i>Domain Information
                                                    </h6>
                                                    <table class="table table-sm">
                                                        <tr>
                                                            <td><strong>Name:</strong></td>
                                                            <td>${zone.name}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Status:</strong></td>
                                                            <td><span class="status-badge status-${zone.status}">${zone.status}</span></td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Plan:</strong></td>
                                                            <td>${zone.plan?.name || 'Unknown'}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Created:</strong></td>
                                                            <td>${this.formatDate(zone.created_on)}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Modified:</strong></td>
                                                            <td>${this.formatDate(zone.modified_on)}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Zone ID:</strong></td>
                                                            <td><code>${zone.id}</code></td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Name Servers -->
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <i class="fas fa-server me-2"></i>Name Servers
                                                    </h6>
                                                    ${zone.name_servers && zone.name_servers.length > 0 ? 
                                                        `<ul class="list-unstyled">
                                                            ${zone.name_servers.map(ns => `<li><code>${ns}</code></li>`).join('')}
                                                        </ul>` : 
                                                        '<p class="text-muted">No name servers available</p>'
                                                    }
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- DNS Records -->
                                    ${dnsRecords.length > 0 ? `
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <i class="fas fa-list me-2"></i>DNS Records (${dnsRecords.length})
                                                    </h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th>Type</th>
                                                                    <th>Name</th>
                                                                    <th>Content</th>
                                                                    <th>TTL</th>
                                                                    <th>Priority</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                ${dnsRecords.slice(0, 20).map(record => `
                                                                    <tr>
                                                                        <td><span class="badge bg-primary">${record.type}</span></td>
                                                                        <td><code>${record.name}</code></td>
                                                                        <td><code class="text-break">${record.content}</code></td>
                                                                        <td>${record.ttl}</td>
                                                                        <td>${record.priority || '-'}</td>
                                                                    </tr>
                                                                `).join('')}
                                                                ${dnsRecords.length > 20 ? `
                                                                    <tr>
                                                                        <td colspan="5" class="text-center text-muted">
                                                                            ... và ${dnsRecords.length - 20} records khác
                                                                        </td>
                                                                    </tr>
                                                                ` : ''}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    ` : ''}
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-info" onclick="window.open('?action=dashboard&zone=${zone.id}', '_blank')">
                                        <i class="fas fa-external-link-alt me-1"></i>Open Dashboard
                                    </button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal if any
                const existingModal = document.getElementById('domainDetailsModal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Add modal to DOM and show
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                const modal = new bootstrap.Modal(document.getElementById('domainDetailsModal'));
                modal.show();
            }

            // Debug Methods
            testJavaScript() {
                alert('JavaScript is working! Debug test successful.');
                console.log('Debug test - JavaScript working');
                
                // Test elements
                const elements = ['searchInput', 'statusFilter', 'searchForm'];
                elements.forEach(id => {
                    const el = document.getElementById(id);
                    console.log(`Element ${id}:`, el ? 'Found' : 'NOT FOUND');
                });
                
                this.showNotification('JavaScript test completed - check console for details', 'info');
            }

            // === DASHBOARD INTEGRATION METHODS ===

            toggleDashboardActions() {
                const panel = document.getElementById('dashboardActionsPanel');
                const btn = document.getElementById('dashboardActionsToggle');
                
                if (!panel || !btn) {
                    console.error('Dashboard actions elements not found');
                    return;
                }
                
                const isVisible = panel.style.display !== 'none';
                panel.style.display = isVisible ? 'none' : 'block';
                
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = isVisible ? 'fas fa-tools me-1' : 'fas fa-times me-1';
                }
                btn.innerHTML = (icon ? icon.outerHTML : '<i class="fas fa-tools me-1"></i>') + 
                              (isVisible ? 'Dashboard Actions' : 'Đóng Dashboard');
            }

            async activateAllHTTPS() {
                if (!this.results || this.results.length === 0) {
                    this.showNotification('Không có domain nào để kích hoạt HTTPS', 'warning');
                    return;
                }

                const confirmMessage = `Kích hoạt Always Use HTTPS cho ${this.results.length} domain?\n\n⚠️ Lưu ý: Quá trình này có thể mất vài phút!`;
                
                if (!confirm(confirmMessage)) {
                    return;
                }

                const btn = document.getElementById('activateAllHttpsBtn');
                const originalHTML = btn.innerHTML;
                
                try {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
                    
                    this.showDashboardProgress(0, 'Bắt đầu kích hoạt HTTPS...');
                    
                    let successCount = 0;
                    let failedCount = 0;
                    
                    for (let i = 0; i < this.results.length; i++) {
                        const domain = this.results[i];
                        const progress = ((i + 1) / this.results.length) * 100;
                        
                        this.updateDashboardProgress(progress, `Đang xử lý: ${domain.name} (${i + 1}/${this.results.length})`);
                        
                        try {
                            const response = await fetch(`index.php?action=ssl&api=1&zone_id=${domain.id}&setting=always_use_https&value=on`, {
                                method: 'POST'
                            });
                            
                            const data = await response.json();
                            
                            if (data.success) {
                                successCount++;
                            } else {
                                failedCount++;
                            }
                        } catch (error) {
                            failedCount++;
                            console.error(`Failed to activate HTTPS for ${domain.name}:`, error);
                        }
                        
                        // Small delay between requests
                        if (i < this.results.length - 1) {
                            await new Promise(resolve => setTimeout(resolve, 1000));
                        }
                    }
                    
                    this.hideDashboardProgress();
                    this.showNotification(`HTTPS kích hoạt hoàn tất! Thành công: ${successCount}, Thất bại: ${failedCount}`, 'success');
                    
                } catch (error) {
                    this.hideDashboardProgress();
                    this.showNotification('Lỗi khi kích hoạt HTTPS: ' + error.message, 'error');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            }

            async checkAlwaysHTTPS() {
                if (!this.results || this.results.length === 0) {
                    this.showNotification('Không có domain nào để kiểm tra Always HTTPS', 'warning');
                    return;
                }

                const btn = document.getElementById('checkAlwaysHttpsBtn');
                const originalHTML = btn.innerHTML;
                
                try {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang kiểm tra...';
                    
                    this.showDashboardProgress(0, 'Bắt đầu kiểm tra Always HTTPS status...');
                    
                    // Collect all zone IDs
                    const zoneIds = this.results.map(domain => domain.id);
                    
                    try {
                        // Use the multiple domains check API
                        const response = await fetch(`index.php?action=https-check&type=multiple&zone_ids=${encodeURIComponent(JSON.stringify(zoneIds))}`);
                        const data = await response.json();
                        
                        if (data.success) {
                            // Update domain data with HTTPS info
                            if (data.data && Array.isArray(data.data)) {
                                data.data.forEach(httpsInfo => {
                                    const domain = this.results.find(d => d.id === httpsInfo.zone_id);
                                    if (domain) {
                                        // Update domain with HTTPS status
                                        domain.always_use_https = httpsInfo.always_use_https;
                                        domain.ssl = domain.ssl || {};
                                        domain.ssl.always_use_https = httpsInfo.always_use_https;
                                        domain.https_status = httpsInfo.status;
                                        domain.https_checked = true;
                                    }
                                });
                            }
                            
                            this.hideDashboardProgress();
                            this.renderResults(); // Re-render with updated HTTPS info
                            this.showNotification(`Always HTTPS status đã được kiểm tra cho ${this.results.length} domain`, 'success');
                        } else {
                            throw new Error(data.error || 'Lỗi khi kiểm tra Always HTTPS');
                        }
                    } catch (error) {
                        console.error('API Error:', error);
                        
                        // Fallback: Check each domain individually
                        let checked = 0;
                        let successCount = 0;
                        
                        for (let i = 0; i < this.results.length; i++) {
                            const domain = this.results[i];
                            const progress = ((i + 1) / this.results.length) * 100;
                            
                            this.updateDashboardProgress(progress, `Đang kiểm tra: ${domain.name} (${i + 1}/${this.results.length})`);
                            
                            try {
                                const response = await fetch(`index.php?action=https-check&type=single&zone_id=${domain.id}`);
                                const data = await response.json();
                                
                                if (data.success && data.data) {
                                    // Update domain with HTTPS info
                                    domain.always_use_https = data.data.always_use_https;
                                    domain.ssl = domain.ssl || {};
                                    domain.ssl.always_use_https = data.data.always_use_https;
                                    domain.https_status = data.data.status;
                                    domain.https_checked = true;
                                    successCount++;
                                }
                                checked++;
                            } catch (error) {
                                console.error(`Failed to check HTTPS for ${domain.name}:`, error);
                                checked++;
                            }
                            
                            // Small delay between requests
                            if (i < this.results.length - 1) {
                                await new Promise(resolve => setTimeout(resolve, 200));
                            }
                        }
                        
                        this.hideDashboardProgress();
                        this.renderResults(); // Re-render with updated HTTPS info
                        this.showNotification(`Always HTTPS đã được kiểm tra: ${successCount}/${checked} thành công`, successCount > 0 ? 'success' : 'warning');
                    }
                    
                } catch (error) {
                    this.hideDashboardProgress();
                    this.showNotification('Lỗi khi kiểm tra Always HTTPS: ' + error.message, 'error');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            }

            async recheckAllSSL() {
                if (!this.results || this.results.length === 0) {
                    this.showNotification('Không có domain nào để kiểm tra SSL', 'warning');
                    return;
                }

                const btn = document.getElementById('recheckSSLBtn');
                const stopBtn = document.getElementById('stopSSLCheckBtn');
                const originalHTML = btn.innerHTML;
                
                try {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang kiểm tra...';
                    stopBtn.classList.remove('d-none');
                    
                    this.showDashboardProgress(0, 'Bắt đầu kiểm tra SSL status...');
                    
                    for (let i = 0; i < this.results.length; i++) {
                        const domain = this.results[i];
                        const progress = ((i + 1) / this.results.length) * 100;
                        
                        this.updateDashboardProgress(progress, `Đang kiểm tra: ${domain.name} (${i + 1}/${this.results.length})`);
                        
                        try {
                            const response = await fetch(`index.php?action=zones&api=1&zone_id=${domain.id}&include_ssl=true`);
                            const data = await response.json();
                            
                            if (data.success) {
                                // Update domain data with latest SSL info
                                const updatedDomain = data.data.zone || data.data;
                                domain.ssl = updatedDomain.ssl || domain.ssl;
                                domain.settings = updatedDomain.settings || domain.settings;
                            }
                        } catch (error) {
                            console.error(`Failed to check SSL for ${domain.name}:`, error);
                        }
                        
                        // Small delay between requests
                        if (i < this.results.length - 1) {
                            await new Promise(resolve => setTimeout(resolve, 500));
                        }
                    }
                    
                    this.hideDashboardProgress();
                    this.renderResults(); // Re-render with updated SSL info
                    this.showNotification(`SSL status đã được cập nhật cho ${this.results.length} domain`, 'success');
                    
                } catch (error) {
                    this.hideDashboardProgress();
                    this.showNotification('Lỗi khi kiểm tra SSL: ' + error.message, 'error');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                    stopBtn.classList.add('d-none');
                }
            }

            toggleBulkMode() {
                this.bulkMode = !this.bulkMode;
                const btn = document.getElementById('toggleBulkModeBtn');
                const bulkActionsBtn = document.getElementById('bulkActionsBtn');
                
                if (this.bulkMode) {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-success');
                    btn.innerHTML = '<i class="fas fa-check me-2"></i>Chế độ chọn nhiều: BẬT';
                    bulkActionsBtn.classList.remove('disabled');
                } else {
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-primary');
                    btn.innerHTML = '<i class="fas fa-check-double me-2"></i>Bật/Tắt chế độ chọn nhiều';
                    bulkActionsBtn.classList.add('disabled');
                    this.selectedDomains = [];
                    this.updateBulkSelection();
                }
                
                // Re-render results with/without checkboxes
                this.renderResults();
            }

            updateBulkSelection() {
                const checkboxes = document.querySelectorAll('.domain-checkbox:checked');
                this.selectedDomains = Array.from(checkboxes).map(cb => cb.value);
                
                const selectedCount = document.getElementById('selectedCount');
                const bulkActionsBtn = document.getElementById('bulkActionsBtn');
                
                if (selectedCount) {
                    selectedCount.textContent = this.selectedDomains.length;
                }
                
                if (bulkActionsBtn) {
                    bulkActionsBtn.classList.toggle('disabled', this.selectedDomains.length === 0);
                }
            }

            async showBulkActions() {
                if (!this.selectedDomains || this.selectedDomains.length === 0) {
                    this.showNotification('Vui lòng chọn ít nhất một domain', 'warning');
                    return;
                }

                const modalHTML = `
                    <div class="modal fade" id="bulkActionsModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-cogs me-2"></i>Bulk Actions - ${this.selectedDomains.length} domains
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-success" onclick="domainSearch.bulkActivateHTTPS()">
                                            <i class="fas fa-shield-alt me-2"></i>Kích hoạt HTTPS cho các domain đã chọn
                                        </button>
                                        <button type="button" class="btn btn-info" onclick="domainSearch.bulkCheckSSL()">
                                            <i class="fas fa-sync-alt me-2"></i>Kiểm tra SSL cho các domain đã chọn
                                        </button>
                                        <button type="button" class="btn btn-warning" onclick="domainSearch.bulkExport()">
                                            <i class="fas fa-download me-2"></i>Export danh sách đã chọn
                                        </button>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal if any
                const existingModal = document.getElementById('bulkActionsModal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Add modal to DOM and show
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                const modal = new bootstrap.Modal(document.getElementById('bulkActionsModal'));
                modal.show();
            }

            toggleView(view) {
                this.currentView = view;
                const tableBtn = document.getElementById('tableViewBtn');
                const cardBtn = document.getElementById('cardViewBtn');
                
                if (view === 'table') {
                    tableBtn.classList.add('active');
                    cardBtn.classList.remove('active');
                } else {
                    tableBtn.classList.remove('active');
                    cardBtn.classList.add('active');
                }
                
                this.renderResults();
            }

            showDashboardProgress(percentage, message) {
                const indicator = document.getElementById('dashboardProgressIndicator');
                const progressBar = document.getElementById('dashboardProgressBar');
                const progressText = document.getElementById('dashboardProgressText');
                
                indicator.style.display = 'block';
                progressBar.style.width = percentage + '%';
                progressBar.setAttribute('aria-valuenow', percentage);
                progressText.textContent = message;
            }

            updateDashboardProgress(percentage, message) {
                const progressBar = document.getElementById('dashboardProgressBar');
                const progressText = document.getElementById('dashboardProgressText');
                
                if (progressBar) {
                    progressBar.style.width = percentage + '%';
                    progressBar.setAttribute('aria-valuenow', percentage);
                }
                
                if (progressText) {
                    progressText.textContent = message;
                }
            }

            hideDashboardProgress() {
                const indicator = document.getElementById('dashboardProgressIndicator');
                if (indicator) {
                    indicator.style.display = 'none';
                }
            }

            showExportOptions() {
                const modalHTML = `
                    <div class="modal fade" id="exportOptionsModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-file-export me-2"></i>Export Options
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-success" onclick="domainSearch.exportResults('csv')">
                                            <i class="fas fa-file-csv me-2"></i>Export as CSV
                                        </button>
                                        <button type="button" class="btn btn-info" onclick="domainSearch.exportResults('json')">
                                            <i class="fas fa-file-code me-2"></i>Export as JSON
                                        </button>
                                        <button type="button" class="btn btn-warning" onclick="domainSearch.exportResults('excel')">
                                            <i class="fas fa-file-excel me-2"></i>Export as Excel
                                        </button>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal if any
                const existingModal = document.getElementById('exportOptionsModal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Add modal to DOM and show
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                const modal = new bootstrap.Modal(document.getElementById('exportOptionsModal'));
                modal.show();
            }

            // === BULK OPERATION METHODS ===

            async bulkActivateHTTPS() {
                if (!this.selectedDomains || this.selectedDomains.length === 0) {
                    this.showNotification('Không có domain nào được chọn', 'warning');
                    return;
                }

                const confirmMessage = `Kích hoạt Always Use HTTPS cho ${this.selectedDomains.length} domain đã chọn?`;
                if (!confirm(confirmMessage)) return;

                try {
                    this.showDashboardProgress(0, 'Đang kích hoạt HTTPS cho domains đã chọn...');
                    
                    let successCount = 0;
                    let failedCount = 0;
                    
                    for (let i = 0; i < this.selectedDomains.length; i++) {
                        const domainId = this.selectedDomains[i];
                        const domain = this.results.find(d => d.id === domainId);
                        const domainName = domain ? domain.name : domainId;
                        
                        const progress = ((i + 1) / this.selectedDomains.length) * 100;
                        this.updateDashboardProgress(progress, `Đang xử lý: ${domainName} (${i + 1}/${this.selectedDomains.length})`);
                        
                        try {
                            const response = await fetch(`index.php?action=ssl&api=1&zone_id=${domainId}&setting=always_use_https&value=on`, {
                                method: 'POST'
                            });
                            const data = await response.json();
                            
                            if (data.success) {
                                successCount++;
                            } else {
                                failedCount++;
                            }
                        } catch (error) {
                            failedCount++;
                        }
                        
                        await new Promise(resolve => setTimeout(resolve, 800));
                    }
                    
                    this.hideDashboardProgress();
                    this.showNotification(`Bulk HTTPS kích hoạt hoàn tất! Thành công: ${successCount}, Thất bại: ${failedCount}`, 'success');
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkActionsModal'));
                    if (modal) modal.hide();
                    
                } catch (error) {
                    this.hideDashboardProgress();
                    this.showNotification('Lỗi bulk activate HTTPS: ' + error.message, 'error');
                }
            }

            async bulkCheckSSL() {
                if (!this.selectedDomains || this.selectedDomains.length === 0) {
                    this.showNotification('Không có domain nào được chọn', 'warning');
                    return;
                }

                try {
                    this.showDashboardProgress(0, 'Đang kiểm tra SSL cho domains đã chọn...');
                    
                    for (let i = 0; i < this.selectedDomains.length; i++) {
                        const domainId = this.selectedDomains[i];
                        const domain = this.results.find(d => d.id === domainId);
                        const domainName = domain ? domain.name : domainId;
                        
                        const progress = ((i + 1) / this.selectedDomains.length) * 100;
                        this.updateDashboardProgress(progress, `Đang kiểm tra: ${domainName} (${i + 1}/${this.selectedDomains.length})`);
                        
                        try {
                            const response = await fetch(`index.php?action=zones&api=1&zone_id=${domainId}&include_ssl=true`);
                            const data = await response.json();
                            
                            if (data.success && domain) {
                                const updatedDomain = data.data.zone || data.data;
                                domain.ssl = updatedDomain.ssl || domain.ssl;
                                domain.settings = updatedDomain.settings || domain.settings;
                            }
                        } catch (error) {
                            console.error(`Failed to check SSL for ${domainName}:`, error);
                        }
                        
                        await new Promise(resolve => setTimeout(resolve, 400));
                    }
                    
                    this.hideDashboardProgress();
                    this.renderResults();
                    this.showNotification(`SSL status đã được cập nhật cho ${this.selectedDomains.length} domain đã chọn`, 'success');
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkActionsModal'));
                    if (modal) modal.hide();
                    
                } catch (error) {
                    this.hideDashboardProgress();
                    this.showNotification('Lỗi bulk check SSL: ' + error.message, 'error');
                }
            }

            bulkExport() {
                if (!this.selectedDomains || this.selectedDomains.length === 0) {
                    this.showNotification('Không có domain nào được chọn', 'warning');
                    return;
                }

                const selectedDomainsData = this.results.filter(domain => this.selectedDomains.includes(domain.id));
                
                const headers = ['Tên Domain', 'Trạng thái', 'Gói dịch vụ', 'Always HTTPS', 'Ngày tạo', 'ID'];
                const csvContent = [
                    headers.join(','),
                    ...selectedDomainsData.map(domain => [
                        domain.name,
                        domain.status,
                        domain.plan ? domain.plan.name : 'Unknown',
                        domain.ssl?.always_use_https || domain.settings?.always_use_https ? 'Enabled' : 'Disabled',
                        domain.created_on,
                        domain.id
                    ].join(','))
                ].join('\n');

                // Download file
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', `selected-domains-${new Date().toISOString().split('T')[0]}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                this.showNotification(`Đã export ${selectedDomainsData.length} domain được chọn`, 'success');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('bulkActionsModal'));
                if (modal) modal.hide();
            }

            // === ENHANCED UI METHODS ===

            showBulkSearchSummary(resultInfo) {
                const bulkSummaryContainer = document.getElementById('bulkStatsContainer');
                const bulkSearchSummary = document.getElementById('bulkSearchSummary');
                
                if (!resultInfo.search_stats) {
                    bulkSearchSummary.style.display = 'none';
                    return;
                }

                const bulkQueries = resultInfo.bulk_queries || 1;
                const duplicatesRemoved = resultInfo.duplicates_removed || 0;
                const individualResults = resultInfo.individual_results || 0;

                let summaryHTML = `
                    <div class="row">
                        <div class="col-md-3">
                            <div class="bulk-stat-item text-center">
                                <h6><i class="fas fa-search text-info me-1"></i>Queries</h6>
                                <div class="stat-value text-info">${bulkQueries}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bulk-stat-item text-center">
                                <h6><i class="fas fa-list text-success me-1"></i>Total Matches</h6>
                                <div class="stat-value text-success">${individualResults}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bulk-stat-item text-center">
                                <h6><i class="fas fa-check text-primary me-1"></i>Unique Results</h6>
                                <div class="stat-value text-primary">${resultInfo.count}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bulk-stat-item text-center">
                                <h6><i class="fas fa-filter text-warning me-1"></i>Duplicates</h6>
                                <div class="stat-value text-warning">${duplicatesRemoved}</div>
                            </div>
                        </div>
                    </div>
                `;

                // Add detailed breakdown
                if (resultInfo.search_stats) {
                    summaryHTML += `
                        <div class="mt-3">
                            <h6>Search Term Breakdown:</h6>
                            <div class="row">
                    `;

                    Object.entries(resultInfo.search_stats).forEach(([key, stat]) => {
                        summaryHTML += `
                            <div class="col-md-6 mb-2">
                                <div class="search-term-breakdown">
                                    <strong>"${stat.term}"</strong>: 
                                    <span class="badge bg-primary">${stat.results} results</span>
                                    ${stat.filtered > 0 ? `<span class="badge bg-warning">${stat.filtered} filtered</span>` : ''}
                                </div>
                            </div>
                        `;
                    });

                    summaryHTML += `
                            </div>
                        </div>
                    `;
                }

                bulkSummaryContainer.innerHTML = summaryHTML;
                bulkSearchSummary.style.display = 'block';
            }

            showSearchDetails() {
                const searchInfo = this.lastSearchInfo || {};
                const resultInfo = this.lastResultInfo || {};

                const detailsHTML = `
                    <div class="modal fade" id="searchDetailsModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-search me-2"></i>Chi tiết tìm kiếm
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-primary">Search Parameters</h6>
                                            <div class="search-detail-item">
                                                <span class="search-detail-label">Query:</span>
                                                <span class="search-detail-value">${searchInfo.query || 'N/A'}</span>
                                            </div>
                                            <div class="search-detail-item">
                                                <span class="search-detail-label">Page:</span>
                                                <span class="search-detail-value">${resultInfo.page || 1}</span>
                                            </div>
                                            <div class="search-detail-item">
                                                <span class="search-detail-label">Per Page:</span>
                                                <span class="search-detail-value">${resultInfo.per_page || 20}</span>
                                            </div>
                                            <div class="search-detail-item">
                                                <span class="search-detail-label">Search Fields:</span>
                                                <span class="search-detail-value">${searchInfo.search_fields ? searchInfo.search_fields.join(', ') : 'name, status, plan'}</span>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6 class="text-success">Performance & Cache</h6>
                                            <div class="search-detail-item">
                                                <span class="search-detail-label">Execution Time:</span>
                                                <span class="search-detail-value">${searchInfo.execution_time_ms || 0}ms</span>
                                            </div>
                                            <div class="search-detail-item">
                                                <span class="search-detail-label">Cache Used:</span>
                                                <span class="search-detail-value">${searchInfo.cache_used ? 'Yes' : 'No'}</span>
                                            </div>
                                            <div class="search-detail-item">
                                                <span class="search-detail-label">Cache Hit:</span>
                                                <span class="search-detail-value">${searchInfo.cache_hit ? 'Yes' : 'No'}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal if any
                const existingModal = document.getElementById('searchDetailsModal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Add modal to DOM and show
                document.body.insertAdjacentHTML('beforeend', detailsHTML);
                const modal = new bootstrap.Modal(document.getElementById('searchDetailsModal'));
                modal.show();
            }

            quickSearch(query, status = null) {
                const searchInput = document.getElementById('searchInput');
                const statusFilter = document.getElementById('statusFilter');
                
                if (searchInput) {
                    searchInput.value = query;
                }
                
                if (statusFilter && status) {
                    statusFilter.value = status;
                }
                
                this.performSearch(1);
            }

            renderCardView() {
                const container = document.getElementById('resultsContainer');
                
                if (!this.results || this.results.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state-enhanced">
                            <div class="empty-state-content">
                                <div class="empty-icon mb-4">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h5 class="mb-3">Không tìm thấy domain</h5>
                                <p class="text-muted mb-4">Thử thay đổi từ khóa tìm kiếm hoặc bộ lọc</p>
                            </div>
                        </div>
                    `;
                    return;
                }

                const cardsHTML = this.results.map((domain, index) => {
                    const relevanceScore = domain._relevance_score || 0;
                    const searchTerm = domain._search_term;
                    const hasContext = searchTerm && searchTerm !== domain.name;

                    return `
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card result-card-enhanced" data-domain-id="${domain.id}">
                                <div class="domain-card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="domain-name-large">${domain.name}</div>
                                        ${this.bulkMode ? `
                                        <div class="form-check">
                                            <input class="form-check-input domain-checkbox" type="checkbox" 
                                                   id="card-domain-${domain.id}" 
                                                   value="${domain.id}" 
                                                   onchange="domainSearch.updateBulkSelection()">
                                        </div>
                                        ` : ''}
                                    </div>
                                    <small class="text-muted">ID: ${domain.id.substring(0, 12)}...</small>
                                </div>
                                
                                <div class="domain-card-body">
                                    <div class="domain-metadata-grid">
                                        <div class="metadata-item">
                                            <div class="label">Status</div>
                                            <div class="value">
                                                <span class="status-badge status-${domain.status}">
                                                    ${this.formatStatus(domain.status)}
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="metadata-item">
                                            <div class="label">Plan</div>
                                            <div class="value">
                                                <span class="plan-badge">
                                                    ${domain.plan ? domain.plan.name : 'Unknown'}
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="metadata-item">
                                            <div class="label">Always HTTPS</div>
                                            <div class="value">
                                                ${this.formatHTTPS(domain)}
                                            </div>
                                        </div>
                                        
                                        <div class="metadata-item">
                                            <div class="label">Created</div>
                                            <div class="value">
                                                <small>${this.formatDate(domain.created_on)}</small>
                                            </div>
                                        </div>
                                        
                                        <div class="metadata-item">
                                            <div class="label">Relevance</div>
                                            <div class="value">
                                                ${relevanceScore > 0 ? 
                                                    `<span class="relevance-score">${Math.round(relevanceScore)}%</span>
                                                    ${relevanceScore >= 90 ? '<i class="fas fa-star text-warning"></i>' : 
                                                      relevanceScore >= 70 ? '<i class="fas fa-star-half-alt text-warning"></i>' : 
                                                      '<i class="far fa-star text-muted"></i>'}` 
                                                    : '<span class="text-muted">-</span>'}
                                            </div>
                                        </div>
                                        
                                        <div class="metadata-item">
                                            <div class="label">Actions</div>
                                            <div class="value">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="window.open('?action=dashboard&zone=${domain.id}', '_blank')" title="Dashboard">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="domainSearch.showDomainDetails('${domain.id}')" title="Details">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    ${hasContext ? 
                                        `<div class="search-context mt-2 pt-2 border-top">
                                            <small class="text-info">
                                                <i class="fas fa-search"></i> 
                                                Matched: ${searchTerm}
                                            </small>
                                        </div>` 
                                        : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                container.innerHTML = `
                    <div class="row">
                        ${cardsHTML}
                    </div>
                `;
            }

            testSearch() {
                console.log('Testing search functionality...');
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.value = 'test';
                    this.performSearch(1);
                } else {
                    console.error('Search input not found!');
                    alert('Error: Search input element not found!');
                }
            }

            // Cache Management Functions
            async clearSearchCache() {
                const btn = document.getElementById('clearSearchCacheBtn');
                const originalHTML = btn.innerHTML;
                
                try {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xóa...';
                    
                    const response = await fetch('index.php?action=cache-ops&operation=clear&cache_type=search_results', {
                        method: 'POST'
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.showNotification(`Đã xóa ${data.cleared_items || 'tất cả'} search cache`, 'success');
                        // Update cache stats if available
                        this.updateCacheIndicator();
                    } else {
                        throw new Error(data.error || 'Lỗi khi xóa cache');
                    }
                } catch (error) {
                    this.showNotification('Lỗi khi xóa search cache: ' + error.message, 'error');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            }

            async viewCacheStats() {
                const btn = document.getElementById('viewCacheStatsBtn');
                const panel = document.getElementById('cacheStatusPanel');
                const content = document.getElementById('cacheStatusContent');
                
                // Toggle panel visibility
                if (panel.style.display === 'none' || !panel.style.display) {
                    panel.style.display = 'block';
                    btn.innerHTML = '<i class="fas fa-chart-bar me-2"></i>Ẩn thống kê Cache';
                    
                    try {
                        content.innerHTML = 'Đang tải...';
                        
                        const response = await fetch('index.php?action=cache-ops&operation=stats');
                        const data = await response.json();
                        
                        if (data.success && data.stats) {
                            const stats = data.stats;
                            content.innerHTML = `
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Cache Hit Rate:</strong> ${stats.cache_hit_rate || 0}%<br>
                                        <strong>Total Requests:</strong> ${stats.total_requests || 0}<br>
                                        <strong>Cache Hits:</strong> ${stats.cache_hits || 0}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Valid Files:</strong> ${stats.cache_stats?.valid_files || 0}<br>
                                        <strong>Expired Files:</strong> ${stats.cache_stats?.expired_files || 0}<br>
                                        <strong>Storage Used:</strong> ${(stats.cache_stats?.total_size_mb || 0).toFixed(2)} MB
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">Cập nhật lúc: ${data.formatted_timestamp || ''}</small>
                                </div>
                            `;
                            this.cacheStats = stats;
                        } else {
                            throw new Error(data.error || 'Không thể lấy thống kê cache');
                        }
                    } catch (error) {
                        content.innerHTML = `<span class="text-danger">Lỗi: ${error.message}</span>`;
                    }
                } else {
                    panel.style.display = 'none';
                    btn.innerHTML = '<i class="fas fa-chart-bar me-2"></i>Xem thống kê Cache';
                }
            }

            toggleCacheUsage() {
                const checkbox = document.getElementById('enableCacheToggle');
                this.cacheEnabled = checkbox.checked;
                
                this.updateCacheIndicator();
                this.showNotification(
                    `Cache đã ${this.cacheEnabled ? 'bật' : 'tắt'}. Tìm kiếm tiếp theo sẽ ${this.cacheEnabled ? 'sử dụng' : 'bỏ qua'} cache.`,
                    this.cacheEnabled ? 'success' : 'warning'
                );
                
                console.log('Cache usage toggled:', this.cacheEnabled);
            }

            updateCacheIndicator() {
                // Update visual indicators for cache status
                const checkbox = document.getElementById('enableCacheToggle');
                if (checkbox) {
                    checkbox.checked = this.cacheEnabled;
                }

                // Update cache status display in search stats
                const cacheStatus = document.getElementById('cacheStatus');
                if (cacheStatus) {
                    cacheStatus.textContent = `Cache: ${this.cacheEnabled ? 'Enabled' : 'Disabled'}`;
                }

                // Update any cache status icons/badges
                const cacheIndicators = document.querySelectorAll('.cache-indicator');
                cacheIndicators.forEach(indicator => {
                    if (this.cacheEnabled) {
                        indicator.classList.remove('text-muted');
                        indicator.classList.add('text-success');
                        indicator.title = 'Cache enabled';
                    } else {
                        indicator.classList.remove('text-success');
                        indicator.classList.add('text-muted');
                        indicator.title = 'Cache disabled';
                    }
                });
            }

            updateCacheStatus(searchInfo) {
                // Update cache status based on search results
                const cacheStatus = document.getElementById('cacheStatus');
                const cacheIndicator = document.querySelector('.cache-indicator');
                
                if (cacheStatus && searchInfo) {
                    let statusText = 'Cache: ';
                    let iconClass = 'text-muted';
                    
                    if (searchInfo.cache_used) {
                        if (searchInfo.cache_hit) {
                            statusText += 'Hit ✓';
                            iconClass = 'text-success';
                        } else {
                            statusText += 'Miss';
                            iconClass = 'text-warning';
                        }
                        
                        if (searchInfo.cache_source) {
                            statusText += ` (${searchInfo.cache_source})`;
                        }
                    } else {
                        statusText += 'Disabled';
                        iconClass = 'text-muted';
                    }
                    
                    cacheStatus.textContent = statusText;
                    
                    if (cacheIndicator) {
                        cacheIndicator.className = `fas fa-database cache-indicator me-1 ${iconClass}`;
                    }
                }
            }

            // === MULTI-LINE SEARCH METHODS ===

            toggleSearchMode() {
                const singleContainer = document.querySelector('.single-line-container');
                const multiContainer = document.querySelector('.multi-line-container');
                const toggleBtn = document.getElementById('multiModeToggle');
                
                if (!singleContainer || !multiContainer || !toggleBtn) {
                    console.error('Search mode toggle elements not found');
                    return;
                }

                const isMultiMode = multiContainer.classList.contains('active');

                if (isMultiMode) {
                    // Switch to single line mode
                    multiContainer.classList.remove('active');
                    singleContainer.style.display = 'block';
                    toggleBtn.textContent = 'Nhiều Domain';
                    toggleBtn.classList.remove('active');
                } else {
                    // Switch to multi line mode
                    multiContainer.classList.add('active');
                    singleContainer.style.display = 'none';
                    toggleBtn.textContent = 'Một Domain';
                    toggleBtn.classList.add('active');
                }
            }

            parseMultiDomains(text) {
                if (!text || !text.trim()) {
                    return [];
                }

                // Split by multiple possible separators
                const domains = text
                    .split(/[,;\n\r\t\s]+/)
                    .map(domain => domain.trim())
                    .filter(domain => domain && domain.length > 0);

                // Basic domain validation
                const validDomains = domains.filter(domain => {
                    // Simple domain pattern check
                    const domainPattern = /^[a-zA-Z0-9][a-zA-Z0-9\-_]*[a-zA-Z0-9]*\.[a-zA-Z]{2,}$/;
                    return domainPattern.test(domain);
                });

                return validDomains.slice(0, 50); // Limit to 50 domains
            }

            async performMultiSearch(domains) {
                if (!domains || domains.length === 0) {
                    this.showNotification('Không có domain hợp lệ để tìm kiếm', 'warning');
                    return;
                }

                if (domains.length > 50) {
                    this.showNotification('Tối đa 50 domain cho mỗi lần tìm kiếm', 'warning');
                    return;
                }

                this.showLoading();
                this.isLoading = true;

                try {
                    // Show progress
                    this.showNotification(`Đang tìm kiếm ${domains.length} domain...`, 'info');

                    // Join domains with commas for API search
                    const queryString = domains.join(',');
                    
                    // Use existing search method
                    this.currentQuery = queryString;
                    await this.performSearch(1);
                    
                    // Update history
                    if (window.searchHistoryManager) {
                        window.searchHistoryManager.addSearch(queryString);
                    }

                } catch (error) {
                    console.error('Multi-search error:', error);
                    this.showNotification('Lỗi khi tìm kiếm nhiều domain', 'error');
                } finally {
                    this.hideLoading();
                    this.isLoading = false;
                }
            }

            showMultiSearchResults(results, query) {
                // Display results with special handling for multi-domain search
                const resultsContainer = document.getElementById('searchResults');
                if (!resultsContainer) return;

                const domains = this.parseMultiDomains(query);
                const totalSearched = domains.length;
                const totalFound = results.length;

                // Add multi-search info header
                const infoHeader = `
                    <div class="multi-search-info alert alert-info">
                        <i class="fas fa-search me-2"></i>
                        Tìm kiếm ${totalSearched} domain, tìm thấy ${totalFound} kết quả
                        ${totalFound !== totalSearched ? `(${totalSearched - totalFound} domain không tìm thấy)` : ''}
                    </div>
                `;

                // Use existing result display method but with custom header
                this.displaySearchResults(results);
                
                // Prepend the info header
                resultsContainer.insertAdjacentHTML('afterbegin', infoHeader);
            }
        }

        // ==================== SEARCH HISTORY MANAGEMENT ====================
        
        class SearchHistory {
            constructor() {
                this.maxHistoryItems = 10; // Maximum number of recent searches to store
                this.storageKey = 'domainSearchHistory';
                this.searchCounts = {}; // Track how many times each search was used
                this.loadHistory();
                this.bindEvents();
            }

            loadHistory() {
                try {
                    const stored = localStorage.getItem(this.storageKey);
                    this.history = stored ? JSON.parse(stored) : [];
                    
                    // Load search counts
                    const countsStored = localStorage.getItem(this.storageKey + '_counts');
                    this.searchCounts = countsStored ? JSON.parse(countsStored) : {};
                    
                    console.log('📚 Search history loaded:', this.history.length, 'items');
                } catch (error) {
                    console.error('❌ Error loading search history:', error);
                    this.history = [];
                    this.searchCounts = {};
                }
            }

            saveHistory() {
                try {
                    localStorage.setItem(this.storageKey, JSON.stringify(this.history));
                    localStorage.setItem(this.storageKey + '_counts', JSON.stringify(this.searchCounts));
                } catch (error) {
                    console.error('❌ Error saving search history:', error);
                }
            }

            addSearch(query, status = '', resultsCount = 0) {
                if (!query || query.trim() === '') return;
                
                query = query.trim();
                
                // Create a unique key for this search
                const searchKey = `${query.toLowerCase()}|${status.toLowerCase()}`;
                
                // Remove existing occurrence if present
                this.history = this.history.filter(item => 
                    `${item.query.toLowerCase()}|${item.status.toLowerCase()}` !== searchKey
                );
                
                // Add to beginning
                this.history.unshift({
                    query: query,
                    status: status,
                    resultsCount: resultsCount,
                    timestamp: Date.now(),
                    key: searchKey
                });
                
                // Update search count
                this.searchCounts[searchKey] = (this.searchCounts[searchKey] || 0) + 1;
                
                // Limit history size
                if (this.history.length > this.maxHistoryItems) {
                    const removed = this.history.splice(this.maxHistoryItems);
                    // Clean up counts for removed items
                    removed.forEach(item => delete this.searchCounts[item.key]);
                }
                
                this.saveHistory();
                this.renderHistory();
                console.log('💾 Search saved to history:', query);
            }

            removeSearch(searchKey) {
                this.history = this.history.filter(item => item.key !== searchKey);
                delete this.searchCounts[searchKey];
                this.saveHistory();
                this.renderHistory();
                console.log('🗑️ Search removed from history');
            }

            clearAllHistory() {
                this.history = [];
                this.searchCounts = {};
                this.saveHistory();
                this.renderHistory();
                console.log('🗑️ All search history cleared');
            }

            getPopularSearches() {
                // Return searches sorted by usage count
                return this.history
                    .map(item => ({
                        ...item,
                        count: this.searchCounts[item.key] || 1
                    }))
                    .sort((a, b) => (b.count - a.count) || (b.timestamp - a.timestamp))
                    .slice(0, 5);
            }

            renderHistory() {
                const container = document.getElementById('recentSearches');
                const listElement = document.getElementById('recentSearchesList');
                
                if (!container || !listElement) return;
                
                if (this.history.length === 0) {
                    container.style.display = 'none';
                    return;
                }
                
                container.style.display = 'block';
                
                // Sort by most recent first, but prioritize frequently used ones
                const sortedHistory = this.history
                    .map(item => ({
                        ...item,
                        count: this.searchCounts[item.key] || 1
                    }))
                    .sort((a, b) => {
                        // Priority: count first, then recency
                        if (a.count !== b.count) return b.count - a.count;
                        return b.timestamp - a.timestamp;
                    })
                    .slice(0, 8); // Show max 8 items

                listElement.innerHTML = sortedHistory.map(item => {
                    const displayText = item.query || '(tìm kiếm trống)';
                    const statusText = item.status ? ` [${item.status}]` : '';
                    const countText = item.count > 1 ? `×${item.count}` : '';
                    
                    return `
                        <div class="recent-search-item" 
                             data-query="${this.escapeHtml(item.query)}" 
                             data-status="${this.escapeHtml(item.status)}"
                             title="Tìm kiếm: '${this.escapeHtml(displayText)}' ${statusText} ${countText ? '- Đã dùng ' + countText + ' lần' : ''}, ${this.formatTime(item.timestamp)}"
                             onclick="searchHistory.performSearch('${this.escapeHtml(item.query)}', '${this.escapeHtml(item.status)}')">
                            <span class="search-text">${this.escapeHtml(displayText)}${statusText}</span>
                            ${countText ? `<span class="search-count">${countText}</span>` : ''}
                            <span class="remove-btn" 
                                  onclick="event.stopPropagation(); searchHistory.removeSearch('${this.escapeHtml(item.key)}')"
                                  title="Xóa khỏi lịch sử">×</span>
                        </div>
                    `;
                }).join('');
            }

            performSearch(query, status = '') {
                const searchInput = document.getElementById('searchInput');
                const statusFilter = document.getElementById('statusFilter');
                
                if (searchInput) searchInput.value = query;
                if (statusFilter && status) statusFilter.value = status;
                
                // Trigger search
                if (window.domainSearch && typeof domainSearch.performSearch === 'function') {
                    domainSearch.performSearch(1);
                } else {
                    // Fallback: submit the form
                    document.getElementById('searchForm')?.submit();
                }
                
                console.log('🔍 Performed search from history:', query);
            }

            bindEvents() {
                // Clear all history button
                const clearAllBtn = document.getElementById('clearAllHistoryBtn');
                if (clearAllBtn) {
                    clearAllBtn.addEventListener('click', () => {
                        if (confirm('Bạn có chắc muốn xóa toàn bộ lịch sử tìm kiếm?')) {
                            this.clearAllHistory();
                        }
                    });
                }
                
                // Show/hide history on search input focus
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.addEventListener('focus', () => {
                        if (this.history.length > 0) {
                            document.getElementById('recentSearches').style.display = 'block';
                        }
                    });
                    
                    searchInput.addEventListener('blur', (e) => {
                        // Hide with delay to allow clicking on history items
                        setTimeout(() => {
                            if (!e.relatedTarget || !e.relatedTarget.closest('#recentSearches')) {
                                document.getElementById('recentSearches').style.display = 'none';
                            }
                        }, 200);
                    });
                }
            }

            toggleHistoryDisplay() {
                const container = document.getElementById('recentSearches');
                if (container) {
                    const isVisible = container.style.display !== 'none';
                    container.style.display = isVisible ? 'none' : 'block';
                    
                    if (!isVisible && this.history.length > 0) {
                        this.renderHistory();
                    }
                }
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            formatTime(timestamp) {
                const now = Date.now();
                const diff = now - timestamp;
                
                if (diff < 60000) return 'vừa xong';
                if (diff < 3600000) return Math.floor(diff / 60000) + ' phút trước';
                if (diff < 86400000) return Math.floor(diff / 3600000) + ' giờ trước';
                return Math.floor(diff / 86400000) + ' ngày trước';
            }
        }

        // Global search history instance
        let searchHistory;

        function initializeSearchHistory() {
            try {
                searchHistory = new SearchHistory();
                searchHistory.renderHistory();
                
                // Hook into the existing search functionality
                const originalPerformSearch = domainSearch.performSearch;
                domainSearch.performSearch = async function(page = 1, showLoading = true) {
                    // Call original function
                    const result = await originalPerformSearch.call(this, page, showLoading);
                    
                    // Save to history if this is a new search (page 1)
                    if (page === 1) {
                        const searchInput = document.getElementById('searchInput');
                        const statusFilter = document.getElementById('statusFilter');
                        
                        if (searchInput && statusFilter) {
                            const query = searchInput.value.trim();
                            const status = statusFilter.value;
                            
                            // Get results count from last search
                            const resultsContainer = document.getElementById('resultsContainer');
                            let resultsCount = 0;
                            if (resultsContainer) {
                                const countElement = resultsContainer.querySelector('.search-stats');
                                if (countElement) {
                                    const match = countElement.textContent.match(/(\d+)/);
                                    resultsCount = match ? parseInt(match[1]) : 0;
                                }
                            }
                            
                            searchHistory.addSearch(query, status, resultsCount);
                        }
                    }
                    
                    return result;
                };
                
                console.log('✅ Search history initialized successfully');
            } catch (error) {
                console.error('❌ Error initializing search history:', error);
            }
        }

        // Initialize when page loads with better error handling
        let domainSearch;
        document.addEventListener('DOMContentLoaded', () => {
            // Add visual loading indicator
            const debugInfo = document.createElement('div');
            debugInfo.id = 'debugInfo';
            debugInfo.style.cssText = 'position: fixed; top: 10px; right: 10px; background: #007bff; color: white; padding: 10px; border-radius: 5px; z-index: 9999; font-size: 12px;';
            debugInfo.innerHTML = '🔄 Initializing search...';
            document.body.appendChild(debugInfo);
            
            try {
                console.log('=== SEARCH PAGE DEBUG START ===');
                console.log('DOM Content Loaded - Starting initialization...');
                console.log('Document state:', document.readyState);
                console.log('Location:', window.location.href);
                
                // Check if required elements exist
                const requiredElements = ['searchInput', 'searchForm', 'statusFilter', 'resultsContainer'];
                const elementsStatus = {};
                let allElementsFound = true;
                
                requiredElements.forEach(id => {
                    const element = document.getElementById(id);
                    elementsStatus[id] = !!element;
                    if (!element) allElementsFound = false;
                    console.log(`Element check - ${id}:`, element ? '✅ Found' : '❌ NOT FOUND');
                });
                
                if (!allElementsFound) {
                    const missing = Object.keys(elementsStatus).filter(key => !elementsStatus[key]);
                    console.error('❌ Missing required elements:', missing);
                    debugInfo.style.background = '#dc3545';
                    debugInfo.innerHTML = `❌ Missing elements: ${missing.join(', ')}`;
                    
                    // Show user-friendly error
                    setTimeout(() => {
                        alert(`❌ Lỗi tải trang: Thiếu các thành phần giao diện - ${missing.join(', ')}`);
                    }, 1000);
                } else {
                    console.log('✅ All required elements found, initializing DomainSearch...');
                    debugInfo.innerHTML = '⚡ Initializing DomainSearch...';
                    
                    domainSearch = new DomainSearch();
                    console.log('✅ DomainSearch initialized successfully');
                    
                    // Initialize Search History
                    initializeSearchHistory();
                    
                    debugInfo.style.background = '#28a745';
                    debugInfo.innerHTML = '✅ Search ready';
                    
                    // Add global debug access
                    window.domainSearchDebug = {
                        instance: domainSearch,
                        testJS: () => domainSearch.testJavaScript(),
                        testSearch: () => domainSearch.testSearch(),
                        elements: elementsStatus
                    };
                    
                    // Auto-hide success message after 3 seconds
                    setTimeout(() => {
                        debugInfo.style.opacity = '0.7';
                        debugInfo.style.transform = 'scale(0.8)';
                    }, 3000);
                    
                    console.log('💡 Debug help: Use window.domainSearchDebug in console for testing');
                }
                
                console.log('=== SEARCH PAGE DEBUG END ===');
                
            } catch (error) {
                console.error('💥 CRITICAL ERROR in initialization:', error);
                debugInfo.style.background = '#dc3545';
                debugInfo.innerHTML = '💥 Init failed!';
                
                // Enhanced error reporting
                const errorDetails = {
                    message: error.message,
                    stack: error.stack,
                    elements: Object.keys(elementsStatus || {}).map(id => ({
                        id,
                        exists: !!document.getElementById(id)
                    }))
                };
                console.error('Error details:', errorDetails);
                
                // Show detailed error to user
                const errorMsg = `Lỗi khởi tạo trang tìm kiếm:\n\n${error.message}\n\nVui lòng mở Developer Tools (F12) để xem chi tiết.`;
                setTimeout(() => alert(errorMsg), 1000);
            }
        });
        
        // Sidebar Action Functions
        function submitSearchForm() {
            if(domainSearch) {
                domainSearch.performSearch();
            } else {
                document.getElementById('searchForm').submit();
            }
        }
        
        function clearSearchForm() {
            document.getElementById('searchInput').value = '';
            document.getElementById('multiDomainInput').value = '';
            // Clear results
            const results = document.getElementById('searchResults');
            if(results) results.innerHTML = '';
        }
        
        function exportSearchResults() {
            const results = document.getElementById('searchResults');
            if(!results || !results.innerHTML.trim()) {
                alert('Không có kết quả để xuất!');
                return;
            }
            
            // Simple export to console for now
            console.log('Exporting search results...');
            const data = results.innerHTML;
            const blob = new Blob([data], { type: 'text/html' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'search-results.html';
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        function loadRecentSearches() {
            // Toggle recent searches section
            const recentSearches = document.getElementById('recentSearches');
            if(recentSearches) {
                recentSearches.style.display = recentSearches.style.display === 'none' ? 'block' : 'none';
            }
        }
    </script>
    
            </div> <!-- End search-main -->
        </div> <!-- End search-layout -->
    </div> <!-- End container-fluid -->
    </div> <!-- End main-wrapper -->
    
</body>
</html>