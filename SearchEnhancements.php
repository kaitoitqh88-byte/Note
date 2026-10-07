<?php

/**
 * Apply advanced filtering to search results
 */
function applyAdvancedFiltering($results, $filters) {
    if (empty($results)) return $results;
    
    $filtered = $results;
    
    // Name pattern filtering (regex support)
    if (!empty($filters['name_filter'])) {
        $namePattern = $filters['name_filter'];
        $filtered = array_filter($filtered, function($zone) use ($namePattern) {
            // Support wildcards and regex
            if (strpos($namePattern, '*') !== false) {
                $pattern = '/^' . str_replace('*', '.*', preg_quote($namePattern, '/')) . '$/i';
                return preg_match($pattern, $zone['name']);
            } else if (preg_match('/^\/.*\/$/', $namePattern)) {
                // Direct regex pattern
                return preg_match($namePattern . 'i', $zone['name']);
            } else {
                // Simple substring search
                return stripos($zone['name'], $namePattern) !== false;
            }
        });
    }
    
    // Domain type filtering 
    if (!empty($filters['domain_type'])) {
        $filtered = array_filter($filtered, function($zone) use ($filters) {
            switch ($filters['domain_type']) {
                case 'tld':
                    return preg_match('/\.[a-z]{2,}$/', $zone['name']);
                case 'subdomain':
                    return substr_count($zone['name'], '.') > 1;
                case 'international':
                    return preg_match('/[^\x20-\x7E]/', $zone['name']) || strpos($zone['name'], 'xn--') !== false;
                case 'short':
                    return strlen($zone['name']) <= 8;
                case 'long':
                    return strlen($zone['name']) > 20;
                default:
                    return true;
            }
        });
    }
    
    return array_values($filtered); // Reindex array
}

/**
 * Apply sorting to search results
 */
function applySorting($results, $sortBy = 'name', $sortOrder = 'ASC') {
    if (empty($results)) return $results;
    
    usort($results, function($a, $b) use ($sortBy, $sortOrder) {
        $valueA = null;
        $valueB = null;
        
        switch ($sortBy) {
            case 'name':
                $valueA = strtolower($a['name'] ?? '');
                $valueB = strtolower($b['name'] ?? '');
                break;
            case 'created_on':
                $valueA = strtotime($a['created_on'] ?? '0');
                $valueB = strtotime($b['created_on'] ?? '0');
                break;
            case 'modified_on':
                $valueA = strtotime($a['modified_on'] ?? '0');
                $valueB = strtotime($b['modified_on'] ?? '0');
                break;
            case 'activated_on':
                $valueA = strtotime($a['activated_on'] ?? '0');
                $valueB = strtotime($b['activated_on'] ?? '0');
                break;
            case 'status':
                $valueA = $a['status'] ?? '';
                $valueB = $b['status'] ?? '';
                break;
            case 'plan':
                $valueA = $a['plan']['name'] ?? '';
                $valueB = $b['plan']['name'] ?? '';
                break;
            case 'relevance':
                $valueA = $a['_relevance_score'] ?? 0;
                $valueB = $b['_relevance_score'] ?? 0;
                break;
            default:
                return 0;
        }
        
        if ($valueA === $valueB) return 0;
        
        $comparison = $valueA < $valueB ? -1 : 1;
        return $sortOrder === 'DESC' ? -$comparison : $comparison;
    });
    
    return $results;
}

/**
 * Export search results in different formats
 */
function exportSearchResults($results, $format = 'json', $searchMeta = []) {
    if (empty($results)) {
        return null;
    }
    
    switch (strtolower($format)) {
        case 'csv':
            return exportToCSV($results, $searchMeta);
        case 'xml':
            return exportToXML($results, $searchMeta);
        case 'json':
        default:
            return exportToJSON($results, $searchMeta);
    }
}

/**
 * Export to CSV format
 */
function exportToCSV($results, $searchMeta = []) {
    $output = "Domain Name,Status,Plan,Type,Created On,Modified On,Name Servers\n";
    
    foreach ($results as $zone) {
        $nameServers = isset($zone['name_servers']) ? implode(';', $zone['name_servers']) : '';
        $output .= sprintf('"%s","%s","%s","%s","%s","%s","%s"' . "\n",
            $zone['name'] ?? '',
            $zone['status'] ?? '',
            $zone['plan']['name'] ?? '',
            $zone['type'] ?? '',
            $zone['created_on'] ?? '',
            $zone['modified_on'] ?? '',
            $nameServers
        );
    }
    
    return [
        'content' => $output,
        'content_type' => 'text/csv',
        'filename' => 'cloudflare_domains_' . date('Y-m-d_H-i-s') . '.csv'
    ];
}

/**
 * Export to XML format
 */
function exportToXML($results, $searchMeta = []) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><domains/>');
    $xml->addAttribute('count', count($results));
    $xml->addAttribute('exported_at', date('c'));
    
    if (!empty($searchMeta)) {
        $meta = $xml->addChild('search_meta');
        $meta->addChild('query', htmlspecialchars($searchMeta['query'] ?? ''));
        $meta->addChild('execution_time_ms', $searchMeta['execution_time_ms'] ?? 0);
    }
    
    foreach ($results as $zone) {
        $domain = $xml->addChild('domain');
        $domain->addAttribute('id', $zone['id'] ?? '');
        $domain->addChild('name', htmlspecialchars($zone['name'] ?? ''));
        $domain->addChild('status', $zone['status'] ?? '');
        $domain->addChild('type', $zone['type'] ?? '');
        $domain->addChild('created_on', $zone['created_on'] ?? '');
        $domain->addChild('modified_on', $zone['modified_on'] ?? '');
        
        if (isset($zone['plan'])) {
            $plan = $domain->addChild('plan');
            $plan->addChild('name', htmlspecialchars($zone['plan']['name'] ?? ''));
            $plan->addChild('price', $zone['plan']['price'] ?? 0);
        }
        
        if (isset($zone['name_servers']) && is_array($zone['name_servers'])) {
            $nameServers = $domain->addChild('name_servers');
            foreach ($zone['name_servers'] as $ns) {
                $nameServers->addChild('server', htmlspecialchars($ns));
            }
        }
    }
    
    return [
        'content' => $xml->asXML(),
        'content_type' => 'application/xml',
        'filename' => 'cloudflare_domains_' . date('Y-m-d_H-i-s') . '.xml'
    ];
}

/**
 * Export to JSON format
 */
function exportToJSON($results, $searchMeta = []) {
    $export = [
        'exported_at' => date('c'),
        'count' => count($results),
        'search_meta' => $searchMeta,
        'domains' => $results
    ];
    
    return [
        'content' => json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        'content_type' => 'application/json',
        'filename' => 'cloudflare_domains_' . date('Y-m-d_H-i-s') . '.json'
    ];
}

/**
 * Enhanced domain statistics
 */
function generateDomainStatistics($results) {
    if (empty($results)) return [];
    
    $stats = [
        'total_domains' => count($results),
        'status_breakdown' => [],
        'plan_breakdown' => [],
        'tld_breakdown' => [],
        'creation_timeline' => [],
        'ssl_enabled' => 0
    ];
    
    foreach ($results as $zone) {
        // Status breakdown
        $status = $zone['status'] ?? 'unknown';
        $stats['status_breakdown'][$status] = ($stats['status_breakdown'][$status] ?? 0) + 1;
        
        // Plan breakdown
        $planName = $zone['plan']['name'] ?? 'unknown';
        $stats['plan_breakdown'][$planName] = ($stats['plan_breakdown'][$planName] ?? 0) + 1;
        
        // TLD breakdown
        $domain = $zone['name'] ?? '';
        $tld = strrpos($domain, '.') !== false ? substr($domain, strrpos($domain, '.')) : 'unknown';
        $stats['tld_breakdown'][$tld] = ($stats['tld_breakdown'][$tld] ?? 0) + 1;
        
        // Creation timeline (by month)
        $createdDate = $zone['created_on'] ?? '';
        if ($createdDate) {
            $month = date('Y-m', strtotime($createdDate));
            $stats['creation_timeline'][$month] = ($stats['creation_timeline'][$month] ?? 0) + 1;
        }
        
        // SSL status (simplified check)
        if ($status === 'active') {
            $stats['ssl_enabled']++;
        }
    }
    
    // Sort breakdowns by count
    arsort($stats['status_breakdown']);
    arsort($stats['plan_breakdown']);
    arsort($stats['tld_breakdown']);
    ksort($stats['creation_timeline']);
    
    return $stats;
}
?>